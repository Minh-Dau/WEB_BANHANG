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
                        <h1 class="mt-4">QUẢN LÝ ĐÁNH GIÁ</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="http://localhost/BAOCAO/quanlydanhgia.php">QUẢN LÝ</a></li>
                            <li class="breadcrumb-item active">QUẢN LÝ ĐÁNH GIÁ</li>
                        </ol>
                        <!-- Display success or error message if present -->
                        <div class="card mb-4">
                        </div>
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-table me-1"></i>
                                Danh sách ĐÁNH GIÁ
                            </div>
                            <div class="card-body">
                                <table id="datatablesSimple">
                                    <thead>
                                        <tr>
                                            <th>Đánh giá</th>
                                            <th>Bình luận</th>
                                            <th>Ngày tạo</th>
                                            <th>Trạng thái duyệt</th>
                                            <th>Hành động</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        include 'config.php';
                                        $sql = "SELECT id, user_id, sanpham_id, rating, comment, created_at, is_edited, admin_reply, admin_id, is_seen, trangthaiduyet 
                                        FROM danhgia 
                                        ORDER BY id DESC";

                                        $result = $conn->query($sql);
                                        if ($result->num_rows > 0) {
                                            while ($row = $result->fetch_assoc()) {
                                                echo "<tr>";
                                                echo "<td>";
                                                $rating = $row["rating"];
                                                for ($i = 0; $i < $rating; $i++) {
                                                    echo "★"; 
                                                }
                                                echo "</td>";
                                                echo "<td>" . $row["comment"] . "</td>";
                                                echo "<td>" . $row["created_at"] . "</td>";
                                                echo "<td>" . ($row["trangthaiduyet"] == 1 ? "<span style='color: green;'>Đã duyệt</span>" : "<span style='color: red;'>Chưa duyệt</span>") . "</td>";
                                                echo "<td>";
                                                // Show "Duyệt" button only if the review is not yet approved
                                                if ($row["trangthaiduyet"] == 0) {
                                                    echo "<form action='' method='POST' style='display:inline;'>
                                                            <input type='hidden' name='review_id' value='" . $row["id"] . "'>
                                                            <button type='submit' class='btn btn-success btn-sm'>Duyệt</button>
                                                        </form>";
                                                }
                                                echo "<button class='btn btn-danger btn-sm delete-btn' data-id='" . $row["id"] . "'>
                                                    <i class='fas fa-trash-alt' title='Xóa'></i>
                                                    </button>";                                            
                                                echo "</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='12'>Không có đánh giá nào</td></tr>";
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
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script src="js/datatables-simple-demo.js"></script>
    </body>
</html>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const deleteButtons = document.querySelectorAll('.delete-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function () {
            const reviewId = this.getAttribute('data-id');
            Swal.fire({
                title: "Xác nhận xóa",
                text: "Bạn có chắc chắn muốn xóa đánh giá này không?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Xóa",
                cancelButtonText: "Hủy"
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('xoadanhgia.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `review_id=${reviewId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === "success") {
                            Swal.fire("Thành công!", "Đánh giá đã được xóa.", "success");
                            button.closest('tr').remove();
                        } else {
                            Swal.fire("Lỗi!", "Có lỗi xảy ra khi xóa đánh giá.", "error");
                        }
                    })
                    .catch(error => {
                        Swal.fire("Lỗi!", "Có lỗi xảy ra. Vui lòng thử lại!", "error");
                        console.error("Lỗi:", error);
                    });
                }
            });
        });
    });
});

</script>