<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Labs — Dental App</title>
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

    /* For "anchor" scrolling on pagination */
    #table-anchor { scroll-margin-top: 20px; }
  </style>
</head>

<body>
  <div class="app-shell">
    <?php require_once "includes/page-header.php" ?>
    <main class="main-content">
      <div class="page-wrapper">
        <div class="page-header">
          <div class="page-header-left">
            <h1>Dental Labs</h1>
            <div class="page-header-sub">Register and manage external lab vendors.</div>
          </div>
        </div>

        <div class="admin-grid" id="table-anchor">
          <div class="sticky-side">
            <div class="form-section-title mb-4">
                <i class="fa-solid fa-industry"></i> <span id="form-title">Register New Lab</span>
            </div>
            
            <form id="lab-register-form" novalidate>
              <input type="hidden" name="id" id="lab-id" value="">
              
              <div class="form-group mb-3">
                <label class="form-label">Lab Name <span class="required">*</span></label>
                <input type="text" name="name" id="lab-name" class="form-control" placeholder="e.g. Precision Dental Lab" required>
                <span class="form-error">Lab name is required.</span>
              </div>

              <div class="form-group mb-3">
                <label class="form-label">Contact Phone</label>
                <input type="tel" name="phone" id="lab-phone" class="form-control" placeholder="+1 000-000-0000">
              </div>

              <div class="form-group mb-3">
                <label class="form-label">Contact Email</label>
                <input type="email" name="email" id="lab-email" class="form-control" placeholder="contact@lab.com">
              </div>

              <div class="form-group mb-3">
                <label class="form-label">Full Address</label>
                <textarea name="address" id="lab-address" class="form-control" rows="2" placeholder="Street, City, Zip"></textarea>
              </div>

              <div class="form-group mb-4" id="status-group" style="display: none;">
                <label class="form-label">Status</label>
                <select name="status" id="lab-status" class="form-control">
                  <option value="active">Active</option>
                  <option value="deactive">Deactive</option>
                </select>
              </div>

              <button class="btn btn-primary w-full" id="btn-save-lab" style="width: 100%;">
                <span class="btn-spinner"></span>
                <span class="btn-text">Register Lab</span>
              </button>
              
              <button type="button" class="btn btn-ghost w-full mt-2" id="btn-cancel-edit" style="width: 100%; display:none;">
                Cancel Edit
              </button>
            </form>
          </div>

          <div class="table-wrapper">
            <table class="data-table" id="labs-table">
              <thead>
                <tr>
                  <th>Lab Information</th>
                  <th>Contact Details</th>
                  <th width="100">Status</th>
                  <th width="120">Actions</th>
                </tr>
              </thead>
              <tbody id="labs-tbody">
                <tr><td colspan="4" class="text-center p-4"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</td></tr>
              </tbody>
            </table>
            
            <div class="table-pagination">
              <span id="labs-info" class="text-muted text-sm">—</span>
              <div class="pagination" id="pagination-btns"></div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="assets/js/app.js"></script>
  <script src="assets/js/labs.js"></script>
  <script>
    let currentPage = 1;

    $(document).ready(function () {
      App.auth.check();
      App.auth.role('admin');

  </script>
</body>
</html>