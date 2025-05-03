<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
    if (!isset($_SESSION['username'])) {
        header("Location: dangnhap.php");
        exit();
    }
}

// Include config.php to access the database connection
include("config.php");

// Lấy số lượng đơn hàng trong giỏ hàng (nếu người dùng đã đăng nhập)
$total_items = 0;
if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];
    $stmt = $conn->prepare("SELECT id FROM frm_dangky WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];

        // Lấy tổng số lượng đơn hàng (số lượng bản ghi riêng biệt) trong giỏ hàng
        $stmt_cart = $conn->prepare("SELECT COUNT(*) as total_orders FROM cart_item WHERE user_id = ?");
        $stmt_cart->bind_param("i", $user_id);
        $stmt_cart->execute();
        $result_cart = $stmt_cart->get_result();
        $row_cart = $result_cart->fetch_assoc();
        $total_items = $row_cart['total_orders'] ?? 0; // Đếm số lượng dòng thay vì tổng soluong
        $stmt_cart->close();
    }
    $stmt->close();
}
?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hu$tle Stonie</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="header.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<header class="main">
    <a href="trangchinh.php">
        <img class="imgLogo" src="IMG/logo2.png" alt="logo" id="reset">
    </a>
    
    <nav>
        <ul class="danhsach">
            <li class="thea"><a href="trangchinh.php"><b>TRANG CHỦ</b></a></li>
            <li class="thea"><a href="shop.php"><b>SẢN PHẨM</b></a></li>                   
            <li class="thea"><a href="contact.php"><b>LIÊN HỆ</b></a></li>
            <li class="thea"><a href="about.php"><b>GIỚI THIỆU</b></a></li>
        </ul>
    </nav>

    <div class="header-right">
        <div class="search-container">
            <form action="shop.php" method="GET">
                <input type="text" name="search" class="search-box" placeholder="Tìm kiếm sản phẩm...">
                <button type="submit" class="search-btn"><i class="bi bi-search"></i></button>
            </form>
        </div>
        <div class="shopping">
            <a href="giohang.php" style="color: black;">
                <i class="bi bi-cart2"></i>
                <span id="cart-count" class="cart-count"><?php echo $total_items; ?></span>
            </a>
        </div>
        <div class="login">
            <a href="<?php echo isset($_SESSION['username']) ? 'thongtin.php' : 'dangnhap.php'; ?>">
                <i class="bi bi-person-fill"></i>
            </a>
            <div class="dropdown-menu">
                <ul>
                    <li><a href="thongtin.php">Tài Khoản Của Tôi</a></li>
                    <li><a href="giaidoan.php">Đơn Mua</a></li>
                    <li><a href="logout.php">Đăng Xuất</a></li>
                </ul>
            </div>
        </div>
    </div>
</header>