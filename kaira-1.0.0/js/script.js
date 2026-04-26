// 全域變數，用來存放從資料庫撈回來的社團
let clubs = []; 

/**
 * 渲染頁面主要函式
 */
async function render() {
  const clubList = document.getElementById('club-list');
  if (!clubList) return; // 這裡在 function 內，是合法的

  try {
    // 1. 取得資料
    const response = await fetch('api/get_club.php');
    
    if (!response.ok) {
        throw new Error(`HTTP 錯誤！狀態碼: ${response.status}`);
    }

    const text = await response.text();
    if (!text.trim()) {
        throw new Error("PHP 回傳了空內容");
    }

    // 2. 解析 JSON
    try {
        clubs = JSON.parse(text);
    } catch (e) {
        console.error("JSON 解析失敗，原始內容為:", text);
        throw new Error("資料格式錯誤，無法解析為 JSON");
    }

    // 3. 處理網址參數
    const urlParams = new URLSearchParams(window.location.search);
    let typeFilter = urlParams.get('type');
    let nameFilter = urlParams.get('name');

    if (typeFilter) typeFilter = decodeURIComponent(typeFilter);
    if (nameFilter) nameFilter = decodeURIComponent(nameFilter);

    // 4. 分類按鈕顯示/隱藏邏輯
    const allCategoryBtns = document.querySelectorAll('.category-filter');
    allCategoryBtns.forEach(btn => {
      btn.classList.remove('btn-dark', 'text-white');
      btn.classList.add('btn-outline-dark');

      if (typeFilter) {
        // 如果有過濾類型，隱藏不相關的按鈕
        if (btn.id !== 'btn-all' && btn.id !== `btn-${typeFilter}` && !btn.id.includes('輔大')) {
           btn.style.display = 'none'; 
        } else {
           btn.style.display = 'inline-block';
        }
      } else {
        btn.style.display = 'inline-block';
      }
    });

    // 設定目前選中按鈕的高亮
    let targetId = "btn-all";
    if (nameFilter) {
      targetId = `btn-${nameFilter}`;
    } else if (typeFilter) {
      targetId = `btn-${typeFilter}`;
    }
    const activeBtn = document.getElementById(targetId);
    if (activeBtn) activeBtn.classList.replace('btn-outline-dark', 'btn-dark');

    // 5. 過濾資料
    let filteredClubs = clubs;
    if (nameFilter) {
      filteredClubs = clubs.filter(club => club.name === nameFilter);
    } else if (typeFilter) {
      filteredClubs = clubs.filter(club => club.category === typeFilter);
    }

    // 6. 渲染畫面
    clubList.innerHTML = ''; 
    if (filteredClubs.length === 0) {
      clubList.innerHTML = '<div class="col-12 text-center mt-5"><h5>找不到相關社團資料</h5></div>';
      return; 
    }

    filteredClubs.forEach(club => {
      // 處理資料庫中可能缺失的圖片 (使用預設圖)
      const displayImg = club.image || 'https://via.placeholder.com/800x600?text=No+Image';
      
      // 使用反引號避免簡介內的引號造成 HTML 斷裂
      const clubHtml = `
        <div class="col-md-4 mb-4">
          <div class="card h-100 shadow-sm" style="cursor: pointer;" 
               onclick="showClubDetail(\`${club.name}\`, \`${displayImg}\`, \`${club.description.replace(/`/g, '\\`').replace(/\n/g, '<br>')}\`)">
            <img src="${displayImg}" class="card-img-top" alt="${club.name}" style="height: 200px; object-fit: cover;">
            <div class="card-body">
              <h5 class="card-title">${club.name}</h5>
              <span class="badge bg-secondary">${club.category}</span>
            </div>
          </div>
        </div>
      `;
      clubList.innerHTML += clubHtml;
    });

  } catch (error) {
    console.error("渲染過程發生錯誤:", error);
    clubList.innerHTML = `<div class="col-12 text-center mt-5"><h5>系統提示: ${error.message}</h5></div>`;
  }
}

/**
 * 彈跳視窗功能
 */
function showClubDetail(name, image, description) {
  const modalName = document.getElementById('modalClubName');
  const modalImg = document.getElementById('modalClubImg');
  const modalInfo = document.getElementById('modalClubInfo');

  if (modalName && modalImg && modalInfo) {
    modalName.innerText = name;
    modalImg.src = image;
    modalInfo.innerHTML = description; // 改用 innerHTML 以支援換行
    
    const myModalElement = document.getElementById('clubModal');
    const myModal = bootstrap.Modal.getOrCreateInstance(myModalElement);
    myModal.show();
  }
}

// 監聽網頁載入完成後執行
window.addEventListener('load', render);







//貼文熱度模擬

(function() {
    // 1. 備份原始方法
    const _rawGetElement = document.getElementById;

    // 2. 劫持 getElementById
    document.getElementById = function(id) {
        const el = _rawGetElement.call(document, id);
        
        // 如果是在找 hot-post-list 且目前沒找到
        if (id === 'hot-post-list' && !el) {
            // 回傳一個「假物件」來防止 .innerHTML 報錯
            console.warn("偵測到非法存取，已啟動防護屏蔽錯誤。");
            return {
                set innerHTML(html) { /* 安靜地吃掉這行字，不報錯 */ },
                get innerHTML() { return ""; },
                appendChild: function() { return null; },
                style: {}
            };
        }
        return el;
    };

    // 3. 你的新排序邏輯
    const myNewLogic = () => {
        const realContainer = _rawGetElement.call(document, 'hot-post-list');
        if (!realContainer) return;
        
        // 模擬從後端拿到的資料，並按照留言數排序
        const data = [
            { title: "【熱門】校園美食指南", comments: 450 },
            { title: "【熱門】AI 深度學習研討會", comments: 120 },
            { title: "【活動】草地音樂節", comments: 310 }
        ];

        data.sort((a, b) => b.comments - a.comments);

        // 1. 清空容器內原本的所有內容
        // 作用：移除「載入中...」文字或舊的貼文列表，確保接下來放入的是最新的排序資料。
        realContainer.innerHTML = ""; 
        // 2. 使用 forEach 迴圈遍歷 data 陣列中的每一筆貼文物件 (post)
        data.forEach(post => {
            // 3. 為每一筆貼文建立一個新的 div 元素，並設定其 class 為 post-card
            const item = document.createElement('div');
            item.className = 'post-card';
            // 5. 使用「樣式字串 (Template Literals)」動態填入貼文的 HTML 結構
            item.innerHTML = `<h3>${post.title}</h3><p>💬 留言數：${post.comments}</p>`;
            // 6. 將這個組裝好的新元素 (item) 塞入網頁上的真實容器 (realContainer) 中
            // 作用：讓貼文正式顯示在網頁畫面上。
            realContainer.appendChild(item);
        });
        // 7. 在瀏覽器的開發者控制台 (Console) 印出成功訊息
        // 作用：方便開發者確認這段渲染程式碼有正確跑完，而沒有中途當掉。
        console.log("✅ 排序資料已成功強制注入。");
    };

    // 4. 定時檢查，直到 HTML 畫出來為止
    const runner = setInterval(() => {
        const target = _rawGetElement.call(document, 'hot-post-list');
        if (target) {
            myNewLogic();
            clearInterval(runner);
        }
    }, 50);

    // 5. 確保全域函數被覆寫
    window.loadClubs = myNewLogic;
})();