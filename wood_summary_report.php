<?php
// wood_summary_report.php

// ตรวจสอบการเข้าสู่ระบบ หรือการอนุญาตการเข้าถึง (ถ้ามี)
// require_once 'auth_check.php'; // สมมติว่ามีไฟล์ auth_check.php สำหรับตรวจสอบสิทธิ์

// ไฟล์เชื่อมต่อฐานข้อมูล
require_once 'config_db.php';
// ไฟล์ฟังก์ชันที่จำเป็นอื่นๆ (หากมี)
require_once 'functions.php';

// กำหนดค่าเริ่มต้นสำหรับช่วงวันที่ หากไม่มีการส่งค่ามา
$startDate1 = date('Y-m-01', strtotime('-1 month')); // เริ่มต้นเดือนที่แล้ว
$endDate1 = date('Y-m-t', strtotime('-1 month'));   // สิ้นสุดเดือนที่แล้ว

$startDate2 = date('Y-m-01'); // เริ่มต้นเดือนปัจจุบัน
$endDate2 = date('Y-m-t');   // สิ้นสุดเดือนปัจจุบัน
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานเปรียบเทียบค่าแรงการผลิตรายจุดประกอบ</title>
    <!-- Bootstrap 5.3 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- daterangepicker CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        .container {
            margin-top: 30px;
            margin-bottom: 30px;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px; /* Add margin below cards */
        }
        .table-responsive {
            margin-top: 20px;
        }
        .table thead th {
            background-color: #007bff;
            color: white;
            border-bottom: none;
        }
        .table tbody th {
            background-color: #f0f0f0; /* Color for group totals */
            font-weight: bold;
        }
        .table tbody tr:hover {
            background-color: #f1f1f1;
        }
        .form-label {
            font-weight: bold;
        }
        #loadingSpinner {
            display: none;
            text-align: center;
            margin-top: 20px;
        }
        .alert-custom {
            border-radius: 10px;
        }
        .comparison-header {
            background-color: #e9ecef;
            font-weight: bold;
        }
        .group-header {
            background-color: #d1ecf1; /* Light blue for group headers */
            font-weight: bold;
            text-align: left !important;
        }
        .group-total-row {
            background-color: #e2e3e5; /* Slightly darker than group header for totals */
            font-weight: bold;
        }
        .chart-container {
            position: relative;
            margin: auto;
            height: 400px; /* Fixed height for charts */
            width: 100%;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; // Inclusion of the navigation bar ?>

    <div class="container">
        <h2 class="mb-4 text-center">รายงานเปรียบเทียบค่าแรงการผลิตรายจุดประกอบ</h2>

        <div class="card p-4 mb-4">
            <h4 class="card-title mb-3">ตัวกรองข้อมูล</h4>
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label for="dateRangePicker1" class="form-label">เลือกช่วงวันที่ 1 (วันที่สร้างค่าแรง):</label>
                    <input type="text" class="form-control rounded-lg" id="dateRangePicker1" value="<?php echo htmlspecialchars($startDate1 . ' - ' . $endDate1); ?>">
                </div>
                <div class="col-md-6">
                    <label for="dateRangePicker2" class="form-label">เลือกช่วงวันที่ 2 (วันที่สร้างค่าแรง):</label>
                    <input type="text" class="form-control rounded-lg" id="dateRangePicker2" value="<?php echo htmlspecialchars($startDate2 . ' - ' . $endDate2); ?>">
                </div>
                <div class="col-md-12"> <!-- Adjusted to full width as PDF button is removed -->
                    <button id="filterBtn" class="btn btn-primary w-100 rounded-lg">
                        <i class="fas fa-filter me-2"></i> กรองข้อมูล
                    </button>
                </div>
                <!-- PDF Export Button Removed -->
            </div>
        </div>

        <div id="loadingSpinner" class="alert alert-info alert-custom" role="alert">
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            กำลังโหลดข้อมูล... โปรดรอสักครู่
        </div>

        <div id="summaryResults" class="card p-4">
            <h4 class="card-title mb-3">ผลสรุปการเปรียบเทียบ</h4>
            <div class="table-responsive">
                <table class="table table-striped table-bordered text-center">
                    <thead>
                        <tr>
                            <th rowspan="2" class="align-middle">กลุ่ม</th>
                            <th rowspan="2" class="align-middle">จุดประกอบ</th>
                            <th colspan="3" class="comparison-header" id="period1Header">ช่วงที่ 1 (<?php echo htmlspecialchars($startDate1 . ' - ' . $endDate1); ?>)</th>
                            <th colspan="3" class="comparison-header" id="period2Header">ช่วงที่ 2 (<?php echo htmlspecialchars($startDate2 . ' - ' . $endDate2); ?>)</th>
                            <th colspan="3" class="comparison-header">ผลต่าง (ช่วง 2 - ช่วง 1)</th>
                        </tr>
                        <tr>
                            <th>จำนวนชิ้นงาน (ตัว)</th>
                            <th>ปริมาตร (ลบ.ม.)</th>
                            <th>ราคารวม (บาท)</th>
                            <th>จำนวนชิ้นงาน (ตัว)</th>
                            <th>ปริมาตร (ลบ.ม.)</th>
                            <th>ราคารวม (บาท)</th>
                            <th>จำนวนชิ้นงาน (ตัว)</th>
                            <th>ปริมาตร (ลบ.ม.)</th>
                            <th>ราคารวม (บาท)</th>
                        </tr>
                    </thead>
                    <tbody id="summaryTableBody">
                        <!-- Data will be loaded here via AJAX -->
                        <tr>
                            <td colspan="11">กรุณาเลือกช่วงวันที่และกด "กรองข้อมูล" เพื่อดูผลสรุป</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="2" class="text-end">รวมทั้งหมด:</th>
                            <th id="totalQuantity1">0</th>
                            <th id="totalM3_1">0.00</th>
                            <th id="totalPrice1">0.00</th>
                            <th id="totalQuantity2">0</th>
                            <th id="totalM3_2">0.00</th>
                            <th id="totalPrice2">0.00</th>
                            <th id="totalQuantityDiff">0</th>
                            <th id="totalM3_Diff">0.00</th>
                            <th id="totalPriceDiff">0.00</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="card p-4 mt-4">
            <h4 class="card-title mb-3">กราฟเปรียบเทียบข้อมูล</h4>
            <div class="row">
                <div class="col-md-6">
                    <h5 class="text-center">จำนวนชิ้นงานผลิตสำเร็จ (ตัว)</h5>
                    <div class="chart-container">
                        <canvas id="quantityChart"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <h5 class="text-center">ราคารวม (บาท)</h5>
                    <div class="chart-container">
                        <canvas id="priceChart"></canvas>
                    </div>
                </div>
                <div class="col-md-12">
                    <h5 class="text-center">ปริมาตรรวม (ลบ.ม.)</h5>
                    <div class="chart-container">
                        <canvas id="m3Chart"></canvas>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <?php include 'modals.php'; // Inclusion of any modals, if needed (e.g., success/error messages) ?>
    <?php include 'footer.php'; // Inclusion of the footer ?>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script type="text/javascript" src="https://cdn.jsdelivr.net/jquery/latest/jquery.min.js"></script>
    <!-- Moment.js -->
    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <!-- daterangepicker JS -->
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <!-- Font Awesome for icons -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/js/all.min.js"></script>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <script>
        let quantityChartInstance = null; // To hold Chart.js instance for quantity
        let priceChartInstance = null;   // To hold Chart.js instance for price
        let m3ChartInstance = null;      // To hold Chart.js instance for m3

        // Define grouping for assembly points in frontend
        const group_sk = ["A-SK", "E-SK", "F-SK", "G-SK", "H-SK", "N-SK"];
        const group_ppk = ["C-PPK", "D-PPK", "K-PPK", "P-PPK", "SK-D(PPK)", "SK-G(PPK)", "W-PPK", "Y-PPK"];
        const group_pcn = ["B-PCN", "I-PCN"];

        // Helper function to get the group name
        function getGroupName(assembly_point) {
            if (group_sk.includes(assembly_point)) {
                return "กลุ่ม SK";
            } else if (group_ppk.includes(assembly_point)) {
                return "กลุ่ม PPK";
            } else if (group_pcn.includes(assembly_point)) {
                return "กลุ่ม PCN";
            } else {
                return "อื่นๆ"; // Or any other default group name
            }
        }

        $(document).ready(function() {
            // Initialize Date Range Picker 1
            $('#dateRangePicker1').daterangepicker({
                opens: 'left',
                locale: {
                    format: 'YYYY-MM-DD',
                    applyLabel: 'ตกลง',
                    cancelLabel: 'ยกเลิก',
                    fromLabel: 'จาก',
                    toLabel: 'ถึง',
                    customRangeLabel: 'กำหนดเอง',
                    weekLabel: 'สัปดาห์',
                    daysOfWeek: ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'],
                    monthNames: ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'],
                    firstDay: 0
                },
                startDate: moment('<?php echo $startDate1; ?>'),
                endDate: moment('<?php echo $endDate1; ?>')
            }, function(start, end, label) {
                // Update header text for period 1
                $('#period1Header').text(`ช่วงที่ 1 (${start.format('YYYY-MM-DD')} - ${end.format('YYYY-MM-DD')})`);
            });

            // Initialize Date Range Picker 2
            $('#dateRangePicker2').daterangepicker({
                opens: 'left',
                locale: {
                    format: 'YYYY-MM-DD',
                    applyLabel: 'ตกลง',
                    cancelLabel: 'ยกเลิก',
                    fromLabel: 'จาก',
                    toLabel: 'ถึง',
                    customRangeLabel: 'กำหนดเอง',
                    weekLabel: 'สัปดาห์',
                    daysOfWeek: ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'],
                    monthNames: ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'],
                    firstDay: 0
                },
                startDate: moment('<?php echo $startDate2; ?>'),
                endDate: moment('<?php echo $endDate2; ?>')
            }, function(start, end, label) {
                // Update header text for period 2
                $('#period2Header').text(`ช่วงที่ 2 (${start.format('YYYY-MM-DD')} - ${end.format('YYYY-MM-DD')})`);
            });

            // Function to fetch and display summary data
            function fetchSummaryData() {
                // Show loading spinner
                $('#loadingSpinner').show();
                $('#summaryResults').hide(); // Hide previous results
                $('.chart-container').hide(); // Hide charts while loading

                let dateRange1 = $('#dateRangePicker1').val();
                let dates1 = dateRange1.split(' - ');
                let startDate1 = dates1[0];
                let endDate1 = dates1[1];

                let dateRange2 = $('#dateRangePicker2').val();
                let dates2 = dateRange2.split(' - ');
                let startDate2 = dates2[0];
                let endDate2 = dates2[1];

                // Update table headers to reflect selected dates
                $('#period1Header').text(`ช่วงที่ 1 (${startDate1} - ${endDate1})`);
                $('#period2Header').text(`ช่วงที่ 2 (${startDate2} - ${endDate2})`);

                $.ajax({
                    url: 'fetch_production_wages_summary.php',
                    method: 'POST',
                    data: {
                        startDate1: startDate1,
                        endDate1: endDate1,
                        startDate2: startDate2,
                        endDate2: endDate2
                    },
                    dataType: 'json',
                    success: function(response) {
                        $('#loadingSpinner').hide(); // Hide loading spinner
                        $('#summaryResults').show(); // Show results section
                        $('.chart-container').show(); // Show charts after data is ready

                        let tableBody = $('#summaryTableBody');
                        tableBody.empty(); // Clear existing rows

                        let grandTotalQuantity1 = 0;
                        let grandTotalM3_1 = 0;
                        let grandTotalPrice1 = 0;
                        let grandTotalQuantity2 = 0;
                        let grandTotalM3_2 = 0;
                        let grandTotalPrice2 = 0;

                        // Data for charts
                        let chartLabels = [];
                        let chartQuantityPeriod1 = [];
                        let chartQuantityPeriod2 = [];
                        let chartM3Period1 = [];
                        let chartM3Period2 = [];
                        let chartPricePeriod1 = [];
                        let chartPricePeriod2 = [];

                        if (response.status === 'success' && response.data) {
                            let comparisonData = response.data;
                            let allAssemblyPoints = new Set();
                            let aggregatedByGroup = {}; // New object to store totals for each group

                            // Collect all unique assembly points from both periods
                            Object.keys(comparisonData.period1_data).forEach(point => allAssemblyPoints.add(point));
                            Object.keys(comparisonData.period2_data).forEach(point => allAssemblyPoints.add(point));

                            if (allAssemblyPoints.size > 0) {
                                // Create an array of objects to sort by group then assembly_point
                                let groupedAndSortedData = [];
                                allAssemblyPoints.forEach(point => {
                                    groupedAndSortedData.push({
                                        assembly_point: point,
                                        group_name: getGroupName(point)
                                    });
                                });

                                // Sort data: primary by custom group order, secondary by assembly point
                                groupedAndSortedData.sort((a, b) => {
                                    let groupOrder = ["กลุ่ม SK", "กลุ่ม PPK", "กลุ่ม PCN", "อื่นๆ"]; // Define custom sort order for groups
                                    let groupAIndex = groupOrder.indexOf(a.group_name);
                                    let groupBIndex = groupOrder.indexOf(b.group_name);

                                    if (groupAIndex - groupBIndex !== 0) {
                                        return groupAIndex - groupBIndex; // Sort by custom group order
                                    }
                                    return a.assembly_point.localeCompare(b.assembly_point); // Then by assembly point
                                });

                                let currentGroup = "";
                                groupedAndSortedData.forEach(function(item) {
                                    let point = item.assembly_point;
                                    let group_name = item.group_name;

                                    let p1 = comparisonData.period1_data[point] || {total_quantity: 0, total_m3: 0, total_price: 0};
                                    let p2 = comparisonData.period2_data[point] || {total_quantity: 0, total_m3: 0, total_price: 0};

                                    // Aggregate totals for each group
                                    if (!aggregatedByGroup[group_name]) {
                                        aggregatedByGroup[group_name] = {
                                            quantity1: 0, m3_1: 0, price1: 0,
                                            quantity2: 0, m3_2: 0, price2: 0
                                        };
                                    }
                                    aggregatedByGroup[group_name].quantity1 += p1.total_quantity;
                                    aggregatedByGroup[group_name].m3_1 += p1.total_m3;
                                    aggregatedByGroup[group_name].price1 += p1.total_price;
                                    aggregatedByGroup[group_name].quantity2 += p2.total_quantity;
                                    aggregatedByGroup[group_name].m3_2 += p2.total_m3;
                                    aggregatedByGroup[group_name].price2 += p2.total_price;
                                    
                                    // Add group header row if new group
                                    if (group_name !== currentGroup) {
                                        // If it's not the very first group, and we are changing groups, add the total row for the previous group
                                        if (currentGroup !== "") {
                                            let prevGroupTotals = aggregatedByGroup[currentGroup];
                                            tableBody.append(`
                                                <tr class="group-total-row">
                                                    <td colspan="2" class="text-end">รวม ${htmlspecialchars(currentGroup)}:</td>
                                                    <td>${htmlspecialchars(prevGroupTotals.quantity1.toLocaleString('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 0 }))}</td>
                                                    <td>${htmlspecialchars(prevGroupTotals.m3_1.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))}</td>
                                                    <td>${htmlspecialchars(prevGroupTotals.price1.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))}</td>
                                                    <td>${htmlspecialchars(prevGroupTotals.quantity2.toLocaleString('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 0 }))}</td>
                                                    <td>${htmlspecialchars(prevGroupTotals.m3_2.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))}</td>
                                                    <td>${htmlspecialchars(prevGroupTotals.price2.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))}</td>
                                                    <td>${htmlspecialchars((prevGroupTotals.quantity2 - prevGroupTotals.quantity1).toLocaleString('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 0 }))}</td>
                                                    <td>${htmlspecialchars((prevGroupTotals.m3_2 - prevGroupTotals.m3_1).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))}</td>
                                                    <td>${htmlspecialchars((prevGroupTotals.price2 - prevGroupTotals.price1).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))}</td>
                                                </tr>
                                            `);
                                        }
                                        tableBody.append(`
                                            <tr class="group-header">
                                                <td colspan="11">${htmlspecialchars(group_name)}</td>
                                            </tr>
                                        `);
                                        currentGroup = group_name;
                                    }

                                    // Calculate diffs for individual assembly points
                                    let quantityDiff = p2.total_quantity - p1.total_quantity;
                                    let m3Diff = p2.total_m3 - p1.total_m3;
                                    let priceDiff = p2.total_price - p1.total_price;

                                    let row = `
                                        <tr>
                                            <td></td> <!-- Empty for group column in detail rows -->
                                            <td>${htmlspecialchars(point)}</td>
                                            <td>${htmlspecialchars(p1.total_quantity.toLocaleString('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 0 }))}</td>
                                            <td>${htmlspecialchars(p1.total_m3.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))}</td>
                                            <td>${htmlspecialchars(p1.total_price.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))}</td>
                                            <td>${htmlspecialchars(p2.total_quantity.toLocaleString('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 0 }))}</td>
                                            <td>${htmlspecialchars(p2.total_m3.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))}</td>
                                            <td>${htmlspecialchars(p2.total_price.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))}</td>
                                            <td>${htmlspecialchars(quantityDiff.toLocaleString('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 0 }))}</td>
                                            <td>${htmlspecialchars(m3Diff.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))}</td>
                                            <td>${htmlspecialchars(priceDiff.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))}</td>
                                        </tr>
                                    `;
                                    tableBody.append(row);

                                    // Accumulate for grand totals
                                    grandTotalQuantity1 += p1.total_quantity;
                                    grandTotalM3_1 += p1.total_m3;
                                    grandTotalPrice1 += p1.total_price;
                                    grandTotalQuantity2 += p2.total_quantity;
                                    grandTotalM3_2 += p2.total_m3;
                                    grandTotalPrice2 += p2.total_price;
                                    
                                    // Collect data for charts (still by assembly_point)
                                    chartLabels.push(point);
                                    chartQuantityPeriod1.push(p1.total_quantity);
                                    chartQuantityPeriod2.push(p2.total_quantity);
                                    chartM3Period1.push(p1.total_m3);
                                    chartM3Period2.push(p2.total_m3);
                                    chartPricePeriod1.push(p1.total_price);
                                    chartPricePeriod2.push(p2.total_price);
                                });

                                // Append the total row for the very last group after the loop finishes
                                if (currentGroup !== "") {
                                    let lastGroupTotals = aggregatedByGroup[currentGroup];
                                    tableBody.append(`
                                        <tr class="group-total-row">
                                            <td colspan="2" class="text-end">รวม ${htmlspecialchars(currentGroup)}:</td>
                                            <td>${htmlspecialchars(lastGroupTotals.quantity1.toLocaleString('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 0 }))}</td>
                                            <td>${htmlspecialchars(lastGroupTotals.m3_1.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))}</td>
                                            <td>${htmlspecialchars(lastGroupTotals.price1.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))}</td>
                                            <td>${htmlspecialchars(lastGroupTotals.quantity2.toLocaleString('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 0 }))}</td>
                                            <td>${htmlspecialchars(lastGroupTotals.m3_2.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))}</td>
                                            <td>${htmlspecialchars(lastGroupTotals.price2.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))}</td>
                                            <td>${htmlspecialchars((lastGroupTotals.quantity2 - lastGroupTotals.quantity1).toLocaleString('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 0 }))}</td>
                                            <td>${htmlspecialchars((lastGroupTotals.m3_2 - lastGroupTotals.m3_1).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))}</td>
                                            <td>${htmlspecialchars((lastGroupTotals.price2 - lastGroupTotals.price1).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))}</td>
                                        </tr>
                                    `);
                                }

                            } else {
                                tableBody.append(`
                                    <tr>
                                        <td colspan="11" class="text-center">
                                            ไม่พบข้อมูลสำหรับช่วงวันที่ที่เลือกทั้งสองช่วง
                                        </td>
                                    </tr>
                                `);
                            }
                            // Render charts only if data is available
                            if (chartLabels.length > 0) {
                                renderCharts(chartLabels, chartQuantityPeriod1, chartQuantityPeriod2, chartM3Period1, chartM3Period2, chartPricePeriod1, chartPricePeriod2);
                            } else {
                                // Destroy existing charts if no data
                                if (quantityChartInstance) quantityChartInstance.destroy();
                                if (priceChartInstance) priceChartInstance.destroy();
                                if (m3ChartInstance) m3ChartInstance.destroy();
                                // Optional: Display a message within the chart area
                                $('#quantityChart').parent().html('<canvas id="quantityChart"></canvas><p class="text-center text-muted mt-3">ไม่พบข้อมูลสำหรับกราฟจำนวนชิ้นงาน</p>');
                                $('#priceChart').parent().html('<canvas id="priceChart"></canvas><p class="text-center text-muted mt-3">ไม่พบข้อมูลสำหรับกราฟราคารวม</p>');
                                $('#m3Chart').parent().html('<canvas id="m3Chart"></canvas><p class="text-center text-muted mt-3">ไม่พบข้อมูลสำหรับกราฟปริมาตรรวม</p>');
                            }

                        } else {
                            // Display a message if no data is found or an error occurred
                            tableBody.append(`
                                <tr>
                                    <td colspan="11" class="text-center">
                                        ${response.message || 'ไม่พบข้อมูลสำหรับช่วงวันที่ที่เลือก'}
                                    </td>
                                </tr>
                            `);
                            // Destroy existing charts if no data
                            if (quantityChartInstance) quantityChartInstance.destroy();
                            if (priceChartInstance) priceChartInstance.destroy();
                            if (m3ChartInstance) m3ChartInstance.destroy();
                            $('#quantityChart').parent().html('<canvas id="quantityChart"></canvas><p class="text-center text-muted mt-3">ไม่พบข้อมูลสำหรับกราฟจำนวนชิ้นงาน</p>');
                            $('#priceChart').parent().html('<canvas id="priceChart"></canvas><p class="text-center text-muted mt-3">ไม่พบข้อมูลสำหรับกราฟราคารวม</p>');
                            $('#m3Chart').parent().html('<canvas id="m3Chart"></canvas><p class="text-center text-muted mt-3">ไม่พบข้อมูลสำหรับกราฟปริมาตรรวม</p>');
                        }

                        // Update grand totals in tfoot
                        $('#totalQuantity1').text(grandTotalQuantity1.toLocaleString('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 0 }));
                        $('#totalM3_1').text(grandTotalM3_1.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                        $('#totalPrice1').text(grandTotalPrice1.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                        $('#totalQuantity2').text(grandTotalQuantity2.toLocaleString('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 0 }));
                        $('#totalM3_2').text(grandTotalM3_2.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                        $('#totalPrice2').text(grandTotalPrice2.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                        // Calculate total differences based on grand totals
                        $('#totalQuantityDiff').text((grandTotalQuantity2 - grandTotalQuantity1).toLocaleString('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 0 }));
                        $('#totalM3_Diff').text((grandTotalM3_2 - grandTotalM3_1).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                        $('#totalPriceDiff').text((grandTotalPrice2 - grandTotalPrice1).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                    },
                    error: function(xhr, status, error) {
                        $('#loadingSpinner').hide(); // Hide loading spinner
                        $('#summaryResults').show(); // Show results section
                        $('.chart-container').hide(); // Keep charts hidden on error

                        console.error("AJAX Error: ", status, error);
                        $('#summaryTableBody').html(`
                            <tr>
                                <td colspan="11" class="text-center text-danger">
                                    เกิดข้อผิดพลาดในการโหลดข้อมูล: ${htmlspecialchars(xhr.responseText || 'ไม่ทราบข้อผิดพลาด')}
                                </td>
                            </tr>
                        `);
                        // Reset totals on error
                        $('#totalQuantity1').text('0');
                        $('#totalM3_1').text('0.00');
                        $('#totalPrice1').text('0.00');
                        $('#totalQuantity2').text('0');
                        $('#totalM3_2').text('0.00');
                        $('#totalPrice2').text('0.00');
                        $('#totalQuantityDiff').text('0');
                        $('#totalM3_Diff').text('0.00');
                        $('#totalPriceDiff').text('0.00');
                        // Destroy existing charts on error
                        if (quantityChartInstance) quantityChartInstance.destroy();
                        if (priceChartInstance) priceChartInstance.destroy();
                        if (m3ChartInstance) m3ChartInstance.destroy();
                        $('#quantityChart').parent().html('<canvas id="quantityChart"></canvas><p class="text-center text-danger mt-3">เกิดข้อผิดพลาดในการแสดงกราฟ</p>');
                        $('#priceChart').parent().html('<canvas id="priceChart"></canvas><p class="text-center text-danger mt-3">เกิดข้อผิดพลาดในการแสดงกราฟ</p>');
                        $('#m3Chart').parent().html('<canvas id="m3Chart"></canvas><p class="text-center text-danger mt-3">เกิดข้อผิดพลาดในการแสดงกราฟ</p>');
                    }
                });
            }

            // Function to render or update charts
            function renderCharts(labels, quantity1Data, quantity2Data, m3_1Data, m3_2Data, price1Data, price2Data) {
                const ctxQuantity = document.getElementById('quantityChart').getContext('2d');
                const ctxPrice = document.getElementById('priceChart').getContext('2d');
                const ctxM3 = document.getElementById('m3Chart').getContext('2d'); // Get context for M3 chart

                // Destroy existing chart instances if they exist
                if (quantityChartInstance) {
                    quantityChartInstance.destroy();
                }
                if (priceChartInstance) {
                    priceChartInstance.destroy();
                }
                if (m3ChartInstance) { // Destroy M3 chart
                    m3ChartInstance.destroy();
                }

                // Quantity Chart
                quantityChartInstance = new Chart(ctxQuantity, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: $('#period1Header').text().replace('ช่วงที่ 1 ', ''), // Dynamic label from header
                            data: quantity1Data,
                            backgroundColor: 'rgba(0, 123, 255, 0.7)', // Blue
                            borderColor: 'rgba(0, 123, 255, 1)',
                            borderWidth: 1
                        }, {
                            label: $('#period2Header').text().replace('ช่วงที่ 2 ', ''), // Dynamic label from header
                            data: quantity2Data,
                            backgroundColor: 'rgba(40, 167, 69, 0.7)', // Green
                            borderColor: 'rgba(40, 167, 69, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'จำนวนชิ้นงานผลิตสำเร็จรายจุดประกอบ'
                            }
                        },
                        scales: {
                            x: {
                                title: {
                                    display: true,
                                    text: 'จุดประกอบ'
                                }
                            },
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'จำนวน (ตัว)'
                                }
                            }
                        }
                    }
                });

                // Price Chart
                priceChartInstance = new Chart(ctxPrice, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: $('#period1Header').text().replace('ช่วงที่ 1 ', ''), // Dynamic label from header
                            data: price1Data,
                            backgroundColor: 'rgba(255, 193, 7, 0.7)', // Yellow/Orange
                            borderColor: 'rgba(255, 193, 7, 1)',
                            borderWidth: 1
                        }, {
                            label: $('#period2Header').text().replace('ช่วงที่ 2 ', ''), // Dynamic label from header
                            data: price2Data,
                            backgroundColor: 'rgba(220, 53, 69, 0.7)', // Red
                            borderColor: 'rgba(220, 53, 69, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'ราคารวมค่าแรงผลิตรายจุดประกอบ'
                            }
                        },
                        scales: {
                            x: {
                                title: {
                                    display: true,
                                    text: 'จุดประกอบ'
                                }
                            },
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'ราคารวม (บาท)'
                                }
                            }
                        }
                    }
                });

                // M3 Chart (New Chart)
                m3ChartInstance = new Chart(ctxM3, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: $('#period1Header').text().replace('ช่วงที่ 1 ', ''), // Dynamic label from header
                            data: m3_1Data,
                            backgroundColor: 'rgba(108, 117, 125, 0.7)', // Gray
                            borderColor: 'rgba(108, 117, 125, 1)',
                            borderWidth: 1
                        }, {
                            label: $('#period2Header').text().replace('ช่วงที่ 2 ', ''), // Dynamic label from header
                            data: m3_2Data,
                            backgroundColor: 'rgba(23, 162, 184, 0.7)', // Teal
                            borderColor: 'rgba(23, 162, 184, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'ปริมาตรรวม (ลบ.ม.) ของไม้ที่เบิกใช้'
                            }
                        },
                        scales: {
                            x: {
                                title: {
                                    display: true,
                                    text: 'จุดประกอบ'
                                }
                            },
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'ปริมาตร (ลบ.ม.)'
                                }
                            }
                        }
                    }
                });
            }


            // Trigger data fetch when filter button is clicked
            $('#filterBtn').on('click', fetchSummaryData);

            // Initial data fetch when page loads
            fetchSummaryData(); // Fetch initial data on page load

            // Function to sanitize HTML output (XSS prevention)
            function htmlspecialchars(str) {
                let map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return str.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
            }

            // Export to PDF functionality (Removed as per user request)
            // $('#exportPdfBtn').on('click', function() {
            //     let dateRange1 = $('#dateRangePicker1').val();
            //     let dates1 = dateRange1.split(' - ');
            //     let startDate1 = dates1[0];
            //     let endDate1 = dates1[1];

            //     let dateRange2 = $('#dateRangePicker2').val();
            //     let dates2 = dateRange2.split(' - ');
            //     let startDate2 = dates2[0];
            //     let endDate2 = dates2[1];

            //     // Open the PDF generation script in a new tab with both date ranges
            //     window.open(`generate_production_wages_summary_pdf.php?startDate1=${startDate1}&endDate1=${endDate1}&startDate2=${startDate2}&endDate2=${endDate2}`, '_blank');
            // });

        });
    </script>
</body>
</html>
