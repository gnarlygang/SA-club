<?php
session_start();
require_once "api/db.php";

$sort_by           = $_GET['sort_by'] ?? 'created_at';
$filter_cat        = $_GET['cat'] ?? '';
$filter_club       = $_GET['club'] ?? '';
$filter_fee        = $_GET['fee'] ?? '';
$filter_subscribed = isset($_GET['subscribed']) && $_GET['subscribed'] === '1';
$hide_closed       = !isset($_GET['show_closed']);
$search            = $_GET['search'] ?? '';
$current_user_id   = $_SESSION['user_id'] ?? 0;

$allowed_sort = ['created_at', 'event_start', 'signup_deadline'];
if (!in_array($sort_by, $allowed_sort)) $sort_by = 'created_at';

$club_categories = ['學術性社團','休閒聯誼性社團','服務性社團','體能性社團','藝術性社團','音樂性社團'];
$activities = $clubs_list = [];
$cat_counts = [];

if (isset($pdo)) {
    $cntRows = $pdo->query("
        SELECT c.category, COUNT(a.id) AS cnt
        FROM activities a LEFT JOIN clubs c ON c.user_id=a.user_id
        WHERE c.category IS NOT NULL GROUP BY c.category
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cntRows as $r) $cat_counts[$r['category']] = $r['cnt'];

    $stmt = $filter_cat
        ? $pdo->prepare("SELECT DISTINCT a.organizer FROM activities a LEFT JOIN clubs c ON c.user_id=a.user_id WHERE c.category=:cat ORDER BY a.organizer")
        : $pdo->query("SELECT DISTINCT organizer FROM activities ORDER BY organizer");
    if ($filter_cat) $stmt->execute([':cat'=>$filter_cat]);
    $clubs_list = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $sql = "SELECT a.*, c.id AS club_id, c.name AS club_name, c.image AS club_image, c.category AS club_category,
                   CASE WHEN f.id IS NOT NULL THEN 1 ELSE 0 END AS is_favorited
            FROM activities a
            LEFT JOIN clubs c ON c.user_id=a.user_id
            LEFT JOIN favorites f ON f.item_id=a.id AND f.item_type='activity' AND f.user_id=:uid
            WHERE 1=1";
    $params = [':uid'=>$current_user_id];

    if ($filter_cat)  { $sql .= " AND c.category=:cat"; $params[':cat']=$filter_cat; }
    if ($filter_subscribed && $current_user_id) {
        $sql .= " AND c.id IN (SELECT club_id FROM subscriptions WHERE user_id=:sub_uid)";
        $params[':sub_uid']=$current_user_id;
    }
    if ($filter_club) { $sql .= " AND a.organizer=:club"; $params[':club']=$filter_club; }
    if ($filter_fee==='free') { $sql .= " AND (a.fee='免費' OR a.fee LIKE '%免費%' OR a.fee='0')"; }
    elseif ($filter_fee==='paid') { $sql .= " AND (a.fee!='免費' AND a.fee NOT LIKE '%免費%' AND a.fee!='0')"; }
    if ($search!=='') { $sql .= " AND (a.title LIKE :s1 OR a.description LIKE :s2)"; $params[':s1']="%$search%"; $params[':s2']="%$search%"; }

    // 排序邏輯：
    // created_at  → 最新發佈排前面 DESC
    // event_start → 最近即將舉辦排前面 ASC（未來活動優先）
    // signup_deadline → 最快截止排前面 ASC
    if ($sort_by === 'event_start' || $sort_by === 'signup_deadline') {
        $sql .= " ORDER BY a.$sort_by ASC";
    } else {
        $sql .= " ORDER BY a.$sort_by DESC";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function isFree($fee): bool { $f=(string)$fee; return str_contains($f,'免費')||strtolower($f)==='free'||$f==='0'; }
function formatDate($dt): string { return empty($dt)?'未設定':date('Y/m/d',strtotime($dt)); }
function formatDateTime($dt): string { return empty($dt)?'未設定':date('Y/m/d H:i',strtotime($dt)); }
function isDeadlineSoon($d): bool { if(empty($d)) return false; $t=strtotime($d); return ($t-time())<=7*86400&&$t>=time(); }
function isDeadlinePassed($d): bool { return !empty($d)&&strtotime($d)<strtotime(date("Y-m-d")); }
function isEventEnded($end): bool { return !empty($end)&&strtotime($end)<time(); }

function qsMerge(array $o, array $ex=[]): string {
    $b=$_GET; foreach($ex as $k) unset($b[$k]);
    $m=array_merge($b,$o); foreach($m as $k=>$v) if($v==='') unset($m[$k]);
    return http_build_query($m);
}

$active_acts   = [];
$deadline_acts = [];
$ended_acts    = [];

foreach ($activities as $act) {
    $deadlinePassed = isDeadlinePassed($act['signup_deadline'] ?? '');
    $eventEnded     = isEventEnded($act['event_end'] ?? '');

    if ($eventEnded) {
        $ended_acts[] = $act;
    } elseif ($deadlinePassed) {
        $deadline_acts[] = $act;
    } else {
        $active_acts[] = $act;
    }
}

require_once "header.php";
?>
<style>
:root {
    --ink: #1a1a2e; --soft: #4a4a6a; --mute: #8888aa;
    --paper: #f5f5f2; --white: #fff; --accent: #c8502a;
    --border: #e2e2dc; --radius: 10px;
    --serif: 'Noto Serif TC', serif; --sans: 'Noto Sans TC', sans-serif;
}
*, *::before, *::after { box-sizing: border-box; }
body { font-family: var(--sans); background: var(--paper); color: var(--ink); margin: 0; }

.page-wrap {
    max-width: none; margin: 0; padding: 1.5rem 1.5rem 4rem;
    display: grid; grid-template-columns: 240px 1fr; gap: 1.5rem; align-items: start;
}

.sidebar {
    background: var(--white); border: 1px solid var(--border);
    border-radius: var(--radius); overflow: hidden;
    position: sticky; top: 80px;
}
.sidebar-section { border-bottom: 1px solid var(--border); }
.sidebar-section:last-child { border-bottom: none; }
.sidebar-heading { font-size:.65rem; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:var(--mute); padding:.85rem 1rem .45rem; }
.sb-search { display:flex; align-items:center; gap:.4rem; margin:0 .75rem .75rem; background:var(--paper); border:1px solid var(--border); border-radius:7px; padding:.4rem .7rem; }
.sb-search svg { width:13px; height:13px; color:var(--mute); flex-shrink:0; }
.sb-search input { border:none; background:transparent; outline:none; font-size:.82rem; color:var(--ink); width:100%; }
.sb-link { display:flex; align-items:center; justify-content:space-between; padding:.5rem 1rem; font-size:.83rem; color:var(--soft); text-decoration:none; transition:background .15s,color .15s; gap:.5rem; }
.sb-link:hover { background:var(--paper); color:var(--ink); }
.sb-link.active { background:#f0ebe8; color:var(--accent); font-weight:600; border-left:3px solid var(--accent); }
.sb-link .cnt { font-size:.68rem; background:var(--paper); color:var(--mute); border-radius:99px; padding:.1rem .4rem; flex-shrink:0; }
.sb-link.active .cnt { background:#f5ddd6; color:var(--accent); }
.sb-radio-group { padding:.2rem .75rem .65rem; }
.sb-radio { display:flex; align-items:center; gap:.5rem; padding:.3rem .3rem; font-size:.82rem; color:var(--soft); cursor:pointer; }
.sb-radio input[type=radio] { accent-color:var(--accent); }
.sb-sort-group { padding:.2rem .75rem .65rem; }
.sb-sort-btn { display:block; width:100%; text-align:left; padding:.35rem .5rem; font-size:.8rem; color:var(--soft); background:none; border:none; border-radius:6px; cursor:pointer; text-decoration:none; transition:background .15s; }
.sb-sort-btn:hover { background:var(--paper); color:var(--ink); }
.sb-sort-btn.active { color:var(--accent); font-weight:600; background:#f0ebe8; }
.sb-sort-hint { font-size:.68rem; color:var(--mute); padding:0 .5rem .3rem; line-height:1.4; }
.sb-toggle-row { display:flex; align-items:center; justify-content:space-between; padding:.6rem 1rem; font-size:.8rem; color:var(--soft); }
.toggle-switch { position:relative; width:32px; height:18px; flex-shrink:0; }
.toggle-switch input { opacity:0; width:0; height:0; }
.toggle-slider { position:absolute; cursor:pointer; inset:0; background:#ccc; border-radius:99px; transition:.2s; }
.toggle-slider::before { content:''; position:absolute; height:12px; width:12px; left:3px; bottom:3px; background:white; border-radius:50%; transition:.2s; }
.toggle-switch input:checked + .toggle-slider { background:var(--accent); }
.toggle-switch input:checked + .toggle-slider::before { transform:translateX(14px); }
.sb-clear { display:block; text-align:center; padding:.55rem; font-size:.75rem; color:var(--mute); text-decoration:none; transition:color .15s; }
.sb-clear:hover { color:var(--accent); }

.content-wrap { min-width:0; }
.top-bar { display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem; flex-wrap:wrap; gap:.5rem; }
.top-bar-title { font-family:var(--serif); font-size:1.2rem; font-weight:700; color:var(--ink); }
.top-bar-count { font-size:.8rem; color:var(--mute); }
.top-bar-count strong { color:var(--ink); }

.filter-chips { display:flex; flex-wrap:wrap; gap:.35rem; margin-bottom:.85rem; }
.chip { display:inline-flex; align-items:center; gap:.3rem; padding:.2rem .65rem; background:#f0ebe8; color:var(--accent); border-radius:99px; font-size:.7rem; font-weight:500; }
.chip a { color:var(--accent); text-decoration:none; font-weight:700; }

.act-list { display:grid; gap:.85rem; }
.act-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; display:grid; grid-template-rows:1fr auto; transition:transform .2s,box-shadow .2s; animation:fadeUp .3s ease both; box-shadow:0 1px 6px rgba(0,0,0,.05); }
.act-card:hover { transform:translateY(-2px); box-shadow:0 5px 20px rgba(0,0,0,.09); }
@keyframes fadeUp { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
.act-card.is-dimmed { opacity:.5; filter:grayscale(.4); box-shadow:none; }
.act-card.is-dimmed:hover { opacity:.7; transform:none; filter:grayscale(.2); }

.act-body { padding:1rem 1.2rem; min-width:0; }
.act-org { display:flex; align-items:center; gap:.4rem; margin-bottom:.45rem; flex-wrap:wrap; }
.act-org-avatar { width:22px; height:22px; border-radius:50%; object-fit:cover; border:1px solid var(--border); flex-shrink:0; background:var(--paper); }
.act-org-name { font-size:.73rem; font-weight:600; color:var(--soft); }
.cat-pill { font-size:.62rem; color:var(--mute); text-decoration:none; border:1px solid var(--border); border-radius:99px; padding:.1rem .4rem; transition:background .15s; }
.cat-pill:hover { background:var(--paper); color:var(--ink); }

.tag { display:inline-block; padding:.12rem .5rem; border-radius:99px; font-size:.65rem; font-weight:600; line-height:1.5; }
.tag-free     { background:#e6f4ec; color:#1e7a47; }
.tag-paid     { background:#fff4e5; color:#b06000; }
.tag-soon     { background:#fff0ec; color:#c8502a; }
.tag-deadline { background:#ebebeb; color:#888; }
.tag-ended    { background:#e8e8f0; color:#5555aa; }

.act-title { font-family:var(--serif); font-size:.98rem; font-weight:700; color:var(--ink); margin-bottom:.35rem; line-height:1.4; }
.act-card.is-dimmed .act-title { color:var(--mute); }
.act-desc { font-size:.78rem; color:var(--soft); line-height:1.6; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; margin-bottom:.6rem; }
.act-meta { display:flex; flex-wrap:wrap; gap:.35rem .75rem; font-size:.72rem; color:var(--mute); }
.act-meta-item { display:flex; align-items:center; gap:.22rem; }
.act-meta-item svg { width:11px; height:11px; flex-shrink:0; }

.act-footer { display:flex; align-items:center; justify-content:space-between; padding:.55rem 1.2rem; border-top:1px solid var(--border); background:#fafaf8; font-size:.7rem; color:var(--mute); flex-wrap:wrap; gap:.35rem; }
.act-footer-left { display:flex; align-items:center; gap:.3rem; }
.act-footer-left svg { width:11px; height:11px; }
.act-footer-right { display:flex; align-items:center; gap:.45rem; }

.view-btn { display:inline-flex; align-items:center; gap:.28rem; background:var(--ink); color:#fff; padding:.28rem .75rem; border-radius:6px; font-size:.7rem; font-weight:500; text-decoration:none; transition:background .18s; }
.view-btn:hover { background:var(--accent); color:#fff; }
.view-btn.dimmed { background:#ccc; color:#888; pointer-events:none; }

.bookmark-btn { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:6px; border:1px solid var(--border); background:#fff; cursor:pointer; color:var(--mute); transition:all .18s; flex-shrink:0; }
.bookmark-btn:hover { border-color:var(--accent); color:var(--accent); }
.bookmark-btn.saved { background:var(--accent); border-color:var(--accent); color:#fff; }
.bookmark-btn svg { width:13px; height:13px; }
.bookmark-btn:disabled { opacity:.3; cursor:not-allowed; pointer-events:none; }

.section-divider { display:flex; align-items:center; gap:.75rem; margin:1.5rem 0 .85rem; }
.section-divider-label { display:flex; align-items:center; gap:.4rem; font-size:.72rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; white-space:nowrap; padding:.25rem .75rem; border-radius:99px; }
.section-divider-label.deadline { background:#f0f0f0; color:#888; }
.section-divider-label.ended    { background:#e8e8f0; color:#5555aa; }
.section-divider::after { content:''; flex:1; height:1px; background:var(--border); }

.collapse-toggle { display:flex; align-items:center; gap:.5rem; width:100%; background:none; border:1px dashed var(--border); border-radius:var(--radius); padding:.6rem 1rem; font-size:.8rem; color:var(--mute); cursor:pointer; transition:all .18s; text-align:left; }
.collapse-toggle:hover { border-color:var(--accent); color:var(--accent); background:#fff8f6; }
.collapse-toggle svg { width:14px; height:14px; flex-shrink:0; transition:transform .2s; }
.collapse-toggle.open svg { transform:rotate(180deg); }
.collapse-body { display:grid; gap:.85rem; margin-top:.85rem; }
.collapse-body[hidden] { display:none; }

.act-empty { text-align:center; padding:3.5rem 1rem; color:var(--mute); }
.act-empty svg { width:44px; height:44px; margin-bottom:.85rem; }
.act-empty h3 { font-family:var(--serif); font-size:1.05rem; color:var(--soft); margin-bottom:.35rem; }
.act-empty p { font-size:.82rem; }

@media (max-width:768px) {
    .page-wrap { grid-template-columns:1fr; }
    .sidebar { position:static; }
}
</style>

<div class="page-wrap">

<!-- ══ SIDEBAR ══ -->
<aside class="sidebar">
    <div class="sidebar-section">
        <div class="sidebar-heading">搜尋活動</div>
        <form method="GET" action="">
            <?php if ($filter_cat):       ?><input type="hidden" name="cat"         value="<?= htmlspecialchars($filter_cat) ?>"><?php endif; ?>
            <?php if ($filter_club):      ?><input type="hidden" name="club"        value="<?= htmlspecialchars($filter_club) ?>"><?php endif; ?>
            <?php if ($filter_fee):       ?><input type="hidden" name="fee"         value="<?= htmlspecialchars($filter_fee) ?>"><?php endif; ?>
            <?php if ($filter_subscribed):?><input type="hidden" name="subscribed"  value="1"><?php endif; ?>
            <?php if (!$hide_closed):     ?><input type="hidden" name="show_closed" value="1"><?php endif; ?>
            <input type="hidden" name="sort_by" value="<?= htmlspecialchars($sort_by) ?>">
            <div class="sb-search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                <input type="text" name="search" placeholder="搜尋活動名稱…" value="<?= htmlspecialchars($search) ?>">
            </div>
        </form>
    </div>

    <div class="sidebar-section">
        <div class="sb-toggle-row">
            <span>隱藏已截止活動</span>
            <label class="toggle-switch">
                <input type="checkbox" <?= $hide_closed?'checked':'' ?> onchange="toggleHideClosed(this)">
                <span class="toggle-slider"></span>
            </label>
        </div>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-heading">社團分類</div>
        <a href="activities.php?<?= qsMerge(['subscribed'=>$filter_subscribed?'':'1','cat'=>'','club'=>'','sort_by'=>$sort_by,'search'=>$search,'fee'=>$filter_fee]) ?>"
           class="sb-link <?= $filter_subscribed?'active':'' ?>">
            <span style="display:flex;align-items:center;gap:.35rem;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                已訂閱社團
            </span>
        </a>
        <a href="activities.php?<?= qsMerge(['cat'=>'','club'=>'','subscribed'=>'','sort_by'=>$sort_by,'search'=>$search,'fee'=>$filter_fee]) ?>"
           class="sb-link <?= $filter_cat===''&&!$filter_subscribed?'active':'' ?>">
            所有活動 <span class="cnt"><?= count($activities) ?></span>
        </a>
        <?php foreach ($club_categories as $c): ?>
        <a href="activities.php?<?= qsMerge(['cat'=>$c,'club'=>'','subscribed'=>'','sort_by'=>$sort_by,'search'=>$search,'fee'=>$filter_fee]) ?>"
           class="sb-link <?= $filter_cat===$c?'active':'' ?>">
            <?= htmlspecialchars($c) ?>
            <?php if (isset($cat_counts[$c])): ?><span class="cnt"><?= $cat_counts[$c] ?></span><?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-heading">費用</div>
        <div class="sb-radio-group">
            <form method="GET" id="feeForm">
                <?php if ($filter_cat):       ?><input type="hidden" name="cat"         value="<?= htmlspecialchars($filter_cat) ?>"><?php endif; ?>
                <?php if ($filter_club):      ?><input type="hidden" name="club"        value="<?= htmlspecialchars($filter_club) ?>"><?php endif; ?>
                <?php if ($search):           ?><input type="hidden" name="search"      value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                <?php if ($filter_subscribed):?><input type="hidden" name="subscribed"  value="1"><?php endif; ?>
                <?php if (!$hide_closed):     ?><input type="hidden" name="show_closed" value="1"><?php endif; ?>
                <input type="hidden" name="sort_by" value="<?= htmlspecialchars($sort_by) ?>">
                <label class="sb-radio"><input type="radio" name="fee" value=""     <?= $filter_fee===''?'checked':'' ?> onchange="this.form.submit()"> 全部</label>
                <label class="sb-radio"><input type="radio" name="fee" value="free" <?= $filter_fee==='free'?'checked':'' ?> onchange="this.form.submit()"> 免費活動</label>
                <label class="sb-radio"><input type="radio" name="fee" value="paid" <?= $filter_fee==='paid'?'checked':'' ?> onchange="this.form.submit()"> 需收費</label>
            </form>
        </div>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-heading">排序方式</div>
        <div class="sb-sort-group">
            <a href="activities.php?<?= qsMerge(['sort_by'=>'created_at']) ?>"
               class="sb-sort-btn <?= $sort_by==='created_at'?'active':'' ?>">
                最新發佈
            </a>
            <?php if ($sort_by==='created_at'): ?>
            <div class="sb-sort-hint">↓ 最新發佈的排最前面</div>
            <?php endif; ?>

            <a href="activities.php?<?= qsMerge(['sort_by'=>'event_start']) ?>"
               class="sb-sort-btn <?= $sort_by==='event_start'?'active':'' ?>">
                活動時間（近→遠）
            </a>
            <?php if ($sort_by==='event_start'): ?>
            <div class="sb-sort-hint">↓ 最快舉辦的活動排最前面</div>
            <?php endif; ?>

            <a href="activities.php?<?= qsMerge(['sort_by'=>'signup_deadline']) ?>"
               class="sb-sort-btn <?= $sort_by==='signup_deadline'?'active':'' ?>">
                報名截止（近→遠）
            </a>
            <?php if ($sort_by==='signup_deadline'): ?>
            <div class="sb-sort-hint">↓ 最快截止報名的排最前面</div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($filter_cat||$filter_club||$filter_fee||$search||$filter_subscribed): ?>
    <a href="activities.php" class="sb-clear">✕ 清除所有篩選</a>
    <?php endif; ?>
</aside>

<!-- ══ CONTENT ══ -->
<div class="content-wrap">
    <div class="top-bar">
        <div>
            <div class="top-bar-title"><?= $filter_subscribed?'已訂閱社團活動':($filter_cat?htmlspecialchars($filter_cat):'所有活動') ?></div>
            <div class="top-bar-count">
                進行中 <strong><?= count($active_acts) ?></strong> 項
                <?php if ($deadline_acts): ?>　報名截止 <strong><?= count($deadline_acts) ?></strong> 項<?php endif; ?>
                <?php if ($ended_acts): ?>　活動已結束 <strong><?= count($ended_acts) ?></strong> 項<?php endif; ?>
            </div>
        </div>
    </div>

    <?php $hasFilter=$filter_cat||$filter_club||$filter_fee||$search||$filter_subscribed; ?>
    <?php if ($hasFilter): ?>
    <div class="filter-chips">
        <?php if ($filter_subscribed): ?><span class="chip">已訂閱社團 <a href="activities.php?<?= qsMerge(['subscribed'=>'']) ?>">✕</a></span><?php endif; ?>
        <?php if ($filter_cat): ?><span class="chip">分類：<?= htmlspecialchars($filter_cat) ?> <a href="activities.php?<?= qsMerge(['cat'=>'','club'=>'']) ?>">✕</a></span><?php endif; ?>
        <?php if ($filter_club): ?><span class="chip">社團：<?= htmlspecialchars($filter_club) ?> <a href="activities.php?<?= qsMerge(['club'=>'']) ?>">✕</a></span><?php endif; ?>
        <?php if ($filter_fee==='free'): ?><span class="chip">免費 <a href="activities.php?<?= qsMerge(['fee'=>'']) ?>">✕</a></span><?php endif; ?>
        <?php if ($filter_fee==='paid'): ?><span class="chip">需收費 <a href="activities.php?<?= qsMerge(['fee'=>'']) ?>">✕</a></span><?php endif; ?>
        <?php if ($search): ?><span class="chip">「<?= htmlspecialchars($search) ?>」 <a href="activities.php?<?= qsMerge(['search'=>'']) ?>">✕</a></span><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    function renderCard(array $act, bool $dimmed, string $btnLabel, bool $btnDisabled): void {
        $free  = isFree($act['fee']??'');
        $soon  = !$dimmed && isDeadlineSoon($act['signup_deadline']??'');
        $isFav = !empty($act['is_favorited']);
        $img   = !empty($act['club_image']) ? $act['club_image']
               : 'https://images.unsplash.com/photo-1507838153414-b4b713384a76?auto=format&fit=crop&w=800&q=80';
        $dimClass = $dimmed ? ' is-dimmed' : '';
        echo '<article class="act-card'.$dimClass.'">';
        echo '<div class="act-body">';
        echo '<div class="act-org">';
        echo '<img class="act-org-avatar" src="'.htmlspecialchars($img).'" alt="">';
        echo '<span class="act-org-name">'.htmlspecialchars($act['organizer']??$act['club_name']??'').'</span>';
        if (!empty($act['club_category'])) {
            $qs = qsMerge(['cat'=>$act['club_category'],'club'=>'']);
            echo '<a href="activities.php?'.$qs.'" class="cat-pill">'.htmlspecialchars($act['club_category']).'</a>';
        }
        echo $free ? '<span class="tag tag-free">免費</span>' : '<span class="tag tag-paid">'.htmlspecialchars($act['fee']??'').'</span>';
        if ($soon) echo '<span class="tag tag-soon">即將截止</span>';
        if ($dimmed && !isEventEnded($act['event_end']??'')) echo '<span class="tag tag-deadline">報名截止</span>';
        if ($dimmed && isEventEnded($act['event_end']??''))  echo '<span class="tag tag-ended">活動已結束</span>';
        echo '</div>';
        echo '<h2 class="act-title">'.htmlspecialchars($act['title']??'').'</h2>';
        echo '<p class="act-desc">'.htmlspecialchars($act['description']??'').'</p>';
        echo '<div class="act-meta">';
        echo '<span class="act-meta-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>'.formatDateTime($act['event_start']??'');
        if (!empty($act['event_end'])) echo ' – '.date('H:i',strtotime($act['event_end']));
        echo '</span>';
        echo '<span class="act-meta-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>'.htmlspecialchars($act['location']??'').'</span>';
        echo '</div></div>';
        echo '<div class="act-footer">';
        echo '<span class="act-footer-left"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>報名截止：'.formatDate($act['signup_deadline']??'').' &nbsp;·&nbsp; 發佈於 '.formatDate($act['created_at']??'').'</span>';
        echo '<span class="act-footer-right">';
        $disAttr = ($dimmed||$btnDisabled) ? ' disabled' : '';
        $favFill = $isFav ? 'currentColor' : 'none';
        $favCls  = $isFav ? ' saved' : '';
        $dataId  = htmlspecialchars($act['id']);
        echo '<button class="bookmark-btn'.$favCls.'" data-type="activity" data-id="'.$dataId.'" onclick="toggleFavorite(this)"'.$disAttr.'>';
        echo '<svg viewBox="0 0 24 24" fill="'.$favFill.'" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/></svg></button>';
        $btnCls = ($dimmed||$btnDisabled) ? ' dimmed' : '';
        echo '<a href="activity_view.php?id='.$dataId.'" class="view-btn'.$btnCls.'">'.$btnLabel;
        if (!$dimmed&&!$btnDisabled) echo ' <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';
        echo '</a></span></div></article>';
    }
    ?>

    <!-- 進行中 -->
    <?php if (empty($active_acts)&&empty($deadline_acts)&&empty($ended_acts)): ?>
    <div class="act-empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        <h3>找不到符合條件的活動</h3>
        <p>請調整篩選條件，或 <a href="activities.php" style="color:var(--accent)">查看全部活動</a></p>
    </div>
    <?php elseif (empty($active_acts)): ?>
    <div class="act-empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
        <h3>目前沒有進行中的活動</h3>
        <p>可查看下方報名截止或已結束的歷史活動</p>
    </div>
    <?php else: ?>
    <div class="act-list">
        <?php foreach ($active_acts as $act) renderCard($act, false, '查看詳情', false); ?>
    </div>
    <?php endif; ?>

    <!-- 報名截止 -->
    <?php if (!empty($deadline_acts)): ?>
    <div class="section-divider">
        <span class="section-divider-label deadline">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
            報名截止（<?= count($deadline_acts) ?> 項）
        </span>
    </div>
    <button class="collapse-toggle" id="deadlineToggle" onclick="toggleSection('deadlineBody','deadlineToggle')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
        顯示報名截止活動
    </button>
    <div class="collapse-body" id="deadlineBody" hidden>
        <?php foreach ($deadline_acts as $act) renderCard($act, true, '報名截止', true); ?>
    </div>
    <?php endif; ?>

    <!-- 活動已結束 -->
    <?php if (!empty($ended_acts)): ?>
    <div class="section-divider">
        <span class="section-divider-label ended">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
            活動已結束（<?= count($ended_acts) ?> 項）
        </span>
    </div>
    <button class="collapse-toggle" id="endedToggle" onclick="toggleSection('endedBody','endedToggle')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
        顯示已結束活動
    </button>
    <div class="collapse-body" id="endedBody" hidden>
        <?php foreach ($ended_acts as $act) renderCard($act, true, '查看紀錄', false); ?>
    </div>
    <?php endif; ?>

</div>
</div>

<script>
function toggleSection(bodyId, btnId) {
    const body = document.getElementById(bodyId);
    const btn  = document.getElementById(btnId);
    const isHidden = body.hidden;
    body.hidden = !isHidden;
    btn.classList.toggle('open', isHidden);
    const labels = { deadlineBody:'報名截止活動', endedBody:'已結束活動' };
    const label = labels[bodyId] || '活動';
    btn.childNodes[1].textContent = isHidden ? (' 隱藏'+label) : (' 顯示'+label);
}

function toggleHideClosed(cb) {
    const url = new URL(window.location.href);
    cb.checked ? url.searchParams.delete('show_closed') : url.searchParams.set('show_closed','1');
    window.location.href = url.toString();
}

function toggleFavorite(btn) {
    if (btn.disabled) return;
    fetch("api/toggle_favorite.php", {
        method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"},
        body:"item_id="+encodeURIComponent(btn.dataset.id)+"&item_type="+encodeURIComponent(btn.dataset.type)
    }).then(r=>r.json()).then(data=>{
        if (!data.success) { alert(data.message); return; }
        const icon = btn.querySelector("svg");
        if (data.favorited) {
            btn.classList.add("saved"); btn.title="取消收藏";
            icon&&icon.setAttribute("fill","currentColor"); showToast("已加入收藏 🔖");
        } else {
            btn.classList.remove("saved"); btn.title="收藏";
            icon&&icon.setAttribute("fill","none"); showToast("已取消收藏");
        }
    }).catch(()=>alert("收藏操作失敗"));
}

function showToast(msg) {
    let t=document.getElementById("fav-toast");
    if (!t) { t=document.createElement("div"); t.id="fav-toast"; t.style.cssText="position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;background:#1a1a2e;color:#fff;padding:.5rem 1rem;border-radius:8px;font-size:.78rem;font-weight:500;box-shadow:0 4px 16px rgba(0,0,0,.2);transition:opacity .3s;opacity:0;pointer-events:none;"; document.body.appendChild(t); }
    t.textContent=msg; t.style.opacity="1";
    clearTimeout(t._t); t._t=setTimeout(()=>{t.style.opacity="0";},2000);
}
</script>

<?php include 'footer.php'; ?>
</body>
</html>