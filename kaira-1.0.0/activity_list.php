<?php
session_start();

require_once "api/db.php";

try {
    // 取得該社團所有已發佈活動
    $stmt = $pdo->prepare("
        SELECT * FROM activities
        WHERE user_id = :uid
        ORDER BY created_at DESC
    ");
    $stmt->execute([":uid" => $_SESSION["user_id"] ?? null]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("資料庫連線失敗：" . $e->getMessage());
}

function fmt_date($d) {
    if (!$d) return "—";
    return date("Y/m/d", strtotime($d));
}

function fmt_datetime($dt) {
    if (!$dt) return "—";
    return date("Y/m/d H:i", strtotime($dt));
}

require_once "header.php";
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>已發佈活動 — 輔大社團平台</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;600;700&family=Microsoft+JhengHei&display=swap" rel="stylesheet">

  <style>
    :root {
      --footer-bg: #afbac7;
      --card-shadow: 0 8px 32px rgba(60,80,120,0.10);
      --border: #eef1f5;
      --btn-bg: #2d2d2d;
      --btn-hover: #444;
    }

    * { box-sizing: border-box; }

    body {
      font-family: "Microsoft JhengHei", "微軟正黑體", sans-serif;
      background: #eef1f5;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .list-wrapper {
      flex: 1;
      display: flex;
      align-items: flex-start;
      justify-content: center;
      padding: 48px 16px 60px;
    }

    .list-card {
      width: 100%;
      max-width: 860px;
      background: #fff;
      border-radius: 18px;
      box-shadow: var(--card-shadow);
      overflow: hidden;
    }

    /* ── Header ── */
    .list-card-header {
      background: #2d3a4a;
      color: #fff;
      padding: 36px 48px 28px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 16px;
    }

    .list-card-header .logo-text {
      font-family: "Noto Serif TC", serif;
      font-size: 22px;
      font-weight: 700;
      letter-spacing: 3px;
      margin-bottom: 4px;
    }

    .list-card-header .subtitle {
      font-size: 13px;
      opacity: 0.72;
      letter-spacing: 1px;
    }

    .btn-new {
      background: rgba(255,255,255,0.15);
      color: #fff;
      border: 1.5px solid rgba(255,255,255,0.35);
      border-radius: 8px;
      padding: 9px 18px;
      font-size: 13px;
      font-weight: 600;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      white-space: nowrap;
      transition: background 0.2s;
      flex-shrink: 0;
    }

    .btn-new:hover {
      background: rgba(255,255,255,0.25);
      color: #fff;
    }

    /* ── Body ── */
    .list-card-body { padding: 36px 48px 48px; }

    /* ── Summary bar ── */
    .summary-bar {
      display: flex;
      align-items: center;
      gap: 20px;
      margin-bottom: 28px;
      padding-bottom: 20px;
      border-bottom: 1px solid var(--border);
    }

    .summary-chip {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 13px;
      color: #667;
    }

    .summary-chip .num {
      font-size: 20px;
      font-weight: 700;
      color: #2d3a4a;
    }

    /* ── Activity item ── */
    .activity-item {
      display: flex;
      align-items: flex-start;
      gap: 20px;
      padding: 22px 0;
      border-bottom: 1px solid var(--border);
      text-decoration: none;
      color: inherit;
      transition: background 0.15s;
      border-radius: 0;
    }

    .activity-item:last-of-type { border-bottom: none; }

    /* ── Status badge ── */
    .status-col {
      flex-shrink: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 6px;
      width: 64px;
      padding-top: 2px;
    }

    .status-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      margin-top: 5px;
    }

    .status-dot.active   { background: #27ae60; box-shadow: 0 0 0 3px rgba(39,174,96,0.2); }
    .status-dot.ended    { background: #bbb; }
    .status-dot.upcoming { background: #e67e22; box-shadow: 0 0 0 3px rgba(230,126,34,0.2); }

    .status-text {
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 0.5px;
    }

    .status-text.active   { color: #27ae60; }
    .status-text.ended    { color: #aab; }
    .status-text.upcoming { color: #e67e22; }

    /* ── Activity content ── */
    .activity-content { flex: 1; min-width: 0; }

    .activity-title {
      font-size: 16px;
      font-weight: 700;
      color: #1a2535;
      margin-bottom: 6px;
      line-height: 1.4;
    }

    .activity-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      font-size: 12px;
      color: #9aa;
      margin-bottom: 8px;
    }

    .activity-meta span {
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .activity-desc {
      font-size: 13px;
      color: #667;
      line-height: 1.6;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    /* ── Deadline badge ── */
    .deadline-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: 11px;
      padding: 2px 8px;
      border-radius: 999px;
      font-weight: 600;
    }

    .deadline-badge.ok      { background: #eafaf1; color: #27ae60; }
    .deadline-badge.soon    { background: #fef9e7; color: #e67e22; }
    .deadline-badge.passed  { background: #f5f5f5; color: #aab; }

    /* ── Action buttons ── */
    .action-col {
      flex-shrink: 0;
      display: flex;
      flex-direction: column;
      gap: 8px;
      align-items: flex-end;
    }

    .btn-view {
      background: #2d3a4a;
      color: #fff;
      border: none;
      border-radius: 7px;
      padding: 7px 14px;
      font-size: 12px;
      font-weight: 600;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      transition: background 0.2s;
      white-space: nowrap;
    }

    .btn-view:hover { background: #3d4e62; color: #fff; }

    /* ── Empty state ── */
    .empty-state {
      text-align: center;
      padding: 80px 20px;
      color: #aab;
    }

    .empty-state i { font-size: 52px; display: block; margin-bottom: 16px; }
    .empty-state p { font-size: 15px; margin-bottom: 20px; }

    .btn-empty-action {
      background: var(--btn-bg);
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 11px 24px;
      font-size: 14px;
      font-weight: 600;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: background 0.2s;
    }

    .btn-empty-action:hover { background: var(--btn-hover); color: #fff; }

    /* ── No permission ── */
    .no-permission {
      text-align: center;
      padding: 60px 40px;
      color: #aab;
    }

    .no-permission i { font-size: 48px; display: block; margin-bottom: 14px; }

    /* ── Footer ── */
    footer {
      background-color: var(--footer-bg);
      color: #333;
      padding: 16px 0;
      text-align: center;
      font-size: 13px;
    }

    @media (max-width: 600px) {
      .list-card-header { padding: 28px 24px 20px; }
      .list-card-body   { padding: 24px 20px 36px; }
      .activity-item    { flex-wrap: wrap; }
      .action-col       { width: 100%; flex-direction: row; }
    }
  </style>
</head>
<body>

<div class="list-wrapper">
  <div class="list-card">

    <!-- 卡片頂部 -->
    <div class="list-card-header">
      <div>
        <div class="logo-text">已發佈活動</div>
        <div class="subtitle">天主教輔仁大學 社團平台</div>
      </div>
      <?php if (!empty($_SESSION["user_id"])): ?>
        <a href="activity_create.php" class="btn-new">
          <i class="bi bi-plus-lg"></i>發佈新活動
        </a>
      <?php endif; ?>
    </div>

    <?php if (empty($_SESSION["user_id"])): ?>
      <div class="no-permission">
        <i class="bi bi-lock-fill"></i>
        請先登入社團帳號才能查看已發佈活動。<br>
        <a href="login.php" style="color:#6e8ab0; font-weight:600; text-decoration:none; margin-top:12px; display:inline-block;">前往登入</a>
      </div>

    <?php else: ?>
      <div class="list-card-body">

        <?php if (empty($activities)): ?>
          <div class="empty-state">
            <i class="bi bi-megaphone"></i>
            <p>目前還沒有發佈任何活動</p>
            <a href="activity_create.php" class="btn-empty-action">
              <i class="bi bi-plus-lg"></i>立即發佈第一個活動
            </a>
          </div>

        <?php else: ?>

          <!-- 統計列 -->
          <div class="summary-bar">
            <?php
              $total    = count($activities);
              $active   = 0;
              $upcoming = 0;
              $ended    = 0;
              $now = time();
              foreach ($activities as $a) {
                  $start = strtotime($a["event_start"]);
                  $end   = $a["event_end"] ? strtotime($a["event_end"]) : null;
                  if ($end && $now > $end) {
                      $ended++;
                  } elseif ($now < $start) {
                      $upcoming++;
                  } else {
                      $active++;
                  }
              }
            ?>
            <div class="summary-chip">
              <span class="num"><?= $total ?></span> 個活動
            </div>
            <div class="summary-chip">
              <span style="width:8px;height:8px;border-radius:50%;background:#27ae60;display:inline-block;"></span>
              進行中 <?= $active ?>
            </div>
            <div class="summary-chip">
              <span style="width:8px;height:8px;border-radius:50%;background:#e67e22;display:inline-block;"></span>
              即將舉行 <?= $upcoming ?>
            </div>
            <div class="summary-chip">
              <span style="width:8px;height:8px;border-radius:50%;background:#bbb;display:inline-block;"></span>
              已結束 <?= $ended ?>
            </div>
          </div>

          <!-- 活動列表 -->
          <?php foreach ($activities as $a):
            $start_ts = strtotime($a["event_start"]);
            $end_ts   = $a["event_end"] ? strtotime($a["event_end"]) : null;
            $deadline_ts = strtotime($a["signup_deadline"]);
            $days_to_deadline = ceil(($deadline_ts - $now) / 86400);

            // 狀態
            if ($end_ts && $now > $end_ts) {
                $status_class = "ended";
                $status_label = "已結束";
            } elseif ($now < $start_ts) {
                $status_class = "upcoming";
                $status_label = "即將舉行";
            } else {
                $status_class = "active";
                $status_label = "進行中";
            }

            // 報名截止狀態
            if ($days_to_deadline < 0) {
                $dl_class = "passed";
                $dl_label = "已截止";
            } elseif ($days_to_deadline <= 3) {
                $dl_class = "soon";
                $dl_label = "還剩 {$days_to_deadline} 天";
            } else {
                $dl_class = "ok";
                $dl_label = "截止 " . fmt_date($a["signup_deadline"]);
            }
          ?>
            <div class="activity-item">

              <!-- 狀態燈 -->
              <div class="status-col">
                <div class="status-dot <?= $status_class ?>"></div>
                <div class="status-text <?= $status_class ?>"><?= $status_label ?></div>
              </div>

              <!-- 活動資訊 -->
              <div class="activity-content">
                <div class="activity-title"><?= htmlspecialchars($a["title"]) ?></div>
                <div class="activity-meta">
                  <span><i class="bi bi-clock"></i><?= fmt_datetime($a["event_start"]) ?></span>
                  <span><i class="bi bi-geo-alt"></i><?= htmlspecialchars($a["location"]) ?></span>
                  <span><i class="bi bi-people"></i><?= htmlspecialchars($a["target"]) ?></span>
                  <span><i class="bi bi-currency-dollar"></i><?= htmlspecialchars($a["fee"]) ?></span>
                </div>
                <div class="activity-desc"><?= htmlspecialchars($a["description"]) ?></div>
                <div style="margin-top:8px;">
                  <span class="deadline-badge <?= $dl_class ?>">
                    <i class="bi bi-calendar-x"></i><?= $dl_label ?>
                  </span>
                </div>
              </div>

              <!-- 操作按鈕 -->
              <div class="action-col">
                <a href="activity_edit.php?id=<?= $a["id"] ?>" class="btn-view">
                  <i class="bi bi-pencil-square"></i>編輯
                </a>
              </div>

            </div>
          <?php endforeach; ?>

        <?php endif; ?>

      </div>
    <?php endif; ?>

  </div>
</div>

<?php require "footer.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>