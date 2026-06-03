<?php
die("進入 update_activity.php");
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/notification_service.php";

/* 接收表單 */

$activity_id = $_POST['id'];

$title = $_POST['title'];

$content = $_POST['content'];


/* 更新活動 */

$stmt = $pdo->prepare("
    UPDATE activities
    SET title = ?,
        content = ?
    WHERE id = ?
");

$stmt->execute([
    $title,
    $content,
    $activity_id
]);

$subject = "你收藏的活動已更新";

$body = "
    <h2>活動更新通知</h2>
    <p>你收藏的活動「{$title}」已有更新。</p>
    <p>請回到 FJU CLUB 查看最新內容。</p>
";

notifyActivityFavorites(
    $pdo,
    $activity_id,
    $subject,
    $body,
    "activity_updated"
);
//訂閱通知
$stmt = $pdo->prepare("
    SELECT c.id AS club_id
    FROM activities a
    JOIN clubs c ON a.user_id = c.user_id
    WHERE a.id = ?
");
$stmt->execute([$activity_id]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if ($club) {
    notifyClubSubscribers(
        $pdo,
        $club['club_id'],
        $activity_id,
        $subject,
        $body,
        "club_activity_updated"
    );
}

/* ===== 這裡開始通知 ===== */


/* 找出收藏此活動的使用者 */

$stmt = $pdo->prepare("
    SELECT u.email
    FROM favorites f

    JOIN users u
    ON f.user_id = u.user_id

    WHERE f.item_type = 'activity'
    AND f.item_id = ?
");

$stmt->execute([$activity_id]);

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* 發送通知 */

foreach ($users as $user) {

    $subject = "你收藏的活動已更新";

    $body = "
        <h2>活動更新通知</h2>

        <p>你收藏的活動已有更新：</p>

        <div>
            <strong>{$title}</strong>
        </div>

        <br>

        <p>請回到網站查看最新內容。</p>
    ";

    sendNotificationMail(
        $user['email'],
        $subject,
        $body
    );
}

/* 返回 */

header("Location: activity_view.php?id=" . $activity_id);

exit;