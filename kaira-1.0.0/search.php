<?php session_start();
require_once "header.php"; ?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <title>搜尋結果 - 輔大社團平台</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { font-family: "Microsoft JhengHei", sans-serif; }
    .club-card img { width: 100%; height: 200px; object-fit: cover; border-radius: 8px; }
    .loading-text { color: #888; text-align: center; width: 100%; padding: 20px 0; }
    #page-search-btn {
      all: unset !important;
      cursor: pointer !important;
      background: #212529 !important;
      color: #fff !important;
      padding: 8px 20px !important;
      border-radius: 6px !important;
      white-space: nowrap !important;
      flex-shrink: 0 !important;
      font-size: 15px !important;
      line-height: 1.5 !important;
    }
    .hot-keyword-tag {
      display: inline-block;
      padding: 8px 14px;
      background: #f2f2f2;
      border-radius: 999px;
      text-decoration: none;
      color: #333;
      font-size: 14px;
      transition: background 0.2s;
    }
    .hot-keyword-tag:hover {
      background: #e0e0e0;
      color: #000;
    }
  </style>
</head>
<body>

<div class="container py-5">

  <h2 class="mb-4">搜尋社團</h2>

  <!-- 搜尋框 -->
  <div class="row mb-4">
    <div class="col-12">
      <form id="page-search-form">
        <div class="d-flex align-items-center border rounded px-3 py-2" style="max-width: 600px; background:#f8f8f8;">
          <input type="text" id="page-search-input"
            class="form-control border-0 shadow-none bg-transparent"
            placeholder="搜尋社團名稱、類別..."
            style="font-size:16px;">
          <button type="submit" id="page-search-btn">
            <i class="bi bi-search"></i> 搜尋
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- 熱門搜尋關鍵字 -->
  <div class="mb-4">
    <h6 class="text-muted mb-2">熱門搜尋：</h6>
    <ul class="list-unstyled d-flex flex-wrap gap-2 mb-0" id="hotKeywords">
      <li class="text-muted" style="font-size:14px;">載入中...</li>
    </ul>
  </div>

  <!-- 結果數量 -->
  <h6 id="result-title" class="mb-3 text-muted"></h6>

  <!-- 社團列表 -->
  <div class="row g-3" id="club-list">
    <p class="loading-text">載入中...</p>
  </div>

</div>

<!-- 搜尋彈窗 -->
<div id="searchPopup" style="background: rgba(255,255,255,0.98); position: fixed; inset: 0; z-index: 9999; display: none; overflow-y: auto;">
  <div style="max-width: 700px; margin: 80px auto; background: #fff; padding: 30px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); position: relative;">

    <button id="closeSearchPopup" style="border: none; background: none; font-size: 28px; position: absolute; top: 15px; right: 20px;">✕</button>

    <form id="header-search-form" role="search" class="form-group position-relative">
      <input type="search" id="header-search-keyword" class="form-control border-0 border-bottom" placeholder="搜尋社團...">
      <button type="submit" class="border-0 position-absolute bg-white" style="top: 10px; right: 10px;">
        <i class="bi bi-search"></i>
      </button>
    </form>

    <!-- 彈窗內熱門關鍵字 -->
    <h5 class="mt-4">熱門搜尋</h5>
    <ul class="list-unstyled d-flex flex-wrap gap-2 mb-0" id="popupHotKeywords">
      <li class="text-muted" style="font-size:14px;">載入中...</li>
    </ul>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const urlParams = new URLSearchParams(window.location.search);
  let currentKeyword = urlParams.get("keyword") || "";

  if (currentKeyword) {
    document.getElementById("page-search-input").value = currentKeyword;
  }

  // ── 載入社團列表 ──────────────────────────────────────────
  async function loadClubs(keyword = "") {
    const container = document.getElementById("club-list");
    const title     = document.getElementById("result-title");
    container.innerHTML = '<p class="loading-text">搜尋中...</p>';

    try {
      const params = new URLSearchParams();
      if (keyword) params.append("keyword", keyword);

      const res  = await fetch(`api/clubs.php?${params.toString()}`);
      const data = await res.json();

      title.textContent = keyword
        ? `「${keyword}」的搜尋結果，共 ${data.length} 筆`
        : `全部社團，共 ${data.length} 筆`;

      if (!data.length) {
        container.innerHTML = '<p class="loading-text">查無符合的社團</p>';
        return;
      }

      container.innerHTML = data.map(club => `
        <div class="col-md-4">
          <div class="club-card border rounded p-2 h-100">
            <img src="${club.image}" alt="${club.name}">
            <div class="mt-2">
              <div style="font-size:12px; color:#777;">${club.category}</div>
              <h6>${club.name}</h6>
              <p style="font-size:13px;">${club.description}</p>
            </div>
          </div>
        </div>
      `).join("");

    } catch (err) {
      container.innerHTML = `<p class="text-danger text-center">載入失敗：${err.message}</p>`;
    }
  }

  // ── 載入熱門關鍵字（通用，帶目標 list ID）────────────────
  async function loadHotKeywords(listId) {
    const list = document.getElementById(listId);
    if (!list) return;

    try {
      const res  = await fetch("api/keywords.php");
      const data = await res.json();
      list.innerHTML = "";

      if (!Array.isArray(data) || !data.length) {
        list.innerHTML = "<li class='text-muted'>目前沒有熱門搜尋</li>";
        return;
      }

      data.forEach(item => {
        const keyword = typeof item === "string" ? item : (item.keyword || item.name || "");
        if (!keyword) return;

        const li = document.createElement("li");
        li.innerHTML = `
          <a href="search.php?keyword=${encodeURIComponent(keyword)}" class="hot-keyword-tag">
            ${keyword}
          </a>`;
        list.appendChild(li);
      });

    } catch (err) {
      list.innerHTML = "<li class='text-muted'>關鍵字載入失敗</li>";
      console.error("熱門關鍵字載入失敗：", err);
    }
  }

  // ── 頁面搜尋表單送出 ──────────────────────────────────────
  document.getElementById("page-search-form").addEventListener("submit", function(e) {
    e.preventDefault();
    currentKeyword = document.getElementById("page-search-input").value.trim();
    history.pushState(null, "", currentKeyword
      ? `search.php?keyword=${encodeURIComponent(currentKeyword)}`
      : "search.php"
    );
    loadClubs(currentKeyword);
  });

  // ── Header 搜尋彈窗開關 ───────────────────────────────────
  document.getElementById("openSearch")?.addEventListener("click", function(e) {
    e.preventDefault();
    const popup = document.getElementById("searchPopup");
    popup.style.display = popup.style.display === "block" ? "none" : "block";
    if (popup.style.display === "block") {
      document.getElementById("header-search-keyword")?.focus();
    }
  });

  document.getElementById("closeSearchPopup")?.addEventListener("click", function() {
    document.getElementById("searchPopup").style.display = "none";
  });

  document.getElementById("searchPopup")?.addEventListener("click", function(e) {
    if (e.target === this) this.style.display = "none";
  });

  // ── 彈窗搜尋表單送出 ──────────────────────────────────────
  document.getElementById("header-search-form")?.addEventListener("submit", function(e) {
    e.preventDefault();
    const kw = document.getElementById("header-search-keyword").value.trim();
    if (kw) {
      window.location.href = `search.php?keyword=${encodeURIComponent(kw)}`;
    }
  });

  // ── 初始化 ────────────────────────────────────────────────
  loadClubs(currentKeyword);
  loadHotKeywords("hotKeywords");       // 頁面上方熱門標籤
  loadHotKeywords("popupHotKeywords");  // 彈窗內熱門標籤
</script>

</body>
</html>