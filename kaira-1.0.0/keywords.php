<?php
header('Content-Type: application/json; charset=utf-8');
require_once "api/db.php";
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->query("SELECT keyword FROM keywords ORDER BY id ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(['熱舞社', '吉他社', '系學會', '服務學習', '排球社'], JSON_UNESCAPED_UNICODE);
}