<?php
// ภาษา: PHP
// ชื่อไฟล์: modals_sw.php
?>

<!-- Modal Form Add/Edit -->
<div class="modal fade" id="modalForm" tabindex="-1" aria-labelledby="modalFormLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" id="frmSW">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalTitle">Form SW</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" id="action" value="">
          <input type="hidden" name="sw_id" id="sw_id" value="">

          <div class="mb-3">
            <label for="sw_code" class="form-label">Code</label>
            <input type="text" class="form-control" id="sw_code" name="sw_code" readonly required>
          </div>

          <div class="mb-3">
            <label for="sw_type" class="form-label">Type</label>
            <select class="form-select" id="sw_type" name="sw_type" required>
              <option value="K">ไม้อบ</option>
              <option value="G">ไม้ยังไม่ได้อบ</option>
            </select>
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label for="sw_thickness" class="form-label">Thickness</label>
              <input type="number" class="form-control" id="sw_thickness" name="sw_thickness" required>
            </div>
            <div class="col-md-4 mb-3">
              <label for="sw_width" class="form-label">Width</label>
              <input type="number" class="form-control" id="sw_width" name="sw_width" required>
            </div>
            <div class="col-md-4 mb-3">
              <label for="sw_length" class="form-label">Length</label>
              <input type="number" class="form-control" id="sw_length" name="sw_length" required>
            </div>
          </div>

          <div class="mb-3">
            <label for="sw_m3" class="form-label">M³</label>
            <input type="number" step="0.001" class="form-control" id="sw_m3" name="sw_m3" readonly required>
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

<!-- Modal Delete Confirmation -->
<div class="modal fade" id="modalDelete" tabindex="-1" aria-labelledby="modalDeleteLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <form method="post">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalDeleteLabel">ยืนยันลบข้อมูล</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p>คุณแน่ใจหรือไม่ที่จะลบข้อมูลรายการนี้?</p>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="sw_id" id="del_sw_id" value="">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-danger">ลบ</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ฟังก์ชัน modal
function openAddModal() {
    document.getElementById('modalTitle').innerText = 'เพิ่ม SW';
    document.getElementById('frmSW').reset();
    document.getElementById('action').value = 'add';
    updateCodeAndM3();
}
function openEditModal(data) {
    document.getElementById('modalTitle').innerText = 'แก้ไข SW';
    document.getElementById('action').value = 'edit';
    document.getElementById('sw_id').value = data.sw_id;
    // set fields
    document.getElementById('sw_type').value = data.sw_type;
    document.getElementById('sw_thickness').value = data.sw_thickness;
    document.getElementById('sw_width').value = data.sw_width;
    document.getElementById('sw_length').value = data.sw_length;
    updateCodeAndM3();
    document.getElementById('sw_m3').value = data.sw_m3;
}
function openDeleteModal(id) {
    document.getElementById('del_sw_id').value = id;
}

// คำนวณ Code และ M3 อัตโนมัติ
function updateCodeAndM3() {
    const type = document.getElementById('sw_type').value;
    const t = document.getElementById('sw_thickness').value.padStart(3,'0');
    const w = document.getElementById('sw_width').value.padStart(3,'0');
    const l = document.getElementById('sw_length').value.padStart(4,'0');
    // สร้าง Code
    if (t && w && l) document.getElementById('sw_code').value = `${type}${t}${w}${l}`;
    // คำนวณ m3
    if (t && w && l) {
        const m3 = (parseInt(t)*parseInt(w)*parseInt(l))/1000000000;
        document.getElementById('sw_m3').value = m3.toFixed(9);
    }
}

// event listeners
['sw_type','sw_thickness','sw_width','sw_length'].forEach(id => {
    document.getElementById(id).addEventListener('input', updateCodeAndM3);
});
</script>