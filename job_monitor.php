<?php
// ภาษา: PHP
// ชื่อไฟล์: job_monitor.php

date_default_timezone_set('Asia/Bangkok');
if (session_status() === PHP_SESSION_NONE)
  session_start();

// รวมไฟล์เชื่อมต่อฐานข้อมูล และฟังก์ชันคำนวณไม้
include_once __DIR__ . '/config_db.php';
include_once __DIR__ . '/wood_issue_functions.php';

// ช่วงเวลาสัปดาห์
$startOfWeek = date('Y-m-d 00:00:00', strtotime('monday this week'));
$endOfWeek = date('Y-m-d 23:59:59', strtotime('sunday this week'));
// ช่วงเวลาเดือน
$startOfMonth = date('Y-m-01 00:00:00');
$endOfMonth = date('Y-m-t 23:59:59');

// สถานะ
$statusWaiting = 'กำลังเตรียมไม้';
$statusProcess = 'เบิกแล้ว';
$statusCreateArr = ['สั่งไม้', 'กำลังเตรียมไม้', 'รอเบิก', 'เบิกแล้ว', 'ปิดสำเร็จ', 'รอยืนยันการสั่งจ่าย', 'สั่งจ่ายแล้ว'];
$statusComplete = ['ปิดสำเร็จ', 'รอยืนยันการสั่งจ่าย', 'สั่งจ่ายแล้ว'];

// ฟังก์ชันช่วย Bind IN params
if (!function_exists('bindInParams')) {
  function bindInParams($stmt, $types, $params)
  {
    $bindNames = [];
    $bindNames[] = $types;
    foreach ($params as &$p) {
      $bindNames[] = &$p;
    }
    call_user_func_array([$stmt, 'bind_param'], $bindNames);
  }
}

// ดึงข้อมูล NOW
$stmt = $conn->prepare(
  "SELECT 
        SUM(issue_status = ?) AS waiting_count,
        SUM(issue_status = ?) AS process_count
     FROM wood_issue
     WHERE want_receive >= '2025-04-01 00:00:00'"
);
$stmt->bind_param('ss', $statusWaiting, $statusProcess);
$stmt->execute();
$stmt->bind_result($nowWaitingCount, $nowProcessCount);
$stmt->fetch();
$stmt->close();

// THIS MONTH: Create & Complete
$phCreate = implode(',', array_fill(0, count($statusCreateArr), '?'));
$sqlCreate = "SELECT COUNT(*) FROM wood_issue WHERE issue_status IN ($phCreate) AND want_receive BETWEEN ? AND ?";
$stmtM1 = $conn->prepare($sqlCreate);
bindInParams($stmtM1, str_repeat('s', count($statusCreateArr)) . 'ss', array_merge($statusCreateArr, [$startOfMonth, $endOfMonth]));
$stmtM1->execute();
$stmtM1->bind_result($monthCreateCount);
$stmtM1->fetch();
$stmtM1->close();
$phComplete = implode(',', array_fill(0, count($statusComplete), '?'));
$sqlComplete = "SELECT COUNT(*) FROM wood_issue WHERE issue_status IN ($phComplete) AND want_receive BETWEEN ? AND ?";
$stmtM2 = $conn->prepare($sqlComplete);
bindInParams($stmtM2, str_repeat('s', count($statusComplete)) . 'ss', array_merge($statusComplete, [$startOfMonth, $endOfMonth]));
$stmtM2->execute();
$stmtM2->bind_result($monthCompleteCount);
$stmtM2->fetch();
$stmtM2->close();

// คำนวณ M3 & Loss เดือนนี้
$sumMain = 0;
$sumLoss = 0;

// เตรียม statement ดึง job_id ช่วงเดือนนี้
$stmtJ = $conn->prepare(
  "SELECT job_id
     FROM wood_issue
     WHERE want_receive BETWEEN ? AND ?"
);

// แก้ไข bind_param: เอา 'vars:' ออก และใส่ตัวแปร $startOfMonth, $endOfMonth ตรงๆ
$stmtJ->bind_param('ss', $startOfMonth, $endOfMonth);

$stmtJ->execute();
$resultJobs = $stmtJ->get_result();
while ($r = $resultJobs->fetch_assoc()) {
  $d   = getJobDetails($conn, $r['job_id']);
  if (isset($d['error'])) continue;
  // คำนวณ M3 หลัก และ M3 ซ่อม/คืนไม้
  
  $m3  = round(getWoodIssueMainM3($d['prod_id'], $d['quantity'], $conn), 4);
  $rep = getWoodIssuesRepairM3($r['job_id'], $conn);
  $ret = getReturnWoodM3($r['job_id'], $conn);
  $l   = calculateWoodLoss($rep, $ret);
  $sumMain += (($m3+$rep)-$ret);
  $sumLoss += $l;
}
$stmtJ->close();
$lossPercentMonth = calculateWoodLossPercent($sumMain, $sumLoss);

// ALL TIME since 2025-04-01
$statusList = $statusCreateArr;
$dateFrom = '2025-04-01 00:00:00';
$phList = implode(',', array_fill(0, count($statusList), '?'));
$sqlAllJobs = "SELECT COUNT(DISTINCT job_id) FROM wood_issue WHERE issue_status IN ($phList) AND creation_date>=?";
$stmtA = $conn->prepare($sqlAllJobs);
bindInParams($stmtA, str_repeat('s', count($statusList)) . 's', array_merge($statusList, [$dateFrom]));
$stmtA->execute();
$stmtA->bind_result($allJobs);
$stmtA->fetch();
$stmtA->close();
$sqlAllComp = "SELECT COUNT(*) FROM wood_issue WHERE issue_status IN ($phComplete) AND creation_date>=?";
$stmtB = $conn->prepare($sqlAllComp);
bindInParams($stmtB, str_repeat('s', count($statusComplete)) . 's', array_merge($statusComplete, [$dateFrom]));
$stmtB->execute();
$stmtB->bind_result($allComp);
$stmtB->fetch();
$stmtB->close();
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta>
  <title>Job Monitor</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root {
      --font-title: 2rem;
      --font-label: 2rem;
      --font-value: 2.8rem;
      --gap: 1rem;
    }

    body {
      background: #000;
      color: #fff;
      margin: 0;
    }

    .grid {
      display: grid;
      grid-template-areas: "all month" "now metrics";
      grid-template-columns: 1fr 1fr;
      gap: var(--gap);
      padding: 2rem;
    }

    .panel {
      border: 2px solid #fff;
      border-radius: .25rem;
      padding: 1rem;
      text-align: center;
    }

    .month {
      grid-area: month;
    }

    .all {
      grid-area: all;
    }

    .metrics {
      grid-area: metrics;
    }

    .now {
      grid-area: now;
    }

    .title {
      font-size: var(--font-title);
      text-transform: uppercase;
      margin-bottom: var(--gap);
    }

    .sub-panel {
      border: 1px solid #fff;
      border-radius: .25rem;
      padding: .5rem;
      margin-bottom: var(--gap);
    }

    .label {
      font-size: var(--font-label);
      margin-bottom: .5rem;
    }

    .value {
      font-size: var(--font-value);
      font-weight: bold;
    }

    .text-yellow {
      color: #FFD700;
    }

    .text-green {
      color: #00FF00;
    }

    .text-red {
      color: #FF4500;
    }
  </style>
</head>

<body>
  <div class="grid">
    <!-- ALL TIME -->
    <div class="panel all">
      <div class="title">This Year</div>
      <div class="sub-panel">
        <div class="label">All Job</div>
        <div class="value text-yellow"><?= number_format($allJobs) ?></div>
      </div>
      <div class="sub-panel">
        <div class="label">Job Complete</div>
        <div class="value text-green"><?= number_format($allComp) ?></div>
      </div>
    </div>
    <!-- NOW -->
    <div class="panel now">
      <div class="title">Now</div>
      <div class="sub-panel">
        <div class="label">Waiting Material</div>
        <div class="value text-yellow"><?= number_format($nowWaitingCount) ?></div>
      </div>
      <div class="sub-panel">
        <div class="label">Work In Process</div>
        <div class="value text-green"><?= number_format($nowProcessCount) ?></div>
      </div>
    </div>
    <!-- THIS MONTH -->
    <div class="panel month">
      <div class="title">This Month</div>
      <div class="sub-panel">
        <div class="label">Job Create</div>
        <div class="value text-yellow"><?= number_format($monthCreateCount) ?></div>
      </div>
      <div class="sub-panel">
        <div class="label">Job Complete</div>
        <div class="value text-green"><?= number_format($monthCompleteCount) ?></div>
      </div>
    </div>
    <!-- METRICS CARD -->
    <div class="panel metrics">
      <div class="title">WOOD SUMMARY (Month)</div>
      <div class="sub-panel">
        <div class="label">Actual Wood M3</div>
        <div class="value text-yellow"><?= number_format($sumMain, 4) ?></div>
      </div>
      <div class="sub-panel">
        <div class="label">Wood Loss M3</div>
        <div class="value text-red"><?= number_format($sumLoss, 4) ?></div>
      </div>
      <div class="sub-panel">
        <div class="label">Wood Loss (%)</div>
        <div class="value text-green"><?= number_format($lossPercentMonth, 2) ?>%</div>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script> 
    window.setTimeout(function () {
      window.location.reload();
    }, 10000);
  </script>
</body>

</html>