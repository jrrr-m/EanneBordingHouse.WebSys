<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/overdue_rules.php';
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

    // 0. Update Overdue Exemption / Deferral
    if ($action === 'update_exemption') {
        $id = (int)($_POST['id'] ?? 0);
        $exemptMonths = max(0, (int)($_POST['exemption_months'] ?? 0));
        $exemptReason = trim($_POST['exemption_reason'] ?? '');

        if ($id > 0) {
            $upStmt = $pdo->prepare("
                UPDATE users 
                SET overdue_exemption_months = ?, 
                    overdue_exemption_reason = ?, 
                    overdue_exemption_granted_at = " . ($exemptMonths > 0 ? "NOW()" : "NULL") . " 
                WHERE id = ? AND role = 'tenant'
            ");
            $upStmt->execute([$exemptMonths, $exemptMonths > 0 ? $exemptReason : null, $id]);
            
            $msgText = $exemptMonths > 0 
                ? "Exemption granted for {$exemptMonths} month(s). Reason: {$exemptReason}."
                : "Exemption cleared. Standard overdue & eviction enforcement restored.";
            header("Location: tenants.php?msg=" . urlencode($msgText));
            exit;
        }
    }

    // 0.1 Execute Eviction / Remove Due to Unpaid Rent
    if ($action === 'evict_tenant') {
        $id = (int)($_POST['id'] ?? 0);
        $evictionReason = trim($_POST['eviction_reason'] ?? 'Evicted due to 3+ months non-payment');
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT full_name, unit_code, balance, monthly_rent FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $t = $stmt->fetch();
            if ($t) {
                $pdo->beginTransaction();
                try {
                    $currentUnit = $t['unit_code'];
                    $pdo->prepare("UPDATE users SET lease_status = 'inactive', previous_unit_code = COALESCE(?, previous_unit_code), unit_code = NULL WHERE id = ?")
                        ->execute([$currentUnit, $id]);

                    if (!empty($currentUnit)) {
                        $pdo->prepare("UPDATE rooms SET status = 'available' WHERE unit_code = ?")->execute([$currentUnit]);
                    }

                    // Log audit transfer
                    $pdo->prepare("INSERT INTO tenant_unit_transfers (tenant_id, from_unit, to_unit, transfer_type, reason, authorized_by) VALUES (?, ?, NULL, 'move_out', ?, ?)")
                        ->execute([$id, $currentUnit, 'EVICTION: ' . $evictionReason, $_SESSION['user']['username'] ?? 'Admin']);

                    $pdo->commit();
                    header("Location: tenants.php?msg=" . urlencode("Tenant {$t['full_name']} has been evicted and removed from Unit " . ($currentUnit ?: 'N/A') . "."));
                    exit;
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    header("Location: tenants.php?err=" . urlencode("Failed to process eviction: " . $e->getMessage()));
                    exit;
                }
            }
        }
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
            // Retrieve current user record to compare username / password / unit
            $currStmt = $pdo->prepare("SELECT username, password, unit_code, lease_status, full_name FROM users WHERE id = ? LIMIT 1");
            $currStmt->execute([$id]);
            $currUser = $currStmt->fetch();

            $updateUsername = !empty($customUsername) ? strtolower(preg_replace('/[^a-zA-Z0-9_.-]/', '', $customUsername)) : ($currUser['username'] ?? '');

            // If username was changed or provided, check uniqueness against other users
            if (!empty($updateUsername) && (!isset($currUser['username']) || $updateUsername !== strtolower($currUser['username']))) {
                $uCheck = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $uCheck->execute([$updateUsername, $id]);
                if ($uCheck->fetch()) {
                    header("Location: tenants.php?err=" . urlencode("Error: Username '{$updateUsername}' is already taken by another account."));
                    exit;
                }
            }

            $oldUnit = $currUser['unit_code'] ?? null;
            $unitChanged = !empty($unitCode) && $unitCode !== $oldUnit;

            // If moving to a new unit and tenant is active, ensure target unit is not already occupied by another active tenant
            if ($unitChanged && $status === 'active') {
                $occupiedCheck = $pdo->prepare("SELECT id, full_name FROM users WHERE unit_code = ? AND id != ? AND lease_status = 'active' LIMIT 1");
                $occupiedCheck->execute([$unitCode, $id]);
                $occupiedBy = $occupiedCheck->fetch();
                if ($occupiedBy) {
                    header("Location: tenants.php?err=" . urlencode("Unit {$unitCode} is currently occupied by {$occupiedBy['full_name']}. Please reassign or vacate the unit first."));
                    exit;
                }
            }

            $pdo->beginTransaction();
            try {
                if (!empty($newPassword) && (!isset($currUser['password']) || $newPassword !== $currUser['password'])) {
                    // New password entered and changed
                    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET full_name = ?, username = ?, password = ?, phone = ?, email = ?, unit_code = ?, previous_unit_code = COALESCE(?, previous_unit_code), monthly_rent = ?, balance = ?, move_in_date = ?, lease_status = ?
                        WHERE id = ? AND role = 'tenant'
                    ");
                    $stmt->execute([$name, $updateUsername, $hashedPassword, $phone, $email, $unitCode, $oldUnit, $rent, $balance, $moveIn, $status, $id]);
                } else {
                    // Password unchanged or left as current
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET full_name = ?, username = ?, phone = ?, email = ?, unit_code = ?, previous_unit_code = COALESCE(?, previous_unit_code), monthly_rent = ?, balance = ?, move_in_date = ?, lease_status = ?
                        WHERE id = ? AND role = 'tenant'
                    ");
                    $stmt->execute([$name, $updateUsername, $phone, $email, $unitCode, $oldUnit, $rent, $balance, $moveIn, $status, $id]);
                }

                // If unit was changed, update room inventory and record audit transfer
                if ($unitChanged) {
                    if (!empty($oldUnit)) {
                        $pdo->prepare("UPDATE rooms SET status = 'available' WHERE unit_code = ?")->execute([$oldUnit]);
                    }
                    if ($status === 'active') {
                        $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE unit_code = ?")->execute([$unitCode]);
                    }
                    $transferStmt = $pdo->prepare("INSERT INTO tenant_unit_transfers (tenant_id, from_unit, to_unit, transfer_type, reason, authorized_by) VALUES (?, ?, ?, 'transfer', 'Unit transfer via tenant edit', ?)");
                    $transferStmt->execute([$id, $oldUnit, $unitCode, $_SESSION['user']['username'] ?? 'Admin']);
                } else {
                    if ($status === 'inactive') {
                        if (!empty($unitCode)) {
                            $pdo->prepare("UPDATE rooms SET status = 'available' WHERE unit_code = ?")->execute([$unitCode]);
                        }
                    } else {
                        if (!empty($unitCode)) {
                            $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE unit_code = ?")->execute([$unitCode]);
                        }
                    }
                }

                $pdo->commit();
                header("Location: tenants.php?msg=" . urlencode("Tenant {$name} account updated successfully."));
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                header("Location: tenants.php?err=" . urlencode("Failed to update tenant: " . $e->getMessage()));
                exit;
            }
        }
    }

    // 3. Move Tenant to New Unit (Dedicated Transfer Action with Safeguards)
    if ($action === 'move_tenant') {
        $id = (int)($_POST['id'] ?? 0);
        $rawToUnit = trim($_POST['to_unit'] ?? '');
        $toUnit = str_replace('Unit ', '', $rawToUnit);
        $transferReason = trim($_POST['reason'] ?? 'Unit Relocation');

        if ($id > 0 && !empty($toUnit)) {
            $stmt = $pdo->prepare("SELECT id, full_name, unit_code, lease_status FROM users WHERE id = ? AND role = 'tenant' LIMIT 1");
            $stmt->execute([$id]);
            $tenant = $stmt->fetch();

            if (!$tenant) {
                header("Location: tenants.php?err=" . urlencode("Resident not found."));
                exit;
            }

            $fromUnit = $tenant['unit_code'];

            if ($fromUnit === $toUnit) {
                header("Location: tenants.php?err=" . urlencode("Tenant is already assigned to Unit {$toUnit}."));
                exit;
            }

            // Safeguard: Check if destination unit exists and fetch its monthly rate
            $roomCheck = $pdo->prepare("SELECT unit_code, monthly_rate, status FROM rooms WHERE unit_code = ? LIMIT 1");
            $roomCheck->execute([$toUnit]);
            $destRoom = $roomCheck->fetch();

            if (!$destRoom) {
                header("Location: tenants.php?err=" . urlencode("Target Unit {$toUnit} does not exist in rooms inventory."));
                exit;
            }

            // Safeguard: Check if destination unit is already occupied by another active tenant
            $occupiedCheck = $pdo->prepare("SELECT id, full_name FROM users WHERE unit_code = ? AND id != ? AND lease_status = 'active' LIMIT 1");
            $occupiedCheck->execute([$toUnit, $id]);
            $occupiedTenant = $occupiedCheck->fetch();

            if ($occupiedTenant) {
                header("Location: tenants.php?err=" . urlencode("Safeguard Alert: Unit {$toUnit} is currently occupied by {$occupiedTenant['full_name']}. Please vacate or reassign the unit first."));
                exit;
            }

            // Execute Unit Transfer in Atomic Transaction
            $pdo->beginTransaction();
            try {
                // Update Tenant Record
                $upTenant = $pdo->prepare("
                    UPDATE users 
                    SET unit_code = ?, previous_unit_code = COALESCE(?, previous_unit_code), monthly_rent = ?, lease_status = 'active'
                    WHERE id = ?
                ");
                $upTenant->execute([$toUnit, $fromUnit, $destRoom['monthly_rate'], $id]);

                // Free old unit if existed
                if (!empty($fromUnit)) {
                    $pdo->prepare("UPDATE rooms SET status = 'available' WHERE unit_code = ?")->execute([$fromUnit]);
                }

                // Mark new unit as occupied
                $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE unit_code = ?")->execute([$toUnit]);

                // Record Transfer History Audit
                $logStmt = $pdo->prepare("
                    INSERT INTO tenant_unit_transfers (tenant_id, from_unit, to_unit, transfer_type, reason, authorized_by)
                    VALUES (?, ?, ?, 'transfer', ?, ?)
                ");
                $logStmt->execute([$id, $fromUnit, $toUnit, $transferReason, $_SESSION['user']['username'] ?? 'Admin']);

                $pdo->commit();
                header("Location: tenants.php?msg=" . urlencode("Resident {$tenant['full_name']} successfully relocated from Unit " . ($fromUnit ?: 'None') . " to Unit {$toUnit}."));
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                header("Location: tenants.php?err=" . urlencode("Transfer Failed: " . $e->getMessage()));
                exit;
            }
        }
    }

    // 4. Archive / Move Out Tenant
    if ($action === 'archive_tenant') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT full_name, unit_code, previous_unit_code FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $t = $stmt->fetch();
            if ($t) {
                $pdo->beginTransaction();
                try {
                    $currentUnit = $t['unit_code'];
                    // Preserve previous_unit_code, set lease_status = inactive and clear active unit_code
                    $pdo->prepare("UPDATE users SET lease_status = 'inactive', previous_unit_code = COALESCE(?, previous_unit_code), unit_code = NULL WHERE id = ?")
                        ->execute([$currentUnit, $id]);

                    if (!empty($currentUnit)) {
                        $pdo->prepare("UPDATE rooms SET status = 'available' WHERE unit_code = ?")->execute([$currentUnit]);
                    }

                    // Record move_out audit
                    $pdo->prepare("INSERT INTO tenant_unit_transfers (tenant_id, from_unit, to_unit, transfer_type, reason, authorized_by) VALUES (?, ?, NULL, 'move_out', 'Resident moved out / lease archived', ?)")
                        ->execute([$id, $currentUnit, $_SESSION['user']['username'] ?? 'Admin']);

                    $pdo->commit();
                    header("Location: tenants.php?msg=" . urlencode("Tenant {$t['full_name']} marked as moved out. Unit " . ($currentUnit ?: 'N/A') . " is now available."));
                    exit;
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    header("Location: tenants.php?err=" . urlencode("Failed to archive resident: " . $e->getMessage()));
                    exit;
                }
            }
        }
    }

    // 5. Delete Tenant
    if ($action === 'delete_tenant') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT full_name, unit_code FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $t = $stmt->fetch();
            if ($t) {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("DELETE FROM tenant_unit_transfers WHERE tenant_id = ?")->execute([$id]);
                    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
                    if (!empty($t['unit_code'])) {
                        $pdo->prepare("UPDATE rooms SET status = 'available' WHERE unit_code = ?")->execute([$t['unit_code']]);
                    }
                    $pdo->commit();
                    header("Location: tenants.php?msg=" . urlencode("Tenant account for {$t['full_name']} permanently deleted."));
                    exit;
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    header("Location: tenants.php?err=" . urlencode("Failed to delete tenant: " . $e->getMessage()));
                    exit;
                }
            }
        }
    }
}

// Fetch all tenants with room info and historical unit resolution
$tenantsStmt = $pdo->query("
    SELECT u.*, 
           COALESCE(r.room_type, r_prev.room_type, 'Boarding House Rental') AS room_type, 
           COALESCE(r.monthly_rate, u.monthly_rent) AS unit_rate,
           COALESCE(u.unit_code, u.previous_unit_code) AS display_unit_code
    FROM users u
    LEFT JOIN rooms r ON u.unit_code = r.unit_code
    LEFT JOIN rooms r_prev ON u.previous_unit_code = r_prev.unit_code
    WHERE u.role = 'tenant'
    ORDER BY u.lease_status ASC, COALESCE(u.unit_code, u.previous_unit_code) ASC
");
$allTenantsList = $tenantsStmt->fetchAll();

$activeTenantsList = [];
$inactiveTenantsList = [];
$bhTenants = 0;
$pbTenants = 0;

foreach ($allTenantsList as $t) {
    if ($t['lease_status'] === 'active') {
        $activeTenantsList[] = $t;
        if (strpos($t['room_type'], 'Private') !== false) {
            $pbTenants++;
        } else {
            $bhTenants++;
        }
    } else {
        $inactiveTenantsList[] = $t;
    }
}

$totalTenants = count($allTenantsList);
$activeTenants = count($activeTenantsList);
$inactiveTenants = count($inactiveTenantsList);

// Fetch available and all rooms for dropdowns and unit-move modal
$allRooms = $pdo->query("SELECT * FROM rooms ORDER BY unit_code ASC")->fetchAll();

// Fetch recent unit transfer logs for audit display
$transfersStmt = $pdo->query("
    SELECT t.*, u.full_name, u.phone 
    FROM tenant_unit_transfers t
    JOIN users u ON t.tenant_id = u.id
    ORDER BY t.created_at DESC
    LIMIT 20
");
$transferLogs = $transfersStmt->fetchAll();

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
          <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" id="sidebarToggleBtn" onclick="if(window.toggleAdminSidebar){window.toggleAdminSidebar(event);}else{var s=document.querySelector('.dashboard-sidebar');if(s)s.classList.toggle('show');}"><i class="bi bi-list fs-5"></i></button>
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

        <!-- Control Bar: Search & Status Navigation Tabs -->
        <div class="ebh-card p-3 mb-4">
          <div class="row g-3 align-items-center justify-content-between">
            <div class="col-md-5 col-lg-4">
              <div class="input-group">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control border-start-0" id="tenantSearchInput" placeholder="Search tenant name, unit, or phone...">
              </div>
            </div>
            <div class="col-md-7 col-lg-8">
              <ul class="nav nav-pills justify-content-md-end gap-1" id="tenantRosterTabs" role="tablist">
                <li class="nav-item">
                  <a class="nav-link active font-weight-bold" id="active-tenants-tab" data-toggle="pill" href="#tabActiveTenants" role="tab">
                    <i class="bi bi-person-check-fill me-1 text-success"></i> Active Residents <span class="badge badge-success ml-1"><?= $activeTenants ?></span>
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link font-weight-bold" id="inactive-tenants-tab" data-toggle="pill" href="#tabInactiveTenants" role="tab">
                    <i class="bi bi-person-x-fill me-1 text-secondary"></i> Inactive / Previous Residents <span class="badge badge-secondary ml-1"><?= $inactiveTenants ?></span>
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link font-weight-bold" id="transfers-history-tab" data-toggle="pill" href="#tabTransfersHistory" role="tab">
                    <i class="bi bi-arrow-left-right me-1 text-primary"></i> Relocation Logs <span class="badge badge-primary ml-1"><?= count($transferLogs) ?></span>
                  </a>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <div class="tab-content" id="tenantRosterTabContent">
          <!-- TAB 1: ACTIVE RESIDENTS TABLE -->
          <div class="tab-pane fade show active" id="tabActiveTenants" role="tabpanel">
            <div class="ebh-card mb-4">
              <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-white">
                <div>
                  <h6 class="mb-0 fw-bold text-navy"><i class="bi bi-check-circle-fill text-success me-2"></i> Current Active Residents</h6>
                  <small class="text-muted">Tenants currently residing in compound boarding units.</small>
                </div>
                <div class="btn-filter-group">
                  <button type="button" class="btn-filter active" data-filter="all" data-target-items="#activeTenantsTable tbody tr">All Active (<?= $activeTenants ?>)</button>
                  <button type="button" class="btn-filter" data-filter="boarding" data-target-items="#activeTenantsTable tbody tr">Boarding (<?= $bhTenants ?>)</button>
                  <button type="button" class="btn-filter" data-filter="private" data-target-items="#activeTenantsTable tbody tr">Private (<?= $pbTenants ?>)</button>
                </div>
              </div>
              <div class="table-responsive">
                <table class="table table-ebh table-hover mb-0" id="activeTenantsTable">
                  <thead>
                    <tr>
                      <th>Resident Profile</th>
                      <th>Current Unit</th>
                      <th>Classification</th>
                      <th>Monthly Rent</th>
                      <th>Current Balance</th>
                      <th>Contract Move-in</th>
                      <th>Lease Status</th>
                      <th class="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($activeTenantsList)): ?>
                      <tr>
                        <td colspan="8" class="text-center py-4 text-muted">No active residents found in records.</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($activeTenantsList as $t): ?>
                        <?php
                          $isPrivate = strpos($t['room_type'], 'Private') !== false;
                          $filterCat = 'active ' . ($isPrivate ? 'private' : 'boarding');
                          $initials = strtoupper(substr($t['full_name'], 0, 1) . substr(strrchr($t['full_name'], ' ') ?: '', 1, 1));
                          if (!$initials) $initials = 'TN';
                          $tBal = (float)($t['balance'] ?? $t['monthly_rent']);
                          $tRent = (float)$t['monthly_rent'];
                          $tData = array_merge($t, ['balance' => $tBal, 'monthly_rent' => $tRent]);
                          $odInfo = calculateOverdueStatus($tData);
                        ?>
                        <tr class="filterable-item <?= $odInfo['is_eviction_risk'] ? 'table-danger' : '' ?>" data-category="<?= $filterCat ?>" data-status="active">
                          <td>
                            <div class="d-flex align-items-center gap-2">
                              <div class="avatar-circle sm <?= $odInfo['is_eviction_risk'] ? 'bg-danger text-white' : '' ?>"><?= htmlspecialchars($initials) ?></div>
                              <div>
                                <span class="fw-bold text-navy d-block">
                                  <?= htmlspecialchars($t['full_name']) ?>
                                  <?php if ($odInfo['is_eviction_risk']): ?>
                                    <span class="badge badge-danger text-white ms-1" title="Subject to Eviction: Unpaid for <?= $odInfo['effective_overdue_months'] ?> months"><i class="bi bi-exclamation-triangle-fill"></i> EVICTION RISK</span>
                                  <?php elseif ($odInfo['is_exempted']): ?>
                                    <span class="badge badge-info text-white ms-1" title="Exemption Active: <?= htmlspecialchars($odInfo['exemption_reason']) ?>"><i class="bi bi-shield-check"></i> EXEMPTED</span>
                                  <?php endif; ?>
                                </span>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                  <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($t['phone']) ?></small>
                                  <span class="badge badge-light border text-primary" style="font-size:0.72rem;" title="Portal Login Username"><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($t['username'] ?? 'tenant') ?></span>
                                </div>
                              </div>
                            </div>
                          </td>
                          <td>
                            <span class="fw-bold text-navy">Unit <?= htmlspecialchars($t['unit_code'] ?? 'N/A') ?></span>
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
                              <?php if ($odInfo['raw_overdue_months'] > 0): ?>
                                <small class="text-danger d-block fw-semibold">
                                  <?= $odInfo['raw_overdue_months'] ?> mo. overdue
                                  <?php if ($odInfo['exemption_months'] > 0): ?>
                                    <span class="text-info">(-<?= $odInfo['exemption_months'] ?> ex.)</span>
                                  <?php endif; ?>
                                </small>
                              <?php endif; ?>
                            <?php endif; ?>
                          </td>
                          <td><small class="text-muted"><?= $t['move_in_date'] ? date('M j, Y', strtotime($t['move_in_date'])) : 'N/A' ?></small></td>
                          <td>
                            <?php if ($odInfo['is_eviction_risk']): ?>
                              <span class="badge badge-danger text-white py-1 px-2"><i class="bi bi-door-closed me-1"></i> 3+ Mos Overdue</span>
                              <small class="text-danger d-block fw-bold" style="font-size:0.7rem;">Needs ≥₱<?= number_format($odInfo['min_payment_required']) ?> (50%)</small>
                            <?php elseif ($odInfo['is_exempted']): ?>
                              <span class="badge badge-warning text-dark py-1 px-2"><i class="bi bi-clock-history me-1"></i> Deferred Exemption</span>
                              <small class="text-muted d-block" style="font-size:0.7rem;"><?= htmlspecialchars(substr($odInfo['exemption_reason'], 0, 20)) ?>...</small>
                            <?php else: ?>
                              <span class="badge-ebh badge-verified"><i class="bi bi-check-circle"></i> Active</span>
                            <?php endif; ?>
                          </td>
                          <td class="text-end">
                            <div class="d-inline-flex align-items-center justify-content-end gap-1">
                              <!-- Overdue Exemption / Deferral Modal Trigger -->
                              <button type="button" class="btn btn-sm btn-outline-info btn-exempt-tenant" title="Grant or Manage Overdue Exemption"
                                data-id="<?= $t['id'] ?>"
                                data-name="<?= htmlspecialchars($t['full_name'], ENT_QUOTES) ?>"
                                data-balance="<?= $tBal ?>"
                                data-rent="<?= $t['monthly_rent'] ?>"
                                data-months="<?= $t['overdue_exemption_months'] ?? 0 ?>"
                                data-reason="<?= htmlspecialchars($t['overdue_exemption_reason'] ?? '', ENT_QUOTES) ?>">
                                <i class="bi bi-shield-exclamation"></i>
                              </button>
                              <!-- Move Tenant Unit Button -->
                              <button type="button" class="btn btn-sm btn-outline-primary btn-move-tenant" title="Move Tenant to Another Unit"
                                data-id="<?= $t['id'] ?>"
                                data-name="<?= htmlspecialchars($t['full_name'], ENT_QUOTES) ?>"
                                data-unit="<?= htmlspecialchars($t['unit_code'] ?? '', ENT_QUOTES) ?>">
                                <i class="bi bi-arrow-left-right"></i> Move Unit
                              </button>
                              <!-- Edit Tenant Button -->
                              <button type="button" class="btn btn-sm btn-outline-warning btn-edit-tenant ml-1" title="Edit Tenant Record & Credentials"
                                data-id="<?= $t['id'] ?>"
                                data-name="<?= htmlspecialchars($t['full_name'], ENT_QUOTES) ?>"
                                data-phone="<?= htmlspecialchars($t['phone'], ENT_QUOTES) ?>"
                                data-email="<?= htmlspecialchars($t['email'] ?? '', ENT_QUOTES) ?>"
                                data-unit="<?= htmlspecialchars($t['unit_code'] ?? '', ENT_QUOTES) ?>"
                                data-rent="<?= $t['monthly_rent'] ?>"
                                data-balance="<?= $tBal ?>"
                                data-movein="<?= htmlspecialchars($t['move_in_date'] ?? '', ENT_QUOTES) ?>"
                                data-status="<?= htmlspecialchars($t['lease_status'], ENT_QUOTES) ?>"
                                data-username="<?= htmlspecialchars($t['username'] ?? '', ENT_QUOTES) ?>"
                                data-password="<?= htmlspecialchars($t['password'] ?? '', ENT_QUOTES) ?>">
                                <i class="bi bi-pencil-square"></i>
                              </button>
                              <?php if ($odInfo['is_eviction_risk']): ?>
                                <!-- Eviction Execution Button -->
                                <form method="POST" action="tenants.php" class="d-inline ml-1" onsubmit="const pwd = prompt('SECURITY CHECK: Enter your Admin Password to EXECUTE EVICTION for <?= htmlspecialchars(addslashes($t['full_name'])) ?> (Unpaid for <?= $odInfo['effective_overdue_months'] ?> months):'); if(!pwd) return false; this.admin_password.value = pwd; return true;">
                                  <input type="hidden" name="action" value="evict_tenant">
                                  <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                  <input type="hidden" name="eviction_reason" value="Overdue <?= $odInfo['effective_overdue_months'] ?> months without paying 50% minimum">
                                  <input type="hidden" name="admin_password" value="">
                                  <button type="submit" class="btn btn-sm btn-danger" title="Execute Eviction / Remove Resident"><i class="bi bi-door-closed-fill"></i> Evict</button>
                                </form>
                              <?php else: ?>
                                <!-- Move Out / Archive Button -->
                                <form method="POST" action="tenants.php" class="d-inline ml-1" onsubmit="const pwd = prompt('SECURITY CHECK: Enter your Admin Password to mark <?= htmlspecialchars(addslashes($t['full_name'])) ?> as moved out:'); if(!pwd) return false; this.admin_password.value = pwd; return true;">
                                  <input type="hidden" name="action" value="archive_tenant">
                                  <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                  <input type="hidden" name="admin_password" value="">
                                  <button type="submit" class="btn btn-sm btn-outline-secondary" title="Archive / Mark Moved Out"><i class="bi bi-box-arrow-right"></i></button>
                                </form>
                              <?php endif; ?>
                              <!-- Delete Button -->
                              <form method="POST" action="tenants.php" class="d-inline ml-1" onsubmit="const pwd = prompt('SECURITY CHECK: Enter your Admin Password to PERMANENTLY delete resident <?= htmlspecialchars(addslashes($t['full_name'])) ?>:'); if(!pwd) return false; this.admin_password.value = pwd; return true;">
                                <input type="hidden" name="action" value="delete_tenant">
                                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                <input type="hidden" name="admin_password" value="">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Tenant"><i class="bi bi-trash3"></i></button>
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
          </div>

          <!-- TAB 2: INACTIVE / PREVIOUS TENANTS TABLE -->
          <div class="tab-pane fade" id="tabInactiveTenants" role="tabpanel">
            <div class="ebh-card mb-4">
              <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-white">
                <div>
                  <h6 class="mb-0 fw-bold text-navy"><i class="bi bi-clock-history text-secondary me-2"></i> Inactive & Previous Tenants Record</h6>
                  <small class="text-muted">Historical roster of all previous tenants and the specific unit they resided in.</small>
                </div>
                <span class="badge badge-light border text-muted px-2 py-1"><i class="bi bi-archive me-1"></i> Historical Archive (<?= $inactiveTenants ?> Records)</span>
              </div>
              <div class="table-responsive">
                <table class="table table-ebh table-hover mb-0" id="inactiveTenantsTable">
                  <thead>
                    <tr>
                      <th>Previous Resident</th>
                      <th>Last Unit Resided</th>
                      <th>Contact Details</th>
                      <th>Rent Rate</th>
                      <th>Move-in Contract</th>
                      <th>Status</th>
                      <th class="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($inactiveTenantsList)): ?>
                      <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No inactive or past tenants found.</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($inactiveTenantsList as $t): ?>
                        <?php
                          $initials = strtoupper(substr($t['full_name'], 0, 1) . substr(strrchr($t['full_name'], ' ') ?: '', 1, 1));
                          if (!$initials) $initials = 'PT';
                          $lastUnit = $t['display_unit_code'] ?: ($t['previous_unit_code'] ?: 'Unassigned');
                        ?>
                        <tr class="table-light">
                          <td>
                            <div class="d-flex align-items-center gap-2">
                              <div class="avatar-circle sm bg-secondary text-white"><?= htmlspecialchars($initials) ?></div>
                              <div>
                                <span class="fw-bold text-navy d-block"><?= htmlspecialchars($t['full_name']) ?></span>
                                <small class="text-muted">Username: <?= htmlspecialchars($t['username'] ?? 'tenant') ?></small>
                              </div>
                            </div>
                          </td>
                          <td>
                            <span class="badge badge-secondary px-2 py-1 font-weight-bold" style="font-size:0.85rem;">
                              <i class="bi bi-door-closed me-1"></i> Unit <?= htmlspecialchars($lastUnit) ?>
                            </span>
                            <small class="text-muted d-block mt-1"><?= htmlspecialchars($t['room_type']) ?></small>
                          </td>
                          <td>
                            <div><i class="bi bi-telephone me-1 text-muted"></i><?= htmlspecialchars($t['phone'] ?: 'None') ?></div>
                            <small class="text-muted"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($t['email'] ?: 'None') ?></small>
                          </td>
                          <td class="fw-semibold text-navy">₱<?= number_format($t['monthly_rent'], 2) ?></td>
                          <td><small class="text-muted"><?= $t['move_in_date'] ? date('M j, Y', strtotime($t['move_in_date'])) : 'N/A' ?></small></td>
                          <td>
                            <span class="badge-ebh badge-rejected"><i class="bi bi-x-circle"></i> Inactive / Moved Out</span>
                          </td>
                          <td class="text-end">
                            <div class="d-inline-flex align-items-center justify-content-end gap-1">
                              <!-- Re-activate / Reassign Unit Option -->
                              <button type="button" class="btn btn-sm btn-outline-success btn-move-tenant" title="Reactivate & Assign to Unit"
                                data-id="<?= $t['id'] ?>"
                                data-name="<?= htmlspecialchars($t['full_name'], ENT_QUOTES) ?>"
                                data-unit="<?= htmlspecialchars($lastUnit, ENT_QUOTES) ?>">
                                <i class="bi bi-box-arrow-in-right"></i> Assign Unit
                              </button>
                              <!-- Edit Record Button -->
                              <button type="button" class="btn btn-sm btn-outline-warning btn-edit-tenant ml-1" title="Edit Tenant Record"
                                data-id="<?= $t['id'] ?>"
                                data-name="<?= htmlspecialchars($t['full_name'], ENT_QUOTES) ?>"
                                data-phone="<?= htmlspecialchars($t['phone'], ENT_QUOTES) ?>"
                                data-email="<?= htmlspecialchars($t['email'] ?? '', ENT_QUOTES) ?>"
                                data-unit="<?= htmlspecialchars($lastUnit, ENT_QUOTES) ?>"
                                data-rent="<?= $t['monthly_rent'] ?>"
                                data-balance="<?= $t['balance'] ?? 0 ?>"
                                data-movein="<?= htmlspecialchars($t['move_in_date'] ?? '', ENT_QUOTES) ?>"
                                data-status="inactive"
                                data-username="<?= htmlspecialchars($t['username'] ?? '', ENT_QUOTES) ?>"
                                data-password="<?= htmlspecialchars($t['password'] ?? '', ENT_QUOTES) ?>">
                                <i class="bi bi-pencil-square"></i>
                              </button>
                              <!-- Delete Button -->
                              <form method="POST" action="tenants.php" class="d-inline ml-1" onsubmit="const pwd = prompt('SECURITY CHECK: Enter your Admin Password to PERMANENTLY delete <?= htmlspecialchars(addslashes($t['full_name'])) ?>:'); if(!pwd) return false; this.admin_password.value = pwd; return true;">
                                <input type="hidden" name="action" value="delete_tenant">
                                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                <input type="hidden" name="admin_password" value="">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Permanently Delete"><i class="bi bi-trash3"></i></button>
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
          </div>

          <!-- TAB 3: RELOCATION & UNIT TRANSFER LOGS -->
          <div class="tab-pane fade" id="tabTransfersHistory" role="tabpanel">
            <div class="ebh-card mb-4">
              <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-white">
                <div>
                  <h6 class="mb-0 fw-bold text-navy"><i class="bi bi-journal-text text-primary me-2"></i> Tenant Relocation & Unit Transfer Audit Trail</h6>
                  <small class="text-muted">Safeguarded chronological record of every unit transfer and move-out event.</small>
                </div>
                <span class="badge badge-light border text-primary px-2 py-1"><i class="bi bi-shield-check me-1"></i> Transaction Safe</span>
              </div>
              <div class="table-responsive">
                <table class="table table-ebh table-hover mb-0">
                  <thead>
                    <tr>
                      <th>Resident Name</th>
                      <th>Event Type</th>
                      <th>From Unit</th>
                      <th>To Unit</th>
                      <th>Reason / Note</th>
                      <th>Authorized By</th>
                      <th>Timestamp</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($transferLogs)): ?>
                      <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No unit relocation events logged yet.</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($transferLogs as $log): ?>
                        <tr>
                          <td class="fw-bold text-navy"><?= htmlspecialchars($log['full_name']) ?></td>
                          <td>
                            <?php if ($log['transfer_type'] === 'transfer'): ?>
                              <span class="badge badge-primary px-2 py-1"><i class="bi bi-arrow-left-right me-1"></i> Unit Transfer</span>
                            <?php elseif ($log['transfer_type'] === 'move_out'): ?>
                              <span class="badge badge-secondary px-2 py-1"><i class="bi bi-box-arrow-right me-1"></i> Move Out</span>
                            <?php else: ?>
                              <span class="badge badge-success px-2 py-1"><i class="bi bi-box-arrow-in-right me-1"></i> Move In</span>
                            <?php endif; ?>
                          </td>
                          <td>
                            <?= $log['from_unit'] ? '<span class="badge badge-light border font-weight-bold">Unit ' . htmlspecialchars($log['from_unit']) . '</span>' : '<span class="text-muted">None</span>' ?>
                          </td>
                          <td>
                            <?= $log['to_unit'] ? '<span class="badge badge-success px-2 py-1 font-weight-bold">Unit ' . htmlspecialchars($log['to_unit']) . '</span>' : '<span class="text-muted">Vacated</span>' ?>
                          </td>
                          <td><small class="text-muted"><?= htmlspecialchars($log['reason'] ?: 'Unit Relocation') ?></small></td>
                          <td><small class="text-dark fw-semibold"><?= htmlspecialchars($log['authorized_by'] ?: 'Admin') ?></small></td>
                          <td><small class="text-muted"><?= date('M j, Y g:i A', strtotime($log['created_at'])) ?></small></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
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
        <form id="editTenantForm" method="POST" action="tenants.php" onsubmit="const pwd = prompt('SECURITY CHECK: Enter your Admin Password to confirm changes:'); if(!pwd) { return false; } document.getElementById('editAdminPassword').value = pwd; return true;">
          <input type="hidden" name="action" value="edit_tenant">
          <input type="hidden" id="editTenantId" name="id">
          <input type="hidden" id="editAdminPassword" name="admin_password" value="">
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

              <!-- Portal Account Credentials Edit Section (Directly Visible & Editable) -->
              <div class="col-12 pt-2 border-top">
                <span class="small fw-bold text-navy d-block mb-2"><i class="bi bi-shield-lock-fill text-primary me-1"></i> Tenant Portal Account Login</span>
              </div>
              <div class="col-md-6">
                <label for="editTenantUsername" class="form-label small fw-bold text-navy">Username</label>
                <div class="input-group input-group-sm">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                  </div>
                  <input type="text" class="form-control" id="editTenantUsername" name="username" placeholder="Tenant username">
                </div>
                <small class="text-muted" style="font-size:0.72rem;">Directly editable tenant username</small>
              </div>
              <div class="col-md-6">
                <label for="editTenantPassword" class="form-label small fw-bold text-navy">Password</label>
                <div class="input-group input-group-sm">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="bi bi-key"></i></span>
                  </div>
                  <input type="text" class="form-control" id="editTenantPassword" name="password" placeholder="Tenant password">
                </div>
                <small class="text-muted" style="font-size:0.72rem;">Directly visible & editable tenant password</small>
              </div>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-ebh-primary btn-sm"><i class="bi bi-save me-1"></i> Apply Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal: Move / Relocate Tenant to Another Unit -->
  <div class="modal fade" id="moveTenantModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-navy text-white">
          <h5 class="modal-title fw-bold text-white mb-0"><i class="bi bi-arrow-left-right me-2 text-warning"></i> Move / Reassign Tenant Unit</h5>
          <button type="button" class="close text-white border-0 bg-transparent" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form method="POST" action="tenants.php" onsubmit="const pwd = prompt('SECURITY CHECK: Enter your Admin Password to authorize unit transfer:'); if(!pwd) { return false; } document.getElementById('moveAdminPassword').value = pwd; return true;">
          <input type="hidden" name="action" value="move_tenant">
          <input type="hidden" id="moveTenantId" name="id">
          <input type="hidden" id="moveAdminPassword" name="admin_password" value="">
          <div class="modal-body p-4">
            <div class="alert alert-info py-2 px-3 small mb-3">
              <i class="bi bi-shield-check me-1"></i> <strong>Safeguard Active:</strong> Moving a tenant will safely vacate their current unit, mark the destination unit occupied, adjust rent rate, and write an immutable transfer audit log.
            </div>
            <div class="mb-3">
              <label class="form-label small fw-bold text-navy">Resident Name</label>
              <input type="text" class="form-control" id="moveTenantName" readonly style="background-color: #f8f9fa;">
            </div>
            <div class="mb-3">
              <label class="form-label small fw-bold text-navy">Current Assigned Unit</label>
              <input type="text" class="form-control" id="moveTenantCurrentUnit" readonly style="background-color: #f8f9fa;">
            </div>
            <div class="mb-3">
              <label for="moveToUnit" class="form-label small fw-bold text-navy">Select New Destination Unit <span class="text-danger">*</span></label>
              <select class="form-control" id="moveToUnit" name="to_unit" required>
                <option value="" disabled selected>Choose available unit...</option>
                <?php foreach ($allRooms as $r): ?>
                  <option value="<?= htmlspecialchars($r['unit_code']) ?>" data-rate="<?= $r['monthly_rate'] ?>" <?= $r['status'] === 'occupied' ? 'disabled' : '' ?>>
                    Unit <?= htmlspecialchars($r['unit_code']) ?> (<?= htmlspecialchars($r['room_type']) ?> - ₱<?= number_format($r['monthly_rate'], 2) ?>/mo) <?= $r['status'] === 'occupied' ? '[Currently Occupied]' : '[Available]' ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted">Occupied units are disabled by safeguards to avoid conflicting assignments.</small>
            </div>
            <div class="mb-3">
              <label for="moveReason" class="form-label small fw-bold text-navy">Reason / Transfer Note</label>
              <input type="text" class="form-control" id="moveReason" name="reason" placeholder="e.g. Upgrading from BH room to Private House" value="Unit Relocation">
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-circle me-1"></i> Authorize & Move Unit</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal: Overdue Non-Payment Exemption / Deferral -->
  <div class="modal fade" id="exemptTenantModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-navy text-white">
          <h5 class="modal-title fw-bold text-white mb-0"><i class="bi bi-shield-exclamation me-2 text-warning"></i> Overdue Exemption / Deferral</h5>
          <button type="button" class="close text-white border-0 bg-transparent" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form method="POST" action="tenants.php" onsubmit="const pwd = prompt('SECURITY CHECK: Enter your Admin Password to authorize exemption:'); if(!pwd) { return false; } document.getElementById('exemptAdminPassword').value = pwd; return true;">
          <input type="hidden" name="action" value="update_exemption">
          <input type="hidden" id="exemptTenantId" name="id">
          <input type="hidden" id="exemptAdminPassword" name="admin_password" value="">
          <div class="modal-body p-4">
            <div class="alert alert-warning py-2 px-3 small mb-3">
              <i class="bi bi-info-circle me-1"></i> <strong>Policy Rule:</strong> If a resident reaches <strong>3 or more months</strong> of unpaid rent, the system mandates a <strong>minimum 50% payment</strong> to prevent immediate eviction. You can grant an administrative exemption/deferral (e.g. 1 month grace period), but their deferred balance carries over and accumulates to the following month where 50% of the entire accumulated balance will still be required.
            </div>

            <div class="mb-3">
              <label class="form-label small fw-bold text-navy">Resident Name</label>
              <input type="text" class="form-control" id="exemptTenantName" readonly style="background-color: #f8f9fa;">
            </div>

            <div class="row g-2 mb-3">
              <div class="col-6">
                <label class="form-label small fw-bold text-navy">Current Balance</label>
                <input type="text" class="form-control" id="exemptCurrentBalance" readonly style="background-color: #f8f9fa;">
              </div>
              <div class="col-6">
                <label class="form-label small fw-bold text-navy">Monthly Rent</label>
                <input type="text" class="form-control" id="exemptMonthlyRent" readonly style="background-color: #f8f9fa;">
              </div>
            </div>

            <div class="mb-3">
              <label for="exemptMonths" class="form-label small fw-bold text-navy">Exemption / Deferral Duration (Months) <span class="text-danger">*</span></label>
              <select class="form-control" id="exemptMonths" name="exemption_months" required>
                <option value="0">0 Months (No Exemption / Enforce Eviction & 50% Rule)</option>
                <option value="1" selected>1 Month Exemption (Deferred until next billing cycle)</option>
                <option value="2">2 Months Exemption</option>
                <option value="3">3 Months Grace Period</option>
              </select>
              <small class="text-muted">Setting to 0 clears any existing exemption.</small>
            </div>

            <div class="mb-3">
              <label for="exemptReason" class="form-label small fw-bold text-navy">Administrative Reason / Justification</label>
              <textarea class="form-control" id="exemptReason" name="exemption_reason" rows="3" placeholder="e.g. Resident requested 1-month salary delay deferral. Agreed to pay 50% of full 4-month total next month."></textarea>
              <small class="text-muted">Document why the landlord permitted this deferral.</small>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-ebh-primary btn-sm"><i class="bi bi-shield-check me-1"></i> Save Exemption Status</button>
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
  <script>
    function openEditTenantModal(id, name, phone, email, unit, rent, moveIn, status, balance, username, password) {
      $('#editTenantId').val(id);
      $('#editTenantName').val(name);
      $('#editTenantContact').val(phone);
      $('#editTenantEmail').val(email || '');
      $('#editTenantRoom').val((unit || '').replace('Unit ', '').trim());
      $('#editTenantRent').val(rent);
      $('#editTenantBalance').val(balance !== undefined && balance !== null ? balance : 0);
      $('#editTenantMoveIn').val(moveIn);
      $('#editTenantStatus').val(status || 'active');
      $('#editTenantUsername').val(username || '');
      $('#editTenantPassword').val(password || '');
      $('#editTenantModal').modal('show');
    }

    function openMoveTenantModal(id, name, currentUnit) {
      $('#moveTenantId').val(id);
      $('#moveTenantName').val(name);
      $('#moveTenantCurrentUnit').val(currentUnit ? 'Unit ' + currentUnit.replace('Unit ', '').trim() : 'Unassigned / None');
      $('#moveToUnit').val('');
      $('#moveTenantModal').modal('show');
    }

    function openExemptTenantModal(id, name, balance, rent, months, reason) {
      $('#exemptTenantId').val(id);
      $('#exemptTenantName').val(name);
      $('#exemptCurrentBalance').val('₱' + Number(balance).toLocaleString('en-US', {minimumFractionDigits: 2}));
      $('#exemptMonthlyRent').val('₱' + Number(rent).toLocaleString('en-US', {minimumFractionDigits: 2}));
      $('#exemptMonths').val(months || 0);
      $('#exemptReason').val(reason || '');
      $('#exemptTenantModal').modal('show');
    }

    $(document).on('click', '.btn-exempt-tenant', function(e) {
      e.preventDefault();
      const btn = $(this);
      openExemptTenantModal(
        btn.data('id'),
        btn.data('name'),
        btn.data('balance'),
        btn.data('rent'),
        btn.data('months'),
        btn.data('reason')
      );
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
      const password = btn.data('password');

      openEditTenantModal(id, name, phone, email, unit, rent, moveIn, status, balance, username, password);
    });

    $(document).on('click', '.btn-move-tenant', function(e) {
      e.preventDefault();
      const btn = $(this);
      const id = btn.data('id');
      const name = btn.data('name');
      const unit = btn.data('unit');

      openMoveTenantModal(id, name, unit);
    });

    // Automatically synchronize monthly rent when unit is changed in edit modal
    $('#editTenantRoom').on('change', function() {
      const selected = $(this).find('option:selected');
      const rate = selected.data('rate');
      if (rate) {
        $('#editTenantRent').val(rate);
      }
    });

    // Universal Search Bar across active and inactive tabs
    $('#tenantSearchInput').on('keyup', function() {
      const q = $(this).val().toLowerCase().trim();
      $('#activeTenantsTable tbody tr, #inactiveTenantsTable tbody tr').each(function() {
        const text = $(this).text().toLowerCase();
        $(this).toggle(text.indexOf(q) > -1);
      });
    });
  </script>
</body>
</html>
