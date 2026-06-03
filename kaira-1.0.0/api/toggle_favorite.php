<?php

ini_set('display_errors', 0);
error_reporting(E_ALL);
header("Content-Type: application/json; charset=utf-8");

session_start();
require_once "db.php";

header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        "success" => false,
        "message" => "請先登入後再收藏"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_id = $_SESSION['user_id'];
$item_type = $_POST['item_type'] ?? '';
$item_id = $_POST['item_id'] ?? null;

if (!$item_id || !$item_type) {
    echo json_encode([
        "success" => false,
        "message" => "缺少收藏資料"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array($item_type, ['activity', 'post'])) {
    echo json_encode([
        "success" => false,
        "message" => "收藏類型錯誤"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* 確認收藏的東西是否真的存在 */
if ($item_type === 'activity') {
    $stmt = $pdo->prepare("SELECT id FROM activities WHERE id = ?");
    $stmt->execute([$item_id]);
} else {
    $stmt = $pdo->prepare("SELECT id FROM forum_posts WHERE id = ?");
    $stmt->execute([$item_id]);
}

$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    echo json_encode([
        "success" => false,
        "message" => "找不到要收藏的內容"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* 檢查是否已收藏 */
$stmt = $pdo->prepare("
    SELECT id 
    FROM favorites 
    WHERE user_id = ? AND item_type = ? AND item_id = ?
");
$stmt->execute([$user_id, $item_type, $item_id]);
$favorite = $stmt->fetch(PDO::FETCH_ASSOC);

if ($favorite) {
    $stmt = $pdo->prepare("
        DELETE FROM favorites 
        WHERE user_id = ? AND item_type = ? AND item_id = ?
    ");
    $stmt->execute([$user_id, $item_type, $item_id]);

    echo json_encode([
        "success" => true,
        "favorited" => false,
        "message" => "已取消收藏"
    ], JSON_UNESCAPED_UNICODE);
} else {
    require_once "notification_helper.php";
    $stmt = $pdo->prepare("
        INSERT INTO favorites (user_id, item_type, item_id)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$user_id, $item_type, $item_id]);

    echo json_encode([
        "success" => true,
        "favorited" => true,
        "message" => "已加入收藏"
    ], JSON_UNESCAPED_UNICODE);

        /* 發送收藏成功通知 */
    $stmt = $pdo->prepare("SELECT email FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && !empty($user['email'])) {
        sendNotificationMail($user['email'], "favorite", [
            "item_type" => $type_text,
            "title" => $item['title']
        ]);
}
}




?>