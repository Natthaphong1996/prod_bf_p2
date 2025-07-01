<?php
ob_start();  // เริ่ม Output Buffering
session_start();
include('config_db.php');
// Include ฟังก์ชันที่เกี่ยวกับข้อมูลสินค้าและ BOM
require_once 'product_functions.php';

// Include ฟังก์ชันที่เกี่ยวกับการจัดการ wood issue
require_once 'wood_issue_functions.php';

require_once 'production_wages_function.php';

// Include ฟังก์ชันที่เกี่ยวกับงานเสร็จ
require_once 'job_complete_functions.php';

// Include ฟังก์ชันที่เกี่ยวกับการจัดการราคาสินค้าและประวัติการแก้ไขราคา
require_once 'product_price_functions.php';
include('navbar.php');
include('modals.php');

// สร้าง Token หากยังไม่มี (สำหรับป้องกันการส่งข้อมูลซ้ำ)
if (!isset($_SESSION['form_token'])) {
    $_SESSION['form_token'] = bin2hex(random_bytes(16));
}

// ================== ตั้งค่าการแบ่งหน้า ==================
$limit = 50; // จำนวนรายการต่อหน้า
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) { $current_page = 1; }
$start = ($current_page - 1) * $limit;

// ================== รับค่า Search ==================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_query = '';
if (!empty($search)) {
    $search_query .= '&search=' . urlencode($search);
}

// ================== ดึงข้อมูลตามเงื่อนไขและ LIMIT ==================
$productPrices = getAllProductPrices($conn, $search, $start, $limit);
$total_records = getTotalProductPrices($conn, $search);
$total_pages = ceil($total_records / $limit);
if ($total_pages < 1) { $total_pages = 1; }

// ================== จัดการ POST Action ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // ใช้ filter_input() เพื่อดึงค่า token จาก POST โดยไม่เกิด warning
    $form_token = filter_input(INPUT_POST, 'form_token', FILTER_SANITIZE_STRING);
    if ($form_token !== $_SESSION['form_token']) {
        echo "Invalid or expired form submission.";
        exit;
    }
    
    if ($_POST['action'] === 'addPrice') {
        $result = addProductPrice($conn, $_POST['prod_id'], $_POST['price_value']);
        if ($result === true) {
            unset($_SESSION['form_token']);
            header("Location: product_price.php");
            exit;
        } else if ($result === "Duplicate") {
            $_SESSION['error'] = "Product price for the selected product already exists.";
            header("Location: product_price.php");
            exit;
        } else {
            $_SESSION['error'] = $result;
            header("Location: product_price.php");
            exit;
        }
    }
    else if ($_POST['action'] === 'editPrice') {
        $result = updateProductPrice($conn, $_POST['price_id'], $_POST['prod_id'], $_POST['price_value']);
        if ($result === true) {
            unset($_SESSION['form_token']);
            header("Location: product_price.php");
            exit;
        } else {
            $_SESSION['error'] = $result;
            header("Location: product_price.php");
            exit;
        }
    }
    else if ($_POST['action'] === 'deletePrice') {
        $result = deleteProductPrice($conn, $_POST['price_id']);
        if ($result === true) {
            unset($_SESSION['form_token']);
            header("Location: product_price.php");
            exit;
        } else {
            $_SESSION['error'] = $result;
            header("Location: product_price.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>จัดการราคาจ้างผลิต</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h1 class="mb-4">จัดการราคาจ้างผลิต</h1>
    
    <!-- ฟอร์มค้นหา -->
    <form method="GET" class="mb-3">
        <div class="input-group">
            <input type="text" name="search" class="form-control" placeholder="ค้นหาโดยใช้ PRODUCT CODE FG | PART NO. | CODE SIZE" value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary">ค้นหา</button>
        </div>
    </form>
    
    <!-- แสดงข้อความ Error (ถ้ามี) -->
    <?php 
      if (isset($_SESSION['error'])) { 
          echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error']) . '</div>';
          unset($_SESSION['error']);
      }
    ?>
    
    <!-- ปุ่มสำหรับเปิด Modal เพิ่มข้อมูลราคา -->
    <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addPriceModal">
        เพิ่มราคาค่าจ้างผลิตใหม่
    </button>
    
    <!-- แสดงรายการราคาสินค้า -->
    <?php displayProductPriceList($productPrices); ?>
    
    <!-- Pagination -->
    <nav>
        <ul class="pagination justify-content-center">
            <!-- ปุ่ม Previous -->
            <li class="page-item <?php if($current_page <= 1) echo 'disabled'; ?>">
                <a class="page-link" href="?page=<?php echo $current_page - 1 . $search_query; ?>" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
            <?php 
            if($total_pages <= 5) {
                for ($i = 1; $i <= $total_pages; $i++) { ?>
                    <li class="page-item <?php if($current_page == $i) echo 'active'; ?>">
                        <a class="page-link" href="?page=<?php echo $i . $search_query; ?>"><?php echo $i; ?></a>
                    </li>
                <?php }
            } else {
                for ($i = 1; $i <= 5; $i++) { ?>
                    <li class="page-item <?php if($current_page == $i) echo 'active'; ?>">
                        <a class="page-link" href="?page=<?php echo $i . $search_query; ?>"><?php echo $i; ?></a>
                    </li>
                <?php } ?>
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
                <li class="page-item <?php if($current_page == $total_pages) echo 'active'; ?>">
                    <a class="page-link" href="?page=<?php echo $total_pages . $search_query; ?>"><?php echo $total_pages; ?></a>
                </li>
            <?php } ?>
            <!-- ปุ่ม Next -->
            <li class="page-item <?php if($current_page >= $total_pages) echo 'disabled'; ?>">
                <a class="page-link" href="?page=<?php echo $current_page + 1 . $search_query; ?>" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        </ul>
    </nav>
    
</div>
    
<!-- ส่ง Token ให้ Modal ใช้งาน -->
<?php 
    $token = $_SESSION['form_token'] ?? ''; 
    displayAddPriceModal($token);
    displayEditPriceModal($token);
?>
    
<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery (Select2 ต้องใช้ jQuery) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Initialize Select2 สำหรับ Modal Add
    $('.select2').select2({
        width: '100%',
        dropdownParent: $('#addPriceModal')
    });
    
    // Initialize Select2 สำหรับ Modal Edit
    $('#edit_prod_id').select2({
        width: '100%',
        dropdownParent: $('#editPriceModal')
    });
    
    // เมื่อกด Edit ให้ดึงข้อมูลจาก data attributes ไป populate ฟอร์มใน Edit Modal
    $('.edit-btn').on('click', function() {
        var priceId = $(this).data('price-id');
        var prodId = $(this).data('prod-id');
        var priceValue = $(this).data('price-value');
        
        $('#edit_price_id').val(priceId);
        $('#edit_prod_id').val(prodId).trigger('change');
        $('#edit_price_value').val(priceValue);
        
        $('#editPriceModal').modal('show');
    });
});
</script>
</body>
</html>
<?php
ob_end_flush();  // ส่ง output ออก
$conn->close();
?>
