<?php
/**
 * admin-dashboard.php
 * Premium Administration Metrics Layout Panel
 */
?>

<div class="grid-6 mb-8">
  <div class="stat-card minimal-metric-card">
    <div class="minimal-card-header text-muted">Total Monthly Revenue</div>
    <div class="minimal-card-value text-primary" id="admin-metric-total-rev">$0.00</div>
    <div class="minimal-card-sub"><i class="fa-solid fa-chart-line text-success"></i> Cash received <span id="cash-received"> </span></div>
  </div>

  <div class="stat-card minimal-metric-card">
    <div class="minimal-card-header text-muted">Total Pre-Auth Value</div>
    <div class="minimal-card-value text-dark" id="admin-metric-preauth-total">$0.00</div>
    <div class="minimal-card-sub" id="admin-count-preauth-total">0 Requests Total</div>
  </div>

  <div class="stat-card minimal-metric-card">
    <div class="minimal-card-header text-muted">Approved Pre-Auth Value</div>
    <div class="minimal-card-value text-success" id="admin-metric-preauth-approved">$0.00</div>
    <div class="minimal-card-sub" id="admin-count-preauth-approved">0 Approved Cases</div>
  </div>

  <div class="stat-card minimal-metric-card">
    <div class="minimal-card-header text-muted">Pending Pre-Auth Value</div>
    <div class="minimal-card-value text-warning" id="admin-metric-preauth-pending">$0.00</div>
    <div class="minimal-card-sub" id="admin-count-preauth-pending">0 Cases in Review</div>
  </div>

  <div class="stat-card minimal-metric-card">
    <div class="minimal-card-header text-muted">Completed Pre-Auth Value</div>
    <div class="minimal-card-value text-info" id="admin-metric-preauth-completed">$0.00</div>
    <div class="minimal-card-sub" id="admin-count-preauth-completed">0 Cases Finalized</div>
  </div>

  <div class="stat-card minimal-metric-card">
    <div class="minimal-card-header text-muted">Total Lab Value</div>
    <div class="minimal-card-value text-purple" id="admin-metric-labs-total">$0.00</div>
    <div class="minimal-card-sub" id="admin-count-labs-total">0 Open Lab Invoices</div>
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
      <canvas id="chart-preauth-status-pie"></canvas>
    </div>
  </div>

  <div class="card p-4">
    <div class="card-header pb-2 border-none">
      <div class="card-title text-sm font-semibold text-muted">
        <i class="fa-solid fa-clinic-medical text-primary mr-2"></i> Pre-Auth Volume By Clinic Office
      </div>
    </div>
    <div class="chart-canvas-wrapper" style="position: relative; height:260px; width:100%;">
      <canvas id="chart-preauth-offices-bar"></canvas>
    </div>
  </div>

  <div class="card p-4">
    <div class="card-header pb-2 border-none">
      <div class="card-title text-sm font-semibold text-muted">
        <i class="fa-solid fa-user-md text-primary mr-2"></i> Payments Capture By Doctor (This Month)
      </div>
    </div>
    <div class="chart-canvas-wrapper" style="position: relative; height:260px; width:100%;">
      <canvas id="chart-doctor-production-bar"></canvas>
    </div>
  </div>

  <div class="card p-4">
    <div class="card-header pb-2 border-none">
      <div class="card-title text-sm font-semibold text-muted">
        <i class="fa-solid fa-users text-primary mr-2"></i> Active Patient Volume Share By Clinic Site
      </div>
    </div>
    <div class="chart-canvas-wrapper" style="position: relative; height:260px; width:100%;">
      <canvas id="chart-patient-share-pie"></canvas>
    </div>
  </div>

</div>

