<?php
// ไฟล์: process_recipe.php (เวอร์ชันแก้ไขล่าสุด)
session_start();
header('Content-Type: application/json');
include 'config_db.php';

// ตรวจสอบการ Login และ Action ที่ส่งมา
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบก่อนดำเนินการ']);
    exit();
}
if (!isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบ Action ที่ร้องขอ']);
    exit();
}

$action = $_POST['action'];

// --- ★★★ [แก้ไข] ลบ 'total_m3' ออก เนื่องจากไม่มีในตารางฐานข้อมูล ★★★ ---
// 'i' = integer, 'd' = double/decimal
$field_map = [
    'rm_id' => 'i', 'p_customer_id' => 'i', 'p_part_id' => 'i', 'cut_size' => 'i', 'amountOfcut' => 'i', 'remainingSize' => 'd', 'amountOfSplit' => 'i', 'p_m3' => 'd',
    's_customer_id' => 'i', 's_part_id' => 'i', 's_cut_size' => 'i', 's_amountOfcut' => 'i', 's_remainingSize' => 'd', 's_amountOfsplit' => 'i', 's_m3' => 'd',
    's2_customer_id' => 'i', 's2_part_id' => 'i', 's2_cut_size' => 'i', 's2_amountOfcut' => 'i', 's2_remainingSize' => 'd', 's2_amountOfsplit' => 'i', 's2_m3' => 'd',
    'h_customer_id' => 'i', 'h_part_id' => 'i', 'h_cut_size' => 'i', 'h_amountOfcut' => 'i', 'h_remainingSize' => 'd', 'h_amountOfsplit' => 'i', 'h_m3' => 'd',
    'hs_customer_id' => 'i', 'hs_part_id' => 'i', 'hs_cut_size' => 'i', 'hs_amountOfcut' => 'i', 'hs_remainingSize' => 'd', 'hs_amountOfsplit' => 'i', 'hs_m3' => 'd',
    'total_loss' => 'd', 
    'total_loss_percent' => 'd'
];

switch ($action) {
    case 'add_recipe':
    case 'edit_recipe':
        $data = [];
        $types = '';
        
        foreach ($field_map as $field => $type) {
            $value = filter_input(INPUT_POST, $field, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            $data[$field] = ($value === '' || $value === null || $value === false) ? NULL : $value;
            $types .= $type;
        }

        if ($action === 'add_recipe') {
            $columns = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            
            $stmt = $conn->prepare("INSERT INTO recipe_list ($columns) VALUES ($placeholders)");
            if (!$stmt) {
                echo json_encode(['success' => false, 'message' => 'Prepare statement failed: ' . $conn->error]);
                exit();
            }
            $stmt->bind_param($types, ...array_values($data));
            $success_message = 'สร้างสูตรการผลิตใหม่สำเร็จ';
            $error_message = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ';

        } else { // action === 'edit_recipe'
            $recipe_id = filter_input(INPUT_POST, 'recipe_id', FILTER_SANITIZE_NUMBER_INT);
            if (empty($recipe_id)) {
                echo json_encode(['success' => false, 'message' => 'ไม่พบ Recipe ID สำหรับการแก้ไข']);
                exit();
            }

            $update_fields = [];
            foreach ($data as $key => $value) {
                $update_fields[] = "$key = ?";
            }
            $update_string = implode(', ', $update_fields);
            $types .= 'i';
            $values = array_values($data);
            $values[] = $recipe_id;

            $stmt = $conn->prepare("UPDATE recipe_list SET $update_string WHERE recipe_id = ?");
             if (!$stmt) {
                echo json_encode(['success' => false, 'message' => 'Prepare statement failed: ' . $conn->error]);
                exit();
            }
            $stmt->bind_param($types, ...$values);
            $success_message = 'อัปเดตข้อมูลสูตรการผลิตสำเร็จ';
            $error_message = 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล: ';
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => $success_message]);
        } else {
            echo json_encode(['success' => false, 'message' => $error_message . $stmt->error]);
        }
        $stmt->close();
        break;

    case 'get_recipe':
        $recipe_id = filter_input(INPUT_POST, 'recipe_id', FILTER_SANITIZE_NUMBER_INT);
        if (empty($recipe_id)) {
             echo json_encode(['success' => false, 'message' => 'ไม่ได้ระบุ Recipe ID']);
             exit();
        }
        $stmt = $conn->prepare("SELECT * FROM recipe_list WHERE recipe_id = ?");
        $stmt->bind_param("i", $recipe_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        
        if ($data) {
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลสูตรการผลิต ID: ' . $recipe_id]);
        }
        $stmt->close();
        break;

    case 'delete_recipe':
        $recipe_id = filter_input(INPUT_POST, 'recipe_id', FILTER_SANITIZE_NUMBER_INT);
        if (empty($recipe_id)) {
             echo json_encode(['success' => false, 'message' => 'ไม่ได้ระบุ Recipe ID']);
             exit();
        }
        $stmt = $conn->prepare("DELETE FROM recipe_list WHERE recipe_id = ?");
        $stmt->bind_param("i", $recipe_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'ลบข้อมูลสำเร็จ']);
        } else {
            echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการลบข้อมูล: ' . $stmt->error]);
        }
        $stmt->close();
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
        break;
}

$conn->close();
?>
