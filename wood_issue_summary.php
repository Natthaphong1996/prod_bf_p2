<?php
session_start();
date_default_timezone_set('Asia/Bangkok');
include('config_db.php');
// Include ฟังก์ชันที่เกี่ยวกับข้อมูลสินค้าและ BOM
require_once 'product_functions.php';

// Include ฟังก์ชันที่เกี่ยวกับการจัดการ wood issue
require_once 'wood_issue_functions.php';

// Include ฟังก์ชันที่เกี่ยวกับงานเสร็จ
require_once 'job_complete_functions.php';

// Include ฟังก์ชันที่เกี่ยวกับการจัดการราคาสินค้าและประวัติการแก้ไขราคา
require_once 'product_price_functions.php';
include('modals.php'); // include ไฟล์ modals.php ที่มีฟังก์ชัน displayJobDetailModal() และ displaySummaryModal()

// รับค่าจาก GET เพื่อให้ค่าค้นหายังคงอยู่เมื่อเปลี่ยนหน้า
$job_id_search = isset($_GET['job_id']) ? $_GET['job_id'] : '';
$prod_code_search = isset($_GET['prod_code']) ? $_GET['prod_code'] : '';
$cus_name_search = isset($_GET['customer_name']) ? $_GET['customer_name'] : '';
$prod_partno_search = isset($_GET['prod_partno']) ? $_GET['prod_partno'] : '';
$want_receive_start = isset($_GET['want_receive_start']) && $_GET['want_receive_start'] !== '' ? $_GET['want_receive_start'] : date('Y-m-01');
$want_receive_end = isset($_GET['want_receive_end']) && $_GET['want_receive_end'] !== '' ? $_GET['want_receive_end'] : date('Y-m-d');
$issue_status_search = isset($_GET['issue_status']) ? $_GET['issue_status'] : '';
$search_job_type_code = isset($_GET['search_job_type_code']) ? $_GET['search_job_type_code'] : '';

$limit = 50;
$current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$start = ($current_page - 1) * $limit;

// สร้างตัวแปรสำหรับเก็บ query string ของการค้นหา (ยกเว้น page)
$search_query = "";
foreach ($_GET as $key => $value) {
    if ($key != "page" && $value != "") {
        $search_query .= "&" . urlencode($key) . "=" . urlencode($value);
    }
}

// สร้าง SQL สำหรับดึงข้อมูลรายการ (มี LIMIT)
$sql = "SELECT wi.*, pl.prod_partno, c.customer_name, c.customer_short_name
        FROM wood_issue wi
        LEFT JOIN prod_list pl ON wi.prod_id = pl.prod_id
        LEFT JOIN customer c ON pl.customer_id = c.customer_id
        WHERE 1";
if ($job_id_search != '') {
    $sql .= " AND wi.job_id LIKE '%$job_id_search%'";
}
if ($cus_name_search != '') {
    $sql .= " AND c.customer_name LIKE '%$cus_name_search%'";
}
if ($prod_code_search != '') {
    $sql .= " AND wi.product_code LIKE '%$prod_code_search%'";
}
if ($prod_partno_search != '') {
    $sql .= " AND pl.prod_partno LIKE '%$prod_partno_search%'";
}
if ($want_receive_start != '' && $want_receive_end != '') {
    $sql .= " AND wi.want_receive BETWEEN STR_TO_DATE('$want_receive_start', '%Y-%m-%d')
              AND STR_TO_DATE('$want_receive_end', '%Y-%m-%d')";
}
if ($issue_status_search != '') {
    $sql .= " AND wi.issue_status = '$issue_status_search'";
}
if ($search_job_type_code != '') {
    $sql .= " AND wi.job_type = '$search_job_type_code'";
}
$sql .= " LIMIT $start, $limit";

$result = $conn->query($sql);

// นับจำนวนข้อมูลทั้งหมดสำหรับ Pagination
$total_sql = "SELECT COUNT(*) 
              FROM wood_issue wi
              LEFT JOIN prod_list pl ON wi.prod_id = pl.prod_id
              LEFT JOIN customer c ON pl.customer_id = c.customer_id
              WHERE 1";
if ($job_id_search != '') {
    $total_sql .= " AND wi.job_id LIKE '%$job_id_search%'";
}
if ($cus_name_search != '') {
    $total_sql .= " AND c.customer_name LIKE '%$cus_name_search%'";
}
if ($prod_code_search != '') {
    $total_sql .= " AND wi.product_code LIKE '%$prod_code_search%'";
}
if ($prod_partno_search != '') {
    $total_sql .= " AND pl.prod_partno LIKE '%$prod_partno_search%'";
}
if ($want_receive_start != '' && $want_receive_end != '') {
    $total_sql .= " AND wi.want_receive BETWEEN STR_TO_DATE('$want_receive_start', '%Y-%m-%d')
              AND STR_TO_DATE('$want_receive_end', '%Y-%m-%d')";
}
if ($issue_status_search != '') {
    $total_sql .= " AND wi.issue_status = '$issue_status_search'";
}
if ($search_job_type_code != '') {
    $total_sql .= " AND wi.job_type = '$search_job_type_code'";
}
$total_result = $conn->query($total_sql);
$total_row = $total_result->fetch_row();
$total_records = $total_row[0];
$total_pages = ceil($total_records / $limit);

// สร้าง SQL สำหรับคำนวณผลรวม (Summary) โดยไม่จำกัดจำนวนรายการ
if ($issue_status_search == "") {
    // ถ้า $issue_status_search เป็นค่าว่าง จะไม่เอาข้อมูลที่ wi.issue_status เป็น 'ยกเลิก'
    $summary_sql = "SELECT wi.*, c.*
                    FROM wood_issue wi
                    LEFT JOIN prod_list pl ON wi.prod_id = pl.prod_id
                    LEFT JOIN customer c ON pl.customer_id = c.customer_id
                    WHERE 1
                    AND wi.issue_status <> 'ยกเลิก'";
    if ($job_id_search != '') {
        $summary_sql .= " AND wi.job_id LIKE '%$job_id_search%'";
    }
    if ($cus_name_search != '') {
        $summary_sql .= " AND c.customer_name LIKE '%$cus_name_search%'";
    }
    if ($prod_code_search != '') {
        $summary_sql .= " AND wi.product_code LIKE '%$prod_code_search%'";
    }
    if ($prod_partno_search != '') {
        $summary_sql .= " AND pl.prod_partno LIKE '%$prod_partno_search%'";
    }
    if ($want_receive_start != '' && $want_receive_end != '') {
        $summary_sql .= " AND wi.want_receive BETWEEN STR_TO_DATE('$want_receive_start', '%Y-%m-%d')
                  AND STR_TO_DATE('$want_receive_end', '%Y-%m-%d')";
    }
    if ($search_job_type_code != '') {
        $summary_sql .= " AND wi.job_type = '$search_job_type_code'";
    }
} else {
    // ถ้า $issue_status_search ไม่ว่าง จะใช้ค่าในตัวแปรค้นหา
    $summary_sql = "SELECT wi.*, c.*
                    FROM wood_issue wi
                    LEFT JOIN prod_list pl ON wi.prod_id = pl.prod_id
                    LEFT JOIN customer c ON pl.customer_id = c.customer_id
                    WHERE 1";
    if ($job_id_search != '') {
        $summary_sql .= " AND wi.job_id LIKE '%$job_id_search%'";
    }
    if ($cus_name_search != '') {
        $summary_sql .= " AND c.customer_name LIKE '%$cus_name_search%'";
    }
    if ($prod_code_search != '') {
        $summary_sql .= " AND wi.product_code LIKE '%$prod_code_search%'";
    }
    if ($prod_partno_search != '') {
        $summary_sql .= " AND pl.prod_partno LIKE '%$prod_partno_search%'";
    }
    if ($want_receive_start != '' && $want_receive_end != '') {
        $summary_sql .= " AND wi.want_receive BETWEEN STR_TO_DATE('$want_receive_start', '%Y-%m-%d')
                  AND STR_TO_DATE('$want_receive_end', '%Y-%m-%d')";
    }
    if ($issue_status_search != '') {
        $summary_sql .= " AND wi.issue_status = '$issue_status_search'";
    }
    if ($search_job_type_code != '') {
        $summary_sql .= " AND wi.job_type = '$search_job_type_code'";
    }
}


$summary_result = $conn->query($summary_sql);

$sum_main = 0;
$sum_repair = 0;
$sum_return = 0;

while ($row = $summary_result->fetch_assoc()) {
    $prod_id = $row['prod_id'];
    $quantity = $row['quantity'];
    $job_id = $row['job_id'];
    $main = getWoodIssueMainM3($prod_id, $quantity, $conn);
    $repair = getWoodIssuesRepairM3($job_id, $conn);
    $return = getReturnWoodM3($job_id, $conn);

    $sum_main += $main;
    $sum_repair += $repair;
    $sum_return += $return;
}

// คำนวณปริมาณไม้ที่ใช้จริงรวมใน Summary
$sum_actual = $sum_main + $sum_repair - $sum_return;
// คำนวณความสูญเสียรวมโดยเทียบกับแผนที่วางไว้ (sum_main)
$sum_loss = $sum_actual - $sum_main;


// สร้างข้อมูลสรุปตามลูกค้า (Customer Summary)
$customer_summary = array();


if ($issue_status_search == "") {
    // ถ้า $issue_status_search เป็นค่าว่าง จะไม่เอาข้อมูลที่ wi.issue_status เป็น 'ยกเลิก'
    $customer_sql = "SELECT wi.*, c.*
                    FROM wood_issue wi
                    LEFT JOIN prod_list pl ON wi.prod_id = pl.prod_id
                    LEFT JOIN customer c ON pl.customer_id = c.customer_id
                    WHERE 1
                    AND wi.issue_status <> 'ยกเลิก'";
    if ($job_id_search != '') {
        $customer_sql .= " AND wi.job_id LIKE '%$job_id_search%'";
    }
    if ($cus_name_search != '') {
        $customer_sql .= " AND c.customer_name LIKE '%$cus_name_search%'";
    }
    if ($prod_code_search != '') {
        $customer_sql .= " AND wi.product_code LIKE '%$prod_code_search%'";
    }
    if ($prod_partno_search != '') {
        $customer_sql .= " AND pl.prod_partno LIKE '%$prod_partno_search%'";
    }
    if ($want_receive_start != '' && $want_receive_end != '') {
        $customer_sql .= " AND wi.want_receive BETWEEN STR_TO_DATE('$want_receive_start', '%Y-%m-%d')
                  AND STR_TO_DATE('$want_receive_end', '%Y-%m-%d')";
    }
    if ($search_job_type_code != '') {
        $customer_sql .= " AND wi.job_type = '$search_job_type_code'";
    }
} else {
    // ถ้า $issue_status_search ไม่ว่าง จะใช้ค่าในตัวแปรค้นหา
    $customer_sql = "SELECT wi.*, c.*
                    FROM wood_issue wi
                    LEFT JOIN prod_list pl ON wi.prod_id = pl.prod_id
                    LEFT JOIN customer c ON pl.customer_id = c.customer_id
                    WHERE 1";


    if ($job_id_search != '') {
        $customer_sql .= " AND wi.job_id LIKE '%$job_id_search%'";
    }
    if ($cus_name_search != '') {
        $customer_sql .= " AND c.customer_name LIKE '%$cus_name_search%'";
    }
    if ($prod_code_search != '') {
        $customer_sql .= " AND wi.product_code LIKE '%$prod_code_search%'";
    }
    if ($prod_partno_search != '') {
        $customer_sql .= " AND pl.prod_partno LIKE '%$prod_partno_search%'";
    }
    if ($want_receive_start != '' && $want_receive_end != '') {
        $customer_sql .= " AND wi.want_receive BETWEEN STR_TO_DATE('$want_receive_start', '%Y-%m-%d')
              AND STR_TO_DATE('$want_receive_end', '%Y-%m-%d')";
    }
    if ($issue_status_search != '') {
        $customer_sql .= " AND wi.issue_status = '$issue_status_search'";
    }
    if ($search_job_type_code != '') {
        $customer_sql .= " AND wi.job_type = '$search_job_type_code'";
    }
}



$customer_result = $conn->query($customer_sql);

while ($row = $customer_result->fetch_assoc()) {
    $customer = $row['customer_name'];
    if (!isset($customer_summary[$customer])) {
        $customer_summary[$customer] = array(
            'job_count' => 0,
            'sum_main' => 0,
            'sum_repair' => 0,
            'sum_return' => 0
        );
    }
    $job_id = $row['job_id'];
    $prod_id = $row['prod_id'];
    $quantity = $row['quantity'];
    $main = getWoodIssueMainM3($prod_id, $quantity, $conn);
    $repair = getWoodIssuesRepairM3($job_id, $conn);
    $return = getReturnWoodM3($job_id, $conn);

    $customer_summary[$customer]['job_count'] += 1;
    $customer_summary[$customer]['sum_main'] += $main;
    $customer_summary[$customer]['sum_repair'] += $repair;
    $customer_summary[$customer]['sum_return'] += $return;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wood Issue Summary</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
</head>

<body>
    <?php include 'navbar.php'; ?>
    <div class="container-fluid mt-5">
        <h1 class="text-center mb-5">Wood Issue Summary</h1>
        <!-- ฟอร์มค้นหา -->
        <form method="GET" class="mb-3">
            <!-- แถวแรก -->
            <div class="row">
                <div class="col-md-3 mb-3">
                    <input type="text" name="job_id" class="form-control" placeholder="JOB ID"
                        value="<?php echo htmlspecialchars($job_id_search); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <input type="text" name="customer_name" class="form-control" placeholder="CUSTOMER NAME"
                        value="<?php echo htmlspecialchars($cus_name_search); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <input type="text" name="prod_code" class="form-control" placeholder="ITEM CODE FG"
                        value="<?php echo htmlspecialchars($prod_code_search); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <input type="text" name="prod_partno" class="form-control" placeholder="PART NO."
                        value="<?php echo htmlspecialchars($prod_partno_search); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <!-- Field สำหรับเลือกช่วงวันที่ -->
                    <input type="text" name="daterange" id="daterange" class="form-control"
                        placeholder="เลือกช่วงวันที่"
                        value="<?php echo ($want_receive_start && $want_receive_end) ? $want_receive_start . ' - ' . $want_receive_end : ''; ?>">
                </div>
                <div class="col-md-2 mb-3">
                    <select class="form-select" name="search_job_type_code" id="search_job_type_code">
                        <option value="">-- เลือกประเภทงาน --</option>
                        <option value="งานหมวด A" <?php echo ($search_job_type_code == 'งานหมวด A') ? 'selected' : ''; ?>>
                            งานหมวด A</option>
                        <option value="งานทั่วไป" <?php echo ($search_job_type_code == 'งานทั่วไป') ? 'selected' : ''; ?>>
                            งานทั่วไป</option>
                        <option value="งานไม้ PACK" <?php echo ($search_job_type_code == 'งานไม้ PACK') ? 'selected' : ''; ?>>งานไม้ PACK</option>
                        <option value="PALLET RETURN" <?php echo ($search_job_type_code == 'PALLET RETURN') ? 'selected' : ''; ?>>PALLET RETURN</option>
                        <option value="งานเคลม" <?php echo ($search_job_type_code == 'งานเคลม') ? 'selected' : ''; ?>>
                            งานเคลม</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <select name="issue_status" class="form-select">
                        <option value="">เลือกสถานะ</option>
                        <option value="สั่งไม้" <?php echo ($issue_status_search == 'สั่งไม้') ? 'selected' : ''; ?>>
                            สั่งไม้</option>
                        <option value="กำลังเตรียมไม้" <?php echo ($issue_status_search == 'กำลังเตรียมไม้') ? 'selected' : ''; ?>>กำลังเตรียมไม้</option>
                        <option value="รอเบิก" <?php echo ($issue_status_search == 'รอเบิก') ? 'selected' : ''; ?>>รอเบิก
                        </option>
                        <option value="เบิกแล้ว" <?php echo ($issue_status_search == 'เบิกแล้ว') ? 'selected' : ''; ?>>
                            เบิกแล้ว</option>
                        <option value="ยกเลิก" <?php echo ($issue_status_search == 'ยกเลิก') ? 'selected' : ''; ?>>ยกเลิก
                        </option>
                        <option value="ปิดสำเร็จ" <?php echo ($issue_status_search == 'ปิดสำเร็จ') ? 'selected' : ''; ?>>
                            ปิดสำเร็จ</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <button type="submit" class="btn btn-primary">ค้นหา</button>
                    <a href="wood_issue_summary.php" class="btn btn-secondary">รีเซ็ต</a>
                    <!-- ปุ่ม สรุป อยู่ข้างๆ ปุ่ม รีเซ็ต -->
                    <button type="button" class="btn btn-success" data-bs-toggle="modal"
                        data-bs-target="#summaryModal">สรุป</button>
                </div>
            </div>
            <!-- Hidden fields สำหรับเก็บวันที่เริ่มต้นและสิ้นสุด -->
            <input type="hidden" name="want_receive_start" id="want_receive_start"
                value="<?php echo htmlspecialchars($want_receive_start); ?>">
            <input type="hidden" name="want_receive_end" id="want_receive_end"
                value="<?php echo htmlspecialchars($want_receive_end); ?>">


        </form>

        <!-- ตารางแสดงข้อมูล -->
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>JOB ID</th>
                        <th>CUSTOMER</th>
                        <th>PRODCODE/PARTNO.</th>
                        <th>สั่งผลิต/ตรวจรับ</th>
                        <th>วันที่ต้องการรับ</th>
                        <th>ปริมาณไม้ที่ใช้ไป</th>
                        <th>สถานะ</th>
                        <th>ดำเนินการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // ตัวแปรสำหรับเก็บ HTML ของ Modal รายละเอียดแต่ละแถว
                    $modals = "";
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $issue_id = $row['issue_id'];
                            $job_id = $row['job_id'];
                            $customer_name = $row['customer_name'];
                            $prod_id = $row['prod_id'];
                            $quantity = $row['quantity'];
                            $want_receive = date('Y-m-d', strtotime($row['want_receive']));
                            $issue_status = $row['issue_status'];
                            $prod_partno = $row['prod_partno'];
                            // ดึงข้อมูลเพิ่มเติม เช่น prod_code, prod_complete_qty
                            $prod_data = getProdInfo($prod_id, $conn);
                            $prod_code = $prod_data ? $prod_data['prod_code'] : "ยังไม่มีข้อมูล";
                            $job_complete_data = getJobCompleteInfo($job_id, $conn);
                            $prod_complete_qty = $job_complete_data ? $job_complete_data['prod_complete_qty'] : 0;

                            $wood_issues_actual_m3 = getWoodIssueMainM3($prod_id, $quantity, $conn);
                            $wood_issues_repair_m3 = getWoodIssuesRepairM3($job_id, $conn);
                            $total_issues_wood_m3 = $wood_issues_actual_m3 + $wood_issues_repair_m3;
                            $wood_return_m3 = getReturnWoodM3($job_id, $conn);
                            $wood_issues_actual_m3 = number_format($total_issues_wood_m3 - $wood_return_m3, 4);

                            echo "<tr>
                                <td>$job_id</td>
                                <td>$customer_name</td>
                                <td>$prod_code / $prod_partno</td>
                                <td>$prod_complete_qty / $quantity</td>
                                <td>$want_receive</td>
                                <td>$wood_issues_actual_m3</td>
                                <td>$issue_status</td>
                                <td>
                                    <a href='generate_issued_pdf.php?issue_id=$issue_id' class='btn btn-info btn-sm' target='_blank'>PDF</a>
                                    <button type='button' class='btn btn-primary btn-sm' data-bs-toggle='modal' data-bs-target='#detailModal$job_id'>รายละเอียด</button>
                                </td>
                            </tr>";

                            // เก็บ HTML ของ Modal รายละเอียดแต่ละแถวด้วย ob buffering
                            ob_start();
                            displayJobDetailModal($job_id, $prod_id, $quantity, $conn);
                            $modals .= ob_get_clean();
                        }
                    } else {
                        echo "<tr><td colspan='7' class='text-center'>ไม่พบข้อมูล</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- แสดง Modal รายละเอียดของแต่ละแถว -->
        <?php echo $modals; ?>

        <!-- เรียกใช้งาน Modal สรุปผลรวม พร้อมตารางสรุปลูกค้า (จากไฟล์ modals.php) -->
        <?php displaySummaryModal($sum_main, $sum_repair, $sum_return, $sum_loss, $customer_summary); ?>

        <!-- Pagination -->
        <div class="pagination justify-content-center">
            <nav aria-label="Page navigation">
                <ul class="pagination">
                    <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo ($current_page - 1) . $search_query; ?>"
                            aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    <?php
                    if ($total_pages <= 5) {
                        for ($i = 1; $i <= $total_pages; $i++) { ?>
                            <li class="page-item <?php echo ($current_page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i . $search_query; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php }
                    } else {
                        for ($i = 1; $i <= 5; $i++) { ?>
                            <li class="page-item <?php echo ($current_page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i . $search_query; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php } ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                        <li class="page-item <?php echo ($current_page == $total_pages) ? 'active' : ''; ?>">
                            <a class="page-link"
                                href="?page=<?php echo $total_pages . $search_query; ?>"><?php echo $total_pages; ?></a>
                        </li>
                    <?php } ?>
                    <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo ($current_page + 1) . $search_query; ?>"
                            aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>

    <script>
        // กำหนด daterangepicker สำหรับช่วงวันที่ โดยตั้งค่าเริ่มต้นจากค่าที่ส่งเข้ามา (หรือใช้วันที่ปัจจุบัน)
        $(function () {
            var initialStart = "<?php echo $want_receive_start; ?>";
            var initialEnd = "<?php echo $want_receive_end; ?>";
            $('#daterange').daterangepicker({
                locale: { format: 'YYYY-MM-DD' },
                opens: 'left',
                startDate: initialStart,
                endDate: initialEnd
            }, function (start, end, label) {
                $('#want_receive_start').val(start.format('YYYY-MM-DD'));
                $('#want_receive_end').val(end.format('YYYY-MM-DD'));
            });
        });
    </script>
</body>

</html>

<?php
$conn->close();
?>