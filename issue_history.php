<?php
// ภาษา: PHP
// ชื่อไฟล์: issue_history.php
// หน้าที่: แสดงประวัติการเบิกทั้งหมด พร้อมปุ่มสำหรับพิมพ์ PDF

session_start();
require_once __DIR__ . '/config_db.php';

// --- ดึงข้อมูลประวัติการเบิกทั้งหมด ---
// ไม่จำเป็นต้อง JOIN หรือเตรียม lookup map ในหน้านี้แล้ว
// ทำให้โค้ดกระชับและเร็วขึ้น
$sql_logs = "
    SELECT 
        log.log_id,
        log.issued_at,
        pu.thainame AS issuer_name
    FROM 
        log_issue_history AS log
    LEFT JOIN 
        prod_user AS pu ON log.issued_by_user_id = pu.user_id
    ORDER BY 
        log.log_id DESC;
";
$result_logs = $conn->query($sql_logs);

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติการเบิกไม้ท่อน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<main class="container">
    <div class="d-flex justify-content-between align-items-center mb-4 mt-4">
        <h1><i class="fas fa-history"></i> ประวัติการเบิกไม้ท่อน</h1>
    </div>

    <div class="card">
        <div class="card-header">
            รายการเบิกล่าสุด
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>เลขที่ใบเบิก</th>
                            <th>วันที่เบิก</th>
                            <th>ผู้เบิก</th>
                            <th class="text-center">ดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result_logs && $result_logs->num_rows > 0): ?>
                            <?php while($log = $result_logs->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?php echo $log['log_id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($log['issued_at']); ?></td>
                                    <td><?php echo htmlspecialchars($log['issuer_name'] ?? 'N/A'); ?></td>
                                    <td class="text-center">
                                        <!-- [แก้ไข] เพิ่มปุ่มสำหรับพิมพ์ PDF -->
                                        <a href="log_issue_pdf.php?log_id=<?php echo $log['log_id']; ?>" class="btn btn-danger btn-sm" target="_blank" title="พิมพ์ใบเบิก PDF">
                                            <i class="fas fa-file-pdf"></i> PDF
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted p-4">
                                    <p class="mb-0">ยังไม่มีประวัติการเบิก</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php 
$conn->close();
include __DIR__ . '/footer.php';
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
