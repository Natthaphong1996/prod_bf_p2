<?php
// เริ่มต้นการเชื่อมต่อกับฐานข้อมูล
require_once 'config_db.php';

// รับค่า search query จาก URL
$search = isset($_GET['search']) ? $_GET['search'] : '';

// สร้างคำสั่ง SQL
if ($search) {
    // ถ้ามีการค้นหา
    $sql = "SELECT customer_id, customer_name, customer_short_name, customer_code FROM customer WHERE customer_name LIKE ? OR customer_code LIKE ?";
    $stmt = $conn->prepare($sql);
    $search = "%$search%";
    $stmt->bind_param('ss', $search, $search);
} else {
    // ถ้าไม่มีการค้นหา
    $sql = "SELECT customer_id, customer_name, customer_short_name, customer_code FROM customer";
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();

// แสดงข้อมูลลูกค้าในตาราง
if ($result->num_rows > 0) {
    echo '<table class="table table-striped table-hover">';
    echo '<thead>';
    echo '<tr>';
    echo '<th scope="col">รหัสลูกค้า</th>';
    echo '<th scope="col">ชื่อลูกค้า</th>';
    echo '<th scope="col">ชื่อย่อ</th>';
    echo '<th scope="col">ดำเนินการ</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    while ($row = $result->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['customer_code']) . '</td>';
        echo '<td>' . htmlspecialchars($row['customer_name']) . '</td>';
        echo '<td>' . htmlspecialchars($row['customer_short_name']) . '</td>';
        echo '<td>';
        // ปุ่มแก้ไข
        echo '<a href="edit_customer.php?id=' . $row['customer_id'] . '" class="btn btn-warning btn-sm">แก้ไข</a>';
        // ปุ่มลบ
        echo '<a href="delete_customer.php?id=' . $row['customer_id'] . '" class="btn btn-danger btn-sm" onclick="return confirm(\'คุณแน่ใจหรือไม่ว่าต้องการลบลูกค้ารายนี้?\')">ลบ</a>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
} else {
    echo '<p>ไม่พบข้อมูลลูกค้าที่ค้นหา</p>';
}

$stmt->close();
$conn->close();
?>
