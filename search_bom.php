<?php
require_once 'config_db.php';

$search = isset($_GET['search']) ? $_GET['search'] : '';
$cards_per_page = 100; // จำนวนข้อมูลต่อหน้า
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $cards_per_page;

// นับจำนวนข้อมูลทั้งหมดที่ตรงกับเงื่อนไขการค้นหา
$sql_count = "SELECT COUNT(*) as total FROM bom 
              LEFT JOIN prod_list ON bom.prod_id = prod_list.prod_id
              LEFT JOIN customer ON prod_list.customer_id = customer.customer_id
              WHERE prod_list.prod_code LIKE ? 
              OR customer.customer_name LIKE ? 
              OR prod_list.prod_type LIKE ? 
              OR prod_list.prod_partno LIKE ?";
$stmt_count = $conn->prepare($sql_count);
$search_param = "%" . $search . "%";
$stmt_count->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$total_rows = $result_count->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $cards_per_page);

// ดึงข้อมูลสำหรับหน้าแต่ละหน้า
$sql = "SELECT bom.bom_id, prod_list.prod_type, prod_list.prod_code, prod_list.prod_partno, prod_list.prod_description, customer.customer_name
        FROM bom 
        LEFT JOIN prod_list ON bom.prod_id = prod_list.prod_id
        LEFT JOIN customer ON prod_list.customer_id = customer.customer_id
        WHERE prod_list.prod_code LIKE ? 
        OR customer.customer_name LIKE ? 
        OR prod_list.prod_type LIKE ? 
        OR prod_list.prod_partno LIKE ?
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssii", $search_param, $search_param, $search_param, $search_param, $cards_per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();

// ผลลัพธ์ JSON ที่ส่งกลับไปยังฝั่ง HTML
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

// ส่งผลลัพธ์ในรูปแบบ JSON พร้อมข้อมูลจำนวนหน้าทั้งหมด
echo json_encode([
    'data' => $data,
    'total_pages' => $total_pages
]);
?>
