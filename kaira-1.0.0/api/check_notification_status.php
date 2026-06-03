<?php

session_start();

require_once "db.php";

header("Content-Type: application/json");

if (!isset($_SESSION['user_id'])) {

    echo json_encode([
        "notification_enabled" => true
    ]);

    exit;

}

$stmt = $pdo->prepare("
    SELECT notification_enabled
    FROM users
    WHERE user_id = ?
");

$stmt->execute([
    $_SESSION['user_id']
]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    "notification_enabled" => (bool)$user['notification_enabled']
]);

