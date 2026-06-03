<?php

session_start();

require_once "db.php";

header("Content-Type: application/json");

if (!isset($_SESSION['user_id'])) {

    echo json_encode([
        "success" => false
    ]);

    exit;

}

$stmt = $pdo->prepare("
    UPDATE users
    SET notification_enabled = 1
    WHERE user_id = ?
");

$stmt->execute([
    $_SESSION['user_id']
]);

echo json_encode([
    "success" => true
]);