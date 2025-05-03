<?php
include "config.php";

// Đặt header Content-Type là application/json
header('Content-Type: application/json');

// Bật hiển thị lỗi để debug (chỉ dùng khi phát triển)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (isset($_POST['discount_code']) && isset($_POST['total'])) {
    $discount_code = trim($_POST['discount_code']);
    $total = floatval($_POST['total']);
    $current_time = date("Y-m-d H:i:s");

    $stmt = $conn->prepare("SELECT discount_type, discount_value, min_order_value, max_uses, used_count, expiry_date, is_active 
                            FROM discount_codes WHERE code = ? AND is_active = 1");
    if ($stmt === false) {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi chuẩn bị truy vấn: ' . $conn->error]);
        exit();
    }

    $stmt->bind_param("s", $discount_code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $discount = $result->fetch_assoc();
        
        if ($discount['expiry_date'] < $current_time) {
            echo json_encode(['status' => 'error', 'message' => 'Mã giảm giá đã hết hạn!']);
        } elseif ($discount['max_uses'] > 0 && $discount['used_count'] >= $discount['max_uses']) {
            echo json_encode(['status' => 'error', 'message' => 'Mã giảm giá đã hết lượt sử dụng!']);
        } elseif ($total < $discount['min_order_value']) {
            echo json_encode(['status' => 'error', 'message' => 'Đơn hàng chưa đạt giá trị tối thiểu để áp dụng mã giảm giá!']);
        } else {
            $discount_amount = ($discount['discount_type'] == 'percent') 
                ? $total * ($discount['discount_value'] / 100) 
                : $discount['discount_value'];
            if ($discount_amount > $total) $discount_amount = $total;

            echo json_encode([
                'status' => 'success',
                'discount_amount' => $discount_amount,
                'message' => 'Mã giảm giá đã được áp dụng!'
            ]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Mã giảm giá không hợp lệ!']);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Dữ liệu không hợp lệ!']);
}

$conn->close();
?>