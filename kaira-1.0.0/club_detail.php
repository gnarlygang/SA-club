<?php
session_start();

// ─── 資料庫連線 ──────────────────────────────────────────────────
$host   = 'localhost';
$dbname = 'sa2026';
$user   = 'root';
$pass   = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $pdo = null;
}

// ─── 取得社團 ID ─────────────────────────────────────────────────
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: clubs.php"); exit; }

$club = null;
$tags = [];
$activities = [];

if ($pdo) {
    // 社團基本資料
    $stmt = $pdo->prepare("SELECT c.*, u.email FROM clubs c LEFT JOIN users u ON u.user_id = c.user_id WHERE c.id = :id");
    $stmt->execute([':id' => $id]);
    $club = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$club) { header("Location: clubs.php"); exit; }

    // 標籤
    $stmt2 = $pdo->prepare("SELECT tag_name FROM club_tags WHERE club_id = :id ORDER BY id");
    $stmt2->execute([':id' => $id]);
    $tags = $stmt2->fetchAll(PDO::FETCH_COLUMN);

    // 該社團的活動（用 user_id 對應）
    if ($club['user_id']) {
        $stmt3 = $pdo->prepare("SELECT * FROM activities WHERE user_id = :uid ORDER BY created_at DESC LIMIT 5");
        $stmt3->execute([':uid' => $club['user_id']]);
        $activities = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    // 假資料
    $club = ['id'=>88,'name'=>'國樂社','category'=>'音樂性社團','image'=>'https://images.unsplash.com/photo-1507838153414-b4b713384a76?auto=format&fit=crop&w=1200&q=80','description'=>'FJU Chinese Music Club','user_id'=>1406061,'email'=>'1406061@cloud.fju.edu.tw'];
    $tags = ['音樂交流','傳統文化'];
    $activities = [];
}

// 分類對應色系
$catColors = [
    '學術性社團'   => ['bg'=>'#e8f0fe','color'=>'#1a56db'],
    '休閒聯誼性社團'=> ['bg'=>'#fef3c7','color'=>'#92400e'],
    '服務性社團'   => ['bg'=>'#d1fae5','color'=>'#065f46'],
    '體能性社團'   => ['bg'=>'#fee2e2','color'=>'#991b1b'],
    '藝術性社團'   => ['bg'=>'#ede9fe','color'=>'#5b21b6'],
    '音樂性社團'   => ['bg'=>'#fce7f3','color'=>'#9d174d'],
];
$cc = $catColors[$club['category']] ?? ['bg'=>'#f0f0f0','color'=>'#666'];

require_once "header.php";
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($club['name']) ?> — FJU_CLUB</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<style>
  body { font-family: "Microsoft JhengHei", sans-serif; background: #f5f5f5; }

  /* ── Hero Image ── */
  .club-hero {
    width: 100%;
    height: 340px;
    object-fit: cover;
    display: block;
    background: #dde1e7;
  }
  .club-hero-placeholder {
    width: 100%; height: 340px;
    background: linear-gradient(135deg, #dde1e7, #c9cfd8);
    display: flex; align-items: center; justify-content: center;
    color: #9aa0aa; font-size: 5rem;
  }

  /* ── Detail Card ── */
  .detail-card {
    background: #fff;
    border-radius: 0 0 16px 16px;
    padding: 2rem 2.5rem 2.5rem;
    max-width: 860px;
    margin: 0 auto 2rem;
    box-shadow: 0 4px 24px rgba(0,0,0,.08);
  }

  .cat-badge {
    display: inline-block; padding: .28rem .75rem;
    border-radius: 99px; font-size: .78rem; font-weight: 600;
    margin-bottom: 1rem;
  }

  .club-name-big {
    font-size: clamp(1.5rem, 3vw, 2rem);
    font-weight: 800; color: #1a1a2e;
    margin-bottom: .3rem;
  }

  /* ── Info Rows ── */
  .info-row {
    display: flex; align-items: flex-start; gap: 1rem;
    padding: 1.1rem 0;
    border-bottom: 1px solid #f0f0f0;
  }
  .info-row:last-child { border-bottom: none; }

  .info-icon {
    width: 42px; height: 42px; border-radius: 10px;
    background: #f0f4ff;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
  }
  .info-icon i { font-size: 1.2rem; color: #4a6cf7; }
  .info-icon.green  { background: #e8f5ee; } .info-icon.green  i { color: #2d8a5e; }
  .info-icon.orange { background: #fff3e0; } .info-icon.orange i { color: #e07a10; }
  .info-icon.purple { background: #f3effe; } .info-icon.purple i { color: #7c3aed; }
  .info-icon.pink   { background: #fce7f3; } .info-icon.pink   i { color: #9d174d; }

  .info-label { font-size: .75rem; color: #999; margin-bottom: .2rem; }
  .info-value { font-size: .95rem; color: #1a1a2e; font-weight: 500; line-height: 1.5; }

  /* ── Tags ── */
  .tag-wrap { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: 1rem; }
  .tag-item {
    padding: .25rem .7rem; border-radius: 99px;
    font-size: .75rem; background: #f5f5f5;
    border: 1px solid #e5e5e5; color: #555;
  }

  /* ── Activities Section ── */
  .section-title {
    font-size: 1rem; font-weight: 700; color: #1a1a2e;
    margin-bottom: 1rem; padding-bottom: .5rem;
    border-bottom: 2px solid #1a1a2e;
    display: inline-block;
  }
  .act-item {
    padding: .9rem 1rem; border-radius: 8px;
    border: 1px solid #efefef; background: #fafafa;
    margin-bottom: .6rem; transition: background .18s;
    text-decoration: none; color: inherit; display: block;
  }
  .act-item:hover { background: #f0f4ff; color: inherit; }
  .act-title { font-weight: 600; font-size: .9rem; color: #1a1a2e; margin-bottom: .25rem; }
  .act-meta  { font-size: .75rem; color: #999; display: flex; flex-wrap: wrap; gap: .5rem .8rem; }
  .act-meta span { display: flex; align-items: center; gap: .25rem; }
  .act-badge {
    font-size: .68rem; padding: .15rem .5rem; border-radius: 99px;
    background: #e8f5ed; color: #2d8a5e; font-weight: 500; flex-shrink: 0;
  }
  .act-badge.paid { background: #fff3e0; color: #b85c00; }

  /* ── Back Button ── */
  .back-btn {
    display: inline-flex; align-items: center; gap: .4rem;
    color: #666; text-decoration: none; font-size: .85rem;
    padding: .4rem .9rem; border-radius: 6px;
    border: 1px solid #ddd; background: #fff;
    transition: all .18s; margin-bottom: 1rem;
    max-width: 860px;
  }
  .back-btn:hover { background: #1a1a2e; color: #fff; border-color: #1a1a2e; }
  .back-wrap { max-width: 860px; margin: 1.5rem auto .5rem; padding: 0 1.5rem; }

  @media (max-width: 640px) {
    .detail-card { padding: 1.2rem 1.2rem 1.5rem; border-radius: 12px; }
    .club-hero, .club-hero-placeholder { height: 220px; }
    .back-wrap { padding: 0 1rem; }
  }
</style>
</head>
<body>

<?php require_once "header.php"; ?>

<!-- Back button -->
<div class="back-wrap container">
    <a href="clubs.php<?= $club['category'] ? '?cat='.urlencode($club['category']) : '' ?>" class="back-btn">
        <i class="bi bi-arrow-left"></i> 返回社團列表
    </a>
</div>

<!-- Hero Image + Detail Card -->
<div style="max-width:860px; margin:0 auto; padding: 0 1.5rem 2rem;">

    <!-- 圖片 -->
    <?php if (!empty($club['image'])): ?>
        <img class="club-hero" src="<?= htmlspecialchars($club['image']) ?>" alt="<?= htmlspecialchars($club['name']) ?>">
    <?php else: ?>
        <div class="club-hero-placeholder">
            <i class="bi bi-people-fill"></i>
        </div>
    <?php endif; ?>

    <!-- 主資訊卡 -->
    <div class="detail-card">

        <!-- 分類 Badge -->
        <span class="cat-badge" style="background:<?= $cc['bg'] ?>;color:<?= $cc['color'] ?>">
            <?= htmlspecialchars($club['category']) ?>
        </span>

        <!-- 社團名稱 -->
        <h1 class="club-name-big"><?= htmlspecialchars($club['name']) ?></h1>

        <!-- 標籤 -->
        <?php if (!empty($tags)): ?>
        <div class="tag-wrap" style="margin-bottom:.5rem;">
            <?php foreach ($tags as $tag): ?>
            <span class="tag-item"># <?= htmlspecialchars($tag) ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <hr style="border-color:#f0f0f0; margin: 1rem 0;">

        <!-- 社團介紹 -->
        <div class="info-row">
            <div class="info-icon green"><i class="bi bi-card-text"></i></div>
            <div>
                <div class="info-label">社團介紹</div>
                <div class="info-value"><?= nl2br(htmlspecialchars($club['description'])) ?></div>
            </div>
        </div>

        <!-- 分類 -->
        <div class="info-row">
            <div class="info-icon purple"><i class="bi bi-tag"></i></div>
            <div>
                <div class="info-label">社團類型</div>
                <div class="info-value"><?= htmlspecialchars($club['category']) ?></div>
            </div>
        </div>

        <!-- 聯絡信箱 -->
        <?php if (!empty($club['email'])): ?>
        <div class="info-row">
            <div class="info-icon orange"><i class="bi bi-envelope"></i></div>
            <div>
                <div class="info-label">聯絡信箱</div>
                <div class="info-value">
                    <a href="mailto:<?= htmlspecialchars($club['email']) ?>" style="color:#c8502a; text-decoration:none;">
                        <?= htmlspecialchars($club['email']) ?>
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /.detail-card -->

    <!-- 近期活動 -->
    <?php if (!empty($activities)): ?>
    <div style="background:#fff; border-radius:12px; padding:1.5rem 2rem; box-shadow:0 2px 12px rgba(0,0,0,.06); margin-bottom:2rem;">
        <div class="section-title">近期活動</div>
        <?php foreach ($activities as $act):
            $isFree = str_contains($act['fee'], '免費') || $act['fee'] === '0';
            $isPast = strtotime($act['signup_deadline']) < time();
        ?>
        <a href="activity_detail.php?id=<?= $act['id'] ?>" class="act-item">
            <div style="display:flex; align-items:center; gap:.5rem; margin-bottom:.3rem;">
                <span class="act-title"><?= htmlspecialchars($act['title']) ?></span>
                <span class="act-badge <?= $isFree ? '' : 'paid' ?>">
                    <?= $isFree ? '免費' : htmlspecialchars($act['fee']) ?>
                </span>
                <?php if ($isPast): ?>
                <span class="act-badge" style="background:#f0f0f0;color:#999;">已截止</span>
                <?php endif; ?>
            </div>
            <div class="act-meta">
                <span><i class="bi bi-calendar3"></i> <?= date('Y/m/d H:i', strtotime($act['event_start'])) ?></span>
                <span><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($act['location']) ?></span>
                <span><i class="bi bi-clock"></i> 報名截止 <?= date('Y/m/d', strtotime($act['signup_deadline'])) ?></span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div><!-- /.container -->

<!-- Footer -->
<footer id="footer" class="mt-5" style="background-color: #afbac7; color: #333333; font-family: 'Microsoft JhengHei', '微軟正黑體', sans-serif;">
    <div class="container">
      <div class="row d-flex flex-wrap justify-content-between py-5" style="border-bottom: 1px solid rgba(0,0,0,0.1);">
        <div class="col-md-4 col-sm-6 mb-4">
          <h5 class="mb-4 pb-2" style="border-bottom:2px solid #555;width:fit-content;font-weight:bold;">校園連結</h5>
          <ul class="list-unstyled fs-6">
            <li class="py-1"><a href="https://www.fju.edu.tw/" target="_blank" class="text-dark text-decoration-none">＞ 輔大全球資訊網</a></li>
            <li class="py-1"><a href="https://www.fju.edu.tw/article.jsp?articleID=21" target="_blank" class="text-dark text-decoration-none">＞ WebMail & LDAP</a></li>
            <li class="py-1"><a href="https://www.fju.edu.tw/resource.jsp?labelID=27" target="_blank" class="text-dark text-decoration-none">＞ 職涯服務 & 學生會</a></li>
          </ul>
        </div>
        <div class="col-md-4 col-sm-6 mb-4">
          <h5 class="mb-4 pb-2" style="border-bottom:2px solid #555;width:fit-content;font-weight:bold;">公告資訊</h5>
          <ul class="list-unstyled fs-6">
            <li class="py-1"><a href="https://www.fju.edu.tw/fee/1_1.html" target="_blank" class="text-dark text-decoration-none">＞ 校務財務資訊專區</a></li>
            <li class="py-1"><a href="http://life.dsa.fju.edu.tw/resource.jsp?labelID=35" target="_blank" class="text-dark text-decoration-none">＞ 獎助學金</a></li>
            <li class="py-1"><a href="http://www.secretariat.fju.edu.tw/article.jsp?articleID=8" target="_blank" class="text-dark text-decoration-none">＞ 行事曆</a></li>
          </ul>
        </div>
        <div class="col-md-4 col-sm-6 mb-4">
          <h5 class="mb-4 pb-2" style="border-bottom:2px solid #555;width:fit-content;font-weight:bold;">快速連結</h5>
          <ul class="list-unstyled fs-6">
            <li class="py-1"><a href="http://activity.dsa.fju.edu.tw/ActivityList.jsp" target="_blank" class="text-dark text-decoration-none">＞ 活動報名系統</a></li>
            <li class="py-1"><a href="https://www.fju.edu.tw/article.jsp?articleID=5" target="_blank" class="text-dark text-decoration-none">＞ 輔大媒體家族</a></li>
            <li class="py-1"><a href="https://cre.fju.edu.tw/" target="_blank" class="text-dark text-decoration-none">＞ 研究倫理中心</a></li>
          </ul>
        </div>
      </div>
      <div class="row py-4 align-items-center">
        <div class="col-md-6 mb-3">
          <p class="h6 mb-1" style="font-weight:bold;">天主教輔仁大學</p>
          <p class="mb-1 small">242062 新北市新莊區中正路510號</p>
          <p class="mb-0 small">電話：(02) 2905-2000</p>
        </div>
        <div class="col-md-6 text-md-end">
          <p class="small mb-0 opacity-75" style="color:#444;">
            天主教輔仁大學 © 2014-2026 版權所有 |
            <a href="https://www.fju.edu.tw/privacy.jsp" target="_blank" class="text-dark mx-1">隱私權聲明</a>
          </p>
        </div>
      </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>