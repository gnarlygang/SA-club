<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "api/db.php";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username, $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 取得此登入者的社團
    $stmt = $pdo->prepare("
        SELECT c.*, u.email
        FROM clubs c
        LEFT JOIN users u ON u.user_id = c.user_id
        WHERE c.user_id = :uid
        LIMIT 1
    ");
    $stmt->execute([':uid' => $_SESSION['user_id'] ?? null]);
    $club = $stmt->fetch(PDO::FETCH_ASSOC);

    $club_id    = $club['id'] ?? 0;
    $tags       = [];
    $activities = [];
    $active_acts = [];
    $closed_acts = [];
    $sub_count  = 0;

    if ($club_id) {
        // 標籤
        $stmt2 = $pdo->prepare("SELECT tag_name FROM club_tags WHERE club_id = :id ORDER BY id");
        $stmt2->execute([':id' => $club_id]);
        $tags = $stmt2->fetchAll(PDO::FETCH_COLUMN);

        // 活動
        $stmt3 = $pdo->prepare("SELECT * FROM activities WHERE user_id = :uid ORDER BY created_at DESC LIMIT 6");
        $stmt3->execute([':uid' => $club['user_id']]);
        $activities = $stmt3->fetchAll(PDO::FETCH_ASSOC);

        // 分離進行中 & 已截止
        foreach ($activities as $a) {
            if (strtotime($a['signup_deadline']) < time()) $closed_acts[] = $a;
            else $active_acts[] = $a;
        }

        // 訂閱人數
        $stmtC = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE club_id = ?");
        $stmtC->execute([$club_id]);
        $sub_count = (int)$stmtC->fetchColumn();
    }

} catch (PDOException $e) {
    die("資料庫連線失敗：" . $e->getMessage());
}

$catColors = [
    '學術性社團'    => ['bg' => '#e8f0fe', 'color' => '#1a56db'],
    '休閒聯誼性社團' => ['bg' => '#fef3c7', 'color' => '#92400e'],
    '服務性社團'    => ['bg' => '#d1fae5', 'color' => '#065f46'],
    '體能性社團'    => ['bg' => '#fee2e2', 'color' => '#991b1b'],
    '藝術性社團'    => ['bg' => '#ede9fe', 'color' => '#5b21b6'],
    '音樂性社團'    => ['bg' => '#fce7f3', 'color' => '#9d174d'],
];
$cc = $catColors[$club['category'] ?? ''] ?? ['bg' => '#f0f0f0', 'color' => '#666'];

require_once "header.php";
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>社團管理 — 輔大社團平台</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;600;700&family=Noto+Sans+TC:wght@400;500;700&display=swap" rel="stylesheet">

  <style>
    :root {
      --sidebar-bg:     #1e2d40;
      --sidebar-width:  220px;
      --sidebar-accent: #4e8cdb;
      --ink:   #1a1a2e;
      --soft:  #4a4a6a;
      --mute:  #8888aa;
      --paper: #f5f5f2;
      --white: #fff;
      --accent:#c8502a;
      --border:#e4e4de;
      --radius:12px;
      --serif: 'Noto Serif TC', serif;
      --sans:  'Noto Sans TC', sans-serif;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: var(--sans);
      background: #eef1f5;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ─── Page shell ─── */
    .page-shell { flex: 1; display: flex; align-items: stretch; }

    /* ─── Sidebar ─── */
    .sidebar {
      width: var(--sidebar-width);
      background: var(--sidebar-bg);
      display: flex;
      flex-direction: column;
      padding: 40px 0 32px;
      position: sticky;
      top: 0;
      height: 100vh;
      flex-shrink: 0;
      box-shadow: 4px 0 24px rgba(0,0,0,0.13);
      z-index: 10;
    }
    .sidebar-brand { padding: 0 24px 32px; border-bottom: 1px solid rgba(255,255,255,0.08); margin-bottom: 24px; }
    .sidebar-brand-label { font-size: 10px; letter-spacing: 2.5px; color: rgba(255,255,255,0.38); text-transform: uppercase; margin-bottom: 6px; }
    .sidebar-brand-title { font-family: var(--serif); font-size: 15px; font-weight: 700; color: #fff; line-height: 1.4; }
    .sidebar-section-label { font-size: 10px; letter-spacing: 2px; color: rgba(255,255,255,0.30); text-transform: uppercase; padding: 0 24px 10px; }
    .sidebar-nav { display: flex; flex-direction: column; gap: 4px; padding: 0 12px; }
    .sidebar-link {
      display: flex; align-items: center; gap: 12px;
      padding: 12px 16px; border-radius: 10px;
      text-decoration: none; color: rgba(255,255,255,0.65);
      font-size: 14px; font-weight: 500;
      transition: background 0.18s, color 0.18s, transform 0.15s;
      position: relative; overflow: hidden;
    }
    .sidebar-link::before {
      content: ''; position: absolute; left: 0; top: 0; bottom: 0;
      width: 3px; background: var(--sidebar-accent); border-radius: 0 3px 3px 0;
      opacity: 0; transform: scaleY(0.4); transition: opacity 0.18s, transform 0.18s;
    }
    .sidebar-link:hover { background: rgba(255,255,255,0.08); color: #fff; transform: translateX(3px); }
    .sidebar-link:hover::before { opacity: 1; transform: scaleY(1); }
    .sidebar-link.active { background: rgba(78,140,219,0.18); color: #7bb8f5; }
    .sidebar-link.active::before { opacity: 1; transform: scaleY(1); }
    .sidebar-link-icon {
      width: 32px; height: 32px; border-radius: 8px;
      background: rgba(255,255,255,0.07);
      display: flex; align-items: center; justify-content: center;
      font-size: 15px; flex-shrink: 0; transition: background 0.18s;
    }
    .sidebar-link:hover .sidebar-link-icon { background: rgba(78,140,219,0.25); }
    .sidebar-link.active .sidebar-link-icon { background: rgba(78,140,219,0.30); color: #7bb8f5; }
    .sidebar-link-text { line-height: 1.2; }
    .sidebar-link-sub { display: block; font-size: 10px; color: rgba(255,255,255,0.28); font-weight: 400; margin-top: 1px; }
    .sidebar-link:hover .sidebar-link-sub { color: rgba(255,255,255,0.45); }
    .sidebar-divider { border: none; border-top: 1px solid rgba(255,255,255,0.08); margin: 16px 24px; }
    .sidebar-footer { margin-top: auto; padding: 0 24px; }
    .sidebar-footer-text { font-size: 10px; color: rgba(255,255,255,0.22); line-height: 1.6; }

    /* ─── Main content ─── */
    .main-content { flex: 1; padding: 36px 32px 60px; overflow-y: auto; }

    /* ─── No club state ─── */
    .no-club-box {
      max-width: 480px; margin: 60px auto; text-align: center;
      background: var(--white); border-radius: var(--radius);
      padding: 48px 36px;
      box-shadow: 0 4px 24px rgba(0,0,0,.08);
    }
    .no-club-box i { font-size: 3.5rem; color: var(--mute); margin-bottom: 16px; display: block; }
    .no-club-box h3 { font-size: 1.2rem; color: var(--ink); margin-bottom: 8px; }
    .no-club-box p { font-size: .9rem; color: var(--soft); }

    /* ─── Club detail view ─── */
    .detail-header {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 24px; flex-wrap: wrap; gap: 12px;
    }
    .detail-header h2 {
      font-family: var(--serif); font-size: 1.3rem; color: var(--ink);
    }

    /* Banner */
    .club-banner {
      width: 100%; height: 240px; object-fit: cover; display: block;
      border-radius: var(--radius) var(--radius) 0 0;
      background: linear-gradient(135deg, #1a1a2e, #2d3a5e);
    }
    .club-banner-placeholder {
      width: 100%; height: 240px;
      background: linear-gradient(135deg, #1a1a2e 0%, #2d3a5e 50%, #1a1a2e 100%);
      display: flex; align-items: center; justify-content: center;
      color: rgba(255,255,255,.15); font-size: 5rem;
      border-radius: var(--radius) var(--radius) 0 0;
    }

    .detail-grid {
      display: grid;
      grid-template-columns: 1fr 280px;
      gap: 1.25rem;
      align-items: start;
    }

    /* Left card group */
    .club-header-card {
      background: var(--white); border: 1px solid var(--border);
      border-radius: var(--radius); padding: 1.5rem 1.75rem;
      margin-bottom: 1.1rem;
      box-shadow: 0 2px 12px rgba(0,0,0,.05);
    }
    .club-header-top {
      display: flex; align-items: flex-start;
      justify-content: space-between; flex-wrap: wrap; gap: .75rem;
      margin-bottom: .75rem;
    }
    .cat-badge {
      display: inline-block; padding: .25rem .75rem;
      border-radius: 99px; font-size: .72rem; font-weight: 600;
      margin-bottom: .5rem;
    }
    .club-name-big {
      font-family: var(--serif);
      font-size: clamp(1.3rem, 2.5vw, 1.75rem);
      font-weight: 800; color: var(--ink); line-height: 1.2;
    }
    .sub-area { display: flex; flex-direction: column; align-items: flex-end; gap: .3rem; flex-shrink: 0; }
    .sub-count-small { font-size: .72rem; color: var(--mute); text-align: right; }
    .sub-count-small strong { color: var(--ink); font-size: .88rem; }
    .tag-wrap { display: flex; flex-wrap: wrap; gap: .3rem; margin-top: .65rem; }
    .tag-item {
      padding: .2rem .6rem; border-radius: 99px;
      font-size: .72rem; background: var(--paper);
      border: 1px solid var(--border); color: var(--soft);
    }
    .stat-row {
      display: flex; gap: 1.25rem; flex-wrap: wrap;
      padding: .85rem 0; border-top: 1px solid var(--border); margin-top: .85rem;
    }
    .stat-item { text-align: center; }
    .stat-num { font-size: 1.3rem; font-weight: 700; color: var(--ink); line-height: 1; }
    .stat-label { font-size: .65rem; color: var(--mute); margin-top: .2rem; }

    .section-card {
      background: var(--white); border: 1px solid var(--border);
      border-radius: var(--radius); padding: 1.4rem 1.75rem;
      margin-bottom: 1.1rem;
      box-shadow: 0 2px 12px rgba(0,0,0,.05);
    }
    .section-label {
      font-size: .65rem; font-weight: 700; letter-spacing: .1em;
      text-transform: uppercase; color: var(--mute);
      margin-bottom: .65rem; display: flex; align-items: center; gap: .4rem;
    }
    .section-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }
    .desc-text { font-size: .9rem; color: var(--soft); line-height: 1.85; white-space: pre-wrap; }

    /* Activity items */
    .act-item {
      display: block; padding: .75rem .9rem;
      border-radius: 8px; border: 1px solid var(--border);
      background: var(--paper); margin-bottom: .55rem;
      text-decoration: none; color: inherit; transition: all .18s;
    }
    .act-item:hover { background: #f0ebe8; border-color: #ddd5d0; }
    .act-item.is-closed { opacity: .55; }
    .act-item-header { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; margin-bottom: .3rem; }
    .act-title { font-size: .88rem; font-weight: 700; color: var(--ink); flex: 1; }
    .act-badge {
      font-size: .65rem; font-weight: 700; padding: .15rem .5rem;
      border-radius: 99px; white-space: nowrap;
    }
    .act-badge.free   { background: #d1fae5; color: #065f46; }
    .act-badge.paid   { background: #fef3c7; color: #92400e; }
    .act-badge.closed { background: #f1f5f9; color: #64748b; }
    .act-meta-row { display: flex; gap: .85rem; font-size: .75rem; color: var(--mute); flex-wrap: wrap; }
    .act-meta-row svg { width: 11px; height: 11px; margin-right: .2rem; }

    /* Right info card */
    .info-card {
      background: var(--white); border: 1px solid var(--border);
      border-radius: var(--radius); padding: 1.25rem;
      box-shadow: 0 2px 12px rgba(0,0,0,.05);
      margin-bottom: 1.1rem;
    }
    .info-card-header {
      font-size: .7rem; font-weight: 700; letter-spacing: .1em;
      text-transform: uppercase; color: var(--mute);
      padding-bottom: .75rem; border-bottom: 1px solid var(--border); margin-bottom: .85rem;
    }

    /* 訂閱人數大數字 */
    .sub-count-big {
      text-align: center; padding: .85rem 0 1rem;
      border-bottom: 1px solid var(--border); margin-bottom: .85rem;
    }
    .sub-count-num { font-size: 2.6rem; font-weight: 800; color: var(--ink); line-height: 1; }
    .sub-count-unit { font-size: .75rem; color: var(--mute); margin-top: .35rem; }

    .info-row { display: flex; align-items: flex-start; gap: .75rem; padding: .6rem 0; border-bottom: 1px solid var(--border); }
    .info-row:last-child { border-bottom: none; }
    .info-icon {
      width: 28px; height: 28px; border-radius: 7px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0; color: #fff;
    }
    .info-icon.purple { background: #7c3aed; }
    .info-icon.orange { background: #ea580c; }
    .info-icon.blue   { background: #2563eb; }
    .info-icon.green  { background: #16a34a; }
    .info-label { font-size: .68rem; color: var(--mute); margin-bottom: .15rem; }
    .info-value { font-size: .85rem; color: var(--ink); font-weight: 500; }

    /* Edit button */
    .btn-edit-club {
      display: block; width: 100%;
      background: var(--ink); color: #fff;
      border: none; border-radius: 8px;
      padding: 12px 16px; font-size: .9rem; font-weight: 700;
      text-align: center; text-decoration: none;
      cursor: pointer; font-family: inherit;
      transition: background .2s, transform .1s;
      margin-top: 4px;
    }
    .btn-edit-club:hover { background: #2d3a5e; color: #fff; transform: translateY(-1px); }

    /* Closed toggle */
    .btn-closed {
      background: none; border: 1px solid var(--border); color: var(--soft);
      border-radius: 8px; padding: .5rem .85rem; font-size: .78rem;
      cursor: pointer; display: flex; align-items: center; gap: .35rem;
      transition: all .18s; font-family: inherit;
    }
    .btn-closed:hover { background: var(--paper); }
    #closedArrow { transition: transform .2s; }

    /* Mobile sidebar toggle */
    .sidebar-toggle {
      display: none; position: fixed;
      bottom: 24px; left: 24px; z-index: 200;
      width: 48px; height: 48px; border-radius: 50%;
      background: var(--sidebar-bg); color: #fff;
      border: none; font-size: 20px;
      box-shadow: 0 4px 16px rgba(0,0,0,0.25);
      align-items: center; justify-content: center; cursor: pointer;
    }

    @media (max-width: 900px) {
      .detail-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 768px) {
      .sidebar { position: fixed; left: 0; top: 0; height: 100%; transform: translateX(-100%); transition: transform 0.28s cubic-bezier(.4,0,.2,1); z-index: 100; }
      .sidebar.open { transform: translateX(0); }
      .sidebar-toggle { display: flex; }
      .main-content { padding: 24px 16px 60px; }
    }
  </style>
</head>
<body>

<div class="page-shell">

  <!-- ═══ SIDEBAR ═══ -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="sidebar-brand-label">管理中心</div>
      <div class="sidebar-brand-title">輔大<br>社團平台</div>
    </div>

    <div class="sidebar-section-label">社團管理</div>

    <nav class="sidebar-nav">

      <a href="create_club.php" class="sidebar-link active">
        <div class="sidebar-link-icon"><i class="bi bi-building"></i></div>
        <div class="sidebar-link-text">
          社團管理編輯
          <span class="sidebar-link-sub">查看與編輯社團資料</span>
        </div>
      </a>

      <a href="form_manage.php" class="sidebar-link">
        <div class="sidebar-link-icon"><i class="bi bi-ui-checks-grid"></i></div>
        <div class="sidebar-link-text">
          表單管理
          <span class="sidebar-link-sub">報名表 / 問卷設定</span>
        </div>
      </a>

      <a href="form_review.php" class="sidebar-link">
        <div class="sidebar-link-icon"><i class="bi bi-person-check"></i></div>
        <div class="sidebar-link-text">
          名單審核
          <span class="sidebar-link-sub">審核成員申請</span>
        </div>
      </a>

    </nav>

  </aside>

  <!-- ═══ MAIN ═══ -->
  <main class="main-content">

    <?php if (!$club): ?>

      <div class="no-club-box">
        <i class="bi bi-building-x"></i>
        <h3>尚未建立社團</h3>
        <p>您的帳號目前沒有關聯的社團資料。<br>請聯絡管理員進行建立。</p>
      </div>

    <?php else: ?>

      <div class="detail-header">
        <h2><i class="bi bi-building me-2"></i>社團管理編輯</h2>
        <a href="edit_club.php?id=<?= $club_id ?>" class="btn-edit-club" style="width:auto;padding:9px 20px;font-size:.85rem;">
          <i class="bi bi-pencil-square me-1"></i>編輯社團資料
        </a>
      </div>

      <!-- Banner -->
      <?php if (!empty($club['image'])): ?>
        <img class="club-banner" src="<?= htmlspecialchars($club['image']) ?>" alt="">
      <?php else: ?>
        <div class="club-banner-placeholder"><i class="bi bi-people-fill"></i></div>
      <?php endif; ?>

      <div class="detail-grid" style="margin-top:1.1rem;">

        <!-- ═══ LEFT ═══ -->
        <div>

          <!-- 社團名稱卡 -->
          <div class="club-header-card">
            <div class="club-header-top">
              <div>
                <span class="cat-badge" style="background:<?= $cc['bg'] ?>;color:<?= $cc['color'] ?>">
                  <?= htmlspecialchars($club['category']) ?>
                </span>
                <div class="club-name-big"><?= htmlspecialchars($club['name']) ?></div>
                <?php if (!empty($club['short_name'])): ?>
                <div style="font-size:.85rem;color:var(--mute);margin-top:.3rem;">
                  <?= htmlspecialchars($club['short_name']) ?>
                </div>
                <?php endif; ?>
              </div>
              <div class="sub-area">
                <div class="sub-count-small"><strong><?= number_format($sub_count) ?></strong> 人訂閱</div>
              </div>
            </div>

            <?php if (!empty($tags)): ?>
            <div class="tag-wrap">
              <?php foreach ($tags as $tag): ?>
                <span class="tag-item"><?= htmlspecialchars($tag) ?></span>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="stat-row">
              <div class="stat-item">
                <div class="stat-num"><?= count($active_acts) ?></div>
                <div class="stat-label">進行中活動</div>
              </div>
              <div class="stat-item">
                <div class="stat-num"><?= count($activities) ?></div>
                <div class="stat-label">總活動數</div>
              </div>
              <div class="stat-item">
                <div class="stat-num"><?= number_format($sub_count) ?></div>
                <div class="stat-label">訂閱人數</div>
              </div>
            </div>
          </div>

          <!-- 社團介紹 -->
          <?php if (!empty($club['description'])): ?>
          <div class="section-card">
            <div class="section-label"><i class="bi bi-text-paragraph"></i> 社團介紹</div>
            <div class="desc-text"><?= htmlspecialchars($club['description']) ?></div>
          </div>
          <?php endif; ?>

          <!-- 近期活動 -->
          <?php if (!empty($activities)): ?>
          <div class="section-card">
            <div class="section-label"><i class="bi bi-calendar-event"></i> 近期活動</div>

            <?php foreach ($active_acts as $act):
              $isFree = str_contains($act['fee'] ?? '', '免費') || ($act['fee'] ?? '') === '0';
            ?>
              <a href="activity_view.php?id=<?= $act['id'] ?>" class="act-item">
                <div class="act-item-header">
                  <span class="act-title"><?= htmlspecialchars($act['title']) ?></span>
                  <span class="act-badge <?= $isFree ? 'free' : 'paid' ?>"><?= $isFree ? '免費' : htmlspecialchars($act['fee']) ?></span>
                </div>
                <div class="act-meta-row">
                  <span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    <?= date('Y/m/d', strtotime($act['event_start'])) ?>
                  </span>
                  <?php if (!empty($act['location'])): ?>
                  <span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <?= htmlspecialchars($act['location']) ?>
                  </span>
                  <?php endif; ?>
                </div>
              </a>
            <?php endforeach; ?>

            <?php if (!empty($closed_acts)): ?>
            <button class="btn-closed" onclick="toggleClosed(this)">
              <svg id="closedArrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
              顯示已截止活動（<?= count($closed_acts) ?> 項）
            </button>
            <div id="closedActs" style="display:none;margin-top:.55rem;">
              <?php foreach ($closed_acts as $act):
                $isFree = str_contains($act['fee'] ?? '', '免費') || ($act['fee'] ?? '') === '0';
              ?>
                <a href="activity_view.php?id=<?= $act['id'] ?>" class="act-item is-closed">
                  <div class="act-item-header">
                    <span class="act-title"><?= htmlspecialchars($act['title']) ?></span>
                    <span class="act-badge <?= $isFree ? 'free' : 'paid' ?>"><?= $isFree ? '免費' : htmlspecialchars($act['fee']) ?></span>
                    <span class="act-badge closed">已截止</span>
                  </div>
                  <div class="act-meta-row">
                    <span>
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                      <?= date('Y/m/d', strtotime($act['event_start'])) ?>
                    </span>
                    <?php if (!empty($act['location'])): ?>
                    <span>
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                      <?= htmlspecialchars($act['location']) ?>
                    </span>
                    <?php endif; ?>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>

        </div>

        <!-- ═══ RIGHT ═══ -->
        <div>
          <div class="info-card">
            <div class="info-card-header">社團資訊</div>

            <!-- 訂閱人數大數字 -->
            <div class="sub-count-big">
              <div class="sub-count-num"><?= number_format($sub_count) ?></div>
              <div class="sub-count-unit">位同學已訂閱</div>
            </div>

            <!-- 分類 -->
            <div class="info-row">
              <div class="info-icon purple">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
              </div>
              <div>
                <div class="info-label">社團類型</div>
                <div class="info-value"><?= htmlspecialchars($club['category']) ?></div>
              </div>
            </div>

            <!-- 聯絡信箱 -->
            <?php if (!empty($club['email'])): ?>
            <div class="info-row">
              <div class="info-icon orange">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              </div>
              <div>
                <div class="info-label">聯絡信箱</div>
                <div class="info-value"><a href="mailto:<?= htmlspecialchars($club['email']) ?>" style="color:var(--accent)"><?= htmlspecialchars($club['email']) ?></a></div>
              </div>
            </div>
            <?php endif; ?>

            <!-- 活動數量 -->
            <div class="info-row">
              <div class="info-icon blue">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
              </div>
              <div>
                <div class="info-label">活動數量</div>
                <div class="info-value">共 <?= count($activities) ?> 項（進行中 <?= count($active_acts) ?> 項）</div>
              </div>
            </div>

            <!-- 標籤 -->
            <?php if (!empty($tags)): ?>
            <div class="info-row">
              <div class="info-icon green">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/></svg>
              </div>
              <div>
                <div class="info-label">標籤</div>
                <div class="info-value" style="display:flex;flex-wrap:wrap;gap:.3rem;margin-top:.25rem;">
                  <?php foreach ($tags as $tag): ?>
                    <span style="font-size:.68rem;padding:.1rem .45rem;border-radius:99px;background:var(--paper);border:1px solid var(--border);color:var(--soft)"><?= htmlspecialchars($tag) ?></span>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
            <?php endif; ?>

            
          </div>
        </div>

      </div><!-- .detail-grid -->

    <?php endif; ?>

  </main>
</div>

<button class="sidebar-toggle" id="sidebarToggle" aria-label="開關側邊欄">
  <i class="bi bi-list"></i>
</button>

<?php require "footer.php"; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleClosed(btn) {
  const div = document.getElementById('closedActs');
  const arrow = document.getElementById('closedArrow');
  const isOpen = div.style.display !== 'none';
  div.style.display = isOpen ? 'none' : 'block';
  arrow.style.transform = isOpen ? '' : 'rotate(180deg)';
  btn.lastChild.textContent = isOpen
    ? ' 顯示已截止活動（<?= count($closed_acts) ?> 項）'
    : ' 隱藏已截止活動（<?= count($closed_acts) ?> 項）';
}

// Sidebar mobile
const sidebar = document.getElementById("sidebar");
const toggleBtn = document.getElementById("sidebarToggle");
toggleBtn.addEventListener("click", () => sidebar.classList.toggle("open"));
document.addEventListener("click", (e) => {
  if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !toggleBtn.contains(e.target))
    sidebar.classList.remove("open");
});
</script>
</body>
</html>