<?php
require_once('tcpdf/tcpdf.php'); // โหลด TCPDF
include 'config_db.php';

// รับ issue_id จาก URL
$issue_id = $_GET['issue_id'] ?? 0;

// ดึงข้อมูลจากฐานข้อมูล wood_issue พร้อม Part No และ prod_description
$query = "
SELECT w.*, p.prod_partno, p.prod_description, p.prod_code
FROM wood_issue w
LEFT JOIN prod_list p ON p.prod_id = w.prod_id
WHERE w.issue_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $issue_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();

    // สร้าง PDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

    // ตั้งค่าหน้ากระดาษ
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('SIAMKYOHWA SEISAKUSHO');
    $pdf->SetTitle('ใบเบิกไม้ หมายเลข : '.$row['job_id']);
    $pdf->SetSubject('ใบเบิกไม้');
    $pdf->SetMargins(10, 10, 10, true); 
    $pdf->setPrintHeader(false); // ไม่พิมพ์ Header
    $pdf->setPrintFooter(false); // ไม่พิมพ์ Footer
    $pdf->AddPage();

    // ตั้งค่า Font ภาษาไทย
    $pdf->SetFont('thsarabunnew', '', 14); // ใช้ฟอนต์ THSarabunNew

    // เพิ่มโลโก้
    $logoPath = 'logo/logo.png'; 
    if (file_exists($logoPath)) {
        $pdf->Image($logoPath, 155, 5, 30, 30, '', '', '', true, 400, '', false, false, 0, false, false, false);
    }

    // เพิ่มข้อความใต้โลโก้
    $pdf->SetXY(147, 30);
    $pdf->Cell(0, 10, 'บริษัท สยามเคียววะ เซซาคูโช จำกัด', 0, 1, 'L');
    $pdf->SetXY(163, 40);
    $pdf->Cell(0, 10, 'ใบเบิกไม้', 0, 1, 'L');

    // ดึงข้อมูล Part และ Nail จาก BOM
    $bomQuery = "SELECT parts, nails FROM bom WHERE prod_id = ?";
    $bomStmt = $conn->prepare($bomQuery);
    $bomStmt->bind_param("s", $row['prod_id']);
    $bomStmt->execute();
    $bomResult = $bomStmt->get_result();

    $parts = [];
    $nails = [];
    if ($bomResult->num_rows > 0) {
        $bomRow = $bomResult->fetch_assoc();
        $parts = !empty($bomRow['parts']) ? json_decode($bomRow['parts'], true) : [];
        $nails = !empty($bomRow['nails']) ? json_decode($bomRow['nails'], true) : [];
    }

    // ดึงชื่อลูกค้า
    $customerName = "-";
    $prodListQuery = "SELECT customer_id FROM prod_list WHERE prod_id = ?";
    $prodListStmt = $conn->prepare($prodListQuery);
    $prodListStmt->bind_param("s", $row['prod_id']);
    $prodListStmt->execute();
    $prodListResult = $prodListStmt->get_result();

    if ($prodListResult->num_rows > 0) {
        $prodListRow = $prodListResult->fetch_assoc();
        $customerId = $prodListRow['customer_id'];

        // ดึง customer_name จาก customer table
        $customerQuery = "SELECT customer_name FROM customer WHERE customer_id = ?";
        $customerStmt = $conn->prepare($customerQuery);
        $customerStmt->bind_param("i", $customerId);
        $customerStmt->execute();
        $customerResult = $customerStmt->get_result();

        if ($customerResult->num_rows > 0) {
            $customerRow = $customerResult->fetch_assoc();
            $customerName = $customerRow['customer_name'];
        }
    }

    // เพิ่มหัวข้อใน PDF
    $html = '
    <br>
    <table border="1" cellpadding="4" style="margin-right: px; border-collapse: collapse; width: 100%;">
        <tr>
            <td style="text-align: center; width: 30%;"><b><i>หมายเลข JOB</i></b></td>
            <td rowspan="2" style="text-align: left; width: 70%; color: red; white-space: nowrap;">' . '<strong>' ."หมายเหตุ : ". htmlspecialchars(string: $row['remark']) . '</strong>' . '</td>
        </tr>
        <tr>
            <td style="text-align: center; width: 30%;">' . '<strong>' . htmlspecialchars($row['job_id']) . '</strong>' . '</td>
        </tr>
    </table>
    <br>
    <table border="1" cellpadding="4" style="border-collapse: collapse; width: 100%;">
        <tr>
            <td style="font-weight: bold; width: 13%;">วันที่ออกเอกสาร</td>
            <td style="width: 20%;">' . (!empty($row['creation_date']) ? date('d-m-y H:i', strtotime($row['creation_date'])) : '-') . '</td>
            <td style="font-weight: bold; width: 15%;">วันที่ต้องการรับไม้</td>
            <td style="width: 19%;">' . (!empty($row['want_receive']) ? date('d-m-y', strtotime($row['want_receive'])) : '-') . '</td>
            <td style="font-weight: bold; width: 11%;">วันที่รับไม้</td>
            <td style="width: 22%;">' . (!empty($row['issue_date']) ? date('d-m-y H:i', strtotime($row['issue_date'])) : '-') . '</td>
        </tr>
        <tr>
            <td style="font-weight: bold; width: 7%;">ลูกค้า</td>
            <td style="width: 26%;">' . htmlspecialchars($customerName) . '</td>
            <td style="font-weight: bold; width: 13%;">ITEM CODE FG</td>
            <td style="width: 21%;">' . htmlspecialchars($row['prod_code']) . '</td>
            <td style="font-weight: bold; width: 11%;">PART NO.</td>
            <td style="width: 22%;">' . htmlspecialchars(isset($row['prod_partno']) ? $row['prod_partno'] : '-') . '</td>
        </tr>
        <tr>
            <td style="font-weight: bold; width: 10%;">รายละเอียด</td>
            <td style="width: 90%;">' . htmlspecialchars($row['prod_description']) . '</td>
        </tr>
        <tr>
            <td style="font-weight: bold; width: 7%;">จำนวน</td>
            <td style="width: 26%;">' . htmlspecialchars($row['quantity']) . '</td>
            <td style="font-weight: bold; width: 13%;">ประเภทไม้</td>
            <td style="width: 21%;">' . htmlspecialchars($row['wood_type']) . '</td>
            <td style="font-weight: bold; width: 11%;">เผื่อไม้เสีย %</td>
            <td style="width: 22%;">' . htmlspecialchars($row['wood_wastage']) . '</td>
        </tr>
    </table>
    <table border="1" cellpadding="6" style="border-collapse: collapse; width: 100%;">
        <tr>
            <th width="7%" style="text-align: center; font-weight: bold;">ลำดับ</th>
            <th width="71%" style="text-align: center; font-weight: bold;">รายการ Part ประกอบ</th>
            <th width="22%" style="text-align: center; font-weight: bold;">จำนวนทั้งหมด</th>
        </tr>';

    // ดึงข้อมูลจาก part_list และคำนวณจำนวนทั้งหมด
    if (!empty($parts)) {
        $index = 1;
        foreach ($parts as $part) {
            $partQuery = "SELECT * FROM part_list WHERE part_id = ?";
            $partStmt = $conn->prepare($partQuery);
            $partStmt->bind_param("i", $part['part_id']);
            $partStmt->execute();
            $partResult = $partStmt->get_result();

            if ($partResult->num_rows > 0) {
                $partRow = $partResult->fetch_assoc();

                // คำนวณจำนวนทั้งหมดพร้อมเผื่อไม้เสีย
                $totalQty = ceil($row['quantity'] * $part['quantity'] * (1 + ($row['wood_wastage'] / 100)));

                // รายการ Part ประกอบ
                $partDescription = htmlspecialchars($partRow['part_code']) . ' | ' .
                    htmlspecialchars($partRow['part_type']) . ' | ' .
                    htmlspecialchars($partRow['part_length']) . ' X ' .
                    htmlspecialchars($partRow['part_width']) . ' X ' .
                    htmlspecialchars($partRow['part_thickness']) ;
                    

                $html .= '
                <tr>
                    <td style="text-align: center;">' . $index . '</td>
                    <td>' . $partDescription . '</td>
                    <td style="text-align: center;">' . $totalQty . '</td>
                </tr>';
                $index++;
            }
        }
    } else {
        $html .= '
        <tr>
            <td colspan="3" style="text-align: center;">ไม่มีข้อมูล Part</td>
        </tr>';
    }

    $html .= '</table>
    <br>
    <table border="0" cellpadding="6" style="border-collapse: collapse; width: 100%;">
        ';

     $html .= '</table>';

    // เพิ่มชื่อผู้เบิก และวันเวลาเบิกด้านล่าง
    $html .= '
            <div>
            <br>
            <table border="0" style="">
                    <tr>
                    <td style="font-weight: bold; width: 85%; text-align: right;">ออกโดย:</td>
                    <td style="font-weight: bold; width: 15%; text-align: right;">' . (empty($row['create_by']) ? '-' : htmlspecialchars($row['create_by'])) . '</td> 
                    </tr>
                    <tr>
                    <td style="font-weight: bold; width: 85%; text-align: right;">ผู้เบิก:</td>
                    <td style="font-weight: bold; width: 15%; text-align: right;">' . (empty($row['issued_by']) ? '-' : htmlspecialchars($row['issued_by'])) . '</td> 
                    </tr>
                    <tr>
                    <td style="font-weight: bold; width: 85%; text-align: right;">วันเวลาเบิก:</td>
                    <td style="font-weight: bold; width: 15%; text-align: right;">' . (!empty($row['issue_date']) ? date('d-m-y H:i', strtotime($row['issue_date'])) : '-') . '</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; width: 100%; text-align: right;">FR-PC-0003 Rev.04 (03/01/67)</td>
                    </tr>
                    <br>
                    <tr>
                        <td style="font-weight: bold; width: 100%; text-align: right;">จุดประกอบ..............................จำนวน..........................................</td>
                        
                    </tr>
                    <br>
                    <tr>
                        <td style="font-weight: bold; width: 100%; text-align: right;">ลงชื่อผู้ส่งงาน..............................ลงชื่อผู้ตรวจ..............................</td>
                    </tr>
                    <br>
                    <tr>
                        <td style="font-weight: bold; width: 100%; text-align: right;">วันที่..................................หมายเหตุ............................................</td>
                    </tr>
                </table>
            </div>';

    // เพิ่มเนื้อหา HTML ลงใน PDF
    $pdf->writeHTML($html, true, false, true, false, '');

    // ถ้ามีข้อมูล nails ค่อยสร้างหน้าใหม่
    // if (!empty($nails)) {
    //     $pdf->AddPage();
    //     // เพิ่มโลโก้
    //     $logoPath = 'logo/logo.png'; 
    //     if (file_exists($logoPath)) {
    //         $pdf->Image($logoPath, 155, 5, 30, 30, '', '', '', true, 400, '', false, false, 0, false, false, false);
    //     }

    //     // เพิ่มข้อความใต้โลโก้
    //     $pdf->SetXY(147, 30);
    //     $pdf->Cell(0, 10, 'บริษัท สยามเคียววาเซซาคุโช จำกัด', 0, 1, 'L');
    //     $pdf->SetXY(160, 40);
    //     $pdf->Cell(0, 10, 'เอกสารเบิกตะปู', 0, 1, 'L');
    //     $pdf->SetFont('thsarabunnew', '', 14);

    //     $htmlNail = '<table border="1" cellpadding="6" style="border-collapse: collapse; width: 100%;">
    //         <tr>
    //             <th width="100%" style="text-align: center; font-weight: bold;">รายละเอียดการเบิกตะปู</th>
    //         </tr>
    //         <tr>
    //             <td style="text-align: center; width: 15%;">' . '<strong>' . 'หมายเลข JOB' . '</strong>' . '</td>
    //             <td style="text-align: center; width: 20%;">' . '<strong>' . htmlspecialchars($row['job_id']) . '</strong>' . '</td>
    //             <td style="text-align: center; width: 15%;">' . '<strong>' . 'ITEM CODE FG' . '</strong>' . '</td>
    //             <td style="text-align: center; width: 20%;">' . '<strong>' . htmlspecialchars($row['prod_code']) . '</strong>' . '</td>
    //             <td style="text-align: center; width: 10%;">' . '<strong>' . 'PART NO.' . '</strong>' . '</td>
    //             <td style="text-align: center; width: 20%;">' . '<strong>' . htmlspecialchars($row['prod_partno']) . '</strong>' . '</td>
    //         </tr>
    //         <tr>
    //             <td style="text-align: center; width: 15%;">' . '<strong>' . 'ประกอบจำนวน' . '</strong>' . '</td>
    //             <td style="text-align: center; width: 20%;">' . '<strong>' . htmlspecialchars($row['quantity']) .' ตัว'. '</strong>' . '</td>
    //             <td style="text-align: center; width: 15%;">' . '<strong>' . 'วันที่ออกเอกสาร' . '</strong>' . '</td>
    //             <td style="text-align: center; width: 20%;">' . '<strong>' . htmlspecialchars($row['creation_date']) . '</strong>' . '</td>
    //             <td style="text-align: center; width: 10%;">' . '<strong>' . 'วันที่ใช้งาน' . '</strong>' . '</td>
    //             <td style="font-weight: bold; width: 20%; text-align: center;">' . (!empty($row['want_receive']) ? date('Y-m-d', strtotime($row['want_receive'])) : '-') . '</td>
                
    //         </tr>
    //         <tr>
    //             <th width="10%" style="text-align: center; font-weight: bold;">ลำดับ</th>
    //             <th width="50%" style="text-align: center; font-weight: bold;">รหัสตะปู</th>
    //             <th width="20%" style="text-align: center; font-weight: bold;">จำนวนรวม (ดอก)</th>
    //             <th width="20%" style="text-align: center; font-weight: bold;">จำนวนม้วนที่ต้องใช้</th>
    //         </tr>';

    //     $nailIndex = 1;
    //     foreach ($nails as $nail) {
    //         $nail_id = $nail['nail_id'];
    //         $quantity_needed = $nail['quantity'] * $row['quantity'];

    //         $nailQuery = "SELECT nail_code, nail_pcsperroll FROM nail WHERE nail_id = ?";
    //         $nailStmt = $conn->prepare($nailQuery);
    //         $nailStmt->bind_param("i", $nail_id);
    //         $nailStmt->execute();
    //         $nailResult = $nailStmt->get_result();

    //         if ($nailResult->num_rows > 0) {
    //             $nailRow = $nailResult->fetch_assoc();
    //             $nail_code = $nailRow['nail_code'];
    //             $pcs_per_roll = $nailRow['nail_pcsperroll'];
    //             $rolls_required = ceil(($quantity_needed) / $pcs_per_roll);

    //             $htmlNail .= '<tr>
    //                 <td style="text-align: center;">' . $nailIndex . '</td>
    //                 <td style="text-align: center;">' . htmlspecialchars($nail_code) . '</td>
    //                 <td style="text-align: center;">' . htmlspecialchars($quantity_needed) . '</td>
    //                 <td style="text-align: center;">' . $rolls_required . '</td>
    //             </tr>';

    //             $nailIndex++;
    //         }
    //     }

    //     $htmlNail .= '</table>';
    //     $pdf->writeHTML($htmlNail, true, false, true, false, '');
    //     // ลายเซ็น
    //     $pdf->SetY(240);
    //     $pdf->writeHTMLCell(0, 0, '', '', '
    //     <table style="width: 100%; text-align: center;">
    //         <tr>
    //             <td><div style="border-bottom: 1px solid #000; width: 50%; margin: auto;">&nbsp;</div>ผู้เบิกจ่าย<br>' . '.....................................................................' . '<br>เจ้าหน้าที่คลัง</td>
    //             <td><div style="border-bottom: 1px solid #000; width: 50%; margin: auto;">&nbsp;</div>ผู้รับอุปกรณ์<br>' . '.....................................................................' . '<br>เจ้าหน้าที่จุดประกอบ</td>
    //         </tr>
    //     </table>', 0, 1, false, true, 'C', true);
    // }

    // แสดง PDF
    $pdf->Output('issued_' . htmlspecialchars($row['issue_id']) . '.pdf', 'I'); 
} else {
    echo "ไม่พบข้อมูลสำหรับ Issue ID นี้";
}

$stmt->close();
$conn->close();
