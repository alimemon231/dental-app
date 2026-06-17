<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Procedures — Dental App</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/global.css">
  <link rel="stylesheet" href="assets/css/layout.css">
  <style>
    /* WordPress Style Two-Column Layout */
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

    /* Deactivated Row Style */
    .row-deactivated {
      opacity: 0.6;
      background-color: var(--bg-muted);
      font-style: italic;
    }
    
    .status-badge {
      font-size: 0.75rem;
      padding: 2px 8px;
      border-radius: 12px;
      font-weight: 600;
      text-transform: uppercase;
    }
    
    .status-active { background: #e3fcef; color: #0a7d45; }
    .status-deactive { background: #fbeae9; color: #c92a2a; }
  </style>
</head>

<body>
  <div class="app-shell">
    <?php require_once "includes/page-header.php" ?>
    <main class="main-content">
      <div class="page-wrapper">
        <div class="page-header">
          <div class="page-header-left">
            <h1>Procedures</h1>
            <div class="page-header-sub">Manage dental services and treatment types.</div>
          </div>
        </div>

        <div class="admin-grid">
          <!-- LEFT SIDE: ADD/EDIT FORM -->
          <div class="sticky-side">
            <div class="form-section-title mb-4"><i class="fa-solid fa-tooth"></i> <span id="form-title">Add New Procedure</span></div>
            <form id="procedure-form" novalidate>
              <input type="hidden" name="procedure_id" id="procedure-id" value="">
              
              <div class="form-group mb-4">
                <label class="form-label">Procedure Name <span class="required">*</span></label>
                <input type="text" name="name" id="name" class="form-control" placeholder="e.g. Root Canal Therapy" required>
                <span class="form-error">Procedure name is required.</span>
              </div>

               <div class="form-group mb-4">
                <label class="form-label">Price</label>
                <input type="number" name="price" id="price" class="form-control" placeholder="e.g. 56.87 etc" >
                <span class="form-error">Procedure Price is required.</span>
              </div>


              <div class="form-group mb-4">
                <label class="form-label">Description (Optional)</label>
                <textarea name="description" id="description" class="form-control" placeholder="Brief details about the service..." rows="3"></textarea>
              </div>

              <!-- Status Toggle: Only visible during Edit -->
              <div class="form-group mb-4" id="status-group" style="display: none;">
                <label class="form-label">Availability Status</label>
                <select name="status" id="status" class="form-control">
                  <option value="active">Active (Available)</option>
                  <option value="deactive">Deactive (Hidden)</option>
                </select>
              </div>

              <button class="btn btn-primary w-full" id="btn-save-procedure" style="width: 100%;">
                <span class="btn-spinner"></span>
                <span class="btn-text">Add New Procedure</span>
              </button>
              <button type="button" class="btn btn-ghost w-full mt-2" id="btn-cancel-edit" style="width: 100%; display:none;">Cancel Edit</button>
            </form>
          </div>

          <!-- RIGHT SIDE: TABLE -->
          <div class="table-wrapper">
            <table class="data-table" id="procedures-table">
              <thead>
                <tr>
                  <th width="50">#</th>
                  <th>Procedure Name</th>
                  <th>Price</th>
                  <th>Description</th>
                  <th width="100">Status</th>
                  <th width="120">Actions</th>
                </tr>
              </thead>
              <tbody id="procedures-tbody">
                <tr>
                  <td colspan="5">
                    <div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>
                  </td>
                </tr>
              </tbody>
            </table>
            <div class="table-pagination" id="procedure-pagination">
              <span id="procedure-info" class="text-muted text-sm">—</span>
              <div class="pagination" id="pagination-btns"></div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Confirmation Modal for Quick Deactivation -->
  <div class="modal-backdrop" id="confirm-modal">
    <div class="modal modal-sm">
      <div class="modal-header">
        <div class="modal-title">Confirm Deactivation</div>
        <button class="modal-close" data-close-modal="confirm-modal">&#x2715;</button>
      </div>
      <div class="modal-body">
        <p class="confirm-message">Deactivating this procedure will hide it from new Pre-Auth requests. Past records will not be affected.</p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-ghost" id="confirm-cancel">Keep Active</button>
        <button class="btn btn-danger" id="confirm-ok">Deactivate Now</button>
      </div>
    </div>
  </div>

  <!-- Toast & Loader -->
  <div id="toast-container"></div>
  <div id="global-loader">
    <div class="loader-spinner"></div>
    <div class="loader-text">Processing…</div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="assets/js/app.js"></script>
  <script src="assets/js/procedures.js"></script>
  <script>
    $(document).ready(function () {
      App.auth.check();
      App.auth.role('admin');

      /* Cancel Edit Logic */
      $('#btn-cancel-edit').on('click', function() {
          $('#procedure-id').val('');
          $('#procedure-form')[0].reset();
          $('#status-group').hide();
          $('#form-title').text('Add New Procedure');
          $('#btn-save-procedure .btn-text').text('Add New Procedure');
          $(this).hide();
      });
    });
  </script>
</body>
</html>