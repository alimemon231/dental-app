/**
 * assets/js/admin-dashboard.js
 * Premium Administration Metrics Layout Engine
 * Handles KPI metric updates, custom Chart.js visual clusters, and order tracking logs.
 */

$(document).ready(function () {

    /* ── Workspace State Configuration ── */
    var currentPage = 1;
    var perPage = 20;

    // Track chart instances globally to safely destroy and re-render during life-cycle updates
    var dashboardCharts = {
        preauthStatusPie: null,
        preauthOfficesBar: null,
        doctorProductionBar: null,
        patientSharePie: null
    };

    /* ================================================================
        FUNCTION 1: REVENUE AND PIPELINE STAT CARDS (6 STATS SINGLE API)
    ================================================================ */
    function loadStatCards() {
        App.ajax({
            url: '/admin-dashboard/dashboard-stats.php',
            method: 'GET',
            loader: false,
            onSuccess: function (data) {
                // Populate Metric Figures safely
                $('#admin-metric-total-rev').text('$' + data.total_cost.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                $('#admin-metric-preauth-total').text('$' + data.preauth_total_value.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                $('#admin-metric-preauth-approved').text('$' + data.preauth_approved_value.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                $('#admin-metric-preauth-pending').text('$' + data.preauth_pending_value.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                $('#admin-metric-preauth-scheduled').text('$' + data.preauth_scheduled_value.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                $('#admin-metric-preauth-completed').text('$' + data.preauth_completed_value.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                $('#admin-metric-labs-total').text('$' + data.labs_total_value.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));

                // Populate Context Counts Metadata Strings
                $('#admin-count-preauth-total').text(data.preauth_total_count + ' Requests Total');
                $('#admin-count-preauth-approved').text(data.preauth_approved_count + ' Approved Cases');
                $('#admin-count-preauth-pending').text(data.preauth_pending_count + ' Cases in Review');
                $('#admin-count-preauth-scheduled').text(data.preauth_scheduled_count + ' Cases Schaduled');
                $('#admin-count-preauth-completed').text(data.preauth_completed_count + ' Cases Finalized');
                $('#admin-count-labs-total').text(data.labs_total_count + ' Open Lab Invoices');
                $('#cash-received').text('$' + data.raw_gross_payments.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
            }
        });
    }

    /* ================================================================
        FUNCTION 2: DYNAMIC CHARTS POPULATION PIPELINE (4 SEPARATE CALLS)
    ================================================================ */
    function initDashboardCharts() {
        
        // Chart A: Pre-Auth Status Matrix (Pie)
        App.ajax({
            url: '/admin-dashboard/chart-preauth-status.php',
            method: 'GET',
            onSuccess: function (res) {
                if (dashboardCharts.preauthStatusPie) dashboardCharts.preauthStatusPie.destroy();
                
                var ctx = document.getElementById('chart-preauth-status-pie').getContext('2d');
                dashboardCharts.preauthStatusPie = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: res.labels, // Expecting ['Requested/Sent', 'Approved', 'Rejected', 'Scheduled', 'Appealed']
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

        // Chart B: Pre-Auth Volume By Office Site (Bar)
        App.ajax({
            url: '/admin-dashboard/chart-office-volumes.php',
            method: 'GET',
            loader: false,
            onSuccess: function (res) {
                if (dashboardCharts.preauthOfficesBar) dashboardCharts.preauthOfficesBar.destroy();
                
                var ctx = document.getElementById('chart-preauth-offices-bar').getContext('2d');
                dashboardCharts.preauthOfficesBar = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: res.labels, // Expecting ['Office A', 'Office B', 'Office C']
                        datasets: [{
                            label: 'Total Pre-Auth Value ($)',
                            data: res.values,
                            backgroundColor: '#4dabf7',
                            borderRadius: 4
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
                });
            }
        });

        // Chart C: Production Captured By Doctor (Bar)
        App.ajax({
            url: '/admin-dashboard/chart-doctor-production.php',
            method: 'GET',
            loader: false,
            onSuccess: function (res) {
                if (dashboardCharts.doctorProductionBar) dashboardCharts.doctorProductionBar.destroy();
                
                var ctx = document.getElementById('chart-doctor-production-bar').getContext('2d');
                dashboardCharts.doctorProductionBar = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: res.labels, // Expecting ['Dr. Name A', 'Dr. Name B']
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

        // Chart D: Active Patient Volume Share (Pie)
        App.ajax({
            url: '/admin-dashboard/chart-patient-share.php',
            method: 'GET',
            loader: false,
            onSuccess: function (res) {
                if (dashboardCharts.patientSharePie) dashboardCharts.patientSharePie.destroy();
                
                var ctx = document.getElementById('chart-patient-share-pie').getContext('2d');
                dashboardCharts.patientSharePie = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: res.labels, // Expecting ['Office A', 'Office B']
                        datasets: [{
                            data: res.values,
                            backgroundColor: ['#20c997', '#ff922b', '#f06595', '#845ef7'],
                            borderWidth: 1
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false }
                });
            }
        });
    }

   

    

    /* ================================================================
        WORKSPACE INITIALIZATION RUNTIME ENGINE
    ================================================================ */
    loadStatCards();
    initDashboardCharts();

});