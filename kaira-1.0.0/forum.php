<?php
session_start();
if (session_status() === PHP_SESSION_NONE) session_start();

$role   = $_SESSION['role'] ?? 0;
$search = trim($_GET['search'] ?? '');

require_once "api/db.php";

$current_user_id = $_SESSION['user_id'] ?? 0;
$current_role    = (int)($_SESSION['role'] ?? 0);
$is_logged_in    = !empty($_SESSION['user_id']);
$is_student      = $is_logged_in && $current_role === 3;
$is_club         = $is_logged_in && $current_role === 2;
$is_admin        = $is_logged_in && $current_role === 4;
$is_visitor      = !$is_logged_in;
$can_report      = $is_student || $is_club;
$show_fav_btn    = $is_student || $is_visitor;

try {
    $categories = $pdo->query("SELECT * FROM forum_categories ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
    $active_cat  = isset($_GET["cat"]) ? (int)$_GET["cat"] : ($categories[0]["id"] ?? 1);

    if ($search !== '') {
        $like = "%$search%";
        $stmt = $pdo->prepare("
            SELECT DISTINCT fp.*, u.username, u.nickname,
                (SELECT COUNT(*) FROM forum_comments fc WHERE fc.post_id=fp.id AND fc.is_deleted=0) AS comment_count,
                CASE WHEN f.id IS NOT NULL THEN 1 ELSE 0 END AS is_favorited
            FROM forum_posts fp
            LEFT JOIN users u ON u.user_id=fp.user_id
            LEFT JOIN forum_comments fc2 ON fc2.post_id=fp.id
            LEFT JOIN favorites f ON f.item_id=fp.id AND f.item_type='post' AND f.user_id=:uid
            WHERE fp.is_deleted=0 AND (fp.title LIKE :s1 OR fp.content LIKE :s2 OR fc2.content LIKE :s3)
            GROUP BY fp.id ORDER BY fp.created_at DESC
        ");
        $stmt->execute([':s1'=>$like,':s2'=>$like,':s3'=>$like,':uid'=>$current_user_id]);
        $active_cat_name = "搜尋結果";
    } else {
        $stmt = $pdo->prepare("
            SELECT fp.*, u.username, u.nickname,
                (SELECT COUNT(*) FROM forum_comments fc WHERE fc.post_id=fp.id AND fc.is_deleted=0) AS comment_count,
                CASE WHEN f.id IS NOT NULL THEN 1 ELSE 0 END AS is_favorited
            FROM forum_posts fp
            LEFT JOIN users u ON u.user_id=fp.user_id
            LEFT JOIN favorites f ON f.item_id=fp.id AND f.item_type='post' AND f.user_id=:uid
            WHERE fp.category_id=:cat AND fp.is_deleted=0
            ORDER BY fp.created_at DESC
        ");
        $stmt->execute([":cat"=>$active_cat,":uid"=>$current_user_id]);
        $active_cat_name = "";
        foreach ($categories as $c) {
            if ((int)$c["id"]===(int)$active_cat) { $active_cat_name=$c["name"]; break; }
        }
    }
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 管理員統計
    $pending_count   = 0;
    $total_posts     = 0;
    $total_comments  = 0;
    $total_reports   = 0;
    if ($is_admin) {
        $pending_count  = (int)$pdo->query("SELECT COUNT(*) FROM forum_reports WHERE status='pending'")->fetchColumn();
        $total_posts    = (int)$pdo->query("SELECT COUNT(*) FROM forum_posts    WHERE is_deleted=0")->fetchColumn();
        $total_comments = (int)$pdo->query("SELECT COUNT(*) FROM forum_comments WHERE is_deleted=0")->fetchColumn();
        $total_reports  = (int)$pdo->query("SELECT COUNT(*) FROM forum_reports")->fetchColumn();
        // 近 7 天新增文章
        $new_posts_week = (int)$pdo->query("SELECT COUNT(*) FROM forum_posts WHERE is_deleted=0 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    }

} catch (PDOException $e) { die("資料庫錯誤：".$e->getMessage()); }

require_once "header.php";
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>社團論壇 — 輔大社團平台</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="css/forum.css">
<style>
/* ════════════════════════════════════════
   管理員頂部橫幅
════════════════════════════════════════ */
.admin-top-bar {
    background: linear-gradient(135deg, #1e2d4a 0%, #2d4a7a 100%);
    color: #fff;
    padding: 0 0 20px;
    margin-bottom: 0;
}
.admin-top-inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: 18px 24px 0;
}
.admin-top-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    font-weight: 700;
    color: #93c5fd;
    letter-spacing: .06em;
    margin-bottom: 14px;
}
.admin-stat-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 16px;
}
.admin-stat-card {
    background: rgba(255,255,255,.1);
    border: 1px solid rgba(255,255,255,.15);
    border-radius: 12px;
    padding: 14px 16px;
    display: flex;
    flex-direction: column;
    gap: 2px;
    transition: background .2s;
}
.admin-stat-card:hover { background: rgba(255,255,255,.16); }
.admin-stat-card.danger {
    background: rgba(185,28,28,.25);
    border-color: rgba(252,165,165,.35);
}
.admin-stat-num {
    font-size: 26px;
    font-weight: 800;
    color: #fff;
    line-height: 1;
}
.admin-stat-card.danger .admin-stat-num { color: #fca5a5; }
.admin-stat-lbl {
    font-size: 11px;
    color: #93c5fd;
    font-weight: 600;
    letter-spacing: .04em;
}
.admin-quick-btns {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.admin-quick-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.2);
    color: #fff;
    border-radius: 999px;
    padding: 8px 18px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: .2s;
}
.admin-quick-btn:hover { background: rgba(255,255,255,.22); color: #fff; }
.admin-quick-btn.alert-btn {
    background: rgba(185,28,28,.5);
    border-color: rgba(252,165,165,.4);
}
.admin-quick-btn.alert-btn:hover { background: rgba(185,28,28,.7); }
.admin-quick-badge {
    background: #ef4444;
    color: #fff;
    border-radius: 999px;
    padding: 1px 7px;
    font-size: 10px;
    font-weight: 800;
}

/* 管理員模式提示條 */
.admin-mode-bar {
    background: #1e2d4a;
    color: #93c5fd;
    font-size: 12px;
    font-weight: 700;
    text-align: center;
    padding: 5px;
    letter-spacing: .08em;
}

/* ════════════════════════════════════════
   搜尋框
════════════════════════════════════════ */
.forum-search-wrap{padding:1rem 1rem .5rem;border-bottom:1px solid #e8e8ee}
.forum-search-label{font-size:.75rem;font-weight:600;color:#8888aa;letter-spacing:.05em;margin-bottom:.5rem}
.forum-search-box{display:flex;align-items:center;gap:.5rem;background:#f5f5f7;border:1px solid #e0e0e8;border-radius:10px;padding:.55rem .9rem}
.forum-search-box i{color:#8888aa;font-size:.9rem;flex-shrink:0}
.forum-search-box input{border:none;outline:none;background:transparent;font-size:.85rem;width:100%;color:#333}
.forum-search-box input::placeholder{color:#aaa}
.forum-search-box button{border:none;background:none;cursor:pointer;color:#8888aa;padding:0;font-size:.9rem;display:flex;align-items:center;flex-shrink:0}
.forum-search-box button:hover{color:#1a1a2e}
.search-badge{font-size:.82rem;color:#4a4a6a;margin-left:.4rem}
.clear-search{font-size:.78rem;color:#8888aa;text-decoration:none;margin-left:.4rem}
.clear-search:hover{color:#c8502a}

/* ════════════════════════════════════════
   管理員側邊欄快捷區（仍保留小卡，但簡化）
════════════════════════════════════════ */
.admin-sidebar-section {
    padding: 12px;
    border-top: 1px solid #e8e8ee;
}
.sidebar-section-label {
    font-size: 11px;
    font-weight: 700;
    color: #8892a6;
    letter-spacing: .06em;
    margin-bottom: 8px;
    padding-left: 2px;
}
.admin-mini-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    background: #eff6ff;
    border: 1px solid #dbe4f0;
    border-radius: 10px;
    color: #1e3a8a;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    transition: .2s;
    margin-bottom: 8px;
}
.admin-mini-btn:hover { background: #dbeafe; color: #1e3a8a; }
.admin-mini-badge {
    margin-left: auto;
    background: #b91c1c;
    color: #fff;
    border-radius: 999px;
    padding: 2px 8px;
    font-size: 11px;
    font-weight: 800;
}

/* ════════════════════════════════════════
   貼文卡
════════════════════════════════════════ */
.post-card{position:relative;transition:box-shadow .18s}
.post-card:hover{box-shadow:0 4px 20px rgba(30,58,138,.1)}
.post-card-link{display:block;color:inherit;text-decoration:none}
.post-card-link:hover{color:inherit}
.post-card-top{display:flex;justify-content:space-between;align-items:flex-start;gap:16px}
.post-card-title{font-size:16px;font-weight:700;color:#1a2535;line-height:1.4;margin-bottom:6px}

/* 管理員專屬：文章卡右側快捷刪除 */
.admin-post-actions {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
    align-items: flex-start;
}
.admin-del-btn {
    border: 1px solid #f1d4d4;
    background: #fff7f7;
    color: #b45353;
    border-radius: 999px;
    padding: 5px 12px;
    font-size: 12px;
    cursor: pointer;
    white-space: nowrap;
    transition: .18s;
}
.admin-del-btn:hover { background: #fee2e2; color: #991b1b; }

/* 收藏 */
.forum-bookmark-btn{flex-shrink:0;border:1px solid #e5e7eb;background:#fff;color:#64748b;border-radius:999px;padding:6px 12px;font-size:13px;cursor:pointer;transition:.2s;white-space:nowrap}
.forum-bookmark-btn:hover{border-color:#1e3a8a;color:#1e3a8a;background:#eff6ff}
.forum-bookmark-btn.saved{background:#1e3a8a;border-color:#1e3a8a;color:#fff}
.post-card-actions{margin-top:12px;display:flex;justify-content:flex-end;gap:8px;align-items:center}
.post-read-btn{display:inline-block;background:#0f2a44;color:#fff;padding:7px 14px;border-radius:999px;font-size:13px;text-decoration:none}
.post-read-btn:hover{background:#1e3a8a;color:#fff}

/* 空狀態 */
.empty-state{text-align:center;padding:60px 20px;color:#aab;font-size:15px}
.empty-state i{font-size:40px;display:block;margin-bottom:12px;color:#c8d4e8}

/* Toast */
#favorite-toast{position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;background:#1a1a2e;color:#fff;padding:.55rem 1.1rem;border-radius:8px;font-size:.8rem;font-weight:500;box-shadow:0 4px 16px rgba(0,0,0,.2);transition:opacity .3s;opacity:0;pointer-events:none}

/* 管理員快速刪除確認 Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9990;align-items:center;justify-content:center}
.modal-overlay.active{display:flex}
.modal-box{background:#fff;border-radius:16px;padding:28px 32px;width:100%;max-width:420px;box-shadow:0 8px 32px rgba(0,0,0,.18);position:relative}
.modal-title{font-size:17px;font-weight:700;color:#1a2535;margin-bottom:10px}
.modal-body{font-size:14px;color:#445;line-height:1.6;margin-bottom:20px}
.modal-acts{display:flex;gap:10px;justify-content:flex-end}
.btn-mc{border:1px solid #d0d8e4;background:#f5f7fa;color:#667;border-radius:8px;padding:9px 18px;font-size:13px;cursor:pointer}
.btn-danger{border:none;background:#b91c1c;color:#fff;border-radius:8px;padding:9px 18px;font-size:13px;font-weight:700;cursor:pointer}
.btn-danger:hover{background:#991b1b}

@media(max-width:768px){
    .admin-stat-cards{grid-template-columns:repeat(2,1fr)}
    .admin-quick-btns{gap:8px}
}
/* ── Footer 修正 ── */
footer {
    background-color: #1a2744 !important;
    color: #cdd5e0 !important;
    width: 100%;
    margin-top: auto;
}
footer .footer-top {
    display: flex;
    justify-content: space-around;
    flex-wrap: wrap;
    gap: 24px;
    padding: 40px 48px 28px;
}
footer .foot-col h5 {
    color: #ffffff !important;
    font-size: 14px;
    font-weight: 700;
    margin-bottom: 12px;
    letter-spacing: .04em;
}
footer .foot-col ul {
    list-style: none;
    padding: 0;
    margin: 0;
}
footer .foot-col ul li {
    margin-bottom: 8px;
}
footer .foot-col a {
    color: #94a3b8 !important;
    text-decoration: none;
    font-size: 13px;
}
footer .foot-col a:hover {
    color: #ffffff !important;
}
footer .footer-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    padding: 18px 48px;
    border-top: 1px solid rgba(255,255,255,.1);
}
footer .footer-brand {
    display: flex;
    align-items: center;
    gap: 14px;
}
footer .footer-logo-box {
    background: #2d4a7a;
    color: #fff;
    font-weight: 800;
    font-size: 14px;
    border-radius: 10px;
    padding: 10px 14px;
    letter-spacing: .06em;
}
footer .footer-brand-info {
    display: flex;
    flex-direction: column;
    gap: 3px;
}
footer .footer-brand-info strong {
    color: #ffffff !important;
    font-size: 14px;
}
footer .footer-brand-info span {
    color: #94a3b8 !important;
    font-size: 12px;
}
footer .footer-contact {
    font-size: 13px;
    color: #94a3b8 !important;
    line-height: 1.8;
    text-align: center;
}
footer .footer-copy {
    font-size: 12px;
    color: #64748b !important;
}
</style>
</head>
<body>

<?php if ($is_admin): ?>
<!-- ══ 管理員頂部橫幅 ══ -->
<div class="admin-top-bar">
    <div class="admin-top-inner">
        <div class="admin-top-title">
            <i class="bi bi-shield-fill-check"></i>
            管理員模式 — 論壇管理中心
        </div>

        <!-- 統計卡 -->
        <div class="admin-stat-cards">
            <div class="admin-stat-card">
                <div class="admin-stat-num"><?= $total_posts ?></div>
                <div class="admin-stat-lbl"><i class="bi bi-file-text me-1"></i>文章總數</div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-num"><?= $total_comments ?></div>
                <div class="admin-stat-lbl"><i class="bi bi-chat-dots me-1"></i>留言總數</div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-num"><?= $new_posts_week ?></div>
                <div class="admin-stat-lbl"><i class="bi bi-graph-up me-1"></i>本週新增文章</div>
            </div>
            <div class="admin-stat-card <?= $pending_count > 0 ? 'danger' : '' ?>">
                <div class="admin-stat-num"><?= $pending_count ?></div>
                <div class="admin-stat-lbl"><i class="bi bi-flag me-1"></i>待審檢舉</div>
            </div>
        </div>

        <!-- 快捷按鈕 -->
        <div class="admin-quick-btns">
            <a href="forum_admin.php" class="admin-quick-btn <?= $pending_count > 0 ? 'alert-btn' : '' ?>">
                <i class="bi bi-shield-check"></i>
                檢舉審核管理
                <?php if ($pending_count > 0): ?>
                    <span class="admin-quick-badge"><?= $pending_count ?> 待審</span>
                <?php endif; ?>
            </a>
            <a href="forum_admin.php?tab=resolved" class="admin-quick-btn">
                <i class="bi bi-check2-circle"></i> 已處理紀錄
            </a>
            <a href="forum_admin.php?tab=dismissed" class="admin-quick-btn">
                <i class="bi bi-x-circle"></i> 已駁回紀錄
            </a>
        </div>
    </div>
</div>
<div class="admin-mode-bar">
    ⚡ 你正以管理員身份瀏覽論壇，可直接在文章卡片上進行管理操作
</div>
<?php endif; ?>

<div class="forum-layout">

    <!-- ══ 左側 ══ -->
    <aside class="forum-sidebar">
        <div class="sidebar-card">

            <!-- 搜尋 -->
            <div class="forum-search-wrap">
                <div class="forum-search-label">搜尋文章</div>
                <form class="forum-search-box" method="GET" action="forum.php">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" placeholder="搜尋文章、留言…" value="<?= htmlspecialchars($search) ?>">
                    <button type="submit"><i class="bi bi-arrow-right-short" style="font-size:1.1rem"></i></button>
                </form>
            </div>

            <!-- 分類 -->
            <div class="sidebar-title"><i class="bi bi-journals me-2"></i>討論分類</div>
            <div class="sidebar-list">
                <?php foreach ($categories as $cat): ?>
                <a href="forum.php?cat=<?= $cat["id"] ?>"
                   class="sidebar-item <?= ((int)$cat["id"]===(int)$active_cat && $search==='') ? 'active' : '' ?>">
                    <?= htmlspecialchars($cat["name"]) ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- 管理員側邊欄快捷（精簡版，因頂部已有完整面板） -->
            <?php if ($is_admin): ?>
            <div class="admin-sidebar-section">
                <div class="sidebar-section-label">管理員快捷</div>
                <a href="forum_admin.php" class="admin-mini-btn">
                    <i class="bi bi-shield-check"></i>
                    檢舉審核
                    <?php if ($pending_count > 0): ?>
                        <span class="admin-mini-badge"><?= $pending_count ?></span>
                    <?php endif; ?>
                </a>
            </div>
            <?php endif; ?>

        </div>
    </aside>

    <!-- ══ 右側文章列表 ══ -->
    <main class="forum-main">

        <div class="forum-header">
            <div>
                <span class="forum-title"><?= htmlspecialchars($active_cat_name) ?></span>
                <?php if ($search !== ''): ?>
                    <span class="search-badge">「<?= htmlspecialchars($search) ?>」</span>
                    <a href="forum.php?cat=<?= $active_cat ?>" class="clear-search">✕ 清除</a>
                <?php endif; ?>
                <span class="forum-count"><?= count($posts) ?> 篇文章</span>
            </div>
            <!-- 管理員不需要發文按鈕 -->
            <?php if ($is_logged_in && !$is_admin): ?>
            <a href="forum_new.php?cat=<?= $active_cat ?>" class="btn-new-post">
                <i class="bi bi-pencil-square"></i>發表文章
            </a>
            <?php endif; ?>
        </div>

        <!-- 管理員提示 -->
        <?php if ($is_admin && !empty($posts)): ?>
        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:10px 16px;font-size:13px;color:#1e40af;margin-bottom:16px;display:flex;align-items:center;gap:8px">
            <i class="bi bi-info-circle-fill"></i>
            管理員視角：每篇文章右側有「快速刪除」按鈕。點「查看貼文」可進入文章頁面進行編輯或管理留言。
        </div>
        <?php endif; ?>

        <?php if ($is_visitor): ?>
        <div class="login-prompt">
            <a href="login.php">登入</a> 後即可發表文章、留言與收藏貼文
        </div>
        <?php endif; ?>

        <?php if (empty($posts)): ?>
        <div class="empty-state">
            <i class="bi bi-chat-square-text"></i>
            <?= $search!=='' ? "找不到「".htmlspecialchars($search)."」相關文章" : "目前還沒有文章，成為第一個發表的人吧！" ?>
        </div>
        <?php else: ?>

        <?php foreach ($posts as $post):
            $author   = $post["nickname"] ?: ($post["username"] ?? "匿名使用者");
            $diff     = time()-strtotime($post["created_at"]);
            $time_str = $diff<60 ? "剛剛" : ($diff<3600 ? floor($diff/60)." 分鐘前" : ($diff<86400 ? floor($diff/3600)." 小時前" : date("Y/m/d",strtotime($post["created_at"]))));
            $isFav    = !empty($post["is_favorited"]);
            $isNew    = $diff < 86400; // 24小時內算新文章
        ?>
        <article class="post-card" id="post-card-<?= $post["id"] ?>">
            <div class="post-card-top">
                <a href="forum_post.php?id=<?= $post["id"] ?>" class="post-card-link" style="flex:1;min-width:0">
                    <div class="post-card-title">
                        <?php if ($isNew): ?>
                            <span style="display:inline-block;background:#dcfce7;color:#166534;font-size:10px;font-weight:800;border-radius:4px;padding:1px 6px;margin-right:6px;vertical-align:middle">NEW</span>
                        <?php endif; ?>
                        <?= htmlspecialchars($post["title"]) ?>
                    </div>
                </a>

                <!-- 管理員：快速刪除 -->
                <?php if ($is_admin): ?>
                <div class="admin-post-actions">
                    <button type="button" class="admin-del-btn"
                        onclick="adminDeletePost(<?= $post['id'] ?>, '<?= htmlspecialchars($post['title'], ENT_QUOTES) ?>')">
                        <i class="bi bi-trash3"></i> 刪除
                    </button>
                </div>
                <?php endif; ?>

                <!-- 學生/訪客：收藏 -->
                <?php if ($show_fav_btn): ?>
                    <?php if ($is_student): ?>
                    <button class="forum-bookmark-btn <?= $isFav?'saved':'' ?>"
                        data-type="post" data-id="<?= $post["id"] ?>"
                        onclick="toggleFavorite(this)"
                        title="<?= $isFav?'取消收藏':'收藏貼文' ?>">
                        <?= $isFav?'❤️ 已收藏':'🤍 收藏' ?>
                    </button>
                    <?php else: ?>
                    <button class="forum-bookmark-btn" onclick="location.href='login.php'" title="登入後才能收藏">
                        🤍 收藏
                    </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <a href="forum_post.php?id=<?= $post["id"] ?>" class="post-card-link">
                <div class="post-card-preview"><?= htmlspecialchars($post["content"]) ?></div>
                <div class="post-card-meta">
                    <span class="meta-item meta-author"><i class="bi bi-person-circle"></i><?= htmlspecialchars($author) ?></span>
                    <span class="meta-item"><i class="bi bi-clock"></i><?= $time_str ?></span>
                    <span class="meta-item"><i class="bi bi-chat-dots"></i><?= $post["comment_count"] ?> 則留言</span>
                    <?php if (!empty($post['updated_at'])): ?>
                    <span class="meta-item" style="color:#aab;font-size:11px"><i class="bi bi-pencil"></i>已編輯</span>
                    <?php endif; ?>
                </div>
            </a>

            <div class="post-card-actions">
                <?php if ($is_admin): ?>
                <!-- 管理員：直接進入文章管理 -->
                <a href="forum_post.php?id=<?= $post["id"] ?>" class="post-read-btn" style="background:#1e3a8a">
                    <i class="bi bi-shield-check me-1"></i>管理此文章
                </a>
                <?php else: ?>
                <a href="forum_post.php?id=<?= $post["id"] ?>" class="post-read-btn">查看貼文</a>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
        <?php endif; ?>

    </main>
</div><!-- /forum-layout -->

<div id="favorite-toast"></div>

<!-- 管理員快速刪除確認 Modal -->
<?php if ($is_admin): ?>
<div class="modal-overlay" id="admin-del-modal">
    <div class="modal-box">
        <div class="modal-title"><i class="bi bi-trash3 me-2" style="color:#b91c1c"></i>確認刪除文章</div>
        <div class="modal-body" id="admin-del-body"></div>
        <form method="POST" action="forum_post.php?_admin_del=1" id="admin-del-form">
            <input type="hidden" name="action"  value="delete_post">
            <input type="hidden" name="post_id" id="admin-del-pid">
            <div class="modal-acts">
                <button type="button" class="btn-mc" onclick="closeModal('admin-del-modal')">取消</button>
                <button type="submit" class="btn-danger"><i class="bi bi-trash3 me-1"></i>確認刪除</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// ── 收藏 ──────────────────────────────────────────────────────
function toggleFavorite(btn) {
    fetch("api/toggle_favorite.php", {
        method:"POST",
        headers:{"Content-Type":"application/x-www-form-urlencoded"},
        body:"item_id="+encodeURIComponent(btn.dataset.id)+"&item_type="+encodeURIComponent(btn.dataset.type)
    })
    .then(r=>r.json())
    .then(data=>{
        if (!data.success){alert(data.message);return}
        if (data.favorited){
            btn.classList.add("saved");btn.title="取消收藏";btn.innerHTML="❤️ 已收藏";showToast("已加入收藏");
        } else {
            btn.classList.remove("saved");btn.title="收藏貼文";btn.innerHTML="🤍 收藏";showToast("已取消收藏");
        }
    })
    .catch(()=>alert("收藏操作失敗"));
}

// ── 管理員快速刪除 ────────────────────────────────────────────
function adminDeletePost(pid, title) {
    document.getElementById('admin-del-pid').value = pid;
    document.getElementById('admin-del-body').innerHTML =
        '確定要刪除以下文章嗎？<br>' +
        '<div style="margin-top:10px;padding:10px 14px;background:#fef2f2;border-radius:8px;font-size:13px;color:#7f1d1d;font-weight:600">' +
        title + '</div>' +
        '<div style="margin-top:8px;font-size:12px;color:#b91c1c">⚠ 刪除後無法復原。</div>';
    // 讓 form POST 到 forum_post.php?id=pid
    document.getElementById('admin-del-form').action = 'forum_post.php?id=' + pid;
    openModal('admin-del-modal');
}

// ── Modal ─────────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', e => { if (e.target===el) closeModal(el.id); });
});

// ── Toast ─────────────────────────────────────────────────────
function showToast(msg){
    const t=document.getElementById("favorite-toast");
    t.textContent=msg;t.style.opacity="1";
    clearTimeout(t._t);t._t=setTimeout(()=>{t.style.opacity="0"},1800);
}
</script>

<?php require "footer.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>