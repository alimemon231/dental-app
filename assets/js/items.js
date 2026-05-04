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
    var selected_categories = [];

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


                '<td> $' + App.utils.escHtml(p.price || '—') + '</td>' +
                '<td>' + App.utils.escHtml(p.category_names || '—') + '</td>' +
                '<td>' + App.utils.escHtml(p.description || '—') + '</td>' +
                '<td>' +
                '<div class="actions">' +
                '<button class="btn btn-ghost btn-sm btn-view" data-id="' + p.id + '" title="View"><i class="fa-solid fa-eye"></i></button>' +
                '<button class="btn btn-ghost btn-sm btn-edit" data-id="' + p.id + '" title="Edit"><i class="fa-solid fa-pen"></i></button>' +
                '<button class="btn btn-ghost btn-sm btn-delete" data-id="' + p.id + '" data-name="' + App.utils.escHtml(p.name) + '" title="Delete" style="color:var(--color-danger)"><i class="fa-solid fa-trash"></i></button>' +
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
       ADD NEW PATIENT
    ================================================================ */
    $('#btn-add-item').on('click', function () {
        editingId = null;
        selected_categories = [];
        resetForm();
        $('[name="image"]').attr('required', 'required');
        $('#item-modal-title').text('Add New Item');
        App.modal.open('item-modal');
    });

    /* ================================================================
       SAVE PATIENT (create or update)
    ================================================================ */
    $('#btn-save-item').on('click', function () {
        var form = $('#item-form');
        var categoryIds = selected_categories.map(c => c.id);

        // Front-end validation
        App.form.clearErrors(form);
        if (!App.form.validate(form)) {
            App.toast.warning('Validation', 'Please fill in all required fields.');
            return;
        }

        var data = new FormData(form[0]);
        categoryIds.forEach(id => {
            data.append('category_ids[]', id);
        });
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
                    infoRow('Dscription', p.description || '—') +
                    infoRow('Categories', p.category_names || '—') +
                    '</div>' +

                    '<div>' +
                    '<img src="' + p.image_path + '" class="main-item-image">'
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
    $(document).on('click', '.btn-edit', function () {
        selected_categories = [];
        openEditModal($(this).data('id'));

    });

    function openEditModal(id) {
        App.ajax({
            url: '/items/get.php?id=' + id,
            loader: false,
            onSuccess: function (p) {
                resetForm();
                editingId = p.id;
                $('#patient-modal-title').text('Edit Item');

                // Populate form
                $('#item-id').val(p.id);
                $('[name="name"]').val(p.name);
                $('[name="price"]').val(p.price);
                $('[name="description"]').val(p.description);
                $('[name="image"]').removeAttr('required');

                if (p.category_ids && p.category_names) {

                    // Split the comma-separated strings into arrays
                    var ids = p.category_ids.toString().split(',');
                    var names = p.category_names.split(',');

                    // 3. Map them into the object format and push to selected_categories
                    ids.forEach(function (id, index) {
                        selected_categories.push({
                            id: id.trim(),
                            name: names[index] ? names[index].trim() : 'Deleted Category'
                        });
                    });
                }

                // 4. Refresh the UI badges
                renderCategoryBadges();
                App.modal.open('item-modal');
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
                    url: '/items/delete.php',
                    method: 'POST',
                    data: { id: id },
                    loaderMsg: 'Deleting Item…',
                    onSuccess: function (d, msg) {
                        App.toast.success('Deleted', msg);
                        loadItems(currentPage);
                    }
                });
            }
        );
    });


    function loadCategories() {

        App.ajax({
            url: '/categories/list.php',
            method: 'GET',
            loader: false,
            onSuccess: function (data, msg, res) {
                var html = '';
                html += '<option value="" selected disabled> </option>'
                $.each(data, function (i, row) {
                    html += '<option value="' + row.id + '">' + row.name + '</option>';
                });

                $("#category-select").html(html)
            },
            onError: function () {
                $('#category-select').html(
                    '<option value="" selected disabled> Please add categories first </option>'
                );
            }
        });
    }



    $('#category-select').on('select2:select', function (e) {
        var data = e.params.data; // Get selected item data

        var catId = data.id;
        var catName = data.text;

        // Prevent duplicates
        var exists = selected_categories.find(c => c.id == catId);
        if (exists) {
            App.toast.info('Already Added', catName + ' is already selected.');
            // Clear the selection so they can search again
            $('#category-select').val(null).trigger('change');
            return;
        }

        // 3. Add to our tracking array
        selected_categories.push({ id: catId, name: catName });

        // 4. Clear the search box immediately for the next search
        $('#category-select').val(null).trigger('change');

        // 5. Update the UI badges
        renderCategoryBadges();
    });

    /**
     * TRIGGER: Remove a badge when the X icon is clicked
     */
    $(document).on('click', '.remove-cat-badge', function () {
        var idToRemove = $(this).data('id');

        // Filter out from array
        selected_categories = selected_categories.filter(c => c.id != idToRemove);

        renderCategoryBadges();
    });

    /**
     * Renders the badges (WordPress/WooCommerce Tag Style)
     */
    function renderCategoryBadges() {
        var $container = $('#selected-categories-list');
        $container.empty();

        if (selected_categories.length === 0) {
            $container.html('<small class="text-muted">No categories assigned.</small>');
            return;
        }

        selected_categories.forEach(function (cat) {
            var badge = `
                <div class="category-tag d-flex align-items-center" 
                     style="background: #e9ecef; border: 1px solid #ced4da; padding: 4px 10px; border-radius: 20px; font-size: 0.9rem;">
                    <span class="me-2">${App.utils.escHtml(cat.name)}</span>
                    <i class="fa-solid fa-circle-xmark remove-cat-badge" 
                       data-id="${cat.id}" 
                       style="cursor:pointer; color:#dc3545;"></i>
                </div>
            `;
            $container.append(badge);
        });
    }




    /* ================================================================
       HELPERS
    ================================================================ */
    function resetForm() {
        App.form.reset(document.getElementById('item-form'));
        editingId = null;
    }

    /* ================================================================
       INIT — load on page ready
    ================================================================ */
    loadItems(1);
    loadCategories()

});
