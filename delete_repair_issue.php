<?php
// เชื่อมต่อฐานข้อมูล
include 'config_db.php';

// ตรวจสอบว่าได้ส่ง repair_issue_id มาหรือไม่
if (isset($_POST['repair_issue_id'])) {
    $repair_id = $_POST['repair_issue_id'];

    // คำสั่ง SQL สำหรับลบข้อมูลจากตาราง repair_issue
    $sql = "UPDATE repair_issue SET status = 'ยกเลิก' WHERE repair_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $repair_id);  // "s" คือ type ของ $repair_id ที่เป็น string
    $stmt->execute();  // ทำการ execute คำสั่ง

    
    // เตรียมคำสั่ง SQL
    if ($stmt = $conn->prepare($sql)) {
        // ผูกพารามิเตอร์
        $stmt->bind_param("s", $repair_id);

        // Execute
        if ($stmt->execute()) {
            echo "ลบข้อมูลสำเร็จ";
        } else {
            echo "ไม่สามารถลบข้อมูลได้";
        }

        // ปิดคำสั่ง SQL
        $stmt->close();
    }
}

// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();
?>
