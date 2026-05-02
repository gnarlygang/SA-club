<?php
session_start();

require_once "api/db.php";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $pdo = null;
}

$sort_by     = $_GET['sort_by'] ?? 'created_at';
$filter_club = $_GET['club']    ?? '';
$filter_fee  = $_GET['fee']     ?? '';
$search      = $_GET['search']  ?? '';

$allowed_sort = ['created_at', 'event_start', 'signup_deadline'];
if (!in_array($sort_by, $allowed_sort)) $sort_by = 'created_at';

$activities = [];
$clubs_list = [];

if ($pdo) {
    $clubs_list = $pdo->query("SELECT DISTINCT organizer FROM activities ORDER BY organizer")->fetchAll(PDO::FETCH_COLUMN);

    $sql = "SELECT a.*, c.name AS club_name, c.image AS club_image
            FROM activities a
            LEFT JOIN clubs c ON c.user_id = a.user_id
            WHERE 1=1";
    $params = [];

    if ($filter_club !== '' && $filter_club !== '__subscribed__') {
        $sql .= " AND a.organizer = :club";
        $params[':club'] = $filter_club;
    }
    // TODO: 訂閱功能完成後，在這裡加 WHERE a.user_id IN (訂閱清單)
    if ($filter_fee === 'free') {
        $sql .= " AND (a.fee = '免費' OR a.fee LIKE '%免費%')";
    } elseif ($filter_fee === 'paid') {
        $sql .= " AND a.fee != '免費' AND a.fee NOT LIKE '%免費%'";
    }
    if ($search !== '') {
        $sql .= " AND (a.title LIKE :search OR a.description LIKE :search2)";
        $params[':search']  = "%$search%";
        $params[':search2'] = "%$search%";
    }

    $sql .= " ORDER BY a.$sort_by DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $clubs_list = ['輔大國樂社', '輔大熱舞社'];
    $activities = [
        ['id'=>1,'title'=>'春季音樂成果發表會','description'=>'輔大國樂社每年春季舉辦的成果發表會。','event_start'=>'2026-05-15 19:00:00','event_end'=>'2026-05-15 21:00:00','location'=>'輔仁大學野聲樓 B1 表演廳','organizer'=>'輔大國樂社','fee'=>'免費入場','target'=>'全校師生及校外人士','signup_deadline'=>'2026-05-10','created_at'=>'2026-04-22 11:14:41','club_image'=>'https://images.unsplash.com/photo-1507838153414-b4b713384a76?auto=format&fit=crop&w=800&q=80'],
        ['id'=>2,'title'=>'國樂入門體驗工作坊','description'=>'提供二胡、琵琶、古箏等樂器體驗。','event_start'=>'2026-05-22 14:00:00','event_end'=>'2026-05-22 17:00:00','location'=>'輔仁大學藝文中心 302 室','organizer'=>'輔大國樂社','fee'=>'NT$100','target'=>'全校學生','signup_deadline'=>'2026-05-18','created_at'=>'2026-04-22 11:14:41','club_image'=>'https://images.unsplash.com/photo-1507838153414-b4b713384a76?auto=format&fit=crop&w=800&q=80'],
    ];
}

function isFree(string $fee): bool {
    return str_contains($fee, '免費') || strtolower($fee) === 'free' || $fee === '0';
}
function formatDate(string $dt): string { return date('Y/m/d', strtotime($dt)); }
function formatDateTime(string $dt): string { return date('Y/m/d H:i', strtotime($dt)); }
function isDeadlineSoon(string $d): bool { $t = strtotime($d); return ($t - time()) <= 7*86400 && $t >= time(); }
function isDeadlinePassed(string $d): bool { return strtotime($d) < time(); }

require_once "header.php";
?>



<!-- Hero -->
<div class="act-hero">
    <h1>所有活動</h1>
    <p>瀏覽各社團最新發佈的活動資訊</p>
    <span class="count-badge"><?= count($activities) ?> 項活動</span>
</div>

<!-- Filter -->
<div class="filter-wrap">
    <form class="filter-inner" method="GET" action="">
        <div class="search-box">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" name="search" placeholder="搜尋活動名稱…" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="filter-divider"></div>
        <span class="filter-label">社團</span>
        <select name="club" class="filter-select" onchange="this.form.submit()">
            <option value="">全部社團</option>
            <option value="__subscribed__" <?= $filter_club === '__subscribed__' ? 'selected' : '' ?> class="subscribed-opt">
                ⭐ 已訂閱社團
            </option>
            <?php foreach ($clubs_list as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $filter_club === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="fee" class="filter-select" onchange="this.form.submit()">
            <option value="">全部費用</option>
            <option value="free" <?= $filter_fee === 'free' ? 'selected' : '' ?>>免費</option>
            <option value="paid" <?= $filter_fee === 'paid' ? 'selected' : '' ?>>需收費</option>
        </select>
        <div class="filter-divider"></div>
        <span class="filter-label">排序</span>
        <div class="sort-pills">
            <?php
            $sorts = ['created_at'=>'依發佈時間','event_start'=>'依活動時間','signup_deadline'=>'依報名截止'];
            foreach ($sorts as $val => $label):
                $active = $sort_by === $val ? ' active' : '';
                $qs = http_build_query(array_merge($_GET, ['sort_by' => $val]));
            ?>
            <a href="?<?= $qs ?>" class="sort-pill<?= $active ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
        <input type="hidden" name="sort_by" value="<?= htmlspecialchars($sort_by) ?>">
        <?php if ($search || $filter_club || $filter_fee): ?>
            <a href="activities.php" style="font-size:.8rem;color:var(--accent);text-decoration:none;white-space:nowrap;">✕ 清除篩選</a>
        <?php endif; ?>
    </form>
</div>

<!-- Main -->
<main class="act-main">
    <p class="result-meta">
        共找到 <strong><?= count($activities) ?></strong> 項活動
        <?php if ($filter_club === '__subscribed__'): ?> · 篩選：<strong>⭐ 已訂閱社團</strong>（訂閱功能建置中）
        <?php elseif ($filter_club): ?> · 社團：<strong><?= htmlspecialchars($filter_club) ?></strong><?php endif; ?>
        <?php if ($filter_fee === 'free'): ?> · <strong>免費</strong><?php elseif ($filter_fee === 'paid'): ?> · <strong>需收費</strong><?php endif; ?>
        <?php if ($search): ?> · 搜尋：<strong>"<?= htmlspecialchars($search) ?>"</strong><?php endif; ?>
    </p>

    <?php if (empty($activities)): ?>
    <div class="act-empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
        <h3>找不到符合條件的活動</h3>
        <p>請調整篩選條件，或 <a href="activities.php" style="color:var(--accent)">查看全部活動</a></p>
    </div>

    <?php else: ?>
    <div class="act-cards">
        <?php foreach ($activities as $i => $act):
            $free   = isFree($act['fee']);
            $soon   = isDeadlineSoon($act['signup_deadline']);
            $closed = isDeadlinePassed($act['signup_deadline']);
            $img = (!empty($act['club_image']))
                ? $act['club_image']
                : 'https://images.unsplash.com/photo-1507838153414-b4b713384a76?auto=format&fit=crop&w=800&q=80';
        ?>
        <article class="act-card" style="animation-delay:<?= $i * 0.06 ?>s">
            <div class="act-card-body">
                <div class="card-organizer">
                    <img class="org-avatar" src="<?= htmlspecialchars($img) ?>" alt="">
                    <span class="org-name"><?= htmlspecialchars($act['organizer']) ?></span>
                    <?php if ($free): ?>
                        <span class="tag-badge tag-free">免費</span>
                    <?php else: ?>
                        <span class="tag-badge tag-paid"><?= htmlspecialchars($act['fee']) ?></span>
                    <?php endif; ?>
                    <?php if ($closed): ?>
                        <span class="tag-badge tag-closed">已截止</span>
                    <?php elseif ($soon): ?>
                        <span class="tag-badge tag-soon">即將截止</span>
                    <?php endif; ?>
                </div>

                <h2 class="card-title"><?= htmlspecialchars($act['title']) ?></h2>
                <p class="card-desc"><?= htmlspecialchars($act['description']) ?></p>

                <div class="card-meta">
                    <span class="meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        <?= formatDateTime($act['event_start']) ?>
                        <?php if (!empty($act['event_end'])): ?> – <?= date('H:i', strtotime($act['event_end'])) ?><?php endif; ?>
                    </span>
                    <span class="meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        <?= htmlspecialchars($act['location']) ?>
                    </span>
                    <span class="meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                        <?= htmlspecialchars($act['target']) ?>
                    </span>
                </div>
            </div>

            <div class="card-img-wrap">
                <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($act['title']) ?>">
            </div>

            <div class="act-card-footer">
                <span class="footer-left">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
                    報名截止：<?= formatDate($act['signup_deadline']) ?>
                    &nbsp;·&nbsp; 發佈於 <?= formatDate($act['created_at']) ?>
                </span>
                <span class="footer-right">
                    <!-- 收藏按鈕 -->
                    <button class="bookmark-btn" data-id="<?= $act['id'] ?>" title="收藏活動" onclick="toggleBookmark(this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/>
                        </svg>
                    </button>
                    <!-- 查看詳情 -->
                    <a href="activity_view.php?id=<?= $act['id'] ?>" class="signup-btn <?= $closed ? 'closed' : '' ?>">
                        <?= $closed ? '已截止' : '查看詳情' ?>
                        <?php if (!$closed): ?>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        <?php endif; ?>
                    </a>
                </span>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<script>
// ── 收藏功能（localStorage，之後可換成 API）────────────────────
const BOOKMARK_KEY = 'fju_bookmarked_activities';

function getBookmarks() {
    return JSON.parse(localStorage.getItem(BOOKMARK_KEY) || '[]');
}
function saveBookmarks(arr) {
    localStorage.setItem(BOOKMARK_KEY, JSON.stringify(arr));
}

// 頁面載入時，把已收藏的按鈕標記為 saved
document.addEventListener('DOMContentLoaded', () => {
    const saved = getBookmarks();
    document.querySelectorAll('.bookmark-btn').forEach(btn => {
        if (saved.includes(Number(btn.dataset.id))) {
            btn.classList.add('saved');
            btn.title = '取消收藏';
            btn.querySelector('svg').setAttribute('fill', 'currentColor');
        }
    });
});

function toggleBookmark(btn) {
    const id   = Number(btn.dataset.id);
    let saved  = getBookmarks();
    const idx  = saved.indexOf(id);

    if (idx === -1) {
        saved.push(id);
        btn.classList.add('saved');
        btn.title = '取消收藏';
        btn.querySelector('svg').setAttribute('fill', 'currentColor');
        showToast('已加入收藏 🔖');
    } else {
        saved.splice(idx, 1);
        btn.classList.remove('saved');
        btn.title = '收藏活動';
        btn.querySelector('svg').setAttribute('fill', 'none');
        showToast('已移除收藏');
    }
    saveBookmarks(saved);
}

// ── Toast 提示 ────────────────────────────────────────────────
function showToast(msg) {
    let toast = document.getElementById('bk-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'bk-toast';
        toast.style.cssText = `
            position:fixed; bottom:1.5rem; right:1.5rem; z-index:9999;
            background:#1a1a2e; color:#fff; padding:.6rem 1.2rem;
            border-radius:8px; font-size:.82rem; font-weight:500;
            box-shadow:0 4px 16px rgba(0,0,0,.2);
            transition:opacity .3s; opacity:0; pointer-events:none;
        `;
        document.body.appendChild(toast);
    }
    toast.textContent = msg;
    toast.style.opacity = '1';
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => { toast.style.opacity = '0'; }, 2000);
}
</script>
<?php include 'footer.php'; ?>
</body>
</html>