/**
 * assets/js/admin-order.js
 * CRUD and Advanced Filter logic for the Admin Order management dashboard.
 * This file handles cross-office order tracking, filtering, and operations.
 */

$(document).ready(function () {

    /* ── State ── */
    var currentPage = 1;
    var perPage = 20;
    var editingId = null;   // null = adding new, number = editing
    var selected_items = [];

    /* ================================================================
        LOAD & FILTER TABLE
    ================================================================ */
    function loadAdminOrders(page) {
        page = page || 1;
        currentPage = page;

        // Gather advanced filter variables from your search/filter UI elements
        var searchVal = $('#search-order').val();
        var officeId = $('#filter-office').val();
        var startDate = $('#filter-start-date').val();
        var endDate = $('#filter-end-date').val();

        App.ajax({
            url: '/admin-order/list.php',
            method: 'GET',
            loader: false,
            data: {
                page: page,
                limit: perPage,
                search: searchVal,          // Text search (Order ID, client name, etc.)
                office_id: officeId,        // Office filter dropdown
                start_date: startDate,      // Date range start
                end_date: endDate           // Date range end
            },
            onSuccess: function (data, msg, res) {
                renderTable(data);
                renderPagination(res.meta || {});
            },
            onError: function () {
                $('#admin-order-tbody').html(
                    '<tr><td colspan="10"><div class="table-empty"><i class="fa-solid fa-circle-exclamation"></i> Failed to load admin orders.</div></td></tr>'
                );
            }
        });
    }

    function renderTable(orders) {
        if (!orders || !orders.length) {
            $('#admin-order-tbody').html(
                '<tr><td colspan="10"><div class="table-empty"><i class="fa-solid fa-bag-shopping"></i> No orders found matching the filter criteria.</div></td></tr>'
            );
            return;
        }

        var rows = '';
        $.each(orders, function (i, o) {
            rows += '<tr>' +
                '<td><strong>#' + (i + 1) + '</strong></td>' +
                '<td> ORD-' + App.utils.escHtml(o.id) + '</td>' +
                '<td><span class="badge badge-office">' + App.utils.escHtml(o.office_name || 'Main Office') + '</span></td>' +
                '<td>' + App.utils.escHtml(o.order_date) + '</td>' +
                '<td>' + App.utils.escHtml(o.expected_received_date || '—') + '</td>' +
                '<td>' + App.utils.escHtml(o.creator_name || '—') + '</td>' +
                '<td>' + App.utils.escHtml(o.approver_name || '—') + '</td>' +
                '<td>$' + parseFloat(o.total_amount || 0).toFixed(2) + '</td>' +
                '<td><span class="status-label status-' + String(o.status || 'pending').toLowerCase() + '">' + App.utils.escHtml(o.status) + '</span></td>' +
                '<td>' +
                '<div class="actions">' +
                '<button class="btn btn-ghost btn-sm btn-view" data-id="' + o.id + '" title="View Details"><i class="fa-solid fa-eye"></i></button>' +
                '<button class="btn btn-ghost btn-sm btn-edit" data-id="' + o.id + '" title="Edit Order"><i class="fa-solid fa-pen"></i></button>' +
                '<button class="btn btn-ghost btn-sm btn-delete" data-id="' + o.id + '" data-code="ORD-' + o.id + '" title="Delete Order"><i class="fa-solid fa-trash"></i></button>' +
                '</div>' +
                '</td>' +
                '</tr>';
        });

        $('#admin-order-tbody').html(rows);
    }

    function renderPagination(meta) {
        var total = meta.total || 0;
        var pages = meta.pages || 1;
        var current = meta.current || 1;
        var from = total ? ((current - 1) * perPage + 1) : 0;
        var to = Math.min(current * perPage, total);

        $('#admin-order-info').text('Showing ' + from + '–' + to + ' of ' + total + ' orders');

        var btns = '';
        btns += '<button class="page-btn" id="pg-prev" ' + (current <= 1 ? 'disabled' : '') + '><i class="fa-solid fa-chevron-left"></i></button>';

        var start = Math.max(1, current - 2);
        var end = Math.min(pages, start + 4);
        for (var i = start; i <= end; i++) {
            btns += '<button class="page-btn ' + (i === current ? 'active' : '') + '" data-page="' + i + '">' + i + '</button>';
        }

        btns += '<button class="page-btn" id="pg-next" ' + (current >= pages ? 'disabled' : '') + '><i class="fa-solid fa-chevron-right"></i></button>';
        $('#admin-pagination-btns').html(btns);
    }

    /* ── Search & Filter Event Triggers ── */
    $('#btn-apply-filters').on('click', function (e) {
        e.preventDefault();
        loadAdminOrders(1);
    });

    $('#btn-reset-filters').on('click', function (e) {
        e.preventDefault();
        $('#search-order').val('');
        $('#filter-office').val('null').trigger('change');
        $('#filter-start-date').val('');
        $('#filter-end-date').val('');
        loadAdminOrders(1);
    });

    /* Pagination Events */
    $(document).on('click', '.page-btn[data-page]', function () {
        loadAdminOrders(parseInt($(this).data('page')));
    });
    $(document).on('click', '#pg-prev', function () { if (currentPage > 1) loadAdminOrders(currentPage - 1); });
    $(document).on('click', '#pg-next', function () { loadAdminOrders(currentPage + 1); });


    /* ================================================================
        ADD NEW ORDER
    ================================================================ */
    $('#btn-add-order').on('click', function () {
        editingId = null;
        resetForm();
        $('#order-modal-title').text('Create Admin Order');
        renderOrderTable();
        App.modal.open('order-modal');
    });


    /* ================================================================
        SAVE ORDER (Create or Update)
    ================================================================ */
    $('#btn-save-order').on('click', function () {
        var form = $('#order-form');

        App.form.clearErrors(form);
        if (!App.form.validate(form)) {
            App.toast.warning('Validation Error', 'Please complete all required fields.');
            return;
        }

        if (!selected_items || selected_items.length === 0) {
            App.toast.warning('Empty Order', 'Please append at least one item to this transaction.');
            return;
        }

        var data = App.form.toObject(form);
        var isEditing = !!editingId;
        var url = isEditing ? '/admin-order/update.php' : '/admin-order/create.php';

        if (isEditing) data.order_id = editingId;
        data.items = selected_items;

        App.ajax({
            url: url,
            method: 'POST',
            data: data,
            btn: $('#btn-save-order'),
            loaderMsg: isEditing ? 'Processing adjustments…' : 'Submitting administrative order…',
            onSuccess: function (d, msg) {
                App.modal.close('order-modal');
                App.toast.success('Execution Complete', msg);
                loadAdminOrders(currentPage);
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
            onSuccess: function (res) {
                // Access the inner 'data' object wrapper from your API payload
                var o = res.data || res;

                // Dynamic office lookup fallback from the form options array since office_name isn't in get.php payload
                var officeText = 'Unassigned';
                if (o.office_id) {
                    var match = $('[name="office_id"] option[value="' + o.office_id + '"]').text();
                    if (match) officeText = match;
                }

                var html =
                    '<div>' +
                    '<div class="form-section-title mb-4"><i class="fa-solid fa-shield-halved"></i> Master Order Context</div>' +
                    infoRow('Order Tracking ID', 'ORD-' + (o.id || '—')) +
                    infoRow('Office', officeText) +
                    infoRow('Order Date', o.order_date || '—') +
                    infoRow('Expecting Receiving Date', o.expected_received_date || '—') +
                    infoRow('Total', '$' + parseFloat(o.total_amount || 0).toFixed(2)) +
                    infoRow('Order By', o.creator_name || '—') +
                    infoRow('Approving By', o.approver_name || 'Pending') +
                    infoRow('State', o.status || '—') +
                    '</div>';

                var rows = "";
                if (o.items && o.items.length) {
                    $.each(o.items, function (index, item) {
                        var quantity = parseInt(item.qty || item.quantity || 0);
                        var priceVal = parseFloat(item.price || 0);
                        var sub = priceVal * quantity;
                        var targetItemId = item.id || item.item_id;

                        rows += '<tr>' +
                            '<td>' +
                            '<a href="javascript:void(0);" class="btn-view-item-detail" data-item-id="' + targetItemId + '" style="text-decoration: none; color: inherit; display: block;">' +
                            '<strong>' + App.utils.escHtml(item.name) + '</strong>' +
                            '<small style="display:block;color:dimgray;margin-top:2px;">' + App.utils.escHtml(item.item_code || '-') + '</small>' +
                            '</a>' +
                            '</td>' +
                            '<td>' + quantity + '</td>' +
                            '<td>$' + priceVal.toFixed(2) + '</td>' +
                            '<td>$' + sub.toFixed(2) + '</td>' +
                            '</tr>';
                    });
                } else {
                    rows = '<tr><td colspan="4" class="text-center">No structural line items defined inside this order.</td></tr>';
                }

                // ALIGNED: Placed content into your exact HTML selector anchors
                $('#view-order-details-pane').html(html);
                $('#view-order-items-tbody').html(rows);
                $('#view-order-grand-total').html('$' + parseFloat(o.total_amount || 0).toFixed(2));

                $('#btn-edit-from-view').data('id', id);
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

    $('#btn-edit-from-view').on('click', function () {
        App.modal.close('view-order-modal');
        openEditModal($(this).data('id'));
    });


    /* ================================================================
        EDIT ORDER
    ================================================================ */
    $(document).on('click', '.btn-edit', function () {
        openEditModal($(this).data('id'));
    });

    function openEditModal(id) {
        App.ajax({
            url: '/emp-order/get.php?id=' + id,
            loader: false,
            onSuccess: function (o) {
                resetForm();
                editingId = o.id;
                $('#order-modal-title').text('Modify Order System Parameters (#' + o.id + ')');

                // Populate form payload maps
                $('#order-id').val(o.id);
                $('[name="office_id"]').val(o.office_id).trigger('change'); // Set the office source
                $('[name="o_date"]').val(o.order_date);
                $('[name="r_date"]').val(o.expected_received_date);
                $('[name="status"]').val(o.status); // Admins can manually toggle statuses

                selected_items = o.items || [];
                renderOrderTable();
                App.modal.open('order-modal');
            }
        });
    }


    /* ================================================================
        DELETE ORDER
    ================================================================ */
    $(document).on('click', '.btn-delete', function () {
        var id = $(this).data('id');
        var orderCode = $(this).data('code');

        App.utils.confirm(
            'Are you strictly certain you wish to purge transaction record "' + orderCode + '"? This will erase internal dependencies.',
            function () {
                App.ajax({
                    url: '/admin-order/delete.php',
                    method: 'POST',
                    data: { id: id },
                    loaderMsg: 'Deleteing  Order…',
                    onSuccess: function (d, msg) {
                        App.toast.success('Record Dropped', msg);
                        loadAdminOrders(currentPage);
                    }
                });
            }
        );
    });


    /* ================================================================
        ORDER ELEMENT MATRIX INTERACTION (Sub-items management)
    ================================================================ */
    // FIXED: Updated target element selector to match ID 'btn-append-item-row' in your HTML structure
    $('#btn-append-item-row').on('click', function (e) {
        e.preventDefault();

        // FIXED: Targeted correct HTML input field IDs: 'modal-item-picker' and 'modal-item-qty'
        var $itemSelect = $('#modal-item-picker');
        var $qtyInput = $('#modal-item-qty');
        var selectedOption = $itemSelect.find(':selected');

        var itemId = $itemSelect.val();
        var itemName = selectedOption.data('name');
        var price = selectedOption.data('price');
        var quantity = $qtyInput.val();

        if (!itemId || !quantity || quantity <= 0) {
            App.toast.warning('Input Exception', 'Ensure you select a valid database unit and positive count matrix.');
            return;
        }

        var itemObj = {
            id: itemId,
            name: itemName,
            price: price,
            qty: quantity
        };

        addItem(itemObj);

        $itemSelect.val('').trigger('change');
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
        var idToRemove = $(this).data('id');
        removeItem(idToRemove);
    });

    function removeItem(itemId) {
        selected_items = selected_items.filter(item => item.id != itemId);
        if (selected_items.length === 0) {
            selected_items = null;
        }
        renderOrderTable();
    }

    function renderOrderTable() {
        var $tbody = $('#manifest-items-tbody');
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
        SEED DICTIONARY PRELOADS
    ================================================================ */
    function getAdminLookupData() {
        // Fetch valid inventory items dropdown
        App.ajax({
            url: '/admin-order/item-list.php',
            loader: false,
            onSuccess: function (p) {
                var rows = '<option value="" disabled selected>Select standard item profile...</option>';
                $.each(p, function (i, item) {
                    rows += '<option value="' + item.id + '" data-name="' + item.name + '" data-price="' + item.price + '" >' + item.name + ' ($' + item.price + ')</option>';
                });
                $("#modal-item-picker").html(rows);
            }
        });

        // Prepopulate office filters inside admin UI if an endpoint exists
        App.ajax({
            url: '/offices/list.php',
            loader: false,
            onSuccess: function (offices) {
                var filterOptions = '<option value="" selected>All Offices</option>';
                var formOptions = '<option value="null" disabled selected>Select Target Office Source</option>';

                $.each(offices, function (i, off) {
                    var chunk = '<option value="' + off.id + '">' + off.office_name + '</option>';
                    filterOptions += chunk;
                    formOptions += chunk;
                });

                $("#filter-office").html(filterOptions);
                $("[name='office_id']").html(formOptions);
            }
        });
    }

    /* ================================================================
        HELPERS
    ================================================================ */
    function resetForm() {
        App.form.reset(document.getElementById('order-form'));
        editingId = null;
    }

    function infoRow(label, value) {
        return '<div class="flex-between mb-4" style="border-bottom:1px solid var(--color-border);padding-bottom:var(--sp-3)">' +
            '<span class="text-sm text-muted">' + label + '</span>' +
            '<span class="text-sm fw-500">' + App.utils.escHtml(String(value)) + '</span>' +
            '</div>';
    }


    /* ================================================================
        INIT RUNTIME ENTRY
    ================================================================ */
    loadAdminOrders(1);
    getAdminLookupData();

});