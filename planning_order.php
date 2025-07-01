<?php
session_start();
include 'config_db.php';

// กำหนดจำนวนข้อมูลต่อหน้า
$records_per_page_default = 60;
$records_per_page = $records_per_page_default;

// 1. ตรวจสอบ action=reset เพื่อเคลียร์เงื่อนไขค้นหาใน session
if (isset($_GET['action']) && $_GET['action'] === 'reset') {
    unset($_SESSION['search_job_code']);
    unset($_SESSION['search_job_type_code']);
    unset($_SESSION['search_prod_part_code']);
    unset($_SESSION['search_customer_name']);
    unset($_SESSION['search_status']);
    unset($_SESSION['search_creation_date']);
    unset($_SESSION['search_want_receive']);
    unset($_SESSION['search_issue_date']);
    
    // Redirect กลับไปที่ planning_order.php โดยไม่มีพารามิเตอร์ action
    header('Location: planning_order.php');
    exit();
}

// 2. ถ้าเป็น POST (ค้นหา) ให้เก็บค่าใน session แล้ว redirect ไปหน้าแรก (page=1)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $_SESSION['search_job_code']       = $_POST['search_job_code'];
    $_SESSION['search_job_type_code']  = $_POST['search_job_type_code'];
    $_SESSION['search_prod_part_code'] = $_POST['search_prod_part_code'];
    $_SESSION['search_customer_name']  = $_POST['search_customer_name'];
    $_SESSION['search_status']         = $_POST['search_status'];
    $_SESSION['search_creation_date']  = $_POST['search_creation_date'];
    $_SESSION['search_want_receive']   = $_POST['search_want_receive'];
    $_SESSION['search_issue_date']     = $_POST['search_issue_date'];
    
    header("Location: planning_order.php?page=1");
    exit();
}

// 3. สร้างเงื่อนไขค้นหาโดยอ่านค่าจาก session
$where = "WHERE 1";
$search_job_code       = isset($_SESSION['search_job_code']) ? $_SESSION['search_job_code'] : '';
$search_job_type_code  = isset($_SESSION['search_job_type_code']) ? $_SESSION['search_job_type_code'] : '';
$search_prod_part_code = isset($_SESSION['search_prod_part_code']) ? $_SESSION['search_prod_part_code'] : '';
$search_customer_name  = isset($_SESSION['search_customer_name']) ? $_SESSION['search_customer_name'] : '';
$search_status         = isset($_SESSION['search_status']) ? $_SESSION['search_status'] : '';
$search_creation_date  = isset($_SESSION['search_creation_date']) ? $_SESSION['search_creation_date'] : '';
$search_want_receive   = isset($_SESSION['search_want_receive']) ? $_SESSION['search_want_receive'] : '';
$search_issue_date     = isset($_SESSION['search_issue_date']) ? $_SESSION['search_issue_date'] : '';

if (!empty($search_job_code)) {
    $where .= " AND wi.job_id LIKE '%$search_job_code%'";
}
if (!empty($search_job_type_code)) {
    $where .= " AND wi.job_type LIKE '%$search_job_type_code%'";
}
if (!empty($search_prod_part_code)) {
    $where .= " AND (pl.prod_code LIKE '%$search_prod_part_code%' OR pl.prod_partno LIKE '%$search_prod_part_code%')";
}
if (!empty($search_customer_name)) {
    $where .= " AND c.customer_name LIKE '%$search_customer_name%'";
}
if (!empty($search_status)) {
    $where .= " AND wi.issue_status LIKE '%$search_status%'";
}
if (!empty($search_creation_date)) {
    $where .= " AND wi.creation_date LIKE '%$search_creation_date%'";
}
if (!empty($search_want_receive)) {
    $where .= " AND wi.want_receive LIKE '%$search_want_receive%'";
}
if (!empty($search_issue_date)) {
    $where .= " AND wi.issue_date LIKE '%$search_issue_date%'";
}

// 4. ตรวจสอบ current_page จาก GET และคำนวณ OFFSET
if (isset($_GET['page'])) {
    $current_page = (int)$_GET['page'];
} else {
    $current_page = 1;
}
$offset = ($current_page - 1) * $records_per_page;

// 5. ดึงจำนวน total records และคำนวณ total pages
$sql_count = "SELECT COUNT(*) as total FROM wood_issue wi
              JOIN prod_list pl ON wi.prod_id = pl.prod_id
              JOIN customer c ON pl.customer_id = c.customer_id
              $where";
$res_count = $conn->query($sql_count);
$row_count = $res_count->fetch_assoc();
$total_records = $row_count['total'];
$total_pages = ceil($total_records / $records_per_page);

// 6. ดึงข้อมูลสำหรับหน้าปัจจุบัน
$sql = "SELECT wi.*, pl.prod_id, pl.prod_code, pl.prod_partno, c.customer_name
        FROM wood_issue wi
        JOIN prod_list pl ON wi.prod_id = pl.prod_id
        JOIN customer c ON pl.customer_id = c.customer_id
        $where
        ORDER BY wi.creation_date DESC
        LIMIT $offset, $records_per_page";
$result = $conn->query($sql);

// 7. สร้าง query string สำหรับเงื่อนไขค้นหา เพื่อใช้ในลิงก์ Pagination
$search_query = "";
if(isset($_SESSION['search_job_code']) && $_SESSION['search_job_code'] != '') {
    $search_query .= "&search_job_code=" . urlencode($_SESSION['search_job_code']);
}
if(isset($_SESSION['search_job_type_code']) && $_SESSION['search_job_type_code'] != '') {
    $search_query .= "&search_job_type_code=" . urlencode($_SESSION['search_job_type_code']);
}
if(isset($_SESSION['search_prod_part_code']) && $_SESSION['search_prod_part_code'] != '') {
    $search_query .= "&search_prod_part_code=" . urlencode($_SESSION['search_prod_part_code']);
}
if(isset($_SESSION['search_customer_name']) && $_SESSION['search_customer_name'] != '') {
    $search_query .= "&search_customer_name=" . urlencode($_SESSION['search_customer_name']);
}
if(isset($_SESSION['search_status']) && $_SESSION['search_status'] != '') {
    $search_query .= "&search_status=" . urlencode($_SESSION['search_status']);
}
if(isset($_SESSION['search_creation_date']) && $_SESSION['search_creation_date'] != '') {
    $search_query .= "&search_creation_date=" . urlencode($_SESSION['search_creation_date']);
}
if(isset($_SESSION['search_want_receive']) && $_SESSION['search_want_receive'] != '') {
    $search_query .= "&search_want_receive=" . urlencode($_SESSION['search_want_receive']);
}
if(isset($_SESSION['search_issue_date']) && $_SESSION['search_issue_date'] != '') {
    $search_query .= "&search_issue_date=" . urlencode($_SESSION['search_issue_date']);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planning Order</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

<!-- Navbar -->
<?php include('navbar.php'); ?>

<!-- Content -->
<div class="container mt-5">

    <form method="post" action="planning_order.php">
        <div class="row mb-3">
            <div class="col-md-2">
                <label for="search_job_code" class="form-label">หมายเลข JOB</label>
                <input type="text" class="form-control" name="search_job_code" id="search_job_code" value="<?php echo isset($_SESSION['search_job_code']) ? $_SESSION['search_job_code'] : ''; ?>">
            </div>

            <div class="col-md-2">
                <label for="search_job_type_code" class="form-label">ประเภทงาน</label>
                <select class="form-select" name="search_job_type_code" id="search_job_type_code">
                    <option value="">-- เลือกประเภทงาน --</option>
                    <option value="งานหมวด A" <?php echo (isset($_SESSION['search_job_type_code']) && $_SESSION['search_job_type_code'] == 'งานหมวด A') ? 'selected' : ''; ?>>งานหมวด A</option>
                    <option value="งานทั่วไป" <?php echo (isset($_SESSION['search_job_type_code']) && $_SESSION['search_job_type_code'] == 'งานทั่วไป') ? 'selected' : ''; ?>>งานทั่วไป</option>
                    <option value="งานพาเลทไม้อัด" <?php echo (isset($_SESSION['search_job_type_code']) && $_SESSION['search_job_type_code'] == 'งานพาเลทไม้อัด') ? 'selected' : ''; ?>>งานพาเลทไม้อัด</option>
                    <option value="งานไม้ PACK" <?php echo (isset($_SESSION['search_job_type_code']) && $_SESSION['search_job_type_code'] == 'งานไม้ PACK') ? 'selected' : ''; ?>>งานไม้ PACK</option>
                    <option value="PALLET RETURN" <?php echo (isset($_SESSION['search_job_type_code']) && $_SESSION['search_job_type_code'] == 'PALLET RETURN') ? 'selected' : ''; ?>>PALLET RETURN</option>
                    <option value="งานเคลม" <?php echo (isset($_SESSION['search_job_type_code']) && $_SESSION['search_job_type_code'] == 'งานเคลม') ? 'selected' : ''; ?>>งานเคลม</option>
                    <option value="งานภายใน" <?php echo (isset($_SESSION['search_job_type_code']) && $_SESSION['search_job_type_code'] == 'งานภายใน') ? 'selected' : ''; ?>>งานภายใน</option>
                </select>
            </div>

            <div class="col-md-2">
                <label for="search_prod_part_code" class="form-label">หมายเลข PRODUCT</label>
                <input type="text" class="form-control" name="search_prod_part_code" id="search_prod_part_code" value="<?php echo isset($_SESSION['search_prod_part_code']) ? $_SESSION['search_prod_part_code'] : ''; ?>">
            </div>

            <div class="col-md-2">
                <label for="search_customer_name" class="form-label">ชื่อลูกค้า</label>
                <input type="text" class="form-control" name="search_customer_name" id="search_customer_name" value="<?php echo isset($_SESSION['search_customer_name']) ? $_SESSION['search_customer_name'] : ''; ?>">
            </div>

            <div class="col-md-2">
                <label for="search_status" class="form-label">สถานะงาน</label>
                <select class="form-select" name="search_status" id="search_status">
                    <option value="">-- เลือกสถานะ --</option>
                    <option value="รอยืนยันงาน" <?php echo (isset($_SESSION['search_status']) && $_SESSION['search_status'] == 'รอยืนยันงาน') ? 'selected' : ''; ?>>รอยืนยันงาน</option>
                    <option value="สั่งไม้" <?php echo (isset($_SESSION['search_status']) && $_SESSION['search_status'] == 'สั่งไม้') ? 'selected' : ''; ?>>สั่งไม้</option>
                    <option value="กำลังเตรียมไม้" <?php echo (isset($_SESSION['search_status']) && $_SESSION['search_status'] == 'กำลังเตรียมไม้') ? 'selected' : ''; ?>>กำลังเตรียมไม้</option>
                    <option value="รอเบิก" <?php echo (isset($_SESSION['search_status']) && $_SESSION['search_status'] == 'รอเบิก') ? 'selected' : ''; ?>>รอเบิก</option>
                    <option value="เบิกแล้ว" <?php echo (isset($_SESSION['search_status']) && $_SESSION['search_status'] == 'เบิกแล้ว') ? 'selected' : ''; ?>>เบิกแล้ว</option>
                    <option value="สั่งจ่ายแล้ว" <?php echo (isset($_SESSION['search_status']) && $_SESSION['search_status'] == 'สั่งจ่ายแล้ว') ? 'selected' : ''; ?>>สั่งจ่ายแล้ว</option>
                    <option value="รอยืนยันการสั่งจ่าย" <?php echo (isset($_SESSION['search_status']) && $_SESSION['search_status'] == 'รอยืนยันการสั่งจ่าย') ? 'selected' : ''; ?>>รอยืนยันการสั่งจ่าย</option>
                    <option value="ปิดสำเร็จ" <?php echo (isset($_SESSION['search_status']) && $_SESSION['search_status'] == 'ปิดสำเร็จ') ? 'selected' : ''; ?>>ปิดสำเร็จ</option>
                    <option value="ยกเลิก" <?php echo (isset($_SESSION['search_status']) && $_SESSION['search_status'] == 'ยกเลิก') ? 'selected' : ''; ?>>ยกเลิก</option>
                </select>
            </div>

            <div class="col-md-2">
                <label for="search_creation_date" class="form-label">วันที่ออกเอกสาร</label>
                <input type="date" class="form-control" name="search_creation_date" id="search_creation_date" value="<?php echo isset($_SESSION['search_creation_date']) ? $_SESSION['search_creation_date'] : ''; ?>">
            </div>
            <div class="col-md-2">
                <label for="search_want_receive" class="form-label">วันที่ต้องการรับไม้</label> 
                <input type="date" class="form-control" name="search_want_receive" id="search_want_receive" value="<?php echo isset($_SESSION['search_want_receive']) ? $_SESSION['search_want_receive'] : ''; ?>">
            
            </div>
            <div class="col-md-2">
                <label for="search_issue_date" class="form-label">วันที่เบิก</label>
                <input type="date" class="form-control" name="search_issue_date" id="search_issue_date" value="<?php echo isset($_SESSION['search_issue_date']) ? $_SESSION['search_issue_date'] : ''; ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary mt-4">ค้นหาใบงาน</button>
                <!-- ปุ่มรีเซ็ต -->
                <a href="planning_order.php?action=reset" class="btn btn-secondary mt-4">รีเซตการค้นหา</a>
            </div>
        </div>
    </form>

    <br>
    <br>
    <br>

    <!-- ปุ่มสำหรับสั่งไม้สำหรับผลิต -->
    <div class="d-flex justify-content-between mb-4">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createIssueModal"><i class="fas fa-cogs"></i> สั่งไม้สำหรับผลิต</button>
    </div>

    <!-- Card สำหรับแสดงรายการงานที่ได้สั่งเบิกไม้ไปแล้ว -->
    <div class="row">
        <?php while($row = $result->fetch_assoc()) { ?>
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <strong><?php echo $row['job_id']; ?></strong> - <?php echo $row['customer_name']; ?>
                    </div>
                    <div class="card-body">
                        <p><strong>Product Code:</strong> <?php echo $row['prod_code']; ?></p>
                        <p><strong>Product Part No:</strong> <?php echo $row['prod_partno']; ?></p>
                        <p><strong>จำนวน:</strong> <?php echo $row['quantity']; ?></p>
                        <p><strong>วันที่สร้าง:</strong> <?php echo $row['creation_date']; ?></p>
                        <p><strong>วันที่ต้องการรับไม้:</strong> <?php echo date('Y-m-d', strtotime($row['want_receive'])); ?></p>
                        <p><strong>วันที่เบิก:</strong> <?php echo $row['issue_date']; ?></p>
                        <p><strong>ผู้เบิก:</strong> <?php echo $row['issued_by']; ?></p>
                        <p><strong>สถานะ:</strong> <?php echo $row['issue_status']; ?></p>
                        <a href="generate_issued_pdf.php?issue_id=<?php echo $row['issue_id']; ?>" class="btn btn-info btn-sm" target="_blank">
                            <i class="fas fa-file-pdf ml-auto"></i> PDF
                        </a>
                    </div>

                    <div class="card-footer">
                        <?php if ($row['issue_status'] == "รอยืนยันงาน") { ?>
                            <!-- ปุ่มยืนยันการสั่งไม้โดยไม่ต้องเรียก modal -->
                            <form action="update_issue_status_confirm.php" method="post" style="display: inline;">
                                <input type="hidden" name="issue_id" value="<?php echo $row['issue_id']; ?>">
                                <input type="hidden" name="new_status" value="สั่งไม้">
                                <button type="submit" name="confirm" class="btn btn-primary btn-sm">
                                    <i class="fas fa-edit"></i> ยืนยันการสั่งไม้
                                </button>
                            </form>
                        <?php } ?>
                        <?php if ($row['issue_status'] != "ปิดสำเร็จ") { ?>
                            <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editModal_<?php echo $row['issue_id']; ?>">
                                <i class="fas fa-edit"></i> แก้ไข
                            </button>
                        <?php } ?>
                        <?php if ($row['issue_status'] != "รอยืนยันการสั่งจ่าย" && $row['issue_status'] != "สั่งจ่ายแล้ว" && $row['issue_status'] != "รอยืนยันงาน" && $row['issue_status'] != "สั่งไม้" && $row['issue_status'] != "กำลังเตรียมไม้" && $row['issue_status'] != "รอเบิก" && $row['issue_status'] != "ยกเลิก" && $row['issue_status'] != "ปิดสำเร็จ") { ?>
                            <a href="repair_issue.php?job_code=<?php echo $row['job_id']; ?>" class="btn btn-success btn-sm">
                                <i class="fas fa-tools"></i> เบิกซ่อม
                            </a>
                        <?php } else { ?>
                        <?php } ?>

                        <?php if ($row['issue_status'] != "รอยืนยันการสั่งจ่าย" && $row['issue_status'] != "สั่งจ่ายแล้ว" && $row['issue_status'] != "กำลังเตรียมไม้" && $row['issue_status'] != "รอเบิก" && $row['issue_status'] != "เบิกแล้ว" && $row['issue_status'] != "ยกเลิก" && $row['issue_status'] != "ปิดสำเร็จ") { ?>
                            <a href="delete_issue_main.php?issue_id=<?php echo $row['issue_id']; ?>" 
                            class="btn btn-danger btn-sm" onclick="return confirm('คุณแน่ใจหรือไม่ว่าจะลบข้อมูลนี้?');">
                                <i class="fas fa-trash"></i> ลบข้อมูล
                            </a>
                        <?php } else { ?>
                            <!-- <a href="#" class="btn btn-danger btn-sm">
                                <i class="fas fa-trash"></i> ลบข้อมูล
                            </a> -->
                        <?php } ?>
                    </div>
                </div>
            </div>

        <!-- Modal สำหรับการแก้ไข -->
        <div class="modal fade" id="editModal_<?php echo $row['issue_id']; ?>" tabindex="-1" aria-labelledby="editModalLabel_<?php echo $row['issue_id']; ?>" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="update_issue.php" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editModalLabel_<?php echo $row['issue_id']; ?>">แก้ไขใบเบิกไม้</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="issue_id" value="<?php echo $row['issue_id']; ?>">

                            <!-- เพิ่มฟิลด์สำหรับ job_id -->
                            <div class="mb-3">
                                <label for="job_id" class="form-label">หมายเลข JOB</label>
                                <input type="text" class="form-control" name="job_id" value="<?php echo $row['job_id']; ?>" <?php echo ($row['issue_status'] != "สั่งไม้" && $row['issue_status'] != "รอยืนยันงาน") ? 'readonly' : ''; ?> required>
                            </div>

                            <!-- จำนวน -->
                            <div class="mb-3">
                                <label for="quantity" class="form-label">จำนวน</label>
                                <!-- <input type="number" class="form-control" name="quantity" value="<?php echo $row['quantity']; ?>" <?php echo ($row['issue_status'] != "สั่งไม้" && $row['issue_status'] != "รอยืนยันงาน") ? 'readonly' : ''; ?> required> -->
                                <input type="number" class="form-control" name="quantity" value="<?php echo $row['quantity']; ?>" <?php echo ($row['issue_status'] != "สั่งไม้" && $row['issue_status'] != "รอยืนยันงาน") ? 'readonly' : ''; ?> required>

                            </div>

                            <!-- วันที่ต้องการรับไม้ -->
                            <div class="mb-3">
                                <label for="want_receive" class="form-label">วันที่ต้องการรับไม้</label>
                                <input type="date" class="form-control" name="want_receive" value="<?php echo date('Y-m-d', strtotime($row['want_receive'])); ?>" <?php echo ($row['issue_status'] != "สั่งไม้" && $row['issue_status'] != "รอยืนยันงาน") ? 'readonly' : ''; ?> required>
                            </div>

                            <!-- Remark -->
                            <div class="mb-3">
                                <label for="remark" class="form-label">หมายเหตุ</label>
                                <input type="text" class="form-control" id="remark" name="remark" maxlength="100" placeholder="กรอกหมายเหตุ (สูงสุด 100 ตัวอักษร)" value="<?php echo $row['remark']; ?>">
                            </div>

                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                            <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>



        <?php } ?>
    </div>

    <!-- Pagination -->
    <nav>
        <ul class="pagination justify-content-center">
            <!-- ปุ่ม Previous -->
            <li class="page-item <?php if($current_page == 1) echo 'disabled'; ?>">
                <a class="page-link" href="?page=<?php echo $current_page - 1 . $search_query; ?>" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
            <?php 
            if($total_pages <= 5) {
                // ถ้าจำนวนหน้าน้อยกว่าหรือเท่ากับ 5 ให้แสดงหน้าทั้งหมด
                for ($i = 1; $i <= $total_pages; $i++) { ?>
                    <li class="page-item <?php if($current_page == $i) echo 'active'; ?>">
                        <a class="page-link" href="?page=<?php echo $i . $search_query; ?>"><?php echo $i; ?></a>
                    </li>
                <?php }
            } else {
                // แสดงหน้าที่ 1 ถึง 5
                for ($i = 1; $i <= 5; $i++) { ?>
                    <li class="page-item <?php if($current_page == $i) echo 'active'; ?>">
                        <a class="page-link" href="?page=<?php echo $i . $search_query; ?>"><?php echo $i; ?></a>
                    </li>
                <?php } ?>
                <!-- แสดง Ellipsis -->
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
                <!-- แสดงหน้าสุดท้าย -->
                <li class="page-item <?php if($current_page == $total_pages) echo 'active'; ?>">
                    <a class="page-link" href="?page=<?php echo $total_pages . $search_query; ?>"><?php echo $total_pages; ?></a>
                </li>
            <?php } ?>
            <!-- ปุ่ม Next -->
            <li class="page-item <?php if($current_page == $total_pages) echo 'disabled'; ?>">
                <a class="page-link" href="?page=<?php echo $current_page + 1 . $search_query; ?>" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        </ul>
    </nav>



</div>

<!-- Modal สำหรับการสร้างใบเบิกไม้ -->
<div class="modal fade" id="createIssueModal" tabindex="-1" aria-labelledby="createIssueModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="create_issue.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="createIssueModalLabel">สร้างใบเบิกไม้</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">

                    <!-- ประเภทงาน -->
                    <div class="mb-3">
                        <label for="job_type" class="form-label">ประเภทงาน</label>
                        <select class="form-select" id="job_type" name="job_type" required>
                            <option value="">-- โปรดเลือกประเภทงาน --</option>
                            <option value="งานหมวด A">งานหมวด A</option>
                            <option value="งานทั่วไป">งานทั่วไป</option>
                            <option value="งานไม้ PACK">งานไม้ PACK</option>
                            <option value="งานพาเลทไม้อัด">งานพาเลทไม้อัด</option>
                            <option value="PALLET RETURN">PALLET RETURN</option>
                            <option value="งานเคลม">งานเคลม</option>
                            <option value="งานภายใน">งานภายใน</option>
                        </select>
                    </div>

                    <!-- หมายเลข JOB ที่ผู้ใช้กรอกเอง -->
                    <div class="mb-3">
                        <label for="job_id" class="form-label">หมายเลข JOB</label>
                        <input type="text" class="form-control" id="job_id" name="job_id" placeholder="กรอกหมายเลข JOB" required>
                    </div>

                    

                    <!-- Product Code -->
                    <div class="mb-3">
                        <label for="product_code" class="form-label">CUSTOMER | SIZE | PRODUCT CODE | PART NO.</label>
                        <input type="text" class="form-control" id="product_code" name="product_code" placeholder="ค้นหา Product Code" required autocomplete="off">
                        <input type="hidden" id="prod_id" name="prod_id"> <!-- เก็บค่า prod_id ที่จะส่งไป -->
                        <input type="hidden" id="prod_code" name="prod_code"> <!-- เก็บค่า prod_id ที่จะส่งไป -->
                        <ul id="product_list" class="list-group" style="display: none; position: absolute; background: white; width: 100%; z-index: 100;"></ul>
                    </div>

                    <!-- จำนวน -->
                    <div class="mb-3">
                        <label for="quantity" class="form-label">จำนวน</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" required>
                    </div>

                    <!-- สูญเสียไม้ (%) -->
                    <div class="mb-3">
                        <label for="wood_wastage" class="form-label">สูญเสียไม้ (%)</label>
                        <input type="number" class="form-control" id="wood_wastage" name="wood_wastage" min="0" max="100" value="0" required>
                    </div>

                    <!-- ประเภทไม้ -->
                    <div class="mb-3">
                        <label for="wood_type" class="form-label">ประเภทไม้</label>
                        <select class="form-select" id="wood_type" name="wood_type" required>
                            <option value="NONFSC">NONFSC</option>
                            <option value="FSCMIX">FSCMIX</option>
                            <option value="FSC100">FSC100</option>
                        </select>
                    </div>

                    <!-- วันที่ต้องการรับไม้ -->
                    <div class="mb-3">
                        <label for="want_receive" class="form-label">วันที่ต้องการรับไม้</label>
                        <input type="date" class="form-control" id="want_receive" name="want_receive" required>
                    </div>

                    <!-- Remark -->
                    <div class="mb-3">
                        <label for="remark" class="form-label">หมายเหตุ</label>
                        <input type="text" class="form-control" id="remark" name="remark" maxlength="100" placeholder="กรอกหมายเหตุ (สูงสุด 100 ตัวอักษร)">
                    </div>

                    <input type="hidden" id="issue_type" name="issue_type" value="ใบเบิกใช้">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึกใบเบิก</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- รวม Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
<script>
// JavaScript สำหรับค้นหา Product Code โดยใช้ AJAX
document.getElementById('product_code').addEventListener('keyup', function() {
    let searchValue = this.value;
    
    // ตรวจสอบว่าผู้ใช้พิมพ์ 2 ตัวขึ้นไป
    if (searchValue.length >= 2) {
        // ส่งคำขอไปยัง PHP ด้วย AJAX
        fetch('search_product_code.php?search=' + searchValue)
        .then(response => response.json())
        .then(data => {
            let productList = document.getElementById('product_list');
            productList.innerHTML = '';
            if (data.length > 0) {
                // แสดงรายการผลลัพธ์
                productList.style.display = 'block';
                data.forEach(product => {
                    let li = document.createElement('li');
                    li.classList.add('list-group-item');
                    
                    // แสดงผลเป็น code_cus_size | prod_code | prod_partno
                    li.textContent = `${product.code_cus_size} | ${product.prod_code} | ${product.prod_partno}`;
                    
                    li.onclick = function() {
                        // เมื่อคลิกให้แสดงผลในช่อง input
                        document.getElementById('product_code').value = `${product.code_cus_size} | ${product.prod_code} | ${product.prod_partno}`;
                        document.getElementById('prod_code').value = `${product.prod_code}`;
                        // ส่ง prod_id ไปเก็บใน input ซ่อน
                        document.getElementById('prod_id').value = `${product.prod_id}`;
                        productList.style.display = 'none';
                    };
                    productList.appendChild(li);
                });
            } else {
                productList.style.display = 'none';
            }
        });
    } else {
        document.getElementById('product_list').style.display = 'none';
    }

});

</script>

</body>
</html>
