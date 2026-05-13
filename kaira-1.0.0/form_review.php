<?php
// form_review.php ── 社團端：名單審核
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once "api/db.php";

function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function fmtDT($v){ return $v ? date('Y/m/d H:i', strtotime($v)) : '—'; }

if (empty($_SESSION['role']) || $_SESSION['role'] != 2) {
    header('Location: login.php'); exit;
}
$me = [
    'user_id'  => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'role'     => $_SESSION['role'],
];

// 自動關閉過期表單
$pdo->prepare("UPDATE forms SET status='closed' WHERE status='open' AND close_at IS NOT NULL AND close_at < NOW()")->execute();

$msg = '';

// ── 處理審核動作 ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $subId  = (int)($_POST['sub_id'] ?? 0);
    $note   = trim($_POST['note'] ?? '');

    if (in_array($action, ['approve','reject'])) {
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';
        $chk = $pdo->prepare("
            SELECT s.id FROM form_submissions s
            JOIN forms f ON f.id=s.form_id
            WHERE s.id=? AND f.creator_id=?
        ");
        $chk->execute([$subId, $me['user_id']]);
        if ($chk->fetch()) {
            $pdo->prepare("
                UPDATE form_submissions
                SET status=?, reviewer_id=?, reviewed_at=NOW(), note=?
                WHERE id=?
            ")->execute([$newStatus, $me['user_id'], $note, $subId]);
            $msg = 'ok:審核已更新';
        }
    }

    // 批次審核
    if ($action === 'batch') {
        $ids    = $_POST['sub_ids'] ?? [];
        $batchS = $_POST['batch_status'] ?? 'approved';
        foreach ($ids as $id) {
            $id = (int)$id;
            $pdo->prepare("
                UPDATE form_submissions s
                JOIN forms f ON f.id=s.form_id
                SET s.status=?, s.reviewer_id=?, s.reviewed_at=NOW()
                WHERE s.id=? AND f.creator_id=?
            ")->execute([$batchS, $me['user_id'], $id, $me['user_id']]);
        }
        $msg = 'ok:批次審核完成';
    }
}

// 取得表單列表（屬於本社團）
$formId = (int)($_GET['form_id'] ?? 0);
$myForms = $pdo->prepare("
    SELECT f.id, f.title, f.status, f.quota, f.need_review,
           a.title AS act_title,
           (SELECT COUNT(*) FROM form_submissions s WHERE s.form_id=f.id AND s.status='pending') AS pending_cnt
    FROM forms f
    JOIN activities a ON a.id=f.activity_id
    WHERE f.creator_id=?
    ORDER BY f.created_at DESC
");
$myForms->execute([$me['user_id']]);
$myForms = $myForms->fetchAll(PDO::FETCH_ASSOC);

$currentForm = null;
$submissions = [];
$formFields  = [];

if ($formId) {
    $currentForm = $pdo->prepare("
        SELECT f.*, a.title AS act_title
        FROM forms f JOIN activities a ON a.id=f.activity_id
        WHERE f.id=? AND f.creator_id=?
    ");
    $currentForm->execute([$formId, $me['user_id']]);
    $currentForm = $currentForm->fetch(PDO::FETCH_ASSOC);

    if ($currentForm) {
        $filterStatus = $_GET['filter'] ?? 'all';
        // ★ 修正：u.name → u.username，u.nickname 保留（資料庫有此欄位）
        $sql = "
            SELECT s.*, u.username AS uname, u.nickname AS unick, u.email AS uemail
            FROM form_submissions s
            JOIN users u ON u.user_id=s.user_id
            WHERE s.form_id=?
        ";
        $params = [$formId];
        if ($filterStatus !== 'all') { $sql .= " AND s.status=?"; $params[] = $filterStatus; }
        $sql .= " ORDER BY s.submitted_at ASC";
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 表單欄位
        $ff = $pdo->prepare("SELECT * FROM form_fields WHERE form_id=? ORDER BY sort_order");
        $ff->execute([$formId]);
        $formFields = $ff->fetchAll(PDO::FETCH_ASSOC);

        // 每筆答案
        foreach ($submissions as &$sub) {
            $ans = $pdo->prepare("SELECT field_id, answer FROM form_answers WHERE submission_id=?");
            $ans->execute([$sub['id']]);
            $sub['answers'] = $ans->fetchAll(PDO::FETCH_KEY_PAIR);
        }
        unset($sub);
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>名單審核 · FJU_CLUB</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;700&family=Noto+Sans+TC:wght@300;400;500;700&display=swap" rel="stylesheet">
<style>
:root{--navy:#1a2744;--navy-mid:#243257;--accent:#3a5fa0;--accent-hover:#4a72be;--gold:#c8a96e;--gold-light:#e8c98e;--cream:#f7f4ef;--cream-dark:#ede9e0;--white:#fff;--text-dark:#1a1f2e;--text-mid:#4a5068;--text-muted:#8a91a8;--border-light:rgba(26,39,68,.10);--border-mid:rgba(26,39,68,.18);}
*{box-sizing:border-box;margin:0;padding:0}body{font-family:'Noto Sans TC',sans-serif;background:var(--cream);color:var(--text-dark)}
a{text-decoration:none;color:inherit}
.page-wrap{max-width:1060px;margin:0 auto;padding:2rem 1.5rem}
.page-head{display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap}
.page-head h1{font-family:'Noto Serif TC',serif;font-size:1.4rem;font-weight:700;color:var(--navy);flex:1}
.msg{padding:.75rem 1.2rem;border-radius:8px;font-size:.88rem;margin-bottom:1.2rem}
.msg.ok{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}
.msg.err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.layout{display:grid;grid-template-columns:240px 1fr;gap:1.5rem;align-items:start}
.sidebar-nav{background:var(--white);border:1px solid var(--border-light);border-radius:12px;overflow:hidden}
.sidebar-nav .nav-header{background:var(--navy);padding:.75rem 1rem;font-size:.78rem;font-weight:600;color:rgba(255,255,255,.8);letter-spacing:.06em}
.nav-item{display:flex;align-items:center;justify-content:space-between;padding:.7rem 1rem;border-bottom:1px solid var(--border-light);cursor:pointer;transition:background .15s;color:var(--text-dark);font-size:.83rem}
.nav-item:last-child{border-bottom:none}
.nav-item:hover,.nav-item.active{background:var(--cream)}
.nav-item .ni-title{font-weight:500;line-height:1.3}
.nav-item .ni-sub{font-size:.7rem;color:var(--text-muted)}
.nav-badge{background:var(--accent);color:#fff;font-size:.65rem;font-weight:700;padding:.1rem .45rem;border-radius:999px;flex-shrink:0}
.main-card{background:var(--white);border:1px solid var(--border-light);border-radius:14px;overflow:hidden}
.main-card-header{background:var(--navy);padding:.9rem 1.4rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap}
.main-card-header h2{font-size:.9rem;font-weight:600;color:rgba(255,255,255,.9)}
.filter-bar{display:flex;gap:.5rem;padding:1rem 1.4rem;border-bottom:1px solid var(--border-light);flex-wrap:wrap;align-items:center}
.filter-btn{font-size:.78rem;padding:.35rem .9rem;border-radius:6px;border:1px solid var(--border-mid);background:var(--cream);color:var(--text-mid);cursor:pointer;font-family:inherit;transition:background .15s}
.filter-btn:hover,.filter-btn.active{background:var(--accent);color:#fff;border-color:var(--accent)}
.stats-row{display:flex;gap:1.5rem;padding:.8rem 1.4rem;background:var(--cream);border-bottom:1px solid var(--border-light);flex-wrap:wrap}
.stat-box{text-align:center}
.stat-num{font-family:'Noto Serif TC',serif;font-size:1.3rem;font-weight:700;color:var(--navy)}
.stat-lbl{font-size:.68rem;color:var(--text-muted);letter-spacing:.04em}
.sub-table{width:100%;border-collapse:collapse}
.sub-table th{font-size:.72rem;font-weight:600;color:var(--text-muted);letter-spacing:.06em;padding:.6rem 1rem;border-bottom:2px solid var(--border-light);text-align:left;white-space:nowrap}
.sub-table td{font-size:.82rem;padding:.7rem 1rem;border-bottom:1px solid var(--border-light);vertical-align:top}
.sub-table tr:last-child td{border-bottom:none}
.sub-table tr:hover td{background:var(--cream)}
.status-badge{display:inline-block;font-size:.65rem;font-weight:700;letter-spacing:.06em;padding:.18rem .55rem;border-radius:4px}
.status-badge.pending{background:#fefce8;color:#854d0e}
.status-badge.approved{background:#f0fdf4;color:#166534}
.status-badge.rejected{background:#fef2f2;color:#991b1b}
.confirmed-icon{font-size:.85rem}
.btn-approve{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;padding:.3rem .75rem;border-radius:6px;font-size:.75rem;cursor:pointer;font-family:inherit;font-weight:600}
.btn-reject{background:#fef2f2;color:#c0392b;border:1px solid #fecaca;padding:.3rem .75rem;border-radius:6px;font-size:.75rem;cursor:pointer;font-family:inherit;font-weight:600}
.btn-approve:hover{background:#dcfce7}.btn-reject:hover{background:#fee2e2}
.batch-bar{display:flex;gap:.7rem;align-items:center;padding:.8rem 1.4rem;border-bottom:1px solid var(--border-light);background:#f8f9ff;flex-wrap:wrap}
.batch-bar label{font-size:.8rem;font-weight:500;color:var(--text-mid)}
.btn-batch-approve{background:var(--accent);color:#fff;border:none;padding:.4rem 1rem;border-radius:6px;font-size:.8rem;cursor:pointer;font-family:inherit;font-weight:600}
.btn-batch-reject{background:#fef2f2;color:#c0392b;border:1px solid #fecaca;padding:.4rem 1rem;border-radius:6px;font-size:.8rem;cursor:pointer;font-family:inherit}
.note-input{font-size:.78rem;padding:.3rem .6rem;border:1px solid var(--border-mid);border-radius:6px;width:130px;font-family:inherit}
.btn-gold{background:var(--gold);color:var(--navy);border:none;padding:.5rem 1.1rem;border-radius:7px;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit}
.empty-state{text-align:center;padding:3rem 1rem;color:var(--text-muted);font-size:.88rem}
@media(max-width:700px){.layout{grid-template-columns:1fr}.sidebar-nav{margin-bottom:1rem}}
</style>
</head>
<body>
<?php require_once __DIR__ . "/header.php"; ?>
<div class="page-wrap">

<div class="page-head">
  <h1>名單審核</h1>
  <a href="form_manage.php" class="btn-gold">← 表單管理</a>
</div>

<?php if ($msg): $isOk=str_starts_with($msg,'ok:'); ?>
  <div class="msg <?= $isOk?'ok':'err' ?>"><?= h(substr($msg,3)) ?></div>
<?php endif; ?>

<div class="layout">
  <!-- 左：表單選擇 -->
  <div class="sidebar-nav">
    <div class="nav-header">我的表單</div>
    <?php foreach ($myForms as $f): ?>
      <a href="form_review.php?form_id=<?= h($f['id']) ?>"
         class="nav-item <?= $formId==$f['id']?'active':'' ?>">
        <div>
          <div class="ni-title"><?= h($f['title']) ?></div>
          <div class="ni-sub"><?= h($f['act_title']) ?></div>
        </div>
        <?php if ($f['pending_cnt']>0): ?>
          <span class="nav-badge"><?= h($f['pending_cnt']) ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- 右：名單 -->
  <div>
  <?php if (!$currentForm): ?>
    <div class="main-card">
      <div class="empty-state">← 請從左側選擇一個表單來查看報名名單</div>
    </div>
  <?php else:
    $submissions_all = (function() use($pdo,$formId){
        $s=$pdo->prepare("SELECT status, confirmed FROM form_submissions WHERE form_id=?");
        $s->execute([$formId]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    })();
    $total    = count($submissions_all);
    $pending  = count(array_filter($submissions_all, fn($r)=>$r['status']==='pending'));
    $approved = count(array_filter($submissions_all, fn($r)=>$r['status']==='approved'));
    $rejected = count(array_filter($submissions_all, fn($r)=>$r['status']==='rejected'));
    $confirmed= count(array_filter($submissions_all, fn($r)=>$r['confirmed']==1));
    $filterStatus = $_GET['filter'] ?? 'all';
  ?>
    <div class="main-card">
      <div class="main-card-header">
        <h2><?= h($currentForm['title']) ?></h2>
        <span style="font-size:.78rem;color:rgba(255,255,255,.5)"><?= h($currentForm['act_title']) ?></span>
      </div>

      <!-- 統計 -->
      <div class="stats-row">
        <div class="stat-box"><div class="stat-num"><?= $total ?></div><div class="stat-lbl">總報名</div></div>
        <div class="stat-box"><div class="stat-num" style="color:#854d0e"><?= $pending ?></div><div class="stat-lbl">待審核</div></div>
        <div class="stat-box"><div class="stat-num" style="color:#166534"><?= $approved ?></div><div class="stat-lbl">已通過</div></div>
        <div class="stat-box"><div class="stat-num" style="color:#c0392b"><?= $rejected ?></div><div class="stat-lbl">已拒絕</div></div>
        <div class="stat-box"><div class="stat-num" style="color:var(--accent)"><?= $confirmed ?></div><div class="stat-lbl">已確認參與</div></div>
        <?php if ($currentForm['quota']): ?>
          <div class="stat-box"><div class="stat-num"><?= h($currentForm['quota']) ?></div><div class="stat-lbl">名額上限</div></div>
        <?php endif; ?>
      </div>

      <!-- 過濾 -->
      <div class="filter-bar">
        <span style="font-size:.78rem;color:var(--text-muted);font-weight:600">篩選：</span>
        <?php foreach(['all'=>'全部','pending'=>'待審核','approved'=>'已通過','rejected'=>'已拒絕'] as $k=>$lbl): ?>
          <a href="form_review.php?form_id=<?= h($formId) ?>&filter=<?= $k ?>"
             class="filter-btn <?= $filterStatus===$k?'active':'' ?>"><?= $lbl ?></a>
        <?php endforeach; ?>
      </div>

      <!-- 批次 -->
      <?php if (!empty($submissions) && $currentForm['need_review']): ?>
      <form method="post" id="batchForm">
        <input type="hidden" name="action" value="batch">
        <div class="batch-bar">
          <label><input type="checkbox" id="checkAll" onchange="toggleAll(this)"> 全選</label>
          <button type="submit" name="batch_status" value="approved" class="btn-batch-approve" onclick="return confirm('批次通過選取項目？')">批次通過</button>
          <button type="submit" name="batch_status" value="rejected" class="btn-batch-reject" onclick="return confirm('批次拒絕選取項目？')">批次拒絕</button>
        </div>
      <?php endif; ?>

      <!-- 名單表格 -->
      <?php if (empty($submissions)): ?>
        <div class="empty-state">目前沒有符合條件的報名紀錄</div>
      <?php else: ?>
      <div style="overflow-x:auto">
      <table class="sub-table">
        <thead>
          <tr>
            <?php if ($currentForm['need_review']): ?><th><input type="checkbox" id="checkAll2" onchange="toggleAll(this)"></th><?php endif; ?>
            <th>#</th><th>學生</th>
            <?php foreach ($formFields as $ff): ?><th><?= h($ff['label']) ?></th><?php endforeach; ?>
            <th>狀態</th><th>確認參與</th><th>備注</th>
            <?php if ($currentForm['need_review']): ?><th>操作</th><?php endif; ?>
            <th>報名時間</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($submissions as $idx=>$sub): ?>
          <tr>
            <?php if ($currentForm['need_review']): ?>
              <td><input type="checkbox" name="sub_ids[]" value="<?= h($sub['id']) ?>" form="batchForm" class="sub-check"></td>
            <?php endif; ?>
            <td style="color:var(--text-muted)"><?= $idx+1 ?></td>
            <td>
              <!-- ★ 修正：顯示 username，nickname 有值才顯示 -->
              <strong><?= h($sub['uname']) ?></strong>
              <?php if (!empty($sub['unick'])): ?>
                <br><span style="font-size:.72rem;color:var(--text-muted)"><?= h($sub['unick']) ?></span>
              <?php endif; ?>
              <br><span style="font-size:.7rem;color:var(--text-muted)"><?= h($sub['uemail']) ?></span>
            </td>
            <?php foreach ($formFields as $ff): ?>
              <td style="max-width:160px;word-break:break-all"><?= h($sub['answers'][$ff['id']] ?? '—') ?></td>
            <?php endforeach; ?>
            <td><span class="status-badge <?= h($sub['status']) ?>"><?= ['pending'=>'待審核','approved'=>'已通過','rejected'=>'已拒絕'][$sub['status']] ?></span></td>
            <td class="confirmed-icon"><?= $sub['confirmed'] ? '✅' : '⬜' ?></td>
            <td style="max-width:120px;font-size:.75rem;color:var(--text-mid)"><?= h($sub['note'] ?: '—') ?></td>
            <?php if ($currentForm['need_review']): ?>
            <td>
              <form method="post" style="display:flex;gap:.3rem;flex-wrap:wrap;align-items:center">
                <input type="hidden" name="sub_id" value="<?= h($sub['id']) ?>">
                <input type="text" name="note" class="note-input" placeholder="備注（選填）" value="<?= h($sub['note']) ?>">
                <button type="submit" name="action" value="approve" class="btn-approve">✓ 通過</button>
                <button type="submit" name="action" value="reject"  class="btn-reject">✕ 拒絕</button>
              </form>
            </td>
            <?php endif; ?>
            <td style="white-space:nowrap;font-size:.75rem;color:var(--text-muted)"><?= fmtDT($sub['submitted_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php endif; ?>

      <?php if (!empty($submissions) && $currentForm['need_review']): ?></form><?php endif; ?>
    </div>
  <?php endif; ?>
  </div>
</div>

</div>
<script>
function toggleAll(src){
  document.querySelectorAll('.sub-check').forEach(c=>c.checked=src.checked);
  const other=document.querySelector(src.id==='checkAll'?'#checkAll2':'#checkAll');
  if(other) other.checked=src.checked;
}
</script>
<?php require_once __DIR__ . "/footer.php"; ?>
</body>
</html>