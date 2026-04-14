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

    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :uid LIMIT 1");
    $stmt->execute([":uid" => $_SESSION["user_id"]]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("資料庫連線失敗：" . $e->getMessage());
}

$role_map = [
    1 => "教師",
    2 => "社團",
    3 => "學生",
    4 => "管理者",
];
$role_label = $role_map[$user["role"]] ?? "未知";

$avatar = $user["avatar_url"] ?: "https://ui-avatars.com/api/?name=" . urlencode($user["username"]) . "&size=150&background=2d3a4a&color=fff&font-size=0.4";
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>個人資料 — 輔大社團平台</title>

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
    .profile-wrapper {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 60px 16px;
    }

    /* ── Card ── */
    .profile-card {
      width: 100%;
      max-width: 480px;
      background: #fff;
      border-radius: 18px;
      box-shadow: var(--card-shadow);
      overflow: hidden;
    }

    .profile-card-header {
      background: #2d3a4a;
      color: #fff;
      padding: 36px 40px 28px;
      text-align: center;
    }

    .profile-card-header .logo-text {
      font-family: "Noto Serif TC", serif;
      font-size: 22px;
      font-weight: 700;
      letter-spacing: 3px;
      margin-bottom: 6px;
    }

    .profile-card-header .subtitle {
      font-size: 13px;
      opacity: 0.72;
      letter-spacing: 1px;
    }

    /* ── Avatar ── */
    .avatar-wrap {
      margin-top: -20px;
      margin-bottom: 16px;
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

    .profile-card-body {
      padding: 0 40px 40px;
    }

    /* ── Info rows ── */
    .info-section {
      margin-top: 8px;
    }

    .info-row {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 14px 0;
      border-bottom: 1px solid #eef1f5;
    }

    .info-row:last-child {
      border-bottom: none;
    }

    .info-icon {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      background: #f0f3f7;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #6e8ab0;
      font-size: 16px;
      flex-shrink: 0;
    }

    .info-content {
      flex: 1;
    }

    .info-label {
      font-size: 11px;
      color: #9aa;
      letter-spacing: 0.5px;
      margin-bottom: 2px;
    }

    .info-value {
      font-size: 15px;
      font-weight: 600;
      color: #2d3a4a;
    }

    .info-value.muted {
      color: #aab;
      font-weight: 400;
      font-style: italic;
    }

    /* ── Role badge ── */
    .role-badge {
      display: inline-block;
      font-size: 11px;
      padding: 3px 10px;
      border-radius: 999px;
      background: #e8ecf5;
      color: #4a6080;
      font-weight: 600;
      letter-spacing: 0.5px;
    }

    /* ── Buttons ── */
    .btn-edit {
      background: var(--btn-bg);
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 12px;
      font-size: 15px;
      font-weight: 600;
      letter-spacing: 1px;
      width: 100%;
      text-align: center;
      text-decoration: none;
      display: block;
      transition: background 0.2s, transform 0.1s;
      margin-top: 28px;
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
    <div class="ms-auto d-flex align-items-center gap-3">
      <span style="font-size:14px; color:#667;">
        <?= htmlspecialchars($_SESSION["username"]) ?>
      </span>
      <a href="logout.php" class="text-decoration-none text-secondary" style="font-size:14px;">
        <i class="bi bi-box-arrow-right me-1"></i>登出
      </a>
    </div>
  </nav>

  <!-- 個人資料區 -->
  <div class="profile-wrapper">
    <div class="profile-card">

      <!-- 卡片頂部 -->
      <div class="profile-card-header">
        <div class="logo-text">個人資料</div>
        <div class="subtitle">天主教輔仁大學 社團平台</div>
      </div>

      <!-- 卡片內容 -->
      <div class="profile-card-body">

        <!-- 頭像 -->
        <div class="avatar-wrap">
          <img src="<?= htmlspecialchars($avatar) ?>"
               alt="頭像"
               onerror="this.src='https://ui-avatars.com/api/?name=User&size=150&background=2d3a4a&color=fff'">
        </div>

        <!-- 身分別標籤 -->
        <div class="text-center mb-2">
          <span class="role-badge">
            <?php
              $role_icons = [1=>"👨‍🏫", 2=>"🏛️", 3=>"🎓", 4=>"🔧"];
              echo ($role_icons[$user["role"]] ?? "") . " " . $role_label;
            ?>
          </span>
        </div>

        <!-- 資料列 -->
        <div class="info-section">

          <div class="info-row">
            <div class="info-icon"><i class="bi bi-person-fill"></i></div>
            <div class="info-content">
              <div class="info-label">姓名</div>
              <div class="info-value"><?= htmlspecialchars($user["username"]) ?></div>
            </div>
          </div>

          <div class="info-row">
            <div class="info-icon"><i class="bi bi-chat-heart-fill"></i></div>
            <div class="info-content">
              <div class="info-label">暱稱</div>
              <?php if (!empty($user["nickname"])): ?>
                <div class="info-value"><?= htmlspecialchars($user["nickname"]) ?></div>
              <?php else: ?>
                <div class="info-value muted">尚未設定暱稱</div>
              <?php endif; ?>
            </div>
          </div>

          <div class="info-row">
            <div class="info-icon"><i class="bi bi-envelope-fill"></i></div>
            <div class="info-content">
              <div class="info-label">電子信箱</div>
              <div class="info-value"><?= htmlspecialchars($user["email"]) ?></div>
            </div>
          </div>

          <div class="info-row">
            <div class="info-icon"><i class="bi bi-fingerprint"></i></div>
            <div class="info-content">
              <div class="info-label">帳號編號</div>
              <div class="info-value"><?= htmlspecialchars($user["user_id"]) ?></div>
            </div>
          </div>

        </div>
        <!-- /資料列 -->

        <a href="profile_edit.php" class="btn-edit">
          <i class="bi bi-pencil-square me-2"></i>編輯個人資料
        </a>

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
</body>
</html>