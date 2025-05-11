<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hu$tle Stonie</title>
    <link rel="stylesheet" href="css_dangnhap.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Include SweetAlert2 CSS and JS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<?php
session_start();
include("config.php");

$error_message1 = "";
$error_message2 = "";
$error_message3 = "";

// Nếu đã đăng nhập, chuyển hướng về trang chính
if (isset($_SESSION['username']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: trangchinh.php');
    exit();
}

// Kiểm tra khi nhấn nút đăng nhập
if (isset($_POST['dangnhap']) && !empty($_POST['username']) && !empty($_POST['password'])) {
    $taikhoan = $_POST['username'];
    $password = $_POST['password'];

    // Truy vấn lấy thông tin tài khoản từ CSDL
    $sql = "SELECT * FROM frm_dangky WHERE username=? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $taikhoan);
    $stmt->execute();
    $result = $stmt->get_result();

    // Kiểm tra tài khoản có tồn tại không
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Kiểm tra trạng thái tài khoản
        if ($user['trangthai'] == "đã khóa") { 
            $error_message3 = "Tài khoản của bạn đã bị khóa. Vui lòng liên hệ qua email hustlerstonie@gmail.com để mở lại.";
        } elseif (password_verify($password, $user['password'])) {
            // Đăng nhập thành công, đặt lại số lần thử
            $sql_reset_attempts = "UPDATE frm_dangky SET solandangnhap = 0 WHERE username = ?";
            $stmt_reset = $conn->prepare($sql_reset_attempts);
            $stmt_reset->bind_param("s", $taikhoan);
            $stmt_reset->execute();
            $stmt_reset->close();

            // Lưu thông tin vào session
            $_SESSION["username"] = $user["username"];
            $_SESSION["phanquyen"] = $user["phanquyen"];
            $_SESSION['user_id'] = $user['id'];

            // Lưu thông tin vào session
            $_SESSION['user'] = [
                'username' => $user['username'],
                'phanquyen' => $user['phanquyen'],
                'id' => $user['id']
            ];
            $_SESSION['logged_in'] = true; // Thêm để đảm bảo trạng thái đăng nhập
            if ($user["phanquyen"] == "admin") {
                header("Location: admin.php");
            } elseif ($user["phanquyen"] == "nhanvien") {
                header("Location: admin.php");
            } else {
                header("Location: trangchinh.php");
            }
            exit();
        } else {
            // Mật khẩu sai
            if ($user['phanquyen'] == "admin") {
                $error_message1 = "Sai mật khẩu!";
            } else {
                // Không phải admin: tăng số lần thử và kiểm tra khóa tài khoản
                $new_attempts = $user['solandangnhap'] + 1;
                $sql_update_attempts = "UPDATE frm_dangky SET solandangnhap = ? WHERE username = ?";
                $stmt_update = $conn->prepare($sql_update_attempts);
                $stmt_update->bind_param("is", $new_attempts, $taikhoan);
                $stmt_update->execute();
                $stmt_update->close();

                // Kiểm tra nếu số lần thử đạt 3
                if ($new_attempts >= 3) {
                    $sql_lock = "UPDATE frm_dangky SET trangthai = 'đã khóa', solandangnhap = 0 WHERE username = ?";
                    $stmt_lock = $conn->prepare($sql_lock);
                    $stmt_lock->bind_param("s", $taikhoan);
                    $stmt_lock->execute();
                    $stmt_lock->close();
                    $error_message3 = "Tài khoản của bạn đã bị khóa do nhập sai mật khẩu quá 3 lần. Vui lòng liên hệ qua email hustlerstonie@gmail.com để mở lại.";
                } else {
                    $error_message1 = "Sai mật khẩu! Bạn đã thử $new_attempts lần.";
                }
            }
        }
    } else {
        $error_message2 = "Tài khoản không tồn tại!";
    }
    $stmt->close();
}
$conn->close();
?>
    <?php include 'header.php'; ?>
    <form id="trangdangnhap" action="dangnhap.php" method="POST">
        <div class="taikhoan">
            <h2>ĐĂNG NHẬP TÀI KHOẢN TẠI ĐÂY</h2>
            <p class="thep">Bạn chưa có tài khoản?<a href="dangky.php" style="color: blue;"> Đăng ký tại đây</a></p>
            <div class="cha">
                <input type="text" name="username" class="input-group_gmail" required id="email">
                <label for="email" class="input-group_label_gmail"> Tài Khoản <span>*</span> </label>
            </div>
            <div class="cha">
                <input type="password" name="password" class="input-group_matkhau" required id="pass">
                <label for="matkhau" class="input-group_label_matkhau">Mật khẩu <span>*</span></label>
                <table class="hienthimatkhau">
                    <tr>
                        <td>
                            <input type="checkbox" id="check"> 
                        </td>
                        <td><p>Hiển thị mật khẩu</p></td>
                    </tr>   
                </table>
                <p>Quên mật khẩu?<a href="quenmatkhau.php" class="a_quenmk" style="color: blue;"> Nhấn vào đây</a></p>
                <div class="cha">
                    <button class="click" name="dangnhap">Đăng Nhập</button>
                </div>
            </div>
        </div>
    </form>
    <br>
    <div>
        <?php include 'footer.php'; ?>
    </div>
    <script>
        // Show password toggle
        var pass = document.getElementById("pass");
        var check = document.getElementById("check");
        check.onchange = function(e) {
            pass.type = check.checked ? "text" : "password";
        };

        // Display SweetAlert2 for error messages
        <?php if (!empty($error_message1)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Lỗi',
                text: '<?php echo addslashes($error_message1); ?>',
                confirmButtonText: 'OK'
            });
        <?php endif; ?>
        <?php if (!empty($error_message2)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Lỗi',
                text: '<?php echo addslashes($error_message2); ?>',
                confirmButtonText: 'OK'
            });
        <?php endif; ?>
        <?php if (!empty($error_message3)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Tài khoản bị khóa',
                text: '<?php echo addslashes($error_message3); ?>',
                confirmButtonText: 'OK'
            });
        <?php endif; ?>
    </script>
</body>
</html>