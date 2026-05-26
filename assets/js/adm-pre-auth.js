/**
 * Admin Pre-Authorization Monitoring Logic
 * Handles global pipeline tracking, filtering, and visual status rendering.
 */

let currentPage = 1;

$(document).ready(function () {
    // 1. Initial Load
    loadClinics();
    loadAdminData(1);

    // 2. Event Listeners
    $('#btn-search').on('click', function () {
        loadAdminData(1);
    });

    $('#btn-clear').on('click', function () {
        $('#filter-patient').val('');
        $('#filter-clinic').val('');
        $('#filter-start-date').val('');
        $('#filter-end-date').val('');

        // Reset Multiselect Statuses
        $('.status-checkbox').prop('checked', false);
        $('#status-display span').text('Select Statuses').addClass('text-muted');
        $('#status-options').hide();

        loadAdminData(1);
    });

    // --- Custom Status Multiselect Logic ---
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
        let selected = [];
        $('.status-checkbox:checked').each(function () {
            selected.push($(this).val());
        });

        if (selected.length > 0) {
            $('#status-display span').text(selected.length + ' Statuses Selected').removeClass('text-muted');
        } else {
            $('#status-display span').text('Select Statuses').addClass('text-muted');
        }
    });
    // ---------------------------------------

    // Print Listener
    $('#btn-print').on('click', function () {
        printPipelineTable();
    });

    // Pagination Click Events
    $(document).on('click', '.page-link', function (e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (page) loadAdminData(page);
    });

    // View Details Click
    $(document).on('click', '.btn-view', function () {
        const id = $(this).data('id');
        viewLifecycleDetails(id);
    });
});

/**
 * Fetches clinics to populate the filter dropdown
 */
function loadClinics() {
    App.ajax({
        url: '/offices/list.php',
        onSuccess: function (data, message) {
            let html = '<option value="">All Clinics</option>';
            data.forEach(clinic => {
                html += `<option value="${clinic.id}">${App.utils.escHtml(clinic.office_name)}</option>`;
            });
            $('#filter-clinic').html(html);
        }
    });
}

/**
 * Main data fetcher with multi-dimensional filtering
 */
function loadAdminData(page = 1) {
    currentPage = page;

    // Collect multi-select statuses
    let selectedStatuses = [];
    $('.status-checkbox:checked').each(function () {
        selectedStatuses.push($(this).val());
    });

    const filters = {
        page: currentPage,
        patient_name: $('#filter-patient').val(),
        clinic_id: $('#filter-clinic').val(),
        start_date: $('#filter-start-date').val(),
        end_date: $('#filter-end-date').val(),
        status: selectedStatuses
    };

    $('#admin-tbody').html('<tr><td colspan="6" class="text-center"><i class="fa-solid fa-spinner fa-spin"></i> Fetching pipeline data...</td></tr>');

    App.ajax({
        url: '/adm-pre-auth/list.php',
        data: filters,
        onSuccess: function (response) {
            renderAdminTable(response.records);
            renderPagination(response.total_records, response.total_pages, response.records.length);
            updateAuthStatsCards(response);
        }
    });
}

/**
 * Print Functionality
 */
function printPipelineTable() {
    const tableHtml = $('#admin-table').clone();
    tableHtml.find('th:last-child, td:last-child').remove();

    const card1 = $('#card-approval-container').clone();
    const card2 = $('#card-scheduled-container').clone();
    const card3 = $('#card-completion-container').clone();

    const printWindow = window.open('', '_blank', 'height=800,width=1000');

    printWindow.document.write('<html><head><title>Pre-Auth Pipeline Report</title>');
    printWindow.document.write('<link rel="stylesheet" href="assets/css/global.css">');
    printWindow.document.write(`
        <style>
            body { padding: 40px; font-family: sans-serif; }
            h1 { margin-bottom: 5px; color: #333; }
            .print-date { margin-bottom: 30px; color: #666; font-size: 14px; }
            .stats-row { display: flex; gap: 20px; margin-bottom: 40px; width: 100%; }
            .stats-row > div { flex: 1; border: 1px solid #eee; padding: 15px; border-radius: 8px; background: #fcfcfc; }
            .progress-bar-container { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; background: #eee !important; }
            .progress-bar-container div { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        </style>
    `);

    printWindow.document.write('</head><body>');
    printWindow.document.write('<h1>Pre-Authorization Pipeline Report</h1>');
    printWindow.document.write('<p class="print-date">Report Generated: ' + new Date().toLocaleString() + '</p>');

    printWindow.document.write('<div class="stats-row">');
    printWindow.document.write(card1[0].outerHTML);
    printWindow.document.write(card2[0].outerHTML);
    printWindow.document.write(card3[0].outerHTML);
    printWindow.document.write('</div>');

    printWindow.document.write(tableHtml[0].outerHTML);
    printWindow.document.write('</body></html>');

    printWindow.document.close();
    printWindow.focus();

    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 750);
}

/**
 * Renders the 4-stage visual pipeline table matching user specifications
 */
function renderAdminTable(records) {
    if (!records || records.length === 0) {
        $('#admin-tbody').html('<tr><td colspan="6" class="table-empty">No records found matching your filters.</td></tr>');
        return;
    }

    let html = '';
    records.forEach(r => {
        const statusStr = (r.status || 'Sent').toLowerCase();
        const hasDate = !!r.appointment_date;

        // Stage 1: Sent (Always true once it hits admin log)
        const colSent = `<td class="stage-cell stage-success">Sent<br><small>${r.created_at_fmt || r.created_at}</small></td>`;

        // Stage 2: Decision (Approved / Rejected / Appealed)
        let colDecision = '';
        if (statusStr === 'rejected' || statusStr === 'denied') {
            colDecision = `<td class="stage-cell stage-danger">Rejected<br><small>By: ${App.utils.escHtml(r.approver_name || 'Admin')}</small></td>`;
        } else if (statusStr === 'appealed') {
            colDecision = `<td class="stage-cell stage-info" style="background:#e0f2fe; color:#0369a1;">Appealed<br><small>In Review</small></td>`;
        } else if (['approved', 'scheduled', 'completed', 'complete', 'done'].includes(statusStr)) {
            colDecision = `<td class="stage-cell stage-success">Approved<br><small>By: ${App.utils.escHtml(r.approver_name || 'Admin')}</small></td>`;
        } else {
            colDecision = `<td class="stage-cell stage-pending"><i class="fa-solid fa-minus"></i></td>`;
        }

        // Stage 3: Scheduled
        let colBooked = '';
        if (statusStr === 'rejected' || statusStr === 'denied') {
            colBooked = `<td class="stage-cell stage-danger" style="background:#fef2f2; opacity:0.6;"><i class="fa-solid fa-xmark"></i></td>`;
        } else if (hasDate || ['scheduled', 'completed', 'complete', 'done'].includes(statusStr)) {
            colBooked = `<td class="stage-cell stage-success">Scheduled<br><small>${r.appointment_date_fmt || r.appointment_date || 'Confirmed'}</small></td>`;
        } else {
            colBooked = `<td class="stage-cell stage-pending"><i class="fa-solid fa-minus"></i></td>`;
        }

        // Stage 4: Completed
        let colCompleted = '';
        if (statusStr === 'rejected' || statusStr === 'denied') {
            colCompleted = `<td class="stage-cell stage-danger" style="background:#fef2f2; opacity:0.6;"><i class="fa-solid fa-xmark"></i></td>`;
        } else if (['completed', 'complete', 'done'].includes(statusStr)) {
            colCompleted = `<td class="stage-cell stage-success">Completed</td>`;
        } else {
            colCompleted = `<td class="stage-cell stage-pending"><i class="fa-solid fa-minus"></i></td>`;
        }

        // Build procedures block html compatible with multi-row or flat schemas
        let proceduresHtml = '';
        if (r.procedures_list && r.procedures_list.length > 0) {
            r.procedures_list.forEach((proc, index) => {
                proceduresHtml += `<div class="text-xs ${index > 0 ? 'mt-1 pt-1 border-top border-dashed' : ''}" style="border-color: rgba(0,0,0,0.05)">
                                    <strong>T${App.utils.escHtml(proc.tooth_number)}:</strong> ${App.utils.escHtml(proc.procedure_name)}
                                   </div>`;
            });
        } else {
            proceduresHtml = `<span class="text-sm">${App.utils.escHtml(r.procedure_name || '—')}</span><br>` +
                `<small class="text-muted">Tooth: ${App.utils.escHtml(r.tooth_numbers || 'N/A')}</small>`;
        }

        html += `
            <tr>
                <td>
                    <div class="font-bold">${App.utils.escHtml(r.patient_name || (r.p_first_name + ' ' + r.p_last_name))}</div>
                    <div class="text-xs mt-1" style="max-width:240px;">${proceduresHtml}</div>
                    <div class="text-xs mt-1 text-muted"><i class="fa-solid fa-house-medical"></i> ${App.utils.escHtml(r.clinic_name || r.office_name)}</div>
                </td>
                ${colSent}
                ${colDecision}
                ${colBooked}
                ${colCompleted}
                <td>
                    <button class="btn btn-sm btn-ghost btn-view" data-id="${r.id}" title="View Timeline Lifecycle">
                        <i class="fa-solid fa-eye"></i>
                    </button>

                  <a href="/adm-add-pre-auth.php?id=${r.id}" class="btn btn-sm btn-ghost btn-view" title="Edit Pre-Auth Record">
                        <i class="fa-solid fa-pen"></i>
                  </a>
                </td>
            </tr>`;
    });
    $('#admin-tbody').html(html);
}

/**
 * Custom Pagination Handler
 */
function renderPagination(totalRecords, totalPages, currentCount) {
    $('#patients-info').text(`Showing ${currentCount} of ${totalRecords} records`);

    let html = '';
    if (totalPages > 1) {
        for (let i = 1; i <= totalPages; i++) {
            const activeClass = i === currentPage ? 'active' : '';
            html += `<button class="page-link ${activeClass}" data-page="${i}">${i}</button>`;
        }
    }
    $('#pagination-btns').html(html);
}

function viewLifecycleDetails(id) {
    App.ajax({
        url: '/adm-pre-auth/get.php', // Explicit administrative controller mapping
        method: 'GET',
        data: { id: id },
        loader: true,
        onSuccess: function (data, message) {

            // Build linked itemized procedure list rows from sub-arrays
            let listHtml = '';
            if (data.procedures_list && data.procedures_list.length) {
                $.each(data.procedures_list, function (idx, item) {
                    listHtml += `<div style="padding: var(--sp-2) 0; border-bottom: 1px dashed #e2e8f0;">
                                    <i class="fa-solid fa-circle-chevron-right text-primary text-sm mr-2"></i> 
                                    <strong>Tooth ${item.tooth_number}:</strong> ${App.utils.escHtml(item.procedure_name)}
                                 </div>`;
                });
            } else {
                listHtml = `<div class="text-muted">No itemized treatments mapped to this pre-auth.</div>`;
            }

            // Extract exact tracking arrays configuration from employee module
            const statusStr = (data.status || 'Created').toLowerCase();
            let progressWidth = '0%';
            let themeColor = '#3b82f6';
            let isRejectedState = (statusStr === 'rejected' || statusStr === 'denied');
            let isAppealedState = (statusStr === 'appealed');

            // Step state values array tracking: 0 = Default Grey, 1 = Active/Passed Accent, 2 = Red/Amber Flag
            let steps = [0, 0, 0, 0, 0];

            switch (statusStr) {
                case 'create':
                case 'created':
                case 'pending':
                    steps = [1, 0, 0, 0, 0];
                    progressWidth = '0%';
                    themeColor = '#64748b';
                    break;
                case 'sent':
                case 'processing':
                    steps = [1, 1, 0, 0, 0];
                    progressWidth = '25%';
                    themeColor = '#2563eb';
                    break;
                case 'appealed':
                    steps = [1, 1, 1, 0, 0];
                    progressWidth = '50%';
                    themeColor = '#f59e0b'; // Amber highlighting for ongoing dispute/appeal process
                    break;
                case 'approved':
                    steps = [1, 1, 1, 0, 0];
                    progressWidth = '50%';
                    themeColor = '#059669';
                    break;
                case 'scheduled':
                    steps = [1, 1, 1, 1, 0];
                    progressWidth = '75%';
                    themeColor = '#8b5cf6';
                    break;
                case 'completed':
                case 'complete':
                case 'done':
                    steps = [1, 1, 1, 1, 1];
                    progressWidth = '100%';
                    themeColor = '#10b981';
                    break;
                case 'expired':
                    steps = [1, 1, 1, 2, 0];
                    progressWidth = '75%';
                    themeColor = '#6b7280';
                    break;
                case 'rejected':
                case 'denied':
                    steps = [1, 1, 2, 0, 0]; // Milestone broken at valuation step
                    progressWidth = '50%';
                    themeColor = '#ef4444';
                    break;
                default:
                    steps = [1, 0, 0, 0, 0];
                    progressWidth = '0%';
            }

            // Node Style Evaluation Function
            function getNodeStyle(stepIndex) {
                let state = steps[stepIndex];
                if (state === 1) return `background: ${themeColor}; color: #fff; box-shadow: 0 0 0 4px #f1f5f9;`;
                if (state === 2) {
                    return isRejectedState
                        ? `background: #ef4444; color: #fff; box-shadow: 0 0 0 4px #fee2e2;`
                        : `background: #f59e0b; color: #fff; box-shadow: 0 0 0 4px #fef3c7;`;
                }
                return `background: #cbd5e1; color: #64748b;`;
            }

            // Node 3 Label calculation dynamically tracking historical flow
            let nodeThreeLabel = '3';
            if (isRejectedState) nodeThreeLabel = '✕';
            else if (isAppealedState) nodeThreeLabel = '§';

            let stepThreeText = 'Approved';
            if (isRejectedState) stepThreeText = 'Rejected';
            else if (isAppealedState) stepThreeText = 'Appealed';

            // Generate structural component timeline bar
            let progressBarHtml = `
            <div class="status-progress-wrapper" style="margin-bottom: var(--sp-6); background: #f8fafc; padding: var(--sp-5) var(--sp-4); border-radius: var(--radius-md); border: 1px solid #e2e8f0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <span class="text-xs font-bold uppercase text-muted" style="letter-spacing:0.5px;">Authorization Pipeline Status</span>
                    <span class="status-badge" style="background: ${themeColor}; color: #fff; font-size: 0.75rem; padding: 3px 10px; border-radius: 50px; text-transform: uppercase; font-weight: 700;">${data.status || 'Create'}</span>
                </div>
                
                <div class="progress-bar-container" style="position: relative; height: 6px; background: #e2e8f0; border-radius: 4px; margin: 25px var(--sp-4) 15px var(--sp-4);">
                    <div style="position: absolute; left: 0; top: 0; height: 100%; width: ${progressWidth}; background: ${themeColor}; border-radius: 4px; transition: width 0.5s ease;"></div>
                    
                    <div style="position: absolute; width: 100%; display: flex; justify-content: space-between; top: -9px; left: 0;">
                        <div style="width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight:bold; ${getNodeStyle(0)}" title="Created">1</div>
                        <div style="width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight:bold; ${getNodeStyle(1)}" title="Sent">2</div>
                        <div style="width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight:bold; ${getNodeStyle(2)}" title="Evaluation Step">${nodeThreeLabel}</div>
                        <div style="width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight:bold; ${getNodeStyle(3)}" title="Scheduled / Expired">${statusStr === 'expired' ? '✕' : '4'}</div>
                        <div style="width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight:bold; ${getNodeStyle(4)}" title="Completed Workflow">5</div>
                    </div>
                </div>
                
                <div style="display: flex; justify-content: space-between; font-size: 0.7rem; color: #64748b; font-weight: 700; padding: 0 2px; text-transform: uppercase;">
                    <span style="${steps[0] ? 'color:#1e293b' : ''}">Create</span>
                    <span style="${steps[1] ? 'color:#1e293b' : ''}">Sent</span>
                    <span style="${steps[2] ? (isRejectedState ? 'color:#ef4444' : (isAppealedState ? 'color:#f59e0b' : 'color:#1e293b')) : ''}">${stepThreeText}</span>
                    <span style="${steps[3] ? (statusStr === 'expired' ? 'color:#ef4444' : 'color:#1e293b') : ''}">Scheduled</span>
                    <span style="${steps[4] ? 'color:#10b981' : ''}">Done</span>
                </div>
            </div>`;

            // Helper structural definitions mirroring parent template
            let approverRow = data.approved_by
                ? `<span class="text-success" style="font-weight:600;"><i class="fa-solid fa-user-check"></i> ${App.utils.escHtml(data.approver_name)}</span>`
                : '<span class="text-muted" style="font-style: italic;"><i class="fa-solid fa-hourglass-half"></i> Pending Review Evaluation</span>';

            let expireRow = data.approval_expire_date
                ? `<strong class="text-dark">${App.utils.escHtml(data.approval_expire_date)}</strong>`
                : '<span class="text-muted">—</span>';

            let editorRow = data.edited_by
                ? `<span><i class="fa-solid fa-user-pen"></i> ${App.utils.escHtml(data.editor_name)} <small class="text-muted">(${data.formatted_edit_time || 'Recent'})</small></span>`
                : '<span class="text-muted" style="font-style: italic;">Never modified</span>';

            // Inline Row Builder utility structure
            function infoRow(label, value) {
                return `<div class="info-row mb-2">
                    <span class="text-muted" style="width:130px; display:inline-block; font-size:0.85rem">${label}:</span>
                    <span class="fw-600">${value}</span>
                </div>`;
            }

            // Standardize into two-column system view presentation ('grid-2')
            let unifiedHtml = progressBarHtml +
                '<div class="grid-2" style="gap:var(--sp-8)">' +

                '<div>' +
                '<div class="form-section-title mb-4" style="color:var(--color-primary); border-bottom:2px solid #f1f5f9; padding-bottom:5px;"><i class="fa-solid fa-user"></i> Patient Information</div>' +
                infoRow('Full Name', App.utils.escHtml(data.patient_name || (data.p_first_name + ' ' + data.p_last_name))) +
                infoRow('Date of Birth', App.utils.escHtml(data.p_dob || data.patient_dob || '—')) +
                infoRow('Insurance Plan', App.utils.escHtml(data.insurance_name || '—')) +
                infoRow('Member ID', App.utils.escHtml(data.p_insurance_id || '—')) +

                '<div class="form-section-title mt-6 mb-4" style="color:var(--color-primary); border-bottom:2px solid #f1f5f9; padding-bottom:5px;"><i class="fa-solid fa-stethoscope"></i> Treatment Details</div>' +
                '<div class="mb-2" style="line-height:1.6; max-height:220px; overflow-y:auto; padding-right:5px;">' + listHtml + '</div>' +
                '</div>' +

                '<div>' +
                '<div class="form-section-title mb-4" style="color:var(--color-primary); border-bottom:2px solid #f1f5f9; padding-bottom:5px;"><i class="fa-solid fa-shield-halved"></i> Authorization & Audit</div>' +
                infoRow('Clinic Site', App.utils.escHtml(data.clinic_name || data.office_name || '—')) +
                infoRow('Authorized By', approverRow) +
                infoRow('Expiration Date', expireRow) +
                infoRow('Appointment Linked', App.utils.escHtml(data.appointment_date_fmt || data.appointment_date || 'None scheduled')) +
                infoRow('Last Edited By', editorRow) +
                infoRow('Submitted By', App.utils.escHtml(data.creator_name || data.staff_name || 'System') + (data.time_ago ? ' <small class="text-muted">(' + data.time_ago + ')</small>' : '')) +

                '<div class="mt-4 p-3" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:var(--radius-md);">' +
                '<small class="text-muted d-block mb-1" style="font-weight:700; text-transform:uppercase; font-size:0.7rem; letter-spacing:0.5px;">Internal Case & Rejection Notes:</small>' +
                '<div style="font-size:0.9rem; color:#334155;">' + App.utils.escHtml(data.notes || 'No notes left by operational staff.') + '</div>' +
                '</div>' +
                '</div>' +

                '</div>';

            // Inject structural layout markup directly into target template DOM interface wrappers
            $('#view-details-body').html(unifiedHtml);
            App.modal.open('view-modal');
        }
    });
}

function infoRow(label, value) {
    return '<div class="info-row mb-2">' +
        '<span class="text-muted" style="width:130px; display:inline-block; font-size:0.85rem">' + label + ':</span>' +
        '<span class="fw-600">' + value + '</span>' +
        '</div>';
}
/**
 * Main logical wrapper to parse and update downstream multi-row mathematical progress bars
 */
function updateAuthStatsCards(response) {
    const records = response.records || [];
    const totalSent = response.total_records || records.length;

    if (totalSent === 0) {
        renderAuthEmptyCards();
        return;
    }

    // 1. Approval Card Logic: Evaluated records (Excludes 'appealed' because it is back in queue awaiting re-review)
    const evaluatedCount = records.filter(r => {
        const status = (r.status || '').toLowerCase();
        // Only consider a case fully evaluated if it's currently Approved, Rejected/Denied, Scheduled, or Completed
        return ['approved', 'rejected', 'denied', 'scheduled', 'completed', 'complete', 'done'].includes(status);
    }).length;
    renderStatCard('#card-approval-content', evaluatedCount, totalSent, 'Approved / Evaluated');

    // 2. Scheduling Card Logic: Scheduled / Completed actions mapped against the absolute Total Sent baseline
    const scheduledCount = records.filter(r => !!r.appointment_date || ['scheduled', 'completed', 'complete', 'done'].includes((r.status || '').toLowerCase())).length;
    renderStatCard('#card-scheduled-content', scheduledCount, totalSent, 'Scheduled Cases');

    // 3. Completion Card Logic: Completed records mapped against the absolute Total Sent baseline
    const completedCount = records.filter(r => ['completed', 'complete', 'done'].includes((r.status || '').toLowerCase())).length;
    renderStatCard('#card-completion-content', completedCount, totalSent, 'Completed Cases');
}

/**
 * Generic Renderer for Auth Stat Cards
 */
function renderStatCard(container, count, total, label) {
    const percent = total > 0 ? Math.round((count / total) * 100) : 0;
    const displayColor = getAuthProgressColor(percent);

    const html = `
        <div class="d-flex justify-content-between mb-1">
            <span><b>${count}</b> <small class="text-muted">${label}</small></span>
            <span class="font-bold">${percent}%</span>
        </div>
        <div class="progress-bar-container" style="background:#eee; height:8px; border-radius:4px; overflow:hidden;">
            <div style="width:${percent}%; background:${displayColor}; height:100%; transition: width 0.5s ease;"></div>
        </div>
        <div class="text-xs text-muted mt-2 text-right">Universe Baseline: ${total}</div>
    `;
    $(container).html(html);
}

/**
 * Dynamic Color Interpolator (Red to Green)
 */
function getAuthProgressColor(percent) {
    let r, g, b = 0;
    if (percent < 50) {
        r = 220;
        g = Math.round(4.4 * percent);
    } else {
        r = Math.round(440 - 4.4 * percent);
        g = 180;
    }
    return `rgb(${r}, ${g}, ${b})`;
}

/**
 * Null State Renderer
 */
function renderAuthEmptyCards() {
    const empty = '<div class="text-center text-muted p-3">No data available</div>';
    $('#card-approval-content, #card-scheduled-content, #card-completion-content').html(empty);
}