<?php
session_start();
require_once "api/db.php";
require_once "header.php";

if (!isset($_SESSION['user_id'])) {
  echo "<script>alert('請先登入'); location.href='login.php';</script>";
  exit;
}

$user_id = $_SESSION['user_id'];

$club_id = $_GET['club_id'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'created';
$order = $_GET['order'] ?? 'desc';

/* 排序 */
if ($sort_by === 'event') {
  if ($order === 'desc') {
    // 活動時間：近到遠
    $orderBy = "
      CASE WHEN a.event_start >= NOW() THEN 0 ELSE 1 END ASC,
      CASE WHEN a.event_start >= NOW() THEN a.event_start END ASC,
      CASE WHEN a.event_start < NOW() THEN a.event_start END DESC
    ";
  } else {
    // 活動時間：遠到近
    $orderBy = "
      CASE WHEN a.event_start >= NOW() THEN 0 ELSE 1 END ASC,
      CASE WHEN a.event_start >= NOW() THEN a.event_start END DESC,
      CASE WHEN a.event_start < NOW() THEN a.event_start END DESC
    ";
  }
} else {
  if ($order === 'desc') {
    // 發布日期：近到遠
    $orderBy = "a.created_at DESC";
  } else {
    // 發布日期：遠到近
    $orderBy = "a.created_at ASC";
  }
}

/* 所有已訂閱社團 */
$stmt = $pdo->prepare("
  SELECT c.*
  FROM subscriptions s
  JOIN clubs c ON s.club_id = c.id
  WHERE s.user_id = ?
  ORDER BY c.category ASC, c.name ASC
");
$stmt->execute([$user_id]);
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* 分類群組 */
$groupedClubs = [];
foreach ($clubs as $club) {
  $cat = $club['category'] ?? '其他';
  if ($cat === '') $cat = '其他';
  $groupedClubs[$cat][] = $club;
}

/* 篩選單一社團 */
$where = "";
$params = [$user_id];

if ($club_id !== '') {
  $where .= " AND c.id = ?";
  $params[] = $club_id;
}

/* 右邊活動 */
$stmt = $pdo->prepare("
  SELECT 
    a.*,
    c.id AS club_id,
    c.name AS club_name,
    c.category AS club_category,
    c.image AS club_image
  FROM subscriptions s
  JOIN clubs c ON s.club_id = c.id
  JOIN activities a ON a.user_id = c.user_id
  WHERE s.user_id = ?
  $where
  ORDER BY $orderBy
");
$stmt->execute($params);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

function buildUrl($club_id, $sort_by, $order) {
  $u = "?tab=subscriptions&sort_by=" . urlencode($sort_by) . "&order=" . urlencode($order);
  if ($club_id !== '') {
    $u .= "&club_id=" . urlencode($club_id);
  }
  return $u;
}
?>

<style>
html, body {
  margin: 0;
  padding: 0;
  overflow-x: hidden;
}

* {
  box-sizing: border-box;
}

.sub-page {
  display: flex;
  width: 100%;
  min-height: calc(100vh - 80px);
  background: #eef3f7;
}

/* 左邊 */
.sub-sidebar {
  width: 310px;
  flex: 0 0 310px;
  background: #2f4358;
  color: white;
  padding: 28px 18px;
  overflow-y: auto;
}

.sub-sidebar h3 {
  font-size: 21px;
  font-weight: 800;
  margin: 16px 0 14px;
  color: white;
}

.category-box {
  margin-bottom: 8px;
}

.category-title {
  width: 100%;
  border: 0;
  background: rgba(255,255,255,0.12);
  color: white;
  padding: 12px 14px;
  border-radius: 12px;
  font-weight: 800;
  text-align: left;
  cursor: pointer;
  margin-bottom: 6px;
}

.category-title:hover {
  background: rgba(255,255,255,0.2);
}

.category-clubs {
  display: none;
  padding-left: 8px;
  margin-bottom: 10px;
}

.category-box.open .category-clubs {
  display: block;
}

.side-link {
  display: block;
  padding: 12px 14px;
  border-radius: 12px;
  color: white;
  text-decoration: none;
  margin-bottom: 8px;
  font-weight: 700;
  transition: 0.2s;
}

.side-link:hover,
.side-link.active {
  background: rgba(255,255,255,0.18);
  color: white;
}

.side-club {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px;
  border-radius: 14px;
  color: white;
  text-decoration: none;
  margin-bottom: 8px;
  transition: 0.2s;
}

.side-club:hover,
.side-club.active {
  background: rgba(255,255,255,0.18);
  color: white;
}

.club-avatar {
  width: 44px;
  height: 44px;
  flex: 0 0 44px;
  border-radius: 50%;
  background: #24384d;
  overflow: hidden;
}

.club-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.club-text strong {
  display: block;
  font-size: 15px;
  color: white;
}

.club-text p {
  margin: 4px 0 0;
  font-size: 13px;
  color: #d9e4ef;
}

.sidebar-divider {
  height: 1px;
  background: rgba(255,255,255,0.18);
  margin: 22px 0;
}

/* 右邊 */
.sub-main {
  flex: 1;
  min-width: 0;
  padding: 38px 42px;
  background: #f3f7fa;
}

.sub-main h2 {
  font-size: 28px;
  font-weight: 800;
  margin-bottom: 18px;
  color: #10263b;
}

.sort-box {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 26px;
}

.sort-box a {
  display: inline-block;
  padding: 9px 16px;
  border-radius: 999px;
  background: white;
  color: #2f4358;
  text-decoration: none;
  font-weight: 700;
  font-size: 14px;
  box-shadow: 0 3px 10px rgba(47,67,88,0.06);
}

.sort-box a.active {
  background: #2f4358;
  color: white;
}

.activity-list {
  width: 100%;
  max-width: 1050px;
}

.activity-card {
  display: flex;
  gap: 18px;
  background: white;
  border-radius: 18px;
  padding: 20px;
  margin-bottom: 18px;
  box-shadow: 0 4px 14px rgba(47,67,88,0.08);
  transition: 0.2s;
}

.activity-card.expired {
  opacity: 0.45;
  filter: grayscale(70%);
}

.activity-img {
  width: 165px;
  height: 115px;
  flex: 0 0 165px;
  border-radius: 14px;
  background: #2f4358;
  overflow: hidden;
}

.activity-img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.activity-content {
  flex: 1;
  min-width: 0;
}

.activity-content h4 {
  font-size: 20px;
  font-weight: 800;
  margin: 0 0 10px;
  color: #10263b;
}

.activity-content p {
  margin: 6px 0;
  color: #40566b;
  line-height: 1.55;
}

.activity-desc {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.tag {
  display: inline-block;
  margin-top: 8px;
  margin-right: 6px;
  padding: 5px 11px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 700;
}

.tag.today {
  background: #ffe7a3;
  color: #6b4b00;
}

.tag.upcoming {
  background: #dcecff;
  color: #24507a;
}

.tag.expired {
  background: #d6d6d6;
  color: #333;
}

.empty-box {
  background: white;
  border-radius: 18px;
  padding: 30px;
  color: #526579;
  box-shadow: 0 4px 14px rgba(47,67,88,0.08);
}

@media (max-width: 768px) {
  .sub-page {
    flex-direction: column;
  }

  .sub-sidebar {
    width: 100%;
    flex: none;
  }

  .sub-main {
    padding: 26px 18px;
  }

  .activity-card {
    flex-direction: column;
  }

  .activity-img {
    width: 100%;
    height: 180px;
    flex: none;
  }
}
</style>

<div class="sub-page">

  <!-- 左邊 -->
  <aside class="sub-sidebar">

    <h3>分類瀏覽</h3>

    <?php foreach ($groupedClubs as $cat => $catClubs): ?>
      <div class="category-box">
        <button type="button" class="category-title">
          <?= htmlspecialchars($cat) ?>（<?= count($catClubs) ?>）
        </button>

        <div class="category-clubs">
          <?php foreach ($catClubs as $club): ?>
            <a href="<?= buildUrl($club['id'], $sort_by, $order) ?>"
               class="side-club <?= $club_id == $club['id'] ? 'active' : '' ?>">

              <div class="club-avatar">
                <?php if (!empty($club['image'])): ?>
                  <img src="<?= htmlspecialchars($club['image']) ?>" alt="<?= htmlspecialchars($club['name']) ?>">
                <?php endif; ?>
              </div>

              <div class="club-text">
                <strong><?= htmlspecialchars($club['name']) ?></strong>
                <p><?= htmlspecialchars($club['category'] ?? '') ?></p>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="sidebar-divider"></div>

    <h3>全部訂閱社團</h3>

    <a href="<?= buildUrl('', $sort_by, $order) ?>"
       class="side-link <?= $club_id === '' ? 'active' : '' ?>">
      全部社團
    </a>

    <?php if (count($clubs) > 0): ?>
      <?php foreach ($clubs as $club): ?>
        <a href="<?= buildUrl($club['id'], $sort_by, $order) ?>"
           class="side-club <?= $club_id == $club['id'] ? 'active' : '' ?>">

          <div class="club-avatar">
            <?php if (!empty($club['image'])): ?>
              <img src="<?= htmlspecialchars($club['image']) ?>" alt="<?= htmlspecialchars($club['name']) ?>">
            <?php endif; ?>
          </div>

          <div class="club-text">
            <strong><?= htmlspecialchars($club['name']) ?></strong>
            <p><?= htmlspecialchars($club['category'] ?? '') ?></p>
          </div>
        </a>
      <?php endforeach; ?>
    <?php else: ?>
      <p style="color:#d9e4ef;">目前尚未訂閱任何社團</p>
    <?php endif; ?>

  </aside>

  <!-- 右邊 -->
  <main class="sub-main">

    <h2>訂閱社團活動</h2>

    <div class="sort-box">
      <a href="<?= buildUrl($club_id, 'created', 'desc') ?>"
         class="<?= $sort_by === 'created' && $order === 'desc' ? 'active' : '' ?>">
        發布日期：近到遠
      </a>

      <a href="<?= buildUrl($club_id, 'created', 'asc') ?>"
         class="<?= $sort_by === 'created' && $order === 'asc' ? 'active' : '' ?>">
        發布日期：遠到近
      </a>

      <a href="<?= buildUrl($club_id, 'event', 'desc') ?>"
         class="<?= $sort_by === 'event' && $order === 'desc' ? 'active' : '' ?>">
        活動時間：近到遠
      </a>

      <a href="<?= buildUrl($club_id, 'event', 'asc') ?>"
         class="<?= $sort_by === 'event' && $order === 'asc' ? 'active' : '' ?>">
        活動時間：遠到近
      </a>
    </div>

    <div class="activity-list">

      <?php if (count($activities) > 0): ?>
        <?php foreach ($activities as $a): ?>

          <?php
            $now = date('Y-m-d H:i:s');
            $eventStart = $a['event_start'] ?? null;
            $eventEnd = $a['event_end'] ?? null;

            if (!empty($eventEnd)) {
              $isExpired = strtotime($eventEnd) < strtotime($now);
            } elseif (!empty($eventStart)) {
              $isExpired = strtotime($eventStart) < strtotime($now);
            } else {
              $isExpired = false;
            }

            $isToday = !empty($eventStart) && date('Y-m-d', strtotime($eventStart)) === date('Y-m-d');
            $isUpcoming = !empty($eventStart) && strtotime($eventStart) > strtotime($now);
          ?>

          <div class="activity-card <?= $isExpired ? 'expired' : '' ?>">

            <div class="activity-img">
              <?php if (!empty($a['image'])): ?>
                <img src="<?= htmlspecialchars($a['image']) ?>" alt="<?= htmlspecialchars($a['title']) ?>">
              <?php elseif (!empty($a['club_image'])): ?>
                <img src="<?= htmlspecialchars($a['club_image']) ?>" alt="<?= htmlspecialchars($a['club_name']) ?>">
              <?php endif; ?>
            </div>

            <div class="activity-content">
              <h4><?= htmlspecialchars($a['title'] ?? '未命名活動') ?></h4>

              <p>
                <strong><?= htmlspecialchars($a['club_name'] ?? '') ?></strong>
                <?php if (!empty($a['club_category'])): ?>
                  ／<?= htmlspecialchars($a['club_category']) ?>
                <?php endif; ?>
              </p>

              <?php if (!empty($a['created_at'])): ?>
                <p>發布日期：<?= htmlspecialchars($a['created_at']) ?></p>
              <?php endif; ?>

              <?php if (!empty($a['event_start'])): ?>
                <p>活動開始：<?= htmlspecialchars($a['event_start']) ?></p>
              <?php endif; ?>

              <?php if (!empty($a['event_end'])): ?>
                <p>活動結束：<?= htmlspecialchars($a['event_end']) ?></p>
              <?php endif; ?>

              <?php if (!empty($a['location'])): ?>
                <p>地點：<?= htmlspecialchars($a['location']) ?></p>
              <?php endif; ?>

              <?php if (!empty($a['fee'])): ?>
                <p>費用：<?= htmlspecialchars($a['fee']) ?></p>
              <?php endif; ?>

              <?php if (!empty($a['description'])): ?>
                <p class="activity-desc"><?= htmlspecialchars($a['description']) ?></p>
              <?php endif; ?>

              <?php if ($isExpired): ?>
                <span class="tag expired">已結束</span>
              <?php elseif ($isToday): ?>
                <span class="tag today">今天活動</span>
              <?php elseif ($isUpcoming): ?>
                <span class="tag upcoming">即將開始</span>
              <?php endif; ?>
            </div>

          </div>

        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-box">
          目前沒有符合條件的訂閱活動。
        </div>
      <?php endif; ?>

    </div>

  </main>

</div>

<script>
document.querySelectorAll('.category-title').forEach(function(btn) {
  btn.addEventListener('click', function() {
    this.parentElement.classList.toggle('open');
  });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>