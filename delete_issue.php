<?php
session_start();
include 'config_db.php'; // เชื่อมต่อกับฐานข้อมูล

// ตรวจสอบว่าได้รับค่าจาก URL หรือไม่
if (isset($_GET['issue_id'])) {
    $issue_id = $_GET['issue_id'];

    // SQL สำหรับการลบข้อมูล
    $sql = "UPDATE wood_issue SET issue_status = 'ยกเลิก' WHERE issue_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $issue_id);  // "s" คือ type ของ $issue_id ที่เป็น string
    $stmt->execute();  // ทำการ execute คำสั่ง


    // ดำเนินการลบข้อมูล
    if ($stmt->execute()) {
        // ถ้าลบสำเร็จ, รีไดเรกต์ไปยังหน้า planning_order.php
        header("Location: planning_order.php?status=deleted");
        exit();
    } else {
        // ถ้าเกิดข้อผิดพลาด
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
} else {
    // ถ้าไม่ได้รับค่าจาก URL
    echo "ไม่พบข้อมูลสำหรับการลบ";
}

$conn->close();
?>
