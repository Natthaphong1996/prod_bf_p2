<?php
session_start();
require_once 'config_db.php'; // เรียกใช้ไฟล์สำหรับเชื่อมต่อฐานข้อมูล

// ตรวจสอบว่ามีการส่งค่า id เข้ามาหรือไม่
if (isset($_GET['id'])) {
    $prod_id = $_GET['id'];

    // เตรียมคำสั่ง SQL สำหรับลบสินค้าตาม prod_id
    $sql = "DELETE FROM prod_list WHERE prod_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $prod_id);

    // ดำเนินการลบข้อมูล
    if ($stmt->execute()) {
        // ถ้าลบสำเร็จ เปลี่ยนเส้นทางกลับไปยัง products_list.php พร้อมกับข้อความแจ้งเตือน
        $_SESSION['success'] = "ลบสินค้าสำเร็จ!";
        header("Location: products_list.php");
        exit();
    } else {
        // ถ้าลบไม่สำเร็จ แจ้งข้อความแสดงข้อผิดพลาด
        $_SESSION['error'] = "เกิดข้อผิดพลาดในการลบสินค้า";
        header("Location: products_list.php");
        exit();
    }

    $stmt->close();
} else {
    // ถ้าไม่มีการส่งค่า id เข้ามา ให้แสดงข้อความแสดงข้อผิดพลาด
    $_SESSION['error'] = "ไม่พบข้อมูลสินค้าที่ต้องการลบ";
    header("Location: products_list.php");
    exit();
}

$conn->close();
?>
