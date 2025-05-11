<?php
session_start();
include 'config.php';

// Hàm kiểm tra quyền
function hasPermission($user_id, $permission) {
    global $conn;
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

    error_log("User không có quyền (vai trò: $phanquyen)");
    return false;
}

// Kiểm tra người dùng đã đăng nhập
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    echo json_encode(["status" => "error", "message" => "Bạn cần phải đăng nhập để thực hiện hành động này."]);
    exit();
}

// Lấy thông tin người dùng từ session
$user_id = $_SESSION['user']['id'];
error_log("User ID từ session: $user_id");

// Kiểm tra quyền edit_discount
if (!hasPermission($user_id, 'edit_discount')) {
    echo json_encode(["status" => "error", "message" => "Bạn không có quyền chỉnh sửa mã giảm giá!"]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = isset($_POST["id"]) ? (int)$_POST["id"] : 0;
    $code = isset($_POST["code"]) ? trim($_POST["code"]) : '';
    $discount_type = isset($_POST["discount_type"]) ? trim($_POST["discount_type"]) : '';
    $discount_value = isset($_POST["discount_value"]) ? floatval($_POST["discount_value"]) : 0;
    $min_order_value = isset($_POST["min_order_value"]) ? floatval($_POST["min_order_value"]) : 0;
    $max_uses = isset($_POST["max_uses"]) ? intval($_POST["max_uses"]) : 0;

    // Kiểm tra dữ liệu đầu vào
    if ($id <= 0 || empty($code) || empty($discount_type) || $discount_value <= 0 || $min_order_value < 0 || $max_uses < 0) {
        echo json_encode(["status" => "error", "message" => "Dữ liệu không hợp lệ!"]);
        exit();
    }

    if (!in_array($discount_type, ['percent', 'fixed'])) {
        echo json_encode(["status" => "error", "message" => "Loại giảm giá không hợp lệ!"]);
        exit();
    }

    if ($discount_type === 'percent' && ($discount_value < 0 || $discount_value > 100)) {
        echo json_encode(["status" => "error", "message" => "Giá trị giảm giá phần trăm phải từ 0 đến 100!"]);
        exit();
    }

    // Kiểm tra giá trị giảm và đơn hàng tối thiểu
    if ($discount_value > $min_order_value && $min_order_value > 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Giá trị giảm không được lớn hơn giá trị đơn hàng tối thiểu!'
        ]);
        exit;
    }

    // Kiểm tra mã giảm giá đã tồn tại (trừ chính nó)
    $checkSql = "SELECT id FROM discount_codes WHERE code = ? AND id != ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("si", $code, $id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    if ($result->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Mã giảm giá đã tồn tại!"]);
        $checkStmt->close();
        exit();
    }
    $checkStmt->close();

    // Cập nhật mã giảm giá
    $sql = "UPDATE discount_codes SET code = ?, discount_type = ?, discount_value = ?, min_order_value = ?, max_uses = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssdidi", $code, $discount_type, $discount_value, $min_order_value, $max_uses, $id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Mã giảm giá đã được cập nhật."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Lỗi SQL: " . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(["status" => "error", "message" => "Phương thức yêu cầu không hợp lệ!"]);
}
?>