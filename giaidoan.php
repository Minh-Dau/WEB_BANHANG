<?php
ob_start();
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');
include "config.php";
include 'header.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['username'])) {
    echo "<script>window.location.href='dangnhap.php';</script>";
    exit();
}

$username = $_SESSION['username'];

// Lấy thông tin người dùng từ frm_dangky
$sql = "SELECT id, hoten, diachi, sdt, email FROM frm_dangky WHERE username = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $user_id = $user['id'];
    $hoten = $user['hoten'] ?? '';
    $diachi = $user['diachi'] ?? '';
    $sdt = $user['sdt'] ?? '';
    $email = $user['email'] ?? '';
} else {
    echo "<script>alert('Không tìm thấy thông tin người dùng. Vui lòng đăng nhập lại.'); window.location.href='dangnhap.php';</script>";
    exit();
}
$stmt->close();

// Xử lý cập nhật thông tin giao hàng
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_GET['action']) && $_GET['action'] == 'update' && isset($_GET['id'])) {
    $order_id = intval($_GET['id']);
    $sdt = $_POST['sdt'];
    $address = $_POST['address'];
    $province = $_POST['province_name'];
    $district = $_POST['district_name'];
    $ward = $_POST['ward_name'];
    $diachi = mysqli_real_escape_string($conn, "$address, $ward, $district, $province");

    // Cập nhật thông tin trong frm_dangky
    $query = "UPDATE frm_dangky SET sdt = ?, diachi = ? WHERE id = ? AND EXISTS (SELECT 1 FROM oder WHERE id = ? AND user_id = ? AND trangthai = 'Chờ xác nhận')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssiii", $sdt, $diachi, $user_id, $order_id, $user_id);
    $stmt->execute();
    $stmt->close();

    header("Location: giaidoan.php");
    exit();
}

// Xử lý hủy đơn hàng
if (isset($_GET['action']) && $_GET['action'] == 'cancel' && isset($_GET['id'])) {
    $order_id = intval($_GET['id']);
    $query = "UPDATE oder SET trangthai = 'Đã hủy' WHERE id = ? AND user_id = ? AND trangthai = 'Chờ xác nhận'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $stmt->close();

    header("Location: giaidoan.php");
    exit();
}

// Lấy danh sách đơn hàng
$orders = [];
if (isset($_GET['order_id']) && is_numeric($_GET['order_id'])) {
    // Hiển thị đơn hàng cụ thể
    $order_id = intval($_GET['order_id']);
    $query = "SELECT o.*, u.hoten, u.diachi, u.sdt, u.email 
              FROM oder o 
              JOIN frm_dangky u ON o.user_id = u.id 
              WHERE o.id = ? AND o.user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $orders[] = $result->fetch_assoc();
    }
    $stmt->close();
} else {
    // Hiển thị tất cả đơn hàng
    $query = "SELECT o.*, u.hoten, u.diachi, u.sdt, u.email 
              FROM oder o 
              JOIN frm_dangky u ON o.user_id = u.id 
              WHERE o.user_id = ? 
              ORDER BY o.ngaydathang DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
}

// Lấy chi tiết đơn hàng
$order_details = [];
foreach ($orders as &$order) {
    $order_id = $order['id'];
    $sql_details = "SELECT od.*, s.tensanpham, s.img 
                   FROM oder_detail od 
                   JOIN sanpham s ON od.sanpham_id = s.id 
                   WHERE od.oder_id = ?";
    $stmt_details = $conn->prepare($sql_details);
    $stmt_details->bind_param("i", $order_id);
    $stmt_details->execute();
    $result_details = $stmt_details->get_result();
    $order['items'] = [];
    while ($row = $result_details->fetch_assoc()) {
        $order['items'][] = $row;
    }
    $stmt_details->close();
}
unset($order);

// Định nghĩa các giai đoạn
$statuses = ['Chờ xác nhận', 'Đang giao', 'Đã giao'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theo dõi đơn hàng | DUMEMAY</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="header.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="order-tracking">
        <h1>Theo dõi đơn hàng</h1>
        <?php if (empty($orders)) { ?>
            <div class="no-orders">
                <i class="bi bi-box-seam"></i>
                <p>Bạn chưa có đơn hàng nào.</p>
                <a href="shop.php" class="shop-now">Mua sắm ngay</a>
            </div>
        <?php } else { ?>
            <?php foreach ($orders as $donhang) { ?>
                <div class="order-card">
                    <!-- Order Header -->
                    <div class="order-header">
                        <div class="order-id">
                            <span>Mã đơn hàng: #<?php echo $donhang['id']; ?></span>
                        </div>
                        <div class="order-status-header">
                            <span class="status-label">Trạng thái:</span>
                            <span class="status-value <?php echo strtolower(str_replace(' ', '-', $donhang['trangthai'])); ?>">
                                <?php echo htmlspecialchars($donhang['trangthai'] ?? 'Chờ xác nhận'); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Address Info -->
                    <div class="section address-info">
                        <h2><i class="bi bi-geo-alt-fill"></i> Địa chỉ nhận hàng</h2>
                        <div class="info-row">
                            <div class="info-item">
                                <label>Họ tên:</label>
                                <span><?php echo htmlspecialchars($donhang['hoten']); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Số điện thoại:</label>
                                <span><?php echo htmlspecialchars($donhang['sdt']); ?></span>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-item">
                                <label>Email:</label>
                                <span><?php echo htmlspecialchars($donhang['email']); ?></span>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-item">
                                <label>Địa chỉ:</label>
                                <span><?php echo htmlspecialchars($donhang['diachi']); ?></span>
                            </div>
                        </div>
                        <?php if ($donhang['trangthai'] == 'Chờ xác nhận') { ?>
                            <div class="action-buttons">
                                <button class="update-btn" data-donhang-id="<?php echo $donhang['id']; ?>">
                                    <i class="bi bi-pencil-square"></i> Cập nhật
                                </button>
                                <button class="cancel-btn" data-donhang-id="<?php echo $donhang['id']; ?>">
                                    <i class="bi bi-x-circle"></i> Hủy đơn hàng
                                </button>
                            </div>

                            <!-- Modal cập nhật -->
                            <div id="update-modal-<?php echo $donhang['id']; ?>" class="modal">
                                <div class="modal-content">
                                    <span class="close-modal">&times;</span>
                                    <h3>Cập nhật thông tin giao hàng</h3>
                                    <form id="update-form-<?php echo $donhang['id']; ?>" method="POST" action="giaidoan.php?action=update&id=<?php echo $donhang['id']; ?>" class="modal-form">
                                        <div class="form-group">
                                            <label>Số điện thoại:</label>
                                            <input type="text" name="sdt" value="<?php echo htmlspecialchars($donhang['sdt']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Tỉnh/Thành phố:</label>
                                            <select id="province-<?php echo $donhang['id']; ?>" name="province" required>
                                                <option value="">Chọn tỉnh/thành phố</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Quận/Huyện:</label>
                                            <select id="district-<?php echo $donhang['id']; ?>" name="district" required>
                                                <option value="">Chọn quận/huyện</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Phường/Xã:</label>
                                            <select id="ward-<?php echo $donhang['id']; ?>" name="ward" required>
                                                <option value="">Chọn phường/xã</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Địa chỉ cụ thể:</label>
                                            <input type="text" name="address" value="<?php echo htmlspecialchars(explode(', ', $donhang['diachi'])[0]); ?>" required>
                                        </div>
                                        <input type="hidden" id="province_name-<?php echo $donhang['id']; ?>" name="province_name">
                                        <input type="hidden" id="district_name-<?php echo $donhang['id']; ?>" name="district_name">
                                        <input type="hidden" id="ward_name-<?php echo $donhang['id']; ?>" name="ward_name">
                                        <div class="modal-actions">
                                            <button type="submit" class="submit-btn">Xác nhận</button>
                                            <button type="button" class="close-btn">Đóng</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php } ?>
                    </div>

                    <!-- Product Info -->
                    <div class="section product-info">
                        <h2><i class="bi bi-box-seam"></i> Sản phẩm</h2>
                        <?php foreach ($donhang['items'] as $item) { ?>
                            <div class="product-item">
                                <img src="<?php echo htmlspecialchars($item['img']); ?>" alt="<?php echo htmlspecialchars($item['tensanpham']); ?>" class="product-image">
                                <div class="product-details">
                                    <h4><?php echo htmlspecialchars($item['tensanpham']); ?></h4>
                                    <div class="product-meta">
                                        <span class="price"><?php echo number_format($item['gia'], 0, ',', '.') . ' VNĐ'; ?></span>
                                        <span class="quantity">Số lượng: <?php echo $item['soluong']; ?></span>
                                        <span class="subtotal">Tổng: <?php echo number_format($item['subtotal'], 0, ',', '.') . ' VNĐ'; ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>

                    <!-- Order Summary -->
                    <div class="section order-summary">
                        <h2><i class="bi bi-wallet2"></i> Tổng thanh toán</h2>
                        <div class="summary-row">
                            <span class="label">Tổng (Kèm phí vận chuyển):</span>
                            <span class="value"><?php echo number_format($donhang['total'], 0, ',', '.') . ' VNĐ'; ?></span>
                        </div>
                        <div class="summary-row">
                            <span class="label">Trạng thái thanh toán:</span>
                            <span class="value payment-status" style="color: <?php echo $donhang['payment_status'] == 'Đã thanh toán' ? '#28a745' : '#dc3545'; ?>">
                                <?php echo htmlspecialchars($donhang['payment_status']); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Order Dates -->
                    <div class="section order-dates">
                        <div class="date-item">
                            <label>Ngày đặt hàng:</label>
                            <span><?php
                                $ngaydathang = new DateTime($donhang['ngaydathang']);
                                echo $ngaydathang->format('d/m/Y H:i');
                            ?></span>
                        </div>
                        <div class="date-item">
                            <label>Ngày giao dự kiến:</label>
                            <span><?php
                                $ngaydathang = new DateTime($donhang['ngaydathang']);
                                $ngaydathang->modify('+2 days');
                                echo $ngaydathang->format('d/m/Y');
                            ?></span>
                        </div>
                    </div>

                    <!-- Delivery Status -->
                    <div class="section delivery-status">
                        <h2><i class="bi bi-truck"></i> Tiến trình giao hàng</h2>
                        <div class="status-bar">
                            <div class="status-line">
                                <?php
                                $current_index = array_search($donhang['trangthai'], $statuses);
                                if ($current_index === false) {
                                    $current_index = -1; // For 'Đã hủy', don't show progress
                                }
                                $progress_width = $current_index >= 0 ? ($current_index / (count($statuses) - 1)) * 100 : 0;
                                ?>
                                <div class="progress" style="width: <?php echo $progress_width; ?>%;"></div>
                            </div>
                            <?php
                            foreach ($statuses as $index => $status) {
                                $active = $donhang['trangthai'] == $status ? 'active' : '';
                                $completed = $index < $current_index ? 'completed' : '';
                            ?>
                                <div class="status-item <?php echo $active . ' ' . $completed; ?>">
                                    <span class="status-icon">
                                        <i class="bi <?php 
                                            if ($status == 'Chờ xác nhận') echo 'bi-clock';
                                            elseif ($status == 'Đang giao') echo 'bi-truck';
                                            elseif ($status == 'Đã giao') echo 'bi-check-circle';
                                        ?>"></i>
                                    </span>
                                    <span class="status-text"><?php echo $status; ?></span>
                                </div>
                            <?php } ?>
                            <?php if ($donhang['trangthai'] == 'Đã hủy') { ?>
                                <div class="status-item active canceled">
                                    <span class="status-icon"><i class="bi bi-x-circle"></i></span>
                                    <span class="status-text">Đã hủy</span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            <?php } ?>
        <?php } ?>
    </div>

    <?php include 'footer.php'; include 'chat.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // API địa chỉ cho từng modal
            document.querySelectorAll('.update-btn').forEach(btn => {
                const id = btn.getAttribute('data-donhang-id');

                // Load tỉnh/thành phố
                fetch("https://provinces.open-api.vn/api/p/")
                    .then(response => response.json())
                    .then(data => {
                        let provinceSelect = document.getElementById(`province-${id}`);
                        data.forEach(province => {
                            let option = document.createElement("option");
                            option.value = province.code;
                            option.textContent = province.name;
                            provinceSelect.appendChild(option);
                        });
                    });

                // Sự kiện thay đổi tỉnh/thành phố
                document.getElementById(`province-${id}`).addEventListener("change", function() {
                    let provinceCode = this.value;
                    let provinceName = this.options[this.selectedIndex].text;
                    document.getElementById(`province_name-${id}`).value = provinceName;
                    fetch(`https://provinces.open-api.vn/api/p/${provinceCode}?depth=2`)
                        .then(response => response.json())
                        .then(data => {
                            let districtSelect = document.getElementById(`district-${id}`);
                            districtSelect.innerHTML = '<option value="">Chọn quận/huyện</option>';
                            data.districts.forEach(district => {
                                let option = document.createElement("option");
                                option.value = district.code;
                                option.textContent = district.name;
                                districtSelect.appendChild(option);
                            });
                        });
                });

                // Sự kiện thay đổi quận/huyện
                document.getElementById(`district-${id}`).addEventListener("change", function() {
                    let districtCode = this.value;
                    let districtName = this.options[this.selectedIndex].text;
                    document.getElementById(`district_name-${id}`).value = districtName;
                    fetch(`https://provinces.open-api.vn/api/d/${districtCode}?depth=2`)
                        .then(response => response.json())
                        .then(data => {
                            let wardSelect = document.getElementById(`ward-${id}`);
                            wardSelect.innerHTML = '<option value="">Chọn phường/xã</option>';
                            data.wards.forEach(ward => {
                                let option = document.createElement("option");
                                option.value = ward.code;
                                option.textContent = ward.name;
                                wardSelect.appendChild(option);
                            });
                        });
                });

                // Sự kiện thay đổi phường/xã
                document.getElementById(`ward-${id}`).addEventListener("change", function() {
                    let wardName = this.options[this.selectedIndex].text;
                    document.getElementById(`ward_name-${id}`).value = wardName;
                });
            });

            // Mở modal
            document.querySelectorAll('.update-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-donhang-id');
                    const modal = document.getElementById(`update-modal-${id}`);
                    modal.classList.add('active');
                });
            });

            // Đóng modal
            document.querySelectorAll('.close-modal, .close-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    this.closest('.modal').classList.remove('active');
                });
            });

            // Hủy đơn hàng với SweetAlert2
            document.querySelectorAll('.cancel-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    Swal.fire({
                        title: 'Xác nhận hủy đơn hàng',
                        text: 'Bạn có chắc chắn muốn hủy đơn hàng này không? Hành động này không thể hoàn tác.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#ff4d4d',
                        cancelButtonColor: '#7f8c8d',
                        confirmButtonText: 'Hủy đơn hàng',
                        cancelButtonText: 'Không, giữ lại',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const id = this.getAttribute('data-donhang-id');
                            window.location.href = `giaidoan.php?action=cancel&id=${id}`;
                        }
                    });
                });
            });
        });
    </script>
</body>
<style>
/* Reset and Base Styles */
body {
    background-color: #f5f7fa;
    color: #333;
}

/* Order Tracking Container */
.order-tracking {
    max-width: 1200px;
    margin: 40px auto;
    padding: 0 20px;
}

.order-tracking h1 {
    font-size: 32px;
    font-weight: 600;
    color: #2c3e50;
    text-align: center;
    margin-bottom: 40px;
    position: relative;
}

.order-tracking h1::after {
    content: '';
    width: 60px;
    height: 3px;
    background-color: #3498db;
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
}

/* No Orders */
.no-orders {
    text-align: center;
    padding: 50px 20px;
    background-color: #fff;
    border-radius: 10px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
}

.no-orders i {
    font-size: 50px;
    color: #bdc3c7;
    margin-bottom: 20px;
}

.no-orders p {
    font-size: 18px;
    color: #7f8c8d;
    margin-bottom: 20px;
}

.shop-now {
    display: inline-block;
    padding: 12px 30px;
    background-color: #3498db;
    color: #fff;
    text-decoration: none;
    border-radius: 5px;
    font-weight: 500;
    transition: background-color 0.3s ease;
}

.shop-now:hover {
    background-color: #2980b9;
}

/* Order Card */
.order-card {
    background-color: #fff;
    border-radius: 15px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
    margin-bottom: 30px;
    overflow: hidden;
    transition: transform 0.3s ease;
}

.order-card:hover {
    transform: translateY(-5px);
}

/* Order Header */
.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background-color: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}

.order-id span {
    font-size: 16px;
    font-weight: 500;
    color: #7f8c8d;
}

.order-status-header {
    display: flex;
    align-items: center;
    gap: 10px;
}

.status-label {
    font-size: 16px;
    font-weight: 500;
    color: #7f8c8d;
}

.status-value {
    font-size: 16px;
    font-weight: 600;
    padding: 5px 15px;
    border-radius: 20px;
    text-transform: capitalize;
}

.status-value.chờ-xác-nhận {
    background-color: #ffeaa7;
    color: #d35400;
}

.status-value.đang-giao {
    background-color: #a29bfe;
    color: #4b0082;
}

.status-value.đã-giao {
    background-color: #55efc4;
    color: #006400;
}

.status-value.đã-hủy {
    background-color: #ff6b6b;
    color: #fff;
}

/* Section Styles */
.section {
    padding: 20px;
    border-bottom: 1px solid #e9ecef;
}

.section h2 {
    font-size: 20px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Address Info */
.address-info .info-row {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 10px;
}

.info-item {
    flex: 1;
    min-width: 250px;
}

.info-item label {
    font-size: 14px;
    font-weight: 500;
    color: #7f8c8d;
    display: block;
    margin-bottom: 5px;
}

.info-item span {
    font-size: 16px;
    color: #2c3e50;
}

.action-buttons {
    margin-top: 15px;
    display: flex;
    gap: 10px;
}

.update-btn, .cancel-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
    transition: background-color 0.3s ease;
}

.update-btn {
    background-color: #3498db;
    color: #fff;
}

.update-btn:hover {
    background-color: #2980b9;
}

.cancel-btn {
    background-color: #ff4d4d;
    color: #fff;
}

.cancel-btn:hover {
    background-color: #e60000;
}

/* Product Info */
.product-info .product-item {
    display: flex;
    align-items: center;
    padding: 15px 0;
    border-bottom: 1px solid #e9ecef;
}

.product-info .product-item:last-child {
    border-bottom: none;
}

.product-image {
    width: 70px;
    height: 70px;
    object-fit: cover;
    border-radius: 8px;
    margin-right: 15px;
}

.product-details h4 {
    font-size: 16px;
    font-weight: 500;
    color: #2c3e50;
    margin-bottom: 5px;
}

.product-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    font-size: 14px;
    color: #7f8c8d;
}

.product-meta .price {
    color: #e74c3c;
    font-weight: 500;
}

.product-meta .subtotal {
    font-weight: 500;
    color: #2c3e50;
}

/* Order Summary */
.order-summary .summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
}

.summary-row .label {
    font-size: 16px;
    font-weight: 500;
    color: #7f8c8d;
}

.summary-row .value {
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
}

/* Order Dates */
.order-dates {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
}

.date-item {
    flex: 1;
    min-width: 200px;
}

.date-item label {
    font-size: 14px;
    font-weight: 500;
    color: #7f8c8d;
    display: block;
    margin-bottom: 5px;
}

.date-item span {
    font-size: 16px;
    color: #2c3e50;
}

/* Delivery Status */
.delivery-status .status-bar {
    display: flex;
    justify-content: space-between;
    position: relative;
    margin-top: 20px;
}

.status-line .progress {
    height: 100%;
    background-color: #28a745;
    transition: width 0.5s ease;
    z-index: 2;
}

.status-item {
    text-align: center;
    flex: 1;
    position: relative;
    z-index: 3;
}

.status-item .status-icon {
    display: block;
    font-size: 24px;
    color: #bdc3c7;
    margin-bottom: 5px;
    transition: color 0.3s ease;
}

.status-item .status-text {
    font-size: 14px;
    color: #7f8c8d;
    transition: color 0.3s ease;
}

.status-item.active .status-icon,
.status-item.completed .status-icon {
    color: #28a745;
}

.status-item.active .status-text,
.status-item.completed .status-text {
    color: #28a745;
    font-weight: 500;
}

.status-item.canceled .status-icon,
.status-item.canceled .status-text {
    color: #ff4d4d;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: #fff;
    padding: 30px;
    width: 90%;
    max-width: 500px;
    border-radius: 10px;
    position: relative;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-content h3 {
    font-size: 22px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 20px;
    text-align: center;
}

.close-modal {
    position: absolute;
    top: 15px;
    right: 15px;
    font-size: 24px;
    color: #7f8c8d;
    cursor: pointer;
    transition: color 0.3s ease;
}

.close-modal:hover {
    color: #2c3e50;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-size: 14px;
    font-weight: 500;
    color: #7f8c8d;
    margin-bottom: 5px;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 10px;
    border: 1px solid #e9ecef;
    border-radius: 5px;
    font-size: 16px;
    color: #2c3e50;
    transition: border-color 0.3s ease;
}

.form-group input:focus,
.form-group select:focus {
    border-color: #3498db;
    outline: none;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

.submit-btn,
.close-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.submit-btn {
    background-color: #3498db;
    color: #fff;
}

.submit-btn:hover {
    background-color: #2980b9;
}

.close-btn {
    background-color: #e9ecef;
    color: #2c3e50;
}

.close-btn:hover {
    background-color: #d1d4d7;
}

/* Copyright */
.copyright {
    text-align: center;
    padding: 20px 0;
    background-color: #fff;
    border-top: 1px solid #e9ecef;
}

.copyright hr {
    display: none;
}

.copyright p {
    font-size: 14px;
    color: #7f8c8d;
}
/* Tùy chỉnh SweetAlert2 */
.swal2-popup {
    font-family: 'Poppins', sans-serif;
}

.swal2-title {
    font-size: 24px;
    font-weight: 600;
    color: #2c3e50;
}

.swal2-content {
    font-size: 16px;
    color: #7f8c8d;
}

.swal2-confirm, .swal2-cancel {
    font-size: 14px;
    font-weight: 500;
    padding: 10px 20px;
    border-radius: 5px;
}

.swal2-confirm:focus, .swal2-cancel:focus {
    box-shadow: none;
}
</style>
</html>