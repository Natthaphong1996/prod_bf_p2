<?php
// ภาษา: PHP
// ชื่อไฟล์: recipe_modals.php
// คอมเมนต์: Modal สำหรับเพิ่ม/แก้ไข สูตรการตัดผ่าไม้ พร้อม Searchable Dropdown และการเติมข้อมูลอัตโนมัติเมื่อแก้ไข

include_once __DIR__ . '/config_db.php';

// ดึงข้อมูลสำหรับ dropdown
$rmSql   = "SELECT rm_id, rm_code, rm_thickness, rm_width, rm_length, rm_type FROM rm_wood_list ORDER BY rm_code";
$partSql = "SELECT part_id, part_code, part_thickness, part_width, part_length, part_type FROM part_list ORDER BY part_code";
$hwSql   = "SELECT hw_id, hw_code, hw_thickness, hw_width, hw_length, hw_type FROM hw_wood_list ORDER BY hw_code";
$swSql   = "SELECT sw_id, sw_code, sw_thickness, sw_width, sw_length, sw_type FROM sw_wood_list ORDER BY sw_code";

$rmList   = $conn->query($rmSql);
$partList = $conn->query($partSql);
$hwList   = $conn->query($hwSql);
$swList   = $conn->query($swSql);
?>

<!-- Select2 CSS & JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Modal: เพิ่ม/แก้ไข สูตรการตัดผ่าไม้ -->
<div class="modal fade" id="recipeModal" tabindex="-1" aria-labelledby="recipeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form id="frmRecipe" method="post" action="recipe_action.php" class="modal-content">
      <!-- เก็บ action และ recipe_id -->
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="recipe_id" value="">

      <div class="modal-header">
        <h5 class="modal-title" id="recipeModalLabel">เพิ่ม/แก้ไข สูตรการตัดผ่าไม้</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <!-- ส่วนที่ 1: เลือกไม้ท่อน -->
        <h6 class="mb-3">ส่วนที่ 1: เลือกไม้ท่อน</h6>
        <div class="row g-3 align-items-center mb-4">
          <label class="col-sm-2 form-label" for="rm_id">ไม้ท่อน</label>
          <div class="col-sm-4">
            <select class="form-control select2" id="rm_id" name="rm_id" data-placeholder="ค้นหาไม้ท่อน..." required>
              <option value=""></option>
              <?php while ($rm = $rmList->fetch_assoc()): ?>
                <option value="<?= $rm['rm_id'] ?>"><?= htmlspecialchars("{$rm['rm_code']} ({$rm['rm_thickness']}×{$rm['rm_width']}×{$rm['rm_length']}) | {$rm['rm_type']}") ?></option>
              <?php endwhile; ?>
            </select>
          </div>

          <!-- เพิ่ม input สำหรับ rm_qty และ rm_comment -->
          <label class="col-sm-2 form-label" for="rm_qty">จำนวน</label>
          <div class="col-sm-4">
            <input type="number" class="form-control" id="rm_qty" name="rm_qty" placeholder="จำนวนไม้ท่อน" min="1" value="1" required>
          </div>
        </div>
        <div class="mb-4">
          <label for="rm_comment" class="form-label">หมายเหตุไม้ท่อน (ถ้ามี)</label>
          <input type="text" class="form-control" id="rm_comment" name="rm_comment" placeholder="หมายเหตุ">
        </div>
        <hr>

        <!-- ส่วนที่ 2: ผลลัพธ์หลังตัด -->
        <h6 class="mb-3">ส่วนที่ 2: ผลลัพธ์หลังตัด</h6>

        <!-- งานหลัก -->
        <div class="mb-3 row g-3 align-items-center">
          <label class="form-label" for="part_id">งานหลัก</label>
          <div class="col-sm-3">
            <select class="form-control select2" id="part_id" name="part_id" data-placeholder="ค้นหางานหลัก..." required>
              <option value=""></option>
              <?php while ($p = $partList->fetch_assoc()): ?>
                <option value="<?= $p['part_id'] ?>"><?= htmlspecialchars("{$p['part_code']} ({$p['part_thickness']}×{$p['part_width']}×{$p['part_length']}) | {$p['part_type']}") ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-sm-3">
            <input type="number" class="form-control" id="part_cut" name="part_cut" placeholder="จำนวนที่ต้อง ตัด" min="1" required>
          </div>
          <div class="col-sm-3">
            <input type="number" class="form-control" id="part_split" name="part_split" placeholder="จำนวนที่ต้อง ผ่า" min="0" required>
          </div>
          <div class="col-sm-3">
            <input type="number" class="form-control" id="part_qty" name="part_qty" placeholder="จำนวน" min="1" required>
          </div>
          <div class="col-sm-12">
            <input type="text" class="form-control" id="part_comment" name="part_comment" placeholder="หมายเหตุ (ถ้ามี)">
          </div>
        </div>

        <!-- หัวไม้ -->
        <div class="mb-3 row g-3 align-items-center">
          <label class="form-label" for="hw_id">หัวไม้</label>
          <div class="col-sm-3">
            <select class="form-control select2" id="hw_id" name="hw_id" data-placeholder="ค้นหาหัวไม้...">
              <option value=""></option>
              <?php while ($h = $hwList->fetch_assoc()): ?>
                <option value="<?= $h['hw_id'] ?>"><?= htmlspecialchars("{$h['hw_code']} ({$h['hw_thickness']}×{$h['hw_width']}×{$h['hw_length']}) | {$h['hw_type']}") ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-sm-3">
            <input type="number" class="form-control" id="hw_cut" name="hw_cut" placeholder="จำนวนที่ต้อง ตัด" min="1">
          </div>
          <div class="col-sm-3">
            <input type="number" class="form-control" id="hw_split" name="hw_split" placeholder="จำนวนที่ต้อง ผ่า" min="0">
          </div>
          <div class="col-sm-3">
            <input type="number" class="form-control" id="hw_qty" name="hw_qty" placeholder="จำนวน" min="1">
          </div>
          <div class="col-sm-12">
            <input type="text" class="form-control" id="hw_comment" name="hw_comment" placeholder="หมายเหตุ (ถ้ามี)">
          </div>
        </div>

        <!-- เศษไม้ -->
        <div class="mb-3 row g-3 align-items-center">
          <label class="form-label" for="sw_id">เศษไม้</label>
          <div class="col-sm-3">
            <select class="form-control select2" id="sw_id" name="sw_id" data-placeholder="ค้นหาเศษไม้...">
              <option value=""></option>
              <?php while ($s = $swList->fetch_assoc()): ?>
                <option value="<?= $s['sw_id'] ?>"><?= htmlspecialchars("{$s['sw_code']} ({$s['sw_thickness']}×{$s['sw_width']}×{$s['sw_length']}) | {$s['sw_type']}") ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-sm-3">
            <input type="number" class="form-control" id="sw_cut" name="sw_cut" placeholder="จำนวนที่ต้อง ตัด" min="0">
          </div>
          <div class="col-sm-3">
            <input type="number" class="form-control" id="sw_split" name="sw_split" placeholder="จำนวนที่ต้อง ผ่า" min="0">
          </div>
          <div class="col-sm-3">
            <input type="number" class="form-control" id="sw_qty" name="sw_qty" placeholder="จำนวน" min="0">
          </div>
          <div class="col-sm-12">
            <input type="text" class="form-control" id="sw_comment" name="sw_comment" placeholder="หมายเหตุ (ถ้ามี)">
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
        <button type="submit" class="btn btn-primary">บันทึกสูตร</button>
      </div>
    </form>
  </div>
</div>

<script>
// เติมข้อมูลเมื่อเปิด modal
var recipeModal = document.getElementById('recipeModal');
recipeModal.addEventListener('show.bs.modal', function(event) {
  var btn  = event.relatedTarget;
  var form = recipeModal.querySelector('#frmRecipe');
  var actionInput = form.querySelector('input[name="action"]');
  var idInput     = form.querySelector('input[name="recipe_id"]');

  if (btn.getAttribute('data-recipe-id')) {
    let actionInput = form.querySelector('input[name="action"]');
    let idInput     = form.querySelector('input[name="recipe_id"]');
    actionInput.value = 'update';
    idInput.value     = btn.getAttribute('data-recipe-id');

    // raw wood
    jQuery(form).find('#rm_id').val(btn.dataset.rmId).trigger('change');
    form.rm_qty.value       = btn.dataset.rmQty;
    form.rm_comment.value   = btn.dataset.rmComment;

    // part
    jQuery(form).find('#part_id').val(btn.dataset.partId).trigger('change');
    form.part_qty.value     = btn.dataset.partQty;
    form.part_comment.value = btn.dataset.partComment;
    form.part_cut.value   = btn.dataset.partCut   || '';
    form.part_split.value = btn.dataset.partSplit || '';

    // head wood
    jQuery(form).find('#hw_id').val(btn.dataset.hwId).trigger('change');
    form.hw_qty.value       = btn.dataset.hwQty;
    form.hw_comment.value   = btn.dataset.hwComment;
    form.hw_cut.value   = btn.dataset.hwCut   || '';
    form.hw_split.value = btn.dataset.hwSplit || '';

    // scrap wood
    jQuery(form).find('#sw_id').val(btn.dataset.swId).trigger('change');
    form.sw_qty.value       = btn.dataset.swQty;
    form.sw_comment.value   = btn.dataset.swComment;
    form.sw_cut.value   = btn.dataset.swCut   || '';
    form.sw_split.value = btn.dataset.swSplit || '';
  } else {
    form.reset();
    actionInput.value = 'add';
    idInput.value     = '';
    jQuery(form).find('.select2').val(null).trigger('change');
  }
});

// ฟังก์ชัน matcher สำหรับ select2 รองรับการค้นหาโดยโค้ด และรูปแบบ dimension ทั้ง 'T x W x L', 'TxWxL' หรือใช้สัญลักษณ์คูณ '×'
function matchByDimensionAndCode(params, data) {
  // ถ้าไม่มี term ให้แสดงทุก option
  if (!params.term || params.term.trim() === '') {
    return data;
  }

  var term = params.term.toLowerCase();
  var text = (data.text || '').toLowerCase();

  // Normalize both '×' (unicode multiply) and 'x' letter: ลบเว้นวรรครอบ ๆ และแทนด้วย 'x'
  var dimNormalized = text.replace(/\s*[×x]\s*/g, 'x');
  var termNormalized = term.replace(/\s*[×x]\s*/g, 'x');

  // ค้นหาจากโค้ด (text) หรือตรวจ dimension ที่ normalize แล้ว
  if (text.indexOf(term) > -1 || dimNormalized.indexOf(termNormalized) > -1) {
    return data;
  }

  // ไม่ match ให้ return null เพื่อ hide
  return null;
}

// เริ่มต้น Select2 พร้อม matcher
jQuery(document).ready(function() {
  jQuery('.select2').select2({
    dropdownParent: jQuery('#recipeModal'),
    width: '100%',
    placeholder: function() { return jQuery(this).data('placeholder'); },
    allowClear: true,
    matcher: matchByDimensionAndCode
  });
});


</script>