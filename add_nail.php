<?php
// add_nail.php

// เริ่มต้น session เพื่อจัดการการล็อกอิน
session_start();

// ตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่ ถ้าไม่ ให้ redirect ไปยังหน้า index.php
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// รวมไฟล์ที่จำเป็น
require_once 'config_db.php'; // ไฟล์เชื่อมต่อฐานข้อมูล

// กำหนดตัวแปรสำหรับเก็บข้อความแจ้งเตือนและข้อมูลจากฟอร์ม
$alert_message = '';
$form_data = [
    'nail_code' => '',
    'nail_pcsperroll' => '', // อัปเดตชื่อตัวแปร
    'nail_rollperbox' => ''  // อัปเดตชื่อตัวแปร
];

// --- การประมวลผลข้อมูลเมื่อมีการส่งฟอร์ม (POST Request) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- Cyber Security: Sanitize and Validate Inputs ---
    $nail_code = htmlspecialchars(trim($_POST['nail_code']));
    // อัปเดตชื่อฟิลด์ที่รับจาก POST
    $nail_pcsperroll = filter_var(trim($_POST['nail_pcsperroll']), FILTER_VALIDATE_INT);
    $nail_rollperbox = filter_var(trim($_POST['nail_rollperbox']), FILTER_VALIDATE_INT);

    // เก็บข้อมูลที่ผู้ใช้กรอกไว้
    $form_data = [
        'nail_code' => $nail_code,
        'nail_pcsperroll' => $nail_pcsperroll,
        'nail_rollperbox' => $nail_rollperbox
    ];

    // --- Data Validation ---
    $errors = [];
    if (empty($nail_code)) {
        $errors[] = "กรุณากรอกรหัสตะปู";
    }
    // อัปเดตเงื่อนไขการตรวจสอบ
    if ($nail_pcsperroll === false || $nail_pcsperroll <= 0) {
        $errors[] = "กรุณากรอก 'จำนวนต่อม้วน' เป็นตัวเลขจำนวนเต็มที่มากกว่า 0";
    }
    if ($nail_rollperbox === false || $nail_rollperbox <= 0) {
        $errors[] = "กรุณากรอก 'จำนวนม้วนต่อกล่อง' เป็นตัวเลขจำนวนเต็มที่มากกว่า 0";
    }

    // --- การตรวจสอบข้อมูลซ้ำ (Duplicate Data Prevention) ---
    // ตาราง `nails` (ตามโค้ดเก่า) และคอลัมน์ `nail_code` ยังคงเดิม
    if (empty($errors)) {
        $sql_check_duplicate = "SELECT nail_id FROM nail WHERE nail_code = ?";
        if ($stmt_check = $conn->prepare($sql_check_duplicate)) {
            $stmt_check->bind_param("s", $nail_code);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $errors[] = "รหัสตะปู '$nail_code' นี้มีอยู่ในระบบแล้ว";
            }
            $stmt_check->close();
        } else {
            $errors[] = "เกิดข้อผิดพลาดในการตรวจสอบข้อมูลซ้ำ: " . $conn->error;
        }
    }

    // --- บันทึกข้อมูลลงฐานข้อมูล (ถ้าไม่มีข้อผิดพลาด) ---
    if (empty($errors)) {
        // --- Database Insertion: อัปเดตชื่อคอลัมน์ใน SQL ให้ตรงกับรูปภาพ ---
        $sql_insert = "INSERT INTO nail (nail_code, nail_pcsperroll, nail_rollperbox) VALUES (?, ?, ?)";
        if ($stmt_insert = $conn->prepare($sql_insert)) {
            $stmt_insert->bind_param("sii", $nail_code, $nail_pcsperroll, $nail_rollperbox);

            if ($stmt_insert->execute()) {
                $_SESSION['success_message'] = "เพิ่มข้อมูลตะปูสำเร็จ!";
                header("location: nail_list.php");
                exit();
            } else {
                $alert_message = '<div class="alert alert-danger" role="alert">เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $conn->error . '</div>';
            }
            $stmt_insert->close();
        }
    } else {
        // หากมีข้อผิดพลาด ให้สร้างข้อความแจ้งเตือน
        $error_html = '<ul>';
        foreach ($errors as $error) {
            $error_html .= '<li>' . htmlspecialchars($error) . '</li>';
        }
        $error_html .= '</ul>';
        $alert_message = '<div class="alert alert-danger" role="alert">' . $error_html . '</div>';
    }

    // ปิดการเชื่อมต่อฐานข้อมูล
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มข้อมูลตะปู</title>
    <!-- Bootstrap 5.3 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card-header-custom {
            background-color: #007bff;
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; // รวม Navbar ?>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header card-header-custom">
                        <h4 class="mb-0">เพิ่มรายการตะปู</h4>
                    </div>
                    <div class="card-body">
                        <?php echo $alert_message; // แสดงข้อความแจ้งเตือน ?>

                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="nail_code" class="form-label"><strong>รหัสตะปู <span class="text-danger">*</span></strong></label>
                                <input type="text" name="nail_code" id="nail_code" class="form-control" value="<?php echo htmlspecialchars($form_data['nail_code']); ?>" required>
                                <div class="invalid-feedback">
                                    กรุณากรอกรหัสตะปู
                                </div>
                            </div>

                            <!-- อัปเดต name และ id ของ input ให้ตรงกับชื่อคอลัมน์ -->
                            <div class="mb-3">
                                <label for="nail_pcsperroll" class="form-label"><strong>จำนวนต่อม้วน <span class="text-danger">*</span></strong></label>
                                <input type="number" name="nail_pcsperroll" id="nail_pcsperroll" class="form-control" min="1" value="<?php echo htmlspecialchars($form_data['nail_pcsperroll']); ?>" required>
                                <div class="invalid-feedback">
                                    กรุณากรอกจำนวนต่อม้วนเป็นตัวเลข
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="nail_rollperbox" class="form-label"><strong>จำนวนม้วนต่อกล่อง <span class="text-danger">*</span></strong></label>
                                <input type="number" name="nail_rollperbox" id="nail_rollperbox" class="form-control" min="1" value="<?php echo htmlspecialchars($form_data['nail_rollperbox']); ?>" required>
                                <div class="invalid-feedback">
                                    กรุณากรอกจำนวนม้วนต่อกล่องเป็นตัวเลข
                                </div>
                            </div>

                            <hr>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="nail_list.php" class="btn btn-secondary">ยกเลิก</a>
                                <button type="submit" class="btn btn-primary">เพิ่มตะปู</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; // รวม Footer ?>

    <!-- Bootstrap 5.3 Script -->
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
