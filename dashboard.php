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

        <?php
        if ($currentUser['role'] != 'admin') {
          ?>
          <!-- Stat cards -->
          <div class="grid-2 mb-8">
            <div class="stat-card">
              <div class="stat-icon primary"><i class="fa-solid fa-dollar"></i></div>
              <div class="stat-body">
                <div class="stat-value" id="stat-patients">—</div>
                <div class="stat-label">This Month Budget</div>
                <div class="stat-trend up" id="dashboard-budget">

                  <div class="budget-progress-container" style="width:500px;">
                    <div class="d-flex justify-content-between mb-1">
                      <span class="amt-spent">$0.00</span>
                      <span class="amt-total text-muted">/ 0.00</span>
                    </div>
                    <div class="progress-track">
                      <div class="progress-fill status-green" style="width: 0%"></div>
                    </div>
                    <div class="percentage-label">0% Utilized</div>
                  </div>


                </div>
              </div>
            </div>

            <div class="stat-card">
              <div class="stat-icon primary"><i class="fa-solid fa-shopping-bag"></i></div>
              <div class="stat-body">
                <div class="stat-value" id="stat-patients">—</div>
                <div class="stat-label">Pending Orders</div>
                <div class="stat-trend up">
                  <h3 id="pending-orders-dasboard"></h3>
                </div>
              </div>
            </div>
          </div>

          <?php
        } else {
          ?>
          <div class="table-wrapper">
            <table class="data-table" id="order-table">
              <thead>
                <tr>
                  <th class="sortable" data-col="id">#</th>
                  <th class="sortable" data-col="name">Order Number</th>
                  <th class="sortable" data-col="name">From Office </th>
                  <th class="sortable" data-col="name">Order date</th>
                  <th class="sortable" data-col="name">Delivery date</th>
                  <th class="sortable" data-col="name">Order By</th>
                  <th class="sortable" data-col="name">Approved By</th>
                  <th>Order Amount</th>
                  <th>status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="order-tbody">
                <tr>
                  <td colspan="8">
                    <div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>
                  </td>
                </tr>
              </tbody>
            </table>
            <div class="table-pagination" id="order-pagination">
              <span id="patients-info" class="text-muted text-sm">—</span>
              <div class="pagination" id="pagination-btns"></div>
            </div>
          </div>

          <!-- ============================================================
     VIEW PATIENT MODAL
============================================================ -->
          <div class="modal-backdrop" id="view-order-modal">
            <div class="modal modal-lg xl">
              <div class="modal-header">
                <div class="modal-title">Order Details</div>
                <button class="modal-close" data-close-modal="view-order-modal">&#x2715;</button>
              </div>
              <div class="modal-body">
                <div class="grid-2" style="gap:var(--sp-8)">
                  <div id="view-order-details">

                  </div>
                  <div>
                    <div class="form-section-title mb-4"><i class="fa-solid fa-pills"></i> Order Items </div>
                    <div class="table-wrapper">
                      <table class="data-table">
                        <thead>
                          <tr>

                            <th class="sortable" data-col="name">Name</th>
                            <th>Qty</th>
                            <th>Price </th>
                            <th>Sub Total</th>
                          </tr>
                        </thead>
                        <tbody id="order-items-tbody">
                          <tr>
                            <td colspan="8">
                              <div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                      <div class="table-pagination" id="order-pagination">
                        <div class="pagination"> Total : <span id="order-grand-total-display"></span></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button class="btn btn-ghost" data-close-modal="view-order-modal">Close</button>
                <button class="btn btn-primary" id="btn-edit-from-view">
                  <i class="fa-solid fa-pen"></i> Edit
                </button>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <?php
        }
        ?>
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
  <script src="assets/js/app.js"></script>
  <?php
  if ($currentUser['role'] == 'admin') {
    ?>
    <script src="assets/js/admin-dashboard.js"></script>
    <?php
  }
  ?>
  <script>
    $(document).ready(function () {

      /* 1. Check auth */
      App.auth.check();

      /* 2. Stats */
      function loadBudgetWidget() {
        App.ajax({
          url: '/dashboard/stats.php',
          method: 'GET',
          onSuccess: function (data) {
            var budget = data.budget;
            var expense = data.expense;
            var percentage = data.percentage;
            var statusClass = data.status_class;

            var html =
              '<div class="budget-progress-container" style="width:500px;">' +
              '<div class="d-flex justify-content-between mb-1">' +
              '<div>' +
              '<span class="fw-600">$' + expense.toLocaleString() + '</span>' +
              '<span class="text-muted text-sm"> / $' + budget.toLocaleString() + '</span>' +
              '</div>' +
              '<span class="text-sm ' + (percentage > 100 ? 'text-danger' : '') + '">' + percentage + '%</span>' +
              '</div>' +
              '<div class="progress-track" style="height:8px; background:#eee; border-radius:10px; overflow:hidden;">' +
              '<div class="progress-fill ' + statusClass + '" style="width: ' + Math.min(percentage, 100) + '%; height:100%;"></div>' +
              '</div>' +
              '<div class="mt-2 text-xs text-muted">Monthly Spending Limit (' + data.month_name + ')</div>' +
              '</div>';

            $('#dashboard-budget').html(html);
            $("#pending-orders-dasboard").html(data.pending_order);
          }
        });
      }


      /* 6. User info */
      App.ajax({
        url: '/auth/check.php', loader: false, silent: true,
        onSuccess: function (d) {
          if (d && d.user) {
            $('#sidebar-user-name').text(d.user.name);
            $('#sidebar-user-role').text(d.user.role || 'Staff');
            $('#user-avatar-initial').text(d.user.name.charAt(0).toUpperCase());
          }
        }
      });

      <?php
      if ($currentUser['role'] != 'admin') {
        ?>
        loadBudgetWidget()
        <?php
      }
      ?>

    });
  </script>
</body>

</html>