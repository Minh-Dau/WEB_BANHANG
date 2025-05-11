<?php
header('Content-Type: application/json');

$response = [
    'status' => 'error',
    'message' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_products = isset($_POST['selected_products']) ? $_POST['selected_products'] : [];

    if (empty($selected_products)) {
        $response['message'] = 'Vui lòng chọn ít nhất một sản phẩm để thanh toán';
    } else {
        $response['status'] = 'success';
    }
}

echo json_encode($response);
exit;