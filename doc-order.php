<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Orders</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/global.css">
  <link rel="stylesheet" href="assets/css/layout.css">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <style>
    .select2 {
      height: 40px;
    }
  </style>
</head>

<body>
  <div class="app-shell">
    <?php require_once "includes/page-header.php" ?>
    <main class="main-content">
      <div class="page-wrapper">
        <div class="page-header">
          <div class="page-header-left">
            <h1>Authorize Order </h1>
            <div class="page-header-sub">Manage all orders here.</div>
          </div>
          <div class="page-header-actions">
          </div>
        </div>

        <!-- ============================================================
     PATIENTS TABLE
============================================================ -->
        <div class="table-wrapper">
          <table class="data-table" id="order-table">
            <thead>
              <tr>
                <th class="sortable" data-col="id">#</th>
                <th class="sortable" data-col="name">Order Number</th>
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
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="assets/js/app.js"></script>
  <script src="assets/js/doc-order.js"></script>
  <script>
    $(document).ready(function () {

      /* 1. Check auth */
      App.auth.check();
      App.auth.role('doctor')

      /* 2. User info */
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

      


    });
  </script>
</body>

</html>