<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../backend/db.php';
$pdo = getDbConnection();

// Direct Native CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $payments = $pdo->query("SELECT * FROM payments ORDER BY id DESC")->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=eanne_boarding_house_financial_report_' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Eanne Boarding House - Monthly Financial Report & Operating Ledger']);
    fputcsv($out, ['Generated At: ' . date('Y-m-d H:i:s'), 'Location: Soldiers Village, Pasig City']);
    fputcsv($out, []);
    fputcsv($out, ['Reference #', 'Date Paid', 'Tenant Name', 'Unit Code', 'Payment Method', 'Amount (PHP)', 'Status', 'Verified By']);
    foreach ($payments as $p) {
        fputcsv($out, [
            $p['reference_no'],
            $p['payment_date'],
            $p['tenant_name'],
            $p['unit_code'],
            $p['payment_method'],
            $p['amount'],
            strtoupper($p['status']),
            $p['verified_by'] ?? 'Pending'
        ]);
    }
    fclose($out);
    exit;
}

// Fetch Financial KPIs
$monthlyCapacity = (float)$pdo->query("SELECT COALESCE(SUM(monthly_rate), 0) FROM rooms")->fetchColumn();
$verifiedTotal = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'verified'")->fetchColumn();
$totalUnits = (int)$pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$occupiedUnits = (int)$pdo->query("SELECT COUNT(*) FROM rooms WHERE status = 'occupied'")->fetchColumn();
$availableUnits = (int)$pdo->query("SELECT COUNT(*) FROM rooms WHERE status = 'available'")->fetchColumn();
$collectionRate = $monthlyCapacity > 0 ? min(100, round(($verifiedTotal / $monthlyCapacity) * 100)) : 100;

// Fetch Master Ledger
$paymentsStmt = $pdo->query("
    SELECT p.*, COALESCE(r.room_type, 'Boarding House Rental') AS room_type
    FROM payments p
    LEFT JOIN rooms r ON p.unit_code = r.unit_code
    ORDER BY p.id DESC
");
$payments = $paymentsStmt->fetchAll();
$totalLedger = count($payments);

$pendingQueueCount = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
$openTicketsCount = (int)$pdo->query("SELECT COUNT(*) FROM maintenance_tickets WHERE status = 'open'")->fetchColumn();
$adminUser = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports & Ledgers | Eanne Admin Console</title>
  <meta name="description" content="Financial reports, rent collection efficiency, utility expenses, and master cash ledgers.">
  
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
        <a href="payments.php" class="sidebar-link"><i class="bi bi-credit-card-2-front-fill"></i><span>Payments Queue</span> <span class="badge <?= $pendingQueueCount > 0 ? 'badge-warning text-dark' : 'badge-success text-white' ?> ml-auto"><?= $pendingQueueCount ?></span></a>
        <a href="tickets.php" class="sidebar-link"><i class="bi bi-tools"></i><span>Maintenance Tickets</span> <span class="badge <?= $openTicketsCount > 0 ? 'badge-danger text-white' : 'badge-secondary' ?> ml-auto"><?= $openTicketsCount ?></span></a>
        <a href="reports.php" class="sidebar-link active"><i class="bi bi-file-earmark-bar-graph-fill"></i><span>Reports & Ledgers</span></a>
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
            <h5 class="mb-0 fw-bold text-navy">Financial Reports & Operating Ledgers</h5>
            <small class="text-muted"><?= date('F Y') ?> Billing Period • Soldiers Village, Pasig City</small>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <a href="reports.php?export=csv" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-download me-1"></i> Export CSV
          </a>
          <button type="button" class="btn btn-sm btn-ebh-primary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print Statement
          </button>
        </div>
      </header>

      <main class="dashboard-content">
        <!-- 4 Financial KPI Stat Cards -->
        <div class="row g-3 mb-4">
          <div class="col-sm-6 col-xl-3">
            <div class="kpi-card">
              <div class="kpi-icon blue"><i class="bi bi-cash-stack"></i></div>
              <div>
                <div class="kpi-title">Monthly Capacity</div>
                <div class="kpi-value text-navy">₱<?= number_format($monthlyCapacity, 2) ?></div>
                <small class="text-success fw-semibold"><i class="bi bi-check-circle"></i> <?= $totalUnits ?> Units Gross Rent</small>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-xl-3">
            <div class="kpi-card">
              <div class="kpi-icon navy"><i class="bi bi-percent"></i></div>
              <div>
                <div class="kpi-title">Collection Rate</div>
                <div class="kpi-value text-navy"><?= $collectionRate ?>%</div>
                <small class="text-muted">Active units current</small>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-xl-3">
            <div class="kpi-card">
              <div class="kpi-icon warning"><i class="bi bi-receipt-cutoff"></i></div>
              <div>
                <div class="kpi-title">Verified Collections</div>
                <div class="kpi-value text-navy">₱<?= number_format($verifiedTotal, 2) ?></div>
                <small class="text-success fw-semibold"><i class="bi bi-shield-check"></i> Recorded in MySQL</small>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-xl-3">
            <div class="kpi-card">
              <div class="kpi-icon accent"><i class="bi bi-houses-fill"></i></div>
              <div>
                <div class="kpi-title">Compound Units</div>
                <div class="kpi-value text-navy"><?= $totalUnits ?> Total</div>
                <small class="text-success fw-semibold"><i class="bi bi-house-check"></i> <?= $occupiedUnits ?> Occupied &bull; <?= $availableUnits ?> Avail</small>
              </div>
            </div>
          </div>
        </div>

        <!-- 2 Column Financial Breakdown -->
        <div class="row g-4 mb-4">
          <!-- Collections Category Analysis -->
          <div class="col-lg-6">
            <div class="ebh-card h-100">
              <div class="ebh-card-header">
                <h6 class="fw-bold text-navy mb-0"><i class="bi bi-pie-chart-fill me-2 text-primary"></i> Revenue by Room Type (Capacity: ₱<?= number_format($monthlyCapacity) ?>/mo)</h6>
                <span class="badge badge-light border"><?= date('F Y') ?></span>
              </div>
              <div class="ebh-card-body">
                <div class="mb-4">
                  <div class="d-flex justify-content-between small fw-bold mb-1">
                    <span>4 Boarding House Rentals (₱3,500 / room)</span>
                    <span class="text-navy">₱14,000 max capacity</span>
                  </div>
                  <div class="progress" style="height: 12px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: 100%;"></div>
                  </div>
                  <small class="text-muted mt-1 d-block">Room + kitchen area, shared comfort room. Max 3 pax per room.</small>
                </div>

                <div>
                  <div class="d-flex justify-content-between small fw-bold mb-1">
                    <span>2 Private Boarding Houses (₱8,000 / house)</span>
                    <span class="text-navy">₱16,000 max capacity</span>
                  </div>
                  <div class="progress" style="height: 12px;">
                    <div class="progress-bar bg-warning" role="progressbar" style="width: 100%;"></div>
                  </div>
                  <small class="text-muted mt-1 d-block">Individual private family homes, no shared common areas.</small>
                </div>
              </div>
            </div>
          </div>

          <!-- Compound Inclusions & Overhead Policy -->
          <div class="col-lg-6">
            <div class="ebh-card h-100">
              <div class="ebh-card-header">
                <h6 class="fw-bold text-navy mb-0"><i class="bi bi-info-circle-fill me-2 text-primary"></i> Utility Inclusions & Billing Breakdown</h6>
                <span class="badge badge-warning text-dark border">Tenant Responsibility</span>
              </div>
              <div class="ebh-card-body">
                <div class="alert alert-info py-2 small mb-3">
                  <i class="bi bi-exclamation-circle-fill me-1"></i> <strong>Important Policy:</strong> Electricity and water utility bills are excluded from monthly rental rates and are shouldered directly by the tenants.
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                  <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-lightning-charge-fill text-warning fs-5"></i>
                    <div>
                      <strong class="text-navy d-block">Meralco Electricity Bills</strong>
                      <small class="text-muted">Individual unit submeters / actual consumption</small>
                    </div>
                  </div>
                  <span class="badge badge-light border text-muted">Excluded (Tenant)</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                  <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-water text-info fs-5"></i>
                    <div>
                      <strong class="text-navy d-block">Manila Water Service</strong>
                      <small class="text-muted">Separate metered water usage</small>
                    </div>
                  </div>
                  <span class="badge badge-light border text-muted">Excluded (Tenant)</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2">
                  <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-door-closed-fill text-success fs-5"></i>
                    <div>
                      <strong class="text-navy d-block">Room / Space Inclusions</strong>
                      <small class="text-muted">BH: Room + kitchen + shared CR | Private: Entire house</small>
                    </div>
                  </div>
                  <span class="badge badge-success text-white">Included in Rent</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Master Transaction Ledger Rendered from MySQL -->
        <div class="ebh-card">
          <div class="ebh-card-header">
            <div>
              <h6 class="fw-bold text-navy mb-0"><i class="bi bi-journal-text me-2 text-primary"></i> Master Transaction Ledger</h6>
              <small class="text-muted">Complete audit trail of all rent collections, deposits, and payment receipts • <span class="fw-bold text-primary"><?= $totalLedger ?></span> records logged</small>
            </div>
            <div class="input-group" style="max-width: 280px;">
              <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
              <input type="text" class="form-control form-control-sm border-start-0" id="reportsSearchInput" placeholder="Search ledger entries...">
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-ebh table-hover mb-0" id="reportsTable">
              <thead>
                <tr>
                  <th>Transaction ID</th>
                  <th>Date & Time</th>
                  <th>Resident</th>
                  <th>Unit</th>
                  <th>Mode of Payment</th>
                  <th>Amount (PHP)</th>
                  <th>Audit Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($payments)): ?>
                  <tr>
                    <td colspan="7" class="text-center py-4 text-muted">No audit ledger records found.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($payments as $p): ?>
                    <?php
                      $isPending = $p['status'] === 'pending';
                      $isVerified = $p['status'] === 'verified';
                      $isRejected = $p['status'] === 'rejected';
                    ?>
                    <tr class="<?= $isPending ? 'table-warning' : '' ?>">
                      <td class="fw-bold text-navy font-monospace"><?= htmlspecialchars($p['reference_no']) ?></td>
                      <td><?= date('M j, Y (g:i A)', strtotime($p['payment_date'])) ?></td>
                      <td><strong><?= htmlspecialchars($p['tenant_name']) ?></strong></td>
                      <td>Unit <?= htmlspecialchars($p['unit_code']) ?> (<?= htmlspecialchars($p['room_type']) ?>)</td>
                      <td><span class="badge badge-light border"><?= htmlspecialchars($p['payment_method']) ?></span></td>
                      <td class="fw-bold text-navy">₱<?= number_format($p['amount'], 2) ?></td>
                      <td>
                        <?php if ($isVerified): ?>
                          <span class="badge-ebh badge-verified"><i class="bi bi-check-circle me-1"></i> Audited & Cleared</span>
                        <?php elseif ($isPending): ?>
                          <span class="badge-ebh badge-pending"><i class="bi bi-clock-history me-1"></i> Pending Verification</span>
                        <?php elseif ($isRejected): ?>
                          <span class="badge-ebh badge-rejected"><i class="bi bi-x-circle me-1"></i> Rejected</span>
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

  <!-- Full jQuery 3.7.1, Popper.js, Bootstrap 4.1.3 JS -->
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.14.3/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.1.3/dist/js/bootstrap.min.js"></script>
  <script src="../assets/js/script.js"></script>
</body>
</html>
