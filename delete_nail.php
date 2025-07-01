<?php
// delete_nail.php

session_start();
require_once 'config_db.php'; // เรียกใช้ไฟล์สำหรับเชื่อมต่อฐานข้อมูล

// --- Security: ตรวจสอบการล็อกอิน ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    // หากยังไม่ล็อกอิน ให้หยุดการทำงานและอาจจะส่งกลับไปหน้า login
    // ในที่นี้เราจะหยุดการทำงานเพื่อความปลอดภัย
    http_response_code(403); // Forbidden
    echo "Access Denied.";
    exit;
}

// --- รับค่าและตรวจสอบ ID ที่ส่งมา ---
// แก้ไขให้รับ 'id' แทน 'code'
$nail_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

if ($nail_id) {
    // --- ลบข้อมูลออกจากฐานข้อมูลโดยใช้ ID ---
    // ใช้ Primary Key (nail_id) ในการลบซึ่งแม่นยำและปลอดภัยกว่า
    $sql = "DELETE FROM nail WHERE nail_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        // bind_param เป็น integer 'i'
        $stmt->bind_param("i", $nail_id);

        if ($stmt->execute()) {
            // ตรวจสอบว่ามีแถวที่ถูกลบจริงหรือไม่
            if ($stmt->affected_rows > 0) {
                // ใช้ key 'success_message' ให้ตรงกับหน้า list
                $_SESSION['success_message'] = "ลบข้อมูลตะปูสำเร็จ!";
            } else {
                $_SESSION['error_message'] = "ไม่พบข้อมูลที่ต้องการลบ หรืออาจถูกลบไปแล้ว";
            }
        } else {
            $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการลบข้อมูล: " . $conn->error;
        }
        $stmt->close();
    }
} else {
    $_SESSION['error_message'] = "ไม่ได้ระบุ ID ของข้อมูลที่ต้องการลบ";
}

$conn->close();

// กลับไปยังหน้ารายการ
header("Location: nail_list.php");
exit();
?>
