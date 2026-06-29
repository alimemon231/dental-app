/**
 * assets/js/doctor-dashboard.js
 * Premium Doctor Metrics & Performance Layout Engine
 * Handles core operational indicators and dynamic responsive custom widgets arrays.
 */

$(document).ready(function () {

    /* ── Workspace State Configuration ── */
    var dynamicChartInstances = [];

    /* ================================================================
        FUNCTION 1: MANDATORY OPERATIONAL CONTEXT (PENDING ORDERS & BUDGET)
    ================================================================ */
    function loadDoctorStatCards() {
        App.ajax({
            url: '/doctor-dashboard/dashboard-stats.php',
            method: 'GET',
            loader: false,
            onSuccess: function (data) {
                function formatValue(val) {
                    return '$' + parseFloat(val || 0).toLocaleString(undefined, {
                        minimumFractionDigits: 2, 
                        maximumFractionDigits: 2
                    });
                }

                // 1. Update Core Pending Order Count Frame
                $('#doc-metric-pending-orders').text(parseInt(data.pending_orders_count) || 0);

                // 2. Parse out Operational Monthly Allocations 
                var totalBudget     = parseFloat(data.total_budget || 0);
                var spentBudget     = parseFloat(data.spent_budget || 0);
                var budgetRemaining = parseFloat(data.monthly_budget_remaining || 0);
                var pctLeft         = parseFloat(data.monthly_budget_percentage_left || 0);
                
                $('#doc-budget-total-text').text(formatValue(totalBudget));
                $('#doc-budget-spent-text').text(formatValue(spentBudget));
                $('#doc-budget-remaining-text').text(formatValue(budgetRemaining));
                $('#doc-budget-percentage-text').text(pctLeft.toFixed(0) + '% left');

                var pctSpent = totalBudget > 0 ? (spentBudget / totalBudget) * 100 : 0;
                var boundedSpentPct = Math.min(Math.max(pctSpent, 0), 100);
                $('#doc-budget-progress-fill').css('width', boundedSpentPct + '%');
            }
        });
    }

    /* ================================================================
        FUNCTION 2: DYNAMIC CUSTOM MATRIX COMPONENT GENERATION LAYER
    ================================================================ */
    function loadCustomAnalyticsGrid() {
        App.ajax({
            url: '/doctor-dashboard/custom-statics.php',
            method: 'GET',
            loader: false,
            onSuccess: function (res) {
                var payload = res.data || res;
                var $cardContainer = $('#custom-statics-card');
                var $chartContainer = $('#custom-statics-charts');

                // Clear running state and memory nodes before injection loop
                $.each(dynamicChartInstances, function(idx, instance) {
                    if (instance) instance.destroy();
                });
                dynamicChartInstances = [];
                $cardContainer.empty();
                $chartContainer.empty();

                if (!payload || payload.length === 0) {
                    return;
                }

                // Loop over backend response arrays sequentially
                $.each(payload, function (index, widget) {
                    var chartType  = widget.chart_type; // 'card', 'pie', 'line', 'bar'
                    var chartLabel = widget.label;
                    var rawData    = widget.data;

                    if (chartType === 'card') {
                        // Value formatting checks: identify if numbers indicate financial value structures
                        var valueDisplay = rawData;
                        if (chartLabel.toLowerCase().includes('value') || chartLabel.toLowerCase().includes('revenue')) {
                            valueDisplay = '$' + parseFloat(rawData || 0).toLocaleString(undefined, {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        } else {
                            valueDisplay = parseInt(rawData).toLocaleString() || '0';
                        }

                        // Formulate minimalist card component frame string
                        var cardHtml = `
                            <div class="stat-card minimal-metric-card">
                                <div class="minimal-card-header text-muted">${App.utils.escHtml(chartLabel)}</div>
                                <div class="minimal-card-value text-primary">${valueDisplay}</div>
                                <div class="minimal-card-sub"><i class="fa-solid fa-chart-simple text-muted mr-1"></i> Active administrative metric snapshot</div>
                            </div>
                        `;
                        $cardContainer.append(cardHtml);

                    } else if (chartType === 'pie' || chartType === 'line' || chartType === 'bar') {
                        var canvasId = 'dynamic-canvas-target-' + index;

                        // Determine standard fontawesome icon matching the visual design grid
                        var iconClass = 'fa-chart-column text-info'; // Default fallback for bar
                        if (chartType === 'pie') iconClass = 'fa-chart-pie text-primary';
                        if (chartType === 'line') iconClass = 'fa-chart-line text-success';

                        // Create premium analytics wrapper panels layout matching dashboard metrics panels
                        var chartHtml = `
                            <div class="card p-4">
                                <div class="card-header pb-2 border-none">
                                    <div class="card-title text-sm font-semibold text-muted">
                                        <i class="fa-solid ${iconClass} mr-2"></i> 
                                        ${App.utils.escHtml(chartLabel)}
                                    </div>
                                </div>
                                <div class="chart-canvas-wrapper" style="position: relative; height:260px; width:100%;">
                                    <canvas id="${canvasId}"></canvas>
                                </div>
                            </div>
                        `;
                        $chartContainer.append(chartHtml);

                        // Parse coordinates out securely
                        var extractedLabels = Object.keys(rawData || {});
                        var extractedValues = Object.values(rawData || {});

                        var ctx = document.getElementById(canvasId).getContext('2d');
                        var datasetConfig = {
                            data: extractedValues,
                            borderWidth: 2
                        };

                        // Configure unique layout decorations per type rule safely
                        if (chartType === 'pie') {
                            datasetConfig.backgroundColor = ['#3498db', '#2ecc71', '#f1c40f', '#e74c3c', '#9b59b6', '#1abc9c'];
                        } else if (chartType === 'bar') {
                            datasetConfig.label = chartLabel;
                            datasetConfig.borderColor = '#17a2b8';
                            datasetConfig.backgroundColor = 'rgba(23, 162, 184, 0.65)';
                            datasetConfig.borderRadius = 4;
                        } else {
                            datasetConfig.label = chartLabel;
                            datasetConfig.borderColor = '#3498db';
                            datasetConfig.backgroundColor = 'rgba(52, 152, 219, 0.08)';
                            datasetConfig.fill = true;
                            datasetConfig.tension = 0.35;
                            datasetConfig.pointRadius = 3;
                        }

                        var chartInstance = new Chart(ctx, {
                            type: chartType,
                            data: {
                                labels: extractedLabels,
                                datasets: [datasetConfig]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: (chartType === 'line' || chartType === 'bar') ? { y: { beginAtZero: true } } : {}
                            }
                        });

                        dynamicChartInstances.push(chartInstance);
                    }
                });
            }
        });
    }

    /* ================================================================
        WORKSPACE INITIALIZATION RUNTIME ENGINE
    ================================================================ */
    loadDoctorStatCards();
    loadCustomAnalyticsGrid();

});