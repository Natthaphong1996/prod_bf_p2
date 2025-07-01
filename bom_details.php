<?php
require_once 'config_db.php'; // เรียกใช้ไฟล์สำหรับเชื่อมต่อฐานข้อมูล

if (isset($_GET['id'])) {
    $bom_id = $_GET['id'];

    // ดึงข้อมูล BOM
    $sql = "SELECT prod_list.prod_code, bom.parts, bom.nails, prod_list.prod_type, prod_list.prod_partno, prod_list.prod_description
            FROM bom
            LEFT JOIN prod_list ON bom.prod_id = prod_list.prod_id
            WHERE bom.bom_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $bom_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $bom_data = $result->fetch_assoc();

    // คำนวณ M3 รวมของชิ้นส่วน
    $total_m3 = 0;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียด BOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }
        .container {
            margin-top: 50px;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }
        .card-header {
            font-size: 1.2rem;
            font-weight: bold;
        }
        .card-body {
            padding: 20px;
        }
        .alert-success {
            font-size: 1.1rem;
            font-weight: bold;
            margin-top: 20px;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>


<div class="container">
<h2 class="text-center mb-4">รายละเอียด BOM</h2>
    <!-- ข้อมูลผลิตภัณฑ์ -->
    <div class="card">
        <div class="card-header bg-primary text-white">ข้อมูลผลิตภัณฑ์</div>
        <div class="card-body">
            <p><strong>รหัสผลิตภัณฑ์:</strong> <?php echo $bom_data['prod_code']; ?></p>
            <p><strong>ประเภทผลิตภัณฑ์:</strong> <?php echo $bom_data['prod_type']; ?></p>
            <p><strong>หมายเลขชิ้นส่วน:</strong> <?php echo $bom_data['prod_partno']; ?></p>
            <p><strong>รายละเอียด:</strong> <?php echo $bom_data['prod_description']; ?></p>
        </div>
    </div>

    <!-- ข้อมูลชิ้นส่วน -->
    <div class="card">
        <div class="card-header bg-info text-white">ข้อมูลชิ้นส่วน</div>
        <div class="card-body">
            <div class="row">
                <?php
                $parts = json_decode($bom_data['parts'], true);
                foreach ($parts as $part) {
                    // ดึงข้อมูลชิ้นส่วน
                    $part_sql = "SELECT part_code, part_thickness, part_width, part_length FROM part_list WHERE part_id = ?";
                    $part_stmt = $conn->prepare($part_sql);
                    $part_stmt->bind_param("i", $part['part_id']);
                    $part_stmt->execute();
                    $part_result = $part_stmt->get_result();
                    $part_data = $part_result->fetch_assoc();

                    // คำนวณ M3 ของชิ้นส่วนแต่ละชิ้น
                    $m3 = ($part_data['part_thickness'] * $part_data['part_width'] * $part_data['part_length']) / 1000000000;
                    $total_m3 += $m3 * $part['quantity'];
                ?>
                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-title"><strong>รหัสชิ้นส่วน:</strong> <?php echo $part_data['part_code']; ?></h6>
                            <p class="card-text">
                                <strong>ขนาด:</strong> <?php echo $part_data['part_thickness'] . ' x ' . $part_data['part_width'] . ' x ' . $part_data['part_length']; ?> มม.<br>
                                <strong>จำนวน:</strong> <?php echo $part['quantity']; ?><br>
                                <strong>M3:</strong> <?php echo number_format($m3 * $part['quantity'], 6); ?> ม³
                            </p>
                        </div>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- ผลรวม M3 -->
    <div class="alert alert-success text-center">
        <strong>ผลรวม M3 ของชิ้นส่วน:</strong> <?php echo number_format($total_m3, 6); ?> ม³
    </div>

    <!-- ข้อมูลตะปู -->
    <div class="card">
        <div class="card-header bg-warning text-white">ข้อมูลตะปู</div>
        <div class="card-body">
            <div class="row">
                <?php
                $nails = json_decode($bom_data['nails'], true);
                foreach ($nails as $nail) {
                    // ดึงข้อมูลตะปู
                    $nail_sql = "SELECT nail_code FROM nail WHERE nail_id = ?";
                    $nail_stmt = $conn->prepare($nail_sql);
                    $nail_stmt->bind_param("i", $nail['nail_id']);
                    $nail_stmt->execute();
                    $nail_result = $nail_stmt->get_result();
                    $nail_data = $nail_result->fetch_assoc();
                ?>
                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-title"><strong>รหัสตะปู:</strong> <?php echo $nail_data['nail_code']; ?></h6>
                            <p class="card-text">
                                <strong>จำนวน:</strong> <?php echo $nail['quantity']; ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
}
?>
