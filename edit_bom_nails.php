<?php
session_start();

// ✅ ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['department']) || !in_array($_SESSION['department'], ['PROD_ADMIN_STORE','PROD_ADMIN_LV1', 'IT'])) {
    echo "<script>alert('คุณไม่มีสิทธิ์เข้าถึงหน้านี้'); window.location.href='index.php';</script>";
    exit;
}

require_once 'config_db.php';

$bom_id = $_GET['id'];
$sql = "SELECT prod_code, nails FROM bom WHERE bom_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $bom_id);
$stmt->execute();
$result = $stmt->get_result();
$bom = $result->fetch_assoc();
$nails_data = json_decode($bom['nails'], true);

// ดึงรายการตะปูทั้งหมด
$sql_nails = "SELECT nail_id, nail_code FROM nail";
$result_nails = $conn->query($sql_nails);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nails = [];
    foreach ($_POST['nails'] as $index => $nail_id) {
        $quantity = $_POST['nail_quantity'][$index];
        if (!empty($nail_id) && !empty($quantity)) {
            $nails[] = ["nail_id" => $nail_id, "quantity" => $quantity];
        }
    }
    $nails_json = json_encode($nails);
    
    $sql_update = "UPDATE bom SET nails = ?, updated_at = NOW() WHERE bom_id = ?";
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param("si", $nails_json, $bom_id);

    if ($stmt->execute()) {
        echo "<script>alert('อัปเดตตะปูสำเร็จ'); window.location.href='bom_list.php';</script>";
        exit;
    } else {
        $error = "เกิดข้อผิดพลาดในการอัปเดตตะปู!";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขตะปูใน BOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card-custom {
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            border-radius: 12px;
        }
        .card-header {
            background-color: #0d6efd;
            color: white;
            font-weight: bold;
            border-radius: 12px 12px 0 0;
        }
        .select2-container .select2-selection--single {
            height: 38px;
            padding: 6px 12px;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <div class="card card-custom">
        <div class="card-header">
            <h5 class="mb-0">แก้ไขตะปูสำหรับ Product Code: <?php echo $bom['prod_code']; ?></h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <div id="nails-container" class="mb-3">
                    <?php foreach ($nails_data as $nail): ?>
                        <div class="row mb-2 nail-row align-items-center">
                            <div class="col-md-7">
                                <select class="form-control select2" name="nails[]">
                                    <option value="">เลือกตะปู</option>
                                    <?php 
                                    $result_nails->data_seek(0);
                                    while ($row = $result_nails->fetch_assoc()):
                                        $selected = $row['nail_id'] == $nail['nail_id'] ? 'selected' : '';
                                    ?>
                                        <option value="<?= $row['nail_id'] ?>" <?= $selected ?>>
                                            <?= $row['nail_code'] ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="number" name="nail_quantity[]" class="form-control" value="<?= $nail['quantity'] ?>" min="1">
                            </div>
                            <div class="col-md-2 text-end">
                                <button type="button" class="btn btn-danger btn-sm remove-nail"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <button type="button" class="btn btn-secondary" id="add-nail"><i class="bi bi-plus-circle me-1"></i>เพิ่มตะปู</button>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>บันทึก</button>
                    <a href="bom_list.php" class="btn btn-danger"><i class="bi bi-x-circle me-1"></i>ยกเลิก</a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    $('.select2').select2({ placeholder: "ค้นหา..." });

    $('#add-nail').click(function() {
        const nailRow = `
            <div class="row mb-2 nail-row align-items-center">
                <div class="col-md-7">
                    <select class="form-control select2" name="nails[]">
                        <option value="">เลือกตะปู</option>
                        <?php 
                        $result_nails->data_seek(0);
                        while ($row = $result_nails->fetch_assoc()): ?>
                            <option value="<?= $row['nail_id'] ?>"><?= $row['nail_code'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="number" name="nail_quantity[]" class="form-control" min="1">
                </div>
                <div class="col-md-2 text-end">
                    <button type="button" class="btn btn-danger btn-sm remove-nail"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
        `;
        $('#nails-container').append(nailRow);
        $('.select2').select2({ placeholder: "ค้นหา..." });
    });

    $(document).on('click', '.remove-nail', function () {
        $(this).closest('.nail-row').remove();
    });
});
</script>

</body>
</html>

