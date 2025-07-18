<?php
// cutting_job_list.php
// ภาษา: PHP
// หน้าที่: แสดงรายการงานตัด (ปรับปรุงให้ตรงกับ Schema ที่ผู้ใช้ให้มา)

session_start();
require_once __DIR__ . '/config_db.php';

// [แก้ไข] ปรับปรุง SQL ให้ตรงกับโครงสร้างฐานข้อมูลของผู้ใช้
$sql = "
    SELECT
        cj.cutting_job_id,
        cj.job_code, -- ดึง job_code มาแสดง
        cj.part_type,
        cj.job_status,
        rl.recipe_name,
        -- ใช้ CASE เพื่อดึงข้อมูลลูกค้าที่ถูกต้องตามประเภทของงาน
        CASE cj.part_type
            WHEN 'main' THEN c_main.customer_name
            WHEN 'head' THEN c_head.customer_name
            WHEN 'scrap' THEN c_scrap.customer_name
            ELSE NULL
        END AS customer_name,
        -- ใช้ CASE เพื่อดึงข้อมูลชิ้นงานที่ได้ (Part) ที่ถูกต้อง
        CASE cj.part_type
            WHEN 'main' THEN p_main.part_code
            WHEN 'head' THEN p_head.part_code
            WHEN 'scrap' THEN p_scrap.part_code
            ELSE NULL
        END AS part_code
    FROM
        cutting_job AS cj
    JOIN
        cutting_batch AS cb ON cj.batch_id = cb.batch_id
    JOIN
        recipe_list AS rl ON cb.recipe_id = rl.recipe_id
    -- LEFT JOINs สำหรับข้อมูลของแต่ละประเภทงาน
    LEFT JOIN customer AS c_main ON rl.main_customer_id = c_main.customer_id
    LEFT JOIN part_list AS p_main ON rl.main_output_part_id = p_main.part_id
    LEFT JOIN customer AS c_head ON rl.head_customer_id = c_head.customer_id
    LEFT JOIN part_list AS p_head ON rl.head_output_part_id = p_head.part_id
    LEFT JOIN customer AS c_scrap ON rl.scrap_customer_id = c_scrap.customer_id
    LEFT JOIN part_list AS p_scrap ON rl.scrap_output_part_id = p_scrap.part_id
    ORDER BY
        cj.cutting_job_id DESC;
";
$result = $conn->query($sql);

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการงานตัด (Cutting Job List)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<main class="container">
    <div class="d-flex justify-content-between align-items-center mb-4 mt-4">
        <h1><i class="fas fa-tasks"></i> รายการงานตัด</h1>
        <!-- ปุ่มสร้างงานควรจะลิงก์ไปหน้าเลือกสูตรการผลิต -->
        <a href="create_cutting_job.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> สร้างงานตัดใหม่
        </a>
    </div>

    <?php
    if (isset($_SESSION['success_message'])) {
        echo "<div class='alert alert-success' role='alert'>" . htmlspecialchars($_SESSION['success_message']) . "</div>";
        unset($_SESSION['success_message']);
    }
    if (isset($_SESSION['error_message'])) {
        echo "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($_SESSION['error_message']) . "</div>";
        unset($_SESSION['error_message']);
    }
    ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <!-- [แก้ไข] ปรับปรุงหัวตารางใหม่ทั้งหมด -->
                            <th>เลขที่ Job</th>
                            <th>ประเภทงาน</th>
                            <th>ลูกค้า</th>
                            <th>ชิ้นงานที่ได้</th>
                            <th>สถานะ</th>
                            <th class="text-center">พิมพ์</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result && $result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td><strong>" . htmlspecialchars($row['job_code'] ?? 'N/A') . "</strong></td>";
                                
                                // --- [แก้ไข] แปลง part_type เป็นภาษาไทย ---
                                $part_type_display = '';
                                switch ($row['part_type']) {
                                    case 'main':
                                        $part_type_display = 'งานหลัก';
                                        break;
                                    case 'head':
                                        $part_type_display = 'งานหัวไม้';
                                        break;
                                    case 'scrap':
                                        $part_type_display = 'เศษไม้';
                                        break;
                                    default:
                                        $part_type_display = htmlspecialchars(ucfirst($row['part_type']));
                                        break;
                                }
                                echo "<td>" . $part_type_display . "</td>";
                                
                                echo "<td>" . htmlspecialchars($row['customer_name'] ?? 'N/A') . "</td>";
                                echo "<td>" . htmlspecialchars($row['part_code'] ?? 'N/A') . "</td>";
                                
                                // --- ส่วนแสดงผลสถานะ ---
                                $status_class = '';
                                switch ($row['job_status']) {
                                    case 'รอดำเนินการ': $status_class = 'bg-warning text-dark'; break;
                                    case 'กำลังดำเนินการ': $status_class = 'bg-primary text-white'; break;
                                    case 'เสร็จสิ้น': $status_class = 'bg-success text-white'; break;
                                    case 'ยกเลิก': $status_class = 'bg-danger text-white'; break;
                                    default: $status_class = 'bg-secondary text-white'; break;
                                }
                                echo "<td><span class='badge " . $status_class . "'>" . htmlspecialchars($row['job_status']) . "</span></td>";
                                
                                // --- ปุ่มพิมพ์ PDF ---
                                echo "<td class='text-center'><a href='create_cutting_job_pdf.php?id=" . $row['cutting_job_id'] . "' target='_blank' class='btn btn-info btn-sm'><i class='fas fa-print'></i></a></td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6' class='text-center'>ไม่พบข้อมูลงานตัด</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php 
$conn->close();
include __DIR__ . '/footer.php';
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
