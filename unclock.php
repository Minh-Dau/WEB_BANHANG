<?php
    session_start();
    include("config.php");

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['mokhoataikhoan'])) {
        $username = trim($_POST['username']);  // Xóa khoảng trắng
        $username = mysqli_real_escape_string($conn, $username);

        // Kiểm tra xem tài khoản có tồn tại không
        $sql = "SELECT * FROM frm_dangky WHERE username=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Cập nhật trạng thái tài khoản sang 'hoạt động'
            $update_query = "UPDATE frm_dangky SET trangthai='hoạt động' WHERE username=?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("s", $username);
            
            if ($stmt->execute()) {
                echo "<script>
                        alert('Tài khoản đã được mở khóa! Vui lòng đăng nhập lại.');
                        window.location.href='dangnhap.php';
                    </script>";
            } else {
                echo "<script>alert('Lỗi khi mở khóa tài khoản. Vui lòng thử lại!');</script>";
            }
        } else {
            echo "<script>alert('Tài khoản không tồn tại!');</script>";
        }
        $stmt->close();
        $conn->close();
    }
?>


<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mở Khóa Tài Khoản</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .unclock-container {
            width: 500px; /* Điều chỉnh độ rộng phù hợp */
            padding: 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        #trangmokhoa {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 20px;
            margin-bottom: 20px;
        }

        .cha_dk {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .nhap_tk{
            
        }
        .taikhoan {
            text-align: left;
            font-size: 16px;
            font-weight: bold;
        }

        /* Ô nhập liệu */
        .nhap_tk {
            width: 100%;
            padding: 10px;
            font-size: 14px;
            border: 1px solid #ccc;
            border-radius: 5px;
            outline: none;
        }

        /* Nút mở khóa */
        .nhandk {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
            margin-top: 10px;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .nhandk:hover {
            background-color:rgb(0, 106, 219);
            transform: scale(1.05); /* Hiệu ứng phóng to nhẹ */
        }

        .nhandk:active {
            transform: scale(0.98); /* Hiệu ứng bấm xuống */
        }
        .p_tde{
            padding-top: 5px;
            font-size: 20px;
        }
        .p_tt{
            padding-top: 5px;
        }
    </style>
</head>

<body>
<?php
    include 'header.php';
?>

<form id="trangmokhoa" action="unclock.php" method="POST">
    <div class="unclock-container">
        <h1 class="h1_dk">MỞ KHÓA TÀI KHOẢN</h1>
        <p class="p_tt">Quay lại đăng nhập? <a href="dangnhap.php">Đăng nhập tại đây</a></p>
        <p class="p_tde">THÔNG TIN CẦN THIẾT</p>
        <div class="cha_dk">
            <h4 class="taikhoan">Tên tài khoản <span style = "color: red;">*</span></h4>
            <input type="text" name="username" class="nhap_tk" required placeholder="Nhập tên tài khoản">
            
            <button class="nhandk" name="mokhoataikhoan">Mở khóa</button>
        </div>
    </div>
</form>

<?php
    include 'footer.php';
?>

<p style="font-size: 17px;" align="center" class="banquyen">Copyright © 2025 Hustler Stonie</p>

</body>
</html>