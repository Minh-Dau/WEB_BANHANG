<?php
session_start();
include("config.php");

$response = ['status' => 'error', 'message' => 'Invalid request'];

if (isset($_POST['product_id']) && is_numeric($_POST['product_id'])) {
    $product_id = (int)$_POST['product_id'];
    $is_admin = isset($_POST['is_admin']) && $_POST['is_admin'] == 1;

    // Fetch all reviews
    $sql_reviews = "SELECT danhgia.*, frm_dangky.username, frm_dangky.hoten AS user_name
                    FROM danhgia 
                    JOIN frm_dangky ON danhgia.user_id = frm_dangky.id
                    WHERE danhgia.sanpham_id = ? " . ($is_admin ? "" : "AND danhgia.trangthaiduyet = 1") . "
                    ORDER BY danhgia.trangthaiduyet ASC, danhgia.created_at DESC";
    $stmt_reviews = $conn->prepare($sql_reviews);
    $stmt_reviews->bind_param("i", $product_id);
    $stmt_reviews->execute();
    $result_reviews = $stmt_reviews->get_result();

    $html = '';
    if ($result_reviews->num_rows > 0) {
        while ($review = $result_reviews->fetch_assoc()) {
            $is_approved = $review['trangthaiduyet'] == 1;
            $review_time = date('d/m/Y H:i:s', strtotime($review['created_at']));
            $html .= '<div class="rv-review-item" data-review-id="' . $review['id'] . '">';
            $html .= '<p><strong>' . htmlspecialchars($review['user_name']) . '</strong> - <span class="rv-rating">' . $review['rating'] . '★</span>';
            if ($is_admin) {
                $html .= ' - <span class="rv-status">' . ($is_approved ? 'Đã duyệt' : 'Chưa duyệt') . '</span>';
            }
            $html .= ' - <span class="rv-time">' . $review_time . '</span></p>';
            $html .= '<p>' . htmlspecialchars($review['comment']) . '</p>';
    
            // Show Approve and Delete buttons for admin if the review is not yet approved
            if ($is_admin && !$is_approved) {
                $html .= '<div class="rv-admin-actions">';
                $html .= '<button class="rv-approve-btn" onclick="approveReview(' . $review['id'] . ')">Duyệt</button>';
                $html .= '<button class="rv-delete-btn" onclick="deleteReview(' . $review['id'] . ')">Xóa</button>';
                $html .= '</div>';
            }
            $html .= '</div>';
        }
    } else {
        $html .= '<p class="rv-no-reviews">Chưa có đánh giá nào.</p>';
    }

    $stmt_reviews->close();
    $conn->close();

    $response = ['status' => 'success', 'html' => $html];
}

header('Content-Type: application/json');
echo json_encode($response);
?>