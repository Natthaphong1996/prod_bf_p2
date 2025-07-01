<?php
include 'config_db.php';

if (isset($_GET['job_id'])) {
    $job_id = $_GET['job_id'];

    // ดึงข้อมูล product_code จาก wood_issue โดยใช้ job_id
    $query = "SELECT product_code FROM wood_issue WHERE job_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $job_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $product_code = $row['product_code'];

        // ดึงข้อมูล parts จาก bom โดยใช้ product_code
        $query = "SELECT JSON_EXTRACT(parts, '$[*].part_id') AS part_ids FROM bom WHERE prod_code = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $product_code);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $part_ids = json_decode($row['part_ids'], true); // แปลง JSON เป็น Array

            if (!empty($part_ids)) {
                // ดึงข้อมูลจาก part_list โดยใช้ part_id ที่ได้จาก bom
                $placeholders = implode(',', array_fill(0, count($part_ids), '?')); // เตรียม Placeholder สำหรับ IN Clause
                $query = "SELECT part_code, part_type, part_thickness, part_width, part_length 
                          FROM part_list WHERE part_id IN ($placeholders)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param(str_repeat('i', count($part_ids)), ...$part_ids);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    while ($part = $result->fetch_assoc()) {
                        echo "<tr>
                                <td>{$part['part_code']}</td>
                                <td>{$part['part_type']} ({$part['part_thickness']}x{$part['part_width']}x{$part['part_length']})</td>
                                <td>
                                    <input type='number' class='form-control part-quantity' name='parts[{$part['part_code']}][quantity]' min='0'>
                                </td>
                                <td>
                                    <select class='form-select reason-dropdown d-none' name='parts[{$part['part_code']}][reason]'>
                                        <option value='เชื้อรา'>เชื้อรา</option>
                                        <option value='ตาไม้'>ตาไม้</option>
                                        <option value='ไม่ได้ขนาด'>ไม่ได้ขนาด</option>
                                        <option value='โก่งงอ'>โก่งงอ</option>
                                        <option value='แตกแล้ว'>แตกแล้ว</option>
                                    </select>
                                </td>
                            </tr>";
                    }
                } else {
                    echo "<tr><td colspan='4' class='text-center'>ไม่พบข้อมูล Part</td></tr>";
                }
            } else {
                echo "<tr><td colspan='4' class='text-center'>ไม่มี Part ที่เกี่ยวข้องใน BOM</td></tr>";
            }
        } else {
            echo "<tr><td colspan='4' class='text-center'>ไม่พบข้อมูลใน BOM สำหรับ Product Code นี้</td></tr>";
        }
    } else {
        echo "<tr><td colspan='4' class='text-center'>ไม่พบข้อมูล JOB นี้ใน wood_issue</td></tr>";
    }
} else {
    echo "<tr><td colspan='4' class='text-center'>กรุณาระบุ JOB ID</td></tr>";
}
?>
