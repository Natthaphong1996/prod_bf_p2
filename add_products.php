<?php
session_start();
require_once 'config_db.php'; // เรียกใช้ไฟล์สำหรับเชื่อมต่อฐานข้อมูล

// ดึงรายการประเภทสินค้าเพื่อใช้ในฟอร์ม
$sql_types = "SELECT type_id, type_code, type_name FROM prod_type";
$result_types = $conn->query($sql_types);

// ดึงรายการชื่อลูกค้าจากตาราง customer เพื่อใช้ในฟอร์ม
$sql_customers = "SELECT customer_id, customer_name, customer_short_name FROM customer";
$result_customers = $conn->query($sql_customers);

// ฟังก์ชันสำหรับอ่านและอัปโหลดข้อมูลจาก CSV
if (isset($_POST['upload_csv'])) {
    $csv_file = $_FILES['csv_file']['tmp_name'];

    if (($handle = fopen($csv_file, "r")) !== FALSE) {
        // ข้าม A1 ซึ่งเป็นหัวตาราง
        fgetcsv($handle);

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $prod_code = trim($data[0]);  // อ่าน prod_code
            $customer_name = trim($data[1]); // อ่าน customer_name
            $prod_type = trim($data[2]); // อ่าน prod_type
            $prod_partno = trim($data[3]); // อ่าน prod_partno
            $prod_description = trim($data[4]); // อ่าน prod_description

            // ข้ามรายการที่ prod_code และ prod_partno เป็นค่าว่าง
            if (empty($prod_code) && empty($prod_partno)) {
                continue; // ข้ามรายการนี้
            }

            // ค้นหาว่า customer_name มีอยู่แล้วในฐานข้อมูลหรือไม่
            $customer_sql = "SELECT customer_id, customer_short_name FROM customer WHERE customer_name = ?";
            $stmt = $conn->prepare($customer_sql);
            $stmt->bind_param("s", $customer_name);
            $stmt->execute();
            $stmt->bind_result($customer_id, $customer_short_name);
            $stmt->fetch();
            $stmt->close();

            // ถ้าหาไม่เจอลูกค้า ให้ข้ามรายการนี้ไป
            if (empty($customer_id)) {
                continue;
            }

            // ใช้ REGEXP เพื่อตัดข้อมูลความยาว, ความกว้าง, ความหนา จาก prod_description
            if (preg_match('/(\d{1,4})X(\d{1,4})X(\d{1,4})/', $prod_description, $matches)) {
                $length = $matches[1];
                $width = $matches[2];
                $thickness = $matches[3];

                // สร้างค่า code_cus_size
                $code_cus_size = $customer_short_name . "_" . $length . "X" . $width . "X" . $thickness;

                // ถ้า prod_code มีข้อมูล ให้ตรวจสอบว่ามีอยู่แล้วในระบบหรือไม่
                $check_code_sql = "SELECT COUNT(*) FROM prod_list WHERE prod_code = ?";
                $stmt = $conn->prepare($check_code_sql);
                $stmt->bind_param("s", $prod_code);
                $stmt->execute();
                $stmt->bind_result($count_code);
                $stmt->fetch();
                $stmt->close();

                // ถ้า prod_code มีอยู่แล้ว ให้ข้ามรายการนี้
                if ($count_code > 0) {
                    continue;
                }

                // ถ้าทุกอย่างผ่านการตรวจสอบ ให้เพิ่มข้อมูลเข้าสู่ระบบ
                $insert_sql = "INSERT INTO prod_list (prod_code, prod_type, prod_partno, prod_description, customer_id, length, width, thickness, code_cus_size) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_sql);
                $stmt->bind_param("ssssiisss", $prod_code, $prod_type, $prod_partno, $prod_description, $customer_id, $length, $width, $thickness, $code_cus_size);
                $stmt->execute();
                $stmt->close();
            }
        }
        fclose($handle);
        $success = "อัปโหลด CSV เรียบร้อยแล้ว!";
    }
}

// ตรวจสอบว่ามีการส่งฟอร์มเข้ามาหรือไม่สำหรับการเพิ่มข้อมูลปกติ
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_product'])) {
    $prod_code = $_POST['prod_code'];
    $prod_type = $_POST['prod_type'];
    $prod_partno = $_POST['prod_partno'];
    $prod_description = $_POST['prod_description'];
    $customer_id = $_POST['customer_id'];
    $length = $_POST['length'];
    $width = $_POST['width'];
    $thickness = $_POST['thickness'];
    $customer_short_name = $_POST['customer_short_name'];

    // ตรวจสอบว่า prod_code นี้มีอยู่ในฐานข้อมูลหรือไม่
    $check_code_sql = "SELECT COUNT(*) FROM prod_list WHERE prod_code = ?";
    $stmt = $conn->prepare($check_code_sql);
    $stmt->bind_param("s", $prod_code);
    $stmt->execute();
    $stmt->bind_result($count_code);
    $stmt->fetch();
    $stmt->close();

    // ถ้า prod_code มีอยู่แล้ว ให้แสดงข้อความแจ้งเตือนเป็น popup
    if ($count_code > 0) {
        $error = "รหัสสินค้าซ้ำกับที่มีอยู่ในระบบแล้ว!";
        echo "<script>alert('$error');</script>"; // แสดง popup alert
    } else {
        // สร้างค่า code_cus_size
        $code_cus_size = $customer_short_name . "_" . $length . "X" . $width . "X" . $thickness;

        // เพิ่มข้อมูลสินค้าใหม่เข้าสู่ฐานข้อมูล
        $sql = "INSERT INTO prod_list (prod_code, prod_type, prod_partno, prod_description, customer_id, length, width, thickness, code_cus_size) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssiisss", $prod_code, $prod_type, $prod_partno, $prod_description, $customer_id, $length, $width, $thickness, $code_cus_size);

        if ($stmt->execute()) {
            $success = "เพิ่มสินค้าเรียบร้อยแล้ว!";
        } else {
            $error = "เกิดข้อผิดพลาดในการเพิ่มสินค้า!";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มสินค้าใหม่</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Add Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .card-custom {
            padding: 20px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .container {
            margin-top: 30px;
        }
        .btn-custom {
            font-size: 16px;
            padding: 10px 20px;
            font-weight: bold;
        }
        .highlighted-title {
            background-color: #25d7fd;
            padding: 10px;
            border-radius: 5px;
            color: #333;
            text-align: center;
            font-weight: bold;
        }
        .form-section {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-6">
            <!-- ฟอร์มสำหรับเพิ่มสินค้าใหม่ -->
            <div class="card card-custom">
                <h3 class="highlighted-title">เพิ่มสินค้าใหม่</h3>
                <form action="add_products.php" method="POST">
                    <div class="form-section">
                        <label for="prod_code" class="form-label">รหัสสินค้า</label>
                        <input type="text" class="form-control" id="prod_code" name="prod_code" required>
                    </div>
                    <div class="form-section">
                        <label for="prod_type" class="form-label">ประเภทสินค้า</label>
                        <select class="form-control select2" id="prod_type" name="prod_type" required>
                            <option value="">เลือกประเภทสินค้า</option>
                            <?php while ($row_type = $result_types->fetch_assoc()): ?>
                                <option value="<?php echo $row_type['type_name']; ?>"><?php echo $row_type['type_name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-section">
                        <label for="prod_partno" class="form-label">Part Number</label>
                        <input type="text" class="form-control" id="prod_partno" name="prod_partno" required>
                    </div>
                    <div class="form-section">
                        <label for="prod_description" class="form-label">คำอธิบายสินค้า</label>
                        <textarea class="form-control" id="prod_description" name="prod_description" rows="3" required></textarea>
                    </div>
                    <div class="form-section">
                        <label for="customer_id" class="form-label">ชื่อลูกค้า</label>
                        <select class="form-control select2" id="customer_id" name="customer_id" required>
                            <option value="">เลือกชื่อลูกค้า</option>
                            <?php while ($row_customer = $result_customers->fetch_assoc()): ?>
                                <option value="<?php echo $row_customer['customer_id']; ?>"
                                        data-customer-short-name="<?php echo $row_customer['customer_short_name']; ?>">
                                    <?php echo $row_customer['customer_name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <!-- ฟิลด์สำหรับความยาว, ความกว้าง, ความหนา -->
                    <div class="form-section">
                        <label for="length" class="form-label">ความยาว (mm)</label>
                        <input type="number" class="form-control" id="length" name="length" required>
                    </div>
                    <div class="form-section">
                        <label for="width" class="form-label">ความกว้าง (mm)</label>
                        <input type="number" class="form-control" id="width" name="width" required>
                    </div>
                    <div class="form-section">
                        <label for="thickness" class="form-label">ความหนา (mm)</label>
                        <input type="number" class="form-control" id="thickness" name="thickness" required>
                    </div>
                    <!-- ฟิลด์สำหรับ code_cus_size (จะถูกสร้างโดยอัตโนมัติใน backend) -->
                    <input type="hidden" name="customer_short_name" id="customer_short_name">
                    <input type="hidden" name="code_cus_size" id="code_cus_size">
                    <button type="submit" class="btn btn-primary btn-custom" name="add_product">เพิ่มสินค้า</button>
                </form>
            </div>
        </div>

        <div class="col-md-6">
            <!-- ฟอร์มสำหรับอัปโหลด CSV -->
            <div class="card card-custom">
                <h3 class="highlighted-title">อัปโหลดสินค้าผ่าน CSV</h3>
                <form action="add_products.php" method="POST" enctype="multipart/form-data">
                    <div class="form-section">
                        <label for="csv_file" class="form-label">เลือกไฟล์ CSV</label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" required>
                    </div>
                    <button type="submit" class="btn btn-success btn-custom" name="upload_csv">อัปโหลด CSV</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        // Apply Select2 to all select elements with class 'select2'
        $('.select2').select2({
            placeholder: "ค้นหา...",
            allowClear: true
        });

        // เมื่อกรอกข้อมูลความยาว, ความกว้าง, และความหนา
        $("#length, #width, #thickness, #customer_id").on("input change", function() {
            var length = $("#length").val();
            var width = $("#width").val();
            var thickness = $("#thickness").val();
            var customerShortName = $("#customer_id option:selected").data("customer-short-name");
            var code_cus_size = customerShortName + "_" + length + "X" + width + "X" + thickness;
            $("#customer_short_name").val(customerShortName);
            $("#code_cus_size").val(code_cus_size);
        });
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
