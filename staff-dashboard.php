<?php
/**
 * staff-dashboard.php
 * Premium Staff Metrics Layout Panel with Advanced Clinic Tracking & Progress Elements
 */
?>

<div class="grid-6 mb-8">
  <div class="stat-card minimal-metric-card">
    <div class="minimal-card-header text-muted">Total Monthly Revenue</div>
    <div class="minimal-card-value text-primary" id="staff-metric-total-rev">$0.00</div>
    <div class="minimal-card-sub"><i class="fa-solid fa-money-bill-wave text-success"></i> Cash received this month : <span id="cash-received"> </span></div>
  </div>

  <div class="stat-card minimal-metric-card">
    <div class="minimal-card-header text-muted">Total Pre-Auth Value</div>
    <div class="minimal-card-value text-dark" id="staff-metric-preauth-total">$0.00</div>
    <div class="minimal-card-sub" id="staff-count-preauth-total">0 Requests Total</div>
  </div>

  <div class="stat-card minimal-metric-card">
    <div class="minimal-card-header text-muted">Requested/Sent Value</div>
    <div class="minimal-card-value text-warning" id="staff-metric-preauth-sent">$0.00</div>
    <div class="minimal-card-sub" id="staff-count-preauth-sent">0 Submissions Out</div>
  </div>

  <div class="stat-card minimal-metric-card">
    <div class="minimal-card-header text-muted">Scheduled Pre-Auth Value</div>
    <div class="minimal-card-value text-primary" id="staff-metric-preauth-scheduled">$0.00</div>
    <div class="minimal-card-sub" id="staff-count-preauth-scheduled">0 Booked Procedures</div>
  </div>

  <div class="stat-card minimal-metric-card">
    <div class="minimal-card-header text-muted">Completed Pre-Auth Value</div>
    <div class="minimal-card-value text-success" id="staff-metric-preauth-completed">$0.00</div>
    <div class="minimal-card-sub" id="staff-count-preauth-completed">0 Cases Finalized</div>
  </div>

  <div class="stat-card minimal-metric-card">
    <div class="minimal-card-header text-muted">Total Lab Value</div>
    <div class="minimal-card-value text-purple" id="staff-metric-labs-total">$0.00</div>
    <div class="minimal-card-sub" id="staff-count-labs-total">0 Open Lab Invoices</div>
  </div>
</div>

<div class="grid-3 mb-8" style="gap: var(--sp-6);">
  <div class="stat-card minimal-metric-card">
    <div class="minimal-card-header text-muted">Pending Orders</div>
    <div class="minimal-card-value text-danger" id="staff-metric-pending-orders">0</div>
    <div class="minimal-card-sub"><i class="fa-solid fa-clock text-danger"></i> Awaiting internal operations</div>
  </div>

  <div class="stat-card minimal-metric-card">
  <div class="minimal-card-header text-muted mb-1">Monthly Budget Tracker</div>
  
  <div class="d-flex align-items-baseline justify-content-between mb-1">
    <div>
      <span class="text-xl font-bold text-dark" id="staff-budget-remaining-text">$0.00</span>
      <span class="text-xs text-muted block mt-0.5">Remaining Balance</span>
    </div>
    <span class="text-xs font-semibold text-primary" id="staff-budget-percentage-text">0% left</span>
  </div>

  <div class="progress-bar-container mb-2" style="background: #e9ecef; border-radius: 4px; height: 8px; width: 100%; overflow: hidden;">
    <div id="staff-budget-progress-fill" style="background: #4dabf7; height: 100%; width: 0%; transition: width 0.4s ease;"></div>
  </div>

  <div class="d-flex align-items-center justify-content-between border-top pt-1" style="border-color: #f1f3f5 !important;">
    <div class="text-xs text-muted">
      Spent: <span class="font-semibold text-dark" id="staff-budget-spent-text">$0.00</span>
    </div>
    <div class="text-xs text-muted">
      Total Budget: <span class="font-semibold text-dark" id="staff-budget-total-text">$0.00</span>
    </div>
  </div>
</div>

  <div class="stat-card minimal-metric-card">
    <div class="minimal-card-header text-muted">Total Registered Patients</div>
    <div class="minimal-card-value text-teal" id="staff-metric-total-patients">0</div>
    <div class="minimal-card-sub"><i class="fa-solid fa-hospital-user text-teal"></i> Active clinic chart directory</div>
  </div>
</div>

<div class="grid-2 mb-8" style="gap: var(--sp-6);">
  
  <div class="card p-4">
    <div class="card-header pb-2 border-none">
      <div class="card-title text-sm font-semibold text-muted">
        <i class="fa-solid fa-pie-chart text-primary mr-2"></i> Pre-Auth Status Matrix (Current Month)
      </div>
    </div>
    <div class="chart-canvas-wrapper" style="position: relative; height:260px; width:100%;">
      <canvas id="chart-staff-preauth-status-pie"></canvas>
    </div>
  </div>

  <div class="card p-4">
    <div class="card-header pb-2 border-none">
      <div class="card-title text-sm font-semibold text-muted">
        <i class="fa-solid fa-chart-bar text-success mr-2"></i> Pre-Auth Revenue by Provider ($)
      </div>
    </div>
    <div class="chart-canvas-wrapper" style="position: relative; height:260px; width:100%;">
      <canvas id="chart-staff-preauth-doctor-bar"></canvas>
    </div>
  </div>

  <div class="card p-4">
    <div class="card-header pb-2 border-none">
      <div class="card-title text-sm font-semibold text-muted">
        <i class="fa-solid fa-user-md text-primary mr-2"></i> Payments Capture By Doctor (This Month)
      </div>
    </div>
    <div class="chart-canvas-wrapper" style="position: relative; height:260px; width:100%;">
      <canvas id="chart-staff-doctor-production-bar"></canvas>
    </div>
  </div>

  <div class="card p-4">
    <div class="card-header pb-2 border-none">
      <div class="card-title text-sm font-semibold text-muted">
        <i class="fa-solid fa-flask text-purple mr-2"></i> Lab Revenue by Provider ($)
      </div>
    </div>
    <div class="chart-canvas-wrapper" style="position: relative; height:260px; width:100%;">
      <canvas id="chart-staff-labs-doctor-bar"></canvas>
    </div>
  </div>

</div>