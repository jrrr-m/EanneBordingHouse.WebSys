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

  // 8. Sign In Form Handler (MySQL & PHP Integrated with Shake Animation)
  const $loginForm = $('#ebhLoginForm');
  if ($loginForm.length) {
    $loginForm.on('submit', function (e) {
      e.preventDefault();
      const user = ($('#loginUsername').val() || '').trim();
      const pass = ($('#loginPassword').val() || '').trim();
      const $card = $loginForm.closest('.ebh-card');
      const $btn = $('#signInSubmitBtn');
      const origText = $btn.html();

      $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Authenticating...');

      $.ajax({
        url: window.getEbhApiUrl('api_auth.php'),
        type: 'POST',
        data: { username: user, password: pass },
        dataType: 'json',
        success: function (res) {
          if (res.success && res.user) {
            showToast('Login Successful', `Welcome back, ${res.user.full_name}!`, 'success');
            setTimeout(function () {
              if (res.user.role === 'admin') {
                window.location.href = 'admin-dashboard.html';
              } else {
                window.location.href = 'tenant-dashboard.html';
              }
            }, 600);
          } else {
            $btn.prop('disabled', false).html(origText);
            // Shaking animation for incorrect credentials
            $card.animate({ marginLeft: '-15px' }, 60)
                 .animate({ marginLeft: '15px' }, 60)
                 .animate({ marginLeft: '-10px' }, 60)
                 .animate({ marginLeft: '10px' }, 60)
                 .animate({ marginLeft: '0px' }, 60);

            showToast('Access Denied', res.error || 'Invalid credentials. Check your username/password.', 'danger');
          }
        },
        error: function (xhr, status, err) {
          $btn.prop('disabled', false).html(origText);
          showToast('Connection Error', 'Could not reach authentication server: ' + err, 'danger');
        }
      });
    });
  }

  // 9. Tenant Proof of Payment Upload Form Handling (MySQL & PHP Integrated)
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
      const $form = $(this);
      const $submitBtn = $form.find('button[type="submit"]');
      const origText = $submitBtn.html();

      const fileInput = document.getElementById('paymentReceiptFile');
      if (!fileInput || !fileInput.files || !fileInput.files.length) {
        showToast('Proof Required', 'Please attach a photo or screenshot of your payment receipt before submitting.', 'warning');
        return;
      }

      $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2" role="status"></span> Uploading to database...');
      $('#uploadProgressBarContainer').slideDown(150);
      $('#uploadProgressBar').css('width', '35%');

      const formData = new FormData($form[0]);
      // Ensure backup fields
      if (!formData.get('tenant_name')) formData.append('tenant_name', $('#payTenantName').val() || 'Maria Clara Santos');
      if (!formData.get('unit_code')) formData.append('unit_code', $('#payUnitCode').val() || 'BH-02');
      if (!formData.get('payment_method')) formData.append('payment_method', $('#payMethodSelect').val() || 'GCash');
      if (!formData.get('amount')) formData.append('amount', $('#payAmount').val() || '3500');
      if (!formData.get('reference_no')) formData.append('reference_no', $('#payRefNumber').val() || '');

      $.ajax({
        url: window.getEbhApiUrl('api_upload_payment.php'),
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (res) {
          $('#uploadProgressBar').css('width', '100%');
          setTimeout(function () {
            $submitBtn.prop('disabled', false).html(origText);
            $('#uploadProgressBarContainer').slideUp(200);
            $('#uploadPreviewContainer').slideUp(200);

            if (res.success && res.payment) {
              const p = res.payment;
              const dateStr = p.payment_date_short || new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
              const proofPath = p.proof_image ? p.proof_image : '';

              // Prepend newly uploaded payment to Tenant Payment History Table
              const newRow = `
                <tr class="table-warning" id="payment-row-${p.id}">
                  <td class="fw-bold text-navy">${p.reference_no}</td>
                  <td>${dateStr}</td>
                  <td><span class="badge badge-light border"><i class="bi bi-phone me-1 text-primary"></i> ${p.payment_method}</span></td>
                  <td class="fw-bold text-navy">₱${parseFloat(p.amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                  <td class="status-col"><span class="badge-ebh badge-pending"><i class="bi bi-clock-history"></i> Pending Verification</span></td>
                  <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="previewReceiptModal('${p.reference_no}', '${p.tenant_name}', '${p.unit_code}', '${p.amount}', '${dateStr}', '${p.payment_method}', '${p.reference_no}', '${proofPath}', 'pending', ${p.id})">
                      <i class="bi bi-eye"></i> View Proof
                    </button>
                  </td>
                </tr>
              `;

              const $tbody = $('#tenantHistoryTable tbody');
              if ($tbody.length) {
                $(newRow).hide().prependTo($tbody).fadeIn(400);
              }

              $form[0].reset();
              $('#tenantUploadModal').modal('hide');
              window.syncPendingQueueBadges();
              showToast('Payment Proof Submitted', `Receipt #${p.reference_no} uploaded to MySQL database. Landlord notified for verification.`, 'success');
            } else {
              showToast('Upload Failed', res.error || 'Could not save payment to database.', 'danger');
            }
          }, 350);
        },
        error: function (xhr, status, err) {
          $('#uploadProgressBar').css('width', '100%');
          $submitBtn.prop('disabled', false).html(origText);
          $('#uploadProgressBarContainer').slideUp(200);
          showToast('Connection Error', 'Could not reach PHP API. Make sure Apache and MySQL are running. (' + err + ')', 'danger');
        }
      });
    });
  }

  // 10. Tenant Maintenance Ticket Submission (MySQL Integrated)
  const $maintenanceForm = $('#tenantMaintenanceForm');
  if ($maintenanceForm.length) {
    $maintenanceForm.on('submit', function (e) {
      e.preventDefault();
      const category = $('#ticketCategory').val() || 'General';
      const urgency = $('#ticketUrgency').val() || 'Normal';
      const description = $('#ticketDescription').val() || 'Assistance requested.';
      const $submitBtn = $(this).find('button[type="submit"]');
      const origText = $submitBtn.html();

      $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Submitting...');

      $.ajax({
        url: window.getEbhApiUrl('api_tickets.php'),
        type: 'POST',
        data: {
          action: 'create',
          tenant_name: 'Maria Clara Santos',
          unit_code: 'BH-02',
          category: category,
          urgency: urgency,
          description: description
        },
        dataType: 'json',
        success: function (res) {
          $submitBtn.prop('disabled', false).html(origText);
          if (res.success) {
            $maintenanceForm[0].reset();
            showToast('Ticket Submitted', `Maintenance Ticket #${res.ticket.ticket_no} logged into MySQL. Caretaker notified.`, 'success');
            if (typeof window.loadTenantTickets === 'function') {
              window.loadTenantTickets('Maria Clara Santos');
            }
          } else {
            showToast('Submission Failed', res.error || 'Could not log ticket.', 'danger');
          }
        },
        error: function (xhr, status, err) {
          $submitBtn.prop('disabled', false).html(origText);
          showToast('Connection Error', 'Could not reach tickets API: ' + err, 'danger');
        }
      });
    });
  }

  // 11. Add New Tenant Modal Form (MySQL Integrated)
  const $addTenantForm = $('#addTenantForm');
  if ($addTenantForm.length) {
    $addTenantForm.on('submit', function (e) {
      e.preventDefault();
      const name = $('#newTenantName').val();
      const contact = $('#newTenantContact').val();
      const rawRoom = $('#newTenantRoom').val() || 'BH-04';
      const cleanRoom = rawRoom.replace('Unit ', '').trim();
      const rent = $('#newTenantRent').val();
      const moveIn = $('#newTenantMoveIn').val() || new Date().toISOString().split('T')[0];
      const $submitBtn = $(this).find('button[type="submit"]');
      const origText = $submitBtn.html();

      $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving to MySQL...');

      $.ajax({
        url: window.getEbhApiUrl('api_tenants.php'),
        type: 'POST',
        data: {
          action: 'add_tenant',
          full_name: name,
          phone: contact,
          unit_code: cleanRoom,
          monthly_rent: rent,
          move_in_date: moveIn
        },
        dataType: 'json',
        success: function (res) {
          $submitBtn.prop('disabled', false).html(origText);
          if (res.success) {
            $addTenantForm[0].reset();
            $('#addTenantModal').modal('hide');
            showToast('Tenant Registered', `${name} saved to MySQL and assigned to Unit ${cleanRoom}.`, 'success');
            
            // Reload all dependent views dynamically
            if (typeof window.loadTenantsList === 'function') window.loadTenantsList();
            if (typeof window.loadRoomsGrid === 'function') window.loadRoomsGrid();
            if (typeof window.loadGlobalStats === 'function') window.loadGlobalStats();
            if (typeof window.syncPendingQueueBadges === 'function') window.syncPendingQueueBadges();
          } else {
            showToast('Save Failed', res.error || 'Could not register tenant.', 'danger');
          }
        },
        error: function (xhr, status, err) {
          $submitBtn.prop('disabled', false).html(origText);
          showToast('Connection Error', 'Could not save tenant to MySQL: ' + err, 'danger');
        }
      });
    });
  }

  // 12. Add Room Inventory Modal Form (MySQL Integrated)
  const $addRoomForm = $('#addRoomForm');
  if ($addRoomForm.length) {
    $addRoomForm.on('submit', function (e) {
      e.preventDefault();
      const rawRoomNum = $('#newRoomNumber').val();
      const cleanUnitCode = rawRoomNum.replace('Unit ', '').trim();
      const type = $('#newRoomType').val();
      const rate = $('#newRoomRate').val();
      const capacity = $('#newRoomCapacity').val() || 3;
      const $submitBtn = $(this).find('button[type="submit"]');
      const origText = $submitBtn.html();

      $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Adding to MySQL...');

      $.ajax({
        url: window.getEbhApiUrl('api_rooms.php'),
        type: 'POST',
        data: {
          action: 'add_room',
          unit_code: cleanUnitCode,
          room_type: type,
          monthly_rate: rate,
          max_pax: capacity,
          inclusions: (type.indexOf('Private') !== -1)
            ? 'Entire standalone private space/house, no shared common areas. Bills excluded.'
            : 'Individual room, kitchen area, shared comfort room. Max 3 pax limit. Bills excluded.'
        },
        dataType: 'json',
        success: function (res) {
          $submitBtn.prop('disabled', false).html(origText);
          if (res.success) {
            $addRoomForm[0].reset();
            $('#addRoomModal').modal('hide');
            showToast('Room Created', `Unit ${cleanUnitCode} added to MySQL inventory.`, 'success');

            if (typeof window.loadRoomsGrid === 'function') window.loadRoomsGrid();
            if (typeof window.loadGlobalStats === 'function') window.loadGlobalStats();
          } else {
            showToast('Save Failed', res.error || 'Could not add room.', 'danger');
          }
        },
        error: function (xhr, status, err) {
          $submitBtn.prop('disabled', false).html(origText);
          showToast('Connection Error', 'Could not add room to MySQL: ' + err, 'danger');
        }
      });
    });
  }

  // 12a. Edit Tenant Modal Form (User Maintenance - Edit Function)
  const $editTenantForm = $('#editTenantForm');
  if ($editTenantForm.length) {
    $editTenantForm.on('submit', function (e) {
      e.preventDefault();
      const id = $('#editTenantId').val();
      const name = $('#editTenantName').val();
      const contact = $('#editTenantContact').val();
      const email = $('#editTenantEmail').val();
      const moveIn = $('#editTenantMoveIn').val();
      const rawRoom = $('#editTenantRoom').val() || '';
      const cleanRoom = rawRoom.replace('Unit ', '').trim();
      const rent = $('#editTenantRent').val();
      const status = $('#editTenantStatus').val() || 'active';
      const $submitBtn = $(this).find('button[type="submit"]');
      const origText = $submitBtn.html();

      $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Updating in MySQL...');

      $.ajax({
        url: window.getEbhApiUrl('api_tenants.php'),
        type: 'POST',
        data: {
          action: 'edit_tenant',
          id: id,
          full_name: name,
          phone: contact,
          email: email,
          unit_code: cleanRoom,
          monthly_rent: rent,
          move_in_date: moveIn,
          lease_status: status
        },
        dataType: 'json',
        success: function (res) {
          $submitBtn.prop('disabled', false).html(origText);
          if (res.success) {
            $('#editTenantModal').modal('hide');
            showToast('Tenant Updated', `${name}'s account was successfully updated in MySQL.`, 'success');

            if (typeof window.loadTenantsList === 'function') window.loadTenantsList();
            if (typeof window.loadRoomsGrid === 'function') window.loadRoomsGrid();
            if (typeof window.loadGlobalStats === 'function') window.loadGlobalStats();
          } else {
            showToast('Update Failed', res.error || 'Could not update tenant.', 'danger');
          }
        },
        error: function (xhr, status, err) {
          $submitBtn.prop('disabled', false).html(origText);
          showToast('Connection Error', 'Could not update tenant: ' + err, 'danger');
        }
      });
    });
  }

  // 12b. Edit Room Modal Form (File Maintenance - Edit Function)
  const $editRoomForm = $('#editRoomForm');
  if ($editRoomForm.length) {
    $editRoomForm.on('submit', function (e) {
      e.preventDefault();
      const id = $('#editRoomId').val();
      const rawRoomNum = $('#editRoomNumber').val();
      const cleanUnitCode = rawRoomNum.replace('Unit ', '').trim();
      const type = $('#editRoomType').val();
      const rate = $('#editRoomRate').val();
      const capacity = $('#editRoomCapacity').val() || 3;
      const status = $('#editRoomStatus').val() || 'available';
      const inclusions = $('#editRoomInclusions').val();
      const $submitBtn = $(this).find('button[type="submit"]');
      const origText = $submitBtn.html();

      $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Updating in MySQL...');

      $.ajax({
        url: window.getEbhApiUrl('api_rooms.php'),
        type: 'POST',
        data: {
          action: 'edit_room',
          id: id,
          unit_code: cleanUnitCode,
          room_type: type,
          monthly_rate: rate,
          max_pax: capacity,
          status: status,
          inclusions: inclusions
        },
        dataType: 'json',
        success: function (res) {
          $submitBtn.prop('disabled', false).html(origText);
          if (res.success) {
            $('#editRoomModal').modal('hide');
            showToast('Room Updated', `Unit ${cleanUnitCode} updated in MySQL inventory.`, 'success');

            if (typeof window.loadRoomsGrid === 'function') window.loadRoomsGrid();
            if (typeof window.loadGlobalStats === 'function') window.loadGlobalStats();
          } else {
            showToast('Update Failed', res.error || 'Could not update room.', 'danger');
          }
        },
        error: function (xhr, status, err) {
          $submitBtn.prop('disabled', false).html(origText);
          showToast('Connection Error', 'Could not update room: ' + err, 'danger');
        }
      });
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

  // 14. Record Walk-in Cash Payment Modal Form (MySQL Integrated)
  const $recordCashForm = $('#recordCashForm');
  if ($recordCashForm.length) {
    $recordCashForm.on('submit', function (e) {
      e.preventDefault();
      const $btn = $(this).find('button[type="submit"]');
      const origText = $btn.html();
      $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Recording in MySQL...');

      const postData = {
        tenant_name: $('#cashTenantSelect').val(),
        amount: $('#cashAmountInput').val(),
        notes: $('#cashNotesInput').val() || 'Cash received over-the-counter'
      };

      $.ajax({
        url: window.getEbhApiUrl('api_record_cash.php'),
        type: 'POST',
        data: postData,
        dataType: 'json',
        success: function (res) {
          $btn.prop('disabled', false).html(origText);
          if (res.success && res.payment) {
            $('#recordCashModal').modal('hide');
            $recordCashForm[0].reset();
            showToast('Cash Payment Logged', `Receipt #${res.payment.reference_no} recorded into MySQL as Verified & Cleared.`, 'success');
            if (typeof window.loadPaymentsList === 'function') {
              window.loadPaymentsList();
            }
          } else {
            showToast('Save Failed', res.error || 'Could not record cash payment.', 'danger');
          }
        },
        error: function (xhr, status, err) {
          $btn.prop('disabled', false).html(origText);
          showToast('Error', 'Could not reach server: ' + err, 'danger');
        }
      });
    });
  }

  // Admin Ticket Creation Modal Form
  const $adminTicketForm = $('#adminCreateTicketForm');
  if ($adminTicketForm.length) {
    $('#adminTicketTenant').on('change', function () {
      const u = $(this).find('option:selected').data('unit') || '';
      $('#adminTicketUnit').val(u);
    });

    $adminTicketForm.on('submit', function (e) {
      e.preventDefault();
      const $btn = $(this).find('button[type="submit"]');
      const orig = $btn.html();
      $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Submitting...');

      $.ajax({
        url: window.getEbhApiUrl('api_tickets.php'),
        type: 'POST',
        data: {
          action: 'create',
          tenant_name: $('#adminTicketTenant').val(),
          unit_code: $('#adminTicketUnit').val(),
          category: $('#adminTicketCategory').val(),
          urgency: $('#adminTicketUrgency').val(),
          description: $('#adminTicketDesc').val()
        },
        dataType: 'json',
        success: function (res) {
          $btn.prop('disabled', false).html(orig);
          if (res.success) {
            $('#createTicketModal').modal('hide');
            $adminTicketForm[0].reset();
            showToast('Ticket Created', `Work order #${res.ticket.ticket_no} logged into MySQL.`, 'success');
            if (typeof window.loadAdminTicketsList === 'function') window.loadAdminTicketsList();
            window.syncTicketsQueueBadges();
          } else {
            showToast('Error', res.error || 'Failed to create ticket', 'danger');
          }
        },
        error: function (xhr, status, err) {
          $btn.prop('disabled', false).html(orig);
          showToast('Error', 'Connection failed: ' + err, 'danger');
        }
      });
    });
  }

  // Admin Tickets Page Controls: Filter Pills & Search
  $('#ticketFilterPills button').on('click', function () {
    $('#ticketFilterPills button').removeClass('active');
    $(this).addClass('active');
    if (typeof window.renderAdminTicketsTable === 'function') {
      window.renderAdminTicketsTable();
    }
  });

  $('#ticketSearchInput, #ticketCategoryFilter').on('input change', function () {
    if (typeof window.renderAdminTicketsTable === 'function') {
      window.renderAdminTicketsTable();
    }
  });

  // Always sync sidebar and header pending queue badges with live MySQL database on every page
  window.syncPendingQueueBadges();
  window.syncTicketsQueueBadges();

  // Auto-load live dynamic data from MySQL database on page load
  if ($('.kpi-card').length || $('#adminPendingTable').length) {
    window.loadGlobalStats();
  }
  if ($('#adminTicketsTable').length) {
    window.loadAdminTicketsList();
  }
  if ($('#heroSection').length || $('#publicAvailRoomsCount').length || $('#bhAvailabilityBadge').length || $('#indexBhAvailBadge').length) {
    window.syncPublicAvailability();
  }
  if ($('#roomsGridContainer').length) {
    window.loadRoomsGrid();
  }
  if ($('#tenantsTable').length) {
    window.loadTenantsList();
  }
  if ($('#paymentsTable').length) {
    window.loadPaymentsList();
  }
  if ($('#tenantStatementRent').length || $('#tenantHistoryTable').length) {
    window.loadTenantDashboard();
  }
  if ($('#adminPendingTable').length) {
    window.loadAdminPendingQueue();
  }
  if ($('#reportsTable').length) {
    window.loadReportsLedger();
  }

  // Periodic 10-second polling to keep all statistics, tables, and queues live across tabs
  setInterval(function () {
    window.syncPendingQueueBadges();
    window.syncTicketsQueueBadges();
    if ($('.kpi-card').length || $('#adminPendingTable').length) {
      window.loadGlobalStats();
    }
    if ($('#heroSection').length || $('#publicAvailRoomsCount').length || $('#bhAvailabilityBadge').length || $('#indexBhAvailBadge').length) {
      window.syncPublicAvailability();
    }
  }, 10000);
});

// URL Resolution Helpers for API and Uploads
window.getEbhApiUrl = function (endpoint) {
  const isPages = window.location.pathname.indexOf('/pages/') !== -1;
  return (isPages ? '../backend/' : 'backend/') + endpoint;
};

window.getEbhUploadUrl = function (filePath) {
  if (!filePath) return '';
  if (filePath.indexOf('http://') === 0 || filePath.indexOf('https://') === 0) return filePath;
  const isPages = window.location.pathname.indexOf('/pages/') !== -1;
  return (isPages ? '../' : '') + filePath;
};

// Global Real-time Pending Queue Badges Synchronizer
window.syncPendingQueueBadges = function (count) {
  function applyCount(cnt) {
    const num = Math.max(0, parseInt(cnt) || 0);
    $('.sidebar-queue-badge, .pending-queue-badge, #sidebarQueueBadge, #pendingFilterBadge, #pendingQueueBadge, #queueBadgeCount').each(function () {
      $(this).text(num);
      if ($(this).hasClass('badge')) {
        if (num === 0) {
          $(this).removeClass('badge-warning text-dark').addClass('badge-success text-white');
        } else {
          $(this).removeClass('badge-success text-white').addClass('badge-warning text-dark');
        }
      }
    });
  }

  if (typeof count !== 'undefined' && count !== null) {
    applyCount(count);
  } else {
    $.ajax({
      url: window.getEbhApiUrl('api_get_payments.php?status=pending'),
      type: 'GET',
      dataType: 'json',
      success: function (res) {
        if (res && res.counts) {
          applyCount(res.counts.pending);
          if ($('#totalPaymentsCount').length) $('#totalPaymentsCount').text(res.counts.total);
          if ($('#verifiedCountBadge').length) $('#verifiedCountBadge').text(res.counts.verified);
        }
      }
    });
  }
};

// Global Real-time Tickets Queue Badges Synchronizer
window.syncTicketsQueueBadges = function (count) {
  function applyCount(cnt) {
    const num = Math.max(0, parseInt(cnt) || 0);
    $('.tickets-queue-badge, #sidebarTicketsBadge').each(function () {
      $(this).text(num);
      if ($(this).hasClass('badge')) {
        if (num === 0) {
          $(this).removeClass('badge-danger').addClass('badge-secondary').css('opacity', '0.65');
        } else {
          $(this).removeClass('badge-secondary').addClass('badge-danger').css('opacity', '1');
        }
      }
    });
  }

  if (typeof count !== 'undefined' && count !== null) {
    applyCount(count);
  } else {
    $.ajax({
      url: window.getEbhApiUrl('api_tickets.php'),
      type: 'GET',
      dataType: 'json',
      success: function (res) {
        if (res && res.counts) {
          applyCount(res.counts.open);
        }
      }
    });
  }
};

// Admin Payment Verification Action (Syncs live with MySQL)
window.processPayment = function (id, action, notes) {
  const isApprove = action === 'approved' || action === 'approve' || action === 'verify';
  const apiAction = isApprove ? 'approve' : 'reject';
  const $row = $('#payment-row-' + id);

  if ($row.length) {
    $row.find('.action-btn-group button').prop('disabled', true);
  }

  $.ajax({
    url: window.getEbhApiUrl('api_update_payment.php'),
    type: 'POST',
    data: {
      id: id,
      action: apiAction,
      notes: notes || '',
      reviewer: 'Landlord Admin'
    },
    dataType: 'json',
    success: function (res) {
      if (res.success && res.payment) {
        const p = res.payment;
        const proofPath = p.proof_image || '';

        if ($row.length) {
          $row.find('.action-btn-group').fadeOut(150, function () {
            if (isApprove) {
              $row.find('.status-col').html('<span class="badge-ebh badge-verified"><i class="bi bi-check-circle"></i> Verified & Cleared</span>');
              $row.removeClass('table-warning').attr('data-status', 'verified');
              $row.attr('data-category', function (i, val) { return (val || '').replace('pending', 'verified'); });

              // If row is in the Admin Dashboard Pending Approvals Queue, fade out and remove after approval
              if ($row.closest('#adminPendingTable').length) {
                $(this).html('<span class="small text-success fw-bold"><i class="bi bi-check2-circle"></i> Cleared</span>').fadeIn(150);
                setTimeout(function () {
                  $row.fadeOut(300, function () {
                    $(this).remove();
                    if (!$('#adminPendingTable tbody tr').length) {
                      $('#adminPendingTable tbody').html('<tr><td colspan="6" class="text-center py-4 text-muted"><i class="bi bi-check-circle-fill text-success fs-3 d-block mb-1"></i><strong>All caught up!</strong> No pending payments in queue.</td></tr>');
                    }
                  });
                }, 800);
              } else {
                // In main Payments table, show Receipt & Proof button
                const receiptBtnHtml = `
                  <button class="btn btn-sm btn-outline-secondary" onclick="previewReceiptModal('${p.reference_no}', '${p.tenant_name}', '${p.unit_code}', '${p.amount}', '${p.payment_date_short}', '${p.payment_method}', '${p.reference_no}', '${proofPath}', 'verified', ${p.id})">
                    <i class="bi bi-receipt me-1"></i> Receipt & Proof
                  </button>
                `;
                $(this).html(receiptBtnHtml).fadeIn(150);
              }
            } else {
              $row.find('.status-col').html('<span class="badge-ebh badge-rejected"><i class="bi bi-x-circle"></i> Rejected</span>');
              $row.removeClass('table-warning').attr('data-status', 'rejected');
              $row.attr('data-category', function (i, val) { return (val || '').replace('pending', 'rejected'); });
              $(this).html('<span class="small text-danger fw-semibold"><i class="bi bi-x"></i> Rejected</span>').fadeIn(150);
            }
          });
        }

        // Live update pending queue badge counts across sidebar, tabs, and KPI cards
        const pendingLeft = typeof res.pending_count !== 'undefined' ? res.pending_count : 0;
        window.syncPendingQueueBadges(pendingLeft);

        // Update verified counter badge if present
        const $verifiedBadge = $('#verifiedCountBadge');
        if ($verifiedBadge.length) {
          const currentVerified = parseInt($verifiedBadge.text() || 0);
          if (isApprove) {
            $verifiedBadge.text(currentVerified + 1);
          }
        }

        // If the user currently has the 'pending' filter tab active on payments.html, hide the newly verified row
        if ($('.btn-filter.active').attr('data-filter') === 'pending' && isApprove) {
          setTimeout(function () {
            $row.fadeOut(300);
          }, 600);
        }

        showToast(
          isApprove ? 'Verified & Cleared' : 'Payment Rejected',
          isApprove 
            ? `Payment #${p.reference_no} is now Verified & Cleared in MySQL.`
            : `Payment #${p.reference_no} marked as rejected.`,
          isApprove ? 'success' : 'warning'
        );
      } else {
        showToast('Update Failed', res.error || 'Could not update payment status.', 'danger');
        if ($row.length) $row.find('.action-btn-group button').prop('disabled', false);
      }
    },
    error: function (xhr, status, err) {
      if ($row.length) {
        if (isApprove) {
          $row.find('.status-col').html('<span class="badge-ebh badge-verified"><i class="bi bi-check-circle"></i> Verified & Cleared</span>');
          $row.removeClass('table-warning').attr('data-status', 'verified');
        } else {
          $row.find('.status-col').html('<span class="badge-ebh badge-rejected"><i class="bi bi-x-circle"></i> Rejected</span>');
          $row.removeClass('table-warning').attr('data-status', 'rejected');
        }
        $row.find('.action-btn-group').html(`<span class="small text-muted fw-semibold">${isApprove ? 'Verified & Cleared' : 'Rejected'}</span>`);
      }
      showToast(isApprove ? 'Verified & Cleared (Local)' : 'Rejected (Local)', `Action applied locally. (${err})`, isApprove ? 'success' : 'warning');
    }
  });
};

// Preview Official Receipt & Attached Proof of Payment Modal
window.previewReceiptModal = function (id, tenant, room, amount, date, method, ref, proofImage, status, dbId) {
  const tenantName = tenant || 'Maria Clara Santos';
  const roomName = room || 'Unit BH-02 (Boarding House)';
  const amountNum = parseFloat(String(amount).replace(/[^0-9.]/g, '')) || 3500;
  const amountStr = amountNum.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const dateStr = date || 'Oct 2, 2026';
  const methodStr = method || 'GCash Transfer';
  const refNum = ref || id || 'GC-992813';
  const currentStatus = (status || 'verified').toLowerCase();
  const paymentDbId = dbId || id;

  let proofPhotoHtml = '';
  if (proofImage && proofImage.trim() !== '') {
    const fullProofUrl = window.getEbhUploadUrl(proofImage);
    proofPhotoHtml = `
      <div class="card mb-3 border bg-light shadow-sm overflow-hidden">
        <div class="card-header bg-navy text-white d-flex justify-content-between align-items-center py-2 px-3">
          <span class="small fw-bold"><i class="bi bi-image me-1 text-info"></i> Uploaded Proof of Payment</span>
          <a href="${fullProofUrl}" target="_blank" class="btn btn-sm btn-outline-light py-0 px-2" style="font-size:0.75rem;"><i class="bi bi-box-arrow-up-right me-1"></i> Open Full Image</a>
        </div>
        <div class="card-body p-2 text-center bg-dark" style="border-radius: 0 0 4px 4px;">
          <a href="${fullProofUrl}" target="_blank" title="Click to view full photo">
            <img src="${fullProofUrl}" alt="Proof of Payment" class="img-fluid rounded" style="max-height: 260px; object-fit: contain;">
          </a>
        </div>
      </div>
    `;
  } else {
    proofPhotoHtml = `
      <div class="alert alert-light border small text-muted mb-3 d-flex align-items-center gap-2">
        <i class="bi bi-receipt-cutoff fs-3 text-secondary"></i>
        <div><strong>Digital / Walk-in Payment:</strong> Logged directly into the system. Official digital voucher generated below.</div>
      </div>
    `;
  }

  let statusBadge = '<span class="badge-ebh badge-verified"><i class="bi bi-check-circle"></i> Verified & Cleared</span>';
  let stampHtml = '<div class="receipt-stamp-paid" style="font-size:0.75rem; letter-spacing:1px;">VERIFIED & CLEARED</div>';
  if (currentStatus === 'pending') {
    statusBadge = '<span class="badge-ebh badge-pending"><i class="bi bi-clock-history"></i> Pending Verification</span>';
    stampHtml = '<div class="receipt-stamp-paid text-warning border-warning" style="color:#d97706 !important; border-color:#d97706 !important;">PENDING</div>';
  } else if (currentStatus === 'rejected') {
    statusBadge = '<span class="badge-ebh badge-rejected"><i class="bi bi-x-circle"></i> Rejected</span>';
    stampHtml = '<div class="receipt-stamp-paid text-danger border-danger" style="color:#dc2626 !important; border-color:#dc2626 !important;">REJECTED</div>';
  }

  let actionButtonsHtml = '';
  if (currentStatus === 'pending') {
    actionButtonsHtml = `
      <div class="p-3 bg-white border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="small text-muted"><i class="bi bi-shield-lock text-primary me-1"></i> Landlord Review Action:</div>
        <div class="btn-group btn-group-sm">
          <button type="button" class="btn btn-outline-danger" onclick="processPayment('${paymentDbId}', 'rejected'); $('#receiptPreviewModal').modal('hide');"><i class="bi bi-x-circle me-1"></i> Reject</button>
          <button type="button" class="btn btn-success" onclick="processPayment('${paymentDbId}', 'approved'); $('#receiptPreviewModal').modal('hide');"><i class="bi bi-check2-circle me-1"></i> Approve & Clear</button>
        </div>
      </div>
    `;
  }

  const modalHtml = `
    <div class="modal fade" id="receiptPreviewModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content border-0 shadow-lg">
          <div class="modal-header bg-navy text-white">
            <h6 class="modal-title fw-bold text-white mb-0"><i class="bi bi-receipt me-2"></i> Payment Verification & Proof Review</h6>
            <button type="button" class="close text-white border-0 bg-transparent" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body p-4 bg-light">
            ${proofPhotoHtml}

            <div class="receipt-paper">
              <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                  <h6 class="fw-bold text-navy mb-0">EANNE BOARDING HOUSE</h6>
                  <small class="text-muted">#275 105 St. Soldiers Village, Brgy. Sta. Lucia, Pasig City</small>
                </div>
                ${stampHtml}
              </div>
              <hr class="my-2">
              <div class="row g-2 mb-3 small">
                <div class="col-6"><span class="text-muted d-block">Reference / Receipt #:</span><strong class="text-navy">#${refNum}</strong></div>
                <div class="col-6 text-end"><span class="text-muted d-block">Payment Date:</span><strong>${dateStr}</strong></div>
                <div class="col-6"><span class="text-muted d-block">Tenant:</span><strong>${tenantName}</strong></div>
                <div class="col-6 text-end"><span class="text-muted d-block">Room / Unit:</span><strong>${roomName}</strong></div>
                <div class="col-6"><span class="text-muted d-block">Payment Method:</span><strong>${methodStr}</strong></div>
                <div class="col-6 text-end"><span class="text-muted d-block">Status:</span>${statusBadge}</div>
              </div>
              <div class="bg-light p-3 rounded mb-3 border">
                <div class="d-flex justify-content-between small mb-1">
                  <span>Monthly Unit Rent:</span>
                  <span>₱${amountStr}</span>
                </div>
                <div class="d-flex justify-content-between small text-muted mb-2">
                  <span>Electricity & Water Bills:</span>
                  <span>Excluded (Shouldered by tenant)</span>
                </div>
                <div class="d-flex justify-content-between fw-bold text-navy border-top pt-2">
                  <span>TOTAL AMOUNT PAID:</span>
                  <span class="fs-5 text-primary">₱${amountStr}</span>
                </div>
              </div>
              <div class="text-center small text-muted">
                <i class="bi bi-shield-check text-success me-1"></i> Recorded in Eanne Boarding House MySQL Database
              </div>
            </div>
          </div>
          ${actionButtonsHtml}
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

// Fetch and Populate All Payments Table on payments.html from MySQL
window.loadPaymentsList = function () {
  const $tbody = $('#paymentsTable tbody');
  if (!$tbody.length) return;

  $.ajax({
    url: window.getEbhApiUrl('api_get_payments.php'),
    type: 'GET',
    dataType: 'json',
    success: function (res) {
      if (res.success && res.payments && res.payments.length) {
        let html = '';
        res.payments.forEach(function (p) {
          const isPending = p.status === 'pending';
          const isVerified = p.status === 'verified';
          const isRejected = p.status === 'rejected';

          let statusBadge = '<span class="badge-ebh badge-verified"><i class="bi bi-check-circle"></i> Verified & Cleared</span>';
          if (isPending) {
            statusBadge = '<span class="badge-ebh badge-pending"><i class="bi bi-clock-history"></i> Pending Verification</span>';
          } else if (isRejected) {
            statusBadge = '<span class="badge-ebh badge-rejected"><i class="bi bi-x-circle"></i> Rejected</span>';
          }

          const initials = (p.tenant_name || 'TN').split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
          const proofPath = p.proof_image ? p.proof_image : '';

          let actionsHtml = '';
          if (isPending) {
            actionsHtml = `
              <div class="action-btn-group btn-group btn-group-sm">
                <button class="btn btn-outline-secondary" title="View Proof & Receipt" onclick="previewReceiptModal('${p.reference_no}', '${p.tenant_name}', '${p.unit_code}', '${p.amount}', '${p.payment_date_short}', '${p.payment_method}', '${p.reference_no}', '${proofPath}', '${p.status}', ${p.id})"><i class="bi bi-eye"></i></button>
                <button class="btn btn-success" title="Approve & Mark Verified" onclick="processPayment(${p.id}, 'approved')"><i class="bi bi-check-lg"></i> Approve</button>
                <button class="btn btn-outline-danger" title="Reject Payment" onclick="processPayment(${p.id}, 'rejected')"><i class="bi bi-x-lg"></i></button>
              </div>
            `;
          } else {
            actionsHtml = `
              <button class="btn btn-sm btn-outline-secondary" onclick="previewReceiptModal('${p.reference_no}', '${p.tenant_name}', '${p.unit_code}', '${p.amount}', '${p.payment_date_short}', '${p.payment_method}', '${p.reference_no}', '${proofPath}', '${p.status}', ${p.id})">
                <i class="bi bi-receipt me-1"></i> ${isVerified ? 'Receipt & Proof' : 'View Details'}
              </button>
            `;
          }

          html += `
            <tr class="filterable-item ${isPending ? 'table-warning' : ''}" data-category="${p.status} ${p.payment_method.toLowerCase()}" data-status="${p.status}" id="payment-row-${p.id}">
              <td class="fw-bold text-navy">${p.reference_no}</td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="avatar-circle sm">${initials}</div>
                  <div>
                    <span class="fw-bold text-navy d-block">${p.tenant_name}</span>
                    <small class="text-muted">Unit ${p.unit_code}</small>
                  </div>
                </div>
              </td>
              <td class="fw-bold text-navy">${p.amount_formatted}</td>
              <td><span class="badge badge-light border"><i class="bi bi-credit-card text-primary me-1"></i> ${p.payment_method}</span></td>
              <td><small class="text-muted">${p.payment_date}</small></td>
              <td class="status-col">${statusBadge}</td>
              <td class="text-end">${actionsHtml}</td>
            </tr>
          `;
        });

        $tbody.html(html);

        // Update counts dynamically across all badges
        if (res.counts) {
          window.syncPendingQueueBadges(res.counts.pending);
          $('#paymentCountBadge').text(res.count);
          $('#totalPaymentsCount').text(res.counts.total);
          $('#verifiedCountBadge').text(res.counts.verified);
        }
      }
    }
  });
};

// Fetch and Populate Tenant Payments on tenant-dashboard.html from MySQL
window.loadTenantPayments = function (tenantName) {
  const $tbody = $('#tenantHistoryTable tbody');
  if (!$tbody.length) return;

  const url = window.getEbhApiUrl('api_get_payments.php') + (tenantName ? '?tenant=' + encodeURIComponent(tenantName) : '');

  $.ajax({
    url: url,
    type: 'GET',
    dataType: 'json',
    success: function (res) {
      if (res.success && res.payments && res.payments.length) {
        let html = '';
        res.payments.forEach(function (p) {
          const isPending = p.status === 'pending';
          const isVerified = p.status === 'verified';
          let statusBadge = '<span class="badge-ebh badge-verified"><i class="bi bi-check-circle"></i> Verified & Cleared</span>';
          if (isPending) {
            statusBadge = '<span class="badge-ebh badge-pending"><i class="bi bi-clock-history"></i> Pending Verification</span>';
          } else if (p.status === 'rejected') {
            statusBadge = '<span class="badge-ebh badge-rejected"><i class="bi bi-x-circle"></i> Rejected</span>';
          }

          const proofPath = p.proof_image ? p.proof_image : '';

          html += `
            <tr class="${isPending ? 'table-warning' : ''}" id="payment-row-${p.id}">
              <td class="fw-bold text-navy">${p.reference_no}</td>
              <td>${p.payment_date_short}</td>
              <td><span class="badge badge-light border"><i class="bi bi-phone text-primary me-1"></i> ${p.payment_method}</span></td>
              <td class="fw-bold text-navy">${p.amount_formatted}</td>
              <td class="status-col">${statusBadge}</td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-secondary" onclick="previewReceiptModal('${p.reference_no}', '${p.tenant_name}', '${p.unit_code}', '${p.amount}', '${p.payment_date_short}', '${p.payment_method}', '${p.reference_no}', '${proofPath}', '${p.status}', ${p.id})">
                  <i class="bi ${isVerified ? 'bi-receipt' : 'bi-eye'} me-1"></i> ${isVerified ? 'Receipt & Proof' : 'View Proof'}
                </button>
              </td>
            </tr>
          `;
        });
        $tbody.html(html);
      }
    }
  });
};

// Fetch and Populate Pending Queue Table on admin-dashboard.html from MySQL
window.loadAdminPendingQueue = function () {
  const $tbody = $('#adminPendingTable tbody');
  if (!$tbody.length) return;

  $.ajax({
    url: window.getEbhApiUrl('api_get_payments.php?status=pending'),
    type: 'GET',
    dataType: 'json',
    success: function (res) {
      if (res.counts) {
        window.syncPendingQueueBadges(res.counts.pending);
      }

      if (res.success && res.payments && res.payments.length) {
        let html = '';
        res.payments.forEach(function (p) {
          const initials = (p.tenant_name || 'TN').split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
          const proofPath = p.proof_image ? p.proof_image : '';

          html += `
            <tr id="payment-row-${p.id}">
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="avatar-circle sm">${initials}</div>
                  <div>
                    <span class="fw-bold text-navy d-block">${p.tenant_name}</span>
                    <small class="text-muted">Unit ${p.unit_code}</small>
                  </div>
                </div>
              </td>
              <td class="fw-bold text-navy">${p.amount_formatted}</td>
              <td>
                <span class="badge badge-light border"><i class="bi bi-credit-card text-primary me-1"></i> ${p.payment_method}</span>
                <small class="d-block text-muted">${p.reference_no}</small>
              </td>
              <td><small class="text-muted">${p.payment_date_short}</small></td>
              <td class="status-col"><span class="badge-ebh badge-pending"><i class="bi bi-clock-history"></i> Pending</span></td>
              <td class="text-end">
                <div class="action-btn-group btn-group btn-group-sm">
                  <button class="btn btn-outline-secondary" title="View Proof & Receipt" onclick="previewReceiptModal('${p.reference_no}', '${p.tenant_name}', '${p.unit_code}', '${p.amount}', '${p.payment_date_short}', '${p.payment_method}', '${p.reference_no}', '${proofPath}', '${p.status}', ${p.id})"><i class="bi bi-eye"></i></button>
                  <button class="btn btn-success" title="Approve & Clear" onclick="processPayment(${p.id}, 'approved')"><i class="bi bi-check-lg"></i> Approve</button>
                  <button class="btn btn-outline-danger" title="Reject Payment" onclick="processPayment(${p.id}, 'rejected')"><i class="bi bi-x-lg"></i></button>
                </div>
              </td>
            </tr>
          `;
        });
        $tbody.html(html);
      } else {
        $tbody.html('<tr><td colspan="6" class="text-center py-4 text-muted"><i class="bi bi-check-circle-fill text-success fs-3 d-block mb-1"></i><strong>All caught up!</strong> No pending payments in queue.</td></tr>');
      }
    }
  });
};

// Fetch and Populate Reports Ledger on reports.html from MySQL
window.loadReportsLedger = function () {
  const $tbody = $('#reportsTable tbody');
  if (!$tbody.length) return;

  $.ajax({
    url: window.getEbhApiUrl('api_get_payments.php'),
    type: 'GET',
    dataType: 'json',
    success: function (res) {
      if (res.success && res.payments && res.payments.length) {
        let html = '';
        res.payments.forEach(function (p) {
          const isVerified = p.status === 'verified';
          const isPending = p.status === 'pending';
          let statusBadge = '<span class="badge-ebh badge-verified"><i class="bi bi-check-circle me-1"></i> Verified & Cleared</span>';
          if (isPending) {
            statusBadge = '<span class="badge-ebh badge-pending"><i class="bi bi-clock-history me-1"></i> Pending Verification</span>';
          } else if (p.status === 'rejected') {
            statusBadge = '<span class="badge-ebh badge-rejected"><i class="bi bi-x-circle me-1"></i> Rejected</span>';
          }

          html += `
            <tr class="${isPending ? 'table-warning' : ''}">
              <td class="fw-bold text-navy">${p.reference_no}</td>
              <td>${p.payment_date}</td>
              <td>${p.tenant_name}</td>
              <td>Unit ${p.unit_code}</td>
              <td><span class="badge badge-light border">${p.payment_method}</span></td>
              <td class="fw-bold text-navy">${p.amount_formatted}</td>
              <td>${statusBadge}</td>
            </tr>
          `;
        });
        $tbody.html(html);

        if ($('#reportsCountBadge').length) {
          $('#reportsCountBadge').text(res.count);
        }
      }
    }
  });
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

// Remove / Move Out Tenant Row (MySQL Integrated)
window.removeTenantRow = function (btn, id, name) {
  const tenantName = name || 'this tenant';
  if (confirm(`Are you sure you want to mark ${tenantName} as moved out? This will update their lease status to inactive and free up their assigned room in MySQL.`)) {
    const $row = $(btn).closest('tr');
    $row.find('button').prop('disabled', true);

    $.ajax({
      url: window.getEbhApiUrl('api_tenants.php'),
      type: 'POST',
      data: {
        action: 'archive_tenant',
        id: id || 0,
        full_name: tenantName
      },
      dataType: 'json',
      success: function (res) {
        if (res.success) {
          $row.css('background-color', '#fee2e2');
          $row.fadeOut(350, function () {
            $(this).remove();
          });
          showToast('Tenant Record Updated', `${tenantName} marked as moved out. Unit ${res.unit_code || ''} is now available in MySQL.`, 'warning');
          if (typeof window.loadTenantsList === 'function') window.loadTenantsList();
          if (typeof window.loadRoomsGrid === 'function') window.loadRoomsGrid();
          if (typeof window.loadGlobalStats === 'function') window.loadGlobalStats();
        } else {
          $row.find('button').prop('disabled', false);
          showToast('Update Failed', res.error || 'Could not archive tenant.', 'danger');
        }
      },
      error: function (xhr, status, err) {
        $row.find('button').prop('disabled', false);
        showToast('Connection Error', 'Could not reach server: ' + err, 'danger');
      }
    });
  }
};

// Open Edit Tenant Modal (User Maintenance - Edit Function)
window.openEditTenantModal = function (id, name, phone, email, unitCode, rent, moveIn, status) {
  $('#editTenantId').val(id);
  $('#editTenantName').val(name);
  $('#editTenantContact').val(phone);
  $('#editTenantEmail').val(email || '');
  $('#editTenantMoveIn').val(moveIn || '');
  $('#editTenantRoom').val((unitCode || '').replace('Unit ', '').trim());
  $('#editTenantRent').val(rent || 3500);
  $('#editTenantStatus').val(status || 'active');
  $('#editTenantModal').modal('show');
};

// Permanently Delete Tenant Record (User Maintenance - Delete Function)
window.deleteTenantRow = function (btn, id, name) {
  const tenantName = name || 'this tenant';
  if (confirm(`Are you sure you want to PERMANENTLY DELETE ${tenantName} from user accounts?\n\nThis will remove their resident account permanently from MySQL.`)) {
    const $row = $(btn).closest('tr');
    $row.find('button').prop('disabled', true);

    $.ajax({
      url: window.getEbhApiUrl('api_tenants.php'),
      type: 'POST',
      data: {
        action: 'delete_tenant',
        id: id || 0
      },
      dataType: 'json',
      success: function (res) {
        if (res.success) {
          $row.css('background-color', '#fee2e2');
          $row.fadeOut(350, function () {
            $(this).remove();
          });
          showToast('Tenant Deleted', res.message, 'warning');
          if (typeof window.loadTenantsList === 'function') window.loadTenantsList();
          if (typeof window.loadRoomsGrid === 'function') window.loadRoomsGrid();
          if (typeof window.loadGlobalStats === 'function') window.loadGlobalStats();
        } else {
          $row.find('button').prop('disabled', false);
          showToast('Delete Failed', res.error || 'Could not delete tenant.', 'danger');
        }
      },
      error: function (xhr, status, err) {
        $row.find('button').prop('disabled', false);
        showToast('Connection Error', 'Could not reach server: ' + err, 'danger');
      }
    });
  }
};

// Open Edit Room Modal (File Maintenance - Edit Function)
window.openEditRoomModal = function (id, unitCode, roomType, rate, maxPax, inclusions, status) {
  $('#editRoomId').val(id);
  $('#editRoomNumber').val(unitCode);
  $('#editRoomType').val(roomType);
  $('#editRoomRate').val(rate);
  $('#editRoomCapacity').val(maxPax);
  $('#editRoomStatus').val(status || 'available');
  try {
    $('#editRoomInclusions').val(decodeURIComponent(inclusions));
  } catch (e) {
    $('#editRoomInclusions').val(inclusions || '');
  }
  $('#editRoomModal').modal('show');
};

// Permanently Delete Room Unit (File Maintenance - Delete Function)
window.deleteRoom = function (id, unitCode) {
  if (confirm(`Are you sure you want to PERMANENTLY DELETE Unit ${unitCode} from rooms inventory?\n\nThis unit will be deleted from MySQL.`)) {
    $.ajax({
      url: window.getEbhApiUrl('api_rooms.php'),
      type: 'POST',
      data: {
        action: 'delete_room',
        id: id
      },
      dataType: 'json',
      success: function (res) {
        if (res.success) {
          showToast('Unit Deleted', res.message, 'warning');
          if (typeof window.loadRoomsGrid === 'function') window.loadRoomsGrid();
          if (typeof window.loadGlobalStats === 'function') window.loadGlobalStats();
        } else {
          showToast('Delete Failed', res.error || 'Could not delete room.', 'danger');
        }
      },
      error: function (xhr, status, err) {
        showToast('Connection Error', 'Could not reach server: ' + err, 'danger');
      }
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
                <span class="badge bg-warning text-dark border px-2 py-1"><i class="bi bi-lightning-charge me-1"></i> Bills Excluded (Tenant)</span>
              </div>
              <p class="text-muted small mb-4">${desc}</p>
              <h6 class="fw-bold text-navy mb-3"><i class="bi bi-stars text-primary me-2"></i>Inclusions & Amenities</h6>
              <ul class="list-unstyled small text-muted mb-0">
                ${featuresList}
              </ul>
            </div>
          </div>
          <div class="modal-footer bg-light justify-content-between">
            <small class="text-muted"><i class="bi bi-shield-check text-success me-1"></i> Gated Compound in Soldiers Village</small>
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

// ====================================================================
// LIVE DATABASE LOADERS: Real-Time Synchronization with MySQL
// ====================================================================

// 1. Fetch & Populate Global KPI Statistics across Dashboard, Rooms, and Reports
window.loadGlobalStats = function () {
  $.ajax({
    url: window.getEbhApiUrl('api_get_stats.php'),
    type: 'GET',
    dataType: 'json',
    success: function (res) {
      if (!res || !res.success) return;
      const kpi = res.kpi;
      const b = res.breakdown;

      // Dashboard KPI Cards
      if ($('#kpiActiveTenants').length) $('#kpiActiveTenants').text(kpi.active_tenants);
      if ($('#kpiActiveTenantsSub').length) {
        $('#kpiActiveTenantsSub').html(`<i class="bi bi-arrow-up-short"></i> ${kpi.occupancy_rate_percent}% Capacity (${kpi.occupied_units}/${kpi.total_units} Units)`);
      }
      if ($('#kpiMonthlyTarget').length) $('#kpiMonthlyTarget').text(kpi.monthly_target_revenue_formatted);
      if ($('#queueBadgeCount').length) $('#queueBadgeCount').text(kpi.pending_queue_count);
      if ($('#kpiPendingSub').length) {
        $('#kpiPendingSub').html(`<i class="bi bi-clock-history me-1"></i> ${kpi.pending_queue_count} Receipts in queue`);
      }
      if ($('#kpiAvailableUnits').length) $('#kpiAvailableUnits').text(kpi.available_units);
      if ($('#kpiAvailableUnitsSub').length) {
        const availText = `${b.boarding_house.available} BH Room${b.boarding_house.available === 1 ? '' : 's'}, ${b.private_house.available} Private House${b.private_house.available === 1 ? '' : 's'}`;
        $('#kpiAvailableUnitsSub').html(`<i class="bi bi-check2-circle me-1"></i> ${availText}`);
      }

      // Category Occupancy Overview on Admin Dashboard
      if ($('#overviewBhTitle').length) $('#overviewBhTitle').text(`Boarding House Rental Units (${b.boarding_house.total} Units Total - ₱3,500/mo)`);
      if ($('#overviewBhSub').length) $('#overviewBhSub').text(`${b.boarding_house.occupied} of ${b.boarding_house.total} Occupied (${b.boarding_house.percent}%)`);
      if ($('#overviewBhProgress').length) {
        $('#overviewBhProgress').css('width', b.boarding_house.percent + '%').attr('aria-valuenow', b.boarding_house.percent);
      }

      if ($('#overviewPbTitle').length) $('#overviewPbTitle').text(`Private Boarding Houses (${b.private_house.total} Units Total - ₱8,000/mo)`);
      if ($('#overviewPbSub').length) $('#overviewPbSub').text(`${b.private_house.occupied} of ${b.private_house.total} Occupied (${b.private_house.percent}%)`);
      if ($('#overviewPbProgress').length) {
        $('#overviewPbProgress').css('width', b.private_house.percent + '%').attr('aria-valuenow', b.private_house.percent);
      }

      // Recent Activity Feed
      if ($('#recentActivityFeed').length && res.recent_activity && res.recent_activity.length) {
        let actHtml = '';
        res.recent_activity.forEach(function (act) {
          actHtml += `
            <div class="d-flex gap-2">
              <div class="avatar-circle sm bg-light border ${act.icon_color}"><i class="bi ${act.icon}"></i></div>
              <div>
                <strong class="text-navy">${act.title}:</strong> ${act.description}
                <div class="text-muted" style="font-size:0.75rem;">${act.timestamp}</div>
              </div>
            </div>
          `;
        });
        $('#recentActivityFeed').html(actHtml);
      }

      // Rooms Page Stat Cards & Filter Counts
      if ($('#roomStatTotal').length) $('#roomStatTotal').text(kpi.total_units);
      if ($('#roomStatOccupied').length) $('#roomStatOccupied').text(kpi.occupied_units);
      if ($('#roomStatOccupiedSub').length) $('#roomStatOccupiedSub').text(`${kpi.occupancy_rate_percent}% Occupancy (${kpi.occupied_units}/${kpi.total_units})`);
      if ($('#roomStatAvailable').length) $('#roomStatAvailable').text(kpi.available_units);
      if ($('#roomStatAvailableSub').length) $('#roomStatAvailableSub').text(`${kpi.available_units} Unit${kpi.available_units === 1 ? '' : 's'} Vacant`);
      if ($('#roomStatTarget').length) $('#roomStatTarget').text(kpi.monthly_target_revenue_formatted);

      if ($('#filterBtnAllRooms').length) $('#filterBtnAllRooms').text(`All Units (${kpi.total_units})`);
      if ($('#filterBtnBhRooms').length) $('#filterBtnBhRooms').text(`Boarding House (${b.boarding_house.total})`);
      if ($('#filterBtnPbRooms').length) $('#filterBtnPbRooms').text(`Private House (${b.private_house.total})`);
      if ($('#filterBtnAvailRooms').length) $('#filterBtnAvailRooms').html(`<i class="bi bi-sparkles text-success"></i> Available Only (${kpi.available_units})`);

      // Tenants Page Filter Counts & Badge
      if ($('#tenantCountBadge').length) $('#tenantCountBadge').text(kpi.active_tenants);
      if ($('#filterBtnAllTenants').length) $('#filterBtnAllTenants').text(`All Tenants (${kpi.total_tenants})`);
      if ($('#filterBtnActiveTenants').length) $('#filterBtnActiveTenants').text(`Active (${kpi.active_tenants})`);
      if ($('#filterBtnBhTenants').length) $('#filterBtnBhTenants').text(`Boarding House (${b.boarding_house.occupied})`);
      if ($('#filterBtnPbTenants').length) $('#filterBtnPbTenants').text(`Private House (${b.private_house.occupied})`);

      // Reports Page Financial Summary Cards
      if ($('#reportMonthlyCapacity').length) $('#reportMonthlyCapacity').text(kpi.monthly_target_revenue_formatted);
      if ($('#reportVerifiedTotal').length) $('#reportVerifiedTotal').text(kpi.total_verified_amount_formatted);
      if ($('#reportCompoundUnitsSub').length) {
        $('#reportCompoundUnitsSub').html(`<i class="bi bi-house-check"></i> ${kpi.occupied_units} Occupied &bull; ${kpi.available_units} Avail`);
      }

      // Live Tickets Queue Badge Synchronization
      if (typeof kpi.open_tickets !== 'undefined') {
        window.syncTicketsQueueBadges(kpi.open_tickets);
      }

      // Public Website Live Availability Synchronization
      if (typeof window.applyPublicAvailabilityData === 'function') {
        window.applyPublicAvailabilityData(kpi, b);
      }
    }
  });
};

// 2. Fetch & Render Rooms Inventory Grid from MySQL
window.loadRoomsGrid = function () {
  const $container = $('#roomsGridContainer');
  if (!$container.length) return;

  $.ajax({
    url: window.getEbhApiUrl('api_rooms.php'),
    type: 'GET',
    dataType: 'json',
    success: function (res) {
      if (!res || !res.success || !res.rooms) return;

      let html = '';
      let bhBadgesHtml = '';
      let pbBadgesHtml = '';

      res.rooms.forEach(function (r) {
        const isOccupied = r.status === 'occupied';
        const isAvailable = r.status === 'available';
        const isPrivate = r.category === 'private-house';

        let statusBadge = '<span class="badge-ebh badge-paid"><i class="bi bi-lock-fill"></i> Occupied</span>';
        if (isAvailable) {
          statusBadge = '<span class="badge-ebh badge-verified"><i class="bi bi-door-open"></i> Available</span>';
        } else if (r.status === 'maintenance') {
          statusBadge = '<span class="badge-ebh badge-pending"><i class="bi bi-wrench"></i> Maintenance</span>';
        }

        let residentHtml = '';
        if (isOccupied && r.current_tenant) {
          const initials = r.current_tenant.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
          residentHtml = `
            <div class="mb-3">
              <div class="small text-muted mb-1">Current Resident:</div>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar-circle sm">${initials}</div>
                <div>
                  <span class="fw-bold text-navy small d-block">${r.current_tenant}</span>
                  <small class="text-muted" style="font-size:0.75rem;">${r.tenant_phone || 'Active Lease'}${r.move_in_date ? ' &bull; Moved ' + r.move_in_date : ''}</small>
                </div>
              </div>
            </div>
          `;
        } else {
          residentHtml = `
            <div class="mb-3">
              <div class="small text-muted mb-1">Current Status:</div>
              <span class="badge bg-light text-success border py-1 px-2 small"><i class="bi bi-check-circle me-1"></i> Ready for immediate occupancy</span>
            </div>
          `;
        }

        const roomCategoryPill = isPrivate ? 'Private House' : 'Boarding House';
        const actionHtml = isOccupied
          ? `<button class="btn btn-sm btn-outline-secondary w-100" onclick="window.updateRoomStatus(${r.id}, 'available')"><i class="bi bi-arrow-repeat me-1"></i> Mark as Vacant</button>`
          : `<button class="btn btn-sm btn-ebh-primary w-100" data-toggle="modal" data-target="#addTenantModal"><i class="bi bi-person-plus me-1"></i> Assign Tenant</button>`;

        html += `
          <div class="col-md-6 col-lg-4 filterable-item" data-category="${r.category} ${r.status}" data-status="${r.status}">
            <div class="room-card h-100">
              <div class="room-card-header">
                <div class="d-flex align-items-center">
                  <span class="room-status-dot ${r.status}"></span>
                  <strong class="text-navy">Unit ${r.unit_code}</strong>
                  <span class="badge badge-light border ms-2 small">${roomCategoryPill}</span>
                </div>
                ${statusBadge}
              </div>
              <div class="ebh-card-body d-flex flex-column flex-grow-1">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span class="text-muted small">${r.room_type}</span>
                  <span class="fw-bold text-navy fs-5">${r.monthly_rate_formatted}<small class="text-muted fw-normal">/mo</small></span>
                </div>
                <p class="small text-muted mb-2">${r.inclusions}</p>
                ${residentHtml}
                <div class="mt-auto pt-3 border-top">
                  <div class="d-flex flex-column gap-2">
                    ${actionHtml}
                    <div class="btn-group btn-group-sm w-100">
                      <button type="button" class="btn btn-outline-primary" title="Edit Unit Specifications" onclick="window.openEditRoomModal(${r.id}, '${r.unit_code}', '${r.room_type}', ${r.monthly_rate}, ${r.max_pax}, '${encodeURIComponent(r.inclusions || '')}', '${r.status}')"><i class="bi bi-pencil-square me-1"></i> Edit Unit</button>
                      <button type="button" class="btn btn-outline-danger" title="Permanently Delete Unit" onclick="window.deleteRoom(${r.id}, '${r.unit_code}')"><i class="bi bi-trash3 me-1"></i> Delete</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        `;

        // Overview badges for dashboard
        const miniBadge = isOccupied
          ? `<span class="badge-ebh badge-paid small"><i class="bi bi-door-closed"></i> Unit ${r.unit_code} (${(r.current_tenant || 'Occupied').split(' ')[0]})</span>`
          : `<span class="badge-ebh badge-verified small"><i class="bi bi-door-open"></i> Unit ${r.unit_code} (Vacant - ${r.monthly_rate_formatted})</span>`;

        if (isPrivate) {
          pbBadgesHtml += miniBadge + ' ';
        } else {
          bhBadgesHtml += miniBadge + ' ';
        }
      });

      $container.html(html);

      // Also update badges on admin dashboard if present
      if ($('#overviewBhBadges').length && bhBadgesHtml) $('#overviewBhBadges').html(bhBadgesHtml);
      if ($('#overviewPbBadges').length && pbBadgesHtml) $('#overviewPbBadges').html(pbBadgesHtml);
    }
  });
};

// 3. Quick Room Status Toggle in MySQL
window.updateRoomStatus = function (roomId, newStatus) {
  if (confirm(`Change this room status to ${newStatus} in MySQL?`)) {
    $.ajax({
      url: window.getEbhApiUrl('api_rooms.php'),
      type: 'POST',
      data: {
        action: 'update_status',
        id: roomId,
        status: newStatus
      },
      dataType: 'json',
      success: function (res) {
        if (res.success) {
          showToast('Room Status Updated', res.message, 'success');
          window.loadRoomsGrid();
          window.loadGlobalStats();
        } else {
          showToast('Update Failed', res.error || 'Could not update status.', 'danger');
        }
      },
      error: function (xhr, status, err) {
        showToast('Error', 'Could not reach server: ' + err, 'danger');
      }
    });
  }
};

// 4. Fetch & Render Tenants Roster from MySQL
window.loadTenantsList = function () {
  const $tbody = $('#tenantsTable tbody');
  if (!$tbody.length) return;

  $.ajax({
    url: window.getEbhApiUrl('api_tenants.php'),
    type: 'GET',
    dataType: 'json',
    success: function (res) {
      if (!res || !res.success || !res.tenants) return;

      let html = '';
      res.tenants.forEach(function (t) {
        const initials = t.full_name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
        const isAct = t.is_active;
        const statusBadge = isAct
          ? '<span class="badge-ebh badge-verified"><i class="bi bi-check-circle"></i> Active</span>'
          : '<span class="badge-ebh badge-rejected"><i class="bi bi-x-circle"></i> Past Resident</span>';

        html += `
          <tr class="filterable-item" data-category="${t.category}" data-status="${t.lease_status}">
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar-circle sm">${initials}</div>
                <div>
                  <span class="fw-bold text-navy d-block">${t.full_name}</span>
                  <small class="text-muted"><i class="bi bi-telephone me-1"></i>${t.phone}</small>
                </div>
              </div>
            </td>
            <td><span class="fw-semibold text-navy">Unit ${t.unit_code}</span> <small class="text-muted d-block">${t.room_type}</small></td>
            <td><span class="badge badge-light border">${t.classification}</span></td>
            <td class="fw-bold text-navy">${t.monthly_rent_formatted}</td>
            <td><small class="text-muted">${t.move_in_date}</small></td>
            <td>${statusBadge}</td>
            <td class="text-end">
              <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-primary" title="View Profile" onclick="showTenantDetails('${(t.full_name || '').replace(/'/g, "\\'")}', 'Unit ${t.unit_code}', '${(t.room_type || '').replace(/'/g, "\\'")}', '${t.monthly_rent_formatted}', '${(t.phone || '').replace(/'/g, "\\'")}', '${t.move_in_date}')"><i class="bi bi-eye"></i></button>
                <button type="button" class="btn btn-outline-warning" title="Edit Tenant Record" onclick="window.openEditTenantModal(${t.id}, '${(t.full_name || '').replace(/'/g, "\\'")}', '${(t.phone || '').replace(/'/g, "\\'")}', '${(t.email || '').replace(/'/g, "\\'")}', '${t.unit_code}', ${t.monthly_rent}, '${t.move_in_date_raw || ''}', '${t.lease_status}')"><i class="bi bi-pencil-square"></i></button>
                <button type="button" class="btn btn-outline-secondary" title="Mark Move Out / Inactive" onclick="removeTenantRow(this, ${t.id}, '${(t.full_name || '').replace(/'/g, "\\'")}')"><i class="bi bi-box-arrow-right"></i></button>
                <button type="button" class="btn btn-outline-danger" title="Permanently Delete Tenant Account" onclick="window.deleteTenantRow(this, ${t.id}, '${(t.full_name || '').replace(/'/g, "\\'")}')"><i class="bi bi-trash3"></i></button>
              </div>
            </td>
          </tr>
        `;
      });

      $tbody.html(html);

      if ($('#tenantCountBadge').length) {
        $('#tenantCountBadge').text(res.counts ? res.counts.active : res.count);
      }
    }
  });
};

// 5. Fetch & Populate Tenant Dashboard Profile and Statement from MySQL
window.loadTenantDashboard = function (tenantName) {
  const currentTenant = tenantName || 'Maria Clara Santos';

  $.ajax({
    url: window.getEbhApiUrl('api_tenant_profile.php?name=' + encodeURIComponent(currentTenant)),
    type: 'GET',
    dataType: 'json',
    success: function (res) {
      if (!res || !res.success || !res.profile) return;
      const p = res.profile;

      if ($('#tenantStatementMonth').length) $('#tenantStatementMonth').text(p.statement_month);
      if ($('#tenantStatementRent').length) $('#tenantStatementRent').text(p.monthly_rent_formatted);
      if ($('#tenantStatementDueDate').length) {
        $('#tenantStatementDueDate').html(`<i class="bi bi-calendar-event me-1 text-primary"></i> ${p.due_date}`);
      }
      if ($('#tenantStatementBadge').length) {
        $('#tenantStatementBadge')
          .attr('class', 'badge-ebh ' + p.badge_class)
          .html(`<i class="bi bi-check-circle"></i> ${p.payment_status_text}`);
      }
    }
  });

  // Also load tenant payment history ledger & maintenance tickets from MySQL
  if (typeof window.loadTenantPayments === 'function') {
    window.loadTenantPayments(currentTenant);
  }
  if (typeof window.loadTenantTickets === 'function') {
    window.loadTenantTickets(currentTenant);
  }
};

// 6. Fetch & Populate Tenant Maintenance Tickets from MySQL
window.loadTenantTickets = function (tenantName) {
  const $list = $('#tenantTicketList');
  if (!$list.length) return;

  const url = window.getEbhApiUrl('api_tickets.php') + (tenantName ? '?tenant=' + encodeURIComponent(tenantName) : '');

  $.ajax({
    url: url,
    type: 'GET',
    dataType: 'json',
    success: function (res) {
      if (!res || !res.success || !res.tickets) return;

      if (!res.tickets.length) {
        $list.html('<div class="p-3 text-center text-muted small"><i class="bi bi-check-circle text-success fs-4 d-block mb-1"></i>No active maintenance issues reported.</div>');
        return;
      }

      let html = '';
      res.tickets.forEach(function (t) {
        const isResolved = t.status === 'resolved';
        const badgeHtml = isResolved
          ? '<span class="badge-ebh badge-verified"><i class="bi bi-check-circle"></i> Resolved</span>'
          : '<span class="badge-ebh badge-pending"><i class="bi bi-clock"></i> Queued</span>';

        html += `
          <div class="ticket-item ${t.is_urgent ? 'urgent' : ''}">
            <div class="d-flex align-items-center justify-content-between mb-1">
              <span class="fw-bold small text-navy">${t.category} Issue (#${t.ticket_no})</span>
              ${badgeHtml}
            </div>
            <p class="small text-muted mb-2">${t.description}</p>
            <div class="d-flex align-items-center justify-content-between text-muted" style="font-size:0.75rem;">
              <span><i class="bi bi-calendar3 me-1"></i> Logged ${t.created_at_short}</span>
              <span class="${t.is_urgent ? 'text-danger fw-bold' : ''}"><i class="bi bi-flag-fill me-1"></i> ${t.urgency} Priority</span>
            </div>
          </div>
        `;
      });

      $list.html(html);
    }
  });
};

// 7. Dynamic Financial Report CSV Export from Live MySQL Records
window.exportReportsCSV = function () {
  $.ajax({
    url: window.getEbhApiUrl('api_get_payments.php'),
    type: 'GET',
    dataType: 'json',
    success: function (res) {
      const payments = (res && res.payments) ? res.payments : [];
      const counts = res.counts || {};

      const csvRows = [
        ['Eanne Boarding House - Monthly Financial Report & Operating Ledger'],
        ['Generated At: ' + new Date().toLocaleString(), 'Location: Soldiers Village, Pasig City'],
        ['Total Verified Revenue: ' + (counts.total_verified_amount_formatted || '₱0.00'), 'Pending Queue: ' + (counts.pending || 0)],
        [],
        ['Reference #', 'Date Paid', 'Tenant Name', 'Unit Code', 'Payment Method', 'Amount (PHP)', 'Status', 'Verified By']
      ];

      payments.forEach(function (p) {
        csvRows.push([
          p.reference_no,
          p.payment_date,
          p.tenant_name,
          p.unit_code,
          p.payment_method,
          p.amount,
          p.status.toUpperCase(),
          p.verified_by || 'Awaiting Check'
        ]);
      });

      csvRows.push([]);
      csvRows.push(['Total Transactions', counts.total || payments.length]);
      csvRows.push(['Verified Receipts', counts.verified || 0]);
      csvRows.push(['Pending Verification', counts.pending || 0]);

      const csvContent = 'data:text/csv;charset=utf-8,' + csvRows.map(e => e.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(',')).join('\n');
      const encodedUri = encodeURI(csvContent);
      const link = document.createElement('a');
      link.setAttribute('href', encodedUri);
      link.setAttribute('download', `eanne_boarding_house_financial_report_${new Date().toISOString().slice(0, 10)}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);

      showToast('Report Exported', 'Live financial report downloaded successfully from MySQL.', 'success');
    },
    error: function () {
      showToast('Export Error', 'Could not fetch payments for export.', 'danger');
    }
  });
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

// ====================================================================
// PUBLIC WEBSITE AVAILABILITY SYNCHRONIZATION
// ====================================================================

window.applyPublicAvailabilityData = function (kpi, b) {
  if (!kpi || !b) return;

  // 1. Total available units pill / counter
  const availCount = parseInt(kpi.available_units) || 0;
  if ($('#heroAvailRoomsText').length) {
    if (availCount > 0) {
      $('#heroAvailRoomsText').text(`${availCount} Unit${availCount === 1 ? '' : 's'} Available Left`);
      $('#heroAvailBadge').removeClass('d-none');
    } else {
      $('#heroAvailRoomsText').text('Currently Fully Booked (0 Units Left)');
      $('#heroAvailBadge').removeClass('d-none');
    }
  }

  if ($('#publicAvailRoomsCount').length) {
    $('#publicAvailRoomsCount').text(`${availCount} Left`);
  }
  if ($('#publicAvailRoomsSub').length) {
    $('#publicAvailRoomsSub').text(availCount > 0 ? 'Vacant Now' : 'Join Waitlist');
  }

  // 2. Boarding House Room Availability (4 Units total)
  const bhAvail = (b.boarding_house && typeof b.boarding_house.available !== 'undefined') ? parseInt(b.boarding_house.available) : 0;
  const bhTotal = (b.boarding_house && typeof b.boarding_house.total !== 'undefined') ? parseInt(b.boarding_house.total) : 4;
  if ($('#indexBhAvailBadge').length) {
    if (bhAvail > 0) {
      $('#indexBhAvailBadge').removeClass('bg-danger').addClass('bg-success text-white').html(`<i class="bi bi-check-circle-fill me-1"></i> ${bhAvail} of ${bhTotal} Available`);
    } else {
      $('#indexBhAvailBadge').removeClass('bg-success').addClass('bg-danger text-white').html(`<i class="bi bi-slash-circle me-1"></i> Fully Booked (0/${bhTotal} Avail)`);
    }
  }
  if ($('#bhAvailabilityBadge').length) {
    if (bhAvail > 0) {
      $('#bhAvailabilityBadge').removeClass('bg-danger').addClass('bg-success text-white').html(`<i class="bi bi-check-circle-fill me-1"></i> ${bhAvail} of ${bhTotal} Available`);
    } else {
      $('#bhAvailabilityBadge').removeClass('bg-success').addClass('bg-danger text-white').html(`<i class="bi bi-slash-circle me-1"></i> Fully Booked (0/${bhTotal} Avail)`);
    }
  }
  if ($('#indexBhNotice').length) {
    if (bhAvail > 0) {
      $('#indexBhNotice').removeClass('text-muted').addClass('text-success').html(`<i class="bi bi-check-circle-fill text-success me-1"></i> ${bhAvail} individual boarding room(s) ready for move-in!`);
    } else {
      $('#indexBhNotice').removeClass('text-success').addClass('text-muted').html(`<i class="bi bi-clock-history text-warning me-1"></i> All ${bhTotal} rooms currently occupied. Waitlist open.`);
    }
  }
  if ($('#bhAvailNotice').length) {
    if (bhAvail > 0) {
      $('#bhAvailNotice').removeClass('alert-warning').addClass('alert-success').html(`<i class="bi bi-check-circle-fill fs-5 text-success"></i> <span>${bhAvail} of ${bhTotal} individual boarding room(s) available for immediate move-in!</span>`);
    } else {
      $('#bhAvailNotice').removeClass('alert-success').addClass('alert-warning').html(`<i class="bi bi-clock-history fs-5 text-warning"></i> <span>All ${bhTotal} individual boarding rooms are currently occupied. Inquire to join the waitlist.</span>`);
    }
  }

  // 3. Private Boarding House Availability (2 Units total)
  const pbAvail = (b.private_house && typeof b.private_house.available !== 'undefined') ? parseInt(b.private_house.available) : 0;
  const pbTotal = (b.private_house && typeof b.private_house.total !== 'undefined') ? parseInt(b.private_house.total) : 2;
  if ($('#indexPbAvailBadge').length) {
    if (pbAvail > 0) {
      $('#indexPbAvailBadge').removeClass('bg-danger').addClass('bg-success text-white').html(`<i class="bi bi-check-circle-fill me-1"></i> ${pbAvail} of ${pbTotal} Available`);
    } else {
      $('#indexPbAvailBadge').removeClass('bg-success').addClass('bg-danger text-white').html(`<i class="bi bi-slash-circle me-1"></i> Fully Booked (0/${pbTotal} Avail)`);
    }
  }
  if ($('#pbAvailabilityBadge').length) {
    if (pbAvail > 0) {
      $('#pbAvailabilityBadge').removeClass('bg-danger').addClass('bg-success text-white').html(`<i class="bi bi-check-circle-fill me-1"></i> ${pbAvail} of ${pbTotal} Available`);
    } else {
      $('#pbAvailabilityBadge').removeClass('bg-success').addClass('bg-danger text-white').html(`<i class="bi bi-slash-circle me-1"></i> Fully Booked (0/${pbTotal} Avail)`);
    }
  }
  if ($('#indexPbNotice').length) {
    if (pbAvail > 0) {
      $('#indexPbNotice').removeClass('text-muted').addClass('text-success').html(`<i class="bi bi-check-circle-fill text-success me-1"></i> ${pbAvail} Private House ready for immediate family move-in!`);
    } else {
      $('#indexPbNotice').removeClass('text-success').addClass('text-muted').html(`<i class="bi bi-clock-history text-warning me-1"></i> Both private houses currently occupied. Inquire for openings.`);
    }
  }
  if ($('#pbAvailNotice').length) {
    if (pbAvail > 0) {
      $('#pbAvailNotice').removeClass('alert-warning').addClass('alert-success').html(`<i class="bi bi-check-circle-fill fs-5 text-success"></i> <span>${pbAvail} Standalone Private House is vacant and ready for immediate family move-in!</span>`);
    } else {
      $('#pbAvailNotice').removeClass('alert-success').addClass('alert-warning').html(`<i class="bi bi-clock-history fs-5 text-warning"></i> <span>Both Standalone Private Houses are currently occupied. Inquire for openings.</span>`);
    }
  }
};

window.syncPublicAvailability = function () {
  $.ajax({
    url: window.getEbhApiUrl('api_get_stats.php'),
    type: 'GET',
    dataType: 'json',
    success: function (res) {
      if (res && res.success) {
        window.applyPublicAvailabilityData(res.kpi, res.breakdown);
      }
    }
  });
};

// ====================================================================
// ADMIN MAINTENANCE & SERVICE TICKETS MANAGEMENT
// ====================================================================

window.loadAdminTicketsList = function () {
  const $tbody = $('#adminTicketsTbody');
  if (!$tbody.length) return;

  $.ajax({
    url: window.getEbhApiUrl('api_tickets.php'),
    type: 'GET',
    dataType: 'json',
    success: function (res) {
      if (!res || !res.success) {
        $tbody.html('<tr><td colspan="8" class="text-center py-4 text-danger">Failed to load tickets from MySQL database.</td></tr>');
        return;
      }

      window.ebhAdminTicketsCache = res.tickets || [];
      const counts = res.counts || { total: 0, open: 0, in_progress: 0, resolved: 0 };

      // Update KPI Cards
      $('#ticketStatTotal').text(counts.total);
      $('#ticketStatOpen').text(counts.open);
      $('#ticketStatInProgress').text(counts.in_progress);
      $('#ticketStatResolved').text(counts.resolved);

      // Update Filter Counts & Badges
      $('#pillCountAll').text(counts.total);
      $('#pillCountOpen').text(counts.open);
      $('#pillCountInProgress').text(counts.in_progress);
      $('#pillCountResolved').text(counts.resolved);
      $('#ticketsCountBadge').text(counts.total);

      // Sync badges across sidebars
      window.syncTicketsQueueBadges(counts.open);

      window.renderAdminTicketsTable();
    },
    error: function (xhr, status, err) {
      $tbody.html(`<tr><td colspan="8" class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle me-1"></i> Error loading tickets: ${err}</td></tr>`);
    }
  });
};

window.renderAdminTicketsTable = function () {
  const $tbody = $('#adminTicketsTbody');
  if (!$tbody.length) return;

  const tickets = window.ebhAdminTicketsCache || [];
  const search = ($('#ticketSearchInput').val() || '').toLowerCase().trim();
  const catFilter = $('#ticketCategoryFilter').val() || '';
  const statusFilter = $('#ticketFilterPills button.active').data('filter') || 'all';

  const filtered = tickets.filter(function (t) {
    if (statusFilter !== 'all' && t.status !== statusFilter) {
      return false;
    }
    if (catFilter && t.category !== catFilter) {
      return false;
    }
    if (search) {
      const matchNo = (t.ticket_no || '').toLowerCase().indexOf(search) !== -1;
      const matchTenant = (t.tenant_name || '').toLowerCase().indexOf(search) !== -1;
      const matchUnit = (t.unit_code || '').toLowerCase().indexOf(search) !== -1;
      const matchDesc = (t.description || '').toLowerCase().indexOf(search) !== -1;
      const matchCat = (t.category || '').toLowerCase().indexOf(search) !== -1;
      if (!matchNo && !matchTenant && !matchUnit && !matchDesc && !matchCat) return false;
    }
    return true;
  });

  if (!filtered.length) {
    $tbody.html('<tr><td colspan="8" class="text-center py-4 text-muted"><i class="bi bi-info-circle me-1"></i> No matching maintenance tickets found.</td></tr>');
    return;
  }

  function esc(s) {
    if (!s) return '';
    return $('<div>').text(s).html();
  }

  let rowsHtml = '';
  filtered.forEach(function (t) {
    let statusBadge = '';
    let actionButtons = '';

    if (t.status === 'open') {
      statusBadge = '<span class="badge-ebh badge-open"><i class="bi bi-clock"></i> Open</span>';
      actionButtons = `
        <button type="button" class="btn btn-outline-primary" title="Start Progress" onclick="window.updateTicketStatus(${t.id}, 'in_progress')"><i class="bi bi-play-fill me-1"></i> In Progress</button>
        <button type="button" class="btn btn-success" title="Mark Resolved" onclick="window.updateTicketStatus(${t.id}, 'resolved')"><i class="bi bi-check-lg"></i></button>
      `;
    } else if (t.status === 'in_progress') {
      statusBadge = '<span class="badge-ebh badge-in-progress"><i class="bi bi-gear-wide-connected"></i> In Progress</span>';
      actionButtons = `
        <button type="button" class="btn btn-success" title="Mark Resolved" onclick="window.updateTicketStatus(${t.id}, 'resolved')"><i class="bi bi-check-lg me-1"></i> Resolve</button>
      `;
    } else {
      statusBadge = '<span class="badge-ebh badge-verified"><i class="bi bi-check-circle-fill"></i> Resolved</span>';
      actionButtons = `
        <button type="button" class="btn btn-outline-warning" title="Reopen Ticket" onclick="window.updateTicketStatus(${t.id}, 'open')"><i class="bi bi-arrow-counterclockwise"></i> Reopen</button>
      `;
    }

    const urgencyBadge = t.is_urgent
      ? '<span class="badge-ebh badge-urgent"><i class="bi bi-lightning-fill"></i> Urgent</span>'
      : `<span class="badge-ebh ${t.urgency === 'Low' ? 'badge-partial' : 'badge-pending'}"><i class="bi bi-flag"></i> ${esc(t.urgency)}</span>`;

    rowsHtml += `
      <tr id="ticket-row-${t.id}">
        <td>
          <strong class="text-navy">#${esc(t.ticket_no)}</strong>
          ${t.is_urgent ? '<span class="badge badge-danger text-white ml-1" style="font-size:0.65rem;">URGENT</span>' : ''}
        </td>
        <td>
          <div class="fw-bold text-navy">${esc(t.tenant_name)}</div>
          <span class="badge bg-light text-dark border small">${esc(t.unit_code)}</span>
        </td>
        <td>
          <span class="badge bg-primary text-white">${esc(t.category)}</span>
        </td>
        <td style="max-width: 280px;">
          <div class="text-truncate text-muted small" title="${esc(t.description)}">${esc(t.description)}</div>
        </td>
        <td>${urgencyBadge}</td>
        <td>${statusBadge}</td>
        <td class="small text-muted">${esc(t.created_at_short)}</td>
        <td class="text-right">
          <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-secondary" title="View Full Details" onclick="window.previewTicketDetails(${t.id})"><i class="bi bi-eye"></i></button>
            ${actionButtons}
          </div>
        </td>
      </tr>
    `;
  });

  $tbody.html(rowsHtml);
};

window.updateTicketStatus = function (id, newStatus) {
  const $row = $('#ticket-row-' + id);
  if ($row.length) {
    $row.find('button').prop('disabled', true);
  }

  $.ajax({
    url: window.getEbhApiUrl('api_tickets.php'),
    type: 'POST',
    data: {
      action: 'update_status',
      id: id,
      status: newStatus
    },
    dataType: 'json',
    success: function (res) {
      if (res.success) {
        showToast('Ticket Updated', `Ticket status updated to ${newStatus.replace('_', ' ').toUpperCase()}.`, 'success');
        $('#viewTicketModal').modal('hide');
        window.loadAdminTicketsList();
        if (typeof window.loadGlobalStats === 'function') window.loadGlobalStats();
      } else {
        showToast('Update Failed', res.error || 'Could not update ticket.', 'danger');
        if ($row.length) $row.find('button').prop('disabled', false);
      }
    },
    error: function (xhr, status, err) {
      showToast('Error', 'Connection failed: ' + err, 'danger');
      if ($row.length) $row.find('button').prop('disabled', false);
    }
  });
};

window.previewTicketDetails = function (id) {
  const tickets = window.ebhAdminTicketsCache || [];
  const t = tickets.find(item => item.id == id);
  if (!t) return;

  let statusBadge = '';
  let actionBtnsHtml = '';

  if (t.status === 'open') {
    statusBadge = '<span class="badge-ebh badge-open"><i class="bi bi-clock"></i> Open & Pending</span>';
    actionBtnsHtml = `
      <button type="button" class="btn btn-primary btn-sm" onclick="window.updateTicketStatus(${t.id}, 'in_progress')"><i class="bi bi-play-circle me-1"></i> Start Progress</button>
      <button type="button" class="btn btn-success btn-sm" onclick="window.updateTicketStatus(${t.id}, 'resolved')"><i class="bi bi-check2-circle me-1"></i> Mark Resolved</button>
    `;
  } else if (t.status === 'in_progress') {
    statusBadge = '<span class="badge-ebh badge-in-progress"><i class="bi bi-gear-wide-connected"></i> In Progress</span>';
    actionBtnsHtml = `
      <button type="button" class="btn btn-success btn-sm" onclick="window.updateTicketStatus(${t.id}, 'resolved')"><i class="bi bi-check2-circle me-1"></i> Mark Resolved</button>
      <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.updateTicketStatus(${t.id}, 'open')"><i class="bi bi-arrow-counterclockwise me-1"></i> Reset to Open</button>
    `;
  } else {
    statusBadge = '<span class="badge-ebh badge-verified"><i class="bi bi-check-circle-fill"></i> Resolved & Closed</span>';
    actionBtnsHtml = `
      <button type="button" class="btn btn-outline-warning btn-sm" onclick="window.updateTicketStatus(${t.id}, 'open')"><i class="bi bi-arrow-counterclockwise me-1"></i> Reopen Ticket</button>
    `;
  }

  function esc(s) {
    if (!s) return '';
    return $('<div>').text(s).html();
  }

  const modalHtml = `
    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
      <div>
        <h5 class="fw-bold text-navy mb-0">Ticket #${esc(t.ticket_no)}</h5>
        <small class="text-muted"><i class="bi bi-calendar3 me-1"></i> Logged on ${esc(t.created_at)}</small>
      </div>
      <div>${statusBadge}</div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="small text-muted d-block mb-1">Tenant / Reporter</label>
        <strong class="text-navy">${esc(t.tenant_name)}</strong>
      </div>
      <div class="col-sm-6">
        <label class="small text-muted d-block mb-1">Unit Assignment</label>
        <span class="badge bg-light text-navy border font-weight-bold">${esc(t.unit_code)}</span>
      </div>
      <div class="col-sm-6">
        <label class="small text-muted d-block mb-1">Category</label>
        <span class="badge bg-primary text-white">${esc(t.category)}</span>
      </div>
      <div class="col-sm-6">
        <label class="small text-muted d-block mb-1">Urgency Priority</label>
        <span class="fw-bold ${t.is_urgent ? 'text-danger' : 'text-navy'}"><i class="bi bi-flag-fill me-1"></i> ${esc(t.urgency)}</span>
      </div>
    </div>

    <div class="p-3 bg-light rounded-3 border mb-3">
      <label class="small fw-bold text-navy d-block mb-1"><i class="bi bi-chat-left-text me-1"></i> Problem Description & Notes</label>
      <p class="mb-0 text-dark small" style="white-space: pre-wrap;">${esc(t.description)}</p>
    </div>

    ${t.resolved_at ? `<div class="small text-success"><i class="bi bi-check-circle me-1"></i> <strong>Resolved At:</strong> ${esc(t.resolved_at)}</div>` : ''}
  `;

  $('#viewTicketModalBody').html(modalHtml);
  $('#viewTicketActionBtns').html(actionBtnsHtml);
  $('#viewTicketModal').modal('show');
};



