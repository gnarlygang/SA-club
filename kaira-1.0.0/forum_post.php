<?php
session_start();
date_default_timezone_set('Asia/Taipei');

require_once "api/db.php";

$post_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$error   = "";
$success = "";

$current_role = (int)($_SESSION['role'] ?? 0);
$can_report = !empty($_SESSION['user_id']) && in_array($current_role, [2, 3], true); // 社團或學生可檢舉

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 取得文章
    $stmt = $pdo->prepare("
        SELECT fp.*, u.username, u.nickname, fc_cat.name AS category_name, fp.category_id
        FROM forum_posts fp
        LEFT JOIN users u ON u.user_id = fp.user_id
        LEFT JOIN forum_categories fc_cat ON fc_cat.id = fp.category_id
        WHERE fp.id = :id
        LIMIT 1
    ");
    $stmt->execute([":id" => $post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        header("Location: forum.php");
        exit;
    }

    // 處理新留言送出（需登入）
    if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_SESSION["user_id"])) {
        $content = trim($_POST["content"] ?? "");

        if ($content === "") {
            $error = "留言內容不可為空。";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO forum_comments (post_id, user_id, content) 
                VALUES (:pid, :uid, :content)
            ");
            $stmt->execute([
                ":pid"     => $post_id,
                ":uid"     => $_SESSION["user_id"],
                ":content" => $content,
            ]);

            header("Location: forum_post.php?id=" . $post_id . "#comments");
            exit;
        }
    }

    // 取得所有留言
    $stmt = $pdo->prepare("
        SELECT fc.*, u.username, u.nickname
        FROM forum_comments fc
        LEFT JOIN users u ON u.user_id = fc.user_id
        WHERE fc.post_id = :pid
        ORDER BY fc.created_at ASC
    ");
    $stmt->execute([":pid" => $post_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("資料庫連線失敗：" . $e->getMessage());
}

$post_author = $post["nickname"] ?: $post["username"];

function time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 0) $diff = 0;

    if ($diff < 60) {
        return "剛剛";
    }

    if ($diff < 3600) {
        return floor($diff / 60) . " 分鐘前";
    }

    if ($diff < 86400) {
        return floor($diff / 3600) . " 小時前";
    }

    return date("Y/m/d H:i", strtotime($datetime));
}

require_once "header.php";
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($post["title"]) ?> — 輔大社團論壇</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;600;700&family=Microsoft+JhengHei&display=swap" rel="stylesheet">

  <style>
    :root {
      --footer-bg: #1a2744;
      --card-shadow: 0 2px 12px rgba(60,80,120,0.08);
      --border: #e8ecf0;
      --accent: #2d3a4a;
      --error-color: #c0392b;
      --input-border: #c8d0dc;
    }

    * { 
      box-sizing: border-box; 
    }

    body {
      font-family: "Microsoft JhengHei", "微軟正黑體", sans-serif;
      background: #f0f2f5;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .post-wrapper {
      flex: 1;
      max-width: 860px;
      margin: 0 auto;
      width: 100%;
      padding: 32px 16px 60px;
    }

    /* ── Breadcrumb ── */
    .breadcrumb-bar {
      font-size: 13px;
      color: #9aa;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .breadcrumb-bar a {
      color: #6e8ab0;
      text-decoration: none;
      font-weight: 600;
    }

    .breadcrumb-bar a:hover { 
      text-decoration: underline; 
    }

    /* ── Post card ── */
    .post-card {
      background: #fff;
      border-radius: 16px;
      box-shadow: var(--card-shadow);
      padding: 36px 40px;
      margin-bottom: 24px;
    }

    .post-category-badge {
      display: inline-block;
      font-size: 11px;
      padding: 3px 10px;
      border-radius: 999px;
      background: #e8ecf5;
      color: #4a6080;
      font-weight: 600;
      letter-spacing: 0.5px;
      margin-bottom: 12px;
    }

    .post-title {
      font-size: 24px;
      font-weight: 700;
      color: #1a2535;
      line-height: 1.4;
      margin-bottom: 14px;
    }

    .post-meta {
      display: flex;
      align-items: center;
      gap: 16px;
      font-size: 13px;
      color: #9aa;
      margin-bottom: 28px;
      padding-bottom: 20px;
      border-bottom: 1px solid var(--border);
    }

    .post-meta .meta-author {
      display: flex;
      align-items: center;
      gap: 6px;
      font-weight: 600;
      color: #6e8ab0;
    }

    .author-avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: #2d3a4a;
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      font-weight: 700;
      flex-shrink: 0;
    }

    .post-content {
      font-size: 15px;
      color: #334;
      line-height: 1.85;
      white-space: pre-wrap;
    }

    /* ── Comments section ── */
    .comments-section {
      margin-top: 8px;
    }

    .comments-header {
      font-size: 16px;
      font-weight: 700;
      color: #2d3a4a;
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .comments-count {
      font-size: 13px;
      color: #9aa;
      font-weight: 400;
    }

    /* ── Comment card ── */
    .comment-card {
      background: #fff;
      border-radius: 12px;
      box-shadow: var(--card-shadow);
      padding: 20px 24px;
      margin-bottom: 12px;
    }

    .comment-meta {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 10px;
    }

    .comment-author {
      font-size: 13px;
      font-weight: 700;
      color: #2d3a4a;
    }

    .comment-time {
      font-size: 12px;
      color: #aab;
    }

    .comment-content {
      font-size: 14px;
      color: #445;
      line-height: 1.7;
      white-space: pre-wrap;
    }

    /* ── Comment form ── */
    .comment-form-card {
      background: #fff;
      border-radius: 14px;
      box-shadow: var(--card-shadow);
      padding: 28px 32px;
      margin-top: 24px;
    }

    .comment-form-title {
      font-size: 15px;
      font-weight: 700;
      color: #2d3a4a;
      margin-bottom: 16px;
    }

    .form-control {
      border: 1.5px solid var(--input-border);
      border-radius: 8px;
      padding: 10px 14px;
      font-size: 14px;
      resize: vertical;
      transition: border-color 0.2s, box-shadow 0.2s;
    }

    .form-control:focus {
      border-color: #6e8ab0;
      box-shadow: 0 0 0 3px rgba(110,138,176,0.15);
      outline: none;
    }

    .btn-submit {
      background: #2d3a4a;
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 11px 24px;
      font-size: 14px;
      font-weight: 600;
      letter-spacing: 0.5px;
      transition: background 0.2s, transform 0.1s;
      margin-top: 12px;
    }

    .btn-submit:hover {
      background: #3d4e62;
      transform: translateY(-1px);
    }

    .alert-error {
      background: #fdf0ef;
      border: 1px solid #f0c4c0;
      color: var(--error-color);
      border-radius: 8px;
      padding: 10px 14px;
      font-size: 13px;
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 14px;
    }

    .login-prompt {
      background: #f4f6f9;
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 16px 20px;
      font-size: 14px;
      color: #667;
      text-align: center;
      margin-top: 24px;
    }

    .login-prompt a {
      color: #4a6080;
      font-weight: 600;
      text-decoration: none;
    }

    /* ── 檢舉按鈕（前端暫存） ── */
    .post-top-row { 
      display: flex; 
      justify-content: space-between; 
      align-items: flex-start; 
      gap: 16px; 
    }

    .forum-report-btn { 
      flex-shrink: 0; 
      border: 1px solid #f1d4d4; 
      background: #fff7f7; 
      color: #b45353; 
      border-radius: 999px; 
      padding: 7px 14px; 
      font-size: 13px; 
      cursor: pointer; 
      transition: 0.2s ease; 
      white-space: nowrap; 
    }

    .forum-report-btn:hover { 
      border-color: #b91c1c; 
      background: #fee2e2; 
      color: #991b1b; 
    }

    .comment-top-row { 
      display: flex; 
      justify-content: space-between; 
      align-items: flex-start; 
      gap: 12px; 
    }

    .comment-report-btn { 
      border: 1px solid #f1d4d4; 
      background: #fff7f7; 
      color: #b45353; 
      border-radius: 999px; 
      padding: 4px 10px; 
      font-size: 12px; 
      cursor: pointer; 
      white-space: nowrap; 
    }

    .comment-report-btn:hover { 
      background: #fee2e2; 
      color: #991b1b; 
    }

    #report-toast { 
      position: fixed; 
      bottom: 1.5rem; 
      right: 1.5rem; 
      z-index: 9999; 
      background: #1a1a2e; 
      color: #fff; 
      padding: .55rem 1.1rem; 
      border-radius: 8px; 
      font-size: .8rem; 
      font-weight: 500; 
      box-shadow: 0 4px 16px rgba(0,0,0,.2); 
      transition: opacity .3s; 
      opacity: 0; 
      pointer-events: none; 
    }

    /* ── Footer ── */
    footer {
      background-color: var(--footer-bg);
      color: #333;
      padding: 16px 0;
      text-align: center;
      font-size: 13px;
    }

    @media (max-width: 600px) {
      .post-card { 
        padding: 24px 20px; 
      }

      .comment-form-card { 
        padding: 20px; 
      }
    }
  </style>
</head>
<body>

<div class="post-wrapper">

  <!-- 麵包屑 -->
  <div class="breadcrumb-bar">
    <a href="forum.php"><i class="bi bi-journals me-1"></i>社團論壇</a>
    <i class="bi bi-chevron-right" style="font-size:11px;"></i>
    <a href="forum.php?cat=<?= $post["category_id"] ?>">
      <?= htmlspecialchars($post["category_name"]) ?>
    </a>
    <i class="bi bi-chevron-right" style="font-size:11px;"></i>
    <span style="color:#667;">
      <?= mb_strimwidth(htmlspecialchars($post["title"]), 0, 30, "...") ?>
    </span>
  </div>

  <!-- 文章主體 -->
  <div class="post-card">
    <div class="post-top-row">
      <div>
        <span class="post-category-badge">
          <?= htmlspecialchars($post["category_name"]) ?>
        </span>
        <div class="post-title">
          <?= htmlspecialchars($post["title"]) ?>
        </div>
      </div>

      <?php if ($can_report): ?>
        <button
          type="button"
          class="forum-report-btn"
          onclick="reportForumItem('post', '<?= htmlspecialchars($post_id) ?>', '<?= htmlspecialchars($post["title"], ENT_QUOTES) ?>')"
        >
          <i class="bi bi-flag"></i> 檢舉貼文
        </button>
      <?php endif; ?>
    </div>

    <div class="post-meta">
      <span class="meta-author">
        <div class="author-avatar">
          <?= mb_substr($post_author, 0, 1) ?>
        </div>
        <?= htmlspecialchars($post_author) ?>
      </span>

      <span>
        <i class="bi bi-clock me-1"></i>
        <?= time_ago($post["created_at"]) ?>
      </span>

      <span>
        <i class="bi bi-chat-dots me-1"></i>
        <?= count($comments) ?> 則留言
      </span>
    </div>

    <div class="post-content">
      <?= htmlspecialchars($post["content"]) ?>
    </div>
  </div>

  <!-- 留言區 -->
  <div class="comments-section" id="comments">

    <div class="comments-header">
      <i class="bi bi-chat-square-text-fill"></i>
      留言區
      <span class="comments-count"><?= count($comments) ?> 則</span>
    </div>

    <?php if (empty($comments)): ?>
      <div style="text-align:center; padding:40px 0; color:#aab; font-size:14px;">
        <i class="bi bi-chat-square" style="font-size:32px; display:block; margin-bottom:10px;"></i>
        還沒有留言，來發表第一則吧！
      </div>
    <?php else: ?>

      <?php foreach ($comments as $i => $comment): ?>
        <?php
          $c_author = $comment["nickname"] ?: $comment["username"];
        ?>

        <div class="comment-card">
          <div class="comment-top-row">
            <div class="comment-meta">
              <div class="author-avatar" style="width:28px;height:28px;font-size:11px;">
                <?= mb_substr($c_author, 0, 1) ?>
              </div>

              <span class="comment-author">
                <?= htmlspecialchars($c_author) ?>
              </span>

              <span class="comment-time">
                <?= time_ago($comment["created_at"]) ?>
              </span>
            </div>

            <?php if ($can_report): ?>
              <button
                type="button"
                class="comment-report-btn"
                onclick="reportForumItem('comment', '<?= htmlspecialchars($comment["id"]) ?>', '<?= htmlspecialchars(mb_strimwidth($comment["content"], 0, 36, "..."), ENT_QUOTES) ?>')"
              >
                <i class="bi bi-flag"></i> 檢舉留言
              </button>
            <?php endif; ?>
          </div>

          <div class="comment-content">
            <?= htmlspecialchars($comment["content"]) ?>
          </div>
        </div>
      <?php endforeach; ?>

    <?php endif; ?>

    <!-- 留言表單 -->
    <?php if (!empty($_SESSION["user_id"])): ?>
      <div class="comment-form-card">
        <div class="comment-form-title">
          <i class="bi bi-pencil me-2"></i>發表留言
        </div>

        <?php if ($error): ?>
          <div class="alert-error">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="forum_post.php?id=<?= $post_id ?>">
          <textarea
            class="form-control"
            name="content"
            rows="4"
            placeholder="輸入您的留言..."
            required
          ></textarea>

          <button type="submit" class="btn-submit">
            <i class="bi bi-send me-2"></i>送出留言
          </button>
        </form>
      </div>
    <?php else: ?>
      <div class="login-prompt">
        請先 <a href="login.php">登入</a> 才能留言
      </div>
    <?php endif; ?>

  </div>

</div>

<div id="report-toast"></div>

<script>
function reportForumItem(type, id, title) {
  const reason = prompt('請輸入檢舉原因：');

  if (reason === null) return;

  if (reason.trim() === '') { 
    alert('請輸入檢舉原因'); 
    return; 
  }

  const reports = JSON.parse(localStorage.getItem('forumReports') || '[]');

  reports.unshift({
    type,
    typeLabel: type === 'comment' ? '留言' : '貼文',
    id, 
    title,
    reason: reason.trim(),
    reporterRole: <?= json_encode($current_role) ?>,
    postId: <?= json_encode($post_id) ?>,
    createdAt: new Date().toLocaleString('zh-TW')
  });

  localStorage.setItem('forumReports', JSON.stringify(reports));
  showReportToast('已送出檢舉');
}

function showReportToast(msg) {
  const t = document.getElementById('report-toast');
  if (!t) return;

  t.textContent = msg;
  t.style.opacity = '1';

  clearTimeout(t._timer);
  t._timer = setTimeout(() => { 
    t.style.opacity = '0'; 
  }, 1800);
}
</script>

<?php require "footer.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>