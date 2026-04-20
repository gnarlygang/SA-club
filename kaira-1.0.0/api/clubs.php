<?php
require_once "../db.php";

header("Content-Type: application/json; charset=utf-8");

$keyword  = $_GET['keyword']  ?? '';
$category = $_GET['category'] ?? '';

$sql    = "SELECT * FROM clubs WHERE 1=1";
$params = [];

if ($keyword !== '') {
    $sql     .= " AND (name LIKE :kw1 OR description LIKE :kw2 OR category LIKE :kw3)";
    $like     = "%{$keyword}%";
    $params[':kw1'] = $like;
    $params[':kw2'] = $like;
    $params[':kw3'] = $like;
}

if ($category !== '') {
    $sql .= " AND category = :category";
    $params[':category'] = $category;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($clubs, JSON_UNESCAPED_UNICODE);