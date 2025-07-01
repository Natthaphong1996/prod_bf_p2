<?php
// เริ่มต้น session
session_start();

// เรียกใช้ไฟล์ config_db.php สำหรับการเชื่อมต่อฐานข้อมูล
require_once 'config_db.php';

// ตรวจสอบว่ามี ID ถูกส่งมาหรือไม่
if (isset($_GET['id'])) {
    $prod_id = $_GET['id'];

    // ดึงข้อมูลสินค้าจากฐานข้อมูล
    $sql = "SELECT * FROM prod_list WHERE prod_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $prod_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    // ตรวจสอบการอัปเดตข้อมูลเมื่อส่งฟอร์ม
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $prod_code = $_POST['prod_code'];
        $prod_type = $_POST['prod_type'];
        $prod_partno = $_POST['prod_partno'];
        $prod_description = $_POST['prod_description'];
        $customer_id = $_POST['customer_id'];
        $length = $_POST['length'];
        $width = $_POST['width'];
        $thickness = $_POST['thickness'];

        // รับค่า code_cus_size ที่ถูกส่งมาจากฟอร์ม
        $code_cus_size = $_POST['code_cus_size'];

        // ตรวจสอบค่าว่าง
        if (!empty($prod_code) && !empty($prod_type)) {
            // อัปเดตข้อมูลสินค้าในฐานข้อมูล
            $sql = "UPDATE prod_list SET prod_code = ?, prod_type = ?, prod_partno = ?, prod_description = ?, customer_id = ?, length = ?, width = ?, thickness = ?, code_cus_size = ? WHERE prod_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssssiiissi', $prod_code, $prod_type, $prod_partno, $prod_description, $customer_id, $length, $width, $thickness, $code_cus_size, $prod_id);

            if ($stmt->execute()) {
                // ถ้าอัปเดตสำเร็จ เปลี่ยนเส้นทางไปยังหน้ารายการสินค้า
                header('Location: products_list.php');
                exit();
            } else {
                $error = "ไม่สามารถอัปเดตข้อมูลสินค้าได้";
            }
        } else {
            $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
        }
    }
} else {
    echo "ไม่มีข้อมูล ID สำหรับการแก้ไข";
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขสินค้า</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <style>
        /* Custom Styling for Form */
        .form-container {
            background-color: #f8f9fa;
            border-radius: 8px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        .form-title {
            text-align: center;
            margin-bottom: 30px;
            color: #007bff;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .btn-custom {
            width: 100%;
            font-size: 16px;
            padding: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <div class="form-container">
        <h2 class="form-title">แก้ไขข้อมูลสินค้า</h2>

        <!-- แสดงข้อความแจ้งเตือนหากมี -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- ฟอร์มแก้ไขข้อมูลสินค้า -->
        <form action="edit_product.php?id=<?php echo $prod_id; ?>" method="POST" onsubmit="updateCodeCusSize()">
            <div class="form-group">
                <label for="prod_code" class="form-label">รหัสสินค้า</label>
                <input type="text" class="form-control" id="prod_code" name="prod_code" value="<?php echo htmlspecialchars($product['prod_code']); ?>" required>
            </div>

            <div class="form-group">
                <label for="prod_type" class="form-label">ประเภทสินค้า</label>
                <select class="form-control select2" id="prod_type" name="prod_type" required>
                    <option value="">เลือกประเภทสินค้า</option>
                    <?php
                    // ดึงข้อมูลประเภทสินค้าจากฐานข้อมูล
                    $sql_types = "SELECT type_id, type_name FROM prod_type";
                    $result_types = $conn->query($sql_types);
                    while ($row_type = $result_types->fetch_assoc()) {
                        echo '<option value="' . htmlspecialchars($row_type['type_name']) . '" ' . 
                            ($row_type['type_name'] == $product['prod_type'] ? 'selected' : '') . '>' . 
                            htmlspecialchars($row_type['type_name']) . '</option>';
                    }
                    ?>
                </select>
            </div>                              

            <div class="form-group">
                <label for="prod_partno" class="form-label">Part No</label>
                <input type="text" class="form-control" id="prod_partno" name="prod_partno" value="<?php echo htmlspecialchars($product['prod_partno']); ?>">
            </div>

            <div class="form-group">
                <label for="prod_description" class="form-label">รายละเอียดสินค้า</label>
                <textarea class="form-control" id="prod_description" name="prod_description"><?php echo htmlspecialchars($product['prod_description']); ?></textarea>
            </div>

            <div class="form-group">
                <label for="customer_id" class="form-label">ลูกค้า</label>
                <select class="form-control select2" id="customer_id" name="customer_id" required>
                    <option value="">เลือกชื่อลูกค้า</option>
                    <?php
                    // ดึงรายชื่อลูกค้าจากฐานข้อมูล
                    $sql_customers = "SELECT customer_id, customer_name, customer_short_name FROM customer";
                    $result_customers = $conn->query($sql_customers);
                    while ($row_customer = $result_customers->fetch_assoc()) {
                        echo '<option value="' . $row_customer['customer_id'] . '" 
                                data-customer-short-name="' . $row_customer['customer_short_name'] . '" ' . 
                                ($row_customer['customer_id'] == $product['customer_id'] ? 'selected' : '') . '>' . 
                                $row_customer['customer_name'] . '</option>';
                    }
                    ?>
                </select>
            </div>

            <!-- ฟิลด์สำหรับความยาว, ความกว้าง, ความหนา -->
            <div class="form-group">
                <label for="length" class="form-label">ความยาว (mm)</label>
                <input type="number" class="form-control" id="length" name="length" value="<?php echo htmlspecialchars($product['length']); ?>" required>
            </div>

            <div class="form-group">
                <label for="width" class="form-label">ความกว้าง (mm)</label>
                <input type="number" class="form-control" id="width" name="width" value="<?php echo htmlspecialchars($product['width']); ?>" required>
            </div>

            <div class="form-group">
                <label for="thickness" class="form-label">ความหนา (mm)</label>
                <input type="number" class="form-control" id="thickness" name="thickness" value="<?php echo htmlspecialchars($product['thickness']); ?>" required>
            </div>

            <!-- แสดงค่า code_cus_size -->
            <div class="form-group">
                <label for="code_cus_size_display" class="form-label">รหัสขนาดสินค้า (code_cus_size)</label>
                <input type="text" class="form-control" id="code_cus_size_display" value="<?php echo htmlspecialchars($product['code_cus_size']); ?>" disabled>
            </div>

            <!-- ฟิลด์สำหรับ code_cus_size (จะถูกสร้างโดยอัตโนมัติใน backend) -->
            <input type="hidden" name="customer_short_name" id="customer_short_name" value="<?php echo isset($product['customer_short_name']) ? htmlspecialchars($product['customer_short_name']) : ''; ?>">
            <input type="hidden" name="code_cus_size" id="code_cus_size" value="<?php echo isset($product['code_cus_size']) ? htmlspecialchars($product['code_cus_size']) : ''; ?>">

            <button type="submit" class="btn btn-primary btn-custom">อัปเดตข้อมูล</button>
        </form>
    </div>
</div>

<!-- Javascript -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- เรียกใช้งาน Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    $(document).ready(function() {
        // คำนวณ code_cus_size ทันทีเมื่อหน้าโหลด
        updateCodeCusSize();
        
        // Apply Select2 to all select elements with class 'select2'
        $('.select2').select2({
            placeholder: "ค้นหา...",
            allowClear: true
        });

        // เมื่อกรอกข้อมูลความยาว, ความกว้าง, และความหนา
        $("#length, #width, #thickness").on("input change", function() {
            updateCodeCusSize();
        });
    });

    function updateCodeCusSize() {
        // ดึงค่าจากฟิลด์ length, width, thickness
        var length = $("#length").val();
        var width = $("#width").val();
        var thickness = $("#thickness").val();

        // ตรวจสอบว่าค่าทั้งหมดถูกกรอกหรือไม่
        if (length && width && thickness) {
            var customerShortName = $("#customer_id option:selected").data("customer-short-name");
            var code_cus_size = customerShortName + "_" + length + "X" + width + "X" + thickness;

            // อัปเดตค่า code_cus_size ในฟอร์ม
            $("#code_cus_size").val(code_cus_size);
            $("#code_cus_size_display").val(code_cus_size); // แสดงในช่องที่ไม่สามารถแก้ไขได้
        } else {
            $("#code_cus_size_display").val("กรุณากรอกข้อมูลให้ครบถ้วน");
        }
    }
</script>

</body>
</html>

<?php
// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();
?>
