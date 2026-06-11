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
        FUNCTION 1: INITIALIZE PATIENT LOOKUP FROM LAB ROUTE
    ================================================================ */
    function initPatientLookup() {
        App.ajax({
            url: '/emp-pre-auth/patients-list.php',
            method: 'GET',
            loader: false,
            onSuccess: function (res) {
                var dataset = res.data || res;

                var options = '<option value="">-- Search Patients --</option>';
                $.each(dataset, function (i, item) {
                    options += `<option value="${item.id}">${App.utils.escHtml(item.name)} (DOB: ${item.dob || '—'})</option>`;
                });

                $('#patient-select')
                    .html(options)
                    .select2({
                        placeholder: "Search patients...",
                        allowClear: true,
                        width: '100%'
                    });
            }
        });
    }

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
            url: '/lab-cases/list.php',
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

        // 3. Load Next Visit Steps
        App.ajax({
            url: '/lab-steps/list.php',
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

        // 4. Load Lab Partners
        App.ajax({
            url: '/labs-settings/list.php',
            method: 'GET',
            loader: false,
            onSuccess: function (data) {
                var options = '<option value="">-- Select Lab  --</option>';
                $.each(data, function (i, p) {
                    options += `<option value="${p.id}">${App.utils.escHtml(p.name)}</option>`;
                });
                $('#lab_provider').html(options);
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
        LOAD TABLE GRID DATA
    ================================================================ */
    function loadLabCases(page) {
        page = page || 1;
        currentPage = page;

        App.ajax({
            url: '/emp-labs/list.php',
            method: 'GET',
            loader: false,
            data: { page: page, limit: perPage },
            onSuccess: function (data) {
                renderTable(data);
            },
            onError: function () {
                $('#lab-tbody').html(
                    '<tr><td colspan="7"><div class="table-empty"><i class="fa-solid fa-circle-exclamation"></i> Failed to load lab records.</div></td></tr>'
                );
            }
        });
    }

    function renderTable(records) {
        if (!records || records.length === 0) {
            $('#lab-tbody').html('<tr><td colspan="7" class="text-center text-muted">No lab cases found for this workspace.</td></tr>');
            return;
        }

        let rows = '';
        records.forEach(r => {
            const statusLower = (r.status || 'Sent').toLowerCase();
            const statusClass = 'status-' + statusLower;

            // Build conditional action buttons matrix based on status parameters
            let actionButtonsHtml = '';

            if (statusLower === 'sent') {
                // "Sent" layout: Mark Received, View, Edit, Delete
                actionButtonsHtml = `
                <button class="btn btn-ghost btn-sm btn-receive" data-id="${r.id}" title="Mark Received" style="color:var(--color-success)">
                    <i class="fa-solid fa-square-check"></i>
                </button>
                <button class="btn btn-ghost btn-sm btn-view" data-id="${r.id}" title="View Case Details"><i class="fa-solid fa-eye"></i></button>
                <button class="btn btn-ghost btn-sm btn-edit" data-id="${r.id}" title="Modify Entry"><i class="fa-solid fa-pen"></i></button>
                
            `;
            } else if (statusLower === 'received') {
                // "Received" layout: View, Edit, and Calendar Button (Schedule Lab)
                actionButtonsHtml = `
                <button class="btn btn-ghost btn-sm btn-schedule" data-id="${r.id}" title="Schedule Lab Appointment" style="color:var(--color-primary)">
                    <i class="fa-solid fa-calendar-days"></i>
                </button>
                <button class="btn btn-ghost btn-sm btn-view" data-id="${r.id}" title="View Case Details"><i class="fa-solid fa-eye"></i></button>
                <button class="btn btn-ghost btn-sm btn-edit" data-id="${r.id}" title="Modify Entry"><i class="fa-solid fa-pen"></i></button>
                
            `;
            } else {
                // Fallback default catch block for other statuses if needed
                actionButtonsHtml = `
                <button class="btn btn-ghost btn-sm btn-view" data-id="${r.id}" title="View Case Details"><i class="fa-solid fa-eye"></i></button>
            `;
            }

            rows += `
        <tr style="transition: background-color 0.2s ease;">
            <td><strong>#${r.id}</strong></td>
            <td>
                <div class="fw-600">${App.utils.escHtml(r.patient_name || '—')}</div>
                <small class="text-muted"><i class="fa-regular fa-calendar"></i> Sent: ${r.formatted_date}</small>
            </td>
            <td>Dr. ${App.utils.escHtml(r.doctor_name || '—')}</td>
            <td>${App.utils.escHtml(r.case_type_name || '—')}</td>
            <td>
                <div class="text-sm"><strong>${App.utils.escHtml(r.display_arch)}</strong></div>
                <small class="text-muted">${App.utils.escHtml(r.impression_type || '—')}</small>
            </td>
            <td>
                <div class="text-sm">${App.utils.escHtml(r.next_visit_step || '—')}</div>
                
            </td>
            <td><span class="status-badge ${statusClass}">${App.utils.escHtml(r.status || 'Sent')}</span></td>
            <td>
                <div class="actions" style="display: flex; gap: 4px;">
                    ${actionButtonsHtml}
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
        var url = editingId ? '/emp-labs/update.php' : '/emp-labs/create.php';

        // Serialize form data safely
        var formData = form.serializeArray();

        // Security check: Match backend patient lookup key constraints
        var patientLookup = formData.find(item => item.name === 'patient_id');
        if (!patientLookup) {
            var selectedPatientVal = $('#patient-select').val();
            if (selectedPatientVal) {
                formData.push({ name: 'patient_id', value: selectedPatientVal });
            }
        }

        // If we are editing, manually push the ID into the data array
        if (editingId) {
            formData.push({ name: 'id', value: editingId });
        }

        // 4. Submit via AJAX
        App.ajax({
            url: url,
            method: 'POST',
            data: $.param(formData),
            btn: $(this),
            onSuccess: function (d, msg) {
                App.modal.close('lab-modal');
                App.toast.success('Success', msg);

                // Stay on page or reset to first page
                loadLabCases(editingId ? currentPage : 1);
                resetForm();
            }
        });
    });

    /* ================================================================
        EDIT LAB CASE (Fixed Arch Selector Population)
    ================================================================ */
    $(document).on('click', '.btn-edit', function () {
        var id = $(this).data('id');
        editingId = id;

        App.ajax({
            url: '/emp-labs/get.php?id=' + id,
            loader: true,
            onSuccess: function (r) {
                resetForm();
                editingId = r.id;
                $('#lab-modal-title').text('Edit Lab Case');

                // 1. Basic Fields Mapping with cross-browser Select2 updates
                if (r.p_id) {
                    if ($('#patient-select').find("option[value='" + r.p_id + "']").length) {
                        $('#patient-select').val(r.p_id).trigger('change');
                    } else {
                        var newOption = new Option(r.patient_name || 'Selected Patient', r.p_id, true, true);
                        $('#patient-select').append(newOption).trigger('change');
                    }
                }
                $('#doctor_id').val(r.provider);
                $('#impression_type').val(r.impression_type);
                $('#next_visit').val(r.next_visit);
                $('#lab_provider').val(r.lab_provider);
                $('textarea[name="notes"]').val(r.notes);

                // 2. Handle Case Type & UI Toggle (This resets arch_selector to '')
                $('#case_type_id').val(r.case_type).trigger('change');

                // 3. Handle Tooth Chart Logic
                if (r.u_arch || r.l_arch) {
                    $('#u_arch_input').val(r.u_arch);
                    $('#l_arch_input').val(r.l_arch);

                    // Light up buttons for tooth targeting
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

                    // FIXED: Map selector using data values directly instead of checking jQuery's :visible status
                    if (r.u_arch === 'Full' && r.l_arch === 'Full') {
                        $('#arch_selector').val('both');
                    } else if (r.u_arch === 'Full') {
                        $('#arch_selector').val('upper');
                    } else if (r.l_arch === 'Full') {
                        $('#arch_selector').val('lower');
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

                    // Left Column: Patient, Provider & Clinical Details
                    '<div>' +
                    '<div class="form-section-title mb-4"><i class="fa-solid fa-user-doctor"></i> General Information</div>' +
                    infoRow('Patient Name', App.utils.escHtml(r.patient_name || '—')) +
                    infoRow('Provider', 'Dr. ' + App.utils.escHtml(r.doctor_name || '—')) +
                    infoRow('Office', App.utils.escHtml(r.office_name || '—')) +
                    infoRow('Lab Provider', App.utils.escHtml(r.lab_partner_name || '—')) +

                    '<div class="form-section-title mt-6 mb-4"><i class="fa-solid fa-tooth"></i> Clinical Details</div>' +
                    infoRow('Case Type', App.utils.escHtml(r.case_type_name || '—')) +
                    infoRow('Impression', App.utils.escHtml(r.impression_type || '—')) +
                    infoRow('Upper Arch', App.utils.escHtml(r.u_arch || '—')) +
                    infoRow('Lower Arch', App.utils.escHtml(r.l_arch || '—')) +
                    '</div>' +

                    // Right Column: Workflow Status, Timeline Logs & Follow-up
                    '<div>' +
                    '<div class="form-section-title mb-4"><i class="fa-solid fa-circle-info"></i> Workflow Status</div>' +
                    infoRow('Current Status', '<span class="status-badge status-' + (r.status || 'sent').toLowerCase() + '">' + (r.status || 'Sent') + '</span>') +
                    infoRow('Date Sent', App.utils.escHtml(r.date_sent || '—')) +
                    infoRow('Date Scheduled', App.utils.escHtml(r.date_scheduled || '—')) +

                    '<div class="form-section-title mt-6 mb-4"><i class="fa-solid fa-clock-rotate-left"></i> Audit Logs</div>' +
                    infoRow('Sent By', App.utils.escHtml(r.created_by_name || '—')) +
                    infoRow('Last Edited By', App.utils.escHtml(r.edited_by_name || '—')) +
                    infoRow('Last Edited At', App.utils.escHtml(r.edited_at || '—')) +

                    '<div class="form-section-title mt-6 mb-4"><i class="fa-solid fa-calendar-check"></i> Follow-up</div>' +
                    infoRow('Next Procedure', App.utils.escHtml(r.next_visit_step_name || '—')) +

                    // Notes Section
                    '<div class="mt-4 p-3 bg-light border-radius-sm" style="border-left: 3px solid var(--color-primary)">' +
                    '<small class="text-muted d-block mb-1">Lab Notes:</small>' +
                    '<div style="white-space: pre-wrap;">' + App.utils.escHtml(r.notes || 'No specific instructions provided.') + '</div>' +
                    '</div>' +
                    '</div>' +

                    '</div>';

                $('#view-lab-body').html(html);
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
        RECEIVE LOGIC
    ================================================================ */
    $(document).on('click', '.btn-receive', function () {
        const id = $(this).data('id');
        const name = $(this).closest('tr').find('td:nth-child(2) .fw-600').text();

        $('#confirm-title').text('Confirm Receipt');
        $('#confirm-ok').text('Confirm & Send').removeClass('btn-danger').addClass('btn-success');
        $('#confirm-body-content').html(`
            <div class="text-center">
                <i class="fa-solid fa-truck-ramp-box text-success mb-3" style="font-size:3rem"></i>
                <p>Mark <b>${App.utils.escHtml(name || 'this case')}</b> as Received?</p>
            </div>
        `);

        $('#confirm-ok').off('click').on('click', function () {
            App.modal.close('confirm-modal');
            executeReceive(id);
        });

        App.modal.open('confirm-modal');
    });

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

     // Open Schedule Modal (Kept Unchanged)
    $(document).on('click', '.btn-schedule', function () {
        const id = $(this).data('id');
        $('#schedule-lab-id').val(id);
        $('#schedule-form')[0].reset();
        App.modal.open('schedule-modal');
    });

    // Confirm Schedule Action (Kept Unchanged)
    $('#btn-confirm-schedule').on('click', function () {
        const form = $('#schedule-form');
        if (!App.form.validate(form)) return;

        App.ajax({
            url: '/m-labs/schedule.php',
            method: 'POST',
            data: form.serialize(),
            btn: $(this),
            onSuccess: function (d, msg) {
                App.toast.success('Scheduled', msg);
                App.modal.close('schedule-modal');
                loadLabCases(currentPage)
            }
        });
    });

    /* ================================================================
        UI HELPERS
    ================================================================ */
    $('#btn-add-lab').on('click', function () {
        resetForm();
        App.modal.open('lab-modal');
    });

    function resetForm() {
        var formEl = document.getElementById('lab-form');
        if (formEl) {
            App.form.reset(formEl);
        }
        $('#patient-select').val(null).trigger('change');
        $('#section-teeth, #section-arch').addClass('hidden-field');
        resetToothChart();
        $('#arch_selector').val('');
        $('#lab-modal-title').text('Create Lab Case');
        editingId = null;
    }

    /* ================================================================
        INIT
    ================================================================ */
    initPatientLookup();
    loadDropdowns();
    loadLabCases(1);
});