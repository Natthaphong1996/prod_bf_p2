<?php
require_once 'config_db.php';

$part_code = $_GET['part_code']; // รับค่า part_code จาก AJAX
$selected_month = $_GET['month']; // รับค่าเดือนจาก AJAX

if ($part_code && $selected_month) {
    // ปรับปรุงคำสั่ง SQL สำหรับดึงข้อมูลการใช้งานของ part นี้ ตามเดือนที่เลือก
    $sql = "
        SELECT 
            p.part_code,
            p.part_type,
            CONCAT(p.part_thickness, ' x ', p.part_width, ' x ', p.part_length) AS size,
            pl.prod_code,
            pl.prod_description,
            f.forecast_quantity -- เพิ่ม forecast_quantity เพื่อแสดงจำนวนงาน
        FROM 
            bom b
        JOIN 
            (SELECT @row := @row + 1 AS n FROM 
                (SELECT 0 UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) t1, 
                (SELECT 0 UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) t2, 
                (SELECT @row := 0) t3
            ) numbers
        JOIN 
            part_list p ON JSON_UNQUOTE(JSON_EXTRACT(b.parts, CONCAT('$[', numbers.n - 1, '].part_id'))) = p.part_id
        JOIN 
            prod_list pl ON b.prod_code = pl.prod_code
        JOIN 
            forecast f ON f.prod_id = pl.prod_id
        WHERE 
            p.part_code = ?
        AND 
            DATE_FORMAT(f.forecast_date, '%Y-%m') = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $part_code, $selected_month);
    $stmt->execute();
    $result = $stmt->get_result();

    // ตรวจสอบและแสดงข้อมูลของงานที่ใช้ Part นี้ในเดือนที่เลือก
    if ($result->num_rows > 0) {
        echo "<div class='job-details-container'>";
        while ($row = $result->fetch_assoc()) {
            echo "<div class='job-item'>";
            echo "<p><strong>รหัสงาน:</strong> " . $row['prod_code'] . "</p>";
            echo "<p><strong>รายละเอียดงาน:</strong> " . $row['prod_description'] . "</p>";
            echo "<p><strong>จำนวนงาน:</strong> " . $row['forecast_quantity'] . "</p>";
            echo "</div>";
        }
        echo "</div>";
    } else {
        echo "<p>ไม่มีข้อมูลงานที่ใช้ Part นี้ในเดือนที่เลือก.</p>";
    }
} else {
    echo "<p>ไม่พบข้อมูล Part Code หรือเดือนที่เลือก.</p>";
}

$conn->close();
?>
<style>
.job-details-container {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.job-item {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    background-color: #f9f9f9;
}

.job-item p {
    margin: 0;
    font-size: 14px;
}

.job-item p strong {
    color: #333;
}
</style>
