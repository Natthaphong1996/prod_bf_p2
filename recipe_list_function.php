<?php // ไฟล์: recipe_list_function.php ?>
<script>
    const recipeModalInstance = new bootstrap.Modal(document.getElementById('recipeModal'));

    $(document).ready(function() {
        $('#recipeModal .form-select').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#recipeModal')
        });

        // Event Listener ที่จะทำงานเมื่อมีการเปลี่ยนแปลงค่าในฟอร์ม
        const eventTriggers = 'change input';
        const selectors = [
            '.form-select',
            'input[id$="_cut_size"]',
            'input[id$="_amountOfcut"]',
            'input[id$="_amountOfSplit"]'
        ].join(', ');

        $('#recipeModal').on(eventTriggers, selectors, runAllCalculations);

        // ★★★ FIX for aria-hidden warning ★★★
        // จัดการ Focus เมื่อ Modal ปิด เพื่อแก้ไขข้อผิดพลาด aria-hidden
        const recipeModalEl = document.getElementById('recipeModal');
        recipeModalEl.addEventListener('hidden.bs.modal', function (event) {
            // เมื่อ modal ปิดสนิทแล้ว ให้ย้าย focus ออกจาก element ที่เคยอยู่ใน modal
            // เพื่อป้องกันข้อผิดพลาด accessibility
            document.body.focus();
        });
    });
    
    function openRecipeModal(recipeId = null) {
        $('#recipeForm')[0].reset();
        $('#recipeModal .form-select').val('').trigger('change');
        
        if (recipeId) {
            $('#recipeModalLabel').text('แก้ไขสูตรการผลิต');
            $.ajax({
                url: 'process_recipe.php',
                type: 'POST',
                data: { action: 'get_recipe', recipe_id: recipeId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        const nameMapping = {
                            'cut_size': 'p_cut_size', 'amountOfcut': 'p_amountOfcut',
                            'amountOfSplit': 'p_amountOfSplit', 'remainingSize': 'p_remainingSize'
                        };
                        for (const key in data) {
                            const elementId = nameMapping[key] || key;
                            const element = $(`#${elementId}`);
                            if (element.length) {
                                if (element.is('select')) {
                                    element.val(data[key]).trigger('change.select2');
                                } else {
                                    element.val(data[key]);
                                }
                            }
                        }
                        setTimeout(runAllCalculations, 200); 
                    } else {
                        Swal.fire('เกิดข้อผิดพลาด', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้', 'error');
                }
            });
        } else {
            $('#recipeModalLabel').text('สร้างสูตรการผลิตใหม่');
        }
        recipeModalInstance.show();
    }

    function saveRecipe() {
        const form = $('#recipeForm');
        const recipeId = $('#recipe_id').val();
        const action = recipeId ? 'edit_recipe' : 'add_recipe';

        $.ajax({
            url: 'process_recipe.php',
            type: 'POST',
            data: form.serialize() + '&action=' + action,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    recipeModalInstance.hide();
                    Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: response.message, timer: 1500, showConfirmButton: false })
                    .then(() => location.reload());
                } else {
                    Swal.fire('เกิดข้อผิดพลาด', response.message || 'ไม่สามารถบันทึกข้อมูลได้', 'error');
                }
            },
            error: function() {
                Swal.fire('เกิดข้อผิดพลาด', 'การเชื่อมต่อล้มเหลว', 'error');
            }
        });
    }

    function deleteRecipe(recipeId) {
       Swal.fire({
            title: 'คุณแน่ใจหรือไม่?',
            text: "คุณต้องการลบสูตรการผลิต ID: " + recipeId + " ใช่หรือไม่?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'ใช่, ลบเลย!',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'process_recipe.php',
                    type: 'POST',
                    data: { action: 'delete_recipe', recipe_id: recipeId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('ลบสำเร็จ!', 'สูตรการผลิตถูกลบเรียบร้อยแล้ว', 'success')
                            .then(() => location.reload());
                        } else {
                            Swal.fire('เกิดข้อผิดพลาด', response.message, 'error');
                        }
                    }
                });
            }
        });
    }

    /**
     * ★★★ [ปรับปรุงใหม่] ฟังก์ชันสำหรับคำนวณในแต่ละ Section (แบบขนาน) ★★★
     * คำนวณ M3 ที่ได้ของแต่ละส่วนเป็นอิสระต่อกัน
     * @param {string} prefix - Prefix ของ ID ในแต่ละ Section (เช่น 'p', 's', 's2')
     * @returns {object} - คืนค่า { output } ซึ่งคือ M3 ที่คำนวณได้
     */
    function calculateSection(prefix) {
        // 1. ดึงข้อมูล M3 ของชิ้นส่วนที่เลือก
        const partSelector = `#${prefix}_part_id`;
        const partOption = $(partSelector).find('option:selected');
        const partM3 = parseFloat(partOption.data('m3')) || 0;

        // 2. อ่านค่าจากฟิลด์ที่ User กรอกสำหรับ Section นี้
        const amountOfCut = parseInt($(`#${prefix}_amountOfcut`).val(), 10) || 0;
        const amountOfSplit = parseInt($(`#${prefix}_amountOfSplit`).val(), 10) || 1; // ถ้าว่าง ให้เป็น 1

        // 3. คำนวณ M3 ที่ได้ (OUTPUT) ตามสูตรใหม่
        let outputM3 = 0;
        if (partM3 > 0 && amountOfCut > 0) {
             outputM3 = (amountOfCut * amountOfSplit) * partM3;
        }

        // 4. แสดงผลลัพธ์ M3 ที่ได้ ในช่องของ Section นั้นๆ
        $(`#${prefix}_m3`).val(outputM3.toFixed(6));
        
        // 5. คืนค่า M3 ที่ได้สำหรับนำไปคำนวณผลรวม
        return { 
            output: outputM3
        };
    }

    /**
     * ★★★ [ปรับปรุงใหม่] ฟังก์ชันหลักสำหรับเรียกการคำนวณทั้งหมด ★★★
     */
    function runAllCalculations() {
        // 1. ดึง M3 ของวัตถุดิบตั้งต้น (INPUT)
        const rmOption = $('#rm_id').find('option:selected');
        const rmM3 = parseFloat(rmOption.data('m3')) || 0;
        
        // 2. เคลียร์ค่าสรุปผลรวมเก่า
        $('#total_m3, #total_loss, #total_loss_percent').val('');

        // 3. คำนวณ OUTPUT ของแต่ละส่วนอย่างเป็นอิสระ
        const p_output = calculateSection('p').output;
        const s_output = calculateSection('s').output;
        const s2_output = calculateSection('s2').output;
        const h_output = calculateSection('h').output;
        const hs_output = calculateSection('hs').output;

        // 4. คำนวณผลรวมของ OUTPUT ทั้งหมด
        const totalOutput = p_output + s_output + s2_output + h_output + hs_output;

        // 5. คำนวณค่าสูญเสียรวม
        const totalLoss = rmM3 > 0 ? rmM3 - totalOutput : 0;
        
        // 6. แสดงผลในส่วนสรุป
        $('#total_m3').val(totalOutput.toFixed(6));
        $('#total_loss').val(totalLoss.toFixed(6));

        if (rmM3 > 0) {
            const totalLossPercent = (totalLoss / rmM3) * 100;
            // ป้องกันไม่ให้ % ติดลบ
            $('#total_loss_percent').val(Math.max(0, totalLossPercent).toFixed(2));
        } else {
             $('#total_loss_percent').val('0.00');
        }
    }
</script>
