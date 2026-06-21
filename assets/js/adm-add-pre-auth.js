/**
 * assets/js/admin-pre-auth.js
 * Global Admin Pre-Authorization Workspace
 * Handles multi-clinic pipeline tracking, advanced filtering, row-spanned data grouping, and full lifecycle overrides.
 */

$(document).ready(function () {

    /* ── State & Configuration ── */
    var currentPage = 1;
    var perPage = 20;
    var editingId = null;
    var filterTimer = null;

    // Cache for global dropdown data
    var dropdownCache = {
        procedures: [],
        insurances: []
    };

    /* ================================================================
        FUNCTION 1: INITIALIZE CLINICS & GLOBAL LOOKUPS
    ================================================================ */
    // Fetches clinics to populate both the filter dropdown and the form selection
    function loadClinics() {
        App.ajax({
            url: '/offices/list.php', // Admin scope endpoint fetching all clinics
            method: 'GET',
            loader: false,
            onSuccess: function (data) {
                var filterHtml = '<option value="">All Clinic Locations</option>';
                var formHtml = '<option value="">Select clinic site context setup...</option>';

                $.each(data, function (i, clinic) {
                    var opt = `<option value="${clinic.id}">${App.utils.escHtml(clinic.office_name)}</option>`;
                    filterHtml += opt;
                    formHtml += opt;
                });

                $('#filter-office-id').html(filterHtml);
                $('#office-select').html(formHtml);
            }
        });
    }

    // Fetches global datasets (Insurances and Procedures) that are not tied to a specific office
    function loadGlobalDropdowns() {
        App.ajax({
            url: '/procedures/list.php',
            method: 'GET',
            loader: false,
            onSuccess: function (data) {

                dropdownCache.procedures = data;

                var options = '<option value="">-- Select Procedure --</option>';
                $.each(data, function (i, p) {
                    options += `<option value="${p.id}">${App.utils.escHtml(p.name)} - <small>$ ${p.cost}</small></option>`;
                });
                $('.treatment-type-select').html(options);
            }
        });

        App.ajax({
            url: '/insurance/list.php',
            method: 'GET',
            loader: false,
            onSuccess: function (data) {
                dropdownCache.insurances = data;
                var options = '<option value="">Select active plan...</option>';
                $.each(data, function (i, ins) {
                    options += `<option value="${ins.id}">${App.utils.escHtml(ins.name)}</option>`;
                });
                $('#p_insurance_plan').html(options);
            }
        });
    }

    /* ================================================================
        FUNCTION 2: DYNAMIC OFFICE-SPECIFIC DATA LOADER
    ================================================================ */
    // Listens for clinic selection changes and triggers separate AJAX calls for scoped data
    $('#office-select').on('change', function () {
        var officeId = $(this).val();

        if (officeId) {
            loadOfficeSpecificData(officeId);
        } else {
            // Reset fields if no office is selected
            $('#patient-select').html('<option value="">Search names (Select Clinic first)...</option>').trigger('change');
            $('#provider').html('<option value="">Select clinician doctor...</option>');
        }
    });

    function loadOfficeSpecificData(officeId, preselectedPatientId = null, preselectedDoctorId = null) {
        // 1. Fetch Patients tied to this specific clinic
        App.ajax({
            url: '/adm-pre-auth/select-patients-by-office.php',
            method: 'GET',
            data: { office_id: officeId },
            loader: false,
            onSuccess: function (res) {
                var dataset = res.data || res;
                var options = '<option value="">-- Search Patients --</option>';
                $.each(dataset, function (i, item) {
                    options += `<option value="${item.id}">${App.utils.escHtml(item.name)} (DOB: ${item.dob || '—'})</option>`;
                });

                var $patientSelect = $('#patient-select');
                $patientSelect.html(options).select2({
                    placeholder: "Search patients...",
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#preauth-modal') // Fixes Select2 focus issues inside modals
                });

                if (preselectedPatientId) {
                    $patientSelect.val(preselectedPatientId).trigger('change');
                }
            }
        });

        // 2. Fetch Doctors tied to this specific clinic
        App.ajax({
            url: '/offices/select_assigned_doctors.php',
            method: 'POST',
            data: { id: officeId },
            loader: false,
            onSuccess: function (data) {
                var dataset = data.data || data;
                var options = '<option value="">Select clinician doctor...</option>';
                $.each(dataset, function (i, doc) {
                    options += `<option value="${doc.user_id}">${App.utils.escHtml(doc.name)}</option>`;
                });

                var $providerSelect = $('#provider');
                $providerSelect.html(options);

                if (preselectedDoctorId) {
                    $providerSelect.val(preselectedDoctorId).trigger('change');
                }
            }
        });
    }

    /* ================================================================
        FUNCTION 3: TREATMENT ROW MANAGEMENT
    ================================================================ */
    function addTreatmentRow(selectedProcedureId, selectedToothNumber, preAuthRowId, preAuthPrice) {
        var rowIdValue = preAuthRowId || '';
        var currentPriceValue = preAuthPrice || '';

        var procedureOptions = '<option value="">-- Select Procedure --</option>';
        $.each(dropdownCache.procedures, function (i, p) {
            // Safe double-precision extraction fallback to 0.00
            var costValue = parseFloat(p.cost || 0);
            var formattedCost = costValue.toFixed(2);

            procedureOptions += `<option value="${p.id}" data-price="${formattedCost}">${App.utils.escHtml(p.name)} - $${formattedCost}</option>`;
        });

        var toothOptions = '';
        for (var i = 1; i <= 32; i++) {
            var selectedAttr = (selectedToothNumber == i) ? 'selected' : '';
            toothOptions += `<option value="${i}" ${selectedAttr}>Tooth ${i}</option>`;
        }

        var newRowHtml = `
        <div class="form-row treatment-row" style="grid-template-columns: 2fr 1fr 40px; gap: var(--sp-3); align-items: flex-end;">
            <input type="hidden" name="item_row_ids[]" value="${rowIdValue}">
            <input type="hidden" name="treatment_price[]" class="treatment-price-hidden" value="${currentPriceValue}">
            
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
                    <option value="">—</option>
                    ${toothOptions}
                </select>
            </div>
            <div class="form-group" style="display: flex; justify-content: center;">
                <button type="button" class="btn-remove-row" title="Delete Procedure">
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

    // Automatically track and copy selected price points to hidden inputs
    $(document).on('change', '.treatment-type-select', function () {
        var $selectedOption = $(this).find('option:selected');
        var selectedPrice = $selectedOption.data('price') || '';

        // Find the hidden input inside the exact same treatment-row block and update it
        $(this).closest('.treatment-row').find('.treatment-price-hidden').val(selectedPrice);
    });

    $('#btn-add-treatment-row').on('click', function () {
        addTreatmentRow('', '', '');
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
        FUNCTION 4: LOAD TABLE GRID DATA & FILTERING
    ================================================================ */
    function loadPreAuths(page) {
        page = page || 1;
        currentPage = page;

        var filters = {
            page: currentPage,
            limit: perPage,
            patient_name: $('#filter-patient-name').val(),
            office_id: $('#filter-office-id').val(),
            status: $('#filter-status').val(),
            case_id: $('#filter-case-id').val()
        };

        $('#preauth-tbody').html('<tr><td colspan="8"><div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Fetching admin pipeline telemetry...</div></td></tr>');

        App.ajax({
            url: '/admin-pre-auth/list.php',
            method: 'GET',
            data: filters,
            loader: false,
            onSuccess: function (response) {
                var records = response.records || response.data || response;
                renderTable(records);

                if (response.total_records !== undefined) {
                    renderPagination(response.total_records, response.total_pages, records.length);
                }
            },
            onError: function () {
                $('#preauth-tbody').html(
                    '<tr><td colspan="8"><div class="table-empty"><i class="fa-solid fa-circle-exclamation"></i> Failed to load records.</div></td></tr>'
                );
            }
        });
    }

    // Debounced Search Inputs
    $('#filter-patient-name, #filter-case-id').on('keyup', function () {
        clearTimeout(filterTimer);
        filterTimer = setTimeout(function () {
            loadPreAuths(1);
        }, 500);
    });

    // Dropdown filters
    $('#filter-office-id, #filter-status').on('change', function () {
        loadPreAuths(1);
    });

    // Refresh Button
    $('#btn-refresh-table').on('click', function (e) {
        e.preventDefault();
        var $icon = $(this).find('i');
        $icon.addClass('fa-spin');
        loadPreAuths(1);

        setTimeout(function () {
            $icon.removeClass('fa-spin');
        }, 600);
    });

    /* ================================================================
        FUNCTION 5: TABLE RENDERING WITH ROW SPANNING
    ================================================================ */
    function renderTable(records) {
        let rows = '';
        if (!records || records.length === 0) {
            // Updated colspan from 8 to 6 due to removing 2 complete columns
            rows = '<tr><td colspan="6" class="text-center text-muted py-4">No authorization records found matching your filters.</td></tr>';
        } else {
            records.forEach(r => {
                const childItems = r.procedures_list || [];
                const rowSpanCount = childItems.length > 0 ? childItems.length : 1;

                for (let i = 0; i < rowSpanCount; i++) {
                    const proc = childItems[i];

                    const currentStatus = proc ? (proc.status || 'Create') : (r.status || 'Create');
                    const statusLower = currentStatus.toLowerCase();
                    const statusClass = 'status-' + statusLower;

                    let rowColorClass = '';
                    if (statusLower === 'expired') {
                        rowColorClass = 'table-warning alert-warning-row';
                    } else if (statusLower === 'rejected' || statusLower === 'denied') {
                        rowColorClass = 'table-danger alert-danger-row';
                    } else if (statusLower === 'approved') {
                        rowColorClass = 'table-success alert-success-row';
                    } else if (statusLower === 'appealed') {
                        rowColorClass = 'table-info alert-info-row';
                    } else if (statusLower === 'scheduled') {
                        rowColorClass = 'table-primary alert-primary-row';
                    } else if (statusLower === 'completed') {
                        rowColorClass = 'table-secondary alert-secondary-row';
                    }

                    let rowBorderStyle = 'vertical-align: middle;';
                    if (i === rowSpanCount - 1) {
                        rowBorderStyle += ' border-bottom: 2px solid #cbd5e1;';
                    }

                    rows += `<tr class="${rowColorClass}" style="${rowBorderStyle} transition: background-color 0.2s ease;">`;

                    // --- COLUMN BLOCK 1: MASTER CONTAINER CASE FIELDS (ROSPAN CONTEXT) ---
                    if (i === 0) {
                        const clinicText = r.clinic_name || r.office_name || '—';
                        rows += `
                    <td rowspan="${rowSpanCount}" class="fw-bold text-center" style="background: rgba(0,0,0,0.01); border-right: 1px solid #e2e8f0; vertical-align: middle;">
                        <span class="badge bg-secondary text-dark px-2 py-1">Case #${App.utils.escHtml(r.id)}</span>
                    </td>
                    <td rowspan="${rowSpanCount}" style="vertical-align: middle;">
                        <div class="fw-600 text-primary">${App.utils.escHtml(r.patient_name)}</div>
                        <div class="text-xs text-muted fw-500 mt-1" title="Assigned Facility Location">
                            <i class="fa-solid fa-house-medical text-muted me-1"></i> ${App.utils.escHtml(clinicText)}
                        </div>
                        <small class="text-muted d-block mt-1">
                            <i class="fa-solid fa-user-doctor"></i> Dr. ${App.utils.escHtml(r.doctor_name || 'Unassigned')}
                        </small>
                        <small class="text-muted d-block">
                            <i class="fa-regular fa-clock"></i> ${proc ? proc.time_ago : r.time_ago}
                        </small>
                    </td>
                    <td rowspan="${rowSpanCount}" style="vertical-align: middle;">${App.utils.escHtml(r.insurance_name || '—')}</td>
                `;
                    }

                    // --- COLUMN BLOCK 2: SPLIT ITEMIZED INDIVIDUAL TREATMENT PLAN TARGETS ---
                    if (proc) {
                        rows += `
                    <td style="vertical-align: middle; border-right: 1px solid #f1f5f9;">
                        <span class="badge bg-light text-primary border me-1">Tooth ${App.utils.escHtml(proc.tooth_number)}</span>
                        <span class="fw-500">${App.utils.escHtml(proc.procedure_name)} - $${App.utils.escHtml(proc.procedure_price)}</span>
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

                    // --- COLUMN BLOCK 3: ACTION LIFECYCLE SELECTIONS ---
                    const targetPreAuthId = proc ? proc.pre_auth_id : r.id;

                    rows += `<td style="vertical-align: middle; text-align: center;">
                            <div class="row-actions" style="display: flex; gap: 4px; justify-content: center;">`;

                    // Context Conditional Engine rules parsing
                    if (statusLower === 'requested' || statusLower === 'create' || statusLower === 'pending') {
                        // Actions: Edit, View, Send to Management Review, Delete
                        rows += `
                        <button class="btn btn-ghost btn-sm btn-edit-item" data-id="${targetPreAuthId}" title="Edit Procedure Parameters"><i class="fa-solid fa-pen text-muted"></i></button>
                        <button class="btn btn-ghost btn-sm btn-view-item" data-id="${targetPreAuthId}" title="View Details"><i class="fa-solid fa-eye text-primary"></i></button>
                        <button class="btn btn-ghost btn-sm btn-send-preauth" data-id="${targetPreAuthId}" title="Send to Management Review" style="color:var(--color-success)"><i class="fa-solid fa-paper-plane"></i></button>
                        <button class="btn btn-ghost btn-sm btn-delete-item" data-id="${targetPreAuthId}" title="Delete Authorization Row" style="color:var(--color-danger)"><i class="fa-solid fa-trash"></i></button>
                    `;
                    } else if (statusLower === 'sent' || statusLower === 'processing' || statusLower === 'appealed') {
                        // Actions: Edit, View, Delete, Accept (Approve), Reject
                        // Note: 'appealed' maps directly to this same operational layout workflow rule array stack
                        rows += `
                        <button class="btn btn-ghost btn-sm btn-view-item" data-id="${targetPreAuthId}" title="View Timeline / Documentation"><i class="fa-solid fa-eye text-primary"></i></button>
                        <button class="btn btn-ghost btn-sm btn-edit-item" data-id="${targetPreAuthId}" title="Modify Details"><i class="fa-solid fa-pen text-muted"></i></button>
                        <button class="btn btn-ghost btn-sm btn-delete-item" data-id="${targetPreAuthId}" title="Delete Authorization Row" style="color:var(--color-danger)"><i class="fa-solid fa-trash"></i></button>
                        <button class="btn btn-ghost btn-sm btn-approve-preauth" data-id="${targetPreAuthId}" title="Mark as Approved" style="color:var(--color-success)"><i class="fa-solid fa-circle-check"></i></button>
                        <button class="btn btn-ghost btn-sm btn-reject-preauth" data-id="${targetPreAuthId}" title="Mark as Rejected" style="color:var(--color-danger)"><i class="fa-solid fa-circle-xmark"></i></button>
                    `;
                    } else if (statusLower === 'denied' || statusLower === 'rejected') {
                        // Actions: View, Edit, Appeal, Delete
                        rows += `
                        <button class="btn btn-ghost btn-sm btn-view-item" data-id="${targetPreAuthId}" title="View Defect Log Notes"><i class="fa-solid fa-eye text-primary"></i></button>
                        <button class="btn btn-ghost btn-sm btn-edit-item" data-id="${targetPreAuthId}" title="Correct Case Properties"><i class="fa-solid fa-pen text-muted"></i></button>
                        <button class="btn btn-ghost btn-sm btn-appeal-preauth" data-id="${targetPreAuthId}" title="File Appeal Matrix Dispute" style="color:#0284c7"><i class="fa-solid fa-gavel"></i></button>
                        <button class="btn btn-ghost btn-sm btn-delete-item" data-id="${targetPreAuthId}" title="Delete Authorization Row" style="color:var(--color-danger)"><i class="fa-solid fa-trash"></i></button>
                    `;
                    } else if (statusLower === 'approved') {
                        // Actions: View, Edit, Schedule, Delete
                        rows += `
                        <button class="btn btn-ghost btn-sm btn-view-item" data-id="${targetPreAuthId}" title="View Details"><i class="fa-solid fa-eye text-primary"></i></button>
                        <button class="btn btn-ghost btn-sm btn-edit-item" data-id="${targetPreAuthId}" title="Admin Override Details"><i class="fa-solid fa-pen text-muted"></i></button>
                        <button class="btn btn-ghost btn-sm btn-schedule-preauth" data-id="${targetPreAuthId}" title="Schedule Treatment Appointment" style="color:#8b5cf6"><i class="fa-solid fa-calendar-days"></i></button>
                        <button class="btn btn-ghost btn-sm btn-delete-item" data-id="${targetPreAuthId}" title="Delete Authorization Row" style="color:var(--color-danger)"><i class="fa-solid fa-trash"></i></button>
                    `;
                    } else if (statusLower === 'scheduled') {
                        // Actions: View, Edit, Complete, Delete
                        rows += `
                        <button class="btn btn-ghost btn-sm btn-view-item" data-id="${targetPreAuthId}" title="View Details"><i class="fa-solid fa-eye text-primary"></i></button>
                        <button class="btn btn-ghost btn-sm btn-edit-item" data-id="${targetPreAuthId}" title="Modify Schedule Parameters"><i class="fa-solid fa-pen text-muted"></i></button>
                        <button class="btn btn-ghost btn-sm btn-complete-preauth" data-id="${targetPreAuthId}" title="Mark Treatment as Completed" style="color:#10b981"><i class="fa-solid fa-flag-checkered"></i></button>
                        <button class="btn btn-ghost btn-sm btn-rescedule-preauth" data-id="${targetPreAuthId}" title="Rescedule" style="color:#10b981"><i class="fa-solid fa-arrow-rotate-left"></i></button>
                        <button class="btn btn-ghost btn-sm btn-delete-item" data-id="${targetPreAuthId}" title="Delete Authorization Row" style="color:var(--color-danger)"><i class="fa-solid fa-trash"></i></button>
                    `;
                    } else if (statusLower === 'completed') {
                        // Actions: View, Edit, Modify Status, Delete
                        rows += `
                        <button class="btn btn-ghost btn-sm btn-view-item" data-id="${targetPreAuthId}" title="View Archival Record Details"><i class="fa-solid fa-eye text-primary"></i></button>
                        <button class="btn btn-ghost btn-sm btn-edit-item" data-id="${targetPreAuthId}" title="Admin Post-Complete Modification Override"><i class="fa-solid fa-pen text-muted"></i></button>
                        <button class="btn btn-ghost btn-sm btn-modify-status" data-id="${targetPreAuthId}" title="Rollback or Alter Status Mode" style="color:#f59e0b"><i class="fa-solid fa-sliders"></i></button>
                        <button class="btn btn-ghost btn-sm btn-delete-item" data-id="${targetPreAuthId}" title="Delete Authorization Row" style="color:var(--color-danger)"><i class="fa-solid fa-trash"></i></button>
                    `;
                    } else {
                        // Fallback for any other statuses (e.g. Expired)
                        rows += `
                        <button class="btn btn-ghost btn-sm btn-view-item" data-id="${targetPreAuthId}" title="View Details"><i class="fa-solid fa-eye text-primary"></i></button>
                        <button class="btn btn-ghost btn-sm btn-delete-item" data-id="${targetPreAuthId}" title="Delete Authorization Row" style="color:var(--color-danger)"><i class="fa-solid fa-trash"></i></button>
                    `;
                    }

                    rows += `   </div>
                          </td>`;
                    rows += `</tr>`;
                }
            });
        }
        $('#preauth-tbody').html(rows);
    }

    /**
 * Custom Arrow-Based Pagination Handler
 */
function renderPagination(totalRecords, totalPages, currentCount) {
    // 1. Update the informational text string panel
    $('#preauth-info').text(`Showing ${currentCount} of ${totalRecords} records (Page ${currentPage} of ${totalPages})`);

    let html = '';

    // Only render pagination controls if there is more than 1 total page
    if (totalPages > 1) {
        // Calculate previous and next page numbers target markers
        const prevPage = currentPage - 1;
        const nextPage = currentPage + 1;

        // ── LEFT ARROW (PREVIOUS) ──
        // If we are on page 1, disable the button so the user can't click back further
        if (currentPage <= 1) {
            html += `<button class="page-link btn disabled" disabled><i class="fa-solid fa-chevron-left"></i></button>`;
        } else {
            html += `<button class="page-link btn" data-page="${prevPage}"><i class="fa-solid fa-chevron-left"></i></button>`;
        }

        // ── RIGHT ARROW (NEXT) ──
        // If we are on the last page, disable the button so the user can't click forward further
        if (currentPage >= totalPages) {
            html += `<button class="page-link btn  disabled" disabled><i class="fa-solid fa-chevron-right"></i></button>`;
        } else {
            html += `<button class="page-link btn" data-page="${nextPage}"><i class="fa-solid fa-chevron-right"></i></button>`;
        }
    }

    // 2. Inject the updated directional markup into your DOM container layout node
    $('#pagination-btns').html(html);
}

    $(document).on('click', '.page-link', function (e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (page) loadPreAuths(page);
    });

    /* ================================================================
        FUNCTION 6: CRUD OPERATIONS (CREATE, READ, UPDATE, DELETE)
    ================================================================ */
    $('#btn-add-preauth').on('click', function () {
        editingId = null;
        resetForm();
        $('#preauth-modal-title').text('Add New Pre-Auth');
        App.modal.open('preauth-modal');
    });

    $('#btn-save-preauth').on('click', function () {
        var form = $('#preauth-form');
        App.form.clearErrors(form);

        if (!App.form.validate(form)) {
            App.toast.warning('Validation', 'Please fill in all required fields.');
            return;
        }

        var formData = form.serialize();
        var isEditing = !!editingId;
        var url = isEditing ? '/admin-pre-auth/update.php' : '/admin-pre-auth/create.php';

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

    // Edit Pre-Auth (Case Companion Container Workflow)
    $(document).on('click', '.btn-edit-item', function () {
        var id = $(this).data('id');

        App.ajax({
            url: '/admin-pre-auth/get-edit.php?id=' + id,
            loader: true,
            onSuccess: function (r) {
                resetForm();

                editingId = r.case_id;
                $('#preauth-modal-title').text('Edit Pre-Auth Case File #' + r.case_id);

                // Populate form keys
                $('#preauth-id').val(r.case_id);

                // 1. Set the office. This triggers the 'change' event but we might need to wait 
                // for the AJAX to finish. For editing, we bypass the wait by passing the preselected IDs.
                $('#office-select').val(r.office_id);
                loadOfficeSpecificData(r.office_id, r.patient_id, r.doctor_id);

                $('#p_insurance_plan').val(r.p_insurance_plan).trigger('change');

                $('#treatments-container').empty();

                if (r.procedures_list && r.procedures_list.length) {
                    $.each(r.procedures_list, function (idx, entry) {
                        addTreatmentRow(entry.procedure_id, entry.tooth_number, entry.pre_auth_id , entry.procedure_price);
                    });
                } else {
                    addTreatmentRow('', '', '');
                }

                App.modal.open('preauth-modal');
            }
        });
    });

    // Handle DELETE Button
    $(document).on('click', '.btn-delete-item', function () {
        const id = $(this).data('id');
        const name = $(this).data('name') || 'this record';

        // 1. Configure Modal Header and Buttons
        $('#confirm-title').text('Delete Pre-Auth Record');
        $('#confirm-ok').text('Confirm Delete').removeClass('btn-success btn-primary').addClass('btn-danger');

        // 2. Adjust Modal Body Containers (Hide dynamic workflow fields if left visible)
        $('#approval-expiry-container').hide();
        $('#rejection-notes-container').hide();
        $('#rejection-notes').val('');

        // 3. Set Modal Body Content Matching Your Destructive Action Design System
        $('#confirm-body-content').html(`
        <div class="text-center">
            <i class="fa-solid fa-trash-can text-danger mb-3" style="font-size:3rem"></i>
            <p>Are you sure you want to delete the pre-auth for <b>${App.utils.escHtml(name)}</b>?</p>
            <p class="text-muted text-sm mt-2">This action will remove it from the active list permanently.</p>
        </div>
    `);

        // 4. Bind the Click Event safely using off() to prevent double submission triggers
        $('#confirm-ok').off('click').on('click', function () {
            App.ajax({
                url: '/admin-pre-auth/delete.php',
                method: 'POST',
                data: { id: id },
                loaderMsg: 'Deleting record...',
                onSuccess: function (d, msg) {
                    App.toast.success('Deleted', msg);
                    loadPreAuths(currentPage);
                }
            });
            App.modal.close('confirm-modal');
        });

        // 5. Open the Modal
        App.modal.open('confirm-modal');
    });

    function resetForm() {
        App.form.reset(document.getElementById('preauth-form'));
        $('#office-select').val('').trigger('change');
        $('#patient-select').html('<option value="">Search names (Select Clinic first)...</option>').trigger('change');
        $('#provider').html('<option value="">Select clinician doctor...</option>');
        $('#treatments-container').empty();
        addTreatmentRow('', '', '');
        editingId = null;
    }

    /* ================================================================
        FUNCTION 7: VIEW TIMELINE LIFECYCLE GENERATOR
    ================================================================ */
    $(document).on('click', '.btn-view-item', function () {
        var id = $(this).data('id');

        App.ajax({
            url: '/admin-pre-auth/get.php?id=' + id,
            loader: true,
            onSuccess: function (r) {
                var listHtml = '';

                if (r.procedures_list && r.procedures_list.length) {
                    $.each(r.procedures_list, function (idx, item) {
                        listHtml += `<div style="padding: var(--sp-2) 0; border-bottom: 1px dashed #e2e8f0;">
                                    <i class="fa-solid fa-circle-chevron-right text-primary text-sm mr-2"></i> 
                                    <strong>Tooth ${App.utils.escHtml(item.tooth_number || '—')}:</strong> ${App.utils.escHtml(item.procedure_name)} <small>- $${item.procedure_price} </small>
                                 </div>`;
                    });
                } else if (r.procedure_name) {
                    listHtml = `<div style="padding: var(--sp-2) 0; border-bottom: 1px dashed #e2e8f0;">
                                <i class="fa-solid fa-circle-chevron-right text-primary text-sm mr-2"></i> 
                                <strong>Tooth ${App.utils.escHtml(r.tooth_number || '—')}:</strong> ${App.utils.escHtml(r.procedure_name)} <small> - $${r.procedure_price} </small>
                             </div>`;
                } else {
                    listHtml = `<div class="text-muted">No itemized treatments mapped to this pre-auth.</div>`;
                }

                var statusStr = (r.status || 'Requested').toLowerCase();
                var progressWidth = '0%';
                var themeColor = '#3b82f6';
                var isRejectedState = (statusStr === 'rejected' || statusStr === 'denied');
                var isAppealedState = (statusStr === 'appealed');
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

                var expireRow = r.approval_expire_date
                    ? `<strong class="text-dark">${App.utils.escHtml(r.approval_expire_date)}</strong>`
                    : (r.approval_expire_date ? `<strong class="text-dark">${App.utils.escHtml(r.approval_expire_date)}</strong>` : '<span class="text-muted">—</span>');

                var editorRow = r.editor_name && r.editor_name !== '—'
                    ? `<span><i class="fa-solid fa-user-pen"></i> ${App.utils.escHtml(r.editor_name)} <small class="text-muted">(${r.formatted_edit_time || 'Recent'})</small></span>`
                    : '<span class="text-muted" style="font-style: italic;">Never modified</span>';

                var html = progressBarHtml +
                    '<div class="grid-2" style="gap:var(--sp-8)">' +

                    '<div>' +
                    '<div class="form-section-title mb-4" style="color:var(--color-primary); border-bottom:2px solid #f1f5f9; padding-bottom:5px;"><i class="fa-solid fa-user"></i> Patient Information</div>' +
                    infoRow('Full Name', App.utils.escHtml(r.patient_name)) +
                    infoRow('Date of Birth', App.utils.escHtml(r.patient_dob || '—')) +
                    infoRow('Insurance Plan', App.utils.escHtml(r.insurance_name || '—')) +
                    infoRow('Clinic Office', App.utils.escHtml(r.clinic_name || r.office_name || '—')) +

                    '<div class="form-section-title mt-6 mb-4" style="color:var(--color-primary); border-bottom:2px solid #f1f5f9; padding-bottom:5px;"><i class="fa-solid fa-stethoscope"></i> Treatment Details</div>' +
                    '<div class="mb-2" style="line-height:1.6; max-height:220px; overflow-y:auto; padding-right:5px;">' + listHtml + '</div>' +
                    '</div>' +

                    '<div>' +
                    '<div class="form-section-title mb-4" style="color:var(--color-primary); border-bottom:2px solid #f1f5f9; padding-bottom:5px;"><i class="fa-solid fa-shield-halved"></i> Authorization & Audit</div>' +
                    infoRow('Authorized By', approverRow) +
                    infoRow('Expiration Date', expireRow) +
                    infoRow('Appointment Linked', App.utils.escHtml(r.appointment_date || 'None scheduled')) +
                    infoRow('Last Edited By', editorRow) +
                    infoRow('Submitted By', App.utils.escHtml(r.creator_name || 'System') + ' <small class="text-muted">(' + (r.time_ago || 'N/A') + ')</small>') +

                    '<div class="mt-4 p-3" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:var(--radius-md);">' +
                    '<small class="text-muted d-block mb-1" style="font-weight:700; text-transform:uppercase; font-size:0.7rem; letter-spacing:0.5px;">Internal Case & Rejection Notes:</small>' +
                    '<div style="font-size:0.9rem; color:#334155;">' + App.utils.escHtml(r.notes || 'No notes left by operational staff.') + '</div>' +
                    '</div>' +
                    '</div>' +

                    '</div>';

                $('#view-details-body').html(html);
                App.modal.open('view-modal');
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
        FUNCTION 8: ACTION TRIGGERS (STATUS UPDATES)
    ================================================================ */
    // Handle SEND Button
    $(document).on('click', '.btn-send-preauth', function () {
        const id = $(this).data('id');
        const name = $(this).data('name') || 'this case';

        // 1. Configure Modal Header and Buttons
        $('#confirm-title').text('Mark Pre-Auth as Sent');
        $('#confirm-ok').text('Confirm Sent').removeClass('btn-danger btn-success').addClass('btn-primary');

        // 2. Adjust Modal Body Containers (Hide dynamic workflow parameter inputs if visible)
        $('#approval-expiry-container').hide();
        $('#rejection-notes-container').hide();
        $('#rejection-notes').val('');

        // 3. Set Modal Body Content Matching Your Design System
        $('#confirm-body-content').html(`
        <div class="text-center">
            <i class="fa-solid fa-paper-plane text-primary mb-3" style="font-size:3rem"></i>
            <p>Are you sure you want to change the status of <b>${App.utils.escHtml(name)}</b> to <strong>Sent</strong>?</p>
            <p class="text-muted text-sm mt-2">This will log that this pre-authorization package has been successfully dispatched to the insurance provider.</p>
        </div>
    `);

        // 4. Bind the Click Event safely using off() to clear any legacy handler queues
        $('#confirm-ok').off('click').on('click', function () {
            App.ajax({
                url: '/admin-pre-auth/send-pre-auth.php',
                method: 'POST',
                data: { id: id },
                loader: true,
                onSuccess: function (d, msg) {
                    App.toast.success("Sent", msg);
                    loadPreAuths(currentPage);
                }
            });
            App.modal.close('confirm-modal');
        });

        // 5. Open the Modal
        App.modal.open('confirm-modal');
    });
    // Handle APPEAL Button
    $(document).on('click', '.btn-appeal-preauth', function () {
        const id = $(this).data('id');
        const name = $(this).data('name') || 'this case';

        // 1. Configure Modal Header and Buttons
        $('#confirm-title').text('Appeal Pre-Auth');
        $('#confirm-ok').text('Submit Appeal').removeClass('btn-danger').addClass('btn-success');

        // 2. Adjust Modal Body Containers
        $('#approval-expiry-container').hide();
        $('#rejection-notes-container').hide(); // Ensure rejection notes are hidden
        $('#rejection-notes').val('');          // Clear any previous values

        // 3. Set Modal Body Content
        $('#confirm-body-content').html(`
        <div class="text-center">
            <i class="fa-solid fa-paper-plane text-success mb-3" style="font-size:3rem"></i>
            <p>Confirm changes have been made. Re-submit the request for <b>${App.utils.escHtml(name)}</b> as an <strong>APPEAL</strong>?</p>
            <p class="text-muted text-sm mt-2">This action will notify management to review the case again.</p>
        </div>
    `);

        // 4. Bind the Click Event
        $('#confirm-ok').off('click').on('click', function () {
            App.ajax({
                url: '/admin-pre-auth/appeal-pre-auth.php',
                method: 'POST',
                data: { id: id },
                loader: true,
                onSuccess: function (d, msg) {
                    App.toast.success("Appeal Submitted", msg);
                    loadPreAuths(currentPage);
                }
            });
            App.modal.close('confirm-modal');
        });

        // 5. Open the Modal
        App.modal.open('confirm-modal');
    });

    // Handle APPROVE Button
    $(document).on('click', '.btn-approve-preauth', function () {
        const id = $(this).data('id');
        const name = $(this).data('name') || 'this case';

        $('#confirm-title').text('Approve Pre-Auth');
        $('#confirm-ok').text('Approve Request').removeClass('btn-danger').addClass('btn-success');

        $('#rejection-notes-container').hide();
        $('#approval-expiry-container').show();
        $('#approval-expiry-date').val('');

        $('#confirm-body-content').html(`
        <div class="text-center">
            <i class="fa-solid fa-circle-check text-success mb-3" style="font-size:3rem"></i>
            <p>Are you sure you want to <strong>APPROVE</strong> the request for <b>${App.utils.escHtml(name)}</b>?</p>
        </div>
    `);

        $('#confirm-ok').off('click').on('click', function () {
            const expiryDate = $('#approval-expiry-date').val();
            updateStatus(id, 'Approved', expiryDate, null);
            App.modal.close('confirm-modal');
        });

        App.modal.open('confirm-modal');
    });

    // Handle REJECT Button
    $(document).on('click', '.btn-reject-preauth', function () {
        const id = $(this).data('id');
        const name = $(this).data('name') || 'this case';

        $('#confirm-title').text('Reject Pre-Auth');
        $('#confirm-ok').text('Reject Request').removeClass('btn-success').addClass('btn-danger');

        $('#approval-expiry-container').hide();
        $('#rejection-notes-container').show();
        $('#rejection-notes').val('');

        $('#confirm-body-content').html(`
        <div class="text-center">
            <i class="fa-solid fa-circle-xmark text-danger mb-3" style="font-size:3rem"></i>
            <p>Are you sure you want to <strong>REJECT</strong> the request for <b>${App.utils.escHtml(name)}</b>?</p>
            <p class="text-danger text-sm mt-2">This action will return the submission as denied.</p>
        </div>
    `);

        $('#confirm-ok').off('click').on('click', function () {
            const notes = $('#rejection-notes').val().trim();
            updateStatus(id, 'Rejected', null, notes);
            App.modal.close('confirm-modal');
        });

        App.modal.open('confirm-modal');
    });

    function updateStatus(id, newStatus, expiryDate = null, notes = null) {
        App.ajax({
            url: '/m-pre-auth/update-status.php',
            method: 'POST',
            data: {
                id: id,
                status: newStatus,
                expiry_date: expiryDate,
                rejection_notes: notes
            },
            loaderMsg: 'Updating status tracking profile...',
            onSuccess: function (res, msg) {
                App.toast.success('Record updated to ' + newStatus, msg);
                loadPreAuths(currentPage);
            }
        });
    }


    // Handle BOOK Button (Calendar Icon) - Open Modal
    $(document).on('click', '.btn-schedule-preauth', function () {
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
            url: '/admin-pre-auth/book-appointment.php',
            method: 'POST',
            data: {
                id: id,
                appointment_date: dateValue
            },
            loaderMsg: 'Scheduling...',
            onSuccess: function (res, msg) {
                App.toast.success('Appointment Scheduled', msg);
                App.modal.close('book-modal');
                loadPreAuths(1); // Refresh table
            }
        });
    });


    // Handle MODIFY STATUS Button Click - Open Modal
    $(document).on('click', '.btn-modify-status', function () {
        const id = $(this).data('id');
        const currentStatus = $(this).data('status') || ''; // Grab current row status context if available

        $('#status-preauth-id').val(id); // Set hidden record tracking ID
        $('#update-status-select').val(currentStatus); // Pre-set dropdown state to current status context

        App.modal.open('modify-status-modal');
    });

    // Handle CONFIRM Status Change (Send New Pipeline Payload to Backend API)
    $(document).on('click', '#btn-confirm-modify-status', function () {
        const id = $('#status-preauth-id').val();
        const selectedStatus = $('#update-status-select').val();

        // Field integrity verification validation checking
        if (!selectedStatus) {
            App.toast.error('Please select a valid pipeline destination status.');
            return;
        }

        App.ajax({
            url: '/admin-pre-auth/modify-status.php',
            method: 'POST',
            data: {
                id: id,
                status: selectedStatus
            },
            loaderMsg: 'Updating operational state...',
            onSuccess: function (res, msg) {
                App.toast.success('Pipeline Status Updated', msg);
                App.modal.close('modify-status-modal');
                loadPreAuths(1); // Refresh data-table component matrix dynamically
            },
            onError: function (err) {
                App.toast.error('Failed to change workflow state structural properties.');
            }
        });
    });

    /**
 * 3. Handle Complete Procedure Logic
 */
    // A. Open Modal
    $(document).on('click', '.btn-complete-preauth', function () {
        const id = $(this).data('id');
        $('#complete-preauth-id').val(id);
        App.modal.open('complete-modal');
    });

    // B. Submit Completion
    $(document).on('click', '#btn-confirm-complete', function () {
        const id = $('#complete-preauth-id').val();

        App.ajax({
            url: '/admin-pre-auth/complete-pre-auth.php', // Ensure this file exists
            method: 'POST',
            data: { id: id },
            loaderMsg: 'Finalizing procedure...',
            onSuccess: function (res, msg) {
                App.toast.success('Completed', msg);
                App.modal.close('complete-modal');
                loadPreAuths(1);// Refresh your Admin table
            }
        });
    });

    /**
     * 4. Handle Reschedule Logic
     */
    // A. Open Modal
    $(document).on('click', '.btn-rescedule-preauth', function () {
        const id = $(this).data('id');
        $('#reschedule-preauth-id').val(id);
        App.modal.open('reschedule-modal');
    });

    // B. Submit Reschedule
    $(document).on('click', '#btn-confirm-reschedule', function () {
        const id = $('#reschedule-preauth-id').val();

        App.ajax({
            url: '/admin-pre-auth/reschedule-request.php', // Ensure this file exists
            method: 'POST',
            data: { id: id },
            loaderMsg: 'Resetting date...',
            onSuccess: function (res, msg) {
                App.toast.success('Rescheduled', msg);
                App.modal.close('reschedule-modal');
                loadPreAuths(1); // Refresh your Admin table
            }
        });
    });




    /* ================================================================
        INITIALIZE RUNTIME EXECUTION
    ================================================================ */
    loadClinics();
    loadGlobalDropdowns();
    loadPreAuths(1);
});