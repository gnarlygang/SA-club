<?php
session_start();

<<<<<<< HEAD
$host   = 'localhost';
$dbname = 'sa2026';
$user   = 'root';
$pass   = '';

=======
require_once "api/db.php";
>>>>>>> ff9d9d8dfc7e99533a15e6cde67f9a611bbc9300
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $pdo = null;
}

$categories = ['學術性社團','休閒聯誼性社團','服務性社團','體能性社團','藝術性社團','音樂性社團'];
$cat = $_GET['cat'] ?? '';

$clubs = [];
if ($pdo) {
    if ($cat && in_array($cat, $categories)) {
        $stmt = $pdo->prepare("
            SELECT c.*, GROUP_CONCAT(ct.tag_name ORDER BY ct.id SEPARATOR ',') AS tags
            FROM clubs c
            LEFT JOIN club_tags ct ON ct.club_id = c.id
            WHERE c.category = :cat
            GROUP BY c.id ORDER BY c.id ASC
        ");
        $stmt->execute([':cat' => $cat]);
    } else {
        $stmt = $pdo->query("
            SELECT c.*, GROUP_CONCAT(ct.tag_name ORDER BY ct.id SEPARATOR ',') AS tags
            FROM clubs c
            LEFT JOIN club_tags ct ON ct.club_id = c.id
            GROUP BY c.id ORDER BY c.category, c.id ASC
        ");
    }
    $clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// header.php 輸出 <!DOCTYPE html><html><head>...<body> 和 navbar
require_once "header.php";
?>

<style>
body { font-family: "Microsoft JhengHei", sans-serif; background: #f8f9fa; }

.clubs-hero {
    background: #1a1a2e; padding: 3rem 1.5rem 2.5rem;
    text-align: center; position: relative; overflow: hidden;
}
.clubs-hero::before {
    content:''; position:absolute; inset:0;
    background: repeating-linear-gradient(45deg,transparent,transparent 24px,rgba(255,255,255,.03) 24px,rgba(255,255,255,.03) 25px);
}
.clubs-hero h1 { font-size: clamp(1.6rem,3vw,2.4rem); color:#fff; letter-spacing:.1em; position:relative; margin-bottom:.5rem; }
.clubs-hero p  { color:rgba(255,255,255,.55); font-size:.9rem; position:relative; }

.cat-tabs-wrap { background:#fff; border-bottom:1px solid #e0e0e0; box-shadow:0 2px 8px rgba(0,0,0,.04); }
.cat-tabs { max-width:1100px; margin:0 auto; padding:.75rem 1.5rem; display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; }
.cat-tab {
    padding:.4rem 1rem; border-radius:99px; border:1px solid #ddd; background:#f5f5f5;
    font-size:.83rem; color:#555; text-decoration:none; transition:all .18s; white-space:nowrap;
}
.cat-tab:hover  { background:#333; color:#fff; border-color:#333; }
.cat-tab.active { background:#1a1a2e; color:#fff; border-color:#1a1a2e; font-weight:500; }

.count-info { max-width:1100px; margin:1.5rem auto .75rem; padding:0 1.5rem; font-size:.83rem; color:#888; }
.count-info strong { color:#333; }

.clubs-grid {
    max-width:1100px; margin:0 auto 3rem; padding:0 1.5rem;
    display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:1.2rem;
}
.club-card {
    background:#fff; border-radius:12px; border:1px solid #e8e8e8;
    box-shadow:0 2px 12px rgba(0,0,0,.06); overflow:hidden;
    text-decoration:none; color:inherit; transition:transform .2s, box-shadow .2s;
    display:flex; flex-direction:column; animation:fadeUp .35s ease both;
}
.club-card:hover { transform:translateY(-4px); box-shadow:0 8px 28px rgba(0,0,0,.12); color:inherit; }
@keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }

.club-card-img { width:100%; height:180px; object-fit:cover; background:#e9ecef; }
.club-card-img-placeholder {
    width:100%; height:180px;
    background:linear-gradient(135deg,#e9ecef,#dee2e6);
    display:flex; align-items:center; justify-content:center;
    color:#adb5bd; font-size:2.5rem;
}
.club-card-body { padding:1rem 1.1rem; flex:1; display:flex; flex-direction:column; }
.club-cat-badge { display:inline-block; padding:.2rem .6rem; border-radius:99px; font-size:.7rem; font-weight:500; margin-bottom:.5rem; background:#f0f0f0; color:#666; }
.cat-學術性社團   { background:#e8f0fe; color:#1a56db; }
.cat-休閒聯誼性社團{ background:#fef3c7; color:#92400e; }
.cat-服務性社團   { background:#d1fae5; color:#065f46; }
.cat-體能性社團   { background:#fee2e2; color:#991b1b; }
.cat-藝術性社團   { background:#ede9fe; color:#5b21b6; }
.cat-音樂性社團   { background:#fce7f3; color:#9d174d; }

.club-name { font-size:1rem; font-weight:700; color:#1a1a2e; margin-bottom:.35rem; }
.club-en   { font-size:.75rem; color:#999; margin-bottom:.5rem; }
.club-tags { margin-top:.7rem; display:flex; flex-wrap:wrap; gap:.3rem; }
.club-tag  { font-size:.68rem; padding:.15rem .5rem; border-radius:99px; background:#f5f5f5; color:#777; border:1px solid #e8e8e8; }
.club-card-footer { padding:.6rem 1.1rem; border-top:1px solid #f0f0f0; font-size:.75rem; color:#aaa; display:flex; align-items:center; justify-content:space-between; }
.view-more { font-size:.75rem; color:#c8502a; font-weight:500; }

.empty-state { text-align:center; padding:4rem 1rem; color:#aaa; grid-column:1/-1; }
.empty-state i { font-size:3rem; margin-bottom:1rem; display:block; }

@media(max-width:640px) {
    .clubs-grid { grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:.8rem; }
    .cat-tabs { gap:.35rem; }
}
</style>

<!-- Hero -->
<div class="clubs-hero">
    <h1>社團介紹</h1>
    <p>探索輔仁大學各類型社團，找到屬於你的舞台</p>
</div>

<!-- Category Tabs -->
<div class="cat-tabs-wrap">
    <div class="cat-tabs">
        <a href="clubs.php" class="cat-tab <?= $cat === '' ? 'active' : '' ?>">全部</a>
        <?php foreach ($categories as $c): ?>
        <a href="clubs.php?cat=<?= urlencode($c) ?>"
           class="cat-tab <?= $cat === $c ? 'active' : '' ?>">
            <?= htmlspecialchars($c) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Count -->
<div class="count-info">
    <?php if ($cat): ?>
        <strong><?= htmlspecialchars($cat) ?></strong>，共 <strong><?= count($clubs) ?></strong> 個社團
    <?php else: ?>
        全部社團，共 <strong><?= count($clubs) ?></strong> 個
    <?php endif; ?>
</div>

<!-- Cards -->
<div class="clubs-grid">
    <?php if (empty($clubs)): ?>
    <div class="empty-state">
        <i class="bi bi-people"></i>
        <p>此分類目前沒有社團資料</p>
    </div>
    <?php else: ?>
    <?php foreach ($clubs as $i => $club):
        $tags   = $club['tags'] ? explode(',', $club['tags']) : [];
        $hasImg = !empty($club['image']);
        $catCls = 'cat-' . $club['category'];
    ?>
    <a href="club_detail.php?id=<?= $club['id'] ?>" class="club-card" style="animation-delay:<?= min($i,20)*0.04 ?>s">
        <?php if ($hasImg): ?>
            <img class="club-card-img" src="<?= htmlspecialchars($club['image']) ?>" alt="<?= htmlspecialchars($club['name']) ?>">
        <?php else: ?>
            <div class="club-card-img-placeholder"><i class="bi bi-people-fill"></i></div>
        <?php endif; ?>

        <div class="club-card-body">
            <span class="club-cat-badge <?= $catCls ?>"><?= htmlspecialchars($club['category']) ?></span>
            <div class="club-name"><?= htmlspecialchars($club['name']) ?></div>
            <?php if (!empty($club['description'])): ?>
                <div class="club-en"><?= htmlspecialchars(mb_substr($club['description'], 0, 40)) ?></div>
            <?php endif; ?>
            <?php if (!empty($tags)): ?>
            <div class="club-tags">
                <?php foreach (array_slice($tags, 0, 3) as $tag): ?>
                <span class="club-tag"><?= htmlspecialchars($tag) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="club-card-footer">
            <span><?= htmlspecialchars($club['category']) ?></span>
            <span class="view-more">查看詳情 →</span>
        </div>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>

</body>
</html>