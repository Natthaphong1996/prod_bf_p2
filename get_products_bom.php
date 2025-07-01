<?php
require_once 'config_db.php'; // เรียกใช้ไฟล์สำหรับเชื่อมต่อฐานข้อมูล

// ตรวจสอบว่ามี customer_code ที่ส่งมาหรือไม่
if (isset($_GET['customer_code']) && !empty($_GET['customer_code'])) {
    $customer_code = $_GET['customer_code'];
    
    // ดึงข้อมูลสินค้าเฉพาะที่ตรงกับ customer_code นั้น พร้อมกับ partno และข้อมูลลูกค้า
    $sql = "SELECT DISTINCT p.prod_code, p.prod_partno, c.customer_code, c.customer_name 
            FROM prod_list p
            JOIN bom b ON p.prod_code = b.prod_code
            JOIN customer c ON p.customer_id = c.customer_id
            WHERE c.customer_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $customer_code);
} else {
    // ดึงข้อมูลสินค้าทั้งหมดเมื่อไม่มีการเลือก customer_code พร้อมกับ partno และข้อมูลลูกค้า
    $sql = "SELECT DISTINCT p.prod_code, p.prod_partno, c.customer_code, c.customer_name 
            FROM prod_list p
            JOIN bom b ON p.prod_code = b.prod_code
            JOIN customer c ON p.customer_id = c.customer_id";
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();

// แสดงรายการสินค้าในรูปแบบของ HTML <option> พร้อมข้อมูล customer_code และ customer_name
echo '<option value="">เลือกรหัสสินค้า</option>';
while ($row = $result->fetch_assoc()) {
    // แสดงในรูปแบบ "รหัสสินค้า - PartNo" และเพิ่มข้อมูล customer_code และ customer_name
    echo '<option value="' . htmlspecialchars($row['prod_code']) . '" 
                data-partno="' . htmlspecialchars($row['prod_partno']) . '" 
                data-customer-code="' . htmlspecialchars($row['customer_code']) . '" 
                data-customer-name="' . htmlspecialchars($row['customer_name']) . '">'
         . htmlspecialchars($row['prod_code']) . ' - ' . htmlspecialchars($row['prod_partno']) . '</option>';
}

$stmt->close();
$conn->close();
?>
