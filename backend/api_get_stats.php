<?php
/**
 * Eanne Boarding House Rental System
 * API: Get Live Global Dashboard Statistics & Overview
 * Returns KPI metrics, occupancy rates, financial summaries, and recent activity
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';
$pdo = getDbConnection();

try {
    // 1. Rooms stats
    $stmtRooms = $pdo->query("
        SELECT 
            COUNT(*) as total_rooms,
            SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied_rooms,
            SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_rooms,
            SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_rooms,
            SUM(monthly_rate) as total_potential_revenue,
            SUM(CASE WHEN room_type LIKE '%Private%' THEN 1 ELSE 0 END) as private_total,
            SUM(CASE WHEN room_type LIKE '%Private%' AND status = 'occupied' THEN 1 ELSE 0 END) as private_occupied,
            SUM(CASE WHEN room_type NOT LIKE '%Private%' THEN 1 ELSE 0 END) as bh_total,
            SUM(CASE WHEN room_type NOT LIKE '%Private%' AND status = 'occupied' THEN 1 ELSE 0 END) as bh_occupied
        FROM rooms
    ");
    $roomStats = $stmtRooms->fetch();

    $totalRooms = (int)($roomStats['total_rooms'] ?? 6);
    $occupiedRooms = (int)($roomStats['occupied_rooms'] ?? 0);
    $availableRooms = (int)($roomStats['available_rooms'] ?? 0);
    $maintenanceRooms = (int)($roomStats['maintenance_rooms'] ?? 0);
    $potentialRevenue = (float)($roomStats['total_potential_revenue'] ?? 30000.00);
    $occupancyRate = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 1) : 0;

    $privateTotal = (int)($roomStats['private_total'] ?? 2);
    $privateOccupied = (int)($roomStats['private_occupied'] ?? 0);
    $privateAvailable = max(0, $privateTotal - $privateOccupied);

    $bhTotal = (int)($roomStats['bh_total'] ?? 4);
    $bhOccupied = (int)($roomStats['bh_occupied'] ?? 0);
    $bhAvailable = max(0, $bhTotal - $bhOccupied);

    // 2. Tenants stats
    $stmtTenants = $pdo->query("
        SELECT 
            COUNT(*) as total_tenants,
            SUM(CASE WHEN lease_status = 'active' THEN 1 ELSE 0 END) as active_tenants,
            SUM(CASE WHEN lease_status = 'active' THEN monthly_rent ELSE 0 END) as active_monthly_rent
        FROM users 
        WHERE role = 'tenant'
    ");
    $tenantStats = $stmtTenants->fetch();
    $activeTenants = (int)($tenantStats['active_tenants'] ?? 0);
    $totalTenants = (int)($tenantStats['total_tenants'] ?? 0);
    $activeMonthlyRent = (float)($tenantStats['active_monthly_rent'] ?? 0);

    // 3. Payments stats
    $stmtPayments = $pdo->query("
        SELECT 
            COUNT(*) as total_payments,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified_count,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
            SUM(CASE WHEN status = 'verified' THEN amount ELSE 0 END) as total_verified_amount,
            SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as total_pending_amount,
            SUM(CASE WHEN status = 'verified' AND payment_method = 'Cash' THEN amount ELSE 0 END) as total_cash_amount
        FROM payments
    ");
    $payStats = $stmtPayments->fetch();
    $pendingCount = (int)($payStats['pending_count'] ?? 0);
    $verifiedCount = (int)($payStats['verified_count'] ?? 0);
    $rejectedCount = (int)($payStats['rejected_count'] ?? 0);
    $totalPayments = (int)($payStats['total_payments'] ?? 0);
    $totalVerifiedAmount = (float)($payStats['total_verified_amount'] ?? 0);
    $totalPendingAmount = (float)($payStats['total_pending_amount'] ?? 0);
    $totalCashAmount = (float)($payStats['total_cash_amount'] ?? 0);

    // 4. Maintenance Tickets
    $stmtTickets = $pdo->query("
        SELECT 
            COUNT(*) as total_tickets,
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_tickets,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tickets,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_tickets
        FROM maintenance_tickets
    ");
    $ticketStats = $stmtTickets->fetch();
    $openTickets = (int)($ticketStats['open_tickets'] ?? 0);

    // 5. Recent Activity Feed (Combine latest payments and tickets)
    $stmtRecentPayments = $pdo->query("
        SELECT 
            id, reference_no, tenant_name, unit_code, amount, payment_method, status, payment_date, created_at, verified_at
        FROM payments 
        ORDER BY payment_date DESC, id DESC 
        LIMIT 6
    ");
    $recentPayments = $stmtRecentPayments->fetchAll();

    $activities = [];
    foreach ($recentPayments as $rp) {
        $amountFmt = '₱' . number_format((float)$rp['amount'], 2);
        $vTs = (!empty($rp['verified_at']) && $rp['verified_at'] !== '0000-00-00 00:00:00') 
            ? strtotime($rp['verified_at']) 
            : strtotime($rp['payment_date'] ?? $rp['created_at']);

        if ($rp['status'] === 'verified') {
            $activities[] = [
                'type' => 'payment_verified',
                'title' => "Payment Verified ({$rp['reference_no']})",
                'description' => "{$rp['tenant_name']} (Unit {$rp['unit_code']}) payment of {$amountFmt} via {$rp['payment_method']} was verified & cleared.",
                'badge' => 'Verified & Cleared',
                'badge_class' => 'badge-verified',
                'icon' => 'bi-check2-circle',
                'icon_color' => 'text-success',
                'timestamp' => $vTs ? date('M j, Y g:i A', $vTs) : 'Recently'
            ];
        } elseif ($rp['status'] === 'pending') {
            $activities[] = [
                'type' => 'payment_pending',
                'title' => "Payment Uploaded ({$rp['reference_no']})",
                'description' => "{$rp['tenant_name']} (Unit {$rp['unit_code']}) submitted proof of payment for {$amountFmt} ({$rp['payment_method']}).",
                'badge' => 'Pending Verification',
                'badge_class' => 'badge-pending',
                'icon' => 'bi-clock-history',
                'icon_color' => 'text-warning',
                'timestamp' => !empty($rp['payment_date']) ? date('M j, Y g:i A', strtotime($rp['payment_date'])) : date('M j, Y g:i A', strtotime($rp['created_at']))
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'kpi' => [
            'total_units' => $totalRooms,
            'occupied_units' => $occupiedRooms,
            'available_units' => $availableRooms,
            'maintenance_units' => $maintenanceRooms,
            'occupancy_rate_percent' => $occupancyRate,
            'active_tenants' => $activeTenants,
            'total_tenants' => $totalTenants,
            'monthly_target_revenue' => $potentialRevenue,
            'monthly_target_revenue_formatted' => '₱' . number_format($potentialRevenue, 2),
            'monthly_active_rent' => $activeMonthlyRent,
            'monthly_active_rent_formatted' => '₱' . number_format($activeMonthlyRent, 2),
            'total_verified_amount' => $totalVerifiedAmount,
            'total_verified_amount_formatted' => '₱' . number_format($totalVerifiedAmount, 2),
            'total_pending_amount' => $totalPendingAmount,
            'total_pending_amount_formatted' => '₱' . number_format($totalPendingAmount, 2),
            'total_cash_amount' => $totalCashAmount,
            'total_cash_amount_formatted' => '₱' . number_format($totalCashAmount, 2),
            'pending_queue_count' => $pendingCount,
            'verified_count' => $verifiedCount,
            'total_payments' => $totalPayments,
            'open_tickets' => $openTickets
        ],
        'breakdown' => [
            'boarding_house' => [
                'total' => $bhTotal,
                'occupied' => $bhOccupied,
                'available' => $bhAvailable,
                'rate' => 3500.00,
                'percent' => $bhTotal > 0 ? round(($bhOccupied / $bhTotal) * 100) : 0
            ],
            'private_house' => [
                'total' => $privateTotal,
                'occupied' => $privateOccupied,
                'available' => $privateAvailable,
                'rate' => 8000.00,
                'percent' => $privateTotal > 0 ? round(($privateOccupied / $privateTotal) * 100) : 0
            ]
        ],
        'recent_activity' => $activities
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch statistics: ' . $e->getMessage()
    ]);
}
