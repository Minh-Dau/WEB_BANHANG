<!DOCTYPE html>
<html lang="en">
<head>
<style>
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
</head>
<body>
<?php
include 'header.php';
include("config.php");
?>
<br>
<div class="img_main">
    <div class="carousel">
        <img src="IMG/testimg1.jpg" alt="Ảnh 1">
        <img src="IMG/testimg2.jpg" alt="Ảnh 2">
        <img src="IMG/testimg3.jpg" alt="Ảnh 3">
    </div>
</div>

<div class="headline">
    <h3>SẢN PHẨM MỚI NHẤT</h3>
</div>

<div class="filter-buttons">
    <button class="filter-btn" data-category-id="all">Tất cả</button>
    <?php
    include("config.php");
    $sql_danhmuc = "SELECT * FROM danhmuc";
    $result_danhmuc = mysqli_query($conn, $sql_danhmuc);

    while ($row = mysqli_fetch_assoc($result_danhmuc)) {
        echo '<button class="filter-btn" data-category-id="'.$row['id'].'">'.$row['tendanhmuc'].'</button>';
    }
    ?>
</div>
<div class="wrapper">
    <div class="product" id="product-list">
        <?php
            include("config.php");
            $danhmuc_id = isset($_GET['danhmuc_id']) ? $_GET['danhmuc_id'] : 'all';
            if ($danhmuc_id === 'all') {
                $sql = "SELECT * FROM sanpham WHERE trangthai = 'Hiển Thị' LIMIT 4";
                $result = mysqli_query($conn, $sql);
            } else {
                $sql = "SELECT * FROM sanpham WHERE trangthai = 'Hiển Thị' AND danhmuc_id = ? LIMIT 4";
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

<?php include 'footer.php' ?>

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
</body>
</html>
<?php include 'chat.php'; ?>