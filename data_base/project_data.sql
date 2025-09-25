-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 20, 2025 at 08:46 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `project_data`
--

-- --------------------------------------------------------

--
-- Table structure for table `item_details`
--

CREATE TABLE `item_details` (
  `detail_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `area_id` int(11) NOT NULL,
  `expire_date` date NOT NULL,
  `barcode` varchar(255) DEFAULT NULL,
  `item_img` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `notification_days` int(11) DEFAULT NULL COMMENT 'จำนวนวันที่แจ้งเตือนก่อนหมดอายุ (NULL = ใช้ค่าจากตาราง items)',
  `status` enum('active','disposed','expired') DEFAULT 'active' COMMENT 'สถานะของชิ้นนี้ (active=กำลังเก็บ/ยังใช้ได้, disposed=ใช้หมดแล้ว, expired=ทิ้ง/หมดอายุแล้ว)',
  `used_date` datetime DEFAULT NULL COMMENT 'วันที่ใช้หมดหรือทิ้งชิ้นนี้',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างข้อมูล',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขข้อมูลล่าสุด'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `item_details`
--

INSERT INTO `item_details` (`detail_id`, `item_id`, `area_id`, `expire_date`, `barcode`, `item_img`, `quantity`, `notification_days`, `status`, `used_date`, `created_at`, `updated_at`) VALUES
(118, 10140, 2, '2025-09-30', '', 'item_68b4056b34035.jpg', 1, 7, 'active', NULL, '2025-08-31 08:18:51', '2025-09-01 20:24:00'),
(119, 10140, 2, '2025-09-30', '', 'item_68b4056b34035.jpg', 1, 7, 'active', NULL, '2025-08-31 08:18:51', '2025-09-01 20:24:00'),
(120, 10140, 2, '2025-09-30', '', 'item_68b4056b34035.jpg', 1, 7, 'active', NULL, '2025-08-31 08:18:51', '2025-09-01 20:24:00'),
(130, 10150, 2, '2025-09-09', '', 'dry_food_default.png', 1, 7, 'disposed', '2025-09-13 16:41:52', '2025-09-01 20:46:13', '2025-09-13 09:41:52'),
(131, 10150, 3, '2025-09-10', '', 'dry_food_default.png', 1, 7, 'expired', '2025-09-18 13:38:02', '2025-09-01 20:46:13', '2025-09-18 06:38:02'),
(132, 10150, 9, '2025-09-12', '', 'dry_food_default.png', 1, 7, 'active', NULL, '2025-09-01 20:46:13', '2025-09-02 10:06:48'),
(134, 10152, 2, '2025-09-11', '8850051019573', 'item_68b9533134683.jpg', 1, 7, 'active', NULL, '2025-09-04 08:52:01', '2025-09-04 08:52:56'),
(138, 10154, 9, '2025-08-07', '8858891301728-2', 'item_68babf6db6d3a.jpg', 1, 7, 'disposed', '2025-08-13 16:42:14', '2025-09-05 10:45:43', '2025-09-20 06:44:11'),
(139, 10154, 9, '2025-09-13', '8858891301728-2', 'item_68babf6db6d3a.jpg', 1, 7, 'disposed', '2025-08-09 13:38:26', '2025-09-05 10:45:43', '2025-09-20 06:45:48'),
(140, 10154, 9, '2025-09-14', '8858891301728-2', 'item_68babf6db6d3a.jpg', 1, 7, 'expired', '2025-08-19 13:38:33', '2025-09-05 10:45:43', '2025-09-20 06:45:57'),
(141, 10155, 4, '2028-06-30', '8850002030312', 'item_68bac5952d8c1.jpg', 1, 7, 'active', NULL, '2025-09-05 11:11:57', '2025-09-05 11:12:21'),
(142, 10155, 3, '2028-06-30', '8850002030312', 'item_68bac5952d8c1.jpg', 1, 7, 'active', NULL, '2025-09-05 11:11:57', '2025-09-05 11:12:21'),
(267, 10174, 3, '2026-04-18', '8850228001028', 'item_68cba856a4973.jpg', 1, 7, 'active', NULL, '2025-09-18 06:36:06', '2025-09-18 06:36:06'),
(268, 10174, 3, '2026-04-18', '8850228001028', 'item_68cba856a4973.jpg', 1, 7, 'active', NULL, '2025-09-18 06:36:06', '2025-09-18 06:36:06'),
(269, 10174, 3, '2026-04-18', '8850228001028', 'item_68cba856a4973.jpg', 1, 7, 'active', NULL, '2025-09-18 06:36:06', '2025-09-18 06:36:06'),
(270, 10174, 3, '2026-04-18', '8850228001028', 'item_68cba856a4973.jpg', 1, 7, 'active', NULL, '2025-09-18 06:36:06', '2025-09-18 06:36:06'),
(271, 10174, 3, '2026-04-18', '8850228001028', 'item_68cba856a4973.jpg', 1, 7, 'active', NULL, '2025-09-18 06:36:06', '2025-09-18 06:36:06'),
(272, 10174, 3, '2026-04-18', '8850228001028', 'item_68cba856a4973.jpg', 1, 7, 'active', NULL, '2025-09-18 06:36:06', '2025-09-18 06:36:06'),
(273, 10174, 3, '2026-04-18', '8850228001028', 'item_68cba856a4973.jpg', 1, 7, 'active', NULL, '2025-09-18 06:36:06', '2025-09-18 06:36:06'),
(274, 10174, 3, '2026-04-18', '8850228001028', 'item_68cba856a4973.jpg', 1, 7, 'active', NULL, '2025-09-18 06:36:06', '2025-09-18 06:36:06'),
(275, 10174, 3, '2026-04-18', '8850228001028', 'item_68cba856a4973.jpg', 1, 7, 'active', NULL, '2025-09-18 06:36:06', '2025-09-18 06:36:06'),
(276, 10174, 3, '2026-04-18', '8850228001028', 'item_68cba856a4973.jpg', 1, 7, 'active', NULL, '2025-09-18 06:36:06', '2025-09-18 06:36:06'),
(277, 10174, 3, '2026-04-18', '8850228001028', 'item_68cba856a4973.jpg', 1, 7, 'active', NULL, '2025-09-18 06:36:06', '2025-09-18 06:36:06'),
(278, 10174, 3, '2026-04-18', '8850228001028', 'item_68cba856a4973.jpg', 1, 7, 'active', NULL, '2025-09-18 06:36:06', '2025-09-18 06:36:06'),
(279, 10174, 3, '2026-04-18', '8850228001028', 'item_68cba856a4973.jpg', 1, 7, 'active', NULL, '2025-09-18 06:36:06', '2025-09-18 06:36:06'),
(280, 10174, 3, '2026-04-18', '8850228001028', 'item_68cba856a4973.jpg', 1, 7, 'active', NULL, '2025-09-18 06:36:06', '2025-09-18 06:36:06'),
(282, 10176, 9, '2025-09-25', '8850425005416', 'item_68cbc305549ad.jpg', 1, 7, 'active', NULL, '2025-09-18 08:11:12', '2025-09-18 08:29:57'),
(283, 10176, 9, '2025-09-25', '8850425005416', 'item_68cbc305549ad.jpg', 1, 7, 'active', NULL, '2025-09-18 08:11:12', '2025-09-18 08:29:57'),
(284, 10176, 3, '2025-09-26', '8850425005416', 'item_68cbc305549ad.jpg', 1, 7, 'active', NULL, '2025-09-18 08:11:12', '2025-09-18 08:29:57'),
(285, 10176, 9, '2025-09-27', '8850425005416', 'item_68cbc305549ad.jpg', 1, 7, 'disposed', '2025-09-19 21:18:09', '2025-09-18 08:11:12', '2025-09-19 14:18:09'),
(286, 10177, 36, '2025-09-26', '', 'medicine _default.png', 1, 7, 'disposed', '2025-09-20 00:19:58', '2025-09-19 14:16:55', '2025-09-19 17:19:58'),
(287, 10177, 36, '2025-10-01', '', 'medicine _default.png', 1, 7, 'active', NULL, '2025-09-19 14:16:55', '2025-09-19 14:17:29'),
(288, 10177, 3, '2025-09-27', '', 'medicine _default.png', 1, 7, 'active', NULL, '2025-09-19 14:16:55', '2025-09-19 14:16:55'),
(289, 10177, 4, '2025-09-28', '', 'medicine _default.png', 1, 7, 'active', NULL, '2025-09-19 14:16:55', '2025-09-19 14:16:55'),
(290, 10177, 1, '2025-09-29', '', 'medicine _default.png', 1, 7, 'active', NULL, '2025-09-19 14:16:55', '2025-09-19 14:16:55'),
(291, 10177, 25, '2025-09-30', '', 'medicine _default.png', 1, 7, 'active', NULL, '2025-09-19 14:16:55', '2025-09-19 14:16:55'),
(292, 10178, 37, '2025-09-26', '8850329120116', 'medicine _default.png', 1, 7, 'active', NULL, '2025-09-19 17:02:47', '2025-09-19 17:02:47'),
(293, 10178, 37, '2025-09-26', '8850329120116', 'medicine _default.png', 1, 7, 'active', NULL, '2025-09-19 17:02:47', '2025-09-19 17:02:47'),
(294, 10178, 38, '2025-09-27', '8850329120116', 'medicine _default.png', 1, 7, 'active', NULL, '2025-09-19 17:02:47', '2025-09-19 17:02:47'),
(295, 10178, 4, '2025-09-27', '8850329120116', 'medicine _default.png', 1, 7, 'active', NULL, '2025-09-19 17:02:47', '2025-09-19 17:02:47'),
(296, 10178, 3, '2025-09-28', '8850329120116', 'medicine _default.png', 1, 7, 'active', NULL, '2025-09-19 17:02:47', '2025-09-19 17:02:47'),
(297, 10178, 3, '2025-09-26', '8850329120116', 'medicine _default.png', 1, 7, 'active', NULL, '2025-09-19 17:02:47', '2025-09-19 17:02:47');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `item_details`
--
ALTER TABLE `item_details`
  ADD PRIMARY KEY (`detail_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `area_id` (`area_id`),
  ADD KEY `idx_notification_days` (`notification_days`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `item_details`
--
ALTER TABLE `item_details`
  MODIFY `detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=298;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `item_details`
--
ALTER TABLE `item_details`
  ADD CONSTRAINT `item_details_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `item_details_ibfk_2` FOREIGN KEY (`area_id`) REFERENCES `areas` (`area_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
