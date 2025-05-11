<?php
session_start();

require 'vendor/autoload.php'; // Tải PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Kiểm tra nếu chưa đăng nhập hoặc không phải admin hoặc nhân viên
if (!isset($_SESSION['user']) || 
    ($_SESSION['user']['phanquyen'] !== 'admin' && $_SESSION['user']['phanquyen'] !== 'nhanvien')) {
    header("Location: dangnhap.php");
    exit();
}

// Xử lý xuất Excel
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['export_excel'])) {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "webbanhang";

    try {
        $conn = new mysqli($servername, $username, $password, $dbname);
        if ($conn->connect_error) {
            throw new Exception("Kết nối cơ sở dữ liệu thất bại: " . $conn->connect_error);
        }

        // Truy vấn dữ liệu người dùng
        $sql = "SELECT id, email, hoten, sdt, diachi FROM frm_dangky WHERE phanquyen = 'user'";
        $result_users = $conn->query($sql);

        // Tạo file Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Đặt tiêu đề cột
        $sheet->setCellValue('A1', 'Email');
        $sheet->setCellValue('B1', 'Họ tên');
        $sheet->setCellValue('C1', 'Số điện thoại');
        $sheet->setCellValue('D1', 'Địa chỉ');
        $sheet->setCellValue('E1', 'Tổng tiền');

        // Định dạng tiêu đề
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);
        $sheet->getStyle('A1:E1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Điền dữ liệu
        $row = 2;
        if ($result_users && $result_users->num_rows > 0) {
            while ($user = $result_users->fetch_assoc()) {
                $user_id = $user['id'];
                // Tính tổng tiền
                $sql_total = "SELECT SUM(total) AS total_spent FROM `oder` WHERE user_id = $user_id AND trangthai = 'Đã giao'";
                $result_total = $conn->query($sql_total);
                $total_spent = 0;
                if ($result_total && $result_total->num_rows > 0) {
                    $total_row = $result_total->fetch_assoc();
                    $total_spent = $total_row['total_spent'] ?? 0;
                }

                // Ghi dữ liệu vào Excel
                $sheet->setCellValue('A' . $row, $user['email']);
                $sheet->setCellValue('B' . $row, $user['hoten']);
                $sheet->setCellValue('C' . $row, $user['sdt']);
                $sheet->setCellValue('D' . $row, $user['diachi']);
                $sheet->setCellValue('E' . $row, number_format($total_spent, 0, ',', '.'));

                $row++;
            }
        }

        // Tự động điều chỉnh kích thước cột
        foreach (range('A', 'E') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Xuất file
        $filename = 'ThongKeNguoiDung_' . date('Ymd_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        $conn->close();
        exit();
    } catch (Exception $e) {
        echo "<div style='color: red; padding: 10px;'>Lỗi: " . $e->getMessage() . "</div>";
    }
}

include 'config.php';

$new_review_count = 0;
$new_order_count = 0; // Biến để lưu số đơn hàng chưa in
$new_pending_orders = 0; // Biến để lưu số đơn hàng chưa giao
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

// Truy vấn số lượng đơn hàng chưa giao
$sql_pending_orders = "SELECT COUNT(*) as count FROM oder WHERE trangthai NOT IN ('Đã giao', 'Đã hủy')";
$result_pending_orders = $conn->query($sql_pending_orders);

if ($result_pending_orders === false) {
    echo "Lỗi SQL (đơn hàng chưa giao): " . $conn->error;
    exit;
}

if ($result_pending_orders->num_rows > 0) {
    $new_pending_orders = $result_pending_orders->fetch_assoc()['count'];
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

$servername = "localhost";
$hoten = "root"; 
$password = ""; 
$dbname = "webbanhang"; 
$total_products = 0;
$total_users = 0;
$total_profit = 0;
try {
    $conn = new mysqli($servername, $hoten, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Kết nối cơ sở dữ liệu thất bại: " . $conn->connect_error);
    }
    $result_products = $conn->query("SELECT SUM(soluong) AS total_remaining FROM sanpham");
    if ($result_products && $result_products->num_rows > 0) {
        $row_products = $result_products->fetch_assoc();
        $total_products = $row_products['total_remaining'] ?? 0;
    }
    $result_users = $conn->query("SELECT COUNT(*) AS total_users FROM frm_dangky");
    if ($result_users && $result_users->num_rows > 0) {
        $row_users = $result_users->fetch_assoc();
        $total_users = $row_users['total_users'] ?? 0;
    }
    $query_profit = "
        SELECT 
            SUM(profit) AS total_profit
        FROM (
            SELECT 
                o.id,
                o.total,
                SUM(od.soluong * sp.gia_nhap) AS total_cost,
                (o.total - SUM(od.soluong * sp.gia_nhap)) AS profit
            FROM 
                `oder` o
            JOIN 
                oder_detail od ON o.id = od.oder_id
            JOIN 
                sanpham sp ON od.sanpham_id = sp.id
            WHERE 
                o.trangthai = 'Đã giao'
            GROUP BY 
                o.id, o.total
        ) AS subquery
    ";
    $result_profit = $conn->query($query_profit);
    if ($result_profit && $result_profit->num_rows > 0) {
        $row_profit = $result_profit->fetch_assoc();
        $total_profit = $row_profit['total_profit'] ?? 0;
    }
    $conn->close();
} catch (Exception $e) {
    echo "<div style='color: red; padding: 10px;'>Lỗi: " . $e->getMessage() . "</div>";
}
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
    <title>Quản Lý - SB Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
    <link href="css/styles.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        .flatpickr-input {
            width: 150px;
            padding: 5px;
            margin-left: 10px;
        }
        .month-year-picker {
            display: flex;
            gap: 10px;
        }
        canvas {
            max-height: 400px;
            width: 100%;
            color: black;
        }
    </style>
</head>
<body class="sb-nav-fixed">
<nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
    <a class="navbar-brand ps-3" href="admin.php">HUSTLE STONIE</a>
    <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" href="#!"><i class="fas fa-bars"></i></button>
    <form class="d-none d-md-inline-block form-inline ms-auto me-0 me-md-3 my-2 my-md-0"></form>
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
                        <div class="sb-nav-link-icon"><i class="fas fa-users"></i></div>
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
                        <div class="sb-nav-link-icon"><i class="fas fa-truck"></i></div>
                        QUẢN LÝ VẬN CHUYỂN 
                    </a>
                    <a class="nav-link" href="quanly_khuyenmai.php">
                        <div class="sb-nav-link-icon"><i class="fas fa-tags"></i></div>
                        QUẢN LÝ KHUYẾN MÃI 
                    </a>
                </div>
            </div>
        </nav>
    </div>
    <div id="layoutSidenav_content">
        <main>
            <div class="container-fluid px-4">
                <h1 class="mt-4">Thống kê</h1>
                <ol class="breadcrumb mb-4">
                    <li class="breadcrumb-item active">Thống kê</li>
                </ol>
                <div class="row">
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-primary text-white mb-4">
                            <div class="card-body">Số lượng sản phẩm còn lại: <?php echo number_format($total_products); ?></div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a class="small text-white stretched-link" href="quanlysanpham.php">Quản lý</a>
                                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-warning text-white mb-4">
                            <div class="card-body">Số lượng người dùng: <?php echo $total_users; ?></div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a class="small text-white stretched-link" href="quanlynguoidung.php">Quản lý</a>
                                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-success text-white mb-4">
                        <div class="card-body">Số lượng đơn hàng chưa giao: <?php echo number_format($new_pending_orders, 0, ',', '.'); ?></div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a class="small text-white stretched-link" href="quanlydonhang.php">Quản lý</a>
                                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-danger text-white mb-4">
                            <div class="card-body">Tổng lãi: <?php echo number_format($total_profit, 0, ',', '.') . ' VNĐ'; ?></div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a class="small text-white stretched-link" href="#">Quản lý</a>
                                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xl-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-chart-area me-1"></i>
                                Thống Kê Lợi Nhuận
                                <form method="GET" class="float-end">
                                    <select name="time_period" id="time_period" onchange="this.form.submit()">
                                        <option value="day" <?php echo (isset($_GET['time_period']) && $_GET['time_period'] == 'day') ? 'selected' : ''; ?>>Theo ngày</option>
                                        <option value="month" <?php echo (isset($_GET['time_period']) && $_GET['time_period'] == 'month') ? 'selected' : ''; ?>>Theo tháng</option>
                                        <option value="quarter" <?php echo (isset($_GET['time_period']) && $_GET['time_period'] == 'quarter') ? 'selected' : ''; ?>>Theo quý</option>
                                        <option value="year" <?php echo (isset($_GET['time_period']) && $_GET['time_period'] == 'year') ? 'selected' : ''; ?>>Theo năm</option>
                                    </select>
                                    <div id="day_picker" style="display: <?php echo (isset($_GET['time_period']) && $_GET['time_period'] == 'day') ? 'inline-block' : 'none'; ?>;">
                                        <input type="text" name="selected_date" class="flatpickr-input" placeholder="Chọn ngày" value="<?php echo isset($_GET['selected_date']) ? htmlspecialchars($_GET['selected_date']) : date('Y-m-d'); ?>">
                                    </div>
                                    <div id="week_picker" style="display: <?php echo (isset($_GET['time_period']) && $_GET['time_period'] == 'week') ? 'inline-block' : 'none'; ?>;">
                                        <button type="submit" name="week_offset" value="<?php echo (isset($_GET['week_offset']) ? (int)$_GET['week_offset'] - 1 : -1); ?>">← Tuần trước</button>
                                        <button type="submit" name="week_offset" value="<?php echo (isset($_GET['week_offset']) ? (int)$_GET['week_offset'] + 1 : 1); ?>">Tuần sau →</button>
                                    </div>
                                    <div id="month_picker" style="display: <?php echo (isset($_GET['time_period']) && $_GET['time_period'] == 'month') ? 'inline-block' : 'none'; ?>;">
                                        <select name="selected_month" onchange="this.form.submit()">
                                            <?php
                                            $default_month = date('m');
                                            $selected_month = isset($_GET['selected_month']) ? $_GET['selected_month'] : $default_month;
                                            for ($m = 1; $m <= 12; $m++) {
                                                $month = sprintf("%02d", $m);
                                                $selected = ($selected_month == $month) ? 'selected' : '';
                                                echo "<option value='$month' $selected>Tháng $m</option>";
                                            }
                                            ?>
                                        </select>
                                        <select name="selected_year" onchange="this.form.submit()">
                                            <?php
                                            $current_year = date('Y');
                                            $default_year = $current_year;
                                            $selected_year = isset($_GET['selected_year']) ? (int)$_GET['selected_year'] : $default_year;
                                            for ($y = $current_year - 5; $y <= $current_year; $y++) {
                                                $selected = ($selected_year == $y) ? 'selected' : '';
                                                echo "<option value='$y' $selected>$y</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </form>
                            </div>
                            <div class="card-body position-relative">
                                <canvas id="myAreaChart"></canvas>
                                <?php
                                $time_period = isset($_GET['time_period']) ? $_GET['time_period'] : 'day';
                                $labels = [];
                                $data = [];
                                $total_profit_for_period = 0;
                                try {
                                    $conn = new mysqli($servername, $hoten, $password, $dbname);
                                    if ($conn->connect_error) {
                                        throw new Exception("Kết nối cơ sở dữ liệu thất bại: " . $conn->connect_error);
                                    }
                                    date_default_timezone_set('Asia/Ho_Chi_Minh');
                                    $conn->query("SET time_zone = '+07:00'");
                                    
                                    if ($time_period == 'day') {
                                        $selected_date = isset($_GET['selected_date']) ? $_GET['selected_date'] : date('Y-m-d');
                                        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
                                            $selected_date = date('Y-m-d');
                                        }
                                        $sql = "
                                            SELECT 
                                                DATE(o.ngaydathang) AS period,
                                                SUM(o.total - (od.soluong * sp.gia_nhap)) AS profit
                                            FROM 
                                                `oder` o
                                            JOIN 
                                                oder_detail od ON o.id = od.oder_id
                                            JOIN 
                                                sanpham sp ON od.sanpham_id = sp.id
                                            WHERE 
                                                DATE(o.ngaydathang) = '$selected_date'
                                                AND o.trangthai = 'Đã giao'
                                            GROUP BY 
                                                DATE(o.ngaydathang)
                                        ";
                                        $result = $conn->query($sql);
                                        $labels[] = date('M d, Y', strtotime($selected_date));
                                        $data[] = 0;
                                        if ($result && $result->num_rows > 0) {
                                            $row = $result->fetch_assoc();
                                            $data[0] = (float)$row['profit'];
                                            $total_profit_for_period = (float)$row['profit'];
                                        }
                                    } elseif ($time_period == 'month') {
                                        $selected_month = isset($_GET['selected_month']) ? $_GET['selected_month'] : date('m');
                                        $selected_year = isset($_GET['selected_year']) ? $_GET['selected_year'] : date('Y');
                                        $month_start = "$selected_year-$selected_month-01";
                                        $month_end = date('Y-m-t 23:59:59', strtotime($month_start));
                                        $sql = "
                                            SELECT 
                                                DATE(o.ngaydathang) AS period,
                                                SUM(o.total - (od.soluong * sp.gia_nhap)) AS profit
                                            FROM 
                                                `oder` o
                                            JOIN 
                                                oder_detail od ON o.id = od.oder_id
                                            JOIN 
                                                sanpham sp ON od.sanpham_id = sp.id
                                            WHERE 
                                                o.ngaydathang >= '$month_start'
                                                AND o.ngaydathang <= '$month_end'
                                                AND o.trangthai = 'Đã giao'
                                            GROUP BY 
                                                DATE(o.ngaydathang)
                                            ORDER BY 
                                                period ASC
                                        ";
                                        $result = $conn->query($sql);
                                        $days_in_month = date('t', strtotime($month_start));
                                        $date_range = [];
                                        for ($day = 1; $day <= $days_in_month; $day++) {
                                            $date = "$selected_year-$selected_month-" . sprintf("%02d", $day);
                                            $date_range[$date] = 0;
                                            $labels[] = date('M d', strtotime($date));
                                        }
                                        if ($result && $result->num_rows > 0) {
                                            while ($row = $result->fetch_assoc()) {
                                                $date = $row['period'];
                                                if (isset($date_range[$date])) {
                                                    $date_range[$date] = (float)$row['profit'];
                                                    $total_profit_for_period += (float)$row['profit'];
                                                }
                                            }
                                        }
                                        $data = array_values($date_range);
                                    } elseif ($time_period == 'quarter') {
                                        $sql = "
                                            SELECT 
                                                CONCAT(YEAR(o.ngaydathang), '-Q', QUARTER(o.ngaydathang)) AS period,
                                                SUM(o.total - (od.soluong * sp.gia_nhap)) AS profit
                                            FROM 
                                                `oder` o
                                            JOIN 
                                                oder_detail od ON o.id = od.oder_id
                                            JOIN 
                                                sanpham sp ON od.sanpham_id = sp.id
                                            WHERE 
                                                o.ngaydathang >= DATE_SUB(CURDATE(), INTERVAL 4 QUARTER)
                                                AND o.ngaydathang <= CURDATE()
                                                AND o.trangthai = 'Đã giao'
                                            GROUP BY 
                                                YEAR(o.ngaydathang), QUARTER(o.ngaydathang)
                                            ORDER BY 
                                                YEAR(o.ngaydathang) ASC, QUARTER(o.ngaydathang) ASC
                                        ";
                                        $result = $conn->query($sql);
                                        $quarter_range = [];
                                        $current_year = date('Y');
                                        $current_quarter = ceil(date('n') / 3);
                                        for ($i = 3; $i >= 0; $i--) {
                                            $quarter = $current_quarter - $i;
                                            $year = $current_year;
                                            if ($quarter <= 0) {
                                                $quarter += 4;
                                                $year--;
                                            }
                                            $period = "$year-Q$quarter";
                                            $quarter_range[$period] = 0;
                                        }
                                        if ($result && $result->num_rows > 0) {
                                            while ($row = $result->fetch_assoc()) {
                                                $period = $row['period'];
                                                if (isset($quarter_range[$period])) {
                                                    $quarter_range[$period] = (float)$row['profit'];
                                                    $total_profit_for_period += (float)$row['profit'];
                                                }
                                            }
                                        }
                                        $labels = array_keys($quarter_range);
                                        $data = array_values($quarter_range);
                                    } elseif ($time_period == 'year') {
                                        $sql = "
                                            SELECT 
                                                YEAR(o.ngaydathang) AS period,
                                                SUM(o.total - (od.soluong * sp.gia_nhap)) AS profit
                                            FROM 
                                                `oder` o
                                            JOIN 
                                                oder_detail od ON o.id = od.oder_id
                                            JOIN 
                                                sanpham Sp ON od.sanpham_id = sp.id
                                            WHERE 
                                                o.ngaydathang >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)
                                                AND o.ngaydathang <= CURDATE()
                                                AND o.trangthai = 'Đã giao'
                                            GROUP BY 
                                                YEAR(o.ngaydathang)
                                            ORDER BY 
                                                period ASC
                                        ";
                                        $result = $conn->query($sql);
                                        $year_range = [];
                                        for ($i = 4; $i >= 0; $i--) {
                                            $year = date('Y') - $i;
                                            $year_range[$year] = 0;
                                        }
                                        if ($result && $result->num_rows > 0) {
                                            while ($row = $result->fetch_assoc()) {
                                                $year = $row['period'];
                                                if (isset($year_range[$year])) {
                                                    $year_range[$year] = (float)$row['profit'];
                                                    $total_profit_for_period += (float)$row['profit'];
                                                }
                                            }
                                        }
                                        $labels = array_keys($year_range);
                                        $data = array_values($year_range);
                                    }
                                    $conn->close();
                                } catch (Exception $e) {
                                    echo "<div style='color: red; padding: 10px;'>Lỗi: " . $e->getMessage() . "</div>";
                                    $labels = [$time_period == 'day' ? 'Selected Date' : 'Period'];
                                    $data = [0];
                                    $total_profit_for_period = 0;
                                }
                                $labels_json = json_encode($labels);
                                $data_json = json_encode($data);
                                ?>
                                <div style="position: absolute; bottom: 10px; right: 10px; font-size: 14px; color: #000;">
                                    Tổng lợi nhuận: <?php echo number_format($total_profit_for_period, 0, ',', '.') . ' VNĐ'; ?>
                                </div>
                                <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        if (typeof Chart === 'undefined') {
                                            console.error("Chart.js is not loaded!");
                                            return;
                                        }
                                        var ctx = document.getElementById("myAreaChart");
                                        if (!ctx) {
                                            console.error("Canvas element 'myAreaChart' not found!");
                                            return;
                                        }
                                        var labels = <?php echo $labels_json; ?>;
                                        var data = <?php echo $data_json; ?>;
                                        console.log("Labels:", labels);
                                        console.log("Data:", data);
                                        try {
                                            var myLineChart = new Chart(ctx, {
                                                type: '<?php echo $time_period == "day" ? "bar" : "line"; ?>',
                                                data: {
                                                    labels: labels,
                                                    datasets: [{
                                                        label: "Lợi Nhuận (VND)",
                                                        lineTension: 0.3,
                                                        backgroundColor: "rgba(78, 115, 223, 0.05)",
                                                        borderColor: "rgba(78, 115, 223, 1)",
                                                        pointRadius: 3,
                                                        pointBackgroundColor: "rgba(78, 115, 223, 1)",
                                                        pointBorderColor: "rgba(78, 115, 223, 1)",
                                                        pointHoverRadius: 3,
                                                        pointHoverBackgroundColor: "rgba(78, 115, 223, 1)",
                                                        pointHoverBorderColor: "rgba(78, 115, 223, 1)",
                                                        pointHitRadius: 10,
                                                        pointBorderWidth: 2,
                                                        data: data,
                                                    }],
                                                },
                                                options: {
                                                    maintainAspectRatio: false,
                                                    layout: { padding: 10 },
                                                    scales: {
                                                        xAxes: [{
                                                            gridLines: { display: false, drawBorder: false },
                                                            ticks: { fontColor: '#000000', maxTicksLimit: 7 }
                                                        }],
                                                        yAxes: [{
                                                            ticks: {
                                                                fontColor: '#000000',
                                                                maxTicksLimit: 5,
                                                                padding: 10,
                                                                callback: function(value) { return value.toLocaleString('vi-VN'); }
                                                            },
                                                            gridLines: {
                                                                color: "rgb(234, 236, 244)",
                                                                zeroLineColor: "rgb(234, 236, 244)",
                                                                drawBorder: false,
                                                                borderDash: [2],
                                                                zeroLineBorderDash: [2]
                                                            }
                                                        }],
                                                    },
                                                    legend: { display: false },
                                                    tooltips: {
                                                        backgroundColor: "rgb(255,255,255)",
                                                        bodyFontColor: "#858796",
                                                        titleMarginBottom: 10,
                                                        titleFontColor: '#6e707e',
                                                        titleFontSize: 14,
                                                        borderColor: '#dddfeb',
                                                        borderWidth: 1,
                                                        xPadding: 15,
                                                        yPadding: 15,
                                                        displayColors: false,
                                                        intersect: false,
                                                        mode: 'index',
                                                        caretPadding: 10,
                                                        callbacks: {
                                                            label: function(tooltipItem, chart) {
                                                                var datasetLabel = chart.datasets[tooltipItem.datasetIndex].label || '';
                                                                return datasetLabel + ': ' + tooltipItem.yLabel.toLocaleString('vi-VN') + ' VND';
                                                            }
                                                        }
                                                    }
                                                }
                                            });
                                            console.log("Chart initialized successfully!");
                                        } catch (error) {
                                            console.error("Error initializing chart:", error);
                                        }
                                    });
                                    document.addEventListener('DOMContentLoaded', function() {
                                        var timePeriodSelect = document.getElementById('time_period');
                                        if (timePeriodSelect) {
                                            timePeriodSelect.addEventListener('change', function() {
                                                var dayPicker = document.getElementById('day_picker');
                                                var weekPicker = document.getElementById('week_picker');
                                                var monthPicker = document.getElementById('month_picker');
                                                dayPicker.style.display = 'none';
                                                weekPicker.style.display = 'none';
                                                monthPicker.style.display = 'none';
                                                if (this.value === 'day') {
                                                    dayPicker.style.display = 'inline-block';
                                                } else if (this.value === 'week') {
                                                    weekPicker.style.display = 'inline-block';
                                                } else if (this.value === 'month') {
                                                    monthPicker.style.display = 'inline-block';
                                                }
                                            });
                                        }
                                    });
                                    document.addEventListener('DOMContentLoaded', function() {
                                        flatpickr(".flatpickr-input", {
                                            dateFormat: "Y-m-d",
                                            maxDate: "<?php echo date('Y-m-d'); ?>",
                                            onChange: function(selectedDates, dateStr, instance) {
                                                instance.element.form.submit();
                                            }
                                        });
                                    });
                                </script>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-chart-bar me-1"></i>
                                Thống Kê Lợi Nhuận Theo Năm
                                <form method="GET" class="float-end">
                                    <select name="bar_chart_year" onchange="this.form.submit()">
                                        <?php
                                        $current_year = date('Y');
                                        $default_bar_chart_year = isset($_GET['bar_chart_year']) ? (int)$_GET['bar_chart_year'] : $current_year;
                                        for ($y = $current_year - 5; $y <= $current_year; $y++) {
                                            $selected = ($default_bar_chart_year == $y) ? 'selected' : '';
                                            echo "<option value='$y' $selected>$y</option>";
                                        }
                                        ?>
                                    </select>
                                </form>
                            </div>
                            <div class="card-body position-relative">
                                <canvas id="myBarChart"></canvas>
                                <?php
                                $selected_bar_chart_year = isset($_GET['bar_chart_year']) ? (int)$_GET['bar_chart_year'] : date('Y');
                                $bar_labels = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                                $bar_data = array_fill(0, 12, 0);
                                $total_profit_for_year = 0;
                                try {
                                    $conn = new mysqli($servername, $hoten, $password, $dbname);
                                    if ($conn->connect_error) {
                                        throw new Exception("Kết nối cơ sở dữ liệu thất bại: " . $conn->connect_error);
                                    }
                                    date_default_timezone_set('Asia/Ho_Chi_Minh');
                                    $conn->query("SET time_zone = '+07:00'");
                                    $sql = "
                                        SELECT 
                                            MONTH(o.ngaydathang) AS month, 
                                            SUM(o.total - (od.soluong * sp.gia_nhap)) AS profit
                                        FROM 
                                            `oder` o
                                        JOIN 
                                            oder_detail od ON o.id = od.oder_id
                                        JOIN 
                                            sanpham sp ON od.sanpham_id = sp.id
                                        WHERE 
                                            YEAR(o.ngaydathang) = $selected_bar_chart_year
                                            AND o.trangthai = 'Đã giao'
                                        GROUP BY 
                                            MONTH(o.ngaydathang)
                                        ORDER BY 
                                            MONTH(o.ngaydathang)
                                    ";
                                    $result = $conn->query($sql);
                                    if ($result && $result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            $month = (int)$row['month'] - 1;
                                            $bar_data[$month] = (float)$row['profit'];
                                            $total_profit_for_year += (float)$row['profit'];
                                        }
                                    }
                                    $conn->close();
                                } catch (Exception $e) {
                                    echo "<div style='color: red; padding: 10px;'>Lỗi: " . $e->getMessage() . "</div>";
                                    $total_profit_for_year = 0;
                                }
                                $bar_labels_json = json_encode($bar_labels);
                                $bar_data_json = json_encode($bar_data);
                                ?>
                                <div style="position: absolute; bottom: 10px; right: 10px; font-size: 14px; color: #000;">
                                    Tổng lợi nhuận: <?php echo number_format($total_profit_for_year, 0, ',', '.') . ' VNĐ'; ?>
                                </div>
                                <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        if (typeof Chart === 'undefined') {
                                            console.error("Chart.js is not loaded!");
                                            return;
                                        }
                                        var ctx = document.getElementById("myBarChart");
                                        if (!ctx) {
                                            console.error("Canvas element 'myBarChart' not found!");
                                            return;
                                        }
                                        var labels = <?php echo $bar_labels_json; ?>;
                                        var data = <?php echo $bar_data_json; ?>;
                                        console.log("Bar Chart Labels:", labels);
                                        console.log("Bar Chart Data:", data);
                                        try {
                                            var myBarChart = new Chart(ctx, {
                                                type: 'bar',
                                                data: {
                                                    labels: labels,
                                                    datasets: [{
                                                        label: "Lợi Nhuận (VND)",
                                                        backgroundColor: "rgba(78, 115, 223, 1)",
                                                        borderColor: "rgba(78, 115, 223, 1)",
                                                        data: data,
                                                    }],
                                                },
                                                options: {
                                                    maintainAspectRatio: false,
                                                    layout: { padding: 10 },
                                                    scales: {
                                                        xAxes: [{
                                                            gridLines: { display: false, drawBorder: false },
                                                            ticks: { fontColor: '#000000', maxTicksLimit: 12 }
                                                        }],
                                                        yAxes: [{
                                                            ticks: {
                                                                fontColor: '#000000',
                                                                maxTicksLimit: 5,
                                                                padding: 10,
                                                                callback: function(value) { return value.toLocaleString('vi-VN'); }
                                                            },
                                                            gridLines: {
                                                                color: "rgb(234, 236, 244)",
                                                                zeroLineColor: "rgb(234, 236, 244)",
                                                                drawBorder: false,
                                                                borderDash: [2],
                                                                zeroLineBorderDash: [2]
                                                            }
                                                        }],
                                                    },
                                                    legend: { display: false },
                                                    tooltips: {
                                                        backgroundColor: "rgb(255,255,255)",
                                                        bodyFontColor: "#858796",
                                                        titleMarginBottom: 10,
                                                        titleFontColor: '#6e707e',
                                                        titleFontSize: 14,
                                                        borderColor: '#dddfeb',
                                                        borderWidth: 1,
                                                        xPadding: 15,
                                                        yPadding: 15,
                                                        displayColors: false,
                                                        caretPadding: 10,
                                                        callbacks: {
                                                            label: function(tooltipItem, chart) {
                                                                var datasetLabel = chart.datasets[tooltipItem.datasetIndex].label || '';
                                                                return datasetLabel + ': ' + tooltipItem.yLabel.toLocaleString('vi-VN') + ' VND';
                                                            }
                                                        }
                                                    }
                                                }
                                            });
                                            console.log("Bar Chart initialized successfully!");
                                        } catch (error) {
                                            console.error("Error initializing bar chart:", error);
                                        }
                                    });
                                </script>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xl-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-chart-bar me-1"></i>
                                Top 5 Sản Phẩm Bán Chạy Nhất
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="myTopCategoriesChart"></canvas>
                                </div>
                                <?php
                                $time_period = isset($_GET['time_period']) ? $_GET['time_period'] : 'day';
                                $selected_date = isset($_GET['selected_date']) ? $_GET['selected_date'] : date('Y-m-d');
                                $selected_month = isset($_GET['selected_month']) ? $_GET['selected_month'] : date('m');
                                $selected_year = isset($_GET['selected_year']) ? $_GET['selected_year'] : date('Y');
                                $selected_bar_chart_year = isset($_GET['bar_chart_year']) ? (int)$_GET['bar_chart_year'] : date('Y');
                                $top_products_labels = [];
                                $top_products_data = [];
                                $top_products_totals = [];
                                try {
                                    $conn = new mysqli($servername, $hoten, $password, $dbname);
                                    if ($conn->connect_error) {
                                        throw new Exception("Kết nối cơ sở dữ liệu thất bại: " . $conn->connect_error);
                                    }
                                    $conn->set_charset("utf8mb4");
                                    date_default_timezone_set('Asia/Ho_Chi_Minh');
                                    $conn->query("SET time_zone = '+07:00'");
                                    $sql = "
                                        SELECT sp.tensanpham, SUM(od.soluong) AS total_sold
                                        FROM oder_detail od
                                        INNER JOIN sanpham sp ON od.sanpham_id = sp.id
                                        INNER JOIN `oder` o ON od.oder_id = o.id
                                        WHERE TRIM(o.trangthai) = 'Đã giao'
                                    ";
                                    if ($time_period == 'day') {
                                        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
                                            $selected_date = date('Y-m-d');
                                        }
                                        $sql .= " AND DATE(o.ngaydathang) = '$selected_date'";
                                    } elseif ($time_period == 'month') {
                                        $month_start = "$selected_year-$selected_month-01";
                                        $month_end = date('Y-m-t 23:59:59', strtotime($month_start));
                                        $sql .= " AND o.ngaydathang >= '$month_start' AND o.ngaydathang <= '$month_end'";
                                    } elseif ($time_period == 'quarter') {
                                        $selected_month_int = (int)$selected_month;
                                        $quarter = ceil($selected_month_int / 3);
                                        $quarter_start_month = ($quarter - 1) * 3 + 1;
                                        $quarter_end_month = $quarter * 3;
                                        $quarter_start = "$selected_year-" . sprintf("%02d", $quarter_start_month) . "-01";
                                        $quarter_end = date('Y-m-t 23:59:59', strtotime("$selected_year-" . sprintf("%02d", $quarter_end_month) . "-01"));
                                        $sql .= " AND o.ngaydathang >= '$quarter_start' AND o.ngaydathang <= '$quarter_end'";
                                    } elseif ($time_period == 'year') {
                                        $sql .= " AND YEAR(o.ngaydathang) = $selected_bar_chart_year";
                                    }
                                    $sql .= "
                                        GROUP BY od.sanpham_id, sp.tensanpham
                                        ORDER BY total_sold DESC
                                        LIMIT 5
                                    ";
                                    $result = $conn->query($sql);
                                    if ($result === false) {
                                        throw new Exception("Query failed: " . $conn->error);
                                    }
                                    if ($result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            $top_products_labels[] = $row['tensanpham'];
                                            $top_products_data[] = (int)$row['total_sold'];
                                            $top_products_totals[] = (int)$row['total_sold'];
                                        }
                                    } else {
                                        $top_products_data = [0];
                                        $top_products_totals = [0];
                                    }
                                    $conn->close();
                                } catch (Exception $e) {
                                    echo "<div style='color: red; padding: 10px;'>Lỗi: " . $e->getMessage() . "</div>";
                                    $top_products_labels = ['Lỗi dữ liệu'];
                                    $top_products_data = [0];
                                    $top_products_totals = [0];
                                }
                                $top_products_labels_json = json_encode($top_products_labels);
                                $top_products_data_json = json_encode($top_products_data);
                                $top_products_totals_json = json_encode($top_products_totals);
                                ?>
                                <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        if (typeof Chart === 'undefined') {
                                            console.error("Chart.js is not loaded!");
                                            return;
                                        }
                                        var ctx = document.getElementById("myTopCategoriesChart");
                                        if (!ctx) {
                                            console.error("Canvas element 'myTopCategoriesChart' not found!");
                                            return;
                                        }
                                        var labels = <?php echo $top_products_labels_json; ?>;
                                        var data = <?php echo $top_products_data_json; ?>;
                                        var totals = <?php echo $top_products_totals_json; ?>;
                                        console.log("Top Products Chart Labels:", labels);
                                        console.log("Top Products Chart Data:", data);
                                        var barColors = ['#FF0000', '#0000FF', '#FFFF00', '#008080', '#800080'];
                                        try {
                                            var myTopProductsChart = new Chart(ctx, {
                                                type: 'bar',
                                                data: {
                                                    labels: totals.map((total, index) => `${total.toLocaleString('vi-VN')}`),
                                                    datasets: [{
                                                        label: "Số Lượng Bán",
                                                        backgroundColor: barColors.slice(0, data.length),
                                                        borderColor: barColors.slice(0, data.length),
                                                        data: data,
                                                        barPercentage: 0.5,
                                                        categoryPercentage: 0.8
                                                    }],
                                                },
                                                options: {
                                                    maintainAspectRatio: false,
                                                    layout: { padding: 10 },
                                                    scales: {
                                                        xAxes: [{
                                                            gridLines: { display: false, drawBorder: false },
                                                            ticks: { maxTicksLimit: 5, fontColor: '#000000', fontSize: 12, fontStyle: 'bold', maxRotation: 0, minRotation: 0 }
                                                        }],
                                                        yAxes: [{
                                                            ticks: {
                                                                beginAtZero: true,
                                                                maxTicksLimit: 5,
                                                                padding: 10,
                                                                callback: function(value) { return value.toLocaleString('vi-VN'); },
                                                                fontColor: '#000000',
                                                                fontSize: 12,
                                                                fontStyle: 'bold'
                                                            },
                                                            gridLines: {
                                                                color: "rgb(234, 236, 244)",
                                                                zeroLineColor: "rgb(234, 236, 244)",
                                                                drawBorder: false,
                                                                borderDash: [2],
                                                                zeroLineBorderDash: [2]
                                                            }
                                                        }],
                                                    },
                                                    legend: { display: false },
                                                    tooltips: {
                                                        backgroundColor: "rgb(255,255,255)",
                                                        bodyFontColor: "#858796",
                                                        titleMarginBottom: 10,
                                                        titleFontColor: '#6e707e',
                                                        titleFontSize: 14,
                                                        borderColor: '#dddfeb',
                                                        borderWidth: 1,
                                                        xPadding: 15,
                                                        yPadding: 15,
                                                        displayColors: false,
                                                        caretPadding: 10,
                                                        callbacks: {
                                                            title: function(tooltipItems, data) {
                                                                var idx = tooltipItems[0].index;
                                                                return labels[idx];
                                                            },
                                                            label: function(tooltipItem, chart) {
                                                                return 'Số Lượng Bán: ' + tooltipItem.yLabel.toLocaleString('vi-VN');
                                                            }
                                                        }
                                                    }
                                                }
                                            });
                                            var legendContainer = document.createElement('div');
                                            legendContainer.style.textAlign = 'center';
                                            legendContainer.style.marginTop = '10px';
                                            labels.forEach((label, index) => {
                                                if (data[index] > 0) {
                                                    var legendItem = document.createElement('span');
                                                    legendItem.style.display = 'inline-block';
                                                    legendItem.style.marginRight = '20px';
                                                    legendItem.style.marginBottom = '5px';
                                                    var colorBox = document.createElement('span');
                                                    colorBox.style.display = 'inline-block';
                                                    colorBox.style.width = '15px';
                                                    colorBox.style.height = '15px';
                                                    colorBox.style.backgroundColor = barColors[index % barColors.length];
                                                    colorBox.style.marginRight = '5px';
                                                    colorBox.style.verticalAlign = 'middle';
                                                    var text = document.createTextNode(label);
                                                    legendItem.appendChild(colorBox);
                                                    legendItem.appendChild(text);
                                                    legendContainer.appendChild(legendItem);
                                                }
                                            });
                                            ctx.parentNode.appendChild(legendContainer);
                                            console.log("Top Products Chart initialized successfully!");
                                        } catch (error) {
                                            console.error("Error initializing top products chart:", error);
                                        }
                                    });
                                </script>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-chart-bar me-1"></i>
                                Top 5 Người Dùng Mua Hàng Nhiều Nhất
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="myTopCustomersChart"></canvas>
                                </div>
                                <?php
                                $time_period = isset($_GET['time_period']) ? $_GET['time_period'] : 'day';
                                $selected_date = isset($_GET['selected_date']) ? $_GET['selected_date'] : date('Y-m-d');
                                $selected_month = isset($_GET['selected_month']) ? $_GET['selected_month'] : date('m');
                                $selected_year = isset($_GET['selected_year']) ? $_GET['selected_year'] : date('Y');
                                $selected_bar_chart_year = isset($_GET['bar_chart_year']) ? (int)$_GET['bar_chart_year'] : date('Y');
                                $top_customers_labels = [];
                                $top_customers_data = [];
                                $top_customers_totals = [];
                                try {
                                    $conn = new mysqli($servername, $hoten, $password, $dbname);
                                    if ($conn->connect_error) {
                                        throw new Exception("Kết nối cơ sở dữ liệu thất bại: " . $conn->connect_error);
                                    }
                                    $conn->set_charset("utf8mb4");
                                    date_default_timezone_set('Asia/Ho_Chi_Minh');
                                    $conn->query("SET time_zone = '+07:00'");
                                    $sql = "
                                        SELECT kh.hoten, SUM(o.total) AS total_spent
                                        FROM `oder` o
                                        INNER JOIN frm_dangky kh ON o.user_id = kh.id
                                        WHERE TRIM(o.trangthai) = 'Đã giao'
                                    ";
                                    if ($time_period == 'day') {
                                        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
                                            $selected_date = date('Y-m-d');
                                        }
                                        $sql .= " AND DATE(o.ngaydathang) = '$selected_date'";
                                    } elseif ($time_period == 'month') {
                                        $month_start = "$selected_year-$selected_month-01";
                                        $month_end = date('Y-m-t 23:59:59', strtotime($month_start));
                                        $sql .= " AND o.ngaydathang >= '$month_start' AND o.ngaydathang <= '$month_end'";
                                    } elseif ($time_period == 'quarter') {
                                        $selected_month_int = (int)$selected_month;
                                        $quarter = ceil($selected_month_int / 3);
                                        $quarter_start_month = ($quarter - 1) * 3 + 1;
                                        $quarter_end_month = $quarter * 3;
                                        $quarter_start = "$selected_year-" . sprintf("%02d", $quarter_start_month) . "-01";
                                        $quarter_end = date('Y-m-t 23:59:59', strtotime("$selected_year-" . sprintf("%02d", $quarter_end_month) . "-01"));
                                        $sql .= " AND o.ngaydathang >= '$quarter_start' AND o.ngaydathang <= '$quarter_end'";
                                    } elseif ($time_period == 'year') {
                                        $sql .= " AND YEAR(o.ngaydathang) = $selected_bar_chart_year";
                                    }
                                    $sql .= "
                                        GROUP BY kh.id, kh.hoten
                                        ORDER BY total_spent DESC
                                        LIMIT 5
                                    ";
                                    $result = $conn->query($sql);
                                    if ($result === false) {
                                        throw new Exception("Query failed: " . $conn->error);
                                    }
                                    if ($result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            $top_customers_labels[] = $row['hoten'];
                                            $top_customers_data[] = (float)$row['total_spent'];
                                            $top_customers_totals[] = (float)$row['total_spent'];
                                        }
                                    } else {
                                        $top_customers_data = [0];
                                        $top_customers_totals = [0];
                                    }
                                    $conn->close();
                                } catch (Exception $e) {
                                    echo "<div style='color: red; padding: 10px;'>Lỗi: " . $e->getMessage() . "</div>";
                                    $top_customers_labels = ['Lỗi dữ liệu'];
                                    $top_customers_data = [0];
                                    $top_customers_totals = [0];
                                }
                                $top_customers_labels_json = json_encode($top_customers_labels);
                                $top_customers_data_json = json_encode($top_customers_data);
                                $top_customers_totals_json = json_encode($top_customers_totals);
                                ?>
                                <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        if (typeof Chart === 'undefined') {
                                            console.error("Chart.js is not loaded!");
                                            return;
                                        }
                                        var ctx = document.getElementById("myTopCustomersChart");
                                        if (!ctx) {
                                            console.error("Canvas element 'myTopCustomersChart' not found!");
                                            return;
                                        }
                                        var labels = <?php echo $top_customers_labels_json; ?>;
                                        var data = <?php echo $top_customers_data_json; ?>;
                                        var totals = <?php echo $top_customers_totals_json; ?>;
                                        console.log("Top Customers Chart Labels:", labels);
                                        console.log("Top Customers Chart Data:", data);
                                        var barColors = ['#FF4500', '#32CD32', '#1E90FF', '#FFD700', '#FF69B4'];
                                        try {
                                            var myTopCustomersChart = new Chart(ctx, {
                                                type: 'bar',
                                                data: {
                                                    labels: totals.map((total, index) => `${total.toLocaleString('vi-VN')} VNĐ`),
                                                    datasets: [{
                                                        label: "Tổng Số Tiền (VNĐ)",
                                                        backgroundColor: barColors.slice(0, data.length),
                                                        borderColor: barColors.slice(0, data.length),
                                                        data: data,
                                                        barPercentage: 0.5,
                                                        categoryPercentage: 0.8
                                                    }],
                                                },
                                                options: {
                                                    maintainAspectRatio: false,
                                                    layout: { padding: 10 },
                                                    scales: {
                                                        xAxes: [{
                                                            gridLines: { display: false, drawBorder: false },
                                                            ticks: { maxTicksLimit: 5, fontColor: '#000000', fontSize: 12, fontStyle: 'bold', maxRotation: 0, minRotation: 0 }
                                                        }],
                                                        yAxes: [{
                                                            ticks: {
                                                                beginAtZero: true,
                                                                maxTicksLimit: 5,
                                                                padding: 10,
                                                                callback: function(value) { return value.toLocaleString('vi-VN'); },
                                                                fontColor: '#000000',
                                                                fontSize: 12,
                                                                fontStyle: 'bold'
                                                            },
                                                            gridLines: {
                                                                color: "rgb(234, 236, 244)",
                                                                zeroLineColor: "rgb(234, 236, 244)",
                                                                drawBorder: false,
                                                                borderDash: [2],
                                                                zeroLineBorderDash: [2]
                                                            }
                                                        }],
                                                    },
                                                    legend: { display: false },
                                                    tooltips: {
                                                        backgroundColor: "rgb(255,255,255)",
                                                        bodyFontColor: "#858796",
                                                        titleMarginBottom: 10,
                                                        titleFontColor: '#6e707e',
                                                        titleFontSize: 14,
                                                        borderColor: '#dddfeb',
                                                        borderWidth: 1,
                                                        xPadding: 15,
                                                        yPadding: 15,
                                                        displayColors: false,
                                                        caretPadding: 10,
                                                        callbacks: {
                                                            title: function(tooltipItems, data) {
                                                                var idx = tooltipItems[0].index;
                                                                return labels[idx];
                                                            },
                                                            label: function(tooltipItem, chart) {
                                                                return 'Tổng Số Tiền: ' + tooltipItem.yLabel.toLocaleString('vi-VN') + ' VNĐ';
                                                            }
                                                        }
                                                    }
                                                }
                                            });
                                            var legendContainer = document.createElement('div');
                                            legendContainer.style.textAlign = 'center';
                                            legendContainer.style.marginTop = '10px';
                                            labels.forEach((label, index) => {
                                                var legendItem = document.createElement('span');
                                                legendItem.style.display = 'inline-block';
                                                legendItem.style.marginRight = '20px';
                                                legendItem.style.marginBottom = '5px';
                                                var colorBox = document.createElement('span');
                                                colorBox.style.display = 'inline-block';
                                                colorBox.style.width = '15px';
                                                colorBox.style.height = '15px';
                                                colorBox.style.backgroundColor = barColors[index % barColors.length];
                                                colorBox.style.marginRight = '5px';
                                                colorBox.style.verticalAlign = 'middle';
                                                var text = document.createTextNode(label);
                                                legendItem.appendChild(colorBox);
                                                legendItem.appendChild(text);
                                                legendContainer.appendChild(legendItem);
                                            });
                                            ctx.parentNode.appendChild(legendContainer);
                                            console.log("Top Customers Chart initialized successfully!");
                                        } catch (error) {
                                            console.error("Error initializing top customers chart:", error);
                                        }
                                    });
                                </script>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-table me-1"></i>
                        Thống kê người dùng mua hàng
                        <form method="POST" action="" class="float-end">
                            <button type="submit" name="export_excel" class="btn btn-success btn-sm">Xuất Excel</button>
                        </form>
                    </div>
                    <div class="card-body">
                        <table id="datatablesSimple">
                            <thead>
                                <tr>
                                    <th>Email</th>
                                    <th>Họ tên</th>
                                    <th>Số điện thoại</th>
                                    <th>Địa chỉ</th>
                                    <th>Tổng tiền</th>
                                </tr>
                            </thead>
                            <tfoot>
                                <tr>
                                    <th>Email</th>
                                    <th>Họ tên</th>
                                    <th>Số điện thoại</th>
                                    <th>Địa chỉ</th>
                                    <th>Tổng tiền</th>
                                </tr>
                            </tfoot>
                            <tbody>
                                <?php
                                try {
                                    $conn = new mysqli($servername, $hoten, $password, $dbname);
                                    if ($conn->connect_error) {
                                        throw new Exception("Kết nối cơ sở dữ liệu thất bại: " . $conn->connect_error);
                                    }
                                    $sql = "SELECT id, email, hoten, sdt, diachi FROM frm_dangky WHERE phanquyen = 'user'";
                                    $result_users = $conn->query($sql);
                                    if ($result_users && $result_users->num_rows > 0) {
                                        while ($row = $result_users->fetch_assoc()) {
                                            $user_id = $row['id'];
                                            $sql_total = "SELECT SUM(total) AS total_spent FROM `oder` WHERE user_id = $user_id AND trangthai = 'Đã giao'";
                                            $result_total = $conn->query($sql_total);
                                            $total_spent = 0;
                                            if ($result_total && $result_total->num_rows > 0) {
                                                $total_row = $result_total->fetch_assoc();
                                                $total_spent = $total_row['total_spent'] ?? 0;
                                            }
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['hoten']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['sdt']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['diachi']) . "</td>";
                                            echo "<td>" . number_format($total_spent, 0, ',', '.') . "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='5'>Không có người dùng nào có quyền 'user'</td></tr>";
                                    }
                                    $conn->close();
                                } catch (Exception $e) {
                                    echo "<tr><td colspan='5'>Lỗi: " . $e->getMessage() . "</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
            <footer class="py-4 bg-light mt-auto">
                <div class="container-fluid px-4">
                    <div class="d-flex align-items-center justify-content-between small">
                        <div class="text-muted">Copyright © Hustle Stonie</div>
                        <div>
                            <a href="#">Privacy Policy</a>
                            ·
                            <a href="#">Terms & Conditions</a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="js/scripts.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
    <script src="js/datatables-simple-demo.js"></script>
</body>
</html>