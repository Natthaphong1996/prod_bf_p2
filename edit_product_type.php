<?php
require_once('config_db.php');

// รับข้อมูลจากฟอร์ม
$type_id = $_POST['type_id'];
$type_code = $_POST['type_code'];
$type_name = $_POST['type_name'];

// สร้างคำสั่ง SQL สำหรับการอัพเดต
$query = "UPDATE prod_type SET type_code = ?, type_name = ? WHERE type_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ssi", $type_code, $type_name, $type_id);

// ตรวจสอบการอัพเดต
if ($stmt->execute()) {
    echo "ข้อมูลประเภทสินค้าได้ถูกอัพเดตเรียบร้อยแล้ว";
} else {
    echo "ไม่สามารถอัพเดตข้อมูลได้";
}

$conn->close();
?>
