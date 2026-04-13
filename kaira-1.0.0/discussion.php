<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>論壇頁面佈局</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* 核心佈局樣式 */
    .forum-container {
      display: flex;
      height: 100vh;
      overflow: hidden;
    }

    .sidebar {
      flex: 0 0 25%;
      display: flex;
      flex-direction: column;
      background-color: #f8f9fa;
      border-right: 1px solid #ddd;
    }

    .nav-item {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      border-bottom: 1px solid #eee;
      cursor: pointer;
      transition: background 0.3s;
      text-decoration: none;
      color: #333;
      font-weight: bold;
    }

    .nav-item:hover {
      background-color: #e9ecef;
    }

    /* 確保 active 狀態顏色明顯 */
    .nav-item.active {
      background-color: #ae8972 !important;
      color: white !important;
    }

    .content-area {
      flex: 0 0 75%;
      overflow-y: auto;
      padding: 20px;
      background-color: #fff;
    }

    .post-card {
      height: calc(50vh - 40px);
      margin-bottom: 20px;
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 20px;
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

  const navItems = document.querySelectorAll('.nav-item');
  const postList = document.getElementById('post-list');

  navItems.forEach(item => {
    item.addEventListener('click', function(e) {
      e.preventDefault();

      // 切換顏色
      navItems.forEach(nav => nav.classList.remove('active'));
      this.classList.add('active');

      // 更新內容
      const clubName = this.innerText;
      const posts = clubData[clubName] || [];

      postList.innerHTML = "";

      if (posts.length === 0) {
        postList.innerHTML = `<div class="post-card"><p>目前 "${clubName}" 尚無貼文。</p></div>`;
      } else {
        posts.forEach(post => {
          const card = document.createElement('div');
          card.className = 'post-card';
          card.innerHTML = `
            <h3>${post.title}</h3>
            <p>${post.content}</p>
          `;
          postList.appendChild(card);
        });
      }

      document.querySelector('.content-area').scrollTop = 0;
    });
  });
</script>

</body>
</html>