-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 19, 2025 at 07:04 PM
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
-- Database: `clinic_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_history`
--

CREATE TABLE `admin_history` (
  `id` int(11) NOT NULL,
  `patient_id` varchar(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `medicine` varchar(50) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `health_history` varchar(100) DEFAULT NULL,
  `purpose_of_visit` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `blood_pressure` varchar(7) DEFAULT NULL,
  `heart_rate` int(11) DEFAULT NULL,
  `blood_oxygen` int(11) DEFAULT NULL,
  `height` float DEFAULT NULL,
  `weight` float DEFAULT NULL,
  `temperature` float DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_history`
--

INSERT INTO `admin_history` (`id`, `patient_id`, `name`, `date`, `medicine`, `quantity`, `health_history`, `purpose_of_visit`, `remarks`, `blood_pressure`, `heart_rate`, `blood_oxygen`, `height`, `weight`, `temperature`, `created_at`, `time_in`, `time_out`) VALUES
(189, '02000177719', 'Mamerto', '2025-05-20', NULL, NULL, 'aa', 'ss', 'df', '120/80', 100, 0, 200, 300, 100, '2025-05-19 16:26:32', '00:26:00', '03:45:00'),
(190, '02000166616', 'Dela Luna', '2025-05-20', NULL, NULL, 'aa', 'ss', 'dd', NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-19 16:43:46', '00:43:00', NULL),
(191, '02000166616', 'Dela Luna', '2025-05-20', NULL, NULL, 'dd', 'ss', 'ff', NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-19 17:02:02', '01:02:00', NULL),
(192, '02000166616', 'Dela Luna', '2025-05-20', NULL, NULL, 'aa', 'ss', 'dd', '120/90', 80, 80, 80, 80, 80, '2025-05-19 17:02:22', '01:02:00', '20:08:00'),
(193, '02000166616', 'Dela Luna', '2025-05-20', NULL, NULL, 'qq', 'ss', 'qq', '120/90', 80, 80, 0, 90, 50, '2025-05-19 17:03:30', '01:03:00', NULL),
(194, '02000166616', 'Dela Luna', '2025-05-20', NULL, NULL, 'aa', 'ss', 'qq', '120/90', 80, 80, 100, 80, 80, '2025-05-19 17:04:07', '01:04:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `clinic_logs`
--

CREATE TABLE `clinic_logs` (
  `id` int(11) NOT NULL,
  `patient_id` varchar(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `age` int(11) NOT NULL,
  `role` varchar(50) NOT NULL,
  `course_section` varchar(20) DEFAULT NULL,
  `specify_role` varchar(50) DEFAULT NULL,
  `blood_pressure` varchar(7) DEFAULT NULL,
  `heart_rate` int(11) DEFAULT NULL,
  `blood_oxygen` int(11) DEFAULT NULL,
  `height` float DEFAULT NULL,
  `weight` float DEFAULT NULL,
  `temperature` float DEFAULT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `purpose_of_visit` varchar(100) DEFAULT NULL,
  `health_history` varchar(100) DEFAULT NULL,
  `medicine` varchar(50) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `remarks` varchar(200) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clinic_logs`
--

INSERT INTO `clinic_logs` (`id`, `patient_id`, `name`, `gender`, `age`, `role`, `course_section`, `specify_role`, `blood_pressure`, `heart_rate`, `blood_oxygen`, `height`, `weight`, `temperature`, `time_in`, `time_out`, `purpose_of_visit`, `health_history`, `medicine`, `quantity`, `remarks`, `date`, `birthday`, `created_at`) VALUES
(132, '02000177719', 'Mamerto', 'Male', 1, 'Student', 'BT803', NULL, '120/80', 100, 0, 200, 300, 100, '00:26:00', '03:45:00', 'ss', 'aa', NULL, NULL, 'df', '2025-05-20', '2024-02-06', '2025-05-19 16:26:32'),
(133, '02000166616', 'Dela Luna', 'Male', 2, 'Student', 'ss', NULL, '120/90', 80, 80, 100, 80, 80, '01:04:00', NULL, 'ss', 'aa', NULL, NULL, 'qq', '2025-05-20', '2023-03-02', '2025-05-19 16:43:46');

-- --------------------------------------------------------

--
-- Table structure for table `guest_requests`
--

CREATE TABLE `guest_requests` (
  `id` int(11) NOT NULL,
  `guest_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT 0,
  `remaining_items` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `name`, `quantity`, `remaining_items`) VALUES
(120, 'Bioflu', 0, 100),
(121, 'Biogesic', 0, 100),
(122, 'Citirizine', 0, 100),
(123, 'Decolgen', 0, 100),
(124, 'Mouthwash', 0, 100);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','guest') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`) VALUES
(3, 'admin1', '$2y$10$POGwfcnTT0AjYqLkzdgxM.IdWG/m26kF3VLcJWZfQtdY7rK91gAPq', 'admin'),
(4, 'guest1', '$2y$10$7Zubw735mC8ogkJnmZP9x.fU.aExTlj0GVm1kYuK2QkZs9ubL5X2S', 'guest');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_history`
--
ALTER TABLE `admin_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clinic_logs`
--
ALTER TABLE `clinic_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `guest_requests`
--
ALTER TABLE `guest_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_request` (`guest_id`,`item_name`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_history`
--
ALTER TABLE `admin_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=195;

--
-- AUTO_INCREMENT for table `clinic_logs`
--
ALTER TABLE `clinic_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=134;

--
-- AUTO_INCREMENT for table `guest_requests`
--
ALTER TABLE `guest_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=125;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
