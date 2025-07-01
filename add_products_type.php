<?php
session_start();
require_once 'config_db.php'; // ใช้ไฟล์ config_db.php สำหรับเชื่อมต่อฐานข้อมูล

// ฟังก์ชันสำหรับสร้างรหัสประเภทสินค้าอัตโนมัติ
function generateTypeCode($conn) {
    // ค้นหารหัสประเภทสินค้าล่าสุด
    $sql = "SELECT type_code FROM prod_type ORDER BY type_id DESC LIMIT 1";
    $result = $conn->query($sql);
    $lastCode = $result->fetch_assoc();

    if ($lastCode) {
        // เพิ่มเลขท้ายของรหัสโดยบวกเพิ่ม 1
        $lastNumber = (int)substr($lastCode['type_code'], 1);
        $newNumber = $lastNumber + 1;
        $newCode = 'T' . str_pad($newNumber, 4, '0', STR_PAD_LEFT); // ตัวอย่างรหัส: T0001, T0002, ...
    } else {
        // ถ้าไม่มีข้อมูลในตาราง ให้เริ่มต้นที่ T0001
        $newCode = 'T0001';
    }

    return $newCode;
}

// ฟังก์ชันสำหรับอ่านและอัปโหลดข้อมูลจากไฟล์ CSV
if (isset($_POST['upload_csv'])) {
    $csv_file = $_FILES['csv_file']['tmp_name'];

    if (($handle = fopen($csv_file, "r")) !== FALSE) {
        // ข้ามแถว A1 ซึ่งเป็นส่วนหัวของตาราง
        fgetcsv($handle);

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $type_name = trim($data[0]); // อ่านข้อมูลจากคอลัมน์ A2 เป็นต้นไป

            if (!empty($type_name)) {
                // ตรวจสอบว่า type_name มีอยู่ในฐานข้อมูลแล้วหรือไม่
                $check_sql = "SELECT COUNT(*) FROM prod_type WHERE type_name = ?";
                $stmt = $conn->prepare($check_sql);
                $stmt->bind_param("s", $type_name);
                $stmt->execute();
                $stmt->bind_result($count);
                $stmt->fetch();
                $stmt->close();

                if ($count == 0) {
                    // ถ้าไม่มีค่า type_name ซ้ำ ให้สร้างรหัสประเภทสินค้าใหม่
                    $type_code = generateTypeCode($conn);
                    $insert_sql = "INSERT INTO prod_type (type_code, type_name) VALUES (?, ?)";
                    $stmt = $conn->prepare($insert_sql);
                    $stmt->bind_param("ss", $type_code, $type_name);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
        fclose($handle);
        $success = "อัปโหลด CSV สำเร็จ!";
    }
}

// ตรวจสอบการส่งฟอร์มสำหรับการเพิ่มประเภทสินค้าใหม่
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_product_type'])) {
    $type_name = $_POST['type_name'];
    $type_code = generateTypeCode($conn); // สร้างรหัสประเภทสินค้าอัตโนมัติ

    // เพิ่มข้อมูลประเภทสินค้าใหม่ในฐานข้อมูล
    $sql = "INSERT INTO prod_type (type_code, type_name) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $type_code, $type_name);

    if ($stmt->execute()) {
        $success = "เพิ่มประเภทสินค้าสำเร็จ!";
    } else {
        $error = "เกิดข้อผิดพลาดในการเพิ่มประเภทสินค้า!";
    }

    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มประเภทสินค้า</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <h2 class="text-center mb-4">เพิ่มประเภทสินค้าใหม่</h2>

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

    <!-- ฟอร์มสำหรับเพิ่มประเภทสินค้าใหม่ -->
    <form action="add_products_type.php" method="POST">
        <div class="mb-3">
            <label for="type_name" class="form-label">ชื่อประเภทสินค้า</label>
            <input type="text" class="form-control" id="type_name" name="type_name" required>
        </div>
        <button type="submit" class="btn btn-primary" name="add_product_type">เพิ่มประเภทสินค้า</button>
    </form>

    <!-- ฟอร์มสำหรับอัปโหลดข้อมูลจาก CSV -->
    <h3 class="text-center mt-5">อัปโหลดประเภทสินค้าจาก CSV</h3>
    <form action="add_products_type.php" method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="csv_file" class="form-label">เลือกไฟล์ CSV</label>
            <input type="file" class="form-control" id="csv_file" name="csv_file" required>
        </div>
        <button type="submit" class="btn btn-success" name="upload_csv">อัปโหลด CSV</button>
    </form>
</div>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
