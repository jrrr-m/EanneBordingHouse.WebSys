-- =======================================================
-- Eanne Boarding House Rental System - MySQL Database Schema
-- Property Location: Soldiers Village, Pasig City
-- Total Units: 6 (4 Boarding House Rentals @ ₱3,500 + 2 Private Boarding Houses @ ₱8,000)
-- Utility Policy: Excluded and shouldered by tenants
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
  `monthly_rent` DECIMAL(10,2) DEFAULT 0.00,
  `move_in_date` DATE NULL,
  `lease_status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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

-- =======================================================
-- Initial Seed Data
-- =======================================================

-- Seed Rooms (6 Units)
INSERT INTO `rooms` (`unit_code`, `room_type`, `monthly_rate`, `max_pax`, `inclusions`, `status`)
VALUES 
  ('BH-01', 'Boarding House Rental', 3500.00, 3, 'Individual room, kitchen area, shared comfort room. Max 3 pax limit. Bills excluded.', 'occupied'),
  ('BH-02', 'Boarding House Rental', 3500.00, 3, 'Individual room, kitchen area, shared comfort room. Max 3 pax limit. Bills excluded.', 'occupied'),
  ('BH-03', 'Boarding House Rental', 3500.00, 3, 'Individual room, kitchen area, shared comfort room. Max 3 pax limit. Bills excluded.', 'occupied'),
  ('BH-04', 'Boarding House Rental', 3500.00, 3, 'Individual room, kitchen area, shared comfort room. Max 3 pax limit. Bills excluded.', 'occupied'),
  ('PB-01', 'Private Boarding House', 8000.00, 6, 'Entire standalone private space/house, no shared common areas. Ideal for families. Bills excluded.', 'occupied'),
  ('PB-02', 'Private Boarding House', 8000.00, 6, 'Entire standalone private space/house, no shared common areas. Ideal for families. Bills excluded.', 'available')
ON DUPLICATE KEY UPDATE `monthly_rate`=VALUES(`monthly_rate`);

-- Seed Users (Admin and Active Tenants)
INSERT INTO `users` (`username`, `password`, `role`, `full_name`, `phone`, `email`, `unit_code`, `monthly_rent`, `move_in_date`, `lease_status`)
VALUES
  ('admin', 'admin12345', 'admin', 'Landlord Admin', '0917-555-EBH1', 'admin@eanneboarding.com', NULL, 0.00, NULL, 'active'),
  ('tenant', 'tenant12345', 'tenant', 'Maria Clara Santos', '0917-882-9901', 'maria.clara@gmail.com', 'BH-02', 3500.00, '2026-01-15', 'active'),
  ('carlos', 'carlos12345', 'tenant', 'Carlos Dominguez', '0917-234-5678', 'carlos.d@gmail.com', 'BH-01', 3500.00, '2026-01-10', 'active'),
  ('juan', 'juan12345', 'tenant', 'Juan Dela Cruz', '0918-345-6789', 'juan.delacruz@yahoo.com', 'BH-03', 3500.00, '2026-02-01', 'active'),
  ('elena', 'elena12345', 'tenant', 'Elena Garcia', '0919-456-7890', 'elena.garcia@outlook.com', 'BH-04', 3500.00, '2026-03-05', 'active'),
  ('eduardo', 'eduardo12345', 'tenant', 'Eduardo & Grace Reyes', '0917-889-4455', 'reyes.family@gmail.com', 'PB-01', 8000.00, '2026-02-01', 'active')
ON DUPLICATE KEY UPDATE `full_name`=VALUES(`full_name`);

-- Seed Initial Payments (Pending and Verified records)
INSERT INTO `payments` (`reference_no`, `tenant_id`, `tenant_name`, `unit_code`, `amount`, `payment_method`, `proof_image`, `proof_original_name`, `payment_date`, `status`, `admin_notes`, `verified_at`, `verified_by`)
VALUES
  ('REF-992813', 2, 'Maria Clara Santos', 'BH-02', 3500.00, 'GCash', 'uploads/payments/sample_gcash_receipt.png', 'gcash_october_rent.png', '2026-10-02 14:15:00', 'pending', 'Submitted via Tenant Portal. Awaiting landlord check.', NULL, NULL),
  ('BPI-441029', 4, 'Juan Dela Cruz', 'BH-03', 3500.00, 'BPI Online', 'uploads/payments/sample_bpi_receipt.png', 'bpi_online_transfer.png', '2026-10-01 16:30:00', 'pending', 'Submitted via Tenant Portal. Awaiting confirmation.', NULL, NULL),
  ('REF-883719', 6, 'Eduardo & Grace Reyes', 'PB-01', 8000.00, 'GCash', 'uploads/payments/sample_gcash_receipt.png', 'gcash_pvt_house_oct.png', '2026-10-01 11:00:00', 'pending', 'Private Boarding House monthly rent submission.', NULL, NULL),
  ('CSH-1002', 3, 'Carlos Dominguez', 'BH-01', 3500.00, 'Cash', NULL, NULL, '2026-09-28 09:00:00', 'verified', 'Cash handed in person at compound caretaker office.', '2026-09-28 09:10:00', 'Landlord Admin'),
  ('MY-552190', 5, 'Elena Garcia', 'BH-04', 3500.00, 'Maya', 'uploads/payments/sample_bdo_receipt.png', 'maya_receipt_sep.png', '2026-09-29 18:40:00', 'verified', 'Audited and cleared via Maya QR.', '2026-09-29 19:00:00', 'Landlord Admin')
ON DUPLICATE KEY UPDATE `amount`=VALUES(`amount`);

-- Seed Maintenance Ticket Sample
INSERT INTO `maintenance_tickets` (`ticket_no`, `tenant_name`, `unit_code`, `category`, `urgency`, `description`, `status`, `resolved_at`)
VALUES
  ('TKT-4192', 'Maria Clara Santos', 'BH-02', 'Plumbing', 'Normal', 'Replaced shower head washer in 1st floor shared bathroom.', 'resolved', '2026-09-20 15:00:00')
ON DUPLICATE KEY UPDATE `status`=VALUES(`status`);
