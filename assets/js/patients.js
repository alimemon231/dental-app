/**
 * assets/js/patients.js
 * CRUD logic for the Patient page module.
 */

$(document).ready(function () {

    /* ── State ── */
    var currentPage = 1;
    var perPage = 20;
    var pageLimit = 0;       // Dynamic base counter tracking row offsets (#1, #2...)
    var editingId = null;   // null = adding new, number = editing patient record

    /* ================================================================
        LOAD TABLE WITH FILTER ATTACHMENTS
    ============================================================ */
    function loadPatients(page) {
        page = page || 1;
        currentPage = page;

        // Extract values from the explicit filter card inputs
        var searchName = $('#filter-patient').val() || '';
        var clinicId = $('#filter-clinic').val() || '';

        App.ajax({
            url: '/patient/list.php',
            method: 'GET',
            loader: false,
            data: {
                page: page,
                limit: perPage,
                search: searchName,     // Sends data to the backend query
                clinic_id: clinicId     // Sends selected clinic to the backend query
            },
            onSuccess: function (data, msg, res) {
                // Read and fallback safe offset state integer
                var metaObj = res.meta || data.meta || {};
                pageLimit = metaObj.offset ? parseInt(metaObj.offset) : 0;

                // Handle differences in standard backend object return wrappers
                var records = data.records || data;

                renderTable(records);
                renderPagination(metaObj);
            },
            onError: function () {
                $('#patients-tbody').html(
                    '<tr><td colspan="7"><div class="table-empty"><i class="fa-solid fa-circle-exclamation"></i> Failed to load patients list.</div></td></tr>'
                );
            }
        });
    }

    function renderTable(patients) {
        if (!patients || !patients.length) {
            $('#patients-tbody').html(
                '<tr><td colspan="7"><div class="table-empty"><i class="fa-solid fa-user-slash"></i> No patient profiles found.</div></td></tr>'
            );
            return;
        }

        var rows = '';
        var localCounter = pageLimit; // Track row IDs sequentially based on page offsets

        $.each(patients, function (i, p) {
            var pId = p.id || p.patient_id;
            var officeName = p.office_name || '—';
            localCounter++;

            rows += '<tr>' +
                '<td><strong>#' + localCounter + '</strong></td>' +
                '<td>' +
                '<div class="flex flex-align gap-3">' +
                '<div class="user-avatar" style="width:32px;height:32px;font-size:0.75rem;background:var(--color-primary-light);color:var(--color-primary)">' +
                App.utils.escHtml(p.name ? p.name.charAt(0).toUpperCase() : 'P') +
                '</div>' +
                '<span>' + App.utils.escHtml(p.name) + '</span>' +
                '</div>' +
                '</td>' +
                '<td>' + App.utils.escHtml(p.dob || '—') + '</td>' +
                '<td>' + App.utils.escHtml(p.mobile || '—') + '</td>' +
                '<td>' + App.utils.escHtml(p.email || '—') + '</td>' +
                '<td><span class="badge badge-success">' + App.utils.escHtml(officeName) + '</span></td>' +
                '<td>' +
                '<div class="actions">' +
                '<button class="btn btn-ghost btn-sm btn-view" data-id="' + pId + '" title="View Profile"><i class="fa-solid fa-eye"></i></button>' +
                '<button class="btn btn-ghost btn-sm btn-edit" data-id="' + pId + '" title="Edit Profile"><i class="fa-solid fa-pen"></i></button>' +
                '<button class="btn btn-ghost btn-sm btn-history" data-id="' + pId + '" title="View Patient History"><i class="fa-solid fa-file-medical"></i></button>' +
                '</td>' +
                '</tr>';
        });

        $('#patients-tbody').html(rows);
    }

    function renderPagination(meta) {
        var total = meta.total || 0;
        var pages = meta.pages || meta.total_pages || 1;
        var current = meta.current || currentPage;

        var from = total ? ((current - 1) * perPage + 1) : 0;
        var to = Math.min(current * perPage, total);

        $('#patients-info').text('Showing ' + from + '–' + to + ' of ' + total + ' patients');

        // Simple and effective arrow toggles with scroll behavior
        var btns = '';
        btns += '<button class="page-btn" id="pg-prev" ' + (current <= 1 ? 'disabled' : '') + '><i class="fa-solid fa-chevron-left"></i></button>';
        btns += '<button class="page-btn" id="pg-next" ' + (current >= pages ? 'disabled' : '') + '><i class="fa-solid fa-chevron-right"></i></button>';

        $('#pagination-btns').html(btns);
    }

    /* ── Pagination Event Bindings with View Reset ── */
    $(document).on('click', '#pg-prev', function () {
        if (currentPage > 1) {
            $('html, body').animate({
                scrollTop: $("#patients-table").offset().top - 100
            }, 500);
            loadPatients(currentPage - 1);
        }
    });

    $(document).on('click', '#pg-next', function () {
        $('html, body').animate({
            scrollTop: $("#patients-table").offset().top - 100
        }, 500);
        loadPatients(currentPage + 1);
    });

    /* ================================================================
        FILTER ACTION BUTTON BINDINGS
    ============================================================ */
    // Trigger filter update upon hitting 'Filter Results' button
    $('#btn-search').on('click', function () {
        loadPatients(1);
    });

    // Optional: Allow pressing Enter inside the input field to search automatically
    $('#filter-patient').on('keypress', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            loadPatients(1);
        }
    });

    // Clear all inputs and restore data view loops to defaults
    $('#btn-clear').on('click', function () {
        $('#filter-patient').val('');
        $('#filter-clinic').val('');
        loadPatients(1);
    });

    // Handle standard browser native landscape printing layouts
    $('#btn-print').on('click', function () {
        window.print();
    });

    /* ================================================================
        FETCH ACTIVE USER ASSIGNED OFFICES FOR DROPDOWN SELECTS
    ============================================================ */
    function populateOfficeDropdowns() {
        App.ajax({
            url: '/auth/user_offices.php',
            method: 'GET',
            loader: false,
            silent: true,
            onSuccess: function (offices) {
                // Prepare options for the Add/Edit form modal selector
                var formOptions = '<option value="">-- Select Assigned Office --</option>';

                // Prepare options for the search canvas card filter selector
                var filterOptions = '<option value="">All Clinics</option>';

                if (offices && offices.length) {
                    $.each(offices, function (i, office) {
                        var id = office.id || office.office_id;
                        var name = office.office_name || office.name;
                        var structuredHtml = '<option value="' + id + '">' + App.utils.escHtml(name) + '</option>';

                        formOptions += structuredHtml;
                        filterOptions += structuredHtml;
                    });
                }

                // Synchronize both select tags globally
                $('#office_id').html(formOptions);
                $('#filter-clinic').html(filterOptions);
            },
            onError: function () {
                console.error("Failed to fetch user assigned office permissions array context.");
            }
        });
    }

    /* ================================================================
        ADD NEW PATIENT INTERACTION CONTROL
    ============================================================ */
    $('#btn-add-patient').on('click', function () {
        editingId = null;
        resetForm();
        $('#patient-modal-title').text('Add New Patient');
        App.modal.open('patient-modal');
    });

    /* ================================================================
        SAVE PATIENT INTERACTION PIPELINE (Create / Update)
    ============================================================ */
    $('#btn-save-patient').on('click', function () {
        var form = $('#patient-form');

        App.form.clearErrors(form);
        if (!App.form.validate(form)) {
            App.toast.warning('Validation', 'Please fill in all required fields.');
            return;
        }

        var data = App.form.toObject(form);
        var isEditing = !!editingId;
        var url = isEditing ? '/patient/update.php' : '/patient/create.php';

        if (isEditing) {
            data.patient_id = editingId;
        }

        App.ajax({
            url: url,
            method: 'POST',
            data: data,
            btn: $('#btn-save-patient'),
            loaderMsg: isEditing ? 'Saving profile modifications…' : 'Registering patient profile…',
            onSuccess: function (d, msg) {
                App.modal.close('patient-modal');
                App.toast.success('Success', msg);
                loadPatients(currentPage);
            }
        });
    });

    /* ================================================================
        VIEW PATIENT DETAILS MODAL
    ============================================================ */
    $(document).on('click', '.btn-view', function () {
        var id = $(this).data('id');

        App.ajax({
            url: '/patient/get.php?id=' + id,
            loader: false,
            onSuccess: function (p) {
                var officeLabel = p.office_name || '—';

                var html =
                    '<div class="grid-2" style="gap:var(--sp-8)">' +
                    '<div>' +
                    '<div class="form-section-title mb-4"><i class="fa-solid fa-id-card"></i> Demographics Info</div>' +
                    infoRow('Patient Name', p.name || '—') +
                    infoRow('Date of Birth', p.dob || '—') +
                    infoRow('Assigned Location', officeLabel) +
                    '</div>' +
                    '<div>' +
                    '<div class="form-section-title mb-4"><i class="fa-solid fa-address-book"></i> Contact Details</div>' +
                    infoRow('Phone Number', p.phone || '—') +
                    infoRow('Email Address', p.email || '—') +
                    infoRow('Home Address', p.address || '—') +
                    '</div>' +
                    '</div>' +

                    /* ── Added System Action Audit Footprint Tracker ── */
                    '<div class="mt-6 pt-4" style="border-top: 1px dashed var(--color-border);">' +
                    '<div class="form-section-title mb-3" style="font-size:0.8rem; text-transform:uppercase; color:var(--color-text-muted); letter-spacing:0.05em;"><i class="fa-solid fa-clock-rotate-left"></i> System Audit Trail</div>' +
                    '<div class="grid-2 gap-4">' +
                    '<div>' + infoRow('Created By', p.creator_name || 'System / Auto') + '</div>' +
                    '<div>' + infoRow('Last Modified', p.edited_time ? (p.edited_time + ' (' + (p.editor_name || 'N/A') + ')') : 'Never Edited') + '</div>' +
                    '</div>' +
                    '</div>';

                $('#view-patient-body').html(html);
                $('#btn-edit-from-view').data('id', id);
                App.modal.open('view-patient-modal');
            }
        });
    });

    function infoRow(label, value) {
        return '<div class="flex-between mb-4" style="border-bottom:1px solid var(--color-border);padding-bottom:var(--sp-3)">' +
            '<span class="text-sm text-muted">' + label + '</span>' +
            '<span class="text-sm fw-500">' + App.utils.escHtml(String(value)) + '</span>' +
            '</div>';
    }

    $('#btn-edit-from-view').on('click', function () {
        App.modal.close('view-patient-modal');
        openEditModal($(this).data('id'));
    });

    /* ================================================================
        EDIT PATIENT SETUP
    ============================================================ */
    $(document).on('click', '.btn-edit', function () {
        openEditModal($(this).data('id'));
    });

    function openEditModal(id) {
        App.ajax({
            url: '/patient/get.php?id=' + id,
            loader: false,
            onSuccess: function (p) {
                resetForm();
                editingId = p.id || p.patient_id;
                $('#patient-modal-title').text('Edit Patient Profile');

                $('#patient-id').val(editingId);
                $('[name="name"]').val(p.name);
                $('[name="dob"]').val(p.dob);
                $('[name="phone"]').val(p.phone);
                $('[name="email"]').val(p.email);
                $('[name="office_id"]').val(p.office_id);
                $('[name="address"]').val(p.address);

                App.modal.open('patient-modal');
            }
        });
    }


    /* ================================================================
        VIEW PATIENT COMPREHENSIVE HISTORY (Pre-Auth & Labs)
    ============================================================ */
    $(document).on('click', '.btn-history', function () {
        var patientId = $(this).data('id');

        // Reset containers to loading state
        $('#history-preauth-container').html('<div class="text-muted text-center p-4"><i class="fa-solid fa-spinner fa-spin"></i> Loading pre-auth records...</div>');
        $('#history-labs-container').html('<div class="text-muted text-center p-4"><i class="fa-solid fa-spinner fa-spin"></i> Loading lab cases...</div>');

        // Open the modal immediately so the user sees the loading state
        App.modal.open('view-history-modal');

        // 1. FETCH PRE-AUTH HISTORY
        App.ajax({
            url: '/patient/pre-auth-his.php',
            method: 'GET',
            data: { patient_id: patientId },
            loader: false,
            onSuccess: function (res) {
                // Fallback checks managing standard wrapping layers
                var records = res.records || res.data || res;
                var html = '';

                if (!Array.isArray(records) || records.length === 0) {
                    html = '<div class="card p-4 text-center text-muted border-dashed shadow-none">No pre-authorizations found for this patient.</div>';
                } else {
                    $.each(records, function (i, r) {
                        // Safeguard against hidden array items caused by clinic-scope cross restrictions
                        if (!r || !r.id) return;

                        var status = (r.status || 'Pending').toUpperCase();
                        var statusClass = status === 'APPROVED' ? 'stage-success' : (status === 'REJECTED' || status === 'DENIED' ? 'stage-danger' : 'stage-pending');

                        html += `
                <div class="card mb-3 mt-3 p-3 shadow-sm border" style="border-left: 4px solid var(--color-primary) !important;margin-top:20px;">
                    <div class="flex-between mb-2 pb-2" style="border-bottom: 1px solid #e2e8f0;">
                        <div>
                            <span class="font-bold text-sm">#PA-${r.id}</span>
                            <span class="text-xs text-muted ml-2"><i class="fa-regular fa-calendar"></i> ${r.created_at_fmt || r.created_at}</span>
                        </div>
                        <span class="badge ${statusClass}" style="font-size: 0.65rem;">${status}</span>
                    </div>
                    <div class="text-sm mb-2">
                        <strong>Procedure:</strong> ${App.utils.escHtml(r.procedure_name || 'N/A')} <br>
                        <span class="text-xs text-muted">Teeth: ${App.utils.escHtml(r.tooth_numbers || '—')}</span>
                    </div>
                    <div class="flex-between text-xs text-muted mt-2 pt-2" style="border-top: 1px dashed #e2e8f0;">
                        <span><i class="fa-solid fa-building"></i> ${App.utils.escHtml(r.clinic_name || r.office_name || '—')}</span>
                        <span><i class="fa-solid fa-user-check"></i> Auth By: ${App.utils.escHtml(r.approver_name || 'Pending')}</span>
                    </div>
                </div>`;
                    });

                    // Final check if loop resulted in completely empty elements due to role restriction skips
                    if (html === '') {
                        html = '<div class="card p-4 text-center text-muted border-dashed shadow-none">No accessible pre-authorizations found within your workspace.</div>';
                    }
                }
                $('#history-preauth-container').html(html);
            },
            onError: function () {
                $('#history-preauth-container').html('<div class="text-danger text-center p-3"><i class="fa-solid fa-circle-exclamation"></i> Failed to load pre-auth history.</div>');
            }
        });

        // 2. FETCH LAB CASE HISTORY
        App.ajax({
            url: '/patient/lab-hist.php',
            method: 'GET',
            data: { p_id: patientId }, // Only passing the required patient identifier 
            loader: false,
            onSuccess: function (res) {
                var records = res.records || res.data || [];
                var html = '';

                if (records.length === 0) {
                    html = '<div class="card p-4 text-center text-muted border-dashed shadow-none">No lab cases found for this patient.</div>';
                } else {
                    $.each(records, function (i, r) {
                        var status = (r.status || 'Sent').toUpperCase();
                        var statusClass = status === 'DONE' ? 'stage-success' : 'stage-pending';

                        html += `
                <div class="card mb-3 p-3 shadow-sm border" style="border-left: 4px solid #8b5cf6 !important;margin-top:20px;">
                    <div class="flex-between mb-2 pb-2" style="border-bottom: 1px solid #e2e8f0;">
                        <div>
                            <span class="font-bold text-sm">#LAB-${r.id}</span>
                            <span class="text-xs text-muted ml-2"><i class="fa-solid fa-paper-plane"></i> Sent: ${r.date_sent_fmt || r.date_sent || '—'}</span>
                        </div>
                        <span class="badge ${statusClass}" style="font-size: 0.65rem;">${status}</span>
                    </div>
                    <div class="text-sm mb-2">
                        <strong>Type:</strong> ${App.utils.escHtml(r.type_name || '—')} <br>
                        <span class="text-xs text-muted">Provider: Dr. ${App.utils.escHtml(r.doctor_name || '—')}</span>
                    </div>
                    <div class="grid-2 text-xs mt-2 pt-2" style="border-top: 1px dashed #e2e8f0; gap: 5px;">
                        <div class="text-muted"><i class="fa-solid fa-box-open"></i> Rcvd: ${r.date_received_fmt || '—'}</div>
                        <div class="text-muted"><i class="fa-solid fa-calendar-check"></i> Appt: ${r.date_scheduled_fmt || '—'}</div>
                    </div>
                </div>`;
                    });
                }
                $('#history-labs-container').html(html);
            },
            onError: function () {
                $('#history-labs-container').html('<div class="text-danger text-center p-3">Failed to load lab case history.</div>');
            }
        });
    });



    function resetForm() {
        App.form.reset(document.getElementById('patient-form'));
        editingId = null;
    }

    /* ================================================================
        INITIALIZATION HOOKS
    ============================================================ */
    populateOfficeDropdowns();
    loadPatients(1);

});