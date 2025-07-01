<?php
// ภาษา: PHP
// ชื่อไฟล์: return_list.php

include('config_db.php'); // เชื่อมต่อฐานข้อมูล

// กำหนดจำนวนรายการที่จะแสดงในแต่ละหน้า
$items_per_page = 15;

// ตรวจสอบว่าผู้ใช้เลือกหน้าที่เท่าไหร่ (ค่าพารามิเตอร์จาก URL ?page=)
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
// คำนวณ OFFSET สำหรับ SQL โดยใช้จำนวนรายการต่อหน้า
$offset = ($current_page - 1) * $items_per_page;

// ดึงค่าคำค้นหา (search) จาก URL เพื่อแสดงในฟอร์มและใช้ในการคิวรี
$search_value = isset($_GET['search']) ? trim($_GET['search']) : '';

// สร้างเงื่อนไขค้นหา SQL (ใช้ LIKE เพื่อค้นหาตรงตัว job_id)
$search_condition = '';
if ($search_value !== '') {
    $search_escaped = mysqli_real_escape_string($conn, $search_value);
    $search_condition = "AND job_id LIKE '%{$search_escaped}%'";
}

// ใช้ WHERE ... IN (...) เพื่อรวมหลายสถานะในครั้งเดียว
$status_list = [
    'เบิกแล้ว',
    'ปิดสำเร็จ',
    'ยกเลิก',
    'รอยืนยันการสั่งจ่าย',
    'สั่งจ่ายแล้ว'
];
// สร้างสตริง 'IN' จากอาร์เรย์สถานะ
$status_in = "'" . implode("','", $status_list) . "'";

// คิวรีเพื่อดึง job_id ตามสถานะ และ จำกัดผลลัพธ์ด้วย LIMIT/OFFSET
$jobs_query = "
    SELECT DISTINCT job_id
    FROM wood_issue
    WHERE issue_status IN ({$status_in})
    {$search_condition}
    ORDER BY creation_date DESC
    LIMIT {$items_per_page}
    OFFSET {$offset}
";

// คิวรีเพื่อดึงจำนวนทั้งหมดของ job_id สำหรับการคำนวณจำนวนหน้า
$total_jobs_query = "
    SELECT COUNT(DISTINCT job_id) AS total
    FROM wood_issue
    WHERE issue_status IN ({$status_in})
    {$search_condition}
";

// รันคิวรี
$jobs_result = mysqli_query($conn, $jobs_query);
$total_jobs_result = mysqli_query($conn, $total_jobs_query);
$total_jobs_row = mysqli_fetch_assoc($total_jobs_result);
$total_jobs = (int) $total_jobs_row['total'];

// คำนวณจำนวนหน้าทั้งหมด (ceil ปัดขึ้นกรณีไม่เต็มหน้า)
$total_pages = ceil($total_jobs / $items_per_page);
?>


<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการการรับไม้คืน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<!-- Navbar -->
<?php include('navbar.php'); ?>

<div class="container py-4">
    <h2 class="mb-4">รายการการรับไม้คืน</h2>

    <!-- ฟอร์มค้นหา: ใช้ GET เพื่อให้ search และ page อยู่ใน URL -->
    <form method="GET" class="row g-3 mb-4">
        <div class="col-auto">
            <label for="search" class="visually-hidden">ค้นหาหมายเลข JOB</label>
            <input type="text" class="form-control" id="search" name="search" placeholder="ใส่หมายเลข JOB" 
                   value="<?php echo htmlspecialchars($search_value, ENT_QUOTES); ?>">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">ค้นหา</button>
        </div>
    </form>

    <!-- รายการ JOB -->
    <div class="row">
        <?php if (mysqli_num_rows($jobs_result) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($jobs_result)): ?>
                <?php
                // ดึง job_id และสถานะล่าสุด
                $job_id = $row['job_id'];
                $status_query = "SELECT issue_status FROM wood_issue WHERE job_id = '" . mysqli_real_escape_string($conn, $job_id) . "' ORDER BY creation_date DESC LIMIT 1";
                $status_result = mysqli_query($conn, $status_query);
                $status_row = mysqli_fetch_assoc($status_result);
                $issue_status = $status_row['issue_status'];

                // เลือกไอคอนตามสถานะ
                switch ($issue_status) {
                    case 'สั่งไม้':           $icon = 'fas fa-cogs text-warning'; break;
                    case 'กำลังเตรียมไม้':    $icon = 'fas fa-box-open text-info'; break;
                    case 'รอเบิก':            $icon = 'fas fa-clock text-secondary'; break;
                    case 'เบิกแล้ว':          $icon = 'fas fa-info-circle text-info'; break;
                    case 'ยกเลิก':            $icon = 'fas fa-ban text-danger'; break;
                    case 'เสร็จเรียบร้อย':    $icon = 'fas fa-thumbs-up text-success'; break;
                    default:                  $icon = 'fas fa-question-circle text-muted'; break;
                }

                // ดึงรายละเอียดสินค้า
                $product_codes_query = "SELECT product_code FROM wood_issue WHERE job_id = '" . mysqli_real_escape_string($conn, $job_id) . "'";
                $prod_details = [];
                $product_codes_result = mysqli_query($conn, $product_codes_query);
                while ($product_row = mysqli_fetch_assoc($product_codes_result)) {
                    $prod_code = mysqli_real_escape_string($conn, $product_row['product_code']);
                    $prod_query = "SELECT prod_partno, code_cus_size, prod_description FROM prod_list WHERE prod_code = '{$prod_code}' LIMIT 1";
                    $prod_result = mysqli_query($conn, $prod_query);
                    if ($prod = mysqli_fetch_assoc($prod_result)) {
                        $prod_details[] = [
                            'prod_partno'     => $prod['prod_partno'],
                            'code_cus_size'   => $prod['code_cus_size'],
                            'prod_description'=> $prod['prod_description'],
                            'product_code'    => $prod_code
                        ];
                    }
                }
                ?>

                <!-- Card แสดงข้อมูล JOB -->
                <div class="col-md-4 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">หมายเลข JOB: <?php echo htmlspecialchars($job_id); ?></h5>
                            <p class="card-text"><strong>สถานะ: <i class="<?php echo $icon; ?>"></i></strong> <?php echo htmlspecialchars($issue_status); ?></p>
                            <?php foreach ($prod_details as $prod): ?>
                                <p class="card-text mb-1"><strong>PRODUCT CODE FG:</strong> <?php echo htmlspecialchars($prod['product_code']); ?></p>
                                <p class="card-text mb-1"><strong>Part NO.:</strong> <?php echo htmlspecialchars($prod['prod_partno']); ?></p>
                                <p class="card-text mb-1"><strong>Code พิเศษ:</strong> <?php echo htmlspecialchars($prod['code_cus_size']); ?></p>
                                <p class="card-text mb-3"><strong>คำอธิบาย:</strong> <?php echo htmlspecialchars($prod['prod_description']); ?></p>
                            <?php endforeach; ?>
                            <a href="return_wood.php?job_code=<?php echo urlencode($job_id); ?>" class="btn btn-success">คืนไม้</a>
                        </div>
                    </div>
                </div>

            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-muted">ไม่พบข้อมูลที่ค้นหา</p>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <nav aria-label="Page navigation">
        <?php
        // กำหนดช่วงหน้าที่จะแสดงรอบหน้า current
        $range = 2; // แสดง current_page +/- $range
        ?>
        <ul class="pagination justify-content-center">
            <?php
            // ปุ่มก่อนหน้า
            $prev = $current_page - 1;
            ?>
            <li class="page-item <?php echo ($current_page <= 1 ? 'disabled' : ''); ?>">
                <a class="page-link" href="?page=<?php echo max(1, $prev); ?>&search=<?php echo urlencode($search_value); ?>">ก่อนหน้า</a>
            </li>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == 1 || $i == $total_pages || ($i >= $current_page - $range && $i <= $current_page + $range)): ?>
                    <li class="page-item <?php echo ($i === $current_page ? 'active' : ''); ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_value); ?>"><?php echo $i; ?></a>
                    </li>
                <?php elseif ($i == 2 && $current_page - $range > 2): ?>
                    <li class="page-item disabled"><span class="page-link">…</span></li>
                <?php elseif ($i == $total_pages - 1 && $current_page + $range < $total_pages - 1): ?>
                    <li class="page-item disabled"><span class="page-link">…</span></li>
                <?php endif; ?>
            <?php endfor; ?>

            <?php
            // ปุ่มถัดไป
            $next = $current_page + 1;
            ?>
            <li class="page-item <?php echo ($current_page >= $total_pages ? 'disabled' : ''); ?>">
                <a class="page-link" href="?page=<?php echo min($total_pages, $next); ?>&search=<?php echo urlencode($search_value); ?>">ถัดไป</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
