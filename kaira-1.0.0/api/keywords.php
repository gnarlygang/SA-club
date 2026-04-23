<?php
header("Content-Type: application/json; charset=utf-8");
require_once "../db.php";

try {
    $stmt = $pdo->query("SELECT id, keyword FROM keywords ORDER BY id ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}