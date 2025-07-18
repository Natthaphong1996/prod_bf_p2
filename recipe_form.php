<?php
// recipe_form.php
session_start();

// -------------------------------------------------------------------------
// 1. เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล
// -------------------------------------------------------------------------
require_once __DIR__ . '/config_db.php';

// -------------------------------------------------------------------------
// 2. ตรวจสอบโหมดการทำงาน (เพิ่ม หรือ แก้ไข)
// -------------------------------------------------------------------------
$recipe = [];
$edit_mode = false;
$page_title = "เพิ่มสูตรการผลิตใหม่";
$form_action = "process_recipe.php";

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $edit_mode = true;
    $recipe_id = (int)$_GET['id'];
    $page_title = "แก้ไขสูตรการผลิต ID: {$recipe_id}";
    
    $stmt = $conn->prepare("SELECT * FROM recipe_list WHERE recipe_id = ?");
    $stmt->bind_param("i", $recipe_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $recipe = $result->fetch_assoc();
    $stmt->close();

    if (!$recipe) {
        $_SESSION['error_message'] = "ไม่พบสูตรการผลิตที่ต้องการแก้ไข";
        header("Location: recipe_list.php");
        exit();
    }
}

// -------------------------------------------------------------------------
// 3. ดึงข้อมูลสำหรับ Dropdowns
// -------------------------------------------------------------------------
// ดึงรายการไม้ท่อน (RM) พร้อมข้อมูลทั้งหมด
$rm_list_result = $conn->query("SELECT rm_id, rm_code, rm_m3, rm_thickness, rm_width, rm_length, rm_type FROM rm_wood_list ORDER BY rm_code ASC");
$rm_options = [];
while ($row = $rm_list_result->fetch_assoc()) {
    $rm_options[] = $row;
}

// ดึงรายการชิ้นส่วน (Part) พร้อมข้อมูลทั้งหมด
$part_list_result = $conn->query("SELECT part_id, part_code, part_m3, part_thickness, part_width, part_length, part_type FROM part_list ORDER BY part_code ASC");
$part_options = [];
while ($row = $part_list_result->fetch_assoc()) {
    $part_options[] = $row;
}

// ดึงรายการลูกค้า (Customer)
$customer_list_result = $conn->query("SELECT customer_id, customer_name FROM customer ORDER BY customer_name ASC");
$customer_options = [];
while ($row = $customer_list_result->fetch_assoc()) {
    $customer_options[] = $row;
}

// ปิดการเชื่อมต่อฐานข้อมูลหลังจากดึงข้อมูลเสร็จ
$conn->close();

// [เพิ่มใหม่] ตรวจสอบว่าควรเปิดส่วนหัวไม้/เศษไม้ ในโหมดแก้ไขหรือไม่
$is_head_wood_active = $edit_mode && !empty($recipe['head_output_part_id']);
$is_scrap_wood_active = $edit_mode && !empty($recipe['scrap_output_part_id']);

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <style>
        .card-header { font-weight: bold; }
        .form-section { margin-bottom: 1.5rem; }
        .select2-container .select2-selection--single { height: 38px; }
        .form-check-input:checked { background-color: #198754; border-color: #198754; }
    </style>
</head>
<body>

    <?php include __DIR__ . '/navbar.php'; ?>

    <div class="container mt-5 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-edit"></i> <?php echo $page_title; ?></h1>
            <a href="recipe_list.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> กลับไปหน้ารายการ
            </a>
        </div>

        <form id="recipeForm" action="process_recipe.php" method="POST">
            <?php if ($edit_mode): ?>
                <input type="hidden" name="recipe_id" value="<?php echo $recipe['recipe_id']; ?>">
            <?php endif; ?>

            <!-- ข้อมูลทั่วไป -->
            <div class="card form-section">
                <div class="card-header bg-primary text-white">ข้อมูลทั่วไป</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="recipe_name" class="form-label">ชื่อสูตร (Recipe Name)</label>
                        <input type="text" class="form-control" id="recipe_name" name="recipe_name" value="<?php echo htmlspecialchars($recipe['recipe_name'] ?? ''); ?>" required>
                    </div>
                </div>
            </div>

            <!-- งานหลัก -->
            <div class="card form-section">
                <div class="card-header bg-success text-white">ส่วนงานหลัก (Main Work)</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Input</h5>
                            <div class="mb-3">
                                <label for="main_customer_id" class="form-label">ลูกค้า</label>
                                <select class="form-select" id="main_customer_id" name="main_customer_id">
                                    <option value="">-- เลือกลูกค้า --</option>
                                    <?php foreach ($customer_options as $option): ?>
                                        <option value="<?php echo $option['customer_id']; ?>" <?php echo (isset($recipe['main_customer_id']) && $recipe['main_customer_id'] == $option['customer_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($option['customer_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="main_input_rm_id" class="form-label">ไม้ท่อน (RM)</label>
                                <select class="form-select" id="main_input_rm_id" name="main_input_rm_id">
                                    <option value="">-- ไม่ระบุ --</option>
                                    <?php foreach ($rm_options as $option): ?>
                                        <?php 
                                            $displayText = htmlspecialchars($option['rm_code']) . ' (' .
                                                           htmlspecialchars($option['rm_thickness']) . 'x' .
                                                           htmlspecialchars($option['rm_width']) . 'x' .
                                                           htmlspecialchars($option['rm_length']) . ') - ' .
                                                           htmlspecialchars($option['rm_type']);
                                        ?>
                                        <option value="<?php echo $option['rm_id']; ?>" data-m3="<?php echo $option['rm_m3']; ?>" <?php echo (isset($recipe['main_input_rm_id']) && $recipe['main_input_rm_id'] == $option['rm_id']) ? 'selected' : ''; ?>>
                                            <?php echo $displayText; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="main_input_qty" class="form-label">จำนวน Input (Qty)</label>
                                <input type="number" class="form-control" id="main_input_qty" name="main_input_qty" value="<?php echo htmlspecialchars($recipe['main_input_qty'] ?? '1'); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h5>Output</h5>
                            <div class="mb-3">
                                <label for="main_output_part_id" class="form-label">ชิ้นงาน (Part)</label>
                                <select class="form-select" id="main_output_part_id" name="main_output_part_id">
                                    <option value="">-- ไม่ระบุ --</option>
                                    <?php foreach ($part_options as $option): ?>
                                        <?php 
                                            $displayText = htmlspecialchars($option['part_code']) . ' (' .
                                                           htmlspecialchars($option['part_thickness']) . 'x' .
                                                           htmlspecialchars($option['part_width']) . 'x' .
                                                           htmlspecialchars($option['part_length']) . ') - ' .
                                                           htmlspecialchars($option['part_type']);
                                        ?>
                                        <option value="<?php echo $option['part_id']; ?>" data-m3="<?php echo $option['part_m3']; ?>" <?php echo (isset($recipe['main_output_part_id']) && $recipe['main_output_part_id'] == $option['part_id']) ? 'selected' : ''; ?>>
                                            <?php echo $displayText; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="main_output_qty" class="form-label">จำนวน Output (Qty)</label>
                                <input type="number" class="form-control" id="main_output_qty" name="main_output_qty" value="<?php echo htmlspecialchars($recipe['main_output_qty'] ?? '0'); ?>">
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label for="main_cutting_operations" class="form-label">Cutting</label>
                                    <input type="number" class="form-control" id="main_cutting_operations" name="main_cutting_operations" value="<?php echo htmlspecialchars($recipe['main_cutting_operations'] ?? '0'); ?>">
                                </div>
                                <div class="col-6">
                                    <label for="main_split_operations" class="form-label">Split</label>
                                    <input type="number" class="form-control" id="main_split_operations" name="main_split_operations" value="<?php echo htmlspecialchars($recipe['main_split_operations'] ?? '0'); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- งานหัวไม้ -->
            <div class="card form-section" id="head_wood_section">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <span>ส่วนงานหัวไม้ (Head Wood)</span>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="use_head_wood" data-bs-toggle="collapse" data-bs-target="#collapseHeadWood" <?php echo $is_head_wood_active ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="use_head_wood">เปิดใช้งาน</label>
                    </div>
                </div>
                <div class="collapse <?php echo $is_head_wood_active ? 'show' : ''; ?>" id="collapseHeadWood">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <!-- [แก้ไข] กลับไปใช้ข้อความเดิม -->
                                <h5>Input</h5>
                                <div class="mb-3">
                                    <label for="head_customer_id" class="form-label">ลูกค้า</label>
                                    <select class="form-select" id="head_customer_id" name="head_customer_id">
                                        <option value="">-- เลือกลูกค้า --</option>
                                        <?php foreach ($customer_options as $option): ?>
                                            <option value="<?php echo $option['customer_id']; ?>" <?php echo (isset($recipe['head_customer_id']) && $recipe['head_customer_id'] == $option['customer_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($option['customer_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">ขนาด Input (หนา x กว้าง x ยาว)</label>
                                    <div class="row g-2">
                                        <div class="col-sm-4"><input type="number" class="form-control" name="head_input_thickness" id="head_input_thickness" placeholder="หนา" value="<?php echo htmlspecialchars($recipe['head_input_thickness'] ?? ''); ?>"></div>
                                        <div class="col-sm-4"><input type="number" class="form-control" name="head_input_width" id="head_input_width" placeholder="กว้าง" value="<?php echo htmlspecialchars($recipe['head_input_width'] ?? ''); ?>"></div>
                                        <div class="col-sm-4"><input type="number" class="form-control" name="head_input_length" id="head_input_length" placeholder="ยาว" value="<?php echo htmlspecialchars($recipe['head_input_length'] ?? ''); ?>"></div>
                                    </div>
                                </div>
                                 <div class="mb-3">
                                    <label for="head_input_qty" class="form-label">จำนวน Input (Qty)</label>
                                    <input type="number" class="form-control" id="head_input_qty" name="head_input_qty" value="<?php echo htmlspecialchars($recipe['head_input_qty'] ?? '0'); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h5>Output</h5>
                                <div class="mb-3">
                                    <label for="head_output_part_id" class="form-label">ชิ้นงาน (Part)</label>
                                    <select class="form-select" id="head_output_part_id" name="head_output_part_id">
                                       <option value="">-- ไม่ระบุ --</option>
                                       <?php foreach ($part_options as $option): ?>
                                            <?php 
                                                $displayText = htmlspecialchars($option['part_code']) . ' (' .
                                                               htmlspecialchars($option['part_thickness']) . 'x' .
                                                               htmlspecialchars($option['part_width']) . 'x' .
                                                               htmlspecialchars($option['part_length']) . ') - ' .
                                                               htmlspecialchars($option['part_type']);
                                            ?>
                                            <option value="<?php echo $option['part_id']; ?>" data-m3="<?php echo $option['part_m3']; ?>" <?php echo (isset($recipe['head_output_part_id']) && $recipe['head_output_part_id'] == $option['part_id']) ? 'selected' : ''; ?>>
                                                <?php echo $displayText; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="head_output_qty" class="form-label">จำนวน Output (Qty)</label>
                                    <input type="number" class="form-control" id="head_output_qty" name="head_output_qty" value="<?php echo htmlspecialchars($recipe['head_output_qty'] ?? '0'); ?>">
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label for="head_cutting_operations" class="form-label">Cutting</label>
                                        <input type="number" class="form-control" id="head_cutting_operations" name="head_cutting_operations" value="<?php echo htmlspecialchars($recipe['head_cutting_operations'] ?? '0'); ?>">
                                    </div>
                                    <div class="col-6">
                                        <label for="head_split_operations" class="form-label">Split</label>
                                        <input type="number" class="form-control" id="head_split_operations" name="head_split_operations" value="<?php echo htmlspecialchars($recipe['head_split_operations'] ?? '0'); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- งานเศษไม้ -->
            <div class="card form-section" id="scrap_wood_section">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                     <span>ส่วนงานเศษไม้ (Scrap Wood)</span>
                     <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="use_scrap_wood" data-bs-toggle="collapse" data-bs-target="#collapseScrapWood" <?php echo $is_scrap_wood_active ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="use_scrap_wood">เปิดใช้งาน</label>
                    </div>
                </div>
                <div class="collapse <?php echo $is_scrap_wood_active ? 'show' : ''; ?>" id="collapseScrapWood">
                    <div class="card-body">
                         <div class="row">
                            <div class="col-md-6">
                                <!-- [แก้ไข] กลับไปใช้ข้อความเดิม -->
                                <h5>Input</h5>
                                <div class="mb-3">
                                    <label for="scrap_customer_id" class="form-label">ลูกค้า</label>
                                    <select class="form-select" id="scrap_customer_id" name="scrap_customer_id">
                                        <option value="">-- เลือกลูกค้า --</option>
                                        <?php foreach ($customer_options as $option): ?>
                                            <option value="<?php echo $option['customer_id']; ?>" <?php echo (isset($recipe['scrap_customer_id']) && $recipe['scrap_customer_id'] == $option['customer_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($option['customer_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">ขนาด Input (หนา x กว้าง x ยาว)</label>
                                    <div class="row g-2">
                                        <div class="col-sm-4"><input type="number" class="form-control" name="scrap_input_thickness" id="scrap_input_thickness" placeholder="หนา" value="<?php echo htmlspecialchars($recipe['scrap_input_thickness'] ?? ''); ?>"></div>
                                        <div class="col-sm-4"><input type="number" class="form-control" name="scrap_input_width" id="scrap_input_width" placeholder="กว้าง" value="<?php echo htmlspecialchars($recipe['scrap_input_width'] ?? ''); ?>"></div>
                                        <div class="col-sm-4"><input type="number" class="form-control" name="scrap_input_length" id="scrap_input_length" placeholder="ยาว" value="<?php echo htmlspecialchars($recipe['scrap_input_length'] ?? ''); ?>"></div>
                                    </div>
                                </div>
                                 <div class="mb-3">
                                    <label for="scrap_input_qty" class="form-label">จำนวน Input (Qty)</label>
                                    <input type="number" class="form-control" id="scrap_input_qty" name="scrap_input_qty" value="<?php echo htmlspecialchars($recipe['scrap_input_qty'] ?? '0'); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h5>Output</h5>
                                <div class="mb-3">
                                    <label for="scrap_output_part_id" class="form-label">ชิ้นงาน (Part)</label>
                                    <select class="form-select" id="scrap_output_part_id" name="scrap_output_part_id">
                                       <option value="">-- ไม่ระบุ --</option>
                                       <?php foreach ($part_options as $option): ?>
                                            <?php 
                                                $displayText = htmlspecialchars($option['part_code']) . ' (' .
                                                               htmlspecialchars($option['part_thickness']) . 'x' .
                                                               htmlspecialchars($option['part_width']) . 'x' .
                                                               htmlspecialchars($option['part_length']) . ') - ' .
                                                               htmlspecialchars($option['part_type']);
                                            ?>
                                            <option value="<?php echo $option['part_id']; ?>" data-m3="<?php echo $option['part_m3']; ?>" <?php echo (isset($recipe['scrap_output_part_id']) && $recipe['scrap_output_part_id'] == $option['part_id']) ? 'selected' : ''; ?>>
                                                <?php echo $displayText; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="scrap_output_qty" class="form-label">จำนวน Output (Qty)</label>
                                    <input type="number" class="form-control" id="scrap_output_qty" name="scrap_output_qty" value="<?php echo htmlspecialchars($recipe['scrap_output_qty'] ?? '0'); ?>">
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label for="scrap_cutting_operations" class="form-label">Cutting</label>
                                        <input type="number" class="form-control" id="scrap_cutting_operations" name="scrap_cutting_operations" value="<?php echo htmlspecialchars($recipe['scrap_cutting_operations'] ?? '0'); ?>">
                                    </div>
                                    <div class="col-6">
                                        <label for="scrap_split_operations" class="form-label">Split</label>
                                        <input type="number" class="form-control" id="scrap_split_operations" name="scrap_split_operations" value="<?php echo htmlspecialchars($recipe['scrap_split_operations'] ?? '0'); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- สรุป -->
            <div class="card form-section">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <span>สรุปปริมาตร (m³)</span>
                    <button type="button" id="calculateBtn" class="btn btn-light btn-sm">
                        <i class="fas fa-calculator"></i> คำนวณ
                    </button>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3"><label class="form-label">Total Input (m³)</label><input type="text" class="form-control" id="total_m3_input" name="total_m3_input" value="<?php echo htmlspecialchars($recipe['total_m3_input'] ?? '0'); ?>" readonly></div>
                        <div class="col-md-3"><label class="form-label">Total Output (m³)</label><input type="text" class="form-control" id="total_m3_output" name="total_m3_output" value="<?php echo htmlspecialchars($recipe['total_m3_output'] ?? '0'); ?>" readonly></div>
                        <div class="col-md-3"><label class="form-label">Total Loss (m³)</label><input type="text" class="form-control" id="total_m3_loss" name="total_m3_loss" value="<?php echo htmlspecialchars($recipe['total_m3_loss'] ?? '0'); ?>" readonly></div>
                        <div class="col-md-3"><label class="form-label">Loss (%)</label><input type="text" class="form-control" id="total_loss_percent" name="total_loss_percent" value="<?php echo htmlspecialchars($recipe['total_loss_percent'] ?? '0'); ?>" readonly></div>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> บันทึกข้อมูล
                </button>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Select2
            $('.form-select').select2({
                theme: 'bootstrap-5'
            });

            // --- Function to toggle section inputs ---
            function toggleSectionInputs(sectionId, isEnabled) {
                const section = $(sectionId);
                section.find('input, select').each(function() {
                    if (!$(this).is('.form-check-input')) {
                        $(this).prop('disabled', !isEnabled);
                    }
                });

                if (!isEnabled) {
                    section.find('input[type="number"]').val('0');
                    section.find('input[type="text"]').val('');
                    section.find('select').val('').trigger('change');
                }
            }

            // --- Event listeners for checkboxes ---
            $('#use_head_wood').on('change', function() {
                toggleSectionInputs('#collapseHeadWood', $(this).is(':checked'));
            });

            $('#use_scrap_wood').on('change', function() {
                toggleSectionInputs('#collapseScrapWood', $(this).is(':checked'));
            });
            
            // --- Initial state setup on page load ---
            toggleSectionInputs('#collapseHeadWood', $('#use_head_wood').is(':checked'));
            toggleSectionInputs('#collapseScrapWood', $('#use_scrap_wood').is(':checked'));


            // Function to calculate all summaries
            function calculateSummary() {
                // --- [แก้ไข] กลับไปใช้การคำนวณ m³ Input แบบเดิม ---
                let main_input_m3 = (parseFloat($('#main_input_rm_id').find(':selected').data('m3')) || 0) * (parseInt($('#main_input_qty').val()) || 0);
                
                let head_input_m3 = 0;
                if ($('#use_head_wood').is(':checked')) {
                    // คำนวณ m3 จากขนาดที่กรอกเอง
                    head_input_m3 = ( (parseInt($('#head_input_thickness').val()) || 0) * (parseInt($('#head_input_width').val()) || 0) * (parseInt($('#head_input_length').val()) || 0) / 1000000000 ) * (parseInt($('#head_input_qty').val()) || 0);
                }
                
                let scrap_input_m3 = 0;
                if ($('#use_scrap_wood').is(':checked')) {
                    // คำนวณ m3 จากขนาดที่กรอกเอง
                    scrap_input_m3 = ( (parseInt($('#scrap_input_thickness').val()) || 0) * (parseInt($('#scrap_input_width').val()) || 0) * (parseInt($('#scrap_input_length').val()) || 0) / 1000000000 ) * (parseInt($('#scrap_input_qty').val()) || 0);
                }

                let total_input = main_input_m3 + head_input_m3 + scrap_input_m3;
                
                // --- Output Calculation ---
                let main_output_m3 = (parseFloat($('#main_output_part_id').find(':selected').data('m3')) || 0) * (parseInt($('#main_output_qty').val()) || 0);
                
                let head_output_m3 = 0;
                if ($('#use_head_wood').is(':checked')) {
                    head_output_m3 = (parseFloat($('#head_output_part_id').find(':selected').data('m3')) || 0) * (parseInt($('#head_output_qty').val()) || 0);
                }

                let scrap_output_m3 = 0;
                if ($('#use_scrap_wood').is(':checked')) {
                    scrap_output_m3 = (parseFloat($('#scrap_output_part_id').find(':selected').data('m3')) || 0) * (parseInt($('#scrap_output_qty').val()) || 0);
                }
                
                let total_output = main_output_m3 + head_output_m3 + scrap_output_m3;

                // --- Loss Calculation ---
                let total_loss = total_input - total_output;
                let loss_percent = (total_input > 0) ? (total_loss / total_input) * 100 : 0;

                // --- Update UI ---
                $('#total_m3_input').val(total_input.toFixed(6));
                $('#total_m3_output').val(total_output.toFixed(6));
                $('#total_m3_loss').val(total_loss.toFixed(6));
                $('#total_loss_percent').val(loss_percent.toFixed(2));
            }

            // ให้ฟังก์ชันทำงานเมื่อกดปุ่มเท่านั้น
            $('#calculateBtn').on('click', function() {
                calculateSummary();
            });

            // คำนวณครั้งแรกเมื่อเปิดหน้า (สำหรับโหมดแก้ไข)
            if (<?php echo $edit_mode ? 'true' : 'false'; ?>) {
                 calculateSummary();
            }
        });
    </script>
</body>
</html>
