<?php
// form_apply.php ── 學生端：填寫報名表 & 查看報名狀態 & 確認參與
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once "api/db.php";

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function fmtDT($v){ return $v ? date('Y/m/d H:i', strtotime($v)) : '—'; }

// 自動關閉
$pdo->prepare("UPDATE forms SET status='closed' WHERE status='open' AND close_at IS NOT NULL AND close_at < NOW()")->execute();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php?redirect='.urlencode($_SERVER['REQUEST_URI'])); exit;
}
$me = [
    'user_id'  => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'role'     => $_SESSION['role'],
];

$formId = (int)($_GET['form_id'] ?? 0);
if (!$formId) { header('Location: activities.php'); exit; }

// 載入表單
$formStmt = $pdo->prepare("
    SELECT f.*, a.title AS act_title, a.event_start, a.location, a.organizer
    FROM forms f
    JOIN activities a ON a.id=f.activity_id
    WHERE f.id=?
");
$formStmt->execute([$formId]);
$form = $formStmt->fetch(PDO::FETCH_ASSOC);
if (!$form) { echo '<p>表單不存在</p>'; exit; }

// 欄位（Google 表單模式不需要，但仍載入備用）
$fieldsStmt = $pdo->prepare("SELECT * FROM form_fields WHERE form_id=? ORDER BY sort_order");
$fieldsStmt->execute([$formId]);
$fields = $fieldsStmt->fetchAll(PDO::FETCH_ASSOC);

// 判斷是否為 Google 表單模式
$isGoogleMode = !empty($form['google_form_url']);

// 已有的報名紀錄
$existingStmt = $pdo->prepare("SELECT * FROM form_submissions WHERE form_id=? AND user_id=?");
$existingStmt->execute([$formId, $me['user_id']]);
$existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

$msg = '';
$mode = 'form'; // form | status

// ── 確認參與 ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm') {
    if ($existing && $existing['status'] === 'approved' && !$existing['confirmed']) {
        $pdo->prepare("UPDATE form_submissions SET confirmed=1, confirmed_at=NOW() WHERE id=?")
            ->execute([$existing['id']]);
        $msg = 'ok:已確認參與，期待你的到來！';
        $existing['confirmed'] = 1;
        $existing['confirmed_at'] = date('Y-m-d H:i:s');
    }
    $mode = 'status';
}

// ── 提交報名（僅自訂表單模式） ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit' && !$isGoogleMode) {

    if ($form['status'] !== 'open') {
        $msg = 'error:此表單已關閉，無法報名';
    } elseif ($existing) {
        $msg = 'error:你已提交過報名，請查看你的報名狀態';
        $mode = 'status';
    } else {
        // 檢查名額
        if ($form['quota']) {
            $cnt = (int)$pdo->query("SELECT COUNT(*) FROM form_submissions WHERE form_id=$formId AND status!='rejected'")->fetchColumn();
            if ($cnt >= $form['quota']) {
                $msg = 'error:報名人數已達名額上限，無法報名';
            }
        }

        if (!str_starts_with($msg, 'error')) {
            // 驗證必填
            $hasError = false;
            foreach ($fields as $f) {
                $raw = $_POST['field_'.$f['id']] ?? '';
                $val = is_array($raw) ? implode(', ', $raw) : trim($raw);
                if ($f['is_required'] && $val === '') {
                    $msg = 'error:請填寫所有必填欄位（' . $f['label'] . '）';
                    $hasError = true; break;
                }
            }

            if (!$hasError) {
                $initStatus = $form['need_review'] ? 'pending' : 'approved';
                $pdo->prepare("
                    INSERT INTO form_submissions (form_id, user_id, status)
                    VALUES (?,?,?)
                ")->execute([$formId, $me['user_id'], $initStatus]);
                $subId = $pdo->lastInsertId();

                foreach ($fields as $f) {
                    $raw = $_POST['field_'.$f['id']] ?? '';
                    $ans = is_array($raw) ? implode(', ', $raw) : trim($raw);
                    $pdo->prepare("INSERT INTO form_answers (submission_id, field_id, answer) VALUES (?,?,?)")
                        ->execute([$subId, $f['id'], $ans]);
                }

                $existingStmt2 = $pdo->prepare("SELECT * FROM form_submissions WHERE id=?");
                $existingStmt2->execute([$subId]);
                $existing = $existingStmt2->fetch(PDO::FETCH_ASSOC);

                $msg  = $form['need_review']
                        ? 'ok:報名成功！你的申請待社團審核，請耐心等候通知。'
                        : 'ok:報名成功！你已通過並等待確認參與。';
                $mode = 'status';
            }
        }
    }
}

if ($existing && $_SERVER['REQUEST_METHOD'] !== 'POST') $mode = 'status';
if (str_starts_with($msg, 'error:')) $mode = 'form';

// 讀取已填答案
$myAnswers = [];
if ($existing) {
    $ansStmt = $pdo->prepare("SELECT field_id, answer FROM form_answers WHERE submission_id=?");
    $ansStmt->execute([$existing['id']]);
    $myAnswers = $ansStmt->fetchAll(PDO::FETCH_KEY_PAIR);
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($form['title']) ?> · FJU_CLUB</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;700&family=Noto+Sans+TC:wght@300;400;500;700&display=swap" rel="stylesheet">
<style>
:root{--navy:#1a2744;--navy-mid:#243257;--accent:#3a5fa0;--accent-hover:#4a72be;--gold:#c8a96e;--gold-light:#e8c98e;--cream:#f7f4ef;--cream-dark:#ede9e0;--white:#fff;--text-dark:#1a1f2e;--text-mid:#4a5068;--text-muted:#8a91a8;--border-light:rgba(26,39,68,.10);--border-mid:rgba(26,39,68,.18);}
*{box-sizing:border-box;margin:0;padding:0}body{font-family:'Noto Sans TC',sans-serif;background:var(--cream);color:var(--text-dark)}
a{text-decoration:none;color:inherit}
.page-wrap{max-width:640px;margin:0 auto;padding:2rem 1.5rem}
.act-info{background:var(--navy);border-radius:14px 14px 0 0;padding:1.5rem 1.8rem;color:#fff;margin-bottom:0}
.act-eyebrow{font-size:.7rem;letter-spacing:.16em;color:var(--gold);text-transform:uppercase;margin-bottom:.5rem}
.act-info h1{font-family:'Noto Serif TC',serif;font-size:1.3rem;font-weight:700;margin-bottom:.8rem;line-height:1.4}
.act-meta-row{display:flex;gap:1.4rem;flex-wrap:wrap}
.act-meta-item{font-size:.78rem;color:rgba(255,255,255,.6);display:flex;align-items:center;gap:.35rem}
.act-meta-item strong{color:rgba(255,255,255,.85);font-weight:500}
.form-card{background:var(--white);border:1px solid var(--border-light);border-top:none;border-radius:0 0 14px 14px;padding:1.8rem}
.form-desc{font-size:.85rem;color:var(--text-mid);line-height:1.8;margin-bottom:1.5rem;padding:.9rem 1rem;background:var(--cream);border-radius:8px;border-left:3px solid var(--gold)}
.msg{padding:.75rem 1.2rem;border-radius:8px;font-size:.88rem;margin-bottom:1.2rem}
.msg.ok{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}
.msg.err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.field-group{margin-bottom:1.1rem}
.field-group label{display:flex;align-items:center;gap:.4rem;font-size:.82rem;font-weight:600;color:var(--text-dark);margin-bottom:.4rem}
.req-mark{color:#c0392b;font-size:.75rem}
input[type=text],input[type=email],input[type=tel],input[type=number],select,textarea{
  width:100%;padding:.6rem .9rem;border:1px solid var(--border-mid);border-radius:8px;
  font-size:.88rem;font-family:inherit;color:var(--text-dark);background:var(--white);
  transition:border-color .2s,box-shadow .2s;outline:none}
input:focus,select:focus,textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(58,95,160,.12)}
textarea{resize:vertical;min-height:90px}
.radio-group,.checkbox-group{display:flex;flex-direction:column;gap:.5rem;padding:.5rem 0}
.radio-group label,.checkbox-group label{font-size:.85rem;font-weight:400;display:flex;align-items:center;gap:.5rem;cursor:pointer}
.radio-group input,.checkbox-group input{width:16px;height:16px;accent-color:var(--accent);cursor:pointer}
.submit-area{margin-top:1.5rem;padding-top:1.2rem;border-top:1px solid var(--border-light)}
.btn-submit{background:var(--accent);color:#fff;border:none;padding:.75rem 2rem;border-radius:10px;font-size:.95rem;font-weight:700;cursor:pointer;font-family:inherit;width:100%;transition:background .18s;display:block;text-align:center}
.btn-submit:hover{background:var(--accent-hover)}
.btn-submit:disabled{background:var(--text-muted);cursor:not-allowed}
.status-card{background:var(--white);border:1px solid var(--border-light);border-top:none;border-radius:0 0 14px 14px;padding:2rem 1.8rem}
.status-hero{text-align:center;padding:1.5rem 0 2rem}
.status-icon{font-size:3.5rem;margin-bottom:.8rem}
.status-hero h2{font-family:'Noto Serif TC',serif;font-size:1.3rem;font-weight:700;color:var(--navy);margin-bottom:.4rem}
.status-hero p{font-size:.88rem;color:var(--text-mid);line-height:1.8}
.status-table{width:100%;border-collapse:collapse;margin:1.2rem 0}
.status-table tr td{padding:.6rem .8rem;border-bottom:1px solid var(--border-light);font-size:.85rem}
.status-table tr td:first-child{font-weight:600;color:var(--text-mid);width:35%}
.status-table tr:last-child td{border-bottom:none}
.status-badge{display:inline-block;font-size:.78rem;font-weight:700;padding:.25rem .7rem;border-radius:6px}
.status-badge.pending{background:#fefce8;color:#854d0e}
.status-badge.approved{background:#f0fdf4;color:#166534}
.status-badge.rejected{background:#fef2f2;color:#991b1b}
.confirm-section{margin-top:1.5rem;border:2px solid #bbf7d0;border-radius:12px;padding:1.2rem 1.4rem;background:#f0fdf4;text-align:center}
.confirm-section h3{font-size:.95rem;font-weight:700;color:#166534;margin-bottom:.5rem}
.confirm-section p{font-size:.82rem;color:#166534;opacity:.8;margin-bottom:1rem;line-height:1.7}
.btn-confirm{background:#166534;color:#fff;border:none;padding:.7rem 2rem;border-radius:10px;font-size:.9rem;font-weight:700;cursor:pointer;font-family:inherit;width:100%;transition:background .18s}
.btn-confirm:hover{background:#14532d}
.confirmed-done{background:#dcfce7;border-radius:8px;padding:1rem;text-align:center;color:#166534;font-weight:600;font-size:.9rem}
.note-box{background:var(--cream);border-radius:8px;padding:.8rem 1rem;margin-top:.8rem;font-size:.82rem;color:var(--text-mid);border-left:3px solid var(--gold)}
.answers-review{margin-top:1.2rem;padding-top:1.2rem;border-top:1px solid var(--border-light)}
.answers-review h4{font-size:.82rem;font-weight:700;color:var(--navy);margin-bottom:.8rem;letter-spacing:.04em}
.ans-item{display:flex;gap:.8rem;padding:.45rem 0;border-bottom:1px solid var(--border-light);font-size:.82rem}
.ans-item:last-child{border-bottom:none}
.ans-label{font-weight:600;color:var(--text-mid);min-width:100px;flex-shrink:0}
.ans-val{color:var(--text-dark)}
.form-closed-notice{background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:1rem 1.2rem;margin-bottom:1rem;color:#991b1b;font-size:.85rem;text-align:center}

/* Google 表單模式專用樣式 */
.google-mode-box{text-align:center;padding:1.8rem 1rem}
.google-mode-box .g-icon{font-size:2.8rem;margin-bottom:.8rem}
.google-mode-box h3{font-family:'Noto Serif TC',serif;font-size:1.1rem;font-weight:700;color:var(--navy);margin-bottom:.5rem}
.google-mode-box p{font-size:.84rem;color:var(--text-mid);line-height:1.8;margin-bottom:1.4rem}
.btn-google{
  display:inline-flex;align-items:center;justify-content:center;gap:.5rem;
  background:var(--accent);color:#fff;border:none;
  padding:.75rem 2rem;border-radius:10px;
  font-size:.95rem;font-weight:700;cursor:pointer;font-family:inherit;
  width:100%;transition:background .18s;text-decoration:none
}
.btn-google:hover{background:var(--accent-hover)}
.google-notice-small{
  margin-top:.9rem;font-size:.75rem;color:var(--text-muted);
  background:var(--cream);border-radius:8px;padding:.65rem .9rem;
  border-left:3px solid var(--gold);text-align:left;line-height:1.7
}
</style>
</head>
<body>
<?php require_once __DIR__ . "/header.php"; ?>

<div class="page-wrap">

<div class="act-info">
  <p class="act-eyebrow"><?= h($form['organizer']) ?></p>
  <h1><?= h($form['title']) ?></h1>
  <div class="act-meta-row">
    <div class="act-meta-item">📅 <strong><?= fmtDT($form['event_start']) ?></strong></div>
    <div class="act-meta-item">📍 <strong><?= h($form['location']) ?></strong></div>
    <?php if ($form['quota'] && !$isGoogleMode): ?>
      <div class="act-meta-item">👥 <strong>名額：<?= h($form['quota']) ?> 人</strong></div>
    <?php endif; ?>
  </div>
</div>

<?php if ($msg): $isOk=str_starts_with($msg,'ok:'); ?>
  <div style="border-radius:0;margin:0">
    <div class="msg <?= $isOk?'ok':'err' ?>" style="border-radius:0;margin:0">
      <?= h(substr($msg,3)) ?>
    </div>
  </div>
<?php endif; ?>

<?php
// ══════════════════════════════════════════════════════
// ① 表單已關閉且尚未報名
// ══════════════════════════════════════════════════════
if ($form['status'] !== 'open' && !$existing && !$isGoogleMode):
?>
<div class="form-card">
  <div class="form-closed-notice">⚠️ 此報名表單目前已關閉，無法接受新的報名</div>
  <div style="text-align:center;padding:1rem 0">
    <a href="activities.php" style="color:var(--accent);font-size:.88rem">← 返回活動列表</a>
  </div>
</div>

<?php
// ══════════════════════════════════════════════════════
// ② Google 表單模式
// ══════════════════════════════════════════════════════
elseif ($isGoogleMode):
?>
<div class="form-card">
  <?php if ($form['description']): ?>
    <div class="form-desc"><?= nl2br(h($form['description'])) ?></div>
  <?php endif; ?>

  <?php if ($form['status'] !== 'open'): ?>
    <div class="form-closed-notice">⚠️ 此報名表單目前已關閉，無法接受新的報名</div>
    <div style="text-align:center;padding:1rem 0">
      <a href="activities.php" style="color:var(--accent);font-size:.88rem">← 返回活動列表</a>
    </div>
  <?php else: ?>
    <div class="google-mode-box">
      <div class="g-icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor">
          <path d="M21.35 11.1H12.18v2.94h5.28c-.23 1.26-.95 2.33-2.02 3.04v2.53h3.27c1.91-1.76 3.01-4.35 3.01-7.44 0-.63-.06-1.24-.17-1.84z" fill="#4285F4"/>
          <path d="M12.18 22c2.76 0 5.07-.91 6.76-2.46l-3.27-2.53c-.91.61-2.07.97-3.49.97-2.68 0-4.95-1.81-5.76-4.25H3.05v2.6C4.73 19.98 8.19 22 12.18 22z" fill="#34A853"/>
          <path d="M6.42 13.73a5.97 5.97 0 010-3.46V7.67H3.05a10.02 10.02 0 000 8.66l3.37-2.6z" fill="#FBBC05"/>
          <path d="M12.18 5.56c1.51 0 2.86.52 3.92 1.54l2.94-2.94C17.24 2.49 14.94 1.5 12.18 1.5 8.19 1.5 4.73 3.52 3.05 6.67l3.37 2.6c.81-2.44 3.08-4.71 5.76-4.71z" fill="#EA4335"/>
        </svg>
      </div>
      <h3>此活動使用 Google 表單報名</h3>
      <p>點擊下方按鈕前往 Google 表單填寫報名資料。<br>填寫完成後即完成報名，無需回到此頁面。</p>
      <a href="<?= h($form['google_form_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn-google">
        📋 前往 Google 表單報名 ↗
      </a>
      <div class="google-notice-small">
        ⚠️ 報名資料由 Google 收集管理，平台不會記錄你的填寫內容。<br>
        如有報名相關問題，請直接聯繫社團幹部。
      </div>
    </div>
  <?php endif; ?>
</div>

<?php
// ══════════════════════════════════════════════════════
// ③ 自訂表單：顯示報名狀態
// ══════════════════════════════════════════════════════
elseif ($mode === 'status' && $existing):
?>
<div class="status-card">
  <div class="status-hero">
    <?php if ($existing['status']==='pending'): ?>
      <div class="status-icon">⏳</div>
      <h2>報名申請已送出</h2>
      <p>你的報名正在等待社團審核<br>通過後你將可以確認參與</p>
    <?php elseif ($existing['status']==='approved'): ?>
      <div class="status-icon">🎉</div>
      <h2>報名審核通過！</h2>
      <p>恭喜！請確認你的參與意願</p>
    <?php else: ?>
      <div class="status-icon">❌</div>
      <h2>很遺憾，報名未通過</h2>
      <p>此次報名申請未能通過審核</p>
    <?php endif; ?>
  </div>

  <table class="status-table">
    <tr>
      <td>報名狀態</td>
      <td><span class="status-badge <?= h($existing['status']) ?>">
        <?= ['pending'=>'待審核','approved'=>'審核通過','rejected'=>'未通過'][$existing['status']] ?>
      </span></td>
    </tr>
    <tr><td>報名時間</td><td><?= fmtDT($existing['submitted_at']) ?></td></tr>
    <?php if ($existing['reviewed_at']): ?>
      <tr><td>審核時間</td><td><?= fmtDT($existing['reviewed_at']) ?></td></tr>
    <?php endif; ?>
    <tr>
      <td>確認參與</td>
      <td><?= $existing['confirmed']
            ? '✅ 已確認（'.fmtDT($existing['confirmed_at']).'）'
            : '⬜ 尚未確認' ?>
      </td>
    </tr>
  </table>

  <?php if ($existing['note']): ?>
    <div class="note-box">💬 社團備注：<?= h($existing['note']) ?></div>
  <?php endif; ?>

  <?php if ($existing['status']==='approved' && !$existing['confirmed']): ?>
  <div class="confirm-section">
    <h3>🙋 請確認你將出席此活動</h3>
    <p>你的報名已通過審核！<br>請點擊下方按鈕確認你的參與，讓主辦方掌握確切人數。</p>
    <form method="post">
      <input type="hidden" name="action" value="confirm">
      <button type="submit" class="btn-confirm">✓ 確認參與</button>
    </form>
  </div>
  <?php elseif ($existing['confirmed']): ?>
  <div class="confirmed-done">✅ 你已確認參與此活動，期待你的到來！</div>
  <?php endif; ?>

  <?php if (!empty($myAnswers) && !empty($fields)): ?>
  <div class="answers-review">
    <h4>📝 你的報名資料</h4>
    <?php foreach ($fields as $f): ?>
      <div class="ans-item">
        <span class="ans-label"><?= h($f['label']) ?></span>
        <span class="ans-val"><?= h($myAnswers[$f['id']] ?? '（未填）') ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div style="margin-top:1.5rem;text-align:center">
    <a href="activities.php" style="color:var(--accent);font-size:.85rem">← 返回活動列表</a>
  </div>
</div>

<?php
// ══════════════════════════════════════════════════════
// ④ 自訂表單：填寫表單
// ══════════════════════════════════════════════════════
else:
?>
<div class="form-card">
  <?php if ($form['description']): ?>
    <div class="form-desc"><?= nl2br(h($form['description'])) ?></div>
  <?php endif; ?>

  <?php if (!$form['need_review']): ?>
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.7rem 1rem;margin-bottom:1.2rem;font-size:.82rem;color:#166534">
      ℹ️ 此表單無需審核，提交後即自動通過
    </div>
  <?php endif; ?>

  <form method="post" id="applyForm">
    <input type="hidden" name="action" value="submit">

    <?php foreach ($fields as $f):
      $fid     = 'field_'.$f['id'];
      $opts    = array_filter(array_map('trim', explode('|', $f['options'] ?? '')));
      $postVal = $_POST[$fid] ?? '';
    ?>
    <div class="field-group">
      <label>
        <?= h($f['label']) ?>
        <?php if ($f['is_required']): ?><span class="req-mark">* 必填</span><?php endif; ?>
      </label>

      <?php if ($f['field_type']==='textarea'): ?>
        <textarea name="<?= h($fid) ?>" <?= $f['is_required']?'required':'' ?> placeholder="請輸入…"><?= h(is_array($postVal) ? implode(', ',$postVal) : $postVal) ?></textarea>

      <?php elseif ($f['field_type']==='select'): ?>
        <select name="<?= h($fid) ?>" <?= $f['is_required']?'required':'' ?>>
          <option value="">── 請選擇 ──</option>
          <?php foreach ($opts as $o): ?>
            <option value="<?= h($o) ?>" <?= (!is_array($postVal) && $postVal===$o)?'selected':'' ?>>
              <?= h($o) ?>
            </option>
          <?php endforeach; ?>
        </select>

      <?php elseif ($f['field_type']==='radio'): ?>
        <div class="radio-group">
          <?php foreach ($opts as $o): ?>
            <label>
              <input type="radio" name="<?= h($fid) ?>" value="<?= h($o) ?>"
                <?= (!is_array($postVal) && $postVal===$o)?'checked':'' ?>
                <?= $f['is_required']?'required':'' ?>>
              <?= h($o) ?>
            </label>
          <?php endforeach; ?>
        </div>

      <?php elseif ($f['field_type']==='checkbox'): ?>
        <?php $checkedVals = is_array($postVal) ? $postVal : []; ?>
        <div class="checkbox-group">
          <?php foreach ($opts as $o): ?>
            <label>
              <input type="checkbox" name="<?= h($fid) ?>[]" value="<?= h($o) ?>"
                <?= in_array($o, $checkedVals)?'checked':'' ?>>
              <?= h($o) ?>
            </label>
          <?php endforeach; ?>
        </div>

      <?php elseif ($f['field_type']==='number'): ?>
        <input type="number" name="<?= h($fid) ?>"
               <?= $f['is_required']?'required':'' ?>
               value="<?= h(is_array($postVal)?'':$postVal) ?>">

      <?php elseif ($f['field_type']==='email'): ?>
        <input type="email" name="<?= h($fid) ?>"
               <?= $f['is_required']?'required':'' ?>
               value="<?= h(is_array($postVal)?'':$postVal) ?>"
               placeholder="example@email.com">

      <?php elseif ($f['field_type']==='tel'): ?>
        <input type="tel" name="<?= h($fid) ?>"
               <?= $f['is_required']?'required':'' ?>
               value="<?= h(is_array($postVal)?'':$postVal) ?>"
               placeholder="09xxxxxxxx">

      <?php else: ?>
        <input type="text" name="<?= h($fid) ?>"
               <?= $f['is_required']?'required':'' ?>
               value="<?= h(is_array($postVal)?'':$postVal) ?>"
               placeholder="請輸入…">
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <div class="submit-area">
      <?php
        $quotaFull = false;
        if ($form['quota']) {
            $usedCnt = (int)$pdo->query("SELECT COUNT(*) FROM form_submissions WHERE form_id=$formId AND status!='rejected'")->fetchColumn();
            $quotaFull = $usedCnt >= $form['quota'];
        }
      ?>
      <?php if ($quotaFull): ?>
        <div class="form-closed-notice">⚠️ 報名人數已達名額上限</div>
        <button type="button" class="btn-submit" disabled>名額已滿</button>
      <?php else: ?>
        <button type="submit" class="btn-submit">📩 送出報名</button>
      <?php endif; ?>
      <p style="text-align:center;margin-top:.8rem;font-size:.75rem;color:var(--text-muted)">
        送出後即可在此頁面查看審核狀態
      </p>
    </div>
  </form>
</div>
<?php endif; ?>

</div>
<?php require_once __DIR__ . "/footer.php"; ?>
</body>
</html>