<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Dynamic Statistics</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/global.css">
  <link rel="stylesheet" href="assets/css/layout.css">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <style>

    .modal-lg{
        max-width: 940px;;
    }
    /* Metric key-value pairing itemization repeat row design tuning */
    .metric-data-row {
      border-bottom: 1px dashed var(--color-border, #e2e8f0);
      padding-bottom: var(--sp-3, 12px);
      margin-bottom: var(--sp-3, 12px);
    }

    .metric-data-row:last-child {
      border-bottom: none;
      padding-bottom: 0;
      margin-bottom: 0;
    }

    .btn-remove-metric {
      background: #fee2e2;
      color: #ef4444;
      border: 1px solid #fca5a5;
      border-radius: var(--radius-md, 6px);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      height: 38px;
      width: 38px;
      transition: all 0.2s ease;
    }

    .btn-remove-metric:hover {
      background: #fecaca;
      color: #dc2626;
    }

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
  </style>
</head>

<body>
  <div class="app-shell">
    <?php require_once "includes/page-header.php" ?>
    <main class="main-content">
      <div class="page-wrapper">
        
        <div class="page-header">
          <div class="page-header-left">
            <h1>Manage Dynamic Statistics</h1>
            <div class="page-header-sub">Configure dynamic diagnostic charts and card metrics for doctor and staff dashboard grids.</div>
          </div>
          <div class="page-header-actions">
            <button class="btn btn-primary" id="btn-add-statics">
              <i class="fa-solid fa-chart-pie"></i> Create New Metric Widget
            </button>
          </div>
        </div>

        <div class="table-controls-container">
          <form id="statics-filter-form" style="width: 100%; display: flex; flex-wrap: wrap; gap: var(--sp-4); align-items: flex-end;">
            <div class="filter-group">
              <label class="filter-label">Filter Month</label>
              <input type="month" id="filter-static-month" class="form-control">
            </div>
            <div class="filter-group">
              <label class="filter-label">Target Scope</label>
              <select id="filter-target-type" class="form-control">
                <option value="">All Scopes</option>
                <option value="office">Offices Matrix</option>
                <option value="doctor">Doctors Matrix</option>
              </select>
            </div>
            <button type="button" id="btn-filter-statics" class="btn btn-primary">
              <i class="fa-solid fa-filter"></i> Filter
            </button>
          </form>
        </div>

        <div class="table-wrapper">
          <table class="data-table" id="statics-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Target Period</th>
                <th>Widget Title / Label</th>
                <th>Target Scope Allocation</th>
                <th>Chart Layout Configuration</th>
                <th>Roles Visibility Access</th>
                <th>Actions Matrix</th>
              </tr>
            </thead>
            <tbody id="statics-tbody">
              <tr>
                <td colspan="7">
                  <div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Initializing data configurations…</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="modal-backdrop" id="statics-modal">
          <div class="modal modal-lg">
            <div class="modal-header">
              <div class="modal-title" id="statics-modal-title">Create Dynamic Metric Grid Widget</div>
              <button class="modal-close" data-close-modal="statics-modal">&#x2715;</button>
            </div>
            <div class="modal-body">
              <form id="statics-form" novalidate>
                <input type="hidden" name="id" id="static-id" value="">

                <div class="form-section">
                  <div class="form-section-title"><i class="fa-solid fa-calendar-days"></i> Period Framework & Header Details</div>
                  <div class="form-row" style="grid-template-columns: 1fr 1fr 2fr; gap: var(--sp-3);">
                    
                    <div class="form-group">
                      <label class="form-label">Target Year <span class="required">*</span></label>
                      <select name="static_year" id="static_year" class="form-control" required>
                        <?php 
                          $currentYear = (int)date('Y');
                          for ($y = $currentYear; $y >= 2020; $y--) {
                              echo "<option value=\"{$y}\">{$y}</option>";
                          }
                        ?>
                      </select>
                    </div>

                    <div class="form-group">
                      <label class="form-label">Target Month <span class="required">*</span></label>
                      <select name="static_month_select" id="static_month_select" class="form-control" required>
                        <?php 
                          for ($m = 1; $m <= 12; $m++) {
                              $monthVal = str_pad($m, 2, '0', STR_PAD_LEFT);
                              $monthName = date('F', mktime(0, 0, 0, $m, 1));
                              echo "<option value=\"{$monthVal}\">{$monthName}</option>";
                          }
                        ?>
                      </select>
                    </div>

                    <div class="form-group">
                      <label class="form-label">Widget Title Label <span class="required">*</span></label>
                      <input type="text" name="static_label" id="static_label" class="form-control" placeholder="e.g., Monthly Clinical Revenue Analysis" required>
                      <span class="form-error">Please assign a descriptive metric title.</span>
                    </div>

                  </div>
                </div>

                <div class="form-section mt-4">
                  <div class="form-section-title"><i class="fa-solid fa-sliders"></i> Target Filter Scope Rules Matrix</div>
                  <div class="form-row" style="grid-template-columns: 1fr 1fr 1fr; gap: var(--sp-3);">
                    
                    <div class="form-group">
                      <label class="form-label">Target Destination Scope <span class="required">*</span></label>
                      <select name="target_composite_type" id="target_composite_type" class="form-control" required>
                        <option value="all_offices">All Functional Offices (Global)</option>
                        <option value="all_doctors">All Medical Providers (Global)</option>
                        <option value="single_office">Single Specific Office Location</option>
                        <option value="single_doctor">Single Specific Medical Provider</option>
                      </select>
                    </div>

                    <div class="form-group">
                      <label class="form-label">Chart Rendering Engine Layout <span class="required">*</span></label>
                      <select name="chart_type" id="chart_type" class="form-control" required>
                        </select>
                    </div>

                    <div class="form-group" id="container-specific-entity" style="display: none;">
                      <label class="form-label" id="label-specific-entity">Select Specific Entity Target <span class="required">*</span></label>
                      <select name="target_id" id="target_id" class="form-control" style="width: 100%;">
                        </select>
                    </div>

                  </div>
                </div>

                <div class="form-section mt-4" id="section-json-payload-matrix">
                  <div class="form-section-title" style="display: flex; justify-content: space-between; align-items: center;">
                    <span><i class="fa-solid fa-database"></i> Metric Values Entry Set Matrix</span>
                    <button type="button" class="btn btn-sm btn-secondary" id="btn-add-metric-row">
                      <i class="fa-solid fa-plus"></i> Add Item Data Entry
                    </button>
                  </div>

                  <div id="metric-rows-wrapper" class="mt-2">
                    </div>
                </div>

                <div class="form-section mt-4">
                  <div class="form-section-title"><i class="fa-solid fa-eye"></i> Dashboard Component Access Privileges</div>
                  <div class="form-row" style="grid-template-columns: 1fr 1fr; gap: var(--sp-4);">
                    
                    <div class="form-group">
                      <label class="form-label">Staff Visibility Profile Toggle</label>
                      <select name="staff_visibility" id="staff_visibility" class="form-control">
                        <option value="1">Show Widget inside Staff Portals Layout Grid</option>
                        <option value="0">Hide Widget from Staff Layout Grid View</option>
                      </select>
                    </div>

                    <div class="form-group">
                      <label class="form-label">Doctor Visibility Profile Toggle</label>
                      <select name="doctor_visibility" id="doctor_visibility" class="form-control">
                        <option value="1">Show Widget inside Doctor Portals Layout Grid</option>
                        <option value="0">Hide Widget from Doctor Layout Grid View</option>
                      </select>
                    </div>

                  </div>
                </div>

              </form>
            </div>
            <div class="modal-footer">
              <button class="btn btn-ghost" data-close-modal="statics-modal">Cancel Configuration</button>
              <button class="btn btn-primary" id="btn-save-statics">
                <span class="btn-spinner"></span>
                <span class="btn-text">Publish Metric Widget</span>
              </button>
            </div>
          </div>
        </div>

      </div>
    </main>
  </div>

  <div class="modal-backdrop" id="delete-confirm-modal">
  <div class="modal modal-sm">
    <div class="modal-header">
      <div class="modal-title text-danger">Confirm Deletion</div>
      <button type="button" class="modal-close" data-close-modal="delete-confirm-modal">&#x2715;</button>
    </div>
    <div class="modal-body">
      <p class="text-sm text-muted">
        Are you sure you want to delete this record? This action is <strong>permanent</strong> and cannot be undone. All associated audit logs will be flagged for removal.
      </p>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost" data-close-modal="delete-confirm-modal">Cancel</button>
      <button type="button" class="btn btn-danger" id="btn-delete-confirm-ok">Permanently Delete</button>
    </div>
  </div>
</div>

  <div id="toast-container"></div>
  <div id="global-loader">
    <div class="loader-spinner"></div>
    <div class="loader-text">Executing operations processing matrix…</div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="assets/js/app.js"></script>
  <script src="assets/js/adm-statics.js"></script>
  
  <script>
    $(document).ready(function () {
      App.auth.check();
      App.auth.role(['admin']); // Enforce administrative control level tracking verification bounds
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