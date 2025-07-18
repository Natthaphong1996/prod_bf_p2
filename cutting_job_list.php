<?php
// cutting_job_list.php
// ภาษา: PHP
// หน้าที่: แสดงรายการงานตัด, เพิ่มฟังก์ชันรับเข้า/จัดเก็บงานที่เสร็จแล้ว (รองรับการรับเข้าบางส่วนและบังคับปิดงาน)
// และเพิ่ม Modal สำหรับรับค่า Min/Max ของ WIP Inventory หาก Part ID ยังไม่เคยมีในระบบ

session_start();
require_once __DIR__ . '/config_db.php'; // เชื่อมต่อฐานข้อมูล
require_once __DIR__ . '/functions.php'; // สำหรับฟังก์ชัน getUserThaiName หรือฟังก์ชันอื่นๆ ที่จำเป็น

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบหรือไม่
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); // ถ้าไม่ได้เข้าสู่ระบบ ให้เปลี่ยนเส้นทางไปหน้า login
    exit();
}

// [แก้ไข] ปรับปรุง SQL เพื่อดึงจำนวนที่รับเข้าแล้ว และคำนวณจำนวนที่ต้องผลิต
$sql = "
    SELECT
        cj.cutting_job_id,
        cj.job_code, -- ดึง job_code มาแสดง
        cj.part_type,
        cj.job_status,
        rl.recipe_name,
        -- ใช้ CASE เพื่อดึงข้อมูลลูกค้าที่ถูกต้องตามประเภทของงาน
        CASE cj.part_type
            WHEN 'main' THEN c_main.customer_name
            WHEN 'head' THEN c_head.customer_name
            WHEN 'scrap' THEN c_scrap.customer_name
            ELSE NULL
        END AS customer_name,
        -- ใช้ CASE เพื่อดึงข้อมูลชิ้นงานที่ได้ (Part) ที่ถูกต้อง และ Part ID
        CASE cj.part_type
            WHEN 'main' THEN p_main.part_code
            WHEN 'head' THEN p_head.part_code
            WHEN 'scrap' THEN p_scrap.part_code
            ELSE NULL
        END AS part_code,
        CASE cj.part_type
            WHEN 'main' THEN rl.main_output_part_id
            WHEN 'head' THEN rl.head_output_part_id
            WHEN 'scrap' THEN rl.scrap_output_part_id
            ELSE NULL
        END AS output_part_id, -- [เพิ่ม] ดึง output_part_id
        -- คำนวณจำนวนชิ้นงานที่ต้องผลิตตามสูตร: total_wood_logs * input_qty * cutting_operations * split_operations
        (
            cb.total_wood_logs *
            COALESCE(CASE cj.part_type
                WHEN 'main' THEN rl.main_input_qty
                WHEN 'head' THEN rl.head_input_qty
                WHEN 'scrap' THEN rl.scrap_input_qty
                ELSE 0
            END, 0) *
            COALESCE(CASE cj.part_type
                WHEN 'main' THEN rl.main_cutting_operations
                WHEN 'head' THEN rl.head_cutting_operations
                WHEN 'scrap' THEN rl.scrap_cutting_operations
                ELSE 0
            END, 0) *
            COALESCE(CASE cj.part_type
                WHEN 'main' THEN rl.main_split_operations
                WHEN 'head' THEN rl.head_split_operations
                WHEN 'scrap' THEN rl.scrap_split_operations
                ELSE 0
            END, 0)
        ) AS quantity_to_produce,
        -- [เพิ่ม] รวมจำนวนที่ผลิตเสร็จสิ้นไปแล้วจากตาราง cutting_jobs_complete
        COALESCE(SUM(cjc.prod_complete_qty), 0) AS current_completed_quantity
    FROM
        cutting_job AS cj
    JOIN
        cutting_batch AS cb ON cj.batch_id = cb.batch_id
    JOIN
        recipe_list AS rl ON cb.recipe_id = rl.recipe_id
    LEFT JOIN customer AS c_main ON rl.main_customer_id = c_main.customer_id
    LEFT JOIN part_list AS p_main ON rl.main_output_part_id = p_main.part_id
    LEFT JOIN customer AS c_head ON rl.head_customer_id = c_head.customer_id
    LEFT JOIN part_list AS p_head ON rl.head_output_part_id = p_head.part_id
    LEFT JOIN customer AS c_scrap ON rl.scrap_customer_id = c_scrap.customer_id
    LEFT JOIN part_list AS p_scrap ON rl.scrap_output_part_id = p_scrap.part_id
    LEFT JOIN cutting_jobs_complete AS cjc ON cj.job_code = cjc.job_id -- Join กับตารางบันทึกงานที่เสร็จสิ้น
    GROUP BY
        cj.cutting_job_id, cj.job_code, cj.part_type, cj.job_status, rl.recipe_name, 
        c_main.customer_name, c_head.customer_name, c_scrap.customer_name, 
        p_main.part_code, p_head.part_code, p_scrap.part_code, 
        rl.main_output_part_id, rl.head_output_part_id, rl.scrap_output_part_id, -- [เพิ่ม] Group by output_part_id
        cb.total_wood_logs, rl.main_input_qty, rl.head_input_qty, rl.scrap_input_qty, 
        rl.main_cutting_operations, rl.head_cutting_operations, rl.scrap_cutting_operations, 
        rl.main_split_operations, rl.head_split_operations, rl.scrap_split_operations
    ORDER BY
        cj.cutting_job_id DESC;
";
$result = $conn->query($sql);

// ดึงชื่อผู้ใช้ที่เข้าสู่ระบบ
$current_user_id = $_SESSION['user_id'];
$current_username = $_SESSION['username']; // สมมติว่ามี username ใน session ด้วย
$current_user_thainame = getUserThaiName($conn, $current_user_id); // ต้องมีฟังก์ชันนี้ใน functions.php หรือรวมไว้ที่นี่

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการงานตัด (Cutting Job List)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Custom CSS for better readability and spacing */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        .container {
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .table thead th {
            background-color: #343a40;
            color: white;
            border-bottom: 2px solid #dee2e6;
        }
        .table-hover tbody tr:hover {
            background-color: #f2f2f2;
        }
        .btn-primary, .btn-info {
            border-radius: 5px;
        }
        .badge {
            padding: 0.5em 0.7em;
            border-radius: 0.5rem;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<main class="container">
    <div class="d-flex justify-content-between align-items-center mb-4 mt-4">
        <h1><i class="fas fa-tasks"></i> รายการงานตัด</h1>
        <!-- ปุ่มสร้างงานควรจะลิงก์ไปหน้าเลือกสูตรการผลิต -->
        <a href="create_cutting_job.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> สร้างงานตัดใหม่
        </a>
    </div>

    <?php
    // แสดงข้อความแจ้งเตือน (Success/Error)
    if (isset($_SESSION['success_message'])) {
        echo "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($_SESSION['success_message']) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
        unset($_SESSION['success_message']);
    }
    if (isset($_SESSION['error_message'])) {
        echo "<div class='alert alert-danger alert-dismissible fade show' role='alert'>" . htmlspecialchars($_SESSION['error_message']) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
        unset($_SESSION['error_message']);
    }

    // [เพิ่ม] ตรวจสอบและแสดง Modal WIP Min/Max หากมีการ redirect มาพร้อมพารามิเตอร์
    if (isset($_GET['show_wip_modal']) && $_GET['show_wip_modal'] == 'true' && isset($_GET['part_id'])) {
        $modal_part_id = htmlspecialchars($_GET['part_id']);
        $modal_part_code = htmlspecialchars($_GET['part_code'] ?? 'N/A');
        echo "
        <script type='text/javascript'>
            document.addEventListener('DOMContentLoaded', function() {
                var wipMinMaxModal = new bootstrap.Modal(document.getElementById('wipMinMaxModal'));
                document.getElementById('wip_modal_part_id').value = '{$modal_part_id}';
                document.getElementById('wip_modal_part_code').value = '{$modal_part_code}';
                wipMinMaxModal.show();
            });
        </script>";
    }
    ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>เลขที่ Job</th>
                            <th>ประเภทงาน</th>
                            <th>ลูกค้า</th>
                            <th>ชิ้นงานที่ได้</th>
                            <th>จำนวนที่ผลิต (ชิ้น)</th>
                            <th>รับเข้าแล้ว (ชิ้น)</th>
                            <th>สถานะ</th>
                            <th class="text-center">พิมพ์</th>
                            <th class="text-center">รับเข้า/จัดเก็บ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result && $result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td><strong>" . htmlspecialchars($row['job_code'] ?? 'N/A') . "</strong></td>";
                                
                                // --- แปลง part_type เป็นภาษาไทย ---
                                $part_type_display = '';
                                switch ($row['part_type']) {
                                    case 'main':
                                        $part_type_display = 'งานหลัก';
                                        break;
                                    case 'head':
                                        $part_type_display = 'งานหัวไม้';
                                        break;
                                    case 'scrap':
                                        $part_type_display = 'เศษไม้';
                                        break;
                                    default:
                                        $part_type_display = htmlspecialchars(ucfirst($row['part_type']));
                                        break;
                                }
                                echo "<td>" . $part_type_display . "</td>";
                                
                                echo "<td>" . htmlspecialchars($row['customer_name'] ?? 'N/A') . "</td>";
                                echo "<td>" . htmlspecialchars($row['part_code'] ?? 'N/A') . "</td>";
                                echo "<td>" . htmlspecialchars($row['quantity_to_produce'] ?? 'N/A') . "</td>";
                                echo "<td>" . htmlspecialchars($row['current_completed_quantity'] ?? '0') . "</td>";
                                
                                // --- ส่วนแสดงผลสถานะ ---
                                $status_class = '';
                                switch ($row['job_status']) {
                                    case 'รอดำเนินการ': $status_class = 'bg-warning text-dark'; break;
                                    case 'กำลังดำเนินการ': $status_class = 'bg-primary text-white'; break;
                                    case 'เสร็จสิ้น': $status_class = 'bg-success text-white'; break;
                                    case 'ยกเลิก': $status_class = 'bg-danger text-white'; break;
                                    default: $status_class = 'bg-secondary text-white'; break;
                                }
                                echo "<td><span class='badge " . $status_class . "'>" . htmlspecialchars($row['job_status']) . "</span></td>";
                                
                                // --- ปุ่มพิมพ์ PDF ---
                                echo "<td class='text-center'><a href='create_cutting_job_pdf.php?id=" . $row['cutting_job_id'] . "' target='_blank' class='btn btn-info btn-sm'><i class='fas fa-print'></i></a></td>";
                                
                                // --- ปุ่มรับเข้า/จัดเก็บ ---
                                echo "<td class='text-center'>";
                                // [แก้ไข] เงื่อนไขการเปิดใช้งานปุ่ม: เปิดใช้งานถ้าสถานะไม่ใช่ 'ยกเลิก'
                                if ($row['job_status'] != 'ยกเลิก') { // สามารถกดได้เสมอ ยกเว้นสถานะยกเลิก
                                    // ตรวจสอบว่างานเสร็จสิ้นแล้วหรือไม่
                                    $is_completed = ($row['job_status'] == 'เสร็จสิ้น');
                                    $button_class = $is_completed ? 'btn-secondary' : 'btn-success';
                                    $button_disabled = $is_completed ? 'disabled' : ''; // ปิดปุ่มถ้าสถานะเป็น 'เสร็จสิ้น'
                                    $button_text = $is_completed ? 'เสร็จสิ้นแล้ว' : 'รับเข้า/จัดเก็บ';
                                    $icon_class = $is_completed ? 'fa-check-double' : 'fa-check-circle';

                                    echo "<button type='button' class='btn {$button_class} btn-sm' data-bs-toggle='modal' data-bs-target='#completeJobModal' 
                                            data-bs-id='" . htmlspecialchars($row['cutting_job_id']) . "' 
                                            data-bs-jobcode='" . htmlspecialchars($row['job_code']) . "'
                                            data-bs-partcode='" . htmlspecialchars($row['part_code'] ?? 'N/A') . "'
                                            data-bs-outputpartid='" . htmlspecialchars($row['output_part_id'] ?? '') . "' -- [เพิ่ม] ส่ง output_part_id
                                            data-bs-quantitytoproduce='" . htmlspecialchars($row['quantity_to_produce'] ?? 'N/A') . "'
                                            data-bs-currentcompleted='" . htmlspecialchars($row['current_completed_quantity'] ?? '0') . "'
                                            data-bs-jobstatus='" . htmlspecialchars($row['job_status']) . "'
                                            {$button_disabled}>
                                            <i class='fas {$icon_class}'></i> {$button_text}
                                        </button>";
                                } else {
                                    echo "<button type='button' class='btn btn-danger btn-sm' disabled><i class='fas fa-times-circle'></i> ยกเลิกแล้ว</button>";
                                }
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='9' class='text-center'>ไม่พบข้อมูลงานตัด</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Modal สำหรับรับเข้า/จัดเก็บงาน -->
<div class="modal fade" id="completeJobModal" tabindex="-1" aria-labelledby="completeJobModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="completeJobModalLabel"><i class="fas fa-check-circle"></i> รับเข้าและจัดเก็บงาน</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="cutting_save_completed_job.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="cutting_job_id" id="modal_cutting_job_id">
                    <input type="hidden" name="record_by_user_id" value="<?php echo htmlspecialchars($current_user_id); ?>">
                    <input type="hidden" name="record_by_username" value="<?php echo htmlspecialchars($current_username); ?>">
                    <input type="hidden" name="quantity_to_produce_hidden" id="modal_quantity_to_produce_hidden">
                    <input type="hidden" name="current_completed_hidden" id="modal_current_completed_hidden">
                    <input type="hidden" name="job_status_hidden" id="modal_job_status_hidden">
                    <input type="hidden" name="output_part_id_hidden" id="modal_output_part_id_hidden"> <!-- [เพิ่ม] Hidden input สำหรับ output_part_id -->
                    <input type="hidden" name="part_code_hidden" id="modal_part_code_hidden"> <!-- [เพิ่ม] Hidden input สำหรับ part_code -->


                    <div class="mb-3">
                        <label for="modal_job_code" class="form-label">เลขที่ Job:</label>
                        <input type="text" class="form-control" id="modal_job_code" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="modal_part_code" class="form-label">ชิ้นงานที่ได้:</label>
                        <input type="text" class="form-control" id="modal_part_code" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="modal_quantity_to_produce" class="form-label">จำนวนที่คาดว่าจะผลิต (ชิ้น):</label>
                        <input type="number" class="form-control" id="modal_quantity_to_produce" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="modal_current_completed" class="form-label">จำนวนที่รับเข้าแล้ว (ชิ้น):</label>
                        <input type="number" class="form-control" id="modal_current_completed" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="completed_quantity" class="form-label">จำนวนที่รับเข้าครั้งนี้ (ชิ้น): <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="completed_quantity" name="completed_quantity" required min="0">
                    </div>
                    <div class="mb-3">
                        <label for="assembly_point" class="form-label">จุดประกอบ/จัดเก็บ:</label>
                        <select class="form-select" id="assembly_point" name="assembly_point" required>
                            <option value="">-- เลือกจุดประกอบ/จัดเก็บ --</option>
                            <option value="เครื่องตัด 1">เครื่องตัด 1</option>
                            <option value="เครื่องตัด 2">เครื่องตัด 2</option>
                            <option value="เครื่องตัด 3">เครื่องตัด 3</option>
                            <option value="เครื่องผ่า 1">เครื่องผ่า 1</option>
                            <option value="เครื่องผ่า 2">เครื่องผ่า 2</option>
                            <option value="เครื่องผ่า 3">เครื่องผ่า 3</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="reason" class="form-label">หมายเหตุ:</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" placeholder="รายละเอียดเพิ่มเติม (ถ้ามี)"></textarea>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1" id="force_complete" name="force_complete">
                        <label class="form-check-label" for="force_complete">
                            <i class="fas fa-exclamation-triangle text-warning"></i> บังคับปิดงาน (แม้จำนวนยังไม่ครบ)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-success">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- [เพิ่ม] Modal สำหรับรับค่า Min/Max ของ WIP Inventory -->
<div class="modal fade" id="wipMinMaxModal" tabindex="-1" aria-labelledby="wipMinMaxModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="wipMinMaxModalLabel"><i class="fas fa-warehouse"></i> กำหนดค่า Min/Max สำหรับ WIP Inventory</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="save_wip_min_max.php" method="POST"> <!-- [เพิ่ม] ไฟล์ใหม่สำหรับบันทึก Min/Max -->
                <div class="modal-body">
                    <p>ชิ้นงานนี้ยังไม่เคยมีใน WIP Inventory กรุณากำหนดค่า Min และ Max สำหรับการจัดการสต็อก</p>
                    <input type="hidden" name="part_id" id="wip_modal_part_id">
                    <div class="mb-3">
                        <label for="wip_modal_part_code" class="form-label">รหัสชิ้นงาน:</label>
                        <input type="text" class="form-control" id="wip_modal_part_code" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="wip_min_qty" class="form-label">จำนวน Min (ชิ้น): <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="wip_min_qty" name="min_qty" required min="0">
                    </div>
                    <div class="mb-3">
                        <label for="wip_max_qty" class="form-label">จำนวน Max (ชิ้น): <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="wip_max_qty" name="max_qty" required min="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึก Min/Max</button>
                </div>
            </form>
        </div>
    </div>
</div>


<?php 
$conn->close();
include __DIR__ . '/footer.php';
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // JavaScript สำหรับส่งค่าไปยัง Modal เมื่อปุ่มถูกคลิก
    var completeJobModal = document.getElementById('completeJobModal');
    completeJobModal.addEventListener('show.bs.modal', function (event) {
        // Button that triggered the modal
        var button = event.relatedTarget; 
        // Extract info from data-bs-* attributes
        var cuttingJobId = button.getAttribute('data-bs-id');
        var jobCode = button.getAttribute('data-bs-jobcode');
        var partCode = button.getAttribute('data-bs-partcode');
        var outputPartId = button.getAttribute('data-bs-outputpartid'); // [เพิ่ม] รับ output_part_id
        var quantityToProduce = parseInt(button.getAttribute('data-bs-quantitytoproduce')); 
        var currentCompleted = parseInt(button.getAttribute('data-bs-currentcompleted')); 
        var jobStatus = button.getAttribute('data-bs-jobstatus'); 

        // Update the modal's content.
        var modalCuttingJobId = completeJobModal.querySelector('#modal_cutting_job_id');
        var modalJobCode = completeJobModal.querySelector('#modal_job_code');
        var modalPartCode = completeJobModal.querySelector('#modal_part_code');
        var modalQuantityToProduce = completeJobModal.querySelector('#modal_quantity_to_produce');
        var modalCurrentCompleted = completeJobModal.querySelector('#modal_current_completed');
        var completedQuantityInput = completeJobModal.querySelector('#completed_quantity');
        var quantityToProduceHidden = completeJobModal.querySelector('#modal_quantity_to_produce_hidden');
        var currentCompletedHidden = completeJobModal.querySelector('#modal_current_completed_hidden');
        var jobStatusHidden = completeJobModal.querySelector('#modal_job_status_hidden');
        var outputPartIdHidden = completeJobModal.querySelector('#modal_output_part_id_hidden'); // [เพิ่ม] Hidden input
        var partCodeHidden = completeJobModal.querySelector('#modal_part_code_hidden'); // [เพิ่ม] Hidden input
        var forceCompleteCheckbox = completeJobModal.querySelector('#force_complete');
        var assemblyPointSelect = completeJobModal.querySelector('#assembly_point');

        modalCuttingJobId.value = cuttingJobId;
        modalJobCode.value = jobCode;
        modalPartCode.value = partCode;
        modalQuantityToProduce.value = quantityToProduce;
        modalCurrentCompleted.value = currentCompleted;
        jobStatusHidden.value = jobStatus;
        outputPartIdHidden.value = outputPartId; // [เพิ่ม] กำหนดค่า
        partCodeHidden.value = partCode; // [เพิ่ม] กำหนดค่า

        // Calculate remaining quantity and set default for input
        var remainingQty = quantityToProduce - currentCompleted;
        if (remainingQty > 0) {
            completedQuantityInput.value = remainingQty;
        } else {
            completedQuantityInput.value = 0; 
        }
        
        quantityToProduceHidden.value = quantityToProduce;
        currentCompletedHidden.value = currentCompleted;

        // Reset checkbox state when modal opens
        forceCompleteCheckbox.checked = false;

        // Disable inputs/checkbox if job is already completed or cancelled
        if (jobStatus === 'เสร็จสิ้น' || jobStatus === 'ยกเลิก') {
            completedQuantityInput.disabled = true;
            forceCompleteCheckbox.disabled = true;
            assemblyPointSelect.disabled = true;
        } else {
            completedQuantityInput.disabled = false;
            forceCompleteCheckbox.disabled = false;
            assemblyPointSelect.disabled = false;
        }
    });
</script>
</body>
</html>
