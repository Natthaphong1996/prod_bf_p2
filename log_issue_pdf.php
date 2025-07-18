<?php
// ภาษา: PHP
// ชื่อไฟล์: log_issue_pdf.php
// หน้าที่: สร้างเอกสาร PDF จาก log_id โดยยึดรูปแบบโค้ดตามที่ผู้ใช้ต้องการ

session_start();
require_once __DIR__ . '/config_db.php';
require_once('tcpdf/tcpdf.php');

// 1. รับ log_id จาก URL และตรวจสอบ
if (!isset($_GET['log_id']) || empty($_GET['log_id'])) {
    die("ไม่พบหมายเลขการเบิก (Log ID)");
}
$log_id = (int)$_GET['log_id'];

// --- [แก้ไข] ส่วนดึงข้อมูลให้รองรับโครงสร้างใหม่ (JSON) ---

// 2.1 ดึงข้อมูลหลักของใบเบิก และ JSON string
$sql_log = "
    SELECT
        lih.log_id,
        lih.log_details,
        lih.issued_at,
        lih.earliest_needed_date,
        pu.thainame AS issuer_name
    FROM
        log_issue_history AS lih
    LEFT JOIN
        prod_user AS pu ON lih.issued_by_user_id = pu.user_id
    WHERE
        lih.log_id = ?;
";
$stmt_log = $conn->prepare($sql_log);
$stmt_log->bind_param("i", $log_id);
$stmt_log->execute();
$result_log = $stmt_log->get_result();
$log_data = $result_log->fetch_assoc();
$stmt_log->close();

if (!$log_data) {
    die("ไม่พบข้อมูลการเบิกสำหรับ ID: {$log_id}");
}

// 2.2 แปลง JSON เป็น PHP Array และดึง rm_id ทั้งหมด
$items = json_decode($log_data['log_details'], true);
if (!is_array($items) || empty($items)) {
    die("ไม่พบรายการในใบเบิก หรือข้อมูลเสียหาย");
}
$rm_ids = array_column($items, 'rm_id');

// 2.3 สร้าง Lookup Map สำหรับข้อมูลไม้ (เพื่อประสิทธิภาพ)
$rm_lookup = [];
if (!empty($rm_ids)) {
    $placeholders = implode(',', array_fill(0, count($rm_ids), '?'));
    $types = str_repeat('i', count($rm_ids));
    $sql_rm = "SELECT rm_id, rm_code, rm_thickness, rm_width, rm_length, rm_type FROM rm_wood_list WHERE rm_id IN ($placeholders)";
    $stmt_rm = $conn->prepare($sql_rm);
    $stmt_rm->bind_param($types, ...$rm_ids);
    $stmt_rm->execute();
    $result_rm = $stmt_rm->get_result();
    while ($row_rm = $result_rm->fetch_assoc()) {
        $rm_lookup[$row_rm['rm_id']] = $row_rm;
    }
    $stmt_rm->close();
}
$conn->close();


// 3. เริ่มสร้างเอกสาร PDF (ตามรูปแบบเดิม)
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, 'A4', true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Your Company Name');
$pdf->SetTitle("ใบเบิกไม้ท่อน - เลขที่ : {$log_id}");
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(TRUE, 10);
$pdf->SetFont('thsarabunnew', '', 14);
$pdf->AddPage();

// 4. สร้างเนื้อหา HTML ทั้งหมดสำหรับ PDF

// เตรียมข้อมูลส่วน Header
$issuer_name_display = htmlspecialchars($log_data['issuer_name'] ?? 'N/A');
$issued_at_display = htmlspecialchars($log_data['issued_at']);
$earliest_needed_date_display = htmlspecialchars($log_data['earliest_needed_date']);
$logo_path = __DIR__ . '/logo/SK-Logo.png';
$logo_img = @file_get_contents($logo_path) ? '<img src="' . $logo_path . '" width="50">' : 'LOGO';

// --- [แก้ไข] สร้างตารางรายการโดยการวนลูป ---
$table_rows_html = '';
foreach ($items as $item) {
    $rm_id = $item['rm_id'];
    $rm_info = $rm_lookup[$rm_id] ?? null;

    if ($rm_info) {
        $rm_code = htmlspecialchars($rm_info['rm_code']);
        $rm_size_display = "{$rm_info['rm_thickness']}x{$rm_info['rm_width']}x{$rm_info['rm_length']}";
        $rm_type_display = ($rm_info['rm_type'] == 'K') ? 'อบ' : 'ไม่อบ';
    } else {
        $rm_code = "N/A";
        $rm_size_display = "ไม่พบข้อมูล";
        $rm_type_display = "N/A";
    }
    
    $issued_qty = number_format($item['issued_qty']);

    $table_rows_html .= '
        <tr>
            <td style="width: 25%;">' . $rm_code . '</td>
            <td style="width: 40%;" class="text-left">ไม้ท่อน ขนาด ' . $rm_size_display . '</td>
            <td style="width: 15%;">' . $rm_type_display . '</td>
            <td style="width: 20%;">' . $issued_qty . '</td>
        </tr>';
}


// นำทุกส่วนมารวมกันในตัวแปร $html เดียว
$html = <<<EOD
<style>
    .header-table { width: 100%; border-collapse: collapse; font-size: 12pt; margin-bottom: 10px; }
    .header-table td { vertical-align: top; padding: 2px; }
    .header-left { width: 60%; font-size: 13pt;}
    .header-right { width: 40%; text-align: center; }
    .detail-table { width: 100%; border-collapse: collapse; font-size: 11pt; }
    .detail-table th, .detail-table td { border: 1px solid #999; padding: 6px; text-align: center; }
    .detail-table th { background-color: #f2f2f2; font-weight: bold; }
    .text-left { text-align: left; }
</style>

<table class="header-table">
    <tr>
        <td class="header-left">
            เลขที่การเบิก : {$log_id}<br>
            ผู้เบิก : {$issuer_name_display}<br>
            วันที่เบิก : {$issued_at_display}<br>
            วันที่ต้องการไม้ : {$earliest_needed_date_display}<br>
        </td>
        <td class="header-right">
            {$logo_img}
            <h2 style="font-size: 14px;">บริษัท สยามเคียววะ เซซาคูโช จำกัด</h2>
            <h3 style="font-size: 13px;">ใบเบิกไม้ท่อน</h3>
        </td>
    </tr>
</table>

<br><br><br><br>

<table class="detail-table">
    <thead>
        <tr>
            <th style="width: 25%;">รหัสวัตถุดิบ</th>
            <th style="width: 40%;">รายการ</th>
            <th style="width: 15%;">ประเภท</th>
            <th style="width: 20%;">จำนวนที่เบิก</th>
        </tr>
    </thead>
    <tbody>
        {$table_rows_html}
    </tbody>
</table>

EOD;

// 5. เขียน HTML ลงใน PDF และแสดงผล
$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output("ใบเบิกไม้ท่อน-{$log_id}.pdf", 'I');
?>
