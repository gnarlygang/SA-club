<?php
session_start();
require_once "api/db.php";
require_once "header.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :uid LIMIT 1");
    $stmt->execute([":uid" => $_SESSION["user_id"]]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header("Location: login.php");
        exit;
    }

    $user_id = $user["user_id"];

    /* ── 訂閱社團 ── */
    $club_id = $_GET['club_id'] ?? '';
    $sort_by = $_GET['sort_by'] ?? 'created';
    $order   = $_GET['order']   ?? 'desc';

    if ($sort_by === 'event') {
        if ($order === 'desc') {
            $orderBy = "CASE WHEN a.event_start >= NOW() THEN 0 ELSE 1 END ASC,
                        CASE WHEN a.event_start >= NOW() THEN a.event_start END ASC,
                        CASE WHEN a.event_start < NOW() THEN a.event_start END DESC";
        } else {
            $orderBy = "CASE WHEN a.event_start >= NOW() THEN 0 ELSE 1 END ASC,
                        CASE WHEN a.event_start >= NOW() THEN a.event_start END DESC,
                        CASE WHEN a.event_start < NOW() THEN a.event_start END DESC";
        }
    } else {
        $orderBy = ($order === 'desc') ? "a.created_at DESC" : "a.created_at ASC";
    }

    $stmt = $pdo->prepare("
        SELECT c.* FROM subscriptions s
        JOIN clubs c ON s.club_id = c.id
        WHERE s.user_id = ? ORDER BY c.category ASC, c.name ASC
    ");
    $stmt->execute([$user_id]);
    $clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $groupedClubs = [];
    foreach ($clubs as $club) {
        $cat = ($club['category'] !== '') ? $club['category'] : '其他';
        $groupedClubs[$cat][] = $club;
    }

    $where  = "";
    $params = [$user_id];
    if ($club_id !== '') { $where .= " AND c.id = ?"; $params[] = $club_id; }

    $stmt = $pdo->prepare("
        SELECT a.*, c.id AS club_id, c.name AS club_name, c.category AS club_category, c.image AS club_image
        FROM subscriptions s
        JOIN clubs c ON s.club_id = c.id
        JOIN activities a ON a.user_id = c.user_id
        WHERE s.user_id = ? $where ORDER BY $orderBy
    ");
    $stmt->execute($params);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* ── 收藏：活動 ── */
    $stmt = $pdo->prepare("
        SELECT f.id AS favorite_id, f.created_at AS favorited_at,
               a.id, a.title, a.description, a.event_start, a.signup_deadline, a.location, a.fee, a.organizer,
               c.name AS club_name, c.category AS club_category, c.image AS club_image
        FROM favorites f
        JOIN activities a ON f.item_id = a.id
        LEFT JOIN clubs c ON c.user_id = a.user_id
        WHERE f.user_id = ? AND f.item_type = 'activity'
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $activityFavorites = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* ── 收藏：貼文 ── */
    $stmt = $pdo->prepare("
        SELECT f.id AS favorite_id, f.created_at AS favorited_at,
               fp.id, fp.title, fp.content, fp.created_at,
               fc.name AS category_name, u.username, u.nickname
        FROM favorites f
        JOIN forum_posts fp ON f.item_id = fp.id
        LEFT JOIN forum_categories fc ON fp.category_id = fc.id
        LEFT JOIN users u ON fp.user_id = u.user_id
        WHERE f.user_id = ? AND f.item_type = 'post'
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $postFavorites = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("資料庫連線失敗：" . $e->getMessage());
}

$role_map   = [1 => "教師", 2 => "社團", 3 => "學生", 4 => "管理者"];
$role_label = $role_map[$user["role"]] ?? "未知";
$role_icons = [1 => "👨‍🏫", 2 => "🏛️", 3 => "🎓", 4 => "🔧"];
$avatar     = $user["avatar_url"] ?: "https://ui-avatars.com/api/?name=" . urlencode($user["username"]) . "&size=150&background=2d3a4a&color=fff&font-size=0.4";

function buildUrl($club_id, $sort_by, $order) {
    $u = "?tab=subscriptions&sort_by=" . urlencode($sort_by) . "&order=" . urlencode($order);
    if ($club_id !== '') $u .= "&club_id=" . urlencode($club_id);
    return $u;
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>個人資料 — 輔大社團平台</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;600;700&family=Noto+Sans+TC:wght@400;500;700&display=swap" rel="stylesheet">

  <style>
    :root {
      --bg:        #eef1f5;
      --navy:      #2d3a4a;
      --navy-light:#3d4f64;
      --accent:    #4a7fb5;
      --accent2:   #e8f0fa;
      --card-bg:   #ffffff;
      --border:    #dde2ea;
      --text:      #1e2a38;
      --muted:     #7a8a9a;
      --tag-today: #d1fae5;
      --tag-today-text: #065f46;
      --tag-up:    #dbeafe;
      --tag-up-text:#1e40af;
      --tag-exp:   #f1f5f9;
      --tag-exp-text:#64748b;
      --shadow:    0 8px 32px rgba(45,58,74,0.10);
      --radius:    18px;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: "Noto Sans TC", "Microsoft JhengHei", sans-serif;
      background: var(--bg);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      color: var(--text);
    }

    /* ── Page layout ── */
    .page-body {
      flex: 1;
      display: grid;
      grid-template-columns: 300px 1fr;
      gap: 28px;
      max-width: 1280px;
      width: 100%;
      margin: 36px auto;
      padding: 0 24px 60px;
      align-items: start;
    }

    /* ══════════════════════════════
       LEFT: Profile card
    ══════════════════════════════ */
    .profile-col { position: sticky; top: 90px; }

    .profile-card {
      background: var(--card-bg);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      overflow: hidden;
    }

    .profile-card-header {
      background: var(--navy);
      color: #fff;
      padding: 30px 28px 20px;
      text-align: center;
    }
    .profile-card-header .title { font-family: "Noto Serif TC", serif; font-size: 18px; letter-spacing: 3px; }
    .profile-card-header .sub   { font-size: 12px; opacity: .65; letter-spacing: 1px; margin-top: 4px; }

    .avatar-wrap {
      margin-top: -20px;
      display: flex;
      justify-content: center;
      margin-bottom: 10px;
    }
    .avatar-wrap img {
      width: 88px; height: 88px;
      border-radius: 50%;
      border: 4px solid #fff;
      box-shadow: 0 4px 16px rgba(45,58,74,.2);
      object-fit: cover;
    }

    .role-badge {
      display: inline-block;
      font-size: 11px;
      padding: 3px 12px;
      border-radius: 999px;
      background: var(--accent2);
      color: var(--accent);
      font-weight: 700;
      letter-spacing: .5px;
      margin-bottom: 12px;
    }

    .profile-card-body { padding: 4px 24px 24px; }

    .info-row {
      display: flex;
      align-items: center;
      gap: 11px;
      padding: 12px 0;
      border-bottom: 1px solid #f0f3f7;
    }
    .info-row:last-child { border-bottom: none; }
    .info-icon {
      width: 32px; height: 32px;
      border-radius: 9px;
      background: #f0f3f7;
      display: flex; align-items: center; justify-content: center;
      color: var(--accent); font-size: 14px; flex-shrink: 0;
    }
    .info-label { font-size: 10px; color: #9aabb8; letter-spacing: .5px; margin-bottom: 1px; }
    .info-value { font-size: 14px; font-weight: 600; color: var(--navy); }
    .info-value.muted { color: #b0bec5; font-weight: 400; font-style: italic; }

    /* Nav buttons inside profile card */
    .profile-nav { margin-top: 18px; display: flex; flex-direction: column; gap: 8px; }

    .pnav-btn {
      display: flex; align-items: center; gap: 10px;
      width: 100%; border: none; border-radius: 10px;
      padding: 12px 16px;
      font-size: 14px; font-weight: 600; font-family: inherit;
      cursor: pointer;
      transition: background .2s, color .2s;
      text-align: left;
      background: var(--bg);
      color: var(--navy);
    }
    .pnav-btn i { font-size: 16px; color: var(--accent); }
    .pnav-btn:hover { background: var(--accent2); }
    .pnav-btn.active { background: var(--navy); color: #fff; }
    .pnav-btn.active i { color: #fff; }
    .pnav-btn .badge-count {
      margin-left: auto;
      font-size: 11px;
      background: var(--accent2);
      color: var(--accent);
      border-radius: 999px;
      padding: 2px 8px;
      font-weight: 700;
    }
    .pnav-btn.active .badge-count { background: rgba(255,255,255,.2); color: #fff; }

    /* Coming soon lock */
    .pnav-btn.locked { opacity: .5; cursor: default; }
    .pnav-btn.locked:hover { background: var(--bg); }
    .pnav-soon { font-size: 10px; color: var(--muted); margin-left: auto; }

    .btn-edit-profile {
      display: block; width: 100%;
      background: var(--navy); color: #fff;
      border: none; border-radius: 10px;
      padding: 12px; font-size: 14px; font-weight: 600;
      text-align: center; text-decoration: none;
      cursor: pointer; font-family: inherit;
      transition: background .2s;
      margin-top: 16px;
    }
    .btn-edit-profile:hover { background: var(--navy-light); color: #fff; }

    /* ══════════════════════════════
       RIGHT: Panels
    ══════════════════════════════ */
    .right-col {}

    .panel { display: none; }
    .panel.active { display: block; }

    /* ── Welcome / default panel ── */
    .welcome-panel {
      background: var(--card-bg);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 56px 40px;
      text-align: center;
    }
    .welcome-panel .welcome-icon { font-size: 52px; margin-bottom: 16px; }
    .welcome-panel h2 { font-family: "Noto Serif TC", serif; font-size: 24px; color: var(--navy); margin-bottom: 10px; }
    .welcome-panel p { color: var(--muted); font-size: 15px; line-height: 1.8; }

    /* ── Panel header ── */
    .panel-header {
      background: var(--card-bg);
      border-radius: var(--radius) var(--radius) 0 0;
      padding: 24px 28px 16px;
      border-bottom: 1px solid var(--border);
      display: flex; align-items: center; gap: 12px;
    }
    .panel-header h2 { font-size: 20px; font-weight: 700; color: var(--navy); }
    .panel-header .panel-icon { font-size: 22px; }

    /* ── Subscriptions ── */
    .sub-layout {
      display: grid;
      grid-template-columns: 220px 1fr;
      gap: 0;
      background: var(--card-bg);
      border-radius: 0 0 var(--radius) var(--radius);
      box-shadow: var(--shadow);
      overflow: hidden;
    }

    .sub-sidebar {
      border-right: 1px solid var(--border);
      padding: 20px 16px;
      max-height: 75vh;
      overflow-y: auto;
    }
    .sub-sidebar h3 {
      font-size: 11px; font-weight: 700; letter-spacing: 1.5px;
      color: var(--muted); text-transform: uppercase; margin-bottom: 10px;
    }

    .category-box { margin-bottom: 10px; }2
    .category-title {
      background: none; border: none; width: 100%;
      display: flex; align-items: center; justify-content: space-between;
      font-size: 13px; font-weight: 700; color: var(--navy);
      padding: 6px 8px; border-radius: 8px; cursor: pointer;
      transition: background .2s;
    }
    .category-title:hover { background: var(--bg); }
    .category-title::after { content: "›"; font-size: 16px; transition: transform .2s; }
    .category-box.open .category-title::after { transform: rotate(90deg); }
    .category-clubs { display: none; padding-left: 4px; }
    .category-box.open .category-clubs { display: block; }
    
    .side-club {
      display: flex; align-items: center; gap: 9px;
      padding: 7px 8px; border-radius: 9px; text-decoration: none;
      transition: background .2s; margin-bottom: 2px;
    }
    .side-club:hover { background: var(--bg); font-size: 13px; color: var(--navy)}
    .side-club.active { background: var(--accent2); }
    .side-club .club-avatar {
      width: 32px; height: 32px; border-radius: 50%;
      background: var(--bg); overflow: hidden; flex-shrink: 0;
      display: flex; align-items: center; justify-content: center;
    }
    .side-club .club-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .side-club .club-text strong { display: block; font-size: 13px; color: var(--bg); line-height: 1.3; font-weight: 700; }
    .side-club:hover .club-text strong { background: var(--bg); font-size: 13px; color: var(--navy) }
    .side-club.active .club-text strong { background: var(--bg); font-size: 13px; color: var(--navy) }
    .side-club .club-text p { font-size: 11px; color: var(--muted); margin: 0; }

    .sidebar-divider { border-top: 1px solid var(--border); margin: 14px 0; }
    .side-link {
      display: block; padding: 7px 10px; border-radius: 9px;
      font-size: 13px; color: var(--bg); text-decoration: none;
      margin-bottom: 4px; transition: background .2s;
    }
    .side-link:hover { background: var(--bg); font-size: 13px; color: var(--navy)}
    .side-link.active { background: var(--navy); color: #fff; font-weight: 700; }

    /* Sub main */
    .sub-main { padding: 20px 24px; overflow-y: auto; max-height: 75vh; }
    .sub-main h2 { font-size: 18px; margin-bottom: 14px; color: var(--navy); }

    .sort-box {
      display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px;
    }
    .sort-box a {
      font-size: 12px; padding: 6px 13px;
      border-radius: 999px; text-decoration: none;
      background: var(--bg); color: var(--muted);
      border: 1px solid var(--border);
      transition: background .2s, color .2s;
    }
    .sort-box a.active { background: var(--navy); color: #fff; border-color: var(--navy); }

    .activity-list { display: flex; flex-direction: column; gap: 14px; }

    .activity-card {
      background: var(--bg); border-radius: 14px;
      border: 1px solid var(--border);
      padding: 16px; transition: box-shadow .2s;
    }
    .activity-card:hover { box-shadow: 0 4px 20px rgba(45,58,74,.12); }
    .activity-card.expired { opacity: .7; }

    .activity-content { flex: 1; }
    .activity-content h4 { font-size: 15px; font-weight: 700; color: var(--navy); margin-bottom: 5px; }
    .activity-content p  { font-size: 12px; color: var(--muted); margin-bottom: 2px; line-height: 1.6; }

    .tag {
      display: inline-block; font-size: 11px;
      padding: 3px 10px; border-radius: 999px;
      font-weight: 700; margin-top: 6px;
    }
    .tag.today    { background: var(--tag-today); color: var(--tag-today-text); }
    .tag.upcoming { background: var(--tag-up);    color: var(--tag-up-text); }
    .tag.expired  { background: var(--tag-exp);   color: var(--tag-exp-text); }

    .empty-box {
      padding: 40px; text-align: center; color: var(--muted);
      background: var(--bg); border-radius: 14px;
      border: 1px dashed var(--border);
    }

    /* ── Favorites panel ── */
    .fav-panel-body {
      background: var(--card-bg);
      border-radius: 0 0 var(--radius) var(--radius);
      box-shadow: var(--shadow);
      padding: 24px 28px;
    }

    .fav-tabs {
      display: flex; gap: 10px; margin-bottom: 22px;
    }
    .fav-tab-btn {
      border: none; border-radius: 999px;
      padding: 9px 18px; font-size: 13px; font-weight: 700;
      cursor: pointer; font-family: inherit;
      background: var(--bg); color: var(--muted);
      transition: background .2s, color .2s;
    }
    .fav-tab-btn.active { background: var(--navy); color: #fff; }

    .fav-section { display: none; }
    .fav-section.active { display: block; }

    .fav-list { display: flex; flex-direction: column; gap: 16px; }

    .fav-card {
      background: var(--bg); border: 1px solid var(--border);
      border-radius: 16px; padding: 20px;
    }
    .fav-card-top {
      display: flex; justify-content: space-between;
      gap: 12px; align-items: flex-start; margin-bottom: 8px;
    }
    .fav-card-top h3 { font-size: 17px; color: var(--navy); font-weight: 700; }
    .fav-badge {
      flex-shrink: 0; background: var(--accent2);
      color: var(--accent); font-size: 12px; padding: 4px 12px;
      border-radius: 999px; font-weight: 700;
    }
    .fav-meta {
      font-size: 12px; color: var(--muted); margin-bottom: 10px; line-height: 1.8;
    }
    .fav-content {
      color: #4a5568; line-height: 1.8; font-size: 14px; margin-bottom: 14px;
    }
    .fav-actions { display: flex; gap: 10px; }
    .read-btn {
      display: inline-block; background: var(--navy); color: #fff;
      padding: 8px 16px; border-radius: 999px; text-decoration: none;
      font-size: 13px; border: none; cursor: pointer; font-family: inherit; font-weight: 600;
      transition: background .2s;
    }
    .read-btn:hover { background: var(--navy-light); color: #fff; }
    .remove-fav-btn {
      border: none; background: #fee2e2; color: #991b1b;
      padding: 8px 16px; border-radius: 999px; cursor: pointer;
      font-size: 13px; font-family: inherit; font-weight: 600;
      transition: background .2s;
    }
    .remove-fav-btn:hover { background: #fecaca; }

    /* ── Footer ── */
    footer {
      background: #1a2744; color: #333;
      padding: 14px 0; text-align: center; font-size: 12px;
    }

    /* ── Scrollbar ── */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 99px; }
  </style>
</head>
<body>

<!-- Page -->
<div class="page-body">

  <!-- ── Left: Profile ── -->
  <aside class="profile-col">
    <div class="profile-card">
      <div class="profile-card-header">
        <div class="title">個人資料</div>
        <div class="sub">天主教輔仁大學 社團平台</div>
      </div>
      <div class="profile-card-body">
        <div class="avatar-wrap">
          <img src="<?= htmlspecialchars($avatar) ?>"
               alt="頭像"
               onerror="this.src='https://ui-avatars.com/api/?name=User&size=150&background=2d3a4a&color=fff'">
        </div>
        <div class="text-center mb-3">
          <span class="role-badge">
            <?= ($role_icons[$user["role"]] ?? "") . " " . $role_label ?>
          </span>
        </div>

        <div class="info-row">
          <div class="info-icon"><i class="bi bi-person-fill"></i></div>
          <div>
            <div class="info-label">姓名</div>
            <div class="info-value"><?= htmlspecialchars($user["username"]) ?></div>
          </div>
        </div>
        <div class="info-row">
          <div class="info-icon"><i class="bi bi-chat-heart-fill"></i></div>
          <div>
            <div class="info-label">暱稱</div>
            <?php if (!empty($user["nickname"])): ?>
              <div class="info-value"><?= htmlspecialchars($user["nickname"]) ?></div>
            <?php else: ?>
              <div class="info-value muted">尚未設定暱稱</div>
            <?php endif; ?>
          </div>
        </div>
        <div class="info-row">
          <div class="info-icon"><i class="bi bi-envelope-fill"></i></div>
          <div>
            <div class="info-label">電子信箱</div>
            <div class="info-value"><?= htmlspecialchars($user["email"]) ?></div>
          </div>
        </div>
        <div class="info-row">
          <div class="info-icon"><i class="bi bi-fingerprint"></i></div>
          <div>
            <div class="info-label">帳號編號</div>
            <div class="info-value"><?= htmlspecialchars($user["user_id"]) ?></div>
          </div>
        </div>


<div class="notification-toggle-box">
  <div class="notification-text">
    <div class="info-label">通知設定</div>
    <div class="info-value" id="notify-status">
      <?= !empty($user["notification_enabled"]) ? "已開啟通知" : "未開啟通知" ?>
    </div>
  </div>

  <button
    type="button"
    class="notify-toggle-btn <?= !empty($user["notification_enabled"]) ? 'on' : 'off' ?>"
    id="notify-toggle-btn"
    data-enabled="<?= !empty($user["notification_enabled"]) ? '1' : '0' ?>"
  >
    <?= !empty($user["notification_enabled"]) ? "關閉通知" : "開啟通知" ?>
  </button>
</div>



        <!-- Nav buttons -->
        <div class="profile-nav">
          <button class="pnav-btn" id="btn-subscriptions" onclick="showPanel('subscriptions', this)">
            <i class="bi bi-bell-fill"></i>
            訂閱社團
            <span class="badge-count"><?= count($clubs) ?></span>
          </button>
          <button class="pnav-btn" id="btn-favorites" onclick="showPanel('favorites', this)">
            <i class="bi bi-heart-fill"></i>
            我的收藏
            <span class="badge-count"><?= count($activityFavorites) + count($postFavorites) ?></span>
          </button>
          <button class="pnav-btn locked" disabled>
            <i class="bi bi-calendar-check-fill"></i>
            活動紀錄
            <span class="pnav-soon">即將推出</span>
          </button>
        </div>

        <a href="profile_edit.php" class="btn-edit-profile">
          <i class="bi bi-pencil-square me-1"></i>編輯個人資料
        </a>
      </div>
    </div>
  </aside>

  <!-- ── Right panels ── -->
  <div class="right-col">

    <!-- Welcome -->
    <div class="panel active" id="panel-welcome">
      <div class="welcome-panel">
        <div class="welcome-icon">👋</div>
        <h2>歡迎，<?= htmlspecialchars($user["nickname"] ?: $user["username"]) ?>！</h2>
        <p>
          點選左側選單，可以查看你訂閱的社團活動<br>
          或是你收藏的活動與貼文。
        </p>
      </div>
    </div>

    <!-- Subscriptions -->
    <div class="panel" id="panel-subscriptions">
      <div class="panel-header">
        <span class="panel-icon">🔔</span>
        <h2>訂閱社團活動</h2>
      </div>
      <div class="sub-layout">

        <!-- Sidebar -->
        <aside class="sub-sidebar">
          <h3>分類瀏覽</h3>
          <?php foreach ($groupedClubs as $cat => $catClubs): ?>
            <div class="category-box">
              <button type="button" class="category-title">
                <?= htmlspecialchars($cat) ?>（<?= count($catClubs) ?>）
              </button>
              <div class="category-clubs">
                <?php foreach ($catClubs as $club): ?>
                  <a href="javascript:void(0)"
                     class="side-club <?= $club_id == $club['id'] ? 'active' : '' ?>"
                     data-club="<?= htmlspecialchars($club['id']) ?>">
                    <div class="club-text">
                      <strong><?= htmlspecialchars($club['name']) ?></strong>
                      <p><?= htmlspecialchars($club['category'] ?? '') ?></p>
                    </div>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>

          <div class="sidebar-divider"></div>
          <h3>全部訂閱社團</h3>
          <a href="javascript:void(0)" class="side-link <?= $club_id === '' ? 'active' : '' ?>" data-club="">全部社團</a>
          <?php foreach ($clubs as $club): ?>
            <a href="javascript:void(0)"
               class="side-club <?= $club_id == $club['id'] ? 'active' : '' ?>"
               data-club="<?= htmlspecialchars($club['id']) ?>">
              <div class="club-text">
                <strong><?= htmlspecialchars($club['name']) ?></strong>
                <p><?= htmlspecialchars($club['category'] ?? '') ?></p>
              </div>
            </a>
          <?php endforeach; ?>
          <?php if (count($clubs) === 0): ?>
            <p style="color:var(--muted); font-size:13px; padding: 8px;">目前尚未訂閱任何社團</p>
          <?php endif; ?>
        </aside>

        <!-- Main -->
        <div class="sub-main">
          <div class="sort-box" id="sort-box">
            <a href="javascript:void(0)" data-sort="created" data-order="desc"
               class="sort-link <?= $sort_by==='created'&&$order==='desc' ? 'active' : '' ?>">
              發布日期：近到遠
            </a>
            <a href="javascript:void(0)" data-sort="created" data-order="asc"
               class="sort-link <?= $sort_by==='created'&&$order==='asc' ? 'active' : '' ?>">
              發布日期：遠到近
            </a>
            <a href="javascript:void(0)" data-sort="event" data-order="desc"
               class="sort-link <?= $sort_by==='event'&&$order==='desc' ? 'active' : '' ?>">
              活動時間：近到遠
            </a>
            <a href="javascript:void(0)" data-sort="event" data-order="asc"
               class="sort-link <?= $sort_by==='event'&&$order==='asc' ? 'active' : '' ?>">
              活動時間：遠到近
            </a>
          </div>

          <div class="activity-list" id="activity-list">
            <?php
            $now = date('Y-m-d H:i:s');
            if (count($activities) > 0):
              foreach ($activities as $a):
                $eventStart = $a['event_start'] ?? null;
                $eventEnd   = $a['event_end']   ?? null;
                if (!empty($eventEnd))       { $isExpired = strtotime($eventEnd)   < strtotime($now); }
                elseif (!empty($eventStart)) { $isExpired = strtotime($eventStart) < strtotime($now); }
                else                         { $isExpired = false; }
                $isToday    = !empty($eventStart) && date('Y-m-d', strtotime($eventStart)) === date('Y-m-d');
                $isUpcoming = !empty($eventStart) && strtotime($eventStart) > strtotime($now);
            ?>
              <div class="activity-card <?= $isExpired ? 'expired' : '' ?>"
                   data-club="<?= htmlspecialchars($a['club_id']) ?>">
                <div class="activity-content">
                  <h4><?= htmlspecialchars($a['title'] ?? '未命名活動') ?></h4>
                  <p><strong><?= htmlspecialchars($a['club_name'] ?? '') ?></strong>
                    <?php if (!empty($a['club_category'])): ?>／<?= htmlspecialchars($a['club_category']) ?><?php endif; ?>
                  </p>
                  <?php if (!empty($a['created_at'])): ?>
                    <p>發布日期：<?= htmlspecialchars($a['created_at']) ?></p>
                  <?php endif; ?>
                  <?php if (!empty($a['event_start'])): ?>
                    <p>活動開始：<?= htmlspecialchars($a['event_start']) ?></p>
                  <?php endif; ?>
                  <?php if (!empty($a['event_end'])): ?>
                    <p>活動結束：<?= htmlspecialchars($a['event_end']) ?></p>
                  <?php endif; ?>
                  <?php if (!empty($a['location'])): ?>
                    <p>地點：<?= htmlspecialchars($a['location']) ?></p>
                  <?php endif; ?>
                  <?php if ($isExpired): ?>
                    <span class="tag expired">已結束</span>
                  <?php elseif ($isToday): ?>
                    <span class="tag today">今天活動</span>
                  <?php elseif ($isUpcoming): ?>
                    <span class="tag upcoming">即將開始</span>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; else: ?>
              <div class="empty-box">目前沒有符合條件的訂閱活動。</div>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div><!-- /panel-subscriptions -->

    <!-- Favorites -->
    <div class="panel" id="panel-favorites">
      <div class="panel-header">
        <span class="panel-icon">❤️</span>
        <h2>我的收藏</h2>
      </div>
      <div class="fav-panel-body">
        <p style="color:var(--muted); font-size:14px; margin-bottom:16px;">這裡會顯示你收藏的活動與論壇貼文。</p>

        <div class="fav-tabs">
          <button class="fav-tab-btn active" onclick="showFavTab('activities', this)">
            活動收藏 <?= count($activityFavorites) ?>
          </button>
          <button class="fav-tab-btn" onclick="showFavTab('posts', this)">
            貼文收藏 <?= count($postFavorites) ?>
          </button>
        </div>

        <!-- Activity favorites -->
        <div class="fav-section active" id="fav-activities">
          <div class="fav-list">
            <?php if (count($activityFavorites) === 0): ?>
              <div class="empty-box">
                <strong>目前還沒有收藏活動</strong><br>
                <span>可以到 <a href="activities.php" style="color:var(--accent)">活動頁</a> 看看有沒有感興趣的內容。</span>
              </div>
            <?php endif; ?>
            <?php foreach ($activityFavorites as $act): ?>
              <article class="fav-card" id="favorite-activity-<?= htmlspecialchars($act['id']) ?>">
                <div class="fav-card-top">
                  <h3><?= htmlspecialchars($act['title'] ?? '未命名活動') ?></h3>
                  <span class="fav-badge">活動</span>
                </div>
                <div class="fav-meta">
                  社團：<?= htmlspecialchars($act['club_name'] ?? $act['organizer'] ?? '未指定社團') ?>
                  ・活動時間：<?= htmlspecialchars($act['event_start'] ?? '未設定') ?>
                  ・收藏時間：<?= htmlspecialchars($act['favorited_at']) ?>
                </div>
                <div class="fav-content">
                  <?= nl2br(htmlspecialchars(mb_substr($act['description'] ?? '', 0, 130))) ?>
                  <?= mb_strlen($act['description'] ?? '') > 130 ? '...' : '' ?>
                </div>
                <div class="fav-actions">
                  <a href="activity_view.php?id=<?= htmlspecialchars($act['id']) ?>" class="read-btn">查看活動</a>
                  <button class="remove-fav-btn" data-type="activity" data-id="<?= htmlspecialchars($act['id']) ?>"
                    onclick="removeFavorite(this)">取消收藏</button>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Post favorites -->
        <div class="fav-section" id="fav-posts">
          <div class="fav-list">
            <?php if (count($postFavorites) === 0): ?>
              <div class="empty-box">
                <strong>目前還沒有收藏貼文</strong><br>
                <span>可以到 <a href="forum.php" style="color:var(--accent)">論壇頁</a> 看看有沒有感興趣的內容。</span>
              </div>
            <?php endif; ?>
            <?php foreach ($postFavorites as $post): ?>
              <?php $author = $post['nickname'] ?: ($post['username'] ?? '匿名使用者'); ?>
              <article class="fav-card" id="favorite-post-<?= htmlspecialchars($post['id']) ?>">
                <div class="fav-card-top">
                  <h3><?= htmlspecialchars($post['title'] ?? '未命名貼文') ?></h3>
                  <span class="fav-badge">貼文</span>
                </div>
                <div class="fav-meta">
                  作者：<?= htmlspecialchars($author) ?>
                  ・看板：<?= htmlspecialchars($post['category_name'] ?? '未分類') ?>
                  ・收藏時間：<?= htmlspecialchars($post['favorited_at']) ?>
                </div>
                <div class="fav-content">
                  <?= nl2br(htmlspecialchars(mb_substr($post['content'] ?? '', 0, 130))) ?>
                  <?= mb_strlen($post['content'] ?? '') > 130 ? '...' : '' ?>
                </div>
                <div class="fav-actions">
                  <a href="forum_post.php?id=<?= htmlspecialchars($post['id']) ?>" class="read-btn">查看貼文</a>
                  <button class="remove-fav-btn" data-type="post" data-id="<?= htmlspecialchars($post['id']) ?>"
                    onclick="removeFavorite(this)">取消收藏</button>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </div>

      </div>
    </div><!-- /panel-favorites -->

  </div>
</div>

 <!-- <footer>天主教輔仁大學 © 2014-2026 版權所有</footer>-->

<script>
/* ── Panel switching ── */
function showPanel(name, btn) {
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.pnav-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('panel-' + name).classList.add('active');
  btn.classList.add('active');
}

/* ── Category accordion ── */
document.querySelectorAll('.category-title').forEach(function(btn) {
  btn.addEventListener('click', function() {
    this.parentElement.classList.toggle('open');
  });
});

/* ── Subscription sidebar filter (client-side) ── */
document.querySelectorAll('[data-club]').forEach(function(el) {
  el.addEventListener('click', function() {
    const clubId = this.dataset.club;

    // Update active state
    document.querySelectorAll('[data-club]').forEach(e => e.classList.remove('active'));
    this.classList.add('active');

    // Filter cards
    document.querySelectorAll('.activity-card').forEach(function(card) {
      if (clubId === '' || card.dataset.club == clubId) {
        card.style.display = '';
      } else {
        card.style.display = 'none';
      }
    });
  });
});

/* ── Sort links (client-side sort) ── */
document.querySelectorAll('.sort-link').forEach(function(link) {
  link.addEventListener('click', function() {
    document.querySelectorAll('.sort-link').forEach(l => l.classList.remove('active'));
    this.classList.add('active');

    const sort  = this.dataset.sort;
    const order = this.dataset.order;
    const list  = document.getElementById('activity-list');
    const cards = Array.from(list.querySelectorAll('.activity-card'));

    cards.sort(function(a, b) {
      const getDate = (card, attr) => {
        const p = card.querySelector('.activity-content p:nth-child(' + (attr === 'event' ? 3 : 2) + ')');
        if (!p) return '';
        const m = p.textContent.match(/\d{4}-\d{2}-\d{2}[\s\d:]+/);
        return m ? m[0].trim() : '';
      };

      // Re-read from data attributes — easier to store in card
      const da = card_data(a, sort);
      const db = card_data(b, sort);
      if (!da && !db) return 0;
      if (!da) return 1;
      if (!db) return -1;
      return order === 'desc' ? (da < db ? 1 : -1) : (da > db ? 1 : -1);
    });

    cards.forEach(c => list.appendChild(c));
  });
});

function card_data(card, sort) {
  const paras = card.querySelectorAll('.activity-content p');
  const target = sort === 'event' ? '活動開始' : '發布日期';
  for (const p of paras) {
    if (p.textContent.startsWith(target)) {
      const m = p.textContent.match(/\d{4}-\d{2}-\d{2}[\s\d:]+/);
      return m ? m[0].trim() : '';
    }
  }
  return '';
}

/* ── Favorites tabs ── */
function showFavTab(type, btn) {
  document.querySelectorAll('.fav-tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.fav-section').forEach(s => s.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('fav-' + type).classList.add('active');
}

/* ── Remove favorite ── */
function removeFavorite(btn) {
  const itemId   = btn.dataset.id;
  const itemType = btn.dataset.type;

  fetch("api/toggle_favorite.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: "item_id=" + encodeURIComponent(itemId) + "&item_type=" + encodeURIComponent(itemType)
  })
  .then(res => res.json())
  .then(data => {
    if (!data.success) { alert(data.message); return; }
    const card = document.getElementById("favorite-" + itemType + "-" + itemId);
    if (card) card.remove();
  })
  .catch(() => alert("取消收藏失敗"));
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<?php require_once "footer.php"; ?>


<script>
document.addEventListener("DOMContentLoaded", function () {
    const notifyBtn = document.getElementById("notify-toggle-btn");
    const status = document.getElementById("notify-status");

    if (!notifyBtn) return;

    notifyBtn.addEventListener("click", function () {
        fetch("/SA-club/kaira-1.0.0/api/toggle_notification.php", {
            method: "POST"
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                alert(data.message || "通知設定更新失敗");
                return;
            }

            const enabled = Number(data.new) === 1;

            notifyBtn.dataset.enabled = enabled ? "1" : "0";
            notifyBtn.textContent = enabled ? "關閉通知" : "開啟通知";

            notifyBtn.classList.remove("on", "off");
            notifyBtn.classList.add(enabled ? "on" : "off");

            if (status) {
                status.textContent = enabled ? "已開啟通知" : "未開啟通知";
            }
        })
        .catch(err => {
            console.error(err);
            alert("fetch 失敗");
        });
    });
});
</script>




</body>
</html>