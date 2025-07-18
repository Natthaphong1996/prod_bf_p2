<?php
// create_cutting_job.php
// ภาษา: PHP
// หน้าที่: แสดงฟอร์มสำหรับสร้างใบงาน และส่งข้อมูลด้วย AJAX ไปยัง process_cutting_job.php

session_start();
require_once __DIR__ . '/config_db.php';

// ดึงข้อมูล Recipe พร้อมชื่อมาแสดงใน Dropdown
$recipe_sql = "SELECT recipe_id, recipe_name FROM recipe_list ORDER BY recipe_id DESC";
$recipe_result = $conn->query($recipe_sql);

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้างใบงานตัด (Create Cutting Job)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <style>
        .tu-input-group { margin-bottom: 0.5rem; }
        .select2-container .select2-selection--single { height: 38px; }
        .is-invalid .select2-selection { border-color: #dc3545 !important; }
    </style>
</head>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<div class="container mt-5 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>สร้างใบงานตัด (Create Cutting Job)</h1>
        <a href="cutting_job_list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> กลับไปหน้ารายการ
        </a>
    </div>

    <!-- ฟอร์มนี้จะถูกจัดการโดย JavaScript ทั้งหมด -->
    <form id="createJobForm" method="POST">
        <div class="card">
            <div class="card-header"><h4>ข้อมูลหลัก</h4></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="recipe_id" class="form-label">รูปแบบการตัด (Recipe)</label>
                        <select class="form-select" id="recipe_id" name="recipe_id" required>
                            <option value="" selected disabled>-- กรุณาเลือก Recipe --</option>
                            <?php
                            if ($recipe_result->num_rows > 0) {
                                while($row = $recipe_result->fetch_assoc()) {
                                    $displayText = htmlspecialchars($row['recipe_id']) . ' - ' . htmlspecialchars($row['recipe_name']);
                                    echo "<option value='" . $row['recipe_id'] . "'>" . $displayText . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="total_wood_logs" class="form-label">จำนวนไม้ท่อนที่ต้องการ</label>
                        <input type="number" class="form-control" id="total_wood_logs" name="total_wood_logs" min="1" required>
                    </div>
                </div>
                 <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="rm_needed_date" class="form-label">วันที่ต้องการรับไม้ในการตัดผ่า</label>
                        <input type="text" class="form-control" id="rm_needed_date" name="rm_needed_date" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="wip_receive_date" class="form-label">วันที่ต้องส่งงาน</label>
                        <input type="text" class="form-control" id="wip_receive_date" name="wip_receive_date" required>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header"><h4>ระบุจำนวนตั้ง (TU)</h4></div>
            <div class="card-body">
                <div id="tu_input_section"><p class="text-muted">กรุณาเลือก Recipe เพื่อแสดงข้อมูล</p></div>
            </div>
        </div>
        
        <div class="d-grid gap-2 mt-4">
             <button type="submit" id="submitBtn" class="btn btn-primary btn-lg">
                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                สร้างใบงาน
            </button>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    let currentRecipeData = null;

    // --- ตั้งค่าเริ่มต้นสำหรับ Plugin ต่างๆ ---
    $('#recipe_id').select2({ theme: 'bootstrap-5' });
    $('input[name="rm_needed_date"], input[name="wip_receive_date"]').daterangepicker({
        singleDatePicker: true, showDropdowns: true, autoApply: true,
        locale: { format: 'YYYY-MM-DD', daysOfWeek: ["อา", "จ", "อ", "พ", "พฤ", "ศ", "ส"], monthNames: ["มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"] }
    });

    // --- เมื่อมีการเลือก Recipe ---
    $('#recipe_id').change(function() {
        const recipeId = $(this).val();
        currentRecipeData = null; // เคลียร์ข้อมูลเก่าทุกครั้งที่เปลี่ยน
        if (!recipeId) {
            $('#tu_input_section').html('<p class="text-muted">กรุณาเลือก Recipe เพื่อแสดงข้อมูล</p>');
            return;
        }
        $.ajax({
            url: 'get_recipe_details_ajax.php',
            type: 'GET',
            data: { recipe_id: recipeId },
            dataType: 'json',
            success: function(data) {
                if (data.error) {
                    alert('Error: ' + data.error);
                } else {
                    currentRecipeData = data;
                    renderTuInputs(data);
                    updateExpectedQuantities(); // [เพิ่มใหม่] เรียกใช้ฟังก์ชันคำนวณจำนวนที่ได้
                }
            },
            error: function(xhr, status, error) {
                alert('An error occurred while fetching recipe data: ' + error);
            }
        });
    });

    // --- [แก้ไข] ฟังก์ชันสำหรับสร้างกล่องกรอกข้อมูล TU ---
    function renderTuInputs(recipeData) {
        const container = $('#tu_input_section');
        container.empty(); 
        const parts = [ { key: 'main', label: 'ส่วนงานหลัก' }, { key: 'head', label: 'ส่วนงานหัวไม้' }, { key: 'scrap', label: 'ส่วนงานเศษไม้' }];
        
        parts.forEach(part => {
            if (recipeData[part.key] && recipeData[part.key].output_part_id) {
                // [แก้ไข] เพิ่ม span สำหรับแสดง "จำนวนที่ได้" กลับเข้ามา
                let partHtml = `
                    <div class="mb-3" id="container-${part.key}">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5>${part.label} <small class="text-muted">(${recipeData[part.key].output_part_code})</small></h5>
                            <span class="badge bg-info" id="qty-${part.key}" style="font-size: 0.9rem;">จำนวนที่ได้: 0</span>
                        </div>
                        <div id="tu-list-${part.key}" class="mt-2"></div>
                        <button type="button" class="btn btn-success btn-sm add-tu-btn mt-2" data-part-key="${part.key}">
                            <i class="fas fa-plus"></i> เพิ่ม TU
                        </button>
                    </div>`;
                container.append(partHtml);
            }
        });
    }

    // --- [เพิ่มใหม่] ฟังก์ชันสำหรับคำนวณและอัปเดต "จำนวนที่ได้" ---
    function updateExpectedQuantities() {
        if (!currentRecipeData) {
            // ถ้าไม่มีข้อมูล recipe ให้เคลียร์ค่า
            $('.badge.bg-info').text('จำนวนที่ได้: 0');
            return;
        }

        const woodLogsQty = parseInt($('#total_wood_logs').val()) || 0;
        const parts = ['main', 'head', 'scrap'];

        parts.forEach(partKey => {
            if (currentRecipeData[partKey] && currentRecipeData[partKey].output_part_id) {
                const cutting_ops = parseInt(currentRecipeData[partKey].cutting_operations) || 0;
                const split_ops = parseInt(currentRecipeData[partKey].split_operations) || 0;
                
                // สำหรับงานหัวไม้และเศษไม้ ให้ใช้ input_qty ของตัวเองในการคำนวณ
                let inputQtyMultiplier = (partKey === 'main') ? woodLogsQty : (parseInt(currentRecipeData[partKey].input_qty) || 0) * woodLogsQty;
                
                const expectedQty = cutting_ops * split_ops * inputQtyMultiplier;
                
                $(`#qty-${partKey}`).text(`จำนวนที่ได้: ${expectedQty.toLocaleString()}`);
            }
        });
    }
    
    // --- จัดการการเพิ่ม TU ---
    $('#tu_input_section').on('click', '.add-tu-btn', function() {
        const partKey = $(this).data('part-key');
        const tuList = $(`#tu-list-${partKey}`);
        const tuIndex = tuList.children().length;
        const newTuInput = `
            <div class="input-group tu-input-group">
                <span class="input-group-text">TU ${tuIndex + 1}</span>
                <input type="number" class="form-control tu-input" name="tu[${partKey}][]" min="0" required>
                <button type="button" class="btn btn-danger remove-tu-btn"><i class="fas fa-trash"></i></button>
            </div>`;
        tuList.append(newTuInput);
    });

    // --- จัดการการลบ TU ---
    $('#tu_input_section').on('click', '.remove-tu-btn', function() {
        $(this).closest('.input-group').remove();
    });

    // --- [เพิ่มใหม่] เมื่อมีการเปลี่ยนจำนวนไม้ท่อน ให้คำนวณ "จำนวนที่ได้" ใหม่ ---
    $('#total_wood_logs').on('input', updateExpectedQuantities);

    // --- ฟังก์ชันสำหรับรีเซตฟอร์มทั้งหมด ---
    function resetPage() {
        $('#createJobForm')[0].reset();
        $('#recipe_id').val(null).trigger('change');
        $('#tu_input_section').html('<p class="text-muted">กรุณาเลือก Recipe เพื่อแสดงข้อมูล</p>');
    }

    // --- จัดการการ Submit ฟอร์มด้วย AJAX ---
    $('#createJobForm').on('submit', function(e) {
        e.preventDefault(); 

        let isValid = true;
        $('.is-invalid').removeClass('is-invalid'); 
        
        if (!$('#recipe_id').val()) {
            $('#recipe_id').next('.select2-container').addClass('is-invalid');
            isValid = false;
        }
        if (!$('#total_wood_logs').val() || $('#total_wood_logs').val() < 1) {
            $('#total_wood_logs').addClass('is-invalid');
            isValid = false;
        }
        if ($('.tu-input').length === 0) {
            alert('กรุณาเพิ่ม TU อย่างน้อย 1 รายการ');
            isValid = false;
        }
        if (!isValid) return;

        const submitBtn = $('#submitBtn');
        submitBtn.prop('disabled', true).find('.spinner-border').removeClass('d-none');
        const formData = $(this).serialize();

        $.ajax({
            url: 'process_cutting_job.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success && response.job_ids) {
                    response.job_ids.forEach(job_id => {
                        window.open('create_cutting_job_pdf.php?id=' + job_id, '_blank');
                    });
                    resetPage();
                } else {
                    alert('เกิดข้อผิดพลาดในการสร้างใบงาน: ' + (response.message || 'ไม่ทราบสาเหตุ'));
                }
            },
            error: function(xhr, status, error) {
                alert('เกิดข้อผิดพลาดไม่คาดคิด: ' + error);
            },
            complete: function() {
                submitBtn.prop('disabled', false).find('.spinner-border').addClass('d-none');
            }
        });
    });
});
</script>

</body>
</html>
<?php
$conn->close();
?>
