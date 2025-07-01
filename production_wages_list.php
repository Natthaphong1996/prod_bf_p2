<?php
include('config_db.php');
include('production_wages_function.php');

// 1. ดึงค่าจาก GET
$search_value = isset($_GET['search']) ? trim($_GET['search']) : '';
$daterange = isset($_GET['daterange']) ? $_GET['daterange'] : '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action']) && $_POST['action'] === 'cancel'
    && isset($_POST['pw_id'])
) {
    $pw_id = $_POST['pw_id'];
    $ok = cancelInvoice($conn, $pw_id);
    $msg = $ok
        ? "ยกเลิก Invoice $pw_id เรียบร้อยแล้ว"
        : "ไม่สามารถยกเลิก Invoice $pw_id ได้";
    header("Location: production_wages_list.php?message=" . urlencode($msg));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm' && isset($_POST['pw_id'])) {
    $pw_id = $_POST['pw_id'];
    $ok = confirmInvoice($conn, $pw_id);
    $msg = $ok
        ? "ยืนยัน Invoice $pw_id เรียบร้อยแล้ว"
        : "ไม่สามารถยืนยัน Invoice $pw_id ได้";
    // ส่งกลับพร้อมข้อความ
    header("Location: production_wages_list.php?message=" . urlencode($msg));
    exit();
}

// ตรวจสอบการส่ง POST สำหรับการสร้าง Invoice
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_ids'])) {
    $success = false;
    if (!empty($_POST['job_ids'])) {
        $resultInvoice = createInvoice($conn, $_POST['job_ids']);
        if ($resultInvoice !== false) {
            $message = "สร้างใบเบิกค่าจ้างผลิตสำเร็จ! เลขที่ใบเบิกค่าจ้างผลิต = $resultInvoice)";
            $success = true;
        } else {
            $message = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
        }
    } else {
        $message = "ไม่ได้เลือก Job ใด ๆ";
    }
    // เปลี่ยนเส้นทางกลับไปยัง production_wages_list.php โดยแนบข้อความผ่าน GET
    header("Location: production_wages_list.php?message=" . urlencode($message) . "&success=" . ($success ? "1" : "0"));
    exit();
}

// รับข้อความจาก GET (กรณีย้ายกลับมาตาม redirect)
$message = $_GET['message'] ?? '';
$success = (isset($_GET['success']) && $_GET['success'] === '1');

// 2. pagination
$items_per_page = 50;
$current_page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $items_per_page;

// 3. แยก date range
$start_date = $end_date = '';
if ($daterange) {
    list($sd, $ed) = explode(' - ', $daterange);
    $start_date = $sd . ' 00:00:00';
    $end_date = $ed . ' 23:59:59';
}

// 4. ดึงข้อมูล
$wages_result = getProductionWagesList(
    $conn,
    $current_page,
    $items_per_page,
    $search_value,
    $start_date,
    $end_date
);

// 5. นับทั้งหมด
$total_count = getProductionWagesCount($conn, $search_value, $start_date, $end_date);
$total_pages = $total_count ? ceil($total_count / $items_per_page) : 1;

// ฟังก์ชันดึงยอดรวมทั้งหมด (ใช้ filter เดียวกับที่แสดงหน้า)
function getTotalWageSum($conn, $search_value, $start_date, $end_date) {
    $where = "WHERE status != 'ยกเลิก'";
    $params = [];
    $types = "";

    // เพิ่ม search filter ถ้ามี
    if ($search_value) {
        $where .= " AND production_wage_id LIKE ?";
        $params[] = '%' . $search_value . '%';
        $types .= "s";
    }

    // เพิ่ม date range filter ถ้ามี
    if ($start_date && $end_date) {
        $where .= " AND date_create BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
        $types .= "ss";
    }

    $sql = "SELECT SUM(total_wage) AS total_sum FROM production_wages $where";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['total_sum'] ?? 0;
}

// เรียกฟังก์ชัน
$total_sum = getTotalWageSum($conn, $search_value, $start_date, $end_date);

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Production Wages List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" rel="stylesheet">
</head>

<body>
    <?php include('navbar.php'); ?>
    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>รายการใบเบิกค่าจ้างผลิต</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createInvoiceModal">
                สร้างใบเบิกค่าจ้างผลิต
            </button>
        </div>

        <!-- ฟอร์มค้นหา -->
        <form method="GET" class="mb-3">
            <div class="row g-2">
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control" placeholder="ค้นหา เลขที่ใบเบิกค่าจ้างผลิต..."
                        value="<?= htmlspecialchars($search_value) ?>">
                </div>
                <div class="col-md-3">
                    <input type="text" name="daterange" class="form-control" id="daterange"
                        value="<?= htmlspecialchars($daterange) ?>" autocomplete="off">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">ค้นหา</button>
                </div>
            </div>
        </form>

        <h3 class="mb-3">
            <b>ยอดรวมค่าจ้างผลิต :</b>
            <?= number_format($total_sum, decimals: 2) ?> บาท
        </h3>

        <!-- ข้อความการทำงาน -->
        <?php if ($message): ?>
            <div class="alert <?= $success ? 'alert-success' : 'alert-danger' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- ตาราง -->
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>เลขที่ใบเบิกค่าจ้างผลิต</th>
                    <th>จุดประกอบงาน</th>
                    <th class="text-end">ยอดรวม</th>
                    <th>วันที่ออกเอกสาร</th>
                    <th>สถานะ</th>
                    <th>ดำเนินการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($wages_result)): ?>
                    <?php foreach ($wages_result as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['production_wage_id']) ?></td>
                            <td><?= htmlspecialchars($row['assembly_point']) ?></td>
                            <td class="text-end"><?= number_format($row['total_wage'], 2) ?> บาท</td>
                            <td><?= htmlspecialchars($row['date_create']) ?></td>
                            <td><?= htmlspecialchars($row['status']) ?></td>
                            <td>
                                <button class="btn btn-info btn-sm detailBtn"
                                    data-id="<?= htmlspecialchars($row['production_wage_id']) ?>">รายละเอียด</button>
                                <a href="generate_invoice_pdf.php?id=<?= htmlspecialchars($row['production_wage_id']) ?>"
                                    class="btn btn-secondary btn-sm" target="_blank">PDF</a>
                                <?php if ($row['status'] === 'รอยืนยัน'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="confirm">
                                        <input type="hidden" name="pw_id"
                                            value="<?= htmlspecialchars($row['production_wage_id']) ?>">
                                        <button type="submit" class="btn btn-success btn-sm"
                                            onclick="return confirm('ยืนยัน Invoice <?= htmlspecialchars($row['production_wage_id']) ?> ?');">ยืนยัน</button>
                                    </form>
                                    <form method="POST" class="d-inline ms-1">
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="pw_id"
                                            value="<?= htmlspecialchars($row['production_wage_id']) ?>">
                                        <button type="submit" class="btn btn-danger btn-sm"
                                            onclick="return confirm('ยกเลิก Invoice <?= htmlspecialchars($row['production_wage_id']) ?> ?');">ยกเลิก</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">ไม่พบข้อมูล</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <nav>
            <ul class="pagination justify-content-center">
                <!-- Prev -->
                <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link"
                        href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page - 1])) ?>">
                        &laquo;
                    </a>
                </li>
                <?php for ($i = 1; $i <= min(5, $total_pages); $i++): ?>
                    <li class="page-item <?= $current_page == $i ? 'active' : '' ?>">
                        <a class="page-link"
                            href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($total_pages > 5): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                    <li class="page-item <?= $current_page == $total_pages ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>">
                            <?= $total_pages ?>
                        </a>
                    </li>
                <?php endif; ?>
                <!-- Next -->
                <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link"
                        href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page + 1])) ?>">
                        &raquo;
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <?php include('production_modal.php'); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script>
        $(function () {
            $('#daterange').daterangepicker({
                locale: { format: 'YYYY-MM-DD', separator: ' - ' },
                opens: 'left'
            });
            $('.detailBtn').click(function () {
                $.get('production_wages_detailModal.php', { id: $(this).data('id') })
                    .done(resp => {
                        $('#detailContent').html(resp);
                        $('#detailModal').modal('show');
                    });
            });
        });
    </script>
</body>

</html>