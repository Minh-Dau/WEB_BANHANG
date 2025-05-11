<?php
ob_start();
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');
include "config.php";
include 'header.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['username'])) {
    echo "<script>window.location.href='dangnhap.php';</script>";
    exit();
}
$username = $_SESSION['username'];

// Lấy thông tin tỉnh/thành từ cơ sở dữ liệu provinces
$provinces = [];
$sql_provinces = "SELECT name, distance FROM provinces";
$result_provinces = $conn->query($sql_provinces);
while ($row = $result_provinces->fetch_assoc()) {
    $provinces[$row['name']] = $row['distance'];
}

// Lấy phương thức vận chuyển từ cơ sở dữ liệu
$shipping_methods = [];
$sql_shipping = "SELECT id, method_name, cost, description FROM shipping_method";
$result_shipping = $conn->query($sql_shipping);
while ($row = $result_shipping->fetch_assoc()) {
    $shipping_methods[$row['id']] = [
        'method_name' => $row['method_name'],
        'cost' => $row['cost'],
        'description' => $row['description']
    ];
}

// Lấy thông tin người dùng từ frm_dangky
$sql = "SELECT id, hoten, diachi, sdt FROM frm_dangky WHERE username = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $user_id = $user['id'];
    $hoten = $user['hoten'] ?? '';
    $diachi = $user['diachi'] ?? '';
    $sdt = $user['sdt'] ?? '';

    // Phân tích địa chỉ để lấy tỉnh/thành, quận/huyện, phường/xã
    $address_parts = explode(', ', $diachi);
    $specific_address = $address_parts[0] ?? '';
    $ward = $address_parts[1] ?? '';
    $district = $address_parts[2] ?? '';
    $province = $address_parts[3] ?? '';
} else {
    echo "<script>alert('Không tìm thấy thông tin người dùng. Vui lòng đăng nhập lại.'); window.location.href='dangnhap.php';</script>";
    exit();
}
$stmt->close();

// Tính phí vận chuyển dựa trên tỉnh/thành phố từ địa chỉ người dùng
$initial_shipping_cost = 0;
if (!empty($province)) {
    $province_key = preg_replace('/^(Tỉnh|Thành phố)\s/', '', $province);
    $default_shipping_id = 1; // Phương thức vận chuyển mặc định
    $shipping_cost_per_km = $shipping_methods[$default_shipping_id]['cost'] ?? 100;
    $initial_shipping_cost = isset($provinces[$province_key]) ? $provinces[$province_key] * $shipping_cost_per_km : 0;
}

// Lấy giỏ hàng từ cart_item
$cart_items = [];
$sql_cart = "SELECT ci.sanpham_id, ci.soluong, s.tensanpham, s.gia, s.img 
             FROM cart_item ci 
             JOIN sanpham s ON ci.sanpham_id = s.id 
             WHERE ci.user_id = ?";
$stmt_cart = $conn->prepare($sql_cart);
$stmt_cart->bind_param("i", $user_id);
$stmt_cart->execute();
$result_cart = $stmt_cart->get_result();

while ($row = $result_cart->fetch_assoc()) {
    $cart_items[$row['sanpham_id']] = [
        'tensanpham' => $row['tensanpham'],
        'soluong' => $row['soluong'],
        'gia' => $row['gia'],
        'img' => $row['img']
    ];
}
$stmt_cart->close();

// Fetch payment status for the specific order (if success)
$payment_status = 'Chưa thanh toán';
if (isset($_GET['success']) && $_GET['success'] == 1 && isset($_GET['order_id'])) {
    $order_id = intval($_GET['order_id']);
    $sql_order = "SELECT payment_status FROM oder WHERE id = ? AND user_id = ?";
    $stmt_order = $conn->prepare($sql_order);
    $stmt_order->bind_param("ii", $order_id, $user_id);
    $stmt_order->execute();
    $result_order = $stmt_order->get_result();
    if ($result_order->num_rows > 0) {
        $order = $result_order->fetch_assoc();
        $payment_status = $order['payment_status'];
    }
    $stmt_order->close();
}

$discount_value = 0;
$discount_code = '';

// Khai báo biến lỗi
$error1 = $error2 = $error3 = $error5 = $error6 = $error7 = $error8 = $error9 = $error10 = "";

// Xử lý hành động
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case "remove":
            if (isset($_GET['id'])) {
                $stmt = $conn->prepare("DELETE FROM cart_item WHERE user_id = ? AND sanpham_id = ?");
                $stmt->bind_param("ii", $user_id, $_GET['id']);
                $stmt->execute();
                $stmt->close();
            }
            echo "<script>window.location.href='giohang.php';</script>";
            exit();
            break;

        case "clear":
            $stmt = $conn->prepare("DELETE FROM cart_item WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
            echo "<script>window.location.href='giohang.php';</script>";
            exit();
            break;

        case "submit":
            if (isset($_POST['oder_click'])) {
                $error = false;
                $hoten = isset($_POST['hoten']) ? trim($_POST['hoten']) : '';
                $sdt = isset($_POST['sdt']) ? trim($_POST['sdt']) : '';
                $address = isset($_POST['address']) ? trim($_POST['address']) : '';
                $province_name = isset($_POST['province_name']) ? trim($_POST['province_name']) : '';
                $district_name = isset($_POST['district_name']) ? trim($_POST['district_name']) : '';
                $ward_name = isset($_POST['ward_name']) ? trim($_POST['ward_name']) : '';
                $shipping_method_id = isset($_POST['shipping_method']) ? intval($_POST['shipping_method']) : 1;

                $address_components = array_filter([$address, $ward_name, $district_name, $province_name], function($value) {
                    return !empty($value);
                });
                $diachi = mysqli_real_escape_string($conn, implode(', ', $address_components));

                $province_key = preg_replace('/^Tỉnh\s/', '', $province_name);
                $shipping_cost = isset($provinces[$province_key]) ? $provinces[$province_key] * ($shipping_methods[$shipping_method_id]['cost'] ?? 100) : 0;
                $shipping_method = $shipping_methods[$shipping_method_id]['method_name'] ?? 'Giao hàng tiêu chuẩn';
                $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
                $trangthai = in_array($payment_method, ['Thanh toán MoMo', 'Thanh toán VNPAY']) ? 'Pending Payment' : 'Chờ xác nhận';
                $payment_status_db = 'Chưa thanh toán';

                $discount_code = isset($_POST['discount_code']) ? trim($_POST['discount_code']) : '';
                $discount_value = 0;
                if (!empty($discount_code)) {
                    $stmt_discount = $conn->prepare("SELECT discount_type, discount_value, min_order_value, max_uses, used_count, expiry_date, is_active 
                                                    FROM discount_codes WHERE code = ? AND is_active = 1");
                    $stmt_discount->bind_param("s", $discount_code);
                    $stmt_discount->execute();
                    $result_discount = $stmt_discount->get_result();
                    if ($result_discount->num_rows > 0) {
                        $discount = $result_discount->fetch_assoc();
                        $current_time = date("Y-m-d H:i:s");
                        if ($discount['expiry_date'] < $current_time) {
                            echo "<script>alert('Mã giảm giá đã hết hạn!'); window.location.href = 'giohang.php';</script>";
                            exit();
                        }
                        if ($discount['max_uses'] > 0 && $discount['used_count'] >= $discount['max_uses']) {
                            echo "<script>alert('Mã giảm giá đã hết lượt sử dụng!'); window.location.href = 'giohang.php';</script>";
                            exit();
                        }
                        $discount_value = $discount['discount_value'];
                    } else {
                        echo "<script>alert('Mã giảm giá không hợp lệ!'); window.location.href = 'giohang.php';</script>";
                        exit();
                    }
                    $stmt_discount->close();
                }

                if (empty($hoten)) {
                    $error1 = "Vui lòng nhập họ tên";
                    $error = true;
                }
                if (empty($address)) {
                    $error2 = "Vui lòng nhập địa chỉ cụ thể";
                    $error = true;
                }
                if (empty($sdt)) {
                    $error3 = "Vui lòng nhập số điện thoại";
                    $error = true;
                }
                if (empty($_POST['selected_products'])) {
                    $error5 = "Vui lòng chọn ít nhất một sản phẩm để thanh toán";
                    $error = true;
                }
                if (empty($shipping_method)) {
                    $error6 = "Vui lòng chọn phương thức vận chuyển";
                    $error = true;
                }
                if (empty($payment_method)) {
                    $error7 = "Vui lòng chọn phương thức thanh toán";
                    $error = true;
                }
                if (empty($province_name)) {
                    $error8 = "Vui lòng chọn tỉnh/thành phố";
                    $error = true;
                }
                if (empty($district_name)) {
                    $error9 = "Vui lòng chọn quận/huyện";
                    $error = true;
                }
                if (empty($ward_name)) {
                    $error10 = "Vui lòng chọn phường/xã";
                    $error = true;
                }

                if (!$error && !empty($cart_items)) {
                    $selected_products = $_POST['selected_products'];
                    $total = 0;
                    $order_items = [];

                    // Kiểm tra tồn kho
                    foreach ($selected_products as $sanpham_id) {
                        if (isset($cart_items[$sanpham_id])) {
                            $item = $cart_items[$sanpham_id];
                            $soluong = $item['soluong'];
                            $gia = $item['gia'];
                            $tensanpham = $item['tensanpham'];

                            $stmt = $conn->prepare("SELECT soluong FROM sanpham WHERE id = ?");
                            $stmt->bind_param("i", $sanpham_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $row = $result->fetch_assoc();
                            $soluong_tonkho = $row['soluong'];
                            $stmt->close();

                            if ($soluong > $soluong_tonkho) {
                                echo "<script>alert('Sản phẩm $tensanpham chỉ còn $soluong_tonkho sản phẩm trong kho.'); window.location.href = './giohang.php';</script>";
                                exit();
                            }

                            $subtotal = $gia * $soluong;
                            $total += $subtotal;
                            $order_items[] = [
                                'sanpham_id' => $sanpham_id,
                                'soluong' => $soluong,
                                'gia' => $gia,
                                'subtotal' => $subtotal
                            ];
                        }
                    }

                    $discount_amount = 0;
                    if ($discount_value > 0) {
                        if ($total < $discount['min_order_value']) {
                            echo "<script>alert('Đơn hàng chưa đạt giá trị tối thiểu để áp dụng mã giảm giá!'); window.location.href = 'giohang.php';</script>";
                            exit();
                        }
                        if ($discount['discount_type'] == 'percent') {
                            $discount_amount = $total * ($discount_value / 100);
                        } else {
                            $discount_amount = $discount_value;
                        }
                        if ($discount_amount > $total) $discount_amount = $total;
                    }

                    $total_payment = $total + $shipping_cost - $discount_amount;

                    $note = isset($_POST['note']) ? mysqli_real_escape_string($conn, $_POST['note']) : '';
                    $ngaydathang = date("Y-m-d H:i:s");

                    // Bắt đầu transaction
                    $conn->begin_transaction();
                    try {
                        // Cập nhật thông tin người dùng
                        $stmt_update = $conn->prepare("UPDATE frm_dangky SET hoten = ?, diachi = ?, sdt = ? WHERE id = ?");
                        $stmt_update->bind_param("sssi", $hoten, $diachi, $sdt, $user_id);
                        $stmt_update->execute();
                        $stmt_update->close();

                        // Tạo đơn hàng tạm thời
                        $stmt = $conn->prepare("INSERT INTO oder (user_id, note, total, ngaydathang, payment_method, shipping_cost, discount_code, discount_amount, trangthai, payment_status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("isdssdssds", $user_id, $note, $total_payment, $ngaydathang, $payment_method, $shipping_cost, $discount_code, $discount_amount, $trangthai, $payment_status_db);
                        $stmt->execute();
                        $order_id = $conn->insert_id;
                        $stmt->close();

                        // Lưu chi tiết đơn hàng vào session thay vì chèn ngay vào oder_detail
                        $_SESSION['pending_order'] = [
                            'order_id' => $order_id,
                            'items' => $order_items,
                            'discount_code' => $discount_code
                        ];

                        // Commit transaction
                        $conn->commit();

                        if ($payment_method == 'Thanh toán MoMo') {
                            $endpoint = "https://test-payment.momo.vn/v2/gateway/api/create";
                            $partnerCode = "MOMO";
                            $accessKey = "F8BBA842ECF85";
                            $secretKey = "K951B6PE1waDMi640xX08PD3vg6EkVlz";
                            $orderInfo = "pay with MoMo";
                            $amount = (int)$total_payment;
                            $orderId = $order_id . "_" . time();
                            $redirectUrl = "http://localhost/BAOCAO/giohang.php?action=callback&order_id=$order_id";
                            $notifyUrl = "http://localhost/BAOCAO/giohang.php?action=notify";
                            $requestId = time() . "";
                            $requestType = "payWithMethod";
                            $extraData = "";
                            $partnerName = "MoMo Payment";
                            $storeId = "Test Store";
                            $lang = "vi";
                            $autoCapture = true;

                            $rawHash = "accessKey=$accessKey&amount=$amount&extraData=$extraData&ipnUrl=$notifyUrl&orderId=$orderId&orderInfo=$orderInfo&partnerCode=$partnerCode&redirectUrl=$redirectUrl&requestId=$requestId&requestType=$requestType";
                            $signature = hash_hmac("sha256", $rawHash, $secretKey);

                            $data = [
                                "partnerCode" => $partnerCode,
                                "partnerName" => $partnerName,
                                "storeId" => $storeId,
                                "requestId" => $requestId,
                                "amount" => $amount,
                                "orderId" => $orderId,
                                "orderInfo" => $orderInfo,
                                "redirectUrl" => $redirectUrl,
                                "ipnUrl" => $notifyUrl,
                                "lang" => $lang,
                                "autoCapture" => $autoCapture,
                                "extraData" => $extraData,
                                "requestType" => $requestType,
                                "signature" => $signature
                            ];

                            $ch = curl_init($endpoint);
                            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                            $result = curl_exec($ch);
                            curl_close($ch);

                            $response = json_decode($result, true);
                            if (isset($response['payUrl'])) {
                                echo "<script>window.location.href='" . $response['payUrl'] . "';</script>";
                                exit();
                            } else {
                                // Xóa đơn hàng tạm thời nếu không tạo được payUrl
                                $stmt_delete_order = $conn->prepare("DELETE FROM oder WHERE id = ?");
                                $stmt_delete_order->bind_param("i", $order_id);
                                $stmt_delete_order->execute();
                                $stmt_delete_order->close();

                                unset($_SESSION['pending_order']);

                                echo "<script>alert('Lỗi MoMo: " . ($response['message'] ?? 'Không có phản hồi') . "'); window.location.href = 'giohang.php';</script>";
                                exit();
                            }
                        } elseif ($payment_method == 'Thanh toán VNPAY') {
                            // Cấu hình VNPAY
                            $vnp_TmnCode = "RDIRE0L2"; // Mã website tại VNPAY
                            $vnp_HashSecret = "5Y79USNE8QBDIU3YE70SEBMSZXNAOUOS"; // Chuỗi bí mật
                            $vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html"; // URL thanh toán sandbox
                            $vnp_Returnurl = "http://localhost/BAOCAO/giohang.php?action=callback&order_id=$order_id";
                            $vnp_TxnRef = $order_id . "_" . time(); // Mã đơn hàng
                            $vnp_OrderInfo = "Thanh toán đơn hàng #$order_id";
                            $vnp_OrderType = "billpayment";
                            $vnp_Amount = (int)($total_payment * 100); // Số tiền (VND * 100)
                            $vnp_Locale = "vn";
                            $vnp_IpAddr = $_SERVER['REMOTE_ADDR'];
                            $vnp_CreateDate = date('YmdHis');
                            $vnp_ExpireDate = date('YmdHis', strtotime('+15 minutes'));

                            $inputData = [
                                "vnp_Version" => "2.1.0",
                                "vnp_TmnCode" => $vnp_TmnCode,
                                "vnp_Amount" => $vnp_Amount,
                                "vnp_Command" => "pay",
                                "vnp_CreateDate" => $vnp_CreateDate,
                                "vnp_CurrCode" => "VND",
                                "vnp_IpAddr" => $vnp_IpAddr,
                                "vnp_Locale" => $vnp_Locale,
                                "vnp_OrderInfo" => $vnp_OrderInfo,
                                "vnp_OrderType" => $vnp_OrderType,
                                "vnp_ReturnUrl" => $vnp_Returnurl,
                                "vnp_TxnRef" => $vnp_TxnRef,
                                "vnp_ExpireDate" => $vnp_ExpireDate
                            ];

                            ksort($inputData);
                            $query = http_build_query($inputData);
                            $hashData = "";
                            foreach ($inputData as $key => $value) {
                                if ($hashData == "") {
                                    $hashData = $key . "=" . urlencode($value);
                                } else {
                                    $hashData .= "&" . $key . "=" . urlencode($value);
                                }
                            }
                            $vnpSecureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);
                            $vnp_Url .= "?" . $query . "&vnp_SecureHash=" . $vnpSecureHash;

                            echo "<script>window.location.href='$vnp_Url';</script>";
                            exit();
                        } else {
                            // Thanh toán khi nhận hàng
                            $conn->begin_transaction();
                            try {
                                // Cập nhật trạng thái đơn hàng
                                $stmt_update_trangthai = $conn->prepare("UPDATE oder SET trangthai = ? WHERE id = ?");
                                $stmt_update_trangthai->bind_param("si", $trangthai, $order_id);
                                $stmt_update_trangthai->execute();
                                $stmt_update_trangthai->close();

                                if (!empty($discount_code)) {
                                    $stmt_update_discount = $conn->prepare("UPDATE discount_codes SET used_count = used_count + 1 WHERE code = ?");
                                    $stmt_update_discount->bind_param("s", $discount_code);
                                    $stmt_update_discount->execute();
                                    $stmt_update_discount->close();
                                }

                                // Chèn chi tiết đơn hàng
                                $stmt_detail = $conn->prepare("INSERT INTO oder_detail (oder_id, sanpham_id, soluong, gia, subtotal) VALUES (?, ?, ?, ?, ?)");
                                $stmt_update_stock = $conn->prepare("UPDATE sanpham SET soluong = soluong - ? WHERE id = ?");
                                $stmt_delete_cart = $conn->prepare("DELETE FROM cart_item WHERE user_id = ? AND sanpham_id = ?");

                                foreach ($order_items as $item) {
                                    $stmt_detail->bind_param("iiidd", $order_id, $item['sanpham_id'], $item['soluong'], $item['gia'], $item['subtotal']);
                                    $stmt_detail->execute();

                                    $stmt_update_stock->bind_param("ii", $item['soluong'], $item['sanpham_id']);
                                    $stmt_update_stock->execute();

                                    $stmt_delete_cart->bind_param("ii", $user_id, $item['sanpham_id']);
                                    $stmt_delete_cart->execute();
                                }
                                $stmt_detail->close();
                                $stmt_update_stock->close();
                                $stmt_delete_cart->close();

                                $conn->commit();

                                unset($_SESSION['pending_order']);

                                echo "<script>window.location.href='giaidoan.php';</script>";
                                exit();
                            } catch (Exception $e) {
                                $conn->rollback();
                                echo "<script>alert('Lỗi khi tạo đơn hàng: " . $e->getMessage() . "'); window.location.href = 'giohang.php';</script>";
                                exit();
                            }
                        }
                    } catch (Exception $e) {
                        $conn->rollback();
                        echo "<script>alert('Lỗi khi tạo đơn hàng: " . $e->getMessage() . "'); window.location.href = 'giohang.php';</script>";
                        exit();
                    }
                }
            }
            break;

        case "callback":
            if (isset($_GET['order_id'])) {
                $order_id = intval($_GET['order_id']);
                $conn->begin_transaction();
                try {
                    // Lấy thông tin đơn hàng tạm thời từ session
                    if (!isset($_SESSION['pending_order']) || $_SESSION['pending_order']['order_id'] != $order_id) {
                        throw new Exception("Không tìm thấy thông tin đơn hàng tạm thời.");
                    }

                    $order_items = $_SESSION['pending_order']['items'];
                    $discount_code = $_SESSION['pending_order']['discount_code'];

                    // Kiểm tra trạng thái thanh toán
                    $payment_success = false;
                    if (isset($_GET['resultCode']) && $_GET['resultCode'] == "0") { // MoMo
                        $payment_success = true;
                    } elseif (isset($_GET['vnp_ResponseCode']) && $_GET['vnp_ResponseCode'] == "00") { // VNPAY
                        // Xác thực chữ ký VNPAY
                        $vnp_HashSecret = "5Y79USNE8QBDIU3YE70SEBMSZXNAOUOS";
                        $vnp_SecureHash = $_GET['vnp_SecureHash'];
                        $inputData = array();
                        foreach ($_GET as $key => $value) {
                            if (substr($key, 0, 4) == "vnp_") {
                                $inputData[$key] = $value;
                            }
                        }
                        unset($inputData['vnp_SecureHash']);
                        ksort($inputData);
                        $hashData = "";
                        foreach ($inputData as $key => $value) {
                            if ($hashData == "") {
                                $hashData = $key . "=" . urlencode($value);
                            } else {
                                $hashData .= "&" . $key . "=" . urlencode($value);
                            }
                        }
                        $secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);
                        if ($secureHash == $vnp_SecureHash) {
                            $payment_success = true;
                        } else {
                            throw new Exception("Chữ ký VNPAY không hợp lệ.");
                        }
                    }

                    if ($payment_success) {
                        // Cập nhật trạng thái đơn hàng
                        $trangthai = 'Chờ xác nhận';
                        $stmt = $conn->prepare("UPDATE oder SET payment_status = 'Đã thanh toán', trangthai = ? WHERE id = ?");
                        $stmt->bind_param("si", $trangthai, $order_id);
                        $stmt->execute();
                        $stmt->close();

                        if (!empty($discount_code)) {
                            $stmt_update_discount = $conn->prepare("UPDATE discount_codes SET used_count = used_count + 1 WHERE code = ?");
                            $stmt_update_discount->bind_param("s", $discount_code);
                            $stmt_update_discount->execute();
                            $stmt_update_discount->close();
                        }

                        // Chèn chi tiết đơn hàng
                        $stmt_detail = $conn->prepare("INSERT INTO oder_detail (oder_id, sanpham_id, soluong, gia, subtotal) VALUES (?, ?, ?, ?, ?)");
                        $stmt_update_stock = $conn->prepare("UPDATE sanpham SET soluong = soluong - ? WHERE id = ?");
                        $stmt_delete_cart = $conn->prepare("DELETE FROM cart_item WHERE user_id = ? AND sanpham_id = ?");

                        foreach ($order_items as $item) {
                            $stmt_detail->bind_param("iiidd", $order_id, $item['sanpham_id'], $item['soluong'], $item['gia'], $item['subtotal']);
                            $stmt_detail->execute();

                            $stmt_update_stock->bind_param("ii", $item['soluong'], $item['sanpham_id']);
                            $stmt_update_stock->execute();

                            $stmt_delete_cart->bind_param("ii", $user_id, $item['sanpham_id']);
                            $stmt_delete_cart->execute();
                        }
                        $stmt_detail->close();
                        $stmt_update_stock->close();
                        $stmt_delete_cart->close();

                        $conn->commit();

                        unset($_SESSION['pending_order']);

                        echo "<script>window.location.href='giohang.php?success=1&order_id=$order_id';</script>";
                        exit();
                    } else {
                        // Xóa đơn hàng tạm thời
                        $stmt_delete_order = $conn->prepare("DELETE FROM oder WHERE id = ? AND trangthai = 'Pending Payment'");
                        $stmt_delete_order->bind_param("i", $order_id);
                        $stmt_delete_order->execute();
                        $stmt_delete_order->close();

                        unset($_SESSION['pending_order']);

                        echo "<script>alert('Thanh toán không thành công hoặc đã bị hủy. Giỏ hàng của bạn vẫn được giữ nguyên.'); window.location.href = 'giohang.php';</script>";
                        exit();
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    echo "<script>alert('Lỗi khi hoàn tất đơn hàng: " . $e->getMessage() . "'); window.location.href = 'giohang.php';</script>";
                    exit();
                }
            } else {
                echo "<script>alert('Không tìm thấy đơn hàng.'); window.location.href = 'giohang.php';</script>";
                exit();
            }
            break;

        case "notify":
            $data = json_decode(file_get_contents('php://input'), true);
            if ($data['resultCode'] == 0) {
                $order_id = explode("_", $data['orderId'])[0];
                $conn->begin_transaction();
                try {
                    // Lấy thông tin đơn hàng tạm thời từ session
                    if (!isset($_SESSION['pending_order']) || $_SESSION['pending_order']['order_id'] != $order_id) {
                        throw new Exception("Không tìm thấy thông tin đơn hàng tạm thời.");
                    }

                    $order_items = $_SESSION['pending_order']['items'];
                    $discount_code = $_SESSION['pending_order']['discount_code'];

                    // Cập nhật trạng thái đơn hàng
                    $trangthai = 'Chờ xác nhận';
                    $stmt = $conn->prepare("UPDATE oder SET payment_status = 'Đã thanh toán', trangthai = ? WHERE id = ?");
                    $stmt->bind_param("si", $trangthai, $order_id);
                    $stmt->execute();
                    $stmt->close();

                    if (!empty($discount_code)) {
                        $stmt_update_discount = $conn->prepare("UPDATE discount_codes SET used_count = used_count + 1 WHERE code = ?");
                        $stmt_update_discount->bind_param("s", $discount_code);
                        $stmt_update_discount->execute();
                        $stmt_update_discount->close();
                    }

                    // Chèn chi tiết đơn hàng
                    $stmt_detail = $conn->prepare("INSERT INTO oder_detail (oder_id, sanpham_id, soluong, gia, subtotal) VALUES (?, ?, ?, ?, ?)");
                    $stmt_update_stock = $conn->prepare("UPDATE sanpham SET soluong = soluong - ? WHERE id = ?");
                    $stmt_delete_cart = $conn->prepare("DELETE FROM cart_item WHERE user_id = ? AND sanpham_id = ?");

                    foreach ($order_items as $item) {
                        $stmt_detail->bind_param("iiidd", $order_id, $item['sanpham_id'], $item['soluong'], $item['gia'], $item['subtotal']);
                        $stmt_detail->execute();

                        $stmt_update_stock->bind_param("ii", $item['soluong'], $item['sanpham_id']);
                        $stmt_update_stock->execute();

                        $stmt_delete_cart->bind_param("ii", $user_id, $item['sanpham_id']);
                        $stmt_delete_cart->execute();
                    }
                    $stmt_detail->close();
                    $stmt_update_stock->close();
                    $stmt_delete_cart->close();

                    $conn->commit();

                    unset($_SESSION['pending_order']);
                } catch (Exception $e) {
                    $conn->rollback();
                    // Ghi log lỗi nếu cần
                }
            }
            exit();
    }
}

$buy_now_product_id = isset($_GET['buy_now']) ? intval($_GET['buy_now']) : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DUMEMAY|VIETNAM</title>
    <link rel="stylesheet" href="css_giohang.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="header.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<form action="giohang.php?action=submit" method="POST" id="cartForm" autocomplete="off">
    <?php if (!empty($cart_items)) { ?>
        <div class="shipping-info">
            <h1><i class="bi bi-geo-alt-fill"></i> Địa Chỉ Nhận Hàng</h1>
            <div class="static-info">
                <p id="address-display"><?= htmlspecialchars($hoten) . ' (' . htmlspecialchars($sdt) . ') ' . htmlspecialchars($diachi) ?></p>
                <div class="address-actions">
                    <button type="button" class="change-btn">Thay Đổi</button>
                </div>
            </div>
            <p style="color: red;"><?php echo $error1; ?></p>
            <p style="color: red;"><?php echo $error2; ?></p>
            <p style="color: red;"><?php echo $error3; ?></p>
            <p style="color: red;"><?php echo $error8; ?></p>
            <p style="color: red;"><?php echo $error9; ?></p>
            <p style="color: red;"><?php echo $error10; ?></p>
        </div>
    <?php } ?>

    <div id="edit-address-modal" class="modal">
        <div class="modal-content">
            <span class="close-btn">×</span>
            <h2>Cập nhật địa chỉ</h2>
            <div class="txtb">
                <label>Họ và tên: <span style="color: red;">(*)</span></label>
                <input type="text" name="hoten" value="<?= htmlspecialchars($hoten) ?>" placeholder="Nhập họ và tên">
                <p style="color: red;"><?php echo $error1; ?></p>
            </div>
            <div class="txtb">
                <label>Số điện thoại: <span style="color: red;">(*)</span></label>
                <input type="number" name="sdt" value="<?= htmlspecialchars($sdt) ?>" placeholder="Nhập số điện thoại">
                <p style="color: red;"><?php echo $error3; ?></p>
            </div>
            <div class="txtb">
                <label>Tỉnh/Thành phố: <span style="color: red;">(*)</span></label>
                <select id="province" name="province">
                    <option value="">Chọn tỉnh/thành phố</option>
                </select>
                <input type="hidden" id="province_name" name="province_name" value="<?= htmlspecialchars($province); ?>">
                <p style="color: red;"><?php echo $error8; ?></p>
            </div>

            <div class="txtb">
                <label>Quận/Huyện: <span style="color: red;">(*)</span></label>
                <select id="district" name="district">
                    <option value="">Chọn quận/huyện</option>
                </select>
                <input type="hidden" id="district_name" name="district_name" value="<?= htmlspecialchars($district); ?>">
                <p style="color: red;"><?php echo $error9; ?></p>
            </div>

            <div class="txtb">
                <label>Phường/Xã: <span style="color: red;">(*)</span></label>
                <select id="ward" name="ward">
                    <option value="">Chọn phường/xã</option>
                </select>
                <input type="hidden" id="ward_name" name="ward_name" value="<?= htmlspecialchars($ward); ?>">
                <p style="color: red;"><?php echo $error10; ?></p>
            </div>
            <div class="txtb">
                <label>Địa chỉ chi tiết: <span style="color: red;">(*)</span></label>
                <input type="text" name="address" value="<?= htmlspecialchars($specific_address); ?>" placeholder="Nhập địa chỉ chi tiết">
                <p style="color: red;"><?php echo $error2; ?></p>
            </div>
            <div class="modal-actions">
                <button type="button" class="default-btn" onclick="window.location.href='giohang.php'">Trở Lại</button>
                <button type="button" class="save-btn">Thay Đổi</button>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['success']) && $_GET['success'] == 1) { ?>
        <div class="cart_success">
            <div class="img_success">
                <img src="IMG/thankyou.jpg" alt="">
            </div>
            <div class="btn_tieptuc">
                <a href="trangchinh.php">Tiếp tục mua hàng</a>
                <a href="giaidoan.php">Đơn hàng của bạn</a>
            </div>
        </div>
    <?php } elseif (empty($cart_items)) { ?>
        <div class="cart_error">
            <div class="image_error">
                <img src="IMG/cart_empty.jpg" alt="">
            </div>
            <div class="btn_dentrangsp" style="margin-top: 20px; margin-bottom: 20px;">
                <a href="shop.php">Đến trang sản phẩm</a>
            </div>
        </div>
    <?php } else { ?>
        <div class="cart-container">
            <div class="table_full">
                <main class="table">
                    <section class="table__header">
                        <h1><i class="bi bi-cart-fill"></i> GIỎ HÀNG CỦA BẠN</h1>
                    </section>
                    <section class="table__body">
                        <table>
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="select_all" onchange="toggleSelectAll()"></th>
                                    <th>Sản phẩm</th>
                                    <th>Số lượng</th>
                                    <th>Giá</th>
                                    <th>Thành tiền</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $dem = 1;
                                $total = 0;
                                foreach ($cart_items as $sanpham_id => $item) {
                                    $tensanpham = $item['tensanpham'];
                                    $subtotal = $item['gia'] * $item['soluong'];
                                    $total += $subtotal;
                                    $checked = ($buy_now_product_id && $sanpham_id == $buy_now_product_id) ? 'checked' : '';
                                    ?>
                                    <tr>
                                        <td class="center">
                                            <input type="checkbox" name="selected_products[]" value="<?= $sanpham_id ?>" class="itemCheckbox" <?= $checked ?>>
                                        </td>
                                        <td class="center product-info">
                                            <img src="<?= htmlspecialchars($item['img']) ?>" alt="">
                                            <p><?= htmlspecialchars($tensanpham) ?></p>
                                        </td>
                                        <td class="center">
                                            <div class="quantity-control">
                                                <button type="button" class="quantity-btn decrease" onclick="updateQuantity('<?= $sanpham_id ?>', -1)">-</button>
                                                <input type="text" 
                                                    class="quantity-input" 
                                                    id="so_<?= $sanpham_id ?>" 
                                                    name="soluong[<?= $sanpham_id ?>]" 
                                                    value="<?= $item['soluong'] ?>" 
                                                    oninput="validateInput(this)">
                                                <button type="button" class="quantity-btn increase" onclick="updateQuantity('<?= $sanpham_id ?>', 1)">+</button>
                                            </div>
                                        </td>
                                        <td class="center">
                                            <p><?php echo number_format($item['gia'], 0, ',', '.'); ?> VNĐ</p>
                                            <input type="hidden" id="price_<?= $sanpham_id ?>" value="<?= $item['gia'] ?>">
                                        </td>
                                        <td class="center">
                                            <strong id="subtotal_<?= $sanpham_id ?>"><?php echo number_format($subtotal, 0, ',', '.'); ?> VNĐ</strong>
                                        </td>
                                        <td><a href="giohang.php?action=remove&id=<?= $sanpham_id ?>" class="remove"><i class="bi bi-trash"></i></a></td>
                                    </tr>
                                    <tr>
                                        <td colspan="6"><hr></td>
                                    </tr>
                                    <?php
                                    $dem++;
                                }
                                ?>
                                <tr class="tr_tongtien">
                                    <td colspan="5">
                                        <b><i class="bi bi-cash-coin"></i> Tổng tiền: </b>
                                        <strong id="total_productss">0 VNĐ</strong>
                                    </td> 
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="btn_capnhat">
                            <a href="giohang.php?action=clear" class="submit_css" onclick="return confirm('Bạn có chắc chắn muốn xóa tất cả sản phẩm trong giỏ hàng?')">Xóa tất cả</a>
                        </div>
                    </section>
                </main>
            </div>

            <div class="checkout-form">
                <h2>Lời nhắn đến người bán</h2>
                <div class="txtb">
                    <label>Ghi chú:</label>
                    <textarea name="note"><?= isset($_POST['note']) ? $_POST['note'] : '' ?></textarea>
                </div>
                
                <h2>Mã giảm giá</h2>
                <div class="txtb discount-section">
                    <label>Nhập mã giảm giá:</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="discount_code" name="discount_code" placeholder="Nhập mã giảm giá" value="<?= isset($_POST['discount_code']) ? htmlspecialchars($_POST['discount_code']) : '' ?>">
                        <button type="button" id="apply_discount" class="submit_css">Áp dụng</button>
                    </div>
                    <p id="discount_message" style="color: green; margin-top: 5px;"></p>
                    <input type="hidden" id="discount_value" name="discount_value" value="0">
                </div>

                <h2>Phương thức vận chuyển</h2>
                <div class="shipping-methods">
                    <?php foreach ($shipping_methods as $id => $method): ?>
                        <button type="button" class="shipping-option <?= $id == 1 ? 'active' : '' ?>" data-method="<?= $id ?>">
                            <div class="shipping-content">
                                <div class="method-header">
                                    <span><strong><?= htmlspecialchars($method['method_name']) ?></strong></span>
                                    <span>(<?= $method['cost'] ?> VND/km)</span>
                                </div>
                                <div class="method-description">
                                    <p><?= htmlspecialchars($method['description']) ?></p>
                                </div>
                            </div>
                        </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="shipping_method" name="shipping_method" value="1">
                <input type="hidden" id="shipping_cost" name="shipping_cost" value="<?= $initial_shipping_cost ?>">
                <p style="color: red;"><?php echo $error6; ?></p>

                <h2>Phương thức thanh toán</h2>
                <div class="payment-methods">
                    <button type="button" class="payment-option active" data-method="Thanh toán khi nhận hàng">
                        <div class="payment-content">
                            <div class="method-header">
                                <span><strong>Thanh toán khi nhận hàng</strong></span>
                            </div>
                            <div class="method-description">
                                <p>Thanh toán bằng tiền mặt khi nhận hàng.</p>
                            </div>
                        </div>
                    </button>
                    <button type="button" class="payment-option" data-method="Thanh toán MoMo">
                        <div class="payment-content">
                            <div class="method-header">
                                <span><strong>Thanh toán MoMo</strong></span>
                            </div>
                            <div class="method-description">
                                <p>Thanh toán qua ví điện tử MoMo.</p>
                            </div>
                        </div>
                    </button>
                    <button type="button" class="payment-option" data-method="Thanh toán VNPAY">
                        <div class="payment-content">
                            <div class="method-header">
                                <span><strong>Thanh toán VNPAY</strong></span>
                            </div>
                            <div class="method-description">
                                <p>Thanh toán qua ví điện tử VNPAY.</p>
                            </div>
                        </div>
                    </button>
                </div>
                <input type="hidden" id="payment_method" name="payment_method" value="Thanh toán khi nhận hàng">
                <p style="color: red;"><?php echo $error7; ?></p>

                <h2>Chi tiết thanh toán</h2>
                <div class="payment-details">
                    <div class="payment-row">
                        <span class="label">Tổng tiền hàng:</span>
                        <span class="value" id="total_products">0 VNĐ</span>
                    </div>
                    <div class="payment-row">
                        <span class="label">Phí vận chuyển:</span>
                        <span class="value" id="total_shipping"><?= number_format($initial_shipping_cost, 0, ',', '.') ?> VNĐ</span>
                    </div>
                    <div class="payment-row">
                        <span class="label">Mã khuyến mãi giảm:</span>
                        <span class="value" id="discount_amount">0 VNĐ</span>
                    </div>
                    <div class="payment-row total">
                        <span class="label">Tổng thanh toán:</span>
                        <span class="value" id="total_payment"><?= number_format($initial_shipping_cost, 0, ',', '.') ?> VNĐ</span>
                    </div>
                    <div class="payment-row total">
                        <span class="label">Trạng thái thanh toán:</span>
                        <span class="value" id="payment_status" style="color: <?php echo $payment_status == 'Đã thanh toán' ? 'green' : 'red'; ?>;">
                            <?php echo $payment_status; ?>
                        </span>
                    </div>
                </div>
                <div class="checkout-button">
                    <input type="submit" value="Đặt Hàng" name="oder_click" class="submit_css">
                </div>
            </div>
        </div>
    <?php } ?>
</form>

<?php include 'footer.php'; include 'chat.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log("DOM loaded and script is running");

    // Only attach form submit validation if there are cart items
    <?php if (!empty($cart_items)) { ?>
        const cartForm = document.getElementById('cartForm');
        if (cartForm) {
            cartForm.addEventListener('submit', function(event) {
                var checkedCheckboxes = document.querySelectorAll('input[name="selected_products[]"]:checked');
                if (checkedCheckboxes.length === 0) {
                    event.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Chưa chọn sản phẩm',
                        text: 'Vui lòng chọn ít nhất một sản phẩm để thanh toán',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                const hoten = document.querySelector('input[name="hoten"]')?.value.trim() || '';
                const sdt = document.querySelector('input[name="sdt"]')?.value.trim() || '';
                const address = document.querySelector('input[name="address"]')?.value.trim() || '';
                const provinceName = document.getElementById('province_name')?.value.trim() || '';
                const districtName = document.getElementById('district_name')?.value.trim() || '';
                const wardName = document.getElementById('ward_name')?.value.trim() || '';

                if (!hoten || !sdt || !address || !provinceName || !districtName || !wardName) {
                    event.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Thiếu thông tin',
                        text: 'Vui lòng nhập đầy đủ thông tin địa chỉ nhận hàng trước khi đặt hàng!',
                        confirmButtonText: 'OK'
                    });
                    return;
                }
            });
        }
    <?php } ?>

    // Load provinces only when the modal is opened
    const changeBtn = document.querySelector('.change-btn');
    let provincesLoaded = false;

    if (changeBtn) {
        changeBtn.addEventListener('click', function() {
            const modal = document.getElementById('edit-address-modal');
            if (modal) {
                modal.style.display = "block";
                centerModal();
                disableScroll();

                // Load provinces if not already loaded
                if (!provincesLoaded) {
                    fetch("https://provinces.open-api.vn/api/p/")
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! Status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log("Province options loaded:", data);
                            let provinceSelect = document.getElementById("province");
                            if (!provinceSelect) {
                                console.error("Province select element not found!");
                                return;
                            }

                            provinceSelect.innerHTML = '<option value="">Chọn tỉnh/thành phố</option>';
                            data.forEach(province => {
                                let option = document.createElement("option");
                                option.value = province.code;
                                option.textContent = province.name;
                                if (province.name === "<?= htmlspecialchars($province) ?>") {
                                    option.selected = true;
                                }
                                provinceSelect.appendChild(option);
                            });

                            provincesLoaded = true;

                            if (provinceSelect.value) {
                                console.log("Initial province selected, loading districts for provinceCode:", provinceSelect.value);
                                loadDistricts(provinceSelect.value);
                                updateShippingCostFromDB();
                                calculateTotalWithDiscount();
                            }
                        })
                        .catch(error => {
                            console.error("Error loading provinces:", error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Lỗi',
                                text: 'Không thể tải danh sách tỉnh/thành phố. Vui lòng thử lại!',
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#ee4d2d'
                            });
                        });
                }
            }
        });
    }

    // Handle province change
    const provinceSelect = document.getElementById("province");
    if (provinceSelect) {
        provinceSelect.addEventListener("change", function() {
            let provinceCode = this.value;
            let provinceName = this.options[this.selectedIndex].text;
            console.log("Province changed, provinceCode:", provinceCode, "provinceName:", provinceName);
            
            document.getElementById("province_name").value = provinceName;

            let districtSelect = document.getElementById("district");
            let wardSelect = document.getElementById("ward");
            if (districtSelect) {
                districtSelect.innerHTML = '<option value="">Chọn quận/huyện</option>';
                document.getElementById("district_name").value = "";
            }
            if (wardSelect) {
                wardSelect.innerHTML = '<option value="">Chọn phường/xã</option>';
                document.getElementById("ward_name").value = "";
            }

            if (provinceCode) {
                loadDistricts(provinceCode);
            }
            updateShippingCostFromDB();
            calculateTotalWithDiscount();
        });
    }

    // Handle district change
    document.getElementById("district").addEventListener("change", function() {
        let districtCode = this.value;
        let districtName = this.options[this.selectedIndex].text;
        console.log("District changed, districtCode:", districtCode, "districtName:", districtName);
        document.getElementById("district_name").value = districtName;
        loadWards(districtCode);

        let wardSelect = document.getElementById("ward");
        if (wardSelect) {
            wardSelect.value = "";
            document.getElementById("ward_name").value = "";
        }
    });

    // Handle ward change
    document.getElementById("ward").addEventListener("change", function() {
        let wardName = this.options[this.selectedIndex].text;
        console.log("Ward changed, wardName:", wardName);
        document.getElementById("ward_name").value = wardName;
    });

    function loadDistricts(provinceCode) {
        if (!provinceCode) {
            console.warn("No provinceCode provided, cannot load districts.");
            return;
        }

        console.log("Fetching districts for provinceCode:", provinceCode);
        fetch(`https://provinces.open-api.vn/api/p/${provinceCode}?depth=2`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log("Districts data:", data);
                let districtSelect = document.getElementById("district");
                if (!districtSelect) {
                    console.error("District select element not found!");
                    return;
                }

                districtSelect.innerHTML = '<option value="">Chọn quận/huyện</option>';
                if (data.districts && data.districts.length > 0) {
                    data.districts.forEach(district => {
                        let option = document.createElement("option");
                        option.value = district.code;
                        option.textContent = district.name;
                        if (district.name === "<?= htmlspecialchars($district) ?>") {
                            option.selected = true;
                        }
                        districtSelect.appendChild(option);
                    });

                    if (districtSelect.value) {
                        console.log("District selected after load, loading wards for districtCode:", districtSelect.value);
                        loadWards(districtSelect.value);
                    }
                } else {
                    console.warn("No districts found for provinceCode:", provinceCode);
                    districtSelect.disabled = true;
                    Swal.fire({
                        icon: 'warning',
                        title: 'Cảnh báo',
                        text: 'Không tìm thấy quận/huyện cho tỉnh này!',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#ee4d2d'
                    });
                }
            })
            .catch(error => {
                console.error("Error loading districts:", error);
                let districtSelect = document.getElementById("district");
                if (districtSelect) {
                    districtSelect.disabled = true;
                }
                Swal.fire({
                    icon: 'error',
                    title: 'Lỗi',
                    text: 'Không thể tải danh sách quận/huyện. Vui lòng thử lại!',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#ee4d2d'
                });
            });
    }

    function loadWards(districtCode) {
        if (!districtCode) {
            console.warn("No districtCode provided, cannot load wards.");
            return;
        }

        console.log("Fetching wards for districtCode:", districtCode);
        fetch(`https://provinces.open-api.vn/api/d/${districtCode}?depth=2`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log("Wards data:", data);
                let wardSelect = document.getElementById("ward");
                if (!wardSelect) {
                    console.error("Ward select element not found!");
                    return;
                }

                wardSelect.innerHTML = '<option value="">Chọn phường/xã</option>';
                if (data.wards && data.wards.length > 0) {
                    data.wards.forEach(ward => {
                        let option = document.createElement("option");
                        option.value = ward.code;
                        option.textContent = ward.name;
                        if (ward.name === "<?= htmlspecialchars($ward) ?>") {
                            option.selected = true;
                        }
                        wardSelect.appendChild(option);
                    });

                    if (wardSelect.value) {
                        let wardName = wardSelect.options[wardSelect.selectedIndex].text;
                        document.getElementById("ward_name").value = wardName;
                        console.log("Ward selected after load, wardName:", wardName);
                    }
                } else {
                    console.warn("No wards found for districtCode:", districtCode);
                    wardSelect.disabled = true;
                    Swal.fire({
                        icon: 'warning',
                        title: 'Cảnh báo',
                        text: 'Không tìm thấy phường/xã cho quận/huyện này!',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#ee4d2d'
                    });
                }
            })
            .catch(error => {
                console.error("Error loading wards:", error);
                let wardSelect = document.getElementById("ward");
                if (wardSelect) {
                    wardSelect.disabled = true;
                }
                Swal.fire({
                    icon: 'error',
                    title: 'Lỗi',
                    text: 'Không thể tải danh sách phường/xã. Vui lòng thử lại!',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#ee4d2d'
                });
            });
    }

    window.updateQuantity = function(productId, change) {
        var input = document.getElementById('so_' + productId);
        if (!input) return;

        var currentValue = parseInt(input.value) || 1;
        var newValue = currentValue + change;
        if (newValue < 1) newValue = 1;

        input.value = newValue;

        var price = parseInt(document.getElementById('price_' + productId).value);
        var subtotal = price * newValue;
        document.getElementById('subtotal_' + productId).innerText = subtotal.toLocaleString('vi-VN') + ' VNĐ';

        $.ajax({
            url: 'ajax_update_cart.php',
            type: 'POST',
            data: { product_id: productId, quantity: newValue, user_id: '<?= $user_id ?>' },
            success: function(response) {
                var result = JSON.parse(response);
                if (result.status === 'success') {
                    calculateTotalWithDiscount();
                } else {
                    Swal.fire({ icon: 'error', title: 'Lỗi', text: 'Có lỗi xảy ra khi cập nhật số lượng!', confirmButtonText: 'OK', confirmButtonColor: '#ee4d2d' });
                    input.value = currentValue;
                    var oldSubtotal = price * currentValue;
                    document.getElementById('subtotal_' + productId).innerText = oldSubtotal.toLocaleString('vi-VN') + ' VNĐ';
                    calculateTotalWithDiscount();
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error: ", status, error);
                Swal.fire({ icon: 'error', title: 'Lỗi', text: 'Không thể kết nối đến server!', confirmButtonText: 'OK', confirmButtonColor: '#ee4d2d' });
                input.value = currentValue;
                var oldSubtotal = price * currentValue;
                document.getElementById('subtotal_' + productId).innerText = oldSubtotal.toLocaleString('vi-VN') + ' VNĐ';
                calculateTotalWithDiscount();
            }
        });
    }

    window.validateInput = function(input) {
        var value = input.value.trim();
        var numericRegex = /^[0-9]+$/;
        if (!numericRegex.test(value) || value <= 0) {
            input.value = 1;
        }
        var productId = input.id.replace('so_', '');
        updateQuantity(productId, 0);
    }

    function getTotalProducts() {
        var checkedCheckboxes = document.querySelectorAll('input[name="selected_products[]"]:checked');
        var total = 0;
        checkedCheckboxes.forEach(function(checkbox) {
            var productId = checkbox.value;
            var quantity = parseInt(document.getElementById('so_' + productId).value) || 0;
            var price = parseInt(document.getElementById('price_' + productId).value) || 0;
            total += quantity * price;
        });
        return total;
    }

    function updateShippingCostFromDB() {
        let provinceSelect = document.getElementById("province");
        let provinceName = provinceSelect.options[provinceSelect.selectedIndex]?.text || document.getElementById("province_name").value;

        if (!provinceName) {
            document.getElementById('shipping_cost').value = 0;
            document.getElementById('total_shipping').innerText = '0 VNĐ';
            calculateTotalWithDiscount();
            return;
        }

        let cleanProvinceName = provinceName.replace(/^(Tỉnh|Thành phố)\s/, '');
        let shippingMethodId = document.getElementById("shipping_method").value;
        let provinces = <?= json_encode($provinces) ?>;
        let distance = provinces[cleanProvinceName] || 0;

        if (distance === 0) {
            console.warn("Không tìm thấy khoảng cách cho tỉnh: " + cleanProvinceName);
            document.getElementById('shipping_cost').value = 0;
            document.getElementById('total_shipping').innerText = '0 VNĐ';
            calculateTotalWithDiscount();
            return;
        }

        let shippingMethods = <?= json_encode($shipping_methods) ?>;
        let shippingCostPerKm = shippingMethods[shippingMethodId]?.cost || 100;
        let shippingCost = distance * shippingCostPerKm;

        document.getElementById('shipping_cost').value = shippingCost;
        document.getElementById('total_shipping').innerText = shippingCost.toLocaleString('vi-VN') + ' VNĐ';

        console.log("Updated shipping cost for " + cleanProvinceName + ": " + shippingCost + " VNĐ");
        calculateTotalWithDiscount();
    }

    function calculateTotalWithDiscount() {
        var total = getTotalProducts();
        var shippingCost = parseFloat(document.getElementById('shipping_cost').value) || 0;
        var discountAmount = parseFloat(document.getElementById('discount_value').value) || 0;
        var totalPayment = total + shippingCost - discountAmount;

        document.getElementById('total_productss').innerText = total.toLocaleString('vi-VN') + ' VNĐ';
        document.getElementById('total_products').innerText = total.toLocaleString('vi-VN') + ' VNĐ';
        document.getElementById('total_shipping').innerText = shippingCost.toLocaleString('vi-VN') + ' VNĐ';
        document.getElementById('discount_amount').innerText = (discountAmount > 0 ? '-' : '') + discountAmount.toLocaleString('vi-VN') + ' VNĐ';
        document.getElementById('total_payment').innerText = totalPayment.toLocaleString('vi-VN') + ' VNĐ';
        console.log("Total: " + total + ", Shipping: " + shippingCost + ", Discount: " + discountAmount + ", Total Payment: " + totalPayment);
    }

    window.calculateTotal = function() {
        calculateTotalWithDiscount();
    }

    // Hàm đồng bộ checkbox "chọn tất cả" và các checkbox sản phẩm
window.toggleSelectAll = function() {
    const selectAllCheckbox = document.getElementById('select_all');
    const productCheckboxes = document.querySelectorAll('input[name="selected_products[]"]');
    
    // Đồng bộ từ "chọn tất cả" sang các checkbox sản phẩm
    productCheckboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    
    // Cập nhật tổng tiền
    calculateTotalWithDiscount();
    };

    // Hàm kiểm tra và cập nhật trạng thái checkbox "chọn tất cả" dựa trên các checkbox sản phẩm
    window.updateSelectAll = function() {
        const selectAllCheckbox = document.getElementById('select_all');
        const productCheckboxes = document.querySelectorAll('input[name="selected_products[]"]');
        const allChecked = Array.from(productCheckboxes).every(checkbox => checkbox.checked);
        
        selectAllCheckbox.checked = allChecked;
        
        // Cập nhật tổng tiền
        calculateTotalWithDiscount();
    };

    // Gắn sự kiện cho checkbox "chọn tất cả"
    const selectAllCheckbox = document.getElementById('select_all');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', toggleSelectAll);
    }

    // Gắn sự kiện cho các checkbox sản phẩm
    const productCheckboxes = document.querySelectorAll('input[name="selected_products[]"]');
    if (productCheckboxes.length > 0) {
        productCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectAll);
        });
    }

    const shippingOptions = document.querySelectorAll('.shipping-option');
    if (shippingOptions.length > 0) {
        shippingOptions.forEach(button => {
            button.addEventListener('click', function() {
                document.querySelectorAll('.shipping-option').forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('shipping_method').value = this.getAttribute('data-method');
                updateShippingCostFromDB();
            });
        });
    }

    const paymentOptions = document.querySelectorAll('.payment-option');
    if (paymentOptions.length > 0) {
        paymentOptions.forEach(button => {
            button.addEventListener('click', function() {
                document.querySelectorAll('.payment-option').forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('payment_method').value = this.getAttribute('data-method');
            });
        });
    }

    const applyDiscountButton = document.getElementById('apply_discount');
    if (applyDiscountButton) {
        applyDiscountButton.addEventListener('click', function() {
            var discountCode = document.getElementById('discount_code').value.trim();
            if (!discountCode) {
                Swal.fire({ icon: 'warning', title: 'Lỗi', text: 'Vui lòng nhập mã giảm giá!', confirmButtonText: 'OK', confirmButtonColor: '#ee4d2d' });
                return;
            }

            $.ajax({
                url: 'apply_discount.php',
                type: 'POST',
                data: { discount_code: discountCode, total: getTotalProducts() },
                success: function(response) {
                    if (response.status === 'success') {
                        document.getElementById('discount_value').value = response.discount_amount;
                        document.getElementById('discount_message').innerText = response.message;
                        document.getElementById('discount_message').style.color = 'green';
                        calculateTotalWithDiscount();
                    } else {
                        document.getElementById('discount_value').value = 0;
                        document.getElementById('discount_amount').innerText = '0 VNĐ';
                        document.getElementById('discount_message').innerText = response.message;
                        document.getElementById('discount_message').style.color = 'red';
                        calculateTotalWithDiscount();
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({ icon: 'error', title: 'Lỗi', text: 'Không thể kết nối đến server!', confirmButtonText: 'OK', confirmButtonColor: '#ee4d2d' });
                }
            });
        });
    }

    const modal = document.getElementById('edit-address-modal');
    const span = document.querySelector('.close-btn');
    const saveBtn = document.querySelector('.save-btn');
    const defaultBtn = document.querySelector('.default-btn');

    if (span) {
        span.addEventListener('click', function() {
            if (modal) {
                modal.style.display = "none";
                enableScroll();
            }
        });
    }

    window.addEventListener('click', function(event) {
        if (event.target == modal) {
            if (modal) {
                modal.style.display = "none";
                enableScroll();
            }
        }
    });

    if (saveBtn) {
        saveBtn.addEventListener('click', function() {
            const hoten = document.querySelector('input[name="hoten"]').value;
            const sdt = document.querySelector('input[name="sdt"]').value;
            const address = document.querySelector('input[name="address"]').value;
            const provinceName = document.getElementById('province_name').value;
            const districtName = document.getElementById('district_name').value;
            const wardName = document.getElementById('ward_name').value;

            if (hoten && sdt && address && provinceName && districtName && wardName) {
                const fullAddress = [address, wardName, districtName, provinceName].filter(val => val).join(', ');
                document.getElementById('address-display').textContent = `${hoten} (${sdt}) ${fullAddress}`;
                if (modal) {
                    modal.style.display = "none";
                    enableScroll();
                }

                $.ajax({
                    url: 'update_user_info.php',
                    type: 'POST',
                    data: { hoten: hoten, sdt: sdt, diachi: fullAddress, user_id: '<?= $user_id ?>' },
                    success: function(response) {
                        console.log('Cập nhật thành công:', response);
                        Swal.fire({
                            icon: 'success',
                            title: 'Thành công',
                            text: 'Thay đổi thông tin thành công!',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#ee4d2d'
                        });
                    },
                    error: function(xhr, status, error) {
                        console.error('Lỗi khi cập nhật:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Lỗi',
                            text: 'Có lỗi xảy ra khi cập nhật thông tin!',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#ee4d2d'
                        });
                    }
                });
                updateShippingCostFromDB();
                calculateTotalWithDiscount();
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Lỗi',
                    text: 'Vui lòng điền đầy đủ thông tin!',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#ee4d2d'
                });
            }
        });
    }

    if (defaultBtn) {
        defaultBtn.addEventListener('click', function() {
            window.location.href = 'giohang.php';
            if (modal) {
                modal.style.display = "none";
                enableScroll();
            }
        });
    }

    function centerModal() {
        if (modal) {
            const modalContent = modal.querySelector('.modal-content');
            modalContent.style.position = 'absolute';
            modalContent.style.top = '50%';
            modalContent.style.left = '50%';
            modalContent.style.transform = 'translate(-50%, -50%)';
        }
    }

    function disableScroll() {
        document.body.style.overflow = 'hidden';
    }

    function enableScroll() {
        document.body.style.overflow = 'auto';
    }

    if (document.querySelectorAll('input[name="selected_products[]"]').length > 0) {
        calculateTotalWithDiscount();
    }
});
</script>

<style>
.quantity-control { display: flex; align-items: center; justify-content: center; }
.quantity-btn { width: 20px; height: 20px; cursor: pointer; }
.quantity-input { width: 40px; text-align: center; margin: 0 5px; }
.center { text-align: center; }
.submit_css { padding: 10px 20px; background-color: #ff4d4d; color: white; border: none; cursor: pointer; text-decoration: none; display: inline-block; }
.submit_css:hover { background-color: #e60000; }

.txtb {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    margin-bottom: 15px;
    width: 100%;
}

.txtb label {
    font-weight: bold;
    margin-bottom: 5px;
    color: #333;
}

.txtb input, .txtb select, .txtb textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 5px;
    background-color: #fff;
    font-size: 16px;
}

.shipping-methods, .payment-methods {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin: 15px 0;
    padding: 0;
}

.shipping-option, .payment-option {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 15px;
    background-color: #ffffff;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    text-decoration: none;
    color: #333;
}

.shipping-option:hover, .payment-option:hover:not(.disabled) {
    border-color: #ff4d4d;
    background-color: #fffafa;
    transform: translateY(-2px);
}

.shipping-option.active, .payment-option.active {
    border-color: #ff4d4d;
    background-color: #fff5f5;
    box-shadow: 0 4px 8px rgba(255, 77, 77, 0.1);
}

.shipping-option.disabled, .payment-option.disabled {
    background-color: #f5f5f5;
    color: #a0a0a0;
    cursor: not-allowed;
    border-color: #e0e0e0;
}

.shipping-content, .payment-content {
    display: flex;
    flex-direction: column;
    width: 100%;
    gap: 8px;
}

.method-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 16px;
    font-weight: 600;
    color: #222;
}

.method-description p {
    margin: 0;
    font-size: 14px;
    color: #555;
}

.payment-details {
    margin-top: 20px;
}

.payment-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    font-size: 18px;
}

.payment-row.total {
    font-weight: bold;
    font-size: 18px;
    margin-top: 15px;
    border-top: 1px solid #ddd;
    padding-top: 10px;
}

.checkout-button {
    margin-top: 20px;
    text-align: center;
}

.shipping-info {
    margin-bottom: 20px;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 5px;
    background-color: #f9f9f9;
}

.static-info {
    margin-bottom: 10px;
}

.static-info p {
    margin: 5px 0;
    font-size: 16px;
}

.address-actions {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}

.change-btn {
    padding: 8px 15px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    background-color: #ee4d2d;
    color: white;
}

.change-btn:hover {
    background-color: #d04526;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
    background-color: rgba(0,0,0,0.4);
}

.modal-content {
    background-color: #fefefe;
    padding: 20px;
    border: 1px solid #888;
    width: 80%;
    max-width: 500px;
    border-radius: 5px;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

.close-btn {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close-btn:hover,
.close-btn:focus {
    color: black;
    text-decoration: none;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

.default-btn, .save-btn {
    padding: 8px 15px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}

.default-btn {
    background-color: #fff;
    border: 1px solid #ccc;
    color: #333;
}

.save-btn {
    background-color: #ee4d2d;
    color: white;
}

.default-btn:hover {
    background-color: #f0f0f0;
}

.save-btn:hover {
    background-color: #d04526;
}
</style>
</body>
</html>