<?php
// create_cutting_job_pdf.php
session_start();

// 1. --- การตั้งค่าและเรียกใช้งานส่วนประกอบที่จำเป็น ---
require_once __DIR__ . '/config_db.php';
require_once('tcpdf/tcpdf.php');

// 2. --- รับ cutting_job_id จาก URL ---
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ไม่พบหมายเลขใบงาน (Job ID)");
}
$cutting_job_id = (int)$_GET['id'];

// 3. --- ดึงข้อมูลทั้งหมดจากฐานข้อมูลโดยใช้ cutting_job_id ---
try {
    // ดึงข้อมูลหลักจาก cutting_job และ join กับ cutting_batch
    $sql_job = "
        SELECT 
            cj.job_code, cj.part_type, cj.tu_details,
            cb.recipe_id, cb.total_wood_logs, cb.rm_needed_date, cb.wip_receive_date 
        FROM cutting_job cj
        JOIN cutting_batch cb ON cj.batch_id = cb.batch_id
        WHERE cj.cutting_job_id = ?
    ";
    $stmt_job = $conn->prepare($sql_job);
    $stmt_job->bind_param("i", $cutting_job_id);
    $stmt_job->execute();
    $job_data = $stmt_job->get_result()->fetch_assoc();
    $stmt_job->close();

    if (!$job_data) {
        throw new Exception("ไม่พบข้อมูลใบงาน ID: {$cutting_job_id}");
    }

    // ดึงข้อมูล Recipe ที่เกี่ยวข้อง
    $recipe_id = $job_data['recipe_id'];
    $sql_pdf_details = "
        SELECT 
            r.*, 
            c_main.customer_name as main_customer_name,
            p_main.part_code as main_part_code, p_main.part_length as main_part_length, p_main.part_thickness as main_part_thickness, p_main.part_width as main_part_width, p_main.part_type as main_part_type,
            rm.rm_code as main_rm_code, rm.rm_thickness as main_rm_thickness, rm.rm_width as main_rm_width, rm.rm_length as main_rm_length, rm.rm_type as main_rm_type,
            c_head.customer_name as head_customer_name, 
            p_head.part_code as head_part_code, p_head.part_length as head_part_length, p_head.part_thickness as head_part_thickness, p_head.part_width as head_part_width, p_head.part_type as head_part_type,
            c_scrap.customer_name as scrap_customer_name, 
            p_scrap.part_code as scrap_part_code, p_scrap.part_length as scrap_part_length, p_scrap.part_thickness as scrap_part_thickness, p_scrap.part_width as scrap_part_width, p_scrap.part_type as scrap_part_type
        FROM recipe_list r
        LEFT JOIN customer c_main ON r.main_customer_id = c_main.customer_id
        LEFT JOIN part_list p_main ON r.main_output_part_id = p_main.part_id
        LEFT JOIN rm_wood_list rm ON r.main_input_rm_id = rm.rm_id
        LEFT JOIN customer c_head ON r.head_customer_id = c_head.customer_id
        LEFT JOIN part_list p_head ON r.head_output_part_id = p_head.part_id
        LEFT JOIN customer c_scrap ON r.scrap_customer_id = c_scrap.customer_id
        LEFT JOIN part_list p_scrap ON r.scrap_output_part_id = p_scrap.part_id
        WHERE r.recipe_id = ?
    ";
    $stmt_pdf = $conn->prepare($sql_pdf_details);
    $stmt_pdf->bind_param("i", $recipe_id);
    $stmt_pdf->execute();
    $details = $stmt_pdf->get_result()->fetch_assoc();
    $stmt_pdf->close();

    if (!$details) {
        throw new Exception("ไม่พบข้อมูล Recipe ที่เชื่อมโยงกับใบงาน");
    }

    // แปลง JSON ของ tu_details กลับมาเป็น Array
    $tu_details_array_for_job = json_decode($job_data['tu_details'], true);
    
    // --- เริ่มสร้าง PDF ---
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, 'A4', true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Your Company Name');
    $pdf->SetTitle("Job Order - {$job_data['job_code']}");
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(TRUE, 10);
    $pdf->SetFont('thsarabunnew', '', 14);
    
    foreach ($tu_details_array_for_job as $index => $quantity) {
        if (empty($quantity) || $quantity <= 0) continue; 

        $tu_number = $index + 1;
        $pdf->AddPage();

        $part_key = $job_data['part_type'];
        $total_tu_count = count($tu_details_array_for_job);

        $customer_name = $details[$part_key.'_customer_name'];
        $footer_text = '';
        $input_size_label = '';
        $input_size_display = '';
        $input_qty_label = '';
        $input_qty_value = 0;
        $part_length = $details[$part_key.'_part_length'];
        $cutting_ops = $details[$part_key.'_cutting_operations'];
        $split_ops = $details[$part_key.'_split_operations'];
        $output_part_size_display = "{$details[$part_key.'_part_thickness']}x{$details[$part_key.'_part_width']}x{$details[$part_key.'_part_length']} ({$details[$part_key.'_part_type']})";
        $remaining_length_html = '';
        $cut_yield_qty = 0;

        if ($part_key === 'main') {
            $footer_text = 'งานหลัก';
            $input_size_label = 'ขนาดไม้ท่อน :';
            $rm_type_display = ($details['main_rm_type'] == 'K') ? 'อบ' : 'ไม่อบ';
            $input_size_display = "{$details['main_rm_thickness']}x{$details['main_rm_width']}x{$details['main_rm_length']} ({$rm_type_display})";
            $input_qty_label = 'จำนวนไม้ท่อนที่ใช้ :';
            $input_qty_value = $job_data['total_wood_logs'];
            $remaining_length = ($details['main_rm_length'] ?? 0) - (($details['main_part_length'] ?? 0) * $cutting_ops);
            $cut_yield_qty = ($cutting_ops ?? 0) * $input_qty_value;
            $remaining_length_html = '<tr><td class="data-label">ขนาดเศษที่เหลือจากการตัด :</td><td>' . $remaining_length . ' mm</td><td class="data-label">ได้จำนวน :</td><td>' . $cut_yield_qty . '</td></tr>';
        } else {
            $footer_text = ($part_key === 'head') ? 'งานหัวไม้' : 'งานเศษไม้';
            $input_size_label = 'ขนาดไม้ input :';
            $input_size_display = "{$details[$part_key.'_input_thickness']}x{$details[$part_key.'_input_width']}x{$details[$part_key.'_input_length']}";
            $input_qty_label = 'จำนวน Input ที่ใช้ :';
            $input_qty_value = ($details[$part_key.'_input_qty'] ?? 0) * $job_data['total_wood_logs'];
            $cut_yield_qty = ($cutting_ops ?? 0) * $input_qty_value;
            $remaining_length_html = '<tr><td class="data-label" colspan="3">ได้จำนวน :</td><td>' . $cut_yield_qty . '</td></tr>';
        }
        
        $total_split_yield_qty = $cutting_ops * $split_ops * $input_qty_value;
        $logo_path = __DIR__ . '/logo/SK-Logo.png';
        $logo_img = @file_get_contents($logo_path) ? '<img src="' . $logo_path . '" width="80">' : 'LOGO';

        // [แก้ไข] เพิ่มจำนวนชิ้นใน TU เข้าไปในส่วนหัว
        $html = <<<EOD
        <style>
            .header-table { width: 100%; border-collapse: collapse; font-size: 16pt; margin-bottom: 20px; }
            .header-table td { vertical-align: top; padding: 2px; }
            .header-left { width: 60%; }
            .header-right { width: 40%; text-align: center; }
            .unified-table { width: 100%; border-collapse: collapse; font-size: 16pt; border: 2px solid black; }
            .unified-table td, .unified-table th { border: 1px solid #999; padding: 5px; vertical-align: middle; }
            .section-header { text-align: center; font-size: 20px; font-weight: bold; background-color: #E0E0E0; }
            .data-label { font-weight: bold; }
            .ng-header { text-align: center; font-weight: bold; }
            .ng-cell { height: 30px; }
            .footer-text { text-align: center; font-size: 26px; font-weight: bold; color: red; margin-top: 20px; }
        </style>
        <table class="header-table">
            <tr>
                <td class="header-left">
                    หมายเลข JOB : {$job_data['job_code']}<br>
                    ลูกค้า : {$customer_name}<br>
                    วันที่ต้องการรับไม้ในการตัดผ่า : {$job_data['rm_needed_date']}<br>
                    วันที่ต้องส่งงาน : {$job_data['wip_receive_date']}<br>
                    TU ที่ : {$tu_number} / {$total_tu_count} (จำนวน: {$quantity} ชิ้น)
                </td>
                <td class="header-right">
                    {$logo_img}
                    <h2>บริษัท สยามเคียววะ เซซาคูโช จำกัด</h2>
                    <h3>ใบสั่งงานตัด</h3>
                </td>
            </tr>
        </table>

        <br><br><br><br>

        <table class="unified-table">
            <tr><th class="section-header" colspan="4">ใบสั่งตัด</th></tr>
            <tr>
                <td class="data-label" style="width: 25%;">{$input_size_label}</td>
                <td style="width: 35%;">{$input_size_display}</td>
                <td class="data-label" style="width: 25%;">{$input_qty_label}</td>
                <td style="width: 15%;">{$input_qty_value}</td>
            </tr>
            <tr>
                <td class="data-label">ขนาดการตัด :</td>
                <td>{$part_length} mm</td>
                <td class="data-label">ตัดได้ :</td>
                <td>{$cutting_ops}</td>
            </tr>
            {$remaining_length_html}
            <tr><th class="section-header" colspan="4">ใบสั่งผ่า</th></tr>
            <tr>
                <td class="data-label">ขนาดไม้ที่ต้องการ :</td>
                <td>{$output_part_size_display}</td>
                <td class="data-label">ผ่าได้ :</td>
                <td>{$split_ops}</td>
            </tr>
            <tr>
                <td class="data-label">จำนวนที่ผ่าได้ทั้งหมด :</td>
                <td colspan="3">{$total_split_yield_qty}</td>
            </tr>
            <tr>
                <td class="ng-header" rowspan="2" style="width:15%; vertical-align: middle;">NG</td>
                <td class="ng-header" style="width:17%;">ตาไม้</td>
                <td class="ng-header" style="width:17%;">ไม่ได้ขนาด</td>
                <td class="ng-header" style="width:17%;">แตก</td>
                <td class="ng-header" style="width:17%;">รวม NG</td>
                <td class="ng-header" style="width:17%;">จำนวนที่นับได้</td>
            </tr>
            <tr>
                <td class="ng-cell"></td><td class="ng-cell"></td><td class="ng-cell"></td><td class="ng-cell"></td><td class="ng-cell"></td>
            </tr>
        </table>
        <div class="footer-text">{$footer_text}</div>
EOD;
        
        $pdf->writeHTML($html, true, false, true, false, '');
    }

    if ($pdf->getNumPages() > 0) {
        $pdf->Output("job_{$job_data['job_code']}_all.pdf", 'I');
    } else {
        echo "ไม่มีข้อมูล TU ที่ถูกต้องสำหรับสร้าง PDF";
    }

} catch (Exception $e) {
    die("เกิดข้อผิดพลาด: " . $e->getMessage());
}

$conn->close();
exit();
?>
