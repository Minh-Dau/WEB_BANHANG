<?php
include 'header.php';
include("config.php");

// Kiểm tra đăng nhập
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$query = "SELECT email, sdt, diachi, anh, hoten FROM frm_dangky WHERE username='$username'";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);

// Split the existing diachi into components (if possible)
$diachi = $user['diachi'] ?? '';
$address_parts = explode(', ', $diachi);
$specific_address = $address_parts[0] ?? '';
$ward = $address_parts[1] ?? '';
$district = $address_parts[2] ?? '';
$province = $address_parts[3] ?? '';

$notification = ''; // Biến để lưu thông báo

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $sdt = $_POST['sdt'];
    $hoten = $_POST['hoten'];

    // Construct the new diachi from the selected fields
    $specific_address = trim($_POST['specific_address']);
    $province_name = trim($_POST['province_name']);
    $district_name = trim($_POST['district_name']);
    $ward_name = trim($_POST['ward_name']);

    // Filter out empty address components and join with commas
    $address_components = array_filter([$specific_address, $ward_name, $district_name, $province_name], function($value) {
        return !empty($value);
    });
    $diachi = mysqli_real_escape_string($conn, implode(', ', $address_components));

    // Update user information if there are changes
    if ($email != $user['email'] || $sdt != $user['sdt'] || $diachi != $user['diachi'] || $hoten != $user['hoten']) {
        $update_query = "UPDATE frm_dangky SET email='$email', sdt='$sdt', diachi='$diachi', hoten='$hoten' WHERE username='$username'";
        if (mysqli_query($conn, $update_query)) {
            $notification = 'update_success';
        }
    }

    // Xử lý upload ảnh
    if (!empty($_FILES["anh"]["name"])) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true); 
        }
        $imageFileType = strtolower(pathinfo($_FILES["anh"]["name"], PATHINFO_EXTENSION));
        $allowed_types = array("jpg", "jpeg", "png", "gif");
    
        if (in_array($imageFileType, $allowed_types)) {
            $target_file = $target_dir . md5(time() . $username) . "." . $imageFileType; 
    
            if (move_uploaded_file($_FILES["anh"]["tmp_name"], $target_file)) {
                if ($target_file != $user['anh']) {
                    $update_image_query = "UPDATE frm_dangky SET anh='$target_file' WHERE username='$username'";
                    if (mysqli_query($conn, $update_image_query)) {
                        $notification = 'image_success';
                    }
                }
            } else {
                $notification = 'image_upload_error';
            }
        } else {
            $notification = 'image_type_error';
        }
    }

    // Xử lý cập nhật mật khẩu
    if (!empty($_POST['password']) && !empty($_POST['confirm_password'])) {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if ($password === $confirm_password) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update_pass_query = "UPDATE frm_dangky SET password='$hashed_password' WHERE username='$username'";
            if (mysqli_query($conn, $update_pass_query)) {
                $notification = 'password_success';
            }
        } else {
            $notification = 'password_mismatch';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hồ Sơ Của Tôi</title>
    <link rel="stylesheet" href="css_thongtin.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="header.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="profile-container">
        <h2>Hồ Sơ Của Tôi</h2>
        <p>Quản lý thông tin hồ sơ để bảo mật tài khoản</p>
        <br>
        <?php if (!empty($user['anh'])): ?>
            <img id="profile-avatar" src="<?= $user['anh']; ?>?t=<?= time(); ?>" alt="Ảnh đại diện" class="avatar">
        <?php endif; ?>
        <form class="profile-form" method="POST" enctype="multipart/form-data">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']); ?>" required>

            <label for="name">Họ và tên</label>
            <input type="text" id="name" name="hoten" value="<?= htmlspecialchars($user['hoten']); ?>" required>

            <label for="phone">Số điện thoại</label>
            <input type="text" id="phone" name="sdt" value="<?= htmlspecialchars($user['sdt']); ?>" required pattern="^0[0-9]{9}$" title="Số điện thoại phải có 10 chữ số và bắt đầu bằng số 0">

            <label for="province">Tỉnh/Thành phố</label>
            <select id="province" name="province" required>
                <option value="">Chọn tỉnh/thành phố</option>
            </select>
            <input type="hidden" id="province_name" name="province_name" value="<?= htmlspecialchars($province); ?>">

            <label for="district">Quận/Huyện</label>
            <select id="district" name="district" required>
                <option value="">Chọn quận/huyện</option>
            </select>
            <input type="hidden" id="district_name" name="district_name" value="<?= htmlspecialchars($district); ?>">

            <label for="ward">Phường/Xã</label>
            <select id="ward" name="ward" required>
                <option value="">Chọn phường/xã</option>
            </select>
            <input type="hidden" id="ward_name" name="ward_name" value="<?= htmlspecialchars($ward); ?>">

            <label for="specific_address">Địa chỉ cụ thể</label>
            <input type="text" id="specific_address" name="specific_address" value="<?= htmlspecialchars($specific_address); ?>" required>

            <label for="avatar">Ảnh đại diện</label>
            <input type="file" id="avatar" name="anh">

            <label for="password">Mật khẩu mới</label>
            <input type="password" id="password" name="password">

            <label for="confirm_password">Xác nhận mật khẩu</label>
            <input type="password" id="confirm_password" name="confirm_password">

            <button type="submit" class="btn">Lưu</button>
            <button type="submit" name="khoa_taikhoan" class="btn" style="background-color: red;">Khóa tài khoản</button>
        </form>
    </div>
    <?php include 'footer.php'; include 'chat.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Load provinces
        fetch("https://provinces.open-api.vn/api/p/")
            .then(response => response.json())
            .then(data => {
                let provinceSelect = document.getElementById("province");
                data.forEach(province => {
                    let option = document.createElement("option");
                    option.value = province.code;
                    option.textContent = province.name;
                    if (province.name === "<?= $province ?>") {
                        option.selected = true;
                    }
                    provinceSelect.appendChild(option);
                });

                // Trigger district loading if province is pre-selected
                if (provinceSelect.value) {
                    loadDistricts(provinceSelect.value);
                }
            });

        // Load districts when a province is selected
        document.getElementById("province").addEventListener("change", function() {
            let provinceCode = this.value;
            let provinceName = this.options[this.selectedIndex].text;
            document.getElementById("province_name").value = provinceName;
            loadDistricts(provinceCode);
        });

        // Load wards when a district is selected
        document.getElementById("district").addEventListener("change", function() {
            let districtCode = this.value;
            let districtName = this.options[this.selectedIndex].text;
            document.getElementById("district_name").value = districtName;
            loadWards(districtCode);
        });

        // Update ward name when a ward is selected
        document.getElementById("ward").addEventListener("change", function() {
            let wardName = this.options[this.selectedIndex].text;
            document.getElementById("ward_name").value = wardName;
        });

        function loadDistricts(provinceCode) {
            fetch(`https://provinces.open-api.vn/api/p/${provinceCode}?depth=2`)
                .then(response => response.json())
                .then(data => {
                    let districtSelect = document.getElementById("district");
                    districtSelect.innerHTML = '<option value="">Chọn quận/huyện</option>';
                    data.districts.forEach(district => {
                        let option = document.createElement("option");
                        option.value = district.code;
                        option.textContent = district.name;
                        if (district.name === "<?= $district ?>") {
                            option.selected = true;
                        }
                        districtSelect.appendChild(option);
                    });

                    // Trigger ward loading if district is pre-selected
                    if (districtSelect.value) {
                        loadWards(districtSelect.value);
                    }
                });
        }

        function loadWards(districtCode) {
            fetch(`https://provinces.open-api.vn/api/d/${districtCode}?depth=2`)
                .then(response => response.json())
                .then(data => {
                    let wardSelect = document.getElementById("ward");
                    wardSelect.innerHTML = '<option value="">Chọn phường/xã</option>';
                    data.wards.forEach(ward => {
                        let option = document.createElement("option");
                        option.value = ward.code;
                        option.textContent = ward.name;
                        if (ward.name === "<?= $ward ?>") {
                            option.selected = true;
                        }
                        wardSelect.appendChild(option);
                    });

                    // Update ward name if pre-selected
                    if (wardSelect.value) {
                        let wardName = wardSelect.options[wardSelect.selectedIndex].text;
                        document.getElementById("ward_name").value = wardName;
                    }
                });
        }
        // Hiển thị thông báo SweetAlert2 dựa trên biến notification
        const notification = '<?= $notification ?>';
        if (notification) {
            switch (notification) {
                case 'update_success':
                    Swal.fire({
                        icon: 'success',
                        title: 'Thành công',
                        text: 'Cập nhật thông tin thành công!',
                        confirmButtonColor: '#3498db',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.reload();
                    });
                    break;
                    case 'image_success':
                    Swal.fire({
                        icon: 'success',
                        title: 'Thành công',
                        text: 'Cập nhật ảnh thành công!',
                        confirmButtonColor: '#3498db',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        // Option 1: Update image dynamically
                        const avatar = document.getElementById('profile-avatar');
                        if (avatar) {
                            const timestamp = new Date().getTime();
                            avatar.src = newImagePath + '?t=' + timestamp; // Use newImagePath with cache-busting
                        }
                        // Option 2: Reload the page as a fallback
                        window.location.reload();
                    });
                    break;
                case 'image_upload_error':
                    Swal.fire({
                        icon: 'error',
                        title: 'Lỗi',
                        text: 'Lỗi khi tải ảnh lên!',
                        confirmButtonColor: '#ff4d4d',
                        confirmButtonText: 'OK'
                    });
                    break;
                case 'image_type_error':
                    Swal.fire({
                        icon: 'error',
                        title: 'Lỗi',
                        text: 'Chỉ chấp nhận JPG, JPEG, PNG, GIF!',
                        confirmButtonColor: '#ff4d4d',
                        confirmButtonText: 'OK'
                    });
                    break;
                case 'password_success':
                    Swal.fire({
                        icon: 'success',
                        title: 'Thành công',
                        text: 'Cập nhật mật khẩu thành công!',
                        confirmButtonColor: '#3498db',
                        confirmButtonText: 'OK'
                    });
                    break;
                case 'password_mismatch':
                    Swal.fire({
                        icon: 'error',
                        title: 'Lỗi',
                        text: 'Mật khẩu xác nhận không khớp!',
                        confirmButtonColor: '#ff4d4d',
                        confirmButtonText: 'OK'
                    });
                    break;
            }
        }
    });
    </script>
</body>
</html>

<?php
if (isset($_POST['khoa_taikhoan'])) {
    $update_status_query = "UPDATE frm_dangky SET trangthai='đã khóa' WHERE username='$username'";
    if (mysqli_query($conn, $update_status_query)) {
        session_destroy(); // Xóa session để đăng xuất
        $notification = 'account_locked';
    }
}
?>

<script>
// Hiển thị thông báo khóa tài khoản
if ('<?= $notification ?>' === 'account_locked') {
    Swal.fire({
        icon: 'info',
        title: 'Thông báo',
        text: 'Tài khoản của bạn đã bị khóa!',
        confirmButtonColor: '#3498db',
        confirmButtonText: 'OK',
        allowOutsideClick: false
    }).then(() => {
        window.location.href = 'dangnhap.php';
    });
}
</script>