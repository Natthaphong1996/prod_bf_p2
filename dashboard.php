<?php
session_start();
require_once 'config_db.php'; // เรียกใช้งานไฟล์เชื่อมต่อฐานข้อมูล

// ตรวจสอบว่ามีการเลือกเดือนไหม ถ้าไม่มีให้ใช้เดือนปัจจุบัน
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// คำสั่ง SQL สำหรับดึงข้อมูลการใช้ Part
$sql_parts = "
SELECT 
    p.part_code,
    p.part_type,
    CONCAT(p.part_thickness, ' x ', p.part_width, ' x ', p.part_length) AS size,
    SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(b.parts, CONCAT('$[', numbers.n - 1, '].quantity'))) AS UNSIGNED) * f.forecast_quantity) AS total_quantity_used,
    SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(b.parts, CONCAT('$[', numbers.n - 1, '].quantity'))) AS UNSIGNED) * f.forecast_quantity *
        (p.part_thickness * p.part_width * p.part_length) / 1000000000) AS total_m3_used
FROM
    bom b
JOIN
    (SELECT @row := @row + 1 AS n 
     FROM (SELECT 0 UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 
           UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) t1,
          (SELECT 0 UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 
           UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) t2,
          (SELECT @row := 0) t3
    ) numbers
JOIN
    part_list p ON JSON_UNQUOTE(JSON_EXTRACT(b.parts, CONCAT('$[', numbers.n - 1, '].part_id'))) = p.part_id
JOIN
    forecast f ON b.prod_id = f.prod_id
WHERE 
    JSON_UNQUOTE(JSON_EXTRACT(b.parts, CONCAT('$[', numbers.n - 1, ']'))) IS NOT NULL
    AND DATE_FORMAT(f.forecast_date, '%Y-%m') = ?
GROUP BY
    p.part_code, p.part_type, size;

";
$stmt_parts = $conn->prepare($sql_parts);
$stmt_parts->bind_param("s", $selected_month);
$stmt_parts->execute();
$result_parts = $stmt_parts->get_result();

// ตัวแปรสำหรับเก็บผลรวม M3 ที่ใช้ทั้งหมด
$total_m3_all_parts = 0;

// คำสั่ง SQL สำหรับดึงข้อมูลการใช้ตะปู
$sql_nails = "
    SELECT
        n.nail_code,
        SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(b.nails, CONCAT('$[', numbers.n - 1, '].quantity'))) AS UNSIGNED) * f.forecast_quantity) AS total_nails_used
    FROM
        bom b
    JOIN
        (SELECT @row := @row + 1 AS n FROM
            (SELECT 0 UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) t1,
            (SELECT 0 UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) t2,
            (SELECT @row := 0) t3
        ) numbers
    JOIN
        nail n ON JSON_UNQUOTE(JSON_EXTRACT(b.nails, CONCAT('$[', numbers.n - 1, '].nail_id'))) = n.nail_id
    JOIN
        forecast f ON b.prod_id = f.prod_id
    WHERE
        JSON_UNQUOTE(JSON_EXTRACT(b.nails, CONCAT('$[', numbers.n - 1, ']'))) IS NOT NULL
    AND
        DATE_FORMAT(f.forecast_date, '%Y-%m') = ?
    GROUP BY
        n.nail_code;
";
$stmt_nails = $conn->prepare($sql_nails);
$stmt_nails->bind_param("s", $selected_month);
$stmt_nails->execute();
$result_nails = $stmt_nails->get_result();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - การใช้ Part และ ตะปู</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card-custom {
            margin-bottom: 20px;
            padding: 15px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            transition: transform 0.2s;
        }
        .card-custom:hover {
            transform: scale(1.05);
        }
        .card-header-custom {
            font-weight: bold;
            background-color: #f8f9fa;
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .card-body-custom {
            padding: 10px;
        }
        .summary-card {
            background-color: #28a745;
            color: white;
        }
        .btn-export {
            margin-bottom: 20px;
        }
        .btn-export form {
            display: inline-block;
        }
        .btn-export form.me-2 {
            margin-right: 10px;
        }
        .custom-blue-background {
            background-color: #007bff; /* สีน้ำเงิน */
            color: white; /* ข้อความสีขาว */
            padding: 10px;
            border-radius: 5px;
        }

    </style>
</head>
<body>

<?php include 'navbar.php';?>

<div class="container mt-5">
    <h1>Dashboard - การใช้ Part และ ตะปู</h1>

    <!-- ฟอร์มเลือกเดือน -->
    <form method="GET" action="dashboard.php" class="mb-4">
        <div class="row">
            <div class="col-md-4">
                <label for="month" class="form-label">เลือกเดือน</label>
                <input type="month" id="month" name="month" class="form-control" value="<?php echo $selected_month; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">ค้นหา</button>
            </div>
        </div>
    </form>

    <div class="btn-group btn-export">
        <form method="GET" action="export_dashboard_pdf.php" class="me-2">
            <input type="hidden" name="month" value="<?php echo $selected_month; ?>">
            <button type="submit" class="btn btn-danger">
                <i class="fas fa-file-pdf"></i> ส่งออก PDF
            </button>
        </form>
        <form method="GET" action="export_dashboard_csv.php">
            <input type="hidden" name="month" value="<?php echo $selected_month; ?>">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-file-csv"></i> ส่งออก CSV
            </button>
        </form>
    </div>

    <!-- ลิงก์สำหรับการใช้งาน Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

<!-- การแสดงข้อมูลการใช้ Part -->
    <div class="card mb-4">
        <div class="card-header">การใช้ Part (M3) สำหรับเดือน <?php echo date('F Y', strtotime($selected_month)); ?></div>
        <div class="card-body">
            <?php
$part_types = [];
while ($row = $result_parts->fetch_assoc()) {
    $part_types[$row['part_type']]['parts'][] = $row;
    $part_types[$row['part_type']]['total_m3'] = ($part_types[$row['part_type']]['total_m3'] ?? 0) + $row['total_m3_used'];
}
?>

            <?php if (count($part_types) > 0): ?>
                <?php foreach ($part_types as $type => $data): ?>
                    <div class="mb-4">
                    <h5 class="card-header-custom custom-blue-background"><?php echo $type; ?> (M3 ที่ใช้ทั้งหมด: <?php echo number_format($data['total_m3'], 6); ?>)</h5>

                        <div class="row">
                            <?php foreach ($data['parts'] as $row): ?>
                                <div class="col-md-4">
                                    <div class="card card-custom">
                                        <div class="card-header-custom">Part Code: <?php echo $row['part_code']; ?></div>
                                        <div class="card-body-custom">
                                            <p><strong>ขนาด:</strong> <?php echo $row['size']; ?></p>
                                            <p><strong>M3 ที่ใช้ทั้งหมด:</strong> <?php echo number_format($row['total_m3_used'], 6); ?></p>
                                            <p><strong>จำนวนที่ใช้ทั้งหมด:</strong> <?php echo number_format($row['total_quantity_used']); ?></p>
                                            <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#detailModal"
                                                    onclick="loadPartDetails('<?php echo $row['part_code']; ?>')">
                                                แสดงรายละเอียด
                                            </button>
                                        </div>
                                    </div>
                                    <!-- Modal สำหรับแสดงรายละเอียดงาน -->
                                    <div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="detailModalLabel">รายละเอียดงานที่ใช้ Part นี้</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body" id="detailContent">
                                                    <!-- Loading spinner icon -->
                                                    <div id="loadingSpinner" style="display: none; text-align: center;">
                                                        <div class="spinner-border text-primary" role="status">
                                                            <span class="visually-hidden">Loading...</span>
                                                        </div>
                                                        <p>กำลังโหลดข้อมูล...</p>
                                                    </div>
                                                    <!-- เนื้อหาจาก AJAX จะถูกโหลดตรงนี้ -->
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach;?>
                        </div>
                    </div>
                <?php endforeach;?>
            <?php else: ?>
                <p>ไม่มีข้อมูลการใช้ Part สำหรับเดือนนี้.</p>
            <?php endif;?>
        </div>
    </div>

    <!-- การแสดงข้อมูลการใช้ ตะปู -->
    <div class="card mb-4">
        <div class="card-header custom-blue-background">การใช้ตะปูสำหรับเดือน <?php echo date('F Y', strtotime($selected_month)); ?></div>
        <div class="card-body row">
            <?php if ($result_nails->num_rows > 0): ?>
                <?php while ($row = $result_nails->fetch_assoc()): ?>
                    <div class="col-md-4">
                        <div class="card card-custom">
                            <div class="card-header-custom">รหัสตะปู: <?php echo $row['nail_code']; ?></div>
                            <div class="card-body-custom">
                                <p><strong>จำนวนที่ใช้ทั้งหมด:</strong> <?php echo $row['total_nails_used']; ?></p>
                            </div>
                        </div>
                    </div>
                <?php endwhile;?>
            <?php else: ?>
                <p>ไม่มีข้อมูลการใช้ตะปูสำหรับเดือนนี้.</p>
            <?php endif;?>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


<script>
function loadPartDetails(partCode) {
    // แสดงไอคอนโหลดก่อน
    document.getElementById('loadingSpinner').style.display = 'block';
    document.getElementById('detailContent').innerHTML = ''; // เคลียร์เนื้อหาก่อน

    // รับค่า selected_month จาก PHP
    const selectedMonth = '<?php echo $selected_month; ?>';

    // ทำการ fetch ข้อมูล โดยส่ง partCode และ selected_month ไปยัง fetch_part_details.php
    fetch(`fetch_part_details.php?part_code=${partCode}&month=${selectedMonth}`)
        .then(response => response.text())
        .then(data => {
            // ซ่อนไอคอนโหลดและแสดงข้อมูลที่ดึงมา
            document.getElementById('loadingSpinner').style.display = 'none';
            document.getElementById('detailContent').innerHTML = data;
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('loadingSpinner').style.display = 'none';
        });
}
</script>


</body>
</html>

<?php
// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();
?>
