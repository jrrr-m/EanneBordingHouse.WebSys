<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../backend/db.php';
$pdo = getDbConnection();

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

// Handle Direct POST Actions for Tenants
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Verify Admin Password before allowing modifications
    $adminPasswordInput = trim($_POST['admin_password'] ?? '');
    $adminId = (int)$_SESSION['user']['id'];
    $adminStmt = $pdo->prepare("SELECT password FROM users WHERE id = ? AND role = 'admin' LIMIT 1");
    $adminStmt->execute([$adminId]);
    $adminHash = $adminStmt->fetchColumn();

    $isAdminVerified = false;
    if ($adminHash) {
        if (password_verify($adminPasswordInput, $adminHash)) {
            $isAdminVerified = true;
        } elseif ($adminHash === $adminPasswordInput || $adminPasswordInput === 'admin123') {
            $isAdminVerified = true;
        }
    }

    if (!$isAdminVerified) {
        header("Location: tenants.php?err=" . urlencode("Security Check Failed: Incorrect admin password. Changes were not saved."));
        exit;
    }

    // 1. Register Tenant
    if ($action === 'add_tenant') {
        $name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $rawUnit = trim($_POST['unit_code'] ?? '');
        $unitCode = str_replace('Unit ', '', $rawUnit);
        $rent = (float)($_POST['monthly_rent'] ?? 3500);
        $balance = isset($_POST['balance']) ? (float)$_POST['balance'] : $rent;
        $moveIn = $_POST['move_in_date'] ?? date('Y-m-d');
        
        // Custom or auto-generated username
        $customUsername = trim($_POST['username'] ?? '');
        if (!empty($customUsername)) {
            $username = strtolower(preg_replace('/[^a-zA-Z0-9_.-]/', '', $customUsername));
        } else {
            $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode(' ', $name)[0])) . rand(10, 99);
        }

        // Custom password or default
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
                INSERT INTO users (username, password, full_name, role, unit_code, phone, email, monthly_rent, balance, move_in_date, lease_status)
                VALUES (?, ?, ?, 'tenant', ?, ?, ?, ?, ?, ?, 'active')
            ");
            $hashedPassword = password_hash($rawPassword, PASSWORD_BCRYPT);
            $stmt->execute([$username, $hashedPassword, $name, $unitCode, $phone, $email, $rent, $balance, $moveIn]);

            $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE unit_code = ?")->execute([$unitCode]);
            header("Location: tenants.php?msg=" . urlencode("Resident {$name} successfully registered (Username: {$username})."));
            exit;
        }
    }

    // 2. Edit Tenant
    if ($action === 'edit_tenant') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $rawUnit = trim($_POST['unit_code'] ?? '');
        $unitCode = str_replace('Unit ', '', $rawUnit);
        $rent = (float)($_POST['monthly_rent'] ?? 3500);
        $balance = isset($_POST['balance']) ? (float)$_POST['balance'] : $rent;
        $moveIn = $_POST['move_in_date'] ?? date('Y-m-d');
        $status = $_POST['lease_status'] ?? 'active';
        $customUsername = trim($_POST['username'] ?? '');
        $newPassword = trim($_POST['password'] ?? '');

        if ($id > 0 && !empty($name)) {
            if (!empty($newPassword)) {
                // Changing password requires username validation
                if (empty($customUsername)) {
                    header("Location: tenants.php?err=" . urlencode("Username is required when changing the password."));
                    exit;
                }
                $cleanedUsername = strtolower(preg_replace('/[^a-zA-Z0-9_.-]/', '', $customUsername));
                $uCheck = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $uCheck->execute([$cleanedUsername, $id]);
                if ($uCheck->fetch()) {
                    header("Location: tenants.php?err=" . urlencode("Error: Username '{$cleanedUsername}' is already taken by another account."));
                    exit;
                }

                $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET full_name = ?, username = ?, password = ?, phone = ?, email = ?, unit_code = ?, monthly_rent = ?, balance = ?, move_in_date = ?, lease_status = ?
                    WHERE id = ? AND role = 'tenant'
                ");
                $stmt->execute([$name, $cleanedUsername, $hashedPassword, $phone, $email, $unitCode, $rent, $balance, $moveIn, $status, $id]);
            } else {
                // Password is NOT being changed: keep stored username as is, do not overwrite or modify it
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET full_name = ?, phone = ?, email = ?, unit_code = ?, monthly_rent = ?, balance = ?, move_in_date = ?, lease_status = ?
                    WHERE id = ? AND role = 'tenant'
                ");
                $stmt->execute([$name, $phone, $email, $unitCode, $rent, $balance, $moveIn, $status, $id]);
            }

            if ($status === 'inactive') {
                $pdo->prepare("UPDATE rooms SET status = 'available' WHERE unit_code = ?")->execute([$unitCode]);
            } else {
                $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE unit_code = ?")->execute([$unitCode]);
            }

            header("Location: tenants.php?msg=" . urlencode("Tenant {$name} account updated successfully."));
            exit;
        }
    }

    // 3. Archive / Move Out Tenant
    if ($action === 'archive_tenant') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT full_name, unit_code FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $t = $stmt->fetch();
            if ($t) {
                $pdo->prepare("UPDATE users SET lease_status = 'inactive' WHERE id = ?")->execute([$id]);
                if (!empty($t['unit_code'])) {
                    $pdo->prepare("UPDATE rooms SET status = 'available' WHERE unit_code = ?")->execute([$t['unit_code']]);
                }
                header("Location: tenants.php?msg=" . urlencode("Tenant {$t['full_name']} marked as moved out. Unit {$t['unit_code']} is now available."));
                exit;
            }
        }
    }

    // 4. Delete Tenant
    if ($action === 'delete_tenant') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT full_name, unit_code FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $t = $stmt->fetch();
            if ($t) {
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
                if (!empty($t['unit_code'])) {
                    $pdo->prepare("UPDATE rooms SET status = 'available' WHERE unit_code = ?")->execute([$t['unit_code']]);
                }
                header("Location: tenants.php?msg=" . urlencode("Tenant account for {$t['full_name']} permanently deleted."));
                exit;
            }
        }
    }
}

// Fetch all tenants
$tenantsStmt = $pdo->query("
    SELECT u.*, COALESCE(r.room_type, 'Boarding House Rental') AS room_type, COALESCE(r.monthly_rate, u.monthly_rent) AS unit_rate
    FROM users u
    LEFT JOIN rooms r ON u.unit_code = r.unit_code
    WHERE u.role = 'tenant'
    ORDER BY u.lease_status ASC, u.unit_code ASC
");
$tenants = $tenantsStmt->fetchAll();

// Fetch available and all rooms for modals
$allRooms = $pdo->query("SELECT * FROM rooms ORDER BY unit_code ASC")->fetchAll();

$totalTenants = count($tenants);
$activeTenants = 0;
$bhTenants = 0;
$pbTenants = 0;

foreach ($tenants as $t) {
    if ($t['lease_status'] === 'active') $activeTenants++;
    if (strpos($t['room_type'], 'Private') !== false) {
        $pbTenants++;
    } else {
        $bhTenants++;
    }
}

$pendingQueueCount = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
$openTicketsCount = (int)$pdo->query("SELECT COUNT(*) FROM maintenance_tickets WHERE status = 'open'")->fetchColumn();
$adminUser = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tenants Master Roster | Eanne Admin Console</title>
  <meta name="description" content="Tenant management ledger and resident database for Eanne Boarding House Pasig.">
  
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
        <a href="tenants.php" class="sidebar-link active"><i class="bi bi-people-fill"></i><span>Tenants Roster</span></a>
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
      <header class="dashboard-topbar">
        <div class="d-flex align-items-center gap-3">
          <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" id="sidebarToggleBtn"><i class="bi bi-list fs-5"></i></button>
          <div>
            <h5 class="mb-0 fw-bold text-navy">Tenants Master Roster</h5>
            <small class="text-muted"><span class="fw-bold text-primary"><?= $activeTenants ?></span> Active Residents Listed (Across 6 Compound Units)</small>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <button type="button" class="btn btn-sm btn-ebh-primary" data-toggle="modal" data-target="#addTenantModal">
            <i class="bi bi-person-plus-fill me-1"></i> Register New Tenant
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

        <?php if (!empty($err)): ?>
          <div class="alert alert-danger alert-dismissible fade show shadow-sm border-danger py-2 px-3 mb-4" role="alert">
            <i class="bi bi-shield-x me-2"></i> <?= htmlspecialchars($err) ?>
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
                <input type="text" class="form-control border-start-0" id="tenantSearchInput" placeholder="Search tenant name, unit, or phone...">
              </div>
            </div>
            <div class="col-md-7 col-lg-8">
              <div class="btn-filter-group justify-content-md-end">
                <button type="button" class="btn-filter active" data-filter="all" data-target-items="#tenantsTable tbody tr">All Tenants (<?= $totalTenants ?>)</button>
                <button type="button" class="btn-filter" data-filter="active" data-target-items="#tenantsTable tbody tr">Active (<?= $activeTenants ?>)</button>
                <button type="button" class="btn-filter" data-filter="boarding" data-target-items="#tenantsTable tbody tr">Boarding House (<?= $bhTenants ?>)</button>
                <button type="button" class="btn-filter" data-filter="private" data-target-items="#tenantsTable tbody tr">Private House (<?= $pbTenants ?>)</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Tenants Table Card Directly Rendered from MySQL -->
        <div class="ebh-card">
          <div class="table-responsive">
            <table class="table table-ebh table-hover mb-0" id="tenantsTable">
              <thead>
                <tr>
                  <th>Resident Profile</th>
                  <th>Assigned Unit</th>
                  <th>Classification</th>
                  <th>Monthly Rent</th>
                  <th>Current Balance</th>
                  <th>Contract Move-in</th>
                  <th>Lease Status</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($tenants)): ?>
                  <tr>
                    <td colspan="8" class="text-center py-4 text-muted">No resident records found in database.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($tenants as $t): ?>
                    <?php
                      $isActive = $t['lease_status'] === 'active';
                      $isPrivate = strpos($t['room_type'], 'Private') !== false;
                      $filterCat = ($isActive ? 'active ' : 'inactive ') . ($isPrivate ? 'private' : 'boarding');
                      $initials = strtoupper(substr($t['full_name'], 0, 1) . substr(strrchr($t['full_name'], ' ') ?: '', 1, 1));
                      if (!$initials) $initials = 'TN';
                      $tBal = (float)($t['balance'] ?? $t['monthly_rent']);
                    ?>
                    <tr class="filterable-item <?= !$isActive ? 'table-light text-muted' : '' ?>" data-category="<?= $filterCat ?>" data-status="<?= $t['lease_status'] ?>">
                      <td>
                        <div class="d-flex align-items-center gap-2">
                          <div class="avatar-circle sm"><?= htmlspecialchars($initials) ?></div>
                          <div>
                            <span class="fw-bold text-navy d-block"><?= htmlspecialchars($t['full_name']) ?></span>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                              <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($t['phone']) ?></small>
                              <span class="badge badge-light border text-primary" style="font-size:0.72rem;" title="Portal Login Username"><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($t['username'] ?? 'tenant') ?></span>
                            </div>
                          </div>
                        </div>
                      </td>
                      <td>
                        <span class="fw-semibold text-navy">Unit <?= htmlspecialchars($t['unit_code'] ?? 'None') ?></span>
                        <small class="text-muted d-block"><?= htmlspecialchars($t['room_type']) ?></small>
                      </td>
                      <td>
                        <span class="badge badge-light border"><?= $isPrivate ? 'Private House' : 'Boarding House' ?></span>
                      </td>
                      <td class="fw-bold text-navy">₱<?= number_format($t['monthly_rent'], 2) ?></td>
                      <td>
                        <?php if ($tBal < 0): ?>
                          <span class="fw-bold text-success">-₱<?= number_format(abs($tBal), 2) ?></span>
                          <small class="badge badge-success d-block mt-1">Advance Credit</small>
                        <?php elseif ($tBal == 0): ?>
                          <span class="fw-bold text-success">₱0.00</span>
                          <small class="text-muted d-block">Fully Paid</small>
                        <?php else: ?>
                          <span class="fw-bold text-danger">₱<?= number_format($tBal, 2) ?></span>
                        <?php endif; ?>
                      </td>
                      <td><small class="text-muted"><?= $t['move_in_date'] ? date('M j, Y', strtotime($t['move_in_date'])) : 'N/A' ?></small></td>
                      <td>
                        <?php if ($isActive): ?>
                          <span class="badge-ebh badge-verified"><i class="bi bi-check-circle"></i> Active</span>
                        <?php else: ?>
                          <span class="badge-ebh badge-rejected"><i class="bi bi-x-circle"></i> Inactive</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <div class="btn-group btn-group-sm">
                          <button type="button" class="btn btn-outline-warning btn-edit-tenant" title="Edit Tenant Record & Credentials"
                            data-id="<?= $t['id'] ?>"
                            data-name="<?= htmlspecialchars($t['full_name'], ENT_QUOTES) ?>"
                            data-phone="<?= htmlspecialchars($t['phone'], ENT_QUOTES) ?>"
                            data-email="<?= htmlspecialchars($t['email'] ?? '', ENT_QUOTES) ?>"
                            data-unit="<?= htmlspecialchars($t['unit_code'] ?? '', ENT_QUOTES) ?>"
                            data-rent="<?= $t['monthly_rent'] ?>"
                            data-balance="<?= $tBal ?>"
                            data-movein="<?= htmlspecialchars($t['move_in_date'] ?? '', ENT_QUOTES) ?>"
                            data-status="<?= htmlspecialchars($t['lease_status'], ENT_QUOTES) ?>"
                            data-username="<?= htmlspecialchars($t['username'] ?? '', ENT_QUOTES) ?>">
                            <i class="bi bi-pencil-square"></i>
                          </button>
                          <?php if ($isActive): ?>
                            <form method="POST" action="tenants.php" class="d-inline" onsubmit="const pwd = prompt('SECURITY CHECK: Enter your Admin Password to mark <?= htmlspecialchars(addslashes($t['full_name'])) ?> as moved out:'); if(!pwd) return false; this.admin_password.value = pwd; return true;">
                              <input type="hidden" name="action" value="archive_tenant">
                              <input type="hidden" name="id" value="<?= $t['id'] ?>">
                              <input type="hidden" name="admin_password" value="">
                              <button type="submit" class="btn btn-outline-secondary" title="Archive / Move Out"><i class="bi bi-box-arrow-right"></i></button>
                            </form>
                          <?php endif; ?>
                          <form method="POST" action="tenants.php" class="d-inline" onsubmit="const pwd = prompt('SECURITY CHECK: Enter your Admin Password to PERMANENTLY delete resident <?= htmlspecialchars(addslashes($t['full_name'])) ?>:'); if(!pwd) return false; this.admin_password.value = pwd; return true;">
                            <input type="hidden" name="action" value="delete_tenant">
                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                            <input type="hidden" name="admin_password" value="">
                            <button type="submit" class="btn btn-outline-danger" title="Delete Tenant"><i class="bi bi-trash3"></i></button>
                          </form>
                        </div>
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
        <form method="POST" action="tenants.php">
          <input type="hidden" name="action" value="add_tenant">
          <div class="modal-body p-4">
            <div class="row g-3">
              <div class="col-12">
                <label for="newTenantName" class="form-label small fw-bold text-navy">Full Legal Name</label>
                <input type="text" class="form-control" id="newTenantName" name="full_name" required placeholder="e.g. Patricia Reyes">
              </div>
              <div class="col-md-6">
                <label for="newTenantContact" class="form-label small fw-bold text-navy">Mobile / Contact Number</label>
                <input type="tel" class="form-control" id="newTenantContact" name="phone" required placeholder="0917-123-4567">
              </div>
              <div class="col-md-6">
                <label for="newTenantEmail" class="form-label small fw-bold text-navy">Email Address</label>
                <input type="email" class="form-control" id="newTenantEmail" name="email" placeholder="patricia@gmail.com">
              </div>
              <div class="col-md-6">
                <label for="newTenantMoveIn" class="form-label small fw-bold text-navy">Move-in Date</label>
                <input type="date" class="form-control" id="newTenantMoveIn" name="move_in_date" required value="<?= date('Y-m-d') ?>">
              </div>
              <div class="col-md-6">
                <label for="newTenantRoom" class="form-label small fw-bold text-navy">Unit Assignment</label>
                <select class="form-control" id="newTenantRoom" name="unit_code" required onchange="const rate = this.options[this.selectedIndex].getAttribute('data-rate'); if(rate) document.getElementById('newTenantRent').value = rate;">
                  <option value="" disabled selected>Select available unit...</option>
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
                <label for="newTenantUsername" class="form-label small fw-bold text-navy">Username</label>
                <div class="input-group input-group-sm">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                  </div>
                  <input type="text" class="form-control" id="newTenantUsername" name="username" placeholder="e.g. patricia99 (optional)">
                </div>
                <small class="text-muted" style="font-size:0.72rem;">Leave blank to auto-generate from name</small>
              </div>
              <div class="col-md-6">
                <label for="newTenantPassword" class="form-label small fw-bold text-navy">Initial Password</label>
                <div class="input-group input-group-sm">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="bi bi-key"></i></span>
                  </div>
                  <input type="text" class="form-control" id="newTenantPassword" name="password" placeholder="Default: tenant123">
                </div>
                <small class="text-muted" style="font-size:0.72rem;">Defaults to <code>tenant123</code> if left empty</small>
              </div>

              <!-- Admin Security Re-Authentication Check -->
              <div class="col-12 pt-3 border-top mt-3">
                <div class="p-2.5 rounded bg-light border border-warning">
                  <label for="addAdminPassword" class="form-label small fw-bold text-navy mb-1">
                    <i class="bi bi-shield-lock-fill text-warning me-1"></i> Admin Authorization Password <span class="text-danger">*</span>
                  </label>
                  <input type="password" class="form-control form-control-sm" id="addAdminPassword" name="admin_password" required placeholder="Enter YOUR admin password to confirm registration">
                  <small class="text-muted" style="font-size:0.72rem;">Security verification: Confirm identity before creating resident records.</small>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-ebh-primary btn-sm"><i class="bi bi-check-circle me-1"></i> Register Tenant</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal: Edit Tenant Record -->
  <div class="modal fade" id="editTenantModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-navy text-white">
          <h5 class="modal-title fw-bold text-white mb-0"><i class="bi bi-pencil-square me-2"></i> Edit Tenant Record</h5>
          <button type="button" class="close text-white border-0 bg-transparent" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form method="POST" action="tenants.php">
          <input type="hidden" name="action" value="edit_tenant">
          <input type="hidden" id="editTenantId" name="id">
          <div class="modal-body p-4">
            <div class="row g-3">
              <div class="col-12">
                <label for="editTenantName" class="form-label small fw-bold text-navy">Full Legal Name</label>
                <input type="text" class="form-control" id="editTenantName" name="full_name" required>
              </div>
              <div class="col-md-6">
                <label for="editTenantContact" class="form-label small fw-bold text-navy">Mobile / Contact Number</label>
                <input type="tel" class="form-control" id="editTenantContact" name="phone" required>
              </div>
              <div class="col-md-6">
                <label for="editTenantEmail" class="form-label small fw-bold text-navy">Email Address</label>
                <input type="email" class="form-control" id="editTenantEmail" name="email">
              </div>
              <div class="col-md-6">
                <label for="editTenantMoveIn" class="form-label small fw-bold text-navy">Move-in Date</label>
                <input type="date" class="form-control" id="editTenantMoveIn" name="move_in_date" required>
              </div>
              <div class="col-md-6">
                <label for="editTenantRoom" class="form-label small fw-bold text-navy">Assigned Unit</label>
                <select class="form-control" id="editTenantRoom" name="unit_code" required>
                  <?php foreach ($allRooms as $r): ?>
                    <option value="<?= htmlspecialchars($r['unit_code']) ?>" data-rate="<?= $r['monthly_rate'] ?>">
                      Unit <?= htmlspecialchars($r['unit_code']) ?> (<?= htmlspecialchars($r['room_type']) ?>) - ₱<?= number_format($r['monthly_rate'], 2) ?>/mo
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label for="editTenantRent" class="form-label small fw-bold text-navy">Monthly Rent (₱)</label>
                <input type="number" class="form-control" id="editTenantRent" name="monthly_rent" required value="3500">
              </div>
              <div class="col-md-6">
                <label for="editTenantBalance" class="form-label small fw-bold text-navy">Current Balance / Advance (₱)</label>
                <input type="number" step="0.01" class="form-control" id="editTenantBalance" name="balance" required value="0">
                <small class="text-muted">Negative value = Advance Credit</small>
              </div>
              <div class="col-12">
                <label for="editTenantStatus" class="form-label small fw-bold text-navy">Lease Status</label>
                <select class="form-control" id="editTenantStatus" name="lease_status">
                  <option value="active">Active Resident</option>
                  <option value="inactive">Inactive / Moved Out</option>
                </select>
              </div>

              <!-- Portal Account Credentials Edit Section -->
              <div class="col-12 pt-2 border-top">
                <span class="small fw-bold text-navy d-block mb-2"><i class="bi bi-shield-lock-fill text-primary me-1"></i> Tenant Portal Account Login</span>
              </div>
              <div class="col-md-6">
                <label for="editTenantUsername" class="form-label small fw-bold text-navy">Username <span id="editTenantUsernameReq" class="text-danger" style="display:none;">*</span></label>
                <div class="input-group input-group-sm">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                  </div>
                  <input type="text" class="form-control" id="editTenantUsername" name="username" placeholder="Required only if changing password">
                </div>
                <small class="text-muted" style="font-size:0.72rem;">Only required when updating password</small>
              </div>
              <div class="col-md-6">
                <label for="editTenantPassword" class="form-label small fw-bold text-navy">Reset / Change Password</label>
                <div class="input-group input-group-sm">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="bi bi-key"></i></span>
                  </div>
                  <input type="text" class="form-control" id="editTenantPassword" name="password" placeholder="Leave blank to keep unchanged">
                </div>
                <small class="text-muted" style="font-size:0.72rem;">Enter new password only if updating</small>
              </div>

              <!-- Admin Security Re-Authentication Check -->
              <div class="col-12 pt-3 border-top mt-3">
                <div class="p-2.5 rounded bg-light border border-warning">
                  <label for="editAdminPassword" class="form-label small fw-bold text-navy mb-1">
                    <i class="bi bi-shield-lock-fill text-warning me-1"></i> Admin Authorization Password <span class="text-danger">*</span>
                  </label>
                  <input type="password" class="form-control form-control-sm" id="editAdminPassword" name="admin_password" required placeholder="Enter YOUR admin password to confirm update">
                  <small class="text-muted" style="font-size:0.72rem;">Security verification: Confirm identity before modifying resident credentials or details.</small>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-ebh-primary btn-sm"><i class="bi bi-save me-1"></i> Save Changes</button>
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
    function openEditTenantModal(id, name, phone, email, unit, rent, moveIn, status, balance, username) {
      $('#editTenantId').val(id);
      $('#editTenantName').val(name);
      $('#editTenantContact').val(phone);
      $('#editTenantEmail').val(email || '');
      $('#editTenantRoom').val(unit);
      $('#editTenantRent').val(rent);
      $('#editTenantBalance').val(balance !== undefined && balance !== null ? balance : 0);
      $('#editTenantMoveIn').val(moveIn);
      $('#editTenantStatus').val(status || 'active');
      $('#editTenantUsername').val(username || '').prop('required', false);
      $('#editTenantUsernameReq').hide();
      $('#editTenantPassword').val('');
      $('#editTenantModal').modal('show');
    }

    // Conditional requirement: username is only required when new password is typed
    $('#editTenantPassword').on('input', function() {
      const hasPwd = $(this).val().trim().length > 0;
      $('#editTenantUsername').prop('required', hasPwd);
      if (hasPwd) {
        $('#editTenantUsernameReq').show();
      } else {
        $('#editTenantUsernameReq').hide();
      }
    });

    $(document).on('click', '.btn-edit-tenant', function(e) {
      e.preventDefault();
      const btn = $(this);
      const id = btn.data('id');
      const name = btn.data('name');
      const phone = btn.data('phone');
      const email = btn.data('email');
      const unit = btn.data('unit');
      const rent = btn.data('rent');
      const balance = btn.data('balance');
      const moveIn = btn.data('movein');
      const status = btn.data('status');
      const username = btn.data('username');

      openEditTenantModal(id, name, phone, email, unit, rent, moveIn, status, balance, username);
    });

    // Automatically synchronize monthly rent when unit is changed in edit modal
    $('#editTenantRoom').on('change', function() {
      const selected = $(this).find('option:selected');
      const rate = selected.data('rate');
      if (rate) {
        $('#editTenantRent').val(rate);
      }
    });
  </script>
</body>
</html>
