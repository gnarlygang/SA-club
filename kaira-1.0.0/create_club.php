<?php
require_once "header.php";
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

    // URL 帶 ?id=N 可指定社團，否則顯示第一筆
    $club_id = isset($_GET["id"]) ? (int)$_GET["id"] : null;

    if ($club_id) {
        $stmt = $pdo->prepare("SELECT c.*, GROUP_CONCAT(ct.tag_name ORDER BY ct.id SEPARATOR ', ') AS tags
                               FROM clubs c
                               LEFT JOIN club_tags ct ON ct.club_id = c.id
                               WHERE c.id = :id
                               GROUP BY c.id
                               LIMIT 1");
        $stmt->execute([":id" => $club_id]);
    } else {
        $stmt = $pdo->query("SELECT c.*, GROUP_CONCAT(ct.tag_name ORDER BY ct.id SEPARATOR ', ') AS tags
                             FROM clubs c
                             LEFT JOIN club_tags ct ON ct.club_id = c.id
                             GROUP BY c.id
                             ORDER BY c.id ASC
                             LIMIT 1");
    }
    $club = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("資料庫連線失敗：" . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>社團資料 — 輔大社團平台</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;600;700&family=Microsoft+JhengHei&display=swap" rel="stylesheet">

  <style>
    :root {
      --nav-bg: #f8f9fa;
      --accent: #3a3a3a;
      --footer-bg: #afbac7;
      --card-shadow: 0 8px 32px rgba(60,80,120,0.10);
      --btn-bg: #2d2d2d;
      --btn-hover: #444;
    }

    * { box-sizing: border-box; }

    body {
      font-family: "Microsoft JhengHei", "微軟正黑體", sans-serif;
      background: #eef1f5;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ── Navbar ── */
    .navbar {
      background: var(--nav-bg);
      border-bottom: 1px solid #dde2ea;
    }
    .navbar-brand {
      letter-spacing: 2px;
      font-size: 20px;
      font-weight: 700;
      color: var(--accent);
      text-decoration: none;
    }

    /* ── Main layout ── */
    .club-wrapper {
      flex: 1;
      display: flex;
      align-items: flex-start;
      justify-content: center;
      padding: 48px 16px 60px;
    }

    /* ── Card ── */
    .club-card {
      width: 100%;
      max-width: 780px;
      background: #fff;
      border-radius: 18px;
      box-shadow: var(--card-shadow);
      overflow: hidden;
    }

    .club-card-header {
      background: #2d3a4a;
      color: #fff;
      padding: 36px 48px 28px;
      text-align: center;
    }

    .club-card-header .logo-text {
      font-family: "Noto Serif TC", serif;
      font-size: 22px;
      font-weight: 700;
      letter-spacing: 3px;
      margin-bottom: 6px;
    }

    .club-card-header .subtitle {
      font-size: 13px;
      opacity: 0.72;
      letter-spacing: 1px;
    }

    /* ── Club image ── */
    .club-image-wrap {
      width: 100%;
      height: 280px;
      overflow: hidden;
      background: #dde2ea;
    }

    .club-image-wrap img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .club-image-placeholder {
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #aab;
      font-size: 48px;
    }

    .club-card-body {
      padding: 36px 48px 44px;
    }

    /* ── Category badge ── */
    .category-badge {
      display: inline-block;
      font-size: 12px;
      padding: 4px 12px;
      border-radius: 999px;
      background: #e8ecf5;
      color: #4a6080;
      font-weight: 600;
      letter-spacing: 0.5px;
      margin-bottom: 16px;
    }

    /* ── Club name ── */
    .club-name {
      font-size: 26px;
      font-weight: 700;
      color: #2d3a4a;
      margin-bottom: 6px;
    }

    /* ── Info rows ── */
    .info-section {
      margin-top: 24px;
    }

    .info-row {
      display: flex;
      align-items: flex-start;
      gap: 14px;
      padding: 16px 0;
      border-bottom: 1px solid #eef1f5;
    }

    .info-row:last-child {
      border-bottom: none;
    }

    .info-icon {
      width: 38px;
      height: 38px;
      border-radius: 10px;
      background: #f0f3f7;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #6e8ab0;
      font-size: 17px;
      flex-shrink: 0;
      margin-top: 2px;
    }

    .info-content {
      flex: 1;
    }

    .info-label {
      font-size: 11px;
      color: #9aa;
      letter-spacing: 0.5px;
      margin-bottom: 4px;
    }

    .info-value {
      font-size: 15px;
      font-weight: 600;
      color: #2d3a4a;
      line-height: 1.6;
    }

    .info-value.description {
      font-weight: 400;
      color: #445;
      font-size: 15px;
    }

    /* ── Tags ── */
    .tags-wrap {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-top: 2px;
    }

    .tag-chip {
      font-size: 12px;
      padding: 3px 10px;
      border-radius: 999px;
      border: 1px solid #c8d0dc;
      color: #667;
      background: #f4f6f9;
    }

    /* ── Buttons ── */
    .btn-edit {
      background: var(--btn-bg);
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 13px;
      font-size: 15px;
      font-weight: 600;
      letter-spacing: 1px;
      width: 100%;
      text-align: center;
      text-decoration: none;
      display: block;
      transition: background 0.2s, transform 0.1s;
      margin-top: 32px;
    }

    .btn-edit:hover {
      background: var(--btn-hover);
      transform: translateY(-1px);
      color: #fff;
    }

    .back-link {
      display: block;
      text-align: center;
      margin-top: 14px;
      font-size: 13px;
      color: #778;
      text-decoration: none;
    }

    .back-link:hover { color: #3a3a3a; }

    /* ── No data ── */
    .no-data {
      text-align: center;
      padding: 60px 40px;
      color: #aab;
    }

    /* ── Footer ── */
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

  <!-- 社團資料區 -->
  <div class="club-wrapper">
    <div class="club-card">

      <!-- 卡片頂部 -->
      <div class="club-card-header">
        <div class="logo-text">社團資料</div>
        <div class="subtitle">天主教輔仁大學 社團平台</div>
      </div>

      <?php if ($club): ?>

        <!-- 社團圖片 -->
        <div class="club-image-wrap">
          <?php if (!empty($club["image"])): ?>
            <img src="<?= htmlspecialchars($club["image"]) ?>"
                 alt="<?= htmlspecialchars($club["name"]) ?>"
                 onerror="this.parentElement.innerHTML='<div class=\'club-image-placeholder\'><i class=\'bi bi-image\'></i></div>'">
          <?php else: ?>
            <div class="club-image-placeholder">
              <i class="bi bi-image"></i>
            </div>
          <?php endif; ?>
        </div>

        <!-- 卡片內容 -->
        <div class="club-card-body">

          <span class="category-badge">
            <?= htmlspecialchars($club["category"]) ?>
          </span>
          <div class="club-name"><?= htmlspecialchars($club["name"]) ?></div>

          <div class="info-section">

            <div class="info-row">
              <div class="info-icon"><i class="bi bi-text-paragraph"></i></div>
              <div class="info-content">
                <div class="info-label">社團介紹</div>
                <div class="info-value description">
                  <?= nl2br(htmlspecialchars($club["description"])) ?>
                </div>
              </div>
            </div>

            <?php if (!empty($club["tags"])): ?>
            <div class="info-row">
              <div class="info-icon"><i class="bi bi-tags-fill"></i></div>
              <div class="info-content">
                <div class="info-label">標籤</div>
                <div class="tags-wrap">
                  <?php foreach (explode(", ", $club["tags"]) as $tag): ?>
                    <span class="tag-chip"><?= htmlspecialchars(trim($tag)) ?></span>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
            <?php endif; ?>

          </div>

          <a href="edit_club.php?id=<?= htmlspecialchars($club["id"]) ?>" class="btn-edit">
            <i class="bi bi-pencil-square me-2"></i>編輯社團資料
          </a>

          <a href="index.html" class="back-link">
            <i class="bi bi-house me-1"></i>返回社團平台首頁
          </a>

        </div>

      <?php else: ?>
        <div class="no-data">
          <i class="bi bi-exclamation-circle" style="font-size:40px; display:block; margin-bottom:12px;"></i>
          找不到社團資料，請確認資料庫是否有資料。
        </div>
      <?php endif; ?>

    </div>
  </div>

  <!-- Footer -->
<?php
require "footer.php";
?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>