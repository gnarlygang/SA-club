<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role     = $_SESSION['role']     ?? 0;
$username = $_SESSION['username'] ?? '';
?>

<head>
    <meta charset="UTF-8">
    <title>輔大社團平台</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <link rel="stylesheet" href="css/activity_list.css">
    <link rel="stylesheet" href="css/club_sub.css">
    <link rel="stylesheet" href="css/forum.css">
    <link rel="stylesheet" href="css/index.css">
<<<<<<< HEAD
    <style>
        .nav-username {
            color: #ffffff !important;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .nav-username:hover {
            color: #ffffff !important;
            text-decoration: none;
            opacity: 0.8;
        }
=======

    <style>
    .nav-username,
    .nav-username:visited,
    .nav-username:hover,
    .nav-username:active {
        color: #ffffff !important;
        text-decoration: none !important;
    }

    .nav-username {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 14px;
        font-weight: 500;
        opacity: 0.95;
        line-height: 1;
    }

    .nav-username:hover {
        color: #e0e7ff !important;
        opacity: 1;
    }

    .nav-username i {
        color: inherit;
        font-size: 15px;
    }
>>>>>>> b0fe645f0c43735b28676557aa16510481ffe689
    </style>
</head>

<nav class="main-nav">
    <a class="nav-logo" href="index.php">FJU_CLUB</a>

<<<<<<< HEAD
  <ul class="nav-links">
    <?php if ($role == 0): ?>
      <li><a href="index.php">首頁</a></li>
      <li><a href="clubs.php">社團介紹</a></li>
      <li><a href="activities.php">活動</a></li>
      <li><a href="forum.php">論壇</a></li>
    <?php elseif ($role == 3): ?>
      <li><a href="index.php">首頁</a></li>
      <li><a href="clubs.php">社團介紹</a></li>
      <li><a href="activities.php">活動</a></li>
      <li><a href="forum.php">論壇</a></li>
    <?php elseif ($role == 2): ?>
      <li><a href="index.php">首頁</a></li>
      <li><a href="edit_club.php">社團後台</a></li>
      <li><a href="activity_create.php">發布活動</a></li>
      <li><a href="activity_list.php">活動管理</a></li>
      <li><a href="forum.php">論壇</a></li>
    <?php elseif ($role == 4): ?>
      <li><a href="index.php">首頁</a></li>
      <li><a href="announcement.php">發布公告</a></li>
      <li><a href="admin.php">管理後台</a></li>
      <li><a href="forum.php">論壇</a></li>
    <?php elseif ($role == 1): ?>
      <li><a href="index.php">首頁</a></li>
      <li><a href="clubs.php">社團介紹</a></li>
      <li><a href="activities.php">活動</a></li>
      <li><a href="forum.php">論壇</a></li>
    <?php endif; ?>
  </ul>

  <div class="nav-right">
    <?php if ($role == 0): ?>
      <a href="login.php" class="btn-login">登入</a>
    <?php else: ?>
      <?php if ($role == 3): ?>
        <a href="profile.php" class="nav-username">
          <i class="bi bi-person-circle"></i>
          <?= htmlspecialchars($username) ?>
        </a>
      <?php else: ?>
        <span class="nav-username">
          <i class="bi bi-person-circle"></i>
          <?= htmlspecialchars($username) ?>
        </span>
      <?php endif; ?>
      <a href="logout.php" class="btn-login">登出</a>
    <?php endif; ?>
  </div>
=======
    <ul class="nav-links">

        <!-- ===== 訪客 ===== -->
        <?php if ($role == 0): ?>
            <li><a href="index.php">首頁</a></li>
            <li><a href="clubs.php">社團介紹</a></li>
            <li><a href="activities.php">活動</a></li>
            <li><a href="forum.php">論壇</a></li>

        <!-- ===== 學生 ===== -->
        <?php elseif ($role == 3): ?>
            <li><a href="index.php">首頁</a></li>
            <li><a href="clubs.php">社團介紹</a></li>
            <li><a href="activities.php">活動</a></li>
            <li><a href="forum.php">論壇</a></li>

        <!-- ===== 社團 ===== -->
        <?php elseif ($role == 2): ?>
            <li><a href="index.php">首頁</a></li>
            <li><a href="edit_club.php">社團後台</a></li>
            <li><a href="activity_create.php">發布活動</a></li>
            <li><a href="activity_list.php">活動管理</a></li>
            <li><a href="forum.php">論壇</a></li>

        <!-- ===== 管理員 ===== -->
        <?php elseif ($role == 4): ?>
            <li><a href="index.php">首頁</a></li>
            <li><a href="announcement.php">發布公告</a></li>
            <li><a href="admin.php">管理後台</a></li>
            <li><a href="forum.php">論壇</a></li>

        <?php endif; ?>

    </ul>

    <div class="nav-right">

        <!-- 搜尋 -->
        <a href="search.php" class="nav-search-btn" title="搜尋">⌕</a>

        <!-- ===== 訪客：只顯示登入按鈕 ===== -->
        <?php if ($role == 0): ?>

            <a href="login.php" class="btn-login">登入</a>

        <!-- ===== 登入後：username 連結到個人頁（學生）或純文字（其他角色） ===== -->
        <?php else: ?>

            <?php if ($role == 3): ?>

                <!-- 學生：username 可點擊連到個人頁 -->
                <a href="profile.php" class="nav-username">
                    <i class="bi bi-person-circle"></i>
                    <?= htmlspecialchars($username) ?>
                </a>

            <?php else: ?>

                <!-- 其他角色：純顯示 username -->
                <span class="nav-username">
                    <i class="bi bi-person-circle"></i>
                    <?= htmlspecialchars($username) ?>
                </span>

            <?php endif; ?>

            <a href="logout.php" class="btn-login">登出</a>

        <?php endif; ?>

    </div>
>>>>>>> b0fe645f0c43735b28676557aa16510481ffe689
</nav>