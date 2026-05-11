<?php
session_start();
require_once "db.php";

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '請先登入才能訂閱'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$club_id = (int)($_POST['club_id'] ?? 0);

if (!$club_id) {
    echo json_encode(['success' => false, 'message' => '參數錯誤'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 確認社團存在
$stmt = $pdo->prepare("SELECT id FROM clubs WHERE id = ?");
$stmt->execute([$club_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => '找不到該社團'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 檢查是否已訂閱
$stmt = $pdo->prepare("SELECT id FROM subscriptions WHERE user_id = ? AND club_id = ?");
$stmt->execute([$user_id, $club_id]);

if ($stmt->fetch()) {
    // 已訂閱 → 取消
    $pdo->prepare("DELETE FROM subscriptions WHERE user_id = ? AND club_id = ?")
        ->execute([$user_id, $club_id]);
    echo json_encode(['success' => true, 'subscribed' => false, 'message' => '已取消訂閱'], JSON_UNESCAPED_UNICODE);
} else {
    // 未訂閱 → 訂閱
    $pdo->prepare("INSERT INTO subscriptions (user_id, club_id) VALUES (?, ?)")
        ->execute([$user_id, $club_id]);
    echo json_encode(['success' => true, 'subscribed' => true, 'message' => '訂閱成功'], JSON_UNESCAPED_UNICODE);
}