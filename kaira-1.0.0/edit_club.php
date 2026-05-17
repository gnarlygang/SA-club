<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "api/db.php";
$error = "";

$club_id = isset($_GET["id"]) ? (int)$_GET["id"] : (isset($_POST["club_id"]) ? (int)$_POST["club_id"] : 0);

try {
    // 所有可用標籤
    $all_tags_stmt = $pdo->query("SELECT DISTINCT tag_name FROM club_tags ORDER BY tag_name ASC");
    $all_tags = $all_tags_stmt->fetchAll(PDO::FETCH_COLUMN);

    // 處理 POST 送出
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $description   = trim($_POST["description"]  ?? "");
        $image         = trim($_POST["image"]         ?? "");
        $email         = trim($_POST["email"]         ?? "");
        $short_name    = trim($_POST["short_name"]    ?? "");
        $keywords      = trim($_POST["keywords"]      ?? "");
        $selected_tags = $_POST["tags"] ?? [];

        $selected_tags = array_values(array_intersect($selected_tags, $all_tags));

        if ($description === "") {
            $error = "社團介紹不可為空。";
        } elseif ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "信箱格式不正確。";
        } elseif (count($selected_tags) < 1) {
            $error = "請至少選擇 1 個標籤。";
        } elseif (count($selected_tags) > 3) {
            $error = "最多只能選擇 3 個標籤。";
        } else {
            $stmt = $pdo->prepare("UPDATE clubs SET description=:description, image=:image, short_name=:short_name, keywords=:keywords WHERE id=:id AND user_id=:uid");
            $stmt->execute([
                ":description" => $description,
                ":image"       => $image,
                ":short_name"  => $short_name,
                ":keywords"    => $keywords,
                ":id"          => $club_id,
                ":uid"         => $_SESSION["user_id"] ?? null,
            ]);

            $stmt = $pdo->prepare("UPDATE users SET email=:email WHERE user_id=:uid");
            $stmt->execute([":email" => $email, ":uid" => $_SESSION["user_id"] ?? null]);

            $pdo->prepare("DELETE FROM club_tags WHERE club_id=?")->execute([$club_id]);
            $ins = $pdo->prepare("INSERT INTO club_tags (club_id, tag_name) VALUES (?, ?)");
            foreach ($selected_tags as $tag) $ins->execute([$club_id, $tag]);

            header("Location: create_club.php");
            exit;
        }
    }

    $stmt = $pdo->prepare("SELECT c.*, u.email FROM clubs c LEFT JOIN users u ON u.user_id=c.user_id WHERE c.id=:id AND c.user_id=:uid LIMIT 1");
    $stmt->execute([":id" => $club_id, ":uid" => $_SESSION["user_id"] ?? null]);
    $club = $stmt->fetch(PDO::FETCH_ASSOC);

    $cur_tags_stmt = $pdo->prepare("SELECT tag_name FROM club_tags WHERE club_id=? ORDER BY id");
    $cur_tags_stmt->execute([$club_id]);
    $current_tags = $cur_tags_stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    die("資料庫連線失敗：" . $e->getMessage());
}

$form_description = isset($_POST["description"]) ? $_POST["description"] : ($club["description"] ?? "");
$form_image       = isset($_POST["image"])       ? $_POST["image"]       : ($club["image"]       ?? "");
$form_email       = isset($_POST["email"])       ? $_POST["email"]       : ($club["email"]       ?? "");
$form_short_name  = isset($_POST["short_name"])  ? $_POST["short_name"]  : ($club["short_name"]  ?? "");
$form_keywords    = isset($_POST["keywords"])    ? $_POST["keywords"]    : ($club["keywords"]    ?? "");
$form_tags        = isset($_POST["tags"])        ? $_POST["tags"]        : $current_tags;

require_once "header.php";
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>編輯社團資料 — 輔大社團平台</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;600;700&family=Microsoft+JhengHei&display=swap" rel="stylesheet">
  <style>
    :root {
      --card-shadow: 0 8px 32px rgba(60,80,120,0.10);
      --input-border: #c8d0dc;
      --btn-bg: #2d2d2d;
      --btn-hover: #444;
      --error-color: #c0392b;
      --label-color: #555;
    }
    * { box-sizing: border-box; }
    body { font-family: "Microsoft JhengHei", sans-serif; background: #eef1f5; min-height: 100vh; display: flex; flex-direction: column; }
    .page-shell { flex: 1; display: flex; align-items: stretch; }
    .main-content { flex: 1; display: flex; align-items: flex-start; justify-content: center; padding: 48px 24px 60px; overflow-y: auto; }
    .edit-card { width: 100%; max-width: 780px; background: #fff; border-radius: 18px; box-shadow: var(--card-shadow); overflow: hidden; }
    .edit-card-header { background: #2d3a4a; color: #fff; padding: 36px 48px 28px; text-align: center; }
    .edit-card-header .logo-text { font-family: "Noto Serif TC", serif; font-size: 22px; font-weight: 700; letter-spacing: 3px; margin-bottom: 6px; }
    .edit-card-header .subtitle { font-size: 13px; opacity: 0.72; letter-spacing: 1px; }
    .preview-wrap { width: 100%; height: 260px; overflow: hidden; background: #dde2ea; position: relative; }
    .preview-wrap img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .preview-placeholder { width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #aab; gap: 8px; }
    .preview-placeholder i { font-size: 48px; }
    .preview-placeholder span { font-size: 13px; }
    .edit-card-body { padding: 36px 48px 44px; }
    .readonly-row { display: flex; align-items: center; gap: 14px; padding: 12px 0; border-bottom: 1px solid #eef1f5; margin-bottom: 4px; }
    .readonly-icon { width: 36px; height: 36px; border-radius: 10px; background: #f0f3f7; display: flex; align-items: center; justify-content: center; color: #6e8ab0; font-size: 15px; flex-shrink: 0; }
    .readonly-label { font-size: 11px; color: #9aa; letter-spacing: 0.5px; }
    .readonly-value { font-size: 14px; font-weight: 600; color: #2d3a4a; }
    .form-section { margin-top: 24px; }
    .form-label { font-size: 13px; font-weight: 600; color: var(--label-color); margin-bottom: 6px; letter-spacing: 0.5px; }
    .form-control { border: 1.5px solid var(--input-border); border-radius: 8px; padding: 10px 14px; font-size: 14px; transition: border-color 0.2s, box-shadow 0.2s; }
    .form-control:focus { border-color: #6e8ab0; box-shadow: 0 0 0 3px rgba(110,138,176,0.15); outline: none; }
    textarea.form-control { resize: vertical; min-height: 110px; }
    .input-group-text { background: #f4f6f9; border: 1.5px solid var(--input-border); border-right: none; border-radius: 8px 0 0 8px; color: #7a8a9a; }
    .input-group .form-control { border-left: none; border-radius: 0 8px 8px 0; }
    .input-group:focus-within .input-group-text { border-color: #6e8ab0; }
    .hint-text { font-size: 11px; color: #9aa; margin-top: 5px; }
    .alert-error { background: #fdf0ef; border: 1px solid #f0c4c0; color: var(--error-color); border-radius: 8px; padding: 10px 14px; font-size: 13px; display: flex; align-items: center; gap: 8px; margin-bottom: 20px; }
    .tag-picker-wrap { display: flex; flex-wrap: wrap; gap: 8px; padding: 14px; background: #f8fafc; border: 1.5px solid var(--input-border); border-radius: 10px; max-height: 220px; overflow-y: auto; }
    .tag-chip input[type="checkbox"] { display: none; }
    .tag-chip label { display: inline-block; padding: 5px 13px; border-radius: 999px; font-size: 12.5px; font-weight: 500; border: 1.5px solid #c8d0dc; background: #fff; color: #556; cursor: pointer; transition: all .15s; user-select: none; }
    .tag-chip label:hover { border-color: #6e8ab0; background: #eef3fa; color: #2d3a4a; }
    .tag-chip input:checked + label { background: #2d3a4a; border-color: #2d3a4a; color: #fff; }
    .tag-chip input:disabled + label { opacity: .45; cursor: not-allowed; }
    .tag-count-hint { font-size: 12px; color: #9aa; margin-top: 6px; }
    .tag-count-hint.warn { color: #c0392b; font-weight: 600; }
    .divider { border: none; border-top: 1px solid #e8ecf0; margin: 28px 0; }
    .btn-save { background: var(--btn-bg); color: #fff; border: none; border-radius: 8px; padding: 13px; font-size: 15px; font-weight: 600; letter-spacing: 1px; width: 100%; transition: background 0.2s, transform 0.1s; cursor: pointer; }
    .btn-save:hover { background: var(--btn-hover); transform: translateY(-1px); }
    .btn-cancel { display: block; text-align: center; margin-top: 14px; font-size: 13px; color: #778; text-decoration: none; }
    .btn-cancel:hover { color: #3a3a3a; }
  </style>
</head>
<body>

<div class="page-shell">
  <main class="main-content">
    <div class="edit-card">

      <div class="edit-card-header">
        <div class="logo-text">編輯社團資料</div>
        <div class="subtitle">天主教輔仁大學 社團平台</div>
      </div>

      <div class="preview-wrap" id="previewWrap">
        <?php if (!empty($form_image)): ?>
          <img src="<?= htmlspecialchars($form_image) ?>" alt="社團圖片預覽" onerror="showPlaceholder()">
        <?php else: ?>
          <div class="preview-placeholder">
            <i class="bi bi-image"></i>
            <span>輸入圖片網址後將自動預覽</span>
          </div>
        <?php endif; ?>
      </div>

      <div class="edit-card-body">

        <?php if ($club): ?>
          <div class="readonly-row">
            <div class="readonly-icon"><i class="bi bi-building"></i></div>
            <div>
              <div class="readonly-label">社團名稱（不可修改）</div>
              <div class="readonly-value"><?= htmlspecialchars($club["name"]) ?></div>
            </div>
          </div>
          <div class="readonly-row">
            <div class="readonly-icon"><i class="bi bi-grid"></i></div>
            <div>
              <div class="readonly-label">社團類別（不可修改）</div>
              <div class="readonly-value"><?= htmlspecialchars($club["category"]) ?></div>
            </div>
          </div>
        <?php endif; ?>

        <div class="form-section">

          <?php if ($error): ?>
            <div class="alert-error">
              <i class="bi bi-exclamation-circle-fill"></i>
              <?= htmlspecialchars($error) ?>
            </div>
          <?php endif; ?>

          <form method="POST" action="edit_club.php?id=<?= $club_id ?>" autocomplete="off">
            <input type="hidden" name="club_id" value="<?= $club_id ?>">

            <!-- 圖片網址 -->
            <div class="mb-4">
              <label class="form-label">社團圖片（網址）</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-image"></i></span>
                <input type="url" class="form-control" name="image" id="imageInput"
                       placeholder="請貼上圖片網址（https://...）"
                       value="<?= htmlspecialchars($form_image) ?>">
              </div>
              <div class="hint-text">可留空，建議使用 Unsplash 等公開圖片連結。</div>
            </div>

            <!-- 社團介紹 -->
            <div class="mb-4">
              <label class="form-label">社團介紹 <span style="color:#c0392b;">*</span></label>
              <div class="input-group align-items-start">
                <span class="input-group-text" style="border-radius:8px 0 0 8px; padding-top:11px;">
                  <i class="bi bi-text-paragraph"></i>
                </span>
                <textarea class="form-control" name="description" placeholder="請輸入社團介紹內容" required><?= htmlspecialchars($form_description) ?></textarea>
              </div>
              <div class="hint-text">社團介紹為必填，將顯示於社團資料頁面。</div>
            </div>

            <!-- 社團簡稱 -->
            <div class="mb-4">
              <label class="form-label">社團簡稱</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-badge-tm"></i></span>
                <input type="text" class="form-control" name="short_name"
                       placeholder="例如：國樂社、天文社（可留空）"
                       maxlength="20"
                       value="<?= htmlspecialchars($form_short_name) ?>">
              </div>
              <div class="hint-text">社團的常用簡稱，最多 20 字，可留空。搜尋時也可用簡稱找到。</div>
            </div>

            <!-- 關鍵字 -->
            <div class="mb-4">
              <label class="form-label">搜尋關鍵字</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-tags"></i></span>
                <input type="text" class="form-control" name="keywords"
                       placeholder="例如：舞蹈、韓流、表演、街舞（用逗號分隔）"
                       value="<?= htmlspecialchars($form_keywords) ?>">
              </div>
              <div class="hint-text">讓使用者輸入相關詞彙時能找到你的社團，多個關鍵字請用逗號「,」分隔。</div>
            </div>

            <!-- 聯絡信箱 -->
            <div class="mb-4">
              <label class="form-label">聯絡信箱</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input type="email" class="form-control" name="email"
                       placeholder="請輸入社團聯絡信箱"
                       value="<?= htmlspecialchars($form_email) ?>">
              </div>
              <div class="hint-text">信箱將顯示於社團資料頁面，可留空。</div>
            </div>

            <!-- 標籤選擇 -->
            <div class="mb-4">
              <label class="form-label">社團標籤 <span style="color:#c0392b;">*</span></label>
              <div class="tag-picker-wrap" id="tagPickerWrap">
                <?php foreach ($all_tags as $tag):
                  $checked = in_array($tag, $form_tags) ? 'checked' : '';
                ?>
                <span class="tag-chip">
                  <input type="checkbox" name="tags[]"
                         id="tag_<?= htmlspecialchars($tag) ?>"
                         value="<?= htmlspecialchars($tag) ?>"
                         <?= $checked ?>
                         onchange="onTagChange(this)">
                  <label for="tag_<?= htmlspecialchars($tag) ?>"><?= htmlspecialchars($tag) ?></label>
                </span>
                <?php endforeach; ?>
              </div>
              <div class="tag-count-hint" id="tagHint">
                已選擇 <strong id="tagCountNum"><?= count($form_tags) ?></strong> / 3 個標籤（至少 1 個，最多 3 個）
              </div>
            </div>

            <hr class="divider">

            <button type="submit" class="btn-save">
              <i class="bi bi-check-circle me-2"></i>儲存變更
            </button>
          </form>

          <a href="create_club.php" class="btn-cancel">
            <i class="bi bi-arrow-left me-1"></i>取消，返回社團資料
          </a>

        </div>
      </div>
    </div>
  </main>
</div>

<?php require "footer.php"; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function onTagChange(checkbox) {
  const allChecked = document.querySelectorAll('#tagPickerWrap input[type="checkbox"]:checked');
  const count = allChecked.length;
  document.getElementById('tagCountNum').textContent = count;
  const hintEl = document.getElementById('tagHint');
  if (count >= 3) {
    document.querySelectorAll('#tagPickerWrap input[type="checkbox"]:not(:checked)').forEach(cb => { cb.disabled = true; });
    hintEl.className = 'tag-count-hint warn';
  } else {
    document.querySelectorAll('#tagPickerWrap input[type="checkbox"]').forEach(cb => { cb.disabled = false; });
    hintEl.className = 'tag-count-hint';
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const checked = document.querySelectorAll('#tagPickerWrap input[type="checkbox"]:checked');
  if (checked.length >= 3) {
    document.querySelectorAll('#tagPickerWrap input[type="checkbox"]:not(:checked)').forEach(cb => { cb.disabled = true; });
    document.getElementById('tagHint').className = 'tag-count-hint warn';
  }
});

const imageInput = document.getElementById("imageInput");
const previewWrap = document.getElementById("previewWrap");

function showPlaceholder() {
  previewWrap.innerHTML = `<div class="preview-placeholder"><i class="bi bi-image"></i><span>圖片無法載入，請確認網址是否正確</span></div>`;
}

function updatePreview(url) {
  if (!url) {
    previewWrap.innerHTML = `<div class="preview-placeholder"><i class="bi bi-image"></i><span>輸入圖片網址後將自動預覽</span></div>`;
    return;
  }
  previewWrap.innerHTML = `<img src="${url}" alt="社團圖片預覽" onerror="showPlaceholder()">`;
}

let debounceTimer;
imageInput.addEventListener("input", function () {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => updatePreview(this.value.trim()), 800);
});
</script>
</body>
</html>