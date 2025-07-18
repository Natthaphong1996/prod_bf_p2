<?php
// get_recipe_details_ajax.php
header('Content-Type: application/json');
require_once __DIR__ . '/config_db.php';

$recipe_id = isset($_GET['recipe_id']) ? (int)$_GET['recipe_id'] : 0;

if (empty($recipe_id)) {
    echo json_encode(['error' => 'No Recipe ID provided.']);
    exit();
}

// SQL query to fetch all necessary details
$sql = "
    SELECT
        r.recipe_id, r.recipe_name,
        
        -- Main Work Details
        r.main_output_part_id, p_main.part_code as main_output_part_code, p_main.part_m3 as main_output_part_m3,
        r.main_cutting_operations, r.main_split_operations,
        rm_main.rm_m3 as main_input_rm_m3,
        
        -- Head Wood Details
        r.head_output_part_id, p_head.part_code as head_output_part_code, p_head.part_m3 as head_output_part_m3,
        r.head_input_qty, r.head_cutting_operations, r.head_split_operations,

        -- Scrap Wood Details
        r.scrap_output_part_id, p_scrap.part_code as scrap_output_part_code, p_scrap.part_m3 as scrap_output_part_m3,
        r.scrap_input_qty, r.scrap_cutting_operations, r.scrap_split_operations

    FROM recipe_list r
    LEFT JOIN rm_wood_list rm_main ON r.main_input_rm_id = rm_main.rm_id
    LEFT JOIN part_list p_main ON r.main_output_part_id = p_main.part_id
    LEFT JOIN part_list p_head ON r.head_output_part_id = p_head.part_id
    LEFT JOIN part_list p_scrap ON r.scrap_output_part_id = p_scrap.part_id

    WHERE r.recipe_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $recipe_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();

if (!$data) {
    echo json_encode(['error' => 'Recipe not found.']);
    exit();
}

// Build the response object
$response = [
    'recipe_id' => $data['recipe_id'],
    'recipe_name' => $data['recipe_name'],
    'main' => null,
    'head' => null,
    'scrap' => null,
];

// Process Main Work
if (!empty($data['main_output_part_id'])) {
    $response['main'] = [
        'input_rm_m3' => $data['main_input_rm_m3'],
        'output_part_id' => $data['main_output_part_id'],
        'output_part_code' => $data['main_output_part_code'],
        'output_part_m3' => $data['main_output_part_m3'],
        'cutting_operations' => $data['main_cutting_operations'],
        'split_operations' => $data['main_split_operations'],
    ];
}

// Process Head Wood
if (!empty($data['head_output_part_id'])) {
    $response['head'] = [
        'input_qty' => $data['head_input_qty'], // [FIX] เพิ่มข้อมูลนี้
        'output_part_id' => $data['head_output_part_id'],
        'output_part_code' => $data['head_output_part_code'],
        'output_part_m3' => $data['head_output_part_m3'],
        'cutting_operations' => $data['head_cutting_operations'],
        'split_operations' => $data['head_split_operations'],
    ];
}

// Process Scrap Wood
if (!empty($data['scrap_output_part_id'])) {
    $response['scrap'] = [
        'input_qty' => $data['scrap_input_qty'], // [FIX] เพิ่มข้อมูลนี้
        'output_part_id' => $data['scrap_output_part_id'],
        'output_part_code' => $data['scrap_output_part_code'],
        'output_part_m3' => $data['scrap_output_part_m3'],
        'cutting_operations' => $data['scrap_cutting_operations'],
        'split_operations' => $data['scrap_split_operations'],
    ];
}

echo json_encode($response);

$conn->close();
?>
