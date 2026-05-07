<?php
session_start();

require_once "api/db.php";

$current_user_id = $_SESSION['user_id'] ?? 0;

try {
    // 取得所有論壇分類
    $categories = $pdo->query("
        SELECT * 
        FROM forum_categories 
        ORDER BY sort_order ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 目前選擇的分類
    $active_cat = isset($_GET["cat"]) 
        ? (int) $_GET["cat"] 
        : ($categories[0]["id"] ?? 1);

    // 取得目前分類文章，包含：
    // 作者、留言數、是否已收藏
    $stmt = $pdo->prepare("
        SELECT 
            fp.*,
            u.username,
            u.nickname,

            (
                SELECT COUNT(*) 
                FROM forum_comments fc 
                WHERE fc.post_id = fp.id
            ) AS comment_count,

            CASE 
                WHEN f.id IS NOT NULL THEN 1
                ELSE 0
            END AS is_favorited

        FROM forum_posts fp

        LEFT JOIN users u 
            ON u.user_id = fp.user_id

        LEFT JOIN favorites f
            ON f.item_id = fp.id
            AND f.item_type = 'post'
            AND f.user_id = :current_user_id

        WHERE fp.category_id = :cat

        ORDER BY fp.created_at DESC
    ");

    $stmt->execute([
        ":cat" => $active_cat,
        ":current_user_id" => $current_user_id
    ]);

    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 目前分類名稱
    $active_cat_name = "";

    foreach ($categories as $c) {
        if ((int)$c["id"] === (int)$active_cat) {
            $active_cat_name = $c["name"];
            break;
        }
    }

} catch (PDOException $e) {
    die("資料庫錯誤：" . $e->getMessage());
}

require_once "header.php";
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>社團論壇 — 輔大社團平台</title>

<style>
.post-card {
    position: relative;
}

.post-card-link {
    display: block;
    color: inherit;
    text-decoration: none;
}

.post-card-link:hover {
    color: inherit;
    text-decoration: none;
}

.post-card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
}

.forum-bookmark-btn {
    flex-shrink: 0;
    border: 1px solid #e5e7eb;
    background: #ffffff;
    color: #64748b;
    border-radius: 999px;
    padding: 6px 12px;
    font-size: 13px;
    cursor: pointer;
    transition: 0.2s ease;
    white-space: nowrap;
}

.forum-bookmark-btn:hover {
    border-color: #1e3a8a;
    color: #1e3a8a;
    background: #eff6ff;
}

.forum-bookmark-btn.saved {
    background: #1e3a8a;
    border-color: #1e3a8a;
    color: white;
}

.post-card-actions {
    margin-top: 12px;
    display: flex;
    justify-content: flex-end;
}

.post-read-btn {
    display: inline-block;
    background: #0f2a44;
    color: white;
    padding: 7px 14px;
    border-radius: 999px;
    font-size: 13px;
    text-decoration: none;
}

.post-read-btn:hover {
    background: #1e3a8a;
    color: white;
}

#favorite-toast {
    position: fixed;
    bottom: 1.5rem;
    right: 1.5rem;
    z-index: 9999;
    background: #1a1a2e;
    color: #fff;
    padding: .55rem 1.1rem;
    border-radius: 8px;
    font-size: .8rem;
    font-weight: 500;
    box-shadow: 0 4px 16px rgba(0,0,0,.2);
    transition: opacity .3s;
    opacity: 0;
    pointer-events: none;
}
</style>
</head>

<body>

<div class="forum-layout">

    <!-- 左側分類 -->
    <aside class="forum-sidebar">
        <div class="sidebar-card">
            <div class="sidebar-title">
                <i class="bi bi-journals me-2"></i>討論分類
            </div>

            <div class="sidebar-list">
                <?php foreach ($categories as $cat): ?>
                    <a 
                        href="forum.php?cat=<?= htmlspecialchars($cat["id"]) ?>"
                        class="sidebar-item <?= (int)$cat["id"] === (int)$active_cat ? 'active' : '' ?>"
                    >
                        <?= htmlspecialchars($cat["name"]) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </aside>

    <!-- 右側文章列表 -->
    <main class="forum-main">

        <div class="forum-header">
            <div>
                <span class="forum-title">
                    <?= htmlspecialchars($active_cat_name) ?>
                </span>

                <span class="forum-count">
                    <?= count($posts) ?> 篇文章
                </span>
            </div>

            <?php if (!empty($_SESSION["user_id"])): ?>
                <a href="forum_new.php?cat=<?= htmlspecialchars($active_cat) ?>" class="btn-new-post">
                    <i class="bi bi-pencil-square"></i>發表文章
                </a>
            <?php endif; ?>
        </div>

        <?php if (empty($_SESSION["user_id"])): ?>
            <div class="login-prompt">
                <a href="login.php">登入</a> 後即可發表文章、留言與收藏貼文
            </div>
        <?php endif; ?>

        <?php if (empty($posts)): ?>

            <div class="empty-state">
                <i class="bi bi-chat-square-text"></i>
                目前還沒有文章，成為第一個發表的人吧！
            </div>

        <?php else: ?>

            <?php foreach ($posts as $post): ?>

                <?php
                $author = $post["nickname"] ?: ($post["username"] ?? "匿名使用者");

                $time_diff = time() - strtotime($post["created_at"]);

                if ($time_diff < 60) {
                    $time_str = "剛剛";
                } elseif ($time_diff < 3600) {
                    $time_str = floor($time_diff / 60) . " 分鐘前";
                } elseif ($time_diff < 86400) {
                    $time_str = floor($time_diff / 3600) . " 小時前";
                } else {
                    $time_str = date("Y/m/d", strtotime($post["created_at"]));
                }

                $isFavorited = !empty($post["is_favorited"]);
                ?>

                <article class="post-card" id="post-card-<?= htmlspecialchars($post["id"]) ?>">

                    <div class="post-card-top">

                        <a 
                            href="forum_post.php?id=<?= htmlspecialchars($post["id"]) ?>" 
                            class="post-card-link"
                        >
                            <div class="post-card-title">
                                <?= htmlspecialchars($post["title"]) ?>
                            </div>
                        </a>

                        <button 
                            class="forum-bookmark-btn <?= $isFavorited ? 'saved' : '' ?>"
                            data-type="post"
                            data-id="<?= htmlspecialchars($post["id"]) ?>"
                            onclick="toggleFavorite(this)"
                            title="<?= $isFavorited ? '取消收藏' : '收藏貼文' ?>"
                        >
                            <?= $isFavorited ? '❤️ 已收藏' : '🤍 收藏' ?>
                        </button>

                    </div>

                    <a 
                        href="forum_post.php?id=<?= htmlspecialchars($post["id"]) ?>" 
                        class="post-card-link"
                    >
                        <div class="post-card-preview">
                            <?= htmlspecialchars($post["content"]) ?>
                        </div>

                        <div class="post-card-meta">
                            <span class="meta-item meta-author">
                                <i class="bi bi-person-circle"></i>
                                <?= htmlspecialchars($author) ?>
                            </span>

                            <span class="meta-item">
                                <i class="bi bi-clock"></i>
                                <?= htmlspecialchars($time_str) ?>
                            </span>

                            <span class="meta-item">
                                <i class="bi bi-chat-dots"></i>
                                <?= htmlspecialchars($post["comment_count"]) ?> 則留言
                            </span>
                        </div>
                    </a>

                    <div class="post-card-actions">
                        <a 
                            href="forum_post.php?id=<?= htmlspecialchars($post["id"]) ?>" 
                            class="post-read-btn"
                        >
                            查看貼文
                        </a>
                    </div>

                </article>

            <?php endforeach; ?>

        <?php endif; ?>

    </main>

</div>

<div id="favorite-toast"></div>

<script>
function toggleFavorite(btn) {
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

        if (data.favorited) {
            btn.classList.add("saved");
            btn.title = "取消收藏";
            btn.innerHTML = "❤️ 已收藏";
            showToast("已加入收藏");
        } else {
            btn.classList.remove("saved");
            btn.title = "收藏貼文";
            btn.innerHTML = "🤍 收藏";
            showToast("已取消收藏");
        }
    })
    .catch(err => {
        console.error(err);
        alert("收藏操作失敗");
    });
}

function showToast(msg) {
    const t = document.getElementById("favorite-toast");

    if (!t) return;

    t.textContent = msg;
    t.style.opacity = "1";

    clearTimeout(t._timer);

    t._timer = setTimeout(() => {
        t.style.opacity = "0";
    }, 1800);
}
</script>

<?php require "footer.php"; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>