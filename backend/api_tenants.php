<?php
/**
 * Eanne Boarding House Rental System
 * API: Tenants Roster & Management
 * Live sync with MySQL `users` and `rooms`
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
        $statusFilter = $_GET['status'] ?? ''; // 'active', 'inactive', or all

        $sql = "
            SELECT 
                u.id,
                u.username,
                u.full_name,
                u.phone,
                u.email,
                u.unit_code,
                u.monthly_rent,
                u.balance,
                u.move_in_date,
                u.lease_status,
                u.created_at,
                r.room_type,
                r.max_pax,
                r.status as room_status,
                p.reference_no as last_payment_ref,
                p.payment_date as last_payment_date,
                p.status as last_payment_status,
                p.amount as last_payment_amount
            FROM users u
            LEFT JOIN rooms r ON u.unit_code = r.unit_code
            LEFT JOIN (
                SELECT p1.*
                FROM payments p1
                INNER JOIN (
                    SELECT tenant_id, MAX(id) as max_id
                    FROM payments
                    GROUP BY tenant_id
                ) p2 ON p1.id = p2.max_id
            ) p ON u.id = p.tenant_id
            WHERE u.role = 'tenant'
        ";

        if ($statusFilter === 'active') {
            $sql .= " AND u.lease_status = 'active'";
        } elseif ($statusFilter === 'inactive') {
            $sql .= " AND u.lease_status = 'inactive'";
        }

        $sql .= " ORDER BY (u.lease_status = 'active') DESC, u.unit_code ASC";

        $stmt = $pdo->query($sql);
        $rawTenants = $stmt->fetchAll();

        $tenants = [];
        $activeCount = 0;
        $inactiveCount = 0;
        $boardingCount = 0;
        $privateCount = 0;

        foreach ($rawTenants as $t) {
            $isActive = $t['lease_status'] === 'active';
            if ($isActive) {
                $activeCount++;
            } else {
                $inactiveCount++;
            }

            $isPrivate = stripos($t['unit_code'] ?? '', 'PB') !== false || stripos($t['room_type'] ?? '', 'Private') !== false;
            if ($isPrivate) {
                $privateCount++;
            } else {
                $boardingCount++;
            }

            $rent = (float)($t['monthly_rent'] ?? 0);
            $balance = (float)($t['balance'] ?? $rent);
            $classification = $isPrivate ? 'Private House' : 'Boarding House';
            $category = ($isActive ? 'active' : 'inactive') . ' ' . ($isPrivate ? 'private' : 'boarding');

            $tenants[] = [
                'id' => (int)$t['id'],
                'username' => $t['username'],
                'password' => $t['password'] ?? '',
                'full_name' => $t['full_name'],
                'phone' => $t['phone'] ?? 'N/A',
                'email' => $t['email'] ?? 'N/A',
                'unit_code' => $t['unit_code'] ?? 'Unassigned',
                'room_type' => $t['room_type'] ?? ($isPrivate ? 'Private Boarding House' : 'Boarding House Rental'),
                'classification' => $classification,
                'category' => $category,
                'monthly_rent' => $rent,
                'monthly_rent_formatted' => '₱' . number_format($rent, 2),
                'balance' => $balance,
                'balance_formatted' => ($balance < 0 ? '-₱' . number_format(abs($balance), 2) : '₱' . number_format($balance, 2)),
                'advance_credit' => $balance < 0 ? abs($balance) : 0,
                'move_in_date' => $t['move_in_date'] ? date('M j, Y', strtotime($t['move_in_date'])) : 'Pending',
                'move_in_date_raw' => $t['move_in_date'] ?? '',
                'lease_status' => $t['lease_status'],
                'is_active' => $isActive,
                'last_payment' => $t['last_payment_ref'] ? [
                    'reference_no' => $t['last_payment_ref'],
                    'amount' => (float)$t['last_payment_amount'],
                    'date' => date('M j, Y', strtotime($t['last_payment_date'])),
                    'status' => $t['last_payment_status']
                ] : null
            ];
        }

        echo json_encode([
            'success' => true,
            'count' => count($tenants),
            'counts' => [
                'total' => count($tenants),
                'active' => $activeCount,
                'inactive' => $inactiveCount,
                'boarding' => $boardingCount,
                'private' => $privateCount
            ],
            'tenants' => $tenants
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

} elseif ($method === 'POST') {
    $action = $_POST['action'] ?? 'add_tenant';

    if ($action === 'add_tenant') {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $unitCode = strtoupper(trim($_POST['unit_code'] ?? ''));
        $monthlyRent = (float)($_POST['monthly_rent'] ?? 0);
        $moveInDate = trim($_POST['move_in_date'] ?? date('Y-m-d'));

        if (empty($fullName)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Full name is required.']);
            exit;
        }

        // Auto-generate username from full name
        $cleanName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $fullName));
        $username = substr($cleanName, 0, 15) . rand(10, 99);
        $password = 'welcome12345'; // default temporary password

        // If monthly rent was not provided, look it up from room
        if ($monthlyRent <= 0 && !empty($unitCode)) {
            $stmtR = $pdo->prepare("SELECT monthly_rate FROM rooms WHERE unit_code = ? LIMIT 1");
            $stmtR->execute([$unitCode]);
            $foundRate = $stmtR->fetchColumn();
            if ($foundRate) {
                $monthlyRent = (float)$foundRate;
            } else {
                $monthlyRent = (stripos($unitCode, 'PB') !== false) ? 8000.00 : 3500.00;
            }
        }

        try {
            $pdo->beginTransaction();

            $initialBalance = isset($_POST['balance']) ? (float)$_POST['balance'] : $monthlyRent;

            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, role, full_name, phone, email, unit_code, monthly_rent, balance, move_in_date, lease_status)
                VALUES (?, ?, 'tenant', ?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            $stmt->execute([$username, $password, $fullName, $phone, $email, $unitCode, $monthlyRent, $initialBalance, $moveInDate]);
            $tenantId = (int)$pdo->lastInsertId();

            // Set room to occupied
            if (!empty($unitCode)) {
                $stmtRoom = $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE unit_code = ?");
                $stmtRoom->execute([$unitCode]);
            }

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => "Tenant {$fullName} registered and assigned to {$unitCode}.",
                'tenant' => [
                    'id' => $tenantId,
                    'username' => $username,
                    'full_name' => $fullName,
                    'phone' => $phone,
                    'unit_code' => $unitCode,
                    'monthly_rent' => $monthlyRent,
                    'monthly_rent_formatted' => '₱' . number_format($monthlyRent, 2),
                    'move_in_date' => date('M j, Y', strtotime($moveInDate)),
                    'lease_status' => 'active'
                ]
            ]);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to register tenant: ' . $e->getMessage()]);
        }

    } elseif ($action === 'archive_tenant' || $action === 'move_out') {
        $tenantId = (int)($_POST['id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');

        try {
            $pdo->beginTransaction();

            // Retrieve current unit
            $stmt = $pdo->prepare("SELECT id, unit_code, full_name FROM users WHERE id = ? OR full_name = ? LIMIT 1");
            $stmt->execute([$tenantId, $fullName]);
            $tenant = $stmt->fetch();

            if (!$tenant) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Tenant record not found.']);
                exit;
            }

            // Mark inactive
            $stmtUp = $pdo->prepare("UPDATE users SET lease_status = 'inactive' WHERE id = ?");
            $stmtUp->execute([$tenant['id']]);

            // Free up room
            if (!empty($tenant['unit_code'])) {
                $stmtR = $pdo->prepare("UPDATE rooms SET status = 'available' WHERE unit_code = ?");
                $stmtR->execute([$tenant['unit_code']]);
            }

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => "{$tenant['full_name']} marked as moved out. Unit {$tenant['unit_code']} is now available.",
                'unit_code' => $tenant['unit_code']
            ]);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to archive tenant: ' . $e->getMessage()]);
        }
    } elseif ($action === 'edit_tenant') {
        $tenantId = (int)($_POST['id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $newUnitCode = strtoupper(trim($_POST['unit_code'] ?? ''));
        $monthlyRent = (float)($_POST['monthly_rent'] ?? 0);
        $moveInDate = trim($_POST['move_in_date'] ?? date('Y-m-d'));
        $leaseStatus = trim($_POST['lease_status'] ?? 'active');
        $balance = isset($_POST['balance']) ? (float)$_POST['balance'] : null;

        if ($tenantId <= 0 || empty($fullName)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Valid Tenant ID and full name are required.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            // Fetch current tenant info
            $stmtCurr = $pdo->prepare("SELECT id, unit_code, lease_status, balance FROM users WHERE id = ? LIMIT 1");
            $stmtCurr->execute([$tenantId]);
            $currentTenant = $stmtCurr->fetch();

            if (!$currentTenant) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Tenant record not found.']);
                exit;
            }

            $oldUnitCode = $currentTenant['unit_code'];
            $newBalance = $balance !== null ? $balance : (float)$currentTenant['balance'];

            $newPassword = trim($_POST['password'] ?? '');
            $customUsername = trim($_POST['username'] ?? '');

            if (!empty($newPassword)) {
                if (empty($customUsername)) {
                    $pdo->rollBack();
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Username is required when changing password.']);
                    exit;
                }
                $cleanedUsername = strtolower(preg_replace('/[^a-zA-Z0-9_.-]/', '', $customUsername));
                $uCheck = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $uCheck->execute([$cleanedUsername, $tenantId]);
                if ($uCheck->fetch()) {
                    $pdo->rollBack();
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => "Username '{$cleanedUsername}' is already taken."]);
                    exit;
                }
                $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                $stmtUp = $pdo->prepare("
                    UPDATE users 
                    SET full_name = ?, username = ?, password = ?, phone = ?, email = ?, unit_code = ?, monthly_rent = ?, balance = ?, move_in_date = ?, lease_status = ?
                    WHERE id = ?
                ");
                $stmtUp->execute([$fullName, $cleanedUsername, $hashedPassword, $phone, $email, $newUnitCode, $monthlyRent, $newBalance, $moveInDate, $leaseStatus, $tenantId]);
            } else {
                // Keep existing username intact, do not overwrite or modify
                $stmtUp = $pdo->prepare("
                    UPDATE users 
                    SET full_name = ?, phone = ?, email = ?, unit_code = ?, monthly_rent = ?, balance = ?, move_in_date = ?, lease_status = ?
                    WHERE id = ?
                ");
                $stmtUp->execute([$fullName, $phone, $email, $newUnitCode, $monthlyRent, $newBalance, $moveInDate, $leaseStatus, $tenantId]);
            }

            // Sync room statuses
            if ($leaseStatus === 'inactive') {
                // Tenant is inactive, free old unit
                if (!empty($oldUnitCode)) {
                    $stmtR = $pdo->prepare("UPDATE rooms SET status = 'available' WHERE unit_code = ?");
                    $stmtR->execute([$oldUnitCode]);
                }
            } else {
                // Tenant is active
                if ($oldUnitCode !== $newUnitCode) {
                    if (!empty($oldUnitCode)) {
                        $stmtR1 = $pdo->prepare("UPDATE rooms SET status = 'available' WHERE unit_code = ?");
                        $stmtR1->execute([$oldUnitCode]);
                    }
                    if (!empty($newUnitCode)) {
                        $stmtR2 = $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE unit_code = ?");
                        $stmtR2->execute([$newUnitCode]);
                    }
                } else if (!empty($newUnitCode)) {
                    $stmtR3 = $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE unit_code = ?");
                    $stmtR3->execute([$newUnitCode]);
                }
            }

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => "Tenant record for {$fullName} updated successfully.",
                'tenant' => [
                    'id' => $tenantId,
                    'full_name' => $fullName,
                    'phone' => $phone,
                    'email' => $email,
                    'unit_code' => $newUnitCode,
                    'monthly_rent' => $monthlyRent,
                    'monthly_rent_formatted' => '₱' . number_format($monthlyRent, 2),
                    'move_in_date' => date('M j, Y', strtotime($moveInDate)),
                    'lease_status' => $leaseStatus
                ]
            ]);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to update tenant: ' . $e->getMessage()]);
        }

    } elseif ($action === 'delete_tenant') {
        $tenantId = (int)($_POST['id'] ?? 0);

        if ($tenantId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Valid Tenant ID is required.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            // Fetch tenant info
            $stmt = $pdo->prepare("SELECT id, full_name, unit_code FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$tenantId]);
            $tenant = $stmt->fetch();

            if (!$tenant) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Tenant record not found.']);
                exit;
            }

            // Free up room if assigned
            if (!empty($tenant['unit_code'])) {
                $stmtR = $pdo->prepare("UPDATE rooms SET status = 'available' WHERE unit_code = ?");
                $stmtR->execute([$tenant['unit_code']]);
            }

            // Safely unlink tenant from payments (retain payment history, set tenant_id to null)
            $stmtPay = $pdo->prepare("UPDATE payments SET tenant_id = NULL WHERE tenant_id = ?");
            $stmtPay->execute([$tenantId]);

            // Permanently delete user record
            $stmtDel = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmtDel->execute([$tenantId]);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => "Tenant {$tenant['full_name']} (ID: {$tenantId}) has been permanently deleted from user records.",
                'unit_code' => $tenant['unit_code']
            ]);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to delete tenant: ' . $e->getMessage()]);
        }

    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unknown action.']);
    }
}
