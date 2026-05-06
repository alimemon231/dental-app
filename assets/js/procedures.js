/**
 * assets/js/procedures.js
 * Logic for Dental Procedure management with Active/Deactive workflow.
 */

$(document).ready(function () {

    /* ── State ── */
    var editingId = null; 

    /* ================================================================
        LOAD TABLE
    ================================================================ */
    function loadProcedures() {
        App.ajax({
            url: '/procedures/list.php',
            method: 'GET',
            loader: false,
            onSuccess: function (data) {
                renderTable(data);
            },
            onError: function () {
                $('#procedures-tbody').html(
                    '<tr><td colspan="5"><div class="table-empty"><i class="fa-solid fa-circle-exclamation"></i> Failed to load procedures.</div></td></tr>'
                );
            }
        });
    }

    function renderTable(procedures) {
        if (!procedures || !procedures.length) {
            $('#procedures-tbody').html(
                '<tr><td colspan="5"><div class="table-empty"><i class="fa-solid fa-tooth"></i> No procedures found.</div></td></tr>'
            );
            return;
        }

        var rows = '';
        $.each(procedures, function (i, p) {
            var isDeactive = (p.status === 'deactive');
            var rowClass = isDeactive ? 'row-deactivated' : '';
            var statusBadge = isDeactive 
                ? '<span class="status-badge status-deactive">Deactive</span>' 
                : '<span class="status-badge status-active">Active</span>';

            rows += '<tr class="' + rowClass + '">' +
                '<td><strong>' + (i + 1) + '</strong></td>' +
                '<td>' + App.utils.escHtml(p.name) + '</td>' +
                '<td>' + App.utils.escHtml(p.description || '—') + '</td>' +
                '<td>' + statusBadge + '</td>' +
                '<td>' +
                '<div class="actions">' +
                '<button class="btn btn-ghost btn-sm btn-edit" data-id="' + p.id + '" title="Edit"><i class="fa-solid fa-pen"></i></button>';
            
            // Only show deactivation button if the procedure is currently active
            if (!isDeactive) {
                rows += '<button class="btn btn-ghost btn-sm btn-deactivate" data-id="' + p.id + '" data-name="' + App.utils.escHtml(p.name) + '" title="Deactivate" style="color:var(--color-danger)">' +
                        '<i class="fa-solid fa-power-off"></i></button>';
            }

            rows += '</div></td></tr>';
        });

        $('#procedures-tbody').html(rows);
    }

    /* ================================================================
        SAVE PROCEDURE (create or update)
    ================================================================ */
    $('#btn-save-procedure').on('click', function (e) {
        e.preventDefault();
        var form = $('#procedure-form');

        App.form.clearErrors(form);
        if (!App.form.validate(form)) {
            App.toast.warning('Validation', 'Please provide a procedure name.');
            return;
        }

        var formData = new FormData(form[0]); 
        var isEditing = !!editingId;
        var url = isEditing ? '/procedures/update.php' : '/procedures/create.php';

        if (isEditing) formData.append('procedure_id', editingId);

        App.ajax({
            url: url,
            method: 'POST',
            data: formData,
            btn: $('#btn-save-procedure'),
            loaderMsg: isEditing ? 'Updating...' : 'Adding...',
            onSuccess: function (d, msg) {
                App.toast.success('Success', msg);
                resetForm();
                loadProcedures();
            }
        });
    });

    /* ================================================================
        EDIT PROCEDURE (Populate side form)
    ================================================================ */
    $(document).on('click', '.btn-edit', function () {
        var id = $(this).data('id');
        
        App.ajax({
            url: '/procedures/get.php?id=' + id,
            loader: true,
            onSuccess: function (p) {
                editingId = p.id;
                
                $('#procedure-id').val(p.id);
                $('#name').val(p.name);
                $('#description').val(p.description);
                
                // Set the status dropdown and show it
                $('#status').val(p.status || 'active');
                $('#status-group').show();

                // Change UI to Edit Mode
                $('.sticky-side .form-section-title').html('<i class="fa-solid fa-pen"></i> Edit Procedure');
                $('#btn-save-procedure .btn-text').text('Update Procedure');
                $('#btn-cancel-edit').show();
                
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    });

    /* Cancel Edit Mode */
    $('#btn-cancel-edit').on('click', function() {
        resetForm();
    });

    /* ================================================================
        DEACTIVATE PROCEDURE (Quick Action)
    ================================================================ */
    $(document).on('click', '.btn-deactivate', function () {
        var id = $(this).data('id');
        var name = $(this).data('name');

        // Reuse the app-wide confirm utility but targeted at deactivation
        App.utils.confirm(
            'Are you sure you want to deactivate "' + name + '"? It will no longer appear in new Pre-Auth requests.',
            function () {
                App.ajax({
                    url: '/procedures/deactivate.php',
                    method: 'POST',
                    data: { id: id },
                    loaderMsg: 'Deactivating...',
                    onSuccess: function (d, msg) {
                        App.toast.success('Status Updated', msg);
                        if(editingId == id) resetForm();
                        loadProcedures();
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
        $('#procedure-id').val('');
        $('#procedure-form')[0].reset();
        App.form.clearErrors($('#procedure-form'));
        
        // Reset UI back to "Add" Mode
        $('#status-group').hide();
        $('.sticky-side .form-section-title').html('<i class="fa-solid fa-plus-circle"></i> Add New Procedure');
        $('#btn-save-procedure .btn-text').text('Add New Procedure');
        $('#btn-cancel-edit').hide();
    }

    /* ================================================================
        INIT
    ================================================================ */
    loadProcedures();

});