<?php
require_once "db.php";

header("Content-Type: application/json; charset=utf-8");

// 取得關鍵字
$keyword = $_GET['keyword'] ?? '';

// SQL 搜尋
$sql = "SELECT * FROM clubs 
        WHERE name LIKE :kw 
        OR category LIKE :kw 
        OR description LIKE :kw";

$stmt = $pdo->prepare($sql);

// 模糊搜尋
$searchTerm = "%{$keyword}%";

// 執行查詢
$stmt->execute(['kw' => $searchTerm]);

// 取得結果（這裡一定要 FETCH_ASSOC）
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 回傳 JSON
echo json_encode($clubs, JSON_UNESCAPED_UNICODE);
?>