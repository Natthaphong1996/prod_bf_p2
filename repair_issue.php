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
    $sql_part = "SELECT part_id, part_code, part_length, part_width, part_thickness, part_m3 
                 FROM part_list WHERE part_id = '$part_id'";
    $result_part = mysqli_query($conn, $sql_part);
    if ($result_part->num_rows > 0) {
        $row_part = mysqli_fetch_assoc($result_part);
        $part_list[] = $row_part;  // เก็บข้อมูล part ที่ได้
    }
}

// ดึงข้อมูลการคืนไม้จาก repair_issue
$sql_repair_issue = "SELECT * FROM repair_issue WHERE job_id = '$job_code'";
$result_repair_issue = mysqli_query($conn, $sql_repair_issue);

// ตรวจสอบว่ามีข้อมูลการคืนไม้หรือไม่
if ($result_repair_issue->num_rows > 0) {
    // มีข้อมูลการคืนไม้
} else {
    // ไม่มีข้อมูลการคืนไม้สำหรับ Job นี้
    $no_data_message = "ไม่พบข้อมูลการคืนไม้สำหรับ Job ID นี้";
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
            <div class="container">
                <!-- ตารางแสดงข้อมูลจาก repair_issue -->
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>หมายเลขใบเบิกซ่อม</th>
                            <th>วันที่ออกเอกสาร</th>
                            <th>วันที่ต้องการรับไม้</th>
                            <th>วันที่เบิก</th>
                            <th>ผู้เบิก</th>
                            <th>ดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // ดึงข้อมูลจาก repair_issue
                        $sql_repair_issue = "SELECT * FROM repair_issue WHERE job_id = '$job_code'";
                        $result_repair_issue = mysqli_query($conn, $sql_repair_issue);
                        if ($result_repair_issue->num_rows > 0) {
                            while ($row = $result_repair_issue->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . $row['repair_id'] . "</td>";
                                echo "<td>" . $row['created_at'] . "</td>";
                                echo "<td>" . $row['want_receive'] . "</td>";
                                echo "<td>" . $row['issue_date'] . "</td>";
                                echo "<td>" . $row['issued_by'] . "</td>";
                                echo "<td>" . $row['status'] . "</td>";

                                // แสดงปุ่ม PDF
                                echo "<td><button type=\"button\" class=\"btn btn-secondary pdfBtn\" data-repairid=\"" . $row['repair_id'] . "\">PDF</button>";

                                // เช็คเงื่อนไขว่า status เป็น 'สั่งไม้' หรือไม่
                                if ($row['status'] == 'สั่งไม้') {
                                    echo "<button class='btn btn-danger deleteBtn' data-repairid='" . $row['repair_id'] . "'>ยกเลิก</button>";
                                }

                                echo "</td>"; // จบการแสดงผลของ <td>
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='7'>ไม่พบข้อมูลการคืนไม้สำหรับ Job ID นี้</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- ฟอร์มการบันทึกข้อมูลการคืนไม้ -->
            <div class="form-container">
                <h2>ฟอร์มบันทึกข้อมูลการเบิกไม้</h2>
                <form id="repairIssueForm" method="POST">
                    <div class="mb-3">
                        <label for="job_id" class="form-label">Job ID</label>
                        <input type="text" class="form-control" id="job_id" name="job_id" value="<?php echo $job_code; ?>" readonly required>
                    </div>

                    <div class="mb-3">
                        <label for="want_receive" class="form-label">วันที่ต้องการรับไม้</label>
                        <input type="date" class="form-control" id="want_receive" name="want_receive" required>
                    </div>

                    <h3>ข้อมูล Parts สำหรับการเบิกไม้</h3>
                    <table class="table table-bordered" id="partsTable">
                        <thead>
                            <tr>
                                <th>PART CODE</th>
                                <th>จำนวน</th>
                                <th>สาเหตุ</th>
                                <th>ดำเนินการ</th>
                            </tr>
                        </thead>
                        <tbody id="partListBody">
                            <tr>
                                <td>
                                    <select class="form-select" name="part_id[]" required>
                                        <option value="">เลือก Part ที่ต้องการเบิกซ่อม</option>
                                        <?php
                                        foreach ($part_list as $part) {
                                            $part_code_display = $part['part_code'] . " | " . $part['part_length'] . "x" . $part['part_width'] . "x" . $part['part_thickness'] . " mm.";
                                            echo "<option value='" . $part['part_id'] . "'>" . $part_code_display . "</option>";
                                        }
                                        ?>
                                    </select>
                                </td>
                                <td><input type="number" class="form-control" name="quantity[]" required></td>
                                <td>
                                    <select class="form-select" name="reason[]" required>
                                        <option value="">เลือกสาเหตุ</option>
                                        <option value="เชื้อรา">เชื้อรา</option>
                                        <option value="ไม้เปียก">ไม้เปียก</option>
                                        <option value="ตาไม้">ตาไม้</option>
                                        <option value="ไม่ได้ขนาด">ไม่ได้ขนาด</option>
                                        <option value="โก่งงอ">โก่งงอ</option>
                                        <option value="แตกร้าว">แตกร้าว</option>
                                        <option value="ไม้เสียหาย">ไม้เสียหาย</option>
                                        <option value="ไส้ไม้">ใส้ไม้</option>
                                        <option value="เป็นรู">เป็นรู</option>
                                        <option value="ไม้ดำ">ไม้ดำ</option>
                                        <option value="เปลี่ยน DWG">เปลี่ยน DWG</option>
                                        <option value="UPDATE BOM">UPDATE BOM</option>
                                        <option value="เบิกซ่อมงานเคลม">เบิกซ่อมงานเคลม</option>
                                        <option value="ไม้ไม่ได้คุณภาพ">ไม้ไม่ได้คุณภาพ</option>
                                        <!-- เพิ่มตัวเลือกสาเหตุได้ตามต้องการ -->
                                    </select>
                                </td>
                                <td><button type="button" class="btn btn-danger removePartBtn">ลบ</button></td>
                            </tr>
                        </tbody>
                    </table>


                    <button type="button" class="btn btn-success" id="addPartBtn">+ เพิ่ม Part</button>
                    <br><br>
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
                                        <option value="">เลือก Part ที่ต้องการเบิกซ่อม</option>
                                        <?php
                                        foreach ($part_list as $part) {
                                            $part_code_display = $part['part_code'] . " | " . $part['part_length'] . "x" . $part['part_width'] . "x" . $part['part_thickness'] . " mm.";
                                            echo "<option value='" . $part['part_id'] . "'>" . $part_code_display . "</option>";
                                        }
                                        ?>
                                    </select>
                                </td>
                                <td><input type="number" class="form-control" name="quantity[]" required></td>
                                <td>
                                    <select class="form-select" name="reason[]" required>
                                        <option value="">เลือกสาเหตุ</option>
                                        <option value="เชื้อรา">เชื้อรา</option>
                                        <option value="ไม้เปียก">ไม้เปียก</option>
                                        <option value="ตาไม้">ตาไม้</option>
                                        <option value="ไม่ได้ขนาด">ไม่ได้ขนาด</option>
                                        <option value="โก่งงอ">โก่งงอ</option>
                                        <option value="แตกร้าว">แตกร้าว</option>
                                        <option value="ไม้เสียหาย">ไม้เสียหาย</option>
                                        <option value="ใส้ไม้">ไส้ไม้</option>
                                        <option value="เป็นรู">เป็นรู</option>
                                        <option value="ไม้ดำ">ไม้ดำ</option>
                                        <option value="เปลี่ยน DWG">เปลี่ยน DWG</option>
                                        <option value="UPDATE BOM">UPDATE BOM</option>
                                        <option value="เบิกซ่อมงานเคลม">เบิกซ่อมงานเคลม</option>
                                        <option value="ไม้ไม่ได้คุณภาพ">ไม้ไม่ได้คุณภาพ</option>
                                        <!-- เพิ่มตัวเลือกสาเหตุได้ตามต้องการ -->
                                    </select>
                                </td>
                                <td><button type="button" class="btn btn-danger removePartBtn">ลบ</button></td>
                            </tr>`;
                $('#partListBody').append(newRow);
            });

            // ฟังก์ชันสำหรับลบ Part
            $(document).on('click', '.removePartBtn', function () {
                $(this).closest('tr').remove();
            });

            $(document).on('click', '.pdfBtn', function() {
                var repairId = $(this).data('repairid');  // รับ repair_id จากปุ่ม PDF
                console.log("Sending repair_id to generate PDF:", repairId); // ตรวจสอบว่า repair_id ถูกส่งไป

                // ส่งคำขอไปยัง generate_repair_issue_pdf.php เพื่อสร้าง PDF ในหน้าต่างใหม่
                var pdfUrl = "generate_repair_issue_pdf.php?repair_id=" + repairId;  // สร้าง URL
                window.open(pdfUrl, '_blank');  // เปิด URL ในหน้าต่างใหม่
            });

            $(document).on('click', '.deleteBtn', function() {
                // รับ repair_id จากปุ่มที่คลิก
                var repairId = $(this).data('repairid');
                console.log("Deleting repair issue with repair_id:", repairId); // ตรวจสอบว่า repair_id ถูกส่งไป

                // ถามผู้ใช้ก่อนการลบข้อมูล
                if (confirm("คุณต้องการลบข้อมูลนี้หรือไม่?")) {
                    // ส่งคำขอลบไปยังเซิร์ฟเวอร์
                    $.ajax({
                        url: 'delete_repair_issue.php',  // ฟังก์ชันที่ใช้ลบข้อมูลจากฐานข้อมูล
                        method: 'POST',
                        data: { repair_issue_id: repairId },  // ส่ง repair_id ไปให้ PHP
                        success: function(response) {
                            // หากลบข้อมูลสำเร็จ
                            alert("ลบข้อมูลเรียบร้อยแล้ว");
                            location.reload();  // รีเฟรชหน้าเพื่อแสดงข้อมูลล่าสุด
                        },
                        error: function(xhr, status, error) {
                            console.error("AJAX error:", status, error);  // แสดงข้อผิดพลาดจาก AJAX
                            alert('ไม่สามารถลบข้อมูลได้');
                        }
                    });
                }
            });

            // ส่งข้อมูลฟอร์มไปยังเซิร์ฟเวอร์เพื่อบันทึก
            $('#repairIssueForm').on('submit', function(e) {
                e.preventDefault();  // ป้องกันการรีเฟรชหน้าเมื่อส่งฟอร์ม

                // สร้าง Object สำหรับเก็บข้อมูลที่กรอก
                var partData = [];
                $('#partListBody tr').each(function() {
                    var partId = $(this).find('select[name="part_id[]"]').val();  // ใช้ part_code แต่เป็น part_id
                    var quantity = $(this).find('input[name="quantity[]"]').val();
                    var reason = $(this).find('select[name="reason[]"]').val();
                    partData.push({
                        part_id: partId,  // ใช้ part_id แทน part_code
                        quantity: quantity,
                        reason: reason
                    });
                });

                // แปลงเป็น JSON string
                var partQuantityReason = JSON.stringify(partData);

                // เก็บข้อมูลในฟอร์ม
                var formData = $(this).serializeArray();
                formData.push({ name: "part_quantity_reason", value: partQuantityReason });

                // ส่งข้อมูลไปยัง server
                $.ajax({
                    url: 'save_repair_issue.php',  // URL ที่จะรับข้อมูล
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        alert(response);
                        location.reload();  // รีเฟรชหน้าเมื่อบันทึกสำเร็จ
                    },
                    error: function() {
                        alert('เกิดข้อผิดพลาดในการส่งข้อมูล');
                    }
                });
            });


        </script>

</body>
</html>
