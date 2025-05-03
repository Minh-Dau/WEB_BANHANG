<?php
header('Content-Type: application/json');
include 'config.php';
session_start();
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
if (!isset($_SESSION['user']['id'])) {
    $response['status'] = 'error';
    $response['message'] = 'Bạn cần đăng nhập để thực hiện hành động này!';
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user']['id'];
if (!hasPermission($user_id, 'add_category')) {
    $response['status'] = 'error';
    $response['message'] = 'Bạn không có quyền thêm danh mục!';
    echo json_encode($response);
    exit();
}
$tendanhmuc = isset($_POST['tendanhmuc']) ? trim($_POST['tendanhmuc']) : '';

$response = array();
if (empty($tendanhmuc)) {
    $response['status'] = 'error';
    $response['message'] = 'Tên danh mục không được để trống!';
    echo json_encode($response);
    exit();
}
$sql = "INSERT INTO danhmuc (tendanhmuc) VALUES (?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $tendanhmuc);
if ($stmt->execute()) {
    $response['status'] = 'success';
    $response['message'] = 'Thêm danh mục thành công!';
} else {
    $response['status'] = 'error';
    $response['message'] = 'Không thể thêm danh mục. Vui lòng thử lại!';
}
$stmt->close();
$conn->close();
echo json_encode($response);
?>