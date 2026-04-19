  <!-- 導覽列 -->
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
                  <li><a href="#" class="dropdown-item item-anchor category-filter" data-category="學術性社團">A 學術性社團</a></li>
                  <li><a href="#" class="dropdown-item item-anchor category-filter" data-category="休閒聯誼性社團">B 休閒聯誼性社團</a></li>
                  <li><a href="#" class="dropdown-item item-anchor category-filter" data-category="服務性社團">C 服務性社團</a></li>
                  <li><a href="#" class="dropdown-item item-anchor category-filter" data-category="體能性社團">D 體能性社團</a></li>
                  <li><a href="#" class="dropdown-item item-anchor category-filter" data-category="藝術性社團">E 藝術性社團</a></li>
                  <li><a href="#" class="dropdown-item item-anchor category-filter" data-category="音樂性社團">F 音樂性社團</a></li>
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