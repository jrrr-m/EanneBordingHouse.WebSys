<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/overdue_rules.php';
$pdo = getDbConnection();

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

// Determine active tenant
$currentUser = $_SESSION['user'];
$tenantName = $currentUser['full_name'];
$username = $currentUser['username'];

// Fetch tenant profile and assigned room
$userStmt = $pdo->prepare("
    SELECT u.*, r.room_type, r.inclusions, r.max_pax
    FROM users u
    LEFT JOIN rooms r ON u.unit_code = r.unit_code
    WHERE u.id = ?
    LIMIT 1
");
$userStmt->execute([$currentUser['id']]);
$user = $userStmt->fetch();

if (!$user || $user['role'] === 'admin') {
    // If an admin accesses this page, load the first active tenant as demo preview
    $firstTenant = $pdo->query("
        SELECT u.*, r.room_type, r.inclusions, r.max_pax
        FROM users u
        LEFT JOIN rooms r ON u.unit_code = r.unit_code
        WHERE u.role = 'tenant' AND u.lease_status = 'active'
        LIMIT 1
    ")->fetch();
    if ($firstTenant) {
        $user = $firstTenant;
        $tenantName = $user['full_name'];
    }
}

// Calculate tenant overdue metrics
$userBal = (float)($user['balance'] ?? 0);
$userRent = (float)($user['monthly_rent'] ?? 3500);
$overdueMetrics = calculateOverdueStatus(array_merge($user, ['balance' => $userBal, 'monthly_rent' => $userRent]));

// Handle Direct POST Actions on Tenant Dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Submit Payment Proof
    if ($action === 'upload_payment') {
        $refNo = trim($_POST['reference_no'] ?? '');
        $amount = (float)($_POST['amount'] ?? ($user['monthly_rent'] ?? 3500));
        $method = trim($_POST['payment_method'] ?? 'GCash');
        $paymentType = strtolower(trim($_POST['payment_type'] ?? 'rent'));
        if (!in_array($paymentType, ['rent', 'partial', 'advance'])) $paymentType = 'rent';
        // Safeguard: Cannot choose advance payment if there is an outstanding balance
        if ($paymentType === 'advance' && $userBal > 0) {
            $paymentType = 'partial';
        }

        // Policy Enforcement: If tenant is at eviction risk (3+ months non-payment), payment must be at least 50%
        if ($overdueMetrics['is_eviction_risk'] && $amount < $overdueMetrics['min_payment_required']) {
            $minFormatted = number_format($overdueMetrics['min_payment_required'], 2);
            header("Location: tenant-dashboard.php?err=" . urlencode("Eviction Policy Alert: You have {$overdueMetrics['effective_overdue_months']} months of unpaid rent. A minimum payment of 50% (₱{$minFormatted}) is required to avoid eviction."));
            exit;
        }
        $unitCode = $user['unit_code'] ?? 'BH-02';
        $tenantId = $user['id'] ?? null;

        $proofPath = '';
        if (!empty($_FILES['proof_image']['name'])) {
            $targetDir = __DIR__ . '/../uploads/payments/';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            $ext = strtolower(pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION));
            $filename = 'pay_' . date('Ymd_His') . '_' . rand(100, 999) . '.' . $ext;
            $dest = $targetDir . $filename;
            if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $dest)) {
                $proofPath = 'uploads/payments/' . $filename;
            }
        }

        if (!empty($refNo)) {
            $stmt = $pdo->prepare("
                INSERT INTO payments (reference_no, tenant_id, tenant_name, unit_code, amount, payment_method, payment_type, payment_date, proof_image, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, 'pending', NOW())
            ");
            $stmt->execute([$refNo, $tenantId, $tenantName, $unitCode, $amount, $method, $paymentType, $proofPath]);
            header("Location: tenant-dashboard.php?msg=" . urlencode("Proof of Payment #{$refNo} submitted successfully. Admin will review and approve."));
            exit;
        }
    }

    // 2. Submit Maintenance Request
    if ($action === 'create_ticket') {
        $category = trim($_POST['category'] ?? 'General');
        $urgency = trim($_POST['urgency'] ?? 'Normal');
        $desc = trim($_POST['description'] ?? '');
        $unitCode = $user['unit_code'] ?? 'BH-02';
        $ticketNo = 'TKT-' . rand(1000, 9999);

        if (!empty($desc)) {
            $uploadedImages = [];
            $targetDir = __DIR__ . '/../uploads/tickets/';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            if (!empty($_FILES['ticket_images']['name'])) {
                $names = (array)$_FILES['ticket_images']['name'];
                $tmpNames = (array)$_FILES['ticket_images']['tmp_name'];
                $sizes = (array)$_FILES['ticket_images']['size'];
                $errors = (array)$_FILES['ticket_images']['error'];

                for ($i = 0; $i < count($names); $i++) {
                    if (empty($names[$i]) || $errors[$i] !== UPLOAD_ERR_OK) continue;
                    if ($sizes[$i] > 10 * 1024 * 1024) continue;
                    $ext = strtolower(pathinfo($names[$i], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) continue;

                    $cleanName = 'ticket_' . date('Ymd_His') . '_' . rand(100, 999) . '.' . $ext;
                    $targetPath = $targetDir . $cleanName;
                    if (move_uploaded_file($tmpNames[$i], $targetPath)) {
                        $uploadedImages[] = 'uploads/tickets/' . $cleanName;
                    }
                }
            }
            $attachmentImagesJson = !empty($uploadedImages) ? json_encode($uploadedImages) : null;

            $stmt = $pdo->prepare("
                INSERT INTO maintenance_tickets (ticket_no, tenant_name, unit_code, category, urgency, description, attachment_images, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'open', NOW())
            ");
            $stmt->execute([$ticketNo, $tenantName, $unitCode, $category, $urgency, $desc, $attachmentImagesJson]);
            header("Location: tenant-dashboard.php?msg=" . urlencode("Maintenance ticket #{$ticketNo} submitted to caretaker."));
            exit;
        }
    }
}

// Fetch Tenant Payment History
$paymentsStmt = $pdo->prepare("
    SELECT * FROM payments 
    WHERE tenant_name = ? 
    ORDER BY id DESC
");
$paymentsStmt->execute([$tenantName]);
$payments = $paymentsStmt->fetchAll();

// Determine Balance & Statement Status
$currentBalance = (float)($user['balance'] ?? $user['monthly_rent']);
$monthlyRent = (float)$user['monthly_rent'];

// Count pending payments for this tenant
$pendingStmt = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(amount), 0) FROM payments WHERE tenant_name = ? AND status = 'pending'");
$pendingStmt->execute([$tenantName]);
[$pendingCount, $pendingTotal] = $pendingStmt->fetch(PDO::FETCH_NUM);
$pendingCount = (int)$pendingCount;
$pendingTotal = (float)$pendingTotal;

$statementStatus = 'unpaid';
$statementBadge = '<span class="badge-ebh badge-pending"><i class="bi bi-clock-history"></i> Balance Due: ₱' . number_format($currentBalance, 2) . '</span>';

if ($currentBalance <= 0) {
    if ($currentBalance < 0) {
        $statementStatus = 'advanced';
        $statementBadge = '<span class="badge-ebh badge-verified"><i class="bi bi-shield-check"></i> Advance Credit: ₱' . number_format(abs($currentBalance), 2) . '</span>';
    } else {
        $statementStatus = 'verified';
        $statementBadge = '<span class="badge-ebh badge-verified"><i class="bi bi-check-circle"></i> Fully Settled & Cleared</span>';
    }
} elseif ($pendingCount > 0) {
    $statementStatus = 'pending';
    $statementBadge = '<span class="badge-ebh badge-pending"><i class="bi bi-hourglass-split"></i> Review in Progress (' . $pendingCount . ' Pending Approval)</span>';
} else {
    $statementStatus = 'unpaid';
    $statementBadge = '<span class="badge-ebh badge-pending"><i class="bi bi-exclamation-circle"></i> Pending Settlement (₱' . number_format($currentBalance, 2) . ')</span>';
}

// Fetch Tenant Maintenance Tickets
$ticketsStmt = $pdo->prepare("
    SELECT * FROM maintenance_tickets 
    WHERE tenant_name = ? 
    ORDER BY id DESC
");
$ticketsStmt->execute([$tenantName]);
$tickets = $ticketsStmt->fetchAll();

$initials = strtoupper(substr($user['full_name'], 0, 1) . substr(strrchr($user['full_name'], ' ') ?: '', 1, 1));
if (!$initials) $initials = 'TN';
$firstName = explode(' ', trim($user['full_name']))[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tenant Portal | <?= htmlspecialchars($user['full_name']) ?> | Eanne Boarding House</title>
  <meta name="description" content="Tenant self-service portal for rent payments, digital receipts, and maintenance requests.">
  
  <!-- Bootstrap 4.1.3 CSS & Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.1.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <!-- Custom Design System -->
  <link rel="stylesheet" href="../assets/css/style.css">
  <!-- Business Logo Favicon -->
  <link rel="icon" type="image/png" href="../assets/img/favicon.png">
  <link rel="apple-touch-icon" href="../assets/img/favicon.png">
</head>
<body class="bg-light d-flex flex-column min-vh-100">

  <!-- Tenant Topbar -->
  <header class="sticky-top">
    <nav class="navbar navbar-expand-lg navbar-ebh shadow-sm" id="tenantNavbar">
      <div class="container-fluid px-3 px-xl-5">
        <a class="ebh-brand" href="../index.php">
          <div class="ebh-logo-mark" title="Eanne Boarding House">
            <img src="../assets/img/795286329_1754004695838265_6753877283478212113_n.png" alt="EBH Logo">
          </div>
          <div class="ebh-brand-text">
            <span class="ebh-brand-title">Tenant Portal</span>
            <span class="ebh-brand-subtitle d-none d-sm-block">Self-Service Console</span>
          </div>
        </a>

        <div class="d-flex align-items-center gap-3">
          <div class="d-flex align-items-center gap-2">
            <div class="avatar-circle sm"><?= htmlspecialchars($initials) ?></div>
            <div class="d-none d-sm-block text-start lh-1">
              <span class="fw-bold small d-block text-navy"><?= htmlspecialchars($user['full_name']) ?></span>
              <small class="text-muted">Unit <?= htmlspecialchars($user['unit_code']) ?> (<?= htmlspecialchars($user['room_type'] ?? 'Boarding Rental') ?>)</small>
            </div>
          </div>
          <a href="login.php?logout=1" class="btn btn-outline-danger btn-sm" title="Sign Out">
            <i class="bi bi-box-arrow-right"></i> <span class="d-none d-sm-inline ms-1">Sign Out</span>
          </a>
        </div>
      </div>
    </nav>
  </header>

  <!-- Main Tenant Content -->
  <main class="container py-4 flex-grow-1">
    <?php if (!empty($msg)): ?>
      <div class="alert alert-success alert-dismissible fade show shadow-sm border-success py-2 px-3 mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($msg) ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
    <?php endif; ?>

    <?php if (!empty($err)): ?>
      <div class="alert alert-danger alert-dismissible fade show shadow-sm border-danger py-2 px-3 mb-4" role="alert">
        <i class="bi bi-exclamation-octagon-fill me-2"></i> <?= htmlspecialchars($err) ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
    <?php endif; ?>

    <?php if ($overdueMetrics['is_eviction_risk']): ?>
      <!-- EVICTION WARNING BANNER (3+ Months Overdue) -->
      <div class="alert alert-danger shadow border-danger p-3 mb-4 rounded-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
          <div class="bg-danger text-white rounded-circle p-3 d-flex align-items-center justify-content-center" style="width:48px; height:48px;">
            <i class="bi bi-door-closed-fill fs-4"></i>
          </div>
          <div>
            <h5 class="fw-bold text-danger mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Urgent: Notice of Eviction / Removal Risk</h5>
            <p class="mb-0 text-dark small">
              You currently have <strong><?= $overdueMetrics['effective_overdue_months'] ?> months of unpaid rent</strong> totaling <strong>₱<?= number_format($overdueMetrics['balance'], 2) ?></strong>. Under boarding house tenancy regulations, you must pay at least <strong>50% (₱<?= number_format($overdueMetrics['min_payment_required'], 2) ?>)</strong> immediately to keep your room and prevent lease cancellation/eviction.
            </p>
          </div>
        </div>
        <div>
          <button type="button" class="btn btn-danger btn-sm fw-bold shadow-sm" onclick="setPayAmount(<?= $overdueMetrics['min_payment_required'] ?>); openPaymentModalWithType('partial');">
            <i class="bi bi-credit-card-2-front me-1"></i> Pay Required 50% (₱<?= number_format($overdueMetrics['min_payment_required']) ?>)
          </button>
        </div>
      </div>
    <?php elseif ($overdueMetrics['is_exempted']): ?>
      <!-- EXEMPTION BANNER -->
      <div class="alert alert-info shadow-sm border-info p-3 mb-4 rounded-3 d-flex align-items-center gap-3">
        <div class="bg-info text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
          <i class="bi bi-shield-check fs-5"></i>
        </div>
        <div>
          <h6 class="fw-bold text-navy mb-0"><i class="bi bi-calendar-check me-1"></i> Admin Overdue Grace Exemption Active</h6>
          <small class="text-muted d-block">
            You were granted an administrative exemption for <strong><?= $overdueMetrics['exemption_months'] ?> month(s)</strong> (Reason: <em>"<?= htmlspecialchars($overdueMetrics['exemption_reason']) ?>"</em>).
            <strong>Note:</strong> Your unpaid rent of ₱<?= number_format($overdueMetrics['balance'], 2) ?> carries over to next month where full 50% minimum settlement will be mandated.
          </small>
        </div>
      </div>
    <?php endif; ?>

    <!-- Welcome Header Card -->
    <div class="ebh-card p-4 mb-4" style="background: linear-gradient(135deg, #ffffff 0%, #faf9f6 100%);">
      <div class="row align-items-center g-3">
        <div class="col-md-8">
          <div class="d-inline-flex align-items-center gap-2 px-2.5 py-0.5 rounded-pill bg-light border text-navy small fw-semibold mb-2" style="font-size:0.75rem;">
            <i class="bi bi-patch-check-fill text-primary"></i> Verified Resident &bull; Unit <?= htmlspecialchars($user['unit_code']) ?>
          </div>
          <h2 class="fw-bold text-navy mb-1" style="font-size: 1.65rem; letter-spacing: -0.02em;">Welcome back, <?= htmlspecialchars($firstName) ?></h2>
          <p class="text-muted mb-0 small">
            <?= htmlspecialchars($user['room_type'] ?? 'Boarding Rental') ?> &bull; Soldiers Village, Pasig City. Electricity &amp; water shouldered by tenant.
          </p>
        </div>
        <div class="col-md-4 text-md-end">
          <button type="button" class="btn btn-ebh-primary shadow-sm" data-toggle="modal" data-target="#tenantUploadModal">
            <i class="bi bi-cloud-arrow-up-fill me-1"></i> Submit Payment Proof
          </button>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <!-- Left Column: Current Bill & Payment History -->
      <div class="col-lg-8">
        <!-- Current Month Bill Card -->
        <div class="ebh-card mb-4">
          <div class="ebh-card-header d-flex justify-content-between align-items-center" style="background: #faf9f6; border-bottom: 1px solid var(--color-border);">
            <div>
              <h6 class="fw-bold text-navy mb-0"><i class="bi bi-calendar2-check-fill me-2 text-primary"></i> Statement: <?= date('F Y') ?></h6>
              <small class="text-muted">Monthly Room Rental Statement</small>
            </div>
            <?= $statementBadge ?>
          </div>
          <div class="ebh-card-body">
            <div class="row align-items-center g-3 mb-4">
              <div class="col-md-4 border-end-md">
                <span class="text-muted small d-block">Monthly Room Rent</span>
                <div class="h3 fw-bold text-navy mb-0">₱<?= number_format($user['monthly_rent'], 2) ?></div>
                <small class="text-danger fw-semibold"><i class="bi bi-exclamation-triangle-fill me-1"></i> Bills Excluded</small>
              </div>
              <div class="col-md-4 border-end-md ps-md-3">
                <span class="text-muted small d-block">Current Outstanding Balance</span>
                <div class="h3 fw-bold <?= $currentBalance <= 0 ? ($currentBalance < 0 ? 'text-success' : 'text-success') : 'text-danger' ?> mb-0" id="tenantCurrentBalanceDisplay">
                  <?php if ($currentBalance < 0): ?>
                    -₱<?= number_format(abs($currentBalance), 2) ?>
                  <?php else: ?>
                    ₱<?= number_format($currentBalance, 2) ?>
                  <?php endif; ?>
                </div>
                <?php if ($currentBalance < 0): ?>
                  <small class="text-success fw-bold"><i class="bi bi-shield-check me-1"></i> ₱<?= number_format(abs($currentBalance), 2) ?> Advance Credit</small>
                <?php elseif ($currentBalance == 0): ?>
                  <small class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> Fully Paid (Zero Balance)</small>
                <?php else: ?>
                  <small class="text-warning fw-semibold"><i class="bi bi-clock-history me-1"></i> Pay whatever you can or advance</small>
                <?php endif; ?>
              </div>
              <div class="col-md-4 ps-md-3">
                <span class="text-muted small d-block">Due Date</span>
                <div class="h5 fw-bold text-navy mb-1"><i class="bi bi-calendar-event me-1 text-primary"></i> <?= date('F 5, Y') ?></div>
                <small class="text-muted">Due every 5th of the month</small>
              </div>
            </div>

            <?php if ($pendingCount > 0): ?>
              <div class="alert alert-info border-info py-2 px-3 mb-3 small d-flex align-items-center justify-content-between">
                <div>
                  <i class="bi bi-hourglass-split me-1"></i> <strong>Under Review:</strong> You have <strong><?= $pendingCount ?> payment(s) totaling ₱<?= number_format($pendingTotal, 2) ?></strong> pending admin review. Once approved, it will automatically deduct from your balance.
                </div>
                <span class="badge badge-warning text-dark">Awaiting Approval</span>
              </div>
            <?php endif; ?>

            <div class="d-flex flex-wrap gap-2 pt-3 border-top justify-content-between align-items-center">
              <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#paymentChannelsModal">
                <i class="bi bi-qr-code me-1 text-primary"></i> View GCash / Bank QR Codes
              </button>
              <div class="d-flex gap-2">
                <?php if ($currentBalance <= 0): ?>
                  <button type="button" class="btn btn-sm btn-outline-primary fw-bold" id="btnAdvancePayment" onclick="openPaymentModalWithType('advance')">
                    <i class="bi bi-calendar-plus me-1"></i> Advance Payment
                  </button>
                <?php endif; ?>
                <button type="button" class="btn btn-sm btn-ebh-accent fw-bold" onclick="openPaymentModalWithType('partial')">
                  <i class="bi bi-upload me-1"></i> <?= $currentBalance > 0 ? 'Pay Any Amount / Settle' : 'Submit Payment Proof' ?>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Payment History Ledger -->
        <div class="ebh-card mb-4">
          <div class="ebh-card-header">
            <div>
              <h6 class="fw-bold text-navy mb-0"><i class="bi bi-receipt-cutoff me-2 text-primary"></i> My Payment History & Official Receipts</h6>
              <small class="text-muted">View past statements and download verified e-receipts</small>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-ebh table-hover mb-0" id="tenantHistoryTable">
              <thead>
                <tr>
                  <th>Receipt / Ref #</th>
                  <th>Date Paid</th>
                  <th>Type</th>
                  <th>Payment Mode</th>
                  <th>Amount</th>
                  <th>Landlord Status</th>
                  <th class="text-end">Official Receipt</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($payments)): ?>
                  <tr>
                    <td colspan="7" class="text-center py-4 text-muted">No payment records found for your account.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($payments as $p): ?>
                    <?php
                      $isPending = $p['status'] === 'pending';
                      $isVerified = $p['status'] === 'verified';
                      $isRejected = $p['status'] === 'rejected';
                      $pType = $p['payment_type'] ?? 'rent';
                    ?>
                    <tr class="<?= $isPending ? 'table-warning' : '' ?>">
                      <td class="fw-bold text-navy font-monospace"><?= htmlspecialchars($p['reference_no']) ?></td>
                      <td><?= date('M j, Y', strtotime($p['payment_date'])) ?></td>
                      <td>
                        <?php if ($pType === 'advance'): ?>
                          <span class="badge badge-success text-white"><i class="bi bi-calendar-plus me-1"></i> Advance</span>
                        <?php elseif ($pType === 'partial'): ?>
                          <span class="badge badge-info text-white"><i class="bi bi-pie-chart me-1"></i> Partial</span>
                        <?php else: ?>
                          <span class="badge badge-secondary text-white">Rent</span>
                        <?php endif; ?>
                      </td>
                      <td><span class="badge badge-light border"><i class="bi bi-phone text-primary me-1"></i> <?= htmlspecialchars($p['payment_method']) ?></span></td>
                      <td class="fw-bold text-navy">₱<?= number_format($p['amount'], 2) ?></td>
                      <td>
                        <?php if ($isPending): ?>
                          <span class="badge-ebh badge-pending"><i class="bi bi-clock-history"></i> Pending Verification</span>
                        <?php elseif ($isVerified): ?>
                          <span class="badge-ebh badge-verified"><i class="bi bi-check-circle"></i> Verified & Paid</span>
                        <?php elseif ($isRejected): ?>
                          <span class="badge-ebh badge-rejected"><i class="bi bi-x-circle"></i> Rejected</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="previewReceiptModal('<?= htmlspecialchars($p['reference_no']) ?>', '<?= htmlspecialchars(addslashes($user['full_name'])) ?>', 'Unit <?= htmlspecialchars($user['unit_code']) ?>', '<?= $p['amount'] ?>', '<?= date('M j, Y', strtotime($p['payment_date'])) ?>', '<?= htmlspecialchars($p['payment_method']) ?>', '<?= htmlspecialchars($p['reference_no']) ?>', '<?= htmlspecialchars($p['proof_image'] ?? '') ?>', '<?= $p['status'] ?>', <?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['admin_notes'] ?? '')) ?>')">
                          <i class="bi bi-receipt me-1"></i> <?= $isVerified ? 'Receipt' : 'View' ?>
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Maintenance & Assistance Requests Module -->
        <div class="ebh-card">
          <div class="ebh-card-header">
            <div>
              <h6 class="fw-bold text-navy mb-0"><i class="bi bi-wrench-adjustable-circle-fill me-2 text-warning"></i> Maintenance & Assistance Service</h6>
              <small class="text-muted">Report room repairs, lighting issues, or compound concerns to the caretaker</small>
            </div>
          </div>
          <div class="ebh-card-body">
            <!-- Submit Form -->
            <form method="POST" action="tenant-dashboard.php" enctype="multipart/form-data" class="mb-4 p-3 bg-light rounded-3 border">
              <input type="hidden" name="action" value="create_ticket">
              <h6 class="fw-bold text-navy mb-3 small"><i class="bi bi-plus-circle me-1 text-primary"></i> Create Repair Request</h6>
              <div class="row g-3">
                <div class="col-md-6">
                  <label for="ticketCategory" class="form-label small fw-bold text-navy">Issue Category</label>
                  <select class="form-control" id="ticketCategory" name="category" required>
                    <option value="Plumbing">Plumbing & Water (Sink, Shower, Flush)</option>
                    <option value="Electrical">Electrical & Lighting (Bulb, Power Outlet)</option>
                    <option value="Carpentry">Door, Lock, or Cabinet Repair</option>
                    <option value="Internet">Compound Wi-Fi Connectivity</option>
                    <option value="House Rules">Quiet Hours / Neighbor Concern</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label for="ticketUrgency" class="form-label small fw-bold text-navy">Urgency Priority</label>
                  <select class="form-control" id="ticketUrgency" name="urgency">
                    <option value="Normal">Normal Priority (Within 24 Hours)</option>
                    <option value="Urgent">Urgent Priority (Immediate)</option>
                  </select>
                </div>
                <div class="col-12">
                  <label for="ticketDescription" class="form-label small fw-bold text-navy">Brief Description of the Problem</label>
                  <input type="text" class="form-control" id="ticketDescription" name="description" required placeholder="e.g. Bathroom sink faucet is dripping slightly.">
                </div>
                <div class="col-12">
                  <label for="tenantTicketImages" class="form-label small fw-bold text-navy"><i class="bi bi-camera me-1"></i> Attach Photos of Problem (Optional)</label>
                  <input type="file" class="form-control form-control-sm" id="tenantTicketImages" name="ticket_images[]" multiple accept="image/*">
                  <small class="text-muted">Take a clear photo of the broken item or leak (JPG, PNG, WebP).</small>
                </div>
                <div class="col-12 text-end">
                  <button type="submit" class="btn btn-sm btn-ebh-primary"><i class="bi bi-send-fill me-1"></i> Submit Ticket</button>
                </div>
              </div>
            </form>

            <!-- Active Tickets List -->
            <div id="tenantTicketList">
              <?php if (empty($tickets)): ?>
                <div class="text-center py-3 text-muted small"><i class="bi bi-check2-circle text-success me-1"></i>No active maintenance concerns logged.</div>
              <?php else: ?>
                <?php foreach ($tickets as $t): ?>
                  <div class="ticket-item mb-2 p-3 bg-white border rounded">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                      <span class="fw-bold small text-navy"><?= htmlspecialchars($t['category']) ?> Issue (#<?= htmlspecialchars($t['ticket_no']) ?>)</span>
                      <?php if ($t['status'] === 'resolved'): ?>
                        <span class="badge-ebh badge-verified"><i class="bi bi-check-circle"></i> Resolved</span>
                      <?php else: ?>
                        <span class="badge-ebh badge-pending"><i class="bi bi-clock"></i> Queued</span>
                      <?php endif; ?>
                    </div>
                    <p class="small text-muted mb-2"><?= htmlspecialchars($t['description']) ?></p>
                    <div class="d-flex align-items-center justify-content-between text-muted" style="font-size:0.75rem;">
                      <span><i class="bi bi-calendar3 me-1"></i> Logged <?= date('M j, Y', strtotime($t['created_at'])) ?></span>
                      <span><i class="bi bi-flag-fill me-1 text-<?= strtolower($t['urgency']) === 'urgent' ? 'danger' : 'secondary' ?>"></i> <?= htmlspecialchars($t['urgency']) ?> Priority</span>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column: Notice Board & Contacts -->
      <div class="col-lg-4">
        <!-- Bulletin Board -->
        <div class="ebh-card mb-4">
          <div class="ebh-card-header bg-navy text-white">
            <h6 class="fw-bold text-white mb-0"><i class="bi bi-megaphone-fill me-2 text-warning"></i> House Bulletins & Notices</h6>
          </div>
          <div class="ebh-card-body p-3">
            <div class="d-flex flex-column gap-3 small">
              <div class="p-2 rounded bg-light border-start border-primary border-3">
                <strong class="text-navy d-block mb-1"><i class="bi bi-shield-lock-fill text-primary me-1"></i> 10:00 PM Night Curfew</strong>
                <span class="text-muted">Compound front gates are locked at 10 PM. For late arrivals, notify Kuya Boy in advance.</span>
              </div>
              <div class="p-2 rounded bg-light border-start border-info border-3">
                <strong class="text-navy d-block mb-1"><i class="bi bi-droplet-half text-info me-1"></i> Water Tank Cleaning</strong>
                <span class="text-muted">Scheduled overhead tank flush on Saturday from 9 AM to 11 AM. Steady water resumes immediately after.</span>
              </div>
              <div class="p-2 rounded bg-light border-start border-success border-3">
                <strong class="text-navy d-block mb-1"><i class="bi bi-wifi text-success me-1"></i> Wi-Fi Password Update</strong>
                <span class="text-muted">Quarterly Wi-Fi security update: Network SSID <code>EBH-RESIDENT-5G</code> password provided in office.</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Caretaker & Landlord Emergency Contact Card -->
        <div class="ebh-card mb-4">
          <div class="ebh-card-header">
            <h6 class="fw-bold text-navy mb-0"><i class="bi bi-telephone-inbound-fill me-2 text-primary"></i> Compound Assistance</h6>
          </div>
          <div class="ebh-card-body">
            <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
              <div class="avatar-circle">KB</div>
              <div>
                <strong class="text-navy d-block">Kuya Boy (On-site Caretaker)</strong>
                <small class="text-muted d-block"><i class="bi bi-telephone me-1"></i> 0918-777-BOY1</small>
                <small class="text-muted">Unit 100 (Ground Floor Office)</small>
              </div>
            </div>
            <div class="d-flex align-items-center gap-3 mb-3">
              <div class="avatar-circle bg-navy">LL</div>
              <div>
                <strong class="text-navy d-block">Landlord Property Admin</strong>
                <small class="text-muted d-block"><i class="bi bi-telephone me-1"></i> 0917-555-EBH1</small>
                <small class="text-muted">Office Hours: 9 AM - 6 PM</small>
              </div>
            </div>
            <div class="d-grid gap-2">
              <a href="tel:09187772691" class="btn btn-outline-primary btn-sm"><i class="bi bi-telephone-fill me-1"></i> Call Caretaker</a>
            </div>
          </div>
        </div>

        <!-- Quick House Rules Reminder -->
        <div class="ebh-card">
          <div class="ebh-card-body p-3 small text-muted">
            <h6 class="fw-bold text-navy mb-2"><i class="bi bi-shield-check text-primary me-1"></i> Boarding House Rules</h6>
            <ul class="ps-3 mb-2">
              <li><strong>Max 3 pax:</strong> Strict 3 persons limit per room.</li>
              <li><strong>Quiet Hours:</strong> No loud noise past 10:00 PM.</li>
              <li><strong>No Pets:</strong> Strict no pets allowed policy.</li>
              <li><strong>CLAYGO:</strong> Clean As You Go in kitchen & common areas.</li>
            </ul>
            <a href="house-rules.php" class="fw-semibold text-primary">Read Complete House Rules &rarr;</a>
          </div>
        </div>
      </div>
    </div>
  </main>

  <!-- Modal: Upload Proof of Payment -->
  <div class="modal fade" id="tenantUploadModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-navy text-white">
          <h5 class="modal-title fw-bold text-white mb-0"><i class="bi bi-cloud-arrow-up-fill me-2 text-info"></i> Upload Proof of Payment</h5>
          <button type="button" class="close text-white border-0 bg-transparent" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form method="POST" action="tenant-dashboard.php" enctype="multipart/form-data">
          <input type="hidden" name="action" value="upload_payment">
          <div class="modal-body p-4">
            <!-- Payment Intent Selector -->
            <div class="mb-3">
              <label class="form-label small fw-bold text-navy mb-1">What are you paying for?</label>
              <div class="btn-group btn-group-toggle w-100 d-flex" data-toggle="buttons">
                <label class="btn btn-outline-primary btn-sm <?= $currentBalance <= 0 ? '' : 'active' ?> flex-fill" id="optTypeRent">
                  <input type="radio" name="payment_type" id="typeRent" value="rent" <?= $currentBalance <= 0 ? '' : 'checked' ?> onchange="updatePayModalHints('rent')"> Regular Rent
                </label>
                <label class="btn btn-outline-info btn-sm flex-fill" id="optTypePartial">
                  <input type="radio" name="payment_type" id="typePartial" value="partial" onchange="updatePayModalHints('partial')"> Partial / Whatever I Can
                </label>
                <?php if ($currentBalance <= 0): ?>
                  <label class="btn btn-outline-success btn-sm active flex-fill" id="optTypeAdvance">
                    <input type="radio" name="payment_type" id="typeAdvance" value="advance" checked onchange="updatePayModalHints('advance')"> Advance Payment
                  </label>
                <?php endif; ?>
              </div>
            </div>

            <!-- Quick Amount Shortcut Chips -->
            <div class="mb-3">
              <label class="form-label small fw-bold text-navy mb-1">Quick Amount Shortcuts</label>
              <div class="d-flex flex-wrap gap-1">
                <?php if ($overdueMetrics['is_eviction_risk']): ?>
                  <button type="button" class="btn btn-xs btn-danger fw-bold" onclick="setPayAmount(<?= $overdueMetrics['min_payment_required'] ?>)">
                    <i class="bi bi-shield-fill-exclamation me-1"></i> Mandatory 50% Min (₱<?= number_format($overdueMetrics['min_payment_required']) ?>)
                  </button>
                <?php endif; ?>
                <?php if ($currentBalance > 0): ?>
                  <button type="button" class="btn btn-xs btn-outline-secondary" onclick="setPayAmount(<?= $currentBalance ?>)">
                    <i class="bi bi-tag-fill me-1"></i> Full Balance (₱<?= number_format($currentBalance) ?>)
                  </button>
                  <?php if (!$overdueMetrics['is_eviction_risk'] && $currentBalance > 1000): ?>
                    <button type="button" class="btn btn-xs btn-outline-secondary" onclick="setPayAmount(<?= round($currentBalance / 2) ?>)">
                      <i class="bi bi-pie-chart me-1"></i> Half (₱<?= number_format(round($currentBalance / 2)) ?>)
                    </button>
                  <?php endif; ?>
                <?php endif; ?>
                <button type="button" class="btn btn-xs btn-outline-secondary" onclick="setPayAmount(<?= $monthlyRent ?>)">
                  <i class="bi bi-calendar me-1"></i> 1 Month (₱<?= number_format($monthlyRent) ?>)
                </button>
                <button type="button" class="btn btn-xs btn-outline-secondary" onclick="setPayAmount(<?= $monthlyRent * 2 ?>)">
                  <i class="bi bi-calendar-plus me-1"></i> 2 Mos Adv (₱<?= number_format($monthlyRent * 2) ?>)
                </button>
                <button type="button" class="btn btn-xs btn-outline-secondary" onclick="setPayAmount(1000)">
                  ₱1,000
                </button>
                <button type="button" class="btn btn-xs btn-outline-secondary" onclick="setPayAmount(500)">
                  ₱500
                </button>
              </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label for="payAmount" class="form-label small fw-bold text-navy">Amount to Pay (₱)</label>
                <input type="number" step="0.01" 
                  min="<?= $overdueMetrics['is_eviction_risk'] ? $overdueMetrics['min_payment_required'] : 1 ?>" 
                  class="form-control <?= $overdueMetrics['is_eviction_risk'] ? 'border-danger' : '' ?>" 
                  id="payAmount" name="amount" required 
                  value="<?= $overdueMetrics['is_eviction_risk'] ? $overdueMetrics['min_payment_required'] : ($currentBalance > 0 ? $currentBalance : $user['monthly_rent']) ?>">
                <small class="<?= $overdueMetrics['is_eviction_risk'] ? 'text-danger fw-bold' : 'text-muted' ?>" id="payAmountHint">
                  <?= $overdueMetrics['is_eviction_risk'] 
                    ? '<i class="bi bi-exclamation-triangle-fill me-1"></i> 3+ months non-payment. Minimum 50% required: ₱' . number_format($overdueMetrics['min_payment_required'], 2) 
                    : 'Enter any amount you can pay today.' ?>
                </small>
              </div>
              <div class="col-md-6">
                <label for="payRefNumber" class="form-label small fw-bold text-navy">Reference / Trans #</label>
                <input type="text" class="form-control" id="payRefNumber" name="reference_no" required placeholder="e.g. 992813401">
                <small class="text-muted">From your GCash / Bank receipt</small>
              </div>
            </div>

            <div class="mb-3">
              <label for="payMethodSelect" class="form-label small fw-bold text-navy">Payment Method Used</label>
              <select class="form-control" id="payMethodSelect" name="payment_method" required>
                <option value="GCash">GCash (0917-555-EBH1)</option>
                <option value="Maya">Maya QR / Phone</option>
                <option value="BPI Online">BPI Online Transfer</option>
                <option value="BDO Deposit">BDO Bank Transfer</option>
                <option value="Cash">Cash Handed to Caretaker</option>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-bold text-navy">Attach Screenshot / Photo Receipt</label>
              <input type="file" class="form-control" id="paymentReceiptFile" name="proof_image" accept="image/*">
              <small class="text-muted">Accepted formats: JPG, PNG, WEBP screenshot file.</small>
            </div>

            <div class="alert alert-light border small text-muted mb-0">
              <i class="bi bi-shield-check text-success me-1"></i> <strong>Admin Approval Policy:</strong> Pay whatever you can or advance. The landlord administrator reviews and approves all payments. Once approved, the exact amount will reduce your outstanding balance. Any excess automatically credits to your advance payment.
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-ebh-primary btn-sm"><i class="bi bi-check2-circle me-1"></i> Submit Payment for Admin Approval</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal: Payment Channels & QR Codes -->
  <div class="modal fade" id="paymentChannelsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-navy text-white">
          <h5 class="modal-title fw-bold text-white mb-0"><i class="bi bi-qr-code me-2"></i> Official EBH Payment Channels</h5>
          <button type="button" class="close text-white border-0 bg-transparent" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body p-4">
          <div class="text-center mb-4">
            <div class="p-3 bg-light border rounded d-inline-block shadow-sm mb-2">
              <i class="bi bi-qr-code-scan display-3 text-navy"></i>
            </div>
            <h6 class="fw-bold text-navy mb-0">GCash Express QR</h6>
            <span class="text-muted small">Account: Eanne Boarding House (0917-555-3241)</span>
          </div>

          <div class="d-flex flex-column gap-2 small">
            <div class="p-3 border rounded d-flex justify-content-between align-items-center">
              <div>
                <strong class="text-navy d-block">BPI Savings Account</strong>
                <span class="text-muted">Account No: <code>1920-4491-02</code></span>
              </div>
              <button class="btn btn-sm btn-outline-primary" onclick="copyToClipboard('1920-4491-02', 'BPI Account')"><i class="bi bi-clipboard"></i> Copy</button>
            </div>
            <div class="p-3 border rounded d-flex justify-content-between align-items-center">
              <div>
                <strong class="text-navy d-block">BDO Savings Account</strong>
                <span class="text-muted">Account No: <code>0042-8819-2041</code></span>
              </div>
              <button class="btn btn-sm btn-outline-primary" onclick="copyToClipboard('0042-8819-2041', 'BDO Account')"><i class="bi bi-clipboard"></i> Copy</button>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Full jQuery 3.7.1, Popper.js, Bootstrap 4.1.3 JS -->
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.14.3/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.1.3/dist/js/bootstrap.min.js"></script>
  <script src="../assets/js/script.js"></script>
  <script>
    function setPayAmount(amt) {
      $('#payAmount').val(amt);
    }

    function openPaymentModalWithType(type) {
      if (type === 'advance') {
        $('#typeAdvance').prop('checked', true).trigger('change');
        $('#optTypeAdvance').addClass('active').siblings().removeClass('active');
        updatePayModalHints('advance');
      } else if (type === 'partial') {
        $('#typePartial').prop('checked', true).trigger('change');
        $('#optTypePartial').addClass('active').siblings().removeClass('active');
        updatePayModalHints('partial');
      } else {
        $('#typeRent').prop('checked', true).trigger('change');
        $('#optTypeRent').addClass('active').siblings().removeClass('active');
        updatePayModalHints('rent');
      }
      $('#tenantUploadModal').modal('show');
    }

    function updatePayModalHints(type) {
      const curBal = <?= json_encode($currentBalance) ?>;
      const rent = <?= json_encode($monthlyRent) ?>;
      const $hint = $('#payAmountHint');
      const $amt = $('#payAmount');

      if (type === 'advance') {
        $hint.html('<span class="text-success"><i class="bi bi-shield-check me-1"></i> <strong>Advance Payment:</strong> Any amount paid in advance will be stored as an advance credit on your account and reduce future rent.</span>');
        if (parseFloat($amt.val()) <= 0 || parseFloat($amt.val()) === curBal) {
          $amt.val(rent);
        }
      } else if (type === 'partial') {
        $hint.html('<span class="text-info"><i class="bi bi-info-circle me-1"></i> <strong>Partial Payment:</strong> Pay whatever you can afford. The admin will verify it and reduce your balance accordingly.</span>');
        if (curBal > 0 && parseFloat($amt.val()) === rent) {
          $amt.val(Math.round(curBal / 2));
        }
      } else {
        $hint.html('<span class="text-muted">Enter the amount you are paying today.</span>');
        if (curBal > 0) {
          $amt.val(curBal);
        } else {
          $amt.val(rent);
        }
      }
    }
  </script>
</body>
</html>
