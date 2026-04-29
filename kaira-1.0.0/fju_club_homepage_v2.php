<?php
// ── 資料庫連線設定 ────────────────────────────────────────────
$db_host = 'localhost';
$db_name = 'sa2026';
$db_user = 'root';       // 請依實際情況修改
$db_pass = '12345678';           // 請依實際情況修改
$db_charset = 'utf8mb4';

try {
    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset={$db_charset}",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('<p style="color:red;padding:2rem;">資料庫連線失敗：' . htmlspecialchars($e->getMessage()) . '</p>');
}

// ── 查詢：近期活動（取最近 4 筆，未截止優先）──────────────────
$stmtAct = $pdo->query("
    SELECT a.*, u.username AS club_name
    FROM activities a
    LEFT JOIN users u ON u.user_id = a.user_id
    ORDER BY a.signup_deadline >= CURDATE() DESC, a.event_start ASC
    LIMIT 4
");
$activities = $stmtAct->fetchAll();

// ── 查詢：系統公告（最新 3 筆）──────────────────────────────
$stmtAnn = $pdo->query("
    SELECT * FROM announcements
    ORDER BY date DESC
    LIMIT 3
");
$announcements = $stmtAnn->fetchAll();

// ── 查詢：熱門社團（依 subscriptions 追蹤數排序，取前 4）──────
$stmtClubs = $pdo->query("
    SELECT c.id, c.name, c.category,
           COUNT(s.id) AS follow_count
    FROM clubs c
    LEFT JOIN subscriptions s ON s.club_id = c.id
    GROUP BY c.id, c.name, c.category
    ORDER BY follow_count DESC, c.id ASC
    LIMIT 4
");
$hotClubs = $stmtClubs->fetchAll();

// ── 查詢：Hero 精選活動（最近一筆未截止活動）────────────────
$stmtHero = $pdo->query("
    SELECT a.*, u.username AS club_name
    FROM activities a
    LEFT JOIN users u ON u.user_id = a.user_id
    WHERE a.signup_deadline >= CURDATE()
    ORDER BY a.event_start ASC
    LIMIT 1
");
$heroAct = $stmtHero->fetch();

// ── 統計數字 ─────────────────────────────────────────────────
$clubCount = $pdo->query("SELECT COUNT(*) FROM clubs")->fetchColumn();
$actCount  = $pdo->query("SELECT COUNT(*) FROM activities WHERE event_start >= NOW()")->fetchColumn();
$userCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 3")->fetchColumn();

// ── 輔助函式 ─────────────────────────────────────────────────
function formatDate(string $datetime): string {
    return date('Y/m/d', strtotime($datetime));
}
function isDeadlinePassed(string $deadline): bool {
    return strtotime($deadline) < strtotime(date('Y-m-d'));
}
function annTag(string $title): array {
    if (str_contains($title, '公告')) return ['sys', '公告'];
    if (str_contains($title, '通知')) return ['new', '通知'];
    if (str_contains($title, '更新')) return ['upd', '更新'];
    return ['sys', '公告'];
}
function clubAbbr(string $name): string {
    $name = preg_replace('/^(輔大|輔仁大學)/', '', $name);
    return mb_substr($name, 0, 2);
}
$rankClass = ['gold', 'silver', 'bronze'];
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FJU_CLUB - 天主教輔仁大學社團平台</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;700&family=Noto+Sans+TC:wght@300;400;500;700&display=swap" rel="stylesheet">
<style>
:root {
  --navy: #1a2744; --navy-mid: #243257; --navy-light: #2e3f6b;
  --accent: #3a5fa0; --accent-hover: #4a72be;
  --gold: #c8a96e; --gold-light: #e8c98e;
  --cream: #f7f4ef; --cream-dark: #ede9e0; --white: #ffffff;
  --text-dark: #1a1f2e; --text-mid: #4a5068; --text-muted: #8a91a8;
  --border-light: rgba(26,39,68,0.10); --border-mid: rgba(26,39,68,0.18);
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Noto Sans TC', sans-serif; background: var(--cream); color: var(--text-dark); min-height: 100vh; }

nav { background: var(--navy); height: 60px; display: flex; align-items: center; justify-content: space-between; padding: 0 2.5rem; position: sticky; top: 0; z-index: 200; }
.nav-logo { font-family: 'Noto Serif TC', serif; font-size: 1.15rem; font-weight: 700; color: #fff; letter-spacing: 0.12em; text-decoration: none; }
.nav-links { display: flex; gap: 0.2rem; list-style: none; }
.nav-links a { color: rgba(255,255,255,0.65); text-decoration: none; font-size: 0.875rem; padding: 0.4rem 0.85rem; border-radius: 6px; transition: background 0.18s, color 0.18s; }
.nav-links a:hover, .nav-links a.active { color: #fff; background: rgba(255,255,255,0.1); }
.nav-right { display: flex; align-items: center; gap: 0.8rem; }
.nav-search-btn { background: none; border: none; cursor: pointer; color: rgba(255,255,255,0.65); display: flex; align-items: center; padding: 6px; border-radius: 6px; transition: background 0.18s, color 0.18s; }
.nav-search-btn:hover { background: rgba(255,255,255,0.1); color: #fff; }
.nav-search-btn svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; }
.btn-login { background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.28); color: #fff; padding: 0.38rem 1.1rem; border-radius: 6px; font-size: 0.84rem; cursor: pointer; text-decoration: none; transition: background 0.2s; }
.btn-login:hover { background: rgba(255,255,255,0.22); }

.hero { background: var(--navy); padding: 0 2.5rem; display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; align-items: center; min-height: 380px; position: relative; overflow: hidden; }
.hero::after { content: ''; position: absolute; right: -100px; bottom: -100px; width: 420px; height: 420px; border-radius: 50%; background: radial-gradient(circle, rgba(200,169,110,0.12) 0%, transparent 65%); pointer-events: none; }
.hero-text { padding: 3.5rem 0; position: relative; z-index: 1; }
.hero-eyebrow { font-size: 0.72rem; font-weight: 500; letter-spacing: 0.18em; color: var(--gold); text-transform: uppercase; margin-bottom: 1rem; }
.hero h1 { font-family: 'Noto Serif TC', serif; font-size: clamp(1.8rem, 3.5vw, 2.8rem); font-weight: 700; line-height: 1.3; color: #fff; margin-bottom: 1rem; }
.hero h1 em { font-style: normal; color: var(--gold-light); }
.hero-sub { font-size: 0.9rem; color: rgba(255,255,255,0.55); line-height: 1.8; margin-bottom: 2rem; font-weight: 300; max-width: 360px; }
.hero-btns { display: flex; gap: 0.75rem; flex-wrap: wrap; }
.btn-hero-primary { background: var(--gold); color: var(--navy); border: none; padding: 0.65rem 1.6rem; border-radius: 7px; font-size: 0.88rem; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-block; transition: background 0.2s, transform 0.15s; }
.btn-hero-primary:hover { background: var(--gold-light); transform: translateY(-1px); }
.btn-hero-ghost { background: transparent; color: rgba(255,255,255,0.8); border: 1px solid rgba(255,255,255,0.28); padding: 0.65rem 1.6rem; border-radius: 7px; font-size: 0.88rem; cursor: pointer; text-decoration: none; display: inline-block; transition: background 0.2s; }
.btn-hero-ghost:hover { background: rgba(255,255,255,0.08); }
.hero-stats { display: flex; gap: 2.5rem; margin-top: 2.5rem; padding-top: 2rem; border-top: 1px solid rgba(255,255,255,0.1); }
.hero-stat-num { font-family: 'Noto Serif TC', serif; font-size: 1.6rem; font-weight: 700; color: var(--gold-light); line-height: 1; margin-bottom: 0.25rem; }
.hero-stat-label { font-size: 0.72rem; color: rgba(255,255,255,0.45); letter-spacing: 0.06em; }

.hero-right { padding: 2rem 0; position: relative; z-index: 1; }
.upcoming-label { font-size: 0.7rem; font-weight: 600; letter-spacing: 0.14em; color: rgba(255,255,255,0.4); text-transform: uppercase; margin-bottom: 0.9rem; }
.activity-card-featured { background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.14); border-radius: 14px; overflow: hidden; transition: border-color 0.2s; }
.activity-card-featured:hover { border-color: rgba(200,169,110,0.4); }
.act-img-hero { width: 100%; height: 160px; background: linear-gradient(135deg, #243257 0%, #1a2744 100%); display: flex; align-items: center; justify-content: center; position: relative; }
.act-img-hero svg { width: 40px; height: 40px; stroke: rgba(255,255,255,0.2); fill: none; stroke-width: 1.5; }
.act-img-hero .act-badge { position: absolute; top: 12px; left: 12px; background: var(--gold); color: var(--navy); font-size: 0.65rem; font-weight: 700; letter-spacing: 0.08em; padding: 0.2rem 0.6rem; border-radius: 4px; }
.act-hero-body { padding: 1.1rem 1.3rem 1.3rem; }
.act-hero-meta { font-size: 0.72rem; color: rgba(255,255,255,0.4); margin-bottom: 0.4rem; display: flex; align-items: center; gap: 0.5rem; }
.act-hero-meta svg { width: 12px; height: 12px; stroke: currentColor; fill: none; stroke-width: 2; }
.act-hero-body h3 { font-size: 1rem; font-weight: 600; color: #fff; margin-bottom: 0.4rem; line-height: 1.4; }
.act-hero-body p { font-size: 0.78rem; color: rgba(255,255,255,0.5); line-height: 1.6; margin-bottom: 0.9rem; font-weight: 300; }
.btn-register-sm { display: inline-block; background: var(--accent); color: #fff; font-size: 0.78rem; padding: 0.4rem 1rem; border-radius: 6px; text-decoration: none; font-weight: 500; transition: background 0.18s; }
.btn-register-sm:hover { background: var(--accent-hover); }

.main-wrap { max-width: 1080px; margin: 0 auto; padding: 2.5rem 2rem; display: grid; grid-template-columns: 1fr 320px; gap: 2rem; align-items: start; }
.sec-label { display: flex; align-items: center; gap: 0.6rem; margin-bottom: 1.2rem; }
.sec-label h2 { font-family: 'Noto Serif TC', serif; font-size: 1.1rem; font-weight: 700; color: var(--navy); }
.sec-label::before { content: ''; width: 4px; height: 1.15em; background: var(--gold); border-radius: 2px; flex-shrink: 0; }
.see-all { margin-left: auto; font-size: 0.8rem; color: var(--accent); text-decoration: none; font-weight: 500; }
.see-all:hover { color: var(--navy); }

.activities-block { margin-bottom: 2.5rem; }
.activities-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.act-card { background: var(--white); border: 1px solid var(--border-light); border-radius: 12px; overflow: hidden; cursor: pointer; transition: box-shadow 0.2s, transform 0.18s, border-color 0.2s; }
.act-card:hover { box-shadow: 0 8px 32px rgba(26,39,68,0.10); transform: translateY(-2px); border-color: var(--border-mid); }
.act-card-img { width: 100%; height: 120px; background: linear-gradient(135deg, #e8eef6 0%, #d4dded 100%); display: flex; align-items: center; justify-content: center; position: relative; }
.act-card-img svg { width: 28px; height: 28px; stroke: rgba(58,95,160,0.3); fill: none; stroke-width: 1.5; }
.act-card-img .tag { position: absolute; top: 8px; left: 8px; font-size: 0.62rem; font-weight: 700; padding: 0.18rem 0.5rem; border-radius: 4px; background: var(--navy); color: rgba(255,255,255,0.9); max-width: 90px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.act-card-body { padding: 0.9rem 1rem; }
.act-card-meta { font-size: 0.7rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.35rem; }
.act-card-meta svg { width: 11px; height: 11px; stroke: currentColor; fill: none; stroke-width: 2; }
.act-card-body h3 { font-size: 0.88rem; font-weight: 600; color: var(--text-dark); margin-bottom: 0.3rem; line-height: 1.4; }
.act-card-body p { font-size: 0.75rem; color: var(--text-mid); line-height: 1.55; font-weight: 300; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.act-card-foot { display: flex; align-items: center; justify-content: space-between; margin-top: 0.7rem; padding-top: 0.7rem; border-top: 1px solid var(--border-light); }
.act-count { font-size: 0.7rem; color: var(--text-muted); display: flex; align-items: center; gap: 4px; }
.act-count svg { width: 11px; height: 11px; stroke: currentColor; fill: none; stroke-width: 2; }
.btn-register-xs { font-size: 0.7rem; font-weight: 600; padding: 0.22rem 0.7rem; background: var(--cream); color: var(--accent); border: 1px solid rgba(58,95,160,0.3); border-radius: 5px; cursor: pointer; text-decoration: none; transition: background 0.18s; }
.btn-register-xs:hover { background: rgba(58,95,160,0.08); }

.forum-block { margin-bottom: 2.5rem; }
.forum-list { display: flex; flex-direction: column; gap: 0.6rem; }
.forum-item { background: var(--white); border: 1px solid var(--border-light); border-radius: 10px; padding: 0.95rem 1.1rem; cursor: pointer; transition: box-shadow 0.18s, border-color 0.18s; display: flex; gap: 1rem; align-items: flex-start; }
.forum-item:hover { box-shadow: 0 4px 18px rgba(26,39,68,0.08); border-color: var(--border-mid); }
.forum-icon { width: 36px; height: 36px; border-radius: 8px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 1rem; }
.forum-icon.promote { background: #fff0f0; }
.forum-icon.ask { background: #f0f4ff; }
.forum-icon.share { background: #f0fff5; }
.forum-body { flex: 1; min-width: 0; }
.forum-tags { display: flex; gap: 0.4rem; margin-bottom: 0.3rem; }
.ftag { font-size: 0.62rem; font-weight: 600; padding: 0.1rem 0.45rem; border-radius: 4px; }
.ftag.red { background: #fff0f0; color: #c0392b; }
.ftag.blue { background: #f0f4ff; color: var(--accent); }
.ftag.green { background: #f0fff5; color: #2d7a4f; }
.ftag.gray { background: var(--cream-dark); color: var(--text-mid); }
.forum-body h3 { font-size: 0.88rem; font-weight: 600; color: var(--text-dark); margin-bottom: 0.2rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.forum-body p { font-size: 0.76rem; color: var(--text-mid); font-weight: 300; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.forum-foot { display: flex; align-items: center; gap: 1rem; margin-top: 0.5rem; }
.forum-stat { font-size: 0.69rem; color: var(--text-muted); display: flex; align-items: center; gap: 3px; }
.forum-stat svg { width: 11px; height: 11px; stroke: currentColor; fill: none; stroke-width: 2; }

.sidebar { display: flex; flex-direction: column; gap: 1.4rem; }
.sidebar-card { background: var(--white); border: 1px solid var(--border-light); border-radius: 12px; overflow: hidden; }
.sidebar-card-header { background: var(--navy); padding: 0.8rem 1.2rem; display: flex; align-items: center; gap: 0.6rem; }
.sidebar-card-header h3 { font-size: 0.82rem; font-weight: 600; color: rgba(255,255,255,0.9); letter-spacing: 0.06em; }
.sidebar-card-header svg { width: 15px; height: 15px; stroke: var(--gold); fill: none; stroke-width: 2; }
.sidebar-card-body { padding: 0.2rem 0; }
.ann-item { padding: 0.75rem 1.2rem; border-bottom: 1px solid var(--border-light); cursor: pointer; transition: background 0.15s; }
.ann-item:last-child { border-bottom: none; }
.ann-item:hover { background: var(--cream); }
.ann-tag { font-size: 0.6rem; font-weight: 700; padding: 0.1rem 0.45rem; border-radius: 3px; display: inline-block; margin-bottom: 0.3rem; }
.ann-tag.sys { background: #e8eef6; color: var(--accent); }
.ann-tag.new { background: #fff0e8; color: #c05020; }
.ann-tag.upd { background: #f0fff5; color: #2d7a4f; }
.ann-item h4 { font-size: 0.82rem; font-weight: 600; color: var(--text-dark); margin-bottom: 0.15rem; line-height: 1.35; }
.ann-item p { font-size: 0.71rem; color: var(--text-mid); font-weight: 300; }
.ann-date { font-size: 0.65rem; color: var(--text-muted); margin-top: 0.25rem; }

.hot-clubs-list { padding: 0.3rem 0; }
.club-item { display: flex; align-items: center; gap: 0.8rem; padding: 0.65rem 1.2rem; border-bottom: 1px solid var(--border-light); cursor: pointer; transition: background 0.15s; }
.club-item:last-child { border-bottom: none; }
.club-item:hover { background: var(--cream); }
.club-rank { font-family: 'Noto Serif TC', serif; font-size: 0.95rem; font-weight: 700; width: 20px; text-align: center; flex-shrink: 0; color: var(--cream-dark); }
.club-rank.gold { color: var(--gold); }
.club-rank.silver { color: #aaa; }
.club-rank.bronze { color: #b87333; }
.club-avatar { width: 34px; height: 34px; border-radius: 8px; background: linear-gradient(135deg, #e8eef6, #d0daee); display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 700; color: var(--accent); flex-shrink: 0; }
.club-info { flex: 1; min-width: 0; }
.club-name { font-size: 0.82rem; font-weight: 600; color: var(--text-dark); }
.club-sub { font-size: 0.68rem; color: var(--text-muted); font-weight: 300; }
.club-arrow { color: var(--text-muted); font-size: 0.8rem; }

footer { background: var(--navy); margin-top: 1rem; }
.footer-top { max-width: 1080px; margin: 0 auto; padding: 2.5rem 2rem 2rem; display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; }
.foot-col h5 { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.14em; color: rgba(255,255,255,0.4); text-transform: uppercase; margin-bottom: 1rem; }
.foot-col ul { list-style: none; }
.foot-col ul li { margin-bottom: 0.5rem; }
.foot-col ul li a { font-size: 0.8rem; color: rgba(255,255,255,0.55); text-decoration: none; font-weight: 300; transition: color 0.18s; display: flex; align-items: center; gap: 5px; }
.foot-col ul li a::before { content: '›'; color: var(--gold); font-size: 0.95rem; }
.foot-col ul li a:hover { color: #fff; }
.footer-bottom { border-top: 1px solid rgba(255,255,255,0.08); max-width: 1080px; margin: 0 auto; padding: 1.5rem 2rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; }
.footer-brand { display: flex; align-items: center; gap: 1rem; }
.footer-logo-box { width: 56px; height: 42px; background: var(--navy-mid); border-radius: 7px; display: flex; align-items: center; justify-content: center; font-family: 'Noto Serif TC', serif; font-size: 0.7rem; font-weight: 700; color: var(--gold); }
.footer-brand-info strong { display: block; font-size: 0.84rem; font-weight: 600; color: rgba(255,255,255,0.85); }
.footer-brand-info span { font-size: 0.72rem; color: rgba(255,255,255,0.38); font-weight: 300; }
.footer-copy { font-size: 0.72rem; color: rgba(255,255,255,0.3); font-weight: 300; }
.footer-contact { font-size: 0.75rem; color: rgba(255,255,255,0.45); font-weight: 300; text-align: right; line-height: 1.7; }

@media (max-width: 760px) {
  .hero { grid-template-columns: 1fr; }
  .hero-right { display: none; }
  .main-wrap { grid-template-columns: 1fr; }
  .sidebar { order: -1; }
  .activities-row { grid-template-columns: 1fr; }
  .footer-top { grid-template-columns: 1fr; }
  .footer-bottom { flex-direction: column; align-items: flex-start; }
  nav { padding: 0 1rem; }
  .nav-links { display: none; }
}
</style>
</head>
<body>

<!-- NAV -->
<nav>
  <a class="nav-logo" href="#">FJU_CLUB</a>
  <ul class="nav-links">
    <li><a href="#" class="active">首頁</a></li>
    <li><a href="#">社團介紹</a></li>
    <li><a href="#">活動</a></li>
    <li><a href="#">論壇</a></li>
  </ul>
  <div class="nav-right">
    <button class="nav-search-btn" title="搜尋">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    </button>
    <a href="login.php" class="btn-login">登入</a>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-text">
    <p class="hero-eyebrow">FJU · 天主教輔仁大學</p>
    <h1>找到你的<em>熱情所在</em><br>從社團開始</h1>
    <p class="hero-sub">探索百餘個精彩社團，參與活動、結交夥伴，讓大學四年留下最難忘的記憶。</p>
    <div class="hero-btns">
      <a href="#" class="btn-hero-primary">探索社團</a>
      <a href="#" class="btn-hero-ghost">近期活動</a>
    </div>
    <div class="hero-stats">
      <div>
        <div class="hero-stat-num"><?= $clubCount ?>+</div>
        <div class="hero-stat-label">社團數量</div>
      </div>
      <div>
        <div class="hero-stat-num"><?= $actCount ?>+</div>
        <div class="hero-stat-label">近期活動</div>
      </div>
      <div>
        <div class="hero-stat-num"><?= $userCount ?>+</div>
        <div class="hero-stat-label">學生成員</div>
      </div>
    </div>
  </div>

  <div class="hero-right">
    <p class="upcoming-label">近期精選活動</p>
    <?php if ($heroAct): ?>
    <div class="activity-card-featured">
      <div class="act-img-hero">
        <svg viewBox="0 0 24 24"><path d="M9 19V6l12-3v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="15" r="3"/></svg>
        <span class="act-badge">熱門活動</span>
      </div>
      <div class="act-hero-body">
        <div class="act-hero-meta">
          <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          <?= formatDate($heroAct['event_start']) ?> · <?= htmlspecialchars($heroAct['club_name'] ?? $heroAct['organizer']) ?>
        </div>
        <h3><?= htmlspecialchars($heroAct['title']) ?></h3>
        <p><?= htmlspecialchars(mb_substr($heroAct['description'], 0, 60)) ?>…</p>
        <a href="activity.php?id=<?= $heroAct['id'] ?>" class="btn-register-sm">立即報名</a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- MAIN CONTENT -->
<div class="main-wrap">
  <div>

    <!-- 近期活動（動態資料） -->
    <div class="activities-block">
      <div class="sec-label">
        <h2>近期活動</h2>
        <a href="activities.php" class="see-all">查看全部 →</a>
      </div>
      <div class="activities-row">
        <?php foreach ($activities as $act):
          $passed = isDeadlinePassed($act['signup_deadline']);
        ?>
        <div class="act-card">
          <div class="act-card-img">
            <svg viewBox="0 0 24 24"><path d="M9 19V6l12-3v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="15" r="3"/></svg>
            <span class="tag"><?= htmlspecialchars($act['organizer']) ?></span>
          </div>
          <div class="act-card-body">
            <div class="act-card-meta">
              <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              <?= formatDate($act['event_start']) ?> · <?= htmlspecialchars($act['club_name'] ?? $act['organizer']) ?>
            </div>
            <h3><?= htmlspecialchars($act['title']) ?></h3>
            <p><?= htmlspecialchars($act['description']) ?></p>
            <div class="act-card-foot">
              <span class="act-count">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                <?= $passed ? '已截止' : '報名中' ?>
              </span>
              <?php if ($passed): ?>
                <span class="btn-register-xs" style="opacity:0.4;cursor:default;">已截止</span>
              <?php else: ?>
                <a href="activity.php?id=<?= $act['id'] ?>" class="btn-register-xs">報名</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- 社團論壇（靜態示範） -->
    <div class="forum-block">
      <div class="sec-label">
        <h2>社團論壇</h2>
        <a href="#" class="see-all">查看全部 →</a>
      </div>
      <div class="forum-list">
        <div class="forum-item">
          <div class="forum-icon promote">📣</div>
          <div class="forum-body">
            <div class="forum-tags"><span class="ftag red">宣傳</span><span class="ftag gray">輔大國樂社</span></div>
            <h3>【招募】2026年度新生招募開始！</h3>
            <p>歡迎對國樂有興趣的新生加入我們，每週三、五晚間練習，不分程度皆可報名。</p>
            <div class="forum-foot">
              <span class="forum-stat"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>34</span>
              <span class="forum-stat"><svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>128</span>
              <span class="forum-stat" style="margin-left:auto;">3小時前</span>
            </div>
          </div>
        </div>
        <div class="forum-item">
          <div class="forum-icon ask">❓</div>
          <div class="forum-body">
            <div class="forum-tags"><span class="ftag blue">提問</span><span class="ftag gray">校園生活</span></div>
            <h3>請問校園美食哪裡最好吃？</h3>
            <p>新生想了解哪個學餐比較推薦，或是附近有什麼平價又好吃的選擇？</p>
            <div class="forum-foot">
              <span class="forum-stat"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>450</span>
              <span class="forum-stat"><svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>892</span>
              <span class="forum-stat" style="margin-left:auto;">1天前</span>
            </div>
          </div>
        </div>
        <div class="forum-item">
          <div class="forum-icon share">✨</div>
          <div class="forum-body">
            <div class="forum-tags"><span class="ftag green">分享</span><span class="ftag gray">AI研究社</span></div>
            <h3>【熱門】AI 深度學習研討會心得分享</h3>
            <p>上週參加了 AI 研討會，整理了幾個重點筆記與資源，分享給有興趣的同學。</p>
            <div class="forum-foot">
              <span class="forum-stat"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>280</span>
              <span class="forum-stat"><svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>543</span>
              <span class="forum-stat" style="margin-left:auto;">2天前</span>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- SIDEBAR -->
  <div class="sidebar">

    <!-- 系統公告（動態資料） -->
    <div class="sidebar-card">
      <div class="sidebar-card-header">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <h3>系統公告</h3>
      </div>
      <div class="sidebar-card-body">
        <?php foreach ($announcements as $ann):
          [$tagClass, $tagText] = annTag($ann['title']);
        ?>
        <div class="ann-item">
          <span class="ann-tag <?= $tagClass ?>"><?= $tagText ?></span>
          <h4><?= htmlspecialchars($ann['title']) ?></h4>
          <p><?= htmlspecialchars($ann['content']) ?></p>
          <div class="ann-date"><?= date('Y/m/d', strtotime($ann['date'])) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- 熱門社團（動態資料，依追蹤數排序） -->
    <div class="sidebar-card">
      <div class="sidebar-card-header">
        <svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
        <h3>熱門社團</h3>
      </div>
      <div class="hot-clubs-list">
        <?php foreach ($hotClubs as $i => $club):
          $rc = $rankClass[$i] ?? '';
        ?>
        <div class="club-item">
          <span class="club-rank <?= $rc ?>"><?= $i + 1 ?></span>
          <div class="club-avatar"><?= htmlspecialchars(clubAbbr($club['name'])) ?></div>
          <div class="club-info">
            <div class="club-name"><?= htmlspecialchars($club['name']) ?></div>
            <div class="club-sub"><?= htmlspecialchars($club['category']) ?> · <?= $club['follow_count'] ?> 人追蹤</div>
          </div>
          <span class="club-arrow">›</span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</div>

<!-- FOOTER -->
<footer>
  <div class="footer-top">
    <div class="foot-col">
      <h5>校園連結</h5>
      <ul>
        <li><a href="#">輔大全球資訊網</a></li>
        <li><a href="#">公文自動化 &amp; ODF</a></li>
        <li><a href="#">高教深耕計畫 &amp; 開放式課程</a></li>
        <li><a href="#">WebMail &amp; LDAP</a></li>
        <li><a href="#">職涯服務 &amp; 學生會</a></li>
      </ul>
    </div>
    <div class="foot-col">
      <h5>公告資訊</h5>
      <ul>
        <li><a href="#">內部控制專區</a></li>
        <li><a href="#">校務財務資訊專區</a></li>
        <li><a href="#">政府公告專區</a></li>
        <li><a href="#">獎助學金</a></li>
        <li><a href="#">行事曆</a></li>
      </ul>
    </div>
    <div class="foot-col">
      <h5>快速連結</h5>
      <ul>
        <li><a href="#">人體研究IRB</a></li>
        <li><a href="#">學術統計報名系統</a></li>
        <li><a href="#">活動報名系統</a></li>
        <li><a href="#">輔大媒體家族</a></li>
        <li><a href="#">研究倫理中心</a></li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="footer-brand">
      <div class="footer-logo-box">FJCU</div>
      <div class="footer-brand-info">
        <strong>天主教輔仁大學</strong>
        <span>242062 新北市新莊區中正路510號</span>
      </div>
    </div>
    <div class="footer-contact">
      <div>電話：(02) 2905-2000</div>
      <div>信箱：pubwww@mail.fju.edu.tw</div>
    </div>
    <div class="footer-copy">天主教輔仁大學 © 2014-2026 版權所有</div>
  </div>
</footer>

</body>
</html>
