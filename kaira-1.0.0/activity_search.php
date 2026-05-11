<?php
session_start();

// ─── 資料庫連線 ──────────────────────────────────────────────────
require_once "api/db.php";

// ─── 取得搜尋關鍵字 ──────────────────────────────────────────────
$search  = trim($_GET['search'] ?? '');
$results = [];

if ($pdo && $search !== '') {
    $stmt = $pdo->prepare("
        SELECT a.*, c.image AS club_image
        FROM activities a
        LEFT JOIN clubs c ON c.user_id = a.user_id
        WHERE a.title LIKE :s OR a.description LIKE :s2 OR a.organizer LIKE :s3
        ORDER BY a.created_at DESC
    ");
    $like = "%$search%";
    $stmt->execute([':s' => $like, ':s2' => $like, ':s3' => $like]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($pdo && $search === '') {
    // 空白時顯示全部
    $stmt = $pdo->query("
        SELECT a.*, c.image AS club_image
        FROM activities a
        LEFT JOIN clubs c ON c.user_id = a.user_id
        ORDER BY a.created_at DESC
    ");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // 假資料
    $results = [
        ['id'=>1,'title'=>'春季音樂成果發表會','description'=>'輔大國樂社每年春季舉辦的成果發表會。','event_start'=>'2026-05-15 19:00:00','event_end'=>'2026-05-15 21:00:00','location'=>'輔仁大學野聲樓 B1 表演廳','organizer'=>'輔大國樂社','fee'=>'免費入場','target'=>'全校師生','signup_deadline'=>'2026-05-10','created_at'=>'2026-04-22 11:14:41','club_image'=>'https://images.unsplash.com/photo-1507838153414-b4b713384a76?auto=format&fit=crop&w=800&q=80'],
        ['id'=>2,'title'=>'國樂入門體驗工作坊','description'=>'提供二胡、琵琶、古箏等樂器體驗。','event_start'=>'2026-05-22 14:00:00','event_end'=>'2026-05-22 17:00:00','location'=>'輔仁大學藝文中心 302 室','organizer'=>'輔大國樂社','fee'=>'NT$100','target'=>'全校學生','signup_deadline'=>'2026-05-18','created_at'=>'2026-04-22 11:14:41','club_image'=>'https://images.unsplash.com/photo-1507838153414-b4b713384a76?auto=format&fit=crop&w=800&q=80'],
    ];
    if ($search !== '') {
        $results = array_filter($results, fn($a) =>
            str_contains($a['title'], $search) ||
            str_contains($a['organizer'], $search) ||
            str_contains($a['description'], $search)
        );
    }
}

function isFree(string $fee): bool { return str_contains($fee, '免費') || $fee === '0'; }
function fmtDate(string $dt): string { return date('Y/m/d', strtotime($dt)); }
function fmtDT(string $dt): string { return date('Y/m/d H:i', strtotime($dt)); }
function deadlinePassed(string $d): bool { return strtotime($d) < time(); }
function deadlineSoon(string $d): bool { $t = strtotime($d); return ($t - time()) <= 7*86400 && $t >= time(); }

require_once "header.php";
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>搜尋活動 — FJU_CLUB</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;600;700&family=Noto+Sans+TC:wght@300;400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
body { font-family: var(--font-sans); background: var(--paper); color: var(--ink); }

/* ─── Search Hero ─────────────────────────────────────────── */
.search-hero {
    background: var(--ink);
    padding: 3rem 1.5rem 2.5rem;
    text-align: center;
    position: relative; overflow: hidden;
}
.search-hero::before {
    content:''; position:absolute; inset:0;
    background: repeating-linear-gradient(45deg,transparent,transparent 24px,rgba(255,255,255,.025) 24px,rgba(255,255,255,.025) 25px);
}
.search-hero h1 { font-family:var(--font-serif); font-size:clamp(1.6rem,3vw,2.4rem); color:#fff; letter-spacing:.1em; position:relative; margin-bottom:1.5rem; }

/* Search Input */
.search-form-wrap { position:relative; max-width:600px; margin:0 auto; }
.search-input {
    width:100%; padding:.85rem 3.5rem .85rem 1.2rem;
    border:none; border-radius:10px;
    font-family:var(--font-sans); font-size:1rem;
    background:#fff; color:var(--ink);
    box-shadow:0 4px 20px rgba(0,0,0,.15);
    outline:none;
}
.search-input:focus { box-shadow:0 4px 24px rgba(200,80,42,.3); }
.search-btn {
    position:absolute; right:.5rem; top:50%; transform:translateY(-50%);
    background:var(--accent); border:none; border-radius:7px;
    color:#fff; padding:.45rem .9rem; cursor:pointer;
    font-size:.9rem; transition:background .18s;
}
.search-btn:hover { background:#a83e20; }
.search-hint { color:rgba(255,255,255,.5); font-size:.82rem; margin-top:.75rem; position:relative; }

/* ─── Main ───────────────────────────────────────────────── */
.main { max-width:900px; margin:0 auto; padding:2rem 1.5rem 4rem; }
.result-meta { font-size:.82rem; color:var(--ink-mute); margin-bottom:1.5rem; }
.result-meta strong { color:var(--ink); }

/* ─── Cards ──────────────────────────────────────────────── */
.cards { display:grid; gap:1.1rem; }
.card {
    background:#fff; border:1px solid var(--border);
    border-radius:var(--radius); box-shadow:var(--shadow);
    display:grid; grid-template-columns:1fr 140px;
    overflow:hidden; transition:transform .2s,box-shadow .2s;
    animation:fadeUp .35s ease both;
}
.card:hover { transform:translateY(-2px); box-shadow:0 8px 28px rgba(26,26,46,.12); }
@keyframes fadeUp { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }

.card-body { padding:1.2rem 1.4rem; min-width:0; }
.card-org { display:flex; align-items:center; gap:.45rem; margin-bottom:.5rem; flex-wrap:wrap; }
.org-avatar { width:26px; height:26px; border-radius:50%; object-fit:cover; border:1px solid var(--border); flex-shrink:0; }
.org-name { font-size:.78rem; font-weight:500; color:var(--ink-soft); }
.badge { display:inline-block; padding:.18rem .55rem; border-radius:99px; font-size:.7rem; font-weight:500; }
.badge-free   { background:#e8f5ed; color:var(--green); }
.badge-paid   { background:#fff3e0; color:#b85c00; }
.badge-soon   { background:#fff0ed; color:var(--accent); }
.badge-closed { background:#f0f0f0; color:var(--ink-mute); }

.card-title { font-family:var(--font-serif); font-size:1.05rem; font-weight:600; color:var(--ink); margin-bottom:.45rem; line-height:1.4; }

/* highlight */
.card-title mark { background:#fff3cd; color:var(--ink); border-radius:3px; padding:0 2px; }

.card-desc { font-size:.82rem; color:var(--ink-soft); line-height:1.6; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; margin-bottom:.8rem; }
.card-meta { display:flex; flex-wrap:wrap; gap:.4rem .8rem; font-size:.76rem; color:var(--ink-soft); }
.meta-item { display:flex; align-items:center; gap:.28rem; }
.meta-item svg { width:12px; height:12px; flex-shrink:0; }

.card-img { overflow:hidden; }
.card-img img { width:100%; height:100%; object-fit:cover; transition:transform .4s; }
.card:hover .card-img img { transform:scale(1.05); }

.card-footer {
    grid-column:1/-1; display:flex; align-items:center; justify-content:space-between;
    padding:.7rem 1.4rem; border-top:1px solid var(--border);
    background:var(--paper-2); font-size:.75rem; color:var(--ink-mute);
}
.detail-btn {
    display:inline-flex; align-items:center; gap:.3rem;
    background:var(--ink); color:#fff; padding:.3rem .8rem;
    border-radius:6px; font-size:.75rem; font-weight:500;
    text-decoration:none; transition:background .18s;
}
.detail-btn:hover { background:var(--accent); color:#fff; }
.detail-btn.closed { background:var(--border); color:var(--ink-mute); pointer-events:none; }

/* ─── Empty ──────────────────────────────────────────────── */
.empty { text-align:center; padding:4rem 1rem; color:var(--ink-mute); }
.empty svg { width:52px; height:52px; margin-bottom:.9rem; }
.empty h3 { font-family:var(--font-serif); font-size:1.1rem; color:var(--ink-soft); }
.empty p { font-size:.85rem; margin-top:.4rem; }

/* ─── No search yet ──────────────────────────────────────── */
.prompt { text-align:center; padding:3rem 1rem; color:var(--ink-mute); font-size:.9rem; }

@media (max-width:560px) {
    .card { grid-template-columns:1fr; }
    .card-img { height:140px; }
}
</style>
</head>
<body>

<?php require_once "header.php"; ?>

<!-- Search Hero -->
<div class="search-hero">
    <h1>搜尋活動</h1>
    <form class="search-form-wrap" method="GET" action="">
        <input class="search-input" type="text" name="search"
               placeholder="輸入活動名稱、社團名稱…"
               value="<?= htmlspecialchars($search) ?>"
               autofocus>
        <button class="search-btn" type="submit">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        </button>
    </form>
    <p class="search-hint">可搜尋活動名稱、主辦社團或活動說明</p>
</div>

<!-- Main -->
<main class="main">

<?php if ($search === ''): ?>
    <p class="prompt">請輸入關鍵字搜尋活動 ✦</p>

<?php elseif (empty($results)): ?>
    <div class="empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
            <path d="M8 11h6M11 8v6" stroke-width="2"/>
        </svg>
        <h3>找不到「<?= htmlspecialchars($search) ?>」相關活動</h3>
        <p>請試試其他關鍵字，或 <a href="activities.php" style="color:var(--accent)">瀏覽全部活動</a></p>
    </div>

<?php else: ?>
    <p class="result-meta">
        「<strong><?= htmlspecialchars($search) ?></strong>」共找到 <strong><?= count($results) ?></strong> 項活動
    </p>

    <div class="cards">
    <?php foreach ($results as $i => $act):
        $free   = isFree($act['fee']);
        $closed = deadlinePassed($act['signup_deadline']);
        $soon   = deadlineSoon($act['signup_deadline']);
        $img    = $act['club_image'] ?? 'https://images.unsplash.com/photo-1507838153414-b4b713384a76?auto=format&fit=crop&w=800&q=80';

        // 關鍵字 highlight
        $titleHtml = htmlspecialchars($act['title']);
        if ($search) {
            $titleHtml = preg_replace('/(' . preg_quote(htmlspecialchars($search), '/') . ')/iu',
                '<mark>$1</mark>', $titleHtml);
        }
    ?>
    <article class="card" style="animation-delay:<?= $i * 0.05 ?>s">
        <div class="card-body">
            <div class="card-org">
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

            <h2 class="card-title"><?= $titleHtml ?></h2>
            <p class="card-desc"><?= htmlspecialchars($act['description']) ?></p>

            <div class="card-meta">
                <span class="meta-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    <?= fmtDT($act['event_start']) ?>
                    <?php if (!empty($act['event_end'])): ?> – <?= date('H:i', strtotime($act['event_end'])) ?><?php endif; ?>
                </span>
                <span class="meta-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <?= htmlspecialchars($act['location']) ?>
                </span>
            </div>
        </div>

        <div class="card-img">
            <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($act['title']) ?>">
        </div>

        <div class="card-footer">
            <span>報名截止：<?= fmtDate($act['signup_deadline']) ?></span>
            <a href="activity_detail.php?id=<?= $act['id'] ?>" class="detail-btn <?= $closed ? 'closed' : '' ?>">
                <?= $closed ? '已截止' : '查看詳情' ?>
                <?php if (!$closed): ?>
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                <?php endif; ?>
            </a>
        </div>
    </article>
    <?php endforeach; ?>
    </div>

<?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>