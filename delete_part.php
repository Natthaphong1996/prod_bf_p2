<?php
session_start();
require_once 'config_db.php'; // เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล

// ตรวจสอบว่ามีการส่ง ID ของชิ้นส่วนมา
if (isset($_GET['id'])) {
    $part_id = $_GET['id'];

    // ลบข้อมูลชิ้นส่วนจากฐานข้อมูล
    $sql = "DELETE FROM part_list WHERE part_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $part_id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "ลบข้อมูลชิ้นส่วนเรียบร้อยแล้ว!";
    } else {
        $_SESSION['error'] = "เกิดข้อผิดพลาดในการลบข้อมูล!";
    }

    header('Location: products_part_list.php');
    exit();
} else {
    $_SESSION['error'] = "ไม่มี ID ของชิ้นส่วนที่ต้องการลบ!";
    header('Location: products_part_list.php');
    exit();
}
?>
