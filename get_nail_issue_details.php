<?php
// get_nail_issue_details.php

session_start();
require_once 'config_db.php';

// --- Security & Validation ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SERVER["REQUEST_METHOD"] !== "POST") {
    die('<div class="alert alert-danger">การเข้าถึงไม่ถูกต้อง</div>');
}

$prod_id = filter_input(INPUT_POST, 'prod_id', FILTER_VALIDATE_INT);
$issue_id = filter_input(INPUT_POST, 'issue_id', FILTER_VALIDATE_INT);

if (!$prod_id || !$issue_id) {
    die('<div class="alert alert-danger">ข้อมูลที่ส่งมาไม่สมบูรณ์</div>');
}

// --- ตรวจสอบว่าเป็นการเบิกครั้งแรก หรือเบิกซ่อม ---
$stmt_check = $conn->prepare("SELECT COUNT(*) as count FROM nail_usage_log WHERE issue_id = ?");
$stmt_check->bind_param("i", $issue_id);
$stmt_check->execute();
$is_repair_issue = ($stmt_check->get_result()->fetch_assoc()['count'] > 0);
$stmt_check->close();

// --- ดึงข้อมูลพื้นฐานที่จำเป็น (BOM และ Nail details) ---
$stmt_bom = $conn->prepare("SELECT nails FROM bom WHERE prod_id = ?");
$stmt_bom->bind_param("i", $prod_id);
$stmt_bom->execute();
$result_bom = $stmt_bom->get_result();
if ($result_bom->num_rows === 0) {
    die('<div class="alert alert-warning">ไม่พบข้อมูล BOM สำหรับสินค้านี้</div>');
}
$bom_nails_json = $result_bom->fetch_assoc()['nails'];
$stmt_bom->close();

$bom_nails = json_decode($bom_nails_json, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($bom_nails) || empty($bom_nails)) {
    die('<div class="alert alert-warning">ข้อมูลตะปูใน BOM ไม่ถูกต้องหรือไม่มีรายการ</div>');
}

$nail_ids_from_bom = array_column($bom_nails, 'nail_id');
if (empty($nail_ids_from_bom)) {
    die('<p class="text-warning">ไม่พบ nail_id ในข้อมูล BOM</p>');
}

$placeholders = implode(',', array_fill(0, count($nail_ids_from_bom), '?'));
$types = str_repeat('i', count($nail_ids_from_bom));
$stmt_nails = $conn->prepare("SELECT nail_id, nail_code, nail_pcsperroll FROM nail WHERE nail_id IN ($placeholders)");
$stmt_nails->bind_param($types, ...$nail_ids_from_bom);
$stmt_nails->execute();
$result_nails = $stmt_nails->get_result();
$nail_details = [];
while ($row = $result_nails->fetch_assoc()) {
    $nail_details[$row['nail_id']] = $row;
}
$stmt_nails->close();

// --- สร้าง HTML ตามประเภทการเบิก ---

if ($is_repair_issue) {
    // --- รูปแบบสำหรับ "เบิกซ่อม" (ให้ User กรอกจำนวนเอง) ---
?>
    <div class="alert alert-info" role="alert">
        <i class="bi bi-tools"></i> <strong>เบิกซ่อม:</strong> กรุณาระบุจำนวนม้วนของตะปูที่ต้องการเบิกเพิ่มเติม
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered">
            <thead class="table-light">
                <tr class="text-center">
                    <th>รหัสตะปู</th>
                    <th style="width: 40%;">จำนวนที่ต้องการเบิก (ม้วน)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bom_nails as $bom_nail): 
                    $nail_id = $bom_nail['nail_id'];
                    if (!isset($nail_details[$nail_id])) continue;
                    $nail_code = $nail_details[$nail_id]['nail_code'];
                ?>
                <tr class="align-middle">
                    <td>
                        <input type="hidden" name="nails[]" value="<?= htmlspecialchars($nail_id) ?>">
                        <?= htmlspecialchars($nail_code) ?>
                    </td>
                    <td>
                        <input type="number" class="form-control text-center" name="quantity_issued[]" value="0" min="0" required>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php
} else {
    // --- รูปแบบสำหรับ "เบิกครั้งแรก" (คำนวณอัตโนมัติ) ---
    $stmt_wood_issue = $conn->prepare("SELECT quantity FROM wood_issue WHERE issue_id = ?");
    $stmt_wood_issue->bind_param("i", $issue_id);
    $stmt_wood_issue->execute();
    $result_wood_issue = $stmt_wood_issue->get_result();
    if ($result_wood_issue->num_rows === 0) {
        die('<div class="alert alert-danger">ไม่พบข้อมูลใบเบิกงาน (Issue ID: ' . $issue_id . ')</div>');
    }
    $job_quantity = (int)$result_wood_issue->fetch_assoc()['quantity'];
    $stmt_wood_issue->close();
?>
    <div class="alert alert-warning" role="alert">
        <i class="bi bi-box-arrow-up"></i> <strong>เบิกครั้งแรก:</strong> ระบบจะคำนวณจำนวนที่ต้องใช้โดยอัตโนมัติ
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered">
            <thead class="table-light">
                <tr class="text-center">
                    <th>รหัสตะปู</th>
                    <th>ใช้ (ตัว/สินค้า)</th>
                    <th>ผลิต (ชิ้น)</th>
                    <th>รวม (ตัว)</th>
                    <th>(ตัว/ม้วน)</th>
                    <th class="bg-warning">ต้องเบิก (ม้วน)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bom_nails as $bom_nail): 
                    $nail_id = $bom_nail['nail_id'];
                    $bom_quantity_per_product = (int)$bom_nail['quantity'];
                    if (!isset($nail_details[$nail_id])) continue;
                    $nail_code = $nail_details[$nail_id]['nail_code'];
                    $pcs_per_roll = (int)$nail_details[$nail_id]['nail_pcsperroll'];
                    $total_pcs_needed = $bom_quantity_per_product * $job_quantity;
                    $rolls_to_issue = ($pcs_per_roll > 0) ? ceil($total_pcs_needed / $pcs_per_roll) : 0;
                ?>
                <tr class="text-center align-middle">
                    <td>
                        <input type="hidden" name="nails[]" value="<?= htmlspecialchars($nail_id) ?>">
                        <?= htmlspecialchars($nail_code) ?>
                    </td>
                    <td><?= number_format($bom_quantity_per_product) ?></td>
                    <td><?= number_format($job_quantity) ?></td>
                    <td><?= number_format($total_pcs_needed) ?></td>
                    <td><?= number_format($pcs_per_roll) ?></td>
                    <td class="fw-bold fs-5">
                        <input type="hidden" name="quantity_issued[]" value="<?= htmlspecialchars($rolls_to_issue) ?>">
                        <?= number_format($rolls_to_issue) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="alert alert-info mt-3" role="alert">
        <i class="bi bi-info-circle-fill"></i>
        <strong>หมายเหตุ:</strong> ระบบได้คำนวณจำนวนม้วนที่ต้องเบิกโดยอัตโนมัติ (ปัดเศษขึ้น) กรุณาตรวจสอบความถูกต้องก่อนกดยืนยัน
    </div>
<?php
}
$conn->close();
?>
