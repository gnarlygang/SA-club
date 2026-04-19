<?php
require_once "header.php";
$host    = "localhost";
$dbname  = "sa2026";
$db_user = "root";
$db_pass = "";

$error = "";

// URL 帶 ?id=N 指定要編輯的社團
$club_id = isset($_GET["id"]) ? (int)$_GET["id"] : (isset($_POST["club_id"]) ? (int)$_POST["club_id"] : 1);

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $db_user,
        $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 處理 POST 送出
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $description = trim($_POST["description"] ?? "");
        $image       = trim($_POST["image"]       ?? "");

        if ($description === "") {
            $error = "社團介紹不可為空。";
        } else {
            $stmt = $pdo->prepare("UPDATE clubs SET description = :description, image = :image WHERE id = :id");
            $stmt->execute([
                ":description" => $description,
                ":image"       => $image,
                ":id"          => $club_id,
            ]);

            header("Location: create_club.php?id=" . $club_id);
            exit;
        }
    }

    // 取得目前社團資料
    $stmt = $pdo->prepare("SELECT * FROM clubs WHERE id = :id LIMIT 1");
    $stmt->execute([":id" => $club_id]);
    $club = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("資料庫連線失敗：" . $e->getMessage());
}

// 表單顯示值：POST 失敗時保留輸入，否則用資料庫值
$form_description = isset($_POST["description"]) ? $_POST["description"] : ($club["description"] ?? "");
$form_image       = isset($_POST["image"])       ? $_POST["image"]       : ($club["image"]       ?? "");
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>編輯社團資料 — 輔大社團平台</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;600;700&family=Microsoft+JhengHei&display=swap" rel="stylesheet">

  <style>
    :root {
      --nav-bg: #f8f9fa;
      --accent: #3a3a3a;
      --footer-bg: #afbac7;
      --card-shadow: 0 8px 32px rgba(60,80,120,0.10);
      --input-border: #c8d0dc;
      --btn-bg: #2d2d2d;
      --btn-hover: #444;
      --error-color: #c0392b;
      --label-color: #555;
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
    .edit-wrapper {
      flex: 1;
      display: flex;
      align-items: flex-start;
      justify-content: center;
      padding: 48px 16px 60px;
    }

    /* ── Card ── */
    .edit-card {
      width: 100%;
      max-width: 780px;
      background: #fff;
      border-radius: 18px;
      box-shadow: var(--card-shadow);
      overflow: hidden;
    }

    .edit-card-header {
      background: #2d3a4a;
      color: #fff;
      padding: 36px 48px 28px;
      text-align: center;
    }

    .edit-card-header .logo-text {
      font-family: "Noto Serif TC", serif;
      font-size: 22px;
      font-weight: 700;
      letter-spacing: 3px;
      margin-bottom: 6px;
    }

    .edit-card-header .subtitle {
      font-size: 13px;
      opacity: 0.72;
      letter-spacing: 1px;
    }

    /* ── Preview image ── */
    .preview-wrap {
      width: 100%;
      height: 260px;
      overflow: hidden;
      background: #dde2ea;
      position: relative;
    }

    .preview-wrap img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      transition: opacity 0.3s;
    }

    .preview-placeholder {
      width: 100%;
      height: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: #aab;
      gap: 8px;
    }

    .preview-placeholder i { font-size: 48px; }
    .preview-placeholder span { font-size: 13px; }

    /* ── Card body ── */
    .edit-card-body {
      padding: 36px 48px 44px;
    }

    /* ── Read-only rows ── */
    .readonly-row {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 12px 0;
      border-bottom: 1px solid #eef1f5;
      margin-bottom: 4px;
    }

    .readonly-icon {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      background: #f0f3f7;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #6e8ab0;
      font-size: 15px;
      flex-shrink: 0;
    }

    .readonly-label {
      font-size: 11px;
      color: #9aa;
      letter-spacing: 0.5px;
    }

    .readonly-value {
      font-size: 14px;
      font-weight: 600;
      color: #2d3a4a;
    }

    /* ── Form ── */
    .form-section { margin-top: 24px; }

    .form-label {
      font-size: 13px;
      font-weight: 600;
      color: var(--label-color);
      margin-bottom: 6px;
      letter-spacing: 0.5px;
    }

    .form-control {
      border: 1.5px solid var(--input-border);
      border-radius: 8px;
      padding: 10px 14px;
      font-size: 14px;
      transition: border-color 0.2s, box-shadow 0.2s;
    }

    .form-control:focus {
      border-color: #6e8ab0;
      box-shadow: 0 0 0 3px rgba(110,138,176,0.15);
      outline: none;
    }

    textarea.form-control {
      resize: vertical;
      min-height: 110px;
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

    .input-group:focus-within .input-group-text {
      border-color: #6e8ab0;
    }

    .hint-text {
      font-size: 11px;
      color: #9aa;
      margin-top: 5px;
    }

    /* ── Alert ── */
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

    /* ── Divider ── */
    .divider {
      border: none;
      border-top: 1px solid #e8ecf0;
      margin: 28px 0;
    }

    /* ── Buttons ── */
    .btn-save {
      background: var(--btn-bg);
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 13px;
      font-size: 15px;
      font-weight: 600;
      letter-spacing: 1px;
      width: 100%;
      transition: background 0.2s, transform 0.1s;
    }

    .btn-save:hover {
      background: var(--btn-hover);
      transform: translateY(-1px);
    }

    .btn-save:active { transform: translateY(0); }

    .btn-cancel {
      display: block;
      text-align: center;
      margin-top: 14px;
      font-size: 13px;
      color: #778;
      text-decoration: none;
    }

    .btn-cancel:hover { color: #3a3a3a; }

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

  <!-- 編輯區 -->
  <div class="edit-wrapper">
    <div class="edit-card">

      <!-- 卡片頂部 -->
      <div class="edit-card-header">
        <div class="logo-text">編輯社團資料</div>
        <div class="subtitle">天主教輔仁大學 社團平台</div>
      </div>

      <!-- 圖片預覽 -->
      <div class="preview-wrap" id="previewWrap">
        <?php if (!empty($form_image)): ?>
          <img id="previewImg"
               src="<?= htmlspecialchars($form_image) ?>"
               alt="社團圖片預覽"
               onerror="showPlaceholder()">
        <?php else: ?>
          <div class="preview-placeholder" id="previewPlaceholder">
            <i class="bi bi-image"></i>
            <span>輸入圖片網址後將自動預覽</span>
          </div>
        <?php endif; ?>
      </div>

      <div class="edit-card-body">

        <?php if ($club): ?>

          <!-- 唯讀資訊 -->
          <div class="readonly-row">
            <div class="readonly-icon"><i class="bi bi-building"></i></div>
            <div>
              <div class="readonly-label">社團名稱（不可修改）</div>
              <div class="readonly-value"><?= htmlspecialchars($club["name"]) ?></div>
            </div>
          </div>

          <div class="readonly-row">
            <div class="readonly-icon"><i class="bi bi-grid"></i></div>
            <div>
              <div class="readonly-label">社團類別（不可修改）</div>
              <div class="readonly-value"><?= htmlspecialchars($club["category"]) ?></div>
            </div>
          </div>

        <?php endif; ?>

        <!-- 表單 -->
        <div class="form-section">

          <?php if ($error): ?>
            <div class="alert-error">
              <i class="bi bi-exclamation-circle-fill"></i>
              <?= htmlspecialchars($error) ?>
            </div>
          <?php endif; ?>

          <form method="POST" action="edit_club.php?id=<?= $club_id ?>" autocomplete="off">
            <input type="hidden" name="club_id" value="<?= $club_id ?>">

            <!-- 圖片網址 -->
            <div class="mb-4">
              <label class="form-label">社團圖片（網址）</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-image"></i></span>
                <input
                  type="url"
                  class="form-control"
                  name="image"
                  id="imageInput"
                  placeholder="請貼上圖片網址（https://...）"
                  value="<?= htmlspecialchars($form_image) ?>"
                >
              </div>
              <div class="hint-text">可留空，建議使用 Unsplash 等公開圖片連結。</div>
            </div>

            <!-- 社團介紹 -->
            <div class="mb-3">
              <label class="form-label">社團介紹 <span style="color:#c0392b;">*</span></label>
              <div class="input-group align-items-start">
                <span class="input-group-text" style="border-radius:8px 0 0 8px; padding-top:11px;">
                  <i class="bi bi-text-paragraph"></i>
                </span>
                <textarea
                  class="form-control"
                  name="description"
                  placeholder="請輸入社團介紹內容"
                  required
                ><?= htmlspecialchars($form_description) ?></textarea>
              </div>
              <div class="hint-text">社團介紹為必填，將顯示於社團資料頁面。</div>
            </div>

            <hr class="divider">

            <button type="submit" class="btn-save">
              <i class="bi bi-check-circle me-2"></i>儲存變更
            </button>

          </form>

          <a href="create_club.php?id=<?= $club_id ?>" class="btn-cancel">
            <i class="bi bi-arrow-left me-1"></i>取消，返回社團資料
          </a>

        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
<?php
require "footer.php";
?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const imageInput = document.getElementById("imageInput");
    const previewWrap = document.getElementById("previewWrap");

    function showPlaceholder() {
      previewWrap.innerHTML = `
        <div class="preview-placeholder" id="previewPlaceholder">
          <i class="bi bi-image"></i>
          <span>圖片無法載入，請確認網址是否正確</span>
        </div>`;
    }

    function updatePreview(url) {
      if (!url) {
        previewWrap.innerHTML = `
          <div class="preview-placeholder" id="previewPlaceholder">
            <i class="bi bi-image"></i>
            <span>輸入圖片網址後將自動預覽</span>
          </div>`;
        return;
      }
      previewWrap.innerHTML = `
        <img id="previewImg"
             src="${url}"
             alt="社團圖片預覽"
             onerror="showPlaceholder()">`;
    }

    // 即時預覽：停止輸入 800ms 後更新
    let debounceTimer;
    imageInput.addEventListener("input", function () {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => updatePreview(this.value.trim()), 800);
    });
  </script>
</body>
</html>