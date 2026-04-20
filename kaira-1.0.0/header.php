<!-- 導覽列 -->
<nav class="navbar navbar-expand-lg bg-light text-uppercase fs-6 p-3 border-bottom align-items-center">
    <div class="container-fluid">
      <div class="row justify-content-between align-items-center w-100">

        <div class="col-auto">
          <a class="navbar-brand fw-bold" href="index.php" style="letter-spacing:2px; font-size:20px;">FJU_CLUB</a>
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
                  <a class="nav-link" href="discuss.php">社團問答區</a>
                </li>

                <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 4): ?>
                  <li class="nav-item">
                    <a class="nav-link" href="announcement.php">公告發佈</a>
                  </li>
                <?php endif; ?>
              </ul>
            </div>
          </div>
        </div>

        <div class="col-3 col-lg-auto">
          <ul class="list-unstyled d-flex align-items-center gap-3 m-0">

            <!-- 搜尋按鈕 -->
            <li class="search-box">
              <a href="#" id="openSearch" class="search-button d-inline-flex align-items-center justify-content-center"
                style="width: 24px; height: 24px; text-decoration: none; color: black;">
                <i class="bi bi-search fs-5"></i>
              </a>
            </li>

            <!-- 通知鈴鐺（桌機） -->
            <li class="d-none d-lg-block">
              <a href="#" class="position-relative d-inline-flex align-items-center justify-content-center"
                style="width: 24px; height: 24px; text-decoration: none; color: black;">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                  fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                  stroke-linejoin="round" style="display:block;">
                  <path d="M18 8a6 6 0 10-12 0c0 7-3 7-3 7h18s-3 0-3-7"/>
                  <path d="M13.73 21a2 2 0 01-3.46 0"/>
                </svg>
                <span style="position:absolute; top:1px; right:-1px; width:6px; height:6px; background:red; border-radius:50%;"></span>
              </a>
            </li>

            <!-- 登入 / 使用者選單（桌機） -->
            <li class="d-none d-lg-block">
              <?php if (!empty($_SESSION['user_id'])): ?>
                <?php
                  $role = $_SESSION['role'] ?? null;
                  if ($role == 2) {
                      $profile_label = "社團資料";
                      $profile_link  = "create_club.php";
                  } else {
                      $profile_label = "個人資料";
                      $profile_link  = "profile.php";
                  }
                ?>
                <div class="dropdown">
                  <a class="dropdown-toggle text-uppercase text-dark text-decoration-none"
                     href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"
                     style="font-size: 14px;">
                    <?= htmlspecialchars($_SESSION['username']) ?>
                  </a>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                      <a class="dropdown-item" href="<?= $profile_link ?>">
                        <?= $profile_label ?>
                      </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                      <a class="dropdown-item text-danger" href="logout.php">登出</a>
                    </li>
                  </ul>
                </div>
              <?php else: ?>
                <a href="login.php" class="text-uppercase" style="text-decoration: none; color: black;">登入</a>
              <?php endif; ?>
            </li>

            <!-- 通知鈴鐺（手機） -->
            <li class="d-lg-none">
              <a href="#" class="position-relative d-inline-flex align-items-center justify-content-center"
                style="width: 24px; height: 24px; text-decoration: none; color: black;">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                  fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                  stroke-linejoin="round" style="display:block;">
                  <path d="M18 8a6 6 0 10-12 0c0 7-3 7-3 7h18s-3 0-3-7"/>
                  <path d="M13.73 21a2 2 0 01-3.46 0"/>
                </svg>
                <span style="position:absolute; top:1px; right:-1px; width:6px; height:6px; background:red; border-radius:50%;"></span>
              </a>
            </li>

            <!-- 登入 / 使用者選單（手機） -->
            <li class="d-lg-none">
              <?php if (!empty($_SESSION['user_id'])): ?>
                <?php
                  $role = $_SESSION['role'] ?? null;
                  if ($role == 2) {
                      $profile_label = "社團資料";
                      $profile_link  = "create_club.php";
                  } else {
                      $profile_label = "個人資料";
                      $profile_link  = "profile.php";
                  }
                ?>
                <div class="dropdown">
                  <a class="dropdown-toggle text-dark text-decoration-none"
                     href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"
                     style="font-size: 14px;">
                    <?= htmlspecialchars($_SESSION['username']) ?>
                  </a>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                      <a class="dropdown-item" href="<?= $profile_link ?>">
                        <?= $profile_label ?>
                      </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                      <a class="dropdown-item text-danger" href="logout.php">登出</a>
                    </li>
                  </ul>
                </div>
              <?php else: ?>
                <a href="login.php" style="text-decoration: none; color: black;">登入</a>
              <?php endif; ?>
            </li>

          </ul>
        </div>

      </div>
    </div>
  </nav>