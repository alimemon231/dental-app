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


  <?php require_once "includes/page-header.php" ?>
  <!-- MAIN CONTENT -->
  <main class="main-content">
    <div class="page-wrapper">

      <div class="page-header">
        <div class="page-header-left">
          <h1>Dashboard</h1>
          <div class="page-header-sub">Welcome back. Here's what's happening today.</div>
        </div>
        <div class="page-header-actions">
          <!-- <button class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-rotate-right"></i> Refresh</button>
          <a href="pages/appointments.php?action=new" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> New Appointment</a> -->
        </div>
      </div>

      <!-- Stat cards -->
      <div class="grid-4 mb-8">
        <div class="stat-card">
          <div class="stat-icon primary"><i class="fa-solid fa-users"></i></div>
          <div class="stat-body">
            <div class="stat-value" id="stat-patients">—</div>
            <div class="stat-label">Total Patients</div>
            <div class="stat-trend up"><i class="fa-solid fa-arrow-trend-up"></i> +12 this month</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon success"><i class="fa-solid fa-calendar-check"></i></div>
          <div class="stat-body">
            <div class="stat-value" id="stat-appointments">—</div>
            <div class="stat-label">Today's Appointments</div>
            <div class="stat-trend up"><i class="fa-solid fa-circle-dot"></i> <span id="stat-pending">0</span> pending</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon warning"><i class="fa-solid fa-box-open"></i></div>
          <div class="stat-body">
            <div class="stat-value" id="stat-stock">—</div>
            <div class="stat-label">Low Stock Items</div>
            <div class="stat-trend down"><i class="fa-solid fa-triangle-exclamation"></i> Needs attention</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon info"><i class="fa-solid fa-file-invoice-dollar"></i></div>
          <div class="stat-body">
            <div class="stat-value" id="stat-revenue">—</div>
            <div class="stat-label">Revenue This Month</div>
            <div class="stat-trend up"><i class="fa-solid fa-arrow-trend-up"></i> +8% vs last month</div>
          </div>
        </div>
      </div>

      <!-- Two column -->
      <!-- <div class="grid-2 mb-8">
        <div class="card">
          <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-calendar-day" style="color:var(--color-primary);margin-right:var(--sp-2)"></i>Today's Appointments</div>
            <a href="pages/appointments.php" class="btn btn-ghost btn-sm">View All</a>
          </div>
          <div id="todays-appointments-container">
            <div class="table-empty"><i class="fa-regular fa-calendar"></i>Loading…</div>
          </div>
        </div>
        <div class="card">
          <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-triangle-exclamation" style="color:var(--color-warning);margin-right:var(--sp-2)"></i>Low Stock Alerts</div>
            <a href="pages/stock.php" class="btn btn-ghost btn-sm">View All</a>
          </div>
          <div id="low-stock-container">
            <div class="table-empty"><i class="fa-solid fa-box-open"></i>Loading…</div>
          </div>
        </div>
      </div> -->

      <!-- Recent patients -->
      <!-- <div class="card mb-8">
        <div class="card-header">
          <div class="card-title"><i class="fa-solid fa-users" style="color:var(--color-primary);margin-right:var(--sp-2)"></i>Recent Patients</div>
          <a href="pages/patients.php?action=new" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Add Patient</a>
        </div>
        <div id="recent-patients-container">
          <div class="table-empty"><i class="fa-solid fa-user-clock"></i>Loading patients…</div>
        </div>
      </div> -->

      <!-- Quick actions -->
      <!-- <div class="card">
        <div class="card-header"><div class="card-title">Quick Actions</div></div>
        <div class="flex flex-align gap-4" style="flex-wrap:wrap">
          <a href="pages/patients.php?action=new"      class="btn btn-secondary"><i class="fa-solid fa-user-plus"></i> New Patient</a>
          <a href="pages/appointments.php?action=new"  class="btn btn-secondary"><i class="fa-solid fa-calendar-plus"></i> Book Appointment</a>
          <a href="pages/invoices.php?action=new"      class="btn btn-secondary"><i class="fa-solid fa-file-circle-plus"></i> Create Invoice</a>
          <a href="pages/stock.php?action=receive"     class="btn btn-secondary"><i class="fa-solid fa-boxes-stacked"></i> Receive Stock</a>
          <a href="pages/reports.php"                  class="btn btn-secondary"><i class="fa-solid fa-chart-pie"></i> Reports</a>
        </div>
      </div> -->

    </div>
  </main>
</div>

<!-- Reusable confirm modal -->
<div class="modal-backdrop" id="confirm-modal">
  <div class="modal modal-sm">
    <div class="modal-header">
      <div class="modal-title">Confirm Action</div>
      <button class="modal-close" data-close-modal="confirm-modal">&#x2715;</button>
    </div>
    <div class="modal-body"><p class="confirm-message">Are you sure?</p></div>
    <div class="modal-footer">
      <button class="btn btn-ghost" id="confirm-cancel">Cancel</button>
      <button class="btn btn-danger" id="confirm-ok">Confirm</button>
    </div>
  </div>
</div>

<div id="toast-container"></div>
<div id="global-loader"><div class="loader-spinner"></div><div class="loader-text">Please wait…</div></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="assets/js/app.js"></script>
<script>
$(document).ready(function () {

  /* 1. Check auth */
  App.auth.check();

  /* 2. Stats */
  // App.ajax({
  //   url: '/dashboard/stats.php', loader: false, silent: true,
  //   onSuccess: function(d) {
  //     if (!d) return;
  //     $('#stat-patients').text(d.total_patients || 0);
  //     $('#stat-appointments').text(d.todays_appointments || 0);
  //     $('#stat-pending').text(d.pending_appointments || 0);
  //     $('#stat-stock').text(d.low_stock_count || 0);
  //     $('#stat-revenue').text(App.utils.formatCurrency(d.revenue_this_month || 0));
  //   }
  // });

  


 

  
  /* 6. User info */
  App.ajax({
    url: '/auth/check.php', loader: false, silent: true,
    onSuccess: function(d) {
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
