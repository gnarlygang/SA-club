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

    $stmt = $pdo->prepare("
    SELECT role
    FROM users
    WHERE user_id = ?
");
$stmt->execute([$user_id]);

$role = $stmt->fetchColumn();

if ($role == 4) {
    return false;
}

    $email = trim((string)$email);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        debugLog("略過無效 email：user_id={$user_id}, email={$email}");
        return false;
    }

    $roleStmt = $pdo->prepare("
        SELECT role
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ");

    $roleStmt->execute([$user_id]);
    $userRole = (int)$roleStmt->fetchColumn();

    if ($userRole === 2) {
        $allowedForClub = [
            "announcement_created",
            "announcement_updated",
            "forum_mention"
        ];

        if (!in_array($notification_type, $allowedForClub, true)) {
            debugLog("社團帳號略過通知：user_id={$user_id}, type={$notification_type}");
            return false;
        }
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

    $contentText = trim(strip_tags($body));

$contentText = preg_replace(
    '/\s*查看活動\s*$/u',
    '',
    $contentText
);

$contentText = trim($contentText);

$log->execute([
    $user_id,
    $notification_type,
    $target_type,
    $target_id,
    $event_key,
    $subject,
    $contentText,
    $url
]);

 
    return true;

} catch (PDOException $e) {

    file_put_contents(
        __DIR__ . "/notification_error.log",
        date("Y-m-d H:i:s") .
        " | notification_logs 寫入失敗 | " .
        $e->getMessage() .
        PHP_EOL,
        FILE_APPEND
    );

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

$announcement_id = $target_id;
$announcementUrl = $url;

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

function notifyMentionedUsers($pdo, $content, $post_id, $comment_id, $sender_id)
{
    preg_match_all('/@([A-Za-z0-9_\x{4e00}-\x{9fa5}]+)/u', $content, $matches);

    if (empty($matches[1])) {
        return false;
    }

    $mentionNames = array_unique($matches[1]);

    $placeholders = implode(',', array_fill(0, count($mentionNames), '?'));

    $stmt = $pdo->prepare("
    SELECT
        user_id,
        username,
        nickname,
        email
    FROM users
    WHERE role <> 4
    AND notification_enabled = 1
    AND email IS NOT NULL
    AND TRIM(email) <> ''
    AND (
        nickname IN ($placeholders)
        OR username IN ($placeholders)
    )
");

    $stmt->execute(array_merge($mentionNames, $mentionNames));
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$users) {
        return false;
    }

    $postStmt = $pdo->prepare("
        SELECT title
        FROM forum_posts
        WHERE id = ?
        LIMIT 1
    ");
    $postStmt->execute([$post_id]);
    $post = $postStmt->fetch(PDO::FETCH_ASSOC);

    $senderStmt = $pdo->prepare("
        SELECT username, nickname
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ");
    $senderStmt->execute([$sender_id]);
    $sender = $senderStmt->fetch(PDO::FETCH_ASSOC);

    $postTitle = $post ? $post["title"] : "論壇貼文";
    $senderName = $sender ? ($sender["nickname"] ?: $sender["username"]) : "某位使用者";

    $url = "http://localhost/SA-club/kaira-1.0.0/forum_post.php?id=" . $post_id . "#comments";

    foreach ($users as $user) {
        if ((int)$user["user_id"] === (int)$sender_id) {
            continue;
        }

        $subject = "你在論壇中被提及";

        $body = "
            <h2>你在論壇中被提及</h2>
            <p>{$senderName} 在貼文「{$postTitle}」中提到了你。</p>
            <p>請點擊下方按鈕查看內容。</p>
            <a href='{$url}'>
查看活動
</a>
        ";

        $eventKey = "mention_comment_" . $comment_id . "_" . $user["user_id"];

        sendOnce(
    $pdo,
    $user["user_id"],
    $user["email"],
    "forum_mention",
    "forum_post",
    $post_id,
    $subject,
    $body,
    $url,
    $eventKey
);
    }

    return true;
}