<?php
// ภาษา: PHP
// ชื่อไฟล์: calculate_wages.php
// คอมเมนต์: หน้าแสดงและคำนวณค่าจ้างไม้ พร้อมเลือกแหล่งราคาโดยใช้ issue_status และ production_wages

ob_start();

date_default_timezone_set('Asia/Bangkok');
session_start();
include_once __DIR__ . '/config_db.php';

// --- รับค่าช่วงวันที่ ---
$dateRange = $_GET['daterange'] ?? '';
if (empty($dateRange)) {
    $firstDay = date('Y-m-01');
    $lastDay  = date('Y-m-t');
    $dateRange = "$firstDay - $lastDay";
}
list($start, $end) = explode(' - ', $dateRange);
$startDate = $start . ' 00:00:00';
$endDate   = $end   . ' 23:59:59';

$whereSql = 'WHERE wi.creation_date BETWEEN ? AND ?';
$params   = [$startDate, $endDate];
$types    = 'ss';

// --- SQL หลัก พร้อม CASE WHEN เลือกแหล่งราคา ---
// SQL หลัก: เช็ค status ก่อน
$baseSql = "
SELECT
  wi.job_id,
  pl.prod_code,
  pl.prod_type,
  wi.quantity,
  wi.wood_wastage,
  wi.creation_date,
  (
    SELECT pp.price_value
    FROM product_price pp
    WHERE pp.prod_id = pl.prod_id
    ORDER BY pp.date_update DESC
    LIMIT 1
  ) AS price_value
FROM wood_issue wi
LEFT JOIN prod_list pl ON wi.prod_id = pl.prod_id
" . $whereSql;


if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // ตั้งค่า header สำหรับ CSV
    header('Content-Type: text/csv; charset=utf-8');
    // ชื่อไฟล์ตามรูปแบบ wages_DD_MM_YYYY_HHMMSS.csv
    $filename = 'wages_' . date('d_m_Y_His') . '.csv';
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // เตรียม statement และ bind parameter
    $exportStmt = $conn->prepare($baseSql);
    $exportStmt->bind_param($types, ...$params);
    $exportStmt->execute();
    $exportResult = $exportStmt->get_result();

    // เขียนข้อมูลออกเป็น CSV
    $output = fopen('php://output', 'w');
    // CSV header
    fputcsv($output, ['Job ID', 'Product Code', 'Prod Type', 'Quantity', 'Price/Unit', 'Net Wage', 'Creation Date']);
    while ($row = $exportResult->fetch_assoc()) {
        $qty      = (int) $row['quantity'];
        $price    = (float) $row['price_value'];
        $netWage  = $qty * $price * (1 - (float) $row['wood_wastage']);
        fputcsv($output, [
            $row['job_id'],
            $row['prod_code'],
            $row['prod_type'],
            $qty,
            number_format($price, 2, '.', ''),
            number_format($netWage, 2, '.', ''),
            $row['creation_date'],
        ]);
    }
    fclose($output);
    exit();
}


include_once __DIR__ . '/navbar.php';

// --- คำนวณสรุปช่วง ---
$sumStmt = $conn->prepare($baseSql);
$sumStmt->bind_param($types, ...$params);
$sumStmt->execute();
$sumRes = $sumStmt->get_result();
$totalQty = 0;
$totalNet = 0;
while ($r = $sumRes->fetch_assoc()) {
    $q = (int)$r['quantity'];
    $p = (float)$r['price_value'];
    $totalQty += $q;
    $totalNet += $q * $p * (1 - (float)$r['wood_wastage']);
}
$sumStmt->close();

// --- Pagination ---
$perPage = 100;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $perPage;

$countSql = "SELECT COUNT(*) FROM wood_issue wi " . $whereSql;
$cntStmt = $conn->prepare($countSql);
$cntStmt->bind_param($types, ...$params);
$cntStmt->execute();
$cntStmt->bind_result($totalRows);
$cntStmt->fetch();
$cntStmt->close();
$totalPages = (int)ceil($totalRows / $perPage);

// --- ดึงข้อมูลหน้าปัจจุบัน ---
$dataSql = $baseSql . " LIMIT ?, ?";
$dataStmt = $conn->prepare($dataSql);
$dataStmt->bind_param($types . 'ii', ...array_merge($params, [$offset, $perPage]));
$dataStmt->execute();
$dataRes = $dataStmt->get_result();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/min/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker@3.1/daterangepicker.min.js"></script>
    <title>คำนวณค่าแรง</title>
</head>
<body>
<div class="container mt-4 mb-4">
    <h2>คำนวณค่าแรง</h2>
    <form method="get" class="row gy-2 gx-3 align-items-center mb-3">
        <div class="col-auto">
            <input type="text" class="form-control" name="daterange" value="<?= htmlspecialchars($dateRange) ?>" placeholder="YYYY-MM-DD - YYYY-MM-DD" />
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">ค้นหา</button>
        </div>
        <div class="col-auto">
            <a href="?<?= http_build_query(array_merge($_GET,['export'=>'csv'])) ?>" class="btn btn-success">Export CSV</a>
        </div>
    </form>
    <div class="mb-3">
        <strong>รวมช่วงที่เลือก:</strong> จำนวน = <?= number_format($totalQty) ?>, ค่าจ้างสุทธิ = <?= number_format($totalNet, 2) ?>
    </div>
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Job ID</th>
                <th>Product Code</th>
                <th>Prod Type</th>
                <th class="text-end">Quantity</th>
                <th class="text-end">Price/Unit</th>
                <th class="text-end">Net Wage</th>
                <th>วันที่</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $dataRes->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['job_id']) ?></td>
                <td><?= htmlspecialchars($row['prod_code']) ?></td>
                <td><?= htmlspecialchars($row['prod_type']) ?></td>
                <td class="text-end"><?= number_format((int)$row['quantity']) ?></td>
                <td class="text-end"><?= number_format((float)$row['price_value'], 2) ?></td>
                <td class="text-end"><?= number_format((int)$row['quantity'] * (float)$row['price_value'] * (1 - (float)$row['wood_wastage']), 2) ?></td>
                <td><?= $row['creation_date'] ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php if ($totalPages > 1): ?>
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <?php
                $start = max(1, $currentPage - 2);
                $end   = min($totalPages, $currentPage + 2);
                if ($start > 1) echo '<li class="page-item"><a class="page-link" href="?page=1&daterange=' . urlencode($dateRange) . '">1</a></li>';
                if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
                for ($i = $start; $i <= $end; $i++) {
                    $act = $i === $currentPage ? ' active' : '';
                    echo '<li class="page-item' . $act . '"><a class="page-link" href="?page=' . $i . '&daterange=' . urlencode($dateRange) . '">' . $i . '</a></li>';
                }
                if ($end < $totalPages - 1) echo '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
                if ($end < $totalPages) echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '&daterange=' . urlencode($dateRange) . '">' . $totalPages . '</a></li>';
            ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>
<?php include_once __DIR__ . '/footer.php'; ?>
<script>
$('input[name="daterange"]').daterangepicker({ opens:'left', locale:{ format:'YYYY-MM-DD' } });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$dataStmt->close();
$conn->close();
ob_end_flush();
?>
