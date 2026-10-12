<?php
// ====================================================================
// Eanne Boarding House - Record Cash Payment API (Admin)
// Saves walk-in cash payments directly into MySQL as 'verified'.
// ====================================================================

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

require_once __DIR__ . '/db.php';
$pdo = getDbConnection();

try {
    $tenantName = trim($_POST['tenant_name'] ?? $_POST['cashTenantSelect'] ?? '');
    $amountRaw = $_POST['amount'] ?? $_POST['cashAmountInput'] ?? '3500';
    $amount = floatval(str_replace([',', ' '], '', $amountRaw));
    $notes = trim($_POST['notes'] ?? 'Cash over-the-counter received by Caretaker/Admin');

    if (empty($tenantName)) {
        throw new Exception("Please select or enter the tenant's name.");
    }

    if ($amount <= 0) {
        throw new Exception("Invalid payment amount.");
    }

    // Try finding tenant unit
    $unitCode = 'BH-01';
    $tenantId = null;
    $userStmt = $pdo->prepare("SELECT id, unit_code FROM `users` WHERE `full_name` = ? OR `username` = ? LIMIT 1");
    $userStmt->execute([$tenantName, $tenantName]);
    $user = $userStmt->fetch();
    if ($user) {
        $tenantId = $user['id'];
        if (!empty($user['unit_code'])) {
            $unitCode = $user['unit_code'];
        }
    } else {
        // If string like "Carlos Dominguez - Unit BH-01"
        if (preg_match('/(BH-\d+|PB-\d+)/i', $tenantName, $matches)) {
            $unitCode = strtoupper($matches[1]);
        }
    }

    $receiptNo = 'CSH-' . date('ymd') . '-' . rand(100, 999);

    $insertSql = "INSERT INTO `payments` 
        (`reference_no`, `tenant_id`, `tenant_name`, `unit_code`, `amount`, `payment_method`, `payment_date`, `status`, `admin_notes`, `verified_at`, `verified_by`)
        VALUES (?, ?, ?, ?, ?, 'Cash', NOW(), 'verified', ?, NOW(), 'Landlord Admin')";
    
    $stmt = $pdo->prepare($insertSql);
    $stmt->execute([
        $receiptNo,
        $tenantId,
        $tenantName,
        $unitCode,
        $amount,
        $notes
    ]);

    $insertedId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => "Cash payment of ₱" . number_format($amount, 2) . " logged and official receipt #{$receiptNo} generated!",
        'payment' => [
            'id' => intval($insertedId),
            'reference_no' => $receiptNo,
            'tenant_name' => $tenantName,
            'unit_code' => $unitCode,
            'amount' => $amount,
            'amount_formatted' => '₱' . number_format($amount, 2),
            'payment_method' => 'Cash',
            'status' => 'verified',
            'payment_date' => date('M j, Y (g:i A)')
        ]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
