<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Hu$tle Stonie</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
  <style>

    .titel {
      text-align: center;
      font-family: 'Anton', sans-serif;
      font-size: 100px;
      color:rgb(0, 0, 0);
      margin: 40px 0 10px;
    }

    .about {
      max-width: 1100px;
      margin: auto;
      padding: 0 20px;
    }

    .section {
      margin-bottom: 50px;
    }

    .image-container {
      text-align: center;
      margin-bottom: 20px;
    }

    .image-container img {
      max-width: 100%;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .content {
      text-align: justify;
      font-size: 16px;
      color:rgb(0, 0, 0);
    }

    @media (min-width: 768px) {
      .section {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        align-items: center;
      }

      .section:nth-child(even) {
        grid-template-columns: 1fr 1fr;
      }

      .section:nth-child(even) .image-container {
        order: 2;
      }

      .content {
        font-size: 18px;
      }
    }
  </style>
</head>
<body>

<?php include 'header.php'; ?>
<?php include 'config.php'; ?>

<div class="about">
  <h4 class="titel">Hu$tle Stonie</h4>

  <div class="section">
    <div class="image-container">
      <img src="IMG/thoitrang1.jpg" alt="Streetwear 1">
    </div>
    <div class="content">
      <p>
        Con đường chinh phục Streetwear của thương hiệu Hustler Stonie được bắt đầu từ 2025 tại Vĩnh Long - Việt Nam, xuất phát từ ý tưởng về một thương hiệu Việt mang đậm văn hóa đường phố.
        Chúng tôi đã bắt đầu hành trình cùng những người bạn GenZ đầy nhiệt huyết và sáng tạo.
        <br><br>
        Không quá ồn ào hay phô trương, Hustler Stonie tượng trưng cho những giá trị nguyên bản nhất của cuộc sống: hiện thực gai góc, giá trị lao động với mồ hôi và nước mắt. 
        Thương hiệu đại diện cho tinh thần mạnh mẽ, táo bạo nhưng gần gũi.
      </p>
    </div>
  </div>

  <div class="section">
    <div class="image-container">
      <img src="IMG/thoitrang2.jpg" alt="Streetwear 2">
    </div>
    <div class="content">
      <p>
        Với sứ mệnh <b>“Hustler Stonie là nơi thể hiện cái tôi và đóng góp giá trị cho văn hoá thời trang đường phố”</b>, chúng tôi đã xây dựng thương hiệu dựa trên những giá trị cốt lõi và bền vững, hướng đến mục tiêu trở thành <b>TOP 1 VIETNAMESE STREETWEAR BRAND</b>.
      </p>
    </div>
  </div>

  <div class="section">
    <div class="image-container">
      <img src="IMG/thoitrang3.jpg" alt="Streetwear 3">
    </div>
    <div class="content">
      <p>
        Chúng tôi thúc đẩy sự sáng tạo không ngừng, tạo việc làm cho hàng trăm nhân sự mỗi năm với môi trường làm việc năng động, đội ngũ đoàn kết và đầy nhiệt huyết.
        <br><br>
        Sản phẩm của Hustler Stonie kết hợp tinh hoa giữa văn hóa phương Tây và Phương Đông, tạo ra một ngôn ngữ sáng tạo rất riêng và đậm chất nghệ thuật đương đại.
      </p>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
<?php include 'chat.php'; ?>

</body>
</html>
