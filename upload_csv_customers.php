<?php
// เริ่มต้น session
session_start();

// เรียกใช้ไฟล์ config_db.php สำหรับการเชื่อมต่อฐานข้อมูล
require_once 'config_db.php';

// ฟังก์ชันสำหรับสร้าง Customer Code ใหม่
function generateCustomerCode($conn) {
    do {
        // สร้างรหัสลูกค้าแบบสุ่ม โดยขึ้นต้นด้วย C และตามด้วยตัวเลข 4 หลัก
        $customer_code = 'C' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

        // ตรวจสอบว่า Customer Code ซ้ำกับที่มีในระบบหรือไม่
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

// ตรวจสอบว่ามีการอัปโหลดไฟล์หรือไม่
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['csv_file']['tmp_name'];
        
        // เปิดไฟล์ CSV
        if (($handle = fopen($fileTmpPath, 'r')) !== false) {
            // ข้ามบรรทัดแรก (หัวตาราง)
            fgetcsv($handle);

            $emptyRows = 0; // ตัวนับบรรทัดว่าง
            while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                $customer_name = trim($data[0]); // ช่อง A คือ Customer Name
                
                // ถ้าช่อง Customer Name ว่าง ให้ข้าม
                if (empty($customer_name)) {
                    $emptyRows++; // เพิ่มจำนวนบรรทัดว่าง
                    if ($emptyRows > 30) { // หยุดถ้าพบบรรทัดว่างมากกว่า 30 บรรทัดติดต่อกัน
                        break;
                    }
                    continue;
                }

                // รีเซ็ตจำนวนบรรทัดว่างเมื่อพบข้อมูล
                $emptyRows = 0;

                // ตรวจสอบว่าชื่อลูกค้าไม่ซ้ำในระบบ
                $sql = "SELECT COUNT(*) FROM customer WHERE customer_name = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('s', $customer_name);
                $stmt->execute();
                $stmt->bind_result($count);
                $stmt->fetch();
                $stmt->close();

                // ถ้าชื่อไม่ซ้ำ ให้สร้าง Customer Code และเพิ่มข้อมูล
                if ($count == 0) {
                    $customer_code = generateCustomerCode($conn); // สร้าง Customer Code ใหม่

                    // เพิ่มข้อมูลลูกค้าใหม่ในฐานข้อมูล
                    $sql = "INSERT INTO customer (customer_name, customer_code) VALUES (?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('ss', $customer_name, $customer_code);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            fclose($handle);

            // เพิ่มข้อมูลสำเร็จ
            $success = "เพิ่มข้อมูลจากไฟล์ CSV เรียบร้อยแล้ว";
        } else {
            $error = "ไม่สามารถเปิดไฟล์ CSV ได้";
        }
    } else {
        $error = "กรุณาเลือกไฟล์ CSV สำหรับอัปโหลด";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload CSV - Customers</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<!-- Navbar -->
<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <h1>Upload CSV for Customers</h1>

    <!-- แสดงข้อความแจ้งเตือน -->
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
    <?php elseif (isset($success)): ?>
        <div class="alert alert-success">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <!-- ฟอร์มสำหรับอัปโหลดไฟล์ CSV -->
    <form action="upload_csv_customers.php" method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="csv_file" class="form-label">Select CSV file</label>
            <input class="form-control" type="file" id="csv_file" name="csv_file" accept=".csv" required>
        </div>
        <button type="submit" class="btn btn-primary">Upload CSV</button>
    </form>
</div>

<!-- Footer -->
<?php include 'footer.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
