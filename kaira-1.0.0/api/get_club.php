<?php
// 1. 確保沒有任何錯誤訊息干擾 JSON 格式
error_reporting(0); 
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

$host = 'localhost';
$db   = 'sa2026'; 
$user = 'root'; 
$pass = ''; // XAMPP 留空，MAMP 填 root

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 2. 抓取所有社團資料
    $stmt = $pdo->query("SELECT id, name, category, image, description FROM clubs");
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. 只輸出 JSON 資料
    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>