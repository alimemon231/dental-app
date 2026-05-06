let currentPage = 1;

function loadRequests(page = 1) {
    currentPage = page;
    App.ajax({
        url: '/m-pre-auth/list.php?page=' + page,
        onSuccess: function (data) {
            renderTable(data.records);
        }
    });
}

function renderTable(records) {
    let html = '';
    if (records.length === 0) {
        html = '<tr><td colspan="7" class="text-center">No pending requests found.</td></tr>';
    } else {
        records.forEach(r => {
            let statusClass = 'status-' + r.status.toLowerCase();
            html += `
                <tr>
                    <td><strong>${App.utils.escHtml(r.clinic_name)}</strong></td>
                    <td>${App.utils.escHtml(r.p_first_name + ' ' + r.p_last_name)}</td>
                    <td>${App.utils.escHtml(r.insurance_name || '—')}</td>
                    <td>${App.utils.escHtml(r.procedure_name || '—')}</td>
                    <td><small>${App.utils.escHtml(r.creator_name)}</small></td>
                    <td><span class="status-badge ${statusClass}">${r.status}</span></td>
                    <td>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-ghost btn-view" data-id="${r.id}" title="View Details">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            ${r.status === 'Sent' ? `
                                <button class="btn btn-sm btn-success btn-approve" data-id="${r.id}" data-name="${r.p_first_name}">
                                    <i class="fa-solid fa-check"></i>
                                </button>
                                <button class="btn btn-sm btn-danger btn-reject" data-id="${r.id}" data-name="${r.p_first_name}">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>`;
        });
    }
    $('#m-preauth-tbody').html(html);
}



/* ================================================================
    M-STAFF ACTION HANDLERS (APPROVE / REJECT)
================================================================ */

// Handle APPROVE Button
$(document).on('click', '.btn-approve', function () {
    const id = $(this).data('id');
    const name = $(this).data('name');

    // Setup Modal UI
    $('#confirm-title').text('Approve Pre-Auth');
    $('#confirm-ok').text('Approve Request').removeClass('btn-danger').addClass('btn-success');

    $('#confirm-body-content').html(`
        <div class="text-center">
            <i class="fa-solid fa-circle-check text-success mb-3" style="font-size:3rem"></i>
            <p>Are you sure you want to <strong>APPROVE</strong> the request for <b>${App.utils.escHtml(name)}</b>?</p>
            <p class="text-muted text-sm mt-2">This will notify the clinic and mark the record as verified.</p>
        </div>
    `);

    // Unbind previous clicks and set new action
    $('#confirm-ok').off('click').on('click', function () {
        updateStatus(id, 'Approved');
        App.modal.close('confirm-modal');
    });

    App.modal.open('confirm-modal');
});

// Handle REJECT Button
$(document).on('click', '.btn-reject', function () {
    const id = $(this).data('id');
    const name = $(this).data('name');

    // Setup Modal UI
    $('#confirm-title').text('Reject Pre-Auth');
    $('#confirm-ok').text('Reject Request').removeClass('btn-success').addClass('btn-danger');

    $('#confirm-body-content').html(`
        <div class="text-center">
            <i class="fa-solid fa-circle-xmark text-danger mb-3" style="font-size:3rem"></i>
            <p>Are you sure you want to <strong>REJECT</strong> the request for <b>${App.utils.escHtml(name)}</b>?</p>
            <p class="text-danger text-sm mt-2">This action is irreversible.</p>
        </div>
    `);

    // Unbind previous clicks and set new action
    $('#confirm-ok').off('click').on('click', function () {
        updateStatus(id, 'Rejected');
        App.modal.close('confirm-modal');
    });

    App.modal.open('confirm-modal');
});

/**
 * Common function to hit the API
 */
function updateStatus(id, newStatus) {
    App.ajax({
        url: '/m-pre-auth/update-status.php',
        method: 'POST',
        data: {
            id: id,
            status: newStatus
        },
        loaderMsg: 'Updating status...',
        onSuccess: function (res, msg) {
            App.toast.success('Record ' + newStatus, msg);
            loadRequests(currentPage); // Refresh the table
        }
    });
}

/* ================================================================
    VIEW PRE-AUTH REQUEST (M-STAFF)
================================================================ */
$(document).on('click', '.btn-view', function () {
    const id = $(this).data('id');

    App.ajax({
        url: '/m-pre-auth/get.php?id=' + id, // Uses the updated GET with JOINs
        loader: true,
        onSuccess: function (r) {
            const statusClass = 'status-' + (r.status ? r.status.toLowerCase() : 'sent');

            const html = `
                <div class="grid-2" style="gap:var(--sp-8)">
                    <!-- Left Column: Patient & Office -->
                    <div>
                        <div class="form-section-title mb-4"><i class="fa-solid fa-hospital"></i> Origin Info</div>
                        ${infoRow('Clinic Name', `<strong>${App.utils.escHtml(r.clinic_name || '—')}</strong>`)}
                        ${infoRow('Submitted By', App.utils.escHtml(r.creator_name || '—'))}
                        ${infoRow('Date Sent', r.formatted_date || '—')}

                        <div class="form-section-title mt-6 mb-4"><i class="fa-solid fa-user"></i> Patient Details</div>
                        ${infoRow('Patient Name', App.utils.escHtml(r.p_first_name + ' ' + r.p_last_name))}
                        ${infoRow('DOB', App.utils.escHtml(r.p_dob || '—'))}
                    </div>

                    <!-- Right Column: Clinical & Status -->
                    <div>
                        <div class="form-section-title mb-4"><i class="fa-solid fa-file-invoice"></i> Authorization Details</div>
                        ${infoRow('Insurance', App.utils.escHtml(r.insurance_name || r.p_insurance_plan || '—'))}
                        ${infoRow('Procedure', App.utils.escHtml(r.procedure_name || r.treatment_type || '—'))}
                        ${infoRow('Tooth #', `<span class="badge-tooth">${App.utils.escHtml(r.tooth_numbers || '—')}</span>`)}

                        <div class="form-section-title mt-6 mb-4"><i class="fa-solid fa-circle-info"></i> Review Status</div>
                        ${infoRow('Current Status', `<span class="status-badge ${statusClass}">${r.status}</span>`)}
                        
                        ${r.notes ? `
                            <div class="mt-4 p-3 bg-light border-radius-sm border">
                                <small class="text-muted d-block mb-1">Office Notes:</small>
                                <div class="text-sm">${App.utils.escHtml(r.notes)}</div>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;

            $('#view-preauth-body').html(html);
            App.modal.open('view-preauth-modal');
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
    loadRequests();
});