<?php
// process_create_job.php
session_start();

// --- การเชื่อมต่อฐานข้อมูล ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "prod_bf_p2";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");


// --- ตรวจสอบว่าเป็น POST request หรือไม่ ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- รับและตรวจสอบข้อมูล ---
    $recipe_id = isset($_POST['recipe_id']) ? intval($_POST['recipe_id']) : 0;
    $total_wood_logs = isset($_POST['total_wood_logs']) ? intval($_POST['total_wood_logs']) : 0;
    $rm_needed_date = isset($_POST['rm_needed_date']) ? $_POST['rm_needed_date'] : null;
    $wip_receive_date = isset($_POST['wip_receive_date']) ? $_POST['wip_receive_date'] : null;
    $tu_details_raw = isset($_POST['tu']) ? $_POST['tu'] : [];

    // --- ตรวจสอบข้อมูลพื้นฐาน ---
    if ($recipe_id <= 0 || $total_wood_logs <= 0 || empty($rm_needed_date) || empty($wip_receive_date)) {
        $_SESSION['error_message'] = "ข้อมูลไม่ครบถ้วน กรุณากรอกข้อมูลให้สมบูรณ์";
        header("Location: create_cutting_job.php");
        exit();
    }
    
    // --- จัดการข้อมูล TU --- 
    $tu_details_json = json_encode($tu_details_raw);

    // --- คำนวณค่าสรุปฝั่ง Server เพื่อความปลอดภัย ---
    // (ดึงข้อมูล recipe มาอีกครั้งเพื่อใช้ในการคำนวณ)
    $sql_recipe = "SELECT rm.rm_m3, p.part_m3 AS p_m3, h.part_m3 AS h_m3, s.part_m3 AS s_m3, hs.part_m3 AS hs_m3, s2.part_m3 AS s2_m3 FROM recipe_list rl LEFT JOIN rm_wood_list rm ON rl.rm_id = rm.rm_id LEFT JOIN part_list p ON rl.p_part_id = p.part_id LEFT JOIN part_list h ON rl.h_part_id = h.part_id LEFT JOIN part_list s ON rl.s_part_id = s.part_id LEFT JOIN part_list hs ON rl.hs_part_id = hs.part_id LEFT JOIN part_list s2 ON rl.s2_part_id = s2.part_id WHERE rl.recipe_id = ?";
    $stmt_recipe = $conn->prepare($sql_recipe);
    $stmt_recipe->bind_param("i", $recipe_id);
    $stmt_recipe->execute();
    $recipe_data = $stmt_recipe->get_result()->fetch_assoc();
    $stmt_recipe->close();

    if (!$recipe_data) {
        $_SESSION['error_message'] = "ไม่พบ Recipe ID ที่ระบุ";
        header("Location: create_cutting_job.php");
        exit();
    }

    // คำนวณ Input
    $summary_input_m3 = $total_wood_logs * $recipe_data['rm_m3'];
    
    // คำนวณ Output
    $summary_output_m3 = 0;
    $part_keys = ['p', 'h', 's', 'hs', 's2'];
    foreach ($part_keys as $key) {
        if (isset($tu_details_raw[$key]) && is_array($tu_details_raw[$key])) {
            $total_tu_qty = array_sum($tu_details_raw[$key]);
            $summary_output_m3 += $total_tu_qty * $recipe_data[$key.'_m3'];
        }
    }
    
    // คำนวณ Loss
    $summary_loss_m3 = $summary_input_m3 - $summary_output_m3;
    $summary_loss_percent = $summary_input_m3 > 0 ? ($summary_loss_m3 / $summary_input_m3) * 100 : 0;


    // --- บันทึกข้อมูลลงฐานข้อมูล ---
    $sql_insert = "INSERT INTO cutting_job (recipe_id, total_wood_logs, tu_details, wip_receive_date, rm_needed_date, summary_input_m3, summary_output_m3, summary_loss_m3, summary_loss_percent, job_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'รอดำเนินการ')";
    
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param(
        "iisssdddd",
        $recipe_id,
        $total_wood_logs,
        $tu_details_json,
        $wip_receive_date,
        $rm_needed_date,
        $summary_input_m3,
        $summary_output_m3,
        $summary_loss_m3,
        $summary_loss_percent
    );

    if ($stmt_insert->execute()) {
        $_SESSION['success_message'] = "สร้างใบงานตัดสำเร็จ! Job ID: " . $conn->insert_id;
        header("Location: cutting_job_list.php");
    } else {
        $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $stmt_insert->error;
        header("Location: create_cutting_job.php");
    }

    $stmt_insert->close();
    $conn->close();

} else {
    // ถ้าไม่ใช่ POST request ให้กลับไปหน้าฟอร์ม
    header("Location: create_cutting_job.php");
    exit();
}
?>
