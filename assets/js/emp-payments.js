/**
 * assets/js/emp-payments-engine.js
 * Core Accounting & Dynamic Payments Ledger Engine
 */

$(document).ready(function () {

    /* ── State Management ── */
    var currentPage = 1;
    var perPage = 20;

    // Unified state management pool for dynamic treatment allocations
    var treatmentsArray = []; 

    /* ================================================================
        PHASE 1A: INITIALIZATION & SELECT2 SETUP
    ================================================================ */

    function initDropdowns() {
        // Initialize Select2 for standard search fields and edit modal targets
        $('#patient-select, #provider-select, #treatment-search-select, #edit_pay_provider_id, #edit-treatment-search-select').select2({
            width: '100%',
            allowClear: true
        });

        // Ensure Select2 fits cleanly into responsive modal containers
        if ($.fn.select2) {
            $('#edit_pay_provider_id').select2({
                dropdownParent: $('#edit-payment-modal'),
                width: '100%',
                allowClear: true
            });
            $('#edit-treatment-search-select').select2({
                dropdownParent: $('#edit-payment-modal'),
                placeholder: "Select medical categorization parameters...",
                width: '100%',
                allowClear: true
            });
        }

        // 1. Fetch Patients
        App.ajax({
            url: '/emp-pre-auth/patients-list.php',
            method: 'GET',
            loader: false,
            onSuccess: function (res) {
                var data = res.data || res;
                var options = '<option value="">-- Search Patient --</option>';
                $.each(data, function (i, item) {
                    options += `<option value="${item.id}">${item.name} (DOB: ${item.dob || 'N/A'})</option>`;
                });
                $('#patient-select').html(options);
                $('#edit_pay_patient_id').html(options)
            }
        });

        // 2. Fetch Providers
        App.ajax({
            url: '/emp-labs/all-doctors.php',
            method: 'GET',
            loader: false,
            onSuccess: function (data) {
                var options = '<option value="">-- Search Provider --</option>';
                $.each(data, function (i, doc) {
                    options += `<option value="${doc.user_id}">Dr. ${doc.name}</option>`;
                });
                $('#provider-select').html(options);
                // Also cache initial setup over to your edit context container
                $('#edit_pay_provider_id').html(options);
            }
        });

        // 3. Fetch Treatments
        App.ajax({
            url: '/emp-pre-auth/load-procedures.php',
            method: 'GET',
            loader: false,
            onSuccess: function (data) {
                var options = '<option value="">-- Search Treatment --</option>';
                $.each(data, function (i, proc) {
                    options += `<option value="${proc.id}" data-name="${App.utils.escHtml(proc.name)}">${proc.name}</option>`;
                });
                $('#treatment-search-select').html(options);
                $('#edit-treatment-search-select').html(options); // Map to edit list selector
            }
        });
    }

    /* ================================================================
        PHASE 1B: DYNAMIC TREATMENT ARRAY LOGIC (UNIFIED ENGINE)
    ================================================================ */

    // Reusable core engine to render pills and compile payload properties
    function renderTreatmentPills(isEditContext) {
        var prefix = isEditContext ? 'edit-' : '';
        var inputPrefix = isEditContext ? 'edit_' : '';
        
        var $container = $(`#${prefix}selected-treatments-container`);
        var $hiddenInput = $(`#${inputPrefix}treatment_ids_payload`);

        $container.empty();

        if (treatmentsArray.length === 0) {
            $container.html(`<span class="text-muted text-sm" id="${prefix}no-treatment-text">No treatments added yet.</span>`);
            $hiddenInput.val('');
            return;
        }

        $.each(treatmentsArray, function (index, treatment) {
            var pillHtml = isEditContext ? `
                <div class="treatment-pill" style="display: inline-flex; align-items: center; margin: 4px; padding: 4px 8px; background: #e2e8f0; border-radius: 4px;">
                    ${treatment.name}
                    <span class="remove-pill-trigger" data-id="${treatment.id}" data-context="edit" style="margin-left: 8px; cursor: pointer; color: #ef4444;">
                        <i class="fa-solid fa-xmark"></i>
                    </span>
                </div>
            ` : `
                <div class="treatment-pill">
                    ${treatment.name}
                    <span class="remove-pill-trigger" data-id="${treatment.id}" data-context="create"><i class="fa-solid fa-xmark"></i></span>
                </div>
            `;
            $container.append(pillHtml);
        });

        // Save layout configuration standard payloads cleanly
        $hiddenInput.val(JSON.stringify(treatmentsArray));
    }

    // Handle Adding Procedures within the Create Payment Context
    $('#btn-add-treatment').on('click', function () {
        var $select = $('#treatment-search-select');
        var selectedId = $select.val();

        if (!selectedId) return;

        var selectedName = $select.find('option:selected').data('name');
        var exists = treatmentsArray.some(t => String(t.id) === String(selectedId));
        if (exists) {
            App.toast.warning('Duplicate', 'This treatment is already added.');
            return;
        }

        treatmentsArray.push({ id: selectedId, name: selectedName });
        renderTreatmentPills(false);
        $select.val(null).trigger('change');
    });

    // Handle Adding Procedures within the Edit Context Modal
    $('#btn-edit-add-treatment').on('click', function (e) {
        e.preventDefault();
        var $select = $('#edit-treatment-search-select');
        var treatmentId = $select.val();
        var treatmentText = $select.find('option:selected').text();

        if (!treatmentId) return;

        var alreadyExists = treatmentsArray.some(item => String(item.id) === String(treatmentId));
        if (!alreadyExists) {
            treatmentsArray.push({ id: treatmentId, name: treatmentText });
            
            // Backwards compatibility sync parameters
            window.editSelectedTreatmentIds = treatmentsArray; 
            if (typeof editSelectedTreatmentIds !== 'undefined') { editSelectedTreatmentIds = treatmentsArray; }

            renderTreatmentPills(true);
            $select.val('').trigger('change');
        } else {
            App.toast.warning('Duplicate', 'This procedure allocation already exists within the ledger stack.');
        }
    });

    // Unified removal delegate matrix handler
    $(document).on('click', '.remove-pill-trigger', function () {
        var idToRemove = String($(this).data('id'));
        var context = $(this).data('context');
        
        treatmentsArray = treatmentsArray.filter(t => String(t.id) !== idToRemove);
        
        // Sync global references safely
        window.editSelectedTreatmentIds = treatmentsArray;
        if (typeof editSelectedTreatmentIds !== 'undefined') { editSelectedTreatmentIds = treatmentsArray; }

        renderTreatmentPills(context === 'edit');
    });


    /* ================================================================
        PHASE 1C: SUBMIT NEW MASTER PAYMENT + FIRST TRANSACTION
    ================================================================ */

    $('#btn-open-payment-modal').on('click', function () {
        App.form.reset(document.getElementById('add-payment-form'));
        $('#patient-select, #provider-select, #treatment-search-select').val(null).trigger('change');
        treatmentsArray = [];
        renderTreatmentPills(false);
        App.modal.open('add-payment-modal');
    });

    $('#btn-save-payment').on('click', function () {
        var form = $('#add-payment-form');

        if (!$('#patient-select').val() || !$('#provider-select').val() || !$('#total_amount').val() || !$('#transaction_amount').val()) {
            App.toast.warning('Validation', 'Please fill all required (*) fields.');
            return;
        }

        if (treatmentsArray.length === 0) {
            App.toast.warning('Validation', 'Please add at least one treatment.');
            return;
        }

        var formData = form.serialize();

        App.ajax({
            url: '/emp-payments/create.php',
            method: 'POST',
            data: formData,
            btn: $('#btn-save-payment'),
            loaderMsg: 'Creating master payment & initial transaction...',
            onSuccess: function (d, msg) {
                App.modal.close('add-payment-modal');
                App.toast.success('Success', msg);
                loadPaymentsTable(1);
            }
        });
    });

    /* ================================================================
        PHASE 2: DATA TABLE GRID VIEWS & AUDIT LOGS
    ================================================================ */

    function loadPaymentsTable(page) {
        page = page || 1;
        currentPage = page;

        App.ajax({
            url: '/emp-payments/list.php',
            method: 'GET',
            loader: false,
            data: { page: page, limit: perPage },
            onSuccess: function (res) {
                const records = res.data || res;
                renderPaymentTable(records);
            },
            onError: function () {
                $('#payments-tbody').html(
                    '<tr><td colspan="7"><div class="table-empty"><i class="fa-solid fa-circle-exclamation"></i> Failed to load payment records.</div></td></tr>'
                );
            }
        });
    }

    function renderPaymentTable(records) {
        let rows = '';

        if (!records || records.length === 0) {
            rows = '<tr><td colspan="7" class="text-center text-muted py-4">No payment records found.</td></tr>';
        } else {
            records.forEach(r => {
                const statusLower = (r.status || '').toLowerCase();
                const rowColorClass = statusLower === 'completed' ? 'table-success' : '';
                const fmt = (val) => '$' + parseFloat(val || 0).toFixed(2);

                let balanceDisplay = '';
                if (r.balance_type === 'Credit') {
                    let displayValue = Math.abs(r.balance_due).toFixed(2);
                    balanceDisplay = `<span class="text-info fw-bold">$${displayValue} (Credit)</span>`;
                } else if (r.balance_type === 'Due') {
                    balanceDisplay = `<span class="text-danger fw-bold">${fmt(r.balance_due)}</span>`;
                } else {
                    balanceDisplay = `<span class="text-muted">0.00</span>`;
                }

                rows += `
                <tr class="${rowColorClass}" style="vertical-align: middle;">
                    <td><span class="badge bg-secondary">#${App.utils.escHtml(r.id)}</span></td>
                    <td>
                        <div class="fw-600">${App.utils.escHtml(r.patient_name)}</div>
                        <small class="text-muted"><i class="fas fa-stethoscope"></i> ${App.utils.escHtml(r.provider_name)}</small>
                    </td>
                    <td>${r.treatment_names || 'N/A'}</td>
                    <td><div class="text-success fw-bold">${fmt(r.total_amount)}</div></td>
                    <td>${fmt(r.total_paid)}</td>
                    <td>${balanceDisplay}</td>
                    <td class="text-center">
                        <div class="row-actions" style="display: flex; gap: 4px; justify-content: center;">
                            <button class="btn btn-ghost btn-sm btn-view-payment" data-id="${r.id}" title="View Details">
                                <i class="fa-solid fa-eye text-primary"></i>
                            </button>
                            <button class="btn btn-ghost btn-sm btn-edit-payment" data-id="${r.id}" title="Edit Master Payment">
                                <i class="fa-solid fa-pen text-secondary"></i>
                            </button>
                            <button class="btn btn-success btn-sm btn-trigger-add-trans" data-id="${r.id}" title="Add Transaction">
                                <i class="fa-solid fa-dollar text-secondary"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
            });
        }
        $('#payments-tbody').html(rows);
    }

    /* ================================================================
        VIEW PAYMENT DETAILS (FINANCIAL LEDGER VIEW)
    ================================================================ */
    $(document).on('click', '.btn-view-payment', function () {
        var id = $(this).data('id');

        App.ajax({
            url: '/emp-payments/get.php?id=' + id,
            loader: true,
            onSuccess: function (res) {
                var r = res.data ? res.data : res;

                var balanceClass = r.balance_due > 0 ? 'text-danger' : (r.balance_due < 0 ? 'text-info' : 'text-success');
                var balanceLabel = r.balance_due > 0 ? 'Amount Due' : (r.balance_due < 0 ? 'Patient Credit' : 'Paid in Full');
                var balanceDisplay = `<span class="${balanceClass} fw-bold">$${Math.abs(r.balance_due).toFixed(2)}</span>`;

                var transRows = '';
                if (r.transactions && r.transactions.length > 0) {
                    r.transactions.forEach(t => {
                        transRows += `
                        <tr>
                            <td>${t.transaction_date}</td>
                            <td>${App.utils.escHtml(t.payment_method)}</td>
                            <td>$${parseFloat(t.amount).toFixed(2)}</td>
                            <td>${t.payment_type}</td>
                            <td>${App.utils.escHtml(t.transaction_notes || '')}</td>
                            <td>${t.creator_name || 'System'}</td>
                            <td>${t.editor_name || 'N/A'}<small> ${t.edited_at || ''} </small></td>
                            <td>
                                <button class="btn btn-sm btn-ghost text-primary btn-edit-trans" data-id="${t.id}"><i class="fa-solid fa-pen"></i></button>
                            </td>
                        </tr>`;
                    });
                } else {
                    transRows = `<tr><td colspan="8" class="text-center text-muted">No transactions recorded.</td></tr>`;
                }

                var html = `
                <div class="row" style="display:grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                    <div>
                        <div class="form-section-title mb-3">Patient & Provider</div>
                        ${infoRow('Patient', r.patient_name)}
                        ${infoRow('Provider', r.provider_name)}
                        ${infoRow('Created By', (r.creator_name || 'System') + ' <small class="text-muted">(' + r.created_at + ')</small>')}
                    </div>
                    <div>
                        <div class="form-section-title mb-3">Ledger Summary</div>
                        ${infoRow('Total Amount', '$' + parseFloat(r.total_amount).toFixed(2))}
                        ${infoRow('Total Paid', '$' + parseFloat(r.total_paid).toFixed(2))}
                        <div class="info-row mb-2" style="background:#f8fafc; padding:8px; border-radius:4px;">
                            <span class="text-muted" style="width:120px; display:inline-block;">${balanceLabel}:</span>
                            ${balanceDisplay}
                        </div>
                    </div>
                </div>

                <div class="form-section-title mb-3">Transaction History</div>
                <table class="data-table table-sm" style="width:100%; border: 1px solid #e2e8f0;">
                    <thead style="background:#f1f5f9;">
                        <tr>
                            <th>Date</th>
                            <th>Method</th>
                            <th>Amount</th>
                            <th>Payment Type</th>
                            <th>Transaction Note</th>
                            <th>Created By</th>
                            <th>Edit By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>${transRows}</tbody>
                </table>`;

                $('#view-payment-body').html(html);
                App.modal.open('view-payment-modal');
            }
        });
    });

    function infoRow(label, value) {
        return '<div class="info-row mb-2">' +
            '<span class="text-muted" style="width:130px; display:inline-block; font-size:0.85rem">' + label + ':</span>' +
            '<span class="fw-600">' + value + '</span>' +
            '</div>';
    }

    /* ================================================================
        TRANSACTION LEVEL PATCH AUDIT (EDIT ROUTE MAPPER)
    ================================================================ */
    $(document).on('click', '.btn-edit-trans', function () {
        var transactionId = $(this).data('id');

        App.ajax({
            url: '/emp-payments/get-transaction.php?id=' + transactionId,
            loader: true,
            onSuccess: function (res) {
                var t = res.data ? res.data : res;

                $('#edit_trans_id').val(t.id);
                $('#edit_trans_payment_id').val(t.payment_id);
                $('#edit_trans_amount').val(parseFloat(t.amount).toFixed(2));
                $('#edit_trans_payment_type').val(t.payment_type || 'Payment');
                $('#edit_trans_payment_method').val(t.payment_method || 'Cash');
                $('#edit_trans_notes').val(t.transaction_notes || '');

                App.modal.open('edit-transaction-modal');
            },
            onError: function (err) {
                App.toast.danger('Error', err.message || 'Failed to pull isolated transaction log parameters.');
            }
        });
    });

    $('#btn-update-transaction').on('click', function () {
        var form = $('#edit-transaction-form');
        var amount = $('#edit_trans_amount').val();
        var type = $('#edit_trans_payment_type').val();
        var method = $('#edit_trans_payment_method').val();

        if (!amount || parseFloat(amount) <= 0 || !type || !method) {
            App.toast.warning('Validation', 'Please fill all required (*) fields with valid parameters.');
            return;
        }

        var formData = form.serialize();
        var parentPaymentId = $('#edit_trans_payment_id').val();

        App.ajax({
            url: '/emp-payments/edit-transaction.php',
            method: 'POST',
            data: formData,
            btn: $('#btn-update-transaction'),
            loaderMsg: 'Updating transaction record and rebalancing file totals...',
            onSuccess: function (d, msg) {
                App.modal.close('edit-transaction-modal');
                App.modal.close('view-payment-modal');
                App.toast.success('Success', msg);

                loadPaymentsTable(currentPage);
            },
            onError: function (err) {
                App.toast.danger('Error', err.message || 'Failed to update transaction.');
            }
        });
    });


    /* ================================================================
        MASTER LEVEL LIABILITY EDIT MAPPER (EDIT ROUTE)
    ================================================================ */

    // Fetch Master Data Record and open modal view
    $(document).on('click', '.btn-edit-payment', function () {
        var paymentId = $(this).data('id');

        App.ajax({
            url: '/emp-payments/get-edit.php',
            method: 'GET',
            data: { id: paymentId },
            loader: true,
            onSuccess: function (res) {
                var p = res.data ? res.data : res;

                // Bind standard UI header fields
                $('#lbl_edit_pay_patient').text(p.patient_name || 'N/A');
                $('#lbl_edit_pay_status').text(p.status || 'Active');

                // Populate Form Target Inputs
                $('#edit_pay_id').val(p.id);
                $('#edit_pay_patient_id').val(p.patient_id);
                $('#edit_pay_total_amount').val(parseFloat(p.total_amount || 0).toFixed(2));

                if (p.payment_date) {
                    var cleanDate = p.payment_date.split(' ')[0];
                    $('#edit_pay_date').val(cleanDate);
                }

                // Match providers collection parameters layout
                $('#edit_pay_provider_id').val(p.provider_id).trigger('change');

                // Clear dynamic master array state safely
                treatmentsArray = [];

                // Unpack structured array configurations from schema payload
                if (p.treatments && Array.isArray(p.treatments)) {
                    p.treatments.forEach(function (treatment) {
                        treatmentsArray.push({
                            id: String(treatment.id),
                            name: treatment.name
                        });
                    });
                }

                // Align globally assigned tracker standard properties mapping
                window.editSelectedTreatmentIds = treatmentsArray;

                // Sync UI structural representation via unified generator engine
                renderTreatmentPills(true);

                App.modal.open('edit-payment-modal');
            },
            onError: function (err) {
                App.toast.danger('Fetch Failure', err.message || 'Failed to pull isolated master layout credentials.');
            }
        });
    });

    // Dispatch updated fields modifications down mutations ledger routes
    $('#btn-update-master-payment').on('click', function () {
        var form = $('#edit-payment-form');
        var totalAmount = $('#edit_pay_total_amount').val();
        var providerId = $('#edit_pay_provider_id').val();
        var statementDate = $('#edit_pay_date').val();

        if (!totalAmount || parseFloat(totalAmount) < 0 || !providerId || !statementDate) {
            App.toast.warning('Required Parameters Missing', 'Please specify provider data fields, execution states, and positive totals.');
            return;
        }

        var formData = form.serialize();

        App.ajax({
            url: '/emp-payments/update-payment.php',
            method: 'POST',
            data: formData,
            btn: $('#btn-update-master-payment'),
            loaderMsg: 'Saving payment adjustments...',
            onSuccess: function (d, msg) {
                App.modal.close('edit-payment-modal');
                App.toast.success('System Updated', msg);
                loadPaymentsTable(currentPage);
            },
            onError: function (err) {
                App.toast.error('Mutation Failure', err.message || 'Failed to successfully patch master data fields parameters.');
            }
        });
    });

    /* ================================================================
        DELETION ROUTINE METHOD MAPPER
    ================================================================ */
    $(document).on('click', '.btn-delete-payment', function () {
        var id = $(this).data('id');
        if (confirm('Are you absolutely certain you want to purge this record and cascade clear all historical ledger variants?')) {
            App.ajax({
                url: '/emp-payments/delete.php',
                method: 'POST',
                data: { id: id },
                onSuccess: function (res, msg) {
                    App.toast.success('Purged', msg || 'Record cleanly wiped out.');
                    loadPaymentsTable(currentPage);
                }
            });
        }
    });

    /* ================================================================
        TRANSACTION LEVEL ADDITION (ADD ROUTE MAPPER)
    ================================================================ */
    
    // Open modal and prime the form fields for a new record entry flow
    $(document).on('click', '.btn-trigger-add-trans', function () {
        // Capture the parent payment ID from whatever element triggers this action
        var parentPaymentId = $(this).data('id') || $('#edit_pay_id').val(); 
        
        if (!parentPaymentId) {
            App.toast.error('Error', 'Unable to determine parent payment ledger context.');
            return;
        }

        // Reset form inputs completely to avoid old cached states
        $('#add-transaction-form')[0].reset();
        
        // Bind key master record identity mappings 
        $('#add_trans_payment_id').val(parentPaymentId);
        
        // Pre-populate with today's date as a sane default user utility fallback
        var today = new Date().toISOString().split('T')[0];
        $('#add_trans_date').val(today);

        App.modal.open('add-transaction-modal');
    });

    // Handle form transmission workflow
    $('#btn-add-transaction').on('click', function () {
        var form = $('#add-transaction-form');
        var amount = $('#add_trans_amount').val();
        var type = $('#add_trans_payment_type').val();
        var method = $('#add_trans_payment_method').val();
        var txDate = $('#add_trans_date').val();

        // Strict parameters safety checks verification engine 
        if (!amount || parseFloat(amount) < 0 || !type || !method || !txDate) {
            App.toast.warning('Validation', 'Please fill all required (*) fields with valid parameters.');
            return;
        }

        var formData = form.serialize();

        App.ajax({
            url: '/emp-payments/add-transaction.php',
            method: 'POST',
            data: formData,
            btn: $('#btn-add-transaction'),
            loaderMsg: 'Logging transactional footprint and rebalancing file accounts...',
            onSuccess: function (d, msg) {
                // Safely close the modal layer stack structures down cleanly
                App.modal.close('add-transaction-modal');
                App.modal.close('view-payment-modal'); // If nested inside detail ledger views
                App.toast.success('Success', msg || 'Transaction logged successfully.');

                // Refresh main grid rendering metrics arrays
                loadPaymentsTable(currentPage);
            },
            onError: function (err) {
                App.toast.error('Error', err.message || 'Failed to append new transaction record.');
            }
        });
    });

    /* ================================================================
        BOOTSTRAP CORE ROUTINES
    ================================================================ */
    initDropdowns();
    loadPaymentsTable(1);
});