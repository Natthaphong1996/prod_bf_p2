<?php
header('Content-Type: text/html; charset=UTF-8');
include 'config_db.php';

// ตั้งเวลาเริ่มต้นให้เป็นเวลาของประเทศไทย
date_default_timezone_set('Asia/Bangkok');

// ตั้งค่าการเชื่อมต่อฐานข้อมูลให้เป็น UTF-8 (ถ้ายังไม่ได้ตั้ง)
$conn->set_charset("utf8");

// รับค่าจากฟอร์ม
$issue_id = $_POST['issue_id'];
$issued_by = $_POST['issued_by'];
$issue_date = date('Y-m-d H:i:s'); // วันที่และเวลาปัจจุบันตามเวลาไทย
$issue_status = 'เบิกแล้ว'; // เปลี่ยนสถานะเป็นเบิกแล้ว

// อัปเดตข้อมูลในตาราง wood_issue
$query = "UPDATE wood_issue SET issued_by = ?, issue_date = ?, issue_status = ? WHERE issue_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("sssi", $issued_by, $issue_date, $issue_status, $issue_id);

if ($stmt->execute()) {
    echo "<script>
            alert('บันทึกการเบิกเรียบร้อย');
            window.location.href = 'issue_list.php';
          </script>";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
