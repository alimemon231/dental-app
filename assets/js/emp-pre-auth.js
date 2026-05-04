/**
 * assets/js/preauth.js
 * CRUD logic for the Pre-Auth management module.
 */

$(document).ready(function () {

    /* ── State ── */
    var currentPage = 1;
    var perPage = 20;
    var editingId = null;   // null = adding new, number = editing

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
            data: {
                page: page,
                limit: perPage,
            },
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
            // Status formatting
            var statusClass = 'status-' + (r.status ? r.status.toLowerCase() : 'pending');

            rows += '<tr>' +
                '<td><strong>#' + r.id + '</strong></td>' +
                '<td>' +
                '<div class="fw-600">' + App.utils.escHtml(r.p_first_name + ' ' + r.p_last_name) + '</div>' +
                '<small class="text-muted"><i class="fa-regular fa-clock"></i> ' + r.time_ago + '</small>' +
                '</td>' +
                '<td>' + App.utils.escHtml(r.p_dob || '—') + '</td>' +
                '<td>' + App.utils.escHtml(r.p_insurance_plan || '—') + '</td>' +
                '<td>' +
                '<span class="text-sm">' + App.utils.escHtml(r.treatment_type || '—') + '</span><br>' +
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

    function renderPagination(meta) {
        var total = meta.total || 0;
        var pages = meta.pages || 1;
        var current = meta.current || 1;
        var from = total ? ((current - 1) * perPage + 1) : 0;
        var to = Math.min(current * perPage, total);

        $('#preauth-info').text('Showing ' + from + '–' + to + ' of ' + total + ' records');

        var btns = '';
        btns += '<button class="page-btn" id="pg-prev" ' + (current <= 1 ? 'disabled' : '') + '><i class="fa-solid fa-chevron-left"></i></button>';

        var start = Math.max(1, current - 2);
        var end = Math.min(pages, start + 4);
        for (var i = start; i <= end; i++) {
            btns += '<button class="page-btn ' + (i === current ? 'active' : '') + '" data-page="' + i + '">' + i + '</button>';
        }

        btns += '<button class="page-btn" id="pg-next" ' + (current >= pages ? 'disabled' : '') + '><i class="fa-solid fa-chevron-right"></i></button>';
        $('#pagination-btns').html(btns);
    }

    /* Pagination Events */
    $(document).on('click', '.page-btn[data-page]', function () {
        loadPreAuths(parseInt($(this).data('page')));
    });
    $(document).on('click', '#pg-prev', function () { if (currentPage > 1) loadPreAuths(currentPage - 1); });
    $(document).on('click', '#pg-next', function () { if (currentPage < pages) loadPreAuths(currentPage + 1); });

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
        SAVE PRE-AUTH (create or update)
    ================================================================ */
    $('#btn-save-preauth').on('click', function () {
        var form = $('#preauth-form');

        App.form.clearErrors(form);
        if (!App.form.validate(form)) {
            App.toast.warning('Validation', 'Please fill in all required fields.');
            return;
        }

        var formData = form.serialize(); // Since there's no file upload here, serialize is cleaner
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
    VIEW PRE-AUTH
================================================================ */
    $(document).on('click', '.btn-view', function () {
        var id = $(this).data('id');

        App.ajax({
            url: '/emp-pre-auth/get.php?id=' + id,
            loader: false,
            onSuccess: function (r) {
                var html = `
                <div class="grid-2" style="gap:var(--sp-8)">
                    <div>
                        <div class="form-section-title mb-4">
                            <i class="fa-solid fa-user"></i> Patient Info 
                        </div>
                        ${infoRow('Full Name', App.utils.escHtml(r.p_first_name + ' ' + r.p_last_name))}
                        ${infoRow('Date of Birth', r.p_dob)}
                        ${infoRow('Insurance', r.p_insurance_plan)}
                        ${infoRow('Clinic Location', r.clinic_name)}
                    </div>
                    <div>
                        <div class="form-section-title mb-4">
                            <i class="fa-solid fa-tooth"></i> Treatment & Status 
                        </div>
                        ${infoRow('Procedure', r.treatment_type)}
                        ${infoRow('Tooth Number', r.tooth_numbers)}
                        ${infoRow('Current Status',  r.status )}
                        ${infoRow('Created At', r.formatted_date)}
                        ${infoRow('Relative Time', r.time_ago )}
                    </div>
                </div>`;

                $('#view-preauth-body').html(html);
                App.modal.open('view-preauth-modal');
            }
        });
    });


    function infoRow(label, value) {
        return '<div class="flex-between mb-4" style="border-bottom:1px solid var(--color-border);padding-bottom:var(--sp-3)">' +
            '<span class="text-sm text-muted">' + label + '</span>' +
            '<span class="text-sm fw-500">' + App.utils.escHtml(String(value || '—')) + '</span>' +
            '</div>';
    }

    /* ================================================================
        EDIT PRE-AUTH
    ================================================================ */
    $(document).on('click', '.btn-edit', function () {
        var id = $(this).data('id');
        App.ajax({
            url: '/emp-pre-auth/get.php?id=' + id,
            loader: false,
            onSuccess: function (r) {
                resetForm();
                editingId = r.id;
                $('#preauth-modal-title').text('Edit Pre-Auth');

                // Populate form fields
                $('#preauth-id').val(r.id);
                $('#p_first_name').val(r.p_first_name);
                $('#p_last_name').val(r.p_last_name);
                $('#p_dob').val(r.p_dob);
                $('#p_insurance_plan').val(r.p_insurance_plan);
                $('#treatment_type').val(r.treatment_type);
                $('#tooth_numbers').val(r.tooth_numbers);
                $('#status').val(r.status);

                App.modal.open('preauth-modal');
            }
        });
    });

    /* ================================================================
        DELETE PRE-AUTH
    ================================================================ */
    $(document).on('click', '.btn-delete', function () {
        var id = $(this).data('id');
        var name = $(this).data('name');

        App.utils.confirm(
            'Are you sure you want to delete pre-auth for "' + name + '"?',
            function () {
                App.ajax({
                    url: '/emp-pre-auth/delete.php',
                    method: 'POST',
                    data: { id: id },
                    loaderMsg: 'Deleting record…',
                    onSuccess: function (d, msg) {
                        App.toast.success('Deleted', msg);
                        loadPreAuths(currentPage);
                    }
                });
            }
        );
    });

    /* ================================================================
        HELPERS
    ================================================================ */
    function resetForm() {
        App.form.reset(document.getElementById('preauth-form'));
        editingId = null;
    }

    /* ================================================================
        INIT
    ================================================================ */
    loadPreAuths(1);

});