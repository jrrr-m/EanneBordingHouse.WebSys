    fyy<?php
// ====================================================================
// Eanne Boarding House - Admin Approve / Reject Payment API
// Updates payment status in MySQL to 'verified' or 'rejected'.
// ====================================================================

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';
$pdo = getDbConnection();

try {
    $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
    $action = strtolower(trim($_POST['action'] ?? $_GET['action'] ?? ''));
    $reason = trim($_POST['reason'] ?? $_GET['reason'] ?? '');
    $notes = trim($_POST['notes'] ?? $_GET['notes'] ?? '');
    $reviewer = trim($_POST['reviewer'] ?? $_GET['reviewer'] ?? 'Landlord Admin');

    if ($id <= 0) {
        throw new Exception("Invalid payment ID specified.");
    }

    if (!in_array($action, ['approve', 'verify', 'reject'])) {
        throw new Exception("Invalid action. Must be 'approve', 'verify', or 'reject'.");
    }

    // Check if payment exists
    $checkStmt = $pdo->prepare("SELECT * FROM `payments` WHERE `id` = ?");
    $checkStmt->execute([$id]);
    $payment = $checkStmt->fetch();

    if (!$payment && $id >= 100) {
        $fallbackId = $id - 100;
        $checkStmt = $pdo->prepare("SELECT * FROM `payments` WHERE `id` = ?");
        $checkStmt->execute([$fallbackId]);
        $fallbackRow = $checkStmt->fetch();
        if ($fallbackRow) {
            $payment = $fallbackRow;
            $id = $fallbackId;
        }
    }

    if (!$payment) {
        throw new Exception("Payment record with ID #{$id} was not found.");
    }

    $isApprove = in_array($action, ['approve', 'verify']);
    $newStatus = $isApprove ? 'verified' : 'rejected';

    if ($isApprove) {
        if (empty($notes)) {
            $notes = "Approved and credited by {$reviewer} on " . date('M j, Y g:i A');
        }
        $rejectionReason = null;
    } else {
        $rejectionReason = !empty($reason) ? $reason : (!empty($notes) ? $notes : 'Payment verification rejected - please re-upload valid proof.');
        $notes = "Reason: {$rejectionReason} (Rejected by {$reviewer} on " . date('M j, Y g:i A') . ")";
    }

    $updateStmt = $pdo->prepare("UPDATE `payments` 
        SET `status` = ?, 
            `verified_at` = NOW(), 
            `verified_by` = ?, 
            `admin_notes` = ? 
        WHERE `id` = ?");
    $updateStmt->execute([$newStatus, $reviewer, $notes, $id]);

    // Fetch updated record
    $fetchStmt = $pdo->prepare("SELECT * FROM `payments` WHERE `id` = ?");
    $fetchStmt->execute([$id]);
    $updatedPayment = $fetchStmt->fetch();

    // Compute updated pending count
    $pendingCount = $pdo->query("SELECT COUNT(*) FROM `payments` WHERE `status` = 'pending'")->fetchColumn();

    echo json_encode([
        'success' => true,
        'message' => $isApprove 
            ? "Payment #{$updatedPayment['reference_no']} has been successfully verified & approved!"
            : "Payment #{$updatedPayment['reference_no']} has been rejected." . ($rejectionReason ? " Reason: {$rejectionReason}" : ""),
        'new_status' => $newStatus,
        'pending_count' => intval($pendingCount),
        'payment' => [
            'id' => intval($updatedPayment['id']),
            'reference_no' => $updatedPayment['reference_no'],
            'tenant_name' => $updatedPayment['tenant_name'],
            'unit_code' => $updatedPayment['unit_code'],
            'amount' => floatval($updatedPayment['amount']),
            'amount_formatted' => '₱' . number_format($updatedPayment['amount'], 2),
            'payment_method' => $updatedPayment['payment_method'],
            'proof_image' => $updatedPayment['proof_image'],
            'payment_date' => date('M j, Y (g:i A)', strtotime($updatedPayment['payment_date'])),
            'payment_date_short' => date('M j, Y', strtotime($updatedPayment['payment_date'])),
            'status' => $updatedPayment['status'],
            'verified_at' => date('M j, Y (g:i A)', strtotime($updatedPayment['verified_at'])),
            'verified_by' => $updatedPayment['verified_by'],
            'admin_notes' => $updatedPayment['admin_notes'],
            'rejection_reason' => $rejectionReason
        ]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
