<?php
/**
 * forecast_functions.php
 * ฟังก์ชันที่เกี่ยวข้องกับการประมวลผล Forecast
 */

/**
 * ดึงข้อมูลสินค้า (prod_id, prod_code, customer_id) จากตาราง prod_list โดยค้นหาจาก prod_partno หรือ prod_code
 *
 * @param mysqli $conn การเชื่อมต่อฐานข้อมูล
 * @param string $prod_partno รหัสสินค้า (หรือ prod_partno)
 * @return array|null คืนค่าเป็นอาเรย์ associative หากพบข้อมูล ไม่เช่นนั้นคืนค่า null
 */
function getProductDetails($conn, $prod_partno) {
    // ลองค้นหาจาก prod_partno
    $sql = "SELECT prod_id, prod_code, customer_id FROM prod_list WHERE prod_partno = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $prod_partno);
        $stmt->execute();
        $stmt->bind_result($prod_id, $prod_code, $customer_id);
        if($stmt->fetch()) {
            $stmt->close();
            return array('prod_id' => $prod_id, 'prod_code' => $prod_code, 'customer_id' => $customer_id);
        }
        $stmt->close();
    }
    // หากไม่พบ ให้ลองค้นหาจาก prod_code
    $sql2 = "SELECT prod_id, customer_id FROM prod_list WHERE prod_code = ?";
    if ($stmt2 = $conn->prepare($sql2)) {
        $stmt2->bind_param("s", $prod_partno);
        $stmt2->execute();
        $stmt2->bind_result($prod_id, $customer_id);
        if($stmt2->fetch()) {
            $stmt2->close();
            return array('prod_id' => $prod_id, 'prod_code' => $prod_partno, 'customer_id' => $customer_id);
        }
        $stmt2->close();
    }
    return null;
}

/**
 * ประมวลผลข้อมูล Forecast โดยแปลงวันที่และทำการ INSERT หรือ UPDATE ข้อมูลในตาราง forecast
 *
 * @param mysqli $conn การเชื่อมต่อฐานข้อมูล
 * @param int $prod_id รหัสสินค้า (primary key)
 * @param string $forecast_date_raw วันที่ในรูปแบบ dd/mm/yy
 * @param int $forecast_quantity จำนวนที่คาดการณ์
 * @param string $customer_code รหัสลูกค้า
 * @return array คืนค่าด้วย array ที่มี key 'success' หากสำเร็จ หรือ key 'error' หากเกิดข้อผิดพลาด
 */
function processForecast($conn, $prod_id, $forecast_date_raw, $forecast_quantity, $customer_code) {
    // แปลงวันที่จาก dd/m/yy หรือ dd/mm/yyyy เป็น yyyy-mm-dd
    $date_parts = explode('/', $forecast_date_raw);
    if (count($date_parts) == 3) {
        $day   = (int)$date_parts[0];
        $month = (int)$date_parts[1];
        $year  = (int)$date_parts[2];

        // หากปีเป็น 2 หลัก ให้เพิ่มเป็นปี 2000+
        if (strlen($date_parts[2]) == 2) {
            $year += 2000;
        }

        // ตรวจสอบความถูกต้องของวันที่
        if (!checkdate($month, $day, $year)) {
            return array('error' => "รูปแบบวันที่ไม่ถูกต้อง");
        }

        // ประกอบเป็นรูปแบบ yyyy-mm-dd
        $forecast_date = sprintf("%04d-%02d-%02d", $year, $month, $day);
    } else {
        return array('error' => "รูปแบบวันที่ไม่ถูกต้อง");
    }
    
    // ตรวจสอบว่ามีข้อมูล forecast ในเดือนเดียวกันอยู่หรือไม่
    $month_year = substr($forecast_date, 0, 7); // yyyy-mm
    $month_year_like = $month_year . '%';

    $check_sql = "SELECT forecast_id FROM forecast WHERE prod_id = ? AND forecast_date LIKE ?";
    if ($stmt = $conn->prepare($check_sql)) {
        $stmt->bind_param("is", $prod_id, $month_year_like);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            // มีข้อมูลอยู่แล้ว ให้ทำการ UPDATE
            $update_sql = "UPDATE forecast SET forecast_quantity = ? WHERE prod_id = ? AND forecast_date LIKE ?";
            if ($update_stmt = $conn->prepare($update_sql)) {
                $update_stmt->bind_param("iis", $forecast_quantity, $prod_id, $month_year_like);
                $update_stmt->execute();
                $update_stmt->close();
            }
        } else {
            // ไม่มีข้อมูลในเดือนนั้น ให้ทำการ INSERT
            $insert_sql = "INSERT INTO forecast (customer_code, prod_id, forecast_quantity, forecast_date) VALUES (?, ?, ?, ?)";
            if ($insert_stmt = $conn->prepare($insert_sql)) {
                $insert_stmt->bind_param("siis", $customer_code, $prod_id, $forecast_quantity, $forecast_date);
                $insert_stmt->execute();
                $insert_stmt->close();
            }
        }
        $stmt->close();
    }
    return array('success' => true);
}

/**
 * ประมวลผลไฟล์ CSV สำหรับข้อมูล Forecast
 *
 * @param mysqli $conn การเชื่อมต่อฐานข้อมูล
 * @param string $csvFilePath ที่อยู่ของไฟล์ CSV
 * @return array คืนค่าเป็นอาเรย์ของข้อมูลข้อผิดพลาดในแต่ละแถว (ถ้ามี)
 */
function processForecastCsv($conn, $csvFilePath) {
    $errorData = [];
    $rowNumber = 1;

    if (($csvFile = fopen($csvFilePath, 'r')) !== FALSE) {
        // ถ้าแถวแรกเป็นหัวตาราง (Header) ให้ข้าม
        fgetcsv($csvFile); // ลบหรือคอมเมนต์บรรทัดนี้ออก ถ้าคุณไม่มีหัวตาราง

        while (($row = fgetcsv($csvFile)) !== FALSE) {
            $rowNumber++;

            // ตรวจสอบว่ามีอย่างน้อย 7 คอลัมน์ (A-G) หรือไม่
            if (count($row) < 7) {
                $errorData[] = [
                    "row" => $rowNumber,
                    "reason" => "จำนวนคอลัมน์ไม่ครบ (ต้องมีอย่างน้อย 7 คอลัมน์)",
                    "prod_partno" => ""
                ];
                continue;
            }

            // สมมติว่า:
            // [0] => PART NO.
            // [1] => Q2-1
            // [2] => Q2-1-DATE
            // [3] => Q2-2
            // [4] => Q2-2-DATE
            // [5] => Q2-3
            // [6] => Q2-3-DATE

            $part_no        = trim($row[0]);
            $q2_1_quantity  = trim($row[1]);
            $q2_1_date_raw  = trim($row[2]);
            $q2_2_quantity  = trim($row[3]);
            $q2_2_date_raw  = trim($row[4]);
            $q2_3_quantity  = trim($row[5]);
            $q2_3_date_raw  = trim($row[6]);

            // ถ้า part_no ว่างหรือไม่มีข้อมูลเลย อาจจะเป็น error
            if (empty($part_no)) {
                $errorData[] = [
                    "row" => $rowNumber,
                    "reason" => "PART NO. ว่าง",
                    "prod_partno" => ""
                ];
                continue;
            }

            // ค้นหา product จาก part_no
            $product = getProductDetails($conn, $part_no);
            if (!$product) {
                // ไม่พบสินค้าหรือ BOM
                $errorData[] = [
                    "row" => $rowNumber,
                    "reason" => "ไม่พบข้อมูลสินค้า/ไม่มี BOM",
                    "prod_partno" => $part_no
                ];
                continue;
            }

            // สำหรับแต่ละ Q2-x ถ้ามีค่า (จำนวน + วันที่) จึงค่อยบันทึก
            // Q2-1
            if (!empty($q2_1_quantity) && !empty($q2_1_date_raw)) {
                if ((float)$q2_1_quantity < 1) {
                    $errorData[] = [
                        "row" => $rowNumber,
                        "reason" => "จำนวน forecast น้อยกว่า 1",
                        "prod_partno" => $part_no
                    ];
                } else {
                    $resultProcess = processForecast(
                        $conn,
                        $product['prod_id'],
                        $q2_1_date_raw,
                        $q2_1_quantity,
                        $product['customer_id']
                    );
                    if (isset($resultProcess['error'])) {
                        $errorData[] = [
                            "row" => $rowNumber,
                            "reason" => $resultProcess['error'],
                            "prod_partno" => $part_no
                        ];
                    }
                }
            }

            // Q2-2
            if (!empty($q2_2_quantity) && !empty($q2_2_date_raw)) {
                if ((float)$q2_2_quantity < 1) {
                    $errorData[] = [
                        "row" => $rowNumber,
                        "reason" => "จำนวน forecast น้อยกว่า 1",
                        "prod_partno" => $part_no
                    ];
                } else {
                    $resultProcess = processForecast(
                        $conn,
                        $product['prod_id'],
                        $q2_2_date_raw,
                        $q2_2_quantity,
                        $product['customer_id']
                    );
                    if (isset($resultProcess['error'])) {
                        $errorData[] = [
                            "row" => $rowNumber,
                            "reason" => $resultProcess['error'],
                            "prod_partno" => $part_no
                        ];
                    }
                }
            }

            // Q2-3
            if (!empty($q2_3_quantity) && !empty($q2_3_date_raw)) {
                if ((float)$q2_3_quantity < 1) {
                    $errorData[] = [
                        "row" => $rowNumber,
                        "reason" => "จำนวน forecast น้อยกว่า 1",
                        "prod_partno" => $part_no
                    ];
                } else {
                    $resultProcess = processForecast(
                        $conn,
                        $product['prod_id'],
                        $q2_3_date_raw,
                        $q2_3_quantity,
                        $product['customer_id']
                    );
                    if (isset($resultProcess['error'])) {
                        $errorData[] = [
                            "row" => $rowNumber,
                            "reason" => $resultProcess['error'],
                            "prod_partno" => $part_no
                        ];
                    }
                }
            }
        }
        fclose($csvFile);
    } else {
        $errorData[] = [
            "row" => 1,
            "reason" => "ไม่สามารถเปิดไฟล์ CSV",
            "prod_partno" => ""
        ];
    }

    return $errorData;
}

/**
 * สร้างไฟล์ CSV สำหรับรายงานข้อผิดพลาดแล้วให้ดาวน์โหลด
 *
 * @param array $errorData อาเรย์ของข้อมูลข้อผิดพลาด
 */
function outputErrorCsv($errorData) {
    $errorFileName = "error_report.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $errorFileName);
    $output = fopen('php://output', 'w');

    // เพิ่ม BOM เพื่อรองรับภาษาไทย
    fprintf($output, chr(239) . chr(187) . chr(191));

    // เขียนหัวตาราง
    fputcsv($output, ['แถวที่', 'สาเหตุ', 'prod_partno']);

    // เขียนข้อมูลข้อผิดพลาดแต่ละแถว
    foreach ($errorData as $errorRow) {
        fputcsv($output, [$errorRow['row'], $errorRow['reason'], $errorRow['prod_partno']]);
    }
    fclose($output);
    exit();
}
?>
