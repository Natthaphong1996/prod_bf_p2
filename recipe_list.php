<?php
// ไฟล์: recipe_list.php (เวอร์ชันปรับปรุงตาม Schema ใหม่)
session_start();
include 'config_db.php';
include 'navbar.php';

// ตรวจสอบการเข้าระบบ
if (!isset($_SESSION['username'])) {
    header('Location: index.php');
    exit();
}

// --- 1. ดึงข้อมูลสำหรับ Dropdowns ทั้งหมด (ต้องดึงที่นี่เพื่อให้ recipe_list_modal.php ใช้ได้) ---
// การดึงข้อมูลทั้งหมดมาครั้งเดียวช่วยลดการ Query ซ้ำซ้อน
$customers = $conn->query("SELECT customer_id, customer_name FROM customer ORDER BY customer_name")->fetch_all(MYSQLI_ASSOC);
$rm_wood = $conn->query("SELECT rm_id, rm_code, rm_thickness, rm_width, rm_length, rm_m3 FROM rm_wood_list ORDER BY rm_code")->fetch_all(MYSQLI_ASSOC);
$parts = $conn->query("SELECT part_id, part_code, part_type, part_thickness, part_width, part_length, part_m3 FROM part_list ORDER BY part_code")->fetch_all(MYSQLI_ASSOC);

// --- 2. ดึงข้อมูลสูตรการผลิตทั้งหมดเพื่อมาแสดงในตาราง ---
// ใช้ LEFT JOIN เพื่อดึงชื่อ code มาแสดงผล ทำให้ผู้ใช้เข้าใจง่าย
$sql = "SELECT 
            r.recipe_id, 
            rm.rm_code, 
            pl.part_code AS primary_part_code,
            c.customer_name AS primary_customer_name
        FROM recipe_list AS r
        LEFT JOIN rm_wood_list AS rm ON r.rm_id = rm.rm_id
        LEFT JOIN part_list AS pl ON r.p_part_id = pl.part_id
        LEFT JOIN customer AS c ON r.p_customer_id = c.customer_id
        ORDER BY r.recipe_id DESC";
$recipes_result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสูตรการผลิต (Recipe)</title>
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <style>
        body { background-color: #f8f9fa; }
        .container { background-color: #fff; border-radius: 8px; padding: 2rem; margin-top: 2rem; box-shadow: 0 4px 8px rgba(0,0,0,0.05); }
        .table-hover tbody tr:hover { background-color: #f1f1f1; }
        .table thead { position: sticky; top: 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-journal-bookmark-fill text-primary"></i> จัดการสูตรการผลิต (Recipe)</h1>
            <button type="button" class="btn btn-primary" onclick="openRecipeModal()">
                <i class="bi bi-plus-circle"></i> สร้างสูตรใหม่
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover table-bordered">
                <thead class="table-dark text-center">
                    <tr>
                        <th>ID สูตร</th>
                        <th>วัตถุดิบหลัก</th>
                        <th>ลูกค้าหลัก</th>
                        <th>ชิ้นส่วนหลักที่ได้</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody class="text-center">
                    <?php if ($recipes_result && $recipes_result->num_rows > 0): ?>
                        <?php while($row = $recipes_result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['recipe_id']) ?></td>
                                <td><?= htmlspecialchars($row['rm_code'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['primary_customer_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['primary_part_code'] ?? 'N/A') ?></td>
                                <td>
                                    <button class="btn btn-warning btn-sm" onclick="openRecipeModal(<?= $row['recipe_id'] ?>)" title="แก้ไข">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteRecipe(<?= $row['recipe_id'] ?>)" title="ลบ">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center p-4">ยังไม่มีข้อมูลสูตรการผลิต</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ★★★ เรียกใช้ Modal จากไฟล์ภายนอก ★★★ -->
    <?php include 'recipe_list_modal.php'; ?>

    <?php include 'footer.php'; ?>
    
    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- ★★★ เรียกใช้ฟังก์ชัน JavaScript จากไฟล์ภายนอก ★★★ -->
    <?php include 'recipe_list_function.php'; ?>

</body>
</html>
