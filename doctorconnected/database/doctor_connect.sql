-- ==============================================================
-- DOCTOR CONNECT: Token-Based Online Doctor Consultation Platform
-- Complete Database Schema & Seed Data
-- Compatible with MySQL 5.7+ / MySQL 8.0+ / MariaDB / XAMPP
-- ==============================================================

CREATE DATABASE IF NOT EXISTS `doctor_connect` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `doctor_connect`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `queue_logs`;
DROP TABLE IF EXISTS `appointments`;
DROP TABLE IF EXISTS `daily_token_sequences`;
DROP TABLE IF EXISTS `doctor_schedules`;
DROP TABLE IF EXISTS `doctors`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- Table: users
-- Stores Patients, Doctors, and Administrators
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('patient', 'doctor', 'admin') NOT NULL DEFAULT 'patient',
  `phone` VARCHAR(30) NULL,
  `gender` ENUM('male', 'female', 'other') DEFAULT 'male',
  `date_of_birth` DATE NULL,
  `blood_group` VARCHAR(10) NULL,
  `address` TEXT NULL,
  `profile_image` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_user_role` (`role`),
  INDEX `idx_user_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: doctors
-- Doctor professional profiles, fees, and consultation details
-- --------------------------------------------------------
CREATE TABLE `doctors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `specialization` VARCHAR(100) NOT NULL,
  `qualification` VARCHAR(100) NOT NULL,
  `experience_years` INT NOT NULL DEFAULT 5,
  `consultation_fee` DECIMAL(10,2) NOT NULL DEFAULT 500.00,
  `avg_consultation_time` INT NOT NULL DEFAULT 15 COMMENT 'in minutes',
  `bio` TEXT NULL,
  `room_number` VARCHAR(50) DEFAULT 'Room 101',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_doctors_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: doctor_schedules
-- Weekly consultation availability slots for doctors
-- --------------------------------------------------------
CREATE TABLE `doctor_schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `doctor_id` INT NOT NULL,
  `day_of_week` ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
  `start_time` TIME NOT NULL DEFAULT '09:00:00',
  `end_time` TIME NOT NULL DEFAULT '13:00:00',
  `max_tokens` INT NOT NULL DEFAULT 30,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_schedules_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_doc_day` (`doctor_id`, `day_of_week`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: daily_token_sequences
-- Race-safe atomic token counter per doctor per date
-- --------------------------------------------------------
CREATE TABLE `daily_token_sequences` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `doctor_id` INT NOT NULL,
  `sequence_date` DATE NOT NULL,
  `current_token` INT NOT NULL DEFAULT 0,
  `last_served_token` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_sequences_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_doc_date` (`doctor_id`, `sequence_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: appointments
-- Token-based patient appointments and consultation data
-- --------------------------------------------------------
CREATE TABLE `appointments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `appointment_number` VARCHAR(50) NOT NULL UNIQUE,
  `patient_id` INT NOT NULL,
  `doctor_id` INT NOT NULL,
  `appointment_date` DATE NOT NULL,
  `token_number` INT NOT NULL,
  `estimated_time` TIME NOT NULL,
  `status` ENUM('waiting', 'serving', 'completed', 'no_show', 'cancelled') NOT NULL DEFAULT 'waiting',
  `symptoms` TEXT NULL,
  `consultation_notes` TEXT NULL,
  `prescription` TEXT NULL,
  `meeting_room` VARCHAR(100) NOT NULL,
  `called_at` DATETIME NULL,
  `started_at` DATETIME NULL,
  `completed_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_appointments_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appointments_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_doc_date_token` (`doctor_id`, `appointment_date`, `token_number`),
  INDEX `idx_apt_status` (`status`),
  INDEX `idx_apt_date` (`appointment_date`),
  INDEX `idx_apt_patient` (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: queue_logs
-- Audit log of queue transitions (waiting -> serving -> completed/no_show)
-- --------------------------------------------------------
CREATE TABLE `queue_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `appointment_id` INT NOT NULL,
  `doctor_id` INT NOT NULL,
  `token_number` INT NOT NULL,
  `status_from` VARCHAR(30) NULL,
  `status_to` VARCHAR(30) NOT NULL,
  `action_by` INT NOT NULL,
  `notes` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_logs_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_logs_action_by` FOREIGN KEY (`action_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: notifications
-- In-app notifications for patients and doctors
-- --------------------------------------------------------
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('info', 'queue', 'alert', 'success') NOT NULL DEFAULT 'info',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- SEED DATA
-- Default Passwords:
-- Admin:    Admin@123   (or password)
-- Doctors:  Doctor@123  (or password)
-- Patients: Patient@123 (or password)
-- --------------------------------------------------------

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `phone`, `gender`, `date_of_birth`, `blood_group`, `address`) VALUES
-- 1. System Administrator
(1, 'Admin User', 'admin@doctorconnect.com', '$SEED$Admin@123', 'admin', '+1 555-0100', 'other', '1985-01-15', 'O+', 'Medical Center Headquarters, Suite 500'),

-- 2-5. Doctors
(2, 'Dr. Sarah Jenkins', 'sarah.jenkins@doctorconnect.com', '$SEED$Doctor@123', 'doctor', '+1 555-0201', 'female', '1980-04-12', 'A+', 'Department of Cardiology, Wing B'),
(3, 'Dr. Marcus Chen', 'marcus.chen@doctorconnect.com', '$SEED$Doctor@123', 'doctor', '+1 555-0202', 'male', '1982-08-25', 'B+', 'Neurology & Brain Health Center, Floor 4'),
(4, 'Dr. Emily Taylor', 'emily.taylor@doctorconnect.com', '$SEED$Doctor@123', 'doctor', '+1 555-0203', 'female', '1988-11-03', 'O+', 'Pediatrics Family Care, Suite 201'),
(5, 'Dr. Alexander Patel', 'alexander.patel@doctorconnect.com', '$SEED$Doctor@123', 'doctor', '+1 555-0204', 'male', '1979-06-18', 'AB+', 'Dermatology & Skin Clinic, Suite 305'),

-- 6-8. Patients
(6, 'John Doe', 'john.doe@example.com', '$SEED$Patient@123', 'patient', '+1 555-0301', 'male', '1992-03-14', 'O+', '742 Evergreen Terrace, Springfield'),
(7, 'Jane Smith', 'jane.smith@example.com', '$SEED$Patient@123', 'patient', '+1 555-0302', 'female', '1995-07-22', 'A-', '10880 Wilshire Blvd, Los Angeles, CA'),
(8, 'Robert Brown', 'robert.brown@example.com', '$SEED$Patient@123', 'patient', '+1 555-0303', 'male', '1989-12-05', 'B+', '456 Elm Street, Dallas, TX');

-- Doctors table records
INSERT INTO `doctors` (`id`, `user_id`, `specialization`, `qualification`, `experience_years`, `consultation_fee`, `avg_consultation_time`, `bio`, `room_number`, `status`) VALUES
(1, 2, 'Cardiology', 'MD, FACC, Harvard Medical School', 14, 75.00, 15, 'Board-certified cardiologist specializing in cardiovascular prevention, hypertension, lipid management, and adult heart diseases.', 'Room 101', 'active'),
(2, 3, 'Neurology', 'MD, PhD, Johns Hopkins Medicine', 12, 90.00, 20, 'Renowned neurologist expert in headache disorders, epilepsy, sleep medicine, and neurological rehabilitation.', 'Room 102', 'active'),
(3, 4, 'Pediatrics', 'MD, FAAP, Stanford University', 8, 60.00, 15, 'Compassionate pediatrician dedicated to child wellness, developmental milestones, adolescent health, and nutrition.', 'Room 103', 'active'),
(4, 5, 'Dermatology', 'MD, American Academy of Dermatology', 16, 65.00, 15, 'Specialist in clinical dermatology, skin cancer screenings, acne treatments, eczema, and laser dermatology.', 'Room 104', 'active');

-- Doctor Schedules (All 7 days configured for doctors to allow testing any day)
INSERT INTO `doctor_schedules` (`doctor_id`, `day_of_week`, `start_time`, `end_time`, `max_tokens`, `is_active`) VALUES
-- Dr. Sarah Jenkins (Cardiology) - Mon to Sun
(1, 'Monday', '09:00:00', '13:00:00', 25, 1),
(1, 'Tuesday', '09:00:00', '13:00:00', 25, 1),
(1, 'Wednesday', '09:00:00', '13:00:00', 25, 1),
(1, 'Thursday', '09:00:00', '13:00:00', 25, 1),
(1, 'Friday', '09:00:00', '13:00:00', 25, 1),
(1, 'Saturday', '10:00:00', '13:00:00', 15, 1),
(1, 'Sunday', '10:00:00', '12:00:00', 10, 1),

-- Dr. Marcus Chen (Neurology) - Mon to Sun
(2, 'Monday', '09:30:00', '13:30:00', 20, 1),
(2, 'Tuesday', '09:30:00', '13:30:00', 20, 1),
(2, 'Wednesday', '09:30:00', '13:30:00', 20, 1),
(2, 'Thursday', '09:30:00', '13:30:00', 20, 1),
(2, 'Friday', '09:30:00', '13:30:00', 20, 1),
(2, 'Saturday', '10:00:00', '13:00:00', 12, 1),
(2, 'Sunday', '10:00:00', '12:00:00', 8, 1),

-- Dr. Emily Taylor (Pediatrics) - Mon to Sun
(3, 'Monday', '08:30:00', '12:30:00', 30, 1),
(3, 'Tuesday', '08:30:00', '12:30:00', 30, 1),
(3, 'Wednesday', '08:30:00', '12:30:00', 30, 1),
(3, 'Thursday', '08:30:00', '12:30:00', 30, 1),
(3, 'Friday', '08:30:00', '12:30:00', 30, 1),
(3, 'Saturday', '09:00:00', '12:00:00', 15, 1),
(3, 'Sunday', '09:00:00', '11:00:00', 10, 1),

-- Dr. Alexander Patel (Dermatology) - Mon to Sun
(4, 'Monday', '10:00:00', '14:00:00', 25, 1),
(4, 'Tuesday', '10:00:00', '14:00:00', 25, 1),
(4, 'Wednesday', '10:00:00', '14:00:00', 25, 1),
(4, 'Thursday', '10:00:00', '14:00:00', 25, 1),
(4, 'Friday', '10:00:00', '14:00:00', 25, 1),
(4, 'Saturday', '10:00:00', '13:00:00', 15, 1),
(4, 'Sunday', '10:00:00', '12:00:00', 10, 1);

-- Seed today's token sequence and appointments for immediate live testing
-- CURRENT_DATE is evaluated dynamically by MySQL!
INSERT INTO `daily_token_sequences` (`doctor_id`, `sequence_date`, `current_token`, `last_served_token`) VALUES
(1, CURRENT_DATE(), 3, 1),
(2, CURRENT_DATE(), 2, 1);

-- Appointments for Dr. Sarah Jenkins (Doctor 1) for today
INSERT INTO `appointments` (`id`, `appointment_number`, `patient_id`, `doctor_id`, `appointment_date`, `token_number`, `estimated_time`, `status`, `symptoms`, `consultation_notes`, `prescription`, `meeting_room`, `called_at`, `started_at`, `completed_at`) VALUES
(1, 'APT-DEMO-001', 6, 1, CURRENT_DATE(), 1, '09:00:00', 'serving', 'Mild chest tightness after exercise and elevated blood pressure.', 'Patient reports intermittent palpitations.', 'Amlodipine 5mg OD, ECG recommended.', 'dc_room_live_demo_01_cardio', NOW(), NOW(), NULL),
(2, 'APT-DEMO-002', 7, 1, CURRENT_DATE(), 2, '09:15:00', 'waiting', 'Follow-up consultation for cholesterol blood report review.', NULL, NULL, 'dc_room_live_demo_02_cardio', NULL, NULL, NULL),
(3, 'APT-DEMO-003', 8, 1, CURRENT_DATE(), 3, '09:30:00', 'waiting', 'Routine cardiovascular health checkup and stress test discussion.', NULL, NULL, 'dc_room_live_demo_03_cardio', NULL, NULL, NULL);

-- Queue audit logs for Demo 1
INSERT INTO `queue_logs` (`appointment_id`, `doctor_id`, `token_number`, `status_from`, `status_to`, `action_by`, `notes`) VALUES
(1, 1, 1, NULL, 'waiting', 6, 'Token 1 generated by patient'),
(1, 1, 1, 'waiting', 'serving', 2, 'Doctor called Token 1 to consultation'),
(2, 1, 2, NULL, 'waiting', 7, 'Token 2 generated by patient'),
(3, 1, 3, NULL, 'waiting', 8, 'Token 3 generated by patient');

-- Initial Notifications
INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`) VALUES
(6, 'Your Turn Is Now!', 'Doctor Sarah Jenkins has called Token #1. Please join your video consultation.', 'queue'),
(7, 'Appointment Confirmed', 'Your appointment with Dr. Sarah Jenkins is booked for today. Your Token number is #2.', 'info'),
(8, 'Appointment Confirmed', 'Your appointment with Dr. Sarah Jenkins is booked for today. Your Token number is #3.', 'info');

