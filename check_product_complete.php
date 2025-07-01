<?php
// เริ่ม session เพื่อเก็บข้อความ
session_start();
date_default_timezone_set('Asia/Bangkok');

// รวมไฟล์การเชื่อมต่อฐานข้อมูลและฟังก์ชันที่เกี่ยวข้อง
include('config_db.php');
include('job_complete_functions.php'); // รวมไฟล์ฟังก์ชัน

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmtUser = $conn->prepare("SELECT thainame FROM prod_user WHERE user_id = ?");
    $stmtUser->bind_param("s", $user_id);
    $stmtUser->execute();
    $stmtUser->bind_result($thainame);
    if ($stmtUser->fetch()) {
        $record_by = $thainame;
    }else{
        $record_by = 'ไม่สามารถดึงข้อมูลได้';
    }
    $stmtUser->close();
}

// รับค่า job_id จาก URL หรือ POST
$job_id = "";
$data = [];

if (isset($_GET['job_id'])) {
    // รับคำขอจาก URL (GET) เช่น check_product_complete.php?job_id=123
    $job_id = $_GET['job_id'];
} elseif (isset($_POST['job_id'])) {
    // รับคำขอจากฟอร์ม (POST) หากมีการส่งค่ามาจากฟอร์ม
    $job_id = $_POST['job_id'];
}

// ตรวจสอบว่าได้รับค่า job_id หรือไม่
if (!empty($job_id)) {
    // ค้นหาข้อมูลที่เกี่ยวข้องกับ job_id
    $query = "SELECT wi.job_id, pl.prod_code, wi.quantity, wi.creation_date, wi.issue_date, wi.issued_by, wi.issue_status, pl.code_cus_size, pl.prod_id 
    FROM wood_issue wi
    JOIN prod_list pl ON wi.prod_id = pl.prod_id
    WHERE wi.job_id = '$job_id' AND wi.issue_status = 'เบิกแล้ว'";
    $result = mysqli_query($conn, $query);
    
    // ถ้ามีข้อมูล
    if (mysqli_num_rows($result) > 0) {
        $data = mysqli_fetch_assoc($result);
    } else {
        $data['error'] = "ไม่พบข้อมูลสำหรับ Job ID นี้";
    }
} else {
    $data['error'] = "กรุณาระบุ Job ID สำหรับการค้นหา";
}

// ตรวจสอบการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
    // ดึง job_id จากฟอร์ม/URL
    $job_id = $_POST['job_id'];

    // 5.0 เช็คสถานะในตาราง repair_issue
    $checkStatusSql = "
        SELECT status 
        FROM repair_issue 
        WHERE job_id = ? 
          AND status IN ('สั่งไม้','กำลังเตรียมไม้','รอเบิก')
        LIMIT 1
    ";
    $stmtCheck = $conn->prepare($checkStatusSql);
    $stmtCheck->bind_param("s", $job_id);
    $stmtCheck->execute();
    $stmtCheck->store_result();
    if ($stmtCheck->num_rows > 0) {
        // ถ้าพบสถานะที่ขัดข้อง ให้ดึงค่าสถานะมาแจ้งเตือน
        $stmtCheck->bind_result($pendingStatus);
        $stmtCheck->fetch();
        $_SESSION['message'] = "ไม่สามารถบันทึกได้ เนื่องจากงานนี้อยู่ในสถานะ: {$pendingStatus}";
        header("Location: check_product_complete.php?job_id={$job_id}");
        exit;
    }
    $stmtCheck->close();

    // 5.1 ตรวจว่า qty ตรวจรับไม่เกิน qty ที่เบิกได้
    $prod_complete_qty = $_POST['prod_complete_qty'];
    if ($prod_complete_qty > $data['quantity']) {
        $_SESSION['message'] = "จำนวนที่ตรวจรับไม่สามารถมากกว่าจำนวนที่เบิกได้";
        header("Location: check_product_complete.php?job_id=$job_id");
        exit;
    }

    $date_complete = date('Y-m-d H:i:s');
    // รับค่าวันที่อย่างเดียว และแปลงเป็น datetime เวลาเริ่มต้น (00:00:00)
    $date_receive  = date('Y-m-d H:i:s', strtotime($_POST['date_receive'] . ' 00:00:00'));

    

    $production_wage_price = calculateProductionWagePrice($conn, $data['prod_id'], $prod_complete_qty);
    $assembly_point = $_POST['assembly_point'];

    if ($production_wage_price === false && $assembly_point != "NON-AP"  && $assembly_point != "CLAIM" && $assembly_point != "Z-รายวัน" && $assembly_point != "KMCT-SK-C") {
        $_SESSION['message'] = "ไม่พบข้อมูลราคาสำหรับสินค้าในตาราง product_price";
        header("Location: check_product_complete.php?job_id=$job_id");
        exit;
    }

    // if (true) {
    //     $_SESSION['message'] = "ไม่พบข้อมูลราคาสำหรับสินค้าในตาราง product_price";
    //     header("Location: check_product_complete.php?job_id=$job_id");
    //     exit;
    // }

    // 5.3 บันทึกข้อมูลลงตาราง jobs_complete
    // $insert_result = insertJobComplete(
    //     $conn,
    //     $job_id,
    //     $prod_complete_qty,
    //     $receive_by,
    //     $send_by,
    //     $assembly_point,
    //     $reason,
    //     $date_complete,
    //     $record_by,
    //     $date_receive
    // );

    $insert_result = insertJobComplete(
        $conn,
        $job_id,
        $_POST['prod_complete_qty'],
        $_POST['receive_by'],
        $_POST['send_by'],
        $_POST['assembly_point'],
        $_POST['reason'],
        $date_complete,
        $record_by,
        $date_receive
    );

    if ($insert_result) {
        // 5.4 อัปเดตสถานะในตาราง wood_issue เป็น ปิดสำเร็จ
        $update_query = "
            UPDATE wood_issue
            SET issue_status = 'ปิดสำเร็จ'
            WHERE job_id = ?
        ";
        $stmtUpdate = $conn->prepare($update_query);
        $stmtUpdate->bind_param("s", $job_id);
        if ($stmtUpdate->execute()) {
            header("Location: product_complete_list.php");
            exit;
        } else {
            $_SESSION['message'] = "เกิดข้อผิดพลาดในการอัปเดตสถานะ";
            header("Location: product_complete_list.php");
            exit;
        }
    } else {
        $_SESSION['message'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
        header("Location: product_complete_list.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตรวจรับงานที่เสร็จแล้ว</title>
    <!-- ลิงก์ไปยัง Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        // ฟังก์ชันการยืนยันการบันทึกข้อมูล
        function confirmSubmit() {
            return confirm('คุณต้องการบันทึกข้อมูลการตรวจรับใช่หรือไม่?');
        }
    </script>
</head>
<body>
<!-- Navbar -->
<?php include('navbar.php'); ?>

    <div class="container my-5">
        <h2>ตรวจรับงานที่เสร็จแล้ว</h2>
        <br><br>

        <!-- แสดงข้อความจาก session -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-info" role="alert">
                <?= $_SESSION['message']; ?>
            </div>
            <?php unset($_SESSION['message']); // ลบข้อความหลังจากแสดงแล้ว ?>
        <?php endif; ?>

        <!-- แสดงข้อมูลที่ค้นหาจากฐานข้อมูล -->
        <?php if (isset($data['error'])): ?>
            <div class="alert alert-info" role="alert">
                <?= $data['error']; ?>
            </div>
        <?php elseif (!empty($data)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title">รายละเอียดของ Job ID: <span class="text-primary"><?= $data['job_id']; ?></span></h5>
                </div>
                <div class="card-body">
                    <p><strong>PRODUCT CODE:</strong> <?= $data['prod_code']; ?></p>
                    <p><strong>CODE CUS SIZE:</strong> <?= $data['code_cus_size']; ?></p>
                    <p><strong>QTY:</strong> <?= $data['quantity']; ?></p>
                    <p><strong>วันที่สร้างคำสั่งเบิก:</strong> <?= date("d-m-Y H:i:s", strtotime($data['creation_date'])); ?></p>
                    <p><strong>วันที่เบิกไม้จากคลัง WIP:</strong> <?= date("d-m-Y H:i:s", strtotime($data['issue_date'])); ?></p>
                    <p><strong>ผู้เบิก:</strong> <?= $data['issued_by']; ?></p>
                    <p><strong>สถานะการเบิก:</strong> <?= $data['issue_status']; ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="container my-5">
        <h2>กรอกข้อมูลการตรวจรับ</h2>
        <form action="" method="POST" onsubmit="return confirmSubmit()">
            <div class="mb-3">
                <label for="prod_complete_qty" class="form-label">จำนวนที่ตรวจรับ</label>
                <input type="number" name="prod_complete_qty" class="form-control" required>
            </div>

            <!-- ฟอร์ม dropdown สำหรับผู้รับข้อมูล -->
            <div class="mb-3">
                <label for="receive_by" class="form-label">ตรวจรับโดย</label>
                <select name="receive_by" class="form-control" required>
                    <option value="" selected>เลือกผู้ตรวจรับงาน</option>
                    <option value="ธานี">ธานี</option>
                    <option value="พรพิมล">พรพิมล</option>
                    <option value="นิสุดา">นิสุดา</option>
                    <option value="ใหม่">ใหม่</option>
                    <option value="พันทิพา">พันทิพา</option>
                    <option value="ศิริพร">ศิริพร</option>
                    <option value="พันธ์ทิพา">พันธ์ทิพา</option>
                    <option value="อาทิตย์">อาทิตย์</option>
                    <option value="สวัสดิ์">สวัสดิ์</option>
                    <option value="รัชนี">รัชนี</option>
                    <option value="พงษ์ธวัช">พงษ์ธวัช</option>
                    <option value="อิงอร">อิงอร</option>
                    <option value="ศศิธร">ศศิธร</option>
                    <option value="ไพลิน">ไพลิน</option>
                    <option value="ณัฐชนากรณ์">ณัฐชนากรณ์</option>
                </select>
            </div>

            <!-- ฟอร์ม dropdown ผู้ส่งงาน -->
            <div class="mb-3">
                <label for="send_by" class="form-label">ผู้ส่งงาน</label>
                <select name="send_by" class="form-control" required>
                    <option value="" selected>เลือกผู้ส่งงาน</option>
                    <option value="สุภาพร">สุภาพร</option>
                    <option value="รุ่งทิวา">รุ่งทิวา</option>
                    <option value="สาม">สาม</option>
                    <option value="เสงี่ยม">เสงี่ยม</option>
                    <option value="กัญญา">กัญญา</option>
                    <option value="ณัฐชนากรณ์">ณัฐชนากรณ์</option>
                </select>
            </div>

            <!-- ฟอร์ม dropdown สำหรับเลือกจุดประกอบ -->
            <div class="mb-3">
                <label for="assembly_point" class="form-label">จุดประกอบ</label>
                <select name="assembly_point" class="form-control" required>
                    <option value="" selected>เลือกจุดประกอบงาน</option>
                    <option value="A-SK">1. A-SK</option>
                    <option value="B-PCN">2. B-PCN</option>
                    <option value="C-PPK">3. C-PPK</option>
                    <option value="D-PPK">4. D-PPK</option>
                    <option value="E-SK">5. E-SK</option>
                    <option value="F-SK">6. F-SK</option>
                    <option value="G-SK">7. G-SK</option>
                    <option value="H-SK">8. H-SK</option>
                    <option value="I-PCN">9. I-PCN</option>
                    <option value="K-PPK">10. K-PPK</option>
                    <option value="N-SK">11. N-SK</option>
                    <option value="P-PPK">12. P-PPK</option>
                    <option value="W-PPK">13. W-PPK</option>
                    <option value="Y-PPK">14. Y-PPK</option>
                    <option value="SK-D(PPK)">15. SK-D(PPK)</option>
                    <option value="SK-G(PPK)">16. SK-G(PPK)</option>
                    <option value="Z-รายวัน">17. Z-รายวัน</option>
                    <option value="KMCT-SK-C">18. KMCT-SK-C(รายวัน)</option>            
                    <option value="NON-AP">19. NON-AP(ไม่มีจุดประกอบ)</option>
                    <option value="CLAIM">20. งานเคลม(ไม่คิดค่าแรง) </option>
                </select>
            </div>

            <div class="mb-3">
                <label for="date_receive" class="form-label">วันที่ส่งงาน (Date Receive)</label>
                <input type="date" 
                    name="date_receive" 
                    id="date_receive" 
                    class="form-control"
                    value="<?= date('Y-m-d'); ?>"  
                    required>
            </div>

            <!-- เพิ่มฟิลด์ textbox สำหรับเก็บ reason -->
            <div class="mb-3">
                <label for="reason" class="form-label">เหตุผล</label>
                <textarea name="reason" id="reason" class="form-control" rows="3" placeholder="ระบุเหตุผล 100 ตัวอักษร"></textarea>
            </div>
            
            <!-- ส่งค่า job_id ผ่าน hidden field -->
            <input type="hidden" name="job_id" value="<?= $job_id; ?>">

            <button type="submit" name="submit" class="btn btn-primary">บันทึกข้อมูลการตรวจรับ</button>
        </form>
    </div>

    <!-- ลิงก์ไปยัง Bootstrap 5 JS และ Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
</body>
</html>
