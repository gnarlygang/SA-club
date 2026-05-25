<?php
session_start();
date_default_timezone_set('Asia/Taipei');
require_once "api/db.php";

$current_user_id = (int)($_SESSION['user_id'] ?? 0);
$current_role    = (int)($_SESSION['role']    ?? 0);
$is_admin        = $current_user_id > 0 && $current_role === 4;

if (!$is_admin) { header("Location: forum.php"); exit; }

// ── POST 處理 ─────────────────────────────────────────────────
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act       = $_POST['act']       ?? '';
    $report_id = (int)($_POST['report_id'] ?? 0);
    $note      = trim($_POST['note'] ?? '');

    if ($act === 'dismiss' && $report_id) {
        $pdo->prepare("UPDATE forum_reports SET status='dismissed', resolved_at=NOW(), resolved_by=? WHERE id=?")
            ->execute([$current_user_id, $report_id]);
        $msg = 'dismiss';
    }

    if ($act === 'resolve' && $report_id) {
        // 取得 item 資訊，軟刪除
        $rep = $pdo->prepare("SELECT * FROM forum_reports WHERE id=?");
        $rep->execute([$report_id]);
        $r   = $rep->fetch(PDO::FETCH_ASSOC);
        if ($r) {
            $tbl = $r['item_type'] === 'post' ? 'forum_posts' : 'forum_comments';
            $pdo->prepare("UPDATE `{$tbl}` SET is_deleted=1, deleted_at=NOW(), deleted_by=? WHERE id=?")
                ->execute([$current_user_id, $r['item_id']]);
            // 同一 item 的所有 pending 檢舉一起結案
            $pdo->prepare("UPDATE forum_reports SET status='resolved', resolved_at=NOW(), resolved_by=? WHERE item_type=? AND item_id=? AND status='pending'")
                ->execute([$current_user_id, $r['item_type'], $r['item_id']]);
        }
        $msg = 'resolve';
    }

    header("Location: forum_admin.php?tab=" . ($_GET['tab'] ?? 'pending') . "&msg=" . $msg);
    exit;
}

// ── 查詢 ──────────────────────────────────────────────────────
$tab    = in_array($_GET['tab'] ?? '', ['pending','resolved','dismissed']) ? $_GET['tab'] : 'pending';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 15;
$offset = ($page - 1) * $limit;

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM forum_reports WHERE status=?");
$count_stmt->execute([$tab]);
$total = (int)$count_stmt->fetchColumn();
$total_pages = max(1, ceil($total / $limit));

$stmt = $pdo->prepare("
    SELECT r.*,
           ru.username AS reporter_name, ru.nickname AS reporter_nick,
           ha.username AS handler_name,
           CASE
               WHEN r.item_type='post'
               THEN (SELECT fp.title   FROM forum_posts     fp WHERE fp.id=r.item_id LIMIT 1)
               ELSE (SELECT LEFT(fc.content,60) FROM forum_comments fc WHERE fc.id=r.item_id LIMIT 1)
           END AS item_preview,
           CASE
               WHEN r.item_type='post'
               THEN (SELECT fp.user_id FROM forum_posts     fp WHERE fp.id=r.item_id LIMIT 1)
               ELSE (SELECT fc.user_id FROM forum_comments fc WHERE fc.id=r.item_id LIMIT 1)
           END AS author_id,
           CASE
               WHEN r.item_type='post'
               THEN (SELECT COALESCE(u2.nickname,u2.username) FROM forum_posts fp2 LEFT JOIN users u2 ON u2.user_id=fp2.user_id WHERE fp2.id=r.item_id LIMIT 1)
               ELSE (SELECT COALESCE(u2.nickname,u2.username) FROM forum_comments fc2 LEFT JOIN users u2 ON u2.user_id=fc2.user_id WHERE fc2.id=r.item_id LIMIT 1)
           END AS author_name,
           CASE
               WHEN r.item_type='post'
               THEN (SELECT fp3.id FROM forum_posts fp3 WHERE fp3.id=r.item_id LIMIT 1)
               ELSE (SELECT fc3.post_id FROM forum_comments fc3 WHERE fc3.id=r.item_id LIMIT 1)
           END AS post_id,
           CASE
               WHEN r.item_type='post'
               THEN (SELECT fp4.is_deleted FROM forum_posts fp4 WHERE fp4.id=r.item_id LIMIT 1)
               ELSE (SELECT fc4.is_deleted FROM forum_comments fc4 WHERE fc4.id=r.item_id LIMIT 1)
           END AS item_deleted
    FROM forum_reports r
    LEFT JOIN users ru ON ru.user_id=r.reporter_id
    LEFT JOIN users ha ON ha.user_id=r.resolved_by
    WHERE r.status=?
    ORDER BY r.created_at DESC
    LIMIT {$limit} OFFSET {$offset}
");
$stmt->execute([$tab]);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 各狀態數量
$counts = [];
foreach (['pending','resolved','dismissed'] as $s) {
    $c = $pdo->prepare("SELECT COUNT(*) FROM forum_reports WHERE status=?");
    $c->execute([$s]);
    $counts[$s] = (int)$c->fetchColumn();
}

// 統計數字
$stats = [];
$stats['total_posts']    = (int)$pdo->query("SELECT COUNT(*) FROM forum_posts    WHERE is_deleted=0")->fetchColumn();
$stats['total_comments'] = (int)$pdo->query("SELECT COUNT(*) FROM forum_comments WHERE is_deleted=0")->fetchColumn();
$stats['total_reports']  = (int)$pdo->query("SELECT COUNT(*) FROM forum_reports")->fetchColumn();
$stats['pending']        = $counts['pending'];

function time_ago($dt) {
    if (!$dt) return '-';
    $d = max(0, time()-strtotime($dt));
    if ($d < 60)    return "剛剛";
    if ($d < 3600)  return floor($d/60)." 分鐘前";
    if ($d < 86400) return floor($d/3600)." 小時前";
    return date("Y/m/d H:i", strtotime($dt));
}

require_once "header.php";
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>論壇檢舉管理 — 輔大社團平台</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<style>
*{box-sizing:border-box}
body{font-family:"Microsoft JhengHei",sans-serif;background:#f0f2f5;min-height:100vh}
.admin-wrap{max-width:1100px;margin:0 auto;padding:32px 16px 60px}

/* 頁首 */
.admin-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:12px}
.admin-title{font-size:22px;font-weight:800;color:#1a2535;display:flex;align-items:center;gap:10px}
.back-btn{display:inline-flex;align-items:center;gap:6px;border:1px solid #c8d4e8;background:#f0f4fb;color:#3a5a8a;border-radius:999px;padding:8px 16px;font-size:14px;text-decoration:none;font-weight:600}
.back-btn:hover{background:#dce7f7;color:#1a2535}

/* 統計卡 */
.stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:28px}
.stat-card{background:#fff;border-radius:14px;padding:20px 22px;box-shadow:0 2px 10px rgba(60,80,120,.07);display:flex;flex-direction:column;gap:4px}
.stat-num{font-size:28px;font-weight:800;color:#1a2535}
.stat-lbl{font-size:12px;color:#8892a6;font-weight:600;letter-spacing:.04em}
.stat-card.danger .stat-num{color:#b91c1c}
.stat-card.warn  .stat-num{color:#b45000}

/* 訊息提示 */
.alert-ok {background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;border-radius:10px;padding:12px 18px;font-size:14px;margin-bottom:20px;display:flex;align-items:center;gap:8px}
.alert-inf{background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;border-radius:10px;padding:12px 18px;font-size:14px;margin-bottom:20px;display:flex;align-items:center;gap:8px}

/* Tab */
.tab-bar{display:flex;gap:4px;background:#e8ecf0;border-radius:12px;padding:4px;margin-bottom:22px;width:fit-content}
.tab-btn{border:none;background:transparent;border-radius:9px;padding:8px 20px;font-size:14px;font-weight:600;cursor:pointer;color:#667;transition:.18s;display:flex;align-items:center;gap:6px}
.tab-btn.active{background:#fff;color:#1a2535;box-shadow:0 2px 8px rgba(0,0,0,.09)}
.badge-cnt{background:#e8ecf0;color:#667;border-radius:999px;padding:1px 7px;font-size:11px;font-weight:700}
.tab-btn.active .badge-cnt{background:#2d3a4a;color:#fff}
.badge-cnt.red{background:#fee2e2;color:#b91c1c}
.tab-btn.active .badge-cnt.red{background:#b91c1c;color:#fff}

/* 檢舉表格 */
.report-table-wrap{background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(60,80,120,.07);overflow:hidden}
.report-table{width:100%;border-collapse:collapse;font-size:14px}
.report-table th{padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:#8892a6;letter-spacing:.06em;border-bottom:1px solid #e8ecf0;background:#fafbfc}
.report-table td{padding:14px 16px;border-bottom:1px solid #f0f2f5;vertical-align:top}
.report-table tr:last-child td{border-bottom:none}
.report-table tr:hover td{background:#fafbfc}

.item-type-badge{display:inline-block;font-size:11px;padding:2px 9px;border-radius:999px;font-weight:700}
.item-type-badge.post   {background:#e0f2fe;color:#0369a1}
.item-type-badge.comment{background:#fef9c3;color:#854d0e}
.item-preview{color:#334;font-size:13px;line-height:1.5;max-width:260px}
.item-preview a{color:#3a6ea8;text-decoration:none;font-weight:600}
.item-preview a:hover{text-decoration:underline}
.item-deleted-tag{font-size:11px;background:#fee2e2;color:#b91c1c;border-radius:4px;padding:1px 6px;margin-left:4px}
.reporter-info{font-size:12px;color:#667}
.reason-text{font-size:13px;color:#334;max-width:200px;line-height:1.5}
.time-text{font-size:12px;color:#9aa;white-space:nowrap}
.status-badge{display:inline-flex;align-items:center;gap:4px;font-size:11px;padding:3px 10px;border-radius:999px;font-weight:700}
.status-badge.pending   {background:#fef3c7;color:#92400e}
.status-badge.resolved  {background:#dcfce7;color:#166534}
.status-badge.dismissed {background:#f1f5f9;color:#64748b}

/* 操作按鈕 */
.act-btns{display:flex;flex-direction:column;gap:7px;min-width:90px}
.btn-resolve {border:none;background:#166534;color:#fff;border-radius:8px;padding:7px 12px;font-size:12px;font-weight:700;cursor:pointer;width:100%;text-align:center}
.btn-resolve:hover{background:#14532d}
.btn-dismiss {border:1px solid #cbd5e1;background:#f8fafc;color:#475569;border-radius:8px;padding:7px 12px;font-size:12px;font-weight:600;cursor:pointer;width:100%;text-align:center}
.btn-dismiss:hover{background:#f1f5f9}
.btn-view    {display:block;border:1px solid #c8d4e8;background:#f0f4fb;color:#3a5a8a;border-radius:8px;padding:7px 12px;font-size:12px;font-weight:600;text-align:center;text-decoration:none}
.btn-view:hover{background:#dce7f7;color:#1a2535}

/* 空狀態 */
.empty-state{text-align:center;padding:60px 0;color:#aab;font-size:15px}
.empty-state i{font-size:40px;display:block;margin-bottom:12px}

/* 分頁 */
.pagination{display:flex;gap:6px;justify-content:center;margin-top:22px;flex-wrap:wrap}
.pg-btn{border:1px solid #e0e4ea;background:#fff;color:#4a5568;border-radius:8px;padding:7px 14px;font-size:14px;text-decoration:none;transition:.15s}
.pg-btn:hover{border-color:#6e8ab0;color:#2d3a4a}
.pg-btn.active{background:#2d3a4a;border-color:#2d3a4a;color:#fff}
.pg-btn.disabled{opacity:.4;pointer-events:none}

/* confirm modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9990;align-items:center;justify-content:center}
.modal-overlay.active{display:flex}
.modal-box{background:#fff;border-radius:16px;padding:30px 34px;width:100%;max-width:460px;box-shadow:0 8px 32px rgba(0,0,0,.18);position:relative}
.modal-title{font-size:17px;font-weight:700;color:#1a2535;margin-bottom:12px}
.modal-body{font-size:14px;color:#445;line-height:1.65;margin-bottom:20px}
.modal-acts{display:flex;gap:10px;justify-content:flex-end}
.btn-mc{border:1px solid #d0d8e4;background:#f5f7fa;color:#667;border-radius:8px;padding:9px 20px;font-size:14px;cursor:pointer}
.btn-danger{border:none;background:#b91c1c;color:#fff;border-radius:8px;padding:9px 20px;font-size:14px;font-weight:700;cursor:pointer}
.btn-danger:hover{background:#991b1b}
.btn-safe{border:none;background:#166534;color:#fff;border-radius:8px;padding:9px 20px;font-size:14px;font-weight:700;cursor:pointer}
.btn-safe:hover{background:#14532d}

@media(max-width:768px){
    .stat-row{grid-template-columns:repeat(2,1fr)}
    .report-table th:nth-child(4),.report-table td:nth-child(4),
    .report-table th:nth-child(5),.report-table td:nth-child(5){display:none}
    .admin-header{flex-direction:column;align-items:flex-start}
}
</style>
</head>
<body>
<div class="admin-wrap">

<!-- 頁首 -->
<div class="admin-header">
    <div class="admin-title">
        <i class="bi bi-shield-check" style="color:#1e3a8a"></i>
        論壇檢舉管理
    </div>
    <a href="forum.php" class="back-btn"><i class="bi bi-arrow-left"></i> 回論壇</a>
</div>

<!-- 成功訊息 -->
<?php if (($_GET['msg'] ?? '') === 'resolve'): ?>
<div class="alert-ok"><i class="bi bi-check-circle-fill"></i>已刪除被檢舉內容，並結案相關檢舉。</div>
<?php elseif (($_GET['msg'] ?? '') === 'dismiss'): ?>
<div class="alert-inf"><i class="bi bi-info-circle-fill"></i>已駁回此檢舉，內容保留不變。</div>
<?php endif; ?>

<!-- 統計卡 -->
<div class="stat-row">
    <div class="stat-card">
        <div class="stat-num"><?= $stats['total_posts'] ?></div>
        <div class="stat-lbl"><i class="bi bi-file-text me-1"></i>文章總數</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= $stats['total_comments'] ?></div>
        <div class="stat-lbl"><i class="bi bi-chat-dots me-1"></i>留言總數</div>
    </div>
    <div class="stat-card warn">
        <div class="stat-num"><?= $stats['total_reports'] ?></div>
        <div class="stat-lbl"><i class="bi bi-flag me-1"></i>檢舉總數</div>
    </div>
    <div class="stat-card danger">
        <div class="stat-num"><?= $stats['pending'] ?></div>
        <div class="stat-lbl"><i class="bi bi-clock-history me-1"></i>待處理</div>
    </div>
</div>

<!-- Tab -->
<div class="tab-bar">
    <?php
    $tabs = ['pending'=>'待處理','resolved'=>'已處理','dismissed'=>'已駁回'];
    foreach ($tabs as $k=>$label):
        $cnt = $counts[$k];
        $isRed = $k==='pending' && $cnt>0;
    ?>
    <a href="forum_admin.php?tab=<?= $k ?>" class="tab-btn <?= $tab===$k?'active':'' ?>">
        <?= $label ?>
        <span class="badge-cnt <?= $isRed?'red':'' ?>"><?= $cnt ?></span>
    </a>
    <?php endforeach; ?>
</div>

<!-- 檢舉列表 -->
<div class="report-table-wrap">
<?php if (empty($reports)): ?>
    <div class="empty-state">
        <i class="bi bi-check2-circle"></i>
        目前沒有<?= $tabs[$tab] ?>的檢舉紀錄
    </div>
<?php else: ?>
<table class="report-table">
    <thead>
        <tr>
            <th>被檢舉內容</th>
            <th>被檢舉人</th>
            <th>檢舉原因</th>
            <th>檢舉人</th>
            <th>時間</th>
            <th>操作</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($reports as $r):
        $typeLabel = $r['item_type']==='post' ? '文章' : '留言';
        $preview   = $r['item_preview'] ?? '（內容已刪除）';
        $postLink  = $r['post_id'] ? "forum_post.php?id={$r['post_id']}" : '#';
        $isDeleted = (int)($r['item_deleted'] ?? 0);
    ?>
    <tr>
        <td>
            <span class="item-type-badge <?= $r['item_type'] ?>"><?= $typeLabel ?></span>
            <?php if ($isDeleted): ?>
                <span class="item-deleted-tag">已刪除</span>
            <?php endif; ?>
            <div class="item-preview" style="margin-top:6px">
                <?php if ($r['post_id'] && !$isDeleted): ?>
                <a href="<?= htmlspecialchars($postLink) ?>" target="_blank">
                    <?= htmlspecialchars(mb_strimwidth($preview, 0, 60, '…')) ?>
                </a>
                <?php else: ?>
                <?= htmlspecialchars(mb_strimwidth($preview, 0, 60, '…')) ?>
                <?php endif; ?>
            </div>
            <div style="font-size:11px;color:#9aa;margin-top:4px">ID: <?= $r['item_id'] ?></div>
        </td>
        <td>
            <div style="font-size:13px;font-weight:600;color:#334"><?= htmlspecialchars($r['author_name'] ?? '-') ?></div>
        </td>
        <td>
            <div class="reason-text"><?= htmlspecialchars($r['reason']) ?></div>
        </td>
        <td>
            <div class="reporter-info">
                <?= htmlspecialchars($r['reporter_nick'] ?: $r['reporter_name']) ?><br>
                <span style="color:#aab"><?= time_ago($r['created_at']) ?></span>
            </div>
        </td>
        <td>
            <div class="time-text"><?= date('m/d H:i', strtotime($r['created_at'])) ?></div>
            <?php if ($r['resolved_at']): ?>
            <div class="time-text" style="color:#aab">
                處理：<?= date('m/d H:i', strtotime($r['resolved_at'])) ?>
            </div>
            <div class="time-text" style="color:#aab">
                <?= htmlspecialchars($r['handler_name'] ?? '') ?>
            </div>
            <?php endif; ?>
        </td>
        <td>
            <?php if ($tab === 'pending'): ?>
            <div class="act-btns">
                <?php if ($r['post_id']): ?>
                <a href="<?= htmlspecialchars($postLink) ?>" target="_blank" class="btn-view">
                    <i class="bi bi-eye"></i> 查看
                </a>
                <?php endif; ?>
                <?php if (!$isDeleted): ?>
                <button type="button" class="btn-resolve"
                    onclick="confirmResolve(<?= $r['id'] ?>, '<?= $typeLabel ?>', '<?= htmlspecialchars(mb_strimwidth($preview,0,30,'…'),ENT_QUOTES) ?>')">
                    <i class="bi bi-trash3"></i> 刪除內容
                </button>
                <?php endif; ?>
                <button type="button" class="btn-dismiss"
                    onclick="confirmDismiss(<?= $r['id'] ?>)">
                    <i class="bi bi-x-circle"></i> 駁回
                </button>
            </div>
            <?php else: ?>
            <div style="text-align:center">
                <span class="status-badge <?= $tab ?>">
                    <?= $tab==='resolved'?'✓ 已刪內容':'✗ 已駁回' ?>
                </span>
                <?php if ($r['post_id']): ?>
                <br><a href="<?= htmlspecialchars($postLink) ?>" target="_blank" class="btn-view" style="margin-top:6px">
                    <i class="bi bi-eye"></i> 查看
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
</div>

<!-- 分頁 -->
<?php if ($total_pages > 1): ?>
<div class="pagination">
    <a href="?tab=<?= $tab ?>&page=<?= $page-1 ?>" class="pg-btn <?= $page<=1?'disabled':'' ?>">
        <i class="bi bi-chevron-left"></i>
    </a>
    <?php for ($i=1; $i<=$total_pages; $i++): ?>
    <a href="?tab=<?= $tab ?>&page=<?= $i ?>" class="pg-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <a href="?tab=<?= $tab ?>&page=<?= $page+1 ?>" class="pg-btn <?= $page>=$total_pages?'disabled':'' ?>">
        <i class="bi bi-chevron-right"></i>
    </a>
</div>
<?php endif; ?>

</div><!-- /admin-wrap -->

<!-- Confirm Modal: 刪除內容 -->
<div class="modal-overlay" id="resolve-modal">
<div class="modal-box">
    <div class="modal-title"><i class="bi bi-trash3 me-2" style="color:#b91c1c"></i>確認刪除被檢舉內容</div>
    <div class="modal-body" id="resolve-body"></div>
    <form method="POST" action="forum_admin.php?tab=<?= $tab ?>">
        <input type="hidden" name="act"       value="resolve">
        <input type="hidden" name="report_id" id="resolve-id">
        <div class="modal-acts">
            <button type="button" class="btn-mc" onclick="closeModal('resolve-modal')">取消</button>
            <button type="submit" class="btn-danger"><i class="bi bi-trash3 me-1"></i>確認刪除</button>
        </div>
    </form>
</div>
</div>

<!-- Confirm Modal: 駁回 -->
<div class="modal-overlay" id="dismiss-modal">
<div class="modal-box">
    <div class="modal-title"><i class="bi bi-x-circle me-2" style="color:#64748b"></i>確認駁回此檢舉</div>
    <div class="modal-body">確定要駁回此檢舉嗎？內容將保留，此檢舉將標記為「已駁回」。</div>
    <form method="POST" action="forum_admin.php?tab=<?= $tab ?>">
        <input type="hidden" name="act"       value="dismiss">
        <input type="hidden" name="report_id" id="dismiss-id">
        <div class="modal-acts">
            <button type="button" class="btn-mc" onclick="closeModal('dismiss-modal')">取消</button>
            <button type="submit" class="btn-safe"><i class="bi bi-check2 me-1"></i>確認駁回</button>
        </div>
    </form>
</div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', e => { if (e.target===el) closeModal(el.id); });
});

function confirmResolve(id, typeLabel, preview) {
    document.getElementById('resolve-id').value = id;
    document.getElementById('resolve-body').innerHTML =
        '確定要刪除以下' + typeLabel + '並結案此檢舉嗎？<br>' +
        '<div style="margin-top:10px;padding:10px 14px;background:#fef2f2;border-radius:8px;font-size:13px;color:#7f1d1d;">' +
        preview + '</div>' +
        '<div style="margin-top:10px;font-size:13px;color:#b91c1c;font-weight:600;">⚠ 刪除後無法復原。</div>';
    openModal('resolve-modal');
}

function confirmDismiss(id) {
    document.getElementById('dismiss-id').value = id;
    openModal('dismiss-modal');
}
</script>

<?php require "footer.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>