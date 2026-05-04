<?php
$host = "localhost";
$dbname = "sa2026";
$username = "root";
<<<<<<< HEAD
$password = "";

=======
$password = "12345678"; 
>>>>>>> ff9d9d8dfc7e99533a15e6cde67f9a611bbc9300
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