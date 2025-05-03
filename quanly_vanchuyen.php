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
                    <h1 class="mt-4">QUẢN LÝ VẬN CHUYỂN</h1>
                    <ol class="breadcrumb mb-4">
                        <li class="breadcrumb-item"><a href="index.html">QUẢN LÝ</a></li>
                        <li class="breadcrumb-item active">QUẢN LÝ VẬN CHUYỂN</li>
                    </ol>
                    <div class="card mb-4">
                    </div>
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-table me-1"></i>
                            DANH SÁCH PHƯƠNG THỨC VẬN CHUYỂN
                        </div>
                        <div class="card-body">
                            <!-- Nút bấm mở Modal -->
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addShippingMethodModal">
                                Thêm phương thức vận chuyển mới
                            </button>

                            <!-- Modal for Adding New Shipping Method -->
                            <div class="modal fade" id="addShippingMethodModal" tabindex="-1" aria-labelledby="addShippingMethodModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="addShippingMethodModalLabel">Thêm Phương Thức Vận Chuyển Mới</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form id="addShippingMethodForm">
                                                <div class="mb-3">
                                                    <label for="method_name" class="form-label">Tên phương thức</label>
                                                    <input type="text" class="form-control" id="method_name" name="method_name" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="cost" class="form-label">Chi phí (VNĐ)</label>
                                                    <input type="number" class="form-control" id="cost" name="cost" step="0.01" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="description" class="form-label">Mô tả</label>
                                                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                                </div>
                                                <button type="submit" class="btn btn-primary">Thêm</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <table id="datatablesSimple">
                                <thead>
                                    <tr>
                                        <th>Tên phương thức</th>
                                        <th>Chi phí</th>
                                        <th>Mô tả</th>
                                        <th>Ngày tạo</th>
                                        <th>Ngày cập nhật</th>
                                        <th>Hoạt động</th>
                                    </tr>
                                </thead>
                                <tfoot>
                                    <tr>
                                        <th>Tên phương thức</th>
                                        <th>Chi phí</th>
                                        <th>Mô tả</th>
                                        <th>Ngày tạo</th>
                                        <th>Ngày cập nhật</th>
                                        <th>Hoạt động</th>
                                    </tr>
                                </tfoot>
                                <tbody>
                                    <?php
                                    include 'config.php';
                                    $sql = "SELECT * FROM shipping_method";
                                    $result = $conn->query($sql);
                                    if ($result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($row["method_name"]) . "</td>";
                                            echo "<td>" . number_format($row["cost"], 0, ',', '.') . " VND</td>";
                                            echo "<td>" . htmlspecialchars($row["description"]) . "</td>";
                                            echo "<td>" . $row["create_at"] . "</td>";
                                            echo "<td>" . $row["update_at"] . "</td>";
                                            echo "<td>
                                                <button class='btn btn-warning btn-sm edit-btn'
                                                    data-id='" . $row["id"] . "'
                                                    data-method_name='" . htmlspecialchars($row["method_name"]) . "'
                                                    data-cost='" . $row["cost"] . "'
                                                    data-description='" . htmlspecialchars($row["description"]) . "'
                                                    data-bs-toggle='modal'
                                                    data-bs-target='#editShippingMethodModal'
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
                                        echo "<tr><td colspan='7'>Không có phương thức vận chuyển nào</td></tr>";
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
    <!-- Modal cập nhật vận chuyển -->
<div class="modal fade" id="editShippingMethodModal" tabindex="-1" aria-labelledby="editShippingMethodModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editShippingMethodModalLabel">Chỉnh sửa phương thức vận chuyển</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editShippingMethodForm">
                    <input type="hidden" id="edit_id" name="id"> <!-- ID ẩn -->
                    
                    <div class="mb-3">
                        <label for="edit_method_name" class="form-label">Tên phương thức</label>
                        <input type="text" class="form-control" id="edit_method_name" name="method_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_cost" class="form-label">Chi phí (VNĐ)</label>
                        <input type="number" class="form-control" id="edit_cost" name="cost" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Mô tả</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Cập nhật</button>
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
    $("#addShippingMethodForm").submit(function (e) {
        e.preventDefault(); // Ngăn form reload lại trang

        var formData = $(this).serialize(); // Lấy dữ liệu từ form
        $.ajax({
            type: "POST",
            url: "themvanchuyen.php",  // Gửi tới file xử lý PHP
            data: formData,
            success: function (response) {
                console.log("Response từ server:", response); // Debug xem server phản hồi gì
                if (response.trim() === "success") {
                    Swal.fire({
                        icon: "success",
                        title: "Thành công!",
                        text: "Phương thức vận chuyển đã được thêm.",
                        showConfirmButton: false,
                        timer: 1000
                    }).then(() => {
                        $("#addShippingMethodModal").modal("hide"); // Ẩn modal
                        location.reload(); // Làm mới trang
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Lỗi!",
                        text: "Không thể thêm phương thức. Lỗi: " + response,
                    });
                }
            },
            error: function (xhr, status, error) {
                console.error("AJAX error:", xhr.responseText);
                Swal.fire({
                    icon: "error",
                    title: "Lỗi!",
                    text: "Lỗi khi gửi dữ liệu: " + xhr.responseText,
                });
            }
        });
    });
});
</script>
<!-- xóa vận chuyển-->
<script>
$(document).ready(function () {
    $(".delete-btn").click(function () {
        var shippingId = $(this).data("id");

        Swal.fire({
            title: "Bạn có chắc chắn muốn xóa?",
            text: "Hành động này không thể hoàn tác!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "Xóa",
            cancelButtonText: "Hủy"
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    type: "POST",
                    url: "xoa_vanchuyen.php",
                    data: { id: shippingId },
                    dataType: "json", // Chỉ định kiểu dữ liệu trả về là JSON
                    success: function (response) {
                        // Kiểm tra trạng thái trong phản hồi JSON
                        if (response.status === "success") {
                            Swal.fire({
                                icon: "success",
                                title: "Thành công!",
                                text: response.message, // Hiển thị thông điệp từ server
                                showConfirmButton: false,
                                timer: 1000
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Lỗi!",
                                text: response.message // Hiển thị thông điệp lỗi từ server
                            });
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error("AJAX error:", xhr.responseText);
                        Swal.fire({
                            icon: "error",
                            title: "Lỗi!",
                            text: "Có lỗi xảy ra: " + (xhr.responseText || "Không thể kết nối tới server")
                        });
                    }
                });
            }
        });
    });
});
</script>
 <!-- Cập nhật vận chuyển-->
  <script>
    $(document).ready(function () {
    $(".edit-btn").click(function () {
        var id = $(this).data("id");
        var method_name = $(this).data("method_name");
        var cost = $(this).data("cost");
        var description = $(this).data("description");

        // Đổ dữ liệu vào modal sửa
        $("#edit_id").val(id);
        $("#edit_method_name").val(method_name);
        $("#edit_cost").val(cost);
        $("#edit_description").val(description);
    });

    // Xử lý cập nhật dữ liệu
    $("#editShippingMethodForm").submit(function (e) {
        e.preventDefault(); // Ngăn form reload lại trang

        var formData = $(this).serialize(); // Lấy dữ liệu từ form
        $.ajax({
            type: "POST",
            url: "update_vanchuyen.php", // Gửi tới file PHP xử lý
            data: formData,
            success: function (response) {
                if (response.trim() === "success") {
                    Swal.fire({
                        icon: "success",
                        title: "Cập nhật thành công!",
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        $("#editShippingMethodModal").modal("hide"); // Ẩn modal
                        location.reload(); // Làm mới trang để hiển thị dữ liệu mới
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Lỗi!",
                        text: "Không thể cập nhật: " + response
                    });
                }
            },
            error: function (xhr, status, error) {
                console.error("AJAX error:", xhr.responseText);
                Swal.fire({
                    icon: "error",
                    title: "Lỗi!",
                    text: "Có lỗi xảy ra: " + xhr.responseText
                });
            }
        });
    });
});
  </script>
  