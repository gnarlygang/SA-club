<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FJU_CLUB</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;600;700&family=Noto+Sans+TC:wght@300;400;500&display=swap" rel="stylesheet">

</head>
<body>

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
<<<<<<< HEAD
                  <a class="nav-link dropdown-toggle" href="academic.php" id="dropdownShop" data-bs-toggle="dropdown"
                    aria-haspopup="true" aria-expanded="false">社團介紹</a>
                  <ul class="dropdown-menu list-unstyled" aria-labelledby="dropdownShop">
                    <a href="academic.php?type=學術性社團" class="btn btn-outline-white" >學術性社團</a>
                    <a href="academic.php?type=休閒聯誼性社團" class="btn btn-outline-white" >休閒聯誼性社團</a>
                    <a href="academic.php?type=服務性社團" class="btn btn-outline-white">服務性社團</a>
                    <a href="academic.php?type=體能性社團" class="btn btn-outline-white" >體能性社團</a>
                    <a href="academic.php?type=藝術性社團" class="btn btn-outline-white">藝術性社團</a>
                    <a href="academic.php?type=音樂性社團" class="btn btn-outline-white">音樂性社團</a>
                  </ul>
                </li>

                <li class="nav-item">
                   <a class="nav-link" href="subscriptions.php">訂閱</a>
                </li>

                <li class="nav-item">
                  <a class="nav-link"href="#">貼文收藏</a>
=======
                  <a class="nav-link dropdown-toggle" href="#" id="dropdownShop"
                     data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    社團介紹
                  </a>
                  <ul class="dropdown-menu" aria-labelledby="dropdownShop">
    <li><a href="clubs.php" class="dropdown-item">全部社團</a></li>
    <li><hr class="dropdown-divider"></li>
    <li><a href="clubs.php?cat=學術性社團" class="dropdown-item">學術性社團</a></li>
    <li><a href="clubs.php?cat=休閒聯誼性社團" class="dropdown-item">休閒聯誼性社團</a></li>
    <li><a href="clubs.php?cat=服務性社團" class="dropdown-item">服務性社團</a></li>
    <li><a href="clubs.php?cat=體能性社團" class="dropdown-item">體能性社團</a></li>
    <li><a href="clubs.php?cat=藝術性社團" class="dropdown-item">藝術性社團</a></li>
    <li><a href="clubs.php?cat=音樂性社團" class="dropdown-item">音樂性社團</a></li>
</ul>
                </li>

                <li class="nav-item">
                  <a class="nav-link" href="#">訂閱</a>
                </li>

                <li class="nav-item">
                  <a class="nav-link" href="#">貼文收藏</a>
>>>>>>> ab5ccd841a9e6096220ebe81bd8382fe58dd6290
                </li>

                <li class="nav-item">
                  <a class="nav-link" href="activities.php">活動</a>
                </li>

                <li class="nav-item">
                  <a class="nav-link" href="discussion.php">社團問答區</a>
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

            <!-- 搜尋 -->
            <li>
              <a href="#" id="openSearch" class="d-inline-flex align-items-center justify-content-center"
                style="width:24px;height:24px;text-decoration:none;color:black;">
                <i class="bi bi-search fs-5"></i>
              </a>
            </li>

            <!-- 通知鈴鐺 -->
            <li>
              <a href="#" class="position-relative d-inline-flex align-items-center justify-content-center"
                style="width:24px;height:24px;text-decoration:none;color:black;">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                  fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M18 8a6 6 0 10-12 0c0 7-3 7-3 7h18s-3 0-3-7"/>
                  <path d="M13.73 21a2 2 0 01-3.46 0"/>
                </svg>
                <span style="position:absolute;top:1px;right:-1px;width:6px;height:6px;background:red;border-radius:50%;"></span>
              </a>
            </li>

            <!-- 登入 / 使用者選單 -->
            <li>
              <?php if (!empty($_SESSION['user_id'])): ?>
                <?php
                  $role = $_SESSION['role'] ?? null;
                  $profile_label = ($role == 2) ? "社團資料" : "個人資料";
                  $profile_link  = ($role == 2) ? "create_club.php" : "profile.php";
                ?>
                <div class="dropdown">
                  <a class="dropdown-toggle text-uppercase text-dark text-decoration-none"
                     href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"
                     style="font-size:14px;">
                    <?= htmlspecialchars($_SESSION['username']) ?>
                  </a>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= $profile_link ?>"><?= $profile_label ?></a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php">登出</a></li>
                  </ul>
                </div>
              <?php else: ?>
                <a href="login.php" class="text-uppercase" style="text-decoration:none;color:black;">登入</a>
              <?php endif; ?>
            </li>

          </ul>
        </div>

      </div>
    </div>
</nav>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>