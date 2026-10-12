<?php
/**
 * Eanne Boarding House Rental System
 * API: Rooms Inventory & Status Management
 * Live sync with MySQL `rooms` and active `users`
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';
$pdo = getDbConnection();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        // Fetch rooms and match with currently active tenant assigned to that unit
        $query = "
            SELECT 
                r.id,
                r.unit_code,
                r.room_type,
                r.monthly_rate,
                r.max_pax,
                r.inclusions,
                r.status,
                r.created_at,
                u.id as tenant_id,
                u.full_name as current_tenant,
                u.phone as tenant_phone,
                u.move_in_date
            FROM rooms r
            LEFT JOIN users u ON r.unit_code = u.unit_code AND u.role = 'tenant' AND u.lease_status = 'active'
            ORDER BY r.unit_code ASC
        ";
        $stmt = $pdo->query($query);
        $rawRooms = $stmt->fetchAll();

        $rooms = [];
        $total = count($rawRooms);
        $occupied = 0;
        $available = 0;
        $maintenance = 0;
        $bhCount = 0;
        $privateCount = 0;

        foreach ($rawRooms as $r) {
            $isPrivate = stripos($r['room_type'], 'Private') !== false;
            if ($isPrivate) {
                $privateCount++;
            } else {
                $bhCount++;
            }

            if ($r['status'] === 'occupied') {
                $occupied++;
            } elseif ($r['status'] === 'available') {
                $available++;
            } elseif ($r['status'] === 'maintenance') {
                $maintenance++;
            }

            $rate = (float)$r['monthly_rate'];
            $category = $isPrivate ? 'private-house' : 'boarding-house';

            $rooms[] = [
                'id' => (int)$r['id'],
                'unit_code' => $r['unit_code'],
                'room_type' => $r['room_type'],
                'category' => $category,
                'monthly_rate' => $rate,
                'monthly_rate_formatted' => '₱' . number_format($rate, 2),
                'max_pax' => (int)$r['max_pax'],
                'inclusions' => $r['inclusions'],
                'status' => $r['status'],
                'tenant_id' => $r['tenant_id'] ? (int)$r['tenant_id'] : null,
                'current_tenant' => $r['current_tenant'] ?? null,
                'tenant_phone' => $r['tenant_phone'] ?? null,
                'move_in_date' => $r['move_in_date'] ? date('M j, Y', strtotime($r['move_in_date'])) : null,
                'is_occupied' => $r['status'] === 'occupied'
            ];
        }

        echo json_encode([
            'success' => true,
            'count' => $total,
            'counts' => [
                'total' => $total,
                'occupied' => $occupied,
                'available' => $available,
                'maintenance' => $maintenance,
                'boarding_house' => $bhCount,
                'private_house' => $privateCount,
                'occupancy_percent' => $total > 0 ? round(($occupied / $total) * 100, 1) : 0
            ],
            'rooms' => $rooms
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

} elseif ($method === 'POST') {
    $action = $_POST['action'] ?? 'update_status';

    if ($action === 'update_status') {
        $roomId = (int)($_POST['id'] ?? 0);
        $unitCode = trim($_POST['unit_code'] ?? '');
        $newStatus = trim($_POST['status'] ?? 'available');

        if (!in_array($newStatus, ['available', 'occupied', 'maintenance'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid status value. Must be available, occupied, or maintenance.']);
            exit;
        }

        try {
            if ($roomId > 0) {
                $stmt = $pdo->prepare("UPDATE rooms SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $roomId]);
            } else {
                $stmt = $pdo->prepare("UPDATE rooms SET status = ? WHERE unit_code = ?");
                $stmt->execute([$newStatus, $unitCode]);
            }

            echo json_encode(['success' => true, 'message' => "Room status updated to {$newStatus}."]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }

    } elseif ($action === 'add_room') {
        $unitCode = strtoupper(trim($_POST['unit_code'] ?? ''));
        $roomType = trim($_POST['room_type'] ?? 'Boarding House Rental');
        $rate = (float)($_POST['monthly_rate'] ?? 3500.00);
        $maxPax = (int)($_POST['max_pax'] ?? 3);
        $inclusions = trim($_POST['inclusions'] ?? 'Individual room, kitchen area, shared CR. Bills excluded.');
        $status = trim($_POST['status'] ?? 'available');

        if (empty($unitCode)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unit code is required (e.g., BH-05).']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO rooms (unit_code, room_type, monthly_rate, max_pax, inclusions, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$unitCode, $roomType, $rate, $maxPax, $inclusions, $status]);

            echo json_encode([
                'success' => true,
                'message' => "Room {$unitCode} added successfully.",
                'room_id' => (int)$pdo->lastInsertId()
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Could not add room: ' . $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unknown action.']);
    }
}
