<?php
include 'config_db.php'; // เชื่อมต่อกับฐานข้อมูล

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // รับค่าจากฟอร์ม
    $issue_id = $_POST['issue_id'];
    $job_id = $_POST['job_id'];
    $quantity = $_POST['quantity'];
    $want_receive = $_POST['want_receive'];
    $remark = $_POST['remark'];

    // ตรวจสอบว่า job_id ที่กรอกมีอยู่ในระบบหรือไม่
    $check_sql = "SELECT COUNT(*) FROM wood_issue WHERE job_id = ? AND issue_id != ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('si', $job_id, $issue_id);
    $check_stmt->execute();
    $check_stmt->bind_result($count);
    $check_stmt->fetch();
    $check_stmt->close();

    // หากพบ job_id ซ้ำ
    if ($count > 0) {
        echo "<script type='text/javascript'>
                alert('Error: job_id นี้มีอยู่ในระบบแล้ว กรุณากรอกหมายเลข JOB ใหม่.');
                window.location.href = 'planning_order.php'; // เปลี่ยนเส้นทางไปยังหน้า planning_order.php
              </script>";
    } else {
        // ถ้า job_id ไม่ซ้ำ ทำการอัปเดตข้อมูล
        $sql = "UPDATE wood_issue SET job_id = ?, quantity = ?, want_receive = ?, remark = ? WHERE issue_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sissi', $job_id, $quantity, $want_receive, $remark, $issue_id);

        if ($stmt->execute()) {
            // ถ้าอัปเดตสำเร็จ
            echo "<script type='text/javascript'>
                    alert('ทำการ UPDATA ข้อมูลเรียบร้อย');
                    window.location.href = 'planning_order.php'; // เปลี่ยนเส้นทางไปยังหน้า planning_order.php
                </script>";
        } else {
            // ถ้าเกิดข้อผิดพลาด
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
    }

    $conn->close();
}
?>
