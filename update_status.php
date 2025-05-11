<?php
header('Content-Type: application/json');
include 'config.php';
session_start();

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
    }
    $stmt->close();

    if ($phanquyen === 'admin') {
        return true;
    }

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

    return false;
}

// Kiểm tra kết nối
if ($conn->connect_error) {
    error_log("Kết nối thất bại: " . $conn->connect_error);
    echo json_encode(['success' => false, 'error' => 'Lỗi kết nối cơ sở dữ liệu']);
    exit();
}

// Kiểm tra đăng nhập
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'error' => 'Bạn cần đăng nhập để thực hiện hành động này.']);
    $conn->close();
    exit();
}

// Kiểm tra quyền
$user_id = $_SESSION['user']['id'];
error_log("User ID from session: $user_id"); // Debug
if (!hasPermission($user_id, 'manage_order_status')) {
    error_log("Permission check failed for user_id: $user_id");
    echo json_encode(['success' => false, 'error' => 'Bạn không có quyền thay đổi trạng thái đơn hàng.']);
    $conn->close();
    exit();
}

// Xử lý yêu cầu POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $trangthai = isset($_POST['trangthai']) ? $_POST['trangthai'] : '';

    if ($id > 0 && !empty($trangthai)) {
        $sql = "UPDATE oder SET trangthai = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            error_log("Prepare failed: " . $conn->error);
            echo json_encode(['success' => false, 'error' => 'Lỗi chuẩn bị truy vấn: ' . $conn->error]);
            $conn->close();
            exit();
        }
        $stmt->bind_param("si", $trangthai, $id);
        $success = $stmt->execute();
        if ($success) {
            echo json_encode(['success' => true]);
        } else {
            error_log("Execute failed: " . $stmt->error);
            echo json_encode(['success' => false, 'error' => 'Không thể cập nhật trạng thái: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Dữ liệu không hợp lệ']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Yêu cầu không hợp lệ']);
}

$conn->close();
?>