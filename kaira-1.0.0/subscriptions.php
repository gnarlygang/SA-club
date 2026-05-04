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