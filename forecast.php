<?php
session_start();
require_once 'config_db.php';

// ตรวจสอบและดึงข้อมูลเดือนที่เลือก
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

// ตรวจสอบว่ามีการกดปุ่ม Export CSV หรือไม่
if (isset($_GET['export']) && $_GET['export'] == '1') {
    exportCSV($conn, $selected_month, $search_query);
    exit;
}
// ฟังก์ชันสำหรับดึงข้อมูล forecast และจัดกลุ่มตามเดือนที่เลือกและการค้นหา
function getForecasts($conn, $selected_month = '', $search_query = '')
{
    $sql = "SELECT f.forecast_id, f.customer_code, p.prod_code, f.prod_id, f.forecast_quantity, 
    DATE_FORMAT(f.forecast_date, '%Y-%m') AS forecast_month, 
    c.customer_name, p.prod_partno, p.prod_description, f.forecast_date, p.prod_id
FROM forecast f
LEFT JOIN customer c ON f.customer_code = c.customer_id
LEFT JOIN prod_list p ON f.prod_id = p.prod_id
WHERE (f.customer_code LIKE ? 
    OR f.prod_id LIKE ? 
    OR c.customer_name LIKE ? 
    OR p.prod_partno LIKE ?)";



    // กรองข้อมูลตามเดือนถ้ามีการเลือก
    if (!empty($selected_month)) {
        $sql .= " AND DATE_FORMAT(f.forecast_date, '%Y-%m') = ?";
    }

    $sql .= " ORDER BY f.forecast_date ASC";
    $stmt = $conn->prepare($sql);
    $search_param = "%$search_query%";

    // ตรวจสอบว่ามีการเลือกเดือนหรือไม่ แล้วกำหนด bind_param ตามเงื่อนไข
    if (!empty($selected_month)) {
        $stmt->bind_param("sssss", $search_param, $search_param, $search_param, $search_param, $selected_month);
    } else {
        $stmt->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $forecasts_by_month = [];
    while ($row = $result->fetch_assoc()) {
        $forecasts_by_month[$row['forecast_month']][] = $row;
    }
    return $forecasts_by_month;
}


// ฟังก์ชันสำหรับการส่งออกเป็น CSV
function exportCSV($conn, $selected_month, $search_query)
{
    $forecasts = getForecasts($conn, $selected_month, $search_query);

    // ตั้งค่า header สำหรับการดาวน์โหลดไฟล์ CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=forecast_export.csv');

    // เปิด output stream สำหรับการเขียนข้อมูล CSV
    $output = fopen('php://output', 'w');

    // เขียน BOM สำหรับ UTF-8
    fwrite($output, "\xEF\xBB\xBF");

    // เขียนหัวตาราง โดยเปลี่ยนจาก Customer Code เป็น Customer Name
    fputcsv($output, ['Forecast ID', 'Customer Name', 'Prod Code', 'Forecast Quantity', 'Forecast Month', 'Part No', 'Description', 'Forecast Date']);

    // เขียนข้อมูล โดยดึง Customer Name แทน Customer Code
    foreach ($forecasts as $month => $forecast_list) {
        foreach ($forecast_list as $forecast) {
            fputcsv($output, [
                $forecast['forecast_id'],
                $forecast['customer_name'], // ใช้ Customer Name แทน Customer Code
                $forecast['prod_code'],
                $forecast['forecast_quantity'],
                $forecast['forecast_month'],
                $forecast['prod_partno'],
                $forecast['prod_description'],
                $forecast['forecast_date']
            ]);
        }
    }

    fclose($output);
}



// ดึงข้อมูล forecast ตามเงื่อนไขที่เลือก
$forecasts_by_month = getForecasts($conn, $selected_month, $search_query);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการคาดการณ์การผลิต</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

    <?php include 'navbar.php'; ?>

    <div class="container mt-5">
        <h2 class="text-center mb-4">รายการคาดการณ์การผลิต</h2>

        <!-- ฟอร์มเลือกเดือนและค้นหา -->
        <form method="GET" action="forecast.php" class="mb-4">
            <div class="row">
                <div class="col-md-4">
                    <label for="month" class="form-label">เลือกเดือน</label>
                    <input type="month" id="month" name="month" class="form-control"
                        value="<?php echo $selected_month; ?>">
                </div>
                <div class="col-md-4">
                    <label for="search" class="form-label">ค้นหา</label>
                    <input type="text" id="search" name="search" class="form-control"
                        placeholder="ค้นหา prod_code, Part No, ชื่อลูกค้า"
                        value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">ค้นหา</button>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" name="export" value="1" class="btn btn-success w-100">Export CSV</button>
                </div>
            </div>
        </form>

        <!-- แสดงผลลัพธ์ของ forecast -->
        <div id="forecast-results">
            <?php foreach ($forecasts_by_month as $month => $forecasts): ?>
                <h3 class="forecast-month-title"><?php echo htmlspecialchars($month); ?></h3>
                <div class="row">
                    <?php foreach ($forecasts as $forecast): ?>
                        <div class="col-md-4">
                            <div class="card forecast-card">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($forecast['prod_code']); ?></h5>
                                    <p class="card-text">
                                        <strong>ลูกค้า:</strong> <?php echo htmlspecialchars($forecast['customer_name']); ?><br>
                                        <strong>Part No:</strong> <?php echo htmlspecialchars($forecast['prod_partno']); ?><br>
                                        <strong>รายละเอียด:</strong>
                                        <?php echo htmlspecialchars($forecast['prod_description']); ?><br>
                                        <strong>จำนวนที่คาดการณ์:</strong>
                                        <?php echo htmlspecialchars($forecast['forecast_quantity']); ?><br>
                                        <strong>วันที่สั่งผลิต:</strong>
                                        <?php echo htmlspecialchars($forecast['forecast_date']); ?>
                                    </p>
                                    <a href="edit_forecast.php?forecast_id=<?php echo $forecast['forecast_id']; ?>"
                                        class="btn btn-warning btn-sm">แก้ไข</a>
                                    <a href="delete_forecast.php?id=<?php echo $forecast['forecast_id']; ?>"
                                        class="btn btn-danger btn-sm"
                                        onclick="return confirm('คุณแน่ใจหรือว่าต้องการลบรายการนี้?');">ลบ</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>