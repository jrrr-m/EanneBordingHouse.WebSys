<?php
/**
 * Eanne Boarding House Rental System
 * API: Tenant Profile & Statement
 * Live sync with MySQL `users`, `rooms`, and `payments`
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
$pdo = getDbConnection();

try {
    $sessionUser = $_SESSION['user'] ?? null;
    $tenantName = trim($_GET['name'] ?? $_GET['tenant'] ?? ($sessionUser['full_name'] ?? ''));
    $username = trim($_GET['username'] ?? ($sessionUser['username'] ?? ''));

    $user = null;

    if (!empty($tenantName) || !empty($username)) {
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
                r.room_type,
                r.inclusions,
                r.max_pax
            FROM users u
            LEFT JOIN rooms r ON u.unit_code = r.unit_code
            WHERE (u.full_name = ? OR u.username = ?) AND u.role = 'tenant'
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tenantName, $username]);
        $user = $stmt->fetch();
    }

    if (!$user) {
        // Fallback to first active tenant dynamically from database
        $stmt2 = $pdo->query("
            SELECT 
                u.id, u.username, u.full_name, u.phone, u.email, u.unit_code, u.monthly_rent, u.balance, u.move_in_date, u.lease_status,
                r.room_type, r.inclusions, r.max_pax
            FROM users u
            LEFT JOIN rooms r ON u.unit_code = r.unit_code
            WHERE u.role = 'tenant' AND u.lease_status = 'active'
            ORDER BY u.id ASC
            LIMIT 1
        ");
        $user = $stmt2->fetch();
    }

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'No active tenant profile found in database.']);
        exit;
    }

    $rent = (float)$user['monthly_rent'];
    $balance = (float)($user['balance'] ?? $rent);

    // Check latest payment for current tenant
    $stmtPay = $pdo->prepare("
        SELECT * FROM payments 
        WHERE tenant_id = ? OR tenant_name = ?
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmtPay->execute([$user['id'], $user['full_name']]);
    $latestPayment = $stmtPay->fetch();

    // Check if there are any pending payments awaiting admin review
    $stmtPending = $pdo->prepare("
        SELECT COUNT(*), COALESCE(SUM(amount), 0) FROM payments 
        WHERE (tenant_id = ? OR tenant_name = ?) AND status = 'pending'
    ");
    $stmtPending->execute([$user['id'], $user['full_name']]);
    [$pendingPaymentsCount, $pendingPaymentsAmount] = $stmtPending->fetch(PDO::FETCH_NUM);
    $pendingPaymentsCount = (int)$pendingPaymentsCount;
    $pendingPaymentsAmount = (float)$pendingPaymentsAmount;

    $paymentStatus = 'unpaid';
    $paymentStatusText = 'Outstanding Balance';
    $badgeClass = 'badge-pending';

    if ($balance <= 0) {
        if ($balance < 0) {
            $paymentStatus = 'advanced';
            $paymentStatusText = 'Advance Credit (₱' . number_format(abs($balance), 2) . ')';
            $badgeClass = 'badge-verified';
        } else {
            $paymentStatus = 'cleared';
            $paymentStatusText = 'Paid & Settled';
            $badgeClass = 'badge-verified';
        }
    } elseif ($pendingPaymentsCount > 0) {
        $paymentStatus = 'pending';
        $paymentStatusText = 'Review in Progress (' . $pendingPaymentsCount . ' Pending)';
        $badgeClass = 'badge-pending';
    } else {
        $paymentStatus = 'unpaid';
        $paymentStatusText = 'Pending Settlement (₱' . number_format($balance, 2) . ')';
        $badgeClass = 'badge-pending';
    }

    $parts = explode(' ', trim($user['full_name']));
    $firstName = $parts[0];
    $initials = strtoupper(substr($parts[0], 0, 1) . (count($parts) > 1 ? substr($parts[count($parts) - 1], 0, 1) : ''));

    echo json_encode([
        'success' => true,
        'profile' => [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'first_name' => $firstName,
            'initials' => $initials,
            'phone' => $user['phone'],
            'email' => $user['email'],
            'unit_code' => $user['unit_code'],
            'room_type' => $user['room_type'] ?? 'Boarding House Rental',
            'inclusions' => $user['inclusions'] ?? 'Individual room, kitchen area, shared CR. Bills excluded.',
            'monthly_rent' => $rent,
            'monthly_rent_formatted' => '₱' . number_format($rent, 2),
            'balance' => $balance,
            'balance_formatted' => ($balance < 0 ? '-₱' . number_format(abs($balance), 2) : '₱' . number_format($balance, 2)),
            'advance_credit' => $balance < 0 ? abs($balance) : 0,
            'advance_credit_formatted' => '₱' . number_format($balance < 0 ? abs($balance) : 0, 2),
            'pending_payments_count' => $pendingPaymentsCount,
            'pending_payments_amount' => $pendingPaymentsAmount,
            'move_in_date' => $user['move_in_date'] ? date('M j, Y', strtotime($user['move_in_date'])) : 'Jan 15, 2026',
            'statement_month' => date('F Y'),
            'due_date' => date('F 5, Y'),
            'payment_status' => $paymentStatus,
            'payment_status_text' => $paymentStatusText,
            'badge_class' => $badgeClass,
            'latest_payment' => $latestPayment ? [
                'reference_no' => $latestPayment['reference_no'],
                'amount' => (float)$latestPayment['amount'],
                'payment_method' => $latestPayment['payment_method'],
                'payment_type' => $latestPayment['payment_type'] ?? 'rent',
                'status' => $latestPayment['status'],
                'date' => date('M j, Y', strtotime($latestPayment['payment_date']))
            ] : null
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
