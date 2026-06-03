<?php
date_default_timezone_set("Asia/Taipei");

require_once __DIR__ . "/../api/db.php";
require_once __DIR__ . "/../api/notification_service.php";

$stmt = $pdo->prepare("
    SELECT
        id,
        title,
        event_start
    FROM activities
    WHERE event_start >= TIMESTAMP(CURDATE() + INTERVAL 1 DAY)
    AND event_start < TIMESTAMP(CURDATE() + INTERVAL 2 DAY)
");

$stmt->execute();
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($activities as $activity) {
    $activity_id = $activity["id"];

    $subject = "活動即將開始";

    $body = "
        <h2>活動即將開始</h2>
        <p>你報名的活動「{$activity['title']}」將於 {$activity['event_start']} 開始。</p>
        <p>請按下方按鈕查看活動詳情。</p>
        <a href='{$activityUrl}'>
            查看活動
        </a>
    ";

    $eventKey =
        "activity_start_" .
        $activity_id .
        "_" .
        date("Ymd");

    notifyActivityFormSubmitters(
        $pdo,
        $activity_id,
        $subject,
        $body,
        "activity_start_reminder",
        $eventKey
    );
}

echo "activity start reminder finished";


