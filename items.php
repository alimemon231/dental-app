<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Items</title>
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
            <h1>Manage Items</h1>
            <div class="page-header-sub">Manage all Items here.</div>
          </div>
          <div class="page-header-actions">
            <button class="btn btn-primary" id="btn-add-item">
              <i class="fa-solid fa-pills"></i> Add Item
            </button>
          </div>
        </div>

        <!-- ============================================================
     PATIENTS TABLE
============================================================ -->
        <div class="table-wrapper">
          <table class="data-table" id="Items-table">
            <thead>
              <tr>
                <th class="sortable" data-col="id">#</th>
                <th class="sortable" data-col="name">Item Name</th>
                <th>Price</th>
                <th>Description</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="items-tbody">
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
     ADD / EDIT Item MODAL
============================================================ -->
        <div class="modal-backdrop" id="item-modal">
          <div class="modal modal-lg">
            <div class="modal-header">
              <div class="modal-title" id="item-modal-title">Add New Item</div>
              <button class="modal-close" data-close-modal="item-modal">&#x2715;</button>
            </div>
            <div class="modal-body">
              <form id="item-form" novalidate>
                <input type="hidden" name="item_id" id="item-id" value="">

                <!-- Personal Info -->
                <div class="form-section">
                  <div class="form-section-title"><i class="fa-solid fa-pills"></i> Item Information</div>
                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label">Item Name <span class="required">*</span></label>
                      <input type="text" name="name" id="name" class="form-control" placeholder="e.g. Item 1"
                        required>
                      <span class="form-error">Item name is required.</span>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Price<span class="required">*</span></label>
                      <input type="number" name="price" id="price" class="form-control" placeholder="+1 453-545-345"
                        required>
                      <span class="form-error"> Item price is required.</span>
                    </div>
                  </div>
                  <div class="form-row" style="grid-template-columns: repeat(1, 1fr);">
                    <div class="form-group">
                      <label class="form-label">Description <span class="required">*</span></label>
                      <textarea name="description" id="description" placeholder="Description about item" class="form-control" required></textarea>
                      <span class="form-error">Description is required</span>
                    </div>
                  </div>
                  <div class="form-row" style="grid-template-columns: repeat(1, 1fr);margin-top:10px;">
                    <div class="form-group">
                      <label class="form-label"> Item Image<span class="required">*</span></label>
                      <input type="file"  class="form-control" accept=".png, .jpg, .jpeg" name="image" id="image" required>
                      <span class="form-error">Image is required</span>
                    </div>
                  </div>
                </div>
              </form>
            </div>
            <div class="modal-footer">
              <button class="btn btn-ghost" data-close-modal="item-modal">Cancel</button>
              <button class="btn btn-primary" id="btn-save-item">
                <span class="btn-spinner"></span>
                <span class="btn-text">Save Item</span>
              </button>
            </div>
          </div>
        </div>

        <!-- ============================================================
     VIEW PATIENT MODAL
============================================================ -->
        <div class="modal-backdrop" id="view-item-modal">
          <div class="modal modal-lg">
            <div class="modal-header">
              <div class="modal-title">Patient Details</div>
              <button class="modal-close" data-close-modal="view-item-modal">&#x2715;</button>
            </div>
            <div class="modal-body" id="view-items-body">
              Loading…
            </div>
            <div class="modal-footer">
              <button class="btn btn-ghost" data-close-modal="view-item-modal">Close</button>
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
  <script src="assets/js/items.js"></script>
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