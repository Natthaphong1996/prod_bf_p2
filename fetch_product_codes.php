<?php
include 'config_db.php';

$search = $_GET['search'] ?? '';

if (!empty($search)) {
    $query = "SELECT prod_code, prod_description FROM prod_list WHERE prod_code LIKE ? LIMIT 10";
    $stmt = $conn->prepare($query);
    $searchTerm = '%' . $search . '%';
    $stmt->bind_param("s", $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo '<a href="#" class="list-group-item list-group-item-action suggestion-item" 
                    data-code="' . htmlspecialchars($row['prod_code']) . '" 
                    data-description="' . htmlspecialchars($row['prod_description']) . '">' 
                    . htmlspecialchars($row['prod_code']) . ' | ' . htmlspecialchars($row['prod_description']) . 
                 '</a>';
        }
    } else {
        echo '<div class="list-group-item">ไม่พบข้อมูล</div>';
    }

    $stmt->close();
}
$conn->close();
?>
