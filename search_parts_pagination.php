<?php
require_once 'config_db.php'; // เรียกใช้ไฟล์สำหรับเชื่อมต่อฐานข้อมูล

// กำหนดจำนวน Card ต่อหน้า
$cards_per_page = 9; // 9 cards per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $cards_per_page;

$search = isset($_GET['search']) ? $_GET['search'] : '';

// ตรวจสอบว่ามีคำค้นหาหรือไม่
if (!empty($search)) {
    $sql = "SELECT part_code, part_type, part_thickness, part_width, part_length FROM part_list 
            WHERE part_code LIKE ? OR part_type LIKE ?
            LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $search_param = "%{$search}%";
    $stmt->bind_param("ssii", $search_param, $search_param, $cards_per_page, $offset);
} else {
    // ถ้าไม่มีคำค้นหา แสดงข้อมูลทั้งหมดพร้อม Pagination
    $sql = "SELECT part_code, part_type, part_thickness, part_width, part_length FROM part_list
            LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $cards_per_page, $offset);
}

$stmt->execute();
$result = $stmt->get_result();

// นับจำนวนข้อมูลทั้งหมดสำหรับ Pagination
if (!empty($search)) {
    $count_sql = "SELECT COUNT(*) FROM part_list WHERE part_code LIKE ? OR part_type LIKE ?";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param("ss", $search_param, $search_param);
} else {
    $count_sql = "SELECT COUNT(*) FROM part_list";
    $count_stmt = $conn->prepare($count_sql);
}
$count_stmt->execute();
$count_stmt->bind_result($total_cards);
$count_stmt->fetch();
$count_stmt->close();

$total_pages = ceil($total_cards / $cards_per_page);

// เริ่มสร้างผลลัพธ์เป็นรูปแบบ Card
$output = '';
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $output .= '
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">' . $row['part_code'] . '</h5>
                    <p class="card-text">
                        <strong>Type:</strong> ' . $row['part_type'] . '<br>
                        <strong>Size:</strong> ' . $row['part_thickness'] . ' x ' . $row['part_width'] . ' x ' . $row['part_length'] . ' mm
                    </p>
                </div>
            </div>
        </div>';
    }
} else {
    $output .= '<div class="col-12 text-center">ไม่พบข้อมูลชิ้นส่วน</div>';
}

// สร้าง Pagination
$pagination = '';
if ($total_pages > 1) {
    $pagination .= '<li class="page-item"><a class="page-link" href="#" data-page="1">First</a></li>';

    for ($i = 1; $i <= $total_pages; $i++) {
        $active = ($i == $page) ? 'active' : '';
        $pagination .= '<li class="page-item ' . $active . '"><a class="page-link" href="#" data-page="' . $i . '">' . $i . '</a></li>';
    }

    $pagination .= '<li class="page-item"><a class="page-link" href="#" data-page="' . $total_pages . '">Last</a></li>';
}

// ส่งข้อมูลออกมาเป็น JSON
echo json_encode(['parts' => $output, 'pagination' => $pagination]);

$stmt->close();
$conn->close();
?>
