<?php
require_once "api/db.php";

/* HTML 安全輸出函式 */
function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/* 日期格式化函式 */
function fmtDate($date) {
    return date('Y/m/d', strtotime($date));
}

$id = $_GET['id'] ?? null;

if (!$id || !is_numeric($id)) {
    die("公告 ID 不正確");
}

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
    <title><?php echo htmlspecialchars($announcement['title']); ?></title>
    <link rel="stylesheet" href="css/ann_detail.css">
</head>
<body>

<?php require_once "header.php"; ?>

<main class="ann-detail-page">
  <article class="ann-detail-card">

    <header class="ann-detail-header">
      <span class="ann-detail-tag">系統公告</span>

      <h1 class="ann-detail-title">
        <?= h($announcement['title']) ?>
      </h1>

      <div class="ann-detail-date">
        發布日期：<?= h(fmtDate($announcement['date'])) ?>
      </div>
    </header>

    <div class="ann-detail-body">

      <section class="ann-detail-section">
        <h2 class="ann-detail-section-title">公告摘要</h2>
        <div class="ann-detail-text">
          <p><?= nl2br(h($announcement['content'])) ?></p>
        </div>
      </section>

      <section class="ann-detail-section">
        <h2 class="ann-detail-section-title">公告詳情</h2>
        <div class="ann-detail-text">
          <p><?= nl2br(h($announcement['detail'])) ?></p>
        </div>
      </section>

      <div class="ann-detail-actions">
  <a href="index.php" class="ann-btn-back">返回首頁</a>

  <?php if ($role===4): ?>
    <div class="ann-admin-actions">
      <a href="ann_edit.php?id=<?= h($announcement['id']) ?>" class="ann-btn-secondary">
        編輯公告
      </a>

      <form action="ann_delete.php" method="POST" class="ann-delete-form" onsubmit="return confirm('確定要刪除此公告嗎？');">
        <input type="hidden" name="id" value="<?= h($announcement['id']) ?>">
        <button type="submit" class="ann-btn-danger">刪除公告</button>
      </form>
    </div>
  <?php endif; ?>
</div>

  </article>
</main>

<?php require_once "footer.php"; ?>

</body>
</html>