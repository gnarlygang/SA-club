<?php
$host     = "localhost";
$dbname   = "sa2026";
$username = "root";
<<<<<<< HEAD
$password = "";
=======
$password = "A230736409";
>>>>>>> 09591eb088b074ab963e08f9986f2753863543b9
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