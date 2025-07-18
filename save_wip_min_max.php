<?php
// save_wip_min_max.php
// ภาษา: PHP
// หน้าที่: บันทึกค่า min และ max สำหรับ part_id ใหม่ใน wip_inventory

session_start();
require_once __DIR__ . '/config_db.php'; // เชื่อมต่อฐานข้อมูล

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบหรือไม่
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "กรุณาเข้าสู่ระบบเพื่อดำเนินการ";
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $part_id = $_POST['part_id'] ?? '';
    $min_qty = $_POST['min_qty'] ?? '';
    $max_qty = $_POST['max_qty'] ?? '';

    // Input Validation
    if (empty($part_id) || !is_numeric($min_qty) || !is_numeric($max_qty) || $min_qty < 0 || $max_qty < 0) {
        $_SESSION['error_message'] = "ข้อมูลไม่ถูกต้องหรือไม่ครบถ้วน กรุณาตรวจสอบค่า Min/Max";
        header("Location: cutting_job_list.php");
        exit();
    }

    $part_id_db = $conn->real_escape_string($part_id);
    $min_qty_db = (int)$min_qty;
    $max_qty_db = (int)$max_qty;

    // [Cyber Security] ใช้ Prepared Statement เพื่อป้องกัน SQL Injection
    // ตรวจสอบว่า part_id นี้มีอยู่แล้วใน wip_inventory หรือไม่ (ป้องกันการบันทึกซ้ำ)
    $sql_check = "SELECT part_id FROM wip_inventory WHERE part_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("s", $part_id_db);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        // ถ้ามีอยู่แล้ว ให้อัปเดต (หรือแจ้งเตือนว่ามีอยู่แล้ว)
        // ในกรณีนี้ เราจะอัปเดตค่า min/max แทนการแจ้งเตือน error
        $sql_update = "UPDATE wip_inventory SET min = ?, max = ? WHERE part_id = ?";
        $stmt_update = $conn->prepare($sql_update);
        if ($stmt_update) {
            $stmt_update->bind_param("iis", $min_qty_db, $max_qty_db, $part_id_db);
            if ($stmt_update->execute()) {
                $_SESSION['success_message'] = "อัปเดตค่า Min/Max สำหรับชิ้นงาน {$part_id} เรียบร้อยแล้ว!";
            } else {
                $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการอัปเดตค่า Min/Max: " . $stmt_update->error;
            }
            $stmt_update->close();
        } else {
            $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL (UPDATE Min/Max): " . $conn->error;
        }
    } else {
        // ถ้ายังไม่มี ให้ INSERT เข้าไป
        $sql_insert = "INSERT INTO wip_inventory (part_id, quantity, min, max) VALUES (?, 0, ?, ?)"; // quantity เริ่มต้นเป็น 0
        $stmt_insert = $conn->prepare($sql_insert);
        if ($stmt_insert) {
            $stmt_insert->bind_param("sii", $part_id_db, $min_qty_db, $max_qty_db);
            if ($stmt_insert->execute()) {
                $_SESSION['success_message'] = "บันทึกค่า Min/Max สำหรับชิ้นงาน {$part_id} เรียบร้อยแล้ว! (Quantity เริ่มต้นเป็น 0)";
            } else {
                $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการบันทึกค่า Min/Max: " . $stmt_insert->error;
            }
            $stmt_insert->close();
        } else {
            $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL (INSERT Min/Max): " . $conn->error;
        }
    }
    $stmt_check->close();

    $conn->close();
    header("Location: cutting_job_list.php");
    exit();

} else {
    header("Location: cutting_job_list.php");
    exit();
}
?>
