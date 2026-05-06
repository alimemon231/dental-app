/**
 * assets/js/emp-pre-auth.js
 * Updated with Dynamic Procedures and Insurance Loading
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
        FETCH DROPDOWN DATA
    ================================================================ */
    function loadDropdowns() {
        // Load Procedures
        App.ajax({
            url: '/emp-pre-auth/load-procedures.php',
            method: 'GET',
            loader: false,
            onSuccess: function (data) {
                dropdownCache.procedures = data;
                var options = '<option value="">-- Select Procedure --</option>';
                $.each(data, function (i, p) {
                    // Only show active procedures in the dropdown

                    options += `<option value="${p.id}">${App.utils.escHtml(p.name)}</option>`;

                });
                $('#treatment_type').html(options);
            }
        });

        // Load Insurances
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
    }

    /* ================================================================
        LOAD TABLE
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
                renderPagination(res.meta || {});
            },
            onError: function () {
                $('#preauth-tbody').html(
                    '<tr><td colspan="7"><div class="table-empty"><i class="fa-solid fa-circle-exclamation"></i> Failed to load records.</div></td></tr>'
                );
            }
        });
    }

    function renderTable(records) {
        if (!records || !records.length) {
            $('#preauth-tbody').html(
                '<tr><td colspan="7"><div class="table-empty"><i class="fa-solid fa-file-medical"></i> No pre-auth records found.</div></td></tr>'
            );
            return;
        }

        var rows = '';
        $.each(records, function (i, r) {
            var statusClass = 'status-' + (r.status ? r.status.toLowerCase() : 'pending');

            rows += '<tr>' +
                '<td><strong>#' + r.id + '</strong></td>' +
                '<td>' +
                '<div class="fw-600">' + App.utils.escHtml(r.p_first_name + ' ' + r.p_last_name) + '</div>' +
                '<small class="text-muted"><i class="fa-regular fa-clock"></i> ' + r.time_ago + '</small>' +
                '</td>' +
                '<td>' + App.utils.escHtml(r.p_dob || '—') + '</td>' +
                '<td>' + App.utils.escHtml(r.insurance_name || r.p_insurance_plan || '—') + '</td>' +
                '<td>' +
                '<span class="text-sm">' + App.utils.escHtml(r.procedure_name || r.treatment_type || '—') + '</span><br>' +
                '<small class="text-primary">Tooth: ' + App.utils.escHtml(r.tooth_numbers) + '</small>' +
                '</td>' +
                '<td><span class="status-badge ' + statusClass + '">' + App.utils.escHtml(r.status || 'Sent') + '</span></td>' +
                '<td>' +
                '<div class="actions">' +
                '<button class="btn btn-ghost btn-sm btn-view" data-id="' + r.id + '" title="View"><i class="fa-solid fa-eye"></i></button>' +
                '<button class="btn btn-ghost btn-sm btn-edit" data-id="' + r.id + '" title="Edit"><i class="fa-solid fa-pen"></i></button>' +
                '<button class="btn btn-ghost btn-sm btn-delete" data-id="' + r.id + '" data-name="' + App.utils.escHtml(r.p_first_name) + '" title="Delete" style="color:var(--color-danger)"><i class="fa-solid fa-trash"></i></button>' +
                '</div>' +
                '</td>' +
                '</tr>';
        });

        $('#preauth-tbody').html(rows);
    }

    /* ... renderPagination, Pagination Events, and infoRow helper remain same ... */

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
    $(document).on('click', '.btn-edit', function () {
        var id = $(this).data('id');
        App.ajax({
            url: '/emp-pre-auth/get.php?id=' + id,
            loader: true,
            onSuccess: function (r) {
                resetForm();
                editingId = r.id;
                $('#preauth-modal-title').text('Edit Pre-Auth');

                // Populate form fields
                $('#preauth-id').val(r.id);
                $('#p_first_name').val(r.p_first_name);
                $('#p_last_name').val(r.p_last_name);
                $('#p_dob').val(r.p_dob);

                // Select values for dropdowns
                $('#p_insurance_plan').val(r.p_insurance_plan);
                $('#treatment_type').val(r.treatment_type);

                $('#tooth_numbers').val(r.tooth_numbers);
                $('#status').val(r.status);

                App.modal.open('preauth-modal');
            }
        });
    });

    /* ================================================================
    VIEW PRE-AUTH DETAILS
================================================================ */
    $(document).on('click', '.btn-view', function () {
        var id = $(this).data('id');

        App.ajax({
            url: '/emp-pre-auth/get.php?id=' + id, // Ensure this endpoint uses the JOIN query
            loader: true,
            onSuccess: function (r) {
                var html =
                    '<div class="grid-2" style="gap:var(--sp-8)">' +

                    // Left Column: Patient & Insurance
                    '<div>' +
                    '<div class="form-section-title mb-4"><i class="fa-solid fa-user"></i> Patient Information</div>' +
                    infoRow('Full Name', App.utils.escHtml(r.p_first_name + ' ' + r.p_last_name)) +
                    infoRow('Date of Birth', App.utils.escHtml(r.p_dob || '—')) +
                    infoRow('Insurance Plan', App.utils.escHtml(r.insurance_name || r.p_insurance_plan || '—')) +

                    '<div class="form-section-title mt-6 mb-4"><i class="fa-solid fa- stethoscope"></i> Treatment Details</div>' +
                    infoRow('Procedure', App.utils.escHtml(r.procedure_name || r.treatment_type || '—')) +
                    infoRow('Tooth Numbers', App.utils.escHtml(r.tooth_numbers || '—')) +
                    '</div>' +

                    // Right Column: Status & Timeline
                    '<div>' +
                    '<div class="form-section-title mb-4"><i class="fa-solid fa-circle-info"></i> Submission Status</div>' +
                    infoRow('Current Status', '<span class="status-badge status-' + (r.status ? r.status.toLowerCase() : 'sent') + '">' + r.status + '</span>') +
                    infoRow('Submission Time', r.formatted_date + ' (' + r.time_ago + ')') +

                    // Optional: If you have a notes field
                    '<div class="mt-4 p-3 bg-light border-radius-sm">' +
                    '<small class="text-muted d-block mb-1">Internal Notes:</small>' +
                    '<div>' + App.utils.escHtml(r.description || 'No notes provided.') + '</div>' +
                    '</div>' +
                    '</div>' +

                    '</div>';

                $('#view-preauth-body').html(html);

                // Set the ID on the edit button inside the view modal if you have one
                $('#btn-edit-from-view').data('id', r.id);

                App.modal.open('view-preauth-modal');
            }
        });
    });

    /**
     * Helper function for clean row formatting
     */
    function infoRow(label, value) {
        return '<div class="info-row mb-2">' +
            '<span class="text-muted" style="width:130px; display:inline-block; font-size:0.85rem">' + label + ':</span>' +
            '<span class="fw-600">' + value + '</span>' +
            '</div>';
    }


    /* ================================================================
    DELETE / DEACTIVATE PRE-AUTH
================================================================ */
    $(document).on('click', '.btn-delete', function () {
        var id = $(this).data('id');
        var name = $(this).data('name'); // Patient first name passed from renderTable

        App.utils.confirm(
            'Are you sure you want to delete the pre-auth for "' + name + '"? This action will remove it from the active list.',
            function () {
                App.ajax({
                    url: '/emp-pre-auth/delete.php', // Or deactivate.php based on your file naming
                    method: 'POST',
                    data: { id: id },
                    loaderMsg: 'Deleting record...',
                    onSuccess: function (d, msg) {
                        App.toast.success('Deleted', msg);

                        // If the user was currently editing this specific record, reset the form
                        if (editingId == id) {
                            resetForm();
                            App.modal.close('preauth-modal');
                        }

                        // Refresh the table data
                        loadPreAuths(currentPage);
                    }
                });
            }
        );
    });

    function resetForm() {
        App.form.reset(document.getElementById('preauth-form'));
        editingId = null;
    }

    /* ================================================================
        INIT
    ================================================================ */
    loadDropdowns(); // Load options for selects
    loadPreAuths(1); // Load table data

});