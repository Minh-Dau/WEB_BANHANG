<?php
include 'config.php';

if (isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];

    // Lấy vai trò chính từ frm_dangky
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

    // Lấy quyền chi tiết từ employee_permissions nếu vai trò là nhanvien
    $permissions = [];
    if ($phanquyen === 'nhanvien') {
        $sql_permissions = "SELECT permission FROM employee_permissions WHERE user_id = ?";
        $stmt_permissions = $conn->prepare($sql_permissions);
        $stmt_permissions->bind_param("i", $user_id);
        $stmt_permissions->execute();
        $result_permissions = $stmt_permissions->get_result();
        while ($row = $result_permissions->fetch_assoc()) {
            $permissions[] = $row['permission'];
        }
        $stmt_permissions->close();
    }

    echo json_encode(["status" => "success", "phanquyen" => $phanquyen, "permissions" => $permissions]);
    $conn->close();
} else {
    echo json_encode(["status" => "error", "message" => "ID không hợp lệ"]);
}
?>