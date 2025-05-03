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

if (!hasPermission($user_id, 'delete_category')) {
    echo json_encode(["status" => "error", "message" => "Bạn không có quyền xóa danh mục!"]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id'])) {
    $id = (int)$_POST['id'];

    if ($id <= 0) {
        echo json_encode(["status" => "error", "message" => "ID danh mục không hợp lệ!"]);
        exit();
    }

    // Bắt đầu transaction để đảm bảo tính toàn vẹn dữ liệu
    $conn->begin_transaction();

    try {
        // Xóa các sản phẩm thuộc danh mục trước
        $delete_products = "DELETE FROM sanpham WHERE danhmuc_id = ?";
        $stmt1 = $conn->prepare($delete_products);
        $stmt1->bind_param("i", $id);
        if (!$stmt1->execute()) {
            throw new Exception("Lỗi xóa sản phẩm: " . $conn->error);
        }
        $stmt1->close();

        // Xóa danh mục
        $delete_category = "DELETE FROM danhmuc WHERE id = ?";
        $stmt2 = $conn->prepare($delete_category);
        $stmt2->bind_param("i", $id);
        if (!$stmt2->execute()) {
            throw new Exception("Lỗi xóa danh mục: " . $conn->error);
        }
        $stmt2->close();

        // Commit transaction nếu mọi thứ thành công
        $conn->commit();
        echo json_encode(["status" => "success", "message" => "Danh mục và tất cả sản phẩm liên quan đã được xóa!"]);
    } catch (Exception $e) {
        // Rollback transaction nếu có lỗi
        $conn->rollback();
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }

    $conn->close();
} else {
    echo json_encode(["status" => "error", "message" => "Yêu cầu không hợp lệ!"]);
}
?>