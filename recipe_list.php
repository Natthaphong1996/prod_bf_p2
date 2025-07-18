<?php
// recipe_list.php
session_start();
// -------------------------------------------------------------------------
// 1. การตั้งค่าและเชื่อมต่อฐานข้อมูล (Database Connection)
// -------------------------------------------------------------------------
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "prod_bf_p2";

// สร้างการเชื่อมต่อ
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// -------------------------------------------------------------------------
// 2. ส่วนจัดการการลบข้อมูล (Delete Logic)
// -------------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $recipe_id_to_delete = (int)$_GET['id'];
    
    // ป้องกันการลบข้อมูลสำคัญโดยไม่ได้ตั้งใจ
    if ($recipe_id_to_delete > 0) {
        $stmt = $conn->prepare("DELETE FROM recipe_list WHERE recipe_id = ?");
        $stmt->bind_param("i", $recipe_id_to_delete);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "ลบสูตรการผลิต ID: {$recipe_id_to_delete} เรียบร้อยแล้ว";
        } else {
            $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการลบข้อมูล: " . $stmt->error;
        }
        $stmt->close();
    }
    
    // Redirect เพื่อป้องกันการลบซ้ำเมื่อรีเฟรชหน้า
    header("Location: recipe_list.php");
    exit();
}

// -------------------------------------------------------------------------
// 3. ดึงข้อมูลทั้งหมดมาแสดง (Fetch Data)
// -------------------------------------------------------------------------
$sql = "SELECT recipe_id, recipe_name, total_m3_input, total_m3_output, total_loss_percent 
        FROM recipe_list 
        ORDER BY recipe_id DESC";
$result = $conn->query($sql);

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสูตรการผลิต (Recipe Management)</title>
    <!-- Bootstrap 5.3 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

    <?php include __DIR__ . '/navbar.php'; // เรียกใช้ Navbar ?>

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-clipboard-list"></i> จัดการสูตรการผลิต (Recipe)</h1>
            <a href="recipe_form.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> เพิ่มสูตรใหม่
            </a>
        </div>

        <?php
        // แสดงข้อความแจ้งเตือน (Success/Error)
        if (isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success">' . $_SESSION['success_message'] . '</div>';
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo '<div class="alert alert-danger">' . $_SESSION['error_message'] . '</div>';
            unset($_SESSION['error_message']);
        }
        ?>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>ชื่อสูตร (Recipe Name)</th>
                                <th class="text-end">Input (m³)</th>
                                <th class="text-end">Output (m³)</th>
                                <th class="text-end">Loss (%)</th>
                                <th class="text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['recipe_id']); ?></td>
                                        <td><?php echo htmlspecialchars($row['recipe_name']); ?></td>
                                        <td class="text-end"><?php echo number_format($row['total_m3_input'], 6); ?></td>
                                        <td class="text-end"><?php echo number_format($row['total_m3_output'], 6); ?></td>
                                        <td class="text-end text-danger fw-bold"><?php echo number_format($row['total_loss_percent'], 2); ?> %</td>
                                        <td class="text-center">
                                            <a href="recipe_form.php?id=<?php echo $row['recipe_id']; ?>" class="btn btn-warning btn-sm" title="แก้ไข">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="recipe_list.php?action=delete&id=<?php echo $row['recipe_id']; ?>" class="btn btn-danger btn-sm" title="ลบ" onclick="return confirm('คุณต้องการลบสูตร ID: <?php echo $row['recipe_id']; ?> ใช่หรือไม่?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">ยังไม่มีข้อมูลสูตรการผลิต</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$conn->close();
?>
