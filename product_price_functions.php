<?php
// product_price_functions.php

// ฟังก์ชันสำหรับเพิ่มข้อมูลราคาสินค้า โดยป้องกัน prod_id ซ้ำกัน
function addProductPrice($conn, $prod_id, $price_value)
{
    $count = 0;
    // ตรวจสอบว่ามี prod_id นี้ในตาราง product_price อยู่แล้วหรือไม่
    $check_stmt = $conn->prepare("SELECT COUNT(*) FROM product_price WHERE prod_id = ?");
    if (!$check_stmt) {
        return "Error: " . $conn->error;
    }
    $check_stmt->bind_param("i", $prod_id);
    $check_stmt->execute();
    $check_stmt->bind_result($count);
    $check_stmt->fetch();
    $check_stmt->close();

    if ($count > 0) {
        return "Duplicate";
    } else {
        $stmt = $conn->prepare("INSERT INTO product_price (prod_id, price_value) VALUES (?, ?)");
        if ($stmt) {
            $stmt->bind_param("id", $prod_id, $price_value);
            $stmt->execute();
            $stmt->close();
            return true;
        } else {
            return "Error: " . $conn->error;
        }
    }
}

// ฟังก์ชันสำหรับบันทึกประวัติการเปลี่ยนแปลงราคา
function logProductPriceHistory($conn, $price_id, $old_price, $new_price)
{
    // ดึง user_id จาก session (ถ้าไม่มี ให้เป็น null)
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    // เตรียม statement สำหรับ insert ข้อมูลประวัติการเปลี่ยนแปลง
    $stmt = $conn->prepare("INSERT INTO product_price_history (price_id, change_from, change_to, user_id) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("iddi", $price_id, $old_price, $new_price, $user_id);
        $stmt->execute();
        $stmt->close();
        return true;
    } else {
        return "Error logging price history: " . $conn->error;
    }
}

// ฟังก์ชันสำหรับอัปเดตข้อมูลราคาสินค้า (แก้ไข)
function updateProductPrice($conn, $price_id, $prod_id, $price_value)
{
    $count = 0;
    $check_stmt = $conn->prepare("SELECT COUNT(*) FROM product_price WHERE prod_id = ? AND price_id <> ?");
    if (!$check_stmt) {
        return "Error: " . $conn->error;
    }
    $check_stmt->bind_param("ii", $prod_id, $price_id);
    $check_stmt->execute();
    $check_stmt->bind_result($count);
    $check_stmt->fetch();
    $check_stmt->close();
    if ($count > 0) {
        return "Duplicate product price exists for this product.";
    }

    $old_price = null;
    $select_stmt = $conn->prepare("SELECT price_value FROM product_price WHERE price_id = ?");
    if ($select_stmt) {
        $select_stmt->bind_param("i", $price_id);
        $select_stmt->execute();
        $select_stmt->bind_result($old_price);
        $select_stmt->fetch();
        $select_stmt->close();
    }

    $stmt = $conn->prepare("UPDATE product_price SET price_value = ?, date_update = NOW() WHERE price_id = ?");
    if ($stmt) {
        $stmt->bind_param("di", $price_value, $price_id);
        $stmt->execute();
        $stmt->close();

        if ($old_price !== null && $old_price != $price_value) {
            $log_result = logProductPriceHistory($conn, $price_id, $old_price, $price_value);
            if ($log_result !== true) {
                return $log_result;
            }
        }
        return true;
    } else {
        return "Error: " . $conn->error;
    }
}

// ฟังก์ชันสำหรับลบข้อมูลราคาสินค้า
function deleteProductPrice($conn, $price_id)
{
    $stmt = $conn->prepare("DELETE FROM product_price WHERE price_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $price_id);
        $stmt->execute();
        $stmt->close();
        return true;
    } else {
        return "Error: " . $conn->error;
    }
}

// ดึงข้อมูลราคาสินค้าพร้อม JOIN ตาราง prod_list และรองรับการค้นหา
function getAllProductPrices($conn, $search = '', $start = 0, $limit = 5) {
    $sql = "SELECT pp.price_id, pp.prod_id, pl.prod_code, pl.prod_partno, pl.code_cus_size, 
                   pp.price_value, pp.date_update
            FROM product_price pp
            LEFT JOIN prod_list pl ON pp.prod_id = pl.prod_id";
    
    if (!empty($search)) {
        $searchEscaped = $conn->real_escape_string($search);
        $sql .= " WHERE (pl.prod_code LIKE '%$searchEscaped%'
                   OR pl.prod_partno LIKE '%$searchEscaped%'
                   OR pl.code_cus_size LIKE '%$searchEscaped%')";
    }

    $sql .= " ORDER BY pp.price_id DESC
              LIMIT $start, $limit";
    
    return $conn->query($sql);
}

// แสดงรายการราคาสินค้าในตาราง (รวมการแสดง modal ประวัติการแก้ไขราคา)
function displayProductPriceList($result) {
    $modals = '';
    ?>
    <h3 class="mt-3">ราคาค่าจ้างผลิต</h3>
    <table class="table table-bordered mt-3 mb-3">
      <thead class="table-light">
        <tr>
          <th>PRODUCT CODE FG/PART NO.</th>
          <th>CODE SIZE</th>
          <th>PRICE VALUE</th>
          <th>DATE UPDATE</th>
          <th>ACTION</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
          <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
              <td><?php echo htmlspecialchars($row['prod_code'] . " / " . $row['prod_partno']); ?></td>
              <td><?php echo htmlspecialchars($row['code_cus_size']); ?></td>
              <td><?php echo number_format($row['price_value'], 2); ?></td>
              <td><?php echo htmlspecialchars($row['date_update']); ?></td>
              <td>
                <button class="btn btn-sm btn-warning edit-btn"
                        data-price-id="<?php echo $row['price_id']; ?>"
                        data-prod-id="<?php echo $row['prod_id']; ?>"
                        data-price-value="<?php echo $row['price_value']; ?>">
                  แก้ไขราคา
                </button>
                <form method="POST" action="product_price.php" style="display:inline;"
                      onsubmit="return confirm('คุณต้องการลบราคาออกจากระบบ นี้ใช่หรือไม่');">
                  <input type="hidden" name="action" value="deletePrice">
                  <input type="hidden" name="price_id" value="<?php echo $row['price_id']; ?>">
                  <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($_SESSION['form_token'] ?? ''); ?>">
                  <button type="submit" class="btn btn-sm btn-danger">ลบราคา</button>
                </form>
                <button class="btn btn-sm btn-info"
                        data-bs-toggle="modal"
                        data-bs-target="#historyModal<?php echo $row['price_id']; ?>">
                  ประวัติการแก้ไขราคา
                </button>
              </td>
            </tr>
            <?php
            ob_start();
            displayHistoryModal($row['price_id']);
            $modals .= ob_get_clean();
            ?>
          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td colspan="4" class="text-center">No product prices found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
    <?php
    echo $modals;
}

// ดึงประวัติการเปลี่ยนแปลงราคาสินค้า
function getPriceHistory($conn, $price_id) {
    $stmt = $conn->prepare("
        SELECT pph.price_log_id,
               pph.change_from,
               pph.change_to,
               pph.change_date,
               pph.user_id,
               pu.thainame
        FROM product_price_history pph
        LEFT JOIN prod_user pu ON pph.user_id = pu.user_id
        WHERE pph.price_id = ?
        ORDER BY pph.change_date DESC
    ");
    if ($stmt) {
        $stmt->bind_param("i", $price_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $history = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $history;
    }
    return [];
}

// ดึงจำนวนรายการราคาสินค้ารวมถึงรองรับการค้นหา
function getTotalProductPrices($conn, $search = '') {
    $sql = "SELECT COUNT(*) AS cnt
            FROM product_price pp
            LEFT JOIN prod_list pl ON pp.prod_id = pl.prod_id";
    
    if (!empty($search)) {
        $searchEscaped = $conn->real_escape_string($search);
        $sql .= " WHERE (pl.prod_code LIKE '%$searchEscaped%'
                   OR pl.prod_partno LIKE '%$searchEscaped%'
                   OR pl.code_cus_size LIKE '%$searchEscaped%')";
    }
    
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        return (int)$row['cnt'];
    }
    return 0;
}
?>
