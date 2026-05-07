<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? 0;

require_once "api/db.php";

$search = trim($_GET['search'] ?? '');

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $categories = $pdo->query("SELECT * FROM forum_categories ORDER BY sort_order ASC")
                      ->fetchAll(PDO::FETCH_ASSOC);

    $active_cat = isset($_GET["cat"]) ? (int)$_GET["cat"] : ($categories[0]["id"] ?? 1);

    if ($search !== '') {
        // 搜尋模式：搜尋所有分類的文章標題、內容、留言
        $stmt = $pdo->prepare("
            SELECT DISTINCT fp.*,
                   u.username,
                   u.nickname,
                   COUNT(DISTINCT fc.id) AS comment_count
            FROM forum_posts fp
            LEFT JOIN users u ON u.user_id = fp.user_id
            LEFT JOIN forum_comments fc ON fc.post_id = fp.id
            WHERE fp.title LIKE :s1
               OR fp.content LIKE :s2
               OR fc.content LIKE :s3
            GROUP BY fp.id
            ORDER BY fp.created_at DESC
        ");
        $like = "%$search%";
        $stmt->execute([':s1' => $like, ':s2' => $like, ':s3' => $like]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $active_cat_name = "搜尋結果";
    } else {
        // 一般模式：依分類顯示
        $stmt = $pdo->prepare("
            SELECT fp.*,
                   u.username,
                   u.nickname,
                   COUNT(fc.id) AS comment_count
            FROM forum_posts fp
            LEFT JOIN users u ON u.user_id = fp.user_id
            LEFT JOIN forum_comments fc ON fc.post_id = fp.id
            WHERE fp.category_id = :cat
            GROUP BY fp.id
            ORDER BY fp.created_at DESC
        ");
        $stmt->execute([":cat" => $active_cat]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $active_cat_name = "";
        foreach ($categories as $c) {
            if ($c["id"] == $active_cat) {
                $active_cat_name = $c["name"];
                break;
            }
        }
    }

} catch (PDOException $e) {
    die("資料庫連線失敗：" . $e->getMessage());
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
.forum-search-box i { color: #8888aa; font-size: .9rem; flex-shrink: 0; }
.forum-search-box input {
    border: none; outline: none; background: transparent;
    font-size: .85rem; width: 100%; color: #333;
}
.forum-search-box input::placeholder { color: #aaa; }
.forum-search-box button {
    border: none; background: none; cursor: pointer;
    color: #8888aa; padding: 0; font-size: .9rem;
    display: flex; align-items: center; flex-shrink: 0;
}
.forum-search-box button:hover { color: #1a1a2e; }
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
          <input type="text" name="search" placeholder="搜尋文章、留言…"
                 value="<?= htmlspecialchars($search) ?>">
          <button type="submit"><i class="bi bi-arrow-right-short" style="font-size:1.1rem"></i></button>
        </form>
      </div>

      <div class="sidebar-title"><i class="bi bi-journals me-2"></i>討論分類</div>
      <div class="sidebar-list">
        <?php foreach ($categories as $cat): ?>
          <a href="forum.php?cat=<?= $cat["id"] ?>"
             class="sidebar-item <?= ($cat["id"] == $active_cat && $search === '') ? 'active' : '' ?>">
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
        <span class="forum-title"><?= htmlspecialchars($active_cat_name) ?></span>
        <?php if ($search !== ''): ?>
          <span class="search-badge">「<?= htmlspecialchars($search) ?>」</span>
          <a href="forum.php?cat=<?= $active_cat ?>" class="clear-search">✕ 清除</a>
        <?php endif; ?>
        <span class="forum-count"><?= count($posts) ?> 篇文章</span>
      </div>
      <?php if (!empty($_SESSION["user_id"])): ?>
        <a href="forum_new.php?cat=<?= $active_cat ?>" class="btn-new-post">
          <i class="bi bi-pencil-square"></i>發表文章
        </a>
      <?php endif; ?>
    </div>

    <?php if (empty($_SESSION["user_id"])): ?>
      <div class="login-prompt">
        <a href="login.php">登入</a> 後即可發表文章與留言
      </div>
    <?php endif; ?>

    <?php if (empty($posts)): ?>
      <div class="empty-state">
        <i class="bi bi-chat-square-text"></i>
        <?= $search !== '' ? "找不到「{$search}」相關文章" : "目前還沒有文章，成為第一個發表的人吧！" ?>
      </div>
    <?php else: ?>
      <?php foreach ($posts as $post):
        $author = $post["nickname"] ?: $post["username"];
        $time_diff = time() - strtotime($post["created_at"]);
        if ($time_diff < 3600) {
            $time_str = floor($time_diff / 60) . " 分鐘前";
        } elseif ($time_diff < 86400) {
            $time_str = floor($time_diff / 3600) . " 小時前";
        } else {
            $time_str = date("Y/m/d", strtotime($post["created_at"]));
        }
      ?>
        <a href="forum_post.php?id=<?= $post["id"] ?>" class="post-card">
          <div class="post-card-title"><?= htmlspecialchars($post["title"]) ?></div>
          <div class="post-card-preview"><?= htmlspecialchars($post["content"]) ?></div>
          <div class="post-card-meta">
            <span class="meta-item meta-author">
              <i class="bi bi-person-circle"></i>
              <?= htmlspecialchars($author) ?>
            </span>
            <span class="meta-item">
              <i class="bi bi-clock"></i>
              <?= $time_str ?>
            </span>
            <span class="meta-item">
              <i class="bi bi-chat-dots"></i>
              <?= $post["comment_count"] ?> 則留言
            </span>
          </div>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>

  </main>
</div>
<?php require "footer.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>