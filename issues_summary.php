<?php
include('config_db.php');  // เชื่อมต่อฐานข้อมูล

// กำหนดจำนวนผลลัพธ์ที่จะแสดงต่อหน้า
$results_per_page = 10;  

// ตรวจสอบว่ามีการกำหนด page หรือไม่ ถ้าไม่ใช้หน้าแรก
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;  
$offset = ($current_page - 1) * $results_per_page;

// รับค่า start_date, end_date, และ job_id จากฟอร์มค้นหาหรือ default
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$job_id = isset($_GET['job_id']) ? $_GET['job_id'] : '';

$issue_status = isset($_GET['issue_status']) ? $_GET['issue_status'] : '';

// สร้าง query สำหรับการดึงข้อมูลจาก jobs_complete พร้อมการฟิลเตอร์สถานะ
$sql = "SELECT DISTINCT * FROM wood_issue 
        INNER JOIN jobs_complete ON wood_issue.job_id = jobs_complete.job_id 
        WHERE 1=1";  // เริ่มต้นด้วยเงื่อนไขทั่วไป

// ฟิลเตอร์ตามสถานะ
if ($issue_status) {
    $sql .= " AND wood_issue.issue_status = '$issue_status'";
}

// ฟิลเตอร์ตามวันที่
if ($start_date && $end_date) {
    $sql .= " AND jobs_complete.date_complete BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";
}

// ฟิลเตอร์ตาม job_id
if ($job_id) {
    $sql .= " AND wood_issue.job_id LIKE '%$job_id%'";
}

$sql .= " LIMIT $offset, $results_per_page";  

$result = mysqli_query($conn, $sql);

// การคำนวณจำนวนหน้าทั้งหมด
$total_sql = "SELECT COUNT(*) AS total FROM wood_issue 
              INNER JOIN jobs_complete ON wood_issue.job_id = jobs_complete.job_id 
              WHERE wood_issue.issue_status = 'ปิดสำเร็จ'";  // เพิ่มเงื่อนไขที่ issue_status = 'ปิดสำเร็จ'

if ($start_date && $end_date) {
    $total_sql .= " AND jobs_complete.date_complete BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";
}
if ($job_id) {
    $total_sql .= " AND wood_issue.job_id LIKE '%$job_id%'";
}
$total_result = mysqli_query($conn, $total_sql);
$total_row = mysqli_fetch_assoc($total_result);
$total_pages = ceil($total_row['total'] / $results_per_page);

// ตัวแปรสำหรับการคำนวณผลรวม
$total_m3_borrowed = 0;
$total_real_usage_m3 = 0;
$total_loss_m3 = 0;
$total_loss_percent = 0;
$total_count = 0;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- เพิ่ม meta tag นี้สำหรับ responsive -->
    <title>สรุปการเบิกไม้</title>
    <!-- <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css"> -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <!-- Navbar -->
    <?php include('navbar.php'); ?>

    <div class="container mt-5">
        <h2>สรุปการเบิกไม้</h2>
        
        <!-- ช่องค้นหาตามช่วงวันที่และ job_id -->
        <form method="GET" class="row mb-3">
            <div class="col-md-3 col-12 mb-3 mb-md-0">
                <label for="start_date" class="form-label">วันที่เริ่มต้น:</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
            </div>

            <div class="col-md-3 col-12 mb-3 mb-md-0">
                <label for="end_date" class="form-label">วันที่สิ้นสุด:</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
            </div>

            <div class="col-md-3 col-12 mb-3 mb-md-0">
                <label for="job_id" class="form-label">Job ID:</label>
                <input type="text" class="form-control" id="job_id" name="job_id" value="<?php echo $job_id; ?>" placeholder="กรอกหมายเลข JOB">
            </div>

            <div class="col-md-3 col-12 mb-3 mb-md-0">
                <label for="issue_status" class="form-label">สถานะ:</label>
                <select class="form-control" id="issue_status" name="issue_status">
                    <option value="">-- เลือกสถานะ --</option>

                    <option value="สั่งไม้" <?php if ($issue_status == 'สั่งไม้') echo 'selected'; ?>>สั่งไม้</option>
                    <option value="กำลังเตรียมไม้" <?php if ($issue_status == 'กำลังเตรียมไม้') echo 'selected'; ?>>กำลังเตรียมไม้</option>
                    <option value="รอเบิก" <?php if ($issue_status == 'รอเบิก') echo 'selected'; ?>>รอเบิก</option>
                    <option value="เบิกแล้ว" <?php if ($issue_status == 'เบิกแล้ว') echo 'selected'; ?>>เบิกแล้ว</option>
                    <option value="ปิดสำเร็จ" <?php if ($issue_status == 'ปิดสำเร็จ') echo 'selected'; ?>>ปิดสำเร็จ</option>
                    <option value="ยกเลิก" <?php if ($issue_status == 'ยกเลิก') echo 'selected'; ?>>ยกเลิก</option>
                    <!-- เพิ่มสถานะอื่นๆ ที่ต้องการ -->
                </select>
            </div>

            <div class="col-md-3 col-12 d-flex justify-content-center align-items-end mt-3">
                <button type="submit" class="btn btn-primary w-100">ค้นหา</button>
            </div>
            
        </form>
        <br>
        <div class="row">
            <?php
            // ตรวจสอบว่า query มีผลลัพธ์หรือไม่
            if (mysqli_num_rows($result) > 0) {
                while($row = mysqli_fetch_assoc($result)) {
                    // ดึงข้อมูลจากตาราง bom ตาม job_id
                    $job_id = $row['job_id'];
                    $prod_id = $row['prod_id'];
                    
                    // Query เพื่อดึงข้อมูลจากตาราง bom ตาม prod_id
                    $bom_sql = "SELECT parts FROM bom WHERE prod_id = '$prod_id'";
                    $bom_result = mysqli_query($conn, $bom_sql);
                    $bom_row = mysqli_fetch_assoc($bom_result);

                    // ตรวจสอบว่า parts มีค่าและแปลงเป็น array
                    $parts = isset($bom_row['parts']) ? json_decode($bom_row['parts'], true) : null;

                    // กำหนดค่า m3 รวมงานหลักเป็น 0 หาก parts เป็น null หรือไม่สามารถแปลงเป็น array
                    $total_m3_main = 0;
                    $m3_main = 0;
                    if (is_array($parts)) {
                        // คำนวณ m3 รวมจาก parts ที่ไม่อยู่ในประเภทที่กำหนด
                        foreach ($parts as $part) {
                            $part_id = $part['part_id'];
                            $quantity = $part['quantity'];

                            // Query เพื่อดึงข้อมูลจาก part_list ตาม part_id
                            $part_sql = "SELECT part_m3, part_type FROM part_list WHERE part_id = '$part_id'";
                            $part_result = mysqli_query($conn, $part_sql);
                            $part_row = mysqli_fetch_assoc($part_result);

                            // ตรวจสอบว่า part_type ไม่ตรงกับประเภทที่ไม่ต้องการ
                            $excluded_types = ['PLYขาว-แดง', 'PLYแดง-แดง', 'PLYขาว-ขาว', 'PLY เกรดA', 'PLY ขาว-แดง', 'PLY SK', 'PLY 20MMแดง-แดง', 'PLY'];
                            if (!in_array($part_row['part_type'], $excluded_types)) {
                                // คำนวณ m3 ของ part ที่ไม่อยู่ในประเภทที่ถูกยกเว้น
                                $total_m3_main += $part_row['part_m3'] * $quantity;
                                $m3_main += $part_row['part_m3'] * $quantity;
                            }
                        }
                        // คำนวณ m3 รวมงานหลักโดยใช้ quantity ของ wood_issue
                        $total_m3_main *= $row['quantity'];
                        $m3_main;
                    }

                    // คำนวณ m3 รวมของงานซ่อม
                    $repair_total_m3 = 0;
                    $repair_sql = "SELECT part_quantity_reason FROM repair_issue WHERE job_id = '$job_id'";
                    $repair_result = mysqli_query($conn, $repair_sql);
                    if (mysqli_num_rows($repair_result) > 0) {
                        $repair_row = mysqli_fetch_assoc($repair_result);
                        $repair_parts = json_decode($repair_row['part_quantity_reason'], true);
                        
                        if (is_array($repair_parts)) {
                            foreach ($repair_parts as $repair_part) {
                                $part_code = $repair_part['part_id'];
                                $repair_quantity = $repair_part['quantity'];

                                // Query เพื่อดึงข้อมูลจาก part_list ตาม part_code
                                $repair_part_sql = "SELECT part_m3 FROM part_list WHERE part_id = '$part_code'";
                                $repair_part_result = mysqli_query($conn, $repair_part_sql);
                                if (mysqli_num_rows($repair_part_result) > 0) {
                                    $repair_part_row = mysqli_fetch_assoc($repair_part_result);
                                    $repair_part_m3 = $repair_part_row['part_m3'];
                                    // คำนวณ m3 รวมของงานซ่อม
                                    $repair_total_m3 += $repair_part_m3 * $repair_quantity;
                                }
                            }
                        }
                    }

                    // คำนวณ m3 ที่เบิก
                    $returned_m3 = 0;
                    $return_sql = "SELECT return_total_m3 FROM return_wood_wip WHERE job_id = '$job_id'";
                    $return_result = mysqli_query($conn, $return_sql);
                    if (mysqli_num_rows($return_result) > 0) {
                        $return_row = mysqli_fetch_assoc($return_result);
                        $returned_m3 = $return_row['return_total_m3'];
                    }
                    $m3_borrowed = ($total_m3_main + $repair_total_m3) - $returned_m3;

                    // คำนวณ m3 ใช้จริง
                    $prod_complete_qty = 0;
                    $jobs_complete_sql = "SELECT prod_complete_qty FROM jobs_complete WHERE job_id = '$job_id'";
                    $jobs_complete_result = mysqli_query($conn, $jobs_complete_sql);
                    if (mysqli_num_rows($jobs_complete_result) > 0) {
                        $jobs_complete_row = mysqli_fetch_assoc($jobs_complete_result);
                        $prod_complete_qty = $jobs_complete_row['prod_complete_qty'];
                    }
                    $real_usage_m3 = $m3_main * $prod_complete_qty;

                    // คำนวณ loss
                    $loss_m3 = $m3_borrowed - $real_usage_m3;

                    // เพิ่มค่าผลรวม
                    $total_m3_borrowed += $m3_borrowed;
                    $total_real_usage_m3 += $real_usage_m3;
                    $total_loss_m3 += $loss_m3;
                    if ($m3_borrowed > 0) {
                        $total_loss_percent += ($loss_m3 / $m3_borrowed) * 100;
                    }
                    $total_count++;
                    ?>
                    <div class="col-md-4 col-12 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <strong>หมายเลข JOB:</strong> <?php echo $row['job_id']; ?>
                            </div>
                            <div class="card-body">
                                <p><strong>Product Code: </strong> <?php echo $row['product_code']; ?></p>
                                <p><strong>วันที่สร้างคำสั่งเบิก: </strong> <?php echo $row['creation_date']; ?></p>
                                <p><strong>วันที่เบิกไม้: </strong> <?php echo $row['issue_date']; ?></p>
                                <p><strong>สั่งผลิต: </strong> <?php echo $row['quantity']; ?> ชิ้น <strong>ตรวจรับ: </strong> <?php echo $row['prod_complete_qty']; ?> ชิ้น</p>
                                <p><strong>วันที่ตรวจรับ: </strong>
                                    <?php 
                                    // ดึงวันที่จาก jobs_complete โดยอ้างอิงจาก job_id
                                    $jobs_complete_sql = "SELECT date_complete FROM jobs_complete WHERE job_id = '$job_id'";
                                    $jobs_complete_result = mysqli_query($conn, $jobs_complete_sql);
                                    
                                    if (mysqli_num_rows($jobs_complete_result) > 0) {
                                        $jobs_complete_row = mysqli_fetch_assoc($jobs_complete_result);
                                        echo date('d-m-Y', strtotime($jobs_complete_row['date_complete'])); // แสดงวันที่ในรูปแบบที่ต้องการ
                                    } else {
                                        echo "-"; // กรณีไม่พบข้อมูล
                                    }
                                    ?>
                                </p>
                                <p><strong>m3 รวมงานหลัก: </strong> <?php echo number_format($total_m3_main, 4); ?> m3</p>
                                <p><strong>m3 รวมเบิกซ่อม: </strong> <?php echo number_format($repair_total_m3, 4); ?> m3</p>
                                <p><strong>m3 รวมไม้คืน: </strong> <?php echo number_format($returned_m3, 4); ?> m3</p>
                                <p><strong>m3 ที่เบิก: </strong> <?php echo number_format($m3_borrowed, 4); ?> <strong>m3 ใช้จริง: </strong><?php echo number_format($real_usage_m3, 4); ?></p>
                                <p><strong>LOSS: </strong> <?php echo number_format($loss_m3, 4); ?> m3 <strong>LOSS(%): </strong><?php echo number_format(($loss_m3/$m3_borrowed*100), 4); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo "<div class='col-12'><p class='text-center'>ไม่มีข้อมูล</p></div>";
            }
            ?>
        </div>

        <br><br>

        <!-- ผลรวมการคำนวณที่แสดงด้านบน -->
        <div class="row">
            <div class="col-md-3 col-12 mb-4">
                <h4>ยอดรวม m3 ที่เบิกทั้งหมด:</h4>
                <p><?php echo number_format($total_m3_borrowed, 4); ?> m3</p>
            </div>
            <div class="col-md-3 col-12 mb-4">
                <h4>ยอดรวม m3 ใช้จริงทั้งหมด:</h4>
                <p><?php echo number_format($total_real_usage_m3, 4); ?> m3</p>
            </div>
            <div class="col-md-3 col-12 mb-4">
                <h4>ยอดรวม LOSS:</h4>
                <p><?php echo number_format($total_loss_m3, 4); ?> m3</p>
            </div>
            <div class="col-md-3 col-12 mb-4">
                <h4>ยอดรวม LOSS(%)</h4>
                <p><?php echo number_format($total_loss_percent, 2); ?>%</p>
            </div>
        </div>

        <br><br>

        <!-- Pagination -->
        <nav aria-label="Page navigation example">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php if ($current_page <= 1) echo 'disabled'; ?>">
                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">ก่อนหน้า</a>
                </li>

                <?php for ($i = 1; $i <= $total_pages; $i++) { ?>
                    <li class="page-item <?php if ($i == $current_page) echo 'active'; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>"><?php echo $i; ?></a>
                    </li>
                <?php } ?>

                <li class="page-item <?php if ($current_page >= $total_pages) echo 'disabled'; ?>">
                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">ถัดไป</a>
                </li>
            </ul>
        </nav>
    </div>

    <!-- ลิงก์ไปยัง Bootstrap 5 JS และ Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
</body>
</html>
