

<?php

session_start();

require_once "db.php";

$id = $_POST["id"] ?? 0;

$stmt = $pdo->prepare("
    UPDATE notification_logs
    SET is_read = 1
    WHERE id = ?
");

$stmt->execute([$id]);

echo json_encode([
    "success" => true
]);