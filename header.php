<?php
// ชื่อไฟล์: header.php
// โหลดส่วนหัว HTML รวม CSS/JS ที่จำเป็นต่อทุกหน้า
?><!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($page_title) ? htmlspecialchars($page_title) : 'ระบบจัดการ' ?></title>

  <!-- Bootstrap 5.3 CSS (CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUa1zYp2xg6+..." crossorigin="anonymous">

  <!-- Select2 CSS (CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

  <!-- Custom CSS -->
  <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">

  <!-- jQuery (Required for Select2) -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJ+YJDv..." crossorigin="anonymous"></script>

  <!-- Select2 JS (CDN) -->
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

  <!-- Bootstrap Bundle JS (Popper + Bootstrap JS) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-ENjdO4Dr2bkBIFxQpeo2zp..." crossorigin="anonymous"></script>

  <!-- Custom JS -->
  <script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</head>
<body>

<?php
// หลังจากนี้ให้ include navbar หรือโค้ดเริ่มต้นของแต่ละหน้า
?>
