<?php
require_once('config_db.php');

// รับ type_id จาก URL
$type_id = $_GET['type_id'] ?? 0;

if ($type_id > 0) {
    // สั่งลบข้อมูลจากฐานข้อมูล
    $query = "DELETE FROM prod_type WHERE type_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $type_id);

    if ($stmt->execute()) {
        echo "ลบข้อมูลประเภทสินค้าเรียบร้อยแล้ว";
        header("Location: products_type_list.php"); // กลับไปที่หน้า products_type_list.php
        exit;
    } else {
        echo "ไม่สามารถลบข้อมูลประเภทสินค้าได้";
    }
} else {
    echo "ไม่พบข้อมูลประเภทสินค้าที่ต้องการลบ";
}

$stmt->close();
$conn->close();
?>
