-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 08, 2026 at 09:32 AM
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
-- Database: `hospital2`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `ProcessPayment` (IN `p_patient_id` INT, IN `p_service_type` VARCHAR(50), IN `p_service_id` INT, IN `p_payment_amount` DECIMAL(10,2), IN `p_payment_method` VARCHAR(50))   BEGIN
    DECLARE v_total_amount DECIMAL(10,2);
    DECLARE v_paid_amount DECIMAL(10,2);
    DECLARE v_new_status VARCHAR(20);
    
    START TRANSACTION;
    
    CASE p_service_type
        WHEN 'consultation' THEN
            SELECT consultation_fee INTO v_total_amount 
            FROM appointments WHERE id = p_service_id AND patient_id = p_patient_id;
            
            IF v_total_amount IS NOT NULL THEN
                IF p_payment_amount >= v_total_amount THEN
                    UPDATE appointments 
                    SET payment_status = 'paid'
                    WHERE id = p_service_id;
                ELSE
                    UPDATE appointments 
                    SET payment_status = 'partial'
                    WHERE id = p_service_id;
                END IF;
            END IF;
            
        WHEN 'lab' THEN
            SELECT total_amount, paid_amount INTO v_total_amount, v_paid_amount
            FROM lab_orders WHERE id = p_service_id AND patient_id = p_patient_id;
            
            IF v_total_amount IS NOT NULL THEN
                SET v_paid_amount = COALESCE(v_paid_amount, 0) + p_payment_amount;
                
                IF v_paid_amount >= v_total_amount THEN
                    UPDATE lab_orders 
                    SET payment_status = 'paid', paid_amount = v_paid_amount
                    WHERE id = p_service_id;
                ELSE
                    UPDATE lab_orders 
                    SET payment_status = 'partial', paid_amount = v_paid_amount
                    WHERE id = p_service_id;
                END IF;
            END IF;
            
        -- Add similar cases for other service types
        
    END CASE;
    
    -- Insert payment record
    INSERT INTO payments (patient_id, service_type, service_id, amount, payment_method, payment_date)
    VALUES (p_patient_id, p_service_type, p_service_id, p_payment_amount, p_payment_method, NOW());
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `update_historical_payments` ()   BEGIN
    -- Update completed appointments
    UPDATE appointments 
    SET consultation_fee = CASE 
        WHEN consultation_fee = 0 AND status = 'completed' THEN 500.00 
        ELSE consultation_fee 
    END
    WHERE status = 'completed' AND consultation_fee = 0;
    
    -- Update lab orders that are completed but not marked as paid
    UPDATE lab_orders 
    SET payment_status = 'paid'
    WHERE status = 'completed' AND payment_status = 'unpaid';
    
    -- Update pharmacy billing for historical records
    UPDATE pharmacy_billing 
    SET payment_status = 'paid'
    WHERE payment_status = 'pending' AND date < CURDATE() - INTERVAL 7 DAY;
    
    -- Update discharged bed assignments
    UPDATE bed_assignments 
    SET status = 'discharged', 
        actual_discharge_date = COALESCE(actual_discharge_date, expected_discharge_date)
    WHERE status = 'active' AND expected_discharge_date < CURDATE();
END$$

--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `generate_patient_id` () RETURNS VARCHAR(20) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC READS SQL DATA BEGIN
    DECLARE next_id INT;
    DECLARE new_patient_id VARCHAR(20);
    
    -- Get the maximum numeric part from existing patient_ids
    SELECT COALESCE(MAX(CAST(SUBSTRING(patient_id, 3) AS UNSIGNED)), 0) INTO next_id 
    FROM patients 
    WHERE patient_id REGEXP '^PT[0-9]+$';
    
    -- Increment and format
    SET next_id = next_id + 1;
    SET new_patient_id = CONCAT('PT', LPAD(next_id, 6, '0'));
    
    RETURN new_patient_id;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `get_next_patient_id` () RETURNS VARCHAR(20) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC READS SQL DATA BEGIN
    DECLARE next_id INT;
    UPDATE patient_sequence SET last_id = last_id + 1;
    SELECT last_id INTO next_id FROM patient_sequence;
    RETURN CONCAT('PT', LPAD(next_id, 6, '0'));
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(10) UNSIGNED NOT NULL,
  `appointment_number` varchar(50) DEFAULT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `doctor_id` int(10) UNSIGNED NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `duration` int(11) DEFAULT 30 COMMENT 'Duration in minutes',
  `appointment_type` enum('consultation','followup','emergency','procedure','checkup') DEFAULT 'consultation',
  `service_id` int(10) UNSIGNED DEFAULT NULL,
  `consultation_fee` decimal(10,2) DEFAULT 0.00,
  `department` varchar(100) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('scheduled','confirmed','in_progress','completed','cancelled','no_show','rescheduled') DEFAULT 'scheduled',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `cancelled_by` int(10) UNSIGNED DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `reminder_sent` tinyint(1) DEFAULT 0,
  `reminder_sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `payment_status` enum('pending','paid','partial') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `appointment_number`, `patient_id`, `doctor_id`, `appointment_date`, `appointment_time`, `duration`, `appointment_type`, `service_id`, `consultation_fee`, `department`, `reason`, `notes`, `status`, `created_by`, `cancelled_by`, `cancellation_reason`, `reminder_sent`, `reminder_sent_at`, `created_at`, `updated_at`, `completed_at`, `payment_status`) VALUES
(1, 'APT-202510-65633', 6, 61, '2025-10-07', '13:30:00', 45, 'checkup', NULL, 0.00, NULL, 'Has a high fever and difficulty breathing.', 'Travelled to Rwanda last month.', 'scheduled', 55, NULL, NULL, 0, NULL, '2025-10-01 21:48:10', '2025-10-01 21:53:40', NULL, 'pending'),
(2, 'APT-202510-69923', 2, 59, '2025-10-12', '14:00:00', 90, 'procedure', NULL, 0.00, NULL, 'Heart surgery coming up. Appointment is to discuss preparations.', '', 'confirmed', 55, NULL, NULL, 0, NULL, '2025-10-01 21:53:12', '2025-10-05 18:29:53', NULL, 'pending'),
(3, 'APT-202510-68602', 6, 61, '2025-10-13', '13:30:00', 30, 'followup', NULL, 0.00, NULL, 'Follow up on previous diagnosis and review on how the medications have reacted.', 'Has developed rashes', 'cancelled', 55, NULL, NULL, 0, NULL, '2025-10-04 09:04:09', '2025-10-06 12:08:33', NULL, 'pending'),
(4, 'APT-202510-29030', 6, 60, '2025-10-05', '23:00:00', 30, 'emergency', NULL, 0.00, NULL, 'Head injury, coupled with confusion and numbness on the right side of her face.', 'A post fell on her while walking near a construction site.', 'in_progress', 65, NULL, NULL, 0, NULL, '2025-10-05 18:35:00', '2025-10-05 18:41:49', NULL, 'pending'),
(5, 'APT-202510-91748', 6, 59, '2025-10-06', '15:05:00', 30, 'consultation', NULL, 0.00, NULL, 'Chest tightness and Nausea.', '', 'completed', 55, NULL, NULL, 0, NULL, '2025-10-06 11:05:27', '2025-10-06 12:08:22', NULL, 'pending'),
(6, 'APT-202510-39302', 8, 64, '2025-10-07', '07:40:00', 30, 'consultation', NULL, 0.00, 'neurology', 'Developed Rashes and mild fever.', '', 'scheduled', 55, NULL, NULL, 0, NULL, '2025-10-06 16:35:06', NULL, NULL, 'pending'),
(8, 'APT-202510-27679', 6, 64, '2025-10-19', '10:15:00', 30, 'consultation', NULL, 0.00, 'neurology', 'Splitting headache, and complains of stomach ache', '', 'completed', 55, NULL, NULL, 0, NULL, '2025-10-18 19:14:45', '2025-10-19 14:40:30', NULL, 'pending'),
(9, 'APT-202510-98287', 6, 67, '2025-10-28', '10:45:00', 45, 'consultation', NULL, 0.00, 'pediatrics', 'Consultation', '', 'confirmed', 55, NULL, NULL, 0, NULL, '2025-10-26 12:53:31', '2025-10-26 19:22:22', NULL, 'pending'),
(10, 'APT-202510-83492', 6, 64, '2025-10-27', '11:30:00', 60, 'procedure', NULL, 0.00, 'neurology', 'Minor surgery', '', 'completed', 55, 55, 'Cancelled by admin', 0, NULL, '2025-10-26 19:23:36', '2025-10-27 14:26:28', NULL, 'pending'),
(11, 'APT-202510-00747', 6, 68, '2025-10-28', '11:30:00', 30, 'consultation', NULL, 0.00, 'orthopedics', 'Complains of joint pains and knee swelling.', 'Diagnosed with arthritis.', 'confirmed', 55, NULL, NULL, 0, NULL, '2025-10-27 07:55:21', '2025-10-27 07:55:55', NULL, 'pending'),
(12, 'APT-202510-15827', 6, 59, '2025-10-27', '12:00:00', 30, 'consultation', NULL, 0.00, NULL, 'Experiencing stomach pain and extreme nausea.', '', 'completed', 55, NULL, NULL, 0, NULL, '2025-10-27 08:01:47', '2025-10-27 14:27:01', NULL, 'pending'),
(13, 'APT-202510-44902', 6, 60, '2025-10-27', '11:25:00', 30, 'consultation', NULL, 0.00, NULL, 'Request for medication refills', '', 'completed', 55, NULL, NULL, 0, NULL, '2025-10-27 08:02:34', '2025-10-27 14:27:15', NULL, 'pending'),
(14, 'APT-202510-92691', 6, 69, '2026-01-15', '02:00:00', 30, 'consultation', NULL, 0.00, 'orthopedics', 'Experience severe migraines and runny nose', '', 'completed', 55, NULL, NULL, 0, NULL, '2025-10-30 12:24:49', '2026-01-15 12:18:14', NULL, 'pending'),
(15, 'APT-202510-72617', 8, 69, '2025-10-30', '17:45:00', 30, 'consultation', NULL, 0.00, 'orthopedics', 'Flu like symptoms', '', 'completed', 55, NULL, NULL, 0, NULL, '2025-10-30 14:43:24', '2025-10-30 17:21:47', NULL, 'pending'),
(16, 'APT20251030160731392', 11, 60, '2025-10-30', '18:25:00', 30, 'consultation', NULL, 0.00, NULL, 'Stomach pains and vomitting.', '', 'completed', NULL, NULL, NULL, 0, NULL, '2025-10-30 15:07:31', '2025-10-30 19:04:11', NULL, 'pending'),
(17, 'APT20251030162821554', 1, 59, '2025-10-30', '18:45:00', 30, 'consultation', NULL, 0.00, NULL, 'Medication refills.', '', 'completed', NULL, NULL, NULL, 0, NULL, '2025-10-30 15:28:21', '2025-10-30 17:09:06', NULL, 'pending'),
(18, 'APT-20251030-460', 7, 59, '2025-10-30', '22:15:00', 30, 'consultation', 17, 500.00, NULL, 'Experiencing joint pains', '', 'confirmed', 55, NULL, NULL, 0, NULL, '2025-10-30 19:07:15', '2025-10-30 19:07:33', NULL, 'pending'),
(19, 'APT-20251031-077', 10, 69, '2025-10-31', '20:30:00', 30, 'consultation', 17, 500.00, NULL, 'Joint pains and flu like symptoms.', 'Diagnosed with gout.', 'completed', 69, NULL, NULL, 0, NULL, '2025-10-31 17:34:39', '2026-01-19 21:37:21', NULL, 'paid'),
(20, 'APT-20251102-871', 12, 59, '2025-11-02', '14:00:00', 30, 'consultation', 17, 500.00, NULL, 'Experiencing joint and muscle pains. Shivers all over the body and feverish.', '', 'completed', 55, NULL, NULL, 0, NULL, '2025-11-02 10:52:45', '2026-01-19 21:37:21', NULL, 'paid'),
(21, 'APT-20251102-324', 11, 59, '2025-11-02', '21:40:00', 30, 'consultation', 3, 5000.00, NULL, 'puking blood, light headed', '', 'confirmed', 72, NULL, NULL, 0, NULL, '2025-11-02 18:38:00', '2025-11-02 18:38:48', NULL, 'pending'),
(22, 'APT-20251103-238', 2, 69, '2025-11-03', '23:50:00', 30, 'consultation', 17, 500.00, NULL, 'flu', '', 'confirmed', 55, NULL, NULL, 0, NULL, '2025-11-03 20:48:54', '2025-11-03 20:49:46', NULL, 'pending'),
(23, 'APT-20251104-096', 12, 69, '2025-11-04', '10:35:00', 30, 'consultation', 17, 500.00, NULL, 'flu and headaches.', '', 'completed', 63, NULL, NULL, 0, NULL, '2025-11-04 07:34:46', '2026-01-19 21:37:21', NULL, 'paid'),
(24, 'APT-20251106-396', 13, 69, '2025-11-06', '11:30:00', 30, 'consultation', 17, 500.00, NULL, 'stomach pains and headaches.', '', 'completed', 55, NULL, NULL, 0, NULL, '2025-11-06 08:27:39', '2026-01-19 21:37:21', NULL, 'paid'),
(25, 'APT-20251114-722', 14, 69, '2025-11-14', '10:00:00', 30, 'consultation', 17, 500.00, NULL, 'Light headed and bad headaches', '', 'completed', 56, NULL, NULL, 0, NULL, '2025-11-14 06:59:30', '2026-01-19 21:37:21', NULL, 'paid'),
(26, 'APT-20251117-919', 13, 69, '2025-11-17', '23:30:00', 30, '', 17, 500.00, NULL, 'flu like symptoms and migraines.', '', 'scheduled', 55, NULL, NULL, 0, NULL, '2025-11-17 20:31:12', NULL, NULL, 'pending'),
(27, 'APT-20251118-746', 5, 67, '2025-11-18', '12:10:00', 30, '', 17, 500.00, NULL, 'flu-like symptoms and extreme body aches', '', 'scheduled', 55, NULL, NULL, 0, NULL, '2025-11-18 09:05:32', NULL, NULL, 'pending'),
(28, 'APT-20260109-965', 8, 59, '2026-01-09', '11:50:00', 30, '', 17, 500.00, NULL, 'Experiencing severe stomach pain and nausea.', '', 'scheduled', 55, NULL, NULL, 0, NULL, '2026-01-09 08:49:42', NULL, NULL, 'pending'),
(29, 'APT-20260109-174', 6, 69, '2026-01-09', '23:50:00', 30, '', 17, 500.00, NULL, 'headaches', '', 'scheduled', 55, NULL, NULL, 0, NULL, '2026-01-09 20:51:08', NULL, NULL, 'pending'),
(30, 'APT-20260109-256', 14, 69, '2026-01-10', '00:20:00', 30, '', 3, 5000.00, NULL, 'Patient was brought unconscious experienced hypoglycemia.', '', 'scheduled', 69, NULL, NULL, 0, NULL, '2026-01-09 21:21:45', NULL, NULL, 'pending'),
(31, 'APT-20260113-677', 13, 69, '2026-01-14', '14:00:00', 30, 'consultation', NULL, 0.00, NULL, 'headaches, runny nose and coughing', 'Approved from request #1', 'scheduled', 55, NULL, NULL, 0, NULL, '2026-01-13 13:49:58', NULL, NULL, 'pending'),
(32, 'APT-20260113-902', 13, 69, '2026-01-14', '14:00:00', 30, 'consultation', NULL, 0.00, NULL, 'headaches, runny nose and coughing', 'Approved from request #1', 'cancelled', 55, 55, 'Cancelled by admin', 0, NULL, '2026-01-13 13:50:45', '2026-01-13 13:52:10', NULL, 'pending'),
(33, 'APT-20260115-171', 14, 60, '2026-01-15', '14:30:00', 30, 'consultation', 17, 500.00, NULL, 'allergies', '', 'confirmed', 63, NULL, NULL, 0, NULL, '2026-01-15 11:29:22', '2026-01-15 20:37:23', NULL, 'pending'),
(34, 'APT-20260115-105', 15, 69, '2026-01-15', '15:20:00', 30, '', 17, 500.00, NULL, 'headaches', '', 'scheduled', 55, NULL, NULL, 0, NULL, '2026-01-15 12:18:57', NULL, NULL, 'pending'),
(35, 'APT-20260115-332', 9, 67, '2026-01-15', '23:07:00', 30, '', 17, 500.00, NULL, 'flu', '', 'completed', 63, NULL, NULL, 0, NULL, '2026-01-15 20:07:15', '2026-01-19 21:37:21', NULL, 'paid'),
(36, 'APT-20260115-028', 1, 67, '2026-01-15', '23:58:00', 30, '', 17, 500.00, NULL, 'IBS', '', 'cancelled', 67, 67, 'Cancelled by admin', 0, NULL, '2026-01-15 20:58:37', '2026-01-15 20:59:37', NULL, 'pending'),
(37, 'APT-20260116-995', 12, 69, '2026-01-17', '10:30:00', 30, 'consultation', NULL, 0.00, NULL, 'vomiting and body aches.', 'Approved from request #6', 'completed', 55, NULL, NULL, 0, NULL, '2026-01-16 08:05:19', '2026-01-17 07:33:25', NULL, 'pending'),
(38, 'APT-20260116-180', 2, 69, '2026-01-16', '11:06:00', 30, 'consultation', 17, 500.00, NULL, 'Homa', '', 'completed', 55, NULL, NULL, 0, NULL, '2026-01-16 08:06:55', '2026-01-19 21:37:21', NULL, 'paid'),
(39, 'APT-20260116111345-488', 7, 60, '2026-01-16', '13:12:00', 30, '', 17, 500.00, NULL, 'Nausea, body aches, yellow eyes and diarrhea', '', 'completed', 60, NULL, NULL, 0, NULL, '2026-01-16 10:13:45', '2026-01-19 21:37:21', NULL, 'paid'),
(40, 'APT-20260116111521-330', 7, 60, '2026-01-16', '13:12:00', 30, '', 17, 500.00, NULL, 'Nausea, body aches, yellow eyes and diarrhea', '', 'cancelled', 60, 60, 'Cancelled by admin', 0, NULL, '2026-01-16 10:15:21', '2026-01-16 10:15:31', NULL, 'pending'),
(41, 'APT-20260116-671', 10, 59, '2026-01-16', '22:53:00', 30, '', 17, 500.00, NULL, 'headaches', '', 'scheduled', 55, NULL, NULL, 0, NULL, '2026-01-16 19:54:06', NULL, NULL, 'pending'),
(42, 'APT-20260117-577', 15, 67, '2026-01-17', '10:40:00', 30, '', 17, 500.00, NULL, 'Migraines', '', 'completed', 55, NULL, NULL, 0, NULL, '2026-01-17 07:39:43', '2026-01-19 21:37:21', NULL, 'paid'),
(43, 'APT-20260117-608', 1, 67, '2026-01-17', '11:26:00', 30, '', 3, 5000.00, NULL, 'Severe difficulty breathing\r\n\r\nWheezing\r\n\r\nChest tightness\r\n\r\nRapid breathing\r\n', '', 'completed', 56, NULL, NULL, 0, NULL, '2026-01-17 08:26:39', '2026-01-19 21:37:21', NULL, 'paid'),
(44, 'APT-20260117-453', 11, 59, '2026-01-17', '11:35:00', 30, '', 17, 500.00, NULL, 'Persistent vomiting (2 days)\r\n\r\nFever\r\n\r\nGeneral body weakness\r\n\r\nLoss of appetite', '', 'completed', 56, NULL, NULL, 0, NULL, '2026-01-17 08:33:31', '2026-01-19 21:37:21', NULL, 'paid'),
(45, 'APT-20260117111433-848', 15, 59, '2026-01-17', '13:15:00', 30, '', 17, 500.00, NULL, 'Exhaustion.', '', 'cancelled', 59, 56, 'Cancelled by admin', 0, NULL, '2026-01-17 10:14:33', '2026-01-17 10:16:02', NULL, 'pending'),
(46, 'APT-20260117-666', 15, 59, '2026-01-17', '13:15:00', 30, '', 17, 500.00, NULL, 'Unconscious patient', '', 'completed', 56, NULL, NULL, 0, NULL, '2026-01-17 10:15:48', '2026-01-19 21:37:21', NULL, 'paid'),
(47, 'APT-20260117-414', 5, 68, '2026-01-17', '13:25:00', 30, '', 17, 500.00, NULL, 'Vomiting and diarrhea', '', 'completed', 55, NULL, NULL, 0, NULL, '2026-01-17 10:24:52', '2026-01-19 21:37:21', NULL, 'paid'),
(48, 'APT-20260117114111-156', 2, 69, '2026-01-17', '13:40:00', 30, '', 2, 1200.00, NULL, 'Glucose treatment', '', 'completed', 69, NULL, NULL, 0, NULL, '2026-01-17 10:41:11', '2026-01-19 21:37:21', NULL, 'paid'),
(49, 'APT-20260117-494', 6, 69, '2026-01-17', '13:42:00', 30, '', 2, 1200.00, NULL, 'Glucose drip treatment', '', 'completed', 69, NULL, NULL, 0, NULL, '2026-01-17 10:42:28', '2026-01-19 21:37:21', NULL, 'paid'),
(50, 'APT-20260117-025', 8, 69, '2026-01-17', '16:16:00', 30, '', 2, 1200.00, NULL, 'Asthma attack', '', 'completed', 55, NULL, NULL, 0, NULL, '2026-01-17 13:16:32', '2026-01-19 21:37:21', NULL, 'paid'),
(51, 'APT-20260117-431', 14, 69, '2026-01-17', '19:06:00', 30, '', 17, 500.00, NULL, 'Difficulty breathing, shortness of breath and chest pains', '', 'completed', 55, NULL, NULL, 0, NULL, '2026-01-17 16:07:05', '2026-01-19 21:37:21', NULL, 'paid'),
(52, 'APT-20260118-133', 10, 59, '2026-01-18', '20:45:00', 30, '', 2, 1200.00, NULL, 'Consultation', '', 'completed', 55, NULL, NULL, 0, NULL, '2026-01-18 17:43:05', '2026-01-19 21:37:21', NULL, 'paid'),
(53, 'APT-20260119-013', 5, 67, '2026-01-19', '13:30:00', 30, '', 2, 1200.00, NULL, 'Asthma Attack', '', 'completed', 56, NULL, NULL, 0, NULL, '2026-01-19 10:29:12', '2026-01-19 21:37:21', NULL, 'paid'),
(54, 'APT-20260119-295', 12, 69, '2026-01-19', '13:30:00', 30, '', 17, 500.00, NULL, 'Body aches, feverish, vomiting and diarrhea', '', 'completed', 56, NULL, NULL, 0, NULL, '2026-01-19 10:29:58', '2026-01-19 21:37:21', NULL, 'paid'),
(55, 'APT-20260119-849', 2, 69, '2026-01-20', '00:06:00', 30, 'consultation', 17, 500.00, NULL, 'Flu', '', 'completed', 56, NULL, NULL, 0, NULL, '2026-01-19 21:03:36', '2026-01-19 21:37:21', NULL, 'paid'),
(56, 'APT-20260119-324', 2, 69, '2026-01-20', '00:05:00', 30, '', 17, 500.00, NULL, 'flu', '', 'cancelled', 69, 69, 'Cancelled by admin', 0, NULL, '2026-01-19 21:05:28', '2026-01-19 21:05:41', NULL, 'pending'),
(57, 'APT-20260119-136', 15, 67, '2026-01-20', '01:03:00', 30, '', 17, 500.00, NULL, 'migraines', '', 'completed', 67, NULL, NULL, 0, NULL, '2026-01-19 22:03:58', '2026-01-19 22:21:33', NULL, 'paid'),
(58, 'APT-20260119-024', 4, 59, '2026-01-20', '01:25:00', 30, 'consultation', 2, 1200.00, NULL, 'heart attack', '', 'completed', 59, NULL, NULL, 0, NULL, '2026-01-19 22:23:16', '2026-01-19 22:28:27', NULL, 'paid'),
(59, 'APT-20260120-967', 6, 61, '2026-01-20', '10:40:00', 30, '', 17, 500.00, NULL, 'homa', '', 'completed', 56, 63, 'Cancelled by admin', 0, NULL, '2026-01-20 07:35:17', '2026-01-20 09:56:34', NULL, 'paid'),
(60, 'APT-20260120-131', 11, 59, '2026-01-20', '10:35:00', 30, '', 2, 1200.00, NULL, 'Chest pains, known heart disease patient', '', 'cancelled', 56, 63, 'Cancelled by admin', 0, NULL, '2026-01-20 07:36:08', '2026-01-20 07:44:40', NULL, 'pending'),
(61, 'APT-20260120-527', 4, 68, '2026-01-20', '10:38:00', 30, '', 2, 1200.00, NULL, 'joint pains and difficulty walking', '', 'cancelled', 56, 63, 'Cancelled by admin', 0, NULL, '2026-01-20 07:36:42', '2026-01-20 07:44:40', NULL, 'pending'),
(62, 'APT-202601-00001', 6, 61, '2026-01-20', '10:45:00', 30, 'consultation', 17, 500.00, NULL, 'homa', '', 'completed', 63, NULL, NULL, 0, NULL, '2026-01-20 07:45:11', '2026-01-20 09:57:14', NULL, 'paid'),
(63, 'APT-202601-00001', 6, 61, '2026-01-20', '10:45:00', 30, 'consultation', 17, 500.00, NULL, 'homa', '', 'completed', 63, NULL, NULL, 0, NULL, '2026-01-20 07:45:40', '2026-01-20 09:58:39', NULL, 'paid'),
(64, 'APT-20260120-871', 4, 68, '2026-01-20', '10:50:00', 30, '', 2, 1200.00, NULL, 'joint pain and difficulty walking', '', 'scheduled', 55, NULL, NULL, 0, NULL, '2026-01-20 07:48:01', NULL, NULL, 'pending'),
(65, 'APT-20260120-838', 11, 59, '2026-01-21', '09:00:00', 30, 'consultation', 17, 500.00, NULL, 'Chest pains', '', 'scheduled', 55, NULL, NULL, 0, NULL, '2026-01-20 08:00:31', NULL, NULL, 'paid'),
(66, 'APT-20260120-414', 6, 61, '2026-01-20', '11:35:00', 30, '', 17, 500.00, NULL, 'homa', '', 'completed', 55, NULL, NULL, 0, NULL, '2026-01-20 08:32:51', '2026-01-20 09:58:06', NULL, 'paid'),
(124, 'APT-20260120-069', 0, 64, '2026-01-20', '13:10:00', 30, '', 17, 500.00, NULL, 'body aches, shivers, vomiting and diarrhea', '', 'scheduled', 55, NULL, NULL, 0, NULL, '2026-01-20 10:10:15', NULL, NULL, 'pending'),
(125, 'APT-20260120-993', 16, 64, '2026-01-20', '13:45:00', 30, '', 17, 500.00, NULL, 'body aches and shivering, headaches, vomiting and diarrhea', '', 'completed', 56, NULL, NULL, 0, NULL, '2026-01-20 10:42:24', '2026-01-20 10:49:23', NULL, 'paid'),
(126, 'APT-20260120-434', 31, 60, '2026-01-20', '14:00:00', 30, '', 17, 500.00, NULL, 'MIGRAINES AND IV TREATMENT', '', 'completed', 55, NULL, NULL, 0, NULL, '2026-01-20 10:58:52', '2026-01-20 12:38:37', NULL, 'paid'),
(127, 'APT-20260120-459', 15, 69, '2026-01-20', '16:10:00', 30, '', 17, 500.00, NULL, 'flu and low energy', '', 'completed', 56, NULL, NULL, 0, NULL, '2026-01-20 13:06:47', '2026-01-20 17:35:21', NULL, 'paid'),
(128, 'APT-20260120-046', 32, 60, '2026-01-20', '21:40:00', 30, '', 17, 500.00, NULL, 'migraines and allergies', '', 'completed', 55, NULL, NULL, 0, NULL, '2026-01-20 18:40:34', '2026-01-20 20:38:12', NULL, 'paid'),
(129, 'APT-20260120-447', 10, 67, '2026-01-20', '23:15:00', 30, '', 17, 500.00, NULL, 'headache', '', 'completed', 55, NULL, NULL, 0, NULL, '2026-01-20 20:13:58', '2026-01-20 20:36:54', NULL, 'paid'),
(130, 'APT-20260120-092', 16, 59, '2026-01-20', '23:45:00', 30, '', 17, 500.00, NULL, 'body aches, feverish, puking and vomiting for the past 2 days.', '', 'completed', 55, NULL, NULL, 0, NULL, '2026-01-20 20:44:25', '2026-01-20 21:12:08', NULL, 'paid'),
(131, 'APT-20260121-771', 7, 60, '2026-01-21', '11:45:00', 30, '', 17, 500.00, NULL, 'blood pressure issues and refill request', '', 'completed', 55, NULL, NULL, 0, NULL, '2026-01-21 08:36:52', '2026-01-21 09:34:48', NULL, 'paid'),
(132, 'APT-20260121-466', 33, 61, '2026-01-21', '14:50:00', 30, '', 17, 500.00, NULL, 'asthma attack', '', 'completed', 55, NULL, NULL, 0, NULL, '2026-01-21 11:46:38', '2026-01-21 12:23:50', NULL, 'paid'),
(133, 'APT-20260122-143', 9, 67, '2026-01-22', '23:00:00', 30, '', 17, 500.00, NULL, 'feverish, body aches and weakness, shivers, vomiting and diarrhea for the past two days', '', 'completed', 55, NULL, NULL, 0, NULL, '2026-01-22 19:50:25', '2026-01-22 19:55:12', NULL, 'paid'),
(134, 'APT-20260205-846', 15, 61, '2026-02-05', '16:20:00', 30, 'consultation', NULL, 500.00, NULL, 'Joint pains and swollen feet', 'Approved from request #0', 'completed', 55, NULL, NULL, 0, NULL, '2026-02-05 13:12:07', '2026-02-05 18:42:56', NULL, 'paid'),
(135, 'APT-20260205-844', 12, 68, '2026-02-05', '19:30:00', 30, 'consultation', NULL, 0.00, NULL, 'Feverish and migraines', 'Approved from request #0', 'cancelled', 55, NULL, NULL, 0, NULL, '2026-02-05 14:51:34', '2026-02-05 15:15:53', NULL, 'pending'),
(136, 'APT-20260205-488', 12, 68, '2026-02-05', '19:45:00', 30, 'consultation', NULL, 0.00, NULL, 'Feverish and migraines', 'Approved from request #0', 'cancelled', 55, NULL, NULL, 0, NULL, '2026-02-05 14:58:34', '2026-02-05 17:14:30', NULL, 'pending'),
(137, 'APT-20260205-171', 15, 68, '2026-02-05', '20:45:00', 30, 'consultation', NULL, 0.00, NULL, 'Joint pains and difficulties walking. Legs have also swollen', 'Approved from request #0', 'completed', 55, NULL, NULL, 0, NULL, '2026-02-05 17:28:19', '2026-02-05 18:22:12', NULL, 'pending'),
(138, 'APT-20260205-972', 14, 61, '2026-02-05', '21:45:00', 30, 'consultation', NULL, 0.00, NULL, 'Chest pains and body aches.', 'Approved from request #18', 'completed', 55, NULL, NULL, 0, NULL, '2026-02-05 18:31:33', '2026-02-05 18:45:35', NULL, 'pending'),
(139, 'APT-20260206-096', 33, 61, '2026-02-07', '11:40:00', 30, 'consultation', 17, 500.00, NULL, 'Flu', '', 'cancelled', 55, 56, 'Cancelled by admin', 0, NULL, '2026-02-06 08:37:13', '2026-02-06 10:38:12', NULL, 'pending'),
(140, 'APT-20260206-955', 32, 59, '2026-02-06', '12:00:00', 30, '', 17, 500.00, NULL, 'Stomach pains, vomiting and diarrhea', '', 'completed', 56, NULL, NULL, 0, NULL, '2026-02-06 08:41:32', '2026-02-06 08:55:04', NULL, 'paid'),
(141, 'APT-20260206-522', 31, 67, '2026-02-07', '13:40:00', 30, '', 17, 500.00, NULL, 'feverish and migraines', '', 'cancelled', 56, 56, 'Cancelled by admin', 0, NULL, '2026-02-06 10:36:21', '2026-02-06 10:38:17', NULL, 'pending'),
(142, 'APT-20260206-226', 16, 69, '2026-02-07', '14:00:00', 30, '', 17, 500.00, NULL, 'stomach issues', '', 'cancelled', 56, 56, 'Cancelled by admin', 0, NULL, '2026-02-06 10:47:19', '2026-02-06 10:48:27', NULL, 'pending'),
(143, 'APT-20260206-923', 31, 67, '2026-02-06', '14:00:00', 30, '', 17, 500.00, NULL, 'flu and chest pains', '', 'completed', 56, NULL, NULL, 0, NULL, '2026-02-06 10:49:12', '2026-02-06 10:52:57', NULL, 'paid'),
(144, 'APT-20260206-701', 13, 68, '2026-02-06', '14:15:00', 30, 'consultation', NULL, 0.00, NULL, 'Joint pains and swollen feet', 'Approved from request #19', 'completed', 56, NULL, NULL, 0, NULL, '2026-02-06 10:58:09', '2026-02-06 11:02:39', NULL, 'pending'),
(146, 'APT-20260206-9542', 10, 69, '2026-02-06', '15:00:00', 30, 'consultation', 17, 500.00, NULL, 'Regular checkup', 'Approved from request #20', 'completed', 55, NULL, NULL, 0, NULL, '2026-02-06 11:50:41', '2026-02-06 11:56:08', NULL, 'paid');

--
-- Triggers `appointments`
--
DELIMITER $$
CREATE TRIGGER `generate_appointment_number` BEFORE INSERT ON `appointments` FOR EACH ROW BEGIN

    IF NEW.appointment_number IS NULL THEN

        SET NEW.appointment_number = CONCAT('APT-', DATE_FORMAT(NOW(), '%Y%m'), '-', LPAD(LAST_INSERT_ID() + 1, 5, '0'));

    END IF;

END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `log_appointment_creation` AFTER INSERT ON `appointments` FOR EACH ROW BEGIN
    INSERT INTO audit_logs (user_id, action, description, timestamp)
    VALUES (@current_user_id, 'create', CONCAT('Created appointment: ', NEW.appointment_number), NOW());
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `appointments_backup`
--

CREATE TABLE `appointments_backup` (
  `id` int(10) UNSIGNED NOT NULL,
  `appointment_number` varchar(50) DEFAULT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `doctor_id` int(10) UNSIGNED NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `duration` int(11) DEFAULT 30 COMMENT 'Duration in minutes',
  `appointment_type` enum('consultation','followup','emergency','procedure','checkup') DEFAULT 'consultation',
  `service_id` int(10) UNSIGNED DEFAULT NULL,
  `consultation_fee` decimal(10,2) DEFAULT 0.00,
  `department` varchar(100) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('scheduled','confirmed','in_progress','completed','cancelled','no_show','rescheduled') DEFAULT 'scheduled',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `cancelled_by` int(10) UNSIGNED DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `reminder_sent` tinyint(1) DEFAULT 0,
  `reminder_sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `payment_status` enum('pending','paid','partial') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments_backup`
--

INSERT INTO `appointments_backup` (`id`, `appointment_number`, `patient_id`, `doctor_id`, `appointment_date`, `appointment_time`, `duration`, `appointment_type`, `service_id`, `consultation_fee`, `department`, `reason`, `notes`, `status`, `created_by`, `cancelled_by`, `cancellation_reason`, `reminder_sent`, `reminder_sent_at`, `created_at`, `updated_at`, `completed_at`, `payment_status`) VALUES
(1, 'APT-202510-65633', 6, 61, '2025-10-07', '13:30:00', 45, 'checkup', NULL, 0.00, NULL, 'Has a high fever and difficulty breathing.', 'Travelled to Rwanda last month.', 'scheduled', 55, NULL, NULL, 0, NULL, '2025-10-01 21:48:10', '2025-10-01 21:53:40', NULL, 'pending'),
(2, 'APT-202510-69923', 2, 59, '2025-10-12', '14:00:00', 90, 'procedure', NULL, 0.00, NULL, 'Heart surgery coming up. Appointment is to discuss preparations.', '', 'confirmed', 55, NULL, NULL, 0, NULL, '2025-10-01 21:53:12', '2025-10-05 18:29:53', NULL, 'pending'),
(3, 'APT-202510-68602', 6, 61, '2025-10-13', '13:30:00', 30, 'followup', NULL, 0.00, NULL, 'Follow up on previous diagnosis and review on how the medications have reacted.', 'Has developed rashes', 'cancelled', 55, NULL, NULL, 0, NULL, '2025-10-04 09:04:09', '2025-10-06 12:08:33', NULL, 'pending'),
(4, 'APT-202510-29030', 6, 60, '2025-10-05', '23:00:00', 30, 'emergency', NULL, 0.00, NULL, 'Head injury, coupled with confusion and numbness on the right side of her face.', 'A post fell on her while walking near a construction site.', 'in_progress', 65, NULL, NULL, 0, NULL, '2025-10-05 18:35:00', '2025-10-05 18:41:49', NULL, 'pending'),
(5, 'APT-202510-91748', 6, 59, '2025-10-06', '15:05:00', 30, 'consultation', NULL, 0.00, NULL, 'Chest tightness and Nausea.', '', 'completed', 55, NULL, NULL, 0, NULL, '2025-10-06 11:05:27', '2025-10-06 12:08:22', NULL, 'pending'),
(6, 'APT-202510-39302', 8, 64, '2025-10-07', '07:40:00', 30, 'consultation', NULL, 0.00, 'neurology', 'Developed Rashes and mild fever.', '', 'scheduled', 55, NULL, NULL, 0, NULL, '2025-10-06 16:35:06', NULL, NULL, 'pending'),
(8, 'APT-202510-27679', 6, 64, '2025-10-19', '10:15:00', 30, 'consultation', NULL, 0.00, 'neurology', 'Splitting headache, and complains of stomach ache', '', 'completed', 55, NULL, NULL, 0, NULL, '2025-10-18 19:14:45', '2025-10-19 14:40:30', NULL, 'pending'),
(9, 'APT-202510-98287', 6, 67, '2025-10-28', '10:45:00', 45, 'consultation', NULL, 0.00, 'pediatrics', 'Consultation', '', 'confirmed', 55, NULL, NULL, 0, NULL, '2025-10-26 12:53:31', '2025-10-26 19:22:22', NULL, 'pending'),
(10, 'APT-202510-83492', 6, 64, '2025-10-27', '11:30:00', 60, 'procedure', NULL, 0.00, 'neurology', 'Minor surgery', '', 'completed', 55, 55, 'Cancelled by admin', 0, NULL, '2025-10-26 19:23:36', '2025-10-27 14:26:28', NULL, 'pending'),
(11, 'APT-202510-00747', 6, 68, '2025-10-28', '11:30:00', 30, 'consultation', NULL, 0.00, 'orthopedics', 'Complains of joint pains and knee swelling.', 'Diagnosed with arthritis.', 'confirmed', 55, NULL, NULL, 0, NULL, '2025-10-27 07:55:21', '2025-10-27 07:55:55', NULL, 'pending'),
(12, 'APT-202510-15827', 6, 59, '2025-10-27', '12:00:00', 30, 'consultation', NULL, 0.00, NULL, 'Experiencing stomach pain and extreme nausea.', '', 'completed', 55, NULL, NULL, 0, NULL, '2025-10-27 08:01:47', '2025-10-27 14:27:01', NULL, 'pending'),
(13, 'APT-202510-44902', 6, 60, '2025-10-27', '11:25:00', 30, 'consultation', NULL, 0.00, NULL, 'Request for medication refills', '', 'completed', 55, NULL, NULL, 0, NULL, '2025-10-27 08:02:34', '2025-10-27 14:27:15', NULL, 'pending'),
(14, 'APT-202510-92691', 6, 69, '2026-01-15', '02:00:00', 30, 'consultation', NULL, 0.00, 'orthopedics', 'Experience severe migraines and runny nose', '', 'completed', 55, NULL, NULL, 0, NULL, '2025-10-30 12:24:49', '2026-01-15 12:18:14', NULL, 'pending'),
(15, 'APT-202510-72617', 8, 69, '2025-10-30', '17:45:00', 30, 'consultation', NULL, 0.00, 'orthopedics', 'Flu like symptoms', '', 'completed', 55, NULL, NULL, 0, NULL, '2025-10-30 14:43:24', '2025-10-30 17:21:47', NULL, 'pending'),
(16, 'APT20251030160731392', 11, 60, '2025-10-30', '18:25:00', 30, 'consultation', NULL, 0.00, NULL, 'Stomach pains and vomitting.', '', 'completed', NULL, NULL, NULL, 0, NULL, '2025-10-30 15:07:31', '2025-10-30 19:04:11', NULL, 'pending'),
(17, 'APT20251030162821554', 1, 59, '2025-10-30', '18:45:00', 30, 'consultation', NULL, 0.00, NULL, 'Medication refills.', '', 'completed', NULL, NULL, NULL, 0, NULL, '2025-10-30 15:28:21', '2025-10-30 17:09:06', NULL, 'pending'),
(18, 'APT-20251030-460', 7, 59, '2025-10-30', '22:15:00', 30, 'consultation', 17, 500.00, NULL, 'Experiencing joint pains', '', 'confirmed', 55, NULL, NULL, 0, NULL, '2025-10-30 19:07:15', '2025-10-30 19:07:33', NULL, 'pending'),
(19, 'APT-20251031-077', 10, 69, '2025-10-31', '20:30:00', 30, 'consultation', 17, 500.00, NULL, 'Joint pains and flu like symptoms.', 'Diagnosed with gout.', 'completed', 69, NULL, NULL, 0, NULL, '2025-10-31 17:34:39', '2026-01-19 21:37:21', NULL, 'paid'),
(20, 'APT-20251102-871', 12, 59, '2025-11-02', '14:00:00', 30, 'consultation', 17, 500.00, NULL, 'Experiencing joint and muscle pains. Shivers all over the body and feverish.', '', 'completed', 55, NULL, NULL, 0, NULL, '2025-11-02 10:52:45', '2026-01-19 21:37:21', NULL, 'paid'),
(21, 'APT-20251102-324', 11, 59, '2025-11-02', '21:40:00', 30, 'consultation', 3, 5000.00, NULL, 'puking blood, light headed', '', 'confirmed', 72, NULL, NULL, 0, NULL, '2025-11-02 18:38:00', '2025-11-02 18:38:48', NULL, 'pending'),
(22, 'APT-20251103-238', 2, 69, '2025-11-03', '23:50:00', 30, 'consultation', 17, 500.00, NULL, 'flu', '', 'confirmed', 55, NULL, NULL, 0, NULL, '2025-11-03 20:48:54', '2025-11-03 20:49:46', NULL, 'pending'),
(23, 'APT-20251104-096', 12, 69, '2025-11-04', '10:35:00', 30, 'consultation', 17, 500.00, NULL, 'flu and headaches.', '', 'completed', 63, NULL, NULL, 0, NULL, '2025-11-04 07:34:46', '2026-01-19 21:37:21', NULL, 'paid'),
(24, 'APT-20251106-396', 13, 69, '2025-11-06', '11:30:00', 30, 'consultation', 17, 500.00, NULL, 'stomach pains and headaches.', '', 'completed', 55, NULL, NULL, 0, NULL, '2025-11-06 08:27:39', '2026-01-19 21:37:21', NULL, 'paid'),
(25, 'APT-20251114-722', 14, 69, '2025-11-14', '10:00:00', 30, 'consultation', 17, 500.00, NULL, 'Light headed and bad headaches', '', 'completed', 56, NULL, NULL, 0, NULL, '2025-11-14 06:59:30', '2026-01-19 21:37:21', NULL, 'paid'),
(26, 'APT-20251117-919', 13, 69, '2025-11-17', '23:30:00', 30, '', 17, 500.00, NULL, 'flu like symptoms and migraines.', '', 'scheduled', 55, NULL, NULL, 0, NULL, '2025-11-17 20:31:12', NULL, NULL, 'pending'),
(27, 'APT-20251118-746', 5, 67, '2025-11-18', '12:10:00', 30, '', 17, 500.00, NULL, 'flu-like symptoms and extreme body aches', '', 'scheduled', 55, NULL, NULL, 0, NULL, '2025-11-18 09:05:32', NULL, NULL, 'pending'),
(28, 'APT-20260109-965', 8, 59, '2026-01-09', '11:50:00', 30, '', 17, 500.00, NULL, 'Experiencing severe stomach pain and nausea.', '', 'scheduled', 55, NULL, NULL, 0, NULL, '2026-01-09 08:49:42', NULL, NULL, 'pending'),
(29, 'APT-20260109-174', 6, 69, '2026-01-09', '23:50:00', 30, '', 17, 500.00, NULL, 'headaches', '', 'scheduled', 55, NULL, NULL, 0, NULL, '2026-01-09 20:51:08', NULL, NULL, 'pending'),
(30, 'APT-20260109-256', 14, 69, '2026-01-10', '00:20:00', 30, '', 3, 5000.00, NULL, 'Patient was brought unconscious experienced hypoglycemia.', '', 'scheduled', 69, NULL, NULL, 0, NULL, '2026-01-09 21:21:45', NULL, NULL, 'pending'),
(31, 'APT-20260113-677', 13, 69, '2026-01-14', '14:00:00', 30, 'consultation', NULL, 0.00, NULL, 'headaches, runny nose and coughing', 'Approved from request #1', 'scheduled', 55, NULL, NULL, 0, NULL, '2026-01-13 13:49:58', NULL, NULL, 'pending'),
(32, 'APT-20260113-902', 13, 69, '2026-01-14', '14:00:00', 30, 'consultation', NULL, 0.00, NULL, 'headaches, runny nose and coughing', 'Approved from request #1', 'cancelled', 55, 55, 'Cancelled by admin', 0, NULL, '2026-01-13 13:50:45', '2026-01-13 13:52:10', NULL, 'pending'),
(33, 'APT-20260115-171', 14, 60, '2026-01-15', '14:30:00', 30, 'consultation', 17, 500.00, NULL, 'allergies', '', 'confirmed', 63, NULL, NULL, 0, NULL, '2026-01-15 11:29:22', '2026-01-15 20:37:23', NULL, 'pending'),
(34, 'APT-20260115-105', 15, 69, '2026-01-15', '15:20:00', 30, '', 17, 500.00, NULL, 'headaches', '', 'scheduled', 55, NULL, NULL, 0, NULL, '2026-01-15 12:18:57', NULL, NULL, 'pending'),
(35, 'APT-20260115-332', 9, 67, '2026-01-15', '23:07:00', 30, '', 17, 500.00, NULL, 'flu', '', 'completed', 63, NULL, NULL, 0, NULL, '2026-01-15 20:07:15', '2026-01-19 21:37:21', NULL, 'paid'),
(36, 'APT-20260115-028', 1, 67, '2026-01-15', '23:58:00', 30, '', 17, 500.00, NULL, 'IBS', '', 'cancelled', 67, 67, 'Cancelled by admin', 0, NULL, '2026-01-15 20:58:37', '2026-01-15 20:59:37', NULL, 'pending'),
(37, 'APT-20260116-995', 12, 69, '2026-01-17', '10:30:00', 30, 'consultation', NULL, 0.00, NULL, 'vomiting and body aches.', 'Approved from request #6', 'completed', 55, NULL, NULL, 0, NULL, '2026-01-16 08:05:19', '2026-01-17 07:33:25', NULL, 'pending'),
(38, 'APT-20260116-180', 2, 69, '2026-01-16', '11:06:00', 30, 'consultation', 17, 500.00, NULL, 'Homa', '', 'completed', 55, NULL, NULL, 0, NULL, '2026-01-16 08:06:55', '2026-01-19 21:37:21', NULL, 'paid'),
(39, 'APT-20260116111345-488', 7, 60, '2026-01-16', '13:12:00', 30, '', 17, 500.00, NULL, 'Nausea, body aches, yellow eyes and diarrhea', '', 'completed', 60, NULL, NULL, 0, NULL, '2026-01-16 10:13:45', '2026-01-19 21:37:21', NULL, 'paid'),
(40, 'APT-20260116111521-330', 7, 60, '2026-01-16', '13:12:00', 30, '', 17, 500.00, NULL, 'Nausea, body aches, yellow eyes and diarrhea', '', 'cancelled', 60, 60, 'Cancelled by admin', 0, NULL, '2026-01-16 10:15:21', '2026-01-16 10:15:31', NULL, 'pending'),
(41, 'APT-20260116-671', 10, 59, '2026-01-16', '22:53:00', 30, '', 17, 500.00, NULL, 'headaches', '', 'scheduled', 55, NULL, NULL, 0, NULL, '2026-01-16 19:54:06', NULL, NULL, 'pending'),
(42, 'APT-20260117-577', 15, 67, '2026-01-17', '10:40:00', 30, '', 17, 500.00, NULL, 'Migraines', '', 'completed', 55, NULL, NULL, 0, NULL, '2026-01-17 07:39:43', '2026-01-19 21:37:21', NULL, 'paid'),
(43, 'APT-20260117-608', 1, 67, '2026-01-17', '11:26:00', 30, '', 3, 5000.00, NULL, 'Severe difficulty breathing\r\n\r\nWheezing\r\n\r\nChest tightness\r\n\r\nRapid breathing\r\n', '', 'completed', 56, NULL, NULL, 0, NULL, '2026-01-17 08:26:39', '2026-01-19 21:37:21', NULL, 'paid'),
(44, 'APT-20260117-453', 11, 59, '2026-01-17', '11:35:00', 30, '', 17, 500.00, NULL, 'Persistent vomiting (2 days)\r\n\r\nFever\r\n\r\nGeneral body weakness\r\n\r\nLoss of appetite', '', 'completed', 56, NULL, NULL, 0, NULL, '2026-01-17 08:33:31', '2026-01-19 21:37:21', NULL, 'paid'),
(45, 'APT-20260117111433-848', 15, 59, '2026-01-17', '13:15:00', 30, '', 17, 500.00, NULL, 'Exhaustion.', '', 'cancelled', 59, 56, 'Cancelled by admin', 0, NULL, '2026-01-17 10:14:33', '2026-01-17 10:16:02', NULL, 'pending'),
(46, 'APT-20260117-666', 15, 59, '2026-01-17', '13:15:00', 30, '', 17, 500.00, NULL, 'Unconscious patient', '', 'completed', 56, NULL, NULL, 0, NULL, '2026-01-17 10:15:48', '2026-01-19 21:37:21', NULL, 'paid'),
(47, 'APT-20260117-414', 5, 68, '2026-01-17', '13:25:00', 30, '', 17, 500.00, NULL, 'Vomiting and diarrhea', '', 'completed', 55, NULL, NULL, 0, NULL, '2026-01-17 10:24:52', '2026-01-19 21:37:21', NULL, 'paid'),
(48, 'APT-20260117114111-156', 2, 69, '2026-01-17', '13:40:00', 30, '', 2, 1200.00, NULL, 'Glucose treatment', '', 'completed', 69, NULL, NULL, 0, NULL, '2026-01-17 10:41:11', '2026-01-19 21:37:21', NULL, 'paid'),
(49, 'APT-20260117-494', 6, 69, '2026-01-17', '13:42:00', 30, '', 2, 1200.00, NULL, 'Glucose drip treatment', '', 'completed', 69, NULL, NULL, 0, NULL, '2026-01-17 10:42:28', '2026-01-19 21:37:21', NULL, 'paid'),
(50, 'APT-20260117-025', 8, 69, '2026-01-17', '16:16:00', 30, '', 2, 1200.00, NULL, 'Asthma attack', '', 'completed', 55, NULL, NULL, 0, NULL, '2026-01-17 13:16:32', '2026-01-19 21:37:21', NULL, 'paid'),
(51, 'APT-20260117-431', 14, 69, '2026-01-17', '19:06:00', 30, '', 17, 500.00, NULL, 'Difficulty breathing, shortness of breath and chest pains', '', 'completed', 55, NULL, NULL, 0, NULL, '2026-01-17 16:07:05', '2026-01-19 21:37:21', NULL, 'paid'),
(52, 'APT-20260118-133', 10, 59, '2026-01-18', '20:45:00', 30, '', 2, 1200.00, NULL, 'Consultation', '', 'completed', 55, NULL, NULL, 0, NULL, '2026-01-18 17:43:05', '2026-01-19 21:37:21', NULL, 'paid'),
(53, 'APT-20260119-013', 5, 67, '2026-01-19', '13:30:00', 30, '', 2, 1200.00, NULL, 'Asthma Attack', '', 'completed', 56, NULL, NULL, 0, NULL, '2026-01-19 10:29:12', '2026-01-19 21:37:21', NULL, 'paid'),
(54, 'APT-20260119-295', 12, 69, '2026-01-19', '13:30:00', 30, '', 17, 500.00, NULL, 'Body aches, feverish, vomiting and diarrhea', '', 'completed', 56, NULL, NULL, 0, NULL, '2026-01-19 10:29:58', '2026-01-19 21:37:21', NULL, 'paid'),
(55, 'APT-20260119-849', 2, 69, '2026-01-20', '00:06:00', 30, 'consultation', 17, 500.00, NULL, 'Flu', '', 'completed', 56, NULL, NULL, 0, NULL, '2026-01-19 21:03:36', '2026-01-19 21:37:21', NULL, 'paid'),
(56, 'APT-20260119-324', 2, 69, '2026-01-20', '00:05:00', 30, '', 17, 500.00, NULL, 'flu', '', 'cancelled', 69, 69, 'Cancelled by admin', 0, NULL, '2026-01-19 21:05:28', '2026-01-19 21:05:41', NULL, 'pending'),
(57, 'APT-20260119-136', 15, 67, '2026-01-20', '01:03:00', 30, '', 17, 500.00, NULL, 'migraines', '', 'completed', 67, NULL, NULL, 0, NULL, '2026-01-19 22:03:58', '2026-01-19 22:21:33', NULL, 'paid'),
(58, 'APT-20260119-024', 4, 59, '2026-01-20', '01:25:00', 30, 'consultation', 2, 1200.00, NULL, 'heart attack', '', 'completed', 59, NULL, NULL, 0, NULL, '2026-01-19 22:23:16', '2026-01-19 22:28:27', NULL, 'paid'),
(0, 'APT-20260120-967', 6, 61, '2026-01-20', '10:40:00', 30, '', 17, 500.00, NULL, 'homa', '', 'completed', 56, 63, 'Cancelled by admin', 0, NULL, '2026-01-20 07:35:17', '2026-01-20 08:35:38', NULL, 'pending'),
(0, 'APT-20260120-131', 11, 59, '2026-01-20', '10:35:00', 30, '', 2, 1200.00, NULL, 'Chest pains, known heart disease patient', '', 'cancelled', 56, 63, 'Cancelled by admin', 0, NULL, '2026-01-20 07:36:08', '2026-01-20 07:44:40', NULL, 'pending'),
(0, 'APT-20260120-527', 4, 68, '2026-01-20', '10:38:00', 30, '', 2, 1200.00, NULL, 'joint pains and difficulty walking', '', 'cancelled', 56, 63, 'Cancelled by admin', 0, NULL, '2026-01-20 07:36:42', '2026-01-20 07:44:40', NULL, 'pending'),
(0, 'APT-202601-00001', 6, 61, '2026-01-20', '10:45:00', 30, 'consultation', 17, 500.00, NULL, 'homa', '', 'completed', 63, NULL, NULL, 0, NULL, '2026-01-20 07:45:11', '2026-01-20 08:35:38', NULL, 'pending'),
(0, 'APT-202601-00001', 6, 61, '2026-01-20', '10:45:00', 30, 'consultation', 17, 500.00, NULL, 'homa', '', 'completed', 63, NULL, NULL, 0, NULL, '2026-01-20 07:45:40', '2026-01-20 08:35:38', NULL, 'pending'),
(0, 'APT-20260120-871', 4, 68, '2026-01-20', '10:50:00', 30, '', 2, 1200.00, NULL, 'joint pain and difficulty walking', '', 'scheduled', 55, NULL, NULL, 0, NULL, '2026-01-20 07:48:01', NULL, NULL, 'pending'),
(0, 'APT-20260120-838', 11, 59, '2026-01-21', '09:00:00', 30, 'consultation', 17, 500.00, NULL, 'Chest pains', '', 'scheduled', 55, NULL, NULL, 0, NULL, '2026-01-20 08:00:31', NULL, NULL, 'pending'),
(0, 'APT-20260120-414', 6, 61, '2026-01-20', '11:35:00', 30, '', 17, 500.00, NULL, 'homa', '', 'completed', 55, NULL, NULL, 0, NULL, '2026-01-20 08:32:51', '2026-01-20 08:35:38', NULL, 'pending');

-- --------------------------------------------------------


--
-- Table structure for table `appointment_requests`
--

CREATE TABLE `appointment_requests` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `preferred_date` date NOT NULL,
  `preferred_time` enum('Morning','Afternoon','Evening') NOT NULL,
  `type` varchar(100) NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `consultation_fee` decimal(10,2) DEFAULT 0.00,
  `urgency` enum('High','Medium','Normal') DEFAULT 'Normal',
  `notes` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointment_requests`
--

INSERT INTO `appointment_requests` (`id`, `patient_id`, `doctor_id`, `preferred_date`, `preferred_time`, `type`, `service_id`, `consultation_fee`, `urgency`, `notes`, `status`, `rejection_reason`, `processed_by`, `processed_at`, `created_at`, `updated_at`) VALUES
(1, 13, 69, '2026-01-15', '', 'consultation', NULL, 0.00, 'Normal', 'headaches, runny nose and coughing', 'approved', NULL, 55, '2026-01-13 13:50:45', '2026-01-13 13:14:44', '2026-01-14 17:46:40'),
(3, 12, 69, '2026-01-17', 'Afternoon', 'consultation', NULL, 0.00, 'Normal', 'Body aches, shivers, headache and vomitng', '', 'Cancelled by patient', NULL, '2026-01-16 08:00:50', '2026-01-16 07:57:12', '2026-01-16 08:00:50'),
(4, 12, 69, '2026-01-17', 'Afternoon', 'consultation', NULL, 0.00, 'Normal', 'Body aches, shivers, headache and vomitng', '', 'Cancelled by patient', NULL, '2026-01-16 07:59:51', '2026-01-16 07:58:17', '2026-01-16 07:59:51'),
(5, 12, 69, '2026-01-17', 'Afternoon', 'consultation', NULL, 0.00, 'Normal', 'Body aches, shivers, headache and vomitng', '', 'Cancelled by patient', NULL, '2026-01-16 07:59:11', '2026-01-16 07:58:54', '2026-01-16 07:59:11'),
(6, 12, 69, '2026-01-17', 'Evening', 'consultation', NULL, 0.00, 'Normal', 'vomiting and body aches.', 'approved', NULL, 55, '2026-01-16 08:05:19', '2026-01-16 08:02:50', '2026-01-16 08:05:19'),
(7, 13, NULL, '2026-01-20', 'Morning', 'consultation', NULL, 0.00, 'Normal', 'Been having homa for the past 5 days, runny nose, coughing and headaches.', 'approved', NULL, 56, '2026-01-19 10:38:01', '2026-01-19 10:35:43', '2026-01-19 10:38:01'),
(8, 12, 61, '2026-02-05', '', 'consultation', NULL, 0.00, '', 'Feverish and migraines', 'approved', NULL, 55, '2026-02-05 17:28:19', '2026-02-05 13:00:12', '2026-02-05 17:28:19'),
(9, 15, 68, '2026-02-05', '', 'consultation', NULL, 0.00, '', 'Joint pains and mobility issues....my hands and feet are also swollen', 'approved', NULL, 55, '2026-02-05 17:28:19', '2026-02-05 14:51:00', '2026-02-05 17:28:19'),
(10, 13, 68, '2026-02-05', '', 'consultation', NULL, 0.00, '', 'Extreme join pains, especially on my legs...I can not walk properly and they are swollen', 'approved', NULL, 55, '2026-02-05 17:28:19', '2026-02-05 14:56:43', '2026-02-05 17:28:19'),
(11, 15, 68, '2026-02-05', '', 'consultation', NULL, 0.00, '', 'Joint pains and difficulties walking. Legs have also swollen', 'approved', NULL, 55, '2026-02-05 17:28:19', '2026-02-05 17:27:35', '2026-02-05 17:28:19'),
(18, 14, 61, '2026-02-05', '', 'consultation', NULL, 0.00, '', 'Chest pains and body aches.', 'approved', NULL, 55, '2026-02-05 18:31:33', '2026-02-05 18:31:07', '2026-02-05 18:31:33'),
(19, 13, 68, '2026-02-06', '', 'consultation', NULL, 500.00, '', 'Joint pains and swollen feet', 'approved', NULL, 56, '2026-02-06 10:58:09', '2026-02-06 10:57:09', '2026-02-06 11:26:20'),
(20, 10, 69, '2026-02-06', '', 'consultation', 17, 500.00, '', 'Regular checkup', 'approved', NULL, 55, '2026-02-06 11:50:41', '2026-02-06 11:28:36', '2026-02-06 11:50:41');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `timestamp` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `timestamp`) VALUES
(1, NULL, 'create', 'Created patient: Emmanuel Maothe', NULL, NULL, '2025-11-14 09:58:27'),
(2, NULL, 'create', 'Created appointment: APT-20251114-722', NULL, NULL, '2025-11-14 09:59:30'),
(3, NULL, 'create', 'Created appointment: APT-20251117-919', NULL, NULL, '2025-11-17 23:31:12'),
(4, NULL, 'create', 'Created appointment: APT-20251118-746', NULL, NULL, '2025-11-18 12:05:32'),
(5, NULL, 'create', 'Created appointment: APT-20260109-965', NULL, NULL, '2026-01-09 11:49:42'),
(6, NULL, 'create', 'Created appointment: APT-20260109-174', NULL, NULL, '2026-01-09 23:51:08'),
(7, NULL, 'create', 'Created appointment: APT-20260109-256', NULL, NULL, '2026-01-10 00:21:45'),
(8, NULL, 'create', 'Created patient: Ria Kumari', NULL, NULL, '2026-01-11 22:14:08'),
(9, NULL, 'create', 'Created appointment: APT-20260113-677', NULL, NULL, '2026-01-13 16:49:58'),
(10, NULL, 'create', 'Created appointment: APT-20260113-902', NULL, NULL, '2026-01-13 16:50:45'),
(11, NULL, 'create', 'Created appointment: APT-20260115-171', NULL, NULL, '2026-01-15 14:29:22'),
(12, NULL, 'create', 'Created appointment: APT-20260115-105', NULL, NULL, '2026-01-15 15:18:57'),
(13, NULL, 'create', 'Created appointment: APT-20260115-332', NULL, NULL, '2026-01-15 23:07:15'),
(14, NULL, 'create', 'Created appointment: APT-20260115-028', NULL, NULL, '2026-01-15 23:58:37'),
(15, NULL, 'create', 'Created appointment: APT-20260116-995', NULL, NULL, '2026-01-16 11:05:19'),
(16, NULL, 'create', 'Created appointment: APT-20260116-180', NULL, NULL, '2026-01-16 11:06:55'),
(17, NULL, 'create', 'Created appointment: APT-20260116111345-488', NULL, NULL, '2026-01-16 13:13:45'),
(18, NULL, 'create', 'Created appointment: APT-20260116111521-330', NULL, NULL, '2026-01-16 13:15:21'),
(19, NULL, 'create', 'Created appointment: APT-20260116-671', NULL, NULL, '2026-01-16 22:54:06'),
(20, NULL, 'create', 'Created appointment: APT-20260117-577', NULL, NULL, '2026-01-17 10:39:43'),
(21, NULL, 'create', 'Created appointment: APT-20260117-608', NULL, NULL, '2026-01-17 11:26:39'),
(22, NULL, 'create', 'Created appointment: APT-20260117-453', NULL, NULL, '2026-01-17 11:33:31'),
(23, NULL, 'create', 'Created appointment: APT-20260117111433-848', NULL, NULL, '2026-01-17 13:14:33'),
(24, NULL, 'create', 'Created appointment: APT-20260117-666', NULL, NULL, '2026-01-17 13:15:48'),
(25, NULL, 'create', 'Created appointment: APT-20260117-414', NULL, NULL, '2026-01-17 13:24:52'),
(26, NULL, 'create', 'Created appointment: APT-20260117114111-156', NULL, NULL, '2026-01-17 13:41:11'),
(27, NULL, 'create', 'Created appointment: APT-20260117-494', NULL, NULL, '2026-01-17 13:42:28'),
(28, NULL, 'create', 'Created appointment: APT-20260117-025', NULL, NULL, '2026-01-17 16:16:32'),
(29, NULL, 'create', 'Created appointment: APT-20260117-431', NULL, NULL, '2026-01-17 19:07:05'),
(30, NULL, 'create', 'Created patient: John Doe', NULL, NULL, '2026-01-17 21:56:00'),
(31, NULL, 'create', 'Created appointment: APT-20260118-133', NULL, NULL, '2026-01-18 20:43:05'),
(32, NULL, 'create', 'Created appointment: APT-20260119-013', NULL, NULL, '2026-01-19 13:29:12'),
(33, NULL, 'create', 'Created appointment: APT-20260119-295', NULL, NULL, '2026-01-19 13:29:58'),
(34, NULL, 'create', 'Created appointment: APT-20260119-849', NULL, NULL, '2026-01-20 00:03:36'),
(35, NULL, 'create', 'Created appointment: APT-20260119-324', NULL, NULL, '2026-01-20 00:05:28'),
(36, NULL, 'create', 'Created appointment: APT-20260119-136', NULL, NULL, '2026-01-20 01:03:58'),
(37, NULL, 'create', 'Created appointment: APT-20260119-024', NULL, NULL, '2026-01-20 01:23:16'),
(38, NULL, 'create', 'Created appointment: APT-20260120-967', NULL, NULL, '2026-01-20 10:35:17'),
(39, NULL, 'create', 'Created appointment: APT-20260120-131', NULL, NULL, '2026-01-20 10:36:08'),
(40, NULL, 'create', 'Created appointment: APT-20260120-527', NULL, NULL, '2026-01-20 10:36:42'),
(41, NULL, 'create', 'Created appointment: APT-202601-00001', NULL, NULL, '2026-01-20 10:45:11'),
(42, NULL, 'create', 'Created appointment: APT-202601-00001', NULL, NULL, '2026-01-20 10:45:40'),
(43, NULL, 'create', 'Created appointment: APT-20260120-871', NULL, NULL, '2026-01-20 10:48:01'),
(44, NULL, 'create', 'Created appointment: APT-20260120-838', NULL, NULL, '2026-01-20 11:00:31'),
(45, NULL, 'create', 'Created appointment: APT-20260120-414', NULL, NULL, '2026-01-20 11:32:51'),
(46, NULL, 'create', 'Created patient: Kelvin Mwasi', NULL, NULL, '2026-01-20 13:08:53'),
(47, NULL, 'create', 'Created appointment: APT-20260120-069', NULL, NULL, '2026-01-20 13:10:15'),
(48, NULL, 'create', 'Created appointment: APT-20260120-993', NULL, NULL, '2026-01-20 13:42:24'),
(49, NULL, 'create', 'Created patient: Achieng Odwour', NULL, NULL, '2026-01-20 13:58:05'),
(50, NULL, 'create', 'Created appointment: APT-20260120-434', NULL, NULL, '2026-01-20 13:58:52'),
(51, NULL, 'create', 'Created appointment: APT-20260120-459', NULL, NULL, '2026-01-20 16:06:47'),
(52, NULL, 'create', 'Created patient: Brianna Wambui', NULL, NULL, '2026-01-20 21:39:45'),
(53, NULL, 'create', 'Created appointment: APT-20260120-046', NULL, NULL, '2026-01-20 21:40:34'),
(54, NULL, 'create', 'Created appointment: APT-20260120-447', NULL, NULL, '2026-01-20 23:13:58'),
(55, NULL, 'create', 'Created appointment: APT-20260120-092', NULL, NULL, '2026-01-20 23:44:25'),
(56, NULL, 'create', 'Created appointment: APT-20260121-771', NULL, NULL, '2026-01-21 11:36:52'),
(57, NULL, 'create', 'Created patient: Tanya Mutana', NULL, NULL, '2026-01-21 14:44:10'),
(58, NULL, 'create', 'Created appointment: APT-20260121-466', NULL, NULL, '2026-01-21 14:46:38'),
(59, NULL, 'create', 'Created appointment: APT-20260122-143', NULL, NULL, '2026-01-22 22:50:25'),
(60, NULL, 'create', 'Created appointment: APT-20260205-846', NULL, NULL, '2026-02-05 16:12:07'),
(61, NULL, 'create', 'Created appointment: APT-20260205-844', NULL, NULL, '2026-02-05 17:51:34'),
(62, NULL, 'create', 'Created appointment: APT-20260205-488', NULL, NULL, '2026-02-05 17:58:34'),
(63, NULL, 'create', 'Created appointment: APT-20260205-171', NULL, NULL, '2026-02-05 20:28:19'),
(64, NULL, 'create', 'Created appointment: APT-20260205-972', NULL, NULL, '2026-02-05 21:31:33'),
(65, NULL, 'create', 'Created appointment: APT-20260206-096', NULL, NULL, '2026-02-06 11:37:13'),
(66, NULL, 'create', 'Created appointment: APT-20260206-955', NULL, NULL, '2026-02-06 11:41:32'),
(67, NULL, 'create', 'Created appointment: APT-20260206-522', NULL, NULL, '2026-02-06 13:36:21'),
(68, NULL, 'create', 'Created appointment: APT-20260206-226', NULL, NULL, '2026-02-06 13:47:19'),
(69, NULL, 'create', 'Created appointment: APT-20260206-923', NULL, NULL, '2026-02-06 13:49:12'),
(70, NULL, 'create', 'Created appointment: APT-20260206-701', NULL, NULL, '2026-02-06 13:58:09'),
(71, NULL, 'create', 'Created appointment: APT-20260206-9542', NULL, NULL, '2026-02-06 14:50:41');

-- --------------------------------------------------------

--
-- Table structure for table `beds`
--

CREATE TABLE `beds` (
  `id` int(11) NOT NULL,
  `bed_number` varchar(50) NOT NULL,
  `ward_id` int(11) NOT NULL,
  `bed_type` enum('Standard','ICU','Ventilator','Oxygen Support','Isolation','Electric','Manual') NOT NULL,
  `bed_status` enum('available','occupied','reserved','maintenance','cleaning') DEFAULT 'available',
  `price_per_day` decimal(10,2) DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `features` text DEFAULT NULL COMMENT 'JSON or comma-separated list of features',
  `last_maintenance_date` date DEFAULT NULL,
  `next_maintenance_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `beds`
--

INSERT INTO `beds` (`id`, `bed_number`, `ward_id`, `bed_type`, `bed_status`, `price_per_day`, `description`, `features`, `last_maintenance_date`, `next_maintenance_date`, `created_at`, `updated_at`) VALUES
(2, 'BED-002', 1, 'Oxygen Support', 'available', 2500.00, 'Used by patients who need oxygen support during their admission period.', 'Oxygen tank', NULL, NULL, '2025-10-26 07:50:07', '2026-01-17 11:34:17'),
(4, 'BED-004', 1, 'Ventilator', 'available', 2000.00, 'Used for patients diagnosed with asthma or other respiratory illness that will require use of ventilator in times of emergency.', '', NULL, NULL, '2025-10-26 07:52:05', '2026-01-17 16:12:13'),
(5, 'BED-008', 2, 'Electric', 'available', 5000.00, 'Used for all VIP patients', '', NULL, NULL, '2025-10-26 08:58:23', '2026-01-21 11:35:40'),
(7, 'BED-005', 3, 'Standard', 'available', 1500.00, 'Used for all mothers', '', NULL, NULL, '2025-10-27 07:06:05', '2026-01-21 11:34:34'),
(8, 'BED-006', 3, 'Electric', 'available', 2000.00, 'electric bed for maximum comfortability for mothers', '', NULL, NULL, '2025-10-27 07:06:35', '2026-01-21 11:34:42'),
(9, 'BED-001', 6, 'Standard', 'available', 2500.00, 'use for heart attack patients', '', NULL, NULL, '2025-10-30 13:13:17', '2026-01-19 22:27:11'),
(10, 'BED-007', 5, 'Standard', 'available', 1000.00, '', '', NULL, NULL, '2026-01-17 11:38:07', '2026-01-21 12:04:23');

-- --------------------------------------------------------

--
-- Table structure for table `bed_assignments`
--

CREATE TABLE `bed_assignments` (
  `id` int(10) UNSIGNED NOT NULL,
  `bed_id` int(11) NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `admission_date` date NOT NULL,
  `expected_discharge_date` date DEFAULT NULL,
  `actual_discharge_date` date DEFAULT NULL,
  `reason_for_admission` text DEFAULT NULL,
  `assigned_by` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('active','discharged','transferred') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `discharge_notes` text DEFAULT NULL,
  `final_diagnosis` varchar(500) DEFAULT NULL,
  `treatment_summary` text DEFAULT NULL,
  `follow_up_instructions` text DEFAULT NULL,
  `discharged_by` int(10) UNSIGNED DEFAULT NULL,
  `payment_status` enum('pending','paid','partial') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bed_assignments`
--

INSERT INTO `bed_assignments` (`id`, `bed_id`, `patient_id`, `admission_date`, `expected_discharge_date`, `actual_discharge_date`, `reason_for_admission`, `assigned_by`, `status`, `created_at`, `updated_at`, `discharge_notes`, `final_diagnosis`, `treatment_summary`, `follow_up_instructions`, `discharged_by`, `payment_status`) VALUES
(2, 2, 1, '2025-10-26', '2025-10-31', '2025-10-27', 'Admitted after she experienced an Asthma attack, to be put under observation till she recovers.', 1, 'discharged', '2025-10-26 08:56:40', '2026-01-19 21:35:28', NULL, NULL, NULL, NULL, NULL, 'paid'),
(3, 5, 7, '2025-10-26', '2025-10-29', '2025-10-27', 'Admitted for IV treatments.', 1, 'discharged', '2025-10-26 08:58:54', '2026-01-19 21:37:21', NULL, NULL, NULL, NULL, NULL, 'paid'),
(4, 7, 9, '2025-10-27', '2025-10-28', '2025-10-27', 'Observation for a day.', 1, 'discharged', '2025-10-27 07:06:57', '2026-01-19 21:37:21', NULL, NULL, NULL, NULL, NULL, 'paid'),
(5, 7, 8, '2025-10-27', '2025-10-27', '2025-10-27', 'Admitted for IV drip treatments for nutrients.', 1, 'discharged', '2025-10-27 07:38:15', '2026-01-19 21:37:21', NULL, NULL, NULL, NULL, NULL, 'paid'),
(6, 8, 9, '2025-10-27', '2025-10-28', '2025-10-27', 'Admitted for IV drip treatment.', 1, 'discharged', '2025-10-27 07:42:09', '2026-01-19 21:37:21', NULL, NULL, NULL, NULL, NULL, 'paid'),
(7, 8, 9, '2025-10-27', '2025-10-27', '2025-10-27', 'Admitted for a day for observation due to extreme stomach pains.', 1, 'discharged', '2025-10-27 07:47:37', '2026-01-19 21:37:21', NULL, NULL, NULL, NULL, NULL, 'paid'),
(8, 2, 5, '2025-10-27', '2025-10-28', '2025-10-27', 'Admitted for ventilator support treatment, suffered an asthma attack.', 1, 'discharged', '2025-10-27 07:48:38', '2026-01-19 21:37:21', NULL, NULL, NULL, NULL, NULL, 'paid'),
(9, 5, 10, '2025-10-27', '2025-10-27', '2025-10-27', 'Admitted for supplements.', 1, 'discharged', '2025-10-27 08:22:29', '2026-01-19 21:37:21', 'N/A', 'Exhaustion and overworking.', 'Get plenty of rest and take given supplements to aid in recovery.', 'Should get more exercise in to prevent any further complications', 55, 'paid'),
(10, 3, 1, '2025-10-27', '2025-10-27', '2025-10-27', 'Admitted for observation due to headaches and diarrhea.', 1, 'discharged', '2025-10-27 08:31:52', '2026-01-19 21:37:21', 'SHOULD CONTINUE WITH THE ANTIBIOTICS FOR 5 DAYS STRAIGHT.', 'Stomach flu', 'Put on antibiotics and supplements.', 'Eat clean, washed food and fruits. Avoid eating street food for a while.', 55, 'paid'),
(11, 3, 11, '2025-10-31', '2025-10-31', '2025-11-01', 'IV drip treatments', 1, 'discharged', '2025-10-31 20:48:45', '2026-01-20 11:06:22', '', '', '', '', 55, 'paid'),
(12, 5, 11, '2025-11-01', '2025-11-05', '2025-11-06', 'To be put on IV drips and is to be actively monitored every 2 hours. Light meals to be given .', 1, 'discharged', '2025-11-02 18:54:19', '2026-01-19 21:37:21', '', 'Exhaustion and overworking.', 'supplement treatments and IV drip treatment.', 'Get plenty of rest and exercise more.', 55, 'paid'),
(13, 3, 14, '2026-01-09', '2026-01-10', '2026-01-09', 'Needs glucose drip.', 1, 'discharged', '2026-01-09 21:24:00', '2026-01-19 21:37:21', '', 'hypoglycemic episode', 'Put on glucose drip as well as supplements', 'To take regular meals.', 69, 'paid'),
(14, 2, 1, '2026-01-17', '2026-01-18', '2026-01-17', 'Acute severe asthma attack, to be put on oxygen therapy', 1, 'discharged', '2026-01-17 08:32:20', '2026-01-19 21:37:21', 'If attacks persist report for follow-up', 'ACUTE ASTHMA ATTACK', 'Was put on controlled oxygen therapy to regulate their breathing', 'Continue taking their inhaler dosage and dress warmly!', 59, 'paid'),
(15, 3, 15, '2026-01-17', '2026-01-17', '2026-01-17', 'Dehydration and exhaustion which lead to patient fainting at work.', 1, 'discharged', '2026-01-17 10:18:55', '2026-01-19 21:37:21', '', 'Exhaustion and overworking.', 'supplement treatments and IV drip treatment.', 'Get plenty of rest and eat more healthy whole foods to replenish energy.', 59, 'paid'),
(16, 3, 8, '2026-01-17', '2026-01-18', '2026-01-17', 'IV drip treatment', 1, 'discharged', '2026-01-17 10:30:28', '2026-01-19 21:37:21', '', 'Supplement treatments', 'IV drip treatments', '', 69, 'paid'),
(17, 3, 6, '2026-01-17', '2026-01-17', '2026-01-17', 'Heat stroke', 1, 'discharged', '2026-01-17 10:45:27', '2026-01-19 21:37:21', '', 'Heat stroke', 'IV DRIPS', 'Hydrate more often', 69, 'paid'),
(18, 2, 7, '2026-01-17', '2026-01-18', '2026-01-18', 'drip treatment', 1, 'discharged', '2026-01-17 11:33:40', '2026-01-19 21:37:21', '', 'Supplement treatments', 'IV treatment', '', 55, 'paid'),
(19, 10, 1, '2026-01-17', '2026-01-17', '2026-01-17', 'oxygen therapy', 1, 'discharged', '2026-01-17 11:38:37', '2026-01-19 21:37:21', '', 'ACUTE ASTHMA ATTACK', 'oxygen tank', '', 55, 'paid'),
(20, 7, 9, '2026-01-17', '2026-01-17', '2026-01-17', 'stomach pains', 1, 'discharged', '2026-01-17 11:39:51', '2026-01-19 21:37:21', '', 'Stomach flu', 'antibiotics', '', 55, 'paid'),
(21, 3, 8, '2026-01-17', '2026-01-17', '2026-01-17', 'glucose drip', 1, 'discharged', '2026-01-17 13:12:53', '2026-01-19 21:37:21', '', 'Exhaustion and overworking.', 'glucose drip', '', 55, 'paid'),
(22, 10, 8, '2026-01-17', '2026-01-17', '2026-01-17', 'Asthma attack', 1, 'discharged', '2026-01-17 13:18:14', '2026-01-19 21:37:21', '', 'ACUTE ASTHMA ATTACK', 'oxygen tank for therapy and regulation.', '', 55, 'paid'),
(23, 4, 14, '2026-01-17', '2026-01-17', '2026-01-17', 'Oxygen tank', 1, 'discharged', '2026-01-17 16:11:08', '2026-01-19 21:37:21', '', 'panic attack', 'Oxygen therapy', '', 69, 'paid'),
(24, 10, 5, '2026-01-19', '2026-01-19', '2026-01-19', 'Acute Asthma Attack', 1, 'discharged', '2026-01-19 10:49:30', '2026-01-19 21:37:21', '', 'ACUTE ASTHMA ATTACK', 'oxygen', '', 67, 'paid'),
(25, 9, 4, '2026-01-19', '2026-01-20', '2026-01-19', 'heart failure', 1, 'discharged', '2026-01-19 22:25:33', '2026-01-20 11:17:32', '', 'heart failure', 'metoprolol and monitored', '', 59, 'paid'),
(26, 3, 31, '2026-01-20', '2026-01-20', '2026-01-20', 'IV SUPPLEMENT TREATMENTS', 1, 'discharged', '2026-01-20 11:01:18', NULL, NULL, 'Supplement deficiency', 'IV supplements', NULL, NULL, 'pending'),
(28, 10, 33, '2026-01-21', '2026-01-21', '2026-01-21', 'Asthma attack', 1, 'discharged', '2026-01-21 11:50:48', '2026-01-21 12:23:50', '', 'ACUTE ASTHMA ATTACK', 'OXYGEN GAS MASK THERAPY', '', 61, 'paid');

-- --------------------------------------------------------

--
-- Table structure for table `billing`
--

CREATE TABLE `billing` (
  `id` int(11) NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `admission_id` int(10) UNSIGNED DEFAULT NULL,
  `bill_number` varchar(50) DEFAULT NULL,
  `bill_date` datetime DEFAULT current_timestamp(),
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `paid_amount` decimal(12,2) DEFAULT 0.00,
  `balance_amount` decimal(12,2) DEFAULT 0.00,
  `status` enum('pending','paid','partial','cancelled') DEFAULT 'pending',
  `due_date` date DEFAULT NULL,
  `payment_method` enum('cash','mpesa','card','insurance','bank_transfer') DEFAULT 'cash',
  `payment_reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billing`
--

INSERT INTO `billing` (`id`, `patient_id`, `admission_id`, `bill_number`, `bill_date`, `total_amount`, `paid_amount`, `balance_amount`, `status`, `due_date`, `payment_method`, `payment_reference`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 10, NULL, 'BL-20251027-092712-671', '2025-10-27 11:27:12', 0.00, 5500.00, -5500.00, 'paid', '2025-11-03', 'insurance', '', 'Hospital stay discharge - VIP Ward (Bed: BED-001)', 55, '2025-10-27 08:27:12', '2026-01-20 20:06:21'),
(2, 1, NULL, 'BL-20251027-093422-471', '2025-10-27 11:34:22', 0.00, 2800.00, -2800.00, 'paid', '2025-11-03', 'cash', '', 'Hospital stay discharge - General Ward (Bed: BED-003)', 55, '2025-10-27 08:34:22', '2026-01-20 20:06:21'),
(3, 7, NULL, 'BL-20251030200715-306', '2025-10-30 22:07:15', 0.00, 1000.00, -1000.00, 'paid', '2025-11-06', 'cash', NULL, NULL, 55, '2025-10-30 19:07:15', '2026-01-20 20:06:21'),
(8, 11, NULL, 'BL-20251101-215135-618', '2025-11-01 23:51:35', 0.00, 7000.00, -7000.00, 'paid', '2025-11-08', 'insurance', '', 'Hospital stay discharge - General Ward (Bed: BED-003)', 55, '2025-11-01 20:51:35', '2026-01-20 20:06:21'),
(9, 10, NULL, 'BL-20251102114738-126', '2025-11-02 13:47:38', 500.00, 1500.00, -1000.00, 'paid', NULL, 'card', '', NULL, 55, '2025-11-02 10:47:38', '2026-01-20 20:19:15'),
(10, 12, NULL, 'BL-20251102115245-822', '2025-11-02 13:52:45', 0.00, 500.00, -500.00, 'paid', '2025-11-09', 'cash', NULL, NULL, 55, '2025-11-02 10:52:45', '2026-01-20 20:06:21'),
(11, 11, NULL, 'BL-20251102193800-288', '2025-11-02 21:38:00', 0.00, 10000.00, -10000.00, 'paid', '2025-11-09', 'card', NULL, NULL, 72, '2025-11-02 18:38:00', '2026-01-20 20:06:21'),
(12, 2, NULL, 'BL-20251103214854-430', '2025-11-03 23:48:54', 0.00, 1000.00, -1000.00, 'paid', '2025-11-10', 'cash', NULL, NULL, 55, '2025-11-03 20:48:54', '2026-01-20 20:06:21'),
(13, 12, NULL, 'BL-20251104083446-919', '2025-11-04 10:34:46', 0.00, 500.00, -500.00, 'paid', '2025-11-11', 'cash', NULL, NULL, 63, '2025-11-04 07:34:46', '2026-01-20 20:06:21'),
(14, 11, NULL, 'BL-20251106-073111-621', '2025-11-06 09:31:11', 0.00, 25000.00, -25000.00, 'paid', '2025-11-13', 'card', '', 'Hospital stay discharge - VIP Ward (Bed: BED-001)', 55, '2025-11-06 06:31:11', '2026-01-20 20:06:21'),
(15, 13, NULL, 'BL-20251106092739-679', '2025-11-06 11:27:39', 0.00, 500.00, -500.00, 'paid', '2025-11-13', 'cash', NULL, NULL, 55, '2025-11-06 08:27:39', '2026-01-20 20:06:21'),
(17, 14, NULL, 'BL-20251114075930-211', '2025-11-14 09:59:30', 0.00, 500.00, -500.00, 'paid', '2025-11-21', 'cash', '', NULL, 56, '2025-11-14 06:59:30', '2026-01-20 20:06:21'),
(18, 13, NULL, 'BL-20251117213112-948', '2025-11-17 23:31:12', 0.00, 500.00, -500.00, 'paid', '2025-11-24', 'card', '', NULL, 55, '2025-11-17 20:31:12', '2026-01-20 20:06:21'),
(19, 5, NULL, 'BL-20251118100532-930', '2025-11-18 12:05:32', 0.00, 500.00, -500.00, 'paid', '2025-11-25', 'cash', '', NULL, 55, '2025-11-18 09:05:32', '2026-01-20 20:06:21'),
(20, 8, NULL, 'BL-20260109094942-732', '2026-01-09 11:49:42', 0.00, 500.00, -500.00, 'paid', '2026-01-16', 'cash', '', NULL, 55, '2026-01-09 08:49:42', '2026-01-20 20:06:21'),
(21, 6, NULL, 'BL-20260109215108-867', '2026-01-09 23:51:08', 0.00, 1000.00, -1000.00, 'paid', '2026-01-16', 'cash', NULL, NULL, 55, '2026-01-09 20:51:08', '2026-01-20 20:06:21'),
(22, 14, NULL, 'BL-20260109222145-724', '2026-01-10 00:21:45', 0.00, 5000.00, -5000.00, 'paid', '2026-01-17', 'card', '', NULL, 69, '2026-01-09 21:21:45', '2026-01-20 20:06:21'),
(23, 14, NULL, 'BL-20260109-222721-112', '2026-01-10 00:27:21', 0.00, 1500.00, -1500.00, 'paid', '2026-01-16', 'card', '', 'Hospital stay discharge - General Ward (Bed: BED-003)', 69, '2026-01-09 21:27:21', '2026-01-20 20:06:21'),
(24, 14, NULL, 'BL-20260115122922-819', '2026-01-15 14:29:22', 0.00, 500.00, -500.00, 'paid', '2026-01-22', 'cash', '', NULL, 63, '2026-01-15 11:29:22', '2026-01-20 20:06:21'),
(25, 15, NULL, 'BL-20260115131857-378', '2026-01-15 15:18:57', 0.00, 500.00, -500.00, 'paid', '2026-01-22', 'cash', '', NULL, 55, '2026-01-15 12:18:57', '2026-01-20 20:06:21'),
(26, 9, NULL, 'BL-20260115210715-270', '2026-01-15 23:07:15', 0.00, 500.00, -500.00, 'paid', '2026-01-22', 'cash', '', NULL, 63, '2026-01-15 20:07:15', '2026-01-20 20:06:21'),
(27, 1, NULL, 'BL-20260115215837-829', '2026-01-15 23:58:37', 0.00, 500.00, -500.00, 'paid', '2026-01-22', 'cash', '', NULL, 67, '2026-01-15 20:58:37', '2026-01-20 20:06:21'),
(28, 2, NULL, 'BL-20260116090655-419', '2026-01-16 11:06:55', 0.00, 500.00, -500.00, 'paid', '2026-01-23', 'cash', '', NULL, 55, '2026-01-16 08:06:55', '2026-01-20 20:06:21'),
(30, 7, NULL, 'BL-20260116120117-496', '2026-01-16 14:01:17', 500.00, 500.00, 0.00, 'paid', NULL, 'mpesa', '', NULL, 71, '2026-01-16 11:01:17', NULL),
(31, 10, NULL, 'BL-20260116205406-626', '2026-01-16 22:54:06', 0.00, 500.00, -500.00, 'paid', '2026-01-23', 'cash', '', NULL, 55, '2026-01-16 19:54:06', '2026-01-20 20:06:21'),
(32, 10, NULL, 'BL-20260116220520-143', '2026-01-17 00:05:20', 500.00, 500.00, 0.00, 'paid', NULL, 'cash', '', NULL, 71, '2026-01-16 21:05:20', NULL),
(33, 15, NULL, 'BL-20260117083943-977', '2026-01-17 10:39:43', 0.00, 500.00, -500.00, 'paid', '2026-01-24', 'cash', NULL, NULL, 55, '2026-01-17 07:39:43', '2026-01-20 20:06:21'),
(34, 15, NULL, 'BL-20260117085501-459', '2026-01-17 10:55:01', 500.00, 500.00, 0.00, 'paid', NULL, 'cash', '', NULL, 71, '2026-01-17 07:55:01', NULL),
(35, 1, NULL, 'BL-20260117092639-194', '2026-01-17 11:26:39', 0.00, 5000.00, -5000.00, 'paid', '2026-01-24', 'cash', NULL, NULL, 56, '2026-01-17 08:26:39', '2026-01-20 20:06:21'),
(36, 11, NULL, 'BL-20260117093331-968', '2026-01-17 11:33:31', 0.00, 500.00, -500.00, 'paid', '2026-01-24', 'cash', NULL, NULL, 56, '2026-01-17 08:33:31', '2026-01-20 20:06:21'),
(37, 11, NULL, 'BL-20260117094451-449', '2026-01-17 11:44:51', 500.00, 500.00, 0.00, 'paid', NULL, 'cash', '', NULL, 71, '2026-01-17 08:44:51', NULL),
(38, 1, NULL, 'BL-20260117-095921-385', '2026-01-17 11:59:21', 0.00, 2500.00, -2500.00, 'paid', '2026-01-24', 'cash', '', 'Hospital stay discharge - General Ward (Bed: BED-002)', 59, '2026-01-17 08:59:21', '2026-01-20 20:06:21'),
(39, 1, NULL, 'BL-20260117100401-297', '2026-01-17 12:04:01', 5000.00, 5000.00, 0.00, 'paid', NULL, 'cash', '', NULL, 71, '2026-01-17 09:04:01', NULL),
(40, 15, NULL, 'BL-20260117111548-910', '2026-01-17 13:15:48', 0.00, 500.00, -500.00, 'paid', '2026-01-24', 'cash', NULL, NULL, 56, '2026-01-17 10:15:48', '2026-01-20 20:06:21'),
(41, 15, NULL, 'BL-20260117-112029-869', '2026-01-17 13:20:29', 0.00, 1500.00, -1500.00, 'paid', '2026-01-24', 'cash', '', 'Hospital stay discharge - General Ward (Bed: BED-003)', 59, '2026-01-17 10:20:29', '2026-01-20 20:06:21'),
(42, 5, NULL, 'BL-20260117112452-423', '2026-01-17 13:24:52', 0.00, 500.00, -500.00, 'paid', '2026-01-24', 'cash', NULL, NULL, 55, '2026-01-17 10:24:52', '2026-01-20 20:06:21'),
(43, 5, NULL, 'BL-20260117112904-517', '2026-01-17 13:29:04', 500.00, 500.00, 0.00, 'paid', NULL, 'cash', '', NULL, 71, '2026-01-17 10:29:04', NULL),
(44, 8, NULL, 'BL-20260117-113149-209', '2026-01-17 13:31:49', 0.00, 1500.00, -1500.00, 'paid', '2026-01-24', 'cash', '', 'Hospital stay discharge - General Ward (Bed: BED-003)', 69, '2026-01-17 10:31:49', '2026-01-20 20:06:21'),
(45, 6, NULL, 'BL-20260117114228-371', '2026-01-17 13:42:28', 0.00, 1200.00, -1200.00, 'paid', '2026-01-24', 'cash', NULL, NULL, 69, '2026-01-17 10:42:28', '2026-01-20 20:06:21'),
(46, 6, NULL, 'BL-20260117-114637-246', '2026-01-17 13:46:37', 0.00, 1500.00, -1500.00, 'paid', '2026-01-24', 'cash', '', 'Hospital stay discharge - General Ward (Bed: BED-003)', 69, '2026-01-17 10:46:37', '2026-01-20 20:06:21'),
(47, 6, NULL, 'BL-20260117114810-387', '2026-01-17 13:48:10', 1200.00, 1200.00, 0.00, 'paid', NULL, 'cash', '', NULL, 71, '2026-01-17 10:48:10', NULL),
(48, 7, NULL, 'BL-20260117-123417-326', '2026-01-17 14:34:17', 0.00, 7500.00, -7500.00, 'paid', '2026-01-24', 'card', '', 'Hospital stay discharge - General Ward (Bed: BED-002)', 55, '2026-01-17 11:34:17', '2026-01-20 20:06:21'),
(49, 1, NULL, 'BL-20260117-123905-614', '2026-01-17 14:39:05', 0.00, 1000.00, -1000.00, 'paid', '2026-01-24', 'cash', '', 'Hospital stay discharge - Pediatric ward (Bed: BED-001)', 55, '2026-01-17 11:39:05', '2026-01-20 20:06:21'),
(50, 9, NULL, 'BL-20260117-124034-469', '2026-01-17 14:40:34', 0.00, 1500.00, -1500.00, 'paid', '2026-01-24', 'cash', '', 'Hospital stay discharge - Maternity ward (Bed: BED-001)', 55, '2026-01-17 11:40:34', '2026-01-20 20:06:21'),
(51, 8, NULL, 'BL-20260117-141318-339', '2026-01-17 16:13:18', 0.00, 1500.00, -1500.00, 'paid', '2026-01-24', 'cash', '', 'Hospital stay discharge - General Ward (Bed: BED-003)', 55, '2026-01-17 13:13:18', '2026-01-20 20:06:21'),
(52, 8, NULL, 'BL-20260117141632-362', '2026-01-17 16:16:32', 0.00, 1200.00, -1200.00, 'paid', '2026-01-24', 'cash', NULL, NULL, 55, '2026-01-17 13:16:32', '2026-01-20 20:06:21'),
(53, 8, NULL, 'BL-20260117-142102-134', '2026-01-17 16:21:02', 0.00, 1000.00, -1000.00, 'paid', '2026-01-24', 'cash', '', 'Hospital stay discharge - Pediatric ward (Bed: BED-001)', 55, '2026-01-17 13:21:02', '2026-01-20 20:06:21'),
(54, 8, NULL, 'BL-20260117142351-275', '2026-01-17 16:23:51', 1200.00, 1200.00, 0.00, 'paid', NULL, 'cash', '', NULL, 55, '2026-01-17 13:23:51', NULL),
(55, 2, NULL, 'BL-20260117154727-126', '2026-01-17 17:47:27', 1200.00, 1200.00, 0.00, 'paid', NULL, 'cash', '', NULL, 71, '2026-01-17 14:47:27', NULL),
(56, 14, NULL, 'BL-20260117170705-175', '2026-01-17 19:07:05', 0.00, 500.00, -500.00, 'paid', '2026-01-24', 'cash', NULL, NULL, 55, '2026-01-17 16:07:05', '2026-01-20 20:06:21'),
(57, 14, NULL, 'BL-20260117-171213-454', '2026-01-17 19:12:13', 0.00, 2000.00, -2000.00, 'paid', '2026-01-24', 'cash', '', 'Hospital stay discharge - General Ward (Bed: BED-004)', 69, '2026-01-17 16:12:13', '2026-01-20 20:06:21'),
(58, 14, NULL, 'BL-20260117171254-572', '2026-01-17 19:12:54', 500.00, 500.00, 0.00, 'paid', NULL, 'cash', '', NULL, 71, '2026-01-17 16:12:54', NULL),
(59, 10, NULL, 'BL-20260118184305-343', '2026-01-18 20:43:05', 0.00, 1200.00, -1200.00, 'paid', '2026-01-25', 'cash', NULL, NULL, 55, '2026-01-18 17:43:05', '2026-01-20 20:06:21'),
(60, 10, NULL, 'BL-20260118194641-905', '2026-01-18 21:46:41', 500.00, 500.00, 0.00, 'paid', NULL, 'cash', '', NULL, 55, '2026-01-18 18:46:41', NULL),
(61, 10, NULL, 'BL-20260118194641-127', '2026-01-18 21:46:41', 1200.00, 1200.00, 0.00, 'paid', NULL, 'cash', '', NULL, 55, '2026-01-18 18:46:41', NULL),
(62, 5, NULL, 'BL-20260119112913-717', '2026-01-19 13:29:13', 0.00, 0.00, 0.00, 'paid', '2026-01-26', 'cash', NULL, NULL, 56, '2026-01-19 10:29:13', '2026-01-20 20:06:21'),
(63, 12, NULL, 'BL-20260119112958-355', '2026-01-19 13:29:58', 0.00, 0.00, 0.00, 'paid', '2026-01-26', 'cash', NULL, NULL, 56, '2026-01-19 10:29:58', '2026-01-20 20:06:21'),
(64, 12, NULL, 'BL-20260119123858-971', '2026-01-19 14:38:58', 500.00, 500.00, 0.00, 'paid', NULL, 'cash', '', NULL, 71, '2026-01-19 11:38:58', NULL),
(65, 5, NULL, 'BL-20260119-212648-717', '2026-01-19 23:26:48', 0.00, 0.00, 0.00, 'paid', '2026-01-26', 'cash', '', 'Hospital stay discharge - Pediatric ward (Bed: BED-001)', 67, '2026-01-19 20:26:48', '2026-01-20 20:06:21'),
(66, 5, NULL, 'BL-20260119212857-706', '2026-01-19 23:28:57', 1200.00, 1200.00, 0.00, 'paid', NULL, 'cash', '', NULL, 71, '2026-01-19 20:28:57', NULL),
(67, 2, NULL, 'BL-20260119220336-131', '2026-01-20 00:03:36', 0.00, 0.00, 0.00, 'paid', '2026-01-27', 'cash', NULL, NULL, 56, '2026-01-19 21:03:36', '2026-01-20 20:06:21'),
(68, 2, NULL, 'BL-20260119220528-877', '2026-01-20 00:05:28', 0.00, 0.00, 0.00, 'paid', '2026-01-27', 'cash', NULL, NULL, 69, '2026-01-19 21:05:28', '2026-01-20 20:06:21'),
(69, 2, NULL, 'BL-20260119223040-867', '2026-01-20 00:30:40', 500.00, 500.00, 0.00, 'paid', NULL, 'mpesa', '', NULL, 71, '2026-01-19 21:30:40', NULL),
(70, 15, NULL, 'BL-20260119230358-583', '2026-01-20 01:03:58', 0.00, 0.00, 0.00, 'paid', '2026-01-27', 'cash', NULL, NULL, 67, '2026-01-19 22:03:58', '2026-01-20 20:06:21'),
(71, 4, NULL, 'BL-20260119232316-520', '2026-01-20 01:23:16', 0.00, 0.00, 0.00, 'paid', '2026-01-27', 'cash', NULL, NULL, 59, '2026-01-19 22:23:16', '2026-01-20 20:06:21'),
(72, 4, NULL, 'BL-20260119-232711-413', '2026-01-20 01:27:11', 0.00, 2500.00, -2500.00, 'paid', '2026-01-26', 'cash', 'PAY-20260119232827-108', 'Hospital stay discharge - cardiology ward (Bed: BED-001)', 59, '2026-01-19 22:27:11', '2026-01-20 20:06:21'),
(73, 6, NULL, 'BL-20260120083517-754', '2026-01-20 10:35:17', 8000.00, 1000.00, 7000.00, 'partial', '2026-01-27', 'cash', 'PAY-20260120105319-229', NULL, 56, '2026-01-20 07:35:17', '2026-02-06 10:49:12'),
(74, 11, NULL, 'BL-20260120083608-290', '2026-01-20 10:36:08', 8000.00, 1000.00, 7000.00, 'partial', '2026-01-27', 'cash', NULL, NULL, 56, '2026-01-20 07:36:08', '2026-02-06 10:49:12'),
(75, 4, NULL, 'BL-20260120083642-319', '2026-01-20 10:36:42', 8000.00, 1000.00, 7000.00, 'partial', '2026-01-27', 'cash', 'PAY-20260120131729-755', NULL, 56, '2026-01-20 07:36:42', '2026-02-06 10:49:12'),
(76, 4, NULL, 'BL-20260120084801-671', '2026-01-20 10:48:01', 8000.00, 1000.00, 7000.00, 'partial', '2026-01-27', 'cash', 'PAY-20260120131729-755', NULL, 55, '2026-01-20 07:48:01', '2026-02-06 10:49:12'),
(77, 11, NULL, 'BL-20260120090031-578', '2026-01-20 11:00:31', 8000.00, 1000.00, 7000.00, 'partial', '2026-01-27', 'cash', NULL, NULL, 55, '2026-01-20 08:00:31', '2026-02-06 10:49:12'),
(78, 6, NULL, 'BL-20260120093251-154', '2026-01-20 11:32:51', 8000.00, 1000.00, 7000.00, 'partial', '2026-01-27', 'cash', 'PAY-20260120105319-229', NULL, 55, '2026-01-20 08:32:51', '2026-02-06 10:49:12'),
(79, 0, NULL, 'BL-20260120111015-743', '2026-01-20 13:10:15', 8000.00, 1000.00, 7000.00, 'partial', '2026-01-27', 'cash', NULL, NULL, 55, '2026-01-20 10:10:15', '2026-02-06 10:49:12'),
(80, 16, NULL, 'BL-20260120114224-786', '2026-01-20 13:42:24', 8000.00, 1000.00, 7000.00, 'partial', '2026-01-27', 'cash', 'PAY-20260120114800-157', NULL, 56, '2026-01-20 10:42:24', '2026-02-06 10:49:12'),
(81, 31, NULL, 'BL-20260120115852-613', '2026-01-20 13:58:52', 8000.00, 1000.00, 7000.00, 'partial', '2026-01-27', 'cash', 'PAY-20260120133753-208', NULL, 55, '2026-01-20 10:58:52', '2026-02-06 10:49:12'),
(82, 15, NULL, 'BL-20260120140647-592', '2026-01-20 16:06:47', 8000.00, 1000.00, 7000.00, 'partial', '2026-01-27', 'cash', NULL, NULL, 56, '2026-01-20 13:06:47', '2026-02-06 10:49:12'),
(83, 32, NULL, 'BL-20260120194034-886', '2026-01-20 21:40:34', 8000.00, 1000.00, 7000.00, 'partial', '2026-01-27', 'cash', NULL, NULL, 55, '2026-01-20 18:40:34', '2026-02-06 10:49:12'),
(84, 32, NULL, 'BL-20260120210632-830', '2026-01-20 23:06:32', 8000.00, 1000.00, 7000.00, 'partial', NULL, 'cash', '', NULL, 72, '2026-01-20 20:06:32', '2026-02-06 10:49:12'),
(85, 10, NULL, 'BL-20260120211358-457', '2026-01-20 23:13:58', 8000.00, 1000.00, 7000.00, 'partial', '2026-01-27', 'cash', NULL, NULL, 55, '2026-01-20 20:13:58', '2026-02-06 10:49:12'),
(86, 10, NULL, 'BL-20260120211641-814', '2026-01-20 23:16:41', 8000.00, 1000.00, 7000.00, 'partial', NULL, 'cash', '', NULL, 71, '2026-01-20 20:16:41', '2026-02-06 10:49:12'),
(87, 16, NULL, 'BL-20260120214425-488', '2026-01-20 23:44:25', 8000.00, 0.00, 7000.00, 'partial', '2026-01-27', 'cash', NULL, NULL, 55, '2026-01-20 20:44:25', '2026-02-06 10:49:12'),
(88, 7, NULL, 'BL-20260121093652-306', '2026-01-21 11:36:52', 8000.00, 0.00, 7000.00, 'partial', '2026-01-28', 'cash', NULL, NULL, 55, '2026-01-21 08:36:52', '2026-02-06 10:49:12'),
(89, 5, NULL, 'BL-20260121-123200-787', '2026-01-21 14:32:00', 8000.00, 0.00, 7000.00, 'partial', '2026-01-28', 'cash', '', 'Hospital stay discharge - Pediatric ward (Bed: BED-001)', 55, '2026-01-21 11:32:00', '2026-02-06 10:49:12'),
(90, 33, NULL, 'BL-20260121124638-157', '2026-01-21 14:46:38', 8000.00, 0.00, 7000.00, 'partial', '2026-01-28', 'cash', NULL, NULL, 55, '2026-01-21 11:46:38', '2026-02-06 10:49:12'),
(91, 33, NULL, 'BL-20260121-130423-422', '2026-01-21 15:04:23', 8000.00, 0.00, 7000.00, 'partial', '2026-01-28', 'cash', '', 'Hospital stay discharge - Pediatric ward (Bed: BED-007)', 61, '2026-01-21 12:04:23', '2026-02-06 10:49:12'),
(92, 9, NULL, 'BL-20260122205026-599', '2026-01-22 22:50:26', 8000.00, 0.00, 7000.00, 'partial', '2026-01-29', 'cash', NULL, NULL, 55, '2026-01-22 19:50:26', '2026-02-06 10:49:12'),
(93, 33, NULL, 'BL-20260206093713-316', '2026-02-06 11:37:13', 8000.00, 0.00, 7000.00, 'partial', '2026-02-13', 'cash', NULL, NULL, 55, '2026-02-06 08:37:13', '2026-02-06 10:49:12'),
(94, 32, NULL, 'BL-20260206094132-452', '2026-02-06 11:41:32', 8000.00, 0.00, 7000.00, 'partial', '2026-02-13', 'cash', NULL, NULL, 56, '2026-02-06 08:41:32', '2026-02-06 10:49:12'),
(95, 31, NULL, 'BL-20260206113621-701', '2026-02-06 13:36:21', 8000.00, 0.00, 7000.00, 'partial', '2026-02-13', 'cash', NULL, NULL, 56, '2026-02-06 10:36:21', '2026-02-06 10:49:12'),
(96, 16, NULL, 'BL-20260206114719-276', '2026-02-06 13:47:19', 8000.00, 0.00, 7000.00, 'partial', '2026-02-13', 'cash', NULL, NULL, 56, '2026-02-06 10:47:19', '2026-02-06 10:49:12'),
(97, 31, NULL, 'BL-20260206114912-821', '2026-02-06 13:49:12', 8000.00, 0.00, 7000.00, 'partial', '2026-02-13', 'cash', NULL, NULL, 56, '2026-02-06 10:49:12', '2026-02-06 10:49:12'),
(164, 10, NULL, 'BL-20260206-125041-596', '2026-02-06 14:50:41', 500.00, 0.00, 500.00, 'pending', '2026-02-13', 'cash', NULL, NULL, 55, '2026-02-06 11:50:41', NULL);

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
(1, 1, 'Transchem Pharmaceutical', 'Paracetamol', 'capsule', '100', 20.00, 5),
(2, 2, 'VeePharmaceuticals', 'Insulin', 'ml', '120', 1750.00, 4),
(3, 3, 'GracePharmacuticals', 'Cough syrup', 'ml', '100', 750.00, 1),
(4, 1, 'MediCare Pharmaceuticals', 'Amoxicillin', 'box', '100', 850.00, 2),
(5, 1, 'Transchem Pharmaceutical', 'Mara Moja', 'box', '100', 20.00, 10),
(6, 1, 'GracePharmacuticals', 'Cough syrup', 'ml', '100', 750.00, 1),
(7, 2, 'VeePharmaceutical', 'Ibuprofen', 'box', '200', 800.00, 1),
(8, 2, 'GracePharmacuticals', 'Cough syrup', 'ml', '100', 750.00, 2),
(9, 3, 'Transchem Pharmaceutical', 'Paracetamol', 'capsule', '100', 20.00, 10),
(10, 4, 'Transchem Pharmaceutical', 'Paracetamol', 'capsule', '100', 20.00, 5),
(11, 5, 'GracePharmacuticals', 'Cough syrup', 'ml', '100', 750.00, 1),
(13, 7, 'Transchem Pharmaceutical', 'Paracetamol', 'capsule', '100', 20.00, 5),
(14, 6, 'GracePharmacuticals', 'Cough syrup', 'ml', '100', 750.00, 2),
(15, 6, 'Transchem Pharmaceutical', 'Eno', 'sachet', '200', 15.00, 7),
(16, 10, 'Transchem Pharmaceutical', 'Eno', 'sachet', '200', 15.00, 10),
(18, 12, 'GracePharmacuticals', 'Cough syrup', 'ml', '100', 750.00, 2),
(19, 13, 'Transchem Pharmaceutical', 'Mara Moja', 'box', '100', 20.00, 10),
(21, 15, 'Transchem Pharmaceutical', 'Mara Moja', 'box', '100', 20.00, 10),
(22, 15, 'VeePharmaceutical', 'Ibuprofen', 'box', '200', 800.00, 2),
(24, 16, 'Transchem Pharmaceutical', 'Eno', 'sachet', '200', 15.00, 3),
(25, 17, 'GracePharmacuticals', 'Cough syrup', 'ml', '100', 750.00, 1),
(26, 18, 'VeePharmaceutical', 'asthma pump', 'pump', '10', 750.00, 2),
(27, 19, 'GracePharmacuticals', 'Cough syrup', 'ml', '100', 750.00, 1),
(28, 20, 'MediCare Pharmaceuticals', 'Amoxicillin', 'mg', '100', 850.00, 1),
(29, 21, 'Transchem Pharmaceutical', 'Eno', 'sachet', '200', 15.00, 10),
(30, 27, 'MediPlugequipments', 'Blood Pressure Monitor', 'box', '50', 3000.00, 1),
(31, 28, 'GracePharmaceuticals', 'Ponstan', 'tablet', '50', 65.00, 5),
(32, 29, 'Transchem pharmaceuticals', 'Agumentin', 'tablet', '100', 825.00, 2),
(33, 30, 'Transchem pharmaceuticals', 'Mara Moja', 'box', '100', 25.00, 10);

-- --------------------------------------------------------

--
-- Table structure for table `billing_items`
--

CREATE TABLE `billing_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `billing_id` int(10) UNSIGNED NOT NULL,
  `item_description` text NOT NULL,
  `quantity` int(10) UNSIGNED DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `item_type` varchar(50) DEFAULT NULL,
  `service_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billing_items`
--

INSERT INTO `billing_items` (`id`, `billing_id`, `item_description`, `quantity`, `unit_price`, `total_amount`, `item_type`, `service_date`, `created_at`) VALUES
(1, 1, 'Consultation Fee', 1, 1500.00, 1500.00, 'consultation', NULL, '2025-11-06 06:46:34');

-- --------------------------------------------------------

--
-- Table structure for table `billing_payments`
--

CREATE TABLE `billing_payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `receipt_number` varchar(50) NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `patient_name` varchar(255) NOT NULL,
  `service_type` enum('consultation','pharmacy','lab','admission','consolidated') NOT NULL,
  `reference_id` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('cash','mpesa','card','insurance','bank_transfer') DEFAULT 'cash',
  `transaction_reference` varchar(100) DEFAULT NULL,
  `status` enum('pending','completed','failed','refunded') DEFAULT 'completed',
  `processed_by` int(10) UNSIGNED DEFAULT NULL,
  `processed_by_name` varchar(255) DEFAULT NULL,
  `payment_date` datetime DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billing_payments`
--

INSERT INTO `billing_payments` (`id`, `receipt_number`, `patient_id`, `patient_name`, `service_type`, `reference_id`, `description`, `amount`, `payment_method`, `transaction_reference`, `status`, `processed_by`, `processed_by_name`, `payment_date`, `created_at`) VALUES
(1, 'RCPT-000001', 6, 'Alpha Mwirigi', 'consultation', 'BL-20260109215108-867', 'Consultation Fee', 500.00, 'mpesa', '', 'completed', 55, 'Vivian chepchumba', '2026-01-10 00:10:08', '2026-01-09 21:10:08'),
(2, 'RCPT-1.844674407371E+19', 6, 'Alpha Mwirigi', 'pharmacy', 'PHARM-0042', 'Pharmacy Charges', 250.00, 'mpesa', '', 'completed', 55, 'Vivian chepchumba', '2026-01-10 00:10:08', '2026-01-09 21:10:08'),
(3, 'RCPT-1.844674407371E+19', 6, 'Alpha Mwirigi', 'consultation', 'BL-20260109215108-867', 'Consultation Fee', 500.00, 'mpesa', '', 'completed', 55, 'Vivian chepchumba', '2026-01-10 00:10:48', '2026-01-09 21:10:48'),
(4, 'RCPT-1.844674407371E+19', 6, 'Alpha Mwirigi', 'pharmacy', 'PHARM-0042', 'Pharmacy Charges', 250.00, 'mpesa', '', 'completed', 55, 'Vivian chepchumba', '2026-01-10 00:10:48', '2026-01-09 21:10:48'),
(5, 'RCPT-000005', 10, 'Christopher Mwasi', 'consultation', '129', 'Consultation Fee', 500.00, 'cash', '', 'completed', 0, NULL, '2026-01-20 23:36:54', '2026-01-20 20:36:54'),
(6, 'RCPT-000005', 32, 'Brianna Wambui', 'consultation', '128', 'Consultation Fee', 500.00, 'cash', '', 'completed', 0, NULL, '2026-01-20 23:38:12', '2026-01-20 20:38:12'),
(7, 'RCPT-000005', 16, 'Kelvin Mwasi', 'consultation', '130', 'Consultation Fee', 500.00, 'cash', '', 'completed', 0, NULL, '2026-01-21 00:12:08', '2026-01-20 21:12:08'),
(8, 'RCPT-000005', 16, 'Kelvin Mwasi', 'pharmacy', NULL, 'Pharmacy Charges', 1700.00, 'cash', '', 'completed', 0, NULL, '2026-01-21 00:12:08', '2026-01-20 21:12:08'),
(9, 'RCPT-000005', 16, 'Kelvin Mwasi', 'lab', NULL, 'Laboratory Tests', 800.00, 'cash', '', 'completed', 0, NULL, '2026-01-21 00:12:08', '2026-01-20 21:12:08'),
(10, 'RCPT-000005', 7, 'Susan Chemutai', 'consultation', '131', 'Consultation Fee', 500.00, 'cash', '', 'completed', 0, NULL, '2026-01-21 12:34:48', '2026-01-21 09:34:48'),
(11, 'RCPT-000005', 7, 'Susan Chemutai', 'pharmacy', NULL, 'Pharmacy Charges', 1500.00, 'cash', '', 'completed', 0, NULL, '2026-01-21 12:34:48', '2026-01-21 09:34:48'),
(12, 'RCPT-000005', 7, 'Susan Chemutai', 'lab', NULL, 'Laboratory Tests', 550.00, 'cash', '', 'completed', 0, NULL, '2026-01-21 12:34:48', '2026-01-21 09:34:48'),
(13, 'RCPT-000013', 33, 'Nadia Mutana', 'consultation', '132', 'Consultation Fee', 500.00, 'cash', '', 'completed', 0, NULL, '2026-01-21 15:23:50', '2026-01-21 12:23:50'),
(14, 'RCPT-000013', 33, 'Nadia Mutana', 'pharmacy', NULL, 'Pharmacy Charges', 1350.00, 'cash', '', 'completed', 0, NULL, '2026-01-21 15:23:50', '2026-01-21 12:23:50'),
(15, 'RCPT-000013', 33, 'Nadia Mutana', 'lab', NULL, 'Laboratory Tests', 800.00, 'cash', '', 'completed', 0, NULL, '2026-01-21 15:23:50', '2026-01-21 12:23:50'),
(16, 'RCPT-000013', 33, 'Nadia Mutana', 'admission', NULL, 'Hospital Admission Charges', 1000.00, 'cash', '', 'completed', 0, NULL, '2026-01-21 15:23:50', '2026-01-21 12:23:50'),
(17, 'RCPT-000017', 9, 'Hannah Nyamwange', 'consultation', '133', 'Consultation Fee', 500.00, 'cash', '', 'completed', 0, NULL, '2026-01-22 22:55:12', '2026-01-22 19:55:12'),
(18, 'RCPT-000017', 9, 'Hannah Nyamwange', 'pharmacy', NULL, 'Pharmacy Charges', 1700.00, 'cash', '', 'completed', 0, NULL, '2026-01-22 22:55:12', '2026-01-22 19:55:12'),
(19, 'RCPT-000017', 9, 'Hannah Nyamwange', 'lab', NULL, 'Laboratory Tests', 800.00, 'cash', '', 'completed', 0, NULL, '2026-01-22 22:55:12', '2026-01-22 19:55:12'),
(20, 'RCPT-000020', 12, 'Phoebe Ndalo', 'pharmacy', NULL, 'Pharmacy Charges', 375.00, 'cash', '', 'completed', 0, NULL, '2026-02-05 17:20:46', '2026-02-05 14:20:46'),
(21, 'RCPT-000021', 15, 'Ria Kumari', 'consultation', '134', 'Consultation Fee', 500.00, 'cash', '', 'completed', 0, NULL, '2026-02-05 21:42:56', '2026-02-05 18:42:56'),
(22, 'RCPT-000021', 15, 'Ria Kumari', 'pharmacy', NULL, 'Pharmacy Charges', 2200.00, 'cash', '', 'completed', 0, NULL, '2026-02-05 21:42:56', '2026-02-05 18:42:56'),
(23, 'RCPT-000021', 15, 'Ria Kumari', 'lab', NULL, 'Laboratory Tests', 700.00, 'cash', '', 'completed', 0, NULL, '2026-02-05 21:42:56', '2026-02-05 18:42:56'),
(24, 'RCPT-000024', 32, 'Brianna Wambui', 'consultation', '140', 'Consultation Fee', 500.00, 'mpesa', '', 'completed', 0, NULL, '2026-02-06 11:55:04', '2026-02-06 08:55:04'),
(25, 'RCPT-000024', 32, 'Brianna Wambui', 'pharmacy', NULL, 'Pharmacy Charges', 2350.00, 'mpesa', '', 'completed', 0, NULL, '2026-02-06 11:55:04', '2026-02-06 08:55:04'),
(26, 'RCPT-000024', 32, 'Brianna Wambui', 'lab', NULL, 'Laboratory Tests', 550.00, 'mpesa', '', 'completed', 0, NULL, '2026-02-06 11:55:04', '2026-02-06 08:55:04'),
(27, 'RCPT-000027', 31, 'Achieng Odwour', 'consultation', '143', 'Consultation Fee', 500.00, 'cash', '', 'completed', 0, NULL, '2026-02-06 13:52:57', '2026-02-06 10:52:57'),
(28, 'RCPT-000027', 31, 'Achieng Odwour', 'pharmacy', NULL, 'Pharmacy Charges', 1700.00, 'cash', '', 'completed', 0, NULL, '2026-02-06 13:52:57', '2026-02-06 10:52:57'),
(29, 'RCPT-000029', 10, 'Christopher Mwasi', 'consultation', '146', 'Consultation Fee', 500.00, 'mpesa', '', 'completed', 0, NULL, '2026-02-06 14:56:08', '2026-02-06 11:56:08'),
(30, 'RCPT-000029', 10, 'Christopher Mwasi', 'pharmacy', NULL, 'Pharmacy Charges', 200.00, 'mpesa', '', 'completed', 0, NULL, '2026-02-06 14:56:08', '2026-02-06 11:56:08'),
(31, 'RCPT-000029', 10, 'Christopher Mwasi', 'lab', NULL, 'Laboratory Tests', 1200.00, 'mpesa', '', 'completed', 0, NULL, '2026-02-06 14:56:08', '2026-02-06 11:56:08'),
(32, 'RCPT-000032', 13, 'Sandra Kosgei', 'pharmacy', NULL, 'Pharmacy Charges', 1500.00, 'card', '', 'completed', 0, NULL, '2026-02-06 15:01:04', '2026-02-06 12:01:04'),
(33, 'RCPT-000032', 13, 'Sandra Kosgei', 'lab', NULL, 'Laboratory Tests', 800.00, 'card', '', 'completed', 0, NULL, '2026-02-06 15:01:04', '2026-02-06 12:01:04');

-- --------------------------------------------------------


--
-- Table structure for table `bill_items`
--

CREATE TABLE `bill_items` (
  `id` int(11) NOT NULL,
  `bill_id` int(11) NOT NULL,
  `item_description` varchar(255) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `item_type` enum('bed_charge','consultation','lab_test','medication','procedure','other') DEFAULT 'other',
  `department` varchar(50) DEFAULT NULL,
  `service_code` varchar(20) DEFAULT NULL,
  `service_date` date DEFAULT NULL,
  `appointment` int(11) DEFAULT NULL,
  `appointment_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bill_items`
--

INSERT INTO `bill_items` (`id`, `bill_id`, `item_description`, `quantity`, `unit_price`, `total_amount`, `item_type`, `department`, `service_code`, `service_date`, `appointment`, `appointment_id`) VALUES
(14, 9, 'Consultation Fee - Appointment #APT-20251031-077', 1, 500.00, 500.00, 'consultation', NULL, NULL, '2025-10-31', NULL, 19),
(34, 30, 'Consultation Fee - Appointment #APT-20260116111345-488', 1, 500.00, 500.00, 'consultation', NULL, NULL, '2026-01-16', NULL, 39),
(36, 32, 'Consultation Fee - Appointment #APT-20251031-077', 1, 500.00, 500.00, 'consultation', NULL, NULL, '2025-10-31', NULL, 19),
(38, 34, 'Consultation Fee - Appointment #APT-20260117-577', 1, 500.00, 500.00, 'consultation', NULL, NULL, '2026-01-17', NULL, 42),
(41, 37, 'Consultation Fee - Appointment #APT-20260117-453', 1, 500.00, 500.00, 'consultation', NULL, NULL, '2026-01-17', NULL, 44),
(43, 39, 'Consultation Fee - Appointment #APT-20260117-608', 1, 5000.00, 5000.00, 'consultation', NULL, NULL, '2026-01-17', NULL, 43),
(47, 43, 'Consultation Fee - Appointment #APT-20260117-414', 1, 500.00, 500.00, 'consultation', NULL, NULL, '2026-01-17', NULL, 47),
(51, 47, 'Consultation Fee - Appointment #APT-20260117-494', 1, 1200.00, 1200.00, 'consultation', NULL, NULL, '2026-01-17', NULL, 49),
(58, 54, 'Consultation Fee - Appointment #APT-20260117-025', 1, 1200.00, 1200.00, 'consultation', NULL, NULL, '2026-01-17', NULL, 50),
(59, 55, 'Consultation Fee - Appointment #APT-20260117114111-156', 1, 1200.00, 1200.00, 'consultation', NULL, NULL, '2026-01-17', NULL, 48),
(62, 58, 'Consultation Fee - Appointment #APT-20260117-431', 1, 500.00, 500.00, 'consultation', NULL, NULL, '2026-01-17', NULL, 51),
(64, 60, 'Consultation Fee - Appointment #APT-20251031-077', 1, 500.00, 500.00, 'consultation', NULL, NULL, '2025-10-31', NULL, 19),
(65, 61, 'Consultation Fee - Appointment #APT-20260118-133', 1, 1200.00, 1200.00, 'consultation', NULL, NULL, '2026-01-18', NULL, 52),
(68, 64, 'Consultation Fee - Appointment #APT-20260119-295', 1, 500.00, 500.00, 'consultation', NULL, NULL, '2026-01-19', NULL, 54),
(70, 66, 'Consultation Fee - Appointment #APT-20260119-013', 1, 1200.00, 1200.00, 'consultation', NULL, NULL, '2026-01-19', NULL, 53),
(73, 69, 'Consultation Fee - Appointment #APT-20260119-849', 1, 500.00, 500.00, 'consultation', NULL, NULL, '2026-01-20', NULL, 55),
(149, 0, 'Consultation Fee - Appointment #APT-20260120-046', 1, 500.00, 500.00, 'consultation', NULL, NULL, '2026-01-20', NULL, 0),
(150, 0, 'General Consultation - Consultation', 1, 500.00, 500.00, 'consultation', NULL, NULL, '2026-01-20', NULL, 0),
(151, 0, 'Consultation Fee - Appointment #APT-20260120-447', 1, 500.00, 500.00, 'consultation', NULL, NULL, '2026-01-20', NULL, 129),
(152, 0, 'General Consultation - Consultation', 1, 500.00, 500.00, 'consultation', NULL, NULL, '2026-01-20', NULL, 0),
(153, 0, 'General Consultation - Consultation', 1, 500.00, 500.00, 'consultation', NULL, NULL, '2026-01-21', NULL, 0),
(154, 0, 'Bed Charges - Standard (1 days @ Ksh 1,000.00/day)', 1, 1000.00, 1000.00, 'bed_charge', NULL, NULL, '2026-01-21', NULL, 0),
(155, 0, 'General Consultation - Consultation', 1, 500.00, 500.00, 'consultation', NULL, NULL, '2026-01-21', NULL, 0),
(156, 0, 'Bed Charges - Standard (1 days @ Ksh 1,000.00/day)', 1, 1000.00, 1000.00, 'bed_charge', NULL, NULL, '2026-01-21', NULL, 0),
(157, 0, 'General Consultation - Consultation', 1, 500.00, 500.00, 'consultation', NULL, NULL, '2026-01-22', NULL, 0),
(158, 0, 'General Consultation - Consultation', 1, 500.00, 500.00, 'consultation', NULL, NULL, '2026-02-06', NULL, 0),
(159, 0, 'General Consultation - Consultation', 1, 500.00, 500.00, 'consultation', NULL, NULL, '2026-02-06', NULL, 0),
(160, 0, 'General Consultation - Consultation', 1, 500.00, 500.00, 'consultation', NULL, NULL, '2026-02-06', NULL, 0),
(161, 0, 'General Consultation - Consultation', 1, 500.00, 500.00, 'consultation', NULL, NULL, '2026-02-06', NULL, 0),
(162, 0, 'General Consultation - Consultation', 1, 500.00, 500.00, 'consultation', NULL, NULL, '2026-02-06', NULL, 0);

--
-- Triggers `bill_items`
--
DELIMITER $$
CREATE TRIGGER `update_bill_total_after_item_delete` AFTER DELETE ON `bill_items` FOR EACH ROW BEGIN
    DECLARE bill_total DECIMAL(12,2);
    DECLARE bill_paid DECIMAL(12,2);
    DECLARE billing_exists INT;
    
    -- Check if billing record exists
    SELECT COUNT(*) INTO billing_exists
    FROM billing 
    WHERE id = OLD.bill_id;
    
    IF billing_exists > 0 THEN
        SELECT COALESCE(SUM(total_amount), 0) INTO bill_total
        FROM bill_items 
        WHERE bill_id = OLD.bill_id;
        
        SELECT COALESCE(paid_amount, 0) INTO bill_paid
        FROM billing 
        WHERE id = OLD.bill_id
        LIMIT 1;
        
        UPDATE billing 
        SET total_amount = bill_total,
            balance_amount = bill_total - bill_paid,
            status = CASE 
                WHEN bill_total <= bill_paid THEN 'paid'
                WHEN bill_paid > 0 THEN 'partial'
                ELSE 'pending'
            END,
            updated_at = NOW()
        WHERE id = OLD.bill_id;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_bill_total_after_item_insert` AFTER INSERT ON `bill_items` FOR EACH ROW BEGIN
    DECLARE bill_total DECIMAL(12,2);
    DECLARE bill_paid DECIMAL(12,2);
    DECLARE billing_exists INT;
    
    -- Check if billing record exists
    SELECT COUNT(*) INTO billing_exists
    FROM billing 
    WHERE id = NEW.bill_id;
    
    IF billing_exists > 0 THEN
        SELECT COALESCE(SUM(total_amount), 0) INTO bill_total
        FROM bill_items 
        WHERE bill_id = NEW.bill_id;
        
        SELECT COALESCE(paid_amount, 0) INTO bill_paid
        FROM billing 
        WHERE id = NEW.bill_id
        LIMIT 1;
        
        UPDATE billing 
        SET total_amount = bill_total,
            balance_amount = bill_total - bill_paid,
            status = CASE 
                WHEN bill_total <= bill_paid THEN 'paid'
                WHEN bill_paid > 0 THEN 'partial'
                ELSE 'pending'
            END,
            updated_at = NOW()
        WHERE id = NEW.bill_id;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_bill_total_after_item_update` AFTER UPDATE ON `bill_items` FOR EACH ROW BEGIN
    DECLARE bill_total DECIMAL(12,2);
    DECLARE bill_paid DECIMAL(12,2);
    DECLARE billing_exists INT;
    
    -- Check if billing record exists
    SELECT COUNT(*) INTO billing_exists
    FROM billing 
    WHERE id = NEW.bill_id;
    
    IF billing_exists > 0 THEN
        SELECT COALESCE(SUM(total_amount), 0) INTO bill_total
        FROM bill_items 
        WHERE bill_id = NEW.bill_id;
        
        SELECT COALESCE(paid_amount, 0) INTO bill_paid
        FROM billing 
        WHERE id = NEW.bill_id
        LIMIT 1;
        
        UPDATE billing 
        SET total_amount = bill_total,
            balance_amount = bill_total - bill_paid,
            status = CASE 
                WHEN bill_total <= bill_paid THEN 'paid'
                WHEN bill_paid > 0 THEN 'partial'
                ELSE 'pending'
            END,
            updated_at = NOW()
        WHERE id = NEW.bill_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `clinical_notes`
--

CREATE TABLE `clinical_notes` (
  `id` int(11) NOT NULL,
  `patient_id` varchar(20) DEFAULT NULL,
  `doctor_id` int(10) UNSIGNED NOT NULL,
  `subjective` text DEFAULT NULL,
  `objective` text DEFAULT NULL,
  `assessment` text DEFAULT NULL,
  `plan` text DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `prescription` text DEFAULT NULL,
  `treatment_plan` text DEFAULT NULL,
  `symptoms` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `provider_name` varchar(100) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clinical_notes`
--

INSERT INTO `clinical_notes` (`id`, `patient_id`, `doctor_id`, `subjective`, `objective`, `assessment`, `plan`, `follow_up_date`, `prescription`, `treatment_plan`, `symptoms`, `diagnosis`, `provider_name`, `appointment_id`, `created_at`) VALUES
(1, '8', 55, 'runny nose, itchy throat and irritative coughs', 'flu and an infection', 'Has an infection, which manifested as a flu.', 'some antibiotics and cough syrup.', NULL, NULL, NULL, NULL, NULL, 'Dr. Unknown', NULL, '2025-10-30 17:13:27'),
(2, '8', 55, 'runny nose, itchy throat and irritative coughs', 'flu and an infection', 'Has an infection, which manifested as a flu.', 'some antibiotics and cough syrup.', NULL, NULL, NULL, NULL, NULL, 'Dr. Unknown', NULL, '2025-10-30 17:13:49'),
(3, '8', 55, 'runny nose, itchy throat and irritative coughs', 'flu and an infection', 'Has an infection, which manifested as a flu.', 'some antibiotics and cough syrup.', NULL, NULL, NULL, NULL, NULL, 'Dr. Unknown', NULL, '2025-10-30 17:14:20'),
(6, '11', 55, 'severe stomach pains and vomiting', 'has been vomiting for the past two days, started after having street food 3 days ago.', 'Suspected food poisoning', 'Eno, painkillers and some antibiotics.', NULL, NULL, NULL, NULL, NULL, 'Dr. Unknown', NULL, '2025-10-30 17:33:59'),
(7, '11', 55, 'severe stomach pains and vomiting', 'has been vomiting for the past two days, started after having street food 3 days ago.', 'Suspected food poisoning', 'Eno, painkillers and some antibiotics.', NULL, NULL, NULL, NULL, NULL, 'Dr. Unknown', NULL, '2025-10-30 17:35:01'),
(8, '7', 69, 'joint pains on the knees and wrists', 'Elevated cholesterol levels and uric acid', 'Gout is suspected', 'Pain killers and dieting', NULL, NULL, NULL, NULL, NULL, 'Dr. Unknown', NULL, '2025-10-30 20:37:06'),
(9, '10', 69, 'joint pain and flu symptoms. Runny nose, coughing and sore throat', 'Suspected gout flare up due to increased uric acid levels. To labs to confirm before proceeding with treatment plans.', '', '', NULL, NULL, NULL, NULL, NULL, 'Dr. Unknown', NULL, '2025-10-31 17:38:22'),
(10, '10', 69, '', '', 'Confirmed hyperuricemia and mild infection.', 'Put on some antibiotics and painkillers', NULL, NULL, NULL, NULL, NULL, 'Dr. Unknown', NULL, '2025-10-31 19:46:33'),
(11, '12', 63, 'Experiencing joint and muscle pains, shivers all over the body and has been feverish for the past week.', '', '', '', NULL, NULL, NULL, NULL, NULL, 'Dr. Unknown', NULL, '2025-11-02 11:07:04'),
(12, '12', 69, '', 'Elevated RBC count, has a blood infection', 'Infection, to be put on some antibiotics.', 'Augmentin(1 * 3)', NULL, NULL, NULL, NULL, NULL, 'Dr. Unknown', NULL, '2025-11-02 13:39:43'),
(13, '11', 59, 'light headed, hasn\'t eaten for two days, is also puking blood', '', '', '', NULL, NULL, NULL, NULL, NULL, 'Dr. Unknown', NULL, '2025-11-02 18:42:07'),
(14, '11', 59, '', 'Extremely elevated RBC count. Indicates extreme blood infection.\r\nHigh PH levels in the urine and clouded, signifies a bacterial infection.', 'Suspected gastritis and infection.', 'To be admitted for observation.', NULL, NULL, NULL, NULL, NULL, 'Dr. Unknown', NULL, '2025-11-02 18:52:51'),
(15, '2', 55, 'runny nose, sore throat and irritative coughs.', '', '', '', NULL, NULL, NULL, NULL, NULL, 'Dr. Unknown', NULL, '2025-11-03 20:50:40'),
(16, '2', 55, '', 'has an infection. ', 'Final diagnosis is flu and infection.', 'put on antibiotics and cough syrup', NULL, NULL, NULL, NULL, NULL, 'Dr. Unknown', NULL, '2025-11-03 20:53:44'),
(17, '12', 69, 'runny nose, itchy and sore throat and coughs, coupled with pounding headaches', 'Elevated RBC count, indicating infection.', 'Blood infection and flu.', 'to be put on some antibiotics, cough syrup and painkiller to help with the headaches.', NULL, NULL, NULL, NULL, NULL, 'Dr. Unknown', NULL, '2025-11-04 07:39:05'),
(18, '13', 55, 'stomach pains and headaches.', '', '', '', NULL, NULL, NULL, NULL, NULL, 'Dr. Unknown', NULL, '2025-11-06 08:29:47'),
(19, '13', 55, '', 'elevated RBC count and contaminated stool sample, signals bacterial infection from food poisoning.', 'blood infection and food poisoning.', 'Antibiotics and painkillers.', NULL, NULL, NULL, NULL, NULL, 'Dr. Unknown', NULL, '2025-11-06 08:34:20'),
(20, '14', 69, 'tension around temples and bad headaches for 3 days now.', '', '', '', NULL, NULL, NULL, NULL, NULL, 'Dr. Unknown', NULL, '2025-11-14 07:04:38'),
(21, '14', 69, '', 'slightly elevated RBC count.', 'blood infection', 'antibiotics and painkillers', NULL, NULL, NULL, NULL, NULL, 'Dr. Unknown', NULL, '2025-11-14 07:09:25'),
(22, '8', 59, 'Stomach pains, vomiting and severe headaches/tension', 'Has a blood and stomach infection.', 'Infection', 'To be put on antibiotics and painkiller', NULL, NULL, NULL, NULL, NULL, 'Dr. Unknown', NULL, '2026-01-09 09:04:55'),
(23, '6', 55, 'headaches and tension on temples', 'stress induced headaches', 'headaches', 'mara moja', NULL, NULL, NULL, NULL, NULL, 'Dr. Unknown', NULL, '2026-01-09 20:52:37'),
(24, '14', 69, 'pre-diabetic, with a history of hypoglycemic episodes', 'Fainted due to low blood sugar levels', 'Hypoglycemic', 'To be put on glucose drip and be admitted for observation', NULL, NULL, NULL, NULL, NULL, 'Dr. Unknown', NULL, '2026-01-09 21:23:04'),
(25, '6', 69, 'severe migraines and runny nose', 'Stress induced headaches and allergies', 'Allergies and stress', 'Mara Moja and celestamine for the allergies', NULL, NULL, NULL, NULL, NULL, 'Dr.Collins', NULL, '2026-01-15 11:08:53'),
(26, '14', 60, 'allergies and constipation', 'Irritated bowels ', 'IBS', 'Celestamine and laxatives', NULL, NULL, NULL, NULL, NULL, 'Dr.Nyamal', NULL, '2026-01-15 11:31:51'),
(27, '15', 55, 'headaches', 'headaches', 'headaches', 'panadol', NULL, NULL, NULL, NULL, NULL, 'Dr. Unknown', NULL, '2026-01-15 12:19:30'),
(28, '9', 67, 'Patient has been experiencing flu like symptoms for the past 5 days. Itchy and sore throat, runny nose and coughing.', 'Elevated RBC count.', 'Blood infection', 'Antibiotics and cough syrup.', NULL, NULL, NULL, NULL, NULL, 'Dr. Unknown', 35, '2026-01-15 20:49:16'),
(29, '2', 69, 'flu', 'blood infection', 'blood infection', 'antibiotics', NULL, NULL, NULL, NULL, NULL, 'Dr.Collins', NULL, '2026-01-16 08:08:30'),
(30, '2', 69, 'flu', 'blood infection', 'blood infection', 'antibiotics', NULL, NULL, NULL, NULL, NULL, 'Dr.Collins', NULL, '2026-01-16 08:20:06'),
(31, '2', 69, 'flu', 'blood infection', 'blood infection', 'antibiotics', NULL, NULL, NULL, NULL, NULL, 'Dr.Collins', 2, '2026-01-16 08:20:40'),
(32, '7', 60, 'Nausea, body aches, yellow eyes and diarrhea', 'Yellow eyes, indicates onset of jaundice', 'Elevated RBC counts and contaminated stool sample, has bacterial and blood infection', 'Antibiotics', NULL, NULL, NULL, NULL, NULL, 'Dr.Nyamal', NULL, '2026-01-16 10:24:03'),
(33, '10', 59, 'Headaches and tension on temples.', 'Raised pressure, elevated RBC', 'Mild infection and stress induced headaches', 'Antibiotics and Painkillers', NULL, NULL, NULL, NULL, NULL, 'Dr. Unknown', NULL, '2026-01-16 21:00:21'),
(34, '12', 69, 'vomiting and body aches', 'Elevated RBC count and concentrated urine', 'Has a mild blood infection and UTI', 'Antibiotics and painkillers', NULL, NULL, NULL, NULL, NULL, 'Dr.Collins', NULL, '2026-01-17 07:33:15'),
(35, '12', 69, 'vomiting and body aches', 'Elevated RBC count and concentrated urine', 'Has a mild blood infection and UTI', 'Antibiotics and painkillers', NULL, NULL, NULL, NULL, NULL, 'Dr.Collins', NULL, '2026-01-17 07:33:31'),
(36, '15', 67, 'migraines', 'headaches', 'headaches', 'painkillers', NULL, NULL, NULL, NULL, NULL, 'Dr. Cate', NULL, '2026-01-17 07:43:38'),
(37, '1', 67, 'Severe difficulty breathing\r\n\r\nWheezing\r\n\r\nChest tightness\r\n\r\nRapid breathing\r\n\r\nInability to speak full sentences', 'Elevated Vitals, known asthmatic', 'Acute severe asthma attack', 'Admission to be put on oxygen tank treatment', NULL, NULL, NULL, NULL, NULL, 'Dr. Cate', NULL, '2026-01-17 08:31:00'),
(38, '11', 59, 'Persistent vomiting (2 days)\r\n\r\nFever\r\n\r\nGeneral body weakness\r\n\r\nLoss of appetite', 'Raised RBC count and low blood sugar levels', 'Malaria', 'Antibiotics (Agumentin 1500mg) for the infection', NULL, NULL, NULL, NULL, NULL, 'Dr.Dana', NULL, '2026-01-17 08:41:19'),
(39, '15', 59, 'Patient was brought in unconscious', 'Fainted due to dehydration and exhaustion', 'Dehydrated and Exhaustion', 'To be put on IV supplement drips and admitted for monitoring.', NULL, NULL, NULL, NULL, NULL, 'Dr.Dana', NULL, '2026-01-17 10:17:54'),
(40, '5', 55, 'Vomiting and diarrhea', 'Contaminated stool sample', 'Food poisoning', 'Agumentin', NULL, NULL, NULL, NULL, NULL, 'superadmin', NULL, '2026-01-17 10:25:36'),
(41, '6', 69, 'Heat stroke', 'Heat stroke', 'Dehydration which caused heat stroke', 'Supplement and Glucose IV drip treatment', NULL, NULL, NULL, NULL, NULL, 'Dr.Collins', NULL, '2026-01-17 10:43:41'),
(42, '8', 55, 'Difficulties breathing, chest pain and tightness and shortness of breath', 'Acute asthma attack', 'Asthma attack', 'To be put on oxygen therapy for 2 hours under observation', NULL, NULL, NULL, NULL, NULL, 'superadmin', NULL, '2026-01-17 13:17:43'),
(43, '2', 55, 'vomiting, diarrhea and fever', 'Raised RBC and contaminated stool sample', 'Food poisoning', 'Antibiotics', NULL, NULL, NULL, NULL, NULL, 'superadmin', NULL, '2026-01-17 14:10:44'),
(44, '14', 69, 'Difficulty breathing, shortness of breath and chest pains', 'acute panic attack', 'Panic attack', 'Oxygen therapy', NULL, NULL, NULL, NULL, NULL, 'Dr.Collins', NULL, '2026-01-17 16:10:01'),
(45, '10', 55, 'consult for medication refill', 'Raised blood pressure', 'On set of hypertension', 'Metroponol', NULL, NULL, NULL, NULL, NULL, 'superadmin', NULL, '2026-01-18 17:45:04'),
(46, '5', 67, 'Acute Asthma attack, patient was brought in with chest pains, shortness of breath and difficulties breathing.', 'On set of an asthma attack', 'Asthma attack', 'To be admitted and put on oxygen tank for regulated oxygen administration', NULL, NULL, NULL, NULL, NULL, 'Dr. Cate', NULL, '2026-01-19 10:49:03'),
(47, '12', 69, 'Body aches, feverish, vomiting and diarrhea', 'Raised RBC count, indicates infection, malaria', 'Malaria', 'Antibioitcs', NULL, NULL, NULL, NULL, NULL, 'Dr.Collins', NULL, '2026-01-19 11:26:57'),
(48, '2', 69, 'flu', 'flu', 'flu', 'antiniotics', NULL, NULL, NULL, NULL, NULL, 'Dr.Collins', NULL, '2026-01-19 21:07:48'),
(49, '15', 67, 'migraines', 'migraine', 'tension', 'panadol', NULL, NULL, NULL, NULL, NULL, 'Dr. Cate', NULL, '2026-01-19 22:04:29'),
(50, '4', 59, 'came in unconscious, suffered a heart attack', 'to be admitted for observations', 'heart failure', 'metropol and admission', NULL, NULL, NULL, NULL, NULL, 'Dr.Dana', NULL, '2026-01-19 22:24:17'),
(0, '15', 68, 'joint pains, swollen feet and difficulty walking', 'Raised uric levels', 'Arthritis flare up due to raised uric levels', 'Metoprolol and ibuprofen', NULL, NULL, NULL, NULL, NULL, 'Dr. Job', NULL, '2026-02-05 18:21:03'),
(0, '14', 55, 'body aches, feverish, headaches, vomiting for the past 2 days', 'Malaria', 'Blood infection/Malaria', 'Antibiotics', NULL, NULL, NULL, NULL, NULL, 'superadmin', NULL, '2026-02-05 18:45:31'),
(0, '32', 56, 'stomach aches, vomiting and diarrhea', 'Bacterial Infection', 'Food poisoning', 'Augmentin and Ibuprofen', NULL, NULL, NULL, NULL, NULL, 'staff', NULL, '2026-02-06 08:44:59'),
(0, '31', 55, 'flu like symptoms and chest pains', 'Bacterial Infection', 'Infection', 'Antibiotics', NULL, NULL, NULL, NULL, NULL, 'superadmin', NULL, '2026-02-06 10:51:52'),
(0, '13', 68, 'Joint pains and swollen feet. Difficulty walking', 'Raised blood pressure levels, tension', 'Arthritis flare up due to raised uric acid levels!', 'Metoprolol', NULL, NULL, NULL, NULL, NULL, 'Dr. Job', NULL, '2026-02-06 11:02:35'),
(0, '10', 55, 'routine checkup', 'No notes. Everything is okay', 'NO notes', 'Supplements', NULL, NULL, NULL, NULL, NULL, 'superadmin', NULL, '2026-02-06 11:52:36');

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
(1, 'Transchem Pharmaceutical'),
(2, 'VeePharmaceutical'),
(3, 'GracePharmacuticals'),
(4, 'MalPharmaceutical'),
(5, 'kimPharm'),
(6, 'MediPlugequipments'),
(7, '	MediCare Pharmaceuticals');

-- --------------------------------------------------------


--
-- Table structure for table `consolidated_payments`
--

CREATE TABLE `consolidated_payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `consolidated_receipt` varchar(50) NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `paid_amount` decimal(10,2) NOT NULL,
  `services_included` text DEFAULT NULL,
  `status` enum('partial','completed','overpaid') DEFAULT 'completed',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `consolidated_payments`
--

INSERT INTO `consolidated_payments` (`id`, `consolidated_receipt`, `patient_id`, `total_amount`, `paid_amount`, `services_included`, `status`, `created_by`, `created_at`) VALUES
(1, 'CONSRCPT-000001', 6, 750.00, 750.00, 'consultation:500,pharmacy:250', 'completed', 55, '2026-01-10 00:10:08'),
(2, 'CONSRCPT-000002', 6, 750.00, 750.00, 'consultation:500,pharmacy:250', 'completed', 55, '2026-01-10 00:10:48');

-- --------------------------------------------------------


--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `head_user_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `description`, `head_user_id`, `created_at`) VALUES
(2, 'Cardiology', 'Deals with heart related issues and cardiovascular system as a whole', 59, '2025-10-26 08:05:27'),
(3, 'Emergency', 'Deals with accidents and emergency cases', 60, '2025-10-26 08:05:49'),
(4, 'Neurology', 'Deals with the nervous systme', 64, '2025-10-26 08:06:45'),
(6, 'Pediatrics', 'Deals with child healthcare, for children aged 0 to 18 years.', 61, '2025-10-26 12:20:10'),
(7, 'Orthopedics', 'Deals with conditions that affect bones and muscles.', 68, '2025-10-27 07:54:16'),
(9, 'General Care', 'Facilitates consultations and general diagnosis', 69, '2025-10-30 13:21:48');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_profiles`
--

CREATE TABLE `doctor_profiles` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `specialty` varchar(150) DEFAULT NULL,
  `qualifications` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `consultation_fee` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctor_profiles`
--

INSERT INTO `doctor_profiles` (`id`, `user_id`, `specialty`, `qualifications`, `bio`, `consultation_fee`, `created_at`) VALUES
(1, 64, 'Neurology', NULL, NULL, 0.00, '2025-10-05 15:28:21'),
(2, 67, 'Child Care', NULL, NULL, 0.00, '2025-10-26 12:19:14'),
(3, 68, 'Orthopedics', NULL, NULL, 0.00, '2025-10-27 07:53:20'),
(4, 69, 'General Health Care', NULL, NULL, 0.00, '2025-10-30 12:21:16');

-- --------------------------------------------------------

--
-- Table structure for table `emergency_response_log`
--

CREATE TABLE `emergency_response_log` (
  `id` int(11) NOT NULL,
  `alert_id` int(11) NOT NULL,
  `action` enum('created','assigned','dispatched','arrived','treatment_started','transporting','completed','cancelled') NOT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `emergency_response_log`
--

INSERT INTO `emergency_response_log` (`id`, `alert_id`, `action`, `performed_by`, `details`, `created_at`) VALUES
(1, 2, 'dispatched', 55, 'Response team dispatched', '2026-01-17 22:42:33'),
(2, 2, 'dispatched', 55, 'Response team dispatched', '2026-01-17 22:43:30'),
(3, 3, 'dispatched', 55, 'Response team dispatched', '2026-01-17 23:51:49'),
(4, 4, 'dispatched', 55, 'Response team dispatched', '2026-01-19 09:35:14'),
(0, 0, 'dispatched', 55, 'Response team dispatched', '2026-01-22 23:17:14'),
(0, 0, 'dispatched', 55, 'Response team dispatched', '2026-02-01 22:15:31'),
(0, 9, 'dispatched', 55, 'Response team dispatched', '2026-02-04 11:57:54'),
(0, 10, 'dispatched', 55, 'Response team dispatched', '2026-02-05 17:49:48'),
(0, 11, 'dispatched', 55, 'Response team dispatched', '2026-02-05 21:37:52');

-- --------------------------------------------------------

--
-- Table structure for table `emergency_teams`
--

CREATE TABLE `emergency_teams` (
  `id` int(11) NOT NULL,
  `team_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `team_type` enum('medical','paramedic','nursing','security') DEFAULT 'medical',
  `department` varchar(100) DEFAULT NULL,
  `assigned_user_id` int(11) DEFAULT NULL,
  `team_leader_id` int(11) DEFAULT NULL,
  `team_members_count` int(11) DEFAULT 1,
  `current_status` enum('available','busy','off_duty','on_break') DEFAULT 'available',
  `response_time` varchar(50) DEFAULT NULL,
  `current_location` varchar(255) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `vehicle_number` varchar(50) DEFAULT NULL,
  `specializations` text DEFAULT NULL,
  `equipment` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `emergency_teams`
--

INSERT INTO `emergency_teams` (`id`, `team_name`, `description`, `team_type`, `department`, `assigned_user_id`, `team_leader_id`, `team_members_count`, `current_status`, `response_time`, `current_location`, `contact_number`, `vehicle_number`, `specializations`, `equipment`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Rapid Response Team A', NULL, 'paramedic', 'Emergency Department', NULL, NULL, 1, 'busy', NULL, '', '+254700123456', 'AMB-001', 'Cardiac, Trauma, Pediatric', NULL, 1, '2026-01-17 21:56:00', '2026-02-05 21:37:52'),
(2, 'Response Team B', NULL, 'medical', 'emergency', NULL, NULL, 1, 'on_break', NULL, '', '0780954321', 'AMB-002', 'Trauma, Accidents, Attacks', NULL, 1, '2026-01-18 00:01:38', '2026-02-05 21:37:28');

-- --------------------------------------------------------

--
-- Table structure for table `emergency_templates`
--

CREATE TABLE `emergency_templates` (
  `id` int(11) NOT NULL,
  `emergency_type` varchar(100) NOT NULL,
  `template_name` varchar(255) DEFAULT NULL,
  `recommended_response` text DEFAULT NULL,
  `required_equipment` text DEFAULT NULL,
  `medical_protocols` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `emergency_templates`
--

INSERT INTO `emergency_templates` (`id`, `emergency_type`, `template_name`, `recommended_response`, `required_equipment`, `medical_protocols`, `created_at`) VALUES
(1, 'Cardiac Emergency', 'Heart Attack/Stroke', '1. Call emergency services\n2. Administer aspirin if no allergies\n3. Monitor vitals\n4. Prepare defibrillator', NULL, NULL, '2026-01-17 21:32:20'),
(2, 'Respiratory Emergency', 'Asthma/COPD Attack', '1. Provide oxygen\n2. Administer bronchodilator\n3. Monitor oxygen saturation', NULL, NULL, '2026-01-17 21:32:20'),
(3, 'Diabetic Emergency', 'Hypoglycemia', '1. Check blood sugar\n2. Administer glucose\n3. Monitor consciousness', NULL, NULL, '2026-01-17 21:32:20'),
(4, 'Allergic Reaction', 'Anaphylaxis', '1. Administer epinephrine\n2. Monitor airway\n3. Prepare for intubation', NULL, NULL, '2026-01-17 21:32:20'),
(5, 'Trauma', 'Accident/Injury', '1. Control bleeding\n2. Immobilize injuries\n3. Monitor for shock', NULL, NULL, '2026-01-17 21:32:20');

-- --------------------------------------------------------


--
-- Table structure for table `lab_orders`
--

CREATE TABLE `lab_orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_number` varchar(50) DEFAULT NULL,
  `medical_record_id` int(10) UNSIGNED DEFAULT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `ordered_by` int(10) UNSIGNED NOT NULL,
  `collected_by` int(10) UNSIGNED DEFAULT NULL,
  `tested_by` int(10) UNSIGNED DEFAULT NULL,
  `verified_by` int(10) UNSIGNED DEFAULT NULL,
  `order_date` datetime DEFAULT current_timestamp(),
  `collected_at` datetime DEFAULT NULL,
  `tested_at` datetime DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `notification_sent` tinyint(1) DEFAULT 0,
  `priority` enum('routine','urgent','stat') DEFAULT 'routine',
  `clinical_notes` text DEFAULT NULL,
  `status` enum('ordered','in_progress','completed','cancelled') DEFAULT 'ordered',
  `payment_status` enum('unpaid','partial','paid') DEFAULT 'unpaid',
  `paid_amount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_orders`
--

INSERT INTO `lab_orders` (`id`, `order_number`, `medical_record_id`, `patient_id`, `ordered_by`, `collected_by`, `tested_by`, `verified_by`, `order_date`, `collected_at`, `tested_at`, `verified_at`, `notification_sent`, `priority`, `clinical_notes`, `status`, `payment_status`, `paid_amount`, `total_amount`, `completed_at`) VALUES
(7, 'LO-20251030-541', NULL, 7, 55, 55, 55, NULL, '2025-10-30 15:30:52', '2025-10-30 13:33:00', '2025-10-30 13:34:00', NULL, 0, 'routine', 'blood test', 'completed', 'paid', 1600.00, 800.00, '2025-10-30 15:37:11'),
(8, 'LO-20251030-582', NULL, 8, 55, 55, 55, NULL, '2025-10-30 20:24:48', '2025-10-30 18:25:00', '2025-10-30 18:25:00', NULL, 0, 'routine', 'Food poisoning is suspected.', 'completed', 'paid', 800.00, 800.00, '2025-10-30 20:26:04'),
(9, 'LO-20251030-391', NULL, 11, 55, 55, 55, NULL, '2025-10-30 20:38:12', '2025-10-30 18:38:00', '2025-10-30 18:38:00', NULL, 0, 'routine', 'stool sample test to check for bacteria in fecal matter', 'completed', 'paid', 550.00, 550.00, '2025-10-30 20:39:11'),
(11, 'LO-20251030-301', NULL, 7, 55, 55, 55, NULL, '2025-10-31 00:19:03', '2025-10-30 22:19:00', '2025-10-30 22:19:00', NULL, 0, 'routine', 'extreme joint pains and difficulty walking.', 'completed', 'paid', 1300.00, 1300.00, '2025-10-31 00:20:39'),
(12, 'LO-20251031-617', NULL, 10, 69, 73, 73, NULL, '2025-10-31 21:02:25', '2025-10-31 20:38:00', '2025-10-31 20:39:00', NULL, 0, 'routine', '', 'completed', 'paid', 1500.00, 1500.00, '2025-10-31 22:40:54'),
(13, 'LO-20251102142117-193', NULL, 12, 69, 66, 66, NULL, '2025-11-02 16:21:17', '2025-11-02 14:33:00', '2025-11-02 14:33:00', NULL, 0, 'routine', '', 'completed', 'paid', 4000.00, 800.00, '2025-11-02 16:33:53'),
(14, 'LO-20251102194339-143', NULL, 11, 59, 66, 66, NULL, '2025-11-02 21:43:39', '2025-11-02 19:44:00', '2025-11-02 19:44:00', NULL, 0, 'stat', 'vomiting blood, do lab tests immediately.', 'completed', 'paid', 2400.00, 2400.00, '2025-11-02 21:49:56'),
(15, 'LO-20251103215058-135', NULL, 2, 55, 55, 55, NULL, '2025-11-03 23:50:58', '2025-11-03 21:51:00', '2025-11-03 21:51:00', NULL, 0, 'routine', '', 'completed', 'paid', 1600.00, 800.00, '2025-11-03 23:51:47'),
(16, 'LO-20251104083924-260', NULL, 12, 69, 66, 66, NULL, '2025-11-04 10:39:24', '2025-11-04 08:40:00', '2025-11-04 08:40:00', NULL, 0, 'routine', '', 'completed', 'paid', 800.00, 800.00, '2025-11-04 10:41:18'),
(17, 'LO-20251106093034-443', NULL, 13, 55, 55, 55, NULL, '2025-11-06 11:30:34', '2025-11-06 09:31:00', '2025-11-06 09:31:00', NULL, 0, 'routine', 'suspected food poisoning', 'completed', 'paid', 1350.00, 1350.00, '2025-11-06 11:32:42'),
(18, 'LO-20251114080518-152', NULL, 14, 69, 66, 66, NULL, '2025-11-14 10:05:18', '2025-11-14 08:06:00', '2025-11-14 08:06:00', NULL, 0, 'routine', '', 'completed', 'paid', 800.00, 800.00, '2025-11-14 10:07:16'),
(19, 'LO-20260109095802-658', NULL, 8, 59, 66, 66, NULL, '2026-01-09 11:58:02', '2026-01-09 10:00:00', '2026-01-09 10:00:00', NULL, 0, 'routine', 'Experiencing severe stomach pains and vomiting.', 'completed', 'paid', 1350.00, 1350.00, '2026-01-09 12:02:03'),
(20, 'LO-20260115214508-742', NULL, 9, 67, 66, 66, NULL, '2026-01-15 23:45:08', '2026-01-15 21:45:00', '2026-01-15 21:45:00', NULL, 0, 'routine', '', 'completed', 'paid', 800.00, 800.00, '2026-01-15 23:47:25'),
(21, 'LO-20260116111718-942', NULL, 7, 60, 66, 66, NULL, '2026-01-16 13:17:18', '2026-01-16 11:19:00', '2026-01-16 11:19:00', NULL, 0, 'routine', '', 'completed', 'paid', 2350.00, 2350.00, '2026-01-16 14:01:17'),
(22, 'LO-20260116215647-566', NULL, 10, 59, 66, 66, NULL, '2026-01-16 23:56:47', '2026-01-16 21:57:00', '2026-01-16 21:57:00', NULL, 0, 'routine', '', 'completed', 'paid', 1950.00, 1950.00, '2026-01-17 00:05:20'),
(23, 'LO-20260117082924-449', NULL, 12, 69, 66, 66, NULL, '2026-01-17 10:29:24', '2026-01-17 08:30:00', '2026-01-17 08:31:00', NULL, 0, 'routine', '', 'completed', 'paid', 1400.00, 1400.00, '2026-01-17 11:52:14'),
(24, 'LO-20260117093619-914', NULL, 11, 59, 66, 66, NULL, '2026-01-17 11:36:19', '2026-01-17 09:36:00', '2026-01-17 09:36:00', NULL, 0, 'routine', '', 'completed', 'paid', 1200.00, 1200.00, '2026-01-17 11:44:51'),
(25, 'LO-20260117112550-053', NULL, 5, 55, 55, 55, NULL, '2026-01-17 13:25:50', '2026-01-17 11:25:00', '2026-01-17 11:26:00', NULL, 0, 'routine', '', 'completed', 'paid', 550.00, 550.00, '2026-01-17 13:29:04'),
(26, 'LO-20260117151125-828', NULL, 2, 55, 55, 55, NULL, '2026-01-17 17:11:25', '2026-01-17 15:11:00', '2026-01-17 15:11:00', NULL, 0, 'routine', '', 'completed', 'paid', 550.00, 550.00, '2026-01-17 17:47:27'),
(27, 'LO-20260119120235-812', NULL, 12, 69, 66, 66, NULL, '2026-01-19 14:02:35', '2026-01-19 12:25:00', '2026-01-19 12:25:00', NULL, 0, 'routine', '', 'completed', 'paid', 800.00, 800.00, '2026-01-19 14:38:58'),
(28, 'LO-20260119232434-439', NULL, 4, 59, 66, 66, NULL, '2026-01-20 01:24:34', '2026-01-20 14:10:00', '2026-02-05 19:13:00', NULL, 0, 'stat', '', 'completed', 'paid', 800.00, 800.00, '2026-02-05 21:13:50'),
(29, 'LO-20260120111124-049', NULL, 16, 64, 66, 66, NULL, '2026-01-20 13:11:24', '2026-01-20 11:39:00', '2026-02-05 19:13:00', NULL, 0, 'routine', '', 'completed', 'paid', 0.00, 800.00, '2026-02-05 21:13:59'),
(51, 'LO-20260120114404-448', NULL, 16, 64, 66, 66, NULL, '2026-01-20 13:44:04', '2026-01-20 11:44:00', '2026-01-20 11:44:00', NULL, 0, 'routine', '', 'completed', 'paid', 800.00, 800.00, '2026-01-20 13:45:08'),
(52, 'LO-20260120120035-074', NULL, 31, 60, 66, 66, NULL, '2026-01-20 14:00:35', '2026-01-20 12:09:00', '2026-01-20 12:09:00', NULL, 0, 'routine', '', 'completed', 'paid', 800.00, 800.00, '2026-01-20 14:10:02'),
(53, 'LO-20260120140857-033', NULL, 15, 69, 66, 66, NULL, '2026-01-20 16:08:57', '2026-01-20 14:10:00', '2026-01-20 14:11:00', NULL, 0, 'routine', '', 'completed', 'paid', 400.00, 400.00, '2026-01-20 16:11:27'),
(54, 'LO-20260120194437-193', NULL, 32, 60, 66, 66, NULL, '2026-01-20 21:44:37', '2026-01-20 19:45:00', '2026-01-20 19:45:00', NULL, 0, 'routine', '', 'completed', 'paid', 1200.00, 1200.00, '2026-01-20 23:06:32'),
(55, 'LO-20260120212911-348', NULL, 7, 55, 55, 55, NULL, '2026-01-20 23:29:11', '2026-01-20 21:29:00', '2026-01-20 21:29:00', NULL, 0, 'routine', '', 'completed', 'paid', 0.00, 550.00, '2026-01-20 23:29:54'),
(56, 'LO-20260120214913-072', NULL, 16, 55, 55, 55, NULL, '2026-01-20 23:49:13', '2026-01-20 21:49:00', '2026-01-20 21:49:00', NULL, 0, 'routine', '', 'completed', 'paid', 0.00, 800.00, '2026-01-20 23:49:56'),
(57, 'LO-20260121125001-287', NULL, 33, 61, 66, 66, NULL, '2026-01-21 14:50:01', '2026-01-21 12:55:00', '2026-01-21 13:00:00', NULL, 0, 'routine', '', 'completed', 'paid', 0.00, 800.00, '2026-01-21 15:01:43'),
(58, 'LO-20260122205201-464', NULL, 9, 55, 55, 55, NULL, '2026-01-22 22:52:01', '2026-01-22 20:52:00', '2026-01-22 20:52:00', NULL, 0, 'routine', '', 'completed', 'paid', 0.00, 800.00, '2026-01-22 22:52:43'),
(59, 'LO-20260205190826-186', NULL, 15, 68, 66, 66, NULL, '2026-02-05 21:08:26', '2026-02-05 19:12:00', '2026-02-05 19:12:00', NULL, 0, 'routine', '', 'completed', 'paid', 0.00, 700.00, '2026-02-05 21:13:15'),
(60, 'LO-20260206094632-879', NULL, 32, 55, 55, 55, NULL, '2026-02-06 11:46:32', '2026-02-06 09:47:00', '2026-02-06 09:47:00', NULL, 0, 'routine', '', 'completed', 'paid', 0.00, 550.00, '2026-02-06 11:47:47'),
(61, 'LO-20260206120300-083', NULL, 13, 68, 66, 66, NULL, '2026-02-06 14:03:00', '2026-02-06 12:03:00', '2026-02-06 12:03:00', NULL, 0, 'routine', '', 'completed', 'paid', 0.00, 800.00, '2026-02-06 14:04:19'),
(62, 'LO-20260206125254-023', NULL, 10, 55, 55, 55, NULL, '2026-02-06 14:52:54', '2026-02-06 12:53:00', '2026-02-06 12:53:00', NULL, 0, 'routine', '', 'completed', 'paid', 0.00, 1200.00, '2026-02-06 14:54:15');

--
-- Triggers `lab_orders`
--
DELIMITER $$
CREATE TRIGGER `generate_lab_order_number` BEFORE INSERT ON `lab_orders` FOR EACH ROW BEGIN
    DECLARE sequence_num INT;
    
    IF NEW.order_number IS NULL THEN
        -- Get the next sequence number based on existing records
        SELECT COALESCE(MAX(id), 0) + 1 INTO sequence_num FROM lab_orders;
        SET NEW.order_number = CONCAT('LAB-', DATE_FORMAT(NOW(), '%Y%m'), '-', LPAD(sequence_num, 4, '0'));
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `lab_orders_old`
--

CREATE TABLE `lab_orders_old` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_number` varchar(50) DEFAULT NULL,
  `medical_record_id` int(10) UNSIGNED DEFAULT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `ordered_by` int(10) UNSIGNED NOT NULL,
  `collected_by` int(10) UNSIGNED DEFAULT NULL,
  `tested_by` int(10) UNSIGNED DEFAULT NULL,
  `verified_by` int(10) UNSIGNED DEFAULT NULL,
  `order_date` datetime DEFAULT current_timestamp(),
  `collected_at` datetime DEFAULT NULL,
  `tested_at` datetime DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `notification_sent` tinyint(1) DEFAULT 0,
  `priority` enum('routine','urgent','stat') DEFAULT 'routine',
  `clinical_notes` text DEFAULT NULL,
  `status` enum('ordered','in_progress','completed','cancelled') DEFAULT 'ordered',
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_orders_old`
--

INSERT INTO `lab_orders_old` (`id`, `order_number`, `medical_record_id`, `patient_id`, `ordered_by`, `collected_by`, `tested_by`, `verified_by`, `order_date`, `collected_at`, `tested_at`, `verified_at`, `notification_sent`, `priority`, `clinical_notes`, `status`, `completed_at`) VALUES
(2, 'LAB-202510-0001', NULL, 10, 55, 55, NULL, NULL, '2025-10-28 00:18:43', '2025-10-27 23:38:00', NULL, NULL, 0, 'stat', 'Uric acid test', '', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `lab_order_items`
--

CREATE TABLE `lab_order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `lab_order_id` int(10) UNSIGNED NOT NULL,
  `service_id` int(10) UNSIGNED DEFAULT NULL,
  `test_name` varchar(255) NOT NULL,
  `test_code` varchar(100) DEFAULT NULL,
  `specimen_type` varchar(100) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `result_value` varchar(255) DEFAULT NULL,
  `result_unit` varchar(50) DEFAULT NULL,
  `reference_range` varchar(100) DEFAULT NULL,
  `result_notes` text DEFAULT NULL,
  `status` enum('ordered','collected','in_progress','completed') DEFAULT 'ordered',
  `specimen_condition` varchar(50) DEFAULT NULL,
  `collection_notes` text DEFAULT NULL,
  `collected_by` varchar(100) DEFAULT NULL,
  `collected_at` datetime DEFAULT NULL,
  `tested_by` varchar(100) DEFAULT NULL,
  `tested_at` datetime DEFAULT NULL,
  `verified_by` varchar(100) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_order_items`
--

INSERT INTO `lab_order_items` (`id`, `lab_order_id`, `service_id`, `test_name`, `test_code`, `specimen_type`, `instructions`, `result_value`, `result_unit`, `reference_range`, `result_notes`, `status`, `specimen_condition`, `collection_notes`, `collected_by`, `collected_at`, `tested_by`, `tested_at`, `verified_by`, `verified_at`) VALUES
(6, 7, 8, 'Blood Test', 'BLOODTES', 'Various', 'Complete blood count test', '8.1', 'mg/dL', '3-7', 'Indicates infection, as her RBC is high.', 'completed', 'good', '', 'Unknown User', '2025-10-30 13:33:00', 'Unknown User', '2025-10-30 13:34:00', NULL, NULL),
(7, 8, 8, 'Blood Test', 'BLOODTES', 'Various', 'Complete blood count test', '65', 'mmol/L', '25-54', 'Has an infection. Food poisoning.', 'completed', 'contaminated', '', 'Unknown User', '2025-10-30 18:25:00', 'Unknown User', '2025-10-30 18:25:00', NULL, NULL),
(8, 9, 19, 'stool sample tests', 'STOOLSAM', 'Various', 'Diagnosing infections from bacteria, viruses, or parasites present in the stool.', '105', 'mmol/L', '60-75', 'Infected. Has stomach infection and food poisoning.', 'completed', 'contaminated', '', 'Unknown User', '2025-10-30 18:38:00', 'Unknown User', '2025-10-30 18:38:00', NULL, NULL),
(9, 11, 9, 'Urine Analysis', 'URINEANA', 'Various', 'Complete urine analysis', '120', 'mmol/L', '80 - 140', 'No signs of infection.', 'completed', 'good', '', 'Unknown User', '2025-10-30 22:19:00', 'Unknown User', '2025-10-30 22:19:00', NULL, NULL),
(10, 11, 20, 'Uric Acid Test', 'URICACID', 'Various', 'Used to test the levels of uric in the blood stream.', '8.4', 'mg/dL', '3 - 8', 'High uric in the blood stream. Signs of early gout.', 'completed', 'good', '', 'Unknown User', '2025-10-30 22:19:00', 'Unknown User', '2025-10-30 22:19:00', NULL, NULL),
(11, 12, 8, 'Blood Test', 'BLOODTES', 'Various', 'Complete blood count test', '65', 'μmol/L', '42 - 63', 'Slightly elevated, indicates mild infection', 'completed', 'good', '', 'Joyce Owenda', '2025-10-31 20:38:00', 'Joyce Owenda', '2025-10-31 20:39:00', NULL, NULL),
(12, 12, 20, 'Uric Acid Test', 'URICACID', 'Various', 'Used to test the levels of uric in the blood stream.', '7.8', 'mg/dL', '3 - 7', 'Elevated uric acid levels. Final diagnosis is Hyperuricemia.', 'completed', 'good', '', 'Joyce Owenda', '2025-10-31 20:38:00', 'Joyce Owenda', '2025-10-31 20:39:00', NULL, NULL),
(13, 13, NULL, 'Blood Test', 'BLOODTES', 'Laboratory specimen', 'Complete blood count test', '85', 'mmol/L', '45 - 80', 'Elevated RBC count. Indicates blood infection.', 'completed', 'good', '', 'Miriam Wambui', '2025-11-02 14:33:00', 'Miriam Wambui', '2025-11-02 14:33:00', NULL, NULL),
(14, 14, NULL, 'Blood Test', 'BLOODTES', 'Laboratory specimen', 'Complete blood count test', '89', 'mmol/L', '50 - 65', 'Extremely elevated. Indicates extreme blood infection.', 'completed', 'hemolyzed', '', 'Miriam Wambui', '2025-11-02 19:44:00', 'Miriam Wambui', '2025-11-02 19:44:00', NULL, NULL),
(15, 14, NULL, 'Urine Analysis', 'URINEANA', 'Laboratory specimen', 'Complete urine analysis', '150', 'mg/dL', '75 - 135', 'High PH levels and clouded. Bacterial infection.', 'completed', 'hemolyzed', '', 'Miriam Wambui', '2025-11-02 19:44:00', 'Miriam Wambui', '2025-11-02 19:44:00', NULL, NULL),
(16, 14, NULL, 'HIV ANTIBODY TEST', 'HIVANTIB', 'Laboratory specimen', 'Used to test for HIV', 'NEGATIVE', '%', '', 'NEGATIVE. NO HIV.', 'completed', 'hemolyzed', '', 'Miriam Wambui', '2025-11-02 19:44:00', 'Miriam Wambui', '2025-11-02 19:44:00', NULL, NULL),
(17, 14, NULL, 'Uric Acid Test', 'URICACID', 'Laboratory specimen', 'Used to test the levels of uric in the blood stream.', '6.4', 'mg/dL', '3 - 7', 'Normal range. no notes.', 'completed', 'hemolyzed', '', 'Miriam Wambui', '2025-11-02 19:44:00', 'Miriam Wambui', '2025-11-02 19:44:00', NULL, NULL),
(18, 15, NULL, 'Blood Test', 'BLOODTES', 'Laboratory specimen', 'Complete blood count test', '85', 'mmol/L', '30 - 80', 'SLIGHTL ELAVATED RBC CPUNT, INDICATES MILD INFECTION.', 'completed', 'good', '', 'Vivian chepchumba', '2025-11-03 21:51:00', 'Vivian chepchumba', '2025-11-03 21:51:00', NULL, NULL),
(19, 16, NULL, 'Blood Test', 'BLOODTES', 'Laboratory specimen', 'Complete blood count test', '85', 'mmol/L', '40 - 82', 'Slightly elevated, indicates mild blood infection.', 'completed', 'good', '', 'Miriam Wambui', '2025-11-04 08:40:00', 'Miriam Wambui', '2025-11-04 08:40:00', NULL, NULL),
(20, 17, NULL, 'Blood Test', 'BLOODTES', 'Laboratory specimen', 'Complete blood count test', '85', 'mmol/L', '45 - 80', 'slightly elevated, indicates mild infection.', 'completed', 'insufficient', '', 'Vivian chepchumba', '2025-11-06 09:31:00', 'Vivian chepchumba', '2025-11-06 09:31:00', NULL, NULL),
(21, 17, NULL, 'stool sample tests', 'STOOLSAM', 'Laboratory specimen', 'Diagnosing infections from bacteria, viruses, or parasites present in the stool.', '123', 'mg/dL', '70 - 110', 'contaminated, indicates bacterial infection and food poisoning.', 'completed', 'insufficient', '', 'Vivian chepchumba', '2025-11-06 09:31:00', 'Vivian chepchumba', '2025-11-06 09:31:00', NULL, NULL),
(22, 18, NULL, 'Blood Test', 'BLOODTES', 'Laboratory specimen', 'Complete blood count test', '85', 'mmol/L', '40 - 75', 'slightly elevated, indicates mild infection.', 'completed', 'good', '', 'Miriam Wambui', '2025-11-14 08:06:00', 'Miriam Wambui', '2025-11-14 08:06:00', NULL, NULL),
(23, 19, NULL, 'Blood Test', 'BLOODTES', 'Laboratory specimen', 'Complete blood count test', '330', 'mmol/L', '110-234mmol/L', 'Indicates high infection', 'completed', 'good', '', 'Miriam Wambui', '2026-01-09 10:00:00', 'Miriam Wambui', '2026-01-09 10:00:00', NULL, NULL),
(24, 19, NULL, 'stool sample tests', 'STOOLSAM', 'Laboratory specimen', 'Diagnosing infections from bacteria, viruses, or parasites present in the stool.', '48', 'mmol/L', '21-35 mmol/L', 'Stomach infection. Suggests food poisoning.', 'completed', 'good', '', 'Miriam Wambui', '2026-01-09 10:00:00', 'Miriam Wambui', '2026-01-09 10:00:00', NULL, NULL),
(25, 20, NULL, 'Blood Test', 'BLOODTES', 'Laboratory specimen', 'Complete blood count test', '7.5', 'mg/dL', '4 - 7', 'Slightly elevated RBC, indicates blood infection!', 'completed', 'good', '', 'Miriam Wambui', '2026-01-15 21:45:00', 'Miriam Wambui', '2026-01-15 21:45:00', NULL, NULL),
(26, 21, NULL, 'Blood Test', 'BLOODTES', 'Laboratory specimen', 'Complete blood count test', '8.6', 'mg/dL', '3- 7', 'Elevated RBC count, indicates Blood infection', 'completed', 'good', '', 'Miriam Wambui', '2026-01-16 11:19:00', 'Miriam Wambui', '2026-01-16 11:19:00', NULL, NULL),
(27, 21, NULL, 'Urine Analysis', 'URINEANA', 'Laboratory specimen', 'Complete urine analysis', '135', 'mmol/L', '100 - 210', 'Normal', 'completed', 'good', '', 'Miriam Wambui', '2026-01-16 11:19:00', 'Miriam Wambui', '2026-01-16 11:19:00', NULL, NULL),
(28, 21, NULL, 'Blood Sugar Test', 'BLOODSUG', 'Laboratory specimen', 'Blood glucose level test', '4.5', 'mmol/L', '3-6', 'Normal', 'completed', 'good', '', 'Miriam Wambui', '2026-01-16 11:19:00', 'Miriam Wambui', '2026-01-16 11:19:00', NULL, NULL),
(29, 21, NULL, 'stool sample tests', 'STOOLSAM', 'Laboratory specimen', 'Diagnosing infections from bacteria, viruses, or parasites present in the stool.', '310', 'mg/dL', '110 - 285', 'Raised, bacterial infection', 'completed', 'good', '', 'Miriam Wambui', '2026-01-16 11:19:00', 'Miriam Wambui', '2026-01-16 11:19:00', NULL, NULL),
(30, 22, NULL, 'Blood Test', 'BLOODTES', 'Laboratory specimen', 'Complete blood count test', '7.2', 'mmol/L', '3 - 7', 'Elevated RBC count, indicates mild infection', 'completed', 'good', '', 'Miriam Wambui', '2026-01-16 21:57:00', 'Miriam Wambui', '2026-01-16 21:57:00', NULL, NULL),
(31, 22, NULL, 'Urine Analysis', 'URINEANA', 'Laboratory specimen', 'Complete urine analysis', '154', 'mg/dL', '85 - 190', 'Normal', 'completed', 'good', '', 'Miriam Wambui', '2026-01-16 21:57:00', 'Miriam Wambui', '2026-01-16 21:57:00', NULL, NULL),
(32, 22, NULL, 'stool sample tests', 'STOOLSAM', 'Laboratory specimen', 'Diagnosing infections from bacteria, viruses, or parasites present in the stool.', '6', 'mg/dL', '3 - 7', 'Normal', 'completed', 'good', '', 'Miriam Wambui', '2026-01-16 21:57:00', 'Miriam Wambui', '2026-01-16 21:57:00', NULL, NULL),
(33, 23, NULL, 'Blood Test', 'BLOODTES', 'Laboratory specimen', 'Complete blood count test', '8', 'mg/dL', '3 - 7', 'Slightly elevated RBC count, mild infection', 'completed', 'good', '', 'Miriam Wambui', '2026-01-17 08:30:00', 'Miriam Wambui', '2026-01-17 08:31:00', NULL, NULL),
(34, 23, NULL, 'Urine Analysis', 'URINEANA', 'Laboratory specimen', 'Complete urine analysis', '6.8', 'mmol/L', '2 - 5.5', 'Concentrated, Suspected UTI infection.', 'completed', 'good', '', 'Miriam Wambui', '2026-01-17 08:30:00', 'Miriam Wambui', '2026-01-17 08:31:00', NULL, NULL),
(35, 24, NULL, 'Blood Test', 'BLOODTES', 'Laboratory specimen', 'Complete blood count test', '8.5', 'mmol/L', '3 -7 ', 'Slightly elevated RBC count, indicates infection', 'completed', 'good', '', 'Miriam Wambui', '2026-01-17 09:36:00', 'Miriam Wambui', '2026-01-17 09:36:00', NULL, NULL),
(36, 24, NULL, 'Blood Sugar Test', 'BLOODSUG', 'Laboratory specimen', 'Blood glucose level test', '3.2', 'mmol/L', '4 - 6', 'Low blood sugar levels, Hypoglycemic, due to dehydration', 'completed', 'good', '', 'Miriam Wambui', '2026-01-17 09:36:00', 'Miriam Wambui', '2026-01-17 09:36:00', NULL, NULL),
(37, 25, NULL, 'stool sample tests', 'STOOLSAM', 'Laboratory specimen', 'Diagnosing infections from bacteria, viruses, or parasites present in the stool.', '231', 'mmol/L', '100 -187', 'Bacterial Infection', 'completed', 'contaminated', '', 'Vivian chepchumba', '2026-01-17 11:25:00', 'Vivian chepchumba', '2026-01-17 11:26:00', NULL, NULL),
(38, 26, NULL, 'stool sample tests', 'STOOLSAM', 'Laboratory specimen', 'Diagnosing infections from bacteria, viruses, or parasites present in the stool.', '231', 'mg/dL', '100 -185', 'Food poisoning', 'completed', 'good', '', 'Vivian chepchumba', '2026-01-17 15:11:00', 'Vivian chepchumba', '2026-01-17 15:11:00', NULL, NULL),
(39, 27, NULL, 'Blood Test', 'BLOODTES', 'Laboratory specimen', 'Complete blood count test', '8.5', 'mmol/L', '3 - 7', 'Raised RBC count, Blood infection', 'completed', 'good', '', 'Miriam Wambui', '2026-01-19 12:25:00', 'Miriam Wambui', '2026-01-19 12:25:00', NULL, NULL),
(40, 28, NULL, 'Blood Test', 'BLOODTES', 'Laboratory specimen', 'Complete blood count test', '6', 'mg/dL', '4 - 7', 'Normal levels. No notes!', 'completed', 'clotted', '', 'Miriam Wambui', '2026-01-20 14:10:00', 'Miriam Wambui', '2026-02-05 19:13:00', NULL, NULL),
(41, 0, NULL, 'Blood Test', 'BLOODTES', 'Laboratory specimen', 'Complete blood count test', '134', 'mg/dL', '65 - 110', 'contaminated, bacterial infection. Food poisoning', 'completed', NULL, NULL, NULL, NULL, 'Vivian chepchumba', '2026-01-20 21:29:00', NULL, NULL),
(42, 51, NULL, 'Blood Test', 'BLOODTES', 'Laboratory specimen', 'Complete blood count test', '134', 'mg/dL', '65 - 110', 'contaminated, bacterial infection. Food poisoning', 'completed', 'good', '', 'Miriam Wambui', '2026-01-20 11:44:00', 'Vivian chepchumba', '2026-01-20 21:29:00', NULL, NULL),
(43, 52, NULL, 'Blood Test', 'BLOODTES', 'Laboratory specimen', 'Complete blood count test', '134', 'mg/dL', '65 - 110', 'contaminated, bacterial infection. Food poisoning', 'completed', 'good', '', 'Miriam Wambui', '2026-01-20 12:09:00', 'Vivian chepchumba', '2026-01-20 21:29:00', NULL, NULL),
(44, 53, NULL, 'Blood Sugar Test', 'BLOODSUG', 'Laboratory specimen', 'Blood glucose level test', '134', 'mg/dL', '65 - 110', 'contaminated, bacterial infection. Food poisoning', 'completed', 'good', '', 'Miriam Wambui', '2026-01-20 14:10:00', 'Vivian chepchumba', '2026-01-20 21:29:00', NULL, NULL),
(45, 54, NULL, 'Blood Test', 'BLOODTES', 'Laboratory specimen', 'Complete blood count test', '134', 'mg/dL', '65 - 110', 'contaminated, bacterial infection. Food poisoning', 'completed', 'insufficient', '', 'Miriam Wambui', '2026-01-20 19:45:00', 'Vivian chepchumba', '2026-01-20 21:29:00', NULL, NULL),
(46, 54, NULL, 'Blood Sugar Test', 'BLOODSUG', 'Laboratory specimen', 'Blood glucose level test', '134', 'mg/dL', '65 - 110', 'contaminated, bacterial infection. Food poisoning', 'completed', 'insufficient', '', 'Miriam Wambui', '2026-01-20 19:45:00', 'Vivian chepchumba', '2026-01-20 21:29:00', NULL, NULL),
(83, 56, NULL, 'Blood Test', 'BLOODTES', 'Laboratory specimen', 'Complete blood count test', '99', 'mg/dL', '40 - 89', 'Slightly raised, blood infection.', 'completed', 'hemolyzed', '', 'Vivian chepchumba', '2026-01-20 21:49:00', 'Vivian chepchumba', '2026-01-20 21:49:00', NULL, NULL),
(84, 57, NULL, 'Blood Test', 'BLOODTES', 'Laboratory specimen', 'Complete blood count test', '5.8', 'mg/dL', '3 - 7', 'Normal Range', 'completed', 'good', '', 'Miriam Wambui', '2026-01-21 12:55:00', 'Miriam Wambui', '2026-01-21 13:00:00', NULL, NULL),
(85, 58, NULL, 'Blood Test', 'BLOODTES', 'Laboratory specimen', 'Complete blood count test', '9.8', 'mg/dL', '3 -7', 'High RBC count, Blood infection', 'completed', 'good', '', 'Vivian chepchumba', '2026-01-22 20:52:00', 'Vivian chepchumba', '2026-01-22 20:52:00', NULL, NULL),
(86, 59, NULL, 'Uric Acid Test', 'URICACID', 'Laboratory specimen', 'Used to test the levels of uric in the blood stream.', '154', 'mmol/L', '80 - 120', 'Raised uric levels.', 'completed', 'good', '', 'Miriam Wambui', '2026-02-05 19:12:00', 'Miriam Wambui', '2026-02-05 19:12:00', NULL, NULL),
(87, 60, NULL, 'stool sample tests', 'STOOLSAM', 'Laboratory specimen', 'Diagnosing infections from bacteria, viruses, or parasites present in the stool.', '12.1', 'mg/dL', '4 - 10', 'Bacterial Infection', 'completed', 'good', '', 'Vivian chepchumba', '2026-02-06 09:47:00', 'Vivian chepchumba', '2026-02-06 09:47:00', NULL, NULL),
(88, 61, NULL, 'Blood Test', 'BLOODTES', 'Laboratory specimen', 'Complete blood count test', '225', 'mg/dL', '100 - 200', 'Raised uric acid levels.', 'completed', 'good', '', 'Miriam Wambui', '2026-02-06 12:03:00', 'Miriam Wambui', '2026-02-06 12:03:00', NULL, NULL),
(89, 62, NULL, 'Blood Test', 'BLOODTES', 'Laboratory specimen', 'Complete blood count test', '5.3', 'mg/dL', '4 - 10', 'Normal Range, No notes!', 'completed', 'good', '', 'Vivian chepchumba', '2026-02-06 12:53:00', 'Vivian chepchumba', '2026-02-06 12:53:00', NULL, NULL),
(90, 62, NULL, 'Blood Sugar Test', 'BLOODSUG', 'Laboratory specimen', 'Blood glucose level test', '5.2', 'mmol/L', '3 - 6', 'Normal Range', 'completed', 'good', '', 'Vivian chepchumba', '2026-02-06 12:53:00', 'Vivian chepchumba', '2026-02-06 12:53:00', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `lab_results`
--

CREATE TABLE `lab_results` (
  `id` int(10) UNSIGNED NOT NULL,
  `record_id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `test_name` varchar(255) NOT NULL,
  `test_category` varchar(100) DEFAULT NULL,
  `result_value` varchar(255) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `reference_range` varchar(100) DEFAULT NULL,
  `status` enum('normal','abnormal','critical') DEFAULT 'normal',
  `performed_date` date DEFAULT NULL,
  `result_date` date DEFAULT NULL,
  `technician_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `recorded_by` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_results`
--

INSERT INTO `lab_results` (`id`, `record_id`, `patient_id`, `test_name`, `test_category`, `result_value`, `unit`, `reference_range`, `status`, `performed_date`, `result_date`, `technician_notes`, `created_at`, `recorded_by`) VALUES
(1, 5, 1, 'Complete Blood Count', 'hematology', NULL, NULL, NULL, '', '2025-10-08', NULL, NULL, '2025-10-08 15:19:09', ''),
(2, 13, 4, 'Complete Blood Count', 'hematology', NULL, NULL, NULL, '', '2025-10-18', NULL, NULL, '2025-10-18 18:45:15', '55'),
(3, 19, 9, 'Complete Blood Count', 'hematology', NULL, NULL, NULL, '', '2025-10-27', NULL, NULL, '2025-10-27 20:23:33', '55');

-- --------------------------------------------------------

--
-- Table structure for table `lab_tests`
--

CREATE TABLE `lab_tests` (
  `id` int(11) NOT NULL,
  `test_name` varchar(255) NOT NULL,
  `test_code` varchar(50) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `specimen_type` varchar(100) DEFAULT NULL,
  `default_instructions` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_tests`
--

INSERT INTO `lab_tests` (`id`, `test_name`, `test_code`, `category`, `specimen_type`, `default_instructions`, `description`, `price`, `active`, `created_at`, `updated_at`) VALUES
(1, 'Complete Blood Count', 'CBC', 'Hematology', 'Whole Blood', 'Collect in EDTA tube', 'Measures red blood cells, white blood cells, hemoglobin, hematocrit, and platelets. Used to evaluate overall health and detect disorders.', 45.00, 1, '2025-10-30 11:20:21', '2025-10-30 11:20:21'),
(2, 'Basic Metabolic Panel', 'BMP', 'Chemistry', 'Serum', 'Fasting required, collect in SST tube', 'Measures glucose, calcium, electrolytes, and kidney function. Provides information about body\'s metabolism.', 65.00, 1, '2025-10-30 11:20:21', '2025-10-30 11:20:21'),
(3, 'Comprehensive Metabolic Panel', 'CMP', 'Chemistry', 'Serum', 'Fasting required, collect in SST tube', 'Includes BMP tests plus liver function tests. Comprehensive assessment of metabolism and organ function.', 95.00, 1, '2025-10-30 11:20:21', '2025-10-30 11:20:21'),
(4, 'Lipid Panel', 'LIPID', 'Chemistry', 'Serum', '12-hour fasting required', 'Measures cholesterol levels including LDL, HDL, and triglycerides. Assesses cardiovascular risk.', 55.00, 1, '2025-10-30 11:20:21', '2025-10-30 11:20:21'),
(5, 'Liver Function Tests', 'LFT', 'Chemistry', 'Serum', 'Collect in SST tube', 'Measures enzymes and proteins to assess liver health and detect liver damage or disease.', 75.00, 1, '2025-10-30 11:20:21', '2025-10-30 11:20:21'),
(6, 'Thyroid Stimulating Hormone', 'TSH', 'Endocrinology', 'Serum', 'Collect in SST tube', 'Measures thyroid function. Screens for thyroid disorders like hypothyroidism and hyperthyroidism.', 50.00, 1, '2025-10-30 11:20:21', '2025-10-30 11:20:21'),
(7, 'Hemoglobin A1c', 'HBA1C', 'Diabetes', 'Whole Blood', 'Collect in EDTA tube', 'Measures average blood sugar levels over past 2-3 months. Used to diagnose and monitor diabetes.', 40.00, 1, '2025-10-30 11:20:21', '2025-10-30 11:20:21'),
(8, 'Urinalysis', 'UA', 'Urine', 'Urine', 'Clean-catch midstream specimen', 'Examines physical, chemical, and microscopic properties of urine. Screens for urinary tract and kidney disorders.', 35.00, 1, '2025-10-30 11:20:21', '2025-10-30 11:20:21'),
(9, 'Urine Culture', 'UC', 'Microbiology', 'Urine', 'Clean-catch midstream specimen', 'Identifies bacteria causing urinary tract infections and determines antibiotic sensitivity.', 60.00, 1, '2025-10-30 11:20:21', '2025-10-30 11:20:21'),
(10, 'Blood Culture', 'BC', 'Microbiology', 'Whole Blood', 'Collect in blood culture bottles', 'Detects presence of bacteria or fungi in the blood. Used to diagnose bloodstream infections.', 85.00, 1, '2025-10-30 11:20:21', '2025-10-30 11:20:21'),
(11, 'Prothrombin Time', 'PT', 'Coagulation', 'Plasma', 'Collect in sodium citrate tube', 'Measures how long it takes blood to clot. Monitors anticoagulant therapy.', 40.00, 1, '2025-10-30 11:20:21', '2025-10-30 11:20:21'),
(12, 'Partial Thromboplastin Time', 'PTT', 'Coagulation', 'Plasma', 'Collect in sodium citrate tube', 'Evaluates intrinsic pathway of coagulation. Monitors heparin therapy.', 45.00, 1, '2025-10-30 11:20:21', '2025-10-30 11:20:21'),
(13, 'Troponin I', 'TROP', 'Cardiac', 'Serum', 'Collect in SST tube', 'Measures cardiac-specific protein released during heart muscle damage. Diagnoses heart attacks.', 70.00, 1, '2025-10-30 11:20:21', '2025-10-30 11:20:21'),
(14, 'B-type Natriuretic Peptide', 'BNP', 'Cardiac', 'Plasma', 'Collect in EDTA tube', 'Measures hormone produced by heart. Helps diagnose and monitor heart failure.', 80.00, 1, '2025-10-30 11:20:21', '2025-10-30 11:20:21'),
(15, 'C-reactive Protein', 'CRP', 'Inflammation', 'Serum', 'Collect in SST tube', 'Measures general inflammation in the body. Screens for inflammatory conditions.', 40.00, 1, '2025-10-30 11:20:21', '2025-10-30 11:20:21'),
(16, 'Vitamin B12', 'B12', 'Nutrition', 'Serum', 'Collect in SST tube', 'Measures vitamin B12 levels. Diagnoses deficiency that can cause anemia and neurological issues.', 55.00, 1, '2025-10-30 11:20:21', '2025-10-30 11:20:21'),
(17, 'Vitamin D', 'VITD', 'Nutrition', 'Serum', 'Collect in SST tube', 'Measures vitamin D levels. Assesses bone health and detects deficiency.', 65.00, 1, '2025-10-30 11:20:21', '2025-10-30 11:20:21'),
(18, 'PSA Total', 'PSA', 'Oncology', 'Serum', 'Collect in SST tube', 'Measures prostate-specific antigen. Screens for prostate cancer and monitors treatment.', 70.00, 1, '2025-10-30 11:20:21', '2025-10-30 11:20:21'),
(19, 'Rheumatoid Factor', 'RF', 'Immunology', 'Serum', 'Collect in SST tube', 'Detects antibodies associated with rheumatoid arthritis and other autoimmune disorders.', 50.00, 1, '2025-10-30 11:20:21', '2025-10-30 11:20:21'),
(20, 'HIV Antibody', 'HIV', 'Infectious Disease', 'Serum', 'Collect in SST tube', 'Screens for antibodies to HIV. Used for diagnosis and screening of HIV infection.', 45.00, 1, '2025-10-30 11:20:21', '2025-10-30 11:20:21');

-- --------------------------------------------------------

--
-- Table structure for table `medical_records`
--

CREATE TABLE `medical_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `record_number` varchar(50) DEFAULT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `triage_assessment_id` int(10) UNSIGNED DEFAULT NULL,
  `doctor_id` int(10) UNSIGNED NOT NULL,
  `assigned_doctor_id` int(10) UNSIGNED DEFAULT NULL,
  `record_type` enum('consultation','lab_result','prescription','diagnosis','procedure','vaccination','imaging','note') NOT NULL,
  `record_date` date NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `nurse_notes` text DEFAULT NULL,
  `doctor_notes` text DEFAULT NULL,
  `treatment_plan` text DEFAULT NULL,
  `follow_up_instructions` text DEFAULT NULL,
  `status` enum('draft','completed','reviewed','archived') DEFAULT 'completed',
  `priority` enum('normal','high','critical') DEFAULT 'normal',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `workflow_status` enum('triage','triage_completed','doctor_review','treatment','completed','follow_up') DEFAULT 'triage'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medical_records`
--

INSERT INTO `medical_records` (`id`, `record_number`, `patient_id`, `triage_assessment_id`, `doctor_id`, `assigned_doctor_id`, `record_type`, `record_date`, `department`, `diagnosis`, `treatment`, `notes`, `nurse_notes`, `doctor_notes`, `treatment_plan`, `follow_up_instructions`, `status`, `priority`, `created_by`, `created_at`, `updated_at`, `workflow_status`) VALUES
(2, 'EHR202510-57930', 1, NULL, 59, NULL, 'consultation', '2025-10-04', 'Cardiology', 'Heart related', 'Medications', '', NULL, NULL, NULL, NULL, 'completed', 'normal', 55, '2025-10-04 11:40:21', NULL, 'triage'),
(3, 'EHR202510-12755', 2, NULL, 61, NULL, 'diagnosis', '2025-10-04', 'Pediatrics', 'Bronchia\'s infection', 'Start on Antibiotics', '', NULL, NULL, NULL, NULL, 'completed', 'high', 55, '2025-10-04 13:13:26', NULL, 'triage'),
(4, 'EHR202510-00002', 8, NULL, 55, NULL, 'consultation', '2025-10-06', NULL, NULL, NULL, 'New patient registration completed.\nInitial information recorded in system.', NULL, NULL, NULL, NULL, 'completed', 'normal', 55, '2025-10-06 11:00:45', NULL, 'triage'),
(5, 'EHR202510-00001', 1, NULL, 55, NULL, 'lab_result', '2025-10-08', 'Laboratory', NULL, NULL, '', NULL, NULL, NULL, NULL, 'completed', 'normal', 55, '2025-10-08 15:19:09', NULL, 'triage'),
(13, '2025101820-1494', 4, NULL, 55, NULL, 'lab_result', '2025-10-18', 'Laboratory', NULL, NULL, '', NULL, NULL, NULL, NULL, 'completed', 'normal', 55, '2025-10-18 18:45:15', NULL, 'triage'),
(14, '2025101820-9039', 4, NULL, 55, NULL, 'consultation', '2025-10-18', 'General', '', '', 'Subjective: Came in with a splitting headache, temperature was at 38 degrees. Has also nose-bleed 3 times in the past 2 weeks.\nObjective: ', NULL, NULL, NULL, NULL, 'completed', 'normal', 55, '2025-10-18 18:46:27', NULL, 'triage'),
(15, 'EHR202510-00003', 9, NULL, 55, NULL, 'consultation', '2025-10-26', NULL, NULL, NULL, 'New patient registration completed.\nInitial information recorded in system.', NULL, NULL, NULL, NULL, 'completed', 'normal', 55, '2025-10-26 12:46:39', NULL, 'triage'),
(16, 'EHR202510-00004', 10, NULL, 55, NULL, 'consultation', '2025-10-26', NULL, NULL, NULL, 'New patient registration completed.\nInitial information recorded in system.', NULL, NULL, NULL, NULL, 'completed', 'normal', 55, '2025-10-26 12:51:46', NULL, 'triage'),
(17, 'EHR202510-00005', 11, NULL, 55, NULL, 'consultation', '2025-10-27', NULL, NULL, NULL, 'New patient registration completed.\nInitial information recorded in system.', NULL, NULL, NULL, NULL, 'completed', 'normal', 55, '2025-10-27 07:59:10', NULL, 'triage'),
(18, '2025102721-1368', 9, NULL, 55, NULL, 'consultation', '2025-10-27', 'General', '', '', 'Subjective: Stomach aches and severe migraines.\nObjective: From the temperature readings, it is suspected the patient has an infection. Lab tests to be ordered to confirm this before proceeding with treatment.', NULL, NULL, NULL, NULL, 'completed', 'normal', 55, '2025-10-27 20:23:17', NULL, 'triage'),
(19, '2025102721-4622', 9, NULL, 55, NULL, 'lab_result', '2025-10-27', 'Laboratory', NULL, NULL, '', NULL, NULL, NULL, NULL, 'completed', 'normal', 55, '2025-10-27 20:23:33', NULL, 'triage'),
(20, 'EHR202511-00006', 12, NULL, 55, NULL, 'consultation', '2025-11-02', NULL, NULL, NULL, 'New patient registration completed.\nInitial information recorded in system.', NULL, NULL, NULL, NULL, 'completed', 'normal', 55, '2025-11-02 10:51:21', NULL, 'triage'),
(21, 'EHR202511-00007', 13, NULL, 55, NULL, 'consultation', '2025-11-06', NULL, NULL, NULL, 'New patient registration completed.\nInitial information recorded in system.', NULL, NULL, NULL, NULL, 'completed', 'normal', 55, '2025-11-06 08:26:38', NULL, 'triage'),
(22, 'EHR202511-00008', 14, NULL, 56, NULL, 'consultation', '2025-11-14', NULL, NULL, NULL, 'New patient registration completed.\nInitial information recorded in system.', NULL, NULL, NULL, NULL, 'completed', 'normal', 56, '2025-11-14 06:58:27', NULL, 'triage'),
(23, 'EHR202601-00001', 0, NULL, 55, NULL, 'consultation', '2026-01-20', NULL, NULL, NULL, 'New patient registration completed.\nInitial information recorded in system.', NULL, NULL, NULL, NULL, 'completed', 'normal', 55, '2026-01-20 10:08:53', NULL, 'triage'),
(24, 'EHR202601-00032', 31, NULL, 55, NULL, 'consultation', '2026-01-20', NULL, NULL, NULL, 'New patient registration completed.\nInitial information recorded in system.', NULL, NULL, NULL, NULL, 'completed', 'normal', 55, '2026-01-20 10:58:05', NULL, 'triage'),
(25, 'EHR202601-00033', 32, NULL, 55, NULL, 'consultation', '2026-01-20', NULL, NULL, NULL, 'New patient registration completed.\nInitial information recorded in system.', NULL, NULL, NULL, NULL, 'completed', 'normal', 55, '2026-01-20 18:39:45', NULL, 'triage'),
(26, 'EHR202601-00034', 33, NULL, 56, NULL, 'consultation', '2026-01-21', NULL, NULL, NULL, 'New patient registration completed.\nInitial information recorded in system.', NULL, NULL, NULL, NULL, 'completed', 'normal', 56, '2026-01-21 11:44:10', NULL, 'triage');

--
-- Triggers `medical_records`
--
DELIMITER $$
CREATE TRIGGER `generate_record_number` BEFORE INSERT ON `medical_records` FOR EACH ROW BEGIN

    IF NEW.record_number IS NULL THEN

        SET NEW.record_number = CONCAT('EHR', DATE_FORMAT(NOW(), '%Y%m'), '-', LPAD(LAST_INSERT_ID() + 1, 5, '0'));

    END IF;

END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `medical_record_access_log`
--

CREATE TABLE `medical_record_access_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `record_id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `accessed_by` int(10) UNSIGNED DEFAULT NULL,
  `access_type` enum('view','edit','create','delete','download') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `accessed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medical_record_access_log`
--

INSERT INTO `medical_record_access_log` (`id`, `record_id`, `patient_id`, `accessed_by`, `access_type`, `ip_address`, `user_agent`, `accessed_at`) VALUES
(1, 2, 1, 55, 'create', '::1', NULL, '2025-10-04 11:40:21'),
(2, 3, 2, 55, 'create', '::1', NULL, '2025-10-04 13:13:26');

-- --------------------------------------------------------

--
-- Table structure for table `old_payments`
--

CREATE TABLE `old_payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `payment_number` varchar(50) NOT NULL,
  `payment_date` datetime NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `mpesa_transaction_id` varchar(100) DEFAULT NULL,
  `status` enum('pending','completed','failed','refunded') DEFAULT 'completed',
  `notes` text DEFAULT NULL,
  `received_by` int(10) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `old_payments`
--

INSERT INTO `old_payments` (`id`, `patient_id`, `payment_number`, `payment_date`, `total_amount`, `payment_method`, `payment_reference`, `mpesa_transaction_id`, `status`, `notes`, `received_by`, `created_at`) VALUES
(1, 7, 'PAY-20251102114416-181', '2025-11-02 13:44:16', 1300.00, 'cash', '', '', 'completed', '', 55, '2025-11-02 13:44:16'),
(2, 7, 'PAY-20251102114444-114', '2025-11-02 13:44:44', 1300.00, 'cash', '', '', 'completed', '', 55, '2025-11-02 13:44:44'),
(3, 11, 'PAY-20251102114616-857', '2025-11-02 13:46:16', 1500.00, 'mpesa', '', '', 'completed', '', 55, '2025-11-02 13:46:16'),
(4, 1, 'PAY-20251102114701-134', '2025-11-02 13:47:01', 1300.00, 'mpesa', '', '', 'completed', '', 55, '2025-11-02 13:47:01'),
(5, 10, 'PAY-20251102114738-307', '2025-11-02 13:47:38', 1000.00, 'card', '', '', 'completed', '', 55, '2025-11-02 13:47:38'),
(6, 10, 'PAY-20251102114810-233', '2025-11-02 13:48:10', 0.00, 'cash', '', '', 'completed', '', 55, '2025-11-02 13:48:10'),
(7, 12, 'PAY-20251102162336-529', '2025-11-02 18:23:36', 800.00, 'insurance', 'A098123', '', 'completed', '', 72, '2025-11-02 18:23:36'),
(8, 12, 'PAY-20251102162349-438', '2025-11-02 18:23:49', 800.00, 'insurance', 'A098123', '', 'completed', '', 72, '2025-11-02 18:23:49'),
(9, 12, 'PAY-20251102162358-286', '2025-11-02 18:23:58', 800.00, 'insurance', 'A098123', '', 'completed', '', 72, '2025-11-02 18:23:58'),
(10, 12, 'PAY-20251102162425-119', '2025-11-02 18:24:25', 800.00, 'insurance', 'A098123', '', 'completed', '', 72, '2025-11-02 18:24:25'),
(11, 12, 'PAY-20251102162440-870', '2025-11-02 18:24:40', 800.00, 'insurance', 'A098123', '', 'completed', '', 72, '2025-11-02 18:24:40'),
(12, 12, 'PAY-20251102162935-820', '2025-11-02 18:29:35', 500.00, 'cash', '', '', 'completed', '', 72, '2025-11-02 18:29:35'),
(13, 2, 'PAY-20251104075651-614', '2025-11-04 09:56:51', 1300.00, 'mpesa', '', '', 'completed', '', 55, '2025-11-04 09:56:51'),
(14, 2, 'PAY-20251104075715-201', '2025-11-04 09:57:15', 1300.00, 'mpesa', '', '', 'completed', '', 55, '2025-11-04 09:57:15'),
(15, 12, 'PAY-20251104092321-671', '2025-11-04 11:23:21', 3025.00, 'mpesa', '', '', 'completed', '', 55, '2025-11-04 11:23:21'),
(16, 11, 'PAY-20251106104827-302', '2025-11-06 12:48:27', 2400.00, 'cash', '', '', 'completed', '', 55, '2025-11-06 12:48:27'),
(17, 8, 'PAY-20251106104842-505', '2025-11-06 12:48:42', 800.00, 'mpesa', '', '', 'completed', '', 55, '2025-11-06 12:48:42'),
(18, 13, 'PAY-20251106110704-119', '2025-11-06 13:07:04', 4250.00, 'mpesa', '', 'TK65H9F47X', 'completed', '', 55, '2025-11-06 13:07:04'),
(19, 13, 'PAY-20251106110842-714', '2025-11-06 13:08:42', 0.00, 'cash', '', '', 'completed', '', 55, '2025-11-06 13:08:42'),
(20, 13, 'PAY-20251106112221-771', '2025-11-06 13:22:21', 0.00, 'cash', '', '', 'completed', '', 55, '2025-11-06 13:22:21'),
(21, 13, 'PAY-20251106113501-641', '2025-11-06 13:35:01', 0.00, 'cash', '', '', 'completed', '', 55, '2025-11-06 13:35:01'),
(22, 12, 'PAY-20251106113519-758', '2025-11-06 13:35:19', 0.00, 'cash', '', '', 'completed', '', 55, '2025-11-06 13:35:19'),
(23, 14, 'PAY-20251114081753-154', '2025-11-14 10:17:53', 2400.00, 'cash', '', '', 'completed', '', 71, '2025-11-14 10:17:53'),
(24, 13, 'PAY-20251122211241-510', '2025-11-22 23:12:41', 500.00, 'card', '', '', 'completed', '', 71, '2025-11-22 23:12:41'),
(25, 5, 'PAY-20251122211302-917', '2025-11-22 23:13:02', 500.00, 'cash', '', '', 'completed', '', 71, '2025-11-22 23:13:02'),
(26, 8, 'PAY-20260109100930-499', '2026-01-09 12:09:30', 0.00, 'cash', '', '', 'completed', '', 71, '2026-01-09 12:09:30'),
(27, 8, 'PAY-20260109125907-301', '2026-01-09 14:59:07', 2950.00, 'cash', '', '', 'completed', '', 55, '2026-01-09 14:59:07'),
(28, 14, 'PAY-20260110123851-720', '2026-01-10 14:38:51', 6500.00, 'card', '', '', 'completed', '', 71, '2026-01-10 14:38:51'),
(29, 14, 'PAY-20260115123503-834', '2026-01-15 14:35:03', 850.00, 'cash', '', '', 'completed', '', 71, '2026-01-15 14:35:03'),
(32, 15, 'PAY-20260115210258-142', '2026-01-15 23:02:58', 650.00, 'cash', '', '', 'completed', '', 55, '2026-01-15 23:02:58'),
(33, 9, 'PAY-20260115220319-486', '2026-01-16 00:03:19', 2900.00, 'cash', '', '', 'completed', '', 55, '2026-01-16 00:03:19'),
(39, 2, 'PAY-20260116102455-642', '0000-00-00 00:00:00', 1325.00, 'cash', '', NULL, 'completed', '', 71, '2026-01-16 12:24:55'),
(41, 7, 'PAY-20260116120117-523', '0000-00-00 00:00:00', 3700.00, 'mpesa', '', NULL, 'completed', '', 71, '2026-01-16 14:01:17'),
(42, 1, 'PAY-20260116123237-908', '0000-00-00 00:00:00', 500.00, 'cash', '', NULL, 'completed', '', 71, '2026-01-16 14:32:37'),
(43, 10, 'PAY-20260116220520-568', '0000-00-00 00:00:00', 4500.00, 'cash', '', NULL, 'completed', '', 71, '2026-01-17 00:05:20'),
(44, 15, 'PAY-20260117085501-437', '0000-00-00 00:00:00', 1200.00, 'cash', '', NULL, 'completed', '', 71, '2026-01-17 10:55:01'),
(45, 11, 'PAY-20260117094451-551', '0000-00-00 00:00:00', 2525.00, 'cash', '', NULL, 'completed', '', 71, '2026-01-17 11:44:51'),
(46, 12, 'PAY-20260117095214-278', '0000-00-00 00:00:00', 2950.00, 'cash', '', NULL, 'completed', '', 71, '2026-01-17 11:52:14'),
(47, 1, 'PAY-20260117100401-687', '0000-00-00 00:00:00', 7500.00, 'cash', '', NULL, 'completed', '', 71, '2026-01-17 12:04:01'),
(48, 5, 'PAY-20260117112904-992', '0000-00-00 00:00:00', 1875.00, 'cash', '', NULL, 'completed', '', 71, '2026-01-17 13:29:04'),
(49, 8, 'PAY-20260117113213-533', '0000-00-00 00:00:00', 1500.00, 'cash', '', NULL, 'completed', '', 71, '2026-01-17 13:32:13'),
(50, 6, 'PAY-20260117114810-982', '0000-00-00 00:00:00', 2700.00, 'cash', '', NULL, 'completed', '', 71, '2026-01-17 13:48:10'),
(51, 7, 'PAY-20260117123539-553', '2026-01-17 14:35:39', 5000.00, 'card', '', '', 'completed', '', 55, '2026-01-17 14:35:39'),
(52, 9, 'PAY-20260117124042-904', '0000-00-00 00:00:00', 1500.00, 'cash', '', NULL, 'completed', '', 55, '2026-01-17 14:40:42'),
(53, 8, 'PAY-20260117142351-725', '0000-00-00 00:00:00', 3100.00, 'cash', '', NULL, 'completed', '', 55, '2026-01-17 16:23:51'),
(54, 2, 'PAY-20260117154727-896', '0000-00-00 00:00:00', 2600.00, 'cash', '', NULL, 'completed', '', 71, '2026-01-17 17:47:27'),
(55, 14, 'PAY-20260117171254-240', '0000-00-00 00:00:00', 2500.00, 'cash', '', NULL, 'completed', '', 71, '2026-01-17 19:12:54'),
(56, 10, 'PAY-20260118194641-886', '0000-00-00 00:00:00', 2375.00, 'cash', '', NULL, 'completed', '', 55, '2026-01-18 21:46:41'),
(57, 12, 'PAY-20260119123858-498', '0000-00-00 00:00:00', 2950.00, 'cash', '', NULL, 'completed', '', 71, '2026-01-19 14:38:58'),
(58, 5, 'PAY-20260119212857-500', '0000-00-00 00:00:00', 2650.00, 'cash', '', NULL, 'completed', '', 71, '2026-01-19 23:28:57'),
(59, 5, 'PAY-20260119214429-879', '0000-00-00 00:00:00', 1000.00, 'cash', '', NULL, 'completed', '', 71, '2026-01-19 23:44:29'),
(60, 2, 'PAY-20260119223040-823', '0000-00-00 00:00:00', 1350.00, 'mpesa', '', NULL, 'completed', '', 71, '2026-01-20 00:30:40');

--
-- Triggers `old_payments`
--
DELIMITER $$
CREATE TRIGGER `after_payment_insert` AFTER INSERT ON `old_payments` FOR EACH ROW BEGIN
    -- Update billing table if payment is allocated to a bill
    UPDATE billing b
    JOIN payment_allocations pa ON b.id = pa.reference_id 
    SET b.paid_amount = b.paid_amount + pa.allocated_amount,
        b.balance_amount = b.balance_amount - pa.allocated_amount,
        b.status = CASE 
            WHEN (b.balance_amount - pa.allocated_amount) <= 0 THEN 'paid'
            ELSE 'pending'
        END
    WHERE pa.payment_id = NEW.id 
    AND pa.service_type = 'general';
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `old_purchases`
--

CREATE TABLE `old_purchases` (
  `id` int(11) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `productcategory` varchar(50) NOT NULL,
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
  `status` enum('pending','received','cancelled') NOT NULL DEFAULT 'pending',
  `received_date` date DEFAULT NULL,
  `received_by` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `selling_price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `old_purchases`
--

INSERT INTO `old_purchases` (`id`, `company_name`, `productcategory`, `product_name`, `unit`, `packing_size`, `quantity`, `price`, `party_name`, `purchase_method`, `expiry_date`, `purchase_date`, `username`, `status`, `received_date`, `received_by`, `notes`, `selling_price`) VALUES
(1, 'Transchem Pharmaceutical', '', 'Eno', 'ml', '', 12, 300.00, 'Transchem pharmaceuticals', 'cash', '2026-10-24', '2025-09-24', 'admin', 'received', NULL, NULL, NULL, NULL),
(2, 'Transchem Pharmaceutical', '', 'Mara Moja', 'tablet', '0', 10, 15.00, 'Transchem pharmaceuticals', 'cash', '2027-01-25', '2025-09-24', 'admin', 'pending', NULL, NULL, NULL, NULL),
(3, 'MediPlugequipments', '', 'Surgical Gloves', 'box', '100', 50, 1500.00, 'MediCare Pharmaceuticals', 'cash', '2029-01-10', '2025-10-10', 'admin', 'pending', NULL, NULL, NULL, NULL),
(8, 'MediCare Pharmaceuticals', '', 'Surgical Spirit', 'ml', '100', 50, 750.00, 'MediCare Pharmaceuticals', 'Mpesa', '2030-07-17', '2025-10-10', 'admin', 'pending', NULL, NULL, NULL, NULL),
(9, 'Transchem Pharmaceutical', '', 'Paracetamol', 'capsule', '100', 50, 10.00, 'Transchem pharmaceuticals', 'cash', '2028-02-08', '2025-10-10', 'admin', 'pending', NULL, NULL, NULL, NULL),
(10, 'VeePharmaceuticals', '', 'Insulin', 'ml', '120', 50, 1250.00, 'VeePharmaceutical', 'cash', '2029-07-25', '2025-10-10', 'admin', 'pending', NULL, NULL, NULL, NULL),
(11, 'GracePharmacuticals', '', 'Cough syrup', 'ml', '100', 50, 500.00, 'GracePharmaceuticals', 'cash', '2028-11-08', '2025-10-10', 'admin', 'pending', NULL, NULL, NULL, NULL),
(12, 'VeePharmaceutical', '', 'Ibuprofen', 'box', '200', 5, 750.00, 'VeePharmaceutical', 'Mpesa', '2028-10-17', '2025-10-14', 'admin', 'pending', NULL, NULL, NULL, NULL),
(13, 'Transchem Pharmaceutical', '', 'Eno', 'sachet', '200', 5, 20.00, 'Transchem pharmaceuticals', 'cash', '2027-06-14', '2025-10-14', 'admin', 'pending', NULL, NULL, NULL, NULL),
(14, 'Transchem Pharmaceutical', '', 'Mara Moja', 'box', '100', 10, 10.00, 'Transchem pharmaceuticals', 'cash', '2028-05-14', '2025-10-14', 'admin', 'pending', NULL, NULL, NULL, NULL),
(15, 'Transchem Pharmaceutical', '', 'Eno', 'sachet', '200', 100, 10.00, 'Transchem pharmaceuticals', 'cash', '2028-10-17', '2025-10-14', 'admin', 'pending', NULL, NULL, NULL, NULL),
(16, 'MediPlugequipments', '', 'syringes', 'injection', '100', 5, 35.00, 'MediPlugequipments', 'Mpesa', '2029-06-14', '2025-10-14', 'admin', 'pending', NULL, NULL, NULL, NULL),
(17, 'MediCare Pharmaceuticals', '', 'Amoxicillin', 'box', '100', 50, 600.00, 'MediCare Pharmaceuticals', 'cash', '2029-10-14', '2025-10-14', 'admin', 'pending', NULL, NULL, NULL, NULL),
(18, 'Transchem Pharmaceutical', '', 'Mara Moja', 'box', '100', 100, 20.00, 'Transchem pharmaceuticals', 'cash', '2029-07-10', '2025-10-17', 'admin', 'pending', NULL, NULL, NULL, NULL),
(19, 'MediPlugequipments', '', 'Surgical Gloves', 'box', '100', 150, 750.00, 'Transchem pharmaceuticals', 'cash', '2029-10-17', '2025-10-17', 'admin', 'pending', NULL, NULL, NULL, NULL),
(20, 'MediPlugequipments', '', 'Surgical Gloves', 'box', '100', 200, 1500.00, 'MediPlugequipments', 'Mpesa', '2033-10-17', '2025-10-17', 'admin', 'pending', NULL, NULL, NULL, NULL),
(21, 'MediPlugequipments', '', 'Blood Pressure Monitor', 'box', '50', 2, 2500.00, 'MediPlugequipments', 'cash', '2029-12-23', '2025-10-23', 'admin', 'pending', NULL, NULL, NULL, NULL),
(22, 'GracePharmaceuticals', 'Medications', 'Ponstan', 'tablet', '50', 10, 50.00, 'GracePharmaceuticals', 'cash', '2028-06-23', '2025-10-23', 'admin', 'pending', NULL, NULL, NULL, NULL),
(23, 'VeePharmaceutical', 'Medications', 'Insulin', 'box', '5', 100, 1500.00, 'VeePharmaceutical', 'cash', '2029-10-23', '2025-10-23', 'admin', 'pending', NULL, NULL, NULL, NULL),
(24, 'OdwourInc', 'Office Supplies', 'Printers', 'each', '5', 10, 5500.00, 'OdwourInc', 'Mpesa', '2050-01-23', '2025-10-23', '', 'pending', NULL, NULL, NULL, NULL),
(25, 'OdwourInc', 'Office Supplies', 'Binders', 'box', '50', 100, 700.00, 'OdwourInc', 'Mpesa', '2028-01-23', '2025-10-23', '', 'pending', NULL, NULL, NULL, NULL),
(26, 'OdwourInc', 'Office Supplies', 'Binders', 'box', '50', 100, 700.00, 'OdwourInc', 'Mpesa', '2028-01-23', '2025-10-23', '', 'pending', NULL, NULL, NULL, NULL),
(27, 'VeePharmaceutical', 'Medications', 'asthma pump', 'pump', '10', 25, 550.00, 'VeePharmaceutical', 'cash', '2029-10-30', '2025-10-30', '', 'pending', NULL, NULL, NULL, NULL),
(28, 'MediCare Pharmaceuticals', 'Medications', 'Amoxicillin', 'mg', '100', 100, 750.00, 'MediCare Pharmaceuticals', 'cash', '2029-06-30', '2025-10-30', '', 'pending', NULL, NULL, NULL, NULL),
(29, 'Transchem pharmaceuticals', 'Medications', 'Agumentin', 'tablet', '100', 100, 650.00, 'Transchem pharmaceuticals', 'cash', '2028-06-30', '2025-10-30', '', 'pending', NULL, NULL, NULL, NULL),
(30, 'RahaPharmacy', 'Medications', 'Agumentin', 'tablet', '100', 150, 650.00, 'RahaPharmacy', 'cash', '2028-06-30', '2025-10-30', '', 'pending', NULL, NULL, NULL, NULL),
(31, 'VeePharmaceutical', 'Medications', 'Ibuprofen', 'box', '200', 100, 550.00, 'VeePharmaceutical', 'cash', '2029-02-28', '2025-10-30', '', 'pending', NULL, NULL, NULL, NULL),
(32, 'Transchem pharmaceuticals', 'Medications', 'Mara Moja', 'box', '100', 50, 15.00, 'Transchem pharmaceuticals', 'cash', '2025-11-30', '2025-11-02', '', 'received', '2026-01-10', 'admin@edoc.com', '', NULL),
(33, 'MediCare Pharmaceuticals', 'Medications', 'cetrizine', 'tablet', '10', 100, 10.00, 'MediCare Pharmaceuticals', 'Mpesa', '2027-01-10', '2026-01-10', 'admin@edoc.com', 'received', '2026-01-10', 'admin@edoc.com', '', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `outstanding_balances_view`
--

CREATE TABLE `outstanding_balances_view` (
  `patient_id` int(10) UNSIGNED DEFAULT NULL,
  `patient_name` varchar(255) DEFAULT NULL,
  `pending_invoices` bigint(21) DEFAULT NULL,
  `total_balance` decimal(32,2) DEFAULT NULL,
  `last_visit_date` date DEFAULT NULL,
  `invoice_numbers` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` varchar(20) NOT NULL,
  `pharmacy_patient_id` varchar(20) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `date_of_birth` date NOT NULL,
  `age` int(10) UNSIGNED NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `marital_status` enum('single','married','divorced','widowed') DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `preferred_contact` enum('Phone','Email','SMS') DEFAULT 'Phone',
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL COMMENT 'Height in cm',
  `weight` decimal(5,2) DEFAULT NULL COMMENT 'Weight in kg',
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `relationship` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `emergency_email` varchar(150) DEFAULT NULL,
  `insurance_provider` varchar(100) DEFAULT NULL,
  `insurance_id` varchar(50) DEFAULT NULL,
  `primary_physician` varchar(100) DEFAULT NULL,
  `medical_history` text DEFAULT NULL,
  `chronic_conditions` text DEFAULT NULL,
  `past_surgeries` text DEFAULT NULL,
  `family_history` text DEFAULT NULL,
  `additional_family_notes` text DEFAULT NULL,
  `smoking_status` enum('never','former','current') DEFAULT NULL,
  `alcohol_consumption` enum('none','social','moderate','heavy') DEFAULT NULL,
  `exercise_frequency` enum('none','rarely','1-2','3+') DEFAULT NULL,
  `dietary_habits` text DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `current_medications` text DEFAULT NULL,
  `status` enum('Active','Inactive','Deceased') DEFAULT 'Active',
  `last_visit` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `last_known_lat` decimal(10,8) DEFAULT NULL,
  `last_known_lng` decimal(11,8) DEFAULT NULL,
  `location_updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `patient_id`, `pharmacy_patient_id`, `first_name`, `middle_name`, `last_name`, `date_of_birth`, `age`, `gender`, `marital_status`, `phone`, `preferred_contact`, `email`, `address`, `height`, `weight`, `blood_type`, `emergency_contact_name`, `relationship`, `emergency_contact_phone`, `emergency_email`, `insurance_provider`, `insurance_id`, `primary_physician`, `medical_history`, `chronic_conditions`, `past_surgeries`, `family_history`, `additional_family_notes`, `smoking_status`, `alcohol_consumption`, `exercise_frequency`, `dietary_habits`, `allergies`, `current_medications`, `status`, `last_visit`, `created_at`, `updated_at`, `updated_by`, `last_known_lat`, `last_known_lng`, `location_updated_at`) VALUES
(1, 'PT000001', NULL, 'Stephanie', NULL, 'Chepkemoi', '2016-03-08', 9, 'Female', NULL, '0726964090', 'Phone', 'stef@gmail.com', 'Nairobi', 161.00, 67.00, 'A-', 'Gracel Chepkerui', NULL, '0726809063', NULL, 'APA', '123456', NULL, 'ASTHMATIC', 'Asthma', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', 'ASTHMA PUMP', 'Active', NULL, '2025-10-01 15:00:16', '2026-01-13 07:44:59', NULL, NULL, NULL, NULL),
(2, 'PT000002', NULL, 'Hillary', NULL, 'Manase', '2002-06-20', 23, 'Male', NULL, '0716864080', 'Phone', 'hilla@gmail.com', 'Nairobi', 175.00, 78.00, 'O-', 'Jacob Manase', NULL, '0700064083', NULL, '', '', NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', 'Active', NULL, '2025-10-01 15:23:59', '2025-10-01 15:29:11', NULL, NULL, NULL, NULL),
(4, 'PT000004', NULL, 'Bravin', NULL, 'Magothe', '1994-01-06', 32, 'Male', NULL, '012357981', 'Phone', 'bravin@gmail.com', 'Nairobi', 190.00, 90.00, 'AB-', '', NULL, '', NULL, '', '', NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', 'Active', NULL, '2025-10-01 15:32:41', '2026-02-05 18:27:49', NULL, NULL, NULL, NULL),
(5, 'PT000005', NULL, 'Tanya', NULL, 'Mutana', '2018-01-04', 7, 'Female', NULL, '0725678091', 'Phone', '', '', 140.00, 41.00, 'AB-', 'Joyce Malaba', NULL, '0725678091', NULL, '', '', NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', 'Active', NULL, '2025-10-01 21:42:50', '2025-10-01 21:42:50', NULL, NULL, NULL, NULL),
(6, 'PT000006', NULL, 'Alpha', NULL, 'Mwirigi', '2012-02-22', 13, 'Male', NULL, '0789547021', 'Phone', 'alpha@gmail.com', '', 150.00, 51.00, 'B-', 'Mary Murithi', NULL, '0789547021', NULL, 'Jubilee Insurance', '08A503', NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', 'Active', NULL, '2025-10-01 21:44:34', '2026-01-14 14:28:25', NULL, NULL, NULL, NULL),
(7, 'PT000007', NULL, 'Susan', NULL, 'Chemutai', '1987-04-28', 38, 'Female', NULL, '0722581212', 'Phone', 'susan@gmail.com', 'UpperHill, Nairobi', 172.00, 80.00, 'B+', 'Beatrice Chemutai', NULL, '0789465901', NULL, '', '', NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', 'Active', NULL, '2025-10-04 09:02:37', '2025-10-08 21:21:58', NULL, NULL, NULL, NULL),
(8, 'PT000008', NULL, 'Valery', NULL, 'Odeku', '2002-06-18', 23, 'Female', NULL, '0780654312', 'Phone', 'valery2@gmail.com', 'NairobiKenya', 167.00, 56.00, 'AB+', 'Mary Odeku', NULL, '0150986590', NULL, 'SHA', 'B0128X', NULL, 'Asthmatic', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', 'Inhaler', 'Active', NULL, '2025-10-06 11:00:45', '2026-01-17 13:16:07', NULL, NULL, NULL, NULL),
(9, 'PT000009', NULL, 'Hannah', NULL, 'Nyamwange', '1998-02-06', 27, 'Female', NULL, '0780954321', 'Phone', 'hannah@gmail.com', 'NairobiKenya', 177.00, 70.00, 'AB+', 'Henry Nyamwange', NULL, '0190875432', NULL, '', '', NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', 'Active', NULL, '2025-10-26 12:46:39', '2026-01-22 20:09:54', NULL, NULL, NULL, NULL),
(10, 'PT000010', NULL, 'Christopher', NULL, 'Mwasi', '1997-01-06', 29, 'Male', NULL, '0980564321', 'Phone', 'christopher@gmail.com', 'NairobiKenya', 185.00, 85.00, 'B-', 'Angela Mwasi', NULL, '0780908654', NULL, 'APA', 'A09X213', NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', 'Active', NULL, '2025-10-26 12:51:46', '2026-02-06 11:17:59', NULL, NULL, NULL, NULL),
(11, 'PT000011', NULL, 'Mary', NULL, 'Njeri', '1982-02-06', 43, 'Female', NULL, '0780965432', 'Phone', 'njeri2@gmail.com', 'NairobiKenya', 163.00, 88.00, 'AB-', 'Bravin Mweni', NULL, '0124689043', NULL, '', '', NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', 'Active', NULL, '2025-10-27 07:59:10', '2025-10-27 07:59:18', NULL, NULL, NULL, NULL),
(12, 'PT000012', NULL, 'Phoebe', NULL, 'Ndalo', '2003-03-11', 22, 'Female', NULL, '0726963098', 'Phone', 'phoebe@gmail.com', 'NairobiKenya', 165.00, 60.00, 'B-', 'Sarah Ndalo', NULL, '0190765432', NULL, 'Jubilee Insurance', 'B0128XY90', NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', 'Active', NULL, '2025-11-02 10:51:21', '2026-01-16 07:53:04', NULL, NULL, NULL, NULL),
(13, 'PT000013', NULL, 'Sandra', NULL, 'Kosgei', '1998-10-05', 27, 'Female', 'single', '0725789012', 'Phone', 'sandra2@gmail.com', 'NairobiKenya', 167.00, 74.00, 'A-', 'Beatrice Kosgei', 'Mother', '0780954321', NULL, '', '', 'Dr.Collins', 'Type 1 Diabetic', 'Type 1 Diabetic', 'NONE', 'N/A', NULL, 'never', 'moderate', '1-2', NULL, 'N/A', 'Insulin', 'Active', NULL, '2025-11-06 08:26:38', '2026-02-02 12:08:17', NULL, NULL, NULL, NULL),
(14, 'PT000014', NULL, 'Emmanuel', NULL, 'Maothe', '2000-03-15', 25, 'Male', NULL, '0724578908', 'Phone', 'emmanuel32@gmail.com', 'NairobiKenya', 180.00, 75.00, 'B+', 'Grace Magothe', NULL, '07906543212', NULL, '', '', NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', 'Active', NULL, '2025-11-14 06:58:27', '2026-02-05 18:30:20', NULL, NULL, NULL, NULL),
(15, 'PT000015', NULL, 'Ria', NULL, 'Kumari', '1999-01-11', 27, 'Female', NULL, '0780654321', 'Phone', 'ria@gmail.com', '', 170.00, 75.00, 'A+', 'Japhthur Kumari', NULL, '0110986542', NULL, '', '', NULL, 'Arthritis', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', 'Active', NULL, '2026-01-11 19:14:08', '2026-02-05 17:26:42', NULL, NULL, NULL, NULL),
(16, 'PT000016', NULL, 'Kelvin', NULL, 'Mwasi', '2000-10-06', 25, '', NULL, '072290764321', 'Phone', 'kelvin@gmail.com', 'Nairobi, Langata', 170.00, 70.00, 'AB+', 'Mary Mwasi', NULL, '0190821432', NULL, '', '', NULL, 'N/A', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'N/A', 'N/A', 'Active', NULL, '2026-01-20 10:08:53', '2026-01-20 10:35:36', NULL, NULL, NULL, NULL),
(31, 'PT000017', NULL, 'Achieng', NULL, 'Odwour', '2000-01-20', 26, 'Female', NULL, '0180954321', 'Phone', 'achieng@gmail.com', 'Nairobi', 163.00, 70.00, 'AB+', 'Godfrey Odwour', NULL, '0785432109', NULL, '', '', NULL, 'N/A', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'N/A', 'N/A', 'Active', NULL, '2026-01-20 10:58:05', '2026-02-06 10:35:43', NULL, NULL, NULL, NULL),
(32, 'PT000018', NULL, 'Brianna', NULL, 'Wambui', '1998-10-06', 27, '', NULL, '0700132167', 'Phone', 'bri@gmail.com', 'Nairobi', 163.00, 65.00, 'O+', 'Jane Wairimu', NULL, '0100908721', NULL, '', '', NULL, 'N/A', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'N/A', 'N/A', 'Active', NULL, '2026-01-20 18:39:45', '2026-01-20 18:39:45', NULL, NULL, NULL, NULL),
(33, 'PT000019', NULL, 'Nadia', NULL, 'Mutana', '2018-10-06', 7, 'Female', NULL, '071230908076', 'Phone', 'miriam33@gmail.com', 'Nairobi', 150.00, 43.00, 'O-', 'Miriam Mutana', NULL, '071230908076', NULL, '', '', NULL, 'ASTHMA', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', 'Active', NULL, '2026-01-21 11:44:10', '2026-01-21 11:45:41', NULL, NULL, NULL, NULL);

--
-- Triggers `patients`
--
DELIMITER $$
CREATE TRIGGER `log_patient_creation` AFTER INSERT ON `patients` FOR EACH ROW BEGIN
    INSERT INTO audit_logs (user_id, action, description, timestamp)
    VALUES (@current_user_id, 'create', CONCAT('Created patient: ', NEW.first_name, ' ', NEW.last_name), NOW());
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `patient_medical_details`
--

CREATE TABLE `patient_medical_details` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `smoking_status` enum('never','former','current') DEFAULT NULL,
  `alcohol_consumption` enum('none','social','moderate','heavy') DEFAULT NULL,
  `exercise_frequency` enum('none','rarely','1-2','3+') DEFAULT NULL,
  `dietary_habits` text DEFAULT NULL,
  `family_history` text DEFAULT NULL,
  `additional_family_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_medical_details`
--

INSERT INTO `patient_medical_details` (`id`, `patient_id`, `smoking_status`, `alcohol_consumption`, `exercise_frequency`, `dietary_habits`, `family_history`, `additional_family_notes`, `created_at`, `updated_at`) VALUES
(1, 8, 'never', 'social', '1-2', '', 'Diabetes, Hypertension, Asthma', '', '2025-10-06 11:00:45', NULL),
(2, 9, 'former', 'social', 'rarely', '', '', '', '2025-10-26 12:46:39', NULL),
(3, 10, 'current', 'heavy', '3+', '', '', '', '2025-10-26 12:51:46', NULL),
(4, 11, 'former', 'none', 'rarely', '', 'Diabetes, Hypertension', '', '2025-10-27 07:59:10', NULL),
(5, 12, 'never', 'social', '1-2', '', 'Hypertension', '', '2025-11-02 10:51:21', NULL),
(6, 13, 'never', 'moderate', 'none', '', '', '', '2025-11-06 08:26:38', NULL),
(7, 14, 'current', 'moderate', '3+', '', 'Hypertension, Cancer', '', '2025-11-14 06:58:27', NULL),
(8, 0, 'current', 'moderate', '3+', '', '', '', '2026-01-20 10:08:53', NULL),
(9, 31, 'never', 'moderate', '1-2', '', '', '', '2026-01-20 10:58:05', NULL),
(10, 32, 'never', 'social', 'rarely', '', '', '', '2026-01-20 18:39:45', NULL),
(11, 33, 'never', 'none', 'none', '', 'Asthma', '', '2026-01-21 11:44:10', NULL);

-- --------------------------------------------------------


--
-- Table structure for table `patient_medications`
--

CREATE TABLE `patient_medications` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `record_id` int(10) UNSIGNED DEFAULT NULL,
  `medication_name` varchar(255) NOT NULL,
  `dosage` varchar(100) DEFAULT NULL,
  `frequency` varchar(100) DEFAULT NULL,
  `route` enum('oral','injection','topical','intravenous','inhalation','other') DEFAULT 'oral',
  `duration` varchar(100) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `prescribing_doctor` int(10) UNSIGNED DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `status` enum('active','completed','discontinued','on_hold') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_medications`
--

INSERT INTO `patient_medications` (`id`, `patient_id`, `record_id`, `medication_name`, `dosage`, `frequency`, `route`, `duration`, `start_date`, `end_date`, `prescribing_doctor`, `instructions`, `status`, `created_at`) VALUES
(1, 1, NULL, 'Amoxicillin', '500mg', 'Three times daily', 'oral', '7 days', '2025-10-08', NULL, 55, 'Take after meals', 'active', '2025-10-08 15:15:23'),
(2, 1, NULL, 'Amoxicillin', '500mg', 'Three times daily', 'oral', '7 days', '2025-10-08', NULL, 55, 'Take after meals', 'active', '2025-10-08 15:15:49'),
(3, 1, NULL, 'Amoxicillin', '500mg', 'Three times daily', 'oral', '7 days', '2025-10-08', NULL, 55, 'Take after meals', 'active', '2025-10-08 15:16:08'),
(4, 7, NULL, 'Ibuprufen', '1000mg', 'Three times daily', 'oral', '7 days', '2025-10-08', NULL, 55, 'Take one tablet after meals, Three times a day', 'active', '2025-10-08 21:23:03'),
(5, 7, NULL, 'Panadol', '200mg', 'Twice daily', 'oral', '3 days', '2025-10-08', NULL, 55, 'Stop once headache reduces', 'active', '2025-10-08 21:26:09'),
(6, 7, NULL, 'Panadol', '200mg', 'Twice daily', 'oral', '3 days', '2025-10-08', NULL, 55, 'Stop once headache reduces', 'active', '2025-10-08 21:35:28'),
(7, 4, NULL, 'Amoxicillin', '1000mg', 'Three times daily', 'oral', '7 days', '2025-10-18', NULL, 55, 'Take 3 times a day after meals.', 'active', '2025-10-18 17:39:11'),
(8, 7, NULL, 'Eno', '200mg', 'Twice daily', 'oral', '3 days', '2025-10-19', NULL, 55, 'Take when feeling bloated.', 'active', '2025-10-19 19:01:28');

-- --------------------------------------------------------


--
-- Table structure for table `patient_sequence`
--

CREATE TABLE `patient_sequence` (
  `last_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_sequence`
--

INSERT INTO `patient_sequence` (`last_id`) VALUES
(2);

-- --------------------------------------------------------

--
-- Table structure for table `patient_visits`
--

CREATE TABLE `patient_visits` (
  `id` int(10) UNSIGNED NOT NULL,
  `visit_number` varchar(50) NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `visit_date` datetime DEFAULT current_timestamp(),
  `visit_type` enum('consultation','emergency','followup','procedure') DEFAULT 'consultation',
  `chief_complaint` text DEFAULT NULL,
  `triage_nurse_id` int(10) UNSIGNED DEFAULT NULL,
  `assigned_doctor_id` int(10) UNSIGNED DEFAULT NULL,
  `visit_status` enum('triage','doctor_assigned','in_progress','awaiting_labs','awaiting_imaging','treatment','discharged','admitted') DEFAULT 'triage',
  `final_diagnosis` text DEFAULT NULL,
  `treatment_summary` text DEFAULT NULL,
  `discharge_instructions` text DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_visits`
--

INSERT INTO `patient_visits` (`id`, `visit_number`, `patient_id`, `visit_date`, `visit_type`, `chief_complaint`, `triage_nurse_id`, `assigned_doctor_id`, `visit_status`, `final_diagnosis`, `treatment_summary`, `discharge_instructions`, `follow_up_date`, `created_by`, `created_at`, `updated_at`) VALUES
(1, '', 10, '2025-10-27 23:52:13', 'consultation', 'Complaining of extreme joint and knee pains.', 55, NULL, '', NULL, NULL, NULL, NULL, 55, '2025-10-27 20:52:13', '2025-10-27 21:18:43'),
(3, 'VIS-20251028-8901', 9, '2025-10-28 02:42:58', 'consultation', 'Experiencing extreme stomach pains and diarrhea.', 55, NULL, '', NULL, NULL, NULL, NULL, 55, '2025-10-27 23:42:58', '2025-10-27 23:52:21');

--
-- Triggers `patient_visits`
--
DELIMITER $$
CREATE TRIGGER `generate_visit_number` BEFORE INSERT ON `patient_visits` FOR EACH ROW BEGIN
    IF NEW.visit_number IS NULL THEN
        SET NEW.visit_number = CONCAT('VISIT-', DATE_FORMAT(NOW(), '%Y%m%d-'), LPAD(FLOOR(RAND() * 10000), 4, '0'));
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `patient_visit_charges_view`
--

CREATE TABLE `patient_visit_charges_view` (
  `patient_id` decimal(10,0) DEFAULT NULL,
  `source_id` decimal(10,0) DEFAULT NULL,
  `reference_id` varchar(50) DEFAULT NULL,
  `charge_type` varchar(20) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `charge_date` datetime DEFAULT NULL,
  `charge_status` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_vitals`
--

CREATE TABLE `patient_vitals` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `record_id` int(10) UNSIGNED DEFAULT NULL,
  `blood_pressure_systolic` int(11) DEFAULT NULL,
  `blood_pressure_diastolic` int(11) DEFAULT NULL,
  `heart_rate` int(11) DEFAULT NULL COMMENT 'BPM',
  `temperature` decimal(4,1) DEFAULT NULL COMMENT 'Fahrenheit',
  `oxygen_saturation` int(11) DEFAULT NULL COMMENT 'Percentage',
  `respiratory_rate` int(11) DEFAULT NULL COMMENT 'Breaths per minute',
  `weight` decimal(5,2) DEFAULT NULL COMMENT 'Kilograms',
  `height` decimal(5,2) DEFAULT NULL COMMENT 'Centimeters',
  `bmi` decimal(4,2) DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_vitals`
--

INSERT INTO `patient_vitals` (`id`, `patient_id`, `record_id`, `blood_pressure_systolic`, `blood_pressure_diastolic`, `heart_rate`, `temperature`, `oxygen_saturation`, `respiratory_rate`, `weight`, `height`, `bmi`, `recorded_by`, `recorded_at`, `notes`) VALUES
(1, 8, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 56.00, 167.00, 20.08, 55, '2025-10-06 11:00:45', NULL),
(2, 1, NULL, 120, 82, 75, 34.0, 98, 18, 67.00, 161.00, 25.85, 55, '2025-10-08 15:13:39', NULL),
(3, 1, NULL, 120, 82, 75, 34.0, 98, 18, 67.00, 161.00, 25.85, 55, '2025-10-08 15:14:12', NULL),
(4, 7, NULL, 132, 86, 74, 34.0, 98, 21, 80.00, 172.00, 27.04, 55, '2025-10-08 21:21:58', NULL),
(5, 4, NULL, 134, 84, 88, 38.0, 96, 25, 90.00, 190.00, 24.93, 55, '2025-10-18 17:38:22', NULL),
(6, 9, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 68.00, 176.00, 21.95, 55, '2025-10-26 12:46:39', NULL),
(7, 10, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 85.00, 185.00, 24.84, 55, '2025-10-26 12:51:46', NULL),
(8, 11, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 88.00, 163.00, 33.12, 55, '2025-10-27 07:59:10', NULL),
(9, 9, NULL, 128, 84, 91, 34.0, 98, 25, 70.00, 177.00, 22.34, 55, '2025-10-27 20:21:02', NULL),
(10, 10, NULL, 142, 87, 88, 36.0, 94, NULL, NULL, NULL, NULL, 55, '2025-10-27 21:00:22', NULL),
(11, 9, NULL, 128, 65, 82, 35.0, 97, NULL, NULL, NULL, NULL, 55, '2025-10-27 23:43:52', NULL),
(12, 11, NULL, 126, 83, 77, 36.0, 96, 21, 71.00, 175.00, 23.18, 55, '2025-10-30 15:44:58', ''),
(13, 8, NULL, 128, 84, 75, 34.0, 94, 18, 74.00, 169.00, 25.91, 55, '2025-10-30 15:46:08', ''),
(14, 1, NULL, 135, 84, 75, 34.0, 95, 22, 72.00, 175.00, 23.51, 55, '2025-10-30 15:46:42', ''),
(15, 7, NULL, 128, 84, 77, 35.0, 95, 20, 82.00, 165.00, 30.12, 69, '2025-10-30 20:35:34', ''),
(16, 12, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 60.00, 165.00, 22.04, 55, '2025-11-02 10:51:21', NULL),
(17, 12, NULL, 132, 84, 75, 35.0, 96, 18, 65.00, 165.00, 23.88, 63, '2025-11-02 11:03:55', ''),
(18, 11, NULL, 145, 92, 94, 36.0, 99, 25, 68.00, 182.00, 20.53, 72, '2025-11-02 18:38:35', ''),
(19, 2, NULL, 120, 84, 76, 36.0, 94, 18, 75.00, 175.00, 24.49, 55, '2025-11-03 20:49:36', ''),
(20, 12, NULL, 132, 86, 77, 35.0, 95, 18, 67.00, 167.00, 24.02, 63, '2025-11-04 07:35:25', ''),
(21, 13, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 74.00, 167.00, 26.53, 55, '2025-11-06 08:26:38', NULL),
(22, 13, NULL, 124, 82, 67, 34.7, 95, 18, 74.00, 167.00, 26.53, 55, '2025-11-06 08:28:27', ''),
(23, 14, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 75.00, 180.00, 23.15, 56, '2025-11-14 06:58:27', NULL),
(24, 14, NULL, 132, 84, 88, 37.0, 96, 22, 78.00, 180.00, 24.07, 63, '2025-11-14 07:02:17', ''),
(25, 13, NULL, 132, 84, 77, 36.0, 97, 21, 67.00, 167.00, 24.02, 55, '2025-11-17 20:31:43', ''),
(26, 8, NULL, 124, 73, 88, 36.0, 96, 18, 54.00, 157.00, 21.91, 59, '2026-01-09 08:55:16', ''),
(27, 8, NULL, 124, 84, 77, 36.0, 98, 18, 75.00, 167.00, 26.89, 55, '2026-01-09 20:51:35', ''),
(28, 6, NULL, 123, 80, 77, 37.0, 96, 18, 75.00, 175.00, 24.49, 69, '2026-01-15 11:07:40', ''),
(29, 6, NULL, 122, 86, 77, 36.0, 95, 18, 75.00, 168.00, 26.57, 63, '2026-01-15 11:29:41', ''),
(30, 9, NULL, 122, 85, 77, 35.0, 95, 18, 71.00, 169.00, 24.86, 63, '2026-01-15 20:07:45', ''),
(31, 2, NULL, 124, 86, 77, 35.0, 96, 18, 78.00, 173.00, 26.06, 69, '2026-01-16 08:07:43', ''),
(32, 7, NULL, 122, 86, 74, 37.0, 99, 20, 74.00, 172.00, 25.01, 60, '2026-01-16 10:16:06', ''),
(33, 10, NULL, 132, 87, 88, 37.0, 96, 18, 75.00, 178.00, 23.67, 59, '2026-01-16 20:55:21', ''),
(34, 12, NULL, 132, 85, 77, 36.0, 96, 18, 68.00, 165.00, 24.98, 69, '2026-01-17 07:28:49', ''),
(35, 15, NULL, 123, 84, 75, 36.0, 95, 18, 68.00, 170.00, 23.53, 67, '2026-01-17 07:41:32', ''),
(36, 1, NULL, 142, 98, 88, 35.0, 98, 19, 67.00, 163.00, 25.22, 63, '2026-01-17 08:29:43', ''),
(37, 11, NULL, 132, 88, 77, 34.0, 96, 18, 72.00, 167.00, 25.82, 63, '2026-01-17 08:35:03', ''),
(38, 6, NULL, 132, 89, 88, 35.0, 96, 18, 74.00, 168.00, 26.22, 69, '2026-01-17 10:42:51', ''),
(39, 14, NULL, 147, 85, 88, 35.0, 86, 18, 75.00, 167.00, 26.89, 55, '2026-01-17 16:08:22', ''),
(40, 12, NULL, 132, 88, 77, 36.0, 99, 18, 72.00, 167.00, 25.82, 63, '2026-01-19 10:43:20', ''),
(41, 12, NULL, 144, 89, 89, 36.0, 81, 19, 54.00, 159.00, 21.36, 63, '2026-01-19 10:44:01', ''),
(42, 2, NULL, 122, 85, 77, 35.0, 87, 18, 75.00, 180.00, 23.15, 63, '2026-01-19 21:04:10', ''),
(43, 11, NULL, 132, 89, 89, 35.0, 85, 18, 67.00, 167.00, 24.02, 59, '2026-01-20 08:56:35', ''),
(44, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 70.00, 170.00, 24.22, 55, '2026-01-20 10:08:53', NULL),
(45, 0, NULL, 132, 87, 88, 36.0, 95, 18, 70.00, 180.00, 21.60, 64, '2026-01-20 10:10:56', ''),
(46, 16, NULL, 132, 85, 77, 35.0, 99, 18, 70.00, 180.00, 21.60, 56, '2026-01-20 10:42:47', ''),
(47, 31, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 70.00, 163.00, 26.35, 55, '2026-01-20 10:58:05', NULL),
(48, 31, NULL, 132, 84, 77, 34.0, 98, 18, 70.00, 164.00, 26.03, 55, '2026-01-20 10:59:17', ''),
(49, 15, NULL, 132, 87, 77, 36.0, 99, 18, 75.00, 167.00, 26.89, 56, '2026-01-20 13:07:13', ''),
(50, 32, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 65.00, 163.00, 24.46, 55, '2026-01-20 18:39:45', NULL),
(51, 32, NULL, 134, 84, 77, 35.0, 95, 18, 65.00, 164.00, 24.17, 55, '2026-01-20 18:43:56', ''),
(52, 7, NULL, 140, 90, 77, 35.0, 85, 18, 67.00, 167.00, 24.02, 60, '2026-01-21 08:40:52', ''),
(53, 33, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 43.00, 150.00, 19.11, 56, '2026-01-21 11:44:10', NULL),
(54, 33, NULL, 132, 87, 77, 34.0, 85, 15, 43.00, 150.00, 19.11, 55, '2026-01-21 11:47:08', ''),
(55, 9, NULL, 138, 88, 77, 35.0, 96, 18, 67.00, 167.00, 24.02, 55, '2026-01-22 19:50:50', ''),
(56, 12, NULL, 124, 81, 67, 36.0, 98, 16, 63.00, 166.00, 22.86, 55, '2026-02-05 14:13:10', ''),
(57, 33, NULL, 120, 81, 88, 34.2, 96, 18, 55.00, 156.00, 22.60, 55, '2026-02-06 08:37:53', ''),
(58, 32, NULL, 122, 86, 77, 35.0, 96, 16, 67.00, 170.00, 23.18, 56, '2026-02-06 08:44:06', ''),
(59, 31, NULL, 122, 85, 77, 35.0, 95, 18, 70.00, 167.00, 25.10, 55, '2026-02-06 10:51:06', ''),
(60, 13, NULL, 132, 86, 88, 34.0, 96, 18, 80.00, 167.00, 28.69, 68, '2026-02-06 11:00:21', ''),
(61, 10, NULL, 132, 88, 77, 32.0, 95, 18, 75.00, 175.00, 24.49, 55, '2026-02-06 11:51:49', '');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `payment_number` varchar(50) DEFAULT NULL,
  `patient_id` int(11) NOT NULL,
  `service_type` varchar(50) NOT NULL,
  `service_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `payment_date` datetime DEFAULT current_timestamp(),
  `reference_number` varchar(100) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'completed',
  `received_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `payment_number`, `patient_id`, `service_type`, `service_id`, `amount`, `payment_method`, `payment_date`, `reference_number`, `payment_reference`, `status`, `received_by`, `notes`, `created_at`) VALUES
(1, NULL, 15, 'unified', 0, 1900.00, 'cash', '2026-01-20 01:21:33', 'PAY-20260119232133-758', NULL, 'completed', NULL, '', '2026-01-19 22:21:33'),
(2, NULL, 4, 'unified', 0, 5200.00, 'cash', '2026-01-20 01:28:27', 'PAY-20260119232827-108', NULL, 'completed', NULL, '', '2026-01-19 22:28:27'),
(3, NULL, 6, 'unified', 0, 3700.00, 'cash', '2026-01-20 12:53:19', 'PAY-20260120105319-229', NULL, 'completed', NULL, '', '2026-01-20 09:53:19'),
(4, NULL, 16, 'unified', 0, 500.00, 'cash', '2026-01-20 13:48:00', 'PAY-20260120114800-157', NULL, 'completed', NULL, '', '2026-01-20 10:48:00'),
(5, NULL, 11, 'unified', 0, 120.00, 'cash', '2026-01-20 13:48:17', 'PAY-20260120114817-188', NULL, 'completed', NULL, '', '2026-01-20 10:48:17'),
(6, NULL, 4, 'unified', 0, 800.00, 'cash', '2026-01-20 15:17:29', 'PAY-20260120131729-755', NULL, 'completed', NULL, '', '2026-01-20 12:17:29'),
(7, NULL, 31, 'unified', 0, 3400.00, 'cash', '2026-01-20 15:37:53', 'PAY-20260120133753-208', NULL, 'completed', NULL, '', '2026-01-20 12:37:53'),
(8, NULL, 16, 'unified', 0, 1600.00, 'cash', '2026-01-20 15:38:51', 'PAY-20260120133851-698', NULL, 'completed', NULL, '', '2026-01-20 12:38:51'),
(9, NULL, 4, 'unified', 0, 2000.00, 'cash', '2026-01-20 15:41:22', 'PAY-20260120134122-510', NULL, 'completed', NULL, '', '2026-01-20 12:41:22'),
(24, 'PAY-20260120183521-965', 15, '', 0, 2600.00, 'cash', '2026-01-20 20:35:21', NULL, '', 'completed', 71, '', '2026-01-20 17:35:21'),
(32, 'PAY-20260120210632-908', 32, '', 0, 2950.00, 'cash', '2026-01-20 23:06:32', NULL, '', 'completed', 72, '', '2026-01-20 20:06:32'),
(33, 'PAY-20260120211641-284', 10, '', 0, 1100.00, 'cash', '2026-01-20 23:16:41', NULL, '', 'completed', 71, '', '2026-01-20 20:16:41'),
(34, 'PAY-20260120211915-115', 10, '', 0, 1000.00, 'cash', '2026-01-20 23:19:15', NULL, '', 'completed', 71, '', '2026-01-20 20:19:15');

-- --------------------------------------------------------

--
-- Table structure for table `payment_allocations`
--

CREATE TABLE `payment_allocations` (
  `id` int(10) UNSIGNED NOT NULL,
  `payment_id` int(10) UNSIGNED NOT NULL,
  `service_type` enum('consultation','lab','pharmacy','admission','consolidated') NOT NULL,
  `reference_id` int(10) UNSIGNED NOT NULL,
  `allocated_amount` decimal(10,2) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_allocations`
--

INSERT INTO `payment_allocations` (`id`, `payment_id`, `service_type`, `reference_id`, `allocated_amount`, `created_at`) VALUES
(1, 1, 'lab', 7, 800.00, '2025-11-02 13:44:16'),
(2, 1, '', 6, 500.00, '2025-11-02 13:44:16'),
(3, 2, 'lab', 7, 800.00, '2025-11-02 13:44:44'),
(4, 2, '', 6, 500.00, '2025-11-02 13:44:44'),
(5, 3, '', 11, 1500.00, '2025-11-02 13:46:16'),
(6, 4, '', 4, 500.00, '2025-11-02 13:47:01'),
(7, 4, '', 5, 800.00, '2025-11-02 13:47:01'),
(8, 5, 'consultation', 19, 500.00, '2025-11-02 13:47:38'),
(9, 5, '', 2, 500.00, '2025-11-02 13:47:38'),
(10, 7, 'lab', 13, 800.00, '2025-11-02 18:23:36'),
(11, 8, 'lab', 13, 800.00, '2025-11-02 18:23:49'),
(12, 9, 'lab', 13, 800.00, '2025-11-02 18:23:58'),
(13, 10, 'lab', 13, 800.00, '2025-11-02 18:24:25'),
(14, 11, 'lab', 13, 800.00, '2025-11-02 18:24:40'),
(15, 12, '', 15, 500.00, '2025-11-02 18:29:35'),
(16, 13, 'lab', 15, 800.00, '2025-11-04 09:56:51'),
(17, 13, '', 17, 500.00, '2025-11-04 09:56:51'),
(18, 14, 'lab', 15, 800.00, '2025-11-04 09:57:15'),
(19, 14, '', 17, 500.00, '2025-11-04 09:57:15'),
(20, 15, 'lab', 16, 800.00, '2025-11-04 11:23:21'),
(21, 15, '', 18, 500.00, '2025-11-04 11:23:21'),
(22, 15, 'pharmacy', 37, 1725.00, '2025-11-04 11:23:21'),
(23, 16, 'lab', 14, 2400.00, '2025-11-06 12:48:27'),
(24, 17, 'lab', 8, 800.00, '2025-11-06 12:48:42'),
(25, 18, 'lab', 17, 1350.00, '2025-11-06 13:07:04'),
(26, 18, '', 20, 500.00, '2025-11-06 13:07:04'),
(27, 18, 'pharmacy', 39, 2400.00, '2025-11-06 13:07:04'),
(28, 23, 'lab', 18, 800.00, '2025-11-14 10:17:53'),
(29, 23, '', 21, 500.00, '2025-11-14 10:17:53'),
(30, 23, 'pharmacy', 40, 1100.00, '2025-11-14 10:17:53'),
(31, 24, '', 22, 500.00, '2025-11-22 23:12:41'),
(32, 25, '', 23, 500.00, '2025-11-22 23:13:02'),
(33, 27, 'lab', 19, 1350.00, '2026-01-09 14:59:07'),
(34, 27, '', 24, 500.00, '2026-01-09 14:59:07'),
(35, 27, 'pharmacy', 41, 1100.00, '2026-01-09 14:59:07'),
(36, 28, '', 26, 5000.00, '2026-01-10 14:38:51'),
(37, 28, '', 27, 1500.00, '2026-01-10 14:38:51'),
(38, 29, '', 28, 500.00, '2026-01-15 14:35:03'),
(39, 29, 'pharmacy', 45, 350.00, '2026-01-15 14:35:03'),
(41, 32, '', 25, 500.00, '2026-01-15 23:02:58'),
(42, 32, 'pharmacy', 1, 150.00, '2026-01-15 23:02:58'),
(43, 33, 'lab', 20, 800.00, '2026-01-16 00:03:19'),
(44, 33, '', 26, 500.00, '2026-01-16 00:03:19'),
(45, 33, 'pharmacy', 2, 1600.00, '2026-01-16 00:03:19'),
(51, 39, '', 32, 500.00, '2026-01-16 12:24:55'),
(52, 39, 'pharmacy', 3, 825.00, '2026-01-16 12:24:55'),
(54, 41, 'consultation', 39, 500.00, '2026-01-16 14:01:17'),
(55, 41, 'lab', 21, 2350.00, '2026-01-16 14:01:17'),
(56, 41, 'pharmacy', 4, 850.00, '2026-01-16 14:01:17'),
(57, 42, '', 27, 500.00, '2026-01-16 14:32:37'),
(58, 43, 'consultation', 19, 500.00, '2026-01-17 00:05:20'),
(59, 43, 'lab', 22, 1950.00, '2026-01-17 00:05:20'),
(60, 43, '', 31, 500.00, '2026-01-17 00:05:20'),
(61, 43, 'pharmacy', 5, 1550.00, '2026-01-17 00:05:20'),
(62, 44, 'consultation', 42, 500.00, '2026-01-17 10:55:01'),
(63, 44, 'pharmacy', 7, 700.00, '2026-01-17 10:55:01'),
(64, 45, 'consultation', 44, 500.00, '2026-01-17 11:44:51'),
(65, 45, 'lab', 24, 1200.00, '2026-01-17 11:44:51'),
(66, 45, 'pharmacy', 8, 825.00, '2026-01-17 11:44:51'),
(67, 46, 'lab', 23, 1400.00, '2026-01-17 11:52:14'),
(68, 46, 'pharmacy', 6, 1550.00, '2026-01-17 11:52:14'),
(69, 47, 'consultation', 43, 5000.00, '2026-01-17 12:04:01'),
(70, 47, '', 38, 2500.00, '2026-01-17 12:04:01'),
(71, 48, 'consultation', 47, 500.00, '2026-01-17 13:29:04'),
(72, 48, 'lab', 25, 550.00, '2026-01-17 13:29:04'),
(73, 48, 'pharmacy', 9, 825.00, '2026-01-17 13:29:04'),
(74, 49, '', 44, 1500.00, '2026-01-17 13:32:13'),
(75, 50, 'consultation', 49, 1200.00, '2026-01-17 13:48:10'),
(76, 50, '', 46, 1500.00, '2026-01-17 13:48:10'),
(77, 51, '', 52, 5000.00, '2026-01-17 14:35:39'),
(78, 52, '', 50, 1500.00, '2026-01-17 14:40:42'),
(79, 53, 'consultation', 50, 1200.00, '2026-01-17 16:23:51'),
(80, 53, '', 53, 1000.00, '2026-01-17 16:23:51'),
(81, 53, 'pharmacy', 10, 900.00, '2026-01-17 16:23:51'),
(82, 54, 'consultation', 48, 1200.00, '2026-01-17 17:47:27'),
(83, 54, 'lab', 26, 550.00, '2026-01-17 17:47:27'),
(84, 54, 'pharmacy', 11, 850.00, '2026-01-17 17:47:27'),
(85, 55, 'consultation', 51, 500.00, '2026-01-17 19:12:54'),
(86, 55, 'admission', 57, 2000.00, '2026-01-17 19:12:54'),
(87, 56, 'consultation', 19, 500.00, '2026-01-18 21:46:41'),
(88, 56, 'consultation', 52, 1200.00, '2026-01-18 21:46:41'),
(89, 56, 'pharmacy', 12, 600.00, '2026-01-18 21:46:41'),
(90, 56, 'pharmacy', 13, 75.00, '2026-01-18 21:46:41'),
(91, 57, 'consultation', 54, 500.00, '2026-01-19 14:38:58'),
(92, 57, 'lab', 27, 800.00, '2026-01-19 14:38:58'),
(93, 57, 'pharmacy', 15, 1650.00, '2026-01-19 14:38:58'),
(94, 58, 'consultation', 53, 1200.00, '2026-01-19 23:28:57'),
(95, 58, 'admission', 65, 1000.00, '2026-01-19 23:28:57'),
(96, 58, 'pharmacy', 16, 450.00, '2026-01-19 23:28:57'),
(97, 59, 'admission', 65, 1000.00, '2026-01-19 23:44:29'),
(98, 60, 'consultation', 55, 500.00, '2026-01-20 00:30:40'),
(99, 60, 'pharmacy', 17, 850.00, '2026-01-20 00:30:40'),
(100, 1, 'consultation', 57, 500.00, '2026-01-20 01:21:33'),
(101, 1, 'pharmacy', 18, 1400.00, '2026-01-20 01:21:33'),
(102, 2, '', 72, 2500.00, '2026-01-20 01:28:27'),
(103, 2, 'consultation', 58, 1200.00, '2026-01-20 01:28:27'),
(104, 2, 'pharmacy', 19, 1500.00, '2026-01-20 01:28:27'),
(105, 0, '', 0, 500.00, '2026-01-20 12:53:19'),
(106, 0, 'pharmacy', 0, 1700.00, '2026-01-20 12:53:19'),
(107, 0, '', 0, 500.00, '2026-01-20 13:48:00'),
(108, 0, 'pharmacy', 0, 120.00, '2026-01-20 13:48:17'),
(109, 0, '', 0, 800.00, '2026-01-20 15:17:29'),
(110, 0, 'lab', 52, 800.00, '2026-01-20 15:37:53'),
(111, 0, '', 0, 500.00, '2026-01-20 15:37:53'),
(112, 0, 'pharmacy', 0, 2100.00, '2026-01-20 15:37:53'),
(113, 0, 'lab', 51, 800.00, '2026-01-20 15:38:51'),
(114, 0, 'lab', 28, 800.00, '2026-01-20 15:41:22'),
(115, 0, '', 75, 1200.00, '2026-01-20 15:41:22'),
(116, 24, 'consultation', 127, 500.00, '2026-01-20 20:35:21'),
(117, 24, 'lab', 53, 400.00, '2026-01-20 20:35:22'),
(118, 24, 'pharmacy', 0, 1700.00, '2026-01-20 20:35:22'),
(119, 32, 'consultation', 128, 500.00, '2026-01-20 23:06:32'),
(120, 32, 'lab', 54, 1200.00, '2026-01-20 23:06:32'),
(121, 32, 'pharmacy', 0, 1250.00, '2026-01-20 23:06:32'),
(122, 33, 'consultation', 19, 500.00, '2026-01-20 23:16:41'),
(123, 33, 'consultation', 129, 500.00, '2026-01-20 23:16:41'),
(124, 33, 'pharmacy', 0, 100.00, '2026-01-20 23:16:41'),
(125, 34, 'consultation', 19, 500.00, '2026-01-20 23:19:15'),
(126, 34, 'consultation', 129, 500.00, '2026-01-20 23:19:15');

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` int(10) UNSIGNED NOT NULL,
  `method_code` varchar(50) NOT NULL,
  `method_name` varchar(100) NOT NULL,
  `requires_reference` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`id`, `method_code`, `method_name`, `requires_reference`, `is_active`, `created_at`) VALUES
(1, 'cash', 'Cash', 0, 1, '2025-11-01 00:39:46'),
(2, 'mpesa', 'M-Pesa', 1, 1, '2025-11-01 00:39:46'),
(3, 'card', 'Card', 1, 1, '2025-11-01 00:39:46'),
(4, 'insurance', 'Insurance', 1, 1, '2025-11-01 00:39:46'),
(5, 'bank_transfer', 'Bank Transfer', 1, 1, '2025-11-01 00:39:46');

-- --------------------------------------------------------

--
-- Table structure for table `payment_transactions`
--

CREATE TABLE `payment_transactions` (
  `id` int(10) UNSIGNED NOT NULL,
  `billing_id` int(10) UNSIGNED NOT NULL,
  `payment_amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `transaction_date` datetime DEFAULT current_timestamp(),
  `received_by` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_transactions`
--

INSERT INTO `payment_transactions` (`id`, `billing_id`, `payment_amount`, `payment_method`, `payment_reference`, `transaction_date`, `received_by`, `notes`, `created_at`) VALUES
(1, 1, 1500.00, 'cash', NULL, '2025-11-06 09:46:34', 1, NULL, '2025-11-06 06:46:34'),
(2, 17, 1725.00, 'cash', '', '2025-11-06 10:23:53', 55, NULL, '2025-11-06 07:23:53'),
(3, 17, 1725.00, 'cash', '', '2025-11-06 10:24:10', 55, NULL, '2025-11-06 07:24:10');

-- --------------------------------------------------------


--
-- Table structure for table `pending_pharmacy_bills_view`
--

CREATE TABLE `pending_pharmacy_bills_view` (
  `id` int(11) DEFAULT NULL,
  `receipt_no` varchar(50) DEFAULT NULL,
  `patient_id` int(10) UNSIGNED DEFAULT NULL,
  `patient_name` varchar(255) DEFAULT NULL,
  `sale_date` datetime DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `paid_amount` decimal(10,2) DEFAULT NULL,
  `balance_amount` decimal(10,2) DEFAULT NULL,
  `billing_status` enum('pending','added_to_invoice','paid','cancelled') DEFAULT NULL,
  `prescription_id` int(11) DEFAULT NULL,
  `dispensed_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `item_count` bigint(21) DEFAULT NULL,
  `products` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pending_purchases_view`
--

CREATE TABLE `pending_purchases_view` (
  `id` int(11) DEFAULT NULL,
  `productcategory` varchar(50) DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
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
  `status` enum('pending','received','cancelled') DEFAULT NULL,
  `received_date` date DEFAULT NULL,
  `received_by` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `supplier_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `code`, `description`, `created_at`) VALUES
(1, 'manage_users', 'Create/update/delete users and roles', '2025-09-23 11:28:35'),
(2, 'manage_patients', 'Create/update patient records', '2025-09-23 11:28:35'),
(3, 'manage_appointments', 'Manage appointments', '2025-09-23 11:28:35'),
(4, 'manage_inventory', 'Manage inventory and pharmacy', '2025-09-23 11:28:35'),
(5, 'manage_billing', 'Generate invoices and payments', '2025-09-23 11:28:35'),
(6, 'view_reports', 'View reporting dashboard', '2025-09-23 11:28:35'),
(7, 'users.manage', 'Manage user accounts (create/edit/delete/activate)', '2025-09-24 20:49:33'),
(8, 'roles.manage', 'Create/Edit/Delete roles and assign permissions', '2025-09-24 20:49:33'),
(9, 'patients.manage', 'Create/Edit patient records', '2025-09-24 20:49:33'),
(10, 'appointments.manage', 'Manage appointments', '2025-09-24 20:49:33'),
(11, 'ehr.manage', 'Access and modify electronic health records', '2025-09-24 20:49:33'),
(12, 'admissions.manage', 'Manage admissions and bed assignments', '2025-09-24 20:49:33'),
(13, 'inventory.manage', 'Manage inventory and stock', '2025-09-24 20:49:33'),
(14, 'pharmacy.manage', 'Pharmacy dispense and stock', '2025-09-24 20:49:33'),
(15, 'billing.manage', 'Create invoices and process payments', '2025-09-24 20:49:33'),
(16, 'reports.view', 'View reports and analytics', '2025-09-24 20:49:33'),
(17, 'lab.manage', 'Manage lab tests and results', '2025-09-24 20:49:33'),
(18, 'hr.manage', 'Manage staff, attendance, payroll', '2025-09-24 20:49:33');

-- --------------------------------------------------------

--
-- Table structure for table `pharmacy_billing`
--

CREATE TABLE `pharmacy_billing` (
  `id` int(11) NOT NULL,
  `CustomerName` varchar(255) NOT NULL,
  `bill_type` varchar(50) NOT NULL,
  `date` datetime NOT NULL,
  `bill_no` varchar(100) NOT NULL,
  `username` varchar(100) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `paid_amount` decimal(10,2) DEFAULT 0.00,
  `balance_amount` decimal(10,2) DEFAULT 0.00,
  `payment_status` enum('pending','partial','paid','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pharmacy_billing`
--

INSERT INTO `pharmacy_billing` (`id`, `CustomerName`, `bill_type`, `date`, `bill_no`, `username`, `patient_id`, `total_amount`, `paid_amount`, `balance_amount`, `payment_status`, `created_at`, `updated_at`) VALUES
(1, 'Ria Kumari', 'Cash', '2026-01-15 19:56:50', 'PHARM-0001', 'superadmin', 15, 0.00, 150.00, -150.00, 'paid', '2026-01-15 18:57:27', '2026-01-18 20:02:55'),
(2, 'Hannah Nyamwange', 'Cash', '2026-01-15 22:01:29', 'PHARM-0002', 'maryanne', 9, 1600.00, 1600.00, 0.00, 'paid', '2026-01-15 21:02:06', '2026-01-15 21:03:19'),
(3, 'Hillary Manase', 'Cash', '2026-01-16 09:39:37', 'PHARM-0003', 'maryanne', 2, 825.00, 825.00, 0.00, 'paid', '2026-01-16 08:39:54', '2026-01-16 09:24:55'),
(4, 'Susan Chemutai', 'Cash', '2026-01-16 11:26:03', 'PHARM-0004', 'maryanne', 7, 850.00, 850.00, 0.00, 'paid', '2026-01-16 10:26:30', '2026-01-16 11:01:17'),
(5, 'Christopher Mwasi', 'Cash', '2026-01-16 22:02:10', 'PHARM-0005', 'maryanne', 10, 1550.00, 1550.00, 0.00, 'paid', '2026-01-16 21:03:27', '2026-01-16 21:05:20'),
(6, 'Phoebe Ndalo', 'Cash', '2026-01-17 08:35:11', 'PHARM-0006', 'maryanne', 12, 1550.00, 1550.00, 0.00, 'paid', '2026-01-17 07:35:47', '2026-01-17 08:52:14'),
(7, 'Ria Kumari', 'Cash', '2026-01-17 08:44:46', 'PHARM-0007', 'maryanne', 15, 700.00, 700.00, 0.00, 'paid', '2026-01-17 07:45:07', '2026-01-17 07:55:01'),
(8, 'Mary Njeri', 'Cash', '2026-01-17 09:43:05', 'PHARM-0008', 'maryanne', 11, 825.00, 825.00, 0.00, 'paid', '2026-01-17 08:43:16', '2026-01-17 08:44:51'),
(9, 'Tanya Mutana', 'Cash', '2026-01-17 11:28:14', 'PHARM-0009', 'maryanne', 5, 825.00, 825.00, 0.00, 'paid', '2026-01-17 10:28:28', '2026-01-17 10:29:04'),
(10, 'Valery Odeku', 'Cash', '2026-01-17 14:23:15', 'PHARM-0010', 'superadmin', 8, 900.00, 900.00, 0.00, 'paid', '2026-01-17 13:23:29', '2026-01-17 13:23:51'),
(11, 'Hillary Manase', 'Cash', '2026-01-17 15:12:32', 'PHARM-0011', 'superadmin', 2, 850.00, 850.00, 0.00, 'paid', '2026-01-17 14:12:53', '2026-01-17 14:47:27'),
(12, 'Christopher Mwasi', 'Cash', '2026-01-18 19:22:48', 'PHARM-0012', 'Admin', 10, 600.00, 600.00, 0.00, 'paid', '2026-01-18 18:22:55', '2026-01-18 18:46:41'),
(13, 'Christopher Mwasi', 'Cash', '2026-01-18 19:31:09', 'PHARM-0013', 'superadmin', 10, 0.00, 75.00, -75.00, 'paid', '2026-01-18 18:32:22', '2026-01-21 09:49:41'),
(15, 'Phoebe Ndalo', 'Cash', '2026-01-19 12:37:26', 'PHARM-0014', 'maryanne', 12, 1650.00, 1650.00, 0.00, 'paid', '2026-01-19 11:37:44', '2026-01-19 11:38:58'),
(16, 'Tanya Mutana', 'Cash', '2026-01-19 21:27:56', 'PHARM-0016', 'maryanne', 5, 450.00, 450.00, 0.00, 'paid', '2026-01-19 20:28:20', '2026-01-19 20:28:57'),
(17, 'Hillary Manase', 'Cash', '2026-01-19 22:08:27', 'PHARM-0017', 'maryanne', 2, 850.00, 850.00, 0.00, 'paid', '2026-01-19 21:08:42', '2026-01-19 21:30:40'),
(18, 'Ria Kumari', 'Cash', '2026-01-19 23:05:35', 'PHARM-0018', 'maryanne', 15, 1400.00, 1400.00, 0.00, 'paid', '2026-01-19 22:05:52', '2026-01-19 22:21:33'),
(19, 'Bravin Magothe', 'Cash', '2026-01-19 23:27:27', 'PHARM-0019', 'maryanne', 4, 1500.00, 1500.00, 0.00, 'paid', '2026-01-19 22:27:45', '2026-01-19 22:28:27'),
(20, 'Alpha Mwirigi', 'Cash', '2026-01-20 09:53:40', 'PHARM-0020', 'superadmin', 6, 1700.00, 4750.00, -4750.00, 'paid', '2026-01-20 08:54:16', '2026-01-20 20:16:41'),
(21, 'Mary Njeri', 'Cash', '2026-01-20 11:47:04', 'PHARM-0020', 'maryanne', 11, 120.00, 4750.00, -4750.00, 'paid', '2026-01-20 10:47:28', '2026-01-20 20:16:41'),
(22, 'Achieng Odwour', 'Cash', '2026-01-20 12:10:19', 'PHARM-0020', 'maryanne', 31, 2100.00, 4750.00, -4750.00, 'paid', '2026-01-20 11:10:35', '2026-01-20 20:16:41'),
(23, 'Ria Kumari', 'Cash', '2026-01-20 14:12:03', 'PHARM-0020', 'maryanne', 15, 1700.00, 4750.00, -4750.00, 'paid', '2026-01-20 13:12:24', '2026-01-20 20:16:41'),
(24, 'Brianna Wambui', 'Cash', '2026-01-20 19:53:22', 'PHARM-0020', 'maryanne', 32, 1250.00, 4750.00, -4750.00, 'paid', '2026-01-20 18:53:57', '2026-01-20 20:16:41'),
(25, 'Christopher Mwasi', 'Cash', '2026-01-20 21:15:15', 'PHARM-0020', 'superadmin', 10, 100.00, 4750.00, -4750.00, 'paid', '2026-01-20 20:15:31', '2026-01-20 20:16:41'),
(26, 'Kelvin Mwasi', 'Cash', '2026-01-20 21:45:51', 'PHARM-0020', 'superadmin', 16, 1700.00, 0.00, 1700.00, 'paid', '2026-01-20 20:46:13', '2026-01-20 21:12:08'),
(45, 'Susan Chemutai', 'Cash', '2026-01-21 10:33:37', 'PHARM-0027', 'superadmin', 7, 1500.00, 0.00, 1500.00, 'paid', '2026-01-21 09:33:59', '2026-01-21 09:34:48'),
(46, 'Nadia Mutana', 'Cash', '2026-01-21 13:05:10', 'PHARM-0046', 'maryanne', 33, 1350.00, 0.00, 1350.00, 'paid', '2026-01-21 12:05:36', '2026-01-21 12:23:50'),
(47, 'Hannah Nyamwange', 'Cash', '2026-01-22 20:53:46', 'PHARM-0047', 'superadmin', 9, 1700.00, 0.00, 1700.00, 'paid', '2026-01-22 19:54:21', '2026-01-22 19:55:12'),
(48, 'Phoebe Ndalo', 'Cash', '2026-02-05 15:19:21', 'PHARM-0048', 'superadmin', 12, 375.00, 0.00, 375.00, 'paid', '2026-02-05 14:20:01', '2026-02-05 14:20:46'),
(49, 'Ria Kumari', 'Cash', '2026-02-05 19:15:17', 'PHARM-0049', 'maryanne', 15, 2200.00, 0.00, 2200.00, 'paid', '2026-02-05 18:18:36', '2026-02-05 18:42:56'),
(50, 'Brianna Wambui', 'Cash', '2026-02-06 09:53:20', 'PHARM-0050', 'superadmin', 32, 2350.00, 0.00, 2350.00, 'paid', '2026-02-06 08:53:45', '2026-02-06 08:55:04'),
(51, 'Achieng Odwour', 'Cash', '2026-02-06 11:52:21', 'PHARM-0051', 'superadmin', 31, 1700.00, 0.00, 1700.00, 'paid', '2026-02-06 10:52:46', '2026-02-06 10:52:57'),
(52, 'Sandra Kosgei', 'Cash', '2026-02-06 12:04:46', 'PHARM-0052', 'maryanne', 13, 1500.00, 0.00, 1500.00, 'paid', '2026-02-06 11:04:58', '2026-02-06 12:01:04'),
(53, 'Christopher Mwasi', 'Cash', '2026-02-06 12:54:53', 'PHARM-0053', 'superadmin', 10, 200.00, 0.00, 200.00, 'paid', '2026-02-06 11:55:28', '2026-02-06 11:56:08');

-- --------------------------------------------------------

--
-- Table structure for table `pharmacy_billing_details`
--

CREATE TABLE `pharmacy_billing_details` (
  `id` int(11) NOT NULL,
  `billing_id` int(11) NOT NULL,
  `product_company` varchar(255) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_unit` varchar(50) NOT NULL,
  `packing_size` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pharmacy_billing_details`
--

INSERT INTO `pharmacy_billing_details` (`id`, `billing_id`, `product_company`, `product_name`, `product_unit`, `packing_size`, `price`, `quantity`, `total_amount`, `patient_id`, `created_at`) VALUES
(2, 2, 'GracePharmacuticals', 'Cough syrup', 'ml', '100', 750.00, 1, 750.00, 9, '2026-01-15 21:02:06'),
(3, 2, 'MediCare Pharmaceuticals', 'Amoxicillin', 'mg', '100', 850.00, 1, 850.00, 9, '2026-01-15 21:02:06'),
(4, 3, 'Transchem pharmaceuticals', 'Agumentin', 'tablet', '100', 825.00, 1, 825.00, 2, '2026-01-16 08:39:54'),
(5, 4, 'MediCare Pharmaceuticals', 'Amoxicillin', 'mg', '100', 850.00, 1, 850.00, 7, '2026-01-16 10:26:30'),
(6, 5, 'MediCare Pharmaceuticals', 'Amoxicillin', 'mg', '100', 850.00, 1, 850.00, 10, '2026-01-16 21:03:27'),
(7, 5, 'VeePharmaceutical', 'Ibuprofen', 'box', '200', 700.00, 1, 700.00, 10, '2026-01-16 21:03:27'),
(8, 6, 'RahaPharmacy', 'Agumentin', 'tablet', '100', 850.00, 1, 850.00, 12, '2026-01-17 07:35:47'),
(9, 6, 'VeePharmaceutical', 'Ibuprofen', 'box', '200', 700.00, 1, 700.00, 12, '2026-01-17 07:35:48'),
(10, 7, 'VeePharmaceutical', 'Ibuprofen', 'box', '200', 700.00, 1, 700.00, 15, '2026-01-17 07:45:07'),
(11, 8, 'Transchem pharmaceuticals', 'Agumentin', 'tablet', '100', 825.00, 1, 825.00, 11, '2026-01-17 08:43:16'),
(12, 9, 'Transchem pharmaceuticals', 'Agumentin', 'tablet', '100', 825.00, 1, 825.00, 5, '2026-01-17 10:28:28'),
(13, 10, 'RahaPharmacy', 'Inhaler', 'bottle', '1', 450.00, 2, 900.00, 8, '2026-01-17 13:23:29'),
(14, 11, 'MediCare Pharmaceuticals', 'Amoxicillin', 'mg', '100', 850.00, 1, 850.00, 2, '2026-01-17 14:12:53'),
(15, 12, 'VeePharmaceutical', 'metoprolol', 'sachet', '30', 300.00, 2, 600.00, 10, '2026-01-18 18:22:55'),
(17, 15, 'Transchem pharmaceuticals', 'Agumentin', 'tablet', '100', 825.00, 2, 1650.00, 12, '2026-01-19 11:37:44'),
(18, 16, 'RahaPharmacy', 'Inhaler', 'bottle', '1', 450.00, 1, 450.00, 5, '2026-01-19 20:28:20'),
(19, 17, 'RahaPharmacy', 'Agumentin', 'tablet', '100', 850.00, 1, 850.00, 2, '2026-01-19 21:08:42'),
(20, 18, 'VeePharmaceutical', 'Ibuprofen', 'box', '200', 700.00, 2, 1400.00, 15, '2026-01-19 22:05:52'),
(21, 19, 'VeePharmaceutical', 'metoprolol', 'sachet', '30', 300.00, 5, 1500.00, 4, '2026-01-19 22:27:45'),
(0, 0, 'MediCare Pharmaceuticals', 'Amoxicillin', 'mg', '100', 850.00, 2, 1700.00, 6, '2026-01-20 08:54:16'),
(0, 0, 'VeePharmaceutical', 'cetrizine', 'tablet', '10', 15.00, 8, 120.00, 11, '2026-01-20 10:47:28'),
(0, 0, 'VeePharmaceutical', 'Ibuprofen', 'box', '200', 700.00, 3, 2100.00, 31, '2026-01-20 11:10:35'),
(0, 0, 'RahaPharmacy', 'Agumentin', 'tablet', '100', 850.00, 2, 1700.00, 15, '2026-01-20 13:12:24'),
(0, 0, 'RahaPharmacy', 'Agumentin', 'tablet', '100', 850.00, 1, 850.00, 32, '2026-01-20 18:53:57'),
(0, 0, 'RahaPharmacy', 'Panadol', 'tablet', '1', 20.00, 20, 400.00, 32, '2026-01-20 18:53:58'),
(0, 0, 'RahaPharmacy', 'Panadol', 'tablet', '1', 20.00, 5, 100.00, 10, '2026-01-20 20:15:31'),
(0, 0, 'MediCare Pharmaceuticals', 'Amoxicillin', 'mg', '100', 850.00, 2, 1700.00, 16, '2026-01-20 20:46:13'),
(0, 45, 'VeePharmaceutical', 'metoprolol', 'sachet', '30', 300.00, 5, 1500.00, 7, '2026-01-21 09:33:59'),
(0, 46, 'RahaPharmacy', 'Inhaler', 'bottle', '1', 450.00, 3, 1350.00, 33, '2026-01-21 12:05:36'),
(0, 47, 'RahaPharmacy', 'Agumentin', 'tablet', '100', 850.00, 2, 1700.00, 9, '2026-01-22 19:54:21'),
(0, 48, 'Transchem pharmaceuticals', 'Mara Moja', 'box', '100', 15.00, 25, 375.00, 12, '2026-02-05 14:20:01'),
(0, 49, 'VeePharmaceutical', 'Ibuprofen', 'box', '200', 700.00, 1, 700.00, 15, '2026-02-05 18:18:36'),
(0, 49, 'VeePharmaceutical', 'metoprolol', 'sachet', '30', 300.00, 5, 1500.00, 15, '2026-02-05 18:18:36'),
(0, 50, 'VeePharmaceutical', 'Ibuprofen', 'box', '200', 700.00, 1, 700.00, 32, '2026-02-06 08:53:45'),
(0, 50, 'Transchem pharmaceuticals', 'Agumentin', 'tablet', '100', 825.00, 2, 1650.00, 32, '2026-02-06 08:53:45'),
(0, 51, 'MediCare Pharmaceuticals', 'Amoxicillin', 'mg', '100', 850.00, 2, 1700.00, 31, '2026-02-06 10:52:46'),
(0, 52, 'VeePharmaceutical', 'metoprolol', 'sachet', '30', 300.00, 5, 1500.00, 13, '2026-02-06 11:04:58'),
(0, 53, 'RahaPharmacy', 'Panadol', 'tablet', '1', 20.00, 10, 200.00, 10, '2026-02-06 11:55:28');

-- --------------------------------------------------------

--
-- Table structure for table `pharmacy_billing_view`
--

CREATE TABLE `pharmacy_billing_view` (
  `id` int(11) DEFAULT NULL,
  `receipt_no` varchar(50) DEFAULT NULL,
  `patient_id` int(10) UNSIGNED DEFAULT NULL,
  `patient_name` varchar(255) DEFAULT NULL,
  `bill_type` enum('Cash','Mpesa','Debit','Insurance','Bank Transfer') DEFAULT NULL,
  `sale_date` datetime DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `paid_amount` decimal(10,2) DEFAULT NULL,
  `balance_amount` decimal(10,2) DEFAULT NULL,
  `payment_status` enum('pending','paid','partial') DEFAULT NULL,
  `prescription_id` int(11) DEFAULT NULL,
  `dispensed_by` varchar(100) DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `outstanding_amount` decimal(10,2) DEFAULT NULL,
  `medication_name` varchar(255) DEFAULT NULL,
  `dosage` varchar(100) DEFAULT NULL,
  `frequency` varchar(100) DEFAULT NULL,
  `duration` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------


--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(10) UNSIGNED NOT NULL,
  `prescription_number` varchar(50) DEFAULT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `doctor_id` int(10) UNSIGNED NOT NULL,
  `medication_name` varchar(255) NOT NULL,
  `dosage` varchar(100) NOT NULL,
  `frequency` varchar(100) NOT NULL,
  `duration` varchar(100) NOT NULL,
  `instructions` text DEFAULT NULL,
  `prescription_date` datetime DEFAULT current_timestamp(),
  `valid_until` date DEFAULT NULL,
  `diagnosis` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','completed','cancelled','expired') DEFAULT 'active',
  `dispensed` tinyint(1) DEFAULT 0,
  `dispensed_date` datetime DEFAULT NULL,
  `dispensed_by` int(10) UNSIGNED DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescriptions`
--

INSERT INTO `prescriptions` (`id`, `prescription_number`, `patient_id`, `doctor_id`, `medication_name`, `dosage`, `frequency`, `duration`, `instructions`, `prescription_date`, `valid_until`, `diagnosis`, `notes`, `status`, `dispensed`, `dispensed_date`, `dispensed_by`, `total_amount`, `created_at`, `updated_at`) VALUES
(1, 'RX-202510-0001', 6, 55, 'Cough Syrup', '500mg', 'thrice daily', '5 days', 'Take after meals, three times a day.', '2025-10-30 13:09:02', NULL, NULL, NULL, 'completed', 0, NULL, NULL, 0.00, '2025-10-30 10:09:02', '2025-10-30 10:11:00'),
(2, 'RX-202510-0002', 1, 55, 'asthma pump', '2 puffs', 'thrice daily', 'as needed', '', '2025-10-30 19:57:46', NULL, NULL, NULL, 'completed', 0, NULL, NULL, 0.00, '2025-10-30 16:57:46', '2025-10-30 16:58:45'),
(3, 'RX-202510-0003', 8, 55, 'Amoxicillin, cough syrup', '1000mg and 500mg', 'thrice daily', '5 days', 'take for 5 days straight. After meals', '2025-10-30 20:15:42', NULL, NULL, NULL, 'completed', 0, NULL, NULL, 0.00, '2025-10-30 17:15:42', '2025-10-30 17:21:07'),
(4, 'RX-202510-0004', 11, 55, 'Eno, antibiotics', '500mg', 'thrice daily', '7 days', 'Take the antibiotics three times for 7 days and Eno take as needed', '2025-10-30 20:40:36', NULL, NULL, NULL, 'completed', 0, NULL, NULL, 0.00, '2025-10-30 17:40:36', '2025-10-30 20:34:25'),
(5, 'RX-202510-0005', 7, 55, 'painkillers', '500mg', 'thrice daily', '7 days', '', '2025-10-30 23:55:13', NULL, NULL, NULL, 'completed', 0, NULL, NULL, 0.00, '2025-10-30 20:55:13', '2025-10-30 21:21:13'),
(7, 'RX-202510-0006', 9, 55, 'ponstan', '500mg', 'twice daily', 'as needed', 'painkiller for cramps', '2025-10-31 01:03:13', NULL, NULL, NULL, 'completed', 0, NULL, NULL, 0.00, '2025-10-30 22:03:13', '2025-10-30 22:10:19'),
(8, 'RX-202510-0007', 2, 66, 'Mara Moja', '150mg', '2 tables', 'as needed', '', '2025-10-31 10:56:55', NULL, NULL, NULL, 'completed', 0, NULL, NULL, 0.00, '2025-10-31 07:56:55', '2025-10-31 07:58:08'),
(9, 'RX-202510-0008', 10, 69, 'antibiotics and painkillers', '1000mg', 'thrice daily', '7 days', '', '2025-10-31 22:48:04', NULL, NULL, NULL, 'completed', 0, NULL, NULL, 0.00, '2025-10-31 19:48:04', '2025-10-31 20:43:52'),
(10, 'RX-202511-0001', 12, 69, 'Agumentin', '1000mg', 'thrice daily', '7 days', '', '2025-11-02 16:40:23', NULL, NULL, NULL, 'completed', 0, NULL, NULL, 0.00, '2025-11-02 13:40:23', '2025-11-02 13:47:28'),
(11, 'RX-202511-0002', 11, 72, 'Mara Moja', '500mg', '2 * 2', 'as needed', '', '2025-11-02 18:31:47', NULL, NULL, NULL, 'completed', 0, NULL, NULL, 0.00, '2025-11-02 15:31:47', '2025-11-02 19:03:30'),
(12, 'RX-202511-0003', 1, 55, 'Ibuprufen', '500mg', 'thrice daily', '7 days', '', '2025-11-03 20:51:43', NULL, NULL, NULL, 'completed', 0, NULL, NULL, 0.00, '2025-11-03 17:51:43', '2025-11-03 18:02:54'),
(13, 'RX-202511-0004', 9, 55, 'ponstan', '1000mg', 'thrice daily', 'as needed', '', '2025-11-03 21:04:08', NULL, NULL, NULL, 'completed', 0, NULL, NULL, 0.00, '2025-11-03 18:04:08', '2025-11-03 18:05:08'),
(14, 'RX-202511-0005', 8, 55, 'Eno', '500mg', 'thrice daily', 'as needed', '', '2025-11-03 21:48:05', NULL, NULL, NULL, 'completed', 1, '2025-11-03 23:12:04', 55, 0.00, '2025-11-03 18:48:05', '2025-11-04 07:31:25'),
(15, 'RX-202511-0006', 2, 55, 'Amoxicillin, cough syrup', '500mg, 1000mg', 'thrice daily', '7 days', '', '2025-11-03 23:54:12', NULL, NULL, NULL, 'completed', 1, '2025-11-03 23:55:00', 55, 0.00, '2025-11-03 20:54:12', '2025-11-04 07:31:21'),
(16, 'RX-202511-0007', 12, 69, 'Cough Syrup, antibiotics and painkillers', '500mg', 'thrice daily', '7 days', '', '2025-11-04 10:39:57', NULL, NULL, NULL, 'completed', 1, '2025-11-04 11:11:07', 55, 0.00, '2025-11-04 07:39:57', '2025-11-04 08:23:35'),
(17, 'RX-202511-0008', 9, 55, 'Cough Syrup', '1000mg', 'thrice daily', '7 days', '', '2025-11-04 11:30:40', NULL, NULL, NULL, 'completed', 1, '2025-11-04 11:31:07', 55, 0.00, '2025-11-04 08:30:40', '2025-11-05 11:37:36'),
(18, 'RX-202511-0009', 13, 55, 'antibiotics and painkillers', '1000mg', 'thrice daily', '7 days', 'take after meals', '2025-11-06 11:35:10', NULL, NULL, NULL, 'completed', 1, '2025-11-06 11:36:58', 55, 0.00, '2025-11-06 08:35:10', '2025-11-06 10:24:01'),
(19, 'RX-202511-0010', 14, 69, 'Amoxicillin, mara moja', '500mg and 1500mg', 'thrice daily', '7 days', 'for the painkillers take 2 tables 2 times a day as needed, or until the pain subsides', '2025-11-14 10:11:23', NULL, NULL, NULL, 'completed', 1, '2025-11-14 10:15:11', 55, 0.00, '2025-11-14 07:11:23', '2025-11-14 07:21:09'),
(20, 'RX-202601-0001', 8, 59, 'Agumentin, painkillers', '500mg', 'thrice daily', '7 days', 'Take after meals, for the painkillers(panadol or maramoja) to take 2 tablets every 4 hours, shouldn\'t take more than 8 tablets a day.', '2026-01-09 12:06:47', NULL, NULL, NULL, 'active', 1, '2026-01-09 12:08:31', 72, 0.00, '2026-01-09 09:06:47', '2026-01-09 09:08:31'),
(21, 'RX-202601-0002', 6, 55, 'Mara Moja', '500mg', 'as needed', 'as needed', '', '2026-01-09 23:53:07', NULL, NULL, NULL, 'active', 1, '2026-01-09 23:53:36', 55, 0.00, '2026-01-09 20:53:07', '2026-01-09 20:53:36'),
(22, 'RX-202601-0003', 6, 69, 'celestamine and maramoja', '500mg', 'thrice daily', 'as needed', '', '2026-01-15 14:09:16', NULL, NULL, NULL, 'active', 1, '2026-01-15 14:10:05', 72, 0.00, '2026-01-15 11:09:16', '2026-01-15 11:10:05'),
(23, 'RX-202601-0004', 14, 60, 'celestamine and laxatives', '500mg', 'thrice daily', 'as needed', '', '2026-01-15 14:32:26', NULL, NULL, NULL, 'active', 1, '2026-01-15 14:33:16', 72, 0.00, '2026-01-15 11:32:26', '2026-01-15 11:33:16'),
(24, 'RX-202601-0005', 15, 55, 'Panadol', '500mg', 'thrice daily', 'as needed', '', '2026-01-15 15:19:41', NULL, NULL, NULL, 'active', 1, '2026-01-15 15:20:33', 55, 0.00, '2026-01-15 12:19:41', '2026-01-15 12:20:33'),
(25, 'RX-202601-0006', 15, 55, 'piriton', '500mg', 'twice daily', '7 days', '', '2026-01-15 15:27:33', NULL, NULL, NULL, 'active', 1, '2026-01-15 21:57:27', 55, 0.00, '2026-01-15 12:27:33', '2026-01-15 18:57:27'),
(26, 'RX-202601-0007', 10, 55, 'Cetirizine ', '500mg', 'thrice daily', '7 days', '', '2026-01-15 15:34:44', NULL, NULL, NULL, 'completed', 0, NULL, NULL, 0.00, '2026-01-15 12:34:44', '2026-01-18 19:59:18'),
(27, 'RX-202601-0008', 9, 67, 'Amoxicillin and cough syrup', '500mg', 'thrice daily', '7 days', '', '2026-01-15 23:51:00', NULL, NULL, NULL, 'active', 1, '2026-01-16 00:02:06', 72, 0.00, '2026-01-15 20:51:00', '2026-01-15 21:02:06'),
(28, 'RX-202601-0009', 2, 69, 'Agumentin', '1000mg', 'thrice daily', '7 days', '', '2026-01-16 11:39:22', NULL, NULL, NULL, 'active', 1, '2026-01-16 11:39:54', 72, 0.00, '2026-01-16 08:39:22', '2026-01-16 08:39:54'),
(29, 'RX-202601-0010', 7, 60, 'Amoxicillin', '1000mg', 'thrice daily', '7 days', '', '2026-01-16 13:25:05', NULL, NULL, NULL, 'active', 1, '2026-01-16 13:26:30', 72, 0.00, '2026-01-16 10:25:05', '2026-01-16 10:26:30'),
(30, 'RX-202601-0011', 10, 59, 'Amoxicillin, mara moja', '1000mg', 'thrice daily', '7 days', '', '2026-01-17 00:01:51', NULL, NULL, NULL, 'active', 1, '2026-01-17 00:03:27', 72, 0.00, '2026-01-16 21:01:51', '2026-01-16 21:03:27'),
(31, 'RX-202601-0012', 12, 69, 'Agumentin and ibuprofen', '1500mg and 500mg', 'thrice daily', '7 days', '', '2026-01-17 10:34:48', NULL, NULL, NULL, 'active', 1, '2026-01-17 10:35:48', 72, 0.00, '2026-01-17 07:34:48', '2026-01-17 07:35:48'),
(32, 'RX-202601-0013', 15, 67, 'Ibuprufen', '500mg', 'thrice daily', 'as needed', '', '2026-01-17 10:44:04', NULL, NULL, NULL, 'active', 1, '2026-01-17 10:45:07', 72, 0.00, '2026-01-17 07:44:04', '2026-01-17 07:45:07'),
(33, 'RX-202601-0014', 11, 59, 'Agumentin', '1500mg', 'thrice daily', '7 days', '', '2026-01-17 11:42:17', NULL, NULL, NULL, 'active', 1, '2026-01-17 11:43:16', 72, 0.00, '2026-01-17 08:42:17', '2026-01-17 08:43:16'),
(34, 'RX-202601-0015', 5, 55, 'Agumentin', '1000mg', 'thrice daily', '7 days', '', '2026-01-17 13:26:56', NULL, NULL, NULL, 'active', 1, '2026-01-17 13:28:28', 72, 0.00, '2026-01-17 10:26:56', '2026-01-17 10:28:28'),
(35, 'RX-202601-0016', 8, 55, 'Inhaler', '500mg', 'twice daily', 'as needed', '', '2026-01-17 16:21:34', NULL, NULL, NULL, 'active', 1, '2026-01-17 16:23:29', 55, 0.00, '2026-01-17 13:21:34', '2026-01-17 13:23:29'),
(36, 'RX-202601-0017', 2, 55, 'Amoxicillin', '1000mg', 'thrice daily', '7 days', '', '2026-01-17 17:12:26', NULL, NULL, NULL, 'active', 1, '2026-01-17 17:12:53', 55, 0.00, '2026-01-17 14:12:26', '2026-01-17 14:12:53'),
(37, 'RX-202601-0018', 10, 55, 'metoprolol', '1000mg', 'thrice daily', 'as needed', '', '2026-01-18 20:45:19', NULL, NULL, NULL, 'active', 1, '2026-01-18 21:22:55', 55, 0.00, '2026-01-18 17:45:19', '2026-01-18 18:22:55'),
(38, 'RX-202601-0019', 5, 67, 'Inhaler', '500mg', 'thrice daily', 'as needed', '', '2026-01-19 13:49:51', NULL, NULL, NULL, 'active', 1, '2026-01-19 23:28:20', 72, 0.00, '2026-01-19 10:49:51', '2026-01-19 20:28:20'),
(39, 'RX-202601-0020', 12, 69, 'Agumentin', '1500mg', 'thrice daily', '7 days', '', '2026-01-19 14:27:44', NULL, NULL, NULL, 'active', 1, '2026-01-19 14:37:44', 72, 0.00, '2026-01-19 11:27:44', '2026-01-19 11:37:44'),
(40, 'RX-202601-0021', 2, 69, 'Agumentin', '1500mg', 'thrice daily', '7 days', '', '2026-01-20 00:08:06', NULL, NULL, NULL, 'active', 1, '2026-01-20 00:08:42', 72, 0.00, '2026-01-19 21:08:06', '2026-01-19 21:08:42'),
(41, 'RX-202601-0022', 15, 67, 'Ibuprufen', '500mg', 'thrice daily', 'as needed', '', '2026-01-20 01:05:09', NULL, NULL, NULL, 'active', 1, '2026-01-20 01:05:52', 72, 0.00, '2026-01-19 22:05:09', '2026-01-19 22:05:52'),
(42, 'RX-202601-0023', 4, 59, 'metoprolol', '1500mg', 'thrice daily', 'as needed', '', '2026-01-20 01:25:04', NULL, NULL, NULL, 'completed', 1, '2026-01-20 01:27:45', 72, 0.00, '2026-01-19 22:25:04', '2026-01-21 09:40:12'),
(43, 'RX-202601-0024', 6, 61, 'Amoxicillin', '1500mg', 'thrice daily', '7 days', '', '2026-01-20 11:36:00', NULL, NULL, NULL, 'completed', 1, '2026-02-06 14:55:28', 55, 0.00, '2026-01-20 08:36:00', '2026-02-06 11:55:28'),
(44, 'RX-202601-0025', 11, 59, 'metoprolol', '1000mg', 'thrice daily', 'as needed', '', '2026-01-20 11:59:17', NULL, NULL, NULL, 'completed', 1, '2026-02-06 14:55:28', 55, 0.00, '2026-01-20 08:59:17', '2026-02-06 11:55:28'),
(45, 'RX-202601-0026', 16, 64, 'Amoxicillin', '1500mg', 'thrice daily', '7 days', '', '2026-01-20 13:45:35', NULL, NULL, NULL, 'completed', 1, '2026-02-06 14:55:28', 55, 0.00, '2026-01-20 10:45:35', '2026-02-06 11:55:28'),
(46, 'RX-202601-0027', 31, 60, 'Ibuprufen', '1000mg', 'thrice daily', 'as needed', '', '2026-01-20 14:00:51', NULL, NULL, NULL, 'completed', 1, '2026-02-06 14:55:28', 55, 0.00, '2026-01-20 11:00:51', '2026-02-06 11:55:28'),
(47, 'RX-202601-0028', 15, 69, 'Agumentin', '1500mg', 'thrice daily', '7 days', '', '2026-01-20 16:09:20', NULL, NULL, NULL, 'completed', 1, '2026-02-06 14:55:28', 55, 0.00, '2026-01-20 13:09:20', '2026-02-06 11:55:28'),
(48, 'RX-202601-0029', 32, 60, 'Agumentin, Panadol', '1000mg, 500mg', 'thrice daily', '7 days', '', '2026-01-20 21:52:35', NULL, NULL, NULL, 'completed', 1, '2026-02-06 14:55:28', 55, 0.00, '2026-01-20 18:52:35', '2026-02-06 11:55:28'),
(49, 'RX-202601-0030', 10, 55, 'Panadol', '500mg', 'thrice daily', 'as needed', '', '2026-01-20 23:15:03', NULL, NULL, NULL, 'completed', 1, '2026-02-06 14:55:28', 55, 0.00, '2026-01-20 20:15:03', '2026-02-06 11:55:28'),
(50, 'RX-202601-0031', 16, 55, 'Amoxicillin', '1500mg', 'thrice daily', '7 days', '', '2026-01-20 23:45:41', NULL, NULL, NULL, 'completed', 1, '2026-02-06 14:55:28', 55, 0.00, '2026-01-20 20:45:41', '2026-02-06 11:55:28'),
(51, 'RX-202601-0032', 7, 60, 'metoprolol', '1500mg', 'thrice daily', '7 days', '', '2026-01-21 11:44:58', NULL, NULL, NULL, 'completed', 1, '2026-02-06 14:55:28', 55, 0.00, '2026-01-21 08:44:58', '2026-02-06 11:55:28'),
(52, 'RX-202601-0033', 33, 61, 'Inhaler', '1000mg', 'thrice daily', 'as needed', '', '2026-01-21 14:50:20', NULL, NULL, NULL, 'completed', 1, '2026-02-06 14:55:28', 55, 0.00, '2026-01-21 11:50:20', '2026-02-06 11:55:28'),
(53, 'RX-202601-0034', 9, 55, 'Agumentin', '1500mg', 'thrice daily', '7 days', '', '2026-01-22 22:53:17', NULL, NULL, NULL, 'completed', 1, '2026-02-06 14:55:28', 55, 0.00, '2026-01-22 19:53:17', '2026-02-06 11:55:28'),
(54, 'RX-202602-0001', 12, 55, 'Mara Moja', '1000mg', 'thrice daily', '7 days', 'Don\'t exceed 8 tablets in a day!', '2026-02-05 17:18:36', NULL, NULL, NULL, 'active', 1, '2026-02-06 14:55:28', 55, 0.00, '2026-02-05 14:18:36', '2026-02-06 11:55:28'),
(55, 'RX-202602-0002', 15, 68, 'metoprolol and ibuprofen', '1500mg, 500mg', 'thrice daily', 'as needed', '', '2026-02-05 21:08:06', NULL, NULL, NULL, 'active', 1, '2026-02-06 14:55:28', 55, 0.00, '2026-02-05 18:08:06', '2026-02-06 11:55:28'),
(56, 'RX-202602-0003', 32, 55, 'Agumentin and ibuprofen', '1500mg, 500mg', 'thrice daily', '7 days', '', '2026-02-06 11:47:09', NULL, NULL, NULL, 'active', 1, '2026-02-06 14:55:28', 55, 0.00, '2026-02-06 08:47:09', '2026-02-06 11:55:28'),
(57, 'RX-202602-0004', 31, 55, 'Amoxicillin', '500mg', 'thrice daily', '7 days', '', '2026-02-06 13:52:12', NULL, NULL, NULL, 'active', 1, '2026-02-06 14:55:28', 55, 0.00, '2026-02-06 10:52:12', '2026-02-06 11:55:28'),
(58, 'RX-202602-0005', 13, 68, 'metoprolol', '500mg', 'thrice daily', '7 days', '', '2026-02-06 14:03:23', NULL, NULL, NULL, 'active', 1, '2026-02-06 14:55:28', 55, 0.00, '2026-02-06 11:03:23', '2026-02-06 11:55:28'),
(59, 'RX-202602-0006', 10, 55, 'Panadol', '500mg', 'as needed', 'as needed', '', '2026-02-06 14:53:10', NULL, NULL, NULL, 'active', 1, '2026-02-06 14:55:28', 55, 0.00, '2026-02-06 11:53:10', '2026-02-06 11:55:28');

--
-- Triggers `prescriptions`
--
DELIMITER $$
CREATE TRIGGER `generate_prescription_number` BEFORE INSERT ON `prescriptions` FOR EACH ROW BEGIN
    DECLARE next_num INT;
    
    IF NEW.prescription_number IS NULL THEN
        SELECT COALESCE(
            MAX(CAST(SUBSTRING(prescription_number, -4) AS UNSIGNED)), 
            0
        ) + 1 INTO next_num
        FROM prescriptions
        WHERE prescription_number LIKE CONCAT('RX-', DATE_FORMAT(NOW(), '%Y%m'), '-%');
        
        SET NEW.prescription_number = CONCAT('RX-', DATE_FORMAT(NOW(), '%Y%m'), '-', LPAD(next_num, 4, '0'));
    END IF;
END
$$
DELIMITER ;

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
(7, 'GracePharmacuticals', 'Medications', 'Cough syrup', 'ml', '100'),
(8, 'MediPlugequipments', 'Medical Equipment', 'Ventillators', 'box', '100'),
(10, 'Transchem Pharmaceutical', 'Medications', 'Paracetamol', 'capsule', '100'),
(11, 'Transchem Pharmaceutical', 'Medications', 'Mara Moja', 'box', '100'),
(12, 'Transchem Pharmaceutical', 'Medications', 'Eno', 'sachet', '200'),
(14, 'VeePharmaceutical', 'Medications', 'Ibuprofen', 'box', '200'),
(15, 'MediPlugequipments', 'Medical Equipment', 'wheelchairs', 'each', '100'),
(16, 'MediPlugequipments', 'Medical Equipment', 'Blood Pressure Monitor', 'box', '50'),
(18, 'OdwourInc', 'Office Supplies', 'Printers', 'each', '5'),
(19, 'kimPharm', 'Medical Supplies', 'Surgical Gloves', 'box', '200'),
(20, 'kimPharm', 'Medical Supplies', 'Bandages', 'box', '100'),
(21, 'kimPharm', 'Medical Supplies', 'syringes', 'gram', '200'),
(22, 'OdwourInc', 'Office Supplies', 'Binders', 'box', '50'),
(23, 'Transchem pharmaceuticals', 'Medications', 'Eno', 'box', '100'),
(24, 'GracePharmaceuticals', 'Medications', 'Ponstan', 'tablet', '50'),
(25, 'Transchem pharmaceuticals', 'Medications', 'Mara Moja', 'box', '100'),
(26, 'VeePharmaceutical', 'Medications', 'Insulin', 'box', '5'),
(27, 'VeePharmaceutical', 'Medications', 'asthma pump', 'pump', '10'),
(28, 'MediCare Pharmaceuticals', 'Medications', 'Amoxicillin', 'mg', '100'),
(29, 'Transchem pharmaceuticals', 'Medications', 'Agumentin', 'tablet', '100'),
(30, 'RahaPharmacy', 'Medications', 'Agumentin', 'tablet', '100');

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
  `received_quantity` int(11) DEFAULT 0,
  `price` decimal(10,2) DEFAULT NULL,
  `product_selling_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `party_name` varchar(255) DEFAULT NULL,
  `purchase_method` varchar(100) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `status` enum('pending','received','cancelled') DEFAULT 'pending',
  `received_date` date DEFAULT NULL,
  `received_by` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `total` decimal(10,2) GENERATED ALWAYS AS (`price` * `received_quantity`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_master`
--

INSERT INTO `purchase_master` (`id`, `productcategory`, `company_name`, `product_name`, `unit`, `packing_size`, `quantity`, `received_quantity`, `price`, `product_selling_price`, `party_name`, `purchase_method`, `expiry_date`, `purchase_date`, `username`, `status`, `received_date`, `received_by`, `notes`) VALUES
(1, 'Medications', 'GracePharmaceuticals', 'Ponstan', 'tablet', '50', 30, 30, 50.00, 0.00, 'GracePharmaceuticals', 'Mpesa', '2027-12-15', '2026-01-15', 'admin@edoc.com', 'received', '2026-01-15', '', ''),
(2, 'Medical Equipment', 'MediPlugequipments', 'Blood Pressure Monitor', 'box', '50', 30, 30, 2500.00, 3000.00, 'MediPlugequipments', 'cash', '2034-02-15', '2026-01-15', 'superadmin', 'received', '2026-01-15', '', ''),
(3, 'Medications', 'VeePharmaceutical', 'cetrizine', 'tablet', '10', 30, 30, 10.00, 15.00, 'VeePharmaceutical', 'cash', '2028-01-15', '2026-01-15', 'superadmin', 'received', '2026-01-15', '', ''),
(4, 'Medications', 'RahaPharmacy', 'Laxatives', 'bottle', '1', 25, 25, 300.00, 350.00, 'RahaPharmacy', 'cash', '2026-12-15', '2026-01-15', 'superadmin', 'received', '2026-01-15', 'root', ''),
(5, 'Medications', 'RahaPharmacy', 'Laxatives', 'bottle', '1', 5, 5, 300.00, 350.00, 'RahaPharmacy', 'cash', '2026-12-15', '2026-01-15', 'superadmin', 'received', '2026-01-15', 'superadmin', ''),
(8, 'Medical Equipment', 'MediPlugequipments', 'Blood Pressure Monitor', 'box', '50', 9, 9, 2500.00, 3000.00, 'MediPlugequipments', 'cash', '2029-06-15', '2026-01-15', 'superadmin', 'received', '2026-01-15', 'superadmin', ''),
(9, 'Medications', 'Transchem pharmaceuticals', 'Mara Moja', 'box', '100', 15, 15, 10.00, 15.00, 'Transchem pharmaceuticals', 'Mpesa', '2026-04-30', '2026-01-15', 'superadmin', 'received', '2026-01-15', 'superadmin', ''),
(10, 'Medications', 'RahaPharmacy', 'Piriton', 'tablet', '1', 30, 20, 10.00, 15.00, 'RahaPharmacy', 'Mpesa', '2026-06-15', '2026-01-15', 'superadmin', 'received', '2026-01-15', 'superadmin', ''),
(11, 'Medications', 'RahaPharmacy', 'Celestamine', 'tablet', '10', 30, 30, 200.00, 300.00, 'RahaPharmacy', 'cash', '2026-04-08', '2026-01-15', 'superadmin', 'received', '2026-01-15', 'superadmin', ''),
(12, 'Medications', 'RahaPharmacy', 'Inhaler', 'bottle', '1', 50, 50, 200.00, 450.00, 'RahaPharmacy', 'Mpesa', '2027-12-17', '2026-01-17', 'superadmin', 'received', '2026-01-17', 'superadmin', ''),
(13, 'Medications', 'VeePharmaceutical', 'metoprolol', 'sachet', '30', 30, 30, 200.00, 300.00, 'VeePharmaceutical', 'cash', '2026-04-30', '2026-01-18', 'superadmin', 'received', '2026-01-18', 'superadmin', ''),
(14, 'Medications', 'RahaPharmacy', 'Panadol', 'tablet', '1', 100, 100, 10.00, 20.00, 'RahaPharmacy', 'cash', '2026-01-31', '2026-01-20', 'maryanne', 'received', '2026-01-20', 'maryanne', ''),
(15, 'Medications', 'RahaPharmacy', 'Piriton', 'tablet', '1', 100, 100, 10.00, 15.00, 'RahaPharmacy', 'cash', '2026-06-21', '2026-01-21', 'superadmin', 'received', '2026-01-21', 'superadmin', ''),
(16, 'Medications', 'Transchem pharmaceuticals', 'Mara Moja', 'box', '100', 100, 100, 10.00, 15.00, 'Transchem pharmaceuticals', 'cash', '2026-11-05', '2026-02-05', 'superadmin', 'received', '2026-02-05', 'superadmin', '');

-- --------------------------------------------------------

--
-- Table structure for table `return_products`
--

CREATE TABLE `return_products` (
  `id` int(11) NOT NULL,
  `bill_no` varchar(50) NOT NULL,
  `return_date` datetime DEFAULT current_timestamp(),
  `product_company` varchar(255) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_unit` varchar(100) NOT NULL,
  `packing_size` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `returned_by` varchar(100) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `return_products`
--

INSERT INTO `return_products` (`id`, `bill_no`, `return_date`, `product_company`, `product_name`, `product_unit`, `packing_size`, `price`, `quantity`, `total`, `returned_by`, `reason`, `created_at`) VALUES
(1, 'PHARM-0038', '2025-11-05 13:45:22', 'GracePharmacuticals', 'Cough syrup', 'ml', '100', 750.00, 1, 750.00, 'superadmin', 'wrong brand.', '2025-11-05 10:45:22'),
(2, 'PHARM-0001', '2026-01-18 23:02:55', 'RahaPharmacy', 'Piriton', 'tablet', '1', 15.00, 10, 150.00, 'maryanne', 'defective', '2026-01-18 20:02:55'),
(3, 'PHARM-0013', '2026-01-21 12:49:41', 'VeePharmaceutical', 'cetrizine', 'tablet', '10', 15.00, 5, 75.00, 'superadmin', 'PATIENT DIDN\\\'T PAY FOR IT', '2026-01-21 09:49:41');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(64) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Super Admin', 'Full system access', '2025-09-23 11:28:34', NULL),
(2, 'Admin', 'Administrative user', '2025-09-23 11:28:34', NULL),
(3, 'Doctor', 'Medical practitioner', '2025-09-23 11:28:34', NULL),
(4, 'Nurse', 'Nursing staff', '2025-09-23 11:28:34', NULL),
(5, 'Pharmacist', 'Pharmacy staff', '2025-09-23 11:28:34', NULL),
(6, 'Lab Technician', 'Laboratory staff', '2025-09-23 11:28:34', NULL),
(7, 'Accountant', 'Finance staff', '2025-09-23 11:28:34', NULL),
(8, 'Receptionist', 'Front desk', '2025-09-23 11:28:34', NULL),
(9, 'Patient', 'Patient account', '2025-09-23 11:28:34', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(10) UNSIGNED NOT NULL,
  `permission_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(1, 10),
(1, 11),
(1, 12),
(1, 13),
(1, 14),
(1, 15),
(1, 16),
(1, 17),
(1, 18),
(2, 7),
(2, 8),
(2, 9),
(2, 10),
(2, 11),
(2, 12),
(2, 13),
(2, 15),
(2, 16),
(5, 13),
(5, 14);

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(10) UNSIGNED NOT NULL,
  `ward_id` int(10) UNSIGNED NOT NULL,
  `room_number` varchar(30) NOT NULL,
  `floor` varchar(20) DEFAULT NULL,
  `type` enum('general','private','icu','maternity','surgical') DEFAULT 'general',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `duration_minutes` int(11) DEFAULT NULL COMMENT 'Duration in minutes',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`, `category`, `description`, `price`, `duration_minutes`, `is_active`, `created_at`, `updated_at`) VALUES
(2, 'Specialist Consultation', 'Consultation', 'Consultation with a medical specialist', 1200.00, 45, 1, '2025-10-26 06:59:09', '2026-01-16 20:42:46'),
(3, 'Emergency Consultation', 'Consultation', 'Urgent medical consultation', 5000.00, 60, 1, '2025-10-26 06:59:09', NULL),
(4, 'X-Ray', 'Radiology', 'Diagnostic X-ray imaging', 2500.00, 30, 1, '2025-10-26 06:59:09', NULL),
(5, 'Ultrasound', 'Radiology', 'Ultrasound diagnostic imaging', 4500.00, 45, 1, '2025-10-26 06:59:09', NULL),
(7, 'MRI', 'Radiology', 'Magnetic Resonance Imaging', 25000.00, 90, 1, '2025-10-26 06:59:09', NULL),
(8, 'Blood Test', 'Laboratory', 'Complete blood count test', 800.00, 15, 1, '2025-10-26 06:59:09', NULL),
(9, 'Urine Analysis', 'Laboratory', 'Complete urine analysis', 600.00, 15, 1, '2025-10-26 06:59:09', NULL),
(10, 'Blood Sugar Test', 'Laboratory', 'Blood glucose level test', 400.00, 10, 1, '2025-10-26 06:59:09', '2025-10-26 07:11:17'),
(12, 'Echocardiogram', 'Cardiology', 'Heart ultrasound', 5000.00, 60, 1, '2025-10-26 06:59:09', '2025-10-27 07:57:13'),
(13, 'Minor Surgery', 'Surgery', 'Minor surgical procedure', 15000.00, 120, 1, '2025-10-26 06:59:09', NULL),
(14, 'Dressing Change', 'Nursing', 'Wound dressing change', 500.00, 15, 1, '2025-10-26 06:59:09', NULL),
(15, 'Vaccination', 'Immunization', 'Routine vaccination', 700.00, 20, 1, '2025-10-26 06:59:09', '2025-10-26 07:15:58'),
(16, 'Physiotherapy Session', 'Therapy', 'Physical therapy session', 2000.00, 60, 1, '2025-10-26 06:59:09', NULL),
(17, 'General Consultation', 'Consultation', 'General consultation with a general doctor/specialist', 500.00, 30, 1, '2025-10-26 07:36:50', NULL),
(18, 'HIV ANTIBODY TEST', 'Laboratory', 'Used to test for HIV', 300.00, 45, 1, '2025-10-30 13:19:18', NULL),
(19, 'stool sample tests', 'Laboratory', 'Diagnosing infections from bacteria, viruses, or parasites present in the stool.', 550.00, 45, 1, '2025-10-30 17:37:02', NULL),
(20, 'Uric Acid Test', 'Laboratory', 'Used to test the levels of uric in the blood stream.', 700.00, 45, 1, '2025-10-30 21:17:29', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `sos_alerts`
--

CREATE TABLE `sos_alerts` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `emergency_type` varchar(100) NOT NULL,
  `priority` enum('critical','high','medium','low') DEFAULT 'high',
  `status` enum('pending','active','acknowledged','responded','resolved','cancelled') DEFAULT 'pending',
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `location_address` text DEFAULT NULL,
  `medical_summary` text DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `response_team_id` int(11) DEFAULT NULL,
  `estimated_arrival` datetime DEFAULT NULL,
  `actual_arrival` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `responded_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sos_alerts`
--

INSERT INTO `sos_alerts` (`id`, `patient_id`, `emergency_type`, `priority`, `status`, `latitude`, `longitude`, `location_address`, `medical_summary`, `assigned_to`, `response_team_id`, `estimated_arrival`, `actual_arrival`, `created_at`, `updated_at`, `responded_at`, `resolved_at`, `notes`) VALUES
(2, 13, 'Medical Emergency', 'high', 'responded', -1.39510029, 36.75470029, NULL, 'Patient: Sandra Kosgei\nPatient ID: PT000013\n', 55, 1, '0000-00-00 00:00:00', NULL, '2026-01-17 22:09:07', '2026-01-17 22:43:30', NULL, NULL, NULL),
(3, 8, 'Medical Emergency', 'high', 'responded', -1.39516758, 36.75474984, NULL, 'Patient: Valery Odeku\nPatient ID: PT000008\n', 55, 1, '0000-00-00 00:00:00', NULL, '2026-01-17 23:05:08', '2026-01-17 23:51:49', NULL, NULL, NULL),
(4, 13, 'Medical Emergency', 'high', 'responded', -1.39512509, 36.75472461, NULL, 'Patient: Sandra Kosgei\nPatient ID: PT000013\n', 55, 1, '0000-00-00 00:00:00', NULL, '2026-01-19 09:34:31', '2026-01-19 09:35:14', NULL, NULL, NULL),
(5, 9, 'Medical Emergency', 'high', 'responded', -1.39506675, 36.75469557, NULL, 'Patient: Hannah Nyamwange\nPatient ID: PT000009\n', 55, 1, '0000-00-00 00:00:00', NULL, '2026-01-22 23:15:37', '2026-02-01 22:15:31', NULL, NULL, NULL),
(6, 13, 'Medical Emergency', 'high', 'responded', -1.39526372, 36.75486624, NULL, 'Patient: Sandra Kosgei\nPatient ID: PT000013\n', 55, 1, '0000-00-00 00:00:00', NULL, '2026-02-01 22:14:14', '2026-02-01 22:15:31', NULL, NULL, NULL),
(7, 13, 'Medical Emergency', 'high', 'cancelled', -1.39512780, 36.75481980, NULL, 'Patient: Sandra Kosgei\nPatient ID: PT000013\n', NULL, NULL, NULL, NULL, '2026-02-04 11:38:33', '2026-02-04 11:45:48', NULL, '2026-02-04 11:45:48', NULL),
(9, 12, 'Medical Emergency', 'high', 'responded', -1.39510870, 36.75482190, NULL, 'Patient: Phoebe Ndalo\nPatient ID: PT000012\n', 55, 2, '0000-00-00 00:00:00', NULL, '2026-02-04 11:56:13', '2026-02-04 11:57:54', NULL, NULL, NULL),
(10, 15, 'Medical Emergency', 'high', 'responded', -1.39510490, 36.75482020, NULL, 'Patient: Ria Kumari\nPatient ID: PT000015\n', 55, 1, '0000-00-00 00:00:00', NULL, '2026-02-05 17:49:08', '2026-02-05 17:49:48', NULL, NULL, NULL),
(11, 14, 'Medical Emergency', 'high', 'responded', -1.39594980, 36.75686680, NULL, 'Patient: Emmanuel Maothe\nPatient ID: PT000014\n', 55, 1, '0000-00-00 00:00:00', NULL, '2026-02-05 21:34:27', '2026-02-05 21:37:52', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `staff_licenses`
--

CREATE TABLE `staff_licenses` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `license_type` varchar(255) NOT NULL,
  `license_number` varchar(255) NOT NULL,
  `issuing_authority` varchar(255) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` enum('active','expired','suspended','revoked') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_licenses`
--

INSERT INTO `staff_licenses` (`id`, `user_id`, `license_type`, `license_number`, `issuing_authority`, `issue_date`, `expiry_date`, `status`, `created_at`, `updated_at`) VALUES
(1, 64, 'Medical License', 'A0X235', NULL, '2025-01-01', '2026-01-01', 'active', '2025-10-05 15:28:21', NULL),
(2, 66, 'Medical License', 'B0Y2125', NULL, '2025-10-01', '2026-10-06', 'active', '2025-10-06 10:41:12', NULL),
(3, 67, 'Medical License', 'A0X235', NULL, '2016-11-26', '2027-06-26', 'active', '2025-10-26 12:19:14', NULL),
(4, 68, 'Medical License', 'A0X235', NULL, '2014-05-27', '2029-06-27', 'active', '2025-10-27 07:53:20', NULL),
(5, 69, 'Medical License', 'B0Y2125', NULL, '2020-01-30', '2027-01-30', 'active', '2025-10-30 12:21:16', NULL),
(6, 70, 'CFA', 'CF21098X', NULL, '2022-05-30', '2032-12-31', 'active', '2025-10-30 13:36:41', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `staff_profiles`
--

CREATE TABLE `staff_profiles` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `department_id` int(10) UNSIGNED DEFAULT NULL,
  `employee_number` varchar(50) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `job_title` varchar(100) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `salary` decimal(12,2) DEFAULT 0.00,
  `address` text DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('female','male','other') DEFAULT 'female',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_profiles`
--

INSERT INTO `staff_profiles` (`id`, `user_id`, `department_id`, `employee_number`, `department`, `job_title`, `hire_date`, `salary`, `address`, `date_of_birth`, `gender`, `created_at`, `updated_at`) VALUES
(1, 64, NULL, 'EMP00001', 'neurology', 'Doctor', '2025-10-05', 150000.00, 'Nairobi\r\nKenya', '1990-06-12', 'male', '2025-10-05 15:28:21', NULL),
(2, 65, NULL, 'EMP00002', 'operations', 'System Administrator', '2025-10-06', 100000.00, 'Nairobi\r\nKenya', '1993-11-08', 'female', '2025-10-05 15:33:05', NULL),
(3, 66, NULL, 'EMP00003', 'laboratory', 'Lab Technician', '2025-10-06', 75000.00, 'Nairobi\r\nKenya', '1987-05-13', 'female', '2025-10-06 10:41:12', NULL),
(4, 67, NULL, 'EMP00004', 'pediatrics', 'Doctor', '2025-10-26', 95000.00, 'Nairobi\r\nKenya', '1997-02-26', 'female', '2025-10-26 12:19:14', NULL),
(5, 68, NULL, 'EMP00005', 'orthopedics', 'Doctor', '2025-10-15', 120000.00, 'Nairobi\r\nKenya', '1975-02-27', 'male', '2025-10-27 07:53:20', NULL),
(6, 69, NULL, 'EMP00006', 'orthopedics', 'Doctor', '2025-11-01', 100000.00, 'Nairobi\r\nKenya', '1997-01-30', 'male', '2025-10-30 12:21:16', NULL),
(7, 70, NULL, 'EMP00007', 'finance', 'Accountant', '2025-11-04', 85000.00, 'Nairobi\r\nKenya', '2000-03-27', 'female', '2025-10-30 13:36:41', NULL),
(8, 71, NULL, 'EMP00008', 'finance', 'Accountant', '2025-10-30', 75000.00, 'Nairobi\r\nKenya', '1987-12-13', 'male', '2025-10-30 14:32:21', NULL),
(9, 72, NULL, 'EMP00009', 'pharmacy', 'Pharmacist', '2025-10-31', 50000.00, 'Nairobi\r\nKenya', '1990-02-08', 'female', '2025-10-30 14:40:25', NULL),
(10, 73, NULL, 'EMP00010', 'laboratory', 'Lab Technician', '2025-11-01', 75000.00, 'Nairobi\r\nKenya', '1997-03-21', 'female', '2025-10-31 19:05:59', NULL);

--
-- Triggers `staff_profiles`
--
DELIMITER $$
CREATE TRIGGER `log_employment_change` AFTER UPDATE ON `staff_profiles` FOR EACH ROW BEGIN

    IF OLD.job_title != NEW.job_title OR OLD.department != NEW.department OR OLD.salary != NEW.salary THEN

        INSERT INTO staff_employment_history (

            user_id, employee_number, department, job_title, 

            employment_type, salary, start_date, end_date

        ) VALUES (

            OLD.user_id, OLD.employee_number, OLD.department, OLD.job_title,

            'full-time', OLD.salary, OLD.hire_date, CURDATE()

        );

    END IF;

END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `staff_qualifications`
--

CREATE TABLE `staff_qualifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `degree_certification` varchar(255) NOT NULL,
  `institution` varchar(255) DEFAULT NULL,
  `year_completed` varchar(10) DEFAULT NULL,
  `field_of_study` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_qualifications`
--

INSERT INTO `staff_qualifications` (`id`, `user_id`, `degree_certification`, `institution`, `year_completed`, `field_of_study`, `created_at`, `updated_at`) VALUES
(1, 64, 'Bachelor of Medicine', 'UON', '2020', NULL, '2025-10-05 15:28:21', NULL),
(2, 65, 'Bachelor of Project Management', 'Kenyatta University', '2021', NULL, '2025-10-05 15:33:05', NULL),
(3, 66, 'Bachelor of Medicine', 'Daystar University', '2015', NULL, '2025-10-06 10:41:12', NULL),
(4, 67, 'Bachelor of Medicine', 'UON', '2016', NULL, '2025-10-26 12:19:14', NULL),
(5, 68, 'Bachelor of Medicine in Orthopedics', 'UON', '2012', NULL, '2025-10-27 07:53:20', NULL),
(6, 69, 'Bachelor of Medicine', 'UON', '2020', NULL, '2025-10-30 12:21:16', NULL),
(7, 70, 'Bachelor of Finance', 'KCA university', '2020', NULL, '2025-10-30 13:36:41', NULL),
(8, 71, 'Bachelor of Finance', 'UON', '2015', NULL, '2025-10-30 14:32:21', NULL),
(9, 72, 'Bachelor of Pharmacy', 'KEMRI', '2019', NULL, '2025-10-30 14:40:25', NULL),
(10, 73, 'Bachelor of laboratory Sciences', 'UON', '2020', NULL, '2025-10-31 19:05:59', NULL);

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
(2, 'MediCare Pharmaceuticals', 'Surgical Spirit', 'ml', '100', 50, 800.00),
(5, 'GracePharmacuticals', 'Cough syrup', 'ml', '100', 39, 750.00),
(6, 'VeePharmaceutical', 'Ibuprofen', 'box', '200', 98, 700.00),
(8, 'Transchem Pharmaceutical', 'Eno', 'sachet', '200', 70, 15.00),
(9, 'MediPlugequipments', 'syringes', 'injection', '100', 5, 115.00),
(10, 'MediCare Pharmaceuticals', 'Amoxicillin', 'box', '100', 44, 850.00),
(11, 'MediPlugequipments', 'Surgical Gloves', 'box', '100', 200, 2300.00),
(12, 'MediPlugequipments', 'Blood Pressure Monitor', 'box', '50', 1, 3000.00),
(13, 'GracePharmaceuticals', 'Ponstan', 'tablet', '50', 5, 65.00),
(14, 'VeePharmaceutical', 'Insulin', 'box', '5', 100, 1950.00),
(15, 'OdwourInc', 'Printers', 'each', '5', 10, 6500.00),
(16, 'OdwourInc', 'Binders', 'box', '50', 200, 950.00),
(17, 'VeePharmaceutical', 'asthma pump', 'pump', '10', 23, 750.00),
(18, 'MediCare Pharmaceuticals', 'Amoxicillin', 'mg', '100', 99, 850.00),
(19, 'Transchem pharmaceuticals', 'Agumentin', 'tablet', '100', 100, 825.00),
(20, 'RahaPharmacy', 'Agumentin', 'tablet', '100', 150, 850.00),
(21, 'Transchem pharmaceuticals', 'Mara Moja', 'box', '100', 290, 15.00),
(22, 'MediCare Pharmaceuticals', 'cetrizine', 'tablet', '10', 100, 15.00),
(23, 'RahaPharmacy', 'Laxatives', 'bottle', '1', 30, 350.00),
(24, 'RahaPharmacy', 'Piriton', 'tablet', '1', 20, 15.00),
(25, 'RahaPharmacy', 'Celestamine', 'tablet', '10', 30, 300.00),
(26, 'RahaPharmacy', 'Inhaler', 'bottle', '1', 50, 450.00),
(27, 'VeePharmaceutical', 'metoprolol', 'sachet', '30', 30, 300.00),
(28, 'RahaPharmacy', 'Panadol', 'tablet', '1', 100, 20.00);

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(10) UNSIGNED NOT NULL,
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
(2, 'James', 'Kerui', 'Transchem pharmaceuticals', 'Medications', '0726964287', 'Nairobi', 'Nairobi'),
(3, 'Jane', 'Waweru', 'MedixPro', 'Medical Equipment', '0726921346', 'Nairobi\r\nKenya', 'Nairobi'),
(4, 'Vivian', 'chepchumba', 'VeePharmaceutical', 'Medications', '0780965321', 'Nairobi\r\nKenya', 'Nairobi'),
(5, 'Grace', 'Wamuhu', 'GracePharmaceuticals', 'Medications', '0134908765', 'Nairobi\r\nKenya', 'Nairobi'),
(6, 'Kimberly', 'Maranga', 'kimPharm', 'Medical Supplies', '0134908765', 'Kisumu,\r\nKenya', 'Kisumu'),
(7, 'Mary', 'Wairimu', 'MediPlugequipments', 'Medical Equipment', '0780965321', 'Nairobi\r\nKenya', 'Nairobi'),
(8, 'Hillary', 'Odwour', 'OdwourInc', 'Office Supplies', '0726964186', 'Kisumu,\r\nKenya', 'Kisumu'),
(10, 'James', 'Maina', 'MediCare Pharmaceuticals', 'Medications', '0780964095', 'Eldoret, Kenya', 'Eldoret'),
(11, 'Daha', 'Raha', 'RahaPharmacy', 'Medications', '0726962099', 'Mombasa, Kenya', 'Mombasa');

-- --------------------------------------------------------

--
-- Table structure for table `system_notifications`
--

CREATE TABLE `system_notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') DEFAULT 'info',
  `related_entity_type` enum('lab_order','appointment','patient') DEFAULT NULL,
  `related_entity_id` int(10) UNSIGNED DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_notifications`
--

INSERT INTO `system_notifications` (`id`, `user_id`, `title`, `message`, `type`, `related_entity_type`, `related_entity_id`, `is_read`, `created_at`) VALUES
(1, 55, 'Sample Collected', 'Sample collected for Christopher Mwasi - Other Test', 'info', 'lab_order', 2, 0, '2025-10-27 22:59:40'),
(2, 55, 'Results Submitted', 'Test results submitted for Christopher Mwasi', 'success', 'lab_order', 2, 0, '2025-10-27 23:24:36'),
(3, 55, 'Sample Collected', 'Sample collected for Hannah Nyamwange - ', 'info', 'lab_order', 3, 0, '2025-10-27 23:52:53'),
(4, 55, 'Results Submitted', 'Test results submitted for Hannah Nyamwange', 'success', 'lab_order', 3, 0, '2025-10-27 23:54:27'),
(5, 55, 'Sample Collected', 'Sample collected for Susan Chemutai - Blood Test', 'info', 'lab_order', 7, 0, '2025-10-30 12:34:02'),
(6, 55, 'Sample Collected', 'Sample collected for Susan Chemutai - Blood Test', 'info', 'lab_order', 7, 0, '2025-10-30 12:34:28'),
(7, 55, 'Lab Results Available', 'Test results are ready for Susan Chemutai', 'success', 'lab_order', 7, 0, '2025-10-30 12:36:48'),
(8, 55, 'Lab Results Available', 'Test results are ready for Susan Chemutai', 'success', 'lab_order', 7, 0, '2025-10-30 12:37:11'),
(9, 55, 'Sample Collected', 'Sample collected for Valery Odeku - Blood Test', 'info', 'lab_order', 8, 0, '2025-10-30 17:25:28'),
(10, 55, 'Lab Results Available', 'Test results are ready for Valery Odeku', 'success', 'lab_order', 8, 0, '2025-10-30 17:26:04'),
(11, 55, 'Sample Collected', 'Sample collected for Mary Njeri - stool sample tests', 'info', 'lab_order', 9, 0, '2025-10-30 17:38:33'),
(12, 55, 'Lab Results Available', 'Test results are ready for Mary Njeri', 'success', 'lab_order', 9, 0, '2025-10-30 17:39:11'),
(13, 55, 'Sample Collected', 'Sample collected for Susan Chemutai - Uric Acid Test, Urine Analysis', 'info', 'lab_order', 11, 0, '2025-10-30 21:19:32'),
(14, 55, 'Lab Results Available', 'Test results are ready for Susan Chemutai', 'success', 'lab_order', 11, 0, '2025-10-30 21:20:39'),
(15, 69, 'Sample Collected', 'Sample collected for Christopher Mwasi - Blood Test, Uric Acid Test', 'info', 'lab_order', 12, 0, '2025-10-31 19:39:04'),
(16, 69, 'Lab Results Available', 'Test results are ready for Christopher Mwasi', 'success', 'lab_order', 12, 0, '2025-10-31 19:40:54'),
(17, 69, 'Sample Collected', 'Sample collected for Phoebe Ndalo - Blood Test', 'info', 'lab_order', 13, 0, '2025-11-02 13:33:10'),
(18, 69, 'Lab Results Available', 'Test results are ready for Phoebe Ndalo', 'success', 'lab_order', 13, 0, '2025-11-02 13:33:53'),
(19, 59, 'Sample Collected', 'Sample collected for Mary Njeri - Blood Test, HIV ANTIBODY TEST, Uric Acid Test, Urine Analysis', 'info', 'lab_order', 14, 0, '2025-11-02 18:44:43'),
(20, 59, 'Lab Results Available', 'Test results are ready for Mary Njeri', 'success', 'lab_order', 14, 0, '2025-11-02 18:49:56'),
(21, 55, 'Sample Collected', 'Sample collected for Hillary Manase - Blood Test', 'info', 'lab_order', 15, 0, '2025-11-03 20:51:13'),
(22, 55, 'Lab Results Available', 'Test results are ready for Hillary Manase', 'success', 'lab_order', 15, 0, '2025-11-03 20:51:47'),
(23, 69, 'Sample Collected', 'Sample collected for Phoebe Ndalo - Blood Test', 'info', 'lab_order', 16, 0, '2025-11-04 07:40:38'),
(24, 69, 'Lab Results Available', 'Test results are ready for Phoebe Ndalo', 'success', 'lab_order', 16, 0, '2025-11-04 07:41:18'),
(25, 55, 'Sample Collected', 'Sample collected for Sandra Kosgei - Blood Test, stool sample tests', 'info', 'lab_order', 17, 0, '2025-11-06 08:31:34'),
(26, 55, 'Lab Results Available', 'Test results are ready for Sandra Kosgei', 'success', 'lab_order', 17, 0, '2025-11-06 08:32:42'),
(27, 69, 'Sample Collected', 'Sample collected for Emmanuel Maothe - Blood Test', 'info', 'lab_order', 18, 0, '2025-11-14 07:06:46'),
(28, 69, 'Lab Results Available', 'Test results are ready for Emmanuel Maothe', 'success', 'lab_order', 18, 0, '2025-11-14 07:07:16'),
(29, 59, 'Sample Collected', 'Sample collected for Valery Odeku - Blood Test, stool sample tests', 'info', 'lab_order', 19, 0, '2026-01-09 09:00:21'),
(30, 59, 'Lab Results Available', 'Test results are ready for Valery Odeku', 'success', 'lab_order', 19, 0, '2026-01-09 09:02:03'),
(31, 67, 'Sample Collected', 'Sample collected for Hannah Nyamwange - Blood Test', 'info', 'lab_order', 20, 0, '2026-01-15 20:45:38'),
(32, 67, 'Lab Results Available', 'Test results are ready for Hannah Nyamwange', 'success', 'lab_order', 20, 0, '2026-01-15 20:47:25'),
(33, 60, 'Sample Collected', 'Sample collected for Susan Chemutai - Blood Sugar Test, Blood Test, stool sample tests, Urine Analysis', 'info', 'lab_order', 21, 0, '2026-01-16 10:19:31'),
(34, 60, 'Lab Results Available', 'Test results are ready for Susan Chemutai', 'success', 'lab_order', 21, 0, '2026-01-16 10:21:12'),
(35, 59, 'Sample Collected', 'Sample collected for Christopher Mwasi - Blood Test, stool sample tests, Urine Analysis', 'info', 'lab_order', 22, 0, '2026-01-16 20:57:17'),
(36, 59, 'Lab Results Available', 'Test results are ready for Christopher Mwasi', 'success', 'lab_order', 22, 0, '2026-01-16 20:58:28'),
(37, 69, 'Sample Collected', 'Sample collected for Phoebe Ndalo - Blood Test, Urine Analysis', 'info', 'lab_order', 23, 0, '2026-01-17 07:30:37'),
(38, 69, 'Lab Results Available', 'Test results are ready for Phoebe Ndalo', 'success', 'lab_order', 23, 0, '2026-01-17 07:32:01'),
(39, 59, 'Sample Collected', 'Sample collected for Mary Njeri - Blood Sugar Test, Blood Test', 'info', 'lab_order', 24, 0, '2026-01-17 08:36:38'),
(40, 59, 'Lab Results Available', 'Test results are ready for Mary Njeri', 'success', 'lab_order', 24, 0, '2026-01-17 08:38:09'),
(41, 55, 'Sample Collected', 'Sample collected for Tanya Mutana - stool sample tests', 'info', 'lab_order', 25, 0, '2026-01-17 10:26:03'),
(42, 55, 'Lab Results Available', 'Test results are ready for Tanya Mutana', 'success', 'lab_order', 25, 0, '2026-01-17 10:26:38'),
(43, 55, 'Sample Collected', 'Sample collected for Hillary Manase - stool sample tests', 'info', 'lab_order', 26, 0, '2026-01-17 14:11:36'),
(44, 55, 'Lab Results Available', 'Test results are ready for Hillary Manase', 'success', 'lab_order', 26, 0, '2026-01-17 14:12:02'),
(45, 69, 'Sample Collected', 'Sample collected for Phoebe Ndalo - Blood Test', 'info', 'lab_order', 27, 0, '2026-01-19 11:25:15'),
(46, 69, 'Lab Results Available', 'Test results are ready for Phoebe Ndalo', 'success', 'lab_order', 27, 0, '2026-01-19 11:25:46'),
(0, 64, 'Sample Collected', 'Sample collected for Kelvin Mwasi - ', 'info', 'lab_order', 29, 0, '2026-01-20 10:39:23'),
(0, 64, 'Sample Collected', 'Sample collected for Kelvin Mwasi - Blood Test', 'info', 'lab_order', 51, 0, '2026-01-20 10:44:43'),
(0, 64, 'Lab Results Available', 'Test results are ready for Kelvin Mwasi', 'success', 'lab_order', 51, 0, '2026-01-20 10:45:08'),
(0, 60, 'Sample Collected', 'Sample collected for Achieng Odwour - Blood Test', 'info', 'lab_order', 52, 0, '2026-01-20 11:09:42'),
(0, 60, 'Lab Results Available', 'Test results are ready for Achieng Odwour', 'success', 'lab_order', 52, 0, '2026-01-20 11:10:02'),
(0, 69, 'Sample Collected', 'Sample collected for Ria Kumari - Blood Sugar Test', 'info', 'lab_order', 53, 0, '2026-01-20 13:10:53'),
(0, 59, 'Sample Collected', 'Sample collected for Bravin Magothe - Blood Test', 'info', 'lab_order', 28, 0, '2026-01-20 13:11:02'),
(0, 69, 'Lab Results Available', 'Test results are ready for Ria Kumari', 'success', 'lab_order', 53, 0, '2026-01-20 13:11:27'),
(0, 60, 'Sample Collected', 'Sample collected for Brianna Wambui - Blood Sugar Test, Blood Test', 'info', 'lab_order', 54, 0, '2026-01-20 18:45:13'),
(0, 60, 'Lab Results Available', 'Test results are ready for Brianna Wambui', 'success', 'lab_order', 54, 0, '2026-01-20 18:48:37'),
(0, 55, 'Sample Collected', 'Sample collected for Susan Chemutai - stool sample tests', 'info', 'lab_order', 55, 0, '2026-01-20 20:29:24'),
(0, 55, 'Lab Results Available', 'Test results are ready for Susan Chemutai', 'success', 'lab_order', 55, 0, '2026-01-20 20:29:54'),
(0, 55, 'Sample Collected', 'Sample collected for Kelvin Mwasi - Blood Test', 'info', 'lab_order', 56, 0, '2026-01-20 20:49:21'),
(0, 55, 'Lab Results Available', 'Test results are ready for Kelvin Mwasi', 'success', 'lab_order', 56, 0, '2026-01-20 20:49:56'),
(0, 61, 'Sample Collected', 'Sample collected for Nadia Mutana - Blood Test', 'info', 'lab_order', 57, 0, '2026-01-21 11:55:15'),
(0, 61, 'Lab Results Available', 'Test results are ready for Nadia Mutana', 'success', 'lab_order', 57, 0, '2026-01-21 12:01:10'),
(0, 61, 'Lab Results Available', 'Test results are ready for Nadia Mutana', 'success', 'lab_order', 57, 0, '2026-01-21 12:01:43'),
(0, 55, 'Sample Collected', 'Sample collected for Hannah Nyamwange - Blood Test', 'info', 'lab_order', 58, 0, '2026-01-22 19:52:14'),
(0, 55, 'Lab Results Available', 'Test results are ready for Hannah Nyamwange', 'success', 'lab_order', 58, 0, '2026-01-22 19:52:43'),
(0, 68, 'Sample Collected', 'Sample collected for Ria Kumari - Uric Acid Test', 'info', 'lab_order', 59, 0, '2026-02-05 18:12:44'),
(0, 68, 'Lab Results Available', 'Test results are ready for Ria Kumari', 'success', 'lab_order', 59, 0, '2026-02-05 18:13:15'),
(0, 59, 'Lab Results Available', 'Test results are ready for Bravin Magothe', 'success', 'lab_order', 28, 0, '2026-02-05 18:13:50'),
(0, 64, 'Lab Results Available', 'Test results are ready for Kelvin Mwasi', 'success', 'lab_order', 29, 0, '2026-02-05 18:13:59'),
(0, 55, 'Sample Collected', 'Sample collected for Brianna Wambui - stool sample tests', 'info', 'lab_order', 60, 0, '2026-02-06 08:47:23'),
(0, 55, 'Lab Results Available', 'Test results are ready for Brianna Wambui', 'success', 'lab_order', 60, 0, '2026-02-06 08:47:47'),
(0, 68, 'Sample Collected', 'Sample collected for Sandra Kosgei - Blood Test', 'info', 'lab_order', 61, 0, '2026-02-06 11:03:52'),
(0, 68, 'Lab Results Available', 'Test results are ready for Sandra Kosgei', 'success', 'lab_order', 61, 0, '2026-02-06 11:04:19'),
(0, 55, 'Sample Collected', 'Sample collected for Christopher Mwasi - Blood Sugar Test, Blood Test', 'info', 'lab_order', 62, 0, '2026-02-06 11:53:19'),
(0, 55, 'Lab Results Available', 'Test results are ready for Christopher Mwasi', 'success', 'lab_order', 62, 0, '2026-02-06 11:54:15');

-- --------------------------------------------------------


--
-- Table structure for table `unified_billing`
--

CREATE TABLE `unified_billing` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `bill_number` varchar(50) NOT NULL,
  `bill_date` datetime DEFAULT current_timestamp(),
  `service_type` enum('consultation','pharmacy','lab','admission','procedure','other') NOT NULL,
  `service_description` text DEFAULT NULL,
  `reference_id` int(10) UNSIGNED DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `balance_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','partial','paid','cancelled') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `unified_billing`
--

INSERT INTO `unified_billing` (`id`, `patient_id`, `bill_number`, `bill_date`, `service_type`, `service_description`, `reference_id`, `total_amount`, `paid_amount`, `balance_amount`, `status`, `payment_method`, `payment_reference`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'BL-20240101-001', '2025-11-06 09:46:33', 'consultation', 'General consultation fee', NULL, 1500.00, 1500.00, 0.00, 'paid', NULL, NULL, 1, '2025-11-06 06:46:33', '2025-11-06 06:46:34'),
(2, 7, '00016', '2025-10-28 21:51:43', 'pharmacy', 'Pharmacy Bill - Susan Chemutai', NULL, 0.00, 45.00, 0.00, 'paid', NULL, NULL, NULL, '2025-11-06 07:13:36', '2025-11-06 07:13:36'),
(3, 6, '00017', '2025-10-30 11:10:02', 'pharmacy', 'Pharmacy Bill - Alpha Mwirigi', NULL, 0.00, 750.00, 0.00, 'paid', NULL, NULL, NULL, '2025-11-06 07:13:36', '2025-11-06 07:13:36'),
(4, 1, '00018', '2025-10-30 17:57:55', 'pharmacy', 'Pharmacy Bill - Stephanie Chepkemoi', NULL, 0.00, 1500.00, 0.00, 'paid', NULL, NULL, NULL, '2025-11-06 07:13:36', '2025-11-06 07:13:36'),
(5, 8, '00019', '2025-10-30 18:17:26', 'pharmacy', 'Pharmacy Bill - Valery Odeku', NULL, 0.00, 750.00, 0.00, 'paid', NULL, NULL, NULL, '2025-11-06 07:13:36', '2025-11-06 07:13:36'),
(6, 8, '00020', '2025-10-30 18:20:37', 'pharmacy', 'Pharmacy Bill - Valery Odeku', NULL, 0.00, 850.00, 0.00, 'paid', NULL, NULL, NULL, '2025-11-06 07:13:36', '2025-11-06 07:13:36'),
(7, 11, '00021', '2025-10-30 20:38:02', 'pharmacy', 'Pharmacy Bill - Mary Njeri', NULL, 0.00, 150.00, 0.00, 'paid', NULL, NULL, NULL, '2025-11-06 07:13:36', '2025-11-06 07:13:36'),
(8, 9, '00028', '2025-10-30 23:09:35', 'pharmacy', 'Pharmacy Bill - Hannah Nyamwange', NULL, 0.00, 325.00, 0.00, 'paid', NULL, NULL, NULL, '2025-11-06 07:13:36', '2025-11-06 07:13:36'),
(9, 12, '00029', '2025-11-02 14:43:59', 'pharmacy', 'Pharmacy Bill - Phoebe Ndalo', NULL, 0.00, 1650.00, 0.00, 'paid', NULL, NULL, NULL, '2025-11-06 07:13:36', '2025-11-06 07:13:36'),
(10, 11, 'PHARM-0003', '2025-11-02 19:35:00', 'pharmacy', 'Pharmacy Bill - Mary Njeri', NULL, 0.00, 250.00, 0.00, 'paid', NULL, NULL, NULL, '2025-11-06 07:13:36', '2025-11-06 07:13:36'),
(11, 1, '00031', '2025-11-03 18:52:03', 'pharmacy', 'Pharmacy Bill - Stephanie Chepkemoi', NULL, 0.00, 1400.00, 0.00, 'paid', NULL, NULL, NULL, '2025-11-06 07:13:36', '2025-11-06 07:13:36'),
(12, 9, '00032', '2025-11-03 19:04:17', 'pharmacy', 'Pharmacy Bill - Hannah Nyamwange', NULL, 0.00, 195.00, 0.00, 'paid', NULL, NULL, NULL, '2025-11-06 07:13:36', '2025-11-06 07:13:36'),
(13, 8, 'PHARM-0033', '2025-11-03 21:03:58', 'pharmacy', 'Pharmacy Bill - Valery Odeku', NULL, 0.00, 150.00, 0.00, 'paid', NULL, NULL, NULL, '2025-11-06 07:13:36', '2025-11-06 07:13:36'),
(14, 8, 'PHARM-0034', '2025-11-03 21:06:59', 'pharmacy', 'Pharmacy Bill - Valery Odeku', NULL, 0.00, 150.00, 0.00, 'paid', NULL, NULL, NULL, '2025-11-06 07:13:36', '2025-11-06 07:13:36'),
(15, 8, 'PHARM-0035', '2025-11-03 21:12:01', 'pharmacy', 'Pharmacy Bill - Valery Odeku', NULL, 0.00, 150.00, 0.00, 'paid', NULL, NULL, NULL, '2025-11-06 07:13:36', '2025-11-06 07:13:36'),
(16, 2, 'PHARM-0036', '2025-11-03 21:54:20', 'pharmacy', 'Pharmacy Bill - Hillary Manase', NULL, 0.00, 1600.00, 0.00, 'paid', NULL, NULL, NULL, '2025-11-06 07:13:36', '2025-11-06 07:13:36'),
(17, 12, 'PHARM-0037', '2025-11-04 09:10:52', 'pharmacy', 'Pharmacy Bill - Phoebe Ndalo', NULL, 1725.00, 5175.00, -1725.00, 'paid', 'cash', NULL, NULL, '2025-11-06 07:13:36', '2025-11-06 07:24:10'),
(18, 9, 'PHARM-0038', '2025-11-04 09:30:45', 'pharmacy', 'Pharmacy Bill - Hannah Nyamwange', NULL, 0.00, 0.00, 0.00, 'paid', 'cash', NULL, NULL, '2025-11-06 07:13:36', '2025-11-06 07:13:36');

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
  `id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('Super Admin','Doctor','Nurse','Pharmacist','Lab Technician','Accountant','Receptionist','Patient') NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `specialization` text NOT NULL,
  `schedule` text NOT NULL,
  `status` enum('active','inactive','on leave','suspended') DEFAULT 'active',
  `last_login` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role_id`, `username`, `email`, `password_hash`, `role`, `department`, `first_name`, `last_name`, `phone`, `specialization`, `schedule`, `status`, `last_login`, `created_at`, `updated_at`) VALUES
(55, 1, 'superadmin', 'vivianchepchumba32@gmail.com', '$2y$10$EhCN6Aev5C1Ii8ykt/ra2.qFxMy1jeJ71zh7FcHvvel48K5vdkRk6', 'Super Admin', 'operations', 'Vivian', 'chepchumba', '0726964087', '', '', 'active', '2026-02-06 14:16:39', '2025-10-01 11:56:39', '2026-02-06 11:16:39'),
(56, 8, 'staff', 'grace@gmail.com', '$2y$10$MJy8GDEv6Kww25K.Z04oQeE1lTZhh6tla.usYaj9eO3E2hxItdTyW', 'Receptionist', 'operations', 'Grace', 'Maina', '0726964056', '', '', 'active', '2026-02-06 13:57:39', '2025-10-01 11:57:34', '2026-02-06 10:57:39'),
(59, 3, 'Dr.Dana', 'dana@gmail.com', '$2y$10$FqfBWNxLmxqZnh9SzdAkeOV5d8o3AvviV0jzyEiek/9I57RQVzFEW', 'Doctor', 'cardiology', 'Danah', 'Murumba', '0712435780', '', '', 'active', '2026-01-20 12:59:40', '2025-10-01 18:39:17', '2026-01-20 09:59:40'),
(60, 3, 'Dr.Nyamal', 'nyamal254@gmail.com', '$2y$10$gIjmSoN9O3owxF4o8GcKuumLWVPbArQRHQDsuuAVhFJmzKbpeiAGa', 'Doctor', 'general care', 'Nyamal', 'Gai', '0189076321', '', '', 'active', '2026-01-21 11:37:23', '2025-10-01 18:40:06', '2026-01-21 08:37:23'),
(61, 3, 'Dr.Marvin', 'marvingaye@gmail.com', '$2y$10$PocQD1qQsE0fNAuYqRCej.cg6VaNMRAXrasdKLV4Xw7F.lqGXyh3m', 'Doctor', '', 'Marvin', 'Gaye', '0198674320', '', '', 'active', '2026-02-06 11:38:48', '2025-10-01 18:40:54', '2026-02-06 08:38:48'),
(62, 4, 'Chris', 'chris@gmail.com', '$2y$10$T9V.vDsIvOuZerqoGZvFueYou04TROlIuMoawDkPT4eoFHmGapFha', 'Nurse', 'emergency', 'Christopher', 'Mwaura', '0190456908', '', '', 'active', '2025-10-31 21:30:28', '2025-10-04 06:00:12', '2025-10-31 15:30:28'),
(63, 4, 'nursehannah', 'hannah@gmail.com', '$2y$10$K2h2cBXydpAJyQvfeyesVuSkDPRJIG2FOZvGWJGAcWddO.XN11vl2', 'Nurse', 'pediatrics', 'Hannah', 'Blakely', '0726978072', 'Child Care', '', 'active', '2026-01-22 23:16:38', '2025-10-05 11:58:44', '2026-01-22 20:16:38'),
(64, 3, 'Dr.Hilla', 'hillary@gmail.com', '$2y$10$jNWE/E6hEas89XmchAHAX.YuWaUWwgkoGA2yFF9sr3tT9wkbDjFh6', 'Doctor', '', 'Hillary', 'Mweni', '+254712350987', '', '', 'active', '2026-01-20 13:45:19', '2025-10-05 12:28:21', '2026-01-20 10:45:19'),
(65, 1, 'ritaaura', 'rita254@gmail.com', '$2y$10$XRsxLv.mFDp4GkYRGRXvjuyyJN8p.C4Xnl7LCFGIt5bMn9G6TeFr6', 'Super Admin', 'operations', 'Rita', 'Aura', '+254709785421', '', '', 'active', '2025-10-05 18:33:05', '2025-10-05 12:33:05', '2025-10-05 12:33:05'),
(66, 6, 'miriam', 'miri32@gmail.com', '$2y$10$VBxeFAQx/pIx7upiMJSddud/33zMFcn7rhPAfUai.0C9pA4IFiDaC', 'Lab Technician', 'laboratory', 'Miriam', 'Wambui', '+254719043218', '', '', 'active', '2026-02-06 14:03:45', '2025-10-06 07:41:12', '2026-02-06 11:03:45'),
(67, 3, 'Dr. Cate', 'cate32@gmail.com', '$2y$10$eR/zoPySxiESSLNsAoQisemrPZm2v5qgtUSxuedgt01/Td.4PUoiK', 'Doctor', 'pediatrics', 'Cathelene', 'Cheru', '0780964321', '', '', 'active', '2026-01-20 01:03:24', '2025-10-26 09:19:14', '2026-01-19 22:03:24'),
(68, 3, 'Dr. Job', 'jobkerui@gmail.com', '$2y$10$E3YN/Vf8lgON6Fkqr5eHIuEt0IcOi0dMWckEn3ZJcBpl62XC0Q/s2', 'Doctor', 'orthopedics', 'Job', 'Kerui', '0190764321', '', '', 'active', '2026-02-06 13:59:20', '2025-10-27 04:53:20', '2026-02-06 10:59:20'),
(69, 3, 'Dr.Collins', 'olwouch2@gmail.com', '$2y$10$irSoVNGBVjGIc8sFnzKa3uD/obyYTqBMEWnFG8pnVvfyUc1SfmzmO', 'Doctor', 'general care', 'Collins', 'Olwouch', '+254714590876', '', '', 'active', '2026-01-21 15:29:26', '2025-10-30 09:21:16', '2026-01-21 12:29:26'),
(70, 7, 'vicky', 'vicky@gmail.com', '$2y$10$NMLVdfkXQGB6DjtsIjmmbeW2og3ACcfQI1ytrWDniPBwTvxPpo4fi', 'Accountant', 'finance', 'Natasha', 'Vicky', '0743717529', '', '', 'active', '2025-10-30 16:36:41', '2025-10-30 10:36:41', '2025-10-30 11:30:09'),
(71, 7, 'sang', 'sang2@gmail.com', '$2y$10$nipbucLzCPJGnaPkyeJOuOOn31c6JJnF3rf7XS1Fin0fJb09TNpPu', 'Accountant', 'finance', 'Sang', 'Mwaura', '+254726985087', '', '', 'active', '2026-02-06 14:05:55', '2025-10-30 11:32:21', '2026-02-06 11:05:55'),
(72, 5, 'maryanne', 'maryanne@gmail.com', '$2y$10$FbLl1akCRJsMjCGpTgAcmuliVdSKI.C3DzLYtjDRzuADrWtS2EWna', 'Pharmacist', 'pharmacy', 'Maryanne', 'Wairimu', '+254780965432', '', '', 'active', '2026-02-06 14:04:29', '2025-10-30 11:40:25', '2026-02-06 11:04:29'),
(73, 6, 'joyce', 'joyce@gmail.com', '$2y$10$bPyIYIy54as6XpG5wbYEzuoBAM.ju4dbXrCc83CeMeYxXNgncga.u', 'Lab Technician', 'laboratory', 'Joyce', 'Owenda', '+254725809643', '', '', 'active', '2025-10-31 22:09:21', '2025-10-31 16:05:59', '2025-10-31 16:09:21'),
(74, 9, 'alpha', 'alpha@gmail.com', '$2y$10$Sj4AOMniyQzfj.ooxpQ8b..RRDk1mC0KWKZsMlq61JdAenjsll.Tm', 'Patient', NULL, 'Alpha', 'Mwirigi', '0789547021', '', '', 'active', '2026-01-14 17:29:33', '2026-01-11 18:40:05', '2026-01-14 14:29:33'),
(75, 9, 'ria', 'ria@gmail.com', '$2y$10$1baFEPusCTFSV5j2iuqlHObkdzGbt5u/hvglUjmJu4UEDoEmQbsZq', 'Patient', NULL, 'Ria', 'Kumari', '0780654321', '', '', 'active', '2026-02-05 20:25:50', '2026-01-11 19:14:08', '2026-02-05 17:25:50'),
(76, 9, 'sandra', 'sandra2@gmail.com', '$2y$10$tRr/S0RK7G2tIv1VnU0ndeVqQn5WBU6hHUPjztF23RU01iB7Q9kr6', 'Patient', NULL, 'Sandra', 'Kosgei', '0725789012', '', '', 'active', '2026-02-06 13:56:22', '2026-01-13 07:30:37', '2026-02-06 10:56:22'),
(77, 9, 'phoebe', 'phoebe22@gmail.com', '$2y$10$4qLV9l4KbZ9X0BJeIAtfvuL9JgheUockNqFp2ZNZFMPIhXmwYyBau', 'Patient', NULL, 'Phoebe', 'Ndalo', '0726963098', '', '', 'active', '2026-02-05 18:15:31', '2026-01-16 07:53:28', '2026-02-05 15:15:31'),
(78, 9, '', 'valery2@gmail.com', '$2y$10$9TZjGpbD1nxDjcnxqLSOWeEqVQUxY5XypiiczWc4sBo5mDO5KcXV2', 'Patient', NULL, 'Valery', 'Odeku', '0780654312', '', '', 'active', '2026-01-17 23:04:26', '2026-01-17 20:03:42', '2026-01-17 20:04:26'),
(102, 9, 'hannah', 'hannah@gmail.com', '$2y$10$jafdp8t9/qedoM3PrLJ04ujulJUCvQHpYyazVM9NQyXcuEFyWC4WC', 'Patient', NULL, 'Hannah', 'Nyamwange', '0780954321', '', '', 'active', '2026-01-22 23:13:21', '2026-01-22 20:12:49', '2026-01-22 20:13:54'),
(103, 9, 'emmanuel', 'emmanuel32@gmail.com', '$2y$10$sWAb/wAHg9Tj5vrFClCZS.taQuZOVB88R0QLobUpnSXDRfJwdIMNa', 'Patient', NULL, 'Emmanuel', 'Maothe', '0724578908', '', '', 'active', '2026-02-05 21:29:48', '2026-02-05 18:28:20', '2026-02-05 19:27:00'),
(104, 9, 'chris', 'christopher@gmail.com', '$2y$10$d8cBvqjxJs1Gg3QkZ4a.AemZUXHBW3pJLNRi.YbZbK7/K96f4JqrW', 'Patient', NULL, 'Christopher', 'Mwasi', '0980564321', '', '', 'active', '2026-02-06 14:17:07', '2026-02-06 11:15:28', '2026-02-06 11:17:07');

-- --------------------------------------------------------

--
-- Table structure for table `visit_components`
--

CREATE TABLE `visit_components` (
  `id` int(10) UNSIGNED NOT NULL,
  `visit_id` int(10) UNSIGNED NOT NULL,
  `component_type` enum('triage','clinical_notes','lab_order','medication','diagnosis','procedure') NOT NULL,
  `component_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`component_data`)),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `visit_components`
--

INSERT INTO `visit_components` (`id`, `visit_id`, `component_type`, `component_data`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'triage', '{\"chief_complaint\":\"Complaining of extreme joint and knee pains.\",\"bp\":\"142\\/87\",\"heart_rate\":\"88\",\"temperature\":\"36\",\"oxygen_saturation\":\"94\",\"summary\":\"Triage completed: Complaining of extreme joint and knee pains....\"}', 55, '2025-10-27 18:00:22', NULL),
(2, 1, 'clinical_notes', '{\"subjective\":\"Patient has been experiencing joint pains especially around the fingers and wrists. He also has experienced severe knee pain which hinders his ability to ove freely.\",\"objective\":\"It is speculated that his uric acid is high, tests to be done so as to proceed accordingly.\",\"assessment\":\"\",\"plan\":\"\",\"summary\":\"Clinical notes updated\"}', 55, '2025-10-27 18:02:03', NULL),
(3, 1, 'clinical_notes', '{\"subjective\":\"Patient has been experiencing joint pains especially around the fingers and wrists. He also has experienced severe knee pain which hinders his ability to ove freely.\",\"objective\":\"It is speculated that his uric acid is high, tests to be done so as to proceed accordingly.\",\"assessment\":\"Proceed with uric tests so as to prescribe medications and treatment plan for the patient.\",\"plan\":\"\",\"summary\":\"Clinical notes updated\"}', 55, '2025-10-27 18:13:55', NULL),
(4, 1, 'lab_order', '{\"test_type\":\"Other Test\",\"priority\":\"stat\",\"instructions\":\"Uric acid test\",\"lab_order_id\":\"2\",\"summary\":\"Lab ordered: Other Test\"}', 55, '2025-10-27 18:18:43', NULL),
(5, 3, 'triage', '{\"chief_complaint\":\"Experiencing extreme stomach pains and diarrhea.\",\"bp\":\"128\\/65\",\"heart_rate\":\"82\",\"temperature\":\"35\",\"oxygen_saturation\":\"97\",\"summary\":\"Triage completed: Experiencing extreme stomach pains and diarrhea....\"}', 55, '2025-10-27 20:43:52', NULL),
(6, 3, 'clinical_notes', '{\"subjective\":\"Has been experiencing extreme stomach pains for the past 1 week, started diarrhea ring 2 days ago and not ceased despite taking medication.\",\"objective\":\"Suspected to have food poisoning. Tests to be done to confirm.\",\"assessment\":\"will conclude after tests.\",\"plan\":\"\",\"summary\":\"Clinical notes updated\"}', 55, '2025-10-27 20:45:32', NULL),
(7, 3, 'lab_order', '{\"test_type\":\"Other Test\",\"priority\":\"routine\",\"instructions\":\"Stool sample test to test for food poisoning.\",\"lab_order_id\":\"5\",\"summary\":\"Lab ordered: Other Test\"}', 55, '2025-10-27 20:52:21', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vital_signs`
--

CREATE TABLE `vital_signs` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `blood_pressure` varchar(20) NOT NULL,
  `heart_rate` int(5) NOT NULL,
  `temperature` decimal(4,2) NOT NULL,
  `oxygen_saturation` int(3) NOT NULL,
  `weight` decimal(5,2) NOT NULL,
  `height` decimal(5,2) NOT NULL,
  `bmi` decimal(4,2) DEFAULT NULL,
  `respiratory_rate` int(3) NOT NULL,
  `notes` text DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `recorded_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vital_signs`
--

INSERT INTO `vital_signs` (`id`, `patient_id`, `blood_pressure`, `heart_rate`, `temperature`, `oxygen_saturation`, `weight`, `height`, `bmi`, `respiratory_rate`, `notes`, `recorded_at`, `recorded_by`) VALUES
(1, 1, '120/80', 72, 36.80, 98, 68.50, 165.00, 25.20, 16, 'Routine check-up', '2025-10-30 09:31:06', NULL),
(2, 1, '118/78', 75, 36.90, 99, 68.20, 165.00, 25.10, 15, 'Follow-up visit', '2025-10-30 09:31:06', NULL),
(3, 2, '130/85', 68, 37.10, 97, 75.00, 175.00, 24.50, 14, 'Pre-operative assessment', '2025-10-30 09:31:06', NULL),
(4, 4, '125/82', 80, 37.00, 96, 70.00, 170.00, 24.20, 18, 'Headache complaint', '2025-10-30 09:31:06', NULL),
(5, 5, '140/90', 85, 36.70, 95, 62.00, 160.00, 24.20, 20, 'Hypertension monitoring', '2025-10-30 09:31:06', NULL),
(6, 6, '110/70', 65, 36.60, 99, 80.00, 180.00, 24.70, 12, 'Annual physical', '2025-10-30 09:31:06', NULL),
(7, 7, '118/76', 72, 37.20, 98, 55.00, 162.00, 21.00, 16, 'Wellness check', '2025-10-30 09:31:06', NULL),
(8, 8, '135/88', 78, 36.80, 94, 85.00, 178.00, 26.80, 17, 'GERD follow-up', '2025-10-30 09:31:06', NULL),
(9, 9, '122/79', 70, 37.00, 98, 60.00, 165.00, 22.00, 15, 'Depression monitoring', '2025-10-30 09:31:06', NULL),
(10, 10, '128/84', 82, 36.90, 97, 78.00, 172.00, 26.40, 19, 'Cholesterol check', '2025-10-30 09:31:06', NULL),
(11, 11, '121/78', 76, 37.30, 96, 65.00, 168.00, 23.00, 18, 'Bronchitis recovery', '2025-10-30 09:31:06', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vw_combined_appointment_requests`
--

CREATE TABLE `vw_combined_appointment_requests` (
  `source` varchar(20) DEFAULT NULL,
  `id` decimal(10,0) DEFAULT NULL,
  `patient_id` decimal(10,0) DEFAULT NULL,
  `doctor_id` decimal(10,0) DEFAULT NULL,
  `preferred_date` date DEFAULT NULL,
  `preferred_actual_time` varchar(10) DEFAULT NULL,
  `preferred_time_period` varchar(9) DEFAULT NULL,
  `type` mediumtext DEFAULT NULL,
  `urgency` varchar(6) DEFAULT NULL,
  `notes` mediumtext DEFAULT NULL,
  `status` varchar(9) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `rejection_reason` mediumtext DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_patient_outstanding_balances`
-- (See below for the actual view)
--
CREATE TABLE `vw_patient_outstanding_balances` (
`patient_id` int(10) unsigned
,`patient_number` varchar(20)
,`patient_name` varchar(101)
,`phone` varchar(20)
,`email` varchar(100)
,`consultation_outstanding` decimal(32,2)
,`consultation_count` bigint(21)
,`lab_outstanding` decimal(33,2)
,`lab_count` bigint(21)
,`billing_outstanding` decimal(34,2)
,`billing_count` bigint(21)
,`admission_outstanding` decimal(38,2)
,`admission_count` bigint(21)
,`pharmacy_outstanding` decimal(32,2)
,`pharmacy_count` bigint(21)
,`total_outstanding` decimal(40,2)
,`total_items` bigint(25)
,`last_service_date` varchar(19)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_patient_outstanding_details`
-- (See below for the actual view)
--
CREATE TABLE `vw_patient_outstanding_details` (
`patient_id` int(10) unsigned
,`patient_number` varchar(20)
,`patient_name` varchar(101)
,`service_type` varchar(12)
,`reference_id` decimal(10,0)
,`description` varchar(255)
,`total_amount` decimal(16,2)
,`paid_amount` decimal(13,2)
,`balance` decimal(16,2)
,`service_date` datetime
,`status` varchar(9)
);

-- --------------------------------------------------------

--
-- Table structure for table `v_combined_billing`
--

CREATE TABLE `v_combined_billing` (
  `source` varchar(8) DEFAULT NULL,
  `id` int(11) DEFAULT NULL,
  `bill_no` varchar(100) DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `total_amount` decimal(32,2) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `patient_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ward`
--

CREATE TABLE `ward` (
  `id` int(11) NOT NULL,
  `ward_number` varchar(50) NOT NULL,
  `ward_name` varchar(100) NOT NULL,
  `ward_type` enum('General','ICU','NICU','Pediatric','Maternity','Surgical','Emergency','Private','Semi-Private') NOT NULL,
  `floor_number` int(11) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `total_beds` int(11) DEFAULT 0,
  `occupied_beds` int(11) DEFAULT 0,
  `available_beds` int(11) GENERATED ALWAYS AS (`total_beds` - `occupied_beds`) STORED,
  `capacity` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `facilities` text DEFAULT NULL COMMENT 'JSON or comma-separated list of facilities',
  `status` enum('active','inactive','maintenance','closed') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ward`
--

INSERT INTO `ward` (`id`, `ward_number`, `ward_name`, `ward_type`, `floor_number`, `department`, `total_beds`, `occupied_beds`, `capacity`, `description`, `facilities`, `status`, `created_at`, `updated_at`, `created_by`) VALUES
(1, 'WARD-001', 'General Ward', 'General', 3, 'General', 3, 0, 5, 'Used for patients with no major issues that require extensive care. Patients shouldn\'t have any infectious diseases as this ward will be shared by multiple other patients.', '', 'active', '2025-10-26 04:48:39', '2026-02-07 16:24:07', 55),
(2, 'WARD-002', 'VIP Ward', 'Private', 5, 'General', 0, 1, 1, 'Used for VIP patients who have funds to facilitate the facilities of the room.', 'AC, ENSUITE BATHROOM, HEATER, WHEELCHAIR, VENTILLATOR', 'active', '2025-10-26 05:39:29', '2025-10-27 05:22:29', 55),
(3, 'WARD-004', 'Maternity ward', 'Maternity', 4, 'Maternity', 0, 1, 4, 'Used for maternity care and child birth.', '', 'active', '2025-10-27 04:05:26', '2026-01-17 11:39:51', 55),
(5, 'WARD-005', 'Pediatric ward', 'Pediatric', 4, 'Pediatrics', 0, 1, 10, 'Used for children aged 0 to 18 years', '', 'active', '2025-10-27 05:08:23', '2026-01-17 11:38:37', 55),
(6, 'WARD-006', 'cardiology ward', 'Emergency', 1, 'Cardiology', 0, 1, 4, 'Use for patients with cardiovascular issues.', '', 'active', '2025-10-30 10:12:33', '2026-01-19 22:25:33', 55);

-- --------------------------------------------------------


--
-- Structure for view `available_appointment_slots`
--
DROP TABLE IF EXISTS `available_appointment_slots`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `available_appointment_slots`  AS SELECT `asls`.`id` AS `slot_id`, `asls`.`doctor_id` AS `doctor_id`, concat(`u`.`first_name`,' ',`u`.`last_name`) AS `doctor_name`, `u`.`role` AS `role`, `sp`.`department` AS `department`, `asls`.`day_of_week` AS `day_of_week`, CASE `asls`.`day_of_week` WHEN 0 THEN 'Sunday' WHEN 1 THEN 'Monday' WHEN 2 THEN 'Tuesday' WHEN 3 THEN 'Wednesday' WHEN 4 THEN 'Thursday' WHEN 5 THEN 'Friday' WHEN 6 THEN 'Saturday' END AS `day_name`, `asls`.`start_time` AS `start_time`, `asls`.`end_time` AS `end_time`, `asls`.`slot_duration` AS `slot_duration`, `asls`.`max_appointments` AS `max_appointments`, `asls`.`is_available` AS `is_available` FROM ((`appointment_slots` `asls` join `users` `u` on(`asls`.`doctor_id` = `u`.`id`)) left join `staff_profiles` `sp` on(`u`.`id` = `sp`.`user_id`)) WHERE `asls`.`is_available` = 1 AND `u`.`status` = 'active' ORDER BY `asls`.`doctor_id` ASC, `asls`.`day_of_week` ASC, `asls`.`start_time` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_patient_outstanding_balances`
--
DROP TABLE IF EXISTS `vw_patient_outstanding_balances`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_patient_outstanding_balances`  AS SELECT `p`.`id` AS `patient_id`, `p`.`patient_id` AS `patient_number`, concat(`p`.`first_name`,' ',`p`.`last_name`) AS `patient_name`, `p`.`phone` AS `phone`, `p`.`email` AS `email`, coalesce(`consultation_data`.`total_amount`,0) AS `consultation_outstanding`, coalesce(`consultation_data`.`count`,0) AS `consultation_count`, coalesce(`lab_data`.`total_amount`,0) AS `lab_outstanding`, coalesce(`lab_data`.`count`,0) AS `lab_count`, coalesce(`billing_data`.`total_amount`,0) AS `billing_outstanding`, coalesce(`billing_data`.`count`,0) AS `billing_count`, coalesce(`admission_data`.`total_amount`,0) AS `admission_outstanding`, coalesce(`admission_data`.`count`,0) AS `admission_count`, coalesce(`pharmacy_data`.`total_amount`,0) AS `pharmacy_outstanding`, coalesce(`pharmacy_data`.`count`,0) AS `pharmacy_count`, coalesce(`consultation_data`.`total_amount`,0) + coalesce(`lab_data`.`total_amount`,0) + coalesce(`billing_data`.`total_amount`,0) + coalesce(`admission_data`.`total_amount`,0) + coalesce(`pharmacy_data`.`total_amount`,0) AS `total_outstanding`, coalesce(`consultation_data`.`count`,0) + coalesce(`lab_data`.`count`,0) + coalesce(`billing_data`.`count`,0) + coalesce(`admission_data`.`count`,0) + coalesce(`pharmacy_data`.`count`,0) AS `total_items`, greatest(coalesce(`consultation_data`.`last_date`,'1970-01-01'),coalesce(`lab_data`.`last_date`,'1970-01-01'),coalesce(`billing_data`.`last_date`,'1970-01-01'),coalesce(`admission_data`.`last_date`,'1970-01-01'),coalesce(`pharmacy_data`.`last_date`,'1970-01-01')) AS `last_service_date` FROM (((((`patients` `p` left join (select `a`.`patient_id` AS `patient_id`,sum(`a`.`consultation_fee`) AS `total_amount`,count(0) AS `count`,max(`a`.`appointment_date`) AS `last_date` from `appointments` `a` where `a`.`status` = 'completed' and `a`.`consultation_fee` > 0 and `a`.`payment_status` in ('pending','partial') group by `a`.`patient_id`) `consultation_data` on(`p`.`id` = `consultation_data`.`patient_id`)) left join (select `lo`.`patient_id` AS `patient_id`,sum(`lo`.`total_amount` - coalesce(`lo`.`paid_amount`,0)) AS `total_amount`,count(0) AS `count`,max(`lo`.`order_date`) AS `last_date` from `lab_orders` `lo` where `lo`.`total_amount` > 0 and `lo`.`payment_status` in ('unpaid','partial') and (`lo`.`paid_amount` is null or `lo`.`paid_amount` < `lo`.`total_amount`) and `lo`.`total_amount` - coalesce(`lo`.`paid_amount`,0) > 0 group by `lo`.`patient_id`) `lab_data` on(`p`.`id` = `lab_data`.`patient_id`)) left join (select `b`.`patient_id` AS `patient_id`,sum(`b`.`balance_amount`) AS `total_amount`,count(0) AS `count`,max(`b`.`bill_date`) AS `last_date` from `billing` `b` where `b`.`total_amount` > 0 and `b`.`balance_amount` > 0 and `b`.`status` in ('pending','partial') and !(`b`.`id` in (select `bi`.`bill_id` from `bill_items` `bi` where `bi`.`item_type` = 'consultation')) group by `b`.`patient_id`) `billing_data` on(`p`.`id` = `billing_data`.`patient_id`)) left join (select `ba`.`patient_id` AS `patient_id`,sum(case when `ba`.`status` = 'active' and `ba`.`actual_discharge_date` is null then (to_days(curdate()) - to_days(`ba`.`admission_date`)) * `bd`.`price_per_day` when `ba`.`status` = 'discharged' and `ba`.`actual_discharge_date` is not null and !exists(select 1 from (`billing` `b` join `bill_items` `bi` on(`b`.`id` = `bi`.`bill_id`)) where `b`.`patient_id` = `ba`.`patient_id` and `bi`.`item_type` = 'bed_charge' and cast(`bi`.`service_date` as date) = cast(`ba`.`admission_date` as date) and `b`.`status` = 'paid' limit 1) then (to_days(`ba`.`actual_discharge_date`) - to_days(`ba`.`admission_date`)) * `bd`.`price_per_day` else 0 end) AS `total_amount`,count(0) AS `count`,max(`ba`.`admission_date`) AS `last_date` from (`bed_assignments` `ba` join `beds` `bd` on(`ba`.`bed_id` = `bd`.`id`)) where (`ba`.`status` = 'active' and `ba`.`actual_discharge_date` is null or `ba`.`status` = 'discharged' and `ba`.`actual_discharge_date` is not null and !exists(select 1 from (`billing` `b` join `bill_items` `bi` on(`b`.`id` = `bi`.`bill_id`)) where `b`.`patient_id` = `ba`.`patient_id` and `bi`.`item_type` = 'bed_charge' and cast(`bi`.`service_date` as date) = cast(`ba`.`admission_date` as date) and `b`.`status` = 'paid' limit 1)) and `ba`.`payment_status` in ('pending','partial') group by `ba`.`patient_id`) `admission_data` on(`p`.`id` = `admission_data`.`patient_id`)) left join (select `pb`.`patient_id` AS `patient_id`,sum(`pb`.`balance_amount`) AS `total_amount`,count(0) AS `count`,max(`pb`.`date`) AS `last_date` from `pharmacy_billing` `pb` where `pb`.`total_amount` > 0 and `pb`.`balance_amount` > 0 and `pb`.`payment_status` in ('pending','partial') group by `pb`.`patient_id`) `pharmacy_data` on(`p`.`id` = `pharmacy_data`.`patient_id`)) HAVING `total_outstanding` > 0 ORDER BY coalesce(`consultation_data`.`total_amount`,0) + coalesce(`lab_data`.`total_amount`,0) + coalesce(`billing_data`.`total_amount`,0) + coalesce(`admission_data`.`total_amount`,0) + coalesce(`pharmacy_data`.`total_amount`,0) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_patient_outstanding_details`
--
DROP TABLE IF EXISTS `vw_patient_outstanding_details`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_patient_outstanding_details`  AS SELECT `p`.`id` AS `patient_id`, `p`.`patient_id` AS `patient_number`, concat(`p`.`first_name`,' ',`p`.`last_name`) AS `patient_name`, 'consultation' AS `service_type`, `a`.`id` AS `reference_id`, 'Consultation Fee' AS `description`, `a`.`consultation_fee` AS `total_amount`, ifnull(`a`.`consultation_fee`,0) AS `paid_amount`, `a`.`consultation_fee`- ifnull(`a`.`consultation_fee`,0) AS `balance`, `a`.`appointment_date` AS `service_date`, `a`.`payment_status` AS `status` FROM (`appointments` `a` join `patients` `p` on(`p`.`id` = `a`.`patient_id`)) WHERE `a`.`status` = 'completed' AND `a`.`consultation_fee` > 0 AND `a`.`payment_status` in ('pending','partial')union all select `p`.`id` AS `patient_id`,`p`.`patient_id` AS `patient_number`,concat(`p`.`first_name`,' ',`p`.`last_name`) AS `patient_name`,'lab' AS `service_type`,`lo`.`id` AS `reference_id`,`loi`.`test_name` AS `description`,`lo`.`total_amount` AS `total_amount`,ifnull(`lo`.`paid_amount`,0) AS `paid_amount`,`lo`.`total_amount` - ifnull(`lo`.`paid_amount`,0) AS `balance`,`lo`.`order_date` AS `service_date`,`lo`.`payment_status` AS `status` from ((`lab_orders` `lo` join `patients` `p` on(`p`.`id` = `lo`.`patient_id`)) join `lab_order_items` `loi` on(`loi`.`lab_order_id` = `lo`.`id`)) where `lo`.`total_amount` > 0 and `lo`.`payment_status` in ('unpaid','partial') and (`lo`.`paid_amount` is null or `lo`.`paid_amount` < `lo`.`total_amount`) union all select `p`.`id` AS `patient_id`,`p`.`patient_id` AS `patient_number`,concat(`p`.`first_name`,' ',`p`.`last_name`) AS `patient_name`,'billing' AS `service_type`,`b`.`id` AS `reference_id`,`b`.`bill_number` AS `description`,`b`.`balance_amount` AS `total_amount`,`b`.`total_amount` - `b`.`balance_amount` AS `paid_amount`,`b`.`balance_amount` AS `balance`,`b`.`bill_date` AS `service_date`,`b`.`status` AS `status` from (`billing` `b` join `patients` `p` on(`p`.`id` = `b`.`patient_id`)) where `b`.`balance_amount` > 0 and `b`.`status` in ('pending','partial') union all select `p`.`id` AS `patient_id`,`p`.`patient_id` AS `patient_number`,concat(`p`.`first_name`,' ',`p`.`last_name`) AS `patient_name`,'admission' AS `service_type`,`ba`.`id` AS `reference_id`,concat('Bed Charge: ',`bd`.`bed_number`) AS `description`,case when `ba`.`status` = 'active' and `ba`.`actual_discharge_date` is null then (to_days(curdate()) - to_days(`ba`.`admission_date`)) * `bd`.`price_per_day` when `ba`.`status` = 'discharged' and `ba`.`actual_discharge_date` is not null then (to_days(`ba`.`actual_discharge_date`) - to_days(`ba`.`admission_date`)) * `bd`.`price_per_day` else 0 end AS `total_amount`,0 AS `paid_amount`,case when `ba`.`status` = 'active' and `ba`.`actual_discharge_date` is null then (to_days(curdate()) - to_days(`ba`.`admission_date`)) * `bd`.`price_per_day` when `ba`.`status` = 'discharged' and `ba`.`actual_discharge_date` is not null then (to_days(`ba`.`actual_discharge_date`) - to_days(`ba`.`admission_date`)) * `bd`.`price_per_day` else 0 end AS `balance`,`ba`.`admission_date` AS `service_date`,`ba`.`payment_status` AS `status` from ((`bed_assignments` `ba` join `beds` `bd` on(`bd`.`id` = `ba`.`bed_id`)) join `patients` `p` on(`p`.`id` = `ba`.`patient_id`)) where `ba`.`payment_status` in ('pending','partial') union all select `p`.`id` AS `patient_id`,`p`.`patient_id` AS `patient_number`,concat(`p`.`first_name`,' ',`p`.`last_name`) AS `patient_name`,'pharmacy' AS `service_type`,`pb`.`id` AS `reference_id`,`pb`.`bill_no` AS `description`,`pb`.`balance_amount` AS `total_amount`,`pb`.`total_amount` - `pb`.`balance_amount` AS `paid_amount`,`pb`.`balance_amount` AS `balance`,`pb`.`date` AS `service_date`,`pb`.`payment_status` AS `status` from (`pharmacy_billing` `pb` join `patients` `p` on(`p`.`id` = `pb`.`patient_id`)) where `pb`.`balance_amount` > 0 and `pb`.`payment_status` in ('pending','partial')  ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `appointment_requests`
--
ALTER TABLE `appointment_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bed_assignments`
--
ALTER TABLE `bed_assignments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `billing`
--
ALTER TABLE `billing`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `billing_payments`
--
ALTER TABLE `billing_payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bill_items`
--
ALTER TABLE `bill_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_consultation_appointment` (`appointment`,`item_type`),
  ADD UNIQUE KEY `uniq_consultation_once` (`appointment`) COMMENT 'Only consultations have appointment_id populated';

--
-- Indexes for table `emergency_templates`
--
ALTER TABLE `emergency_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lab_orders`
--
ALTER TABLE `lab_orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lab_order_items`
--
ALTER TABLE `lab_order_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lab_tests`
--
ALTER TABLE `lab_tests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `patient_medical_details`
--
ALTER TABLE `patient_medical_details`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `patient_medications`
--
ALTER TABLE `patient_medications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `patient_visits`
--
ALTER TABLE `patient_visits`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `patient_vitals`
--
ALTER TABLE `patient_vitals`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payment_allocations`
--
ALTER TABLE `payment_allocations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pharmacy_billing`
--
ALTER TABLE `pharmacy_billing`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sos_alerts`
--
ALTER TABLE `sos_alerts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ward`
--
ALTER TABLE `ward`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=147;

--
-- AUTO_INCREMENT for table `appointment_requests`
--
ALTER TABLE `appointment_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `bed_assignments`
--
ALTER TABLE `bed_assignments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `billing`
--
ALTER TABLE `billing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=165;

--
-- AUTO_INCREMENT for table `billing_payments`
--
ALTER TABLE `billing_payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `bill_items`
--
ALTER TABLE `bill_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=163;

--
-- AUTO_INCREMENT for table `emergency_templates`
--
ALTER TABLE `emergency_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `lab_orders`
--
ALTER TABLE `lab_orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `lab_order_items`
--
ALTER TABLE `lab_order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT for table `lab_tests`
--
ALTER TABLE `lab_tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `medical_records`
--
ALTER TABLE `medical_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `patient_medical_details`
--
ALTER TABLE `patient_medical_details`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `patient_medications`
--
ALTER TABLE `patient_medications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `patient_visits`
--
ALTER TABLE `patient_visits`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `patient_vitals`
--
ALTER TABLE `patient_vitals`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `payment_allocations`
--
ALTER TABLE `payment_allocations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=127;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `pharmacy_billing`
--
ALTER TABLE `pharmacy_billing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `purchase_master`
--
ALTER TABLE `purchase_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `return_products`
--
ALTER TABLE `return_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `sos_alerts`
--
ALTER TABLE `sos_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT for table `ward`
--
ALTER TABLE `ward`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
