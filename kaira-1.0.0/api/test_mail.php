<?php

require_once __DIR__ . "/notification_helper.php";

$body = "
<h2>測試信</h2>

<p>
<a href='https://google.com'>
Google
</a>
</p>
";

$result = sendNotificationMail(
    "momo930210@gmail.com",
    "測試連結",
    $body
);

var_dump($result);