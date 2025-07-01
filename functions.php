<?php
function getProdInfo($prod_id, $conn) {
    $sql = "SELECT prod_code, prod_partno FROM prod_list WHERE prod_id = '$prod_id'";
    $result = $conn->query($sql);
    return $result->fetch_assoc();
}

function getJobCompleteInfo($job_id, $conn) {
    $sql = "SELECT date_complete, prod_complete_qty FROM jobs_complete WHERE job_id = '$job_id'";
    $result = $conn->query($sql);
    return $result->fetch_assoc();
}

function getJobStatus($job_id, $conn) {
    $sql = "SELECT issue_status FROM wood_issue WHERE job_id = '$job_id'";
    $result = $conn->query($sql);
    return $result->fetch_assoc();
}

function getBomParts($prod_id, $conn) {
    $sql = "SELECT parts FROM bom WHERE prod_id = '$prod_id'";
    $result = $conn->query($sql);
    return $result->fetch_assoc();
}

function getWoodIssueMainM3($prod_id, $quantity, $conn) {
    $bom_data = getBomParts($prod_id, $conn);
    $parts = isset($bom_data['parts']) ? json_decode($bom_data['parts'], true) : [];
    $wood_issues_main_m3 = 0;
    foreach ($parts as $part) {
        $part_id = $part['part_id'];
        $part_qty = $part['quantity'];

        $part_sql = "SELECT part_m3 FROM part_list WHERE part_id = '$part_id'";
        $part_result = $conn->query($part_sql);
        $part_data = $part_result->fetch_assoc();
        $part_m3 = isset($part_data['part_m3']) ? $part_data['part_m3'] : 0;
        $wood_issues_main_m3 += $part_m3 * $part_qty;
    }
    return $wood_issues_main_m3 * $quantity;
}

function getWoodIssuesRepairM3($job_id, $conn) {
    $sql = "SELECT part_quantity_reason FROM repair_issue WHERE job_id = '$job_id'";
    $result = $conn->query($sql);
    $repair_data = $result->fetch_assoc();
    $repair_parts = isset($repair_data['part_quantity_reason']) ? json_decode($repair_data['part_quantity_reason'], true) : [];
    $wood_issues_repair_m3 = 0;
    foreach ($repair_parts as $repair_part) {
        $repair_part_id = $repair_part['part_id'];
        $repair_part_qty = $repair_part['quantity'];

        $repair_part_sql = "SELECT part_m3 FROM part_list WHERE part_id = '$repair_part_id'";
        $repair_part_result = $conn->query($repair_part_sql);
        $repair_part_data = $repair_part_result->fetch_assoc();
        $repair_part_m3 = isset($repair_part_data['part_m3']) ? $repair_part_data['part_m3'] : 0;
        $wood_issues_repair_m3 += $repair_part_m3 * $repair_part_qty;
    }
    return $wood_issues_repair_m3;
}

function getReturnWoodM3($job_id, $conn) {
    $sql = "SELECT return_total_m3 FROM return_wood_wip WHERE job_id = '$job_id'";
    $result = $conn->query($sql);
    $return_data = $result->fetch_assoc();
    return isset($return_data['return_total_m3']) ? $return_data['return_total_m3'] : 0;
}

function calculateWoodLoss($wood_issues_repair, $wood_return) {
    return $wood_issues_repair - $wood_return;
}

function calculateWoodLossPercent($wood_issue_main, $wood_loss) {
    return ($wood_issue_main > 0) ? ($wood_loss * 100) / $wood_issue_main : 0;
}

// ฟังก์ชันแปลงวันที่ให้อยู่ในรูปแบบที่เหมาะสมกับฐานข้อมูล
function convertDateToDBFormat($date) {
    if ($date != '') {
        return date('Y-m-d', strtotime($date));
    }
    return null;
}

// ฟังก์ชันสำหรับอัปเดตสถานะของ wood_issue
function updateIssueStatus($issue_id, $new_status) {
    // รวมไฟล์เชื่อมต่อฐานข้อมูล (ปรับให้ตรงกับการตั้งค่าของคุณ)
    include 'config_db.php'; // เชื่อมต่อกับฐานข้อมูล

    // ป้องกัน SQL Injection ด้วยการ escape ค่าที่รับมา
    $issue_id = mysqli_real_escape_string($conn, $issue_id);
    $new_status = mysqli_real_escape_string($conn, $new_status);

    // คำสั่ง SQL สำหรับอัปเดตข้อมูล
    $sql = "UPDATE wood_issue SET issue_status = '$new_status' WHERE issue_id = '$issue_id'";

    if (mysqli_query($conn, $sql)) {
        return true;
    } else {
        return "เกิดข้อผิดพลาดในการอัปเดตข้อมูล: " . mysqli_error($conn);
    }
}

?>
