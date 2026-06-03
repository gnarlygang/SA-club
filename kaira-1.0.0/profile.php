<?php
session_start();
require_once "api/db.php";

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
        SELECT c.* 
        FROM subscriptions s
        JOIN clubs c ON s.club_id = c.id
        WHERE s.user_id = ? 
        ORDER BY c.category ASC, c.name ASC
    ");
    $stmt->execute([$user_id]);
    $clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $groupedClubs = [];
    foreach ($clubs as $club) {
        $cat = (!empty($club['category'])) ? $club['category'] : '其他';
        $groupedClubs[$cat][] = $club;
    }

    $where  = "";
    $params = [$user_id];

    if ($club_id !== '') {
        $where .= " AND c.id = ?";
        $params[] = $club_id;
    }

    $stmt = $pdo->prepare("
        SELECT 
            a.*, 
            c.id AS club_id, 
            c.name AS club_name, 
            c.category AS club_category, 
            c.image AS club_image
        FROM subscriptions s
        JOIN clubs c ON s.club_id = c.id
        JOIN activities a ON a.user_id = c.user_id
        WHERE s.user_id = ? $where 
        ORDER BY $orderBy
    ");
    $stmt->execute($params);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* ── 收藏：活動 ── */
    $stmt = $pdo->prepare("
        SELECT 
            f.id AS favorite_id, 
            f.created_at AS favorited_at,
            a.id, 
            a.title, 
            a.description, 
            a.event_start, 
            a.signup_deadline, 
            a.location, 
            a.fee, 
            a.organizer,
            c.name AS club_name, 
            c.category AS club_category, 
            c.image AS club_image
        FROM favorites f
        JOIN activities a ON f.item_id = a.id
        LEFT JOIN clubs c ON c.user_id = a.user_id
        WHERE f.user_id = ? 
          AND f.item_type = 'activity'
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $activityFavorites = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* ── 收藏：貼文 ── */
    $stmt = $pdo->prepare("
        SELECT 
            f.id AS favorite_id, 
            f.created_at AS favorited_at,
            fp.id, 
            fp.title, 
            fp.content, 
            fp.created_at,
            fc.name AS category_name, 
            u.username, 
            u.nickname
        FROM favorites f
        JOIN forum_posts fp ON f.item_id = fp.id
        LEFT JOIN forum_categories fc ON fp.category_id = fc.id
        LEFT JOIN users u ON fp.user_id = u.user_id
        WHERE f.user_id = ? 
          AND f.item_type = 'post'
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $postFavorites = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* ── 活動紀錄 ── */
    try {
        $pdo->prepare("
            UPDATE forms SET status = 'closed'
            WHERE status = 'open' AND close_at IS NOT NULL AND close_at < NOW()
        ")->execute();

        $stmt = $pdo->prepare("
            SELECT
                s.id AS submission_id, s.form_id,
                s.status AS submission_status,
                s.confirmed, s.confirmed_at, s.reviewed_at, s.note, s.submitted_at,
                f.title AS form_title, f.need_review, f.status AS form_status,
                a.id AS activity_id, a.title AS activity_title,
                a.event_start, a.event_end, a.location, a.organizer, a.signup_deadline
            FROM form_submissions s
            JOIN forms f ON f.id = s.form_id
            JOIN activities a ON a.id = f.activity_id
            WHERE s.user_id = ?
            ORDER BY s.submitted_at DESC
        ");
        $stmt->execute([$user_id]);
        $activityRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $activityRecords = [];
    }

} catch (PDOException $e) {
    die("資料庫連線失敗：" . $e->getMessage());
}

$role_map   = [1 => "教師", 2 => "社團", 3 => "學生", 4 => "管理者"];
$role_label = $role_map[$user["role"]] ?? "未知";
$role_icons = [1 => "👨‍🏫", 2 => "🏛️", 3 => "🎓", 4 => "🔧"];
$avatar     = $user["avatar_url"] ?: "https://ui-avatars.com/api/?name=" . urlencode($user["username"]) . "&size=150&background=2d3a4a&color=fff&font-size=0.4";

/* ── 活動紀錄分類計數 ── */
$recordCountAll      = count($activityRecords);
$recordCountActive   = $recordCountEnded     = 0;
$recordCountPending  = $recordCountApproved  = 0;
$recordCountRejected = $recordCountConfirmed = 0;
$nowTime = time();

foreach ($activityRecords as $r) {
    $nowTime = time();

    if (!empty($r['event_end'])) {
        $isEnded = strtotime($r['event_end']) < $nowTime;
    } elseif (!empty($r['event_start'])) {
        $isEnded = strtotime($r['event_start']) < $nowTime;
    } else {
        $isEnded = false;
    }
    if ($isEnded) $recordCountEnded++; else $recordCountActive++;
    if ($r['submission_status'] === 'pending')                         $recordCountPending++;
    if ($r['submission_status'] === 'approved' && !$r['confirmed'])   $recordCountApproved++;
    if ($r['submission_status'] === 'rejected')                        $recordCountRejected++;
    if ($r['submission_status'] === 'approved' && $r['confirmed'])    $recordCountConfirmed++;
}

$notifyEnabled = !empty($user["notification_enabled"]);
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
      --tag-today: #d1fae5;  --tag-today-text: #065f46;
      --tag-up:    #dbeafe;  --tag-up-text:    #1e40af;
      --tag-exp:   #f1f5f9;  --tag-exp-text:   #64748b;
      --shadow:    0 8px 32px rgba(45,58,74,0.10);
      --radius:    18px;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: "Noto Sans TC", "Microsoft JhengHei", sans-serif;
      background: var(--bg); min-height: 100vh;
      display: flex; flex-direction: column; color: var(--text);
    }

    /* ── Layout ── */
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
    .profile-col { position: sticky; top: 90px; }

    /* ── Profile card ── */
    .profile-card { background: var(--card-bg); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
    .profile-card-header { background: var(--navy); color: #fff; padding: 30px 28px 20px; text-align: center; }
    .profile-card-header .title { font-family: "Noto Serif TC", serif; font-size: 18px; letter-spacing: 3px; }
    .profile-card-header .sub   { font-size: 12px; opacity: .65; letter-spacing: 1px; margin-top: 4px; }
    .avatar-wrap { margin-top: -20px; display: flex; justify-content: center; margin-bottom: 10px; }
    .avatar-wrap img { width: 88px; height: 88px; border-radius: 50%; border: 4px solid #fff; box-shadow: 0 4px 16px rgba(45,58,74,.2); object-fit: cover; }
    .role-badge { display: inline-block; font-size: 11px; padding: 3px 12px; border-radius: 999px; background: var(--accent2); color: var(--accent); font-weight: 700; letter-spacing: .5px; margin-bottom: 12px; }
    .profile-card-body { padding: 4px 24px 24px; }
    .info-row { display: flex; align-items: center; gap: 11px; padding: 12px 0; border-bottom: 1px solid #f0f3f7; }
    .info-row:last-child { border-bottom: none; }
    .info-icon { width: 32px; height: 32px; border-radius: 9px; background: #f0f3f7; display: flex; align-items: center; justify-content: center; color: var(--accent); font-size: 14px; flex-shrink: 0; }
    .info-label { font-size: 10px; color: #9aabb8; letter-spacing: .5px; margin-bottom: 1px; }
    .info-value { font-size: 14px; font-weight: 600; color: var(--navy); }
    .info-value.muted { color: #b0bec5; font-weight: 400; font-style: italic; }

    /* ── 通知設定列 ── */
    .notify-row {
      display: flex; align-items: center; justify-content: space-between;
      padding: 12px 0; border-bottom: 1px solid #f0f3f7;
    }
    .notify-btn {
      border: none; border-radius: 999px; padding: 5px 14px;
      font-size: 12px; font-weight: 700; cursor: pointer;
      font-family: inherit; transition: background .2s;
    }
    .notify-btn.on  { background: #fee2e2; color: #991b1b; }
    .notify-btn.off { background: #d1fae5; color: #065f46; }
    .notify-btn.on:hover  { background: #fecaca; }
    .notify-btn.off:hover { background: #a7f3d0; }

    /* ── Nav buttons ── */
    .profile-nav { margin-top: 18px; display: flex; flex-direction: column; gap: 8px; }
    .pnav-btn {
      display: flex; align-items: center; gap: 10px;
      width: 100%; border: none; border-radius: 10px;
      padding: 12px 16px; font-size: 14px; font-weight: 600;
      font-family: inherit; cursor: pointer;
      transition: background .2s, color .2s;
      text-align: left; background: var(--bg); color: var(--navy);
    }
    .pnav-btn i { font-size: 16px; color: var(--accent); }
    .pnav-btn:hover { background: var(--accent2); }
    .pnav-btn.active { background: var(--navy); color: #fff; }
    .pnav-btn.active i { color: #fff; }
    .pnav-btn .badge-count {
      margin-left: auto; font-size: 11px;
      background: var(--accent2); color: var(--accent);
      border-radius: 999px; padding: 2px 8px; font-weight: 700;
    }
    .pnav-btn.active .badge-count { background: rgba(255,255,255,.2); color: #fff; }
    .btn-edit-profile {
      display: block; width: 100%;
      background: var(--navy); color: #fff; border: none;
      border-radius: 10px; padding: 12px;
      font-size: 14px; font-weight: 600; text-align: center;
      text-decoration: none; cursor: pointer; font-family: inherit;
      transition: background .2s; margin-top: 16px;
    }
    .btn-edit-profile:hover { background: var(--navy-light); color: #fff; }

    /* ── Panels ── */
    .panel { display: none; }
    .panel.active { display: block; }

    .welcome-panel {
      background: var(--card-bg); border-radius: var(--radius);
      box-shadow: var(--shadow); padding: 56px 40px; text-align: center;
    }
    .welcome-panel .welcome-icon { font-size: 52px; margin-bottom: 16px; }
    .welcome-panel h2 { font-family: "Noto Serif TC", serif; font-size: 24px; color: var(--navy); margin-bottom: 10px; }
    .welcome-panel p  { color: var(--muted); font-size: 15px; line-height: 1.8; }

    .panel-header {
      background: var(--card-bg);
      border-radius: var(--radius) var(--radius) 0 0;
      padding: 24px 28px 16px;
      border-bottom: 1px solid var(--border);
      display: flex; align-items: center; gap: 12px;
    }
    .panel-header h2 { font-size: 20px; font-weight: 700; color: var(--navy); }
    .panel-header .panel-icon { font-size: 22px; }

    /* ── Subscription layout ── */
    .sub-layout {
      display: grid; grid-template-columns: 220px minmax(0, 1fr);
      background: var(--card-bg);
      border-radius: 0 0 var(--radius) var(--radius);
      box-shadow: var(--shadow); overflow: hidden;
    }
    .sub-sidebar {
      border-right: 1px solid var(--border);
      padding: 20px 16px; max-height: 75vh; overflow-y: auto;
    }
    .sub-sidebar h3 { font-size: 11px; font-weight: 700; letter-spacing: 1.5px; color: var(--muted); text-transform: uppercase; margin-bottom: 10px; }
    .category-box { margin-bottom: 10px; }
    .category-title {
      background: none; border: none; width: 100%;
      display: flex; align-items: center; justify-content: space-between;
      font-size: 13px; font-weight: 700; color: var(--navy);
      padding: 6px 8px; border-radius: 8px; cursor: pointer; transition: background .2s;
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
    .side-club:hover { background: var(--bg); }
    .side-club.active { background: var(--accent2); }
    .side-club .club-text strong { display: block; font-size: 13px; color: var(--navy); line-height: 1.3; font-weight: 700; }
    .side-club .club-text p      { font-size: 11px; color: var(--muted); margin: 0; }
    .sidebar-divider { border-top: 1px solid var(--border); margin: 14px 0; }
    .side-link {
      display: block; padding: 7px 10px; border-radius: 9px;
      font-size: 13px; color: var(--navy); text-decoration: none;
      margin-bottom: 4px; transition: background .2s;
    }
    .side-link:hover { background: var(--bg); color: var(--navy); }
    .side-link.active { background: var(--navy); color: #fff; font-weight: 700; }

    .sub-main { padding: 20px 24px; overflow-y: auto; overflow-x: hidden; max-height: 75vh; min-width: 0; }

    /* 右邊排序按鈕：強制同一排 */
    .sort-box {
      display: flex !important;
      flex-direction: row !important;
      flex-wrap: nowrap !important;
      align-items: center !important;
      gap: 8px !important;
      margin-bottom: 20px !important;
      width: 100% !important;
      overflow-x: auto !important;
      overflow-y: hidden !important;
      padding-bottom: 4px !important;
    }
    .sort-box a {
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      white-space: nowrap !important;
      flex: 0 0 auto !important;
      font-size: 12px !important;
      padding: 8px 12px !important;
      border-radius: 999px !important;
      text-decoration: none !important;
      background: var(--bg); color: var(--muted); border: 1px solid var(--border); transition: background .2s, color .2s;
    }
    .sort-box a.active { background: var(--navy) !important; color: #fff !important; border-color: var(--navy) !important; }

    /* 全部訂閱社團下面的文字：白色 */
    .all-club-link .club-text strong { color: #ffffff !important; }
    .all-club-link .club-text p { color: rgba(255,255,255,.78) !important; }

    .activity-list { display: flex; flex-direction: column; gap: 14px; }
    .activity-card {
      background: var(--bg); border-radius: 14px;
      border: 1px solid var(--border); padding: 16px; transition: box-shadow .2s;
    }
    .activity-card:hover { box-shadow: 0 4px 20px rgba(45,58,74,.12); }
    .activity-card.expired { opacity: .7; }
    .activity-content h4 { font-size: 15px; font-weight: 700; color: var(--navy); margin-bottom: 5px; }
    .activity-content p  { font-size: 12px; color: var(--muted); margin-bottom: 2px; line-height: 1.6; }
    .tag { display: inline-block; font-size: 11px; padding: 3px 10px; border-radius: 999px; font-weight: 700; margin-top: 6px; }
    .tag.today    { background: var(--tag-today); color: var(--tag-today-text); }
    .tag.upcoming { background: var(--tag-up);    color: var(--tag-up-text); }
    .tag.expired  { background: var(--tag-exp);   color: var(--tag-exp-text); }

    /* ── Favorites / Records ── */
    .fav-panel-body, .record-panel-body {
      background: var(--card-bg);
      border-radius: 0 0 var(--radius) var(--radius);
      box-shadow: var(--shadow); padding: 24px 28px;
    }
    .fav-tabs, .record-tabs { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 22px; }
    .fav-tab-btn, .record-tab-btn {
      border: none; border-radius: 999px; padding: 9px 18px;
      font-size: 13px; font-weight: 700; cursor: pointer; font-family: inherit;
      background: var(--bg); color: var(--muted); transition: background .2s, color .2s;
    }
    .fav-tab-btn.active, .record-tab-btn.active { background: var(--navy); color: #fff; }
    .fav-section { display: none; }
    .fav-section.active { display: block; }
    .fav-list, .record-list { display: flex; flex-direction: column; gap: 16px; }
    .fav-card, .record-card {
      background: var(--bg); border: 1px solid var(--border);
      border-radius: 16px; padding: 20px; transition: box-shadow .2s;
    }
    .fav-card:hover, .record-card:hover { box-shadow: 0 4px 20px rgba(45,58,74,.12); }
    .fav-card-top, .record-card-top {
      display: flex; justify-content: space-between; gap: 12px;
      align-items: flex-start; margin-bottom: 8px;
    }
    .fav-card-top h3, .record-card-top h3 { font-size: 17px; color: var(--navy); font-weight: 700; line-height: 1.5; }
    .fav-badge { flex-shrink: 0; background: var(--accent2); color: var(--accent); font-size: 12px; padding: 4px 12px; border-radius: 999px; font-weight: 700; }
    .fav-meta, .record-meta { font-size: 12px; color: var(--muted); margin-bottom: 10px; line-height: 1.8; }
    .fav-content { color: #4a5568; line-height: 1.8; font-size: 14px; margin-bottom: 14px; }
    .fav-actions, .record-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .read-btn {
      display: inline-block; background: var(--navy); color: #fff;
      padding: 8px 16px; border-radius: 999px; text-decoration: none;
      font-size: 13px; border: none; cursor: pointer;
      font-family: inherit; font-weight: 600; transition: background .2s;
    }
    .read-btn:hover { background: var(--navy-light); color: #fff; }
    .remove-fav-btn {
      border: none; background: #fee2e2; color: #991b1b;
      padding: 8px 16px; border-radius: 999px; cursor: pointer;
      font-size: 13px; font-family: inherit; font-weight: 600; transition: background .2s;
    }
    .remove-fav-btn:hover { background: #fecaca; }

    /* ── Activity records ── */
    .record-status { flex-shrink: 0; font-size: 12px; padding: 5px 12px; border-radius: 999px; font-weight: 700; }
    .record-status.pending   { background: #fef3c7; color: #92400e; }
    .record-status.approved  { background: #d1fae5; color: #065f46; }
    .record-status.rejected  { background: #fee2e2; color: #991b1b; }
    .record-status.confirmed { background: #dbeafe; color: #1e40af; }
    .record-status.active    { background: #e0f2fe; color: #075985; }
    .record-status.ended     { background: #e5e7eb; color: #4b5563; }
    .record-note {
      background: #fff; border-left: 4px solid var(--accent);
      border-radius: 10px; padding: 10px 12px; color: #4a5568;
      font-size: 13px; line-height: 1.7; margin-bottom: 12px;
    }
    .confirm-record-box {
      background: #f0fdf4; border: 1px solid #bbf7d0;
      border-radius: 12px; padding: 12px 14px; margin-bottom: 12px;
      display: flex; justify-content: space-between; gap: 12px;
      align-items: center; flex-wrap: wrap;
    }
    .confirm-record-box p { color: #166534; font-size: 13px; font-weight: 600; margin: 0; }
    .btn-confirm-record {
      border: none; background: #166534; color: #fff;
      padding: 8px 16px; border-radius: 999px;
      font-size: 13px; font-weight: 700; cursor: pointer; font-family: inherit;
    }
    .btn-confirm-record:hover { background: #14532d; }

    /* ── Empty ── */
    .empty-box {
      padding: 40px; text-align: center;
      color: var(--muted); background: var(--bg);
      border-radius: 14px; border: 1px dashed var(--border);
    }

    /* ── Scrollbar ── */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 99px; }

    /* ── Mobile ── */
    @media (max-width: 860px) {
      .page-body { grid-template-columns: 1fr; }
      .profile-col { position: static; }
      .sub-layout  { grid-template-columns: 1fr; }
      .sub-sidebar { max-height: none; border-right: none; border-bottom: 1px solid var(--border); }
    }
  </style>
</head>

<body>
<?php require_once "header.php"; ?>

<div class="page-body">

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

<script>
document.addEventListener("DOMContentLoaded", function () {
  const notifyBtn = document.getElementById("notify-toggle-btn");
  const notifyStatus = document.getElementById("notify-status");

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

      const enabled = Number(data.notification_enabled) === 1;

      notifyBtn.dataset.enabled = enabled ? "1" : "0";
      notifyBtn.textContent = enabled ? "關閉通知" : "開啟通知";

      notifyBtn.classList.remove("on", "off");
      notifyBtn.classList.add(enabled ? "on" : "off");

      if (notifyStatus) {
        notifyStatus.textContent = enabled ? "已開啟通知" : "未開啟通知";
      }
    })
    .catch(() => {
      alert("通知設定連線失敗");
    });
  });
});
</script>

        <!-- 左側導覽 -->
        <div class="profile-nav">
          <button class="pnav-btn active" onclick="showPanel('welcome', this)">
            <i class="bi bi-house-fill"></i>
            首頁
          </button>
          <button class="pnav-btn" onclick="showPanel('subscriptions', this)">
            <i class="bi bi-bell-fill"></i>
            訂閱社團
            <span class="badge-count"><?= count($clubs) ?></span>
          </button>
          <button class="pnav-btn" onclick="showPanel('favorites', this)">
            <i class="bi bi-heart-fill"></i>
            我的收藏
            <span class="badge-count"><?= count($activityFavorites) + count($postFavorites) ?></span>
          </button>
          <button class="pnav-btn" onclick="showPanel('records', this)">
            <i class="bi bi-calendar-check-fill"></i>
            活動紀錄
            <span class="badge-count"><?= count($activityRecords) ?></span>
          </button>
        </div>

        <a href="profile_edit.php" class="btn-edit-profile">
          <i class="bi bi-pencil-square me-1"></i>編輯個人資料
        </a>
      </div><!-- /profile-card-body -->
    </div><!-- /profile-card -->
  </aside><!-- /profile-col -->

  <!-- ════ RIGHT: Content ════ -->
  <div class="right-col">

    <div class="panel active" id="panel-welcome">
      <div class="welcome-panel">
        <div class="welcome-icon">👋</div>
        <h2>歡迎，<?= htmlspecialchars($user["nickname"] ?: $user["username"]) ?>！</h2>
        <p>
          點選左側選單，可以查看你訂閱的社團活動、收藏內容<br>
          或是你已報名的活動紀錄。
        </p>
      </div>
    </div>

    <div class="panel" id="panel-subscriptions">
      <div class="panel-header">
        <span class="panel-icon">🔔</span>
        <h2>訂閱社團活動</h2>
      </div>
      <div class="sub-layout">
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
          <a href="javascript:void(0)" class="side-link <?= $club_id === '' ? 'active' : '' ?>" data-club="">
            全部社團
          </a>
          <?php foreach ($clubs as $club): ?>
            <a href="javascript:void(0)"
               class="side-club all-club-link <?= $club_id == $club['id'] ? 'active' : '' ?>"
               data-club="<?= htmlspecialchars($club['id']) ?>">
              <div class="club-text">
                <strong style="color:#ffffff !important;"><?= htmlspecialchars($club['name']) ?></strong>
                <p style="color:rgba(255,255,255,.78) !important;"><?= htmlspecialchars($club['category'] ?? '') ?></p>
              </div>
            </a>
          <?php endforeach; ?>
          <?php if (count($clubs) === 0): ?>
            <p style="color:var(--muted); font-size:13px; padding:8px;">目前尚未訂閱任何社團</p>
          <?php endif; ?>
        </aside>

        <div class="sub-main">
          <div class="sort-box" id="sort-box" style="display:flex !important; flex-wrap:nowrap !important; gap:8px !important; align-items:center !important; overflow-x:auto !important; width:100% !important;">
            <a href="javascript:void(0)" data-sort="created" data-order="desc"
               style="white-space:nowrap !important; flex:0 0 auto !important; padding:8px 12px !important; font-size:12px !important;" class="sort-link <?= $sort_by==='created'&&$order==='desc' ? 'active' : '' ?>">
              發布日期：近到遠
            </a>

            <a href="javascript:void(0)" data-sort="created" data-order="asc"
               style="white-space:nowrap !important; flex:0 0 auto !important; padding:8px 12px !important; font-size:12px !important;" class="sort-link <?= $sort_by==='created'&&$order==='asc' ? 'active' : '' ?>">
              發布日期：遠到近
            </a>

            <a href="javascript:void(0)" data-sort="event" data-order="desc"
               style="white-space:nowrap !important; flex:0 0 auto !important; padding:8px 12px !important; font-size:12px !important;" class="sort-link <?= $sort_by==='event'&&$order==='desc' ? 'active' : '' ?>">
              活動時間：近到遠
            </a>

            <a href="javascript:void(0)" data-sort="event" data-order="asc"
               style="white-space:nowrap !important; flex:0 0 auto !important; padding:8px 12px !important; font-size:12px !important;" class="sort-link <?= $sort_by==='event'&&$order==='asc' ? 'active' : '' ?>">
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
                $isExpired  = !empty($eventEnd)
                    ? strtotime($eventEnd) < strtotime($now)
                    : (!empty($eventStart) ? strtotime($eventStart) < strtotime($now) : false);
                $isToday    = !empty($eventStart) && date('Y-m-d', strtotime($eventStart)) === date('Y-m-d');
                $isUpcoming = !empty($eventStart) && strtotime($eventStart) > strtotime($now);
            ?>

              <div class="activity-card <?= $isExpired ? 'expired' : '' ?>"
                   data-club="<?= htmlspecialchars($a['club_id']) ?>">
                <div class="activity-content">
                  <h4><?= htmlspecialchars($a['title'] ?? '未命名活動') ?></h4>

                  <p>
                    <strong><?= htmlspecialchars($a['club_name'] ?? '') ?></strong>
                    <?php if (!empty($a['club_category'])): ?>
                      ／<?= htmlspecialchars($a['club_category']) ?>
                    <?php endif; ?>
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

        <div class="fav-section active" id="fav-activities">
          <div class="fav-list">

            <?php if (count($activityFavorites) === 0): ?>
              <div class="empty-box">
                <strong>目前還沒有收藏活動</strong><br>
                <span>
                  可以到 <a href="activities.php" style="color:var(--accent)">活動頁</a> 看看有沒有感興趣的內容。
                </span>
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
                  <a href="activity_view.php?id=<?= htmlspecialchars($act['id']) ?>" class="read-btn">
                    查看活動
                  </a>

                  <button class="remove-fav-btn"
                          data-type="activity"
                          data-id="<?= htmlspecialchars($act['id']) ?>"
                          onclick="removeFavorite(this)">
                    取消收藏
                  </button>
                </div>
              </article>
            <?php endforeach; ?>

          </div>
        </div>

        <div class="fav-section" id="fav-posts">
          <div class="fav-list">

            <?php if (count($postFavorites) === 0): ?>
              <div class="empty-box">
                <strong>目前還沒有收藏貼文</strong><br>
                <span>
                  可以到 <a href="forum.php" style="color:var(--accent)">論壇頁</a> 看看有沒有感興趣的內容。
                </span>
              </div>
            <?php endif; ?>
            <?php foreach ($postFavorites as $post):
              $author = $post['nickname'] ?: ($post['username'] ?? '匿名使用者');
            ?>
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
                  <a href="forum_post.php?id=<?= htmlspecialchars($post['id']) ?>" class="read-btn">
                    查看貼文
                  </a>

                  <button class="remove-fav-btn"
                          data-type="post"
                          data-id="<?= htmlspecialchars($post['id']) ?>"
                          onclick="removeFavorite(this)">
                    取消收藏
                  </button>
                </div>
              </article>
            <?php endforeach; ?>

          </div>
        </div>
      </div>
    </div><!-- /panel-favorites -->

    <div class="panel" id="panel-records">
      <div class="panel-header">
        <span class="panel-icon">📋</span>
        <h2>活動紀錄</h2>
      </div>
      <div class="record-panel-body">
        <p style="color:var(--muted); font-size:14px; margin-bottom:16px;">
          這裡會顯示你已報名的活動、審核狀態與確認參與紀錄。
        </p>
        <div class="record-tabs">
          <button class="record-tab-btn active" onclick="showRecordTab('all', this)">
            全部 <?= $recordCountAll ?>
          </button>

          <button class="record-tab-btn" onclick="showRecordTab('active', this)">
            未結束 <?= $recordCountActive ?>
          </button>

          <button class="record-tab-btn" onclick="showRecordTab('ended', this)">
            已結束 <?= $recordCountEnded ?>
          </button>

          <button class="record-tab-btn" onclick="showRecordTab('pending', this)">
            待審核 <?= $recordCountPending ?>
          </button>

          <button class="record-tab-btn" onclick="showRecordTab('approved', this)">
            審核通過 <?= $recordCountApproved ?>
          </button>

          <button class="record-tab-btn" onclick="showRecordTab('rejected', this)">
            未通過 <?= $recordCountRejected ?>
          </button>

          <button class="record-tab-btn" onclick="showRecordTab('confirmed', this)">
            已確認參與 <?= $recordCountConfirmed ?>
          </button>
        </div>

        <div class="record-list">

          <?php if (count($activityRecords) === 0): ?>
            <div class="empty-box">
              <strong>目前還沒有活動紀錄</strong><br>
              <span>
                可以到 <a href="activities.php" style="color:var(--accent)">活動頁</a> 報名感興趣的活動。
              </span>
            </div>
          <?php endif; ?>

          <?php foreach ($activityRecords as $record):
            $statusText = ['pending'=>'待審核','approved'=>'審核通過','rejected'=>'未通過'];
            $statusClass = $record['submission_status'];
            if ($record['submission_status'] === 'approved' && $record['confirmed']) {
                $displayStatus = '已確認參與'; $statusClass = 'confirmed';
            } else {
                $displayStatus = $statusText[$record['submission_status']] ?? '未知狀態';
            }
            $eventEnded = !empty($record['event_end'])
                ? strtotime($record['event_end']) < $nowTime
                : (!empty($record['event_start']) ? strtotime($record['event_start']) < $nowTime : false);
            $eventStatusClass = $eventEnded ? 'ended' : 'active';
            $eventStatusText  = $eventEnded ? '已結束' : '未結束';
            $needConfirm = ($record['submission_status'] === 'approved' && !$record['confirmed']);
          ?>
            <article class="record-card"
                     data-record-status="<?= htmlspecialchars($statusClass) ?>"
                     data-event-status="<?= htmlspecialchars($eventStatusClass) ?>">
              <div class="record-card-top">
                <h3><?= htmlspecialchars($record['activity_title'] ?? '未命名活動') ?></h3>

                <div style="display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end;">
                  <span class="record-status <?= htmlspecialchars($eventStatusClass) ?>">
                    <?= htmlspecialchars($eventStatusText) ?>
                  </span>

                  <span class="record-status <?= htmlspecialchars($statusClass) ?>">
                    <?= htmlspecialchars($displayStatus) ?>
                  </span>
                </div>
              </div>
              <div class="record-meta">
                表單：<?= htmlspecialchars($record['form_title'] ?? '未命名表單') ?><br>
                主辦單位：<?= htmlspecialchars($record['organizer'] ?? '未指定') ?><br>

                活動時間：
                <?= htmlspecialchars($record['event_start'] ?? '未設定') ?>

                <?php if (!empty($record['event_end'])): ?>
                  ～ <?= htmlspecialchars($record['event_end']) ?>
                <?php endif; ?>
                <br>

                地點：<?= htmlspecialchars($record['location'] ?? '未設定') ?><br>
                報名時間：<?= htmlspecialchars($record['submitted_at'] ?? '') ?>
                <?php if (!empty($record['reviewed_at'])): ?> ・審核時間：<?= htmlspecialchars($record['reviewed_at']) ?><?php endif; ?>
              </div>
              <?php if (!empty($record['note'])): ?>
                <div class="record-note">
                  💬 社團備註：<?= nl2br(htmlspecialchars($record['note'])) ?>
                </div>
              <?php endif; ?>
              <?php if ($needConfirm): ?>
                <div class="confirm-record-box">
                  <p>🎉 你的報名已通過，請確認是否參與活動。</p>

                  <form method="post" action="form_apply.php?form_id=<?= htmlspecialchars($record['form_id']) ?>">
                    <input type="hidden" name="action" value="confirm">
                    <button type="submit" class="btn-confirm-record">
                      確認參與
                    </button>
                  </form>
                </div>

              <?php elseif ($record['confirmed']): ?>
                <div class="record-note">
                  ✅ 已確認參與
                  <?php if (!empty($record['confirmed_at'])): ?>
                    ：<?= htmlspecialchars($record['confirmed_at']) ?>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
              <div class="record-actions">
                <a href="activity_view.php?id=<?= htmlspecialchars($record['activity_id']) ?>" class="read-btn">
                  查看活動
                </a>

                <a href="form_apply.php?form_id=<?= htmlspecialchars($record['form_id']) ?>" class="read-btn">
                  查看表單
                </a>
              </div>
            </article>

          <?php endforeach; ?>

        </div>
      </div>
    </div><!-- /panel-records -->

  </div><!-- /right-col -->
</div><!-- /page-body -->

<script>
function showPanel(name, btn) {
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.pnav-btn').forEach(b => b.classList.remove('active'));

  const panel = document.getElementById('panel-' + name);

  if (panel) {
    panel.classList.add('active');
  }

  btn.classList.add('active');
}

document.querySelectorAll('.category-title').forEach(function(btn) {
  btn.addEventListener('click', function() {
    this.parentElement.classList.toggle('open');
  });
});

document.querySelectorAll('[data-club]').forEach(function(el) {
  el.addEventListener('click', function() {
    const clubId = this.dataset.club;

    document.querySelectorAll('[data-club]').forEach(e => e.classList.remove('active'));
    this.classList.add('active');

    document.querySelectorAll('.activity-card').forEach(function(card) {
      if (clubId === '' || card.dataset.club == clubId) {
        card.style.display = '';
      } else {
        card.style.display = 'none';
      }
    });
  });
});

document.querySelectorAll('.sort-link').forEach(function(link) {
  link.addEventListener('click', function() {
    document.querySelectorAll('.sort-link').forEach(l => l.classList.remove('active'));
    this.classList.add('active');

    const sort  = this.dataset.sort;
    const order = this.dataset.order;
    const list  = document.getElementById('activity-list');
    const cards = Array.from(list.querySelectorAll('.activity-card'));

    cards.sort(function(a, b) {
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

function showFavTab(type, btn) {
  document.querySelectorAll('.fav-tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.fav-section').forEach(s => s.classList.remove('active'));

  btn.classList.add('active');

  const tab = document.getElementById('fav-' + type);

  if (tab) {
    tab.classList.add('active');
  }
}

function showRecordTab(status, btn) {
  document.querySelectorAll('.record-tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');

  document.querySelectorAll('.record-card').forEach(function(card) {
    const cardStatus = card.dataset.recordStatus;
    const eventStatus = card.dataset.eventStatus;

    if (
      status === 'all' ||
      cardStatus === status ||
      eventStatus === status
    ) {
      card.style.display = '';
    } else {
      card.style.display = 'none';
    }
  });
}

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

/* ── 通知設定 ── */
document.addEventListener("DOMContentLoaded", function () {
  const notifyBtn = document.getElementById("notify-toggle-btn");
  const statusEl  = document.getElementById("notify-status");
  if (!notifyBtn) return;

  notifyBtn.addEventListener("click", function () {
    fetch("/SA-club/kaira-1.0.0/api/toggle_notification.php", { method: "POST" })
      .then(res => res.json())
      .then(data => {
        if (!data.success) { alert(data.message || "通知設定更新失敗"); return; }
        const enabled = Number(data.new) === 1;
        notifyBtn.dataset.enabled = enabled ? "1" : "0";
        notifyBtn.textContent = enabled ? "關閉通知" : "開啟通知";
        notifyBtn.classList.remove("on", "off");
        notifyBtn.classList.add(enabled ? "on" : "off");
        if (statusEl) statusEl.textContent = enabled ? "已開啟通知" : "未開啟通知";
      })
      .catch(err => { console.error(err); alert("fetch 失敗"); });
  });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<?php require_once "footer.php"; ?>
</body>
</html>