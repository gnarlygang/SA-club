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
  const modalName = document.getElementById('modalClubName');
  const modalImg = document.getElementById('modalClubImg');
  const modalInfo = document.getElementById('modalClubInfo');

  if (modalName && modalImg && modalInfo) {
    modalName.innerText = name;
    modalImg.src = image;
    modalInfo.innerText = description;
    
    const myModalElement = document.getElementById('clubModal');
    const myModal = bootstrap.Modal.getOrCreateInstance(myModalElement);
    myModal.show();
  }
}

// 啟動
window.addEventListener('load', render);
