<?php
require_once 'config_db.php';

// ดึงข้อมูลจากตาราง part_list
$sql_parts = "SELECT part_id, part_code, CONCAT(part_thickness, 'x', part_width, 'x', part_length) AS size FROM part_list";
$result_parts = $conn->query($sql_parts);

// สร้างตัวเลือกของ part ในรูปแบบ HTML
$parts_html = '<option value="">Select Part</option>';
while ($row_part = $result_parts->fetch_assoc()) {
    $parts_html .= '<option value="' . $row_part['part_id'] . '">' . $row_part['part_code'] . ' (' . $row_part['size'] . ')</option>';
}

// ดึงข้อมูลจากตาราง nail
$sql_nails = "SELECT nail_id, nail_code FROM nail";
$result_nails = $conn->query($sql_nails);

// สร้างตัวเลือกของ nail ในรูปแบบ HTML
$nails_html = '<option value="">Select Nail</option>';
while ($row_nail = $result_nails->fetch_assoc()) {
    $nails_html .= '<option value="' . $row_nail['nail_id'] . '">' . $row_nail['nail_code'] . '</option>';
}

// ส่งข้อมูลกลับในรูปแบบ JSON
$response = array(
    'parts' => $parts_html,
    'nails' => $nails_html
);

echo json_encode($response);
?>
