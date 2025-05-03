<?php
include "config.php";

if (isset($_POST['hoten']) && isset($_POST['sdt']) && isset($_POST['diachi']) && isset($_POST['user_id'])) {
    $hoten = $_POST['hoten'];
    $sdt = $_POST['sdt'];
    $diachi = $_POST['diachi'];
    $user_id = intval($_POST['user_id']);

    $stmt = $conn->prepare("UPDATE frm_dangky SET hoten = ?, sdt = ?, diachi = ? WHERE id = ?");
    $stmt->bind_param("sssi", $hoten, $sdt, $diachi, $user_id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error']);
}
?>