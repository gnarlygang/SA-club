<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/api/db.php";

function h($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function fmtDate($v) {
    if (empty($v)) return '';
    return date("Y/m/d", strtotime($v));
}

function fmtDateTime($v) {
    if (empty($v)) return '';
    return date("Y/m/d H:i", strtotime($v));
}

function shortText($text, $len = 55) {
    $text = trim(strip_tags((string)($text ?? '')));
    return mb_strlen($text, "UTF-8") > $len
        ? mb_substr($text, 0, $len, "UTF-8") . "..."
        : $text;
}

function imgOrDefault($url) {
    $url = trim((string)($url ?? ''));
    return $url !== '' ? $url : 'https://images.unsplash.com/photo-1517048676732-d65bc937f952?auto=format&fit=crop&w=800&q=80';
}

/* ── 統計資料 ── */
$clubCount    = (int)$pdo->query("SELECT COUNT(*) FROM clubs")->fetchColumn();
$activityCount = (int)$pdo->query("SELECT COUNT(*) FROM activities")->fetchColumn();
$userCount    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 3")->fetchColumn();

$uid = $_SESSION['user_id'] ?? null;

/* ══════════════════════════════════════════════════════
   精選活動（Hero）
   登入：找用戶有收藏的活動同社團（subscriptions）中，最近未過期的活動
   未登入：訂閱數最多社團的最近活動
══════════════════════════════════════════════════════ */
if ($uid) {
    // 用戶訂閱的社團 user_id 清單
    $featuredActivity = $pdo->prepare("
        SELECT a.*
        FROM activities a
        JOIN clubs c ON c.user_id = a.user_id
        JOIN subscriptions s ON s.club_id = c.id
        WHERE s.user_id = :uid
          AND a.event_start >= NOW()
        ORDER BY a.event_start ASC
        LIMIT 1
    ");
    $featuredActivity->execute([':uid' => $uid]);
    $featuredActivity = $featuredActivity->fetch(PDO::FETCH_ASSOC);

    // fallback：若訂閱社團無近期活動，改用全局最近活動
    if (!$featuredActivity) {
        $featuredActivity = $pdo->query("
            SELECT * FROM activities WHERE event_start >= NOW()
            ORDER BY event_start ASC LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);
    }
} else {
    // 未登入：訂閱人數最多的社團的最近活動
    $featuredActivity = $pdo->query("
        SELECT a.*
        FROM activities a
        JOIN clubs c ON c.user_id = a.user_id
        JOIN (
            SELECT club_id, COUNT(*) AS sub_cnt
            FROM subscriptions GROUP BY club_id ORDER BY sub_cnt DESC LIMIT 1
        ) top ON top.club_id = c.id
        WHERE a.event_start >= NOW()
        ORDER BY a.event_start ASC
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    // fallback
    if (!$featuredActivity) {
        $featuredActivity = $pdo->query("
            SELECT * FROM activities WHERE event_start >= NOW()
            ORDER BY event_start ASC LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);
    }
}

/* ══════════════════════════════════════════════════════
   推薦活動（4 張卡）
   登入：
     優先顯示訂閱社團的活動（weight +3）
     其次是用戶收藏過的活動所屬分類相同的活動（weight +2）
     基礎分：距今越近的未截止活動分越高
   未登入：
     按 (訂閱數×2 + 截止日距今遠近) 排序
══════════════════════════════════════════════════════ */
if ($uid) {
    $activities = $pdo->prepare("
        SELECT a.*,
            (
                -- 訂閱社團加分
                CASE WHEN EXISTS (
                    SELECT 1 FROM subscriptions s
                    JOIN clubs c ON c.id = s.club_id
                    WHERE s.user_id = :uid1 AND c.user_id = a.user_id
                ) THEN 3 ELSE 0 END
                +
                -- 收藏過同社團活動加分
                CASE WHEN EXISTS (
                    SELECT 1 FROM favorites f
                    JOIN activities fa ON fa.id = f.item_id AND f.item_type = 'activity'
                    WHERE f.user_id = :uid2 AND fa.user_id = a.user_id
                ) THEN 2 ELSE 0 END
            ) AS rec_score
        FROM activities a
        WHERE a.signup_deadline >= CURDATE()
        ORDER BY rec_score DESC, a.event_start ASC
        LIMIT 4
    ");
    $activities->execute([':uid1' => $uid, ':uid2' => $uid]);
    $activities = $activities->fetchAll(PDO::FETCH_ASSOC);
} else {
    $activities = $pdo->query("
        SELECT a.*,
            COALESCE((
                SELECT COUNT(*) FROM subscriptions s
                JOIN clubs c ON c.id = s.club_id
                WHERE c.user_id = a.user_id
            ), 0) * 2 AS rec_score
        FROM activities a
        WHERE a.signup_deadline >= CURDATE()
        ORDER BY rec_score DESC, a.event_start ASC
        LIMIT 4
    ")->fetchAll(PDO::FETCH_ASSOC);
}

/* ── 系統公告 ── */
$announcements = $pdo->query("
    SELECT * FROM announcements ORDER BY date DESC LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

/* ══════════════════════════════════════════════════════
   熱門社團排序公式：
     訂閱數 × 3
   + 最近 30 天有活動數 × 2
   + 若有活動：距最近活動越近加分（最多 +5，線性遞減）
   總分越高排越前
══════════════════════════════════════════════════════ */
$hotClubs = $pdo->query("
    SELECT
        c.id, c.name, c.category, c.image, c.description,
        COUNT(DISTINCT s.id) AS follower_count,
        COUNT(DISTINCT CASE
            WHEN a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN a.id
        END) AS recent_act_count,
        MIN(CASE WHEN a.event_start >= NOW() THEN a.event_start END) AS next_event,
        (
            COUNT(DISTINCT s.id) * 3
            + COUNT(DISTINCT CASE
                WHEN a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN a.id
              END) * 2
            + COALESCE(
                GREATEST(0, 5 - FLOOR(
                    DATEDIFF(MIN(CASE WHEN a.event_start >= NOW() THEN a.event_start END), NOW()) / 7
                )),
                0
              )
        ) AS hot_score
    FROM clubs c
    LEFT JOIN subscriptions s ON s.club_id = c.id
    LEFT JOIN activities a ON a.user_id = c.user_id
    GROUP BY c.id, c.name, c.category, c.image, c.description
    ORDER BY hot_score DESC, follower_count DESC, c.id ASC
    LIMIT 4
")->fetchAll(PDO::FETCH_ASSOC);

/* ══════════════════════════════════════════════════════
   社團論壇
   登入：優先推薦用戶收藏過的同分類貼文、以及留言數多的貼文
   未登入：留言數最多的貼文
══════════════════════════════════════════════════════ */
if ($uid) {
    $posts = $pdo->prepare("
        SELECT
            fp.id, fp.title, fp.content, fp.created_at,
            fc_cat.name AS club_name,
            u.nickname, u.username,
            COUNT(DISTINCT fc.id) AS comment_count,
            (
                -- 用戶收藏過同分類加分
                CASE WHEN EXISTS (
                    SELECT 1 FROM favorites fav
                    JOIN forum_posts fp2 ON fp2.id = fav.item_id AND fav.item_type = 'post'
                    WHERE fav.user_id = :uid1 AND fp2.category_id = fp.category_id
                ) THEN 3 ELSE 0 END
                + COUNT(DISTINCT fc.id) -- 留言數本身也是分數
            ) AS rec_score,
            CASE WHEN COUNT(DISTINCT fc.id) >= 5 THEN 'hot' ELSE 'rec' END AS type
        FROM forum_posts fp
        LEFT JOIN forum_categories fc_cat ON fc_cat.id = fp.category_id
        LEFT JOIN users u ON u.user_id = fp.user_id
        LEFT JOIN forum_comments fc ON fc.post_id = fp.id AND fc.is_deleted = 0
        WHERE fp.is_deleted = 0
        GROUP BY fp.id, fp.title, fp.content, fp.created_at, fc_cat.name, u.nickname, u.username
        ORDER BY rec_score DESC, fp.created_at DESC
        LIMIT 3
    ");
    $posts->execute([':uid1' => $uid]);
    $posts = $posts->fetchAll(PDO::FETCH_ASSOC);
} else {
    $posts = $pdo->query("
        SELECT
            fp.id, fp.title, fp.content, fp.created_at,
            fc_cat.name AS club_name,
            u.nickname, u.username,
            COUNT(DISTINCT fc.id) AS comment_count,
            CASE WHEN COUNT(DISTINCT fc.id) >= 5 THEN 'hot' ELSE 'rec' END AS type
        FROM forum_posts fp
        LEFT JOIN forum_categories fc_cat ON fc_cat.id = fp.category_id
        LEFT JOIN users u ON u.user_id = fp.user_id
        LEFT JOIN forum_comments fc ON fc.post_id = fp.id AND fc.is_deleted = 0
        WHERE fp.is_deleted = 0
        GROUP BY fp.id, fp.title, fp.content, fp.created_at, fc_cat.name, u.nickname, u.username
        ORDER BY comment_count DESC, fp.created_at DESC
        LIMIT 3
    ")->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FJU_CLUB - 天主教輔仁大學社團平台</title>

<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;700&family=Noto+Sans+TC:wght@300;400;500;700&display=swap" rel="stylesheet">

<style>
:root {
  --navy: #1a2744;
  --navy-mid: #243257;
  --navy-light: #2e3f6b;
  --accent: #3a5fa0;
  --accent-hover: #4a72be;
  --gold: #c8a96e;
  --gold-light: #e8c98e;
  --cream: #f7f4ef;
  --cream-dark: #ede9e0;
  --white: #ffffff;
  --text-dark: #1a1f2e;
  --text-mid: #4a5068;
  --text-muted: #8a91a8;
  --border-light: rgba(26,39,68,0.10);
  --border-mid: rgba(26,39,68,0.18);
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Noto Sans TC', sans-serif;
  background: var(--cream);
  color: var(--text-dark);
  min-height: 100vh;
}
a { text-decoration: none; }

/* HERO */
.hero {
  background: var(--navy);
  padding: 0 2.5rem;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 3rem;
  align-items: center;
  min-height: 380px;
  position: relative;
  overflow: hidden;
}
.hero::after {
  content: '';
  position: absolute;
  right: -100px;
  bottom: -100px;
  width: 420px;
  height: 420px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(200,169,110,0.12) 0%, transparent 65%);
  pointer-events: none;
}
.hero-text { padding: 3.5rem 0; position: relative; z-index: 1; }
.hero-eyebrow {
  font-size: 0.72rem;
  font-weight: 500;
  letter-spacing: 0.18em;
  color: var(--gold);
  text-transform: uppercase;
  margin-bottom: 1rem;
}
.hero h1 {
  font-family: 'Noto Serif TC', serif;
  font-size: clamp(1.8rem, 3.5vw, 2.8rem);
  font-weight: 700;
  line-height: 1.3;
  color: #fff;
  margin-bottom: 1rem;
}
.hero h1 em { font-style: normal; color: var(--gold-light); }
.hero-sub {
  font-size: 0.9rem;
  color: rgba(255,255,255,0.55);
  line-height: 1.8;
  margin-bottom: 2rem;
  font-weight: 300;
  max-width: 360px;
}
.hero-btns { display: flex; gap: 0.75rem; flex-wrap: wrap; }
.btn-hero-primary {
  background: var(--gold);
  color: var(--navy);
  border: none;
  padding: 0.65rem 1.6rem;
  border-radius: 7px;
  font-size: 0.88rem;
  font-weight: 700;
  cursor: pointer;
  display: inline-block;
  transition: background 0.2s, transform 0.15s;
  letter-spacing: 0.04em;
}
.btn-hero-primary:hover { background: var(--gold-light); transform: translateY(-1px); }
.btn-hero-ghost {
  background: transparent;
  color: rgba(255,255,255,0.8);
  border: 1px solid rgba(255,255,255,0.28);
  padding: 0.65rem 1.6rem;
  border-radius: 7px;
  font-size: 0.88rem;
  cursor: pointer;
  display: inline-block;
  transition: background 0.2s;
}
.btn-hero-ghost:hover { background: rgba(255,255,255,0.08); }
.hero-stats {
  display: flex;
  gap: 2.5rem;
  margin-top: 2.5rem;
  padding-top: 2rem;
  border-top: 1px solid rgba(255,255,255,0.1);
}
.hero-stat-num {
  font-family: 'Noto Serif TC', serif;
  font-size: 1.6rem;
  font-weight: 700;
  color: var(--gold-light);
  line-height: 1;
  margin-bottom: 0.25rem;
}
.hero-stat-label {
  font-size: 0.72rem;
  color: rgba(255,255,255,0.45);
  letter-spacing: 0.06em;
}
.hero-right { padding: 2rem 0; position: relative; z-index: 1; }
.upcoming-label {
  font-size: 0.7rem;
  font-weight: 600;
  letter-spacing: 0.14em;
  color: rgba(255,255,255,0.4);
  text-transform: uppercase;
  margin-bottom: 0.9rem;
}
.activity-card-featured {
  background: rgba(255,255,255,0.07);
  border: 1px solid rgba(255,255,255,0.14);
  border-radius: 14px;
  overflow: hidden;
  transition: border-color 0.2s;
}
.activity-card-featured:hover { border-color: rgba(200,169,110,0.4); }
.act-img-hero {
  width: 100%;
  height: 160px;
  background: linear-gradient(135deg, #243257 0%, #1a2744 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
}
.act-img-hero svg { width: 40px; height: 40px; stroke: rgba(255,255,255,0.2); fill: none; stroke-width: 1.5; }
.act-img-hero .act-badge {
  position: absolute;
  top: 12px;
  left: 12px;
  background: var(--gold);
  color: var(--navy);
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  padding: 0.2rem 0.6rem;
  border-radius: 4px;
}
.act-hero-body { padding: 1.1rem 1.3rem 1.3rem; }
.act-hero-meta {
  font-size: 0.72rem;
  color: rgba(255,255,255,0.4);
  margin-bottom: 0.4rem;
}
.act-hero-body h3 {
  font-size: 1rem;
  font-weight: 600;
  color: #fff;
  margin-bottom: 0.4rem;
  line-height: 1.4;
}
.act-hero-body p {
  font-size: 0.78rem;
  color: rgba(255,255,255,0.5);
  line-height: 1.6;
  margin-bottom: 0.9rem;
  font-weight: 300;
}
.btn-register-sm {
  display: inline-block;
  background: var(--accent);
  color: #fff;
  font-size: 0.78rem;
  padding: 0.4rem 1rem;
  border-radius: 6px;
  font-weight: 500;
  transition: background 0.18s;
}
.btn-register-sm:hover { background: var(--accent-hover); }

/* MAIN */
.main-wrap {
  max-width: 1080px;
  margin: 0 auto;
  padding: 2.5rem 2rem;
  display: grid;
  grid-template-columns: 580px 320px;
  gap: 2rem;
  align-items: start;
}
.sec-label {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  margin-bottom: 1.2rem;
}
.sec-label h2 {
  font-family: 'Noto Serif TC', serif;
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--navy);
  letter-spacing: 0.02em;
}
.sec-label::before {
  content: '';
  width: 4px;
  height: 1.15em;
  background: var(--gold);
  border-radius: 2px;
  flex-shrink: 0;
}
.see-all {
  margin-left: auto;
  font-size: 0.8rem;
  color: var(--accent);
  font-weight: 500;
}
.activities-block, .forum-block { margin-bottom: 2.5rem; }
.activities-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}
.activities-block .act-card {
  background: var(--white);
  border: 1px solid var(--border-light);
  border-radius: 12px;
  overflow: hidden;
  cursor: pointer;
  transition: box-shadow 0.2s, transform 0.18s, border-color 0.2s;
  display: block;
  grid-template-columns: unset;
}
.activities-block .act-card:hover {
  box-shadow: 0 8px 32px rgba(26,39,68,0.10);
  transform: translateY(-2px);
  border-color: var(--border-mid);
}
.activities-block .act-card-img {
  width: 100%;
  height: 120px;
  background: linear-gradient(135deg, #e8eef6 0%, #d4dded 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
}
.activities-block .act-card-img svg { width: 28px; height: 28px; stroke: rgba(58,95,160,0.3); fill: none; stroke-width: 1.5; }
.activities-block .act-card-img .tag {
  position: absolute;
  top: 8px;
  left: 8px;
  font-size: 0.62rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  padding: 0.18rem 0.5rem;
  border-radius: 4px;
  background: var(--navy);
  color: rgba(255,255,255,0.9);
  writing-mode: horizontal-tb;
  text-orientation: mixed;
}
.activities-block .act-card-body { padding: 0.9rem 1rem; }
.activities-block .act-card-meta {
  font-size: 0.7rem;
  color: var(--text-muted);
  margin-bottom: 0.35rem;
}
.activities-block .act-card-body h3 {
  font-size: 0.88rem;
  font-weight: 600;
  color: var(--text-dark);
  margin-bottom: 0.3rem;
  line-height: 1.4;
}
.activities-block .act-card-body p {
  font-size: 0.75rem;
  color: var(--text-mid);
  line-height: 1.55;
  font-weight: 300;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.activities-block .act-card-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 0.7rem;
  padding-top: 0.7rem;
  border-top: 1px solid var(--border-light);
}
.act-count { font-size: 0.7rem; color: var(--text-muted); }
.btn-register-xs {
  font-size: 0.7rem;
  font-weight: 600;
  padding: 0.22rem 0.7rem;
  background: var(--cream);
  color: var(--accent);
  border: 1px solid rgba(58,95,160,0.3);
  border-radius: 5px;
  transition: background 0.18s;
}
.btn-register-xs:hover { background: rgba(58,95,160,0.08); }

/* FORUM */
.forum-list { display: flex; flex-direction: column; gap: 0.6rem; }
.forum-item {
  background: var(--white);
  border: 1px solid var(--border-light);
  border-radius: 10px;
  padding: 0.95rem 1.1rem;
  cursor: pointer;
  transition: box-shadow 0.18s, border-color 0.18s;
  display: flex;
  gap: 1rem;
  align-items: flex-start;
}
.forum-item:hover {
  box-shadow: 0 4px 18px rgba(26,39,68,0.08);
  border-color: var(--border-mid);
}
.forum-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  background: #fff0f0;
}
.forum-body { flex: 1; min-width: 0; }
.forum-tags { display: flex; gap: 0.4rem; margin-bottom: 0.3rem; flex-wrap: wrap; }
.ftag {
  font-size: 0.62rem;
  font-weight: 600;
  letter-spacing: 0.05em;
  padding: 0.1rem 0.45rem;
  border-radius: 4px;
}
.ftag.red { background: #fff0f0; color: #c0392b; }
.ftag.blue { background: #f0f4ff; color: var(--accent); }
.ftag.green { background: #f0fff5; color: #2d7a4f; }
.ftag.gray { background: var(--cream-dark); color: var(--text-mid); }
.forum-body h3 {
  font-size: 0.88rem;
  font-weight: 600;
  color: var(--text-dark);
  margin-bottom: 0.2rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.forum-body p {
  font-size: 0.76rem;
  color: var(--text-mid);
  font-weight: 300;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.forum-foot { display: flex; align-items: center; gap: 1rem; margin-top: 0.5rem; }
.forum-stat { font-size: 0.69rem; color: var(--text-muted); }

/* SIDEBAR */
.sidebar { display: flex; flex-direction: column; gap: 1.4rem; }
.sidebar-card {
  background: var(--white);
  border: 1px solid var(--border-light);
  border-radius: 12px;
  overflow: hidden;
}
.sidebar-card-header {
  background: var(--navy);
  padding: 0.8rem 1.2rem;
  display: flex;
  align-items: center;
  gap: 0.6rem;
}
.sidebar-card-header h3 {
  font-size: 0.82rem;
  font-weight: 600;
  color: rgba(255,255,255,0.9);
  letter-spacing: 0.06em;
}
.sidebar-card-body { padding: 0.2rem 0; }
.ann-item {
  padding: 0.75rem 1.2rem;
  border-bottom: 1px solid var(--border-light);
  cursor: pointer;
  transition: background 0.15s;
}
.ann-item:last-child { border-bottom: none; }
.ann-item:hover { background: var(--cream); }
.ann-tag {
  font-size: 0.6rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  padding: 0.1rem 0.45rem;
  border-radius: 3px;
  display: inline-block;
  margin-bottom: 0.3rem;
}
.ann-tag.sys { background: #e8eef6; color: var(--accent); }
.ann-item h4 {
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--text-dark);
  margin-bottom: 0.15rem;
  line-height: 1.35;
}
.ann-item p { font-size: 0.71rem; color: var(--text-mid); font-weight: 300; }
.ann-date { font-size: 0.65rem; color: var(--text-muted); margin-top: 0.25rem; }
.hot-clubs-list { padding: 0.3rem 0; }
.club-item {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  padding: 0.65rem 1.2rem;
  border-bottom: 1px solid var(--border-light);
  cursor: pointer;
  transition: background 0.15s;
  color: inherit;
}
.club-item:last-child { border-bottom: none; }
.club-item:hover { background: var(--cream); }
.club-rank {
  font-family: 'Noto Serif TC', serif;
  font-size: 0.95rem;
  font-weight: 700;
  width: 20px;
  text-align: center;
  flex-shrink: 0;
  color: var(--cream-dark);
}
.club-rank.gold { color: var(--gold); }
.club-rank.silver { color: #aaa; }
.club-rank.bronze { color: #b87333; }
.club-avatar {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  background: linear-gradient(135deg, #e8eef6, #d0daee);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.65rem;
  font-weight: 700;
  color: var(--accent);
  flex-shrink: 0;
  letter-spacing: 0.03em;
}
.club-info { flex: 1; min-width: 0; }
.club-name { font-size: 0.82rem; font-weight: 600; color: var(--text-dark); }
.club-sub { font-size: 0.68rem; color: var(--text-muted); font-weight: 300; }
.club-arrow { color: var(--text-muted); font-size: 0.8rem; }

/* FOOTER */
footer {
  background: var(--navy);
  margin-top: 1rem;
}
.footer-top {
  max-width: 1080px;
  margin: 0 auto;
  padding: 2.5rem 2rem 2rem;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 2rem;
}
.foot-col h5 {
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  color: rgba(255,255,255,0.4);
  text-transform: uppercase;
  margin-bottom: 1rem;
}
.foot-col ul { list-style: none; }
.foot-col ul li { margin-bottom: 0.5rem; }
.foot-col ul li a {
  font-size: 0.8rem;
  color: rgba(255,255,255,0.55);
  font-weight: 300;
  transition: color 0.18s;
  display: flex;
  align-items: center;
  gap: 5px;
}
.foot-col ul li a::before { content: '›'; color: var(--gold); font-size: 0.95rem; }
.foot-col ul li a:hover { color: #fff; }
.footer-bottom {
  border-top: 1px solid rgba(255,255,255,0.08);
  max-width: 1080px;
  margin: 0 auto;
  padding: 1.5rem 2rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
}
.footer-brand { display: flex; align-items: center; gap: 1rem; }
.footer-logo-box {
  width: 56px;
  height: 42px;
  background: var(--navy-mid);
  border-radius: 7px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: 'Noto Serif TC', serif;
  font-size: 0.7rem;
  font-weight: 700;
  color: var(--gold);
  letter-spacing: 0.04em;
}
.footer-brand-info strong {
  display: block;
  font-size: 0.84rem;
  font-weight: 600;
  color: rgba(255,255,255,0.85);
}
.footer-brand-info span { font-size: 0.72rem; color: rgba(255,255,255,0.38); font-weight: 300; }
.footer-copy { font-size: 0.72rem; color: rgba(255,255,255,0.3); font-weight: 300; }
.footer-contact { font-size: 0.75rem; color: rgba(255,255,255,0.45); font-weight: 300; text-align: right; line-height: 1.7; }

@media (max-width: 760px) {
  .hero { grid-template-columns: 1fr; }
  .hero-right { display: none; }
  .main-wrap { grid-template-columns: 1fr; }
  .sidebar { order: -1; }
  .activities-row { grid-template-columns: 1fr; }
  .footer-top { grid-template-columns: 1fr; }
  .footer-bottom { flex-direction: column; align-items: flex-start; }
  
}

.sidebar-card-header .see-all {
  color: #ffffff !important;
}

.sidebar-card-header .see-all:hover {
  color: #ffffff !important;
  opacity: 0.8;
}
</style>
</head>

<body>

<?php require_once __DIR__ . "/header.php"; ?>

<!-- HERO -->
<section class="hero">
  <div class="hero-text">
    <p class="hero-eyebrow">FJU · 天主教輔仁大學</p>
    <h1>找到你的<em>熱情所在</em><br>從社團開始</h1>
    <p class="hero-sub">探索百餘個精彩社團，參與活動、結交夥伴，讓大學四年留下最難忘的記憶。</p>

    <div class="hero-btns">
      <a href="clubs.php" class="btn-hero-primary">探索社團</a>
      <a href="activities.php" class="btn-hero-ghost">近期活動</a>
    </div>

    <div class="hero-stats">
      <div>
        <div class="hero-stat-num"><?= h($clubCount) ?>+</div>
        <div class="hero-stat-label">社團數量</div>
      </div>
      <div>
        <div class="hero-stat-num"><?= h($activityCount) ?>+</div>
        <div class="hero-stat-label">近期活動</div>
      </div>
      <div>
        <div class="hero-stat-num"><?= h($userCount) ?>+</div>
        <div class="hero-stat-label">學生成員</div>
      </div>
    </div>
  </div>

  <div class="hero-right">
    <p class="upcoming-label"><?= $uid ? '為你精選活動' : '精選活動' ?></p>

    <?php if ($featuredActivity): ?>
      <div class="activity-card-featured">
        <div class="act-img-hero">
          <svg viewBox="0 0 24 24"><path d="M9 19V6l12-3v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="15" r="3"/></svg>
          <span class="act-badge"><?= $uid ? '推薦活動' : '近期活動' ?></span>
        </div>

        <div class="act-hero-body">
          <div class="act-hero-meta">
            <?= h(fmtDateTime($featuredActivity['event_start'])) ?> · <?= h($featuredActivity['organizer']) ?>
          </div>
          <h3><?= h($featuredActivity['title']) ?></h3>
          <p><?= h(shortText($featuredActivity['description'], 70)) ?></p>
          <a href="activity_view.php?id=<?= h($featuredActivity['id']) ?>" class="btn-register-sm">查看活動</a>
        </div>
      </div>
    <?php else: ?>
      <div class="activity-card-featured">
        <div class="act-hero-body">
          <h3>目前沒有近期活動</h3>
          <p>之後社團發布活動後，這裡會自動顯示。</p>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- MAIN CONTENT -->
<div class="main-wrap">

  <!-- LEFT -->
  <div>
    <!-- Activities -->
    <div class="activities-block">
      <div class="sec-label">
        <h2><?= $uid ? '推薦活動' : '推薦活動' ?></h2>
        <a href="activities.php" class="see-all">查看全部 →</a>
      </div>

      <div class="activities-row">
        <?php if (!empty($activities)): ?>
          <?php foreach ($activities as $act): ?>
            <div class="act-card">
              <div class="act-card-img">
                <svg viewBox="0 0 24 24"><path d="M9 19V6l12-3v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="15" r="3"/></svg>
                <span class="tag"><?= h($act['organizer']) ?></span>
              </div>

              <div class="act-card-body">
                <div class="act-card-meta">
                  <?= h(fmtDateTime($act['event_start'])) ?> · <?= h($act['location']) ?>
                </div>

                <h3><?= h($act['title']) ?></h3>
                <p><?= h(shortText($act['description'], 58)) ?></p>

                <div class="act-card-foot">
                  <span class="act-count">
                    截止：<?= h(fmtDate($act['signup_deadline'])) ?>
                  </span>
                  <a href="activity_view.php?id=<?= h($act['id']) ?>" class="btn-register-xs">查看</a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p>目前沒有活動資料。</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Forum -->
    <div class="forum-block">
      <div class="sec-label">
        <h2>社團論壇</h2>
        <a href="forum.php" class="see-all">查看全部 →</a>
      </div>

      <div class="forum-list">
        <?php if (!empty($posts)): ?>
          <?php foreach ($posts as $post): ?>
            <?php
              $typeLabel = ($post['type'] === 'hot') ? '熱門' : '推薦';
              $tagClass  = ($post['type'] === 'hot') ? 'red' : 'blue';
              $author    = $post['nickname'] ?: ($post['username'] ?? '匿名');
            ?>
            <a href="forum_post.php?id=<?= h($post['id']) ?>" class="forum-item">
              <div class="forum-icon"><?= $post['type'] === 'hot' ? '🔥' : '✨' ?></div>

              <div class="forum-body">
                <div class="forum-tags">
                  <span class="ftag <?= h($tagClass) ?>"><?= h($typeLabel) ?></span>
                  <span class="ftag gray"><?= h($post['club_name'] ?? '論壇') ?></span>
                </div>

                <h3><?= h($post['title']) ?></h3>
                <p><?= h(shortText($post['content'], 65)) ?></p>

                <div class="forum-foot">
                  <span class="forum-stat"><?= h(fmtDate($post['created_at'])) ?></span>
                  <span class="forum-stat"><?= h($post['comment_count']) ?> 則留言</span>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        <?php else: ?>
          <p>目前沒有論壇貼文。</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- SIDEBAR -->
  <div class="sidebar">

    <!-- Announcements -->
<div class="sidebar-card-header">
  <h3>系統公告</h3>
  <a href="ann_list.php" class="see-all ">查看全部 →</a>
</div>

  <div class="sidebar-card-body">
    <?php if (!empty($announcements)): ?>
      <?php foreach ($announcements as $ann): ?>

        <a href="ann_detail.php?id=<?= h($ann['id']) ?>" class="ann-link">
          <div class="ann-item">
            <span class="ann-tag sys">公告</span>

            <h4><?= h($ann['title']) ?></h4>

            <p><?= h(shortText($ann['content'], 32)) ?></p>

            <div class="ann-date">
              <?= h(fmtDate($ann['date'])) ?>
            </div>
          </div>
        </a>

      <?php endforeach; ?>
    <?php else: ?>
      <div class="ann-item">
        <h4>目前沒有公告</h4>
      </div>
    <?php endif; ?>
  </div>
</div>

    <!-- Hot clubs -->
    <div class="sidebar-card">
      <div class="sidebar-card-header">
        <h3>熱門社團</h3>
      </div>

      <div class="hot-clubs-list">
        <?php if (!empty($hotClubs)): ?>
          <?php foreach ($hotClubs as $i => $club): ?>
            <?php
              $rankClass = '';
              if ($i === 0) $rankClass = 'gold';
              if ($i === 1) $rankClass = 'silver';
              if ($i === 2) $rankClass = 'bronze';
            ?>
            <a href="club_detail.php?id=<?= h($club['id']) ?>" class="club-item">
              <span class="club-rank <?= h($rankClass) ?>"><?= $i + 1 ?></span>
              <div class="club-avatar"><?= h(mb_substr($club['name'], 0, 2, 'UTF-8')) ?></div>

              <div class="club-info">
                <div class="club-name"><?= h($club['name']) ?></div>
                <div class="club-sub">
                  <?= h($club['category']) ?> · <?= h($club['follower_count']) ?> 人訂閱
                </div>
              </div>

              <span class="club-arrow">›</span>
            </a>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="club-item">
            <div class="club-info">
              <div class="club-name">目前沒有社團資料</div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . "/footer.php"; ?>

</body>
</html>