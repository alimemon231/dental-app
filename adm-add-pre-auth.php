<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Pre-Auth Lifecycle Manager</title>
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
      border: 1px solid #cbd5e1 !important;
      border-radius: var(--radius-md) !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
      line-height: 36px !important;
      padding-left: var(--sp-3) !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
      height: 36px !important;
    }

    /* Table Control Filter Board Layout */
    .table-controls-container {
      background: #ffffff;
      padding: var(--sp-5);
      border-radius: var(--radius-md) var(--radius-md) 0 0;
      border: 1px solid #e2e8f0;
      border-bottom: none;
      margin-top: var(--sp-4);
    }

    .filter-grid-layout {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) 120px;
      gap: var(--sp-3);
      align-items: flex-end;
      width: 100%;
    }

    .filter-group {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .filter-label {
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      color: #64748b;
      letter-spacing: 0.5px;
    }

    .filter-control {
      height: 38px;
      width: 100%;
      padding: 0 var(--sp-3);
      border: 1px solid #cbd5e1;
      border-radius: var(--radius-md);
      font-size: 0.9rem;
      color: #334155;
      background-color: #fff;
      transition: border-color 0.15s ease;
    }

    .filter-control:focus {
      outline: none;
      border-color: var(--color-primary, #2563eb);
    }

    .btn-refresh-control {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      background: #f8fafc;
      color: #475569;
      border: 1px solid #cbd5e1;
      height: 38px;
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
    }

    .btn-refresh-control:active {
      transform: scale(0.97);
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
            <h1>Pre-Auth Lifecycle Tracking (Admin)</h1>
            <div class="page-header-sub">Global administrative cross-clinic matrix authorization monitor controls.</div>
          </div>
          <div class="page-header-actions">
            <button type="button" class="btn btn-primary" id="btn-add-preauth">
              <i class="fa-solid fa-file-medical"></i> New Pre-Auth
            </button>
          </div>
        </div>

        <div class="table-controls-container">
          <form id="admin-filter-form" class="filter-grid-layout" onsubmit="return false;">

            <div class="filter-group">
              <label class="filter-label">Patient Name</label>
              <input type="text" id="filter-patient-name" class="filter-control" placeholder="Search patient...">
            </div>

            <div class="filter-group">
              <label class="filter-label">Clinic Office Site</label>
              <select id="filter-office-id" class="filter-control">
                <option value="">All Clinic Locations</option>
              </select>
            </div>

            <div class="filter-group">
              <label class="filter-label">Pipeline Status</label>
              <select id="filter-status" class="filter-control">
                <option value="">All Statuses</option>
                <option value="Requested">Requested</option>
                <option value="Sent">Sent</option>
                <option value="Processing">Processing</option>
                <option value="Approved">Approved</option>
                <option value="Appealed">Appealed</option>
                <option value="Scheduled">Scheduled</option>
                <option value="Completed">Completed</option>
                <option value="Expired">Expired</option>
                <option value="Rejected">Rejected</option>
              </select>
            </div>

            <div class="filter-group">
              <label class="filter-label">Case File No.</label>
              <input type="number" id="filter-case-id" class="filter-control" placeholder="e.g. 280" min="1">
            </div>

            <button type="button" id="btn-refresh-table" class="btn-refresh-control" title="Reload Pipeline Matrix">
              <i class="fa-solid fa-rotate"></i> <span>Refresh</span>
            </button>

          </form>
        </div>

        <div class="table-wrapper">
          <table class="data-table" id="preauth-table">
            <thead>
              <tr>
                <th class="sortable">Case ID</th>
                <th class="sortable">Patient Name</th>
                <th>Insurance Plan</th>
                <th>Treatment(s) Spanned</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="preauth-tbody">
              <tr>
                <td colspan="6">
                  <div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Initializing admin telemetry pipeline...</div>
                </td>
              </tr>
            </tbody>
          </table>
          <div class="table-pagination" id="preauth-pagination">
            <span id="preauth-info" class="text-muted text-sm">—</span>
            <div class="pagination" id="pagination-btns"></div>
          </div>
        </div>

      </div>
    </main>
  </div>

  <div class="modal-backdrop" id="preauth-modal">
    <div class="modal modal-lg">
      <div class="modal-header">
        <div class="modal-title" id="preauth-modal-title">Add New Pre-Auth</div>
        <button type="button" class="modal-close" data-close-modal="preauth-modal">&#x2715;</button>
      </div>
      <div class="modal-body">
        <form id="preauth-form" novalidate>
          <input type="hidden" name="preauth_id" id="preauth-id" value="">

          <div class="form-section">
            <div class="form-section-title"><i class="fa-solid fa-hospital"></i> Workspace Scope Mapping</div>

            <div class="form-row" style="grid-template-columns: 1fr;">
              <div class="form-group">
                <label class="form-label">Clinic Location Site <span class="required">*</span></label>
                <select name="office_id" id="office-select" class="form-control" required>
                  <option value="">Select clinic site context setup...</option>
                </select>
                <span class="form-error">Please designate the target clinic workspace before proceeding.</span>
              </div>
            </div>

            <div class="form-section-title" style="margin-top:20px;"><i class="fa-solid fa-user-shield"></i> Patient & Insurance Profile Context</div>

            <div class="form-row" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
              <div class="form-group">
                <label class="form-label">Select Patient Record <span class="required">*</span></label>
                <select name="patient_id" id="patient-select" class="form-control" required style="width: 100%;">
                  <option value="">Search names (Select Clinic first)...</option>
                </select>
                <span class="form-error">Please designate a valid patient profile.</span>
              </div>

              <div class="form-group">
                <label class="form-label">Insurance Plan Option <span class="required">*</span></label>
                <select name="p_insurance_plan" id="p_insurance_plan" class="form-control" required>
                  <option value="">Select active plan...</option>
                </select>
                <span class="form-error">Please select a validated plan profile mapping.</span>
              </div>

              <div class="form-group">
                <label class="form-label">Assigned Attending Provider <span class="required">*</span></label>
                <select name="doctor_id" id="provider" class="form-control" required>
                  <option value="">Select clinician doctor...</option>
                </select>
                <span class="form-error">Please select an accountable treating provider.</span>
              </div>

              <div class="form-group">
                <label class="form-label"> Request Date </label>
                <input type="date" class="form-control" id="request_date" name="request_date">
              </div>
            </div>

            <div class="form-section-title" style="margin-top:25px; display: flex; justify-content: space-between; align-items: center;">
              <span><i class="fa-solid fa-tooth"></i> Treatment Matrix Configuration</span>
              <button type="button" class="btn btn-sm btn-secondary" id="btn-add-treatment-row">
                <i class="fa-solid fa-plus"></i> Add Procedure Row
              </button>
            </div>

            <div id="treatments-container">
              <div class="form-row treatment-row" style="grid-template-columns: 2fr 1fr 40px; gap: var(--sp-3); align-items: flex-end;">
                <div class="form-group">
                  <label class="form-label">Treatment Procedure Code <span class="required">*</span></label>
                  <select name="treatment_type[]" class="form-control treatment-type-select" required>
                    <option value="">Select dental treatment standard...</option>
                  </select>
                  <span class="form-error">Please choose a treatment code.</span>
                </div>

                <div class="form-group">
                  <label class="form-label">Tooth Number Mapping <span class="required">*</span></label>
                  <select name="tooth_numbers[]" class="form-control" required>
                    <option value="">—</option>
                    <?php for ($i = 1; $i <= 32; $i++): ?>
                      <option value="<?php echo $i ?>">Tooth <?php echo $i ?></option>
                    <?php endfor; ?>
                  </select>
                </div>

                <div class="form-group" style="display: flex; justify-content: center;">
                  <button type="button" class="btn-remove-row" style="visibility: hidden;" title="Remove Entry Row">
                    <i class="fa-solid fa-trash-can"></i>
                  </button>
                </div>
              </div>
            </div>

          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-close-modal="preauth-modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="btn-save-preauth">
          <span class="btn-spinner"></span>
          <span class="btn-text">Save Pre-Auth File</span>
        </button>
      </div>
    </div>
  </div>

  <div class="modal-backdrop" id="view-modal">
    <div class="modal modal-lg">
      <div class="modal-header">
        <div class="modal-title">Pre-Authorization Execution Context</div>
        <button type="button" class="modal-close" data-close-modal="view-modal">&#x2715;</button>
      </div>
      <div class="modal-body" id="view-details-body">Loading details…</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-close-modal="view-modal">Close Window</button>
      </div>
    </div>
  </div>

  <div class="modal-backdrop" id="confirm-modal">
    <div class="modal modal-sm">
      <div class="modal-header">
        <div class="modal-title" id="confirm-title">Process Workflow Action</div>
        <button type="button" class="modal-close" data-close-modal="confirm-modal">&#x2715;</button>
      </div>
      <div class="modal-body">
        <div id="confirm-body-content"></div>

        <div id="approval-expiry-container" class="mt-4 p-3 bg-light border-radius-sm" style="display:none; border: 1px solid #e2e8f0;">
          <label class="form-label font-bold text-xs mb-2 d-block">SET APPROVAL EXPIRY DATE</label>
          <input type="date" id="approval-expiry-date" class="form-control">
          <small class="text-muted d-block mt-1">Sets record validity duration window.</small>
        </div>

        <div id="rejection-notes-container" class="mt-4 p-3 bg-light border-radius-sm" style="display:none; border: 1px solid #fee2e2;">
          <label class="form-label font-bold text-xs mb-2 d-block text-danger">REJECTION / DENIAL METRIC REASONING</label>
          <textarea id="rejection-notes" class="form-control" rows="3" placeholder="Provide direct denial feedback explaining why..."></textarea>
          <small class="text-muted d-block mt-1">This context displays instantly downstream inside medical tracking boards.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-close-modal="confirm-modal" id="confirm-cancel">Cancel Operations</button>
        <button type="button" class="btn" id="confirm-ok">Execute Transition</button>
      </div>
    </div>
  </div>

  <div class="modal-backdrop" id="book-modal">
    <div class="modal modal-sm">
      <div class="modal-header">
        <div class="modal-title">Schedule Appointment</div>
        <button type="button" class="modal-close" data-close-modal="book-modal">&#x2715;</button>
      </div>
      <div class="modal-body">
        <form id="book-form" novalidate>
          <input type="hidden" name="id" id="book-preauth-id">

          <div class="form-group">
            <label class="form-label">Appointment Date <span class="required">*</span></label>
            <input type="date" name="appointment_date" id="appointment_date" class="form-control" required>
            <span class="form-error">Please select a valid date and time.</span>
            <small class="text-muted mt-2 d-block">Select the date and time for the treatment.</small>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-close-modal="book-modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="btn-confirm-book">
          <i class="fa-solid fa-calendar-check"></i> Confirm Booking
        </button>
      </div>
    </div>
  </div>

  <div class="modal-backdrop" id="complete-modal">
    <div class="modal modal-sm">
      <div class="modal-header">
        <div class="modal-title">Mark Procedure Done</div>
        <button type="button" class="modal-close" data-close-modal="complete-modal">&#x2715;</button>
      </div>
      <div class="modal-body text-center">
        <div class="mb-4">
          <i class="fa-solid fa-circle-check text-success" style="font-size: 3rem;"></i>
        </div>
        <p>Are you sure you want to mark this procedure as <strong>Completed</strong>?</p>
        <p class="text-muted text-sm">This will finalize the patient journey for this pre-authorization.</p>
        <input type="hidden" id="complete-preauth-id">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-close-modal="complete-modal">No, Cancel</button>
        <button type="button" class="btn btn-primary" id="btn-confirm-complete">
          <i class="fa-solid fa-check-double"></i> Yes, Procedure Done
        </button>
      </div>
    </div>
  </div>

  <div class="modal-backdrop" id="reschedule-modal">
    <div class="modal modal-sm">
      <div class="modal-header">
        <div class="modal-title">Reschedule Patient</div>
        <button type="button" class="modal-close" data-close-modal="reschedule-modal">&#x2715;</button>
      </div>
      <div class="modal-body text-center">
        <div class="mb-4">
          <i class="fa-solid fa-calendar-minus text-warning" style="font-size: 3rem;"></i>
        </div>
        <p>Are you sure you want to <strong>Reschedule</strong> this patient?</p>
        <p class="text-muted text-sm">This will clear the current appointment date and move the record back to the "Book Appointments" list.</p>
        <input type="hidden" id="reschedule-preauth-id">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-close-modal="reschedule-modal">Cancel</button>
        <button type="button" class="btn btn-warning" id="btn-confirm-reschedule">
          <i class="fa-solid fa-rotate-left"></i> Confirm Reschedule
        </button>
      </div>
    </div>
  </div>

  <div class="modal-backdrop" id="modify-status-modal">
  <div class="modal modal-sm">
    <div class="modal-header">
      <div class="modal-title">Modify Pipeline Status</div>
      <button type="button" class="modal-close" data-close-modal="modify-status-modal">&#x2715;</button>
    </div>
    <div class="modal-body">
      <form id="modify-status-form" novalidate onsubmit="return false;">
        <input type="hidden" id="status-preauth-id">
        
        <div class="text-center mb-4">
          <div class="mb-2">
            <i class="fa-solid fa-sliders text-primary" style="font-size: 3rem;"></i>
          </div>
          <p>Update authorization state for this record.</p>
        </div>

        <div class="form-group">
          <label class="form-label">Select New Status <span class="required">*</span></label>
          <select id="update-status-select" class="form-control" required>
            <option value="">Choose new status...</option>
            <option value="Requested">Requested</option>
            <option value="Sent">Sent</option>
            <option value="Approved">Approved</option>
            <option value="Appealed">Appealed</option>
            <option value="Scheduled">Scheduled</option>
            <option value="Completed">Completed</option>
            <option value="Expired">Expired</option>
            <option value="Rejected">Rejected</option>
          </select>
          <span class="form-error">Please choose a valid status destination.</span>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost" data-close-modal="modify-status-modal">Cancel</button>
      <button type="button" class="btn btn-primary" id="btn-confirm-modify-status">
        <i class="fa-solid fa-floppy-disk"></i> Update Status
      </button>
    </div>
  </div>
</div>

  <div id="toast-container"></div>
  <div id="global-loader">
    <div class="loader-spinner"></div>
    <div class="loader-text">Syncing administrative databanks...</div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="assets/js/app.js"></script>
  <script src="assets/js/adm-add-pre-auth.js"></script>
  <script>
    $(document).ready(function () {
      /* Assert Global Admin and Management Gateways explicitly */
      App.auth.check();
      App.auth.role(['admin', 'management']);

      /* Fetch current workspace profile definitions */
      App.ajax({
        url: '/auth/check.php', loader: false, silent: true,
        onSuccess: function (d) {
          if (d && d.user) {
            $('#sidebar-user-name').text(d.user.name);
            $('#sidebar-user-role').text(d.user.role + " Console");
            $('#user-avatar-initial').text(d.user.name.charAt(0).toUpperCase());
          }
        }
      });
    });
  </script>
</body>

</html>