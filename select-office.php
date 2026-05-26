<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Select Office — Dental App</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/global.css">
  <link rel="stylesheet" href="assets/css/auth.css">
  <style>
    /* Center layout styling matching auth page shell aesthetics */
    .office-selection-page {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      background-color: var(--color-bg, #f8f9fa);
      padding: var(--sp-6, 24px);
    }

    .office-selection-card {
      background: var(--color-surface, #fff);
      border: 1px solid var(--color-border, #eee);
      border-radius: var(--radius-lg, 12px);
      padding: var(--sp-8, 32px);
      width: 100%;
      max-width: 800px;
      box-shadow: var(--shadow-md);
    }

    /* Grid configuration to show 2 or 3 items beautifully in rows */
    .office-cards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: var(--sp-5, 20px);
      margin-top: var(--sp-6, 24px);
    }

    /* Individual custom styled office item card */
    .office-location-card {
      border: 1px solid var(--color-border, #ddd);
      border-radius: var(--radius-md, 8px);
      padding: var(--sp-5, 20px);
      text-align: center;
      cursor: pointer;
      transition: all var(--t-fast, 0.2s);
      background: var(--color-surface, #fff);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: space-between;
    }

    .office-location-card:hover {
      transform: translateY(-3px);
      border-color: var(--color-primary, #2b8a3e);
      box-shadow: var(--shadow-sm);
    }

    .office-card-icon {
      width: 54px;
      height: 54px;
      border-radius: var(--radius-full, 50%);
      background: var(--color-primary-light, #eef9f0);
      color: var(--color-primary, #2b8a3e);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.4rem;
      margin-bottom: var(--sp-3, 12px);
    }

    .office-card-name {
      font-size: var(--font-size-md, 1rem);
      font-weight: 600;
      color: var(--color-text, #222);
      margin-bottom: var(--sp-1, 4px);
    }

    .office-card-code {
      font-size: var(--font-size-xs, 11px);
      color: var(--color-text-muted, #777);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      background: var(--color-bg, #eee);
      padding: 2px 8px;
      border-radius: var(--radius-sm, 4px);
      margin-bottom: var(--sp-4, 16px);
    }

    .office-card-action-btn {
      width: 100%;
      text-align: center;
    }
  </style>
</head>

<body>

  <div class="office-selection-page">
    <div class="office-selection-card">

      <div class="auth-brand">
        <div class="auth-logo">
          <img src="assets/images/logo.jpg" alt="Ouray Dental Logo">
        </div>
      </div>

      <div class="auth-title text-center">Select Your Location</div>
      <div class="auth-subtitle text-center">Choose an assigned office clinic to monitor pipelines</div>

      <div id="selection-message" class="mt-4"></div>

      <div class="office-cards-grid" id="employee-office-container">
        
        <div class="text-center text-muted p-5" style="grid-column: 1 / -1;">
          <i class="fa-solid fa-circle-notch fa-spin fa-2xl mb-3 text-primary"></i>
          <div>Loading assigned clinical properties...</div>
        </div>

      </div>

      <div class="auth-footer text-center mt-8">
        &copy; <?= date('Y') ?> Ouray Dental &mdash; All rights reserved
      </div>

    </div></div><div id="toast-container"></div>
  
  <div id="global-loader">
    <div class="loader-spinner"></div>
    <div class="loader-text">Setting office context…</div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="assets/js/app.js"></script>
  <script>
    $(document).ready(function () {
      
      // Strict authentication checking context route
      App.auth.check();

      // Pull available office scopes assigned to this user session
      fetchAssignedUserOffices();

      // Handle card selections
      $(document).on('click', '.office-location-card', function () {
        const targetOfficeId = $(this).data('id');
        const targetOfficeName = $(this).data('name');
        setSessionWorkingOffice(targetOfficeId, targetOfficeName);
      });

    });

    /**
     * Pulls associated user offices array from authorization backend endpoint
     */
    function fetchAssignedUserOffices() {
      App.ajax({
        url: '/auth/user_offices.php',
        method: 'GET',
        onSuccess: function (records, message) {
          renderOfficeSelectionGrid(records);
        },
        onError: function (errorMessage) {
          $('#selection-message').html(
            '<div class="alert alert-danger">' +
            '<i class="fa-solid fa-circle-xmark"></i>' +
            '<div class="alert-content"><div class="alert-body">' + App.utils.escHtml(errorMessage) + '</div></div>' +
            '</div>'
          );
          $('#employee-office-container').html('');
        }
      });
    }

    /**
     * Builds standard card components into grid DOM wrapper
     */
    function renderOfficeSelectionGrid(items) {
      if (!items || items.length === 0) {
        $('#employee-office-container').html(
          '<div class="text-center text-muted p-4 style="grid-column: 1 / -1;">' +
          '<i class="fa-solid fa-building-circle-exclamation fa-2xl mb-2"></i>' +
          '<div>No clinic locations are currently assigned to your staff profile.</div>' +
          '</div>'
        );
        return;
      }

      let gridHtml = '';
      items.forEach(item => {
        const id = item.id || item.office_id;
        const name = item.office_name || item.name;
        const code = item.office_code || 'CLINIC';

        gridHtml += `
          <div class="office-location-card" data-id="${id}" data-name="${App.utils.escHtml(name)}">
            <div class="office-card-icon">
              <i class="fa-solid fa-hospital"></i>
            </div>
            <div class="office-card-name">${App.utils.escHtml(name)}</div>
            <div class="office-card-code">${App.utils.escHtml(code)}</div>
            <div class="office-card-action-btn">
              <button class="btn btn-primary btn-sm btn-block">
                Enter Location <i class="fa-solid fa-arrow-right text-xs ml-1"></i>
              </button>
            </div>
          </div>
        `;
      });

      $('#employee-office-container').html(gridHtml);
    }

    /**
     * Saves chosen context inside backend session environment before routing
     */
    function setSessionWorkingOffice(officeId, officeName) {
      App.ajax({
        url: '/auth/set_working_office.php',
        method: 'POST',
        data: { office_id: officeId },
        loader: true,
        loaderMsg: 'Accessing ' + officeName + '...',
        onSuccess: function (response, successMessage) {
          // Route straight to employee monitor dashboard view
          window.location.href = '/dashboard.php';
        },
        onError: function (errorMessage) {
          $('#selection-message').html(
            '<div class="alert alert-danger">' +
            '<i class="fa-solid fa-circle-xmark"></i>' +
            '<div class="alert-content"><div class="alert-body">' + App.utils.escHtml(errorMessage) + '</div></div>' +
            '</div>'
          );
        }
      });
    }
  </script>
</body>

</html>