/**
 * 1. Load only Approved Pre-Auths for the logged-in office
 */
function loadApprovedRequests(page = 1) {
    currentPage = page;
    App.ajax({
        // This endpoint should filter: WHERE office_id = YOUR_OFFICE AND status = 'Approved'
        url: '/emp-pre-auth/list-approved.php?page=', 
        onSuccess: function (data) {
            renderTable(data.records);
            // Assuming your App.js handles pagination UI
            //if(typeof renderPagination === "function") renderPagination(data);
        }
    });
}

/**
 * 2. Render the Table
 */
function renderTable(records) {
    let html = '';
    if (!records || records.length === 0) {
        html = '<tr><td colspan="6" class="text-center">No approved authorizations ready for booking.</td></tr>';
    } else {
        records.forEach(r => {
            const statusClass = 'status-approved'; // Hardcoded since we only fetch approved
            html += `
                <tr>
                    <td><strong>${App.utils.escHtml(r.p_first_name + ' ' + r.p_last_name)}</strong></td>
                    <td>${App.utils.escHtml(r.p_dob || '—')}</td>
                    <td><small>${App.utils.escHtml(r.approver_name || 'Management')}</small></td>
                    <td><small>${App.utils.escHtml(r.approval_expire_date || '-')}</small></td>
                    <td>${r.formatted_date || '—'}</td>
                    <td><span class="status-badge ${statusClass}">Approved</span></td>
                    <td>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-ghost btn-view" data-id="${r.id}" title="View Details">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-primary btn-book" data-id="${r.id}" title="Schedule Appointment">
                                <i class="fa-solid fa-calendar-plus"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
        });
    }
    $('#appointment-tbody').html(html);
}

/* ================================================================
    ACTION HANDLERS
================================================================ */

/* ================================================================
    VIEW PRE-AUTH DETAILS (BOOKING CONTEXT)
================================================================ */
$(document).on('click', '.btn-view', function () {
    const id = $(this).data('id');

    App.ajax({
        url: '/emp-pre-auth/get.php?id=' + id,
        loader: true,
        onSuccess: function (r) {
            const statusClass = 'status-' + (r.status ? r.status.toLowerCase() : 'sent');
            
            const html = `
                <div class="grid-2" style="gap:var(--sp-8)">
                    <!-- Left Column: Patient & Insurance -->
                    <div>
                        <div class="form-section-title mb-4">
                            <i class="fa-solid fa-user"></i> Patient Information
                        </div>
                        ${infoRow('Full Name', App.utils.escHtml(r.p_first_name + ' ' + r.p_last_name))}
                        ${infoRow('Date of Birth', App.utils.escHtml(r.p_dob || '—'))}
                        ${infoRow('Insurance Plan', App.utils.escHtml(r.insurance_name || r.p_insurance_plan || '—'))}

                        <div class="form-section-title mt-6 mb-4">
                            <i class="fa-solid fa-stethoscope"></i> Treatment Details
                        </div>
                        ${infoRow('Procedure', App.utils.escHtml(r.procedure_name || r.treatment_type || '—'))}
                        ${infoRow('Tooth Numbers', App.utils.escHtml(r.tooth_numbers || '—'))}
                    </div>

                    <!-- Right Column: Status & Timeline -->
                    <div>
                        <div class="form-section-title mb-4">
                            <i class="fa-solid fa-circle-info"></i> Submission Status
                        </div>
                        ${infoRow('Current Status', `<span class="status-badge ${statusClass}">${r.status}</span>`)}
                        ${infoRow('Submission Time', `${r.formatted_date} (${r.time_ago})`)}

                        <!-- Internal Notes -->
                        <div class="mt-4 p-3 bg-light border-radius-sm border">
                            <small class="text-muted d-block mb-1">Office Notes:</small>
                            <div class="text-sm">
                                ${App.utils.escHtml(r.notes || r.description || 'No notes provided.')}
                            </div>
                        </div>
                        
                        ${r.appointment_date ? `
                            <div class="mt-4 p-3 bg-success-light border-radius-sm border border-success">
                                <small class="text-success d-block mb-1">Scheduled For:</small>
                                <strong>${r.appointment_date}</strong>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;

            $('#view-details-body').html(html);
            App.modal.open('view-modal');
        }
    });
});

// Handle BOOK Button (Calendar Icon) - Open Modal
$(document).on('click', '.btn-book', function () {
    const id = $(this).data('id');
    $('#book-preauth-id').val(id); // Set hidden ID
    $('#appointment_date').val(''); // Clear previous value
    App.modal.open('book-modal');
});

// Handle CONFIRM Booking (Save Appointment Date)
$(document).on('click', '#btn-confirm-book', function () {
    const id = $('#book-preauth-id').val();
    const dateValue = $('#appointment_date').val();

    if (!dateValue) {
        App.toast.error('Please select a valid date.');
        return;
    }

    App.ajax({
        url: '/emp-pre-auth/book-appointment.php',
        method: 'POST',
        data: {
            id: id,
            appointment_date: dateValue
        },
        loaderMsg: 'Scheduling...',
        onSuccess: function (res, msg) {
            App.toast.success('Appointment Scheduled', msg);
            App.modal.close('book-modal');
            loadApprovedRequests(currentPage); // Refresh table
        }
    });
});

/**
 * Helper function for consistent row styling
 */
function infoRow(label, value) {
    return `
        <div class="info-row mb-2 d-flex">
            <span class="text-muted" style="min-width:120px; font-size:0.85rem">${label}:</span>
            <span class="text-dark">${value}</span>
        </div>`;
}

// Initial Load
$(document).ready(function () {
    loadApprovedRequests();
});