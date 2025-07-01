<?php
include 'config_db.php'; // เชื่อมต่อกับฐานข้อมูล

// รับค่าจากการค้นหา
$search = isset($_GET['search']) ? $_GET['search'] : '';

// ค้นหาค่าจากตาราง prod_list ที่ตรงกับการพิมพ์ในฟิลด์ prod_code, prod_partno หรือ code_cus_size
$sql = "SELECT prod_id,prod_code, prod_partno, code_cus_size 
        FROM prod_list 
        WHERE prod_code LIKE '%$search%' 
        OR prod_partno LIKE '%$search%' 
        OR code_cus_size LIKE '%$search%' 
        LIMIT 10";

$result = $conn->query($sql);

// สร้างอาเรย์เพื่อเก็บผลลัพธ์
$product_codes = [];
while ($row = $result->fetch_assoc()) {
    $product_codes[] = $row;
}

// ส่งข้อมูลกลับในรูปแบบ JSON
echo json_encode($product_codes);

// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();
?>