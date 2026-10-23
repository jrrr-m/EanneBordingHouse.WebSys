<?php
/**
 * Eanne Boarding House Rental System
 * API: Schedule a Viewing / Viewing Requests
 * Handles creating viewing appointments and retrieving them for admin console
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();
require_once __DIR__ . '/db.php';
$pdo = getDbConnection();

// Ensure viewing_requests table exists
$pdo->exec("
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
");

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'create') {
        $userName = trim($_POST['user_name'] ?? $_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? $_POST['contact_phone'] ?? $_POST['mobile'] ?? '');
        $unitOrType = trim($_POST['unit_or_type'] ?? $_POST['room_type'] ?? 'General Inquiry');
        $preferredDate = trim($_POST['preferred_date'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        // If logged-in user submits, capture session details if name/phone blank
        $userId = null;
        if (isset($_SESSION['user'])) {
            $userId = (int)$_SESSION['user']['id'];
            if (empty($userName)) {
                $userName = $_SESSION['user']['full_name'];
            }
            if (empty($phone) && !empty($_SESSION['user']['phone'])) {
                $phone = $_SESSION['user']['phone'];
            }
        }

        if (empty($userName)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Full name is required.']);
            exit;
        }

        if (empty($phone)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Contact mobile number is required.']);
            exit;
        }

        if (empty($preferredDate)) {
            $preferredDate = date('Y-m-d', strtotime('+1 day'));
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO viewing_requests (user_id, user_name, contact_phone, unit_or_type, preferred_date, notes, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$userId, $userName, $phone, $unitOrType, $preferredDate, $notes]);

            echo json_encode([
                'success' => true,
                'message' => 'Viewing schedule request submitted successfully! Caretaker will contact you via SMS.',
                'request_id' => (int)$pdo->lastInsertId()
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    } elseif ($action === 'update_status') {
        // Admin update status
        $reqId = (int)($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? 'confirmed');

        if (!in_array($status, ['pending', 'confirmed', 'cancelled', 'completed'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid status.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE viewing_requests SET status = ? WHERE id = ?");
            $stmt->execute([$status, $reqId]);
            echo json_encode(['success' => true, 'message' => "Viewing request updated to {$status}."]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
} elseif ($method === 'GET') {
    try {
        $statusFilter = $_GET['status'] ?? '';
        $sql = "SELECT * FROM viewing_requests";
        $params = [];
        if (!empty($statusFilter)) {
            $sql .= " WHERE status = ?";
            $params[] = $statusFilter;
        }
        $sql .= " ORDER BY id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $requests = $stmt->fetchAll();

        $formatted = [];
        foreach ($requests as $r) {
            $formatted[] = [
                'id' => (int)$r['id'],
                'user_id' => $r['user_id'] ? (int)$r['user_id'] : null,
                'user_name' => $r['user_name'],
                'contact_phone' => $r['contact_phone'],
                'unit_or_type' => $r['unit_or_type'],
                'preferred_date' => $r['preferred_date'],
                'preferred_date_formatted' => date('M j, Y', strtotime($r['preferred_date'])),
                'notes' => $r['notes'] ?? '',
                'status' => $r['status'],
                'created_at' => $r['created_at'],
                'created_at_formatted' => date('M j, Y g:i A', strtotime($r['created_at']))
            ];
        }

        echo json_encode([
            'success' => true,
            'requests' => $formatted,
            'total' => count($formatted)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
