<?php
header("Content-Type: application/json; charset=UTF-8");
require_once "db.php";

$input = json_decode(file_get_contents("php://input"), true);

$email = trim($input["email"] ?? "");
$message = trim($input["message"] ?? "");

if ($email === "" || $message === "") {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Email 和意見內容不能為空"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$sql = "INSERT INTO feedbacks (email, message) VALUES (:email, :message)";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ":email" => $email,
    ":message" => $message
]);

echo json_encode([
    "success" => true,
    "message" => "意見已成功送出，謝謝你的回饋！"
], JSON_UNESCAPED_UNICODE);
?>