<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Offices</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/global.css">
  <link rel="stylesheet" href="assets/css/layout.css">
</head>

<body>
  <div class="app-shell">
    <?php require_once "includes/page-header.php" ?>
    <main class="main-content">
      <div class="page-wrapper">
        <div class="page-header">
          <div class="page-header-left">
            <h1>Dental Offices</h1>
            <div class="page-header-sub">Manage all Dental Offices.</div>
          </div>
          <div class="page-header-actions">
            <button class="btn btn-primary" id="btn-add-patient">
              <i class="fa-solid fa-hospital"></i> Add Office
            </button>
          </div>
        </div>

        <!-- ============================================================
     PATIENTS TABLE
============================================================ -->
        <div class="table-wrapper">
          <table class="data-table" id="patients-table">
            <thead>
              <tr>
                <th class="sortable" data-col="id">#</th>
                <th class="sortable" data-col="name">Office Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Full Office Address</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="patients-tbody">
              <tr>
                <td colspan="8">
                  <div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>
                </td>
              </tr>
            </tbody>
          </table>
          <div class="table-pagination" id="patients-pagination">
            <span id="patients-info" class="text-muted text-sm">—</span>
            <div class="pagination" id="pagination-btns"></div>
          </div>
        </div>

        <!-- ============================================================
     ADD / EDIT OFFICE MODAL
============================================================ -->
        <div class="modal-backdrop" id="patient-modal">
          <div class="modal modal-lg">
            <div class="modal-header">
              <div class="modal-title" id="patient-modal-title">Add New Office</div>
              <button class="modal-close" data-close-modal="patient-modal">&#x2715;</button>
            </div>
            <div class="modal-body">
              <form id="patient-form" novalidate>
                <input type="hidden" name="patient_id" id="patient-id" value="">

                <!-- Personal Info -->
                <div class="form-section">
                  <div class="form-section-title"><i class="fa-solid fa-hospital"></i> Office Information</div>
                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label">Office Name <span class="required">*</span></label>
                      <input type="text" name="name" id="name" class="form-control" placeholder="e.g. Office 1"
                        required>
                      <span class="form-error">Office name is required.</span>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Phone Number <span class="required">*</span></label>
                      <input type="phone" name="phone" id="phone" class="form-control" placeholder="+1 453-545-345"
                        required>
                      <span class="form-error">Phone Number is required.</span>
                    </div>
                  </div>
                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label">Email Adress <span class="required">*</span></label>
                      <input type="email" name="email" id="email" class="form-control" placeholder="e.g office@mail.com"
                        required>
                      <span class="form-error">Email is required</span>
                    </div>

                    <div class="form-group">
                      <label class="form-label">Full Address <span class="required">*</span></label>
                      <input type="text" name="address" id="address" class="form-control"
                        placeholder="e.g block 3 new street newyork" required>
                      <span class="form-error">Address is required.</span>
                    </div>
                  </div>
                </div>
              </form>
            </div>
            <div class="modal-footer">
              <button class="btn btn-ghost" data-close-modal="patient-modal">Cancel</button>
              <button class="btn btn-primary" id="btn-save-patient">
                <span class="btn-spinner"></span>
                <span class="btn-text">Save Office</span>
              </button>
            </div>
          </div>
        </div>

        <!-- ============================================================
     VIEW PATIENT MODAL
============================================================ -->
        <div class="modal-backdrop" id="view-patient-modal">
          <div class="modal modal-lg xl">
            <div class="modal-header">
              <div class="modal-title">Office Details</div>
              <button class="modal-close" data-close-modal="view-patient-modal">&#x2715;</button>
            </div>
            <div class="modal-body">
              <div class="grid-2" style="gap:var(--sp-8)">
                <div id="view-office-details">

                </div>
                <div id="view-attached-doctors">
                  <div class="form-section-title mb-4"><i class="fa-solid fa-user"></i> Assigened Doctors</div>
                  <div class="form-group">
                    <label class="form-label">Select Doctor</label>
                    <select name="name" id="select-d-t-a" class="form-control" placeholder="Select Doctor">
                      <option value="null" disabled selected> Select Doctor</option>
                    </select>
                  </div>
                  <div class="table-wrapper">
                    <table class="data-table" id="assigened-doctors">
                      <thead>
                        <tr>
                          <th class="sortable" data-col="id">#</th>
                          <th class="sortable" data-col="name">Doctor Name</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody id="assigened-doc-tbody">
                        <tr>
                          <td colspan="8">
                            <div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

              <div class="grid-1">
                <div class="form-section-title mb-4"><i class="fa-solid fa-user"></i> Assigened Staff </div>
                <div class="form-group">
                  <label class="form-label">Select Staff</label>
                  <select name="name" id="select-s-t-a" class="form-control" placeholder="Select Staff">
                    <option value="null" disabled selected> Select Staff</option>
                  </select>
                </div>
                <div class="table-wrapper">
                  <table class="data-table" id="assigened-doctors">
                    <thead>
                      <tr>
                        <th class="sortable" data-col="id">#</th>
                        <th class="sortable" data-col="name">Staff Name</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody id="assigened-staff-tbody">
                      <tr>
                        <td colspan="8">
                          <div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-ghost" data-close-modal="view-patient-modal">Close</button>
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
  <script src="assets/js/app.js"></script>
  <script src="assets/js/offices.js"></script>
  <script>
    $(document).ready(function () {

      /* 1. Check auth */
      App.auth.check();
      App.auth.role('admin')

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