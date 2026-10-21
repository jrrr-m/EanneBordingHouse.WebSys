<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../backend/db.php';
$pdo = getDbConnection();

$msg = $_GET['msg'] ?? '';

// Handle Direct POST Actions on Rooms
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Add Room
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
            header("Location: rooms.php?msg=" . urlencode("Unit {$unitCode} added to inventory."));
            exit;
        }
    }

    // 2. Edit Room
    if ($action === 'edit_room') {
        $id = (int)($_POST['id'] ?? 0);
        $rawNum = trim($_POST['unit_code'] ?? '');
        $unitCode = str_replace('Unit ', '', $rawNum);
        $type = $_POST['room_type'] ?? 'Boarding House Rental';
        $rate = (float)($_POST['monthly_rate'] ?? 3500);
        $pax = (int)($_POST['max_pax'] ?? 3);
        $status = $_POST['status'] ?? 'available';
        $inclusions = trim($_POST['inclusions'] ?? '');

        if ($id > 0 && !empty($unitCode)) {
            $stmt = $pdo->prepare("
                UPDATE rooms 
                SET unit_code = ?, room_type = ?, monthly_rate = ?, max_pax = ?, status = ?, inclusions = ?
                WHERE id = ?
            ");
            $stmt->execute([$unitCode, $type, $rate, $pax, $status, $inclusions, $id]);
            header("Location: rooms.php?msg=" . urlencode("Unit {$unitCode} updated successfully."));
            exit;
        }
    }

    // 3. Quick Status Toggle
    if ($action === 'update_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'available';
        if ($id > 0) {
            $pdo->prepare("UPDATE rooms SET status = ? WHERE id = ?")->execute([$status, $id]);
            header("Location: rooms.php?msg=" . urlencode("Unit status updated to {$status}."));
            exit;
        }
    }

    // 4. Delete Room
    if ($action === 'delete_room') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM rooms WHERE id = ?")->execute([$id]);
            header("Location: rooms.php?msg=" . urlencode("Room unit removed from inventory."));
            exit;
        }
    }

    // 5. Assign Tenant
    if ($action === 'add_tenant') {
        $name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $rawUnit = trim($_POST['unit_code'] ?? '');
        $unitCode = str_replace('Unit ', '', $rawUnit);
        $rent = (float)($_POST['monthly_rent'] ?? 3500);
        $moveIn = $_POST['move_in_date'] ?? date('Y-m-d');
        $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode(' ', $name)[0])) . rand(10, 99);

        if (!empty($name) && !empty($unitCode)) {
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, full_name, role, unit_code, phone, monthly_rent, move_in_date, lease_status)
                VALUES (?, ?, ?, 'tenant', ?, ?, ?, ?, 'active')
            ");
            $defaultHash = password_hash('tenant123', PASSWORD_BCRYPT);
            $stmt->execute([$username, $defaultHash, $name, $unitCode, $phone, $rent, $moveIn]);

            $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE unit_code = ?")->execute([$unitCode]);
            header("Location: rooms.php?msg=" . urlencode("Resident {$name} assigned to Unit {$unitCode}."));
            exit;
        }
    }
}

// Fetch Rooms & Inventory Statistics
$roomsStmt = $pdo->query("
    SELECT r.*, u.full_name AS current_tenant, u.phone AS tenant_phone, u.move_in_date
    FROM rooms r
    LEFT JOIN users u ON r.unit_code = u.unit_code AND u.lease_status = 'active'
    ORDER BY r.unit_code ASC
");
$rooms = $roomsStmt->fetchAll();

$totalUnits = count($rooms);
$occupiedUnits = 0;
$availableUnits = 0;
$bhUnits = 0;
$pbUnits = 0;
$totalTarget = 0;

foreach ($rooms as $r) {
    if ($r['status'] === 'occupied') $occupiedUnits++;
    if ($r['status'] === 'available') $availableUnits++;
    if (strpos($r['room_type'], 'Private') !== false) {
        $pbUnits++;
    } else {
        $bhUnits++;
    }
    $totalTarget += (float)$r['monthly_rate'];
}

$occupancyRate = $totalUnits > 0 ? round(($occupiedUnits / $totalUnits) * 100) : 0;
$pendingQueueCount = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
$openTicketsCount = (int)$pdo->query("SELECT COUNT(*) FROM maintenance_tickets WHERE status = 'open'")->fetchColumn();
$adminUser = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Rooms & Houses Inventory | Eanne Admin Console</title>
  <meta name="description" content="Visual boarding house inventory management for Eanne Boarding House Pasig: 6 units total.">
  
  <!-- Bootstrap 4.1.3 CSS & Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.1.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <!-- Custom Design System -->
  <link rel="stylesheet" href="../assets/css/style.css">
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
        <a href="rooms.php" class="sidebar-link active"><i class="bi bi-door-open-fill"></i><span>Rooms & Houses</span></a>
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
            <h5 class="mb-0 fw-bold text-navy">Boarding House & Private Units Inventory</h5>
            <small class="text-muted"><?= $totalUnits ?> Units Total: <?= $bhUnits ?> Boarding House Rentals (₱3,500) & <?= $pbUnits ?> Private Boarding Houses (₱8,000)</small>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <button type="button" class="btn btn-sm btn-ebh-primary" data-toggle="modal" data-target="#addRoomModal">
            <i class="bi bi-plus-circle me-1"></i> Add Unit
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

        <!-- Quick Inventory Stat Cards -->
        <div class="row g-3 mb-4">
          <div class="col-6 col-md-3">
            <div class="kpi-card">
              <div class="kpi-icon navy"><i class="bi bi-building"></i></div>
              <div>
                <div class="kpi-title">Total Units</div>
                <div class="kpi-value text-navy"><?= $totalUnits ?></div>
                <small class="text-muted">Units in Compound</small>
              </div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="kpi-card">
              <div class="kpi-icon blue"><i class="bi bi-person-check-fill"></i></div>
              <div>
                <div class="kpi-title">Occupied Units</div>
                <div class="kpi-value text-navy"><?= $occupiedUnits ?></div>
                <small class="text-success fw-semibold"><?= $occupancyRate ?>% Occupancy (<?= $occupiedUnits ?>/<?= $totalUnits ?>)</small>
              </div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="kpi-card">
              <div class="kpi-icon accent"><i class="bi bi-door-open-fill"></i></div>
              <div>
                <div class="kpi-title">Available Units</div>
                <div class="kpi-value text-navy"><?= $availableUnits ?></div>
                <small class="text-info fw-semibold"><?= $availableUnits ?> Vacant Now</small>
              </div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="kpi-card">
              <div class="kpi-icon warning"><i class="bi bi-cash-stack"></i></div>
              <div>
                <div class="kpi-title">Potential Target</div>
                <div class="kpi-value text-navy">₱<?= number_format($totalTarget, 2) ?></div>
                <small class="text-muted">Full 100% capacity</small>
              </div>
            </div>
          </div>
        </div>

        <!-- Filter Pills Bar -->
        <div class="ebh-card p-3 mb-4">
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="btn-filter-group">
              <button type="button" class="btn-filter active" data-filter="all" data-target-items="#roomsGridContainer > .filterable-item">All Units (<?= $totalUnits ?>)</button>
              <button type="button" class="btn-filter" data-filter="boarding-house" data-target-items="#roomsGridContainer > .filterable-item">Boarding House (<?= $bhUnits ?>)</button>
              <button type="button" class="btn-filter" data-filter="private-house" data-target-items="#roomsGridContainer > .filterable-item">Private House (<?= $pbUnits ?>)</button>
              <button type="button" class="btn-filter" data-filter="available" data-target-items="#roomsGridContainer > .filterable-item"><i class="bi bi-sparkles text-success"></i> Available Only (<?= $availableUnits ?>)</button>
            </div>
            <div class="small text-muted">
              <span class="room-status-dot occupied"></span> Occupied &nbsp;
              <span class="room-status-dot available"></span> Available &nbsp;
              <span class="badge badge-warning text-dark"><i class="bi bi-lightning-charge"></i> Bills Excluded</span>
            </div>
          </div>
        </div>

        <!-- Rooms Grid Directly Rendered from MySQL -->
        <div class="row g-4" id="roomsGridContainer">
          <?php foreach ($rooms as $r): ?>
            <?php
              $isOccupied = $r['status'] === 'occupied';
              $isAvailable = $r['status'] === 'available';
              $isPrivate = strpos($r['room_type'], 'Private') !== false;
              $catClass = $isPrivate ? 'private-house' : 'boarding-house';
            ?>
            <div class="col-md-6 col-lg-4 filterable-item" data-category="<?= $catClass ?> <?= $r['status'] ?>" data-status="<?= $r['status'] ?>">
              <div class="room-card h-100 <?= $isAvailable ? 'border-2' : '' ?>" style="<?= $isAvailable ? 'border-style:dashed!important;' : '' ?>">
                <div class="room-card-header <?= $isAvailable ? 'bg-light' : '' ?>">
                  <div class="d-flex align-items-center">
                    <span class="room-status-dot <?= $r['status'] ?>"></span>
                    <strong class="text-navy">Unit <?= htmlspecialchars($r['unit_code']) ?></strong>
                    <span class="badge badge-light border ms-2 small"><?= $isPrivate ? 'Private House' : 'Boarding House' ?></span>
                  </div>
                  <?php if ($isOccupied): ?>
                    <span class="badge-ebh badge-paid"><i class="bi bi-lock-fill"></i> Occupied</span>
                  <?php else: ?>
                    <span class="badge-ebh badge-verified"><i class="bi bi-door-open-fill"></i> Available</span>
                  <?php endif; ?>
                </div>
                <div class="ebh-card-body d-flex flex-column flex-grow-1">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small"><?= htmlspecialchars($r['room_type']) ?></span>
                    <span class="fw-bold text-navy fs-5">₱<?= number_format($r['monthly_rate']) ?><small class="text-muted fw-normal">/mo</small></span>
                  </div>
                  <p class="small text-muted mb-2"><?= htmlspecialchars($r['inclusions']) ?></p>
                  
                  <?php if ($isOccupied && !empty($r['current_tenant'])): ?>
                    <?php
                      $tenantInitials = strtoupper(substr($r['current_tenant'], 0, 1) . substr(strrchr($r['current_tenant'], ' ') ?: '', 1, 1));
                      if (!$tenantInitials) $tenantInitials = 'TN';
                    ?>
                    <div class="mb-3">
                      <div class="small text-muted mb-1">Current Resident:</div>
                      <div class="d-flex align-items-center gap-2">
                        <div class="avatar-circle sm"><?= htmlspecialchars($tenantInitials) ?></div>
                        <div>
                          <span class="fw-bold text-navy small d-block"><?= htmlspecialchars($r['current_tenant']) ?></span>
                          <small class="text-muted" style="font-size:0.75rem;"><?= htmlspecialchars($r['tenant_phone'] ?? 'Active Lease') ?></small>
                        </div>
                      </div>
                    </div>
                  <?php else: ?>
                    <div class="p-3 bg-light rounded text-center mb-3">
                      <i class="bi bi-key fs-3 text-success"></i>
                      <div class="small fw-bold text-success mt-1">Available for Lease</div>
                      <div class="text-muted" style="font-size:0.75rem;">Move-in ready unit</div>
                    </div>
                  <?php endif; ?>

                  <div class="mt-auto pt-3 border-top">
                    <div class="d-flex flex-column gap-2">
                      <?php if ($isOccupied): ?>
                        <form method="POST" action="rooms.php" onsubmit="return confirm('Mark Unit <?= htmlspecialchars($r['unit_code']) ?> as Vacant in MySQL?');">
                          <input type="hidden" name="action" value="update_status">
                          <input type="hidden" name="id" value="<?= $r['id'] ?>">
                          <input type="hidden" name="status" value="available">
                          <button type="submit" class="btn btn-sm btn-outline-secondary w-100"><i class="bi bi-arrow-repeat me-1"></i> Mark as Vacant</button>
                        </form>
                      <?php else: ?>
                        <button type="button" class="btn btn-sm btn-ebh-primary w-100" data-toggle="modal" data-target="#addTenantModal" onclick="document.getElementById('assignModalRoom').value='<?= htmlspecialchars($r['unit_code']) ?>'; document.getElementById('assignModalRent').value='<?= $r['monthly_rate'] ?>';">
                          <i class="bi bi-person-plus me-1"></i> Assign Tenant
                        </button>
                      <?php endif; ?>

                      <div class="btn-group btn-group-sm w-100">
                        <button type="button" class="btn btn-outline-primary" onclick="openEditRoom(<?= $r['id'] ?>, '<?= htmlspecialchars($r['unit_code']) ?>', '<?= htmlspecialchars($r['room_type']) ?>', <?= $r['monthly_rate'] ?>, <?= $r['max_pax'] ?>, '<?= htmlspecialchars($r['status']) ?>', '<?= htmlspecialchars(addslashes($r['inclusions'])) ?>')">
                          <i class="bi bi-pencil-square me-1"></i> Edit Unit
                        </button>
                        <form method="POST" action="rooms.php" class="d-inline" onsubmit="return confirm('Permanently delete Unit <?= htmlspecialchars($r['unit_code']) ?>?');">
                          <input type="hidden" name="action" value="delete_room">
                          <input type="hidden" name="id" value="<?= $r['id'] ?>">
                          <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash3"></i></button>
                        </form>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </main>
    </div>
  </div>

  <!-- Modal: Add Rental Unit -->
  <div class="modal fade" id="addRoomModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-navy text-white">
          <h5 class="modal-title fw-bold text-white mb-0"><i class="bi bi-door-plus-fill me-2"></i> Add Rental Unit</h5>
          <button type="button" class="close text-white border-0 bg-transparent" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form method="POST" action="rooms.php">
          <input type="hidden" name="action" value="add_room">
          <div class="modal-body p-4">
            <div class="row g-3">
              <div class="col-md-6">
                <label for="newRoomNumber" class="form-label small fw-bold text-navy">Unit Identifier</label>
                <input type="text" class="form-control" id="newRoomNumber" name="unit_code" required placeholder="e.g. BH-05">
              </div>
              <div class="col-md-6">
                <label for="newRoomType" class="form-label small fw-bold text-navy">Rental Category</label>
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
                <label for="newRoomCapacity" class="form-label small fw-bold text-navy">Max Pax Limit</label>
                <input type="number" class="form-control" id="newRoomCapacity" name="max_pax" value="3" min="1" max="6">
              </div>
              <div class="col-12">
                <div class="alert alert-info py-2 px-3 small mb-0">
                  <i class="bi bi-info-circle me-1"></i> Note: Electricity and water bills are excluded and shouldered by tenants.
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

  <!-- Modal: Edit Rental Unit -->
  <div class="modal fade" id="editRoomModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-navy text-white">
          <h5 class="modal-title fw-bold text-white mb-0"><i class="bi bi-pencil-square me-2"></i> Edit Unit Specifications</h5>
          <button type="button" class="close text-white border-0 bg-transparent" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form method="POST" action="rooms.php">
          <input type="hidden" name="action" value="edit_room">
          <input type="hidden" id="editRoomId" name="id">
          <div class="modal-body p-4">
            <div class="row g-3">
              <div class="col-md-6">
                <label for="editRoomNumber" class="form-label small fw-bold text-navy">Unit Identifier</label>
                <input type="text" class="form-control" id="editRoomNumber" name="unit_code" required>
              </div>
              <div class="col-md-6">
                <label for="editRoomType" class="form-label small fw-bold text-navy">Rental Category</label>
                <select class="form-control" id="editRoomType" name="room_type" required>
                  <option value="Boarding House Rental">Boarding House Rental</option>
                  <option value="Private Boarding House">Private Boarding House</option>
                </select>
              </div>
              <div class="col-md-6">
                <label for="editRoomRate" class="form-label small fw-bold text-navy">Monthly Rate (₱)</label>
                <input type="number" class="form-control" id="editRoomRate" name="monthly_rate" required>
              </div>
              <div class="col-md-6">
                <label for="editRoomCapacity" class="form-label small fw-bold text-navy">Max Pax Limit</label>
                <input type="number" class="form-control" id="editRoomCapacity" name="max_pax" required min="1" max="10">
              </div>
              <div class="col-12">
                <label for="editRoomStatus" class="form-label small fw-bold text-navy">Unit Occupancy Status</label>
                <select class="form-control" id="editRoomStatus" name="status" required>
                  <option value="available">Available (Vacant)</option>
                  <option value="occupied">Occupied</option>
                  <option value="maintenance">Under Maintenance</option>
                </select>
              </div>
              <div class="col-12">
                <label for="editRoomInclusions" class="form-label small fw-bold text-navy">Inclusions & Description</label>
                <textarea class="form-control" id="editRoomInclusions" name="inclusions" rows="2"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-ebh-primary btn-sm"><i class="bi bi-save me-1"></i> Update Unit</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal: Assign Tenant to Vacant Unit -->
  <div class="modal fade" id="addTenantModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-navy text-white">
          <h5 class="modal-title fw-bold text-white mb-0"><i class="bi bi-person-plus-fill me-2"></i> Assign Tenant to Vacant Unit</h5>
          <button type="button" class="close text-white border-0 bg-transparent" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form method="POST" action="rooms.php">
          <input type="hidden" name="action" value="add_tenant">
          <div class="modal-body p-4">
            <div class="row g-3">
              <div class="col-12">
                <label for="newTenantName" class="form-label small fw-bold text-navy">Full Legal Name</label>
                <input type="text" class="form-control" id="newTenantName" name="full_name" required placeholder="e.g. Patricia Reyes">
              </div>
              <div class="col-md-6">
                <label for="newTenantContact" class="form-label small fw-bold text-navy">Mobile Number</label>
                <input type="tel" class="form-control" id="newTenantContact" name="phone" required placeholder="0917-123-4567">
              </div>
              <div class="col-md-6">
                <label for="newTenantMoveIn" class="form-label small fw-bold text-navy">Move-in Date</label>
                <input type="date" class="form-control" id="newTenantMoveIn" name="move_in_date" required value="<?= date('Y-m-d') ?>">
              </div>
              <div class="col-md-6">
                <label for="assignModalRoom" class="form-label small fw-bold text-navy">Assigned Unit</label>
                <select class="form-control" id="assignModalRoom" name="unit_code" required>
                  <?php foreach ($rooms as $r): ?>
                    <option value="<?= htmlspecialchars($r['unit_code']) ?>" <?= $r['status'] === 'occupied' ? 'disabled' : '' ?>>
                      Unit <?= htmlspecialchars($r['unit_code']) ?> (<?= htmlspecialchars($r['room_type']) ?> - ₱<?= number_format($r['monthly_rate']) ?>) <?= $r['status'] === 'occupied' ? '[Occupied]' : '[Available]' ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label for="assignModalRent" class="form-label small fw-bold text-navy">Monthly Rent (₱)</label>
                <input type="number" class="form-control" id="assignModalRent" name="monthly_rent" required value="3500">
              </div>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-ebh-primary btn-sm"><i class="bi bi-check-circle me-1"></i> Confirm & Assign</button>
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
    function openEditRoom(id, code, type, rate, pax, status, inclusions) {
      $('#editRoomId').val(id);
      $('#editRoomNumber').val(code);
      $('#editRoomType').val(type);
      $('#editRoomRate').val(rate);
      $('#editRoomCapacity').val(pax);
      $('#editRoomStatus').val(status);
      $('#editRoomInclusions').val(inclusions);
      $('#editRoomModal').modal('show');
    }
  </script>
</body>
</html>
