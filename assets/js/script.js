$(document).ready(function () {

  if (!$('#ebhToastContainer').length) {
    $('body').append('<div id="ebhToastContainer" class="ebh-toast-container"></div>');
  }

  if (!$('#btnBackToTop').length) {
    $('body').append('<a href="javascript:void(0)" id="btnBackToTop" class="btn-back-to-top" title="Back to top"><i class="bi bi-chevron-up"></i></a>');
  }

  // 1. Toast Notification Utility Function
  window.showToast = function (title, message, type = 'info', duration = 3500) {
    const iconMap = {
      success: 'bi-check-circle-fill text-success',
      danger: 'bi-x-circle-fill text-danger',
      warning: 'bi-exclamation-triangle-fill text-warning',
      info: 'bi-info-circle-fill text-primary'
    };
    const icon = iconMap[type] || iconMap.info;

    const toastId = 'toast-' + Date.now();
    const toastHtml = `
      <div class="ebh-toast ${type}" id="${toastId}">
        <i class="bi ${icon} fs-5 mt-1"></i>
        <div class="flex-grow-1">
          <div class="fw-bold small text-navy">${title}</div>
          <div class="text-muted small">${message}</div>
        </div>
        <button type="button" class="btn-close btn-sm ms-2 border-0 bg-transparent text-muted" aria-label="Close" style="font-size:0.85rem;"><i class="bi bi-x-lg"></i></button>
      </div>
    `;

    const $toast = $(toastHtml).hide();
    $('#ebhToastContainer').append($toast);
    $toast.fadeIn(250);

    // Close button click
    $toast.find('button').on('click', function () {
      $toast.fadeOut(250, function () { $(this).remove(); });
    });

    // Auto dismiss
    setTimeout(function () {
      if ($toast.length) {
        $toast.fadeOut(300, function () { $(this).remove(); });
      }
    }, duration);
  };

  // 2. Mobile Sidebar Toggle with Backdrop
  const $sidebar = $('.dashboard-sidebar');
  const $sidebarBtn = $('#sidebarToggleBtn');

  if ($sidebarBtn.length && $sidebar.length) {
    $sidebarBtn.on('click', function (e) {
      e.stopPropagation();
      $sidebar.toggleClass('show');
    });

    $(document).on('click', function (e) {
      if ($(window).width() < 992 && !$sidebar.is(e.target) && $sidebar.has(e.target).length === 0 && !$sidebarBtn.is(e.target) && $sidebarBtn.has(e.target).length === 0) {
        $sidebar.removeClass('show');
      }
    });
  }

  // 3. Scroll to Top Floating Button
  $(window).on('scroll', function () {
    if ($(this).scrollTop() > 250) {
      $('#btnBackToTop').fadeIn(200);
    } else {
      $('#btnBackToTop').fadeOut(200);
    }
  });

  $('#btnBackToTop').on('click', function (e) {
    e.preventDefault();
    $('html, body').animate({ scrollTop: 0 }, 500);
  });

  // 4. Safe Smooth Anchor Scrolling for Public Pages
  $('a[href^="#"]').on('click', function (e) {
    const href = this.getAttribute('href');
    if (!href || href === '#' || href.length <= 1) return;
    try {
      const target = $(href);
      if (target.length) {
        e.preventDefault();
        const navOffset = $('.navbar-ebh').outerHeight() || 70;
        $('html, body').stop().animate({
          scrollTop: target.offset().top - navOffset
        }, 500);

        // Auto close mobile navbar if open
        if ($(window).width() < 992) {
          $('#navbarContent').collapse('hide');
        }
      }
    } catch (err) {
      // Ignore selector syntax errors
    }
  });

  // Auto-close mobile navbar on clicking standard navigation links
  $('.navbar-ebh .navbar-nav .nav-link:not(.dropdown-toggle), .navbar-ebh .dropdown-item').on('click', function () {
    if ($(window).width() < 992) {
      $('#navbarContent').collapse('hide');
    }
  });

  // 5. Animated Number Roll-up Counter for KPIs
  $('.kpi-value[data-counter]').each(function () {
    const $this = $(this);
    const countTo = parseFloat($this.attr('data-counter')) || 0;
    const prefix = $this.attr('data-prefix') || '';
    const suffix = $this.attr('data-suffix') || '';
    const isCurrency = prefix === '₱';

    $({ countNum: 0 }).animate({
      countNum: countTo
    }, {
      duration: 1200,
      easing: 'swing',
      step: function () {
        const val = isCurrency ? Math.floor(this.countNum).toLocaleString() : Math.floor(this.countNum);
        $this.text(prefix + val + suffix);
      },
      complete: function () {
        const val = isCurrency ? Math.floor(this.countNum).toLocaleString() : Math.floor(this.countNum);
        $this.text(prefix + val + suffix);
      }
    });
  });

  // 6. Interactive Filter Pills (Rooms, Tenants, Payments)
  $('.btn-filter').on('click', function () {
    const $btn = $(this);
    const $group = $btn.closest('.btn-filter-group');
    const filter = $btn.attr('data-filter');
    const targetSelector = $btn.attr('data-target-items') || '.filterable-item';

    $group.find('.btn-filter').removeClass('active');
    $btn.addClass('active');

    const $items = $(targetSelector);

    if (filter === 'all' || !filter) {
      $items.each(function (index) {
        $(this).delay(index * 30).fadeIn(200);
      });
    } else {
      $items.each(function () {
        const itemCategory = $(this).attr('data-category') || '';
        const itemStatus = $(this).attr('data-status') || '';
        if (itemCategory.includes(filter) || itemStatus === filter) {
          $(this).fadeIn(200);
        } else {
          $(this).fadeOut(150);
        }
      });
    }
  });

  // 7. Interactive Table Search Filtering
  function setupTableSearch(inputId, tableId, countBadgeId) {
    const $input = $(inputId);
    const $table = $(tableId);
    if (!$input.length || !$table.length) return;

    $input.on('keyup', function () {
      const value = $(this).val().toLowerCase().trim();
      let matchCount = 0;

      $table.find('tbody tr').each(function () {
        const rowText = $(this).text().toLowerCase();
        const matches = rowText.indexOf(value) > -1;
        $(this).toggle(matches);
        if (matches) matchCount++;
      });

      if (countBadgeId && $(countBadgeId).length) {
        $(countBadgeId).text(matchCount);
      }
    });
  }

  setupTableSearch('#tenantSearchInput', '#tenantsTable', '#tenantCountBadge');
  setupTableSearch('#paymentSearchInput', '#paymentsTable', '#paymentCountBadge');
  setupTableSearch('#reportsSearchInput', '#reportsTable', '#reportsCountBadge');

  // 8. Sign In Form Handler with Card Shake Animation
  const $loginForm = $('#ebhLoginForm');
  if ($loginForm.length) {
    $loginForm.on('submit', function (e) {
      e.preventDefault();
      const user = ($('#loginUsername').val() || '').trim().toLowerCase();
      const pass = ($('#loginPassword').val() || '').trim();
      const $card = $loginForm.closest('.ebh-card');

      if (user === 'admin' && pass === 'admin12345') {
        showToast('Login Successful', 'Welcome back, Landlord Admin!', 'success');
        setTimeout(function () {
          window.location.href = 'admin-dashboard.html';
        }, 700);
      } else if (user === 'tenant' && pass === 'tenant12345') {
        showToast('Login Successful', 'Welcome back, Maria Clara!', 'success');
        setTimeout(function () {
          window.location.href = 'tenant-dashboard.html';
        }, 700);
      } else {
        // Shaking animation for incorrect credentials
        $card.animate({ marginLeft: '-15px' }, 60)
             .animate({ marginLeft: '15px' }, 60)
             .animate({ marginLeft: '-10px' }, 60)
             .animate({ marginLeft: '10px' }, 60)
             .animate({ marginLeft: '0px' }, 60);

        showToast('Access Denied', 'Invalid username or password. Please try again.', 'danger');
      }
    });
  }

  // 9. Tenant Proof of Payment Upload Form Handling
  const $tenantPayForm = $('#tenantUploadPaymentForm');
  if ($tenantPayForm.length) {
    // Live file upload preview
    $('#paymentReceiptFile').on('change', function () {
      const file = this.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function (e) {
          $('#uploadPreviewContainer').slideDown(200);
          $('#uploadPreviewImg').attr('src', e.target.result);
          $('#uploadPreviewName').text(file.name);
        };
        reader.readAsDataURL(file);
      }
    });

    $tenantPayForm.on('submit', function (e) {
      e.preventDefault();
      const $submitBtn = $(this).find('button[type="submit"]');
      const origText = $submitBtn.html();

      $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2" role="status"></span> Uploading receipt...');

      // Animate progress simulation
      $('#uploadProgressBarContainer').slideDown(150);
      $('#uploadProgressBar').css('width', '0%').animate({ width: '100%' }, 900, function () {
        setTimeout(function () {
          $submitBtn.prop('disabled', false).html(origText);
          $('#uploadProgressBarContainer').slideUp(200);
          $('#uploadPreviewContainer').slideUp(200);

          const method = $('#payMethodSelect').val() || 'GCash';
          const refNo = $('#payRefNumber').val() || 'REF-' + Math.floor(100000 + Math.random() * 900000);
          const amount = $('#payAmount').val() || '3,500';
          const dateStr = new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

          // Prepend row to Tenant Payment History Table
          const newRow = `
            <tr class="table-warning">
              <td class="fw-bold">${refNo}</td>
              <td>${dateStr}</td>
              <td><span class="badge badge-light border"><i class="bi bi-phone me-1 text-primary"></i> ${method}</span></td>
              <td class="fw-bold text-navy">₱${parseFloat(amount).toLocaleString()}</td>
              <td><span class="badge-ebh badge-pending"><i class="bi bi-clock-history"></i> Pending Verification</span></td>
              <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="previewReceiptModal('${refNo}', 'Maria Clara Santos', 'Unit BH-02 (Boarding House)', '${amount}', '${dateStr}', '${method}', '${refNo}')">
                  <i class="bi bi-eye"></i> View
                </button>
              </td>
            </tr>
          `;

          const $tbody = $('#tenantHistoryTable tbody');
          if ($tbody.length) {
            $(newRow).hide().prependTo($tbody).fadeIn(400);
          }

          $tenantPayForm[0].reset();
          $('#tenantUploadModal').modal('hide');
          showToast('Payment Submitted', `Receipt ${refNo} sent to Landlord for verification.`, 'success');
        }, 300);
      });
    });
  }

  // 10. Tenant Maintenance Ticket Submission
  const $maintenanceForm = $('#tenantMaintenanceForm');
  if ($maintenanceForm.length) {
    $maintenanceForm.on('submit', function (e) {
      e.preventDefault();
      const category = $('#ticketCategory').val() || 'General';
      const urgency = $('#ticketUrgency').val() || 'Normal';
      const description = $('#ticketDescription').val() || 'Assistance requested.';
      const ticketId = 'TKT-' + Math.floor(1000 + Math.random() * 9000);
      const isUrgent = urgency === 'Urgent';

      const ticketHtml = `
        <div class="ticket-item ${isUrgent ? 'urgent' : ''}" style="display:none;">
          <div class="d-flex align-items-center justify-content-between mb-1">
            <span class="fw-bold small text-navy">${category} Issue (#${ticketId})</span>
            <span class="badge-ebh badge-pending"><i class="bi bi-clock"></i> Queued</span>
          </div>
          <p class="small text-muted mb-2">${description}</p>
          <div class="d-flex align-items-center justify-content-between text-muted" style="font-size:0.75rem;">
            <span><i class="bi bi-calendar3 me-1"></i> Just now</span>
            <span class="${isUrgent ? 'text-danger fw-bold' : ''}"><i class="bi bi-flag-fill me-1"></i> ${urgency} Priority</span>
          </div>
        </div>
      `;

      const $list = $('#tenantTicketList');
      if ($list.length) {
        $(ticketHtml).prependTo($list).slideDown(300);
      }

      $maintenanceForm[0].reset();
      showToast('Maintenance Ticket Logged', `Ticket #${ticketId} submitted. The caretaker has been notified.`, 'info');
    });
  }

  // 11. Add New Tenant Modal Form
  const $addTenantForm = $('#addTenantForm');
  if ($addTenantForm.length) {
    $addTenantForm.on('submit', function (e) {
      e.preventDefault();
      const name = $('#newTenantName').val();
      const contact = $('#newTenantContact').val();
      const room = $('#newTenantRoom').val();
      const type = $('#newTenantType').val();
      const rent = $('#newTenantRent').val();
      const moveIn = $('#newTenantMoveIn').val() || new Date().toISOString().split('T')[0];

      const initials = name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();

      const newRowHtml = `
        <tr class="filterable-item" data-status="active" style="display:none;">
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="avatar-circle sm">${initials}</div>
              <div>
                <div class="fw-bold text-navy">${name}</div>
                <small class="text-muted">${contact}</small>
              </div>
            </div>
          </td>
          <td><span class="fw-semibold text-navy">${room}</span></td>
          <td><span class="badge badge-light border">${type}</span></td>
          <td class="fw-bold text-navy">₱${parseFloat(rent).toLocaleString()}</td>
          <td><small class="text-muted">${moveIn}</small></td>
          <td><span class="badge-ebh badge-verified"><i class="bi bi-check-circle"></i> Active</span></td>
          <td class="text-end">
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-primary" title="View Profile" onclick="showTenantDetails('${name}', '${room}', '${type}', '₱${parseFloat(rent).toLocaleString()}', '${contact}', '${moveIn}')"><i class="bi bi-eye"></i></button>
              <button class="btn btn-outline-danger" title="Archive / Move Out" onclick="removeTenantRow(this, '${name}')"><i class="bi bi-box-arrow-right"></i></button>
            </div>
          </td>
        </tr>
      `;

      $('#tenantsTable tbody').prepend($(newRowHtml));
      $('#tenantsTable tbody tr:first-child').fadeIn(350);

      // Increment count
      const $badge = $('#tenantCountBadge');
      if ($badge.length) {
        $badge.text(parseInt($badge.text() || 0) + 1);
      }

      $addTenantForm[0].reset();
      $('#addTenantModal').modal('hide');
      showToast('Tenant Registered', `${name} assigned to ${room}.`, 'success');
    });
  }

  // 12. Add Room Inventory Modal Form
  const $addRoomForm = $('#addRoomForm');
  if ($addRoomForm.length) {
    $addRoomForm.on('submit', function (e) {
      e.preventDefault();
      const roomNum = $('#newRoomNumber').val();
      const floor = $('#newRoomFloor').val();
      const type = $('#newRoomType').val();
      const rate = $('#newRoomRate').val();
      const capacity = $('#newRoomCapacity').val() || 1;

      const cardHtml = `
        <div class="col-md-6 col-lg-4 filterable-item" data-category="${floor} ${type.toLowerCase()}" data-status="available" style="display:none;">
          <div class="room-card h-100">
            <div class="room-card-header">
              <div class="d-flex align-items-center">
                <span class="room-status-dot available"></span>
                <strong class="text-navy">${roomNum}</strong>
                <span class="badge badge-light border ms-2 small">${floor}</span>
              </div>
              <span class="badge-ebh badge-verified"><i class="bi bi-check-circle"></i> Available</span>
            </div>
            <div class="ebh-card-body d-flex flex-column flex-grow-1">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-muted small">${type} Unit</span>
                <span class="fw-bold text-navy fs-5">₱${parseFloat(rate).toLocaleString()}<small class="text-muted fw-normal">/mo</small></span>
              </div>
              <p class="text-muted small mb-3">Capacity: ${capacity} Person(s). Water & Electricity included in monthly rent.</p>
              <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                <span class="small text-success fw-semibold"><i class="bi bi-door-open me-1"></i> Ready for move-in</span>
                <button class="btn btn-sm btn-outline-primary" onclick="showToast('Room Assigned', 'Assigning ${roomNum} to applicant...', 'info')">Assign</button>
              </div>
            </div>
          </div>
        </div>
      `;

      $('#roomsGridContainer').prepend($(cardHtml));
      $('#roomsGridContainer > div:first-child').fadeIn(350);

      $addRoomForm[0].reset();
      $('#addRoomModal').modal('hide');
      showToast('Room Created', `${roomNum} added to inventory.`, 'success');
    });
  }

  // 13. Public Contact & Viewing Form
  const $contactForm = $('#contactViewingForm');
  if ($contactForm.length) {
    $contactForm.on('submit', function (e) {
      e.preventDefault();
      const $btn = $(this).find('button[type="submit"]');
      const originalText = $btn.html();

      $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Submitting request...');

      setTimeout(function () {
        $btn.prop('disabled', false).html(originalText);
        $contactForm[0].reset();
        $('#contactSuccessAlert').slideDown(250);
        showToast('Viewing Scheduled', 'Thank you! The landlord will contact you to confirm your schedule.', 'success');
      }, 700);
    });
  }
});

// Admin Payment Verification Action
window.processPayment = function (id, action) {
  const isApprove = action === 'approved';
  const $row = $('#payment-row-' + id);

  if ($row.length) {
    $row.find('.action-btn-group').fadeOut(150, function () {
      if (isApprove) {
        $row.find('.status-col').html('<span class="badge-ebh badge-verified"><i class="bi bi-check-circle"></i> Verified</span>');
      } else {
        $row.find('.status-col').html('<span class="badge-ebh badge-rejected"><i class="bi bi-x-circle"></i> Rejected</span>');
      }
      $(this).html(`<span class="small text-muted fw-semibold">${isApprove ? 'Approved' : 'Rejected'}</span>`).fadeIn(150);
    });

    // Update pending queue badge count
    const $badge = $('#pendingQueueBadge, #queueBadgeCount');
    if ($badge.length) {
      const current = parseInt($badge.text() || 0);
      if (current > 0) {
        $badge.text(current - 1);
        if (current - 1 === 0) {
          $badge.removeClass('bg-danger').addClass('bg-success');
        }
      }
    }

    showToast(
      isApprove ? 'Payment Verified' : 'Payment Rejected',
      `Payment receipt #${id} has been marked as ${isApprove ? 'verified and credited' : 'rejected'}.`,
      isApprove ? 'success' : 'warning'
    );
  }
};

// Preview Official Receipt Modal
window.previewReceiptModal = function (id, tenant, room, amount, date, method, ref) {
  const tenantName = tenant || 'Maria Clara Santos';
  const roomName = room || 'Unit BH-02 (Boarding House)';
  const amountStr = amount || '3,500';
  const dateStr = date || 'Oct 2, 2026';
  const methodStr = method || 'GCash Transfer';
  const refNum = ref || id || 'GC-992813';

  const modalHtml = `
    <div class="modal fade" id="receiptPreviewModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg">
          <div class="modal-header bg-navy text-white">
            <h6 class="modal-title fw-bold text-white mb-0"><i class="bi bi-receipt me-2"></i> Official Digital Receipt</h6>
            <button type="button" class="close text-white border-0 bg-transparent" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body p-4 bg-light">
            <div class="receipt-paper">
              <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                  <h6 class="fw-bold text-navy mb-0">EANNE BOARDING HOUSE</h6>
                  <small class="text-muted">#275 105 St. Soldiers Village, Pasig City</small>
                </div>
                <div class="receipt-stamp-paid">PAID</div>
              </div>
              <hr class="my-2">
              <div class="row g-2 mb-3 small">
                <div class="col-6"><span class="text-muted d-block">Receipt No:</span><strong class="text-navy">#${refNum}</strong></div>
                <div class="col-6 text-end"><span class="text-muted d-block">Payment Date:</span><strong>${dateStr}</strong></div>
                <div class="col-6"><span class="text-muted d-block">Tenant:</span><strong>${tenantName}</strong></div>
                <div class="col-6 text-end"><span class="text-muted d-block">Room Assigned:</span><strong>${roomName}</strong></div>
                <div class="col-6"><span class="text-muted d-block">Payment Method:</span><strong>${methodStr}</strong></div>
                <div class="col-6 text-end"><span class="text-muted d-block">Landlord Status:</span><span class="text-success fw-bold">Verified</span></div>
              </div>
              <div class="bg-light p-3 rounded mb-3">
                <div class="d-flex justify-content-between small mb-1">
                  <span>Monthly Room Rent (Inclusive of Utilities):</span>
                  <span>₱${parseFloat(amountStr).toLocaleString()}.00</span>
                </div>
                <div class="d-flex justify-content-between small text-muted mb-2">
                  <span>Water & Electricity Allowance:</span>
                  <span>Included</span>
                </div>
                <div class="d-flex justify-content-between fw-bold text-navy border-top pt-2">
                  <span>TOTAL AMOUNT PAID:</span>
                  <span class="fs-5 text-primary">₱${parseFloat(amountStr).toLocaleString()}.00</span>
                </div>
              </div>
              <div class="text-center small text-muted">
                <i class="bi bi-shield-check text-success me-1"></i> E-Verified by Eanne Boarding House Property Manager
              </div>
            </div>
          </div>
          <div class="modal-footer bg-white border-top">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Close</button>
            <button type="button" class="btn btn-ebh-primary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print Receipt</button>
          </div>
        </div>
      </div>
    </div>
  `;

  $('#receiptPreviewModal').remove();
  $('body').append(modalHtml);
  $('#receiptPreviewModal').modal('show');
};

// Show Tenant Details Drawer / Modal
window.showTenantDetails = function (name, room, type, rent, contact, moveIn) {
  const detailsHtml = `
    <div class="modal fade" id="tenantDetailsModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg">
          <div class="modal-header bg-navy text-white">
            <h6 class="modal-title fw-bold text-white mb-0"><i class="bi bi-person-badge me-2"></i> Tenant Profile: ${name}</h6>
            <button type="button" class="close text-white border-0 bg-transparent" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body p-4">
            <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
              <div class="avatar-circle" style="width:54px; height:54px; font-size:1.25rem;">${name.split(' ').map(n=>n[0]).join('').substring(0,2)}</div>
              <div>
                <h5 class="fw-bold text-navy mb-0">${name}</h5>
                <span class="badge-ebh badge-verified mt-1"><i class="bi bi-check-circle"></i> Current Resident</span>
              </div>
            </div>
            <div class="row g-3 small">
              <div class="col-6"><span class="text-muted d-block">Room Assigned:</span><strong class="text-navy fs-6">${room}</strong></div>
              <div class="col-6"><span class="text-muted d-block">Room Type:</span><strong>${type}</strong></div>
              <div class="col-6"><span class="text-muted d-block">Monthly Rent:</span><strong class="text-primary fs-6">${rent}</strong></div>
              <div class="col-6"><span class="text-muted d-block">Due Date:</span><strong>Every 5th of Month</strong></div>
              <div class="col-6"><span class="text-muted d-block">Contact Number:</span><strong>${contact}</strong></div>
              <div class="col-6"><span class="text-muted d-block">Move-in Date:</span><strong>${moveIn}</strong></div>
              <div class="col-12"><span class="text-muted d-block">Emergency Contact:</span><strong>Family Member (${contact.slice(0, 4)}-EMG-REL)</strong></div>
            </div>
            <div class="alert alert-info mt-4 mb-0 small">
              <i class="bi bi-info-circle me-1"></i> Lease Agreement is valid for 6 months with 1-month advance & 1-month deposit on record.
            </div>
          </div>
          <div class="modal-footer bg-light">
            <a href="tel:${contact}" class="btn btn-outline-primary btn-sm"><i class="bi bi-telephone me-1"></i> Call Tenant</a>
            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
  `;

  $('#tenantDetailsModal').remove();
  $('body').append(detailsHtml);
  $('#tenantDetailsModal').modal('show');
};

// Remove / Move Out Tenant Row with animation
window.removeTenantRow = function (btn, name) {
  if (confirm(`Are you sure you want to mark ${name} as moved out?`)) {
    const $row = $(btn).closest('tr');
    $row.css('background-color', '#fee2e2');
    $row.fadeOut(400, function () {
      $(this).remove();
      const $badge = $('#tenantCountBadge');
      if ($badge.length) {
        const val = parseInt($badge.text() || 1);
        if (val > 0) $badge.text(val - 1);
      }
      showToast('Tenant Record Updated', `${name} was moved to past records.`, 'warning');
    });
  }
};

// Photo Gallery Lightbox Viewer Modal
window.openPhotoLightbox = function (src, title, category, desc) {
  const modalHtml = `
    <div class="modal fade" id="ebhPhotoLightboxModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content border-0 shadow-lg bg-navy text-white overflow-hidden">
          <div class="modal-header border-0 pb-0 d-flex justify-content-between align-items-center">
            <div>
              <span class="badge badge-info text-dark px-2 py-1 mb-1 font-weight-bold">${category || 'Property Tour'}</span>
              <h5 class="modal-title fw-bold text-white mb-0">${title || 'Eanne Boarding House Photo'}</h5>
            </div>
            <button type="button" class="close text-white border-0 bg-transparent p-2" data-dismiss="modal" aria-label="Close" style="font-size: 1.5rem; opacity: 0.9;">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body p-3 text-center">
            <div style="max-height: 70vh; overflow: hidden; border-radius: 8px; background: #0b1338;">
              <img src="${src}" alt="${title}" class="img-fluid" style="max-height: 70vh; width: auto; object-fit: contain;">
            </div>
            ${desc ? `<p class="text-white-50 small mt-3 mb-0">${desc}</p>` : ''}
          </div>
          <div class="modal-footer border-0 pt-0 bg-navy justify-content-between">
            <small class="text-white-50"><i class="bi bi-geo-alt-fill text-info me-1"></i> Soldiers Village, Brgy. Sta. Lucia, Pasig City</small>
            <div class="d-flex gap-2">
              <a href="../pages/contact.html" class="btn btn-ebh-accent btn-sm fw-bold"><i class="bi bi-calendar-event me-1"></i> Schedule a Visit</a>
              <button type="button" class="btn btn-outline-light btn-sm" data-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  `;

  $('#ebhPhotoLightboxModal').remove();
  $('body').append(modalHtml);
  $('#ebhPhotoLightboxModal').modal('show');
};

// Room Details Modal for Accommodations Page
window.openRoomDetailsModal = function (title, rate, type, featuresArray, imageSrc, desc) {
  const featuresList = (featuresArray || []).map(f => `<li class="mb-2"><i class="bi bi-check2-circle text-success me-2"></i>${f}</li>`).join('');
  const modalHtml = `
    <div class="modal fade" id="roomDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content border-0 shadow-lg overflow-hidden">
          <div class="modal-header bg-navy text-white">
            <div>
              <span class="badge badge-info text-dark px-2 py-1 mb-1 font-weight-bold">${type}</span>
              <h5 class="modal-title fw-bold text-white mb-0">${title}</h5>
            </div>
            <button type="button" class="close text-white border-0 bg-transparent" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body p-0">
            ${imageSrc ? `<div style="height: 280px; overflow: hidden; background: #e2e8f0;"><img src="${imageSrc}" alt="${title}" style="width: 100%; height: 100%; object-fit: cover;"></div>` : ''}
            <div class="p-4">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="h3 fw-bold text-navy mb-0">${rate} <span class="fs-6 fw-normal text-muted">/ month</span></div>
                <span class="badge bg-success text-white border px-2 py-1"><i class="bi bi-check-circle me-1"></i> Utilities Included</span>
              </div>
              <p class="text-muted small mb-4">${desc}</p>
              <h6 class="fw-bold text-navy mb-3"><i class="bi bi-stars text-primary me-2"></i>Inclusions & Amenities</h6>
              <ul class="list-unstyled small text-muted mb-0">
                ${featuresList}
              </ul>
            </div>
          </div>
          <div class="modal-footer bg-light justify-content-between">
            <small class="text-muted"><i class="bi bi-shield-check text-success me-1"></i> Inclusive of Water & Electricity</small>
            <div class="d-flex gap-2">
              <a href="../index.html#contact" class="btn btn-ebh-primary btn-sm" onclick="$('#roomDetailModal').modal('hide');"><i class="bi bi-calendar2-check me-1"></i> Inquire for This Room</a>
              <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  `;
  $('#roomDetailModal').remove();
  $('body').append(modalHtml);
  $('#roomDetailModal').modal('show');
};

// Real Financial Report CSV Export
window.exportReportsCSV = function () {
  const csvRows = [
    ['Eanne Boarding House - Monthly Financial Report & Operating Ledger'],
    ['Billing Period: October 2026', 'Location: Soldiers Village, Pasig City'],
    [],
    ['Reference #', 'Date', 'Tenant Name', 'Room Assigned', 'Payment Method', 'Amount (PHP)', 'Status'],
    ['REF-992813', '2026-10-02', 'Maria Clara Santos', 'Room 102 (Solo)', 'GCash', '5000.00', 'Pending Verification'],
    ['BPI-441029', '2026-10-02', 'Juan Dela Cruz', 'Room 201 (Bed A)', 'BPI Online', '3500.00', 'Pending Verification'],
    ['MYA-812049', '2026-10-01', 'Patricia Reyes', 'Room 104 (Solo)', 'Maya QR', '5000.00', 'Pending Verification'],
    ['GC-881290', '2026-10-01', 'Carlos Dominguez', 'Room 101 (Solo)', 'GCash', '5000.00', 'Verified Paid'],
    ['BDO-992019', '2026-09-30', 'Gabriel Reyes', 'Room 202 (Bed A)', 'BDO Deposit', '3500.00', 'Verified Paid'],
    ['CSH-1002', '2026-09-30', 'Andres Bonifacio', 'Room 204 (Solo)', 'Cash', '5000.00', 'Verified Paid'],
    ['GC-771829', '2026-09-28', 'Beatriz Alonzo', 'Room 202 (Bed B)', 'GCash', '3500.00', 'Verified Paid'],
    [],
    ['Gross Collections Target', '75000.00'],
    ['Current Verified & Queued Revenue', '68500.00'],
    ['Collection Efficiency', '91.3%'],
    ['Operating Utilities (Water/Power/WiFi)', '14200.00'],
    ['Net Operating Flow', '54300.00']
  ];

  const csvContent = 'data:text/csv;charset=utf-8,' + csvRows.map(e => e.map(cell => `"${cell}"`).join(',')).join('\n');
  const encodedUri = encodeURI(csvContent);
  const link = document.createElement('a');
  link.setAttribute('href', encodedUri);
  link.setAttribute('download', 'eanne_boarding_house_financial_report_oct2026.csv');
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);

  showToast('Report Exported', 'eanne_boarding_house_financial_report_oct2026.csv has been downloaded.', 'success');
};

// Clipboard Copy Helper
window.copyToClipboard = function (text, label) {
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text).then(function () {
      showToast('Copied to Clipboard', `${label || 'Item'}: ${text}`, 'success');
    }).catch(function () {
      fallbackCopy(text, label);
    });
  } else {
    fallbackCopy(text, label);
  }

  function fallbackCopy(val, lbl) {
    const tempInput = document.createElement('input');
    tempInput.value = val;
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand('copy');
    document.body.removeChild(tempInput);
    showToast('Copied to Clipboard', `${lbl || 'Item'}: ${val}`, 'success');
  }
};

