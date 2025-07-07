<?php
// repair_issue_summary.php

session_start();
include 'config_db.php';
include 'navbar.php';

// ตรวจสอบสิทธิ์การเข้าใช้งาน (ถ้ามี)
if (!isset($_SESSION['username'])) {
    header('Location: index.php');
    exit();
}

// --- การตั้งค่าตัวแปรสำหรับ Pagination และ Filter ---
$limit = 25; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// จัดการตัวกรอง
$search_job_id = isset($_GET['search_job_id']) ? trim($_GET['search_job_id']) : '';
$default_start_date = date('Y-m-01');
$default_end_date = date('Y-m-t');
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : $default_start_date;
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : $default_end_date;

// --- Helper function for bind_param references (needed for dynamic parameters) ---
function ref_values($arr) {
    if (strnatcmp(phpversion(),'5.3') >= 0) { // PHP 5.3+
        $refs = array();
        foreach($arr as $key => $value)
            $refs[$key] = &$arr[$key];
        return $refs;
    }
    return $arr;
}

// --- Logic to fetch all repair issues for M3 calculation ---
$all_repair_issues_raw = [];
$params = [];
$types = '';

$query_base = "SELECT repair_id, job_id, part_quantity_reason, created_at FROM repair_issue";
$where_clauses = [];

if (!empty($search_job_id)) {
    $where_clauses[] = "job_id LIKE ?";
    $params[] = "%" . $search_job_id . "%";
    $types .= 's';
} else {
    // Adjust date range to cover full day (00:00:00 to 23:59:59)
    $start_date_full = $start_date . ' 00:00:00';
    $end_date_full = $end_date . ' 23:59:59';
    $where_clauses[] = "created_at BETWEEN ? AND ?";
    $params[] = $start_date_full;
    $params[] = $end_date_full;
    $types .= 'ss';
}

if (!empty($where_clauses)) {
    $query_base .= " WHERE " . implode(' AND ', $where_clauses);
}
$query_base .= " ORDER BY created_at DESC"; // Order by created_at

$stmt = $conn->prepare($query_base);
if ($stmt) {
    if (!empty($params)) {
        call_user_func_array([$stmt, 'bind_param'], ref_values(array_merge([$types], $params)));
    }
    $stmt->execute();
    $all_repair_issues_raw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    die("SQL Error: " . $conn->error);
}

// --- Fetch all unique part_ids from all repair_issues for global lookup ---
$all_part_ids_for_lookup = [];
foreach ($all_repair_issues_raw as $issue) {
    $parts_array = json_decode($issue['part_quantity_reason'], true);
    if (is_array($parts_array)) {
        foreach ($parts_array as $part) {
            if (isset($part['part_id'])) {
                $all_part_ids_for_lookup[] = trim($part['part_id']);
            }
        }
    }
}

// Fetch part_details (m3, length, width, thickness, type) for all unique part_ids
$part_details_lookup = [];
if (!empty($all_part_ids_for_lookup)) {
    $unique_part_ids = array_unique(array_filter($all_part_ids_for_lookup));
    if (!empty($unique_part_ids)) {
        $part_ids_placeholder = implode(',', array_fill(0, count($unique_part_ids), '?'));
        $part_ids_types = str_repeat('i', count($unique_part_ids)); // Assuming part_id is integer

        // Select all necessary part details
        $stmt = $conn->prepare("SELECT part_id, part_m3, part_length, part_width, part_thickness, part_type FROM part_list WHERE part_id IN ($part_ids_placeholder)");
        if ($stmt) {
            call_user_func_array([$stmt, 'bind_param'], ref_values(array_merge([$part_ids_types], $unique_part_ids)));
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $part_details_lookup[$row['part_id']] = [
                    'm3' => (float)$row['part_m3'],
                    'length' => (int)$row['part_length'],
                    'width' => (int)$row['part_width'],
                    'thickness' => (int)$row['part_thickness'],
                    'type' => $row['part_type']
                ];
            }
            $stmt->close();
        } else {
            error_log("Failed to prepare part_list query: " . $conn->error);
        }
    }
}

// --- Calculate M3 for each repair issue and global totals, and enrich part details for modal ---
$processed_repair_issues = [];
$grand_total_m3 = 0;

foreach ($all_repair_issues_raw as $issue) {
    $issue_m3 = 0;
    $parts_array = json_decode($issue['part_quantity_reason'], true);
    $enriched_part_details = []; // Array to store enriched details for the modal

    if (is_array($parts_array)) {
        foreach ($parts_array as $part_item) {
            $part_id = $part_item['part_id'] ?? null;
            $quantity = (int)($part_item['quantity'] ?? 0);
            $reason = $part_item['reason'] ?? 'N/A';

            $current_item_m3 = 0.0;
            $part_size = 'N/A';
            $part_type_display = 'N/A';

            if ($part_id && isset($part_details_lookup[$part_id])) {
                $lookup_data = $part_details_lookup[$part_id];
                $current_item_m3 = $lookup_data['m3'] * $quantity;
                $part_size = "{$lookup_data['thickness']} x {$lookup_data['width']} x {$lookup_data['length']}";
                $part_type_display = $lookup_data['type'];
            }
            
            $issue_m3 += $current_item_m3; // Accumulate M3 for this repair_issue

            // Store enriched data for the modal
            $enriched_part_details[] = [
                'part_id' => $part_id,
                'quantity' => $quantity,
                'reason' => $reason,
                'size' => $part_size,
                'part_type' => $part_type_display,
                'item_m3' => $current_item_m3
            ];
        }
    }
    $issue['total_m3'] = $issue_m3;
    $issue['enriched_part_details'] = $enriched_part_details; // Add enriched data to the issue
    $grand_total_m3 += $issue_m3;
    $processed_repair_issues[] = $issue;
}

// --- Pagination for display ---
$total_rows = count($processed_repair_issues); // Total rows are now individual repair issues
$total_pages = ceil($total_rows / $limit);
$repair_issues_for_this_page = array_slice($processed_repair_issues, $offset, $limit);

// Calculate page total M3
$page_total_m3 = 0;
foreach ($repair_issues_for_this_page as $issue) {
    $page_total_m3 += $issue['total_m3'];
}


// --- Pagination HTML Generator ---
function generate_pagination($current_page, $total_pages, $url_params) {
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    $window = 2; 

    if ($current_page > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="?page=' . ($current_page - 1) . '&' . $url_params . '">ก่อนหน้า</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">ก่อนหน้า</span></li>';
    }

    if ($current_page > $window + 1) {
        $html .= '<li class="page-item"><a class="page-link" href="?page=1&' . $url_params . '">1</a></li>';
        if ($current_page > $window + 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    for ($i = max(1, $current_page - $window); $i <= min($total_pages, $current_page + $window); $i++) {
        if ($i == $current_page) {
            $html .= '<li class="page-item active" aria-current="page"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="?page=' . $i . '&' . $url_params . '">' . $i . '</a></li>';
        }
    }

    if ($current_page < $total_pages - $window) {
        if ($current_page < $total_pages - $window - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&' . $url_params . '">' . $total_pages . '</a></li>';
    }

    if ($current_page < $total_pages) {
        $html .= '<li class="page-item"><a class="page-link" href="?page=' . ($current_page + 1) . '&' . $url_params . '">ถัดไป</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">ถัดไป</span></li>';
    }

    $html .= '</ul></nav>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานสรุป M³ งานเบิกซ่อม</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <style>
        body { background-color: #f8f9fa; }
        .container-fluid { background-color: #fff; border-radius: 8px; padding: 2rem; margin: 1rem; box-shadow: 0 4px 8px rgba(0,0,0,0.05); }
        .table-hover tbody tr:hover { background-color: #f1f1f1; }
        .table th, .table td { vertical-align: middle; text-align: center; }
        .table .text-start { text-align: left !important; }
        .table tfoot th { font-weight: bold; background-color: #f8f9fa; }
        /* Adjusted column widths for this specific report */
        .table th:nth-child(1), .table td:nth-child(1) { width: 15%; } /* Repair ID */
        .table th:nth-child(2), .table td:nth-child(2) { width: 15%; } /* Job ID */
        .table th:nth-child(3), .table td:nth-child(3) { width: 20%; } /* วันที่สร้างเอกสาร */
        .table th:nth-child(4), .table td:nth-child(4) { width: 25%; } /* M³ รวม (ลบ.ม.) */
        .table th:nth-child(5), .table td:nth-child(5) { width: 25%; } /* รายละเอียด (ปุ่ม) */

        /* Modal table styling */
        #repairDetailsModal .modal-body .table th,
        #repairDetailsModal .modal-body .table td {
            text-align: center; /* Center align text in modal table */
        }
        #repairDetailsModal .modal-body .table th:nth-child(1) { width: 20%; } /* Part ID/Size */
        #repairDetailsModal .modal-body .table th:nth-child(2) { width: 20%; } /* Part Type */
        #repairDetailsModal .modal-body .table th:nth-child(3) { width: 15%; } /* Quantity */
        #repairDetailsModal .modal-body .table th:nth-child(4) { width: 30%; } /* Reason */
        #repairDetailsModal .modal-body .table th:nth-child(5) { width: 15%; } /* M3 per Item */
    </style>
</head>
<body>
    <div class="container-fluid">
        <h1 class="mb-4"><i class="bi bi-tools text-primary"></i> รายงานสรุป M³ งานเบิกซ่อม</h1>
        <form method="GET" class="row g-3 align-items-center mb-4 p-3 border rounded bg-light">
            <div class="col-md-4">
                <label for="search_job_id" class="form-label">ค้นหาโดย Job ID:</label>
                <input type="text" id="search_job_id" name="search_job_id" class="form-control" value="<?= htmlspecialchars($search_job_id) ?>" placeholder="กรอก Job ID ที่ต้องการค้นหา...">
            </div>
            <div class="col-md-4">
                <label for="daterange" class="form-label">หรือเลือกช่วงวันที่สร้างเอกสาร:</label>
                <input type="text" id="daterange" name="daterange" class="form-control">
                <input type="hidden" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                <input type="hidden" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> ค้นหา</button>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <a href="repair_issue_summary.php" class="btn btn-secondary w-100"><i class="bi bi-arrow-clockwise"></i> ล้างค่า</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped table-hover table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>Repair ID</th>
                        <th>Job ID</th>
                        <th>วันที่สร้างเอกสาร</th>
                        <th>M³ รวม (ลบ.ม.)</th>
                        <th>รายละเอียด</th> <!-- NEW COLUMN FOR DETAIL BUTTON -->
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($repair_issues_for_this_page)): ?>
                        <?php foreach ($repair_issues_for_this_page as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['repair_id']) ?></td>
                                <td><?= htmlspecialchars($row['job_id']) ?></td>
                                <td><?= $row['created_at'] ? date('d/m/Y H:i:s', strtotime($row['created_at'])) : 'N/A' ?></td>
                                <td class="fw-bold bg-success-subtle"><?= number_format($row['total_m3'], 4) ?></td>
                                <td>
                                    <button type="button" class="btn btn-info btn-sm view-details-btn" 
                                            data-bs-toggle="modal" data-bs-target="#repairDetailsModal" 
                                            data-repair-id="<?= htmlspecialchars($row['repair_id']) ?>"
                                            data-part-details='<?= htmlspecialchars(json_encode($row['enriched_part_details']), ENT_QUOTES, 'UTF-8') ?>'>
                                        <i class="bi bi-eye"></i> ดูรายละเอียด
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center p-4">ไม่พบข้อมูลตามเงื่อนไขที่ค้นหา</td></tr> <!-- Adjusted colspan -->
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($processed_repair_issues)): ?>
                <tfoot class="table-group-divider">
                    <tr class="table-light">
                        <th colspan="4" class="text-end">ยอดรวมหน้านี้:</th>
                        <th class="bg-info-subtle"><?= number_format($page_total_m3, 4) ?></th>
                    </tr>
                    <tr class="table-secondary">
                        <th colspan="4" class="text-end">ยอดรวมทั้งหมด (จากการค้นหา):</th>
                        <th class="bg-primary-subtle"><?= number_format($grand_total_m3, 4) ?></th>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <?php 
                $query_params = http_build_query([
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'search_job_id' => $search_job_id
                ]);
                echo generate_pagination($page, $total_pages, $query_params); 
            ?>
        <?php endif; ?>

    </div>

    <?php include 'footer.php'; ?>

    <!-- Repair Details Modal -->
    <div class="modal fade" id="repairDetailsModal" tabindex="-1" aria-labelledby="repairDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="repairDetailsModalLabel">รายละเอียดงานเบิกซ่อม: <span id="modalRepairId"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6>รายการ Part ที่เบิกซ่อม:</h6>
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>ขนาด</th>
                                <th>ประเภทไม้</th>
                                <th>Quantity</th>
                                <th>Reason</th>
                                <th>M³ (ลบ.ม.)</th>
                            </tr>
                        </thead>
                        <tbody id="modalPartDetailsBody">
                            <!-- Details will be loaded here by JS -->
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script>
        $(function() {
            // Initialize daterangepicker
            $('#daterange').daterangepicker({
                startDate: moment('<?= htmlspecialchars($start_date) ?>'),
                endDate: moment('<?= htmlspecialchars($end_date) ?>'),
                locale: {
                    format: 'DD/MM/YYYY',
                    applyLabel: 'ตกลง',
                    cancelLabel: 'ยกเลิก',
                    fromLabel: 'จาก',
                    toLabel: 'ถึง',
                    customRangeLabel: 'กำหนดเอง',
                    weekLabel: 'สัปดาห์',
                    daysOfWeek: ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'],
                    monthNames: ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'],
                }
            }, function(start, end, label) {
                $('#start_date').val(start.format('YYYY-MM-DD'));
                $('#end_date').val(end.format('YYYY-MM-DD'));
            });

            // Disable date range picker if Job ID search is active
            if ($('#search_job_id').val().trim() !== '') {
                $('#daterange').prop('disabled', true).css('background-color', '#e9ecef');
            }

            // Toggle date range picker based on Job ID search input
            $('#search_job_id').on('input', function() {
                if ($(this).val().trim() !== '') {
                    $('#daterange').prop('disabled', true).css('background-color', '#e9ecef');
                } else {
                    $('#daterange').prop('disabled', false).css('background-color', '');
                }
            });

            // Event listener for "View Details" button
            $(document).on('click', '.view-details-btn', function() {
                const repairId = $(this).data('repair-id');
                // The data-part-details attribute now contains the pre-enriched JSON array
                const partDetails = $(this).data('part-details'); 

                $('#modalRepairId').text(repairId);
                const modalPartDetailsBody = $('#modalPartDetailsBody');
                modalPartDetailsBody.empty(); // Clear previous details

                if (Array.isArray(partDetails) && partDetails.length > 0) {
                    partDetails.forEach(function(part) {
                        const row = `
                            <tr>
                                <td>${part.size ? htmlspecialchars(String(part.size)) : 'N/A'}</td>
                                <td>${part.part_type ? htmlspecialchars(String(part.part_type)) : 'N/A'}</td>
                                <td>${part.quantity ? htmlspecialchars(String(part.quantity)) : 'N/A'}</td>
                                <td>${part.reason ? htmlspecialchars(String(part.reason)) : 'N/A'}</td>
                                <td>${part.item_m3 ? Number(part.item_m3).toLocaleString('th-TH', { minimumFractionDigits: 4, maximumFractionDigits: 4 }) : '0.0000'}</td>
                            </tr>
                        `;
                        modalPartDetailsBody.append(row);
                    });
                } else {
                    modalPartDetailsBody.append('<tr><td colspan="5" class="text-center">ไม่พบรายละเอียด Part</td></tr>');
                }
            });

            // Helper function to sanitize HTML output (XSS prevention)
            function htmlspecialchars(str) {
                let map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return str.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
            }
        });
    </script>
</body>
</html>
