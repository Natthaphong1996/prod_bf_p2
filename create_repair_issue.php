<?php
include 'config_db.php';

session_start();

// ตรวจสอบว่า session มีค่า user_id หรือไม่
if (!isset($_SESSION['user_id'])) {
    echo "User not logged in!";
    exit;
}

// ดึงข้อมูล `thainame` จาก `prod_user` โดยใช้ `user_id` ที่ได้จาก session
$user_id = $_SESSION['user_id']; // ดึง user_id จาก session

$sql_user = "SELECT thainame FROM prod_user WHERE user_id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id); // ใช้ user_id จาก session
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user = $result_user->fetch_assoc();
$thainame = $user['thainame']; // เก็บชื่อผู้ใช้งาน

// รับค่าจากฟอร์ม
$job_id = $_POST['repair_job_id'];
$parts = $_POST['parts']; // รับข้อมูล Part

foreach ($parts as $part_code => $details) {
    // ตรวจสอบว่ามีข้อมูล quantity และ reason หรือไม่
    $quantity = isset($details['quantity']) ? intval($details['quantity']) : 0;
    $reason = isset($details['reason']) ? trim($details['reason']) : '';

    // กรองข้อมูล: ไม่บันทึกถ้า quantity เป็น 0 หรือ reason เป็นค่าว่าง
    if ($quantity > 0 && !empty($reason)) {
        // บันทึกข้อมูลลงในตาราง repair_issue พร้อมกับ `create_by` เป็นชื่อผู้ใช้จาก session
        $query = "INSERT INTO repair_issue (job_id, part_code, quantity, reason, issue_date, create_by)
                  VALUES (?, ?, ?, ?, NOW(), ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssiss", $job_id, $part_code, $quantity, $reason, $thainame);
        $stmt->execute();
    }
}

// ปิดการเชื่อมต่อฐานข้อมูล
if (isset($stmt)) {
    $stmt->close();
}
$conn->close();

// กลับไปยังหน้า issue_list.php หรือแสดงข้อความสำเร็จ
header("Location: issue_list.php");
exit();
?>
