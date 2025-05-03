<?php
header('Content-Type: application/json');
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $trangthai = isset($_POST['trangthai']) ? $_POST['trangthai'] : '';

    if ($id > 0 && !empty($trangthai)) {
        $sql = "UPDATE oder SET trangthai = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $trangthai, $id);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Không thể cập nhật trạng thái']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Dữ liệu không hợp lệ']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Yêu cầu không hợp lệ']);
}
$conn->close();
?>