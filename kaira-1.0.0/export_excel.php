<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "api/db.php";

if (
    empty($_SESSION['role']) ||
    $_SESSION['role'] != 2
) {
    exit('無權限');
}

$formId = (int)($_GET['form_id'] ?? 0);

if (!$formId) {
    exit('缺少 form_id');
}

$userId = $_SESSION['user_id'];


/* 驗證是否自己的表單 */
$formStmt = $pdo->prepare("
SELECT
id,
title
FROM forms
WHERE id=?
AND creator_id=?
");

$formStmt->execute([
    $formId,
    $userId
]);

$form = $formStmt->fetch(PDO::FETCH_ASSOC);

if (!$form) {
    exit('找不到表單');
}


/* 檔名使用表單名稱 */
$fileName = trim($form['title']);

$fileName = preg_replace(
    '/[\\\\\\/:*?"<>|]/',
    '_',
    $fileName
);

if ($fileName === '') {
    $fileName = '匯出名單';
}


/* 表單欄位 */
$fieldStmt = $pdo->prepare("
SELECT
id,
label
FROM form_fields
WHERE form_id=?
ORDER BY sort_order
");

$fieldStmt->execute([
    $formId
]);

$formFields = $fieldStmt->fetchAll(PDO::FETCH_ASSOC);


/* 名單資料 */
$subStmt = $pdo->prepare("
SELECT
s.*,
u.username,
u.nickname,
u.email
FROM form_submissions s
JOIN users u
ON u.user_id=s.user_id
WHERE s.form_id=?
ORDER BY s.submitted_at ASC
");

$subStmt->execute([
    $formId
]);

$submissions = $subStmt->fetchAll(PDO::FETCH_ASSOC);



header("Content-Type: application/vnd.ms-excel; charset=UTF-8");

header(
'Content-Disposition: attachment; filename="' .
rawurlencode($fileName) .
'.xls"; filename*=UTF-8\'\'' .
rawurlencode($fileName) .
'.xls'
);

echo chr(239).chr(187).chr(191);



/* 標題列 */

$headers = [
'姓名',
'暱稱',
'Email'
];

foreach ($formFields as $field) {
    $headers[] = $field['label'];
}

$headers[] = '狀態';
$headers[] = '確認參與';
$headers[] = '備註';
$headers[] = '報名時間';

echo implode("\t", $headers);

echo "\n";



/* 資料列 */

foreach ($submissions as $sub) {

    $answerStmt = $pdo->prepare("
    SELECT
    field_id,
    answer
    FROM form_answers
    WHERE submission_id=?
    ");

    $answerStmt->execute([
        $sub['id']
    ]);

    $answers = $answerStmt->fetchAll(
        PDO::FETCH_KEY_PAIR
    );

    $row = [];

    $row[] = $sub['username'];

    $row[] = $sub['nickname'];

    $row[] = $sub['email'];



    foreach ($formFields as $field) {

        $row[] =
        str_replace(
            ["\r","\n","\t"],
            ' ',
            $answers[$field['id']] ?? ''
        );

    }



    $statusMap = [
        'pending'=>'待審核',
        'approved'=>'已通過',
        'rejected'=>'已拒絕'
    ];

    $row[] =
    $statusMap[$sub['status']]
    ?? $sub['status'];



    $row[] =
    $sub['confirmed']
    ? '是'
    : '否';



    $row[] =
    str_replace(
        ["\r","\n","\t"],
        ' ',
        $sub['note']
    );



    $row[] =
    $sub['submitted_at'];



    echo implode(
        "\t",
        $row
    );

    echo "\n";
}

exit;