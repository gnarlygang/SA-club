<?php
session_start();
date_default_timezone_set('Asia/Taipei');

require_once "api/db.php";

$post_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$error   = "";
$success = "";

$current_user_id = (int)($_SESSION['user_id'] ?? 0);
$current_role    = (int)($_SESSION['role'] ?? 0);
$is_logged_in    = $current_user_id > 0;
$is_admin        = $is_logged_in && $current_role === 4;
$can_report      = $is_logged_in && in_array($current_role, [2, 3], true);

// 安全的 json_encode，避免任何特殊字元破壞 HTML 屬性
function je($val) {
    return json_encode($val, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $is_logged_in) {
    $action = $_POST['action'] ?? 'add_comment';

    if ($action === 'delete_post') {
        $pid = (int)($_POST['post_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT user_id FROM forum_posts WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$pid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && ((int)$row['user_id'] == $current_user_id || $is_admin)) {
            $pdo->prepare("UPDATE forum_posts SET is_deleted=1, deleted_at=NOW(), deleted_by=? WHERE id=?")
                ->execute([$current_user_id, $pid]);
            header("Location: forum.php"); exit;
        }
        $error = "無權限刪除此文章。";
    }

    elseif ($action === 'edit_post') {
        $pid     = (int)($_POST['post_id'] ?? 0);
        $title   = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $stmt = $pdo->prepare("SELECT user_id FROM forum_posts WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$pid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$title || !$content) {
            $error = "標題與內容不可為空。";
        } elseif ($row && ((int)$row['user_id'] == $current_user_id || $is_admin)) {
            $pdo->prepare("UPDATE forum_posts SET title=?, content=?, updated_at=NOW() WHERE id=?")
                ->execute([$title, $content, $pid]);
            header("Location: forum_post.php?id=" . $pid . "&edited=1"); exit;
        } else {
            $error = "無權限編輯此文章。";
        }
    }

    elseif ($action === 'delete_comment') {
        $cid = (int)($_POST['comment_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT user_id FROM forum_comments WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$cid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && ((int)$row['user_id'] == $current_user_id || $is_admin)) {
            $pdo->prepare("UPDATE forum_comments SET is_deleted=1, deleted_at=NOW(), deleted_by=? WHERE id=?")
                ->execute([$current_user_id, $cid]);
            header("Location: forum_post.php?id=" . $post_id . "#comments"); exit;
        }
        $error = "無權限刪除此留言。";
    }

    elseif ($action === 'edit_comment') {
        $cid     = (int)($_POST['comment_id'] ?? 0);
        $content = trim($_POST['comment_content'] ?? '');
        if (!$content) {
            $error = "留言內容不可為空。";
        } else {
            $stmt = $pdo->prepare("SELECT user_id FROM forum_comments WHERE id = ? AND is_deleted = 0");
            $stmt->execute([$cid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && ((int)$row['user_id'] == $current_user_id || $is_admin)) {
                $pdo->prepare("UPDATE forum_comments SET content=?, updated_at=NOW() WHERE id=?")
                    ->execute([$content, $cid]);
                header("Location: forum_post.php?id=" . $post_id . "#comments"); exit;
            }
            $error = "無權限編輯此留言。";
        }
    }

    elseif ($action === 'report') {
        $item_type = $_POST['item_type'] ?? '';
        $item_id   = (int)($_POST['item_id'] ?? 0);
        $reason    = trim($_POST['reason'] ?? '');
        if (!in_array($item_type, ['post', 'comment'])) {
            $error = "無效的檢舉類型。";
        } elseif (empty($reason)) {
            $error = "請填寫檢舉原因。";
        } else {
            $check = $pdo->prepare("SELECT id FROM forum_reports WHERE item_type=? AND item_id=? AND reporter_id=? AND status='pending'");
            $check->execute([$item_type, $item_id, $current_user_id]);
            if ($check->fetch()) {
                $error = "你已經檢舉過這則內容，請等待管理員處理。";
            } else {
                $pdo->prepare("INSERT INTO forum_reports (item_type, item_id, reporter_id, reason) VALUES (?,?,?,?)")
                    ->execute([$item_type, $item_id, $current_user_id, $reason]);
                $success = "檢舉已送出，感謝你維護社群環境。";
            }
        }
    }

    elseif ($action === 'add_comment') {
        $content   = trim($_POST["content"] ?? "");
        $parent_id = isset($_POST['parent_id']) && (int)$_POST['parent_id'] > 0
                     ? (int)$_POST['parent_id'] : null;
        if ($content === "") {
            $error = "留言內容不可為空。";
        } else {
            if ($parent_id) {
                $pc = $pdo->prepare("SELECT id FROM forum_comments WHERE id=? AND post_id=? AND is_deleted=0");
                $pc->execute([$parent_id, $post_id]);
                if (!$pc->fetch()) $parent_id = null;
            }
            $pdo->prepare("INSERT INTO forum_comments (post_id, user_id, content, parent_id) VALUES (?,?,?,?)")
                ->execute([$post_id, $current_user_id, $content, $parent_id]);
            header("Location: forum_post.php?id=" . $post_id . "#comments"); exit;
        }
    }
}

// ── 查詢 ──────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare("
        SELECT fp.*, u.username, u.nickname, fc_cat.name AS category_name, fp.category_id
        FROM forum_posts fp
        LEFT JOIN users u ON u.user_id = fp.user_id
        LEFT JOIN forum_categories fc_cat ON fc_cat.id = fp.category_id
        WHERE fp.id = :id AND fp.is_deleted = 0
        LIMIT 1
    ");
    $stmt->execute([":id" => $post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$post) { header("Location: forum.php"); exit; }

    $stmt = $pdo->prepare("
        SELECT fc.*,
               u.username, u.nickname,
               pu.username AS parent_username,
               pu.nickname AS parent_nickname
        FROM forum_comments fc
        LEFT JOIN users u  ON u.user_id  = fc.user_id
        LEFT JOIN forum_comments pc ON pc.id = fc.parent_id
        LEFT JOIN users pu ON pu.user_id = pc.user_id
        WHERE fc.post_id = :pid AND fc.is_deleted = 0
        ORDER BY fc.created_at ASC
    ");
    $stmt->execute([":pid" => $post_id]);
    $all_comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("資料庫連線失敗：" . $e->getMessage());
}

// 整理頂層留言與回覆
$top_comments = [];
$replies_map  = [];
foreach ($all_comments as $c) {
    $pid = $c['parent_id'] ?? null;
    if ($pid) $replies_map[$pid][] = $c;
    else      $top_comments[] = $c;
}

$post_author   = $post["nickname"] ?: $post["username"];
$can_edit_post = $is_logged_in && ((int)$post['user_id'] == $current_user_id || $is_admin);

function time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 0) $diff = 0;
    if ($diff < 60)    return "剛剛";
    if ($diff < 3600)  return floor($diff / 60) . " 分鐘前";
    if ($diff < 86400) return floor($diff / 3600) . " 小時前";
    return date("Y/m/d H:i", strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">

<body>
<?php require_once "header.php"; ?>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($post["title"]) ?> — 輔大社團論壇</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root{--shadow:0 2px 12px rgba(60,80,120,.08);--border:#e8ecf0;--inp:#c8d0dc;--err:#c0392b}
    *{box-sizing:border-box}
    body{font-family:"Microsoft JhengHei",sans-serif;background:#f0f2f5;min-height:100vh;display:flex;flex-direction:column}
    .wrap{flex:1;max-width:860px;margin:0 auto;width:100%;padding:32px 16px 60px}
    .bc{font-size:13px;color:#9aa;margin-bottom:20px;display:flex;align-items:center;gap:6px}
    .bc a{color:#6e8ab0;text-decoration:none;font-weight:600}.bc a:hover{text-decoration:underline}
    .post-card{background:#fff;border-radius:16px;box-shadow:var(--shadow);padding:36px 40px;margin-bottom:24px}
    .cat-badge{display:inline-block;font-size:11px;padding:3px 10px;border-radius:999px;background:#e8ecf5;color:#4a6080;font-weight:600;margin-bottom:12px}
    .post-title{font-size:24px;font-weight:700;color:#1a2535;line-height:1.4;margin-bottom:14px}
    .post-meta{display:flex;align-items:center;gap:16px;font-size:13px;color:#9aa;margin-bottom:28px;padding-bottom:20px;border-bottom:1px solid var(--border);flex-wrap:wrap}
    .meta-author{display:flex;align-items:center;gap:6px;font-weight:600;color:#6e8ab0}
    .post-content{font-size:15px;color:#334;line-height:1.85;white-space:pre-wrap}
    .post-top{display:flex;justify-content:space-between;align-items:flex-start;gap:16px}
    .act-group{display:flex;gap:8px;flex-shrink:0;flex-wrap:wrap;justify-content:flex-end}
    .av{width:32px;height:32px;border-radius:50%;background:#2d3a4a;color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0}
    .av.sm{width:28px;height:28px;font-size:11px}
    /* 按鈕 */
    .btn-e{border:1px solid #c8d4e8;background:#f0f4fb;color:#3a5a8a;border-radius:999px;padding:6px 14px;font-size:13px;cursor:pointer}.btn-e:hover{background:#dce7f7}
    .btn-d{border:1px solid #f1d4d4;background:#fff7f7;color:#b45353;border-radius:999px;padding:6px 14px;font-size:13px;cursor:pointer}.btn-d:hover{background:#fee2e2;color:#991b1b}
    .btn-r{border:1px solid #f1d4d4;background:#fff7f7;color:#b45353;border-radius:999px;padding:6px 14px;font-size:13px;cursor:pointer}.btn-r:hover{background:#fee2e2;color:#991b1b}
    .btn-sm-e{border:1px solid #c8d4e8;background:#f0f4fb;color:#3a5a8a;border-radius:999px;padding:4px 10px;font-size:12px;cursor:pointer}.btn-sm-e:hover{background:#dce7f7}
    .btn-sm-d{border:1px solid #f1d4d4;background:#fff7f7;color:#b45353;border-radius:999px;padding:4px 10px;font-size:12px;cursor:pointer}.btn-sm-d:hover{background:#fee2e2;color:#991b1b}
    .btn-sm-r{border:1px solid #f1d4d4;background:#fff7f7;color:#b45353;border-radius:999px;padding:4px 10px;font-size:12px;cursor:pointer}.btn-sm-r:hover{background:#fee2e2;color:#991b1b}
    .btn-sm-rep{border:1px solid #c8d4e8;background:#f0f4fb;color:#3a5a8a;border-radius:999px;padding:4px 10px;font-size:12px;cursor:pointer}.btn-sm-rep:hover{background:#dce7f7}
    /* 留言 */
    .cmts-hd{font-size:16px;font-weight:700;color:#2d3a4a;margin-bottom:16px;display:flex;align-items:center;gap:8px}
    .cmts-cnt{font-size:13px;color:#9aa;font-weight:400}
    .cmt-wrap{margin-bottom:10px}
    .cmt-card{background:#fff;border-radius:12px;box-shadow:var(--shadow);padding:18px 22px}
    .cmt-card.highlight{box-shadow:0 0 0 2px #6e8ab0,var(--shadow)}
    .cmt-top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:8px}
    .cmt-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .cmt-author{font-size:13px;font-weight:700;color:#2d3a4a}
    .cmt-time{font-size:12px;color:#aab}
    .cmt-content{font-size:14px;color:#445;line-height:1.7;white-space:pre-wrap}
    .cmt-btns{display:flex;gap:6px;flex-shrink:0;flex-wrap:wrap}
    /* 回覆區 */
    .replies-area{margin-top:8px;padding-left:40px}
    .replies-toggle{display:inline-flex;align-items:center;gap:5px;background:none;border:none;color:#6e8ab0;font-size:13px;font-weight:600;cursor:pointer;padding:4px 0;margin-bottom:6px}
    .replies-toggle:hover{color:#3a5a8a}
    .replies-toggle .arrow{font-size:10px;transition:transform .2s}
    .replies-toggle.open .arrow{transform:rotate(180deg)}
    .replies-list{display:none}
    .replies-list.open{display:block}
    .reply-card{background:#f4f7fc;border-radius:10px;padding:14px 18px;margin-bottom:8px;border-left:3px solid #c8d4e8}
    .reply-card.highlight{border-left-color:#6e8ab0;box-shadow:0 0 0 1px #6e8ab0}
    .reply-top{display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:6px}
    .mention-tag{display:inline-flex;align-items:center;gap:3px;background:#dce8fb;color:#3a5a8a;border-radius:6px;padding:2px 8px;font-size:11px;font-weight:600;margin-bottom:6px}
    /* 留言表單 */
    .cmt-form-card{background:#fff;border-radius:14px;box-shadow:var(--shadow);padding:26px 30px;margin-top:22px}
    .cmt-form-title{font-size:15px;font-weight:700;color:#2d3a4a;margin-bottom:14px;display:flex;align-items:center;gap:8px}
    .reply-banner{background:#eef3fb;border-radius:8px;padding:8px 14px;margin-bottom:12px;font-size:13px;color:#3a5a8a;justify-content:space-between;align-items:center;display:none}
    .reply-banner.show{display:flex}
    .reply-cancel{background:none;border:none;color:#9aa;font-size:18px;cursor:pointer;line-height:1}
    .reply-cancel:hover{color:#334}
    .fc{border:1.5px solid var(--inp);border-radius:8px;padding:10px 14px;font-size:14px;resize:vertical;width:100%}
    .fc:focus{border-color:#6e8ab0;box-shadow:0 0 0 3px rgba(110,138,176,.15);outline:none}
    .btn-sub{background:#2d3a4a;color:#fff;border:none;border-radius:8px;padding:11px 24px;font-size:14px;font-weight:600;cursor:pointer;margin-top:12px}
    .btn-sub:hover{background:#3d4e62}
    /* 알림 */
    .alert-err{background:#fdf0ef;border:1px solid #f0c4c0;color:var(--err);border-radius:8px;padding:10px 14px;font-size:13px;display:flex;align-items:center;gap:8px;margin-bottom:14px}
    .alert-ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;border-radius:8px;padding:10px 14px;font-size:13px;display:flex;align-items:center;gap:8px;margin-bottom:14px}
    .login-prompt{background:#f4f6f9;border:1px solid var(--border);border-radius:10px;padding:16px 20px;font-size:14px;color:#667;text-align:center;margin-top:24px}
    .login-prompt a{color:#4a6080;font-weight:600;text-decoration:none}
    /* Modal */
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9990;align-items:center;justify-content:center}
    .modal-overlay.active{display:flex}
    .modal-box{background:#fff;border-radius:16px;padding:32px 36px;width:100%;max-width:560px;box-shadow:0 8px 32px rgba(0,0,0,.18);position:relative}
    .modal-title{font-size:18px;font-weight:700;color:#1a2535;margin-bottom:20px}
    .modal-close{position:absolute;top:16px;right:20px;background:none;border:none;font-size:20px;color:#aab;cursor:pointer}
    .modal-close:hover{color:#334}
    .modal-inp{width:100%;border:1.5px solid var(--inp);border-radius:8px;padding:10px 14px;font-size:14px;font-family:inherit;resize:vertical;margin-bottom:14px}
    .modal-inp:focus{border-color:#6e8ab0;outline:none}
    .modal-acts{display:flex;gap:10px;justify-content:flex-end}
    .btn-mc{border:1px solid #d0d8e4;background:#f5f7fa;color:#667;border-radius:8px;padding:9px 20px;font-size:14px;cursor:pointer}
    .btn-ms{border:none;background:#2d3a4a;color:#fff;border-radius:8px;padding:9px 20px;font-size:14px;font-weight:600;cursor:pointer}.btn-ms:hover{background:#3d4e62}
    .btn-md{border:none;background:#b91c1c;color:#fff;border-radius:8px;padding:9px 20px;font-size:14px;font-weight:600;cursor:pointer}.btn-md:hover{background:#991b1b}
    .report-hint{font-size:12px;color:#9aa;margin-bottom:10px}
    #toast-msg{position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;background:#1a1a2e;color:#fff;padding:.55rem 1.1rem;border-radius:8px;font-size:.8rem;font-weight:500;box-shadow:0 4px 16px rgba(0,0,0,.2);transition:opacity .3s;opacity:0;pointer-events:none}
    @media(max-width:600px){
      .post-card,.cmt-form-card,.modal-box{padding:20px}
      .post-top{flex-direction:column}
      .replies-area{padding-left:16px}
    }
  </style>
</head>
<div class="wrap">

<!-- 麵包屑 -->
<div class="bc">
  <a href="forum.php"><i class="bi bi-journals me-1"></i>社團論壇</a>
  <i class="bi bi-chevron-right" style="font-size:11px"></i>
  <a href="forum.php?cat=<?= $post["category_id"] ?>"><?= htmlspecialchars($post["category_name"]) ?></a>
  <i class="bi bi-chevron-right" style="font-size:11px"></i>
  <span style="color:#667"><?= mb_strimwidth(htmlspecialchars($post["title"]),0,30,"...") ?></span>
</div>

<?php if ($error):   ?><div class="alert-err"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert-ok"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if (isset($_GET['edited'])): ?><div class="alert-ok"><i class="bi bi-check-circle-fill"></i>文章已成功更新。</div><?php endif; ?>

<!-- 文章 -->
<div class="post-card">
  <div class="post-top">
    <div>
      <span class="cat-badge"><?= htmlspecialchars($post["category_name"]) ?></span>
      <div class="post-title"><?= htmlspecialchars($post["title"]) ?></div>
    </div>
    <div class="act-group">
      <?php if ($can_edit_post): ?>
      <button type="button" class="btn-e" onclick="openModal('edit-post-modal')">
        <i class="bi bi-pencil"></i> 編輯
      </button>
      <form method="POST" action="forum_post.php?id=<?= $post_id ?>" style="margin:0"
            onsubmit="return confirm('確定要刪除這篇文章嗎？刪除後無法復原。')">
        <input type="hidden" name="action"  value="delete_post">
        <input type="hidden" name="post_id" value="<?= $post_id ?>">
        <button type="submit" class="btn-d"><i class="bi bi-trash3"></i> 刪除</button>
      </form>
      <?php endif; ?>
      <?php if ($can_report && (int)$post['user_id'] != $current_user_id): ?>
      <button type="button" class="btn-r"
        onclick="openReportModal('post',<?= $post_id ?>,<?= je($post["title"]) ?>)">
        <i class="bi bi-flag"></i> 檢舉
      </button>
      <?php endif; ?>
    </div>
  </div>

  <div class="post-meta">
    <span class="meta-author">
      <div class="av"><?= mb_substr($post_author,0,1) ?></div>
      <?= htmlspecialchars($post_author) ?>
    </span>
    <span><i class="bi bi-clock me-1"></i><?= time_ago($post["created_at"]) ?></span>
    <?php if (!empty($post['updated_at']) && $post['updated_at'] !== $post['created_at']): ?>
    <span style="color:#aab;font-size:12px"><i class="bi bi-pencil me-1"></i>已編輯</span>
    <?php endif; ?>
    <span><i class="bi bi-chat-dots me-1"></i><?= count($all_comments) ?> 則留言</span>
  </div>

  <div class="post-content"><?= htmlspecialchars($post["content"]) ?></div>
</div>

<!-- 留言區 -->
<div id="comments">
<div class="cmts-hd">
  <i class="bi bi-chat-square-text-fill"></i>留言區
  <span class="cmts-cnt"><?= count($all_comments) ?> 則</span>
</div>

<?php if (empty($top_comments)): ?>
<div style="text-align:center;padding:40px 0;color:#aab;font-size:14px">
  <i class="bi bi-chat-square" style="font-size:32px;display:block;margin-bottom:10px"></i>
  還沒有留言，來發表第一則吧！
</div>
<?php else: ?>

<?php foreach ($top_comments as $c):
  $c_author    = $c["nickname"] ?: $c["username"];
  $can_edit_c  = $is_logged_in && ((int)$c['user_id'] == $current_user_id || $is_admin);
  $sub_replies = $replies_map[$c['id']] ?? [];
  $reply_count = count($sub_replies);
?>
<div class="cmt-wrap">
  <div class="cmt-card" id="comment-<?= $c['id'] ?>">
    <div class="cmt-top">
      <div class="cmt-meta">
        <div class="av sm"><?= mb_substr($c_author,0,1) ?></div>
        <span class="cmt-author"><?= htmlspecialchars($c_author) ?></span>
        <span class="cmt-time"><?= time_ago($c["created_at"]) ?></span>
        <?php if (!empty($c['updated_at']) && $c['updated_at'] !== $c['created_at']): ?>
        <span style="color:#aab;font-size:11px">(已編輯)</span>
        <?php endif; ?>
      </div>
      <div class="cmt-btns">
        <?php if ($is_logged_in): ?>
        <button type="button" class="btn-sm-rep"
          onclick="setReply(<?= $c['id'] ?>,<?= je($c_author) ?>)">
          <i class="bi bi-reply"></i> 回覆
        </button>
        <?php endif; ?>
        <?php if ($can_edit_c): ?>
        <button type="button" class="btn-sm-e"
          onclick='openEditCommentModal(<?= $c["id"] ?>,<?= je($c["content"]) ?>)'
          <i class="bi bi-pencil"></i> 編輯
        </button>
        <form method="POST" action="forum_post.php?id=<?= $post_id ?>" style="margin:0"
              onsubmit="return confirm('確定要刪除這則留言嗎？')">
          <input type="hidden" name="action"     value="delete_comment">
          <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
          <button type="submit" class="btn-sm-d"><i class="bi bi-trash3"></i> 刪除</button>
        </form>
        <?php endif; ?>
        <?php if ($can_report && (int)$c['user_id'] != $current_user_id): ?>
        <button type="button" class="btn-sm-r"
          onclick="openReportModal('comment',<?= $c['id'] ?>,<?= je(mb_strimwidth($c["content"],0,36,"...")) ?>)">
          <i class="bi bi-flag"></i> 檢舉
        </button>
        <?php endif; ?>
      </div>
    </div>
    <div class="cmt-content"><?= htmlspecialchars($c["content"]) ?></div>
  </div>

  <!-- 回覆區 -->
  <div class="replies-area">
    <?php if ($reply_count > 0): ?>
    <button type="button" class="replies-toggle" onclick="toggleReplies(this,<?= $c['id'] ?>)">
      <i class="bi bi-chevron-down arrow"></i>
      <span class="toggle-label"><?= $reply_count ?> 則回覆</span>
    </button>
    <div class="replies-list" id="replies-<?= $c['id'] ?>">
      <?php foreach ($sub_replies as $r):
        $r_author   = $r["nickname"] ?: $r["username"];
        $can_edit_r = $is_logged_in && ((int)$r['user_id'] == $current_user_id || $is_admin);
        $at_name    = $r['parent_nickname'] ?? ($r['parent_username'] ?? '');
      ?>
      <div class="reply-card" id="comment-<?= $r['id'] ?>">
        <div class="reply-top">
          <div class="cmt-meta">
            <div class="av sm"><?= mb_substr($r_author,0,1) ?></div>
            <span class="cmt-author"><?= htmlspecialchars($r_author) ?></span>
            <span class="cmt-time"><?= time_ago($r["created_at"]) ?></span>
            <?php if (!empty($r['updated_at']) && $r['updated_at'] !== $r['created_at']): ?>
            <span style="color:#aab;font-size:11px">(已編輯)</span>
            <?php endif; ?>
          </div>
          <div class="cmt-btns">
            <?php if ($is_logged_in): ?>
            <button type="button" class="btn-sm-rep"
              onclick="setReply(<?= $c['id'] ?>,<?= je($r_author) ?>)">
              <i class="bi bi-reply"></i> 回覆
            </button>
            <?php endif; ?>
            <?php if ($can_edit_r): ?>
            <button type="button" class="btn-sm-e"
              onclick="openEditCommentModal(<?= $r['id'] ?>,<?= je($r['content']) ?>)">
              <i class="bi bi-pencil"></i> 編輯
            </button>
            <form method="POST" action="forum_post.php?id=<?= $post_id ?>" style="margin:0"
                  onsubmit="return confirm('確定要刪除這則留言嗎？')">
              <input type="hidden" name="action"     value="delete_comment">
              <input type="hidden" name="comment_id" value="<?= $r['id'] ?>">
              <button type="submit" class="btn-sm-d"><i class="bi bi-trash3"></i> 刪除</button>
            </form>
            <?php endif; ?>
            <?php if ($can_report && (int)$r['user_id'] != $current_user_id): ?>
            <button type="button" class="btn-sm-r"
              onclick="openReportModal('comment',<?= $r['id'] ?>,<?= je(mb_strimwidth($r["content"],0,36,"...")) ?>)">
              <i class="bi bi-flag"></i> 檢舉
            </button>
            <?php endif; ?>
          </div>
        </div>
        <?php if ($at_name): ?>
        <div class="mention-tag"><i class="bi bi-at"></i><?= htmlspecialchars($at_name) ?></div>
        <?php endif; ?>
        <div class="cmt-content"><?= htmlspecialchars($r["content"]) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- 留言表單 -->
<?php if ($is_logged_in): ?>
<div class="cmt-form-card" id="cmt-form-card">
  <div class="cmt-form-title">
    <i class="bi bi-pencil"></i>
    <span id="form-title">發表留言</span>
  </div>
  <div class="reply-banner" id="reply-banner">
    <span><i class="bi bi-reply me-1"></i>回覆 <strong id="reply-name"></strong></span>
    <button type="button" class="reply-cancel" onclick="cancelReply()">✕</button>
  </div>
  <form method="POST" action="forum_post.php?id=<?= $post_id ?>">
    <input type="hidden" name="action"    value="add_comment">
    <input type="hidden" name="parent_id" id="input-parent" value="">
    <textarea class="fc" name="content" id="cmt-textarea" rows="4" placeholder="輸入您的留言..." required></textarea>
    <button type="submit" class="btn-sub"><i class="bi bi-send me-2"></i>送出留言</button>
  </form>
</div>
<?php else: ?>
<div class="login-prompt">請先 <a href="login.php">登入</a> 才能留言</div>
<?php endif; ?>
</div>

</div><!-- /wrap -->

<!-- Modal：編輯文章 -->
<div class="modal-overlay" id="edit-post-modal">
<div class="modal-box">
  <button class="modal-close" onclick="closeModal('edit-post-modal')">✕</button>
  <div class="modal-title"><i class="bi bi-pencil-square me-2"></i>編輯文章</div>
  <form method="POST" action="forum_post.php?id=<?= $post_id ?>">
    <input type="hidden" name="action"  value="edit_post">
    <input type="hidden" name="post_id" value="<?= $post_id ?>">
    <input type="text" class="modal-inp" name="title"
           value="<?= htmlspecialchars($post['title']) ?>" placeholder="文章標題" required>
    <textarea class="modal-inp" name="content" rows="8"
              placeholder="文章內容" required><?= htmlspecialchars($post['content']) ?></textarea>
    <div class="modal-acts">
      <button type="button" class="btn-mc" onclick="closeModal('edit-post-modal')">取消</button>
      <button type="submit" class="btn-ms"><i class="bi bi-check2 me-1"></i>儲存</button>
    </div>
  </form>
</div>
</div>

<!-- Modal：編輯留言 -->
<div class="modal-overlay" id="edit-comment-modal">
<div class="modal-box">
  <button class="modal-close" onclick="closeModal('edit-comment-modal')">✕</button>
  <div class="modal-title"><i class="bi bi-chat-left-text me-2"></i>編輯留言</div>
  <form method="POST" action="forum_post.php?id=<?= $post_id ?>">
    <input type="hidden" name="action"     value="edit_comment">
    <input type="hidden" name="comment_id" id="edit-cmt-id">
    <textarea class="modal-inp" name="comment_content" id="edit-cmt-content"
              rows="5" placeholder="留言內容" required></textarea>
    <div class="modal-acts">
      <button type="button" class="btn-mc" onclick="closeModal('edit-comment-modal')">取消</button>
      <button type="submit" class="btn-ms"><i class="bi bi-check2 me-1"></i>儲存</button>
    </div>
  </form>
</div>
</div>

<!-- Modal：檢舉 -->
<div class="modal-overlay" id="report-modal">
<div class="modal-box">
  <button class="modal-close" onclick="closeModal('report-modal')">✕</button>
  <div class="modal-title"><i class="bi bi-flag me-2"></i>檢舉內容</div>
  <p class="report-hint">正在檢舉：<strong id="report-label"></strong></p>
  <form method="POST" action="forum_post.php?id=<?= $post_id ?>">
    <input type="hidden" name="action"    value="report">
    <input type="hidden" name="item_type" id="report-type">
    <input type="hidden" name="item_id"   id="report-id">
    <textarea class="modal-inp" name="reason" rows="4"
              placeholder="請描述違規原因，例如：內容不當、廣告、騷擾他人…" required></textarea>
    <div class="modal-acts">
      <button type="button" class="btn-mc" onclick="closeModal('report-modal')">取消</button>
      <button type="submit" class="btn-md"><i class="bi bi-flag me-1"></i>送出檢舉</button>
    </div>
  </form>
</div>
</div>

<div id="toast-msg"></div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
document.querySelectorAll('.modal-overlay').forEach(el => {
  el.addEventListener('click', e => { if (e.target === el) closeModal(el.id); });
});

function openEditCommentModal(id, content) {
  document.getElementById('edit-cmt-id').value      = id;
  document.getElementById('edit-cmt-content').value = content;
  openModal('edit-comment-modal');
}

function openReportModal(type, id, title) {
  document.getElementById('report-type').value  = type;
  document.getElementById('report-id').value    = id;
  document.getElementById('report-label').textContent =
    (type === 'comment' ? '留言' : '貼文') + ' — ' + title;
  openModal('report-modal');
}

function toggleReplies(btn, parentId) {
  const list  = document.getElementById('replies-' + parentId);
  const label = btn.querySelector('.toggle-label');
  const count = list.querySelectorAll('.reply-card').length;
  const isOpen = list.classList.toggle('open');
  btn.classList.toggle('open', isOpen);
  label.textContent = isOpen ? '收起回覆' : count + ' 則回覆';
}

function setReply(parentId, authorName) {
  document.getElementById('input-parent').value = parentId;
  document.getElementById('reply-name').textContent = authorName;
  document.getElementById('reply-banner').classList.add('show');
  document.getElementById('form-title').textContent = '回覆留言';
  document.querySelectorAll('.cmt-card,.reply-card').forEach(el => el.classList.remove('highlight'));
  const target = document.getElementById('comment-' + parentId);
  if (target) target.classList.add('highlight');
  const repliesList = document.getElementById('replies-' + parentId);
  if (repliesList) {
    repliesList.classList.add('open');
    const btn = repliesList.previousElementSibling;
    if (btn && btn.classList.contains('replies-toggle')) {
      btn.classList.add('open');
      const lbl = btn.querySelector('.toggle-label');
      if (lbl) lbl.textContent = '收起回覆';
    }
  }
  document.getElementById('cmt-form-card').scrollIntoView({ behavior: 'smooth', block: 'center' });
  setTimeout(() => document.getElementById('cmt-textarea').focus(), 400);
}

function cancelReply() {
  document.getElementById('input-parent').value = '';
  document.getElementById('reply-banner').classList.remove('show');
  document.getElementById('form-title').textContent = '發表留言';
  document.querySelectorAll('.cmt-card,.reply-card').forEach(el => el.classList.remove('highlight'));
}

function showToast(msg) {
  const t = document.getElementById('toast-msg');
  t.textContent = msg; t.style.opacity = '1';
  clearTimeout(t._t);
  t._t = setTimeout(() => { t.style.opacity = '0'; }, 2000);
}
</script>

<?php require "footer.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>