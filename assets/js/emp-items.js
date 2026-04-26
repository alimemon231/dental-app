/**
 * assets/js/offices.js
 * CRUD logic for the offices page.
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
    function loadItems(page) {
        page = page || 1;
        currentPage = page;

        App.ajax({
            url: '/items/list.php',
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
                $('#items-tbody').html(
                    '<tr><td colspan="8"><div class="table-empty"><i class="fa-solid fa-circle-exclamation"></i> Failed to load patients.</div></td></tr>'
                );
            }
        });
    }

    function renderTable(patients) {
        if (!patients || !patients.length) {
            $('#items-tbody').html(
                '<tr><td colspan="8"><div class="table-empty"><i class="fa-solid fa-pills"></i> No items found.</div></td></tr>'
            );
            return;
        }

        var rows = '';
        $.each(patients, function (i, p) {
            rows += '<tr>' +
                '<td><strong>#' + (i + 1) + '</strong></td>' +
                '<td>' +
                '<div class="flex flex-align gap-3">' +
                '<img src="' + p.image_path + '" class="item-img">' +
                '<div>' +
                App.utils.escHtml(p.name) +
                '</div>' +
                '</div>' +
                '</td>' +


                '<td>' + App.utils.escHtml(p.price || '—') + '</td>' +
                '<td>' + App.utils.escHtml(p.description || '—') + '</td>' +
                '<td>' +
                '<div class="actions">' +
                '<button class="btn btn-ghost btn-sm btn-view" data-id="' + p.id + '" title="View"><i class="fa-solid fa-eye"></i></button>' +
                '</div>' +
                '</td>' +
                '</tr>';
        });

        $('#items-tbody').html(rows);
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
       SAVE PATIENT (create or update)
    ================================================================ */
    $('#btn-save-item').on('click', function () {
        var form = $('#item-form');

        // Front-end validation
        App.form.clearErrors(form);
        if (!App.form.validate(form)) {
            App.toast.warning('Validation', 'Please fill in all required fields.');
            return;
        }

        var data = new FormData(form[0]);
        var isEditing = !!editingId;
        var url = isEditing
            ? '/items/update.php'
            : '/items/create.php';

        if (isEditing) data.patient_id = editingId;

        App.ajax({
            url: url,
            method: 'POST',
            data: data,
            btn: $('#btn-save-item'),
            loaderMsg: isEditing ? 'Saving changes…' : 'Creating Item…',
            onSuccess: function (d, msg) {
                App.modal.close('item-modal');
                App.toast.success('Success', msg);
                loadItems(currentPage);
            }
        });
    });

    /* ================================================================
       VIEW PATIENT
    ================================================================ */
    $(document).on('click', '.btn-view', function () {
        var id = $(this).data('id');

        App.ajax({
            url: '/items/get.php?id=' + id,
            loader: false,
            onSuccess: function (p) {
                var html =
                    '<div class="grid-2" style="gap:var(--sp-8)">' +

                    '<div>' +
                    '<div class="form-section-title mb-4"><i class="fa-solid fa-user"></i>  Item Info </div>' +
                        infoRow('Item Name', p.name || '—') +
                        infoRow('Price', p.price || '—') +
                        infoRow('description', p.description || '—') +
                    '</div>' +

                    '<div>' +
                        '<img src="'+ p.image_path +'" class="main-item-image">'
                    '</div>' +
                    '</div>';


                $('#view-items-body').html(html);
                $('#btn-edit-from-view').data('id', p.id);
                App.modal.open('view-item-modal');
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
        App.modal.close('view-item-modal');
        openEditModal($(this).data('id'));
    });

    /* ================================================================
       EDIT PATIENT
    ================================================================ */
   

    
    
    

    /* ================================================================
       INIT — load on page ready
    ================================================================ */
    loadItems(1);

});
