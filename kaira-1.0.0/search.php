<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? 0;

require_once "api/db.php";

$search  = trim($_GET['q'] ?? '');
$results = [];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($search !== '') {
        // 拆成單字
        $chars = [];
        $len = mb_strlen($search, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($search, $i, 1, 'UTF-8');
            if (trim($char) !== '') $chars[] = $char;
        }

        $char_conditions = [];
        $params = [];
        foreach ($chars as $idx => $char) {
            $likeChar = "%$char%";
            $char_conditions[] = "c.id IN (
                SELECT id FROM clubs WHERE name LIKE :cn{$idx}
                UNION SELECT id FROM clubs WHERE category LIKE :cc{$idx}
                UNION SELECT id FROM clubs WHERE description LIKE :cd{$idx}
                UNION SELECT club_id FROM club_tags WHERE tag_name LIKE :ct{$idx}
            )";
            $params[":cn{$idx}"] = $likeChar;
            $params[":cc{$idx}"] = $likeChar;
            $params[":cd{$idx}"] = $likeChar;
            $params[":ct{$idx}"] = $likeChar;
        }

        $stmt = $pdo->prepare("
            SELECT c.*, GROUP_CONCAT(DISTINCT ct.tag_name SEPARATOR ',') AS tags
            FROM clubs c
            LEFT JOIN club_tags ct ON ct.club_id = c.id
            WHERE " . implode(' AND ', $char_conditions) . "
            GROUP BY c.id
            ORDER BY c.name ASC
        ");
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    die("資料庫連線失敗：" . $e->getMessage());
}

require_once "header.php";
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>搜尋社團 — FJU_CLUB</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;600;700&family=Noto+Sans+TC:wght@300;400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --ink: #1a1a2e; --ink-soft: #4a4a6a; --ink-mute: #8888aa;
    --paper: #fafaf8; --paper-2: #f2f2ee;
    --accent: #c8502a;
    --border: #ddddd8; --shadow: 0 2px 16px rgba(26,26,46,.08);
    --radius: 16px;
    --font-serif: 'Noto Serif TC', serif;
    --font-sans: 'Noto Sans TC', sans-serif;
}
body { font-family: var(--font-sans); background: var(--paper); color: var(--ink); min-height: 100vh; }

.search-hero {
    background: var(--ink);
    padding: 4rem 2rem 3.5rem;
    text-align: center;
    position: relative;
    overflow: hidden;
}
.search-hero::before {
    content: '';
    position: absolute; inset: 0;
    background: repeating-linear-gradient(45deg, transparent, transparent 24px, rgba(255,255,255,.025) 24px, rgba(255,255,255,.025) 25px);
}
.search-hero h1 {
    font-family: var(--font-serif);
    font-size: clamp(1.6rem, 3.5vw, 2.4rem);
    color: #fff;
    letter-spacing: .1em;
    position: relative;
    margin-bottom: 1.8rem;
}
.search-form {
    position: relative;
    max-width: 560px;
    margin: 0 auto;
}
.search-input {
    width: 100%;
    padding: .9rem 3.5rem .9rem 1.4rem;
    border-radius: 99px;
    border: none;
    font-family: var(--font-sans);
    font-size: 1rem;
    color: var(--ink);
    background: #fff;
    outline: none;
    box-shadow: 0 4px 24px rgba(0,0,0,.2);
}
.search-btn {
    position: absolute;
    right: 6px; top: 50%;
    transform: translateY(-50%);
    background: var(--accent);
    border: none;
    border-radius: 99px;
    width: 42px; height: 42px;
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    cursor: pointer;
    transition: background .2s;
}
.search-btn:hover { background: #a03e1e; }
.search-hint { margin-top: .9rem; color: rgba(255,255,255,.45); font-size: .82rem; position: relative; }

.main { max-width: 1100px; margin: 0 auto; padding: 2.5rem 1.5rem 5rem; }
.result-meta { font-size: .85rem; color: var(--ink-mute); margin-bottom: 1.8rem; }
.result-meta strong { color: var(--ink); font-size: 1rem; }

.cards-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
}
@media (max-width: 900px) { .cards-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px) { .cards-grid { grid-template-columns: 1fr; } }

.club-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: var(--shadow);
    transition: transform .2s, box-shadow .2s;
    animation: fadeUp .4s ease both;
    display: flex;
    flex-direction: column;
}
.club-card:hover { transform: translateY(-3px); box-shadow: 0 10px 36px rgba(26,26,46,.13); }
@keyframes fadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }

.club-img { width: 100%; height: 180px; object-fit: cover; display: block; }
.club-img-placeholder {
    width: 100%; height: 180px;
    background: linear-gradient(135deg, var(--paper-2), var(--border));
    display: flex; align-items: center; justify-content: center;
    color: var(--ink-mute); font-size: 2.5rem;
}
.club-body { padding: 1.25rem 1.3rem; flex: 1; display: flex; flex-direction: column; }
.club-category {
    display: inline-block;
    background: #fff8e8; color: #a07020;
    font-size: .72rem; font-weight: 500;
    padding: .2rem .7rem; border-radius: 99px;
    margin-bottom: .7rem; border: 1px solid #f0dfa0;
}
.club-name {
    font-family: var(--font-serif);
    font-size: 1.1rem; font-weight: 700;
    color: var(--ink); margin-bottom: .5rem;
}
.club-desc {
    font-size: .83rem; color: var(--ink-soft);
    line-height: 1.65;
    display: -webkit-box; -webkit-line-clamp: 2;
    -webkit-box-orient: vertical; overflow: hidden;
    margin-bottom: .9rem; flex: 1;
}
.club-tags { display: flex; flex-wrap: wrap; gap: .35rem; margin-bottom: 1rem; }
.club-tag {
    background: var(--paper-2); color: var(--ink-soft);
    font-size: .72rem; padding: .2rem .6rem;
    border-radius: 99px; border: 1px solid var(--border);
}
.club-footer {
    padding: .75rem 1.3rem;
    border-top: 1px solid var(--border);
    background: var(--paper-2);
    display: flex; justify-content: flex-end;
}
.detail-btn {
    display: inline-flex; align-items: center; gap: .35rem;
    color: var(--accent); font-size: .82rem; font-weight: 500;
    text-decoration: none; transition: gap .2s;
}
.detail-btn:hover { gap: .6rem; color: var(--accent); }

.state-box { text-align: center; padding: 5rem 1rem; color: var(--ink-mute); }
.state-box i { font-size: 3.5rem; display: block; margin-bottom: 1.2rem; }
.state-box h3 { font-family: var(--font-serif); font-size: 1.2rem; color: var(--ink-soft); margin-bottom: .5rem; }
.state-box p { font-size: .88rem; }
</style>
</head>
<body>

<!-- Search Hero -->
<div class="search-hero">
    <h1>搜尋社團</h1>
    <form class="search-form" method="GET" action="search.php">
        <input
            class="search-input"
            type="text"
            name="q"
            placeholder="輸入社團名稱、分類或關鍵字…"
            value="<?= htmlspecialchars($search) ?>"
            autofocus
        >
        <button class="search-btn" type="submit">
            <i class="bi bi-search"></i>
        </button>
    </form>
    <p class="search-hint">可搜尋：社團名稱、社團分類、關鍵字標籤</p>
</div>

<main class="main">
    <?php if ($search === ''): ?>
        <div class="state-box">
            <i class="bi bi-search"></i>
            <h3>輸入關鍵字開始搜尋</h3>
            <p>試試看「音樂」、「熱舞」、「輔舞」…</p>
        </div>

    <?php elseif (empty($results)): ?>
        <div class="state-box">
            <i class="bi bi-emoji-frown"></i>
            <h3>找不到「<?= htmlspecialchars($search) ?>」相關社團</h3>
            <p>請嘗試其他關鍵字，或 <a href="clubs.php" style="color:var(--accent)">瀏覽所有社團</a></p>
        </div>

    <?php else: ?>
        <p class="result-meta">
            搜尋「<strong><?= htmlspecialchars($search) ?></strong>」，
            共找到 <strong><?= count($results) ?></strong> 個社團
        </p>
        <div class="cards-grid">
            <?php foreach ($results as $i => $club):
                $tags = array_filter(array_map('trim', explode(',', $club['tags'] ?? '')));
                $img  = $club['image'] ?? '';
            ?>
            <div class="club-card" style="animation-delay:<?= $i * 0.05 ?>s">
                <?php if ($img): ?>
                    <img class="club-img" src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($club['name']) ?>">
                <?php else: ?>
                    <div class="club-img-placeholder"><i class="bi bi-people-fill"></i></div>
                <?php endif; ?>
                <div class="club-body">
                    <span class="club-category"><?= htmlspecialchars($club['category']) ?></span>
                    <div class="club-name"><?= htmlspecialchars($club['name']) ?></div>
                    <p class="club-desc"><?= htmlspecialchars($club['description']) ?></p>
                    <?php if (!empty($tags)): ?>
                    <div class="club-tags">
                        <?php foreach ($tags as $tag): ?>
                            <span class="club-tag"><?= htmlspecialchars($tag) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="club-footer">
                    <a href="club_detail.php?id=<?= $club['id'] ?>" class="detail-btn">
                        查看詳情 <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php require "footer.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>