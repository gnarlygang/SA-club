<?php
session_start();
// ─── 資料庫連線設定 ───────────────────────────────────────────────
require_once "api/db.php";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $pdo = null;
}

// ─── 取得篩選參數 ────────────────────────────────────────────────
$sort_by     = $_GET['sort_by'] ?? 'created_at';
$filter_club = $_GET['club']    ?? '';
$filter_fee  = $_GET['fee']     ?? '';
$search      = $_GET['search']  ?? '';

$allowed_sort = ['created_at', 'event_start', 'signup_deadline'];
if (!in_array($sort_by, $allowed_sort)) $sort_by = 'created_at';

// ─── 查詢活動 ────────────────────────────────────────────────────
$activities = [];
$clubs_list = [];

if ($pdo) {
    $clubs_list = $pdo->query("SELECT DISTINCT organizer FROM activities ORDER BY organizer")->fetchAll(PDO::FETCH_COLUMN);

    $sql = "SELECT a.*, c.name AS club_name, c.image AS club_image
            FROM activities a
            LEFT JOIN clubs c ON c.user_id = a.user_id
            WHERE 1=1";
    $params = [];

    if ($filter_club !== '' && $filter_club !== '__subscribed__') {
        $sql .= " AND a.organizer = :club";
        $params[':club'] = $filter_club;
    }
    // TODO: 訂閱功能完成後，在這裡加入 WHERE a.user_id IN (SELECT club_user_id FROM subscriptions WHERE user_id = ?) 的邏輯
    if ($filter_fee === 'free') {
        $sql .= " AND (a.fee = '免費' OR a.fee LIKE '%免費%')";
    } elseif ($filter_fee === 'paid') {
        $sql .= " AND a.fee != '免費' AND a.fee NOT LIKE '%免費%'";
    }
    if ($search !== '') {
        $sql .= " AND (a.title LIKE :search OR a.description LIKE :search2)";
        $params[':search']  = "%$search%";
        $params[':search2'] = "%$search%";
    }

    $sql .= " ORDER BY a.$sort_by DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $clubs_list = ['輔大國樂社', '輔大熱舞社'];
    $activities = [
        ['id'=>1,'title'=>'春季音樂成果發表會','description'=>'輔大國樂社每年春季舉辦的成果發表會，演出曲目包含傳統國樂及現代改編曲，歡迎全校師生蒞臨欣賞。','event_start'=>'2026-05-15 19:00:00','event_end'=>'2026-05-15 21:00:00','location'=>'輔仁大學野聲樓 B1 表演廳','organizer'=>'輔大國樂社','fee'=>'免費入場','target'=>'全校師生及校外人士','signup_deadline'=>'2026-05-10','created_at'=>'2026-04-22 11:14:41','club_image'=>'https://images.unsplash.com/photo-1507838153414-b4b713384a76?auto=format&fit=crop&w=800&q=80'],
        ['id'=>2,'title'=>'國樂入門體驗工作坊','description'=>'想學國樂卻不知從何開始？本工作坊提供二胡、琵琶、古箏等樂器體驗，由社員帶領入門，歡迎零基礎參加。','event_start'=>'2026-05-22 14:00:00','event_end'=>'2026-05-22 17:00:00','location'=>'輔仁大學藝文中心 302 室','organizer'=>'輔大國樂社','fee'=>'NT$100（含材料費）','target'=>'全校學生','signup_deadline'=>'2026-05-18','created_at'=>'2026-04-22 11:14:41','club_image'=>'https://images.unsplash.com/photo-1507838153414-b4b713384a76?auto=format&fit=crop&w=800&q=80'],
        ['id'=>3,'title'=>'宿營','description'=>'宿營活動，歡迎報名！','event_start'=>'2026-06-19 19:39:00','event_end'=>'2026-06-22 19:39:00','location'=>'中美堂','organizer'=>'國樂社','fee'=>'2000','target'=>'不限','signup_deadline'=>'2026-04-29','created_at'=>'2026-04-22 11:40:10','club_image'=>'https://images.unsplash.com/photo-1507838153414-b4b713384a76?auto=format&fit=crop&w=800&q=80'],
        ['id'=>4,'title'=>'春季音樂成果發表會','description'=>'輔大熱舞社精彩演出，歡迎全校師生蒞臨欣賞。','event_start'=>'2026-05-15 19:00:00','event_end'=>'2026-05-15 21:00:00','location'=>'輔仁大學野聲樓 B1 表演廳','organizer'=>'輔大熱舞社','fee'=>'免費入場','target'=>'全校師生及校外人士','signup_deadline'=>'2026-05-10','created_at'=>'2026-04-22 11:14:41','club_image'=>'https://images.unsplash.com/photo-1547153760-18fc86324498?auto=format&fit=crop&w=800&q=80'],
    ];
}

function isFree(string $fee): bool {
    return str_contains($fee, '免費') || strtolower($fee) === 'free' || $fee === '0';
}
function formatDate(string $dt): string { return date('Y/m/d', strtotime($dt)); }
function formatDateTime(string $dt): string { return date('Y/m/d H:i', strtotime($dt)); }
function isDeadlineSoon(string $d): bool { $t = strtotime($d); return ($t - time()) <= 7*86400 && $t >= time(); }
function isDeadlinePassed(string $d): bool { return strtotime($d) < time(); }
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>活動列表 — FJU_CLUB</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;600;700&family=Noto+Sans+TC:wght@300;400;500&display=swap" rel="stylesheet">
<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --ink: #1a1a2e; --ink-soft: #4a4a6a; --ink-mute: #8888aa;
    --paper: #fafaf8; --paper-2: #f2f2ee;
    --accent: #c8502a; --green: #2d8a5e;
    --border: #ddddd8; --shadow: 0 2px 16px rgba(26,26,46,.08);
    --radius: 12px;
    --font-serif: 'Noto Serif TC', serif;
    --font-sans: 'Noto Sans TC', sans-serif;
}
body { font-family: var(--font-sans); background: var(--paper); color: var(--ink); min-height: 100vh; }

/* Hero */
.hero { background: var(--ink); padding: 3.5rem 2rem 3rem; text-align: center; position: relative; overflow: hidden; }
.hero::before { content:''; position:absolute; inset:0; background: repeating-linear-gradient(45deg,transparent,transparent 24px,rgba(255,255,255,.025) 24px,rgba(255,255,255,.025) 25px); }
.hero h1 { font-family:var(--font-serif); font-size:clamp(1.8rem,4vw,2.8rem); color:#fff; letter-spacing:.1em; position:relative; }
.hero p { margin-top:.75rem; color:rgba(255,255,255,.55); font-size:.9rem; position:relative; }
.hero .count-badge { display:inline-block; margin-top:1.2rem; background:var(--accent); color:#fff; padding:.3rem .9rem; border-radius:99px; font-size:.8rem; font-weight:500; position:relative; }

/* Filter */
.filter-wrap { background:#fff; border-bottom:1px solid var(--border); position:relative; box-shadow:0 2px 8px rgba(0,0,0,.04); }
.filter-inner { max-width:1100px; margin:0 auto; padding:1rem 1.5rem; display:flex; flex-wrap:wrap; gap:.75rem; align-items:center; }
.search-box { display:flex; align-items:center; gap:.5rem; background:var(--paper-2); border:1px solid var(--border); border-radius:8px; padding:.45rem .8rem; flex:1; min-width:180px; }
.search-box svg { width:16px; height:16px; color:var(--ink-mute); flex-shrink:0; }
.search-box input { border:none; background:transparent; font-family:var(--font-sans); font-size:.88rem; color:var(--ink); width:100%; outline:none; }
.filter-select { appearance:none; background:var(--paper-2) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%238888aa' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E") no-repeat right .7rem center; border:1px solid var(--border); border-radius:8px; padding:.45rem 2rem .45rem .8rem; font-family:var(--font-sans); font-size:.85rem; color:var(--ink); cursor:pointer; outline:none; }
.filter-select:focus { border-color:var(--accent); }
.sort-pills { display:flex; gap:.4rem; flex-wrap:wrap; }
.sort-pill { padding:.38rem .85rem; border-radius:99px; border:1px solid var(--border); background:var(--paper-2); font-size:.8rem; color:var(--ink-soft); text-decoration:none; transition:all .18s; white-space:nowrap; }
.sort-pill.active, .sort-pill:hover { background:var(--ink); color:#fff; border-color:var(--ink); }
.sort-pill.active { font-weight:500; }
.filter-label { font-size:.78rem; color:var(--ink-mute); white-space:nowrap; }
.filter-divider { width:1px; height:28px; background:var(--border); flex-shrink:0; }

/* Main */
.main { max-width:1100px; margin:0 auto; padding:2rem 1.5rem 4rem; }
.result-meta { font-size:.82rem; color:var(--ink-mute); margin-bottom:1.5rem; }
.result-meta strong { color:var(--ink); }

/* Cards */
.cards { display:grid; gap:1.25rem; }
.card { background:#fff; border:1px solid var(--border); border-radius:var(--radius); box-shadow:var(--shadow); display:grid; grid-template-columns:1fr auto; overflow:hidden; transition:transform .2s,box-shadow .2s; animation:fadeUp .4s ease both; }
.card:hover { transform:translateY(-2px); box-shadow:0 8px 32px rgba(26,26,46,.12); }
@keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
.card-body { padding:1.4rem 1.5rem; min-width:0; }
.card-organizer { display:flex; align-items:center; gap:.5rem; margin-bottom:.6rem; flex-wrap:wrap; }
.org-avatar { width:28px; height:28px; border-radius:50%; object-fit:cover; border:1px solid var(--border); flex-shrink:0; }
.org-name { font-size:.8rem; font-weight:500; color:var(--ink-soft); }
.card-title { font-family:var(--font-serif); font-size:1.15rem; font-weight:600; color:var(--ink); margin-bottom:.6rem; line-height:1.4; }
.card-desc { font-size:.85rem; color:var(--ink-soft); line-height:1.65; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; margin-bottom:.9rem; }
.card-meta { display:flex; flex-wrap:wrap; gap:.5rem .9rem; font-size:.78rem; color:var(--ink-soft); }
.meta-item { display:flex; align-items:center; gap:.3rem; }
.meta-item svg { width:13px; height:13px; flex-shrink:0; }
.badge { display:inline-block; padding:.2rem .6rem; border-radius:99px; font-size:.72rem; font-weight:500; }
.badge-free   { background:#e8f5ed; color:var(--green); }
.badge-paid   { background:#fff3e0; color:#b85c00; }
.badge-soon   { background:#fff0ed; color:var(--accent); }
.badge-closed { background:#f0f0f0; color:var(--ink-mute); }
.card-img-wrap { width:160px; flex-shrink:0; overflow:hidden; }
.card-img-wrap img { width:100%; height:100%; object-fit:cover; transition:transform .4s; }
.card:hover .card-img-wrap img { transform:scale(1.04); }
.card-footer { display:flex; align-items:center; justify-content:space-between; padding:.8rem 1.5rem; border-top:1px solid var(--border); background:var(--paper-2); font-size:.78rem; color:var(--ink-mute); grid-column:1/-1; }
.card-footer .signup-info { display:flex; align-items:center; gap:.4rem; }
.card-footer svg { width:13px; height:13px; }
.signup-btn { display:inline-flex; align-items:center; gap:.35rem; background:var(--ink); color:#fff; padding:.35rem .9rem; border-radius:6px; font-size:.78rem; font-weight:500; text-decoration:none; transition:background .18s; }
.signup-btn:hover { background:var(--accent); }
.signup-btn.closed { background:var(--border); color:var(--ink-mute); pointer-events:none; }
.empty { text-align:center; padding:5rem 1rem; color:var(--ink-mute); }
.empty svg { width:56px; height:56px; margin-bottom:1rem; }
.empty h3 { font-family:var(--font-serif); font-size:1.2rem; color:var(--ink-soft); }
.empty p  { font-size:.88rem; margin-top:.5rem; }
@media (max-width:640px) {
    .card { grid-template-columns:1fr; }
    .card-img-wrap { width:100%; height:160px; }
    .filter-inner { gap:.5rem; }
}
</style>
</head>
<body>

<?php require_once "header.php"; ?>

<!-- Hero -->
<div class="hero">
    <h1>所有活動</h1>
    <p>瀏覽各社團最新發佈的活動資訊</p>
    <span class="count-badge"><?= count($activities) ?> 項活動</span>
</div>

<!-- Filter Bar -->
<div class="filter-wrap">
    <form class="filter-inner" method="GET" action="">
        <div class="search-box">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" name="search" placeholder="搜尋活動名稱…" value="<?= htmlspecialchars($search) ?>">
        </div>

        <div class="filter-divider"></div>
        <span class="filter-label">社團</span>
        <select name="club" class="filter-select" onchange="this.form.submit()">
            <option value="">全部社團</option>
            <option value="__subscribed__" <?= $filter_club === '__subscribed__' ? 'selected' : '' ?>>⭐ 已訂閱社團</option>
            <?php foreach ($clubs_list as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $filter_club === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="fee" class="filter-select" onchange="this.form.submit()">
            <option value="">全部費用</option>
            <option value="free" <?= $filter_fee === 'free' ? 'selected' : '' ?>>免費</option>
            <option value="paid" <?= $filter_fee === 'paid' ? 'selected' : '' ?>>需收費</option>
        </select>

        <div class="filter-divider"></div>
        <span class="filter-label">排序</span>
        <div class="sort-pills">
            <?php
            $sorts = ['created_at'=>'依發佈時間','event_start'=>'依活動時間','signup_deadline'=>'依報名截止'];
            foreach ($sorts as $val => $label):
                $active = $sort_by === $val ? ' active' : '';
                $qs = http_build_query(array_merge($_GET, ['sort_by' => $val]));
            ?>
            <a href="?<?= $qs ?>" class="sort-pill<?= $active ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>

        <input type="hidden" name="sort_by" value="<?= htmlspecialchars($sort_by) ?>">
        <?php if ($search || $filter_club || $filter_fee): ?>
            <a href="activities.php" style="font-size:.8rem;color:var(--accent);text-decoration:none;white-space:nowrap;">✕ 清除篩選</a>
        <?php endif; ?>
    </form>
</div>

<!-- Main -->
<main class="main">
    <p class="result-meta">
        共找到 <strong><?= count($activities) ?></strong> 項活動
        <?php if ($filter_club === '__subscribed__'): ?> · 篩選：<strong>已訂閱社團</strong>（訂閱功能建置中）<?php elseif ($filter_club): ?> · 社團：<strong><?= htmlspecialchars($filter_club) ?></strong><?php endif; ?>
        <?php if ($filter_fee === 'free'): ?> · <strong>免費</strong><?php elseif ($filter_fee === 'paid'): ?> · <strong>需收費</strong><?php endif; ?>
        <?php if ($search): ?> · 搜尋：<strong>"<?= htmlspecialchars($search) ?>"</strong><?php endif; ?>
    </p>

    <?php if (empty($activities)): ?>
    <div class="empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
        <h3>找不到符合條件的活動</h3>
        <p>請調整篩選條件，或 <a href="activities.php" style="color:var(--accent)">查看全部活動</a></p>
    </div>

    <?php else: ?>
    <div class="cards">
        <?php foreach ($activities as $i => $act):
            $free   = isFree($act['fee']);
            $soon   = isDeadlineSoon($act['signup_deadline']);
            $closed = isDeadlinePassed($act['signup_deadline']);
            $img    = $act['club_image'] ?? 'https://images.unsplash.com/photo-1507838153414-b4b713384a76?auto=format&fit=crop&w=800&q=80';
        ?>
        <article class="card" style="animation-delay:<?= $i * 0.06 ?>s">
            <div class="card-body">
                <div class="card-organizer">
                    <img class="org-avatar" src="<?= htmlspecialchars($img) ?>" alt="">
                    <span class="org-name"><?= htmlspecialchars($act['organizer']) ?></span>
                    <?php if ($free): ?>
                        <span class="badge badge-free">免費</span>
                    <?php else: ?>
                        <span class="badge badge-paid"><?= htmlspecialchars($act['fee']) ?></span>
                    <?php endif; ?>
                    <?php if ($closed): ?>
                        <span class="badge badge-closed">已截止</span>
                    <?php elseif ($soon): ?>
                        <span class="badge badge-soon">即將截止</span>
                    <?php endif; ?>
                </div>

                <h2 class="card-title"><?= htmlspecialchars($act['title']) ?></h2>
                <p class="card-desc"><?= htmlspecialchars($act['description']) ?></p>

                <div class="card-meta">
                    <span class="meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        <?= formatDateTime($act['event_start']) ?>
                        <?php if (!empty($act['event_end'])): ?> – <?= date('H:i', strtotime($act['event_end'])) ?><?php endif; ?>
                    </span>
                    <span class="meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        <?= htmlspecialchars($act['location']) ?>
                    </span>
                    <span class="meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                        <?= htmlspecialchars($act['target']) ?>
                    </span>
                </div>
            </div>

            <div class="card-img-wrap">
                <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($act['title']) ?>">
            </div>

            <div class="card-footer">
                <span class="signup-info">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
                    報名截止：<?= formatDate($act['signup_deadline']) ?>
                    &nbsp;·&nbsp; 發佈於 <?= formatDate($act['created_at']) ?>
                </span>
                <a href="activity_detail.php?id=<?= $act['id'] ?>" class="signup-btn <?= $closed ? 'closed' : '' ?>">
                    <?= $closed ? '已截止' : '查看詳情' ?>
                    <?php if (!$closed): ?>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    <?php endif; ?>
                </a>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>
<?php include_once 'footer.php'; ?>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>