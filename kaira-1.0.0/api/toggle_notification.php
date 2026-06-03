<?php
session_start();
require_once __DIR__ . "/db.php";

header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["user_id"])) {
    echo json_encode([
        "success" => false,
        "message" => "請先登入"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_id = $_SESSION["user_id"];

$stmt = $pdo->prepare("
    SELECT notification_enabled
    FROM users
    WHERE user_id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode([
        "success" => false,
        "message" => "找不到使用者"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$newStatus = !empty($user["notification_enabled"]) ? 0 : 1;

$stmt = $pdo->prepare("
    UPDATE users
    SET notification_enabled = ?
    WHERE user_id = ?
");
$stmt->execute([$newStatus, $user_id]);

echo json_encode([
    "success" => true,
    "notification_enabled" => $newStatus
]);

exit;


