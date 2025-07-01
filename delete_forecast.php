<?php
session_start();
require_once 'config_db.php'; // เรียกใช้ไฟล์สำหรับการเชื่อมต่อฐานข้อมูล

// ตรวจสอบว่ามีการส่งค่า forecast_id ผ่าน URL หรือไม่
if (!isset($_GET['id'])) {
    // ถ้าไม่มี forecast_id ให้แสดงข้อความและหยุดการทำงาน
    $_SESSION['error'] = "ไม่พบข้อมูลการคาดการณ์ที่ต้องการลบ";
    header('Location: forecast.php');
    exit();
}

$forecast_id = $_GET['id'];

// เตรียมคำสั่ง SQL สำหรับลบข้อมูลการคาดการณ์
$sql = "DELETE FROM forecast WHERE forecast_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $forecast_id);

// ดำเนินการลบข้อมูล
if ($stmt->execute()) {
    $_SESSION['success'] = "ลบข้อมูลการคาดการณ์สำเร็จ!";
} else {
    $_SESSION['error'] = "เกิดข้อผิดพลาดในการลบข้อมูล";
}

// เปลี่ยนเส้นทางกลับไปยังหน้า forecast.php
header('Location: forecast.php');
exit();

?>
