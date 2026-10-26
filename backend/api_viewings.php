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
        `status` ENUM('pending', 'confirmed', 'declined', 'cancelled', 'completed') DEFAULT 'pending',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'create') {
        // 1. Spam protection: Honeypot check (hidden field 'website' or 'hp_check')
        $honeypot = trim($_POST['website'] ?? $_POST['hp_check'] ?? '');
        if (!empty($honeypot)) {
            // Silently discard or pretend success to spambots
            echo json_encode([
                'success' => true,
                'message' => 'Viewing schedule request submitted successfully! Caretaker will contact you via SMS.'
            ]);
            exit;
        }

        // 2. Rate limiting check (e.g., maximum 5 submissions per 10 minutes per IP/session)
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $now = time();
        if (!isset($_SESSION['viewing_submits'])) {
            $_SESSION['viewing_submits'] = [];
        }
        // Filter out timestamps older than 600s
        $_SESSION['viewing_submits'] = array_filter($_SESSION['viewing_submits'], function ($ts) use ($now) {
            return ($now - $ts) < 600;
        });
        if (count($_SESSION['viewing_submits']) >= 5) {
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'error' => 'Too many viewing requests submitted. Please wait a few minutes before trying again or call our hotline.'
            ]);
            exit;
        }

        $userName = trim($_POST['user_name'] ?? $_POST['full_name'] ?? '');
        $phone = trim($_POST['contact_phone'] ?? $_POST['phone'] ?? $_POST['mobile'] ?? '');
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
            echo json_encode(['success' => false, 'error' => 'Your Full Name is required.']);
            exit;
        }

        if (empty($phone)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Mobile number is required for SMS confirmation.']);
            exit;
        }

        // Philippine mobile number format validation:
        // Accepts: 09XXXXXXXXX, 09XX-XXX-XXXX, +639XXXXXXXXX, 09XX XXX XXXX
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($cleanPhone, '639') && strlen($cleanPhone) === 12) {
            $cleanPhone = '0' . substr($cleanPhone, 2);
        }
        if (!preg_match('/^09[0-9]{9}$/', $cleanPhone)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Please provide a valid Philippine mobile number (e.g. 0917-555-1234 or 09XXXXXXXXX).'
            ]);
            exit;
        }
        // Format cleanly as 09XX-XXX-XXXX for standardized SMS dialing
        $formattedPhone = substr($cleanPhone, 0, 4) . '-' . substr($cleanPhone, 4, 3) . '-' . substr($cleanPhone, 7, 4);

        if (empty($preferredDate)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Preferred viewing date is required.']);
            exit;
        }

        // Validate date is not in the past
        $today = date('Y-m-d');
        if ($preferredDate < $today) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Preferred viewing date cannot be in the past. Please select today or a future date.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO viewing_requests (user_id, user_name, contact_phone, unit_or_type, preferred_date, notes, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$userId, $userName, $formattedPhone, $unitOrType, $preferredDate, $notes]);

            $_SESSION['viewing_submits'][] = $now;

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
        // Restricted to Admin
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unauthorized: Admin privileges required to update viewing status.']);
            exit;
        }

        $reqId = (int)($_POST['id'] ?? $_POST['viewing_id'] ?? 0);
        $status = strtolower(trim($_POST['status'] ?? 'confirmed'));
        if ($status === 'cancelled') $status = 'declined';

        if (!in_array($status, ['pending', 'confirmed', 'declined', 'completed'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid status. Permitted: pending, confirmed, declined, completed.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE viewing_requests SET status = ? WHERE id = ?");
            $stmt->execute([$status, $reqId]);
            echo json_encode(['success' => true, 'message' => "Viewing request updated to " . ucfirst($status) . "."]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
} elseif ($method === 'GET') {
    // Only Admin can retrieve all viewing requests
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Unauthorized: Admin privileges required to view requests list.']);
        exit;
    }
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
