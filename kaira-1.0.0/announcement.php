<?php
require_once "api/db.php"; 
require_once "api/notification_service.php";

// --- 處理邏輯區 ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');
$detail = trim($_POST['detail'] ?? '');

if (!empty($title) && !empty($content) && !empty($detail)) {
    try {
        $sql = "INSERT INTO announcements (title, content, detail, date) 
                VALUES (:title, :content, :detail, NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':title' => $title,
            ':content' => $content,
            ':detail' => $detail
        ]);

        $announcement_id = $pdo->lastInsertId();

        $subject = "系統公告新增通知";

        $body = "
            <h2>系統公告新增</h2>
            <p><strong>{$title}</strong></p>
            <p>{$content}</p>
        ";

        $announcementUrl = "http://localhost/SA-club/kaira-1.0.0/announcement_detail.php?id=" . $announcement_id;

$eventKey = "announcement_created_" . $announcement_id . "_" . date("YmdHis");

notifyAllUsers(
    $pdo,
    "announcement",
    $announcement_id,
    $subject,
    $body,
    "announcement_created",
    $announcementUrl,
    $eventKey
);

        echo "<script>alert('✅ 公告已成功發佈！'); window.location.href = 'index.php';</script>";
        exit;

    } catch (PDOException $e) {
        $error_msg = "資料庫錯誤：" . $e->getMessage();
    }
}
}

?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>發佈公告 - Management Console</title>
    <link rel="stylesheet" href="css/ann.css">
</head>
<body>
<?php require_once "header.php"; ?>
<div class="full-screen-wrapper">
    <div class="glass-card">
        <div class="text-center">
            <h2 class="card-title text-uppercase">
                <i class="bi bi-shield-lock-fill me-2" style="color: #00d4ff;"></i> 
                Management Console
            </h2>
        </div>

        <form action="announcement.php" method="POST">
    <div class="mb-4">
        <label>
            <i class="bi bi-type-h1 me-2"></i>公告標題
        </label>
        <input 
            type="text" 
            name="title" 
            class="form-control" 
            placeholder="請輸入標題內容..." 
            required
        >
    </div>

    <div class="mb-4">
        <label>
            <i class="bi bi-card-text me-2"></i>公告摘要
        </label>
        <textarea 
            name="content" 
            class="form-control" 
            rows="4" 
            placeholder="請輸入公告摘要內容..." 
            style="resize: vertical;" 
            required
        ></textarea>
    </div>

    <div class="mb-4">
        <label>
            <i class="bi bi-justify-left me-2"></i>公告詳情
        </label>
        <textarea 
            name="detail" 
            class="form-control detail-control" 
            rows="8" 
            placeholder="請輸入詳細公告內容..." 
            style="resize: vertical;" 
            required
        ></textarea>
    </div>

    <button type="submit" class="btn-submit">
        發佈公告 <i class="bi bi-send-check-fill ms-2"></i>
    </button>
    
    <div class="btn-back-container">
        <a href="index.php" class="btn-back">
            <i class="bi bi-chevron-left"></i> 返回後台首頁
        </a>
    </div>
</form>
    </div>
</div>
<?php require_once "footer.php"; ?>
</body>
</html>