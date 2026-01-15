-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 15, 2026 at 03:29 PM
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
-- Database: `ams11`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) NOT NULL,
  `created_at` datetime NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(60) NOT NULL,
  `meta` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `created_at`, `user_id`, `action`, `meta`) VALUES
(1, '2026-01-15 13:23:02', 1, 'login', '{\"ip\":\"::1\"}'),
(2, '2026-01-15 13:41:04', 1, 'logout', '{}'),
(3, '2026-01-15 13:41:22', 1, 'login', '{\"ip\":\"::1\"}'),
(4, '2026-01-15 13:41:55', 1, 'create_user', '{\"username\":\"wiwit\",\"role\":\"admin\"}'),
(5, '2026-01-15 13:42:05', 1, 'logout', '{}'),
(6, '2026-01-15 13:42:12', 2, 'login', '{\"ip\":\"::1\"}'),
(7, '2026-01-15 13:43:46', 2, 'create_user', '{\"username\":\"Ahmad\",\"role\":\"dokter\"}'),
(8, '2026-01-15 13:43:51', 2, 'logout', '{}'),
(9, '2026-01-15 13:44:01', 3, 'login', '{\"ip\":\"::1\"}'),
(10, '2026-01-15 14:41:48', 3, 'logout', '{}'),
(11, '2026-01-15 14:42:41', 1, 'login', '{\"ip\":\"::1\"}'),
(12, '2026-01-15 15:09:35', 1, 'logout', '{}'),
(13, '2026-01-15 15:09:43', 3, 'login', '{\"ip\":\"::1\"}');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` bigint(20) NOT NULL,
  `mrn` varchar(30) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `dob` date DEFAULT NULL,
  `gender` enum('L','P') NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `mrn`, `full_name`, `dob`, `gender`, `address`, `created_at`) VALUES
(2, '2026000001', 'athallah', '2015-01-27', 'L', 'dapur adena', '2026-01-15 13:44:23');

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` bigint(20) NOT NULL,
  `visit_id` bigint(20) NOT NULL,
  `rx_no` varchar(30) NOT NULL,
  `content` mediumtext NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescriptions`
--

INSERT INTO `prescriptions` (`id`, `visit_id`, `rx_no`, `content`, `created_at`) VALUES
(2, 2, 'RX202601150001', 'amox', '2026-01-15 14:52:00');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `key` varchar(80) NOT NULL,
  `value` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key`, `value`) VALUES
(1, 'brand_title', 'Praktek dr. Agus'),
(2, 'brand_badge', 'Adena Medical System'),
(3, 'footer_text', '© 2026 Adena Medical System ver 1.1'),
(4, 'clinic_name', 'Praktek dr. Agus'),
(5, 'clinic_address', ''),
(6, 'clinic_sip', ''),
(7, 'logo_path', ''),
(8, 'signature_path', '/storage/uploads/signature/signature_20260115_133429_4d11f3.png'),
(9, 'custom_css', '/* Fix dropdown select pada dark theme */\r\nselect option {\r\n  color: #000 !important;\r\n  background: #fff !important;\r\n}\r\n\r\nselect {\r\n  color: #e5e7eb;\r\n  background-color: rgba(255,255,255,.06);\r\n}\r\n');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','dokter','perawat','sekretariat') NOT NULL DEFAULT 'dokter',
  `full_name` varchar(120) DEFAULT NULL,
  `sip` varchar(80) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `full_name`, `sip`, `phone`, `email`, `signature_path`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'adyto', '$2y$10$4lfyRebqAKBULbNkPcj2O.FYIwedVVB72d4whidq0YNws1drpSDaa', 'admin', 'dr Agus Darwiyanto,Sp.Rad Subsp.RI(K)', '', '', '', NULL, 1, '2026-01-15 19:22:52', '2026-01-15 13:34:05'),
(2, 'wiwit', '$2y$10$Mbdf1awHlCbs5krbnCVRrujl6FO0dinLMu1.gus5xCCL3UwULX5FC', 'admin', 'Wiwit S Yuniasih', NULL, NULL, NULL, NULL, 1, '2026-01-15 13:41:55', NULL),
(3, 'Ahmad', '$2y$10$zhTKWTsxbQM1NtwsR5X8I.grctwzCbu.DRTlPdnLh/bdbXldw6hbm', 'dokter', 'dr Ahmad Darwiyanto', NULL, NULL, NULL, NULL, 1, '2026-01-15 13:43:46', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `visits`
--

CREATE TABLE `visits` (
  `id` bigint(20) NOT NULL,
  `patient_id` bigint(20) NOT NULL,
  `visit_no` varchar(30) NOT NULL,
  `visit_date` datetime NOT NULL,
  `anamnesis` mediumtext DEFAULT NULL,
  `physical_exam` mediumtext DEFAULT NULL,
  `usg_report` mediumtext DEFAULT NULL,
  `therapy` mediumtext DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `visits`
--

INSERT INTO `visits` (`id`, `patient_id`, `visit_no`, `visit_date`, `anamnesis`, `physical_exam`, `usg_report`, `therapy`, `doctor_id`, `signature_path`, `created_at`) VALUES
(2, 2, '202601150001', '2026-01-15 13:44:59', 'berat', 'endut', '', 'Lanjutkan terapi dari dokter sebelumnya', 3, NULL, '2026-01-15 13:44:59');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mrn` (`mrn`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rx_no` (`rx_no`),
  ADD KEY `visit_id` (`visit_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key` (`key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `visits`
--
ALTER TABLE `visits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `visit_no` (`visit_no`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `visit_date` (`visit_date`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `visits`
--
ALTER TABLE `visits`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `visits`
--
ALTER TABLE `visits`
  ADD CONSTRAINT `visits_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
