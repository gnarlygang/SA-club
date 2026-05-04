<?php session_start();
require_once "header.php"; ?>
<!DOCTYPE html>
<html lang="zh-Hant">

<body>
  
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
  
  <a href="academic.php?type=學術性社團" class="btn btn-outline-dark category-filter" id="btn-學術性社團">學術性社團</a>
  <a href="academic.php?type=休閒聯誼性社團" class="btn btn-outline-dark category-filter" id="btn-休閒聯誼性社團">休閒聯誼性社團</a>
  <a href="academic.php?type=服務性社團" class="btn btn-outline-dark category-filter" id="btn-服務性社團">服務性社團</a>
  <a href="academic.php?type=體能性社團" class="btn btn-outline-dark category-filter" id="btn-體能性社團">體能性社團</a>
  <a href="academic.php?type=藝術性社團" class="btn btn-outline-dark category-filter" id="btn-藝術性社團">藝術性社團</a>
  <a href="academic.php?type=音樂性社團" class="btn btn-outline-dark category-filter" id="btn-音樂性社團">音樂性社團</a>
</div>
      </div>

      <div class="row g-3" id="club-list">
        <p class="loading-text">載入中...</p>
      </div>
    </div>

  </section>

<?php
require_once "footer.php";
?>


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