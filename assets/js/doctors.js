/**
 * assets/js/doctor.js
 * CRUD logic for the Doctor page.
 * This file is the template/pattern to copy when building any new module.
 */

$(document).ready(function () {

    /* ── State ── */
    var currentPage = 1;
    var perPage = 20;
    var editingId = null;   // null = adding new, number = editing

    /* ================================================================
       LOAD TABLE
    ================================================================ */
    function loadDoctor(page) {
        page = page || 1;
        currentPage = page;

        App.ajax({
            url: '/doctor/list.php',
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
                $('#patients-tbody').html(
                    '<tr><td colspan="8"><div class="table-empty"><i class="fa-solid fa-circle-exclamation"></i> Failed to load patients.</div></td></tr>'
                );
            }
        });
    }

    function renderTable(patients) {
        if (!patients || !patients.length) {
            $('#doctors-tbody').html(
                '<tr><td colspan="8"><div class="table-empty"><i class="fa-solid fa-user-slash"></i> No Doctors found.</div></td></tr>'
            );
            return;
        }

        var rows = '';
        $.each(patients, function (i, p) {
            rows += '<tr>' +
                '<td><strong>#' + p.user_id + '</strong></td>' +
                '<td>' +
                '<div class="flex flex-align gap-3">' +
                '<div class="user-avatar" style="width:32px;height:32px;font-size:0.75rem;background:var(--color-primary-light);color:var(--color-primary)">' +
                App.utils.escHtml(p.name) +
                '</div>' +
                '</div>' +
                '</td>' +


                '<td>' + App.utils.escHtml(p.mobile || '—') + '</td>' +
                '<td>' + App.utils.escHtml(p.email || '—') + '</td>' +
                '<td>' + App.utils.escHtml(p.address || '-') + '</td>' +
                '<td>' +
                '<div class="actions">' +
                '<button class="btn btn-ghost btn-sm btn-view" data-id="' + p.user_id + '" title="View"><i class="fa-solid fa-eye"></i></button>' +
                '<button class="btn btn-ghost btn-sm btn-edit" data-id="' + p.user_id + '" title="Edit"><i class="fa-solid fa-pen"></i></button>' +
                '<button class="btn btn-ghost btn-sm btn-delete" data-id="' + p.user_id + '" data-name="' + App.utils.escHtml(p.name) + '" title="Delete" style="color:var(--color-danger)"><i class="fa-solid fa-trash"></i></button>' +
                '</div>' +
                '</td>' +
                '</tr>';
        });

        $('#doctors-tbody').html(rows);
    }

    function renderPagination(meta) {
        var total = meta.total || 0;
        var pages = meta.pages || 1;
        var current = meta.current || 1;
        var from = total ? ((current - 1) * perPage + 1) : 0;
        var to = Math.min(current * perPage, total);

        $('#patients-info').text('Showing ' + from + '–' + to + ' of ' + total + ' patients');

        var btns = '';
        btns += '<button class="page-btn" id="pg-prev" ' + (current <= 1 ? 'disabled' : '') + '><i class="fa-solid fa-chevron-left"></i></button>';

        // Show max 5 page buttons
        var start = Math.max(1, current - 2);
        var end = Math.min(pages, start + 4);
        for (var i = start; i <= end; i++) {
            btns += '<button class="page-btn ' + (i === current ? 'active' : '') + '" data-page="' + i + '">' + i + '</button>';
        }

        btns += '<button class="page-btn" id="pg-next" ' + (current >= pages ? 'disabled' : '') + '><i class="fa-solid fa-chevron-right"></i></button>';
        $('#pagination-btns').html(btns);
    }


    /* Pagination */
    $(document).on('click', '.page-btn[data-page]', function () {
        loadPatients(parseInt($(this).data('page')));
    });
    $(document).on('click', '#pg-prev', function () { if (currentPage > 1) loadPatients(currentPage - 1); });
    $(document).on('click', '#pg-next', function () { loadPatients(currentPage + 1); });

    /* ================================================================
       ADD NEW PATIENT
    ================================================================ */
    $('#btn-add-patient').on('click', function () {
        editingId = null;
        resetForm();
        $('#patient-modal-title').text('Add New Doctor');
        $('.password-row').show();
        $('.password-row input').prop('disabled', false).attr('required', 'required');
        App.modal.open('patient-modal');
    });

    /* ================================================================
       SAVE PATIENT (create or update)
    ================================================================ */
    $('#btn-save-doctor').on('click', function () {
        var form = $('#patient-form');

        // Front-end validation
        App.form.clearErrors(form);
        if (!App.form.validate(form)) {
            App.toast.warning('Validation', 'Please fill in all required fields.');
            return;
        }

        var data = App.form.toObject(form);
        var isEditing = !!editingId;
        var url = isEditing
            ? '/doctor/update.php'
            : '/doctor/create.php';

        if (data.password != data.re_password) {
            App.toast.warning('Validation', 'Passwords Not Match .');
            return;
        }

        if (isEditing) data.doctor_id = editingId;

        App.ajax({
            url: url,
            method: 'POST',
            data: data,
            btn: $('#btn-save-patient'),
            loaderMsg: isEditing ? 'Saving changes…' : 'Creating Office…',
            onSuccess: function (d, msg) {
                App.modal.close('patient-modal');
                App.toast.success('Success', msg);
                loadDoctor(currentPage);
            }
        });
    });

    /* ================================================================
       VIEW PATIENT
    ================================================================ */
    $(document).on('click', '.btn-view', function () {
        var id = $(this).data('id');

        App.ajax({
            url: '/doctor/get.php?id=' + id,
            loader: false,
            onSuccess: function (p) {
                var html =
                    '<div class="grid-2" style="gap:var(--sp-8)">' +

                    '<div>' +
                    '<div class="form-section-title mb-4"><i class="fa-solid fa-user"></i> Personal Info</div>' +
                    infoRow('Office Name', p.name || '—') +
                    '</div>' +

                    '<div>' +
                    '<div class="form-section-title mb-4"><i class="fa-solid fa-address-book"></i> Contact</div>' +
                    infoRow('Phone', p.mobile || '—') +
                    infoRow('Email', p.email || '—') +
                    infoRow('Address', p.address || '—') +
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

    function ucFirst(str) { return str ? str.charAt(0).toUpperCase() + str.slice(1) : ''; }

    $('#btn-edit-from-view').on('click', function () {
        App.modal.close('view-patient-modal');
        openEditModal($(this).data('id'));
    });

    /* ================================================================
       EDIT PATIENT
    ================================================================ */
    $(document).on('click', '.btn-edit', function () {
        $('.password-row').hide();
        $('.password-row input').prop('disabled', true).removeAttr('required');
        openEditModal($(this).data('id'));
    });

    function openEditModal(id) {
        App.ajax({
            url: '/doctor/get.php?id=' + id,
            loader: false,
            onSuccess: function (p) {
                resetForm();
                editingId = p.user_id;
                $('#patient-modal-title').text('Edit Doctor');

                // Populate form
                $('#doctor-id').val(p.user_id);
                $('[name="name"]').val(p.name);
                $('[name="phone"]').val(p.mobile);
                $('[name="email"]').val(p.email);
                $('[name="address"]').val(p.address);
                App.modal.open('patient-modal');
            }
        });
    }

    /* ================================================================
       DELETE PATIENT
    ================================================================ */
    $(document).on('click', '.btn-delete', function () {
        var id = $(this).data('id');
        var name = $(this).data('name');

        App.utils.confirm(
            'Are you sure you want to delete "' + name + '"? This cannot be undone.',
            function () {
                App.ajax({
                    url: '/doctor/delete.php',
                    method: 'POST',
                    data: { id: id },
                    loaderMsg: 'Deleting doctor…',
                    onSuccess: function (d, msg) {
                        App.toast.success('Deleted', msg);
                        loadDoctor(currentPage);
                    }
                });
            }
        );
    });

    /* ================================================================
       HELPERS
    ================================================================ */
    function resetForm() {
        App.form.reset(document.getElementById('patient-form'));
        editingId = null;
    }

    /* ================================================================
       INIT — load on page ready
    ================================================================ */
    loadDoctor(1);

});
