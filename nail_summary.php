<?php
// nail_summary.php

session_start();
date_default_timezone_set('Asia/Bangkok');

// --- Security: ตรวจสอบการล็อกอิน ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once 'config_db.php';

// --- ตัวกรองช่วงวันที่ (Date Filter) ---
$default_end_date = date('Y-m-d');
$default_start_date = date('Y-m-d', strtotime('-29 days'));
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : $default_start_date;
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : $default_end_date;

// --- 1. ดึงข้อมูลการใช้งานทั้งหมด (เบิกครั้งแรก + เบิกซ่อม) ---
$sql_usage = "
    SELECT 
        wi.prod_id,
        u.nail_id,
        u.total_quantity
    FROM 
        (
            SELECT issue_id, nail_id, SUM(quantity_issued) AS total_quantity 
            FROM nail_usage_log 
            WHERE DATE(issue_timestamp) BETWEEN ? AND ?
            GROUP BY issue_id, nail_id
            
            UNION ALL
            
            SELECT issue_id, nail_id, SUM(quantity_issued) AS total_quantity 
            FROM nail_repair_log 
            WHERE DATE(repair_timestamp) BETWEEN ? AND ?
            GROUP BY issue_id, nail_id
        ) u
    JOIN wood_issue wi ON u.issue_id = wi.issue_id
";
$stmt_usage = $conn->prepare($sql_usage);
$stmt_usage->bind_param("ssss", $start_date, $end_date, $start_date, $end_date);
$stmt_usage->execute();
$usage_result = $stmt_usage->get_result();

// --- 2. เตรียมข้อมูลอ้างอิง ---
$nails_query = $conn->query("SELECT nail_id, nail_code FROM nail ORDER BY nail_code ASC");
$nails = [];
while ($row = $nails_query->fetch_assoc()) {
    $nails[$row['nail_id']] = $row;
}

$customers_query = $conn->query("SELECT customer_id, customer_name FROM customer ORDER BY customer_name ASC");
$customers = [];
while ($row = $customers_query->fetch_assoc()) {
    $customers[$row['customer_id']] = $row;
}

$prod_customer_query = $conn->query("SELECT prod_id, customer_id FROM prod_list");
$prod_map = $prod_customer_query->fetch_all(MYSQLI_ASSOC);
$prod_map = array_column($prod_map, 'customer_id', 'prod_id');

// --- 3. ประมวลผลข้อมูลเพื่อสร้างสรุป ---
$overall_summary = [];
$customer_summary = [];

foreach ($nails as $nail_id => $nail) {
    $overall_summary[$nail_id] = ['code' => $nail['nail_code'], 'total' => 0];
}
foreach ($customers as $customer_id => $customer) {
    $customer_summary[$customer_id] = [
        'name' => $customer['customer_name'],
        'nails' => array_fill_keys(array_keys($nails), 0)
    ];
}

while ($row = $usage_result->fetch_assoc()) {
    $prod_id = $row['prod_id'];
    $nail_id = $row['nail_id'];
    $quantity = (int)$row['total_quantity'];

    if (isset($overall_summary[$nail_id])) {
        $overall_summary[$nail_id]['total'] += $quantity;
    }

    if (isset($prod_map[$prod_id])) {
        $customer_id = $prod_map[$prod_id];
        if (isset($customer_summary[$customer_id]['nails'][$nail_id])) {
            $customer_summary[$customer_id]['nails'][$nail_id] += $quantity;
        }
    }
}

uasort($overall_summary, fn($a, $b) => $b['total'] <=> $a['total']);
$chart_labels = json_encode(array_column($overall_summary, 'code'));
$chart_data = json_encode(array_column($overall_summary, 'total'));

$stmt_usage->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สรุปการใช้งานตะปู</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0">Dashboard สรุปการใช้งานตะปู</h1>
        </div>

        <!-- Filter Form -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label for="date_range" class="form-label">เลือกช่วงวันที่:</label>
                        <input type="text" id="date_range" name="date_range" class="form-control">
                        <input type="hidden" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                        <input type="hidden" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-filter"></i> กรองข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 1. Overall Usage Section -->
        <div class="card shadow-sm mb-5">
            <div class="card-header">
                <h4>ภาพรวมการใช้งานตะปูทั้งหมด (ม้วน)</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-8 mb-4 mb-lg-0">
                        <canvas id="overallUsageChart"></canvas>
                    </div>
                    <div class="col-lg-4">
                        <h5>ข้อมูลตามตาราง</h5>
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-striped table-sm">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>รหัสตะปู</th>
                                        <th class="text-end">จำนวน (ม้วน)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($overall_summary as $data): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($data['code']) ?></td>
                                        <td class="text-end"><?= number_format($data['total']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- [แก้ไข] 2. Usage by Customer Section -->
        <div class="border-top pt-4">
             <h4 class="mb-3">สรุปการใช้งานตะปูแยกตามลูกค้า (ม้วน)</h4>
             <div class="row">
                <?php 
                $customer_with_usage = 0;
                foreach ($customer_summary as $data): 
                    // ตรวจสอบว่าลูกค้ารายนี้มีการใช้งานหรือไม่
                    if (array_sum($data['nails']) == 0) {
                        continue; // ข้ามไปถ้าไม่มีการใช้งาน
                    }
                    $customer_with_usage++;
                ?>
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header bg-light">
                                <h5 class="mb-0"><?= htmlspecialchars($data['name']) ?></h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th>รหัสตะปู</th>
                                                <th class="text-end">จำนวน (ม้วน)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            // จัดเรียงตะปูที่ใช้จากมากไปน้อย
                                            arsort($data['nails']);
                                            foreach ($data['nails'] as $nail_id => $quantity): 
                                                // แสดงเฉพาะตะปูที่มีการใช้งาน
                                                if ($quantity > 0): 
                                            ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($nails[$nail_id]['nail_code']) ?></td>
                                                    <td class="text-end"><?= number_format($quantity) ?></td>
                                                </tr>
                                            <?php 
                                                endif; 
                                            endforeach; 
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if ($customer_with_usage == 0): ?>
                    <div class="col-12">
                        <div class="alert alert-secondary text-center">ไม่พบข้อมูลการใช้งานแยกตามลูกค้าในช่วงวันที่ที่เลือก</div>
                    </div>
                <?php endif; ?>
             </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script>
    $(function() {
        const startDate = moment('<?= $start_date ?>');
        const endDate = moment('<?= $end_date ?>');

        $('#date_range').daterangepicker({
            startDate: startDate,
            endDate: endDate,
            locale: {
                format: 'DD/MM/YYYY',
                applyLabel: "ตกลง",
                cancelLabel: "ยกเลิก",
                fromLabel: "จาก",
                toLabel: "ถึง",
                customRangeLabel: "กำหนดเอง",
                daysOfWeek: ["อา", "จ", "อ", "พ", "พฤ", "ศ", "ส"],
                monthNames: ["มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"],
            }
        }, function(start, end) {
            $('#start_date').val(start.format('YYYY-MM-DD'));
            $('#end_date').val(end.format('YYYY-MM-DD'));
        });

        const ctx = document.getElementById('overallUsageChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= $chart_labels ?>,
                datasets: [{
                    label: 'จำนวนการใช้งาน (ม้วน)',
                    data: <?= $chart_data ?>,
                    backgroundColor: 'rgba(0, 123, 255, 0.5)',
                    borderColor: 'rgba(0, 123, 255, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } },
                plugins: { legend: { display: false } }
            }
        });
    });
    </script>
</body>
</html>
