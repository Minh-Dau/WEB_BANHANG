<?php
session_start();

// Kiểm tra nếu chưa đăng nhập hoặc không phải admin hoặc nhân viên
if (!isset($_SESSION['user']) || 
    ($_SESSION['user']['phanquyen'] !== 'admin' && $_SESSION['user']['phanquyen'] !== 'nhanvien')) {
    header("Location: dangnhap.php");
    exit();
}

// Kiểm tra quyền quản lý đơn hàng
include 'config.php';
$user_id = $_SESSION['user']['id']; // Lấy ID của người dùng hiện tại từ session

// Kiểm tra quyền manage_order trong employee_permissions
$has_order_permission = false;
if ($_SESSION['user']['phanquyen'] === 'nhanvien') {
    $sql_permission = "SELECT permission FROM employee_permissions WHERE user_id = ? AND permission = 'manage_order'";
    $stmt_permission = $conn->prepare($sql_permission);
    $stmt_permission->bind_param("i", $user_id);
    $stmt_permission->execute();
    $result_permission = $stmt_permission->get_result();
    $has_order_permission = $result_permission->num_rows > 0;
    $stmt_permission->close();
} else if ($_SESSION['user']['phanquyen'] === 'admin') {
    // Admin mặc định có tất cả quyền, bao gồm manage_order
    $has_order_permission = true;
}

$new_review_count = 0;
$new_order_count = 0; // Biến để lưu số đơn hàng chưa in
$notifications = [];

// Truy vấn số lượng đánh giá chưa duyệt
$sql_reviews = "SELECT danhgia.id, danhgia.user_id, danhgia.sanpham_id, danhgia.created_at, 
               frm_dangky.hoten AS user_name, sanpham.tensanpham AS product_name 
        FROM danhgia 
        JOIN frm_dangky ON danhgia.user_id = frm_dangky.id 
        JOIN sanpham ON danhgia.sanpham_id = sanpham.id 
        WHERE danhgia.is_seen = 0 
        ORDER BY danhgia.created_at DESC";
$result_reviews = $conn->query($sql_reviews);
if ($result_reviews === false) {
    echo "Lỗi SQL (đánh giá): " . $conn->error;
    exit;
}
if ($result_reviews->num_rows > 0) {
    $new_review_count = $result_reviews->num_rows;
    while ($row = $result_reviews->fetch_assoc()) {
        $notifications[] = $row;
    }
}

// Truy vấn số lượng đơn hàng chưa in
$sql_orders = "SELECT COUNT(*) as count FROM oder WHERE invoice_status = 'Chưa in'";
$result_orders = $conn->query($sql_orders);
if ($result_orders === false) {
    echo "Lỗi SQL (đơn hàng): " . $conn->error;
    exit;
}
if ($result_orders->num_rows > 0) {
    $new_order_count = $result_orders->fetch_assoc()['count'];
}

// Xử lý duyệt đánh giá (nếu có)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['review_id'])) {
    $review_id = $_POST['review_id'];
    $sql = "UPDATE danhgia SET trangthaiduyet = 1, is_seen = 1 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $review_id);
    if ($stmt->execute()) {
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=Failed to approve review");
        exit();
    }
    $stmt->close();
}

$conn->close();
?>
<!-- Phần thông báo admin -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Kiểm tra quyền manage_order
        let hasOrderPermission = <?php echo json_encode($has_order_permission); ?>;
        if (!hasOrderPermission) {
            alert("Bạn không có quyền truy cập trang này!");
            window.location.href = "admin.php?error=You do not have permission to access this page.";
            return; // Dừng thực thi script nếu không có quyền
        }

        // Tiếp tục xử lý nếu có quyền
        let newReviewCount = <?php echo $new_review_count; ?>;
        let newOrderCount = <?php echo $new_order_count; ?>; // Số đơn hàng chưa in
        let notificationList = document.getElementById("notificationList");
        let reviewCountBadge = document.getElementById("reviewCountBadge");
        let orderCountBadge = document.getElementById("orderCountBadge"); // Badge cho đơn hàng

        // Cập nhật badge số lượng đánh giá chưa duyệt
        if (newReviewCount > 0) {
            reviewCountBadge.textContent = newReviewCount;
            reviewCountBadge.style.display = "inline";
        } else {
            reviewCountBadge.style.display = "none";
        }

        // Cập nhật badge số lượng đơn hàng chưa in
        if (newOrderCount > 0) {
            orderCountBadge.textContent = newOrderCount;
            orderCountBadge.style.display = "inline";
        } else {
            orderCountBadge.style.display = "none";
        }

        // Cập nhật danh sách thông báo trong dropdown (đánh giá)
        if (newReviewCount > 0) {
            notificationList.innerHTML = "";
            let notifications = <?php echo json_encode($notifications); ?>;
            notifications.forEach(function(notif) {
                let item = document.createElement("li");
                item.className = "dropdown-item";

                let link = document.createElement("a");
                link.href = "chitietsanpham.php?id=" + notif.sanpham_id + "&review_id=" + notif.id;
                link.textContent = "Đánh giá từ (" + notif.user_name + ") về sản phẩm (" + notif.product_name + ")";
                item.appendChild(link);

                // Thêm nút Duyệt (gửi form để load lại trang)
                let approveButton = document.createElement("form");
                approveButton.method = "POST";
                approveButton.style.display = "inline";
                let input = document.createElement("input");
                input.type = "hidden";
                input.name = "review_id";
                input.value = notif.id;
                approveButton.appendChild(input);
                let button = document.createElement("button");
                button.type = "submit";
                button.className = "btn btn-sm btn-success ms-2";
                button.textContent = "Duyệt";
                approveButton.appendChild(button);
                item.appendChild(approveButton);

                notificationList.appendChild(item);
            });
        } else {
            notificationList.innerHTML = '<li><a class="dropdown-item" href="#">Không có thông báo mới</a></li>';
        }
    });
</script>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="" />
        <meta name="author" content="" />
        <title>Tables - SB Admin</title>
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    </head>
    <style>
    .filter-section {
        margin-bottom: 15px;
    }

    .filter-section label {
        margin-right: 10px;
        font-weight: bold;
    }

    .filter-section select {
        padding: 5px 10px; 
        border: 1px solid #ccc; 
        border-radius: 8px;
        cursor: pointer; 
        font-size: 14px; 
        background-color: #fff;
        transition: border-color 0.3s ease; 
    }

    .filter-section select:focus {
        outline: none; 
        border-color: #007bff; 
        box-shadow: 0 0 5px rgba(0, 123, 255, 0.3); 
    }
    </style>
    <body class="sb-nav-fixed">
        <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
            <!-- Navbar Brand-->
            <a class="navbar-brand ps-3" href="admin.php">HUSTLE STONIE</a>
            <!-- Sidebar Toggle-->
            <button class="btn btn-link btn-sm oder-1 oder-lg-0 me-4 me-lg-0" id="sidebarToggle" href="#!"><i class="fas fa-bars"></i></button>
            <!-- Navbar Search-->
            <form class="d-none d-md-inline-block form-inline ms-auto me-0 me-md-3 my-2 my-md-0">
            </form>
            <!-- Navbar-->
            <ul class="navbar-nav ms-auto ms-md-0 me-3 me-lg-4">
                <!-- Chuông thông báo -->
                <!-- Icon User -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user fa-fw"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                        <li><a class="dropdown-item" href="logout.php">Đăng xuất</a></li>
                    </ul>
                </li>
            </ul>
        </nav>
        <div id="layoutSidenav">
            <div id="layoutSidenav_nav">
                <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
                    <div class="sb-sidenav-menu">
                        <div class="nav">
                            <div class="sb-sidenav-menu-heading">Core</div>
                            <a class="nav-link" href="admin.php">
                                <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                                QUẢN LÝ
                            </a>
                            <div class="sb-sidenav-menu-heading">Interface</div>
                            <a class="nav-link" href="quanlysanpham.php">
                                <div class="sb-nav-link-icon"><i class="fas fa-table"></i></div>
                                QUẢN LÝ SẢN PHẨM
                            </a>
                            <a class="nav-link" href="quanlynguoidung.php">
                                <div class="sb-nav-link-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                QUẢN LÝ NGƯỜI DÙNG
                            </a>
                            <!-- Mục QUẢN LÝ ĐƠN HÀNG với badge số lượng đơn hàng chưa in -->
                            <a class="nav-link" href="quanlydonhang.php">
                                <div class="sb-nav-link-icon">
                                    <i class="fas fa-box"></i> 
                                </div>
                                QUẢN LÝ ĐƠN HÀNG
                                <span id="orderCountBadge" class="badge bg-danger ms-2" style="display: none;">0</span>
                            </a>
                            <a class="nav-link" href="quanlydanhgia.php">
                                <div class="sb-nav-link-icon">
                                    <i class="fas fa-star"></i>
                                   
                                </div>
                                QUẢN LÝ ĐÁNH GIÁ
                                <span id="reviewCountBadge" class="badge bg-danger ms-2" style="display: none;">0</span>
                            </a>
                            <a class="nav-link" href="quanly_vanchuyen.php">
                                <div class="sb-nav-link-icon">
                                    <i class="fas fa-truck"></i>  
                                </div>
                                QUẢN LÝ VẬN CHUYỂN 
                            </a>
                            <a class="nav-link" href="quanly_khuyenmai.php">
                                <div class="sb-nav-link-icon">
                                    <i class="fas fa-tags"></i>  
                                </div>
                                QUẢN LÝ KHUYẾN MÃI 
                            </a>
                        </div>
                    </div>
                </nav>
            </div>
            <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid px-4">
                    <h1 class="mt-4">QUẢN LÝ ĐƠN HÀNG</h1>
                    <ol class="breadcrumb mb-4">
                        <li class="breadcrumb-item"><a href="index.html">QUẢN LÝ</a></li>
                        <li class="breadcrumb-item active">QUẢN LÝ ĐƠN HÀNG</li>
                    </ol>
                    <div class="card mb-4"></div>
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-table me-1"></i>
                            DANH SÁCH ĐƠN HÀNG
                        </div>
                        <div class="card-body">
                        <table id="datatablesSimple">
                            <div class="filter-section">
                                <label for="statusFilter">Lọc đơn hàng theo trạng thái: </label>
                                <select id="statusFilter" name="statusFilter">
                                    <option value="">Tất cả</option>
                                    <option value="Chờ xác nhận">Chờ xác nhận</option>
                                    <option value="Đã xác nhận">Đã xác nhận</option>
                                    <option value="Đang giao">Đang giao</option>
                                    <option value="Đã giao">Đã giao</option>
                                    <option value="Đã hủy">Đã hủy</option>
                                </select>
                            </div>
                            <thead>
                                <tr>
                                    <th>Ngày đặt hàng</th>
                                    <th>Phương thức thanh toán</th>
                                    <th>Trạng thái</th>
                                    <th>Phí ship</th>
                                    <th>Trạng thái thanh toán</th>
                                    <th>Tổng tiền</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tfoot>
                                <tr>
                                    <th>Ngày đặt hàng</th>
                                    <th>Phương thức thanh toán</th>
                                    <th>Trạng thái</th>
                                    <th>Phí ship</th>
                                    <th>Trạng thái thanh toán</th>
                                    <th>Tổng tiền</th>
                                    <th>Thao tác</th>
                                </tr>
                            </tfoot>
                            <tbody>
                                <?php
                                include 'config.php';
                                $sql = "SELECT o.*, u.hoten AS user_name, u.email AS user_email, u.sdt AS user_phone, u.diachi AS user_address 
                                        FROM oder o 
                                        JOIN frm_dangky u ON o.user_id = u.id";
                                $result = $conn->query($sql);

                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $oder_id = $row["id"];
                                        $oder_details_sql = "SELECT od.*, sp.tensanpham, sp.img 
                                                            FROM oder_detail od 
                                                            JOIN sanpham sp ON od.sanpham_id = sp.id 
                                                            WHERE od.oder_id = ?";
                                        $stmt = $conn->prepare($oder_details_sql);
                                        $stmt->bind_param("i", $oder_id);
                                        $stmt->execute();
                                        $oder_details_result = $stmt->get_result();
                                        $oder_details = [];
                                        if ($oder_details_result->num_rows > 0) {
                                            while ($item = $oder_details_result->fetch_assoc()) {
                                                $oder_details[] = $item;
                                            }
                                        }
                                        $stmt->close();
                                        $oder_details_json = json_encode($oder_details);
                                        echo "<tr>";
                                        echo "<td>" . $row["ngaydathang"] . "</td>";
                                        echo "<td>" . $row["payment_method"] . "</td>";
                                        echo "<td>" . $row["trangthai"] . "</td>";
                                        echo "<td>" . number_format($row["shipping_cost"], 0, ',', '.') . " VND</td>";
                                        echo "<td>" . $row["payment_status"] . "</td>";
                                        echo "<td>" . number_format($row["total"], 0, ',', '.') . " VND</td>"; // Tổng tiền di chuyển lên trước Hành động
                                        echo "<td>";
                                        echo "<button class='btn btn-info btn-sm view-btn'
                                            data-id='" . $row["id"] . "'
                                            data-user_id='" . $row["user_id"] . "'
                                            data-total='" . $row["total"] . "'
                                            data-ngaydathang='" . $row["ngaydathang"] . "'
                                            data-payment_method='" . $row["payment_method"] . "'
                                            data-trangthai='" . $row["trangthai"] . "'
                                            data-shipping_cost='" . $row["shipping_cost"] . "'
                                            data-payment_status='" . $row["payment_status"] . "'
                                            data-user_name='" . htmlspecialchars($row["user_name"], ENT_QUOTES, 'UTF-8') . "'
                                            data-user_email='" . htmlspecialchars($row["user_email"], ENT_QUOTES, 'UTF-8') . "'
                                            data-user_phone='" . htmlspecialchars($row["user_phone"], ENT_QUOTES, 'UTF-8') . "'
                                            data-user_address='" . htmlspecialchars($row["user_address"], ENT_QUOTES, 'UTF-8') . "'
                                            data-oder_details='" . htmlspecialchars($oder_details_json, ENT_QUOTES, 'UTF-8') . "'>
                                            Xem chi tiết
                                        </button> ";
                                        // Xử lý nút "Xác nhận" dựa trên trạng thái
                                        $trangthai = $row["trangthai"];
                                        $buttonText = "";
                                        $buttonClass = "btn btn-warning btn-sm update-status-btn";
                                        $disabled = "";
                            
                                        if ($trangthai == "Chờ xác nhận") {
                                            $buttonText = "Xác nhận";
                                        } elseif ($trangthai == "Đã xác nhận") {
                                            $buttonText = "Đang giao";
                                        } elseif ($trangthai == "Đang giao") {
                                            $buttonText = "Đã giao";
                                        } elseif ($trangthai == "Đã giao") {
                                            $buttonText = "Đã giao";
                                            $disabled = "disabled";
                                        }
                                        if ($trangthai == "Đã hủy") {
                                            $buttonText = "Đã hủy";
                                            $buttonClass = "btn btn-danger btn-sm"; 
                                            $disabled="disabled";
                                        }
                                        echo "<button class='$buttonClass' $disabled
                                        data-id='" . $row["id"] . "'
                                        data-trangthai='" . $trangthai . "'>
                                        $buttonText
                                    </button> ";
                                        $invoiceStatus = $row["invoice_status"]; 
                                        echo "<button class='btn btn-secondary btn-sm printOrder' 
                                        data-invoice-status='" . htmlspecialchars($invoiceStatus) . "' 
                                        data-id='" . htmlspecialchars($row["id"]) . "'>
                                        " . ($invoiceStatus === "Đã in" ? "Đã in" : "In Đơn") . "
                                  </button>";
                           

                                        echo "</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='7'>Không có đơn hàng nào</td></tr>";
                                }
                                $conn->close();
                                ?>
                            </tbody>
                        </table>
                            <div id="oderDetailsSection" class="mt-4" style="display: none;">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>CHI TIẾT ĐƠN HÀNG</h5>
                                    </div>
                                    <div class="card-body">
                                        <form>
                                            <h5 class="mt-4">Thông tin người dùng</h5>
                                            <div class="row mb-3">
                                                <div class="col-md-3">
                                                    <label for="detailUserName" class="form-label">Tên người dùng</label>
                                                    <input type="text" class="form-control" id="detailUserName" readonly>
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="detailUserEmail" class="form-label">Email</label>
                                                    <input type="text" class="form-control" id="detailUserEmail" readonly>
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="detailUserPhone" class="form-label">Số điện thoại</label>
                                                    <input type="text" class="form-control" id="detailUserPhone" readonly>
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="detailUserAddress" class="form-label">Địa chỉ</label>
                                                    <input type="text" class="form-control" id="detailUserAddress" readonly>
                                                </div>
                                            </div>
                                            <div class="row mb-3">
                                                
                                                <div class="col-md-3">
                                                    <label for="detailoderDate" class="form-label">Ngày đặt hàng</label>
                                                    <input type="text" class="form-control" id="detailoderDate" readonly>
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="detailPaymentMethod" class="form-label">Phương thức thanh toán</label>
                                                    <input type="text" class="form-control" id="detailPaymentMethod" readonly>
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="detailStatus" class="form-label">Trạng thái</label>
                                                    <input type="text" class="form-control" id="detailStatus" readonly>
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="detailPaymentStatus" class="form-label">Trạng thái thanh toán</label>
                                                    <input type="text" class="form-control" id="detailPaymentStatus" readonly>
                                                </div>
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-md-3">
                                                    <label for="detailShippingCost" class="form-label">Phí ship</label>
                                                    <input type="text" class="form-control" id="detailShippingCost" readonly>
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="detailTotal" class="form-label">Tổng tiền</label>
                                                    <input type="text" class="form-control" id="detailTotal" readonly>
                                                </div>
                                            </div>
                                            <h5 class="mt-4">Danh sách sản phẩm</h5>
                                            <table class="table table-bodered">
                                                <thead>
                                                    <tr>
                                                        <th>Hình ảnh</th>
                                                        <th>Tên sản phẩm</th>
                                                        <th>Số lượng</th>
                                                        <th>Giá</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="oderItemsTableBody">
                                                </tbody>
                                            </table>
                                            <button type="button" class="btn btn-secondary" id="closeDetails">Đóng</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script src="js/datatables-simple-demo.js"></script>
    </body>
</html>
<!-- này là trạng thái đơn-->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const viewButtons = document.querySelectorAll('.view-btn');
    const updateStatusButtons = document.querySelectorAll('.update-status-btn');
    const oderDetailsSection = document.getElementById('oderDetailsSection');
    const closeDetailsButton = document.getElementById('closeDetails');
    const oderItemsTableBody = document.getElementById('oderItemsTableBody');
    viewButtons.forEach(button => {
        button.addEventListener('click', function () {
            const id = button.getAttribute('data-id');
            const userId = button.getAttribute('data-user_id');
            const total = button.getAttribute('data-total');
            const oderDate = button.getAttribute('data-ngaydathang');
            const paymentMethod = button.getAttribute('data-payment_method');
            const status = button.getAttribute('data-trangthai');
            const shippingCost = button.getAttribute('data-shipping_cost');
            const paymentStatus = button.getAttribute('data-payment_status');
            const userName = button.getAttribute('data-user_name');
            const userEmail = button.getAttribute('data-user_email');
            const userPhone = button.getAttribute('data-user_phone');
            const userAddress = button.getAttribute('data-user_address');
            const oderDetails = JSON.parse(button.getAttribute('data-oder_details'));

            document.getElementById('detailUserName').value = userName || 'Không có thông tin';
            document.getElementById('detailUserEmail').value = userEmail || 'Không có thông tin';
            document.getElementById('detailUserPhone').value = userPhone || 'Không có thông tin';
            document.getElementById('detailUserAddress').value = userAddress || 'Không có thông tin';

            document.getElementById('detailTotal').value = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(total);
            document.getElementById('detailoderDate').value = oderDate;
            document.getElementById('detailPaymentMethod').value = paymentMethod;
            document.getElementById('detailStatus').value = status;
            document.getElementById('detailShippingCost').value = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(shippingCost);
            document.getElementById('detailPaymentStatus').value = paymentStatus;

            oderItemsTableBody.innerHTML = '';
            if (oderDetails.length > 0) {
                oderDetails.forEach(item => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td><img src="${item.img}" alt="${item.tensanpham}" style="width: 50px; height: 50px; object-fit: cover;"></td>
                        <td>${item.tensanpham}</td>
                        <td>${item.soluong}</td>
                        <td>${new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(item.gia)}</td>
                    `;
                    oderItemsTableBody.appendChild(row);
                });
            } else {
                oderItemsTableBody.innerHTML = '<tr><td colspan="5">Không có sản phẩm nào trong đơn hàng này</td></tr>';
            }

            oderDetailsSection.style.display = 'block';
            oderDetailsSection.scrollIntoView({ behavior: 'smooth' });
        });
    });

    closeDetailsButton.addEventListener('click', function () {
        oderDetailsSection.style.display = 'none';
    });

    // Xử lý nút "Xác nhận", "Đang giao", "Đã giao", 
    updateStatusButtons.forEach(button => {
        button.addEventListener('click', function () {
            const id = button.getAttribute('data-id');
            const currentStatus = button.getAttribute('data-trangthai');
            let newStatus = '';
            if (currentStatus === 'Chờ xác nhận') {
                newStatus = 'Đã xác nhận';
            } else if (currentStatus === 'Đã xác nhận') {
                newStatus = 'Đang giao';
            } else if (currentStatus === 'Đang giao') {
                newStatus = 'Đã giao';
            } else if (currentStatus === 'Đã giao') {
                newStatus = 'Đã giao';
            }

            fetch('update_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${id}&trangthai=${newStatus}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.setAttribute('data-trangthai', newStatus);
                    button.textContent = newStatus === 'Đã xác nhận' ? 'Đang giao' :
                                         newStatus === 'Đang giao' ? 'Đã giao' :
                                         newStatus === 'Đã giao' ? 'Đã hủy' : '';
                    if (newStatus === 'Đã hủy') {
                        button.remove();
                    }
                    const statusCell = button.closest('tr').children[2];
                    statusCell.textContent = newStatus;
                } else {
                    alert('Cập nhật trạng thái thất bại!');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Đã xảy ra lỗi khi cập nhật trạng thái!');
            });
        });
    });
});
</script>
<!-- này là lọc đơn trạng thái đơn-->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const viewButtons = document.querySelectorAll('.view-btn');
    const updateStatusButtons = document.querySelectorAll('.update-status-btn');
    const oderDetailsSection = document.getElementById('oderDetailsSection');
    const closeDetailsButton = document.getElementById('closeDetails');
    const oderItemsTableBody = document.getElementById('oderItemsTableBody');
    const statusFilter = document.getElementById('statusFilter');
    const tableRows = document.querySelectorAll('#datatablesSimple tbody tr');

    // Xử lý nút "Xem chi tiết"
    viewButtons.forEach(button => {
        button.addEventListener('click', function () {
            const id = button.getAttribute('data-id');
            const userId = button.getAttribute('data-user_id');
            const total = button.getAttribute('data-total');
            const oderDate = button.getAttribute('data-ngaydathang');
            const paymentMethod = button.getAttribute('data-payment_method');
            const status = button.getAttribute('data-trangthai');
            const shippingCost = button.getAttribute('data-shipping_cost');
            const paymentStatus = button.getAttribute('data-payment_status');
            const userName = button.getAttribute('data-user_name');
            const userEmail = button.getAttribute('data-user_email');
            const userPhone = button.getAttribute('data-user_phone');
            const userAddress = button.getAttribute('data-user_address');
            const oderDetails = JSON.parse(button.getAttribute('data-oder_details'));

            document.getElementById('detailUserName').value = userName || 'Không có thông tin';
            document.getElementById('detailUserEmail').value = userEmail || 'Không có thông tin';
            document.getElementById('detailUserPhone').value = userPhone || 'Không có thông tin';
            document.getElementById('detailUserAddress').value = userAddress || 'Không có thông tin';

            document.getElementById('detailTotal').value = new Intl.NumberFormat('vi-VN').format(total) + " VND";
            document.getElementById('detailoderDate').value = oderDate;
            document.getElementById('detailPaymentMethod').value = paymentMethod;
            document.getElementById('detailStatus').value = status;
            document.getElementById('detailShippingCost').value = new Intl.NumberFormat('vi-VN').format(shippingCost) + " VND";
            document.getElementById('detailPaymentStatus').value = paymentStatus;

            oderItemsTableBody.innerHTML = '';
            if (oderDetails.length > 0) {
                oderDetails.forEach(item => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td><img src="${item.img}" alt="${item.tensanpham}" style="width: 50px; height: 50px; object-fit: cover;"></td>
                        <td>${item.tensanpham}</td>
                        <td>${item.soluong}</td>
                        <td>${new Intl.NumberFormat('vi-VN').format(item.gia)} VND</td>
                    `;
                    oderItemsTableBody.appendChild(row);
                });
            } else {
                oderItemsTableBody.innerHTML = '<tr><td colspan="5">Không có sản phẩm nào trong đơn hàng này</td></tr>';
            }

            oderDetailsSection.style.display = 'block';
            oderDetailsSection.scrollIntoView({ behavior: 'smooth' });
        });
    });

    closeDetailsButton.addEventListener('click', function () {
        oderDetailsSection.style.display = 'none';
    });

    updateStatusButtons.forEach(button => {
        button.addEventListener('click', function () {
            const id = button.getAttribute('data-id');
            const currentStatus = button.getAttribute('data-trangthai');
            let newStatus = '';

            if (currentStatus === 'Chờ xác nhận') {
                newStatus = 'Đã xác nhận';
            } else if (currentStatus === 'Đã xác nhận') {
                newStatus = 'Đang giao';
            } else if (currentStatus === 'Đang giao') {
                newStatus = 'Đã giao';
            }

            fetch('update_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${id}&trangthai=${newStatus}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.setAttribute('data-trangthai', newStatus);
                    button.textContent = newStatus === 'Đã xác nhận' ? 'Đang giao' :
                                         newStatus === 'Đang giao' ? 'Đã giao' : 'Đã giao';
                    if (newStatus === 'Đã giao') {
                        button.setAttribute('disabled', 'disabled');
                    }
                    const statusCell = button.closest('tr').children[2]; // Cột "Trạng thái" giờ là cột thứ 3
                    statusCell.textContent = newStatus;
                } else {
                    alert('Cập nhật trạng thái thất bại!');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Đã xảy ra lỗi khi cập nhật trạng thái!');
            });
        });
    });

    // Xử lý lọc đơn hàng theo trạng thái
   statusFilter.addEventListener('change', function () {
    const selectedStatus = this.value;
    console.log("Selected Status:", selectedStatus);

    tableRows.forEach(row => {
        const statusCell = row.children[2]; 
        const rowStatus = statusCell.textContent.trim();
        console.log("Row Status:", rowStatus);

        if (selectedStatus === '' || rowStatus === selectedStatus) {
            row.style.display = ''; // Hiển thị hàng
        } else {
            row.style.display = 'none'; // Ẩn hàng
        }
    });
});
});
</script>
<!-- Bao gồm SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.getElementById("editUserForm").addEventListener("submit", function(e) {
    e.preventDefault(); // Ngăn form submit mặc định
    let formData = new FormData(this);
    Swal.fire({
        title: "Đang xử lý...",
        text: "Vui lòng chờ...",
        icon: "info",
        allowOutsideClick: false,
        showConfirmButton: false,
        timerProgressBar: true
    });
    fetch("update_nguoidung.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === "success"){
            Swal.fire({
                title: "Thành công!",
                text: data.message,
                icon: "success"
            }).then(() => {
                var modalEl = document.getElementById('editUserModal');
                var modal = bootstrap.Modal.getInstance(modalEl);
                modal.hide();
                location.reload();
            });
        } else {
            Swal.fire({
                title: "Lỗi!",
                text: data.message,
                icon: "error"
            });
        }
    })
    .catch(error => {
        Swal.fire({
            title: "Lỗi!",
            text: "Có lỗi xảy ra. Vui lòng thử lại!",
            icon: "error"
        });
        console.error("Lỗi:", error);
    });
});
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.68/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.68/vfs_fonts.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const printButtons = document.querySelectorAll(".printOrder");

    printButtons.forEach(function (printButton) {
        let invoiceStatus = printButton.getAttribute("data-invoice-status");

        // Thiết lập trạng thái ban đầu của nút
        if (invoiceStatus === "Đã in") {
            printButton.textContent = "Đã in";
            printButton.disabled = true;
        } else {
            printButton.textContent = "In Đơn";
            printButton.disabled = false;
        }

        printButton.addEventListener("click", function () {
            if (invoiceStatus === "Đã in") {
                alert("Hóa đơn này đã được in!");
                return;
            }

            function maskPhoneNumber(phone) {
                return phone;
            }
            function maskEmail(email) {
                let parts = email.split("@");
                return parts[0].length > 3 ? parts[0].slice(0, 3) + "*****@" + parts[1] : "*****@" + parts[1];
            }

            let userName = document.getElementById("detailUserName").value;
            let email = maskEmail(document.getElementById("detailUserEmail").value);
            let phone = maskPhoneNumber(document.getElementById("detailUserPhone").value);
            let address = document.getElementById("detailUserAddress").value;
            let orderDate = document.getElementById("detailoderDate").value;
            let paymentMethod = document.getElementById("detailPaymentMethod").value;
            let status = document.getElementById("detailStatus").value;
            let paymentStatus = document.getElementById("detailPaymentStatus").value;
            let shippingCost = document.getElementById("detailShippingCost").value + " VND";
            let total = document.getElementById("detailTotal").value.replace("VND VND", "VND");

            let docDefinition = {
                content: [
                    { text: "HÓA ĐƠN MUA HÀNG", style: "header" },
                    {
                        table: {
                            widths: ["35%", "65%"],
                            body: [
                                [{ text: "Tên khách hàng:", style: "boldText" }, userName],
                                [{ text: "Email:", style: "boldText" }, email],
                                [{ text: "Số điện thoại:", style: "boldText" }, phone],
                                [{ text: "Địa chỉ giao hàng:", style: "boldText" }, address],
                                [{ text: "Ngày đặt hàng:", style: "boldText" }, orderDate],
                                [{ text: "Phương thức thanh toán:", style: "boldText" }, paymentMethod],
                                [{ text: "Trạng thái đơn hàng:", style: "boldText" }, status],
                                [{ text: "Trạng thái thanh toán:", style: "boldText" }, paymentStatus],
                            ],
                        },
                        layout: "lightHorizontalLines",
                        margin: [0, 10, 0, 10],
                    },
                    { text: "Chi tiết đơn hàng", style: "subheader" },
                    {
                        table: {
                            headerRows: 1,
                            widths: ["65%", "15%", "20%"],
                            body: [
                                [
                                    { text: "Sản phẩm", style: "tableHeader" },
                                    { text: "Số lượng", style: "tableHeader" },
                                    { text: "Đơn giá", style: "tableHeader" },
                                ],
                                ...Array.from(document.querySelectorAll("#oderItemsTableBody tr")).map(row => {
                                    let cells = Array.from(row.cells).slice(1);
                                    return [
                                        { text: cells[0].textContent.trim() },
                                        { text: cells[1].textContent.trim(), alignment: "center" },
                                        { text: cells[2].textContent.trim(), alignment: "right" }
                                    ];
                                }),
                            ],
                        },
                    },
                    {
                        table: {
                            widths: ["80%", "20%"],
                            body: [[
                                { text: "Tổng tiền:", style: "totalPrice" },
                                { text: total, style: "totalPrice", alignment: "right" }
                            ]],
                        },
                        layout: "noBorders",
                        margin: [0, 5, 0, 0],
                    },
                ],
                styles: {
                    header: { fontSize: 22, bold: true, alignment: "center", margin: [0, 0, 0, 10], color: "#EE4D2D" },
                    subheader: { fontSize: 16, bold: true, margin: [0, 10, 0, 5] },
                    tableHeader: { bold: true, fillColor: "#f3f3f3" },
                    boldText: { bold: true },
                    totalPrice: { fontSize: 14, bold: true, color: "red" },
                },
            };
            // Tạo và tải PDF
            pdfMake.createPdf(docDefinition).download("HoaDon.pdf");
            // Cập nhật UI
            printButton.textContent = "Đã in";
            printButton.disabled = true;
            invoiceStatus = "Đã in";
            // Lấy ID đơn hàng từ nút (hoặc từ button .view-btn nếu cần)
            const orderId = printButton.getAttribute("data-id");
            // Gửi yêu cầu cập nhật trạng thái hóa đơn về server
            fetch("updatehoadon.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ 
                    id: orderId, 
                    invoice_status: "Đã in" 
                }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log("Cập nhật trạng thái hóa đơn thành công");
                } else {
                    console.error("Lỗi cập nhật hóa đơn:", data.message);
                }
            })
            .catch(error => console.error("Lỗi kết nối:", error));
        });
    });
});
</script>
