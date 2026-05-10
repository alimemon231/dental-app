/**
 * assets/js/emp-lab.js
 * Logic for staff lab case management with dynamic tooth chart
 */

$(document).ready(function () {

    /* ── State ── */
    var currentPage = 1;
    var perPage = 20;
    var editingId = null;
    var caseTypesCache = []; // To store case type metadata (target type)

    /* ================================================================
        FETCH DROPDOWN DATA
    ================================================================ */
    function loadDropdowns() {
        // 1. Load Doctors (Filtered by current office at backend)
        App.ajax({
            url: '/emp-labs/all-doctors.php',
            method: 'GET',
            loader: false,
            onSuccess: function (data) {
                var options = '<option value="">-- Select Doctor --</option>';
                $.each(data, function (i, d) {
                    options += `<option value="${d.user_id}">${App.utils.escHtml(d.name)}</option>`;
                });
                $('#doctor_id').html(options);
            }
        });

        // 2. Load Case Types
        App.ajax({
            url: '/lab-cases/list.php', // You will need this to fetch case types
            method: 'GET',
            loader: false,
            onSuccess: function (data) {
                caseTypesCache = data;
                var options = '<option value="">-- Select Case Type --</option>';
                $.each(data, function (i, c) {
                    options += `<option value="${c.id}">${App.utils.escHtml(c.name)}</option>`;
                });
                $('#case_type_id').html(options);
            }
        });


        App.ajax({
            url: '/procedures/list.php', // Reusing your existing procedure loader
            method: 'GET',
            loader: false,
            onSuccess: function (data) {
                var options = '<option value="">-- Select Procedure --</option>';
                $.each(data, function (i, p) {
                    options += `<option value="${p.id}">${App.utils.escHtml(p.name)}</option>`;
                });
                $('#next_visit').html(options);
            }
        });
    }

    /* ================================================================
        DYNAMIC FORM LOGIC (Teeth vs Arch)
    ================================================================ */
    $('#case_type_id').on('change', function () {
        var selectedId = $(this).val();
        var selectedCase = caseTypesCache.find(c => c.id == selectedId);

        // Hide both sections first
        $('#section-teeth, #section-arch').addClass('hidden-field');
        resetToothChart();
        $('#arch_selector').val('');

        if (!selectedCase) return;

        // Logic based on "target" (teeth or arch)
        if (selectedCase.target === 'teeth') {
            $('#section-teeth').removeClass('hidden-field');
        } else if (selectedCase.target === 'arch') {
            $('#section-arch').removeClass('hidden-field');
        }
    });

    // Handle Tooth Button Clicks
    $(document).on('click', '.tooth-btn', function () {
        $(this).toggleClass('selected');
        updateToothInputs();
    });

    // Handle Arch Dropdown Logic
    $('#arch_selector').on('change', function () {
        var val = $(this).val();
        // Reset hidden inputs
        $('#u_arch_input').val('');
        $('#l_arch_input').val('');

        if (val === 'upper') {
            $('#u_arch_input').val('Full');
        } else if (val === 'lower') {
            $('#l_arch_input').val('Full');
        } else if (val === 'both') {
            $('#u_arch_input').val('Full');
            $('#l_arch_input').val('Full');
        }
    });

    function updateToothInputs() {
        var upper = [];
        var lower = [];

        $('#upper-arch .tooth-btn.selected').each(function () {
            upper.push($(this).data('tooth'));
        });
        $('#lower-arch .tooth-btn.selected').each(function () {
            lower.push($(this).data('tooth'));
        });

        $('#u_arch_input').val(upper.join(','));
        $('#l_arch_input').val(lower.join(','));
    }

    function resetToothChart() {
        $('.tooth-btn').removeClass('selected');
        $('#u_arch_input').val('');
        $('#l_arch_input').val('');
    }

    /* ================================================================
        LOAD TABLE
    ================================================================ */
    function loadLabCases(page) {
        page = page || 1;
        currentPage = page;

        App.ajax({
            url: '/emp-labs/list.php',
            method: 'GET',
            data: { page: page, limit: perPage },
            onSuccess: function (data, msg, res) {
                renderTable(data);
                // renderPagination is handled by your global App/Helper logic
            }
        });
    }

    function renderTable(records) {
        if (!records || !records.length) {
            $('#lab-tbody').html('<tr><td colspan="7"><div class="table-empty">No lab cases found.</div></td></tr>');
            return;
        }

        var rows = '';
        $.each(records, function (i, r) {
            rows += `<tr>
                <td><strong>${App.utils.escHtml(r.p_name)}</strong></td>
                <td>Dr. ${App.utils.escHtml(r.doctor_name)}</td>
                <td>${App.utils.escHtml(r.case_type_name)}</td>
                <td>${App.utils.escHtml(r.impression_type)}</td>
                <td>${App.utils.escHtml(r.next_visit_procedure)}</td>
                <td><span class="status-badge status-${r.status.toLowerCase()}">${r.status}</span></td>
                <td>
                    <div class="actions">
            <button class="btn btn-ghost btn-sm btn-receive" data-id="${r.id}" title="Mark Received" style="color:var(--color-success)">
                <i class="fa-solid fa-square-check"></i>
            </button>
            
            <button class="btn btn-ghost btn-sm btn-view" data-id="${r.id}" title="View">
                <i class="fa-solid fa-eye"></i>
            </button>
            
            <button class="btn btn-ghost btn-sm btn-edit" data-id="${r.id}" title="Edit">
                <i class="fa-solid fa-pen-to-square"></i>
            </button>
            
            <button class="btn btn-ghost btn-sm btn-delete" data-id="${r.id}" data-name="${App.utils.escHtml(r.p_name)}" title="Delete" style="color:var(--color-danger)">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
                </td>
            </tr>`;
        });
        $('#lab-tbody').html(rows);
    }

    /* ================================================================
         SAVE / UPDATE LAB CASE
     ================================================================ */
    $('#btn-save-lab').on('click', function () {
        var form = $('#lab-form');

        // 1. Basic Form Validation
        if (!App.form.validate(form)) {
            App.toast.warning('Validation', 'Please fill in all required fields.');
            return;
        }

        // 2. Clinical Validation: Ensure something is selected (Teeth or Arch)
        var isTeethVisible = $('#section-teeth').is(':visible');
        var isArchVisible = $('#section-arch').is(':visible');
        var uArchVal = $('#u_arch_input').val();
        var lArchVal = $('#l_arch_input').val();

        if (isTeethVisible && !uArchVal && !lArchVal) {
            App.toast.warning('Selection Required', 'Please select at least one tooth from the chart.');
            return;
        }

        if (isArchVisible && !$('#arch_selector').val()) {
            App.toast.warning('Selection Required', 'Please select a target arch.');
            return;
        }

        // 3. Determine URL and Data
        // If editingId is set (from the .btn-edit click), we update; otherwise, create.
        var url = editingId ? '/emp-labs/update.php' : '/emp-labs/create.php';

        // Serialize form data
        var formData = form.serializeArray();

        // If we are editing, manually push the ID into the data array
        if (editingId) {
            formData.push({ name: 'id', value: editingId });
        }

        // 4. Submit via AJAX
        App.ajax({
            url: url,
            method: 'POST',
            data: $.param(formData), // Convert array back to string
            btn: $(this),
            onSuccess: function (d, msg) {
                App.modal.close('lab-modal');
                App.toast.success('Success', msg);

                // If update, stay on current page; if new, go to page 1
                loadLabCases(editingId ? currentPage : 1);

                resetForm(); // Important: Clears editingId and UI
            }
        });
    });

    /* ================================================================
    EDIT LAB CASE
================================================================ */
    $(document).on('click', '.btn-edit', function () {
        var id = $(this).data('id');
        editingId = id;

        App.ajax({
            url: '/emp-labs/get.php?id=' + id,
            loader: true,
            onSuccess: function (r) {
                resetForm(); // Clear previous state
                editingId = r.id;
                $('#lab-modal-title').text('Edit Lab Case');

                // 1. Basic Fields
                $('input[name="patient_name"]').val(r.p_name);
                $('#doctor_id').val(r.provider);
                $('#impression_type').val(r.impression_type);
                $('#next_visit').val(r.next_visit);
                $('textarea[name="notes"]').val(r.notes);

                // 2. Handle Case Type & UI Toggle
                // We set the value and manually trigger 'change' so the JS shows/hides sections
                $('#case_type_id').val(r.case_type).trigger('change');

                // 3. Handle Tooth Chart Logic
                if (r.u_arch || r.l_arch) {
                    // Set hidden inputs
                    $('#u_arch_input').val(r.u_arch);
                    $('#l_arch_input').val(r.l_arch);

                    // If it's a 'teeth' target, we light up the buttons
                    if ($('#section-teeth').is(':visible')) {
                        var upperTeeth = r.u_arch ? r.u_arch.split(',') : [];
                        var lowerTeeth = r.l_arch ? r.l_arch.split(',') : [];

                        upperTeeth.forEach(function (num) {
                            $('#upper-arch .tooth-btn[data-tooth="' + num.trim() + '"]').addClass('selected');
                        });
                        lowerTeeth.forEach(function (num) {
                            $('#lower-arch .tooth-btn[data-tooth="' + num.trim() + '"]').addClass('selected');
                        });
                    }

                    // If it's an 'arch' target (Full), we set the dropdown
                    if ($('#section-arch').is(':visible')) {
                        if (r.u_arch === 'Full' && r.l_arch === 'Full') {
                            $('#arch_selector').val('both');
                        } else if (r.u_arch === 'Full') {
                            $('#arch_selector').val('upper');
                        } else if (r.l_arch === 'Full') {
                            $('#arch_selector').val('lower');
                        }
                    }
                }

                App.modal.open('lab-modal');
            }
        });
    });


    /* ================================================================
    VIEW LAB CASE DETAILS
================================================================ */
    $(document).on('click', '.btn-view', function () {
        var id = $(this).data('id');

        App.ajax({
            url: '/emp-labs/get.php?id=' + id,
            loader: true,
            onSuccess: function (r) {
                var html =
                    '<div class="grid-2" style="gap:var(--sp-8)">' +

                    // Left Column: Patient & Provider
                    '<div>' +
                    '<div class="form-section-title mb-4"><i class="fa-solid fa-user-doctor"></i> General Information</div>' +
                    infoRow('Patient Name', App.utils.escHtml(r.p_name)) +
                    infoRow('Provider', 'Dr. ' + App.utils.escHtml(r.doctor_name)) +
                    infoRow('Office', App.utils.escHtml(r.office_name)) +

                    '<div class="form-section-title mt-6 mb-4"><i class="fa-solid fa-tooth"></i> Clinical Details</div>' +
                    infoRow('Case Type', App.utils.escHtml(r.case_type_name)) +
                    infoRow('Impression', App.utils.escHtml(r.impression_type)) +
                    infoRow('Upper Arch', App.utils.escHtml(r.u_arch || '—')) +
                    infoRow('Lower Arch', App.utils.escHtml(r.l_arch || '—')) +
                    '</div>' +

                    // Right Column: Status & Next Visit
                    '<div>' +
                    '<div class="form-section-title mb-4"><i class="fa-solid fa-circle-info"></i> Workflow Status</div>' +
                    infoRow('Current Status', '<span class="status-badge status-' + r.status.toLowerCase() + '">' + r.status + '</span>') +
                    infoRow('Date Sent', App.utils.escHtml(r.formatted_date)) +

                    '<div class="form-section-title mt-6 mb-4"><i class="fa-solid fa-calendar-check"></i> Follow-up</div>' +
                    infoRow('Next Procedure', App.utils.escHtml(r.next_visit_procedure || '—')) +

                    // Notes Section
                    '<div class="mt-4 p-3 bg-light border-radius-sm" style="border-left: 3px solid var(--color-primary)">' +
                    '<small class="text-muted d-block mb-1">Lab Notes:</small>' +
                    '<div style="white-space: pre-wrap;">' + App.utils.escHtml(r.notes || 'No specific instructions provided.') + '</div>' +
                    '</div>' +
                    '</div>' +

                    '</div>';

                $('#view-lab-body').html(html);

                // Map ID to the edit button inside the modal for convenience
                $('#btn-edit-from-view').data('id', r.id);

                App.modal.open('view-lab-modal');
            }
        });
    });


    function infoRow(label, value) {
        return '<div class="info-row mb-2">' +
            '<span class="text-muted" style="width:130px; display:inline-block; font-size:0.85rem">' + label + ':</span>' +
            '<span class="fw-600">' + value + '</span>' +
            '</div>';
    }


    /* ================================================================
      DELETE LOGIC
  ================================================================ */
    $(document).on('click', '.btn-delete', function () {
        const id = $(this).data('id');
        const name = $(this).data('name');

        // 1. Prepare UI
        $('#confirm-title').text('Delete Lab Case');
        $('#confirm-ok').text('Delete Permanently').removeClass('btn-success').addClass('btn-danger');
        $('#confirm-body-content').html(`
        <div class="text-center">
            <i class="fa-solid fa-trash-can text-danger mb-3" style="font-size:3rem"></i>
            <p>Delete lab case for <b>${App.utils.escHtml(name)}</b>?</p>
        </div>
    `);

        // 2. CLEAR AND BIND (This prevents the double-firing issue)
        $('#confirm-ok').off('click').on('click', function () {
            App.modal.close('confirm-modal');
            executeDelete(id);
        });

        App.modal.open('confirm-modal');
    });

    /* ================================================================
        RECEIVE LOGIC
    ================================================================ */
    $(document).on('click', '.btn-receive', function () {
        const id = $(this).data('id');
        const name = $(this).closest('tr').find('td:first').text();

        // 1. Prepare UI
        $('#confirm-title').text('Confirm Receipt');
        $('#confirm-ok').text('Confirm & Send').removeClass('btn-danger').addClass('btn-success');
        $('#confirm-body-content').html(`
        <div class="text-center">
            <i class="fa-solid fa-truck-ramp-box text-success mb-3" style="font-size:3rem"></i>
            <p>Mark <b>${App.utils.escHtml(name)}</b> as Received?</p>
        </div>
    `);

        // 2. CLEAR AND BIND (This cleans up the Delete logic if it was there)
        $('#confirm-ok').off('click').on('click', function () {
            App.modal.close('confirm-modal');
            executeReceive(id);
        });

        App.modal.open('confirm-modal');
    });

    /**
     * Helper functions to keep the click listeners clean
     */
    function executeDelete(id) {
        App.ajax({
            url: '/emp-labs/delete.php',
            method: 'POST',
            data: { id: id },
            onSuccess: function (d, msg) {
                App.toast.success('Deleted', msg);
                loadLabCases(currentPage);
            }
        });
    }

    function executeReceive(id) {
        App.ajax({
            url: '/emp-labs/received.php',
            method: 'POST',
            data: { id: id },
            onSuccess: function (d, msg) {
                App.toast.success('Received', msg);
                loadLabCases(currentPage);
            }
        });
    }
    /* ================================================================
        UI HELPERS
    ================================================================ */
    $('#btn-add-lab').on('click', function () {
        resetForm();
        editingId = null
        App.modal.open('lab-modal');
    });

    function resetForm() {
        App.form.reset(document.getElementById('lab-form'));
        $('#section-teeth, #section-arch').addClass('hidden-field');
        resetToothChart();
    }

    /* ================================================================
        INIT
    ================================================================ */
    loadDropdowns();
    loadLabCases(1);
});