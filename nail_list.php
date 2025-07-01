<?php
// nail_list.php

session_start();

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once 'config_db.php';

// --- การจัดการข้อความแจ้งเตือน (Success/Error Messages) ---
$alert_message = '';
if (isset($_SESSION['success_message'])) {
    $alert_message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        ' . htmlspecialchars($_SESSION['success_message']) . '
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
    unset($_SESSION['success_message']); // ล้าง session message หลังจากแสดงผล
}

// --- การค้นหา (Search) ---
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_sql = '';
$params = [];
$types = '';

if (!empty($search_term)) {
    $search_sql = " WHERE nail_code LIKE ?";
    $types .= 's';
    $params[] = "%" . $search_term . "%";
}

// --- การแบ่งหน้า (Pagination) ---
// 1. นับจำนวนข้อมูลทั้งหมดที่ตรงกับเงื่อนไขการค้นหา (แก้ไขตารางเป็น 'nail')
$count_sql = "SELECT COUNT(nail_id) AS total FROM nail" . $search_sql;
$stmt_count = $conn->prepare($count_sql);
if (!empty($search_term)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_records = $stmt_count->get_result()->fetch_assoc()['total'];
$stmt_count->close();

// 2. กำหนดค่าต่างๆ สำหรับ pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10; // แสดง 10 รายการต่อหน้า
$offset = ($page - 1) * $records_per_page;
$total_pages = ceil($total_records / $records_per_page);

// --- การดึงข้อมูลจากฐานข้อมูล (แก้ไขตารางเป็น 'nail') ---
$sql = "SELECT nail_id, nail_code, nail_pcsperroll, nail_rollperbox FROM nail" . $search_sql . " ORDER BY nail_code ASC LIMIT ? OFFSET ?";
$types .= 'ii'; // เพิ่ม type สำหรับ LIMIT และ OFFSET
$params[] = $records_per_page;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการตะปู</title>
    <!-- Bootstrap 5.3 CDN and Icons -->
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"> -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .table-hover tbody tr:hover {
            background-color: #f2f2f2;
        }
        .pagination .page-link {
            cursor: pointer;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">รายการตะปู</h2>
            <a href="add_nail.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> เพิ่มตะปู</a>
        </div>

        <?php echo $alert_message; ?>

        <!-- Search Form -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form action="nail_list.php" method="GET" class="row g-3 align-items-center">
                    <div class="col-md">
                        <label for="search" class="visually-hidden">ค้นหารหัสตะปู</label>
                        <input type="text" class="form-control" id="search" name="search" placeholder="ค้นหาจากรหัสตะปู..." value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> ค้นหา</button>
                    </div>
                    <div class="col-md-auto">
                        <a href="nail_list.php" class="btn btn-outline-secondary w-100">ล้างค่า</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Nails Table -->
        <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle text-center">
                <thead class="table-light">
                    <tr>
                        <th scope="col" style="width: 5%;">#</th>
                        <th scope="col">รหัสตะปู</th>
                        <th scope="col">จำนวนต่อม้วน</th>
                        <th scope="col">จำนวนม้วนต่อกล่อง</th>
                        <th scope="col" style="width: 15%;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0) : ?>
                        <?php $row_num = $offset + 1; ?>
                        <?php while ($row = $result->fetch_assoc()) : ?>
                            <tr>
                                <td><?php echo $row_num++; ?></td>
                                <td><?php echo htmlspecialchars($row['nail_code']); ?></td>
                                <td><?php echo number_format($row['nail_pcsperroll']); ?></td>
                                <td><?php echo number_format($row['nail_rollperbox']); ?></td>
                                <td>
                                    <a href="edit_nail.php?id=<?php echo $row['nail_id']; ?>" class="btn btn-sm btn-outline-warning me-1">
                                        <i class="bi bi-pencil-square"></i> แก้ไข
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger delete-btn" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteNailModal" 
                                            data-id="<?php echo $row['nail_id']; ?>"
                                            data-code="<?php echo htmlspecialchars($row['nail_code']); ?>">
                                        <i class="bi bi-trash-fill"></i> ลบ
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">ไม่พบข้อมูล</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center mt-4">
                <!-- Previous Button -->
                <li class="page-item <?php if($page <= 1){ echo 'disabled'; } ?>">
                    <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search_term); ?>">ก่อนหน้า</a>
                </li>

                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php if($page == $i) {echo 'active'; } ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_term); ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                
                <!-- Next Button -->
                <li class="page-item <?php if($page >= $total_pages) { echo 'disabled'; } ?>">
                    <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search_term); ?>">ถัดไป</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>

    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteNailModal" tabindex="-1" aria-labelledby="deleteNailModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteNailModalLabel">ยืนยันการลบข้อมูล</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    คุณต้องการลบข้อมูลตะปูรหัส "<strong id="nailCodeToDelete"></strong>" ใช่หรือไม่?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <a href="#" id="confirmDeleteLink" class="btn btn-danger">ยืนยันการลบ</a>
                </div>
            </div>
        </div>
    </div>


    <?php 
    include 'footer.php'; 
    // $stmt->close();
    $conn->close();
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // JavaScript สำหรับจัดการ Modal ยืนยันการลบ
    document.addEventListener('DOMContentLoaded', function () {
        var deleteNailModal = document.getElementById('deleteNailModal');
        deleteNailModal.addEventListener('show.bs.modal', function (event) {
            // ปุ่มที่ถูกคลิกเพื่อเปิด modal
            var button = event.relatedTarget;
            
            // ดึงข้อมูลจาก data-* attributes
            var nailId = button.getAttribute('data-id');
            var nailCode = button.getAttribute('data-code');

            // อัปเดตเนื้อหาใน modal
            var modalBody = deleteNailModal.querySelector('#nailCodeToDelete');
            modalBody.textContent = nailCode;

            // อัปเดตลิงก์สำหรับปุ่มยืนยันการลบ
            var confirmDeleteLink = deleteNailModal.querySelector('#confirmDeleteLink');
            confirmDeleteLink.href = 'delete_nail.php?id=' + nailId;
        });
    });
    </script>
</body>
</html>
