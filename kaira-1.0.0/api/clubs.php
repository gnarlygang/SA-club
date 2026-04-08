<?php
header("Content-Type: application/json; charset=UTF-8");
require_once "db.php";

$category = $_GET["category"] ?? "";
$keyword = $_GET["keyword"] ?? "";

$sql = "SELECT c.id, c.name, c.category, c.description, c.image,
        GROUP_CONCAT(ct.tag_name SEPARATOR '、') AS tags
        FROM clubs c
        LEFT JOIN club_tags ct ON c.id = ct.club_id
        WHERE 1=1";

$params = [];

if ($category !== "") {
    $sql .= " AND c.category = :category";
    $params[":category"] = $category;
}

if ($keyword !== "") {
    $sql .= " AND (
        c.name LIKE :keyword
        OR c.description LIKE :keyword
        OR c.category LIKE :keyword
        OR ct.tag_name LIKE :keyword
    )";
    $params[":keyword"] = "%$keyword%";
}

$sql .= " GROUP BY c.id ORDER BY c.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($data as &$club) {
    $club["tags"] = $club["tags"] ? explode("、", $club["tags"]) : [];
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
?>