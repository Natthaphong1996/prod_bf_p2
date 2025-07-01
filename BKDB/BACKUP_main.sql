/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

CREATE DATABASE IF NOT EXISTS `prod_bf_p2` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;
USE `prod_bf_p2`;

CREATE TABLE IF NOT EXISTS `bom` (
  `bom_id` int(11) NOT NULL AUTO_INCREMENT,
  `prod_id` char(50) NOT NULL,
  `prod_code` varchar(50) DEFAULT NULL,
  `parts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`parts`)),
  `nails` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`bom_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1158 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `customer` (
  `customer_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_code` varchar(50) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_short_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`customer_id`)
) ENGINE=InnoDB AUTO_INCREMENT=596 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `forecast` (
  `forecast_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_code` varchar(50) NOT NULL,
  `prod_id` varchar(50) NOT NULL,
  `forecast_date` date NOT NULL,
  `forecast_quantity` int(11) NOT NULL,
  PRIMARY KEY (`forecast_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2888 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `hw_wood_list` (
  `hw_id` int(11) NOT NULL AUTO_INCREMENT,
  `hw_code` varchar(50) NOT NULL,
  `hw_type` varchar(50) NOT NULL DEFAULT '',
  `hw_thickness` int(11) NOT NULL,
  `hw_width` int(11) NOT NULL,
  `hw_length` int(11) NOT NULL,
  `hw_m3` double(15,6) NOT NULL DEFAULT 0.000000,
  PRIMARY KEY (`hw_id`)
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `jobs_complete` (
  `jobs_complete_id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` char(50) DEFAULT NULL,
  `prod_complete_qty` int(11) DEFAULT NULL,
  `production_wage_price` double DEFAULT NULL,
  `receive_by` char(50) DEFAULT NULL,
  `send_by` char(50) DEFAULT NULL,
  `record_by` char(50) DEFAULT NULL,
  `assembly_point` char(50) DEFAULT NULL,
  `reason` char(200) DEFAULT NULL,
  `date_complete` datetime DEFAULT current_timestamp(),
  `date_receive` datetime DEFAULT '2025-05-28 15:00:00',
  PRIMARY KEY (`jobs_complete_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2984 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `nail` (
  `nail_id` int(11) NOT NULL AUTO_INCREMENT,
  `nail_code` varchar(50) NOT NULL,
  `nail_pcsperroll` int(11) NOT NULL,
  `nail_rollperbox` int(11) NOT NULL,
  PRIMARY KEY (`nail_id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `nail_usage_log` (
  `usage_id` int(11) NOT NULL AUTO_INCREMENT,
  `issue_id` int(11) NOT NULL,
  `job_id` varchar(50) NOT NULL,
  `nail_id` int(11) NOT NULL COMMENT 'FK to nail.nail_id',
  `quantity_issued` int(11) NOT NULL,
  `issued_by_user_id` int(11) NOT NULL,
  `issue_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`usage_id`),
  KEY `issue_id` (`issue_id`),
  KEY `nail_id` (`nail_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='เก็บประวัติการเบิกตะปูครั้งแรก';

CREATE TABLE IF NOT EXISTS `part_list` (
  `part_id` int(11) NOT NULL AUTO_INCREMENT,
  `part_code` varchar(50) DEFAULT NULL,
  `part_type` varchar(50) DEFAULT NULL,
  `part_thickness` int(11) DEFAULT NULL,
  `part_width` int(11) DEFAULT NULL,
  `part_length` int(11) DEFAULT NULL,
  `part_m3` decimal(10,6) DEFAULT NULL,
  PRIMARY KEY (`part_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9182 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `part_type` (
  `type_id` int(11) NOT NULL AUTO_INCREMENT,
  `type_code` varchar(50) NOT NULL,
  `type_name` varchar(100) NOT NULL,
  PRIMARY KEY (`type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=102 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `production_wages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `production_wage_id` text DEFAULT NULL,
  `job_id` text DEFAULT NULL,
  `total_wage` double DEFAULT NULL,
  `assembly_point` varchar(100) DEFAULT NULL,
  `status` enum('รอยืนยัน','อนุมัติแล้ว','ยกเลิก') DEFAULT 'รอยืนยัน',
  `date_create` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=141 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `product_price` (
  `price_id` int(11) NOT NULL AUTO_INCREMENT,
  `price_value` double DEFAULT NULL,
  `prod_id` varchar(50) DEFAULT NULL,
  `date_update` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`price_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=381 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `product_price_history` (
  `price_log_id` int(11) NOT NULL AUTO_INCREMENT,
  `price_id` text DEFAULT NULL,
  `change_from` double DEFAULT NULL,
  `change_to` double DEFAULT NULL,
  `change_date` datetime DEFAULT current_timestamp(),
  `user_id` text DEFAULT NULL,
  PRIMARY KEY (`price_log_id`)
) ENGINE=InnoDB AUTO_INCREMENT=96 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `prod_list` (
  `prod_id` int(11) NOT NULL AUTO_INCREMENT,
  `prod_code` varchar(50) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `code_cus_size` text DEFAULT NULL,
  `prod_type` varchar(50) DEFAULT NULL,
  `prod_partno` varchar(50) DEFAULT NULL,
  `length` int(11) DEFAULT NULL,
  `width` int(11) DEFAULT NULL,
  `thickness` int(11) DEFAULT NULL,
  `prod_description` text DEFAULT NULL,
  PRIMARY KEY (`prod_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1331 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `prod_type` (
  `type_id` int(11) NOT NULL AUTO_INCREMENT,
  `type_code` varchar(50) NOT NULL,
  `type_name` varchar(100) NOT NULL,
  PRIMARY KEY (`type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `prod_user` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `thainame` varchar(255) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `level` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `recipe_list` (
  `recipe_id` int(11) NOT NULL AUTO_INCREMENT,
  `rm_id` int(11) NOT NULL,
  `rm_qty` int(11) NOT NULL,
  `rm_total_m3` decimal(15,7) NOT NULL COMMENT 'ปริมาตร RAW MATERIAL รวม (m³)',
  `rm_comment` varchar(200) DEFAULT NULL,
  `part_id` int(11) NOT NULL,
  `part_qry` int(11) NOT NULL,
  `part_cut` int(11) NOT NULL,
  `part_split` int(11) NOT NULL,
  `part_total_m3` decimal(15,7) NOT NULL DEFAULT 0.0000000,
  `part_comment` varchar(200) DEFAULT NULL,
  `hw_id` int(11) DEFAULT NULL,
  `hw_qty` int(11) DEFAULT NULL,
  `hw_cut` int(11) DEFAULT NULL,
  `hw_split` int(11) DEFAULT NULL,
  `hw_total_m3` decimal(15,7) DEFAULT 0.0000000 COMMENT 'ปริมาตร HEAVY WOOD รวม (m³)',
  `hw_comment` varchar(200) DEFAULT NULL,
  `sw_id` int(11) DEFAULT NULL,
  `sw_qty` int(11) DEFAULT NULL,
  `sw_cut` int(11) DEFAULT NULL,
  `sw_split` int(11) DEFAULT NULL,
  `sw_total_m3` decimal(15,7) DEFAULT 0.0000000 COMMENT 'ปริมาตร SAW WOOD รวม (m³)',
  `sw_comment` varchar(200) DEFAULT NULL,
  `rm_m3` decimal(15,7) NOT NULL DEFAULT 0.0000000 COMMENT 'ปริมาตร RAW MATERIAL ใช้จริง (m³)',
  `net_m3` decimal(15,7) NOT NULL DEFAULT 0.0000000 COMMENT 'ปริมาตรสุทธิหลังตัด (m³)',
  `loss_m3` decimal(15,7) NOT NULL DEFAULT 0.0000000 COMMENT 'ปริมาตรสูญเสีย (m³)',
  `loss_per_m3` decimal(15,7) NOT NULL DEFAULT 0.0000000 COMMENT 'เปอร์เซ็นต์ปริมาตรสูญเสีย (m³)',
  PRIMARY KEY (`recipe_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `repair_issue` (
  `repair_id` varchar(50) NOT NULL DEFAULT '',
  `job_id` varchar(50) NOT NULL,
  `part_quantity_reason` varchar(9999) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `create_by` varchar(50) DEFAULT NULL,
  `want_receive` date DEFAULT current_timestamp(),
  `issue_date` timestamp NULL DEFAULT NULL,
  `issued_by` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` enum('สั่งไม้','กำลังเตรียมไม้','รอเบิก','เบิกแล้ว','ยกเลิก') DEFAULT NULL,
  PRIMARY KEY (`repair_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `return_wood_wip` (
  `return_id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` char(50) NOT NULL DEFAULT '',
  `return_detail` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`return_detail`)),
  `return_total_m3` double DEFAULT NULL,
  `return_by` char(50) DEFAULT NULL,
  `recive_by` char(50) DEFAULT NULL,
  `return_date` datetime DEFAULT NULL,
  PRIMARY KEY (`return_id`)
) ENGINE=InnoDB AUTO_INCREMENT=826 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `rm_wood_list` (
  `rm_id` int(11) NOT NULL AUTO_INCREMENT,
  `rm_code` varchar(50) NOT NULL,
  `rm_type` varchar(50) DEFAULT NULL,
  `rm_thickness` int(11) NOT NULL,
  `rm_width` int(11) NOT NULL,
  `rm_length` int(11) NOT NULL,
  `rm_m3` double(15,6) NOT NULL DEFAULT 0.000000,
  PRIMARY KEY (`rm_id`)
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `sw_wood_list` (
  `sw_id` int(11) NOT NULL AUTO_INCREMENT,
  `sw_code` varchar(50) NOT NULL,
  `sw_type` varchar(50) NOT NULL,
  `sw_thickness` int(11) NOT NULL,
  `sw_width` int(11) NOT NULL,
  `sw_length` int(11) NOT NULL,
  `sw_m3` double(15,8) NOT NULL DEFAULT 0.00000000,
  PRIMARY KEY (`sw_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `wip_inventory` (
  `part_id` char(50) NOT NULL COMMENT 'อ้างอิง part_list.part_id',
  `quantity` int(11) NOT NULL DEFAULT 0 COMMENT 'จำนวนคงเหลือ',
  `max` int(11) NOT NULL DEFAULT 0 COMMENT 'จำนวนสูงสุด',
  `min` int(11) NOT NULL DEFAULT 0 COMMENT 'จำนวนต่ำสุด',
  PRIMARY KEY (`part_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `wood_issue` (
  `issue_id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` varchar(50) NOT NULL,
  `job_type` varchar(50) DEFAULT NULL,
  `prod_id` varchar(50) NOT NULL,
  `product_code` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price_value` double NOT NULL DEFAULT 0,
  `total_wage` double NOT NULL DEFAULT 0,
  `wood_wastage` float DEFAULT 0,
  `wood_type` enum('NONFSC','FSCMIX','FSC100') NOT NULL,
  `creation_date` datetime NOT NULL DEFAULT current_timestamp(),
  `create_by` varchar(50) DEFAULT NULL,
  `want_receive` datetime DEFAULT NULL,
  `issue_date` datetime DEFAULT NULL,
  `issued_by` varchar(100) NOT NULL,
  `issue_status` enum('รอยืนยันงาน','สั่งไม้','กำลังเตรียมไม้','รอเบิก','เบิกแล้ว','ปิดสำเร็จ','ยกเลิก','รอยืนยันการสั่งจ่าย','สั่งจ่ายแล้ว') NOT NULL DEFAULT 'สั่งไม้' COMMENT 'สถานะการเบิกไม้: รอเบิก = ยังไม่ได้เบิก, เบิกแล้ว = เบิกไม้เรียบร้อย',
  `issue_type` varchar(50) NOT NULL DEFAULT 'ใบเบิกใช้',
  `remark` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`issue_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3645 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
