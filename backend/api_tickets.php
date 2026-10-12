<?php
/**
 * Eanne Boarding House Rental System
 * API: Maintenance Tickets
 * Live sync with MySQL `maintenance_tickets`
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
        $tenantFilter = trim($_GET['tenant'] ?? '');

        $sql = "SELECT * FROM maintenance_tickets";
        $params = [];

        if (!empty($tenantFilter)) {
            $sql .= " WHERE tenant_name LIKE ? OR unit_code LIKE ?";
            $params[] = "%{$tenantFilter}%";
            $params[] = "%{$tenantFilter}%";
        }

        $sql .= " ORDER BY (status = 'open') DESC, (status = 'in_progress') DESC, created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rawTickets = $stmt->fetchAll();

        $tickets = [];
        $openCount = 0;
        $inProgressCount = 0;
        $resolvedCount = 0;

        foreach ($rawTickets as $t) {
            if ($t['status'] === 'open') {
                $openCount++;
            } elseif ($t['status'] === 'in_progress') {
                $inProgressCount++;
            } elseif ($t['status'] === 'resolved') {
                $resolvedCount++;
            }

            $tickets[] = [
                'id' => (int)$t['id'],
                'ticket_no' => $t['ticket_no'],
                'tenant_name' => $t['tenant_name'],
                'unit_code' => $t['unit_code'],
                'category' => $t['category'],
                'urgency' => $t['urgency'],
                'is_urgent' => strtolower($t['urgency']) === 'urgent',
                'description' => $t['description'],
                'status' => $t['status'],
                'created_at' => date('M j, Y g:i A', strtotime($t['created_at'])),
                'created_at_short' => date('M j, Y', strtotime($t['created_at'])),
                'resolved_at' => $t['resolved_at'] ? date('M j, Y g:i A', strtotime($t['resolved_at'])) : null
            ];
        }

        echo json_encode([
            'success' => true,
            'count' => count($tickets),
            'counts' => [
                'total' => count($tickets),
                'open' => $openCount,
                'in_progress' => $inProgressCount,
                'resolved' => $resolvedCount
            ],
            'tickets' => $tickets
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

} elseif ($method === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'create') {
        $tenantName = trim($_POST['tenant_name'] ?? 'Maria Clara Santos');
        $unitCode = trim($_POST['unit_code'] ?? 'BH-02');
        $category = trim($_POST['category'] ?? 'General');
        $urgency = trim($_POST['urgency'] ?? 'Normal');
        $description = trim($_POST['description'] ?? '');

        if (empty($description)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Please provide a description of the maintenance issue.']);
            exit;
        }

        $ticketNo = 'TKT-' . rand(1000, 9999);

        try {
            $stmt = $pdo->prepare("
                INSERT INTO maintenance_tickets (ticket_no, tenant_name, unit_code, category, urgency, description, status)
                VALUES (?, ?, ?, ?, ?, ?, 'open')
            ");
            $stmt->execute([$ticketNo, $tenantName, $unitCode, $category, $urgency, $description]);
            $ticketId = (int)$pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'message' => "Ticket {$ticketNo} submitted successfully.",
                'ticket' => [
                    'id' => $ticketId,
                    'ticket_no' => $ticketNo,
                    'tenant_name' => $tenantName,
                    'unit_code' => $unitCode,
                    'category' => $category,
                    'urgency' => $urgency,
                    'description' => $description,
                    'status' => 'open',
                    'created_at' => date('M j, Y g:i A'),
                    'created_at_short' => date('M j, Y')
                ]
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to log maintenance ticket: ' . $e->getMessage()]);
        }

    } elseif ($action === 'update_status') {
        $ticketId = (int)($_POST['id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? 'resolved');

        try {
            $resolvedAt = ($newStatus === 'resolved') ? date('Y-m-d H:i:s') : null;
            $stmt = $pdo->prepare("UPDATE maintenance_tickets SET status = ?, resolved_at = ? WHERE id = ?");
            $stmt->execute([$newStatus, $resolvedAt, $ticketId]);

            echo json_encode([
                'success' => true,
                'message' => "Ticket status updated to {$newStatus}."
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unknown action.']);
    }
}
