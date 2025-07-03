<?php
// ไฟล์: process_recipe.php
session_start();
header('Content-Type: application/json');
include 'config_db.php';

if (!isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid Action.']);
    exit();
}

$action = $_POST['action'];

// --- ★★★ อัปเดตรายชื่อฟิลด์ให้ตรงกับตารางล่าสุด ★★★ ---
$fields = [
    'rm_id', 'p_customer_id', 'p_part_id', 'cut_size', 'amountOfcut', 'remainingSize', 'amountOfSplit',
    's_customer_id', 's_part_id', 's_cut_size', 's_amountOfcut', 's_remainingSize', 's_amountOfsplit',
    's2_customer_id', 's2_part_id', 's2_cut_size', 's2_amountOfcut', 's2_remainingSize', 's2_amountOfsplit',
    'h_customer_id', 'h_part_id', 'h_cut_size', 'h_amountOfcut', 'h_remainingSize', 'h_amountOfsplit',
    'hs_customer_id', 'hs_part_id', 'hs_cut_size', 'hs_amountOfcut', 'hs_remainingSize', 'hs_amountOfsplit'
];

switch ($action) {
    case 'add':
    case 'edit':
        $data = [];
        foreach ($fields as $field) {
            $value = filter_input(INPUT_POST, $field, FILTER_SANITIZE_NUMBER_INT);
            $data[$field] = ($value === '' || $value === false) ? NULL : $value;
        }

        if ($action === 'add') {
            $columns = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $types = str_repeat('i', count($data));

            $stmt = $conn->prepare("INSERT INTO recipe_list ($columns) VALUES ($placeholders)");
            $stmt->bind_param($types, ...array_values($data));
            $success_message = 'สร้างสูตรการผลิตใหม่สำเร็จ';
            $error_message = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ';

        } else { // action === 'edit'
            $recipe_id = filter_input(INPUT_POST, 'recipe_id', FILTER_SANITIZE_NUMBER_INT);
            if (empty($recipe_id)) {
                echo json_encode(['success' => false, 'message' => 'Recipe ID is missing.']);
                exit();
            }

            $update_fields = [];
            foreach ($data as $key => $value) {
                $update_fields[] = "$key = ?";
            }
            $update_string = implode(', ', $update_fields);
            $types = str_repeat('i', count($data)) . 'i';
            $values = array_values($data);
            $values[] = $recipe_id;

            $stmt = $conn->prepare("UPDATE recipe_list SET $update_string WHERE recipe_id = ?");
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
        $stmt = $conn->prepare("SELECT * FROM recipe_list WHERE recipe_id = ?");
        $stmt->bind_param("i", $recipe_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        
        if ($data) {
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูล']);
        }
        $stmt->close();
        break;

    case 'delete':
        $recipe_id = filter_input(INPUT_POST, 'recipe_id', FILTER_SANITIZE_NUMBER_INT);
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
