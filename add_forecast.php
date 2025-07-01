<?php
session_start();
require_once 'config_db.php';
require_once 'forecast_functions.php'; // นำเข้าไฟล์ฟังก์ชัน forecast

header('Content-Type: text/html; charset=utf-8');

// ดึงข้อมูลลูกค้าที่มีการใช้งานใน BOM
$sql_customers = "SELECT DISTINCT c.customer_code, c.customer_name
                  FROM customer c
                  JOIN prod_list p ON c.customer_id = p.customer_id
                  JOIN bom b ON p.prod_id = b.prod_id";
$result_customers = $conn->query($sql_customers);

// ตรวจสอบว่ามีการ submit ข้อมูลหรือไม่
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // กรณีที่ 1: อัปโหลดไฟล์ CSV
    if (isset($_POST['upload_csv'])) {
        // เช็คว่าผู้ใช้เลือกไฟล์มาจริงหรือไม่
        if (isset($_FILES['csv_file']) && !empty($_FILES['csv_file']['name'])) {
            // ประมวลผลไฟล์ CSV โดยใช้ฟังก์ชัน processForecastCsv จาก forecast_functions.php
            $errorData = processForecastCsv($conn, $_FILES['csv_file']['tmp_name']);
            
            // หากพบข้อผิดพลาดให้สร้างไฟล์ CSV รายงานข้อผิดพลาดและดาวน์โหลด
            if (!empty($errorData)) {
                outputErrorCsv($errorData);
            } else {
                echo "<script>
                        alert('Upload ข้อมูล Forecast สำเร็จ');
                        window.location.href = 'add_forecast.php';
                      </script>";
                exit();
            }
        } else {
            // กรณีไม่ได้เลือกไฟล์
            echo "<script>
                    alert('กรุณาเลือกไฟล์ CSV ก่อนอัปโหลด');
                    window.location.href = 'add_forecast.php';
                  </script>";
            exit();
        }
    } 
    // กรณีที่ 2: เพิ่มข้อมูลทีละรายการ
    else if (isset($_POST['submit_forecast'])) {
        // รับข้อมูลจากฟอร์ม
        $customer_code     = isset($_POST['customer_code']) ? $_POST['customer_code'] : '';
        $prod_code_input   = isset($_POST['prod_code']) ? $_POST['prod_code'] : '';
        $forecast_date_raw = isset($_POST['forecast_date']) ? $_POST['forecast_date'] : '';
        $forecast_quantity = isset($_POST['forecast_quantity']) ? $_POST['forecast_quantity'] : '';

        // ตรวจสอบข้อมูลที่จำเป็น
        if (empty($customer_code) || empty($prod_code_input) || empty($forecast_date_raw) || empty($forecast_quantity)) {
            echo "<script>
                    alert('กรุณากรอกข้อมูลให้ครบถ้วน');
                    window.location.href = 'add_forecast.php';
                  </script>";
            exit();
        }
        
        // ดึงข้อมูลสินค้าโดยใช้ prod_code (หรือ prod_partno ในกรณีที่ใช้ค่าเดียวกัน)
        $product = getProductDetails($conn, $prod_code_input);
        if (!$product) {
            echo "<script>
                    alert('ไม่พบข้อมูลสินค้า หรือไม่มี BOM');
                    window.location.href = 'add_forecast.php';
                  </script>";
            exit();
        }
        
        // ประมวลผลข้อมูล Forecast
        $resultProcess = processForecast($conn, $product['prod_id'], $forecast_date_raw, $forecast_quantity, $customer_code);
        if (isset($resultProcess['error'])) {
            echo "<script>
                    alert('".$resultProcess['error']."');
                    window.location.href = 'add_forecast.php';
                  </script>";
            exit();
        } else {
            echo "<script>
                    alert('เพิ่มข้อมูล Forecast สำเร็จ');
                    window.location.href = 'add_forecast.php';
                  </script>";
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่ม Forecast</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <h2 class="text-center mb-4">เพิ่ม Forecast ใหม่</h2>
    
    <!-- ฟอร์มสำหรับเพิ่ม Forecast ทีละรายการ -->
    <div class="card mb-4">
        <div class="card-header">
            <strong>เพิ่ม Forecast ทีละรายการ</strong>
        </div>
        <div class="card-body">
            <form id="forecast-form" action="add_forecast.php" method="POST">
                <div class="mb-3">
                    <label for="customer_code" class="form-label">ชื่อลูกค้า</label>
                    <select class="form-control select2" id="customer_code" name="customer_code" required>
                        <option value="">เลือกชื่อลูกค้า</option>
                        <?php while ($row_customer = $result_customers->fetch_assoc()): ?>
                            <option value="<?php echo $row_customer['customer_code']; ?>">
                                <?php echo htmlspecialchars($row_customer['customer_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="prod_code" class="form-label">รหัสสินค้า</label>
                    <select class="form-control select2" id="prod_code" name="prod_code" required>
                        <option value="">เลือกรหัสสินค้า</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="forecast_date" class="form-label">วันที่</label>
                    <input type="date" class="form-control" id="forecast_date" name="forecast_date" required>
                </div>

                <div class="mb-3">
                    <label for="forecast_quantity" class="form-label">จำนวนที่คาดการณ์</label>
                    <input type="number" class="form-control" id="forecast_quantity" name="forecast_quantity" required>
                </div>

                <!-- ปุ่มนี้สำหรับการเพิ่ม Forecast ทีละรายการ -->
                <button type="submit" class="btn btn-primary" name="submit_forecast" value="1">เพิ่ม Forecast</button>
            </form>
        </div>
    </div>

    <!-- ฟอร์มสำหรับอัปโหลดไฟล์ CSV -->
    <div class="card">
        <div class="card-header">
            <strong>อัปโหลดไฟล์ CSV</strong>
        </div>
        <div class="card-body">
            <form id="csv-form" action="add_forecast.php" method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="csv_file" class="form-label">เลือกไฟล์ CSV</label>
                    <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                </div>
                <!-- ปุ่มนี้สำหรับการอัปโหลด CSV -->
                <button type="submit" class="btn btn-success" name="upload_csv" value="1">อัปโหลด CSV</button>
            </form>
        </div>
    </div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        // เปิดใช้งาน Select2 สำหรับ dropdown
        $('#prod_code, #customer_code').select2({
            placeholder: "ค้นหา...",
            allowClear: true,
            templateResult: formatOption,
            templateSelection: formatOption
        });

        function formatOption(option) {
            if (!option.id) {
                return option.text;
            }
            var partno = $(option.element).data('partno') || '';
            var prod_code = option.text.split(' - ')[0];
            return $('<span>' + prod_code + (partno ? ' - ' + partno : '') + '</span>');
        }

        // เมื่อเลือกชื่อลูกค้า ให้โหลดเฉพาะรหัสสินค้าของลูกค้านั้นๆ
        $('#customer_code').change(function() {
            var customerCode = $(this).val();
            $.ajax({
                url: 'get_products_bom.php',
                type: 'GET',
                data: { customer_code: customerCode },
                success: function(data) {
                    // ใส่ option ที่ได้จาก get_products_bom.php ลงใน #prod_code
                    $('#prod_code').html(data).trigger('change.select2');
                }
            });
        });

        // โหลดรหัสสินค้าทั้งหมดเมื่อไม่มีการเลือกชื่อลูกค้า
        if (!$('#customer_code').val()) {
            $.ajax({
                url: 'get_products_bom.php',
                type: 'GET',
                success: function(data) {
                    $('#prod_code').html(data).trigger('change.select2');
                }
            });
        }

        // เมื่อเลือก prod_code ให้เติมข้อมูล customer_code อัตโนมัติ (ถ้ามี)
        $('#prod_code').change(function() {
            var selectedOption = $(this).find(':selected');
            var customerCode = selectedOption.data('customer-code');
            if (customerCode) {
                $('#customer_code').val(customerCode).trigger('change');
            }
        });
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
