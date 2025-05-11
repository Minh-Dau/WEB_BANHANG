<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hu$tle Stonie</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<style>
    .avatar {
        width: 40px;
        height: 40px;
        border-radius: 10%;
        margin-right: 10px;
        object-fit: cover;
    }
    .i {
        color: black;
    }
    
    .no-products {
        width: 100%;
        text-align: center;
        font-size: 30px;
        font-weight: bold;
        color: #555;
    }
    .filter-buttons {
        margin: 30px 0; /* Slightly increased margin for better spacing */
        text-align: center;
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 15px; /* Increased gap for better separation between buttons */
        padding: 10px; /* Added padding for a more balanced look */
    }

    .filter-buttons button {
        padding: 12px 20px; /* Slightly larger padding for a more comfortable click area */
        font-size: 16px;
        font-family: 'Poppins', sans-serif; /* Modern, clean font (you can include this via Google Fonts) */
        font-weight: 500; /* Medium weight for better readability */
        text-transform: uppercase; /* Uppercase for a bold, professional look */
        letter-spacing: 0.5px; /* Slight letter spacing for elegance */
        border: none; /* Removed border for a cleaner look */
        border-radius: 25px; /* More rounded corners for a modern feel */
        background-color: #ffffff; /* White background for a clean start */
        color: #333; /* Darker text color for contrast */
        cursor: pointer;
        transition: all 0.3s ease-in-out; /* Smooth transition for hover effects */
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); /* Softer, more modern shadow */
    }

    .filter-buttons button:hover {
        background-color: #007bff; /* Primary blue color on hover */
        color: #ffffff; /* White text on hover */
        box-shadow: 0 6px 20px rgba(0, 123, 255, 0.3); /* Enhanced shadow on hover for depth */
        transform: translateY(-2px); /* Slight lift effect on hover */
    }

    .filter-buttons button.active {
        background-color: #0056b3; /* Darker blue for the active state */
        color: #ffffff; /* White text for contrast */
        box-shadow: 0 4px 15px rgba(0, 86, 179, 0.4); /* Slightly stronger shadow for active state */
        font-weight: 600; /* Slightly bolder text for the active button */
    }
    </style>
<body>
    <?php
include 'header.php';
include 'config.php'; // Include only once
?>
<div class="thumb_shop">
    <img src="IMG/testimg.jpg" alt="">
</div>
<div class="headline">
    <h3>TẤT CẢ SẢN PHẨM</h3>
</div>
<div class="filter-buttons">
    <button class="filter-btn" data-category-id="all">Tất cả</button>
    <?php
    $sql_danhmuc = "SELECT * FROM danhmuc";
    $result_danhmuc = mysqli_query($conn, $sql_danhmuc);
    if ($result_danhmuc === false) {
        die("Query failed: " . $conn->error);
    }
    while ($row = mysqli_fetch_assoc($result_danhmuc)) {
        echo '<button class="filter-btn" data-category-id="' . $row['id'] . '">' . $row['tendanhmuc'] . '</button>';
    }
    ?>
</div>
<div class="wrapper">
    <div class="product" id="product-list">
        <?php
        include("config.php");

        $search = isset($_GET['search']) ? trim($_GET['search']) : "";
        $danhmuc_id = isset($_GET['danhmuc_id']) ? $_GET['danhmuc_id'] : 'all';
        $sql = "SELECT * FROM sanpham WHERE trangthai = 'Hiển Thị'";
        $params = [];
        $types = "";
        if (!empty($search)) {
            $sql .= " AND ((tensanpham LIKE ? OR SOUNDEX(tensanpham) = SOUNDEX(?)) 
                    OR danhmuc_id IN 
                        (SELECT id FROM danhmuc WHERE tendanhmuc LIKE ? OR SOUNDEX(tendanhmuc) = SOUNDEX(?)))";
            $params[] = "%$search%";
            $params[] = $search;
            $params[] = "%$search%";
            $params[] = $search;
            $types .= "ssss";
        }
        if ($danhmuc_id !== 'all') {
            $sql .= " AND danhmuc_id = ?";
            $params[] = $danhmuc_id;
            $types .= "i";
        }
        $sql .= " ORDER BY id DESC";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            die("Prepare failed: " . $conn->error);
        }
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        ?>
        <div class="wrapper">
            <div class="product">
                <?php
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                ?>
                        <div class="product_item">
                            <div class="product_top">
                                <a href="chitietsanpham.php?id=<?= $row['id'] ?>" class="product_thumb">
                                    <img src="<?= $row['img'] ?>" alt="" width="250" height="250">
                                </a>
                                <a href="chitietsanpham.php?id=<?= $row['id'] ?>" class="buy_now">Mua ngay</a>
                            </div>
                            <div class="product_info">
                                <a href="chitietsanpham.php?id=<?= $row['id'] ?>" class="product_cat"><?= htmlspecialchars($row['tensanpham']) ?></a>
                                <div class="product_price"><?= number_format($row['gia'], 0, ',', '.') ?> VND</div>
                            </div>
                        </div>
                <?php 
                    }
                } else {
                    echo '<p id="no-product-msg" style="text-align: center; color: red; ">Không tìm thấy sản phẩm nào.</p>';
                }

                $stmt->close();
                $conn->close();
                ?>
            </div>
        </div>
    </div>
</div>
<?php
include 'chat.php';
include 'footer.php';
?>
</body>
</html>
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
</script>