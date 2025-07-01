<?php
// เชื่อมต่อฐานข้อมูล
include 'config_db.php';

// ตรวจสอบว่าได้ส่งค่า repair_issue_id มาหรือไม่
if (isset($_POST['repair_issue_id'])) {
    $repair_issue_id = $_POST['repair_issue_id'];

    // คำสั่ง SQL เพื่อดึงข้อมูล part_quantity_reason จากตาราง repair_issue
    $query = "SELECT part_quantity_reason FROM repair_issue WHERE repair_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $repair_issue_id);  // ใช้ "s" เนื่องจาก repair_id เป็น varchar
    $stmt->execute();
    $stmt->bind_result($part_quantity_reason);
    $stmt->fetch();

    // ถ้ามีข้อมูลใน part_quantity_reason ให้แปลงเป็น JSON และส่งกลับ
    if ($part_quantity_reason) {
        // แปลง JSON ที่เก็บใน part_quantity_reason
        $part_details = json_decode($part_quantity_reason, true);

        // ส่งข้อมูลกลับเป็น JSON โดยไม่แปลงเป็น Unicode Escape Sequence
        echo json_encode($part_details, JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([]); // ส่งกลับเป็น array ว่างถ้าไม่พบข้อมูล
    }
} else {
    echo json_encode([]); // ส่งกลับเป็น array ว่างถ้าไม่ได้ส่ง repair_issue_id มาด้วย
}

// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();

?>
