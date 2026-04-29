<?php
session_start();

$host    = "localhost";
$dbname  = "sa2026";
$db_user = "root";
$db_pass = "";

$activity_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $db_user,
        $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->prepare("
        SELECT a.*, u.username, u.nickname
        FROM activities a
        LEFT JOIN users u ON u.user_id = a.user_id
        WHERE a.id = :id
        LIMIT 1
    ");
    $stmt->execute([":id" => $activity_id]);
    $activity = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$activity) {
        header("Location: index.php");
        exit;
    }

} catch (PDOException $e) {
    die("資料庫連線失敗：" . $e->getMessage());
}

// 時間格式化
function fmt_datetime($dt) {
    if (!$dt) return null;
    return date("Y 年 m 月 d 日 H:i", strtotime($dt));
}

function fmt_date($d) {
    if (!$d) return null;
    return date("Y 年 m 月 d 日", strtotime($d));
}

// 是否已截止報名
$is_deadline_passed = strtotime($activity["signup_deadline"]) < strtotime(date("Y-m-d"));

require_once "header.php";
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($activity["title"]) ?> — 輔大社團平台</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;600;700&family=Microsoft+JhengHei&display=swap" rel="stylesheet">

  <style>
    :root {
      --footer-bg: #afbac7;
      --card-shadow: 0 8px 32px rgba(60,80,120,0.10);
      --border: #eef1f5;
      --accent: #2d3a4a;
    }

    * { box-sizing: border-box; }

    body {
      font-family: "Microsoft JhengHei", "微軟正黑體", sans-serif;
      background: #eef1f5;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .view-wrapper {
      flex: 1;
      display: flex;
      align-items: flex-start;
      justify-content: center;
      padding: 48px 16px 60px;
    }

    .view-card {
      width: 100%;
      max-width: 780px;
      background: #fff;
      border-radius: 18px;
      box-shadow: var(--card-shadow);
      overflow: hidden;
    }

    /* ── Header banner ── */
    .view-card-header {
      background: linear-gradient(135deg, #2d3a4a 0%, #3d5268 100%);
      color: #fff;
      padding: 40px 48px 36px;
    }

    .activity-category {
      display: inline-block;
      font-size: 11px;
      padding: 3px 12px;
      border-radius: 999px;
      background: rgba(255,255,255,0.18);
      color: rgba(255,255,255,0.9);
      font-weight: 600;
      letter-spacing: 1px;
      margin-bottom: 14px;
    }

    .activity-title {
      font-family: "Noto Serif TC", serif;
      font-size: 28px;
      font-weight: 700;
      line-height: 1.4;
      margin-bottom: 16px;
    }

    .activity-organizer {
      font-size: 14px;
      opacity: 0.8;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    /* ── Body ── */
    .view-card-body { padding: 40px 48px 48px; }

    /* ── Info grid ── */
    .info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0;
      background: #f8fafc;
      border-radius: 12px;
      border: 1px solid var(--border);
      overflow: hidden;
      margin-bottom: 32px;
    }

    .info-cell {
      padding: 18px 22px;
      border-bottom: 1px solid var(--border);
      border-right: 1px solid var(--border);
    }

    .info-cell:nth-child(even) { border-right: none; }
    .info-cell:nth-last-child(-n+2) { border-bottom: none; }

    /* If odd number of cells, last cell spans full width */
    .info-cell.full-width {
      grid-column: span 2;
      border-right: none;
    }

    .info-cell-label {
      font-size: 11px;
      color: #9aa;
      font-weight: 700;
      letter-spacing: 1px;
      margin-bottom: 6px;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .info-cell-value {
      font-size: 14px;
      font-weight: 600;
      color: #2d3a4a;
      line-height: 1.5;
    }

    .info-cell-value.deadline-passed {
      color: #c0392b;
    }

    /* ── Description ── */
    .desc-section {
      margin-bottom: 36px;
    }

    .desc-label {
      font-size: 13px;
      font-weight: 700;
      color: #9aa;
      letter-spacing: 1px;
      text-transform: uppercase;
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .desc-content {
      font-size: 15px;
      color: #334;
      line-height: 1.85;
      white-space: pre-wrap;
      background: #f8fafc;
      border-radius: 12px;
      padding: 20px 24px;
      border: 1px solid var(--border);
    }

    /* ── Signup button ── */
    .signup-section {
      margin-top: 8px;
    }

    .btn-signup {
      display: block;
      width: 100%;
      background: #2d3a4a;
      color: #fff;
      border: none;
      border-radius: 10px;
      padding: 16px;
      font-size: 17px;
      font-weight: 700;
      letter-spacing: 1px;
      text-align: center;
      text-decoration: none;
      transition: background 0.2s, transform 0.15s, box-shadow 0.15s;
      box-shadow: 0 4px 16px rgba(45,58,74,0.25);
    }

    .btn-signup:hover {
      background: #3d4e62;
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(45,58,74,0.30);
      color: #fff;
    }

    .btn-signup:active { transform: translateY(0); }

    .btn-signup.disabled-btn {
      background: #aab;
      cursor: not-allowed;
      box-shadow: none;
      pointer-events: none;
    }

    .deadline-notice {
      text-align: center;
      margin-top: 10px;
      font-size: 13px;
      color: #c0392b;
      font-weight: 600;
    }

    .back-link {
      display: block;
      text-align: center;
      margin-top: 16px;
      font-size: 13px;
      color: #778;
      text-decoration: none;
    }

    .back-link:hover { color: #3a3a3a; }

    /* ── Footer ── */
    footer {
      background-color: var(--footer-bg);
      color: #333;
      padding: 16px 0;
      text-align: center;
      font-size: 13px;
    }

    @media (max-width: 600px) {
      .info-grid { grid-template-columns: 1fr; }
      .info-cell { border-right: none !important; }
      .info-cell.full-width { grid-column: span 1; }
      .info-cell:last-child { border-bottom: none; }
      .view-card-header { padding: 28px 24px; }
      .view-card-body { padding: 28px 24px 36px; }
      .activity-title { font-size: 22px; }
    }
  </style>
</head>
<body>

<div class="view-wrapper">
  <div class="view-card">

    <!-- 活動標題 banner -->
    <div class="view-card-header">
      <div class="activity-category">
        <i class="bi bi-megaphone me-1"></i>社團活動
      </div>
      <div class="activity-title"><?= htmlspecialchars($activity["title"]) ?></div>
      <div class="activity-organizer">
        <i class="bi bi-building"></i>
        <?= htmlspecialchars($activity["organizer"]) ?>
      </div>
    </div>

    <div class="view-card-body">

      <!-- 活動資訊格狀 -->
      <div class="info-grid">

        <div class="info-cell">
          <div class="info-cell-label"><i class="bi bi-clock"></i>活動時間</div>
          <div class="info-cell-value">
            <?= fmt_datetime($activity["event_start"]) ?>
            <?php if ($activity["event_end"]): ?>
              <br><span style="font-size:12px; color:#9aa;">至</span> <?= fmt_datetime($activity["event_end"]) ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="info-cell">
          <div class="info-cell-label"><i class="bi bi-geo-alt"></i>活動地點</div>
          <div class="info-cell-value"><?= htmlspecialchars($activity["location"]) ?></div>
        </div>

        <div class="info-cell">
          <div class="info-cell-label"><i class="bi bi-currency-dollar"></i>活動費用</div>
          <div class="info-cell-value"><?= htmlspecialchars($activity["fee"]) ?></div>
        </div>

        <div class="info-cell">
          <div class="info-cell-label"><i class="bi bi-people"></i>活動對象</div>
          <div class="info-cell-value"><?= htmlspecialchars($activity["target"]) ?></div>
        </div>

        <div class="info-cell full-width">
          <div class="info-cell-label"><i class="bi bi-calendar-x"></i>報名截止日期</div>
          <div class="info-cell-value <?= $is_deadline_passed ? 'deadline-passed' : '' ?>">
            <?= fmt_date($activity["signup_deadline"]) ?>
            <?php if ($is_deadline_passed): ?>
              <span style="font-size:12px; margin-left:8px;">（報名已截止）</span>
            <?php else:
              $days_left = ceil((strtotime($activity["signup_deadline"]) - time()) / 86400);
            ?>
              <span style="font-size:12px; color:#6e8ab0; margin-left:8px;">（還有 <?= $days_left ?> 天）</span>
            <?php endif; ?>
          </div>
        </div>

      </div>

      <!-- 活動簡介 -->
      <div class="desc-section">
        <div class="desc-label">
          <i class="bi bi-text-paragraph"></i>活動簡介
        </div>
        <div class="desc-content"><?= htmlspecialchars($activity["description"]) ?></div>
      </div>

      <!-- 報名按鈕 -->
      <div class="signup-section">
        <?php if ($is_deadline_passed): ?>
          <a href="#" class="btn-signup disabled-btn">
            <i class="bi bi-calendar-x me-2"></i>報名已截止
          </a>
          <div class="deadline-notice">此活動報名已於 <?= fmt_date($activity["signup_deadline"]) ?> 截止</div>
        <?php else: ?>
          <a href="#" class="btn-signup">
            <i class="bi bi-pencil-square me-2"></i>我要報名
          </a>
        <?php endif; ?>
      </div>

      <a href="index.php" class="back-link">
        <i class="bi bi-arrow-left me-1"></i>返回社團平台首頁
      </a>

    </div>
  </div>
</div>

<?php require "footer.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>