<?php
session_start();
require_once 'config_db.php'; // เรียกใช้ไฟล์สำหรับเชื่อมต่อฐานข้อมูล

// ฟังก์ชันสำหรับสร้างรหัสประเภทชิ้นส่วนอัตโนมัติ
function generatePartTypeCode($conn) {
    // ค้นหารหัสประเภทชิ้นส่วนล่าสุด
    $sql = "SELECT type_code FROM part_type ORDER BY type_id DESC LIMIT 1";
    $result = $conn->query($sql);
    $lastCode = $result->fetch_assoc();

    if ($lastCode) {
        // นำเลขท้ายมาต่อเพิ่ม 1
        $lastNumber = (int)substr($lastCode['type_code'], 1);
        $newNumber = $lastNumber + 1;
        $newCode = 'P' . str_pad($newNumber, 4, '0', STR_PAD_LEFT); // P0001, P0002, ...
    } else {
        // ถ้ายังไม่มีข้อมูลในตาราง ให้เริ่มที่ P0001
        $newCode = 'P0001';
    }

    return $newCode;
}

// ฟังก์ชันสำหรับอ่านและอัปโหลดข้อมูลจาก CSV
if (isset($_POST['upload_csv'])) {
    $csv_file = $_FILES['csv_file']['tmp_name'];

    if (($handle = fopen($csv_file, "r")) !== FALSE) {
        // ข้าม A1 ซึ่งเป็นหัวตาราง
        fgetcsv($handle);

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $type_name = trim($data[0]); // อ่านจากคอลัมน์ A2 เป็นต้นไป

            if (!empty($type_name)) {
                // ตรวจสอบว่ามี type_name ซ้ำในฐานข้อมูลหรือไม่
                $check_sql = "SELECT COUNT(*) FROM part_type WHERE type_name = ?";
                $stmt = $conn->prepare($check_sql);
                $stmt->bind_param("s", $type_name);
                $stmt->execute();
                $stmt->bind_result($count);
                $stmt->fetch();
                $stmt->close();

                if ($count == 0) {
                    // ถ้าไม่มีค่า type_name ซ้ำ ให้สร้าง type_code ใหม่
                    $type_code = generatePartTypeCode($conn);
                    $insert_sql = "INSERT INTO part_type (type_code, type_name) VALUES (?, ?)";
                    $stmt = $conn->prepare($insert_sql);
                    $stmt->bind_param("ss", $type_code, $type_name);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
        fclose($handle);
        $success = "อัปโหลด CSV เรียบร้อยแล้ว!";
    }
}

// ตรวจสอบว่ามีการส่งฟอร์มเข้ามาหรือไม่สำหรับการเพิ่มข้อมูลปกติ
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_part_type'])) {
    $type_name = $_POST['type_name'];
    $type_code = generatePartTypeCode($conn); // สร้างรหัสประเภทชิ้นส่วนอัตโนมัติ

    // เพิ่มข้อมูลประเภทชิ้นส่วนใหม่เข้าสู่ฐานข้อมูล
    $sql = "INSERT INTO part_type (type_code, type_name) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $type_code, $type_name);

    if ($stmt->execute()) {
        $success = "เพิ่มประเภทชิ้นส่วนเรียบร้อยแล้ว!";
    } else {
        $error = "เกิดข้อผิดพลาดในการเพิ่มประเภทชิ้นส่วน!";
    }

    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มประเภทชิ้นส่วน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <h2 class="text-center mb-4">เพิ่มประเภทชิ้นส่วนใหม่</h2>

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

    <!-- ฟอร์มสำหรับเพิ่มประเภทชิ้นส่วนใหม่ -->
    <form action="add_part_type.php" method="POST">
        <div class="mb-3">
            <label for="type_name" class="form-label">ชื่อประเภทชิ้นส่วน</label>
            <input type="text" class="form-control" id="type_name" name="type_name" required>
        </div>
        <button type="submit" class="btn btn-primary" name="add_part_type">เพิ่มประเภทชิ้นส่วน</button>
    </form>

    <!-- ฟอร์มสำหรับอัปโหลด CSV -->
    <h3 class="text-center mt-5">อัปโหลดประเภทชิ้นส่วนผ่าน CSV</h3>
    <form action="add_part_type.php" method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="csv_file" class="form-label">เลือกไฟล์ CSV</label>
            <input type="file" class="form-control" id="csv_file" name="csv_file" required>
        </div>
        <button type="submit" class="btn btn-success" name="upload_csv">อัปโหลด CSV</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
