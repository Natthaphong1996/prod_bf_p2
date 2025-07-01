<?php
session_start();

// ✅ ตรวจสอบสิทธิ์การใช้งาน
if (!isset($_SESSION['department']) || !in_array($_SESSION['department'], ['PROD_ADMIN','IT'])) {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงหน้านี้'); window.location.href='index.php';</script>";
    exit;
}

require_once 'config_db.php';

$bom_id = $_GET['id'];
$sql = "SELECT prod_code, parts FROM bom WHERE bom_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $bom_id);
$stmt->execute();
$result = $stmt->get_result();
$bom = $result->fetch_assoc();
$parts_data = json_decode($bom['parts'], true);

// ดึงรายการ Part ทั้งหมด
$sql_parts = "SELECT part_id, part_code, part_thickness, part_width, part_length, part_type FROM part_list";
$result_parts = $conn->query($sql_parts);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $parts = [];
    foreach ($_POST['parts'] as $index => $part_id) {
        $quantity = $_POST['part_quantity'][$index];
        if (!empty($part_id) && !empty($quantity)) {
            $parts[] = ["part_id" => $part_id, "quantity" => $quantity];
        }
    }
    $parts_json = json_encode($parts);
    
    $sql_update = "UPDATE bom SET parts = ?, updated_at = NOW() WHERE bom_id = ?";
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param("si", $parts_json, $bom_id);

    if ($stmt->execute()) {
        echo "<script>alert('อัปเดต Part สำเร็จ'); window.location.href='bom_list.php';</script>";
        exit;
    } else {
        $error = "เกิดข้อผิดพลาดในการอัปเดต Part!";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไข Part ใน BOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <h4>แก้ไขชิ้นส่วน (Part) สำหรับ Product Code: <?php echo $bom['prod_code']; ?></h4>
    <form method="POST">
        <div id="parts-container" class="mb-3">
            <?php foreach ($parts_data as $index => $part): ?>
                <div class="row mb-2 part-row">
                    <div class="col-md-7">
                        <select class="form-control select2" name="parts[]">
                            <option value="">เลือกชิ้นส่วน</option>
                            <?php 
                            $result_parts->data_seek(0);
                            while ($row = $result_parts->fetch_assoc()):
                                $selected = $row['part_id'] == $part['part_id'] ? 'selected' : '';
                                $size = "{$row['part_thickness']}x{$row['part_width']}x{$row['part_length']}";
                            ?>
                                <option value="<?= $row['part_id'] ?>" <?= $selected ?>>
                                    <?= "{$row['part_code']} ({$row['part_type']}) - {$size}" ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="number" name="part_quantity[]" class="form-control" value="<?= $part['quantity'] ?>" min="1">
                    </div>
                    <div class="col-md-1 d-flex align-items-center">
                        <button type="button" class="btn btn-danger btn-sm remove-part"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" class="btn btn-secondary mb-3" id="add-part">เพิ่มชิ้นส่วน</button>
        <br>
        <button type="submit" class="btn btn-primary">บันทึก</button>
        <a href="bom_list.php" class="btn btn-danger">ยกเลิก</a>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.select2').select2({ placeholder: "ค้นหา..." });

    $('#add-part').click(function() {
        const partRow = `
            <div class="row mb-2 part-row">
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
                    <input type="number" name="part_quantity[]" class="form-control" min="1">
                </div>
                <div class="col-md-1 d-flex align-items-center">
                    <button type="button" class="btn btn-danger btn-sm remove-part"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>`;
        $('#parts-container').append(partRow);
        $('.select2').select2({ placeholder: "ค้นหา..." });
    });

    $(document).on('click', '.remove-part', function () {
        $(this).closest('.part-row').remove();
    });
});
</script>

</body>
</html>
