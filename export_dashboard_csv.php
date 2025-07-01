<?php
session_start();
require_once 'config_db.php'; // เรียกใช้งานไฟล์เชื่อมต่อฐานข้อมูล

// ตรวจสอบว่ามีการเลือกเดือนไหม ถ้าไม่มีให้ใช้เดือนปัจจุบัน
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Header สำหรับบอก browser ว่านี่เป็นไฟล์ CSV พร้อมการเข้ารหัส UTF-8 BOM
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=dashboard_data_' . $selected_month . '.csv');

// เปิดการเขียนไฟล์ CSV ใน output stream
$output = fopen('php://output', 'w');

// เขียน BOM เพื่อบอกว่าเป็นไฟล์ UTF-8 สำหรับการรองรับภาษาไทยใน Excel
fwrite($output, "\xEF\xBB\xBF");

// เขียนหัวข้อของตารางในไฟล์ CSV
fputcsv($output, ['Part Code', 'Part Type', 'Size', 'Total M3 Used', 'Total Quantity Used']);
fputcsv($output, ['Nail Code', 'Total Nails Used']);

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

// เขียนข้อมูลของ Part ลงในไฟล์ CSV
if ($result_parts->num_rows > 0) {
    while ($row = $result_parts->fetch_assoc()) {
        fputcsv($output, [
            $row['part_code'],
            $row['part_type'],
            $row['size'],
            number_format($row['total_m3_used'], 6),
            $row['total_quantity_used']
        ]);
    }
}

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

// เขียนข้อมูลของ Nail ลงในไฟล์ CSV
if ($result_nails->num_rows > 0) {
    fputcsv($output, ['']); // แทรกบรรทัดว่าง
    fputcsv($output, ['Nail Code', 'Total Nails Used']); // เขียนหัวข้อใหม่

    while ($row = $result_nails->fetch_assoc()) {
        fputcsv($output, [
            $row['nail_code'],
            $row['total_nails_used']
        ]);
    }
}

// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();
?>
