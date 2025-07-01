<?php
// เริ่มต้น session และเชื่อมต่อฐานข้อมูล
session_start();
date_default_timezone_set('Asia/Bangkok'); // ตั้งค่าเวลาประเทศไทย
include 'config_db.php';  // เชื่อมต่อไฟล์ฐานข้อมูล

// การตั้งค่าหน้า Pagination
$limit = 100; // จำนวนรายการต่อหน้า
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// ตรวจสอบการกรองข้อมูลจากฟอร์ม
$whereClauses = [];
if (!empty($_GET['search_job_code'])) {
    $whereClauses[] = "repair_id LIKE '%" . $conn->real_escape_string($_GET['search_job_code']) . "%'";
}
if (!empty($_GET['search_status'])) {
    $whereClauses[] = "status = '" . $conn->real_escape_string($_GET['search_status']) . "'";
}
if (!empty($_GET['search_date_from']) && !empty($_GET['search_date_to'])) {
    $whereClauses[] = "created_at BETWEEN '" . $conn->real_escape_string($_GET['search_date_from']) . "' AND '" . $conn->real_escape_string($_GET['search_date_to']) . "'";
}

// สร้างคำสั่ง SQL นับจำนวนทั้งหมด
$countSql = "SELECT COUNT(*) as total FROM repair_issue";
if (count($whereClauses) > 0) {
    $countSql .= " WHERE " . implode(' AND ', $whereClauses);
}

$countResult = $conn->query($countSql);
$totalRows = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

$whereClauses[] = "NOT EXISTS (
    SELECT 1 FROM wood_issue wi
    WHERE wi.job_id = repair_issue.job_id
      AND wi.job_type IN ('งานพาเลทไม้อัด', 'รอยืนยันงาน')
)";

$sql = "SELECT * FROM repair_issue";
if (count($whereClauses) > 0) {
    $sql .= " WHERE " . implode(' AND ', $whereClauses);
}
$sql .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";


// ดึงข้อมูลจากฐานข้อมูล
$result = $conn->query($sql);

// การอัปเดตสถานะและการบันทึกข้อมูลจะใช้ AJAX ในหน้าเดียว
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['id']) && isset($_POST['status'])) {
        $id = $_POST['id'];
        $status = $_POST['status'];

        $sql = $status === 'ยกเลิก' ?
            "UPDATE repair_issue SET status = 'ยกเลิก' WHERE repair_id = '$id'" :
            "UPDATE repair_issue SET status = '$status' WHERE repair_id = '$id'";

        if ($conn->query($sql) === TRUE) {
            echo "Record updated successfully";
        } else {
            echo "Error updating record: " . $conn->error;
        }
    }

    if (isset($_POST['issued_by']) && isset($_POST['id'])) {
        $id = $_POST['id'];
        $issued_by = $_POST['issued_by'];
        $issue_date = date('Y-m-d H:i:s');
        $sql = "UPDATE repair_issue SET issued_by = '$issued_by', issue_date = '$issue_date', status = 'เบิกแล้ว' WHERE repair_id = '$id'";

        if ($conn->query($sql) === TRUE) {
            echo "Record updated successfully";
        } else {
            echo "Error updating record: " . $conn->error;
        }
    }
    exit;
    
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดเตรียมไม้สำหรับงานหลัก</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> <!-- เชื่อมโยง jQuery -->
</head>
<body>

<!-- Navbar -->
<?php include 'navbar.php';?>

<div class="container mt-5">
    <h1>จัดเตรียมไม้สำหรับงานซ่อม</h1>

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
        </div>
        <button type="submit" class="btn btn-primary">ค้นหา</button>
        <a href="wip_manage_issue_repair.php" class="btn btn-secondary">รีเซ็ต</a>
    </form>

    <br>

    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>หมายเลขงานซ่อม</th>
                <th>หมายเลขงานหลัก</th>

                <th>วันที่ออกเอกสาร</th>
                <th>วันที่ต้องการรับไม้</th>
                <th>วันที่เบิกไม้</th>
                
                <th>สถานะ</th>
                <th>ดำเนินการ</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo $row['repair_id']; ?></td>
                    <td><?php echo $row['job_id']; ?></td>

                    <td><?php echo $row['created_at']; ?></td>
                    <td><?php echo $row['want_receive']; ?></td>
                    <td><?php echo $row['issue_date']; ?></td>

                    <td><?php echo $row['status']; ?></td>
                    <td>
                    <?php if ($row['status'] == 'สั่งไม้') { ?>
                        <button class="btn btn-success btn-sm" onclick="updateStatusRepairIssue('<?php echo $row['repair_id']; ?>', 'กำลังเตรียมไม้', 'repair_issue')">ยืนยัน</button>
                    <?php } elseif ($row['status'] == 'กำลังเตรียมไม้') { ?>
                        <button class="btn btn-warning btn-sm" onclick="updateStatusRepairIssue('<?php echo $row['repair_id']; ?>', 'รอเบิก', 'repair_issue')">เตรียมไม้เรียบร้อย</button>
                    <?php } elseif ($row['status'] == 'รอเบิก') { ?>
                        <button class="btn btn-info btn-sm" onclick="showPopupRepairIssue('<?php echo $row['repair_id']; ?>', 'repair_issue')">เบิกออก</button>
                    <?php } ?>

                    <?php if ($row['status'] != 'เบิกแล้ว' && $row['status'] != 'ยกเลิก') { ?>
                        <button class="btn btn-danger btn-sm" onclick="confirmCancelRepairIssue('<?php echo $row['repair_id']; ?>')">ยกเลิก</button>
                    <?php } else { ?>
                        <button class="btn btn-danger btn-sm" disabled>ยกเลิก</button>
                    <?php } ?>

                    <a href="generate_repair_issue_pdf.php?repair_id=<?php echo $row['repair_id']; ?>" class="btn btn-info btn-sm" target="_blank">PDF</a>

                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<!-- Popup สำหรับการเบิกออก -->
<div id="popupModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">กรอกชื่อผู้เบิก</h5>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bi bi-x-circle"></i> ปิด
                </button>
            </div>
            <div class="modal-body">
                <!-- Dropdown List สำหรับชื่อผู้เบิก -->
                <select id="issued_by" class="form-select" aria-label="กรอกชื่อผู้เบิก">
                    <option value="" selected>เลือกชื่อผู้เบิก</option>
                    <option value="สุภาพร">สุภาพร</option>
                    <option value="รุ่งทิวา">รุ่งทิวา</option>
                    <option value="สาม">สาม</option>
                    <option value="เสงี่ยม">เสงี่ยม</option>
                    <option value="กัญญา">กัญญา</option>
                    <!-- เพิ่มรายการชื่อผู้เบิกตามที่ต้องการ -->
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bi bi-x-circle"></i> ยกเลิก
                </button>
                <button type="button" class="btn btn-primary" onclick="checkAndSubmitRepairIssue()">ยืนยันการเบิก</button>
            </div>
        </div>
    </div>
</div>


<!-- Confirmation Modal สำหรับการยกเลิก -->
<div id="confirmCancelModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">ยืนยันการยกเลิก</h5>
                <!-- ปุ่มปิดที่มีไอคอน (Bootstrap 5) -->
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bi bi-x-circle"></i> ปิด
                </button>
            </div>
            <div class="modal-body">
                <p>คุณแน่ใจหรือไม่ว่าต้องการยกเลิกใบงานซ่อมนี้?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bi bi-x-circle"></i> ยกเลิก
                </button>
                <button type="button" class="btn btn-danger" onclick="cancelRepairIssue()">ยืนยันการยกเลิก</button>
            </div>
        </div>
    </div>
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
// ฟังก์ชันสำหรับอัปเดตสถานะ
function updateStatusRepairIssue(id, status, table) {
    $.ajax({
        url: '',  // ใช้ในหน้านี้
        method: 'POST',
        data: { id, status, table },  // ส่งค่า id, status และ table ไปยัง PHP
        success: function(response) {
            console.log('Update Response:', response);  // ดูผลลัพธ์ใน console
            location.reload();  // รีเฟรชหน้าเพื่ออัปเดตข้อมูล
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);  // แสดงข้อผิดพลาดใน console
        }
    });
}

// ฟังก์ชันสำหรับเปิด Popup เพื่อกรอกชื่อผู้เบิก
function showPopupRepairIssue(id, table) {
    console.log("showPopupRepairIssue called with:", id, table);
    $('#popupModal').modal('show');  // เปิด modal
    $('#popupModal').data('id', id); // เก็บ ID ลงใน data
    $('#popupModal').data('table', table); // เก็บ table ลงใน data
}


// ฟังก์ชันสำหรับตรวจสอบค่าใน dropdown ก่อนที่จะยืนยันการเบิก
function checkAndSubmitRepairIssue() {
    var issued_by = $('#issued_by').val();  // ดึงค่าจาก dropdown
    // ตรวจสอบว่าผู้ใช้เลือกชื่อผู้เบิกหรือยัง
    if (issued_by === "") {
        alert("กรุณาเลือกชื่อผู้เบิก");
    } else {
        // หากเลือกแล้ว ให้เรียกใช้ฟังก์ชัน submitIssue()
        submitIssueRepairIssue(issued_by);
    }
}


// ฟังก์ชันสำหรับยืนยันการเบิก
function submitIssueRepairIssue() {
    var issued_by = $('#issued_by').val();  // ดึงค่าจาก dropdown
    var id = $('#popupModal').data('id');    // ดึงค่า id จาก modal
    var table = $('#popupModal').data('table');  // ดึงค่า table จาก modal

    // ตรวจสอบข้อมูลก่อนส่ง
    console.log("Data to be sent:", {
        id: id,
        table: table,
        issued_by: issued_by
    });

    $.ajax({
        url: '',  // ทำงานในหน้านี้
        method: 'POST',
        data: {
            id: id,
            table: table,
            issued_by: issued_by
        },
        success: function(response) {
            console.log("Server Response:", response); // ตรวจสอบการตอบกลับจากเซิร์ฟเวอร์
            location.reload();  // รีเฟรชหน้า
        },
        error: function(xhr, status, error) {
            console.error("AJAX Error:", error); // ตรวจสอบหากมีข้อผิดพลาด
        }
    });
}


// function submitIssueRepairIssue() {
//     var issued_by = $('#issued_by').val();  // ดึงค่าจาก dropdown
//     var id = $('#popupModal').data('id');    // ดึงค่า id จาก modal
//     var table = $('#popupModal').data('table');  // ดึงค่า table จาก modal

//     $.ajax({
//         url: '',  // ทำงานในหน้านี้
//         method: 'POST',
//         data: {
//             id: id,
//             table: table,
//             issued_by: issued_by
//         },
//         success: function(response) {
//             location.reload();  // รีเฟรชหน้า
//         }
//     });
// }

// ฟังก์ชันสำหรับยืนยันการยกเลิก
function confirmCancelRepairIssue(id) {
    // เปิด Modal และเก็บค่า repair_id ไว้ใน Modal
    $('#confirmCancelModal').modal('show');
    $('#confirmCancelModal').data('id', id);
}

// ฟังก์ชันสำหรับดำเนินการยกเลิกเมื่อกดยืนยันใน Modal
function cancelRepairIssue() {
    // ดึงค่า repair_id จาก Modal
    var id = $('#confirmCancelModal').data('id');

    // ส่งข้อมูลผ่าน AJAX เพื่ออัปเดตสถานะเป็น "ยกเลิก"
    $.ajax({
        url: '',  // ใช้งานในหน้านี้
        method: 'POST',
        data: {
            id: id,
            status: 'ยกเลิก',
            table: 'repair_issue'  // กำหนดตารางที่ต้องการอัปเดต
        },
        success: function(response) {
            console.log("Cancel Success:", response); // แสดงข้อความตอบกลับจากเซิร์ฟเวอร์
            location.reload();  // รีเฟรชหน้าเพื่อตรวจสอบการเปลี่ยนแปลง
        },
        error: function(xhr, status, error) {
            console.error("Cancel Error:", error); // แสดงข้อความผิดพลาดหาก AJAX ล้มเหลว
        }
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

</body>
</html>
