<?php
session_start(); // เริ่มต้น session
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าแรก</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .hero-section {
            background-color: #f8f9fa;
            padding: 60px 0;
            text-align: center;
        }
        .news-section {
            padding: 40px 0;
        }
        .news-card {
            transition: transform 0.3s;
        }
        .news-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>

<!-- Navbar -->
<?php include 'navbar.php'; ?> <!-- เพิ่ม Navbar ตรงนี้ -->

<!-- <div class="alert alert-danger text-center" role="alert">
    <strong>ประกาศแจ้งเตือน:</strong> วันที่ 26/03/2025 เวลา 13:30 เป็นต้นไป จะมีการปิดระบบเพื่อ UPDATE ซึ่งจะทำให้ไม่สามารถใช้งานระบบได้จนถึง 17:00 หรือจนกว่าจะมีประกาศเพิ่มเติม
</div> -->

<!-- Hero Section -->
<div class="hero-section">
    <div class="container">
        <h1>ยินดีต้อนรับสู่ระบบจัดการ</h1>
        <p class="lead">ระบบจัดเก็บข้อมูลการผลิตช่วยเพิ่มประสิทธิภาพ ลดข้อผิดพลาด และติดตามสถานะการผลิตได้แบบเรียลไทม์</p>
    </div>
</div>

<!-- News Section -->
<div class="news-section">
    <div class="container">
        <h2 class="text-center mb-5">ข่าวสารและประกาศ</h2>
        <div class="row">
            <!-- ข่าวสาร 1 -->
            <div class="col-md-4">
                <div class="card news-card">
                    <img src="https://via.placeholder.com/300x200" class="card-img-top" alt="ข่าวสาร 1">
                    <div class="card-body">
                        <h5 class="card-title">ข่าวสารล่าสุด</h5>
                        <p class="card-text">รายละเอียดข่าวสารที่ต้องการแจ้งให้ผู้ใช้งานทราบ...</p>
                        <a href="#" class="btn btn-primary">อ่านเพิ่มเติม</a>
                    </div>
                </div>
            </div>
            <!-- ข่าวสาร 2 -->
            <div class="col-md-4">
                <div class="card news-card">
                    <img src="https://via.placeholder.com/300x200" class="card-img-top" alt="ข่าวสาร 2">
                    <div class="card-body">
                        <h5 class="card-title">ประกาศสำคัญ</h5>
                        <p class="card-text">เนื้อหาเกี่ยวกับประกาศสำคัญที่ผู้ใช้ต้องทราบ...</p>
                        <a href="#" class="btn btn-primary">อ่านเพิ่มเติม</a>
                    </div>
                </div>
            </div>
            <!-- ข่าวสาร 3 -->
            <div class="col-md-4">
                <div class="card news-card">
                    <img src="https://via.placeholder.com/300x200" class="card-img-top" alt="ข่าวสาร 3">
                    <div class="card-body">
                        <h5 class="card-title">กิจกรรมล่าสุด</h5>
                        <p class="card-text">ข้อมูลเกี่ยวกับกิจกรรมหรืออีเวนต์ล่าสุด...</p>
                        <a href="#" class="btn btn-primary">อ่านเพิ่มเติม</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="bg-light py-4">
    <div class="container text-center">
        <p>© 2025 Production. สงวนลิขสิทธิ์ Siamkyohwa Seisakusho Co., Ltd</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

</body>
</html>
