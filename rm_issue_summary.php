<?php
// rm_issue_summary.php
// ภาษา: PHP
// หน้าที่: สรุปการเบิกไม้ท่อนจาก Cutting Batch ตามช่วงวันที่ที่ต้องการไม้ท่อน (rm_needed_date)
// พร้อมแสดงจำนวนท่อนและลูกค้าที่เกี่ยวข้อง และเพิ่มฟังก์ชันค้นหา (รวมช่องขนาด)

session_start();
require_once __DIR__ . '/config_db.php'; // เชื่อมต่อฐานข้อมูล
require_once __DIR__ . '/functions.php'; // สำหรับฟังก์ชัน getUserThaiName หรือฟังก์ชันอื่นๆ ที่จำเป็น

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบหรือไม่
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); // ถ้าไม่ได้เข้าสู่ระบบ ให้เปลี่ยนเส้นทางไปหน้า login
    exit();
}

// กำหนดค่าเริ่มต้นสำหรับช่วงวันที่
$today = date('Y-m-d');
$default_start_date = date('Y-m-01'); // วันที่ 1 ของเดือนปัจจุบัน
$default_end_date = $today;

$start_date = $_GET['start_date'] ?? $default_start_date;
$end_date = $_GET['end_date'] ?? $default_end_date;

// รับค่าจากช่องค้นหา
$search_rm_code = $_GET['search_rm_code'] ?? '';
$search_size = $_GET['search_size'] ?? ''; // [แก้ไข] ช่องค้นหาขนาดรวม
$search_customer_name = $_GET['search_customer_name'] ?? '';

// ตรวจสอบและทำความสะอาดวันที่และค่าค้นหา
$start_date_db = $conn->real_escape_string($start_date);
$end_date_db = $conn->real_escape_string($end_date);
$search_rm_code_db = $conn->real_escape_string($search_rm_code);
$search_size_db = $conn->real_escape_string($search_size); // ทำความสะอาด input ขนาดรวม
$search_customer_name_db = $conn->real_escape_string($search_customer_name);


// กำหนดค่าสำหรับ Pagination
$limit = 10; // จำนวนรายการต่อหน้า
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$aggregated_data = []; // สำหรับเก็บข้อมูลที่รวมแล้ว
$total_records = 0; // จำนวนรายการทั้งหมดก่อน pagination
$total_pages = 0; // กำหนดค่าเริ่มต้นให้ $total_pages

try {
    // สร้างเงื่อนไข WHERE สำหรับการค้นหา
    $where_clauses = ["DATE(cb.rm_needed_date) BETWEEN ? AND ?", "cb.batch_status != 'รอดำเนินการ'"];
    $param_types = "ss";
    $param_values = [&$start_date_db, &$end_date_db];

    if (!empty($search_rm_code)) {
        $where_clauses[] = "rm.rm_code LIKE ?";
        $param_types .= "s";
        $search_rm_code_like = "%" . $search_rm_code_db . "%";
        $param_values[] = &$search_rm_code_like;
    }

    // [แก้ไข] Logic สำหรับค้นหาขนาดรวม
    if (!empty($search_size)) {
        $size_parts = explode('x', strtolower($search_size_db)); // แยกด้วย 'x' และแปลงเป็นตัวพิมพ์เล็ก

        $parsed_thickness = null;
        $parsed_width = null;
        $parsed_length = null;

        // พยายามแยกส่วนประกอบของขนาด
        if (isset($size_parts[0]) && is_numeric($size_parts[0])) {
            $parsed_thickness = (int)$size_parts[0];
        }
        if (isset($size_parts[1]) && is_numeric($size_parts[1])) {
            $parsed_width = (int)$size_parts[1];
        }
        if (isset($size_parts[2]) && is_numeric($size_parts[2])) {
            $parsed_length = (int)$size_parts[2];
        }

        // เพิ่มเงื่อนไข WHERE ตามส่วนประกอบที่พบ
        if ($parsed_thickness !== null) {
            $where_clauses[] = "rm.rm_thickness = ?";
            $param_types .= "i";
            $param_values[] = &$parsed_thickness;
        }
        if ($parsed_width !== null) {
            $where_clauses[] = "rm.rm_width = ?";
            $param_types .= "i";
            $param_values[] = &$parsed_width;
        }
        if ($parsed_length !== null) {
            $where_clauses[] = "rm.rm_length = ?";
            $param_types .= "i";
            $param_values[] = &$parsed_length;
        }
        // หากไม่มีส่วนใดเป็นตัวเลขเลย (เช่น "abc") จะไม่เพิ่มเงื่อนไขขนาด
    }

    if (!empty($search_customer_name)) {
        $where_clauses[] = "c.customer_name LIKE ?";
        $param_types .= "s";
        $search_customer_name_like = "%" . $search_customer_name_db . "%";
        $param_values[] = &$search_customer_name_like;
    }

    $where_sql = "WHERE " . implode(" AND ", $where_clauses);

    // 1. ดึงข้อมูลสรุปการเบิกไม้ท่อนจาก cutting_batch
    $sql_summary = "
        SELECT
            rm.rm_id,
            rm.rm_code,
            rm.rm_type,
            rm.rm_thickness,
            rm.rm_width,
            rm.rm_length,
            rm.rm_m3,
            c.customer_name,
            SUM(cb.total_wood_logs * rl.main_input_qty) AS total_logs_issued,
            SUM(cb.total_wood_logs * rl.main_input_qty * rm.rm_m3) AS total_m3_issued
        FROM
            cutting_batch AS cb
        JOIN
            recipe_list AS rl ON cb.recipe_id = rl.recipe_id
        JOIN
            rm_wood_list AS rm ON rl.main_input_rm_id = rm.rm_id
        LEFT JOIN
            customer AS c ON rl.main_customer_id = c.customer_id
        {$where_sql}
        GROUP BY
            rm.rm_id, rm.rm_code, rm.rm_type, rm.rm_thickness, rm.rm_width, rm.rm_length, rm.rm_m3, c.customer_name
        ORDER BY
            rm.rm_code ASC, c.customer_name ASC;
    ";

    $stmt_summary = $conn->prepare($sql_summary);
    if ($stmt_summary) {
        // ใช้ call_user_func_array เพื่อ bind_param ด้วยตัวแปรอ้างอิง
        call_user_func_array([$stmt_summary, 'bind_param'], array_merge([$param_types], $param_values));
        $stmt_summary->execute();
        $result_summary = $stmt_summary->get_result();

        while ($row = $result_summary->fetch_assoc()) {
            $aggregated_data[] = $row;
        }
        $stmt_summary->close();
    } else {
        $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
    }

    $total_records = count($aggregated_data);
    $total_pages = ceil($total_records / $limit);

    // ตัดข้อมูลสำหรับหน้าปัจจุบัน
    $paginated_data = array_slice($aggregated_data, $offset, $limit);

} catch (Exception $e) {
    $_SESSION['error_message'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    error_log("Error in rm_issue_summary.php: " . $e->getMessage());
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สรุปการเบิกไม้ท่อน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Daterangepicker CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        .container {
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .table thead th {
            background-color: #343a40;
            color: white;
            border-bottom: 2px solid #dee2e6;
        }
        .table-hover tbody tr:hover {
            background-color: #f2f2f2;
        }
        .pagination .page-link {
            border-radius: 5px;
            margin: 0 2px;
        }
        .pagination .page-item.active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<main class="container">
    <div class="d-flex justify-content-between align-items-center mb-4 mt-4">
        <h1><i class="fas fa-chart-bar"></i> สรุปการเบิกไม้ท่อน</h1>
    </div>

    <?php
    if (isset($_SESSION['success_message'])) {
        echo "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($_SESSION['success_message']) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
        unset($_SESSION['success_message']);
    }
    if (isset($_SESSION['error_message'])) {
        echo "<div class='alert alert-danger alert-dismissible fade show' role='alert'>" . htmlspecialchars($_SESSION['error_message']) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
        unset($_SESSION['error_message']);
    }
    ?>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">ตัวกรองข้อมูล</h5>
        </div>
        <div class="card-body">
            <form id="filter_form" method="GET" action="">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="date_range_picker" class="form-label">ช่วงวันที่:</label>
                        <input type="text" name="dates" id="date_range_picker" class="form-control" 
                               value="<?php echo htmlspecialchars($start_date . ' - ' . $end_date); ?>">
                        <input type="hidden" name="start_date" id="hidden_start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                        <input type="hidden" name="end_date" id="hidden_end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="search_rm_code" class="form-label">รหัสไม้ท่อน:</label>
                        <input type="text" name="search_rm_code" id="search_rm_code" class="form-control" placeholder="ค้นหารหัสไม้ท่อน" value="<?php echo htmlspecialchars($search_rm_code); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="search_size" class="form-label">ขนาด (หนา x กว้าง x ยาว):</label>
                        <input type="text" name="search_size" id="search_size" class="form-control" placeholder="เช่น 10x200x500 หรือ 10x200x" value="<?php echo htmlspecialchars($search_size); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="search_customer_name" class="form-label">ลูกค้า:</label>
                        <input type="text" name="search_customer_name" id="search_customer_name" class="form-control" placeholder="ค้นหาชื่อลูกค้า" value="<?php echo htmlspecialchars($search_customer_name); ?>">
                    </div>
                    <div class="col-12 mt-3">
                        <button type="submit" class="btn btn-primary me-2"><i class="fas fa-filter"></i> กรองข้อมูล</button>
                        <a href="rm_issue_summary.php" class="btn btn-secondary"><i class="fas fa-sync-alt"></i> รีเซ็ตตัวกรอง</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">ผลสรุปการเบิกไม้ท่อน (ระหว่าง <?php echo htmlspecialchars($start_date); ?> ถึง <?php echo htmlspecialchars($end_date); ?>)</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>รหัสไม้ท่อน</th>
                            <th>ประเภทไม้</th>
                            <th>ขนาด (หนา x กว้าง x ยาว)</th>
                            <th>ปริมาตร/ท่อน (m³)</th>
                            <th>ลูกค้า</th>
                            <th>จำนวนเบิก (ท่อน)</th>
                            <th>ปริมาตรรวม (m³)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($paginated_data)) {
                            foreach ($paginated_data as $data) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($data['rm_code'] ?? 'N/A') . "</td>";
                                echo "<td>" . htmlspecialchars($data['rm_type'] ?? 'N/A') . "</td>";
                                echo "<td>" . htmlspecialchars($data['rm_thickness'] ?? 'N/A') . " x " . htmlspecialchars($data['rm_width'] ?? 'N/A') . " x " . htmlspecialchars($data['rm_length'] ?? 'N/A') . "</td>";
                                echo "<td>" . number_format($data['rm_m3'] ?? 0, 6) . "</td>";
                                echo "<td>" . htmlspecialchars($data['customer_name'] ?? 'N/A') . "</td>";
                                echo "<td>" . number_format($data['total_logs_issued'] ?? 0) . "</td>";
                                echo "<td>" . number_format($data['total_m3_issued'] ?? 0, 6) . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='7' class='text-center'>ไม่พบข้อมูลการเบิกไม้ท่อนในช่วงวันที่และตัวกรองที่เลือก</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php if ($total_pages > 1): ?>
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&search_rm_code=<?php echo urlencode($search_rm_code); ?>&search_size=<?php echo urlencode($search_size); ?>&search_customer_name=<?php echo urlencode($search_customer_name); ?>&page=<?php echo $page - 1; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>

                        <?php
                        // Ellipsis Pagination Logic
                        $start_page_display = max(1, $page - 2);
                        $end_page_display = min($total_pages, $page + 2);

                        if ($start_page_display > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?start_date=' . urlencode($start_date) . '&end_date=' . urlencode($end_date) . '&search_rm_code=' . urlencode($search_rm_code) . '&search_size=' . urlencode($search_size) . '&search_customer_name=' . urlencode($search_customer_name) . '&page=1">1</a></li>';
                            if ($start_page_display > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }

                        for ($i = $start_page_display; $i <= $end_page_display; $i++) {
                            echo '<li class="page-item ' . (($i == $page) ? 'active' : '') . '"><a class="page-link" href="?start_date=' . urlencode($start_date) . '&end_date=' . urlencode($end_date) . '&search_rm_code=' . urlencode($search_rm_code) . '&search_size=' . urlencode($search_size) . '&search_customer_name=' . urlencode($search_customer_name) . '&page=' . $i . '">' . $i . '</a></li>';
                        }

                        if ($end_page_display < $total_pages) {
                            if ($end_page_display < $total_pages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?start_date=' . urlencode($start_date) . '&end_date=' . urlencode($end_date) . '&search_rm_code=' . urlencode($search_rm_code) . '&search_size=' . urlencode($search_size) . '&search_customer_name=' . urlencode($search_customer_name) . '&page=' . $total_pages . '">' . $total_pages . '</a></li>';
                        }
                        ?>

                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&search_rm_code=<?php echo urlencode($search_rm_code); ?>&search_size=<?php echo urlencode($search_size); ?>&search_customer_name=<?php echo urlencode($search_customer_name); ?>&page=<?php echo $page + 1; ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery (required for daterangepicker) -->
<script type="text/javascript" src="https://cdn.jsdelivr.net/jquery/latest/jquery.min.js"></script>
<!-- Moment.js (required for daterangepicker) -->
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<!-- Daterangepicker JS -->
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

<script>
    $(function() {
        // Initialize daterangepicker
        $('#date_range_picker').daterangepicker({
            opens: 'left',
            locale: {
                format: 'YYYY-MM-DD',
                applyLabel: 'เลือก',
                cancelLabel: 'ยกเลิก',
                fromLabel: 'จาก',
                toLabel: 'ถึง',
                customRangeLabel: 'กำหนดเอง',
                daysOfWeek: ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'],
                monthNames: [
                    'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
                    'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
                ],
                firstDay: 1
            },
            ranges: {
               'วันนี้': [moment(), moment()],
               'เมื่อวาน': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
               '7 วันที่ผ่านมา': [moment().subtract(6, 'days'), moment()],
               '30 วันที่ผ่านมา': [moment().subtract(29, 'days'), moment()],
               'เดือนนี้': [moment().startOf('month'), moment().endOf('month')],
               'เดือนที่แล้ว': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            }
        }, function(start, end, label) {
            // Update hidden input fields
            $('#hidden_start_date').val(start.format('YYYY-MM-DD'));
            $('#hidden_end_date').val(end.format('YYYY-MM-DD'));
            // Submit the form to apply date range filter along with other search parameters
            $('#filter_form').submit(); 
        });
    });
</script>
</body>
</html>
