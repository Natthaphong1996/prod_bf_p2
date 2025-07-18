<?php
// process_recipe.php
session_start();

// -------------------------------------------------------------------------
// 1. เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล
// -------------------------------------------------------------------------
require_once __DIR__ . '/config_db.php';

// -------------------------------------------------------------------------
// 2. ตรวจสอบว่ามีการส่งข้อมูลมาแบบ POST หรือไม่
// -------------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: recipe_list.php");
    exit();
}

// -------------------------------------------------------------------------
// 3. รับข้อมูลจากฟอร์มและทำความสะอาดข้อมูล
// -------------------------------------------------------------------------
$recipe_id = isset($_POST['recipe_id']) ? (int)$_POST['recipe_id'] : null;
$recipe_name = trim($_POST['recipe_name'] ?? '');

function get_int_or_null($value) {
    return (isset($value) && $value !== '') ? (int)$value : null;
}

function get_int_or_default($value, $default = 0) {
    return (isset($value) && $value !== '') ? (int)$value : $default;
}

// --- ข้อมูลงานหลัก (Main Work) ---
$main_customer_id = get_int_or_null($_POST['main_customer_id']);
$main_input_rm_id = get_int_or_null($_POST['main_input_rm_id']);
$main_input_qty = get_int_or_null($_POST['main_input_qty']);
$main_output_part_id = get_int_or_null($_POST['main_output_part_id']);
$main_output_qty = get_int_or_null($_POST['main_output_qty']);
$main_cutting_operations = get_int_or_default($_POST['main_cutting_operations'], 0);
$main_split_operations = get_int_or_default($_POST['main_split_operations'], 0);

// --- ข้อมูลงานหัวไม้ (Head Wood) ---
$head_customer_id = get_int_or_null($_POST['head_customer_id'] ?? null);
$head_input_thickness = get_int_or_null($_POST['head_input_thickness'] ?? null);
$head_input_width = get_int_or_null($_POST['head_input_width'] ?? null);
$head_input_length = get_int_or_null($_POST['head_input_length'] ?? null);
$head_input_qty = get_int_or_null($_POST['head_input_qty'] ?? null);
$head_output_part_id = get_int_or_null($_POST['head_output_part_id'] ?? null);
$head_output_qty = get_int_or_null($_POST['head_output_qty'] ?? null);
$head_cutting_operations = get_int_or_default($_POST['head_cutting_operations'] ?? 0, 0);
$head_split_operations = get_int_or_default($_POST['head_split_operations'] ?? 0, 0);

// --- ข้อมูลงานเศษไม้ (Scrap Wood) ---
$scrap_customer_id = get_int_or_null($_POST['scrap_customer_id'] ?? null);
$scrap_input_thickness = get_int_or_null($_POST['scrap_input_thickness'] ?? null);
$scrap_input_width = get_int_or_null($_POST['scrap_input_width'] ?? null);
$scrap_input_length = get_int_or_null($_POST['scrap_input_length'] ?? null);
$scrap_input_qty = get_int_or_null($_POST['scrap_input_qty'] ?? null);
$scrap_output_part_id = get_int_or_null($_POST['scrap_output_part_id'] ?? null);
$scrap_output_qty = get_int_or_null($_POST['scrap_output_qty'] ?? null);
$scrap_cutting_operations = get_int_or_default($_POST['scrap_cutting_operations'] ?? 0, 0);
$scrap_split_operations = get_int_or_default($_POST['scrap_split_operations'] ?? 0, 0);


// -------------------------------------------------------------------------
// 4. คำนวณค่าสรุปฝั่ง Server (เพื่อความปลอดภัยและถูกต้อง)
// -------------------------------------------------------------------------
function get_m3_from_db($conn, $table, $id_column, $id_value) {
    if (empty($id_value)) return 0;
    $m3_column = ($table === 'rm_wood_list') ? 'rm_m3' : 'part_m3';
    $stmt = $conn->prepare("SELECT $m3_column FROM $table WHERE $id_column = ?");
    $stmt->bind_param("i", $id_value);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? (float)$row[$m3_column] : 0;
}

$main_input_m3 = get_m3_from_db($conn, 'rm_wood_list', 'rm_id', $main_input_rm_id) * ($main_input_qty ?: 0);
$head_input_m3 = (($head_input_thickness ?: 0) * ($head_input_width ?: 0) * ($head_input_length ?: 0) / 1000000000) * ($head_input_qty ?: 0);
$scrap_input_m3 = (($scrap_input_thickness ?: 0) * ($scrap_input_width ?: 0) * ($scrap_input_length ?: 0) / 1000000000) * ($scrap_input_qty ?: 0);
$total_m3_input = $main_input_m3 + $head_input_m3 + $scrap_input_m3;

$main_output_m3 = get_m3_from_db($conn, 'part_list', 'part_id', $main_output_part_id) * ($main_output_qty ?: 0);
$head_output_m3 = get_m3_from_db($conn, 'part_list', 'part_id', $head_output_part_id) * ($head_output_qty ?: 0);
$scrap_output_m3 = get_m3_from_db($conn, 'part_list', 'part_id', $scrap_output_part_id) * ($scrap_output_qty ?: 0);
$total_m3_output = $main_output_m3 + $head_output_m3 + $scrap_output_m3;

$total_m3_loss = $total_m3_input - $total_m3_output;
$total_loss_percent = ($total_m3_input > 0) ? ($total_m3_loss / $total_m3_input) * 100 : 0;


// -------------------------------------------------------------------------
// 5. บันทึกข้อมูลลงฐานข้อมูล (INSERT หรือ UPDATE)
// -------------------------------------------------------------------------
if ($recipe_id) {
    // --- โหมดแก้ไข (UPDATE) ---
    $sql = "UPDATE recipe_list SET 
                recipe_name = ?,
                main_customer_id = ?, main_input_rm_id = ?, main_input_qty = ?, main_output_part_id = ?, main_output_qty = ?, main_cutting_operations = ?, main_split_operations = ?,
                head_customer_id = ?, head_input_thickness = ?, head_input_width = ?, head_input_length = ?, head_input_qty = ?, head_output_part_id = ?, head_output_qty = ?, head_cutting_operations = ?, head_split_operations = ?,
                scrap_customer_id = ?, scrap_input_thickness = ?, scrap_input_width = ?, scrap_input_length = ?, scrap_input_qty = ?, scrap_output_part_id = ?, scrap_output_qty = ?, scrap_cutting_operations = ?, scrap_split_operations = ?,
                total_m3_input = ?, total_m3_output = ?, total_m3_loss = ?, total_loss_percent = ?
            WHERE recipe_id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        $_SESSION['error_message'] = "SQL Error (UPDATE prepare): " . $conn->error;
        header("Location: recipe_list.php");
        exit();
    }
    
    // [แก้ไข] แก้ไข Type String ให้ถูกต้องเป็น 32 ตัวอักษร (s + 26i + 4d + 1i)
    $stmt->bind_param("siiiiiiiiiiiiiiiiiiiiiiiiiddddi", 
        $recipe_name,
        $main_customer_id, $main_input_rm_id, $main_input_qty, $main_output_part_id, $main_output_qty, $main_cutting_operations, $main_split_operations,
        $head_customer_id, $head_input_thickness, $head_input_width, $head_input_length, $head_input_qty, $head_output_part_id, $head_output_qty, $head_cutting_operations, $head_split_operations,
        $scrap_customer_id, $scrap_input_thickness, $scrap_input_width, $scrap_input_length, $scrap_input_qty, $scrap_output_part_id, $scrap_output_qty, $scrap_cutting_operations, $scrap_split_operations,
        $total_m3_input, $total_m3_output, $total_m3_loss, $total_loss_percent,
        $recipe_id
    );

} else {
    // --- โหมดเพิ่มใหม่ (INSERT) ---
    $sql = "INSERT INTO recipe_list (
                recipe_name,
                main_customer_id, main_input_rm_id, main_input_qty, main_output_part_id, main_output_qty, main_cutting_operations, main_split_operations,
                head_customer_id, head_input_thickness, head_input_width, head_input_length, head_input_qty, head_output_part_id, head_output_qty, head_cutting_operations, head_split_operations,
                scrap_customer_id, scrap_input_thickness, scrap_input_width, scrap_input_length, scrap_input_qty, scrap_output_part_id, scrap_output_qty, scrap_cutting_operations, scrap_split_operations,
                total_m3_input, total_m3_output, total_m3_loss, total_loss_percent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        $_SESSION['error_message'] = "SQL Error (INSERT prepare): " . $conn->error;
        header("Location: recipe_list.php");
        exit();
    }
    
    // Type String ที่ถูกต้องสำหรับ INSERT คือ 31 ตัวอักษร (s + 26i + 4d)
    $stmt->bind_param("siiiiiiiiiiiiiiiiiiiiiiiiidddd", 
        $recipe_name,
        $main_customer_id, $main_input_rm_id, $main_input_qty, $main_output_part_id, $main_output_qty, $main_cutting_operations, $main_split_operations,
        $head_customer_id, $head_input_thickness, $head_input_width, $head_input_length, $head_input_qty, $head_output_part_id, $head_output_qty, $head_cutting_operations, $head_split_operations,
        $scrap_customer_id, $scrap_input_thickness, $scrap_input_width, $scrap_input_length, $scrap_input_qty, $scrap_output_part_id, $scrap_output_qty, $scrap_cutting_operations, $scrap_split_operations,
        $total_m3_input, $total_m3_output, $total_m3_loss, $total_loss_percent
    );
}

// -------------------------------------------------------------------------
// 6. สั่งทำงานและ Redirect
// -------------------------------------------------------------------------
if ($stmt->execute()) {
    $_SESSION['success_message'] = "บันทึกข้อมูลสูตรการผลิตเรียบร้อยแล้ว";
} else {
    // แสดงข้อผิดพลาดของ SQL ให้ชัดเจนขึ้น
    $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $stmt->error;
}

$stmt->close();
$conn->close();

header("Location: recipe_list.php");
exit();
