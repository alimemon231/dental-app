/**
 * assets/js/labs-manage.js
 * Logic for Lab Vendor management (Name, Address, Email, Phone).
 */

$(document).ready(function () {

    /* ── State ── */
    var editingId = null; 

    /* ================================================================
        LOAD TABLE
    ================================================================ */
    function loadLabs() {
        App.ajax({
            url: '/labs-settings/list.php', // Assuming this returns the full list
            method: 'GET',
            loader: false,
            onSuccess: function (data) {
                // If your list.php returns {records: [], meta: []}, 
                // use data.records. Otherwise use data directly.
                var records = data.records ? data.records : data;
                renderTable(records);
            },
            onError: function () {
                $('#labs-tbody').html(
                    '<tr><td colspan="4"><div class="table-empty"><i class="fa-solid fa-circle-exclamation"></i> Failed to load lab vendors.</div></td></tr>'
                );
            }
        });
    }

    function renderTable(labs) {
        if (!labs || !labs.length) {
            $('#labs-tbody').html(
                '<tr><td colspan="4"><div class="table-empty"><i class="fa-solid fa-industry"></i> No registered labs found.</div></td></tr>'
            );
            return;
        }

        var rows = '';
        $.each(labs, function (i, item) {
            var isDeactive = (item.status === 'deactive');
            var rowClass = isDeactive ? 'row-deactivated' : '';
            var statusBadge = isDeactive 
                ? '<span class="status-badge status-deactive">Deactive</span>' 
                : '<span class="status-badge status-active">Active</span>';
            
            rows += '<tr class="' + rowClass + '">' +
                '<td>' +
                    '<div class="font-bold">' + App.utils.escHtml(item.name) + '</div>' +
                    '<div class="text-xs text-muted">' + App.utils.escHtml(item.address || 'No address provided') + '</div>' +
                '</td>' +
                '<td>' +
                    '<div class="text-sm"><i class="fa-solid fa-phone fa-xs"></i> ' + App.utils.escHtml(item.phone || '—') + '</div>' +
                    '<div class="text-sm"><i class="fa-solid fa-envelope fa-xs"></i> ' + App.utils.escHtml(item.email || '—') + '</div>' +
                '</td>' +
                '<td>' + statusBadge + '</td>' +
                '<td>' +
                    '<div class="actions">' +
                        '<button class="btn btn-ghost btn-sm btn-edit" data-id="' + item.id + '" title="Edit Lab"><i class="fa-solid fa-pen"></i></button>';
            
            if (!isDeactive) {
                rows += '<button class="btn btn-ghost btn-sm btn-deactivate" data-id="' + item.id + '" data-name="' + App.utils.escHtml(item.name) + '" title="Deactivate" style="color:var(--color-danger)">' +
                        '<i class="fa-solid fa-power-off"></i></button>';
            }

            rows += '</div></td></tr>';
        });

        $('#labs-tbody').html(rows);
    }

    /* ================================================================
        SAVE LAB (Create or Update)
    ================================================================ */
    $('#lab-register-form').on('submit', function (e) {
        e.preventDefault();
        var form = $(this);

        App.form.clearErrors(form);
        
        // Basic Validation (Name is required)
        if (!App.form.validate(form)) {
            App.toast.warning('Validation', 'Lab Name is required.');
            return;
        }

        var formData = new FormData(form[0]); 
        var isEditing = !!editingId;
        var url = isEditing ? '/labs-settings/update.php' : '/labs-settings/create.php';

        // Ensure ID is passed if editing
        if (isEditing) formData.append('id', editingId);

        App.ajax({
            url: url,
            method: 'POST',
            data: formData,
            btn: $('#btn-save-lab'),
            loaderMsg: isEditing ? 'Updating Lab...' : 'Registering Lab...',
            onSuccess: function (d, msg) {
                App.toast.success('Success', msg);
                resetForm();
                loadLabs();
            }
        });
    });

    /* ================================================================
        EDIT LAB (Populate Form)
    ================================================================ */
    $(document).on('click', '.btn-edit', function () {
        var id = $(this).data('id');
        
        App.ajax({
            url: '/labs-settings/get.php?id=' + id,
            method: 'GET',
            loader: true,
            onSuccess: function (data) {
                editingId = data.id;
                
                $('#lab-id').val(data.id);
                $('#lab-name').val(data.name);
                $('#lab-address').val(data.address);
                $('#lab-email').val(data.email);
                $('#lab-phone').val(data.phone);
                
                // Set status and show group
                $('#lab-status').val(data.status || 'active');
                $('#status-group').show();

                // Update UI to Edit Mode
                $('#form-title').html('<i class="fa-solid fa-pen"></i> Edit Lab Details');
                $('#btn-save-lab .btn-text').text('Update Lab');
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
        DEACTIVATE LAB (Quick Action)
    ================================================================ */
    $(document).on('click', '.btn-deactivate', function () {
        var id = $(this).data('id');
        var name = $(this).data('name');

        App.utils.confirm(
            'Are you sure you want to deactivate "' + name + '"? This lab will no longer be available for selection.',
            function () {
                App.ajax({
                    url: '/labs-settings/deactivate.php',
                    method: 'POST',
                    data: { id: id },
                    loaderMsg: 'Deactivating...',
                    onSuccess: function (d, msg) {
                        App.toast.success('Status Updated', msg);
                        if(editingId == id) resetForm();
                        loadLabs();
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
        $('#lab-id').val('');
        $('#lab-register-form')[0].reset();
        $('#lab-register-form').removeClass('was-validated');
        App.form.clearErrors($('#lab-register-form'));
        
        // Reset UI back to "Register" Mode
        $('#status-group').hide();
        $('#form-title').html('<i class="fa-solid fa-industry"></i> Register New Lab');
        $('#btn-save-lab .btn-text').text('Register Lab');
        $('#btn-cancel-edit').hide();
    }

    /* ================================================================
        INIT
    ================================================================ */
    loadLabs();

});