<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/overdue_rules.php';
$pdo = getDbConnection();

$toastMsg = '';
$toastType = 'success';

// Handle Direct POST Submissions on Admin Dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Add New Tenant
    if ($action === 'add_tenant') {
        $name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $rawUnit = trim($_POST['unit_code'] ?? '');
        $unitCode = str_replace('Unit ', '', $rawUnit);
        $rent = (float)($_POST['monthly_rent'] ?? 3500);
        $moveIn = $_POST['move_in_date'] ?? date('Y-m-d');
        
        $customUsername = trim($_POST['username'] ?? '');
        if (!empty($customUsername)) {
            $username = strtolower(preg_replace('/[^a-zA-Z0-9_.-]/', '', $customUsername));
        } else {
            $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode(' ', $name)[0])) . rand(10, 99);
        }

        $rawPassword = trim($_POST['password'] ?? '');
        if (empty($rawPassword)) {
            $rawPassword = 'tenant123';
        }

        // Verify username uniqueness
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->execute([$username]);
        if ($checkStmt->fetch()) {
            $username .= rand(10, 99);
        }

        if (!empty($name) && !empty($unitCode)) {
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, full_name, role, unit_code, phone, monthly_rent, balance, move_in_date, lease_status)
                VALUES (?, ?, ?, 'tenant', ?, ?, ?, ?, ?, 'active')
            ");
            $hashedPassword = password_hash($rawPassword, PASSWORD_BCRYPT);
            $stmt->execute([$username, $hashedPassword, $name, $unitCode, $phone, $rent, $rent, $moveIn]);

            // Mark room occupied
            $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE unit_code = ?")->execute([$unitCode]);

            header("Location: admin-dashboard.php?msg=" . urlencode("Resident {$name} successfully registered (Username: {$username}) and assigned to Unit {$unitCode}."));
            exit;
        }
    }

    // Add Rental Unit
    if ($action === 'add_room') {
        $rawNum = trim($_POST['unit_code'] ?? '');
        $unitCode = str_replace('Unit ', '', $rawNum);
        $type = $_POST['room_type'] ?? 'Boarding House Rental';
        $rate = (float)($_POST['monthly_rate'] ?? 3500);
        $pax = (int)($_POST['max_pax'] ?? 3);
        $inclusions = ($type === 'Private Boarding House')
            ? 'Entire standalone private space/house, no shared common areas. Bills excluded.'
            : 'Individual room, kitchen area, shared comfort room. Max 3 pax limit. Bills excluded.';

        if (!empty($unitCode)) {
            $stmt = $pdo->prepare("
                INSERT INTO rooms (unit_code, room_type, monthly_rate, max_pax, status, inclusions)
                VALUES (?, ?, ?, ?, 'available', ?)
            ");
            $stmt->execute([$unitCode, $type, $rate, $pax, $inclusions]);
            header("Location: admin-dashboard.php?msg=" . urlencode("Rental Unit {$unitCode} added to inventory."));
            exit;
        }
    }

    // Approve / Reject Payment from Dashboard
    if ($action === 'update_payment_status') {
        $pId = (int)($_POST['payment_id'] ?? 0);
        $status = $_POST['status'] ?? 'verified';
        $notes = trim($_POST['rejection_reason'] ?? '');

        if ($pId > 0) {
            $pCheck = $pdo->prepare("SELECT tenant_id, tenant_name, amount, status FROM payments WHERE id = ?");
            $pCheck->execute([$pId]);
            $paymentRow = $pCheck->fetch();

            $adminName = $_SESSION['user']['full_name'] ?? 'Landlord Admin';
            if ($status === 'verified') {
                $stmt = $pdo->prepare("UPDATE payments SET status = 'verified', verified_by = ?, verified_at = NOW() WHERE id = ?");
                $stmt->execute([$adminName, $pId]);

                if ($paymentRow && $paymentRow['status'] !== 'verified') {
                    $payAmount = (float)$paymentRow['amount'];
                    if (!empty($paymentRow['tenant_id'])) {
                        $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$payAmount, $paymentRow['tenant_id']]);
                    } elseif (!empty($paymentRow['tenant_name'])) {
                        $pdo->prepare("UPDATE users SET balance = balance - ? WHERE full_name = ?")->execute([$payAmount, $paymentRow['tenant_name']]);
                    }
                }

                header("Location: admin-dashboard.php?msg=" . urlencode("Payment #{$pId} verified and balance updated."));
                exit;
            } elseif ($status === 'rejected') {
                $rejectNote = "Rejected by {$adminName} - " . ($notes ?: 'Unverified receipt screenshot.');
                $stmt = $pdo->prepare("UPDATE payments SET status = 'rejected', admin_notes = ?, verified_by = ?, verified_at = NOW() WHERE id = ?");
                $stmt->execute([$rejectNote, $adminName, $pId]);
                header("Location: admin-dashboard.php?msg=" . urlencode("Payment #{$pId} marked as rejected."));
                exit;
            }
        }
    }

    // Update Viewing Request Status from Dashboard
    if ($action === 'update_viewing_status') {
        $vId = (int)($_POST['viewing_id'] ?? 0);
        $vStatus = $_POST['status'] ?? 'confirmed';
        if ($vId > 0 && in_array($vStatus, ['pending', 'confirmed', 'cancelled', 'completed'])) {
            $pdo->prepare("UPDATE viewing_requests SET status = ? WHERE id = ?")->execute([$vStatus, $vId]);
            header("Location: admin-dashboard.php?msg=" . urlencode("Viewing request #{$vId} updated to {$vStatus}."));
            exit;
        }
    }
}

if (isset($_GET['msg'])) {
    $toastMsg = $_GET['msg'];
}

// Ensure viewing_requests table exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `viewing_requests` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NULL,
        `user_name` VARCHAR(100) NOT NULL,
        `contact_phone` VARCHAR(50) NOT NULL,
        `unit_or_type` VARCHAR(100) NOT NULL,
        `preferred_date` DATE NOT NULL,
        `notes` TEXT NULL,
        `status` ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// 1. Fetch KPI Metrics
$totalUnits = (int)$pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$occupiedUnits = (int)$pdo->query("SELECT COUNT(*) FROM rooms WHERE status = 'occupied'")->fetchColumn();
$availableUnits = (int)$pdo->query("SELECT COUNT(*) FROM rooms WHERE status = 'available'")->fetchColumn();
$activeTenants = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'tenant' AND lease_status = 'active'")->fetchColumn();
$monthlyTarget = (float)$pdo->query("SELECT COALESCE(SUM(monthly_rate), 0) FROM rooms")->fetchColumn();
$pendingQueueCount = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
$openTicketsCount = (int)$pdo->query("SELECT COUNT(*) FROM maintenance_tickets WHERE status = 'open'")->fetchColumn();
$occupancyPercent = $totalUnits > 0 ? round(($occupiedUnits / $totalUnits) * 100) : 0;

// Viewing Requests Count & List
$viewingRequestsStmt = $pdo->query("SELECT * FROM viewing_requests ORDER BY id DESC LIMIT 15");
$viewingRequests = $viewingRequestsStmt->fetchAll();
$pendingViewingsCount = (int)$pdo->query("SELECT COUNT(*) FROM viewing_requests WHERE status = 'pending'")->fetchColumn();

// Eviction Risk & Overdue Tracking
$activeTenantsList = $pdo->query("SELECT * FROM users WHERE role = 'tenant' AND lease_status = 'active'")->fetchAll();
$evictionRiskTenants = [];
$exemptedTenantsCount = 0;
foreach ($activeTenantsList as $at) {
    $m = calculateOverdueStatus($at);
    if ($m['is_eviction_risk']) {
        $evictionRiskTenants[] = array_merge($at, $m);
    } elseif ($m['is_exempted']) {
        $exemptedTenantsCount++;
    }
}

// 2. Fetch Category Breakdown
$bhTotal = (int)$pdo->query("SELECT COUNT(*) FROM rooms WHERE room_type LIKE '%Boarding%' AND room_type NOT LIKE '%Private%'")->fetchColumn();
$bhOccupied = (int)$pdo->query("SELECT COUNT(*) FROM rooms WHERE room_type LIKE '%Boarding%' AND room_type NOT LIKE '%Private%' AND status = 'occupied'")->fetchColumn();
$bhPercent = $bhTotal > 0 ? round(($bhOccupied / $bhTotal) * 100) : 0;

$pbTotal = (int)$pdo->query("SELECT COUNT(*) FROM rooms WHERE room_type LIKE '%Private%'")->fetchColumn();
$pbOccupied = (int)$pdo->query("SELECT COUNT(*) FROM rooms WHERE room_type LIKE '%Private%' AND status = 'occupied'")->fetchColumn();
$pbPercent = $pbTotal > 0 ? round(($pbOccupied / $pbTotal) * 100) : 0;

// 3. Fetch Pending Payments
$pendingPaymentsStmt = $pdo->query("
    SELECT p.*, COALESCE(u.phone, '') AS tenant_phone, COALESCE(r.room_type, 'Boarding Rental') AS room_type
    FROM payments p
    LEFT JOIN users u ON p.tenant_name = u.full_name
    LEFT JOIN rooms r ON p.unit_code = r.unit_code
    WHERE p.status = 'pending'
    ORDER BY p.id DESC
    LIMIT 10
");
$pendingPayments = $pendingPaymentsStmt->fetchAll();

// 4. Fetch Rooms for Mini Badges & Modals
$allRoomsStmt = $pdo->query("
    SELECT r.*, u.full_name AS tenant_name
    FROM rooms r
    LEFT JOIN users u ON r.unit_code = u.unit_code AND u.lease_status = 'active'
    ORDER BY r.unit_code ASC
");
$allRooms = $allRoomsStmt->fetchAll();

// 5. Fetch Recent Activities (Combined Payments and Tickets)
$recentActivities = [];
$latestPayments = $pdo->query("
    SELECT 'payment' AS type, reference_no, tenant_name, unit_code, amount, status, created_at
    FROM payments
    ORDER BY id DESC
    LIMIT 4
")->fetchAll();

foreach ($latestPayments as $lp) {
    $timeAgo = date('M j, g:i A', strtotime($lp['created_at']));
    $recentActivities[] = [
        'icon' => 'bi-credit-card-2-front text-success',
        'title' => 'Payment ' . strtoupper($lp['status']),
        'desc' => "{$lp['tenant_name']} (Unit {$lp['unit_code']}) - ₱" . number_format($lp['amount'], 2) . " [{$lp['reference_no']}]",
        'time' => $timeAgo,
        'raw_date' => $lp['created_at']
    ];
}

$latestTickets = $pdo->query("
    SELECT 'ticket' AS type, ticket_no, tenant_name, unit_code, category, urgency, status, created_at
    FROM maintenance_tickets
    ORDER BY id DESC
    LIMIT 3
")->fetchAll();

foreach ($latestTickets as $lt) {
    $timeAgo = date('M j, g:i A', strtotime($lt['created_at']));
    $recentActivities[] = [
        'icon' => 'bi-tools text-warning',
        'title' => 'Ticket #' . $lt['ticket_no'],
        'desc' => "{$lt['category']} issue by {$lt['tenant_name']} (Unit {$lt['unit_code']}) - Status: " . ucfirst($lt['status']),
        'time' => $timeAgo,
        'raw_date' => $lt['created_at']
    ];
}

usort($recentActivities, function ($a, $b) {
    return strtotime($b['raw_date']) - strtotime($a['raw_date']);
});

$adminUser = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard | Eanne Boarding House Console</title>
  <meta name="description" content="Landlord administrative dashboard for Eanne Boarding House rental operations in Pasig City.">
  
  <!-- Bootstrap 4.1.3 CSS & Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.1.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <!-- Custom Design System -->
  <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>">
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
        <a href="admin-dashboard.php" class="sidebar-link active"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
        <a href="tenants.php" class="sidebar-link"><i class="bi bi-people-fill"></i><span>Tenants Roster</span></a>
        <a href="rooms.php" class="sidebar-link"><i class="bi bi-door-open-fill"></i><span>Rooms & Houses</span></a>
        <a href="payments.php" class="sidebar-link"><i class="bi bi-credit-card-2-front-fill"></i><span>Payments Queue</span> <span class="badge <?= $pendingQueueCount > 0 ? 'badge-warning text-dark' : 'badge-success text-white' ?> ml-auto"><?= $pendingQueueCount ?></span></a>
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
      <!-- Top Bar -->
      <header class="dashboard-topbar">
        <div class="d-flex align-items-center gap-3">
          <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" id="sidebarToggleBtn" onclick="if(window.toggleAdminSidebar){window.toggleAdminSidebar(event);}else{var s=document.querySelector('.dashboard-sidebar');if(s)s.classList.toggle('show');}"><i class="bi bi-list fs-5"></i></button>
          <div>
            <h5 class="mb-0 fw-bold text-navy">Boarding House Management Console</h5>
            <small class="text-muted">6 Units Total: 4 Boarding House Rentals & 2 Private Boarding Houses</small>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <button type="button" class="btn btn-sm btn-ebh-primary" data-toggle="modal" data-target="#addTenantModal">
            <i class="bi bi-person-plus-fill me-1"></i> Add Tenant
          </button>
          <a href="../index.php" class="btn btn-sm btn-outline-primary d-none d-md-inline-block"><i class="bi bi-house me-1"></i> Public Site</a>
          <a href="login.php?logout=1" class="btn btn-sm btn-outline-danger" title="Sign Out"><i class="bi bi-box-arrow-left"></i></a>
        </div>
      </header>

      <!-- Dashboard Content -->
      <main class="dashboard-content">
        
        <?php if (!empty($toastMsg)): ?>
          <div class="alert alert-success alert-dismissible fade show shadow-sm border-success py-2 px-3 mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($toastMsg) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        <?php endif; ?>

        <?php if (!empty($evictionRiskTenants)): ?>
          <!-- EVICTION RISK ADMIN ALERT -->
          <div class="alert alert-danger shadow-sm border-danger p-3 mb-4 rounded-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
              <div class="bg-danger text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width:42px; height:42px;">
                <i class="bi bi-door-closed-fill fs-5"></i>
              </div>
              <div>
                <h6 class="fw-bold text-danger mb-0"><i class="bi bi-exclamation-triangle-fill me-1"></i> Eviction Alert: <?= count($evictionRiskTenants) ?> Tenant(s) at Eviction Risk (3+ Months Overdue)</h6>
                <small class="text-dark d-block">
                  These residents have not settled rent for 3 or more months without active exemption. Under policy, they must pay 50% minimum immediately or be evicted.
                </small>
              </div>
            </div>
            <a href="tenants.php" class="btn btn-sm btn-danger fw-bold"><i class="bi bi-people-fill me-1"></i> Review in Tenants Roster</a>
          </div>
        <?php endif; ?>

        <!-- Welcome Alert Banner -->
        <div class="ebh-card p-4 mb-4" style="background: linear-gradient(135deg, #ffffff 0%, #faf9f6 100%);">
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
              <div class="kpi-icon blue"><i class="bi bi-building-check"></i></div>
              <div>
                <div class="d-inline-flex align-items-center gap-2 px-2 py-0.5 rounded-pill bg-light text-navy small fw-bold mb-1 border" style="font-size: 0.72rem;">
                  <span class="badge-dot bg-success" style="width:6px;height:6px;border-radius:50%;display:inline-block;background:#16a34a;"></span> Operations Normal &bull; Pasig Compound
                </div>
                <h6 class="fw-bold text-navy mb-1" style="font-size: 1.05rem;">Rent Collection &amp; Tenancy Active</h6>
                <p class="small text-muted mb-0"><?= date('F Y') ?> billing cycle open. <?= $pendingQueueCount ?> tenant proofs awaiting review. Utility bills shouldered by tenants.</p>
              </div>
            </div>
            <div class="d-flex align-items-center gap-2">
              <a href="payments.php" class="btn btn-sm btn-ebh-primary"><i class="bi bi-check2-all me-1"></i> Review Payments (<?= $pendingQueueCount ?>)</a>
            </div>
          </div>
        </div>

        <!-- 4 KPI Stat Cards Directly from Database -->
        <div class="row g-3 mb-4">
          <div class="col-sm-6 col-xl-3">
            <div class="kpi-card h-100">
              <div class="kpi-icon navy"><i class="bi bi-people-fill"></i></div>
              <div>
                <div class="kpi-title">Active Tenants</div>
                <div class="kpi-value text-navy"><?= $activeTenants ?></div>
                <small class="text-success fw-semibold"><i class="bi bi-arrow-up-short"></i> <?= $occupancyPercent ?>% Capacity (<?= $occupiedUnits ?>/<?= $totalUnits ?> Units)</small>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-xl-3">
            <div class="kpi-card h-100">
              <div class="kpi-icon blue"><i class="bi bi-cash-stack"></i></div>
              <div>
                <div class="kpi-title">Monthly Target</div>
                <div class="kpi-value text-navy">₱<?= number_format($monthlyTarget, 2) ?></div>
                <small class="text-muted"><i class="bi bi-calendar3 me-1"></i> Full Compound Target</small>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-xl-3">
            <div class="kpi-card h-100">
              <div class="kpi-icon warning"><i class="bi bi-hourglass-split"></i></div>
              <div>
                <div class="kpi-title">Pending Verification</div>
                <div class="kpi-value text-navy"><?= $pendingQueueCount ?></div>
                <small class="text-warning fw-semibold"><i class="bi bi-clock-history me-1"></i> Receipts in queue</small>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-xl-3">
            <div class="kpi-card h-100">
              <div class="kpi-icon accent"><i class="bi bi-door-open-fill"></i></div>
              <div>
                <div class="kpi-title">Available Units</div>
                <div class="kpi-value text-navy"><?= $availableUnits ?></div>
                <small class="text-info fw-semibold"><i class="bi bi-check2-circle me-1"></i> <?= $availableUnits ?> Vacant Now</small>
              </div>
            </div>
          </div>
        </div>

        <!-- Main 2-Column Content -->
        <div class="row g-4">
          <!-- Left Column: Pending Payments Verification Queue -->
          <div class="col-xl-8">
            <div class="ebh-card mb-4">
              <div class="ebh-card-header">
                <div>
                  <h6 class="fw-bold text-navy mb-0"><i class="bi bi-credit-card-2-front-fill me-2 text-primary"></i> Pending Payment Approvals</h6>
                  <small class="text-muted">Digital rent receipts submitted by tenants requiring Landlord verification</small>
                </div>
                <a href="payments.php" class="btn btn-sm btn-outline-primary">View All Payments</a>
              </div>
              <div class="table-responsive">
                <table class="table table-ebh table-hover mb-0" id="adminPendingTable">
                  <thead>
                    <tr>
                      <th>Tenant & Unit</th>
                      <th>Amount</th>
                      <th>Method & Ref</th>
                      <th>Date</th>
                      <th>Status</th>
                      <th class="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($pendingPayments)): ?>
                      <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                          <i class="bi bi-check2-circle text-success fs-3 d-block mb-1"></i>
                          No pending payments! All tenant receipts are verified and cleared.
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($pendingPayments as $p): ?>
                        <?php
                          $initials = strtoupper(substr($p['tenant_name'], 0, 1) . substr(strrchr($p['tenant_name'], ' ') ?: '', 1, 1));
                          if (!$initials) $initials = 'TN';
                        ?>
                        <tr class="table-warning">
                          <td>
                            <div class="d-flex align-items-center gap-2">
                              <div class="avatar-circle sm"><?= htmlspecialchars($initials) ?></div>
                              <div>
                                <strong class="text-navy d-block"><?= htmlspecialchars($p['tenant_name']) ?></strong>
                                <small class="text-muted">Unit <?= htmlspecialchars($p['unit_code']) ?></small>
                              </div>
                            </div>
                          </td>
                          <td class="fw-bold text-navy">₱<?= number_format($p['amount'], 2) ?></td>
                          <td>
                            <span class="badge badge-light border"><i class="bi bi-phone text-primary me-1"></i> <?= htmlspecialchars($p['payment_method']) ?></span>
                            <small class="d-block text-muted font-monospace"><?= htmlspecialchars($p['reference_no']) ?></small>
                          </td>
                          <td><small class="text-muted"><?= date('M j, Y', strtotime($p['payment_date'])) ?></small></td>
                          <td><span class="badge-ebh badge-pending"><i class="bi bi-clock-history"></i> Pending</span></td>
                          <td class="text-end">
                            <form method="POST" action="admin-dashboard.php" class="d-inline" onsubmit="return confirm('Approve and mark Payment #<?= htmlspecialchars($p['reference_no']) ?> as Verified?');">
                              <input type="hidden" name="action" value="update_payment_status">
                              <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                              <input type="hidden" name="status" value="verified">
                              <button type="submit" class="btn btn-sm btn-success" title="Approve"><i class="bi bi-check-lg"></i> Approve</button>
                            </form>
                            <a href="payments.php" class="btn btn-sm btn-outline-secondary" title="Review Proof"><i class="bi bi-eye"></i></a>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Viewing Requests / Schedule a Viewing Roster -->
            <div class="ebh-card mb-4" id="viewingsSection">
              <div class="ebh-card-header d-flex justify-content-between align-items-center">
                <div>
                  <h6 class="fw-bold text-navy mb-0"><i class="bi bi-calendar2-check-fill me-2 text-primary"></i> Viewing Schedule Requests</h6>
                  <small class="text-muted">In-person room visits scheduled by prospective tenants &amp; users</small>
                </div>
                <span class="badge <?= $pendingViewingsCount > 0 ? 'badge-warning text-dark' : 'badge-success text-white' ?>"><?= $pendingViewingsCount ?> Pending</span>
              </div>
              <div class="table-responsive">
                <table class="table table-ebh table-hover mb-0" id="adminViewingsTable">
                  <thead>
                    <tr>
                      <th>Prospective Resident</th>
                      <th>Contact (SMS)</th>
                      <th>Unit / Type</th>
                      <th>Target Date</th>
                      <th>Status</th>
                      <th class="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($viewingRequests)): ?>
                      <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                          <i class="bi bi-calendar-x text-muted fs-3 d-block mb-1"></i>
                          No viewing schedule requests received yet.
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($viewingRequests as $vr): ?>
                        <?php
                          $vInitials = strtoupper(substr($vr['user_name'], 0, 1) . substr(strrchr($vr['user_name'], ' ') ?: '', 1, 1));
                          if (!$vInitials) $vInitials = 'VR';
                          $vStatus = $vr['status'] ?? 'pending';
                        ?>
                        <tr class="<?= $vStatus === 'pending' ? 'table-warning' : '' ?>" id="viewing-row-<?= $vr['id'] ?>">
                          <td>
                            <div class="d-flex align-items-center gap-2">
                              <div class="avatar-circle sm bg-light border text-navy"><?= htmlspecialchars($vInitials) ?></div>
                              <div>
                                <strong class="text-navy d-block"><?= htmlspecialchars($vr['user_name']) ?></strong>
                                <?php if (!empty($vr['notes'])): ?>
                                  <small class="text-muted d-block text-truncate" style="max-width:200px;" title="<?= htmlspecialchars($vr['notes']) ?>"><i class="bi bi-chat-left-text me-1"></i><?= htmlspecialchars($vr['notes']) ?></small>
                                <?php endif; ?>
                              </div>
                            </div>
                          </td>
                          <td>
                            <a href="tel:<?= htmlspecialchars($vr['contact_phone']) ?>" class="fw-semibold text-navy text-decoration-none">
                              <i class="bi bi-telephone text-primary me-1"></i><?= htmlspecialchars($vr['contact_phone']) ?>
                            </a>
                          </td>
                          <td>
                            <span class="badge badge-light border text-navy"><i class="bi bi-door-open me-1"></i><?= htmlspecialchars($vr['unit_or_type']) ?></span>
                          </td>
                          <td>
                            <small class="fw-semibold text-navy d-block"><i class="bi bi-calendar-event text-primary me-1"></i><?= date('M j, Y', strtotime($vr['preferred_date'])) ?></small>
                            <small class="text-muted" style="font-size:0.75rem;">Submitted <?= date('M j', strtotime($vr['created_at'])) ?></small>
                          </td>
                          <td>
                            <?php if ($vStatus === 'pending'): ?>
                              <span class="badge-ebh badge-pending"><i class="bi bi-clock-history"></i> Pending</span>
                            <?php elseif ($vStatus === 'confirmed'): ?>
                              <span class="badge-ebh badge-verified"><i class="bi bi-check-circle"></i> Confirmed</span>
                            <?php elseif ($vStatus === 'completed'): ?>
                              <span class="badge badge-secondary"><i class="bi bi-check-all"></i> Completed</span>
                            <?php else: ?>
                              <span class="badge badge-danger"><?= htmlspecialchars(ucfirst($vStatus)) ?></span>
                            <?php endif; ?>
                          </td>
                          <td class="text-end">
                            <?php if ($vStatus === 'pending'): ?>
                              <form method="POST" action="admin-dashboard.php" class="d-inline" onsubmit="return confirm('Confirm viewing visit for <?= htmlspecialchars(addslashes($vr['user_name'])) ?>?');">
                                <input type="hidden" name="action" value="update_viewing_status">
                                <input type="hidden" name="viewing_id" value="<?= $vr['id'] ?>">
                                <input type="hidden" name="status" value="confirmed">
                                <button type="submit" class="btn btn-sm btn-primary" title="Confirm Appointment"><i class="bi bi-check-lg"></i> Confirm</button>
                              </form>
                              <form method="POST" action="admin-dashboard.php" class="d-inline" onsubmit="return confirm('Cancel this viewing request?');">
                                <input type="hidden" name="action" value="update_viewing_status">
                                <input type="hidden" name="viewing_id" value="<?= $vr['id'] ?>">
                                <input type="hidden" name="status" value="cancelled">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel"><i class="bi bi-x-lg"></i></button>
                              </form>
                            <?php elseif ($vStatus === 'confirmed'): ?>
                              <form method="POST" action="admin-dashboard.php" class="d-inline">
                                <input type="hidden" name="action" value="update_viewing_status">
                                <input type="hidden" name="viewing_id" value="<?= $vr['id'] ?>">
                                <input type="hidden" name="status" value="completed">
                                <button type="submit" class="btn btn-sm btn-outline-success" title="Mark Completed"><i class="bi bi-check-all"></i> Done</button>
                              </form>
                            <?php else: ?>
                              <span class="text-muted small">&mdash;</span>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Unit Occupancy Status -->
            <div class="ebh-card">
              <div class="ebh-card-header">
                <h6 class="fw-bold text-navy mb-0"><i class="bi bi-grid-3x3-gap-fill me-2 text-primary"></i> Category Occupancy Overview</h6>
                <a href="rooms.php" class="btn btn-sm btn-outline-primary">Manage 6 Units</a>
              </div>
              <div class="ebh-card-body">
                <!-- Boarding House Rentals -->
                <div class="mb-4">
                  <div class="d-flex justify-content-between small fw-semibold mb-1">
                    <span>Boarding House Rental Units (<?= $bhTotal ?> Units Total - ₱3,500/mo)</span>
                    <span class="text-navy"><?= $bhOccupied ?> of <?= $bhTotal ?> Occupied (<?= $bhPercent ?>%)</span>
                  </div>
                  <div class="progress" style="height: 10px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $bhPercent ?>%;" aria-valuenow="<?= $bhPercent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                  </div>
                  <div class="d-flex gap-2 mt-2 flex-wrap">
                    <?php foreach ($allRooms as $r): ?>
                      <?php if (strpos($r['room_type'], 'Boarding') !== false && strpos($r['room_type'], 'Private') === false): ?>
                        <?php if ($r['status'] === 'occupied'): ?>
                          <span class="badge-ebh badge-paid small"><i class="bi bi-door-closed"></i> Unit <?= htmlspecialchars($r['unit_code']) ?> (<?= htmlspecialchars(explode(' ', $r['tenant_name'] ?? 'Occupied')[0]) ?>)</span>
                        <?php else: ?>
                          <span class="badge-ebh badge-verified small"><i class="bi bi-door-open"></i> Unit <?= htmlspecialchars($r['unit_code']) ?> (Vacant)</span>
                        <?php endif; ?>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </div>
                </div>

                <!-- Private Boarding Houses -->
                <div>
                  <div class="d-flex justify-content-between small fw-semibold mb-1">
                    <span>Private Boarding Houses (<?= $pbTotal ?> Units Total - ₱8,000/mo)</span>
                    <span class="text-navy"><?= $pbOccupied ?> of <?= $pbTotal ?> Occupied (<?= $pbPercent ?>%)</span>
                  </div>
                  <div class="progress" style="height: 10px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $pbPercent ?>%;" aria-valuenow="<?= $pbPercent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                  </div>
                  <div class="d-flex gap-2 mt-2 flex-wrap">
                    <?php foreach ($allRooms as $r): ?>
                      <?php if (strpos($r['room_type'], 'Private') !== false): ?>
                        <?php if ($r['status'] === 'occupied'): ?>
                          <span class="badge-ebh badge-paid small"><i class="bi bi-house-door-fill"></i> Unit <?= htmlspecialchars($r['unit_code']) ?> (<?= htmlspecialchars(explode(' ', $r['tenant_name'] ?? 'Occupied')[0]) ?>)</span>
                        <?php else: ?>
                          <span class="badge-ebh badge-verified small"><i class="bi bi-key-fill"></i> Unit <?= htmlspecialchars($r['unit_code']) ?> (Vacant - ₱8,000)</span>
                        <?php endif; ?>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Right Column: Quick Operations & Recent Activity -->
          <div class="col-xl-4">
            <!-- Quick Actions Card -->
            <div class="ebh-card mb-4">
              <div class="ebh-card-header">
                <h6 class="fw-bold text-navy mb-0"><i class="bi bi-lightning-charge-fill me-1 text-warning"></i> Quick Operations</h6>
              </div>
              <div class="ebh-card-body p-2 d-flex flex-column gap-1">
                <button type="button" class="btn text-start d-flex align-items-center justify-content-between p-2.5 rounded-3 border-0 bg-transparent quick-op-item" data-toggle="modal" data-target="#addTenantModal">
                  <span class="d-flex align-items-center gap-2.5">
                    <span class="kpi-icon sm navy" style="width:32px;height:32px;font-size:1rem;"><i class="bi bi-person-plus-fill"></i></span>
                    <span class="fw-semibold text-navy small">Register New Tenant</span>
                  </span>
                  <i class="bi bi-chevron-right text-muted" style="font-size:0.75rem;"></i>
                </button>
                <button type="button" class="btn text-start d-flex align-items-center justify-content-between p-2.5 rounded-3 border-0 bg-transparent quick-op-item" data-toggle="modal" data-target="#addRoomModal">
                  <span class="d-flex align-items-center gap-2.5">
                    <span class="kpi-icon sm blue" style="width:32px;height:32px;font-size:1rem;"><i class="bi bi-door-plus-fill"></i></span>
                    <span class="fw-semibold text-navy small">Add Rental Unit</span>
                  </span>
                  <i class="bi bi-chevron-right text-muted" style="font-size:0.75rem;"></i>
                </button>
                <a href="reports.php" class="btn text-start d-flex align-items-center justify-content-between p-2.5 rounded-3 border-0 bg-transparent quick-op-item">
                  <span class="d-flex align-items-center gap-2.5">
                    <span class="kpi-icon sm accent" style="width:32px;height:32px;font-size:1rem;"><i class="bi bi-printer-fill"></i></span>
                    <span class="fw-semibold text-navy small">Print Collection Sheet</span>
                  </span>
                  <i class="bi bi-chevron-right text-muted" style="font-size:0.75rem;"></i>
                </a>
                <a href="tickets.php" class="btn text-start d-flex align-items-center justify-content-between p-2.5 rounded-3 border-0 bg-transparent quick-op-item">
                  <span class="d-flex align-items-center gap-2.5">
                    <span class="kpi-icon sm danger" style="width:32px;height:32px;font-size:1rem;"><i class="bi bi-tools"></i></span>
                    <span class="fw-semibold text-navy small">Maintenance Tickets</span>
                  </span>
                  <span class="badge-ebh <?= $openTicketsCount > 0 ? 'badge-urgent' : 'badge-verified' ?>" style="font-size:0.7rem;padding:0.2rem 0.55rem;"><?= $openTicketsCount ?></span>
                </a>
                <a href="payments.php" class="btn text-start d-flex align-items-center justify-content-between p-2.5 rounded-3 border-0 bg-transparent quick-op-item">
                  <span class="d-flex align-items-center gap-2.5">
                    <span class="kpi-icon sm navy" style="width:32px;height:32px;font-size:1rem;background:#ecfdf5;color:#059669;"><i class="bi bi-cash-coin"></i></span>
                    <span class="fw-semibold text-navy small">Record Cash Payment</span>
                  </span>
                  <i class="bi bi-chevron-right text-muted" style="font-size:0.75rem;"></i>
                </a>
              </div>
            </div>

            <!-- Recent Activity Feed -->
            <div class="ebh-card">
              <div class="ebh-card-header">
                <h6 class="fw-bold text-navy mb-0"><i class="bi bi-activity me-1 text-info"></i> Recent Activity</h6>
              </div>
              <div class="ebh-card-body p-3">
                <div class="d-flex flex-column gap-3 small">
                  <?php if (empty($recentActivities)): ?>
                    <div class="text-center py-4 text-muted">No activity records found.</div>
                  <?php else: ?>
                    <?php foreach ($recentActivities as $act): ?>
                      <div class="d-flex gap-2">
                        <div class="avatar-circle sm bg-light border"><i class="bi <?= htmlspecialchars($act['icon']) ?>"></i></div>
                        <div>
                          <strong class="text-navy"><?= htmlspecialchars($act['title']) ?>:</strong> <?= htmlspecialchars($act['desc']) ?>
                          <div class="text-muted" style="font-size:0.75rem;"><?= htmlspecialchars($act['time']) ?></div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>

  <!-- Modal: Add New Tenant -->
  <div class="modal fade" id="addTenantModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-navy text-white">
          <h5 class="modal-title fw-bold text-white mb-0"><i class="bi bi-person-plus-fill me-2"></i> Register New Tenant</h5>
          <button type="button" class="close text-white border-0 bg-transparent" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form method="POST" action="admin-dashboard.php">
          <input type="hidden" name="action" value="add_tenant">
          <div class="modal-body p-4">
            <div class="row g-3">
              <div class="col-12">
                <label for="newTenantName" class="form-label small fw-bold text-navy">Full Legal Name</label>
                <input type="text" class="form-control" id="newTenantName" name="full_name" required placeholder="e.g. Roberto Gomez">
              </div>
              <div class="col-md-6">
                <label for="newTenantContact" class="form-label small fw-bold text-navy">Mobile / Contact Number</label>
                <input type="tel" class="form-control" id="newTenantContact" name="phone" required placeholder="0917-123-4567">
              </div>
              <div class="col-md-6">
                <label for="newTenantMoveIn" class="form-label small fw-bold text-navy">Move-in Date</label>
                <input type="date" class="form-control" id="newTenantMoveIn" name="move_in_date" required value="<?= date('Y-m-d') ?>">
              </div>
              <div class="col-md-6">
                <label for="newTenantRoom" class="form-label small fw-bold text-navy">Unit Assignment</label>
                <select class="form-control" id="newTenantRoom" name="unit_code" required onchange="const rate = this.options[this.selectedIndex].getAttribute('data-rate'); if(rate) document.getElementById('newTenantRent').value = rate;">
                  <option value="" disabled selected>Select a unit...</option>
                  <?php foreach ($allRooms as $r): ?>
                    <option value="<?= htmlspecialchars($r['unit_code']) ?>" data-rate="<?= $r['monthly_rate'] ?>" <?= $r['status'] === 'occupied' ? 'disabled' : '' ?>>
                      Unit <?= htmlspecialchars($r['unit_code']) ?> (<?= htmlspecialchars($r['room_type']) ?> - ₱<?= number_format($r['monthly_rate']) ?>) <?= $r['status'] === 'occupied' ? '[Occupied]' : '[Available]' ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label for="newTenantRent" class="form-label small fw-bold text-navy">Monthly Rent (₱)</label>
                <input type="number" class="form-control" id="newTenantRent" name="monthly_rent" required value="3500">
              </div>

              <!-- Portal Account Credentials Section -->
              <div class="col-12 pt-2 border-top">
                <span class="small fw-bold text-navy d-block mb-2"><i class="bi bi-shield-lock-fill text-primary me-1"></i> Tenant Portal Account Credentials</span>
              </div>
              <div class="col-md-6">
                <label for="newDashTenantUsername" class="form-label small fw-bold text-navy">Username</label>
                <div class="input-group input-group-sm">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                  </div>
                  <input type="text" class="form-control" id="newDashTenantUsername" name="username" placeholder="e.g. roberto22 (optional)">
                </div>
                <small class="text-muted" style="font-size:0.72rem;">Leave blank to auto-generate</small>
              </div>
              <div class="col-md-6">
                <label for="newDashTenantPassword" class="form-label small fw-bold text-navy">Initial Password</label>
                <div class="input-group input-group-sm">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="bi bi-key"></i></span>
                  </div>
                  <input type="text" class="form-control" id="newDashTenantPassword" name="password" placeholder="Default: tenant123">
                </div>
                <small class="text-muted" style="font-size:0.72rem;">Defaults to <code>tenant123</code> if blank</small>
              </div>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-ebh-primary btn-sm"><i class="bi bi-check-circle me-1"></i> Save & Assign Tenant</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal: Add Room Inventory -->
  <div class="modal fade" id="addRoomModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-navy text-white">
          <h5 class="modal-title fw-bold text-white mb-0"><i class="bi bi-door-plus-fill me-2"></i> Add Rental Unit</h5>
          <button type="button" class="close text-white border-0 bg-transparent" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form method="POST" action="admin-dashboard.php">
          <input type="hidden" name="action" value="add_room">
          <div class="modal-body p-4">
            <div class="row g-3">
              <div class="col-md-6">
                <label for="newRoomNumber" class="form-label small fw-bold text-navy">Unit Identifier</label>
                <input type="text" class="form-control" id="newRoomNumber" name="unit_code" required placeholder="e.g. BH-05">
              </div>
              <div class="col-md-6">
                <label for="newRoomType" class="form-label small fw-bold text-navy">Unit Category</label>
                <select class="form-control" id="newRoomType" name="room_type" required onchange="document.getElementById('newRoomRate').value = this.value === 'Private Boarding House' ? 8000 : 3500;">
                  <option value="Boarding House Rental">Boarding House Rental</option>
                  <option value="Private Boarding House">Private Boarding House</option>
                </select>
              </div>
              <div class="col-md-6">
                <label for="newRoomRate" class="form-label small fw-bold text-navy">Monthly Rate (₱)</label>
                <input type="number" class="form-control" id="newRoomRate" name="monthly_rate" required value="3500">
              </div>
              <div class="col-md-6">
                <label for="newRoomCapacity" class="form-label small fw-bold text-navy">Maximum Occupants</label>
                <input type="number" class="form-control" id="newRoomCapacity" name="max_pax" value="3" min="1" max="6">
              </div>
              <div class="col-12">
                <div class="alert alert-info py-2 px-3 small mb-0">
                  <i class="bi bi-info-circle me-1"></i> Electricity and water bills are excluded and shouldered by tenants.
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-ebh-primary btn-sm"><i class="bi bi-save me-1"></i> Add Unit to Inventory</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Full jQuery 3.7.1, Popper.js, Bootstrap 4.1.3 JS -->
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.14.3/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.1.3/dist/js/bootstrap.min.js"></script>
  <script src="../assets/js/script.js?v=<?= time() ?>"></script>
</body>
</html>
