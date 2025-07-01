<?php
// job_complete_functions.php

// ดึงข้อมูลรายละเอียดงานจากตาราง jobs_complete
function getJobCompleteInfo($job_id, $conn)
{
    $sql = "SELECT date_complete, prod_complete_qty FROM jobs_complete WHERE job_id = '$job_id'";
    $result = $conn->query($sql);
    return $result->fetch_assoc();
}

// คำนวณ production wage price โดยใช้ prod_complete_qty คูณกับ price_value จาก product_price
function calculateProductionWagePrice($conn, $prod_id, $prod_complete_qty)
{
    $priceQuery = "SELECT price_value FROM product_price WHERE prod_id = '$prod_id' LIMIT 1";
    $priceResult = mysqli_query($conn, $priceQuery);
    if ($priceResult && mysqli_num_rows($priceResult) > 0) {
        $priceData = mysqli_fetch_assoc($priceResult);
        $price_value = $priceData['price_value'];
        return $prod_complete_qty * $price_value;
    } else {
        // ถ้าไม่มีข้อมูลราคาสำหรับ prod_id นี้ คืนค่า false เพื่อเป็นสัญญาณ error
        return false;
    }
}

/**
 * บันทึกข้อมูลงานที่เสร็จสมบูรณ์
 *
 * @param mysqli $conn            การเชื่อมต่อฐานข้อมูลแบบ mysqli
 * @param string $job_id          รหัสงาน
 * @param int    $prod_complete_qty   จำนวนที่ตรวจรับ
 * @param string $receive_by      รับเข้าจาก
 * @param string $send_by         ส่งต่อไปยัง
 * @param string $assembly_point  จุดประกอบ
 * @param string $reason          เหตุผล
 * @param string $date_complete   วันที่ตรวจรับ (รูปแบบ YYYY-MM-DD HH:MM:SS)
 * @param string $record_by       ชื่อผู้บันทึก
 * @return bool                   true ถ้าสำเร็จ, false ถ้าเกิดข้อผิดพลาด
 */
function insertJobComplete(
    $conn,
    $job_id,
    $qty,
    $receive_by,
    $send_by,
    $assembly_point,
    $reason,
    $date_complete,
    $record_by,
    $date_receive
) {
    $sql = "
        INSERT INTO jobs_complete (
            job_id,
            prod_complete_qty,
            receive_by,
            send_by,
            assembly_point,
            reason,
            date_complete,
            record_by,
            date_receive
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'sisssssss',
        $job_id,
        $qty,
        $receive_by,
        $send_by,
        $assembly_point,
        $reason,
        $date_complete,
        $record_by,
        $date_receive
    );
    return $stmt->execute();
}

?>
