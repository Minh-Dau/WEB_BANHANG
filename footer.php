<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Footer với Bản đồ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        .footer p:hover {
            color:rgb(121, 121, 121);
            cursor: pointer;
        }

        /* Map popup */
        .map-popup {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 600px;
            height: 400px;
            background-color: #fff;
            border: 2px solid #ccc;
            border-radius: 12px;
            box-shadow: 0 0 10px rgba(0,0,0,0.3);
            padding: 10px;
        }

        .map-popup iframe {
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 10px;
        }

        .map-overlay {
            display: none;
            position: fixed;
            z-index: 9998;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .map-close-btn {
            position: absolute;
            top: 8px;
            right: 12px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            font-size: 16px;
            line-height: 26px;
            cursor: pointer;
        }
        a{
            color: black;
        }
    </style>
</head>
<body>

<div class="support">
    <p><i class="bi bi-telephone-fill"></i>  Hỗ trợ - Mua hàng: 
        <span style="color: rgb(201, 0, 0);"><b>0122 112 211</b></span>
    </p>
</div>

<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="footer-col">
                <h4>HỆ THỐNG CỬA HÀNG</h4>
                <p onclick="openMap()"><i class="bi bi-geo-alt"></i> &nbsp;Chi Nhánh Trường ĐH Sư Phạm Kỹ Thuật Vĩnh Long</p>
                <p onclick="openMap()"><i class="bi bi-geo-alt"></i> &nbsp;Đường Nguyễn Huệ, Phường 2, TP. Vĩnh Long</p>
                <p><i class="bi bi-telephone-fill"></i> &nbsp;Hotline: 0122 112 211</p>
                <p><i class="bi bi-envelope-fill"></i> &nbsp;<a href="mailto:hustlerstonie@gmail.com">hustlerstonie@gmail.com</a></p>
            </div>
            <div class="footer-col">
                <h4>Chính sách</h4>
                <ul>
                    <li><i class="bi bi-dot"></i>&nbsp;<a href="chinhsach.php">Hướng Dẫn Mua Hàng</a></li>
                    <li><i class="bi bi-dot"></i>&nbsp;<a href="baohanh.php">Bảo hành & Đổi trả</a></li>
                    <li><i class="bi bi-dot"></i>&nbsp;<a href="giaohanghoatoc.php">Giao hàng hỏa tốc</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Mạng Xã Hội</h4>
                <p><i class="bi bi-facebook"></i>&nbsp; <a href="https://www.facebook.com/profile.php?id=61576157423449">Hustler Stonie</a></p>
                <p><i class="bi bi-instagram"></i>&nbsp; <a href="https://www.facebook.com/profile.php?id=61576157423449">Hustler Stonie</a></p>
            </div>
            <div class="footer-col">
                <h4>Fanpage</h4>
                <iframe src="https://www.facebook.com/plugins/page.php?href=https%3A%2F%2Fwww.facebook.com%2Fprofile.php%3Fid%3D61576157423449&tabs=timeline&width=300&height=150&small_header=true&adapt_container_width=true&hide_cover=true&show_facepile=true&appId" width="300" height="150" style="border:none;overflow:hidden" scrolling="no" frameborder="0" allowfullscreen="true" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share"></iframe>
            </div>
        </div>
    </div>
</footer>
<hr>
    <p style="font-size: 17px;" align="center" class="banquyen">Copyright © 2025 Hustler Stonie</p>
</div>

<!-- Google Map Popup -->
<div class="map-overlay" onclick="closeMap()"></div>
<div class="map-popup" id="mapPopup">
    <button class="map-close-btn" onclick="closeMap()">×</button>
    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3926.14552567832!2d105.95914837376765!3d10.249844868688417!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x310a82ce95555555%3A0x451cc8d95d6039f8!2zVHLGsOG7nW5nIMSQ4bqhaSBo4buNYyBTxrAgcGjhuqFtIEvhu7kgdGh14bqtdCBWxKluaCBMb25n!5e0!3m2!1svi!2s!4v1746160472342!5m2!1svi!2s" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
</div>

<script>
    function openMap() {
        document.querySelector('.map-popup').style.display = 'block';
        document.querySelector('.map-overlay').style.display = 'block';
    }

    function closeMap() {
        document.querySelector('.map-popup').style.display = 'none';
        document.querySelector('.map-overlay').style.display = 'none';
    }
</script>

</body>
</html>
