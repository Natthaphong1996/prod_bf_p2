<?php
// get_nail_history.php
// ทำหน้าที่ดึงข้อมูลสรุปและประวัติการเบิกเพื่อแสดงใน Modal

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

// --- 1. คำนวณจำนวนที่ควรใช้จาก BOM (Logic เดิมจาก get_nail_issue_details.php) ---
$stmt_wood_issue = $conn->prepare("SELECT quantity FROM wood_issue WHERE issue_id = ?");
$stmt_wood_issue->bind_param("i", $issue_id);
$stmt_wood_issue->execute();
$job_quantity_result = $stmt_wood_issue->get_result();
if ($job_quantity_result->num_rows === 0) die('<div class="alert alert-danger">ไม่พบข้อมูลใบเบิกงาน</div>');
$job_quantity = (int)$job_quantity_result->fetch_assoc()['quantity'];
$stmt_wood_issue->close();

$stmt_bom = $conn->prepare("SELECT nails FROM bom WHERE prod_id = ?");
$stmt_bom->bind_param("i", $prod_id);
$stmt_bom->execute();
$bom_result = $stmt_bom->get_result();
if ($bom_result->num_rows === 0) die('<div class="alert alert-warning">ไม่พบข้อมูล BOM</div>');
$bom_nails_json = $bom_result->fetch_assoc()['nails'];
$stmt_bom->close();

$bom_nails = json_decode($bom_nails_json, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($bom_nails) || empty($bom_nails)) {
    die('<div class="alert alert-warning">ข้อมูลตะปูใน BOM ไม่ถูกต้อง</div>');
}

$nail_ids_from_bom = array_column($bom_nails, 'nail_id');
$placeholders = implode(',', array_fill(0, count($nail_ids_from_bom), '?'));
$types = str_repeat('i', count($nail_ids_from_bom));
$stmt_nails = $conn->prepare("SELECT nail_id, nail_code, nail_pcsperroll FROM nail WHERE nail_id IN ($placeholders)");
$stmt_nails->bind_param($types, ...$nail_ids_from_bom);
$stmt_nails->execute();
$nails_details_result = $stmt_nails->get_result();
$nail_details = [];
while ($row = $nails_details_result->fetch_assoc()) {
    $nail_details[$row['nail_id']] = $row;
}
$stmt_nails->close();

// สร้าง Array สำหรับเก็บข้อมูลสรุปของแต่ละตะปู
$summary = [];
foreach ($bom_nails as $bom_nail) {
    $nail_id = $bom_nail['nail_id'];
    if (!isset($nail_details[$nail_id])) continue;

    $pcs_per_roll = (int)$nail_details[$nail_id]['nail_pcsperroll'];
    $total_pcs_needed = (int)$bom_nail['quantity'] * $job_quantity;
    $required_rolls = ($pcs_per_roll > 0) ? ceil($total_pcs_needed / $pcs_per_roll) : 0;

    $summary[$nail_id] = [
        'code' => $nail_details[$nail_id]['nail_code'],
        'required' => $required_rolls,
        'issued' => 0, // ค่าเริ่มต้น
        'repaired' => 0 // ค่าเริ่มต้น
    ];
}

// --- 2. ดึงข้อมูลการเบิกครั้งแรก ---
$stmt_usage = $conn->prepare("SELECT nail_id, quantity_issued FROM nail_usage_log WHERE issue_id = ?");
$stmt_usage->bind_param("i", $issue_id);
$stmt_usage->execute();
$usage_result = $stmt_usage->get_result();
$initial_issue_details = [];
while ($row = $usage_result->fetch_assoc()) {
    if (isset($summary[$row['nail_id']])) {
        $summary[$row['nail_id']]['issued'] += $row['quantity_issued'];
    }
    $initial_issue_details[$row['nail_id']] = $row['quantity_issued'];
}
$stmt_usage->close();
$total_initial_issues = count($initial_issue_details) > 0 ? 1 : 0;

// --- 3. ดึงข้อมูลการเบิกซ่อม ---
$stmt_repair = $conn->prepare("SELECT nail_id, quantity_issued, repair_timestamp FROM nail_repair_log WHERE issue_id = ? ORDER BY repair_timestamp ASC");
$stmt_repair->bind_param("i", $issue_id);
$stmt_repair->execute();
$repair_result = $stmt_repair->get_result();
$repair_history = [];
while ($row = $repair_result->fetch_assoc()) {
    if (isset($summary[$row['nail_id']])) {
        $summary[$row['nail_id']]['repaired'] += $row['quantity_issued'];
    }
    // จัดกลุ่มตาม Timestamp เพื่อแสดงประวัติ
    $repair_history[$row['repair_timestamp']][] = [
        'nail_id' => $row['nail_id'],
        'quantity' => $row['quantity_issued']
    ];
}
$stmt_repair->close();
$total_repair_issues = count($repair_history);
$total_issues = $total_initial_issues + $total_repair_issues;

?>

<!-- สร้างส่วนของ HTML ที่จะแสดงผล -->
<h5 class="mb-3">สรุปภาพรวม</h5>
<p>มีการเบิกทั้งหมด: <strong><?= $total_issues ?> ครั้ง</strong> (เบิกครั้งแรก <?= $total_initial_issues ?> ครั้ง, เบิกซ่อม <?= $total_repair_issues ?> ครั้ง)</p>
<div class="table-responsive mb-4">
    <table class="table table-bordered table-sm">
        <thead class="table-secondary">
            <tr class="text-center">
                <th>รหัสตะปู</th>
                <th>จำนวนที่ควรใช้ (ม้วน)</th>
                <th>เบิกครั้งแรก (ม้วน)</th>
                <th>เบิกซ่อม (ม้วน)</th>
                <th>รวมเบิกทั้งหมด (ม้วน)</th>
                <th class="bg-light">ขาด/เกิน (ม้วน)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($summary as $nail_id => $data): 
                $total_issued = $data['issued'] + $data['repaired'];
                $balance = $total_issued - $data['required'];
                $balance_class = $balance < 0 ? 'text-success' : 'text-danger';
            ?>
            <tr class="text-center">
                <td><?= htmlspecialchars($data['code']) ?></td>
                <td><?= number_format($data['required']) ?></td>
                <td><?= number_format($data['issued']) ?></td>
                <td><?= number_format($data['repaired']) ?></td>
                <td class="fw-bold"><?= number_format($total_issued) ?></td>
                <td class="fw-bold <?= $balance_class ?>"><?= number_format($balance) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<hr>
<h5 class="mb-3">ประวัติการเบิก</h5>

<?php if ($total_initial_issues > 0): ?>
    <h6><i class="bi bi-box-arrow-up text-warning"></i> การเบิกครั้งแรก</h6>
    <ul>
        <?php foreach($initial_issue_details as $nail_id => $qty): ?>
            <li><?= htmlspecialchars($summary[$nail_id]['code']) ?>: <?= number_format($qty) ?> ม้วน</li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if ($total_repair_issues > 0): 
    $repair_count = 1;
    foreach($repair_history as $timestamp => $details):
?>
    <h6 class="mt-3"><i class="bi bi-tools text-info"></i> การเบิกซ่อม ครั้งที่ <?= $repair_count++ ?> <small class="text-muted">(<?= $timestamp ?>)</small></h6>
    <ul>
        <?php foreach($details as $detail): ?>
            <li><?= htmlspecialchars($summary[$detail['nail_id']]['code']) ?>: <?= number_format($detail['quantity']) ?> ม้วน</li>
        <?php endforeach; ?>
    </ul>
<?php 
    endforeach;
endif; ?>

<?php if ($total_issues === 0): ?>
    <p class="text-center text-muted">ยังไม่มีประวัติการเบิกสำหรับ Job นี้</p>
<?php endif; ?>

<?php $conn->close(); ?>
