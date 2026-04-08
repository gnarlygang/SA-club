<?php
header("Content-Type: application/json; charset=UTF-8");
require_once "db.php";

try {
    $sql = "SELECT keyword FROM keywords ORDER BY id DESC LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($data, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode([
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>