<?php
/**
 * ดึงรายการ Production Wages พร้อมจุดประกอบงาน (assembly points)
 *
 * @param mysqli $conn             Connection object
 * @param int    $current_page     เลขหน้าปัจจุบัน
 * @param int    $items_per_page   จำนวนรายการต่อหน้า
 * @param string $search_value     คำค้นหา (production_wage_id หรือ job_id)
 * @param string $date_from        วันที่เริ่มต้น ในรูปแบบ 'YYYY-MM-DD HH:MM:SS'
 * @param string $date_to          วันที่สิ้นสุด   ในรูปแบบ 'YYYY-MM-DD HH:MM:SS'
 * @return array                   คืนค่าเป็น array ของ associative arrays
 */
function getProductionWagesList(
    $conn,
    $current_page = 1,
    $items_per_page = 60,
    $search_value = '',
    $date_from = '',
    $date_to = ''
) {
    $offset = ($current_page - 1) * $items_per_page;

    // เงื่อนไขค้นหา
    $search_condition = '';
    if ($search_value !== '') {
        $s = mysqli_real_escape_string($conn, $search_value);
        $search_condition = " AND (
            pw.production_wage_id LIKE '%{$s}%'
            OR pw.job_id             LIKE '%{$s}%'
        )";
    }

    // เงื่อนไข date range
    $date_condition = '';
    if ($date_from !== '' && $date_to !== '') {
        $df = mysqli_real_escape_string($conn, $date_from);
        $dt = mysqli_real_escape_string($conn, $date_to);
        $date_condition = " AND pw.date_create BETWEEN '{$df}' AND '{$dt}'";
    }

    // Query หลัก: ดึง total_wage และ assembly_point จาก production_wages
    $sql = "
        SELECT
            pw.production_wage_id,
            pw.job_id,
            pw.total_wage,
            pw.assembly_point,
            pw.date_create,
            pw.status
        FROM production_wages pw
        WHERE 1=1
          {$search_condition}
          {$date_condition}
        ORDER BY pw.id DESC
        LIMIT {$items_per_page} OFFSET {$offset}
    ";

    return mysqli_query($conn, $sql);
}



function getProductionWagesCount($conn, $search_value = '')
{
    $search_condition = '';

    if ($search_value !== '') {
        $s = mysqli_real_escape_string($conn, $search_value);
        // นับทั้ง production_wage_id และ job_id string
        $search_condition = " AND (
            production_wage_id LIKE '%{$s}%'
            OR job_id             LIKE '%{$s}%'
        )";
    }

    $query = "
        SELECT COUNT(*) AS total
        FROM production_wages
        WHERE 1=1
          {$search_condition}
    ";

    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        return (int) mysqli_fetch_assoc($result)['total'];
    }
    return 0;
}

// production_wages_function.php

/**
 * สร้าง Invoice พร้อมบันทึก total_wage (price_value * prod_complete_qty) และจุดประกอบงาน
 *
 * @param mysqli $conn               Connection object
 * @param array  $job_ids_array      รายการ job_id ที่จะออกใบเบิก
 * @return string|false              รหัส production_wage_id หรือ false เมื่อไม่สำเร็จ
 */
function createInvoice($conn, array $job_ids_array)
{
    // ตรวจสอบ input
    if (empty($job_ids_array)) return false;

    // Sanitize job_ids
    $escaped_ids = array_map(fn($jid) => mysqli_real_escape_string($conn, $jid), $job_ids_array);

    // กรองเฉพาะ job_ids ที่ยังไม่ได้สั่งจ่าย
    $to_invoice = [];
    foreach ($escaped_ids as $jid) {
        $res = mysqli_query($conn, "SELECT issue_status FROM wood_issue WHERE job_id='{$jid}' LIMIT 1");
        if ($res && mysqli_num_rows($res) > 0) {
            $status = mysqli_fetch_assoc($res)['issue_status'];
            if ($status !== 'สั่งจ่ายแล้ว') {
                $to_invoice[] = $jid;
            }
        }
    }
    if (empty($to_invoice)) return false;

    // สร้าง tuple list และคำนวณ total_wage จาก price_value * prod_complete_qty
    $tuple_list = [];
    $total_wage = 0.0;
    foreach ($to_invoice as $jid) {
        // ดึง price_value
        $sqlPrice = "SELECT pp.price_value
                     FROM wood_issue wi
                     JOIN product_price pp ON pp.prod_id = wi.prod_id
                     WHERE wi.job_id='{$jid}' LIMIT 1";
        $rP = mysqli_query($conn, $sqlPrice);
        $price_value = ($rP && mysqli_num_rows($rP) > 0)
                     ? (float) mysqli_fetch_assoc($rP)['price_value']
                     : 0;

        // ดึง prod_complete_qty
        $sqlQty = "SELECT prod_complete_qty FROM jobs_complete WHERE job_id='{$jid}' LIMIT 1";
        $rQ = mysqli_query($conn, $sqlQty);
        $qty = ($rQ && mysqli_num_rows($rQ) > 0)
             ? (int) mysqli_fetch_assoc($rQ)['prod_complete_qty']
             : 0;

        $line_total = $price_value * $qty;
        $total_wage += $line_total;
        $tuple_list[] = "{" . $jid . "," . $price_value . "}";
    }
    $job_ids_string = implode(',', $tuple_list);

    // ดึง assembly_point แบบไม่ซ้ำ
    $in_list = implode(',', array_map(fn($j) => "'" . mysqli_real_escape_string($conn, $j) . "'", $to_invoice));
    $apRes = mysqli_query($conn,
        "SELECT DISTINCT assembly_point FROM jobs_complete WHERE job_id IN ({$in_list})"
    );
    $ap_list = [];
    while ($apRow = mysqli_fetch_assoc($apRes)) {
        $ap_list[] = $apRow['assembly_point'];
    }
    $assembly_point = implode(', ', array_unique($ap_list));

    // สร้าง production_wage_id
    date_default_timezone_set('Asia/Bangkok');
    $production_wage_id = 'PW-' . date('dmy-His');

    // Insert ลง production_wages
    $asm_safe = mysqli_real_escape_string($conn, $assembly_point);
    $tw_safe  = mysqli_real_escape_string($conn, (string)$total_wage);
    $insertSQL = "INSERT INTO production_wages
        (production_wage_id, job_id, total_wage, assembly_point)
     VALUES
        ('{$production_wage_id}', '{$job_ids_string}', '{$tw_safe}', '{$asm_safe}')";
    if (!mysqli_query($conn, $insertSQL)) return false;

    // อัปเดตสถานะ wood_issue
    $ids_for_update = implode("','", $to_invoice);
    mysqli_query($conn,
        "UPDATE wood_issue SET issue_status='รอยืนยันการสั่งจ่าย' WHERE job_id IN ('{$ids_for_update}')"
    );

    return $production_wage_id;
}

/**
 * ยืนยันการออก Invoice: 
 *  - เปลี่ยน issue_status ใน wood_issue เป็น 'สั่งจ่ายแล้ว'
 *  - เปลี่ยน status ใน production_wages เป็น 'อนุมัติแล้ว'
 *
 * @param mysqli $conn               MySQLi connection
 * @param string $production_wage_id รหัส production_wage ที่ต้องการอนุมัติ
 * @return bool                      true ถ้าอัปเดตสำเร็จ, false ถ้าเกิดข้อผิดพลาด
 */
function confirmInvoice($conn, $production_wage_id)
{
    // 1. ป้องกัน SQL Injection
    $id = mysqli_real_escape_string($conn, $production_wage_id);

    // 2. ดึงสตริง job_id จาก production_wages
    $sql = "SELECT job_id 
            FROM production_wages 
            WHERE production_wage_id = '$id' 
            LIMIT 1";
    $res = mysqli_query($conn, $sql);
    if (!$res || mysqli_num_rows($res) === 0) {
        return false;
    }
    $row = mysqli_fetch_assoc($res);
    $jobs_string = $row['job_id'];  // ตัวอย่าง: "{25-04/001,1500},{25-04/002,2000}"

    // 3. แยกเอา job_id ออกมา (ก่อน comma) ด้วย regex
    preg_match_all('/\{([^,}]+),/', $jobs_string, $matches);
    $job_ids = $matches[1];  // array ของ job_id เช่น ['25-04/001', '25-04/002', ...]

    if (empty($job_ids)) {
        return false;
    }

    // 4. ป้องกัน SQL Injection สำหรับแต่ละ job_id
    $job_ids_escaped = array_map(function($v) use ($conn) {
        return mysqli_real_escape_string($conn, $v);
    }, $job_ids);
    $in_clause = "'" . implode("','", $job_ids_escaped) . "'";

    // 5. เริ่ม transaction
    mysqli_begin_transaction($conn);

    // 6. อัปเดตสถานะใน wood_issue
    $q1 = "UPDATE wood_issue 
           SET issue_status = 'สั่งจ่ายแล้ว' 
           WHERE job_id IN ($in_clause)";
    $u1 = mysqli_query($conn, $q1);

    // 7. อัปเดตสถานะใน production_wages
    $q2 = "UPDATE production_wages 
           SET status = 'อนุมัติแล้ว' 
           WHERE production_wage_id = '$id'";
    $u2 = mysqli_query($conn, $q2);

    // 8. ตรวจสอบผลลัพธ์
    if ($u1 && $u2) {
        mysqli_commit($conn);
        return true;
    } else {
        mysqli_rollback($conn);
        return false;
    }
}

/**
 * ยกเลิก Invoice:
 *  - เปลี่ยน issue_status ใน wood_issue กลับเป็น 'ปิดสำเร็จ'
 *  - เปลี่ยน status ใน production_wages เป็น 'ยกเลิก'
 *
 * @param mysqli $conn               MySQLi connection
 * @param string $production_wage_id รหัส production_wage ที่ต้องการยกเลิก
 * @return bool                      true ถ้าอัปเดตสำเร็จ, false ถ้าเกิดข้อผิดพลาด
 */
function cancelInvoice($conn, $production_wage_id)
{
    // 1. ป้องกัน SQL Injection
    $pwId = mysqli_real_escape_string($conn, $production_wage_id);

    // 2. ดึงสตริง job_id จาก production_wages
    $sql = "SELECT job_id 
            FROM production_wages 
            WHERE production_wage_id = '$pwId'
            LIMIT 1";
    $res = mysqli_query($conn, $sql);
    if (!$res || mysqli_num_rows($res) === 0) {
        return false;
    }
    $row         = mysqli_fetch_assoc($res);
    $jobs_string = $row['job_id'];  // เช่น "{25-04/001,1500},{25-04/002,2000}"

    // 3. แยกเอา job_id ออกมา (ก่อน comma) ด้วย regex
    preg_match_all('/\{([^,}]+),/', $jobs_string, $matches);
    $job_ids = $matches[1];  // ['25-04/001', '25-04/002', ...]

    if (empty($job_ids)) {
        return false;
    }

    // 4. ป้องกัน SQL Injection สำหรับแต่ละ job_id
    $escaped = array_map(fn($v) => mysqli_real_escape_string($conn, $v), $job_ids);
    $inClause = "'" . implode("','", $escaped) . "'";

    // 5. เริ่ม transaction
    mysqli_begin_transaction($conn);

    // 6. อัปเดต issue_status ใน wood_issue กลับเป็น 'ปิดสำเร็จ'
    $q1 = "UPDATE wood_issue
           SET issue_status = 'ปิดสำเร็จ'
           WHERE job_id IN ($inClause)";
    $u1 = mysqli_query($conn, $q1);

    // 7. อัปเดต status ใน production_wages เป็น 'ยกเลิก'
    $q2 = "UPDATE production_wages
           SET status = 'ยกเลิก'
           WHERE production_wage_id = '$pwId'";
    $u2 = mysqli_query($conn, $q2);

    // 8. ตรวจสอบผลลัพธ์ แล้ว commit หรือ rollback
    if ($u1 && $u2) {
        mysqli_commit($conn);
        return true;
    } else {
        mysqli_rollback($conn);
        return false;
    }
}

// Modal สำหรับเพิ่มข้อมูลราคาสินค้า
function displayAddPriceModal($token)
{
    global $conn;
?>
    <div class="modal fade" id="addPriceModal" tabindex="-1" aria-labelledby="addPriceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="product_price.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addPriceModalLabel">เพิ่มราคาค่าจ้างผลิต-ใหม่</h5>
                        <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="addPrice">
                        <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($token); ?>">

                        <!-- Dropdown สำหรับเลือกผลิตภัณฑ์ (ค้นหาได้ด้วย Select2) -->
                        <div class="mb-3">
                            <label for="prod_id" class="form-label">เลือก PRODUCT</label>
                            <select class="form-control select2" id="prod_id" name="prod_id" required>
                                <option value="">-- Select Product --</option>
                                <?php
                                $sql = "SELECT prod_id, prod_code, prod_partno, code_cus_size 
                            FROM prod_list 
                            ORDER BY code_cus_size, prod_code, prod_partno";
                                $result = $conn->query($sql);
                                if ($result) {
                                    while ($row = $result->fetch_assoc()) {
                                        $value = $row['prod_id'];
                                        $display = $row['code_cus_size'] . " | " . $row['prod_code'] . " | " . $row['prod_partno'];
                                        echo "<option value=\"" . htmlspecialchars($value) . "\">" . htmlspecialchars($display) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Input สำหรับระบุราคา -->
                        <div class="mb-3">
                            <label for="price_value" class="form-label">ใส่ราคา</label>
                            <input type="number" step="0.01" class="form-control" id="price_value" name="price_value" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ย้อนกลับ</button>
                        <button type="submit" class="btn btn-primary">ยืนยันการเพิ่มราคา</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php
}

// Modal สำหรับแก้ไขข้อมูลราคาสินค้า
function displayEditPriceModal($token)
{
    global $conn;
?>
    <div class="modal fade" id="editPriceModal" tabindex="-1" aria-labelledby="editPriceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="product_price.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editPriceModalLabel">แก้ไขราคาค่าจ้างผลิต</h5>
                        <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="editPrice">
                        <input type="hidden" id="edit_price_id" name="price_id" value="">
                        <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($token); ?>">

                        <!-- Dropdown สำหรับเลือกผลิตภัณฑ์ (ค้นหาได้ด้วย Select2) -->
                        <div class="mb-3">
                            <label for="edit_prod_id" class="form-label">PRODUCT</label>
                            <select class="form-control select2" id="edit_prod_id" name="prod_id" disabled>
                                <option value="">-- Select Product --</option>
                                <?php
                                $sql = "SELECT prod_id, prod_code, prod_partno, code_cus_size 
                          FROM prod_list 
                          ORDER BY code_cus_size, prod_code, prod_partno";
                                $result = $conn->query($sql);
                                if ($result) {
                                    while ($row = $result->fetch_assoc()) {
                                        $value = $row['prod_id'];
                                        $display = $row['code_cus_size'] . " | " . $row['prod_code'] . " | " . $row['prod_partno'];
                                        echo "<option value=\"" . htmlspecialchars($value) . "\">" . htmlspecialchars($display) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Input สำหรับแก้ไขราคา -->
                        <div class="mb-3">
                            <label for="edit_price_value" class="form-label">PRICE VALUE</label>
                            <input type="number" step="0.01" class="form-control" id="edit_price_value" name="price_value" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ย้อนกลับ</button>
                        <button type="submit" class="btn btn-primary">ยืนยันการแก้ไข</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php
}

function displayHistoryModal($price_id)
{
    global $conn;
    // ดึงประวัติการเปลี่ยนแปลงราคา
    $history = getPriceHistory($conn, $price_id);
?>
    <div class="modal fade" id="historyModal<?php echo $price_id; ?>" tabindex="-1"
        aria-labelledby="historyModalLabel<?php echo $price_id; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="historyModalLabel<?php echo $price_id; ?>">
                        ประวัติการเปลี่ยนแปลงราคาค่าจ้างผลิต ID: <?php echo $price_id; ?>
                    </h5>
                    <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($history)): ?>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>แก้ไขจาก</th>
                                    <th>แก้ไขเป็น</th>
                                    <th>วันที่ทำการแก้ไข</th>
                                    <th>ผู้แก้ไขราคา</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $record): ?>
                                    <?php
                                    // กำหนดชื่อ user ถ้าไม่มีใน DB จะเป็น "Unknown user"
                                    $userName = $record['thainame'] ?? 'Unknown user';
                                    ?>
                                    <tr>
                                        <td><?php echo number_format($record['change_from'], 2); ?></td>
                                        <td><?php echo number_format($record['change_to'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($record['change_date']); ?></td>
                                        <td><?php echo htmlspecialchars($userName); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>ไม่พบข้อมูลการเปลี่ยนแปลงราคาค่าจ้างผลิต</p>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
<?php
}

/**
 * คำนวณยอดเงินรวมของ Invoice
 *
 * ดึงข้อมูล job_id (ในรูปแบบ "{job_id,price},…") จากตาราง production_wages
 * แล้วนำแต่ละ job_id ไปดึง prod_complete_qty จาก jobs_complete
 * คูณกับ price และรวมค่าเหล่านั้นคืนเป็นยอดรวม
 *
 * @param mysqli  $conn               MySQLi connection
 * @param string  $production_wage_id รหัส Invoice (production_wage_id)
 * @return float|false                ยอดรวม (float) หรือ false ถ้าไม่พบ Invoice
 */
function calculateInvoiceTotal($conn, $production_wage_id)
{
    // 1. ป้องกัน SQL Injection
    $pwId = mysqli_real_escape_string($conn, $production_wage_id);

    // 2. ดึงสตริง job_id จาก production_wages
    $sql  = "SELECT job_id
             FROM production_wages
             WHERE production_wage_id = '$pwId'
             LIMIT 1";
    $res  = mysqli_query($conn, $sql);
    if (!$res || mysqli_num_rows($res) === 0) {
        return false;
    }
    $row         = mysqli_fetch_assoc($res);
    $jobsString  = $row['job_id'];  // เช่น "{25-03/211,8},{25-03/212,7},…"

    // 3. แยก job_id และ price ออกมา
    preg_match_all('/\{([^,}]+),([^}]+)\}/', $jobsString, $matches);
    $jobIds = $matches[1] ?? [];
    $prices = $matches[2] ?? [];

    // 4. ถ้าไม่มีงานใด ๆ คืน 0
    if (empty($jobIds)) {
        return 0.0;
    }

    // 5. วนลูปคำนวณยอดรวม
    $total = 0.0;
    foreach ($jobIds as $i => $jobId) {
        $price = floatval($prices[$i]);

        // ดึง prod_complete_qty จาก jobs_complete
        $jid  = mysqli_real_escape_string($conn, $jobId);
        $sql2 = "SELECT prod_complete_qty
                 FROM jobs_complete
                 WHERE job_id = '$jid'
                 LIMIT 1";
        $res2 = mysqli_query($conn, $sql2);

        $qty = 0.0;
        if ($res2 && mysqli_num_rows($res2) > 0) {
            $r2  = mysqli_fetch_assoc($res2);
            $qty = floatval($r2['prod_complete_qty']);
        }

        // บวกยอด (qty * price)
        $total += $qty * $price;
    }

    return $total;
}



?>