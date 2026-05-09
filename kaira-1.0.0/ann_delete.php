<?php
session_start();
require_once "api/db.php";

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 4;

if (!$isAdmin) {
    die("權限不足，只有管理員可以刪除公告。");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("不允許的請求方式。");
}

$id = $_POST['id'] ?? null;

if (!$id || !is_numeric($id)) {
    die("公告 ID 不正確");
}

try {
    $sql = "DELETE FROM announcements
            WHERE id = :id
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id' => $id
    ]);

    header("Location: index.php");
    exit;

} catch (PDOException $e) {
    die("資料庫錯誤：" . $e->getMessage());
}