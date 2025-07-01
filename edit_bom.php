<?php
session_start();
require_once 'config_db.php'; // Database connection

// ตั้งค่า LINE Notify Token
$line_notify_token = 'Aofj2WMjuH6E3wwnRbcQyMt7W1CpJRFNyn1myvldbFg'; // ใส่ Access Token ของ LINE Notify ที่นี่

// ฟังก์ชันส่งการแจ้งเตือน LINE Notify
function sendLineNotify($message, $token) {
    $line_api = "https://notify-api.line.me/api/notify";
    $headers = array(
        'Content-Type: application/x-www-form-urlencoded',
        "Authorization: Bearer $token"
    );
    $data = http_build_query(array(
        'message' => $message
    ));
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $line_api);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

// Fetching the current BOM data
$bom_id = $_GET['id'];
$sql = "SELECT * FROM bom WHERE bom_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $bom_id);
$stmt->execute();
$result = $stmt->get_result();
$bom = $result->fetch_assoc();

// Decode the parts and nails data from JSON format
$parts_data = json_decode($bom['parts'], true);
$nails_data = json_decode($bom['nails'], true);

// Fetching Product Codes from prod_list
$sql_products = "SELECT prod_code FROM prod_list";
$result_products = $conn->query($sql_products);

// Fetching Parts from part_list
$sql_parts = "SELECT part_id, part_code, part_thickness, part_width, part_length, part_type FROM part_list";
$result_parts = $conn->query($sql_parts);

// Fetching Nails from nail table
$sql_nails = "SELECT nail_id, nail_code FROM nail";
$result_nails = $conn->query($sql_nails);

// Handling the form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $prod_code = $_POST['prod_code'];
    
    // กรองข้อมูล parts ที่ว่างเปล่าออก
    $parts = [];
    foreach ($_POST['parts'] as $index => $part_id) {
        $quantity = $_POST['part_quantity'][$index];
        if (!empty($part_id) && !empty($quantity)) {
            $parts[] = ["part_id" => $part_id, "quantity" => $quantity];
        }
    }
    $parts_json = json_encode($parts);

    // กรองข้อมูล nails ที่ว่างเปล่าออก
    $nails = [];
    foreach ($_POST['nails'] as $index => $nail_id) {
        $quantity = $_POST['nail_quantity'][$index];
        if (!empty($nail_id) && !empty($quantity)) {
            $nails[] = ["nail_id" => $nail_id, "quantity" => $quantity];
        }
    }
    $nails_json = json_encode($nails);

    // อัปเดตข้อมูล BOM ลงในฐานข้อมูล
    $sql_update = "UPDATE bom SET prod_code = ?, parts = ?, nails = ?, updated_at = NOW() WHERE bom_id = ?";
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param("sssi", $prod_code, $parts_json, $nails_json, $bom_id);
    
    if ($stmt->execute()) {
        // ส่งการแจ้งเตือน LINE Notify
        $message = "BOM Product code : " . $prod_code . " ถูกแก้ไขแล้ว";
        sendLineNotify($message, $line_notify_token);

        echo "<script>
            alert(" . json_encode("Update BOM Product: $prod_code สำเร็จ") . ");
            window.location.href = 'bom_list.php';
        </script>";
        exit;
    } else {
        $error = "เกิดข้อผิดพลาดในการแก้ไข BOM!";
    }
}
?>


<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit BOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            margin-top: 50px;
        }
        .card-custom {
            padding: 20px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            margin-bottom: 30px;
        }
        .card-header {
            font-size: 1.25rem;
            font-weight: bold;
            background-color: #007bff;
            color: #fff;
            padding: 10px;
            border-radius: 10px 10px 0 0;
        }
        .form-label {
            font-weight: bold;
        }
        .form-section {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card card-custom">
                <div class="card-header">แก้ไข BOM</div>
                <div class="card-body">
                    <!-- ฟอร์มสำหรับแก้ไข BOM -->
                    <form action="edit_bom.php?id=<?php echo $bom_id; ?>" method="POST">
                        <!-- ส่วนของรหัสสินค้า -->
                        <div class="form-section">
                            <label for="prod_code" class="form-label">รหัสสินค้า</label>
                            <select class="form-control select2" id="prod_code" name="prod_code" required>
                                <option value="">เลือก Product Code</option>
                                <?php while ($row_product = $result_products->fetch_assoc()): ?>
                                    <option value="<?php echo $row_product['prod_code']; ?>" <?php echo $row_product['prod_code'] == $bom['prod_code'] ? 'selected' : ''; ?>>
                                        <?php echo $row_product['prod_code']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- ส่วนของชิ้นส่วน -->
                        <div class="form-section">
                            <label for="parts" class="form-label">ชิ้นส่วน</label>
                            <div id="parts-container">
                                <?php foreach ($parts_data as $index => $part): ?>
                                    <div class="row mb-3 part-row">
                                        <div class="col-md-1">
                                            <span class="part-number"><?php echo $index + 1; ?></span>
                                        </div>
                                        <div class="col-md-7">
                                            <select class="form-control select2" name="parts[]">
                                                <option value="">เลือกชิ้นส่วน</option>
                                                <?php 
                                                $result_parts->data_seek(0);
                                                while ($row_part = $result_parts->fetch_assoc()): 
                                                    $size = $row_part['part_thickness'] . 'x' . $row_part['part_width'] . 'x' . $row_part['part_length'];
                                                ?>
                                                    <option value="<?php echo $row_part['part_id']; ?>" <?php echo $row_part['part_id'] == $part['part_id'] ? 'selected' : ''; ?>>
                                                        <?php echo $row_part['part_code'] . " (" . $row_part['part_type'] . ") - " . $size; ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <input type="number" name="part_quantity[]" class="form-control" value="<?php echo $part['quantity']; ?>" min="1">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-secondary" id="add-part">เพิ่มชิ้นส่วน</button>
                        </div>

                        <!-- ส่วนของตะปู -->
                        <div class="form-section">
                            <label for="nails" class="form-label">ตะปู</label>
                            <div id="nails-container">
                                <?php foreach ($nails_data as $nail): ?>
                                    <div class="row mb-3 nail-row">
                                        <div class="col-md-8">
                                            <select class="form-control select2" name="nails[]">
                                                <option value="">เลือกตะปู</option>
                                                <?php 
                                                $result_nails->data_seek(0); // Reset pointer
                                                while ($row_nail = $result_nails->fetch_assoc()): ?>
                                                    <option value="<?php echo $row_nail['nail_id']; ?>" <?php echo $row_nail['nail_id'] == $nail['nail_id'] ? 'selected' : ''; ?>>
                                                        <?php echo $row_nail['nail_code']; ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <input type="number" name="nail_quantity[]" class="form-control" value="<?php echo $nail['quantity']; ?>" min="1">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-secondary" id="add-nail">เพิ่มตะปู</button>
                        </div>

                        <button type="submit" class="btn btn-primary">บันทึกการเปลี่ยนแปลง</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ฟังก์ชันเพิ่มฟิลด์ Part และ Nail ใหม่ -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function() {
        $('.select2').select2({
            placeholder: "ค้นหา...",
            allowClear: true
        });
    });

    document.getElementById('add-nail').addEventListener('click', function() {
        const nailContainer = document.getElementById('nails-container');
        const nailRow = document.createElement('div');
        nailRow.classList.add('row', 'mb-3', 'nail-row');
        nailRow.innerHTML = `
            <div class="col-md-8">
                <select class="form-control select2" name="nails[]">
                    <option value="">เลือกตะปู</option>
                    <?php 
                    $result_nails->data_seek(0); // Reset pointer
                    while ($row_nail = $result_nails->fetch_assoc()): ?>
                        <option value="<?php echo $row_nail['nail_id']; ?>"><?php echo $row_nail['nail_code']; ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-4">
                <input type="number" name="nail_quantity[]" class="form-control" placeholder="จำนวน" min="1">
            </div>`;
        nailContainer.appendChild(nailRow);

        $('.select2').select2({
            placeholder: "ค้นหา...",
            allowClear: true
        });
    });


    function updatePartNumbers() {
    document.querySelectorAll('#parts-container .part-number').forEach((element, index) => {
        element.textContent = index + 1;
    });
    }

    document.getElementById('add-part').addEventListener('click', function() {
        const partContainer = document.getElementById('parts-container');
        const partRow = document.createElement('div');
        partRow.classList.add('row', 'mb-3', 'part-row');
        partRow.innerHTML = `
            <div class="col-md-1">
                <span class="part-number"></span>
            </div>
            <div class="col-md-7">
                <select class="form-control select2" name="parts[]">
                    <option value="">เลือกชิ้นส่วน</option>
                    <?php 
                    $result_parts->data_seek(0); 
                    while ($row_part = $result_parts->fetch_assoc()): 
                        $size = $row_part['part_thickness'] . 'x' . $row_part['part_width'] . 'x' . $row_part['part_length'];
                    ?>
                        <option value="<?php echo $row_part['part_id']; ?>">
                            <?php echo $row_part['part_code'] . " (" . $row_part['part_type'] . ") - " . $size; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-4">
                <input type="number" name="part_quantity[]" class="form-control" placeholder="จำนวน" min="1">
            </div>`;
        partContainer.appendChild(partRow);

        // รีเฟรช Select2 และลำดับหมายเลขใหม่
        $('.select2').select2({
            placeholder: "ค้นหา...",
            allowClear: true
        });
        updatePartNumbers();
    });



</script>
</body>
</html>
