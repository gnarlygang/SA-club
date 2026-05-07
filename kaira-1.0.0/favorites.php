<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "api/db.php";
require_once "header.php";

if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('請先登入'); location.href='login.php';</script>";
    exit;
}

$user_id = $_SESSION['user_id'];

/* 活動收藏 */
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
    JOIN activities a 
        ON f.item_id = a.id

    LEFT JOIN clubs c 
        ON c.user_id = a.user_id

    WHERE f.user_id = ?
      AND f.item_type = 'activity'

    ORDER BY f.created_at DESC
");
$stmt->execute([$user_id]);
$activityFavorites = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* 貼文收藏 */
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
    JOIN forum_posts fp 
        ON f.item_id = fp.id

    LEFT JOIN forum_categories fc 
        ON fp.category_id = fc.id

    LEFT JOIN users u 
        ON fp.user_id = u.user_id

    WHERE f.user_id = ?
      AND f.item_type = 'post'

    ORDER BY f.created_at DESC
");
$stmt->execute([$user_id]);
$postFavorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<title>我的收藏｜FJU_CLUB</title>
<link rel="stylesheet" href="style.css">

<style>
body {
    background: #f5f2ed;
}

.favorite-page {
    max-width: 1180px;
    margin: 48px auto;
    padding: 0 24px 80px;
}

.favorite-header {
    margin-bottom: 28px;
}

.favorite-header h1 {
    font-size: 32px;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 8px;
}

.favorite-header p {
    color: #64748b;
    font-size: 15px;
}

.favorite-tabs {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
}

.favorite-tab-btn {
    border: none;
    background: #e5e7eb;
    color: #334155;
    padding: 10px 18px;
    border-radius: 999px;
    cursor: pointer;
    font-weight: 600;
}

.favorite-tab-btn.active {
    background: #0f2a44;
    color: white;
}

.favorite-section {
    display: none;
}

.favorite-section.active {
    display: block;
}

.favorite-list {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.favorite-card {
    background: white;
    border-radius: 22px;
    padding: 24px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
    border: 1px solid #e5e7eb;
}

.favorite-card-top {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: flex-start;
    margin-bottom: 10px;
}

.favorite-card h3 {
    font-size: 21px;
    color: #0f172a;
    margin: 0;
    line-height: 1.4;
}

.favorite-badge {
    flex-shrink: 0;
    background: #dbeafe;
    color: #1e3a8a;
    font-size: 13px;
    padding: 6px 12px;
    border-radius: 999px;
}

.favorite-meta {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 14px;
    line-height: 1.8;
}

.favorite-content {
    color: #475569;
    line-height: 1.8;
    margin-bottom: 18px;
    font-size: 15px;
}

.favorite-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.read-btn {
    display: inline-block;
    background: #1e3a8a;
    color: white;
    padding: 9px 16px;
    border-radius: 999px;
    text-decoration: none;
    font-size: 14px;
    border: none;
}

.read-btn:hover {
    background: #172554;
    color: white;
}

.remove-favorite-btn {
    border: none;
    background: #fee2e2;
    color: #991b1b;
    padding: 9px 16px;
    border-radius: 999px;
    cursor: pointer;
    font-size: 14px;
}

.remove-favorite-btn:hover {
    background: #fecaca;
}

.empty-box {
    background: white;
    border: 1px dashed #cbd5e1;
    border-radius: 22px;
    padding: 50px 30px;
    text-align: center;
    color: #64748b;
}

.empty-box h3 {
    color: #0f172a;
    font-size: 22px;
    margin-bottom: 10px;
}

.empty-box a {
    color: #1e3a8a;
    font-weight: 700;
    text-decoration: none;
}

@media (max-width: 768px) {
    .favorite-tabs {
        flex-direction: column;
        align-items: flex-start;
    }

    .favorite-card-top {
        flex-direction: column;
    }
}
</style>
</head>

<body>

<main class="favorite-page">

    <div class="favorite-header">
        <h1>我的收藏</h1>
        <p>這裡會顯示你收藏的活動與論壇貼文。</p>
    </div>

    <div class="favorite-tabs">
        <button class="favorite-tab-btn active" onclick="showFavoriteTab('activities', this)">
            活動收藏 <?= count($activityFavorites) ?>
        </button>

        <button class="favorite-tab-btn" onclick="showFavoriteTab('posts', this)">
            貼文收藏 <?= count($postFavorites) ?>
        </button>
    </div>

    <!-- 活動收藏 -->
    <section id="activities-section" class="favorite-section active">
        <div class="favorite-list">

            <?php if (count($activityFavorites) === 0): ?>
                <div class="empty-box">
                    <h3>目前還沒有收藏活動</h3>
                    <p>可以到 <a href="activities.php">活動頁</a> 看看有沒有感興趣的內容。</p>
                </div>
            <?php endif; ?>

            <?php foreach ($activityFavorites as $act): ?>
                <article 
                    class="favorite-card" 
                    id="favorite-activity-<?= htmlspecialchars($act['id']) ?>"
                >
                    <div class="favorite-card-top">
                        <h3><?= htmlspecialchars($act['title'] ?? '未命名活動') ?></h3>
                        <span class="favorite-badge">活動</span>
                    </div>

                    <div class="favorite-meta">
                        社團：
                        <?= htmlspecialchars($act['club_name'] ?? $act['organizer'] ?? '未指定社團') ?>
                        ・活動時間：
                        <?= htmlspecialchars($act['event_start'] ?? '未設定') ?>
                        ・收藏時間：
                        <?= htmlspecialchars($act['favorited_at']) ?>
                    </div>

                    <div class="favorite-content">
                        <?= nl2br(htmlspecialchars(mb_substr($act['description'] ?? '', 0, 130))) ?>
                        <?= mb_strlen($act['description'] ?? '') > 130 ? '...' : '' ?>
                    </div>

                    <div class="favorite-actions">
                        <a 
                            href="activity_view.php?id=<?= htmlspecialchars($act['id']) ?>" 
                            class="read-btn"
                        >
                            查看活動
                        </a>

                        <button 
                            class="remove-favorite-btn"
                            data-type="activity"
                            data-id="<?= htmlspecialchars($act['id']) ?>"
                            onclick="removeFavorite(this)"
                        >
                            取消收藏
                        </button>
                    </div>
                </article>
            <?php endforeach; ?>

        </div>
    </section>

    <!-- 貼文收藏 -->
    <section id="posts-section" class="favorite-section">
        <div class="favorite-list">

            <?php if (count($postFavorites) === 0): ?>
                <div class="empty-box">
                    <h3>目前還沒有收藏貼文</h3>
                    <p>可以到 <a href="forum.php">論壇頁</a> 看看有沒有感興趣的內容。</p>
                </div>
            <?php endif; ?>

            <?php foreach ($postFavorites as $post): ?>
                <?php
                $author = $post['nickname'] ?: ($post['username'] ?? '匿名使用者');
                ?>

                <article 
                    class="favorite-card" 
                    id="favorite-post-<?= htmlspecialchars($post['id']) ?>"
                >
                    <div class="favorite-card-top">
                        <h3><?= htmlspecialchars($post['title'] ?? '未命名貼文') ?></h3>
                        <span class="favorite-badge">貼文</span>
                    </div>

                    <div class="favorite-meta">
                        作者：
                        <?= htmlspecialchars($author) ?>
                        ・看板：
                        <?= htmlspecialchars($post['category_name'] ?? '未分類') ?>
                        ・收藏時間：
                        <?= htmlspecialchars($post['favorited_at']) ?>
                    </div>

                    <div class="favorite-content">
                        <?= nl2br(htmlspecialchars(mb_substr($post['content'] ?? '', 0, 130))) ?>
                        <?= mb_strlen($post['content'] ?? '') > 130 ? '...' : '' ?>
                    </div>

                    <div class="favorite-actions">
                        <a 
                            href="forum_post.php?id=<?= htmlspecialchars($post['id']) ?>" 
                            class="read-btn"
                        >
                            查看貼文
                        </a>

                        <button 
                            class="remove-favorite-btn"
                            data-type="post"
                            data-id="<?= htmlspecialchars($post['id']) ?>"
                            onclick="removeFavorite(this)"
                        >
                            取消收藏
                        </button>
                    </div>
                </article>
            <?php endforeach; ?>

        </div>
    </section>

</main>

<script>
function showFavoriteTab(type, btn) {
    document.querySelectorAll(".favorite-tab-btn").forEach(b => {
        b.classList.remove("active");
    });

    document.querySelectorAll(".favorite-section").forEach(section => {
        section.classList.remove("active");
    });

    btn.classList.add("active");

    if (type === "activities") {
        document.getElementById("activities-section").classList.add("active");
    } else {
        document.getElementById("posts-section").classList.add("active");
    }
}

function removeFavorite(btn) {
    const itemId = btn.dataset.id;
    const itemType = btn.dataset.type;

    fetch("api/toggle_favorite.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body:
            "item_id=" + encodeURIComponent(itemId) +
            "&item_type=" + encodeURIComponent(itemType)
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            alert(data.message);
            return;
        }

        const card = document.getElementById("favorite-" + itemType + "-" + itemId);

        if (card) {
            card.remove();
        }

        location.reload();
    })
    .catch(err => {
        console.error(err);
        alert("取消收藏失敗");
    });
}
</script>

<?php require_once "footer.php"; ?>

</body>
</html>