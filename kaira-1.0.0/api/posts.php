<?php
header("Content-Type: application/json; charset=UTF-8");
require_once "db.php";

$type = $_GET["type"] ?? "";

if ($type !== "hot" && $type !== "recommended") {
    echo json_encode(["message" => "type 參數錯誤"]);
    exit;
}

$sql = "SELECT id, club_name, title, description, image,
        DATE_FORMAT(date, '%Y/%m/%d') AS date
        FROM posts
        WHERE type = :type
        ORDER BY date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([":type" => $type]);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($data, JSON_UNESCAPED_UNICODE);
?>