<?php
// ไฟล์: m3_summary_report.php (เวอร์ชันปรับปรุง)
session_start();
include 'config_db.php';
include 'navbar.php';

// ตรวจสอบสิทธิ์การเข้าใช้งาน
if (!isset($_SESSION['username'])) {
    header('Location: index.php');
    exit();
}

// --- การตั้งค่าตัวแปรสำหรับ Pagination และ Filter ---
$limit = 25; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// จัดการตัวกรอง
$search_job_id = isset($_GET['search_job_id']) ? trim($_GET['search_job_id']) : '';
$default_start_date = date('Y-m-01');
$default_end_date = date('Y-m-t');
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : $default_start_date;
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : $default_end_date;

// --- 1. ดึงข้อมูลทั้งหมดที่ตรงตามเงื่อนไข (ไม่แบ่งหน้าใน SQL) ---
$params = [];
$types = '';
$main_query_sql = "
    SELECT
        jc.job_id, jc.date_receive, jc.prod_complete_qty,
        jc.send_by AS sender_name, jc.receive_by AS receiver_name,
        wi.prod_id, pl.prod_code, pl.prod_partno, wi.quantity AS main_job_quantity
    FROM jobs_complete AS jc
    LEFT JOIN wood_issue AS wi ON jc.job_id = wi.job_id AND wi.issue_type = 'ใบเบิกใช้'
    LEFT JOIN prod_list AS pl ON wi.prod_id = pl.prod_id
";

$where_clauses = [];
if (!empty($search_job_id)) {
    $where_clauses[] = "jc.job_id LIKE ?";
    $params[] = "%" . $search_job_id . "%";
    $types .= 's';
} else {
    $where_clauses[] = "DATE(jc.date_receive) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= 'ss';
}

if (!empty($where_clauses)) {
    $main_query_sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$main_query_sql .= " ORDER BY jc.date_receive DESC";

$stmt = $conn->prepare($main_query_sql);
if ($stmt) {
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $all_completed_jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    die("SQL Error: " . $conn->error);
}

// --- 2. จัดการ Pagination ด้วย PHP ---
$total_rows = count($all_completed_jobs);
$total_pages = ceil($total_rows / $limit);
$jobs_for_this_page = array_slice($all_completed_jobs, $offset, $limit);

// ประกาศตัวแปรสำหรับเก็บยอดรวม
$page_total_prod_complete_qty = 0;
$page_total_main_m3 = 0; // ยังคงคำนวณเพื่อรวมใน net_m3
$page_total_repair_m3 = 0; // ยังคงคำนวณเพื่อรวมใน net_m3
$page_total_returned_m3 = 0; // ยังคงคำนวณเพื่อรวมใน net_m3
$page_total_net_m3 = 0;

$grand_total_prod_complete_qty = 0;
$grand_total_main_m3 = 0; // ยังคงคำนวณเพื่อรวมใน net_m3
$grand_total_repair_m3 = 0; // ยังคงคำนวณเพื่อรวมใน net_m3
$grand_total_returned_m3 = 0; // ยังคงคำนวณเพื่อรวมใน net_m3
$grand_total_net_m3 = 0;

$results = [];

if (!empty($all_completed_jobs)) {
    // --- 3. ดึงข้อมูลประกอบทั้งหมดสำหรับทุก Job ที่พบ ---
    $all_job_ids = array_column($all_completed_jobs, 'job_id');
    $all_prod_ids = array_unique(array_filter(array_column($all_completed_jobs, 'prod_id')));
    
    // Helper function for bind_param references
    $ref_values = function($arr) {
        $refs = [];
        foreach($arr as $key => $value) $refs[$key] = &$arr[$key];
        return $refs;
    };

    // ดึงข้อมูลประกอบ (BOM, Repair, Return, Parts) ทีเดียว
    $boms_data = [];
    if (!empty($all_prod_ids)) {
        $prod_ids_placeholder = implode(',', array_fill(0, count($all_prod_ids), '?'));
        $prod_ids_types = str_repeat('s', count($all_prod_ids));
        $stmt = $conn->prepare("SELECT prod_id, parts FROM bom WHERE prod_id IN ($prod_ids_placeholder)");
        call_user_func_array([$stmt, 'bind_param'], $ref_values(array_merge([$prod_ids_types], $all_prod_ids)));
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $boms_data[$row['prod_id']] = $row;
        $stmt->close();
    }
    
    $job_ids_placeholder = implode(',', array_fill(0, count($all_job_ids), '?'));
    $job_ids_types = str_repeat('s', count($all_job_ids));

    $repair_jobs_data = [];
    $stmt = $conn->prepare("SELECT job_id, part_quantity_reason FROM repair_issue WHERE job_id IN ($job_ids_placeholder)");
    call_user_func_array([$stmt, 'bind_param'], $ref_values(array_merge([$job_ids_types], $all_job_ids)));
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $repair_jobs_data[$row['job_id']][] = $row;
    $stmt->close();

    $returned_wood_data = [];
    $stmt = $conn->prepare("SELECT job_id, SUM(return_total_m3) as total_returned FROM return_wood_wip WHERE job_id IN ($job_ids_placeholder) GROUP BY job_id");
    call_user_func_array([$stmt, 'bind_param'], $ref_values(array_merge([$job_ids_types], $all_job_ids)));
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $returned_wood_data[$row['job_id']] = $row['total_returned'];
    $stmt->close();

    $all_part_ids_for_lookup = [];
    foreach ($boms_data as $bom) { $parts = json_decode($bom['parts'], true); if (is_array($parts)) $all_part_ids_for_lookup = array_merge($all_part_ids_for_lookup, array_column($parts, 'part_id')); }
    foreach ($repair_jobs_data as $repairs) { foreach ($repairs as $repair) { $parts = json_decode($repair['part_quantity_reason'], true); if (is_array($parts)) $all_part_ids_for_lookup = array_merge($all_part_ids_for_lookup, array_column($parts, 'part_id')); } }
    
    $part_m3_map = [];
    if (!empty($all_part_ids_for_lookup)) {
        $unique_part_ids = array_unique(array_filter($all_part_ids_for_lookup));
        if (!empty($unique_part_ids)) {
            $part_ids_placeholder = implode(',', array_fill(0, count($unique_part_ids), '?'));
            $part_ids_types = str_repeat('i', count($unique_part_ids));
            $stmt = $conn->prepare("SELECT part_id, part_m3 FROM part_list WHERE part_id IN ($part_ids_placeholder)");
            call_user_func_array([$stmt, 'bind_param'], $ref_values(array_merge([$part_ids_types], $unique_part_ids)));
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) $part_m3_map[$row['part_id']] = (float)$row['part_m3'];
            $stmt->close();
        }
    }

    // --- 4. คำนวณ M3 และยอดรวมทั้งหมด (Grand Total) ---
    foreach ($all_completed_jobs as $job) {
        $job_id = $job['job_id'];
        $main_m3 = 0; $repair_m3 = 0;
        
        // Calculate main_m3
        if (isset($boms_data[$job['prod_id']])) {
            $parts = json_decode($boms_data[$job['prod_id']]['parts'], true);
            $job_quantity = (int)$job['main_job_quantity'];
            $bom_m3_sum = 0;
            if (is_array($parts)) {
                foreach ($parts as $part) {
                    $part_id = $part['part_id'] ?? null;
                    $part_qty = (int)($part['quantity'] ?? $part['part_qty'] ?? 0);
                    if ($part_id && isset($part_m3_map[$part_id])) {
                        $bom_m3_sum += ($part_m3_map[$part_id]) * $part_qty;
                    }
                }
            }
            $main_m3 = $bom_m3_sum * $job_quantity;
        }
        
        // Calculate repair_m3
        if (isset($repair_jobs_data[$job_id])) {
            foreach($repair_jobs_data[$job_id] as $repair) {
                $parts = json_decode($repair['part_quantity_reason'], true);
                if (is_array($parts)) {
                    foreach ($parts as $part) {
                        $part_id = $part['part_id'] ?? null;
                        $part_qty = (int)($part['quantity'] ?? 0);
                        if ($part_id && isset($part_m3_map[$part_id])) {
                            $repair_m3 += ($part_m3_map[$part_id]) * $part_qty;
                        }
                    }
                }
            }
        }
        
        $returned_m3 = $returned_wood_data[$job_id] ?? 0;
        $net_m3 = ($main_m3 + $repair_m3) - $returned_m3;

        $grand_total_prod_complete_qty += (int)$job['prod_complete_qty'];
        $grand_total_main_m3 += $main_m3;
        $grand_total_repair_m3 += $repair_m3;
        $grand_total_returned_m3 += $returned_m3;
        $grand_total_net_m3 += $net_m3;
    }

    // --- 5. เตรียมข้อมูลสำหรับแสดงผลในหน้าปัจจุบัน และคำนวณยอดรวมหน้านี้ (Page Total) ---
    foreach ($jobs_for_this_page as $job) {
        $job_id = $job['job_id'];
        $main_m3 = 0; $repair_m3 = 0;
        
        // Calculate main_m3 (same logic as above)
        if (isset($boms_data[$job['prod_id']])) {
            $parts = json_decode($boms_data[$job['prod_id']]['parts'], true);
            $job_quantity = (int)$job['main_job_quantity'];
            $bom_m3_sum = 0;
            if (is_array($parts)) {
                foreach ($parts as $part) {
                    $part_id = $part['part_id'] ?? null;
                    $part_qty = (int)($part['quantity'] ?? $part['part_qty'] ?? 0);
                    if ($part_id && isset($part_m3_map[$part_id])) {
                        $bom_m3_sum += ($part_m3_map[$part_id]) * $part_qty;
                    }
                }
            }
            $main_m3 = $bom_m3_sum * $job_quantity;
        }
        
        // Calculate repair_m3 (same logic as above)
        if (isset($repair_jobs_data[$job_id])) {
            foreach($repair_jobs_data[$job_id] as $repair) {
                $parts = json_decode($repair['part_quantity_reason'], true);
                if (is_array($parts)) {
                    foreach ($parts as $part) {
                        $part_id = $part['part_id'] ?? null;
                        $part_qty = (int)($part['quantity'] ?? 0);
                        if ($part_id && isset($part_m3_map[$part_id])) {
                            $repair_m3 += ($part_m3_map[$part_id]) * $part_qty;
                        }
                    }
                }
            }
        }
        
        $returned_m3 = $returned_wood_data[$job_id] ?? 0;
        $net_m3 = ($main_m3 + $repair_m3) - $returned_m3;

        $page_total_prod_complete_qty += (int)$job['prod_complete_qty'];
        $page_total_main_m3 += $main_m3;
        $page_total_repair_m3 += $repair_m3;
        $page_total_returned_m3 += $returned_m3;
        $page_total_net_m3 += $net_m3;

        $results[] = array_merge($job, [
            'main_m3' => $main_m3, 'repair_m3' => $repair_m3,
            'returned_m3' => $returned_m3, 'net_m3' => $net_m3
        ]);
    }
}

function generate_pagination($current_page, $total_pages, $url_params) {
    // เริ่มต้นสร้าง HTML ของ Pagination ด้วย Bootstrap 5
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // กำหนดจำนวนหน้าที่ต้องการให้แสดงข้างๆ หน้าปัจจุบัน
    $window = 2; 

    // --- 1. สร้างปุ่ม "ก่อนหน้า" (Previous) ---
    if ($current_page > 1) {
        // ถ้าไม่ใช่หน้าแรก ให้สร้างเป็นลิงก์ที่คลิกได้
        $html .= '<li class="page-item"><a class="page-link" href="?page=' . ($current_page - 1) . '&' . $url_params . '">ก่อนหน้า</a></li>';
    } else {
        // ถ้าเป็นหน้าแรก ให้แสดงเป็นปุ่มที่คลิกไม่ได้ (disabled)
        $html .= '<li class="page-item disabled"><span class="page-link">ก่อนหน้า</span></li>';
    }

    // --- 2. สร้างลิงก์หน้าแรก และเครื่องหมาย "..." ---
    // จะแสดงส่วนนี้ก็ต่อเมื่อหน้าปัจจุบันอยู่ไกลจากหน้าแรกมากพอ
    if ($current_page > $window + 1) {
        $html .= '<li class="page-item"><a class="page-link" href="?page=1&' . $url_params . '">1</a></li>';
        // แสดงเครื่องหมาย ... เพื่อย่อหน้าที่อยู่ระหว่างกลาง
        if ($current_page > $window + 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    // --- 3. สร้างลิงก์หน้าที่อยู่รอบๆ หน้าปัจจุบัน ---
    // วนลูปเพื่อสร้างลิงก์ตามจำนวนที่กำหนดใน $window
    for ($i = max(1, $current_page - $window); $i <= min($total_pages, $current_page + $window); $i++) {
        if ($i == $current_page) {
            // ถ้าเป็นหน้าปัจจุบัน ให้แสดงเป็นปุ่ม active
            $html .= '<li class="page-item active" aria-current="page"><span class="page-link">' . $i . '</span></li>';
        } else {
            // หน้าอื่นๆ ให้แสดงเป็นลิงก์ปกติ
            $html .= '<li class="page-item"><a class="page-link" href="?page=' . $i . '&' . $url_params . '">' . $i . '</a></li>';
        }
    }

    // --- 4. สร้างลิงก์หน้าสุดท้าย และเครื่องหมาย "..." ---
    // จะแสดงส่วนนี้ก็ต่อเมื่อหน้าปัจจุบันอยู่ไกลจากหน้าสุดท้ายมากพอ
    if ($current_page < $total_pages - $window) {
        if ($current_page < $total_pages - $window - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&' . $url_params . '">' . $total_pages . '</a></li>';
    }

    // --- 5. สร้างปุ่ม "ถัดไป" (Next) ---
    if ($current_page < $total_pages) {
        // ถ้าไม่ใช่หน้าสุดท้าย ให้สร้างเป็นลิงก์ที่คลิกได้
        $html .= '<li class="page-item"><a class="page-link" href="?page=' . ($current_page + 1) . '&' . $url_params . '">ถัดไป</a></li>';
    } else {
        // ถ้าเป็นหน้าสุดท้าย ให้แสดงเป็นปุ่มที่คลิกไม่ได้ (disabled)
        $html .= '<li class="page-item disabled"><span class="page-link">ถัดไป</span></li>';
    }

    $html .= '</ul></nav>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานสรุปปริมาตรไม้ (m³)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <style>
        body { background-color: #f8f9fa; }
        .container-fluid { background-color: #fff; border-radius: 8px; padding: 2rem; margin: 1rem; box-shadow: 0 4px 8px rgba(0,0,0,0.05); }
        .table-hover tbody tr:hover { background-color: #f1f1f1; }
        .table th, .table td { vertical-align: middle; text-align: center; }
        .table .text-start { text-align: left !important; }
        .table tfoot th { font-weight: bold; background-color: #f8f9fa; }
        /* Adjusted column widths */
        .table th:nth-child(1), .table td:nth-child(1) { width: 15%; } /* Job ID */
        .table th:nth-child(2), .table td:nth-child(2) { width: 20%; } /* Product Code */
        .table th:nth-child(3), .table td:nth-child(3) { width: 20%; } /* Part Code */
        .table th:nth-child(4), .table td:nth-child(4) { width: 10%; } /* จำนวนผลิตเสร็จ */
        .table th:nth-child(5), .table td:nth-child(5) { width: 15%; } /* วันที่รับงาน */
        .table th:nth-child(6), .table td:nth-child(6) { width: 20%; } /* M³ สุทธิ */
        /* For the summary rows in tfoot, adjust colspan if needed based on new columns */
        .table tfoot th:nth-child(1) { text-align: end; } /* "ยอดรวมหน้านี้" and "ยอดรวมทั้งหมด" */
    </style>
</head>
<body>
    <div class="container-fluid">
        <h1 class="mb-4"><i class="bi bi-bar-chart-line-fill text-primary"></i> รายงานสรุปปริมาตรไม้ (m³)</h1>
        <form method="GET" class="row g-3 align-items-center mb-4 p-3 border rounded bg-light">
            <div class="col-md-4">
                <label for="search_job_id" class="form-label">ค้นหาโดย Job ID:</label>
                <input type="text" id="search_job_id" name="search_job_id" class="form-control" value="<?= htmlspecialchars($search_job_id) ?>" placeholder="กรอก Job ID ที่ต้องการค้นหา...">
            </div>
            <div class="col-md-4">
                <label for="daterange" class="form-label">หรือเลือกช่วงวันที่รับงาน:</label>
                <input type="text" id="daterange" name="daterange" class="form-control">
                <input type="hidden" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                <input type="hidden" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> ค้นหา</button>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <a href="m3_summary_report.php" class="btn btn-secondary w-100"><i class="bi bi-arrow-clockwise"></i> ล้างค่า</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped table-hover table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>Job ID</th>
                        <th>PRODUCT CODE</th>
                        <th>PART CODE</th>
                        <th>จำนวนผลิตเสร็จ</th>
                        <th>วันที่รับงาน</th>
                        <th>M³ สุทธิ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($results)): ?>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['job_id']) ?></td>
                                <td class="text-start"><?= htmlspecialchars($row['prod_code'] ?? 'N/A') ?></td>
                                <td class="text-start"><?= htmlspecialchars($row['prod_partno'] ?? 'N/A') ?></td>
                                <td><?= number_format($row['prod_complete_qty'], 0) ?></td>
                                <td><?= $row['date_receive'] ? date('d/m/Y', strtotime($row['date_receive'])) : 'N/A' ?></td>
                                <td class="fw-bold bg-success-subtle"><?= number_format($row['net_m3'], 4) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center p-4">ไม่พบข้อมูลตามเงื่อนไขที่ค้นหา</td></tr> <!-- Adjusted colspan -->
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($results)): ?>
                <tfoot class="table-group-divider">
                    <tr class="table-light">
                        <th colspan="4" class="text-end">ยอดรวมหน้านี้:</th>
                        <th><?=  number_format($page_total_prod_complete_qty, 0) ?></th>
                        <th class="bg-info-subtle"><?= number_format($page_total_net_m3, 4) ?></th>
                    </tr>
                    <tr class="table-secondary">
                        <th colspan="4" class="text-end">ยอดรวมทั้งหมด (จากการค้นหา):</th>
                        <th><?= number_format($grand_total_prod_complete_qty, 0) ?></th>
                        <th class="bg-primary-subtle"><?= number_format($grand_total_net_m3, 4) ?></th>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <?php 
                $query_params = http_build_query([
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'search_job_id' => $search_job_id
                ]);
                echo generate_pagination($page, $total_pages, $query_params); 
            ?>
        <?php endif; ?>

    </div>

    <?php include 'footer.php'; ?>
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script>
        $(function() {
            $('#daterange').daterangepicker({
                startDate: moment('<?= htmlspecialchars($start_date) ?>'),
                endDate: moment('<?= htmlspecialchars($end_date) ?>'),
                locale: {
                    format: 'DD/MM/YYYY',
                    applyLabel: 'ตกลง',
                    cancelLabel: 'ยกเลิก',
                    fromLabel: 'จาก',
                    toLabel: 'ถึง',
                    customRangeLabel: 'กำหนดเอง',
                    weekLabel: 'สัปดาห์',
                    daysOfWeek: ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'],
                    monthNames: ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'],
                }
            }, function(start, end, label) {
                $('#start_date').val(start.format('YYYY-MM-DD'));
                $('#end_date').val(end.format('YYYY-MM-DD'));
            });

            if ($('#search_job_id').val().trim() !== '') {
                $('#daterange').prop('disabled', true).css('background-color', '#e9ecef');
            }

            $('#search_job_id').on('input', function() {
                if ($(this).val().trim() !== '') {
                    $('#daterange').prop('disabled', true).css('background-color', '#e9ecef');
                } else {
                    $('#daterange').prop('disabled', false).css('background-color', '');
                }
            });
        });
    </script>
</body>
</html>
