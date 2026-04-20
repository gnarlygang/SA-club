<?php
require_once "api/db.php"; 

// --- 處理邏輯區 ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (!empty($title) && !empty($content)) {
        try {
            $sql = "INSERT INTO announcements (title, content, date) VALUES (:title, :content, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':title' => $title, ':content' => $content]);
            echo "<script>alert('✅ 公告已成功發佈！'); window.location.href = 'index.php';</script>";
            exit;
        } catch (PDOException $e) {
            $error_msg = "資料庫錯誤：" . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>發佈公告 - Management Console</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        /* 套用你提供的基礎 CSS */
        body {
            font-family: "Microsoft JhengHei", "微軟正黑體", sans-serif;
            margin: 0;
            height: 100%;
            /* 延續藍黑色背景需求 */
            background: #ffffff; 
            overflow-x: hidden;
        }

        .full-screen-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* 延續玻璃擬態卡片設計 */
        .glass-card {
    /* 改為實心深黑藍色 */
    background: #2d3a4a; 
    
    /* 移除模糊濾鏡，實色不需要此屬性 */
    backdrop-filter: none; 
    
    /* 調整邊框顏色，讓邊緣有微弱的藍光質感 */
    border: 1px solid #2c3e50; 
    
    /* 保持原有的圓角與大小設定 */
    border-radius: 20px;
    width: 100%;
    max-width: 900px;
    padding: 40px;
    
}

        .card-title {
            color: #ffffff;
            font-weight: 700;
            letter-spacing: 2px;
            text-align: center;
            margin-bottom: 30px;
            text-shadow: 0 2px 10px rgba(0, 123, 255, 0.5);
        }

        /* 打字區：保持純白，文字置中 */
        .form-control {
            background-color: #ffffff !important;
            color: #212529 !important;
            border: none;
            border-radius: 12px;
            padding: 15px 20px;
            font-size: 1.1rem;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center; 
            width: 100%;
            display: block;
            margin-bottom: 20px;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            box-shadow: 0 0 15px rgba(0, 123, 255, 0.6);
            transform: scale(1.01);
            transition: all 0.3s ease;
        }

        label {
            color: #ced4da !important;
            margin-bottom: 8px;
            display: block;
            font-weight: 500;
            text-align: center;
        }

        /* 酷炫按鈕：使用你提供的 footer 顏色與深色調 */
        .btn-submit {
            background: #afbac7; /* 使用你提供的 footer-custom 顏色 */
            border: none;
            color: #333;
            font-weight: bold;
            padding: 12px 60px;
            border-radius: 50px;
            text-transform: uppercase;
            transition: 0.3s;
            cursor: pointer;
            display: block;
            margin: 0 auto;
        }

        .btn-submit:hover {
            transform: scale(1.05);
            background: #ffffff;
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.4);
        }

        .btn-back-container {
            text-align: center;
            margin-top: 20px;
        }

        .btn-back {
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            transition: 0.3s;
            font-size: 0.9rem;
        }

        .btn-back:hover {
            color: #ffffff;
        }

        /* 你提供的其餘元件 CSS (保留以備後續擴充) */
        .footer-custom { background-color: #afbac7; color: #333; }
        .cat-list { list-style: none; padding: 0; display: flex; flex-wrap: wrap; gap: 10px; }
    </style>
</head>
<body>

<div class="full-screen-wrapper">
    <div class="glass-card">
        <div class="text-center">
            <h2 class="card-title text-uppercase">
                <i class="bi bi-shield-lock-fill me-2" style="color: #00d4ff;"></i> 
                Management Console
            </h2>
        </div>

        <form action="announcement.php" method="POST">
            <div class="mb-4">
                <label><i class="bi bi-type-h1 me-2"></i>公告標題</label>
                <input type="text" name="title" class="form-control" placeholder="請輸入標題內容..." required>
            </div>

            <div class="mb-4">
                <label><i class="bi bi-justify-left me-2"></i>公告詳情</label>
                <textarea name="content" class="form-control" rows="8" placeholder="請輸入詳細公告內容..." style="resize: none;" required></textarea>
            </div>

            <button type="submit" class="btn-submit">
                發佈公告 <i class="bi bi-send-check-fill ms-2"></i>
            </button>
            
            <div class="btn-back-container">
                <a href="index.php" class="btn-back">
                    <i class="bi bi-chevron-left"></i> 返回後台首頁
                </a>
            </div>
        </form>
    </div>
</div>

</body>
</html>