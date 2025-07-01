<?php
// index.php

// เริ่มต้น session
session_start();

// ตรวจสอบว่าผู้ใช้ล็อกอินอยู่แล้วหรือไม่
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: landing_page.php");
    exit;
}

// รวมไฟล์เชื่อมต่อฐานข้อมูล
require_once 'config_db.php';

// กำหนดตัวแปรเริ่มต้น
$username = "";
$password = "";
$error = "";

// ประมวลผลข้อมูลฟอร์มเมื่อมีการส่งข้อมูลมา
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- การตรวจสอบข้อมูลเบื้องต้น ---
    if (empty(trim($_POST["username"]))) {
        $error = "กรุณากรอก Username";
    } else {
        $username = trim($_POST["username"]);
    }

    if (empty(trim($_POST["password"]))) {
        $error = "กรุณากรอก Password";
    } else {
        $password = trim($_POST["password"]);
    }

    // --- การตรวจสอบข้อมูลกับฐานข้อมูล ---
    if (empty($error)) {
        // เตรียม SQL statement โดยใช้ Prepared Statement
        $sql = "SELECT user_id, username, password, department, level FROM prod_user WHERE username = ?";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_username);
            $param_username = $username;

            if ($stmt->execute()) {
                $stmt->store_result();

                // ตรวจสอบว่ามี username นี้ในระบบหรือไม่
                if ($stmt->num_rows == 1) {
                    // ผูกผลลัพธ์กับตัวแปร
                    $stmt->bind_result($user_id, $db_username, $db_password, $department, $level);
                    if ($stmt->fetch()) {
                        
                        // --- [ไม่ปลอดภัย] ตรวจสอบรหัสผ่านแบบข้อความธรรมดา ---
                        // คำเตือน: วิธีนี้ไม่ปลอดภัยอย่างยิ่งและไม่แนะนำให้ใช้ในระบบจริง
                        if ($password == $db_password) {
                            
                            // รหัสผ่านถูกต้อง, เริ่มต้น session ใหม่
                            // session_start(); // Session เริ่มต้นไปแล้วด้านบน

                            // เก็บข้อมูลลงใน session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $user_id;
                            $_SESSION["username"] = $db_username;
                            $_SESSION["department"] = $department;
                            $_SESSION["level"] = $level;

                            // Redirect ไปยังหน้า landing page
                            header("location: landing_page.php");
                            exit();
                        } else {
                            // รหัสผ่านไม่ถูกต้อง
                            $error = "Username หรือ Password ไม่ถูกต้อง";
                        }
                    }
                } else {
                    // ไม่พบ Username
                    $error = "Username หรือ Password ไม่ถูกต้อง";
                }
            } else {
                $error = "เกิดข้อผิดพลาดบางอย่าง โปรดลองอีกครั้งในภายหลัง";
            }
            // ปิด statement
            $stmt->close();
        }
    }
    // ปิดการเชื่อมต่อ
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f8f9fa;
        }
        .login-form {
            width: 100%;
            max-width: 400px;
            padding: 40px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .logo {
            display: block;
            margin: 0 auto 20px;
            max-width: 90px;
        }
    </style>
</head>
<body>

<div class="login-form">
    <img src="logo/SK-Logo.png" alt="Logo" class="logo">
    
    <h2 class="text-center mb-4">เข้าสู่ระบบ</h2>

    <?php 
    if (!empty($error)) {
        echo '<div class="alert alert-danger">' . htmlspecialchars($error) . '</div>';
    }
    ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" novalidate>
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary w-100 mt-3">Login</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
