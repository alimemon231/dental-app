<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lab Case Types — Dental App</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/global.css">
  <link rel="stylesheet" href="assets/css/layout.css">
  <style>
    /* Two-Column Grid */
    .admin-grid {
      display: grid;
      grid-template-columns: 350px 1fr;
      gap: 30px;
      align-items: start;
    }

    @media (max-width: 992px) {
      .admin-grid { grid-template-columns: 1fr; }
    }

    .sticky-side {
      position: sticky;
      top: 20px;
      background: var(--bg-surface);
      padding: 20px;
      border-radius: var(--radius-md);
      border: 1px solid var(--border-color);
    }

    /* Status Badges */
    .status-badge {
      font-size: 0.75rem;
      padding: 2px 8px;
      border-radius: 12px;
      font-weight: 600;
      text-transform: uppercase;
    }
    
    .status-active { background: #e3fcef; color: #0a7d45; }
    .status-deactive { background: #fbeae9; color: #c92a2a; }

    .row-deactivated {
      opacity: 0.6;
      background-color: var(--bg-muted);
      font-style: italic;
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
            <h1>Lab Case Types</h1>
            <div class="page-header-sub">Define the types of dental lab cases (e.g. Crown, Denture, Bridge).</div>
          </div>
        </div>

        <div class="admin-grid">
          <div class="sticky-side">
            <div class="form-section-title mb-4">
                <i class="fa-solid fa-flask"></i> <span id="form-title">Add New Case Type</span>
            </div>
            
            <form id="lab-case-form" novalidate>
              <input type="hidden" name="case_id" id="case-id" value="">
              
              <div class="form-group mb-4">
                <label class="form-label">Case Name <span class="required">*</span></label>
                <input type="text" name="name" id="name" class="form-control" placeholder="e.g. PFM Crown" required>
                <span class="form-error">Case name is required.</span>
              </div>

              <div class="form-group mb-4">
                <label class="form-label">Target Area <span class="required">*</span></label>
                <select name="target" id="target" class="form-control" required>
                    <option value="" selected disabled>Select Target...</option>
                    <option value="teeth">Teeth (Specific Units)</option>
                    <option value="arch">Arch (Full Jaw)</option>
                </select>
                <span class="form-error">Please select a target area.</span>
              </div>

              <div class="form-group mb-4">
                <label class="form-label"> Price <span class="required">*</span></label>
                <input type="number" name="price" id="price" class="form-control" placeholder="e.g. 23.0" required>
                <span class="form-error">Price is required.</span>
              </div>

              <div class="form-group mb-4" id="status-group" style="display: none;">
                <label class="form-label">Status</label>
                <select name="status" id="status" class="form-control">
                  <option value="active">Active</option>
                  <option value="deactive">Deactive</option>
                </select>
              </div>

              <button class="btn btn-primary w-full" id="btn-save-case" style="width: 100%;">
                <span class="btn-spinner"></span>
                <span class="btn-text">Add Case Type</span>
              </button>
              
              <button type="button" class="btn btn-ghost w-full mt-2" id="btn-cancel-edit" style="width: 100%; display:none;">
                Cancel Edit
              </button>
            </form>
          </div>

          <div class="table-wrapper">
            <table class="data-table" id="lab-case-table">
              <thead>
                <tr>
                  <th width="50">#</th>
                  <th>Case Name</th>
                  <th>Target</th>
                   <th>Price</th>
                  <th width="100">Status</th>
                  <th width="120">Actions</th>
                </tr>
              </thead>
              <tbody id="lab-case-tbody">
                <tr>
                  <td colspan="5">
                    <div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading lab cases...</div>
                  </td>
                </tr>
              </tbody>
            </table>
            
            <div class="table-pagination" id="lab-case-pagination">
              <span id="lab-case-info" class="text-muted text-sm">—</span>
              <div class="pagination" id="pagination-btns"></div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <div class="modal-backdrop" id="confirm-modal">
    <div class="modal modal-sm">
      <div class="modal-header">
        <div class="modal-title">Confirm Deactivation</div>
        <button class="modal-close" data-close-modal="confirm-modal">&#x2715;</button>
      </div>
      <div class="modal-body">
        <p class="confirm-message">Deactivating this case type will prevent it from being used in new lab orders.</p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-ghost" id="confirm-cancel">Cancel</button>
        <button class="btn btn-danger" id="confirm-ok">Confirm Deactivate</button>
      </div>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="assets/js/app.js"></script>
  <script src="assets/js/lab-cases.js"></script>
  <script>
    $(document).ready(function () {
      App.auth.check();
      App.auth.role('admin');

      $('#btn-cancel-edit').on('click', function() {
          $('#case-id').val('');
          $('#lab-case-form')[0].reset();
          $('#status-group').hide();
          $('#form-title').html('<i class="fa-solid fa-flask"></i> Add New Case Type');
          $('#btn-save-case .btn-text').text('Add Case Type');
          $(this).hide();
      });
    });
  </script>
</body>
</html>