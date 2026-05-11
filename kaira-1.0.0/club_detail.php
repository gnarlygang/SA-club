<?php
session_start();
require_once "api/db.php";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { $pdo = null; }

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: clubs.php"); exit; }

$club = $tags = $activities = [];
$is_subscribed = false;
$sub_count = 0;

if ($pdo) {
    $stmt = $pdo->prepare("SELECT c.*, u.email FROM clubs c LEFT JOIN users u ON u.user_id=c.user_id WHERE c.id=:id");
    $stmt->execute([':id' => $id]);
    $club = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$club) { header("Location: clubs.php"); exit; }

    $stmt2 = $pdo->prepare("SELECT tag_name FROM club_tags WHERE club_id=:id ORDER BY id");
    $stmt2->execute([':id' => $id]);
    $tags = $stmt2->fetchAll(PDO::FETCH_COLUMN);

    if ($club['user_id']) {
        $stmt3 = $pdo->prepare("SELECT * FROM activities WHERE user_id=:uid ORDER BY created_at DESC LIMIT 6");
        $stmt3->execute([':uid' => $club['user_id']]);
        $activities = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    }

    // 訂閱人數
    $stmtC = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE club_id=?");
    $stmtC->execute([$id]);
    $sub_count = (int)$stmtC->fetchColumn();

    // 是否已訂閱
    if (!empty($_SESSION['user_id'])) {
        $stmtS = $pdo->prepare("SELECT id FROM subscriptions WHERE user_id=? AND club_id=?");
        $stmtS->execute([$_SESSION['user_id'], $id]);
        $is_subscribed = (bool)$stmtS->fetch();
    }
}

$catColors = [
    '學術性社團'    => ['bg'=>'#e8f0fe','color'=>'#1a56db'],
    '休閒聯誼性社團' => ['bg'=>'#fef3c7','color'=>'#92400e'],
    '服務性社團'    => ['bg'=>'#d1fae5','color'=>'#065f46'],
    '體能性社團'    => ['bg'=>'#fee2e2','color'=>'#991b1b'],
    '藝術性社團'    => ['bg'=>'#ede9fe','color'=>'#5b21b6'],
    '音樂性社團'    => ['bg'=>'#fce7f3','color'=>'#9d174d'],
];
$cc = $catColors[$club['category']] ?? ['bg'=>'#f0f0f0','color'=>'#666'];

// 分離進行中 & 已截止
$active_acts = [];
$closed_acts = [];
foreach ($activities as $a) {
    if (strtotime($a['signup_deadline']) < time()) $closed_acts[] = $a;
    else $active_acts[] = $a;
}

require_once "header.php";
?>
<style>
:root {
    --ink: #1a1a2e; --soft: #4a4a6a; --mute: #8888aa;
    --paper: #f5f5f2; --white: #fff; --accent: #c8502a;
    --border: #e4e4de; --radius: 12px;
    --serif: 'Noto Serif TC', serif; --sans: 'Noto Sans TC', sans-serif;
}
*, *::before, *::after { box-sizing: border-box; }
body { font-family: var(--sans); background: var(--paper); color: var(--ink); margin: 0; }

/* ── Hero 橫幅圖 ── */
.club-banner {
    width: 100%; height: 300px; object-fit: cover; display: block;
    background: linear-gradient(135deg, #1a1a2e, #2d3a5e);
}
.club-banner-placeholder {
    width: 100%; height: 300px;
    background: linear-gradient(135deg, #1a1a2e 0%, #2d3a5e 50%, #1a1a2e 100%);
    display: flex; align-items: center; justify-content: center;
    color: rgba(255,255,255,.15); font-size: 6rem;
}

/* ── 麵包屑 ── */
.breadcrumb-bar {
    background: var(--white); border-bottom: 1px solid var(--border);
    padding: .55rem 0; font-size: .78rem; color: var(--mute);
}
.breadcrumb-bar .inner { max-width: 1100px; margin: 0 auto; padding: 0 1.5rem; display: flex; align-items: center; gap: .4rem; flex-wrap: wrap; }
.breadcrumb-bar a { color: var(--soft); text-decoration: none; }
.breadcrumb-bar a:hover { color: var(--accent); }
.breadcrumb-bar .sep { color: var(--border); }

/* ── 主體左右版型 ── */
.detail-wrap {
    max-width: 1100px; margin: 1.75rem auto 4rem;
    padding: 0 1.5rem;
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 1.5rem;
    align-items: start;
}

/* ═══════════ LEFT ═══════════ */
.detail-left {}

/* 社團頭部卡 */
.club-header-card {
    background: var(--white); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 1.75rem 2rem;
    margin-bottom: 1.25rem;
    box-shadow: 0 2px 12px rgba(0,0,0,.05);
}
.club-header-top {
    display: flex; align-items: flex-start;
    justify-content: space-between; flex-wrap: wrap; gap: 1rem;
    margin-bottom: .85rem;
}
.cat-badge {
    display: inline-block; padding: .25rem .75rem;
    border-radius: 99px; font-size: .72rem; font-weight: 600;
    margin-bottom: .5rem;
}
.club-name-big {
    font-family: var(--serif);
    font-size: clamp(1.4rem, 3vw, 1.9rem);
    font-weight: 800; color: var(--ink);
    line-height: 1.2; margin: 0;
}

/* 訂閱區塊 */
.sub-area { display: flex; flex-direction: column; align-items: flex-end; gap: .35rem; flex-shrink: 0; }
.sub-count { font-size: .72rem; color: var(--mute); text-align: right; }
.sub-count strong { color: var(--ink); font-size: .88rem; }

.sub-btn {
    display: inline-flex; align-items: center; gap: .4rem;
    font-size: .85rem; font-weight: 600;
    padding: .5rem 1.15rem; border-radius: 8px;
    border: 2px solid var(--ink); background: transparent; color: var(--ink);
    cursor: pointer; transition: all .18s; white-space: nowrap;
}
.sub-btn:hover { background: var(--ink); color: #fff; }
.sub-btn.subscribed { background: var(--ink); color: #fff; }
.sub-btn.subscribed:hover { background: #c0392b; border-color: #c0392b; }

/* 標籤 */
.tag-wrap { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: .75rem; }
.tag-item {
    padding: .2rem .65rem; border-radius: 99px;
    font-size: .72rem; background: var(--paper);
    border: 1px solid var(--border); color: var(--soft);
}

/* 統計數字列 */
.stat-row {
    display: flex; gap: 1.5rem; flex-wrap: wrap;
    padding: 1rem 0; border-top: 1px solid var(--border); margin-top: 1rem;
}
.stat-item { text-align: center; }
.stat-num { font-size: 1.4rem; font-weight: 700; color: var(--ink); line-height: 1; }
.stat-label { font-size: .68rem; color: var(--mute); margin-top: .2rem; }

/* 介紹卡 */
.section-card {
    background: var(--white); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 1.5rem 2rem;
    margin-bottom: 1.25rem;
    box-shadow: 0 2px 12px rgba(0,0,0,.05);
}
.section-label {
    font-size: .65rem; font-weight: 700; letter-spacing: .1em;
    text-transform: uppercase; color: var(--mute);
    margin-bottom: .75rem; display: flex; align-items: center; gap: .4rem;
}
.section-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }
.desc-text { font-size: .9rem; color: var(--soft); line-height: 1.85; white-space: pre-wrap; }

/* 近期活動 */
.act-item {
    display: block; padding: .85rem 1rem;
    border-radius: 8px; border: 1px solid var(--border);
    background: var(--paper); margin-bottom: .6rem;
    text-decoration: none; color: inherit; transition: all .18s;
}
.act-item:hover { background: #f0ebe8; border-color: #ddd5d0; }
.act-item.is-closed { opacity: .55; filter: grayscale(.3); }
.act-item.is-closed:hover { opacity: .75; }
.act-item-header { display: flex; align-items: center; gap: .45rem; flex-wrap: wrap; margin-bottom: .3rem; }
.act-title { font-weight: 600; font-size: .88rem; color: var(--ink); }
.act-badge { font-size: .65rem; padding: .12rem .5rem; border-radius: 99px; font-weight: 600; }
.act-badge.free   { background: #e6f4ec; color: #1e7a47; }
.act-badge.paid   { background: #fff4e5; color: #b06000; }
.act-badge.closed { background: #ebebeb; color: #999; }
.act-meta-row { display: flex; flex-wrap: wrap; gap: .35rem .7rem; font-size: .72rem; color: var(--mute); }
.act-meta-row span { display: flex; align-items: center; gap: .2rem; }
.act-meta-row svg { width: 11px; height: 11px; flex-shrink: 0; }

.closed-toggle {
    display: flex; align-items: center; gap: .4rem;
    font-size: .78rem; color: var(--mute); cursor: pointer;
    background: none; border: 1px dashed var(--border);
    border-radius: 8px; padding: .5rem .85rem; width: 100%; margin-top: .2rem;
    transition: all .18s;
}
.closed-toggle:hover { color: var(--accent); border-color: var(--accent); background: #fff8f6; }

/* ═══════════ RIGHT SIDEBAR ═══════════ */
.detail-right {}

.info-card {
    background: var(--white); border: 1px solid var(--border);
    border-radius: var(--radius); overflow: hidden;
    box-shadow: 0 2px 12px rgba(0,0,0,.05);
    position: sticky; top: 80px;
    margin-bottom: 1.25rem;
}
.info-card-header {
    background: var(--ink); color: #fff;
    padding: .85rem 1.2rem; font-size: .75rem; font-weight: 600; letter-spacing: .06em;
}
.info-row {
    display: flex; align-items: flex-start; gap: .75rem;
    padding: .85rem 1.2rem; border-bottom: 1px solid var(--border);
}
.info-row:last-child { border-bottom: none; }
.info-icon {
    width: 34px; height: 34px; border-radius: 8px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: .95rem;
}
.info-icon.blue   { background: #e8f0fe; color: #1a56db; }
.info-icon.green  { background: #e8f5ee; color: #2d8a5e; }
.info-icon.orange { background: #fff3e0; color: #e07a10; }
.info-icon.purple { background: #f3effe; color: #7c3aed; }
.info-icon.red    { background: #fff0ec; color: var(--accent); }
.info-label { font-size: .68rem; color: var(--mute); margin-bottom: .15rem; }
.info-value { font-size: .85rem; color: var(--ink); font-weight: 500; word-break: break-all; }
.info-value a { color: var(--accent); text-decoration: none; }
.info-value a:hover { text-decoration: underline; }

/* 訂閱人數大字 */
.sub-count-big {
    text-align: center; padding: 1.2rem 1.2rem .6rem;
    border-bottom: 1px solid var(--border);
}
.sub-count-num { font-size: 2.2rem; font-weight: 800; color: var(--ink); line-height: 1; }
.sub-count-unit { font-size: .72rem; color: var(--mute); margin-top: .2rem; }

/* 返回按鈕 */
.back-btn {
    display: flex; align-items: center; gap: .4rem; justify-content: center;
    padding: .65rem; font-size: .82rem; color: var(--soft); text-decoration: none;
    border-top: 1px solid var(--border); transition: color .18s;
}
.back-btn:hover { color: var(--accent); }

/* Share */
.share-card {
    background: var(--white); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 1rem 1.2rem;
    box-shadow: 0 2px 12px rgba(0,0,0,.05);
}
.share-title { font-size: .7rem; color: var(--mute); font-weight: 700; letter-spacing: .08em; text-transform: uppercase; margin-bottom: .65rem; }
.share-btns { display: flex; gap: .5rem; }
.share-btn {
    flex: 1; display: flex; align-items: center; justify-content: center; gap: .35rem;
    padding: .45rem; border-radius: 7px; font-size: .72rem; font-weight: 600;
    text-decoration: none; border: 1px solid var(--border); color: var(--soft);
    background: var(--paper); cursor: pointer; transition: all .18s;
}
.share-btn:hover { border-color: var(--ink); color: var(--ink); background: #fff; }
.share-btn svg { width: 14px; height: 14px; }

/* toast */
#sub-toast {
    position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9999;
    background: var(--ink); color: #fff; padding: .55rem 1.1rem;
    border-radius: 8px; font-size: .8rem; font-weight: 500;
    box-shadow: 0 4px 16px rgba(0,0,0,.2);
    transition: opacity .3s; opacity: 0; pointer-events: none;
}

@media (max-width: 820px) {
    .detail-wrap { grid-template-columns: 1fr; }
    .info-card { position: static; }
    .club-banner, .club-banner-placeholder { height: 220px; }
}
</style>

<!-- Banner -->
<?php if (!empty($club['image'])): ?>
    <img class="club-banner" src="<?= htmlspecialchars($club['image']) ?>" alt="<?= htmlspecialchars($club['name']) ?>">
<?php else: ?>
    <div class="club-banner-placeholder">
        <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
        </svg>
    </div>
<?php endif; ?>

<!-- 麵包屑 -->
<div class="breadcrumb-bar">
    <div class="inner">
        <a href="clubs.php">社團介紹</a>
        <span class="sep">›</span>
        <a href="javascript:history.back()">← 回上一頁</a>
    </div>
</div>

<!-- 主體 -->
<div class="detail-wrap">

    <!-- ═══ LEFT ═══ -->
    <div class="detail-left">

        <!-- 社團頭部 -->
        <div class="club-header-card">
            <div class="club-header-top">
                <div>
                    <span class="cat-badge" style="background:<?= $cc['bg'] ?>;color:<?= $cc['color'] ?>">
                        <?= htmlspecialchars($club['category']) ?>
                    </span>
                    <h1 class="club-name-big"><?= htmlspecialchars($club['name']) ?></h1>
                </div>

                <?php if (!empty($_SESSION['user_id'])): ?>
                <div class="sub-area">
                    <div class="sub-count">
                        訂閱人數 <strong><?= number_format($sub_count) ?></strong> 人
                    </div>
                    <button id="subBtn" class="sub-btn <?= $is_subscribed ? 'subscribed' : '' ?>"
                            data-club-id="<?= $id ?>" onclick="toggleSub(this)">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="<?= $is_subscribed ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2">
                            <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path d="M13.73 21a2 2 0 01-3.46 0"/>
                        </svg>
                        <?= $is_subscribed ? '已訂閱' : '訂閱此社團' ?>
                    </button>
                </div>
                <?php else: ?>
                <div class="sub-area">
                    <div class="sub-count">訂閱人數 <strong><?= number_format($sub_count) ?></strong> 人</div>
                    <a href="login.php" class="sub-btn" style="text-decoration:none">登入後訂閱</a>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($tags)): ?>
            <div class="tag-wrap">
                <?php foreach ($tags as $tag): ?>
                <span class="tag-item"># <?= htmlspecialchars($tag) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- 統計 -->
            <div class="stat-row">
                <div class="stat-item">
                    <div class="stat-num"><?= count($activities) ?></div>
                    <div class="stat-label">活動總數</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num"><?= count($active_acts) ?></div>
                    <div class="stat-label">進行中</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num"><?= number_format($sub_count) ?></div>
                    <div class="stat-label">訂閱人數</div>
                </div>
            </div>
        </div>

        <!-- 社團介紹 -->
        <?php if (!empty($club['description'])): ?>
        <div class="section-card">
            <div class="section-label">社團介紹</div>
            <p class="desc-text"><?= nl2br(htmlspecialchars($club['description'])) ?></p>
        </div>
        <?php endif; ?>

        <!-- 近期活動 -->
        <?php if (!empty($activities)): ?>
        <div class="section-card">
            <div class="section-label">近期活動</div>

            <?php if (empty($active_acts) && empty($closed_acts)): ?>
                <p style="color:var(--mute);font-size:.85rem;">目前沒有活動</p>
            <?php endif; ?>

            <!-- 進行中 -->
            <?php foreach ($active_acts as $act):
                $isFree = str_contains($act['fee'],'免費') || $act['fee']==='0';
            ?>
            <a href="activity_view.php?id=<?= $act['id'] ?>" class="act-item">
                <div class="act-item-header">
                    <span class="act-title"><?= htmlspecialchars($act['title']) ?></span>
                    <span class="act-badge <?= $isFree ? 'free' : 'paid' ?>"><?= $isFree ? '免費' : htmlspecialchars($act['fee']) ?></span>
                </div>
                <div class="act-meta-row">
                    <span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        <?= date('Y/m/d H:i', strtotime($act['event_start'])) ?>
                    </span>
                    <span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        <?= htmlspecialchars($act['location']) ?>
                    </span>
                    <span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
                        報名截止 <?= date('Y/m/d', strtotime($act['signup_deadline'])) ?>
                    </span>
                </div>
            </a>
            <?php endforeach; ?>

            <!-- 已截止（折疊） -->
            <?php if (!empty($closed_acts)): ?>
            <button class="closed-toggle" onclick="toggleClosed(this)">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" id="closedArrow"><path d="M6 9l6 6 6-6"/></svg>
                顯示已截止活動（<?= count($closed_acts) ?> 項）
            </button>
            <div id="closedActs" style="display:none; margin-top:.6rem;">
                <?php foreach ($closed_acts as $act):
                    $isFree = str_contains($act['fee'],'免費') || $act['fee']==='0';
                ?>
                <a href="activity_view.php?id=<?= $act['id'] ?>" class="act-item is-closed">
                    <div class="act-item-header">
                        <span class="act-title"><?= htmlspecialchars($act['title']) ?></span>
                        <span class="act-badge <?= $isFree ? 'free' : 'paid' ?>"><?= $isFree ? '免費' : htmlspecialchars($act['fee']) ?></span>
                        <span class="act-badge closed">已截止</span>
                    </div>
                    <div class="act-meta-row">
                        <span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                            <?= date('Y/m/d', strtotime($act['event_start'])) ?>
                        </span>
                        <span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <?= htmlspecialchars($act['location']) ?>
                        </span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div>
        <?php endif; ?>

    </div>

    <!-- ═══ RIGHT SIDEBAR ═══ -->
    <div class="detail-right">

        <!-- 聯絡資訊卡 -->
        <div class="info-card">
            <div class="info-card-header">社團資訊</div>

            <!-- 訂閱人數 -->
            <div class="sub-count-big">
                <div class="sub-count-num"><?= number_format($sub_count) ?></div>
                <div class="sub-count-unit">位同學已訂閱</div>
            </div>

            <!-- 分類 -->
            <div class="info-row">
                <div class="info-icon purple">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                </div>
                <div>
                    <div class="info-label">社團類型</div>
                    <div class="info-value">
                        <a href="clubs.php?cat=<?= urlencode($club['category']) ?>" style="color:var(--accent)">
                            <?= htmlspecialchars($club['category']) ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- 聯絡信箱 -->
            <?php if (!empty($club['email'])): ?>
            <div class="info-row">
                <div class="info-icon orange">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </div>
                <div>
                    <div class="info-label">聯絡信箱</div>
                    <div class="info-value"><a href="mailto:<?= htmlspecialchars($club['email']) ?>"><?= htmlspecialchars($club['email']) ?></a></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- 活動數量 -->
            <div class="info-row">
                <div class="info-icon blue">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                </div>
                <div>
                    <div class="info-label">活動數量</div>
                    <div class="info-value">共 <?= count($activities) ?> 項活動（進行中 <?= count($active_acts) ?> 項）</div>
                </div>
            </div>

            <!-- 標籤 -->
            <?php if (!empty($tags)): ?>
            <div class="info-row">
                <div class="info-icon green">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/></svg>
                </div>
                <div>
                    <div class="info-label">關鍵字</div>
                    <div class="info-value" style="display:flex;flex-wrap:wrap;gap:.3rem;margin-top:.2rem;">
                        <?php foreach ($tags as $tag): ?>
                        <span style="font-size:.7rem;padding:.1rem .45rem;border-radius:99px;background:var(--paper);border:1px solid var(--border);color:var(--soft)"><?= htmlspecialchars($tag) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- 返回 -->
            <a href="clubs.php?cat=<?= urlencode($club['category']) ?>" class="back-btn">
                ← 返回<?= htmlspecialchars($club['category']) ?>列表
            </a>
        </div>

        <!-- 分享 -->
        <div class="share-card">
            <div class="share-title">分享這個社團</div>
            <div class="share-btns">
                <button class="share-btn" onclick="copyLink()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
                    複製連結
                </button>
                <a class="share-btn" href="https://line.me/R/msg/text/?<?= urlencode($club['name'].' '.($_SERVER['REQUEST_URI'] ?? '')) ?>" target="_blank">
                    <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M12 2C6.48 2 2 6.1 2 11.1c0 3.5 2.2 6.5 5.5 8.2-.2.8-.8 2.9-.9 3.3-.1.5.2.5.4.4.3-.1 3.8-2.5 5.3-3.5.4.1.8.1 1.2.1 5.5 0 10-4.1 10-9.1S17.5 2 12 2z"/></svg>
                    Line
                </a>
            </div>
        </div>

    </div>
</div>

<div id="sub-toast"></div>

<script>
function toggleSub(btn) {
    fetch("api/toggle_subscription.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "club_id=" + encodeURIComponent(btn.dataset.clubId)
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) { showToast(data.message); return; }
        const icon = btn.querySelector('svg');
        if (data.subscribed) {
            btn.classList.add("subscribed");
            btn.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg> 已訂閱';
            // 更新人數
            updateSubCount(1);
        } else {
            btn.classList.remove("subscribed");
            btn.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg> 訂閱此社團';
            updateSubCount(-1);
        }
        showToast(data.message);
    })
    .catch(() => showToast("操作失敗，請稍後再試"));
}

function updateSubCount(delta) {
    // 更新右側大數字
    const big = document.querySelector('.sub-count-num');
    if (big) big.textContent = Math.max(0, parseInt(big.textContent.replace(/,/g,'')) + delta).toLocaleString();
    // 更新 sub-area 小字
    const small = document.querySelector('.sub-area .sub-count strong');
    if (small) small.textContent = Math.max(0, parseInt(small.textContent.replace(/,/g,'')) + delta).toLocaleString();
    // 更新 stat-row
    const stats = document.querySelectorAll('.stat-item .stat-num');
    if (stats[2]) stats[2].textContent = Math.max(0, parseInt(stats[2].textContent.replace(/,/g,'')) + delta).toLocaleString();
}

function toggleClosed(btn) {
    const div = document.getElementById('closedActs');
    const arrow = document.getElementById('closedArrow');
    const isOpen = div.style.display !== 'none';
    div.style.display = isOpen ? 'none' : 'block';
    arrow.style.transform = isOpen ? '' : 'rotate(180deg)';
    btn.childNodes[1].textContent = isOpen
        ? ' 顯示已截止活動（<?= count($closed_acts) ?> 項）'
        : ' 隱藏已截止活動（<?= count($closed_acts) ?> 項）';
}

function copyLink() {
    navigator.clipboard.writeText(window.location.href).then(() => showToast('連結已複製 ✓'));
}

function showToast(msg) {
    const t = document.getElementById("sub-toast");
    t.textContent = msg; t.style.opacity = "1";
    clearTimeout(t._t);
    t._t = setTimeout(() => { t.style.opacity = "0"; }, 2200);
}
</script>

<?php include 'footer.php'; ?>
</body>
</html>