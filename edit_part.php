<?php
session_start();
require_once 'config_db.php'; // เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล

// ตรวจสอบว่ามีการส่ง ID ชิ้นส่วนมา
if (isset($_GET['id'])) {
    $part_id = $_GET['id'];

    // ตรวจสอบว่ามีการส่งฟอร์มการแก้ไขเข้ามาหรือไม่
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $part_code = $_POST['part_code'];
        $part_type = $_POST['part_type'];
        $part_thickness = $_POST['part_thickness'];
        $part_width = $_POST['part_width'];
        $part_length = $_POST['part_length'];

        // อัปเดตข้อมูลในฐานข้อมูล
        $sql = "UPDATE part_list 
                SET part_code = ?, part_type = ?, part_thickness = ?, part_width = ?, part_length = ? 
                WHERE part_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdddi", $part_code, $part_type, $part_thickness, $part_width, $part_length, $part_id);

        if ($stmt->execute()) {
            $_SESSION['success'] = "แก้ไขข้อมูลชิ้นส่วนเรียบร้อยแล้ว!";
            header('Location: products_part_list.php');
            exit();
        } else {
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการแก้ไขข้อมูล!";
        }
    }

    // ดึงข้อมูลปัจจุบันของชิ้นส่วนเพื่อแสดงในฟอร์มแก้ไข
    $sql = "SELECT * FROM part_list WHERE part_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $part_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $part = $result->fetch_assoc();
    } else {
        $_SESSION['error'] = "ไม่พบข้อมูลชิ้นส่วนที่ต้องการแก้ไข!";
        header('Location: products_part_list.php');
        exit();
    }
} else {
    $_SESSION['error'] = "ไม่มี ID ชิ้นส่วนที่ต้องการแก้ไข!";
    header('Location: products_part_list.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Part</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <h2 class="text-center mb-4">Edit Part</h2>

    <!-- แสดงข้อความแจ้งเตือน -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- ฟอร์มสำหรับแก้ไขข้อมูล -->
    <form action="edit_part.php?id=<?php echo $part_id; ?>" method="POST">
        <div class="mb-3">
            <label for="part_code" class="form-label">Part Code</label>
            <input type="text" class="form-control" id="part_code" name="part_code" value="<?php echo $part['part_code']; ?>" required>
        </div>
        <div class="mb-3">
            <label for="part_type" class="form-label">Part Type</label>
            <select class="form-control" id="part_type" name="part_type" required>
                <option value="">เลือกประเภทชิ้นส่วน</option>
                <?php
                // ดึงข้อมูลประเภทชิ้นส่วนจากฐานข้อมูล
                $sql_types = "SELECT type_name FROM part_type";
                $result_types = $conn->query($sql_types);
                while ($row_type = $result_types->fetch_assoc()) {
                    echo '<option value="' . htmlspecialchars($row_type['type_name']) . '" ' . 
                        ($row_type['type_name'] == $part['part_type'] ? 'selected' : '') . '>' . 
                        htmlspecialchars($row_type['type_name']) . '</option>';
                }
                ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="part_thickness" class="form-label">Thickness</label>
            <input type="number" step="0.01" class="form-control" id="part_thickness" name="part_thickness" value="<?php echo $part['part_thickness']; ?>" required>
        </div>
        <div class="mb-3">
            <label for="part_width" class="form-label">Width</label>
            <input type="number" step="0.01" class="form-control" id="part_width" name="part_width" value="<?php echo $part['part_width']; ?>" required>
        </div>
        <div class="mb-3">
            <label for="part_length" class="form-label">Length</label>
            <input type="number" step="0.01" class="form-control" id="part_length" name="part_length" value="<?php echo $part['part_length']; ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Update Part</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
