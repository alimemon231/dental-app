let currentPage = 1;

$(document).ready(function () {
    loadGlobalLabs(1);

    // View Details
    $(document).on('click', '.btn-view', function () {
        const id = $(this).data('id');

        App.ajax({
            url: '/m-labs/get.php?id=' + id,
            loader: true,
            onSuccess: function (r) {
                let html = `
                <div class="grid-2">
                    <div>
                        <div class="form-section-title"><i class="fa-solid fa-user"></i> Patient & Clinic</div>
                        ${infoRow('Patient Name', `<strong>${App.utils.escHtml(r.patient_name || '—')}</strong>`)}
                        ${infoRow('Clinic Location', App.utils.escHtml(r.office_location || '—'))}
                        ${infoRow('Doctor', 'Dr. ' + App.utils.escHtml(r.doctor_name || '—'))}
                        ${infoRow('Lab Provider', App.utils.escHtml(r.lab_partner_name || '—'))}
                        ${infoRow('Case Type', App.utils.escHtml(r.case_type_name || '—'))}
                    </div>

                    <div>
                        <div class="form-section-title"><i class="fa-solid fa-clock"></i> Tracking Status</div>
                        ${infoRow('Current Status', `<span class="status-badge status-${(r.status || 'Sent').toLowerCase()}">${r.status || 'Sent'}</span>`)}
                        ${infoRow('Sent By', App.utils.escHtml(r.created_by_name || '—') + ' on ' + r.fmt_sent_date)}
                        ${infoRow('Received By', App.utils.escHtml(r.received_by_name || '—') + ' on ' + r.fmt_received_date)}
                    </div>
                </div>

                <div class="grid-2 mt-4">
                    <div>
                        <div class="form-section-title"><i class="fa-solid fa-tooth"></i> Clinical Details</div>
                        ${infoRow('Impression', App.utils.escHtml(r.impression_type || '—'))}
                        ${infoRow('Arch / Teeth', App.utils.escHtml(r.display_arch || '—'))}
                        ${infoRow('Next Procedure', App.utils.escHtml(r.next_visit_procedure || '—'))}
                        ${infoRow('Scheduled Date', App.utils.escHtml(r.fmt_scheduled_date || '—'))}
                    </div>

                    <div>
                        <div class="form-section-title"><i class="fa-solid fa-clock-rotate-left"></i> Audit Logs</div>
                        ${infoRow('Last Edited By', App.utils.escHtml(r.edited_by_name || '—'))}
                        ${infoRow('Last Edited At', App.utils.escHtml(r.fmt_edited_at || '—'))}
                        
                        <div class="form-section-title mt-3"><i class="fa-solid fa-comment-medical"></i> Internal Notes</div>
                        <div class="p-3 bg-light border rounded text-sm" style="min-height: 80px; white-space: pre-wrap;">${r.notes ? App.utils.escHtml(r.notes) : '<span class="text-muted italic">No clinical notes provided.</span>'}</div>
                    </div>
                </div>
                `;

                $('#view-details-body').html(html);
                App.modal.open('view-modal');
            }
        });
    });

    function infoRow(label, value) {
        return '<div class="info-row mb-2">' +
            '<span class="text-muted" style="width:130px; display:inline-block; font-size:0.85rem">' + label + ':</span>' +
            '<span class="fw-600">' + value + '</span>' +
            '</div>';
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
                loadGlobalLabs(currentPage);
            }
        });
    });
});

function loadGlobalLabs(page) {
    currentPage = page;
    App.ajax({
        url: '/m-labs/list.php?page=' + page,
        loader: true,
        onSuccess: function (data) {
            let rows = '';

            if (data && data.length > 0) {
                data.forEach(r => {

                    console.log(r)
                    const receivedDateDisp = r.date_received
                        ? `<small class="text-success">${r.fmt_received_date}</small>`
                        : `<small class="text-muted italic">Not received</small>`;

                    rows += `<tr>
                        <td>
                            <div class="text-bold text-primary">${App.utils.escHtml(r.office_location || '—')}</div>
                            <small class="text-muted">ID: #LAB-${r.id}</small>
                        </td>
                        <td><strong>${App.utils.escHtml(r.patient_name || '—')}</strong></td>
                        <td>Dr. ${App.utils.escHtml(r.doctor_name || '—')}</td>
                        <td>${App.utils.escHtml(r.case_type_name || '—')}</td>
                        <td>${r.fmt_sent_date}</td>
                        <td>${receivedDateDisp}</td>
                        <td><span class="status-badge status-${(r.status || 'Sent').toLowerCase()}">${r.status || 'Sent'}</span></td>
                        <td>
                            <div class="actions" style="display: flex; gap: 4px;">
                                <button class="btn btn-ghost btn-sm btn-view" data-id="${r.id}" title="View Full Details">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                                <button class="btn btn-primary btn-sm btn-schedule" data-id="${r.id}" title="Schedule Appointment">
                                    <i class="fa-solid fa-calendar-plus"></i> Schedule
                                </button>
                            </div>
                        </td>
                    </tr>`;
                });
            }

            $('#m-labs-tbody').html(rows || '<tr><td colspan="8" class="text-center p-4">No lab cases found in the system.</td></tr>');
        }
    });
}