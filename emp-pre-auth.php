<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Pre-Auths</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/global.css">
  <link rel="stylesheet" href="assets/css/layout.css">
  <style>
    /* Styling for the scrollable treatment list */
    .scrollable-select {
      height: 120px !important;
      overflow-y: auto;
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
            <h1>Manage Pre-Auths</h1>
            <div class="page-header-sub">Track and manage patient insurance authorizations.</div>
          </div>
          <div class="page-header-actions">
            <button class="btn btn-primary" id="btn-add-preauth">
              <i class="fa-solid fa-file-medical"></i> New Pre-Auth
            </button>
          </div>
        </div>

        <div class="table-wrapper">
          <table class="data-table" id="preauth-table">
            <thead>
              <tr>
                <th class="sortable">#</th>
                <th class="sortable">Patient Name</th>
                <th>DOB</th>
                <th>Insurance Plan</th>
                <th>Treatment</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="preauth-tbody">
              <tr>
                <td colspan="7">
                  <div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>
                </td>
              </tr>
            </tbody>
          </table>
          <div class="table-pagination" id="preauth-pagination">
            <span id="preauth-info" class="text-muted text-sm">—</span>
            <div class="pagination" id="pagination-btns"></div>
          </div>
        </div>

        <!-- ============================================================
             ADD / EDIT PRE-AUTH MODAL
        ============================================================ -->
        <div class="modal-backdrop" id="preauth-modal">
          <div class="modal modal-lg">
            <div class="modal-header">
              <div class="modal-title" id="preauth-modal-title">Add New Pre-Auth</div>
              <button class="modal-close" data-close-modal="preauth-modal">&#x2715;</button>
            </div>
            <div class="modal-body">
              <form id="preauth-form" novalidate>
                <input type="hidden" name="preauth_id" id="preauth-id" value="">

                <div class="form-section">
                  <div class="form-section-title"><i class="fa-solid fa-user-shield"></i> Patient & Insurance Info</div>
                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label">First Name <span class="required">*</span></label>
                      <input type="text" name="p_first_name" id="p_first_name" class="form-control" placeholder="John"
                        required>
                      <span class="form-error">Required.</span>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Last Name <span class="required">*</span></label>
                      <input type="text" name="p_last_name" id="p_last_name" class="form-control" placeholder="Doe"
                        required>
                      <span class="form-error">Required.</span>
                    </div>
                  </div>

                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label">Date of Birth <span class="required">*</span></label>
                      <input type="date" name="p_dob" id="p_dob" class="form-control" required>
                    </div>

                    <div class="form-group">
                      <label class="form-label">Insurance Plan <span class="required">*</span></label>
                      <select name="p_insurance_plan" id="p_insurance_plan" class="form-control" required>
                        <option value="">Loading plans...</option>
                      </select>
                      <span class="form-error">Please select a plan.</span>
                    </div>

                  </div>

                  <div class="form-section-title" style="margin-top:20px;"><i class="fa-solid fa-tooth"></i> Treatment
                    Details</div>

                  <div class="form-row" style="grid-template-columns: 2fr 1fr;">
                    <div class="form-group">
                      <label class="form-label">Treatment Type <span class="required">*</span></label>
                      <select name="treatment_type" id="treatment_type" class="form-control" required>
                        <option value="">Loading procedures...</option>
                      </select>
                      <span class="form-error">Please select a procedure.</span>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Tooth Number(s) <span class="required">*</span></label>
                      <select name="tooth_numbers" id="tooth_numbers" aria-placeholder="Select Number of teeths" class="form-control" required >
                        <?php
                          for($i = 1 ; $i <=32 ;$i++){

                          ?>
                                <option value="<?php echo $i ?>"><?php echo $i ?></option>
                          <?php
                          }
                        ?>
                      </select>
                    </div>
                  </div>
                </div>
              </form>
            </div>
            <div class="modal-footer">
              <button class="btn btn-ghost" data-close-modal="preauth-modal">Cancel</button>
              <button class="btn btn-primary" id="btn-save-preauth">
                <span class="btn-spinner"></span>
                <span class="btn-text">Save Pre-Auth</span>
              </button>
            </div>
          </div>
        </div>

        <!-- VIEW MODAL -->
        <div class="modal-backdrop" id="view-preauth-modal">
          <div class="modal modal-lg">
            <div class="modal-header">
              <div class="modal-title">Pre-Auth Details</div>
              <button class="modal-close" data-close-modal="view-preauth-modal">&#x2715;</button>
            </div>
            <div class="modal-body" id="view-preauth-body">Loading…</div>
            <div class="modal-footer">
              <button class="btn btn-ghost" data-close-modal="view-preauth-modal">Close</button>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <div id="toast-container"></div>
  <div id="global-loader">
    <div class="loader-spinner"></div>
    <div class="loader-text">Please wait…</div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="assets/js/app.js"></script>
  <script src="assets/js/emp-pre-auth.js"></script>
  <script>
    $(document).ready(function () {
      /* 1. Check auth - Role is now STAFF */
      App.auth.check();
      App.auth.role('staff');

      /* 2. User info UI update */
      App.ajax({
        url: '/auth/check.php', loader: false, silent: true,
        onSuccess: function (d) {
          if (d && d.user) {
            $('#sidebar-user-name').text(d.user.name);
            $('#sidebar-user-role').text(d.user.role);
            $('#user-avatar-initial').text(d.user.name.charAt(0).toUpperCase());
          }
        }
      });
    });
  </script>
</body>

</html>