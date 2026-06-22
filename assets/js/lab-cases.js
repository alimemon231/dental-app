/**
 * assets/js/lab-cases.js
 * Logic for Lab Case Type management (Teeth/Arch targets).
 */

$(document).ready(function () {

    /* ── State ── */
    var editingId = null; 

    /* ================================================================
        LOAD TABLE
    ================================================================ */
    function loadLabCases() {
        App.ajax({
            url: '/lab-cases/list.php',
            method: 'GET',
            loader: false,
            onSuccess: function (data) {
                renderTable(data);
            },
            onError: function () {
                $('#lab-case-tbody').html(
                    '<tr><td colspan="5"><div class="table-empty"><i class="fa-solid fa-circle-exclamation"></i> Failed to load lab case types.</div></td></tr>'
                );
            }
        });
    }

    function renderTable(cases) {
        if (!cases || !cases.length) {
            $('#lab-case-tbody').html(
                '<tr><td colspan="5"><div class="table-empty"><i class="fa-solid fa-flask"></i> No lab case types found.</div></td></tr>'
            );
            return;
        }

        var rows = '';
        $.each(cases, function (i, item) {
            var isDeactive = (item.status === 'deactive');
            var rowClass = isDeactive ? 'row-deactivated' : '';
            var statusBadge = isDeactive 
                ? '<span class="status-badge status-deactive">Deactive</span>' 
                : '<span class="status-badge status-active">Active</span>';
            
            // Format target for better UI display
            var targetDisplay = item.target === 'arch' 
                ? '<i class="fa-solid fa-grip-lines"></i> Arch' 
                : '<i class="fa-solid fa-tooth"></i> Teeth';

            rows += '<tr class="' + rowClass + '">' +
                '<td><strong>' + (i + 1) + '</strong></td>' +
                '<td>' + App.utils.escHtml(item.name) + '</td>' +
                '<td>' + targetDisplay + '</td>' +
                '<td>' + item.price + '</td>' +
                '<td>' + statusBadge + '</td>' +
                '<td>' +
                '<div class="actions">' +
                '<button class="btn btn-ghost btn-sm btn-edit" data-id="' + item.id + '" title="Edit"><i class="fa-solid fa-pen"></i></button>';
            
            if (!isDeactive) {
                rows += '<button class="btn btn-ghost btn-sm btn-deactivate" data-id="' + item.id + '" data-name="' + App.utils.escHtml(item.name) + '" title="Deactivate" style="color:var(--color-danger)">' +
                        '<i class="fa-solid fa-power-off"></i></button>';
            }

            rows += '</div></td></tr>';
        });

        $('#lab-case-tbody').html(rows);
    }

    /* ================================================================
        SAVE CASE (Create or Update)
    ================================================================ */
    $('#btn-save-case').on('click', function (e) {
        e.preventDefault();
        var form = $('#lab-case-form');

        App.form.clearErrors(form);
        
        // Basic Validation
        if (!App.form.validate(form)) {
            App.toast.warning('Validation', 'Please fill in all required fields.');
            return;
        }

        var formData = new FormData(form[0]); 
        var isEditing = !!editingId;
        var url = isEditing ? '/lab-cases/update.php' : '/lab-cases/create.php';

        if (isEditing) formData.append('case_id', editingId);

        App.ajax({
            url: url,
            method: 'POST',
            data: formData,
            btn: $('#btn-save-case'),
            loaderMsg: isEditing ? 'Updating Case...' : 'Adding Case...',
            onSuccess: function (d, msg) {
                App.toast.success('Success', msg);
                resetForm();
                loadLabCases();
            }
        });
    });

    /* ================================================================
        EDIT CASE (Populate Form)
    ================================================================ */
    $(document).on('click', '.btn-edit', function () {
        var id = $(this).data('id');
        
        App.ajax({
            url: '/lab-cases/get.php?id=' + id,
            loader: true,
            onSuccess: function (data) {
                editingId = data.id;
                
                $('#case-id').val(data.id);
                $('#name').val(data.name);
                $('#target').val(data.target);
                $('#price').val(data.price);
                
                // Set the status dropdown and show it
                $('#status').val(data.status || 'active');
                $('#status-group').show();

                // Update UI to Edit Mode
                $('#form-title').html('<i class="fa-solid fa-pen"></i> Edit Case Type');
                $('#btn-save-case .btn-text').text('Update Case Type');
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
        DEACTIVATE CASE (Quick Action)
    ================================================================ */
    $(document).on('click', '.btn-deactivate', function () {
        var id = $(this).data('id');
        var name = $(this).data('name');

        App.utils.confirm(
            'Are you sure you want to deactivate "' + name + '"? It will no longer appear in new lab orders.',
            function () {
                App.ajax({
                    url: '/lab-cases/deactivate.php',
                    method: 'POST',
                    data: { id: id },
                    loaderMsg: 'Deactivating...',
                    onSuccess: function (d, msg) {
                        App.toast.success('Status Updated', msg);
                        if(editingId == id) resetForm();
                        loadLabCases();
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
        $('#case-id').val('');
        $('#lab-case-form')[0].reset();
        App.form.clearErrors($('#lab-case-form'));
        
        // Reset UI back to "Add" Mode
        $('#status-group').hide();
        $('#form-title').html('<i class="fa-solid fa-flask"></i> Add New Case Type');
        $('#btn-save-case .btn-text').text('Add Case Type');
        $('#btn-cancel-edit').hide();
    }

    /* ================================================================
        INIT
    ================================================================ */
    loadLabCases();

});