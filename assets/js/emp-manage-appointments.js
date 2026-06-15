/**
 * Manage Scheduled Appointments
 * Logic for marking procedures as 'Completed' or clearing dates to 'Reschedule'.
 */

let currentPage = 1;

$(document).ready(function () {
    loadScheduledAppointments();

    // Event Delegation for Table Actions
    $(document).on('click', '.btn-view', handleViewDetails);
    $(document).on('click', '.btn-complete', handleOpenCompleteModal);
    $(document).on('click', '.btn-reschedule', handleOpenRescheduleModal);

    // Modal Confirmation Actions
    $(document).on('click', '#btn-confirm-complete', submitCompletion);
    $(document).on('click', '#btn-confirm-reschedule', submitReschedule);

    $(document).on('click', '#btn-filter-table', function (e) {
        e.preventDefault();

        // 1. Inject the loading indicator directly into the correct scheduled table container body
        $('#manage-appointments-tbody').html(`
        <tr>
            <td colspan="6" class="text-center">
                <div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</div>
            </td>
        </tr>
    `);

        // 2. Animate the button icon funnel/spinner element asset
        var $icon = $(this).find('i');
        $icon.addClass('fa-spin');

        // 3. Force matrix hot reload jumping safely back to pagination index 1
        if (typeof loadScheduledAppointments === 'function') {
            loadScheduledAppointments(1);
        }

        // 4. Terminate the rotation rendering execution after a quick frame window delay
        setTimeout(function () {
            $icon.removeClass('fa-spin');
        }, 600);
    });
});

/**
 * 1. Fetch only Scheduled records for the clinic with active filters
 */
function loadScheduledAppointments(page) {
    page = page || 1;
    currentPage = page;

    // Show smooth loading state container matching target tbody design rules
    $('#manage-appointments-tbody').html(`
        <tr>
            <td colspan="6" class="text-center">
                <div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</div>
            </td>
        </tr>
    `);

    // Gather filter parameters from the exact same layout element nodes
    var patientName = $('#filter-patient-name').val();
    var status = $('#filter-status').val();
    var caseId = $('#filter-case-id').val();

    App.ajax({
        url: '/emp-pre-auth/list-scheduled.php',
        method: 'GET',
        loader: false,
        // Pass filter constraints alongside layout pagination properties
        data: {
            page: page,
            patient_name: patientName,
            status: status,
            case_id: caseId
        },
        onSuccess: function (res) {
            // Unpack paginated dataset records envelope safely matching both configurations
            var dataset = (res && res.records) ? res.records : (res.data || res);
            renderManageTable(dataset);
        },
        onError: function () {
            $('#manage-appointments-tbody').html(
                '<tr><td colspan="6" class="text-center"><div class="table-empty"><i class="fa-solid fa-circle-exclamation"></i> Failed to load scheduled records.</div></td></tr>'
            );
        }
    });
}

/**
 * 2. Render the Management Table utilizing row-spanning case grouping matrices
 */
function renderManageTable(records) {
    let html = '';
    if (!records || records.length === 0) {
        html = '<tr><td colspan="7" class="text-center text-muted py-4">No appointments scheduled at this time.</td></tr>';
    } else {
        records.forEach(r => {
            const childItems = r.procedures_list || [];
            const rowSpanCount = childItems.length > 0 ? childItems.length : 1;

            // Loop through each itemized scheduled procedure within this Case
            for (let i = 0; i < rowSpanCount; i++) {
                const proc = childItems[i];

                // Determine status and styling context properties
                const currentStatus = proc ? (proc.status || 'Scheduled') : (r.status || 'Scheduled');
                const statusLower = currentStatus.toLowerCase();
                const statusClass = 'status-' + statusLower;

                // Compile visual cell border separation between distinct case groups
                let rowBorderStyle = 'vertical-align: middle;';
                if (i === rowSpanCount - 1) {
                    rowBorderStyle += ' border-bottom: 2px solid #cbd5e1;';
                }

                html += `<tr style="${rowBorderStyle} transition: background-color 0.2s ease;">`;

                // ================================================================
                // COLUMN BLOCK 1: MASTER ENVELOPE CASE FIELDS (Rendered only on the first row)
                // ================================================================
                if (i === 0) {
                    html += `
                        <td rowspan="${rowSpanCount}" class="fw-bold text-center" style="background: rgba(0,0,0,0.01); border-right: 1px solid #e2e8f0; vertical-align: middle;">
                            <span class="badge bg-secondary text-dark px-2 py-1">Case #${App.utils.escHtml(r.id)}</span>
                        </td>
                        <td rowspan="${rowSpanCount}" style="vertical-align: middle;">
                            <div class="fw-600 text-primary">${App.utils.escHtml(r.patient_name)}</div>
                            <small class="text-muted d-block mt-1">
                                <i class="fa-regular fa-clock"></i> ${App.utils.escHtml(r.time_ago || '—')}
                            </small>
                            <small class="text-muted d-block mt-1">
                                <i class="fas fa-user"></i> DR: ${App.utils.escHtml(r.doctor_name || '—')}
                            </small>
                        </td>
                        <td rowspan="${rowSpanCount}" class="text-success font-bold" style="vertical-align: middle;">
                            ${App.utils.escHtml(r.appointment_date_fmt || r.appointment_date || '—')}
                        </td>
                        <td rowspan="${rowSpanCount}" style="vertical-align: middle;">
                            <small class="text-muted">${App.utils.escHtml(r.insurance_name || '—')}</small>
                        </td>
                    `;
                }

                // ================================================================
                // COLUMN BLOCK 2: SPLIT ITEMIZED PROCEDURES & WORKFLOW STATUSES
                // ================================================================
                if (proc) {
                    html += `
                        <td style="vertical-align: middle; border-right: 1px solid #f1f5f9;">
                            <span class="badge bg-light text-primary border me-1">Tooth ${App.utils.escHtml(proc.tooth_number)}</span>
                            <span class="fw-500">${App.utils.escHtml(proc.procedure_name)}</span>
                        </td>
                        <td style="vertical-align: middle; text-align: center; border-right: 1px solid #f1f5f9;">
                            <span class="status-badge ${statusClass}">${App.utils.escHtml(currentStatus)}</span>
                        </td>
                    `;
                } else {
                    html += `
                        <td style="vertical-align: middle; border-right: 1px solid #f1f5f9;">
                            <span class="text-muted">${App.utils.escHtml(r.procedure_name || '—')}</span><br>
                            <small class="text-danger">Tooth: ${App.utils.escHtml(r.tooth_numbers || '—')}</small>
                        </td>
                        <td style="vertical-align: middle; text-align: center; border-right: 1px solid #f1f5f9;">
                            <span class="status-badge status-scheduled">${App.utils.escHtml(currentStatus)}</span>
                        </td>
                    `;
                }

                // ================================================================
                // COLUMN BLOCK 3: LINE-LEVEL MANAGEMENT ACTION BUTTONS (Isolated Operations)
                // ================================================================
                const targetPreAuthId = proc ? proc.pre_auth_id : r.id;

                html += `
                    <td style="vertical-align: middle; text-align: center;">
                        <div class="row-actions" style="display: flex; gap: 4px; justify-content: center;">
                            <button class="btn btn-ghost btn-sm btn-view" data-id="${targetPreAuthId}" title="View Timeline Pipeline Tracking Summary">
                                <i class="fa-solid fa-eye text-primary"></i>
                            </button>
                            <button class="btn btn-ghost btn-sm btn-reschedule" data-id="${targetPreAuthId}" title="Reschedule Appointment" style="color: var(--color-warning)">
                                <i class="fa-solid fa-rotate-left"></i>
                            </button>
                            <button class="btn btn-ghost btn-sm btn-complete" data-id="${targetPreAuthId}" title="Mark as Done" style="color: var(--color-success)">
                                <i class="fa-solid fa-check"></i>
                            </button>
                        </div>
                    </td>
                `;

                html += `</tr>`;
            }
        });
    }
    $('#manage-appointments-tbody').html(html);
}

function handleViewDetails() {
    // Correctly extract the target record identification parameter context from the event target instance trigger
    const id = $(this).data('id');

    App.ajax({
        url: '/emp-pre-auth/get.php?id=' + id,
        method: 'GET',
        loader: true,
        onSuccess: function (r) {
            // Format procedure block dynamic loop list or structural fallback fields alignment
            let listHtml = '';
            if (r.procedures_list && r.procedures_list.length) {
                $.each(r.procedures_list, function (idx, item) {
                    listHtml += `<div style="padding: var(--sp-2) 0; border-bottom: 1px dashed #e2e8f0;">
                                    <i class="fa-solid fa-circle-chevron-right text-primary text-sm mr-2"></i> 
                                    <strong>Tooth ${App.utils.escHtml(item.tooth_number || '—')}:</strong> ${App.utils.escHtml(item.procedure_name)}
                                 </div>`;
                });
            } else if (r.procedure_name) {
                // Single-row direct properties fallback injection structural insurance
                listHtml = `<div style="padding: var(--sp-2) 0; border-bottom: 1px dashed #e2e8f0;">
                                <i class="fa-solid fa-circle-chevron-right text-primary text-sm mr-2"></i> 
                                <strong>Tooth ${App.utils.escHtml(r.tooth_number || '—')}:</strong> ${App.utils.escHtml(r.procedure_name)}
                             </div>`;
            } else {
                listHtml = `<div class="text-muted">No itemized treatments mapped to this pre-auth.</div>`;
            }

            const statusStr = (r.status || 'Requested').toLowerCase();

            // Layout Configuration Parameters for Progress Vector Pipeline
            let progressWidth = '0%';
            let themeColor = '#3b82f6';
            const isRejectedState = (statusStr === 'rejected' || statusStr === 'denied');
            const isAppealedState = (statusStr === 'appealed');

            // Step state values array tracking: 0 = Default Grey, 1 = Active/Passed Accent, 2 = Red/Amber Flag
            let steps = [0, 0, 0, 0, 0];

            switch (statusStr) {
                case 'requested':
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
                    themeColor = '#8b5cf6'; // Violet theme for Scheduled phase
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

            function getNodeStyle(stepIndex) {
                var state = steps[stepIndex];
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

            // Build out Pipeline Header UI Blocks Component
            var progressBarHtml = `
            <div class="status-progress-wrapper" style="margin-bottom: var(--sp-6); background: #f8fafc; padding: var(--sp-5) var(--sp-4); border-radius: var(--radius-md); border: 1px solid #e2e8f0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <span class="text-xs font-bold uppercase text-muted" style="letter-spacing:0.5px;">Authorization Pipeline Status</span>
                    <span class="status-badge" style="background: ${themeColor}; color: #fff; font-size: 0.75rem; padding: 3px 10px; border-radius: 50px; text-transform: uppercase; font-weight: 700;">${App.utils.escHtml(r.status || 'Requested')}</span>
                </div>
                
                <div class="progress-bar-container" style="position: relative; height: 6px; background: #e2e8f0; border-radius: 4px; margin: 25px var(--sp-4) 15px var(--sp-4);">
                    <div style="position: absolute; left: 0; top: 0; height: 100%; width: ${progressWidth}; background: ${themeColor}; border-radius: 4px; transition: width 0.5s ease;"></div>
                    
                    <div style="position: absolute; width: 100%; display: flex; justify-content: space-between; top: -9px; left: 0;">
                        <div style="width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight:bold; ${getNodeStyle(0)}" title="Requested">1</div>
                        <div style="width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight:bold; ${getNodeStyle(1)}" title="Sent">2</div>
                        <div style="width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight:bold; ${getNodeStyle(2)}" title="Evaluation Step">${nodeThreeLabel}</div>
                        <div style="width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight:bold; ${getNodeStyle(3)}" title="Scheduled / Expired">${statusStr === 'expired' ? '✕' : '4'}</div>
                        <div style="width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight:bold; ${getNodeStyle(4)}" title="Completed Workflow">5</div>
                    </div>
                </div>
                
                <div style="display: flex; justify-content: space-between; font-size: 0.7rem; color: #64748b; font-weight: 700; padding: 0 2px; text-transform: uppercase;">
                    <span style="${steps[0] ? 'color:#1e293b' : ''}">Requested</span>
                    <span style="${steps[1] ? 'color:#1e293b' : ''}">Sent</span>
                    <span style="${steps[2] ? (isRejectedState ? 'color:#ef4444' : (isAppealedState ? 'color:#f59e0b' : 'color:#1e293b')) : ''}">${stepThreeText}</span>
                    <span style="${steps[3] ? (statusStr === 'expired' ? 'color:#ef4444' : 'color:#1e293b') : ''}">Scheduled</span>
                    <span style="${steps[4] ? 'color:#10b981' : ''}">Done</span>
                </div>
            </div>`;

            var approverRow = r.approver_name
                ? `<span class="text-success" style="font-weight:600;"><i class="fa-solid fa-user-check"></i> ${App.utils.escHtml(r.approver_name)}</span>`
                : '<span class="text-muted" style="font-style: italic;"><i class="fa-solid fa-hourglass-half"></i> Pending Review Evaluation</span>';



            var editorRow = r.editor_name && r.editor_name !== '—'
                ? `<span><i class="fa-solid fa-user-pen"></i> ${App.utils.escHtml(r.editor_name)} <small class="text-muted">(${r.formatted_edit_time || r.edit_time || ''})</small></span>`
                : '<span class="text-muted" style="font-style: italic;">Never modified</span>';

            var html = progressBarHtml +
                '<div class="grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap:var(--sp-8)">' +

                '<div>' +
                '<div class="form-section-title mb-4" style="color:var(--color-primary); border-bottom:2px solid #f1f5f9; padding-bottom:5px; font-weight:700;"><i class="fa-solid fa-user"></i> Patient Information</div>' +
                infoRow('Full Name', App.utils.escHtml(r.patient_name)) +
                infoRow('Date of Birth', App.utils.escHtml(r.patient_dob || '—')) +
                infoRow('Insurance Plan', App.utils.escHtml(r.insurance_name || '—')) +

                '<div class="form-section-title mt-6 mb-4" style="color:var(--color-primary); border-bottom:2px solid #f1f5f9; padding-bottom:5px; font-weight:700;"><i class="fa-solid fa-stethoscope"></i> Treatment Details</div>' +
                '<div class="mb-2" style="line-height:1.6; max-height:220px; overflow-y:auto; padding-right:5px;">' + listHtml + '</div>' +
                '</div>' +

                '<div>' +
                '<div class="form-section-title mb-4" style="color:var(--color-primary); border-bottom:2px solid #f1f5f9; padding-bottom:5px; font-weight:700;"><i class="fa-solid fa-shield-halved"></i> Authorization & Audit</div>' +
                infoRow('Authorized By', approverRow) +
                infoRow('Expiration Date', r.procedures_list[0].approval_expire_date) +
                infoRow('Appointment Linked', `<span class="text-success font-bold">${App.utils.escHtml(r.procedures_list[0].appointment_date || 'None scheduled')}</span>`) +
                infoRow('Last Edited By', editorRow) +
                infoRow('Submitted By', App.utils.escHtml(r.creator_name || 'System') + ' <small class="text-muted">(' + (r.time_ago || '—') + ')</small>') +

                '<div class="mt-4 p-3" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:var(--radius-md);">' +
                '<small class="text-muted d-block mb-1" style="font-weight:700; text-transform:uppercase; font-size:0.7rem; letter-spacing:0.5px;">Internal Case & Rejection Notes:</small>' +
                '<div style="font-size:0.9rem; color:#334155;">' + App.utils.escHtml(r.notes || 'No notes left by operational staff.') + '</div>' +
                '</div>' +
                '</div>' +

                '</div>';

            $('#view-preauth-body').html(html);
            // Explicitly sets the specific pre_auth_id parameter on the edit redirect target trigger button
            $('#btn-edit-from-view').data('id', r.pre_auth_id || r.id);
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
 * 4. Completion Logic
 */
function handleOpenCompleteModal() {
    const id = $(this).data('id');
    $('#complete-preauth-id').val(id);
    App.modal.open('complete-modal');
}

var completeRequestLock = false;
function submitCompletion() {
    if (completeRequestLock) return;
    const id = $('#complete-preauth-id').val();
    completeRequestLock = true;

    App.ajax({
        url: '/emp-pre-auth/complete-procedure.php',
        method: 'POST',
        data: { id: id },
        loaderMsg: 'Finalizing...',
        onSuccess: function (res, msg) {
            App.toast.success('Success', 'Procedure marked as completed.');
            App.modal.close('complete-modal');
            loadScheduledAppointments(currentPage);
        },
        complete: function () {
            completeRequestLock = false;
        }
    });
}

/**
 * 5. Reschedule Logic
 */
function handleOpenRescheduleModal() {
    const id = $(this).data('id');
    $('#reschedule-preauth-id').val(id);
    App.modal.open('reschedule-modal');
}

var rescheduleRequestLock = false;
function submitReschedule() {
    if (rescheduleRequestLock) return;
    const id = $('#reschedule-preauth-id').val();
    rescheduleRequestLock = true;

    App.ajax({
        url: '/emp-pre-auth/reschedule-request.php',
        method: 'POST',
        data: { id: id },
        loaderMsg: 'Resetting date...',
        onSuccess: function (res, msg) {
            App.toast.success('Status Updated', 'Appointment cleared for rescheduling.');
            App.modal.close('reschedule-modal');
            loadScheduledAppointments(currentPage);
        },
        complete: function () {
            rescheduleRequestLock = false;
        }
    });
}