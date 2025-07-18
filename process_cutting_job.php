<?php
// process_cutting_job.php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/config_db.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

// --- รับข้อมูล ---
$recipe_id = isset($_POST['recipe_id']) ? (int)$_POST['recipe_id'] : 0;
$total_wood_logs = isset($_POST['total_wood_logs']) ? (int)$_POST['total_wood_logs'] : 0;
$rm_needed_date = isset($_POST['rm_needed_date']) ? $conn->real_escape_string($_POST['rm_needed_date']) : null;
$wip_receive_date = isset($_POST['wip_receive_date']) ? $conn->real_escape_string($_POST['wip_receive_date']) : null;
$tu_details_array = isset($_POST['tu']) ? $_POST['tu'] : [];

if (empty($recipe_id) || empty($total_wood_logs) || empty($tu_details_array)) {
    echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
    exit();
}

// --- ดึงข้อมูล Recipe มาเตรียมไว้สำหรับการคำนวณ ---
$sql_recipe_data = "SELECT * FROM recipe_list WHERE recipe_id = ?";
$stmt_recipe = $conn->prepare($sql_recipe_data);
$stmt_recipe->bind_param("i", $recipe_id);
$stmt_recipe->execute();
$recipe_data = $stmt_recipe->get_result()->fetch_assoc();
$stmt_recipe->close();

if (!$recipe_data) {
    echo json_encode(['success' => false, 'message' => "ไม่พบข้อมูล Recipe ID: {$recipe_id}"]);
    exit();
}

// --- Function ช่วยดึงค่า m3 จากฐานข้อมูล ---
function get_m3_from_db($conn, $table, $id_column, $id_value) {
    if (empty($id_value)) return 0;
    $m3_column = ($table === 'rm_wood_list') ? 'rm_m3' : 'part_m3';
    $stmt = $conn->prepare("SELECT $m3_column FROM $table WHERE $id_column = ?");
    $stmt->bind_param("i", $id_value);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? (float)$row[$m3_column] : 0;
}

$conn->begin_transaction();
try {
    // --- ส่วนตรรกะการสร้าง Job Code ---
    $buddhistYear = date('Y') + 543;
    $yearPrefix = substr($buddhistYear, -2);
    $datePrefix = $yearPrefix . date('md');

    $sql_last_code = "SELECT job_code FROM cutting_job WHERE job_code LIKE ? ORDER BY job_code DESC LIMIT 1";
    $stmt_last_code = $conn->prepare($sql_last_code);
    $search_prefix = $datePrefix . '%';
    $stmt_last_code->bind_param("s", $search_prefix);
    $stmt_last_code->execute();
    $result = $stmt_last_code->get_result();
    $lastJob = $result->fetch_assoc();
    $stmt_last_code->close();

    $running_number = 1;
    if ($lastJob) {
        $last_running = (int)substr($lastJob['job_code'], -3);
        $running_number = $last_running + 1;
    }
    // --- สิ้นสุดตรรกะการสร้าง Job Code ---

    // 1. สร้าง Batch หลัก
    $stmt_batch = $conn->prepare(
        "INSERT INTO cutting_batch (recipe_id, total_wood_logs, rm_needed_date, wip_receive_date, batch_status) 
         VALUES (?, ?, ?, ?, 'รอดำเนินการ')"
    );
    $stmt_batch->bind_param("iiss", $recipe_id, $total_wood_logs, $rm_needed_date, $wip_receive_date);
    if (!$stmt_batch->execute()) {
        throw new Exception("Failed to create batch: " . $stmt_batch->error);
    }
    $batch_id = $conn->insert_id;
    $stmt_batch->close();

    $created_job_ids = [];

    // 2. วนลูปสร้าง Job ย่อยสำหรับแต่ละ Part Type
    foreach ($tu_details_array as $part_type => $tu_values) {
        if (empty($tu_values) || empty(array_filter($tu_values))) {
            continue; 
        }

        // --- คำนวณ Summary สำหรับ Job ย่อยนี้ ---
        $summary_input_m3 = 0;
        if ($part_type === 'main') {
            $summary_input_m3 = get_m3_from_db($conn, 'rm_wood_list', 'rm_id', $recipe_data['main_input_rm_id']) * $total_wood_logs;
        } else {
            $input_qty = (int)($recipe_data[$part_type.'_input_qty'] ?? 0);
            $input_thickness = (int)($recipe_data[$part_type.'_input_thickness'] ?? 0);
            $input_width = (int)($recipe_data[$part_type.'_input_width'] ?? 0);
            $input_length = (int)($recipe_data[$part_type.'_input_length'] ?? 0);
            $summary_input_m3 = (($input_thickness * $input_width * $input_length) / 1000000000) * $input_qty * $total_wood_logs;
        }
        
        $part_m3 = get_m3_from_db($conn, 'part_list', 'part_id', $recipe_data[$part_type.'_output_part_id']);
        $summary_output_m3 = array_sum($tu_values) * $part_m3;
        $summary_loss_m3 = $summary_input_m3 - $summary_output_m3;
        $summary_loss_percent = ($summary_input_m3 > 0) ? ($summary_loss_m3 / $summary_input_m3) * 100 : 0;
        
        // --- สร้าง Job Code ที่สมบูรณ์สำหรับใบงานย่อยนี้ ---
        $new_job_code = $datePrefix . str_pad($running_number, 3, '0', STR_PAD_LEFT);
        $tu_details_json = json_encode($tu_values);
        
        // --- [แก้ไข] เพิ่ม job_code และคอลัมน์ summary ทั้งหมดในการ INSERT ---
        $stmt_job = $conn->prepare(
            "INSERT INTO cutting_job (
                batch_id, job_code, part_type, tu_details, 
                summary_input_m3, summary_output_m3, summary_loss_m3, summary_loss_percent, 
                job_status
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'รอดำเนินการ')"
        );
        // [แก้ไข] ปรับ bind_param ให้ถูกต้อง
        $stmt_job->bind_param("isssddds", 
            $batch_id, 
            $new_job_code,
            $part_type, 
            $tu_details_json,
            $summary_input_m3,
            $summary_output_m3,
            $summary_loss_m3,
            $summary_loss_percent
        );
        
        if (!$stmt_job->execute()) {
            throw new Exception("Failed to create job for part {$part_type}: " . $stmt_job->error);
        }
        $created_job_ids[] = $conn->insert_id;
        $stmt_job->close();
        
        $running_number++; // เพิ่ม Running Number สำหรับ Job ถัดไป
    }

    if (empty($created_job_ids)) {
        throw new Exception("ไม่มีข้อมูล TU ที่ถูกต้องสำหรับสร้างใบงานย่อย");
    }

    $conn->commit();
    echo json_encode(['success' => true, 'job_ids' => $created_job_ids]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}

$conn->close();
?>
