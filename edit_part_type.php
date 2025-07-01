<?php
session_start();
require_once 'config_db.php'; // เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล

$part_type = null; // กำหนดค่าเริ่มต้นให้กับตัวแปร part_type

// ตรวจสอบว่ามีการส่งข้อมูลแก้ไขหรือไม่
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_part_type'])) {
    $type_id = $_POST['type_id'];
    $type_name = $_POST['type_name'];

    // อัปเดตข้อมูลประเภทชิ้นส่วน
    $sql = "UPDATE part_type SET type_name = ? WHERE type_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $type_name, $type_id);

    if ($stmt->execute()) {
        // รีไดเรกต์กลับไปที่หน้า part_type_list.php หลังจากอัปเดตสำเร็จ
        header("Location: part_type_list.php");
        exit(); // หยุดการทำงานของสคริปต์เพื่อป้องกันการทำงานต่อหลังรีไดเรกต์
    } else {
        $error = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล!";
    }

    $stmt->close();
}

// ตรวจสอบว่ามีการส่งค่า id มาเพื่อแก้ไข
if (isset($_GET['id'])) {
    $type_id = $_GET['id'];

    // ดึงข้อมูลประเภทชิ้นส่วนที่ต้องการแก้ไข
    $sql = "SELECT type_code, type_name FROM part_type WHERE type_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $type_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // ตรวจสอบว่ามีข้อมูลที่ตรงกันหรือไม่
    if ($result->num_rows > 0) {
        $part_type = $result->fetch_assoc(); // กำหนดค่าให้กับตัวแปร $part_type
    } else {
        $error = "ไม่พบข้อมูลประเภทชิ้นส่วนที่ต้องการแก้ไข!";
    }

    $stmt->close();
} else {
    // แสดงข้อความนี้เฉพาะเมื่อไม่ได้ส่ง ID มาจาก URL เท่านั้น
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $error = "ไม่พบ ID ของประเภทชิ้นส่วน!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Part Type</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <h2 class="text-center mb-4">Edit Part Type</h2>

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

    <?php if ($part_type): ?>
    <!-- ฟอร์มสำหรับแก้ไขประเภทชิ้นส่วน -->
    <form action="edit_part_type.php" method="POST">
        <div class="mb-3">
            <label for="type_code" class="form-label">Part Type Code</label>
            <input type="text" class="form-control" id="type_code" name="type_code" value="<?php echo htmlspecialchars($part_type['type_code']); ?>" readonly>
        </div>
        <div class="mb-3">
            <label for="type_name" class="form-label">Part Type Name</label>
            <input type="text" class="form-control" id="type_name" name="type_name" value="<?php echo htmlspecialchars($part_type['type_name']); ?>" required>
        </div>
        <input type="hidden" name="type_id" value="<?php echo $type_id; ?>">
        <button type="submit" class="btn btn-primary" name="update_part_type">Update</button>
    </form>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
