<?php

// ตั้งค่า Time Zone
date_default_timezone_set('Asia/Bangkok');

// เชื่อมต่อฐานข้อมูล
include 'config_db.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการใบเบิกไม้</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'navbar.php'; ?>
    <div class="container mt-5">
        <h1 class="text-center mb-4">รายการใบเบิกไม้</h1>

        <!-- ปุ่มสร้างใบเบิก -->
        <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#createIssueModal">
            สร้างใบเบิก
        </button>
        <button type="button" class="btn btn-secondary mb-3" data-bs-toggle="modal" data-bs-target="#createRepairIssueModal">
            สร้างใบเบิกซ่อม
        </button>

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
                                    <option value="A">งานหมวด A</option>
                                    <option value="general">งานทั่วไป</option>
                                </select>
                            </div>

                            <!-- หมายเลข JOB (สร้างอัตโนมัติ) -->
                            <div class="mb-3 position-relative">
                                <label for="job_id" class="form-label">หมายเลข JOB</label>
                                <input type="text" class="form-control" id="job_id" name="job_id" placeholder="ระบบจะสร้างให้โดยอัตโนมัติ" autocomplete="off" readonly>
                                <div id="jobIdSuggestions" class="list-group position-absolute w-100" style="z-index: 1050;"></div>
                            </div>

                            <!-- Product Code -->
                            <div class="mb-3">
                                <label for="product_code" class="form-label">Product Code</label>
                                <input type="text" class="form-control" id="product_code" name="product_code" placeholder="ค้นหา Product Code" required autocomplete="off">
                                <div id="productCodeSuggestions" class="list-group position-absolute w-100"></div>
                            </div>

                            <!-- จำนวน -->
                            <div class="mb-3">
                                <label for="quantity" class="form-label">จำนวน</label>
                                <input type="number" class="form-control" id="quantity" name="quantity" required>
                            </div>

                            <!-- สูญเสียไม้ (%) -->
                            <div class="mb-3">
                                <label for="wood_wastage" class="form-label">สูญเสียไม้ (%)</label>
                                <input type="number" class="form-control" id="wood_wastage" name="wood_wastage" min="0" max="100" required>
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



        <!-- Modal สำหรับสร้างใบเบิกซ่อม -->
        <div class="modal fade" id="createRepairIssueModal" tabindex="-1" aria-labelledby="createRepairIssueModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form action="create_repair_issue.php" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="createRepairIssueModalLabel">สร้างใบเบิกซ่อม</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <!-- JOB ID -->
                            <div class="mb-3">
                                <label for="repair_job_id" class="form-label">หมายเลข JOB</label>
                                <input type="text" class="form-control" id="repair_job_id" name="repair_job_id" placeholder="กรุณากรอกหมายเลข JOB" required>
                            </div>
                            <!-- ปุ่มดึงข้อมูล -->
                            <button type="button" id="fetchPartsBtn" class="btn btn-primary">ดึงข้อมูล Part</button>
                            
                            <!-- ตารางแสดงข้อมูล Part -->
                            <table class="table table-bordered mt-3">
                                <thead>
                                    <tr>
                                        <th>Part Code</th>
                                        <th>Part Name</th>
                                        <th>จำนวนที่ต้องการเบิก</th>
                                        <th>สาเหตุ</th>
                                    </tr>
                                </thead>
                                <tbody id="partsTableBody">
                                    <!-- ตารางจะถูกเติมข้อมูลด้วย JavaScript -->
                                </tbody>
                            </table>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                            <button type="submit" class="btn btn-success">บันทึกใบเบิกซ่อม</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ตารางแสดงรายการใบเบิก -->
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>หมายเลข JOB</th>
                    <th>Product Code</th>
                    <th>จำนวน</th>
                    <th>วันที่สร้าง</th>
                    <th>วันที่ต้องการรับไม้</th>
                    <th>วันที่เบิก</th>
                    <th>ผู้เบิก</th>
                    <th>สถานะ</th>
                    <th>ดำเนินการ</th>       
                </tr>
            </thead>
            <tbody>
                <?php
// ดึงข้อมูลจากตาราง wood_issue
$query = "SELECT * FROM wood_issue ORDER BY creation_date DESC";
$result = $conn->query($query);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        
        // แปลง format วันที่ creation_date (datetime)
        $creation_date_formatted = date('d-m-Y H:i', strtotime($row['creation_date']));

        // แปลง format วันที่ want_receive (date)
        // ถ้าต้องการเฉพาะวันเดือนปี:
        $want_receive_formatted = date('d-m-Y', strtotime($row['want_receive']));
        // ถ้าหากต้องการ HH:mm ด้วยแต่ค่าเป็น DATE ไม่มีเวลา อาจจะได้ 00:00 เสมอ
        // $want_receive_formatted = date('d-m-Y H:i', strtotime($row['want_receive']));

        // แปลง format วันที่ issue_date (datetime)
        $issue_date_formatted = $row['issue_date'] ? date('d-m-Y H:i', strtotime($row['issue_date'])) : '-';

        echo "<tr>
                <td>{$row['job_id']}</td>
                <td>{$row['product_code']}</td>
                <td>{$row['quantity']}</td>
                <td>{$creation_date_formatted}</td>
                <td>{$want_receive_formatted}</td>
                <td>{$issue_date_formatted}</td>
                <td>{$row['issued_by']}</td>
                <td>{$row['issue_status']}</td>
                <td>
                    <a href='repair_issue.php?job_id={$row['job_id']}' class='btn btn-warning btn-sm'>
                        เบิกซ่อม
                    </a>
                    <!-- ปุ่มแก้ไข -->
                    <button class='btn btn-warning btn-sm' data-bs-toggle='modal' data-bs-target='#editIssueModal{$row['issue_id']}'" . ($row['issue_status'] === 'เบิกแล้ว' ? ' disabled' : '') . ">แก้ไข</button>
                    <!-- ปุ่มลบ -->
                    <button class='btn btn-danger btn-sm' data-bs-toggle='modal' data-bs-target='#deleteIssueModal{$row['issue_id']}'" . ($row['issue_status'] === 'เบิกแล้ว' ? ' disabled' : '') . ">ลบ</button>
                    <!-- ปุ่ม PDF -->
                    <a href='generate_issued_pdf.php?issue_id={$row['issue_id']}' target='_blank' class='btn btn-info btn-sm'>PDF</a>
                    <!-- ปุ่ม เบิก -->
                    <button class='btn btn-success btn-sm' data-bs-toggle='modal' data-bs-target='#issueModal{$row['issue_id']}' " . ($row['issue_status'] === 'เบิกแล้ว' ? ' disabled' : '') . ">
                        เบิก
                    </button>
                </td>
              </tr>";

              
        // Modal สำหรับแก้ไขข้อมูล
        echo "<div class='modal fade' id='editIssueModal{$row['issue_id']}' tabindex='-1' aria-labelledby='editIssueModalLabel' aria-hidden='true'>
                                <div class='modal-dialog'>
                                    <div class='modal-content'>
                                        <form action='update_issue.php' method='POST'>
                                            <div class='modal-header'>
                                                <h5 class='modal-title' id='editIssueModalLabel'>แก้ไขข้อมูลใบเบิก: {$row['job_id']}</h5>
                                                <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                                            </div>
                                            <div class='modal-body'>
                                                <input type='hidden' name='issue_id' value='{$row['issue_id']}'>
                                                <div class='mb-3'>
                                                    <label for='quantity' class='form-label'>จำนวน</label>
                                                    <input type='number' class='form-control' id='quantity' name='quantity' value='{$row['quantity']}' required>
                                                </div>
                                                <div class='mb-3'>
                                                    <label for='wood_wastage' class='form-label'>สูญเสียไม้ (%)</label>
                                                    <input type='number' class='form-control' id='wood_wastage' name='wood_wastage' min='0' max='100' value='{$row['wood_wastage']}' required>
                                                </div>
                                                <div class='mb-3'>
                                                    <label for='wood_type' class='form-label'>ประเภทไม้</label>
                                                    <select class='form-select' id='wood_type' name='wood_type' required>
                                                        <option value='NONFSC' " . ($row['wood_type'] == 'NONFSC' ? 'selected' : '') . ">NONFSC</option>
                                                        <option value='FSCMIX' " . ($row['wood_type'] == 'FSCMIX' ? 'selected' : '') . ">FSCMIX</option>
                                                        <option value='FSC100' " . ($row['wood_type'] == 'FSC100' ? 'selected' : '') . ">FSC100</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class='modal-footer'>
                                                <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>ยกเลิก</button>
                                                <button type='submit' class='btn btn-primary'>บันทึกการเปลี่ยนแปลง</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>";
        // Modal สำหรับลบข้อมูล
        echo "<div class='modal fade' id='deleteIssueModal{$row['issue_id']}' tabindex='-1' aria-labelledby='deleteIssueModalLabel' aria-hidden='true'>
            <div class='modal-dialog'>
                <div class='modal-content'>
                    <form action='delete_issue.php' method='POST'>
                        <div class='modal-header'>
                            <h5 class='modal-title' id='deleteIssueModalLabel'>ยืนยันการลบใบเบิก: {$row['job_id']}</h5>
                            <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                        </div>
                        <div class='modal-body'>
                            <input type='hidden' name='issue_id' value='{$row['issue_id']}'>
                            <p>กรุณาพิมพ์ <strong>ฉันแน่ใจ</strong> เพื่อยืนยันการลบ</p>
                            <input type='text' class='form-control' id='confirmation_text' name='confirmation_text' required>
                        </div>
                        <div class='modal-footer'>
                            <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>ยกเลิก</button>
                            <button type='submit' class='btn btn-danger'>ลบ</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>";

        // Modal สำหรับรับชื่อผู้เบิก
        echo "<div class='modal fade' id='issueModal{$row['issue_id']}' tabindex='-1' aria-labelledby='issueModalLabel' aria-hidden='true'>
            <div class='modal-dialog'>
                <div class='modal-content'>
                    <form action='process_issue.php' method='POST'>
                        <div class='modal-header'>
                            <h5 class='modal-title' id='issueModalLabel'>เบิกไม้สำหรับใบเบิก: {$row['job_id']}</h5>
                            <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                        </div>
                        <div class='modal-body'>
                            <input type='hidden' name='issue_id' value='{$row['issue_id']}'>
                            <div class='mb-3'>
                                <label for='issued_by' class='form-label'>ชื่อผู้เบิก</label>
                                <input type='text' class='form-control' id='issued_by' name='issued_by' required>
                            </div>
                        </div>
                        <div class='modal-footer'>
                            <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>ยกเลิก</button>
                            <button type='submit' class='btn btn-primary'>ยืนยันการเบิก</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>";


        
    }
} else {
    echo "<tr><td colspan='10' class='text-center'>ไม่พบข้อมูล</td></tr>";
}
?>
            </tbody>

        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        // เมื่อผู้ใช้พิมพ์ในช่อง Product Code
        $('#product_code').on('input', function() {
            let query = $(this).val();

            if (query.length > 1) {
                $.ajax({
                    url: 'fetch_product_codes.php',
                    method: 'GET',
                    data: { search: query },
                    success: function(data) {
                        $('#productCodeSuggestions').html(data).fadeIn();
                    }
                });
            } else {
                $('#productCodeSuggestions').fadeOut();
            }
        });

        // เมื่อคลิกเลือก Product Code จากรายการที่แสดง
        $(document).on('click', '.suggestion-item', function() {
            $('#product_code').val($(this).text());
            $('#productCodeSuggestions').fadeOut();
        });

        // ปิดรายการเมื่อคลิกที่อื่น
        $(document).click(function(event) {
            if (!$(event.target).closest('#product_code, #productCodeSuggestions').length) {
                $('#productCodeSuggestions').fadeOut();
            }
        });
    });
    </script>
    <script>
        $(document).ready(function() {
            // เมื่อกดปุ่มดึงข้อมูล Part
            $('#fetchPartsBtn').on('click', function() {
                const jobId = $('#repair_job_id').val();

                if (jobId.trim() === '') {
                    alert('กรุณากรอกหมายเลข JOB');
                    return;
                }

                // AJAX ดึงข้อมูล Part
                $.ajax({
                    url: 'fetch_parts.php', // ไฟล์สำหรับดึงข้อมูล Part
                    method: 'GET',
                    data: { job_id: jobId },
                    success: function(response) {
                        // เติมข้อมูลในตาราง
                        $('#partsTableBody').html(response);
                    },
                    error: function() {
                        alert('เกิดข้อผิดพลาดในการดึงข้อมูล');
                    }
                });
            });

            // แสดง Dropdown เมื่อกรอกจำนวน
            $(document).on('input', '.part-quantity', function() {
                const row = $(this).closest('tr');
                const quantity = $(this).val();

                if (quantity > 0) {
                    row.find('.reason-dropdown').removeClass('d-none');
                } else {
                    row.find('.reason-dropdown').addClass('d-none');
                }
            });
        });
    </script>
    <script>
    $(document).ready(function () {
        // เมื่อพิมพ์ในช่อง JOB ID
        $('#job_id').on('input', function () {
            let query = $(this).val();

            if (query.length > 1) {
                $.ajax({
                    url: 'fetch_job_ids.php', // ไฟล์ PHP สำหรับค้นหา
                    method: 'GET',
                    data: { search: query },
                    success: function (data) {
                        $('#jobIdSuggestions').html(data).fadeIn();
                    }
                });
            } else {
                $('#jobIdSuggestions').fadeOut();
            }
        });

        // เมื่อคลิกเลือก JOB ID จากรายการที่แสดง
        $(document).on('click', '.job-suggestion-item', function () {
            $('#job_id').val($(this).data('jobid')); // เก็บเฉพาะ JOB ID
            $('#jobIdSuggestions').fadeOut();
        });

        // ปิดรายการเมื่อคลิกที่อื่น
        $(document).click(function (event) {
            if (!$(event.target).closest('#job_id, #jobIdSuggestions').length) {
                $('#jobIdSuggestions').fadeOut();
            }
        });
    });
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    

</body>
</html>
