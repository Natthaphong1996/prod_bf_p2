<?php
// เริ่มต้น session
session_start();

// เรียกใช้ไฟล์ config_db.php สำหรับการเชื่อมต่อฐานข้อมูล
require_once 'config_db.php';

// ตรวจสอบว่ามี ID ลูกค้า (customer_id) ถูกส่งมาหรือไม่
if (isset($_GET['id'])) {
    $customer_id = $_GET['id'];

    // สร้างคำสั่ง SQL สำหรับการลบข้อมูลลูกค้า
    $sql = "DELETE FROM customer WHERE customer_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $customer_id);

    // ดำเนินการลบข้อมูล
    if ($stmt->execute()) {
        // ลบสำเร็จ เปลี่ยนเส้นทางไปยังหน้า customers_list.php
        header('Location: customers_list.php');
        exit();
    } else {
        // ถ้ามีข้อผิดพลาดในการลบ
        echo "ไม่สามารถลบข้อมูลลูกค้าได้";
    }
} else {
    // ถ้าไม่มี ID ลูกค้า
    echo "ไม่พบข้อมูลลูกค้า";
}

$stmt->close();
$conn->close();
?>
