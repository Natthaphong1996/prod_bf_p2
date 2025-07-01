<?php
// ภาษา: PHP
// ชื่อไฟล์: sw_list.php

session_start();
include_once __DIR__ . '/config_db.php';
// include_once __DIR__ . '/navbar.php';

// --- รับค่าการค้นหาจาก GET ---
$searchCode = trim($_GET['search_code'] ?? '');
$searchType = trim($_GET['search_type'] ?? '');

// --- Pagination Settings ---
$perPage     = 30;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset      = ($currentPage - 1) * $perPage;

// ฟังก์ชันตรวจสอบรหัสซ้ำ
function isDuplicateCode($conn, $code, $excludeId = null) {
    $count = 0;
    $sql = 'SELECT COUNT(*) FROM sw_wood_list WHERE sw_code = ?';
    if ($excludeId) $sql .= ' AND sw_id != ?';
    $stmt = $conn->prepare($sql);
    if ($excludeId) $stmt->bind_param('si', $code, $excludeId);
    else           $stmt->bind_param('s', $code);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count > 0;
}

// --- Process CRUD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $msg    = '';

    if ($action === 'add') {
        $code      = trim($_POST['sw_code']);
        $type      = $_POST['sw_type'];
        $thickness = (int) $_POST['sw_thickness'];
        $width     = (int) $_POST['sw_width'];
        $length    = (int) $_POST['sw_length'];
        // รองรับทศนิยม 8 ตำแหน่ง
        $m3        = round((float) $_POST['sw_m3'], 8);

        if (isDuplicateCode($conn, $code)) {
            $msg = 'รหัสซ้ำในระบบ';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO sw_wood_list (sw_code, sw_type, sw_thickness, sw_width, sw_length, sw_m3) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('ssiiid', $code, $type, $thickness, $width, $length, $m3);
            $stmt->execute();
            $stmt->close();
            $msg = 'เพิ่มข้อมูลเรียบร้อย';
        }

    } elseif ($action === 'edit') {
        $id        = (int) $_POST['sw_id'];
        $code      = trim($_POST['sw_code']);
        $type      = $_POST['sw_type'];
        $thickness = (int) $_POST['sw_thickness'];
        $width     = (int) $_POST['sw_width'];
        $length    = (int) $_POST['sw_length'];
        // รองรับทศนิยม 8 ตำแหน่ง
        $m3        = round((float) $_POST['sw_m3'], 8);

        if (isDuplicateCode($conn, $code, $id)) {
            $msg = 'รหัสซ้ำในระบบ';
        } else {
            $stmt = $conn->prepare(
                "UPDATE sw_wood_list SET sw_code=?, sw_type=?, sw_thickness=?, sw_width=?, sw_length=?, sw_m3=? WHERE sw_id=?"
            );
            $stmt->bind_param('ssiiidi', $code, $type, $thickness, $width, $length, $m3, $id);
            $stmt->execute();
            $stmt->close();
            $msg = 'แก้ไขข้อมูลเรียบร้อย';
        }

    } elseif ($action === 'delete') {
        $id = (int) $_POST['sw_id'];
        $stmt = $conn->prepare("DELETE FROM sw_wood_list WHERE sw_id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $msg = 'ลบข้อมูลเรียบร้อย';
    }

    echo "<script>window.onload=function(){ alert('{$msg}');
        window.location='sw_list.php?search_code=".urlencode($searchCode)."&search_type=".urlencode($searchType)."'; }</script>";
    exit;
}

// --- Build WHERE Clause ---
$where  = [];
$params = [];
$types  = '';
if ($searchCode !== '') {
    $where[]  = '(
        sw_code LIKE ?
        OR CONCAT(sw_thickness, "x", sw_width, "x", sw_length) LIKE ?
        OR CONCAT(sw_thickness, " x ", sw_width, " x ", sw_length) LIKE ?
    )';
    $like     = "%{$searchCode}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= 'sss';
}
if ($searchType === 'K' || $searchType === 'G') {
    $where[]  = 'sw_type = ?';
    $params[] = $searchType;
    $types   .= 's';
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

// --- Count Total for Pagination ---
$totalSql  = "SELECT COUNT(*) AS total FROM sw_wood_list" . $whereSql;
$stmtTotal = $conn->prepare($totalSql);
if ($params) $stmtTotal->bind_param($types, ...$params);
$stmtTotal->execute();
$totalItems = $stmtTotal->get_result()->fetch_assoc()['total'];
$totalPages = (int) ceil($totalItems / $perPage);
$stmtTotal->close();

// --- Fetch Page Data ---
$sql = "SELECT * FROM sw_wood_list" . $whereSql . " ORDER BY sw_code ASC LIMIT ?, ?";
$params[]   = $offset;
$params[]   = $perPage;
$types     .= 'ii';
$stmtData   = $conn->prepare($sql);
$stmtData->bind_param($types, ...$params);
$stmtData->execute();
$resultAll  = $stmtData->get_result();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5.3 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body{padding-bottom:80px;}</style>
    <title>จัดการ SW Wood List</title>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container mt-4">
    <h1 class="mb-3">จัดการ SW Wood List</h1>

    <!-- Search & Add -->
    <form method="get" class="row g-3 mb-3">
        <div class="col-md-4">
            <input type="text" name="search_code" class="form-control" placeholder="ค้นหา Code หรือ ขนาด เช่น 10x20x30" value="<?=htmlspecialchars($searchCode)?>">
        </div>
        <div class="col-md-3">
            <select name="search_type" class="form-select">
                <option value="" <?= $searchType===''?'selected':''?>>ทั้งหมด</option>
                <option value="K" <?= $searchType==='K'?'selected':''?>>ไม้อบ</option>
                <option value="G" <?= $searchType==='G'?'selected':''?>>ไม้ยังไม่ได้อบ</option>
            </select>
        </div>
        <div class="col-md-auto">
            <button class="btn btn-primary">ค้นหา</button>
        </div>
        <div class="col-md-auto">
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalForm" onclick="openAddModal()">+ เพิ่ม SW</button>
        </div>
    </form>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-bordered text-center">
            <thead class="table-light">
                <tr>
                    <th>Code</th>
                    <th>Type</th>
                    <th>ขนาด (T x W x L)</th>
                    <th>M³</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
            <?php while($row = $resultAll->fetch_assoc()):
                $typeLabel = $row['sw_type']==='K'? 'ไม้อบ' : 'ไม้ยังไม่ได้อบ';
                $sizeLabel = "{$row['sw_thickness']} x {$row['sw_width']} x {$row['sw_length']}";
            ?>
            <tr>
                <td><?=htmlspecialchars($row['sw_code'])?></td>
                <td><?=$typeLabel?></td>
                <td><?=$sizeLabel?></td>
                <td><?=number_format($row['sw_m3'], 8)?></td>
                <td>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalForm" onclick='openEditModal(<?=json_encode($row)?>)'>แก้ไข</button>
                    <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalDelete" onclick="openDeleteModal(<?=$row['sw_id']?>)">ลบ</button>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Ellipsis Pagination -->
    <?php if($totalPages > 1): ?>
    <nav aria-label="Page navigation">
      <ul class="pagination justify-content-center">
        <li class="page-item <?= $currentPage<=1?'disabled':'' ?>">
          <a class="page-link" href="?search_code=<?=urlencode($searchCode)?>&search_type=<?=urlencode($searchType)?>&page=<?=$currentPage-1?>">Previous</a>
        </li>
        <?php if($currentPage>3): ?>
          <li class="page-item"><a class="page-link" href="?search_code=<?=urlencode($searchCode)?>&search_type=<?=urlencode($searchType)?>&page=1">1</a></li>
          <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
        <?php endif; ?>
        <?php for($i=max(1,$currentPage-2); $i<=min($totalPages,$currentPage+2); $i++): ?>
          <li class="page-item <?= $i===$currentPage?'active':'' ?>">
            <a class="page-link" href="?search_code=<?=urlencode($searchCode)?>&search_type=<?=urlencode($searchType)?>&page=<?=$i?>"><?=$i?></a>
          </li>
        <?php endfor; ?>
        <?php if($currentPage<$totalPages-2): ?>
          <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
          <li class="page-item"><a class="page-link" href="?search_code=<?=urlencode($searchCode)?>&search_type=<?=urlencode($searchType)?>&page=<?=$totalPages?>"><?=$totalPages?></a></li>
        <?php endif; ?>
        <li class="page-item <?= $currentPage>=$totalPages?'disabled':'' ?>">
          <a class="page-link" href="?search_code=<?=urlencode($searchCode)?>&search_type=<?=urlencode($searchType)?>&page=<?=$currentPage+1?>">Next</a>
        </li>
      </ul>
    </nav>
    <?php endif; ?>

</div>

<!-- รวมโมดอล -->
<?php include 'modals_sw.php'; ?>

<!-- รวม Footer -->
<?php include 'footer.php'; ?>

</body>
</html>

<?php
$stmtData->close();
$conn->close();
?>
