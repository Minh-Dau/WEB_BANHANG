<?php
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
    echo json_encode(["status" => "error", "message" => "Bạn cần đăng nhập để thực hiện hành động này!"]);
    exit();
}

$user_id = $_SESSION['user']['id'];
if (!hasPermission($user_id, 'edit_category')) {
    echo json_encode(["status" => "error", "message" => "Bạn không có quyền sửa danh mục!"]);
    exit();
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $tendanhmuc = $_POST['tendanhmuc'];

    if (empty($tendanhmuc)) {
        echo json_encode(["status" => "error", "message" => "Tên danh mục không được để trống!"]);
        exit();
    }

    if (!is_numeric($id)) {
        echo json_encode(["status" => "error", "message" => "ID danh mục không hợp lệ!"]);
        exit();
    }

    $sql = "UPDATE danhmuc SET tendanhmuc = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $tendanhmuc, $id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Danh mục đã được cập nhật thành công!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Lỗi khi cập nhật danh mục: " . $conn->error]);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(["status" => "error", "message" => "Yêu cầu không hợp lệ!"]);
}
?>