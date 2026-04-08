-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 07, 2026 at 05:20 AM
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
-- Database: `hyphen_sys`
--

-- --------------------------------------------------------

--
-- Table structure for table `hy_users`
--

CREATE TABLE `hy_users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `staff_id` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(10) NOT NULL,
  `designation` varchar(20) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'user',
  `status` varchar(50) NOT NULL DEFAULT 'active',
  `menu_rights` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`menu_rights`)),
  `image_url` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hy_users`
--

INSERT INTO `hy_users` (`id`, `username`, `staff_id`, `password`, `email`, `phone`, `designation`, `role`, `status`, `menu_rights`, `image_url`, `created_at`) VALUES
(1, 'Steve Ding', '00001', '$2y$10$CrrkFEwy8VxW9CrA40qIbuV6p1oykHgxML7b7zh22neRm2mpe8Gne', 'steve.ding@hyphen.com', '1111', 'System Admin', 'System Admin', 'active', '[\"1\",\"2\",\"99\"]', '00000.jpg', '2026-04-02 08:20:39'),
(2, 'System Admin', '00002', '$2y$10$9urOkAcpKFCrXnr7Eia7L.jtn/WKLNZrPPzfvS.MDcaJj3PuJx2GW', 'developer@dev.com', '222000111', 'Developer', 'System Admin', 'active', '[\"1\",\"99\"]', '00001.jpg', '2026-04-06 06:14:13'),
(3, 'System Admin2', '00003', '$2y$10$SFLti6X4lCjJ5zKHvtIc9O/stYSZ2cHeHJNR/0Yz01.lFbcy278vi', 'admin@dev.com', '2222333311', 'System Admin', 'System Admin', 'active', '[\"1\",\"2\",\"99\"]', '00003_db073c.jpg', '2026-04-06 06:23:50'),
(4, 'Tester', '00004', '$2y$10$nPaxx7/f1P0otaF5mCkID.z/wgK0yFSSsx5PCBo2i1MdnaYPYfjGi', 'tester@adv.com', '11111111', 'Tester', 'Employee', 'active', '[\"1\",\"99\"]', '00000.jpg', '2026-04-06 09:03:21');

-- --------------------------------------------------------

--
-- Table structure for table `hy_user_menu`
--

CREATE TABLE `hy_user_menu` (
  `id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL,
  `link` varchar(10) NOT NULL,
  `menu_id` varchar(50) NOT NULL,
  `menu_name` varchar(255) NOT NULL,
  `menu_icon` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hy_user_menu`
--

INSERT INTO `hy_user_menu` (`id`, `category`, `link`, `menu_id`, `menu_name`, `menu_icon`, `created_at`) VALUES
(1, 'Main', 'home', '1', 'Home', 'ri-home-office-line', '2026-04-01 07:52:31'),
(2, 'System Settings', 'settings', '99', 'System Settings', 'ri-user-settings-fill', '2026-04-01 08:40:17'),
(3, 'HR Management', 'hr', '2', 'HR Management', 'ri-user-2-line', '2026-04-02 03:46:11');

-- --------------------------------------------------------

--
-- Table structure for table `hy_user_pages`
--

CREATE TABLE `hy_user_pages` (
  `id` int(11) NOT NULL,
  `menu_id` varchar(50) NOT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `page_name` varchar(100) DEFAULT NULL,
  `page_url` varchar(100) NOT NULL,
  `page_order` varchar(10) DEFAULT '0',
  `permission_target_page_id` int(11) DEFAULT NULL,
  `required_ability` enum('view','add','edit','delete') NOT NULL DEFAULT 'view',
  `show_in_sidebar` tinyint(1) NOT NULL DEFAULT 1,
  `show_in_breadcrumb` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hy_user_pages`
--

INSERT INTO `hy_user_pages` (`id`, `menu_id`, `display_name`, `page_name`, `page_url`, `page_order`, `permission_target_page_id`, `required_ability`, `show_in_sidebar`, `show_in_breadcrumb`, `created_at`) VALUES
(2, '99', 'System Dashboard', 'system_dashboard', 'sys_admin/system_dashboard', '99.1.0', 2, 'view', 1, 1, '2026-04-01 08:43:30'),
(3, '99', 'System Settings', NULL, '', '99.2.0', 3, 'view', 1, 0, '2026-04-01 08:45:33'),
(4, '99', 'System Menu', 'system_menu', 'sys_admin/system_menu', '99.2.1', 4, 'view', 1, 1, '2026-04-01 08:50:08'),
(9, '1', 'User Dashboard', 'user_dashboard', 'home/user_dashboard', '1.1.0', 9, 'view', 1, 1, '2026-04-02 07:51:51'),
(10, '99', 'System User', 'system_users', 'sys_admin/system_users', '99.2.2', 10, 'view', 1, 1, '2026-04-02 08:13:45'),
(11, '99', 'New User', 'system_users_new', 'sys_admin/system_users_new', '99.2.3', 10, 'add', 0, 1, '2026-04-06 02:30:33'),
(12, '99', 'Edit User', 'system_users_edit', 'sys_admin/system_users_edit', '99.2.4', 10, 'edit', 0, 0, '2026-04-06 08:41:34');

-- --------------------------------------------------------

--
-- Table structure for table `hy_user_permissions`
--

CREATE TABLE `hy_user_permissions` (
  `id` int(11) NOT NULL,
  `page_id` int(11) NOT NULL,
  `staff_id` varchar(50) NOT NULL,
  `can_view` tinyint(1) DEFAULT 0,
  `can_add` tinyint(1) DEFAULT 0,
  `can_edit` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0,
  `status` varchar(50) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hy_user_permissions`
--

INSERT INTO `hy_user_permissions` (`id`, `page_id`, `staff_id`, `can_view`, `can_add`, `can_edit`, `can_delete`, `status`, `created_at`) VALUES
(1, 9, '00002', 1, 0, 0, 0, 'active', '2026-04-06 06:14:13'),
(2, 2, '00002', 1, 0, 0, 0, 'active', '2026-04-06 06:14:13'),
(3, 3, '00002', 1, 0, 0, 0, 'active', '2026-04-06 06:14:13'),
(4, 4, '00002', 1, 0, 0, 0, 'active', '2026-04-06 06:14:13'),
(5, 10, '00002', 1, 0, 0, 0, 'active', '2026-04-06 06:14:13'),
(6, 11, '00002', 1, 0, 0, 0, 'active', '2026-04-06 06:14:13'),
(124, 9, '00001', 1, 1, 1, 1, 'active', '2026-04-06 08:54:50'),
(125, 2, '00001', 1, 1, 1, 1, 'active', '2026-04-06 08:54:51'),
(126, 3, '00001', 1, 1, 1, 1, 'active', '2026-04-06 08:54:51'),
(127, 4, '00001', 1, 1, 1, 1, 'active', '2026-04-06 08:54:51'),
(128, 10, '00001', 1, 1, 1, 1, 'active', '2026-04-06 08:54:51'),
(129, 11, '00001', 1, 1, 1, 1, 'active', '2026-04-06 08:54:51'),
(130, 12, '00001', 1, 1, 1, 1, 'active', '2026-04-06 08:54:51'),
(138, 9, '00003', 1, 1, 1, 1, 'active', '2026-04-06 08:58:00'),
(139, 2, '00003', 1, 1, 1, 1, 'active', '2026-04-06 08:58:01'),
(140, 3, '00003', 1, 1, 1, 1, 'active', '2026-04-06 08:58:01'),
(141, 4, '00003', 1, 1, 1, 1, 'active', '2026-04-06 08:58:01'),
(142, 10, '00003', 1, 1, 1, 1, 'active', '2026-04-06 08:58:01'),
(143, 11, '00003', 1, 1, 1, 1, 'active', '2026-04-06 08:58:01'),
(144, 12, '00003', 1, 1, 1, 1, 'active', '2026-04-06 08:58:01'),
(152, 9, '00004', 1, 0, 0, 0, 'active', '2026-04-06 09:04:29'),
(153, 2, '00004', 0, 0, 0, 0, 'active', '2026-04-06 09:04:29'),
(154, 3, '00004', 1, 0, 0, 0, 'active', '2026-04-06 09:04:29'),
(155, 4, '00004', 1, 0, 0, 0, 'active', '2026-04-06 09:04:29'),
(156, 10, '00004', 1, 0, 0, 0, 'active', '2026-04-06 09:04:29'),
(157, 11, '00004', 1, 0, 0, 0, 'active', '2026-04-06 09:04:29'),
(158, 12, '00004', 1, 0, 0, 0, 'active', '2026-04-06 09:04:29');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `hy_users`
--
ALTER TABLE `hy_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `staff_id` (`staff_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `hy_user_menu`
--
ALTER TABLE `hy_user_menu`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `hy_user_pages`
--
ALTER TABLE `hy_user_pages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hy_user_pages_permission_target_page_id` (`permission_target_page_id`);

--
-- Indexes for table `hy_user_permissions`
--
ALTER TABLE `hy_user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `page_id` (`page_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `hy_users`
--
ALTER TABLE `hy_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `hy_user_menu`
--
ALTER TABLE `hy_user_menu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `hy_user_pages`
--
ALTER TABLE `hy_user_pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `hy_user_permissions`
--
ALTER TABLE `hy_user_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=159;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `hy_user_pages`
--
ALTER TABLE `hy_user_pages`
  ADD CONSTRAINT `fk_hy_user_pages_permission_target_page_id` FOREIGN KEY (`permission_target_page_id`) REFERENCES `hy_user_pages` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `hy_user_permissions`
--
ALTER TABLE `hy_user_permissions`
  ADD CONSTRAINT `hy_user_permissions_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `hy_user_pages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `hy_user_permissions_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `hy_users` (`staff_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
