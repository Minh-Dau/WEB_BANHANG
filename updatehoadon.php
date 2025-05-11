<?php
header('Content-Type: application/json');
include 'config.php';
session_start();

// Hàm kiểm tra quyền (tương tự như trong các file khác)
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

// Kiểm tra đăng nhập
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập để thực hiện hành động này.']);
    $conn->close();
    exit();
}

// Kiểm tra quyền print_invoice
$user_id = $_SESSION['user']['id'];
if (!hasPermission($user_id, 'print_invoice')) {
    echo json_encode(['success' => false, 'message' => 'Bạn không có quyền in hóa đơn.']);
    $conn->close();
    exit();
}

// Xử lý yêu cầu POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = isset($input['id']) ? intval($input['id']) : 0;
    $invoice_status = isset($input['invoice_status']) ? $input['invoice_status'] : '';

    if ($id > 0 && !empty($invoice_status)) {
        $sql = "UPDATE oder SET invoice_status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            echo json_encode(['success' => false, 'message' => 'Lỗi chuẩn bị truy vấn: ' . $conn->error]);
            $conn->close();
            exit();
        }
        $stmt->bind_param("si", $invoice_status, $id);
        $success = $stmt->execute();
        if ($success) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không thể cập nhật trạng thái hóa đơn: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ']);
}

$conn->close();
?>