<?php session_start();
require_once "header.php"; ?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>論壇頁面佈局</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* 設定論壇整體的容器，使用 Flexbox 佈局，高度佔滿視窗，隱藏溢出內容 */
    .forum-container {
      /* 1. 將此元素設為 Flex 容器，使其子元素（如側邊欄與主內容區）能併排顯示 */
      display: flex;
      /* 2. 設定容器高度為視窗高度的 100% (100vh)，確保頁面撐滿整個螢幕且不產生多餘空白 */
      height: 100vh;
      /* 3. 隱藏超出容器範圍的內容，防止整個網頁出現捲軸（通常為了讓內部特定區域自行捲動） */
      overflow: hidden;
    }

    /* 側邊欄設定，固定佔寬 25%，內部元素垂直排列 */
    .sidebar {
      flex: 0 0 25%;
      display: flex;
      flex-direction: column;
      background-color: #f8f9fa;
      border-right: 1px solid #ddd;
    }

    /* 導航項目樣式，設定為 flex 容器使文字垂直置中 */
    .nav-item {
      /* 讓該項目佔滿分配到的剩餘空間（在垂直排列的側邊欄中，這會讓每個選項等分高度） */
      flex: 1;
      /* 將此項目自身也設為一個 Flex 容器，以便對內部的文字進行對齊控制 */
      display: flex;
      /* 設定 Flex 軸線的垂直對齊，讓文字在該區塊內「垂直居中」 */
      align-items: center;
      /* 設定 Flex 軸線的水平對齊，讓文字在該區塊內「水平居中」 */
      justify-content: center;
      /* 在項目底部畫出一條 1 像素寬、淺灰色的實線，作為選項與選項間的分隔線 */
      border-bottom: 1px solid #eee;
      /* 當滑鼠移上去時，游標會變成「手指形狀」(Pointer)，提示使用者這是一個可點擊的按鈕 */
      cursor: pointer;
      /* 設定背景顏色變化的過渡效果（持續時間為 0.3 秒），讓 hover 時的變色過程平滑自然，不突兀 */
      transition: background 0.3s;
      /* 移除 <a> 標籤預設的底線樣式，讓文字看起來更乾淨 */
      text-decoration: none;
      /* 設定文字的基本顏色為深灰色（#333），比純黑色柔和，閱讀舒適度較高 */
      color: #333;
      /* 將文字加粗，增加視覺上的重點層級 */
      font-weight: bold;
    }

    /* 滑鼠懸停效果 */
    .nav-item:hover {
      background-color: #e9ecef;
    }

    /* 當前選中項目的樣式，使用 !important 確保優先級 */
    .nav-item.active {
      background-color: #2d3a4a !important;
      color: white !important;
    }

    /* 主要內容區域，佔寬 75%，啟用垂直滾動，處理內容過多時的情況 */
    .content-area {
      flex: 0 0 75%;
      overflow-y: auto;
      padding: 20px;
      background-color: #fff;
    }

    /* 單個貼文卡片的樣式，固定高度以維持佈局一致 */
    .post-card {
      /* 1. 動態計算高度：將視窗高度的一半 (50vh) 減去 40 像素。
      這確保在大多數螢幕上，使用者一眼能看到大約兩張卡片。 */
      height: calc(50vh - 40px);
      /* 2. 下方外距：在每張卡片底部留出 20 像素的空間，防止卡片互相黏在一起。 */
      margin-bottom: 20px;
      /* 3. 邊框：加上 1 像素寬的實線，顏色為淺灰色 (#ddd)，用來勾勒出卡片的輪廓。 */
      border: 1px solid #ddd;
      /* 4. 圓角：將卡片的四個角落設為 8 像素的圓弧，讓整體外觀更柔和。 */
      border-radius: 8px;
      /* 5. 內距：在卡片內部四周留出 20 像素的空間，確保內容不會緊貼邊框，提升閱讀體驗。 */
      padding: 20px;
      /* 6. 盒子陰影：水平偏移 0, 垂直偏移 2px, 模糊度 5px，
      顏色為極透明的黑色 (0.05)，營造出微弱的浮動立體感。 */
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
  </style>
</head>

<body>

<div class="forum-container">
  
  <nav class="sidebar">
    <a href="#" class="nav-item active">學術性社團</a>
    <a href="#" class="nav-item">休閒聯誼性社團</a>
    <a href="#" class="nav-item">服務性社團</a>
    <a href="#" class="nav-item">體能性社團</a>
    <a href="#" class="nav-item">藝術性社團</a>
    <a href="#" class="nav-item">音樂性社團</a>
  </nav>

  <main class="content-area">
    <div id="post-list">
      <div class="post-card">
        <h3>【學術】AI 深度學習研討會</h3>
        <p>本週五將舉辦 AI 專題講座...</p>
      </div>
      <div class="post-card">
        <h3>【學術】讀書會：量子力學入門</h3>
        <p>歡迎對物理有興趣的同學參加...</p>
      </div>
    </div>
  </main>

</div>

<script>
  // 定義社團對應的貼文資料物件 (資料庫模擬)
  const clubData = {
    "學術性社團": [
      { title: "【學術】AI 深度學習研討會", content: "本週五將舉辦 AI 專題講座..." },
      { title: "【學術】讀書會：量子力學入門", content: "歡迎對物理有興趣的同學參加..." },
      { title: "【競賽】創創社：創新創業競賽", content: "歡迎對創業有興趣的同學參加..." }
    ],
    "休閒聯誼性社團": [
      { title: "【聯誼】桌遊之夜開始報名", content: "今晚在活動中心有阿瓦隆大賽！" },
      { title: "【休閒】校園攝影馬拉松", content: "拿起相機，記錄校園的美景吧。" }
    ],
    "服務性社團": [
      { title: "【服務】偏鄉支教志願者招募", content: "今年暑假，讓我們一起去山上..." },
      { title: "【服務】流浪動物之家義工", content: "週末需要 5 位小幫手幫忙餵食。" }
    ],
    "體能性社團": [
      { title: "【體能】校長盃籃球賽", content: "熱血開打，快來報名！" }
    ],
    "藝術性社團": [
      { title: "【藝術】油畫基礎班", content: "從零開始學習色彩與筆觸。" }
    ],
    "音樂性社團": [
      { title: "【音樂】草地音樂節", content: "夕陽下的合唱表演，不容錯過。" }
    ]
  };

  // 獲取所有導航項目的 NodeList 與貼文列表的 DOM 容器
  const navItems = document.querySelectorAll('.nav-item');
  const postList = document.getElementById('post-list');

  // 為每個側邊欄項目添加點擊監聽事件
  navItems.forEach(item => {
    item.addEventListener('click', function(e) {
      e.preventDefault(); // 阻止連結的預設跳轉行為

      // 將所有項目的 active class 移除，並將當前點擊的項目加上 active
      navItems.forEach(nav => nav.classList.remove('active'));
      this.classList.add('active');

      // 取得當前點擊的文字內容，並對應到資料物件
      const clubName = this.innerText;
      const posts = clubData[clubName] || []; // 若無資料則設為空陣列

      // 清空目前的內容區域
      postList.innerHTML = "";

      // 判斷是否有資料，若無則顯示提示，有則渲染內容
      if (posts.length === 0) {
        postList.innerHTML = `<div class="post-card"><p>目前 "${clubName}" 尚無貼文。</p></div>`;
      } else {
        // 遍歷該社團的貼文，並動態建立新的 div 元素
        posts.forEach(post => {
          const card = document.createElement('div');
          card.className = 'post-card';
          // 插入 HTML 結構
          card.innerHTML = `
            <h3>${post.title}</h3>
            <p>${post.content}</p>
          `;
          postList.appendChild(card); // 將卡片添加到 post-list 容器中
        });
      }

      // 切換後將捲軸重置到頂端
      document.querySelector('.content-area').scrollTop = 0;
    });
  });
</script>

</body>
</html>