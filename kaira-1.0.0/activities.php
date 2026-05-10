<?php
session_start();

require_once "api/db.php";

$sort_by           = $_GET['sort_by'] ?? 'created_at';
$filter_cat        = $_GET['cat'] ?? '';
$filter_club       = $_GET['club'] ?? '';
$filter_fee        = $_GET['fee'] ?? '';
$filter_subscribed = isset($_GET['subscribed']) && $_GET['subscribed'] === '1';
$search            = $_GET['search'] ?? '';

$current_user_id = $_SESSION['user_id'] ?? 0;

$allowed_sort = ['created_at', 'event_start', 'signup_deadline'];
if (!in_array($sort_by, $allowed_sort)) {
    $sort_by = 'created_at';
}

$club_categories = [
    '學術性社團',
    '休閒聯誼性社團',
    '服務性社團',
    '體能性社團',
    '藝術性社團',
    '音樂性社團'
];

$activities = [];
$clubs_list = [];
$cat_counts = [];

if (isset($pdo)) {

    // 各分類活動數
    $cntRows = $pdo->query("
        SELECT c.category, COUNT(a.id) AS cnt
        FROM activities a
        LEFT JOIN clubs c ON c.user_id = a.user_id
        WHERE c.category IS NOT NULL
        GROUP BY c.category
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cntRows as $r) {
        $cat_counts[$r['category']] = $r['cnt'];
    }

    // 社團列表
    if ($filter_cat) {
        $stmt = $pdo->prepare("
            SELECT DISTINCT a.organizer
            FROM activities a
            LEFT JOIN clubs c ON c.user_id = a.user_id
            WHERE c.category = :cat
            ORDER BY a.organizer
        ");
        $stmt->execute([
            ':cat' => $filter_cat
        ]);
    } else {
        $stmt = $pdo->query("
            SELECT DISTINCT organizer
            FROM activities
            ORDER BY organizer
        ");
    }

    $clubs_list = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 主查詢：加入 favorites 判斷收藏狀態
    $sql = "
        SELECT 
            a.*,
            c.id AS club_id,
            c.name AS club_name,
            c.image AS club_image,
            c.category AS club_category,
            CASE 
                WHEN f.id IS NOT NULL THEN 1
                ELSE 0
            END AS is_favorited
        FROM activities a
        LEFT JOIN clubs c ON c.user_id = a.user_id
        LEFT JOIN favorites f
            ON f.item_id = a.id
            AND f.item_type = 'activity'
            AND f.user_id = :current_user_id
        WHERE 1=1
    ";

    $params = [
        ':current_user_id' => $current_user_id
    ];

    if ($filter_cat) {
        $sql .= " AND c.category = :cat";
        $params[':cat'] = $filter_cat;
    }

    if ($filter_subscribed && isset($_SESSION['user_id'])) {
        $sql .= "
            AND c.id IN (
                SELECT club_id
                FROM subscriptions
                WHERE user_id = :uid
            )
        ";
        $params[':uid'] = $_SESSION['user_id'];
    }

    if ($filter_club) {
        $sql .= " AND a.organizer = :club";
        $params[':club'] = $filter_club;
    }

    if ($filter_fee === 'free') {
        $sql .= " AND (a.fee = '免費' OR a.fee LIKE '%免費%' OR a.fee = '0')";
    } elseif ($filter_fee === 'paid') {
        $sql .= " AND (a.fee != '免費' AND a.fee NOT LIKE '%免費%' AND a.fee != '0')";
    }

    if ($search !== '') {
        $sql .= " AND (a.title LIKE :s1 OR a.description LIKE :s2)";
        $params[':s1'] = "%$search%";
        $params[':s2'] = "%$search%";
    }

    $sql .= " ORDER BY a.$sort_by DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function isFree($fee): bool {
    $fee = (string) $fee;
    return str_contains($fee, '免費') || strtolower($fee) === 'free' || $fee === '0';
}

function formatDate($dt): string {
    if (empty($dt)) return '未設定';
    return date('Y/m/d', strtotime($dt));
}

function formatDateTime($dt): string {
    if (empty($dt)) return '未設定';
    return date('Y/m/d H:i', strtotime($dt));
}

function isDeadlineSoon($d): bool {
    if (empty($d)) return false;
    $t = strtotime($d);
    return ($t - time()) <= 7 * 86400 && $t >= time();
}

function isDeadlinePassed($d): bool {
    if (empty($d)) return false;
    return strtotime($d) < time();
}

function qsMerge(array $override, array $exclude = []): string {
    $base = $_GET;

    foreach ($exclude as $k) {
        unset($base[$k]);
    }

    $merged = array_merge($base, $override);

    foreach ($merged as $key => $value) {
        if ($value === '') {
            unset($merged[$key]);
        }
    }

    return http_build_query($merged);
}

require_once "header.php";
?>

<style>
:root {
    --ink:    #1a1a2e;
    --soft:   #4a4a6a;
    --mute:   #8888aa;
    --paper:  #f5f5f2;
    --white:  #ffffff;
    --accent: #c8502a;
    --green:  #2d8a5e;
    --border: #e2e2dc;
    --radius: 10px;
    --serif:  'Noto Serif TC', serif;
    --sans:   'Noto Sans TC', sans-serif;
}

*, *::before, *::after {
    box-sizing: border-box;
}

body {
    font-family: var(--sans);
    background: var(--paper);
    color: var(--ink);
    margin: 0;
}

.page-wrap {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1.5rem 1.5rem 4rem;
    display: flex;
    gap: 1.5rem;
    align-items: flex-start;
}

.sidebar {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    position: sticky;
    top: 80px;
    flex: 0 0 240px;
    width: 240px;
    align-self: flex-start;
}

.content-wrap {
    flex: 1;
    min-width: 0;
}

.sidebar-section {
    border-bottom: 1px solid var(--border);
}

.sidebar-section:last-child {
    border-bottom: none;
}

.sidebar-heading {
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: var(--mute);
    padding: .9rem 1rem .5rem;
}

.sb-search {
    display: flex;
    align-items: center;
    gap: .4rem;
    margin: 0 .75rem .75rem;
    background: var(--paper);
    border: 1px solid var(--border);
    border-radius: 7px;
    padding: .4rem .7rem;
}

.sb-search svg {
    width: 14px;
    height: 14px;
    color: var(--mute);
    flex-shrink: 0;
}

.sb-search input {
    border: none;
    background: transparent;
    outline: none;
    font-size: .83rem;
    color: var(--ink);
    width: 100%;
}

.sb-link {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .55rem 1rem;
    font-size: .85rem;
    color: var(--soft);
    text-decoration: none;
    transition: background .15s, color .15s;
    gap: .5rem;
}

.sb-link:hover {
    background: var(--paper);
    color: var(--ink);
}

.sb-link.active {
    background: #f0ebe8;
    color: var(--accent);
    font-weight: 600;
    border-left: 3px solid var(--accent);
}

.sb-link .cnt {
    font-size: .7rem;
    background: var(--paper);
    color: var(--mute);
    border-radius: 99px;
    padding: .1rem .45rem;
    flex-shrink: 0;
}

.sb-link.active .cnt {
    background: #f5ddd6;
    color: var(--accent);
}

.sb-radio-group {
    padding: .25rem .75rem .75rem;
}

.sb-radio {
    display: flex;
    align-items: center;
    gap: .5rem;
    padding: .35rem .3rem;
    font-size: .83rem;
    color: var(--soft);
    cursor: pointer;
}

.sb-radio input[type=radio] {
    accent-color: var(--accent);
}

.sb-sort-group {
    padding: .25rem .75rem .75rem;
}

.sb-sort-btn {
    display: block;
    width: 100%;
    text-align: left;
    padding: .38rem .5rem;
    font-size: .82rem;
    color: var(--soft);
    background: none;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    transition: background .15s;
}

.sb-sort-btn:hover {
    background: var(--paper);
    color: var(--ink);
}

.sb-sort-btn.active {
    color: var(--accent);
    font-weight: 600;
    background: #f0ebe8;
}

.sb-clear {
    display: block;
    text-align: center;
    padding: .6rem;
    font-size: .78rem;
    color: var(--mute);
    text-decoration: none;
    transition: color .15s;
}

.sb-clear:hover {
    color: var(--accent);
}

.top-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: .5rem;
}

.top-bar-title {
    font-family: var(--serif);
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--ink);
}

.top-bar-count {
    font-size: .82rem;
    color: var(--mute);
}

.top-bar-count strong {
    color: var(--ink);
}

.filter-chips {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
    margin-bottom: .85rem;
}

.chip {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .2rem .65rem;
    background: #f0ebe8;
    color: var(--accent);
    border-radius: 99px;
    font-size: .72rem;
    font-weight: 500;
}

.chip a {
    color: var(--accent);
    text-decoration: none;
    font-weight: 700;
}

.act-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(420px, 1fr));
    gap: 1rem;
}

.act-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    display: grid;
    grid-template-rows: 1fr auto;
    transition: transform .2s, box-shadow .2s;
    animation: fadeUp .35s ease both;
    box-shadow: 0 1px 8px rgba(0,0,0,.05);
}

.act-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 24px rgba(0,0,0,.1);
}

@keyframes fadeUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.act-body {
    padding: 1.1rem 1.25rem;
    min-width: 0;
}

.act-org {
    display: flex;
    align-items: center;
    gap: .45rem;
    margin-bottom: .5rem;
    flex-wrap: wrap;
}

.act-org-name {
    font-size: .75rem;
    font-weight: 500;
    color: var(--soft);
}

.tag {
    display: inline-block;
    padding: .15rem .55rem;
    border-radius: 99px;
    font-size: .68rem;
    font-weight: 600;
    line-height: 1.5;
}

.tag-free {
    background: #e6f4ec;
    color: #1e7a47;
}

.tag-paid {
    background: #fff4e5;
    color: #b06000;
}

.tag-soon {
    background: #fff0ec;
    color: #c8502a;
}

.tag-closed {
    background: #f0f0f0;
    color: #999;
}

.act-title {
    font-family: var(--serif);
    font-size: 1rem;
    font-weight: 700;
    color: var(--ink);
    margin-bottom: .4rem;
    line-height: 1.4;
}

.act-desc {
    font-size: .8rem;
    color: var(--soft);
    line-height: 1.6;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: .75rem;
}

.act-meta {
    display: flex;
    flex-wrap: wrap;
    gap: .4rem .8rem;
    font-size: .74rem;
    color: var(--mute);
}

.act-meta-item {
    display: flex;
    align-items: center;
    gap: .25rem;
}

.act-meta-item svg {
    width: 12px;
    height: 12px;
    flex-shrink: 0;
}

.act-footer {
    grid-column: 1 / -1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .6rem 1.25rem;
    border-top: 1px solid var(--border);
    background: #fafaf8;
    font-size: .73rem;
    color: var(--mute);
    flex-wrap: wrap;
    gap: .4rem;
}

.act-footer-left {
    display: flex;
    align-items: center;
    gap: .35rem;
}

.act-footer-left svg {
    width: 12px;
    height: 12px;
}

.act-footer-right {
    display: flex;
    align-items: center;
    gap: .5rem;
}

.view-btn {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    background: var(--ink);
    color: #fff;
    padding: .3rem .8rem;
    border-radius: 6px;
    font-size: .73rem;
    font-weight: 500;
    text-decoration: none;
    transition: background .18s;
}

.view-btn:hover {
    background: var(--accent);
    color: #fff;
}

.view-btn.closed {
    background: #e0e0e0;
    color: #aaa;
    pointer-events: none;
}

.bookmark-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: #fff;
    cursor: pointer;
    color: var(--mute);
    transition: all .18s;
    flex-shrink: 0;
}

.bookmark-btn:hover {
    border-color: var(--accent);
    color: var(--accent);
}

.bookmark-btn.saved {
    background: var(--accent);
    border-color: var(--accent);
    color: #fff;
}

.bookmark-btn svg {
    width: 14px;
    height: 14px;
}

.act-empty {
    text-align: center;
    padding: 4rem 1rem;
    color: var(--mute);
    min-height: 300px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.act-empty svg {
    width: 48px;
    height: 48px;
    margin-bottom: 1rem;
}

.act-empty h3 {
    font-family: var(--serif);
    font-size: 1.1rem;
    color: var(--soft);
    margin-bottom: .4rem;
}

@media (max-width: 768px) {
    .page-wrap {
        flex-direction: column;
    }

    .sidebar {
        position: static;
        width: 100%;
        flex: none;
    }

    .act-list {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="page-wrap">

    <aside class="sidebar">

        <div class="sidebar-section">
            <div class="sidebar-heading">搜尋活動</div>

            <form method="GET" action="">
                <?php if ($filter_cat): ?>
                    <input type="hidden" name="cat" value="<?= htmlspecialchars($filter_cat) ?>">
                <?php endif; ?>

                <?php if ($filter_club): ?>
                    <input type="hidden" name="club" value="<?= htmlspecialchars($filter_club) ?>">
                <?php endif; ?>

                <?php if ($filter_fee): ?>
                    <input type="hidden" name="fee" value="<?= htmlspecialchars($filter_fee) ?>">
                <?php endif; ?>

                <?php if ($filter_subscribed): ?>
                    <input type="hidden" name="subscribed" value="1">
                <?php endif; ?>

                <input type="hidden" name="sort_by" value="<?= htmlspecialchars($sort_by) ?>">

                <div class="sb-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="M21 21l-4.35-4.35"/>
                    </svg>

                    <input 
                        type="text" 
                        name="search" 
                        placeholder="搜尋活動名稱…" 
                        value="<?= htmlspecialchars($search) ?>"
                    >
                </div>
            </form>
        </div>

        <div class="sidebar-section">
            <div class="sidebar-heading">社團分類</div>

            <a 
                href="activities.php?<?= qsMerge([
                    'subscribed' => $filter_subscribed ? '' : '1',
                    'cat' => '',
                    'club' => '',
                    'sort_by' => $sort_by,
                    'search' => $search,
                    'fee' => $filter_fee
                ]) ?>"
                class="sb-link <?= $filter_subscribed ? 'active' : '' ?>"
            >
                <span style="display:flex;align-items:center;gap:.4rem;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 01-3.46 0"/>
                    </svg>
                    已訂閱社團
                </span>
            </a>

            <a 
                href="activities.php?<?= qsMerge([
                    'cat' => '',
                    'club' => '',
                    'subscribed' => '',
                    'sort_by' => $sort_by,
                    'search' => $search,
                    'fee' => $filter_fee
                ]) ?>"
                class="sb-link <?= $filter_cat === '' && !$filter_subscribed ? 'active' : '' ?>"
            >
                所有活動
            </a>

            <?php foreach ($club_categories as $cat): ?>
                <a 
                    href="activities.php?<?= qsMerge([
                        'cat' => $cat,
                        'club' => '',
                        'subscribed' => '',
                        'sort_by' => $sort_by,
                        'search' => $search,
                        'fee' => $filter_fee
                    ]) ?>"
                    class="sb-link <?= $filter_cat === $cat ? 'active' : '' ?>"
                >
                    <?= htmlspecialchars($cat) ?>

                    <?php if (isset($cat_counts[$cat])): ?>
                        <span class="cnt"><?= htmlspecialchars($cat_counts[$cat]) ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="sidebar-section">
            <div class="sidebar-heading">費用</div>

            <div class="sb-radio-group">
                <form method="GET" id="feeForm">

                    <?php if ($filter_cat): ?>
                        <input type="hidden" name="cat" value="<?= htmlspecialchars($filter_cat) ?>">
                    <?php endif; ?>

                    <?php if ($filter_club): ?>
                        <input type="hidden" name="club" value="<?= htmlspecialchars($filter_club) ?>">
                    <?php endif; ?>

                    <?php if ($search): ?>
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                    <?php endif; ?>

                    <?php if ($filter_subscribed): ?>
                        <input type="hidden" name="subscribed" value="1">
                    <?php endif; ?>

                    <input type="hidden" name="sort_by" value="<?= htmlspecialchars($sort_by) ?>">

                    <label class="sb-radio">
                        <input 
                            type="radio" 
                            name="fee" 
                            value="" 
                            <?= $filter_fee === '' ? 'checked' : '' ?> 
                            onchange="this.form.submit()"
                        >
                        全部
                    </label>

                    <label class="sb-radio">
                        <input 
                            type="radio" 
                            name="fee" 
                            value="free" 
                            <?= $filter_fee === 'free' ? 'checked' : '' ?> 
                            onchange="this.form.submit()"
                        >
                        免費活動
                    </label>

                    <label class="sb-radio">
                        <input 
                            type="radio" 
                            name="fee" 
                            value="paid" 
                            <?= $filter_fee === 'paid' ? 'checked' : '' ?> 
                            onchange="this.form.submit()"
                        >
                        需收費
                    </label>
                </form>
            </div>
        </div>

        <div class="sidebar-section">
            <div class="sidebar-heading">排序方式</div>

            <div class="sb-sort-group">
                <?php
                $sorts = [
                    'created_at' => '依發佈時間（新→舊）',
                    'event_start' => '依活動時間',
                    'signup_deadline' => '依報名截止'
                ];

                foreach ($sorts as $val => $label):
                ?>
                    <a 
                        href="activities.php?<?= qsMerge(['sort_by' => $val]) ?>"
                        class="sb-sort-btn <?= $sort_by === $val ? 'active' : '' ?>"
                    >
                        <?= htmlspecialchars($label) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($filter_cat || $filter_club || $filter_fee || $search || $filter_subscribed): ?>
            <a href="activities.php" class="sb-clear">✕ 清除所有篩選</a>
        <?php endif; ?>

    </aside>

    <div class="content-wrap">

        <div class="top-bar">
            <div>
                <div class="top-bar-title">
                    <?php
                    if ($filter_subscribed) {
                        echo '已訂閱社團活動';
                    } elseif ($filter_cat) {
                        echo htmlspecialchars($filter_cat);
                    } else {
                        echo '所有活動';
                    }
                    ?>
                </div>

                <div class="top-bar-count">
                    共 <strong><?= count($activities) ?></strong> 項活動
                </div>
            </div>
        </div>

        <?php
        $hasFilter = $filter_cat || $filter_club || $filter_fee || $search || $filter_subscribed;
        ?>

        <?php if ($hasFilter): ?>
            <div class="filter-chips">

                <?php if ($filter_subscribed): ?>
                    <span class="chip">
                        已訂閱社團
                        <a href="activities.php?<?= qsMerge(['subscribed' => '']) ?>">✕</a>
                    </span>
                <?php endif; ?>

                <?php if ($filter_cat): ?>
                    <span class="chip">
                        分類：<?= htmlspecialchars($filter_cat) ?>
                        <a href="activities.php?<?= qsMerge(['cat' => '', 'club' => '']) ?>">✕</a>
                    </span>
                <?php endif; ?>

                <?php if ($filter_club): ?>
                    <span class="chip">
                        社團：<?= htmlspecialchars($filter_club) ?>
                        <a href="activities.php?<?= qsMerge(['club' => '']) ?>">✕</a>
                    </span>
                <?php endif; ?>

                <?php if ($filter_fee === 'free'): ?>
                    <span class="chip">
                        免費
                        <a href="activities.php?<?= qsMerge(['fee' => '']) ?>">✕</a>
                    </span>
                <?php elseif ($filter_fee === 'paid'): ?>
                    <span class="chip">
                        需收費
                        <a href="activities.php?<?= qsMerge(['fee' => '']) ?>">✕</a>
                    </span>
                <?php endif; ?>

                <?php if ($search): ?>
                    <span class="chip">
                        「<?= htmlspecialchars($search) ?>」
                        <a href="activities.php?<?= qsMerge(['search' => '']) ?>">✕</a>
                    </span>
                <?php endif; ?>

            </div>
        <?php endif; ?>

        <?php if (empty($activities)): ?>

            <div class="act-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>

                <h3>找不到符合條件的活動</h3>
                <p>
                    請調整篩選條件，或
                    <a href="activities.php" style="color:var(--accent)">查看全部活動</a>
                </p>
            </div>

        <?php else: ?>

            <div class="act-list">

                <?php foreach ($activities as $i => $act): ?>
                    <?php
                    $free = isFree($act['fee'] ?? '');
                    $soon = isDeadlineSoon($act['signup_deadline'] ?? '');
                    $closed = isDeadlinePassed($act['signup_deadline'] ?? '');

                    $img = !empty($act['club_image'])
                        ? $act['club_image']
                        : 'https://images.unsplash.com/photo-1507838153414-b4b713384a76?auto=format&fit=crop&w=800&q=80';

                    $isFavorited = !empty($act['is_favorited']);
                    ?>

                    <article class="act-card" style="animation-delay:<?= $i * 0.05 ?>s">

                        <div class="act-body">

                            <div class="act-org">
                                <span class="act-org-name">
                                    <?= htmlspecialchars($act['organizer'] ?? $act['club_name'] ?? '未指定社團') ?>
                                </span>

                                <?php if (!empty($act['club_category'])): ?>
                                    <a 
                                        href="activities.php?<?= qsMerge(['cat' => $act['club_category'], 'club' => '']) ?>"
                                        style="font-size:.65rem;color:var(--mute);text-decoration:none;border:1px solid var(--border);border-radius:99px;padding:.1rem .45rem;"
                                        title="篩選此分類"
                                    >
                                        <?= htmlspecialchars($act['club_category']) ?>
                                    </a>
                                <?php endif; ?>

                                <?php if ($free): ?>
                                    <span class="tag tag-free">免費</span>
                                <?php else: ?>
                                    <span class="tag tag-paid">
                                        <?= htmlspecialchars($act['fee'] ?? '未設定') ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($closed): ?>
                                    <span class="tag tag-closed">已截止</span>
                                <?php elseif ($soon): ?>
                                    <span class="tag tag-soon">即將截止</span>
                                <?php endif; ?>
                            </div>

                            <h2 class="act-title">
                                <?= htmlspecialchars($act['title'] ?? '未命名活動') ?>
                            </h2>

                            <p class="act-desc">
                                <?= htmlspecialchars($act['description'] ?? '') ?>
                            </p>

                            <div class="act-meta">

                                <span class="act-meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="4" width="18" height="18" rx="2"/>
                                        <path d="M16 2v4M8 2v4M3 10h18"/>
                                    </svg>

                                    <?= formatDateTime($act['event_start'] ?? '') ?>

                                    <?php if (!empty($act['event_end'])): ?>
                                        – <?= date('H:i', strtotime($act['event_end'])) ?>
                                    <?php endif; ?>
                                </span>

                                <span class="act-meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0118 0z"/>
                                        <circle cx="12" cy="10" r="3"/>
                                    </svg>

                                    <?= htmlspecialchars($act['location'] ?? '未設定地點') ?>
                                </span>

                            </div>
                        </div>

                        <div class="act-footer">

                            <span class="act-footer-left">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <path d="M12 8v4l3 3"/>
                                </svg>

                                報名截止：<?= formatDate($act['signup_deadline'] ?? '') ?>
                                &nbsp;·&nbsp;
                                發佈於 <?= formatDate($act['created_at'] ?? '') ?>
                            </span>

                            <span class="act-footer-right">

                                <button 
                                    class="bookmark-btn <?= $isFavorited ? 'saved' : '' ?>"
                                    data-type="activity"
                                    data-id="<?= htmlspecialchars($act['id']) ?>"
                                    title="<?= $isFavorited ? '取消收藏' : '收藏' ?>"
                                    onclick="toggleFavorite(this)"
                                >
                                    <svg 
                                        viewBox="0 0 24 24"
                                        fill="<?= $isFavorited ? 'currentColor' : 'none' ?>"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/>
                                    </svg>
                                </button>

                                <a 
                                    href="activity_view.php?id=<?= htmlspecialchars($act['id']) ?>"
                                    class="view-btn <?= $closed ? 'closed' : '' ?>"
                                >
                                    <?= $closed ? '已截止' : '查看詳情' ?>

                                    <?php if (!$closed): ?>
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                            <path d="M5 12h14M12 5l7 7-7 7"/>
                                        </svg>
                                    <?php endif; ?>
                                </a>

                            </span>

                        </div>

                    </article>
                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

</div>

<script>
function toggleFavorite(btn) {
    const itemId = btn.dataset.id;
    const itemType = btn.dataset.type;

    fetch("api/toggle_favorite.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body:
            "item_id=" + encodeURIComponent(itemId) +
            "&item_type=" + encodeURIComponent(itemType)
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            alert(data.message);
            return;
        }

        const icon = btn.querySelector("svg");

        if (data.favorited) {
            btn.classList.add("saved");
            btn.title = "取消收藏";

            if (icon) {
                icon.setAttribute("fill", "currentColor");
            }

            showToast("已加入收藏 🔖");
        } else {
            btn.classList.remove("saved");
            btn.title = "收藏";

            if (icon) {
                icon.setAttribute("fill", "none");
            }

            showToast("已取消收藏");
        }
    })
    .catch(err => {
        console.error(err);
        alert("收藏操作失敗");
    });
}

function showToast(msg) {
    let t = document.getElementById("favorite-toast");

    if (!t) {
        t = document.createElement("div");
        t.id = "favorite-toast";
        t.style.cssText = `
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
        `;

        document.body.appendChild(t);
    }

    t.textContent = msg;
    t.style.opacity = "1";

    clearTimeout(t._timer);

    t._timer = setTimeout(() => {
        t.style.opacity = "0";
    }, 2000);
}
</script>

<?php include 'footer.php'; ?>
</body>
</html>