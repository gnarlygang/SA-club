<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . "/../vendor/autoload.php";

function sendNotificationMail($to, $subject, $body)
{
    $config = require __DIR__ . "/mail_config.php";

    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();

        $mail->Host = $config['host'];

        $mail->SMTPAuth = true;

        $mail->Username = $config['username'];

        $mail->Password = $config['password'];

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = $config['port'];

        $mail->CharSet = "UTF-8";

        $mail->setFrom(
            $config['from_email'],
            $config['from_name']
        );

        $mail->addAddress($to);

        $mail->isHTML(true);

        $mail->Subject = $subject;

        $mail->Body = $body;

        $result = $mail->send();



return $result;

    } catch (Exception $e) {
    echo "<pre>";
    echo "Mailer Error: " . $mail->ErrorInfo;
    echo "</pre>";
    return false;
}
}