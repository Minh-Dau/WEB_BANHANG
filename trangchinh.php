<!DOCTYPE html>
<html lang="en">
<head>
    <style>
        .filter-buttons {
            text-align: center;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 15px;
            padding: 10px;
        }

        .filter-buttons button {
            padding: 12px 20px;
            font-size: 16px;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
            border-radius: 25px;
            background-color: #ffffff;
            color: #333;
            cursor: pointer;
            transition: all 0.3s ease-in-out;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .filter-buttons button:hover {
            background-color: #007bff;
            color: #ffffff;
            box-shadow: 0 6px 20px rgba(0, 123, 255, 0.3);
            transform: translateY(-2px);
        }

        .filter-buttons button.active {
            background-color: #0056b3;
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(0, 86, 179, 0.4);
            font-weight: 600;
        }

        .discount-code {
            text-align: center;
            margin: 40px 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .discount-code h4 {
            margin-bottom: 20px;
            font-size: 24px;
            color: #333;
        }

        .discount-code-list {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
        }

        .discount-code-item {
            display: flex;
            align-items: center;
            background-color: #f4f4f4;
            border-radius: 12px;
            padding: 10px 15px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }

        .discount-code-item:hover {
            transform: translateY(-3px);
        }

        .discount-code-item input {
            padding: 10px;
            font-size: 16px;
            width: 180px;
            border: none;
            background-color: transparent;
            font-weight: bold;
            color: #2c3e50;
        }

        .discount-code-item input:focus {
            outline: none;
        }

        .discount-code-item button {
            padding: 8px 16px;
            font-size: 14px;
            background-color:rgb(255, 94, 0);
            border: none;
            border-radius: 6px;
            color: #fff;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .discount-code-item button:hover {
            background-color:rgb(228, 83, 0);
        }

    </style>
</head>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<body>
    <?php include 'header.php'; ?>
    <?php include("config.php"); ?>

    <br>
    <div class="img_main">
        <div class="carousel">
            <img src="IMG/testimg1.jpg" alt="Ảnh 1">
            <img src="IMG/testimg2.jpg" alt="Ảnh 2">
            <img src="IMG/testimg3.jpg" alt="Ảnh 3">
        </div>
    </div>

    <div class="discount-code">
        <h4>Mã Giảm Giá:</h4>
        <div class="discount-code-list">
            <?php
            $sql_discount = "SELECT code, expiry_date, is_active FROM discount_codes";
            $result_discount = mysqli_query($conn, $sql_discount);

            if ($result_discount->num_rows > 0) {
                $index = 0;
                while ($row = mysqli_fetch_assoc($result_discount)) {
                    // Kiểm tra nếu mã còn hiệu lực và chưa hết hạn
                    $isActive = $row['is_active'] == 1;
                    $currentDate = new DateTime();
                    $expiryDate = new DateTime($row['expiry_date']);
                    $isNotExpired = $currentDate <= $expiryDate;

                    if ($isActive && $isNotExpired) {
                        $index++;
            ?>
                        <div class="discount-code-item">
                            <input type="text" id="discountCode_<?php echo $index; ?>" value="<?php echo htmlspecialchars($row['code']); ?>" readonly>
                            <button onclick="copyToClipboard('discountCode_<?php echo $index; ?>')">Sao chép</button>
                        </div>
            <?php
                    }
                }
                if ($index == 0) {
                    echo '<p>Không có mã giảm giá nào còn hiệu lực.</p>';
                }
            } else {
                echo '<p>Không có mã giảm giá nào.</p>';
            }
            ?>
        </div>
    </div>

    <div class="headline">
        <h3>SẢN PHẨM MỚI NHẤT</h3>
    </div>

    <div class="filter-buttons">
        <button class="filter-btn" data-category-id="all">Tất cả</button>
        <?php
        $sql_danhmuc = "SELECT * FROM danhmuc";
        $result_danhmuc = mysqli_query($conn, $sql_danhmuc);

        while ($row = mysqli_fetch_assoc($result_danhmuc)) {
            echo '<button class="filter-btn" data-category-id="' . $row['id'] . '">' . $row['tendanhmuc'] . '</button>';
        }
        ?>
    </div>

    <!-- Phần hiển thị tất cả mã giảm giá từ bảng discount_codes -->
    

    <div class="wrapper">
        <div class="product" id="product-list">
            <?php
            $danhmuc_id = isset($_GET['danhmuc_id']) ? $_GET['danhmuc_id'] : 'all';
            if ($danhmuc_id === 'all') {
                $sql = "SELECT * FROM sanpham WHERE trangthai = 'Hiển Thị' ORDER BY id DESC LIMIT 4";
                $result = mysqli_query($conn, $sql);
            } else {
                $sql = "SELECT * FROM sanpham WHERE trangthai = 'Hiển Thị' AND danhmuc_id = ? ORDER BY id DESC LIMIT 4";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $danhmuc_id);
                $stmt->execute();
                $result = $stmt->get_result();
            }

            if ($result->num_rows > 0) {
                while ($row = mysqli_fetch_array($result)) {
            ?>
                    <div class="product_item">
                        <div class="product_top">
                            <a href="chitietsanpham.php?id=<?= $row['id'] ?>" class="product_thumb">
                                <img src="<?php echo $row['img'] ?>" alt="" width="250" height="250">
                            </a>
                            <a href="chitietsanpham.php?id=<?= $row['id'] ?>" class="buy_now">Mua ngay</a>
                        </div>
                        <div class="product_info">
                            <a href="chitietsanpham.php?id=<?= $row['id'] ?>" class="product_cat"><?php echo $row['tensanpham'] ?></a>
                            <div class="product_price"><?php echo number_format($row['gia'], 0, ',', '.') ?> VND</div>
                        </div>
                    </div>
            <?php
                }
            } else {
                echo '
                    <div class="no_product">
                        <p>Không có sản phẩm nào thuộc danh mục này.</p>
                    </div>
                ';
            }

            if ($danhmuc_id !== 'all') {
                $stmt->close();
            }
            $conn->close();
            ?>
        </div>
    </div>

    <div class="product_all">
        <a href="shop.php">Xem tất cả</a>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        const carousel = document.querySelector(".carousel");
        const images = document.querySelectorAll(".carousel img");
        const totalImages = images.length;
        let currentIndex = 0;

        function getImageWidth() {
            return images[0].clientWidth;
        }
        function setPositionByIndex() {
            carousel.style.transition = "transform 0.5s ease-in-out";
            carousel.style.transform = `translateX(${-currentIndex * getImageWidth()}px)`;
        }
        function autoSlide() {
            currentIndex++;
            if (currentIndex >= totalImages) {
                currentIndex = 0;
            }
            setPositionByIndex();
        }
        window.addEventListener("resize", setPositionByIndex);
        setInterval(autoSlide, 5000);

        document.querySelectorAll('.filter-btn').forEach(button => {
            button.addEventListener('click', function () {
                document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                const danhmucId = this.getAttribute('data-category-id');
                window.location.href = `?danhmuc_id=${danhmucId}`;
            });
        });

        const urlParams = new URLSearchParams(window.location.search);
        const danhmucIdParam = urlParams.get('danhmuc_id') || 'all';
        document.querySelectorAll('.filter-btn').forEach(button => {
            if (button.getAttribute('data-category-id') === danhmucIdParam) {
                button.classList.add('active');
            }
        });

        function copyToClipboard(inputId) {
            var copyText = document.getElementById(inputId);
            copyText.select();
            document.execCommand("copy");

            Swal.fire({
                icon: 'success',
                title: 'Đã sao chép',
                text: 'Mã giảm giá đã được sao chép: ' + copyText.value,
                confirmButtonText: 'OK'
            });
        }
    </script>
</body>
</html>
<?php include 'chat.php'; ?>