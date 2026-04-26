/**
 * assets/js/staff.js
 * CRUD logic for the Staff page.
 * This file is the template/pattern to copy when building any new module.
 */

$(document).ready(function () {

    /* ── State ── */
    var currentPage = 1;
    var perPage = 20;
    var editingId = null;   // null = adding new, number = editing

    /* ================================================================
       LOAD TABLE
    ================================================================ */
    function loadBudget(page) {
        page = page || 1;
        currentPage = page;

        App.ajax({
            url: '/budget/list.php',
            method: 'GET',
            loader: false,
            data: {
                page: page,
                limit: perPage,
            },
            onSuccess: function (data, msg, res) {
                renderTable(data);
                renderPagination(res.meta || {});
            },
            onError: function () {
                $('#patients-tbody').html(
                    '<tr><td colspan="8"><div class="table-empty"><i class="fa-solid fa-circle-exclamation"></i> Failed to load patients.</div></td></tr>'
                );
            }
        });
    }

    function renderTable(patients) {
        var $tbody = $('#budget-tbody');
        if (!patients || !patients.length) {
            $tbody.html('<tr><td colspan="8"><div class="table-empty"><i class="fa-solid fa-folder-open"></i> No budget records found.</div></td></tr>');
            return;
        }

        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        var rows = '';

        patients.forEach(function (p, i) {
            var expense = p.total_spent.toLocaleString(undefined, { minimumFractionDigits: 2 });
            var budget = p.budget_amount.toLocaleString(undefined, { minimumFractionDigits: 2 });
            var percentage = p.percentage;
            var statusClass = p.status_class;

            // Create a readable month string (e.g., "April 2026")
            var displayDate = new Date(p.budget_year, p.budget_month - 1).toLocaleString('default', { month: 'long', year: 'numeric' });

            rows += '<tr>' +
                '<td><span class="row-index">#' + (i + 1) + '</span></td>' +
                '<td>' +
                '<div class="office-info">' +
                '<span class="office-name fw-bold">' + App.utils.escHtml(p.office_name) + '</span>' +
                '</div>' +
                '</td>' +
                '<td><span class="badge-date">' + displayDate + '</span></td>' +
                '<td width="250">' +
                '<div class="budget-progress-container">' +
                '<div class="d-flex justify-content-between mb-1" style="font-size: 0.85rem;">' +
                '<span><b class="amt-spent">$' + expense + '</b></span>' +
                '<span class="amt-total text-muted">/ $' + budget + '</span>' +
                '</div>' +
                '<div class="progress-track" style="background: #eee; height: 8px; border-radius: 4px; overflow: hidden;">' +
                '<div class="progress-fill ' + statusClass + '" style="width: ' + Math.min(percentage, 100) + '%; height: 100%; transition: width 0.4s;"></div>' +
                '</div>' +
                '<div class="percentage-label mt-1 ' + (percentage > 100 ? 'text-danger fw-bold' : 'text-muted') + '" style="font-size: 11px;">' +
                percentage + '% Utilized' +
                '</div>' +
                '</div>' +
                '</td>' +
                '<td>' +
                '<div class="actions">' +
                '<button class="btn btn-ghost btn-sm btn-view" data-id="' + p.id + '"><i class="fa-solid fa-eye"></i></button>' +
                '<button class="btn btn-ghost btn-sm btn-edit" data-id="' + p.id + '"><i class="fa-solid fa-pen"></i></button>' +
                '<button class="btn btn-ghost btn-sm btn-delete" data-id="' + p.id + '" data-name="' + p.budget_month + '" style="color:var(--color-danger)"><i class="fa-solid fa-trash"></i></button>' +
                '</div>' +
                '</td>' +
                '</tr>';
        });

        $tbody.html(rows);
    }

    function renderPagination(meta) {
        var total = meta.total || 0;
        var pages = meta.pages || 1;
        var current = meta.current || 1;
        var from = total ? ((current - 1) * perPage + 1) : 0;
        var to = Math.min(current * perPage, total);

        $('#patients-info').text('Showing ' + from + '–' + to + ' of ' + total + ' patients');

        var btns = '';
        btns += '<button class="page-btn" id="pg-prev" ' + (current <= 1 ? 'disabled' : '') + '><i class="fa-solid fa-chevron-left"></i></button>';

        // Show max 5 page buttons
        var start = Math.max(1, current - 2);
        var end = Math.min(pages, start + 4);
        for (var i = start; i <= end; i++) {
            btns += '<button class="page-btn ' + (i === current ? 'active' : '') + '" data-page="' + i + '">' + i + '</button>';
        }

        btns += '<button class="page-btn" id="pg-next" ' + (current >= pages ? 'disabled' : '') + '><i class="fa-solid fa-chevron-right"></i></button>';
        $('#pagination-btns').html(btns);
    }


    /* Pagination */
    $(document).on('click', '.page-btn[data-page]', function () {
        loadPatients(parseInt($(this).data('page')));
    });
    $(document).on('click', '#pg-prev', function () { if (currentPage > 1) loadPatients(currentPage - 1); });
    $(document).on('click', '#pg-next', function () { loadPatients(currentPage + 1); });

    /* ================================================================
       ADD NEW PATIENT
    ================================================================ */
    $('#btn-add-budget').on('click', function () {
        editingId = null;
        resetForm();
        $('#budget-modal-title').text('Add New Budget');
        App.modal.open('budget-modal');
    });

    /* ================================================================
       SAVE PATIENT (create or update)
    ================================================================ */
    $('#btn-save-budget').on('click', function () {
        var form = $('#budget-form');

        // Front-end validation
        App.form.clearErrors(form);
        if (!App.form.validate(form)) {
            App.toast.warning('Validation', 'Please fill in all required fields.');
            return;
        }

        var data = App.form.toObject(form);
        var isEditing = !!editingId;
        var url = isEditing
            ? '/budget/update.php'
            : '/budget/create.php';



        if (isEditing) data.id = editingId;

        App.ajax({
            url: url,
            method: 'POST',
            data: data,
            btn: $('#btn-save-budget'),
            loaderMsg: isEditing ? 'Saving changes…' : 'Creating Budget…',
            onSuccess: function (d, msg) {
                App.modal.close('budget-modal');
                App.toast.success('Success', msg);
                loadBudget(currentPage);
            }
        });
    });

    /* ================================================================
       VIEW PATIENT
    ================================================================ */
    $(document).on('click', '.btn-view', function () {
        var id = $(this).data('id');

        App.ajax({
            url: '/budget/budget-data.php?id=' + id,
            loader: false,
            onSuccess: function (data) {
                var b = data.budget; // Budget object
                var orders = data.orders; // Array of orders

                // 1. Calculate Summary Stats for Header
                var totalSpent = orders.reduce((sum, ord) => sum + parseFloat(ord.total_amount || 0), 0);
                var budgetAmt = parseFloat(b.budget_amount || 0);
                var percentage = budgetAmt > 0 ? Math.round((totalSpent / budgetAmt) * 100) : 0;
                var monthName = new Date(b.budget_year, b.budget_month - 1).toLocaleString('default', { month: 'long' });

                var html =
                    // --- SECTION 1: BUDGET HEADER ---
                    '<div class="grid-2 mb-6" style="gap:var(--sp-8); background: var(--bg-surface-2); padding: 20px; border-radius: 8px;">' +
                    '<div>' +
                    '<div class="form-section-title mb-4"><i class="fa-solid fa-building"></i> Office Context</div>' +
                    infoRow('Office', b.office_name) +
                    infoRow('Budget Period', monthName + ' ' + b.budget_year) +
                    '</div>' +
                    '<div>' +
                    '<div class="form-section-title mb-4"><i class="fa-solid fa-chart-line"></i> Utilization</div>' +
                    '<div class="d-flex justify-content-between mb-1">' +
                    '<span><b>$' + totalSpent.toLocaleString() + '</b> <small class="text-muted">spent</small></span>' +
                    '<span>$' + budgetAmt.toLocaleString() + '</span>' +
                    '</div>' +
                    '<div class="progress-track" style="background:#ddd; height:10px; border-radius:5px; overflow:hidden;">' +
                    '<div class="progress-fill" style="width:' + Math.min(percentage, 100) + '%; height:100%; background:var(--color-primary);"></div>' +
                    '</div>' +
                    '<div class="text-sm mt-2">' + percentage + '% of monthly budget used</div>' +
                    '</div>' +
                    '</div>' +

                    // --- SECTION 2: ORDERS TABLE ---
                    '<div class="form-section-title mb-3"><i class="fa-solid fa-list-check"></i> Orders Breakdown</div>' +
                    '<div class="table-wrapper">' +
                    '<table class="data-table">' +
                    '<thead>' +
                    '<tr>' +
                    '<th>Order ID</th>' +
                    '<th>Date</th>' +
                    '<th>Created By</th>' +
                    '<th>Status</th>' +
                    '<th class="text-right">Amount</th>' +
                    '</tr>' +
                    '</thead>' +
                    '<tbody>';

                if (orders.length === 0) {
                    html += '<tr><td colspan="5" class="text-center text-muted">No orders found for this period.</td></tr>';
                } else {
                    orders.forEach(function (ord) {
                        var statusBadge = '';
                        // Match badge color to status
                        if (ord.status === 'approved') statusBadge = '<span class="badge bg-success">Approved</span>';
                        else if (ord.status === 'pending') statusBadge = '<span class="badge bg-warning">Pending</span>';
                        else if (ord.status === 'rejected') statusBadge = '<span class="badge bg-danger">Rejected</span>';

                        html += '<tr>' +
                            '<td>#' + ord.id + '</td>' +
                            '<td>' + ord.order_date + '</td>' +
                            '<td>' + App.utils.escHtml(ord.creator_name) + '</td>' +
                            '<td>' + statusBadge + '</td>' +
                            '<td class="text-right"><b>$' + parseFloat(ord.total_amount || 0).toLocaleString() + '</b></td>' +
                            '</tr>';
                    });
                }

                html += '</tbody></table></div>';

                // Inject and Open
                $('#view-patient-body').html(html);
                $('#btn-edit-from-view').data('id', b.id);
                App.modal.open('view-patient-modal');
            }
        });
    });

    function infoRow(label, value) {
        return '<div class="flex-between mb-4" style="border-bottom:1px solid var(--color-border);padding-bottom:var(--sp-3)">' +
            '<span class="text-sm text-muted">' + label + '</span>' +
            '<span class="text-sm fw-500">' + App.utils.escHtml(String(value)) + '</span>' +
            '</div>';
    }

    function ucFirst(str) { return str ? str.charAt(0).toUpperCase() + str.slice(1) : ''; }

    $('#btn-edit-from-view').on('click', function () {
        App.modal.close('view-patient-modal');
        openEditModal($(this).data('id'));
    });

    /* ================================================================
       EDIT PATIENT
    ================================================================ */
    $(document).on('click', '.btn-edit', function () {
        openEditModal($(this).data('id'));
    });

    function openEditModal(id) {
        App.ajax({
            url: '/budget/get.php?id=' + id,
            loader: false,
            onSuccess: function (p) {
                resetForm();
                editingId = p.id;
                $('#patient-modal-title').text('Edit Staff');

                // Populate form
                $('#budget-id').val(p.id);
                $('[name="office"]').val(p.office_id);
                $('[name="budget_year"]').val(p.budget_year);
                $('[name="budget_month"]').val(String(p.budget_month).padStart(2, '0'));
                $('[name="budget_amount"]').val(p.budget_amount);
                App.modal.open('budget-modal');
            }
        });
    }

    /* ================================================================
       DELETE PATIENT
    ================================================================ */
    $(document).on('click', '.btn-delete', function () {
        var id = $(this).data('id');
        var name = $(this).data('name');

        App.utils.confirm(
            'Are you sure you want to delete "' + name + '"? This cannot be undone.',
            function () {
                App.ajax({
                    url: '/staff/delete.php',
                    method: 'POST',
                    data: { id: id },
                    loaderMsg: 'Deleting staff…',
                    onSuccess: function (d, msg) {
                        App.toast.success('Deleted', msg);
                        loadBudget(currentPage);
                    }
                });
            }
        );
    });


    function getOffices() {
        App.ajax({
            url: '/offices/list.php',
            data: {
                page: 1,
                limit: 200,
            },
            method: 'GET',
            loader: false,
            onSuccess: function (p) {
                var rows = '<option value="" disabled selected> Select Office</option>';
                $.each(p, function (i, p) {
                    rows += '<option value=' + p.id + '>' + p.office_name + '</option>';
                });

                $("#office").html(rows)
            }
        });
    }

    /* ================================================================
       HELPERS
    ================================================================ */
    function resetForm() {
        App.form.reset(document.getElementById('budget-form'));
        editingId = null;
    }

    $(document).on('click', '#btn-clear-filters', function () {
        $("#month").val("");
        $("#year").val("");
    });

    $(document).on('click', '#search-button', function () {
        var month = $("#month").val();
        var year = $("#year").val();
        var page = 1;

        App.ajax({
            url: '/budget/list.php',
            method: 'GET',
            loader: false,
            data: {
                page: page,
                limit: perPage,
                month: month,
                year: year
            },
            onSuccess: function (data, msg, res) {
                $("#month").val("");
                $("#year").val("");
                renderTable(data);
                renderPagination(res.meta || {});
            },
            onError: function () {
                $('#patients-tbody').html(
                    '<tr><td colspan="8"><div class="table-empty"><i class="fa-solid fa-circle-exclamation"></i> Failed to load budgets</div></td></tr>'
                );
            }
        });
    });

    /* ================================================================
       INIT — load on page ready
    ================================================================ */
    loadBudget(1);
    getOffices()

});
