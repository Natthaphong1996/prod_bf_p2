<?php
// ภาษา: PHP
// ชื่อไฟล์: issue_wood_log.php
// หน้าที่: สร้างใบเบิก และอัปเดตสถานะของ cutting_job และ cutting_batch ที่เกี่ยวข้อง

session_start();
require_once __DIR__ . '/config_db.php';

// --- ส่วนที่ 1: การประมวลผลฟอร์ม ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ตรวจสอบว่ามีการเลือกรายการมาอย่างน้อย 1 รายการหรือไม่
    if (isset($_POST['selected_rm']) && is_array($_POST['selected_rm']) && !empty($_POST['selected_rm'])) {
        
        $selected_rm_ids = $_POST['selected_rm'];
        $issued_by_user_id = $_SESSION['user_id'] ?? 0;
        
        $details_for_json = [];
        $all_needed_dates = [];

        foreach ($selected_rm_ids as $rm_id) {
            $issued_qty = (int)($_POST['qty'][$rm_id] ?? 0);
            $needed_date = $_POST['date'][$rm_id] ?? null;

            if ($issued_qty > 0) {
                $details_for_json[] = ['rm_id' => (int)$rm_id, 'issued_qty' => $issued_qty];
            }
            if ($needed_date) {
                $all_needed_dates[] = $needed_date;
            }
        }

        if (!empty($details_for_json)) {
            // --- เริ่มต้น Transaction ---
            $conn->begin_transaction();
            try {
                // 1. บันทึกใบเบิก (log_issue_history)
                $log_details_json = json_encode($details_for_json);
                $earliest_needed_date = !empty($all_needed_dates) ? min($all_needed_dates) : date('Y-m-d');
                $sql_insert_log = "INSERT INTO log_issue_history (log_details, earliest_needed_date, issued_by_user_id, issued_at) VALUES (?, ?, ?, NOW())";
                $stmt_log = $conn->prepare($sql_insert_log);
                $stmt_log->bind_param("ssi", $log_details_json, $earliest_needed_date, $issued_by_user_id);
                $stmt_log->execute();
                $new_log_id = $conn->insert_id;
                $stmt_log->close();

                // --- [เพิ่มใหม่] ส่วนอัปเดตสถานะ ---
                // เตรียม placeholder สำหรับ IN clause เพื่อความปลอดภัย
                $placeholders = implode(',', array_fill(0, count($selected_rm_ids), '?'));
                $types = str_repeat('i', count($selected_rm_ids));

                // 2. อัปเดต cutting_job ที่เกี่ยวข้อง
                // [แก้ไข] ปรับเงื่อนไขการ JOIN ให้ถูกต้องตามที่ผู้ใช้ระบุ
                $sql_update_jobs = "
                    UPDATE cutting_job cj
                    JOIN cutting_batch cb ON cj.batch_id = cb.batch_id
                    JOIN recipe_list rl ON cb.recipe_id = rl.recipe_id
                    SET cj.job_status = 'กำลังดำเนินการ'
                    WHERE rl.main_input_rm_id IN ($placeholders)
                    AND cb.batch_status = 'รอดำเนินการ'
                ";
                $stmt_jobs = $conn->prepare($sql_update_jobs);
                $stmt_jobs->bind_param($types, ...$selected_rm_ids);
                $stmt_jobs->execute();
                $stmt_jobs->close();

                // 3. อัปเดต cutting_batch ที่เกี่ยวข้อง
                $sql_update_batches = "
                    UPDATE cutting_batch cb
                    JOIN recipe_list rl ON cb.recipe_id = rl.recipe_id
                    SET cb.batch_status = 'กำลังดำเนินการ'
                    WHERE rl.main_input_rm_id IN ($placeholders)
                    AND cb.batch_status = 'รอดำเนินการ'
                ";
                $stmt_batches = $conn->prepare($sql_update_batches);
                $stmt_batches->bind_param($types, ...$selected_rm_ids);
                $stmt_batches->execute();
                $stmt_batches->close();

                // --- ยืนยัน Transaction ---
                $conn->commit();
                $_SESSION['success_message'] = "สร้างใบเบิก เลขที่ #{$new_log_id} และอัปเดตสถานะใบงานเรียบร้อยแล้ว!";

            } catch (Exception $e) {
                // --- หากเกิดข้อผิดพลาด ให้ยกเลิกทั้งหมด ---
                $conn->rollback();
                $_SESSION['error_message'] = "เกิดข้อผิดพลาดร้ายแรง: " . $e->getMessage();
            }
        } else {
            $_SESSION['error_message'] = "ไม่มีรายการที่ถูกต้องสำหรับสร้างใบเบิก (จำนวนต้องมากกว่า 0)";
        }

    } else {
        $_SESSION['error_message'] = "กรุณาเลือกรายการที่ต้องการเบิกอย่างน้อย 1 รายการ";
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}


// --- ส่วนที่ 2: ดึงข้อมูลสรุปยอดไม้ที่ต้องใช้ (ใช้โค้ดเดิม) ---
$sql_summary = "
    SELECT
        rm.rm_id, rm.rm_code, rm.rm_thickness, rm.rm_width, rm.rm_length, rm.rm_type,
        SUM(cb.total_wood_logs) AS total_required_qty,
        MIN(cb.rm_needed_date) AS earliest_needed_date
    FROM cutting_batch cb
    JOIN recipe_list rl ON cb.recipe_id = rl.recipe_id
    JOIN rm_wood_list rm ON rl.main_input_rm_id = rm.rm_id
    WHERE cb.batch_status = 'รอดำเนินการ'
    GROUP BY rm.rm_id, rm.rm_code, rm.rm_thickness, rm.rm_width, rm.rm_length, rm.rm_type
    ORDER BY earliest_needed_date ASC, rm.rm_code ASC;
";
$result = $conn->query($sql_summary);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้างใบเบิกไม้ท่อน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<main class="container">
    <div class="d-flex justify-content-between align-items-center mb-4 mt-4">
        <h1><i class="fas fa-file-invoice"></i> สร้างใบเบิกไม้ท่อน</h1>
    </div>

    <!-- แสดงข้อความแจ้งเตือนจาก Session -->
    <?php
    if (isset($_SESSION['success_message'])) {
        echo "<div class='alert alert-success'>" . htmlspecialchars($_SESSION['success_message']) . "</div>";
        unset($_SESSION['success_message']);
    }
    if (isset($_SESSION['error_message'])) {
        echo "<div class='alert alert-danger'>" . htmlspecialchars($_SESSION['error_message']) . "</div>";
        unset($_SESSION['error_message']);
    }
    ?>
    
    <!-- ฟอร์มครอบตารางทั้งหมด -->
    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" id="issue-form">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>เลือกรายการไม้ที่ต้องการเบิก (สำหรับใบงานที่ "รอดำเนินการ")</span>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> สร้างใบเบิกสำหรับรายการที่เลือก</button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 5%;"><input class="form-check-input" type="checkbox" id="select-all"></th>
                                <th>รหัส/ประเภท</th>
                                <th>ขนาด (หนาxกว้างxยาว)</th>
                                <th style="width: 20%;" class="text-end">จำนวนที่เบิก</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="form-check-input chk-item" name="selected_rm[]" value="<?php echo $row['rm_id']; ?>">
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['rm_code']); ?></strong><br>
                                            <small class="text-muted"><?php echo ($row['rm_type'] == 'K') ? 'อบ' : 'ไม่อบ'; ?></small>
                                        </td>
                                        <td><?php echo "{$row['rm_thickness']}x{$row['rm_width']}x{$row['rm_length']}"; ?></td>
                                        <td class="text-end">
                                            <strong class="fs-5 text-primary"><?php echo number_format($row['total_required_qty']); ?></strong>
                                            <input type="hidden" name="qty[<?php echo $row['rm_id']; ?>]" value="<?php echo $row['total_required_qty']; ?>">
                                            <input type="hidden" name="date[<?php echo $row['rm_id']; ?>]" value="<?php echo $row['earliest_needed_date']; ?>">
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted p-4">
                                        <i class="fas fa-check-circle fa-2x text-success"></i>
                                        <p class="mt-2 mb-0">ไม่มีรายการไม้ท่อนที่ต้องเบิกในขณะนี้</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </form>
</main>

<?php 
$conn->close();
include __DIR__ . '/footer.php';
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.getElementById('select-all');
    const checkboxes = document.querySelectorAll('.chk-item');

    selectAll.addEventListener('change', function() {
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });

    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (!this.checked) {
                selectAll.checked = false;
            }
        });
    });
});
</script>

</body>
</html>
