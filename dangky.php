<?php
session_start();
include("config.php");
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['status' => 'error', 'message' => 'Có lỗi xảy ra!'];

    if ($_POST['action'] === 'register') {
        // Xử lý đăng ký
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $username = mysqli_real_escape_string($conn, $_POST['username']);
        $password = mysqli_real_escape_string($conn, $_POST['password']);
        $resetpassword = mysqli_real_escape_string($conn, $_POST['resetpassword']);

        // Ràng buộc email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = "Email không hợp lệ!";
            echo json_encode($response);
            exit();
        }

        if (!preg_match('/^[A-Za-z0-9!@#$%^&*()_+\-=\[\]{};:"\'<>,.?\/\\|]{6,20}$/', $username)) {
            $response['message'] = "Tên tài khoản không hợp lệ! Phải có ít nhất 6 ký tự.";
            echo json_encode($response);
            exit();
        }

        // Ràng buộc password
        if (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
            $response['message'] = "Mật khẩu phải có ít nhất 8 ký tự, bao gồm chữ in hoa, số và ký tự đặc biệt!";
            echo json_encode($response);
            exit();
        }

        // Ràng buộc resetpassword
        if ($password !== $resetpassword) {
            $response['message'] = "Mật khẩu nhập lại không khớp!";
            echo json_encode($response);
            exit();
        }

        // Kiểm tra tài khoản hoặc email đã tồn tại
        $check_query = "SELECT * FROM frm_dangky WHERE username='$username' OR email='$email'";
        $check_result = mysqli_query($conn, $check_query);

        if (mysqli_num_rows($check_result) > 0) {
            $response['message'] = "Tài khoản hoặc email đã tồn tại, vui lòng nhập lại thông tin!";
            echo json_encode($response);
            exit();
        }

        // Tạo mã xác nhận và gửi email
        $verification_code = rand(100000, 999999);
        $_SESSION['reg_verification_code'] = $verification_code;
        $_SESSION['reg_email'] = $email;
        $_SESSION['reg_username'] = $username;
        $_SESSION['reg_password'] = password_hash($password, PASSWORD_DEFAULT);

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'khannhsiuvip@gmail.com';
            $mail->Password = 'zlwjpcootaprksdp';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('khannhsiuvip@gmail.com', 'Hustler Stonie');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Mã xác nhận đăng ký tài khoản';
            $mail->Body = "Xin chào $username,<br>
                            Cảm ơn bạn đã đăng ký tài khoản! Mã xác nhận của bạn là: <b>$verification_code</b><br>Vui lòng nhập mã này để hoàn tất đăng ký. <br>
                            HUSTLER STONIE CHÚC QUÝ KHÁCH NHỮNG THỜI GIAN TRẢI NGHIỆM MUA HÀNG TỐT NHẤT!";
            $mail->CharSet = 'UTF-8';

            $mail->send();
            $response = [
                'status' => 'success',
                'message' => 'Đã gửi mã xác nhận đến email của bạn. Vui lòng kiểm tra!',
                'show_verification' => true
            ];
        } catch (Exception $e) {
            $response['message'] = "Không thể gửi mã xác nhận. Lỗi: {$mail->ErrorInfo}";
            unset($_SESSION['reg_verification_code']);
            unset($_SESSION['reg_email']);
            unset($_SESSION['reg_username']);
            unset($_SESSION['reg_password']);
        }
        echo json_encode($response);
        exit();
    } elseif ($_POST['action'] === 'verify') {
        // Xử lý xác nhận mã
        $input_code = $_POST['verification_code'];
        if ($input_code == $_SESSION['reg_verification_code']) {
            $email = $_SESSION['reg_email'];
            $username = $_SESSION['reg_username'];
            $hashed_password = $_SESSION['reg_password'];
            $default_phone = "";
            $default_role = "user";
            $default_address = "";
            $default_avatar = "";

            $insert_query = "INSERT INTO frm_dangky (email, username, password, sdt, phanquyen, diachi, anh) 
                            VALUES ('$email', '$username', '$hashed_password', '$default_phone', '$default_role', '$default_address', '$default_avatar')";
            
            if (mysqli_query($conn, $insert_query)) {
                unset($_SESSION['reg_verification_code']);
                unset($_SESSION['reg_email']);
                unset($_SESSION['reg_username']);
                unset($_SESSION['reg_password']);
                $response = [
                    'status' => 'success',
                    'message' => 'Đăng ký thành công! Chuyển hướng đến trang đăng nhập...',
                    'redirect' => 'dangnhap.php'
                ];
            } else {
                $response['message'] = "Lỗi đăng ký: " . mysqli_error($conn);
                $response['show_verification'] = true;
            }
        } else {
            $response['message'] = "Mã xác nhận không đúng!";
            $response['show_verification'] = true;
        }
        echo json_encode($response);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hu$tle Stonie</title>
    <link rel="stylesheet" href="css_dangky.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Include jQuery for AJAX -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Include SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<?php include 'header.php'; ?>

<form id="trangdk" action="dangky.php" method="POST">
    <div>
        <h1 class="h1_dk">ĐĂNG KÝ TÀI KHOẢN</h1>
        <p class="p_tt">Bạn đã có tài khoản? <a href="dangnhap.php" style="color: blue;">Đăng nhập tại đây</a></p>
        <br>
        <p class="p_tde">THÔNG TIN CÁ NHÂN</p>
        <div class="cha_dk">
            <div id="register-form">
                <h4 class="h4_dk">Email <span>*</span></h4>
                <input type="email" name="email" class="nhaptt" required placeholder="Nhập email">
                
                <h4 class="h4_dk">Tên tài khoản <span>*</span></h4>
                <input type="text" name="username" class="nhaptt" required placeholder="Nhập tên tài khoản" maxlength="20">
                
                <h4 class="h4_dk">Mật khẩu <span>*</span></h4>
                <input type="password" name="password" class="nhaptt" required placeholder="Nhập mật khẩu">
                
                <h4 class="h4_dk">Nhập lại mật khẩu <span>*</span></h4>
                <input type="password" name="resetpassword" class="nhaptt" required placeholder="Nhập lại mật khẩu">
                
                <button type="button" class="nhandk" onclick="register()">Đăng ký</button>
            </div>
            <div id="verify-form" style="display: none;">
                <h4 class="h4_dk">Nhập mã xác nhận <span>*</span></h4>
                <input type="text" name="verification_code" class="nhaptt" required placeholder="Nhập mã xác nhận">
                <button type="button" class="nhandk" onclick="verify()">Xác nhận</button>
            </div>
            <span id="error-message" style="display: block; margin-top: 10px;"></span>
            <br>
        </div>
    </div>
</form>
<?php include 'footer.php' ?>
<p style="font-size: 17px;" align="center" class="banquyen">Copyright © 2025 Hustler Stonie</p>

<script>
function showError(message, isSuccess = false) {
    Swal.fire({
        icon: isSuccess ? 'success' : 'error',
        title: isSuccess ? 'Thành công' : 'Lỗi',
        text: message,
        confirmButtonText: 'OK',
        confirmButtonColor: isSuccess ? '#ff4d4d' : '#ff4d4d'
    });
}

function register() {
    const formData = new FormData();
    formData.append('action', 'register');
    formData.append('email', $('input[name="email"]').val());
    formData.append('username', $('input[name="username"]').val());
    formData.append('password', $('input[name="password"]').val());
    formData.append('resetpassword', $('input[name="resetpassword"]').val());

    $.ajax({
        url: 'dangky.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.status === 'success') {
                showError(response.message, true);
                if (response.show_verification) {
                    $('#register-form').hide();
                    $('#verify-form').show();
                    $('.p_tde').text('XÁC NHẬN MÃ');
                    $('.p_tt').hide(); // Ẩn dòng "Bạn đã có tài khoản?"
                }
            } else {
                showError(response.message);
            }
        },
        error: function() {
            showError('Không thể kết nối đến server!');
        }
    });
}

function verify() {
    const formData = new FormData();
    formData.append('action', 'verify');
    formData.append('verification_code', $('input[name="verification_code"]').val());

    $.ajax({
        url: 'dangky.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.status === 'success') {
                showError(response.message, true);
                if (response.redirect) {
                    setTimeout(() => {
                        window.location.href = response.redirect;
                    }, 1500);
                }
            } else {
                showError(response.message);
                if (response.show_verification) {
                    $('#verify-form').show();
                }
            }
        },
        error: function() {
            showError('Không thể kết nối đến server!');
        }
    });
}
</script>
</body>
</html>