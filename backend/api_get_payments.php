<?php
// ====================================================================
// Eanne Boarding House - Get Payments API
// Returns payment records from MySQL with filter support (status, tenant)
// and aggregate counts (pending, verified, rejected, total).
// ====================================================================

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';
$pdo = getDbConnection();

try {
    $statusFilter = strtolower(trim($_GET['status'] ?? 'all'));
    $tenantFilter = trim($_GET['tenant'] ?? '');
    $limit = intval($_GET['limit'] ?? 100);

    // Compute global counts for tabs / badges
    $countsStmt = $pdo->query("SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN `status` = 'pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN `status` = 'verified' THEN 1 ELSE 0 END) AS verified,
        SUM(CASE WHEN `status` = 'rejected' THEN 1 ELSE 0 END) AS rejected,
        COALESCE(SUM(CASE WHEN `status` = 'verified' THEN `amount` ELSE 0 END), 0) AS total_verified_amount
    FROM `payments`");
    $globalCounts = $countsStmt->fetch();

    // Build query with filters
    $whereClauses = [];
    $params = [];

    if ($statusFilter !== 'all' && in_array($statusFilter, ['pending', 'verified', 'rejected'])) {
        $whereClauses[] = "`status` = ?";
        $params[] = $statusFilter;
    }

    if (!empty($tenantFilter)) {
        $whereClauses[] = "(`tenant_name` LIKE ? OR `unit_code` LIKE ?)";
        $params[] = '%' . $tenantFilter . '%';
        $params[] = '%' . $tenantFilter . '%';
    }

    $sql = "SELECT * FROM `payments`";
    if (!empty($whereClauses)) {
        $sql .= " WHERE " . implode(' AND ', $whereClauses);
    }
    $sql .= " ORDER BY `created_at` DESC, `id` DESC LIMIT " . $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $payments = [];
    foreach ($rows as $r) {
        $adminNotes = $r['admin_notes'] ?? '';
        $rejectionReason = '';
        if ($r['status'] === 'rejected' && !empty($adminNotes)) {
            if (preg_match('/^Reason:\s*(.*?)(?:\s*\(Rejected by|$)/is', $adminNotes, $m)) {
                $rejectionReason = trim($m[1]);
            } else {
                $rejectionReason = $adminNotes;
            }
        }
        $payments[] = [
            'id' => intval($r['id']),
            'reference_no' => $r['reference_no'],
            'tenant_id' => $r['tenant_id'] ? intval($r['tenant_id']) : null,
            'tenant_name' => $r['tenant_name'],
            'unit_code' => $r['unit_code'],
            'amount' => floatval($r['amount']),
            'amount_formatted' => '₱' . number_format($r['amount'], 2),
            'payment_method' => $r['payment_method'],
            'payment_type' => $r['payment_type'] ?? 'rent',
            'proof_image' => $r['proof_image'],
            'has_proof' => !empty($r['proof_image']),
            'proof_original_name' => $r['proof_original_name'],
            'payment_date' => date('M j, Y (g:i A)', strtotime($r['payment_date'])),
            'payment_date_short' => date('M j, Y', strtotime($r['payment_date'])),
            'status' => $r['status'],
            'admin_notes' => $r['admin_notes'],
            'rejection_reason' => $rejectionReason ?: null,
            'verified_at' => $r['verified_at'] ? date('M j, Y (g:i A)', strtotime($r['verified_at'])) : null,
            'verified_by' => $r['verified_by'],
            'created_at' => $r['created_at']
        ];
    }

    echo json_encode([
        'success' => true,
        'count' => count($payments),
        'counts' => [
            'total' => intval($globalCounts['total'] ?? 0),
            'pending' => intval($globalCounts['pending'] ?? 0),
            'verified' => intval($globalCounts['verified'] ?? 0),
            'rejected' => intval($globalCounts['rejected'] ?? 0),
            'total_verified_amount' => floatval($globalCounts['total_verified_amount'] ?? 0),
            'total_verified_amount_formatted' => '₱' . number_format($globalCounts['total_verified_amount'] ?? 0, 2),
        ],
        'payments' => $payments
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
