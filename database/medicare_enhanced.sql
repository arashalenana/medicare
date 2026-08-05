-- Family Medicare Enhanced Database Schema
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+03:00";

-- Drop existing tables if they exist (in reverse order of dependencies)
DROP TABLE IF EXISTS `lab_test_results`;
DROP TABLE IF EXISTS `lab_tests`;
DROP TABLE IF EXISTS `consultation_charges`;
DROP TABLE IF EXISTS `consultation_notes`;
DROP TABLE IF EXISTS `consultations`;
DROP TABLE IF EXISTS `prescription_items`;
DROP TABLE IF EXISTS `sale_items`;
DROP TABLE IF EXISTS `sales`;
DROP TABLE IF EXISTS `prescriptions`;
DROP TABLE IF EXISTS `medicines`;
DROP TABLE IF EXISTS `patients`;
DROP TABLE IF EXISTS `users`;

-- Users Table (Updated with Receptionist/Cashier role)
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Admin','Receptionist','Cashier','Lab Tech','Guest') NOT NULL DEFAULT 'Receptionist',
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Patients Table (renamed from Customers for clinic context)
CREATE TABLE `patients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `id_number` varchar(50) DEFAULT NULL,
  `insurance_provider` varchar(100) DEFAULT NULL,
  `insurance_number` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_number` (`id_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Medicines Table
CREATE TABLE `medicines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `batch_no` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `buying_price` decimal(10,2) NOT NULL,
  `selling_price` decimal(10,2) NOT NULL,
  `expiry_date` date NOT NULL,
  `manufacturer` varchar(100) DEFAULT NULL,
  `dosage` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Consultations Table
CREATE TABLE `consultations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `doctor_name` varchar(100) DEFAULT NULL,
  `consultation_date` datetime NOT NULL,
  `reason` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `status` enum('Pending','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  CONSTRAINT `consultations_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Consultation Notes Table
CREATE TABLE `consultation_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `consultation_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `vital_signs` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `consultation_id` (`consultation_id`),
  CONSTRAINT `consultation_notes_ibfk_1` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Consultation Charges Table
CREATE TABLE `consultation_charges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `consultation_id` int(11) NOT NULL,
  `charge_amount` decimal(10,2) NOT NULL,
  `charge_type` varchar(50) DEFAULT 'Consultation Fee',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `consultation_id` (`consultation_id`),
  CONSTRAINT `consultation_charges_ibfk_1` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Prescriptions Table
CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `consultation_id` int(11) DEFAULT NULL,
  `doctor_name` varchar(100) DEFAULT NULL,
  `prescription_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Active','Completed','Cancelled') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `consultation_id` (`consultation_id`),
  CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Prescription Items Table
CREATE TABLE `prescription_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prescription_id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `dosage` varchar(50) DEFAULT NULL,
  `frequency` varchar(100) DEFAULT NULL,
  `duration_days` int(11) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `prescription_id` (`prescription_id`),
  KEY `medicine_id` (`medicine_id`),
  CONSTRAINT `prescription_items_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prescription_items_ibfk_2` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lab Tests Table
CREATE TABLE `lab_tests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `consultation_id` int(11) DEFAULT NULL,
  `test_name` varchar(100) NOT NULL,
  `test_category` varchar(50) DEFAULT NULL,
  `test_date` datetime NOT NULL,
  `test_charge` decimal(10,2) NOT NULL,
  `status` enum('Pending','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `consultation_id` (`consultation_id`),
  CONSTRAINT `lab_tests_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lab_tests_ibfk_2` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lab Test Results Table
CREATE TABLE `lab_test_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lab_test_id` int(11) NOT NULL,
  `result_value` varchar(255) DEFAULT NULL,
  `result_unit` varchar(50) DEFAULT NULL,
  `reference_range` varchar(100) DEFAULT NULL,
  `result_status` enum('Normal','Abnormal','Critical') DEFAULT 'Normal',
  `result_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `lab_test_id` (`lab_test_id`),
  CONSTRAINT `lab_test_results_ibfk_1` FOREIGN KEY (`lab_test_id`) REFERENCES `lab_tests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sales Table (Enhanced for multiple billing categories)
CREATE TABLE `sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) DEFAULT NULL,
  `consultation_fee` decimal(10,2) DEFAULT 0.00,
  `medication_fee` decimal(10,2) DEFAULT 0.00,
  `lab_fee` decimal(10,2) DEFAULT 0.00,
  `drug_sales` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `payment_status` enum('Pending','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sale Items Table
CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `item_type` enum('Medicine','Consultation','Lab Test') NOT NULL DEFAULT 'Medicine',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `medicine_id` (`medicine_id`),
  CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Users
INSERT INTO `users` (`id`, `username`, `password`, `role`, `full_name`, `email`) VALUES
(1, 'admin', '$2b$10$D.2qnE4JSrSFMUwlAlHPHeEQERtGH4IFcbNAXIyQSn2PREOIrC0XK', 'Admin', 'Administrator', 'admin@familymedicare.com'),
(2, 'receptionist', '$2b$10$n3J06ZpUzdhp2ezsh1SzQ.FvPVSsnsK3RjDwEijAbl./Y2uP64Ciy', 'Receptionist', 'Jane Mwangi', 'receptionist@familymedicare.com'),
(3, 'cashier', '$2b$10$vc/wt3q3FO6R3HhI8yOddOlTrQB290NAcrA9hIlMPefwhAxKailpK', 'Cashier', 'Peter Kipchoge', 'cashier@familymedicare.com'),
(4, 'labtech', '$2b$10$gER0FgKxFARpycxi7L0WeuHUkxc7IcZtBRoKz0fP3H48QxTPigOh6', 'Lab Tech', 'Lab Technician', 'labtech@familymedicare.com'),
(5, 'guest', '$2b$10$BkUdHdanjq5SoGGnZQ0Na.SAg6jfevdhv5qjGJFNhGSXSvci6FT5i', 'Guest', 'Guest User', 'guest@familymedicare.com');

-- Seed Patients
INSERT INTO `patients` (`id`, `first_name`, `last_name`, `date_of_birth`, `gender`, `phone`, `email`, `id_number`) VALUES
(1, 'John', 'Kamau', '1980-05-15', 'Male', '0712345678', 'john.kamau@email.com', '12345678'),
(2, 'Mary', 'Wanjiku', '1985-08-22', 'Female', '0722334455', 'mary.wanjiku@email.com', '12345679'),
(3, 'David', 'Ochieng', '1975-03-10', 'Male', '0733445566', 'david.ochieng@email.com', '12345680'),
(4, 'Sarah', 'Achieng', '1990-11-30', 'Female', '0744556677', 'sarah.achieng@email.com', '12345681'),
(5, 'Michael', 'Njoroge', '1982-07-18', 'Male', '0755667788', 'michael.njoroge@email.com', '12345682'),
(6, 'Emily', 'Muthoni', '1988-09-25', 'Female', '0766778899', 'emily.muthoni@email.com', '12345683'),
(7, 'James', 'Kipkorir', '1978-12-05', 'Male', '0777889900', 'james.kipkorir@email.com', '12345684'),
(8, 'Jessica', 'Nyambura', '1992-02-14', 'Female', '0788990011', 'jessica.nyambura@email.com', '12345685'),
(9, 'Robert', 'Mutua', '1983-06-20', 'Male', '0799001122', 'robert.mutua@email.com', '12345686'),
(10, 'Linda', 'Khadija', '1987-04-08', 'Female', '0700112233', 'linda.khadija@email.com', '12345687'),
(11, 'William', 'Ouma', '1981-10-12', 'Male', '0711223344', 'william.ouma@email.com', '12345688'),
(12, 'Elizabeth', 'Moraa', '1986-01-28', 'Female', '0722334455', 'elizabeth.moraa@email.com', '12345689');

-- Seed Medicines
INSERT INTO `medicines` (`name`, `category`, `batch_no`, `quantity`, `buying_price`, `selling_price`, `expiry_date`, `dosage`) VALUES
('Paracetamol 500mg', 'Analgesics', 'B-101', 150, 2.00, 5.00, '2027-12-31', '500mg'),
('Amoxicillin 250mg', 'Antibiotics', 'B-102', 8, 15.00, 25.00, '2026-06-15', '250mg'),
('Ibuprofen 400mg', 'Analgesics', 'B-103', 200, 3.50, 8.00, '2027-05-20', '400mg'),
('Cetirizine 10mg', 'Antihistamines', 'B-104', 120, 5.00, 12.00, '2026-05-25', '10mg'),
('Metformin 500mg', 'Antidiabetics', 'B-105', 300, 10.00, 20.00, '2028-01-10', '500mg'),
('Amlodipine 5mg', 'Antihypertensives', 'B-106', 5, 12.00, 22.00, '2026-05-10', '5mg'),
('Omeprazole 20mg', 'Antacids', 'B-107', 180, 8.00, 18.00, '2027-09-30', '20mg'),
('Azithromycin 500mg', 'Antibiotics', 'B-108', 45, 50.00, 85.00, '2026-08-12', '500mg'),
('Salbutamol Inhaler', 'Respiratory', 'B-109', 30, 150.00, 250.00, '2027-03-15', 'Inhaler'),
('Loratadine 10mg', 'Antihistamines', 'B-110', 90, 6.00, 15.00, '2026-05-05', '10mg'),
('Ciprofloxacin 500mg', 'Antibiotics', 'B-111', 60, 25.00, 45.00, '2027-11-20', '500mg'),
('Diclofenac 50mg', 'Analgesics', 'B-112', 250, 4.00, 10.00, '2028-02-28', '50mg'),
('Losartan 50mg', 'Antihypertensives', 'B-113', 140, 15.00, 30.00, '2027-07-15', '50mg'),
('Atorvastatin 20mg', 'Statins', 'B-114', 100, 20.00, 45.00, '2027-10-05', '20mg'),
('Hydrochlorothiazide 25mg', 'Diuretics', 'B-115', 85, 5.00, 12.00, '2026-12-10', '25mg');

-- Seed Consultations
INSERT INTO `consultations` (`patient_id`, `doctor_name`, `consultation_date`, `reason`, `diagnosis`, `status`) VALUES
(1, 'Dr. James Kariuki', '2026-06-10 09:00:00', 'Routine checkup', 'Hypertension', 'Completed'),
(2, 'Dr. Sarah Kipchoge', '2026-06-11 10:30:00', 'Flu symptoms', 'Common Cold', 'Completed'),
(3, 'Dr. James Kariuki', '2026-06-12 14:00:00', 'Chest pain', 'Anxiety', 'Completed'),
(4, 'Dr. Sarah Kipchoge', '2026-06-13 11:00:00', 'Diabetes follow-up', 'Type 2 Diabetes', 'Completed'),
(5, 'Dr. James Kariuki', '2026-06-14 15:30:00', 'Allergy check', 'Seasonal Allergies', 'Pending');

-- Seed Lab Tests
INSERT INTO `lab_tests` (`patient_id`, `consultation_id`, `test_name`, `test_category`, `test_date`, `test_charge`, `status`) VALUES
(1, 1, 'Blood Pressure Test', 'Vital Signs', '2026-06-10 09:15:00', 500.00, 'Completed'),
(2, 2, 'Full Blood Count', 'Hematology', '2026-06-11 10:45:00', 1500.00, 'Completed'),
(3, 3, 'ECG', 'Cardiology', '2026-06-12 14:15:00', 2000.00, 'Completed'),
(4, 4, 'Fasting Blood Sugar', 'Biochemistry', '2026-06-13 08:00:00', 800.00, 'Completed'),
(5, 5, 'Allergy Panel', 'Immunology', '2026-06-14 16:00:00', 3000.00, 'Pending');

-- Seed Lab Test Results
INSERT INTO `lab_test_results` (`lab_test_id`, `result_value`, `result_unit`, `reference_range`, `result_status`) VALUES
(1, '140/90', 'mmHg', '<120/80', 'Abnormal'),
(2, '4.5', '10^9/L', '4.5-11.0', 'Normal'),
(3, 'Normal', 'N/A', 'Normal', 'Normal'),
(4, '120', 'mg/dL', '70-100', 'Abnormal');

-- Seed Prescriptions
INSERT INTO `prescriptions` (`patient_id`, `consultation_id`, `doctor_name`, `prescription_date`, `notes`, `status`) VALUES
(1, 1, 'Dr. James Kariuki', '2026-06-10', 'Take Amlodipine 5mg daily for blood pressure control', 'Active'),
(2, 2, 'Dr. Sarah Kipchoge', '2026-06-11', 'Take Azithromycin 500mg twice daily for 5 days', 'Active'),
(3, 3, 'Dr. James Kariuki', '2026-06-12', 'Take Paracetamol 500mg as needed for chest discomfort', 'Active'),
(4, 4, 'Dr. Sarah Kipchoge', '2026-06-13', 'Continue Metformin 500mg with meals twice daily', 'Active'),
(5, 5, 'Dr. James Kariuki', '2026-06-14', 'Take Cetirizine 10mg at night for allergies', 'Active');

-- Seed Prescription Items
INSERT INTO `prescription_items` (`prescription_id`, `medicine_id`, `quantity`, `dosage`, `frequency`, `duration_days`, `instructions`) VALUES
(1, 6, 30, '5mg', 'Once daily', 30, 'Take in the morning with water'),
(2, 8, 10, '500mg', 'Twice daily', 5, 'Take with meals'),
(3, 1, 20, '500mg', 'As needed', 7, 'Take with water when needed'),
(4, 5, 60, '500mg', 'Twice daily', 30, 'Take with breakfast and dinner'),
(5, 4, 10, '10mg', 'Once at night', 30, 'Take before bedtime');

-- Seed Sales (with breakdown)
INSERT INTO `sales` (`patient_id`, `consultation_fee`, `medication_fee`, `lab_fee`, `drug_sales`, `total_amount`, `payment_method`, `payment_status`, `user_id`) VALUES
(1, 500.00, 0.00, 500.00, 110.00, 1110.00, 'Cash', 'Completed', 2),
(2, 500.00, 0.00, 1500.00, 85.00, 2085.00, 'M-Pesa', 'Completed', 3),
(3, 500.00, 0.00, 2000.00, 5.00, 2505.00, 'Cash', 'Completed', 2),
(4, 500.00, 0.00, 800.00, 20.00, 1320.00, 'M-Pesa', 'Completed', 3),
(5, 500.00, 0.00, 3000.00, 12.00, 3512.00, 'Cash', 'Pending', 2);

-- Seed Sale Items
INSERT INTO `sale_items` (`sale_id`, `medicine_id`, `quantity`, `price`, `item_type`) VALUES
(1, 6, 1, 110.00, 'Medicine'),
(2, 8, 1, 85.00, 'Medicine'),
(3, 1, 1, 5.00, 'Medicine'),
(4, 5, 2, 20.00, 'Medicine'),
(5, 4, 1, 12.00, 'Medicine');

COMMIT;

-- ============================================================
-- Fix: Lab Tech / Guest role and login repair
-- (Safe to re-run; ensures roles are correct and accounts exist)
-- ============================================================
START TRANSACTION;

-- 1. Confirm the users.role column allows all five roles
ALTER TABLE `users`
  MODIFY COLUMN `role` ENUM('Admin','Receptionist','Cashier','Lab Tech','Guest')
  NOT NULL DEFAULT 'Receptionist';

-- 2. Remove any existing labtech/guest rows (in case roles got truncated/corrupted)
DELETE FROM `users` WHERE `username` IN ('labtech', 'guest');

-- 3. Re-insert labtech and guest with the correct, full role names
INSERT INTO `users` (`username`, `password`, `role`, `full_name`, `email`) VALUES
('labtech', '$2b$10$gER0FgKxFARpycxi7L0WeuHUkxc7IcZtBRoKz0fP3H48QxTPigOh6', 'Lab Tech', 'Lab Technician', 'labtech@familymedicare.com'),
('guest', '$2b$10$BkUdHdanjq5SoGGnZQ0Na.SAg6jfevdhv5qjGJFNhGSXSvci6FT5i', 'Guest', 'Guest User', 'guest@familymedicare.com');

-- 4. Re-affirm roles on the other seeded accounts, just in case
UPDATE `users` SET `role` = 'Admin' WHERE `username` = 'admin';
UPDATE `users` SET `role` = 'Receptionist' WHERE `username` = 'receptionist';
UPDATE `users` SET `role` = 'Cashier' WHERE `username` = 'cashier';

COMMIT;