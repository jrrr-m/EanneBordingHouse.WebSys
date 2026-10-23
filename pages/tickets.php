<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../backend/db.php';
$pdo = getDbConnection();

$msg = $_GET['msg'] ?? '';

// Handle Direct POST Actions on Tickets
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Create Maintenance Ticket
    if ($action === 'create_ticket') {
        $tenantName = trim($_POST['tenant_name'] ?? '');
        $unitCode = trim($_POST['unit_code'] ?? 'BH-01');
        $category = trim($_POST['category'] ?? 'General');
        $urgency = trim($_POST['urgency'] ?? 'Normal');
        $description = trim($_POST['description'] ?? '');
        $ticketNo = 'TKT-' . rand(1000, 9999);

        $initialStatus = strtolower(trim($_POST['status'] ?? 'open'));
        if (!in_array($initialStatus, ['open', 'in_progress', 'resolved'])) {
            $initialStatus = 'open';
        }
        $resolvedAt = ($initialStatus === 'resolved') ? date('Y-m-d H:i:s') : null;

        if (!empty($tenantName) && !empty($description)) {
            $stmt = $pdo->prepare("
                INSERT INTO maintenance_tickets (ticket_no, tenant_name, unit_code, category, urgency, description, status, created_at, resolved_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $stmt->execute([$ticketNo, $tenantName, $unitCode, $category, $urgency, $description, $initialStatus, $resolvedAt]);
            header("Location: tickets.php?msg=" . urlencode("Service Ticket #{$ticketNo} logged into database with status " . ucfirst(str_replace('_', ' ', $initialStatus)) . "."));
            exit;
        }
    }

    // 2. Update Ticket Status (Open, In Progress, Resolved)
    if ($action === 'update_status') {
        $id = (int)($_POST['id'] ?? 0);
        $newStatus = strtolower(trim($_POST['status'] ?? 'open'));
        if (!in_array($newStatus, ['open', 'in_progress', 'resolved'])) {
            $newStatus = 'open';
        }
        $resolvedAt = ($newStatus === 'resolved') ? date('Y-m-d H:i:s') : null;

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE maintenance_tickets SET status = ?, resolved_at = ? WHERE id = ?");
            $stmt->execute([$newStatus, $resolvedAt, $id]);
            $statusLabel = ($newStatus === 'in_progress') ? 'In Progress' : ucfirst($newStatus);
            header("Location: tickets.php?msg=" . urlencode("Ticket status updated to " . $statusLabel . "."));
            exit;
        }
    }

    // 3. Delete Ticket
    if ($action === 'delete_ticket') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM maintenance_tickets WHERE id = ?")->execute([$id]);
            header("Location: tickets.php?msg=" . urlencode("Ticket record deleted."));
            exit;
        }
    }
}

// Fetch All Maintenance Tickets
$ticketsStmt = $pdo->query("SELECT * FROM maintenance_tickets ORDER BY id DESC");
$tickets = $ticketsStmt->fetchAll();

$totalTickets = count($tickets);
$openCount = 0;
$inProgressCount = 0;
$resolvedCount = 0;

foreach ($tickets as $t) {
    if ($t['status'] === 'open') $openCount++;
    if ($t['status'] === 'in_progress') $inProgressCount++;
    if ($t['status'] === 'resolved') $resolvedCount++;
}

// Active tenants for modal select
$activeTenants = $pdo->query("SELECT full_name, unit_code FROM users WHERE role = 'tenant' AND lease_status = 'active' ORDER BY full_name ASC")->fetchAll();
$pendingQueueCount = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
$adminUser = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Maintenance Tickets | Eanne Admin Console</title>
  <meta name="description" content="Manage tenant maintenance requests, repairs, electrical and plumbing work orders at Eanne Boarding House.">

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
        <a href="tickets.php" class="sidebar-link active"><i class="bi bi-tools"></i><span>Maintenance Tickets</span> <span class="badge <?= $openCount > 0 ? 'badge-danger text-white' : 'badge-secondary' ?> ml-auto"><?= $openCount ?></span></a>
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
            <h5 class="mb-0 fw-bold text-navy">Maintenance & Service Tickets</h5>
            <small class="text-muted"><span class="fw-bold text-primary"><?= $totalTickets ?></span> Work Orders in Database</small>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <button type="button" class="btn btn-sm btn-ebh-primary" data-toggle="modal" data-target="#createTicketModal">
            <i class="bi bi-plus-circle me-1"></i> Log Service Ticket
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

        <!-- 4 KPI Stat Cards Directly from Database -->
        <div class="row g-3 mb-4">
          <div class="col-sm-6 col-xl-3">
            <div class="kpi-card h-100">
              <div class="kpi-icon navy"><i class="bi bi-tools"></i></div>
              <div>
                <div class="kpi-title">Total Work Orders</div>
                <div class="kpi-value text-navy"><?= $totalTickets ?></div>
                <small class="text-muted"><i class="bi bi-database me-1"></i> Compound Total</small>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-xl-3">
            <div class="kpi-card h-100">
              <div class="kpi-icon warning"><i class="bi bi-exclamation-triangle-fill"></i></div>
              <div>
                <div class="kpi-title">Open & Pending</div>
                <div class="kpi-value text-danger"><?= $openCount ?></div>
                <small class="text-danger fw-semibold"><i class="bi bi-clock-history me-1"></i> Requires Attention</small>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-xl-3">
            <div class="kpi-card h-100">
              <div class="kpi-icon blue"><i class="bi bi-gear-wide-connected"></i></div>
              <div>
                <div class="kpi-title">In Progress</div>
                <div class="kpi-value text-primary"><?= $inProgressCount ?></div>
                <small class="text-primary fw-semibold"><i class="bi bi-wrench-adjustable me-1"></i> Caretaker Assigned</small>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-xl-3">
            <div class="kpi-card h-100">
              <div class="kpi-icon accent"><i class="bi bi-check2-circle"></i></div>
              <div>
                <div class="kpi-title">Resolved & Fixed</div>
                <div class="kpi-value text-success"><?= $resolvedCount ?></div>
                <small class="text-success fw-semibold"><i class="bi bi-shield-check me-1"></i> Completed Work</small>
              </div>
            </div>
          </div>
        </div>

        <!-- Filter & Search Control Bar -->
        <div class="ebh-card p-3 mb-4">
          <div class="row g-3 align-items-center justify-content-between">
            <div class="col-md-4 col-lg-3">
              <div class="input-group input-group-sm">
                <div class="input-group-prepend">
                  <span class="input-group-text bg-white text-muted border-right-0"><i class="bi bi-search"></i></span>
                </div>
                <input type="text" id="ticketSearchInput" class="form-control border-left-0" placeholder="Search ticket #, tenant, unit...">
              </div>
            </div>

            <div class="col-md-8 col-lg-9 d-flex flex-wrap align-items-center justify-content-md-end gap-2">
              <div class="btn-filter-group" id="ticketFilterPills">
                <button type="button" class="btn-filter active" data-filter="all" data-target-items="#adminTicketsTable tbody tr">All (<?= $totalTickets ?>)</button>
                <button type="button" class="btn-filter" data-filter="open" data-target-items="#adminTicketsTable tbody tr">Open (<?= $openCount ?>)</button>
                <button type="button" class="btn-filter" data-filter="in_progress" data-target-items="#adminTicketsTable tbody tr">In Progress (<?= $inProgressCount ?>)</button>
                <button type="button" class="btn-filter" data-filter="resolved" data-target-items="#adminTicketsTable tbody tr">Resolved (<?= $resolvedCount ?>)</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Tickets Table Card Rendered from MySQL -->
        <div class="ebh-card overflow-hidden">
          <div class="table-responsive">
            <table class="table table-ebh table-hover mb-0" id="adminTicketsTable">
              <thead>
                <tr>
                  <th style="width: 140px;">Ticket No.</th>
                  <th>Tenant & Unit</th>
                  <th>Category</th>
                  <th>Issue Description</th>
                  <th style="width: 130px;">Priority</th>
                  <th style="width: 140px;">Status</th>
                  <th style="width: 150px;">Date Reported</th>
                  <th style="width: 180px;" class="text-right">Actions</th>
                </tr>
              </thead>
              <tbody id="adminTicketsTbody">
                <?php if (empty($tickets)): ?>
                  <tr>
                    <td colspan="8" class="text-center py-4 text-muted">No maintenance requests logged yet.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($tickets as $t): ?>
                    <?php
                      $isOpen = $t['status'] === 'open';
                      $isInProgress = $t['status'] === 'in_progress';
                      $isResolved = $t['status'] === 'resolved';
                      $isUrgent = strtolower($t['urgency']) === 'urgent';
                      $filterCat = $t['status'] . ' ' . strtolower($t['category']);
                    ?>
                    <tr class="filterable-item <?= $isUrgent && !$isResolved ? 'table-danger' : '' ?>" data-category="<?= $filterCat ?>" data-status="<?= $t['status'] ?>">
                      <td class="fw-bold text-navy font-monospace"><?= htmlspecialchars($t['ticket_no']) ?></td>
                      <td>
                        <strong class="text-navy d-block"><?= htmlspecialchars($t['tenant_name']) ?></strong>
                        <span class="badge bg-light text-navy border">Unit <?= htmlspecialchars($t['unit_code']) ?></span>
                      </td>
                      <td><span class="badge bg-primary text-white"><?= htmlspecialchars($t['category']) ?></span></td>
                      <td>
                        <p class="mb-0 text-dark small" style="max-width: 320px;"><?= htmlspecialchars($t['description']) ?></p>
                      </td>
                      <td>
                        <?php if ($isUrgent): ?>
                          <span class="badge badge-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> Urgent</span>
                        <?php else: ?>
                          <span class="badge badge-light border text-navy"><?= htmlspecialchars($t['urgency']) ?></span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <form method="POST" action="tickets.php" class="d-inline">
                          <input type="hidden" name="action" value="update_status">
                          <input type="hidden" name="id" value="<?= $t['id'] ?>">
                          <select name="status" class="form-select form-select-sm status-select status-select-<?= $t['status'] ?>" onchange="this.form.submit()" style="font-size:0.8rem; font-weight:600; padding:3px 8px; border-radius:6px; cursor:pointer;">
                            <option value="open" <?= $isOpen ? 'selected' : '' ?>>🟡 Open</option>
                            <option value="in_progress" <?= $isInProgress ? 'selected' : '' ?>>🔵 In Progress</option>
                            <option value="resolved" <?= $isResolved ? 'selected' : '' ?>>🟢 Resolved</option>
                          </select>
                        </form>
                      </td>
                      <td><small class="text-muted"><?= date('M j, Y', strtotime($t['created_at'])) ?></small></td>
                      <td class="text-right">
                        <div class="btn-group btn-group-sm">
                          <div class="dropdown d-inline">
                            <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Change Status">
                              <i class="bi bi-pencil-square me-1"></i> Status
                            </button>
                            <div class="dropdown-menu dropdown-menu-right shadow-sm border-0">
                              <h6 class="dropdown-header text-uppercase text-muted" style="font-size:0.7rem;">Select Status</h6>
                              <form method="POST" action="tickets.php">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                <input type="hidden" name="status" value="open">
                                <button type="submit" class="dropdown-item d-flex align-items-center gap-2 <?= $isOpen ? 'active fw-bold' : '' ?>">
                                  <i class="bi bi-clock-history text-warning"></i> Set as Open
                                </button>
                              </form>
                              <form method="POST" action="tickets.php">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                <input type="hidden" name="status" value="in_progress">
                                <button type="submit" class="dropdown-item d-flex align-items-center gap-2 <?= $isInProgress ? 'active fw-bold' : '' ?>">
                                  <i class="bi bi-wrench-adjustable text-primary"></i> Set as In Progress
                                </button>
                              </form>
                              <form method="POST" action="tickets.php">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                <input type="hidden" name="status" value="resolved">
                                <button type="submit" class="dropdown-item d-flex align-items-center gap-2 <?= $isResolved ? 'active fw-bold' : '' ?>">
                                  <i class="bi bi-check-circle-fill text-success"></i> Set as Resolved
                                </button>
                              </form>
                            </div>
                          </div>
                          <form method="POST" action="tickets.php" class="d-inline" onsubmit="return confirm('Delete Ticket #<?= htmlspecialchars($t['ticket_no']) ?>?');">
                            <input type="hidden" name="action" value="delete_ticket">
                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger" title="Delete Ticket"><i class="bi bi-trash3"></i></button>
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

  <!-- Create Service Ticket Modal -->
  <div class="modal fade" id="createTicketModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-navy text-white">
          <h6 class="modal-title font-weight-bold"><i class="bi bi-tools me-2 text-warning"></i> Log Caretaker / Maintenance Ticket</h6>
          <button type="button" class="close text-white border-0 bg-transparent" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form method="POST" action="tickets.php">
          <input type="hidden" name="action" value="create_ticket">
          <div class="modal-body p-4">
            <div class="row g-3">
              <div class="col-md-7">
                <label for="adminTicketTenant" class="form-label small fw-bold text-navy">Tenant / Reporter</label>
                <select class="form-control form-control-sm" id="adminTicketTenant" name="tenant_name" required onchange="const unit = this.options[this.selectedIndex].getAttribute('data-unit'); if(unit) document.getElementById('adminTicketUnit').value = unit;">
                  <option value="" disabled selected>Select tenant / unit...</option>
                  <?php foreach ($activeTenants as $at): ?>
                    <option value="<?= htmlspecialchars($at['full_name']) ?>" data-unit="<?= htmlspecialchars($at['unit_code']) ?>">
                      <?= htmlspecialchars($at['full_name']) ?> (Unit <?= htmlspecialchars($at['unit_code']) ?>)
                    </option>
                  <?php endforeach; ?>
                  <option value="Caretaker Inspection" data-unit="Compound">Caretaker / Facility Management</option>
                </select>
              </div>
              <div class="col-md-5">
                <label for="adminTicketUnit" class="form-label small fw-bold text-navy">Unit Code</label>
                <input type="text" class="form-control form-control-sm" id="adminTicketUnit" name="unit_code" value="BH-01" required>
              </div>

              <div class="col-md-4">
                <label for="adminTicketCategory" class="form-label small fw-bold text-navy">Category</label>
                <select class="form-control form-control-sm" id="adminTicketCategory" name="category" required>
                  <option value="Plumbing">Plumbing (Pipes, CR, Sinks)</option>
                  <option value="Electrical">Electrical (Lights, Outlets, Breakers)</option>
                  <option value="Structural">Structural & Carpentry (Doors, Locks, Windows)</option>
                  <option value="Appliances">Appliances & Air Conditioning</option>
                  <option value="General">General Compound Repairs</option>
                </select>
              </div>
              <div class="col-md-4">
                <label for="adminTicketUrgency" class="form-label small fw-bold text-navy">Urgency / Priority</label>
                <select class="form-control form-control-sm" id="adminTicketUrgency" name="urgency" required>
                  <option value="Normal">Normal Priority</option>
                  <option value="Urgent">Urgent (Immediate Caretaker Visit)</option>
                  <option value="Low">Low / Scheduled Routine</option>
                </select>
              </div>
              <div class="col-md-4">
                <label for="adminTicketStatus" class="form-label small fw-bold text-navy">Status</label>
                <select class="form-control form-control-sm font-weight-bold" id="adminTicketStatus" name="status" required>
                  <option value="open" selected>Open</option>
                  <option value="in_progress">In Progress</option>
                  <option value="resolved">Resolved</option>
                </select>
              </div>

              <div class="col-12">
                <label for="adminTicketDesc" class="form-label small fw-bold text-navy">Issue Description & Location</label>
                <textarea class="form-control form-control-sm" id="adminTicketDesc" name="description" rows="3" required placeholder="Describe the maintenance problem, exact room location, or materials needed..."></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-ebh-primary btn-sm"><i class="bi bi-save me-1"></i> Submit Work Order</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal: View & Update Maintenance Ticket Details -->
  <div class="modal fade" id="viewTicketModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-navy text-white">
          <h6 class="modal-title font-weight-bold"><i class="bi bi-info-circle me-2 text-info"></i> Maintenance Ticket Details</h6>
          <button type="button" class="close text-white border-0 bg-transparent" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body p-4" id="viewTicketModalBody">
          <!-- Populated by JavaScript previewTicketDetails() -->
        </div>
        <div class="modal-footer bg-light justify-content-between">
          <div id="viewTicketActionBtns"></div>
          <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <style>
    .status-select {
      border: 1px solid #ced4da;
      transition: all 0.2s ease;
    }
    .status-select-open {
      background-color: #fff9e6;
      color: #b7791f;
      border-color: #f6e05e;
    }
    .status-select-in_progress {
      background-color: #ebf8ff;
      color: #2b6cb0;
      border-color: #bee3f8;
    }
    .status-select-resolved {
      background-color: #f0fff4;
      color: #2f855a;
      border-color: #9ae6b4;
    }
  </style>

  <!-- Full jQuery 3.7.1, Popper.js, Bootstrap 4.1.3 JS -->
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.14.3/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.1.3/dist/js/bootstrap.min.js"></script>
  <script src="../assets/js/script.js"></script>
</body>
</html>
