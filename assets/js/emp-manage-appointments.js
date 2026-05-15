/**
 * Manage Scheduled Appointments
 * Logic for marking procedures as 'Completed' or clearing dates to 'Reschedule'.
 */

let currentPage = 1;

$(document).ready(function () {
    loadScheduledAppointments();

    // Event Delegation for Table Actions
    $(document).on('click', '.btn-view', handleViewDetails);
    $(document).on('click', '.btn-complete', handleOpenCompleteModal);
    $(document).on('click', '.btn-reschedule', handleOpenRescheduleModal);

    // Modal Confirmation Actions
    $(document).on('click', '#btn-confirm-complete', submitCompletion);
    $(document).on('click', '#btn-confirm-reschedule', submitReschedule);
});

/**
 * 1. Fetch only Scheduled records for the clinic
 */
function loadScheduledAppointments(page = 1) {
    currentPage = page;

    // Show loading state
    $('#manage-appointments-tbody').html('<tr><td colspan="6" class="text-center"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</td></tr>');

    App.ajax({
        // Endpoint filters: WHERE office_id = YOUR_OFFICE AND status = 'Scheduled'
        url: '/emp-pre-auth/list-scheduled.php',
        data: { page: page },
        onSuccess: function (data) {
            renderManageTable(data.records);
            renderPagination(data.total_records, data.total_pages, data.records.length);
        }
    });
}

/**
 * 2. Render the Management Table
 */
function renderManageTable(records) {
    let html = '';
    if (!records || records.length === 0) {
        html = '<tr><td colspan="6" class="table-empty">No appointments scheduled at this time.</td></tr>';
    } else {
        records.forEach(r => {
            html += `
                <tr>
                    <td><strong>${App.utils.escHtml(r.p_first_name + ' ' + r.p_last_name)}</strong></td>
                    <td class="text-success font-bold">${r.appointment_date_fmt || r.appointment_date}</td>
                    <td>${App.utils.escHtml(r.procedure_name)}</td>
                    <td><small class="text-muted">${App.utils.escHtml(r.insurance_name)}</small></td>
                    <td><span class="status-badge status-scheduled">Scheduled</span></td>
                    <td class="text-right">
                        <div class="btn-group">
                            <button class="btn btn-sm btn-ghost btn-view" data-id="${r.id}" title="View Details">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-warning btn-reschedule" data-id="${r.id}" title="Reschedule">
                                <i class="fa-solid fa-rotate-left"></i>
                            </button>
                            <button class="btn btn-sm btn-primary btn-complete" data-id="${r.id}" title="Mark as Done">
                                <i class="fa-solid fa-check"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
        });
    }
    $('#manage-appointments-tbody').html(html);
}

/**
 * 3. Handle View Details (reusing your logic)
 */
function handleViewDetails() {
    const id = $(this).data('id');
    App.ajax({
        url: '/adm-pre-auth/get.php', // Using the detailed admin get for full info
        data: { id: id },
        onSuccess: function (data) {

            // Reusing your detailed HTML builder logic
            let infoHtml = `
    <!-- Patient & Clinic Section -->
    <div class="modal-detail-section">
        <h4 class="section-title"><i class="fa-solid fa-user"></i> Patient & Clinic</h4>
        <div class="detail-grid">
            <div class="detail-item"><strong>Patient:</strong> <span>${App.utils.escHtml(data.p_first_name + ' ' + data.p_last_name)}</span></div>
            <div class="detail-item"><strong>DOB:</strong> <span>${App.utils.escHtml(data.p_dob || '—')}</span></div>
            <div class="detail-item"><strong>Clinic:</strong> <span>${App.utils.escHtml(data.office_name || '—')}</span></div>
            <div class="detail-item"><strong>Staff:</strong> <span>${App.utils.escHtml(data.staff_name || '—')}</span></div>
            <div class="detail-item"><strong>Approval Expire Date:</strong> <span>${App.utils.escHtml(data.approval_expire_date || '—')}</span></div>
        </div>
    </div>

    <!-- Insurance & Submission Section -->
    <div class="modal-detail-section">
        <h4 class="section-title"><i class="fa-solid fa-shield-halved"></i> Insurance Details</h4>
        <div class="detail-grid">
            <div class="detail-item"><strong>Insurance Plan:</strong> <span>${App.utils.escHtml(data.insurance_name || data.p_insurance_plan || '—')}</span></div>
            <div class="detail-item"><strong>Requested On:</strong> <span>${data.created_at_fmt || '—'}</span></div>
            <div class="detail-item"><strong>Approved By:</strong> <span>${App.utils.escHtml(data.approver_name || 'Management')}</span></div>
        </div>
    </div>

    <!-- Appointment & Treatment Section -->
    <div class="modal-detail-section no-border">
        <h4 class="section-title"><i class="fa-solid fa- calendar-check"></i> Treatment Schedule</h4>
        <div class="detail-grid">
            <div class="detail-item"><strong>Scheduled For:</strong> <span class="text-success font-bold">${data.appointment_date}</span></div>
            <div class="detail-item"><strong>Procedure:</strong> <span>${App.utils.escHtml(data.procedure_name || '—')}</span></div>
            <div class="detail-item"><strong>Tooth Number(s):</strong> <span class="badge badge-outline">${App.utils.escHtml(data.tooth_numbers || 'N/A')}</span></div>
        </div>
        
        ${data.notes ? `
            <div class="mt-4 p-3 bg-light border-radius-sm">
                <small class="text-muted d-block mb-1">Office Notes:</small>
                <div class="text-sm">${App.utils.escHtml(data.notes)}</div>
            </div>
        ` : ''}
    </div>
`;

            $('#view-details-body').html(infoHtml);
            App.modal.open('view-modal');
        }
    });
}

/**
 * 4. Completion Logic
 */
function handleOpenCompleteModal() {
    const id = $(this).data('id');
    $('#complete-preauth-id').val(id);
    App.modal.open('complete-modal');
}

function submitCompletion() {
    const id = $('#complete-preauth-id').val();
    App.ajax({
        url: '/emp-pre-auth/complete-procedure.php',
        method: 'POST',
        data: { id: id },
        loaderMsg: 'Finalizing...',
        onSuccess: function (res, msg) {
            App.toast.success('Success', 'Procedure marked as completed.');
            App.modal.close('complete-modal');
            loadScheduledAppointments(currentPage);
        }
    });
}

/**
 * 5. Reschedule Logic
 */
function handleOpenRescheduleModal() {
    const id = $(this).data('id');
    $('#reschedule-preauth-id').val(id);
    App.modal.open('reschedule-modal');
}

function submitReschedule() {
    const id = $('#reschedule-preauth-id').val();
    App.ajax({
        url: '/emp-pre-auth/reschedule-request.php',
        method: 'POST',
        data: { id: id },
        loaderMsg: 'Resetting date...',
        onSuccess: function (res, msg) {
            App.toast.success('Status Updated', 'Appointment cleared for rescheduling.');
            App.modal.close('reschedule-modal');
            loadScheduledAppointments(currentPage);
        }
    });
}

/**
 * 6. Helper: Pagination
 */
function renderPagination(totalRecords, totalPages, currentCount) {
    $('#manage-info').text(`Showing ${currentCount} of ${totalRecords} scheduled patients`);
    let html = '';
    if (totalPages > 1) {
        for (let i = 1; i <= totalPages; i++) {
            const activeClass = i === currentPage ? 'active' : '';
            html += `<button class="page-link ${activeClass}" onclick="loadScheduledAppointments(${i})">${i}</button>`;
        }
    }
    $('#manage-pagination-btns').html(html);
}