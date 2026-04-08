<?php
header("Content-Type: application/json; charset=UTF-8");
require_once "db.php";

$sql = "SELECT id, title, content, DATE_FORMAT(date, '%Y/%m/%d') AS date
        FROM announcements
        ORDER BY date DESC";

$stmt = $pdo->query($sql);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($data, JSON_UNESCAPED_UNICODE);
?>