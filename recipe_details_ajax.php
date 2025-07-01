<?php
// ภาษา: PHP
// ชื่อไฟล์: recipe_details_ajax.php

session_start();
include_once __DIR__ . '/config_db.php';   // เชื่อมต่อฐานข้อมูล

// หลีกเลี่ยงการส่ง output ใดๆ ก่อน header
header('Content-Type: application/json; charset=utf-8');

try {
    // ตรวจสอบ parameter recipe_id (รองรับ GET หรือ POST)
    if (isset($_GET['recipe_id'])) {
        $recipe_id = (int) $_GET['recipe_id'];
    } elseif (isset($_POST['recipe_id'])) {
        $recipe_id = (int) $_POST['recipe_id'];
    } else {
        echo json_encode(['error' => 'ไม่พบ parameter recipe_id'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ฟังก์ชันช่วยจัดรูปแบบขนาด “หนา x กว้าง x ยาว”
    function formatSize($t, $w, $l) {
        return "{$t} x {$w} x {$l}";
    }

    // เตรียม SQL JOIN ดึงข้อมูลจากตารางหลักและที่เกี่ยวข้อง
    $sql = "
        SELECT
            rl.recipe_id,
            rl.rm_qty, rl.rm_total_m3, rl.rm_comment,
            rl.part_qry, rl.part_cut, rl.part_split, rl.part_total_m3, rl.part_comment,
            rl.hw_qty, rl.hw_cut, rl.hw_split, rl.hw_total_m3, rl.hw_comment,
            rl.sw_qty, rl.sw_cut, rl.sw_split, rl.sw_total_m3, rl.sw_comment,
            rl.rm_m3, rl.net_m3, rl.loss_m3, rl.loss_per_m3,
            r.rm_code, r.rm_thickness, r.rm_width, r.rm_length, r.rm_type,
            p.part_code, p.part_thickness, p.part_width, p.part_length, p.part_type,
            hw.hw_code, hw.hw_thickness, hw.hw_width, hw.hw_length, hw.hw_type,
            sw.sw_code, sw.sw_thickness, sw.sw_width, sw.sw_length, sw.sw_type
        FROM recipe_list AS rl
        INNER JOIN rm_wood_list    AS r  ON rl.rm_id   = r.rm_id
        INNER JOIN part_list       AS p  ON rl.part_id = p.part_id
        LEFT JOIN hw_wood_list     AS hw ON rl.hw_id   = hw.hw_id
        LEFT JOIN sw_wood_list     AS sw ON rl.sw_id   = sw.sw_id
        WHERE rl.recipe_id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare statement failed: ' . $conn->error);
    }
    $stmt->bind_param('i', $recipe_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data   = $result->fetch_assoc();
    $stmt->close();

    // ถ้าไม่พบข้อมูล ให้คืนค่า error
    if (!$data) {
        echo json_encode(['error' => 'ไม่พบข้อมูลสำหรับ recipe_id นี้'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // กำหนดค่า M³ ของแต่ละส่วนให้เป็น 0.0 หากไม่มีข้อมูล
    $partM3  = isset($data['part_total_m3']) ? (float)$data['part_total_m3'] : 0.0;
    $heavyM3 = isset($data['hw_total_m3'])   ? (float)$data['hw_total_m3']   : 0.0;
    $sawM3   = isset($data['sw_total_m3'])   ? (float)$data['sw_total_m3']   : 0.0;

    // สร้างโครงสร้าง JSON ส่งกลับ
    $response = [
        'recipe_id' => $data['recipe_id'],
        'raw' => [
            'code'     => $data['rm_code'],
            'size'     => formatSize($data['rm_thickness'], $data['rm_width'], $data['rm_length']),
            'type'     => ($data['rm_type'] === 'K' ? 'อบแล้ว' : 'ยังไม่อบ'),
            'qty'      => (int)$data['rm_qty'],
            'total_m3' => (float)$data['rm_total_m3'],
            'comment'  => $data['rm_comment'] ?: '',
        ],
        'part' => [
            'code'     => $data['part_code'],
            'size'     => formatSize($data['part_thickness'], $data['part_width'], $data['part_length']),
            'type'     => $data['part_type'],
            'cut'      => (int)$data['part_cut'],
            'split'    => (int)$data['part_split'],
            'qty'      => (int)$data['part_qry'],
            'total_m3' => $partM3,
            'comment'  => $data['part_comment'] ?: '',
        ],
        'heavy' => [
            'code'     => $data['hw_code'],
            'size'     => formatSize($data['hw_thickness'], $data['hw_width'], $data['hw_length']),
            'type'     => $data['hw_type'],
            'cut'      => (int)$data['hw_cut'],
            'split'    => (int)$data['hw_split'],
            'qty'      => (int)$data['hw_qty'],
            'total_m3' => $heavyM3,
            'comment'  => $data['hw_comment'] ?: '',
        ],
        'saw' => [
            'code'     => $data['sw_code'],
            'size'     => formatSize($data['sw_thickness'], $data['sw_width'], $data['sw_length']),
            'type'     => $data['sw_type'],
            'cut'      => (int)$data['sw_cut'],
            'split'    => (int)$data['sw_split'],
            'qty'      => (int)$data['sw_qty'],
            'total_m3' => $sawM3,
            'comment'  => $data['sw_comment'] ?: '',
        ],
        'summary' => [
            'rm_m3'    => (float)$data['rm_m3'],
            'net_m3'   => (float)$data['net_m3'],
            'used_m3'  => $partM3 + $heavyM3 + $sawM3,
            'loss_m3'  => (float)$data['loss_m3'],
            'loss_pct' => (float)$data['loss_per_m3'],
        ],
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // กรณีเกิดข้อผิดพลาดภายในระบบ
    http_response_code(500);
    echo json_encode([
        'error'   => 'เกิดข้อผิดพลาดภายในระบบ',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
