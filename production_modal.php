<?php
// production_modal.php
// แสดง Modal สำหรับเลือก JOB ที่มี issue_status = 'ปิดสำเร็จ'
include('config_db.php');

// ดึงข้อมูลโดย JOIN ตาราง wood_issue, prod_list และ jobs_complete
$sql_closed = "SELECT DISTINCT 
                    wi.job_id, 
                    wi.job_type, 
                    pl.prod_code,
                    pl.prod_partno, 
                    jc.prod_complete_qty, 
                    jc.assembly_point, 
                    jc.receive_by, 
                    jc.send_by, 
                    jc.date_complete
               FROM wood_issue wi
               LEFT JOIN prod_list pl ON wi.prod_id = pl.prod_id
               LEFT JOIN jobs_complete jc ON wi.job_id = jc.job_id
               WHERE wi.issue_status IN ('ปิดสำเร็จ','รอยืนยันการสั่งจ่าย')
                 AND wi.job_type NOT IN ('งานไม้ PACK')
                 AND jc.assembly_point NOT IN ('NON-AP', 'CLAIM' ,'Z-รายวัน','KMCT-SK-C')
                 AND jc.date_complete >= '2025-03-26 13:30:00'
               ORDER BY wi.job_id ASC";

$result_closed = mysqli_query($conn, $sql_closed);
?>



<div class="modal fade" id="createInvoiceModal" tabindex="-1" aria-labelledby="createInvoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <!-- ฟอร์มใน Modal ส่งข้อมูลแบบ POST กลับไปยัง production_wages_list.php -->
        <form method="POST" action="">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createInvoiceModalLabel">เลือกงานที่ต้องการออกใบเบิกค่าจ้างผลิต</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- ช่องค้นหา -->
                    <div class="row mb-3">
                        <div class="col-md-2">
                            <input type="text" id="searchJobID" class="form-control" placeholder="Job ID">
                        </div>
                        <div class="col-md-2">
                            <input type="text" id="searchJobType" class="form-control" placeholder="ประเภทงาน">
                        </div>
                        <div class="col-md-3">
                            <input type="text" id="searchProd" class="form-control" placeholder="PROD CODE / PART NO.">
                        </div>
                        <div class="col-md-2">
                            <select id="searchAssembly" class="form-control">
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
                            </select>
                        </div>
                        <div class="col-md-3">
                            <div class="input-group">
                                <input type="date" id="searchStartDate" class="form-control" placeholder="เริ่มต้น">
                                <input type="date" id="searchEndDate" class="form-control" placeholder="สิ้นสุด">
                            </div>
                        </div>
                    </div>
                    <!-- ตารางแสดงข้อมูล -->
                    <?php
                    if ($result_closed && mysqli_num_rows($result_closed) > 0) {
                        echo '<table class="table table-bordered">';
                        echo '<thead><tr>';
                        echo '<th><input style="width:40px; height:40px;" type="checkbox" id="checkAll"></th>';
                        echo '<th>Job ID</th>';
                        echo '<th>ประเภทงาน</th>';
                        echo '<th>PROD CODE / PART NO.</th>';
                        echo '<th>ผลิตได้</th>';
                        echo '<th>จุดประกอบ</th>';
                        echo '<th>ผู้ส่งงาน</th>';
                        echo '<th>ผู้ตรวจรับ</th>';
                        echo '<th>วันที่ตรวจรับ</th>';
                        echo '</tr></thead>';
                        echo '<tbody>';
                        while ($row = mysqli_fetch_assoc($result_closed)) {
                            $jid = $row['job_id'];
                            echo '<tr>';
                            echo '<td><input type="checkbox" name="job_ids[]" value="' . htmlspecialchars($jid) . '" class="jobCheckbox" style="width:40px; height:40px;"></td>';
                            echo '<td>' . htmlspecialchars($row['job_id']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['job_type']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['prod_code']) ." / ". htmlspecialchars($row['prod_partno']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['prod_complete_qty']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['assembly_point']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['send_by']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['receive_by']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['date_complete']) . '</td>';
                            echo '</tr>';
                        }
                        echo '</tbody>';
                        echo '</table>';
                    } else {
                        echo '<p class="text-center">ยังไม่มีรายการ</p>';
                    }
                    ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึก</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- เรียกใช้ jQuery (ตัวอย่างใช้ CDN) -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
$(document).ready(function(){
    function filterTable() {
        var searchJobID   = $('#searchJobID').val().toLowerCase();
        var searchJobType = $('#searchJobType').val().toLowerCase();
        var searchProd    = $('#searchProd').val().toLowerCase();
        var searchAssembly= $('#searchAssembly').val().toLowerCase();

        // —————— เปลี่ยนตรงนี้ ——————
        var searchStartDate = $('#searchStartDate').val();
        if (searchStartDate) {
            searchStartDate = searchStartDate + 'T00:00:00';
        }
        var searchEndDate = $('#searchEndDate').val();
        if (searchEndDate) {
            searchEndDate = searchEndDate + 'T23:59:00';
        }
        // ————————————————

        $('#createInvoiceModal table tbody tr').each(function(){
            var row          = $(this);
            var jobIDText    = row.find('td:eq(1)').text().toLowerCase();
            var jobTypeText  = row.find('td:eq(2)').text().toLowerCase();
            var prodText     = row.find('td:eq(3)').text().toLowerCase();
            var assemblyText = row.find('td:eq(5)').text().toLowerCase();
            var dateText     = row.find('td:eq(8)').text().trim();

            var showRow = true;

            if (searchJobID && jobIDText.indexOf(searchJobID) === -1) {
                showRow = false;
            }
            if (searchJobType && jobTypeText.indexOf(searchJobType) === -1) {
                showRow = false;
            }
            if (searchProd && prodText.indexOf(searchProd) === -1) {
                showRow = false;
            }
            if (searchAssembly && assemblyText.indexOf(searchAssembly) === -1) {
                showRow = false;
            }

            // กรองวันที่โดยใช้เวลาที่ fix แล้ว
            if (searchStartDate || searchEndDate) {
                var rowDate = new Date(dateText);
                if (searchStartDate) {
                    var start = new Date(searchStartDate);
                    if (rowDate < start) showRow = false;
                }
                if (searchEndDate) {
                    var end = new Date(searchEndDate);
                    if (rowDate > end) showRow = false;
                }
            }

            showRow ? row.show() : row.hide();
        });
    }

    $('#searchJobID, #searchJobType, #searchProd, #searchAssembly, #searchStartDate, #searchEndDate')
        .on('input change', filterTable);
});
</script>
<script>
$(document).ready(function(){
  // … โค้ด filterTable เดิม …

  // ปรับให้เช็คเฉพาะแถวที่ visible
  $('#checkAll').on('change', function(){
    // เช็ค/ไม่เช็ค เฉพาะ checkbox ใน tr ที่ยังแสดงอยู่
    $('input.jobCheckbox:visible').prop('checked', this.checked);
  });

  // เมื่อมีการติ๊ก checkbox เดียว จะอัปเดตสถานะหัวตาราง จากเฉพาะ visible เท่านั้น
  $('table').on('change', 'input.jobCheckbox', function(){
    // นับจำนวน visible checkbox ทั้งหมด
    const $visible = $('input.jobCheckbox:visible');
    // นับ visible ทั้งหมดที่ถูกติ๊ก
    const checkedCount = $visible.filter(':checked').length;
    // ถ้า visible ทั้งหมดถูกติ๊ก ให้หัวตารางติ๊กด้วย ไม่งั้นเอาออก
    $('#checkAll').prop('checked', checkedCount > 0 && checkedCount === $visible.length);
  });
});
</script>


<!-- Modal สำหรับแจ้งผลลัพธ์ (จากการสร้าง Invoice) -->
<?php if (!empty($message)): ?>
<div class="modal fade" id="resultModal" tabindex="-1" aria-labelledby="resultModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <?php if ($success): ?>
          <h5 class="modal-title" id="resultModalLabel">บันทึกสำเร็จ</h5>
        <?php else: ?>
          <h5 class="modal-title" id="resultModalLabel">ขัดข้อง</h5>
        <?php endif; ?>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php echo $message; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- production_modal.php -->
<!-- Modal สำหรับแสดงรายละเอียด Production Wage -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="detailModalLabel">ใบเบิกเงิน(ค่าจ้างผลิต)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="detailContent">
         <!-- ข้อมูลรายละเอียดจะถูกโหลดเข้ามาทาง AJAX -->
      </div>
      <div class="modal-footer">
         <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal สำหรับ Create Invoice -->
<div class="modal fade" id="createInvoiceModal" tabindex="-1" aria-labelledby="createInvoiceModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="production_wages_list.php">
        <div class="modal-header">
          <h5 class="modal-title" id="createInvoiceModalLabel">Create Invoice</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <!-- ตัวอย่างฟอร์มสำหรับระบุ Job IDs โดยคั่นด้วยเครื่องหมายจุลภาค -->
          <div class="form-group">
            <label for="job_ids">Job IDs</label>
            <input type="text" name="job_ids" id="job_ids" class="form-control" placeholder="เช่น 25-02/290,25-0460">
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">สร้าง Invoice</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
        </div>
      </form>
    </div>
  </div>
</div>
