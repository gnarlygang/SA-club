<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/api/db.php";

$activity_id     = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$current_user_id = $_SESSION['user_id'] ?? 0;

try {
    $stmt = $pdo->prepare("
        SELECT a.*, u.username, u.nickname,
               c.name AS club_name, c.image AS club_image, c.id AS club_id,
               CASE WHEN f.id IS NOT NULL THEN 1 ELSE 0 END AS is_favorited
        FROM activities a
        LEFT JOIN users u ON u.user_id = a.user_id
        LEFT JOIN clubs c ON c.user_id = a.user_id
        LEFT JOIN favorites f ON f.item_id = a.id AND f.item_type = 'activity' AND f.user_id = :uid
        WHERE a.id = :id
        LIMIT 1
    ");
    $stmt->execute([":id" => $activity_id, ":uid" => $current_user_id]);
    $activity = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$activity) {
        header("Location: index.php");
        exit;
    }

    // 相關活動（同社團，排除自己）
    $stmt2 = $pdo->prepare("
        SELECT a.id, a.title, a.event_start, a.signup_deadline
        FROM activities a
        LEFT JOIN clubs c ON c.user_id = a.user_id
        WHERE c.id = :club_id AND a.id != :aid
        ORDER BY a.created_at DESC
        LIMIT 3
    ");
    $stmt2->execute([":club_id" => $activity['club_id'], ":aid" => $activity_id]);
    $related = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // 查詢此活動對應的開放表單
    $formStmt = $pdo->prepare("
        SELECT id FROM forms
        WHERE activity_id = ? AND status = 'open'
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $formStmt->execute([$activity_id]);
    $actForm = $formStmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("資料庫查詢失敗：" . $e->getMessage());
}

function fmt_datetime($dt) {
    if (!$dt) return null;
    return date("Y 年 m 月 d 日 H:i", strtotime($dt));
}
function fmt_date($d) {
    if (!$d) return null;
    return date("Y 年 m 月 d 日", strtotime($d));
}

$is_deadline_passed = !empty($activity["signup_deadline"]) &&
                      strtotime($activity["signup_deadline"]) < strtotime(date("Y-m-d"));
$isFav = !empty($activity['is_favorited']);
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
      --card-shadow: 0 8px 32px rgba(60,80,120,0.10);
      --border: #eef1f5;
      --accent: #2d3a4a;
    }
    * { box-sizing: border-box; }
    body {
      font-family: "Microsoft JhengHei", sans-serif;
      background: #eef1f5;
      min-height: 100vh;
      display: flex; flex-direction: column;
    }
    .view-wrapper {
      flex: 1;
      display: flex; align-items: flex-start; justify-content: center;
      padding: 48px 16px 60px;
    }
    .view-card {
      width: 100%; max-width: 780px;
      background: #fff; border-radius: 18px;
      box-shadow: var(--card-shadow); overflow: hidden;
    }

    /* Back button */
    .back-top-bar {
      padding: 14px 24px 0;
      background: #fff;
    }
    .back-top-btn {
      display: inline-flex; align-items: center; gap: 6px;
      color: #5a6a7a; font-size: 13px; font-weight: 600;
      text-decoration: none; padding: 6px 12px;
      border-radius: 7px; border: 1px solid var(--border);
      background: #f8fafc;
      transition: background .15s, color .15s;
    }
    .back-top-btn:hover {
      background: #2d3a4a; color: #fff; border-color: #2d3a4a;
    }

    /* Header */
    .view-card-header {
      background: linear-gradient(135deg, #2d3a4a 0%, #3d5268 100%);
      color: #fff; padding: 40px 48px 36px; position: relative;
      margin-top: 14px;
    }
    .header-actions {
      position: absolute; top: 20px; right: 24px;
      display: flex; gap: 8px;
    }
    .fav-btn {
      display: inline-flex; align-items: center; gap: 6px;
      background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3);
      color: #fff; border-radius: 8px; padding: 7px 14px;
      font-size: 13px; cursor: pointer; transition: all .2s;
    }
    .fav-btn:hover { background: rgba(255,255,255,0.25); }
    .fav-btn.saved { background: #e74c3c; border-color: #e74c3c; }
    .fav-btn svg { width: 15px; height: 15px; }

    .share-btn {
      display: inline-flex; align-items: center; gap: 6px;
      background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3);
      color: #fff; border-radius: 8px; padding: 7px 14px;
      font-size: 13px; cursor: pointer; transition: all .2s;
    }
    .share-btn:hover { background: rgba(255,255,255,0.25); }

    .activity-category {
      display: inline-block; font-size: 11px; padding: 3px 12px;
      border-radius: 999px; background: rgba(255,255,255,0.18);
      color: rgba(255,255,255,0.9); font-weight: 600;
      letter-spacing: 1px; margin-bottom: 14px;
    }
    .activity-title {
      font-family: "Noto Serif TC", serif;
      font-size: 28px; font-weight: 700; line-height: 1.4; margin-bottom: 16px;
    }
    .activity-organizer {
      font-size: 14px; opacity: 0.8;
      display: flex; align-items: center; gap: 6px;
    }
    .club-avatar {
      width: 28px; height: 28px; border-radius: 50%;
      object-fit: cover; border: 2px solid rgba(255,255,255,0.4);
    }

    /* Body */
    .view-card-body { padding: 40px 48px 48px; }

    .info-grid {
      display: grid; grid-template-columns: 1fr 1fr;
      gap: 0; background: #f8fafc;
      border-radius: 12px; border: 1px solid var(--border);
      overflow: hidden; margin-bottom: 32px;
    }
    .info-cell {
      padding: 18px 22px;
      border-bottom: 1px solid var(--border);
      border-right: 1px solid var(--border);
    }
    .info-cell:nth-child(even) { border-right: none; }
    .info-cell:nth-last-child(-n+2) { border-bottom: none; }
    .info-cell.full-width { grid-column: span 2; border-right: none; }
    .info-cell-label {
      font-size: 11px; color: #9aa; font-weight: 700;
      letter-spacing: 1px; margin-bottom: 6px;
      display: flex; align-items: center; gap: 5px;
    }
    .info-cell-value {
      font-size: 14px; font-weight: 600; color: #2d3a4a; line-height: 1.5;
    }
    .info-cell-value.deadline-passed { color: #c0392b; }

    /* Description */
    .desc-section { margin-bottom: 36px; }
    .desc-label {
      font-size: 13px; font-weight: 700; color: #9aa;
      letter-spacing: 1px; text-transform: uppercase;
      margin-bottom: 12px; display: flex; align-items: center; gap: 6px;
    }
    .desc-content {
      font-size: 15px; color: #334; line-height: 1.85;
      white-space: pre-wrap; background: #f8fafc;
      border-radius: 12px; padding: 20px 24px; border: 1px solid var(--border);
    }

    /* Signup */
    .signup-section { margin-top: 8px; }
    .btn-signup {
      display: block; width: 100%;
      background: #2d3a4a; color: #fff; border: none;
      border-radius: 10px; padding: 16px; font-size: 17px;
      font-weight: 700; letter-spacing: 1px; text-align: center;
      text-decoration: none; transition: background .2s, transform .15s, box-shadow .15s;
      box-shadow: 0 4px 16px rgba(45,58,74,0.25);
    }
    .btn-signup:hover {
      background: #3d4e62; transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(45,58,74,0.30); color: #fff;
    }
    .btn-signup.disabled-btn {
      background: #aab; cursor: not-allowed;
      box-shadow: none; pointer-events: none;
    }
    .btn-signup.no-form-btn {
      background: #8a96a8; cursor: not-allowed;
      box-shadow: none; pointer-events: none;
    }
    .deadline-notice {
      text-align: center; margin-top: 10px;
      font-size: 13px; color: #c0392b; font-weight: 600;
    }
    .no-form-notice {
      text-align: center; margin-top: 10px;
      font-size: 13px; color: #8a96a8; font-weight: 600;
    }

    /* Related */
    .related-section { margin-top: 36px; padding-top: 32px; border-top: 1px solid var(--border); }
    .related-title {
      font-size: 14px; font-weight: 700; color: #9aa;
      letter-spacing: 1px; text-transform: uppercase;
      margin-bottom: 14px; display: flex; align-items: center; gap: 6px;
    }
    .related-list { display: flex; flex-direction: column; gap: 10px; }
    .related-item {
      display: flex; align-items: center; justify-content: space-between;
      padding: 12px 16px; background: #f8fafc;
      border-radius: 10px; border: 1px solid var(--border);
      text-decoration: none; color: inherit;
      transition: background .15s, box-shadow .15s;
    }
    .related-item:hover { background: #eef1f5; box-shadow: 0 2px 8px rgba(45,58,74,.08); }
    .related-item-title { font-size: 14px; font-weight: 600; color: #2d3a4a; }
    .related-item-date { font-size: 12px; color: #9aa; margin-top: 3px; }
    .related-item-arrow { color: #9aa; font-size: 16px; }

    .back-link {
      display: block; text-align: center; margin-top: 16px;
      font-size: 13px; color: #778; text-decoration: none;
    }
    .back-link:hover { color: #3a3a3a; }

    /* Toast */
    #fav-toast {
      position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9999;
      background: #1a1a2e; color: #fff; padding: .55rem 1.1rem;
      border-radius: 8px; font-size: .8rem; font-weight: 500;
      box-shadow: 0 4px 16px rgba(0,0,0,.2);
      transition: opacity .3s; opacity: 0; pointer-events: none;
    }

    @media (max-width: 600px) {
      .info-grid { grid-template-columns: 1fr; }
      .info-cell { border-right: none !important; }
      .info-cell.full-width { grid-column: span 1; }
      .info-cell:last-child { border-bottom: none; }
      .view-card-header { padding: 28px 24px; }
      .view-card-body { padding: 28px 24px 36px; }
      .activity-title { font-size: 22px; }
      .header-actions { position: static; margin-bottom: 16px; justify-content: flex-end; }
      .back-top-bar { padding: 12px 16px 0; }
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . "/header.php"; ?>

<div class="view-wrapper">
  <div class="view-card">

    <!-- 回上一頁 -->
    <div class="back-top-bar">
      <a href="javascript:history.back()" class="back-top-btn">
        <i class="bi bi-arrow-left"></i> 返回上一頁
      </a>
    </div>

    <!-- Header -->
    <div class="view-card-header">

      <!-- 收藏 & 分享按鈕 -->
      <div class="header-actions">
        <?php if ($current_user_id): ?>
        <button class="fav-btn <?= $isFav ? 'saved' : '' ?>"
                id="favBtn"
                data-id="<?= $activity_id ?>"
                data-type="activity"
                onclick="toggleFavorite(this)">
          <svg viewBox="0 0 24 24" fill="<?= $isFav ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2">
            <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
          </svg>
          <?= $isFav ? '已收藏' : '收藏活動' ?>
        </button>
        <?php endif; ?>
        <button class="share-btn" onclick="shareActivity()">
          <i class="bi bi-share"></i> 分享
        </button>
      </div>

      <div class="activity-category">
        <i class="bi bi-megaphone me-1"></i>社團活動
      </div>
      <div class="activity-title"><?= htmlspecialchars($activity["title"]) ?></div>
      <div class="activity-organizer">
        <?php if (!empty($activity['club_image'])): ?>
          <img class="club-avatar" src="<?= htmlspecialchars($activity['club_image']) ?>" alt="">
        <?php else: ?>
          <i class="bi bi-building"></i>
        <?php endif; ?>
        <?= htmlspecialchars($activity["organizer"]) ?>
        <?php if (!empty($activity['club_id'])): ?>
          <a href="club-detail.php?id=<?= $activity['club_id'] ?>"
             style="color:rgba(255,255,255,0.6); font-size:12px; margin-left:4px;">
            查看社團 →
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Body -->
    <div class="view-card-body">

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
        <div class="desc-label"><i class="bi bi-text-paragraph"></i>活動簡介</div>
        <div class="desc-content"><?= htmlspecialchars($activity["description"]) ?></div>
      </div>

      <!-- 報名按鈕 -->
      <div class="signup-section">
        <?php if ($is_deadline_passed): ?>
          <a href="#" class="btn-signup disabled-btn">
            <i class="bi bi-calendar-x me-2"></i>報名已截止
          </a>
          <div class="deadline-notice">
            此活動報名已於 <?= fmt_date($activity["signup_deadline"]) ?> 截止
          </div>

        <?php elseif (!empty($actForm)): ?>
          <a href="form_apply.php?form_id=<?= $actForm['id'] ?>" class="btn-signup">
            <i class="bi bi-pencil-square me-2"></i>我要報名
          </a>

        <?php else: ?>
          <a href="#" class="btn-signup no-form-btn">
            <i class="bi bi-slash-circle me-2"></i>報名表單尚未開放
          </a>
          <div class="no-form-notice">社團尚未建立此活動的報名表單，請稍後再查看</div>
        <?php endif; ?>
      </div>

      <!-- 相關活動 -->
      <?php if (!empty($related)): ?>
      <div class="related-section">
        <div class="related-title">
          <i class="bi bi-collection"></i>同社團其他活動
        </div>
        <div class="related-list">
          <?php foreach ($related as $rel): ?>
          <a href="activity_view.php?id=<?= $rel['id'] ?>" class="related-item">
            <div>
              <div class="related-item-title"><?= htmlspecialchars($rel['title']) ?></div>
              <div class="related-item-date">
                <?= !empty($rel['event_start']) ? date('Y/m/d', strtotime($rel['event_start'])) : '時間未定' ?>
              </div>
            </div>
            <span class="related-item-arrow">›</span>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <a href="activities.php" class="back-link">
        <i class="bi bi-arrow-left me-1"></i>返回活動列表
      </a>

    </div>
  </div>
</div>

<div id="fav-toast"></div>

<?php require "footer.php"; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleFavorite(btn) {
  const itemId   = btn.dataset.id;
  const itemType = btn.dataset.type;

  fetch("api/toggle_favorite.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: "item_id=" + encodeURIComponent(itemId) + "&item_type=" + encodeURIComponent(itemType)
  })
  .then(r => r.json())
  .then(data => {
    if (!data.success) { alert(data.message); return; }
    const icon = btn.querySelector("svg");
    if (data.favorited) {
      btn.classList.add("saved");
      btn.innerHTML = `<svg viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg> 已收藏`;
      showToast("已加入收藏 ❤️");
    } else {
      btn.classList.remove("saved");
      btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg> 收藏活動`;
      showToast("已取消收藏");
    }
    btn.onclick = () => toggleFavorite(btn);
  })
  .catch(() => alert("收藏操作失敗"));
}

function shareActivity() {
  if (navigator.share) {
    navigator.share({
      title: document.title,
      url: window.location.href
    });
  } else {
    navigator.clipboard.writeText(window.location.href).then(() => {
      showToast("連結已複製到剪貼簿！");
    });
  }
}

function showToast(msg) {
  const t = document.getElementById("fav-toast");
  t.textContent = msg; t.style.opacity = "1";
  clearTimeout(t._t);
  t._t = setTimeout(() => { t.style.opacity = "0"; }, 2000);
}
</script>
</body>
</html>