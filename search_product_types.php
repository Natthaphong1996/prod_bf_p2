<?php
require_once 'config_db.php'; // เรียกใช้งานไฟล์สำหรับเชื่อมต่อฐานข้อมูล

$search = isset($_GET['search']) ? $_GET['search'] : '';

// ตรวจสอบว่ามีคำค้นหาหรือไม่
if ($search != '') {
    $sql = "SELECT type_code, type_name FROM prod_type 
            WHERE type_code LIKE ? OR type_name LIKE ?";
    $stmt = $conn->prepare($sql);
    $search_param = "%{$search}%";
    $stmt->bind_param("ss", $search_param, $search_param);
} else {
    // ถ้าไม่มีคำค้นหา แสดงข้อมูลประเภทสินค้าทั้งหมด
    $sql = "SELECT type_code, type_name FROM prod_type";
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<table class="table table-bordered table-hover">
    <thead>
        <tr class="text-center">
            <th>Type Code</th>
            <th>Type Name</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['type_code']; ?></td>
                    <td><?php echo $row['type_name']; ?></td>
                    <td class="text-center">
                        <!-- ปุ่มแก้ไข -->
                        <a href="edit_product_type.php?code=<?php echo $row['type_code']; ?>" class="btn btn-warning btn-sm">Edit</a>

                        <!-- ปุ่มลบ -->
                        <a href="delete_product_type.php?code=<?php echo $row['type_code']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this product type?');">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="3" class="text-center">ไม่พบข้อมูลประเภทสินค้า</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php
// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();
?>
