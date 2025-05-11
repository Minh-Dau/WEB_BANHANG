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

// Kiểm tra quyền edit_product
if (!hasPermission($user_id, 'edit_product')) {
    echo json_encode(["status" => "error", "message" => "Bạn không có quyền cập nhật sản phẩm!"]);
    exit();
}

header('Content-Type: application/json'); // Đảm bảo trả về JSON

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize inputs
    $id = isset($_POST["id"]) ? (int)$_POST["id"] : 0;
    $tensanpham = isset($_POST["tensanpham"]) ? trim($_POST["tensanpham"]) : '';
    $gia = isset($_POST["gia"]) ? (int)$_POST["gia"] : 0;
    $gia_nhap = isset($_POST["gia_nhap"]) ? (int)$_POST["gia_nhap"] : 0;
    $soluong = isset($_POST["soluong"]) ? (int)$_POST["soluong"] : 0;
    $noidungsanpham = isset($_POST["noidungsanpham"]) ? trim($_POST["noidungsanpham"]) : '';
    $danhmuc_id = isset($_POST["danhmuc_id"]) ? (int)$_POST["danhmuc_id"] : 0;
    $trangthai = isset($_POST["trangthai"]) ? trim($_POST["trangthai"]) : '';

    // Kiểm tra giá nhập và giá bán
    if ($gia_nhap >= $gia) {
        $response['message'] = 'Giá nhập không được lớn hơn hoặc bằng giá bán!';
        echo json_encode($response);
        exit;
    }
    // Basic validation
    if ($id <= 0 || empty($tensanpham) || $gia < 0 || $gia_nhap < 0 || $soluong < 0 || $danhmuc_id <= 0) {
        echo json_encode(["status" => "error", "message" => "Invalid input data"]);
        exit;
    }

    $conn->set_charset("utf8mb4");

    // Check category status
    $stmt = $conn->prepare("SELECT trangthai FROM danhmuc WHERE id = ?");
    if ($stmt === false) {
        echo json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]);
        exit;
    }
    $stmt->bind_param("i", $danhmuc_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Category not found"]);
        exit;
    }
    $category_status = $result->fetch_assoc()["trangthai"];
    $stmt->close();

    // Set product status based on category status
    if ($category_status == "Ẩn") {
        $trangthai = "Ẩn";
    } else {
        $trangthai = $trangthai;
    }

    // Handle image upload
    $img_path = null;
    if (isset($_FILES["new_img"]) && $_FILES["new_img"]["error"] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        $upload_dir = "uploads/";

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_type = $_FILES["new_img"]["type"];
        $file_size = $_FILES["new_img"]["size"];
        $file_tmp = $_FILES["new_img"]["tmp_name"];
        $file_name = time() . "_" . basename($_FILES["new_img"]["name"]);
        $file_path = $upload_dir . $file_name;

        if (!in_array($file_type, $allowed_types)) {
            echo json_encode(["status" => "error", "message" => "Error: Only JPEG, PNG, and GIF files are allowed."]);
            exit;
        }
        if ($file_size > $max_size) {
            echo json_encode(["status" => "error", "message" => "Error: File size exceeds 5MB limit."]);
            exit;
        }

        if (move_uploaded_file($file_tmp, $file_path)) {
            $img_path = $file_path;
        } else {
            echo json_encode(["status" => "error", "message" => "Error: Failed to upload the image."]);
            exit;
        }
    }

    // If no new image is uploaded, fetch the current image path from the database
    if ($img_path === null) {
        $stmt = $conn->prepare("SELECT img FROM sanpham WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $img_path = $result->fetch_assoc()["img"];
        } else {
            echo json_encode(["status" => "error", "message" => "Product not found"]);
            exit;
        }
        $stmt->close();
    }

    // Update product in the database
    $sql = "UPDATE sanpham 
            SET tensanpham=?, img=?, gia=?, gia_nhap=?, soluong=?, noidungsanpham=?, trangthai=?, danhmuc_id=?
            WHERE id=?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        echo json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]);
        exit;
    }

    $stmt->bind_param("ssiiissii", $tensanpham, $img_path, $gia, $gia_nhap, $soluong, $noidungsanpham, $trangthai, $danhmuc_id, $id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Cập nhật thành công!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Lỗi: " . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
}
?>