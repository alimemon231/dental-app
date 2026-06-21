<?php
/**
 * doctor-dashboard.php
 * Premium Provider Analytics Panel with Personal Performance Pipelines & Line Charts
 */
?>

<div class="grid-6 mb-8">
  <div class="stat-card minimal-metric-card">
    <div class="minimal-card-header text-muted">Total Revenue Made</div>
    <div class="minimal-card-value text-primary" id="doc-metric-total-rev">$0.00</div>
    <div class="minimal-card-sub"><i class="fa-solid fa-wallet text-success"></i> Cash collected from your cases <small id="total-cash-collected"> </small></div>
  </div>

  <div class="stat-card minimal-metric-card">
    <div class="minimal-card-header text-muted">Pre-Auth Sent (This Month)</div>
    <div class="minimal-card-value text-warning" id="doc-metric-preauth-sent">$0.00</div>
    <div class="minimal-card-sub" id="doc-count-preauth-sent">0 Submissions Out</div>
  </div>

  <div class="stat-card minimal-metric-card">
    <div class="minimal-card-header text-muted">Pre-Auth Approved</div>
    <div class="minimal-card-value text-info" id="doc-metric-preauth-approved">$0.00</div>
    <div class="minimal-card-sub" id="doc-count-preauth-approved">0 Cases Authorized</div>
  </div>

  <div class="stat-card minimal-metric-card">
    <div class="minimal-card-header text-muted">Pre-Auth Scheduled</div>
    <div class="minimal-card-value text-primary" id="doc-metric-preauth-scheduled">$0.00</div>
    <div class="minimal-card-sub" id="doc-count-preauth-scheduled">0 Booked Procedures</div>
  </div>

  <div class="stat-card minimal-metric-card">
    <div class="minimal-card-header text-muted">Pre-Auth Completed</div>
    <div class="minimal-card-value text-success" id="doc-metric-preauth-completed">$0.00</div>
    <div class="minimal-card-sub" id="doc-count-preauth-completed">0 Cases Finalized</div>
  </div>

  <div class="stat-card minimal-metric-card">
    <div class="minimal-card-header text-muted">Total Lab Value</div>
    <div class="minimal-card-value text-purple" id="doc-metric-labs-total">$0.00</div>
    <div class="minimal-card-sub" id="doc-count-labs-total">0 Linked Invoices</div>
  </div>
</div>

<div class="grid-2 mb-8" style="gap: var(--sp-6);">
  <div class="stat-card minimal-metric-card">
    <div class="minimal-card-header text-muted">Pending Clinical Orders</div>
    <div class="minimal-card-value text-danger" id="doc-metric-pending-orders">0</div>
    <div class="minimal-card-sub"><i class="fa-solid fa-clock text-danger"></i> Requisitions awaiting internal ops processing</div>
  </div>

  <div class="stat-card minimal-metric-card">
    <div class="minimal-card-header text-muted mb-1">Monthly Budget Remaining</div>
    
    <div class="d-flex align-items-baseline justify-content-between mb-1">
      <div>
        <span class="text-xl font-bold text-dark" id="doc-budget-remaining-text">$0.00</span>
        <span class="text-xs text-muted block mt-0.5">Your Office Remaining Balance</span>
      </div>
      <span class="text-xs font-semibold text-primary" id="doc-budget-percentage-text">0% left</span>
    </div>

    <div class="progress-bar-container mb-2" style="background: #e9ecef; border-radius: 4px; height: 8px; width: 100%; overflow: hidden;">
      <div id="doc-budget-progress-fill" style="background: #4dabf7; height: 100%; width: 0%; transition: width 0.4s ease;"></div>
    </div>

    <div class="d-flex align-items-center justify-content-between border-top pt-1" style="border-color: #f1f3f5 !important;">
      <div class="text-xs text-muted">
        Office Spent: <span class="font-semibold text-dark" id="doc-budget-spent-text">$0.00</span>
      </div>
      <div class="text-xs text-muted">
        Total Allocation: <span class="font-semibold text-dark" id="doc-budget-total-text">$0.00</span>
      </div>
    </div>
  </div>
</div>

<div class="grid-2 mb-8" style="gap: var(--sp-6);">
  
  <div class="card p-4">
    <div class="card-header pb-2 border-none">
      <div class="card-title text-sm font-semibold text-muted">
        <i class="fa-solid fa-chart-pie text-primary mr-2"></i> Personal Pre-Auth Status Matrix
      </div>
    </div>
    <div class="chart-canvas-wrapper" style="position: relative; height:260px; width:100%;">
      <canvas id="chart-doc-preauth-status-pie"></canvas>
    </div>
  </div>

  <div class="card p-4">
    <div class="card-header pb-2 border-none">
      <div class="card-title text-sm font-semibold text-muted">
        <i class="fa-solid fa-chart-line text-success mr-2"></i> Revenue Collections Trend (Current Month)
      </div>
    </div>
    <div class="chart-canvas-wrapper" style="position: relative; height:260px; width:100%;">
      <canvas id="chart-doc-payments-revenue-line"></canvas>
    </div>
  </div>

  <div class="card p-4">
    <div class="card-header pb-2 border-none">
      <div class="card-title text-sm font-semibold text-muted">
        <i class="fa-solid fa-chart-line text-info mr-2"></i> Pre-Auth Pricing Generation Pipeline
      </div>
    </div>
    <div class="chart-canvas-wrapper" style="position: relative; height:260px; width:100%;">
      <canvas id="chart-doc-preauth-prices-line"></canvas>
    </div>
  </div>

  <div class="card p-4">
    <div class="card-header pb-2 border-none">
      <div class="card-title text-sm font-semibold text-muted">
        <i class="fa-solid fa-chart-line text-purple mr-2"></i> Mapped Lab Revenue Trends
      </div>
    </div>
    <div class="chart-canvas-wrapper" style="position: relative; height:260px; width:100%;">
      <canvas id="chart-doc-labs-revenue-line"></canvas>
    </div>
  </div>

</div>