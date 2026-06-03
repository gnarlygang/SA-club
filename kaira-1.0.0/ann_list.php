<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/api/db.php";

function h($str) {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function fmtDate($date) {
    if (empty($date)) return '';
    return date('Y/m/d', strtotime($date));
}

function shortText($text, $len = 80) {
    $text = trim(strip_tags((string)($text ?? '')));
    return mb_strlen($text, 'UTF-8') > $len
        ? mb_substr($text, 0, $len, 'UTF-8') . '...'
        : $text;
}

/* 取得全部公告，由新到舊 */
try {
    $stmt = $pdo->query("
        SELECT id, title, content, detail, date
        FROM announcements
        ORDER BY date DESC, id DESC
    ");
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("資料庫錯誤：" . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>全部系統公告 - FJU_CLUB</title>
    <link rel="stylesheet" href="css/ann_list.css">
</head>
<style>
:root {
  --navy: #1a2744;
  --navy-mid: #243257;
  --accent: #3a5fa0;
  --gold: #c8a96e;
  --gold-light: #e8c98e;
  --cream: #f7f4ef;
  --white: #ffffff;
  --text-dark: #1a1f2e;
  --text-mid: #4a5068;
  --text-muted: #8a91a8;
  --border-light: rgba(26,39,68,0.10);
}

* {
  box-sizing: border-box;
}

body {
  margin: 0;
  font-family: "Noto Sans TC", sans-serif;
  background: var(--cream);
  color: var(--text-dark);
}

a {
  text-decoration: none;
}

.ann-list-page {
  min-height: calc(100vh - 60px);
  background:
    radial-gradient(circle at top right, rgba(200,169,110,0.18), transparent 34%),
    linear-gradient(180deg, var(--cream) 0%, #f2eee6 100%);
  padding-bottom: 4rem;
}

.ann-list-hero {
  background: var(--navy);
  padding: 3.5rem 1.5rem;
  text-align: center;
  color: #fff;
}

.ann-list-label {
  display: inline-block;
  color: var(--gold-light);
  border: 1px solid rgba(232,201,142,0.35);
  background: rgba(232,201,142,0.12);
  padding: 0.25rem 0.7rem;
  border-radius: 999px;
  font-size: 0.72rem;
  letter-spacing: 0.12em;
  margin-bottom: 1rem;
}

.ann-list-hero h1 {
  margin: 0 0 0.8rem;
  font-size: 2rem;
  font-weight: 700;
}

.ann-list-hero p {
  margin: 0;
  font-size: 0.95rem;
  color: rgba(255,255,255,0.65);
}

.ann-list-wrapper {
  width: min(980px, calc(100% - 2rem));
  margin: 2rem auto 0;
}

.ann-list-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.ann-list-top h2 {
  margin: 0;
  font-size: 1.25rem;
  color: var(--navy);
}

.ann-list-back {
  background: var(--navy);
  color: #fff;
  padding: 0.55rem 1rem;
  border-radius: 8px;
  font-size: 0.85rem;
  transition: 0.2s;
}

.ann-list-back:hover {
  background: var(--accent);
  transform: translateY(-1px);
}

.ann-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.ann-list-card {
  display: flex;
  justify-content: space-between;
  gap: 1.5rem;
  background: var(--white);
  border: 1px solid var(--border-light);
  border-radius: 16px;
  padding: 1.3rem 1.5rem;
  box-shadow: 0 8px 24px rgba(26,39,68,0.08);
  color: inherit;
  transition: 0.2s;
}

.ann-list-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 32px rgba(26,39,68,0.12);
}

.ann-list-card-main {
  flex: 1;
}

.ann-list-meta {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  margin-bottom: 0.65rem;
}

.ann-list-tag {
  background: rgba(200,169,110,0.14);
  color: #9b7a3e;
  border: 1px solid rgba(200,169,110,0.35);
  border-radius: 999px;
  padding: 0.18rem 0.55rem;
  font-size: 0.72rem;
  font-weight: 700;
}

.ann-list-date {
  color: var(--text-muted);
  font-size: 0.82rem;
}

.ann-list-card h3 {
  margin: 0 0 0.55rem;
  color: var(--navy);
  font-size: 1.08rem;
  line-height: 1.5;
}

.ann-list-card p {
  margin: 0;
  color: var(--text-mid);
  font-size: 0.9rem;
  line-height: 1.75;
}

.ann-list-arrow {
  align-self: center;
  color: var(--accent);
  font-size: 0.85rem;
  white-space: nowrap;
  font-weight: 600;
}

.ann-empty {
  background: var(--white);
  border: 1px solid var(--border-light);
  border-radius: 16px;
  padding: 2rem;
  text-align: center;
  box-shadow: 0 8px 24px rgba(26,39,68,0.08);
}

.ann-empty h3 {
  margin: 0 0 0.5rem;
  color: var(--navy);
}

.ann-empty p {
  margin: 0;
  color: var(--text-muted);
}

@media (max-width: 760px) {
  .ann-list-top {
    align-items: flex-start;
    flex-direction: column;
    gap: 0.8rem;
  }

  .ann-list-card {
    flex-direction: column;
  }

  .ann-list-arrow {
    align-self: flex-start;
  }
}
</style>
<body>

<?php require_once "header.php"; ?>

<main class="ann-list-page">

    <section class="ann-list-hero">
        <span class="ann-list-label">SYSTEM ANNOUNCEMENTS</span>
        <h1>全部系統公告</h1>
        <p>查看平台最新公告、重要通知與系統更新內容。</p>
    </section>

    <section class="ann-list-wrapper">

        <div class="ann-list-top">
            <h2>公告列表</h2>
            <a href="index.php" class="ann-list-back">返回首頁</a>
        </div>

        <?php if (!empty($announcements)): ?>
            <div class="ann-list">
                <?php foreach ($announcements as $ann): ?>
                    <a href="ann_detail.php?id=<?= h($ann['id']) ?>" class="ann-list-card">

                        <div class="ann-list-card-main">
                            <div class="ann-list-meta">
                                <span class="ann-list-tag">系統公告</span>
                                <span class="ann-list-date"><?= h(fmtDate($ann['date'])) ?></span>
                            </div>

                            <h3><?= h($ann['title']) ?></h3>

                            <p>
                                <?= h(shortText($ann['content'], 90)) ?>
                            </p>
                        </div>

                        <div class="ann-list-arrow">
                            查看完整公告 →
                        </div>

                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="ann-empty">
                <h3>目前尚無公告</h3>
                <p>目前平台尚未發布任何系統公告。</p>
            </div>
        <?php endif; ?>

    </section>

</main>

<?php require_once "footer.php"; ?>

</body>
</html>