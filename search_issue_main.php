<?php
// เชื่อมต่อไฟล์ฐานข้อมูล
include 'config_db.php';

// รับค่าค้นหาจากฟอร์ม
$job_id_search = isset($_POST['job_id_search']) ? $_POST['job_id_search'] : '';
$product_code_search = isset($_POST['product_code_search']) ? $_POST['product_code_search'] : '';

// สร้างเงื่อนไขสำหรับการค้นหาตามหมายเลข JOB และ ITEM CODE FG
$where_clause = "";
if (!empty($job_id_search)) {
    $where_clause .= " AND job_id LIKE ?";
}
if (!empty($product_code_search)) {
    $where_clause .= " AND product_code LIKE ?";
}

// ดึงข้อมูลจากตาราง wood_issue พร้อมกรองข้อมูลตามฟิลด์ที่กรอกค้นหามา
$sql_search = "SELECT * FROM wood_issue WHERE 1=1 $where_clause ORDER BY creation_date DESC";
$stmt = $conn->prepare($sql_search);

// เตรียมค่าพารามิเตอร์
$params = [];
if (!empty($job_id_search)) {
    $params[] = "%$job_id_search%";
}
if (!empty($product_code_search)) {
    $params[] = "%$product_code_search%";
}

if (count($params) > 0) {
    // ผูกพารามิเตอร์กับ SQL query
    $stmt->bind_param(str_repeat("s", count($params)), ...$params);
}

$stmt->execute();
$result_search = $stmt->get_result();

// ตรวจสอบข้อผิดพลาดในการ query
if ($stmt->error) {
    echo "Error: " . $stmt->error;
    exit;
}

return $result_search; // ส่งผลลัพธ์กลับไปยังไฟล์หลัก
?>
