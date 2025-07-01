<?php
include 'config_db.php';

$prod_id = $_POST['prod_id'] ?? null;
$job_id = $_POST['job_id'] ?? null;

if (!$prod_id || !$job_id) {
    echo '<p class="text-danger">ไม่พบข้อมูลที่จำเป็น</p>';
    exit;
}

// 1. ดึงรายการเบิกย้อนหลังจาก nail_usage_log
echo '<h6 class="mb-2"><i class="bi bi-clock-history"></i> รายการเบิกตะปูย้อนหลัง</h6>';

$stmt_log = $conn->prepare("SELECT nails, used_at FROM nail_usage_log WHERE job_id = ? ORDER BY used_at DESC LIMIT 3");
$stmt_log->bind_param("s", $job_id);
$stmt_log->execute();
$result_log = $stmt_log->get_result();

if ($result_log->num_rows > 0) {
    echo '<table class="table table-sm table-bordered">';
    echo '<thead><tr><th>รหัสตะปู</th><th>จำนวนที่เบิก</th><th>วันที่</th></tr></thead><tbody>';

    while ($log = $result_log->fetch_assoc()) {
        $used_at = date('Y-m-d H:i', strtotime($log['used_at']));
        $nails = json_decode($log['nails'], true);

        foreach ($nails as $nail) {
            $nail_id = $nail['nail_id'];
            $qty = $nail['qty'];

            // ดึงชื่อรหัสตะปู
            $stmt_nail = $conn->prepare("SELECT nail_code FROM nail WHERE nail_id = ?");
            $stmt_nail->bind_param("i", $nail_id);
            $stmt_nail->execute();
            $res_nail = $stmt_nail->get_result();
            $nail_code = $res_nail->fetch_assoc()['nail_code'] ?? 'N/A';

            echo "<tr>
                    <td>$nail_code</td>
                    <td>$qty</td>
                    <td>$used_at</td>
                  </tr>";
        }
    }

    echo '</tbody></table><hr>';
} else {
    echo '<p class="text-muted">ยังไม่มีการเบิกตะปูสำหรับ JOB นี้</p><hr>';
}

// 2. ดึงรายการตะปูจาก BOM
$sql_bom = "SELECT nails FROM bom WHERE prod_id = ?";
$stmt_bom = $conn->prepare($sql_bom);
$stmt_bom->bind_param("s", $prod_id);
$stmt_bom->execute();
$res_bom = $stmt_bom->get_result();

if ($row_bom = $res_bom->fetch_assoc()) {
    $nails = json_decode($row_bom['nails'], true);
    echo '<h6>รายการตะปูจาก BOM</h6>';

    if (empty($nails)) {
        echo '<p class="text-warning">ไม่มีรายการตะปูใน BOM</p>';
    } else {
        foreach ($nails as $index => $nail) {
            $nail_id = $nail['nail_id'];

            // ดึงชื่อรหัสตะปู
            $stmt2 = $conn->prepare("SELECT nail_code FROM nail WHERE nail_id = ?");
            $stmt2->bind_param("i", $nail_id);
            $stmt2->execute();
            $res_nail = $stmt2->get_result();
            $nail_row = $res_nail->fetch_assoc();
            $nail_code = $nail_row['nail_code'] ?? 'N/A';

            echo '<div class="row mb-2 nail-row">';
            echo '<div class="col-md-6">';
            echo "<label>รหัสตะปู: <strong>$nail_code</strong></label>";
            echo "<input type='hidden' name='nail_id[]' value='$nail_id'>";
            echo '</div>';
            echo '<div class="col-md-6">';
            echo "<input type='number' name='quantity[]' class='form-control' placeholder='จำนวนที่ต้องการเบิก' min='1' required>";
            echo '</div>';
            echo '</div>';
        }
    }
} else {
    echo '<p class="text-danger">ไม่พบ BOM สำหรับ Product นี้</p>';
}
?>
