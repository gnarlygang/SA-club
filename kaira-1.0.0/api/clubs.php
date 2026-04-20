<?php
// api/clubs.php
require_once "../db_connect.php"; // 確保你有連線到資料庫

$keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';

// 準備 SQL：比對社團名稱 (name)、類別 (category) 或 描述 (description)
$sql = "SELECT * FROM clubs WHERE name LIKE :kw OR category LIKE :kw OR description LIKE :kw";
$stmt = $pdo->prepare($sql);
$searchTerm = "%$keyword%"; // 前後加上 % 進行模糊匹配
$stmt->execute(['kw' => $searchTerm]);
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($clubs);
?>