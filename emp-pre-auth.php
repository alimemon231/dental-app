<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Pre-Auths</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/global.css">
  <link rel="stylesheet" href="assets/css/layout.css">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <style>
    .scrollable-select {
      height: 120px !important;
      overflow-y: auto;
    }

    /* Treatment repeat layout row tuning */
    .treatment-row {
      border-bottom: 1px dashed var(--color-border);
      padding-bottom: var(--sp-4);
      margin-bottom: var(--sp-4);
    }

    .treatment-row:last-child {
      border-bottom: none;
      padding-bottom: 0;
      margin-bottom: 0;
    }

    .btn-remove-row {
      background: #fee2e2;
      color: #ef4444;
      border: 1px solid #fca5a5;
      border-radius: var(--radius-md);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      height: 38px;
      width: 38px;
      transition: all 0.2s ease;
    }

    .btn-remove-row:hover {
      background: #fecaca;
      color: #dc2626;
    }

    /* Sync Select2 with custom form engine styling */
    .select2-container--default .select2-selection--single {
      height: 38px !important;
      border: 1px solid var(--color-border) !important;
      border-radius: var(--radius-md) !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
      line-height: 36px !important;
      padding-left: var(--sp-3) !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
      height: 36px !important;
    }

    /* Custom Row Highlighting Variants for Pre-Authorization Tracking Grid Tables */
    .alert-warning-row {
      background-color: #eabd0c !important;
      /* Soft pastel amber warning context tint */
    }

    .alert-warning-row:hover {
      background-color: #fef3c7 !important;
    }

    .alert-danger-row {
      background-color: #f3c1c1 !important;
      /* Soft pastel red danger context tint */
    }

    .alert-danger-row:hover {
      background-color: #fee2e2 !important;
    }

    /* ================================================================
    TABLE CONTROL HEADER ACTIONS STYLING MATRIX
================================================================ */
    .table-controls-container {
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: #ffffff;
      padding: var(--sp-4) var(--sp-5);
      border-radius: var(--radius-md) var(--radius-md) 0 0;
      border: 1px solid #e2e8f0;
      border-bottom: none;
      margin-top: var(--sp-4);
    }

    .table-controls-container .section-title {
      margin: 0;
      font-size: 1.1rem;
      font-weight: 700;
      color: #1e293b;
    }

    /* Custom Interactive Micro-Control Buttons Definition Layout */
    .btn-refresh-control {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: #f8fafc;
      color: #475569;
      border: 1px solid #cbd5e1;
      padding: 7px 14px;
      font-size: 0.85rem;
      font-weight: 600;
      border-radius: var(--radius-md);
      cursor: pointer;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .btn-refresh-control:hover {
      background: #f1f5f9;
      color: var(--color-primary, #2563eb);
      border-color: #94a3b8;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .btn-refresh-control:active {
      transform: scale(0.97);
      background: #e2e8f0;
    }

    .btn-refresh-control i {
      font-size: 0.9rem;
      transition: transform 0.15s ease;
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

        <div class="table-controls-container">
          <div class="controls-right">
            <button type="button" id="btn-refresh-table" class="btn-refresh-control"
              title="Hot Reload Live Records Pipeline">
              <i class="fa-solid fa-rotate"></i> <span>Refresh Data</span>
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
                <th>Treatment(s)</th>
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
                      <label class="form-label">Select Patient Record <span class="required">*</span></label>
                      <select name="patient_id" id="patient-select" class="form-control" required style="width: 100%;">
                        <option value="">Search by patient name...</option>
                      </select>
                      <span class="form-error">Please designate a valid patient profile.</span>
                    </div>

                    <div class="form-group">
                      <label class="form-label">Insurance Plan <span class="required">*</span></label>
                      <select name="p_insurance_plan" id="p_insurance_plan" class="form-control" required>
                        <option value="">Loading plans...</option>
                      </select>
                      <span class="form-error">Please select a plan.</span>
                    </div>

                    <div class="form-group">
                      <label class="form-label">Doctor (Provider)<span class="required">*</span></label>
                      <select name="provider" id="provider" class="form-control" required>
                        <option value="">Loading Doctors...</option>
                      </select>
                      <span class="form-error">Please select a plan.</span>
                    </div>
                  </div>

                  <div class="form-section-title"
                    style="margin-top:20px; display: flex; justify-content: space-between; align-items: center;">
                    <span><i class="fa-solid fa-tooth"></i> Treatment Matrix Details</span>
                    <button type="button" class="btn btn-sm btn-secondary" id="btn-add-treatment-row">
                      <i class="fa-solid fa-plus"></i> Add Procedure Row
                    </button>
                  </div>

                  <div id="treatments-container">
                    <div class="form-row treatment-row"
                      style="grid-template-columns: 2fr 1fr 40px; gap: var(--sp-3); align-items: flex-end;">
                      <div class="form-group">
                        <label class="form-label">Treatment Type <span class="required">*</span></label>
                        <select name="treatment_type[]" class="form-control treatment-type-select" required>
                          <option value="">Loading procedures...</option>
                        </select>
                        <span class="form-error">Please select a procedure.</span>
                      </div>

                      <div class="form-group">
                        <label class="form-label">Tooth Number <span class="required">*</span></label>
                        <select name="tooth_numbers[]" class="form-control" required>
                          <?php for ($i = 1; $i <= 32; $i++): ?>
                            <option value="<?php echo $i ?>"><?php echo $i ?></option>
                          <?php endfor; ?>
                        </select>
                      </div>

                      <div class="form-group" style="display: flex; justify-content: center;">
                        <button type="button" class="btn-remove-row" style="visibility: hidden;"
                          title="Delete Procedure">
                          <i class="fa-solid fa-trash-can"></i>
                        </button>
                      </div>
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

        <div class="modal-backdrop" id="confirm-modal">
          <div class="modal modal-sm">
            <div class="modal-header">
              <div class="modal-title" id="confirm-title">Confirm Action</div>
              <button class="modal-close" data-close-modal="confirm-modal">&#x2715;</button>
            </div>
            <div class="modal-body">
              <div id="confirm-body-content"></div>

              <div id="approval-expiry-container" class="mt-4 p-3 bg-light border-radius-sm"
                style="display:none; border: 1px solid #ddd;">
                <label class="form-label font-bold text-xs mb-2 d-block">SET APPROVAL EXPIRY
                  DATE</label>
                <input type="date" id="approval-expiry-date" class="form-control">
                <small class="text-muted d-block mt-1">This date will be saved with the approval
                  record.</small>
              </div>

              <div id="rejection-notes-container" class="mt-4 p-3 bg-light border-radius-sm"
                style="display:none; border: 1px solid #ddd;">
                <label class="form-label font-bold text-xs mb-2 d-block text-danger">REJECTION NOTES
                  (OPTIONAL)</label>
                <textarea id="rejection-notes" class="form-control" rows="3"
                  placeholder="Provide details/reasoning for the staff member..."></textarea>
                <small class="text-muted d-block mt-1">These notes will be displayed to staff explaining
                  the denial reason.</small>
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-ghost" data-close-modal="confirm-modal" id="confirm-cancel">Cancel</button>
              <button class="btn" id="confirm-ok">Proceed</button>
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
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="assets/js/app.js"></script>
  <script src="assets/js/emp-pre-auth.js"></script>
  <script>
    $(document).ready(function () {
      /* 1. Check auth - Role is now STAFF */
      App.auth.check();
      App.auth.role(['staff', 'doctor']);

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