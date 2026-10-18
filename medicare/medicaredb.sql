-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 25, 2026 at 08:34 PM
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
-- Database: `medicare`
--

DELIMITER $$
--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `get_pending_purchase_quantity` (`product_name_param` VARCHAR(255), `company_param` VARCHAR(255)) RETURNS INT(11) DETERMINISTIC BEGIN
    DECLARE pending_qty INT;
    
    SELECT COALESCE(SUM(quantity), 0) INTO pending_qty
    FROM purchase_master 
    WHERE product_name = product_name_param 
    AND company_name = company_param
    AND status = 'pending';
    
    RETURN pending_qty;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `is_consultation_paid` (`appointment_id` INT, `patient_id` INT) RETURNS TINYINT(1) DETERMINISTIC READS SQL DATA BEGIN
    DECLARE payment_count INT;
    
    SELECT COUNT(*) INTO payment_count
    FROM billing_payments
    WHERE reference_id = appointment_id
    AND pid = patient_id
    AND type = 'consultation'
    AND status = 'paid';
    
    RETURN payment_count > 0;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `is_pharmacy_bill_paid` (`billing_id` INT, `patient_id` INT) RETURNS TINYINT(1) DETERMINISTIC READS SQL DATA BEGIN
    DECLARE payment_count INT;
    
    SELECT COUNT(*) INTO payment_count
    FROM billing_payments
    WHERE reference_id = billing_id
    AND pid = patient_id
    AND type = 'pharmacy'
    AND status = 'paid';
    
    RETURN payment_count > 0;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `aemail` varchar(255) NOT NULL,
  `apassword` varchar(255) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`aemail`, `apassword`) VALUES
('admin@edoc.com', '123');

-- --------------------------------------------------------

--
-- Table structure for table `appointment`
--

CREATE TABLE `appointment` (
  `appoid` int(11) NOT NULL,
  `pid` int(10) DEFAULT NULL,
  `apponum` int(3) DEFAULT NULL,
  `scheduleid` int(10) DEFAULT NULL,
  `appodate` date DEFAULT NULL,
  `billing_status` enum('pending','paid') DEFAULT 'pending',
  `status` enum('Scheduled','Complete','Cancelled') DEFAULT 'Scheduled',
  `workflow_stage` enum('scheduled','consultation_complete','prescription_ready','dispensed','payment_pending','payment_complete','completed') DEFAULT 'scheduled',
  `completed_by` varchar(100) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `appointment`
--

INSERT INTO `appointment` (`appoid`, `pid`, `apponum`, `scheduleid`, `appodate`, `billing_status`, `status`, `workflow_stage`, `completed_by`, `completed_at`) VALUES
(10, 5, 1, 15, '2025-11-17', 'paid', 'Complete', 'scheduled', 'superadmin', '2025-11-17 23:16:53'),
(12, 6, 1, 16, '2025-11-18', 'paid', 'Complete', 'scheduled', 'Admin', '2025-11-18 10:34:31'),
(13, 4, 2, 16, '2025-11-18', 'paid', 'Complete', 'payment_complete', 'superadmin', '2025-11-18 12:09:39'),
(14, 5, 1, 17, '2025-11-20', 'paid', 'Complete', 'payment_pending', 'Admin', '2025-11-20 16:55:17'),
(15, 3, 2, 17, '2025-11-20', 'paid', 'Complete', 'payment_complete', 'Admin', '2025-11-20 17:02:03'),
(18, 6, 1, 18, '2025-11-21', 'paid', 'Complete', 'completed', 'Admin', '2025-11-21 00:07:12'),
(19, 7, 1, 19, '2025-11-22', 'paid', 'Complete', 'completed', 'admin@edoc.com', '2025-11-22 11:45:02'),
(20, 5, 2, 19, '2025-11-22', 'paid', 'Complete', 'completed', 'admin@edoc.com', '2025-11-22 12:45:02'),
(21, 2, 3, 19, '2025-11-22', 'paid', 'Complete', 'completed', 'admin@edoc.com', '2025-11-22 12:50:14'),
(22, 1, 4, 19, '2025-11-22', 'paid', 'Complete', 'completed', 'cate@smartclinic.com', '2025-11-22 22:04:03'),
(23, 3, 1, 20, '2025-11-25', 'paid', 'Complete', 'completed', 'admin@edoc.com', '2025-11-25 19:05:09'),
(24, 4, 2, 20, '2025-11-25', 'paid', 'Complete', 'completed', 'admin@edoc.com', '2025-11-25 19:17:15'),
(25, 5, 1, 21, '2025-12-19', 'paid', 'Complete', 'completed', 'cate@smartclinic.com', '2025-12-19 20:07:25'),
(26, 1, 1, 22, '2025-12-23', 'paid', 'Complete', 'completed', 'cate@smartclinic.com', '2025-12-23 13:08:30'),
(27, 8, 1, 23, '2025-12-28', 'paid', 'Complete', 'completed', 'cate@smartclinic.com', '2025-12-28 16:50:30'),
(28, 4, 2, 23, '2025-12-28', 'paid', 'Complete', 'completed', 'cate@smartclinic.com', '2025-12-30 18:44:09'),
(29, 5, 1, 24, '2025-12-31', 'paid', 'Complete', 'completed', 'aiden@smartclinic.com', '2025-12-31 11:47:59'),
(30, 9, 1, 25, '2026-02-28', 'paid', 'Complete', 'completed', 'Admin', '2026-02-28 09:55:57'),
(31, 10, 1, 26, '2026-03-22', 'paid', 'Complete', 'completed', 'admin@edoc.com', '2026-03-22 19:48:20'),
(32, 7, 2, 26, '2026-03-22', 'paid', 'Complete', 'completed', 'stanley@smartclinic.com', '2026-03-22 20:15:32'),
(33, 5, 1, 27, '2026-03-26', 'paid', 'Complete', 'completed', 'hannah@smartclinic.com', '2026-03-25 22:22:09');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` datetime NOT NULL,
  `appointment_reason` varchar(255) DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled') DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointment_backup`
--

CREATE TABLE `appointment_backup` (
  `appoid` int(11) NOT NULL DEFAULT 0,
  `pid` int(10) DEFAULT NULL,
  `apponum` int(3) DEFAULT NULL,
  `scheduleid` int(10) DEFAULT NULL,
  `appodate` date DEFAULT NULL,
  `billing_status` enum('pending','paid') CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'pending',
  `status` enum('Scheduled','Complete','Cancelled') CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'Scheduled',
  `workflow_stage` enum('scheduled','consultation_complete','prescription_ready','dispensed','payment_pending','payment_complete','completed') CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'scheduled',
  `completed_by` varchar(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointment_backup`
--

INSERT INTO `appointment_backup` (`appoid`, `pid`, `apponum`, `scheduleid`, `appodate`, `billing_status`, `status`, `workflow_stage`, `completed_by`, `completed_at`) VALUES
(10, 5, 1, 15, '2025-11-17', 'paid', 'Complete', 'scheduled', 'superadmin', '2025-11-17 23:16:53'),
(12, 6, 1, 16, '2025-11-18', 'paid', 'Complete', 'scheduled', 'Admin', '2025-11-18 10:34:31'),
(13, 4, 2, 16, '2025-11-18', 'paid', 'Complete', 'payment_pending', 'superadmin', '2025-11-18 12:09:39');

-- --------------------------------------------------------

--
-- Table structure for table `billing`
--

CREATE TABLE `billing` (
  `id` int(5) NOT NULL,
  `pid` int(11) DEFAULT NULL,
  `appoid` int(11) DEFAULT NULL,
  `CustomerName` varchar(100) NOT NULL,
  `bill_type` varchar(50) NOT NULL,
  `date` datetime NOT NULL,
  `bill_no` varchar(10) NOT NULL,
  `username` varchar(50) NOT NULL,
  `payment_status` enum('pending','paid') DEFAULT 'pending',
  `billing_stage` enum('pending_pharmacy','ready_for_payment','paid','released') DEFAULT 'pending_pharmacy'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billing`
--

INSERT INTO `billing` (`id`, `pid`, `appoid`, `CustomerName`, `bill_type`, `date`, `bill_no`, `username`, `payment_status`, `billing_stage`) VALUES
(41, 6, 0, 'Miriam Chepkemoi', 'Cash', '2025-11-18 10:37:45', 'PHARM-0001', 'Admin', 'paid', 'ready_for_payment'),
(42, 4, 13, 'collins ongwach', 'Cash', '2025-11-18 13:16:33', 'PHARM-0042', 'superadmin', 'paid', 'ready_for_payment'),
(45, 5, 0, 'Mary Njeri', 'Cash', '2025-11-20 16:53:12', 'PHARM-0043', 'Admin', 'paid', 'ready_for_payment'),
(46, 5, 14, 'Mary Njeri', 'Cash', '2025-11-20 16:56:25', 'PHARM-0046', 'Admin', 'paid', 'ready_for_payment'),
(47, 5, 0, 'Mary Njeri', 'Cash', '2025-11-20 16:57:35', 'PHARM-0047', 'Admin', 'paid', 'ready_for_payment'),
(48, 5, 0, 'Mary Njeri', 'Cash', '2025-11-20 18:25:05', 'PHARM-0048', 'Admin', 'paid', 'ready_for_payment'),
(49, 2, 0, 'Hashen Udara', 'Cash', '2025-11-20 18:50:59', 'PHARM-0049', 'Admin', 'paid', 'ready_for_payment'),
(50, 6, 18, 'Miriam Chepkemoi', 'Cash', '2025-11-22 10:02:26', 'PHARM-0006', 'admin@edoc.com', 'paid', 'paid'),
(51, 7, 19, 'Stefanie Ochieng', 'Cash', '2025-11-22 11:35:21', 'PHARM-0007', 'admin@edoc.com', 'paid', 'paid'),
(52, 5, 20, 'Mary Njeri', 'Cash', '2025-11-22 12:45:24', 'PHARM-0005', 'admin@edoc.com', 'paid', 'paid'),
(53, 2, 21, 'Hashen Udara', 'Cash', '2025-11-22 12:50:42', 'PHARM-0002', 'admin@edoc.com', 'paid', 'paid'),
(54, 1, 22, 'Test Patient', 'Cash', '2025-11-22 22:06:09', 'PHARM-0001', 'pharmacy@smartclinic.com', 'paid', 'paid'),
(55, 3, 23, 'Hillary Omwancha', 'Cash', '2025-11-25 19:05:49', 'PHARM-0003', 'admin@edoc.com', 'paid', 'paid'),
(56, 4, 24, 'collins ongwach', 'Cash', '2025-11-25 19:18:20', 'PHARM-0004', 'admin@edoc.com', 'paid', 'paid'),
(57, 5, 25, 'Mary Njeri', 'Cash', '2025-12-19 20:16:25', 'PHARM-0005', 'pharmacy@smartclinic.com', 'paid', 'paid'),
(58, 1, 26, 'Test Patient', 'Cash', '2025-12-23 15:08:27', 'PHARM-0001', 'pharmacy@smartclinic.com', 'paid', 'paid'),
(59, 8, 27, 'Susan jemutai', 'Cash', '2025-12-28 16:51:18', 'PHARM-0008', 'pharmacy@smartclinic.com', 'paid', 'paid'),
(60, 5, 29, 'Mary Njeri', 'Cash', '2025-12-31 11:49:05', 'PHARM-0005', 'pharmacy@smartclinic.com', 'paid', 'paid'),
(61, 4, 28, 'collins ongwach', 'Cash', '2025-12-31 11:49:39', 'PHARM-0004', 'pharmacy@smartclinic.com', 'paid', 'paid'),
(62, 9, 30, 'Brian Wairimu', 'Cash', '2026-02-28 09:59:26', 'PHARM-0062', 'Admin', 'paid', 'ready_for_payment'),
(63, 10, 31, 'Maryanne Wangui', 'Cash', '2026-03-22 19:49:14', 'PHARM-0010', 'admin@edoc.com', 'paid', 'paid'),
(64, 7, 32, 'Stefanie Ochieng', 'Cash', '2026-03-22 20:21:51', 'PHARM-0007', 'pharmacy@smartclinic.com', 'paid', 'paid'),
(65, 5, 33, 'Mary Njeri', 'Cash', '2026-03-25 22:25:04', 'PHARM-0005', 'pharmacy@smartclinic.com', 'paid', 'paid');

-- --------------------------------------------------------

--
-- Table structure for table `billing_backup`
--

CREATE TABLE `billing_backup` (
  `id` int(5) NOT NULL DEFAULT 0,
  `pid` int(11) DEFAULT NULL,
  `appoid` int(11) DEFAULT NULL,
  `CustomerName` varchar(100) NOT NULL,
  `bill_type` varchar(50) NOT NULL,
  `date` datetime NOT NULL,
  `bill_no` varchar(10) NOT NULL,
  `username` varchar(50) NOT NULL,
  `payment_status` enum('pending','paid') DEFAULT 'pending',
  `billing_stage` enum('pending_pharmacy','ready_for_payment','paid','released') DEFAULT 'pending_pharmacy'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billing_backup`
--

INSERT INTO `billing_backup` (`id`, `pid`, `appoid`, `CustomerName`, `bill_type`, `date`, `bill_no`, `username`, `payment_status`, `billing_stage`) VALUES
(41, 6, 0, 'Miriam Chepkemoi', 'Cash', '2025-11-18 10:37:45', 'PHARM-0001', 'Admin', 'paid', 'ready_for_payment'),
(42, 4, 13, 'collins ongwach', 'Cash', '2025-11-18 13:16:33', 'PHARM-0042', 'superadmin', 'paid', 'ready_for_payment');

-- --------------------------------------------------------

--
-- Table structure for table `billing_details`
--

CREATE TABLE `billing_details` (
  `id` int(11) NOT NULL,
  `billing_id` int(11) DEFAULT NULL,
  `product_company` varchar(255) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `product_unit` varchar(100) DEFAULT NULL,
  `packing_size` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billing_details`
--

INSERT INTO `billing_details` (`id`, `billing_id`, `product_company`, `product_name`, `product_unit`, `packing_size`, `price`, `quantity`) VALUES
(30, 27, 'MediPlugequipments', 'Blood Pressure Monitor', 'box', '50', 3000.00, 1),
(31, 28, 'GracePharmaceuticals', 'Ponstan', 'tablet', '50', 65.00, 5),
(32, 29, 'Transchem pharmaceuticals', 'Agumentin', 'tablet', '100', 825.00, 2),
(33, 30, 'Transchem pharmaceuticals', 'Mara Moja', 'box', '100', 25.00, 10),
(34, 31, 'GracePharmacuticals', 'Cough syrup', 'ml', '100', 750.00, 1),
(35, 31, 'RahaPharmacy', 'Agumentin', 'tablet', '100', 850.00, 1),
(36, 32, 'Transchem pharmaceuticals', 'Mara Moja', 'box', '100', 25.00, 5),
(37, 33, 'RahaPharmacy', 'Agumentin', 'tablet', '100', 850.00, 1),
(38, 33, 'GracePharmacuticals', 'Cough syrup', 'ml', '100', 750.00, 1),
(39, 34, 'RahaPharmacy', 'Agumentin', 'tablet', '100', 850.00, 1),
(40, 35, 'Transchem pharmaceuticals', 'Mara Moja', 'box', '100', 25.00, 10),
(41, 36, 'GracePharmaceuticals', 'Ponstan', 'tablet', '50', 75.00, 20),
(42, 37, 'RahaPharmacy', 'Agumentin', 'tablet', '100', 850.00, 1),
(43, 37, 'GracePharmacuticals', 'Cough syrup', 'ml', '100', 750.00, 1),
(44, 38, 'Transchem pharmaceuticals', 'Mara Moja', 'box', '100', 25.00, 5),
(45, 39, 'Transchem pharmaceuticals', 'Mara Moja', 'box', '100', 25.00, 5),
(46, 41, 'GracePharmacuticals', 'Cough syrup', 'ml', '100', 750.00, 1),
(47, 41, 'MediCare Pharmaceuticals', 'Amoxicillin', 'mg', '100', 850.00, 1),
(48, 42, 'Transchem pharmaceuticals', 'Mara Moja', 'box', '100', 25.00, 6),
(51, 45, 'Transchem Pharmacy', 'Agumentin', 'capsule', '100', 500.00, 1),
(52, 46, 'MyDawa', 'Mara Moja', 'box', '100', 25.00, 4),
(53, 46, 'RangePharmacy', 'Cough syrup', 'bottle', '150', 750.00, 1),
(54, 47, 'Transchem Pharmacy', 'Agumentin', 'capsule', '100', 500.00, 1),
(55, 48, 'Transchem Pharmacy', 'Agumentin', 'capsule', '100', 500.00, 1),
(56, 49, 'MyDawa', 'Mara Moja', 'box', '100', 25.00, 10),
(57, 50, 'MyDawa', 'Mara Moja', NULL, NULL, 25.00, 6),
(58, 51, 'MyDawa', 'Mara Moja', NULL, NULL, 25.00, 10),
(59, 51, 'Transchem Pharmacy', 'Agumentin', NULL, NULL, 500.00, 1),
(60, 52, 'MyDawa', 'Mara Moja', NULL, NULL, 25.00, 10),
(61, 53, 'MyDawa', 'Mara Moja', NULL, NULL, 25.00, 15),
(62, 53, 'Transchem Pharmacy', 'Agumentin', NULL, NULL, 500.00, 1),
(63, 54, 'Transchem Pharmacy', 'Agumentin', NULL, NULL, 500.00, 2),
(64, 54, 'MyDawa', 'Mara Moja', NULL, NULL, 25.00, 5),
(65, 55, 'RangePharmacy', 'Cough syrup', NULL, NULL, 750.00, 2),
(66, 55, 'Transchem Pharmacy', 'Agumentin', NULL, NULL, 500.00, 3),
(67, 55, 'MediPlus Pharmacy', 'Panadol', NULL, NULL, 25.00, 5),
(68, 56, 'MyDawa', 'Mara Moja', NULL, NULL, 25.00, 5),
(69, 57, 'MyDawa', 'Mara Moja', NULL, NULL, 25.00, 5),
(70, 57, 'RangePharmacy', 'Cough syrup', NULL, NULL, 750.00, 1),
(71, 57, 'Transchem Pharmacy', 'Agumentin', NULL, NULL, 500.00, 1),
(72, 58, 'MediPlus Pharmacy', 'Panadol', NULL, NULL, 25.00, 10),
(73, 58, 'MyDawa', 'Strepsils', NULL, NULL, 15.00, 10),
(74, 59, 'MyDawa', 'Mara Moja', NULL, NULL, 20.00, 10),
(75, 60, 'MyDawa', 'Mara Moja', NULL, NULL, 20.00, 20),
(76, 61, 'MediPlus Pharmacy', 'Panadol', NULL, NULL, 25.00, 15),
(77, 62, 'MyDawa', 'Mara Moja', 'box', '100', 20.00, 5),
(78, 63, 'Transchem Pharmacy', 'Agumentin', NULL, NULL, 500.00, 4),
(79, 64, 'MediPlus Pharmacy', 'Panadol', NULL, NULL, 25.00, 5),
(80, 64, 'Transchem Pharmacy', 'Agumentin', NULL, NULL, 500.00, 2),
(81, 65, 'MediPlus Pharmacy', 'Panadol', NULL, NULL, 25.00, 5),
(82, 65, 'Family Access Pharmacy', 'Azetromizine', NULL, NULL, 100.00, 3),
(83, 65, 'MyDawa', 'Mara Moja', NULL, NULL, 20.00, 5);

-- --------------------------------------------------------

--
-- Stand-in structure for view `billing_patient_view`
-- (See below for the actual view)
--
CREATE TABLE `billing_patient_view` (
`billing_id` int(5)
,`bill_no` varchar(10)
,`CustomerName` varchar(100)
,`date` datetime
,`bill_type` varchar(50)
,`payment_status` enum('pending','paid')
,`pid` int(11)
,`patient_name` varchar(255)
,`patient_email` varchar(255)
,`patient_phone` varchar(15)
);

-- --------------------------------------------------------

--
-- Table structure for table `billing_payments`
--

CREATE TABLE `billing_payments` (
  `id` int(11) NOT NULL,
  `pid` int(11) NOT NULL,
  `receipt_no` varchar(50) NOT NULL,
  `type` enum('consultation','pharmacy','other','lab','procedure') NOT NULL,
  `reference_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL DEFAULT '',
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','mpesa','card','bank_transfer','insurance','other') NOT NULL DEFAULT 'cash',
  `status` enum('paid','pending','failed','refunded') NOT NULL DEFAULT 'paid',
  `receipt_released` tinyint(1) DEFAULT 0,
  `released_by` varchar(100) DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `paid_by` varchar(100) NOT NULL,
  `processed_by` varchar(100) DEFAULT NULL,
  `payment_date` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billing_payments`
--

INSERT INTO `billing_payments` (`id`, `pid`, `receipt_no`, `type`, `reference_id`, `description`, `amount`, `payment_method`, `status`, `receipt_released`, `released_by`, `released_at`, `transaction_id`, `paid_by`, `processed_by`, `payment_date`, `created_at`, `updated_at`) VALUES
(23, 5, 'RCPT-000023', 'consultation', 10, '0', 500.00, 'cash', 'paid', 0, NULL, NULL, '', 'Mary Njeri', 'Admin', '2025-11-17 23:19:01', '2025-11-17 20:19:01', '2025-11-18 13:16:37'),
(24, 6, 'RCPT-000024', 'consultation', 12, '0', 500.00, 'cash', 'paid', 0, NULL, NULL, '', 'Miriam Chepkemoi', 'Admin', '2025-11-18 10:38:24', '2025-11-18 07:38:24', '2025-11-18 13:16:37'),
(25, 6, 'RCPT-000024', 'pharmacy', 41, '0', 1600.00, 'cash', 'paid', 0, NULL, NULL, '', 'Miriam Chepkemoi', 'Admin', '2025-11-18 10:38:24', '2025-11-18 07:38:24', '2025-11-18 13:16:37'),
(26, 4, 'RCPT-000026', 'consultation', 13, '0', 500.00, 'mpesa', 'paid', 0, NULL, NULL, 'RXC806904', 'collins ongwach', 'Admin', '2025-11-18 13:17:20', '2025-11-18 10:17:20', '2025-11-18 13:16:37'),
(27, 4, 'RCPT-000026', 'pharmacy', 42, '0', 150.00, 'mpesa', 'paid', 0, NULL, NULL, 'RXC806904', 'collins ongwach', 'Admin', '2025-11-18 13:17:20', '2025-11-18 10:17:20', '2025-11-18 13:16:37'),
(30, 4, 'RCPT-000030', 'consultation', 13, '0', 500.00, 'cash', 'paid', 0, NULL, NULL, '', 'collins ongwach', 'Admin', '2025-11-18 15:48:20', '2025-11-18 12:48:20', '2025-11-18 13:16:37'),
(31, 4, 'RCPT-000031', 'consultation', 13, 'Consultation Fee', 500.00, 'cash', 'paid', 0, NULL, NULL, NULL, 'collins ongwach', 'admin@edoc.com', '2025-11-19 20:58:46', '2025-11-19 17:58:46', NULL),
(32, 4, 'RCPT-000031', 'pharmacy', 42, 'Pharmacy Charges', 150.00, 'cash', 'paid', 0, NULL, NULL, '', 'collins ongwach', 'admin@edoc.com', '2025-11-19 20:58:46', '2025-11-19 17:58:46', NULL),
(37, 5, 'RCPT-000037', '', 48, '0', 500.00, 'cash', 'paid', 0, NULL, NULL, '', 'Mary Njeri', 'Admin', '2025-11-20 18:25:32', '2025-11-20 15:25:32', NULL),
(38, 2, 'RCPT-000038', '', 49, '0', 250.00, 'cash', 'paid', 0, NULL, NULL, '', 'Hashen Udara', 'Admin', '2025-11-20 23:33:59', '2025-11-20 20:33:59', NULL),
(39, 6, 'RCPT-000039', 'consultation', 18, 'Consultation Fee', 500.00, 'cash', 'paid', 0, NULL, NULL, '', 'Miriam Chepkemoi', 'admin@edoc.com', '2025-11-22 10:21:25', '2025-11-22 07:21:25', NULL),
(40, 6, 'RCPT-000039', 'pharmacy', 50, 'Pharmacy Charges', 150.00, 'cash', 'paid', 0, NULL, NULL, '', 'Miriam Chepkemoi', 'admin@edoc.com', '2025-11-22 10:21:26', '2025-11-22 07:21:26', NULL),
(41, 3, 'RCPT-000040', 'consultation', 15, 'Consultation Fee', 500.00, 'cash', 'paid', 0, NULL, NULL, '', 'Hillary Omwancha', 'admin@edoc.com', '2025-11-22 12:15:45', '2025-11-22 09:15:45', NULL),
(42, 7, 'RCPT-000041', 'consultation', 19, 'Consultation Fee', 500.00, 'cash', 'paid', 0, NULL, NULL, '', 'Stefanie Ochieng', 'admin@edoc.com', '2025-11-22 12:26:34', '2025-11-22 09:26:34', NULL),
(43, 7, 'RCPT-000041', 'pharmacy', 51, 'Pharmacy Charges', 750.00, 'cash', 'paid', 0, NULL, NULL, '', 'Stefanie Ochieng', 'admin@edoc.com', '2025-11-22 12:26:34', '2025-11-22 09:26:34', NULL),
(44, 5, 'RCPT-000042', 'consultation', 20, 'Consultation Fee', 500.00, 'cash', 'paid', 0, NULL, NULL, '', 'Mary Njeri', 'admin@edoc.com', '2025-11-22 12:45:34', '2025-11-22 09:45:34', NULL),
(45, 5, 'RCPT-000042', 'pharmacy', 52, 'Pharmacy Charges', 250.00, 'cash', 'paid', 0, NULL, NULL, '', 'Mary Njeri', 'admin@edoc.com', '2025-11-22 12:45:34', '2025-11-22 09:45:34', NULL),
(46, 2, 'RCPT-000043', 'consultation', 21, 'Consultation Fee', 500.00, 'mpesa', 'paid', 0, NULL, NULL, 'RXC806904', 'Hashen Udara', 'admin@edoc.com', '2025-11-22 12:51:20', '2025-11-22 09:51:20', NULL),
(47, 2, 'RCPT-000043', 'pharmacy', 53, 'Pharmacy Charges', 875.00, 'mpesa', 'paid', 0, NULL, NULL, 'RXC806904', 'Hashen Udara', 'admin@edoc.com', '2025-11-22 12:51:20', '2025-11-22 09:51:20', NULL),
(48, 1, 'RCPT-000044', 'consultation', 22, 'Consultation Fee', 500.00, 'cash', 'paid', 0, NULL, NULL, '', 'Test Patient', 'reception1@smartclinic.com', '2025-11-22 22:08:33', '2025-11-22 19:08:33', NULL),
(49, 1, 'RCPT-000044', 'pharmacy', 54, 'Pharmacy Charges', 1125.00, 'cash', 'paid', 0, NULL, NULL, '', 'Test Patient', 'reception1@smartclinic.com', '2025-11-22 22:08:33', '2025-11-22 19:08:33', NULL),
(50, 3, 'RCPT-000045', 'consultation', 23, 'Consultation Fee', 500.00, 'mpesa', 'paid', 0, NULL, NULL, 'TKOCH321', 'Hillary Omwancha', 'admin@edoc.com', '2025-11-25 19:08:50', '2025-11-25 16:08:50', NULL),
(51, 3, 'RCPT-000045', 'pharmacy', 55, 'Pharmacy Charges', 3125.00, 'mpesa', 'paid', 0, NULL, NULL, 'TKOCH321', 'Hillary Omwancha', 'admin@edoc.com', '2025-11-25 19:08:50', '2025-11-25 16:08:50', NULL),
(52, 4, 'RCPT-000046', 'consultation', 24, 'Consultation Fee', 500.00, 'cash', 'paid', 0, NULL, NULL, '', 'collins ongwach', 'reception1@smartclinic.com', '2025-11-25 19:28:56', '2025-11-25 16:28:56', NULL),
(53, 4, 'RCPT-000046', 'pharmacy', 56, 'Pharmacy Charges', 125.00, 'cash', 'paid', 0, NULL, NULL, '', 'collins ongwach', 'reception1@smartclinic.com', '2025-11-25 19:28:56', '2025-11-25 16:28:56', NULL),
(54, 5, 'RCPT-000047', 'consultation', 25, 'Consultation Fee', 500.00, 'cash', 'paid', 0, NULL, NULL, '', 'Mary Njeri', 'reception1@smartclinic.com', '2025-12-19 20:18:04', '2025-12-19 17:18:04', NULL),
(55, 5, 'RCPT-000047', 'pharmacy', 57, 'Pharmacy Charges', 1375.00, 'cash', 'paid', 0, NULL, NULL, '', 'Mary Njeri', 'reception1@smartclinic.com', '2025-12-19 20:18:04', '2025-12-19 17:18:04', NULL),
(56, 1, 'RCPT-000048', 'consultation', 26, 'Consultation Fee', 500.00, 'mpesa', 'paid', 0, NULL, NULL, '', 'Test Patient', 'reception1@smartclinic.com', '2025-12-23 15:10:04', '2025-12-23 12:10:04', NULL),
(57, 1, 'RCPT-000048', 'pharmacy', 58, 'Pharmacy Charges', 400.00, 'mpesa', 'paid', 0, NULL, NULL, '', 'Test Patient', 'reception1@smartclinic.com', '2025-12-23 15:10:04', '2025-12-23 12:10:04', NULL),
(58, 8, 'RCPT-000049', 'consultation', 27, 'Consultation Fee', 500.00, 'cash', 'paid', 0, NULL, NULL, '', 'Susan jemutai', 'reception1@smartclinic.com', '2025-12-28 16:51:53', '2025-12-28 13:51:53', NULL),
(59, 8, 'RCPT-000049', 'pharmacy', 59, 'Pharmacy Charges', 200.00, 'cash', 'paid', 0, NULL, NULL, '', 'Susan jemutai', 'reception1@smartclinic.com', '2025-12-28 16:51:53', '2025-12-28 13:51:53', NULL),
(60, 4, 'RCPT-000050', 'consultation', 28, 'Consultation Fee', 500.00, 'cash', 'paid', 0, NULL, NULL, '', 'collins ongwach', 'reception1@smartclinic.com', '2025-12-31 11:50:02', '2025-12-31 08:50:02', NULL),
(61, 4, 'RCPT-000050', 'pharmacy', 61, 'Pharmacy Charges', 375.00, 'cash', 'paid', 0, NULL, NULL, '', 'collins ongwach', 'reception1@smartclinic.com', '2025-12-31 11:50:02', '2025-12-31 08:50:02', NULL),
(62, 5, 'RCPT-000051', 'consultation', 29, 'Consultation Fee', 500.00, 'mpesa', 'paid', 0, NULL, NULL, '', 'Mary Njeri', 'reception1@smartclinic.com', '2025-12-31 11:50:06', '2025-12-31 08:50:06', NULL),
(63, 5, 'RCPT-000051', 'pharmacy', 60, 'Pharmacy Charges', 400.00, 'mpesa', 'paid', 0, NULL, NULL, '', 'Mary Njeri', 'reception1@smartclinic.com', '2025-12-31 11:50:06', '2025-12-31 08:50:06', NULL),
(64, 9, 'RCPT-000064', '', 30, '0', 500.00, 'cash', 'paid', 0, NULL, NULL, '', 'Brian Wairimu', 'Admin', '2026-02-28 10:00:45', '2026-02-28 07:00:45', NULL),
(65, 9, 'RCPT-000064', '', 62, '0', 100.00, 'cash', 'paid', 0, NULL, NULL, '', 'Brian Wairimu', 'Admin', '2026-02-28 10:00:45', '2026-02-28 07:00:45', NULL),
(66, 10, 'RCPT-000065', 'consultation', 31, 'Consultation Fee', 500.00, 'cash', 'paid', 0, NULL, NULL, '', 'Maryanne Wangui', 'admin@edoc.com', '2026-03-22 19:49:26', '2026-03-22 16:49:26', NULL),
(67, 10, 'RCPT-000065', 'pharmacy', 63, 'Pharmacy Charges', 2000.00, 'cash', 'paid', 0, NULL, NULL, '', 'Maryanne Wangui', 'admin@edoc.com', '2026-03-22 19:49:26', '2026-03-22 16:49:26', NULL),
(68, 7, 'RCPT-000066', 'consultation', 32, 'Consultation Fee', 500.00, 'mpesa', 'paid', 0, NULL, NULL, '', 'Stefanie Ochieng', 'reception1@smartclinic.com', '2026-03-22 20:23:46', '2026-03-22 17:23:46', NULL),
(69, 7, 'RCPT-000066', 'pharmacy', 64, 'Pharmacy Charges', 1125.00, 'mpesa', 'paid', 0, NULL, NULL, '', 'Stefanie Ochieng', 'reception1@smartclinic.com', '2026-03-22 20:23:46', '2026-03-22 17:23:46', NULL),
(70, 5, 'RCPT-000067', 'consultation', 33, 'Consultation Fee', 500.00, 'mpesa', 'paid', 0, NULL, NULL, '', 'Mary Njeri', 'reception1@smartclinic.com', '2026-03-25 22:26:10', '2026-03-25 19:26:10', NULL),
(71, 5, 'RCPT-000067', 'pharmacy', 65, 'Pharmacy Charges', 525.00, 'mpesa', 'paid', 0, NULL, NULL, '', 'Mary Njeri', 'reception1@smartclinic.com', '2026-03-25 22:26:10', '2026-03-25 19:26:10', NULL);

--
-- Triggers `billing_payments`
--
DELIMITER $$
CREATE TRIGGER `after_payment_insert` AFTER INSERT ON `billing_payments` FOR EACH ROW BEGIN
    -- Update consultation payment status
    IF NEW.type = 'consultation' AND NEW.status = 'paid' THEN
        -- Update patient_charges
        UPDATE patient_charges 
        SET status = 'paid', 
            paid_date = NEW.payment_date,
            paid_amount = NEW.amount
        WHERE appoid = NEW.reference_id 
        AND pid = NEW.pid 
        AND charge_type = 'consultation';
        
        -- Update appointment
        UPDATE appointment 
        SET billing_status = 'paid'
        WHERE appoid = NEW.reference_id;
    END IF;
    
    -- Update pharmacy payment status
    IF NEW.type = 'pharmacy' AND NEW.status = 'paid' THEN
        UPDATE billing 
        SET payment_status = 'paid'
        WHERE id = NEW.reference_id 
        AND pid = NEW.pid;
    END IF;
    
    -- Update other charges
    IF NEW.type = 'other' AND NEW.status = 'paid' THEN
        UPDATE patient_charges 
        SET status = 'paid', 
            paid_date = NEW.payment_date,
            paid_amount = NEW.amount
        WHERE id = NEW.reference_id 
        AND pid = NEW.pid;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `billing_payments_backup`
--

CREATE TABLE `billing_payments_backup` (
  `id` int(11) NOT NULL DEFAULT 0,
  `pid` int(11) NOT NULL,
  `receipt_no` varchar(50) NOT NULL,
  `type` enum('consultation','pharmacy','other') NOT NULL,
  `reference_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL DEFAULT '',
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','mpesa','card','bank_transfer','insurance','other') NOT NULL DEFAULT 'cash',
  `status` enum('paid','pending','failed','refunded') NOT NULL DEFAULT 'paid',
  `receipt_released` tinyint(1) DEFAULT 0,
  `released_by` varchar(100) DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `paid_by` varchar(100) NOT NULL,
  `processed_by` varchar(100) DEFAULT NULL,
  `payment_date` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billing_payments_backup`
--

INSERT INTO `billing_payments_backup` (`id`, `pid`, `receipt_no`, `type`, `reference_id`, `description`, `amount`, `payment_method`, `status`, `receipt_released`, `released_by`, `released_at`, `transaction_id`, `paid_by`, `processed_by`, `payment_date`, `created_at`, `updated_at`) VALUES
(23, 5, 'RCPT-000023', '', 10, '0', 500.00, 'cash', 'paid', 0, NULL, NULL, '', 'Mary Njeri', 'Admin', '2025-11-17 23:19:01', '2025-11-17 20:19:01', '2025-11-18 10:35:03'),
(24, 6, 'RCPT-000024', '', 12, '0', 500.00, 'cash', 'paid', 0, NULL, NULL, '', 'Miriam Chepkemoi', 'Admin', '2025-11-18 10:38:24', '2025-11-18 07:38:24', '2025-11-18 10:35:03'),
(25, 6, 'RCPT-000024', '', 41, '0', 1600.00, 'cash', 'paid', 0, NULL, NULL, '', 'Miriam Chepkemoi', 'Admin', '2025-11-18 10:38:24', '2025-11-18 07:38:24', '2025-11-18 10:35:03'),
(26, 4, 'RCPT-000026', '', 13, '0', 500.00, 'mpesa', 'paid', 0, NULL, NULL, 'RXC806904', 'collins ongwach', 'Admin', '2025-11-18 13:17:20', '2025-11-18 10:17:20', '2025-11-18 10:35:03'),
(27, 4, 'RCPT-000026', '', 42, '0', 150.00, 'mpesa', 'paid', 0, NULL, NULL, 'RXC806904', 'collins ongwach', 'Admin', '2025-11-18 13:17:20', '2025-11-18 10:17:20', '2025-11-18 10:35:03'),
(30, 4, 'RCPT-000030', '', 13, '0', 500.00, 'cash', 'paid', 0, NULL, NULL, '', 'collins ongwach', 'Admin', '2025-11-18 15:48:20', '2025-11-18 12:48:20', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `company_name`
--

CREATE TABLE `company_name` (
  `id` int(11) NOT NULL,
  `company_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `company_name`
--

INSERT INTO `company_name` (`id`, `company_name`) VALUES
(9, 'MyDawa'),
(10, 'RangePharmacy'),
(11, 'Transchem Pharmacy'),
(12, 'MediPlus Pharmacy'),
(13, 'Family Access Pharmacy'),
(14, 'MediCare Pharmaceuticals'),
(15, 'MedicPro'),
(16, 'nyali');

-- --------------------------------------------------------

--
-- Table structure for table `doctor`
--

CREATE TABLE `doctor` (
  `docid` int(11) NOT NULL,
  `docemail` varchar(255) DEFAULT NULL,
  `docname` varchar(255) DEFAULT NULL,
  `docpassword` varchar(255) DEFAULT NULL,
  `docnic` varchar(15) DEFAULT NULL,
  `doctel` varchar(15) DEFAULT NULL,
  `specialties` int(2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `doctor`
--

INSERT INTO `doctor` (`docid`, `docemail`, `docname`, `docpassword`, `docnic`, `doctel`, `specialties`) VALUES
(1, 'doctor@edoc.com', 'Doctor', '123', '000000000', '0110000000', 1),
(2, 'hannah@smartclinic.com', 'hannah nyokabi', 'hannah', '', '0790543218', 11),
(3, 'cate@smartclinic.com', 'cate Wamuhu', 'cate254', '', '0780956421', 18),
(4, 'collins@gmail.com', 'Collins Odwour', 'collins', NULL, '0780965432', 45),
(5, 'marvin254@gmail.com', 'Marvin Mbuguwa', 'marvin', NULL, '0789432150', 5),
(6, 'Dr.Danah@smartclinic.com', 'Danah Murumba', 'danah', '', '0780956421', 18),
(7, 'aiden@smartclinic.com', 'Aiden Wairimu', 'aiden', '', '0780956320', 5),
(8, 'stanley@smartclinic.com', 'Stanley Kiprop', 'stan', '', '0780943267', 35);

-- --------------------------------------------------------

--
-- Table structure for table `doctors`
--

CREATE TABLE `doctors` (
  `doctor_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `specialization` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctors`
--

INSERT INTO `doctors` (`doctor_id`, `full_name`, `phone`, `specialization`) VALUES
(2, 'hannah nyokabi', '', 'General Practitioner');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_work_hours`
--

CREATE TABLE `doctor_work_hours` (
  `id` int(11) NOT NULL,
  `docid` int(11) NOT NULL,
  `day_of_week` varchar(20) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctor_work_hours`
--

INSERT INTO `doctor_work_hours` (`id`, `docid`, `day_of_week`, `start_time`, `end_time`, `created_at`) VALUES
(1, 3, 'monday', '09:00:00', '17:00:00', '2025-11-12 08:03:15'),
(2, 3, 'tuesday', '09:00:00', '17:00:00', '2025-11-12 08:03:15'),
(3, 3, 'wednesday', '09:00:00', '17:00:00', '2025-11-12 08:03:15'),
(4, 3, 'thursday', '09:00:00', '17:00:00', '2025-11-12 08:03:15'),
(5, 3, 'friday', '09:00:00', '17:00:00', '2025-11-12 08:03:15'),
(6, 3, 'saturday', '08:00:00', '16:00:00', '2025-11-12 08:03:15'),
(7, 2, 'monday', '08:00:00', '17:00:00', '2025-11-12 08:04:30'),
(8, 2, 'tuesday', '08:00:00', '17:00:00', '2025-11-12 08:04:30'),
(9, 2, 'wednesday', '08:00:00', '17:00:00', '2025-11-12 08:04:30'),
(10, 2, 'thursday', '08:00:00', '17:00:00', '2025-11-12 08:04:30'),
(11, 2, 'friday', '08:00:00', '17:00:00', '2025-11-12 08:04:30'),
(12, 2, 'saturday', '09:00:00', '16:00:00', '2025-11-12 08:04:30'),
(13, 4, 'monday', '10:00:00', '18:00:00', '2025-11-17 05:45:16'),
(14, 4, 'tuesday', '10:00:00', '18:00:00', '2025-11-17 05:45:16'),
(15, 4, 'wednesday', '10:00:00', '18:00:00', '2025-11-17 05:45:16'),
(16, 4, 'thursday', '10:00:00', '18:00:00', '2025-11-17 05:45:16'),
(17, 4, 'friday', '10:00:00', '18:00:00', '2025-11-17 05:45:16'),
(18, 4, 'saturday', '09:00:00', '17:00:00', '2025-11-17 05:45:16');

-- --------------------------------------------------------

--
-- Table structure for table `ehr_prescriptions`
--

CREATE TABLE `ehr_prescriptions` (
  `id` int(11) NOT NULL,
  `appoid` int(11) NOT NULL,
  `medication` varchar(255) NOT NULL,
  `dosage` varchar(100) DEFAULT NULL,
  `frequency` varchar(100) DEFAULT NULL,
  `duration` varchar(100) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending_pharmacy','dispensed','payment_pending','payment_complete','released') DEFAULT 'pending_pharmacy'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ehr_prescriptions`
--

INSERT INTO `ehr_prescriptions` (`id`, `appoid`, `medication`, `dosage`, `frequency`, `duration`, `instructions`, `created_at`, `status`) VALUES
(18, 18, 'mara moja', '500mg', 'thrice daily', 'as needed', '', '2025-11-20 21:07:07', 'released'),
(19, 19, 'Mara Moja', '500mg', 'Twice daily', 'as needed', '', '2025-11-22 08:32:42', 'released'),
(20, 19, 'Cough Syrup, agumentin', '500mg', 'Three times daily', '7 days', '', '2025-11-22 08:33:23', 'released'),
(21, 20, 'Mara Moja', '500mg', 'Three times daily', 'as needed', '', '2025-11-22 09:44:31', 'released'),
(22, 20, 'Mara Moja', '500mg', 'Three times daily', 'as needed', '', '2025-11-22 09:44:57', 'released'),
(23, 21, 'Mara Moja', '500mg', 'Twice daily', 'as needed', '', '2025-11-22 09:49:31', 'released'),
(24, 21, 'Agumentin', '1500mg', 'Three times daily', '7 days', '', '2025-11-22 09:49:54', 'released'),
(25, 22, 'Agumentin', '500mg', 'Three times daily', '7', '', '2025-11-22 19:03:44', 'released'),
(26, 22, 'Mara Moja', '500mg', 'Twice daily', 'as  needed', '', '2025-11-22 19:03:58', 'released'),
(27, 23, 'Panadol', '500mg', 'Three times daily', 'as needed', '', '2025-11-25 16:04:33', 'released'),
(28, 23, 'Agumentin', '500mg', 'Three times daily', '7 days', '', '2025-11-25 16:04:52', 'released'),
(29, 23, 'Cough Syrup', '500mg', 'Three times daily', '7 days', '', '2025-11-25 16:05:01', 'released'),
(30, 24, 'Mara Moja', '500mg', 'Three times daily', 'as needed', '', '2025-11-25 16:17:13', 'released'),
(31, 25, 'Agumentin', '1000mg', 'Three times daily', '7 days', 'Take after meals', '2025-12-19 17:06:22', 'released'),
(32, 25, 'Cough Syrup', '1000mg', 'Twice daily', '7 days', 'Take after meals', '2025-12-19 17:06:39', 'released'),
(33, 25, 'mara moja', '500mg', 'As needed', 'as needed', 'take 2 tables every 5 hours if pain persists', '2025-12-19 17:07:14', 'released'),
(34, 26, 'strepsils', '500mg', 'As needed', '3 days', 'take 1 every 3 hours', '2025-12-23 10:06:53', 'released'),
(35, 26, 'Panadol', '500mg', 'Three times daily', '3 days', '2 tablets every 4 hours until pain subsides.', '2025-12-23 10:07:24', 'released'),
(36, 27, 'Mara Moja', '500mg', 'Twice daily', '7 days', 'take 2 tablets every 4 hours.', '2025-12-28 13:50:27', 'released'),
(37, 28, 'Panadol', '500mg', 'Twice daily', 'as needed', 'take 2 tablets every 4 hours till pain subsides', '2025-12-30 15:43:48', 'released'),
(38, 28, 'Panadol', '500mg', 'Twice daily', 'as needed', 'take 2 tablets every 4 hours till pain subsides', '2025-12-30 15:44:05', 'released'),
(39, 29, 'Mara Moja', '500mg', 'Four times daily', '7 days', 'Take 2 tablets every 4 hours until pain subsides', '2025-12-31 08:47:15', 'released'),
(40, 29, 'Mara Moja', '500mg', 'Four times daily', '7 days', 'Take 2 tablets every 4 hours until pain subsides', '2025-12-31 08:47:48', 'released'),
(41, 30, 'mara moja', '1000mg', 'thrice daily', 'as needed', 'take 2 tablets every 4 hours', '2026-02-28 06:53:36', 'released'),
(42, 31, 'Amoxicillin', '1000mg', 'Three times daily', '7 days', '', '2026-03-22 16:48:13', 'released'),
(43, 32, 'Agumentin', '1000mg', 'Four times daily', '7 days', '', '2026-03-22 17:15:13', 'released'),
(44, 32, 'Ibuprofen', '1000mg', 'Three times daily', '7 days', '', '2026-03-22 17:15:29', 'released'),
(45, 33, 'Mara Moja', '500mg', 'Three times daily', 'as needed', 'Take 2 tablets every 4 hours, not more than 8 tablets a day!', '2026-03-25 19:21:39', 'released'),
(46, 33, 'Agumentin', '1500mg', 'Three times daily', '7 days', 'Take after meals!', '2026-03-25 19:22:07', 'released'),
(47, 33, 'ibuprofen', '1000mg', '2 * 2', '5 days', '', '2026-03-25 19:22:43', 'released');

-- --------------------------------------------------------

--
-- Table structure for table `ehr_records`
--

CREATE TABLE `ehr_records` (
  `id` int(11) NOT NULL,
  `appoid` int(11) NOT NULL,
  `pid` int(11) NOT NULL,
  `docid` int(11) NOT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment_plan` text DEFAULT NULL,
  `medical_notes` text DEFAULT NULL,
  `vitals_bp` varchar(20) DEFAULT NULL,
  `vitals_temp` varchar(20) DEFAULT NULL,
  `vitals_hr` varchar(20) DEFAULT NULL,
  `vitals_rr` varchar(20) DEFAULT NULL,
  `vitals_weight` varchar(20) DEFAULT NULL,
  `vitals_height` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ehr_records`
--

INSERT INTO `ehr_records` (`id`, `appoid`, `pid`, `docid`, `diagnosis`, `treatment_plan`, `medical_notes`, `vitals_bp`, `vitals_temp`, `vitals_hr`, `vitals_rr`, `vitals_weight`, `vitals_height`, `created_at`, `updated_at`) VALUES
(1, 2, 3, 3, 'Symptoms: \r\nrunny nose, sore throat and irritative coughs.\r\n\r\nDiagnosis:\r\nHas the flu and mild infection.', 'To be put on some antibiotics and cough syrup.', '', '132/84', '34.6', '88', '21', '74', '175', '2025-11-07 07:48:48', '2025-11-07 07:48:48'),
(2, 5, 1, 3, 'headaches', 'painkillers', '', '122/84', '34.9', '87', '25', '74', '175', '2025-11-07 10:02:35', '2025-11-07 10:02:35'),
(3, 6, 4, 3, 'flu like symptoms, runny nose, itchy throat and irritative coughs.', 'Antibiotics and cough syrup.', '', '132/84', '34.6', '87', '25', '74', '179', '2025-11-12 08:45:23', '2025-11-12 08:45:23'),
(4, 7, 5, 3, 'flu', 'antibiotics.', '', '132/84', '34.6', '88', '25', '67', '168', '2025-11-12 09:49:11', '2025-11-12 09:49:11'),
(5, 8, 2, 3, 'headaches', 'mara moja', '', '132/84', '34.6', '88', '25', '74', '179', '2025-11-12 09:57:21', '2025-11-12 09:57:21'),
(6, 9, 6, 4, 'depressed, and presenting with bipolar disorder tendencies.', 'To be put on anti-depressants and monitored after every 2 weeks. Should come for bi-weekly check-ups.', '', '132/84', '34.9', '88', '25', '53', '168', '2025-11-17 05:57:03', '2025-11-17 05:57:03'),
(7, 10, 5, 3, 'flu', 'cough syrup and Augmentin', '', '132/84', '34.6', '88', '25', '67', '175', '2025-11-17 20:16:48', '2025-11-17 20:16:48'),
(8, 12, 6, 3, 'runny nose, sore throat and irritative coughs.', 'Amoxicillin and Cough syrup.', '', '132/84', '34.6', '88', '25', '67', '168', '2025-11-18 07:34:17', '2025-11-18 07:34:17'),
(9, 13, 4, 3, 'extreme migraines and headaches', 'painkillers', '', '132/84', '34.9', '87', '25', '74', '175', '2025-11-18 09:07:47', '2025-11-18 09:09:23'),
(10, 14, 5, 3, 'flu like symptoms and headaches', 'mara moja for headaches and Augmentin and cough syrup', '', '132/84', '34.6', '88', '25', '74', '175', '2025-11-20 13:55:12', '2025-11-20 13:55:12'),
(11, 18, 6, 2, 'tooth aches, cavity on her molar', 'painkillers/ mara moja', '', '132/84', '36', '77', '25', '67', '168', '2025-11-20 21:07:07', '2025-11-20 21:07:07'),
(12, 19, 7, 3, 'Possible infection and flu.', 'To be put on some antibiotics and cough syrup and mara moja for the headaches.', '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-22 08:32:14', '2025-11-22 08:32:14'),
(13, 20, 5, 3, 'headaches', 'mara moja', '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-22 09:44:18', '2025-11-22 09:44:18'),
(14, 21, 2, 3, 'food poisoning', 'antibiotics and painkillers', '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-22 09:49:14', '2025-11-22 09:49:14'),
(15, 22, 1, 3, 'Infection', 'Augmentin and mara moja', '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-22 19:03:27', '2025-11-22 19:03:27'),
(16, 23, 3, 3, 'Suspected bacterial infection', 'To be put antibiotics, cough syrup and panadol for headaches', '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-25 16:04:14', '2025-11-25 16:04:14'),
(17, 24, 4, 3, 'headache', 'mara moja', '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-25 16:16:59', '2025-11-25 16:16:59'),
(18, 25, 5, 3, 'Flu', 'Antibiotics, cough syrup and painkillers', '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-12-19 17:05:57', '2025-12-19 17:05:57'),
(19, 26, 1, 3, 'Mild infection', 'Strepsils and panadol', '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-12-23 10:06:19', '2025-12-23 10:06:19'),
(20, 27, 8, 3, 'headaches and migraines', 'maramoja', '', '124/86', '35.5', '17', '21', '74', '167', '2025-12-28 13:50:00', '2025-12-28 13:55:16'),
(21, 28, 4, 3, 'headache/stress', 'Panadol', '', '132/77', '36.5', '18', '97', '75', '173', '2025-12-30 15:43:10', '2025-12-30 16:07:37'),
(22, 29, 5, 7, 'Stress induced headaches', 'Painkillers/ Mara Moja', '', '122/77', '36', '75', '18', '67', '164', '2025-12-31 08:46:04', '2025-12-31 08:46:39'),
(23, 30, 9, 3, 'Headaches and migraines.', 'Pain killers', '', '122/84', '34.6', '77', '21', '74', '175', '2026-02-28 06:53:36', '2026-02-28 06:53:36'),
(24, 31, 10, 8, 'Arthritis Flare up!', 'Antibiotics.', '', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-22 16:47:56', '2026-03-22 16:47:56'),
(25, 32, 7, 8, 'Arthritis Flare up', 'Augumentin and ibuprofen', '', '132/85', '36', '78', '18', '67', '175', '2026-03-22 17:13:44', '2026-03-22 17:14:54'),
(26, 33, 5, 2, 'Tooth ache and bacterial infection', 'Antibiotics and Painkillers', '', '132/85', '35', '77', '18', '72', '169', '2026-03-25 19:20:27', '2026-03-25 19:21:03');

-- --------------------------------------------------------

--
-- Table structure for table `medical_records`
--

CREATE TABLE `medical_records` (
  `record_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `diagnosis` text NOT NULL,
  `symptoms` text DEFAULT NULL,
  `treatment_plan` text DEFAULT NULL,
  `notes` text NOT NULL,
  `record_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medical_records`
--

INSERT INTO `medical_records` (`record_id`, `patient_id`, `doctor_id`, `appointment_id`, `diagnosis`, `symptoms`, `treatment_plan`, `notes`, `record_date`) VALUES
(7, 3, 2, NULL, 'Overweight', 'overweight, sluggish.', 'To be put on appetite reducers and draft a nutrition plan', '', '2025-11-03 15:30:18');

-- --------------------------------------------------------

--
-- Table structure for table `part_info`
--

CREATE TABLE `part_info` (
  `id` int(11) NOT NULL,
  `firstname` varchar(100) DEFAULT NULL,
  `lastname` varchar(100) DEFAULT NULL,
  `businessname` varchar(255) DEFAULT NULL,
  `contact` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `part_info`
--

INSERT INTO `part_info` (`id`, `firstname`, `lastname`, `businessname`, `contact`, `address`, `city`) VALUES
(2, 'Vivian', 'chepchumba', 'VeePharmaceutical', '0726964087', 'Nairobi\r\nKenya', 'Nairobi'),
(5, 'Grace', 'Wamuhu', 'GracePharmaceuticals', '07000000000', 'Nairobi\r\nKenya', 'Nairobi'),
(8, 'Brian', 'Mal', 'MalPharmaceutical', '0123567890', 'Nairobi', 'Nairobi'),
(12, 'Hillary', 'Odwour', 'MediCare Pharmaceuticals', '0134908765', 'Nairobi\r\nKenya', 'Nairobi'),
(14, 'Mary', 'Wamuyu', 'MediPlugequipments', '0780965321', 'Nairobi', 'Nairobi'),
(18, 'Jane', 'Waweru', 'MedicPro', '0721367000', 'Nairobi\r\nKenya', 'Nairobi');

-- --------------------------------------------------------

--
-- Table structure for table `patient`
--

CREATE TABLE `patient` (
  `pid` int(11) NOT NULL,
  `pemail` varchar(255) DEFAULT NULL,
  `pname` varchar(255) DEFAULT NULL,
  `ppassword` varchar(255) DEFAULT NULL,
  `paddress` varchar(255) DEFAULT NULL,
  `pnic` varchar(15) DEFAULT NULL,
  `pdob` date DEFAULT NULL,
  `ptel` varchar(15) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `patient`
--

INSERT INTO `patient` (`pid`, `pemail`, `pname`, `ppassword`, `paddress`, `pnic`, `pdob`, `ptel`) VALUES
(1, 'patient@edoc.com', 'Test Patient', '123', 'Nairobi', '0000000000', '2000-01-01', '0720000090'),
(2, 'emhashenudara@gmail.com', 'Hashen Udara', '123', 'Nairobi', '0110000000', '2022-06-03', '0700000000'),
(3, 'hillary@gmail.com', 'Hillary Omwancha', 'hilla', 'Nairobi\r\nKenya', '01234567', '2000-10-04', '0780965421'),
(4, 'collins@gmail.com', 'collins ongwach', 'collins', 'Nairobi\r\nKenya', '0901002', '1999-01-10', '0789544310'),
(5, 'mary@gmail.com', 'Mary Njeri', 'mary', 'Nairobi\r\nKenya', '35809632', '1989-10-15', '0735907652'),
(6, 'miri@gmail.com', 'Miriam Chepkemoi', 'miriam', 'Nairobi', '31204156', '1975-03-14', '0780543219'),
(7, 'stefanie@gmail.com', 'Stefanie Ochieng', 'stefanie', 'NairobiKenya', '41404123', '2000-03-21', '0780967632'),
(8, 'susan@gmail.com', 'Susan jemutai', 'susan', 'Nairobi, Kenya', '40809213', '2000-03-22', '0780965432'),
(9, 'brian@gmail.com', 'Brian Wairimu', 'brian254', 'Nairobi, Langata', '31509876', '1997-11-11', '0786543210'),
(10, 'maryanne@gmail.com', 'Maryanne Wangui', 'maryanne', 'Nairobi', '39087532', '1998-11-01', '0100976432');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `patient_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `gender` enum('Female','Male') NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `dob` date DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`patient_id`, `full_name`, `gender`, `email`, `phone`, `dob`, `address`) VALUES
(1, 'Vivian mboru', 'Female', 'vivian@example.com', '0701234567', '1998-08-12', 'Nairobi'),
(2, 'Brian Otieno', 'Male', 'brian@example.com', '0712345678', '1989-04-23', 'Kisumu'),
(3, 'Esther Njeri', 'Female', 'esther@example.com', '0723456789', '2001-01-10', 'Nakuru');

-- --------------------------------------------------------

--
-- Stand-in structure for view `patient_bill_summary`
-- (See below for the actual view)
--
CREATE TABLE `patient_bill_summary` (
`appoid` int(11)
,`pid` int(10)
,`pname` varchar(255)
,`appodate` date
,`consultation_fee` decimal(10,2)
,`pharmacy_fee` decimal(42,2)
,`total_due` decimal(43,2)
,`payment_status` varchar(17)
,`workflow_stage` enum('scheduled','consultation_complete','prescription_ready','dispensed','payment_pending','payment_complete','completed')
);

-- --------------------------------------------------------

--
-- Table structure for table `patient_charges`
--

CREATE TABLE `patient_charges` (
  `id` int(11) NOT NULL,
  `pid` int(11) NOT NULL,
  `appoid` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `paid_amount` decimal(10,2) DEFAULT 0.00,
  `charge_type` enum('consultation','pharmacy','lab','procedure') NOT NULL,
  `status` enum('pending','paid') DEFAULT 'pending',
  `created_by` varchar(100) NOT NULL,
  `created_date` datetime NOT NULL,
  `paid_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_charges`
--

INSERT INTO `patient_charges` (`id`, `pid`, `appoid`, `description`, `amount`, `paid_amount`, `charge_type`, `status`, `created_by`, `created_date`, `paid_date`) VALUES
(1, 3, 2, 'Consultation Fee', 500.00, 0.00, 'consultation', 'paid', 'Admin', '2025-11-07 12:56:52', '2025-11-07 14:45:08'),
(2, 1, 5, 'Consultation Fee', 500.00, 0.00, 'consultation', 'paid', 'Admin', '2025-11-07 13:02:40', '2025-11-17 22:23:46'),
(3, 4, 6, 'Consultation Fee', 500.00, 0.00, 'consultation', 'paid', 'Admin', '2025-11-12 11:45:32', '2025-11-12 11:51:23'),
(4, 5, 7, 'Consultation Fee', 500.00, 0.00, 'consultation', 'paid', 'Admin', '2025-11-12 12:49:17', '2025-11-12 12:50:14'),
(5, 2, 8, 'Consultation Fee', 500.00, 0.00, 'consultation', 'paid', 'Admin', '2025-11-12 12:57:25', '2025-11-12 12:59:27'),
(6, 6, 9, 'Consultation Fee', 500.00, 0.00, 'consultation', 'paid', 'Admin', '2025-11-17 08:57:07', '2025-11-17 22:21:46'),
(7, 5, 10, 'Consultation Fee', 500.00, 0.00, 'consultation', 'paid', 'superadmin', '2025-11-17 23:16:53', '2025-11-18 13:40:23'),
(8, 6, 12, 'Consultation Fee', 500.00, 0.00, 'consultation', 'paid', 'Admin', '2025-11-18 10:34:31', '2025-11-18 10:38:24'),
(9, 4, 13, 'Consultation Fee', 500.00, 500.00, 'consultation', 'paid', 'superadmin', '2025-11-18 12:09:39', '2025-11-19 20:58:46'),
(10, 5, 14, 'Consultation Fee', 500.00, 500.00, 'consultation', 'paid', 'Admin', '2025-11-20 16:55:17', '2025-11-20 16:56:35'),
(11, 3, 15, 'Consultation Fee', 500.00, 500.00, 'consultation', 'paid', 'Admin', '2025-11-20 17:02:03', '2025-11-22 12:15:45'),
(12, 6, 18, 'Consultation Fee', 500.00, 500.00, 'consultation', 'paid', 'Admin', '2025-11-21 00:07:12', '2025-11-22 10:21:26'),
(13, 7, 19, 'Consultation Fee', 500.00, 500.00, 'consultation', 'paid', 'admin@edoc.com', '2025-11-22 11:45:02', '2025-11-22 12:26:34'),
(14, 5, 20, 'Consultation Fee', 500.00, 500.00, 'consultation', 'paid', 'admin@edoc.com', '2025-11-22 12:45:02', '2025-11-22 12:45:34'),
(15, 2, 21, 'Consultation Fee', 500.00, 500.00, 'consultation', 'paid', 'admin@edoc.com', '2025-11-22 12:50:14', '2025-11-22 12:51:20'),
(16, 1, 22, 'Consultation Fee', 500.00, 500.00, 'consultation', 'paid', 'cate@smartclinic.com', '2025-11-22 22:04:03', '2025-11-22 22:08:33'),
(17, 3, 23, 'Consultation Fee', 500.00, 500.00, 'consultation', 'paid', 'admin@edoc.com', '2025-11-25 19:05:09', '2025-11-25 19:08:50'),
(18, 4, 24, 'Consultation Fee', 500.00, 500.00, 'consultation', 'paid', 'admin@edoc.com', '2025-11-25 19:17:15', '2025-11-25 19:28:56'),
(19, 5, 25, 'Consultation Fee', 500.00, 500.00, 'consultation', 'paid', 'cate@smartclinic.com', '2025-12-19 20:07:25', '2025-12-19 20:18:04'),
(20, 1, 26, 'Consultation Fee', 500.00, 500.00, 'consultation', 'paid', 'cate@smartclinic.com', '2025-12-23 13:08:30', '2025-12-23 15:10:04'),
(21, 8, 27, 'Consultation Fee', 500.00, 500.00, 'consultation', 'paid', 'cate@smartclinic.com', '2025-12-28 16:50:30', '2025-12-28 16:51:53'),
(22, 4, 28, 'Consultation Fee', 500.00, 500.00, 'consultation', 'paid', 'cate@smartclinic.com', '2025-12-30 18:44:09', '2025-12-31 11:50:02'),
(23, 5, 29, 'Consultation Fee', 500.00, 500.00, 'consultation', 'paid', 'aiden@smartclinic.com', '2025-12-31 11:47:59', '2025-12-31 11:50:06'),
(24, 9, 30, 'Consultation Fee', 500.00, 500.00, 'consultation', 'paid', 'Admin', '2026-02-28 09:55:57', '2026-02-28 10:00:45'),
(25, 10, 31, 'Consultation Fee', 500.00, 500.00, 'consultation', 'paid', 'admin@edoc.com', '2026-03-22 19:48:20', '2026-03-22 19:49:26'),
(26, 7, 32, 'Consultation Fee', 500.00, 500.00, 'consultation', 'paid', 'stanley@smartclinic.com', '2026-03-22 20:15:32', '2026-03-22 20:23:46'),
(27, 5, 33, 'Consultation Fee', 500.00, 500.00, 'consultation', 'paid', 'hannah@smartclinic.com', '2026-03-25 22:22:09', '2026-03-25 22:26:10');

-- --------------------------------------------------------

--
-- Table structure for table `patient_charges_backup`
--

CREATE TABLE `patient_charges_backup` (
  `id` int(11) NOT NULL DEFAULT 0,
  `pid` int(11) NOT NULL,
  `appoid` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `charge_type` enum('consultation','pharmacy','lab','procedure') NOT NULL,
  `status` enum('pending','paid') DEFAULT 'pending',
  `created_by` varchar(100) NOT NULL,
  `created_date` datetime NOT NULL,
  `paid_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_charges_backup`
--

INSERT INTO `patient_charges_backup` (`id`, `pid`, `appoid`, `description`, `amount`, `charge_type`, `status`, `created_by`, `created_date`, `paid_date`) VALUES
(1, 3, 2, 'Consultation Fee', 500.00, 'consultation', 'paid', 'Admin', '2025-11-07 12:56:52', '2025-11-07 14:45:08'),
(2, 1, 5, 'Consultation Fee', 500.00, 'consultation', 'paid', 'Admin', '2025-11-07 13:02:40', '2025-11-17 22:23:46'),
(3, 4, 6, 'Consultation Fee', 500.00, 'consultation', 'paid', 'Admin', '2025-11-12 11:45:32', '2025-11-12 11:51:23'),
(4, 5, 7, 'Consultation Fee', 500.00, 'consultation', 'paid', 'Admin', '2025-11-12 12:49:17', '2025-11-12 12:50:14'),
(5, 2, 8, 'Consultation Fee', 500.00, 'consultation', 'paid', 'Admin', '2025-11-12 12:57:25', '2025-11-12 12:59:27'),
(6, 6, 9, 'Consultation Fee', 500.00, 'consultation', 'paid', 'Admin', '2025-11-17 08:57:07', '2025-11-17 22:21:46'),
(7, 5, 10, 'Consultation Fee', 500.00, 'consultation', 'paid', 'superadmin', '2025-11-17 23:16:53', '2025-11-18 13:40:23'),
(8, 6, 12, 'Consultation Fee', 500.00, 'consultation', 'paid', 'Admin', '2025-11-18 10:34:31', '2025-11-18 10:38:24'),
(9, 4, 13, 'Consultation Fee', 500.00, 'consultation', 'paid', 'superadmin', '2025-11-18 12:09:39', '2025-11-18 15:48:20');

-- --------------------------------------------------------

--
-- Stand-in structure for view `patient_payment_status`
-- (See below for the actual view)
--
CREATE TABLE `patient_payment_status` (
`pid` int(11)
,`pname` varchar(255)
,`ptel` varchar(15)
,`unpaid_consultations` bigint(21)
,`consultation_dues` decimal(32,2)
,`unpaid_pharmacy_bills` bigint(21)
,`pharmacy_dues` decimal(64,2)
,`other_dues` decimal(32,2)
,`total_dues` decimal(65,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `pending_purchases_view`
-- (See below for the actual view)
--
CREATE TABLE `pending_purchases_view` (
`id` int(11)
,`productcategory` varchar(50)
,`company_name` varchar(255)
,`product_name` varchar(255)
,`unit` varchar(100)
,`packing_size` varchar(100)
,`quantity` int(11)
,`price` decimal(10,2)
,`party_name` varchar(255)
,`purchase_method` varchar(100)
,`expiry_date` date
,`purchase_date` date
,`username` varchar(100)
,`status` enum('pending','received','cancelled')
,`received_date` date
,`received_by` varchar(100)
,`notes` text
,`supplier_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `medication_name` varchar(255) NOT NULL,
  `dosage` varchar(100) NOT NULL,
  `frequency` varchar(100) NOT NULL,
  `duration` varchar(100) NOT NULL,
  `instructions` text DEFAULT NULL,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `dispensed` tinyint(1) DEFAULT 0,
  `dispensed_date` datetime DEFAULT NULL,
  `dispensed_by` int(11) DEFAULT NULL,
  `prescription_date` datetime DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `payment_status` enum('pending','paid','completed') DEFAULT 'pending',
  `payment_date` datetime DEFAULT NULL,
  `receipt_no` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescriptions`
--

INSERT INTO `prescriptions` (`id`, `patient_id`, `doctor_id`, `medication_name`, `dosage`, `frequency`, `duration`, `instructions`, `status`, `dispensed`, `dispensed_date`, `dispensed_by`, `prescription_date`, `created_at`, `updated_at`, `payment_status`, `payment_date`, `receipt_no`) VALUES
(1, 1, 1, 'Amoxicillin 500mg', '500mg', '3 times a day', '5 days', 'Take after meals', 'completed', 1, '2025-11-06 15:58:16', 0, '2025-11-03 10:28:55', '2025-11-03 07:28:55', '2025-11-06 14:58:16', 'pending', NULL, NULL),
(2, 2, 2, 'Insulin Glargine', '1 unit', 'once daily', '30 days', 'Inject before breakfast', 'completed', 1, '2025-11-06 15:58:21', 0, '2025-11-03 10:28:55', '2025-11-03 07:28:55', '2025-11-06 14:58:21', 'pending', NULL, NULL),
(3, 3, 1, 'Ciprofloxacin 400mg', '400mg', 'twice daily', '7 days', 'Take with water', 'completed', 1, '2025-11-07 09:08:11', 0, '2025-11-03 10:28:55', '2025-11-03 07:28:55', '2025-11-07 08:08:11', 'pending', NULL, NULL),
(4, 3, 2, 'Amoxicillin', '500mg', 'Three times daily', '7 days', 'take after meals', 'completed', 1, '2025-11-06 15:58:32', 0, '2025-11-03 15:31:17', '2025-11-03 12:31:17', '2025-11-06 14:58:32', 'pending', NULL, NULL),
(5, 3, 2, 'Amoxicillin', '500mg', 'Three times daily', '7 days', 'take after meals', 'completed', 1, '2025-11-06 15:58:10', 0, '2025-11-03 15:32:02', '2025-11-03 12:32:02', '2025-11-06 14:58:10', 'pending', NULL, NULL),
(7, 2, 3, 'Mara Moja', '500mg', 'Twice daily', 'as needed', '', '', 1, '2025-11-21 00:03:59', NULL, '2025-11-20 18:50:29', '2025-11-20 15:50:29', '2025-11-20 21:03:59', 'pending', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `prescription_details`
--

CREATE TABLE `prescription_details` (
  `prescription_detail_id` int(11) NOT NULL,
  `prescription_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `dosage` varchar(100) NOT NULL,
  `frequency` varchar(100) NOT NULL,
  `duration` varchar(100) NOT NULL,
  `instructions` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `prescription_dispensing_view`
-- (See below for the actual view)
--
CREATE TABLE `prescription_dispensing_view` (
`prescription_id` int(11)
,`medication` varchar(255)
,`dosage` varchar(100)
,`frequency` varchar(100)
,`duration` varchar(100)
,`prescription_status` enum('pending_pharmacy','dispensed','payment_pending','payment_complete','released')
,`pid` int(10)
,`patient_name` varchar(255)
,`billing_id` int(5)
,`billing_payment_status` enum('pending','paid')
,`payment_status` enum('paid','pending','failed','refunded')
);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `product_category` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `unit` varchar(100) DEFAULT NULL,
  `packing_size` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `company_name`, `product_category`, `product_name`, `unit`, `packing_size`) VALUES
(31, 'MyDawa', 'Medications', 'Mara Moja', 'box', '100'),
(32, 'RangePharmacy', 'Medications', 'Cough syrup', 'bottle', '100'),
(33, 'Transchem Pharmacy', 'Medications', 'Agumentin', 'capsule', '150'),
(34, 'MediPlus Pharmacy', 'Medications', 'Panadol', 'box', '100'),
(35, 'Family Access Pharmacy', 'Medications', 'Azetromizine', 'capsule', '50'),
(36, 'MediCare Pharmaceuticals', 'Medical Equipment', 'Blood Pressure Monitor', 'box', '25'),
(37, 'MedicPro', 'Medications', 'Laxatives', 'tablet', '50'),
(38, 'Transchem Pharmacy', 'Medical Supplies', 'Surgical Gloves', 'box', '50');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_master`
--

CREATE TABLE `purchase_master` (
  `id` int(11) NOT NULL,
  `productcategory` varchar(50) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `unit` varchar(100) DEFAULT NULL,
  `packing_size` varchar(100) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `party_name` varchar(255) DEFAULT NULL,
  `purchase_method` varchar(100) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `status` enum('pending','received','cancelled') DEFAULT 'pending',
  `received_date` date DEFAULT NULL,
  `received_by` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_master`
--

INSERT INTO `purchase_master` (`id`, `productcategory`, `company_name`, `product_name`, `unit`, `packing_size`, `quantity`, `price`, `party_name`, `purchase_method`, `expiry_date`, `purchase_date`, `username`, `status`, `received_date`, `received_by`, `notes`) VALUES
(48, 'Medications', 'MyDawa', 'Mara Moja', 'box', '100', 100, 20.00, 'MyDawa', 'cash', '2025-12-10', '2025-11-20', 'admin@edoc.com', 'received', '2025-12-22', 'admin@edoc.com', ''),
(49, 'Medications', 'RangePharmacy', 'Cough syrup', 'bottle', '150', 100, 550.00, 'RangePharmacy', 'Mpesa', '2025-12-31', '2025-11-20', 'admin@edoc.com', 'received', '2025-12-22', 'admin@edoc.com', ''),
(50, 'Medications', 'Transchem Pharmacy', 'Agumentin', 'capsule', '100', 150, 450.00, 'Transchem Pharmacy', 'cash', '2026-01-14', '2025-11-20', 'admin@edoc.com', 'pending', NULL, NULL, NULL),
(51, 'Medications', 'MediPlus Pharmacy', 'Panadol', 'box', '100', 50, 20.00, 'MediPlus Pharmacy', 'Mpesa', '2025-12-25', '2025-11-25', 'admin@edoc.com', 'pending', NULL, NULL, NULL),
(52, 'Medications', 'Family Access Pharmacy', 'Azetromizine', 'capsule', '3', 50, 100.00, 'Family Access Pharmacy', 'cash', '2026-12-31', '2025-11-25', 'admin@edoc.com', 'received', '2025-12-22', 'admin@edoc.com', ''),
(53, 'Medications', 'MedicPro', 'Laxatives', 'tablet', '50', 100, 20.00, 'MedicPro', 'cash', '2026-12-31', '2025-12-22', 'admin@edoc.com', 'received', '2025-12-23', 'admin@edoc.com', ''),
(54, 'Medications', 'MedicPro', 'Laxatives', 'tablet', '50', 100, 20.00, 'MedicPro', 'cash', '2026-12-31', '2025-12-22', 'admin@edoc.com', 'pending', NULL, NULL, NULL),
(55, 'Medications', 'MyDawa', 'Strepsils', 'sachet', '100', 90, 15.00, 'MyDawa', 'Mpesa', '2026-12-27', '2025-12-23', 'admin@edoc.com', 'received', '2025-12-23', 'admin@edoc.com', '10 units spoilt. They refunded the money.'),
(56, 'Medications', 'MediPlus Pharmacy', 'Panadol', 'box', '50', 50, 10.00, 'MediPlus Pharmacy', 'cash', '2028-03-31', '2026-03-25', 'pharmacy@smartclinic.com', 'pending', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `return_products`
--

CREATE TABLE `return_products` (
  `id` int(11) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `bill_no` varchar(10) DEFAULT NULL,
  `return_date` date DEFAULT NULL,
  `product_company` varchar(255) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `product_unit` varchar(100) DEFAULT NULL,
  `packing_size` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `return_products`
--

INSERT INTO `return_products` (`id`, `username`, `bill_no`, `return_date`, `product_company`, `product_name`, `product_unit`, `packing_size`, `price`, `quantity`, `total`) VALUES
(1, 'admin', '', '2025-10-14', 'GracePharmacuticals', 'Cough syrup', 'ml', '100', 750.00, 2, 1500.00),
(2, 'admin', '', '2025-10-14', 'MediCare Pharmaceuticals', 'Surgical Spirit', 'ml', '100', 800.00, 1, 800.00),
(3, 'admin', '', '2025-10-14', 'MediCare Pharmaceuticals', 'Surgical Spirit', 'ml', '100', 800.00, 1, 800.00),
(4, 'admin', '', '2025-10-14', 'MediCare Pharmaceuticals', 'Surgical Spirit', 'ml', '100', 800.00, 2, 1600.00),
(5, 'admin', '', '2025-10-17', 'MediPlugequipments', 'syringes', 'injection', '100', 115.00, 2, 230.00),
(6, 'admin', '00015', '2025-10-17', 'MediCare Pharmaceuticals', 'Amoxicillin', 'box', '100', 850.00, 1, 850.00);

-- --------------------------------------------------------

--
-- Table structure for table `schedule`
--

CREATE TABLE `schedule` (
  `scheduleid` int(11) NOT NULL,
  `docid` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `scheduledate` date DEFAULT NULL,
  `scheduletime` time DEFAULT NULL,
  `nop` int(4) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `schedule`
--

INSERT INTO `schedule` (`scheduleid`, `docid`, `title`, `scheduledate`, `scheduletime`, `nop`) VALUES
(10, '2', 'Dental/oral hygeine consultations', '2025-11-03', '14:30:00', 5),
(14, '4', 'Psychiatrist/ mental well being', '2025-11-17', '08:50:00', 5),
(22, '3', 'General Consultations', '2025-12-23', '13:00:00', 5),
(21, '3', 'general consultations', '2025-12-19', '20:00:00', 5),
(20, '3', 'general consultations', '2025-11-25', '19:00:00', 10),
(15, '3', 'General Consultations', '2025-11-17', '23:15:00', 10),
(16, '3', 'General Care Consultations', '2025-11-18', '10:30:00', 10),
(17, '3', 'Consultations', '2025-11-20', '16:30:00', 10),
(18, '2', 'Dental Consults', '2025-11-21', '00:05:00', 5),
(19, '3', 'General consultations', '2025-11-22', '11:05:00', 10),
(23, '3', 'consultations', '2025-12-28', '16:40:00', 5),
(24, '7', 'General consults', '2025-12-31', '11:40:00', 10),
(25, '3', 'General Consultation', '2026-02-28', '09:50:00', 10),
(26, '8', 'General Consultation', '2026-03-22', '19:45:00', 5),
(27, '2', 'Dental/Oral Hygeine Consultations', '2026-03-25', '21:50:00', 10);

-- --------------------------------------------------------

--
-- Table structure for table `specialties`
--

CREATE TABLE `specialties` (
  `id` int(2) NOT NULL,
  `sname` varchar(50) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `specialties`
--

INSERT INTO `specialties` (`id`, `sname`) VALUES
(1, 'Accident and emergency medicine'),
(2, 'Allergology'),
(3, 'Anaesthetics'),
(4, 'Biological hematology'),
(5, 'Cardiology'),
(6, 'Child psychiatry'),
(7, 'Clinical biology'),
(8, 'Clinical chemistry'),
(9, 'Clinical neurophysiology'),
(10, 'Clinical radiology'),
(11, 'Dental, oral and maxillo-facial surgery'),
(12, 'Dermato-venerology'),
(13, 'Dermatology'),
(14, 'Endocrinology'),
(15, 'Gastro-enterologic surgery'),
(16, 'Gastroenterology'),
(17, 'General hematology'),
(18, 'General Practice'),
(19, 'General surgery'),
(20, 'Geriatrics'),
(21, 'Immunology'),
(22, 'Infectious diseases'),
(23, 'Internal medicine'),
(24, 'Laboratory medicine'),
(25, 'Maxillo-facial surgery'),
(26, 'Microbiology'),
(27, 'Nephrology'),
(28, 'Neuro-psychiatry'),
(29, 'Neurology'),
(30, 'Neurosurgery'),
(31, 'Nuclear medicine'),
(32, 'Obstetrics and gynecology'),
(33, 'Occupational medicine'),
(34, 'Ophthalmology'),
(35, 'Orthopaedics'),
(36, 'Otorhinolaryngology'),
(37, 'Paediatric surgery'),
(38, 'Paediatrics'),
(39, 'Pathology'),
(40, 'Pharmacology'),
(41, 'Physical medicine and rehabilitation'),
(42, 'Plastic surgery'),
(43, 'Podiatric Medicine'),
(44, 'Podiatric Surgery'),
(45, 'Psychiatry'),
(46, 'Public health and Preventive Medicine'),
(47, 'Radiology'),
(48, 'Radiotherapy'),
(49, 'Respiratory medicine'),
(50, 'Rheumatology'),
(51, 'Stomatology'),
(52, 'Thoracic surgery'),
(53, 'Tropical medicine'),
(54, 'Urology'),
(55, 'Vascular surgery'),
(56, 'Venereology');

-- --------------------------------------------------------

--
-- Table structure for table `stock_master`
--

CREATE TABLE `stock_master` (
  `id` int(11) NOT NULL,
  `product_company` varchar(255) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `product_unit` varchar(100) DEFAULT NULL,
  `packing_size` varchar(100) DEFAULT NULL,
  `product_quantity` int(11) DEFAULT NULL,
  `product_selling_price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_master`
--

INSERT INTO `stock_master` (`id`, `product_company`, `product_name`, `product_unit`, `packing_size`, `product_quantity`, `product_selling_price`) VALUES
(27, 'MyDawa', 'Mara Moja', 'box', '100', 90, 20.00),
(28, 'RangePharmacy', 'Cough syrup', 'bottle', '150', 196, 550.00),
(29, 'Transchem Pharmacy', 'Agumentin', 'capsule', '100', 133, 500.00),
(30, 'MediPlus Pharmacy', 'Panadol', 'box', '100', 60, 15.00),
(31, 'Family Access Pharmacy', 'Azetromizine', 'capsule', '3', 97, 100.00),
(32, 'MedicPro', 'Laxatives', 'tablet', '50', 100, 20.00),
(33, 'MyDawa', 'Strepsils', 'sachet', '100', 80, 20.00);

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(5) NOT NULL,
  `firstname` varchar(50) NOT NULL,
  `lastname` varchar(50) NOT NULL,
  `businessname` varchar(100) NOT NULL,
  `productcategory` varchar(50) NOT NULL,
  `contact` varchar(20) NOT NULL,
  `address` varchar(50) NOT NULL,
  `city` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `firstname`, `lastname`, `businessname`, `productcategory`, `contact`, `address`, `city`) VALUES
(13, 'James', 'Wairimu', 'MyDawa', 'Medications', '0726908654', 'Nairobi\r\nKenya', 'Nairobi'),
(14, 'Ann', 'Wambua', 'RangePharmacy', 'Medications', '0718904321', 'Nairobi\r\nKenya', 'Nairobi'),
(15, 'Grace', 'Odwour', 'Transchem Pharmacy', 'Medical Supplies', '0726964087', 'Nairobi\r\nKenya', 'Nairobi'),
(16, 'Christian', 'Kerui', 'MediPlus Pharmacy', 'Medications', '0790437810', 'Nairobi', 'Nairobi'),
(17, 'Elizabeth', 'Wanjiru', 'Family Access Pharmacy', 'Medications', '0790856432', 'Nairobi', 'Nairobi'),
(18, 'Hannah', 'Nyaboke', 'MediCare Pharmaceuticals', 'Medical Equipment', '0780954321', 'Nairobi\r\nKenya', 'Nairobi'),
(19, 'Henry', 'Mwai', 'MedicPro', 'Medications', '0790854321', 'Nairobi', 'Nairobi');

-- --------------------------------------------------------

--
-- Table structure for table `unit`
--

CREATE TABLE `unit` (
  `id` int(11) NOT NULL,
  `unit` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `unit`
--

INSERT INTO `unit` (`id`, `unit`) VALUES
(1, 'ml'),
(2, 'tablet'),
(3, 'mm'),
(4, 'mg'),
(5, 'capsule'),
(6, 'injection'),
(8, 'box'),
(9, 'tube'),
(12, 'bottle'),
(14, 'sachet'),
(15, 'each'),
(17, 'gram'),
(18, 'kg'),
(20, 'pump');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('doctor','admin','patient','pharmacist','receptionist') NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `gender` enum('Male','Female') DEFAULT NULL,
  `date_created` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `username`, `email`, `password`, `role`, `phone`, `gender`, `date_created`) VALUES
(1, 'Dr. Cate Wamuhu', 'dr.cate', 'wamuhu@smartclinic.com', 'cate254', 'doctor', '0701000001', 'Female', '2025-11-03 06:53:20'),
(2, 'Dr. Mary Atieno', 'dr.mary', 'mary@smartclinic.com', 'mary2', 'doctor', '0701000002', 'Female', '2025-11-03 06:53:20'),
(3, 'John Kiptoo', 'pharma', 'john@smartclinic.com', 'johnpharmacist', 'pharmacist', '0701000003', 'Male', '2025-11-03 06:53:20'),
(4, 'Lucy Wanjiru', 'recept.lucy', 'lucy@smartclinic.com', 'lucy', 'receptionist', '0701000004', 'Female', '2025-11-03 06:53:20'),
(6, 'hannah nyokabi', 'hannah', 'hannah@smartclinic.com', 'hannah', 'doctor', '', '', '2025-11-03 10:17:12'),
(7, 'cate Wamuhu', 'cate', 'cate@smartclinic.com', 'cate254', 'doctor', '', '', '2025-11-03 10:17:55'),
(8, 'Phoebe Ndalo', 'pharmacy', 'pharmacy@smartclinic.com', 'pharmacypass', 'pharmacist', '', '', '2025-11-03 10:19:17'),
(9, 'Mary Maranga', 'reception1', 'reception1@smartclinic.com', 'reception', 'receptionist', '', '', '2025-11-03 10:19:39'),
(10, 'Hillary Omwancha', 'hillary@gmail.com', 'hillary@gmail.com', 'hilla', 'patient', '0780965421', '', '2025-11-03 11:09:28'),
(11, 'collins ongwach', 'collins@gmail.com', 'collins@gmail.com', 'collins', 'patient', '0789544310', '', '2025-11-03 11:22:02'),
(12, 'Stefanie Ochieng', 'stefanie@gmail.com', 'stefanie@gmail.com', 'stef', 'patient', '0780967632', '', '2025-11-22 08:24:33'),
(13, 'Danah Murumba', 'Dr.Danah', 'Dr.Danah@smartclinic.com', 'danah', 'doctor', '', '', '2025-12-31 07:05:52'),
(14, 'Aiden Wairimu', 'aiden', 'aiden@smartclinic.com', 'aiden', 'doctor', '', '', '2025-12-31 07:33:49'),
(15, 'Stanley Kiprop', 'Dr. Stanley', 'Dr. Stanley@smartclinic.com', 'stan', 'doctor', '', '', '2026-03-22 16:41:04'),
(16, 'Maryanne Wangui', 'maryanne@gmail.com', 'maryanne@gmail.com', 'maryanne', 'patient', '0100976432', '', '2026-03-22 16:44:56');

-- --------------------------------------------------------

--
-- Table structure for table `user_registration`
--

CREATE TABLE `user_registration` (
  `id` int(11) NOT NULL,
  `firstname` varchar(100) DEFAULT NULL,
  `lastname` varchar(100) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_registration`
--

INSERT INTO `user_registration` (`id`, `firstname`, `lastname`, `username`, `password`, `role`, `status`) VALUES
(11, 'hannah', 'nyokabi', 'hannah', 'hannah', 'Doctor', 'active'),
(12, 'cate', 'Wamuhu', 'cate', 'cate254', 'Doctor', 'active'),
(13, 'Phoebe', 'Ndalo', 'pharmacy@smartclinic.com', 'pass', 'Pharmacist', 'active'),
(14, 'Mary', 'Maranga', 'reception1@smartclinic.com', 'reception', 'Receptionist', 'active'),
(17, 'Danah', 'Murumba', 'danah', 'danah', 'Doctor', 'active'),
(18, 'Aiden', 'Wairimu', 'aiden', 'aiden', 'Doctor', 'active'),
(19, 'Stanley', 'Kiprop', 'Dr. Stanley', 'stan', 'Doctor', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `webuser`
--

CREATE TABLE `webuser` (
  `email` varchar(255) NOT NULL,
  `usertype` char(1) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `webuser`
--

INSERT INTO `webuser` (`email`, `usertype`) VALUES
('admin@edoc.com', 'a'),
('doctor@edoc.com', 'd'),
('patient@edoc.com', 'p'),
('emhashenudara@gmail.com', 'p'),
('hannah@smartclinic.com', 'd'),
('cate@smartclinic.com', 'd'),
('pharmacy@smartclinic.com', 'u'),
('reception1@smartclinic.com', 'r'),
('hillary@gmail.com', 'p'),
('collins@gmail.com', 'p'),
('mary@gmail.com', 'p'),
('marvin254@gmail.com', 'd'),
('miri@gmail.com', 'p'),
('finance@smartclinic.com', 'u'),
('stefanie@gmail.com', 'p'),
('susan@gmail.com', 'p'),
('Dr.Danah@smartclinic.com', 'd'),
('aiden@smartclinic.com', 'd'),
('brian@gmail.com', 'p'),
('stanley@smartclinic.com', 'd'),
('maryanne@gmail.com', 'p');

-- --------------------------------------------------------

--
-- Structure for view `billing_patient_view`
--
DROP TABLE IF EXISTS `billing_patient_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `billing_patient_view`  AS SELECT `b`.`id` AS `billing_id`, `b`.`bill_no` AS `bill_no`, `b`.`CustomerName` AS `CustomerName`, `b`.`date` AS `date`, `b`.`bill_type` AS `bill_type`, `b`.`payment_status` AS `payment_status`, `b`.`pid` AS `pid`, `p`.`pname` AS `patient_name`, `p`.`pemail` AS `patient_email`, `p`.`ptel` AS `patient_phone` FROM (`billing` `b` left join `patient` `p` on(`b`.`pid` = `p`.`pid`)) ;

-- --------------------------------------------------------

--
-- Structure for view `patient_bill_summary`
--
DROP TABLE IF EXISTS `patient_bill_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `patient_bill_summary`  AS SELECT `a`.`appoid` AS `appoid`, `a`.`pid` AS `pid`, `p`.`pname` AS `pname`, `a`.`appodate` AS `appodate`, coalesce(`pc`.`amount`,0) AS `consultation_fee`, coalesce(`pharm`.`amount`,0) AS `pharmacy_fee`, coalesce(`pc`.`amount`,0) + coalesce(`pharm`.`amount`,0) AS `total_due`, CASE WHEN `bp`.`id` is not null THEN 'paid' WHEN `b`.`id` is not null THEN 'ready_for_payment' ELSE 'pending' END AS `payment_status`, `a`.`workflow_stage` AS `workflow_stage` FROM (((((`appointment` `a` left join `patient` `p` on(`a`.`pid` = `p`.`pid`)) left join `patient_charges` `pc` on(`a`.`appoid` = `pc`.`appoid`)) left join `billing` `b` on(`a`.`pid` = `b`.`pid` and `a`.`appoid` = `b`.`appoid`)) left join (select `billing_details`.`billing_id` AS `billing_id`,sum(`billing_details`.`price` * `billing_details`.`quantity`) AS `amount` from `billing_details` group by `billing_details`.`billing_id`) `pharm` on(`b`.`id` = `pharm`.`billing_id`)) left join `billing_payments` `bp` on(`a`.`appoid` = `bp`.`reference_id` and `bp`.`type` = 'consultation')) WHERE `a`.`status` = 'Complete' ;

-- --------------------------------------------------------

--
-- Structure for view `patient_payment_status`
--
DROP TABLE IF EXISTS `patient_payment_status`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `patient_payment_status`  AS SELECT `p`.`pid` AS `pid`, `p`.`pname` AS `pname`, `p`.`ptel` AS `ptel`, count(distinct case when `a`.`status` = 'Complete' and `pc`.`status` = 'pending' and `pc`.`charge_type` = 'consultation' then `a`.`appoid` end) AS `unpaid_consultations`, coalesce(sum(case when `a`.`status` = 'Complete' and `pc`.`status` = 'pending' and `pc`.`charge_type` = 'consultation' then `pc`.`amount` end),0) AS `consultation_dues`, count(distinct case when `b`.`payment_status` = 'pending' then `b`.`id` end) AS `unpaid_pharmacy_bills`, coalesce(sum(case when `b`.`payment_status` = 'pending' then (select sum(`bd`.`price` * `bd`.`quantity`) from `billing_details` `bd` where `bd`.`billing_id` = `b`.`id`) end),0) AS `pharmacy_dues`, coalesce(sum(case when `pc`.`status` = 'pending' and `pc`.`charge_type` <> 'consultation' then `pc`.`amount` end),0) AS `other_dues`, coalesce(sum(case when `a`.`status` = 'Complete' and `pc`.`status` = 'pending' and `pc`.`charge_type` = 'consultation' then `pc`.`amount` end),0) + coalesce(sum(case when `b`.`payment_status` = 'pending' then (select sum(`bd`.`price` * `bd`.`quantity`) from `billing_details` `bd` where `bd`.`billing_id` = `b`.`id`) end),0) + coalesce(sum(case when `pc`.`status` = 'pending' and `pc`.`charge_type` <> 'consultation' then `pc`.`amount` end),0) AS `total_dues` FROM (((`patient` `p` left join `appointment` `a` on(`p`.`pid` = `a`.`pid`)) left join `patient_charges` `pc` on(`p`.`pid` = `pc`.`pid`)) left join `billing` `b` on(`p`.`pid` = `b`.`pid`)) GROUP BY `p`.`pid`, `p`.`pname`, `p`.`ptel` ;

-- --------------------------------------------------------

--
-- Structure for view `pending_purchases_view`
--
DROP TABLE IF EXISTS `pending_purchases_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `pending_purchases_view`  AS SELECT `pm`.`id` AS `id`, `pm`.`productcategory` AS `productcategory`, `pm`.`company_name` AS `company_name`, `pm`.`product_name` AS `product_name`, `pm`.`unit` AS `unit`, `pm`.`packing_size` AS `packing_size`, `pm`.`quantity` AS `quantity`, `pm`.`price` AS `price`, `pm`.`party_name` AS `party_name`, `pm`.`purchase_method` AS `purchase_method`, `pm`.`expiry_date` AS `expiry_date`, `pm`.`purchase_date` AS `purchase_date`, `pm`.`username` AS `username`, `pm`.`status` AS `status`, `pm`.`received_date` AS `received_date`, `pm`.`received_by` AS `received_by`, `pm`.`notes` AS `notes`, `s`.`businessname` AS `supplier_name` FROM (`purchase_master` `pm` left join `suppliers` `s` on(`pm`.`party_name` = `s`.`businessname`)) WHERE `pm`.`status` = 'pending' ORDER BY `pm`.`purchase_date` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `prescription_dispensing_view`
--
DROP TABLE IF EXISTS `prescription_dispensing_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `prescription_dispensing_view`  AS SELECT `ep`.`id` AS `prescription_id`, `ep`.`medication` AS `medication`, `ep`.`dosage` AS `dosage`, `ep`.`frequency` AS `frequency`, `ep`.`duration` AS `duration`, `ep`.`status` AS `prescription_status`, `a`.`pid` AS `pid`, `p`.`pname` AS `patient_name`, `b`.`id` AS `billing_id`, `b`.`payment_status` AS `billing_payment_status`, `bp`.`status` AS `payment_status` FROM ((((`ehr_prescriptions` `ep` join `appointment` `a` on(`ep`.`appoid` = `a`.`appoid`)) join `patient` `p` on(`a`.`pid` = `p`.`pid`)) left join `billing` `b` on(`a`.`pid` = `b`.`pid`)) left join `billing_payments` `bp` on(`b`.`id` = `bp`.`reference_id` and `bp`.`type` = 'pharmacy')) WHERE `ep`.`status` = 'pending_pharmacy' ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`aemail`);

--
-- Indexes for table `appointment`
--
ALTER TABLE `appointment`
  ADD PRIMARY KEY (`appoid`),
  ADD KEY `pid` (`pid`),
  ADD KEY `scheduleid` (`scheduleid`),
  ADD KEY `idx_pid_status` (`pid`,`status`),
  ADD KEY `idx_appodate` (`appodate`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `billing`
--
ALTER TABLE `billing`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pid` (`pid`),
  ADD KEY `idx_comprehensive_billing` (`pid`,`payment_status`,`id`);

--
-- Indexes for table `billing_details`
--
ALTER TABLE `billing_details`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `billing_payments`
--
ALTER TABLE `billing_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pid` (`pid`),
  ADD KEY `idx_type_reference` (`type`,`reference_id`),
  ADD KEY `idx_payment_date` (`payment_date`),
  ADD KEY `idx_receipt_no` (`receipt_no`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_type_status` (`type`,`status`),
  ADD KEY `idx_reference` (`reference_id`),
  ADD KEY `idx_pid_type_status` (`pid`,`type`,`status`),
  ADD KEY `idx_reference_type` (`reference_id`,`type`),
  ADD KEY `idx_comprehensive_search` (`pid`,`status`,`type`,`reference_id`);

--
-- Indexes for table `company_name`
--
ALTER TABLE `company_name`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `doctor`
--
ALTER TABLE `doctor`
  ADD PRIMARY KEY (`docid`),
  ADD KEY `specialties` (`specialties`);

--
-- Indexes for table `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`doctor_id`);

--
-- Indexes for table `doctor_work_hours`
--
ALTER TABLE `doctor_work_hours`
  ADD PRIMARY KEY (`id`),
  ADD KEY `docid` (`docid`);

--
-- Indexes for table `ehr_prescriptions`
--
ALTER TABLE `ehr_prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_appoid` (`appoid`);

--
-- Indexes for table `ehr_records`
--
ALTER TABLE `ehr_records`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD PRIMARY KEY (`record_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `appointment_id` (`appointment_id`);

--
-- Indexes for table `part_info`
--
ALTER TABLE `part_info`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `patient`
--
ALTER TABLE `patient`
  ADD PRIMARY KEY (`pid`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`patient_id`);

--
-- Indexes for table `patient_charges`
--
ALTER TABLE `patient_charges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_appoid` (`appoid`),
  ADD KEY `idx_status_appoid` (`status`,`appoid`),
  ADD KEY `idx_comprehensive_charges` (`pid`,`status`,`charge_type`,`appoid`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `dispensed_by` (`dispensed_by`),
  ADD KEY `status` (`status`),
  ADD KEY `dispensed` (`dispensed`),
  ADD KEY `idx_dispensed` (`dispensed`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `prescription_details`
--
ALTER TABLE `prescription_details`
  ADD PRIMARY KEY (`prescription_detail_id`),
  ADD KEY `prescription_id` (`prescription_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `purchase_master`
--
ALTER TABLE `purchase_master`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `return_products`
--
ALTER TABLE `return_products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `schedule`
--
ALTER TABLE `schedule`
  ADD PRIMARY KEY (`scheduleid`),
  ADD KEY `docid` (`docid`);

--
-- Indexes for table `specialties`
--
ALTER TABLE `specialties`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stock_master`
--
ALTER TABLE `stock_master`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `unit`
--
ALTER TABLE `unit`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_registration`
--
ALTER TABLE `user_registration`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `webuser`
--
ALTER TABLE `webuser`
  ADD PRIMARY KEY (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointment`
--
ALTER TABLE `appointment`
  MODIFY `appoid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `billing`
--
ALTER TABLE `billing`
  MODIFY `id` int(5) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `billing_details`
--
ALTER TABLE `billing_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT for table `billing_payments`
--
ALTER TABLE `billing_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `company_name`
--
ALTER TABLE `company_name`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `doctor`
--
ALTER TABLE `doctor`
  MODIFY `docid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `doctor_work_hours`
--
ALTER TABLE `doctor_work_hours`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `ehr_prescriptions`
--
ALTER TABLE `ehr_prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `ehr_records`
--
ALTER TABLE `ehr_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `medical_records`
--
ALTER TABLE `medical_records`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `part_info`
--
ALTER TABLE `part_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `patient`
--
ALTER TABLE `patient`
  MODIFY `pid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `patient_charges`
--
ALTER TABLE `patient_charges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `prescription_details`
--
ALTER TABLE `prescription_details`
  MODIFY `prescription_detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `purchase_master`
--
ALTER TABLE `purchase_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `return_products`
--
ALTER TABLE `return_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `schedule`
--
ALTER TABLE `schedule`
  MODIFY `scheduleid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `stock_master`
--
ALTER TABLE `stock_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(5) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `unit`
--
ALTER TABLE `unit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `user_registration`
--
ALTER TABLE `user_registration`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`) ON DELETE CASCADE;

--
-- Constraints for table `doctors`
--
ALTER TABLE `doctors`
  ADD CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD CONSTRAINT `medical_records_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medical_records_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medical_records_ibfk_3` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE SET NULL;

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `prescription_details`
--
ALTER TABLE `prescription_details`
  ADD CONSTRAINT `prescription_details_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescription_details_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
