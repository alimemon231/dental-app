/**
 * Admin Pre-Authorization Monitoring Logic
 * Handles global pipeline tracking, filtering, and visual status rendering.
 */

let currentPage = 1;

$(document).ready(function () {
    // 1. Initial Load
    loadClinics();
    loadAdminData(1);

   // 2. Event Listeners
    $('#btn-search').on('click', function () {
        loadAdminData(1);
    });

    $('#btn-clear').on('click', function () {
        $('#filter-patient').val('');
        $('#filter-clinic').val('');
        $('#filter-start-date').val('');
        $('#filter-end-date').val('');
        $('#filter-status').val('');
        loadAdminData(1);
    });

    // Print Listener
    $('#btn-print').on('click', function () {
        printPipelineTable();
    });

    // Pagination Click Events
    $(document).on('click', '.page-link', function (e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (page) loadAdminData(page);
    });

    // View Details Click
    $(document).on('click', '.btn-view', function () {
        const id = $(this).data('id');
        viewLifecycleDetails(id);
    });
});

/**
 * Fetches clinics to populate the filter dropdown
 */
function loadClinics() {
    App.ajax({
        url: '/offices/list.php',
        onSuccess: function (data, message) {
            let html = '<option value="">All Clinics</option>';
            data.forEach(clinic => {
                html += `<option value="${clinic.id}">${App.utils.escHtml(clinic.office_name)}</option>`;
            });
            $('#filter-clinic').html(html);
        }
    });
}

/**
 * Main data fetcher with multi-dimensional filtering
 */
function loadAdminData(page = 1) {
    currentPage = page;
    
    const filters = {
        page: currentPage,
        patient_name: $('#filter-patient').val(), // New filter
        clinic_id: $('#filter-clinic').val(),
        start_date: $('#filter-start-date').val(), // New filter
        end_date: $('#filter-end-date').val(),     // New filter
        status: $('#filter-status').val()
    };

    $('#admin-tbody').html('<tr><td colspan="6" class="text-center"><i class="fa-solid fa-spinner fa-spin"></i> Fetching pipeline data...</td></tr>');

    App.ajax({
        url: '/adm-pre-auth/list.php',
        data: filters,
        onSuccess: function (response) {
            renderAdminTable(response.records);
            renderPagination(response.total_records, response.total_pages, response.records.length);
        }
    });
}

/**
 * Print Functionality
 * Extracts the table and prints it with minimal styling
 */
function printPipelineTable() {
    const tableHtml = $('#admin-table').clone();
    
    // Remove the 'Actions' column from the printed version
    tableHtml.find('th:last-child, td:last-child').remove();

    const printWindow = window.open('', '_blank', 'height=600,width=900');
    printWindow.document.write('<html><head><title>Pre-Auth Pipeline Report</title>');
    printWindow.document.write('<link rel="stylesheet" href="assets/css/global.css">');
    printWindow.document.write('<style>body{padding:20px} table{width:100%; border-collapse:collapse;} th,td{border:1px solid #ddd; padding:8px; text-align:left;} .stage-success{background:#6ef787 !important; color:#2b8a3e;} .stage-danger{background:#ee5050 !important; color:#fff;} .stage-pending{background:#eddc87 !important;}</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write('<h1>Pre-Authorization Pipeline Report</h1>');
    printWindow.document.write('<p>Report Generated: ' + new Date().toLocaleString() + '</p>');
    printWindow.document.write(tableHtml[0].outerHTML);
    printWindow.document.write('</body></html>');
    
    printWindow.document.close();
    printWindow.focus();
    
    // Give CSS time to load before triggering print dialog
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 500);
}

/**
 * Renders the 4-stage visual pipeline table
 */
/**
 * Renders the 4-stage visual pipeline table with cascade logic
 */
function renderAdminTable(records) {
    if (!records || records.length === 0) {
        $('#admin-tbody').html('<tr><td colspan="6" class="table-empty">No records found matching your filters.</td></tr>');
        return;
    }

    let html = '';
    records.forEach(r => {
        const status = r.status; // Sent, Approved, Rejected, Scheduled, Completed
        const hasDate = !!r.appointment_date;

        // --------------------------------------------------------
        // STAGE 1: SENT (Entry Point)
        // Rule: Always Green
        // --------------------------------------------------------
        const colSent = `<td class="stage-cell stage-success">Sent<br><small>${r.created_at_date}</small></td>`;

        // --------------------------------------------------------
        // STAGE 2: DECISION (Approved / Rejected)
        // --------------------------------------------------------
        let colDecision;
        if (status === 'Rejected') {
            colDecision = `<td class="stage-cell stage-danger">Rejected<br><small>By: ${App.utils.escHtml(r.approver_name || 'Admin')}</small></td>`;
        } else if (['Approved', 'Scheduled', 'Completed'].includes(status) || hasDate) {
            colDecision = `<td class="stage-cell stage-success">Approved<br><small>By: ${App.utils.escHtml(r.approver_name || 'Admin')}</small></td>`;
        } else {
            colDecision = `<td class="stage-cell stage-pending"><i class="fa-solid fa-minus"></i></td>`;
        }

        // --------------------------------------------------------
        // STAGE 3: BOOKING (Scheduled)
        // --------------------------------------------------------
        let colBooked;
        if (status === 'Rejected') {
            colBooked = `<td class="stage-cell stage-danger"><i class="fa-solid fa-xmark"></i></td>`;
        } else if (hasDate || status === 'Completed') {
            colBooked = `<td class="stage-cell stage-success">Scheduled<br><small>${r.appointment_date_fmt || r.appointment_date}</small></td>`;
        } else {
            colBooked = `<td class="stage-cell stage-pending"><i class="fa-solid fa-minus"></i></td>`;
        }

        // --------------------------------------------------------
        // STAGE 4: RESULT (Completed)
        // --------------------------------------------------------
        let colCompleted;
        if (status === 'Rejected') {
            colCompleted = `<td class="stage-cell stage-danger"><i class="fa-solid fa-xmark"></i></td>`;
        } else if (status === 'Completed') {
            colCompleted = `<td class="stage-cell stage-success">Completed</td>`;
        } else {
            colCompleted = `<td class="stage-cell stage-pending"><i class="fa-solid fa-minus"></i></td>`;
        }

        html += `
            <tr>
                <td>
                    <div class="font-bold">${App.utils.escHtml(r.p_first_name + ' ' + r.p_last_name)}</div>
                    <div class="text-xs text-muted">(${App.utils.escHtml(r.insurance_name)}) ${App.utils.escHtml(r.procedure_name)}</div>
                    <div class="text-xs mt-1"><i class="fa-solid fa-house-medical"></i> ${App.utils.escHtml(r.office_name)}</div>
                </td>
                ${colSent}
                ${colDecision}
                ${colBooked}
                ${colCompleted}
                <td>
                    <button class="btn btn-sm btn-ghost btn-view" data-id="${r.id}" title="View Lifecycle">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </td>
            </tr>`;
    });
    $('#admin-tbody').html(html);
}

/**
 * Custom Pagination Handler
 */
function renderPagination(totalRecords, totalPages, currentCount) {
    $('#patients-info').text(`Showing ${currentCount} of ${totalRecords} records`);
    
    let html = '';
    if (totalPages > 1) {
        for (let i = 1; i <= totalPages; i++) {
            const activeClass = i === currentPage ? 'active' : '';
            html += `<button class="page-link ${activeClass}" data-page="${i}">${i}</button>`;
        }
    }
    $('#pagination-btns').html(html);
}

/**
 * View detailed record and update the Modal Progress Bar
 */
function viewLifecycleDetails(id) {
    App.ajax({
        url: '/adm-pre-auth/get.php',
        data: { id: id },
        onSuccess: function (data, message) {
            updateProgressBar(data.status);
            
            // Build a more comprehensive detail view
            let infoHtml = `
                <div class="modal-detail-section">
                    <h4 class="section-title"><i class="fa-solid fa-user-shield"></i> Patient & Insurance</h4>
                    <div class="detail-grid">
                        <div class="detail-item"><strong>Patient Name:</strong> <span>${data.p_first_name} ${data.p_last_name}</span></div>
                        <div class="detail-item"><strong>Date of Birth:</strong> <span>${data.p_dob}</span></div>
                        <div class="detail-item"><strong>Insurance Plan:</strong> <span>${data.insurance_name}</span></div>
                        <div class="detail-item"><strong>Member ID:</strong> <span>${data.p_insurance_id || 'N/A'}</span></div>
                    </div>
                </div>

                <div class="modal-detail-section">
                    <h4 class="section-title"><i class="fa-solid fa-stethoscope"></i> Treatment Info</h4>
                    <div class="detail-grid">
                        <div class="detail-item"><strong>Procedure:</strong> <span>${data.procedure_name}</span></div>
                        <div class="detail-item"><strong>Clinic:</strong> <span>${data.office_name}</span></div>
                        <div class="detail-item"><strong>Tooth #s:</strong> <span>${data.tooth_numbers || 'N/A'}</span></div>
                        <div class="detail-item"><strong>Submitted By:</strong> <span>${data.staff_name}</span></div>
                    </div>
                </div>

                <div class="modal-detail-section">
                    <h4 class="section-title"><i class="fa-solid fa-clock-rotate-left"></i> Timeline Details</h4>
                    <div class="detail-grid">
                        <div class="detail-item"><strong>Date Sent:</strong> <span>${data.created_at_fmt}</span></div>
                        <div class="detail-item"><strong>Decision By:</strong> <span>${data.approver_name || 'Pending'}</span></div>
                        <div class="detail-item"><strong>Appointment:</strong> <span class="${data.appointment_date_fmt ? 'text-success font-bold' : ''}">${data.appointment_date || 'Not Scheduled Yet'}</span></div>
                        <div class="detail-item"><strong>Status:</strong> <span class="badge badge-${data.status.toLowerCase()}">${data.status}</span></div>
                    </div>
                </div>

                <div class="modal-detail-section no-border">
                    <h4 class="section-title"><i class="fa-solid fa-comment-dots"></i> Admin Notes</h4>
                    <div class="notes-box">${data.notes || 'No notes provided for this record.'}</div>
                </div>
            `;
            
            $('#view-details-body').html(infoHtml);
            App.modal.open('view-modal');
        }
    });
}

/**
 * Updates the Visual Progress Bar in the Modal
 */
function updateProgressBar(status) {
    const stages = ['Sent', 'Approved', 'Scheduled', 'Completed'];
    // Handle 'Rejected' case: Treat it as stage 2 for position but visually different if needed
    const effectiveStatus = (status === 'Rejected') ? 'Approved' : status; 
    const currentIdx = stages.indexOf(effectiveStatus);
    
    let html = '';
    stages.forEach((label, idx) => {
        let stateClass = '';
        
        if (status === 'Completed') {
            stateClass = 'completed';
        } else if (idx < currentIdx) {
            stateClass = 'completed';
        } else if (idx === currentIdx) {
            stateClass = (status === 'Rejected') ? 'danger' : 'active';
        }
        
        const displayLabel = (idx === 1 && status === 'Rejected') ? 'Rejected' : label;

        html += `
            <div class="step ${stateClass}">
                <div class="step-icon">${idx + 1}</div>
                <div class="step-label">${displayLabel}</div>
            </div>`;
    });
    $('#modal-progress-bar').html(html);
}