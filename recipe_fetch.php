<?php
// ภาษา: PHP
// ชื่อไฟล์: recipe_fetch.php
// หน้าที่: คืนค่าข้อมูลสูตรล่าสุดของไม้ท่อนที่เลือก (JSON)

header('Content-Type: application/json; charset=utf-8');
include_once __DIR__ . '/config_db.php';

$rm_id = isset($_GET['rm_id']) ? (int)$_GET['rm_id'] : 0;
if ($rm_id <= 0) {
    echo json_encode(['error' => 'Invalid rm_id']);
    exit;
}

// ดึงสูตรล่าสุดสำหรับ rm_id
$sql = "
    SELECT rl.part_id, rl.part_qry   AS part_qty,    rl.part_comment,
           rl.hw_id,   rl.hw_qty     AS hw_qty,      rl.hw_comment,
           rl.sw_id,   rl.sw_qty     AS sw_qty,      rl.sw_comment
    FROM recipe_list AS rl
    WHERE rl.rm_id = ?
    ORDER BY rl.recipe_id DESC
    LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $rm_id);
$stmt->execute();
$result = $stmt->get_result();
$data   = $result->fetch_assoc() ?: [];
$stmt->close();
$conn->close();

echo json_encode($data);
