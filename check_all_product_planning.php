<?php
// เริ่มต้น session และเชื่อมต่อฐานข้อมูล
session_start();
date_default_timezone_set('Asia/Bangkok'); // ตั้งค่าเวลาประเทศไทย
include 'config_db.php'; // เชื่อมต่อไฟล์ฐานข้อมูล

// การตั้งค่าหน้า Pagination
// ถ้ามีการค้นหา ให้ตั้งค่า limit เป็น 10000
//$limit = isset($_GET['search_job_code']) || isset($_GET['search_customer']) || isset($_GET['search_prod_code']) || isset($_GET['search_prod_partno']) || isset($_GET['search_status']) ? 10000 : 10;
$limit = 100;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// ตรวจสอบการกรองข้อมูลจากฟอร์ม
$whereClauses = [];
if (!empty($_GET['search_job_code'])) {
    $whereClauses[] = "wi.job_id LIKE '%" . $conn->real_escape_string($_GET['search_job_code']) . "%'";
}
if (!empty($_GET['search_job_type'])) {
    $whereClauses[] = "wi.job_type LIKE '%" . $conn->real_escape_string($_GET['search_job_type']) . "%'";
}
if (!empty($_GET['search_customer'])) {
    $whereClauses[] = "(c.customer_name LIKE '%" . $conn->real_escape_string($_GET['search_customer']) . "%' OR c.customer_short_name LIKE '%" . $conn->real_escape_string($_GET['search_customer']) . "%')";
}
if (!empty($_GET['search_prod_code'])) {
    $whereClauses[] = "pl.prod_code LIKE '%" . $conn->real_escape_string($_GET['search_prod_code']) . "%'";
}
if (!empty($_GET['search_prod_partno'])) {
    $whereClauses[] = "pl.prod_partno LIKE '%" . $conn->real_escape_string($_GET['search_prod_partno']) . "%'";
}
if (!empty($_GET['search_status'])) {
    $whereClauses[] = "wi.issue_status = '" . $conn->real_escape_string($_GET['search_status']) . "'";
}
// กรองตามวันที่สร้าง (created_at)
if (!empty($_GET['search_date_created'])) {
    $dateRange = explode(' - ', $_GET['search_date_created']);
    $whereClauses[] = "DATE(want_receive) BETWEEN '" . $conn->real_escape_string($dateRange[0]) . "' AND '" . $conn->real_escape_string($dateRange[1]) . "'";
}

// สร้างคำสั่ง SQL นับจำนวนทั้งหมด
$countSql = "SELECT COUNT(*) as total 
             FROM wood_issue wi
             LEFT JOIN prod_list pl ON wi.product_code = pl.prod_code
             LEFT JOIN customer c ON pl.customer_id = c.customer_id";
if (count($whereClauses) > 0) {
    $countSql .= " WHERE " . implode(' AND ', $whereClauses);
}

$countResult = $conn->query($countSql);
$totalRows = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

// สร้างคำสั่ง SQL ดึงข้อมูล
$sql = "SELECT wi.*, pl.prod_partno,pl.prod_code, c.customer_name, c.customer_short_name
        FROM wood_issue wi
        LEFT JOIN prod_list pl ON pl.prod_id = wi.prod_id  
        LEFT JOIN customer c ON pl.customer_id = c.customer_id";
if (count($whereClauses) > 0) {
    $sql .= " WHERE " . implode(' AND ', $whereClauses);
}
$sql .= " ORDER BY wi.creation_date DESC LIMIT $limit OFFSET $offset";

// ดึงข้อมูลจากฐานข้อมูล
$result = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดตามแผนงานผลิต</title>
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
        <h1>ติดตามแผนงานผลิต</h1>

        <!-- ฟอร์มค้นหาข้อมูล -->
        <form method="GET" action="">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="search_job_code" class="form-label">หมายเลข JOB</label>
                    <input type="text" class="form-control" name="search_job_code" id="search_job_code"
                        value="<?php echo isset($_GET['search_job_code']) ? $_GET['search_job_code'] : ''; ?>">
                </div>
                <div class="col-md-3">
                    <label for="search_job_type" class="form-label">ประเภทงาน</label>
                    <select class="form-select" name="search_job_type" id="search_job_type">
                        <option value="">-- เลือกประเภทงาน --</option>
                        <option value="งานหมวด A" <?php echo isset($_GET['search_job_type']) && $_GET['search_job_type'] === 'งานหมวด A' ? 'selected' : ''; ?>>งานหมวด A</option>
                        <option value="งานทั่วไป" <?php echo isset($_GET['search_job_type']) && $_GET['search_job_type'] === 'งานทั่วไป' ? 'selected' : ''; ?>>งานทั่วไป</option>
                        <option value="งานไม้ PACK" <?php echo isset($_GET['search_job_type']) && $_GET['search_job_type'] === 'งานไม้ PACK' ? 'selected' : ''; ?>>งานไม้ PACK</option>
                        <option value="PALLET RETURN" <?php echo isset($_GET['search_job_type']) && $_GET['search_job_type'] === 'PALLET RETURN' ? 'selected' : ''; ?>>PALLET RETURN</option>
                        <option value="งานเคลม" <?php echo isset($_GET['search_job_type']) && $_GET['search_job_type'] === 'งานเคลม' ? 'selected' : ''; ?>>งานเคลม</option>
                        <option value="งานพาเลทไม้อัด" <?php echo isset($_GET['search_job_type']) && $_GET['search_job_type'] === 'งานพาเลทไม้อัด' ? 'selected' : ''; ?>>งานพาเลทไม้อัด</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="search_customer" class="form-label">ชื่อลูกค้า</label>
                    <input type="text" class="form-control" name="search_customer" id="search_customer"
                        value="<?php echo isset($_GET['search_customer']) ? $_GET['search_customer'] : ''; ?>">
                </div>
                <div class="col-md-3">
                    <label for="search_prod_code" class="form-label">ITEM CODE FG</label>
                    <input type="text" class="form-control" name="search_prod_code" id="search_prod_code"
                        value="<?php echo isset($_GET['search_prod_code']) ? $_GET['search_prod_code'] : ''; ?>">
                </div>
                <div class="col-md-3">
                    <label for="search_prod_partno" class="form-label">PARTNO.</label>
                    <input type="text" class="form-control" name="search_prod_partno" id="search_prod_partno"
                        value="<?php echo isset($_GET['search_prod_partno']) ? $_GET['search_prod_partno'] : ''; ?>">
                </div>
                <div class="col-md-3">
                    <label for="search_status" class="form-label">สถานะ</label>
                    <select class="form-select" name="search_status" id="search_status">
                        <option value="">-- เลือกสถานะ --</option>
                        <option value="สั่งไม้" <?php echo isset($_GET['search_status']) && $_GET['search_status'] === 'สั่งไม้' ? 'selected' : ''; ?>>สั่งไม้</option>
                        <option value="กำลังเตรียมไม้" <?php echo isset($_GET['search_status']) && $_GET['search_status'] === 'กำลังเตรียมไม้' ? 'selected' : ''; ?>>กำลังเตรียมไม้</option>
                        <option value="รอเบิก" <?php echo isset($_GET['search_status']) && $_GET['search_status'] === 'รอเบิก' ? 'selected' : ''; ?>>รอเบิก</option>
                        <option value="เบิกแล้ว" <?php echo isset($_GET['search_status']) && $_GET['search_status'] === 'เบิกแล้ว' ? 'selected' : ''; ?>>เบิกแล้ว</option>
                        <option value="ยกเลิก" <?php echo isset($_GET['search_status']) && $_GET['search_status'] === 'ยกเลิก' ? 'selected' : ''; ?>>ยกเลิก</option>
                        <option value="ปิดสำเร็จ" <?php echo isset($_GET['search_status']) && $_GET['search_status'] === 'ปิดสำเร็จ' ? 'selected' : ''; ?>>ปิดสำเร็จ</option>
                    </select>
                </div>
                <!-- ช่องเลือกวันที่ created_at -->
                <div class="col-md-3">
                    <label for="search_date_created" class="form-label">วันที่ต้องการรับไม้	</label>
                    <input type="text" class="form-control" name="search_date_created" id="search_date_created"
                        value="<?php echo isset($_GET['search_date_created']) ? $_GET['search_date_created'] : ''; ?>" />
                </div>
            </div>
            <button type="submit" class="btn btn-primary">ค้นหา</button>
            <a href="check_all_product_planning.php" class="btn btn-secondary">รีเซ็ต</a>
        </form>

        <br>

        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>หมายเลข JOB</th>
                    <th>ITEM CODE FG</th>
                    <th>PART NO.</th>
                    <th>จำนวน</th>
                    <th>วันที่ต้องการรับไม้</th>
                    <th>สถานะ</th>
                    <th>ดำเนินการ</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo $row['job_id']; ?></td>
                        <td><?php echo $row['prod_code']; ?></td>
                        <td><?php echo isset($row['prod_partno']) ? $row['prod_partno'] : 'N/A'; ?></td>
                        <td><?php echo $row['quantity']; ?></td>
                        <td><?php echo date('Y-m-d', strtotime($row['want_receive'])); ?></td>
                        <td><?php echo $row['issue_status']; ?></td>
                        <td>
                            <a href="generate_issued_pdf.php?issue_id=<?php echo $row['issue_id']; ?>"
                                class="btn btn-info btn-sm" target="_blank">PDF</a>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

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


    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

    <script>
        // ใช้งาน daterangepicker สำหรับช่องวันที่
        $('#search_date_created').daterangepicker({
            locale: { format: 'YYYY-MM-DD' },
            opens: 'left',
        });
    </script>

</body>

</html>