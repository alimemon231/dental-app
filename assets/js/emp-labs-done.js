$(document).ready(function () {
    loadScheduledLabs();

    // ==========================================
    // WORKFLOW TASK A: COMPLETE LAB PROCESS
    // ==========================================
    $(document).on('click', '.btn-mark-done', function () {
        const id = $(this).data('id');
        const name = $(this).data('name');
        $('#complete-lab-id').val(id);
        $('#complete-patient-name').text(name);
        App.modal.open('complete-modal');
    });

    $('#btn-confirm-done').on('click', function () {
        const id = $('#complete-lab-id').val();
        const btn = $(this);

        App.ajax({
            url: '/emp-labs/complete.php',
            method: 'POST',
            data: { id: id },
            btn: btn,
            onSuccess: function (d, msg) {
                App.toast.success('Completed', msg);
                App.modal.close('complete-modal');
                loadScheduledLabs();
            }
        });
    });

    // ==========================================
    // WORKFLOW TASK B: RESCHEDULE LAB ENGINE
    // ==========================================
    $(document).on('click', '.btn-rescedule', function () {
        const id = $(this).data('id');
        const name = $(this).data('name');
        
        // Populate modal data targets
        $('#reschedule-lab-id').val(id);
        $('#reschedule-patient-name').text(name);
        $('#reschedule-date').val(''); // Reset input value element matrix
        
        App.modal.open('reschedule-modal');
    });

    $('#btn-confirm-reschedule').on('click', function () {
        const id = $('#reschedule-lab-id').val();
        const newDate = $('#reschedule-date').val();
        const btn = $(this);

        if (!newDate) {
            App.toast.error('Validation Error', 'Please select a valid future date and time coordinate.');
            return;
        }

        App.ajax({
            url: '/emp-labs/reschedule.php',
            method: 'POST',
            data: { 
                id: id,
                date_scheduled: newDate 
            },
            btn: btn,
            onSuccess: function (d, msg) {
                App.toast.success('Rescheduled', msg);
                App.modal.close('reschedule-modal');
                loadScheduledLabs();
            }
        });
    });

    // ==========================================
    // WORKFLOW TASK C: RENDER DETAIL PROFILES
    // ==========================================
    $(document).on('click', '.btn-view', function () {
        var id = $(this).data('id');

        App.ajax({
            url: '/emp-labs/get.php?id=' + id,
            loader: true,
            onSuccess: function (r) {
                var html =
                    '<div class="grid-2" style="gap:var(--sp-8)">' +

                    '<div>' +
                    '<div class="form-section-title mb-4"><i class="fa-solid fa-user-doctor"></i> General Information</div>' +
                    infoRow('Lab ID', '<span class="text-bold text-primary">#LAB-' + r.id + '</span>') +
                    infoRow('Patient Name', App.utils.escHtml(r.patient_name || '—')) +
                    infoRow('Clinic Office', App.utils.escHtml(r.office_name || '—')) +
                    infoRow('Provider', 'Dr. ' + App.utils.escHtml(r.doctor_name || '—')) +
                    infoRow('Lab Partner', App.utils.escHtml(r.lab_partner_name || '—')) +

                    '<div class="form-section-title mt-6 mb-4"><i class="fa-solid fa-tooth"></i> Clinical Details</div>' +
                    infoRow('Case Type', App.utils.escHtml(r.case_type_name || '—')) +
                    infoRow('Impression', App.utils.escHtml(r.impression_type || '—')) +
                    infoRow('Upper Arch', App.utils.escHtml(r.u_arch || '—')) +
                    infoRow('Lower Arch', App.utils.escHtml(r.l_arch || '—')) +
                    '</div>' +

                    '<div>' +
                    '<div class="form-section-title mb-4"><i class="fa-solid fa-circle-info"></i> Workflow Status</div>' +
                    infoRow('Current Status', '<span class="status-badge status-' + (r.status || 'sent').toLowerCase() + '">' + (r.status || 'Sent') + '</span>') +
                    infoRow('Date Sent', App.utils.escHtml(r.date_sent || '—')) +
                    infoRow('Sent By', App.utils.escHtml(r.created_by_name || '—')) +
                    infoRow('Date Received', App.utils.escHtml(r.date_received || '—')) +
                    infoRow('Date Scheduled', App.utils.escHtml(r.date_scheduled || '—')) +

                    '<div class="form-section-title mt-6 mb-4"><i class="fa-solid fa-clock-rotate-left"></i> Audit History</div>' +
                    infoRow('Last Edited By', App.utils.escHtml(r.edited_by_name || '—')) +
                    infoRow('Last Edited At', App.utils.escHtml(r.edited_at || '—')) +

                    '<div class="form-section-title mt-6 mb-4"><i class="fa-solid fa-calendar-check"></i> Follow-up</div>' +
                    infoRow('Next Procedure', App.utils.escHtml(r.next_visit_step_name || '—')) +

                    '<div class="mt-4 p-3 bg-light border-radius-sm" style="border-left: 3px solid var(--color-primary); padding-left:12px;">' +
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
});

// ================================================================
// DATA RETRIEVAL GENERATION MATRIX WITH ACTIVE FILTERS
// ================================================================
function loadScheduledLabs() {
    

    // Show smooth inline loading spinner matching target tbody design metrics
    $('#labs-done-tbody').html(`
        <tr>
            <td colspan="7">
                <div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>
            </td>
        </tr>
    `);

    // 1. Gather filtered parameters directly from the DOM input control elements
    var patientName = $('#filter-patient-name').val();
    var status = $('#filter-status').val();
    var caseId = $('#filter-case-id').val();

    App.ajax({
        url: '/emp-labs/list-scheduled.php',
        method: 'GET',
        loader: false,
        // 2. Pass the filter query payload parameters alongside runtime page values
        data: {
            patient_name: patientName,
            status: status,
            case_id: caseId
        },
        onSuccess: function (response) {
            // Unpack dataset cleanly whether it returns a paginated envelope payload or a flat array
            var records = response.records || response.data || response;
            let rows = '';
            
            if (records && records.length > 0) {
                records.forEach(r => {
                    rows += `<tr>
                        <td>
                            <div class="text-bold text-primary">#LAB-${r.id}</div>
                        </td>
                        <td><strong>${App.utils.escHtml(r.patient_name || '—')}</strong></td>
                        <td>${r.fmt_scheduled_date || '—'}</td>
                        <td>Dr. ${App.utils.escHtml(r.doctor_name || '—')}</td>
                        <td>${App.utils.escHtml(r.case_type_name || '—')}</td>
                        <td><span class="status-badge status-${(r.status || 'scheduled').toLowerCase()}">${r.status || 'Scheduled'}</span></td>
                        <td class="text-right">
                            <div class="actions" style="display: flex; gap: 4px; justify-content: flex-end;">
                                <button class="btn btn-ghost btn-sm btn-view" data-id="${r.id}" title="View Full Details">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                                <button class="btn btn-primary btn-sm btn-mark-done" data-id="${r.id}" data-name="${App.utils.escHtml(r.patient_name || 'Patient')}">
                                    <i class="fa-solid fa-check"></i> Done
                                </button>
                                <button class="btn btn-danger btn-sm btn-rescedule" data-id="${r.id}" data-name="${App.utils.escHtml(r.patient_name || 'Patient')}" title="Reschedule Appointment">
                                    <i class="fa-solid fa-rotate"></i>
                                </button>
                            </div>
                        </td>
                    </tr>`;
                });
            }
            
            $('#labs-done-tbody').html(rows || '<tr><td colspan="7" class="text-center p-4">No scheduled lab procedures found.</td></tr>');
        },
        onError: function () {
            $('#labs-done-tbody').html(
                '<tr><td colspan="7"><div class="table-empty"><i class="fa-solid fa-circle-exclamation"></i> Failed to load scheduled lab records.</div></td></tr>'
            );
        }
    });
}


// ================================================================
// SCHEDULED LABS FILTER CONTROL CLICK PIPELINE HANDLER
// ================================================================
$(document).on('click', '#btn-filter-table', function (e) {
    e.preventDefault();

    // 1. Immediately inject the smooth loading spinner row into the target scheduled table body
    $('#labs-done-tbody').html(`
        <tr>
            <td colspan="7">
                <div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>
            </td>
        </tr>
    `);

    // 2. Animate the button filter icon itself for layout visual feedback
    var $icon = $(this).find('i');
    $icon.addClass('fa-spin');

    // 3. Hot reload the runtime pipeline jumping back safely to page 1
    if (typeof loadScheduledLabs === 'function') {
        loadScheduledLabs(1);
    }

    // 4. Remove animation class from the button once request execution begins
    setTimeout(function () {
        $icon.removeClass('fa-spin');
    }, 600);
});


function infoRow(label, value) {
    return '<div class="info-row mb-2">' +
        '<span class="text-muted" style="width:130px; display:inline-block; font-size:0.85rem">' + label + ':</span>' +
        '<span class="fw-600">' + value + '</span>' +
        '</div>';
}