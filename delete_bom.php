<?php
session_start();
require_once 'config_db.php'; // เรียกใช้ไฟล์สำหรับเชื่อมต่อฐานข้อมูล

// ตรวจสอบว่ามีการส่งค่า ID เข้ามาหรือไม่
if (isset($_GET['id'])) {
    $bom_id = $_GET['id'];

    // SQL สำหรับลบข้อมูล BOM
    $sql = "DELETE FROM bom WHERE bom_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $bom_id);

    if ($stmt->execute()) {
        // ลบสำเร็จ
        $_SESSION['message'] = "BOM ถูกลบเรียบร้อยแล้ว!";
        header("Location: bom_list.php");
        exit();
    } else {
        // ลบล้มเหลว
        $_SESSION['error'] = "เกิดข้อผิดพลาดในการลบ BOM!";
        header("Location: bom_list.php");
        exit();
    }

    $stmt->close();
} else {
    // ถ้าไม่มีการส่ง ID
    $_SESSION['error'] = "ไม่พบ BOM ID!";
    header("Location: bom_list.php");
    exit();
}
?>
