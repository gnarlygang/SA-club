<?php
session_start();
require_once "api/db.php";

function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 4;

if (!$isAdmin) {
    die("權限不足，只有管理員可以編輯公告。");
}

$id = $_GET['id'] ?? $_POST['id'] ?? null;

if (!$id || !is_numeric($id)) {
    die("公告 ID 不正確");
}

$error_msg = "";

/* 更新公告 */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $detail = trim($_POST['detail'] ?? '');

    if ($title === '' || $content === '' || $detail === '') {
        $error_msg = "請完整填寫公告標題、摘要與詳情。";
    } else {
        try {
            $sql = "UPDATE announcements
                    SET title = :title,
                        content = :content,
                        detail = :detail
                    WHERE id = :id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':title' => $title,
                ':content' => $content,
                ':detail' => $detail,
                ':id' => $id
            ]);

            header("Location: ann_detail.php?id=" . urlencode($id));
            exit;

        } catch (PDOException $e) {
            $error_msg = "資料庫錯誤：" . $e->getMessage();
        }
    }
}

/* 讀取原本公告資料 */
try {
    $sql = "SELECT id, title, content, detail, date
            FROM announcements
            WHERE id = :id
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id' => $id
    ]);

    $announcement = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$announcement) {
        die("找不到此公告");
    }

} catch (PDOException $e) {
    die("資料庫錯誤：" . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>編輯公告 - <?= h($announcement['title']) ?></title>
    <link rel="stylesheet" href="css/ann_edit.css">
</head>
<body>

<?php require_once "header.php"; ?>

<main class="edit-wrapper">
  <div class="edit-card">

    <div class="edit-header">
      <h1 class="edit-title">編輯公告</h1>
      <p class="edit-subtitle">
        修改公告標題、摘要與詳細內容後，請按下儲存修改。
      </p>
    </div>

    <?php if (!empty($error_msg)): ?>
      <div class="edit-error">
        <?= h($error_msg) ?>
      </div>
    <?php endif; ?>

    <form action="ann_edit.php" method="POST" class="edit-form">
      <input type="hidden" name="id" value="<?= h($announcement['id']) ?>">

      <div class="edit-form-group">
        <label for="title">公告標題</label>
        <input
          type="text"
          id="title"
          name="title"
          class="edit-form-control"
          value="<?= h($announcement['title']) ?>"
          required
        >
      </div>

      <div class="edit-form-group">
        <label for="content">公告摘要</label>
        <textarea
          id="content"
          name="content"
          class="edit-form-control"
          rows="4"
          required
        ><?= h($announcement['content']) ?></textarea>
      </div>

      <div class="edit-form-group">
        <label for="detail">公告詳情</label>
        <textarea
          id="detail"
          name="detail"
          class="edit-form-control detail-area"
          rows="8"
          required
        ><?= h($announcement['detail']) ?></textarea>
      </div>

      <div class="edit-actions">
        <button type="submit" class="btn-edit-save">
          儲存修改
        </button>

        <a href="ann_detail.php?id=<?= h($announcement['id']) ?>" class="btn-edit-cancel">
          取消
        </a>
      </div>

      <div class="edit-back-container">
        <a href="ann_detail.php?id=<?= h($announcement['id']) ?>" class="edit-back-link">
          返回公告詳情
        </a>
      </div>
    </form>

  </div>
</main>

<?php require_once "footer.php"; ?>

</body>
</html>