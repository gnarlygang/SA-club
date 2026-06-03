<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/notification_helper.php";

function debugLog($message)
{
    $file = __DIR__ . "/notification_debug.log";
    $time = date("Y-m-d H:i:s");
    file_put_contents($file, "[$time] " . $message . PHP_EOL, FILE_APPEND);
}

function isValidEmail($email)
{
    $email = trim((string)$email);

    if ($email === '') {
        return false;
    }

    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function sendOnce(
    $pdo,
    $user_id,
    $email,
    $notification_type,
    $target_type,
    $target_id,
    $subject,
    $body,
    $url = null,
    $event_key = null
) {
    $email = trim((string)$email);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        debugLog("略過無效 email：user_id={$user_id}, email={$email}");
        return false;
    }

    if ($event_key === null) {
        $event_key = $notification_type . "_" . $target_type . "_" . $target_id . "_" . date("YmdHis");
    }

    if (trim((string)$subject) === '' || trim(strip_tags((string)$body)) === '') {
        debugLog("略過空白通知：user_id={$user_id}");
        return false;
    }

    $check = $pdo->prepare("
        SELECT id
        FROM notification_logs
        WHERE user_id = ?
        AND notification_type = ?
        AND target_type = ?
        AND target_id = ?
        AND event_key = ?
    ");
    $check->execute([
        $user_id,
        $notification_type,
        $target_type,
        $target_id,
        $event_key
    ]);

    if ($check->fetch()) {
        debugLog("略過重複通知：user_id={$user_id}, type={$notification_type}, target={$target_type}:{$target_id}, event={$event_key}");
        return false;
    }

    $sent = sendNotificationMail($email, $subject, $body);

    if ($sent) {
        try {
    $log = $pdo->prepare("
        INSERT INTO notification_logs
        (
            user_id,
            notification_type,
            target_type,
            target_id,
            event_key,
            title,
            content,
            url
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $log->execute([
        $user_id,
        $notification_type,
        $target_type,
        $target_id,
        $event_key,
        $subject,
        trim(strip_tags($body)),
        $url
    ]);

    echo "notification_logs 寫入成功";
    return true;

} catch (PDOException $e) {
    echo "<pre>";
    echo "notification_logs 寫入失敗：";
    echo $e->getMessage();
    echo "</pre>";
    return false;
}

        debugLog("通知成功：user_id={$user_id}, email={$email}, type={$notification_type}");
        return true;
    }

    debugLog("寄送失敗：user_id={$user_id}, email={$email}, type={$notification_type}");
    return false;
}

function notifyActivityFavorites($pdo, $activity_id, $subject, $body, $notification_type, $event_key = null)
{
    debugLog("進入 notifyActivityFavorites，activity_id={$activity_id}");

    $stmt = $pdo->prepare("
        SELECT u.user_id, u.email
        FROM favorites f
        JOIN users u ON f.user_id = u.user_id
        WHERE f.item_type = 'activity'
        AND f.item_id = ?
        AND u.email IS NOT NULL
        AND TRIM(u.email) <> ''
    ");
    $stmt->execute([$activity_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    debugLog("活動收藏通知人數：" . count($users));

    foreach ($users as $user) {
        $activityUrl =
    "http://localhost/SA-club/kaira-1.0.0/activity_view.php?id=" .
    $activity_id;

sendOnce(
    $pdo,
    $user['user_id'],
    $user['email'],
    $notification_type,
    "activity",
    $activity_id,
    $subject,
    $body,
    $activityUrl,
    $event_key
);
    }
}

function notifyClubSubscribers($pdo, $club_id, $target_id, $subject, $body, $notification_type, $url = null, $event_key = null)
{
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.email
        FROM subscriptions s
        JOIN users u ON s.user_id = u.user_id
        WHERE s.club_id = ?
        AND u.notification_enabled = 1
        AND u.email IS NOT NULL
        AND TRIM(u.email) <> ''
    ");

    $stmt->execute([$club_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as $user) {
        sendOnce(
            $pdo,
            $user['user_id'],
            $user['email'],
            $notification_type,
            "activity",
            $target_id,
            $subject,
            $body,
            $url,
            $event_key
        );
    }
}
function notifyAllUsers($pdo, $target_type, $target_id, $subject, $body, $notification_type, $url = null, $event_key = null)
{
    $stmt = $pdo->prepare("
    SELECT user_id, email
    FROM users
    WHERE notification_enabled = 1
    AND email IS NOT NULL
    AND TRIM(email) <> ''
    LIMIT 10
");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    debugLog("全站公告通知人數：" . count($users));

    foreach ($users as $user) {
        sendOnce(
    $pdo,
    $user['user_id'],
    $user['email'],
    $notification_type,
    "announcement",
    $announcement_id,
    $subject,
    $body,
    $announcementUrl,
    $event_key
);
    }
}


function notifyActivityFormSubmitters(
    $pdo,
    $activity_id,
    $subject,
    $body,
    $notification_type,
    $event_key = null
) {
    $activityUrl =
        "http://localhost/SA-club/kaira-1.0.0/activity_view.php?id=" .
        $activity_id;

    $stmt = $pdo->prepare("
        SELECT DISTINCT
            u.user_id,
            u.email
        FROM forms f
        JOIN form_submissions fs
            ON fs.form_id = f.id
        JOIN users u
            ON fs.user_id = u.user_id
        WHERE f.activity_id = ?
        AND u.notification_enabled = 1
        AND u.email IS NOT NULL
        AND TRIM(u.email) <> ''
    ");

    $stmt->execute([$activity_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as $user) {
        sendOnce(
            $pdo,
            $user['user_id'],
            $user['email'],
            $notification_type,
            "activity",
            $activity_id,
            $subject,
            $body,
            $activityUrl,
            $event_key
        );
    }
}