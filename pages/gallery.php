<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Photo Gallery | Eanne Boarding House Pasig</title>
  <meta name="description" content="Photo tour of Eanne Boarding House in Pasig City: bedrooms, kitchen, common areas, and exterior.">
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
            <span class="ebh-brand-subtitle d-none d-sm-block">Rooms & Bedspace Rental</span>
          </div>
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarContent">
          <ul class="navbar-nav mx-auto mb-2 mb-lg-0 align-items-lg-center">
            <li class="nav-item"><a class="nav-link" href="../index.php">Home</a></li>
            <li class="nav-item"><a class="nav-link" href="accommodations.php">Rooms & Rates</a></li>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle active" href="#" data-toggle="dropdown">The Property</a>
              <ul class="dropdown-menu shadow">
                <li><a class="dropdown-item" href="about.php"><i class="bi bi-building"></i> About Us</a></li>
                <li><a class="dropdown-item active" href="gallery.php"><i class="bi bi-images"></i> Photo Gallery</a></li>
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
          <i class="bi bi-images text-info"></i> Soldiers Village, Brgy. Sta. Lucia, Pasig City
        </div>
        <h1 class="display-4 fw-bold mb-3 text-white">Photo Gallery & Preview</h1>
        <p class="lead mb-4 mx-auto" style="max-width: 650px;">
          Visual tour of our bedrooms, kitchen, common areas, and gated residential compound in Soldiers Village, Pasig City. Click any photo to expand.
        </p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
          <a href="accommodations.php" class="btn btn-ebh-accent btn-lg px-4"><i class="bi bi-door-open-fill me-1"></i> View Rooms</a>
          <a href="../index.php#contact" class="btn btn-ebh-outline-light btn-lg px-4"><i class="bi bi-calendar-event me-1"></i> Book a Viewing</a>
        </div>
        <div class="row mt-5 pt-3 border-top border-white border-opacity-15 text-white g-3 justify-content-center">
          <div class="col-4 col-md-3"><div class="h3 fw-bold mb-0 text-info">100%</div><small class="opacity-75">Actual Photos</small></div>
          <div class="col-4 col-md-3"><div class="h3 fw-bold mb-0 text-info">2 Floors</div><small class="opacity-75">Clean Facility</small></div>
          <div class="col-4 col-md-3"><div class="h3 fw-bold mb-0 text-info">Secure</div><small class="opacity-75">Gated Perimeter</small></div>
        </div>
      </div>
    </section>

    <!-- Photo Gallery Grid with Interactive Filters -->
    <section class="py-5 bg-light">
      <div class="container">
        <!-- Filter Tabs -->
        <div class="d-flex flex-wrap justify-content-center align-items-center gap-2 mb-5 btn-filter-group">
          <button type="button" class="btn-filter active" data-filter="all" data-target-items="#galleryGrid .filterable-item"><i class="bi bi-grid-fill"></i> All Photos (8)</button>
          <button type="button" class="btn-filter" data-filter="bedrooms" data-target-items="#galleryGrid .filterable-item"><i class="bi bi-door-closed-fill"></i> Bedrooms (3)</button>
          <button type="button" class="btn-filter" data-filter="common" data-target-items="#galleryGrid .filterable-item"><i class="bi bi-cup-hot-fill"></i> Common Areas (3)</button>
          <button type="button" class="btn-filter" data-filter="exterior" data-target-items="#galleryGrid .filterable-item"><i class="bi bi-building"></i> Compound & Exterior (2)</button>
        </div>

        <div class="row g-4" id="galleryGrid">
          <!-- 1: Exterior Compound -->
          <div class="col-md-6 col-lg-3 filterable-item" data-category="exterior">
            <div class="gallery-card" onclick="openPhotoLightbox('../assets/img/exterior_compound.jpg', 'Residential Boarding House Facility', 'Exterior', 'Welcoming two-story residential compound located on 105 St., Soldiers Village, Pasig City.')">
              <div class="gallery-card-preview">
                <img src="../assets/img/exterior_compound.jpg" alt="Compound Exterior">
                <span class="gallery-badge-category">Exterior</span>
                <span class="gallery-badge-dimen">HD Photo</span>
                <div class="gallery-overlay">
                  <span class="gallery-overlay-btn"><i class="bi bi-zoom-in"></i> View Photo</span>
                </div>
              </div>
              <div class="gallery-card-body">
                <h6 class="fw-bold text-navy mb-1">Compound Facade</h6>
                <p class="text-muted small mb-0">Peaceful 2-story boarding house in Soldiers Village, Pasig.</p>
              </div>
            </div>
          </div>

          <!-- 2: Compound Entrance Gate -->
          <div class="col-md-6 col-lg-3 filterable-item" data-category="exterior">
            <div class="gallery-card" onclick="openPhotoLightbox('../assets/img/gated_entrance.jpg', 'Gated Compound Entrance & Night Security', 'Exterior', 'Heavy-duty steel entrance gate with perimeter security and night lights.')">
              <div class="gallery-card-preview">
                <img src="../assets/img/gated_entrance.jpg" alt="Gated Entrance">
                <span class="gallery-badge-category">Exterior</span>
                <span class="gallery-badge-dimen">HD Photo</span>
                <div class="gallery-overlay">
                  <span class="gallery-overlay-btn"><i class="bi bi-zoom-in"></i> View Photo</span>
                </div>
              </div>
              <div class="gallery-card-body">
                <h6 class="fw-bold text-navy mb-1">Gated Entrance</h6>
                <p class="text-muted small mb-0">Secure perimeter gate with night illumination & curfew security.</p>
              </div>
            </div>
          </div>

          <!-- 3: Boarding House Room -->
          <div class="col-md-6 col-lg-3 filterable-item" data-category="bedrooms">
            <div class="gallery-card" onclick="openPhotoLightbox('../assets/img/solo_room.jpg', 'Boarding House Rental Bedroom', 'Boarding House', 'Individual room with personal storage and direct kitchen access. ₱3,500 per month.')">
              <div class="gallery-card-preview">
                <img src="../assets/img/solo_room.jpg" alt="Boarding House Bedroom">
                <span class="gallery-badge-category">Boarding House</span>
                <span class="gallery-badge-dimen">₱3,500/mo</span>
                <div class="gallery-overlay">
                  <span class="gallery-overlay-btn"><i class="bi bi-zoom-in"></i> View Photo</span>
                </div>
              </div>
              <div class="gallery-card-body">
                <h6 class="fw-bold text-navy mb-1">Boarding House Room</h6>
                <p class="text-muted small mb-0">Individual room with kitchen area & shared CR (₱3,500/mo).</p>
              </div>
            </div>
          </div>

          <!-- 4: Private Boarding House Exterior / Living Space -->
          <div class="col-md-6 col-lg-3 filterable-item" data-category="bedrooms">
            <div class="gallery-card" onclick="openPhotoLightbox('../assets/img/deluxe_suite.jpg', 'Private Boarding House (Entire House)', 'Private House', 'Complete standalone private boarding house with personal kitchen, CR, and living area. Ideal for families at ₱8,000/mo.')">
              <div class="gallery-card-preview">
                <img src="../assets/img/deluxe_suite.jpg" alt="Private Boarding House">
                <span class="gallery-badge-category">Private House</span>
                <span class="gallery-badge-dimen">₱8,000/mo</span>
                <div class="gallery-overlay">
                  <span class="gallery-overlay-btn"><i class="bi bi-zoom-in"></i> View Photo</span>
                </div>
              </div>
              <div class="gallery-card-body">
                <h6 class="fw-bold text-navy mb-1">Private Boarding House</h6>
                <p class="text-muted small mb-0">Own private space with no shared common areas (₱8,000/mo).</p>
              </div>
            </div>
          </div>

          <!-- 5: Dedicated Kitchen Area -->
          <div class="col-md-6 col-lg-3 filterable-item" data-category="common">
            <div class="gallery-card" onclick="openPhotoLightbox('../assets/img/bedspace_room.jpg', 'Kitchen Area & Dining Station', 'Kitchen Area', 'Dedicated kitchen space for boarding house tenants to prepare meals.')">
              <div class="gallery-card-preview">
                <img src="../assets/img/bedspace_room.jpg" alt="Kitchen Area">
                <span class="gallery-badge-category">Kitchen Area</span>
                <span class="gallery-badge-dimen">Included</span>
                <div class="gallery-overlay">
                  <span class="gallery-overlay-btn"><i class="bi bi-zoom-in"></i> View Photo</span>
                </div>
              </div>
              <div class="gallery-card-body">
                <h6 class="fw-bold text-navy mb-1">Kitchen Area</h6>
                <p class="text-muted small mb-0">Dedicated cooking and meal preparation area.</p>
              </div>
            </div>
          </div>

          <!-- 6: Shared Kitchen -->
          <div class="col-md-6 col-lg-3 filterable-item" data-category="common">
            <div class="gallery-card" onclick="openPhotoLightbox('../assets/img/shared_kitchen.jpg', 'Shared Kitchen & Dining Station', 'Common Area', 'Clean communal pantry equipped with refrigerator, microwave, sink, and dining table.')">
              <div class="gallery-card-preview">
                <img src="../assets/img/shared_kitchen.jpg" alt="Shared Kitchen">
                <span class="gallery-badge-category">Kitchen</span>
                <span class="gallery-badge-dimen">HD Photo</span>
                <div class="gallery-overlay">
                  <span class="gallery-overlay-btn"><i class="bi bi-zoom-in"></i> View Photo</span>
                </div>
              </div>
              <div class="gallery-card-body">
                <h6 class="fw-bold text-navy mb-1">Shared Kitchen</h6>
                <p class="text-muted small mb-0">Pantry with refrigerator, microwave, sink, and dining set.</p>
              </div>
            </div>
          </div>

          <!-- 7: Study Lounge -->
          <div class="col-md-6 col-lg-3 filterable-item" data-category="common">
            <div class="gallery-card" onclick="openPhotoLightbox('../assets/img/study_lounge.jpg', 'Quiet Study Lounge & Library Space', 'Common Area', 'Dedicated reading and study area with comfortable seating and warm reading lamps.')">
              <div class="gallery-card-preview">
                <img src="../assets/img/study_lounge.jpg" alt="Study Lounge">
                <span class="gallery-badge-category">Common</span>
                <span class="gallery-badge-dimen">HD Photo</span>
                <div class="gallery-overlay">
                  <span class="gallery-overlay-btn"><i class="bi bi-zoom-in"></i> View Photo</span>
                </div>
              </div>
              <div class="gallery-card-body">
                <h6 class="fw-bold text-navy mb-1">Study Lounge</h6>
                <p class="text-muted small mb-0">Quiet tables and chairs for late night study and remote work.</p>
              </div>
            </div>
          </div>

          <!-- 8: Residential Corridor & Hallway -->
          <div class="col-md-6 col-lg-3 filterable-item" data-category="common">
            <div class="gallery-card" onclick="openPhotoLightbox('../assets/img/hallway_corridor.jpg', 'Residential Hallway & Corridor', 'Common Area', 'Bright, well-lit corridor with ceramic tile flooring and room number identifiers.')">
              <div class="gallery-card-preview">
                <img src="../assets/img/hallway_corridor.jpg" alt="Hallway Corridor">
                <span class="gallery-badge-category">Common</span>
                <span class="gallery-badge-dimen">HD Photo</span>
                <div class="gallery-overlay">
                  <span class="gallery-overlay-btn"><i class="bi bi-zoom-in"></i> View Photo</span>
                </div>
              </div>
              <div class="gallery-card-body">
                <h6 class="fw-bold text-navy mb-1">Hallway Corridor</h6>
                <p class="text-muted small mb-0">Well-lit clean hallway with tile flooring & emergency exit lights.</p>
              </div>
            </div>
          </div>
        </div>

        <div class="text-center mt-5">
          <a href="../index.php#contact" class="btn btn-ebh-primary btn-lg px-4"><i class="bi bi-calendar2-check me-1"></i> Schedule an In-Person Viewing</a>
        </div>
      </div>
    </section>
  </main>

  <footer class="footer-ebh py-4">
    <div class="container text-center text-white-50 small">
      <p class="mb-0">&copy; 2026 Eanne Boarding House Rental • Soldiers Village, Pasig City</p>
    </div>
  </footer>

  <!-- Bootstrap 4.1.3 Dependencies: Full jQuery 3.7.1, Popper.js, Bootstrap 4.1.3 JS -->
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.14.3/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.1.3/dist/js/bootstrap.min.js"></script>
  <script src="../assets/js/script.js"></script>
</body>
</html>
