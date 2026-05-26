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
});

/**
 * 1. Fetch only Scheduled records for the clinic
 */
function loadScheduledAppointments(page = 1) {
    currentPage = page;

    // Show loading state
    $('#manage-appointments-tbody').html('<tr><td colspan="6" class="text-center"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</td></tr>');

    App.ajax({
        url: '/emp-pre-auth/list-scheduled.php',
        method: 'GET',
        data: { page: page },
        onSuccess: function (res) {
            // Unpack envelope wrapper data response context securely
            var dataset = res.data || res;
            renderManageTable(dataset);
        }
    });
}

/**
 * 2. Render the Management Table matching backend keys exactly
 */
function renderManageTable(records) {
    let html = '';
    if (!records || records.length === 0) {
        html = '<tr><td colspan="6" class="table-empty">No appointments scheduled at this time.</td></tr>';
    } else {
        records.forEach(r => {
            // Build out itemized procedures loop rows matching table context layout
            let treatmentsHtml = '';
            if (r.procedures_list && r.procedures_list.length > 0) {
                r.procedures_list.forEach((proc, index) => {
                    treatmentsHtml += `<div class="text-xs ${index > 0 ? 'mt-1 pt-1 border-top border-dashed' : ''}" style="border-color: rgba(0,0,0,0.05)">
                                        <strong>T${App.utils.escHtml(proc.tooth_number)}:</strong> ${App.utils.escHtml(proc.procedure_name)}
                                       </div>`;
                });
            } else {
                treatmentsHtml = `<span class="text-sm">${App.utils.escHtml(r.procedure_name || '—')}</span><br>` +
                                 `<small class="text-primary">Tooth: ${App.utils.escHtml(r.tooth_numbers || '—')}</small>`;
            }

            html += `
                <tr>
                    <td><strong>#${r.id}</strong></td>
                    <td>
                        <div class="fw-600">${App.utils.escHtml(r.patient_name)}</div>
                        <small class="text-muted"><i class="fa-regular fa-clock"></i> ${r.time_ago || '—'}</small>
                    </td>
                    <td class="text-success font-bold">${r.appointment_date_fmt || r.appointment_date || '—'}</td>
                    <td style="max-width: 240px; font-size: 0.85rem;">${treatmentsHtml}</td>
                    <td><small class="text-muted">${App.utils.escHtml(r.insurance_name || '—')}</small></td>
                    <td><span class="status-badge status-scheduled">${App.utils.escHtml(r.status || 'Scheduled')}</span></td>
                    <td class="text-right">
                        <div class="actions" style="display: flex; gap: 4px; justify-content: flex-end;">
                            <button class="btn btn-ghost btn-sm btn-view" data-id="${r.id}" title="View Timeline Pipeline Tracking Summary">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            <button class="btn btn-ghost btn-sm btn-reschedule" data-id="${r.id}" title="Reschedule Appointment" style="color: var(--color-warning)">
                                <i class="fa-solid fa-rotate-left"></i>
                            </button>
                            <button class="btn btn-ghost btn-sm btn-complete" data-id="${r.id}" title="Mark as Done" style="color: var(--color-success)">
                                <i class="fa-solid fa-check"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
        });
    }
    $('#manage-appointments-tbody').html(html);
}

/**
 * 3. Handle View Details (incorporating the precise 5-Step Pipeline Progress Framework)
 */
function handleViewDetails() {
    const id = $(this).data('id');
    
    App.ajax({
        url: '/emp-pre-auth/get.php?id=' + id,
        method: 'GET',
        loader: true,
        onSuccess: function (r) {
            // Format procedure block dynamic loop list
            let listHtml = '';
            if (r.procedures_list && r.procedures_list.length) {
                $.each(r.procedures_list, function (idx, item) {
                    listHtml += `<div style="padding: var(--sp-2) 0; border-bottom: 1px dashed #e2e8f0;">
                                    <i class="fa-solid fa-circle-chevron-right text-primary text-sm mr-2"></i> 
                                    <strong>Tooth ${item.tooth_number}:</strong> ${App.utils.escHtml(item.procedure_name)}
                                 </div>`;
                });
            } else {
                listHtml = `<div class="text-muted">No itemized treatments mapped to this pre-auth.</div>`;
            }

            const statusStr = (r.status || 'Scheduled').toLowerCase();

            // Layout Configuration Parameters for Progress Vector Pipeline
            let progressWidth = '0%';
            let themeColor = '#3b82f6';
            const isRejectedState = (statusStr === 'rejected' || statusStr === 'denied');
            const isAppealedState = (statusStr === 'appealed');

            // Step evaluation tracking flags array: 0 = Inactive, 1 = Active/Passed, 2 = Alert Flag
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
                    themeColor = '#f59e0b';
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
                    steps = [1, 1, 2, 0, 0];
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
                    <span class="status-badge" style="background: ${themeColor}; color: #fff; font-size: 0.75rem; padding: 3px 10px; border-radius: 50px; text-transform: uppercase; font-weight: 700;">${App.utils.escHtml(r.status)}</span>
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

            var approverRow = r.approved_by
                ? `<span class="text-success" style="font-weight:600;"><i class="fa-solid fa-user-check"></i> ${App.utils.escHtml(r.approver_name)}</span>`
                : '<span class="text-muted" style="font-style: italic;"><i class="fa-solid fa-hourglass-half"></i> Pending Review Evaluation</span>';

            var expireRow = r.approval_expire_date
                ? `<strong class="text-dark">${App.utils.escHtml(r.approval_expire_date)}</strong>`
                : '<span class="text-muted">—</span>';

            var editorRow = r.edited_by
                ? `<span><i class="fa-solid fa-user-pen"></i> ${App.utils.escHtml(r.editor_name)} <small class="text-muted">(${r.formatted_edit_time || r.edit_time || ''})</small></span>`
                : '<span class="text-muted" style="font-style: italic;">Never modified</span>';

            function infoRow(label, value) {
                return '<div class="info-row mb-2">' +
                    '<span class="text-muted" style="width:130px; display:inline-block; font-size:0.85rem">' + label + ':</span>' +
                    '<span class="fw-600">' + value + '</span>' +
                    '</div>';
            }

            var infoHtml = progressBarHtml +
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
                infoRow('Expiration Date', expireRow) +
                infoRow('Appointment Linked', `<span class="text-success font-bold">${App.utils.escHtml(r.appointment_date_fmt || r.appointment_date || 'None scheduled')}</span>`) +
                infoRow('Last Edited By', editorRow) +
                infoRow('Submitted By', App.utils.escHtml(r.creator_name || 'System') + ' <small class="text-muted">(' + (r.time_ago || '—') + ')</small>') +

                '<div class="mt-4 p-3" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:var(--radius-md);">' +
                '<small class="text-muted d-block mb-1" style="font-weight:700; text-transform:uppercase; font-size:0.7rem; letter-spacing:0.5px;">Internal Case & Rejection Notes:</small>' +
                '<div style="font-size:0.9rem; color:#334155;">' + App.utils.escHtml(r.notes || 'No notes left by operational staff.') + '</div>' +
                '</div>' +
                '</div>' +

                '</div>';

            $('#view-details-body').html(infoHtml);
            App.modal.open('view-modal');
        }
    });
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
        complete: function() {
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
        complete: function() {
            rescheduleRequestLock = false;
        }
    });
}