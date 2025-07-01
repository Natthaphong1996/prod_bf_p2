<?php
// nails_issue_list.php

session_start();
date_default_timezone_set('Asia/Bangkok');

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once 'config_db.php';

// --- ดึงรายการ issue_id ทั้งหมดที่เคยมีการเบิกครั้งแรกแล้ว ---
$issued_ids_sql = "SELECT DISTINCT issue_id FROM nail_usage_log";
$issued_result = $conn->query($issued_ids_sql);
$issued_ids = [];
if ($issued_result) {
    while ($row = $issued_result->fetch_assoc()) {
        $issued_ids[] = $row['issue_id'];
    }
}

// --- การตั้งค่าการแสดงผลและ Pagination ---
$limit = 100;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// --- การสร้างเงื่อนไขสำหรับ SQL Query (WHERE Clause) ---
$whereClauses = [];
$params = [];
$types = '';

if (!empty($_GET['search_status'])) {
    $whereClauses[] = "wi.issue_status = ?";
    $types .= 's';
    $params[] = $_GET['search_status'];
} else {
    $statuses_to_show = ['สั่งไม้', 'กำลังเตรียมไม้', 'รอเบิก', 'เบิกแล้ว', 'ปิดสำเร็จ', 'รอยืนยันการสั่งจ่าย', 'สั่งจ่ายแล้ว'];
    $placeholders = implode(',', array_fill(0, count($statuses_to_show), '?'));
    $whereClauses[] = "wi.issue_status IN ($placeholders)";
    $types .= str_repeat('s', count($statuses_to_show));
    array_push($params, ...$statuses_to_show);
}

// --- เพิ่มเงื่อนไขการค้นหาอื่นๆ ---
if (!empty($_GET['search_job_code'])) {
    $whereClauses[] = "wi.job_id LIKE ?";
    $types .= 's';
    $params[] = "%" . $_GET['search_job_code'] . "%";
}
if (!empty($_GET['search_job_type'])) {
    $whereClauses[] = "wi.job_type LIKE ?";
    $types .= 's';
    $params[] = "%" . $_GET['search_job_type'] . "%";
}
if (!empty($_GET['search_customer'])) {
    $whereClauses[] = "(c.customer_name LIKE ? OR c.customer_short_name LIKE ?)";
    $types .= 'ss';
    $params[] = "%" . $_GET['search_customer'] . "%";
    $params[] = "%" . $_GET['search_customer'] . "%";
}
if (!empty($_GET['search_prod_code'])) {
    $whereClauses[] = "pl.prod_code LIKE ?";
    $types .= 's';
    $params[] = "%" . $_GET['search_prod_code'] . "%";
}
if (!empty($_GET['search_prod_partno'])) {
    $whereClauses[] = "pl.prod_partno LIKE ?";
    $types .= 's';
    $params[] = "%" . $_GET['search_prod_partno'] . "%";
}
if (!empty($_GET['search_date_created'])) {
    $dateRange = explode(' - ', $_GET['search_date_created']);
    if (count($dateRange) == 2) {
        $whereClauses[] = "DATE(wi.want_receive) BETWEEN ? AND ?";
        $types .= 'ss';
        $params[] = $dateRange[0];
        $params[] = $dateRange[1];
    }
}

$sql_where = "";
if (count($whereClauses) > 0) {
    $sql_where = " WHERE " . implode(' AND ', $whereClauses);
}

// --- การนับจำนวนข้อมูลทั้งหมดสำหรับ Pagination ---
$countSql = "SELECT COUNT(*) as total FROM wood_issue wi LEFT JOIN prod_list pl ON wi.prod_id = pl.prod_id LEFT JOIN customer c ON pl.customer_id = c.customer_id" . $sql_where;
$stmt_count = $conn->prepare($countSql);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$totalRows = $stmt_count->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);
$stmt_count->close();

// --- การดึงข้อมูลหลัก ---
$dataSql = "SELECT wi.issue_id, wi.job_id, wi.prod_id, wi.quantity, wi.want_receive, wi.issue_status,
                   pl.prod_partno, pl.prod_code, c.customer_name, c.customer_short_name
            FROM wood_issue wi
            LEFT JOIN prod_list pl ON pl.prod_id = wi.prod_id  
            LEFT JOIN customer c ON pl.customer_id = c.customer_id" . $sql_where . 
            " ORDER BY wi.creation_date DESC LIMIT ? OFFSET ?";
$data_params = $params;
$data_types = $types . 'ii';
$data_params[] = $limit;
$data_params[] = $offset;

$stmt_data = $conn->prepare($dataSql);
$stmt_data->bind_param($data_types, ...$data_params);
$stmt_data->execute();
$result = $stmt_data->get_result();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการเบิกตะปู</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="mb-0">รายการเบิกตะปู</h1>
        </div>

        <!-- ส่วนของฟอร์มค้นหา (เหมือนเดิม) -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row g-3">
                        <div class="col-md-4 col-lg-3">
                            <label for="search_job_code" class="form-label">หมายเลข JOB</label>
                            <input type="text" class="form-control" name="search_job_code" id="search_job_code" value="<?php echo isset($_GET['search_job_code']) ? htmlspecialchars($_GET['search_job_code']) : ''; ?>">
                        </div>
                        <div class="col-md-4 col-lg-3">
                            <label for="search_job_type" class="form-label">ประเภทงาน</label>
                            <select class="form-select" name="search_job_type" id="search_job_type">
                                <option value="">-- เลือกประเภทงาน --</option>
                                <option value="งานหมวด A" <?php echo (($_GET['search_job_type'] ?? '') === 'งานหมวด A') ? 'selected' : ''; ?>>งานหมวด A</option>
                                <option value="งานทั่วไป" <?php echo (($_GET['search_job_type'] ?? '') === 'งานทั่วไป') ? 'selected' : ''; ?>>งานทั่วไป</option>
                                <option value="งานไม้ PACK" <?php echo (($_GET['search_job_type'] ?? '') === 'งานไม้ PACK') ? 'selected' : ''; ?>>งานไม้ PACK</option>
                                <option value="PALLET RETURN" <?php echo (($_GET['search_job_type'] ?? '') === 'PALLET RETURN') ? 'selected' : ''; ?>>PALLET RETURN</option>
                                <option value="งานเคลม" <?php echo (($_GET['search_job_type'] ?? '') === 'งานเคลม') ? 'selected' : ''; ?>>งานเคลม</option>
                                <option value="งานพาเลทไม้อัด" <?php echo (($_GET['search_job_type'] ?? '') === 'งานพาเลทไม้อัด') ? 'selected' : ''; ?>>งานพาเลทไม้อัด</option>
                            </select>
                        </div>
                        <div class="col-md-4 col-lg-3">
                            <label for="search_customer" class="form-label">ชื่อลูกค้า</label>
                            <input type="text" class="form-control" name="search_customer" id="search_customer" value="<?php echo isset($_GET['search_customer']) ? htmlspecialchars($_GET['search_customer']) : ''; ?>">
                        </div>
                        <div class="col-md-4 col-lg-3">
                            <label for="search_prod_code" class="form-label">ITEM CODE FG</label>
                            <input type="text" class="form-control" name="search_prod_code" id="search_prod_code" value="<?php echo isset($_GET['search_prod_code']) ? htmlspecialchars($_GET['search_prod_code']) : ''; ?>">
                        </div>
                        <div class="col-md-4 col-lg-3">
                            <label for="search_prod_partno" class="form-label">PART NO.</label>
                            <input type="text" class="form-control" name="search_prod_partno" id="search_prod_partno" value="<?php echo isset($_GET['search_prod_partno']) ? htmlspecialchars($_GET['search_prod_partno']) : ''; ?>">
                        </div>
                        <div class="col-md-4 col-lg-3">
                            <label for="search_status" class="form-label">สถานะ</label>
                            <select class="form-select" name="search_status" id="search_status">
                                <option value="">-- ทุกสถานะที่เกี่ยวข้อง --</option>
                                <?php
                                $all_statuses = ['สั่งไม้', 'กำลังเตรียมไม้', 'รอเบิก', 'เบิกแล้ว', 'ปิดสำเร็จ', 'รอยืนยันการสั่งจ่าย', 'สั่งจ่ายแล้ว'];
                                $selected_status = $_GET['search_status'] ?? '';
                                foreach ($all_statuses as $status) {
                                    $selected = ($selected_status === $status) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($status) . '" ' . $selected . '>' . htmlspecialchars($status) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4 col-lg-3">
                            <label for="search_date_created" class="form-label">วันที่ต้องการรับไม้</label>
                            <input type="text" class="form-control" name="search_date_created" id="search_date_created" value="<?php echo isset($_GET['search_date_created']) ? htmlspecialchars($_GET['search_date_created']) : ''; ?>" />
                        </div>
                        <div class="col-md-12 d-flex justify-content-end align-items-end">
                            <button type="submit" class="btn btn-primary me-2"><i class="bi bi-search"></i> ค้นหา</button>
                            <a href="nails_issue_list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i> รีเซ็ต</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover">
                <thead class="table-light text-center align-middle">
                    <tr>
                        <th>หมายเลข JOB</th>
                        <th>ITEM CODE FG</th>
                        <th>PART NO.</th>
                        <th>จำนวน</th>
                        <th>วันที่ต้องการรับไม้</th>
                        <th>สถานะ</th>
                        <th>ดำเนินการ</th>
                    </tr>
                </thead>
                <tbody class="text-center align-middle">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <?php
                            $is_finished = in_array($row['issue_status'], ['ปิดสำเร็จ', 'รอยืนยันการสั่งจ่าย', 'สั่งจ่ายแล้ว']);
                            $has_been_issued = in_array($row['issue_id'], $issued_ids);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['job_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['prod_code']); ?></td>
                                <td><?php echo isset($row['prod_partno']) ? htmlspecialchars($row['prod_partno']) : 'N/A'; ?></td>
                                <td><?php echo htmlspecialchars($row['quantity']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($row['want_receive'])); ?></td>
                                <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($row['issue_status']); ?></span></td>
                                <td class="btn-group" role="group">
                                    <button type="button" class="btn btn-success btn-sm view-history-btn" data-issue-id="<?= $row['issue_id'] ?>" data-prod-id="<?= $row['prod_id'] ?>" data-job-id="<?= $row['job_id'] ?>">
                                        <i class="bi bi-clock-history"></i> ดูประวัติ
                                    </button>
                                    <?php if ($is_finished): ?>
                                        <button type="button" class="btn btn-secondary btn-sm" disabled><i class="bi bi-check-circle-fill"></i></button>
                                    <?php elseif ($has_been_issued): ?>
                                        <button type="button" class="btn btn-info btn-sm open-nail-modal" data-issue-id="<?= $row['issue_id'] ?>" data-prod-id="<?= $row['prod_id'] ?>" data-job-id="<?= $row['job_id'] ?>"><i class="bi bi-tools"></i></button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-warning btn-sm open-nail-modal" data-issue-id="<?= $row['issue_id'] ?>" data-prod-id="<?= $row['prod_id'] ?>" data-job-id="<?= $row['job_id'] ?>"><i class="bi bi-box-arrow-up"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">ไม่พบข้อมูลตามเงื่อนไขที่ระบุ</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-4">
            <div>
                <?php
                $start_record = $totalRows > 0 ? ($offset + 1) : 0;
                $end_record = min($offset + $limit, $totalRows);
                echo "แสดงรายการที่ {$start_record} ถึง {$end_record} จากทั้งหมด {$totalRows} รายการ";
                ?>
            </div>
            <div>
                <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination">
                        <?php
                        $base_params = $_GET; unset($base_params['page']);
                        $base_query = !empty($base_params) ? '&' . http_build_query($base_params) : '';
                        echo '<li class="page-item ' . ($page <= 1 ? 'disabled' : '') . '"><a class="page-link" href="?page=1' . $base_query . '">First</a></li>';
                        echo '<li class="page-item ' . ($page <= 1 ? 'disabled' : '') . '"><a class="page-link" href="?page=' . ($page - 1) . $base_query . '">&laquo;</a></li>';
                        $window = 2; $show_ellipsis_start = false; $show_ellipsis_end = false;
                        for ($i = 1; $i <= $totalPages; $i++) {
                            if ($i == 1 || $i == $totalPages || ($i >= $page - $window && $i <= $page + $window)) {
                                $active = ($i == $page) ? 'active' : '';
                                echo '<li class="page-item ' . $active . '"><a class="page-link" href="?page=' . $i . $base_query . '">' . $i . '</a></li>';
                            } else {
                                if ($i < $page && !$show_ellipsis_start) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; $show_ellipsis_start = true; }
                                if ($i > $page && !$show_ellipsis_end) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; $show_ellipsis_end = true; }
                            }
                        }
                        echo '<li class="page-item ' . ($page >= $totalPages ? 'disabled' : '') . '"><a class="page-link" href="?page=' . ($page + 1) . $base_query . '">&raquo;</a></li>';
                        echo '<li class="page-item ' . ($page >= $totalPages ? 'disabled' : '') . '"><a class="page-link" href="?page=' . $totalPages . $base_query . '">Last</a></li>';
                        ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal เบิกตะปู -->
    <div class="modal fade" id="nailModal" tabindex="-1" aria-labelledby="nailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="nailForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="nailModalLabel">เบิกตะปู</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p><strong>Job ID:</strong> <span id="modalJobIdText"></span></p>
                        <div id="nailListContainer"><div class="text-center my-3"><div class="spinner-border" role="status"></div></div></div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="issue_id" id="modalIssueId">
                        <input type="hidden" name="job_id" id="modalJobId">
                        <input type="hidden" name="user_id" value="<?php echo $_SESSION['user_id']; ?>">
                        <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> ยืนยันการเบิก</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal ดูประวัติ -->
    <div class="modal fade" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="historyModalLabel">ประวัติการเบิกตะปู</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Job ID:</strong> <span id="historyJobIdText"></span></p>
                    <div id="historyContainer"><div class="text-center my-3"><div class="spinner-border" role="status"></div></div></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($stmt_data)) $stmt_data->close(); if (isset($conn)) $conn->close(); ?>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script>
    $(function() {
        $('#search_date_created').daterangepicker({ autoUpdateInput: false, locale: { format: 'YYYY-MM-DD', cancelLabel: 'Clear', applyLabel: "ตกลง", fromLabel: "จาก", toLabel: "ถึง" }, opens: 'left'
        }).on('apply.daterangepicker', function(ev, picker) { $(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD'));
        }).on('cancel.daterangepicker', function(ev, picker) { $(this).val(''); });

        // [แก้ไข] ปรับปรุง Event Handlers ทั้งหมด
        $(document).on('click', '.open-nail-modal', function() {
            const button = $(this);
            const issueId = button.data('issue-id');
            const prodId = button.data('prod-id');
            const jobId = button.data('job-id');
            
            const modalElement = document.getElementById('nailModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
            
            $('#modalIssueId').val(issueId);
            $('#modalJobId').val(jobId);
            $('#modalJobIdText').text(jobId);
            $('#nailListContainer').html('<div class="d-flex justify-content-center my-3"><div class="spinner-border" role="status"></div></div>');
            
            modal.show();
            
            $.post('get_nail_issue_details.php', { prod_id: prodId, issue_id: issueId })
                .done(function(response) { $('#nailListContainer').html(response); })
                .fail(function() { $('#nailListContainer').html('<p class="text-center text-danger">เกิดข้อผิดพลาดในการโหลดข้อมูล</p>'); });
        });

        $(document).on('click', '.view-history-btn', function() {
            const button = $(this);
            const issueId = button.data('issue-id');
            const prodId = button.data('prod-id');
            const jobId = button.data('job-id');
            
            const modalElement = document.getElementById('historyModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalElement);

            $('#historyJobIdText').text(jobId);
            $('#historyContainer').html('<div class="d-flex justify-content-center my-3"><div class="spinner-border" role="status"></div></div>');
            
            modal.show();

            $.post('get_nail_history.php', { prod_id: prodId, issue_id: issueId })
                .done(function(response) { $('#historyContainer').html(response); })
                .fail(function() { $('#historyContainer').html('<p class="text-center text-danger">เกิดข้อผิดพลาดในการโหลดประวัติ</p>'); });
        });

        $('#nailForm').submit(function(e) {
            e.preventDefault();
            const formData = $(this).serialize();
            const submitButton = $(this).find('button[type="submit"]');
            submitButton.prop('disabled', true);
            $.post('save_nail_usage.php', formData)
                .done(function(response) {
                    let res = response;
                    if (typeof res !== 'object') { try { res = JSON.parse(res); } catch (e) { alert('Error processing response.'); return; } }
                    alert(res.message);
                    if (res.success) { 
                        const modalInstance = bootstrap.Modal.getInstance(document.getElementById('nailModal'));
                        if (modalInstance) {
                            modalInstance.hide();
                        }
                        location.reload(); 
                    }
                })
                .fail(function() { alert("เกิดข้อผิดพลาดในการส่งข้อมูล"); })
                .always(function() { submitButton.prop('disabled', false); });
        });
    });
    </script>
</body>
</html>
