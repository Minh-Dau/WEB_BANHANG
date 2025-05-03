<?php
session_start();
include 'config.php'; // Kết nối database

// Đặt header để đảm bảo phản hồi JSON sử dụng UTF-8
header('Content-Type: application/json; charset=UTF-8');

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
    }
    $stmt->close();

    // Nếu là admin, có tất cả quyền
    if ($phanquyen === 'admin') {
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
        $stmt_perm->close();
        return $has_perm;
    }

    // Nếu là user, không có quyền
    return false;
}

// Kiểm tra người dùng đã đăng nhập
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    echo json_encode(["status" => "error", "message" => "Bạn cần phải đăng nhập để thực hiện hành động này."]);
    exit();
}

// Lấy thông tin người dùng từ session
$user_id = $_SESSION['user']['id'];
$phanquyen = $_SESSION['user']['phanquyen'];

// Kiểm tra quyền delete_shipping
if (!hasPermission($user_id, 'delete_shipping')) {
    echo json_encode(["status" => "error", "message" => "Bạn không có quyền xóa phương thức vận chuyển!"]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = isset($_POST["id"]) ? intval($_POST["id"]) : 0;

    if ($id <= 0) {
        echo json_encode(["status" => "error", "message" => "ID không hợp lệ!"]);
        exit();
    }

    // Kiểm tra tồn tại trước khi xóa
    $checkSql = "SELECT id FROM shipping_method WHERE id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $exists = $result->num_rows > 0;
    $checkStmt->close();

    if ($exists) {
        $sql = "DELETE FROM shipping_method WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            // Kiểm tra xem xóa có thành công không bằng cách kiểm tra số hàng bị ảnh hưởng
            if ($stmt->affected_rows > 0) {
                echo json_encode(["status" => "success", "message" => "Phương thức vận chuyển đã được xóa thành công!"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Xóa không thành công, có thể dữ liệu đã bị xóa trước đó."]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Lỗi SQL: " . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => "Phương thức vận chuyển không tồn tại!"]);
    }

    $conn->close();
}
?>