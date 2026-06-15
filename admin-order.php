<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Order Lifecycle Manager</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/global.css">
  <link rel="stylesheet" href="assets/css/layout.css">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <style>
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
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)) 120px;
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

    .added-item-row {
      animation: fadeIn 0.2s ease-in-out;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(4px); }
      to { opacity: 1; transform: translateY(0); }
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
            <h1>Global Order Management (Admin)</h1>
            <div class="page-header-sub">Administrative cross-clinic supply line fulfillment and verification matrix.</div>
          </div>
          <div class="page-header-actions">
            
          </div>
        </div>

        <div class="table-controls-container">
          <form id="admin-order-filter-form" class="filter-grid-layout" onsubmit="return false;">

            <div class="filter-group">
              <label class="filter-label">Order Reference No.</label>
              <input type="text" id="search-order" class="filter-control" placeholder="Search order code...">
            </div>

            <div class="filter-group">
              <label class="filter-label">Clinic Destination Site</label>
              <select id="filter-office" class="filter-control">
                <option value="">All Locations</option>
              </select>
            </div>

            <div class="filter-group">
              <label class="filter-label">Start Date</label>
              <input type="date" id="filter-start-date" class="filter-control">
            </div>

            <div class="filter-group">
              <label class="filter-label">End Date</label>
              <input type="date" id="filter-end-date" class="filter-control">
            </div>

            <button type="button" id="btn-apply-filters" class="btn btn-primary" title="Search">
              <i class="fa-solid fa-glass"></i> <span>Search</span>
            </button>

          </form>
        </div>

        <div class="table-wrapper">
          <table class="data-table" id="master-order-table">
            <thead>
              <tr>
                <th class="sortable" data-col="id">#</th>
                <th class="sortable" data-col="order_number">Order Number</th>
                <th class="sortable" data-col="office_name">Clinic Site</th>
                <th class="sortable" data-col="order_date">Order Date</th>
                <th class="sortable" data-col="delivery_date">Delivery Date</th>
                <th>Requested By</th>
                <th>Approved By</th>
                <th>Total Value</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="admin-order-tbody">
              <tr>
                <td colspan="10">
                  <div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Initializing administrative telemetric channels...</div>
                </td>
              </tr>
            </tbody>
          </table>
          <div class="table-pagination" id="master-order-pagination">
            <span id="order-meta-info" class="text-muted text-sm">—</span>
            <div class="pagination" id="pagination-btns"></div>
          </div>
        </div>

      </div>
    </main>
  </div>

  <div class="modal-backdrop" id="order-modal">
    <div class="modal modal-lg">
      <div class="modal-header">
        <div class="modal-title" id="order-modal-title">Create Administrative Order File</div>
        <button type="button" class="modal-close" data-close-modal="order-modal">&#x2715;</button>
      </div>
      <div class="modal-body">
        <form id="order-form" novalidate>
          <input type="hidden" name="order_id" id="order-id" value="">

          <div class="form-section">
            <div class="form-section-title"><i class="fa-solid fa-sliders"></i> Context & Routing Setup</div>
            <div class="form-row" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
              <div class="form-group">
                <label class="form-label">Destination Clinic Site <span class="required">*</span></label>
                <select name="office_id" id="order-office-select" class="form-control" required style="width:100%;">
                  <option value="">Select targeting workspace location...</option>
                </select>
                <span class="form-error">Destination location routing selection is required.</span>
              </div>
              <div class="form-group">
                <label class="form-label">Order Timeline Date <span class="required">*</span></label>
                <input type="date" name="o_date" id="o-date" class="form-control" required>
                <span class="form-error">Initialization date is required.</span>
              </div>
              <div class="form-group">
                <label class="form-label">Expected Target Delivery <span class="required">*</span></label>
                <input type="date" name="r_date" id="r-date" class="form-control" required>
                <span class="form-error">Delivery target operational baseline timestamp required.</span>
              </div>
            </div>

            <div class="form-section-title" style="margin-top:25px;"><i class="fa-solid fa-basket-shopping"></i> Inventory Line Assembly Engine</div>
            <div class="form-row" style="grid-template-columns: 2fr 1fr auto; align-items: end; gap: var(--sp-3);">
              <div class="form-group">
                <label class="form-label">Inventory Item Configuration Selection</label>
                <select id="modal-item-picker" class="form-control" style="width: 100%;">
                  <option value="">Select standard item profile...</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Fulfillment Quantity</label>
                <input type="number" id="modal-item-qty" class="form-control" min="1" placeholder="e.g. 10">
              </div>
              <div class="form-group">
                <button type="button" class="btn btn-secondary" id="btn-append-item-row" style="height:38px;">
                  <i class="fa-solid fa-plus"></i> Push Item
                </button>
              </div>
            </div>
          </div>
        </form>

        <div class="table-wrapper" style="margin-top: var(--sp-4);">
          <table class="data-table" id="manifest-items-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Inventory Manifest Name</th>
                <th>Unit Cost Price</th>
                <th>Target Quantity</th>
                <th>Sub-Total</th>
                <th style="width: 60px; text-align: center;">Purge</th>
              </tr>
            </thead>
            <tbody id="manifest-items-tbody">
              <tr>
                <td colspan="6">
                  <div class="table-empty text-muted">No line entries pushed onto localized buffer table array yet.</div>
                </td>
              </tr>
            </tbody>
          </table>
          <div class="table-pagination" style="display: flex; justify-content: flex-end; padding: var(--sp-3);">
            <div class="font-bold">Grand Calculated Valuation: <span id="grand-total-display">$0.00</span></div>
          </div>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-close-modal="order-modal">Cancel Operational Workflow</button>
        <button type="button" class="btn btn-primary" id="btn-save-order">
          <span class="btn-spinner"></span>
          <span class="btn-text"><i class="fa-solid fa-floppy-disk"></i> Commit Order File</span>
        </button>
      </div>
    </div>
  </div>

  <div class="modal-backdrop" id="view-order-modal">
    <div class="modal modal-lg">
      <div class="modal-header">
        <div class="modal-title"><i class="fa-solid fa-receipt"></i> System Order Verification Record View</div>
        <button type="button" class="modal-close" data-close-modal="view-order-modal">&#x2715;</button>
      </div>
      <div class="modal-body">
        <div class="grid-2" style="gap: var(--sp-6);">
          <div>
            <div class="form-section-title mb-3"><i class="fa-solid fa-circle-info"></i> Base System Information</div>
            <div id="view-order-details-pane" style="line-height: 1.8;">
              </div>
          </div>
          <div>
            <div class="form-section-title mb-3"><i class="fa-solid fa-boxes-stacked"></i> Packaging Cargo Item Set</div>
            <div class="table-wrapper">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Item Spec</th>
                    <th>Qty</th>
                    <th>Unit Cost</th>
                    <th>Subtotal</th>
                  </tr>
                </thead>
                <tbody id="view-order-items-tbody">
                  </tbody>
              </table>
              <div style="text-align: right; font-weight: bold; padding: var(--sp-3); border-top: 1px solid #e2e8f0;">
               Total:  <span id="view-order-grand-total"></span>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-close-modal="view-order-modal">Close Window View</button>
        <button type="button" class="btn btn-primary" id="btn-edit-from-view"><i class="fa-solid fa-pen-to-square"></i> Pivot Into Editor Mode</button>
      </div>
    </div>
  </div>

  <div class="modal-backdrop" id="adjudicate-modal">
    <div class="modal modal-sm">
      <div class="modal-header">
        <div class="modal-title" id="adjudicate-title">Adjudicate Workflow Transaction</div>
        <button type="button" class="modal-close" data-close-modal="adjudicate-modal">&#x2715;</button>
      </div>
      <div class="modal-body">
        <div class="text-center mb-4">
          <i class="fa-solid fa-gavel text-primary mb-2" style="font-size: 2.5rem;"></i>
          <p id="adjudicate-prompt-message">Assert administrative routing destination authority over this item record file tracking lifecycle state:</p>
        </div>
        <form id="adjudicate-form" onsubmit="return false;">
          <input type="hidden" id="adjudicate-order-id">
          <input type="hidden" id="adjudicate-target-action">

          <div id="rejection-commentary-box" style="display: none;" class="mb-3">
            <label class="form-label text-danger font-bold text-xs mb-1">REJECTION EXPLANATION RECORDING <span class="required">*</span></label>
            <textarea id="adjudicate-notes" class="form-control" rows="3" placeholder="Provide system logs contextual grounds for order rejection fulfillment..."></textarea>
          </div>

          <div id="state-mutation-box" style="display: none;" class="mb-3">
            <label class="form-label font-bold text-xs mb-1">TARGET STATE STEP DESTINATION <span class="required">*</span></label>
            <select id="adjudicate-status-select" class="form-control">
              <option value="Pending">Pending</option>
              <option value="Approved">Approved</option>
              <option value="Rejected">Rejected</option>
              <option value="Completed">Completed</option>
              <option value="Cancelled">Cancelled</option>
            </select>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-close-modal="adjudicate-modal">Dismiss Control Execution</button>
        <button type="button" class="btn" id="btn-execute-adjudication">Commit Modification</button>
      </div>
    </div>
  </div>

  <div class="modal-backdrop" id="confirm-modal">
    <div class="modal modal-sm">
      <div class="modal-header">
        <div class="modal-title">Confirm Destructive Action</div>
        <button type="button" class="modal-close" data-close-modal="confirm-modal">&#x2715;</button>
      </div>
      <div class="modal-body">
        <p class="confirm-message">Are you sure you want to permanently clear this record entry tracking log line from database indexes?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" id="confirm-cancel">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirm-ok">Delete</button>
      </div>
    </div>
  </div>

  <div id="toast-container"></div>
  <div id="global-loader">
    <div class="loader-spinner"></div>
    <div class="loader-text">Synchronizing global administrative matrix databases...</div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="assets/js/app.js"></script>
   <script src="assets/js/admin-order.js"></script>
  <script>
    $(document).ready(function () {
      
      /* ----------------------------------------------------------------
         1. ADMINISTRATIVE GATEWAYS & SYSTEM AUTH CHECKS
      ---------------------------------------------------------------- */
      App.auth.check();
      App.auth.role(['admin', 'management']);

      App.ajax({
        url: '/auth/check.php', loader: false, silent: true,
        onSuccess: function (d) {
          if (d && d.user) {
            $('#sidebar-user-name').text(d.user.name);
            $('#sidebar-user-role').text(d.user.role.toUpperCase() + " Control Console");
            $('#user-avatar-initial').text(d.user.name.charAt(0).toUpperCase());
          }
        }
      });
    });
  </script>
</body>

</html>