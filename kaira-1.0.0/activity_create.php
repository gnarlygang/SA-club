<?php
session_start();

require_once "api/db.php";
require_once __DIR__ . "/api/notification_service.php";
$error = "";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 取得登入社團的資訊（用於預填主辦單位）
    $organizer_default = "";
    if (!empty($_SESSION["user_id"])) {
        $stmt = $pdo->prepare("SELECT username FROM users WHERE user_id = :uid LIMIT 1");
        $stmt->execute([":uid" => $_SESSION["user_id"]]);
        $me = $stmt->fetch(PDO::FETCH_ASSOC);
        $organizer_default = $me["username"] ?? "";
    }

    // 處理 POST 送出
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $title           = trim($_POST["title"]           ?? "");
        $description     = trim($_POST["description"]     ?? "");
        $event_start     = trim($_POST["event_start"]     ?? "");
        $event_end       = trim($_POST["event_end"]       ?? "");
        $location        = trim($_POST["location"]        ?? "");
        $organizer       = trim($_POST["organizer"]       ?? "");
        $fee             = trim($_POST["fee"]             ?? "");
        $target          = trim($_POST["target"]          ?? "");
        $signup_deadline = trim($_POST["signup_deadline"] ?? "");

        // 驗證
        if ($title === "") {
            $error = "請填寫活動名稱。";
        } elseif ($description === "") {
            $error = "請填寫活動簡介。";
        } elseif ($event_start === "") {
            $error = "請填寫活動開始時間。";
        } elseif ($location === "") {
            $error = "請填寫活動地點。";
        } elseif ($organizer === "") {
            $error = "請填寫主辦單位。";
        } elseif ($target === "") {
            $error = "請填寫活動對象。";
        } elseif ($signup_deadline === "") {
            $error = "請填寫報名截止日期。";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO activities
                    (user_id, title, description, event_start, event_end, location, organizer, fee, target, signup_deadline)
                VALUES
                    (:uid, :title, :desc, :start, :end, :loc, :org, :fee, :target, :deadline)
            ");
            $stmt->execute([
                ":uid"      => $_SESSION["user_id"] ?? 0,
                ":title"    => $title,
                ":desc"     => $description,
                ":start"    => $event_start,
                ":end"      => $event_end !== "" ? $event_end : null,
                ":loc"      => $location,
                ":org"      => $organizer,
                ":fee"      => $fee !== "" ? $fee : "免費",
                ":target"   => $target,
                ":deadline" => $signup_deadline,
            ]);
            $new_id = $pdo->lastInsertId();



$activity_id = $pdo->lastInsertId();

$activityUrl = "http://localhost/SA-club/kaira-1.0.0/activity_view.php?id=" . $activity_id;

$eventKey = "club_activity_created_" . $activity_id . "_" . date("YmdHis");

$subject = "你訂閱的社團發布了新活動";

$body = "
<h2>社團新活動通知</h2>
<p>你訂閱的社團發布了新活動：<strong>{$title}</strong></p>
<p>請點擊下方按鈕查看活動內容。</p>
<a href='{$activityUrl}'>
查看活動
</a>
";

$clubStmt = $pdo->prepare("
    SELECT c.id
    FROM clubs c
    JOIN activities a ON a.user_id = c.user_id
    WHERE a.id = ?
    LIMIT 1
");

$clubStmt->execute([$activity_id]);
$club = $clubStmt->fetch(PDO::FETCH_ASSOC);


$activityUrl = "http://localhost/SA-club/kaira-1.0.0/activity_view.php?id=" . $activity_id;
$eventKey = "club_activity_created_" . $activity_id . "_" . date("YmdHis");

notifyClubSubscribers(
    $pdo,
    $club["id"],
    $activity_id,
    $subject,
    $body,
    "club_activity_created",
    $activityUrl,
    $eventKey
);




if ($club) {
    notifyClubSubscribers(
        $pdo,
        $club["id"],
        $activity_id,
        $subject,
        $body,
        "club_activity_created",
        $activityUrl,
        $eventKey
    );
}



            header("Location: activity_view.php?id=" . $new_id);
            exit;
        }
    }

} catch (PDOException $e) {
    die("資料庫連線失敗：" . $e->getMessage());
}

// 保留表單輸入值
$f = [
    "title"           => $_POST["title"]           ?? "",
    "description"     => $_POST["description"]     ?? "",
    "event_start"     => $_POST["event_start"]     ?? "",
    "event_end"       => $_POST["event_end"]       ?? "",
    "location"        => $_POST["location"]        ?? "",
    "organizer"       => $_POST["organizer"]       ?? $organizer_default,
    "fee"             => $_POST["fee"]             ?? "",
    "target"          => $_POST["target"]          ?? "",
    "signup_deadline" => $_POST["signup_deadline"] ?? "",
];

require_once "header.php";
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>發佈活動 — 輔大社團平台</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;600;700&family=Microsoft+JhengHei&display=swap" rel="stylesheet">

  <style>
    :root {
      --footer-bg: #1a2744;
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

    .create-wrapper {
      flex: 1;
      display: flex;
      align-items: flex-start;
      justify-content: center;
      padding: 48px 16px 60px;
    }

    .create-card {
      width: 100%;
      max-width: 780px;
      background: #fff;
      border-radius: 18px;
      box-shadow: var(--card-shadow);
      overflow: hidden;
    }

    .create-card-header {
      background: #2d3a4a;
      color: #fff;
      padding: 36px 48px 28px;
      text-align: center;
    }

    .create-card-header .logo-text {
      font-family: "Noto Serif TC", serif;
      font-size: 22px;
      font-weight: 700;
      letter-spacing: 3px;
      margin-bottom: 6px;
    }

    .create-card-header .subtitle {
      font-size: 13px;
      opacity: 0.72;
      letter-spacing: 1px;
    }

    .create-card-body { padding: 40px 48px 48px; }

    /* ── Section title ── */
    .section-label {
      font-size: 11px;
      font-weight: 700;
      color: #9aa;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      margin-bottom: 16px;
      margin-top: 32px;
      padding-bottom: 8px;
      border-bottom: 1px solid #eef1f5;
    }

    .section-label:first-of-type { margin-top: 0; }

    /* ── Form elements ── */
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
      font-family: inherit;
    }

    .form-control:focus,
    .form-select:focus {
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
      font-size: 15px;
    }

    .input-group .form-control {
      border-left: none;
      border-radius: 0 8px 8px 0;
    }

    .input-group:focus-within .input-group-text { border-color: #6e8ab0; }

    .hint-text {
      font-size: 11px;
      color: #aab;
      margin-top: 5px;
    }

    /* ── Two-column row ── */
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }

    @media (max-width: 600px) {
      .form-row { grid-template-columns: 1fr; }
      .create-card-body { padding: 28px 24px 36px; }
      .create-card-header { padding: 28px 24px 20px; }
    }

    /* ── Alert ── */
    .alert-error {
      background: #fdf0ef;
      border: 1px solid #f0c4c0;
      color: var(--error-color);
      border-radius: 8px;
      padding: 12px 16px;
      font-size: 13px;
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 24px;
    }

    /* ── Divider ── */
    .divider {
      border: none;
      border-top: 1px solid #e8ecf0;
      margin: 32px 0;
    }

    /* ── Buttons ── */
    .btn-submit {
      background: var(--btn-bg);
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 14px;
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

    .btn-submit:active { transform: translateY(0); }

    .btn-cancel {
      display: block;
      text-align: center;
      margin-top: 14px;
      font-size: 13px;
      color: #778;
      text-decoration: none;
    }

    .btn-cancel:hover { color: #3a3a3a; }

    /* ── Readonly row ── */
    .readonly-row {
      display: flex;
      align-items: center;
      gap: 12px;
      background: #f4f6f9;
      border: 1.5px solid var(--input-border);
      border-radius: 8px;
      padding: 10px 14px;
    }

    .readonly-icon {
      color: #7a8a9a;
      font-size: 15px;
      flex-shrink: 0;
    }

    .readonly-value {
      font-size: 14px;
      font-weight: 600;
      color: #2d3a4a;
    }

    /* ── Not logged in ── */
    .no-permission {
      text-align: center;
      padding: 60px 40px;
      color: #aab;
    }

    .no-permission i { font-size: 48px; display: block; margin-bottom: 14px; }

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

<div class="create-wrapper">
  <div class="create-card">

    <div class="create-card-header">
      <div class="logo-text">發佈活動</div>
      <div class="subtitle">天主教輔仁大學 社團平台</div>
    </div>

    <?php if (empty($_SESSION["user_id"])): ?>
      <div class="no-permission">
        <i class="bi bi-lock-fill"></i>
        請先登入社團帳號才能發佈活動。<br>
        <a href="login.php" style="color:#6e8ab0; font-weight:600; text-decoration:none; margin-top:12px; display:inline-block;">前往登入</a>
      </div>

    <?php else: ?>
      <div class="create-card-body">

        <?php if ($error): ?>
          <div class="alert-error">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="activity_create.php" autocomplete="off">

          <!-- 基本資訊 -->
          <div class="section-label">基本資訊</div>

          <div class="mb-4">
            <label class="form-label">活動名稱 <span style="color:#c0392b;">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
              <input type="text" class="form-control" name="title"
                     placeholder="請輸入活動名稱"
                     value="<?= htmlspecialchars($f["title"]) ?>"
                     maxlength="255" required>
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label">活動簡介 <span style="color:#c0392b;">*</span></label>
            <div class="input-group align-items-start">
              <span class="input-group-text" style="padding-top:11px;"><i class="bi bi-text-paragraph"></i></span>
              <textarea class="form-control" name="description"
                        placeholder="請輸入活動詳細介紹內容"
                        required><?= htmlspecialchars($f["description"]) ?></textarea>
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label">主辦單位</label>
            <div class="readonly-row">
              <div class="readonly-icon"><i class="bi bi-building"></i></div>
              <div class="readonly-value"><?= htmlspecialchars($f["organizer"]) ?></div>
            </div>
            <input type="hidden" name="organizer" value="<?= htmlspecialchars($f["organizer"]) ?>">
            <div class="hint-text">主辦單位依登入帳號自動帶入，不可修改。</div>
          </div>

          <!-- 時間地點 -->
          <div class="section-label">時間與地點</div>

          <div class="form-row mb-4">
            <div>
              <label class="form-label">活動開始時間 <span style="color:#c0392b;">*</span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-clock"></i></span>
                <input type="datetime-local" class="form-control" name="event_start"
                       value="<?= htmlspecialchars($f["event_start"]) ?>" required>
              </div>
            </div>
            <div>
              <label class="form-label">活動結束時間</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-clock-history"></i></span>
                <input type="datetime-local" class="form-control" name="event_end"
                       value="<?= htmlspecialchars($f["event_end"]) ?>">
              </div>
              <div class="hint-text">可留空</div>
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label">活動地點 <span style="color:#c0392b;">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
              <input type="text" class="form-control" name="location"
                     placeholder="請輸入活動地點"
                     value="<?= htmlspecialchars($f["location"]) ?>"
                     required>
            </div>
          </div>

          <!-- 報名資訊 -->
          <div class="section-label">報名資訊</div>

          <div class="form-row mb-4">
            <div>
              <label class="form-label">活動費用 <span style="color:#c0392b;">*</span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-currency-dollar"></i></span>
                <input type="text" class="form-control" name="fee"
                       placeholder="例：免費 / NT$200"
                       value="<?= htmlspecialchars($f["fee"]) ?>">
              </div>
              <div class="hint-text">留空則預設為「免費」</div>
            </div>
            <div>
              <label class="form-label">報名截止日期 <span style="color:#c0392b;">*</span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-calendar-x"></i></span>
                <input type="date" class="form-control" name="signup_deadline"
                       value="<?= htmlspecialchars($f["signup_deadline"]) ?>" required>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">活動對象 <span style="color:#c0392b;">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-people"></i></span>
              <input type="text" class="form-control" name="target"
                     placeholder="例：全校學生 / 限本系學生 / 不限"
                     value="<?= htmlspecialchars($f["target"]) ?>"
                     required>
            </div>
          </div>

          <hr class="divider">

          <button type="submit" class="btn-submit">
            <i class="bi bi-megaphone me-2"></i>發佈活動
          </button>

        </form>

        <a href="index.php" class="btn-cancel">
          <i class="bi bi-arrow-left me-1"></i>取消，返回首頁
        </a>

      </div>
    <?php endif; ?>

  </div>
</div>

<?php require "footer.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>