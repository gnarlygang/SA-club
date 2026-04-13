<!DOCTYPE html>
<html lang="zh-Hant">

<head>
  <title>輔大社團平台</title>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body>
  
  <nav class="navbar navbar-expand-lg bg-light text-uppercase fs-6 p-3 border-bottom align-items-center">
  <div class="container-fluid">
    <div class="row justify-content-between align-items-center w-100">

      <div class="col-auto">
        <a class="navbar-brand fw-bold" href="index.html" style="letter-spacing:2px; font-size:20px;">FJU_CLUB</a>
      </div>

      <div class="col-auto">
        <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar"
          aria-controls="offcanvasNavbar">
          <span class="navbar-toggler-icon"></span>
        </button>

        <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar"
          aria-labelledby="offcanvasNavbarLabel">
          <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="offcanvasNavbarLabel">Menu</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"
              aria-label="Close"></button>
          </div>

          <div class="offcanvas-body">
            <ul class="navbar-nav justify-content-end flex-grow-1 gap-1 gap-md-5 pe-3">
              <li class="nav-item dropdown">
                <a class="nav-link" href="#">主頁</a>
              </li>

              <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="dropdownShop" data-bs-toggle="dropdown"
                  aria-haspopup="true" aria-expanded="false">社團介紹</a>
                <ul class="dropdown-menu list-unstyled" aria-labelledby="dropdownShop">
                  <a href="academic.php?type=學術性社團" class="btn btn-outline-white" target="_blank">學術性社團</a>
                  <a href="academic.php?type=休閒聯誼性社團" class="btn btn-outline-white" target="_blank">休閒聯誼性社團</a>
                  <a href="academic.php?type=服務性社團" class="btn btn-outline-white" target="_blank">服務性社團</a>
                  <a href="academic.php?type=體能性社團" class="btn btn-outline-white" target="_blank">體能性社團</a>
                  <a href="academic.php?type=藝術性社團" class="btn btn-outline-white" target="_blank">藝術性社團</a>
                  <a href="academic.php?type=音樂性社團" class="btn btn-outline-white" target="_blank">音樂性社團</a>
                </ul>
              </li>

              <li class="nav-item dropdown">
                <a class="nav-link" href="#">訂閱</a>
              </li>

              <li class="nav-item dropdown">
                <a class="nav-link" href="#">貼文收藏</a>
              </li>

              <li class="nav-item">
                <a class="nav-link" href="#">活動</a>
              </li>

              <li class="nav-item">
                <a class="nav-link" href="#">社團問答區</a>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-3 col-lg-auto">
        <ul class="list-unstyled d-flex align-items-center gap-3 m-0">

          <li class="search-box">
            <a href="#" id="openSearch" class="search-button d-inline-flex align-items-center justify-content-center"
              style="width: 24px; height: 24px; text-decoration: none; color: black;">
              <i class="bi bi-search fs-5"></i>
            </a>
          </li>

          <li class="d-none d-lg-block">
            <a href="#" class="position-relative d-inline-flex align-items-center justify-content-center"
              style="width: 24px; height: 24px; text-decoration: none; color: black;">
              <svg xmlns="http://www.w3.org/2000/svg"
                  width="22"
                  height="22"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  style="display:block;">
                <path d="M18 8a6 6 0 10-12 0c0 7-3 7-3 7h18s-3 0-3-7"/>
                <path d="M13.73 21a2 2 0 01-3.46 0"/>
              </svg>

              <span style="
                position: absolute;
                top: 1px;
                right: -1px;
                width: 6px;
                height: 6px;
                background: red;
                border-radius: 50%;
              "></span>
            </a>
          </li>

          <li class="d-none d-lg-block">
            <a href="login.html" class="text-uppercase" style="text-decoration: none; color: black;">登入</a>
          </li>

          <li class="d-lg-none">
            <a href="#" class="position-relative d-inline-flex align-items-center justify-content-center"
              style="width: 24px; height: 24px; text-decoration: none; color: black;">
              <svg xmlns="http://www.w3.org/2000/svg"
                  width="22"
                  height="22"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  style="display:block;">
                <path d="M18 8a6 6 0 10-12 0c0 7-3 7-3 7h18s-3 0-3-7"/>
                <path d="M13.73 21a2 2 0 01-3.46 0"/>
              </svg>

              <span style="
                position: absolute;
                top: 1px;
                right: -1px;
                width: 6px;
                height: 6px;
                background: red;
                border-radius: 50%;
              "></span>
            </a>
          </li>

          <li class="d-lg-none">
            <a href="login.html" style="text-decoration: none; color: black;">登入</a>
          </li>

        </ul>
      </div>

    </div>
  </div>
</nav>




<!-- 社團分類與搜尋 -->
  <section id="club-section" class="py-5">
    <div class="container">
      <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h4 class="text-uppercase">社團分類與瀏覽</h4>
      </div>

      <div class="category-btns mb-3">
        <div class="category-btns mb-3">
  <div class="category-btns mb-3">
  <a href="academic.php" class="btn btn-outline-dark category-filter" id="btn-all">全部</a>
  
  <a href="academic.php?name=輔大韓研社" class="btn btn-outline-dark category-filter" id="btn-學術性社團">輔大韓研社</a>
  <a href="academic.php?name=輔大桌遊社" class="btn btn-outline-dark category-filter" id="btn-休閒聯誼性社團">輔大桌遊社</a>
  <a href="academic.php?name=輔大志工服務隊" class="btn btn-outline-dark category-filter" id="btn-服務性社團">輔大志工服務隊</a>
  <a href="academic.php?name=輔大登山社" class="btn btn-outline-dark category-filter" id="btn-體能性社團">輔大登山社</a>
  <a href="academic.php?name=輔大熱舞社" class="btn btn-outline-dark category-filter" id="btn-藝術性社團">輔大熱舞社</a>
  <a href="academic.php?name=輔大國樂社" class="btn btn-outline-dark category-filter" id="btn-音樂性社團">輔大國樂社</a>
</div>
      </div>

      <div class="row g-3" id="club-list">
        <p class="loading-text">載入中...</p>
      </div>
    </div>



    


  </section>



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




<div class="modal fade" id="clubModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalClubName">社團名稱</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <img src="" id="modalClubImg" class="img-fluid mb-3" alt="">
        <p id="modalClubInfo">這裡會顯示社團詳細介紹...</p>
      </div>
    </div>
  </div>
</div>




<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/script.js"></script>
</body>