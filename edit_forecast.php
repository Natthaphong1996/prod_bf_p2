<?php
session_start();
require_once 'config_db.php'; // เรียกใช้ไฟล์สำหรับเชื่อมต่อฐานข้อมูล

// ตรวจสอบว่ามีการส่ง forecast_id มาหรือไม่
if (isset($_GET['forecast_id'])) {
    $forecast_id = $_GET['forecast_id'];

    // ดึงข้อมูล forecast โดยใช้ forecast_id
    $sql = "SELECT f.forecast_id, f.customer_code, f.prod_code, 
                   DATE_FORMAT(f.forecast_date, '%Y-%m') AS forecast_month, 
                   f.forecast_quantity, c.customer_name, p.prod_partno, p.prod_description 
            FROM forecast f
            JOIN customer c ON f.customer_code = c.customer_code
            JOIN prod_list p ON f.prod_code = p.prod_code
            WHERE f.forecast_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $forecast_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // ตรวจสอบว่ามีข้อมูล forecast หรือไม่
    if ($result->num_rows > 0) {
        $forecast = $result->fetch_assoc();
    } else {
        echo "ไม่พบข้อมูล forecast สำหรับ ID ที่ระบุ";
        exit(); // หยุดการทำงานถ้าไม่พบข้อมูล
    }

    // ตรวจสอบการส่งข้อมูลแบบ POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $prod_code = $_POST['prod_code'];
        $forecast_month = $_POST['forecast_month'] . '-01'; // ใช้ format 'YYYY-MM-DD' 
        $forecast_quantity = $_POST['forecast_quantity'];

        // อัปเดตข้อมูล forecast
        $update_sql = "UPDATE forecast SET prod_code = ?, forecast_date = ?, forecast_quantity = ? WHERE forecast_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param('ssii', $prod_code, $forecast_month, $forecast_quantity, $forecast_id);
        
        if ($stmt->execute()) {
            // หากอัปเดตสำเร็จ กลับไปที่หน้า forecast.php
            header('Location: forecast.php');
            exit();
        } else {
            $error = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล";
        }
    }
} else {
    echo "ไม่พบ forecast_id";
    exit(); // หยุดการทำงานถ้าไม่พบ forecast_id ใน URL
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไข Forecast</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <h1>แก้ไข Forecast</h1>

    <!-- แสดงข้อความแจ้งเตือนหากมี -->
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <!-- ฟอร์มสำหรับแก้ไขข้อมูล Forecast -->
    <form action="edit_forecast.php?forecast_id=<?php echo $forecast['forecast_id']; ?>" method="POST">
        <div class="mb-3">
            <label for="customer_name" class="form-label">ชื่อลูกค้า</label>
            <input type="text" class="form-control" id="customer_name" name="customer_name" value="<?php echo htmlspecialchars($forecast['customer_name']); ?>" readonly>
        </div>
        <div class="mb-3">
            <label for="prod_code" class="form-label">รหัสสินค้า</label>
            <input type="text" class="form-control" id="prod_code" name="prod_code" value="<?php echo htmlspecialchars($forecast['prod_code']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="prod_partno" class="form-label">Part No</label>
            <input type="text" class="form-control" id="prod_partno" name="prod_partno" value="<?php echo htmlspecialchars($forecast['prod_partno']); ?>" readonly>
        </div>
        <div class="mb-3">
            <label for="prod_description" class="form-label">รายละเอียด</label>
            <input type="text" class="form-control" id="prod_description" name="prod_description" value="<?php echo htmlspecialchars($forecast['prod_description']); ?>" readonly>
        </div>
        <div class="mb-3">
            <label for="forecast_month" class="form-label">เดือนที่จะผลิต (YYYY-MM)</label>
            <input type="month" class="form-control" id="forecast_month" name="forecast_month" value="<?php echo htmlspecialchars($forecast['forecast_month']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="forecast_quantity" class="form-label">จำนวนที่คาดการณ์</label>
            <input type="number" class="form-control" id="forecast_quantity" name="forecast_quantity" value="<?php echo htmlspecialchars($forecast['forecast_quantity']); ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">อัปเดตข้อมูล</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
