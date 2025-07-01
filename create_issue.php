<?php
// ภาษา: PHP
// ชื่อไฟล์: create_issue.php

include 'config_db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    exit('User not logged in!');
}

// รับข้อมูลจากฟอร์ม
$job_id       = $_POST['job_id'];
$job_type     = $_POST['job_type'];
$product_code = $_POST['prod_code'];
$product_id   = $_POST['prod_id'];
$quantity     = (int)$_POST['quantity'];
$wood_wastage = (float)$_POST['wood_wastage'];
$wood_type    = $_POST['wood_type'];
$want_receive = $_POST['want_receive'];
$remark       = $_POST['remark'];

// กำหนด issue_status
$issue_status = in_array($job_type, ['งานเคลม','งานพาเลทไม้อัด']) ? 'เบิกแล้ว' : 'รอยืนยันงาน';

// ตรวจสอบ prod_id
if (empty($product_id)) {
    echo "<script>alert('กรุณากรอก PRODUCT CODE');window.location='planning_order.php';</script>";
    exit;
}

// ดึง thainame จาก prod_user
$user_id = $_SESSION['user_id'];
$stmt    = $conn->prepare("SELECT thainame FROM prod_user WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$thainame = $stmt->get_result()->fetch_assoc()['thainame'];

// ตรวจสอบ job_id ซ้ำ
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM wood_issue WHERE job_id = ?");
$stmt->bind_param('s', $job_id);
$stmt->execute();
$cnt = $stmt->get_result()->fetch_assoc()['cnt'];
if ($cnt > 0) {
    echo "<script>alert('หมายเลข JOB ซ้ำ!');window.location='planning_order.php';</script>";
    exit;
}

// ดึง price_value ล่าสุด
$stmt = $conn->prepare("SELECT price_value FROM product_price WHERE prod_id = ? ORDER BY date_update DESC LIMIT 1");
$stmt->bind_param('i', $product_id);
$stmt->execute();
$price_value = $stmt->get_result()->fetch_assoc()['price_value'] ?: 0;
$total_wage = $price_value * $quantity ?: 0;

// เตรียม INSERT
$query = "INSERT INTO wood_issue 
    (job_id, job_type, prod_id, product_code, price_value, total_wage, quantity, wood_wastage, wood_type, issue_status, want_receive, remark, create_by)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);";
$stmt = $conn->prepare($query);
$stmt->bind_param(
    'ssissdidsssss',
    $job_id, $job_type, $product_id, $product_code,
    $price_value, $total_wage, $quantity, $wood_wastage, $wood_type,
    $issue_status, $want_receive, $remark, $thainame
);

if ($stmt->execute()) {
    $issue_id = $conn->insert_id;
    echo '<script>window.open("generate_issued_pdf.php?issue_id=' . $issue_id . '", "_blank");</script>';
    echo "<script>alert('บันทึกสำเร็จ');window.location='planning_order.php';</script>";
} else {
    echo "Error: " . $stmt->error;
}

$conn->close();
?>
