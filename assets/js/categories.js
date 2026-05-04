/**
 * assets/js/categories.js
 * Logic for Category management with a side-by-side layout.
 */

$(document).ready(function () {

    /* ── State ── */
    var editingId = null; 

    /* ================================================================
        LOAD TABLE
    ================================================================ */
    function loadCategories() {
        App.ajax({
            url: '/categories/list.php',
            method: 'GET',
            loader: false,
            onSuccess: function (data) {
                renderTable(data);
            },
            onError: function () {
                $('#categories-tbody').html(
                    '<tr><td colspan="4"><div class="table-empty"><i class="fa-solid fa-circle-exclamation"></i> Failed to load categories.</div></td></tr>'
                );
            }
        });
    }

    function renderTable(categories) {
        if (!categories || !categories.length) {
            $('#categories-tbody').html(
                '<tr><td colspan="4"><div class="table-empty"><i class="fa-solid fa-folder-open"></i> No categories found.</div></td></tr>'
            );
            return;
        }

        var rows = '';
        $.each(categories, function (i, c) {
            rows += '<tr>' +
                '<td><strong>' + (i + 1) + '</strong></td>' +
                '<td>' + App.utils.escHtml(c.name) + '</td>' +
                '<td>' + App.utils.escHtml(c.description || '—') + '</td>' +
                '<td>' +
                '<div class="actions">' +
                '<button class="btn btn-ghost btn-sm btn-edit" data-id="' + c.id + '" title="Edit"><i class="fa-solid fa-pen"></i></button>' +
                '<button class="btn btn-ghost btn-sm btn-delete" data-id="' + c.id + '" data-name="' + App.utils.escHtml(c.name) + '" title="Delete" style="color:var(--color-danger)"><i class="fa-solid fa-trash"></i></button>' +
                '</div>' +
                '</td>' +
                '</tr>';
        });

        $('#categories-tbody').html(rows);
    }

    /* ================================================================
        SAVE CATEGORY (create or update)
    ================================================================ */
    $('#btn-save-category').on('click', function (e) {
        e.preventDefault();
        var form = $('#category-form');

        // Front-end validation
        App.form.clearErrors(form);
        if (!App.form.validate(form)) {
            App.toast.warning('Validation', 'Please provide a category name.');
            return;
        }

        var formData = new FormData(form[0]); // 
        var isEditing = !!editingId;
        var url = isEditing ? '/categories/update.php' : '/categories/create.php';

        if (isEditing) formData.category_id = editingId;

        App.ajax({
            url: url,
            method: 'POST',
            data: formData,
            btn: $('#btn-save-category'),
            loaderMsg: isEditing ? 'Updating...' : 'Adding...',
            onSuccess: function (d, msg) {
                App.toast.success('Success', msg);
                resetForm();
                loadCategories();
            }
        });
    });

    /* ================================================================
        EDIT CATEGORY (Populate side form)
    ================================================================ */
    $(document).on('click', '.btn-edit', function () {
        var id = $(this).data('id');
        
        App.ajax({
            url: '/categories/get.php?id=' + id,
            loader: true,
            onSuccess: function (c) {
                editingId = c.id;
                
                // Populate the on-page form
                $('#category-id').val(c.id);
                $('#name').val(c.name);
                $('#description').val(c.description);

                // Change UI to Edit Mode
                $('.sticky-side .form-section-title').html('<i class="fa-solid fa-pen"></i> Edit Category');
                $('#btn-save-category .btn-text').text('Update Category');
                $('#btn-cancel-edit').show();
                
                // Scroll to form for better UX on mobile
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    });

    /* Cancel Edit Mode */
    $('#btn-cancel-edit').on('click', function() {
        resetForm();
    });

    /* ================================================================
        DELETE CATEGORY
    ================================================================ */
    $(document).on('click', '.btn-delete', function () {
        var id = $(this).data('id');
        var name = $(this).data('name');

        App.utils.confirm(
            'Are you sure you want to delete "' + name + '"? Any items linked to this category may become uncategorized.',
            function () {
                App.ajax({
                    url: '/categories/delete.php',
                    method: 'POST',
                    data: { id: id },
                    loaderMsg: 'Deleting...',
                    onSuccess: function (d, msg) {
                        App.toast.success('Deleted', msg);
                        // If we were currently editing the one we deleted, reset the form
                        if(editingId == id) resetForm();
                        loadCategories();
                    }
                });
            }
        );
    });

    /* ================================================================
        HELPERS
    ================================================================ */
    function resetForm() {
        editingId = null;
        $('#category-id').val('');
        $('#category-form')[0].reset();
        App.form.clearErrors($('#category-form'));
        
        // Reset UI back to "Add" Mode
        $('.sticky-side .form-section-title').html('<i class="fa-solid fa-plus-circle"></i> Add New Category');
        $('#btn-save-category .btn-text').text('Add New Category');
        $('#btn-cancel-edit').hide();
    }

    /* ================================================================
        INIT
    ================================================================ */
    loadCategories();

});