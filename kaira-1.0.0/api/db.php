<?php
$host = "localhost";
$dbname = "sa2026";
$username = "root";
<<<<<<< HEAD
$password = "";
=======
$password = "12345678";
>>>>>>> b0fe645f0c43735b28676557aa16510481ffe689
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