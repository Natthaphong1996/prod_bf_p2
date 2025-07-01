<?php
require_once 'config_db.php'; // เรียกใช้ไฟล์สำหรับเชื่อมต่อฐานข้อมูล

// รับค่าค้นหาที่ถูกส่งมาจาก AJAX
$search = isset($_GET['search']) ? $_GET['search'] : '';

// SQL Query สำหรับดึงข้อมูลประเภทชิ้นส่วน (part_type)
if (!empty($search)) {
    // ถ้ามีการค้นหา
    $sql = "SELECT type_id, type_code, type_name FROM part_type WHERE type_code LIKE ? OR type_name LIKE ?";
    $stmt = $conn->prepare($sql);
    $search_param = "%" . $search . "%";
    $stmt->bind_param("ss", $search_param, $search_param);
} else {
    // ถ้าไม่มีการค้นหา ดึงข้อมูลทั้งหมด
    $sql = "SELECT type_id, type_code, type_name FROM part_type";
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();

// ตรวจสอบว่ามีข้อมูลหรือไม่
if ($result->num_rows > 0) {
    echo '<table class="table table-bordered">';
    echo '<thead><tr><th>Type Code</th><th>Type Name</th><th>Actions</th></tr></thead>';
    echo '<tbody>';

    // แสดงข้อมูลประเภทชิ้นส่วน
    while ($row = $result->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['type_code']) . '</td>';
        echo '<td>' . htmlspecialchars($row['type_name']) . '</td>';
        echo '<td>';
        echo '<a href="edit_part_type.php?id=' . $row['type_id'] . '" class="btn btn-warning btn-sm">Edit</a> ';
        echo '<a href="delete_part_type.php?id=' . $row['type_id'] . '" class="btn btn-danger btn-sm" onclick="return confirm(\'Are you sure you want to delete this part type?\');">Delete</a>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
} else {
    echo '<p class="text-center">ไม่พบข้อมูล</p>';
}

$stmt->close();
$conn->close();
?>
