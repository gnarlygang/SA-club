<?php
session_start();

$host    = "localhost";
$dbname  = "sa2026";
$db_user = "root";
$db_pass = "";

$error   = "";
$success = "";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $db_user,
        $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 處理 POST 送出
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $nickname = trim($_POST["nickname"] ?? "");

        // 暱稱可為空值，長度上限 10 字（配合資料庫 varchar(10)）
        if (mb_strlen($nickname) > 10) {
            $error = "暱稱長度不可超過 10 個字。";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET nickname = :nickname WHERE user_id = :uid");
            $stmt->execute([
                ":nickname" => $nickname === "" ? null : $nickname,
                ":uid"      => $_SESSION["user_id"],
            ]);

            header("Location: profile.php");
            exit;
        }
    }

    // 取得目前資料
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :uid LIMIT 1");
    $stmt->execute([":uid" => $_SESSION["user_id"]]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("資料庫連線失敗：" . $e->getMessage());
}

$avatar = $user["avatar_url"] ?: "https://ui-avatars.com/api/?name=" . urlencode($user["username"]) . "&size=150&background=2d3a4a&color=fff&font-size=0.4";

// 表單顯示的暱稱：POST 失敗時保留輸入值，否則用資料庫值
$form_nickname = isset($_POST["nickname"]) ? $_POST["nickname"] : ($user["nickname"] ?? "");
require_once "header.php";
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>編輯個人資料 — 輔大社團平台</title>

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
      align-items: center;
      justify-content: center;
      padding: 60px 16px;
    }

    /* ── Card ── */
    .edit-card {
      width: 100%;
      max-width: 480px;
      background: #fff;
      border-radius: 18px;
      box-shadow: var(--card-shadow);
      overflow: hidden;
    }

    .edit-card-header {
      background: #2d3a4a;
      color: #fff;
      padding: 36px 40px 28px;
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

    /* ── Avatar ── */
    .avatar-wrap {
      margin-top: -20px;
      margin-bottom: 20px;
      display: flex;
      justify-content: center;
    }

    .avatar-wrap img {
      width: 96px;
      height: 96px;
      border-radius: 50%;
      border: 4px solid #fff;
      box-shadow: 0 4px 16px rgba(60,80,120,0.18);
      object-fit: cover;
      background: #dde2ea;
    }

    .edit-card-body {
      padding: 0 40px 40px;
    }

    /* ── Read-only info ── */
    .readonly-row {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 0;
      border-bottom: 1px solid #eef1f5;
      margin-bottom: 4px;
    }

    .readonly-icon {
      width: 32px;
      height: 32px;
      border-radius: 8px;
      background: #f0f3f7;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #6e8ab0;
      font-size: 14px;
      flex-shrink: 0;
    }

    .readonly-content .readonly-label {
      font-size: 11px;
      color: #9aa;
      letter-spacing: 0.5px;
    }

    .readonly-content .readonly-value {
      font-size: 14px;
      font-weight: 600;
      color: #2d3a4a;
    }

    /* ── Form ── */
    .form-section {
      margin-top: 20px;
    }

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
      margin: 24px 0;
    }

    /* ── Buttons ── */
    .btn-save {
      background: var(--btn-bg);
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 12px;
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
        <div class="logo-text">編輯個人資料</div>
        <div class="subtitle">天主教輔仁大學 社團平台</div>
      </div>

      <div class="edit-card-body">

        <!-- 頭像 -->
        <div class="avatar-wrap">
          <img src="<?= htmlspecialchars($avatar) ?>"
               alt="頭像"
               onerror="this.src='https://ui-avatars.com/api/?name=User&size=150&background=2d3a4a&color=fff'">
        </div>

        <!-- 唯讀資訊 -->
        <div class="readonly-row">
          <div class="readonly-icon"><i class="bi bi-person-fill"></i></div>
          <div class="readonly-content">
            <div class="readonly-label">姓名（不可修改）</div>
            <div class="readonly-value"><?= htmlspecialchars($user["username"]) ?></div>
          </div>
        </div>

        <div class="readonly-row">
          <div class="readonly-icon"><i class="bi bi-envelope-fill"></i></div>
          <div class="readonly-content">
            <div class="readonly-label">電子信箱（不可修改）</div>
            <div class="readonly-value"><?= htmlspecialchars($user["email"]) ?></div>
          </div>
        </div>

        <!-- 表單 -->
        <div class="form-section">

          <?php if ($error): ?>
            <div class="alert-error">
              <i class="bi bi-exclamation-circle-fill"></i>
              <?= htmlspecialchars($error) ?>
            </div>
          <?php endif; ?>

          <form method="POST" action="profile_edit.php" autocomplete="off">

            <div class="mb-3">
              <label class="form-label">暱稱</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-chat-heart"></i></span>
                <input
                  type="text"
                  class="form-control"
                  name="nickname"
                  placeholder="可留空，上限 10 字"
                  value="<?= htmlspecialchars($form_nickname) ?>"
                  maxlength="10"
                >
              </div>
              <div class="hint-text">暱稱可為空值，將顯示於個人資料頁面。</div>
            </div>

            <hr class="divider">

            <button type="submit" class="btn-save">
              <i class="bi bi-check-circle me-2"></i>儲存變更
            </button>

          </form>

          <a href="profile.php" class="btn-cancel">
            <i class="bi bi-arrow-left me-1"></i>取消，返回個人資料
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
</body>
</html>