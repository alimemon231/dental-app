/**
 * Booking Management Dashboard
 * Tailored to match root level 'data' array envelopes with 5-step timeline tracking
 */

let currentPage = 1;

$(document).ready(function () {
    loadApprovedRequests();
});

/**
 * 1. Load only Approved Pre-Auths for the logged-in office
 */
function loadApprovedRequests(page = 1) {
    currentPage = page;
    
    $('#appointment-tbody').html(`
        <tr>
            <td colspan="7">
                <div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>
            </td>
        </tr>
    `);

    App.ajax({
        url: '/emp-pre-auth/list-approved.php', 
        data: { page: page },
        onSuccess: function (response) {
            // Target the root level 'data' array from your JSON wrapper
            let sourceRecords = response;
            renderTable(sourceRecords);
        }
    });
}

/**
 * 2. Render the Table with streamlined Management actions
 */
function renderTable(records) {
    let rows = '';
    if (!records || records.length === 0) {
        rows = '<tr><td colspan="7" class="text-center text-muted py-4">No appointments scheduled at this time.</td></tr>';
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

                rows += `<tr style="${rowBorderStyle} transition: background-color 0.2s ease;">`;

                // ================================================================
                // COLUMN BLOCK 1: MASTER ENVELOPE CASE FIELDS (Rendered only on the first row)
                // ================================================================
                if (i === 0) {
                    rows += `
                        <td rowspan="${rowSpanCount}" class="fw-bold text-center" style="background: rgba(0,0,0,0.01); border-right: 1px solid #e2e8f0; vertical-align: middle;">
                            <span class="badge bg-secondary text-dark px-2 py-1">Case #${App.utils.escHtml(r.id)}</span>
                        </td>
                        <td rowspan="${rowSpanCount}" style="vertical-align: middle;">
                            <div class="fw-600 text-primary">${App.utils.escHtml(r.patient_name)}</div>
                            <small class="text-muted d-block mt-1">
                                <i class="fa-regular fa-clock"></i> ${App.utils.escHtml(r.time_ago || '—')}
                            </small>
                            <small class="text-muted d-block mt-1">
                                <i class="fas fa-user"></i> DR : ${App.utils.escHtml(r.doctor_name || '—')}
                            </small>
                        </td>
                        <td rowspan="${rowSpanCount}" class="text-success font-bold" style="vertical-align: middle;">
                            ${App.utils.escHtml(r.patient_dob || r.patient_dob || '—')}
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
                    rows += `
                        <td style="vertical-align: middle; border-right: 1px solid #f1f5f9;">
                            <span class="badge bg-light text-primary border me-1">Tooth ${App.utils.escHtml(proc.tooth_number)}</span>
                            <span class="fw-500">${App.utils.escHtml(proc.procedure_name)}</span>
                        </td>
                        <td style="vertical-align: middle; text-align: center; border-right: 1px solid #f1f5f9;">
                            <span class="status-badge ${statusClass}">${App.utils.escHtml(currentStatus)}</span>
                        </td>
                    `;
                } else {
                    rows += `
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
                // COLUMN BLOCK 3: LINE-LEVEL MANAGEMENT ACTION BUTTONS (Streamlined)
                // ================================================================
                const targetPreAuthId = proc ? proc.pre_auth_id : r.id;

                rows += `
                    <td style="vertical-align: middle; text-align: center;">
                        <div class="row-actions" style="display: flex; gap: 4px; justify-content: center;">
                            <button class="btn btn-ghost btn-sm btn-view" data-id="${targetPreAuthId}" title="View Timeline Pipeline Tracking Summary">
                                <i class="fa-solid fa-eye text-primary"></i>
                            </button>
                            <button class="btn btn-ghost btn-sm btn-book-appointment" data-id="${targetPreAuthId}" title="Book Appointment" style="color: var(--color-success)">
                                <i class="fa-solid fa-calendar-plus"></i>
                            </button>
                        </div>
                    </td>
                `;

                rows += `</tr>`;
            }
        });
    }
    $('#appointment-tbody').html(rows);
}

/* ================================================================
    ACTION HANDLERS
================================================================ */

/* ================================================================
    VIEW PRE-AUTH DETAILS (5-STEP TIMELINE TRACKING WORKFLOW)
================================================================ */
$(document).on('click', '.btn-view', function () {
    const id = $(this).data('id');

    App.ajax({
        url: '/emp-pre-auth/get.php?id=' + id,
        loader: true,
        onSuccess: function (response) {
            // Unpack data object wrapper safely if returned nested
            var r = response.data ? response.data : response;

            let listHtml = '';
            if (r.procedures_list && r.procedures_list.length) {
                $.each(r.procedures_list, function (idx, item) {
                    listHtml += `<div style="padding: var(--sp-2) 0; border-bottom: 1px dashed #e2e8f0;">
                                    <i class="fa-solid fa-circle-chevron-right text-primary text-sm mr-2"></i> 
                                    <strong>Tooth ${item.tooth_number}:</strong> ${App.utils.escHtml(item.procedure_name)}
                                 </div>`;
                });
            } else {
                listHtml = `<div style="padding: var(--sp-2) 0; border-bottom: 1px dashed #e2e8f0;">
                                <i class="fa-solid fa-circle-chevron-right text-primary text-sm mr-2"></i> 
                                <strong>Tooth ${r.tooth_numbers || 'N/A'}:</strong> ${App.utils.escHtml(r.procedure_name || r.treatment_type || '—')}
                             </div>`;
            }

            let statusStr = (r.status || 'Approved').toLowerCase();

            // Pipeline Progress Tracking Elements
            let progressWidth = '50%';
            let themeColor = '#059669'; 
            let steps = [1, 1, 1, 0, 0]; 

            if (statusStr === 'scheduled') { 
                steps = [1, 1, 1, 1, 0]; 
                progressWidth = '75%'; 
                themeColor = '#8b5cf6'; 
            } else if (statusStr === 'completed' || statusStr === 'complete' || statusStr === 'done') { 
                steps = [1, 1, 1, 1, 1]; 
                progressWidth = '100%'; 
                themeColor = '#10b981'; 
            } else if (statusStr === 'expired') { 
                steps = [1, 1, 1, 2, 0]; 
                progressWidth = '75%'; 
                themeColor = '#6b7280'; 
            }

            function getNodeStyle(stepIndex) {
                let state = steps[stepIndex];
                if (state === 1) return `background: ${themeColor}; color: #fff; box-shadow: 0 0 0 4px #f1f5f9;`;
                if (state === 2) return `background: #ef4444; color: #fff; box-shadow: 0 0 0 4px #fee2e2;`;
                return `background: #cbd5e1; color: #64748b;`;
            }

            const progressBarHtml = `
            <div class="status-progress-wrapper" style="margin-bottom: var(--sp-6); background: #f8fafc; padding: var(--sp-5) var(--sp-4); border-radius: var(--radius-md); border: 1px solid #e2e8f0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <span class="text-xs font-bold uppercase text-muted" style="letter-spacing:0.5px;">Authorization Pipeline Status</span>
                    <span class="status-badge" style="background: ${themeColor}; color: #fff; font-size: 0.75rem; padding: 3px 10px; border-radius: 50px; text-transform: uppercase; font-weight: 700;">${r.status || 'Approved'}</span>
                </div>
                <div class="progress-bar-container" style="position: relative; height: 6px; background: #e2e8f0; border-radius: 4px; margin: 25px var(--sp-4) 15px var(--sp-4);">
                    <div style="position: absolute; left: 0; top: 0; height: 100%; width: ${progressWidth}; background: ${themeColor}; border-radius: 4px; transition: width 0.5s ease;"></div>
                    <div style="position: absolute; width: 100%; display: flex; justify-content: space-between; top: -9px; left: 0;">
                        <div style="width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight:bold; ${getNodeStyle(0)}">1</div>
                        <div style="width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight:bold; ${getNodeStyle(1)}">2</div>
                        <div style="width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight:bold; ${getNodeStyle(2)}">3</div>
                        <div style="width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight:bold; ${getNodeStyle(3)}">${statusStr === 'expired' ? '✕' : '4'}</div>
                        <div style="width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight:bold; ${getNodeStyle(4)}">5</div>
                    </div>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.7rem; color: #64748b; font-weight: 700; padding: 0 2px; text-transform: uppercase;">
                    <span style="${steps[0] ? 'color:#1e293b' : ''}">Create</span>
                    <span style="${steps[1] ? 'color:#1e293b' : ''}">Sent</span>
                    <span style="${steps[2] ? 'color:#059669' : ''}">Approved</span>
                    <span style="${steps[3] ? 'color:#1e293b' : ''}">Scheduled</span>
                    <span style="${steps[4] ? 'color:#10b981' : ''}">Done</span>
                </div>
            </div>`;

            var approverRow = r.approver_name
                ? `<span class="text-success" style="font-weight:600;"><i class="fa-solid fa-user-check"></i> ${App.utils.escHtml(r.approver_name || 'Management')}</span>` 
                : '<span class="text-muted" style="font-style: italic;">—</span>';

            var editorRow = r.edited_by 
                ? `<span><i class="fa-solid fa-user-pen"></i> ${App.utils.escHtml(r.editor_name)}</span>` 
                : '<span class="text-muted" style="font-style: italic;">Never modified</span>';

            const html = progressBarHtml + `
                <div class="grid-2" style="gap:var(--sp-8); display: grid; grid-template-columns: repeat(2, minmax(0, 1fr));">
                    <div>
                        <div class="form-section-title mb-4" style="color:var(--color-primary); border-bottom:2px solid #f1f5f9; padding-bottom:5px; font-weight:700;"><i class="fa-solid fa-user"></i> Patient Information</div>
                        ${infoRow('Full Name', App.utils.escHtml(r.patient_name || (r.p_first_name + ' ' + r.p_last_name)))}
                        ${infoRow('Date of Birth', App.utils.escHtml(r.patient_dob || r.p_dob || '—'))}
                        ${infoRow('Insurance Plan', App.utils.escHtml(r.insurance_name || r.p_insurance_plan || '—'))}

                        <div class="form-section-title mt-6 mb-4" style="color:var(--color-primary); border-bottom:2px solid #f1f5f9; padding-bottom:5px; font-weight:700;"><i class="fa-solid fa-stethoscope"></i> Treatment Details</div>
                        <div class="mb-2" style="line-height:1.6; max-height:220px; overflow-y:auto; padding-right:5px;">${listHtml}</div>
                    </div>
                    <div>
                        <div class="form-section-title mb-4" style="color:var(--color-primary); border-bottom:2px solid #f1f5f9; padding-bottom:5px; font-weight:700;"><i class="fa-solid fa-shield-halved"></i> Authorization & Audit</div>
                        ${infoRow('Current Status', `<span class="status-badge status-${statusStr}">${r.status}</span>`)}
                        ${infoRow('Authorized By', approverRow)}
                        ${infoRow('Expiration Date', r.procedures_list[0].approval_expire_date ? `<strong class="text-dark">${App.utils.escHtml(r.procedures_list[0].approval_expire_date)}</strong>` : '—')}
                        ${infoRow('Last Edited By', editorRow)}
                        ${infoRow('Submitted By', App.utils.escHtml(r.creator_name || r.staff_name || 'System') + ' <small class="text-muted">(' + (r.time_ago || '—') + ')</small>')}
                        
                        <div class="mt-4 p-3" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:var(--radius-md);">
                            <small class="text-muted d-block mb-1" style="font-weight:700; text-transform:uppercase; font-size:0.7rem; letter-spacing:0.5px;">Office Notes:</small>
                            <div style="font-size:0.9rem; color:#334155;">${App.utils.escHtml(r.notes || r.description || 'No operational tracking notes left by staff.')}</div>
                        </div>
                        
                        ${r.appointment_date ? `
                            <div class="mt-4 p-3" style="background: #f0fdf4; border-radius: var(--radius-md); border: 1px solid #bbf7d0;">
                                <small class="text-success d-block mb-1" style="font-weight:700; text-transform:uppercase; font-size:0.7rem; letter-spacing:0.5px;">Scheduled For:</small>
                                <strong class="text-success">${r.appointment_date}</strong>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
            $('#view-details-body').html(html); 
            App.modal.open('view-modal');
        }
    });
});

// Handle BOOK Button (Calendar Icon) - Open Modal
$(document).on('click', '.btn-book-appointment', function () {
    const id = $(this).data('id');
    $('#book-preauth-id').val(id); // Set hidden ID
    $('#appointment_date').val(''); // Clear previous value
    App.modal.open('book-modal');
});

// Handle CONFIRM Booking (Save Appointment Date)
$(document).on('click', '#btn-confirm-book', function () {
    const id = $('#book-preauth-id').val();
    const dateValue = $('#appointment_date').val();

    if (!dateValue) {
        App.toast.error('Please select a valid date.');
        return;
    }

    App.ajax({
        url: '/emp-pre-auth/book-appointment.php',
        method: 'POST',
        data: {
            id: id,
            appointment_date: dateValue
        },
        loaderMsg: 'Scheduling...',
        onSuccess: function (res, msg) {
            App.toast.success('Appointment Scheduled', msg);
            App.modal.close('book-modal');
            loadApprovedRequests(currentPage); // Refresh table
        }
    });
});

/**
 * Helper function for consistent row styling
 */
function infoRow(label, value) {
    return `
        <div class="info-row mb-2 d-flex">
            <span class="text-muted" style="min-width:130px; font-size:0.85rem">${label}:</span>
            <span class="text-dark fw-600">${value}</span>
        </div>`;
}