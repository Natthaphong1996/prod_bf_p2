<?php
session_start();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการประเภทชิ้นส่วน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <h2 class="text-center mb-4">รายการประเภทชิ้นส่วน</h2>

    <!-- ฟอร์มสำหรับค้นหา -->
    <form class="d-flex justify-content-center mb-4">
        <input class="form-control me-2" type="search" id="search-input" placeholder="ค้นหาโค้ดประเภท หรือ ชื่อประเภท">
    </form>

    <div id="results">
        <!-- ผลลัพธ์จากการค้นหาจะถูกแสดงที่นี่ -->
    </div>
</div>

<script>
    $(document).ready(function() {
        // โหลดข้อมูลประเภทชิ้นส่วนทั้งหมดเมื่อเปิดหน้าเว็บ
        $.ajax({
            url: 'search_part_types.php', // ไฟล์ที่ประมวลผลการค้นหา
            method: 'GET',
            success: function(response) {
                $('#results').html(response); // แสดงผลลัพธ์การค้นหาหรือข้อมูลทั้งหมด
            }
        });

        // เมื่อผู้ใช้พิมพ์ในช่องค้นหา
        $('#search-input').on('input', function() {
            var searchQuery = $(this).val();
            $.ajax({
                url: 'search_part_types.php', // ไฟล์ที่ประมวลผลการค้นหา
                method: 'GET',
                data: { search: searchQuery },
                success: function(response) {
                    $('#results').html(response); // แสดงผลลัพธ์การค้นหา
                }
            });
        });
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
