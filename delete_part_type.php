<?php
session_start();
require_once 'config_db.php'; // เรียกใช้ไฟล์สำหรับเชื่อมต่อฐานข้อมูล

if (isset($_GET['id'])) {
    $type_id = $_GET['id'];

    // ลบข้อมูลประเภทชิ้นส่วน
    $sql = "DELETE FROM part_type WHERE type_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $type_id);

    if ($stmt->execute()) {
        // หลังจากลบเสร็จแล้วให้เปลี่ยนหน้าไปที่ part_type_list.php
        header("Location: part_type_list.php");
        exit();
    } else {
        echo "เกิดข้อผิดพลาดในการลบข้อมูล!";
    }

    $stmt->close();
}

$conn->close();
?>
