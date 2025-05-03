<?php
include 'config.php';
header('Content-Type: application/json');
error_log(print_r($_POST, true));

session_start();  // Bắt đầu phiên làm việc

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
if (!isset($_SESSION['user_id']) || !isset($_SESSION['phanquyen'])) {
    echo json_encode(["status" => "error", "message" => "Bạn cần phải đăng nhập để thực hiện hành động này."]);
    exit();
}

// Lấy thông tin người dùng đang đăng nhập từ session
$currentUserId = $_SESSION['user_id']; 
$currentUserRole = $_SESSION['phanquyen'];

// Kiểm tra quyền delete_user
if (!hasPermission($currentUserId, 'delete_user')) {
    echo json_encode(["status" => "error", "message" => "Bạn không có quyền xóa người dùng!"]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['id'])) {
    $id = $_POST['id'];
    
    // Kiểm tra nếu admin cố gắng xóa chính mình
    if ($id == $currentUserId) {
        echo json_encode(["status" => "error", "message" => "Bạn không thể xóa chính mình."]);
        exit();
    }

    // Kiểm tra vai trò của người dùng bị xóa
    $sqlCheckRole = "SELECT phanquyen FROM frm_dangky WHERE id = ?";
    $stmtCheckRole = $conn->prepare($sqlCheckRole);
    $stmtCheckRole->bind_param("i", $id);
    $stmtCheckRole->execute();
    $resultRole = $stmtCheckRole->get_result();
    $targetUserRole = '';
    if ($resultRole->num_rows > 0) {
        $row = $resultRole->fetch_assoc();
        $targetUserRole = $row['phanquyen'];
    }
    $stmtCheckRole->close();

    // Nếu người dùng bị xóa là admin và người dùng hiện tại là nhân viên, không cho xóa
    if ($targetUserRole === 'admin' && $currentUserRole === 'nhanvien') {
        echo json_encode(["status" => "error", "message" => "Nhân viên không thể xóa người dùng admin."]);
        exit();
    }

    // Cấm xóa tài khoản có email là 21004125@st.vlute.edu.vn
    $sqlCheckEmail = "SELECT email FROM frm_dangky WHERE id = ?";
    $stmtCheckEmail = $conn->prepare($sqlCheckEmail);
    $stmtCheckEmail->bind_param("i", $id);
    $stmtCheckEmail->execute();
    $stmtCheckEmail->bind_result($email);
    $stmtCheckEmail->fetch();
    $stmtCheckEmail->close();

    // Nếu email là tranminhdau85@gmail.com, không cho phép xóa
    if ($email === 'tranminhdau85@gmail.com') {
        echo json_encode(["status" => "error", "message" => "Tài khoản không thể bị xóa."]);
        exit();
    }

    // Xử lý xóa tài khoản
    $sql = "DELETE FROM frm_dangky WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Lỗi prepare: " . $conn->error]);
        exit();
    }
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Người dùng đã được xóa!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Lỗi xóa: " . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
    exit();
}
?>