/**
 * assets/js/doctor-dashboard.js
 * Premium Doctor Metrics & Performance Layout Engine
 * Handles personal KPI metric maps, dynamic budget tracking, and custom Chart.js lines.
 */

$(document).ready(function () {

    /* ── Workspace State Configuration ── */
    // Track provider chart instances globally to prevent layout overlapping memory bugs
    var doctorCharts = {
        preauthStatusPie: null,
        paymentsRevenueLine: null,
        preauthPricesLine: null,
        labsRevenueLine: null
    };

    /* ================================================================
       FUNCTION 1: PROVIDER AND PIPELINE STAT CARDS (ALL METRICS SINGLE API)
    ================================================================ */
    function loadDoctorStatCards() {
        App.ajax({
            url: '/doctor-dashboard/dashboard-stats.php',
            method: 'GET',
            loader: false,
            onSuccess: function (data) {
                // Currency Formatting Helper Rule Matrix
                function formatValue(val) {
                    return '$' + parseFloat(val || 0).toLocaleString(undefined, {
                        minimumFractionDigits: 2, 
                        maximumFractionDigits: 2
                    });
                }

                // 1. Populate Core Top Rows Financial Metric Figures safely
                $('#doc-metric-total-rev').text(formatValue(data.total_cost));
                $('#total-cash-collected').text(formatValue(data.total_revenue_made));
                $('#doc-metric-preauth-sent').text(formatValue(data.preauth_sent_value));
                $('#doc-metric-preauth-approved').text(formatValue(data.preauth_approved_value));
                $('#doc-metric-preauth-scheduled').text(formatValue(data.preauth_scheduled_value));
                $('#doc-metric-preauth-completed').text(formatValue(data.preauth_completed_value));
                $('#doc-metric-labs-total').text(formatValue(data.labs_total_value));

                // 2. Populate Bottom Meta Substrings Descriptions
                $('#doc-count-preauth-sent').text((data.preauth_sent_count || 0) + ' Submissions Out');
                $('#doc-count-preauth-approved').text((data.preauth_approved_count || 0) + ' Cases Authorized');
                $('#doc-count-preauth-scheduled').text((data.preauth_scheduled_count || 0) + ' Booked Procedures');
                $('#doc-count-preauth-completed').text((data.preauth_completed_count || 0) + ' Cases Finalized');
                $('#doc-count-labs-total').text((data.labs_total_count || 0) + ' Linked Invoices');

                // 3. Populate Secondary Row Extra Operational Metrics 
                $('#doc-metric-pending-orders').text(parseInt(data.pending_orders_count) || 0);

                // 4. Calculate and Update Dynamic Monthly Budget Metrics
                var totalBudget    = parseFloat(data.total_budget || 0);
                var spentBudget    = parseFloat(data.spent_budget || 0);
                var budgetRemaining = parseFloat(data.monthly_budget_remaining || 0);
                var pctLeft         = parseFloat(data.monthly_budget_percentage_left || 0);
                
                // Update text elements with explicitly formatted values
                $('#doc-budget-total-text').text(formatValue(totalBudget));
                $('#doc-budget-spent-text').text(formatValue(spentBudget));
                $('#doc-budget-remaining-text').text(formatValue(budgetRemaining));
                $('#doc-budget-percentage-text').text(pctLeft.toFixed(0) + '% left');

                // Progress bar fills up as spending increases
                var pctSpent = totalBudget > 0 ? (spentBudget / totalBudget) * 100 : 0;
                var boundedSpentPct = Math.min(Math.max(pctSpent, 0), 100);
                $('#doc-budget-progress-fill').css('width', boundedSpentPct + '%');
            }
        });
    }

    /* ================================================================
       FUNCTION 2: DYNAMIC CHARTS POPULATION PIPELINE (4 SEPARATE ENDPOINTS)
    ================================================================ */
    function initDoctorDashboardCharts() {
        
        // Chart A: Personal Pre-Auth Status Matrix Share (Pie)
        App.ajax({
            url: '/doctor-dashboard/chart-preauth-status.php',
            method: 'GET',
            onSuccess: function (res) {
                if (doctorCharts.preauthStatusPie) doctorCharts.preauthStatusPie.destroy();
                
                var ctx = document.getElementById('chart-doc-preauth-status-pie').getContext('2d');
                doctorCharts.preauthStatusPie = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: res.labels,
                        datasets: [{
                            data: res.values,
                            backgroundColor: ['#f1c40f', '#2ecc71', '#e74c3c', '#3498db', '#9b59b6'],
                            borderWidth: 1
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false }
                });
            }
        });

        // Chart B: Revenue Collections Trend Timeline (Line)
        App.ajax({
            url: '/doctor-dashboard/chart-revenue-timeline.php',
            method: 'GET',
            loader: false,
            onSuccess: function (res) {
                if (doctorCharts.paymentsRevenueLine) doctorCharts.paymentsRevenueLine.destroy();
                
                var ctx = document.getElementById('chart-doc-payments-revenue-line').getContext('2d');
                doctorCharts.paymentsRevenueLine = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: res.labels, // Expects days of month: ['01', '02', '03'...]
                        datasets: [{
                            label: 'Revenue Captured ($)',
                            data: res.values,
                            borderColor: '#37b24d',
                            backgroundColor: 'rgba(55, 178, 77, 0.08)',
                            fill: true,
                            tension: 0.35,
                            borderWidth: 2.5,
                            pointRadius: 2
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
                });
            }
        });

        // Chart C: Pre-Auth Pricing Generation Pipeline Timeline (Line)
        App.ajax({
            url: '/doctor-dashboard/chart-preauth-timeline.php',
            method: 'GET',
            loader: false,
            onSuccess: function (res) {
                if (doctorCharts.preauthPricesLine) doctorCharts.preauthPricesLine.destroy();
                
                var ctx = document.getElementById('chart-doc-preauth-prices-line').getContext('2d');
                doctorCharts.preauthPricesLine = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: res.labels,
                        datasets: [{
                            label: 'Pre-Auth Pipeline ($)',
                            data: res.values,
                            borderColor: '#1098ad',
                            backgroundColor: 'rgba(16, 152, 173, 0.08)',
                            fill: true,
                            tension: 0.35,
                            borderWidth: 2.5,
                            pointRadius: 2
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
                });
            }
        });

        //Chart D: Mapped Lab Revenue Trends Timeline (Line)
        App.ajax({
            url: '/doctor-dashboard/chart-labs-timeline.php',
            method: 'GET',
            loader: false,
            onSuccess: function (res) {
                if (doctorCharts.labsRevenueLine) doctorCharts.labsRevenueLine.destroy();
                
                var ctx = document.getElementById('chart-doc-labs-revenue-line').getContext('2d');
                doctorCharts.labsRevenueLine = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: res.labels,
                        datasets: [{
                            label: 'Lab Value Generated ($)',
                            data: res.values,
                            borderColor: '#845ef7',
                            backgroundColor: 'rgba(132, 94, 247, 0.08)',
                            fill: true,
                            tension: 0.35,
                            borderWidth: 2.5,
                            pointRadius: 2
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
                });
            }
        });
    }

    /* ================================================================
       WORKSPACE INITIALIZATION RUNTIME ENGINE
    ================================================================ */
    loadDoctorStatCards();
    initDoctorDashboardCharts();

});