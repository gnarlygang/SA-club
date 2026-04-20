<?php session_start();
require_once "header.php"; ?>
<!DOCTYPE html>
<html lang="zh-Hant">

<head>
  <title>輔大社團平台</title>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    body {
      font-family: "Microsoft JhengHei", "微軟正黑體", sans-serif;
    }

    .search-popup {
      background: rgba(255, 255, 255, 0.98);
      position: fixed;
      inset: 0;
      z-index: 9999;
      display: none;
      overflow-y: auto;
    }

    .search-popup.active {
      display: block;
    }

    .search-popup-container {
      max-width: 700px;
      margin: 80px auto;
      background: #fff;
      padding: 30px;
      border-radius: 16px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
      position: relative;
    }

    .close-search {
      border: none;
      background: none;
      font-size: 28px;
      position: absolute;
      top: 15px;
      right: 20px;
    }

    .cat-list {
      list-style: none;
      padding: 0;
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }

    .cat-list-item a {
      display: inline-block;
      padding: 8px 14px;
      background: #f2f2f2;
      border-radius: 999px;
      text-decoration: none;
      color: #333;
    }

    .feature-box {
      height: 100%;
    }

    .feature-icon {
      width: 50px;
      height: 50px;
      margin: 0 auto 20px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .feature-icon i {
      font-size: 38px;
      line-height: 1;
      color: #8f8f8f;
    }

    .feature-box p {
      color: #9a9a9a;
      line-height: 1.8;
      max-width: 260px;
      margin: 0 auto;
    }

    .club-card img,
    .post-card img {
      width: 100%;
      object-fit: cover;
      border-radius: 8px;
    }

    .club-card img {
      height: 220px;
    }

    .post-card img {
      height: 220px;
    }

    .category-btns .btn {
      margin: 4px;
    }

    .footer-custom {
      background-color: #afbac7;
      color: #333;
    }

    .loading-text {
      color: #888;
      text-align: center;
      width: 100%;
      padding: 20px 0;
    }
  </style>
</head>

<body class="homepage">

  <!-- 搜尋彈窗 -->
  <div class="search-popup" id="searchPopup">
    <div class="search-popup-container">

      <form id="search-form-api" role="search" method="get" class="form-group position-relative" action="">
        <input
          type="search"
          id="search-keyword"
          class="form-control border-0 border-bottom"
          placeholder="搜尋"
        />
        <button type="submit"
          class="search-submit border-0 position-absolute bg-white"
          style="top: 10px; right: 10px;">
          <i class="bi bi-search"></i>
        </button>
      </form>

      <h5 class="mt-4">熱門搜尋</h5>
      <ul class="cat-list d-flex flex-wrap gap-2 list-unstyled mb-0" id="hotKeywords"></ul>

    </div>
  </div>


  <!-- 系統公告 -->
  <section id="billboard" class="bg-light py-5">
    <div class="container" style="max-width: 900px;">
      <div class="row justify-content-center">
        <h2 class="text-center mt-3 mb-4" style="font-size: 32px;">系統公告</h2>
        <div class="col-md-12" id="announcement-list">
          <p class="loading-text">載入中...</p>
        </div>
      </div>
    </div>
  </section>

  <!-- 熱門貼文 -->
  <section id="hot-post-section" class="py-5">
    <div class="container">
      <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 mb-3">
        <h4 class="text-uppercase">熱門貼文</h4>
        <a href="#" class="btn-link">查看全部</a>
      </div>

      <div class="row g-3" id="hot-post-list">
        <p class="loading-text">載入中...</p>
      </div>
    </div>
  </section>

  <!-- 功能特色 -->
  <section class="features py-5">
    <div class="container">
      <div class="row">

        <div class="col-md-3 text-center">
          <div class="py-5 feature-box">
            <div class="feature-icon">
              <i class="bi bi-search"></i>
            </div>
            <h4 class="my-3">智能檢索</h4>
            <p>根據興趣標籤快速篩選，精準找到全校最適合你的特色社團。</p>
          </div>
        </div>

        <div class="col-md-3 text-center">
          <div class="py-5 feature-box">
            <div class="feature-icon">
              <i class="bi bi-calendar-event"></i>
            </div>
            <h4 class="my-3">活動報名</h4>
            <p>即時掌握各社團體驗課與迎新動態，一鍵預約不漏接任何精彩瞬間。</p>
          </div>
        </div>

        <div class="col-md-3 text-center">
          <div class="py-5 feature-box">
            <div class="feature-icon">
              <i class="bi bi-people"></i>
            </div>
            <h4 class="my-3">多元交流</h4>
            <p>結交志同道合的好友，跨系人脈擴展，讓大學生活活出精彩回憶。</p>
          </div>
        </div>

        <div class="col-md-3 text-center">
          <div class="py-5 feature-box">
            <div class="feature-icon">
              <i class="bi bi-award"></i>
            </div>
            <h4 class="my-3">成果展示</h4>
            <p>匯整期末大型展演與競賽資訊，記錄你在社團發光發熱的每一刻。</p>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- 你可能感興趣 -->
  <section id="related-posts" class="py-5">
    <div class="container">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="text-uppercase">你可能感興趣的貼文</h4>
        <a href="#" class="btn-link">查看全部</a>
      </div>

      <div class="row g-3" id="recommended-post-list">
        <p class="loading-text">載入中...</p>
      </div>
    </div>
  </section>

  <!-- 意見回饋 -->
  <section id="feedback-section" style="background-color: #eaeef2;">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-md-8 py-4">

          <div class="text-center mb-3">
            <h3 class="section-title text-uppercase">意見回饋</h3>
            <p style="font-size: 14px; color: #666;">
              若您對平台有任何建議或問題，歡迎留下您的意見。
            </p>
          </div>

          <form id="feedback-form" class="d-flex flex-column gap-2">
            <input type="email" name="email" placeholder="您的Email" class="form-control" required>
            <textarea name="message" rows="3" placeholder="請輸入您的意見或建議..." class="form-control" required></textarea>
            <button type="submit" class="btn btn-dark text-uppercase">送出意見</button>
          </form>

          <div id="feedback-result" class="mt-3 text-center"></div>

        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer id="footer" class="mt-5" style="background-color: #afbac7; color: #333333; font-family: 'Microsoft JhengHei', '微軟正黑體', sans-serif;">
    <div class="container">
      <div class="row d-flex flex-wrap justify-content-between py-5" style="border-bottom: 1px solid rgba(0,0,0,0.1);">

        <div class="col-md-4 col-sm-6 mb-4">
          <div class="footer-menu">
            <h5 class="widget-title mb-4 pb-2" style="border-bottom: 2px solid #555; width: fit-content; font-weight: bold;">校園連結</h5>
            <ul class="menu-list list-unstyled fs-6">
              <li class="py-1"><a href="https://www.fju.edu.tw/" target="_blank" class="text-dark text-decoration-none">＞ 輔大全球資訊網</a></li>
              <li class="py-1"><a href="https://www.fju.edu.tw/article.jsp?articleID=34" target="_blank" class="text-dark text-decoration-none">＞ 公文自動化 & ODF</a></li>
              <li class="py-1"><a href="https://www.fju.edu.tw/article.jsp?articleID=22" target="_blank" class="text-dark text-decoration-none">＞ 高教深耕計畫 & 開放式課程</a></li>
              <li class="py-1"><a href="https://www.fju.edu.tw/article.jsp?articleID=21" target="_blank" class="text-dark text-decoration-none">＞ WebMail & LDAP</a></li>
              <li class="py-1"><a href="https://www.fju.edu.tw/resource.jsp?labelID=27" target="_blank" class="text-dark text-decoration-none">＞ 職涯服務 & 學生會</a></li>
            </ul>
          </div>
        </div>

        <div class="col-md-4 col-sm-6 mb-4">
          <div class="footer-menu">
            <h5 class="widget-title mb-4 pb-2" style="border-bottom: 2px solid #555; width: fit-content; font-weight: bold;">公告資訊</h5>
            <ul class="menu-list list-unstyled fs-6">
              <li class="py-1"><a href="https://control.fju.edu.tw/#&panel1-1" target="_blank" class="text-dark text-decoration-none">＞ 內部控制專區</a></li>
              <li class="py-1"><a href="https://www.fju.edu.tw/fee/1_1.html" target="_blank" class="text-dark text-decoration-none">＞ 校務財務資訊專區</a></li>
              <li class="py-1"><a href="https://www.fju.edu.tw/article.jsp?articleID=20" target="_blank" class="text-dark text-decoration-none">＞ 政府公告專區</a></li>
              <li class="py-1"><a href="http://life.dsa.fju.edu.tw/resource.jsp?labelID=35" target="_blank" class="text-dark text-decoration-none">＞ 獎助學金</a></li>
              <li class="py-1"><a href="http://www.secretariat.fju.edu.tw/article.jsp?articleID=8" target="_blank" class="text-dark text-decoration-none">＞ 行事曆</a></li>
            </ul>
          </div>
        </div>

        <div class="col-md-4 col-sm-6 mb-4">
          <div class="footer-menu">
            <h5 class="widget-title mb-4 pb-2" style="border-bottom: 2px solid #555; width: fit-content; font-weight: bold;">快速連結</h5>
            <ul class="menu-list list-unstyled fs-6">
              <li class="py-1"><a href="http://irb.rdo.fju.edu.tw/" target="_blank" class="text-dark text-decoration-none">＞ 人體研究IRB</a></li>
              <li class="py-1"><a href="https://researchinfo.fju.edu.tw/" target="_blank" class="text-dark text-decoration-none">＞ 學術統計資料網</a></li>
              <li class="py-1"><a href="http://activity.dsa.fju.edu.tw/ActivityList.jsp" target="_blank" class="text-dark text-decoration-none">＞ 活動報名系統</a></li>
              <li class="py-1"><a href="https://www.fju.edu.tw/article.jsp?articleID=5" target="_blank" class="text-dark text-decoration-none">＞ 輔大媒體家族</a></li>
              <li class="py-1"><a href="https://cre.fju.edu.tw/" target="_blank" class="text-dark text-decoration-none">＞ 研究倫理中心</a></li>
            </ul>
          </div>
        </div>
      </div>

      <div class="row py-5 align-items-start">
        <div class="col-md-4 mb-4 text-center text-md-start">
          <img src="https://www.fju.edu.tw/showImg/focus/focus2293.jpg" alt="輔大焦點新聞" class="img-fluid rounded shadow-sm" style="border: 4px solid #fff; object-fit: cover; width: 100%; max-width: 350px; height: 180px;">
        </div>

        <div class="col-md-4 mb-4">
          <p class="h5 mb-3" style="font-weight: bold;">天主教輔仁大學</p>
          <p class="mb-2">242062 新北市新莊區中正路510號</p>
          <div class="small mt-3 pt-2" style="border-top: 1px solid rgba(0,0,0,0.1);">
            <span class="d-block mb-1" style="color: #555;">Member of:</span>
            <div class="lh-lg">
              <a href="https://www.fiuc.org/" target="_blank" class="text-dark text-decoration-underline me-1">IFCU</a>,
              <a href="https://www.g-c-e.org/" target="_blank" class="text-dark text-decoration-underline me-1">GCE</a>,
              <a href="https://unitedboard.org/" target="_blank" class="text-dark text-decoration-underline me-1">United Board</a>,
              <a href="http://aseaccu.fju.edu.tw/" target="_blank" class="text-dark text-decoration-underline me-1">ASEACCU</a>
            </div>
          </div>
        </div>

        <div class="col-md-4 mb-4 text-md-end">
          <p class="mb-2">電話：<a href="tel:+886229052000" class="text-dark text-decoration-none">(02) 2905-2000</a></p>
          <p class="mb-2">信箱：<a href="mailto:pubwww@mail.fju.edu.tw" class="text-dark text-decoration-none">pubwww@mail.fju.edu.tw</a></p>
        </div>
      </div>
    </div>

    <div class="py-3" style="background-color: rgba(0,0,0,0.05);">
      <div class="container text-center">
        <p class="small mb-0 opacity-75" style="color: #444;">
          天主教輔仁大學 © 2014-2026 版權所有 |
          <a href="https://www.fju.edu.tw/contact.jsp" target="_blank" class="text-dark mx-2">業務單位聯絡方式</a> |
          <a href="https://www.fju.edu.tw/privacy.jsp" target="_blank" class="text-dark mx-2">隱私權聲明</a>
        </p>
      </div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    let currentCategory = "";
    let currentKeyword = "";

    async function fetchJson(url) {
      const res = await fetch(url);
      const text = await res.text();

      console.log("API:", url);
      console.log("Response text:", text);

      if (!res.ok) {
        throw new Error(`HTTP ${res.status} - ${text}`);
      }

      try {
        return JSON.parse(text);
      } catch (e) {
        throw new Error(`不是合法 JSON：${text}`);
      }
    }

    async function loadAnnouncements() {
      const container = document.getElementById("announcement-list");

      try {
        const data = await fetchJson("api/announcements.php");

        if (!data.length) {
          container.innerHTML = '<p class="loading-text">目前沒有公告</p>';
          return;
        }

        container.innerHTML = data.map(item => `
          <div class="p-3 mb-2 border rounded bg-white">
            <h6 class="mb-1">${item.title}</h6>
            <p class="mb-1" style="font-size: 13px;">${item.content}</p>
            <small style="font-size: 12px;">${item.date}</small>
          </div>
        `).join("");
      } catch (error) {
        container.innerHTML = `<p class="text-danger text-center">公告載入失敗<br>${error.message}</p>`;
        console.error(error);
      }
    }

    async function loadPosts(type, containerId) {
      const container = document.getElementById(containerId);

      try {
        const data = await fetchJson(`api/posts.php?type=${type}`);

        if (!data.length) {
          container.innerHTML = '<p class="loading-text">目前沒有資料</p>';
          return;
        }

        container.innerHTML = data.map(post => `
          <div class="col-md-4">
            <article class="post-card border rounded p-2 h-100">
              <img src="${post.image}" alt="${post.club_name}">
              <div class="my-2">
                <div class="text-secondary" style="font-size: 12px;">
                  ${post.club_name} / ${post.date}
                </div>
                <h6 class="mt-1">${post.title}</h6>
                <p style="font-size: 13px;">${post.description}</p>
              </div>
            </article>
          </div>
        `).join("");
      } catch (error) {
        container.innerHTML = `<p class="text-danger text-center">資料載入失敗<br>${error.message}</p>`;
        console.error(error);
      }
    }

    async function loadClubs(category = "", keyword = "") {
      const container = document.getElementById("club-list");
      if (!container) return;

      try {
        const params = new URLSearchParams();
        if (category) params.append("category", category);
        if (keyword) params.append("keyword", keyword);

        const url = params.toString() ? `api/clubs.php?${params.toString()}` : "api/clubs.php";
        const data = await fetchJson(url);

        if (!data.length) {
          //container.innerHTML = '<p class="loading-text">查無符合結果</p>';
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
                <div style="font-size:12px; color:#666;">
                  ${Array.isArray(club.tags) ? club.tags.join("、") : ""}
                </div>
              </div>
            </div>
          </div>
        `).join("");
      } catch (error) {
        container.innerHTML = `<p class="text-danger text-center">社團載入失敗<br>${error.message}</p>`;
        console.error(error);
      }
    }

    async function submitFeedback(email, message) {
      const result = document.getElementById("feedback-result");

      try {
        const res = await fetch("api/feedback.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify({ email, message })
        });

        const text = await res.text();
        console.log("feedback response:", text);

        const data = JSON.parse(text);

        if (data.success) {
          result.innerHTML = `<span class="text-success">${data.message}</span>`;
          document.getElementById("feedback-form").reset();
        } else {
          result.innerHTML = `<span class="text-danger">${data.message}</span>`;
        }
      } catch (error) {
        result.innerHTML = `<span class="text-danger">送出失敗：${error.message}</span>`;
        console.error(error);
      }
    }

    async function loadHotKeywords() {
      const list = document.getElementById("hotKeywords");
      if (!list) return;

      try {
        const data = await fetchJson("api/keywords.php");
        list.innerHTML = "";

        if (!Array.isArray(data) || !data.length) {
          list.innerHTML = "<li class='text-muted'>目前沒有熱門搜尋</li>";
          return;
        }

        data.forEach(item => {
          const keyword =
            typeof item === "string"
              ? item
              : (item.keyword || item.name || item.title || "");

          if (!keyword) return;

          const li = document.createElement("li");
          li.className = "cat-list-item";
          li.innerHTML = `<a href="#" class="text-decoration-none">${keyword}</a>`;

          li.addEventListener("click", function (e) {
            e.preventDefault();
            currentKeyword = keyword;

            const input = document.getElementById("search-keyword");
            if (input) input.value = keyword;

            document.getElementById("searchPopup")?.classList.remove("active");
            loadClubs(currentCategory, currentKeyword);
            document.getElementById("club-section")?.scrollIntoView({ behavior: "smooth" });
          });

          list.appendChild(li);
        });
      } catch (error) {
        console.error("熱門關鍵字載入失敗：", error);
      }
    }

    document.getElementById("feedback-form")?.addEventListener("submit", function (e) {
      e.preventDefault();
      const email = this.email.value.trim();
      const message = this.message.value.trim();
      submitFeedback(email, message);
    });

    document.querySelectorAll(".category-filter").forEach(button => {
      button.addEventListener("click", function (e) {
        e.preventDefault();
        currentCategory = this.dataset.category || "";
        loadClubs(currentCategory, currentKeyword);
        document.getElementById("club-section")?.scrollIntoView({ behavior: "smooth" });
      });
    });

    document.getElementById("search-form-api")?.addEventListener("submit", function (e) {
      e.preventDefault();
      currentKeyword = document.getElementById("search-keyword").value.trim();
      document.getElementById("searchPopup")?.classList.remove("active");
      loadClubs(currentCategory, currentKeyword);
      document.getElementById("club-section")?.scrollIntoView({ behavior: "smooth" });
    });

    document.getElementById("openSearch")?.addEventListener("click", function (e) {
      e.preventDefault();
      document.getElementById("searchPopup")?.classList.add("active");
    });

    document.getElementById("searchPopup")?.addEventListener("click", function (e) {
      if (e.target === this) {
        this.classList.remove("active");
      }
    });

    document.getElementById("closeSearchPopup")?.addEventListener("click", function () {
      document.getElementById("searchPopup")?.classList.remove("active");
    });

    loadAnnouncements();
    loadPosts("hot", "hot-post-list");
    loadPosts("recommended", "recommended-post-list");
    loadClubs();
    loadHotKeywords();
  </script>


<script src="js/script.js"></script>

</body>
</html>