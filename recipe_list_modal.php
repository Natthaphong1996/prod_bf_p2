<?php
// ไฟล์: recipe_list_modal.php
// ถูก include โดย recipe_list.php ดังนั้นจึงสามารถใช้ตัวแปร $customers, $rm_wood, $parts ที่ประกาศไว้ในไฟล์หลักได้
?>
<div class="modal fade" id="recipeModal" tabindex="-1" aria-labelledby="recipeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="recipeModalLabel">สร้างสูตรการผลิตใหม่</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="recipeForm" onsubmit="return false;">
            <input type="hidden" id="recipe_id" name="recipe_id">
            <div class="accordion" id="recipeAccordion">
                
                <!-- ======================= 1. ส่วนของไม้ท่อน (Raw Material) ======================= -->
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingPrimary">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePrimary" aria-expanded="true" aria-controls="collapsePrimary">
                            1. ส่วนของไม้ท่อน (Raw Material)
                        </button>
                    </h2>
                    <div id="collapsePrimary" class="accordion-collapse collapse show" data-bs-parent="#recipeAccordion">
                        <div class="accordion-body">
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">วัตถุดิบ</label><select class="form-select" id="rm_id" name="rm_id"><option value="">-- เลือก --</option><?php if(isset($rm_wood)) foreach($rm_wood as $rm) echo "<option value='{$rm['rm_id']}' data-m3='{$rm['rm_m3']}' data-length='{$rm['rm_length']}'>{$rm['rm_code']}</option>"; ?></select></div>
                                <div class="col-md-6"><label class="form-label">ลูกค้า</label><select class="form-select" id="p_customer_id" name="p_customer_id"><option value="">-- เลือก --</option><?php if(isset($customers)) foreach($customers as $c) echo "<option value='{$c['customer_id']}'>{$c['customer_name']}</option>"; ?></select></div>
                                <div class="col-md-12"><label class="form-label">ชิ้นส่วนที่ได้</label><select class="form-select" id="p_part_id" name="p_part_id"><option value="">-- เลือก --</option><?php if(isset($parts)) foreach($parts as $p) echo "<option value='{$p['part_id']}' data-m3='{$p['part_m3']}' data-length='{$p['part_length']}'>{$p['part_code']} ({$p['part_type']})</option>"; ?></select></div>
                                
                                <div class="col-md-3"><label class="form-label">ขนาดตัด (mm)</label><input type="number" class="form-control" id="p_cut_size" name="cut_size" placeholder="กรอกขนาดตัด"></div>
                                <div class="col-md-3"><label class="form-label">จำนวนครั้งตัด</label><input type="number" class="form-control" id="p_amountOfcut" name="amountOfcut" placeholder="กรอกจำนวนครั้ง"></div>
                                <div class="col-md-3"><label class="form-label">จำนวนครั้งผ่า</label><input type="number" class="form-control" id="p_amountOfSplit" name="amountOfSplit" placeholder="กรอกจำนวนครั้ง"></div>
                                <div class="col-md-3"><label class="form-label">ขนาดเหลือ (mm)</label><input type="number" class="form-control" id="p_remainingSize" name="remainingSize"></div>

                                <div class="col-md-12"><label class="form-label">M3 ที่ได้</label><input type="text" class="form-control calc-result" id="p_m3" name="p_m3" ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ======================= 2. ส่วนของเศษไม้ (Saw Wood) ======================= -->
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingSaw1">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSaw1" aria-expanded="false" aria-controls="collapseSaw1">
                            2. ส่วนของเศษไม้ (Saw Wood)
                        </button>
                    </h2>
                    <div id="collapseSaw1" class="accordion-collapse collapse" data-bs-parent="#recipeAccordion">
                        <div class="accordion-body">
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">ลูกค้า</label><select class="form-select" id="s_customer_id" name="s_customer_id"><option value="">-- เลือก --</option><?php if(isset($customers)) foreach($customers as $c) echo "<option value='{$c['customer_id']}'>{$c['customer_name']}</option>"; ?></select></div>
                                <div class="col-md-6"><label class="form-label">ชิ้นส่วนที่ได้</label><select class="form-select" id="s_part_id" name="s_part_id"><option value="">-- เลือก --</option><?php if(isset($parts)) foreach($parts as $p) echo "<option value='{$p['part_id']}' data-m3='{$p['part_m3']}' data-length='{$p['part_length']}'>{$p['part_code']} ({$p['part_type']})</option>"; ?></select></div>
                                <div class="col-md-3"><label class="form-label">ขนาดตัด (mm)</label><input type="number" class="form-control" id="s_cut_size" name="s_cut_size" placeholder="กรอกขนาดตัด"></div>
                                <div class="col-md-3"><label class="form-label">จำนวนครั้งตัด</label><input type="number" class="form-control" id="s_amountOfcut" name="s_amountOfcut" placeholder="กรอกจำนวนครั้ง"></div>
                                <div class="col-md-3"><label class="form-label">จำนวนครั้งผ่า</label><input type="number" class="form-control" id="s_amountOfSplit" name="s_amountOfSplit" placeholder="กรอกจำนวนครั้ง"></div>
                                <div class="col-md-3"><label class="form-label">ขนาดเหลือ (mm)</label><input type="number" class="form-control" id="s_remainingSize" name="s_remainingSize" ></div>
                                <div class="col-md-12"><label class="form-label">M3 ที่ได้</label><input type="text" class="form-control calc-result" id="s_m3" name="s_m3" ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ======================= 3. ส่วนของเศษไม้ 2 ======================= -->
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingSaw2">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSaw2" aria-expanded="false" aria-controls="collapseSaw2">
                            3. ส่วนของเศษไม้ 2
                        </button>
                    </h2>
                    <div id="collapseSaw2" class="accordion-collapse collapse" data-bs-parent="#recipeAccordion">
                        <div class="accordion-body">
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">ลูกค้า</label><select class="form-select" id="s2_customer_id" name="s2_customer_id"><option value="">-- เลือก --</option><?php if(isset($customers)) foreach($customers as $c) echo "<option value='{$c['customer_id']}'>{$c['customer_name']}</option>"; ?></select></div>
                                <div class="col-md-6"><label class="form-label">ชิ้นส่วนที่ได้</label><select class="form-select" id="s2_part_id" name="s2_part_id"><option value="">-- เลือก --</option><?php if(isset($parts)) foreach($parts as $p) echo "<option value='{$p['part_id']}' data-m3='{$p['part_m3']}' data-length='{$p['part_length']}'>{$p['part_code']} ({$p['part_type']})</option>"; ?></select></div>
                                <div class="col-md-3"><label class="form-label">ขนาดตัด (mm)</label><input type="number" class="form-control" id="s2_cut_size" name="s2_cut_size" placeholder="กรอกขนาดตัด"></div>
                                <div class="col-md-3"><label class="form-label">จำนวนครั้งตัด</label><input type="number" class="form-control" id="s2_amountOfcut" name="s2_amountOfcut" placeholder="กรอกจำนวนครั้ง"></div>
                                <div class="col-md-3"><label class="form-label">จำนวนครั้งผ่า</label><input type="number" class="form-control" id="s2_amountOfSplit" name="s2_amountOfSplit" placeholder="กรอกจำนวนครั้ง"></div>
                                <div class="col-md-3"><label class="form-label">ขนาดเหลือ (mm)</label><input type="number" class="form-control" id="s2_remainingSize" name="s2_remainingSize" ></div>
                                <div class="col-md-12"><label class="form-label">M3 ที่ได้</label><input type="text" class="form-control calc-result" id="s2_m3" name="s2_m3" ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ======================= 4. ส่วนของหัวไม้ (Heavy Wood) ======================= -->
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingHeavy">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseHeavy" aria-expanded="false" aria-controls="collapseHeavy">
                            4. ส่วนของหัวไม้ (Heavy Wood)
                        </button>
                    </h2>
                    <div id="collapseHeavy" class="accordion-collapse collapse" data-bs-parent="#recipeAccordion">
                        <div class="accordion-body">
                           <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">ลูกค้า</label><select class="form-select" id="h_customer_id" name="h_customer_id"><option value="">-- เลือก --</option><?php if(isset($customers)) foreach($customers as $c) echo "<option value='{$c['customer_id']}'>{$c['customer_name']}</option>"; ?></select></div>
                                <div class="col-md-6"><label class="form-label">ชิ้นส่วนที่ได้</label><select class="form-select" id="h_part_id" name="h_part_id"><option value="">-- เลือก --</option><?php if(isset($parts)) foreach($parts as $p) echo "<option value='{$p['part_id']}' data-m3='{$p['part_m3']}' data-length='{$p['part_length']}'>{$p['part_code']} ({$p['part_type']})</option>"; ?></select></div>
                                <div class="col-md-3"><label class="form-label">ขนาดตัด (mm)</label><input type="number" class="form-control" id="h_cut_size" name="h_cut_size" placeholder="กรอกขนาดตัด"></div>
                                <div class="col-md-3"><label class="form-label">จำนวนครั้งตัด</label><input type="number" class="form-control" id="h_amountOfcut" name="h_amountOfcut" placeholder="กรอกจำนวนครั้ง"></div>
                                <div class="col-md-3"><label class="form-label">จำนวนครั้งผ่า</label><input type="number" class="form-control" id="h_amountOfSplit" name="h_amountOfSplit" placeholder="กรอกจำนวนครั้ง"></div>
                                <div class="col-md-3"><label class="form-label">ขนาดเหลือ (mm)</label><input type="number" class="form-control" id="h_remainingSize" name="h_remainingSize" ></div>
                                <div class="col-md-12"><label class="form-label">M3 ที่ได้</label><input type="text" class="form-control calc-result" id="h_m3" name="h_m3" ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ======================= 5. ส่วนของเศษหัวไม้ ======================= -->
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingHeavySaw">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseHeavySaw" aria-expanded="false" aria-controls="collapseHeavySaw">
                            5. ส่วนของเศษหัวไม้
                        </button>
                    </h2>
                    <div id="collapseHeavySaw" class="accordion-collapse collapse" data-bs-parent="#recipeAccordion">
                        <div class="accordion-body">
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">ลูกค้า</label><select class="form-select" id="hs_customer_id" name="hs_customer_id"><option value="">-- เลือก --</option><?php if(isset($customers)) foreach($customers as $c) echo "<option value='{$c['customer_id']}'>{$c['customer_name']}</option>"; ?></select></div>
                                <div class="col-md-6"><label class="form-label">ชิ้นส่วนที่ได้</label><select class="form-select" id="hs_part_id" name="hs_part_id"><option value="">-- เลือก --</option><?php if(isset($parts)) foreach($parts as $p) echo "<option value='{$p['part_id']}' data-m3='{$p['part_m3']}' data-length='{$p['part_length']}'>{$p['part_code']} ({$p['part_type']})</option>"; ?></select></div>
                                <div class="col-md-3"><label class="form-label">ขนาดตัด (mm)</label><input type="number" class="form-control" id="hs_cut_size" name="hs_cut_size" placeholder="กรอกขนาดตัด"></div>
                                <div class="col-md-3"><label class="form-label">จำนวนครั้งตัด</label><input type="number" class="form-control" id="hs_amountOfcut" name="hs_amountOfcut" placeholder="กรอกจำนวนครั้ง"></div>
                                <div class="col-md-3"><label class="form-label">จำนวนครั้งผ่า</label><input type="number" class="form-control" id="hs_amountOfSplit" name="hs_amountOfSplit" placeholder="กรอกจำนวนครั้ง"></div>
                                <div class="col-md-3"><label class="form-label">ขนาดเหลือ (mm)</label><input type="number" class="form-control" id="hs_remainingSize" name="hs_remainingSize" ></div>
                                <div class="col-md-12"><label class="form-label">M3 ที่ได้</label><input type="text" class="form-control calc-result" id="hs_m3" name="hs_m3" ></div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ส่วนสรุปผลรวม -->
            <div class="mt-4 p-3 rounded" style="background-color: #e9ecef;">
                <div class="row g-3 align-items-center">
                    <div class="col-md-4"><label for="total_m3" class="form-label text-success fw-bold">ปริมาตรที่ได้รวม (m³)</label><input type="text" class="form-control form-control-lg text-success" id="total_m3" name="total_m3" ></div>
                    <div class="col-md-4"><label for="total_loss" class="form-label text-danger fw-bold">ปริมาตรสูญเสียรวม (m³)</label><input type="text" class="form-control form-control-lg text-danger" id="total_loss" name="total_loss" ></div>
                    <div class="col-md-4"><label for="total_loss_percent" class="form-label text-danger fw-bold">% สูญเสียรวม</label><input type="text" class="form-control form-control-lg text-danger" id="total_loss_percent" name="total_loss_percent" ></div>
                </div>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
        <button type="button" class="btn btn-primary" onclick="saveRecipe()">บันทึกข้อมูล</button>
      </div>
    </div>
  </div>
</div>
