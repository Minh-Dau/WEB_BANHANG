<?php
session_start();
include 'config.php';

header('Content-Type: application/json');

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
    return false;
}
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    echo json_encode(["status" => "error", "message" => "Bạn cần phải đăng nhập để thực hiện hành động này."]);
    exit();
}
$user_id = $_SESSION['user']['id'];
$phanquyen = $_SESSION['user']['phanquyen'];
if (!hasPermission($user_id, 'delete_review')) {
    echo json_encode(["status" => "error", "message" => "Bạn không có quyền xóa đánh giá!"]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_id'])) {
    $review_id = intval($_POST['review_id']);
    $sql = "DELETE FROM danhgia WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $review_id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Đánh giá đã được xóa."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Không thể xóa đánh giá."]);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(["status" => "error", "message" => "Yêu cầu không hợp lệ."]);
}
?>