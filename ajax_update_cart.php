<?php
session_start();
include "config.php";

// Kiểm tra dữ liệu đầu vào
if (!isset($_POST['product_id']) || !isset($_POST['quantity']) || !isset($_POST['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Dữ liệu không hợp lệ!']);
    exit();
}

$user_id = intval($_POST['user_id']);
$product_id = intval($_POST['product_id']);
$quantity = intval($_POST['quantity']);

if ($quantity <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Số lượng không hợp lệ!']);
    exit();
}

// Kiểm tra số lượng tồn kho
$stmt = $conn->prepare("SELECT soluong FROM sanpham WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Sản phẩm không tồn tại!']);
    $stmt->close();
    exit();
}
$row = $result->fetch_assoc();
$soluong_tonkho = $row['soluong'];
$stmt->close();

if ($quantity > $soluong_tonkho) {
    echo json_encode(['status' => 'error', 'message' => "Chỉ còn $soluong_tonkho sản phẩm trong kho!"]);
    exit();
}

// Cập nhật số lượng trong giỏ hàng
$stmt = $conn->prepare("UPDATE cart_item SET soluong = ? WHERE user_id = ? AND sanpham_id = ?");
$stmt->bind_param("iii", $quantity, $user_id, $product_id);
$stmt->execute();
$stmt->close();

echo json_encode(['status' => 'success']);
?>