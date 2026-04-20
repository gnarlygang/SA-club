<?php
$host     = "localhost";
$dbname   = "sa2026";    // 1. 這裡改成妳剛建好的資料庫名稱
$username = "root";
$password = "";          // 2. XAMPP 預設密碼通常是空的，除非妳有設 12345678

try {
    // 3. 這裡加入 ;port=3307，因為妳的 MySQL 跑在 3307
    $pdo = new PDO(
        "mysql:host=$host;port=3307;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // 設定回傳為 JSON 格式，方便前端 index.html 顯示錯誤
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "success" => false,
        "message" => "資料庫連線失敗：" . $e->getMessage()
    ]);
    exit;
}
?>