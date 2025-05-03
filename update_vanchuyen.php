<?php
session_start();
include 'config.php'; // Kết nối database

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
    echo "Bạn cần phải đăng nhập để thực hiện hành động này.";
    exit();
}

// Lấy thông tin người dùng từ session
$user_id = $_SESSION['user']['id'];
$phanquyen = $_SESSION['user']['phanquyen'];

// Kiểm tra quyền manage_shipping
if (!hasPermission($user_id, 'manage_shipping')) {
    echo "Bạn không có quyền quản lý phương thức vận chuyển!";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = intval($_POST["id"]);
    $method_name = $_POST["method_name"];
    $cost = $_POST["cost"];
    $description = $_POST["description"];

    if ($id > 0) {
        $sql = "UPDATE shipping_method SET method_name = ?, cost = ?, description = ?, update_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sdsi", $method_name, $cost, $description, $id);

        if ($stmt->execute()) {
            echo "success";
        } else {
            echo "Lỗi SQL: " . $stmt->error;
        }

        $stmt->close();
    } else {
        echo "ID không hợp lệ!";
    }
    $conn->close();
}
?>