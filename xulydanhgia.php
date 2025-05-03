<?php
include("config.php");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra người dùng đã đăng nhập chưa
$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id <= 0) {
    die("Bạn cần đăng nhập để thực hiện hành động này.");
}

// Kiểm tra vai trò người dùng
$sql_role = "SELECT phanquyen FROM frm_dangky WHERE id = ?";
$stmt_role = $conn->prepare($sql_role);
$stmt_role->bind_param("i", $user_id);
$stmt_role->execute();
$result_role = $stmt_role->get_result();
$user_role = $result_role->fetch_assoc()['phanquyen'] ?? '';
$stmt_role->close();

if ($user_role !== 'user') {
    die("Bạn không có quyền gửi hoặc cập nhật đánh giá.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Kiểm tra xem tất cả các trường đã được gửi hay chưa
    if (empty($_POST['user_id']) || empty($_POST['sanpham_id']) || empty($_POST['oder_id']) || empty($_POST['rating']) || !isset($_POST['comment'])) {
        die("Thiếu thông tin đánh giá.");
    }

    // Lấy dữ liệu từ form, đảm bảo an toàn
    $form_user_id = intval($_POST['user_id']);
    $sanpham_id = intval($_POST['sanpham_id']);
    $oder_id = intval($_POST['oder_id']);
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);

    // Kiểm tra tính hợp lệ của dữ liệu
    if ($form_user_id <= 0 || $sanpham_id <= 0 || $oder_id <= 0 || $rating < 1 || $rating > 5) {
        die("Dữ liệu không hợp lệ.");
    }

    // Kiểm tra xem user_id từ form có khớp với user_id trong session không (bảo mật)
    if ($form_user_id !== $user_id) {
        die("Dữ liệu không hợp lệ: ID người dùng không khớp.");
    }

    // Kiểm tra xem người dùng đã mua sản phẩm trong đơn hàng này chưa
    $sql_check_order = "SELECT oder.id FROM oder 
                        JOIN oder_detail ON oder.id = oder_detail.oder_id 
                        WHERE oder.user_id = ? AND oder_detail.sanpham_id = ? AND oder.id = ?";
    if ($stmt_check_order = $conn->prepare($sql_check_order)) {
        $stmt_check_order->bind_param("iii", $user_id, $sanpham_id, $oder_id);
        $stmt_check_order->execute();
        $result_check_order = $stmt_check_order->get_result();
        if ($result_check_order->num_rows === 0) {
            $stmt_check_order->close();
            die("Bạn chưa mua sản phẩm này trong đơn hàng này.");
        }
        $stmt_check_order->close();
    } else {
        die("Lỗi truy vấn: " . htmlspecialchars($conn->error));
    }

    // Kiểm tra xem người dùng đã từng gửi đánh giá cho sản phẩm này trong đơn hàng này chưa (dựa trên has_reviewed)
    $sql_check_has_reviewed = "SELECT has_reviewed FROM oder_detail 
                               WHERE oder_id = ? AND sanpham_id = ?";
    if ($stmt_check_has_reviewed = $conn->prepare($sql_check_has_reviewed)) {
        $stmt_check_has_reviewed->bind_param("ii", $oder_id, $sanpham_id);
        $stmt_check_has_reviewed->execute();
        $result_check_has_reviewed = $stmt_check_has_reviewed->get_result();
        $row_has_reviewed = $result_check_has_reviewed->fetch_assoc();
        $has_reviewed = $row_has_reviewed['has_reviewed'] ?? 0;
        $stmt_check_has_reviewed->close();

        // Nếu đã gửi đánh giá (has_reviewed = 1) nhưng không có bản ghi trong danhgia, không cho phép gửi lại
        if ($has_reviewed == 1) {
            $sql_check_danhgia = "SELECT id FROM danhgia WHERE user_id = ? AND sanpham_id = ? AND oder_id = ?";
            if ($stmt_check_danhgia = $conn->prepare($sql_check_danhgia)) {
                $stmt_check_danhgia->bind_param("iii", $user_id, $sanpham_id, $oder_id);
                $stmt_check_danhgia->execute();
                $result_check_danhgia = $stmt_check_danhgia->get_result();
                if ($result_check_danhgia->num_rows === 0) {
                    $stmt_check_danhgia->close();
                    die("Bạn đã gửi đánh giá trước đó và không thể gửi lại.");
                }
                $stmt_check_danhgia->close();
            } else {
                die("Lỗi truy vấn: " . htmlspecialchars($conn->error));
            }
        }
    } else {
        die("Lỗi truy vấn: " . htmlspecialchars($conn->error));
    }

    // Kiểm tra xem người dùng đã đánh giá cho đơn hàng này chưa (trong bảng danhgia)
    $sql_check = "SELECT id FROM danhgia WHERE user_id = ? AND sanpham_id = ? AND oder_id = ?";
    if ($stmt_check = $conn->prepare($sql_check)) {
        $stmt_check->bind_param("iii", $user_id, $sanpham_id, $oder_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $review = $result_check->fetch_assoc();
        $stmt_check->close();
    } else {
        die("Lỗi truy vấn: " . htmlspecialchars($conn->error));
    }

    if ($review) {
        // Cập nhật đánh giá nếu đã tồn tại
        $sql_update = "UPDATE danhgia SET rating = ?, comment = ?, is_edited = 1 WHERE user_id = ? AND sanpham_id = ? AND oder_id = ?";
        if ($stmt_update = $conn->prepare($sql_update)) {
            $stmt_update->bind_param("isiii", $rating, $comment, $user_id, $sanpham_id, $oder_id);
            if ($stmt_update->execute()) {
                $stmt_update->close();
                $conn->close();
                header("Location: chitietsanpham.php?id=" . $sanpham_id);
                exit();
            } else {
                die("Lỗi khi cập nhật đánh giá: " . htmlspecialchars($stmt_update->error));
            }
        } else {
            die("Lỗi truy vấn: " . htmlspecialchars($conn->error));
        }
    } else {
        // Thêm mới đánh giá nếu chưa có
        $sql_insert = "INSERT INTO danhgia (user_id, sanpham_id, oder_id, rating, comment, is_edited) VALUES (?, ?, ?, ?, ?, 0)";
        if ($stmt_insert = $conn->prepare($sql_insert)) {
            $stmt_insert->bind_param("iiiis", $user_id, $sanpham_id, $oder_id, $rating, $comment);
            if ($stmt_insert->execute()) {
                // Cập nhật has_reviewed = 1 trong bảng oder_detail
                $sql_update_has_reviewed = "UPDATE oder_detail SET has_reviewed = 1 WHERE oder_id = ? AND sanpham_id = ?";
                if ($stmt_update_has_reviewed = $conn->prepare($sql_update_has_reviewed)) {
                    $stmt_update_has_reviewed->bind_param("ii", $oder_id, $sanpham_id);
                    $stmt_update_has_reviewed->execute();
                    $stmt_update_has_reviewed->close();
                } else {
                    die("Lỗi truy vấn: " . htmlspecialchars($conn->error));
                }

                $stmt_insert->close();
                $conn->close();
                header("Location: chitietsanpham.php?id=" . $sanpham_id);
                exit();
            } else {
                die("Lỗi khi lưu đánh giá: " . htmlspecialchars($stmt_insert->error));
            }
        } else {
            die("Lỗi truy vấn: " . htmlspecialchars($conn->error));
        }
    }
}
$conn->close();
?>