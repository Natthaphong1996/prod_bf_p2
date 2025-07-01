<?php
session_start();
require_once 'config_db.php'; // เรียกใช้ไฟล์สำหรับเชื่อมต่อฐานข้อมูล

// กำหนดจำนวนการ์ดต่อหน้า (ใช้เมื่อไม่มีการค้นหา)
$cards_per_page = 30;

// ตรวจสอบหน้าที่ผู้ใช้เลือก
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $cards_per_page;

// SQL Query สำหรับดึงข้อมูลสินค้ารวมถึงชื่อลูกค้าจากฐานข้อมูล พร้อมการแบ่ง pagination
$sql = "SELECT p.prod_id, p.prod_code, p.prod_type, p.prod_partno, p.prod_description, c.customer_name 
        FROM prod_list p
        LEFT JOIN customer c ON p.customer_id = c.customer_id
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $cards_per_page, $offset);

// หาจำนวนทั้งหมดของการ์ดสำหรับการแบ่ง pagination
$total_sql = "SELECT COUNT(*) FROM prod_list";
$total_result = $conn->query($total_sql);
$total_cards = $total_result->fetch_row()[0];
$total_pages = ceil($total_cards / $cards_per_page);

$stmt->execute();
$result = $stmt->get_result();

// ฟังก์ชันแสดง pagination
function render_pagination($page, $total_pages) {
    $pagination_range = 2; // จำนวนหน้าแสดงรอบๆ หน้าเลือก
    $pagination_max = 5;   // จำนวนหน้าเริ่มต้นที่แสดงก่อน "..."

    echo '<nav><ul class="pagination justify-content-center">';

    // ปุ่ม Previous
    if ($page > 1) {
        echo '<li class="page-item"><a class="page-link" href="?page=' . ($page - 1) . '">ก่อนหน้า</a></li>';
    }

    // แสดงหน้าแรกถึงหน้า 5
    for ($i = 1; $i <= min($pagination_max, $total_pages); $i++) {
        echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '"><a class="page-link" href="?page=' . $i . '">' . $i . '</a></li>';
    }

    // แสดง ... ถ้าหากยังไม่ถึงหน้าสุดท้าย
    if ($page > $pagination_max + $pagination_range) {
        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
    }

    // แสดงหน้าที่อยู่ใกล้หน้าปัจจุบัน
    for ($i = max($pagination_max + 1, $page - $pagination_range); $i <= min($total_pages, $page + $pagination_range); $i++) {
        if ($i > $pagination_max) {
            echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '"><a class="page-link" href="?page=' . $i . '">' . $i . '</a></li>';
        }
    }

    // แสดง ... ถ้าหากมีหน้าเกินช่วงแสดง
    if ($page + $pagination_range < $total_pages) {
        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
    }

    // แสดงหน้าสุดท้าย
    if ($page + $pagination_range < $total_pages || $page < $total_pages) {
        echo '<li class="page-item ' . ($total_pages == $page ? 'active' : '') . '"><a class="page-link" href="?page=' . $total_pages . '">' . $total_pages . '</a></li>';
    }

    // ปุ่ม Next
    if ($page < $total_pages) {
        echo '<li class="page-item"><a class="page-link" href="?page=' . ($page + 1) . '">ถัดไป</a></li>';
    }

    echo '</ul></nav>';
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการสินค้า</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card-custom {
            margin-bottom: 20px;
            padding: 15px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }
        .card-header-custom {
            font-weight: bold;
            background-color: #f8f9fa;
            padding: 10px;
            border-bottom: 1px solid #ddd;
            text-align: center;
        }
        .card-body-custom {
            padding: 10px;
        }
        .container {
            margin-top: 50px;
        }
        .pagination {
            justify-content: center;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container">
    <h2 class="text-center mb-4">รายการสินค้า</h2>

    <!-- ฟอร์มสำหรับค้นหา -->
    <div class="d-flex justify-content-center mb-4">
        <input class="form-control me-2" type="search" id="search-input" placeholder="ค้นหา Product Code, ชื่อลูกค้า หรือ ประเภทสินค้า">
    </div>

    <div id="results" class="row justify-content-center">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="col-md-4 mb-4 product-card"> <!-- 3 การ์ดต่อแถว -->
                    <div class="card card-custom h-100">
                        <div class="card-header-custom">Product Code: <?php echo $row['prod_code']; ?></div>
                        <div class="card-body-custom">
                            <p><strong>Customer:</strong> <?php echo $row['customer_name']; ?></p>
                            <p><strong>Type:</strong> <?php echo $row['prod_type']; ?></p>
                            <p><strong>Part No:</strong> <?php echo $row['prod_partno']; ?></p>
                            <p><strong>Description:</strong> <?php echo $row['prod_description']; ?></p>
                        </div>
                        <!-- ปุ่มการกระทำ -->
                        <div class="btn-group" role="group">
                            <a href="edit_product.php?id=<?php echo $row['prod_id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                            <a href="delete_product.php?id=<?php echo $row['prod_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this product?');">Delete</a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <p class="text-center">ไม่พบข้อมูลสินค้า</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- แสดง pagination -->
    <div id="pagination">
        <?php render_pagination($page, $total_pages); ?>
    </div>
</div>

<script>
    $(document).ready(function() {
        // ส่งคำค้นหาไปที่ search_products.php ผ่าน AJAX
        $("#search-input").on("keyup", function() {
            const searchValue = $(this).val();
            $.ajax({
                url: "search_products.php",
                type: "GET",
                data: { search: searchValue },
                success: function(data) {
                    $("#results").html(data); // แสดงผลลัพธ์การค้นหาในรูปแบบการ์ด
                    $("#pagination").hide(); // ซ่อน pagination เมื่อค้นหา
                }
            });
        });
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();
?>
