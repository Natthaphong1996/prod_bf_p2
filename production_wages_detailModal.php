<?php
include('config_db.php'); // เรียกการเชื่อมต่อฐานข้อมูล
include('wood_issue_functions.php');

if (isset($_GET['id'])) {
    $productionWageId = $_GET['id'];
    // ดึงข้อมูล job_id และวันที่สร้างจาก production_wages โดยใช้ production_wage_id
    $sql = "SELECT job_id, date_create FROM production_wages WHERE production_wage_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $productionWageId);
    $stmt->execute();
    $stmt->bind_result($job_ids, $date_create);
    $stmt->fetch();
    $stmt->close();
    
    if ($job_ids) {
        // แก้ไขสำหรับรูปแบบใหม่ {XXXXXX,1200},{XXXXXX,1500},{XXXXXX,3500}
        // ลบเครื่องหมาย {} รอบนอกแล้วแยก tuple ด้วย '},{'
        $jobTuples = explode('},{', trim($job_ids, '{}'));
        
        // ประกาศตัวแปรสำหรับเก็บผลรวมของจำนวนเงิน
        $totalWagePrice = 0;
        
        echo '<table class="table table-bordered">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Job ID</th>';
        echo '<th>Job Type</th>';
        echo '<th>PRODUCT CODE</th>';
        echo '<th>PART NO</th>';
        echo '<th>จุดประกอบ</th>';
        echo '<th>จำนวนที่ผลิตได้</th>';
        echo '<th>ราคาต่อตัว (บาท)</th>';
        echo '<th>จำนวนเงิน (บาท)</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($jobTuples as $tuple) {
            // แต่ละ tuple อยู่ในรูปแบบ "XXXXXX,1200"
            $parts = explode(',', $tuple);
            $job_id = trim($parts[0]);
            // ดึงราคาจาก tuple (override price)
            $overridePrice = isset($parts[1]) ? floatval(trim($parts[1])) : 0;
            
            // Query ตาราง wood_issue เพื่อดึง job_type และ prod_id
            $sql1 = "SELECT job_type, prod_id FROM wood_issue WHERE job_id = ?";
            $stmt1 = $conn->prepare($sql1);
            $stmt1->bind_param("s", $job_id);
            $stmt1->execute();
            $stmt1->bind_result($job_type, $prod_id);
            $stmt1->fetch();
            $stmt1->close();
            
            // Query ตาราง prod_list เพื่อดึง prod_code และ prod_partno
            $sql2 = "SELECT prod_code, prod_partno FROM prod_list WHERE prod_id = ?";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bind_param("s", $prod_id);
            $stmt2->execute();
            $stmt2->bind_result($prod_code, $prod_partno);
            $stmt2->fetch();
            $stmt2->close();
            
            // Query ตาราง jobs_complete เพื่อดึง prod_complete_qty, production_wage_price, assembly_point
            $sql3 = "SELECT prod_complete_qty, production_wage_price, assembly_point FROM jobs_complete WHERE job_id = ?";
            $stmt3 = $conn->prepare($sql3);
            $stmt3->bind_param("s", $job_id);
            $stmt3->execute();
            $stmt3->bind_result($prod_complete_qty, $production_wage_price, $assembly_point);
            $stmt3->fetch();
            $stmt3->close();
            
            // ใช้ overridePrice จาก tupleเป็นราคาต่อตัว
            $unitPrice = $overridePrice;
            // คำนวณจำนวนเงินสำหรับแถวนี้ (ราคาต่อตัว * จำนวนที่ผลิตได้)
            $lineTotal = floatval($unitPrice) * floatval($prod_complete_qty);
            $totalWagePrice += $lineTotal;
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($job_id) . "</td>";
            echo "<td>" . htmlspecialchars($job_type) . "</td>";
            echo "<td>" . htmlspecialchars($prod_code) . "</td>";
            echo "<td>" . htmlspecialchars($prod_partno) . "</td>";
            echo "<td>" . htmlspecialchars($assembly_point) . "</td>";
            echo "<td>" . htmlspecialchars($prod_complete_qty) . " ตัว" . "</td>";
            echo "<td class='fw-bold'>" . number_format($unitPrice, 2) . "</td>";
            echo "<td class='fw-bold'>" . number_format($lineTotal, 2) . "</td>";
            echo "</tr>";
        }
        
        echo '</tbody>';
        echo '</table>';
        
        // แสดงผลรวมของจำนวนเงินด้านล่างตาราง
        echo "<div class='text-end fs-4 fw-bold'>ค่าแรงประกอบรวม: " . number_format($totalWagePrice, 2) . " บาท</div>";
        echo "<div class='text-end fs-5 fw-bold'>วันที่ออกเอกสาร: " . $date_create . "</div>";
    } else {
        echo "ไม่พบข้อมูลสำหรับ Production Wage ID นี้";
    }
} else {
    echo "ไม่พบข้อมูล ID";
}
?>
