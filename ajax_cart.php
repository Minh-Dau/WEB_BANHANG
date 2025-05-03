<?php
// Start output buffering to capture any unexpected output
ob_start();

// Start the session
session_start();

// Include the database configuration
include "config.php";

// Set the content type to JSON
header('Content-Type: application/json');

// Kiểm tra dữ liệu đầu vào
$missing_fields = [];
if (!isset($_POST['add_to_cart'])) $missing_fields[] = 'add_to_cart';
if (!isset($_POST['id'])) $missing_fields[] = 'id';
if (!isset($_POST['soluong'])) $missing_fields[] = 'soluong';

if (!empty($missing_fields)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Thiếu các trường: ' . implode(', ', $missing_fields)
    ]);
    ob_end_flush();
    exit();
}

if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng đăng nhập!']);
    ob_end_flush();
    exit();
}

$username = $_SESSION['username'];
$product_id = intval($_POST['id']);
$soluong = intval($_POST['soluong']);

if ($product_id <= 0 || $soluong <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID hoặc số lượng không hợp lệ!']);
    ob_end_flush();
    exit();
}

// Lấy user_id
$stmt = $conn->prepare("SELECT id FROM frm_dangky WHERE username = ?");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi cơ sở dữ liệu: Không thể chuẩn bị truy vấn!']);
    ob_end_flush();
    exit();
}
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy người dùng!']);
    $stmt->close();
    ob_end_flush();
    exit();
}
$user = $result->fetch_assoc();
$user_id = $user['id'];
$stmt->close();

// Kiểm tra tồn kho
$stmt = $conn->prepare("SELECT soluong, tensanpham FROM sanpham WHERE id = ?");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi cơ sở dữ liệu: Không thể chuẩn bị truy vấn!']);
    ob_end_flush();
    exit();
}
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Sản phẩm không tồn tại!']);
    $stmt->close();
    ob_end_flush();
    exit();
}
$row = $result->fetch_assoc();
$soluong_tonkho = $row['soluong'];
$tensanpham = $row['tensanpham'];
$stmt->close();

if ($soluong > $soluong_tonkho) {
    echo json_encode(['status' => 'error', 'message' => "Sản phẩm " . htmlspecialchars($tensanpham) . " chỉ còn $soluong_tonkho sản phẩm trong kho!"]);
    ob_end_flush();
    exit();
}

// Kiểm tra và thêm/cập nhật giỏ hàng
$stmt = $conn->prepare("SELECT soluong FROM cart_item WHERE user_id = ? AND sanpham_id = ?");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi cơ sở dữ liệu: Không thể chuẩn bị truy vấn!']);
    ob_end_flush();
    exit();
}
$stmt->bind_param("ii", $user_id, $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $new_soluong = $row['soluong'] + $soluong;
    if ($new_soluong > $soluong_tonkho) {
        echo json_encode(['status' => 'error', 'message' => "Sản phẩm " . htmlspecialchars($tensanpham) . ": Tổng số lượng vượt quá tồn kho ($soluong_tonkho)!"]);
        $stmt->close();
        ob_end_flush();
        exit();
    }
    $stmt_update = $conn->prepare("UPDATE cart_item SET soluong = ? WHERE user_id = ? AND sanpham_id = ?");
    if (!$stmt_update) {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi cơ sở dữ liệu: Không thể chuẩn bị truy vấn!']);
        $stmt->close();
        ob_end_flush();
        exit();
    }
    $stmt_update->bind_param("iii", $new_soluong, $user_id, $product_id);
    $stmt_update->execute();
    $stmt_update->close();
} else {
    $stmt_insert = $conn->prepare("INSERT INTO cart_item (user_id, sanpham_id, soluong) VALUES (?, ?, ?)");
    if (!$stmt_insert) {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi cơ sở dữ liệu: Không thể chuẩn bị truy vấn!']);
        $stmt->close();
        ob_end_flush();
        exit();
    }
    $stmt_insert->bind_param("iii", $user_id, $product_id, $soluong);
    $stmt_insert->execute();
    $stmt_insert->close();
}
$stmt->close();

// Tính số lượng mặt hàng khác nhau trong giỏ hàng
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM cart_item WHERE user_id = ?");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi cơ sở dữ liệu: Không thể chuẩn bị truy vấn!']);
    ob_end_flush();
    exit();
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$total_items = $row['total'] ?? 0;
$stmt->close();

// Trả về phản hồi thành công với message và total_items
echo json_encode([
    'status' => 'success',
    'message' => 'Đã thêm sản phẩm ' . htmlspecialchars($tensanpham) . ' vào giỏ hàng thành công!',
    'total_items' => $total_items
]);
ob_end_flush();
exit();
?>