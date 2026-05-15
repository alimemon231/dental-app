/**
 * assets/js/lab-steps.js
 * Logic for Lab Workflow Steps management (id, name, status).
 */

$(document).ready(function () {

    /* ── State ── */
    var editingId = null; 

    /* ================================================================
        LOAD TABLE
    ================================================================ */
    function loadLabSteps() {
        App.ajax({
            url: '/lab-steps/list.php',
            method: 'GET',
            loader: false,
            onSuccess: function (data) {
                // Handle both direct arrays or objects containing a records key
                var records = data.records ? data.records : data;
                renderTable(records);
            },
            onError: function () {
                $('#lab-step-tbody').html(
                    '<tr><td colspan="4"><div class="table-empty"><i class="fa-solid fa-circle-exclamation"></i> Failed to load lab steps.</div></td></tr>'
                );
            }
        });
    }

    function renderTable(steps) {
        if (!steps || !steps.length) {
            $('#lab-step-tbody').html(
                '<tr><td colspan="4"><div class="table-empty"><i class="fa-solid fa-list-check"></i> No lab steps defined.</div></td></tr>'
            );
            return;
        }

        var rows = '';
        $.each(steps, function (i, item) {
            var isDeactive = (item.status === 'deactive');
            var rowClass = isDeactive ? 'row-deactivated' : '';
            var statusBadge = isDeactive 
                ? '<span class="status-badge status-deactive">Deactive</span>' 
                : '<span class="status-badge status-active">Active</span>';
            
            rows += '<tr class="' + rowClass + '">' +
                '<td><strong>' + (i + 1) + '</strong></td>' +
                '<td>' + App.utils.escHtml(item.name) + '</td>' +
                '<td>' + statusBadge + '</td>' +
                '<td>' +
                    '<div class="actions">' +
                        '<button class="btn btn-ghost btn-sm btn-edit" data-id="' + item.id + '" title="Edit Step"><i class="fa-solid fa-pen"></i></button>';
            
            if (!isDeactive) {
                rows += '<button class="btn btn-ghost btn-sm btn-deactivate" data-id="' + item.id + '" data-name="' + App.utils.escHtml(item.name) + '" title="Deactivate" style="color:var(--color-danger)">' +
                        '<i class="fa-solid fa-power-off"></i></button>';
            }

            rows += '</div></td></tr>';
        });

        $('#lab-step-tbody').html(rows);
    }

    /* ================================================================
        SAVE STEP (Create or Update)
    ================================================================ */
    $('#lab-step-form').on('submit', function (e) {
        e.preventDefault();
        var form = $(this);

        App.form.clearErrors(form);
        
        // Validation (Step Name is required)
        if (!App.form.validate(form)) {
            App.toast.warning('Validation', 'Step Name is required.');
            return;
        }

        var formData = new FormData(form[0]); 
        var isEditing = !!editingId;
        var url = isEditing ? '/lab-steps/update.php' : '/lab-steps/create.php';

        if (isEditing) formData.append('id', editingId);

        App.ajax({
            url: url,
            method: 'POST',
            data: formData,
            btn: $('#btn-save-step'),
            loaderMsg: isEditing ? 'Updating Step...' : 'Adding Step...',
            onSuccess: function (d, msg) {
                App.toast.success('Success', msg);
                resetForm();
                loadLabSteps();
            }
        });
    });

    /* ================================================================
        EDIT STEP (Populate Form)
    ================================================================ */
    $(document).on('click', '.btn-edit', function () {
        var id = $(this).data('id');
        
        App.ajax({
            url: '/lab-steps/get.php?id=' + id,
            method: 'GET',
            loader: true,
            onSuccess: function (data) {
                editingId = data.id;
                
                $('#step-id').val(data.id);
                $('#step-name').val(data.name);
                
                // Set status and show group
                $('#step-status').val(data.status || 'active');
                $('#status-group').show();

                // Update UI to Edit Mode
                $('#form-title').html('<i class="fa-solid fa-pen"></i> Edit Lab Step');
                $('#btn-save-step .btn-text').text('Update Step');
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
        DEACTIVATE STEP (Quick Action)
    ================================================================ */
    $(document).on('click', '.btn-deactivate', function () {
        var id = $(this).data('id');
        var name = $(this).data('name');

        App.utils.confirm(
            'Are you sure you want to deactivate "' + name + '"? It will no longer be selectable in lab orders.',
            function () {
                App.ajax({
                    url: '/lab-steps/deactivate.php',
                    method: 'POST',
                    data: { id: id },
                    loaderMsg: 'Deactivating...',
                    onSuccess: function (d, msg) {
                        App.toast.success('Status Updated', msg);
                        if(editingId == id) resetForm();
                        loadLabSteps();
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
        $('#step-id').val('');
        $('#lab-step-form')[0].reset();
        $('#lab-step-form').removeClass('was-validated');
        App.form.clearErrors($('#lab-step-form'));
        
        // Reset UI back to "Add" Mode
        $('#status-group').hide();
        $('#form-title').html('<i class="fa-solid fa-list-check"></i> Add New Step');
        $('#btn-save-step .btn-text').text('Add Step');
        $('#btn-cancel-edit').hide();
    }

    /* ================================================================
        INIT
    ================================================================ */
    loadLabSteps();

});