<?php
// wood_issue_functions.php

include_once __DIR__ . '/product_functions.php';

// ดึงสถานะงานจากตาราง wood_issue
function getJobStatus($job_id, $conn)
{
    $sql = "SELECT issue_status FROM wood_issue WHERE job_id = '$job_id'";
    $result = $conn->query($sql);
    return $result->fetch_assoc();
}

// คำนวณ wood issue main m3 โดยใช้ BOM parts และข้อมูลจาก part_list
function getWoodIssueMainM3($prod_id, $quantity, $conn)
{
    $bom_data = getBomParts($prod_id, $conn);
    $parts = isset($bom_data['parts']) ? json_decode($bom_data['parts'], true) : [];
    $wood_issues_main_m3 = 0;

    // รายการของ part_type ที่อนุญาตให้คำนวณ
    $allowed_types = [
        "PINE NON FSC",
        "PINE NON FSC ไส",
        "PINE NON FSC*",
        "PINE NON FSC*2",
        "PINE NON FSCไส1ด้าน",
        "PINE NON FSC*4",
        "PINE NON FSCไสเหลือ 22",
        "PINE NON FSCไสเหลือ 50",
        "PINE NON FSC บาก",
        "Pine wood",
        "STOPPER",
        "Pine wood shocking",
        "PINE NON FSC เฉือน",
        "PINE NON FSC สโลป1แผ่น",
        "PINE NON FSC สโลป2แผ่น",
        "*** ไม้แพ็ค ***",
        "PINE NON FSC บาก2แบบ",
        "PINE NON FSC เฉือนหัว-ท้าย",
        "PINE NON FSC สโลป"

    ];

    foreach ($parts as $part) {
        $part_id = $part['part_id'];
        $part_qty = $part['quantity'];

        $part_sql = "SELECT part_m3, part_type FROM part_list WHERE part_id = '$part_id'";
        $part_result = $conn->query($part_sql);
        $part_data = $part_result->fetch_assoc();

        // ถ้า part_type ไม่ตรงกับรายการที่อนุญาต ให้ข้ามไป
        if (!in_array($part_data['part_type'], $allowed_types)) {
            continue;
        }

        $part_m3 = isset($part_data['part_m3']) ? $part_data['part_m3'] : 0;
        $wood_issues_main_m3 += $part_m3 * $part_qty;
    }
    return $wood_issues_main_m3 * $quantity;
}


// คำนวณ wood issues repair m3 จากข้อมูล repair_issue
function getWoodIssuesRepairM3($job_id, $conn)
{
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

// ดึงข้อมูล return wood m3 จากตาราง return_wood_wip
function getReturnWoodM3($job_id, $conn)
{
    $sql = "SELECT return_total_m3 FROM return_wood_wip WHERE job_id = '$job_id'";
    $result = $conn->query($sql);
    $return_data = $result->fetch_assoc();
    return isset($return_data['return_total_m3']) ? $return_data['return_total_m3'] : 0;
}

// คำนวณ wood loss จาก wood issues repair และ return wood
function calculateWoodLoss($wood_issues_repair, $wood_return)
{
    return $wood_issues_repair - $wood_return;
}

// คำนวณเปอร์เซ็นต์ wood loss
function calculateWoodLossPercent($wood_issue_main, $wood_loss)
{
    return ($wood_issue_main > 0) ? ($wood_loss * 100) / $wood_issue_main : 0;
}

// อัปเดตสถานะของ wood_issue
function updateIssueStatus($issue_id, $new_status)
{
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

// ดึงข้อมูลรายละเอียดของงานจาก wood_issue พร้อม JOIN ตาราง prod_list
function getJobDetails($conn, $job_id)
{
    $query = "SELECT wi.job_id, wi.product_code, wi.quantity, wi.prod_id, wi.creation_date, wi.issue_date, wi.issued_by, wi.issue_status, 
                     pl.code_cus_size 
              FROM wood_issue wi
              JOIN prod_list pl ON wi.product_code = pl.prod_code
              WHERE wi.job_id = '$job_id' AND wi.issue_status NOT IN ('ยกเลิก')";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    } else {
        return ['error' => "ไม่พบข้อมูลสำหรับ Job ID นี้"];
    }
}

// อัปเดตสถานะงานในตาราง wood_issue เป็น 'ปิดสำเร็จ'
function updateWoodIssueStatus($conn, $job_id, $status = 'ปิดสำเร็จ')
{
    $update_query = "UPDATE wood_issue SET issue_status = '$status' WHERE job_id = '$job_id'";
    return mysqli_query($conn, $update_query);
}

// ดึงรายการ JOB จาก wood_issue โดยรองรับการค้นหาและแบ่งหน้า
function getJobs($conn, $search_value, $offset, $items_per_page)
{
    if (!empty($search_value)) {
        $query = "SELECT DISTINCT job_id 
                  FROM wood_issue 
                  WHERE (issue_status = 'เบิกแล้ว' OR issue_status = 'ปิดสำเร็จ' OR issue_status = 'รอยืนยันการสั่งจ่าย' OR issue_status = 'สั่งจ่ายแล้ว')
                  AND job_id LIKE '%$search_value%'
                  ORDER BY creation_date DESC";
    } else {
        $query = "SELECT DISTINCT job_id 
                  FROM wood_issue 
                  WHERE (issue_status = 'เบิกแล้ว' OR issue_status = 'ปิดสำเร็จ' OR issue_status = 'รอยืนยันการสั่งจ่าย' OR issue_status = 'สั่งจ่ายแล้ว')
                  ORDER BY creation_date DESC 
                  LIMIT $items_per_page OFFSET $offset";
    }
    return mysqli_query($conn, $query);
}
?>
