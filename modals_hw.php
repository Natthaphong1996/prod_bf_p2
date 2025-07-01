<?php
// ภาษา: PHP
// ชื่อไฟล์: modals_hw.php
?>
<!-- Modal Form Add/Edit HW -->
<div class="modal fade" id="modalForm" tabindex="-1" aria-labelledby="modalFormLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" id="frmHW">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalTitle">Form HW</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" id="action">
          <input type="hidden" name="hw_id" id="hw_id">

          <div class="mb-3">
            <label for="hw_code" class="form-label">Code</label>
            <input type="text" class="form-control" id="hw_code" name="hw_code" readonly required>
          </div>

          <div class="mb-3">
            <label for="hw_type" class="form-label">Type</label>
            <select class="form-select" id="hw_type" name="hw_type" required>
              <option value="K">ไม้อบ</option>
              <option value="G">ไม้ยังไม่ได้อบ</option>
            </select>
          </div>

          <div class="row">
            <div class="col-4 mb-3">
              <label for="hw_thickness" class="form-label">Thickness</label>
              <input type="number" class="form-control" id="hw_thickness" name="hw_thickness" required>
            </div>
            <div class="col-4 mb-3">
              <label for="hw_width" class="form-label">Width</label>
              <input type="number" class="form-control" id="hw_width" name="hw_width" required>
            </div>
            <div class="col-4 mb-3">
              <label for="hw_length" class="form-label">Length</label>
              <input type="number" class="form-control" id="hw_length" name="hw_length" required>
            </div>
          </div>

          <div class="mb-3">
            <label for="hw_m3" class="form-label">M³</label>
            <input type="number" step="0.001" class="form-control" id="hw_m3" name="hw_m3" readonly required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary">บันทึก</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Modal Delete HW -->
<div class="modal fade" id="modalDelete" tabindex="-1" aria-labelledby="modalDeleteLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <form method="post">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalDeleteLabel">ยืนยันลบ HW</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>ต้องการลบรายการนี้หรือไม่?</p>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="hw_id" id="del_hw_id">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-danger">ลบ</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Inline JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// เตรียมฟอร์มใหม่
function openAddModal() {
    document.getElementById('modalTitle').innerText = 'เพิ่ม HW';
    document.getElementById('frmHW').reset();
    document.getElementById('action').value = 'add';
    updateCodeAndM3();
}
// เตรียมข้อมูลแก้ไข
function openEditModal(data) {
    document.getElementById('modalTitle').innerText = 'แก้ไข HW';
    document.getElementById('action').value = 'edit';
    document.getElementById('hw_id').value = data.hw_id;
    document.getElementById('hw_type').value = data.hw_type;
    document.getElementById('hw_thickness').value = data.hw_thickness;
    document.getElementById('hw_width').value = data.hw_width;
    document.getElementById('hw_length').value = data.hw_length;
    updateCodeAndM3();
    document.getElementById('hw_m3').value = data.hw_m3;
}
// เตรียมลบ
function openDeleteModal(id) {
    document.getElementById('del_hw_id').value = id;
}
// ฟังก์ชันสร้าง Code และคำนวณ m³
function updateCodeAndM3() {
    const type = document.getElementById('hw_type').value;
    let t = document.getElementById('hw_thickness').value;
    let w = document.getElementById('hw_width').value;
    let l = document.getElementById('hw_length').value;
    if (!t||!w||!l) return;
    t = t.toString().padStart(3,'0');
    w = w.toString().padStart(3,'0');
    l = l.toString().padStart(4,'0');
    document.getElementById('hw_code').value = `${type}${t}${w}${l}`;
    const m3 = (parseInt(t)*parseInt(w)*parseInt(l))/1000000000;
    document.getElementById('hw_m3').value = m3.toFixed(9);
}
// attach events
['hw_type','hw_thickness','hw_width','hw_length'].forEach(id=>{
    document.getElementById(id).addEventListener('input', updateCodeAndM3);
});
</script>
