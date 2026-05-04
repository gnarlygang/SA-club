// 全域變數，用來存放從資料庫撈回來的社團
let clubs = []; 

/**
 * 渲染頁面主要函式
 */
async function render() {
  const clubList = document.getElementById('club-list');
  if (!clubList) return; 

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

    // 4. 分類按鈕顯示/隱藏邏輯 (修正：移除 element.style)
    const allCategoryBtns = document.querySelectorAll('.category-filter');
    allCategoryBtns.forEach(btn => {
      // 移除 Bootstrap 強制色，改由 CSS 處理選中狀態
      btn.classList.remove('btn-dark', 'text-white');
      btn.classList.add('btn-outline-dark');

      if (typeFilter) {
        // 如果有過濾類型，透過 class 控制隱藏
        if (btn.id !== 'btn-all' && btn.id !== `btn-${typeFilter}` && !btn.id.includes('輔大')) {
           btn.classList.add('d-none'); // 使用 Bootstrap 的 class 或你在 CSS 定義的隱藏類
        } else {
           btn.classList.remove('d-none');
        }
      } else {
        btn.classList.remove('d-none');
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

    // 6. 渲染畫面 (修正：移除卡片上的 inline style)
    clubList.innerHTML = ''; 
    if (filteredClubs.length === 0) {
      clubList.innerHTML = '<div class="col-12 text-center mt-5"><h5>找不到相關社團資料</h5></div>';
      return; 
    }

    filteredClubs.forEach(club => {
      const displayImg = club.image || 'https://via.placeholder.com/800x600?text=No+Image';
      
      const clubHtml = `
        <div class="col-md-4 mb-4">
          <div class="card h-100 shadow-sm club-card" 
               onclick="showClubDetail(\`${club.name}\`, \`${displayImg}\`, \`${club.description.replace(/`/g, '\\`').replace(/\n/g, '<br>')}\`)">
            <img src="${displayImg}" class="card-img-top" alt="${club.name}">
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
    modalInfo.innerHTML = description; 
    
    const myModalElement = document.getElementById('clubModal');
    const myModal = bootstrap.Modal.getOrCreateInstance(myModalElement);
    myModal.show();
  }
}

window.addEventListener('load', render);

// 貼文熱度模擬 (保持劫持邏輯，但建議 CSS 加強強健性)
(function() {
    const _rawGetElement = document.getElementById;
    document.getElementById = function(id) {
        const el = _rawGetElement.call(document, id);
        if (id === 'hot-post-list' && !el) {
            return {
                set innerHTML(html) { },
                get innerHTML() { return ""; },
                appendChild: function() { return null; },
                style: {}
            };
        }
        return el;
    };

    const myNewLogic = () => {
        const realContainer = _rawGetElement.call(document, 'hot-post-list');
        if (!realContainer) return;
        
        const data = [
            { title: "【熱門】校園美食指南", comments: 450 },
            { title: "【熱門】AI 深度學習研討會", comments: 120 },
            { title: "【活動】草地音樂節", comments: 310 }
        ];

        data.sort((a, b) => b.comments - a.comments);
        realContainer.innerHTML = ""; 
        data.forEach(post => {
            const item = document.createElement('div');
            item.className = 'post-card';
            item.innerHTML = `<h3>${post.title}</h3><p>💬 留言數：${post.comments}</p>`;
            realContainer.appendChild(item);
        });
        console.log("✅ 排序資料已成功強制注入。");
    };

    const runner = setInterval(() => {
        const target = _rawGetElement.call(document, 'hot-post-list');
        if (target) {
            myNewLogic();
            clearInterval(runner);
        }
    }, 50);

    window.loadClubs = myNewLogic;
})();