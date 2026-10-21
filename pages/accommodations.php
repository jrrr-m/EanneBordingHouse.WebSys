<?php
require_once __DIR__ . '/../backend/db.php';
$pdo = getDbConnection();

$totalUnits = 6;
$availUnits = 0;
$bhAvail = 0;
$pbAvail = 0;

if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT room_type, status, COUNT(*) as cnt FROM rooms GROUP BY room_type, status");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['status'] === 'available') {
                $availUnits += (int)$row['cnt'];
                if (stripos($row['room_type'], 'private') !== false) {
                    $pbAvail += (int)$row['cnt'];
                } else {
                    $bhAvail += (int)$row['cnt'];
                }
            }
        }
    } catch (PDOException $e) {
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Rooms & Rates | Eanne Boarding House Pasig</title>
  <meta name="description" content="View rooms and rates at Eanne Boarding House: 6 boarding houses for rent. 4 Boarding House rentals at ₱3,500/mo and 2 Private Boarding Houses at ₱8,000/mo in Soldiers Village, Pasig.">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.1.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/style.css">
  <!-- Business Logo Favicon -->
  <link rel="icon" type="image/png" href="../assets/img/favicon.png">
  <link rel="apple-touch-icon" href="../assets/img/favicon.png">
</head>
<body>

  <header class="sticky-top">
    <nav class="navbar navbar-expand-lg navbar-ebh" id="mainNavbar">
      <div class="container-fluid px-3 px-xl-5">
        <a class="ebh-brand" href="../index.php">
          <div class="ebh-logo-mark" title="Eanne Boarding House Rental">
            <img src="../assets/img/795286329_1754004695838265_6753877283478212113_n.png" alt="Eanne Boarding House Rental Logo">
          </div>
          <div class="ebh-brand-text">
            <span class="ebh-brand-title">Eanne Boarding House</span>
            <span class="ebh-brand-subtitle d-none d-sm-block">Boarding House & Private Rentals</span>
          </div>
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarContent">
          <ul class="navbar-nav mx-auto mb-2 mb-lg-0 align-items-lg-center">
            <li class="nav-item"><a class="nav-link" href="../index.php">Home</a></li>
            <li class="nav-item"><a class="nav-link active" href="accommodations.php">Rooms & Rates</a></li>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">The Property</a>
              <ul class="dropdown-menu shadow">
                <li><a class="dropdown-item" href="about.php"><i class="bi bi-building"></i> About Us</a></li>
                <li><a class="dropdown-item" href="gallery.php"><i class="bi bi-images"></i> Photo Gallery</a></li>
                <li><a class="dropdown-item" href="house-rules.php"><i class="bi bi-shield-check"></i> House Rules</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="../index.php#contact"><i class="bi bi-geo-alt-fill"></i> Location</a></li>
              </ul>
            </li>
            <li class="nav-item"><a class="nav-link" href="../index.php#contact">Contact</a></li>
          </ul>
          <div class="d-flex align-items-center gap-2">
            <a href="../index.php#contact" class="btn btn-ebh-accent btn-sm px-3 fw-bold">Book a Viewing</a>
            <a href="login.php" class="btn btn-outline-primary btn-sm px-3"><i class="bi bi-person-circle me-1"></i> Sign In</a>
          </div>
        </div>
      </div>
    </nav>
  </header>

  <main>
    <!-- Hero Section -->
    <section class="hero-ebh" id="heroSection">
      <div class="container text-center">
        <div class="hero-pill-badge">
          <i class="bi bi-door-open-fill text-info"></i> Soldiers Village, Brgy. Sta. Lucia, Pasig City
        </div>
        <h1 class="display-4 fw-bold mb-3 text-white">Our Rooms, Houses, & Rates</h1>
        <p class="lead mb-4 mx-auto" style="max-width: 680px;">
          Currently offering <strong>6 boarding house units</strong> for rent in Soldiers Village, Pasig City. Choose between affordable room rentals or complete private houses.
        </p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
          <a href="../index.php#contact" class="btn btn-ebh-accent btn-lg px-4"><i class="bi bi-calendar-check me-1"></i> Book a Viewing</a>
          <a href="house-rules.php" class="btn btn-ebh-outline-light btn-lg px-4"><i class="bi bi-shield-check me-1"></i> House Rules</a>
        </div>
        <div class="row mt-5 pt-3 border-top border-white border-opacity-15 text-white g-3 justify-content-center">
          <div class="col-6 col-md-3"><div class="h3 fw-bold mb-0 text-info">6 Units</div><small class="opacity-75">Total Compound</small></div>
          <div class="col-6 col-md-3"><div class="h3 fw-bold mb-0 text-info">2 Types</div><small class="opacity-75">Rental Options</small></div>
          <div class="col-6 col-md-3"><div class="h3 fw-bold mb-0 text-info">₱3,500 & ₱8k</div><small class="opacity-75">Monthly Rates</small></div>
          <div class="col-6 col-md-3"><div class="h3 fw-bold mb-0 text-warning" id="publicAvailRoomsCount"><?= (int)$availUnits ?> Left</div><small class="opacity-75" id="publicAvailRoomsSub"><?= $availUnits > 0 ? "Vacant Now" : "Fully Booked" ?></small></div>
        </div>
      </div>
    </section>

    <!-- Rental Types & Inclusions Section -->
    <section class="py-5 bg-light">
      <div class="container">
        
        <div class="text-center mb-5" style="max-width: 700px; margin: 0 auto;">
          <span class="badge-ebh badge-pending mb-2">Rental Options</span>
          <h2 class="fw-bold text-navy">2 Types of Accommodations</h2>
          <p class="text-muted small">We have 4 boarding house rental rooms and 2 standalone private boarding houses available in our compound.</p>
        </div>

        <div class="row g-4 mb-5 justify-content-center">
          <!-- Type 1: Boarding House Rental -->
          <div class="col-lg-6">
            <div class="ebh-card h-100 d-flex flex-column overflow-hidden border-primary shadow-sm">
              <div class="room-preview-box cursor-pointer" onclick="openRoomDetailsModal('Boarding House Rental', '₱3,500', 'Boarding House (4 Units Total)', ['Individual private room', 'Dedicated kitchen area', 'Shared comfort room (CR)', 'Shared common area', 'Maximum 3 pax per room limit', 'Bills excluded: Electricity & water shouldered by tenants', 'Strict quiet hours after 10 PM (No karaoke)', 'No pets allowed and CLAYGO policy', 'No drilling or renovations without permission'], '../assets/img/solo_room.jpg', 'Affordable individual room rental with dedicated kitchen area and shared comfort room. Strictly up to 3 persons per room. Electricity and water bills are shouldered by tenants based on consumption.')">
                <img src="../assets/img/solo_room.jpg" alt="Boarding House Rental">
                <span class="gallery-badge-category">4 Units Total</span>
              </div>
              <div class="p-4 d-flex flex-column flex-grow-1 bg-white justify-content-between">
                <div>
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <h4 class="fw-bold text-navy mb-0">Boarding House Rental</h4>
                    <?php if ($bhAvail > 0): ?>
                    <span id="bhAvailabilityBadge" class="badge bg-success text-white"><i class="bi bi-check-circle me-1"></i> <?= $bhAvail ?> of 4 Available</span>
<?php else: ?>
                    <span id="bhAvailabilityBadge" class="badge bg-danger text-white"><i class="bi bi-slash-circle me-1"></i> Fully Booked (0/4 Avail)</span>
<?php endif; ?>
                  </div>
                  <div class="display-6 fw-bold text-navy mb-2">₱3,500 <span class="fs-6 fw-normal text-muted">/ month per room</span></div>
                  <?php if ($bhAvail > 0): ?>
                  <div id="bhAvailNotice" class="alert alert-success py-2 px-3 small mb-3 border-0 d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill fs-5 text-success"></i> <span><?= $bhAvail ?> individual boarding room<?= $bhAvail > 1 ? "s are" : " is" ?> vacant and ready for immediate move-in!</span>
                  </div>
<?php else: ?>
                  <div id="bhAvailNotice" class="alert alert-warning py-2 px-3 small mb-3 border-0 d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history fs-5 text-warning"></i> <span>All 4 individual boarding rooms are currently occupied. Inquire to join the waitlist.</span>
                  </div>
<?php endif; ?>
                  <p class="text-muted small mb-3">Individual room and kitchen area with shared comfort room. Perfect for budget-conscious tenants, students, and workers.</p>
                  
                  <div class="mb-3">
                    <strong class="text-navy small d-block mb-2"><i class="bi bi-box-seam text-primary me-1"></i> Inclusions:</strong>
                    <ul class="list-unstyled small text-muted d-flex flex-column gap-2 mb-3 ps-2">
                      <li><i class="bi bi-check2-circle text-success me-2"></i> <strong>Individual room</strong> (Private bedroom space)</li>
                      <li><i class="bi bi-check2-circle text-success me-2"></i> <strong>Dedicated kitchen area</strong> for meal preparations</li>
                      <li><i class="bi bi-check2-circle text-success me-2"></i> <strong>Shared comfort room (CR)</strong> & common area</li>
                      <li><i class="bi bi-info-circle text-warning me-2"></i> <strong>Max 3 pax per room</strong> occupancy</li>
                      <li><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i> <strong>Bills are excluded:</strong> Electricity and water are shouldered by tenants</li>
                    </ul>
                  </div>

                  <div class="p-3 bg-light rounded-3 mb-4 border">
                    <strong class="text-navy small d-block mb-1"><i class="bi bi-shield-check text-success me-1"></i> House Rules Highlights:</strong>
                    <ul class="small text-muted mb-0 ps-3">
                      <li>Maximum 3 pax per room</li>
                      <li>No karaokes past 10:00 PM</li>
                      <li>No pets allowed</li>
                      <li>CLAYGO (Clean As You Go)</li>
                      <li>No drilling and renovations without prior permission</li>
                    </ul>
                  </div>
                </div>
                <div class="d-flex gap-2">
                  <button type="button" class="btn btn-outline-primary btn-sm w-100" onclick="openRoomDetailsModal('Boarding House Rental', '₱3,500', 'Boarding House (4 Units Total)', ['Individual private room', 'Dedicated kitchen area', 'Shared comfort room (CR)', 'Shared common area', 'Maximum 3 pax per room limit', 'Bills excluded: Electricity & water shouldered by tenants', 'Strict quiet hours after 10 PM (No karaoke)', 'No pets allowed and CLAYGO policy', 'No drilling or renovations without permission'], '../assets/img/solo_room.jpg', 'Affordable individual room rental with dedicated kitchen area and shared comfort room. Strictly up to 3 persons per room. Electricity and water bills are shouldered by tenants based on consumption.')">Full Specs</button>
                  <a href="../index.php#contact" class="btn btn-ebh-primary btn-sm w-100">Inquire Now</a>
                </div>
              </div>
            </div>
          </div>

          <!-- Type 2: Private Boarding House -->
          <div class="col-lg-6">
            <div class="ebh-card h-100 d-flex flex-column overflow-hidden border-info shadow-sm">
              <div class="room-preview-box cursor-pointer" onclick="openRoomDetailsModal('Private Boarding House', '₱8,000', 'Private Boarding House (2 Units Total)', ['Own complete private space (entire house)', 'No shared common areas with other tenants', 'Private kitchen and private comfort room', 'Ideal for families or tenants seeking full privacy', 'No strict boarding house rules (peaceful family living)', 'Bills excluded: Electricity & water shouldered by tenants', 'Gated compound security in Soldiers Village'], '../assets/img/exterior_compound.jpg', 'Complete private boarding house with own standalone space and no shared common areas. Designed for families and residents who value privacy. Electricity and water bills are shouldered by tenants based on actual consumption.')">
                <img src="../assets/img/exterior_compound.jpg" alt="Private Boarding House">
                <span class="gallery-badge-category">2 Units Total</span>
              </div>
              <div class="p-4 d-flex flex-column flex-grow-1 bg-white justify-content-between">
                <div>
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <h4 class="fw-bold text-navy mb-0">Private Boarding House</h4>
                    <?php if ($pbAvail > 0): ?>
                    <span id="pbAvailabilityBadge" class="badge bg-success text-white"><i class="bi bi-check-circle me-1"></i> <?= $pbAvail ?> of 2 Available</span>
<?php else: ?>
                    <span id="pbAvailabilityBadge" class="badge bg-danger text-white"><i class="bi bi-slash-circle me-1"></i> Fully Booked (0/2 Avail)</span>
<?php endif; ?>
                  </div>
                  <div class="display-6 fw-bold text-navy mb-2">₱8,000 <span class="fs-6 fw-normal text-muted">/ month (entire house)</span></div>
                  <?php if ($pbAvail > 0): ?>
                  <div id="pbAvailNotice" class="alert alert-success py-2 px-3 small mb-3 border-0 d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill fs-5 text-success"></i> <span><?= $pbAvail ?> Standalone Private House<?= $pbAvail > 1 ? "s are" : " is" ?> vacant and ready for immediate family move-in!</span>
                  </div>
<?php else: ?>
                  <div id="pbAvailNotice" class="alert alert-warning py-2 px-3 small mb-3 border-0 d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history fs-5 text-warning"></i> <span>Both private houses are currently occupied. Inquire to join the waitlist.</span>
                  </div>
<?php endif; ?>
                  <p class="text-muted small mb-3">Individual private house with complete personal space and no shared common areas. Pang-pamilya at tahimik na pamumuhay.</p>
                  
                  <div class="mb-3">
                    <strong class="text-navy small d-block mb-2"><i class="bi bi-box-seam text-primary me-1"></i> Inclusions:</strong>
                    <ul class="list-unstyled small text-muted d-flex flex-column gap-2 mb-3 ps-2">
                      <li><i class="bi bi-check2-circle text-success me-2"></i> <strong>Own complete private space</strong> (Full standalone house)</li>
                      <li><i class="bi bi-check2-circle text-success me-2"></i> <strong>No shared common areas</strong> (Private kitchen & private CR)</li>
                      <li><i class="bi bi-check2-circle text-success me-2"></i> <strong>Family-friendly</strong> layout with full privacy</li>
                      <li><i class="bi bi-check2-circle text-success me-2"></i> <strong>Independent living</strong> without room-sharing</li>
                      <li><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i> <strong>Bills are excluded:</strong> Electricity and water are shouldered by tenants</li>
                    </ul>
                  </div>

                  <div class="p-3 bg-light rounded-3 mb-4 border border-info">
                    <strong class="text-navy small d-block mb-1"><i class="bi bi-house-heart-fill text-info me-1"></i> House Rules Policy:</strong>
                    <p class="small text-muted mb-0">
                      <strong>No strict house rules</strong> &bull; Bahay na ito at pang-pamilya. Tenants enjoy full privacy and autonomy within their private premises.
                    </p>
                  </div>
                </div>
                <div class="d-flex gap-2">
                  <button type="button" class="btn btn-outline-primary btn-sm w-100" onclick="openRoomDetailsModal('Private Boarding House', '₱8,000', 'Private Boarding House (2 Units Total)', ['Own complete private space (entire house)', 'No shared common areas with other tenants', 'Private kitchen and private comfort room', 'Ideal for families or tenants seeking full privacy', 'No strict boarding house rules (peaceful family living)', 'Bills excluded: Electricity & water shouldered by tenants', 'Gated compound security in Soldiers Village'], '../assets/img/exterior_compound.jpg', 'Complete private boarding house with own standalone space and no shared common areas. Designed for families and residents who value privacy. Electricity and water bills are shouldered by tenants based on actual consumption.')">Full Specs</button>
                  <a href="../index.php#contact" class="btn btn-ebh-accent btn-sm w-100">Inquire Now</a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Comparison Table -->
        <div class="ebh-card p-4 bg-white mb-5 shadow-sm">
          <h4 class="fw-bold text-navy mb-3"><i class="bi bi-layout-split text-primary me-2"></i> Side-by-Side Comparison</h4>
          <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">
              <thead class="bg-navy text-white">
                <tr>
                  <th style="width: 25%;">Feature / Detail</th>
                  <th style="width: 37.5%;">Boarding House Rental</th>
                  <th style="width: 37.5%;">Private Boarding House</th>
                </tr>
              </thead>
              <tbody class="small">
                <tr>
                  <td class="fw-bold text-navy">Total Units For Rent</td>
                  <td><span class="badge badge-primary">4 Units</span></td>
                  <td><span class="badge badge-success">2 Units</span></td>
                </tr>
                <tr>
                  <td class="fw-bold text-navy">Monthly Rate</td>
                  <td class="fw-bold text-primary fs-6">₱3,500 / month</td>
                  <td class="fw-bold text-success fs-6">₱8,000 / month</td>
                </tr>
                <tr>
                  <td class="fw-bold text-navy">Living Space Type</td>
                  <td>Individual room + kitchen area</td>
                  <td>Own complete standalone private house</td>
                </tr>
                <tr>
                  <td class="fw-bold text-navy">Comfort Room (CR)</td>
                  <td>Shared comfort room</td>
                  <td>Own private comfort room (No sharing)</td>
                </tr>
                <tr>
                  <td class="fw-bold text-navy">Common Areas</td>
                  <td>Shared common area</td>
                  <td>No shared common areas (100% private)</td>
                </tr>
                <tr>
                  <td class="fw-bold text-navy">Occupancy Capacity</td>
                  <td>Max 3 pax per room</td>
                  <td>Ideal for families / private households</td>
                </tr>
                <tr>
                  <td class="fw-bold text-navy">Utility Bills (Electricity & Water)</td>
                  <td class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i> Excluded (Shouldered by tenant)</td>
                  <td class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i> Excluded (Shouldered by tenant)</td>
                </tr>
                <tr>
                  <td class="fw-bold text-navy">House Rules</td>
                  <td>
                    5 Rules: 3 pax cap, No karaoke past 10 PM, No pets, CLAYGO, No unauthorized drilling
                  </td>
                  <td>
                    <strong>No strict rules</strong> (Bahay na ito, pang-pamilya)
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Inclusions & Utility Policy Alert Banner -->
        <div class="alert alert-warning border-warning p-4 rounded-3 shadow-sm mb-0">
          <div class="d-flex align-items-start gap-3">
            <i class="bi bi-info-circle-fill fs-3 text-warning mt-1"></i>
            <div>
              <h5 class="fw-bold text-navy mb-1">Clear Inclusions & Utility Policy</h5>
              <p class="text-muted small mb-0">
                All rentals include move-in ready basic facilities, secure perimeter access in Soldiers Village, and access to on-site compound maintenance. 
                <strong>Please note that utility bills (electricity and water) are strictly excluded from the monthly rental rates</strong> and must be shouldered directly by tenants according to actual consumption.
              </p>
            </div>
          </div>
        </div>

      </div>
    </section>
  </main>

  <footer class="footer-ebh py-4">
    <div class="container text-center text-white-50 small">
      <p class="mb-0">&copy; 2026 Eanne Boarding House Rental &bull; Soldiers Village, Pasig City</p>
    </div>
  </footer>

  <!-- Bootstrap 4.1.3 Dependencies: Full jQuery 3.7.1, Popper.js, Bootstrap 4.1.3 JS -->
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.14.3/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.1.3/dist/js/bootstrap.min.js"></script>
  <script src="../assets/js/script.js"></script>
</body>
</html>
