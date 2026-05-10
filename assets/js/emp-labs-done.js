$(document).ready(function () {
    loadScheduledLabs();

    // Open Completion Modal
    $(document).on('click', '.btn-mark-done', function () {
        const id = $(this).data('id');
        const name = $(this).data('name');
        $('#complete-lab-id').val(id);
        $('#complete-patient-name').text(name);
        App.modal.open('complete-modal');
    });

    // Handle AJAX Completion
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
                    infoRow('Date Sent', App.utils.escHtml(r.date_sent)) +
                    infoRow('Date Received', App.utils.escHtml(r.date_received)) +
                    infoRow('Date Scheduled', App.utils.escHtml(r.date_scheduled)) +

                    '<div class="form-section-title mt-6 mb-4"><i class="fa-solid fa-calendar-check"></i> Follow-up</div>' +
                    infoRow('Next Procedure', App.utils.escHtml(r.next_visit_procedure || '—')) +

                    // Notes Section
                    '<div class="mt-4 p-3 bg-light border-radius-sm" style="border-left: 3px solid var(--color-primary); padding-left:12px;">' +
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

    
});



function loadScheduledLabs() {
    App.ajax({
        url: '/emp-labs/list-scheduled.php',
        onSuccess: function (data) {
            let rows = '';
            data.forEach(r => {
                rows += `<tr>
                    <td><strong>${r.p_name}</strong></td>
                    <td>${r.fmt_scheduled_date}</td>
                    <td>Dr. ${r.doctor_name}</td>
                    <td>${r.case_type_name}</td>
                    <td><span class="status-badge status-scheduled">Scheduled</span></td>
                    <td class="text-right">
                        <button class="btn btn-ghost btn-sm btn-view" data-id="${r.id}"><i class="fa-solid fa-eye"></i></button>
                        <button class="btn btn-primary btn-sm btn-mark-done" data-id="${r.id}" data-name="${r.p_name}">
                            <i class="fa-solid fa-check"></i> Done
                        </button>
                    </td>
                </tr>`;
            });
            $('#labs-done-tbody').html(rows || '<tr><td colspan="6" class="text-center">No scheduled lab procedures found.</td></tr>');
        }
    });
}


function infoRow(label, value) {
        return '<div class="info-row mb-2">' +
            '<span class="text-muted" style="width:130px; display:inline-block; font-size:0.85rem">' + label + ':</span>' +
            '<span class="fw-600">' + value + '</span>' +
            '</div>';
    }