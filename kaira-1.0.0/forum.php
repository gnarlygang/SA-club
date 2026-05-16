<?php
session_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? 0;
$search = trim($_GET['search'] ?? '');

require_once "api/db.php";

/*
role 對應
0 = 訪客
1 = teacher
2 = club
3 = student
4 = admin
*/

$current_user_id = $_SESSION['user_id'] ?? 0;
$current_role = (int)($_SESSION['role'] ?? 0);

$is_logged_in = !empty($_SESSION['user_id']);
$is_student = $is_logged_in && $current_role === 3;
$is_club = $is_logged_in && $current_role === 2;
$is_admin = $is_logged_in && $current_role === 4;
$is_visitor = !$is_logged_in;
$can_report = $is_student || $is_club;

// 只有學生跟訪客顯示收藏按鈕
$show_favorite_btn = $is_student || $is_visitor;

$search = trim($_GET['search'] ?? '');

try {
    $categories = $pdo->query("
        SELECT * FROM forum_categories ORDER BY sort_order ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $active_cat = isset($_GET["cat"])
        ? (int) $_GET["cat"]
        : ($categories[0]["id"] ?? 1);

    if ($search !== '') {
        // 搜尋模式：搜尋所有分類的文章標題、內容、留言，同時帶入收藏狀態
        $like = "%$search%";
        $stmt = $pdo->prepare("
            SELECT DISTINCT
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
            LEFT JOIN users u ON u.user_id = fp.user_id
            LEFT JOIN forum_comments fc2 ON fc2.post_id = fp.id
            LEFT JOIN favorites f
                ON f.item_id = fp.id
                AND f.item_type = 'post'
                AND f.user_id = :uid
            WHERE fp.title LIKE :s1
               OR fp.content LIKE :s2
               OR fc2.content LIKE :s3
            GROUP BY fp.id
            ORDER BY fp.created_at DESC
        ");
        $stmt->execute([
            ':s1' => $like,
            ':s2' => $like,
            ':s3' => $like,
            ':uid' => $current_user_id
        ]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $active_cat_name = "搜尋結果";

    } else {
        // 一般模式：依分類顯示，含收藏狀態
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
            LEFT JOIN users u ON u.user_id = fp.user_id
            LEFT JOIN favorites f
                ON f.item_id = fp.id
                AND f.item_type = 'post'
                AND f.user_id = :uid
            WHERE fp.category_id = :cat
            ORDER BY fp.created_at DESC
        ");
        $stmt->execute([
            ":cat" => $active_cat,
            ":uid" => $current_user_id
        ]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $active_cat_name = "";
        foreach ($categories as $c) {
            if ((int)$c["id"] === (int)$active_cat) {
                $active_cat_name = $c["name"];
                break;
            }
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
/* ── 搜尋框 ── */
.forum-search-wrap {
    padding: 1rem 1rem .5rem;
    border-bottom: 1px solid #e8e8ee;
}
.forum-search-label {
    font-size: .75rem;
    font-weight: 600;
    color: #8888aa;
    letter-spacing: .05em;
    margin-bottom: .5rem;
}
.forum-search-box {
    display: flex;
    align-items: center;
    gap: .5rem;
    background: #f5f5f7;
    border: 1px solid #e0e0e8;
    border-radius: 10px;
    padding: .55rem .9rem;
}
.forum-search-box i { 
    color: #8888aa; 
    font-size: .9rem; 
    flex-shrink: 0; 
}
.forum-search-box input {
    border: none; 
    outline: none; 
    background: transparent;
    font-size: .85rem; 
    width: 100%; 
    color: #333;
}
.forum-search-box input::placeholder { 
    color: #aaa; 
}
.forum-search-box button {
    border: none; 
    background: none; 
    cursor: pointer;
    color: #8888aa; 
    padding: 0; 
    font-size: .9rem;
    display: flex; 
    align-items: center; 
    flex-shrink: 0;
}
.forum-search-box button:hover { 
    color: #1a1a2e; 
}

/* ── 搜尋 badge / 清除 ── */
.search-badge {
    font-size: .82rem;
    color: #4a4a6a;
    margin-left: .4rem;
}
.clear-search {
    font-size: .78rem;
    color: #8888aa;
    text-decoration: none;
    margin-left: .4rem;
}
.clear-search:hover { 
    color: #c8502a; 
}

/* ── 貼文 card ── */
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

/* ── 收藏按鈕 ── */
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

/* ── 檢舉按鈕／管理員檢舉區（前端暫存） ── */
.forum-report-btn {
    flex-shrink: 0;
    border: 1px solid #f1d4d4;
    background: #fff7f7;
    color: #b45353;
    border-radius: 999px;
    padding: 7px 14px;
    font-size: 13px;
    cursor: pointer;
    transition: 0.2s ease;
    white-space: nowrap;
}
.forum-report-btn:hover { 
    border-color: #b91c1c; 
    background: #fee2e2; 
    color: #991b1b; 
}
.report-admin-panel { 
    margin-top: 18px; 
    padding: 14px; 
    border-top: 1px solid #e8e8ee; 
}
.report-admin-title { 
    font-size: 13px; 
    font-weight: 700; 
    color: #1e3a8a; 
    margin-bottom: 10px; 
    display: flex; 
    align-items: center; 
    gap: 6px; 
}
.report-admin-empty { 
    font-size: 12px; 
    color: #8892a6; 
    line-height: 1.6; 
}
.report-admin-item { 
    border: 1px solid #e5e7eb; 
    background: #f8fafc; 
    border-radius: 10px; 
    padding: 10px; 
    margin-bottom: 8px; 
    font-size: 12px; 
    color: #475569; 
    line-height: 1.55; 
}
.report-admin-item strong { 
    color: #0f172a; 
}
.report-admin-meta { 
    color: #94a3b8; 
    font-size: 11px; 
    margin-top: 4px; 
}
.report-clear-btn { 
    width: 100%; 
    border: 1px solid #cbd5e1; 
    background: #ffffff; 
    color: #475569; 
    border-radius: 8px; 
    padding: 7px 10px; 
    font-size: 12px; 
    cursor: pointer; 
    margin-top: 8px; 
}
.report-clear-btn:hover { 
    background: #f1f5f9; 
}

/* ── Toast ── */
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

            <!-- 搜尋框 -->
            <div class="forum-search-wrap">
                <div class="forum-search-label">搜尋文章</div>
                <form class="forum-search-box" method="GET" action="forum.php">
                    <i class="bi bi-search"></i>
                    <input 
                        type="text" 
                        name="search" 
                        placeholder="搜尋文章、留言…"
                        value="<?= htmlspecialchars($search) ?>"
                    >
                    <button type="submit">
                        <i class="bi bi-arrow-right-short" style="font-size:1.1rem"></i>
                    </button>
                </form>
            </div>

            <div class="sidebar-title">
                <i class="bi bi-journals me-2"></i>討論分類
            </div>

            <div class="sidebar-list">
                <?php foreach ($categories as $cat): ?>
                    <a 
                        href="forum.php?cat=<?= htmlspecialchars($cat["id"]) ?>"
                        class="sidebar-item <?= ((int)$cat["id"] === (int)$active_cat && $search === '') ? 'active' : '' ?>"
                    >
                        <?= htmlspecialchars($cat["name"]) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($is_admin): ?>
                <div class="report-admin-panel">
                    <div class="report-admin-title">
                        <i class="bi bi-flag-fill"></i> 檢舉內容管理
                    </div>
                    <div id="report-admin-list" class="report-admin-empty">目前沒有檢舉紀錄</div>
                    <button type="button" class="report-clear-btn" >查看所有檢舉紀錄</button>
                </div>
            <?php endif; ?>

        </div>
    </aside>

    <!-- 右側文章列表 -->
    <main class="forum-main">

        <div class="forum-header">
            <div>
                <span class="forum-title"><?= htmlspecialchars($active_cat_name) ?></span>

                <?php if ($search !== ''): ?>
                    <span class="search-badge">「<?= htmlspecialchars($search) ?>」</span>
                    <a href="forum.php?cat=<?= htmlspecialchars($active_cat) ?>" class="clear-search">
                        ✕ 清除
                    </a>
                <?php endif; ?>

                <span class="forum-count"><?= count($posts) ?> 篇文章</span>
            </div>

            <?php if (!empty($_SESSION["user_id"])): ?>
                <a href="forum_new.php?cat=<?= htmlspecialchars($active_cat) ?>" class="btn-new-post">
                    <i class="bi bi-pencil-square"></i>發表文章
                </a>
            <?php endif; ?>
        </div>

        <?php if (empty($_SESSION["user_id"])): ?>
            <div class="login-prompt">
                <a href="login.php">登入學生帳號</a> 後即可發表文章、留言與收藏貼文
            </div>
        <?php endif; ?>

        <?php if (empty($posts)): ?>

            <div class="empty-state">
                <i class="bi bi-chat-square-text"></i>
                <?= $search !== '' 
                    ? "找不到「" . htmlspecialchars($search) . "」相關文章" 
                    : "目前還沒有文章，成為第一個發表的人吧！" 
                ?>
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
                        <a href="forum_post.php?id=<?= htmlspecialchars($post["id"]) ?>" class="post-card-link">
                            <div class="post-card-title">
                                <?= htmlspecialchars($post["title"]) ?>
                            </div>
                        </a>

                        <?php if ($show_favorite_btn): ?>
                            <?php if ($is_student): ?>
                                <!-- 學生：可以正常收藏 -->
                                <button
                                    class="forum-bookmark-btn <?= $isFavorited ? 'saved' : '' ?>"
                                    data-type="post"
                                    data-id="<?= htmlspecialchars($post["id"]) ?>"
                                    onclick="toggleFavorite(this)"
                                    title="<?= $isFavorited ? '取消收藏' : '收藏貼文' ?>"
                                >
                                    <?= $isFavorited ? '❤️ 已收藏' : '🤍 收藏' ?>
                                </button>
                            <?php else: ?>
                                <!-- 訪客：看得到收藏，但點了會去登入頁 -->
                                <button
                                    class="forum-bookmark-btn"
                                    type="button"
                                    onclick="location.href='login.php'"
                                    title="登入後才能收藏"
                                >
                                    🤍 收藏
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>

                    </div>

                    <a href="forum_post.php?id=<?= htmlspecialchars($post["id"]) ?>" class="post-card-link">
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

                    <div class="post-card-actions" style="gap:8px; align-items:center;">

                        <a href="forum_post.php?id=<?= htmlspecialchars($post["id"]) ?>" class="post-read-btn">
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
    const itemId   = btn.dataset.id;
    const itemType = btn.dataset.type;

    fetch("api/toggle_favorite.php", {
        method: "POST",
        headers: { 
            "Content-Type": "application/x-www-form-urlencoded" 
        },
        body: "item_id=" + encodeURIComponent(itemId) + 
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

function reportForumItem(type, id, title) {
    const typeLabel = type === 'comment' ? '留言' : '貼文';
    const reason = prompt('請輸入檢舉原因：');
    if (reason === null) return;
    if (reason.trim() === '') { 
        alert('請輸入檢舉原因'); 
        return; 
    }

    const reports = JSON.parse(localStorage.getItem('forumReports') || '[]');
    reports.unshift({
        type, 
        typeLabel, 
        id, 
        title,
        reason: reason.trim(),
        reporterRole: <?= json_encode($current_role) ?>,
        createdAt: new Date().toLocaleString('zh-TW')
    });

    localStorage.setItem('forumReports', JSON.stringify(reports));
    showToast('已送出檢舉（前端暫存）');
    renderForumReports();
}

function renderForumReports() {
    const list = document.getElementById('report-admin-list');
    if (!list) return;

    const reports = JSON.parse(localStorage.getItem('forumReports') || '[]');

    if (reports.length === 0) {
        list.className = 'report-admin-empty';
        list.innerHTML = '目前沒有檢舉紀錄';
        return;
    }

    list.className = '';
    list.innerHTML = reports.map(r => `
        <div class="report-admin-item">
            <strong>${escapeHtml(r.typeLabel || r.type)} #${escapeHtml(r.id)}</strong><br>
            ${escapeHtml(r.title || '未命名內容')}<br>
            檢舉原因：${escapeHtml(r.reason || '')}
            <div class="report-admin-meta">${escapeHtml(r.createdAt || '')}</div>
        </div>
    `).join('');
}

function clearForumReports() {
    if (!confirm('確定要清空目前瀏覽器暫存的檢舉紀錄嗎？')) return;

    localStorage.removeItem('forumReports');
    renderForumReports();
    showToast('已清空前端檢舉紀錄');
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

document.addEventListener('DOMContentLoaded', renderForumReports);

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