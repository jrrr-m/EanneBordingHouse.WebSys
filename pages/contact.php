<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact & Schedule a Viewing | Eanne Boarding House Pasig</title>
  <meta name="description" content="Contact Eanne Boarding House in Soldiers Village, Pasig City. Inquire about rooms or schedule an in-person viewing tour.">
  
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

  <!-- Navigation -->
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
              <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">The Property</a>
              <ul class="dropdown-menu shadow">
                <li><a class="dropdown-item" href="about.php"><i class="bi bi-building"></i> About Us</a></li>
                <li><a class="dropdown-item" href="gallery.php"><i class="bi bi-images"></i> Photo Gallery</a></li>
                <li><a class="dropdown-item" href="house-rules.php"><i class="bi bi-shield-check"></i> House Rules</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item active" href="contact.php"><i class="bi bi-geo-alt-fill"></i> Location</a></li>
              </ul>
            </li>
            <li class="nav-item"><a class="nav-link active" href="contact.php">Contact</a></li>
          </ul>
          <div class="d-flex align-items-center gap-2">
            <a href="#viewingFormSection" class="btn btn-ebh-accent btn-sm px-3 fw-bold">Book a Viewing</a>
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
          <i class="bi bi-geo-alt-fill text-info"></i> Soldiers Village, Brgy. Sta. Lucia, Pasig City
        </div>
        <h1 class="display-4 fw-bold mb-3 text-white">Contact & Room Viewing</h1>
        <p class="lead mb-4 mx-auto" style="max-width: 650px;">
          Have questions or want to see our available solo rooms and bedspaces? Schedule an in-person tour or reach out to our property manager.
        </p>
        <div class="row mt-5 pt-3 border-top border-white border-opacity-15 text-white g-3 justify-content-center">
          <div class="col-4 col-md-3"><div class="h3 fw-bold mb-0 text-info">Mon - Sat</div><small class="opacity-75">9:00 AM - 6:00 PM</small></div>
          <div class="col-4 col-md-3"><div class="h3 fw-bold mb-0 text-info">Free</div><small class="opacity-75">Walk-through Tour</small></div>
          <div class="col-4 col-md-3"><div class="h3 fw-bold mb-0 text-info">Same-Day</div><small class="opacity-75">SMS Confirmation</small></div>
        </div>
      </div>
    </section>

    <!-- Main Contact & Booking Section -->
    <section class="py-5 bg-white" id="viewingFormSection">
      <div class="container">
        <div class="row g-5">
          <!-- Left: In-Person Viewing Booking Form -->
          <div class="col-lg-7">
            <div class="ebh-card p-4 shadow-sm border-0 bg-light">
              <h4 class="fw-bold text-navy mb-2"><i class="bi bi-calendar2-check-fill me-2 text-primary"></i> Schedule an In-Person Viewing</h4>
              <p class="text-muted small mb-4">Fill out your details below and Kuya Boy (our compound caretaker) will confirm your visit via SMS.</p>

              <!-- Success Alert (Hidden until submission) -->
              <div class="alert alert-success border-success" id="contactSuccessAlert" style="display:none;">
                <h6 class="fw-bold text-success mb-1"><i class="bi bi-check-circle-fill me-1"></i> Viewing Request Confirmed!</h6>
                <p class="small mb-0">Thank you! Your schedule request has been logged. Our caretaker will text you at your provided mobile number.</p>
              </div>

              <form id="contactViewingForm">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label for="contactFullName" class="form-label small fw-bold text-navy">Your Full Name</label>
                    <input type="text" class="form-control" id="contactFullName" required placeholder="e.g. Juanita Gomez">
                  </div>
                  <div class="col-md-6">
                    <label for="contactMobile" class="form-label small fw-bold text-navy">Mobile Number (SMS)</label>
                    <input type="tel" class="form-control" id="contactMobile" required placeholder="0917-000-0000">
                  </div>
                  <div class="col-md-6">
                    <label for="contactRoomType" class="form-label small fw-bold text-navy">Interested Accommodation</label>
                    <select class="form-control" id="contactRoomType">
                      <option value="Boarding House Rental">Boarding House Rental (₱3,500 / mo)</option>
                      <option value="Private Boarding House">Private Boarding House (₱8,000 / mo)</option>
                      <option value="General Inquiry">General Inquiry / Either</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label for="contactPreferredDate" class="form-label small fw-bold text-navy">Preferred Tour Date & Time</label>
                    <input type="date" class="form-control" id="contactPreferredDate" required>
                  </div>
                  <div class="col-12">
                    <label for="contactNotes" class="form-label small fw-bold text-navy">Target Move-in Date / Questions</label>
                    <textarea class="form-control" id="contactNotes" rows="3" placeholder="Tell us your target move-in date or any questions regarding our house rules..."></textarea>
                  </div>
                  <div class="col-12">
                    <button type="submit" class="btn btn-ebh-primary btn-lg w-100 fw-bold shadow-sm">
                      <i class="bi bi-send-fill me-1"></i> Confirm Viewing Schedule
                    </button>
                  </div>
                </div>
              </form>
            </div>
          </div>

          <!-- Right: Location Details, Hotline, & Commute Guide -->
          <div class="col-lg-5">
            <!-- Gated Compound Entrance Photo -->
            <div class="ebh-card overflow-hidden mb-4 shadow-sm border-0">
              <div style="height: 200px; position: relative; overflow: hidden;">
                <img src="../assets/img/gated_entrance.jpg" alt="Soldiers Village Compound Gate" style="width: 100%; height: 100%; object-fit: cover;">
                <span class="gallery-badge-category"><i class="bi bi-shield-lock-fill me-1"></i> Gated Compound</span>
              </div>
            </div>

            <!-- Location Details -->
            <div class="ebh-card p-4 mb-4">
              <h5 class="fw-bold text-navy mb-3"><i class="bi bi-geo-alt-fill text-danger me-2"></i> Compound Address</h5>
              <p class="text-navy fw-semibold mb-1">Eanne Boarding House Rental</p>
              <p class="text-muted small mb-3">
                #275 105 St. Soldiers Village, Brgy. Sta. Lucia, Pasig City, Metro Manila, Philippines
              </p>

              <div class="d-flex flex-column gap-2 small border-top pt-3">
                <div class="d-flex align-items-center gap-2">
                  <i class="bi bi-telephone-fill text-primary"></i>
                  <span>Landlord Hotline: <strong>0917-555-EBH1</strong></span>
                </div>
                <div class="d-flex align-items-center gap-2">
                  <i class="bi bi-person-badge-fill text-info"></i>
                  <span>Caretaker (Kuya Boy): <strong>0918-777-BOY1</strong></span>
                </div>
                <div class="d-flex align-items-center gap-2">
                  <i class="bi bi-clock-fill text-warning"></i>
                  <span>Tour Hours: <strong>Mon - Sat, 9:00 AM - 6:00 PM</strong></span>
                </div>
              </div>
            </div>

            <!-- Commute Directions Guide -->
            <div class="ebh-card p-4 mb-4">
              <h5 class="fw-bold text-navy mb-3"><i class="bi bi-signpost-2-fill text-info me-2"></i> How to Get Here</h5>
              <div class="d-flex flex-column gap-3 small text-muted">
                <div>
                  <strong class="text-navy d-block">From Ortigas CBD / Robinsons Galleria:</strong>
                  Take a jeepney or UV Express bound for Pasig Palengke or Taytay/Cainta. Drop off at Soldiers Village Gate along Tramo / Floodway road.
                </div>
                <div>
                  <strong class="text-navy d-block">From Eastwood City / Libis:</strong>
                  Take a tricycle or jeep towards Jenny's Ave / Rosario, then ride towards Soldiers Village, Brgy. Sta. Lucia.
                </div>
                <div class="p-2 rounded bg-light border text-navy fw-semibold">
                  <i class="bi bi-shield-check text-success me-1"></i> Landmark: Soldiers Village Gate guardhouse. Tell guard you are visiting #275 105 Street.
                </div>
              </div>
            </div>

            <!-- Interactive Google Map Embed -->
            <div class="ebh-card overflow-hidden shadow-sm border p-0">
              <div class="p-3 bg-light border-bottom d-flex align-items-center justify-content-between">
                <div>
                  <strong class="text-navy d-block small"><i class="bi bi-geo-alt-fill text-danger me-1"></i> Soldiers Village Compound Map</strong>
                  <span class="text-muted" style="font-size:0.75rem;">Brgy. Sta. Lucia, Pasig City</span>
                </div>
                <a href="https://maps.google.com/?q=Soldiers+Village+Sta+Lucia+Pasig" target="_blank" rel="noopener noreferrer" class="btn btn-xs btn-outline-primary py-1 px-2" style="font-size:0.75rem;">
                  <i class="bi bi-box-arrow-up-right me-1"></i> Open Google Maps
                </a>
              </div>
              <div style="position: relative; width: 100%; height: 280px; overflow: hidden;">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d293734.37503802107!2d121.00392745175189!3d14.691211130934194!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397c700370e17c5%3A0x44673bbd2d2ff65d!2sSoldier%E2%80%99s%20village!5e0!3m2!1sen!2sph!4v1792996817495!5m2!1sen!2sph" width="100%" height="100%" style="border:0; position:absolute; top:0; left:0; width:100%; height:100%;" allowfullscreen="" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>

  <!-- Footer -->
  <footer class="footer-ebh">
    <div class="container">
      <div class="row g-4 mb-4">
        <div class="col-lg-5">
          <a class="ebh-brand dark mb-2 d-inline-flex" href="../index.php">
            <div class="ebh-logo-mark"><img src="../assets/img/795286329_1754004695838265_6753877283478212113_n.png" alt="EBH Logo"></div>
            <div class="ebh-brand-text"><span class="ebh-brand-title text-white">Eanne Boarding House</span><span class="ebh-brand-subtitle">Pasig City</span></div>
          </a>
          <p class="small text-white-50">Comfortable, clean, and secure rooms for students and professionals in Soldiers Village, Pasig.</p>
        </div>
        <div class="col-6 col-lg-3">
          <h5>Quick Links</h5>
          <ul class="list-unstyled small d-flex flex-column gap-1">
            <li><a href="about.php">About Us</a></li>
            <li><a href="accommodations.php">Rooms & Rates</a></li>
            <li><a href="gallery.php">Photo Gallery</a></li>
            <li><a href="house-rules.php">House Rules</a></li>
            <li><a href="contact.php">Location & Contact</a></li>
          </ul>
        </div>
        <div class="col-6 col-lg-4">
          <h5>Location</h5>
          <address class="small text-white-50">
            <p class="mb-1"><i class="bi bi-geo-alt text-info me-2"></i>#275 105 St. Soldiers Village, Pasig City</p>
            <p class="mb-1"><i class="bi bi-telephone text-info me-2"></i>0917-555-EBH1</p>
            <p class="mb-0"><i class="bi bi-clock text-info me-2"></i>Mon - Sat: 9:00 AM - 6:00 PM</p>
          </address>
        </div>
      </div>
      <div class="border-top border-white border-opacity-10 pt-3 text-center small text-white-50">
        <p class="mb-0">&copy; 2026 Eanne Boarding House Rental. All rights reserved.</p>
      </div>
    </div>
  </footer>

  <!-- Full jQuery 3.7.1, Popper.js, Bootstrap 4.1.3 JS -->
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.14.3/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.1.3/dist/js/bootstrap.min.js"></script>
  <script src="../assets/js/script.js"></script>
</body>
</html>
