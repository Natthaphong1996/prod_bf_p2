<?php
// ฟังก์ชันสำหรับแสดง Modal รายละเอียดของงาน (ที่มีอยู่แล้ว)
function displayJobDetailModal($job_id, $prod_id, $quantity, $conn)
{
  // ดึงข้อมูลรายละเอียดงานที่ผลิตเสร็จแล้ว (สมมุติว่าเป็น array)
  $wood_jobcompleteIntro = getJobCompleteInfo($job_id, $conn);
  // เอาค่าจำนวนที่ผลิตเสร็จแล้วมาใส่ตัวแปรนี้
  $wood_jobcompleteQty = isset($wood_jobcompleteIntro['prod_complete_qty']) ? $wood_jobcompleteIntro['prod_complete_qty'] : 0;
  
  $wood_issue_main    = getWoodIssueMainM3($prod_id, $quantity, $conn);
  $wood_issue_complete = getWoodIssueMainM3($prod_id, $wood_jobcompleteQty, $conn);
  $wood_issues_repair = getWoodIssuesRepairM3($job_id, $conn);
  $wood_return        = getReturnWoodM3($job_id, $conn);
  $wood_status        = getJobStatus($job_id, $conn);
  
  // เลือกใช้ค่าที่ถูกต้องตามสถานะงาน
  // $wood_issue_value = ($wood_status['issue_status'] != 'ปิดสำเร็จ' ) ? $wood_issue_main : $wood_issue_complete;
  $wood_issue_value = in_array($wood_status['issue_status'], ['รอยืนยันการสั่งจ่าย','สั่งจ่ายแล้ว','ปิดสำเร็จ'])
    ? $wood_issue_complete
    : $wood_issue_main;

  
  // คำนวณปริมาณไม้ที่ใช้จริง
  $wood_actual = ($wood_issue_value + $wood_issues_repair) - $wood_return;
  // คำนวณส่วนต่าง โดยเทียบกับแผนที่วางไว้ (wood_issue_main)
  $wood_loss = number_format($wood_issue_main-$wood_actual, 4);
  
  // ฟอร์แมทตัวเลขให้มีทศนิยม 4 ตำแหน่ง
  $wood_actual_formatted = number_format($wood_actual, 4);
  $wood_loss_formatted   = number_format($wood_loss, 4);

  // ตรวจสอบว่ามีข้อมูลวันที่ปิดงานหรือไม่
  $date_complete = (is_array($wood_jobcompleteIntro) && isset($wood_jobcompleteIntro['date_complete']) && $wood_jobcompleteIntro['date_complete'])
                    ? $wood_jobcompleteIntro['date_complete']
                    : 'งานยังไม่ได้ปิด';

  ?>
  <div class="modal fade" id="detailModal<?php echo $job_id; ?>" tabindex="-1"
    aria-labelledby="detailModalLabel<?php echo $job_id; ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="detailModalLabel<?php echo $job_id; ?>">รายละเอียดงาน <?php echo $job_id; ?></h5>
          <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p><strong>Wood Issue Main M3:</strong> <?php echo number_format($wood_issue_main, 4); ?></p>
          <p><strong>Wood Issues Repair M3:</strong> <?php echo number_format($wood_issues_repair, 4); ?></p>
          <p><strong>Return Wood M3:</strong> <?php echo number_format($wood_return, 4); ?></p>
          <p><strong>Wood Issues Actual M3:</strong> <?php echo $wood_actual_formatted; ?></p>
          <p><strong>Wood Loss M3:</strong> <?php echo $wood_loss_formatted; ?></p>
          <p><strong>วันที่ปิดงาน:</strong> <?php echo $date_complete; ?></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
        </div>
      </div>
    </div>
  </div>
  <?php
}

// ฟังก์ชันสำหรับแสดง Modal สรุปผลรวม พร้อมตารางแยกตามลูกค้า
function displaySummaryModal($sum_main, $sum_repair, $sum_return, $sum_loss, $customer_summary)
{
  ?>
  <div class="modal fade" id="summaryModal" tabindex="-1" aria-labelledby="summaryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="summaryModalLabel">สรุปผลการค้นหา</h5>
          <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <!-- สรุปผลรวมโดยรวม -->
          <p><strong>Wood Issue Main M3:</strong> <?php echo number_format($sum_main, 4); ?></p>
          <p><strong>Wood Issues Repair M3:</strong> <?php echo number_format($sum_repair, 4); ?></p>
          <p><strong>Return Wood M3:</strong> <?php echo number_format($sum_return, 4); ?></p>
          <p><strong>Wood Issues Actual M3:</strong> <?php echo number_format(($sum_main + $sum_repair) - $sum_return, 4); ?></p>
          <p><strong>Wood Loss M3:</strong> <?php echo number_format($sum_loss, 4); ?></p>
          <p><strong>Wood Loss (%):</strong>
            <?php echo ($sum_main > 0 ? number_format(($sum_loss * 100) / $sum_main, 2) : '0'); ?></p>

          <hr>
          <!-- ตารางแยกสรุปตามลูกค้า -->
          <h5 class="mt-3">สรุปผลตามลูกค้า</h5>
          <div class="table-responsive">
            <table class="table table-bordered">
              <thead class="table-light">
                <tr>
                  <th>ชื่อลูกค้า</th>
                  <th>จำนวนงาน</th>
                  <th>Wood Issues Actual M3</th>
                  <th>Wood Loss M3</th>
                  <th>Wood Loss (%)</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($customer_summary as $customer => $data):
                  $job_count = $data['job_count'];
                  $cust_sum_main = $data['sum_main'];
                  $cust_sum_repair = $data['sum_repair'];
                  $cust_sum_return = $data['sum_return'];
                  // คำนวณ Actual Wood สำหรับลูกค้า
                  $cust_actual = ($cust_sum_main + $cust_sum_repair) - $cust_sum_return;
                  // คำนวณ Wood Loss ตามสูตรใหม่
                  $cust_loss = number_format($cust_actual - $cust_sum_main, 4);
                  $cust_loss_percent = ($cust_sum_main > 0 ? ($cust_loss * 100) / $cust_sum_main : 0);
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars($customer); ?></td>
                    <td><?php echo $job_count; ?></td>
                    <td><?php echo number_format($cust_actual, 4); ?></td>
                    <td><?php echo number_format($cust_loss, 4); ?></td>
                    <td><?php echo number_format($cust_loss_percent, 2); ?>%</td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
        </div>
      </div>
    </div>
  </div>
  <?php
}
