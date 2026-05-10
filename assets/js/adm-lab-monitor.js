/**
 * Admin Lab Case Monitoring Logic
 * Handles global pipeline tracking, advanced filtering, and visual status rendering.
 */

let currentPage = 1;

$(document).ready(function () {
    // 1. Initial Load
    initDropdowns();
    loadLabMonitor(1);

    // 2. Filter Event Listeners
    $('#btn-search').on('click', function () {
        loadLabMonitor(1);
    });

    $('#btn-clear').on('click', function () {
        $('.form-control').val('');
        // Reset Multiselect
        $('.status-checkbox').prop('checked', false);
        $('#status-display span').text('Select Statuses').addClass('text-muted');
        $('#status-options').hide();

        loadLabMonitor(1);
    });

    // --- Custom Status Multiselect UI Logic ---
    $(document).on('click', '#status-display', function (e) {
        e.stopPropagation();
        $('#status-options').toggle();
    });

    $(document).on('click', function () {
        $('#status-options').hide();
    });

    $('#status-options').on('click', function (e) {
        e.stopPropagation();
    });

    $(document).on('change', '.status-checkbox', function () {
        let selectedCount = $('.status-checkbox:checked').length;
        if (selectedCount > 0) {
            $('#status-display span').text(selectedCount + ' Statuses Selected').removeClass('text-muted');
        } else {
            $('#status-display span').text('Select Statuses').addClass('text-muted');
        }
    });

    // 3. Print Listener
    $('#btn-print').on('click', function () {
        printLabPipeline();
    });

    // 4. Pagination Events
    $(document).on('click', '.page-link', function (e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (page) loadLabMonitor(page);
    });

    // 5. View Details Click
    $(document).on('click', '.btn-view', function () {
        const id = $(this).data('id');
        viewLabLifecycle(id);
    });
});

/**
 * Populate filter dropdowns from API
 */
function initDropdowns() {
    // Clinics
    App.ajax({
        url: '/offices/list.php',
        onSuccess: function(data) {
            data.forEach(o => $('#filter-clinic').append(`<option value="${o.id}">${o.office_name}</option>`));
        }
    });
    // Providers
    App.ajax({
        url: '/doctor/list.php',
        onSuccess: function(data) {
            data.forEach(p => $('#filter-provider').append(`<option value="${p.user_id}">Dr. ${p.name}</option>`));
        }
    });
    // Lab Types
    App.ajax({
        url: '/lab-cases/list.php',
        onSuccess: function(data) {
            data.forEach(t => $('#filter-lab-type').append(`<option value="${t.id}">${t.name}</option>`));
        }
    });
}

/**
 * Main Data Fetcher
 */
function loadLabMonitor(page = 1) {
    currentPage = page;

    let selectedStatuses = [];
    $('.status-checkbox:checked').each(function () {
        selectedStatuses.push($(this).val());
    });

    const filters = {
        page: currentPage,
        patient: $('#filter-patient').val(),
        office_id: $('#filter-clinic').val(),
        provider_id: $('#filter-provider').val(),
        case_type: $('#filter-lab-type').val(),
        start_date: $('#filter-start-date').val(),
        end_date: $('#filter-end-date').val(),
        statuses: selectedStatuses.join(',')
    };

    $('#lab-monitor-tbody').html('<tr><td colspan="6" class="text-center"><i class="fa-solid fa-spinner fa-spin"></i> Loading lab cases...</td></tr>');

    App.ajax({
        url: '/adm-labs/list.php',
        data: filters,
        onSuccess: function (response) {
            renderLabTable(response.records);
            renderPagination(response.total_records, response.total_pages, response.records.length);
        }
    });
}

/**
 * Renders the Pipeline Table with Stage Logic
 */
function renderLabTable(records) {
    if (!records || records.length === 0) {
        $('#lab-monitor-tbody').html('<tr><td colspan="6" class="text-center p-4">No lab cases found.</td></tr>');
        return;
    }

    let html = '';
    records.forEach(r => {
        // Stage 1: Sent (Always success if in list)
        const stage1 = `<td class="stage-cell stage-success">Sent<br><small>${App.utils.formatDate(r.date_sent)}</small></td>`;
        
        // Stage 2: Arrived (date_received)
        const stage2 = r.date_received 
            ? `<td class="stage-cell stage-success">Arrived<br><small>${App.utils.formatDate(r.date_received)}</small></td>`
            : `<td class="stage-cell stage-pending"><i class="fa-solid fa-clock"></i></td>`;

        // Stage 3: Booked (date_scheduled)
        const stage3 = r.date_scheduled 
            ? `<td class="stage-cell stage-success">Booked<br><small>${App.utils.formatDate(r.date_scheduled)}</small></td>`
            : `<td class="stage-cell stage-pending"><i class="fa-solid fa-calendar-minus"></i></td>`;

        // Stage 4: Result (status === 'Done')
        const stage4 = (r.status === 'Done')
            ? `<td class="stage-cell stage-success">Done<br><small>${App.utils.formatDate(r.date_complete)}</small></td>`
            : `<td class="stage-cell stage-pending"><i class="fa-solid fa-vial"></i></td>`;

        html += `
            <tr>
                <td>
                    <div class="font-bold">${App.utils.escHtml(r.p_name)}</div>
                    <div class="text-xs text-muted">${App.utils.escHtml(r.type_name)} | Dr. ${App.utils.escHtml(r.doctor_name)}</div>
                    <div class="text-xs mt-1"><i class="fa-solid fa-location-dot"></i> ${App.utils.escHtml(r.office_name)}</div>
                </td>
                ${stage1} ${stage2} ${stage3} ${stage4}
                <td class="text-right">
                    <button class="btn btn-sm btn-ghost btn-view" data-id="${r.id}">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </td>
            </tr>`;
    });
    $('#lab-monitor-tbody').html(html);
}

/**
 * Pagination Handler
 */
function renderPagination(totalRecords, totalPages, currentCount) {
    $('#patients-info').text(`Showing ${currentCount} of ${totalRecords} records`);
    let html = '';
    if (totalPages > 1) {
        for (let i = 1; i <= totalPages; i++) {
            html += `<button class="page-link ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
        }
    }
    $('#pagination-btns').html(html);
}

/**
 * Detailed Lifecycle Fetcher
 */
function viewLabLifecycle(id) {
    App.ajax({
        url: '/adm-labs/get.php',
        data: { id: id },
        onSuccess: function (data) {
            updateProgressBar(data.status);
            
            let infoHtml = `
                <div class="modal-detail-section">
                    <h4 class="section-title"><i class="fa-solid fa-flask"></i> Case Information</h4>
                    <div class="detail-grid">
                        <div class="detail-item"><strong>Patient:</strong> <span>${data.p_name}</span></div>
                        <div class="detail-item"><strong>Case Type:</strong> <span>${data.type_name}</span></div>
                        <div class="detail-item"><strong>Provider:</strong> <span>Dr. ${data.doctor_name}</span></div>
                        <div class="detail-item"><strong>Office:</strong> <span>${data.office_name}</span></div>
                    </div>
                </div>
                <div class="modal-detail-section">
                    <h4 class="section-title"><i class="fa-solid fa-timeline"></i> Lab Timeline</h4>
                    <div class="detail-grid">
                        <div class="detail-item"><strong>Date Sent:</strong> <span>${App.utils.formatDate(data.date_sent)}</span></div>
                        <div class="detail-item"><strong>Date Received:</strong> <span>${data.date_received || 'Waiting...'}</span></div>
                        <div class="detail-item"><strong>Patient Appt:</strong> <span>${data.date_scheduled || 'Not Booked'}</span></div>
                        <div class="detail-item"><strong>Current Status:</strong> <span class="badge">${data.status}</span> <span class="badge">${data.status}</span></div>
                    </div>
                </div>
                <div class="modal-detail-section no-border">
                    <h4 class="section-title"><i class="fa-solid fa-note-sticky"></i> Internal Notes</h4>
                    <div class="notes-box">${data.notes || 'No internal notes found for this lab case.'}</div>
                </div>
            `;
            
            $('#view-details-body').html(infoHtml);
            App.modal.open('view-modal');
        }
    });
}

/**
 * Updates Modal Progress Bar
 */
function updateProgressBar(status) {
    const stages = ['Sent', 'Received', 'Scheduled', 'Done'];
    const currentIdx = stages.indexOf(status);
    
    let html = '';
    stages.forEach((label, idx) => {
        let stateClass = '';
        if (idx < currentIdx) stateClass = 'completed';
        else if (idx === currentIdx) stateClass = 'active';
        
        // Ensure "Done" shows as completed when active
        if (status === 'Done') stateClass = 'completed';

        html += `
            <div class="step ${stateClass}">
                <div class="step-icon">${idx + 1}</div>
                <div class="step-label">${label}</div>
            </div>`;
    });
    $('#modal-progress-bar').html(html);
}

/**
 * Print Report Functionality
 */
function printLabPipeline() {
    const tableClone = $('#lab-monitor-table').clone();
    tableClone.find('th:last-child, td:last-child').remove(); // Remove Action column

    const printWin = window.open('', '_blank');
    printWin.document.write(`
        <html>
        <head>
            <title>Lab Pipeline Report</title>
            <style>
                body { font-family: sans-serif; padding: 20px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 10px; text-align: left; font-size: 12px; }
                th { background: #f4f4f4; }
                .stage-success { background-color: #6ef787 !important; }
                .stage-pending { background-color: #eddc87 !important; }
                h1 { color: #2c3e50; }
            </style>
        </head>
        <body>
            <h1>Lab Case Pipeline Report</h1>
            <p>Generated on: ${new Date().toLocaleString()}</p>
            ${tableClone[0].outerHTML}
        </body>
        </html>
    `);
    printWin.document.close();
    setTimeout(() => {
        printWin.print();
        printWin.close();
    }, 500);
}