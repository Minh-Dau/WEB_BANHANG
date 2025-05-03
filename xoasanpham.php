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
    echo json_encode(["status" => "error", "message" => "Bạn cần đăng nhập để thực hiện hành động này."]);
    exit();
}

$user_id = $_SESSION['user']['id'];

if (!hasPermission($user_id, 'delete_product')) {
    echo json_encode(["status" => "error", "message" => "Bạn không có quyền xóa sản phẩm!"]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST["id"];

    // Xóa ảnh nếu tồn tại
    $sql_img = "SELECT img FROM sanpham WHERE id = ?";
    $stmt_img = $conn->prepare($sql_img);
    $stmt_img->bind_param("i", $id);
    $stmt_img->execute();
    $result_img = $stmt_img->get_result();
    if ($result_img->num_rows > 0) {
        $row = $result_img->fetch_assoc();
        $image_path = $row["img"];
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }
    $stmt_img->close();

    // Xóa sản phẩm
    $sql = "DELETE FROM sanpham WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Xóa thành công!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Lỗi: " . $stmt->error]);
    }
    $stmt->close();
    $conn->close();
}
?>
