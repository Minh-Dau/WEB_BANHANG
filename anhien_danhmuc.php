<?php
include 'config.php';
session_start();

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
    } else {
        error_log("Không tìm thấy vai trò cho user_id: $user_id");
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
        if (!$has_perm) {
            error_log("Không có quyền $permission cho user_id: $user_id");
        }
        $stmt_perm->close();
        return $has_perm;
    }

    // Nếu là user, không có quyền
    return false;
}

// Kiểm tra người dùng đã đăng nhập
if (!isset($_SESSION['user']['id'])) {
    echo json_encode(["status" => "error", "message" => "Bạn cần đăng nhập để thực hiện hành động này!"]);
    exit();
}

$user_id = $_SESSION['user']['id'];
error_log("User ID: $user_id");

// Kiểm tra quyền toggle_category_visibility
if (!hasPermission($user_id, 'toggle_category_visibility')) {
    echo json_encode(["status" => "error", "message" => "Bạn không có quyền cập nhật trạng thái danh mục!"]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id']) && isset($_POST['trangthai'])) {
    $id = (int)$_POST['id'];
    $trangthai = trim($_POST['trangthai']);

    // Kiểm tra dữ liệu đầu vào
    if ($id <= 0) {
        echo json_encode(["status" => "error", "message" => "ID danh mục không hợp lệ!"]);
        exit();
    }
    if (empty($trangthai) || !in_array($trangthai, ['Hiển thị', 'Ẩn'])) {
        echo json_encode(["status" => "error", "message" => "Trạng thái không hợp lệ!"]);
        exit();
    }

    // Bắt đầu transaction để đảm bảo tính toàn vẹn dữ liệu
    $conn->begin_transaction();

    try {
        // Cập nhật trạng thái danh mục
        $sql = "UPDATE danhmuc SET trangthai = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $trangthai, $id);
        if (!$stmt->execute()) {
            throw new Exception("Lỗi cập nhật danh mục: " . $conn->error);
        }
        $stmt->close();

        // Cập nhật trạng thái các sản phẩm thuộc danh mục
        $sql_update_sanpham = "UPDATE sanpham SET trangthai = ? WHERE danhmuc_id = ?";
        $stmt_sanpham = $conn->prepare($sql_update_sanpham);
        $stmt_sanpham->bind_param("si", $trangthai, $id);
        if (!$stmt_sanpham->execute()) {
            throw new Exception("Lỗi cập nhật sản phẩm: " . $conn->error);
        }
        $stmt_sanpham->close();

        // Commit transaction nếu mọi thứ thành công
        $conn->commit();
        echo json_encode(["status" => "success", "message" => "Cập nhật trạng thái thành công!"]);
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