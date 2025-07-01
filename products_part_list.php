<?php
session_start();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการส่วนประกอบ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            margin-bottom: 20px;
        }
        .container {
            margin-top: 50px;
        }
        .pagination {
            justify-content: center;
        }
        .highlighted-title {
            background-color: #25d7fd; /* สีพื้นหลังที่ต้องการ */
            padding: 10px;
            border-radius: 5px;
            color: #333; /* สีตัวหนังสือ */
            text-align: center; /* จัดข้อความให้อยู่ตรงกลาง */
            font-weight: bold;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container">
<h2 class="text-center mb-4">รายการส่วนประกอบ</h2>
    <!-- ฟอร์มสำหรับค้นหา -->
    <div class="d-flex justify-content-center mb-4">
        <input class="form-control me-2" type="search" id="search-input" placeholder="ค้นหา" value="">
    </div>

    <!-- แสดงข้อมูลชิ้นส่วนที่โหลดจาก search_parts.php -->
    <div id="results" class="row justify-content-center">
        <!-- ผลลัพธ์จะถูกแทรกตรงนี้โดย AJAX -->
    </div>

    <!-- Pagination -->
    <div id="pagination" class="mt-4">
        <!-- Pagination จะถูกแทรกตรงนี้โดย AJAX -->
    </div>
</div>

<script>
$(document).ready(function() {
    // ฟังก์ชันสำหรับโหลดข้อมูลชิ้นส่วน
    function loadParts(searchQuery = '', page = 1) {
        $.ajax({
            url: 'search_parts.php',
            method: 'GET',
            data: {
                search: searchQuery,
                page: page
            },
            dataType: 'json',
            success: function(response) {
                $('#results').html(response.parts_html);
                $('#pagination').html(response.pagination_html);
            }
        });
    }

    // โหลดข้อมูลครั้งแรกเมื่อเปิดหน้า
    loadParts();

    // เมื่อผู้ใช้พิมพ์ในช่องค้นหา
    $('#search-input').on('input', function() {
        var searchQuery = $(this).val();
        loadParts(searchQuery);
    });

    // เมื่อผู้ใช้คลิก pagination
    $(document).on('click', '.page-link', function(e) {
        e.preventDefault();
        var searchQuery = $('#search-input').val();
        var page = $(this).data('page');
        loadParts(searchQuery, page);
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
