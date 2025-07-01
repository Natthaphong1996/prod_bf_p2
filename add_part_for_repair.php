<?php
session_start();
include 'config_db.php'; // เชื่อมต่อกับฐานข้อมูล

// รับค่า job_code จาก URL
$job_code = isset($_GET['job_code']) ? $_GET['job_code'] : '';
$part_data = isset($_POST['part_data']) ? $_POST['part_data'] : ''; // ข้อมูลที่ส่งมา

// ตรวจสอบว่า job_code มีค่าหรือไม่
if ($job_code) {
    // ดึง repair_id ล่าสุดจากฐานข้อมูล
    $sql = "SELECT repair_id FROM repair_issue WHERE job_id = ? ORDER BY repair_id DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $job_code);
    $stmt->execute();
    $result = $stmt->get_result();
    $last_repair = $result->fetch_assoc();

    // ถ้ามี repair_id ล่าสุด
    if ($last_repair) {
        // เพิ่มหมายเลข repair_id ต่อท้ายจากค่าเดิม
        $repair_id = $last_repair['repair_id'] + 1;
    } else {
        // ถ้าไม่มีให้สร้าง repair_id ใหม่
        $repair_id = $job_code . '-1'; // สร้าง repair_id แรก
    }

    // แปลงข้อมูล part_quantity_reason เป็น JSON
    $part_quantity_reason = json_encode($part_data);

    // คำสั่ง SQL สำหรับบันทึกข้อมูล
    $sql_insert = "INSERT INTO repair_issue (repair_id, job_id, part_quantity_reason, created_at)
                   VALUES (?, ?, ?, NOW())";
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param('iss', $repair_id, $job_code, $part_quantity_reason);
    
    if ($stmt_insert->execute()) {
        echo "บันทึกข้อมูลสำเร็จ! Repair ID: $repair_id";
    } else {
        echo "เกิดข้อผิดพลาดในการบันทึกข้อมูล!";
    }
} else {
    echo "ไม่พบข้อมูล job_code";
}

$conn->close();
?>
