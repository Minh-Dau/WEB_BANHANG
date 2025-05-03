<?php
session_start();
include 'config.php';

header('Content-Type: application/json');

// Hàm kiểm tra quyền
function hasPermission($user_id, $permission) {
    global $conn;

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

    if ($phanquyen === 'admin') {
        error_log("User là admin, cấp quyền tự động");
        return true;
    }

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

// Kiểm tra đăng nhập
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    echo json_encode(["status" => "error", "message" => "Bạn chưa đăng nhập."]);
    exit();
}

$user_id = $_SESSION['user']['id'];
error_log("User ID từ session: $user_id");

// Kiểm tra quyền delete_discount
if (!hasPermission($user_id, 'delete_discount')) {
    echo json_encode(["status" => "error", "message" => "Bạn không có quyền xóa mã giảm giá!"]);
    exit();
}

// Xử lý xóa
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = isset($_POST["id"]) ? (int)$_POST["id"] : 0;

    if ($id <= 0) {
        echo json_encode(["status" => "error", "message" => "ID không hợp lệ!"]);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM discount_codes WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Đã xóa mã giảm giá."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Lỗi khi xóa: " . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(["status" => "error", "message" => "Phương thức yêu cầu không hợp lệ!"]);
}
?>