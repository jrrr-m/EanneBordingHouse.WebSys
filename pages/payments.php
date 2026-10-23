<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../backend/db.php';
$pdo = getDbConnection();

$msg = $_GET['msg'] ?? '';

// Handle Direct POST Actions on Payments
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Record Walk-in Cash Payment
    if ($action === 'record_cash') {
        $tenantName = trim($_POST['tenant_name'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $notes = trim($_POST['notes'] ?? 'Walk-in cash payment received over-the-counter.');
        $paymentType = strtolower(trim($_POST['payment_type'] ?? 'rent'));
        if (!in_array($paymentType, ['rent', 'partial', 'advance'])) $paymentType = 'rent';
        
        $uStmt = $pdo->prepare("SELECT id, unit_code FROM users WHERE full_name = ? LIMIT 1");
        $uStmt->execute([$tenantName]);
        $u = $uStmt->fetch();
        $unitCode = $u ? $u['unit_code'] : 'BH-01';
        $tenantId = $u ? $u['id'] : null;

        $refNo = 'CSH-' . date('ymd') . '-' . rand(100, 999);
        $adminName = $_SESSION['user']['full_name'] ?? 'Landlord Admin';

        $stmt = $pdo->prepare("
            INSERT INTO payments (reference_no, tenant_id, tenant_name, unit_code, amount, payment_method, payment_type, payment_date, status, verified_by, verified_at, admin_notes)
            VALUES (?, ?, ?, ?, ?, 'Cash', ?, NOW(), 'verified', ?, NOW(), ?)
        ");
        $stmt->execute([$refNo, $tenantId, $tenantName, $unitCode, $amount, $paymentType, $adminName, $notes]);

        // Reduce balance
        if ($tenantId) {
            $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$amount, $tenantId]);
        } else {
            $pdo->prepare("UPDATE users SET balance = balance - ? WHERE full_name = ?")->execute([$amount, $tenantName]);
        }

        header("Location: payments.php?msg=" . urlencode("Walk-in Cash Payment #{$refNo} (₱" . number_format($amount, 2) . ") recorded and tenant balance updated."));
        exit;
    }

    // 2. Approve Payment
    if ($action === 'approve_payment') {
        $paymentId = (int)($_POST['payment_id'] ?? 0);
        if ($paymentId > 0) {
            // Check current status and amount
            $pCheck = $pdo->prepare("SELECT tenant_id, tenant_name, amount, status FROM payments WHERE id = ?");
            $pCheck->execute([$paymentId]);
            $paymentRow = $pCheck->fetch();

            $adminName = $_SESSION['user']['full_name'] ?? 'Landlord Admin';
            $stmt = $pdo->prepare("
                UPDATE payments 
                SET status = 'verified', verified_by = ?, verified_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$adminName, $paymentId]);

            if ($paymentRow && $paymentRow['status'] !== 'verified') {
                $payAmount = (float)$paymentRow['amount'];
                if (!empty($paymentRow['tenant_id'])) {
                    $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$payAmount, $paymentRow['tenant_id']]);
                } elseif (!empty($paymentRow['tenant_name'])) {
                    $pdo->prepare("UPDATE users SET balance = balance - ? WHERE full_name = ?")->execute([$payAmount, $paymentRow['tenant_name']]);
                }
            }

            header("Location: payments.php?msg=" . urlencode("Payment #{$paymentId} verified and credited to tenant balance."));
            exit;
        }
    }

    // 3. Reject Payment with Reason
    if ($action === 'reject_payment') {
        $paymentId = (int)($_POST['payment_id'] ?? 0);
        $reason = trim($_POST['rejection_reason'] ?? '');
        if ($paymentId > 0) {
            $adminName = $_SESSION['user']['full_name'] ?? 'Landlord Admin';
            $rejectNote = "Rejected by {$adminName} - " . ($reason ?: 'Proof of payment rejected.');
            $stmt = $pdo->prepare("
                UPDATE payments 
                SET status = 'rejected', admin_notes = ?, verified_by = ?, verified_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$rejectNote, $adminName, $paymentId]);
            header("Location: payments.php?msg=" . urlencode("Payment #{$paymentId} marked as rejected in MySQL."));
            exit;
        }
    }
}

// Fetch All Payments
$paymentsStmt = $pdo->query("
    SELECT p.*, COALESCE(u.phone, '') AS tenant_phone, COALESCE(r.room_type, 'Boarding Rental') AS room_type
    FROM payments p
    LEFT JOIN users u ON p.tenant_name = u.full_name
    LEFT JOIN rooms r ON p.unit_code = r.unit_code
    ORDER BY p.id DESC
");
$payments = $paymentsStmt->fetchAll();

// Counts for filter pills
$totalPayments = count($payments);
$pendingCount = 0;
$verifiedCount = 0;
$rejectedCount = 0;

foreach ($payments as $p) {
    if ($p['status'] === 'pending') $pendingCount++;
    if ($p['status'] === 'verified') $verifiedCount++;
    if ($p['status'] === 'rejected') $rejectedCount++;
}

// Active tenants for walk-in cash modal dropdown
$activeTenants = $pdo->query("
    SELECT id, full_name, unit_code, monthly_rent, balance 
    FROM users 
    WHERE role = 'tenant' AND lease_status = 'active'
    ORDER BY full_name ASC
")->fetchAll();

$openTicketsCount = (int)$pdo->query("SELECT COUNT(*) FROM maintenance_tickets WHERE status = 'open'")->fetchColumn();
$adminUser = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payments Verification Queue | Eanne Admin Console</title>
  <meta name="description" content="Digital rent verification queue, GCash reference reconciliation, and payment ledgers.">
  
  <!-- Bootstrap 4.1.3 CSS & Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.1.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <!-- Custom Design System -->
  <link rel="stylesheet" href="../assets/css/style.css">
  <!-- Business Logo Favicon -->
  <link rel="icon" type="image/png" href="../assets/img/favicon.png">
  <link rel="apple-touch-icon" href="../assets/img/favicon.png">
</head>
<body>

  <div class="dashboard-wrapper">
    <!-- Sticky Sidebar -->
    <aside class="dashboard-sidebar">
      <div class="sidebar-header">
        <a class="ebh-brand dark" href="../index.php">
          <div class="ebh-logo-mark" title="Eanne Boarding House">
            <img src="../assets/img/795286329_1754004695838265_6753877283478212113_n.png" alt="EBH Logo">
          </div>
          <div class="ebh-brand-text">
            <span class="ebh-brand-title text-white">Eanne Admin</span>
            <span class="ebh-brand-subtitle">Boarding House</span>
          </div>
        </a>
      </div>

      <nav class="sidebar-nav">
        <a href="admin-dashboard.php" class="sidebar-link"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
        <a href="tenants.php" class="sidebar-link"><i class="bi bi-people-fill"></i><span>Tenants Roster</span></a>
        <a href="rooms.php" class="sidebar-link"><i class="bi bi-door-open-fill"></i><span>Rooms & Houses</span></a>
        <a href="payments.php" class="sidebar-link active"><i class="bi bi-credit-card-2-front-fill"></i><span>Payments Queue</span> <span class="badge <?= $pendingCount > 0 ? 'badge-warning text-dark' : 'badge-success text-white' ?> ml-auto"><?= $pendingCount ?></span></a>
        <a href="tickets.php" class="sidebar-link"><i class="bi bi-tools"></i><span>Maintenance Tickets</span> <span class="badge <?= $openTicketsCount > 0 ? 'badge-danger text-white' : 'badge-secondary' ?> ml-auto"><?= $openTicketsCount ?></span></a>
        <a href="reports.php" class="sidebar-link"><i class="bi bi-file-earmark-bar-graph-fill"></i><span>Reports & Ledgers</span></a>
      </nav>

      <div class="sidebar-footer">
        <div class="d-flex align-items-center gap-2 mb-3">
          <div class="avatar-circle sm">AD</div>
          <div class="overflow-hidden">
            <div class="small fw-bold text-white text-truncate"><?= htmlspecialchars($adminUser['full_name']) ?></div>
            <div class="small text-white-50 text-truncate" style="font-size:0.75rem;"><?= htmlspecialchars($adminUser['email'] ?? 'admin@eanneboarding.com') ?></div>
          </div>
        </div>
        <a href="login.php?logout=1" class="btn btn-outline-light btn-sm w-100"><i class="bi bi-box-arrow-left me-1"></i> Sign Out</a>
      </div>
    </aside>

    <!-- Main Content Area -->
    <div class="dashboard-main">
      <header class="dashboard-topbar">
        <div class="d-flex align-items-center gap-3">
          <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" id="sidebarToggleBtn"><i class="bi bi-list fs-5"></i></button>
          <div>
            <h5 class="mb-0 fw-bold text-navy">Rent Payment Verification Queue</h5>
            <small class="text-muted"><span class="fw-bold text-primary"><?= $totalPayments ?></span> Total Payment Transactions</small>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <button type="button" class="btn btn-sm btn-ebh-primary" data-toggle="modal" data-target="#recordCashModal">
            <i class="bi bi-cash-coin me-1"></i> Record Cash Payment
          </button>
          <a href="admin-dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Dashboard</a>
        </div>
      </header>

      <main class="dashboard-content">
        <?php if (!empty($msg)): ?>
          <div class="alert alert-success alert-dismissible fade show shadow-sm border-success py-2 px-3 mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($msg) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        <?php endif; ?>

        <!-- Control Bar: Search & Filter Pills -->
        <div class="ebh-card p-3 mb-4">
          <div class="row g-3 align-items-center justify-content-between">
            <div class="col-md-5 col-lg-4">
              <div class="input-group">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control border-start-0" id="paymentSearchInput" placeholder="Search reference #, tenant, or room...">
              </div>
            </div>
            <div class="col-md-7 col-lg-8">
              <div class="btn-filter-group justify-content-md-end">
                <button type="button" class="btn-filter active" data-filter="all" data-target-items="#paymentsTable tbody tr">All Receipts (<?= $totalPayments ?>)</button>
                <button type="button" class="btn-filter" data-filter="pending" data-target-items="#paymentsTable tbody tr"><i class="bi bi-clock-history text-warning"></i> Pending (<?= $pendingCount ?>)</button>
                <button type="button" class="btn-filter" data-filter="verified" data-target-items="#paymentsTable tbody tr"><i class="bi bi-check-circle text-success"></i> Verified (<?= $verifiedCount ?>)</button>
                <button type="button" class="btn-filter" data-filter="rejected" data-target-items="#paymentsTable tbody tr"><i class="bi bi-x-circle text-danger"></i> Rejected (<?= $rejectedCount ?>)</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Payments Table Rendered Directly from MySQL -->
        <div class="ebh-card">
          <div class="table-responsive">
            <table class="table table-ebh table-hover mb-0" id="paymentsTable">
              <thead>
                <tr>
                  <th>Reference #</th>
                  <th>Tenant & Unit</th>
                  <th>Type</th>
                  <th>Amount</th>
                  <th>Payment Mode</th>
                  <th>Date Logged</th>
                  <th>Verification Status</th>
                  <th class="text-end">Review Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($payments)): ?>
                  <tr>
                    <td colspan="8" class="text-center py-4 text-muted">No payment transactions found in database.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($payments as $p): ?>
                    <?php
                      $isPending = $p['status'] === 'pending';
                      $isVerified = $p['status'] === 'verified';
                      $isRejected = $p['status'] === 'rejected';
                      $pType = $p['payment_type'] ?? 'rent';
                      $filterCat = $p['status'] . ' ' . strtolower($p['payment_method']) . ' ' . $pType;
                      $initials = strtoupper(substr($p['tenant_name'], 0, 1) . substr(strrchr($p['tenant_name'], ' ') ?: '', 1, 1));
                      if (!$initials) $initials = 'TN';
                    ?>
                    <tr class="filterable-item <?= $isPending ? 'table-warning' : '' ?>" data-category="<?= $filterCat ?>" data-status="<?= $p['status'] ?>">
                      <td class="fw-bold text-navy font-monospace"><?= htmlspecialchars($p['reference_no']) ?></td>
                      <td>
                        <div class="d-flex align-items-center gap-2">
                          <div class="avatar-circle sm"><?= htmlspecialchars($initials) ?></div>
                          <div>
                            <span class="fw-bold text-navy d-block"><?= htmlspecialchars($p['tenant_name']) ?></span>
                            <small class="text-muted">Unit <?= htmlspecialchars($p['unit_code']) ?></small>
                          </div>
                        </div>
                      </td>
                      <td>
                        <?php if ($pType === 'advance'): ?>
                          <span class="badge badge-success text-white"><i class="bi bi-calendar-plus me-1"></i> Advance</span>
                        <?php elseif ($pType === 'partial'): ?>
                          <span class="badge badge-info text-white"><i class="bi bi-pie-chart me-1"></i> Partial</span>
                        <?php else: ?>
                          <span class="badge badge-secondary text-white">Rent</span>
                        <?php endif; ?>
                      </td>
                      <td class="fw-bold text-navy">₱<?= number_format($p['amount'], 2) ?></td>
                      <td>
                        <span class="badge badge-light border">
                          <i class="bi bi-credit-card text-primary me-1"></i> <?= htmlspecialchars($p['payment_method']) ?>
                        </span>
                      </td>
                      <td><small class="text-muted"><?= date('M j, Y g:i A', strtotime($p['payment_date'])) ?></small></td>
                      <td>
                        <?php if ($isPending): ?>
                          <span class="badge-ebh badge-pending"><i class="bi bi-clock-history"></i> Pending Verification</span>
                        <?php elseif ($isVerified): ?>
                          <span class="badge-ebh badge-verified"><i class="bi bi-check-circle"></i> Verified & Cleared</span>
                        <?php elseif ($isRejected): ?>
                          <span class="badge-ebh badge-rejected"><i class="bi bi-x-circle"></i> Rejected</span>
                          <?php if (!empty($p['admin_notes'])): ?>
                            <small class="d-block text-danger mt-1 text-truncate" style="max-width:180px;" title="<?= htmlspecialchars($p['admin_notes']) ?>"><?= htmlspecialchars($p['admin_notes']) ?></small>
                          <?php endif; ?>
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <?php if ($isPending): ?>
                          <div class="btn-group btn-group-sm">
                            <form method="POST" action="payments.php" class="d-inline" onsubmit="return confirm('Approve Payment #<?= htmlspecialchars($p['reference_no']) ?> as Verified & Cleared?');">
                              <input type="hidden" name="action" value="approve_payment">
                              <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                              <button type="submit" class="btn btn-success" title="Approve & Mark Verified"><i class="bi bi-check-lg"></i> Approve</button>
                            </form>
                            <button type="button" class="btn btn-outline-danger" title="Reject Payment" onclick="openRejectModal(<?= $p['id'] ?>, '<?= htmlspecialchars($p['reference_no']) ?>', '<?= htmlspecialchars(addslashes($p['tenant_name'])) ?>', '₱<?= number_format($p['amount'], 2) ?>', 'Unit <?= htmlspecialchars($p['unit_code']) ?>')">
                              <i class="bi bi-x-lg"></i>
                            </button>
                          </div>
                        <?php else: ?>
                          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="previewReceiptModal('<?= htmlspecialchars($p['reference_no']) ?>', '<?= htmlspecialchars(addslashes($p['tenant_name'])) ?>', 'Unit <?= htmlspecialchars($p['unit_code']) ?>', '<?= $p['amount'] ?>', '<?= date('M j, Y', strtotime($p['payment_date'])) ?>', '<?= htmlspecialchars($p['payment_method']) ?>', '<?= htmlspecialchars($p['reference_no']) ?>', '<?= htmlspecialchars($p['proof_image'] ?? '') ?>', '<?= $p['status'] ?>', <?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['admin_notes'] ?? '')) ?>')">
                            <i class="bi bi-receipt me-1"></i> Receipt
                          </button>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </main>
    </div>
  </div>

  <!-- Modal: Record Walk-in Cash Payment -->
  <div class="modal fade" id="recordCashModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-navy text-white">
          <h5 class="modal-title fw-bold text-white mb-0"><i class="bi bi-cash-coin me-2"></i> Record Walk-in Cash Payment</h5>
          <button type="button" class="close text-white border-0 bg-transparent" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form method="POST" action="payments.php">
          <input type="hidden" name="action" value="record_cash">
          <div class="modal-body p-4">
            <div class="row g-3">
              <div class="col-12">
                <label for="cashTenantSelect" class="form-label small fw-bold text-navy">Tenant</label>
                <select class="form-control" id="cashTenantSelect" name="tenant_name" required onchange="
                  const opt = this.options[this.selectedIndex];
                  const rent = opt.getAttribute('data-rent');
                  const bal = opt.getAttribute('data-balance');
                  if (bal !== null) {
                    document.getElementById('cashAmountInput').value = parseFloat(bal) > 0 ? bal : rent;
                    document.getElementById('cashBalanceBadge').innerHTML = 'Current Balance: <strong>₱' + parseFloat(bal).toLocaleString(undefined, {minimumFractionDigits:2}) + '</strong>';
                  } else if (rent) {
                    document.getElementById('cashAmountInput').value = rent;
                  }
                ">
                  <option value="" disabled selected>Choose active tenant...</option>
                  <?php foreach ($activeTenants as $at): ?>
                    <?php $atBal = (float)($at['balance'] ?? $at['monthly_rent']); ?>
                    <option value="<?= htmlspecialchars($at['full_name']) ?>" data-rent="<?= $at['monthly_rent'] ?>" data-balance="<?= $atBal ?>">
                      <?= htmlspecialchars($at['full_name']) ?> - Unit <?= htmlspecialchars($at['unit_code']) ?> (Rent: ₱<?= number_format($at['monthly_rent']) ?> | Bal: ₱<?= number_format($atBal) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
                <small class="text-muted d-block mt-1" id="cashBalanceBadge">Select a tenant to view their live balance.</small>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-bold text-navy">Payment Purpose / Type</label>
                <select class="form-control" name="payment_type" id="cashPaymentType">
                  <option value="rent">Regular Rent</option>
                  <option value="partial">Partial Payment (Whatever they can)</option>
                  <option value="advance">Advance Payment (Credit future rent)</option>
                </select>
              </div>
              <div class="col-md-6">
                <label for="cashAmountInput" class="form-label small fw-bold text-navy">Amount Received (₱)</label>
                <input type="number" step="0.01" min="1" class="form-control" id="cashAmountInput" name="amount" required value="3500">
              </div>
              <div class="col-12">
                <label class="form-label small fw-bold text-navy">Notes / Remarks</label>
                <input type="text" class="form-control" id="cashNotesInput" name="notes" placeholder="e.g. Paid in full / partial / advance payment received">
              </div>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-ebh-primary btn-sm"><i class="bi bi-check-circle me-1"></i> Issue Receipt & Clear</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal: Reject Payment Verification -->
  <div class="modal fade" id="rejectPaymentModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-danger text-white">
          <h6 class="modal-title fw-bold text-white mb-0"><i class="bi bi-x-circle me-2"></i> Reject Payment Verification</h6>
          <button type="button" class="close text-white border-0 bg-transparent" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form method="POST" action="payments.php">
          <input type="hidden" name="action" value="reject_payment">
          <input type="hidden" id="rejectPaymentId" name="payment_id" value="">
          <div class="modal-body p-4 bg-light">
            <div class="card border mb-3 shadow-sm bg-white">
              <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <span class="badge badge-secondary px-2 py-1 mb-1" id="rejectPaymentRef">REF</span>
                    <h6 class="fw-bold text-navy mb-0" id="rejectTenantName">Tenant</h6>
                    <small class="text-muted d-block" id="rejectUnitCode">Unit</small>
                  </div>
                  <div class="text-end">
                    <span class="text-muted small d-block">Amount Due</span>
                    <strong class="fs-5 text-danger" id="rejectAmount">₱0.00</strong>
                  </div>
                </div>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-bold text-navy mb-1">Select Preset Reason</label>
              <select class="form-control form-control-sm mb-2" onchange="if(this.value && this.value !== 'custom') document.getElementById('rejectReasonText').value = this.value;">
                <option value="">-- Choose a standard reason (optional) --</option>
                <option value="Unclear or blurry proof of payment. Transaction reference number and amount are unreadable.">Unclear or blurry receipt screenshot</option>
                <option value="Reference / Transaction number does not match the uploaded proof of payment.">Reference number mismatch</option>
                <option value="Duplicate submission. This transaction receipt was already recorded or claimed.">Duplicate receipt submission</option>
                <option value="Payment not received in owner's GCash / BPI bank account. Please check recipient number.">Payment not found in landlord account</option>
                <option value="custom">Other / Custom reason</option>
              </select>

              <label for="rejectReasonText" class="form-label small fw-bold text-navy mb-1">Reason for Rejection <span class="text-danger">*</span></label>
              <textarea class="form-control" id="rejectReasonText" name="rejection_reason" rows="3" required placeholder="Type or select the reason why this payment is being rejected..."></textarea>
            </div>
          </div>
          <div class="modal-footer bg-white border-top">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-x-circle me-1"></i> Confirm Rejection</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Full jQuery 3.7.1, Popper.js, Bootstrap 4.1.3 JS -->
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.14.3/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.1.3/dist/js/bootstrap.min.js"></script>
  <script src="../assets/js/script.js"></script>
  <script>
    function openRejectModal(id, ref, tenant, amount, unit) {
      $('#rejectPaymentId').val(id);
      $('#rejectPaymentRef').text(ref);
      $('#rejectTenantName').text(tenant);
      $('#rejectAmount').text(amount);
      $('#rejectUnitCode').text(unit);
      $('#rejectReasonText').val('');
      $('#rejectPaymentModal').modal('show');
    }
  </script>
</body>
</html>
