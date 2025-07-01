<?php
// ภาษา: PHP
// ชื่อไฟล์: wip_inventory.php
// หน้าจอแสดงคลังไม้ WIP Inventory พร้อมฟังก์ชันค้นหา Part Code และ ขนาด

session_start();
include __DIR__ . '/config_db.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// =====================
// การตั้งค่า Theme ของ Progress Bar
$barBgColor         = '#343a40';    // สีพื้นหลังของหลอด
$barFillColorNormal = '#0d6efd';    // สีหลอดเมื่อปริมาณ >= min
$barFillColorLow    = '#dc3545';    // สีหลอดเมื่อปริมาณ < min
// =====================

// รับค่าการค้นหาจากแบบฟอร์ม
$search_code = isset($_GET['search_code']) ? trim($_GET['search_code']) : '';
$search_size = isset($_GET['search_size']) ? trim($_GET['search_size']) : '';

// การตั้งค่าการแบ่งหน้า
$items_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// นับจำนวนรายการทั้งหมดตามเงื่อนไขค้นหา
$count_sql = "
SELECT COUNT(*) AS total
FROM wip_inventory AS wi
JOIN part_list AS pl ON wi.part_id = pl.part_id
WHERE pl.part_code LIKE ?
  AND CONCAT(pl.part_thickness, ' x ', pl.part_width, ' x ', pl.part_length) LIKE ?
";
$stmt_count = $conn->prepare($count_sql);
$like_code = "%{$search_code}%";
$like_size = "%{$search_size}%";
$stmt_count->bind_param('ss', $like_code, $like_size);
$stmt_count->execute();
$stmt_count->bind_result($total_items);
$stmt_count->fetch();
$stmt_count->close();
$total_pages = ceil($total_items / $items_per_page);

// ดึงข้อมูลตามเงื่อนไขค้นหา พร้อม pagination
$data_sql = <<<SQL
SELECT
    pl.part_code,
    pl.part_thickness, pl.part_width, pl.part_length,
    wi.quantity, wi.max, wi.min,
    pl.part_m3
FROM wip_inventory AS wi
JOIN part_list AS pl ON wi.part_id = pl.part_id
WHERE pl.part_code LIKE ?
  AND CONCAT(pl.part_thickness, ' x ', pl.part_width, ' x ', pl.part_length) LIKE ?
ORDER BY pl.part_code
LIMIT ?, ?
SQL;
$stmt_data = $conn->prepare($data_sql);
$stmt_data->bind_param('ssii', $like_code, $like_size, $offset, $items_per_page);
$stmt_data->execute();
$data_result = $stmt_data->get_result();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>คลังไม้ WIP Inventory</title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<div class="container mt-4">
    <h1 class="mb-4">คลังไม้ WIP Inventory</h1>
    <!-- ฟอร์มค้นหา -->
    <form method="get" class="row g-3 mb-4">
        <div class="col-md-4">
            <input type="text" name="search_code" class="form-control" placeholder="ค้นหา Part Code"
                   value="<?php echo htmlspecialchars($search_code); ?>">
        </div>
        <div class="col-md-4">
            <input type="text" name="search_size" class="form-control" placeholder="ค้นหา ขนาด (หนา x กว้าง x ยาว)"
                   value="<?php echo htmlspecialchars($search_size); ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">ค้นหา</button>
        </div>
        <div class="col-md-2">
            <a href="wip_inventory.php" class="btn btn-secondary w-100">รีเซ็ต</a>
        </div>
    </form>

    <table class="table table-bordered table-striped">
        <thead class="table-light">
            <tr>
                <th>Part Code</th>
                <th>ขนาด<br>(หนา x กว้าง x ยาว)</th>
                <th>จำนวนคงเหลือ</th>
                <th>ปริมาณ (%)</th>
                <th>Total m<sup>3</sup></th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = $data_result->fetch_assoc()):
            $percent = $row['max'] > 0 ? ($row['quantity'] / $row['max']) * 100 : 0;
            $percent = min(100, max(0, $percent));
            $barFillColor = ($row['quantity'] < $row['min']) ? $barFillColorLow : $barFillColorNormal;
            $total_m3 = $row['part_m3'] * $row['quantity'];
        ?>
            <tr>
                <td><?php echo htmlspecialchars($row['part_code']); ?></td>
                <td><?php echo htmlspecialchars(
                    $row['part_thickness'].' x '.
                    $row['part_width'].' x '.
                    $row['part_length']
                ); ?></td>
                <td><?php echo number_format($row['quantity']); ?></td>
                <td style="width: 25%;">
                    <div class="d-flex align-items-center">
                        <span><?php echo number_format($row['min']); ?></span>
                        <div class="progress position-relative flex-grow-1 mx-2"
                             style="height: 1.5rem; background-color: <?php echo $barBgColor; ?>;">
                            <div class="progress-bar" role="progressbar"
                                 style="width: <?php echo $percent; ?>%; background-color: <?php echo $barFillColor; ?>;"
                                 aria-valuenow="<?php echo $percent; ?>"
                                 aria-valuemin="0" aria-valuemax="100">
                            </div>
                            <span class="position-absolute top-50 start-50 translate-middle text-white fw-bold">
                                <?php echo number_format($percent, 0); ?>%
                            </span>
                        </div>
                        <span><?php echo number_format($row['max']); ?></span>
                    </div>
                </td>
                <td><?php echo number_format($total_m3, 6); ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
                <li class="page-item"><a class="page-link" href="?page=<?php echo $page-1;
                    ?>&search_code=<?php echo urlencode($search_code);
                    ?>&search_size=<?php echo urlencode($search_size); ?>">ก่อนหน้า</a></li>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <li class="page-item<?php if ($p === $page) echo ' active'; ?>">
                    <a class="page-link" href="?page=<?php echo $p;
                        ?>&search_code=<?php echo urlencode($search_code);
                        ?>&search_size=<?php echo urlencode($search_size); ?>"><?php echo $p; ?></a>
                </li>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <li class="page-item"><a class="page-link" href="?page=<?php echo $page+1;
                    ?>&search_code=<?php echo urlencode($search_code);
                    ?>&search_size=<?php echo urlencode($search_size); ?>">ถัดไป</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</div>

<?php include __DIR__ . '/footer.php'; ?>
<!-- Bootstrap 5.3 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>