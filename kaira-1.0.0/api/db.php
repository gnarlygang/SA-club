<?php
$host     = "localhost";
$dbname   = "sa2026";
$username = "root";
$password = "A230736409";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "資料庫連線失敗：" . $e->getMessage()
    ]);
    exit;
}
?>