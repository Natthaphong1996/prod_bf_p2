<?php
// เริ่มต้น session
session_start();

// เรียกใช้ไฟล์ config_db.php สำหรับการเชื่อมต่อฐานข้อมูล
require_once 'config_db.php';

// ตรวจสอบว่ามี ID ถูกส่งมาหรือไม่
if (isset($_GET['id'])) {
    $customer_id = $_GET['id'];

    // ดึงข้อมูลลูกค้าจากฐานข้อมูล
    $sql = "SELECT * FROM customer WHERE customer_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();

    // ตรวจสอบการอัปเดตข้อมูลเมื่อส่งฟอร์ม
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $customer_name = $_POST['customer_name'];
        $customer_short_name = $_POST['customer_short_name'];  // รับค่าจากฟอร์ม

        // ตรวจสอบค่าว่าง
        if (!empty($customer_name) && !empty($customer_short_name)) {
            // คำสั่ง SQL สำหรับอัปเดตข้อมูลลูกค้า (ยกเว้น customer_code)
            $sql = "UPDATE customer SET customer_name = ?, customer_short_name = ? WHERE customer_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $customer_name, $customer_short_name, $customer_id);

            // ดำเนินการอัปเดตข้อมูล
            if ($stmt->execute()) {
                // อัปเดตสำเร็จ เปลี่ยนเส้นทางกลับไปยังหน้า customers_list.php
                header('Location: customers_list.php');
                exit();
            } else {
                $error = "ไม่สามารถอัปเดตข้อมูลลูกค้าได้";
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
    <title>Edit Customer</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<!-- Navbar -->
<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <h1>Edit Customer</h1>
    
    <!-- แสดงข้อความแจ้งเตือนหากมี -->
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <!-- ฟอร์มแก้ไขข้อมูลลูกค้า -->
    <form action="edit_customer.php?id=<?php echo $customer['customer_id']; ?>" method="POST">
        <div class="mb-3">
            <label for="customer_name" class="form-label">Customer Name</label>
            <input type="text" class="form-control" id="customer_name" name="customer_name" value="<?php echo htmlspecialchars($customer['customer_name']); ?>" required>
        </div>
        
        <!-- เพิ่มช่องสำหรับแก้ไข customer_short_name -->
        <div class="mb-3">
            <label for="customer_short_name" class="form-label">Customer Short Name</label>
            <input type="text" class="form-control" id="customer_short_name" name="customer_short_name" value="<?php echo htmlspecialchars($customer['customer_short_name']); ?>" required>
        </div>

        <div class="mb-3">
            <label for="customer_code" class="form-label">Customer Code (ไม่สามารถแก้ไขได้)</label>
            <input type="text" class="form-control" id="customer_code" name="customer_code" value="<?php echo htmlspecialchars($customer['customer_code']); ?>" readonly>
        </div>

        <button type="submit" class="btn btn-primary">Update Customer</button>
    </form>
</div>

<!-- Footer -->
<?php include 'footer.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();
?>
