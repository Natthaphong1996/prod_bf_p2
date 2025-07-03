<?php
// ไฟล์: recipe_list.php (เวอร์ชัน Refactor)
session_start();
include 'config_db.php';
include 'navbar.php';

if (!isset($_SESSION['username'])) {
    header('Location: index.php');
    exit();
}

// --- 1. ดึงข้อมูลสำหรับ Dropdowns ทั้งหมด (ต้องดึงที่นี่เพื่อให้ recipe_list_modal.php ใช้ได้) ---
$customers = $conn->query("SELECT customer_id, customer_name FROM customer ORDER BY customer_name")->fetch_all(MYSQLI_ASSOC);
$rm_wood = $conn->query("SELECT rm_id, rm_code, rm_thickness, rm_width, rm_length, rm_m3 FROM rm_wood_list ORDER BY rm_code")->fetch_all(MYSQLI_ASSOC);
$parts = $conn->query("SELECT part_id, part_code, part_type, part_thickness, part_width, part_length, part_m3 FROM part_list ORDER BY part_code")->fetch_all(MYSQLI_ASSOC);

// --- 2. ดึงข้อมูลสูตรการผลิตทั้งหมดเพื่อมาแสดงในตาราง ---
$sql = "SELECT 
            r.recipe_id, rm.rm_code, pl.part_code AS primary_part_code
        FROM recipe_list AS r
        LEFT JOIN rm_wood_list AS rm ON r.rm_id = rm.rm_id
        LEFT JOIN part_list AS pl ON r.p_part_id = pl.part_id
        ORDER BY r.recipe_id DESC";
$recipes_result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสูตรการผลิต (Recipe)</title>
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <style>
        .container { background-color: #fff; border-radius: 8px; padding: 2rem; margin-top: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .form-label { font-weight: bold; }
        .accordion-button:not(.collapsed) { background-color: #e7f1ff; color: #0c63e4; }
        .select2-container--open { z-index: 9999999; }
        .form-control[readonly] { background-color: #e9ecef; cursor: not-allowed; }
        .calc-result { color: #0d6efd; font-weight: bold; }
        .total-loss-section { background-color: #fff3cd; border: 1px solid #ffc107; }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-journal-bookmark-fill"></i> จัดการสูตรการผลิต (Recipe)</h1>
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
                                <td><?= htmlspecialchars($row['primary_part_code'] ?? 'N/A') ?></td>
                                <td>
                                    <button class="btn btn-warning btn-sm" onclick="openRecipeModal(<?= $row['recipe_id'] ?>)"><i class="bi bi-pencil-square"></i> แก้ไข</button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteRecipe(<?= $row['recipe_id'] ?>)"><i class="bi bi-trash"></i> ลบ</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center">ยังไม่มีข้อมูลสูตรการผลิต</td></tr>
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
