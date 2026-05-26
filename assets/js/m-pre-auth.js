let currentPage = 1;

function loadRequests(page = 1) {
    currentPage = page;
    App.ajax({
        url: '/m-pre-auth/list.php?page=' + page,
        onSuccess: function (data) {
            renderTable(data.records);
        }
    });
}

function renderTable(records) {
    let html = '';
    if (!records || records.length === 0) {
        html = '<tr><td colspan="8" class="text-center text-muted" style="padding: var(--sp-6);">No pending requests found.</td></tr>';
    } else {
        records.forEach(r => {
            let statusLower = (r.status || 'Sent').toLowerCase();
            let statusClass = 'status-' + statusLower;
            
            // 1. Establish distinct, high-contrast row highlighting for appeals vs standard pipelines
            let rowColorClass = '';
            if (statusLower === 'appealed') {
                // Custom targeted styling background tint to mark high-priority operational disputes
                rowColorClass = 'status-appealed-row'; 
            } else if (statusLower === 'rejected' || statusLower === 'denied') {
                rowColorClass = 'table-danger alert-danger-row';
            } else if (statusLower === 'approved') {
                rowColorClass = 'table-success alert-success-row';
            } else {
                rowColorClass = '';
            }

            // Format dynamic array itemized list block safely for the table cell
            let treatmentsHtml = '';
            if (r.procedures_list && r.procedures_list.length > 0) {
                r.procedures_list.forEach(proc => {
                    treatmentsHtml += `<div class="text-sm border-bottom border-dashed border-slate-100 py-1 last:border-0">
                                        <i class="fa-solid fa-caret-right text-primary mr-1"></i> ${App.utils.escHtml(proc)}
                                      </div>`;
                });
            } else {
                treatmentsHtml = '<span class="text-muted">—</span>';
            }

            html += `
                <tr class="${rowColorClass}" style="transition: background-color 0.2s ease; ${statusLower === 'appealed' ? 'background-color: #fef3c7 !important;' : ''}">
                    <td>
                        <span class="badge bg-dark text-white fw-bold" style="font-family: monospace; font-size: 0.85rem; padding: 4px 8px;">#${r.id}</span>
                    </td>

                    <td>
                        <strong>${App.utils.escHtml(r.clinic_name || '—')}</strong>
                    </td>
                    
                    <td>
                        <div class="fw-600">${App.utils.escHtml(r.patient_name || '—')}</div>
                        <small class="text-muted"><i class="fa-regular fa-clock"></i> ${r.time_ago}</small>
                    </td>
                    
                    <td>${App.utils.escHtml(r.insurance_name || '—')}</td>
                    
                    <td style="max-width: 280px;">${treatmentsHtml}</td>
                    
                    <td><small class="fw-600 text-secondary">${App.utils.escHtml(r.creator_name || 'System')}</small></td>
                    
                    <td><span class="status-badge ${statusClass}">${App.utils.escHtml(r.status)}</span></td>
                    
                    <td>
                        <div class="btn-group" style="gap: 4px;">
                            <button class="btn btn-sm btn-ghost btn-view" data-id="${r.id}" title="View Pipeline Tracker & Logs">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            ${(statusLower === 'sent' || statusLower === 'appealed') ? `
                                <button class="btn btn-sm btn-success btn-approve" data-id="${r.id}" data-name="${App.utils.escHtml(r.patient_name)}" title="Approve Request">
                                    <i class="fa-solid fa-check"></i>
                                </button>
                                <button class="btn btn-sm btn-danger btn-reject" data-id="${r.id}" data-name="${App.utils.escHtml(r.patient_name)}" title="Deny / Reject Request">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>`;
        });
    }
    $('#m-preauth-tbody').html(html);
}

/* ================================================================
    M-STAFF ACTION HANDLERS (APPROVE / REJECT)
================================================================ */

// Handle APPROVE Button
$(document).on('click', '.btn-approve', function () {
    const id = $(this).data('id');
    const name = $(this).data('name');

    // Setup Modal UI Layouts
    $('#confirm-title').text('Approve Pre-Auth');
    $('#confirm-ok').text('Approve Request').removeClass('btn-danger').addClass('btn-success');
    
    // Toggle Section Matrices
    $('#rejection-notes-container').hide();
    $('#approval-expiry-container').show();
    $('#approval-expiry-date').val(''); // Clear previous value

    $('#confirm-body-content').html(`
        <div class="text-center">
            <i class="fa-solid fa-circle-check text-success mb-3" style="font-size:3rem"></i>
            <p>Are you sure you want to <strong>APPROVE</strong> the request for <b>${App.utils.escHtml(name)}</b>?</p>
        </div>
    `);

    $('#confirm-ok').off('click').on('click', function () {
        const expiryDate = $('#approval-expiry-date').val();
        updateStatus(id, 'Approved', expiryDate, null); // Pass date to update function
        App.modal.close('confirm-modal');
    });

    App.modal.open('confirm-modal');
});

// Handle REJECT Button
$(document).on('click', '.btn-reject', function () {
    const id = $(this).data('id');
    const name = $(this).data('name');

    // Setup Modal UI Layouts
    $('#confirm-title').text('Reject Pre-Auth');
    $('#confirm-ok').text('Reject Request').removeClass('btn-success').addClass('btn-danger');

    // Toggle Section Matrices
    $('#approval-expiry-container').hide();
    $('#rejection-notes-container').show();
    $('#rejection-notes').val(''); // Clear old entries completely

    $('#confirm-body-content').html(`
        <div class="text-center">
            <i class="fa-solid fa-circle-xmark text-danger mb-3" style="font-size:3rem"></i>
            <p>Are you sure you want to <strong>REJECT</strong> the request for <b>${App.utils.escHtml(name)}</b>?</p>
            <p class="text-danger text-sm mt-2">This action will return the submission as denied.</p>
        </div>
    `);

    // Bind execution context payload capturing the text value
    $('#confirm-ok').off('click').on('click', function () {
        const notes = $('#rejection-notes').val().trim();
        updateStatus(id, 'Rejected', null, notes); // Pass text value down
        App.modal.close('confirm-modal');
    });

    App.modal.open('confirm-modal');
});

/**
 * Common function to hit the status pipeline endpoints
 */
function updateStatus(id, newStatus, expiryDate = null, notes = null) {
    App.ajax({
        url: '/m-pre-auth/update-status.php',
        method: 'POST',
        data: {
            id: id,
            status: newStatus,
            expiry_date: expiryDate,
            rejection_notes: notes // Relayed up directly to your server layer parameters
        },
        loaderMsg: 'Updating status tracking profile...',
        onSuccess: function (res, msg) {
            App.toast.success('Record updated to ' + newStatus, msg);
            if (typeof loadRequests === 'function') {
                loadRequests(currentPage); // Dynamic hot reload
            } else {
                window.location.reload();
            }
        }
    });
}
/* ================================================================
    VIEW PRE-AUTH REQUEST (M-STAFF WITH UPDATED WORKFLOW TIMELINE)
================================================================ */
$(document).on('click', '.btn-view', function () {
    const id = $(this).data('id');

    App.ajax({
        url: '/m-pre-auth/get.php?id=' + id, // Routed to your updated management endpoint
        loader: true,
        onSuccess: function (r) {
            
            // 1. Build Dynamic Multi-Procedure List Items for Clinical Section
            let listHtml = '';
            if (r.procedures_list && r.procedures_list.length) {
                $.each(r.procedures_list, function (idx, item) {
                    // Check if item is object or simple string to prevent rendering errors
                    let procName = typeof item === 'object' ? item.procedure_name : item;
                    let toothNum = typeof item === 'object' ? `Tooth ${item.tooth_number}: ` : '';
                    
                    listHtml += `<div style="padding: var(--sp-2) 0; border-bottom: 1px dashed #e2e8f0;">
                                    <i class="fa-solid fa-circle-chevron-right text-primary text-sm mr-2"></i> 
                                    <strong>${toothNum}</strong>${App.utils.escHtml(procName)}
                                 </div>`;
                });
            } else {
                listHtml = `<div class="text-muted">No itemized treatments mapped to this pre-auth.</div>`;
            }

            // 2. Synchronized Multi-Status Tracker Matrix Definitions matching emp-pre-auth.js
            const statusStr = (r.status || 'Sent').toLowerCase();
            
            // Layout Configuration Parameters
            let progressWidth = '0%';
            let themeColor = '#3b82f6'; // Default primary blue
            const isRejectedState = (statusStr === 'rejected' || statusStr === 'denied');
            const isAppealedState = (statusStr === 'appealed');

            // Step state values array tracking: 0 = Default Grey, 1 = Active/Passed Accent, 2 = Red/Amber Flag
            let steps = [0, 0, 0, 0, 0]; 

            // Map and calculate tracking line percentages sequentially based on status state
            switch (statusStr) {
                case 'create':
                case 'created':
                case 'pending':
                    steps = [1, 0, 0, 0, 0];
                    progressWidth = '0%';
                    themeColor = '#64748b'; // Slate gray
                    break;
                case 'sent':
                case 'processing':
                case 'review':
                    steps = [1, 1, 0, 0, 0];
                    progressWidth = '25%';
                    themeColor = '#2563eb'; // Deep tracking blue
                    break;
                case 'appealed':
                    steps = [1, 1, 1, 0, 0]; 
                    progressWidth = '50%';
                    themeColor = '#f59e0b'; // Amber highlighting for ongoing dispute/appeal process
                    break;
                case 'approved':
                    steps = [1, 1, 1, 0, 0];
                    progressWidth = '50%';
                    themeColor = '#059669'; // Emerald teal
                    break;
                case 'scheduled':
                    steps = [1, 1, 1, 1, 0];
                    progressWidth = '75%';
                    themeColor = '#8b5cf6'; // Scheduled purple
                    break;
                case 'completed':
                case 'complete':
                case 'done':
                    steps = [1, 1, 1, 1, 1];
                    progressWidth = '100%';
                    themeColor = '#10b981'; // Final operational green
                    break;
                case 'expired':
                    steps = [1, 1, 1, 2, 0]; 
                    progressWidth = '75%';
                    themeColor = '#6b7280';
                    break;
                case 'rejected':
                case 'denied':
                    steps = [1, 1, 2, 0, 0]; // Milestone broken at validation step
                    progressWidth = '50%';
                    themeColor = '#ef4444'; // Danger flag
                    break;
                default:
                    steps = [1, 0, 0, 0, 0];
                    progressWidth = '0%';
            }

            // Helper function to return unified inline node coloring matching current state
            function getNodeStyle(stepIndex) {
                const state = steps[stepIndex];
                if (state === 1) return `background: ${themeColor}; color: #fff; box-shadow: 0 0 0 4px #f1f5f9;`;
                if (state === 2) {
                    return isRejectedState 
                        ? `background: #ef4444; color: #fff; box-shadow: 0 0 0 4px #fee2e2;`
                        : `background: #f59e0b; color: #fff; box-shadow: 0 0 0 4px #fef3c7;`;
                }
                return `background: #cbd5e1; color: #64748b;`; // Default Unreached
            }

            // Node 3 Label calculation dynamically tracking historical flow
            let nodeThreeLabel = '3';
            if (isRejectedState) nodeThreeLabel = '✕';
            else if (isAppealedState) nodeThreeLabel = '§';

            let stepThreeText = 'Approved';
            if (isRejectedState) stepThreeText = 'Rejected';
            else if (isAppealedState) stepThreeText = 'Appealed';

            // Progress Bar HTML Module Component matching exact layout styles
            const progressBarHtml = `
                <div class="status-progress-wrapper" style="margin-bottom: var(--sp-6); background: #f8fafc; padding: var(--sp-5) var(--sp-4); border-radius: var(--radius-md); border: 1px solid #e2e8f0;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <span class="text-xs font-bold uppercase text-muted" style="letter-spacing:0.5px;">Authorization Status Pipeline</span>
                        <span class="status-badge" style="background: ${themeColor}; color: #fff; font-size: 0.75rem; padding: 3px 10px; border-radius: 50px; text-transform: uppercase; font-weight: 700;">${r.status}</span>
                    </div>
                    
                    <div class="progress-bar-container" style="position: relative; height: 6px; background: #e2e8f0; border-radius: 4px; margin: 25px var(--sp-4) 15px var(--sp-4);">
                        <div style="position: absolute; left: 0; top: 0; height: 100%; width: ${progressWidth}; background: ${themeColor}; border-radius: 4px; transition: width 0.5s ease;"></div>
                        
                        <div style="position: absolute; width: 100%; display: flex; justify-content: space-between; top: -9px; left: 0;">
                            <div style="width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight:bold; transition: all 0.3s; ${getNodeStyle(0)}" title="Created">1</div>
                            <div style="width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight:bold; transition: all 0.3s; ${getNodeStyle(1)}" title="Sent">2</div>
                            <div style="width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight:bold; transition: all 0.3s; ${getNodeStyle(2)}" title="Evaluation Step">${nodeThreeLabel}</div>
                            <div style="width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight:bold; transition: all 0.3s; ${getNodeStyle(3)}" title="Scheduled / Expired">${statusStr === 'expired' ? '✕' : '4'}</div>
                            <div style="width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight:bold; transition: all 0.3s; ${getNodeStyle(4)}" title="Completed Workflow">5</div>
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

            // 3. Setup Fallback Audits verification validation strings
            const approverRow = r.approved_by 
                ? `<span class="text-success" style="font-weight:600;"><i class="fa-solid fa-user-check"></i> ${App.utils.escHtml(r.approver_name)}</span>` 
                : '<span class="text-muted" style="font-style: italic;"><i class="fa-solid fa-hourglass-half"></i> Pending Review Evaluation</span>';

            const expireRow = r.approval_expire_date 
                ? `<strong class="text-dark">${App.utils.escHtml(r.approval_expire_date)}</strong>` 
                : '<span class="text-muted">—</span>';

            const editorRow = r.edited_by 
                ? `<span><i class="fa-solid fa-user-pen"></i> ${App.utils.escHtml(r.editor_name)} <small class="text-muted">(${r.formatted_edit_time})</small></span>` 
                : '<span class="text-muted" style="font-style: italic;">Never modified</span>';

            // 4. Construct complete grid modal UI
            const html = progressBarHtml + `
                <div class="grid-2" style="gap:var(--sp-8)">
                    <div>
                        <div class="form-section-title mb-4" style="color:var(--color-primary); border-bottom:2px solid #f1f5f9; padding-bottom:5px;">
                            <i class="fa-solid fa-hospital"></i> Origin Info
                        </div>
                        ${infoRow('Clinic Name', `<strong>${App.utils.escHtml(r.clinic_name || '—')}</strong>`)}
                        ${infoRow('Submitted By', App.utils.escHtml(r.creator_name || 'System') + ' <small class="text-muted">(' + (r.time_ago || '—') + ')</small>')}
                        ${infoRow('Date Sent', r.formatted_date || '—')}

                        <div class="form-section-title mt-6 mb-4" style="color:var(--color-primary); border-bottom:2px solid #f1f5f9; padding-bottom:5px;">
                            <i class="fa-solid fa-user"></i> Patient Details
                        </div>
                        ${infoRow('Patient Name', App.utils.escHtml(r.patient_name || '—'))}
                        ${infoRow('Date of Birth', App.utils.escHtml(r.patient_dob || '—'))}
                    </div>

                    <div>
                        <div class="form-section-title mb-4" style="color:var(--color-primary); border-bottom:2px solid #f1f5f9; padding-bottom:5px;">
                            <i class="fa-solid fa-shield-halved"></i> Authorization & Audit
                        </div>
                        ${infoRow('Insurance Plan', App.utils.escHtml(r.insurance_name || '—'))}
                        ${infoRow('Authorized By', approverRow)}
                        ${infoRow('Expiration Date', expireRow)}
                        ${infoRow('Last Edited By', editorRow)}

                        <div class="form-section-title mt-6 mb-4" style="color:var(--color-primary); border-bottom:2px solid #f1f5f9; padding-bottom:5px;">
                            <i class="fa-solid fa-stethoscope"></i> Treatment Details
                        </div>
                        <div class="mb-2" style="line-height:1.6; max-height:180px; overflow-y:auto; padding-right:5px;">
                            ${listHtml}
                        </div>
                        
                        <div class="mt-4 p-3" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:var(--radius-md);">
                            <small class="text-muted d-block mb-1" style="font-weight:700; text-transform:uppercase; font-size:0.7rem; letter-spacing:0.5px;">Internal Case & Rejection Notes:</small>
                            <div style="font-size:0.9rem; color:#334155;">${App.utils.escHtml(r.notes || r.description || 'No additional internal notes recorded.')}</div>
                        </div>
                    </div>
                </div>
            `;

            $('#view-preauth-body').html(html);
            App.modal.open('view-preauth-modal');
        }
    });
});

/* ================================================================
    ACTION TRIGGER: FORCE RUNTIME REFRESH / RELOAD LIVE RECORD PIPELINE
================================================================ */
    $(document).on('click', '#btn-refresh-table', function (e) {
        e.preventDefault();

        // 1. Immediately inject the smooth loading spinner row into the table body
        $('#preauth-tbody').html(`
        <tr>
            <td colspan="7">
                <div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>
            </td>
        </tr>
    `);

        // 2. Animate the button icon itself for secondary visual feedback
        var $icon = $(this).find('i');
        $icon.addClass('fa-spin');

        // 3. Hot reload the runtime pipeline
        if (typeof loadRequests === 'function') {
            loadRequests();
        }

        // 4. Remove animation class from the button once request execution begins
        setTimeout(function () {
            $icon.removeClass('fa-spin');
        }, 600);
    });

/**
 * Helper function for consistent row styling
 */
function infoRow(label, value) {
    return `
        <div class="info-row mb-2 d-flex">
            <span class="text-muted" style="min-width:130px; font-size:0.85rem">${label}:</span>
            <span class="fw-600">${value}</span>
        </div>`;
}

// Initial Load
$(document).ready(function () {
    loadRequests();
});