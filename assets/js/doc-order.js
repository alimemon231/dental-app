/**
 * assets/js/doc-order.js
 * CRUD logic for the Order page (Doctor View with full Edit & Approval rights).
 */

$(document).ready(function () {

    /* ── State ── */
    var currentPage = 1;
    var perPage = 20;
    var editingId = null;   // null = adding new, number = editing
    var selected_items = [];

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
                $('#order-tbody').html(
                    '<tr><td colspan="8"><div class="table-empty"><i class="fa-solid fa-circle-exclamation"></i> Failed to load orders.</div></td></tr>'
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
            const orderStatus = (p.status || '').toLowerCase();

            // Always keep the view button accessible
            let actionButtonsHtml = '<button class="btn btn-ghost btn-sm btn-view" data-id="' + p.id + '" title="View"><i class="fa-solid fa-eye"></i></button>';

            // Show Edit, Approve and Reject options if the status is NOT approved
            if (orderStatus !== 'approved') {
                actionButtonsHtml +=
                    '<button class="btn btn-ghost btn-sm btn-edit" data-id="' + p.id + '" title="Edit"><i class="fa-solid fa-pen-to-square"></i></button>' +
                    '<button class="btn btn-ghost btn-sm btn-approve" data-id="' + p.id + '" title="Approve"><i class="fa-solid fa-check"></i> Approve</button>' +
                    '<button class="btn btn-ghost btn-sm btn-delete" data-id="' + p.id + '" data-name="ORD-' + App.utils.escHtml(p.id) + '" title="Reject" style="color:var(--color-danger)"><i class="fa-solid fa-trash"></i> Rej</button>';
            }

            rows += '<tr>' +
                '<td><strong>#' + (i + 1) + '</strong></td>' +
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
                '<td>' + App.utils.escHtml(p.approver_name || '—') + '</td>' +
                '<td>$' + parseFloat(p.total_amount).toFixed(2) + '</td>' +
                '<td>' + App.utils.escHtml(p.status) + '</td>' +
                '<td>' +
                '<div class="actions" style="display: flex; gap: 4px;">' +
                actionButtonsHtml +
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

        $('#patients-info').text('Showing ' + from + '–' + to + ' of ' + total + ' orders');

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

    /* Pagination Event Listeners */
    $(document).on('click', '.page-btn[data-page]', function () {
        loadOrder(parseInt($(this).data('page')));
    });
    $(document).on('click', '#pg-prev', function () { if (currentPage > 1) loadOrder(currentPage - 1); });
    $(document).on('click', '#pg-next', function () { loadOrder(currentPage + 1); });

    /* ================================================================
       SAVE ORDER (create or update)
    ================================================================ */
    $('#btn-save-order').on('click', function () {
        var form = $('#order-form');

        // Front-end validation
        App.form.clearErrors(form);
        if (!App.form.validate(form)) {
            App.toast.warning('Validation', 'Please fill in all required fields.');
            return;
        }

        if (!selected_items || selected_items.length === 0) {
            App.toast.warning('Empty Order', 'Please add at least one item to the order before saving.');
            return;
        }

        var data = App.form.toObject(form);
        var isEditing = !!editingId;
        var url = isEditing
            ? '/emp-order/update.php'
            : '/emp-order/create.php';

        if (isEditing) data.order_id = editingId; // Map ID assignment payload key explicitly
        data.items = selected_items;

        App.ajax({
            url: url,
            method: 'POST',
            data: data,
            btn: $('#btn-save-order'),
            loaderMsg: isEditing ? 'Saving changes…' : 'Creating Order…',
            onSuccess: function (d, msg) {
                App.modal.close('order-modal');
                App.toast.success('Success', msg);
                loadOrder(currentPage);
                selected_items = [];
            }
        });
    });

    /* ================================================================
       VIEW ORDER DETAILS
    ================================================================ */
    $(document).on('click', '.btn-view', function () {
        var id = $(this).data('id');

        App.ajax({
            url: '/emp-order/get.php?id=' + id,
            loader: false,
            onSuccess: function (p) {
                var html =
                    '<div>' +
                    '<div class="form-section-title mb-4"><i class="fa-solid fa-shopping-bag"></i> Order Info</div>' +
                    infoRow('Order Number', p.id || '—') +
                    infoRow('Order Date', p.order_date || '—') +
                    infoRow('Delivery Date', p.expected_received_date || '—') +
                    infoRow('Total Order Amount', p.total_amount || '—') +
                    infoRow('Created By', p.creator_name || '—') +
                    infoRow('Approved By', p.approver_name || '—') +
                    infoRow('Status', p.status || '—') +
                    '</div>';

                var rows = "";
                $.each(p.items, function (index, item) {
                    rows += '<tr>' +
                        '<td>' +
                        '<a href="javascript:void(0);" class="btn-view-item-detail" data-item-id="' + item.id + '" style="text-decoration: none; color: inherit; display: block;">' +
                        '<strong>' + App.utils.escHtml(item.name) + '</strong>' +
                        '<small style="display:block;color:green;margin-top:2px;">' + App.utils.escHtml(item.item_code || '-') + '</small>' +
                        '</a>' +
                        '</td>' +
                        '<td>' + item.qty + '</td>' +
                        '<td>' + parseFloat(item.price).toFixed(2) + '</td>' +
                        '<td>' + parseFloat(item.subtotal).toFixed(2) + '</td>' +
                        '</tr>';
                });

                $('#view-order-details').html(html);
                $('#order-items-tbody').html(rows);
                $('#order-grand-total-display').html(parseFloat(p.total_amount).toFixed(2));

                // Keep modal button visibility synced to status context rules
                if ((p.status || '').toLowerCase() === 'approved') {
                    $('#btn-edit-from-view').hide();
                } else {
                    $('#btn-edit-from-view').show().data('id', id);
                }

                App.modal.open('view-order-modal');
            }
        });
    });


    /* ================================================================
       VIEW PRODUCT ITEM DETAIL
    ================================================================ */
    $(document).on('click', '.btn-view-item-detail', function (e) {
        e.preventDefault();
        var itemId = $(this).data('item-id');

        App.ajax({
            url: '/items/get.php?id=' + itemId,
            loader: false,
            onSuccess: function (p) {
                var html =
                    '<div class="grid-2" style="gap:var(--sp-8)">' +
                        '<div>' +
                            '<div class="form-section-title mb-4"><i class="fa-solid fa-box"></i> Item Info </div>' +
                            infoRow('Item Name', p.name || '—') +
                            infoRow('Price', p.price || '—') +
                            infoRow('Item Code', p.item_code || '—') +
                            infoRow('Description', p.description || '—') +
                            infoRow('Categories', p.category_names || '—') +
                        '</div>' +
                        '<div>' +
                            '<img src="' + (p.image_path || '/assets/img/placeholder.jpg') + '" class="main-item-image" style="width:100%; border-radius:var(--radius-md); max-height:250px; object-fit:cover;">' +
                        '</div>' +
                    '</div>';

                $('#view-items-body').html(html);
                $('#btn-edit-from-view').data('id', p.id);
                App.modal.open('view-item-modal');
            }
        });
    });

    function infoRow(label, value) {
        return '<div class="flex-between mb-4" style="border-bottom:1px solid var(--color-border);padding-bottom:var(--sp-3)">' +
            '<span class="text-sm text-muted">' + label + '</span>' +
            '<span class="text-sm fw-500">' + App.utils.escHtml(String(value)) + '</span>' +
            '</div>';
    }

    $('#btn-edit-from-view').on('click', function () {
        App.modal.close('view-order-modal');
        openEditModal($(this).data('id'));
    });

    /* ================================================================
       EDIT ORDER FUNCTIONALITIES
    ================================================================ */
    $(document).on('click', '.btn-edit', function () {
        openEditModal($(this).data('id'));
    });

    function openEditModal(id) {
        App.ajax({
            url: '/emp-order/get.php?id=' + id,
            loader: false,
            onSuccess: function (p) {
                resetForm();
                editingId = p.id;
                $('#order-modal-title').text('Edit Order');

                // Populate fields
                $('#order-id').val(p.id);
                $('[name="o_date"]').val(p.order_date);
                $('[name="r_date"]').val(p.expected_received_date);
                selected_items = p.items;

                renderOrderTable();
                App.modal.open('order-modal');
            }
        });
    }

    /* ================================================================
       APPROVE & REJECT ACTIONS
    ================================================================ */
    $(document).on('click', '.btn-approve', function () {
        var id = $(this).data('id');
        App.utils.confirm(
            'Are you sure you want to approve this order?',
            function () {
                App.ajax({
                    url: '/doc-order/approve.php',
                    method: 'POST',
                    data: { id: id },
                    loaderMsg: 'Approving order…',
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
                    loaderMsg: 'Rejecting order…',
                    onSuccess: function (d, msg) {
                        App.toast.success('Rejected', msg);
                        loadOrder(currentPage);
                    }
                });
            }
        );
    });

    /* ================================================================
       DYNAMIC ITEM MANAGEMENT METHODS
    ================================================================ */
    function getitems() {
        App.ajax({
            url: '/emp-order/item-list.php',
            loader: false,
            onSuccess: function (p) {
                var rows = '<option value="null" disabled selected> Select Item</option>';
                $.each(p, function (i, item) {
                    rows += `<option value="${item.id}" data-name="${item.name}" data-price="${item.price}">${item.name}</option>`;
                });
                $("#order-items").html(rows);
            }
        });
    }

    $('#btn-add-item').on('click', function (e) {
        e.preventDefault();

        var $itemSelect = $('#order-items');
        var $qtyInput = $('#qty');
        var selectedOption = $itemSelect.find(':selected');

        var itemId = $itemSelect.val();
        var itemName = selectedOption.data('name');
        var price = selectedOption.data('price');
        var quantity = $qtyInput.val();

        if (!itemId || !quantity || quantity <= 0) {
            App.toast.warning('Input Error', 'Please select an item and enter a valid quantity.');
            return;
        }

        var itemObj = {
            name: itemName,
            id: itemId,
            price: price,
            qty: quantity
        };

        addItem(itemObj);

        // Reset sub fields
        $itemSelect.val(null).trigger('change');
        $qtyInput.val('');
    });

    function addItem(itemObj) {
        if (selected_items === null) selected_items = [];

        var existing = selected_items.find(item => item.id == itemObj.id);
        if (existing) {
            existing.qty = parseInt(existing.qty) + parseInt(itemObj.qty);
        } else {
            selected_items.push(itemObj);
        }

        renderOrderTable();
    }

    $(document).on('click', '.btn-remove-item', function () {
        removeItem($(this).data('id'));
    });

    function removeItem(itemId) {
        selected_items = selected_items.filter(item => item.id != itemId);
        if (selected_items.length === 0) {
            selected_items = null;
        }
        renderOrderTable();
    }

    function renderOrderTable() {
        var $tbody = $('#added-items-tbody');
        $tbody.empty();

        if (!selected_items || selected_items.length === 0) {
            $tbody.append('<tr><td colspan="6" class="text-center">No items added to order.</td></tr>');
            $('#grand-total-display').text('$0.00');
            return;
        }

        var grandTotal = 0;
        var count = 1;

        selected_items.forEach(function (item) {
            var subtotal = parseFloat(item.price) * parseInt(item.qty);
            grandTotal += subtotal;

            var row = `
            <tr>
                <td>${count}</td>
                <td>${item.name}</td>
                <td>$${parseFloat(item.price).toFixed(2)}</td>
                <td>
                    <input type="number" 
                           class="form-control btn-update-qty" 
                           data-id="${item.id}" 
                           value="${item.qty}" 
                           min="1" 
                           style="width: 80px; padding: var(--sp-1) var(--sp-2); text-align: center;" />
                </td>
                <td>$${subtotal.toFixed(2)}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger btn-remove-item" data-id="${item.id}">
                        <i class="fa fa-trash"></i> Remove
                    </button>
                </td>
            </tr>`;
            $tbody.append(row);
            count++;
        });

        $('#grand-total-display').text('$' + grandTotal.toFixed(2));
    }


    /* Inline Quantity Change Listener */
    $(document).on('change keyup', '.btn-update-qty', function () {
        var itemId = $(this).data('id');
        var newQty = parseInt($(this).val());

        // Validate that quantity is a real positive number
        if (isNaN(newQty) || newQty <= 0) {
            newQty = 1;
            $(this).val(1);
        }

        // Find the specific item object and update its state parameters
        if (selected_items && selected_items.length > 0) {
            var item = selected_items.find(item => item.id == itemId);
            if (item) {
                item.qty = newQty;

                // Recalculate totals and line sub-totals dynamically without rebuilding focus away
                var subtotal = parseFloat(item.price) * newQty;
                $(this).closest('tr').find('td:nth-child(5)').text('$' + subtotal.toFixed(2));

                // Update Grand Total calculation container
                var grandTotal = 0;
                selected_items.forEach(function (el) {
                    grandTotal += parseFloat(el.price) * parseInt(el.qty);
                });
                $('#grand-total-display').text('$' + grandTotal.toFixed(2));
            }
        }
    });

    /* ================================================================
       HELPERS
    ================================================================ */
    function resetForm() {
        App.form.reset(document.getElementById('order-form'));
        $('#order-id').val('');
        editingId = null;
        selected_items = [];
    }




    /* ================================================================
       INIT — load on page ready
    ================================================================ */
    loadOrder(1);
    getitems();

});