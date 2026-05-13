<?php
// my_applications.php ── 學生端：我的報名紀錄
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/api/db.php";

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function fmtDT($v){ return $v ? date('Y/m/d H:i', strtotime($v)) : '—'; }

if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }
$me = $_SESSION['user'];

// 自動關閉
$pdo->prepare("UPDATE forms SET status='closed' WHERE status='open' AND close_at IS NOT NULL AND close_at < NOW()")->execute();

// 取得所有報名紀錄
$subs = $pdo->prepare("
    SELECT s.*,
           f.title AS form_title, f.need_review, f.status AS form_status,
           a.title AS act_title, a.event_start, a.location, a.organizer
    FROM form_submissions s
    JOIN forms f ON f.id=s.form_id
    JOIN activities a ON a.id=f.activity_id
    WHERE s.user_id=?
    ORDER BY s.submitted_at DESC
");
$subs->execute([$me['user_id']]);
$subs = $subs->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>我的報名紀錄 · FJU_CLUB</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;700&family=Noto+Sans+TC:wght@300;400;500;700&display=swap" rel="stylesheet">
<style>
:root{--navy:#1a2744;--accent:#3a5fa0;--accent-hover:#4a72be;--gold:#c8a96e;--gold-light:#e8c98e;--cream:#f7f4ef;--cream-dark:#ede9e0;--white:#fff;--text-dark:#1a1f2e;--text-mid:#4a5068;--text-muted:#8a91a8;--border-light:rgba(26,39,68,.10);--border-mid:rgba(26,39,68,.18);}
*{box-sizing:border-box;margin:0;padding:0}body{font-family:'Noto Sans TC',sans-serif;background:var(--cream);color:var(--text-dark)}
a{text-decoration:none;color:inherit}
.page-wrap{max-width:760px;margin:0 auto;padding:2rem 1.5rem}
.page-head{display:flex;align-items:center;gap:1rem;margin-bottom:2rem;flex-wrap:wrap}
.page-head h1{font-family:'Noto Serif TC',serif;font-size:1.4rem;font-weight:700;color:var(--navy);flex:1}
.sec-label{display:flex;align-items:center;gap:.6rem;margin-bottom:1.2rem}
.sec-label::before{content:'';width:4px;height:1.15em;background:var(--gold);border-radius:2px;flex-shrink:0}
.sec-label h2{font-family:'Noto Serif TC',serif;font-size:1.05rem;font-weight:700;color:var(--navy)}
/* 報名卡片 */
.sub-card{background:var(--white);border:1px solid var(--border-light);border-radius:14px;overflow:hidden;margin-bottom:1.2rem;transition:box-shadow .2s}
.sub-card:hover{box-shadow:0 4px 20px rgba(26,39,68,.08)}
.sub-card-head{background:var(--navy);padding:.8rem 1.3rem;display:flex;align-items:center;justify-content:space-between;gap:.8rem;flex-wrap:wrap}
.sub-card-head .form-name{font-size:.88rem;font-weight:600;color:#fff}
.sub-card-head .act-name{font-size:.75rem;color:rgba(255,255,255,.5)}
.status-badge{display:inline-block;font-size:.68rem;font-weight:700;letter-spacing:.06em;padding:.2rem .65rem;border-radius:5px}
.status-badge.pending{background:#fefce8;color:#854d0e}
.status-badge.approved{background:#f0fdf4;color:#166534}
.status-badge.rejected{background:#fef2f2;color:#991b1b}
.sub-card-body{padding:1rem 1.3rem}
.meta-row{display:flex;gap:1.5rem;flex-wrap:wrap;margin-bottom:.8rem}
.meta-item{font-size:.78rem;color:var(--text-muted);display:flex;align-items:center;gap:.3rem}
.meta-item strong{color:var(--text-dark);font-weight:500}
.note-box{background:var(--cream);border-radius:7px;padding:.6rem .9rem;font-size:.8rem;color:var(--text-mid);border-left:3px solid var(--gold);margin-bottom:.8rem}
/* 確認按鈕 */
.confirm-box{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:.9rem 1.1rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap}
.confirm-box p{font-size:.83rem;color:#166534;font-weight:500}
.btn-confirm{background:#166534;color:#fff;border:none;padding:.5rem 1.3rem;border-radius:8px;font-size:.83rem;font-weight:700;cursor:pointer;font-family:inherit;transition:background .18s}
.btn-confirm:hover{background:#14532d}
.confirmed-row{background:#dcfce7;border-radius:8px;padding:.6rem .9rem;font-size:.82rem;color:#166534;font-weight:600}
.sub-card-foot{padding:.7rem 1.3rem;background:var(--cream);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem}
.sub-card-foot .time-info{font-size:.72rem;color:var(--text-muted)}
.btn-view{font-size:.78rem;padding:.3rem .9rem;background:var(--white);border:1px solid var(--border-mid);border-radius:6px;color:var(--accent);font-weight:500;transition:background .18s}
.btn-view:hover{background:rgba(58,95,160,.06)}
.empty-state{text-align:center;padding:4rem 1rem;color:var(--text-muted)}
.empty-state .icon{font-size:3rem;margin-bottom:1rem}
.empty-state p{font-size:.9rem}
.empty-state a{color:var(--accent);font-weight:500}
</style>
</head>
<body>
<?php require_once __DIR__ . "/header.php"; ?>
<div class="page-wrap">
<div class="page-head">
  <h1>我的報名紀錄</h1>
</div>

<?php if (empty($subs)): ?>
  <div class="empty-state">
    <div class="icon">📋</div>
    <p>你還沒有報名任何活動</p>
    <p style="margin-top:.5rem"><a href="activities.php">瀏覽活動 →</a></p>
  </div>
<?php else:
  // 分組：需要確認的 vs 其他
  $needConfirm = array_filter($subs, fn($s)=>$s['status']==='approved'&&!$s['confirmed']);
  if (!empty($needConfirm)): ?>
    <div class="sec-label"><h2>⚡ 待確認參與</h2></div>
  <?php endif;

  foreach ($subs as $sub):
    $isApprovedUnconfirmed = ($sub['status']==='approved' && !$sub['confirmed']);
?>
  <div class="sub-card">
    <div class="sub-card-head">
      <div>
        <div class="form-name"><?= h($sub['form_title']) ?></div>
        <div class="act-name"><?= h($sub['act_title']) ?></div>
      </div>
      <span class="status-badge <?= h($sub['status']) ?>"><?= ['pending'=>'待審核','approved'=>'審核通過','rejected'=>'未通過'][$sub['status']] ?></span>
    </div>

    <div class="sub-card-body">
      <div class="meta-row">
        <div class="meta-item">📅 <strong><?= fmtDT($sub['event_start']) ?></strong></div>
        <div class="meta-item">📍 <strong><?= h($sub['location']) ?></strong></div>
        <div class="meta-item">🏛 <strong><?= h($sub['organizer']) ?></strong></div>
      </div>

      <?php if ($sub['note']): ?>
        <div class="note-box">💬 社團備注：<?= h($sub['note']) ?></div>
      <?php endif; ?>

      <?php if ($isApprovedUnconfirmed): ?>
        <div class="confirm-box">
          <p>🎉 你的報名已通過！請確認你將出席此活動</p>
          <form method="post" action="form_apply.php?form_id=<?= h($sub['form_id']) ?>">
            <input type="hidden" name="action" value="confirm">
            <button type="submit" class="btn-confirm">✓ 確認參與</button>
          </form>
        </div>
      <?php elseif ($sub['confirmed']): ?>
        <div class="confirmed-row">✅ 已確認參與（<?= fmtDT($sub['confirmed_at']) ?>）</div>
      <?php endif; ?>
    </div>

    <div class="sub-card-foot">
      <span class="time-info">
        報名時間：<?= fmtDT($sub['submitted_at']) ?>
        <?php if ($sub['reviewed_at']): ?> · 審核：<?= fmtDT($sub['reviewed_at']) ?><?php endif; ?>
      </span>
      <a href="form_apply.php?form_id=<?= h($sub['form_id']) ?>" class="btn-view">查看詳情 →</a>
    </div>
  </div>
<?php endforeach; endif; ?>
</div>
<?php require_once __DIR__ . "/footer.php"; ?>
</body>
</html>
