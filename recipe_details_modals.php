<?php
// ภาษา: PHP
// ชื่อไฟล์: recipe_details_modals.php

// session_start();
include_once __DIR__ . '/config_db.php';  // เชื่อมต่อฐานข้อมูล
// header('Content-Type: application/json; charset=utf-8');

// --- AJAX: ดึงรายละเอียดเมื่อรับ POST มาเป็น detail_id ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['detail_id'])) {
    $recipe_id = (int) $_POST['detail_id'];

    // เตรียม SQL JOIN ดึงข้อมูลจากทุกตารางที่เกี่ยวข้อง
    $sql = "
        SELECT
            rl.*,
            r.rm_code, r.rm_thickness, r.rm_width, r.rm_length, r.rm_type,
            p.part_code, p.part_thickness, p.part_width, p.part_length, p.part_type,
            hw.hw_code, hw.hw_thickness, hw.hw_width, hw.hw_length, hw.hw_type,
            sw.sw_code, sw.sw_thickness, sw.sw_width, sw.sw_length, sw.sw_type
        FROM recipe_list AS rl
        INNER JOIN rm_wood_list AS r ON rl.rm_id = r.rm_id
        INNER JOIN part_list AS p ON rl.part_id = p.part_id
        LEFT JOIN hw_wood_list AS hw ON rl.hw_id = hw.hw_id
        LEFT JOIN sw_wood_list AS sw ON rl.sw_id = sw.sw_id
        WHERE rl.recipe_id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $recipe_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = $res->fetch_assoc() ?: [];
    $stmt->close();

    // สร้างขนาดเป็น string "หนา x กว้าง x ยาว"
    function formatSize($t, $w, $l) {
        return "{$t} x {$w} x {$l}";
    }

    // ส่งกลับเป็น JSON
    echo json_encode([
        'recipe_id'      => $data['recipe_id'],
        'raw' => [
            'code'         => $data['rm_code'],
            'size'         => formatSize($data['rm_thickness'], $data['rm_width'], $data['rm_length']),
            'type'         => $data['rm_type'] === 'K' ? 'อบแล้ว' : 'ยังไม่อบ',
            'qty'          => $data['rm_qty'],
            'total_m3'     => $data['rm_total_m3'],
            'comment'      => $data['rm_comment'],
        ],
        'part' => [
            'code'         => $data['part_code'],
            'size'         => formatSize($data['part_thickness'], $data['part_width'], $data['part_length']),
            'type'         => $data['part_type'],
            'cut'          => $data['part_cut'],
            'split'        => $data['part_split'],
            'qty'          => $data['part_qry'],
            'total_m3'     => $data['part_total_m3'],
            'comment'      => $data['part_comment'],
        ],
        'heavy' => [
            'code'         => $data['hw_code'],
            'size'         => formatSize($data['hw_thickness'], $data['hw_width'], $data['hw_length']),
            'type'         => $data['hw_type'],
            'cut'          => $data['hw_cut'],
            'split'        => $data['hw_split'],
            'qty'          => $data['hw_qty'],
            'total_m3'     => $data['hw_total_m3'],
            'comment'      => $data['hw_comment'],
        ],
        'saw' => [
            'code'         => $data['sw_code'],
            'size'         => formatSize($data['sw_thickness'], $data['sw_width'], $data['sw_length']),
            'type'         => $data['sw_type'],
            'cut'          => $data['sw_cut'],
            'split'        => $data['sw_split'],
            'qty'          => $data['sw_qty'],
            'total_m3'     => $data['sw_total_m3'],
            'comment'      => $data['sw_comment'],
        ],
        'summary' => [
            'rm_m3'       => $data['rm_total_m3'],
            'used_m3'     => $data['part_total_m3'] + $data['hw_total_m3'] + $data['sw_total_m3'],
            'loss_m3'     => $data['rm_total_m3'] - ($data['part_total_m3'] + $data['hw_total_m3'] + $data['sw_total_m3']),
            'loss_pct'    => $data['loss_per_m3'],
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>

<!-- HTML ของ Bootstrap 5.3 Modal สำหรับแสดงรายละเอียด Recipe -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<div class="modal fade" id="recipeDetailModal" tabindex="-1" aria-labelledby="recipeDetailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="recipeDetailModalLabel">รายละเอียดสูตรการตัดไม้</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="ปิด"></button>
      </div>
      <div class="modal-body">
        <!-- จะแสดงข้อมูลแต่ละส่วนในกล่องนี้ -->
        <div id="detailContent">
          <p class="text-center text-muted">กำลังโหลดข้อมูล...</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
      </div>
    </div>
  </div>
</div>

<!-- ชื่อไฟล์: recipe_details_modals.php -->
<script>
// เมื่อคลิกปุ่มดูรายละเอียด
$(document).on('click', '.btn-detail', function() {
    const recipeId = $(this).data('id');
    $('#detailContent').html('<p class="text-center text-muted">กำลังโหลดข้อมูล...</p>');
    $.post('recipe_details_ajax.php', { recipe_id: recipeId }, function(res) {
        if (!res.raw) {
            $('#detailContent').html('<div class="alert alert-danger">ไม่พบข้อมูล</div>');
            return;
        }

        let html = '';
        const sections = [
          { key: 'raw',   title: 'ไม้ท่อน' },
          { key: 'part',  title: 'งานหลัก' },
          { key: 'heavy', title: 'หัวไม้' },
          { key: 'saw',   title: 'เศษไม้' }
        ];

        sections.forEach(sec => {
            const d = res[sec.key];
            html += `<h6 class="mt-3">${sec.title}</h6>`;

            // ถ้าไม่มีข้อมูล (code เป็น null หรือ undefined) ให้แสดง “ไม่มีข้อมูล” บรรทัดเดียว
            if (!d.code) {
                html += `<p class="text-center text-muted">ไม่มีข้อมูล</p>`;
                return;  // ข้ามไป section ถัดไป
            }

            // กรณีมีข้อมูล ให้แสดงเป็นตารางตามปกติ
            html += `
              <table class="table table-sm">
                <tr><th>รหัส</th><td>${d.code}</td></tr>
                <tr><th>ขนาด</th><td>${d.size}</td></tr>
                <tr><th>ประเภท</th><td>${d.type}</td></tr>
            `;
            if (sec.key !== 'raw') {
              html += `
                <tr><th>ตัด</th><td>${d.cut}</td></tr>
                <tr><th>ผ่า</th><td>${d.split}</td></tr>
              `;
            }
            html += `
                <tr><th>จำนวน</th><td>${d.qty}</td></tr>
                <tr><th>M³ รวม</th><td>${d.total_m3}</td></tr>
                <tr><th>หมายเหตุ</th><td>${d.comment || '-'}</td></tr>
              </table>
            `;
        });

        // สรุปปริมาตร
        html += `
          <h6 class="mt-3">สรุปปริมาตร</h6>
          <table class="table table-sm">
            <tr><th>m³ รวมก่อนตัดผ่า</th><td>${res.summary.rm_m3}</td></tr>
            <tr><th>m³ รวมหลังตัดผ่า</th><td>${res.summary.used_m3}</td></tr>
            <tr><th>m³ LOSS</th><td>${res.summary.loss_m3}</td></tr>
            <tr><th>% LOSS</th><td>${res.summary.loss_pct}</td></tr>
          </table>
        `;

        $('#detailContent').html(html);
        $('#recipeDetailModal').modal('show');
    }, 'json').fail(function(){
        $('#detailContent').html('<div class="alert alert-danger">เกิดข้อผิดพลาดในการดึงข้อมูล</div>');
    });
});
</script>

