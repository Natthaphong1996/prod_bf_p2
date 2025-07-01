<?php
include('config_db.php');

// ตรวจสอบว่ามีการส่งข้อมูลมาใน URL หรือไม่
if (isset($_GET['job_code'])) {
    $job_code = $_GET['job_code'];  // กำหนดค่า job_code จาก URL
} else {
    // ถ้าไม่มีการส่งค่ามา ก็ต้องกำหนดค่าเริ่มต้นหรือดึงจากฐานข้อมูล
    $job_code = 'ไม่มีข้อมูล';  // กรณีไม่มีการส่งค่ามา
}

// ดึงข้อมูล product_code จาก wood_issue ที่มี job_id ตรงกับ job_code
$sql_product_code = "SELECT prod_id FROM wood_issue WHERE job_id = '$job_code'";  // แก้ไขชื่อ table ตามที่ใช้งาน
$result_product_code = mysqli_query($conn, $sql_product_code);
$product_code = '';
if ($result_product_code->num_rows > 0) {
    $row_product_code = mysqli_fetch_assoc($result_product_code);
    $product_code = $row_product_code['prod_id'];
}

// ดึงข้อมูลจากตาราง bom ที่มี product_code ตรงกับที่ได้มา
$sql_bom = "SELECT parts FROM bom WHERE prod_id = '$product_code'";
$result_bom = mysqli_query($conn, $sql_bom);
$parts_data = [];
if ($result_bom->num_rows > 0) {
    $row_bom = mysqli_fetch_assoc($result_bom);
    $parts_data = json_decode($row_bom['parts'], true);  // แปลงข้อมูล JSON ในฟิลด์ parts
}

// นำ part_id จาก parts มาใช้ค้นหาข้อมูลจาก part_list
$part_list = [];
foreach ($parts_data as $part) {
    $part_id = $part['part_id'];
    $sql_part = "SELECT part_id, part_type, part_code, part_length, part_width, part_thickness, part_m3 
                 FROM part_list WHERE part_id = '$part_id'";
    $result_part = mysqli_query($conn, $sql_part);
    if ($result_part->num_rows > 0) {
        $row_part = mysqli_fetch_assoc($result_part);
        $part_list[] = $row_part;  // เก็บข้อมูล part ที่ได้
    }
}

// ดึงข้อมูลการคืนไม้จาก return_wood_wip
$sql_repair_issue = "SELECT * FROM return_wood_wip WHERE job_id = '$job_code'";
$result_repair_issue = mysqli_query($conn, $sql_repair_issue);

// ตรวจสอบว่ามีข้อมูลการคืนไม้หรือไม่
if ($result_repair_issue->num_rows > 0) {
    // มีข้อมูลการคืนไม้
} else {
    // ไม่มีข้อมูลการคืนไม้สำหรับ Job นี้
    $no_data_message = "ไม่พบข้อมูลการคืนไม้สำหรับ Job ID นี้";
}

// ตรวจสอบการส่งข้อมูลมาในฟอร์มเมื่อทำการ POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // ดึงข้อมูลจากฟอร์ม
    $return_by = $_POST['return_by'];
    $recive_by = $_POST['recive_by'];
    $parts_data = [];
    $total_m3 = 0;

    // ดึงข้อมูลจากส่วนของ Part
    foreach ($_POST['part_id'] as $key => $part_id) {
        $quantity = $_POST['quantity'][$key];
        echo $key;

        // คำนวณ part_totalm3 (Part M3 * Quantity)
        $sql_part = "SELECT part_m3 FROM part_list WHERE part_id = '$part_id'";
        $result_part = mysqli_query($conn, $sql_part);
        $part_m3 = 0;
        if ($result_part->num_rows > 0) {
            $row_part = mysqli_fetch_assoc($result_part);
            $part_m3 = $row_part['part_m3'];
        }

        // คำนวณ total_m3 สำหรับแต่ละ part
        $part_total_m3 = $part_m3 * $quantity;
        $total_m3 += $part_total_m3;  // รวม total_m3

        // เก็บข้อมูลใน array
        $parts_data[] = [
            'part_id' => $part_id,
            'return_quantity' => $quantity,
            'part_totalm3' => $part_total_m3
        ];
    }

    // แปลงข้อมูล parts_data เป็น JSON
    $return_detail = json_encode($parts_data, JSON_UNESCAPED_UNICODE);

    // ใช้เวลาในประเทศไทย (UTC+7)
    date_default_timezone_set('Asia/Bangkok');
    $return_date = date('Y-m-d H:i:s');  // เวลาปัจจุบันในประเทศไทย

    // บันทึกข้อมูลลงในฐานข้อมูล
    $sql_insert = "INSERT INTO return_wood_wip (job_id, return_detail, return_total_m3, return_by, recive_by, return_date) 
                   VALUES ('$job_code', '$return_detail', $total_m3, '$return_by', '$recive_by', '$return_date')";

    if (mysqli_query($conn, $sql_insert)) {
        // ทำการ redirect ไปยังหน้าเดิมหลังจากบันทึกข้อมูลสำเร็จ
        header("Location: " . $_SERVER['PHP_SELF'] . "?job_code=" . $job_code);
        exit; // ให้หยุดการทำงานหลังจาก redirect
    } else {
        echo "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . mysqli_error($conn);
    }
}

// คำนวณ total_m3_all
$sql_total_m3_all = "SELECT SUM(return_total_m3) as total_m3_all FROM return_wood_wip WHERE job_id = '$job_code'";
$result_total_m3_all = mysqli_query($conn, $sql_total_m3_all);
$total_m3_all = 0;
if ($result_total_m3_all->num_rows > 0) {
    $row_total_m3_all = mysqli_fetch_assoc($result_total_m3_all);
    $total_m3_all = $row_total_m3_all['total_m3_all'];  // เก็บผลรวมของ return_total_m3
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>บันทึกการรับไม้คืน</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .repair-info-container {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .form-container {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }

        h2 {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <?php include 'navbar.php'; ?>

    <div class="container mt-5">

        <!-- ข้อมูลการเบิกซ่อมสำหรับ Job ID -->
        <div class="repair-info-container">
            <h2>Job ID: <?php echo $job_code; ?></h2>
            <h4>รวมจำนวนไม้คืน (M3): <?php echo number_format($total_m3_all, 6); ?> M3</h4> <!-- แสดงผลรวม total_m3_all -->
            <div class="container">
                <!-- ตารางแสดงข้อมูลจาก repair_issue -->
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>return_date</th>
                            <th>return_total_m3</th>
                            <th>return_by</th>
                            <th>recive_by</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // ดึงข้อมูลจาก return_wood_wip
                        $sql_repair_issue = "SELECT * FROM return_wood_wip WHERE job_id = '$job_code'";
                        $result_repair_issue = mysqli_query($conn, $sql_repair_issue);
                        if ($result_repair_issue->num_rows > 0) {
                            while ($row = $result_repair_issue->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . $row['return_date'] . "</td>";
                                echo "<td>" . $row['return_total_m3'] . "</td>";
                                echo "<td>" . $row['return_by'] . "</td>";
                                echo "<td>" . $row['recive_by'] . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4'>ไม่พบข้อมูลการคืนไม้สำหรับ Job ID นี้</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- ฟอร์มการบันทึกข้อมูลการคืนไม้ -->
            <div class="form-container">
                <h2>ฟอร์มบันทึกข้อมูลการคืนไม้</h2>
                <form id="returnForm" method="POST">
                    <div class="mb-3">
                        <label for="job_id" class="form-label">Job ID</label>
                        <input type="text" class="form-control" id="job_id" name="job_id" value="<?php echo $job_code; ?>" readonly required>
                    </div>

                    <h3>ข้อมูล Parts สำหรับการคืนไม้</h3>
                    <table class="table table-bordered" id="partsTable">
                        <thead>
                            <tr>
                                <th>PART CODE</th>
                                <th>จำนวน</th>
                                <th>ดำเนินการ</th>
                            </tr>
                        </thead>
                        <tbody id="partListBody">
                            <tr>
                                <td>
                                    <select class="form-select" name="part_id[]" required>
                                        <option value="">เลือก Part ที่ต้องการคืน</option>
                                        <?php
                                        foreach ($part_list as $part) {
                                            $part_code_display = $part['part_code'] ." ". $part['part_type'] . " (" . $part['part_length'] . "x" . $part['part_width'] . "x" . $part['part_thickness'] . ") M3: " . $part['part_m3'];
                                            echo "<option value='" . $part['part_id'] . "'>" . $part_code_display . "</option>";
                                        }
                                        ?>
                                    </select>
                                </td>
                                <td><input type="number" class="form-control" name="quantity[]" required></td>
                                <td><button type="button" class="btn btn-danger removePartBtn">ลบ</button></td>
                            </tr>
                        </tbody>
                    </table>

                    <button type="button" class="btn btn-success" id="addPartBtn">+ เพิ่ม Part</button>
                    <br><br>

                    <!-- Dropdown สำหรับผู้เบิกและผู้รับ อยู่ในบรรทัดเดียวกัน -->
                    <div class="mb-3 row">
                        <div class="col-md-6">
                            <label for="return_by" class="form-label">ผู้ส่งคืน</label> <i class="fas fa-user"></i>
                            <select class="form-select" name="return_by" required style="background-color: #d1e7dd;">
                                <option value="" selected>เลือกชื่อผู้ส่งคืน</option>
                                <option value="สุภาพร">สุภาพร</option>
                                <option value="รุ่งทิวา">รุ่งทิวา</option>
                                <option value="สาม">สาม</option>
                                <option value="เสงี่ยม">เสงี่ยม</option>
                                <option value="กัญญา">กัญญา</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="recive_by" class="form-label">ผู้รับคืน</label> <i class="fas fa-handshake"></i>
                            <select class="form-select" name="recive_by" required style="background-color: #f7b781;">
                                <option value="">เลือกผู้รับคืน</option>
                                <option value="อรจิรา">อรจิรา</option>
                            </select>
                        </div>
                    </div>


                    <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
                </form>
            </div>
        </div>

        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

        <script>
            // ฟังก์ชันสำหรับเพิ่ม Part ใหม่
            $('#addPartBtn').on('click', function () {
                let newRow = `<tr>
                    <td>
                        <select class="form-select" name="part_id[]" required>
                            <option value="">เลือก Part ที่ต้องการคืน</option>
                            <?php
                            foreach ($part_list as $part) {
                                $part_code_display = $part['part_code'] ." ". $part['part_type'] . " (" . $part['part_length'] . "x" . $part['part_width'] . "x" . $part['part_thickness'] . ") M3: " . $part['part_m3'];
                                echo "<option value='" . $part['part_id'] . "'>" . $part_code_display . "</option>";
                            }
                            ?>
                        </select>
                    </td>
                    <td><input type="number" class="form-control" name="quantity[]" required></td>
                    <td><button type="button" class="btn btn-danger removePartBtn">ลบ</button></td>
                </tr>`;
                $('#partListBody').append(newRow);
            });

            // ฟังก์ชันสำหรับลบ Part
            $(document).on('click', '.removePartBtn', function () {
                $(this).closest('tr').remove();
            });
        </script>

</body>
</html>
