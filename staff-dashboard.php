<?php
/**
 * doctor-dashboard.php
 * Premium Provider Analytics Panel with Pre-built Responsive Layout Grid matrix
 */
?>

<div class="grid-3 mb-8" style="gap: var(--sp-6);" id="custom-statics-card"></div>

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
      <div class="text-xs text-muted">Office Spent: <span class="font-semibold text-dark" id="doc-budget-spent-text">$0.00</span></div>
      <div class="text-xs text-muted">Total Allocation: <span class="font-semibold text-dark" id="doc-budget-total-text">$0.00</span></div>
    </div>
  </div>
</div>

<div class="grid-2 mb-8" style="gap: var(--sp-6);" id="custom-statics-charts"></div>