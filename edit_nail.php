<?php
// edit_nail.php

session_start();
require_once 'config_db.php'; // เรียกใช้ไฟล์สำหรับเชื่อมต่อฐานข้อมูล

// --- Security: ตรวจสอบการล็อกอิน ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// --- กำหนดตัวแปรเริ่มต้น ---
$nail = null;
$nail_id = null;
$alert_message = '';
$form_data = [
    'nail_code' => '',
    'nail_pcsperroll' => '',
    'nail_rollperbox' => ''
];


// --- การประมวลผลเมื่อมีการส่งฟอร์ม (POST Request) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- Sanitize and Validate Inputs ---
    $nail_id = filter_var($_POST['nail_id'], FILTER_VALIDATE_INT);
    $nail_pcsperroll = filter_var(trim($_POST['nail_pcsperroll']), FILTER_VALIDATE_INT);
    $nail_rollperbox = filter_var(trim($_POST['nail_rollperbox']), FILTER_VALIDATE_INT);

    // --- Data Validation ---
    $errors = [];
    if ($nail_id === false) {
        $errors[] = "รหัสตะปูไม่ถูกต้อง";
    }
    if ($nail_pcsperroll === false || $nail_pcsperroll <= 0) {
        $errors[] = "กรุณากรอก 'จำนวนต่อม้วน' เป็นตัวเลขจำนวนเต็มที่มากกว่า 0";
    }
    if ($nail_rollperbox === false || $nail_rollperbox <= 0) {
        $errors[] = "กรุณากรอก 'จำนวนม้วนต่อกล่อง' เป็นตัวเลขจำนวนเต็มที่มากกว่า 0";
    }

    // --- อัปเดตข้อมูลในฐานข้อมูล (ถ้าไม่มีข้อผิดพลาด) ---
    if (empty($errors)) {
        // ใช้ nail_id ในการอัปเดต
        $sql = "UPDATE nail SET nail_pcsperroll = ?, nail_rollperbox = ? WHERE nail_id = ?";
        if ($stmt = $conn->prepare($sql)) {
            // bind_param: integer, integer, integer
            $stmt->bind_param("iii", $nail_pcsperroll, $nail_rollperbox, $nail_id);

            if ($stmt->execute()) {
                // ใช้ key 'success_message' ให้ตรงกับหน้า list
                $_SESSION['success_message'] = "แก้ไขข้อมูลสำเร็จ!";
                header("Location: nail_list.php");
                exit();
            } else {
                $alert_message = '<div class="alert alert-danger">เกิดข้อผิดพลาดในการแก้ไขข้อมูล: ' . $conn->error . '</div>';
            }
            $stmt->close();
        }
    } else {
        // แสดงข้อผิดพลาด
        $error_html = '<ul>';
        foreach ($errors as $error) {
            $error_html .= '<li>' . htmlspecialchars($error) . '</li>';
        }
        $error_html .= '</ul>';
        $alert_message = '<div class="alert alert-danger">' . $error_html . '</div>';
    }
}

// --- ดึงข้อมูลจากฐานข้อมูลเพื่อนำไปแสดงในฟอร์ม (GET Request) ---
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    // รับค่า id จาก URL parameter
    $nail_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

    if (!$nail_id) {
        $_SESSION['error_message'] = "Invalid request."; // ควรมีข้อความแจ้งเตือน
        header("Location: nail_list.php");
        exit();
    }

    $sql = "SELECT * FROM nail WHERE nail_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $nail_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $nail = $result->fetch_assoc();
            // กำหนดค่าเริ่มต้นให้ฟอร์ม
            $form_data = $nail;
        } else {
            $_SESSION['error_message'] = "ไม่พบข้อมูลตะปูที่ต้องการแก้ไข";
            header("Location: nail_list.php");
            exit();
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขข้อมูลตะปู</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .card-header-custom {
            background-color: #ffc107; /* สีเหลืองสำหรับหน้าแก้ไข */
            color: black;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header card-header-custom">
                    <h4 class="mb-0">แก้ไขรายการตะปู</h4>
                </div>
                <div class="card-body">
                    <?php echo $alert_message; ?>

                    <?php if (!empty($form_data)): ?>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="needs-validation" novalidate>
                        <!-- ส่ง nail_id ไปด้วยแบบ hidden field -->
                        <input type="hidden" name="nail_id" value="<?php echo htmlspecialchars($form_data['nail_id']); ?>">

                        <div class="mb-3">
                            <label for="nail_code" class="form-label"><strong>รหัสตะปู</strong></label>
                            <!-- ตั้งค่าเป็น readonly เพราะไม่ควรให้แก้ไขรหัสได้ -->
                            <input type="text" class="form-control" id="nail_code" name="nail_code" value="<?php echo htmlspecialchars($form_data['nail_code']); ?>" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="nail_pcsperroll" class="form-label"><strong>จำนวนต่อม้วน <span class="text-danger">*</span></strong></label>
                            <input type="number" class="form-control" id="nail_pcsperroll" name="nail_pcsperroll" value="<?php echo htmlspecialchars($form_data['nail_pcsperroll']); ?>" min="1" required>
                            <div class="invalid-feedback">
                                กรุณากรอกจำนวนต่อม้วนเป็นตัวเลข
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="nail_rollperbox" class="form-label"><strong>จำนวนม้วนต่อกล่อง <span class="text-danger">*</span></strong></label>
                            <input type="number" class="form-control" id="nail_rollperbox" name="nail_rollperbox" value="<?php echo htmlspecialchars($form_data['nail_rollperbox']); ?>" min="1" required>
                             <div class="invalid-feedback">
                                กรุณากรอกจำนวนม้วนต่อกล่องเป็นตัวเลข
                            </div>
                        </div>
                        
                        <hr>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="nail_list.php" class="btn btn-secondary">ยกเลิก</a>
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-save-fill"></i> บันทึกการแก้ไข
                            </button>
                        </div>
                    </form>
                    <?php else: ?>
                        <div class="alert alert-danger">ไม่พบข้อมูลที่จะแก้ไข</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// JavaScript สำหรับเปิดใช้งาน Bootstrap validation styles
(function () {
  'use strict'
  var forms = document.querySelectorAll('.needs-validation')
  Array.prototype.slice.call(forms)
    .forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
          event.preventDefault()
          event.stopPropagation()
        }
        form.classList.add('was-validated')
      }, false)
    })
})()
</script>
</body>
</html>
