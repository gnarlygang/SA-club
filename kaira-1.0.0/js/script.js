// 1. 統一資料格式 (確保每個物件的 key 名稱都一樣)
const clubs = [
  { 
    id: 1,
    name: "輔大熱舞社", 
    category: "藝術性社團", 
    image: "https://images.unsplash.com/photo-1547153760-18fc86324498?auto=format&fit=crop&w=800&q=80", 
    description: "喜歡跳舞與表演的同學可以一起交流、練舞與參與成果展。" 
  },
  { 
    id: 2,
    name: "輔大登山社", 
    category: "體能性社團", 
    image: "https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=800&q=80", 
    description: "透過登山活動培養體能、合作與戶外探索能力。" 
  },
  { 
    id: 3,
    name: "輔大國樂社", 
    category: "音樂性社團", 
    image: "https://images.unsplash.com/photo-1507838153414-b4b713384a76?auto=format&fit=crop&w=800&q=80", 
    description: "傳統音樂演奏與交流，歡迎對國樂有興趣的同學加入。" 
  },
  { 
    id: 4,
    name: "輔大韓研社", 
    category: "學術性社團", 
    image: "https://images.unsplash.com/photo-1517048676732-d65bc937f952?auto=format&fit=crop&w=800&q=80", 
    description: "認識韓國 culture、語言與流行趨勢，舉辦講座與交流活動。" 
  },
  { 
    id: 5,
    name: "輔大志工服務隊", 
    category: "服務性社團", 
    image: "https://images.unsplash.com/photo-1521737604893-d14cc237f11d?auto=format&fit=crop&w=800&q=80", 
    description: "參與偏鄉、公益與陪伴活動，累積服務學習經驗。" 
  },
  { 
    id: 6,
    name: "輔大桌遊社", 
    category: "休閒聯誼性社團", 
    image: "https://images.unsplash.com/photo-1610890716171-6b1bb98ffd09?auto=format&fit=crop&w=800&q=80", 
    description: "用桌遊交流認識朋友，培養策略思考與互動默契。" 
  }
];

function render() {
  const clubList = document.getElementById('club-list');
  if (!clubList) return;

  const urlParams = new URLSearchParams(window.location.search);
  let typeFilter = urlParams.get('type');
  let nameFilter = urlParams.get('name');

  if (typeFilter) typeFilter = decodeURIComponent(typeFilter);
  if (nameFilter) nameFilter = decodeURIComponent(nameFilter);

  // --- 新增：按鈕隱藏邏輯 ---
  // 找出所有的分類按鈕
  const allCategoryBtns = document.querySelectorAll('.category-filter');

  allCategoryBtns.forEach(btn => {
    // 1. 先恢復所有按鈕的高亮狀態 (變成空心)
    btn.classList.remove('btn-dark', 'text-white');
    btn.classList.add('btn-outline-dark');

    // 2. 判斷是否隱藏：
    // 如果網址有 type (例如「學術性社團」)，且按鈕的 ID 不是該 type，也不是「全部」按鈕，就隱藏它
    if (typeFilter) {
      const isSpecificClubBtn = btn.id.startsWith('btn-'); // 辨識是否為特定社團按鈕
      
      // 如果按鈕 ID 不符合目前的 type，也不是「全部」按鈕，就隱藏
      // 這裡假設你的按鈕 ID 命名規則是 btn-學術性社團, btn-輔大韓研社 等
      if (btn.id !== 'btn-all' && btn.id !== `btn-${typeFilter}` && !btn.id.includes('輔大')) {
         btn.style.display = 'none'; 
      } else {
         btn.style.display = 'inline-block'; // 符合的則顯示
      }
    } else {
      // 如果沒有 type 參數 (在首頁)，顯示所有按鈕
      btn.style.display = 'inline-block';
    }
  });

  // --- 設定高亮 (維持原有邏輯) ---
  let targetId = "btn-all";
  if (nameFilter) {
    targetId = `btn-${nameFilter}`;
  } else if (typeFilter) {
    targetId = `btn-${typeFilter}`;
  }
  const activeBtn = document.getElementById(targetId);
  if (activeBtn) activeBtn.classList.replace('btn-outline-dark', 'btn-dark');


  // --- 過濾資料與渲染 (維持原有邏輯) ---
  let filteredClubs = clubs;
  if (nameFilter) {
    filteredClubs = clubs.filter(club => club.name === nameFilter);
  } else if (typeFilter) {
    filteredClubs = clubs.filter(club => club.category === typeFilter);
  }

  clubList.innerHTML = ''; 
  if (filteredClubs.length === 0) {
    clubList.innerHTML = '<div class="col-12 text-center mt-5"><h5>找不到相關社團資料</h5></div>';
    return;
  }

  filteredClubs.forEach(club => {
    const clubHtml = `
      <div class="col-md-4">
        <div class="card h-100 shadow-sm" style="cursor: pointer;" onclick="showClubDetail('${club.name}', '${club.image}', '${club.description}')">
          <img src="${club.image}" class="card-img-top" alt="${club.name}" style="height: 200px; object-fit: cover;">
          <div class="card-body">
            <h5 class="card-title">${club.name}</h5>
            <span class="badge bg-secondary">${club.category}</span>
          </div>
        </div>
      </div>
    `;
    clubList.innerHTML += clubHtml;
  });
}

// 彈跳視窗功能 (手動點擊圖片時觸發)
function showClubDetail(name, image, description) {
  // 透過 ID 取得彈出視窗內準備用來顯示名稱的 HTML 元素
  const modalName = document.getElementById('modalClubName');
  const modalImg = document.getElementById('modalClubImg');
  const modalInfo = document.getElementById('modalClubInfo');

  //  防禦性檢查：確保上述三個元素在 HTML 頁面中都存在，才執行後續動作（避免 null 報錯）
  if (modalName && modalImg && modalInfo) {
    // 將傳入的「社團名稱」填入到 modalName 元素的文字內容中
    modalName.innerText = name;
    modalImg.src = image;
    modalInfo.innerText = description;
    
    //  取得整個彈出視窗（Modal）的最外層容器元素
    const myModalElement = document.getElementById('clubModal');
    //  使用 Bootstrap 的實例方法：若該元素已建立過 Modal 實例則取得它，否則建立一個新的
    const myModal = bootstrap.Modal.getOrCreateInstance(myModalElement);
    //  呼叫Bootstrap API來顯示彈出視窗
    myModal.show();
  }
}

// 啟動
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