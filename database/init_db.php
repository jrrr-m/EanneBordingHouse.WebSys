<?php
// ====================================================================
// Eanne Boarding House - Database Initializer & Migration Tool
// Connects to MySQL using root, creates DB & tables if not present.
// ====================================================================

header('Content-Type: application/json; charset=UTF-8');

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbname = 'eanne_boarding_house_db';

try {
    // 1. Connect to MySQL Server (without selecting database first)
    $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // 2. Create Database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbname}`");

    // 3. Create Tables
    $sqlRooms = "CREATE TABLE IF NOT EXISTS `rooms` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `unit_code` VARCHAR(20) NOT NULL UNIQUE,
        `room_type` VARCHAR(50) NOT NULL,
        `monthly_rate` DECIMAL(10,2) NOT NULL,
        `max_pax` INT NOT NULL DEFAULT 3,
        `inclusions` TEXT NOT NULL,
        `status` ENUM('available', 'occupied', 'maintenance') DEFAULT 'available',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlRooms);

    $sqlUsers = "CREATE TABLE IF NOT EXISTS `users` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlUsers);

    $sqlPayments = "CREATE TABLE IF NOT EXISTS `payments` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlPayments);

    $sqlTickets = "CREATE TABLE IF NOT EXISTS `maintenance_tickets` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sqlTickets);

    // 4. Seed Rooms if empty
    $roomCount = $pdo->query("SELECT COUNT(*) FROM `rooms`")->fetchColumn();
    if ($roomCount == 0) {
        $stmt = $pdo->prepare("INSERT INTO `rooms` (`unit_code`, `room_type`, `monthly_rate`, `max_pax`, `inclusions`, `status`) VALUES (?, ?, ?, ?, ?, ?)");
        $rooms = [
            ['BH-01', 'Boarding House Rental', 3500.00, 3, 'Individual room, kitchen area, shared comfort room. Max 3 pax limit. Bills excluded.', 'occupied'],
            ['BH-02', 'Boarding House Rental', 3500.00, 3, 'Individual room, kitchen area, shared comfort room. Max 3 pax limit. Bills excluded.', 'occupied'],
            ['BH-03', 'Boarding House Rental', 3500.00, 3, 'Individual room, kitchen area, shared comfort room. Max 3 pax limit. Bills excluded.', 'occupied'],
            ['BH-04', 'Boarding House Rental', 3500.00, 3, 'Individual room, kitchen area, shared comfort room. Max 3 pax limit. Bills excluded.', 'occupied'],
            ['PB-01', 'Private Boarding House', 8000.00, 6, 'Entire standalone private space/house, no shared common areas. Ideal for families. Bills excluded.', 'occupied'],
            ['PB-02', 'Private Boarding House', 8000.00, 6, 'Entire standalone private space/house, no shared common areas. Ideal for families. Bills excluded.', 'available'],
        ];
        foreach ($rooms as $r) {
            $stmt->execute($r);
        }
    }

    // 5. Seed Users if empty
    $userCount = $pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
    if ($userCount == 0) {
        $stmt = $pdo->prepare("INSERT INTO `users` (`username`, `password`, `role`, `full_name`, `phone`, `email`, `unit_code`, `monthly_rent`, `move_in_date`, `lease_status`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $users = [
            ['admin', 'admin12345', 'admin', 'Landlord Admin', '0917-555-EBH1', 'admin@eanneboarding.com', NULL, 0.00, NULL, 'active'],
            ['tenant', 'tenant12345', 'tenant', 'Maria Clara Santos', '0917-882-9901', 'maria.clara@gmail.com', 'BH-02', 3500.00, '2026-01-15', 'active'],
            ['carlos', 'carlos12345', 'tenant', 'Carlos Dominguez', '0917-234-5678', 'carlos.d@gmail.com', 'BH-01', 3500.00, '2026-01-10', 'active'],
            ['juan', 'juan12345', 'tenant', 'Juan Dela Cruz', '0918-345-6789', 'juan.delacruz@yahoo.com', 'BH-03', 3500.00, '2026-02-01', 'active'],
            ['elena', 'elena12345', 'tenant', 'Elena Garcia', '0919-456-7890', 'elena.garcia@outlook.com', 'BH-04', 3500.00, '2026-03-05', 'active'],
            ['eduardo', 'eduardo12345', 'tenant', 'Eduardo & Grace Reyes', '0917-889-4455', 'reyes.family@gmail.com', 'PB-01', 8000.00, '2026-02-01', 'active'],
        ];
        foreach ($users as $u) {
            $stmt->execute($u);
        }
    }

    // 6. Seed Payments if empty
    $payCount = $pdo->query("SELECT COUNT(*) FROM `payments`")->fetchColumn();
    if ($payCount == 0) {
        $stmt = $pdo->prepare("INSERT INTO `payments` (`reference_no`, `tenant_id`, `tenant_name`, `unit_code`, `amount`, `payment_method`, `proof_image`, `proof_original_name`, `payment_date`, `status`, `admin_notes`, `verified_at`, `verified_by`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $payments = [
            ['REF-992813', 2, 'Maria Clara Santos', 'BH-02', 3500.00, 'GCash', 'uploads/payments/sample_gcash_receipt.svg', 'gcash_october_rent.svg', '2026-10-02 14:15:00', 'pending', 'Submitted via Tenant Portal. Awaiting landlord check.', NULL, NULL],
            ['BPI-441029', 4, 'Juan Dela Cruz', 'BH-03', 3500.00, 'BPI Online', 'uploads/payments/sample_bpi_receipt.svg', 'bpi_online_transfer.svg', '2026-10-01 16:30:00', 'pending', 'Submitted via Tenant Portal. Awaiting confirmation.', NULL, NULL],
            ['REF-883719', 6, 'Eduardo & Grace Reyes', 'PB-01', 8000.00, 'GCash', 'uploads/payments/sample_gcash_receipt.svg', 'gcash_pvt_house_oct.svg', '2026-10-01 11:00:00', 'pending', 'Private Boarding House monthly rent submission.', NULL, NULL],
            ['CSH-1002', 3, 'Carlos Dominguez', 'BH-01', 3500.00, 'Cash', NULL, NULL, '2026-09-28 09:00:00', 'verified', 'Cash handed in person at compound caretaker office.', '2026-09-28 09:10:00', 'Landlord Admin'],
            ['MY-552190', 5, 'Elena Garcia', 'BH-04', 3500.00, 'Maya', 'uploads/payments/sample_bpi_receipt.svg', 'maya_receipt_sep.svg', '2026-09-29 18:40:00', 'verified', 'Audited and cleared via Maya QR.', '2026-09-29 19:00:00', 'Landlord Admin'],
        ];
        foreach ($payments as $p) {
            $stmt->execute($p);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Database initialized successfully!',
        'database' => $dbname,
        'tables' => ['rooms', 'users', 'payments', 'maintenance_tickets']
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
