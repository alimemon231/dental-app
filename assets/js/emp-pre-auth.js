/**
 * assets/js/emp-pre-auth.js
 * Updated with Dynamic Procedures, Insurance Loading, and Core Correction Workflows
 * Refactored for 'Appealed' status handling.
 */

$(document).ready(function () {

    /* ── State ── */
    var currentPage = 1;
    var perPage = 20;
    var editingId = null;

    // Cache for dropdown data to avoid unnecessary API calls
    var dropdownCache = {
        procedures: [],
        insurances: []
    };

    /* ================================================================
        FUNCTION 1: INITIALIZE CLIENT-SIDE LOOKUP FROM DIRECT DATA
    ================================================================ */
    function initPatientLookup() {
        App.ajax({
            url: '/emp-pre-auth/patients-list.php',
            method: 'GET',
            loader: false,
            onSuccess: function (res) {
                var dataset = res.data || res;

                var options = '<option value="">-- Search Patients --</option>';
                $.each(dataset, function (i, item) {
                    options += `<option value="${item.id}">${App.utils.escHtml(item.name)} (DOB: ${item.dob || '—'})</option>`;
                });

                $('#patient-select')
                    .html(options)
                    .select2({
                        placeholder: "Search patients...",
                        allowClear: true,
                        width: '100%'
                    });
            }
        });
    }

    /* ================================================================
        FUNCTION 2: ADD N NUMBER OF TEETH & PROCEDURE ROWS
    ================================================================ */
    // Added 3rd parameter: preAuthRowId to accurately track database record keys
    function addTreatmentRow(selectedProcedureId, selectedToothNumber, preAuthRowId) {
        var rowIdValue = preAuthRowId || '';

        var procedureOptions = '<option value="">-- Select Procedure --</option>';
        $.each(dropdownCache.procedures, function (i, p) {
            procedureOptions += `<option value="${p.id}">${App.utils.escHtml(p.name)}</option>`;
        });

        var toothOptions = '';
        for (var i = 1; i <= 32; i++) {
            var selectedAttr = (selectedToothNumber == i) ? 'selected' : '';
            toothOptions += `<option value="${i}" ${selectedAttr}>${i}</option>`;
        }

        var newRowHtml = `
            <div class="form-row treatment-row" style="grid-template-columns: 2fr 1fr 40px; gap: var(--sp-3); align-items: flex-end;">
                <input type="hidden" name="item_row_ids[]" value="${rowIdValue}">
                
                <div class="form-group">
                    <label class="form-label">Treatment Type <span class="required">*</span></label>
                    <select name="treatment_type[]" class="form-control treatment-type-select" required>
                        ${procedureOptions}
                    </select>
                    <span class="form-error">Please select a procedure.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Tooth Number <span class="required">*</span></label>
                    <select name="tooth_numbers[]" class="form-control" required>
                        ${toothOptions}
                    </select>
                </div>
                <div class="form-group" style="display: flex; justify-content: center;">
                    <button type="button" class="btn-remove-row" style="background:#fee2e2; color:#ef4444; border:1px solid #fca5a5; border-radius:var(--radius-md); height:38px; width:38px; cursor:pointer;" title="Delete Procedure">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </div>
            </div>`;

        var $newRow = $(newRowHtml);
        $('#treatments-container').append($newRow);

        if (selectedProcedureId) {
            $newRow.find('.treatment-type-select').val(selectedProcedureId);
        }

        toggleTrashButtons();
    }

    // Dynamic treatment block utilities
    $('#btn-add-treatment-row').on('click', function () {
        // Appends a completely brand new structural row entry on UI (Row ID is left empty)
        addTreatmentRow('', '1', '');
    });

    $(document).on('click', '.btn-remove-row', function () {
        if ($('.treatment-row').length > 1) {
            $(this).closest('.treatment-row').remove();
            toggleTrashButtons();
        }
    });

    function toggleTrashButtons() {
        var $rows = $('.treatment-row');
        if ($rows.length <= 1) {
            $rows.find('.btn-remove-row').css('visibility', 'hidden');
        } else {
            $rows.find('.btn-remove-row').css('visibility', 'visible');
        }
    }
    /* ================================================================
        FETCH DROPDOWN DATA
    ================================================================ */
    function loadDropdowns() {
        App.ajax({
            url: '/emp-pre-auth/load-procedures.php',
            method: 'GET',
            loader: false,
            onSuccess: function (data) {
                dropdownCache.procedures = data;
                var options = '<option value="">-- Select Procedure --</option>';
                $.each(data, function (i, p) {
                    options += `<option value="${p.id}">${App.utils.escHtml(p.name)}</option>`;
                });
                $('#treatment_type').html(options);
                $('.treatment-type-select').html(options);
            }
        });

        App.ajax({
            url: '/emp-pre-auth/load-insurance.php',
            method: 'GET',
            loader: false,
            onSuccess: function (data) {
                dropdownCache.insurances = data;
                var options = '<option value="">-- Select Insurance --</option>';
                $.each(data, function (i, ins) {
                    options += `<option value="${ins.id}">${App.utils.escHtml(ins.name)}</option>`;
                });
                $('#p_insurance_plan').html(options);
            }
        });

        App.ajax({
            url: '/emp-labs/all-doctors.php',
            method: 'GET',
            loader: false,
            onSuccess: function (data) {
                dropdownCache.insurances = data;
                var options = '<option value="">-- Select Provider --</option>';
                $.each(data, function (i, ins) {
                    options += `<option value="${ins.user_id}">${App.utils.escHtml(ins.name)}</option>`;
                });
                $('#provider').html(options);
            }
        });
    }

    /* ================================================================
        LOAD TABLE GRID DATA
    ================================================================ */
    function loadPreAuths(page) {
        page = page || 1;
        currentPage = page;

        App.ajax({
            url: '/emp-pre-auth/list.php',
            method: 'GET',
            loader: false,
            data: { page: page, limit: perPage },
            onSuccess: function (data, msg, res) {
                renderTable(data);
            },
            onError: function () {
                $('#preauth-tbody').html(
                    '<tr><td colspan="7"><div class="table-empty"><i class="fa-solid fa-circle-exclamation"></i> Failed to load records.</div></td></tr>'
                );
            }
        });
    }

    function renderTable(records) {
        let rows = '';
        if (!records || records.length === 0) {
            rows = '<tr><td colspan="8" class="text-center text-muted py-4">No authorization records found for this workspace.</td></tr>';
        } else {
            records.forEach(r => {
                const childItems = r.procedures_list || [];
                const rowSpanCount = childItems.length > 0 ? childItems.length : 1;

                // Loop through each itemized procedure within this Case
                for (let i = 0; i < rowSpanCount; i++) {
                    const proc = childItems[i];

                    // Determine status and styling attributes for each specific split row
                    const currentStatus = proc ? (proc.status || 'Create') : (r.status || 'Create');
                    const statusLower = currentStatus.toLowerCase();
                    const statusClass = 'status-' + statusLower;

                    // Define contextual row colors based on operational status rules
                    let rowColorClass = '';
                    if (statusLower === 'expired') {
                        rowColorClass = 'table-warning alert-warning-row';
                    } else if (statusLower === 'rejected' || statusLower === 'denied') {
                        rowColorClass = 'table-danger alert-danger-row';
                    } else if (statusLower === 'approved') {
                        rowColorClass = 'table-success alert-success-row';
                    } else if (statusLower === 'appealed') {
                        rowColorClass = 'table-info alert-info-row';
                    }

                    // Compile an elegant visual border separation between distinct cases
                    let rowBorderStyle = 'vertical-align: middle;';
                    if (i === rowSpanCount - 1) {
                        rowBorderStyle += ' border-bottom: 2px solid #cbd5e1;';
                    }

                    rows += `<tr class="${rowColorClass}" style="${rowBorderStyle} transition: background-color 0.2s ease;">`;

                    // ================================================================
                    // COLUMN BLOCK 1: MASTER CONTAINER CASE FIELDS (Rendered only on the first row)
                    // ================================================================
                    if (i === 0) {
                        rows += `
                        <td rowspan="${rowSpanCount}" class="fw-bold text-center" style="background: rgba(0,0,0,0.01); border-right: 1px solid #e2e8f0; vertical-align: middle;">
                            <span class="badge bg-secondary text-dark px-2 py-1">Case #${App.utils.escHtml(r.id)}</span>
                        </td>
                        <td rowspan="${rowSpanCount}" style="vertical-align: middle;">
                            <div class="fw-600 text-primary">${App.utils.escHtml(r.patient_name)}</div>
                            <small class="text-muted d-block mt-1">
                                <i class="fa-solid fa-user-doctor"></i> Dr. ${App.utils.escHtml(r.doctor_name || 'Unassigned')}
                            </small>
                            <small class="text-muted d-block">
                                <i class="fa-regular fa-clock"></i> ${proc ? proc.time_ago : r.time_ago}
                            </small>
                        </td>
                        <td rowspan="${rowSpanCount}" style="vertical-align: middle;">${App.utils.escHtml(r.patient_dob || '—')}</td>
                        <td rowspan="${rowSpanCount}" style="vertical-align: middle;">${App.utils.escHtml(r.insurance_name || '—')}</td>
                    `;
                    }

                    // ================================================================
                    // COLUMN BLOCK 2: SPLIT ITEMIZED INDIVIDUAL PATIENT TREATMENTS & STATUS
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
                            <span class="status-badge ${statusClass}">${App.utils.escHtml(currentStatus)}</span>
                        </td>
                    `;
                    }

                    // ================================================================
                    // COLUMN BLOCK 3: ITEMIZED LINE-LEVEL OPERATIONS & LIFECYCLE CONTROLS
                    // ================================================================
                    const targetPreAuthId = proc ? proc.pre_auth_id : r.id;

                    rows += `<td style="vertical-align: middle; text-align: center;">
                            <div class="row-actions" style="display: flex; gap: 4px; justify-content: center;">`;

                    // Condition A: STATUS == 'REQUESTED' (Edit, View, Send, Delete for itemized record row)
                    if (statusLower === 'requested' || statusLower === 'create') {
                        rows += `
                        <button class="btn btn-ghost btn-sm btn-edit-item" data-id="${targetPreAuthId}" title="Edit Procedure Parameters"><i class="fa-solid fa-pen text-muted"></i></button>
                        <button class="btn btn-ghost btn-sm btn-view-item" data-id="${targetPreAuthId}" title="View Details"><i class="fa-solid fa-eye text-primary"></i></button>
                        <button class="btn btn-ghost btn-sm btn-send-preauth" data-id="${targetPreAuthId}" title="Send to Management Review" style="color:var(--color-success)">
                            <i class="fa-solid fa-paper-plane"></i>
                        </button>
                        
                    `;
                    }
                    // Condition B: STATUS == 'SENT' (View, Edit, Approve, Reject lifecycle adjustments)
                    else if (statusLower === 'sent') {
                        rows += `
                        <button class="btn btn-ghost btn-sm btn-view-item" data-id="${targetPreAuthId}" title="View Clearinghouse Timeline"><i class="fa-solid fa-eye text-primary"></i></button>
                        <button class="btn btn-ghost btn-sm btn-edit-item" data-id="${targetPreAuthId}" title="Modify Details"><i class="fa-solid fa-pen text-muted"></i></button>
                        <button class="btn btn-ghost btn-sm btn-approve-preauth" data-id="${targetPreAuthId}" title="Mark as Approved" style="color:var(--color-success)">
                            <i class="fa-solid fa-circle-check"></i>
                        </button>
                        <button class="btn btn-ghost btn-sm btn-reject-preauth" data-id="${targetPreAuthId}" title="Mark as Rejected" style="color:var(--color-danger)">
                            <i class="fa-solid fa-circle-xmark"></i>
                        </button>
                    `;
                    }
                    // Condition C: STATUS == 'APPEALED' (Matches Sent controls but explicitly drops the edit option)
                    else if (statusLower === 'appealed') {
                        rows += `
                        <button class="btn btn-ghost btn-sm btn-view-item" data-id="${targetPreAuthId}" title="View Appeal Documentation"><i class="fa-solid fa-eye text-primary"></i></button>
                        <button class="btn btn-ghost btn-sm btn-approve-preauth" data-id="${targetPreAuthId}" title="Mark as Approved" style="color:var(--color-success)">
                            <i class="fa-solid fa-circle-check"></i>
                        </button>
                        <button class="btn btn-ghost btn-sm btn-reject-preauth" data-id="${targetPreAuthId}" title="Mark as Rejected" style="color:var(--color-danger)">
                            <i class="fa-solid fa-circle-xmark"></i>
                        </button>
                    `;
                    }
                    // Condition D: STATUS == 'DENIED' or 'REJECTED' (View, Edit, Appeal adjustment)
                    else if (statusLower === 'denied' || statusLower === 'rejected') {
                        rows += `
                        <button class="btn btn-ghost btn-sm btn-view-item" data-id="${targetPreAuthId}" title="View Defect Log Notes"><i class="fa-solid fa-eye text-primary"></i></button>
                        <button class="btn btn-ghost btn-sm btn-edit-item" data-id="${targetPreAuthId}" title="Correct Case Properties"><i class="fa-solid fa-pen text-muted"></i></button>
                        <button class="btn btn-ghost btn-sm btn-appeal-preauth" data-id="${targetPreAuthId}" title="File Appeal Matrix Dispute" style="color:#0284c7">
                            <i class="fa-solid fa-gavel"></i>
                        </button>
                    `;
                    }
                    // Fallback for remaining active tracking states (e.g., Approved, Expired)
                    else {
                        rows += `<button class="btn btn-ghost btn-sm btn-view-item" data-id="${targetPreAuthId}" title="View Details"><i class="fa-solid fa-eye text-primary"></i></button>`;
                    }

                    rows += `   </div>
                          </td>`;

                    // ================================================================
                    // COLUMN BLOCK 4: GLOBAL CONTAINER CASE ACTIONS (Rendered only on the first row)
                    // ================================================================
                   

                    rows += `</tr>`;
                }
            });
        }
        $('#preauth-tbody').html(rows);
    }
    /* ================================================================
        ADD NEW PRE-AUTH
    ================================================================ */
    $('#btn-add-preauth').on('click', function () {
        editingId = null;
        resetForm();
        $('#preauth-modal-title').text('Add New Pre-Auth');
        App.modal.open('preauth-modal');
    });

    /* ================================================================
        SAVE PRE-AUTH
    ================================================================ */
    $('#btn-save-preauth').on('click', function () {
        var form = $('#preauth-form');
        App.form.clearErrors(form);

        if (!App.form.validate(form)) {
            App.toast.warning('Validation', 'Please fill in all required fields.');
            return;
        }

        var formData = form.serialize();
        var isEditing = !!editingId;
        var url = isEditing ? '/emp-pre-auth/update.php' : '/emp-pre-auth/create.php';

        App.ajax({
            url: url,
            method: 'POST',
            data: formData,
            btn: $('#btn-save-preauth'),
            loaderMsg: isEditing ? 'Updating record…' : 'Creating record…',
            onSuccess: function (d, msg) {
                App.modal.close('preauth-modal');
                App.toast.success('Success', msg);
                loadPreAuths(currentPage);
            }
        });
    });

    /* ================================================================
        EDIT PRE-AUTH
    ================================================================ */
    /* ================================================================
                EDIT PRE-AUTH (CASE COMPANION CONTAINER WORKFLOW)
    ================================================================ */
    $(document).on('click', '.btn-edit-item', function () {
        var id = $(this).data('id'); // Row pre-auth record level ID pointer

        App.ajax({
            url: '/emp-pre-auth/get-edit.php?id=' + id,
            loader: true,
            onSuccess: function (r) {
                resetForm(); // Safety wipe of previously loaded selection fields

                editingId = r.case_id;
                $('#preauth-modal-title').text('Edit Pre-Auth Case File #' + r.case_id);

                // Populate form keys and drop-down structures
                $('#preauth-id').val(r.case_id);
                $('#p_insurance_plan').val(r.p_insurance_plan).trigger('change');
                $('#provider').val(r.doctor_id).trigger('change'); // Updates assigned practitioner drop-down
                $('#status').val(r.status).trigger('change');

                // Initialize or append search engine profile option metrics for the patient
                if (r.patient_id) {
                    var option = new Option(r.patient_name, r.patient_id, true, true);
                    $('#patient-select').append(option).trigger('change');
                }

                // Empty the matrix container row elements safely
                $('#treatments-container').empty();

                // Check array response parameters and map procedures to layout rows
                if (r.procedures_list && r.procedures_list.length) {
                    $.each(r.procedures_list, function (idx, entry) {
                        addTreatmentRow(entry.procedure_id, entry.tooth_number, entry.pre_auth_id);
                    });
                } else {
                    // Safe default row mapping fallback for fresh templates
                    addTreatmentRow('', '1', '');
                }

                // Open original versatile modal grid view safely
                App.modal.open('preauth-modal');
            }
        });
    });

    /* ================================================================
     VIEW PRE-AUTH DETAILS (5-STEP WORKFLOW TIMELINE GENERATOR)
   ================================================================ */
    $(document).on('click', '.btn-view-item', function () {
        var id = $(this).data('id');

        App.ajax({
            url: '/emp-pre-auth/get.php?id=' + id,
            loader: true,
            onSuccess: function (r) {
                var listHtml = '';

                // Build the treatment display row using the procedures list block array
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

                var statusStr = (r.status || 'Requested').toLowerCase();

                // Layout Configuration Parameters
                var progressWidth = '0%';
                var themeColor = '#3b82f6';
                var isRejectedState = (statusStr === 'rejected' || statusStr === 'denied');
                var isAppealedState = (statusStr === 'appealed');

                // Step state values array tracking: 0 = Default Grey, 1 = Active/Passed Accent, 2 = Red/Amber Flag
                var steps = [0, 0, 0, 0, 0];

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

                var expireRow = r.formatted_expiry
                    ? `<strong class="text-dark">${App.utils.escHtml(r.formatted_expiry)}</strong>`
                    : (r.approval_expire_date ? `<strong class="text-dark">${App.utils.escHtml(r.approval_expire_date)}</strong>` : '<span class="text-muted">—</span>');

                var editorRow = r.editor_name && r.editor_name !== '—'
                    ? `<span><i class="fa-solid fa-user-pen"></i> ${App.utils.escHtml(r.editor_name)} <small class="text-muted">(${r.formatted_edit_time})</small></span>`
                    : '<span class="text-muted" style="font-style: italic;">Never modified</span>';

                var html = progressBarHtml +
                    '<div class="grid-2" style="gap:var(--sp-8)">' +

                    '<div>' +
                    '<div class="form-section-title mb-4" style="color:var(--color-primary); border-bottom:2px solid #f1f5f9; padding-bottom:5px;"><i class="fa-solid fa-user"></i> Patient Information</div>' +
                    infoRow('Full Name', App.utils.escHtml(r.patient_name)) +
                    infoRow('Date of Birth', App.utils.escHtml(r.patient_dob || '—')) +
                    infoRow('Insurance Plan', App.utils.escHtml(r.insurance_name || '—')) +

                    '<div class="form-section-title mt-6 mb-4" style="color:var(--color-primary); border-bottom:2px solid #f1f5f9; padding-bottom:5px;"><i class="fa-solid fa-stethoscope"></i> Treatment Details</div>' +
                    '<div class="mb-2" style="line-height:1.6; max-height:220px; overflow-y:auto; padding-right:5px;">' + listHtml + '</div>' +
                    '</div>' +

                    '<div>' +
                    '<div class="form-section-title mb-4" style="color:var(--color-primary); border-bottom:2px solid #f1f5f9; padding-bottom:5px;"><i class="fa-solid fa-shield-halved"></i> Authorization & Audit</div>' +
                    infoRow('Authorized By', approverRow) +
                    infoRow('Expiration Date', expireRow) +
                    infoRow('Appointment Linked', App.utils.escHtml(r.appointment_date || 'None scheduled')) +
                    infoRow('Last Edited By', editorRow) +
                    infoRow('Submitted By', App.utils.escHtml(r.creator_name || 'System') + ' <small class="text-muted">(' + r.time_ago + ')</small>') +

                    '<div class="mt-4 p-3" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:var(--radius-md);">' +
                    '<small class="text-muted d-block mb-1" style="font-weight:700; text-transform:uppercase; font-size:0.7rem; letter-spacing:0.5px;">Internal Case & Rejection Notes:</small>' +
                    '<div style="font-size:0.9rem; color:#334155;">' + App.utils.escHtml(r.notes || 'No notes left by operational staff.') + '</div>' +
                    '</div>' +
                    '</div>' +

                    '</div>';

                $('#view-preauth-body').html(html);
                // Explicitly sets the specific pre_auth_id parameter on the edit redirect target trigger button
                $('#btn-edit-from-view').data('id', r.pre_auth_id || r.id);
                App.modal.open('view-preauth-modal');
            }
        });
    });

    function infoRow(label, value) {
        return '<div class="info-row mb-2">' +
            '<span class="text-muted" style="width:130px; display:inline-block; font-size:0.85rem">' + label + ':</span>' +
            '<span class="fw-600">' + value + '</span>' +
            '</div>';
    }
    /* ================================================================
        DELETE / DEACTIVATE PRE-AUTH
    ================================================================ */
    $(document).on('click', '.btn-delete-item', function () {
        var id = $(this).data('id');
        var name = $(this).data('name');
         $('#rejection-notes-container').hide();
        $('#approval-expiry-container').hide();
         $('#confirm-body-content').html(`
        <div class="text-center">
            <i class="fa-solid fa-circle-check text-danger mb-3" style="font-size:3rem"></i>
            <p>Are you sure you want to <strong>Delete</strong> the request for <b>${App.utils.escHtml(name)}</b>?</p>
        </div>
    `);

        App.utils.confirm(
            'Are you sure you want to delete the pre-auth for "' + name + '"? This action will remove it from the active list.',
            function () {
                App.ajax({
                    url: '/emp-pre-auth/delete.php',
                    method: 'POST',
                    data: { id: id },
                    loaderMsg: 'Deleting record...',
                    onSuccess: function (d, msg) {
                        App.toast.success('Deleted', msg);

                        if (editingId == id) {
                            resetForm();
                            App.modal.close('preauth-modal');
                        }
                        loadPreAuths(currentPage);
                    }
                });
            }
        );
    });

    /* ================================================================
        ACTION TRIGGER: DISPATCH/SEND "CREATED" PRE-AUTH TO "SENT"
    ================================================================ */
    $(document).on('click', '.btn-send-preauth', function () {
        var id = $(this).data('id');

         $('#rejection-notes-container').hide();
        $('#approval-expiry-container').hide();
         $('#confirm-body-content').html(`
        <div class="text-center">
            <i class="fa-solid fa-circle-check text-danger mb-3" style="font-size:3rem"></i>
            <p>Are you sure you want to <strong>Sent</strong> the request for <b>${App.utils.escHtml(name)}</b>?</p>
        </div>
    `);

        App.utils.confirm('Are you sure you want to mark this pre-authorization as Sent?', function () {
            App.ajax({
                url: '/emp-pre-auth/send-pre-auth.php',
                method: 'POST',
                data: { id: id },
                loader: true,
                onSuccess: function (d, msg) {
                    App.toast.success("Sent", msg);
                    loadPreAuths(1);
                }
            });
        });
    });

    /* ================================================================
        ACTION TRIGGER: REAPPLY A "REJECTED" PRE-AUTH (STATUS TO "APPEALED")
    ================================================================ */
    $(document).on('click', '.btn-appeal-preauth', function () {
        var id = $(this).data('id');

         $('#rejection-notes-container').hide();
        $('#approval-expiry-container').hide();
         $('#confirm-body-content').html(`
        <div class="text-center">
            <i class="fa-solid fa-circle-check text-danger mb-3" style="font-size:3rem"></i>
            <p>Are you sure you want to <strong>Appeal</strong> the request for <b>${App.utils.escHtml(name)}</b>?</p>
        </div>
    `);

        App.utils.confirm('Confirm changes have been made. Re-submit this rejected case as an Appeal?', function () {
            App.ajax({
                url: '/emp-pre-auth/appeal-pre-auth.php', // Points cleanly to the newly created backend controller
                method: 'POST',
                data: { id: id },
                loader: true,
                onSuccess: function (d, msg) {
                    App.toast.success("Appeal Submitted", msg);
                    loadPreAuths(currentPage);
                }
            });
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
        if (typeof loadPreAuths === 'function') {
            loadPreAuths(1);
        }

        // 4. Remove animation class from the button once request execution begins
        setTimeout(function () {
            $icon.removeClass('fa-spin');
        }, 600);
    });

    function resetForm() {
        App.form.reset(document.getElementById('preauth-form'));
        $('#patient-select').val(null).trigger('change');
        $('#treatments-container').empty();
        addTreatmentRow();
        editingId = null;
    }


    /* ================================================================
    M-STAFF ACTION HANDLERS (APPROVE / REJECT)
================================================================ */

    // Handle APPROVE Button
    $(document).on('click', '.btn-approve-preauth', function () {
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
    $(document).on('click', '.btn-reject-preauth', function () {
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
        INITIALIZE RUNTIME EXECUTION
    ================================================================ */
    initPatientLookup();
    loadDropdowns();
    loadPreAuths(1);

});