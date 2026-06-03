<?php
session_start();

require_once "api/db.php";

if (!isset($_SESSION["user_id"])) {
    exit("請先登入");
}

$user_id = $_SESSION["user_id"];

$stmt = $pdo->prepare("
    SELECT *
    FROM notification_logs
    WHERE user_id = ?
    ORDER BY sent_at DESC
");

$stmt->execute([$user_id]);

$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);



?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>

<meta charset="UTF-8">

<title>通知中心</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background:#f5f7fb;
}

.notification-card{

    background:white;

    border-radius:16px;

    padding:16px;

    margin-bottom:14px;

    cursor:pointer;

    transition:.2s;

    border:1px solid #edf0f5;
}

.notification-card:hover{

    transform:translateY(-2px);

    box-shadow:0 6px 20px rgba(0,0,0,.06);
}

.notification-unread{

    border-left:5px solid #2563eb;
}

.notification-time{

    font-size:12px;

    color:#94a3b8;
}

.notification-type{

    display:inline-block;

    padding:4px 8px;

    border-radius:999px;

    font-size:12px;

    background:#eff6ff;

    color:#2563eb;

    margin-bottom:8px;
}

</style>

</head>
<body>
<?php require_once "header.php"; ?>
<class="notification-page">
<h2 class="mb-4">通知中心</h2>

<?php if (count($notifications) > 0): ?>

    <?php foreach($notifications as $n): ?>

        <div
            class="notification-card <?= !$n['is_read'] ? 'notification-unread' : '' ?>"
            data-id="<?= $n['id'] ?>"
            data-title="<?= htmlspecialchars($n['title']) ?>"
            data-content="<?= htmlspecialchars($n['content']) ?>"
            data-url="<?= htmlspecialchars($n['url'] ?? '') ?>"
        >

            <div class="notification-type">
                <?= htmlspecialchars($n['notification_type']) ?>
            </div>

            <h5 class="notification-title">
                <?= htmlspecialchars($n['title']) ?>
            </h5>

            <div class="notification-time">
                <?= htmlspecialchars($n['sent_at']) ?>
            </div>

        </div>

    <?php endforeach; ?>

<?php else: ?>

    <div class="empty-notification">

        <div class="empty-icon">
            🔔
        </div>

        <div class="empty-title">
            尚未收到通知
        </div>

        <div class="empty-text">
            當活動更新、公告發布或社團有新消息時，
            通知將會顯示在這裡。
        </div>

    </div>

<?php endif; ?>

<!-- Modal -->

<div class="modal fade" id="notificationModal" tabindex="-1">

<div class="modal-dialog modal-dialog-centered">

<div class="modal-content">

<div class="modal-header">

<h5 class="modal-title" id="modalTitle"></h5>

<button type="button" class="btn-close" data-bs-dismiss="modal"></button>

</div>

<div class="modal-body" id="modalContent"></div>

</div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>

const modal = new bootstrap.Modal(
    document.getElementById('notificationModal')
);

document.querySelectorAll('.notification-card').forEach(card => {

    card.addEventListener('click', () => {

        document.getElementById('modalTitle').innerText =
            card.dataset.title;

let html = "";

if (card.dataset.content) {
    html += `
        <p>${card.dataset.content}</p>
    `;
}

if (card.dataset.url) {
    html += `
        <a href="${card.dataset.url}" class="btn btn-primary">
            查看活動
        </a>
    `;
}

document.getElementById("modalContent").innerHTML = html;

        modal.show();

        fetch('api/read_notification.php', {

            method:'POST',

            headers:{
                'Content-Type':'application/x-www-form-urlencoded'
            },

            body:'id=' + card.dataset.id

        });

        card.classList.remove('notification-unread');

    });

});

</script>

</main>
<?php require_once "footer.php"; ?>
</body>

</html>

