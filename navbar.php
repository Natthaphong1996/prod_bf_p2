<?php
include 'config_db.php'; // เชื่อมต่อกับฐานข้อมูล

// เริ่มต้น session หากยังไม่ได้เริ่ม
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$username = $_SESSION['username'];

// ฟังก์ชันสำหรับดึงรายการเมนูตามสิทธิ์ของผู้ใช้ 
function getMenuItems($user_department, $user_level)
{
    $menu = [];
    // สำหรับกลุ่ม IT
    if (in_array($user_department, ['IT'])) {
        $menu[] = [
            'title' => 'แดชบอร์ด',
            'url' => 'dashboard.php'
        ];
        $menu[] = [
            'title' => 'ลูกค้า',
            'submenu' => [
                ['title' => 'รายการลูกค้า', 'url' => 'customers_list.php'],
                ['title' => 'เพิ่มลูกค้า', 'url' => 'add_customers.php']
            ]
        ];
        $menu[] = [
            'title' => 'สินค้า',
            'submenu' => [
                ['title' => 'รายการสินค้า', 'url' => 'products_list.php'],
                ['title' => 'เพิ่มสินค้า', 'url' => 'add_products.php'],
                ['title' => 'รายการประเภทสินค้า', 'url' => 'products_type_list.php'],
                ['title' => 'เพิ่มประเภทสินค้า', 'url' => 'add_products_type.php'],
                ['title' => 'รายการชิ้นส่วนสินค้า', 'url' => 'products_part_list.php'],
                ['title' => 'เพิ่มชิ้นส่วนสินค้า', 'url' => 'add_part.php'],
                ['title' => 'รายการประเภทชิ้นส่วน', 'url' => 'part_type_list.php'],
                ['title' => 'เพิ่มประเภทชิ้นส่วน', 'url' => 'add_part_type.php'],

            ]
        ];
        $menu[] = [
            'title' => 'BOM',
            'submenu' => [
                ['title' => 'รายการ BOM', 'url' => 'bom_list.php'],
                ['title' => 'เพิ่ม BOM', 'url' => 'add_bom.php']
            ]
        ];
        $menu[] = [
            'title' => 'Forecast',
            'submenu' => [
                ['title' => 'รายการ Forecast', 'url' => 'forecast.php'],
                ['title' => 'เพิ่ม Forecast', 'url' => 'add_forecast.php']
            ]
        ];

        $menu[] = [
            'title' => 'กระบวนการเบิกไม้',
            'submenu' => [
                ['title' => 'สั่งเบิกไม้สำหรับงานผลิต', 'url' => 'planning_order.php'],
                ['title' => 'ติดตามแผนงานผลิต', 'url' => 'check_all_product_planning.php'],
                ['title' => 'ติดตามงานเบิกซ่อม', 'url' => 'check_status_issue_repair.php'],
                ['title' => 'จัดเตรียมไม้สำหรับงานผลิต (งานหลัก)', 'url' => 'wip_manage_issue_main.php'],
                ['title' => 'จัดเตรียมไม้สำหรับงานผลิต (งานเบิกซ่อม)', 'url' => 'wip_manage_issue_repair.php'],
                ['title' => 'รายการส่งคืนไม้', 'url' => 'return_list.php'],
                ['title' => 'ตรวจรับงานผลิต', 'url' => 'product_complete_list.php'],
                ['title' => 'สรุปการเบิกไม้', 'url' => 'wood_issue_summary.php']
            ]
        ];

        $menu[] = [
            'title' => 'ตะปู',
            'submenu' => [
                ['title' => 'เพิ่มตะปู', 'url' => 'add_nail.php'],
                ['title' => 'รายการตะปู', 'url' => 'nail_list.php'],
                ['title' => 'เบิกตะปู', 'url' => 'nails_issue_list.php'],
                ['title' => 'สรุปการใช้งานตะปู', 'url' => 'nail_summary.php'],
            ]
        ];

        $menu[] = [
            'title' => 'คำนวนค่าแรง',
            'submenu' => [
                ['title' => 'เพิ่มราคาค่าแรง', 'url' => 'product_price.php'],
                ['title' => 'ออกเอกสารค่าแรง', 'url' => 'production_wages_list.php'],
                ['title' => 'ประมาณการค่าแรง', 'url' => 'calculate_wages.php'],
                ['title' => 'รายงานเปรียบเทียบค่าแรง', 'url' => 'wood_summary_report.php'],
            ]
        ];

        $menu[] = [
            'title' => 'WIP',
            'submenu' => [
                ['title' => 'คลัง WIP', 'url' => 'wip_inventory.php'],
                ['title' => 'รูปแบบการผลิต', 'url' => 'recipe_list.php'], 
                ['title' => 'ไม้ท่อน', 'url' => 'rm_list.php'],
                ['title' => 'หัวไม้', 'url' => 'hw_list.php'],
                ['title' => 'เศษไม้', 'url' => 'sw_list.php'], 
            ]
        ];
    }

    // สำหรับกลุ่ม MD
    if (in_array($user_department, ['MD'])) {
        $menu[] = [
            'title' => 'แดชบอร์ด',
            'url' => 'dashboard.php'
        ];
        $menu[] = [
            'title' => 'ลูกค้า',
            'submenu' => [
                ['title' => 'รายการลูกค้า', 'url' => 'customers_list.php'],
                ['title' => 'เพิ่มลูกค้า', 'url' => 'add_customers.php']
            ]
        ];
        $menu[] = [
            'title' => 'สินค้า',
            'submenu' => [
                ['title' => 'รายการสินค้า', 'url' => 'products_list.php'],
                ['title' => 'เพิ่มสินค้า', 'url' => 'add_products.php'],
                ['title' => 'รายการประเภทสินค้า', 'url' => 'products_type_list.php'],
                ['title' => 'เพิ่มประเภทสินค้า', 'url' => 'add_products_type.php'],
                ['title' => 'รายการชิ้นส่วนสินค้า', 'url' => 'products_part_list.php'],
                ['title' => 'เพิ่มชิ้นส่วนสินค้า', 'url' => 'add_part.php'],
                ['title' => 'รายการประเภทชิ้นส่วน', 'url' => 'part_type_list.php'],
                ['title' => 'เพิ่มประเภทชิ้นส่วน', 'url' => 'add_part_type.php'],
            ]
        ];
        $menu[] = [
            'title' => 'BOM',
            'submenu' => [
                ['title' => 'รายการ BOM', 'url' => 'bom_list.php'],
                ['title' => 'เพิ่ม BOM', 'url' => 'add_bom.php']
            ]
        ];
        $menu[] = [
            'title' => 'Forecast',
            'submenu' => [
                ['title' => 'รายการ Forecast', 'url' => 'forecast.php'],
                ['title' => 'เพิ่ม Forecast', 'url' => 'add_forecast.php']
            ]
        ];

        $menu[] = [
            'title' => 'กระบวนการเบิกไม้',
            'submenu' => [
                ['title' => 'สั่งเบิกไม้สำหรับงานผลิต', 'url' => 'planning_order.php'],
                ['title' => 'ติดตามแผนงานผลิต', 'url' => 'check_all_product_planning.php'],
                ['title' => 'ติดตามงานเบิกซ่อม', 'url' => 'check_status_issue_repair.php'],
                ['title' => 'จัดเตรียมไม้สำหรับงานผลิต (งานหลัก)', 'url' => 'wip_manage_issue_main.php'],
                ['title' => 'จัดเตรียมไม้สำหรับงานผลิต (งานเบิกซ่อม)', 'url' => 'wip_manage_issue_repair.php'],
                ['title' => 'รายการส่งคืนไม้', 'url' => 'return_list.php'],
                ['title' => 'ตรวจรับงานผลิต', 'url' => 'product_complete_list.php'],
                ['title' => 'สรุปการเบิกไม้', 'url' => 'wood_issue_summary.php']
            ]
        ];

        $menu[] = [
            'title' => 'คำนวนค่าแรง',
            'submenu' => [
                ['title' => 'เพิ่มราคาค่าแรง', 'url' => 'product_price.php'],
                ['title' => 'ออกเอกสารค่าแรง', 'url' => 'production_wages_list.php'],
            ]
        ];
    }

    // สำหรับกลุ่ม PROD
    if (in_array($user_department, ['PROD'])) {
        $menu[] = [
            'title' => 'แดชบอร์ด',
            'url' => 'dashboard.php'
        ];
        $menu[] = [
            'title' => 'BOM',
            'submenu' => [
                ['title' => 'รายการ BOM', 'url' => 'bom_list.php'],
            ]
        ];
        $menu[] = [
            'title' => 'Forecast',
            'submenu' => [
                ['title' => 'รายการ Forecast', 'url' => 'forecast.php'],
            ]
        ];

        $menu[] = [
            'title' => 'กระบวนการเบิกไม้',
            'submenu' => [
                ['title' => 'ติดตามแผนงานผลิต', 'url' => 'check_all_product_planning.php'],
                ['title' => 'ติดตามงานเบิกซ่อม', 'url' => 'check_status_issue_repair.php'],
                ['title' => 'ตรวจรับงานผลิต', 'url' => 'product_complete_list.php'],
                ['title' => 'สรุปการเบิกไม้', 'url' => 'wood_issue_summary.php']
            ]
        ];

    }


    // สำหรับกลุ่ม WIP
    if (in_array($user_department, ['WIP'])) {
        $menu[] = [
            'title' => 'แดชบอร์ด',
            'url' => 'dashboard.php'
        ];
        $menu[] = [
            'title' => 'กระบวนการเบิกไม้',
            'submenu' => [
                ['title' => 'จัดเตรียมไม้สำหรับงานผลิต (งานหลัก)', 'url' => 'wip_manage_issue_main.php'],
                ['title' => 'จัดเตรียมไม้สำหรับงานผลิต (งานเบิกซ่อม)', 'url' => 'wip_manage_issue_repair.php'],
                ['title' => 'รายการส่งคืนไม้', 'url' => 'return_list.php'],
                ['title' => 'สรุปการเบิกไม้', 'url' => 'wood_issue_summary.php']
            ]
        ];

    }

    // สำหรับกลุ่มผู้ใช้ MANGER_REPORT
    if (in_array($user_department, ['MANGER_REPORT'])) {
        $menu[] = [
            'title' => 'รายการใบเบิกไม้',
            'submenu' => [
                ['title' => 'สรุปการเบิกไม้', 'url' => 'wood_issue_summary.php']
            ]
        ];
        $menu[] = [
            'title' => 'ตะปู',
            'submenu' => [
                ['title' => 'เพิ่มตะปู', 'url' => 'add_nail.php'],
                ['title' => 'รายการตะปู', 'url' => 'nail_list.php'],
                ['title' => 'เบิกตะปู', 'url' => 'nails_issue_list.php'],
                ['title' => 'สรุปการใช้งานตะปู', 'url' => 'nail_summary.php'],
            ]
        ];
    }

    // สำหรับกลุ่มผู้ใช้ PROD_ADMIN
    if (in_array($user_department, ['PROD_ADMIN'])) {
        $menu[] = [
            'title' => 'ลูกค้า',
            'submenu' => [
                ['title' => 'รายการลูกค้า', 'url' => 'customers_list.php'],
                ['title' => 'เพิ่มลูกค้า', 'url' => 'add_customers.php']
            ]
        ];
        $menu[] = [
            'title' => 'สินค้า',
            'submenu' => [
                ['title' => 'รายการสินค้า', 'url' => 'products_list.php'],
                ['title' => 'เพิ่มสินค้า', 'url' => 'add_products.php'],
                ['title' => 'รายการประเภทสินค้า', 'url' => 'products_type_list.php'],
                ['title' => 'เพิ่มประเภทสินค้า', 'url' => 'add_products_type.php'],
                ['title' => 'รายการชิ้นส่วนสินค้า', 'url' => 'products_part_list.php'],
                ['title' => 'เพิ่มชิ้นส่วนสินค้า', 'url' => 'add_part.php'],
                ['title' => 'รายการประเภทชิ้นส่วน', 'url' => 'part_type_list.php'],
                ['title' => 'เพิ่มประเภทชิ้นส่วน', 'url' => 'add_part_type.php'],
            ]
        ];
        $menu[] = [
            'title' => 'BOM',
            'submenu' => [
                ['title' => 'รายการ BOM', 'url' => 'bom_list.php'],
                ['title' => 'เพิ่ม BOM', 'url' => 'add_bom.php']
            ]
        ];
        $menu[] = [
            'title' => 'รายการใบเบิกไม้',
            'submenu' => [
                ['title' => 'สั่งเบิกไม้สำหรับงานผลิต', 'url' => 'planning_order.php'],
                ['title' => 'ติดตามแผนงานผลิต', 'url' => 'check_all_product_planning.php'],
                ['title' => 'ติดตามงานเบิกซ่อม', 'url' => 'check_status_issue_repair.php'],
                ['title' => 'ตรวจรับงานผลิต', 'url' => 'product_complete_list.php'],
                ['title' => 'สรุปการเบิกไม้', 'url' => 'wood_issue_summary.php']
            ]
        ];

        $menu[] = [
            'title' => 'ตะปู',
            'submenu' => [
                ['title' => 'เพิ่มตะปู', 'url' => 'add_nail.php']
            ]
        ];

        $menu[] = [
            'title' => 'คำนวนค่าแรง',
            'submenu' => [
                ['title' => 'เพิ่มราคาค่าแรง', 'url' => 'product_price.php'],
                ['title' => 'ออกเอกสารค่าแรง', 'url' => 'production_wages_list.php'],
                ['title' => 'ประมาณการค่าแรง', 'url' => 'calculate_wages.php'],
                ['title' => 'รายงานเปรียบเทียบค่าแรง', 'url' => 'wood_summary_report.php'],
            ]
        ];
    }

    // สำหรับกลุ่มผู้ใช้ PROD_ADMIN_STORE
    if (in_array($user_department, ['PROD_ADMIN_STORE'])) {

        $menu[] = [
            'title' => 'BOM',
            'submenu' => [
                ['title' => 'รายการ BOM(แก้ไข BOM ตะปูที่นี้)', 'url' => 'bom_list.php'],
            ]
        ];

        $menu[] = [
            'title' => 'ตะปู',
            'submenu' => [
                ['title' => 'เพิ่มตะปู', 'url' => 'add_nail.php'],
                ['title' => 'รายการตะปู', 'url' => 'nail_list.php'],
                ['title' => 'เบิกตะปู', 'url' => 'nails_issue_list.php'],
                ['title' => 'สรุปการใช้งานตะปู', 'url' => 'nail_summary.php'],
            ]
        ];
    }

    // สำหรับกลุ่มผู้ใช้ PROD_ADMIN_LV1
    if (in_array($user_department, ['PROD_ADMIN_LV1'])) {
        $menu[] = [
            'title' => 'BOM',
            'submenu' => [
                ['title' => 'รายการ BOM(แก้ไข BOM ตะปูที่นี้)', 'url' => 'bom_list.php'],
            ]
        ];

        $menu[] = [
            'title' => 'ตะปู',
            'submenu' => [
                ['title' => 'เพิ่มตะปู', 'url' => 'add_nail.php'],
                ['title' => 'รายการตะปู', 'url' => 'nail_list.php'],
                ['title' => 'เบิกตะปู', 'url' => 'nails_issue_list.php'],
                ['title' => 'สรุปการใช้งานตะปู', 'url' => 'nail_summary.php'],
            ]
        ];
    }

    // PROD_MANGER
    if (in_array($user_department, ['PROD_MANGER'])) {
        
        
        
        $menu[] = [
            'title' => 'รายการใบเบิกไม้',
            'submenu' => [
                ['title' => 'ติดตามแผนงานผลิต', 'url' => 'check_all_product_planning.php'],
                ['title' => 'ติดตามงานเบิกซ่อม', 'url' => 'check_status_issue_repair.php'],
                ['title' => 'สรุปการเบิกไม้', 'url' => 'wood_issue_summary.php']
            ]
        ];
        $menu[] = [
            'title' => 'คำนวนค่าแรง',
            'submenu' => [
                ['title' => 'ประมาณการค่าแรง', 'url' => 'calculate_wages.php'],
                ['title' => 'รายงานเปรียบเทียบค่าแรง', 'url' => 'wood_summary_report.php'],
            ]
        ];
    }

    // สำหรับกลุ่มผู้ใช้ PROD_ADMIN_LV2
    if (in_array($user_department, ['PROD_ADMIN_LV2'])) {
        $menu[] = [
            'title' => 'ลูกค้า',
            'submenu' => [
                ['title' => 'รายการลูกค้า', 'url' => 'customers_list.php'],
                ['title' => 'เพิ่มลูกค้า', 'url' => 'add_customers.php']
            ]
        ];
        $menu[] = [
            'title' => 'สินค้า',
            'submenu' => [
                ['title' => 'รายการสินค้า', 'url' => 'products_list.php'],
                ['title' => 'เพิ่มสินค้า', 'url' => 'add_products.php'],
                ['title' => 'รายการประเภทสินค้า', 'url' => 'products_type_list.php'],
                ['title' => 'เพิ่มประเภทสินค้า', 'url' => 'add_products_type.php'],
                ['title' => 'รายการชิ้นส่วนสินค้า', 'url' => 'products_part_list.php'],
                ['title' => 'เพิ่มชิ้นส่วนสินค้า', 'url' => 'add_part.php'],
                ['title' => 'รายการประเภทชิ้นส่วน', 'url' => 'part_type_list.php'],
                ['title' => 'เพิ่มประเภทชิ้นส่วน', 'url' => 'add_part_type.php'],
            ]
        ];
        $menu[] = [
            'title' => 'BOM',
            'submenu' => [
                ['title' => 'รายการ BOM', 'url' => 'bom_list.php'],
                ['title' => 'เพิ่ม BOM', 'url' => 'add_bom.php']
            ]
        ];
        $menu[] = [
            'title' => 'รายการใบเบิกไม้',
            'submenu' => [
                ['title' => 'สั่งเบิกไม้สำหรับงานผลิต', 'url' => 'planning_order.php'],
                ['title' => 'ติดตามแผนงานผลิต', 'url' => 'check_all_product_planning.php'],
                ['title' => 'ติดตามงานเบิกซ่อม', 'url' => 'check_status_issue_repair.php'],
                ['title' => 'ตรวจรับงานผลิต', 'url' => 'product_complete_list.php'],
                ['title' => 'สรุปการเบิกไม้', 'url' => 'wood_issue_summary.php']
            ]
        ];
        $menu[] = [
            'title' => 'คำนวนค่าแรง',
            'submenu' => [
                ['title' => 'เพิ่มราคาค่าแรง', 'url' => 'product_price.php'],
                ['title' => 'ออกเอกสารค่าแรง', 'url' => 'production_wages_list.php'],
                ['title' => 'ประมาณการค่าแรง', 'url' => 'calculate_wages.php'],
            ]
        ];
    }

    return $menu;
}

// ดึงข้อมูลผู้ใช้จากฐานข้อมูล
$sql = "SELECT department, level FROM prod_user WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result($user_department, $user_level);
$stmt->fetch();
$stmt->close();

$menuItems = getMenuItems($user_department, $user_level);
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <!-- แสดง Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container-fluid">
            <a class="navbar-brand" href="landing_page.php">Siam Kyohwa Seisakusho</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <?php foreach ($menuItems as $item): ?>
                        <?php if (isset($item['submenu'])): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                                    aria-expanded="false">
                                    <?= $item['title'] ?>
                                </a>
                                <ul class="dropdown-menu">
                                    <?php foreach ($item['submenu'] as $sub): ?>
                                        <li><a class="dropdown-item" href="<?= $sub['url'] ?>"><?= $sub['title'] ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= $item['url'] ?>"><?= $item['title'] ?></a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
                <!-- เมนูออกจากระบบ -->
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- ประกาศแจ้งเตือน -->
    <!-- <div class="alert alert-danger text-center" role="alert">
        <strong>ประกาศแจ้งเตือน:</strong> วันที่ 12/06/2025 เวลา 12:10 - 13:00 จะมีการปิดระบบเพื่อ UPDATE
        ซึ่งจะทำให้ไม่สามารถใช้งานระบบได้ ขออภัยในความไม่สะดวก
    </div> -->

    <!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script> -->
</body>

</html>