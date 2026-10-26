<?php
// ====================================================================
// Eanne Boarding House - Database Initializer & Migration Tool
// Connects to MySQL, sets up database, schema & 1-year operational dataset.
// ====================================================================

header('Content-Type: application/json; charset=UTF-8');

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbname = 'eanne_boarding_house_db';

try {
    // 1. Connect to MySQL Server
    $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // 2. Create Database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbname}`");

    // Optional reset / refresh flag
    $isReset = isset($_GET['reset']) || isset($_GET['refresh']);

    // 3. Create Tables
    $pdo->exec("
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
            `balance` DECIMAL(10,2) DEFAULT 0.00,
            `move_in_date` DATE NULL,
            `lease_status` ENUM('active', 'inactive') DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
    ");

    if ($isReset) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE `payments`; TRUNCATE TABLE `maintenance_tickets`; TRUNCATE TABLE `users`; TRUNCATE TABLE `rooms`; SET FOREIGN_KEY_CHECKS = 1;");
    }

    // 4. Import seed schema directly
    $schemaFile = __DIR__ . '/schema.sql';
    if (file_exists($schemaFile)) {
        $sql = file_get_contents($schemaFile);
        $pdo->exec($sql);
    }

    // 5. Gather Counts
    $counts = [
        'rooms' => (int)$pdo->query("SELECT COUNT(*) FROM `rooms`")->fetchColumn(),
        'users' => (int)$pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn(),
        'payments' => (int)$pdo->query("SELECT COUNT(*) FROM `payments`")->fetchColumn(),
        'verified_payments' => (int)$pdo->query("SELECT COUNT(*) FROM `payments` WHERE status = 'verified'")->fetchColumn(),
        'pending_payments' => (int)$pdo->query("SELECT COUNT(*) FROM `payments` WHERE status = 'pending'")->fetchColumn(),
        'rejected_payments' => (int)$pdo->query("SELECT COUNT(*) FROM `payments` WHERE status = 'rejected'")->fetchColumn(),
        'maintenance_tickets' => (int)$pdo->query("SELECT COUNT(*) FROM `maintenance_tickets`")->fetchColumn(),
    ];

    echo json_encode([
        'success' => true,
        'message' => 'Database initialized and populated successfully with 1-year operational dataset!',
        'database' => $dbname,
        'counts' => $counts
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}