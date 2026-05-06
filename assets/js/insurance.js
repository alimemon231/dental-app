/**
 * assets/js/insurance.js
 * Logic for Insurance Company management with Active/Deactive workflow.
 */

$(document).ready(function () {

    /* ── State ── */
    var editingId = null; 

    /* ================================================================
        LOAD TABLE
    ================================================================ */
    function loadInsurance() {
        App.ajax({
            url: '/insurance/list.php',
            method: 'GET',
            loader: false,
            onSuccess: function (data) {
                renderTable(data);
            },
            onError: function () {
                $('#insurance-tbody').html(
                    '<tr><td colspan="5"><div class="table-empty"><i class="fa-solid fa-circle-exclamation"></i> Failed to load insurance providers.</div></td></tr>'
                );
            }
        });
    }

    function renderTable(insurance) {
        if (!insurance || !insurance.length) {
            $('#insurance-tbody').html(
                '<tr><td colspan="5"><div class="table-empty"><i class="fa-solid fa-building-shield"></i> No insurance providers found.</div></td></tr>'
            );
            return;
        }

        var rows = '';
        $.each(insurance, function (i, ins) {
            var isDeactive = (ins.status === 'deactive');
            var rowClass = isDeactive ? 'row-deactivated' : '';
            var statusBadge = isDeactive 
                ? '<span class="status-badge status-deactive">Deactive</span>' 
                : '<span class="status-badge status-active">Active</span>';

            rows += '<tr class="' + rowClass + '">' +
                '<td><strong>' + (i + 1) + '</strong></td>' +
                '<td>' + App.utils.escHtml(ins.name) + '</td>' +
                '<td>' + App.utils.escHtml(ins.email || '—') + '</td>' +
                '<td>' + statusBadge + '</td>' +
                '<td>' +
                '<div class="actions">' +
                '<button class="btn btn-ghost btn-sm btn-edit" data-id="' + ins.id + '" title="Edit"><i class="fa-solid fa-pen"></i></button>';
            
            // Only show deactivation button if the insurance is currently active
            if (!isDeactive) {
                rows += '<button class="btn btn-ghost btn-sm btn-deactivate" data-id="' + ins.id + '" data-name="' + App.utils.escHtml(ins.name) + '" title="Deactivate" style="color:var(--color-danger)">' +
                        '<i class="fa-solid fa-power-off"></i></button>';
            }

            rows += '</div></td></tr>';
        });

        $('#insurance-tbody').html(rows);
    }

    /* ================================================================
        SAVE INSURANCE (create or update)
    ================================================================ */
    $('#btn-save-insurance').on('click', function (e) {
        e.preventDefault();
        var form = $('#insurance-form');

        App.form.clearErrors(form);
        if (!App.form.validate(form)) {
            App.toast.warning('Validation', 'Please provide an insurance company name.');
            return;
        }

        var formData = new FormData(form[0]); 
        var isEditing = !!editingId;
        var url = isEditing ? '/insurance/update.php' : '/insurance/create.php';

        if (isEditing) formData.append('insurance_id', editingId);

        App.ajax({
            url: url,
            method: 'POST',
            data: formData,
            btn: $('#btn-save-insurance'),
            loaderMsg: isEditing ? 'Updating...' : 'Adding...',
            onSuccess: function (d, msg) {
                App.toast.success('Success', msg);
                resetForm();
                loadInsurance();
            }
        });
    });

    /* ================================================================
        EDIT INSURANCE (Populate side form)
    ================================================================ */
    $(document).on('click', '.btn-edit', function () {
        var id = $(this).data('id');
        
        App.ajax({
            url: '/insurance/get.php?id=' + id,
            loader: true,
            onSuccess: function (ins) {
                editingId = ins.id;
                
                $('#insurance-id').val(ins.id);
                $('#name').val(ins.name);
                $('#email').val(ins.email);
                $('#notes').val(ins.description);
                
                // Set the status dropdown and show it
                $('#status').val(ins.status || 'active');
                $('#status-group').show();

                // Change UI to Edit Mode
                $('.sticky-side .form-section-title').html('<i class="fa-solid fa-pen"></i> Edit Insurance');
                $('#btn-save-insurance .btn-text').text('Update Insurance');
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
        DEACTIVATE INSURANCE (Quick Action)
    ================================================================ */
    $(document).on('click', '.btn-deactivate', function () {
        var id = $(this).data('id');
        var name = $(this).data('name');

        App.utils.confirm(
            'Are you sure you want to deactivate "' + name + '"? New patients cannot be linked to this provider.',
            function () {
                App.ajax({
                    url: '/insurance/deactivate.php',
                    method: 'POST',
                    data: { id: id },
                    loaderMsg: 'Deactivating...',
                    onSuccess: function (d, msg) {
                        App.toast.success('Status Updated', msg);
                        if(editingId == id) resetForm();
                        loadInsurance();
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
        $('#insurance-id').val('');
        $('#insurance-form')[0].reset();
        App.form.clearErrors($('#insurance-form'));
        
        // Reset UI back to "Add" Mode
        $('#status-group').hide();
        $('.sticky-side .form-section-title').html('<i class="fa-solid fa-plus-circle"></i> Add New Insurance');
        $('#btn-save-insurance .btn-text').text('Add Insurance Provider');
        $('#btn-cancel-edit').hide();
    }

    /* ================================================================
        INIT
    ================================================================ */
    loadInsurance();

});