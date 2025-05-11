<?php
session_start();

// Kiểm tra nếu chưa đăng nhập hoặc không phải admin hoặc nhân viên
if (!isset($_SESSION['user']) || 
    ($_SESSION['user']['phanquyen'] !== 'admin' && $_SESSION['user']['phanquyen'] !== 'nhanvien')) {
    header("Location: dangnhap.php");
    exit();
}

include 'config.php';

$new_review_count = 0;
$new_order_count = 0;
$notifications = [];
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
        let newReviewCount = <?php echo $new_review_count; ?>;
        let newOrderCount = <?php echo $new_order_count; ?>; // Số đơn hàng chưa in
        let notificationList = document.getElementById("notificationList");
        let reviewCountBadge = document.getElementById("reviewCountBadge");
        let orderCountBadge = document.getElementById("orderCountBadge"); // Badge cho đơn hàng
        let notificationCountBadge = document.getElementById("notificationCountBadge");

        // Cập nhật badge số lượng thông báo
        if (notificationCountBadge) {
            if (newReviewCount > 0) {
                notificationCountBadge.textContent = newReviewCount;
                notificationCountBadge.style.display = "inline";
            } else {
                notificationCountBadge.style.display = "none";
            }
        }

        // Cập nhật badge số lượng đánh giá chưa duyệt
        if (reviewCountBadge) {
            if (newReviewCount > 0) {
                reviewCountBadge.textContent = newReviewCount;
                reviewCountBadge.style.display = "inline";
            } else {
                reviewCountBadge.style.display = "none";
            }
        }

        // Cập nhật badge số lượng đơn hàng chưa in
        if (orderCountBadge) {
            if (newOrderCount > 0) {
                orderCountBadge.textContent = newOrderCount;
                orderCountBadge.style.display = "inline";
            } else {
                orderCountBadge.style.display = "none";
            }
        }

        // Cập nhật danh sách thông báo trong dropdown (đánh giá)
        if (notificationList) {
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="sb-nav-fixed">
    <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
        <!-- Navbar Brand-->
        <a class="navbar-brand ps-3" href="admin.php">HUSTLE STONIE</a>
        <!-- Sidebar Toggle-->
        <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" href="#!"><i class="fas fa-bars"></i></button>
        <!-- Navbar Search-->
        <form class="d-none d-md-inline-block form-inline ms-auto me-0 me-md-3 my-2 my-md-0">
        </form>
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
                        <div class="sb-sidenav-menu-heading">Thống kê</div>
                        <a class="nav-link" href="admin.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                            THỐNG KÊ
                        </a>
                        <div class="sb-sidenav-menu-heading">Quản lý</div>
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
                    <h1 class="mt-4">QUẢN LÝ NGƯỜI DÙNG</h1>
                    <ol class="breadcrumb mb-4">
                        <li class="breadcrumb-item"><a href="http://localhost/BAOCAO/quanlynguoidung.php">QUẢN LÝ</a></li>
                        <li class="breadcrumb-item active">QUẢN LÝ NGƯỜI DÙNG</li>
                    </ol>
                    <div class="card mb-4">
                    </div>
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-table me-1"></i>
                            DANH SÁCH NGƯỜI DÙNG
                        </div>
                        <div class="card-body">
                            <!-- Nút bấm mở Modal -->
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                Thêm người dùng mới
                            </button>
                            <table id="datatablesSimple">
                                <thead>
                                    <tr>
                                        <th>Tài khoản</th>
                                        <th>Tên</th>
                                        <th>Ảnh</th>
                                        <th>Email</th>
                                        <th>Quyền</th>
                                        <th>Số điện thoại</th>
                                        <th>Địa chỉ</th>
                                        <th>Trạng thái</th>
                                        <th>Hoạt động</th>
                                    </tr>
                                </thead>
                                <tfoot>
                                    <tr>
                                        <th>Tài khoản</th>
                                        <th>Tên</th>
                                        <th>Ảnh</th>
                                        <th>Email</th>
                                        <th>Quyền</th>
                                        <th>Số điện thoại</th>
                                        <th>Địa chỉ</th>
                                        <th>Trạng thái</th>
                                        <th>Hoạt động</th>
                                    </tr>
                                </tfoot>
                                <tbody>
                                    <?php
                                    include 'config.php';
                                    $sql = "SELECT * FROM frm_dangky ORDER BY id DESC";
                                    $result = $conn->query($sql);
                                    if ($result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($row["username"]) . "</td>";
                                            echo "<td>" . htmlspecialchars($row["hoten"]) . "</td>";
                                            echo "<td><img src='" . htmlspecialchars($row["anh"] ?? '/images/default.jpg') . "' width='50' height='50' onerror=\"this.onerror=null; this.src='/images/default.jpg';\"></td>";
                                            echo "<td>" . htmlspecialchars($row["email"]) . "</td>";
                                            echo "<td>" . htmlspecialchars($row["phanquyen"]) . "</td>";
                                            echo "<td>" . htmlspecialchars($row["sdt"] ?? '') . "</td>";
                                            $diachi = htmlspecialchars($row["diachi"] ?? '');
                                            $words = explode(' ', $diachi);
                                            $shortened = implode(' ', array_slice($words, 0, 15));
                                            if (count($words) > 15) {
                                                $shortened .= '...';
                                            }
                                            echo "<td>" . $shortened . "</td>";
                                            echo "<td>" . ($row["trangthai"] == "hoạt động" 
                                                ? "<span style='color: green; font-size: 20px;'>●</span> Hoạt Động" 
                                                : "<span style='color: red; font-size: 20px;'>●</span> Bị Khóa") . "</td>";
                                            echo "<td>
                                                <button class='btn btn-warning btn-sm edit-btn'
                                                    data-id='" . $row["id"] . "'
                                                    data-username='" . htmlspecialchars($row["username"]) . "'
                                                    data-hoten='" . htmlspecialchars($row["hoten"]) . "'
                                                    data-email='" . htmlspecialchars($row["email"]) . "'
                                                    data-sdt='" . htmlspecialchars($row["sdt"] ?? '') . "'
                                                    data-diachi='" . htmlspecialchars($row["diachi"] ?? '') . "'
                                                    data-phanquyen='" . htmlspecialchars($row["phanquyen"]) . "'
                                                    data-anh='" . htmlspecialchars($row["anh"] ?? '') . "'
                                                    data-trangthai='" . htmlspecialchars($row["trangthai"]) . "'
                                                    data-bs-toggle='modal'
                                                    data-bs-target='#editUserModal'>
                                                    <i class='fas fa-edit'></i>
                                                </button>";
                                            $loggedInRole = isset($_SESSION['user']['phanquyen']) ? $_SESSION['user']['phanquyen'] : '';
                                            $targetRole = $row["phanquyen"];
                                            if (($loggedInRole === 'admin' || $loggedInRole === 'nhanvien') && $targetRole === 'nhanvien') {
                                                echo "<button class='btn btn-info btn-sm permission-btn'
                                                            data-id='" . $row["id"] . "'
                                                            data-bs-toggle='modal'
                                                            data-bs-target='#permissionModal'>
                                                            <i class='fas fa-user-shield'></i>
                                                        </button>";
                                            } else {
                                                echo "<button class='btn btn-info btn-sm permission-btn' disabled
                                                            title='Chỉ admin có thể phân quyền cho nhân viên'>
                                                            <i class='fas fa-user-shield'></i>
                                                        </button>";
                                            }

                                            echo "<button class='btn btn-danger btn-sm delete-btn' data-id='" . $row["id"] . "'>
                                                    <i class='fas fa-trash-alt'></i>
                                                </button>
                                            </td>";                                            
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='9'>Không có người dùng nào</td></tr>";
                                    }
                                    $conn->close();
                                    ?>
                                </tbody>
                            </table>
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
    <!-- Modal Thêm người dùng -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="addUserForm" action="them_nguoidung.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addUserModalLabel">Thêm người dùng mới</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="hoten" class="form-label">Họ tên</label>
                            <input type="text" class="form-control" id="hoten" name="hoten" required maxlength="50">
                        </div>
                        <div class="mb-3">
                            <label for="username" class="form-label">Tên đăng nhập</label>
                            <input type="text" class="form-control" id="username" name="username" required maxlength="20" minlength="6">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Mật khẩu</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="phanquyen" class="form-label">Phân quyền</label>
                            <select class="form-select" id="phanquyen" name="phanquyen">
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                                <option value="nhanvien">Nhân Viên</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="sdt" class="form-label">Số điện thoại</label>
                            <input type="number" class="form-control" id="sdt" name="sdt" minlength="10">
                        </div>
                        <div class="mb-3">
                            <label for="province" class="form-label">Tỉnh/Thành phố</label>
                            <select class="form-select" id="province" name="province" required>
                                <option value="">Chọn tỉnh/thành phố</option>
                            </select>
                            <input type="hidden" id="province_name" name="province_name">
                        </div>
                        <div class="mb-3">
                            <label for="district" class="form-label">Quận/Huyện</label>
                            <select class="form-select" id="district" name="district" required>
                                <option value="">Chọn quận/huyện</option>
                            </select>
                            <input type="hidden" id="district_name" name="district_name">
                        </div>
                        <div class="mb-3">
                            <label for="ward" class="form-label">Phường/Xã</label>
                            <select class="form-select" id="ward" name="ward" required>
                                <option value="">Chọn phường/xã</option>
                            </select>
                            <input type="hidden" id="ward_name" name="ward_name">
                        </div>
                        <div class="mb-3">
                            <label for="specific_address" class="form-label">Địa chỉ cụ thể</label>
                            <input type="text" class="form-control" id="specific_address" name="specific_address" placeholder="Số nhà, tên đường..." required>
                        </div>
                        <div class="mb-3">
                            <label for="anh" class="form-label">Ảnh đại diện</label>
                            <input type="file" class="form-control" id="anh" name="anh">
                        </div>
                        <div class="mb-3">
                            <label for="trangthai" class="form-label">Trạng thái</label>
                            <select class="form-select" id="trangthai" name="trangthai">
                                <option value="hoạt động">Hoạt động</option>
                                <option value="đã khóa">Bị khóa</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                        <button type="submit" class="btn btn-primary">Lưu</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Modal Chỉnh Sửa Người Dùng -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Chỉnh sửa người dùng</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editUserForm" action="update_nguoidung.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="edit-id">
                        <div class="mb-3">
                            <label for="edit-hoten" class="form-label">Họ tên</label>
                            <input type="text" class="form-control" name="hoten" id="edit-hoten" required maxlength="50">
                        </div>
                        <div class="mb-3">
                            <label for="edit-username" class="form-label">Tên người dùng</label>
                            <input type="text" class="form-control" name="username" id="edit-username" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit-email" class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="edit-email" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit-password" class="form-label">Mật khẩu</label>
                            <input type="password" class="form-control" name="password" id="edit-password" placeholder="Nhập mật khẩu mới (nếu muốn thay đổi)">
                        </div>
                        <div class="mb-3">
                            <label for="edit-sdt" class="form-label">Số điện thoại</label>
                            <input type="text" class="form-control" name="sdt" id="edit-sdt">
                        </div>
                        <div class="mb-3">
                            <label for="edit-province" class="form-label">Tỉnh/Thành phố</label>
                            <select class="form-select" id="edit-province" name="province" required>
                                <option value="">Chọn tỉnh/thành phố</option>
                            </select>
                            <input type="hidden" id="edit-province_name" name="province_name">
                        </div>
                        <div class="mb-3">
                            <label for="edit-district" class="form-label">Quận/Huyện</label>
                            <select class="form-select" id="edit-district" name="district" required>
                                <option value="">Chọn quận/huyện</option>
                            </select>
                            <input type="hidden" id="edit-district_name" name="district_name">
                        </div>
                        <div class="mb-3">
                            <label for="edit-ward" class="form-label">Phường/Xã</label>
                            <select class="form-select" id="edit-ward" name="ward" required>
                                <option value="">Chọn phường/xã</option>
                            </select>
                            <input type="hidden" id="edit-ward_name" name="ward_name">
                        </div>
                        <div class="mb-3">
                            <label for="edit-specific_address" class="form-label">Địa chỉ cụ thể</label>
                            <input type="text" class="form-control" id="edit-specific_address" name="specific_address" placeholder="Số nhà, tên đường..." required>
                        </div>
                        <div class="mb-3">
                            <label for="edit-phanquyen" class="form-label">Quyền</label>
                            <select class="form-control" name="phanquyen" id="edit-phanquyen">
                                <option value="admin">Admin</option>
                                <option value="nhanvien">Nhân Viên</option>
                                <option value="user">User</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit-trangthai" class="form-label">Trạng thái</label>
                            <select class="form-control" name="trangthai" id="edit-trangthai">
                                <option value="hoạt động">Hoạt động</option>
                                <option value="đã khóa">Bị Khóa</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit-anh" class="form-label">Ảnh</label>
                            <br>
                            <img id="current-user-img" src="" width="50" height="50" style="margin-bottom: 10px;">
                            <input type="hidden" name="current_anh" id="current-anh">
                            <input type="file" class="form-control" name="anh" id="edit-anh">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Modal Phân Quyền -->
    <div class="modal fade" id="permissionModal" tabindex="-1" aria-labelledby="permissionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl custom-modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="permissionModalLabel">Phân Quyền Cho Người Dùng</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="permissionUserId" name="user_id">
                    <div class="mb-3">
                        <select class="form-select" id="roleSelect" name="phanquyen">
                            <option value="nhanvien">Nhân viên</option>
                        </select>
                    </div>
                    <div id="employeePermissions" style="display: none;">
                        <h6>Sản phẩm</h6>
                        <div class="d-flex flex-row">
                            <div class="form-check form-switch me-3">
                                <input class="form-check-input" type="checkbox" value="add_product" id="add_product">
                                <label class="form-check-label" for="add_product">Thêm sản phẩm</label>
                            </div>
                            <div class="form-check form-switch me-3">
                                <input class="form-check-input" type="checkbox" value="edit_product" id="edit_product">
                                <label class="form-check-label" for="edit_product">Sửa sản phẩm</label>
                            </div>
                            <div class="form-check form-switch me-3">
                                <input class="form-check-input" type="checkbox" value="delete_product" id="delete_product">
                                <label class="form-check-label" for="delete_product">Xóa sản phẩm</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" value="add_category" id="add_category">
                                <label class="form-check-label" for="add_category">Thêm danh mục</label>
                            </div>
                            <div class="form-check form-switch me-3">
                                <input class="form-check-input" type="checkbox" value="edit_category" id="edit_category">
                                <label class="form-check-label" for="edit_category">Sửa danh mục</label>
                            </div>
                            <div class="form-check form-switch me-3">
                                <input class="form-check-input" type="checkbox" value="delete_category" id="delete_category">
                                <label class="form-check-label" for="delete_category">Xóa danh mục</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" value="toggle_category_visibility" id="toggle_category_visibility">
                                <label class="form-check-label" for="toggle_category_visibility">Ẩn/Hiện danh mục</label>
                            </div>
                        </div>
                        <h6>Người dùng</h6>
                        <div class="d-flex flex-row">
                            <div class="form-check form-switch me-3">
                                <input class="form-check-input" type="checkbox" value="add_user" id="add_user">
                                <label class="form-check-label" for="add_user">Thêm người dùng</label>
                            </div>
                            <div class="form-check form-switch me-3">
                                <input class="form-check-input" type="checkbox" value="edit_user" id="edit_user">
                                <label class="form-check-label" for="edit_user">Sửa người dùng</label>
                            </div>
                            <div class="form-check form-switch me-3">
                                <input class="form-check-input" type="checkbox" value="delete_user" id="delete_user">
                                <label class="form-check-label" for="delete_user">Xóa người dùng</label>
                            </div>
                            <!-- <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" value="permission" id="permission">
                                <label class="form-check-label" for="permission">Phân quyền</label>
                            </div> -->
                        </div>
                        <h6>Quản lý đơn hàng</h6>
                        <div class="d-flex flex-row">
                            <div class="form-check form-switch me-3">
                                <input class="form-check-input" type="checkbox" value="manage_order_status" id="manage_order_status">
                                <label class="form-check-label" for="manage_order_status">Thay đổi trạng thái đơn hàng</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" value="print_invoice" id="print_invoice">
                                <label class="form-check-label" for="print_invoice">In hóa đơn</label>
                            </div>
                        </div>
                        <h6>Đánh giá</h6>
                        <div class="d-flex flex-row">
                            <div class="form-check form-switch me-3">
                                <input class="form-check-input" type="checkbox" value="approve_review" id="approve_review">
                                <label class="form-check-label" for="approve_review">Duyệt đánh giá</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" value="delete_review" id="delete_review">
                                <label class="form-check-label" for="delete_review">Xóa đánh giá</label>
                            </div>
                        </div>
                        <h6>Vận chuyển</h6>
                        <div class="d-flex flex-row">
                            <div class="form-check form-switch me-3">
                                <input class="form-check-input" type="checkbox" value="add_shipping" id="add_shipping">
                                <label class="form-check-label" for="add_shipping">Thêm vận chuyển</label>
                            </div>
                            <div class="form-check form-switch me-3">
                                <input class="form-check-input" type="checkbox" value="edit_shipping" id="edit_shipping">
                                <label class="form-check-label" for="edit_shipping">Sửa vận chuyển</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" value="delete_shipping" id="delete_shipping">
                                <label class="form-check-label" for="delete_shipping">Xóa vận chuyển</label>
                            </div>
                        </div>
                        <h6>Mã giảm giá</h6>
                        <div class="d-flex flex-row">
                            <div class="form-check form-switch me-3">
                                <input class="form-check-input" type="checkbox" value="add_discount" id="add_discount">
                                <label class="form-check-label" for="add_discount">Thêm mã giảm giá</label>
                            </div>
                            <div class="form-check form-switch me-3">
                                <input class="form-check-input" type="checkbox" value="edit_discount" id="edit_discount">
                                <label class="form-check-label" for="edit_discount">Sửa mã giảm giá</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" value="delete_discount" id="delete_discount">
                                <label class="form-check-label" for="delete_discount">Xóa mã giảm giá</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="button" class="btn btn-primary" id="savePermission">Lưu</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Function to load provinces
async function loadProvinces(selectElement, hiddenInput, selectedProvince = '') {
    try {
        const response = await fetch("https://provinces.open-api.vn/api/p/");
        const data = await response.json();
        selectElement.innerHTML = '<option value="">Chọn tỉnh/thành phố</option>';
        data.forEach(province => {
            const option = document.createElement("option");
            option.value = province.code;
            option.textContent = province.name;
            if (province.name === selectedProvince) {
                option.selected = true;
            }
            selectElement.appendChild(option);
        });
        if (selectedProvince && selectElement.value) {
            hiddenInput.value = selectedProvince;
        }
    } catch (error) {
        console.error("Error loading provinces:", error);
        Swal.fire("Lỗi!", "Không thể tải danh sách tỉnh/thành phố. Vui lòng thử lại.", "error");
    }
}

// Function to load districts
async function loadDistricts(provinceCode, districtSelect, districtHiddenInput, wardSelect, wardHiddenInput, selectedDistrict = '') {
    try {
        districtSelect.innerHTML = '<option value="">Chọn quận/huyện</option>';
        wardSelect.innerHTML = '<option value="">Chọn phường/xã</option>';
        wardHiddenInput.value = '';
        if (!provinceCode) return;
        const response = await fetch(`https://provinces.open-api.vn/api/p/${provinceCode}?depth=2`);
        const data = await response.json();
        data.districts.forEach(district => {
            const option = document.createElement("option");
            option.value = district.code;
            option.textContent = district.name;
            if (district.name === selectedDistrict) {
                option.selected = true;
            }
            districtSelect.appendChild(option);
        });
        if (selectedDistrict && districtSelect.value) {
            districtHiddenInput.value = selectedDistrict;
        }
    } catch (error) {
        console.error("Error loading districts:", error);
        Swal.fire("Lỗi!", "Không thể tải danh sách quận/huyện. Vui lòng thử lại.", "error");
    }
}

// Function to load wards
async function loadWards(districtCode, wardSelect, wardHiddenInput, selectedWard = '') {
    try {
        wardSelect.innerHTML = '<option value="">Chọn phường/xã</option>';
        if (!districtCode) return;
        const response = await fetch(`https://provinces.open-api.vn/api/d/${districtCode}?depth=2`);
        const data = await response.json();
        data.wards.forEach(ward => {
            const option = document.createElement("option");
            option.value = ward.code;
            option.textContent = ward.name;
            if (ward.name === selectedWard) {
                option.selected = true;
            }
            wardSelect.appendChild(option);
        });
        if (selectedWard && wardSelect.value) {
            wardHiddenInput.value = selectedWard;
        }
    } catch (error) {
        console.error("Error loading wards:", error);
        Swal.fire("Lỗi!", "Không thể tải danh sách phường/xã. Vui lòng thử lại.", "error");
    }
}

document.addEventListener("DOMContentLoaded", function () {
    const provinceSelect = document.getElementById("province");
    const districtSelect = document.getElementById("district");
    const wardSelect = document.getElementById("ward");
    const provinceHidden = document.getElementById("province_name");
    const districtHidden = document.getElementById("district_name");
    const wardHidden = document.getElementById("ward_name");
    loadProvinces(provinceSelect, provinceHidden);
    provinceSelect.addEventListener("change", function () {
        const provinceCode = this.value;
        const provinceName = this.options[this.selectedIndex].text;
        provinceHidden.value = provinceName;
        loadDistricts(provinceCode, districtSelect, districtHidden, wardSelect, wardHidden);
    });
    districtSelect.addEventListener("change", function () {
        const districtCode = this.value;
        const districtName = this.options[this.selectedIndex].text;
        districtHidden.value = districtName;
        loadWards(districtCode, wardSelect, wardHidden);
    });
    wardSelect.addEventListener("change", function () {
        const wardName = this.options[this.selectedIndex].text;
        wardHidden.value = wardName;
    });
    const editProvinceSelect = document.getElementById("edit-province");
    const editDistrictSelect = document.getElementById("edit-district");
    const editWardSelect = document.getElementById("edit-ward");
    const editProvinceHidden = document.getElementById("edit-province_name");
    const editDistrictHidden = document.getElementById("edit-district_name");
    const editWardHidden = document.getElementById("edit-ward_name");
    loadProvinces(editProvinceSelect, editProvinceHidden);
    editProvinceSelect.addEventListener("change", function () {
        const provinceCode = this.value;
        const provinceName = this.options[this.selectedIndex].text;
        editProvinceHidden.value = provinceName;
        loadDistricts(provinceCode, editDistrictSelect, editDistrictHidden, editWardSelect, editWardHidden);
    });
    editDistrictSelect.addEventListener("change", function () {
        const districtCode = this.value;
        const districtName = this.options[this.selectedIndex].text;
        editDistrictHidden.value = districtName;
        loadWards(districtCode, editWardSelect, editWardHidden);
    });
    editWardSelect.addEventListener("change", function () {
        const wardName = this.options[this.selectedIndex].text;
        editWardHidden.value = wardName;
    });
    const editButtons = document.querySelectorAll(".edit-btn");
    editButtons.forEach(button => {
        button.addEventListener("click", function () {
            document.getElementById("edit-id").value = this.dataset.id;
            document.getElementById("edit-hoten").value = this.dataset.hoten;
            document.getElementById("edit-username").value = this.dataset.username;
            document.getElementById("edit-email").value = this.dataset.email;
            document.getElementById("edit-sdt").value = this.dataset.sdt || '';
            document.getElementById("edit-phanquyen").value = this.dataset.phanquyen;
            document.getElementById("edit-trangthai").value = this.dataset.trangthai;
            document.getElementById("current-user-img").src = this.dataset.anh || '/images/default.jpg';
            document.getElementById("current-anh").value = this.dataset.anh || '';

            // Parse the diachi field into components
            const diachi = this.dataset.diachi || '';
            const addressParts = diachi.split(', ');
            const specificAddress = addressParts[0] || '';
            const ward = addressParts[1] || '';
            const district = addressParts[2] || '';
            const province = addressParts[3] || '';

            document.getElementById("edit-specific_address").value = specificAddress;
            editProvinceHidden.value = province;
            editDistrictHidden.value = district;
            editWardHidden.value = ward;

            // Load provinces and pre-select
            loadProvinces(editProvinceSelect, editProvinceHidden, province).then(() => {
                if (editProvinceSelect.value) {
                    loadDistricts(editProvinceSelect.value, editDistrictSelect, editDistrictHidden, editWardSelect, editWardHidden, district).then(() => {
                        if (editDistrictSelect.value) {
                            loadWards(editDistrictSelect.value, editWardSelect, editWardHidden, ward);
                        }
                    });
                }
            });
        });
    });

    // Handle Add User form submission
    document.getElementById("addUserForm").addEventListener("submit", function (e) {
        e.preventDefault();
        let formData = new FormData(this);
        Swal.fire({
            title: "Đang xử lý...",
            text: "Vui lòng chờ...",
            icon: "info",
            allowOutsideClick: false,
            showConfirmButton: false,
            timerProgressBar: true
        });
        fetch("them_nguoidung.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === "success") {
                Swal.fire({
                    title: "Thành công!",
                    text: data.message,
                    icon: "success"
                }).then(() => {
                    document.getElementById("addUserForm").reset();
                    const modalElement = document.getElementById('addUserModal');
                    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
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

    // Handle Edit User form submission
    document.getElementById("editUserForm").addEventListener("submit", function (e) {
        e.preventDefault();
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
            if (data.status === "success") {
                Swal.fire({
                    title: "Thành công!",
                    text: data.message,
                    icon: "success"
                }).then(() => {
                    const modalElement = document.getElementById('editUserModal');
                    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
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

    document.querySelectorAll(".delete-btn").forEach(function (button) {
        button.addEventListener("click", function () {
            var userId = this.dataset.id;
            Swal.fire({
                title: "Bạn có chắc chắn?",
                text: "Dữ liệu sẽ bị xóa vĩnh viễn!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Xóa",
                cancelButtonText: "Hủy"
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch("delete_nguoidung.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded"
                        },
                        body: "id=" + encodeURIComponent(userId)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === "success") {
                            Swal.fire("Đã xóa!", data.message, "success").then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire("Lỗi!", data.message, "error");
                        }
                    })
                    .catch(error => {
                        Swal.fire("Lỗi!", "Có lỗi xảy ra. Vui lòng thử lại!", "error");
                        console.error("Error:", error);
                    });
                }
            });
        });
    });
});
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const roleSelect = document.getElementById("roleSelect");
    const employeePermissionsDiv = document.getElementById("employeePermissions");

    // Hiển thị/ẩn quyền chi tiết khi chọn vai trò
    roleSelect.addEventListener("change", function () {
        if (this.value === "nhanvien") {
            employeePermissionsDiv.style.display = "block";
        } else {
            employeePermissionsDiv.style.display = "none";
        }
    });

    // Khi nhấn nút phân quyền
    const permissionButtons = document.querySelectorAll(".permission-btn");
    permissionButtons.forEach(button => {
        button.addEventListener("click", function () {
            const userId = this.dataset.id;
            document.getElementById("permissionUserId").value = userId;

            // Lấy vai trò và quyền chi tiết hiện tại
            fetch(`get_permissions.php?user_id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    console.log("Dữ liệu trả về:", data); // Debug dữ liệu
                    if (data.status === "success") {
                        // Cập nhật vai trò chính
                        roleSelect.value = data.phanquyen || 'user';

                        // Hiển thị/ẩn div quyền chi tiết dựa trên vai trò
                        if (roleSelect.value === "nhanvien") {
                            employeePermissionsDiv.style.display = "block";
                        } else {
                            employeePermissionsDiv.style.display = "none";
                        }

                        // Reset tất cả checkbox
                        document.querySelectorAll('.form-check-input').forEach(checkbox => checkbox.checked = false);

                        // Điền quyền chi tiết nếu có
                        if (data.permissions && roleSelect.value === "nhanvien") {
                            data.permissions.forEach(permission => {
                                const checkbox = document.getElementById(permission);
                                if (checkbox) {
                                    checkbox.checked = true;
                                    console.log(`Checkbox ${permission} được chọn.`);
                                } else {
                                    console.warn(`Checkbox với id ${permission} không tồn tại.`);
                                }
                            });
                        }
                    } else {
                        console.error("Lỗi khi lấy dữ liệu phân quyền:", data.message);
                    }
                })
                .catch(error => console.error("Lỗi khi lấy quyền:", error));
        });
    });

    // Khi nhấn nút Lưu
    document.getElementById("savePermission").addEventListener("click", function () {
        const userId = document.getElementById("permissionUserId").value;
        const phanquyen = roleSelect.value;
        let permissions = [];

        // Nếu vai trò là nhân viên, lấy các quyền chi tiết
        if (phanquyen === "nhanvien") {
            document.querySelectorAll('.form-check-input:checked').forEach(checkbox => {
                permissions.push(checkbox.value);
            });
        }

        fetch('update_permissions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `user_id=${encodeURIComponent(userId)}&phanquyen=${encodeURIComponent(phanquyen)}&permissions=${encodeURIComponent(JSON.stringify(permissions))}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === "success") {
                Swal.fire({
                    title: "Thành công!",
                    text: "Phân quyền đã được cập nhật.",
                    icon: "success"
                }).then(() => {
                    const modalElement = document.getElementById('permissionModal');
                    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
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
});
</script>