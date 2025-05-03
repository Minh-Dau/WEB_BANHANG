<?php
session_start();
include "config.php";

// Kiểm tra nếu người dùng đã đăng nhập
if (!isset($_SESSION['username'])) {
    echo "error: User not logged in";
    exit();
}

// Lấy user_id từ bảng frm_dangky
$username = $_SESSION['username'];
$sql = "SELECT id FROM frm_dangky WHERE username = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_id = $user['id'];
$stmt->close();

if (isset($_POST['id']) && isset($_POST['quantity'])) {
    $id = intval($_POST['id']);
    $quantity = max(1, intval($_POST['quantity']));

    if (isset($_SESSION['cart'][$id])) {
        $_SESSION['cart'][$id] = $quantity;

        // Cập nhật số lượng trong bảng cart
        $sql = "SELECT * FROM cart WHERE user_id = ? AND sanpham_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Cập nhật số lượng nếu sản phẩm đã có trong giỏ hàng
            $sql = "UPDATE cart SET soluong = ? WHERE user_id = ? AND sanpham_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iii", $quantity, $user_id, $id);
            $stmt->execute();
        } else {
            // Thêm sản phẩm mới vào giỏ hàng trong cơ sở dữ liệu
            $sql = "INSERT INTO cart (user_id, sanpham_id, soluong) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iii", $user_id, $id, $quantity);
            $stmt->execute();
        }
        $stmt->close();

        echo "success";
    } else {
        echo "error: Product not in cart";
    }
} else {
    echo "error: Invalid data";
}
?>