<?php
// เรียกใช้ TCPDF
require_once('tcpdf/tcpdf.php');
require_once 'config_db.php'; // เรียกใช้งานไฟล์เชื่อมต่อฐานข้อมูล

// ตรวจสอบว่ามีการเลือกเดือนไหม ถ้าไม่มีให้ใช้เดือนปัจจุบัน
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// กำหนดค่าการตั้งค่า TCPDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// ตั้งค่าข้อมูลเริ่มต้นของเอกสาร
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Your Name');
$pdf->SetTitle('Dashboard Part & Nail Usage');
$pdf->SetSubject('Usage Report');

// ตั้งค่าฟอนต์ภาษาไทย

$fontname = $pdf->SetFont('thsarabunnew', 'B', 12); // กำหนดฟอนต์ที่รองรับภาษาไทย
$pdf->SetFont($fontname, '', 14, '', false);

// ตั้งค่าขนาดขอบกระดาษ
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// เพิ่มหน้าเอกสาร
$pdf->AddPage();

// หัวข้อเอกสาร
$pdf->SetFont($fontname, 'B', 16);
$pdf->Cell(0, 10, 'Dashboard - Part & Nail Usage for ' . date('F Y', strtotime($selected_month)), 0, 1, 'C');

// Query สำหรับดึงข้อมูลการใช้ Part
$sql_parts = "
    SELECT 
        p.part_code,
        p.part_type,
        CONCAT(p.part_thickness, ' x ', p.part_width, ' x ', p.part_length) AS size,
        SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(b.parts, CONCAT('$[', numbers.n - 1, '].quantity'))) AS UNSIGNED) * f.forecast_quantity) AS total_quantity_used,
        SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(b.parts, CONCAT('$[', numbers.n - 1, '].quantity'))) AS UNSIGNED) * f.forecast_quantity * 
            (p.part_thickness * p.part_width * p.part_length) / 1000000000) AS total_m3_used
    FROM 
        bom b
    JOIN 
        (SELECT @row := @row + 1 AS n FROM 
            (SELECT 0 UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) t1, 
            (SELECT 0 UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) t2, 
            (SELECT @row := 0) t3
        ) numbers
    JOIN 
        part_list p ON JSON_UNQUOTE(JSON_EXTRACT(b.parts, CONCAT('$[', numbers.n - 1, '].part_id'))) = p.part_id
    JOIN 
        forecast f ON b.prod_code = f.prod_code
    WHERE 
        JSON_UNQUOTE(JSON_EXTRACT(b.parts, CONCAT('$[', numbers.n - 1, ']'))) IS NOT NULL
    AND 
        DATE_FORMAT(f.forecast_date, '%Y-%m') = ?
    GROUP BY 
        p.part_code, p.part_type, size;
";
$stmt_parts = $conn->prepare($sql_parts);
$stmt_parts->bind_param("s", $selected_month);
$stmt_parts->execute();
$result_parts = $stmt_parts->get_result();

// กำหนดตารางสำหรับแสดงข้อมูล Part
$pdf->SetFont($fontname, 'B', 14);
$pdf->Cell(0, 10, 'Part Usage (M3)', 0, 1);

$pdf->SetFont($fontname, '', 12);
$pdf->SetFillColor(224, 235, 255);
$pdf->SetTextColor(0);
$pdf->SetDrawColor(128, 0, 0);
$pdf->SetLineWidth(0.3);

// ส่วนหัวของตาราง Part
$pdf->Cell(30, 10, 'Part Code', 1, 0, 'C', 1);
$pdf->Cell(40, 10, 'Part Type', 1, 0, 'C', 1);
$pdf->Cell(40, 10, 'Size', 1, 0, 'C', 1);
$pdf->Cell(30, 10, 'Total M3 Used', 1, 0, 'C', 1);
$pdf->Cell(40, 10, 'Total Quantity Used', 1, 1, 'C', 1);

$pdf->SetFont($fontname, '', 10);

// ข้อมูลในตาราง Part
if ($result_parts->num_rows > 0) {
    while ($row = $result_parts->fetch_assoc()) {
        $pdf->Cell(30, 10, $row['part_code'], 1);
        $pdf->Cell(40, 10, $row['part_type'], 1);
        $pdf->Cell(40, 10, $row['size'], 1);
        $pdf->Cell(30, 10, number_format($row['total_m3_used'], 6), 1);
        $pdf->Cell(40, 10, $row['total_quantity_used'], 1, 1);
    }
} else {
    $pdf->Cell(0, 10, 'No part usage data for this month.', 1, 1);
}

$pdf->AddPage();

// Query สำหรับดึงข้อมูลการใช้ ตะปู
$sql_nails = "
    SELECT 
        n.nail_code,
        SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(b.nails, CONCAT('$[', numbers.n - 1, '].quantity'))) AS UNSIGNED) * f.forecast_quantity) AS total_nails_used
    FROM 
        bom b
    JOIN 
        (SELECT @row := @row + 1 AS n FROM 
            (SELECT 0 UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) t1, 
            (SELECT 0 UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) t2, 
            (SELECT @row := 0) t3
        ) numbers
    JOIN 
        nail n ON JSON_UNQUOTE(JSON_EXTRACT(b.nails, CONCAT('$[', numbers.n - 1, '].nail_id'))) = n.nail_id
    JOIN 
        forecast f ON b.prod_code = f.prod_code
    WHERE 
        JSON_UNQUOTE(JSON_EXTRACT(b.nails, CONCAT('$[', numbers.n - 1, ']'))) IS NOT NULL
    AND 
        DATE_FORMAT(f.forecast_date, '%Y-%m') = ?
    GROUP BY 
        n.nail_code;
";
$stmt_nails = $conn->prepare($sql_nails);
$stmt_nails->bind_param("s", $selected_month);
$stmt_nails->execute();
$result_nails = $stmt_nails->get_result();

// เพิ่มส่วนสำหรับข้อมูลการใช้ตะปู
$pdf->Ln(10);
$pdf->SetFont($fontname, 'B', 14);
$pdf->Cell(0, 10, 'Nail Usage', 0, 1);

$pdf->SetFont($fontname, '', 12);
$pdf->Cell(60, 10, 'Nail Code', 1, 0, 'C', 1);
$pdf->Cell(40, 10, 'Total Nails Used', 1, 1, 'C', 1);

// ข้อมูลในตาราง ตะปู
$pdf->SetFont($fontname, '', 10);
if ($result_nails->num_rows > 0) {
    while ($row = $result_nails->fetch_assoc()) {
        $pdf->Cell(60, 10, $row['nail_code'], 1);
        $pdf->Cell(40, 10, $row['total_nails_used'], 1, 1);
    }
} else {
    $pdf->Cell(0, 10, 'No nail usage data for this month.', 1, 1);
}

// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();

// สร้างไฟล์ PDF และแสดงผล
$pdf->Output('dashboard_usage_' . $selected_month . '.pdf', 'I');
?>
