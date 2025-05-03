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
if (!isset($_SESSION['user']['id'])) {
    echo json_encode(["status" => "error", "message" => "Bạn cần đăng nhập để thực hiện hành động này!"]);
    exit();
}

$user_id = $_SESSION['user']['id'];

// Kiểm tra quyền add_product
if (!hasPermission($user_id, 'add_product')) {
    echo json_encode(["status" => "error", "message" => "Bạn không có quyền thêm sản phẩm!"]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tensanpham = $_POST["tensanpham"];
    $gia_nhap = (int)$_POST["gia_nhap"];
    $gia = (int)$_POST["gia"];
    $soluong = (int)$_POST["soluong"];
    $noidungsanpham = $_POST["noidungsanpham"];
    $trangthai = $_POST["trangthai"];
    $danhmuc_id = (int)$_POST["danhmuc_id"]; 

    // Kiểm tra dữ liệu đầu vào
    if (empty($tensanpham)) {
        echo json_encode(["status" => "error", "message" => "Tên sản phẩm không được để trống!"]);
        exit();
    }
    if ($gia_nhap < 0 || $gia < 0 || $soluong < 0) {
        echo json_encode(["status" => "error", "message" => "Giá nhập, giá bán hoặc số lượng không được âm!"]);
        exit();
    }
    if ($danhmuc_id <= 0) {
        echo json_encode(["status" => "error", "message" => "ID danh mục không hợp lệ!"]);
        exit();
    }

    $target_dir = "uploads/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    $target_file = $target_dir . basename($_FILES["img"]["name"]);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    $check = getimagesize($_FILES["img"]["tmp_name"]);
    if ($check === false) {
        echo json_encode(["status" => "error", "message" => "Tệp không phải là hình ảnh."]);
        exit();
    }
    if ($_FILES["img"]["size"] > 5000000) {
        echo json_encode(["status" => "error", "message" => "Tệp quá lớn."]);
        exit();
    }
    if (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
        echo json_encode(["status" => "error", "message" => "Chỉ chấp nhận file JPG, JPEG, PNG, GIF."]);
        exit();
    }
    if (move_uploaded_file($_FILES["img"]["tmp_name"], $target_file)) {
        $stmt = $conn->prepare("INSERT INTO sanpham (tensanpham, img, gia_nhap, gia, soluong, noidungsanpham, trangthai, danhmuc_id) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiiissi", $tensanpham, $target_file, $gia_nhap, $gia, $soluong, $noidungsanpham, $trangthai, $danhmuc_id);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Thêm sản phẩm thành công!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Lỗi: " . $stmt->error]);
        }

        $stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => "Lỗi khi tải lên hình ảnh."]);
    }

    $conn->close();
}
?>