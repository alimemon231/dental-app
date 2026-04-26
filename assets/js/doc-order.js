/**
 * assets/js/order.js
 * CRUD logic for the Order page.
 * This file is the template/pattern to copy when building any new module.
 */

$(document).ready(function () {

    /* ── State ── */
    var currentPage = 1;
    var perPage = 20;
    
    /* ================================================================
       LOAD TABLE
    ================================================================ */
    function loadOrder(page) {
        page = page || 1;
        currentPage = page;

        App.ajax({
            url: '/emp-order/list.php',
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
        if (!patients || !patients.length) {
            $('#order-tbody').html(
                '<tr><td colspan="8"><div class="table-empty"><i class="fa-solid fa-bag-shopping"></i> No orders found.</div></td></tr>'
            );
            return;
        }

        var rows = '';
        $.each(patients, function (i, p) {
            rows += '<tr>' +
                '<td><strong>#' +( i + 1) + '</strong></td>' +
                '<td> ORD-' + App.utils.escHtml(p.id) + '</td>' +
                '<td>' +
                '<div class="flex flex-align gap-3">' +
                '<div>' +
                App.utils.escHtml(p.order_date) +
                '</div>' +
                '</div>' +
                '</td>' +


                '<td>' + App.utils.escHtml(p.expected_received_date) + '</td>' +
                '<td>' + App.utils.escHtml(p.creator_name) + '</td>' +
                '<td>' + App.utils.escHtml(p.approver_name) + '</td>' +
                '<td>' + App.utils.escHtml(p.total_amount) + '</td>' +
                '<td>' + App.utils.escHtml(p.status) + '</td>' +
                '<td>' +
                '<div class="actions">' +
                '<button class="btn btn-ghost btn-sm btn-view" data-id="' + p.id + '" title="View"><i class="fa-solid fa-eye"></i></button>' +
                '<button class="btn btn-ghost btn-sm btn-approve" data-id="' + p.id + '" title="Edit"><i class="fa-solid fa-check"></i> Approve</button>' +
                '<button class="btn btn-ghost btn-sm btn-delete" data-id="' + p.id + '" data-name="ORD-' + App.utils.escHtml(p.id) + '" title="Delete" style="color:var(--color-danger)"><i class="fa-solid fa-trash"></i>Rej</button>' +
                '</div>' +
                '</td>' +
                '</tr>';
        });

        $('#order-tbody').html(rows);
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
       VIEW PATIENT
    ================================================================ */
    $(document).on('click', '.btn-view', function () {
        var id = $(this).data('id');

        App.ajax({
            url: '/emp-order/get.php?id=' + id,
            loader: false,
            onSuccess: function (p) {

                console.log(p)
                var html =
                    '<div>' +
                    '<div class="form-section-title mb-4"><i class="fa-solid fa-shoping-bag"></i> Order Info</div>' +
                    infoRow('Order Numbber', p.id || '—') +
                    infoRow('Order Date', p.order_date || '—') +
                    infoRow('Delivery Date', p.expected_received_date || '—') +
                    infoRow('Total Order Amount', p.total_amount || '—') +
                    infoRow('Created By', p.creator_name || '—') +
                    infoRow('Approved By', p.approver_name || '—') +
                    infoRow('Status', p.status || '—') +
                    '</div>';

                var rows = "";
                $.each(p.items, function (index, item) {
                    rows += '<tr>'+
                    '<td>' + item.name + '</td>'+
                    '<td>' + item.qty + '</td>'+
                    '<td>' + item.price + '</td>'+
                    '<td>' + item.subtotal + '</td>'+
                    
                    '</tr>';
                });

                $('#view-order-details').html(html);
                $('#order-items-tbody').html(rows);
                $('#order-grand-total-display').html(p.total_amount);
                $('#btn-edit-from-view').data('id', id);
                App.modal.open('view-order-modal');
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
       DELETE PATIENT
    ================================================================ */

    $(document).on('click', '.btn-approve', function () {
        var id = $(this).data('id');
        App.utils.confirm(
            'Are you sure you want to approvve this order.',
            function () {
                App.ajax({
                    url: '/doc-order/approve.php',
                    method: 'POST',
                    data: { id: id },
                    loaderMsg: 'Deleting doctor…',
                    onSuccess: function (d, msg) {
                        App.toast.success('Order Approved', msg);
                        loadOrder(currentPage);
                    }
                });
            }
        );
    });

    $(document).on('click', '.btn-delete', function () {
        var id = $(this).data('id');
        var name = $(this).data('name');

        App.utils.confirm(
            'Are you sure you want to reject "' + name + '"? This cannot be undone.',
            function () {
                App.ajax({
                    url: '/doc-order/reject.php',
                    method: 'POST',
                    data: { id: id },
                    loaderMsg: 'Deleting doctor…',
                    onSuccess: function (d, msg) {
                        App.toast.success('Deleted', msg);
                        loadOrder(currentPage);
                    }
                });
            }
        );
    });


    

    
    /* ================================================================
       INIT — load on page ready
    ================================================================ */
    loadOrder(1);


});
