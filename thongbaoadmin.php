<?php
include("config.php");

if (isset($_GET['review_id'])) {
    $review_id = intval($_GET['review_id']); // Lấy ID của đánh giá thay vì ID sản phẩm
    $sql_update = "UPDATE danhgia SET is_seen = 1 WHERE id = ?";
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param("i", $review_id);
    $stmt->execute();
    $stmt->close();
}

$id_danhgia = $_GET['review_id'] ?? 0;

if ($id_danhgia > 0) {
    $sql = "SELECT * FROM danhgia WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_danhgia);
    $stmt->execute();
    $review = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($review) {
        echo "<h3>Đánh giá từ User #" . htmlspecialchars($review['user_id']) . "</h3>";
        echo "<p>Rating: " . $review['rating'] . " ★</p>";
        echo "<p>" . htmlspecialchars($review['comment']) . "</p>";
    } else {
        echo "<p>Không tìm thấy đánh giá này.</p>";
    }
} else {
    echo "<p>Không có đánh giá được chọn.</p>";
}

$conn->close();
?>
