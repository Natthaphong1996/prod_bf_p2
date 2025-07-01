<?php
// เรียกใช้ TCPDF
require_once('tcpdf/tcpdf.php');

// เชื่อมต่อฐานข้อมูล
include 'config_db.php';

// ตรวจสอบว่าได้ส่ง repair_id มาหรือไม่
if (isset($_GET['repair_id'])) {
    $repair_id = $_GET['repair_id'];

    // สร้างคำสั่ง SQL เพื่อดึงข้อมูล customer_name
    $sql = "
        SELECT c.customer_name 
        FROM repair_issue ri
        JOIN wood_issue wi ON ri.job_id = wi.job_id
        JOIN prod_list pl ON wi.prod_id = pl.prod_id
        JOIN customer c ON pl.customer_id = c.customer_id
        WHERE ri.repair_id = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $repair_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // ถ้ามีข้อมูล
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $customer_name = $row['customer_name'];
    } else {
        $customer_name = 'ไม่พบข้อมูลลูกค้า';
    }

    // คำสั่ง SQL เพื่อดึงข้อมูลจาก repair_issue โดยใช้ repair_id
    $sql = "SELECT * FROM repair_issue WHERE repair_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $repair_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // ถ้ามีข้อมูล
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // สร้าง PDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('SIAMKYOHWA SEISAKUSHO');
        $pdf->SetTitle('ใบเบิกไม้งานซ่อม : '. $row['repair_id'] );
        $pdf->SetSubject('ใบเบิกไม้งานซ่อม');
        $pdf->setPrintHeader(val: false); // ไม่พิมพ์ Header
        $pdf->setPrintFooter(false); // ไม่พิมพ์ Footer
        $pdf->AddPage();  // เพิ่มหน้า PDF
        $pdf->SetFont('thsarabunnew', '', 14); // ใช้ฟอนต์ THSarabunNew

        // เพิ่มโลโก้
        $logoPath = 'logo/logo.png'; 
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 155, 5, 30, 30, '', '', '', true, 400, '', false, false, 0, false, false, false);
        }

        // เพิ่มข้อความใต้โลโก้
        $pdf->SetXY(147, 30);
        $pdf->Cell(0, 10, 'บริษัท สยามเคียววะ เซซาคูโช จำกัด', 0, 1, 'L');
        $pdf->SetXY(153, 40);
        $pdf->SetFont('thsarabunnew', '', 18); // ใช้ฟอนต์ THSarabunNew
        $pdf->Cell(0, 10, 'ใบเบิกไม้ - งานซ่อม', 0, 1, 'L');

        $pdf->Ln(10); 
        $pdf->SetFont('thsarabunnew', '', 14); // ใช้ฟอนต์ THSarabunNew
        // ใช้ HTML เพื่อแสดงข้อมูลในตาราง
        $html = '
        <table border="1" cellpadding="6" style="border-collapse: collapse; width: 100%;">
            <tbody>
                <tr>
                    <th style="text-align: center; width: 25%;">หมายเลข JOB</th>
                    <th style="text-align: center; width: 25%;">' . $row['job_id'] . '</th>
                    <th style="text-align: center; width: 25%;">หมายเลขใบเบิกซ่อม</th>
                    <th style="text-align: center; width: 25%;">' . $row['repair_id'] . '</th>
                </tr>
                <tr>
                    <th style="text-align: center; width: 25%;">ชื่อลูกค้า</th>
                    <th style="text-align: center; width: 75%;">' . $customer_name . '</th>
                </tr>
                <tr>
                    <th style="text-align: center; width: 25%;">วันที่ออกเอกสาร</th>
                    <th style="text-align: center; width: 25%;">' . date('d-m-y', strtotime($row['created_at'])) . '</th>
                    <th style="text-align: center; width: 25%;">วันที่ต้องการรับไม้</th>
                    <th style="text-align: center; width: 25%;">' . date('d-m-y', strtotime($row['want_receive'])) . '</th>
                </tr>
            </tbody>
        </table>
        ';

        // แปลงข้อมูล JSON ของ parts ที่เบิกซ่อม
        $parts = json_decode($row['part_quantity_reason'], true);

        // เพิ่มตารางสำหรับ Parts ที่เบิกซ่อม
        $html .= '
        <table border="1" cellpadding="6" style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr>
                    <th style="text-align: center; width: 60%; ">PART CODE</th>
                    <th style="text-align: center; width: 20%; ">จำนวน</th>
                    <th style="text-align: center; width: 20%; ">เหตุผล</th>
                </tr>
            </thead>
            <tbody>';

        // เพิ่มแถวสำหรับข้อมูล Parts
        foreach ($parts as $part) {
            // รับค่า part_id จาก $part
            $part_id = $part['part_id'];
            
            // ค้นหาข้อมูลจากตาราง part_list โดยใช้ part_id
            $sql_part = "SELECT part_code, part_type, part_thickness, part_width, part_length FROM part_list WHERE part_id = ?";
            $stmt = $conn->prepare($sql_part);
            $stmt->bind_param("s", $part_id); // ใช้ part_id ที่ได้
            $stmt->execute();
            $result_part = $stmt->get_result();
            
            // ถ้าพบข้อมูลใน part_list
            if ($result_part->num_rows > 0) {
                $row_part = $result_part->fetch_assoc();
                
                // ดึงข้อมูลจากผลลัพธ์
                $part_code = $row_part['part_code'];
                $part_type = $row_part['part_type'];
                $part_thickness = $row_part['part_thickness'];
                $part_width = $row_part['part_width'];
                $part_length = $row_part['part_length'];

                // ตรวจสอบและจัดการกรณีที่คีย์ part_length, part_width, part_thickness อาจจะไม่ถูกตั้งค่า
                $part_length = isset($part_length) ? $part_length : 'X';
                $part_width = isset($part_width) ? $part_width : 'X';
                $part_thickness = isset($part_thickness) ? $part_thickness : 'X';

                // สร้าง HTML สำหรับแสดงผล
                $html .= '<tr>
                            <td style="text-align: center; width: 60%;">' . $part_code . " | " . $part_type . " | " . $part_length . "X" . $part_width . "X" . $part_thickness . " มม.".'</td>
                            <td style="text-align: center; width: 20%;">' . $part['quantity'] . '</td>
                            <td style="text-align: center; width: 20%;">' . $part['reason'] . '</td>
                        </tr>';
            } else {
                // ถ้าไม่พบข้อมูลใน part_list ให้ใช้ค่าเริ่มต้นหรือแสดงข้อความที่เหมาะสม
                $html .= '<tr>
                            <td style="text-align: center; width: 60%;">' . $part_id . ' | X | X | X</td>
                            <td style="text-align: center; width: 20%;">' . $part['quantity'] . '</td>
                            <td style="text-align: center; width: 20%;">' . $part['reason'] . '</td>
                        </tr>';
            }

            $stmt->close(); // ปิดคำสั่ง SQL
        }

        $html .= '</tbody></table>';

        // เพิ่มข้อมูลชื่อผู้เบิก และวันเวลาเบิก
        $html .= '
        <br>
        <br>    
        <br>
        <table border="0" style="width: 100%;">
            <tr>
                <td style="font-weight: bold; width: 85%; text-align: right;">ออกโดย:</td>
                <td style="font-weight: bold; width: 15%; text-align: right;">' . (empty($row['create_by']) ? '-' : htmlspecialchars($row['create_by'])) . '</td>
            </tr>
            <tr>
                <td style="font-weight: bold; width: 85%; text-align: right;">เบิกโดย:</td>
                <td style="font-weight: bold; width: 15%; text-align: right;">' . (empty($row['issued_by']) ? '-' : htmlspecialchars($row['issued_by'])) . '</td>
            </tr>
            <tr>
                <td style="font-weight: bold; width: 85%; text-align: right;">วันเวลาเบิก:</td>
                <td style="font-weight: bold; width: 15%; text-align: right;">' . (!empty($row['issue_date']) ? date('d-m-y H:i', strtotime($row['issue_date'])) : '-') . '</td>
            </tr>
            <tr>
                <td style="font-weight: bold; width: 100%; text-align: right;">FR-PC-0009 Rev.02 (01/08/66)</td>
            </tr>
        </table>';

        // เพิ่มเนื้อหาของ HTML ลงใน PDF
        $pdf->writeHTML($html, true, false, true, false, '');

        // ปิดการเชื่อมต่อฐานข้อมูล
        $conn->close();

        // ส่ง PDF ให้ผู้ใช้ดาวน์โหลด
        $pdf->Output('ใบเบิกไม้งานซ่อม_' . $repair_id . '.pdf', 'I');  // สั่งให้ TCPDF ส่งไฟล์ PDF ไปยังเบราว์เซอร์ให้ผู้ใช้ดาวน์โหลด
    } else {
        echo "ไม่พบข้อมูลสำหรับ Repair ID นี้";
    }
} else {
    echo "ไม่พบ Repair ID";
}
?>
