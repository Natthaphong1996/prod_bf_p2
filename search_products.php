<?php
require_once 'config_db.php'; // เรียกใช้งานไฟล์สำหรับเชื่อมต่อฐานข้อมูล

$search = isset($_GET['search']) ? $_GET['search'] : '';

if ($search != '') {
    $sql = "SELECT p.prod_id, p.prod_code, p.prod_type, p.prod_partno, p.prod_description, c.customer_name 
            FROM prod_list p
            LEFT JOIN customer c ON p.customer_id = c.customer_id
            WHERE p.prod_code LIKE ? OR p.prod_type LIKE ? OR p.prod_partno LIKE ? OR p.prod_description LIKE ? OR c.customer_name LIKE ?";
    $stmt = $conn->prepare($sql);
    $search_param = "%{$search}%";
    $stmt->bind_param("sssss", $search_param, $search_param, $search_param, $search_param, $search_param);
} else {
    $sql = "SELECT p.prod_id, p.prod_code, p.prod_type, p.prod_partno, p.prod_description, c.customer_name 
            FROM prod_list p
            LEFT JOIN customer c ON p.customer_id = c.customer_id";
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();

?>

<div class="row justify-content-center">
    <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="col-md-4 mb-4 product-card"> <!-- 3 cards per row -->
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="highlighted-title card-title"><?php echo $row['prod_code']; ?></h5>
                        <p class="card-text">
                            <strong>Customer:</strong> <?php echo $row['customer_name']; ?><br>
                            <strong>Type:</strong> <?php echo $row['prod_type']; ?><br>
                            <strong>Part No:</strong> <?php echo $row['prod_partno']; ?><br>
                            <strong>Description:</strong> <?php echo $row['prod_description']; ?>
                        </p>
                    </div>
                    <div class="card-footer text-center">
                        <a href="edit_product.php?id=<?php echo $row['prod_id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                        <a href="delete_product.php?id=<?php echo $row['prod_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this product?');">Delete</a>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="col-12">
            <p class="text-center">ไม่พบข้อมูลสินค้า</p>
        </div>
    <?php endif; ?>
</div>

<?php
// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();
?>
