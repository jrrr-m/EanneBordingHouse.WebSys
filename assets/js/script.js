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
  window.toggleAdminSidebar = function (e) {
    if (e && e.preventDefault) e.preventDefault();
    if (e && e.stopPropagation) e.stopPropagation();
    
    var sb = document.querySelector('.dashboard-sidebar');
    if (!sb) return;

    var bd = document.querySelector('.sidebar-backdrop');
    if (!bd) {
      bd = document.createElement('div');
      bd.className = 'sidebar-backdrop';
      bd.addEventListener('click', function() {
        if (sb) sb.classList.remove('show');
        bd.classList.remove('show');
      });
      document.body.appendChild(bd);
    }

    sb.classList.toggle('show');
    bd.classList.toggle('show');
  };

  $(document).on('click', '#sidebarToggleBtn', function (e) {
    window.toggleAdminSidebar(e);
  });

  $(document).on('click', '.sidebar-backdrop', function () {
    $('.dashboard-sidebar').removeClass('show');
    $('.sidebar-backdrop').removeClass('show');
  });

  $(document).on('click', function (e) {
    if ($(window).width() < 992) {
      const $sb = $('.dashboard-sidebar');
      const $btn = $('#sidebarToggleBtn');
      if ($sb.hasClass('show') && !$sb.is(e.target) && $sb.has(e.target).length === 0 && !$btn.is(e.target) && $btn.has(e.target).length === 0) {
        $sb.removeClass('show');
        $('.sidebar-backdrop').removeClass('show');
      }
    }
  });

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

  // 6. Interactive Filter Pills (Rooms, Tenants, Payments, Tickets, Gallery)
  window.applyUnifiedFilter = function ($group, targetSelector) {
    const $btn = $group.find('.btn-filter.active').first();
    const filter = ($btn.attr('data-filter') || 'all').toLowerCase().trim();
    const $items = $(targetSelector || '.filterable-item');
    if (!$items.length) return;

    // Detect if there is an associated search box on the page
    let searchTerm = '';
    if ($('#tenantSearchInput').length && targetSelector.includes('Tenant')) {
      searchTerm = ($('#tenantSearchInput').val() || '').toLowerCase().trim();
    } else if ($('#paymentSearchInput').length && targetSelector.includes('payment')) {
      searchTerm = ($('#paymentSearchInput').val() || '').toLowerCase().trim();
    } else if ($('#ticketSearchInput').length && targetSelector.includes('Ticket')) {
      searchTerm = ($('#ticketSearchInput').val() || '').toLowerCase().trim();
    }

    let matchCount = 0;
    $items.each(function () {
      const $item = $(this);
      const cat = ($item.attr('data-category') || '').toLowerCase();
      const status = ($item.attr('data-status') || '').toLowerCase();
      const text = $item.text().toLowerCase();

      // Check filter condition
      let matchesFilter = false;
      if (filter === 'all' || !filter) {
        matchesFilter = true;
      } else {
        // Token-based matching or exact match
        const catTokens = cat.split(/\s+/);
        matchesFilter = (status === filter) || (catTokens.indexOf(filter) !== -1) || (cat.indexOf(filter) !== -1);
      }

      // Check search condition
      let matchesSearch = true;
      if (searchTerm) {
        matchesSearch = text.indexOf(searchTerm) !== -1;
      }

      const show = matchesFilter && matchesSearch;
      if (show) {
        $item.show();
        matchCount++;
      } else {
        $item.hide();
      }
    });

    // Handle empty state display
    const isTable = $items.first().is('tr');
    if (isTable) {
      const $tbody = $items.first().closest('tbody');
      $tbody.find('.ebh-empty-filter-row').remove();
      if (matchCount === 0) {
        const colSpan = $items.first().children('td').length || 8;
        $tbody.append(`
          <tr class="ebh-empty-filter-row">
            <td colspan="${colSpan}" class="text-center py-4 text-muted">
              <i class="bi bi-search text-secondary me-2"></i> No matching results found for this filter or search.
            </td>
          </tr>
        `);
      }
    } else {
      // Grid containers (like #roomsGridContainer or #galleryGrid)
      const $parent = $items.first().parent();
      $parent.find('.ebh-empty-filter-card').remove();
      if (matchCount === 0) {
        $parent.append(`
          <div class="col-12 text-center py-5 text-muted ebh-empty-filter-card">
            <i class="bi bi-inbox fs-2 text-secondary d-block mb-2"></i>
            <h6>No matching items found</h6>
            <p class="small text-muted mb-0">Try selecting a different filter or clearing search.</p>
          </div>
        `);
      }
    }
  };

  $(document).on('click', '.btn-filter', function () {
    const $btn = $(this);
    const $group = $btn.closest('.btn-filter-group');
    const targetSelector = $btn.attr('data-target-items') || '.filterable-item';

    $group.find('.btn-filter').removeClass('active');
    $btn.addClass('active');

    window.applyUnifiedFilter($group, targetSelector);

    // If on tickets page and dynamic renderer exists, let it sync too
    if (typeof window.renderAdminTicketsTable === 'function' && targetSelector.includes('Ticket')) {
      window.renderAdminTicketsTable();
    }
  });

  // 7. Interactive Table Search Filtering (coordinating with filter pills)
  function setupTableSearch(inputId, tableId, countBadgeId) {
    const $input = $(inputId);
    const $table = $(tableId);
    if (!$input.length || !$table.length) return;

    $input.on('keyup input', function () {
      const $group = $('.btn-filter-group').has(`[data-target-items*="${tableId.replace('#', '')}"]`);
      if ($group.length) {
        const activeTarget = $group.find('.btn-filter.active').attr('data-target-items') || `${tableId} tbody tr`;
        window.applyUnifiedFilter($group, activeTarget);
      } else {
        const value = $(this).val().toLowerCase().trim();
        let matchCount = 0;
        $table.find('tbody tr').not('.ebh-empty-filter-row').each(function () {
          const rowText = $(this).text().toLowerCase();
          const matches = rowText.indexOf(value) > -1;
          $(this).toggle(matches);
          if (matches) matchCount++;
        });

        $table.find('.ebh-empty-filter-row').remove();
        if (matchCount === 0) {
          const colSpan = $table.find('thead th').length || 8;
          $table.find('tbody').append(`
            <tr class="ebh-empty-filter-row">
              <td colspan="${colSpan}" class="text-center py-4 text-muted">
                <i class="bi bi-search text-secondary me-2"></i> No matching results found for "${window.escapeHtml(value)}".
              </td>
            </tr>
          `);
        }

        if (countBadgeId && $(countBadgeId).length) {
          $(countBadgeId).text(matchCount);
        }
      }
    });
  }

  setupTableSearch('#tenantSearchInput', '#activeTenantsTable', '#tenantCountBadge');
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
            try {
              sessionStorage.setItem('ebh_user', JSON.stringify(res.user));
              localStorage.setItem('ebh_user', JSON.stringify(res.user));
            } catch (err) {}
            showToast('Login Successful', `Welcome back, ${res.user.full_name}!`, 'success');
            setTimeout(function () {
              if (res.user.role === 'admin') {
                window.location.href = 'admin-dashboard.php';
              } else {
                window.location.href = 'tenant-dashboard.php';
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
      const currentProfile = window.currentTenantProfile || window.getEbhCurrentUser() || {};
      const activeTenant = currentProfile.full_name || $('#payTenantName').val() || '';
      const activeUnit = currentProfile.unit_code || $('#payUnitCode').val() || '';
      const activeAmount = currentProfile.monthly_rent || $('#payAmount').val() || '3500';

      // Ensure dynamic fields
      if (!formData.get('tenant_name') && activeTenant) formData.append('tenant_name', activeTenant);
      if (!formData.get('unit_code') && activeUnit) formData.append('unit_code', activeUnit);
      if (!formData.get('payment_method')) formData.append('payment_method', $('#payMethodSelect').val() || 'GCash');
      if (!formData.get('amount')) formData.append('amount', activeAmount);
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
                $tbody.find('.empty-row, #tenantLoadingRow').remove();
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
      const currentProfile = window.currentTenantProfile || window.getEbhCurrentUser() || {};
      const activeTenant = currentProfile.full_name || $('#payTenantName').val() || '';
      const activeUnit = currentProfile.unit_code || $('#payUnitCode').val() || '';

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
          tenant_name: activeTenant,
          unit_code: activeUnit,
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
              window.loadTenantTickets(activeTenant);
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

  // 13. Public Contact & Viewing Form (Anonymous Visitor Submission)
  const $contactForm = $('#contactViewingForm');
  if ($contactForm.length) {
    $contactForm.on('submit', function (e) {
      e.preventDefault();
      const $form = $(this);
      const $btn = $form.find('button[type="submit"]');
      const originalText = $btn.html();

      // Reset alert displays
      $('#contactSuccessAlert').slideUp(150);
      $('#contactErrorAlert').slideUp(150);

      const fullName = ($form.find('#contactFullName').val() || $form.find('input[type="text"]').not('#contactWebsite').val() || '').trim();
      const mobile = ($form.find('#contactMobile').val() || $form.find('input[type="tel"]').val() || '').trim();
      const roomType = $form.find('#contactRoomType').val() || $form.find('select').val() || 'General Inquiry';
      const preferredDate = $form.find('#contactPreferredDate').val() || $form.find('input[type="date"]').val() || '';
      const notes = ($form.find('#contactNotes').val() || $form.find('textarea').val() || '').trim();
      const honeypot = ($form.find('#contactWebsite').val() || '').trim();

      // Client-side validations
      if (!fullName) {
        $('#contactErrorMsg').text('Please enter your full name.');
        $('#contactErrorAlert').slideDown(200);
        showToast('Required Field', 'Please enter your full name.', 'warning');
        return;
      }

      const cleanPhone = mobile.replace(/[^0-9]/g, '');
      const validPhone = (cleanPhone.length === 11 && cleanPhone.startsWith('09')) || (cleanPhone.length === 12 && cleanPhone.startsWith('639'));
      if (!validPhone) {
        $('#contactErrorMsg').text('Please enter a valid Philippine mobile number (e.g. 0917-555-1234 or 09XXXXXXXXX).');
        $('#contactErrorAlert').slideDown(200);
        showToast('Invalid Mobile Number', 'Please enter a valid Philippine mobile number (09XX-XXX-XXXX).', 'warning');
        return;
      }

      if (!preferredDate) {
        $('#contactErrorMsg').text('Please select a preferred viewing date.');
        $('#contactErrorAlert').slideDown(200);
        showToast('Required Field', 'Please select a preferred viewing date.', 'warning');
        return;
      }

      // Check date is not in past
      const todayStr = new Date().toISOString().split('T')[0];
      if (preferredDate < todayStr) {
        $('#contactErrorMsg').text('Preferred viewing date cannot be in the past. Please select today or a future date.');
        $('#contactErrorAlert').slideDown(200);
        showToast('Invalid Date', 'Preferred viewing date cannot be in the past.', 'warning');
        return;
      }

      $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Submitting request...');

      const postData = {
        action: 'create',
        user_name: fullName,
        contact_phone: mobile,
        unit_or_type: roomType,
        preferred_date: preferredDate,
        notes: notes,
        website: honeypot
      };

      $.ajax({
        url: window.getEbhApiUrl('api_viewings.php'),
        type: 'POST',
        data: postData,
        dataType: 'json',
        success: function (res) {
          $btn.prop('disabled', false).html(originalText);
          if (res.success) {
            $form[0].reset();
            $('#contactErrorAlert').hide();
            $('#contactSuccessAlert').slideDown(250);
            showToast('Viewing Scheduled', res.message || 'Thank you! The caretaker will contact you via SMS to confirm your schedule.', 'success');
            if (typeof window.loadAdminViewingsQueue === 'function') {
              window.loadAdminViewingsQueue();
            }
          } else {
            const err = res.error || 'Could not schedule viewing.';
            $('#contactErrorMsg').text(err);
            $('#contactErrorAlert').slideDown(250);
            showToast('Submission Failed', err, 'danger');
          }
        },
        error: function (xhr, status, err) {
          $btn.prop('disabled', false).html(originalText);
          let errMsg = 'Connection failed: ' + err;
          try {
            const parsed = JSON.parse(xhr.responseText);
            if (parsed && parsed.error) errMsg = parsed.error;
          } catch (e) {}
          $('#contactErrorMsg').text(errMsg);
          $('#contactErrorAlert').slideDown(250);
          showToast('Submission Error', errMsg, 'danger');
        }
      });
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

  // Populate dynamic select dropdowns across modals (Add Tenant, Edit Tenant, Record Cash, Create Ticket)
  if (typeof window.populateModalSelects === 'function') {
    window.populateModalSelects();
  }

  // Handle global sign out links cleanly
  $('a[href*="login.php"], a[href*="login.html"]').on('click', function () {
    const text = ($(this).text() || '').toLowerCase();
    if (text.indexOf('sign out') !== -1 || text.indexOf('logout') !== -1 || $(this).attr('title') === 'Sign Out') {
      try {
        sessionStorage.removeItem('ebh_user');
        localStorage.removeItem('ebh_user');
      } catch (e) {}
      $.get(window.getEbhApiUrl('api_auth.php?logout=1'));
    }
  });

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
  if ($('#adminViewingsTable').length) {
    window.loadAdminViewingsQueue();
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

window.getEbhCurrentUser = function () {
  try {
    const raw = sessionStorage.getItem('ebh_user') || localStorage.getItem('ebh_user');
    return raw ? JSON.parse(raw) : null;
  } catch (err) {
    return null;
  }
};

// Dynamic Select Dropdown Populator for Modals (Syncs live with MySQL rooms and users)
window.populateModalSelects = function () {
  // 1. Populate Room Selects (#newTenantRoom, #editTenantRoom, #contactRoomType)
  if ($('#newTenantRoom').length || $('#editTenantRoom').length || $('#contactRoomType').length) {
    $.ajax({
      url: window.getEbhApiUrl('api_rooms.php'),
      type: 'GET',
      dataType: 'json',
      success: function (res) {
        if (!res || !res.success || !res.rooms) return;

        let newTenantOpts = '';
        let editTenantOpts = '';
        let contactOpts = '<option value="">-- Select Preferred Accommodation --</option>';

        res.rooms.forEach(function (r) {
          const isAvail = r.status === 'available';
          const availTag = isAvail ? ' (Available)' : ` (${r.status.toUpperCase()})`;
          const optLabel = `Unit ${r.unit_code} - ${r.room_type} (₱${Number(r.monthly_rate).toLocaleString()}/mo)${availTag}`;

          newTenantOpts += `<option value="Unit ${r.unit_code}" data-unit="${r.unit_code}" data-rate="${r.monthly_rate}" data-type="${r.room_type}">${optLabel}</option>`;
          editTenantOpts += `<option value="${r.unit_code}" data-rate="${r.monthly_rate}" data-type="${r.room_type}">Unit ${r.unit_code} (${r.room_type})</option>`;
          contactOpts += `<option value="Unit ${r.unit_code} (${r.room_type})">Unit ${r.unit_code} - ${r.room_type} (₱${Number(r.monthly_rate).toLocaleString()})</option>`;
        });

        if ($('#newTenantRoom').length) {
          const prevVal = $('#newTenantRoom').val();
          $('#newTenantRoom').html(newTenantOpts);
          if (prevVal) $('#newTenantRoom').val(prevVal);
          $('#newTenantRoom').trigger('change');
        }

        if ($('#editTenantRoom').length) {
          const prevVal = $('#editTenantRoom').val();
          $('#editTenantRoom').html(editTenantOpts);
          if (prevVal) $('#editTenantRoom').val(prevVal);
        }

        if ($('#contactRoomType').length) {
          $('#contactRoomType').html(contactOpts);
        }
      }
    });
  }

  // 2. Populate Tenant Selects (#cashTenantSelect, #adminTicketTenant)
  if ($('#cashTenantSelect').length || $('#adminTicketTenant').length) {
    $.ajax({
      url: window.getEbhApiUrl('api_tenants.php'),
      type: 'GET',
      dataType: 'json',
      success: function (res) {
        if (!res || !res.success || !res.tenants) return;

        let cashOpts = '<option value="">-- Choose Resident --</option>';
        let ticketOpts = '<option value="">-- Choose Tenant / Unit --</option>';

        res.tenants.forEach(function (t) {
          if (t.is_active) {
            cashOpts += `<option value="${t.full_name}" data-unit="${t.unit_code}" data-rent="${t.monthly_rent}">${t.full_name} - Unit ${t.unit_code} (${t.monthly_rent_formatted})</option>`;
            ticketOpts += `<option value="${t.full_name}" data-unit="${t.unit_code}">${t.full_name} (Unit ${t.unit_code})</option>`;
          }
        });
        ticketOpts += `<option value="Caretaker Inspection" data-unit="Compound">Caretaker / Facility Management</option>`;

        if ($('#cashTenantSelect').length) {
          const prevVal = $('#cashTenantSelect').val();
          $('#cashTenantSelect').html(cashOpts);
          if (prevVal) $('#cashTenantSelect').val(prevVal);
        }

        if ($('#adminTicketTenant').length) {
          const prevVal = $('#adminTicketTenant').val();
          $('#adminTicketTenant').html(ticketOpts);
          if (prevVal) $('#adminTicketTenant').val(prevVal);
        }
      }
    });
  }
};

// Auto-fill connected inputs on dropdown change
$(document).on('change', '#newTenantRoom', function () {
  const $opt = $(this).find('option:selected');
  const rate = $opt.attr('data-rate');
  const type = $opt.attr('data-type');
  if (rate && $('#newTenantRent').length) $('#newTenantRent').val(rate);
  if (type && $('#newTenantType').length) $('#newTenantType').val(type);
});

$(document).on('change', '#editTenantRoom', function () {
  const $opt = $(this).find('option:selected');
  const rate = $opt.attr('data-rate');
  if (rate && $('#editTenantRent').length) $('#editTenantRent').val(rate);
});

$(document).on('change', '#cashTenantSelect', function () {
  const $opt = $(this).find('option:selected');
  const rent = $opt.attr('data-rent');
  if (rent && $('#cashAmountInput').length) $('#cashAmountInput').val(rent);
});

$(document).on('change', '#adminTicketTenant', function () {
  const $opt = $(this).find('option:selected');
  const unit = $opt.attr('data-unit');
  if (unit && $('#adminTicketUnit').length) $('#adminTicketUnit').val(unit);
});

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

// Sanitization Helpers for HTML and JS Strings
window.escapeHtml = function (str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
};

window.escapeJs = function (str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/\\/g, '\\\\')
    .replace(/'/g, "\\'")
    .replace(/"/g, '\\"')
    .replace(/\n/g, ' ')
    .replace(/\r/g, '');
};

// Open Reject Payment Verification Modal with Target Info & Reason Prompt
window.openRejectPaymentModal = function (id, refNo, tenantName, amount, unitCode) {
  const $row = $('#payment-row-' + id);
  let resolvedRef = refNo || '';
  let resolvedTenant = tenantName || '';
  let resolvedUnit = unitCode || '';
  let resolvedAmount = amount || '';

  if ($row.length) {
    if (!resolvedRef) {
      resolvedRef = $row.find('td').eq(0).text().trim();
    }
    if (!resolvedTenant) {
      resolvedTenant = $row.find('.avatar-circle').next().find('span').text().trim() || $row.find('td').eq(1).find('.fw-bold').text().trim() || $row.find('td').eq(0).find('.fw-bold').text().trim();
    }
    if (!resolvedUnit) {
      resolvedUnit = $row.find('.avatar-circle').next().find('small').text().trim() || $row.find('td').eq(1).find('.text-muted').text().trim() || $row.find('td').eq(0).find('.text-muted').text().trim();
    }
    if (!resolvedAmount) {
      resolvedAmount = $row.find('td').eq(2).text().trim() || $row.find('td').eq(1).text().trim();
    }
  }

  resolvedRef = resolvedRef || ('REF-' + id);
  resolvedTenant = resolvedTenant || 'Tenant';
  resolvedAmount = resolvedAmount || 'Rent Payment';

  // Ensure Reject Modal exists in DOM
  if (!$('#rejectPaymentModal').length) {
    $('body').append(`
      <div class="modal fade" id="rejectPaymentModal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered" role="document">
          <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger text-white">
              <h6 class="modal-title fw-bold text-white mb-0">
                <i class="bi bi-x-circle me-2"></i> Reject Payment Verification
              </h6>
              <button type="button" class="close text-white border-0 bg-transparent" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <form id="rejectPaymentForm" novalidate onsubmit="event.preventDefault(); window.confirmRejectPayment(); return false;">
              <input type="hidden" id="rejectPaymentId" value="">
              <div class="modal-body p-4 bg-light">
                <!-- Payment target summary banner -->
                <div class="card border mb-3 shadow-sm bg-white">
                  <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                      <div>
                        <span class="badge badge-secondary px-2 py-1 mb-1" id="rejectPaymentRef"></span>
                        <h6 class="fw-bold text-navy mb-0" id="rejectTenantName"></h6>
                        <small class="text-muted d-block" id="rejectUnitCode"></small>
                      </div>
                      <div class="text-end">
                        <span class="text-muted small d-block">Amount Due</span>
                        <strong class="fs-5 text-danger" id="rejectAmount"></strong>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label small fw-bold text-navy mb-1">
                    Select Common Rejection Reason
                  </label>
                  <select class="form-control form-control-sm mb-2" id="rejectPresetSelect" onchange="window.handleRejectPresetChange ? window.handleRejectPresetChange(this.value) : (this.value && this.value !== 'custom' ? document.getElementById('rejectReasonText').value = this.value : null)">
                    <option value="">-- Choose a standard reason (optional) --</option>
                    <option value="Unclear or blurry proof of payment. Transaction reference number and amount are unreadable.">Unclear or blurry receipt screenshot</option>
                    <option value="Reference / Transaction number does not match the uploaded proof of payment.">Reference number mismatch</option>
                    <option value="Duplicate submission. This transaction receipt was already recorded or claimed.">Duplicate receipt submission</option>
                    <option value="Payment not received in owner's GCash / BPI bank account. Please check recipient number.">Payment not found in landlord account</option>
                    <option value="Payment sent to wrong recipient / invalid account.">Wrong recipient account used</option>
                    <option value="Expired or altered transaction screenshot.">Invalid or altered screenshot</option>
                    <option value="custom">Other / Custom reason</option>
                  </select>

                  <div class="d-flex flex-wrap gap-1 mb-3" id="rejectQuickChips">
                    <button type="button" class="btn btn-xs btn-outline-secondary reject-chip" onclick="window.setRejectReasonChip ? window.setRejectReasonChip(this.getAttribute('data-reason')) : (document.getElementById('rejectReasonText').value = this.getAttribute('data-reason'))" data-reason="Unclear or blurry receipt. Please re-upload a clear image showing reference number and amount."><i class="bi bi-tag me-1"></i> Blurry receipt</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary reject-chip" onclick="window.setRejectReasonChip ? window.setRejectReasonChip(this.getAttribute('data-reason')) : (document.getElementById('rejectReasonText').value = this.getAttribute('data-reason'))" data-reason="Reference number does not match uploaded receipt screenshot."><i class="bi bi-tag me-1"></i> Ref # mismatch</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary reject-chip" onclick="window.setRejectReasonChip ? window.setRejectReasonChip(this.getAttribute('data-reason')) : (document.getElementById('rejectReasonText').value = this.getAttribute('data-reason'))" data-reason="Transaction not found in landlord's bank / GCash history."><i class="bi bi-tag me-1"></i> Not received</button>
                  </div>

                  <label for="rejectReasonText" class="form-label small fw-bold text-navy mb-1">
                    Reason for Rejection <span class="text-danger">*</span>
                  </label>
                  <textarea class="form-control" id="rejectReasonText" rows="3" placeholder="Type or select the reason why this payment is being rejected..."></textarea>
                  <div class="invalid-feedback">Please specify a reason before rejecting.</div>

                  <div class="alert alert-light border small text-muted mt-2 mb-0 py-2 px-3">
                    <i class="bi bi-info-circle text-primary me-1"></i> <strong>Audit Trail:</strong> This reason will be recorded in MySQL and displayed to the tenant on their portal so they know what to fix.
                  </div>
                </div>
              </div>
              <div class="modal-footer bg-white border-top">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" id="btnConfirmReject" onclick="if(window.confirmRejectPayment){ window.confirmRejectPayment(); }">
                  <i class="bi bi-x-circle me-1"></i> Confirm Rejection
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    `);
  }

  // Populate fields
  $('#rejectPaymentId').val(id);
  $('#rejectPaymentRef').text(resolvedRef);
  $('#rejectTenantName').text(resolvedTenant);
  $('#rejectUnitCode').text(resolvedUnit);
  $('#rejectAmount').text(resolvedAmount);
  $('#rejectPresetSelect').val('');
  $('#rejectReasonText').val('').removeClass('is-invalid');

  $('#rejectPaymentModal').modal('show');
};

// Global Handlers for Rejection Modal (works vanilla and with jQuery)
window.handleRejectPresetChange = function (val) {
  const reasonEl = document.getElementById('rejectReasonText');
  if (!reasonEl) return;
  if (val && val !== 'custom') {
    reasonEl.value = val;
    $(reasonEl).removeClass('is-invalid');
  } else if (val === 'custom') {
    reasonEl.value = '';
    reasonEl.focus();
    $(reasonEl).removeClass('is-invalid');
  }
};

window.setRejectReasonChip = function (chipReason) {
  const reasonEl = document.getElementById('rejectReasonText');
  const presetEl = document.getElementById('rejectPresetSelect');
  if (reasonEl && chipReason) {
    reasonEl.value = chipReason;
    $(reasonEl).removeClass('is-invalid');
  }
  if (presetEl) {
    presetEl.value = '';
  }
};

window.confirmRejectPayment = function () {
  const pId = $('#rejectPaymentId').val();
  const reasonEl = document.getElementById('rejectReasonText');
  const presetEl = document.getElementById('rejectPresetSelect');
  let reasonVal = reasonEl ? reasonEl.value.trim() : '';
  const presetVal = presetEl ? presetEl.value : '';

  // Fallback: If reason textarea is blank but preset was chosen, auto-fill it!
  if (!reasonVal && presetVal && presetVal !== 'custom') {
    reasonVal = presetVal;
    if (reasonEl) reasonEl.value = presetVal;
  }

  if (!reasonVal) {
    if (reasonEl) {
      $(reasonEl).addClass('is-invalid').focus();
    }
    return;
  }

  if (reasonEl) $(reasonEl).removeClass('is-invalid');

  const $btn = $('#btnConfirmReject');
  const origHtml = $btn.html();
  $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Rejecting...');

  window.processPayment(pId, 'rejected', reasonVal);

  $('#rejectPaymentModal').modal('hide');
  setTimeout(function () {
    $btn.prop('disabled', false).html(origHtml);
  }, 500);
};

// Rejection Reason Modal Global Event Listeners (Delegated)
$(document).on('change', '#rejectPresetSelect', function () {
  window.handleRejectPresetChange($(this).val());
});

$(document).on('click', '.reject-chip', function () {
  window.setRejectReasonChip($(this).data('reason') || $(this).attr('data-reason'));
});

$(document).on('submit', '#rejectPaymentForm', function (e) {
  e.preventDefault();
  window.confirmRejectPayment();
});

// Admin Payment Verification Action (Syncs live with MySQL)
window.processPayment = function (id, action, notes) {
  const isApprove = action === 'approved' || action === 'approve' || action === 'verify';
  const apiAction = isApprove ? 'approve' : 'reject';
  const $row = $('#payment-row-' + id);

  // If rejecting and no reason was provided, intercept and show the Rejection Reason modal
  if (!isApprove && (!notes || String(notes).trim() === '')) {
    if (typeof window.openRejectPaymentModal === 'function') {
      window.openRejectPaymentModal(id);
      return;
    }
  }

  if ($row.length) {
    $row.find('.action-btn-group button').prop('disabled', true);
  }

  $.ajax({
    url: window.getEbhApiUrl('api_update_payment.php'),
    type: 'POST',
    data: {
      id: id,
      action: apiAction,
      reason: (!isApprove && notes) ? notes : '',
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
                  <button class="btn btn-sm btn-outline-secondary" onclick="previewReceiptModal('${p.reference_no}', '${window.escapeJs(p.tenant_name)}', '${window.escapeJs(p.unit_code)}', '${p.amount}', '${p.payment_date_short}', '${window.escapeJs(p.payment_method)}', '${p.reference_no}', '${proofPath}', 'verified', ${p.id}, '${window.escapeJs(p.admin_notes || '')}')">
                    <i class="bi bi-receipt me-1"></i> Receipt & Proof
                  </button>
                `;
                $(this).html(receiptBtnHtml).fadeIn(150);
              }
            } else {
              const displayReason = p.rejection_reason || notes || 'Rejected';
              $row.find('.status-col').html(`
                <span class="badge-ebh badge-rejected"><i class="bi bi-x-circle"></i> Rejected</span>
                <small class="d-block text-danger mt-1 text-wrap" style="font-size:0.75rem; max-width:200px;" title="${window.escapeHtml(p.admin_notes || notes || '')}">
                  <i class="bi bi-info-circle me-1"></i>${window.escapeHtml(displayReason)}
                </small>
              `);
              $row.removeClass('table-warning').attr('data-status', 'rejected');
              $row.attr('data-category', function (i, val) { return (val || '').replace('pending', 'rejected'); });

              if ($row.closest('#adminPendingTable').length) {
                $(this).html('<span class="small text-danger fw-semibold"><i class="bi bi-x"></i> Rejected</span>').fadeIn(150);
                setTimeout(function () {
                  $row.fadeOut(300, function () {
                    $(this).remove();
                    if (!$('#adminPendingTable tbody tr').length) {
                      $('#adminPendingTable tbody').html('<tr><td colspan="6" class="text-center py-4 text-muted"><i class="bi bi-check-circle-fill text-success fs-3 d-block mb-1"></i><strong>All caught up!</strong> No pending payments in queue.</td></tr>');
                    }
                  });
                }, 1000);
              } else {
                const detailsBtnHtml = `
                  <button class="btn btn-sm btn-outline-secondary" onclick="previewReceiptModal('${p.reference_no}', '${window.escapeJs(p.tenant_name)}', '${window.escapeJs(p.unit_code)}', '${p.amount}', '${p.payment_date_short}', '${window.escapeJs(p.payment_method)}', '${p.reference_no}', '${proofPath}', 'rejected', ${p.id}, '${window.escapeJs(p.admin_notes || notes || '')}')">
                    <i class="bi bi-receipt me-1"></i> View Details
                  </button>
                `;
                $(this).html(detailsBtnHtml).fadeIn(150);
              }
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

        // If the user currently has the 'pending' filter tab active on payments.html, hide the newly verified/rejected row
        if ($('.btn-filter.active').attr('data-filter') === 'pending') {
          setTimeout(function () {
            $row.fadeOut(300);
          }, 600);
        }

        const toastMsg = isApprove 
          ? `Payment #${p.reference_no} is now Verified & Cleared in MySQL.`
          : `Payment #${p.reference_no} rejected: "${p.rejection_reason || notes || 'Action logged'}"`;

        showToast(
          isApprove ? 'Verified & Cleared' : 'Payment Rejected',
          toastMsg,
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
window.previewReceiptModal = function (id, tenant, room, amount, date, method, ref, proofImage, status, dbId, notes) {
  const tenantName = tenant || 'Resident';
  const roomName = room || 'Assigned Unit';
  const amountNum = parseFloat(String(amount).replace(/[^0-9.]/g, '')) || 0;
  const amountStr = amountNum.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const dateStr = date || new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  const methodStr = method || 'Electronic Transfer';
  const refNum = ref || id || 'REF-N/A';
  const currentStatus = (status || 'verified').toLowerCase();
  const paymentDbId = dbId || id;

  let proofPhotoHtml = '';
  if (proofImage && proofImage.trim() !== '') {
    const fullProofUrl = window.getEbhUploadUrl(proofImage);
    const placeholderUrl = '../uploads/payments/receipt_placeholder.svg';
    proofPhotoHtml = `
      <div class="card mb-3 border bg-light shadow-sm overflow-hidden">
        <div class="card-header bg-navy text-white d-flex justify-content-between align-items-center py-2 px-3">
          <span class="small fw-bold"><i class="bi bi-image me-1 text-info"></i> Uploaded Proof of Payment</span>
          <div class="d-flex gap-1">
            <a href="${fullProofUrl}" target="_blank" class="btn btn-sm btn-outline-light py-0 px-2" style="font-size:0.75rem;" title="Open in new tab"><i class="bi bi-box-arrow-up-right me-1"></i> Open in New Tab</a>
            <a href="${fullProofUrl}" download="payment_receipt_${refNum}.jpg" class="btn btn-sm btn-light py-0 px-2" style="font-size:0.75rem;" title="Download proof"><i class="bi bi-download me-1"></i> Download</a>
          </div>
        </div>
        <div class="card-body p-2 text-center bg-dark" style="border-radius: 0 0 4px 4px; min-height:160px; display:flex; align-items:center; justify-content:center;">
          <a href="${fullProofUrl}" target="_blank" title="Click to view enlarged photo">
            <img src="${fullProofUrl}" alt="Proof of Payment" class="img-fluid rounded shadow" style="max-height: 280px; object-fit: contain; cursor: zoom-in;" onerror="this.onerror=null; this.src='${placeholderUrl}'; this.parentElement.removeAttribute('href'); this.title='Image could not be loaded';">
          </a>
        </div>
      </div>
    `;
  } else {
    proofPhotoHtml = `
      <div class="alert alert-secondary border small text-muted mb-3 d-flex align-items-center gap-2">
        <i class="bi bi-image text-secondary fs-4"></i>
        <div><strong>No image uploaded:</strong> Walk-in cash or manual ledger payment without attachment. Official digital payment voucher generated below.</div>
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

  let rejectionNoticeHtml = '';
  if (currentStatus === 'rejected') {
    const reasonText = notes || 'Payment verification rejected. Please re-upload a clear proof of payment or consult management.';
    rejectionNoticeHtml = `
      <div class="alert alert-danger border-danger mb-3 p-3 rounded shadow-sm" style="background-color: #fef2f2;">
        <div class="d-flex align-items-start gap-2">
          <i class="bi bi-x-circle-fill fs-5 text-danger mt-1"></i>
          <div class="flex-grow-1">
            <h6 class="fw-bold text-danger mb-1">Payment Verification Rejected</h6>
            <div class="small text-dark mb-1"><strong>Reason for Rejection:</strong> ${window.escapeHtml(reasonText)}</div>
            <small class="text-muted d-block"><i class="bi bi-info-circle me-1"></i> The tenant has been prompted to re-submit valid proof with matching transaction reference number.</small>
          </div>
        </div>
      </div>
    `;
  } else if (notes && notes.trim() !== '') {
    rejectionNoticeHtml = `
      <div class="alert alert-info border mb-3 p-2 rounded small">
        <i class="bi bi-info-circle me-1"></i> <strong>Admin Notes:</strong> ${window.escapeHtml(notes)}
      </div>
    `;
  }

  let actionButtonsHtml = '';
  if (currentStatus === 'pending') {
    actionButtonsHtml = `
      <div class="p-3 bg-white border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="small text-muted"><i class="bi bi-shield-lock text-primary me-1"></i> Landlord Review Action:</div>
        <div class="btn-group btn-group-sm">
          <button type="button" class="btn btn-outline-danger" onclick="$('#receiptPreviewModal').modal('hide'); setTimeout(function(){ openRejectPaymentModal('${paymentDbId}', '${refNum}', '${window.escapeJs(tenantName)}', '₱${amountStr}', '${window.escapeJs(roomName)}'); }, 250);"><i class="bi bi-x-circle me-1"></i> Reject</button>
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
            ${rejectionNoticeHtml}

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
            const cleanReason = p.rejection_reason || (p.admin_notes || '').replace(/^Rejected by .*? - /i, '').replace(/^Reason:\s*/i, '');
            statusBadge = `
              <span class="badge-ebh badge-rejected"><i class="bi bi-x-circle"></i> Rejected</span>
              ${cleanReason ? `<small class="d-block text-danger mt-1 text-wrap" style="font-size:0.75rem; max-width:200px;" title="${window.escapeHtml(p.admin_notes)}"><i class="bi bi-info-circle me-1"></i>${window.escapeHtml(cleanReason)}</small>` : ''}
            `;
          }

          const initials = (p.tenant_name || 'TN').split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
          const proofPath = p.proof_image ? p.proof_image : '';

          let actionsHtml = '';
          if (isPending) {
            actionsHtml = `
              <div class="action-btn-group btn-group btn-group-sm">
                <button class="btn btn-outline-secondary" title="View Proof & Receipt" onclick="previewReceiptModal('${p.reference_no}', '${window.escapeJs(p.tenant_name)}', '${window.escapeJs(p.unit_code)}', '${p.amount}', '${p.payment_date_short}', '${window.escapeJs(p.payment_method)}', '${p.reference_no}', '${proofPath}', '${p.status}', ${p.id}, '${window.escapeJs(p.admin_notes || '')}')"><i class="bi bi-eye"></i></button>
                <button class="btn btn-success" title="Approve & Mark Verified" onclick="processPayment(${p.id}, 'approved')"><i class="bi bi-check-lg"></i> Approve</button>
                <button class="btn btn-outline-danger" title="Reject Payment" onclick="openRejectPaymentModal(${p.id}, '${p.reference_no}', '${window.escapeJs(p.tenant_name)}', '${p.amount_formatted}', 'Unit ${window.escapeJs(p.unit_code)}')"><i class="bi bi-x-lg"></i> Reject</button>
              </div>
            `;
          } else {
            actionsHtml = `
              <button class="btn btn-sm btn-outline-secondary" onclick="previewReceiptModal('${p.reference_no}', '${window.escapeJs(p.tenant_name)}', '${window.escapeJs(p.unit_code)}', '${p.amount}', '${p.payment_date_short}', '${window.escapeJs(p.payment_method)}', '${p.reference_no}', '${proofPath}', '${p.status}', ${p.id}, '${window.escapeJs(p.admin_notes || '')}')">
                <i class="bi bi-receipt me-1"></i> ${isVerified ? 'Receipt & Proof' : 'View Details'}
              </button>
            `;
          }

          let typeBadge = '<span class="badge badge-secondary text-white">Rent</span>';
          if (p.payment_type === 'advance') {
            typeBadge = '<span class="badge badge-success text-white"><i class="bi bi-calendar-plus me-1"></i> Advance</span>';
          } else if (p.payment_type === 'partial') {
            typeBadge = '<span class="badge badge-info text-white"><i class="bi bi-pie-chart me-1"></i> Partial</span>';
          }

          html += `
            <tr class="filterable-item ${isPending ? 'table-warning' : ''}" data-category="${p.status} ${p.payment_method.toLowerCase()} ${p.payment_type || 'rent'}" data-status="${p.status}" id="payment-row-${p.id}">
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
              <td>${typeBadge}</td>
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
      } else {
        $tbody.html('<tr><td colspan="7" class="text-center py-4 text-muted"><i class="bi bi-inbox fs-2 text-muted d-block mb-1"></i>No payment transactions found in database.</td></tr>');
      }
    },
    error: function () {
      $tbody.html('<tr><td colspan="7" class="text-center py-4 text-muted">Could not load payments from database.</td></tr>');
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
            const cleanReason = p.rejection_reason || (p.admin_notes || '').replace(/^Rejected by .*? - /i, '').replace(/^Reason:\s*/i, '');
            statusBadge = `
              <span class="badge-ebh badge-rejected"><i class="bi bi-x-circle"></i> Rejected</span>
              ${cleanReason ? `<small class="d-block text-danger mt-1 text-wrap" style="font-size:0.75rem; max-width:180px;"><i class="bi bi-info-circle me-1"></i>${window.escapeHtml(cleanReason)}</small>` : ''}
            `;
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
                <button class="btn btn-sm btn-outline-secondary" onclick="previewReceiptModal('${p.reference_no}', '${window.escapeJs(p.tenant_name)}', '${window.escapeJs(p.unit_code)}', '${p.amount}', '${p.payment_date_short}', '${window.escapeJs(p.payment_method)}', '${p.reference_no}', '${proofPath}', '${p.status}', ${p.id}, '${window.escapeJs(p.admin_notes || '')}')">
                  <i class="bi ${isVerified ? 'bi-receipt' : 'bi-eye'} me-1"></i> ${isVerified ? 'Receipt & Proof' : 'View Proof'}
                </button>
              </td>
            </tr>
          `;
        });
        $tbody.html(html);
      } else {
        $tbody.html('<tr class="empty-row"><td colspan="6" class="text-center py-4 text-muted"><i class="bi bi-receipt fs-3 text-muted d-block mb-1"></i>No payments recorded yet for this resident account.</td></tr>');
      }
    },
    error: function () {
      $tbody.html('<tr><td colspan="6" class="text-center py-3 text-muted">Could not load payment history.</td></tr>');
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
                  <button class="btn btn-outline-secondary" title="View Proof & Receipt" onclick="previewReceiptModal('${p.reference_no}', '${window.escapeJs(p.tenant_name)}', '${window.escapeJs(p.unit_code)}', '${p.amount}', '${p.payment_date_short}', '${window.escapeJs(p.payment_method)}', '${p.reference_no}', '${proofPath}', '${p.status}', ${p.id}, '${window.escapeJs(p.admin_notes || '')}')"><i class="bi bi-eye"></i></button>
                  <button class="btn btn-success" title="Approve & Clear" onclick="processPayment(${p.id}, 'approved')"><i class="bi bi-check-lg"></i> Approve</button>
                  <button class="btn btn-outline-danger" title="Reject Payment" onclick="openRejectPaymentModal(${p.id}, '${p.reference_no}', '${window.escapeJs(p.tenant_name)}', '${p.amount_formatted}', 'Unit ${window.escapeJs(p.unit_code)}')"><i class="bi bi-x-lg"></i></button>
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

// Update Viewing Request Status via AJAX
window.updateAdminViewingStatus = function (id, newStatus) {
  if (!id || !newStatus) return;
  $.ajax({
    url: window.getEbhApiUrl('api_viewings.php'),
    type: 'POST',
    data: {
      action: 'update_status',
      id: id,
      status: newStatus
    },
    dataType: 'json',
    success: function (res) {
      if (res.success) {
        if (typeof window.showToast === 'function') {
          window.showToast('Viewing request updated to ' + newStatus, 'success');
        }
        window.loadAdminViewingsQueue();
      } else {
        alert(res.message || 'Failed to update status.');
      }
    },
    error: function () {
      alert('Network error updating viewing status.');
    }
  });
};

// Fetch and Populate Viewings Queue Table on admin-dashboard from MySQL
window.loadAdminViewingsQueue = function () {
  const $tbody = $('#adminViewingsTable tbody');
  if (!$tbody.length) return;

  $.ajax({
    url: window.getEbhApiUrl('api_viewings.php'),
    type: 'GET',
    dataType: 'json',
    success: function (res) {
      if (res.success && res.requests && res.requests.length) {
        let html = '';
        let pendingCount = 0;
        res.requests.forEach(function (vr) {
          const initials = (vr.user_name || 'VR').split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
          const isPending = vr.status === 'pending';
          const isConfirmed = vr.status === 'confirmed';
          const isDeclined = vr.status === 'declined';
          const isCompleted = vr.status === 'completed';

          if (isPending) pendingCount++;

          let statusBadge = `<span class="badge badge-secondary">${vr.status}</span>`;
          if (isPending) {
            statusBadge = `<span class="badge-ebh badge-pending"><i class="bi bi-clock-history"></i> Pending</span>`;
          } else if (isConfirmed) {
            statusBadge = `<span class="badge-ebh badge-verified"><i class="bi bi-check-circle"></i> Confirmed</span>`;
          } else if (isDeclined) {
            statusBadge = `<span class="badge badge-danger"><i class="bi bi-x-circle"></i> Declined</span>`;
          } else if (isCompleted) {
            statusBadge = `<span class="badge badge-secondary"><i class="bi bi-check-all"></i> Completed</span>`;
          }

          const safePhone = window.escapeJs(vr.contact_phone || '');
          const safeName = window.escapeJs(vr.user_name || '');

          html += `
            <tr class="${isPending ? 'table-warning' : ''}" id="viewing-row-${vr.id}">
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="avatar-circle sm bg-light border text-navy">${initials}</div>
                  <div>
                    <strong class="text-navy d-block">${window.escapeJs(vr.user_name)}</strong>
                    ${vr.notes ? `<small class="text-muted d-block text-truncate" style="max-width:200px;" title="${window.escapeJs(vr.notes)}"><i class="bi bi-chat-left-text me-1"></i>${window.escapeJs(vr.notes)}</small>` : ''}
                  </div>
                </div>
              </td>
              <td>
                <div class="d-flex align-items-center gap-1">
                  <a href="tel:${safePhone}" class="fw-semibold text-navy text-decoration-none" title="Call or SMS via cellular">
                    <i class="bi bi-telephone text-primary me-1"></i>${safePhone}
                  </a>
                  <button type="button" class="btn btn-sm btn-link text-muted p-0 px-1 border-0" title="Copy Mobile Number" onclick="if(navigator.clipboard){navigator.clipboard.writeText('${safePhone}');alert('Phone number copied: ${safePhone}');}">
                    <i class="bi bi-clipboard"></i>
                  </button>
                </div>
              </td>
              <td>
                <span class="badge badge-light border text-navy"><i class="bi bi-door-open me-1"></i>${window.escapeJs(vr.unit_or_type)}</span>
              </td>
              <td>
                <small class="fw-semibold text-navy d-block"><i class="bi bi-calendar-event text-primary me-1"></i>${vr.preferred_date_formatted || vr.preferred_date}</small>
                <small class="text-muted" style="font-size:0.75rem;">Submitted ${vr.created_at_formatted || vr.created_at}</small>
              </td>
              <td>${statusBadge}</td>
              <td class="text-end">
                <select class="form-control form-control-sm custom-select custom-select-sm d-inline-block w-auto" onchange="window.updateAdminViewingStatus(${vr.id}, this.value)">
                  <option value="pending" ${isPending ? 'selected' : ''}>Pending</option>
                  <option value="confirmed" ${isConfirmed ? 'selected' : ''}>Confirmed</option>
                  <option value="declined" ${isDeclined ? 'selected' : ''}>Declined</option>
                  <option value="completed" ${isCompleted ? 'selected' : ''}>Completed</option>
                </select>
              </td>
            </tr>
          `;
        });
        $tbody.html(html);

        // Update badges
        const $badge = $('#viewingPendingBadge');
        if ($badge.length) {
          $badge.text(pendingCount + ' Pending');
          $badge.removeClass('badge-warning badge-success text-dark text-white')
            .addClass(pendingCount > 0 ? 'badge-warning text-dark' : 'badge-success text-white');
        }
        const $sideBadge = $('#sidebarViewingsBadge');
        if ($sideBadge.length) {
          $sideBadge.text(pendingCount);
          $sideBadge.removeClass('badge-warning badge-secondary text-dark text-white')
            .addClass(pendingCount > 0 ? 'badge-warning text-dark' : 'badge-secondary text-white');
        }
      } else {
        $tbody.html('<tr><td colspan="6" class="text-center py-4 text-muted"><i class="bi bi-calendar-x text-muted fs-3 d-block mb-1"></i>No viewing schedule requests received yet.</td></tr>');
        $('#viewingPendingBadge').text('0 Pending').removeClass('badge-warning text-dark').addClass('badge-success text-white');
        $('#sidebarViewingsBadge').text('0').removeClass('badge-warning text-dark').addClass('badge-secondary text-white');
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
      } else {
        $tbody.html('<tr><td colspan="7" class="text-center py-4 text-muted"><i class="bi bi-journal-x fs-2 text-muted d-block mb-1"></i>No ledger records found in database.</td></tr>');
        if ($('#reportsCountBadge').length) $('#reportsCountBadge').text('0');
      }
    },
    error: function () {
      $tbody.html('<tr><td colspan="7" class="text-center py-4 text-muted">Could not load report ledger from database.</td></tr>');
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
window.openEditTenantModal = function (id, name, phone, email, unitCode, rent, moveIn, status, username, password) {
  $('#editTenantId').val(id);
  $('#editTenantName').val(name);
  $('#editTenantContact').val(phone);
  $('#editTenantEmail').val(email || '');
  $('#editTenantMoveIn').val(moveIn || '');
  $('#editTenantRoom').val((unitCode || '').replace('Unit ', '').trim());
  $('#editTenantRent').val(rent || 3500);
  $('#editTenantStatus').val(status || 'active');
  $('#editTenantUsername').val(username || '');
  $('#editTenantPassword').val(password || '');
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
      const activitiesList = res.recent_activity || res.activities || [];
      if ($('#recentActivityFeed').length) {
        if (activitiesList.length) {
          let actHtml = '';
          activitiesList.forEach(function (act) {
            actHtml += `
              <div class="d-flex gap-2">
                <div class="avatar-circle sm bg-light border ${act.icon_color || 'text-primary'}"><i class="bi ${act.icon || 'bi-activity'}"></i></div>
                <div>
                  <strong class="text-navy">${act.title}:</strong> ${act.description}
                  <div class="text-muted" style="font-size:0.75rem;">${act.timestamp}</div>
                </div>
              </div>
            `;
          });
          $('#recentActivityFeed').html(actHtml);
        } else {
          $('#recentActivityFeed').html('<div class="text-center py-3 text-muted small"><i class="bi bi-clock-history me-1"></i>No recent activity logged yet.</div>');
        }
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
            <div class="room-resident-card mb-3">
              <div class="avatar-circle md me-3 flex-shrink-0 bg-primary text-white shadow-sm fw-bold">${initials}</div>
              <div class="overflow-hidden flex-grow-1">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <span class="badge bg-success-subtle text-success fw-bold" style="font-size:0.65rem;"><i class="bi bi-check-circle-fill me-1"></i> Active Tenant</span>
                  ${r.move_in_date ? `<small class="text-muted" style="font-size:0.7rem;"><i class="bi bi-calendar-event me-1"></i>Since ${r.move_in_date}</small>` : ''}
                </div>
                <h6 class="fw-bold text-navy mb-0 text-truncate" style="font-size:0.9rem;">${r.current_tenant}</h6>
                <div class="small text-muted text-truncate" style="font-size:0.75rem;">
                  <a href="tel:${(r.tenant_phone || '').replace(/[^0-9]/g, '')}" class="text-muted text-decoration-none">
                    <i class="bi bi-telephone-fill text-primary me-1"></i>${r.tenant_phone || 'Active Lease'}
                  </a>
                </div>
              </div>
            </div>
          `;
        } else {
          residentHtml = `
            <div class="room-resident-card vacant mb-3">
              <div class="avatar-circle md me-3 flex-shrink-0 bg-success text-white shadow-sm">
                <i class="bi bi-door-open-fill fs-5"></i>
              </div>
              <div class="overflow-hidden flex-grow-1">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <span class="badge bg-success text-white fw-bold" style="font-size:0.65rem;"><i class="bi bi-sparkles me-1"></i> Vacant Now</span>
                  <small class="text-success fw-semibold" style="font-size:0.7rem;">Ready to Move In</small>
                </div>
                <h6 class="fw-bold text-success mb-0" style="font-size:0.9rem;">Available for Lease</h6>
                <small class="text-muted d-block" style="font-size:0.75rem;">No current resident &bull; Immediate turnover</small>
              </div>
            </div>
          `;
        }

        const roomCategoryPill = isPrivate 
          ? '<span class="badge bg-purple-subtle"><i class="bi bi-house-door-fill me-1"></i> Private</span>'
          : '<span class="badge bg-blue-subtle"><i class="bi bi-building me-1"></i> Boarding</span>';

        const statusPill = isOccupied
          ? '<span class="badge bg-danger-subtle"><i class="bi bi-lock-fill me-1"></i> Occupied</span>'
          : '<span class="badge bg-success-subtle"><i class="bi bi-sparkles me-1"></i> Available</span>';

        const featureChips = isPrivate
          ? `
            <span class="room-chip"><i class="bi bi-house-fill text-primary"></i> Standalone</span>
            <span class="room-chip"><i class="bi bi-shield-check text-success"></i> 100% Private</span>
            <span class="room-chip"><i class="bi bi-droplet-fill text-info"></i> Private CR</span>
            <span class="room-chip text-danger"><i class="bi bi-lightning-charge-fill"></i> Bills Excluded</span>
          `
          : `
            <span class="room-chip"><i class="bi bi-door-open-fill text-primary"></i> Solo Room</span>
            <span class="room-chip"><i class="bi bi-cup-hot-fill text-warning"></i> Kitchen Area</span>
            <span class="room-chip"><i class="bi bi-droplet-half text-info"></i> Shared CR</span>
            <span class="room-chip text-danger"><i class="bi bi-lightning-charge-fill"></i> Bills Excluded</span>
          `;

        const actionHtml = isOccupied
          ? `<button class="btn btn-light border text-muted hover-navy btn-sm w-100 fw-semibold py-2" onclick="window.updateRoomStatus(${r.id}, 'available')"><i class="bi bi-arrow-repeat me-1 text-primary"></i> Mark as Vacant</button>`
          : `<button class="btn btn-ebh-primary btn-sm w-100 fw-bold py-2 shadow-sm" data-toggle="modal" data-target="#addTenantModal"><i class="bi bi-person-plus-fill me-1"></i> Assign New Tenant</button>`;

        html += `
          <div class="col-md-6 col-lg-4 mb-4 filterable-item" data-category="${r.category} ${r.status}" data-status="${r.status}">
            <div class="room-card h-100 ${isOccupied ? 'is-occupied' : 'is-available'}">
              <div class="room-card-header">
                <div class="d-flex align-items-center gap-2">
                  <span class="room-status-dot ${r.status}"></span>
                  <span class="room-unit-badge">Unit ${r.unit_code}</span>
                  ${roomCategoryPill}
                </div>
                ${statusPill}
              </div>
              <div class="ebh-card-body d-flex flex-column flex-grow-1">
                <!-- Pricing & Capacity Highlight -->
                <div class="d-flex justify-content-between align-items-center py-2 px-3 mb-3 rounded-3" style="background:#f8fafc; border:1px solid #edf2f7;">
                  <div>
                    <span class="text-uppercase text-muted fw-bold d-block" style="font-size:0.65rem; letter-spacing:0.04em;">Monthly Rate</span>
                    <span class="h4 fw-bold text-navy mb-0">${r.monthly_rate_formatted}</span>
                    <small class="text-muted fw-normal" style="font-size:0.75rem;">/ month</small>
                  </div>
                  <div class="text-end">
                    <span class="text-uppercase text-muted fw-bold d-block" style="font-size:0.65rem; letter-spacing:0.04em;">Occupancy</span>
                    <span class="badge bg-white text-navy border fw-semibold px-2 py-1">
                      <i class="bi bi-people-fill text-primary me-1"></i> Max ${r.max_pax || (isPrivate ? '6' : '3')} Pax
                    </span>
                  </div>
                </div>

                <!-- Feature Chips -->
                <div class="d-flex flex-wrap gap-1 mb-3">
                  ${featureChips}
                </div>

                <!-- Resident / Availability Information Box -->
                ${residentHtml}

                <!-- Bottom Card Actions -->
                <div class="mt-auto pt-3 border-top" style="border-top-color: #f1f5f9 !important;">
                  <div class="d-flex flex-column gap-2">
                    ${actionHtml}
                    <div class="d-flex gap-2">
                      <button type="button" class="btn btn-outline-primary btn-sm flex-grow-1 py-1.5 fw-semibold" title="Edit Unit Specifications" onclick="window.openEditRoomModal(${r.id}, '${r.unit_code}', '${r.room_type}', ${r.monthly_rate}, ${r.max_pax}, '${encodeURIComponent(r.inclusions || '')}', '${r.status}')"><i class="bi bi-pencil-square me-1"></i> Edit Specifications</button>
                      <button type="button" class="btn btn-outline-danger btn-sm px-3 py-1.5" title="Permanently Delete Unit" onclick="window.deleteRoom(${r.id}, '${r.unit_code}')"><i class="bi bi-trash3"></i></button>
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

      $container.html(html || '<div class="col-12 text-center py-5 text-muted"><i class="bi bi-door-open fs-2 d-block mb-2"></i>No rooms found in database inventory.</div>');

      // Update filter counts and stats dynamically
      if (res.counts) {
        if ($('#filterBtnAllRooms').length) $('#filterBtnAllRooms').text(`All Units (${res.counts.total})`);
        if ($('#filterBtnBhRooms').length) $('#filterBtnBhRooms').text(`Boarding House (${res.counts.boarding_house})`);
        if ($('#filterBtnPbRooms').length) $('#filterBtnPbRooms').text(`Private House (${res.counts.private_house})`);
        if ($('#filterBtnAvailRooms').length) $('#filterBtnAvailRooms').html(`<i class="bi bi-sparkles text-success"></i> Available Only (${res.counts.available})`);
        if ($('#roomStatTotal').length) $('#roomStatTotal').text(res.counts.total);
        if ($('#roomStatOccupied').length) $('#roomStatOccupied').text(res.counts.occupied);
        if ($('#roomStatAvailable').length) $('#roomStatAvailable').text(res.counts.available);
      }

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
          if (typeof window.loadTenantsList === 'function') window.loadTenantsList();
          if (typeof window.syncPublicAvailability === 'function') window.syncPublicAvailability();
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

        const balNum = parseFloat(t.balance !== undefined ? t.balance : t.monthly_rent);
        let balHtml = `<span class="fw-bold text-danger">₱${balNum.toLocaleString(undefined, {minimumFractionDigits:2})}</span>`;
        if (balNum < 0) {
          balHtml = `<span class="fw-bold text-success">-₱${Math.abs(balNum).toLocaleString(undefined, {minimumFractionDigits:2})}</span><small class="badge badge-success d-block mt-1">Advance Credit</small>`;
        } else if (balNum === 0) {
          balHtml = `<span class="fw-bold text-success">₱0.00</span><small class="text-muted d-block">Fully Paid</small>`;
        }

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
            <td>${balHtml}</td>
            <td><small class="text-muted">${t.move_in_date}</small></td>
            <td>${statusBadge}</td>
            <td class="text-end">
              <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-primary" title="View Profile" onclick="showTenantDetails('${(t.full_name || '').replace(/'/g, "\\'")}', 'Unit ${t.unit_code}', '${(t.room_type || '').replace(/'/g, "\\'")}', '${t.monthly_rent_formatted}', '${(t.phone || '').replace(/'/g, "\\'")}', '${t.move_in_date}')"><i class="bi bi-eye"></i></button>
                <button type="button" class="btn btn-outline-warning" title="Edit Tenant Record" onclick="window.openEditTenantModal(${t.id}, '${window.escapeJs(t.full_name)}', '${window.escapeJs(t.phone)}', '${window.escapeJs(t.email)}', '${t.unit_code}', ${t.monthly_rent}, '${t.move_in_date_raw || ''}', '${t.lease_status}', '${window.escapeJs(t.username)}', '${window.escapeJs(t.password)}')"><i class="bi bi-pencil-square"></i></button>
                <button type="button" class="btn btn-outline-secondary" title="Mark Move Out / Inactive" onclick="removeTenantRow(this, ${t.id}, '${(t.full_name || '').replace(/'/g, "\\'")}')"><i class="bi bi-box-arrow-right"></i></button>
                <button type="button" class="btn btn-outline-danger" title="Permanently Delete Tenant Account" onclick="window.deleteTenantRow(this, ${t.id}, '${(t.full_name || '').replace(/'/g, "\\'")}')"><i class="bi bi-trash3"></i></button>
              </div>
            </td>
          </tr>
        `;
      });

      $tbody.html(html || '<tr><td colspan="8" class="text-center py-4 text-muted">No resident records found in database.</td></tr>');

      if (res.counts) {
        if ($('#tenantCountBadge').length) $('#tenantCountBadge').text(res.counts.active);
        if ($('#filterBtnAllTenants').length) $('#filterBtnAllTenants').text(`All Tenants (${res.counts.total})`);
        if ($('#filterBtnActiveTenants').length) $('#filterBtnActiveTenants').text(`Active (${res.counts.active})`);
        if ($('#filterBtnBhTenants').length) $('#filterBtnBhTenants').text(`Boarding House (${res.counts.boarding_house})`);
        if ($('#filterBtnPbTenants').length) $('#filterBtnPbTenants').text(`Private House (${res.counts.private_house})`);
      }
    }
  });
};

// 5. Fetch & Populate Tenant Dashboard Profile and Statement from MySQL
window.loadTenantDashboard = function (tenantName, username) {
  const sessionUser = typeof window.getEbhCurrentUser === 'function' ? window.getEbhCurrentUser() : null;
  const searchName = tenantName || (sessionUser ? sessionUser.full_name : '');
  const searchUser = username || (sessionUser ? sessionUser.username : '');

  let param = '';
  if (searchName) param = '?name=' + encodeURIComponent(searchName);
  else if (searchUser) param = '?username=' + encodeURIComponent(searchUser);

  $.ajax({
    url: window.getEbhApiUrl('api_tenant_profile.php' + param),
    type: 'GET',
    dataType: 'json',
    success: function (res) {
      if (!res || !res.success || !res.profile) return;
      const p = res.profile;
      window.currentTenantProfile = p;

      // Update Document Title
      document.title = `Tenant Portal | ${p.full_name} | Eanne Boarding House`;

      // Topbar Navbar User Info
      if ($('#tenantNavAvatar').length) $('#tenantNavAvatar').text(p.initials || 'TN');
      if ($('#tenantNavName').length) $('#tenantNavName').text(p.full_name);
      if ($('#tenantNavUnit').length) $('#tenantNavUnit').text(`Unit ${p.unit_code} (${p.room_type})`);

      // Welcome Banner
      if ($('#tenantWelcomeTitle').length) $('#tenantWelcomeTitle').text(`Welcome back, ${p.first_name || p.full_name}!`);
      if ($('#tenantWelcomeSub').length) {
        $('#tenantWelcomeSub').html(`Unit ${p.unit_code} (${p.room_type} - ${p.inclusions}) &bull; Soldiers Village, Pasig City. Bills excluded (shouldered by tenant).`);
      }

      // Statement Info
      if ($('#tenantStatementMonth').length) $('#tenantStatementMonth').text(p.statement_month);
      if ($('#tenantStatementRent').length) $('#tenantStatementRent').text(p.monthly_rent_formatted);
      if ($('#tenantCurrentBalanceDisplay').length && p.balance_formatted) {
        $('#tenantCurrentBalanceDisplay').text(p.balance_formatted);
        if (p.balance <= 0) {
          $('#tenantCurrentBalanceDisplay').removeClass('text-danger').addClass('text-success');
        } else {
          $('#tenantCurrentBalanceDisplay').removeClass('text-success').addClass('text-danger');
        }
      }
      if ($('#tenantStatementDueDate').length) {
        $('#tenantStatementDueDate').html(`<i class="bi bi-calendar-event me-1 text-primary"></i> ${p.due_date}`);
      }
      if ($('#tenantStatementBadge').length) {
        $('#tenantStatementBadge')
          .attr('class', 'badge-ebh ' + p.badge_class)
          .html(`<i class="bi bi-check-circle"></i> ${p.payment_status_text}`);
      }

      // Hidden Form Fields for Upload Payment Modal
      if ($('#payTenantName').length) $('#payTenantName').val(p.full_name);
      if ($('#payUnitCode').length) $('#payUnitCode').val(p.unit_code);
      if ($('#payAmount').length && (!parseFloat($('#payAmount').val()) || parseFloat($('#payAmount').val()) === p.monthly_rent)) {
        $('#payAmount').val(p.balance > 0 ? p.balance : p.monthly_rent);
      }

      // Safeguard: Hide Advance Payment options if tenant has an outstanding balance
      if ($('#btnAdvancePayment').length) {
        if (p.balance > 0) {
          $('#btnAdvancePayment').hide();
        } else {
          $('#btnAdvancePayment').show();
        }
      }
      if ($('#optTypeAdvance').length) {
        if (p.balance > 0) {
          $('#optTypeAdvance').hide();
          if ($('#typeAdvance').is(':checked')) {
            $('#typePartial').prop('checked', true).trigger('change');
            $('#optTypePartial').addClass('active').siblings().removeClass('active');
          }
        } else {
          $('#optTypeAdvance').show();
        }
      }

      // Also load tenant payment history ledger & maintenance tickets from MySQL
      if (typeof window.loadTenantPayments === 'function') {
        window.loadTenantPayments(p.full_name);
      }
      if (typeof window.loadTenantTickets === 'function') {
        window.loadTenantTickets(p.full_name);
      }
    }
  });
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
    const isOp = t.status === 'open';
    const isInP = t.status === 'in_progress';
    const isRes = t.status === 'resolved';

    // Interactive Status Select Box
    const statusSelect = `
      <select class="form-select form-select-sm status-select status-select-${t.status}" onchange="window.updateTicketStatus(${t.id}, this.value)" style="font-size:0.8rem; font-weight:600; padding:3px 8px; border-radius:6px; cursor:pointer;">
        <option value="open" ${isOp ? 'selected' : ''}>🟡 Open</option>
        <option value="in_progress" ${isInP ? 'selected' : ''}>🔵 In Progress</option>
        <option value="resolved" ${isRes ? 'selected' : ''}>🟢 Resolved</option>
      </select>
    `;

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
        <td>${statusSelect}</td>
        <td class="small text-muted">${esc(t.created_at_short)}</td>
        <td class="text-right">
          <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-secondary" title="View Full Details" onclick="window.previewTicketDetails(${t.id})"><i class="bi bi-eye"></i></button>
            <div class="dropdown d-inline">
              <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Select Status">
                <i class="bi bi-pencil-square"></i>
              </button>
              <div class="dropdown-menu dropdown-menu-right shadow-sm border-0">
                <h6 class="dropdown-header text-uppercase text-muted" style="font-size:0.7rem;">Set Status</h6>
                <button type="button" class="dropdown-item ${isOp ? 'active fw-bold' : ''}" onclick="window.updateTicketStatus(${t.id}, 'open')">
                  <i class="bi bi-clock-history text-warning me-1"></i> Open
                </button>
                <button type="button" class="dropdown-item ${isInP ? 'active fw-bold' : ''}" onclick="window.updateTicketStatus(${t.id}, 'in_progress')">
                  <i class="bi bi-wrench-adjustable text-primary me-1"></i> In Progress
                </button>
                <button type="button" class="dropdown-item ${isRes ? 'active fw-bold' : ''}" onclick="window.updateTicketStatus(${t.id}, 'resolved')">
                  <i class="bi bi-check-circle-fill text-success me-1"></i> Resolved
                </button>
              </div>
            </div>
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
    $row.find('button, select').prop('disabled', true);
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
        if ($row.length) $row.find('button, select').prop('disabled', false);
      }
    },
    error: function (xhr, status, err) {
      showToast('Error', 'Connection failed: ' + err, 'danger');
      if ($row.length) $row.find('button, select').prop('disabled', false);
    }
  });
};

window.previewTicketDetails = function (id) {
  function renderTicketModal(t) {
    const isOp = t.status === 'open';
    const isInP = t.status === 'in_progress';
    const isRes = t.status === 'resolved';

    let statusBadge = '';
    if (isOp) {
      statusBadge = '<span class="badge-ebh badge-open"><i class="bi bi-clock"></i> Open</span>';
    } else if (isInP) {
      statusBadge = '<span class="badge-ebh badge-in-progress"><i class="bi bi-gear-wide-connected"></i> In Progress</span>';
    } else {
      statusBadge = '<span class="badge-ebh badge-verified"><i class="bi bi-check-circle-fill"></i> Resolved</span>';
    }

    const actionBtnsHtml = `
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm ${isOp ? 'btn-warning fw-bold' : 'btn-outline-warning'}" onclick="window.updateTicketStatus(${t.id}, 'open')">
          <i class="bi bi-clock-history me-1"></i> Open
        </button>
        <button type="button" class="btn btn-sm ${isInP ? 'btn-primary fw-bold' : 'btn-outline-primary'}" onclick="window.updateTicketStatus(${t.id}, 'in_progress')">
          <i class="bi bi-wrench-adjustable me-1"></i> In Progress
        </button>
        <button type="button" class="btn btn-sm ${isRes ? 'btn-success fw-bold' : 'btn-outline-success'}" onclick="window.updateTicketStatus(${t.id}, 'resolved')">
          <i class="bi bi-check2-circle me-1"></i> Resolved
        </button>
      </div>
    `;

    function esc(s) {
      if (!s) return '';
      return $('<div>').text(s).html();
    }

    // Parse attachments (could be JSON array string, comma-separated, or single file path)
    let images = [];
    if (t.attachment_images) {
      if (Array.isArray(t.attachment_images)) {
        images = t.attachment_images;
      } else if (typeof t.attachment_images === 'string') {
        const raw = t.attachment_images.trim();
        if (raw.startsWith('[') && raw.endsWith(']')) {
          try {
            images = JSON.parse(raw);
          } catch (e) {
            images = [raw];
          }
        } else if (raw) {
          images = raw.split(',').map(s => s.trim()).filter(Boolean);
        }
      }
    }

    let imagesHtml = '';
    if (images && images.length > 0) {
      let thumbsHtml = '';
      images.forEach(function (imgSrc, idx) {
        const fullUrl = window.getEbhUploadUrl(imgSrc);
        thumbsHtml += `
          <div class="col-6 col-md-4 mb-2">
            <div class="border rounded bg-dark p-1 text-center position-relative overflow-hidden" style="height: 140px; display: flex; align-items: center; justify-content: center;">
              <a href="${fullUrl}" target="_blank" title="Click to open full photo">
                <img src="${fullUrl}" alt="Attachment ${idx + 1}" class="img-fluid rounded" style="max-height: 130px; object-fit: contain; cursor: zoom-in;" onerror="this.onerror=null; this.src='../uploads/payments/receipt_placeholder.svg'; this.parentElement.removeAttribute('href'); this.title='Image could not be loaded';">
              </a>
              <div class="position-absolute bottom-0 end-0 p-1">
                <a href="${fullUrl}" download="ticket_${esc(t.ticket_no)}_img_${idx + 1}.jpg" class="btn btn-xs btn-light shadow-sm py-0 px-1" title="Download Image"><i class="bi bi-download"></i></a>
              </div>
            </div>
          </div>
        `;
      });

      imagesHtml = `
        <div class="card mb-3 border bg-light shadow-sm">
          <div class="card-header bg-navy text-white d-flex justify-content-between align-items-center py-2 px-3">
            <span class="small fw-bold"><i class="bi bi-images me-1 text-info"></i> Attached Photos & Receipts (${images.length})</span>
            <small class="text-white-50">Click any image to enlarge or open in new tab</small>
          </div>
          <div class="card-body p-3">
            <div class="row g-2">
              ${thumbsHtml}
            </div>
          </div>
        </div>
      `;
    } else {
      imagesHtml = `
        <div class="alert alert-secondary border small text-muted mb-3 d-flex align-items-center gap-2">
          <i class="bi bi-image text-secondary fs-4"></i>
          <div><strong>No image uploaded:</strong> No photos or repair receipts were attached to this ticket.</div>
        </div>
      `;
    }

    const modalHtml = `
      <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
        <div>
          <h5 class="fw-bold text-navy mb-0">Ticket #${esc(t.ticket_no)}</h5>
          <small class="text-muted"><i class="bi bi-calendar3 me-1"></i> Logged on ${esc(t.created_at || t.created_at_short)}</small>
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

      ${imagesHtml}

      ${t.resolved_at ? `<div class="small text-success mt-2"><i class="bi bi-check-circle me-1"></i> <strong>Resolved At:</strong> ${esc(t.resolved_at)}</div>` : ''}
    `;

    $('#viewTicketModalBody').html(modalHtml);
    $('#viewTicketActionBtns').html(actionBtnsHtml);
    $('#viewTicketModal').modal('show');
  }

  const tickets = window.ebhAdminTicketsCache || [];
  const t = tickets.find(item => item.id == id);
  if (t) {
    renderTicketModal(t);
  } else {
    // Fetch directly from API
    $.ajax({
      url: window.getEbhApiUrl('api_tickets.php'),
      type: 'GET',
      dataType: 'json',
      success: function (res) {
        if (res.success && res.tickets) {
          window.ebhAdminTicketsCache = res.tickets;
          const found = res.tickets.find(item => item.id == id);
          if (found) {
            renderTicketModal(found);
          } else {
            showToast('Ticket Details', 'Ticket record not found.', 'warning');
          }
        }
      }
    });
  }
};



