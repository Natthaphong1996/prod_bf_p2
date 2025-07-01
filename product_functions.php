<?php
// product_functions.php

// ดึงข้อมูลสินค้า (prod_code, prod_partno) จากตาราง prod_list
function getProdInfo($prod_id, $conn)
{
    $sql = "SELECT prod_code, prod_partno FROM prod_list WHERE prod_id = '$prod_id'";
    $result = $conn->query($sql);
    return $result->fetch_assoc();
}

// ดึงข้อมูล BOM parts จากตาราง bom
function getBomParts($prod_id, $conn)
{
    $sql = "SELECT parts FROM bom WHERE prod_id = '$prod_id'";
    $result = $conn->query($sql);
    return $result->fetch_assoc();
}

// ฟังก์ชันแปลงวันที่ให้อยู่ในรูปแบบที่เหมาะสมกับฐานข้อมูล
function convertDateToDBFormat($date)
{
    if ($date != '') {
        return date('Y-m-d', strtotime($date));
    }
    return null;
}
?>
