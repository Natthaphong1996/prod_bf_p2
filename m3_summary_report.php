<?php
// ไฟล์: m3_summary_report.php (ค้นหา 2 เงื่อนไข)
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
    INNER JOIN wood_issue AS wi ON jc.job_id = wi.job_id
    LEFT JOIN prod_list AS pl ON wi.prod_id = pl.prod_id
    WHERE wi.job_type NOT IN ('งานพาเลทไม้อัด','งานไม้ PACK','งานภายใน','PALLET RETURN')
";

// ======================= START: CODE MODIFICATION =======================
// ปรับปรุง Logic การค้นหาให้รองรับ 2 เงื่อนไขพร้อมกัน (AND)
$additional_conditions = [];

// เงื่อนไขที่ 1: ค้นหาตาม Job ID (ถ้ามีการกรอก)
if (!empty($search_job_id)) {
    $additional_conditions[] = "jc.job_id LIKE ?";
    $params[] = "%" . $search_job_id . "%";
    $types .= 's';
}

// เงื่อนไขที่ 2: ค้นหาตามช่วงวันที่ (จะทำงานเสมอ เนื่องจากมีค่า default)
if (!empty($start_date) && !empty($end_date)) {
    $additional_conditions[] = "DATE(jc.date_receive) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= 'ss';
}
// ======================== END: CODE MODIFICATION ========================

if (!empty($additional_conditions)) {
    $main_query_sql .= " AND " . implode(' AND ', $additional_conditions);
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
    die("SQL Prepare Error: " . $conn->error);
}

// ประกาศตัวแปรสำหรับเก็บยอดรวม
$page_total_prod_complete_qty = 0;
$page_total_net_m3 = 0;
$grand_total_prod_complete_qty = 0;
$grand_total_net_m3 = 0;

$results = [];

if (!empty($all_completed_jobs)) {
    // --- 3. ดึงข้อมูลประกอบทั้งหมดสำหรับทุก Job ที่พบ ---
    $all_job_ids = array_column($all_completed_jobs, 'job_id');
    $all_prod_ids = array_unique(array_filter(array_column($all_completed_jobs, 'prod_id')));
    
    $ref_values = function($arr) {
        $refs = [];
        foreach($arr as $key => $value) $refs[$key] = &$arr[$key];
        return $refs;
    };

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
    
    $part_data_map = [];
    if (!empty($all_part_ids_for_lookup)) {
        $unique_part_ids = array_unique(array_filter($all_part_ids_for_lookup));
        if (!empty($unique_part_ids)) {
            $part_ids_placeholder = implode(',', array_fill(0, count($unique_part_ids), '?'));
            $part_ids_types = str_repeat('i', count($unique_part_ids));
            
            // *** สำคัญ: ตรวจสอบให้แน่ใจว่า 'part_type' คือชื่อคอลัมน์ที่ถูกต้อง ***
            $stmt = $conn->prepare("SELECT part_id, part_m3, part_type FROM part_list WHERE part_id IN ($part_ids_placeholder)");
            
            call_user_func_array([$stmt, 'bind_param'], $ref_values(array_merge([$part_ids_types], $unique_part_ids)));
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $part_data_map[$row['part_id']] = [
                    'm3'   => (float)$row['part_m3'],
                    'type' => trim($row['part_type'] ?? '') 
                ];
            }
            $stmt->close();
        }
    }

    $allowed_types = [
        "PINE NON FSC", "PINE NON FSC ไส", "PINE NON FSC*", "PINE NON FSC*2",
        "PINE NON FSCไส1ด้าน", "PINE NON FSC*4", "PINE NON FSCไสเหลือ 22",
        "PINE NON FSCไสเหลือ 50", "PINE NON FSC บาก", "STOPPER",
        "Pine wood shocking", "PINE NON FSC เฉือน", "PINE NON FSC สโลป1แผ่น",
        "PINE NON FSC สโลป2แผ่น", "*** ไม้แพ็ค ***", "PINE NON FSC บาก2แบบ",
        "PINE NON FSC เฉือนหัว-ท้าย", "PINE NON FSC สโลป"
    ];

    // --- 4. คำนวณ M3 และยอดรวมทั้งหมด (Grand Total) ---
    foreach ($all_completed_jobs as &$job) { 
        $job_id = $job['job_id'];
        $main_m3 = 0; $repair_m3 = 0;
        
        if (isset($boms_data[$job['prod_id']])) {
            $parts = json_decode($boms_data[$job['prod_id']]['parts'], true);
            $job_quantity = (int)$job['main_job_quantity'];
            $bom_m3_sum = 0;
            if (is_array($parts)) {
                foreach ($parts as $part) {
                    $part_id = $part['part_id'] ?? null;
                    $part_qty = (int)($part['quantity'] ?? $part['part_qty'] ?? 0);
                    if ($part_id && isset($part_data_map[$part_id]) && in_array($part_data_map[$part_id]['type'], $allowed_types)) {
                        $bom_m3_sum += ($part_data_map[$part_id]['m3']) * $part_qty;
                    }
                }
            }
            $main_m3 = $bom_m3_sum * $job_quantity;
        }
        
        if (isset($repair_jobs_data[$job_id])) {
            foreach($repair_jobs_data[$job_id] as $repair) {
                $parts = json_decode($repair['part_quantity_reason'], true);
                if (is_array($parts)) {
                    foreach ($parts as $part) {
                        $part_id = $part['part_id'] ?? null;
                        $part_qty = (int)($part['quantity'] ?? 0);
                        if ($part_id && isset($part_data_map[$part_id]) && in_array($part_data_map[$part_id]['type'], $allowed_types)) {
                            $repair_m3 += ($part_data_map[$part_id]['m3']) * $part_qty;
                        }
                    }
                }
            }
        }
        
        $returned_m3 = $returned_wood_data[$job_id] ?? 0;
        $net_m3 = ($main_m3 + $repair_m3) - $returned_m3;

        $job['net_m3'] = $net_m3; 

        $grand_total_prod_complete_qty += (int)$job['prod_complete_qty'];
        $grand_total_net_m3 += $net_m3;
    }
    unset($job); 

    // --- 5. จัดการ Pagination และเตรียมข้อมูลสำหรับแสดงผลในหน้าปัจจุบัน ---
    $total_rows = count($all_completed_jobs);
    $total_pages = ceil($total_rows / $limit);
    $jobs_for_this_page = array_slice($all_completed_jobs, $offset, $limit);

    foreach ($jobs_for_this_page as $job) {
        $page_total_prod_complete_qty += (int)$job['prod_complete_qty'];
        $page_total_net_m3 += $job['net_m3'];
        $results[] = $job;
    }
}

function generate_pagination($current_page, $total_pages, $url_params) {
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    $window = 2; 

    if ($current_page > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="?page=' . ($current_page - 1) . '&' . $url_params . '">ก่อนหน้า</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">ก่อนหน้า</span></li>';
    }

    if ($current_page > $window + 1) {
        $html .= '<li class="page-item"><a class="page-link" href="?page=1&' . $url_params . '">1</a></li>';
        if ($current_page > $window + 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    for ($i = max(1, $current_page - $window); $i <= min($total_pages, $current_page + $window); $i++) {
        if ($i == $current_page) {
            $html .= '<li class="page-item active" aria-current="page"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="?page=' . $i . '&' . $url_params . '">' . $i . '</a></li>';
        }
    }

    if ($current_page < $total_pages - $window) {
        if ($current_page < $total_pages - $window - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&' . $url_params . '">' . $total_pages . '</a></li>';
    }

    if ($current_page < $total_pages) {
        $html .= '<li class="page-item"><a class="page-link" href="?page=' . ($current_page + 1) . '&' . $url_params . '">ถัดไป</a></li>';
    } else {
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
    <title>รายงานสรุปยอดผลิตพาเลทไม้สน</title>
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
        .table th:nth-child(1), .table td:nth-child(1) { width: 15%; }
        .table th:nth-child(2), .table td:nth-child(2) { width: 20%; }
        .table th:nth-child(3), .table td:nth-child(3) { width: 20%; }
        .table th:nth-child(4), .table td:nth-child(4) { width: 10%; }
        .table th:nth-child(5), .table td:nth-child(5) { width: 15%; }
        .table th:nth-child(6), .table td:nth-child(6) { width: 20%; }
        .table tfoot th:nth-child(1) { text-align: end; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <h1 class="mb-4"><i class="bi bi-bar-chart-line-fill text-primary"></i> รายงานสรุปยอดผลิตพาเลทไม้สน</h1>
        <form method="GET" class="row g-3 align-items-center mb-4 p-3 border rounded bg-light">
            <div class="col-md-4">
                <label for="search_job_id" class="form-label">ค้นหาโดย Job ID:</label>
                <input type="text" id="search_job_id" name="search_job_id" class="form-control" value="<?= htmlspecialchars($search_job_id) ?>" placeholder="กรอก Job ID และ/หรือเลือกวันที่">
            </div>
            <div class="col-md-4">
                <label for="daterange" class="form-label">และ/หรือเลือกช่วงวันที่รับงาน:</label>
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
                        <tr><td colspan="6" class="text-center p-4">ไม่พบข้อมูลตามเงื่อนไขที่ค้นหา</td></tr>
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

        <?php if (isset($total_pages) && $total_pages > 1): ?>
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

            // ======================= START: CODE MODIFICATION =======================
            // ลบ JavaScript ที่ปิดการใช้งาน daterange ออก
            // เพื่อให้สามารถกรอก Job ID และเลือกวันที่พร้อมกันได้
            // ======================== END: CODE MODIFICATION ========================
        });
    </script>
</body>
</html>
