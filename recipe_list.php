<?php
// ภาษา: PHP
// ชื่อไฟล์: recipe_list.php

session_start();
include_once __DIR__ . '/config_db.php';
include_once __DIR__ . '/navbar.php';

// --- ดึงค่าการค้นหาจาก GET ---
$searchRmSize = trim($_GET['search_rm_size'] ?? '');
$searchPartSize = trim($_GET['search_part_size'] ?? '');
$searchHwSize = trim($_GET['search_hw_size'] ?? '');
$searchSwSize = trim($_GET['search_sw_size'] ?? '');

// --- เตรียมเงื่อนไข WHERE แบบไดนามิก ---
$whereClauses = [];
$params = [];
$types = '';

if ($searchRmSize !== '') {
  // กรองตามขนาดไม้ท่อน
  $whereClauses[] = "CONCAT(r.rm_thickness, ' x ', r.rm_width, ' x ', r.rm_length) LIKE ?";
  $params[] = "%{$searchRmSize}%";
  $types .= 's';
}
if ($searchPartSize !== '') {
  // กรองตามขนาดงานหลัก
  $whereClauses[] = "CONCAT(p.part_thickness, ' x ', p.part_width, ' x ', p.part_length) LIKE ?";
  $params[] = "%{$searchPartSize}%";
  $types .= 's';
}
if ($searchHwSize !== '') {
  // กรองตามขนาดหัวไม้
  $whereClauses[] = "CONCAT(h.hw_thickness, ' x ', h.hw_width, ' x ', h.hw_length) LIKE ?";
  $params[] = "%{$searchHwSize}%";
  $types .= 's';
}
if ($searchSwSize !== '') {
  // กรองตามขนาดเศษไม้
  $whereClauses[] = "CONCAT(s.sw_thickness, ' x ', s.sw_width, ' x ', s.sw_length) LIKE ?";
  $params[] = "%{$searchSwSize}%";
  $types .= 's';
}
$whereSql = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// --- ค่า Pagination ---
$perPage = 10;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $perPage;

// --- นับแถวทั้งหมด ตามเงื่อนไขค้นหา ---
$countSql = "
    SELECT COUNT(*)
    FROM recipe_list AS rl
    JOIN rm_wood_list     AS r ON rl.rm_id   = r.rm_id
    JOIN part_list        AS p ON rl.part_id = p.part_id
    LEFT JOIN hw_wood_list AS h ON rl.hw_id  = h.hw_id
    LEFT JOIN sw_wood_list AS s ON rl.sw_id  = s.sw_id
    {$whereSql}
";
$countStmt = $conn->prepare($countSql);
if ($types !== '') {
  // ผูกพารามิเตอร์สำหรับกรอง
  $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countStmt->bind_result($totalRows);
$countStmt->fetch();
$countStmt->close();

$totalPages = (int) ceil($totalRows / $perPage);

// --- ดึงข้อมูลพร้อมเงื่อนไขและ Pagination ---
$dataSql = "
    SELECT
      rl.recipe_id,
      r.rm_id, r.rm_thickness, r.rm_width, r.rm_length, r.rm_type, rl.rm_qty, rl.rm_comment,
      p.part_id, p.part_thickness, p.part_width, p.part_length, p.part_type, rl.part_qry AS part_qty, rl.part_cut, rl.part_split, rl.part_comment,
      h.hw_id, h.hw_thickness, h.hw_width, h.hw_length, h.hw_type, rl.hw_qty, rl.hw_cut, rl.hw_split, rl.hw_comment,
      s.sw_id, s.sw_thickness, s.sw_width, s.sw_length, s.sw_type, rl.sw_qty, rl.sw_cut, rl.sw_split, rl.sw_comment
    FROM recipe_list AS rl
    JOIN rm_wood_list     AS r ON rl.rm_id   = r.rm_id
    JOIN part_list        AS p ON rl.part_id = p.part_id
    LEFT JOIN hw_wood_list AS h ON rl.hw_id  = h.hw_id
    LEFT JOIN sw_wood_list AS s ON rl.sw_id  = s.sw_id
    {$whereSql}
    ORDER BY rl.recipe_id DESC
    LIMIT ?, ?
";
$dataStmt = $conn->prepare($dataSql);

// ผูกพารามิเตอร์ทั้งเงื่อนไขและ Pagination
$bindTypes = $types . 'ii';
$dataParams = array_merge($params, [$offset, $perPage]);
$dataStmt->bind_param($bindTypes, ...$dataParams);
$dataStmt->execute();
$result = $dataStmt->get_result();
?>


<div class="container mt-5">
  <h2 class="mb-3">สูตรการตัดผ่าไม้</h2>

  <!-- ฟอร์มค้นหา -->
  <form method="get" class="row g-3 mb-4">
    <div class="col-md-3">
      <input type="text" class="form-control" name="search_rm_size" placeholder="ไม้ท่อน (หนา x กว้าง x ยาว)"
        value="<?= htmlspecialchars($searchRmSize) ?>">
    </div>
    <div class="col-md-3">
      <input type="text" class="form-control" name="search_part_size" placeholder="งานหลัก (หนา x กว้าง x ยาว)"
        value="<?= htmlspecialchars($searchPartSize) ?>">
    </div>
    <div class="col-md-3">
      <input type="text" class="form-control" name="search_hw_size" placeholder="หัวไม้ (หนา x กว้าง x ยาว)"
        value="<?= htmlspecialchars($searchHwSize) ?>">
    </div>
    <div class="col-md-3">
      <input type="text" class="form-control" name="search_sw_size" placeholder="เศษไม้ (หนา x กว้าง x ยาว)"
        value="<?= htmlspecialchars($searchSwSize) ?>">
    </div>
    <div class="col-md-1">
      <button type="submit" class="btn btn-primary w-100">ค้นหา</button>
    </div>
  </form>

  <!-- ปุ่มเพิ่มสูตร -->
  <button class="btn btn-success mb-4" data-bs-toggle="modal" data-bs-target="#recipeModal">เพิ่มสูตรการตัดผ่า</button>

  <table class="table table-striped table-bordered text-center align-middle">
    <thead class="table-dark">
      <tr>
        <th>ไม้ท่อน<br><small>ขนาด | ประเภท | จำนวน</small></th>
        <th>งานหลัก<br><small>ขนาด | ประเภท | จำนวน</small></th>
        <th>หัวไม้<br><small>ขนาด | ประเภท | จำนวน</small></th>
        <th>เศษไม้<br><small>ขนาด | ประเภท | จำนวน</small></th>
        <th>จัดการ</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td>
            <?= htmlspecialchars("{$row['rm_thickness']} x {$row['rm_width']} x {$row['rm_length']} | {$row['rm_type']} | {$row['rm_qty']}") ?>
          </td>
          <td>
            <?= htmlspecialchars("{$row['part_thickness']} x {$row['part_width']} x {$row['part_length']} | {$row['part_type']} | {$row['part_qty']}") ?>
          </td>
          <td>
            <?php if ($row['hw_thickness'] !== null)
              echo htmlspecialchars("{$row['hw_thickness']} x {$row['hw_width']} x {$row['hw_length']} | {$row['hw_type']} | {$row['hw_qty']}");
            else
              echo '<em>ไม่มี</em>'; ?>
          </td>
          <td>
            <?php if ($row['sw_thickness'] !== null)
              echo htmlspecialchars("{$row['sw_thickness']} x {$row['sw_width']} x {$row['sw_length']} | {$row['sw_type']} | {$row['sw_qty']}");
            else
              echo '<em>ไม่มี</em>'; ?>
          </td>
          <td>
            <!-- ปรับเป็น data-id เพื่อให้ตรงกับ JS -->
            <a href="#" class="btn btn-sm btn-info btn-detail me-1" data-bs-toggle="modal"
              data-bs-target="#recipeDetailsModal" data-id="<?= $row['recipe_id'] ?>">
              รายละเอียด
            </a>

            <!-- ปุ่มแก้ไข -->
            <button class="btn btn-sm btn-primary me-1" data-bs-toggle="modal" data-bs-target="#recipeModal"
              data-recipe-id="<?= $row['recipe_id'] ?>" data-rm-id="<?= $row['rm_id'] ?>"
              data-rm-qty="<?= $row['rm_qty'] ?>"
              data-rm-comment="<?= htmlspecialchars($row['rm_comment'], ENT_QUOTES) ?>"
              data-part-id="<?= $row['part_id'] ?>" data-part-qty="<?= $row['part_qty'] ?>"
              data-part-cut="<?= $row['part_cut'] ?>" data-part-split="<?= $row['part_split'] ?>"
              data-part-comment="<?= htmlspecialchars($row['part_comment'], ENT_QUOTES) ?>"
              data-hw-id="<?= $row['hw_id'] ?>" data-hw-qty="<?= $row['hw_qty'] ?>" data-hw-cut="<?= $row['hw_cut'] ?>"
              data-hw-split="<?= $row['hw_split'] ?>"
              data-hw-comment="<?= htmlspecialchars($row['hw_comment'], ENT_QUOTES) ?>" data-sw-id="<?= $row['sw_id'] ?>"
              data-sw-qty="<?= $row['sw_qty'] ?>" data-sw-cut="<?= $row['sw_cut'] ?>"
              data-sw-split="<?= $row['sw_split'] ?>"
              data-sw-comment="<?= htmlspecialchars($row['sw_comment'], ENT_QUOTES) ?>">
              แก้ไข
            </button>
            <!-- ปุ่มลบ -->
            <form method="post" action="recipe_delete.php" style="display:inline;"
              onsubmit="return confirm('ยืนยันลบสูตรนี้?');">
              <input type="hidden" name="recipe_id" value="<?= $row['recipe_id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger">ลบ</button>
            </form>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <?php
  // ฟังก์ชันแสดง Pagination พร้อมค่าส่งผ่าน URL ค้นหา
  function ellipsis_pagination($totalPages, $currentPage, $queryParams)
  {
    $adj = 2;
    if ($totalPages <= 1)
      return;
    echo '<nav><ul class="pagination justify-content-center">';
    // ปุ่มหน้าแรก
    if ($currentPage > 1)
      echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($queryParams + ['page' => 1]) . '">&laquo;</a></li>';
    for ($i = 1; $i <= $totalPages; $i++) {
      if ($i === 1 || $i === $totalPages || ($i >= $currentPage - $adj && $i <= $currentPage + $adj)) {
        $active = $i === $currentPage ? ' active' : '';
        echo '<li class="page-item' . $active . '"><a class="page-link" href="?' . http_build_query($queryParams + ['page' => $i]) . '">' . $i . '</a></li>';
      } elseif ($i === 2 && $currentPage - $adj > 2) {
        echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
      } elseif ($i === $totalPages - 1 && $currentPage + $adj < $totalPages - 1) {
        echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
      }
    }
    if ($currentPage < $totalPages)
      echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($queryParams + ['page' => $totalPages]) . '">&raquo;</a></li>';
    echo '</ul></nav>';
  }
  // เตรียมพารามิเตอร์เดิมสำหรับ Pagination
  $queryParams = [
    'search_rm_size' => $searchRmSize,
    'search_part_size' => $searchPartSize,
    'search_hw_size' => $searchHwSize,
    'search_sw_size' => $searchSwSize
  ];
  ellipsis_pagination($totalPages, $currentPage, $queryParams);
  ?>

  <!-- โหลดโค้ด popup จาก recipe_modals.php -->
  <?php include_once __DIR__ . '/recipe_modals.php'; ?>
  <!-- เรียก modal แสดงรายละเอียด -->
  <?php include_once __DIR__ . '/recipe_details_modals.php'; ?>

</div>
</body>


<!-- Select2 JS (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> -->

</html>