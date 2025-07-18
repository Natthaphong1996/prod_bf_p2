<?php
// cutting_save_completed_job.php
// ภาษา: PHP
// หน้าที่: บันทึกข้อมูลงานที่เสร็จสิ้นลงในตาราง cutting_jobs_complete
// อัปเดตสถานะ cutting_job เป็น 'เสร็จสิ้น' หากจำนวนที่รับเข้าสะสมเท่ากับหรือมากกว่าจำนวนที่ผลิต
// หรือเมื่อผู้ใช้เลือก 'บังคับปิดงาน' และอัปเดตจำนวนใน wip_inventory
// [เพิ่ม] ตรวจสอบ WIP Inventory และแสดง Modal รับค่า Min/Max หาก Part ID ยังไม่เคยมีในระบบ

session_start();
require_once __DIR__ . '/config_db.php'; // เชื่อมต่อฐานข้อมูล

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบหรือไม่
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "กรุณาเข้าสู่ระบบเพื่อดำเนินการ";
    header("Location: index.php");
    exit();
}

// ตรวจสอบว่าเป็น request แบบ POST และมีข้อมูลที่จำเป็นครบถ้วน
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // รับค่าจากฟอร์ม
    $cutting_job_id = $_POST['cutting_job_id'] ?? '';
    $completed_quantity_this_time = $_POST['completed_quantity'] ?? ''; // จำนวนที่รับเข้าครั้งนี้
    $quantity_to_produce = $_POST['quantity_to_produce_hidden'] ?? ''; // จำนวนที่คาดว่าจะผลิต
    $current_completed_from_modal = $_POST['current_completed_hidden'] ?? ''; // จำนวนที่รับเข้าแล้ว (จาก Modal)
    $assembly_point = $_POST['assembly_point'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $record_by_user_id = $_POST['record_by_user_id'] ?? $_SESSION['user_id'];
    $record_by_username = $_POST['record_by_username'] ?? $_SESSION['username'];
    $force_complete = isset($_POST['force_complete']) ? true : false; // รับค่า checkbox บังคับปิดงาน
    $output_part_id = $_POST['output_part_id_hidden'] ?? null; // [เพิ่ม] รับ output_part_id
    $part_code_for_modal = $_POST['part_code_hidden'] ?? 'N/A'; // [เพิ่ม] รับ part_code สำหรับส่งไป Modal

    // ตรวจสอบความถูกต้องของข้อมูล (Input Validation)
    if (empty($cutting_job_id) || !is_numeric($completed_quantity_this_time) || $completed_quantity_this_time < 0 || !is_numeric($quantity_to_produce) || is_null($output_part_id) || empty($output_part_id)) {
        $_SESSION['error_message'] = "ข้อมูลไม่ถูกต้องหรือไม่ครบถ้วน (Part ID ไม่ถูกต้อง)";
        header("Location: cutting_job_list.php");
        exit();
    }

    // แปลงค่าให้เป็นประเภทที่ถูกต้องและป้องกัน SQL Injection
    $cutting_job_id = (int)$cutting_job_id;
    $completed_quantity_this_time = (int)$completed_quantity_this_time;
    $quantity_to_produce = (int)$quantity_to_produce;
    $current_completed_from_modal = (int)$current_completed_from_modal;
    $assembly_point = $conn->real_escape_string($assembly_point);
    $reason = $conn->real_escape_string($reason);
    $record_by_user_id_db = $conn->real_escape_string($record_by_user_id);
    $record_by_username_db = $conn->real_escape_string($record_by_username);
    $output_part_id_db = $conn->real_escape_string($output_part_id);


    // ดึง job_code, สถานะปัจจุบันของงาน และตรวจสอบสถานะ
    $sql_get_job_info = "SELECT job_code, job_status FROM cutting_job WHERE cutting_job_id = ?";
    $stmt_get_job_info = $conn->prepare($sql_get_job_info);
    $stmt_get_job_info->bind_param("i", $cutting_job_id);
    $stmt_get_job_info->execute();
    $result_get_job_info = $stmt_get_job_info->get_result();
    $job_info_row = $result_get_job_info->fetch_assoc();
    $job_id_for_cutting_jobs_complete = $job_info_row['job_code'] ?? null;
    $current_job_status = $job_info_row['job_status'] ?? null;
    $stmt_get_job_info->close();

    if (is_null($job_id_for_cutting_jobs_complete)) {
        $_SESSION['error_message'] = "ไม่พบ Job Code สำหรับ Cutting Job ID ที่ระบุ";
        header("Location: cutting_job_list.php");
        exit();
    }

    // ตรวจสอบไม่ให้บันทึกถ้าสถานะเป็น 'เสร็จสิ้น' หรือ 'ยกเลิก' แล้ว และไม่ใช่การบังคับปิดงาน
    if ($current_job_status == 'เสร็จสิ้น' && !$force_complete) {
        $_SESSION['error_message'] = "งานนี้เสร็จสิ้นแล้ว ไม่สามารถบันทึกเพิ่มได้";
        header("Location: cutting_job_list.php");
        exit();
    }
    if ($current_job_status == 'ยกเลิก') {
        $_SESSION['error_message'] = "งานนี้ถูกยกเลิกแล้ว ไม่สามารถบันทึกได้";
        header("Location: cutting_job_list.php");
        exit();
    }

    // คำนวณจำนวนที่รับเข้าสะสมใหม่ (รวมกับครั้งนี้)
    $potential_total_completed_quantity = $current_completed_from_modal + $completed_quantity_this_time;

    // ตรวจสอบไม่ให้กรอกจำนวนเกิน "จำนวนที่ผลิต" เว้นแต่จะเลือกบังคับปิด
    if ($potential_total_completed_quantity > $quantity_to_produce && !$force_complete) {
        $_SESSION['error_message'] = "จำนวนที่รับเข้าครั้งนี้ ทำให้จำนวนสะสมเกินจำนวนที่ผลิต กรุณาตรวจสอบ หรือเลือก 'บังคับปิดงาน' หากต้องการปิดงานนี้";
        header("Location: cutting_job_list.php");
        exit();
    }
    
    // เริ่มต้น Transaction เพื่อให้การ INSERT และ UPDATE เป็น Atomic Operation
    $conn->begin_transaction();
    $success = true;
    $status_updated_message = "";

    // บันทึกข้อมูลงานที่รับเข้าครั้งนี้ลงในตาราง cutting_jobs_complete
    // บันทึกถ้ามีจำนวน > 0 หรือ บังคับปิดงาน
    if ($completed_quantity_this_time > 0 || $force_complete) {
        $sql_insert_complete = "
            INSERT INTO cutting_jobs_complete (
                job_id, 
                prod_complete_qty, 
                receive_by, 
                record_by, 
                assembly_point, 
                reason, 
                date_complete
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())";

        $stmt_insert = $conn->prepare($sql_insert_complete);

        if ($stmt_insert) {
            $stmt_insert->bind_param(
                "sissss", 
                $job_id_for_cutting_jobs_complete, 
                $completed_quantity_this_time, 
                $record_by_username_db, 
                $record_by_username_db, 
                $assembly_point, 
                $reason
            );

            if (!$stmt_insert->execute()) {
                $success = false;
                $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูลงานที่เสร็จสิ้น: " . $stmt_insert->error;
            }
            $stmt_insert->close();
        } else {
            $success = false;
            $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL (INSERT cutting_jobs_complete): " . $conn->error;
        }
    } else {
        // ถ้า completed_quantity_this_time เป็น 0 และไม่ได้บังคับปิด ก็ไม่ต้องทำอะไรกับการบันทึก
        $_SESSION['success_message'] = "ไม่มีจำนวนงานที่รับเข้าครั้งนี้ และไม่ได้เลือกบังคับปิดงาน";
    }


    // หากบันทึกข้อมูลงานที่เสร็จสิ้นสำเร็จ (หรือไม่มีการบันทึกเพราะจำนวนเป็น 0)
    if ($success) {
        // ตรวจสอบว่าจำนวนที่รับเข้าสะสมถึงจำนวนที่ต้องผลิตหรือไม่ หรือมีการบังคับปิด
        if ($potential_total_completed_quantity >= $quantity_to_produce || $force_complete) {
            // อัปเดตสถานะของ cutting_job เป็น 'เสร็จสิ้น'
            $sql_update_job_status = "
                UPDATE cutting_job
                SET job_status = 'เสร็จสิ้น'
                WHERE cutting_job_id = ?";
            
            $stmt_update = $conn->prepare($sql_update_job_status);

            if ($stmt_update) {
                $stmt_update->bind_param("i", $cutting_job_id);
                if (!$stmt_update->execute()) {
                    $success = false;
                    $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการอัปเดตสถานะงาน: " . $stmt_update->error;
                }
                $stmt_update->close();
            } else {
                $success = false;
                $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL (UPDATE cutting_job): " . $conn->error;
            }
            
            if ($success) {
                $status_updated_message = " สถานะงานถูกเปลี่ยนเป็น 'เสร็จสิ้น'";

                // [เพิ่ม] หากสถานะเปลี่ยนเป็น 'เสร็จสิ้น' ให้อัปเดต wip_inventory
                if (!is_null($output_part_id)) {
                    // ตรวจสอบว่า part_id นี้มีอยู่ใน wip_inventory หรือไม่
                    $sql_check_wip = "SELECT quantity FROM wip_inventory WHERE part_id = ?";
                    $stmt_check_wip = $conn->prepare($sql_check_wip);
                    $stmt_check_wip->bind_param("s", $output_part_id_db); // part_id เป็น CHAR(50)
                    $stmt_check_wip->execute();
                    $result_check_wip = $stmt_check_wip->get_result();

                    if ($result_check_wip->num_rows > 0) {
                        // ถ้ามีอยู่แล้ว ให้อัปเดต quantity
                        $sql_update_wip = "UPDATE wip_inventory SET quantity = quantity + ? WHERE part_id = ?";
                        $stmt_update_wip = $conn->prepare($sql_update_wip);
                        $stmt_update_wip->bind_param("is", $completed_quantity_this_time, $output_part_id_db); // ใช้จำนวนที่รับเข้าครั้งนี้
                        if (!$stmt_update_wip->execute()) {
                            $success = false;
                            $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการอัปเดต WIP Inventory: " . $stmt_update_wip->error;
                        }
                        $stmt_update_wip->close();
                    } else {
                        // [แก้ไข] ถ้ายังไม่มีใน WIP ให้ Redirect ไปหน้า cutting_job_list.php พร้อมพารามิเตอร์เพื่อแสดง Modal รับค่า Min/Max
                        $conn->rollback(); // ยกเลิก transaction ก่อน redirect
                        $_SESSION['error_message'] = "ชิ้นงานนี้ยังไม่ถูกตั้งค่า Min/Max ใน WIP Inventory กรุณากำหนดค่า";
                        header("Location: cutting_job_list.php?show_wip_modal=true&part_id=" . urlencode($output_part_id) . "&part_code=" . urlencode($part_code_for_modal));
                        exit();
                    }
                    $stmt_check_wip->close();
                } else {
                    // กรณี output_part_id เป็น NULL (อาจจะไม่มี output part สำหรับงานประเภทนี้)
                    error_log("Warning: output_part_id is NULL for cutting_job_id: " . $cutting_job_id . ". WIP inventory not updated.");
                }
            }

        } else {
            // ถ้าจำนวนสะสมยังไม่ถึงเป้าหมาย และไม่ได้บังคับปิด ไม่ต้องเปลี่ยนสถานะงาน
            $status_updated_message = " (จำนวนสะสม: {$potential_total_completed_quantity}/{$quantity_to_produce} ชิ้น)";
        }
    }


    // จัดการ Transaction
    if ($success) {
        $conn->commit();
        // ตั้งค่าข้อความสำเร็จหลัก
        $_SESSION['success_message'] = "บันทึกข้อมูลสำหรับ Job '{$job_id_for_cutting_jobs_complete}' จำนวน {$completed_quantity_this_time} ชิ้น เรียบร้อยแล้ว!" . $status_updated_message;
    } else {
        $conn->rollback(); // Rollback ถ้ามีข้อผิดพลาด
        // error_message ถูกตั้งค่าไว้แล้วใน block ที่เกิดข้อผิดพลาด
    }

    $conn->close();
    header("Location: cutting_job_list.php");
    exit();

} else {
    // ถ้าไม่ได้ส่งข้อมูลมาแบบ POST ให้เปลี่ยนเส้นทางกลับ
    header("Location: cutting_job_list.php");
    exit();
}
?>
