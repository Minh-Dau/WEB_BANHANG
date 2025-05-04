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
    let newReviewCount = <?php echo $new_review_count; ?>;
    let newOrderCount = <?php echo $new_order_count; ?>; // Số đơn hàng chưa in
    let notificationList = document.getElementById("notificationList");
    let reviewCountBadge = document.getElementById("reviewCountBadge");
    let orderCountBadge = document.getElementById("orderCountBadge"); // Badge cho đơn hàng
    if (newReviewCount > 0) {
        reviewCountBadge.textContent = newReviewCount;
        reviewCountBadge.style.display = "inline";
    } else {
        reviewCountBadge.style.display = "none";
    }
    if (newOrderCount > 0) {
        orderCountBadge.textContent = newOrderCount;
        orderCountBadge.style.display = "inline";
    } else {
        orderCountBadge.style.display = "none";
    }
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
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.x/css/all.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

    </head>
    <style>
        .switch {
    position: relative;
    display: inline-block;
    width: 42px;
    height: 22px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: relative;
    display: block;
    width: 100%;
    height: 100%;
    background-color: #ccc;
    border-radius: 30px;
    transition: 0.3s;
}

.slider:before {
    content: "";
    position: absolute;
    height: 16px;
    width: 16px;
    left: 3px;
    top: 3px;
    background-color: white;
    border-radius: 50%;
    transition: 0.3s;
}

input:checked + .slider {
    background-color: #17a2b8; /* Màu xanh nhạt đồng bộ btn-info */
}

input:checked + .slider:before {
    transform: translateX(20px);
}
    /* Toggle Switch Styling */
    .switch {
        position: relative;
        display: inline-block;
        width: 42px;
        height: 22px;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0; left: 0;
        right: 0; bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 34px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }

    input:checked + .slider {
        background-color: #0d6efd;
    }

    input:checked + .slider:before {
        transform: translateX(20px);
    }

    /* Fix for align-middle */
    .table td, .table th {
        vertical-align: middle;
    }

    .gap-2 {
        gap: 0.5rem !important;
    }
    </style>
    <body class="sb-nav-fixed">
        <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
        <a class="navbar-brand ps-3" href="admin.php">HUSTLE STONIE</a>
            <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" href="#!"><i class="fas fa-bars"></i></button>
            <form class="d-none d-md-inline-block form-inline ms-auto me-0 me-md-3 my-2 my-md-0">
            </form>
            <ul class="navbar-nav ms-auto ms-md-0 me-3 me-lg-4">
                <!-- Chuông thông báo -->
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
                            <div class="sb-sidenav-menu-heading">Addons</div>
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
                        <h1 class="mt-4">QUẢN LÝ SẢN PHẨM</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="index.html">QUẢN LÝ</a></li>
                            <li class="breadcrumb-item active">QUẢN LÝ SẢN PHẨM</li>
                        </ol>
                        <div class="card mb-4">
                        </div>
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-table me-1"></i>
                                Danh sách sản phẩm
                            </div>
                            <div class="card-body">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">Thêm sản phẩm</button>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">QUẢN LÝ DANH MỤC</button>
                                <table id="datatablesSimple">
                                    <thead>
                                        <tr>
                                            <th>Tên sản phẩm</th>
                                            <th>Ảnh</th>
                                            <th>Giá nhập</th>
                                            <th>Giá bán</th>
                                            <th>Số lượng</th>
                                            <th>Loại</th>
                                            <th>Nội dung</th>
                                            <th>Trạng thái</th>
                                            <th>Hành động</th>
                                        </tr>
                                    </thead>
                                    <tfoot>
                                        <tr>
                                            <th>Tên sản phẩm</th>
                                            <th>Ảnh</th>
                                            <th>Giá nhập</th>
                                            <th>Giá bán</th>
                                            <th>Số lượng</th>
                                            <th>Loại</th>
                                            <th>Nội dung</th>
                                            <th>Trạng thái</th>
                                            <th>Hành động</th>
                                        </tr>
                                    </tfoot>
                                    <tbody>
                                        <?php
                                        include 'config.php';
                                        $sql = "SELECT sp.id, sp.tensanpham, sp.img, sp.gia_nhap, sp.gia, sp.soluong, 
                                                    sp.noidungsanpham, sp.trangthai, dm.tendanhmuc 
                                                FROM sanpham sp
                                                JOIN danhmuc dm ON sp.danhmuc_id = dm.id";  // Thay vì lấy loaisanpham, ta lấy tendanhmuc từ bảng danhmuc

                                        $result = $conn->query($sql);
                                        if ($result->num_rows > 0) {
                                            while ($row = $result->fetch_assoc()) {
                                                echo "<tr>";
                                                echo "<td>" . $row["tensanpham"] . "</td>";
                                                echo "<td><img src='" . $row["img"] . "' width='50' height='50'></td>";
                                                echo "<td>" . number_format($row["gia_nhap"], 0, ',', '.') . " VND</td>";
                                                echo "<td>" . number_format($row["gia"], 0, ',', '.') . " VND</td>";
                                                echo "<td>" . $row["soluong"] . "</td>";
                                                echo "<td>" . $row["tendanhmuc"] . "</td>";  // Hiển thị tên danh mục thay vì mã danh mục
                                                $noidung = strip_tags($row["noidungsanpham"]); // loại bỏ thẻ HTML nếu có
                                                $words = explode(' ', $noidung);
                                                $short = implode(' ', array_slice($words, 0, 10));
                                                echo "<td>" . (count($words) > 10 ? $short . "..." : $short) . "</td>";
                                                echo "<td>" . ($row["trangthai"] == "Hiển thị" 
                                                ? "<span style='color: green; font-size: 20px;'>&#x25CF;</span> Hiển thị" 
                                                : "<span style='color: red; font-size: 20px;'>&#x25CF;</span> Đang ẩn") . "</td>";                                            
                                                echo "<td>
                                                    <button class='btn btn-warning btn-sm edit-btn' 
                                                        data-id='" . $row["id"] . "' 
                                                        data-tensanpham='" . $row["tensanpham"] . "'
                                                        data-img='" . $row["img"] . "'
                                                        data-gia_nhap='" . $row["gia_nhap"] . "'
                                                        data-gia='" . $row["gia"] . "'
                                                        data-soluong='" . $row["soluong"] . "'
                                                        data-danhmuc_id='" . $row["tendanhmuc"] . "' 
                                                        data-noidungsanpham='" . $row["noidungsanpham"] . "'
                                                        data-trangthai='" . $row["trangthai"] . "'
                                                        data-bs-toggle='modal' data-bs-target='#editModal'>
                                                        <i class='fas fa-edit'></i>
                                                    </button>
                                                    <button class='btn btn-danger btn-sm delete-btn' data-id='" . $row["id"] . "'>
                                                        <i class='fas fa-trash-alt'></i>
                                                    </button>
                                                </td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='10'>Không có sản phẩm nào</td></tr>";
                                        }
                                        $conn->close();
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </main>
               <!-- Modal Thêm Sản Phẩm -->
            <div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addProductModalLabel">Thêm Sản Phẩm</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="addProductForm" enctype="multipart/form-data">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label for="add-tensanpham" class="form-label">Tên sản phẩm</label>
                                    <input type="text" class="form-control" name="tensanpham" id="add-tensanpham" required>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="add-gia_nhap" class="form-label">Giá nhập</label>
                                        <input type="number" class="form-control" name="gia_nhap" id="add-gia_nhap" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="add-gia" class="form-label">Giá bán</label>
                                        <input type="number" class="form-control" name="gia" id="add-gia" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="add-soluong" class="form-label">Số lượng</label>
                                        <input type="number" class="form-control" name="soluong" id="add-soluong" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="add-danhmuc_id" class="form-label">Danh mục sản phẩm</label>
                                        <select class="form-control" name="danhmuc_id" id="add-danhmuc_id" required>
                                            <?php
                                            include 'config.php';
                                            $sql = "SELECT id, tendanhmuc FROM danhmuc WHERE trangthai = 'Hiển thị'";
                                            $result = $conn->query($sql);

                                            if ($result === false) {
                                                echo "<option value=''>Lỗi: " . $conn->error . "</option>";
                                            } elseif ($result->num_rows > 0) {
                                                while ($row = $result->fetch_assoc()) {
                                                    echo "<option value='" . $row["id"] . "'>" . $row["tendanhmuc"] . "</option>";
                                                }
                                            } else {
                                                echo "<option value=''>Không có danh mục nào</option>";
                                            }
                                            $conn->close();
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="add-noidungsanpham" class="form-label">Nội dung sản phẩm</label>
                                    <textarea class="form-control" name="noidungsanpham" id="add-noidungsanpham" required></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="add-img" class="form-label">Chọn ảnh</label>
                                    <input type="file" class="form-control" name="img" id="add-img" required>
                                </div>
                                <div class="mb-3">
                                    <label for="add-trangthai" class="form-label">Trạng thái</label>
                                    <select class="form-control" name="trangthai" id="add-trangthai" required>
                                        <option value="Hiển thị">Hiển thị</option>
                                        <option value="Ẩn">Ẩn</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                <button type="submit" class="btn btn-primary">Thêm</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- Modal Quản lý danh mục -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCategoryModalLabel">Quản lý danh mục</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6>Thêm danh mục mới</h6>
                <form id="addCategoryForm">
                    <div class="mb-3">
                        <label for="add-tendanhmuc" class="form-label">Tên danh mục</label>
                        <input type="text" class="form-control" name="tendanhmuc" id="add-tendanhmuc" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Thêm</button>
                </form>
                <hr>
                <h6>Danh sách danh mục</h6>
                <table class="table table-bordered align-middle text-center">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 30%;">Tên danh mục</th>
                            <th style="width: 20%;">Trạng thái</th>
                            <th style="width: 50%;">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    include 'config.php';
                    $sql = "SELECT id, tendanhmuc, trangthai FROM danhmuc";
                    $result = $conn->query($sql);
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $trangthai = $row["trangthai"] == "Hiển thị" ? "Hiển thị" : "Ẩn";
                            $isChecked = $row["trangthai"] == "Hiển thị" ? "checked" : "";
                            echo "<tr>";
                            echo "<td>" . $row["tendanhmuc"] . "</td>";
                            echo "<td>" . $trangthai . "</td>";
                            echo "<td>
                                <div class='d-flex justify-content-center align-items-center gap-2'>
                                    <button class='btn btn-warning btn-sm edit-category-btn' 
                                            data-id='" . $row["id"] . "' 
                                            data-tendanhmuc='" . $row["tendanhmuc"] . "' 
                                            data-bs-toggle='modal' 
                                            data-bs-target='#editCategoryModal'>
                                        <i class='fas fa-edit'></i>
                                    </button>

                                    <button class='btn btn-danger btn-sm delete-category-btn' 
                                            data-id='" . $row["id"] . "'>
                                        <i class='fas fa-trash'></i>
                                    </button>

                                    <label class='switch'>
                                        <input type='checkbox' class='toggle-category' data-id='" . $row["id"] . "' $isChecked>
                                        <span class='slider'></span>
                                    </label>
                                </div>
                            </td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='3'>Không có danh mục nào</td></tr>";
                    }
                    $conn->close();
                    ?>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>
            <!-- Modal Chỉnh sửa Danh mục -->
                <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editCategoryModalLabel">Chỉnh sửa danh mục</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form id="editCategoryForm">
                                <div class="modal-body">
                                    <input type="hidden" name="id" id="edit-category-id">
                                    <div class="mb-3">
                                        <label for="edit-tendanhmuc" class="form-label">Tên danh mục</label>
                                        <input type="text" class="form-control" name="tendanhmuc" id="edit-tendanhmuc" required>
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
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script src="js/datatables-simple-demo.js"></script>
        <?php
            include 'config.php';
            if (isset($_GET["id"])) {
                $id = $_GET["id"];
                $sql = "SELECT trangthai FROM danhmuc WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $trangthai_danhmuc = ($row = $result->fetch_assoc()) ? $row["trangthai"] : "Không tồn tại";

                $stmt->close();
            }
            ?>
    <!-- Modal Chỉnh Sửa Sản Phẩm -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Chỉnh sửa sản phẩm</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editProductForm" enctype="multipart/form-data" onsubmit="updateProduct(event)">
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit-id">
                    <div class="mb-3">
                        <label for="edit-tensanpham" class="form-label">Tên sản phẩm</label>
                        <input type="text" class="form-control" name="tensanpham" id="edit-tensanpham">
                    </div>
                    <div class="mb-3">
                        <label for="edit-img" class="form-label">Hình ảnh hiện tại</label><br>
                        <img id="current-img" src="" width="100"><br>
                        <label for="edit-new-img" class="form-label mt-2">Chọn ảnh mới</label>
                        <input type="file" class="form-control" name="new_img" id="edit-new-img">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label for="edit-gia_nhap" class="form-label">Giá nhập</label>
                            <input type="number" class="form-control" name="gia_nhap" id="edit-gia_nhap">
                        </div>
                        <div class="col-md-6">
                            <label for="edit-gia" class="form-label">Giá</label>
                            <input type="number" class="form-control" name="gia" id="edit-gia">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label for="edit-soluong" class="form-label">Số lượng</label>
                            <input type="number" class="form-control" name="soluong" id="edit-soluong">
                        </div>
                        <div class="col-md-6">
                            <label for="edit-danhmuc_id" class="form-label">Danh mục sản phẩm</label>
                            <select class="form-control" name="danhmuc_id" id="edit-danhmuc_id">
                                <?php
                                include 'config.php';
                                $sql = "SELECT id, tendanhmuc FROM danhmuc";
                                $result = $conn->query($sql);
                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        echo "<option value='" . $row["id"] . "'>" . $row["tendanhmuc"] . "</option>";
                                    }
                                } else {
                                    echo "<option value=''>Không có danh mục nào</option>";
                                }
                                $conn->close();
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit-noidungsanpham" class="form-label">Nội dung sản phẩm</label>
                        <textarea class="form-control" name="noidungsanpham" id="edit-noidungsanpham"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit-trangthai" class="form-label">Trạng thái</label>
                        <select class="form-control" name="trangthai" id="edit-trangthai"
                            <?php echo (isset($trangthai_danhmuc) && $trangthai_danhmuc == "Ẩn") ? "disabled" : ""; ?>>
                            <option value="Hiển thị">Hiển thị</option>
                            <option value="Ẩn">Đang ẩn</option>
                        </select>
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

    </body>
</html>
 <!-- chỗ này xử lý cập nhật thông tin-->
 <script>
document.addEventListener("DOMContentLoaded", function () {
    const editButtons = document.querySelectorAll(".edit-btn");
    editButtons.forEach(button => {
        button.addEventListener("click", function () {
            document.getElementById("edit-id").value = this.dataset.id;
            document.getElementById("edit-tensanpham").value = this.dataset.tensanpham;
            document.getElementById("current-img").src = this.dataset.img;
            document.getElementById("edit-gia_nhap").value = this.dataset.gia_nhap;
            document.getElementById("edit-gia").value = this.dataset.gia;
            document.getElementById("edit-noidungsanpham").value = this.dataset.noidungsanpham;
            document.getElementById("edit-trangthai").value = this.dataset.trangthai;
            document.getElementById("edit-soluong").value = this.dataset.soluong;

            // Cập nhật danh mục sản phẩm
            let selectElement = document.getElementById("edit-danhmuc_id");
            let selectedValue = this.dataset.danhmuc_id; 

            for (let i = 0; i < selectElement.options.length; i++) {
                if (parseInt(selectElement.options[i].value) === parseInt(selectedValue)) {
                    selectElement.options[i].selected = true;
                    break;
                }
            }
        });
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".delete-btn").forEach(button => {
        button.addEventListener("click", function () {
            let productId = this.getAttribute("data-id");

            Swal.fire({
                title: "Bạn có chắc chắn?",
                text: "Sản phẩm sẽ bị xóa khỏi hệ thống!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#3085d6",
                confirmButtonText: "Xóa ngay",
                cancelButtonText: "Hủy"
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: "Đang xử lý...",
                        text: "Vui lòng chờ...",
                        icon: "info",
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        timerProgressBar: true
                    });

                    fetch("xoasanpham.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded"
                        },
                        body: "id=" + productId
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === "error") {
                            Swal.fire({
                                title: "Không được phép",
                                text: data.message,
                                icon: "warning"
                            });
                            return;
                        }

                        Swal.fire({
                            title: "Thành công!",
                            text: data.message,
                            icon: "success"
                        }).then(() => {
                            location.reload();
                        });
                    })
                    .catch(error => {
                        Swal.fire({
                            title: "Lỗi!",
                            text: "Không thể xóa sản phẩm. Vui lòng thử lại!",
                            icon: "error"
                        });
                        console.error("Lỗi:", error);
                    });
                }
            });
        });
    });
});
</script>

    <!-- Thông báo thêm thành công-->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    document.getElementById("addProductForm").addEventListener("submit", function (e) {
        e.preventDefault(); // Ngăn form submit mặc định
        let formData = new FormData(this); // Lấy dữ liệu từ form
        Swal.fire({
            title: "Đang xử lý...",
            text: "Vui lòng chờ...",
            icon: "info",
            allowOutsideClick: false,
            showConfirmButton: false,
            timerProgressBar: true
        });
        fetch("themsanpham.php", {
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
                    document.getElementById("addProductForm").reset(); 
                    var modal = new bootstrap.Modal(document.getElementById('addProductModal'));
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
    <script>
document.getElementById("addCategoryForm").addEventListener("submit", function (e) {
    e.preventDefault(); // Ngăn form submit mặc định
    let formData = new FormData(this); // Lấy dữ liệu từ form

    Swal.fire({
        title: "Đang xử lý...",
        text: "Vui lòng chờ...",
        icon: "info",
        allowOutsideClick: false,
        showConfirmButton: false,
        timerProgressBar: true
    });

    fetch("themdanhmuc.php", {
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
                document.getElementById("addCategoryForm").reset(); 
                var modal = new bootstrap.Modal(document.getElementById('addCategoryModal'));
                modal.hide(); 
                location.reload(); // Reload lại trang để cập nhật danh sách danh mục
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
<script>
document.addEventListener("DOMContentLoaded", function () {
    // Xử lý nút Chỉnh sửa
    const editCategoryButtons = document.querySelectorAll(".edit-category-btn");
    editCategoryButtons.forEach(button => {
        button.addEventListener("click", function () {
            document.getElementById("edit-category-id").value = this.dataset.id;
            document.getElementById("edit-tendanhmuc").value = this.dataset.tendanhmuc;
        });
    });
   // Xử lý nút Xóa
document.querySelectorAll(".delete-category-btn").forEach(button => {
    button.addEventListener("click", function () {
        let categoryId = this.getAttribute("data-id");
        Swal.fire({
            title: "Bạn có chắc chắn?",
            text: "Danh mục sẽ bị xóa khỏi hệ thống!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "Xóa ngay",
            cancelButtonText: "Hủy"
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: "Đang xử lý...",
                    text: "Vui lòng chờ...",
                    icon: "info",
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    timerProgressBar: true
                });
                fetch("xoadanhmuc.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: "id=" + categoryId
                })
                .then(response => response.json()) // Chuyển sang JSON
                .then(data => {
                    if (data.status === "success") {
                        Swal.fire({
                            title: "Thành công!",
                            text: data.message,
                            icon: "success"
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: "Lỗi!",
                            text: data.message,
                            icon: "error",
                            confirmButtonText: "OK"
                        });
                    }
                })
                .catch(error => {
                    Swal.fire({
                        title: "Lỗi!",
                        text: "Không thể xóa danh mục. Vui lòng thử lại!",
                        icon: "error",
                        confirmButtonText: "OK"
                    });
                    console.error("Lỗi:", error);
                });
            }
        });
    });
});
    // Xử lý form Chỉnh sửa danh mục
    document.getElementById("editCategoryForm").addEventListener("submit", function (e) {
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
        fetch("update_danhmuc.php", {
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
                    var modal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
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
<script>
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".toggle-category").forEach(function (checkbox) {
        checkbox.addEventListener("change", function () {
            const id = this.getAttribute("data-id");
            const isChecked = this.checked;
            const newTrangthai = isChecked ? "Hiển thị" : "Ẩn";
            const checkboxEl = this;

            Swal.fire({
                title: `Bạn có chắc chắn muốn ${newTrangthai.toLowerCase()} danh mục này không?`,
                icon: "question",
                showCancelButton: true,
                confirmButtonText: "Đồng ý",
                cancelButtonText: "Hủy",
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Hiển thị tiến trình
                    Swal.fire({
                        title: "Đang cập nhật...",
                        text: "Vui lòng chờ...",
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => Swal.showLoading()
                    });

                    fetch("anhien_danhmuc.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded"
                        },
                        body: `id=${id}&trangthai=${newTrangthai}`
                    })
                    .then(response => response.json()) // Chuyển sang JSON
                    .then(data => {
                        if (data.status === "success") {
                            Swal.fire({
                                title: "Thành công!",
                                text: data.message,
                                icon: "success",
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => location.reload());
                        } else {
                            Swal.fire({
                                title: "Lỗi!",
                                text: data.message,
                                icon: "error",
                                confirmButtonText: "OK"
                            });
                            checkboxEl.checked = !isChecked; // Khôi phục lại checkbox nếu lỗi
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            title: "Lỗi!",
                            text: "Có lỗi xảy ra khi cập nhật trạng thái.",
                            icon: "error",
                            confirmButtonText: "OK"
                        });
                        console.error("Lỗi:", error);
                        checkboxEl.checked = !isChecked; // Khôi phục lại checkbox nếu lỗi
                    });
                } else {
                    checkboxEl.checked = !isChecked; // Hủy thì khôi phục lại trạng thái ban đầu
                }
            });
        });
    });
});
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const editButtons = document.querySelectorAll(".edit-btn");
    editButtons.forEach(button => {
        button.addEventListener("click", function () {
            document.getElementById("edit-id").value = this.dataset.id;
            document.getElementById("edit-tensanpham").value = this.dataset.tensanpham;
            document.getElementById("current-img").src = this.dataset.img;
            document.getElementById("edit-gia_nhap").value = this.dataset.gia_nhap;
            document.getElementById("edit-gia").value = this.dataset.gia;
            document.getElementById("edit-soluong").value = this.dataset.soluong;
            document.getElementById("edit-noidungsanpham").value = this.dataset.noidungsanpham;
            document.getElementById("edit-trangthai").value = this.dataset.trangthai;

            // Cập nhật danh mục sản phẩm
            let selectElement = document.getElementById("edit-danhmuc_id");
            let selectedValue = this.dataset.danhmuc_id;
            for (let i = 0; i < selectElement.options.length; i++) {
                if (parseInt(selectElement.options[i].value) === parseInt(selectedValue)) {
                    selectElement.options[i].selected = true;
                    break;
                }
            }

            // Kiểm tra trạng thái danh mục để disable select nếu cần
            <?php if (isset($trangthai_danhmuc) && $trangthai_danhmuc == "Ẩn"): ?>
                document.getElementById("edit-trangthai").disabled = true;
            <?php endif; ?>
        });
    });

    // Hàm xử lý cập nhật sản phẩm bằng AJAX
    window.updateProduct = function(event) {
        event.preventDefault(); // Ngăn submit mặc định

        let form = document.getElementById("editProductForm");
        let formData = new FormData(form);

        Swal.fire({
            title: "Đang xử lý...",
            text: "Vui lòng chờ...",
            icon: "info",
            allowOutsideClick: false,
            showConfirmButton: false,
            timerProgressBar: true
        });

        fetch("update_product.php", {
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
                    var modal = bootstrap.Modal.getInstance(document.getElementById('editModal'));
                    modal.hide();
                    location.reload(); // Có thể thay bằng cập nhật giao diện trực tiếp nếu muốn
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
    };
});
</script>