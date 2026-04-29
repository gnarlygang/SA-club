<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
role 對應
0 = 訪客
1 = teacher
2 = club
3 = student
4 = admin
*/
$role = $_SESSION['role'] ?? 0;
?>

<!-- NAV -->
<nav class="main-nav">
  <a class="nav-logo" href="index.php">FJU_CLUB</a>

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
      <li><a href="manager_index.php">管理後台</a></li>
      <li><a href="forum.php">論壇</a></li>

    <!-- ===== 老師 ===== -->
    <?php elseif ($role == 1): ?>
      <li><a href="index.php">首頁</a></li>
      <li><a href="clubs.php">社團介紹</a></li>
      <li><a href="activities.php">活動</a></li>
      <li><a href="forum.php">論壇</a></li>
    <?php endif; ?>

  </ul>

  <div class="nav-right">

    <!-- 搜尋 -->
    <a href="search.php" class="nav-search-btn" title="搜尋">⌕</a>

    <!-- ===== 右側功能 ===== -->

    <?php if ($role == 0): ?>
      <a href="login.php" class="btn-login">登入</a>

    <?php elseif ($role == 3): ?>
      <a href="profile.php" class="btn-login">個人</a>
      <a href="logout.php" class="btn-login">登出</a>

    <?php elseif ($role == 2): ?>
      <a href="logout.php" class="btn-login">登出</a>

    <?php elseif ($role == 4): ?>
      <a href="logout.php" class="btn-login">登出</a>

    <?php endif; ?>

  </div>
</nav>

<!-- CSS -->
<style>
.main-nav {
  background: #1a2744;
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 40px;
  position: sticky;
  top: 0;
  z-index: 999;
}

/* LOGO */
.nav-logo {
  color: #fff;
  font-size: 18px;
  font-weight: bold;
  text-decoration: none;
  letter-spacing: 2px;
}

/* LINKS */
.nav-links {
  display: flex;
  gap: 20px;
  list-style: none;
}

.nav-links a {
  color: rgba(255,255,255,0.7);
  text-decoration: none;
  font-size: 14px;
  padding: 6px 10px;
  border-radius: 6px;
  transition: 0.2s;
}

.nav-links a:hover {
  color: #fff;
  background: rgba(255,255,255,0.1);
}

/* RIGHT */
.nav-right {
  display: flex;
  align-items: center;
  gap: 10px;
}

/* SEARCH */
.nav-search-btn {
  color: rgba(255,255,255,0.7);
  font-size: 18px;
  text-decoration: none;
}

.nav-search-btn:hover {
  color: #fff;
}

/* BUTTON */
.btn-login {
  background: rgba(255,255,255,0.1);
  border: 1px solid rgba(255,255,255,0.3);
  color: #fff;
  padding: 5px 12px;
  border-radius: 6px;
  font-size: 13px;
  text-decoration: none;
  transition: 0.2s;
}

.btn-login:hover {
  background: rgba(255,255,255,0.2);
}
</style>