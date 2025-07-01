<?php
// save_nail_usage.php

session_start();
require_once 'config_db.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => 'เกิดข้อผิดพลาดที่ไม่รู้จัก'
];

// --- Security & Validation ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    $response['message'] = 'Access Denied: คุณต้องเข้าสู่ระบบก่อน';
    echo json_encode($response);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

$issue_id = filter_input(INPUT_POST, 'issue_id', FILTER_VALIDATE_INT);
$user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$job_id = isset($_POST['job_id']) ? htmlspecialchars(trim($_POST['job_id'])) : null;
$nail_ids = isset($_POST['nails']) ? $_POST['nails'] : [];
$quantities = isset($_POST['quantity_issued']) ? $_POST['quantity_issued'] : [];

if (!$issue_id || !$user_id || !$job_id || empty($nail_ids) || count($nail_ids) !== count($quantities)) {
    $response['message'] = 'ข้อมูลที่ส่งมาไม่สมบูรณ์หรือไม่ถูกต้อง';
    echo json_encode($response);
    exit;
}

// --- Database Transaction ---
$conn->begin_transaction();

try {
    // 1. ตรวจสอบว่าเคยมีการเบิกครั้งแรกสำหรับ issue_id นี้แล้วหรือยัง
    $check_sql = "SELECT COUNT(*) as count FROM nail_usage_log WHERE issue_id = ?";
    $stmt_check = $conn->prepare($check_sql);
    $stmt_check->bind_param("i", $issue_id);
    $stmt_check->execute();
    $is_first_issue = ($stmt_check->get_result()->fetch_assoc()['count'] == 0);
    $stmt_check->close();

    if ($is_first_issue) {
        // --- Logic สำหรับการเบิกครั้งแรก ---
        $target_table = "nail_usage_log";
        $sql = "INSERT INTO $target_table (issue_id, job_id, nail_id, quantity_issued, issued_by_user_id) VALUES (?, ?, ?, ?, ?)";
    } else {
        // --- Logic สำหรับการเบิกซ่อม ---
        $target_table = "nail_repair_log";
        $sql = "INSERT INTO $target_table (issue_id, job_id, nail_id, quantity_issued, repaired_by_user_id) VALUES (?, ?, ?, ?, ?)";
    }

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Prepare statement for $target_table failed: " . $conn->error);
    }
    
    $items_saved = 0;
    foreach ($nail_ids as $index => $nail_id) {
        $quantity_issued = filter_var($quantities[$index], FILTER_VALIDATE_INT);
        $nail_id_validated = filter_var($nail_id, FILTER_VALIDATE_INT);

        // บันทึกเฉพาะรายการที่มีการเบิก (จำนวน > 0)
        if ($quantity_issued > 0 && $nail_id_validated) {
            $stmt->bind_param("isiii", $issue_id, $job_id, $nail_id_validated, $quantity_issued, $user_id);
            $stmt->execute();
            $items_saved++;
        }
    }
    $stmt->close();
    
    if ($items_saved === 0) {
        throw new Exception("ไม่มีการระบุจำนวนตะปูที่ต้องการเบิก");
    }

    // --- [ลบออก] ไม่มีการอัปเดตสถานะในตาราง wood_issue อีกต่อไป ---
    /*
    if ($is_first_issue) {
        $update_status_sql = "UPDATE wood_issue SET issue_status = 'เบิกแล้ว' WHERE issue_id = ?";
        $stmt_update = $conn->prepare($update_status_sql);
        $stmt_update->bind_param("i", $issue_id);
        $stmt_update->execute();
        $stmt_update->close();
    }
    */

    // --- หากทุกอย่างสำเร็จ ให้ Commit Transaction ---
    $conn->commit();
    $response['success'] = true;
    $response['message'] = ($is_first_issue ? 'บันทึกการเบิกตะปูสำเร็จ!' : 'บันทึกการเบิกซ่อมสำเร็จ!');

} catch (Exception $e) {
    // --- หากมีข้อผิดพลาด ให้ Rollback Transaction ---
    $conn->rollback();
    $response['success'] = false;
    $response['message'] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>
