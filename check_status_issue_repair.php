<?php
// เริ่มต้น session และเชื่อมต่อฐานข้อมูล
session_start();
date_default_timezone_set('Asia/Bangkok'); // ตั้งค่าเวลาประเทศไทย
include 'config_db.php'; // เชื่อมต่อไฟล์ฐานข้อมูล

// // ตรวจสอบว่ามีการค้นหาหรือไม่
// if (isset($_GET['search_job_code']) || isset($_GET['search_status']) || isset($_GET['search_date_created'])) {
//     $limit = 1000000; // ไม่จำกัดจำนวนเมื่อมีการค้นหา
// } else {
//     $limit = 30; // จำนวนรายการต่อหน้า
// }

$limit = 100; // จำนวนรายการต่อหน้า

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// ตรวจสอบการกรองข้อมูลจากฟอร์ม
$whereClauses = [];
if (!empty($_GET['search_job_code'])) {
    $whereClauses[] = "repair_id LIKE '%" . $conn->real_escape_string($_GET['search_job_code']) . "%'";
}
if (!empty($_GET['search_status'])) {
    $whereClauses[] = "status = '" . $conn->real_escape_string($_GET['search_status']) . "'"; 
}

if (!empty($_GET['search_customer'])) {
    $searchCustomer = $conn->real_escape_string($_GET['search_customer']);
    $whereClauses[] = "(customer.customer_name LIKE '%$searchCustomer%' OR customer.customer_short_name LIKE '%$searchCustomer%')";
}

// ตรวจสอบการกรองข้อมูลจากช่อง prod_code
if (!empty($_GET['search_prod_code'])) {
    $searchProdCode = $conn->real_escape_string($_GET['search_prod_code']);
    $whereClauses[] = "prod_list.prod_code LIKE '%$searchProdCode%'";
}

// ตรวจสอบการกรองข้อมูลจากช่อง prod_prod_partno
if (!empty($_GET['search_prod_partno'])) {
    $searchProdCode = $conn->real_escape_string($_GET['search_prod_partno']);
    $whereClauses[] = "prod_list.prod_partno LIKE '%$searchProdCode%'";
}

// กรองตามวันที่สร้าง (created_at)
if (!empty($_GET['search_date_created'])) {
    $dateRange = explode(' - ', $_GET['search_date_created']);
    $whereClauses[] = "DATE(created_at) BETWEEN '" . $conn->real_escape_string($dateRange[0]) . "' AND '" . $conn->real_escape_string($dateRange[1]) . "'";
}

// สร้างคำสั่ง SQL
$countSql = "SELECT COUNT(*) as total 
    FROM repair_issue
    LEFT JOIN wood_issue ON repair_issue.job_id = wood_issue.job_id
    LEFT JOIN prod_list ON wood_issue.prod_id = prod_list.prod_id
    LEFT JOIN customer ON prod_list.customer_id = customer.customer_id";

// หากมีเงื่อนไขการค้นหา
if (count($whereClauses) > 0) {
    $countSql .= " WHERE " . implode(' AND ', $whereClauses);
}

$countResult = $conn->query($countSql);
$totalRows = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

$sql = "SELECT repair_issue.*, wood_issue.prod_id, prod_list.prod_code, prod_list.prod_partno, customer.customer_name
        FROM repair_issue
        LEFT JOIN wood_issue ON repair_issue.job_id = wood_issue.job_id
        LEFT JOIN prod_list ON wood_issue.prod_id = prod_list.prod_id
        LEFT JOIN customer ON prod_list.customer_id = customer.customer_id";

if (count($whereClauses) > 0) {
    $sql .= " WHERE " . implode(' AND ', $whereClauses);
}

$sql .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";



// ดึงข้อมูลจากฐานข้อมูล
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดตามงานเบิกซ่อม</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> <!-- เชื่อมโยง jQuery -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
</head>
<body>

<!-- Navbar -->
<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <h1>ติดตามงานเบิกซ่อม</h1>

    <!-- ฟอร์มค้นหาข้อมูล -->
    <form method="GET" action="">
        <div class="row mb-3">
            <div class="col-md-3">
                <label for="search_job_code" class="form-label">หมายเลข JOB</label>
                <input type="text" class="form-control" name="search_job_code" id="search_job_code" value="<?php echo isset($_GET['search_job_code']) ? $_GET['search_job_code'] : ''; ?>">
            </div>
            <div class="col-md-3">
                <label for="search_status" class="form-label">สถานะ</label>
                <select class="form-select" name="search_status" id="search_status">
                    <option value="" selected>-- เลือกสถานะ --</option>
                    <option value="สั่งไม้" <?php echo isset($_GET['search_status']) && $_GET['search_status'] === 'สั่งไม้' ? 'selected' : ''; ?>>สั่งไม้</option>
                    <option value="กำลังเตรียมไม้" <?php echo isset($_GET['search_status']) && $_GET['search_status'] === 'กำลังเตรียมไม้' ? 'selected' : ''; ?>>กำลังเตรียมไม้</option>
                    <option value="รอเบิก" <?php echo isset($_GET['search_status']) && $_GET['search_status'] === 'รอเบิก' ? 'selected' : ''; ?>>รอเบิก</option>
                    <option value="เบิกแล้ว" <?php echo isset($_GET['search_status']) && $_GET['search_status'] === 'เบิกแล้ว' ? 'selected' : ''; ?>>เบิกแล้ว</option>
                    <option value="ยกเลิก" <?php echo isset($_GET['search_status']) && $_GET['search_status'] === 'ยกเลิก' ? 'selected' : ''; ?>>ยกเลิก</option>
                    <option value="ปิดสำเร็จ" <?php echo isset($_GET['search_status']) && $_GET['search_status'] === 'ปิดสำเร็จ' ? 'selected' : ''; ?>>ปิดสำเร็จ</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="search_customer" class="form-label">ชื่อลูกค้า</label>
                <input type="text" class="form-control" name="search_customer" id="search_customer" value="<?php echo isset($_GET['search_customer']) ? $_GET['search_customer'] : ''; ?>">
            </div>

            <div class="col-md-3">
                <label for="search_prod_code" class="form-label">ITEM CODE FG</label>
                <input type="text" class="form-control" name="search_prod_code" id="search_prod_code" value="<?php echo isset($_GET['search_prod_code']) ? $_GET['search_prod_code'] : ''; ?>">
            </div>

            <div class="col-md-3">
                <label for="search_prod_partno" class="form-label">PARTNO.</label>
                <input type="text" class="form-control" name="search_prod_partno" id="search_prod_partno" value="<?php echo isset($_GET['search_prod_partno']) ? $_GET['search_prod_partno'] : ''; ?>">
            </div>

            <!-- ช่องเลือกวันที่ created_at -->
            <div class="col-md-3">
                <label for="search_date_created" class="form-label">วันที่ออกเอกสาร</label>
                <input type="text" class="form-control" name="search_date_created" id="search_date_created" value="<?php echo isset($_GET['search_date_created']) ? $_GET['search_date_created'] : ''; ?>" />
            </div>
        </div>
        <button type="submit" class="btn btn-primary">ค้นหา</button>
        <a href="check_status_issue_repair.php" class="btn btn-secondary">รีเซ็ต</a>
    </form>


    <br>

    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>หมายเลขงานซ่อม</th>
                <th>ITEM CODE FG</th>
                <th>PARTNO.</th>
                <th>วันที่ออกเอกสาร</th>
                <th>วันที่เบิก</th>
                <th>สถานะ</th>
                <th>ดำเนินการ</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()) {?>
                <tr>
                    <td><?php echo $row['repair_id']; ?></td>
                    <td><?php echo $row['prod_code']; ?></td>
                    <td><?php echo $row['prod_partno']; ?></td>
                    <td><?php echo $row['created_at']; ?></td>
                    <td><?php echo $row['issue_date']; ?></td>
                    <td><?php echo $row['status']; ?></td>
                    <td>
                        <a href="generate_repair_issue_pdf.php?repair_id=<?php echo $row['repair_id']; ?>" class="btn btn-info btn-sm" target="_blank">PDF</a>
                    </td>
                </tr>
            <?php }?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<nav>
        <ul class="pagination justify-content-center">
            <!-- ปุ่ม Previous -->
            <li class="page-item <?php if($page == 1) echo 'disabled'; ?>">
                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
            <?php 
            if ($totalPages <= 5) {
                // ถ้าจำนวนหน้าน้อยกว่าหรือเท่ากับ 5 แสดงทั้งหมด
                for ($i = 1; $i <= $totalPages; $i++) { ?>
                    <li class="page-item <?php if($page == $i) echo 'active'; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php }
            } else {
                // แสดง 5 หน้าแรก
                for ($i = 1; $i <= 5; $i++) { ?>
                    <li class="page-item <?php if($page == $i) echo 'active'; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php } ?>
                <!-- แสดง Ellipsis -->
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
                <!-- แสดงหน้าสุดท้าย -->
                <li class="page-item <?php if($page == $totalPages) echo 'active'; ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>">
                        <?php echo $totalPages; ?>
                    </a>
                </li>
            <?php } ?>
            <!-- ปุ่ม Next -->
            <li class="page-item <?php if($page == $totalPages) echo 'disabled'; ?>">
                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        </ul>
    </nav>

<script>
    // ใช้งาน daterangepicker สำหรับช่องวันที่
    $('#search_date_created').daterangepicker({
        locale: { format: 'YYYY-MM-DD' },
        opens: 'left',
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

</body>
</html>
