<?php
// ภาษา: PHP
// ชื่อไฟล์: recipe_action.php
// หน้าที่: รับค่าจากฟอร์ม recipe_modals.php, คำนวณปริมาตรทั้งหมด และเพิ่ม/อัปเดตสูตรใน recipe_list

session_start();
include_once __DIR__ . '/config_db.php';

// --- รับค่าจาก POST ---
$action      = $_POST['action']      ?? 'add';
$recipe_id   = (int)($_POST['recipe_id'] ?? 0);
$rm_id       = (int)($_POST['rm_id']       ?? 0);
$rm_qty      = (int)($_POST['rm_qty']      ?? 1);
$rm_comment  = trim($_POST['rm_comment']  ?? '');

$part_id      = (int)($_POST['part_id']      ?? 0);
$part_qty     = (int)($_POST['part_qty']     ?? 1);
$part_comment = trim($_POST['part_comment'] ?? '');
$part_cut   = isset($_POST['part_cut'])   ? (int)$_POST['part_cut']   : 0;
$part_split = isset($_POST['part_split']) ? (int)$_POST['part_split'] : 0;

$hw_id       = (int)($_POST['hw_id']       ?? 0);
$hw_qty      = (int)($_POST['hw_qty']      ?? 0);
$hw_comment  = trim($_POST['hw_comment']  ?? '');
$hw_cut   = isset($_POST['hw_cut'])   ? (int)$_POST['hw_cut']   : 0;
$hw_split = isset($_POST['hw_split']) ? (int)$_POST['hw_split'] : 0;

$sw_id       = (int)($_POST['sw_id']       ?? 0);
$sw_qty      = (int)($_POST['sw_qty']      ?? 0);
$sw_comment  = trim($_POST['sw_comment']  ?? '');
$sw_cut   = isset($_POST['sw_cut'])   ? (int)$_POST['sw_cut']   : 0;
$sw_split = isset($_POST['sw_split']) ? (int)$_POST['sw_split'] : 0;

/**
 * ดึง dimension (thickness, width, length)
 */
function getDimensions(mysqli $conn, string $table, int $id): array {
    $prefix = explode('_', $table)[0];
    $colTh = "{$prefix}_thickness";
    $colWd = "{$prefix}_width";
    $colLn = "{$prefix}_length";
    $idCol = "{$prefix}_id";

    $sql = "SELECT $colTh, $colWd, $colLn FROM $table WHERE $idCol = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->bind_result($th, $wd, $ln);
    $stmt->fetch();
    $stmt->close();
    return [(float)$th, (float)$wd, (float)$ln];
}

/**
 * คำนวณปริมาตร (m³)
 */
function calcM3(float $th, float $wd, float $ln, int $qty): float {
    return ($th * $wd * $ln) / 1e9 * $qty;
}

// --- คำนวณปริมาตรทั้งหมด ---
list($r_th, $r_w, $r_l) = getDimensions($conn, 'rm_wood_list', $rm_id);
$rm_total_m3 = calcM3($r_th, $r_w, $r_l, $rm_qty);

list($p_th, $p_w, $p_l) = getDimensions($conn, 'part_list', $part_id);
$part_total_m3 = calcM3($p_th, $p_w, $p_l, $part_qty);

if ($hw_id) {
    list($h_th, $h_w, $h_l) = getDimensions($conn, 'hw_wood_list', $hw_id);
    $hw_total_m3 = calcM3($h_th, $h_w, $h_l, $hw_qty);
} else {
    $hw_total_m3 = 0.0;
}

if ($sw_id) {
    list($s_th, $s_w, $s_l) = getDimensions($conn, 'sw_wood_list', $sw_id);
    $sw_total_m3 = calcM3($s_th, $s_w, $s_l, $sw_qty);
} else {
    $sw_total_m3 = 0.0;
}

$rm_m3   = $rm_total_m3;
$net_m3  = $part_total_m3 + $hw_total_m3 + $sw_total_m3;
$loss_m3 = $rm_total_m3 - ($part_total_m3 + $hw_total_m3 + $sw_total_m3);
$loss_per = $rm_total_m3 > 0 ? ($loss_m3 / $rm_total_m3 * 100) : 0;

// --- กรณีแก้ไข (UPDATE) ---
if ($action === 'update' && $recipe_id > 0) {
    $sql = "UPDATE recipe_list SET
        rm_id=?, rm_qty=?, rm_total_m3=?, rm_comment=?,
        part_id=?, part_qry=?, part_cut=?, part_split=?, part_total_m3=?, part_comment=?,
        hw_id=?, hw_qty=?, hw_cut=?, hw_split=?, hw_total_m3=?, hw_comment=?,
        sw_id=?, sw_qty=?, sw_cut=?, sw_split=?, sw_total_m3=?, sw_comment=?,
        rm_m3=?, net_m3=?, loss_m3=?, loss_per_m3=?
      WHERE recipe_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'iidsiiiidsiiiidsiiiidsddddi',
        $rm_id, $rm_qty, $rm_total_m3, $rm_comment,
        $part_id, $part_qty, $part_cut, $part_split, $part_total_m3, $part_comment,
        $hw_id,  $hw_qty,  $hw_cut, $hw_split, $hw_total_m3, $hw_comment,
        $sw_id,  $sw_qty, $sw_cut, $sw_split, $sw_total_m3, $sw_comment,
        $rm_m3,  $net_m3,  $loss_m3,  $loss_per,
        $recipe_id
    );
    $stmt->execute();
    $stmt->close();
    header('Location: recipe_list.php?updated=1');
    exit;
}

// --- กรณีเพิ่มใหม่ (INSERT) ---
$sql = "INSERT INTO recipe_list
    (rm_id, rm_qty, rm_total_m3, rm_comment,
     part_id, part_qry, part_cut, part_split, part_total_m3, part_comment,
     hw_id, hw_qty, hw_cut, hw_split, hw_total_m3, hw_comment,
     sw_id, sw_qty, sw_cut, sw_split, sw_total_m3, sw_comment,
     rm_m3, net_m3, loss_m3, loss_per_m3)
  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param(
    'iidsiiiidsiiiidsiiiidsdddd',
    $rm_id, $rm_qty,   $rm_total_m3, $rm_comment,
    $part_id, $part_qty, $part_cut, $part_split, $part_total_m3,$part_comment,
    $hw_id,   $hw_qty,  $hw_cut, $hw_split, $hw_total_m3,$hw_comment,
    $sw_id,   $sw_qty,  $sw_cut, $sw_split, $sw_total_m3,$sw_comment,
    $rm_m3,   $net_m3,  $loss_m3,    $loss_per
);
$stmt->execute();
$stmt->close();
header('Location: recipe_list.php?added=1');
exit;
?>
