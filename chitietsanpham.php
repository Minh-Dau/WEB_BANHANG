<?php
ob_start();
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');
include("config.php");

// Validate and fetch product data
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID sản phẩm không hợp lệ!");
}

$stmt = $conn->prepare("SELECT * FROM sanpham WHERE id = ?");
$stmt->bind_param("i", $_GET['id']);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows == 0) {
    die("Sản phẩm không tồn tại!");
}
$product = $result->fetch_assoc();
$stmt->close();

// Biến để lưu thông báo lỗi
$error_message = "";

// Xử lý "Mua ngay"
if (isset($_SESSION['username']) && isset($_POST['buy_now'])) {
    $username = $_SESSION['username'];
    $product_id = (int)$_POST['id'];
    $tensanpham = trim($_POST['tensanpham']);
    $soluong = isset($_POST['soluong']) ? (int)$_POST['soluong'] : 1;

    // Lấy user_id từ username
    $stmt = $conn->prepare("SELECT id FROM frm_dangky WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 0) {
        $error_message = "Không tìm thấy người dùng!";
        $stmt->close();
    } else {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];
        $stmt->close();

        // Kiểm tra số lượng tồn kho
        $stmt = $conn->prepare("SELECT soluong FROM sanpham WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $soluong_tonkho = $row['soluong'];
        $stmt->close();

        // Kiểm tra số lượng hiện có trong giỏ hàng
        $current_cart_quantity = 0;
        $stmt = $conn->prepare("SELECT soluong FROM cart_item WHERE user_id = ? AND sanpham_id = ?");
        $stmt->bind_param("ii", $user_id, $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $current_cart_quantity = $row['soluong'];
        }
        $stmt->close();

        // Kiểm tra tổng số lượng (trong giỏ + yêu cầu mới) so với tồn kho
        $total_quantity = $current_cart_quantity + $soluong;
        if ($total_quantity > $soluong_tonkho) {
            $error_message = "Sản phẩm $tensanpham chỉ còn $soluong_tonkho sản phẩm trong kho. Bạn đã có $current_cart_quantity sản phẩm trong giỏ hàng!";
        } else {
            // Cập nhật hoặc thêm mới vào giỏ hàng
            if ($current_cart_quantity > 0) {
                $stmt_update = $conn->prepare("UPDATE cart_item SET soluong = ? WHERE user_id = ? AND sanpham_id = ?");
                $stmt_update->bind_param("iii", $total_quantity, $user_id, $product_id);
                $stmt_update->execute();
                $stmt_update->close();
            } else {
                $stmt_insert = $conn->prepare("INSERT INTO cart_item (user_id, sanpham_id, soluong) VALUES (?, ?, ?)");
                $stmt_insert->bind_param("iii", $user_id, $product_id, $soluong);
                $stmt_insert->execute();
                $stmt_insert->close();
            }

            // Chuyển hướng đến trang giỏ hàng
            header("Location: giohang.php?buy_now=" . $product_id);
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hu$tle Stonie</title>
    <link rel="stylesheet" href="css_chitiet.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Include jQuery for AJAX -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Include SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php include 'header.php'; ?>
    <table class="sanpham">
        <tr>
            <td><img src="<?= htmlspecialchars($product['img']) ?>" alt="" class="anh_cc" onclick="zoom(this)"></td>
            <td>
                <div class="thongtin">
                    <div class="titel_chitiet"><?= htmlspecialchars($product['tensanpham']) ?></div>
                    <p id="soluongton">Số lượng còn lại: <span id="tonkho"><?= number_format($product['soluong'], 0, '', '') ?></span></p>
                    <p id="gia">Giá: <?= number_format($product['gia'], 0, ',', '.') ?> VNĐ</p>
                    <br>
                    <!-- Form for "Buy Now" -->
                    <form action="" method="POST" onsubmit="return validateForm()" id="buyNowForm">
                        <input type="hidden" name="id" value="<?= $product['id'] ?>">
                        <input type="hidden" name="tensanpham" value="<?= htmlspecialchars($product['tensanpham']) ?>">
                        <p id="mausac"><b style="font-size: 20px">Số lượng:</b></p>
                        <div id="soluong">
                            <input type="button" value="-" id="giam" onclick="giamSo()">
                            <input type="text" id="so" name="soluong" value="1" oninput="validateQuantity()">
                            <input type="button" value="+" id="tang" onclick="tangSo()">
                        </div>
                        <button type="button" class="btn_giohang" onclick="addToCart()">Thêm vào giỏ hàng</button>
                        <input type="submit" name="buy_now" value="Mua hàng" class="btn_muahang">
                    </form>
                    <div class="sdt">
                        <span>Gọi đặt mua 0122112211 8:00-22:00</span>
                    </div>
                    <hr width="100%">
                </div>       
            </td>
        </tr>
    </table>
    <!-- Đánh giá sản phẩm -->
    <div class="rv-review-section" id="review-section">
        <h2 class="titel_danhgia">Đánh giá sản phẩm</h2>
        <div class="rv-review-sp">
            <?php
            include("config.php");
            $user_id = $_SESSION['user_id'] ?? 0;
            $product_id = $_GET['id'] ?? 0;
            $review_id = $_GET['review_id'] ?? 0;
            $da_mua = false;
            $orders = [];

            // Update the is_seen status for a specific review if review_id is provided
            if ($review_id > 0) {
                $sql_update_seen = "UPDATE danhgia SET is_seen = 1 WHERE id = ?";
                $stmt_update_seen = $conn->prepare($sql_update_seen);
                $stmt_update_seen->bind_param("i", $review_id);
                $stmt_update_seen->execute();
                $stmt_update_seen->close();
            }

            // Check if the user has purchased the product
            if ($user_id > 0 && $product_id > 0) {
                $sql_check = "SELECT oder.id FROM oder_detail
                    INNER JOIN oder ON oder_detail.oder_id = oder.id
                    WHERE oder.user_id = ? 
                        AND oder_detail.sanpham_id = ?
                        AND oder.trangthai = 'Đã giao'";
                $stmt = $conn->prepare($sql_check);
                $stmt->bind_param("ii", $user_id, $product_id);
                $stmt->execute();
                $result_check = $stmt->get_result();

                while ($row = $result_check->fetch_assoc()) {
                    $orders[] = $row['id'];
                    $da_mua = true;
                }
                $stmt->close();
            }

            // Display the review form or status for users who have purchased the product
            if ($user_id > 0 && $da_mua): ?>
                <?php foreach ($orders as $order_id): ?>
                    <?php
                    // Check if a review exists for this user, product, and order
                    $sql_check_review = "SELECT * FROM danhgia WHERE user_id = ? AND sanpham_id = ? AND oder_id = ?";
                    $stmt_check = $conn->prepare($sql_check_review);
                    $stmt_check->bind_param("iii", $user_id, $product_id, $order_id);
                    $stmt_check->execute();
                    $result_check_review = $stmt_check->get_result();
                    $review = $result_check_review->fetch_assoc();
                    $stmt_check->close();

                    // Check has_reviewed in oder_detail
                    $has_reviewed = false;
                    $sql_check_has_reviewed = "SELECT has_reviewed FROM oder_detail WHERE oder_id = ? AND sanpham_id = ?";
                    if ($stmt_check_has_reviewed = $conn->prepare($sql_check_has_reviewed)) {
                        $stmt_check_has_reviewed->bind_param("ii", $order_id, $product_id);
                        $stmt_check_has_reviewed->execute();
                        $result_check_has_reviewed = $stmt_check_has_reviewed->get_result();
                        $row_has_reviewed = $result_check_has_reviewed->fetch_assoc();
                        $has_reviewed = ($row_has_reviewed['has_reviewed'] ?? 0) == 1;
                        $stmt_check_has_reviewed->close();
                    }
                    ?>

                    <?php if ($review): ?>
                        <?php if ($review['trangthaiduyet'] == 0): ?>
                            <!-- Review exists but is not yet approved -->
                            <p class="rv-waiting-approval">Bạn đã gửi đánh giá, vui lòng chờ admin duyệt đánh giá.</p>
                        <?php endif; ?>
                    <?php elseif ($has_reviewed): ?>
                        <!-- Nếu has_reviewed = 1 nhưng không có bản ghi trong danhgia -->
                    <?php else: ?>
                        <!-- No review exists and has_reviewed = 0, show the form to submit a new review -->
                        <form action="xulydanhgia.php" method="POST" class="review-form">
                            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                            <input type="hidden" name="sanpham_id" value="<?php echo $product_id; ?>">
                            <input type="hidden" name="oder_id" value="<?php echo $order_id; ?>">

                            <div class="star-rating">
                                <input type="radio" id="star5" name="rating" value="5"><label for="star5">★</label>
                                <input type="radio" id="star4" name="rating" value="4"><label for="star4">★</label>
                                <input type="radio" id="star3" name="rating" value="3"><label for="star3">★</label>
                                <input type="radio" id="star2" name="rating" value="2"><label for="star2">★</label>
                                <input type="radio" id="star1" name="rating" value="1"><label for="star1">★</label>
                            </div>

                            <input type="text" name="comment" placeholder="Nhập đánh giá của bạn">
                            <button type="submit">Gửi đánh giá</button>
                        </form>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="tb_danhgia">Bạn cần mua sản phẩm này để có thể đánh giá.</p>
            <?php endif; ?>

            <?php
            // Calculate the average rating, considering only approved reviews
            $sql_avg_rating = "SELECT AVG(rating) as avg_rating FROM danhgia WHERE sanpham_id = ? AND trangthaiduyet = 1";
            $stmt_avg = $conn->prepare($sql_avg_rating);
            $stmt_avg->bind_param("i", $product_id);
            $stmt_avg->execute();
            $result_avg = $stmt_avg->get_result();
            $row_avg = $result_avg->fetch_assoc();
            $avg_rating = round($row_avg['avg_rating'] ?? 0, 1);
            $stmt_avg->close();

            function renderStars($rating) {
                $fullStars = floor($rating);
                $halfStar = ($rating - $fullStars) >= 0.5 ? 1 : 0;
                $emptyStars = 5 - ($fullStars + $halfStar);

                $starsHTML = "";
                for ($i = 0; $i < $fullStars; $i++) {
                    $starsHTML .= '<i class="fas fa-star"></i>';
                }
                if ($halfStar) {
                    $starsHTML .= '<i class="fas fa-star-half-alt"></i>';
                }
                for ($i = 0; $i < $emptyStars; $i++) {
                    $starsHTML .= '<i class="far fa-star"></i>';
                }
                return $starsHTML;
            }
            ?>
            <div class="rv-rating-container">
                <span class="rv-rating-number"><?php echo $avg_rating; ?></span>
                <span class="rv-stars"><?php echo renderStars($avg_rating); ?></span>
            </div>    
        </div>

        <div class="mota">
            <p>Mô tả sản phẩm</p>
        </div>
        <div class="motasanpham">
            <p class="mota_sp"><?= nl2br(htmlspecialchars($product['noidungsanpham'])) ?></p>
        </div>

        <div class="rv-review-list">
            <h2 class="titel_danhgia_user">Đánh giá từ người mua</h2>
            <div class="rv-review-sp" id="review-list">
                <?php
                // Check if the user is an admin
                $is_admin = false;
                if (isset($_SESSION['user_id'])) {
                    $sql_role = "SELECT phanquyen FROM frm_dangky WHERE id = ?";
                    $stmt_role = $conn->prepare($sql_role);
                    $stmt_role->bind_param("i", $_SESSION['user_id']);
                    $stmt_role->execute();
                    $result_role = $stmt_role->get_result();
                    $user_role = $result_role->fetch_assoc()['phanquyen'] ?? '';
                    $stmt_role->close();
                    if ($user_role === 'admin') {
                        $is_admin = true;
                    }
                }

                // Fetch only the 3 most recent approved reviews (or all for admin) with created_at
                $sql_reviews = "SELECT danhgia.*, frm_dangky.username, frm_dangky.hoten AS user_name
                                FROM danhgia 
                                JOIN frm_dangky ON danhgia.user_id = frm_dangky.id
                                WHERE danhgia.sanpham_id = ? " . ($is_admin ? "" : "AND danhgia.trangthaiduyet = 1") . "
                                ORDER BY danhgia.trangthaiduyet ASC, danhgia.created_at DESC
                                LIMIT 3";
                $stmt_reviews = $conn->prepare($sql_reviews);
                $stmt_reviews->bind_param("i", $product_id);
                $stmt_reviews->execute();
                $result_reviews = $stmt_reviews->get_result();

                if ($result_reviews->num_rows > 0) {
                    while ($review = $result_reviews->fetch_assoc()) {
                        $is_approved = $review['trangthaiduyet'] == 1;
                        // Format the created_at timestamp
                        $review_time = date('d/m/Y H:i:s', strtotime($review['created_at']));
                        echo '<div class="rv-review-item" data-review-id="' . $review['id'] . '">';
                        echo '<p><strong>' . htmlspecialchars($review['user_name']) . '</strong> - <span class="rv-rating">' . $review['rating'] . '★</span>';
                        if ($is_admin) {
                            echo ' - <span class="rv-status">' . ($is_approved ? 'Đã duyệt' : 'Chưa duyệt') . '</span>';
                        }
                        echo ' - <div class="rv_time_all"><span class="rv-time">' . $review_time . '</span></div></p>';
                        echo '<p>' . htmlspecialchars($review['comment']) . '</p>';

                        // Show Approve and Delete buttons for admin if the review is not yet approved
                        if ($is_admin && !$is_approved) {
                            echo '<div class="rv-admin-actions">';
                            echo '<button class="rv-approve-btn" onclick="approveReview(' . $review['id'] . ')">Duyệt</button>';
                            echo '<button class="rv-delete-btn" onclick="deleteReview(' . $review['id'] . ')">Xóa</button>';
                            echo '</div>';
                        }
                        echo '</div>';
                    }
                } else {
                    echo '<p class="rv-no-reviews">Chưa có đánh giá nào.</p>';
                }

                $stmt_reviews->close();
                // Check if there are more than 3 reviews to show "Xem tất cả" button
                $sql_count = "SELECT COUNT(*) as total FROM danhgia WHERE sanpham_id = ? " . ($is_admin ? "" : "AND trangthaiduyet = 1");
                $stmt_count = $conn->prepare($sql_count);
                $stmt_count->bind_param("i", $product_id);
                $stmt_count->execute();
                $result_count = $stmt_count->get_result();
                $total_reviews = $result_count->fetch_assoc()['total'];
                $stmt_count->close();

                if ($total_reviews > 3) {
                    echo '<button class="rv-view-all-btn" onclick="loadAllReviews()">Xem tất cả</button>';
                }

                $conn->close();
                ?>
            </div>
        </div>
    </div>
    <!-- Sản phẩm cùng danh mục -->
    <div class="headline">
        <h3>SẢN PHẨM TƯƠNG TỰ</h3>
    </div>
    <div class="wrapper">
        <div class="product" id="related-products">
            <?php
            include("config.php");
            $product_id = isset($_GET['id']) ? $_GET['id'] : 0;
            $sql = "SELECT danhmuc_id FROM sanpham WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $product_data = $result->fetch_assoc();
            $danhmuc_id = $product_data['danhmuc_id'];

            // Lấy danh sách sản phẩm cùng danh mục nhưng không bao gồm sản phẩm hiện tại
            $sql_related = "SELECT * FROM sanpham WHERE danhmuc_id = ? AND id != ? AND trangthai = 'Hiển Thị'";
            $stmt_related = $conn->prepare($sql_related);
            $stmt_related->bind_param("ii", $danhmuc_id, $product_id);
            $stmt_related->execute();
            $result_related = $stmt_related->get_result();

            if ($result_related->num_rows > 0) {
                while ($row = $result_related->fetch_assoc()) {
            ?>
            <div class="product_item">
                <div class="product_top">
                    <a href="chitietsanpham.php?id=<?= $row['id'] ?>" class="product_thumb">
                        <img src="<?= htmlspecialchars($row['img']) ?>" alt="" width="250" height="250">
                    </a>
                    <a href="chitietsanpham.php?id=<?= $row['id'] ?>" class="buy_now">Mua ngay</a>
                </div>
                <div class="product_info">
                    <a href="chitietsanpham.php?id=<?= $row['id'] ?>" class="product_cat"><?= htmlspecialchars($row['tensanpham']) ?></a>
                    <div class="product_price"><?= number_format($row['gia'], 0, ',', '.') ?> VNĐ</div>
                </div>
            </div>
            <?php
                }
            } else {
                echo '<p style="text-align: center; color: red;">Không có sản phẩm liên quan.</p>';
            }

            $stmt->close();
            $stmt_related->close();
            $conn->close();
            ?>
        </div>
    </div>
    <!-- Footer -->
    <?php include 'footer.php'; ?>

    <!-- Script -->
    <script>
        // Hiển thị thông báo lỗi bằng SweetAlert2 nếu có
        <?php if (!empty($error_message)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Lỗi',
                text: '<?= htmlspecialchars($error_message) ?>',
                confirmButtonText: 'OK',
                confirmButtonColor: '#ff4d4d'
            });
        <?php endif; ?>

        // Lấy số lượng tồn kho
        const tonKho = parseInt(document.getElementById("tonkho").textContent);

        function validateQuantity() {
            var inputElement = document.getElementById('so');
            if (!inputElement) {
                console.error('Input #so không tồn tại!');
                return;
            }
            var inputValue = inputElement.value.trim();
            var numericRegex = /^[0-9]+$/;
            if (!numericRegex.test(inputValue) || parseInt(inputValue) <= 0) {
                inputElement.value = 1;
            } else if (parseInt(inputValue) > tonKho) {
                inputElement.value = tonKho;
                Swal.fire({
                    icon: 'error',
                    title: 'Lỗi',
                    text: 'Số lượng vượt quá tồn kho! Chỉ còn ' + tonKho + ' sản phẩm.',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#ff4d4d'
                });
            }
        }

        function giamSo() {
            var soInput = document.getElementById('so');
            var currentQuantity = parseInt(soInput.value);
            if (currentQuantity > 1) {
                soInput.value = currentQuantity - 1;
            }
        }

        function tangSo() {
            var soInput = document.getElementById('so');
            var currentQuantity = parseInt(soInput.value);
            if (currentQuantity < tonKho) {
                soInput.value = currentQuantity + 1;
            } else {
                soInput.value = tonKho;
                Swal.fire({
                    icon: 'error',
                    title: 'Lỗi',
                    text: 'Số lượng vượt quá tồn kho! Chỉ còn ' + tonKho + ' sản phẩm.',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#ff4d4d'
                });
            }
        }

        function validateForm() {
            validateQuantity();
            var soLuong = parseInt(document.getElementById('so').value);
            if (soLuong > tonKho) {
                Swal.fire({
                    icon: 'error',
                    title: 'Lỗi',
                    text: 'Số lượng vượt quá tồn kho! Chỉ còn ' + tonKho + ' sản phẩm.',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#ff4d4d'
                });
                return false;
            }
            if (soLuong < 1) {
                Swal.fire({
                    icon: 'error',
                    title: 'Lỗi',
                    text: 'Vui lòng chọn số lượng hợp lệ!',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#ff4d4d'
                });
                return false;
            }
            return true;
        }

        function addToCart() {
            validateQuantity();
            var id = $('input[name="id"]').val();
            var tensanpham = $('input[name="tensanpham"]').val();
            var soluong = $('#so').val();

            console.log('Sending data:', { id: id, tensanpham: tensanpham, soluong: soluong });

            if (!id || !soluong || parseInt(soluong) <= 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Lỗi',
                    text: 'Dữ liệu không hợp lệ! Vui lòng kiểm tra lại.',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#ff4d4d'
                });
                return;
            }

            if (parseInt(soluong) > tonKho) {
                Swal.fire({
                    icon: 'error',
                    title: 'Lỗi',
                    text: 'Số lượng vượt quá tồn kho! Chỉ còn ' + tonKho + ' sản phẩm.',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#ff4d4d'
                });
                return;
            }

            $.ajax({
                url: 'ajax_cart.php',
                type: 'POST',
                data: {
                    id: id,
                    tensanpham: tensanpham,
                    soluong: soluong,
                    add_to_cart: true
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Parsed Result:', response);
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Thành công',
                            text: response.message || 'Đã thêm vào giỏ hàng thành công!',
                            showCancelButton: true,
                            confirmButtonText: 'Đi đến giỏ hàng',
                            cancelButtonText: 'Tiếp tục mua sắm',
                            confirmButtonColor: '#ff4d4d',
                            cancelButtonColor: '#707070'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = 'giohang.php';
                            }
                        });
                        $('#cart-count').text(response.total_items || 0);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Lỗi',
                            text: response.message || 'Có lỗi xảy ra khi thêm vào giỏ hàng',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#ff4d4d'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    console.log('Response Text:', xhr.responseText);
                    Swal.fire({
                        icon: 'error',
                        title: 'Lỗi',
                        text: 'Không thể kết nối đến server hoặc phản hồi không hợp lệ!',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#ff4d4d'
                    });
                }
            });
        }

        function zoom(img) {
            Swal.fire({
                imageUrl: img.src,
                imageAlt: 'Ảnh sản phẩm',
                showConfirmButton: false,
                showCloseButton: true
            });
        }

        function approveReview(reviewId) {
            Swal.fire({
                title: 'Xác nhận',
                text: 'Bạn có chắc muốn duyệt bình luận này?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Duyệt',
                cancelButtonText: 'Hủy',
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#dc3545'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'xuly_review_admin.php',
                        type: 'POST',
                        data: {
                            action: 'approve',
                            review_id: reviewId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Thành công',
                                    text: response.message,
                                    confirmButtonText: 'OK',
                                    confirmButtonColor: '#28a745'
                                });
                                const reviewItem = $(`.rv-review-item[data-review-id="${reviewId}"]`);
                                reviewItem.find('.rv-status').text('Đã duyệt');
                                reviewItem.find('.rv-admin-actions').remove();
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Lỗi',
                                    text: response.message,
                                    confirmButtonText: 'OK',
                                    confirmButtonColor: '#dc3545'
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', status, error);
                            console.log('Response Text:', xhr.responseText);
                            Swal.fire({
                                icon: 'error',
                                title: 'Lỗi',
                                text: 'Không thể kết nối đến server!',
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    });
                }
            });
        }

        function deleteReview(reviewId) {
            Swal.fire({
                title: 'Xác nhận',
                text: 'Bạn có chắc muốn xóa bình luận này?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Xóa',
                cancelButtonText: 'Hủy',
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#707070'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'xuly_review_admin.php',
                        type: 'POST',
                        data: {
                            action: 'delete',
                            review_id: reviewId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Thành công',
                                    text: response.message,
                                    confirmButtonText: 'OK',
                                    confirmButtonColor: '#28a745'
                                });
                                $(`.rv-review-item[data-review-id="${reviewId}"]`).remove();
                                if ($('.rv-review-item').length === 0) {
                                    $('#review-list').html('<p class="rv-no-reviews">Chưa có đánh giá nào.</p>');
                                }
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Lỗi',
                                    text: response.message,
                                    confirmButtonText: 'OK',
                                    confirmButtonColor: '#dc3545'
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', status, error);
                            console.log('Response Text:', xhr.responseText);
                            Swal.fire({
                                icon: 'error',
                                title: 'Lỗi',
                                text: 'Không thể kết nối đến server!',
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    });
                }
            });
        }

        function loadAllReviews() {
            $.ajax({
                url: 'fetch_all_reviews.php',
                type: 'POST',
                data: {
                    product_id: <?php echo $product_id; ?>,
                    is_admin: <?php echo $is_admin ? 1 : 0; ?>
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        $('#review-list').html(response.html);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Lỗi',
                            text: response.message || 'Không thể tải đánh giá!',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    console.log('Response Text:', xhr.responseText);
                    Swal.fire({
                        icon: 'error',
                        title: 'Lỗi',
                        text: 'Không thể kết nối đến server!',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#dc3545'
                    });
                }
            });
        }
    </script>
</body>
</html>
<?php ob_end_flush(); ?>