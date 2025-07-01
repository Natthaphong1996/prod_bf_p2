<?php
session_start();
require_once 'config_db.php'; // เรียกใช้ไฟล์สำหรับเชื่อมต่อฐานข้อมูล

// ฟังก์ชันสำหรับคำนวณ m3
function calculateM3($thickness, $width, $length) {
    return ($thickness * $width * $length) / 1000000000; // แปลงหน่วยจาก mm เป็น m3
}

// ดึงข้อมูลประเภท Part จากฐานข้อมูลเพื่อแสดงใน Dropdown
$sql_part_types = "SELECT type_id, type_name FROM part_type";
$result_part_types = $conn->query($sql_part_types);

// ตรวจสอบว่ามีการส่งฟอร์มหรือไม่สำหรับการเพิ่มรายการทีละรายการ
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_part'])) {
    $thickness = floatval($_POST['part_thickness']);
    $width = floatval($_POST['part_width']);
    $length = floatval($_POST['part_length']);
    $part_type = $_POST['part_type']; // เลือกจาก Dropdown

    // สร้างรหัสส่วนประกอบอัตโนมัติ
    $part_code = 'S' . str_pad((int)$thickness, 3, '0', STR_PAD_LEFT) . str_pad((int)$width, 3, '0', STR_PAD_LEFT) . str_pad((int)$length, 4, '0', STR_PAD_LEFT);

    // คำนวณค่า m3
    $part_m3 = calculateM3($thickness, $width, $length);
    
    // ตรวจสอบว่ามี part_code ซ้ำหรือไม่
    $check_sql = "SELECT COUNT(*) FROM part_list WHERE part_code = ? AND part_type = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ss", $part_code, $part_type);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    if ($count == 0) {
        // ถ้าไม่มี part_code ซ้ำ ให้เพิ่มข้อมูลลงในฐานข้อมูล
        $insert_sql = "INSERT INTO part_list (part_code, part_type, part_thickness, part_width, part_length, part_m3) 
                       VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("ssddds", $part_code, $part_type, $thickness, $width, $length, $part_m3);
        
        if ($stmt->execute()) {
            $success = "เพิ่ม Part เรียบร้อยแล้ว! รหัสส่วนประกอบ: " . $part_code;
        } else {
            $error = "เกิดข้อผิดพลาดในการเพิ่ม Part!";
        }
        $stmt->close();
    } else {
        $error = "Part Code ซ้ำในระบบสำหรับประเภทนี้! รหัสที่สร้าง: " . $part_code;
    }
}

// ฟังก์ชันสำหรับการอัปโหลดข้อมูลจาก CSV
if (isset($_POST['upload_csv'])) {
    $csv_file = $_FILES['csv_file']['tmp_name'];

    if (($handle = fopen($csv_file, "r")) !== FALSE) {
        // ข้าม A1 ซึ่งเป็นหัวตาราง
        fgetcsv($handle);

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $part_code = trim($data[0]);
            $part_type = trim($data[1]);
            $thickness = floatval(trim($data[2]));
            $width = floatval(trim($data[3]));
            $length = floatval(trim($data[4]));

            // คำนวณค่า m3
            $part_m3 = calculateM3($thickness, $width, $length);

            // ข้ามรายการที่ part_code เป็นค่าว่าง
            if (empty($part_code)) {
                continue;
            }

            // ตรวจสอบว่ามี part_code ซ้ำหรือไม่
            $check_sql = "SELECT COUNT(*) FROM part_list WHERE part_code = ? AND part_type = ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("ss", $part_code, $part_type);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $stmt->close();

            if ($count == 0) {
                // ถ้าไม่มี part_code ซ้ำ ให้เพิ่มข้อมูลลงในฐานข้อมูล
                $insert_sql = "INSERT INTO part_list (part_code, part_type, part_thickness, part_width, part_length, part_m3) 
                               VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_sql);
                $stmt->bind_param("ssddds", $part_code, $part_type, $thickness, $width, $length, $part_m3);
                $stmt->execute();
                $stmt->close();
            }
        }
        fclose($handle);
        $success = "อัปโหลด CSV เรียบร้อยแล้ว!";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มส่วนประกอบ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card-custom {
            padding: 20px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .container {
            margin-top: 50px;
        }
        .btn-custom {
            font-size: 16px;
            padding: 10px 20px;
            font-weight: bold;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <h2 class="text-center mb-4">เพิ่มส่วนประกอบใหม่</h2>

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

    <!-- ฟอร์มสำหรับเพิ่ม Part ใหม่ทีละรายการ -->
    <div class="card card-custom">
        <form action="add_part.php" method="POST">
            <div class="mb-3">
                <label for="part_code" class="form-label">รหัสส่วนประกอบ</label>
                <input type="text" class="form-control" id="part_code" name="part_code" readonly>
            </div>
            <div class="mb-3">
                <label for="part_type" class="form-label">ประเภทส่วนประกอบ</label>
                <select class="form-control" id="part_type" name="part_type" required>
                    <option value="">เลือกประเภทส่วนประกอบ</option>
                    <?php while ($row_part_type = $result_part_types->fetch_assoc()): ?>
                        <option value="<?php echo $row_part_type['type_name']; ?>"><?php echo $row_part_type['type_name']; ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="part_thickness" class="form-label">ความหนา (มม.)</label>
                <input type="number" step="0.01" class="form-control" id="part_thickness" name="part_thickness" required>
            </div>
            <div class="mb-3">
                <label for="part_width" class="form-label">ความกว้าง (มม.)</label>
                <input type="number" step="0.01" class="form-control" id="part_width" name="part_width" required>
            </div>
            <div class="mb-3">
                <label for="part_length" class="form-label">ความยาว (มม.)</label>
                <input type="number" step="0.01" class="form-control" id="part_length" name="part_length" required>
            </div>
            <div class="mb-3">
                <label for="part_m3" class="form-label">ปริมาตร m3 ที่คำนวณได้</label>
                <input type="text" class="form-control" id="part_m3" name="part_m3" readonly>
            </div>
            <button type="submit" class="btn btn-primary btn-custom" name="add_part">เพิ่มส่วนประกอบ</button>
        </form>
    </div>

    <!-- ฟอร์มสำหรับอัปโหลด CSV -->
    <div class="card card-custom">
        <h3 class="text-center">อัปโหลดส่วนประกอบผ่าน CSV</h3>
        <form action="add_part.php" method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="csv_file" class="form-label">เลือกไฟล์ CSV</label>
                <input type="file" class="form-control" id="csv_file" name="csv_file" required>
            </div>
            <button type="submit" class="btn btn-success btn-custom" name="upload_csv">อัปโหลด CSV</button>
        </form>
    </div>
</div>

<script>
// คำนวณค่า m3 อัตโนมัติเมื่อกรอกข้อมูล
document.querySelectorAll('#part_thickness, #part_width, #part_length').forEach(function(input) {
    input.addEventListener('input', function() {
        var thickness = parseFloat(document.getElementById('part_thickness').value) || 0;
        var width = parseFloat(document.getElementById('part_width').value) || 0;
        var length = parseFloat(document.getElementById('part_length').value) || 0;
        var m3 = (thickness * width * length) / 1000000000; // คำนวณ m3
        document.getElementById('part_m3').value = m3.toFixed(6); // แสดงค่า m3

        // สร้างรหัสส่วนประกอบอัตโนมัติ
        var partCode = 'S' + thickness.toString().padStart(3, '0') + width.toString().padStart(3, '0') + length.toString().padStart(4, '0');
        document.getElementById('part_code').value = partCode; // แสดงรหัสส่วนประกอบในช่อง input
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
