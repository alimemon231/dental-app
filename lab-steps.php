<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lab Steps — Dental App</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/global.css">
  <link rel="stylesheet" href="assets/css/layout.css">
  <style>
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
            <h1>Lab Steps</h1>
            <div class="page-header-sub">Define workflow steps for lab cases (e.g. Try-in, Final Insertion, Framework Check).</div>
          </div>
        </div>

        <div class="admin-grid">
          <div class="sticky-side">
            <div class="form-section-title mb-4">
                <i class="fa-solid fa-list-check"></i> <span id="form-title">Add New Step</span>
            </div>
            
            <form id="lab-step-form" novalidate>
              <input type="hidden" name="id" id="step-id" value="">
              
              <div class="form-group mb-4">
                <label class="form-label">Step Name <span class="required">*</span></label>
                <input type="text" name="name" id="step-name" class="form-control" placeholder="e.g. Wax Try-in" required>
                <span class="form-error">Step name is required.</span>
              </div>

              <div class="form-group mb-4" id="status-group" style="display: none;">
                <label class="form-label">Status</label>
                <select name="status" id="step-status" class="form-control">
                  <option value="active">Active</option>
                  <option value="deactive">Deactive</option>
                </select>
              </div>

              <button class="btn btn-primary w-full" id="btn-save-step" style="width: 100%;">
                <span class="btn-spinner"></span>
                <span class="btn-text">Add Step</span>
              </button>
              
              <button type="button" class="btn btn-ghost w-full mt-2" id="btn-cancel-edit" style="width: 100%; display:none;">
                Cancel Edit
              </button>
            </form>
          </div>

          <div class="table-wrapper">
            <table class="data-table" id="lab-step-table">
              <thead>
                <tr>
                  <th width="50">#</th>
                  <th>Step Name</th>
                  <th width="120">Status</th>
                  <th width="120">Actions</th>
                </tr>
              </thead>
              <tbody id="lab-step-tbody">
                <tr>
                  <td colspan="4">
                    <div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading lab steps...</div>
                  </td>
                </tr>
              </tbody>
            </table>
            
            <div class="table-pagination">
              <span id="lab-step-info" class="text-muted text-sm">—</span>
              <div class="pagination" id="pagination-btns"></div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="assets/js/app.js"></script>
  <script src="assets/js/lab-steps.js"></script>
  <script>
    $(document).ready(function () {
      App.auth.check();
      App.auth.role('admin');
    });
  </script>
</body>
</html>