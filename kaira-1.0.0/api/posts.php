<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . "/db.php";

header("Content-Type: application/json; charset=utf-8");

$type = $_GET["type"] ?? "recommended";
$user_id = $_SESSION["user_id"] ?? 0;
$limit = 6;

function output_json($data) {
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function default_img() {
  return "https://images.unsplash.com/photo-1517048676732-d65bc937f952?auto=format&fit=crop&w=800&q=80";
}

function format_date_text($value) {
  if (empty($value)) return "";
  $time = strtotime($value);
  return $time ? date("Y-m-d", $time) : $value;
}

function get_legacy_posts($pdo, $type, $limit) {
  $stmt = $pdo->prepare("\n    SELECT\n      id,\n      club_name,\n      title,\n      description,\n      image,\n      date,\n      0 AS score,\n      CASE WHEN type = 'recommended' THEN '系統預設推薦' ELSE '熱門貼文' END AS reason\n    FROM posts\n    WHERE type = ?\n    ORDER BY date DESC\n    LIMIT ?\n  ");
  $stmt->bindValue(1, $type, PDO::PARAM_STR);
  $stmt->bindValue(2, $limit, PDO::PARAM_INT);
  $stmt->execute();

  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  return array_map(function($r) {
    return [
      "id" => (int)$r["id"],
      "club_id" => null,
      "club_name" => $r["club_name"] ?? "社團貼文",
      "category" => "",
      "title" => $r["title"] ?? "",
      "description" => $r["description"] ?? "",
      "image" => !empty($r["image"]) ? $r["image"] : default_img(),
      "date" => format_date_text($r["date"] ?? ""),
      "score" => (int)($r["score"] ?? 0),
      "reason" => $r["reason"] ?? "推薦貼文"
    ];
  }, $rows);
}

function get_user_preference($pdo, $user_id) {
  $result = [
    "club_ids" => [],
    "categories" => []
  ];

  if (!$user_id) return $result;

  // 使用者訂閱的社團與分類
  $stmt = $pdo->prepare("\n    SELECT c.id, c.category\n    FROM subscriptions s\n    JOIN clubs c ON c.id = s.club_id\n    WHERE s.user_id = ?\n  ");
  $stmt->execute([$user_id]);
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (!empty($r["id"])) $result["club_ids"][] = (int)$r["id"];
    if (!empty($r["category"])) $result["categories"][] = $r["category"];
  }

  // 使用者收藏過的活動分類
  $stmt = $pdo->prepare("\n    SELECT DISTINCT c.category\n    FROM bookmarks b\n    JOIN activities a ON a.id = b.activity_id\n    LEFT JOIN clubs c\n      ON c.user_id = a.user_id\n      OR c.name = a.organizer\n      OR CONCAT('輔大', c.name) = a.organizer\n    WHERE b.user_id = ?\n      AND c.category IS NOT NULL\n      AND c.category <> ''\n  ");
  $stmt->execute([$user_id]);
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (!empty($r["category"])) $result["categories"][] = $r["category"];
  }

  $result["club_ids"] = array_values(array_unique($result["club_ids"]));
  $result["categories"] = array_values(array_unique($result["categories"]));
  return $result;
}

function get_activity_rows($pdo, $limit) {
  // 用子查詢抓社團資料，避免 JOIN 重複或欄位對不到造成爆掉
  $stmt = $pdo->prepare("\n    SELECT\n      a.id,\n      a.title,\n      a.description,\n      a.event_start,\n      a.created_at,\n      a.organizer,\n\n      COALESCE(\n        (SELECT c.id FROM clubs c\n         WHERE c.user_id = a.user_id OR c.name = a.organizer OR CONCAT('輔大', c.name) = a.organizer\n         ORDER BY CASE WHEN c.name = a.organizer THEN 1 WHEN CONCAT('輔大', c.name) = a.organizer THEN 2 ELSE 3 END\n         LIMIT 1),\n        0\n      ) AS club_id,\n\n      COALESCE(\n        (SELECT c.name FROM clubs c\n         WHERE c.user_id = a.user_id OR c.name = a.organizer OR CONCAT('輔大', c.name) = a.organizer\n         ORDER BY CASE WHEN c.name = a.organizer THEN 1 WHEN CONCAT('輔大', c.name) = a.organizer THEN 2 ELSE 3 END\n         LIMIT 1),\n        a.organizer\n      ) AS club_name,\n\n      COALESCE(\n        (SELECT c.category FROM clubs c\n         WHERE c.user_id = a.user_id OR c.name = a.organizer OR CONCAT('輔大', c.name) = a.organizer\n         ORDER BY CASE WHEN c.name = a.organizer THEN 1 WHEN CONCAT('輔大', c.name) = a.organizer THEN 2 ELSE 3 END\n         LIMIT 1),\n        ''\n      ) AS category,\n\n      COALESCE(\n        (SELECT c.image FROM clubs c\n         WHERE c.user_id = a.user_id OR c.name = a.organizer OR CONCAT('輔大', c.name) = a.organizer\n         ORDER BY CASE WHEN c.name = a.organizer THEN 1 WHEN CONCAT('輔大', c.name) = a.organizer THEN 2 ELSE 3 END\n         LIMIT 1),\n        ''\n      ) AS club_image,\n\n      (SELECT COUNT(*) FROM bookmarks b WHERE b.activity_id = a.id) AS bookmark_count\n\n    FROM activities a\n    ORDER BY a.created_at DESC, a.event_start ASC\n    LIMIT ?\n  ");
  $stmt->bindValue(1, max($limit, 20), PDO::PARAM_INT);
  $stmt->execute();
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function build_recommendations($pdo, $user_id, $limit) {
  $pref = get_user_preference($pdo, $user_id);
  $rows = get_activity_rows($pdo, $limit);
  $data = [];

  foreach ($rows as $r) {
    $club_id = (int)($r["club_id"] ?? 0);
    $category = $r["category"] ?? "";
    $bookmark_count = (int)($r["bookmark_count"] ?? 0);
    $score = 0;
    $reason = "熱門與最新推薦";

    if ($user_id && $club_id && in_array($club_id, $pref["club_ids"], true)) {
      $score += 80;
      $reason = "來自你訂閱的社團";
    } elseif ($user_id && $category !== "" && in_array($category, $pref["categories"], true)) {
      $score += 50;
      $reason = "因為你喜歡同類型社團";
    }

    $score += min($bookmark_count * 5, 30);

    if (!empty($r["created_at"])) {
      $created = strtotime($r["created_at"]);
      if ($created && $created >= strtotime("-14 days")) {
        $score += 15;
      } elseif ($created && $created >= strtotime("-30 days")) {
        $score += 8;
      }
    }

    if (!$user_id) {
      $reason = "新用戶熱門推薦";
    }

    $data[] = [
      "id" => (int)$r["id"],
      "club_id" => $club_id ?: null,
      "club_name" => $r["club_name"] ?: ($r["organizer"] ?? "社團活動"),
      "category" => $category,
      "title" => $r["title"] ?? "",
      "description" => $r["description"] ?? "",
      "image" => !empty($r["club_image"]) ? $r["club_image"] : default_img(),
      "date" => format_date_text($r["event_start"] ?? $r["created_at"] ?? ""),
      "score" => $score,
      "reason" => $reason
    ];
  }

  usort($data, function($a, $b) {
    if ($a["score"] === $b["score"]) {
      return strcmp($b["date"], $a["date"]);
    }
    return $b["score"] <=> $a["score"];
  });

  return array_slice($data, 0, $limit);
}

try {
  if ($type === "recommended") {
    $data = build_recommendations($pdo, $user_id, $limit);

    if (count($data) === 0) {
      $data = get_legacy_posts($pdo, "recommended", $limit);
    }
    if (count($data) === 0) {
      $data = get_legacy_posts($pdo, "hot", $limit);
    }

    output_json($data);
  }

  if ($type === "hot") {
    $data = build_recommendations($pdo, 0, $limit);
    if (count($data) === 0) {
      $data = get_legacy_posts($pdo, "hot", $limit);
    }
    output_json($data);
  }

  output_json([]);

} catch (Throwable $e) {
  // 不讓首頁整塊壞掉：推薦出錯時改抓 posts 舊資料
  try {
    $fallback = get_legacy_posts($pdo, $type === "hot" ? "hot" : "recommended", $limit);
    if (count($fallback) === 0) {
      $fallback = get_legacy_posts($pdo, "hot", $limit);
    }
    output_json($fallback);
  } catch (Throwable $e2) {
    output_json([
      "error" => true,
      "message" => $e->getMessage()
    ]);
  }
}
