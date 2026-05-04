<?php
session_start();

$host    = "localhost";
$dbname  = "sa2026";
$db_user = "root";
$db_pass = "";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $db_user,
        $db_pass,
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

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;600;700&family=Microsoft+JhengHei&display=swap" rel="stylesheet">

  <style>
    :root {
      --sidebar-bg: #2d3a4a;
      --sidebar-active: #1a2535;
      --sidebar-hover: #374a5e;
      --footer-bg: #afbac7;
      --card-shadow: 0 2px 12px rgba(60,80,120,0.08);
      --accent: #4a6080;
      --border: #e8ecf0;
    }

    * { box-sizing: border-box; }

    body {
      font-family: "Microsoft JhengHei", "微軟正黑體", sans-serif;
      background: #f0f2f5;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ── Layout ── */
    .forum-layout {
      flex: 1;
      display: flex;
      max-width: 1200px;
      margin: 0 auto;
      width: 100%;
      padding: 32px 16px 60px;
      gap: 24px;
    }

    /* ── Sidebar ── */
    .forum-sidebar {
      width: 220px;
      flex-shrink: 0;
    }

    .sidebar-card {
      background: #fff;
      border-radius: 14px;
      box-shadow: var(--card-shadow);
      overflow: hidden;
      position: sticky;
      top: 24px;
    }

    .sidebar-title {
      background: var(--sidebar-bg);
      color: #fff;
      padding: 16px 20px;
      font-size: 14px;
      font-weight: 700;
      letter-spacing: 1px;
    }

    .sidebar-item {
      display: block;
      padding: 15px 20px;
      font-size: 14px;
      color: #445;
      text-decoration: none;
      border-bottom: 1px solid var(--border);
      transition: background 0.15s, color 0.15s;
      font-weight: 500;
    }

    .sidebar-item:last-child { border-bottom: none; }

    .sidebar-item:hover {
      background: #f4f6f9;
      color: var(--accent);
    }

    .sidebar-item.active {
      background: var(--sidebar-bg);
      color: #fff;
      font-weight: 700;
    }

    /* ── Main content ── */
    .forum-main { flex: 1; min-width: 0; }

    .forum-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
    }

    .forum-title {
      font-size: 22px;
      font-weight: 700;
      color: #2d3a4a;
    }

    .forum-count {
      font-size: 13px;
      color: #9aa;
      margin-left: 8px;
      font-weight: 400;
    }

    .btn-new-post {
      background: #2d3a4a;
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 9px 18px;
      font-size: 13px;
      font-weight: 600;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: background 0.2s, transform 0.1s;
    }

    .btn-new-post:hover {
      background: #3d4e62;
      color: #fff;
      transform: translateY(-1px);
    }

    /* ── Post card ── */
    .post-card {
      background: #fff;
      border-radius: 14px;
      box-shadow: var(--card-shadow);
      padding: 22px 28px;
      margin-bottom: 14px;
      cursor: pointer;
      transition: box-shadow 0.2s, transform 0.15s;
      text-decoration: none;
      display: block;
      color: inherit;
      border: 1px solid transparent;
    }

    .post-card:hover {
      box-shadow: 0 6px 24px rgba(60,80,120,0.13);
      transform: translateY(-2px);
      border-color: #dde2ea;
      color: inherit;
    }

    .post-card-title {
      font-size: 17px;
      font-weight: 700;
      color: #1a2535;
      margin-bottom: 8px;
      line-height: 1.4;
    }

    .post-card-preview {
      font-size: 14px;
      color: #667;
      line-height: 1.6;
      margin-bottom: 14px;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .post-card-meta {
      display: flex;
      align-items: center;
      gap: 16px;
      font-size: 12px;
      color: #9aa;
    }

    .meta-item {
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .meta-author {
      font-weight: 600;
      color: #6e8ab0;
    }

    /* ── Empty ── */
    .empty-state {
      text-align: center;
      padding: 80px 20px;
      color: #aab;
    }

    .empty-state i {
      font-size: 48px;
      display: block;
      margin-bottom: 14px;
    }

    /* ── Login prompt ── */
    .login-prompt {
      background: #f4f6f9;
      border: 1px solid #dde2ea;
      border-radius: 10px;
      padding: 14px 20px;
      font-size: 13px;
      color: #667;
      text-align: center;
      margin-bottom: 20px;
    }

    .login-prompt a {
      color: #4a6080;
      font-weight: 600;
      text-decoration: none;
    }

    /* ── Footer ── */
    footer {
      background-color: var(--footer-bg);
      color: #333;
      padding: 16px 0;
      text-align: center;
      font-size: 13px;
    }

    /* ── Mobile ── */
    @media (max-width: 768px) {
      .forum-layout { flex-direction: column; padding: 16px 12px 40px; }
      .forum-sidebar { width: 100%; }
      .sidebar-card { position: static; }
      .sidebar-list { display: flex; overflow-x: auto; }
      .sidebar-item { white-space: nowrap; border-bottom: none; border-right: 1px solid var(--border); }
    }
  </style>
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
