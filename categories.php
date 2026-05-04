<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Categories — Dental App</title>
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
  </style>
</head>

<body>
  <div class="app-shell">
    <?php require_once "includes/page-header.php" ?>
    <main class="main-content">
      <div class="page-wrapper">
        <div class="page-header">
          <div class="page-header-left">
            <h1>Categories</h1>
            <div class="page-header-sub">Manage item categories for the inventory.</div>
          </div>
        </div>

        <div class="admin-grid">
          <!-- LEFT SIDE: ADD FORM -->
          <div class="sticky-side">
            <div class="form-section-title mb-4"><i class="fa-solid fa-plus-circle"></i> Add New Category</div>
            <form id="category-form" novalidate>
              <input type="hidden" name="category_id" id="category-id" value="">
              
              <div class="form-group mb-4">
                <label class="form-label">Category Name <span class="required">*</span></label>
                <input type="text" name="name" id="name" class="form-control" placeholder="e.g. Surgical Supplies" required>
                <span class="form-error">Category name is required.</span>
              </div>

              <div class="form-group mb-4">
                <label class="form-label">Description (Optional)</label>
                <textarea name="description" id="description" class="form-control" placeholder="Brief description..." rows="4"></textarea>
              </div>

              <button class="btn btn-primary w-full" id="btn-save-category" style="width: 100%;">
                <span class="btn-spinner"></span>
                <span class="btn-text">Add New Category</span>
              </button>
              <button type="button" class="btn btn-ghost w-full mt-2" id="btn-cancel-edit" style="width: 100%; display:none;">Cancel Edit</button>
            </form>
          </div>

          <!-- RIGHT SIDE: TABLE -->
          <div class="table-wrapper">
            <table class="data-table" id="categories-table">
              <thead>
                <tr>
                  <th width="50">#</th>
                  <th>Category Name</th>
                  <th>Description</th>
                  <th width="100">Actions</th>
                </tr>
              </thead>
              <tbody id="categories-tbody">
                <tr>
                  <td colspan="4">
                    <div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>
                  </td>
                </tr>
              </tbody>
            </table>
            <div class="table-pagination" id="category-pagination">
              <span id="category-info" class="text-muted text-sm">—</span>
              <div class="pagination" id="pagination-btns"></div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Reusable confirm modal (Keep same as Items) -->
  <div class="modal-backdrop" id="confirm-modal">
    <div class="modal modal-sm">
      <div class="modal-header">
        <div class="modal-title">Confirm Action</div>
        <button class="modal-close" data-close-modal="confirm-modal">&#x2715;</button>
      </div>
      <div class="modal-body">
        <p class="confirm-message">Are you sure you want to delete this category?</p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-ghost" id="confirm-cancel">Cancel</button>
        <button class="btn btn-danger" id="confirm-ok">Confirm</button>
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
  <!-- Assuming you will create categories.js similar to items.js -->
  <script src="assets/js/categories.js"></script>
  <script>
    $(document).ready(function () {
      App.auth.check();
      App.auth.role('admin');

      /* Cancel Edit Logic */
      $('#btn-cancel-edit').on('click', function() {
          $('#category-id').val('');
          $('#category-form')[0].reset();
          $('#btn-save-category .btn-text').text('Add New Category');
          $(this).hide();
      });
    });
  </script>
</body>
</html>