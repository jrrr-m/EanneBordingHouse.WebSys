<?php
// ====================================================================
// Eanne Boarding House - Tenant Payment Proof Upload API
// Handles multipart/form-data upload of payment receipt image,
// validates inputs, moves file to uploads/payments/, saves to MySQL.
// ====================================================================

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
$pdo = getDbConnection();

try {
    $sessionUser = $_SESSION['user'] ?? null;
    $tenantName = trim($_POST['tenant_name'] ?? $_POST['payTenantName'] ?? ($sessionUser['full_name'] ?? ''));
    $unitCode = trim($_POST['unit_code'] ?? $_POST['payUnitCode'] ?? ($sessionUser['unit_code'] ?? ''));

    // If still missing, attempt dynamic resolution from MySQL
    if (empty($tenantName)) {
        $firstTenant = $pdo->query("SELECT full_name, unit_code, monthly_rent FROM users WHERE role = 'tenant' AND lease_status = 'active' LIMIT 1")->fetch();
        if ($firstTenant) {
            $tenantName = $firstTenant['full_name'];
            if (empty($unitCode)) $unitCode = $firstTenant['unit_code'];
        }
    }
    if (empty($unitCode) && !empty($tenantName)) {
        $uStmt = $pdo->prepare("SELECT unit_code FROM users WHERE full_name = ? LIMIT 1");
        $uStmt->execute([$tenantName]);
        $unitCode = $uStmt->fetchColumn() ?: 'General';
    }

    $paymentMethod = trim($_POST['payment_method'] ?? $_POST['payMethodSelect'] ?? 'GCash');
    $amountRaw = $_POST['amount'] ?? $_POST['payAmount'] ?? '0';
    $amount = floatval(str_replace([',', ' '], '', $amountRaw));
    
    if ($amount <= 0 && !empty($tenantName)) {
        $rStmt = $pdo->prepare("SELECT monthly_rent FROM users WHERE full_name = ? LIMIT 1");
        $rStmt->execute([$tenantName]);
        $resolvedRent = (float)$rStmt->fetchColumn();
        $amount = $resolvedRent > 0 ? $resolvedRent : 3500.00;
    } elseif ($amount <= 0) {
        $amount = 3500.00;
    }

    $refRaw = trim($_POST['reference_no'] ?? $_POST['payRefNumber'] ?? '');
    if (empty($refRaw)) {
        $refRaw = 'REF-' . date('ymd') . '-' . rand(1000, 9999);
    }
    // Clean reference number
    $referenceNo = preg_replace('/[^A-Za-z0-9\-_]/', '', $refRaw);
    if (empty($referenceNo)) {
        $referenceNo = 'REF-' . time();
    }

    $notes = trim($_POST['notes'] ?? 'Uploaded via Tenant Self-Service Portal');

    // 2. Handle File Upload
    $proofImagePath = null;
    $originalFileName = null;

    $uploadDir = __DIR__ . '/../uploads/payments/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileField = null;
    if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $fileField = 'proof_image';
    } elseif (isset($_FILES['paymentReceiptFile']) && $_FILES['paymentReceiptFile']['error'] !== UPLOAD_ERR_NO_FILE) {
        $fileField = 'paymentReceiptFile';
    }

    if ($fileField !== null) {
        $file = $_FILES[$fileField];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload failed with error code: " . $file['error']);
        }

        // Validate file size (max 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new Exception("File size exceeds 10MB limit.");
        }

        $originalFileName = basename($file['name']);
        $fileExt = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];

        if (!in_array($fileExt, $allowedExts)) {
            throw new Exception("Invalid file type (." . htmlspecialchars($fileExt) . "). Allowed extensions: JPG, PNG, WEBP, GIF, SVG.");
        }

        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];
        // Allow text/xml or image/svg+xml for SVG
        if (!in_array($mimeType, $allowedMimes) && $fileExt !== 'svg') {
            throw new Exception("Invalid image MIME type: {$mimeType}");
        }

        // Generate safe unique filename
        $safeRef = preg_replace('/[^a-zA-Z0-9]/', '', $referenceNo);
        $newFileName = 'proof_' . date('Ymd_His') . '_' . $safeRef . '_' . substr(md5(uniqid()), 0, 6) . '.' . $fileExt;
        $targetFilePath = $uploadDir . $newFileName;

        if (!move_uploaded_file($file['tmp_name'], $targetFilePath)) {
            throw new Exception("Could not save the uploaded file to target folder.");
        }

        $proofImagePath = 'uploads/payments/' . $newFileName;
    }

    // 3. Handle Reference Number collision gracefully
    $checkStmt = $pdo->prepare("SELECT id FROM `payments` WHERE `reference_no` = ?");
    $checkStmt->execute([$referenceNo]);
    if ($checkStmt->fetch()) {
        $referenceNo .= '-' . rand(10, 99);
    }

    // 4. Lookup tenant_id from users table if available
    $tenantId = null;
    $userStmt = $pdo->prepare("SELECT id FROM `users` WHERE `full_name` = ? OR `username` = ? LIMIT 1");
    $userStmt->execute([$tenantName, $tenantName]);
    $userRow = $userStmt->fetch();
    if ($userRow) {
        $tenantId = $userRow['id'];
    }

    $paymentType = strtolower(trim($_POST['payment_type'] ?? 'rent'));
    if (!in_array($paymentType, ['rent', 'partial', 'advance'])) {
        $paymentType = 'rent';
    }

    // Safeguard: Advance payment is not allowed when tenant has an outstanding balance
    if ($tenantId) {
        require_once __DIR__ . '/overdue_rules.php';
        $userRowFull = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $userRowFull->execute([$tenantId]);
        $tData = $userRowFull->fetch();
        if ($tData) {
            $odInfo = calculateOverdueStatus($tData);
            if ($odInfo['is_eviction_risk'] && $amount < $odInfo['min_payment_required']) {
                $minFmt = number_format($odInfo['min_payment_required'], 2);
                throw new Exception("Eviction Rule Alert: You have {$odInfo['effective_overdue_months']} months overdue rent. Minimum payment of 50% (₱{$minFmt}) is required to avoid eviction.");
            }
            if ($paymentType === 'advance' && $odInfo['balance'] > 0) {
                $paymentType = 'partial';
            }
        }
    }

    // 5. Insert Payment Record into MySQL
    $insertSql = "INSERT INTO `payments` 
        (`reference_no`, `tenant_id`, `tenant_name`, `unit_code`, `amount`, `payment_method`, `payment_type`, `proof_image`, `proof_original_name`, `payment_date`, `status`, `admin_notes`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending', ?)";
    
    $insertStmt = $pdo->prepare($insertSql);
    $insertStmt->execute([
        $referenceNo,
        $tenantId,
        $tenantName,
        $unitCode,
        $amount,
        $paymentMethod,
        $paymentType,
        $proofImagePath,
        $originalFileName,
        $notes
    ]);

    $insertedId = $pdo->lastInsertId();

    // Fetch the newly created record
    $fetchStmt = $pdo->prepare("SELECT * FROM `payments` WHERE `id` = ?");
    $fetchStmt->execute([$insertedId]);
    $newPayment = $fetchStmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'Proof of payment uploaded successfully! It is now pending admin verification.',
        'payment' => [
            'id' => $newPayment['id'],
            'reference_no' => $newPayment['reference_no'],
            'tenant_name' => $newPayment['tenant_name'],
            'unit_code' => $newPayment['unit_code'],
            'amount' => floatval($newPayment['amount']),
            'amount_formatted' => '₱' . number_format($newPayment['amount'], 2),
            'payment_method' => $newPayment['payment_method'],
            'proof_image' => $newPayment['proof_image'],
            'proof_original_name' => $newPayment['proof_original_name'],
            'payment_date' => date('M j, Y (g:i A)', strtotime($newPayment['payment_date'])),
            'status' => $newPayment['status'],
            'admin_notes' => $newPayment['admin_notes']
        ]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
