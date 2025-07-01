<?php

// $servername = "localhost";
// $username = "maintena_ncesk";
// $password = "!QAZxsw23edc";
// $dbname = "maintena_prod_bf";

$servername = "localhost"; // ชื่อเซิร์ฟเวอร์ (เช่น localhost)
$username = "root"; // ชื่อผู้ใช้ฐานข้อมูล
$password = ""; // รหัสผ่านของผู้ใช้ฐานข้อมูล (ถ้าว่างใส่ "")
$dbname = "prod_bf_p2"; // ชื่อฐานข้อมูลที่คุณใช้

// สร้างการเชื่อมต่อฐานข้อมูล
$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
