<?php
// update_issue.php

// รวมไฟล์ functions.php เพื่อเรียกใช้ฟังก์ชัน updateIssueStatus
include('functions.php');

if (isset($_POST['confirm'])) {
    $issue_id = $_POST['issue_id'];
    $new_status = $_POST['new_status'];

    $result = updateIssueStatus($issue_id, $new_status);
    if ($result === true) {
        header("Location: planning_order.php?message=success");
        exit();
    } else {
        echo $result;
    }
}
?>
