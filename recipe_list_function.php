<?php // ไฟล์: recipe_list_function.php ?>
<script>
    $(document).ready(function() {
        // เปิดใช้งาน Select2 กับ Dropdown ทั้งหมดใน Modal
        $('#recipeModal .form-select').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#recipeModal') // สำคัญมาก: ทำให้ search box ใน modal ใช้งานได้
        });

        // เพิ่ม Event Listener สำหรับการคำนวณอัตโนมัติ
        $('#recipeModal').on('change', '.form-select', runAllCalculations);
    });

    // ฟังก์ชันสำหรับคำนวณแต่ละส่วน
    function calculateSection(inputM3, inputLength, partSelector, prefix) {
        const partOption = $(partSelector).find('option:selected');
        const partLength = parseFloat(partOption.data('length')) || 0;
        const partM3 = parseFloat(partOption.data('m3')) || 0;
        
        // ล้างค่าเก่าก่อน
        const fields = ['cut_size', 'amountOfcut', 'remainingSize', 'm3', 'm3_loss', 'percent_loss'];
        fields.forEach(field => $(`#${prefix}_${field}`).val(''));
        
        let remainingLength = 0;
        let lossM3ForCalc = 0;
        let outputM3 = 0;

        if (inputLength > 0 && partLength > 0 && inputM3 > 0 && partM3 > 0) {
            const amountOfCut = Math.floor(inputLength / partLength);
            remainingLength = inputLength - (partLength * amountOfCut);
            
            outputM3 = partM3 * amountOfCut;
            lossM3ForCalc = inputM3 - outputM3;
            const percentLoss = (lossM3ForCalc / inputM3) * 100;

            $(`#${prefix}_cut_size`).val(partLength);
            $(`#${prefix}_amountOfcut`).val(amountOfCut);
            $(`#${prefix}_remainingSize`).val(remainingLength.toFixed(2));
            $(`#${prefix}_m3`).val(outputM3.toFixed(6));
            $(`#${prefix}_m3_loss`).val(lossM3ForCalc.toFixed(6));
            $(`#${prefix}_percent_loss`).val(percentLoss.toFixed(2));
        }
        
        return { 
            length: remainingLength, 
            m3: (inputM3 > 0 && inputLength > 0 ? (remainingLength / inputLength) * inputM3 : 0),
            loss: lossM3ForCalc,
            output: outputM3
        };
    }

    // ฟังก์ชันหลักสำหรับเรียกการคำนวณทั้งหมดตามลำดับ
    function runAllCalculations() {
        const rmOption = $('#rm_id').find('option:selected');
        const rmLength = parseFloat(rmOption.data('length')) || 0;
        const rmM3 = parseFloat(rmOption.data('m3')) || 0;
        
        $('#total_m3, #total_loss, #total_loss_percent').val('');

        const p = calculateSection(rmM3, rmLength, '#p_part_id', 'p');
        const s = calculateSection(p.m3, p.length, '#s_part_id', 's');
        const s2 = calculateSection(s.m3, s.length, '#s2_part_id', 's2');
        const h = calculateSection(s2.m3, s2.length, '#h_part_id', 'h');
        const hs = calculateSection(h.m3, h.length, '#hs_part_id', 'hs');

        const totalOutput = p.output + s.output + s2.output + h.output + hs.output;
        const totalLoss = p.loss + s.loss + s2.loss + h.loss + hs.loss;
        
        $('#total_m3').val(totalOutput.toFixed(6));
        $('#total_loss').val(totalLoss.toFixed(6));

        if (rmM3 > 0) {
            const totalLossPercent = (totalLoss / rmM3) * 100;
            $('#total_loss_percent').val(totalLossPercent.toFixed(2));
        }
    }

    const recipeModal = new bootstrap.Modal(document.getElementById('recipeModal'));
    
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
                        for (const key in data) {
                            const element = $(`#${key}`);
                            if (element.is('select')) {
                                element.val(data[key]).trigger('change');
                            } else {
                                element.val(data[key]);
                            }
                        }
                        setTimeout(runAllCalculations, 100); 
                    } else {
                        Swal.fire('เกิดข้อผิดพลาด', response.message, 'error');
                    }
                }
            });
        } else {
            $('#recipeModalLabel').text('สร้างสูตรการผลิตใหม่');
        }
        recipeModal.show();
    }

    function saveRecipe() {
        const form = $('#recipeForm');
        const recipeId = $('#recipe_id').val();
        const action = recipeId ? 'edit' : 'add';

        $.ajax({
            url: 'process_recipe.php',
            type: 'POST',
            data: form.serialize() + '&action=' + action,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    recipeModal.hide();
                    Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: response.message, timer: 1500, showConfirmButton: false })
                    .then(() => location.reload());
                } else {
                    Swal.fire('เกิดข้อผิดพลาด', response.message, 'error');
                }
            }
        });
    }

    function deleteRecipe(recipeId) {
        Swal.fire({
            title: 'คุณแน่ใจหรือไม่?',
            text: "คุณต้องการลบสูตรการผลิตนี้ใช่หรือไม่?",
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
                    data: { action: 'delete', recipe_id: recipeId },
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
</script>
