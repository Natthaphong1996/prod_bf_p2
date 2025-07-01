<?php
include 'config_db.php'; // เชื่อมต่อกับฐานข้อมูล

// ตรวจสอบว่ามีการส่งค่า product_code หรือไม่
if (isset($_GET['product_code'])) {
    $product_code = $_GET['product_code'];

    // คำสั่ง SQL สำหรับดึงข้อมูล part ที่เกี่ยวข้องกับ product_code
    $sql = "SELECT part_code, part_type, part_thickness, part_width, part_length 
            FROM part_list 
            WHERE product_code = ?";
    
    // เตรียมคำสั่ง SQL
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $product_code);
    $stmt->execute();
    
    // รับผลลัพธ์จากคำสั่ง SQL
    $result = $stmt->get_result();
    $parts = [];
    
    // แปลงผลลัพธ์เป็น Array
    while ($row = $result->fetch_assoc()) {
        $parts[] = $row;
    }

    // ส่งข้อมูลกลับเป็น JSON
    echo json_encode($parts);
} else {
    echo json_encode([]);
}

$conn->close();
?>
