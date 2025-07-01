<?php
require_once('tcpdf/tcpdf.php');
include 'config_db.php';
include 'wood_issue_functions.php';

ob_start();
session_start();

// ดึงชื่อผู้ใช้งาน
$staffName = '........................';
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmtUser = $conn->prepare("SELECT thainame FROM prod_user WHERE user_id = ?");
    $stmtUser->bind_param("s", $user_id);
    $stmtUser->execute();
    $stmtUser->bind_result($thainame);
    if ($stmtUser->fetch()) {
        $staffName = $thainame;
    }
    $stmtUser->close();
}

// กำหนดคอลัมน์ที่จะแสดง
$SHOW_COLUMNS = [
    'job_id'         => true,
    'job_type'       => false,
    'prod_code'      => true,
    'prod_partno'    => false,
    'assembly_point' => true,
    'qty'            => true,
    'wage'           => true,
    'unit_price'     => true,
    'date_complete'  => false,
];

// จำนวนรายการต่อหน้า
$ITEMS_PER_PAGE = 15;

// สร้างคลาสสำหรับ Header / Footer
class CustomPDF extends TCPDF {
    public $invoiceId = '';
    public $dateCreate = '';

    public function Header() {
        if (file_exists('logo/logo.png')) {
            $this->Image('logo/logo.png', 153, 5, 30, 30);
        }
        $this->Ln(10);
        $this->SetFont('thsarabunnew', '', 12);
        $this->Cell(0, 8, 'เลขที่ใบแจ้งหนี้: ' . $this->invoiceId, 0, 1, 'L');
        $this->Cell(0, 8, 'วันที่ออกเอกสาร: ' . date('d-m-Y', strtotime($this->dateCreate)), 0, 1, 'L');
        $this->Ln(3);
        $this->SetXY(149, 30);
        $this->Cell(0, 10, 'บริษัท สยามเคียววะ เซซาคูโช จำกัด', 0, 1, 'L');
        $this->SetXY(144, 40);
        $this->Cell(0, 10, 'ใบเบิกเงินจุดประกอบ (Production Invoice)', 0, 1, 'L');
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('thsarabunnew', '', 12);
        $this->Cell(208, 10, 'หน้า ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// รับรหัส Invoice
$productionWageId = $_GET['id'] ?? '';
if (!$productionWageId) {
    die('ไม่พบรหัส Invoice');
}

// ดึงข้อมูล job_id และ date_create
$stmt = $conn->prepare("SELECT job_id, date_create FROM production_wages WHERE production_wage_id = ?");
$stmt->bind_param("s", $productionWageId);
$stmt->execute();
$stmt->bind_result($job_ids_string, $date_create);
$stmt->fetch();
$stmt->close();

if (!$job_ids_string) {
    die('ไม่พบข้อมูลใบแจ้งหนี้นี้');
}

// แปลงสตริง "{job,price},{job2,price2},…" เป็นอาร์เรย์
$jobIdsArray = [];
if (preg_match_all('/\{(.*?),(.*?)\}/', $job_ids_string, $matches, PREG_SET_ORDER)) {
    foreach ($matches as $m) {
        $jobIdsArray[] = [
            'job_id'     => trim($m[1]),
            'unit_price' => floatval($m[2])
        ];
    }
}

// สร้างข้อมูลแต่ละ JOB พร้อมคำนวณค่า wage
$jobs = [];
// หลังจากสร้าง array $jobs เรียบร้อยแล้ว

$totalWageAll = 0;
foreach ($jobIdsArray as $item) {
    $job_id     = $item['job_id'];
    $unit_price = $item['unit_price'];

    // wood_issue
    $stmt1 = $conn->prepare("SELECT job_type, prod_id FROM wood_issue WHERE job_id = ?");
    $stmt1->bind_param("s", $job_id);
    $stmt1->execute();
    $stmt1->bind_result($job_type, $prod_id);
    $stmt1->fetch();
    $stmt1->close();

    // prod_list
    $stmt2 = $conn->prepare("SELECT prod_code, prod_partno FROM prod_list WHERE prod_id = ?");
    $stmt2->bind_param("s", $prod_id);
    $stmt2->execute();
    $stmt2->bind_result($prod_code, $prod_partno);
    $stmt2->fetch();
    $stmt2->close();

    // jobs_complete
    $stmt3 = $conn->prepare("SELECT prod_complete_qty, assembly_point, date_receive FROM jobs_complete WHERE job_id = ?");
    $stmt3->bind_param("s", $job_id);
    $stmt3->execute();
    $stmt3->bind_result($qty, $point, $date_receive);
    $stmt3->fetch();
    $stmt3->close();

    $wage = $unit_price * $qty;
    $jobs[] = [
        'job_id'         => $job_id,
        'job_type'       => $job_type,
        'prod_code'      => $prod_code,
        'prod_partno'    => $prod_partno,
        'assembly_point' => $point,
        'qty'            => $qty,
        'wage'           => $wage,
        'unit_price'     => $unit_price,
        'date_complete'  => $date_receive,
    ];
    $totalWageAll += $wage;
}

$totalQtyAll = array_sum(array_column($jobs, 'qty'));

// จบ foreach ที่เก็บ $jobs แล้ว ให้เพิ่มโค้ด sort ตรงนี้
usort($jobs, function($a, $b) {
    // แปลงเป็นแค่ปี-เดือน-วัน (ตัดเวลาออก) เพื่อเปรียบเทียบวันเท่านั้น
    $dateA = date('Y-m-d', strtotime(datetime: $a['date_complete']));
    $dateB = date('Y-m-d', strtotime($b['date_complete']));

    if ($dateA < $dateB) {
        return -1;
    } elseif ($dateA > $dateB) {
        return 1;
    }

    // ถ้าวันเดียวกัน: เปรียบเทียบ job_id แบบ lexicographical (จากน้อย→มาก)
    return strcmp($a['job_id'], $b['job_id']);
});

// สร้าง PDF
$pdf = new CustomPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('SIAMKYOHWA SEISAKUSHO');
$pdf->SetTitle('ใบแจ้งหนี้ค่าแรงประกอบ');
$pdf->SetSubject('Production Invoice');
$pdf->SetMargins(10, 45, 10, true);
$pdf->setPrintHeader(true);
$pdf->setPrintFooter(true);
$pdf->SetFont('thsarabunnew', '', 12);
$pdf->invoiceId  = $productionWageId;
$pdf->dateCreate = $date_create;

// ตัวนับลำดับรายการ ให้ต่อเนื่องข้ามหน้า
$rowNo = 1;

// แบ่งหน้า
$chunks = array_chunk($jobs, $ITEMS_PER_PAGE);
foreach ($chunks as $index => $pageJobs) {
    $pdf->AddPage();
    $pageTotal = 0;
    $pageQtyTotal = 0;      // <-- เพิ่มตัวแปรเก็บรวมจำนวนต่อหน้า
    $pdf->Ln(10);

    // สร้างตาราง
    $html  = '<table border="1" cellpadding="4" style="border-collapse: collapse; width: 100%;">';
    $html .= '<thead><tr>';
    $html .= '<th style="text-align:center; width: 5%;">ลำดับ</th>';
    if ($SHOW_COLUMNS['date_complete'])  $html .= '<th style="text-align:center; width:9%;">วันที่ตรวจรับ</th>';
    if ($SHOW_COLUMNS['job_id'])         $html .= '<th style="text-align:center; width:10%;">Job ID</th>';
    if ($SHOW_COLUMNS['job_type'])       $html .= '<th style="text-align:center;">Job Type</th>';
    if ($SHOW_COLUMNS['prod_code'])      $html .= '<th style="text-align:center; width:40%;">Product Code</th>';
    if ($SHOW_COLUMNS['prod_partno'])    $html .= '<th style="text-align:center;">Part No</th>';
    if ($SHOW_COLUMNS['assembly_point']) $html .= '<th style="text-align:center; width:10%;">จุดประกอบ</th>';
    if ($SHOW_COLUMNS['unit_price'])     $html .= '<th style="text-align:center; width:9%;">ราคาต่อตัว</th>';
    if ($SHOW_COLUMNS['qty'])            $html .= '<th style="text-align:center; width:6%;">จำนวน</th>';
    if ($SHOW_COLUMNS['wage'])           $html .= '<th style="text-align:center; width:19%;">จำนวนเงิน (บาท)</th>';
    $html .= '</tr></thead><tbody>';

    foreach ($pageJobs as $job) {
        $html .= '<tr>';
        // ลำดับรายการ
        $html .= '<td style="text-align:center; width:5%;">' . $rowNo . '</td>';
        if ($SHOW_COLUMNS['date_complete'])
            $html .= '<td style="text-align:center; width:9%">' . date('d-m-Y', strtotime($job['date_complete'])) . '</td>';
        if ($SHOW_COLUMNS['job_id'])
            $html .= '<td style="text-align:center; width:10%;">' . htmlspecialchars($job['job_id']) . '</td>';
        if ($SHOW_COLUMNS['job_type'])
            $html .= '<td style="text-align:center;">' . htmlspecialchars($job['job_type']) . '</td>';
        if ($SHOW_COLUMNS['prod_code'])
            $html .= '<td style="text-align:center;width:40%;">' . htmlspecialchars($job['prod_code']) . ' / ' . $job['prod_partno'] . '</td>';
        if ($SHOW_COLUMNS['prod_partno'])
            $html .= '<td>' . htmlspecialchars($job['prod_partno']) . '</td>';
        if ($SHOW_COLUMNS['assembly_point'])
            $html .= '<td style="text-align:center; width:10%;">' . htmlspecialchars($job['assembly_point']) . '</td>';
        if ($SHOW_COLUMNS['unit_price'])
            $html .= '<td style="text-align:center; width:9%;">' . number_format($job['unit_price'], 2) . '</td>';
        if ($SHOW_COLUMNS['qty'])
            $html .= '<td style="text-align:center; width:6%;">' . htmlspecialchars($job['qty']) . '</td>';
        if ($SHOW_COLUMNS['wage'])
            $html .= '<td style="text-align:center; width:19%;">' . number_format($job['wage'], 2) . '</td>';
        $html .= '</tr>';

        $pageTotal += $job['wage'];
        $pageQtyTotal += $job['qty'];    // <-- เก็บรวมจำนวนตรงนี้
        $rowNo++;
    }

    // รวมเงินหน้านี้
    if (count($chunks) > 1 && $SHOW_COLUMNS['wage']) {
        $colspan = count(array_filter($SHOW_COLUMNS));
        $html  .= '<tr>'
                .  '<td colspan="' . $colspan . '" style="text-align:right; font-size:13pt;"><b>'."รวมจำนวนชิ้นงานในหน้านี้ ". $pageQtyTotal ." ตัว".'</b></td>'
                .  '<td style="text-align:center;"><b>' . number_format($pageTotal, 2) . '</b></td>'
                .  '</tr>';
    }

    // รวมเงินทั้งสิ้น (เฉพาะหน้าสุดท้าย)
    if ($index === count($chunks) - 1 && $SHOW_COLUMNS['wage']) {
        $colspan = count(array_filter($SHOW_COLUMNS));
        $html  .= '<tr>'
                .  '<td colspan="' . $colspan . '" style="text-align:right; font-size:13pt; font-weight:bold;">'."รวมจำนวนชิ้นงานทั้งหมด ". $totalQtyAll . " ตัว".'</td>'
                .  '<td style="text-align:center; font-size:12pt; font-weight:bold;">' . number_format($totalWageAll, 2) . ' บาท</td>'
                .  '</tr>';
    }

    $html .= '</tbody></table>';
    $pdf->writeHTML($html, true, false, true, false, '');

    // --- แทรกโค้ดแสดงยอดจำนวนชิ้นงาน ต่อจากนี้ ---
    // $pdf->Ln(2); // เว้นบรรทัดนิดหน่อย
    // $pdf->Cell(0,0,'รวมจำนวนชิ้นงานในหน้านี้: ' . $pageQtyTotal,0,1,'R');

    // === สรุปยอดรวมทั้งสิ้น เฉพาะหน้าสุดท้าย ===

    // if ($index === count($chunks) - 1) {
    //     $pdf->Ln(4);
    //     $pdf->Cell(0,0,'รวมจำนวนชิ้นงานทั้งหมด: ' . $totalQtyAll,0,1,'R');
    // }

    // ลายเซ็น
    $pdf->SetY(240);
    $pdf->writeHTMLCell(
        0, 0, '', '', '
        <table style="width: 100%; text-align: center;">
            <tr>
                <td><div style="border-bottom: 1px solid #000; width: 50%; margin: auto;"></div>ผู้ออกเอกสาร<br>' 
                    . 'ณัฐชนากรณ์ แสงสุวรรณวริศ' . '<br>เจ้าหน้าที่</td>
                <td><div style="border-bottom: 1px solid #000; width: 50%; margin: auto;"></div>ผู้ตรวจสอบ<br>วนิชา บุญเทียน<br>เจ้าหน้าที่</td>
                <td><div style="border-bottom: 1px solid #000; width: 50%; margin: auto;"></div>ผู้อนุมัติ<br>ประวิทย์ ฮวดสุนทร<br>ผู้จัดการฝ่ายผลิต</td>
            </tr>
        </table>', 
        0, 1, false, true, 'C', true
    );
}

$pdf->Output('ใบเบิกเงินจุดประกอบ_' . $productionWageId . '.pdf', 'I');
