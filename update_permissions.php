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
    echo json_encode(["status" => "error", "message" => "Bạn cần phải đăng nhập để thực hiện hành động này."]);
    exit();
}

// Lấy thông tin người dùng từ session
$user_id = $_SESSION['user']['id'];
$phanquyen = $_SESSION['user']['phanquyen'];

// Kiểm tra quyền permission
if (!hasPermission($user_id, 'permission')) {
    echo json_encode(["status" => "error", "message" => "Bạn không có quyền phân quyền!"]);
    exit();
}

// Kiểm tra nếu người dùng là admin hoặc nhanvien, và mục tiêu phải là nhanvien
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['user_id']) && isset($_POST['phanquyen'])) {
    $target_user_id = $_POST['user_id'];
    $new_phanquyen = $_POST['phanquyen'];

    // Lấy vai trò hiện tại của người dùng mục tiêu
    $sql_target = "SELECT phanquyen FROM frm_dangky WHERE id = ?";
    $stmt_target = $conn->prepare($sql_target);
    $stmt_target->bind_param("i", $target_user_id);
    $stmt_target->execute();
    $result_target = $stmt_target->get_result();
    $target_phanquyen = '';
    if ($result_target->num_rows > 0) {
        $row = $result_target->fetch_assoc();
        $target_phanquyen = $row['phanquyen'];
    }
    $stmt_target->close();

    // Chỉ cho phép admin hoặc nhanvien phân quyền cho nhanvien
    if (($phanquyen === 'admin' || $phanquyen === 'nhanvien') && $target_phanquyen === 'nhanvien') {
        $permissions = isset($_POST['permissions']) ? json_decode($_POST['permissions'], true) : [];

        // Kiểm tra giá trị hợp lệ cho enum
        $valid_roles = ['user', 'nhanvien', 'admin'];
        if (!in_array($new_phanquyen, $valid_roles)) {
            echo json_encode(["status" => "error", "message" => "Vai trò không hợp lệ"]);
            exit;
        }

        // Cập nhật vai trò chính vào frm_dangky
        $sql = "UPDATE frm_dangky SET phanquyen = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_phanquyen, $target_user_id);
        $stmt->execute();
        $stmt->close();

        // Nếu vai trò là nhanvien, cập nhật quyền chi tiết
        if ($new_phanquyen === 'nhanvien') {
            // Xóa các quyền cũ
            $sql_delete = "DELETE FROM employee_permissions WHERE user_id = ?";
            $stmt_delete = $conn->prepare($sql_delete);
            $stmt_delete->bind_param("i", $target_user_id);
            $stmt_delete->execute();
            $stmt_delete->close();

            // Thêm các quyền mới
            if (!empty($permissions) && is_array($permissions)) {
                $sql_insert = "INSERT INTO employee_permissions (user_id, permission) VALUES (?, ?)";
                $stmt_insert = $conn->prepare($sql_insert);
                foreach ($permissions as $permission) {
                    $stmt_insert->bind_param("is", $target_user_id, $permission);
                    $stmt_insert->execute();
                }
                $stmt_insert->close();
            }
        } else {
            // Nếu không phải nhanvien, xóa hết quyền chi tiết
            $sql_delete = "DELETE FROM employee_permissions WHERE user_id = ?";
            $stmt_delete = $conn->prepare($sql_delete);
            $stmt_delete->bind_param("i", $target_user_id);
            $stmt_delete->execute();
            $stmt_delete->close();
        }
        echo json_encode(["status" => "success", "message" => "Phân quyền đã được cập nhật"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Chỉ admin hoặc nhân viên có thể phân quyền cho nhân viên!"]);
    }
    $conn->close();
} else {
    echo json_encode(["status" => "error", "message" => "Yêu cầu không hợp lệ"]);
}
?>