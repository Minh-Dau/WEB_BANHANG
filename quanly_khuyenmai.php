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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="sb-nav-fixed">
    <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
        <a class="navbar-brand ps-3" href="admin.php">HUSTLE STONIE</a>
        <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" href="#!"><i class="fas fa-bars"></i></button>
        <form class="d-none d-md-inline-block form-inline ms-auto me-0 me-md-3 my-2 my-md-0">
        </form>
        <ul class="navbar-nav ms-auto ms-md-0 me-3 me-lg-4">
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
                    <h1 class="mt-4">QUẢN LÝ MÃ GIẢM GIÁ</h1>
                    <ol class="breadcrumb mb-4">
                        <li class="breadcrumb-item"><a href="http://localhost/BAOCAO/quanly_khuyenmai.php">QUẢN LÝ</a></li>
                        <li class="breadcrumb-item active">QUẢN LÝ MÃ GIẢM GIÁ</li>
                    </ol>
                    <div class="card mb-4">
                    </div>
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-table me-1"></i>
                            DANH SÁCH MÃ GIẢM GIÁ
                        </div>
                        <div class="card-body">
                            <!-- Nút bấm mở Modal -->
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDiscountCodeModal">
                                Thêm mã giảm giá mới
                            </button>
                            <table id="datatablesSimple">
                                <thead>
                                    <tr>
                                        <th>Mã giảm giá</th>
                                        <th>Loại giảm giá</th>
                                        <th>Giá trị giảm</th>
                                        <th>Đơn hàng tối thiểu</th>
                                        <th>Số lần sử dụng tối đa</th>
                                        <th>Đã sử dụng</th>
                                        <th>Ngày hết hạn</th>
                                        <th>Trạng thái</th>
                                        <th>Hoạt động</th>
                                    </tr>
                                </thead>
                                <tfoot>
                                    <tr>
                                        <th>Mã giảm giá</th>
                                        <th>Loại giảm giá</th>
                                        <th>Giá trị giảm</th>
                                        <th>Đơn hàng tối thiểu</th>
                                        <th>Số lần sử dụng tối đa</th>
                                        <th>Đã sử dụng</th>
                                        <th>Ngày hết hạn</th>
                                        <th>Trạng thái</th>
                                        <th>Hoạt động</th>
                                    </tr>
                                </tfoot>
                                <tbody>
                                    <?php
                                    include 'config.php';
                                    $sql = "SELECT * FROM discount_codes ORDER BY id DESC";
                                    $result = $conn->query($sql);
                                    if ($result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            echo "<tr>"; 
                                            echo "<td>" . htmlspecialchars($row["code"]) . "</td>";
                                            echo "<td>" . htmlspecialchars($row["discount_type"]) . "</td>";
                                            
                                            // Kiểm tra loại giảm giá để hiển thị % hoặc VND
                                            echo "<td>";
                                            if ($row["discount_type"] === "percent") {
                                                echo number_format($row["discount_value"], 0, ',', '.') . " %";
                                            } else {
                                                echo number_format($row["discount_value"], 0, ',', '.') . " VND";
                                            }
                                            echo "</td>";

                                            echo "<td>" . number_format($row["min_order_value"], 0, ',', '.') . " VND</td>";
                                            echo "<td>" . $row["max_uses"] . "</td>";
                                            echo "<td>" . $row["used_count"] . "</td>";
                                            echo "<td>" . $row["expiry_date"] . "</td>";
                                            echo "<td>" . ($row["is_active"] ? "Kích hoạt" : "Không kích hoạt") . "</td>";
                                            echo "<td>
                                                <button class='btn btn-warning btn-sm edit-btn'
                                                    data-id='" . $row["id"] . "'
                                                    data-code='" . htmlspecialchars($row["code"]) . "'
                                                    data-discount_type='" . htmlspecialchars($row["discount_type"]) . "'
                                                    data-discount_value='" . $row["discount_value"] . "'
                                                    data-min_order_value='" . $row["min_order_value"] . "'
                                                    data-max_uses='" . $row["max_uses"] . "'
                                                    data-expiry_date='" . $row["expiry_date"] . "'
                                                    data-is_active='" . $row["is_active"] . "'
                                                    data-bs-toggle='modal'
                                                    data-bs-target='#editDiscountCodeModal'
                                                    title='Sửa'>
                                                    <i class='fas fa-edit'></i>
                                                </button>
                                                <button class='btn btn-danger btn-sm delete-btn'
                                                    data-id='" . $row["id"] . "'
                                                    title='Xóa'>
                                                    <i class='fas fa-trash-alt'></i>
                                                </button>
                                            </td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='10'>Không có mã giảm giá nào</td></tr>";
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
<!-- From thêm mã giảm giá-->
<div class="modal fade" id="addDiscountCodeModal" tabindex="-1" aria-labelledby="addDiscountCodeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addDiscountCodeModalLabel">Thêm Mã Giảm Giá</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addDiscountCodeForm">
                    <div class="mb-3">
                        <label for="code" class="form-label">Mã giảm giá</label>
                        <input type="text" class="form-control" id="code" name="code" required>
                    </div>
                    <div class="mb-3">
                        <label for="discount_type" class="form-label">Loại giảm giá</label>
                        <select class="form-control" id="discount_type" name="discount_type">
                            <option value="percent">Phần trăm</option>
                            <option value="fixed">Giá trị cố định</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="discount_value" class="form-label">Giá trị giảm</label>
                        <input type="number" class="form-control" id="discount_value" name="discount_value" required>
                    </div>
                    <div class="mb-3">
                        <label for="min_order_value" class="form-label">Giá trị đơn hàng tối thiểu</label>
                        <input type="number" class="form-control" id="min_order_value" name="min_order_value">
                    </div>
                    <div class="mb-3">
                        <label for="max_uses" class="form-label">Số lần sử dụng tối đa</label>
                        <input type="number" class="form-control" id="max_uses" name="max_uses" required>
                    </div>
                    <div class="mb-3">
                        <label for="expiry_date" class="form-label">Ngày hết hạn</label>
                        <input type="date" class="form-control" id="expiry_date" name="expiry_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="is_active" class="form-label">Trạng thái</label>
                        <select class="form-control" id="is_active" name="is_active">
                            <option value="1">Kích hoạt</option>
                            <option value="0">Không kích hoạt</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Thêm</button>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- Modal Sửa Mã Giảm Giá -->
<div class="modal fade" id="editDiscountCodeModal" tabindex="-1" aria-labelledby="editDiscountCodeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editDiscountCodeModalLabel">Chỉnh Sửa Mã Giảm Giá</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editDiscountCodeForm">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="mb-3">
                        <label for="edit_code" class="form-label">Mã giảm giá</label>
                        <input type="text" class="form-control" id="edit_code" name="code" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_discount_type" class="form-label">Loại giảm giá</label>
                        <select class="form-control" id="edit_discount_type" name="discount_type">
                            <option value="percent">Phần trăm</option>
                            <option value="fixed">Giá trị cố định</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_discount_value" class="form-label">Giá trị giảm</label>
                        <input type="number" class="form-control" id="edit_discount_value" name="discount_value" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_min_order_value" class="form-label">Đơn hàng tối thiểu</label>
                        <input type="number" class="form-control" id="edit_min_order_value" name="min_order_value" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_max_uses" class="form-label">Số lần sử dụng tối đa</label>
                        <input type="number" class="form-control" id="edit_max_uses" name="max_uses" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Lưu</button>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>
    <!-- Include jQuery for AJAX -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="js/scripts.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
    <script src="js/datatables-simple-demo.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
$(document).ready(function () {
    $("#addDiscountCodeForm").submit(function (e) {
        e.preventDefault();
        var formData = $(this).serialize();
        $.ajax({
            type: "POST",
            url: "them_magiamgia.php",
            data: formData,
            dataType: "json", // Đảm bảo phân tích JSON
            success: function (response) {
                if (response.status === "success") {
                    Swal.fire({
                        icon: "success",
                        title: "Thành công!",
                        text: response.message,
                    }).then(() => {
                        $("#addDiscountCodeModal").modal("hide"); 
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Lỗi!",
                        text: response.message,
                    });
                }
            },
            error: function (xhr, status, error) {
                Swal.fire({
                    icon: "error",
                    title: "Lỗi!",
                    text: "Đã xảy ra lỗi khi gửi yêu cầu. Vui lòng thử lại!",
                });
                console.error("Lỗi AJAX:", error);
            }
        });
    });
});
</script>
<script>
$(document).ready(function () {
    $(".edit-btn").click(function () {
        $("#edit_id").val($(this).data("id"));
        $("#edit_code").val($(this).data("code"));
        $("#edit_discount_type").val($(this).data("discount_type"));
        $("#edit_discount_value").val($(this).data("discount_value"));
        $("#edit_min_order_value").val($(this).data("min_order_value"));
        $("#edit_max_uses").val($(this).data("max_uses"));
    });

    $("#editDiscountCodeForm").submit(function (e) {
    e.preventDefault();

    var formData = $(this).serialize();
    $.ajax({
        type: "POST",
        url: "update_magiamgia.php",
        data: formData,
        dataType: "json", // đảm bảo phản hồi được xử lý đúng dạng JSON
        success: function (response) {
            if (response.status === "success") {
                Swal.fire("Thành công!", response.message, "success").then(() => {
                    $("#editDiscountCodeModal").modal("hide");
                    location.reload();
                });
            } else {
                Swal.fire("Lỗi!", response.message, "error");
            }
        },
        error: function (xhr, status, error) {
            Swal.fire("Lỗi!", "Không thể kết nối đến máy chủ.", "error");
            console.error("AJAX lỗi:", xhr.responseText);
        }
    });
});
    // Xóa mã giảm giá
$(".delete-btn").click(function () {
    var id = $(this).data("id");

    Swal.fire({
        title: "Bạn có chắc chắn muốn xóa?",
        text: "Hành động này không thể hoàn tác!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Xóa",
        cancelButtonText: "Hủy"
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                type: "POST",
                url: "xoa_magiamgia.php",
                data: { id: id },
                dataType: "json", // Đảm bảo phân tích JSON
                success: function (response) {
                    if (response.status === "success") {
                        Swal.fire("Xóa thành công!", response.message, "success").then(() => location.reload());
                    } else {
                        Swal.fire("Lỗi!", response.message, "error");
                    }
                },
                error: function (xhr, status, error) {
                    Swal.fire("Lỗi!", "Đã xảy ra lỗi khi gửi yêu cầu. Vui lòng thử lại!", "error");
                    console.error("Lỗi AJAX:", error);
                }
            });
        }
    });
});
});
</script>