<?php
session_start();
require_once 'config_db.php';

$sql_products = "SELECT prod_code, prod_id, code_cus_size FROM prod_list";
$result_products = $conn->query($sql_products);

$sql_parts = "SELECT part_id, part_code, part_thickness, part_width, part_length, part_type FROM part_list";
$result_parts = $conn->query($sql_parts);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_bom'])) {
    if (strpos($_POST['prod_code'], '|') !== false) {
        list($prod_id, $prod_code, $code_cus_size) = explode('|', $_POST['prod_code']);
    } else {
        echo "ข้อมูลไม่ถูกต้อง";
        exit;
    }

    // ✅ เช็คว่า prod_code ซ้ำหรือยัง
    $sql_check = "SELECT COUNT(*) as total FROM bom WHERE prod_code = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("s", $prod_code);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result()->fetch_assoc();
    if ($result_check['total'] > 0) {
        echo "<script>alert('มี BOM สำหรับ Product นี้อยู่แล้ว!'); window.history.back();</script>";
        exit;
    }

    $parts_array = [];
    if (isset($_POST['parts']) && isset($_POST['part_quantity'])) {
        for ($i = 0; $i < count($_POST['parts']); $i++) {
            if (!empty($_POST['parts'][$i]) && !empty($_POST['part_quantity'][$i])) {
                $parts_array[] = [
                    'part_id' => $_POST['parts'][$i],
                    'quantity' => $_POST['part_quantity'][$i]
                ];
            }
        }
    }

    $parts = json_encode($parts_array);
    $nails = json_encode([]); // ตะปูว่าง

    $sql = "INSERT INTO bom (prod_id, prod_code, parts, nails) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $prod_id, $prod_code, $parts, $nails);

    if ($stmt->execute()) {
        echo "<script>alert('เพิ่ม BOM สำเร็จ'); window.location.href='bom_list.php';</script>";
    } else {
        echo "เกิดข้อผิดพลาดในการเพิ่ม BOM!";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เพิ่ม BOM - Part เท่านั้น</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">เพิ่ม BOM (เฉพาะ Part)</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <!-- เลือกสินค้า -->
                <div class="mb-3">
                    <label class="form-label">รหัสสินค้า</label>
                    <select class="form-control select2" name="prod_code" required>
                        <option value="">เลือก Product Code</option>
                        <?php while ($row = $result_products->fetch_assoc()): ?>
                            <option value="<?= $row['prod_id'] . '|' . $row['prod_code'] . '|' . $row['code_cus_size'] ?>">
                                <?= $row['prod_code'] . ' | ' . $row['code_cus_size'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- รายการ Part -->
                <div id="parts-container">
                    <div class="row mb-3 part-row">
                        <div class="col-md-7">
                            <select class="form-control select2" name="parts[]">
                                <option value="">เลือกชิ้นส่วน</option>
                                <?php 
                                $result_parts->data_seek(0);
                                while ($row = $result_parts->fetch_assoc()):
                                    $size = "{$row['part_thickness']}x{$row['part_width']}x{$row['part_length']}";
                                ?>
                                    <option value="<?= $row['part_id'] ?>">
                                        <?= "{$row['part_code']} ({$row['part_type']}) - {$size}" ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="number" name="part_quantity[]" class="form-control" placeholder="จำนวน" min="1">
                        </div>
                        <div class="col-md-1 d-flex align-items-center">
                            <button type="button" class="btn btn-danger btn-sm remove-part"><i class="bi bi-x-lg"></i></button>
                        </div>
                    </div>
                </div>

                <!-- ปุ่มเพิ่ม Part -->
                <button type="button" class="btn btn-secondary mb-3" id="add-part">เพิ่มชิ้นส่วน</button>

                <!-- ปุ่มส่งฟอร์ม -->
                <div class="d-flex gap-2">
                    <button type="submit" name="add_bom" class="btn btn-primary">เพิ่ม BOM</button>
                    <a href="bom_list.php" class="btn btn-danger">ยกเลิก</a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function () {
    $('.select2').select2({ placeholder: "ค้นหา..." });

    // เพิ่มแถว part
    $('#add-part').click(function () {
        const partRow = `
            <div class="row mb-3 part-row">
                <div class="col-md-7">
                    <select class="form-control select2" name="parts[]">
                        <option value="">เลือกชิ้นส่วน</option>
                        <?php 
                        $result_parts->data_seek(0);
                        while ($row = $result_parts->fetch_assoc()):
                            $size = "{$row['part_thickness']}x{$row['part_width']}x{$row['part_length']}";
                        ?>
                            <option value="<?= $row['part_id'] ?>">
                                <?= "{$row['part_code']} ({$row['part_type']}) - {$size}" ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="number" name="part_quantity[]" class="form-control" placeholder="จำนวน" min="1">
                </div>
                <div class="col-md-1 d-flex align-items-center">
                    <button type="button" class="btn btn-danger btn-sm remove-part"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
        `;
        $('#parts-container').append(partRow);
        $('.select2').select2({ placeholder: "ค้นหา..." });
    });

    // ลบแถว part
    $(document).on('click', '.remove-part', function () {
        $(this).closest('.part-row').remove();
    });

    // ✅ ตรวจสอบ Part ซ้ำก่อน submit
    $('form').on('submit', function (e) {
        const selectedParts = [];
        let hasDuplicate = false;

        $('select[name="parts[]"]').each(function () {
            const val = $(this).val();
            if (val) {
                if (selectedParts.includes(val)) {
                    hasDuplicate = true;
                }
                selectedParts.push(val);
            }
        });

        if (hasDuplicate) {
            alert('ห้ามเลือกชิ้นส่วนซ้ำกันใน BOM');
            e.preventDefault(); // ยกเลิก submit
        }
    });
});
</script>

</body>
</html>
