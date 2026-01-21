-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jan 17, 2026 at 02:09 PM
-- Server version: 11.4.8-MariaDB-cll-lve
-- PHP Version: 8.4.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `adey8293_ams`
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

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
(13, '2026-01-15 15:09:43', 3, 'login', '{\"ip\":\"::1\"}'),
(14, '2026-01-15 15:36:26', 1, 'login', '{\"ip\":\"::1\"}'),
(15, '2026-01-15 15:37:35', 1, 'create_user', '{\"username\":\"bintang\",\"role\":\"sekretariat\"}'),
(16, '2026-01-15 15:37:42', 1, 'logout', '{}'),
(17, '2026-01-15 15:37:49', 4, 'login', '{\"ip\":\"::1\"}'),
(18, '2026-01-15 15:39:01', 4, 'logout', '{}'),
(19, '2026-01-15 15:41:10', 1, 'login', '{\"ip\":\"::1\"}'),
(20, '2026-01-16 03:49:49', 1, 'login', '{\"ip\":\"::1\"}'),
(21, '2026-01-16 09:02:04', 3, 'login', '{\"ip\":\"::1\"}'),
(22, '2026-01-16 09:03:32', 3, 'logout', '{}'),
(23, '2026-01-16 09:03:42', 1, 'login', '{\"ip\":\"::1\"}'),
(24, '2026-01-16 09:04:41', 1, 'create_user', '{\"username\":\"Indrawan10\",\"role\":\"admin\"}'),
(25, '2026-01-16 09:10:57', 1, 'delete_user', '{\"target_id\":4}'),
(26, '2026-01-16 12:07:35', 1, 'login', '{\"ip\":\"::1\"}'),
(27, '2026-01-16 21:16:29', 1, 'login', '{\"ip\":\"180.254.164.192\"}'),
(28, '2026-01-16 21:33:25', 5, 'login', '{\"ip\":\"182.2.39.152\"}'),
(29, '2026-01-16 21:42:36', 1, 'login', '{\"ip\":\"146.75.160.26\"}'),
(30, '2026-01-16 22:55:59', 1, 'login', '{\"ip\":\"180.254.164.192\"}'),
(31, '2026-01-17 06:22:06', 1, 'login', '{\"ip\":\"180.254.160.70\"}'),
(32, '2026-01-17 07:47:58', 1, 'login', '{\"ip\":\"2a09:bac2:39b9:18d2::279:40\"}'),
(33, '2026-01-17 10:26:35', 1, 'login', '{\"ip\":\"125.167.57.21\"}'),
(34, '2026-01-17 13:57:57', 1, 'login', '{\"ip\":\"125.167.57.21\"}');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `mrn`, `full_name`, `dob`, `gender`, `address`, `created_at`) VALUES
(4, '2026000001', 'Athallah', '2015-01-27', 'L', '', '2026-01-17 10:27:00');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `referrals`
--

CREATE TABLE `referrals` (
  `id` bigint(20) NOT NULL,
  `visit_id` bigint(20) DEFAULT NULL,
  `patient_id` bigint(20) NOT NULL,
  `sender_doctor_id` int(11) NOT NULL,
  `referral_no` varchar(32) NOT NULL,
  `referred_to_doctor` varchar(120) NOT NULL,
  `referred_to_specialty` varchar(120) NOT NULL,
  `diagnosis` mediumtext NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `key` varchar(80) NOT NULL,
  `value` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `full_name`, `sip`, `phone`, `email`, `signature_path`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'adyto', '$2y$10$4lfyRebqAKBULbNkPcj2O.FYIwedVVB72d4whidq0YNws1drpSDaa', 'admin', 'dr Agus Darwiyanto,Sp.Rad Subsp.RI(K)', '', '', '', '/storage/uploads/signature/users/sig_user_20260116_212436_480506.png', 1, '2026-01-15 19:22:52', '2026-01-16 21:24:36'),
(2, 'wiwit', '$2y$10$Mbdf1awHlCbs5krbnCVRrujl6FO0dinLMu1.gus5xCCL3UwULX5FC', 'admin', 'Wiwit S Yuniasih', NULL, NULL, NULL, NULL, 1, '2026-01-15 13:41:55', NULL),
(3, 'Ahmad', '$2y$10$zhTKWTsxbQM1NtwsR5X8I.grctwzCbu.DRTlPdnLh/bdbXldw6hbm', 'dokter', 'dr Ahmad Darwiyanto', NULL, NULL, NULL, NULL, 1, '2026-01-15 13:43:46', NULL),
(5, 'Indrawan10', '$2y$10$xksgokDdpiqezcrHiXKqUebk0jjpvr3CtJordsgLDJUHbcz0uR3fS', 'admin', 'Bintang', NULL, NULL, NULL, NULL, 1, '2026-01-16 09:04:41', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `usg_images`
--

CREATE TABLE `usg_images` (
  `id` bigint(20) NOT NULL,
  `visit_id` bigint(20) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `caption` varchar(120) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Dumping data for table `visits`
--

INSERT INTO `visits` (`id`, `patient_id`, `visit_no`, `visit_date`, `anamnesis`, `physical_exam`, `usg_report`, `therapy`, `doctor_id`, `signature_path`, `created_at`, `updated_at`) VALUES
(6, 4, '202601170001', '2026-01-17 14:00:52', 'dbn', 'dbn', 'Pemeriksaan: USG Abdomen.\r\n\r\nHepar :\r\nUkuran tidak membesar, sudut tajam, permukaan rata, tekstur parenkim homogen halus, kapsul tidak menebal, tidak tampak bayangan nodul / massa. Vena porta dan vena hepatika tidak melebar. Vena gastrika sinistra (coronary vein) tidak melebar. Tidak tampak koleksi cairan di sekitarnya. \r\nKandung empedu : \r\nBesar normal, dinding normal, tidak tampak batu / sludge.\r\nDuktus biliaris intra / ekstrahepatal: tidak melebar, tidak tampak bayangan hiperekhoik dengan acoustic shadow.\r\nSpleen :	\r\nUkuran tidak membesar, tekstur parenkim homogen halus, tidak tampak nodul / massa. Vena lienalis tidak melebar. \r\nPankreas :\r\nBesar normal, kontur normal, tekstur parenkim homogen, tidak tampak massa / kalsifikasi. Duktur pankreatikus tidak melebar.\r\nGinjal kanan:\r\nUkuran normal, kontur normal, parenkim normal, intensitas gema normal. Batas tekstur parenkim dengan central echocomplek normal. Tidak tampak bayangan hiperekhoik dengan acoustic shadow. Sistem pelvokalises tidak melebar. Ureter tidak terdeteksi.\r\nGinjal kiri :\r\nUkuran normal, kontur normal, parenkim normal, intensitas gema normal. Batas tekstur parenkim dengan central echocomplek normal. Tidak tampak bayangan hiperekhoik dengan acoustic shadow. Sistem pelvokalises tidak melebar. Ureter tidak terdeteksi.\r\nVesica urinaria:\r\ndinding tidak menebal, reguler, tidak tampak bayangan hiperekhoik dengan acoustic shadow/massa.\r\n\r\nKesan : \r\nUSG hepar, kandung empedu, spleen, pankreas, ginjal kanan/kiri, vesica urinaria, saat ini tak tampak kelainan.', 'Lanjutkan terapi dari dokter sebelumnya', 1, NULL, '2026-01-17 14:00:52', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `visit_queue`
--

CREATE TABLE `visit_queue` (
  `id` bigint(20) NOT NULL,
  `patient_id` bigint(20) NOT NULL,
  `queue_date` date NOT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'new',
  `handled_visit_id` bigint(20) DEFAULT NULL,
  `created_by` bigint(20) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Dumping data for table `visit_queue`
--

INSERT INTO `visit_queue` (`id`, `patient_id`, `queue_date`, `status`, `handled_visit_id`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 2, '2026-01-16', 'done', 3, 1, '2026-01-16 05:29:11', '2026-01-16 05:29:55'),
(2, 3, '2026-01-16', 'done', 4, 1, '2026-01-16 21:21:40', '2026-01-16 21:22:18'),
(3, 3, '2026-01-16', 'done', 5, 1, '2026-01-16 22:56:20', NULL),
(4, 4, '2026-01-17', 'done', 6, 1, '2026-01-17 10:27:09', NULL);

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
-- Indexes for table `referrals`
--
ALTER TABLE `referrals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `referral_no` (`referral_no`),
  ADD KEY `idx_visit_id` (`visit_id`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_sender_doctor_id` (`sender_doctor_id`);

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
-- Indexes for table `usg_images`
--
ALTER TABLE `usg_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_visit_id` (`visit_id`);

--
-- Indexes for table `visits`
--
ALTER TABLE `visits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `visit_no` (`visit_no`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `visit_date` (`visit_date`);

--
-- Indexes for table `visit_queue`
--
ALTER TABLE `visit_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_queue_date` (`queue_date`),
  ADD KEY `idx_patient_date` (`patient_id`,`queue_date`),
  ADD KEY `idx_status_date` (`status`,`queue_date`),
  ADD KEY `idx_handled_visit` (`handled_visit_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `referrals`
--
ALTER TABLE `referrals`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `usg_images`
--
ALTER TABLE `usg_images`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `visits`
--
ALTER TABLE `visits`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `visit_queue`
--
ALTER TABLE `visit_queue`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `referrals`
--
ALTER TABLE `referrals`
  ADD CONSTRAINT `fk_ref_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ref_sender` FOREIGN KEY (`sender_doctor_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_ref_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `usg_images`
--
ALTER TABLE `usg_images`
  ADD CONSTRAINT `fk_usg_images_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `visits`
--
ALTER TABLE `visits`
  ADD CONSTRAINT `visits_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
