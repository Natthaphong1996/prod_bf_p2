<?php
require_once 'config_db.php'; // เรียกใช้ไฟล์สำหรับเชื่อมต่อฐานข้อมูล

$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$cards_per_page = 30;
$offset = ($page - 1) * $cards_per_page;

// ตรวจสอบว่ามีคำค้นหาหรือไม่
if ($search != '') {
    $sql = "SELECT part_id, part_code, part_type, part_thickness, part_width, part_length, 
                   CONCAT(part_thickness, 'x', part_width, 'x', part_length) AS size 
            FROM part_list 
            WHERE part_code LIKE ? 
            OR part_type LIKE ? 
            OR CONCAT(part_thickness, 'x', part_width, 'x', part_length) LIKE ?
            LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $search_param = "%{$search}%";
    $stmt->bind_param("sssii", $search_param, $search_param, $search_param, $cards_per_page, $offset);
} else {
    $sql = "SELECT part_id, part_code, part_type, part_thickness, part_width, part_length, 
                   CONCAT(part_thickness, 'x', part_width, 'x', part_length) AS size 
            FROM part_list
            LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $cards_per_page, $offset);
}

$stmt->execute();
$result = $stmt->get_result();

// หาจำนวนหน้าทั้งหมดสำหรับ pagination
$total_sql = "SELECT COUNT(*) FROM part_list";
$total_result = $conn->query($total_sql);
$total_cards = $total_result->fetch_row()[0];
$total_pages = ceil($total_cards / $cards_per_page);

// เริ่มสร้างผลลัพธ์เป็นรูปแบบ Card
$parts_html = '';
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $parts_html .= '
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="highlighted-title card-title">' . $row['part_code'] . '</h5>
                    <p class="card-text">
                        <strong>Type:</strong> ' . $row['part_type'] . '<br>
                        <strong>Size:</strong> ' . $row['part_thickness'] . ' x ' . $row['part_width'] . ' x ' . $row['part_length'] . ' mm
                    </p>
                </div>
                <div class="card-footer text-center">
                    <a href="edit_part.php?id=' . $row['part_id'] . '" class="btn btn-warning btn-sm">Edit</a>
                    <a href="delete_part.php?id=' . $row['part_id'] . '" class="btn btn-danger btn-sm" onclick="return confirm(\'Are you sure you want to delete this part?\');">Delete</a>
                </div>
            </div>
        </div>';
    }
} else {
    $parts_html .= '<div class="col-12 text-center">ไม่พบข้อมูลชิ้นส่วน</div>';
}

// สร้าง pagination
$pagination_html = '<nav><ul class="pagination justify-content-center">';

if ($page > 1) {
    $pagination_html .= '<li class="page-item"><a class="page-link" href="#" data-page="' . ($page - 1) . '">Previous</a></li>';
}

for ($i = 1; $i <= min(5, $total_pages); $i++) {
    $active_class = ($i == $page) ? 'active' : '';
    $pagination_html .= '<li class="page-item ' . $active_class . '"><a class="page-link" href="#" data-page="' . $i . '">' . $i . '</a></li>';
}

if ($page > 5 && $total_pages > 5) {
    $pagination_html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
}

for ($i = max(6, $page - 1); $i <= min($total_pages, $page + 1); $i++) {
    if ($i > 5) {
        $active_class = ($i == $page) ? 'active' : '';
        $pagination_html .= '<li class="page-item ' . $active_class . '"><a class="page-link" href="#" data-page="' . $i . '">' . $i . '</a></li>';
    }
}

if ($page + 1 < $total_pages) {
    $pagination_html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
}

if ($page + 1 < $total_pages || $page < $total_pages) {
    $pagination_html .= '<li class="page-item ' . ($total_pages == $page ? 'active' : '') . '"><a class="page-link" href="#" data-page="' . $total_pages . '">' . $total_pages . '</a></li>';
}

if ($page < $total_pages) {
    $pagination_html .= '<li class="page-item"><a class="page-link" href="#" data-page="' . ($page + 1) . '">Next</a></li>';
}

$pagination_html .= '</ul></nav>';

$response = array(
    'parts_html' => $parts_html,
    'pagination_html' => $pagination_html
);

echo json_encode($response);

$stmt->close();
$conn->close();
