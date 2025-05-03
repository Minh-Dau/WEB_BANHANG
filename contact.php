<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Hu$tle Stonie</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
  <style>
    .hs-store-container {
      max-width: 800px;
      margin: 60px auto;
      padding: 20px;
      background-color:rgb(255, 255, 255);
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(255, 255, 255, 0.05);
      text-align: center;
    }

    .hs-store-title {
      font-size: 32px;
      text-align: center;
      color:rgb(0, 0, 0);
      margin-bottom: 10px;
    }

    .hs-store-subinfo {
      text-align: center;
      font-size: 14px;
      color:rgb(0, 0, 0);
    }

    .hs-store-section {
      margin-top: 30px;
    }

    .hs-store-section h2 {
      font-size: 24px;
      border-left: 5px solidrgb(0, 0, 0);
      color:rgb(0, 0, 0);
    }

    .hs-store-branch {
      margin-top: 20px;
    }

    .hs-store-branch h4 {
      font-size: 20px;
      color:rgb(0, 0, 0);
    }

    .hs-store-branch p {
      font-size: 16px;
      line-height: 1.6;
      color:rgb(0, 0, 0);
    }

    .hs-store-icon {
      margin-right: 8px;
      color:rgb(0, 0, 0);
    }

    .hs-store-divider {
      text-align: center;
      font-size: 22px;
      color:rgb(0, 0, 0);
      margin: 30px 0;
    }

    @media (max-width: 600px) {
      .hs-store-title {
        font-size: 26px;
      }

      .hs-store-section h2 {
        font-size: 20px;
      }

      .hs-store-branch h4 {
        font-size: 18px;
      }

      .hs-store-branch p {
        font-size: 15px;
      }
    }
  </style>
</head>
<body>

<?php include 'header.php'; ?>
<?php include 'config.php'; ?>

<div class="hs-store-container">
  <h1 class="hs-store-title">HỆ THỐNG CỬA HÀNG HUSTLE STONIE</h1>
  <p class="hs-store-subinfo"><i class="bi bi-person-circle hs-store-icon"></i>Đăng bởi: Team HUSTLE STONIE | 2025</p>

  <div class="hs-store-section">
    <h2><i class="bi bi-shop hs-store-icon"></i>HỆ THỐNG CỬA HÀNG</h2>

    <div class="hs-store-branch">
      <h4><i class="bi bi-geo-alt-fill hs-store-icon"></i>CHI NHÁNH TRƯỜNG ĐH SƯ PHẠM KỸ THUẬT VĨNH LONG</h4>
      <p><i class="bi bi-geo-alt hs-store-icon"></i>Đường Nguyễn Huệ, Phường 2, Thành phố Vĩnh Long</p>

      <div class="hs-store-divider">---</div>

      <p><i class="bi bi-telephone-fill hs-store-icon"></i>Hotline: 0122 112 211</p>
      <p><i class="bi bi-envelope-fill hs-store-icon"></i>Email: hustlerstonie@gmail.com</p>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
<?php include 'chat.php'; ?>

</body>
</html>
