<?php
include 'config_db.php'; // เชื่อมต่อกับฐานข้อมูล

// รับค่า part_id จาก URL
$part_id = isset($_GET['part_id']) ? $_GET['part_id'] : '';

// ถ้ามีค่า part_id, ดึงข้อมูลจากตาราง part_list
if ($part_id) {
    // ค้นหาข้อมูลจากตาราง part_list โดยใช้ part_id
    $sql = "SELECT part_code, part_type, part_thickness, part_width, part_length 
            FROM part_list 
            WHERE part_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $part_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // ดึงข้อมูลของ part
        $part = $result->fetch_assoc();
        echo json_encode($part);  // ส่งข้อมูลเป็น JSON
    } else {
        echo json_encode([]);  // ถ้าไม่พบข้อมูลให้ส่งเป็น array ว่าง
    }
} else {
    echo json_encode([]);  // ถ้าไม่มี part_id ให้ส่งเป็น array ว่าง
}

$conn->close();
?>
