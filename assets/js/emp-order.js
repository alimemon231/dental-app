/**
 * assets/js/order.js
 * CRUD logic for the Order page.
 * This file is the template/pattern to copy when building any new module.
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
                '<button class="btn btn-ghost btn-sm btn-edit" data-id="' + p.id + '" title="Edit"><i class="fa-solid fa-pen"></i></button>' +
                '<button class="btn btn-ghost btn-sm btn-delete" data-id="' + p.id + '" data-name="ORD-' + App.utils.escHtml(p.id) + '" title="Delete" style="color:var(--color-danger)"><i class="fa-solid fa-trash"></i></button>' +
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
       ADD NEW PATIENT
    ================================================================ */
    $('#btn-add-order').on('click', function () {
        editingId = null;
        resetForm();
        $('#order-modal-title').text('Add New Order');
        renderOrderTable()
        App.modal.open('order-modal');
    });

    /* ================================================================
       SAVE PATIENT (create or update)
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

        if (data.password != data.re_password) {
            App.toast.warning('Validation', 'Passwords Not Match .');
            return;
        }

        if (isEditing) data.doctor_id = editingId;

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
       EDIT PATIENT
    ================================================================ */
    $(document).on('click', '.btn-edit', function () {
        resetForm();
        openEditModal($(this).data('id'));
    });

    function openEditModal(id) {
        App.ajax({
            url: '/emp-order/get.php?id=' + id,
            loader: false,
            onSuccess: function (p) {
                resetForm();
                editingId = p.id;
                $('#patient-modal-title').text('Edit Order');

                // Populate form
                $('#order-id').val(p.id);
                $('[name="o_date"]').val(p.order_date);
                $('[name="r_date"]').val(p.expected_received_date);
                selected_items  = p.items ;
                renderOrderTable()
                App.modal.open('order-modal');
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
                    url: '/emp-order/delete.php',
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


    function getitems() {
        App.ajax({
            url: '/emp-order/item-list.php',
            loader: false,
            onSuccess: function (p) {
                var rows = '<option value="null" disabled selected> Select Item</option>';
                $.each(p, function (i, p) {
                    rows += '<option value=' + p.id + ' data-name=' + p.name + ' data-price=' + p.price + ' >' + p.name + '</option>';
                });

                $("#order-items").html(rows)
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

    $('#btn-add-item').on('click', function (e) {
        e.preventDefault();

        // 1. Get references to your fields
        var $itemSelect = $('#order-items');
        var $qtyInput = $('#qty');

        // 2. Get the selected <option> element specifically
        var selectedOption = $itemSelect.find(':selected');

        // 3. Extract the data
        var itemId = $itemSelect.val();
        var itemName = selectedOption.data('name');
        var price = selectedOption.data('price');
        var quantity = $qtyInput.val();

        itemObj = {
            name: itemName,
            id: itemId,
            price: price,
            qty: quantity
        };

        // 4. Validation: Check if item is selected and qty is valid
        if (!itemId || !quantity || quantity <= 0) {
            App.toast.warning('Input Error', 'Please select an item and enter a valid quantity.');
            return;
        }

        addItem(itemObj)



        // 8. Reset inputs for the next item
        $itemSelect.val(null).trigger('change'); // Clears Select2
        $qtyInput.val('');                       // Clears Quantity
    });

    /**
 * Adds an item object to the list. 
 * If exists, combines quantity.
 */
    function addItem(itemObj) {
        // 1. If the list was null, reset to array
        if (selected_items === null) selected_items = [];

        // 2. Check if item already exists
        var existing = selected_items.find(item => item.id == itemObj.id);

        if (existing) {
            existing.qty = parseInt(existing.qty) + parseInt(itemObj.qty);
        } else {
            selected_items.push(itemObj);
        }

        renderOrderTable();
    }

    // This listens for clicks on buttons with class 'btn-remove-item' 
    // even if they were added to the table AFTER the page loaded.
    $(document).on('click', '.btn-remove-item', function () {
        var idToRemove = $(this).data('id');
        removeItem(idToRemove);
    });

    /**
     * Removes an item by ID. 
     * If empty, sets list to null.
     */
    function removeItem(itemId) {
        // Filter out the item
        selected_items = selected_items.filter(item => item.id != itemId);

        // If no items left, set to null per your requirement
        if (selected_items.length === 0) {
            selected_items = null;
        }

        renderOrderTable();
    }


    function renderOrderTable() {
        var $tbody = $('#added-items-tbody');
        $tbody.empty(); // Clear current rows

        // If null or empty, show a "No Items" message
        if (!selected_items || selected_items.length === 0) {
            $tbody.append('<tr><td colspan="5" class="text-center">No items added to order.</td></tr>');
            return;
        }

        var grandTotal = 0;
        var count = 1;
        // Loop through items and build rows
        selected_items.forEach(function (item) {
            var subtotal = parseFloat(item.price) * parseInt(item.qty);
            grandTotal += subtotal;

            var row = `
            <tr>
                <td>${count}</td>
                <td>${item.name}</td>
                <td>$${parseFloat(item.price).toFixed(2)}</td>
                <td>${item.qty}</td>
                <td>$${subtotal.toFixed(2)}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger btn-remove-item" data-id="${item.id}">
                        <i class="fa fa-trash"></i> Remove
                    </button>
                </td>
            </tr>
        `;
            $tbody.append(row);
            count++;
        });

        // Optional: Update a Total Display if you have one
        $('#grand-total-display').text('$' + grandTotal.toFixed(2));
    }

    /* ================================================================
       INIT — load on page ready
    ================================================================ */
    loadOrder(1);
    getitems()


});
