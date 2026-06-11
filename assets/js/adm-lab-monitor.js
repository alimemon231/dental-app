/**
 * Admin Lab Case Monitoring Logic
 * Handles global pipeline tracking, advanced filtering, and visual status rendering.
 */

let currentPage = 1;

$(document).ready(function () {
    // 1. Initial Load
    initDropdowns();
    loadLabMonitor(1);

    // 2. Filter Event Listeners
    $('#btn-search').on('click', function () {
        loadLabMonitor(1);
    });

    $('#btn-clear').on('click', function () {
        $('.form-control').val('');
        // Reset Multiselect
        $('.status-checkbox').prop('checked', false);
        $('#status-display span').text('Select Statuses').addClass('text-muted');
        $('#status-options').hide();

        loadLabMonitor(1);
    });

    // --- Custom Status Multiselect UI Logic ---
    $(document).on('click', '#status-display', function (e) {
        e.stopPropagation();
        $('#status-options').toggle();
    });

    $(document).on('click', function () {
        $('#status-options').hide();
    });

    $('#status-options').on('click', function (e) {
        e.stopPropagation();
    });

    $(document).on('change', '.status-checkbox', function () {
        let selectedCount = $('.status-checkbox:checked').length;
        if (selectedCount > 0) {
            $('#status-display span').text(selectedCount + ' Statuses Selected').removeClass('text-muted');
        } else {
            $('#status-display span').text('Select Statuses').addClass('text-muted');
        }
    });

    // 3. Print Listener
    $('#btn-print').on('click', function () {
        printLabPipeline();
    });

    // 4. Pagination Events - TARGET FIXED TO TARGET .page-link GRAPHIC NODES
    $(document).on('click', '.page-link', function (e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (page) loadLabMonitor(page);
    });

    // 5. View Details Click
    $(document).on('click', '.btn-view', function () {
        const id = $(this).data('id');
        if (id) viewLabLifecycle(id);
    });

    /* ================================================================
        DELETE LOGIC - DELEGATED BINDING SAFEGUARD
    ================================================================ */
    $(document).on('click', '.btn-delete', function (e) {
        e.preventDefault();
        
        // Read directly from data attributes on the clicked element container
        const id = $(this).attr('data-id') || $(this).data('id');
        const name = $(this).attr('data-name') || $(this).data('name');

        if (!id) {
            console.error("Unable to delete: target case record container has an evaluation error.");
            return;
        }

        $('#confirm-title').text('Delete Lab Case');
        $('#confirm-ok').text('Delete Permanently').removeClass('btn-success').addClass('btn-danger');
        $('#confirm-body-content').html(`
            <div class="text-center">
                <i class="fa-solid fa-trash-can text-danger mb-3" style="font-size:3rem"></i>
                <p>Delete lab case for <b>${App.utils.escHtml(name || 'this patient')}</b>?</p>
            </div>
        `);

        // Safely unbind previous references before committing the execution pipeline hook
        $('#confirm-ok').off('click').on('click', function () {
            App.modal.close('confirm-modal');
            executeDelete(id);
        });

        App.modal.open('confirm-modal');
    });
});

function executeDelete(id) {
        App.ajax({
            url: '/emp-labs/delete.php',
            method: 'POST',
            data: { id: id },
            onSuccess: function (d, msg) {
                App.toast.success('Deleted', msg);
                loadLabCases(currentPage);
            }
        });
}

/**
 * Placeholder executor function to guarantee your pipeline has a termination target endpoint
 */
function executeDelete(id) {
    App.ajax({
        url: '/emp-labs/delete.php',
        method: 'POST',
        data: { id: id },
        onSuccess: function (res, msg) {
            // Re-fire table query smoothly onto the workspace landscape view grid layout
            App.toast.success("Deleted" , msg)
            loadLabMonitor(1);
        },
        onError: function(err) {
            console.error("Deletion target drop failed: ", err);
        }
    });
}

/**
 * Populate filter dropdowns from API
 */
function initDropdowns() {
    App.ajax({
        url: '/offices/list.php',
        onSuccess: function (data) {
            if(data) data.forEach(o => $('#filter-clinic').append(`<option value="${o.id}">${o.office_name}</option>`));
        }
    });
    App.ajax({
        url: '/doctor/list.php',
        onSuccess: function (data) {
            if(data) data.forEach(p => $('#filter-provider').append(`<option value="${p.user_id}">Dr. ${p.name}</option>`));
        }
    });
    App.ajax({
        url: '/lab-cases/list.php',
        onSuccess: function (data) {
            if(data) data.forEach(t => $('#filter-lab-type').append(`<option value="${t.id}">${t.name}</option>`));
        }
    });
}

/**
 * Main Data Fetcher
 */
function loadLabMonitor(page = 1) {
    currentPage = page;

    let selectedStatuses = [];
    $('.status-checkbox:checked').each(function () {
        selectedStatuses.push($(this).val());
    });

    const filters = {
        page: currentPage,
        patient: $('#filter-patient').val(),
        office_id: $('#filter-clinic').val(),
        provider_id: $('#filter-provider').val(),
        case_type: $('#filter-lab-type').val(),
        start_date: $('#filter-start-date').val(),
        end_date: $('#filter-end-date').val(),
        statuses: selectedStatuses.join(',')
    };

    $('#lab-monitor-tbody').html('<tr><td colspan="6" class="text-center"><i class="fa-solid fa-spinner fa-spin"></i> Loading lab cases...</td></tr>');

    App.ajax({
        url: '/adm-labs/list.php',
        data: filters,
        onSuccess: function (response) {
            const records = response.records || [];
            renderLabTable(records);
            renderPagination(response.total_records || 0, response.total_pages || 1, records.length);
            updateStatsCards(response);
        },
        onError: function() {
            $('#lab-monitor-tbody').html('<tr><td colspan="6" class="text-center text-danger"><i class="fa-solid fa-circle-exclamation"></i> Error pulling lab cases matrix data.</td></tr>');
        }
    });
}

/**
 * Renders the Pipeline Table with Stage Logic
 */
function renderLabTable(records) {
    if (!records || records.length === 0) {
        $('#lab-monitor-tbody').html('<tr><td colspan="6" class="text-center p-4">No lab cases found.</td></tr>');
        return;
    }

    let html = '';
    records.forEach(r => {
        // Safe case parsing status weights logic map tracker
        const statusMap = {
            'sent': 1, 'sent': 1,
            'received': 2, 'arrived': 2,
            'scheduled': 3, 'booked': 3,
            'done': 4, 'complete': 4
        };
        const currentStatusStr = String(r.status || '').toLowerCase();
        const currentWeight = statusMap[currentStatusStr] || 0;

        // Stage 1: Sent (Weight 1+)
        const stage1 = (currentWeight >= 1)
            ? `<td class="stage-cell stage-success">Sent<br><small>${App.utils.formatDate(r.date_sent)}</small></td>`
            : `<td class="stage-cell stage-pending"><i class="fa-solid fa-clock"></i></td>`;

        // Stage 2: Arrived (Weight 2+)
        const stage2 = (currentWeight >= 2)
            ? `<td class="stage-cell stage-success">Arrived<br><small>${App.utils.formatDate(r.date_received)}</small></td>`
            : `<td class="stage-cell stage-pending"><i class="fa-solid fa-clock"></i></td>`;

        // Stage 3: Booked (Weight 3+)
        const stage3 = (currentWeight >= 3)
            ? `<td class="stage-cell stage-success">Booked<br><small>${App.utils.formatDate(r.date_scheduled)}</small></td>`
            : `<td class="stage-cell stage-pending"><i class="fa-solid fa-calendar-minus"></i></td>`;

        // Stage 4: Result (Weight 4)
        const stage4 = (currentWeight === 4)
            ? `<td class="stage-cell stage-success">Done<br><small>${App.utils.formatDate(r.date_complete || r.date_completed)}</small></td>`
            : `<td class="stage-cell stage-pending"><i class="fa-solid fa-vial"></i></td>`;

        html += `
            <tr>
                <td>
                    <div class="font-bold">${App.utils.escHtml(r.patient_name)}</div>
                    <div class="text-xs text-muted">${App.utils.escHtml(r.type_name)} | Dr. ${App.utils.escHtml(r.doctor_name)}</div>
                    <div class="text-xs mt-1"><i class="fa-solid fa-location-dot"></i> ${App.utils.escHtml(r.office_name)}</div>
                </td>
                ${stage1} ${stage2} ${stage3} ${stage4}
                <td class="text-right">
                    <button class="btn btn-sm btn-ghost btn-view" data-id="${r.id}">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                    <button href="/adm-create-lab.php?id=${r.id}" class="btn btn-sm btn-ghost" title="Edit Pre-Auth Record">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <button class="btn btn-ghost btn-sm btn-delete" data-id="${r.id}" data-name="${App.utils.escHtml(r.patient_name)}" title="Delete Permanently" style="color:var(--color-danger)">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </td>
            </tr>`;
    });
    $('#lab-monitor-tbody').html(html);
}

/**
 * Pagination Handler
 */
function renderPagination(totalRecords, totalPages, currentCount) {
    $('#patients-info').text(`Showing ${currentCount} of ${totalRecords} records`);
    let html = '';
    if (totalPages > 1) {
        for (let i = 1; i <= totalPages; i++) {
            html += `<button class="page-link ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
        }
    }
    $('#pagination-btns').html(html);
}

/**
 * Detailed Lifecycle Fetcher - Admin Monitor Module
 */
function viewLabLifecycle(id) {
    App.ajax({
        url: '/adm-labs/get.php',
        method: 'GET',
        data: { id: id },
        loader: true,
        onSuccess: function (res) {
            var r = res.hasOwnProperty('data') ? res.data : res;

            if (typeof updateProgressBar === "function") {
                updateProgressBar(r.status);
            }

            var html =
                '<div class="grid-2" style="gap:var(--sp-8); margin-top:var(--sp-6);">' +
                '<div>' +
                '<div class="form-section-title mb-4"><i class="fa-solid fa-user-doctor"></i> General Information</div>' +
                infoRow('Lab ID', '<span class="text-bold text-primary">#LAB-' + r.id + '</span>') +
                infoRow('Patient Name', App.utils.escHtml(r.p_name || r.patient_name || '—')) +
                infoRow('Provider', 'Dr. ' + App.utils.escHtml(r.doctor_name || '—')) +
                infoRow('Clinic Office', App.utils.escHtml(r.office_name || '—')) +
                infoRow('Lab Provider', App.utils.escHtml(r.lab_partner_name || '—')) +

                '<div class="form-section-title mt-6 mb-4"><i class="fa-solid fa-tooth"></i> Clinical Details</div>' +
                infoRow('Case Type', App.utils.escHtml(r.type_name || '—')) +
                infoRow('Impression', App.utils.escHtml(r.impression_type || '—')) +
                infoRow('Upper Arch', App.utils.escHtml(r.u_arch || '—')) +
                infoRow('Lower Arch', App.utils.escHtml(r.l_arch || '—')) +
                '</div>' +

                '<div>' +
                '<div class="form-section-title mb-4"><i class="fa-solid fa-circle-info"></i> Workflow Status</div>' +
                infoRow('Current Status', '<span class="status-badge status-' + (r.status || 'sent').toLowerCase() + '">' + (r.status || 'Sent') + '</span>') +
                infoRow('Date Sent', App.utils.escHtml(r.date_sent || '—')) +
                infoRow('Date Received', App.utils.escHtml(r.date_received || 'Waiting...')) +
                infoRow('Date Scheduled', App.utils.escHtml(r.date_scheduled || 'Not Booked')) +
                infoRow('Date Completed', App.utils.escHtml(r.date_completed || r.date_complete || '—')) +

                '<div class="form-section-title mt-6 mb-4"><i class="fa-solid fa-clock-rotate-left"></i> Audit Logs</div>' +
                infoRow('Sent By', App.utils.escHtml(r.created_by_name || '—')) +
                infoRow('Last Edited By', App.utils.escHtml(r.edited_by_name || '—')) +
                infoRow('Last Edited At', App.utils.escHtml(r.edited_at || '—')) +

                '<div class="form-section-title mt-6 mb-4"><i class="fa-solid fa-calendar-check"></i> Follow-up</div>' +
                infoRow('Next Procedure', App.utils.escHtml(r.next_step_name || '—')) +

                '<div class="mt-4 p-3 bg-light border-radius-sm" style="border-left: 3px solid var(--color-primary); padding-left:12px;">' +
                '<small class="text-muted d-block mb-1">Lab Notes:</small>' +
                '<div style="white-space: pre-wrap;">' + App.utils.escHtml(r.notes || 'No internal notes found for this lab case.') + '</div>' +
                '</div>' +
                '</div>' +
                '</div>';

            $('#view-details-body').html(html);
            App.modal.open('view-modal');
        }
    });
}

/**
 * Shared Helper Component for structured text metadata formatting rows
 */
function infoRow(label, value) {
    return '<div class="info-row mb-2" style="display: flex; align-items: baseline;">' +
        '<span class="text-muted" style="width: 140px; min-width: 140px; display: inline-block; font-size: 0.85rem;">' + label + ':</span>' +
        '<span class="fw-600" style="word-break: break-word;">' + value + '</span>' +
        '</div>';
}

/**
 * Main wrapper called inside loadLabMonitor onSuccess
 */
function updateStatsCards(response) {
    const records = response.records || [];
    const total = response.total_records || records.length;

    if (total === 0) {
        renderEmptyCards();
        return;
    }

    const statusWeight = {
        'sent': 1, 'received': 2, 'arrived': 2, 'scheduled': 3, 'booked': 3, 'done': 4, 'complete': 4
    };

    // 1. Calculate Completion (Status weight must be 4)
    const completedCount = records.filter(r => statusWeight[String(r.status).toLowerCase()] === 4).length;
    renderPipelineCard(completedCount, total);

    // 2. Calculate Booking Efficiency (Status weight must be >= 3)
    const bookedCount = records.filter(r => statusWeight[String(r.status).toLowerCase()] >= 3).length;
    renderEfficiencyCard(bookedCount, total);
}

function renderPipelineCard(count, total) {
    const percent = total > 0 ? Math.round((count / total) * 100) : 0;
    const color = getProgressColor(percent);

    const html = `
        <div class="d-flex justify-content-between mb-1">
            <span><b>${count}</b> <small class="text-muted">completed</small></span>
            <span class="font-bold">${percent}%</span>
        </div>
        <div class="progress-bar-container" style="background:#eee; height:8px; border-radius:4px; overflow:hidden;">
            <div style="width:${percent}%; background:${color}; height:100%; transition: width 0.5s ease;"></div>
        </div>
        <div class="text-xs text-muted mt-2 text-right">Total Cases: ${total}</div>
    `;
    $('#card-pipeline-content').html(html);
}

function renderEfficiencyCard(count, total) {
    const percent = total > 0 ? Math.round((count / total) * 100) : 0;
    const color = getProgressColor(percent);

    const html = `
        <div class="d-flex justify-content-between mb-1">
            <span><b>${count}</b> <small class="text-muted">booked</small></span>
            <span class="font-bold">${percent}%</span>
        </div>
        <div class="progress-bar-container" style="background:#eee; height:8px; border-radius:4px; overflow:hidden;">
            <div style="width:${percent}%; background:${color}; height:100%; transition: width 0.5s ease;"></div>
        </div>
        <div class="text-xs text-muted mt-2 text-right">Target: ${total} appts</div>
    `;
    $('#card-efficiency-content').html(html);
}

/**
 * Dynamic Color Interpolator
 */
function getProgressColor(percent) {
    let r = 0, g = 0, b = 0;
    if (percent < 50) {
        r = 200;
        g = Math.round(5.1 * percent);
    } else {
        r = Math.round(510 - 5.1 * percent);
        g = 200;
    }
    return `rgb(${r}, ${g}, ${b})`;
}

function renderEmptyCards() {
    const empty = '<div class="text-center text-muted p-3">No data for current filters</div>';
    $('#card-pipeline-content').html(empty);
    $('#card-efficiency-content').html(empty);
}

/**
 * Print Report Functionality
 */
function printLabPipeline() {
    const tableClone = $('#lab-monitor-table').clone();
    tableClone.find('th:last-child, td:last-child').remove(); 

    const printWin = window.open('', '_blank');
    printWin.document.write(`
        <html>
        <head>
            <title>Lab Pipeline Report</title>
            <style>
                body { font-family: sans-serif; padding: 20px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 10px; text-align: left; font-size: 12px; }
                th { background: #f4f4f4; }
                .stage-success { background-color: #6ef787 !important; }
                .stage-pending { background-color: #eddc87 !important; }
                h1 { color: #2c3e50; }
            </style>
        </head>
        <body>
            <h1>Lab Case Pipeline Report</h1>
            <p>Generated on: ${new Date().toLocaleString()}</p>
            ${tableClone[0].outerHTML}
        </body>
        </html>
    `);
    printWin.document.close();
    setTimeout(() => {
        printWin.print();
        printWin.close();
    }, 500);
}