<?php
include('config_db.php');

// กำหนดจำนวนรายการที่จะแสดงในแต่ละหน้า (สูงสุด 60 รายการ)
$items_per_page = 150;

// ตรวจสอบว่าผู้ใช้เลือกหน้าที่เท่าไหร่จาก GET (หากไม่มีให้ใช้หน้า 1)
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}
$offset = ($current_page - 1) * $items_per_page;

// ดึงค่าการค้นหาจาก GET (trim เพื่อความสะอาด)
$search_value = isset($_GET['search']) ? trim($_GET['search']) : '';
// สร้างตัวแปรสำหรับผนวกใน URL pagination
$search_query = ($search_value != '') ? '&search=' . urlencode($search_value) : '';

// สร้างเงื่อนไขค้นหาสำหรับ query ทั้งหมด
$search_condition = "";
if (!empty($search_value)) {
    $search_condition = " AND job_id LIKE '%$search_value%'";
}

// Query หลักสำหรับดึงข้อมูล JOB พร้อม LIMIT และ OFFSET เสมอ
$jobs_query = "SELECT DISTINCT job_id 
               FROM wood_issue 
               WHERE (issue_status = 'เบิกแล้ว' OR issue_status = 'ปิดสำเร็จ') 
               $search_condition
               ORDER BY creation_date DESC
               LIMIT $items_per_page OFFSET $offset";

// Query สำหรับนับจำนวน JOB ทั้งหมดที่ตรงกับเงื่อนไขค้นหา
if (!empty($search_value)) {
    $count_query = "SELECT COUNT(DISTINCT job_id) AS total 
                    FROM wood_issue 
                    WHERE (issue_status = 'เบิกแล้ว' OR issue_status = 'ปิดสำเร็จ') 
                    $search_condition";
} else {
    $count_query = "SELECT COUNT(DISTINCT job_id) AS total 
                    FROM wood_issue 
                    WHERE issue_status = 'เบิกแล้ว' OR issue_status = 'ปิดสำเร็จ'";
}

$count_result = mysqli_query($conn, $count_query);
$total_count = 0;
if ($count_result) {
    $count_row = mysqli_fetch_assoc($count_result);
    $total_count = $count_row['total'];
}
// คำนวณจำนวนหน้าที่ต้องแสดง (ถ้าจำนวนผลลัพธ์น้อยกว่า 60 ก็จะได้ 1 หน้า)
$total_pages = ($total_count > 0) ? ceil($total_count / $items_per_page) : 1;

// ดึงข้อมูล JOB ด้วย query ที่สร้างไว้
$jobs_result = mysqli_query($conn, $jobs_query);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการตรวจรับงาน</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
    <!-- แสดง Navbar -->
    <?php include('navbar.php'); ?>

<div class="container my-4">
    <h2>รายการตรวจรับงาน</h2>

    <!-- แบบฟอร์มค้นหา (ใช้ GET เพื่อให้แสดงใน URL) -->
    <form method="GET" class="mb-3">
        <div class="form-group">
            <label for="search" class="mb-2">ค้นหาหมายเลข JOB</label>
            <input type="text" class="form-control" id="search" name="search" placeholder="ใส่หมายเลข JOB" value="<?php echo htmlspecialchars($search_value); ?>">
        </div>
        <button type="submit" class="btn btn-primary mt-2">ค้นหา</button>
    </form>

    <!-- แสดงรายการ JOB -->
    <div class="row">
        <?php
        if ($jobs_result && mysqli_num_rows($jobs_result) > 0) {
            while ($row = mysqli_fetch_assoc($jobs_result)) {
                $job_id = $row['job_id'];

                // ดึงข้อมูลสถานะจาก wood_issue สำหรับ JOB นั้น
                $status_query = "SELECT issue_status FROM wood_issue WHERE job_id = '$job_id' LIMIT 1";
                $status_result = mysqli_query($conn, $status_query);
                $status_row = mysqli_fetch_assoc($status_result);
                $issue_status = $status_row['issue_status'];

                // เลือกไอคอนและสีตามสถานะ
                switch ($issue_status) {
                    case 'สั่งไม้':
                        $icon = 'fas fa-cogs text-warning';
                        break;
                    case 'กำลังเตรียมไม้':
                        $icon = 'fas fa-box-open text-info';
                        break;
                    case 'รอเบิก':
                        $icon = 'fas fa-clock text-secondary';
                        break;
                    case 'เบิกแล้ว':
                        $icon = 'fas fa-info-circle text-info';
                        break;
                    case 'ยกเลิก':
                        $icon = 'fas fa-ban text-danger';
                        break;
                    case 'ปิดสำเร็จ':
                        $icon = 'fas fa-thumbs-up text-success';
                        break;
                    default:
                        $icon = 'fas fa-question-circle text-muted';
                        break;
                }

                // ดึงข้อมูลผลิตภัณฑ์ (product_code, prod_id) สำหรับ JOB นี้
                $product_codes_query = "SELECT product_code, prod_id FROM wood_issue WHERE job_id = '$job_id'";
                $product_codes_result = mysqli_query($conn, $product_codes_query);

                // เก็บรายละเอียดผลิตภัณฑ์จากตาราง prod_list
                $prod_details = [];
                while ($product_row = mysqli_fetch_assoc($product_codes_result)) {
                    $product_code = $product_row['product_code'];
                    $prod_id = $product_row['prod_id'];

                    $prod_query = "SELECT prod_code,prod_partno, code_cus_size, prod_description FROM prod_list WHERE prod_id = '$prod_id'";
                    $prod_result = mysqli_query($conn, $prod_query);
                    if ($prod_row = mysqli_fetch_assoc($prod_result)) {
                        $prod_details[] = [
                            'prod_code'      => $prod_row['prod_code'],
                            'prod_partno'      => $prod_row['prod_partno'],
                            'code_cus_size'    => $prod_row['code_cus_size'],
                            'prod_description' => $prod_row['prod_description']
                        ];
                    }
                }

                // ถ้าสถานะเป็น 'ปิดสำเร็จ' ให้คำนวณจำนวนที่ส่งไปแล้ว
                $sent_qty = '';
                if ($issue_status == 'ปิดสำเร็จ') {
                    $jobs_complete_query = "SELECT SUM(prod_complete_qty) AS total_sent FROM jobs_complete WHERE job_id = '$job_id'";
                    $jobs_complete_result = mysqli_query($conn, $jobs_complete_query);
                    $jobs_complete_row = mysqli_fetch_assoc($jobs_complete_result);
                    $total_sent = $jobs_complete_row['total_sent'];

                    $quantity_query = "SELECT quantity FROM wood_issue WHERE job_id = '$job_id'";
                    $quantity_result = mysqli_query($conn, $quantity_query);
                    $quantity_row = mysqli_fetch_assoc($quantity_result);
                    $quantity = $quantity_row['quantity'];

                    $sent_qty = $total_sent . '/' . $quantity;
                }
        ?>
                <!-- Card สำหรับแสดงข้อมูล JOB -->
                <div class="col-md-4 col-sm-12 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">หมายเลข JOB: <?php echo $job_id; ?></h5>
                            <?php foreach ($prod_details as $prod) { ?>
                                <p class="card-text"><strong>สถานะ: <i class="<?php echo $icon; ?>"></i></strong> <?php echo $issue_status; ?></p>
                                <p class="card-text"><strong>PRODUCT CODE FG:</strong> <?php echo $prod['prod_code']; ?></p>
                                <p class="card-text"><strong>Part NO.:</strong> <?php echo $prod['prod_partno']; ?></p>
                                <p class="card-text"><strong>Code พิเศษ:</strong> <?php echo $prod['code_cus_size']; ?></p>
                                <p class="card-text"><strong>คำอธิบาย:</strong> <?php echo $prod['prod_description']; ?></p>
                                <?php
                                // ดึงข้อมูลผู้ส่งงานและผู้ตรวจรับ
                                $send_receive_query = "SELECT send_by, receive_by, record_by, reason, date_complete, date_receive, assembly_point FROM jobs_complete WHERE job_id = '$job_id' LIMIT 1";
                                $send_receive_result = mysqli_query($conn, $send_receive_query);
                                if ($send_receive_row = mysqli_fetch_assoc($send_receive_result)) {
                                    $date_complete = $send_receive_row['date_complete'];
                                    $send_by = $send_receive_row['send_by'];
                                    $receive_by = $send_receive_row['receive_by'];
                                    $record_by = $send_receive_row['record_by'];
                                    $reason = $send_receive_row['reason'];
                                    $assembly_point = $send_receive_row['assembly_point'];
                                    $date_receive = $send_receive_row['date_receive'];
                                } else {
                                    $date_complete = "ไม่พบข้อมูล";
                                    $send_by = "ไม่พบข้อมูล";
                                    $receive_by = "ไม่พบข้อมูล";
                                    $record_by = "ไม่พบข้อมูล";
                                    $reason = "ไม่พบข้อมูล";
                                    $assembly_point = "ไม่พบข้อมูล";
                                    $date_receive = "ไม่พบข้อมูล";
                                }
                                ?>
                                <p class="card-text"><strong>วันที่ส่งงาน:</strong> <?php echo $date_receive; ?></p>
                                <p class="card-text"><strong>วันที่ตรวจรับ:</strong> <?php echo $date_complete; ?></p>
                                <p class="card-text"><strong>จุดประกอบ:</strong> <?php echo $assembly_point; ?></p>
                                <p class="card-text"><strong>ผู้ส่งงาน:</strong> <?php echo $send_by; ?></p>
                                <p class="card-text"><strong>ผู้ตรวจรับ:</strong> <?php echo $receive_by; ?></p>
                                <p class="card-text"><strong>ผู้บันทึกข้อมูล:</strong> <?php echo $record_by; ?></p>
                                <p class="card-text"><strong>หมายเหตุ:</strong> <?php echo $reason; ?></p>
                            <?php } ?>
                            <?php if ($sent_qty != ''): ?>
                                <p class="card-text"><strong>ส่งงานแล้ว:</strong> <?php echo $sent_qty; ?></p>
                            <?php endif; ?>
                            <?php if ($issue_status == 'ปิดสำเร็จ'): ?>
                                <button class="btn btn-warning" disabled>ตรวจรับงาน</button>
                            <?php else: ?>
                                <a href="check_product_complete.php?job_id=<?php echo $job_id; ?>" class="btn btn-warning">ตรวจรับงาน</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
        <?php
            }
        } else {
            echo "<p>ไม่พบข้อมูลที่ค้นหา</p>";
        }
        ?>
    </div>

    <!-- แสดง Pagination -->
    <nav>
        <ul class="pagination justify-content-center">
            <!-- ปุ่ม Previous -->
            <li class="page-item <?php if ($current_page == 1) echo 'disabled'; ?>">
                <a class="page-link" href="?page=<?php echo $current_page - 1 . $search_query; ?>" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
            <?php 
            // แสดงลิงก์หน้าทั้งหมด หากจำนวนหน้าน้อยกว่าหรือเท่ากับ 5
            if ($total_pages <= 5) {
                for ($i = 1; $i <= $total_pages; $i++) { ?>
                    <li class="page-item <?php if ($current_page == $i) echo 'active'; ?>">
                        <a class="page-link" href="?page=<?php echo $i . $search_query; ?>"><?php echo $i; ?></a>
                    </li>
                <?php }
            } else {
                // ถ้ามากกว่า 5 ให้แสดงหน้าที่ 1 ถึง 5 ตามด้วย Ellipsis และหน้าสุดท้าย
                for ($i = 1; $i <= 5; $i++) { ?>
                    <li class="page-item <?php if ($current_page == $i) echo 'active'; ?>">
                        <a class="page-link" href="?page=<?php echo $i . $search_query; ?>"><?php echo $i; ?></a>
                    </li>
                <?php } ?>
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
                <li class="page-item <?php if ($current_page == $total_pages) echo 'active'; ?>">
                    <a class="page-link" href="?page=<?php echo $total_pages . $search_query; ?>"><?php echo $total_pages; ?></a>
                </li>
            <?php } ?>
            <!-- ปุ่ม Next -->
            <li class="page-item <?php if ($current_page == $total_pages) echo 'disabled'; ?>">
                <a class="page-link" href="?page=<?php echo $current_page + 1 . $search_query; ?>" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        </ul>
    </nav>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
</body>
</html>
