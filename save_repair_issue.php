<?php
// เชื่อมต่อฐานข้อมูล
include 'config_db.php';

// เริ่มต้น session
session_start();

// ตรวจสอบว่าเป็นการส่งข้อมูลด้วยวิธี POST หรือไม่
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // รับข้อมูลจากฟอร์ม
    $job_id = $_POST['job_id'];
    $want_receive = $_POST['want_receive'];

    // รับข้อมูลจากส่วนที่เป็น JSON (part_quantity_reason)
    $part_quantity_reason = $_POST['part_quantity_reason']; // ข้อมูล JSON ที่ส่งมา

    // ตรวจสอบข้อมูลที่ได้รับ
    if (empty($job_id) || empty($part_quantity_reason)) {
        echo "ข้อมูลไม่ครบถ้วน";
        exit;
    }

    // กำหนดสถานะเริ่มต้นเป็น "สั่งไม้"
    $status = "สั่งไม้";

    // ตรวจสอบ job_type จาก wood_issue สำหรับ job_id ที่รับมา
    $sql_wood = "SELECT job_type FROM wood_issue WHERE job_id = '$job_id'";
    $result_wood = $conn->query($sql_wood);
    if ($result_wood->num_rows > 0) {
        $row_wood = $result_wood->fetch_assoc();
        if ($row_wood['job_type'] == "งานพาเลทไม้อัด") {
            $status = "เบิกแล้ว";
        }
    }

    // ดึงค่า user_id จาก session
    $user_id = $_SESSION['user_id']; // สมมติว่า user_id ถูกเก็บใน session

    // ขั้นตอน 1: ค้นหาชื่อจริง (thainame) จาก prod_user
    $sql_user = "SELECT thainame FROM prod_user WHERE user_id = '$user_id'";
    $result_user = $conn->query($sql_user);

    $thainame = "-"; // กำหนดค่าเริ่มต้นหากไม่พบชื่อ
    if ($result_user->num_rows > 0) {
        $user_row = $result_user->fetch_assoc();
        $thainame = $user_row['thainame'];
    }

    // ขั้นตอน 2: ค้นหาหมายเลข repair_id ล่าสุดของ job_id นี้
    $sql_max_repair_id = "SELECT MAX(CAST(SUBSTRING_INDEX(repair_id, '-', -1) AS UNSIGNED)) AS max_sequence_number
                          FROM repair_issue 
                          WHERE job_id = '$job_id'";
    $result_max_repair_id = $conn->query($sql_max_repair_id);
    $sequence_number = 1;  // กำหนดเริ่มต้นหากไม่พบการบันทึกใดๆ
    if ($result_max_repair_id->num_rows > 0) {
        $row = $result_max_repair_id->fetch_assoc();
        $sequence_number = $row['max_sequence_number'] + 1;
    }

    // ขั้นตอน 3: สร้าง repair_id ใหม่ โดยใช้ job_id และ sequence_number
    $repair_id = $job_id . '-' . $sequence_number;

    // ตรวจสอบว่า repair_id นี้มีในฐานข้อมูลหรือไม่
    $sql_check_repair_id = "SELECT repair_id FROM repair_issue WHERE repair_id = '$repair_id'";
    $result_check = $conn->query($sql_check_repair_id);
    while ($result_check->num_rows > 0) {
        $sequence_number++;
        $repair_id = $job_id . '-' . $sequence_number;
        $result_check = $conn->query("SELECT repair_id FROM repair_issue WHERE repair_id = '$repair_id'");
    }

    // ขั้นตอน 4: บันทึกข้อมูลลงในตาราง repair_issue
    $sql_insert = "INSERT INTO repair_issue (repair_id, job_id, part_quantity_reason, want_receive, status, create_by)
                   VALUES ('$repair_id', '$job_id', '$part_quantity_reason', '$want_receive', '$status', '$thainame')";
    if ($conn->query($sql_insert) === TRUE) {
        echo "บันทึกข้อมูลสำเร็จ";
    } else {
        echo "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $conn->error;
    }

    // ปิดการเชื่อมต่อฐานข้อมูล
    $conn->close();
} else {
    echo "ไม่พบข้อมูลที่ส่งมา";
}
?>
