<?php
session_start();

<<<<<<< HEAD
$host    = "localhost";
$dbname  = "sa2026";
$db_user = "root";
$db_pass = "";
=======
require_once "api/db.php";
>>>>>>> ff9d9d8dfc7e99533a15e6cde67f9a611bbc9300

$error = "";

// 取得預設分類（從 URL 帶入）
$default_cat = isset($_GET["cat"]) ? (int)$_GET["cat"] : 1;

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

    // 處理 POST
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $title       = trim($_POST["title"]       ?? "");
        $content     = trim($_POST["content"]     ?? "");
        $category_id = (int)($_POST["category_id"] ?? 0);

        if ($title === "") {
            $error = "標題不可為空。";
        } elseif (mb_strlen($title) > 255) {
            $error = "標題不可超過 255 字。";
        } elseif ($content === "") {
            $error = "內容不可為空。";
        } elseif ($category_id === 0) {
            $error = "請選擇討論分類。";
        } else {
            $stmt = $pdo->prepare("INSERT INTO forum_posts (category_id, user_id, title, content) VALUES (:cat, :uid, :title, :content)");
            $stmt->execute([
                ":cat"     => $category_id,
                ":uid"     => $_SESSION["user_id"],
                ":title"   => $title,
                ":content" => $content,
            ]);
            $new_id = $pdo->lastInsertId();
            header("Location: forum_post.php?id=" . $new_id);
            exit;
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
  <title>發表文章 — 輔大社團論壇</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;600;700&family=Microsoft+JhengHei&display=swap" rel="stylesheet">

  <style>
    :root {
      --footer-bg: #afbac7;
      --card-shadow: 0 4px 24px rgba(60,80,120,0.10);
      --input-border: #c8d0dc;
      --btn-bg: #2d2d2d;
      --btn-hover: #444;
      --error-color: #c0392b;
      --label-color: #555;
    }

    * { box-sizing: border-box; }

    body {
      font-family: "Microsoft JhengHei", "微軟正黑體", sans-serif;
      background: #f0f2f5;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .new-post-wrapper {
      flex: 1;
      max-width: 780px;
      margin: 0 auto;
      width: 100%;
      padding: 32px 16px 60px;
    }

    .breadcrumb-bar {
      font-size: 13px;
      color: #9aa;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .breadcrumb-bar a {
      color: #6e8ab0;
      text-decoration: none;
      font-weight: 600;
    }

    .new-post-card {
      background: #fff;
      border-radius: 16px;
      box-shadow: var(--card-shadow);
      overflow: hidden;
    }

    .new-post-header {
      background: #2d3a4a;
      color: #fff;
      padding: 28px 40px;
    }

    .new-post-header .logo-text {
      font-family: "Noto Serif TC", serif;
      font-size: 20px;
      font-weight: 700;
      letter-spacing: 2px;
      margin-bottom: 4px;
    }

    .new-post-header .subtitle {
      font-size: 13px;
      opacity: 0.72;
    }

    .new-post-body { padding: 36px 40px 44px; }

    .form-label {
      font-size: 13px;
      font-weight: 600;
      color: var(--label-color);
      margin-bottom: 6px;
      letter-spacing: 0.5px;
    }

    .form-control,
    .form-select {
      border: 1.5px solid var(--input-border);
      border-radius: 8px;
      padding: 10px 14px;
      font-size: 14px;
      transition: border-color 0.2s, box-shadow 0.2s;
    }

    .form-control:focus,
    .form-select:focus {
      border-color: #6e8ab0;
      box-shadow: 0 0 0 3px rgba(110,138,176,0.15);
      outline: none;
    }

    textarea.form-control {
      resize: vertical;
      min-height: 180px;
    }

    .input-group-text {
      background: #f4f6f9;
      border: 1.5px solid var(--input-border);
      border-right: none;
      border-radius: 8px 0 0 8px;
      color: #7a8a9a;
    }

    .input-group .form-control {
      border-left: none;
      border-radius: 0 8px 8px 0;
    }

    .input-group:focus-within .input-group-text { border-color: #6e8ab0; }

    .hint-text {
      font-size: 11px;
      color: #9aa;
      margin-top: 5px;
    }

    .alert-error {
      background: #fdf0ef;
      border: 1px solid #f0c4c0;
      color: var(--error-color);
      border-radius: 8px;
      padding: 10px 14px;
      font-size: 13px;
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 20px;
    }

    .divider {
      border: none;
      border-top: 1px solid #e8ecf0;
      margin: 28px 0;
    }

    .btn-submit {
      background: var(--btn-bg);
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 13px 24px;
      font-size: 15px;
      font-weight: 600;
      letter-spacing: 1px;
      width: 100%;
      transition: background 0.2s, transform 0.1s;
    }

    .btn-submit:hover {
      background: var(--btn-hover);
      transform: translateY(-1px);
    }

    .btn-cancel {
      display: block;
      text-align: center;
      margin-top: 14px;
      font-size: 13px;
      color: #778;
      text-decoration: none;
    }

    .btn-cancel:hover { color: #3a3a3a; }

    footer {
      background-color: var(--footer-bg);
      color: #333;
      padding: 16px 0;
      text-align: center;
      font-size: 13px;
    }
  </style>
</head>
<body>

<div class="new-post-wrapper">

  <div class="breadcrumb-bar">
    <a href="forum.php"><i class="bi bi-journals me-1"></i>社團論壇</a>
    <i class="bi bi-chevron-right" style="font-size:11px;"></i>
    <span style="color:#667;">發表文章</span>
  </div>

  <div class="new-post-card">

    <div class="new-post-header">
      <div class="logo-text">發表文章</div>
      <div class="subtitle">天主教輔仁大學 社團論壇</div>
    </div>

    <div class="new-post-body">

      <?php if ($error): ?>
        <div class="alert-error">
          <i class="bi bi-exclamation-circle-fill"></i>
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="forum_new.php" autocomplete="off">

        <!-- 分類 -->
        <div class="mb-4">
          <label class="form-label">討論分類 <span style="color:#c0392b;">*</span></label>
          <select class="form-select" name="category_id" required>
            <option value="">請選擇分類</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat["id"] ?>"
                <?= ((isset($_POST["category_id"]) ? (int)$_POST["category_id"] : $default_cat) == $cat["id"]) ? "selected" : "" ?>>
                <?= htmlspecialchars($cat["name"]) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- 標題 -->
        <div class="mb-4">
          <label class="form-label">文章標題 <span style="color:#c0392b;">*</span></label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-fonts"></i></span>
            <input
              type="text"
              class="form-control"
              name="title"
              placeholder="請輸入文章標題"
              value="<?= htmlspecialchars($_POST["title"] ?? "") ?>"
              maxlength="255"
              required
            >
          </div>
          <div class="hint-text">標題上限 255 字，請簡明扼要。</div>
        </div>

        <!-- 內容 -->
        <div class="mb-3">
          <label class="form-label">文章內容 <span style="color:#c0392b;">*</span></label>
          <div class="input-group align-items-start">
            <span class="input-group-text" style="border-radius:8px 0 0 8px; padding-top:11px;">
              <i class="bi bi-text-paragraph"></i>
            </span>
            <textarea
              class="form-control"
              name="content"
              placeholder="請輸入文章內容..."
              required
            ><?= htmlspecialchars($_POST["content"] ?? "") ?></textarea>
          </div>
        </div>

        <hr class="divider">

        <button type="submit" class="btn-submit">
          <i class="bi bi-send me-2"></i>發表文章
        </button>

      </form>

      <a href="forum.php?cat=<?= $default_cat ?>" class="btn-cancel">
        <i class="bi bi-arrow-left me-1"></i>取消，返回論壇
      </a>

    </div>
  </div>

</div>

<?php require "footer.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
