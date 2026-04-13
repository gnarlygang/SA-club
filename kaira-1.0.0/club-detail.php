<?php
require_once __DIR__ . "/api/db.php";

$clubId = isset($_GET["id"]) ? intval($_GET["id"]) : 0;

if ($clubId <= 0) {
    die("社團 ID 錯誤");
}

/* 1. 抓社團基本資料 */
$sql = "SELECT id, name, category, description, image
        FROM clubs
        WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([":id" => $clubId]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$club) {
    die("查無此社團");
}

/* 2. 抓社團標籤 */
$sqlTags = "SELECT tag_name
            FROM club_tags
            WHERE club_id = :club_id";
$stmtTags = $pdo->prepare($sqlTags);
$stmtTags->execute([":club_id" => $clubId]);
$tags = $stmtTags->fetchAll(PDO::FETCH_COLUMN);

/* 3. 抓該社團貼文
   注意：你目前 posts 表只有 club_name，沒有 club_id
   所以這裡先用社團名稱對應 */
$sqlPosts = "SELECT title, description, DATE_FORMAT(date, '%Y/%m/%d') AS date, image
             FROM posts
             WHERE club_name = :club_name
             ORDER BY date DESC
             LIMIT 5";
$stmtPosts = $pdo->prepare($sqlPosts);
$stmtPosts->execute([":club_name" => $club["name"]]);
$posts = $stmtPosts->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($club["name"]) ?>｜FJU_CLUB</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      font-family: "Microsoft JhengHei", sans-serif;
      background-color: #f8f8f8;
    }
    .navbar-brand {
      font-weight: bold;
      font-size: 22px;
      letter-spacing: 2px;
      color: #111 !important;
    }
    .club-hero img {
      width: 100%;
      height: 420px;
      object-fit: cover;
      border-radius: 16px;
    }
    .club-info-card,
    .club-section-card {
      background: #fff;
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .club-tag {
      display: inline-block;
      background: #eef1f4;
      color: #333;
      padding: 6px 14px;
      border-radius: 999px;
      font-size: 14px;
      margin: 4px 6px 0 0;
    }
    .section-title {
      font-size: 24px;
      font-weight: bold;
      margin-bottom: 18px;
    }
    .post-item {
      border: 1px solid #e5e5e5;
      border-radius: 12px;
      padding: 16px;
      margin-bottom: 12px;
      background: #fff;
    }
    .meta-text {
      color: #777;
      font-size: 14px;
    }
  </style>
</head>
<body>
/* 以下是社團介紹公版 */
  <nav class="navbar navbar-expand-lg bg-light fs-6 p-3 border-bottom">
    <div class="container">
      <a class="navbar-brand" href="index.html">FJU_CLUB</a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarContent">
        <ul class="navbar-nav ms-auto gap-3">
          <li class="nav-item"><a class="nav-link" href="index.html">主頁</a></li>
          <li class="nav-item"><a class="nav-link active" href="#">社團介紹</a></li>
          <li class="nav-item"><a class="nav-link" href="#">活動</a></li>
          <li class="nav-item"><a class="nav-link" href="#">社團問答區</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <div class="container py-5">
    <div class="mb-4">
      <a href="index.html" class="btn btn-outline-dark">
        <i class="bi bi-arrow-left"></i> 返回首頁
      </a>
    </div>

    <div class="club-hero mb-4">
      <img src="<?= htmlspecialchars($club["image"]) ?>" alt="<?= htmlspecialchars($club["name"]) ?>">
    </div>

    <div class="row g-4">
      <div class="col-lg-8">

        <div class="club-info-card mb-4">
          <div class="d-flex flex-wrap justify-content-between align-items-start mb-3">
            <div>
              <h1 class="mb-2"><?= htmlspecialchars($club["name"]) ?></h1>
              <div class="meta-text mb-2">分類：<?= htmlspecialchars($club["category"]) ?></div>
            </div>
            <a href="#" class="btn btn-dark">我要追蹤</a>
          </div>

          <p><?= nl2br(htmlspecialchars($club["description"])) ?></p>

          <div class="mt-3">
            <?php if (!empty($tags)): ?>
              <?php foreach ($tags as $tag): ?>
                <span class="club-tag"><?= htmlspecialchars($tag) ?></span>
              <?php endforeach; ?>
            <?php else: ?>
              <span class="text-muted">目前沒有標籤</span>
            <?php endif; ?>
          </div>
        </div>

        <div class="club-section-card">
          <div class="section-title">最新貼文</div>

          <?php if (!empty($posts)): ?>
            <?php foreach ($posts as $post): ?>
              <div class="post-item">
                <h6><?= htmlspecialchars($post["title"]) ?></h6>
                <div class="meta-text mb-2">發佈日期：<?= htmlspecialchars($post["date"]) ?></div>
                <p class="mb-0"><?= htmlspecialchars($post["description"]) ?></p>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p class="text-muted mb-0">目前沒有貼文資料</p>
          <?php endif; ?>
        </div>

      </div>

      <div class="col-lg-4">
        <div class="club-section-card mb-4">
          <div class="section-title">社團資訊</div>
          <p><strong>社團名稱：</strong><?= htmlspecialchars($club["name"]) ?></p>
          <p><strong>社團分類：</strong><?= htmlspecialchars($club["category"]) ?></p>
          <p class="mb-0"><strong>社團 ID：</strong><?= htmlspecialchars($club["id"]) ?></p>
        </div>

        <div class="club-section-card">
          <div class="section-title">相關連結</div>
          <div class="d-grid gap-2">
            <a href="index.html" class="btn btn-outline-dark">返回首頁</a>
            <a href="#" class="btn btn-outline-dark">聯絡社團</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <footer class="mt-5 py-4 text-center bg-light border-top">
    <p class="mb-0">FJU_CLUB 社團平台</p>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>