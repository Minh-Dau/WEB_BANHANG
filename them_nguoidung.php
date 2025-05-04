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

// Lấy vai trò từ session (dùng để kiểm tra quyền cấp admin)
$logged_in_user_role = $_SESSION['phanquyen'] ?? '';

// Kiểm tra quyền add_user
if (!hasPermission($user_id, 'add_user')) {
    echo json_encode(["status" => "error", "message" => "Bạn không có quyền thêm người dùng!"]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $hoten      = isset($_POST['hoten']) ? trim($_POST['hoten']) : '';
    $username   = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email      = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password   = isset($_POST['password']) ? trim($_POST['password']) : '';
    $phanquyen  = isset($_POST['phanquyen']) ? trim($_POST['phanquyen']) : '';
    $sdt        = isset($_POST['sdt']) ? trim($_POST['sdt']) : null;
    $trangthai  = isset($_POST['trangthai']) ? trim($_POST['trangthai']) : 'hoạt động';

    $specific_address = trim($_POST['specific_address']);
    $province_name = trim($_POST['province_name']);
    $district_name = trim($_POST['district_name']);
    $ward_name = trim($_POST['ward_name']);

    $address_components = array_filter([$specific_address, $ward_name, $district_name, $province_name], function($value) {
        return !empty($value);
    });
    $diachi = implode(', ', $address_components);

    // Nếu nhân viên đang đăng nhập, không cho phép cấp quyền admin
    if ($logged_in_user_role === 'nhanvien' && $phanquyen === 'admin') {
        echo json_encode(["status" => "error", "message" => "Bạn không có quyền cấp tài khoản admin"]);
        exit;
    }

    // Kiểm tra dữ liệu nhập vào
    if (empty($hoten)) {
        echo json_encode(["status" => "error", "message" => "Họ tên không được để trống"]);
        exit;
    }
    if (strlen($hoten) > 50) {
        echo json_encode(["status" => "error", "message" => "Họ tên không được dài quá 50 ký tự"]);
        exit;
    }
    if (empty($username)) {
        echo json_encode(["status" => "error", "message" => "Tên người dùng không được để trống"]);
        exit;
    }
    if (empty($email)) {
        echo json_encode(["status" => "error", "message" => "Email không được để trống"]);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["status" => "error", "message" => "Email không hợp lệ"]);
        exit;
    }
    if (empty($password)) {
        echo json_encode(["status" => "error", "message" => "Mật khẩu không được để trống"]);
        exit;
    }
    if (empty($phanquyen) || !in_array($phanquyen, ['admin', 'nhanvien', 'user'])) {
        echo json_encode(["status" => "error", "message" => "Quyền không hợp lệ"]);
        exit;
    }
    if (empty($trangthai) || !in_array($trangthai, ['hoạt động', 'đã khóa'])) {
        echo json_encode(["status" => "error", "message" => "Trạng thái không hợp lệ"]);
        exit;
    }
    if (empty($diachi)) {
        echo json_encode(["status" => "error", "message" => "Địa chỉ không được để trống"]);
        exit;
    }
    if (strlen($diachi) > 500) {
        echo json_encode(["status" => "error", "message" => "Địa chỉ không được dài quá 500 ký tự"]);
        exit;
    }

    // Kiểm tra số điện thoại
    if (!empty($sdt)) {
        // Kiểm tra định dạng số điện thoại (10 chữ số, bắt đầu bằng 03, 05, 07, 08, hoặc 09)
        if (!preg_match("/^(03|05|07|08|09)[0-9]{8}$/", $sdt)) {
            echo json_encode(["status" => "error", "message" => "Số điện thoại không hợp lệ. Phải có 10 chữ số và bắt đầu bằng 03, 05, 07, 08, hoặc 09."]);
            exit;
        }
    }

    // Kiểm tra username trùng lặp
    $sql_check_username = "SELECT id FROM frm_dangky WHERE username = ?";
    $stmt_check_username = $conn->prepare($sql_check_username);
    $stmt_check_username->bind_param("s", $username);
    $stmt_check_username->execute();
    $result_check_username = $stmt_check_username->get_result();
    if ($result_check_username->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Tên người dùng đã tồn tại, vui lòng chọn tên khác"]);
        $stmt_check_username->close();
        exit;
    }
    $stmt_check_username->close();

    // Xử lý tải ảnh
    $anh = null;
    if (isset($_FILES['anh']) && $_FILES['anh']['error'] == 0) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        if (in_array($_FILES['anh']['type'], $allowedTypes) && $_FILES['anh']['size'] <= $maxSize) {
            $targetDir = "uploads/";
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            $targetFile = $targetDir . basename($_FILES['anh']['name']);
            if (move_uploaded_file($_FILES['anh']['tmp_name'], $targetFile)) {
                $anh = $targetFile;
            }
        } else {
            echo json_encode(["status" => "error", "message" => "File ảnh không hợp lệ (chỉ chấp nhận JPEG, PNG, GIF, tối đa 5MB)"]);
            exit;
        }
    }

    // Mã hóa mật khẩu
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Thêm người dùng vào cơ sở dữ liệu (bao gồm hoten)
    $sql = "INSERT INTO frm_dangky (username, hoten, email, password, phanquyen, sdt, diachi, anh, trangthai)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]);
        exit;
    }
    $stmt->bind_param("sssssssss", $username, $hoten, $email, $hashedPassword, $phanquyen, $sdt, $diachi, $anh, $trangthai);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Thêm người dùng thành công!"]);
    } else {
        if ($stmt->errno == 1062) {
            echo json_encode(["status" => "error", "message" => "Email đã tồn tại, vui lòng sử dụng email khác"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Lỗi thêm người dùng: " . $stmt->error]);
        }
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
}
?>