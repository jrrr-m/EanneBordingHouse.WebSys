-- =======================================================
-- Eanne Boarding House Rental System - MySQL Database Schema
-- Property Location: Soldiers Village, Pasig City
-- Total Units: 6 (4 Boarding House Rentals @ ₱3,500 + 2 Private Boarding Houses @ ₱8,000)
-- Utility Policy: Excluded and shouldered by tenants
-- Includes 1-Year Comprehensive Operational Dataset (Oct 2025 - Oct 2026)
-- =======================================================

CREATE DATABASE IF NOT EXISTS `eanne_boarding_house_db` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `eanne_boarding_house_db`;

-- -------------------------------------------------------
-- Table: rooms
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rooms` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `unit_code` VARCHAR(20) NOT NULL UNIQUE,
  `room_type` VARCHAR(50) NOT NULL,
  `monthly_rate` DECIMAL(10,2) NOT NULL,
  `max_pax` INT NOT NULL DEFAULT 3,
  `inclusions` TEXT NOT NULL,
  `status` ENUM('available', 'occupied', 'maintenance') DEFAULT 'available',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Table: users (Admins & Tenants)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'tenant') DEFAULT 'tenant',
  `full_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `email` VARCHAR(100) NULL,
  `unit_code` VARCHAR(20) NULL,
  `previous_unit_code` VARCHAR(20) NULL,
  `monthly_rent` DECIMAL(10,2) DEFAULT 0.00,
  `balance` DECIMAL(10,2) DEFAULT 0.00,
  `move_in_date` DATE NULL,
  `lease_status` ENUM('active', 'inactive') DEFAULT 'active',
  `overdue_exemption_months` INT NOT NULL DEFAULT 0,
  `overdue_exemption_reason` VARCHAR(255) NULL,
  `overdue_exemption_granted_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Table: tenant_unit_transfers (Unit Transfer & Move Audit Trail)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tenant_unit_transfers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT NOT NULL,
  `from_unit` VARCHAR(20) NULL,
  `to_unit` VARCHAR(20) NULL,
  `transfer_type` ENUM('transfer', 'move_in', 'move_out') DEFAULT 'transfer',
  `reason` VARCHAR(255) NULL,
  `authorized_by` VARCHAR(100) DEFAULT 'Admin',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`tenant_id`),
  INDEX (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Table: payments (Uploaded Proofs of Payment)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `reference_no` VARCHAR(50) NOT NULL UNIQUE,
  `tenant_id` INT NULL,
  `tenant_name` VARCHAR(100) NOT NULL,
  `unit_code` VARCHAR(20) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_method` VARCHAR(50) NOT NULL,
  `payment_type` ENUM('rent', 'partial', 'advance') NOT NULL DEFAULT 'rent',
  `proof_image` VARCHAR(255) NULL,
  `proof_original_name` VARCHAR(255) NULL,
  `payment_date` DATETIME NOT NULL,
  `status` ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
  `admin_notes` TEXT NULL,
  `verified_at` DATETIME NULL,
  `verified_by` VARCHAR(100) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Table: maintenance_tickets
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `maintenance_tickets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_no` VARCHAR(50) NOT NULL UNIQUE,
  `tenant_name` VARCHAR(100) NOT NULL,
  `unit_code` VARCHAR(20) NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `urgency` VARCHAR(20) DEFAULT 'Normal',
  `description` TEXT NOT NULL,
  `status` ENUM('open', 'in_progress', 'resolved') DEFAULT 'open',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
-- Table: viewing_requests (Public / Tenant Viewing Appointments)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `viewing_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `user_name` VARCHAR(100) NOT NULL,
  `contact_phone` VARCHAR(50) NOT NULL,
  `unit_or_type` VARCHAR(100) NOT NULL,
  `preferred_date` DATE NOT NULL,
  `notes` TEXT NULL,
  `status` ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =======================================================
-- Initial Seed Data: Rooms Inventory (6 Units)
-- =======================================================
INSERT INTO `rooms` (`unit_code`, `room_type`, `monthly_rate`, `max_pax`, `inclusions`, `status`)
VALUES 
  ('BH-01', 'Boarding House Rental', 3500.00, 3, 'Individual room, kitchen area, shared comfort room. Max 3 pax limit. Bills excluded.', 'occupied'),
  ('BH-02', 'Boarding House Rental', 3500.00, 3, 'Individual room, kitchen area, shared comfort room. Max 3 pax limit. Bills excluded.', 'occupied'),
  ('BH-03', 'Boarding House Rental', 3500.00, 3, 'Individual room, kitchen area, shared comfort room. Max 3 pax limit. Bills excluded.', 'occupied'),
  ('BH-04', 'Boarding House Rental', 3500.00, 3, 'Individual room, kitchen area, shared comfort room. Max 3 pax limit. Bills excluded.', 'occupied'),
  ('PB-01', 'Private Boarding House', 8000.00, 6, 'Entire standalone private space/house, no shared common areas. Ideal for families. Bills excluded.', 'occupied'),
  ('PB-02', 'Private Boarding House', 8000.00, 6, 'Entire standalone private space/house, no shared common areas. Ideal for families. Bills excluded.', 'available')
ON DUPLICATE KEY UPDATE `monthly_rate`=VALUES(`monthly_rate`), `status`=VALUES(`status`);

-- =======================================================
-- Initial Seed Data: User Accounts (Admin & Tenants)
-- =======================================================
INSERT INTO `users` (`username`, `password`, `role`, `full_name`, `phone`, `email`, `unit_code`, `monthly_rent`, `move_in_date`, `lease_status`)
VALUES
  ('admin', 'admin12345', 'admin', 'Landlord Admin', '0917-555-EBH1', 'admin@eanneboarding.com', NULL, 0.00, NULL, 'active'),
  ('tenant', 'tenant12345', 'tenant', 'Maria Clara Santos', '0917-882-9901', 'maria.clara@gmail.com', 'BH-02', 3500.00, '2025-10-01', 'active'),
  ('carlos', 'carlos12345', 'tenant', 'Carlos Dominguez', '0917-234-5678', 'carlos.d@gmail.com', 'BH-01', 3500.00, '2025-10-01', 'active'),
  ('juan', 'juan12345', 'tenant', 'Juan Dela Cruz', '0918-345-6789', 'juan.delacruz@yahoo.com', 'BH-03', 3500.00, '2025-10-01', 'active'),
  ('elena', 'elena12345', 'tenant', 'Elena Garcia', '0919-456-7890', 'elena.garcia@outlook.com', 'BH-04', 3500.00, '2025-10-01', 'active'),
  ('eduardo', 'eduardo12345', 'tenant', 'Eduardo & Grace Reyes', '0917-889-4455', 'reyes.family@gmail.com', 'PB-01', 8000.00, '2025-10-01', 'active'),
  ('ricardo', 'ricardo12345', 'tenant', 'Ricardo Mendoza', '0917-991-2233', 'ricardo.mendoza@gmail.com', 'PB-02', 8000.00, '2025-10-01', 'inactive')
ON DUPLICATE KEY UPDATE `full_name`=VALUES(`full_name`), `lease_status`=VALUES(`lease_status`);

-- =======================================================
-- 1-Year Operational Payments Dataset (79 Transactions)
-- =======================================================
INSERT INTO `payments` (`reference_no`, `tenant_id`, `tenant_name`, `unit_code`, `amount`, `payment_method`, `proof_image`, `proof_original_name`, `payment_date`, `status`, `admin_notes`, `verified_at`, `verified_by`, `created_at`)
VALUES
  ('REF-992813', 2, 'Maria Clara Santos', 'BH-02', 3500.00, 'GCash', 'uploads/payments/sample_gcash_receipt.svg', 'gcash_october_rent.svg', '2026-10-02 14:15:00', 'rejected', 'Reason: Reference number does not match uploaded receipt screenshot. (Rejected by Landlord Admin on Oct 13, 2026 7:27 AM)', '2026-10-13 13:27:14', 'Landlord Admin', '2026-10-13 07:22:46'),
  ('BPI-441029', 4, 'Juan Dela Cruz', 'BH-03', 3500.00, 'BPI Online', 'uploads/payments/sample_bpi_receipt.svg', 'bpi_online_transfer.svg', '2026-10-01 16:30:00', 'rejected', 'Reason: Payment verification rejected - please re-upload valid proof. (Rejected by Landlord Admin on Oct 13, 2026 7:15 AM)', '2026-10-13 13:15:11', 'Landlord Admin', '2026-10-13 07:22:46'),
  ('REF-883719', 6, 'Eduardo & Grace Reyes', 'PB-01', 8000.00, 'GCash', 'uploads/payments/sample_gcash_receipt.svg', 'gcash_pvt_house_oct.svg', '2026-10-01 11:00:00', 'verified', 'Approved and credited by Landlord Admin on Oct 13, 2026 1:25 AM', '2026-10-13 07:25:05', 'Landlord Admin', '2026-10-13 07:22:46'),
  ('CSH-1002', 3, 'Carlos Dominguez', 'BH-01', 3500.00, 'Cash', NULL, NULL, '2026-09-28 09:00:00', 'verified', 'Cash handed in person at compound caretaker office.', '2026-09-28 09:10:00', 'Landlord Admin', '2026-10-13 07:22:46'),
  ('MY-552190', 5, 'Elena Garcia', 'BH-04', 3500.00, 'Maya', 'uploads/payments/sample_bpi_receipt.svg', 'maya_receipt_sep.svg', '2026-09-29 18:40:00', 'verified', 'Audited and cleared via Maya QR.', '2026-09-29 19:00:00', 'Landlord Admin', '2026-10-13 07:22:46'),
  ('CSH-202510-4AF59D', 3, 'Carlos Dominguez', 'BH-01', 3500.00, 'Cash', NULL, NULL, '2025-10-07 10:21:00', 'verified', 'Walk-in cash payment collected at caretaker office. Physical receipt issued.', '2025-10-07 14:27:00', 'Landlord Admin', '2025-10-07 10:21:00'),
  ('GC-202510-8271E0', 2, 'Maria Clara Santos', 'BH-02', 3500.00, 'GCash', 'uploads/payments/receipt_placeholder.svg', 'receipt_Oct_2025_maria.png', '2025-10-05 10:14:00', 'verified', 'Verified and cleared against landlord GCash account credit.', '2025-10-05 14:18:00', 'Landlord Admin', '2025-10-05 10:14:00'),
  ('BPI-202510-0296B2', 4, 'Juan Dela Cruz', 'BH-03', 3500.00, 'BPI Online', 'uploads/payments/receipt_placeholder.svg', 'receipt_Oct_2025_juan.png', '2025-10-09 10:28:00', 'verified', 'Verified and cleared against landlord BPI Online account credit.', '2025-10-09 14:36:00', 'Landlord Admin', '2025-10-09 10:28:00'),
  ('MY-202510-DCC0DD', 5, 'Elena Garcia', 'BH-04', 3500.00, 'Maya', 'uploads/payments/receipt_placeholder.svg', 'receipt_Oct_2025_elena.png', '2025-10-11 10:35:00', 'verified', 'Verified and cleared against landlord Maya account credit.', '2025-10-11 14:45:00', 'Landlord Admin', '2025-10-11 10:35:00'),
  ('BDO-202510-755296', 6, 'Eduardo & Grace Reyes', 'PB-01', 8000.00, 'BDO Online', 'uploads/payments/receipt_placeholder.svg', 'receipt_Oct_2025_reyes.png', '2025-10-13 10:42:00', 'verified', 'Verified and cleared against landlord BDO Online account credit.', '2025-10-13 14:54:00', 'Landlord Admin', '2025-10-13 10:42:00'),
  ('GC-202510-281792', 7, 'Ricardo Mendoza', 'PB-02', 8000.00, 'GCash', 'uploads/payments/receipt_placeholder.svg', 'receipt_Oct_2025_ricardo.png', '2025-10-15 10:49:00', 'verified', 'Verified and cleared against landlord GCash account credit.', '2025-10-15 14:08:00', 'Landlord Admin', '2025-10-15 10:49:00'),
  ('GC-202511-2E74CF', 3, 'Carlos Dominguez', 'BH-01', 3500.00, 'GCash', 'uploads/payments/receipt_placeholder.svg', 'receipt_Nov_2025_carlos.png', '2025-11-07 10:21:00', 'verified', 'Verified and cleared against landlord GCash account credit.', '2025-11-07 14:27:00', 'Landlord Admin', '2025-11-07 10:21:00'),
  ('GC-202511-D06D2F', 2, 'Maria Clara Santos', 'BH-02', 3500.00, 'GCash', 'uploads/payments/receipt_placeholder.svg', 'receipt_Nov_2025_maria.png', '2025-11-05 10:14:00', 'verified', 'Verified and cleared against landlord GCash account credit.', '2025-11-05 14:18:00', 'Landlord Admin', '2025-11-05 10:14:00'),
  ('BPI-202511-3CB78E', 4, 'Juan Dela Cruz', 'BH-03', 3500.00, 'BPI Online', 'uploads/payments/receipt_placeholder.svg', 'receipt_Nov_2025_juan.png', '2025-11-09 10:28:00', 'verified', 'Verified and cleared against landlord BPI Online account credit.', '2025-11-09 14:36:00', 'Landlord Admin', '2025-11-09 10:28:00'),
  ('MY-202511-48A2B2', 5, 'Elena Garcia', 'BH-04', 3500.00, 'Maya', 'uploads/payments/receipt_placeholder.svg', 'receipt_Nov_2025_elena.png', '2025-11-11 10:35:00', 'verified', 'Verified and cleared against landlord Maya account credit.', '2025-11-11 14:45:00', 'Landlord Admin', '2025-11-11 10:35:00'),
  ('GC-202511-7B8929', 6, 'Eduardo & Grace Reyes', 'PB-01', 8000.00, 'GCash', 'uploads/payments/receipt_placeholder.svg', 'receipt_Nov_2025_reyes.png', '2025-11-13 10:42:00', 'verified', 'Verified and cleared against landlord GCash account credit.', '2025-11-13 14:54:00', 'Landlord Admin', '2025-11-13 10:42:00'),
  ('BPI-202511-52DF11', 7, 'Ricardo Mendoza', 'PB-02', 8000.00, 'BPI Online', 'uploads/payments/receipt_placeholder.svg', 'receipt_Nov_2025_ricardo.png', '2025-11-15 10:49:00', 'verified', 'Verified and cleared against landlord BPI Online account credit.', '2025-11-15 14:08:00', 'Landlord Admin', '2025-11-15 10:49:00'),
  ('CSH-202512-2740E6', 3, 'Carlos Dominguez', 'BH-01', 3500.00, 'Cash', NULL, NULL, '2025-12-07 10:21:00', 'verified', 'Walk-in cash payment collected at caretaker office. Physical receipt issued.', '2025-12-07 14:27:00', 'Landlord Admin', '2025-12-07 10:21:00'),
  ('MY-202512-66894D', 2, 'Maria Clara Santos', 'BH-02', 3500.00, 'Maya', 'uploads/payments/receipt_placeholder.svg', 'receipt_Dec_2025_maria.png', '2025-12-05 10:14:00', 'verified', 'Verified and cleared against landlord Maya account credit.', '2025-12-05 14:18:00', 'Landlord Admin', '2025-12-05 10:14:00'),
  ('GC-202512-E8110D', 4, 'Juan Dela Cruz', 'BH-03', 3500.00, 'GCash', 'uploads/payments/receipt_placeholder.svg', 'receipt_Dec_2025_juan.png', '2025-12-09 10:28:00', 'verified', 'Verified and cleared against landlord GCash account credit.', '2025-12-09 14:36:00', 'Landlord Admin', '2025-12-09 10:28:00'),
  ('GC-202512-414646', 5, 'Elena Garcia', 'BH-04', 3500.00, 'GCash', 'uploads/payments/receipt_placeholder.svg', 'receipt_Dec_2025_elena.png', '2025-12-11 10:35:00', 'verified', 'Verified and cleared against landlord GCash account credit.', '2025-12-11 14:45:00', 'Landlord Admin', '2025-12-11 10:35:00'),
  ('BDO-202512-FD7946', 6, 'Eduardo & Grace Reyes', 'PB-01', 8000.00, 'BDO Online', 'uploads/payments/receipt_placeholder.svg', 'receipt_Dec_2025_reyes.png', '2025-12-13 10:42:00', 'verified', 'Verified and cleared against landlord BDO Online account credit.', '2025-12-13 14:54:00', 'Landlord Admin', '2025-12-13 10:42:00'),
  ('CSH-202512-487850', 7, 'Ricardo Mendoza', 'PB-02', 8000.00, 'Cash', NULL, NULL, '2025-12-15 10:49:00', 'verified', 'Walk-in cash payment collected at caretaker office. Physical receipt issued.', '2025-12-15 14:08:00', 'Landlord Admin', '2025-12-15 10:49:00'),
  ('BPI-202601-D511CC', 3, 'Carlos Dominguez', 'BH-01', 3500.00, 'BPI Online', 'uploads/payments/receipt_placeholder.svg', 'receipt_Jan_2026_carlos.png', '2026-01-07 10:21:00', 'verified', 'Verified and cleared against landlord BPI Online account credit.', '2026-01-07 14:27:00', 'Landlord Admin', '2026-01-07 10:21:00'),
  ('GC-202601-04FE47', 2, 'Maria Clara Santos', 'BH-02', 3500.00, 'GCash', 'uploads/payments/receipt_placeholder.svg', 'receipt_Jan_2026_maria.png', '2026-01-05 10:14:00', 'verified', 'Verified and cleared against landlord GCash account credit.', '2026-01-05 14:18:00', 'Landlord Admin', '2026-01-05 10:14:00'),
  ('BPI-202601-79A32E', 4, 'Juan Dela Cruz', 'BH-03', 3500.00, 'BPI Online', 'uploads/payments/receipt_placeholder.svg', 'receipt_Jan_2026_juan.png', '2026-01-09 10:28:00', 'verified', 'Verified and cleared against landlord BPI Online account credit.', '2026-01-09 14:36:00', 'Landlord Admin', '2026-01-09 10:28:00'),
  ('MY-202601-4E39B2', 5, 'Elena Garcia', 'BH-04', 3500.00, 'Maya', 'uploads/payments/receipt_placeholder.svg', 'receipt_Jan_2026_elena.png', '2026-01-11 10:35:00', 'verified', 'Verified and cleared against landlord Maya account credit.', '2026-01-11 14:45:00', 'Landlord Admin', '2026-01-11 10:35:00'),
  ('GC-202601-17480E', 6, 'Eduardo & Grace Reyes', 'PB-01', 8000.00, 'GCash', 'uploads/payments/receipt_placeholder.svg', 'receipt_Jan_2026_reyes.png', '2026-01-13 10:42:00', 'verified', 'Verified and cleared against landlord GCash account credit.', '2026-01-13 14:54:00', 'Landlord Admin', '2026-01-13 10:42:00'),
  ('GC-202601-659433', 7, 'Ricardo Mendoza', 'PB-02', 8000.00, 'GCash', 'uploads/payments/receipt_placeholder.svg', 'receipt_Jan_2026_ricardo.png', '2026-01-15 10:49:00', 'verified', 'Verified and cleared against landlord GCash account credit.', '2026-01-15 14:08:00', 'Landlord Admin', '2026-01-15 10:49:00'),
  ('CSH-202602-F82EA9', 3, 'Carlos Dominguez', 'BH-01', 3500.00, 'Cash', NULL, NULL, '2026-02-07 10:21:00', 'verified', 'Walk-in cash payment collected at caretaker office. Physical receipt issued.', '2026-02-07 14:27:00', 'Landlord Admin', '2026-02-07 10:21:00'),
  ('GC-202602-07847C', 2, 'Maria Clara Santos', 'BH-02', 3500.00, 'GCash', 'uploads/payments/receipt_placeholder.svg', 'receipt_Feb_2026_maria.png', '2026-02-05 10:14:00', 'verified', 'Verified and cleared against landlord GCash account credit.', '2026-02-05 14:18:00', 'Landlord Admin', '2026-02-05 10:14:00'),
  ('BPI-202602-CECDBC', 4, 'Juan Dela Cruz', 'BH-03', 3500.00, 'BPI Online', 'uploads/payments/receipt_placeholder.svg', 'receipt_Feb_2026_juan.png', '2026-02-09 10:28:00', 'verified', 'Verified and cleared against landlord BPI Online account credit.', '2026-02-09 14:36:00', 'Landlord Admin', '2026-02-09 10:28:00'),
  ('CSH-202602-88846F', 5, 'Elena Garcia', 'BH-04', 3500.00, 'Cash', NULL, NULL, '2026-02-11 10:35:00', 'verified', 'Walk-in cash payment collected at caretaker office. Physical receipt issued.', '2026-02-11 14:45:00', 'Landlord Admin', '2026-02-11 10:35:00'),
  ('BDO-202602-D52D3C', 6, 'Eduardo & Grace Reyes', 'PB-01', 8000.00, 'BDO Online', 'uploads/payments/receipt_placeholder.svg', 'receipt_Feb_2026_reyes.png', '2026-02-13 10:42:00', 'verified', 'Verified and cleared against landlord BDO Online account credit.', '2026-02-13 14:54:00', 'Landlord Admin', '2026-02-13 10:42:00'),
  ('BPI-202602-50B84F', 7, 'Ricardo Mendoza', 'PB-02', 8000.00, 'BPI Online', 'uploads/payments/receipt_placeholder.svg', 'receipt_Feb_2026_ricardo.png', '2026-02-15 10:49:00', 'verified', 'Verified and cleared against landlord BPI Online account credit.', '2026-02-15 14:08:00', 'Landlord Admin', '2026-02-15 10:49:00'),
  ('GC-202603-55E2D0', 3, 'Carlos Dominguez', 'BH-01', 3500.00, 'GCash', 'uploads/payments/receipt_placeholder.svg', 'receipt_Mar_2026_carlos.png', '2026-03-07 10:21:00', 'verified', 'Verified and cleared against landlord GCash account credit.', '2026-03-07 14:27:00', 'Landlord Admin', '2026-03-07 10:21:00'),
  ('BPI-202603-B9F041', 2, 'Maria Clara Santos', 'BH-02', 3500.00, 'BPI Online', 'uploads/payments/receipt_placeholder.svg', 'receipt_Mar_2026_maria.png', '2026-03-05 10:14:00', 'verified', 'Verified and cleared against landlord BPI Online account credit.', '2026-03-05 14:18:00', 'Landlord Admin', '2026-03-05 10:14:00'),
  ('MY-202603-1AF593', 4, 'Juan Dela Cruz', 'BH-03', 3500.00, 'Maya', 'uploads/payments/receipt_placeholder.svg', 'receipt_Mar_2026_juan.png', '2026-03-09 10:28:00', 'verified', 'Verified and cleared against landlord Maya account credit.', '2026-03-09 14:36:00', 'Landlord Admin', '2026-03-09 10:28:00'),
  ('MY-202603-62BCF8', 5, 'Elena Garcia', 'BH-04', 3500.00, 'Maya', 'uploads/payments/receipt_placeholder.svg', 'receipt_Mar_2026_elena.png', '2026-03-11 10:35:00', 'verified', 'Verified and cleared against landlord Maya account credit.', '2026-03-11 14:45:00', 'Landlord Admin', '2026-03-11 10:35:00'),
  ('GC-202603-66EC49', 6, 'Eduardo & Grace Reyes', 'PB-01', 8000.00, 'GCash', 'uploads/payments/receipt_placeholder.svg', 'receipt_Mar_2026_reyes.png', '2026-03-13 10:42:00', 'verified', 'Verified and cleared against landlord GCash account credit.', '2026-03-13 14:54:00', 'Landlord Admin', '2026-03-13 10:42:00'),
  ('CSH-202603-5D3431', 7, 'Ricardo Mendoza', 'PB-02', 8000.00, 'Cash', NULL, NULL, '2026-03-15 10:49:00', 'verified', 'Walk-in cash payment collected at caretaker office. Physical receipt issued.', '2026-03-15 14:08:00', 'Landlord Admin', '2026-03-15 10:49:00'),
  ('CSH-202604-382DBD', 3, 'Carlos Dominguez', 'BH-01', 3500.00, 'Cash', NULL, NULL, '2026-04-07 10:21:00', 'verified', 'Walk-in cash payment collected at caretaker office. Physical receipt issued.', '2026-04-07 14:27:00', 'Landlord Admin', '2026-04-07 10:21:00'),
  ('GC-202604-6A51A5', 2, 'Maria Clara Santos', 'BH-02', 3500.00, 'GCash', 'uploads/payments/receipt_placeholder.svg', 'receipt_Apr_2026_maria.png', '2026-04-05 10:14:00', 'verified', 'Verified and cleared against landlord GCash account credit.', '2026-04-05 14:18:00', 'Landlord Admin', '2026-04-05 10:14:00'),
  ('BPI-202604-D7A764', 4, 'Juan Dela Cruz', 'BH-03', 3500.00, 'BPI Online', 'uploads/payments/receipt_placeholder.svg', 'receipt_Apr_2026_juan.png', '2026-04-09 10:28:00', 'verified', 'Verified and cleared against landlord BPI Online account credit.', '2026-04-09 14:36:00', 'Landlord Admin', '2026-04-09 10:28:00'),
  ('GC-202604-D9A20B', 5, 'Elena Garcia', 'BH-04', 3500.00, 'GCash', 'uploads/payments/receipt_placeholder.svg', 'receipt_Apr_2026_elena.png', '2026-04-11 10:35:00', 'verified', 'Verified and cleared against landlord GCash account credit.', '2026-04-11 14:45:00', 'Landlord Admin', '2026-04-11 10:35:00'),
  ('BDO-202604-73C9F2', 6, 'Eduardo & Grace Reyes', 'PB-01', 8000.00, 'BDO Online', 'uploads/payments/receipt_placeholder.svg', 'receipt_Apr_2026_reyes.png', '2026-04-13 10:42:00', 'verified', 'Verified and cleared against landlord BDO Online account credit.', '2026-04-13 14:54:00', 'Landlord Admin', '2026-04-13 10:42:00'),
  ('GC-202604-D42C74', 7, 'Ricardo Mendoza', 'PB-02', 8000.00, 'GCash', 'uploads/payments/receipt_placeholder.svg', 'receipt_Apr_2026_ricardo.png', '2026-04-15 10:49:00', 'verified', 'Verified and cleared against landlord GCash account credit.', '2026-04-15 14:08:00', 'Landlord Admin', '2026-04-15 10:49:00'),
  ('CSH-202605-D3A2D8', 3, 'Carlos Dominguez', 'BH-01', 3500.00, 'Cash', NULL, NULL, '2026-05-07 10:21:00', 'verified', 'Walk-in cash payment collected at caretaker office. Physical receipt issued.', '2026-05-07 14:27:00', 'Landlord Admin', '2026-05-07 10:21:00'),
  ('MY-202605-120A50', 2, 'Maria Clara Santos', 'BH-02', 3500.00, 'Maya', 'uploads/payments/receipt_placeholder.svg', 'receipt_May_2026_maria.png', '2026-05-05 10:14:00', 'verified', 'Verified and cleared against landlord Maya account credit.', '2026-05-05 14:18:00', 'Landlord Admin', '2026-05-05 10:14:00'),
  ('GC-202605-C3A006', 4, 'Juan Dela Cruz', 'BH-03', 3500.00, 'GCash', 'uploads/payments/receipt_placeholder.svg', 'receipt_May_2026_juan.png', '2026-05-09 10:28:00', 'verified', 'Verified and cleared against landlord GCash account credit.', '2026-05-09 14:36:00', 'Landlord Admin', '2026-05-09 10:28:00'),
  ('MY-202605-FAB5CA', 5, 'Elena Garcia', 'BH-04', 3500.00, 'Maya', 'uploads/payments/receipt_placeholder.svg', 'receipt_May_2026_elena.png', '2026-05-11 10:35:00', 'verified', 'Verified and cleared against landlord Maya account credit.', '2026-05-11 14:45:00', 'Landlord Admin', '2026-05-11 10:35:00'),
  ('GC-202605-B40F42', 6, 'Eduardo & Grace Reyes', 'PB-01', 8000.00, 'GCash', 'uploads/payments/receipt_placeholder.svg', 'receipt_May_2026_reyes.png', '2026-05-13 10:42:00', 'verified', 'Verified and cleared against landlord GCash account credit.', '2026-05-13 14:54:00', 'Landlord Admin', '2026-05-13 10:42:00'),
  ('BPI-202605-CA7121', 7, 'Ricardo Mendoza', 'PB-02', 8000.00, 'BPI Online', 'uploads/payments/receipt_placeholder.svg', 'receipt_May_2026_ricardo.png', '2026-05-15 10:49:00', 'verified', 'Verified and cleared against landlord BPI Online account credit.', '2026-05-15 14:08:00', 'Landlord Admin', '2026-05-15 10:49:00'),
  ('BPI-202606-FD007D', 3, 'Carlos Dominguez', 'BH-01', 3500.00, 'BPI Online', 'uploads/payments/receipt_placeholder.svg', 'receipt_Jun_2026_carlos.png', '2026-06-07 10:21:00', 'verified', 'Verified and cleared against landlord BPI Online account credit.', '2026-06-07 14:27:00', 'Landlord Admin', '2026-06-07 10:21:00'),
  ('GC-202606-7A6A1C', 2, 'Maria Clara Santos', 'BH-02', 3500.00, 'GCash', 'uploads/payments/receipt_placeholder.svg', 'receipt_Jun_2026_maria.png', '2026-06-05 10:14:00', 'verified', 'Verified and cleared against landlord GCash account credit.', '2026-06-05 14:18:00', 'Landlord Admin', '2026-06-05 10:14:00'),
  ('BPI-202606-4556FB', 4, 'Juan Dela Cruz', 'BH-03', 3500.00, 'BPI Online', 'uploads/payments/receipt_placeholder.svg', 'receipt_Jun_2026_juan.png', '2026-06-09 10:28:00', 'verified', 'Verified and cleared against landlord BPI Online account credit.', '2026-06-09 14:36:00', 'Landlord Admin', '2026-06-09 10:28:00'),
  ('MY-202606-D1F56E', 5, 'Elena Garcia', 'BH-04', 3500.00, 'Maya', 'uploads/payments/receipt_placeholder.svg', 'receipt_Jun_2026_elena.png', '2026-06-11 10:35:00', 'verified', 'Verified and cleared against landlord Maya account credit.', '2026-06-11 14:45:00', 'Landlord Admin', '2026-06-11 10:35:00'),
  ('BDO-202606-8823A8', 6, 'Eduardo & Grace Reyes', 'PB-01', 8000.00, 'BDO Online', 'uploads/payments/receipt_placeholder.svg', 'receipt_Jun_2026_reyes.png', '2026-06-13 10:42:00', 'verified', 'Verified and cleared against landlord BDO Online account credit.', '2026-06-13 14:54:00', 'Landlord Admin', '2026-06-13 10:42:00'),
  ('CSH-202606-9EABB3', 7, 'Ricardo Mendoza', 'PB-02', 8000.00, 'Cash', NULL, NULL, '2026-06-15 10:49:00', 'verified', 'Walk-in cash payment collected at caretaker office. Physical receipt issued.', '2026-06-15 14:08:00', 'Landlord Admin', '2026-06-15 10:49:00'),
  ('CSH-202607-DB9C55', 3, 'Carlos Dominguez', 'BH-01', 3500.00, 'Cash', NULL, NULL, '2026-07-07 10:21:00', 'verified', 'Walk-in cash payment collected at caretaker office. Physical receipt issued.', '2026-07-07 14:27:00', 'Landlord Admin', '2026-07-07 10:21:00'),
  ('GC-202607-CED3CF', 2, 'Maria Clara Santos', 'BH-02', 3500.00, 'GCash', 'uploads/payments/receipt_placeholder.svg', 'receipt_Jul_2026_maria.png', '2026-07-05 10:14:00', 'verified', 'Verified and cleared against landlord GCash account credit.', '2026-07-05 14:18:00', 'Landlord Admin', '2026-07-05 10:14:00'),
  ('BPI-202607-41439F', 4, 'Juan Dela Cruz', 'BH-03', 3500.00, 'BPI Online', 'uploads/payments/receipt_placeholder.svg', 'receipt_Jul_2026_juan.png', '2026-07-09 10:28:00', 'verified', 'Verified and cleared against landlord BPI Online account credit.', '2026-07-09 14:36:00', 'Landlord Admin', '2026-07-09 10:28:00'),
  ('CSH-202607-2447C4', 5, 'Elena Garcia', 'BH-04', 3500.00, 'Cash', NULL, NULL, '2026-07-11 10:35:00', 'verified', 'Walk-in cash payment collected at caretaker office. Physical receipt issued.', '2026-07-11 14:45:00', 'Landlord Admin', '2026-07-11 10:35:00'),
  ('GC-202607-46A712', 6, 'Eduardo & Grace Reyes', 'PB-01', 8000.00, 'GCash', 'uploads/payments/receipt_placeholder.svg', 'receipt_Jul_2026_reyes.png', '2026-07-13 10:42:00', 'verified', 'Verified and cleared against landlord GCash account credit.', '2026-07-13 14:54:00', 'Landlord Admin', '2026-07-13 10:42:00'),
  ('GC-202608-21FCA9', 3, 'Carlos Dominguez', 'BH-01', 3500.00, 'GCash', 'uploads/payments/receipt_placeholder.svg', 'receipt_Aug_2026_carlos.png', '2026-08-07 10:21:00', 'verified', 'Verified and cleared against landlord GCash account credit.', '2026-08-07 14:27:00', 'Landlord Admin', '2026-08-07 10:21:00'),
  ('MY-202608-6E3FBF', 2, 'Maria Clara Santos', 'BH-02', 3500.00, 'Maya', 'uploads/payments/receipt_placeholder.svg', 'receipt_Aug_2026_maria.png', '2026-08-05 10:14:00', 'verified', 'Verified and cleared against landlord Maya account credit.', '2026-08-05 14:18:00', 'Landlord Admin', '2026-08-05 10:14:00'),
  ('GC-202608-42EFF7', 4, 'Juan Dela Cruz', 'BH-03', 3500.00, 'GCash', 'uploads/payments/receipt_placeholder.svg', 'receipt_Aug_2026_juan.png', '2026-08-09 10:28:00', 'verified', 'Verified and cleared against landlord GCash account credit.', '2026-08-09 14:36:00', 'Landlord Admin', '2026-08-09 10:28:00'),
  ('MY-202608-2474EC', 5, 'Elena Garcia', 'BH-04', 3500.00, 'Maya', 'uploads/payments/receipt_placeholder.svg', 'receipt_Aug_2026_elena.png', '2026-08-11 10:35:00', 'verified', 'Verified and cleared against landlord Maya account credit.', '2026-08-11 14:45:00', 'Landlord Admin', '2026-08-11 10:35:00'),
  ('BDO-202608-8502B8', 6, 'Eduardo & Grace Reyes', 'PB-01', 8000.00, 'BDO Online', 'uploads/payments/receipt_placeholder.svg', 'receipt_Aug_2026_reyes.png', '2026-08-13 10:42:00', 'verified', 'Verified and cleared against landlord BDO Online account credit.', '2026-08-13 14:54:00', 'Landlord Admin', '2026-08-13 10:42:00'),
  ('CSH-202609-BB0DCC', 3, 'Carlos Dominguez', 'BH-01', 3500.00, 'Cash', NULL, NULL, '2026-09-07 10:21:00', 'verified', 'Walk-in cash payment collected at caretaker office. Physical receipt issued.', '2026-09-07 14:27:00', 'Landlord Admin', '2026-09-07 10:21:00'),
  ('GC-202609-CD4936', 2, 'Maria Clara Santos', 'BH-02', 3500.00, 'GCash', 'uploads/payments/receipt_placeholder.svg', 'receipt_Sep_2026_maria.png', '2026-09-05 10:14:00', 'verified', 'Verified and cleared against landlord GCash account credit.', '2026-09-05 14:18:00', 'Landlord Admin', '2026-09-05 10:14:00'),
  ('BPI-202609-793AC7', 4, 'Juan Dela Cruz', 'BH-03', 3500.00, 'BPI Online', 'uploads/payments/receipt_placeholder.svg', 'receipt_Sep_2026_juan.png', '2026-09-09 10:28:00', 'verified', 'Verified and cleared against landlord BPI Online account credit.', '2026-09-09 14:36:00', 'Landlord Admin', '2026-09-09 10:28:00'),
  ('MY-202609-F4790F', 5, 'Elena Garcia', 'BH-04', 3500.00, 'Maya', 'uploads/payments/receipt_placeholder.svg', 'receipt_Sep_2026_elena.png', '2026-09-11 10:35:00', 'verified', 'Verified and cleared against landlord Maya account credit.', '2026-09-11 14:45:00', 'Landlord Admin', '2026-09-11 10:35:00'),
  ('GC-202609-C65E04', 6, 'Eduardo & Grace Reyes', 'PB-01', 8000.00, 'GCash', 'uploads/payments/receipt_placeholder.svg', 'receipt_Sep_2026_reyes.png', '2026-09-13 10:42:00', 'verified', 'Verified and cleared against landlord GCash account credit.', '2026-09-13 14:54:00', 'Landlord Admin', '2026-09-13 10:42:00'),
  ('GC-202602-REJ812', 2, 'Maria Clara Santos', 'BH-02', 3500.00, 'GCash', 'uploads/payments/receipt_placeholder.svg', 'blurry_screenshot_feb2.png', '2026-02-02 09:30:00', 'rejected', 'Reason: Unclear or blurry proof of payment. Transaction reference number and amount are unreadable. (Rejected by Landlord Admin on Feb 2, 2026 11:15 AM)', NULL, NULL, '2026-02-02 09:30:00'),
  ('BPI-202606-REJ409', 4, 'Juan Dela Cruz', 'BH-03', 3500.00, 'BPI Online', 'uploads/payments/receipt_placeholder.svg', 'bpi_wrong_ref_jun.png', '2026-06-03 14:10:00', 'rejected', 'Reason: Reference / Transaction number does not match the uploaded proof of payment. (Rejected by Landlord Admin on Jun 3, 2026 3:45 PM)', NULL, NULL, '2026-06-03 14:10:00'),
  ('REF-994201', 2, 'Maria Clara Santos', 'BH-02', 3500.00, 'GCash', 'uploads/payments/receipt_placeholder.svg', 'gcash_october_clear_receipt.png', '2026-10-13 08:30:00', 'pending', 'Resubmitted clear receipt after reference mismatch inquiry. Awaiting review.', NULL, NULL, '2026-10-13 08:30:00'),
  ('BPI-442195', 4, 'Juan Dela Cruz', 'BH-03', 3500.00, 'BPI Online', 'uploads/payments/receipt_placeholder.svg', 'bpi_october_fund_transfer.png', '2026-10-12 16:45:00', 'pending', 'Submitted via Tenant Portal. Online transfer confirmation attached.', NULL, NULL, '2026-10-12 16:45:00'),
  ('MY-559102', 5, 'Elena Garcia', 'BH-04', 3500.00, 'Maya', 'uploads/payments/receipt_placeholder.svg', 'maya_october_payment.png', '2026-10-13 09:15:00', 'pending', 'Submitted via Maya QR code. Awaiting verification.', NULL, NULL, '2026-10-13 09:15:00')
ON DUPLICATE KEY UPDATE 
  `amount`=VALUES(`amount`),
  `status`=VALUES(`status`),
  `admin_notes`=VALUES(`admin_notes`),
  `verified_at`=VALUES(`verified_at`);

-- =======================================================
-- 1-Year Operational Maintenance Tickets Dataset (13 Tickets)
-- =======================================================
INSERT INTO `maintenance_tickets` (`ticket_no`, `tenant_name`, `unit_code`, `category`, `urgency`, `description`, `status`, `created_at`, `resolved_at`)
VALUES
  ('TKT-2025-1102', 'Carlos Dominguez', 'BH-01', 'Plumbing', 'Normal', 'Replaced main PVC drain trap under shared kitchen sink.', 'resolved', '2025-11-02 08:30:00', '2025-11-03 14:00:00'),
  ('TKT-2025-1215', 'Eduardo & Grace Reyes', 'PB-01', 'Electrical', 'Normal', 'Installed new weatherproof outdoor LED floodlight near private entryway.', 'resolved', '2025-12-15 17:00:00', '2025-12-16 11:30:00'),
  ('TKT-2026-0120', 'Juan Dela Cruz', 'BH-03', 'Carpentry', 'Normal', 'Replaced entrance door knob cylinder and adjusted strike plate for smooth closing.', 'resolved', '2026-01-20 10:15:00', '2026-01-20 16:45:00'),
  ('TKT-2026-0214', 'Ricardo Mendoza', 'PB-02', 'Plumbing', 'Normal', 'Cleared kitchen grease trap and replaced sink flexi-hose.', 'resolved', '2026-02-14 09:00:00', '2026-02-14 15:20:00'),
  ('TKT-2026-0322', 'Elena Garcia', 'BH-04', 'Electrical', 'Normal', 'Replaced fluorescent ballast with energy-efficient T8 LED tube in 2nd floor hallway.', 'resolved', '2026-03-22 13:40:00', '2026-03-23 10:00:00'),
  ('TKT-2026-0418', 'Maria Clara Santos', 'BH-02', 'Plumbing', 'Normal', 'Adjusted shared comfort room bidet spray pressure and replaced washer ring.', 'resolved', '2026-04-18 11:20:00', '2026-04-18 17:00:00'),
  ('TKT-2026-0510', 'Carlos Dominguez', 'BH-01', 'General Maintenance', 'Low', 'Quarterly roof gutter clearing and downspout inspection before rainy season.', 'resolved', '2026-05-10 08:00:00', '2026-05-10 16:30:00'),
  ('TKT-2026-0628', 'Ricardo Mendoza', 'PB-02', 'General Maintenance', 'Normal', 'Pre-move-out unit turnover inspection and touch-up repainting in living area.', 'resolved', '2026-06-28 09:30:00', '2026-06-29 17:00:00'),
  ('TKT-2026-0725', 'Elena Garcia', 'BH-04', 'Electrical', 'High', 'Inspected sub-meter breaker switch; tightened terminal lugs and validated meter calibration.', 'resolved', '2026-07-25 15:10:00', '2026-07-25 18:30:00'),
  ('TKT-2026-0814', 'Carlos Dominguez', 'BH-01', 'Carpentry', 'Normal', 'Fixed window screen frame and lubricated aluminum sliding track.', 'resolved', '2026-08-14 10:00:00', '2026-08-15 11:00:00'),
  ('TKT-2026-0920', 'Maria Clara Santos', 'BH-02', 'Plumbing', 'Normal', 'Replaced shower head washer in 1st floor shared bathroom.', 'resolved', '2026-09-20 09:45:00', '2026-09-20 15:00:00'),
  ('TKT-2026-1008', 'Elena Garcia', 'BH-04', 'Electrical', 'Normal', 'Hallway ceiling light flickering intermittently during evening hours.', 'in_progress', '2026-10-08 19:20:00', NULL),
  ('TKT-2026-1011', 'Maria Clara Santos', 'BH-02', 'Plumbing', 'Normal', 'Kitchen sink faucet swivel neck has small water drip when turned on high.', 'open', '2026-10-11 11:15:00', NULL)
ON DUPLICATE KEY UPDATE 
  `status`=VALUES(`status`),
  `description`=VALUES(`description`),
  `resolved_at`=VALUES(`resolved_at`);
