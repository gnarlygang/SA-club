<?php
session_start();
$_SESSION['role'] = 0; // 強制把當前狀態改為訪客
$role = 0;

require_once "api/db.php";

try {
  
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 取得所有分類
    $categories = $pdo->query("SELECT * FROM forum_categories ORDER BY sort_order ASC")
                      ->fetchAll(PDO::FETCH_ASSOC);

    // 目前選擇的分類（預設第一個）
    $active_cat = isset($_GET["cat"]) ? (int)$_GET["cat"] : ($categories[0]["id"] ?? 1);

    // 取得該分類的文章（含留言數、發文者）
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

    // 目前分類名稱
    $active_cat_name = "";
    foreach ($categories as $c) {
        if ($c["id"] == $active_cat) {
            $active_cat_name = $c["name"];
            break;
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


</head>
<body>

<div class="forum-layout">

  <!-- 左側分類 -->
  <aside class="forum-sidebar">
    <div class="sidebar-card">
      <div class="sidebar-title"><i class="bi bi-journals me-2"></i>討論分類</div>
      <div class="sidebar-list">
        <?php foreach ($categories as $cat): ?>
          <a href="forum.php?cat=<?= $cat["id"] ?>"
             class="sidebar-item <?= $cat["id"] == $active_cat ? 'active' : '' ?>">
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
        目前還沒有文章，成為第一個發表的人吧！
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
