<?php
date_default_timezone_set("Asia/Taipei");

require_once __DIR__ . "/../api/db.php";
require_once __DIR__ . "/../api/notification_service.php";

$stmt = $pdo->prepare("
    SELECT id, title, signup_deadline, user_id
    FROM activities
    WHERE DATE(signup_deadline) = CURDATE() + INTERVAL 1 DAY
");
$stmt->execute();
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($activities as $activity) {
    $activity_id = $activity['id'];

    $activityUrl = "http://localhost/SA-club/kaira-1.0.0/activity_view.php?id=" . $activity_id;

    $subject = "活動報名即將截止";

    $body = "
        <h2>活動報名即將截止</h2>
        <p>活動「{$activity['title']}」將於 {$activity['signup_deadline']} 截止報名。</p>
        <p>請點擊下方按鈕查看活動詳情。</p>
        <a href='{$activityUrl}'>
查看活動
</a>
    ";

    // 1. 通知收藏此活動的人
    $favoriteEventKey = "activity_deadline_" . $activity_id . "_" . date("Ymd");

    notifyActivityFavorites(
        $pdo,
        $activity_id,
        $subject,
        $body,
        "activity_deadline",
        $favoriteEventKey
    );

    // 2. 找出發布此活動的社團
    // activities.user_id = clubs.user_id
    $clubStmt = $pdo->prepare("
        SELECT id
        FROM clubs
        WHERE user_id = ?
        LIMIT 1
    ");
    $clubStmt->execute([$activity['user_id']]);
    $club = $clubStmt->fetch(PDO::FETCH_ASSOC);

    // 3. 通知訂閱該社團的人
    if ($club) {
        $clubEventKey = "club_activity_deadline_" . $activity_id . "_" . date("Ymd");

        notifyClubSubscribers(
            $pdo,
            $club['id'],
            $activity_id,
            $subject,
            $body,
            "club_activity_deadline",
            $activityUrl,
            $clubEventKey
        );
    }
}

echo "deadline check finished";