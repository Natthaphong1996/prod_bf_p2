<?php
// เริ่มต้น session
session_start();

// เรียกใช้ไฟล์ config_db.php สำหรับการเชื่อมต่อฐานข้อมูล
require_once 'config_db.php';

// ฟังก์ชันสำหรับสร้างรหัสลูกค้าใหม่
function generateCustomerCode($conn) {
    do {
        // สร้างรหัสลูกค้าแบบสุ่ม โดยขึ้นต้นด้วย C และตามด้วยตัวเลข 4 หลัก
        $customer_code = 'C' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

        // ตรวจสอบว่ารหัสลูกค้าซ้ำกับที่มีในระบบหรือไม่
        $sql = "SELECT COUNT(*) FROM customer WHERE customer_code = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $customer_code);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
    } while ($count > 0); // ทำการสร้างใหม่หากพบว่ามีรหัสซ้ำในระบบ

    return $customer_code;
}

// ตรวจสอบเมื่อมีการส่งข้อมูลฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = $_POST['customer_name'];

    // สร้างรหัสลูกค้าใหม่
    $customer_code = generateCustomerCode($conn);

    // ตรวจสอบว่าชื่อลูกค้าและรหัสลูกค้าไม่ซ้ำกัน
    $sql = "SELECT COUNT(*) FROM customer WHERE customer_name = ? OR customer_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $customer_name, $customer_code);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    if ($count == 0) {
        // ถ้าไม่ซ้ำ ให้บันทึกข้อมูลลูกค้าใหม่
        $sql = "INSERT INTO customer (customer_name, customer_code) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $customer_name, $customer_code);

        if ($stmt->execute()) {
            // เพิ่มข้อมูลสำเร็จ เปลี่ยนเส้นทางไปยังหน้า customers_list.php
            header('Location: customers_list.php');
            exit();
        } else {
            $error = "ไม่สามารถเพิ่มข้อมูลลูกค้าได้ กรุณาลองใหม่อีกครั้ง";
        }

        $stmt->close();
    } else {
        $error = "ชื่อลูกค้าหรือรหัสลูกค้าซ้ำกับข้อมูลในระบบ";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มลูกค้าใหม่</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<body>

<!-- Navbar -->
<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <h1>เพิ่มลูกค้าใหม่</h1>
    
    <!-- แสดงข้อความแจ้งเตือนหากมี -->
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <!-- ฟอร์มเพิ่มข้อมูลลูกค้า -->
    <form action="add_customers.php" method="POST">
        <div class="mb-3">
            <label for="customer_name" class="form-label">ชื่อลูกค้า</label>
            <input type="text" class="form-control" id="customer_name" name="customer_name" required>
        </div>
        <!-- ไม่ต้องให้ผู้ใช้กรอกรหัสลูกค้า -->
        <button type="submit" class="btn btn-primary">เพิ่มลูกค้า</button>
        <!-- ปุ่ม Upload CSV พร้อมไอคอน -->
        <a href="upload_csv_customers.php" class="btn btn-success ms-2">
            <i class="fas fa-file-upload"></i> อัปโหลดจาก CSV
        </a>
    </form>
</div>

<!-- Footer -->
<?php include 'footer.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Font Awesome JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/js/all.min.js"></script>
</body>
</html>
