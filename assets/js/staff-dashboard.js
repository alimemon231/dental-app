/**
 * assets/js/staff-dashboard.js
 * Premium Staff Metrics Layout Engine
 * Handles KPI metric updates, custom Chart.js visual clusters, and operations progression meters.
 */

$(document).ready(function () {

    /* ── Workspace State Configuration ── */
    // Track chart instances globally to safely destroy and re-render during life-cycle updates without causing overlap bugs
    var staffCharts = {
        preauthStatusPie: null,
        preauthDoctorBar: null,
        doctorProductionBar: null,
        labsDoctorBar: null
    };

    /* ================================================================
        FUNCTION 1: REVENUE AND PIPELINE STAT CARDS (ALL METRICS SINGLE API)
    ================================================================ */
    function loadStaffStatCards() {
        App.ajax({
            url: '/staff-dashboard/dashboard-stats.php',
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
                $('#staff-metric-total-rev').text(formatValue(data.total_cost));
                $('#cash-received').text(formatValue(data.money_received));
                $('#staff-metric-preauth-total').text(formatValue(data.preauth_total_value));
                $('#staff-metric-preauth-sent').text(formatValue(data.preauth_sent_value));
                $('#staff-metric-preauth-scheduled').text(formatValue(data.preauth_scheduled_value));
                $('#staff-metric-preauth-completed').text(formatValue(data.preauth_completed_value));
                $('#staff-metric-labs-total').text(formatValue(data.labs_total_value));

                // 2. Populate Bottom Meta Substrings Descriptions
                $('#staff-count-preauth-total').text((data.preauth_total_count || 0) + ' Requests Total');
                $('#staff-count-preauth-sent').text((data.preauth_sent_count || 0) + ' Submissions Out');
                $('#staff-count-preauth-scheduled').text((data.preauth_scheduled_count || 0) + ' Booked Procedures');
                $('#staff-count-preauth-completed').text((data.preauth_completed_count || 0) + ' Cases Finalized');
                $('#staff-count-labs-total').text((data.labs_total_count || 0) + ' Open Lab Invoices');

                // 3. Populate Secondary Row Extra Operational Metrics 
                $('#staff-metric-pending-orders').text(parseInt(data.pending_orders_count) || 0);
                $('#staff-metric-total-patients').text((parseInt(data.total_patients_count) || 0).toLocaleString());

               // 4. Calculate and Update Dynamic Monthly Budget Metrics
                var totalBudget    = parseFloat(data.total_budget || 0);
                var spentBudget    = parseFloat(data.spent_budget || 0);
                var budgetRemaining = parseFloat(data.monthly_budget_remaining || 0);
                var pctLeft         = parseFloat(data.monthly_budget_percentage_left || 0);
                
                // Update text elements with explicitly formatted values
                $('#staff-budget-total-text').text(formatValue(totalBudget));      // Total Assigned Budget
                $('#staff-budget-spent-text').text(formatValue(spentBudget));      // Total Spent Budget
                $('#staff-budget-remaining-text').text(formatValue(budgetRemaining)); // Remaining Balance
                $('#staff-budget-percentage-text').text(pctLeft.toFixed(0) + '% left');

                // OPTION A: Progress bar fills up as spending increases (Recommended for budget tracking)
                var pctSpent = totalBudget > 0 ? (spentBudget / totalBudget) * 100 : 0;
                var boundedSpentPct = Math.min(Math.max(pctSpent, 0), 100);
                $('#staff-budget-progress-fill').css('width', boundedSpentPct + '%');

                // OPTION B: Un-comment below if you want the progress bar to show remaining budget shrinking down
                // var boundedRemainingPct = Math.min(Math.max(pctLeft, 0), 100);
                // $('#staff-budget-progress-fill').css('width', boundedRemainingPct + '%');
            }
        });
    }

    /* ================================================================
        FUNCTION 2: DYNAMIC CHARTS POPULATION PIPELINE (4 SEPARATE ENDPOINTS)
    ================================================================ */
    function initStaffDashboardCharts() {
        
        // Chart 1: Pre-Auth Status Matrix Share (Pie)
        App.ajax({
            url: '/staff-dashboard/chart-preauth-status.php',
            method: 'GET',
            onSuccess: function (res) {
                if (staffCharts.preauthStatusPie) staffCharts.preauthStatusPie.destroy();
                
                var ctx = document.getElementById('chart-staff-preauth-status-pie').getContext('2d');
                staffCharts.preauthStatusPie = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: res.labels, // Expects standard array ['Requested/Sent', 'Approved', etc.]
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

        // Chart 2: Pre-Auth Revenue Pipeline metrics by Assigned Provider (Bar)
        App.ajax({
            url: '/staff-dashboard/chart-preauth-doctor-volumes.php',
            method: 'GET',
            loader: false,
            onSuccess: function (res) {
                if (staffCharts.preauthDoctorBar) staffCharts.preauthDoctorBar.destroy();
                
                var ctx = document.getElementById('chart-staff-preauth-doctor-bar').getContext('2d');
                staffCharts.preauthDoctorBar = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: res.labels, // Expects ['Dr. Alpha', 'Dr. Beta']
                        datasets: [{
                            label: 'Pre-Auth Value Pipeline ($)',
                            data: res.values,
                            backgroundColor: '#2b8a3e',
                            borderRadius: 4
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
                });
            }
        });

        // Chart 3: Payments Recovered Ledger by Doctor (Bar)
        App.ajax({
            url: '/staff-dashboard/chart-doctor-production.php',
            method: 'GET',
            loader: false,
            onSuccess: function (res) {
                if (staffCharts.doctorProductionBar) staffCharts.doctorProductionBar.destroy();
                
                var ctx = document.getElementById('chart-staff-doctor-production-bar').getContext('2d');
                staffCharts.doctorProductionBar = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: res.labels,
                        datasets: [{
                            label: 'Gross Treatment Production ($)',
                            data: res.values,
                            backgroundColor: '#37b24d',
                            borderRadius: 4
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
                });
            }
        });

        // Chart 4: Lab Revenue Attributed By Provider (Bar)
        App.ajax({
            url: '/staff-dashboard/chart-labs-doctor-volumes.php',
            method: 'GET',
            loader: false,
            onSuccess: function (res) {
                if (staffCharts.labsDoctorBar) staffCharts.labsDoctorBar.destroy();
                
                var ctx = document.getElementById('chart-staff-labs-doctor-bar').getContext('2d');
                staffCharts.labsDoctorBar = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: res.labels,
                        datasets: [{
                            label: 'Lab Value Share ($)',
                            data: res.values,
                            backgroundColor: '#845ef7',
                            borderRadius: 4
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
    loadStaffStatCards();
    initStaffDashboardCharts();

});