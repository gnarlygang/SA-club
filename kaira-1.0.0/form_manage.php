<?php
// form_manage.php  ── 社團端：表單管理（建立 / 編輯 / 列表）
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once "api/db.php";

function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

// ── 權限：僅 role=2（社團帳號）可用 ────────────────────────────
// 改後
if (empty($_SESSION['role']) || $_SESSION['role'] != 2) {
    header('Location: login.php'); exit;
}
$me = [
    'user_id'  => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'role'     => $_SESSION['role'],
];

// 取得該社團可用的活動列表
$myActivities = $pdo->prepare("
    SELECT id, title FROM activities WHERE user_id = ? ORDER BY event_start DESC
");
$myActivities->execute([$me['user_id']]);
$myActivities = $myActivities->fetchAll(PDO::FETCH_ASSOC);

// ── 處理新增 / 編輯 表單 ────────────────────────────────────────
$msg = '';
$editForm = null;
$editFields = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 建立或更新表單
    if ($action === 'save_form') {
        $actId      = (int)($_POST['activity_id'] ?? 0);
        $title      = trim($_POST['title'] ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $quota      = ($_POST['quota'] !== '') ? (int)$_POST['quota'] : null;
        $closeAt    = (!empty($_POST['close_at'])) ? $_POST['close_at'] : null;
        $needReview = isset($_POST['need_review']) ? 1 : 0;
        $status     = $_POST['status'] ?? 'open';
        $formId     = (int)($_POST['form_id'] ?? 0);

        // 驗證活動屬於本社團
        $chk = $pdo->prepare("SELECT id FROM activities WHERE id=? AND user_id=?");
        $chk->execute([$actId, $me['user_id']]);
        if (!$chk->fetch()) {
            $msg = 'error:活動不存在或無權限';
        } elseif (empty($title)) {
            $msg = 'error:請填寫表單標題';
        } else {
            if ($formId) {
                // update
                $upd = $pdo->prepare("
                    UPDATE forms SET activity_id=?,title=?,description=?,quota=?,
                    close_at=?,need_review=?,status=? WHERE id=? AND creator_id=?
                ");
                $upd->execute([$actId,$title,$desc,$quota,$closeAt,$needReview,$status,$formId,$me['user_id']]);
            } else {
                // insert
                $ins = $pdo->prepare("
                    INSERT INTO forms (activity_id,club_id,creator_id,title,description,quota,close_at,need_review,status)
                    VALUES (?,?,?,?,?,?,?,?,?)
                ");
                // club_id: 從 clubs 找
                $clubRow = $pdo->prepare("SELECT id FROM clubs WHERE user_id=? LIMIT 1");
                $clubRow->execute([$me['user_id']]);
                $clubRow = $clubRow->fetch(PDO::FETCH_ASSOC);
                $clubId  = $clubRow ? $clubRow['id'] : 0;
                $ins->execute([$actId,$clubId,$me['user_id'],$title,$desc,$quota,$closeAt,$needReview,$status]);
                $formId = $pdo->lastInsertId();
            }

            // 儲存自訂欄位
            $pdo->prepare("DELETE FROM form_fields WHERE form_id=?")->execute([$formId]);
            $labels    = $_POST['field_label']    ?? [];
            $types     = $_POST['field_type']     ?? [];
            $opts      = $_POST['field_options']  ?? [];
            $reqs      = $_POST['field_required'] ?? [];
            foreach ($labels as $i => $lbl) {
                $lbl = trim($lbl);
                if ($lbl === '') continue;
                $pdo->prepare("
                    INSERT INTO form_fields (form_id,label,field_type,options,is_required,sort_order)
                    VALUES (?,?,?,?,?,?)
                ")->execute([
                    $formId, $lbl,
                    $types[$i] ?? 'text',
                    trim($opts[$i] ?? ''),
                    isset($reqs[$i]) ? 1 : 0,
                    $i
                ]);
            }
            $msg = 'ok:表單已儲存！';
        }
    }

    // 切換表單狀態
    if ($action === 'toggle_status') {
        $formId = (int)($_POST['form_id'] ?? 0);
        $toStatus = $_POST['to_status'] ?? 'closed';
        $pdo->prepare("UPDATE forms SET status=? WHERE id=? AND creator_id=?")
            ->execute([$toStatus, $formId, $me['user_id']]);
        $msg = 'ok:狀態已更新';
    }
}

// 載入要編輯的表單
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editForm = $pdo->prepare("SELECT * FROM forms WHERE id=? AND creator_id=?");
    $editForm->execute([$editId, $me['user_id']]);
    $editForm = $editForm->fetch(PDO::FETCH_ASSOC);
    if ($editForm) {
        $ef = $pdo->prepare("SELECT * FROM form_fields WHERE form_id=? ORDER BY sort_order");
        $ef->execute([$editId]);
        $editFields = $ef->fetchAll(PDO::FETCH_ASSOC);
    }
}

// 列表
$myForms = $pdo->prepare("
    SELECT f.*, a.title AS act_title,
           (SELECT COUNT(*) FROM form_submissions s WHERE s.form_id=f.id) AS sub_count
    FROM forms f
    JOIN activities a ON a.id=f.activity_id
    WHERE f.creator_id=?
    ORDER BY f.created_at DESC
");
$myForms->execute([$me['user_id']]);
$myForms = $myForms->fetchAll(PDO::FETCH_ASSOC);

// 自動關閉：若 close_at 已過，更新為 closed
$pdo->prepare("UPDATE forms SET status='closed' WHERE status='open' AND close_at IS NOT NULL AND close_at < NOW()")->execute();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>表單管理 · FJU_CLUB</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;700&family=Noto+Sans+TC:wght@300;400;500;700&display=swap" rel="stylesheet">
<style>
:root{--navy:#1a2744;--navy-mid:#243257;--accent:#3a5fa0;--accent-hover:#4a72be;--gold:#c8a96e;--gold-light:#e8c98e;--cream:#f7f4ef;--cream-dark:#ede9e0;--white:#fff;--text-dark:#1a1f2e;--text-mid:#4a5068;--text-muted:#8a91a8;--border-light:rgba(26,39,68,.10);--border-mid:rgba(26,39,68,.18);}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Noto Sans TC',sans-serif;background:var(--cream);color:var(--text-dark);min-height:100vh}
a{text-decoration:none;color:inherit}
.page-wrap{max-width:1000px;margin:0 auto;padding:2rem 1.5rem}
/* 頁首 */
.page-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem}
.page-head h1{font-family:'Noto Serif TC',serif;font-size:1.5rem;font-weight:700;color:var(--navy)}
.page-head h1 span{color:var(--gold);font-size:1rem;margin-left:.5rem;font-family:'Noto Sans TC',sans-serif;font-weight:400}
/* Msg */
.msg{padding:.75rem 1.2rem;border-radius:8px;font-size:.88rem;margin-bottom:1.5rem}
.msg.ok{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}
.msg.err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
/* 卡片 */
.card{background:var(--white);border:1px solid var(--border-light);border-radius:14px;overflow:hidden;margin-bottom:2rem}
.card-header{background:var(--navy);padding:.9rem 1.4rem;display:flex;align-items:center;justify-content:space-between;gap:1rem}
.card-header h2{font-size:.9rem;font-weight:600;color:rgba(255,255,255,.9);letter-spacing:.06em}
.card-body{padding:1.5rem}
/* Form 元素 */
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem}
.field-row.full{grid-template-columns:1fr}
.field-row.three{grid-template-columns:1fr 1fr 1fr}
label{display:block;font-size:.78rem;font-weight:600;color:var(--text-mid);margin-bottom:.35rem;letter-spacing:.04em}
input[type=text],input[type=datetime-local],input[type=number],select,textarea{
  width:100%;padding:.55rem .85rem;border:1px solid var(--border-mid);border-radius:8px;
  font-size:.88rem;font-family:inherit;color:var(--text-dark);background:var(--white);
  transition:border-color .2s,box-shadow .2s;outline:none}
input:focus,select:focus,textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(58,95,160,.12)}
textarea{resize:vertical;min-height:80px}
.hint{font-size:.72rem;color:var(--text-muted);margin-top:.25rem}
/* 切換 */
.toggle-row{display:flex;align-items:center;gap:.7rem;margin-bottom:1rem}
.toggle-row label{margin:0;font-size:.85rem;font-weight:500;color:var(--text-dark);cursor:pointer}
input[type=checkbox]{width:16px;height:16px;accent-color:var(--accent);cursor:pointer}
/* 自訂欄位區 */
.fields-section{border:1px solid var(--border-light);border-radius:10px;padding:1.2rem;margin-top:1rem;background:var(--cream)}
.fields-section h3{font-size:.82rem;font-weight:700;color:var(--navy);margin-bottom:1rem;letter-spacing:.06em}
.custom-field-row{display:grid;grid-template-columns:2fr 1fr 2fr auto auto;gap:.6rem;align-items:center;margin-bottom:.6rem}
.custom-field-row input,.custom-field-row select{font-size:.82rem}
.btn-rm{background:none;border:none;color:#c0392b;font-size:1.1rem;cursor:pointer;padding:.2rem .4rem;border-radius:4px;line-height:1}
.btn-rm:hover{background:#fef2f2}
.btn-add-field{background:none;border:1px dashed var(--border-mid);color:var(--accent);font-size:.8rem;font-weight:500;padding:.45rem 1rem;border-radius:8px;cursor:pointer;margin-top:.4rem;width:100%}
.btn-add-field:hover{background:rgba(58,95,160,.06)}
/* 按鈕 */
.btn-primary{background:var(--accent);color:#fff;border:none;padding:.6rem 1.6rem;border-radius:8px;font-size:.88rem;font-weight:600;cursor:pointer;font-family:inherit;transition:background .18s}
.btn-primary:hover{background:var(--accent-hover)}
.btn-gold{background:var(--gold);color:var(--navy);border:none;padding:.55rem 1.3rem;border-radius:7px;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit;transition:background .18s}
.btn-gold:hover{background:var(--gold-light)}
.btn-ghost{background:transparent;color:var(--text-mid);border:1px solid var(--border-mid);padding:.55rem 1.1rem;border-radius:7px;font-size:.82rem;cursor:pointer;font-family:inherit;transition:background .18s}
.btn-ghost:hover{background:var(--cream-dark)}
.btn-danger{background:#fef2f2;color:#c0392b;border:1px solid #fecaca;padding:.45rem 1rem;border-radius:7px;font-size:.78rem;cursor:pointer;font-family:inherit}
/* 表單列表 */
.form-list{display:flex;flex-direction:column;gap:.8rem}
.form-item{display:grid;grid-template-columns:1fr auto;gap:1rem;align-items:center;background:var(--white);border:1px solid var(--border-light);border-radius:12px;padding:1rem 1.2rem;transition:box-shadow .2s}
.form-item:hover{box-shadow:0 4px 18px rgba(26,39,68,.08)}
.fi-title{font-size:.95rem;font-weight:600;color:var(--text-dark);margin-bottom:.2rem}
.fi-meta{font-size:.75rem;color:var(--text-muted)}
.fi-badges{display:flex;gap:.4rem;margin-top:.4rem;flex-wrap:wrap}
.badge{font-size:.65rem;font-weight:700;letter-spacing:.06em;padding:.15rem .5rem;border-radius:4px}
.badge.open{background:#f0fdf4;color:#166534}
.badge.closed{background:#fef2f2;color:#991b1b}
.badge.draft{background:#fefce8;color:#854d0e}
.badge.review{background:#f0f4ff;color:var(--accent)}
.fi-actions{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;justify-content:flex-end}
.sec-label{display:flex;align-items:center;gap:.6rem;margin-bottom:1.2rem}
.sec-label h2{font-family:'Noto Serif TC',serif;font-size:1.1rem;font-weight:700;color:var(--navy)}
.sec-label::before{content:'';width:4px;height:1.15em;background:var(--gold);border-radius:2px;flex-shrink:0}
@media(max-width:640px){
  .field-row,.field-row.three{grid-template-columns:1fr}
  .custom-field-row{grid-template-columns:1fr 1fr;grid-template-rows:auto auto}
  .form-item{grid-template-columns:1fr}
}
</style>
</head>
<body>
<?php require_once __DIR__ . "/header.php"; ?>

<div class="page-wrap">
<div class="page-head">
  <h1>表單管理 <span>社團端</span></h1>
  <a href="form_review.php" class="btn-gold">名單審核 →</a>
</div>

<?php if ($msg): 
  $isOk = str_starts_with($msg, 'ok:');
  $text = substr($msg,3);
?>
  <div class="msg <?= $isOk?'ok':'err' ?>"><?= h($text) ?></div>
<?php endif; ?>

<!-- ══ 建立 / 編輯 表單 ══════════════════════════════════════ -->
<div class="card">
  <div class="card-header">
    <h2><?= $editForm ? '✏️ 編輯表單' : '＋ 建立新表單' ?></h2>
    <?php if ($editForm): ?>
      <a href="form_manage.php" class="btn-ghost" style="font-size:.78rem;padding:.3rem .8rem">取消編輯</a>
    <?php endif; ?>
  </div>
  <div class="card-body">
  <form method="post" id="formBuilder">
    <input type="hidden" name="action" value="save_form">
    <input type="hidden" name="form_id" value="<?= h($editForm['id'] ?? '') ?>">

    <div class="field-row">
      <div>
        <label>關聯活動 *</label>
        <select name="activity_id" required>
          <option value="">── 請選擇活動 ──</option>
          <?php foreach ($myActivities as $act): ?>
            <option value="<?= h($act['id']) ?>" <?= ($editForm['activity_id'] ?? '') == $act['id'] ? 'selected' : '' ?>>
              <?= h($act['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>表單標題 *</label>
        <input type="text" name="title" placeholder="例：春季成果發表會報名" value="<?= h($editForm['title'] ?? '') ?>" required>
      </div>
    </div>

    <div class="field-row full">
      <label>表單說明</label>
      <textarea name="description" placeholder="說明填表注意事項、報名須知…"><?= h($editForm['description'] ?? '') ?></textarea>
    </div>

    <div class="field-row three">
      <div>
        <label>名額上限</label>
        <input type="number" name="quota" min="1" placeholder="留空=不限" value="<?= h($editForm['quota'] ?? '') ?>">
        <div class="hint">超過名額後自動拒絕新報名</div>
      </div>
      <div>
        <label>自動關閉時間</label>
        <input type="datetime-local" name="close_at" value="<?= h($editForm['close_at'] ? date('Y-m-d\TH:i', strtotime($editForm['close_at'])) : '') ?>">
        <div class="hint">到時自動將表單設為關閉</div>
      </div>
      <div>
        <label>表單狀態</label>
        <select name="status">
          <option value="open"   <?= ($editForm['status']??'open')==='open'  ?'selected':'' ?>>開放中</option>
          <option value="closed" <?= ($editForm['status']??'')==='closed'    ?'selected':'' ?>>已關閉</option>
          <option value="draft"  <?= ($editForm['status']??'')==='draft'     ?'selected':'' ?>>草稿</option>
        </select>
      </div>
    </div>

    <div class="toggle-row">
      <input type="checkbox" id="need_review" name="need_review" value="1" <?= ($editForm['need_review'] ?? 1) ? 'checked' : '' ?>>
      <label for="need_review">啟用名單審核（社團需手動審核每位報名者）</label>
    </div>

    <!-- 自訂欄位 -->
    <div class="fields-section">
      <h3>📋 自訂表單欄位</h3>
      <div style="display:grid;grid-template-columns:2fr 1fr 2fr auto auto;gap:.6rem;margin-bottom:.4rem">
        <span style="font-size:.72rem;color:var(--text-muted);font-weight:600">欄位名稱</span>
        <span style="font-size:.72rem;color:var(--text-muted);font-weight:600">類型</span>
        <span style="font-size:.72rem;color:var(--text-muted);font-weight:600">選項（用 | 分隔）</span>
        <span style="font-size:.72rem;color:var(--text-muted);font-weight:600">必填</span>
        <span></span>
      </div>
      <div id="fieldsContainer">
        <?php if ($editFields): foreach ($editFields as $f): ?>
        <div class="custom-field-row">
          <input type="text" name="field_label[]" placeholder="如：姓名、系所…" value="<?= h($f['label']) ?>" required>
          <select name="field_type[]">
            <?php foreach(['text'=>'短文字','textarea'=>'長文字','select'=>'下拉選單','radio'=>'單選','checkbox'=>'複選','number'=>'數字','email'=>'Email','tel'=>'電話'] as $v=>$lbl): ?>
              <option value="<?= $v ?>" <?= $f['field_type']===$v?'selected':'' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="field_options[]" placeholder="選A|選B|選C" value="<?= h($f['options']) ?>">
          <input type="checkbox" name="field_required[<?= $loop ?? 0 ?>]" value="1" <?= $f['is_required']?'checked':'' ?> style="width:16px;height:16px;margin:auto">
          <button type="button" class="btn-rm" onclick="this.closest('.custom-field-row').remove()">✕</button>
        </div>
        <?php endforeach; else: ?>
        <div class="custom-field-row">
          <input type="text" name="field_label[]" placeholder="如：姓名" required>
          <select name="field_type[]">
            <option value="text">短文字</option><option value="textarea">長文字</option>
            <option value="select">下拉選單</option><option value="radio">單選</option>
            <option value="checkbox">複選</option><option value="number">數字</option>
            <option value="email">Email</option><option value="tel">電話</option>
          </select>
          <input type="text" name="field_options[]" placeholder="（選項用 | 分隔）">
          <input type="checkbox" name="field_required[0]" value="1" style="width:16px;height:16px;margin:auto">
          <button type="button" class="btn-rm" onclick="this.closest('.custom-field-row').remove()">✕</button>
        </div>
        <?php endif; ?>
      </div>
      <button type="button" class="btn-add-field" onclick="addField()">＋ 新增欄位</button>
    </div>

    <div style="margin-top:1.5rem;display:flex;gap:.8rem;flex-wrap:wrap">
      <button type="submit" class="btn-primary">💾 儲存表單</button>
      <?php if ($editForm): ?>
        <a href="form_manage.php" class="btn-ghost">取消</a>
      <?php endif; ?>
    </div>
  </form>
  </div>
</div>

<!-- ══ 我的表單列表 ══════════════════════════════════════════ -->
<div class="sec-label"><h2>我建立的表單</h2></div>
<div class="form-list">
  <?php if (empty($myForms)): ?>
    <p style="color:var(--text-muted);font-size:.88rem">尚未建立任何表單。</p>
  <?php else: foreach ($myForms as $f): 
    $statusLabel = ['open'=>'開放中','closed'=>'已關閉','draft'=>'草稿'][$f['status']] ?? $f['status'];
  ?>
    <div class="form-item">
      <div>
        <div class="fi-title"><?= h($f['title']) ?></div>
        <div class="fi-meta">
          活動：<?= h($f['act_title']) ?> ·
          報名：<?= h($f['sub_count']) ?> 人<?= $f['quota'] ? ' / '.$f['quota'].' 人' : '' ?> ·
          <?= $f['close_at'] ? '關閉：'.date('Y/m/d H:i',strtotime($f['close_at'])) : '無自動關閉' ?>
        </div>
        <div class="fi-badges">
          <span class="badge <?= h($f['status']) ?>"><?= h($statusLabel) ?></span>
          <?php if ($f['need_review']): ?><span class="badge review">需審核</span><?php endif; ?>
        </div>
      </div>
      <div class="fi-actions">
        <a href="form_manage.php?edit=<?= h($f['id']) ?>" class="btn-ghost">編輯</a>
        <a href="form_review.php?form_id=<?= h($f['id']) ?>" class="btn-gold">審核名單</a>
        <?php if ($f['status']==='open'): ?>
          <form method="post" style="display:inline">
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="form_id" value="<?= h($f['id']) ?>">
            <input type="hidden" name="to_status" value="closed">
            <button type="submit" class="btn-danger">關閉</button>
          </form>
        <?php else: ?>
          <form method="post" style="display:inline">
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="form_id" value="<?= h($f['id']) ?>">
            <input type="hidden" name="to_status" value="open">
            <button type="submit" class="btn-ghost">重新開放</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

</div><!-- /page-wrap -->

<script>
let fieldIdx = <?= max(count($editFields), 1) ?>;
function addField() {
  const c = document.getElementById('fieldsContainer');
  const div = document.createElement('div');
  div.className = 'custom-field-row';
  div.innerHTML = `
    <input type="text" name="field_label[]" placeholder="欄位名稱" required>
    <select name="field_type[]">
      <option value="text">短文字</option><option value="textarea">長文字</option>
      <option value="select">下拉選單</option><option value="radio">單選</option>
      <option value="checkbox">複選</option><option value="number">數字</option>
      <option value="email">Email</option><option value="tel">電話</option>
    </select>
    <input type="text" name="field_options[]" placeholder="（選項用 | 分隔）">
    <input type="checkbox" name="field_required[${fieldIdx}]" value="1" style="width:16px;height:16px;margin:auto">
    <button type="button" class="btn-rm" onclick="this.closest('.custom-field-row').remove()">✕</button>`;
  c.appendChild(div);
  fieldIdx++;
}
</script>

<?php require_once __DIR__ . "/footer.php"; ?>
</body>
</html>
