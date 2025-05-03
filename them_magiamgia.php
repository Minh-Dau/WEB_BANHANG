<?php
session_start();
include 'config.php'; // Kết nối database

// Đặt header để đảm bảo phản hồi JSON sử dụng UTF-8
header('Content-Type: application/json; charset=UTF-8');

// Hàm kiểm tra quyền
function hasPermission($user_id, $permission) {
    global $conn;
    // Log user_id để kiểm tra
    error_log("Kiểm tra quyền cho user_id: $user_id, permission: $permission");

    // Lấy vai trò chính
    $sql = "SELECT phanquyen FROM frm_dangky WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $phanquyen = '';
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $phanquyen = $row['phanquyen'];
        error_log("Vai trò của user_id $user_id: $phanquyen");
    } else {
        error_log("Không tìm thấy vai trò cho user_id: $user_id");
    }
    $stmt->close();

    // Nếu là admin, có tất cả quyền
    if ($phanquyen === 'admin') {
        error_log("User là admin, cấp quyền tự động");
        return true;
    }

    // Nếu là nhanvien, kiểm tra quyền chi tiết
    if ($phanquyen === 'nhanvien') {
        $sql_perm = "SELECT permission FROM employee_permissions WHERE user_id = ? AND permission = ?";
        $stmt_perm = $conn->prepare($sql_perm);
        $stmt_perm->bind_param("is", $user_id, $permission);
        $stmt_perm->execute();
        $result_perm = $stmt_perm->get_result();
        $has_perm = $result_perm->num_rows > 0;
        if ($has_perm) {
            error_log("Quyền $permission được cấp cho user_id: $user_id");
        } else {
            error_log("Không có quyền $permission cho user_id: $user_id");
        }
        $stmt_perm->close();
        return $has_perm;
    }

    // Nếu là user, không có quyền
    error_log("User là user, không có quyền");
    return false;
}

// Kiểm tra người dùng đã đăng nhập
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    echo json_encode(["status" => "error", "message" => "Bạn cần phải đăng nhập để thực hiện hành động này."]);
    exit();
}

// Lấy thông tin người dùng từ session
$user_id = $_SESSION['user']['id'];
$phanquyen = $_SESSION['user']['phanquyen'] ?? '';
error_log("User ID từ session: $user_id, Vai trò từ session: $phanquyen");

// Kiểm tra quyền add_discount
if (!hasPermission($user_id, 'add_discount')) {
    echo json_encode(["status" => "error", "message" => "Bạn không có quyền thêm mã giảm giá!"]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Lấy và kiểm tra dữ liệu đầu vào
    $code = isset($_POST["code"]) ? trim($_POST["code"]) : '';
    $discount_type = isset($_POST["discount_type"]) ? trim($_POST["discount_type"]) : '';
    $discount_value = isset($_POST["discount_value"]) ? floatval($_POST["discount_value"]) : 0;
    $min_order_value = isset($_POST["min_order_value"]) ? floatval($_POST["min_order_value"]) : 0;
    $max_uses = isset($_POST["max_uses"]) ? intval($_POST["max_uses"]) : 0;
    $expiry_date = isset($_POST["expiry_date"]) ? trim($_POST["expiry_date"]) : '';
    $is_active = isset($_POST["is_active"]) ? intval($_POST["is_active"]) : 0;

    // Kiểm tra dữ liệu bắt buộc
    if (empty($code) || empty($discount_type) || $discount_value <= 0 || $min_order_value < 0 || $max_uses <= 0 || empty($expiry_date)) {
        echo json_encode(["status" => "error", "message" => "Thiếu hoặc dữ liệu không hợp lệ! Vui lòng kiểm tra các trường bắt buộc."]);
        exit();
    }

    // Kiểm tra định dạng discount_type
    if (!in_array($discount_type, ['percentage', 'fixed'])) {
        echo json_encode(["status" => "error", "message" => "Loại giảm giá không hợp lệ!"]);
        exit();
    }

    // Kiểm tra giá trị giảm giá
    if ($discount_type === 'percentage' && ($discount_value < 0 || $discount_value > 100)) {
        echo json_encode(["status" => "error", "message" => "Giá trị giảm giá phần trăm phải nằm trong khoảng 0-100!"]);
        exit();
    }

    // Kiểm tra ngày hết hạn
    $currentDate = date('Y-m-d H:i:s');
    if (strtotime($expiry_date) <= strtotime($currentDate)) {
        echo json_encode(["status" => "error", "message" => "Ngày hết hạn phải lớn hơn ngày hiện tại!"]);
        exit();
    }

    // Kiểm tra mã giảm giá đã tồn tại
    $checkSql = "SELECT id FROM discount_codes WHERE code = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("s", $code);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    if ($result->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Mã giảm giá đã tồn tại!"]);
        $checkStmt->close();
        exit();
    }
    $checkStmt->close();

    // Thêm mã giảm giá
    $sql = "INSERT INTO discount_codes (code, discount_type, discount_value, min_order_value, max_uses, expiry_date, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssddiss", $code, $discount_type, $discount_value, $min_order_value, $max_uses, $expiry_date, $is_active);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Thêm mã giảm giá thành công!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Lỗi SQL: " . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(["status" => "error", "message" => "Phương thức yêu cầu không hợp lệ!"]);
}
?>