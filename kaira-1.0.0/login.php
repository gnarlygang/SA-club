<?php
session_start();

$host     = "localhost";
$dbname   = "sa2026";
$db_user  = "root";       // 請依實際情況修改
$db_pass  = "";           // 請依實際情況修改

$error   = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $input_id  = trim($_POST["user_id"]  ?? "");
    $input_pw  = trim($_POST["password"] ?? "");

    if ($input_id === "" || $input_pw === "") {
        $error = "請輸入帳號與密碼。";
    } else {
        try {
            $pdo = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $db_user,
                $db_pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :uid LIMIT 1");
            $stmt->execute([":uid" => $input_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($input_pw, $user["password"])) {
                $_SESSION["user_id"]  = $user["user_id"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["role"]     = $user["role"];

                // 依身分別導向不同頁面
                $role_map = [
                    1 => "teacher_dashboard.php",
                    2 => "club_dashboard.php",
                    3 => "student_dashboard.php",
                    4 => "admin_dashboard.php",
                ];
                $redirect = $role_map[$user["role"]] ?? "index.html";
                header("Location: $redirect");
                exit;
            } else {
                $error = "帳號或密碼錯誤，請重新輸入。";
            }
        } catch (PDOException $e) {
            $error = "資料庫連線失敗：" . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>登入 — 輔大社團平台</title>

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

    /* ── Navbar (same as index) ── */
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
    .login-wrapper {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 60px 16px;
    }

    /* ── Card ── */
    .login-card {
      width: 100%;
      max-width: 440px;
      background: #fff;
      border-radius: 18px;
      box-shadow: var(--card-shadow);
      overflow: hidden;
    }

    .login-card-header {
      background: #2d3a4a;
      color: #fff;
      padding: 36px 40px 28px;
      text-align: center;
    }

    .login-card-header .logo-text {
      font-family: "Noto Serif TC", serif;
      font-size: 26px;
      font-weight: 700;
      letter-spacing: 3px;
      margin-bottom: 6px;
    }

    .login-card-header .subtitle {
      font-size: 13px;
      opacity: 0.72;
      letter-spacing: 1px;
    }

    .login-card-body {
      padding: 36px 40px 40px;
    }

    /* ── Form ── */
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

    .input-group .form-control:focus {
      border-left: none;
    }

    .input-group:focus-within .input-group-text {
      border-color: #6e8ab0;
    }

    .toggle-pw {
      cursor: pointer;
      background: #f4f6f9;
      border: 1.5px solid var(--input-border);
      border-left: none;
      border-radius: 0 8px 8px 0;
      padding: 0 14px;
      color: #7a8a9a;
      transition: color 0.2s;
    }

    .toggle-pw:hover { color: #3a3a3a; }

    .pw-group .form-control {
      border-left: none;
      border-right: none;
      border-radius: 0;
    }

    /* ── Role hint chips ── */
    .role-hints {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-top: 16px;
      margin-bottom: 4px;
    }

    .role-chip {
      font-size: 11px;
      padding: 3px 10px;
      border-radius: 999px;
      border: 1px solid #c8d0dc;
      color: #667;
      background: #f4f6f9;
      white-space: nowrap;
    }

    /* ── Alert ── */
    .alert-login {
      background: #fdf0ef;
      border: 1px solid #f0c4c0;
      color: var(--error-color);
      border-radius: 8px;
      padding: 10px 14px;
      font-size: 13px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    /* ── Submit button ── */
    .btn-login {
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

    .btn-login:hover {
      background: var(--btn-hover);
      transform: translateY(-1px);
    }

    .btn-login:active {
      transform: translateY(0);
    }

    /* ── Back link ── */
    .back-link {
      display: block;
      text-align: center;
      margin-top: 20px;
      font-size: 13px;
      color: #778;
      text-decoration: none;
    }

    .back-link:hover { color: #3a3a3a; }

    /* ── Divider ── */
    .divider {
      border: none;
      border-top: 1px solid #e8ecf0;
      margin: 24px 0;
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

  <!-- 導覽列 -->
  <nav class="navbar px-4 py-3">
    <a class="navbar-brand" href="index.html">FJU_CLUB</a>
    <a href="index.html" class="ms-auto text-decoration-none text-secondary" style="font-size:14px;">
      <i class="bi bi-arrow-left me-1"></i>回主頁
    </a>
  </nav>

  <!-- 登入區 -->
  <div class="login-wrapper">
    <div class="login-card">

      <!-- 卡片頂部 -->
      <div class="login-card-header">
        <div class="logo-text">FJU_CLUB</div>
        <div class="subtitle">天主教輔仁大學 社團平台</div>
      </div>

      <!-- 表單 -->
      <div class="login-card-body">

        <h5 class="mb-1" style="font-weight:700; font-size:18px;">歡迎登入</h5>
        <p style="font-size:13px; color:#889; margin-bottom:22px;">請使用您的學號 / 教師編號 / 社團編號登入</p>

        <?php if ($error): ?>
          <div class="alert-login mb-4">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="login.php" autocomplete="off">

          <!-- 帳號 -->
          <div class="mb-4">
            <label class="form-label">帳號（編號）</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-person"></i></span>
              <input
                type="text"
                class="form-control"
                name="user_id"
                placeholder="請輸入您的編號"
                value="<?= htmlspecialchars($_POST['user_id'] ?? '') ?>"
                required
                autofocus
              >
            </div>
            <!-- 身分別提示 -->
            <div class="role-hints">
              <span class="role-chip">👨‍🏫 教師：6位數</span>
              <span class="role-chip">🏛️ 社團：7位數</span>
              <span class="role-chip">🎓 學生：9位數</span>
              <span class="role-chip">🔧 管理者：不限</span>
            </div>
          </div>

          <!-- 密碼 -->
          <div class="mb-4">
            <label class="form-label">密碼</label>
            <div class="input-group pw-group">
              <span class="input-group-text"><i class="bi bi-lock"></i></span>
              <input
                type="password"
                class="form-control"
                name="password"
                id="passwordInput"
                placeholder="請輸入密碼"
                required
              >
              <button type="button" class="toggle-pw" onclick="togglePassword()" title="顯示/隱藏密碼">
                <i class="bi bi-eye" id="eyeIcon"></i>
              </button>
            </div>
          </div>

          <hr class="divider">

          <button type="submit" class="btn-login">
            <i class="bi bi-box-arrow-in-right me-2"></i>登入
          </button>

        </form>

        <a href="index.html" class="back-link">
          <i class="bi bi-house me-1"></i>返回社團平台首頁
        </a>

      </div>
    </div>
  </div>

  <!-- Footer -->
  <footer>
    天主教輔仁大學 © 2014-2026 版權所有
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function togglePassword() {
      const input = document.getElementById("passwordInput");
      const icon  = document.getElementById("eyeIcon");
      if (input.type === "password") {
        input.type = "text";
        icon.classList.replace("bi-eye", "bi-eye-slash");
      } else {
        input.type = "password";
        icon.classList.replace("bi-eye-slash", "bi-eye");
      }
    }
  </script>
</body>
</html>