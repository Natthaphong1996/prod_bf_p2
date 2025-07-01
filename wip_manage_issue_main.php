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
    $whereClauses[] = "job_id LIKE '%" . $conn->real_escape_string($_GET['search_job_code']) . "%'";
}
if (!empty($_GET['search_status'])) {
    $whereClauses[] = "issue_status = '" . $conn->real_escape_string($_GET['search_status']) . "'";
}
if (!empty($_GET['search_date_from']) && !empty($_GET['search_date_to'])) {
    $whereClauses[] = "creation_date BETWEEN '" . $conn->real_escape_string($_GET['search_date_from']) . "' AND '" . $conn->real_escape_string($_GET['search_date_to']) . "'";
}

// สร้างคำสั่ง SQL นับจำนวนทั้งหมด
$countSql = "SELECT COUNT(*) as total FROM wood_issue";
if (count($whereClauses) > 0) {
    $countSql .= " WHERE " . implode(' AND ', $whereClauses);
}

$countResult = $conn->query($countSql);
$totalRows = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

$whereClauses[] = "job_type != 'งานพาเลทไม้อัด'";
$whereClauses[] = "issue_status != 'รอยืนยันงาน'";
$whereClauses[] = "issue_status != 'สั่งจ่ายแล้ว'";

$sql = "SELECT * FROM wood_issue";
if (count($whereClauses) > 0) {
    $sql .= " WHERE " . implode(' AND ', $whereClauses);
}
$sql .= " ORDER BY creation_date DESC LIMIT $limit OFFSET $offset";


// ดึงข้อมูลจากฐานข้อมูล
$result = $conn->query($sql);


// การอัปเดตสถานะและการบันทึกข้อมูลจะใช้ AJAX ในหน้าเดียว
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['id']) && isset($_POST['status'])) {
        $id = $_POST['id'];
        $status = $_POST['status'];
        $table = $_POST['table'];

        if ($table == 'wood_issue') {
            $sql = $status === 'ยกเลิก' ?
                "UPDATE wood_issue SET issue_status = 'ยกเลิก' WHERE issue_id = $id" :
                "UPDATE wood_issue SET issue_status = '$status' WHERE issue_id = $id";
        }

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
        $sql = "UPDATE wood_issue SET issued_by = '$issued_by', issue_date = '$issue_date', issue_status = 'เบิกแล้ว' WHERE issue_id = $id";

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
    <h1>จัดเตรียมไม้สำหรับงานหลัก</h1>

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
                    <option value="">-- เลือกสถานะ --</option>
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
        <a href="wip_manage_issue_main.php" class="btn btn-secondary">รีเซ็ต</a>
    </form>

    <br>

    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>หมายเลข JOB</th>
                <th>ITEM CODE FG</th>
                <th>จำนวน</th>
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
                    <td><?php echo $row['job_id']; ?></td>
                    <td><?php echo $row['product_code']; ?></td>
                    <td><?php echo $row['quantity']; ?></td>

                    <td><?php echo $row['creation_date']; ?></td>
                    <td><?php echo date('Y-m-d', strtotime($row['want_receive'])); ?></td>
                    <td><?php echo $row['issue_date']; ?></td>

                    <td><?php echo $row['issue_status']; ?></td>
                    <td>
                        <?php if ($row['issue_status'] == 'สั่งไม้') { ?>
                            <button class="btn btn-success btn-sm" onclick="updateStatus(<?php echo $row['issue_id']; ?>, 'กำลังเตรียมไม้', 'wood_issue')">ยืนยัน</button>
                        <?php } elseif ($row['issue_status'] == 'กำลังเตรียมไม้') { ?>
                            <button class="btn btn-warning btn-sm" onclick="updateStatus(<?php echo $row['issue_id']; ?>, 'รอเบิก', 'wood_issue')">เตรียมไม้เรียบร้อย</button>
                        <?php } elseif ($row['issue_status'] == 'รอเบิก') { ?>
                            <button class="btn btn-info btn-sm" onclick="showPopup(<?php echo $row['issue_id']; ?>, 'wood_issue')">เบิกออก</button>
                        <?php } ?>

                        <?php if ($row['issue_status'] != 'เบิกแล้ว' && $row['issue_status'] != 'ยกเลิก' && $row['issue_status'] != 'ปิดสำเร็จ') { ?>
                            <button class="btn btn-danger btn-sm" onclick="confirmCancel(<?php echo $row['issue_id']; ?>)">ยกเลิก</button>
                        <?php } else { ?>
                            <button class="btn btn-danger btn-sm" disabled>ยกเลิก</button>
                        <?php } ?>

                        <a href="generate_issued_pdf.php?issue_id=<?php echo $row['issue_id']; ?>" class="btn btn-info btn-sm" target="_blank">PDF</a>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<!-- Popup สำหรับการเบิกออก -->
<div id="popupModal" class="modal" tabindex="-1" role="dialog" data-id="123" data-table="inventory">
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
                <select id="issued_by" class="form-select" aria-label="กรอกชื่อผู้เบิก" placeholder="กรุณากรอกชื่อผู้เบิก">
                    <option value="" selected>เลือกชื่อผู้เบิก</option>
                    <option value="สุภาพร">สุภาพร</option>
<<<<<<< Updated upstream
                    <option value="สมพร">สมพร</option>
=======
                    <option value="รุ่งทิวา">รุ่งทิวา</option>
>>>>>>> Stashed changes
                    <option value="สาม">สาม</option>
                    <option value="เสงี่ยม">เสงี่ยม</option>
                    <option value="กัญญา">กัญญา</option>
                    <option value="อรจิรา">อรจิรา</option>
                    <!-- เพิ่มรายการชื่อผู้เบิกตามที่ต้องการ -->
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bi bi-x-circle"></i> ยกเลิก
                </button>
                <button type="button" class="btn btn-primary" onclick="checkAndSubmitIssue()">ยืนยันการเบิก</button>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal สำหรับการยกเลิก -->
<div id="confirmCancelModal" class="modal" tabindex="-1" role="dialog">
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
                <p>คุณแน่ใจหรือไม่ว่าต้องการยกเลิกใบงานนี้?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bi bi-x-circle"></i> ยกเลิก
                </button>
                <button type="button" class="btn btn-danger" onclick="cancelIssue()">ยืนยันการยกเลิก</button>
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
            // ถ้าจำนวนหน้าน้อยกว่าหรือเท่ากับ 5 ให้แสดงหน้าทั้งหมด
            for ($i = 1; $i <= $totalPages; $i++) { ?>
                <li class="page-item <?php if($page == $i) echo 'active'; ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                </li>
            <?php }
        } else {
            // แสดง 5 หน้าแรก
            for ($i = 1; $i <= 5; $i++) { ?>
                <li class="page-item <?php if($page == $i) echo 'active'; ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                </li>
            <?php } ?>
            <!-- แสดง Ellipsis -->
            <li class="page-item disabled">
                <span class="page-link">...</span>
            </li>
            <!-- แสดงหน้าสุดท้าย -->
            <li class="page-item <?php if($page == $totalPages) echo 'active'; ?>">
                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>"><?php echo $totalPages; ?></a>
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
function updateStatus(id, status, table) {
    $.ajax({
        url: '',  // ทำงานในหน้านี้
        method: 'POST',
        data: {
            id: id,
            status: status,
            table: table
        },
        success: function(response) {
            location.reload();  // รีเฟรชหน้า
        },
        error: function(xhr, status, error) {
            console.log("AJAX Error:", error);
        }
    });
}

// ฟังก์ชันสำหรับเปิด popup
function showPopup(id, table) {
    $('#popupModal').modal('show');
    $('#popupModal').data('id', id);
    $('#popupModal').data('table', table);
}

    // ฟังก์ชันสำหรับตรวจสอบค่าใน dropdown ก่อนที่จะยืนยันการเบิก
    function checkAndSubmitIssue() {
        var issued_by = $('#issued_by').val();  // ดึงค่าจาก dropdown

        // ตรวจสอบว่าผู้ใช้เลือกชื่อผู้เบิกหรือยัง
        if (issued_by === "") {
            alert("กรุณาเลือกชื่อผู้เบิก");
        } else {
            // หากเลือกแล้ว ให้เรียกใช้ฟังก์ชัน submitIssue()
            submitIssue(issued_by);
        }
    }

    // ฟังก์ชันสำหรับยืนยันการเบิก
    function submitIssue(issued_by) {
        var id = $('#popupModal').data('id');    // ดึงค่า id จาก modal
        var table = $('#popupModal').data('table');  // ดึงค่า table จาก modal

        // ส่งข้อมูลไปยังเซิร์ฟเวอร์
        $.ajax({
            url: '',  // ทำงานในหน้านี้
            method: 'POST',
            data: {
                id: id,
                table: table,
                issued_by: issued_by
            },
            success: function(response) {
                location.reload();  // รีเฟรชหน้าเมื่อส่งสำเร็จ
            }
        });
    }

// ฟังก์ชันสำหรับยืนยันการยกเลิก
function confirmCancel(id) {
    $('#confirmCancelModal').modal('show');
    $('#confirmCancelModal').data('id', id);
}

function cancelIssue() {
    var id = $('#confirmCancelModal').data('id');

    $.ajax({
        url: '',  // ทำงานในหน้านี้
        method: 'POST',
        data: {
            id: id,
            status: 'ยกเลิก',
            table: 'wood_issue'
        },
        success: function(response) {
            location.reload();  // รีเฟรชหน้า
        }
    });
}

</script>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

</body>
</html>
