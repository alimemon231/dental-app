<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — DentalPro</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/global.css">
  <link rel="stylesheet" href="assets/css/layout.css">
</head>

<body>
  <div class="app-shell">

    <?php require_once "includes/page-header.php"; ?>
    
    <main class="main-content">
      <div class="page-wrapper" style="max-width:1240px;">

        <div class="page-header">
          <div class="page-header-left">
            <h1>Dashboard</h1>
            <div class="page-header-sub">Welcome back. Here's what's happening today.</div>
          </div>
          <div class="page-header-actions">
            </div>
        </div>

        <?php
        // Dynamic Role-Based Template router engine
        if ($currentUser['role'] === 'admin') {
            require_once "admin-dashboard.php";
        } else if ($currentUser['role'] === 'staff') {
            require_once "staff-dashboard.php";
        } else if ($currentUser['role'] === 'doctor') {
            require_once "doctor-dashboard.php";
        } else {
            echo '<div class="alert alert-danger">Access Denied: Unrecognized organizational portal role profile context.</div>';
        }
        ?>

      </div>
    </main>
  </div>

  <div class="modal-backdrop" id="confirm-modal">
    <div class="modal modal-sm">
      <div class="modal-header">
        <div class="modal-title">Confirm Action</div>
        <button class="modal-close" data-close-modal="confirm-modal">&#x2715;</button>
      </div>
      <div class="modal-body">
        <p class="confirm-message">Are you sure?</p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-ghost" id="confirm-cancel">Cancel</button>
        <button class="btn btn-danger" id="confirm-ok">Confirm</button>
      </div>
    </div>
  </div>

  <div id="toast-container"></div>
  <div id="global-loader">
    <div class="loader-spinner"></div>
    <div class="loader-text">Please wait…</div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="assets/js/app.js"></script>

  <?php if ($currentUser['role'] === 'admin'): ?>
      <script src="assets/js/admin-dashboard.js"></script>
  <?php elseif ($currentUser['role'] === 'staff'): ?>
      <script src="assets/js/staff-dashboard.js"></script>
  <?php elseif ($currentUser['role'] === 'doctor'): ?>
      <script src="assets/js/doctor-dashboard.js"></script>
  <?php endif; ?>

  <script>
    $(document).ready(function () {
      /* 1. Global Runtime Security Check */
      App.auth.check();

      /* 2. Global Side-Navigation User Profile Initialization */
      App.ajax({
        url: '/auth/check.php', 
        loader: false, 
        silent: true,
        onSuccess: function (d) {
          if (d && d.user) {
            $('#sidebar-user-name').text(d.user.name);
            $('#sidebar-user-role').text(d.user.role || 'Staff');
            $('#user-avatar-initial').text(d.user.name.charAt(0).toUpperCase());
          }
        }
      });
    });
  </script>
</body>
</html>