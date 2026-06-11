<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payments & Ledger</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/layout.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        /* Select2 custom height alignment */
        .select2-container--default .select2-selection--single {
            height: 38px !important;
            border: 1px solid var(--color-border) !important;
            border-radius: var(--radius-md) !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 36px !important;
            padding-left: var(--sp-3) !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px !important;
        }

        /* Selected Treatments Pill UI */
        .treatment-pill {
            display: inline-flex;
            align-items: center;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #334155;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
            margin: 4px;
        }

        .treatment-pill .remove-pill {
            margin-left: 8px;
            color: #ef4444;
            cursor: pointer;
            font-weight: bold;
        }

        .treatment-pill .remove-pill:hover {
            color: #b91c1c;
        }

        /* Custom Table Control Header */
        .table-controls-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            padding: var(--sp-4) var(--sp-5);
            border-radius: var(--radius-md) var(--radius-md) 0 0;
            border: 1px solid #e2e8f0;
            border-bottom: none;
            margin-top: var(--sp-4);
        }

        .table-controls-container .section-title {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
        }

        .btn-refresh-control {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #f8fafc;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 7px 14px;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-refresh-control:hover {
            background: #f1f5f9;
            color: var(--color-primary);
            border-color: #94a3b8;
        }
    </style>
</head>

<body>
    <div class="app-shell">
        <?php require_once "includes/page-header.php" ?>

        <main class="main-content">
            <div class="page-wrapper">

                <div class="page-header">
                    <div class="page-header-left">
                        <h1>Account Receivables & Payments</h1>
                        <div class="page-header-sub">Manage master invoices, multi-method payments, and refunds.</div>
                    </div>
                    <div class="page-header-actions">
                        <button class="btn btn-primary" id="btn-open-payment-modal">
                            <i class="fa-solid fa-file-invoice-dollar"></i> New Payment
                        </button>
                    </div>
                </div>

                <div class="table-controls-container">
                    <div class="controls-right">
                        <button type="button" id="btn-refresh-table" class="btn-refresh-control"
                            title="Hot Reload Live Records Pipeline">
                            <i class="fa-solid fa-rotate"></i> <span>Refresh Data</span>
                        </button>
                    </div>
                </div>

                <div class="table-wrapper">
                    <table class="data-table" id="payments-table">
                        <thead>
                            <tr>
                                <th class="sortable">Payment ID</th>
                                <th class="sortable">Patient Name</th>
                                <th>Treatments</th>
                                <th>Total Cost</th>
                                <th>Total Paid Amount</th>
                                <th>Balance</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="payments-tbody">
                            <tr>
                                <td colspan="7" class="text-center p-4 text-muted">
                                    <div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading...
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="table-pagination" id="payments-pagination">
                        <span id="payments-info" class="text-muted text-sm">—</span>
                        <div class="pagination" id="pagination-btns"></div>
                    </div>
                </div>


                <div class="modal-backdrop" id="view-payment-modal">
                    <div class="modal modal-xl" style="max-width:1000px;">
                        <div class="modal-header">
                            <div class="modal-title" id="payment-modal-title">Payment Details</div>
                            <button class="modal-close" data-close-modal="view-payment-modal">&#x2715;</button>
                        </div>
                        <div class="modal-body" id="view-payment-body">Loading...</div>
                        <div class="modal-footer">
                            <button class="btn btn-ghost" data-close-modal="view-payment-modal">Close</button>
                            <button class="btn btn-primary btn-add-transaction" id="btn-add-trans-from-view">Add
                                Transaction</button>
                        </div>
                    </div>
                </div>

                <div class="modal-backdrop" id="add-payment-modal">
                    <div class="modal modal-lg">
                        <div class="modal-header">
                            <div class="modal-title" id="payment-modal-title">Initialize New Payment File</div>
                            <button class="modal-close" data-close-modal="add-payment-modal">&#x2715;</button>
                        </div>

                        <div class="modal-body">
                            <form id="add-payment-form" novalidate>
                                <input type="hidden" name="payment_id" id="payment_id" value="">

                                <div class="form-section-title mb-3"><i class="fa-solid fa-address-card"></i> 1. Master
                                    Invoice Details</div>

                                <div class="form-row" style="grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Patient <span class="required">*</span></label>
                                        <select name="patient_id" id="patient-select" class="form-control" required
                                            style="width: 100%;">
                                            <option value="">Search patient...</option>
                                        </select>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label class="form-label">Provider <span class="required">*</span></label>
                                        <select name="provider_id" id="provider-select" class="form-control" required
                                            style="width: 100%;">
                                            <option value="">Search provider...</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group mb-3 p-3"
                                    style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px;">
                                    <label class="form-label">Treatments Included <span
                                            class="required">*</span></label>
                                    <div style="display: flex; gap: 10px;">
                                        <select id="treatment-search-select" class="form-control" style="width: 100%;">
                                            <option value="">Search and select treatment...</option>
                                        </select>
                                        <button type="button" id="btn-add-treatment"
                                            class="btn btn-secondary text-nowrap">Add</button>
                                    </div>
                                    <input type="hidden" name="treatment_ids" id="treatment_ids_payload" required>

                                    <div id="selected-treatments-container" class="mt-3">
                                        <span class="text-muted text-sm" id="no-treatment-text">No treatments added
                                            yet.</span>
                                    </div>
                                </div>

                                <div class="form-row" style="grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Total Amount / Cost ($) <span
                                                class="required">*</span></label>
                                        <input type="number" step="0.01" name="total_amount" id="total_amount"
                                            class="form-control" required>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label class="form-label">Master Payment Type <span
                                                class="required">*</span></label>
                                        <select name="master_payment_type" id="master_payment_type" class="form-control"
                                            required>
                                            <option value="Self Pay">Self Pay</option>
                                            <option value="Co Payment">Co Payment</option>
                                        </select>
                                    </div>
                                </div>

                                <hr style="border-top: 1px solid #e2e8f0; margin: 20px 0;">

                                <div class="form-section-title mb-3"><i class="fa-solid fa-cash-register"></i> 2.
                                    Initial Transaction</div>

                                <div class="form-row" style="grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Initial Paid Amount ($) <span
                                                class="required">*</span></label>
                                        <input type="number" step="0.01" name="transaction_amount"
                                            id="transaction_amount" class="form-control" required>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label class="form-label">Payment Method <span class="required">*</span></label>
                                        <select name="payment_method" id="payment_method" class="form-control" required>
                                            <option value="Cash">Cash</option>
                                            <option value="Credit">Credit Card</option>
                                            <option value="Debit">Debit Card</option>
                                            <option value="Care Credit">Care Credit</option>
                                            <option value="Alpheon">Alpheon</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="form-label">Transaction Notes (Optional)</label>
                                    <textarea name="transaction_notes" id="transaction_notes" class="form-control"
                                        rows="2" placeholder="Reference numbers, check details, etc."></textarea>
                                </div>

                            </form>
                        </div>

                        <div class="modal-footer">
                            <button class="btn btn-ghost" data-close-modal="add-payment-modal">Cancel</button>
                            <button class="btn btn-primary" id="btn-save-payment">
                                <span class="btn-spinner"></span>
                                <span class="btn-text">Save Payment & Transaction</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="modal-backdrop" id="add-transaction-modal">
                    <div class="modal modal-md">
                        <div class="modal-header">
                            <div class="modal-title">Add New Transaction</div>
                            <button class="modal-close" data-close-modal="add-transaction-modal">&#x2715;</button>
                        </div>

                        <div class="modal-body">
                            <form id="add-transaction-form" novalidate>
                                <input type="hidden" name="payment_id" id="add_trans_payment_id" value="">

                                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Transaction Type <span
                                                class="required">*</span></label>
                                        <select name="payment_type" id="add_trans_payment_type" class="form-control"
                                            required>
                                            <option value="Payment">Payment</option>
                                            <option value="Refund">Refund</option>
                                        </select>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label class="form-label">Payment Method <span class="required">*</span></label>
                                        <select name="payment_method" id="add_trans_payment_method" class="form-control"
                                            required>
                                            <option value="Cash">Cash</option>
                                            <option value="Credit">Credit Card</option>
                                            <option value="Debit">Debit Card</option>
                                            <option value="Care Credit">Care Credit</option>
                                            <option value="Alpheon">Alpheon</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Amount ($) <span class="required">*</span></label>
                                        <input type="number" step="0.01" name="amount" id="add_trans_amount"
                                            class="form-control" required>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label class="form-label">Transaction Date <span
                                                class="required">*</span></label>
                                        <input type="date" name="transaction_date" id="add_trans_date"
                                            class="form-control" required>
                                    </div>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="form-label">Transaction Notes (Optional)</label>
                                    <textarea name="transaction_notes" id="add_trans_notes" class="form-control"
                                        rows="3"
                                        placeholder="Reference details, check numbers, tracking logs..."></textarea>
                                </div>
                            </form>
                        </div>

                        <div class="modal-footer">
                            <button class="btn btn-ghost" data-close-modal="add-transaction-modal">Cancel</button>
                            <button class="btn btn-primary" id="btn-add-transaction">
                                <span class="btn-spinner"></span>
                                <span class="btn-text">Save Transaction</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="modal-backdrop" id="edit-transaction-modal">
                    <div class="modal modal-md">
                        <div class="modal-header">
                            <div class="modal-title">Edit Transaction Details</div>
                            <button class="modal-close" data-close-modal="edit-transaction-modal">&#x2715;</button>
                        </div>

                        <div class="modal-body">
                            <form id="edit-transaction-form" novalidate>
                                <input type="hidden" name="transaction_id" id="edit_trans_id" value="">
                                <input type="hidden" name="payment_id" id="edit_trans_payment_id" value="">

                                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Transaction Type <span
                                                class="required">*</span></label>
                                        <select name="payment_type" id="edit_trans_payment_type" class="form-control"
                                            required>
                                            <option value="Payment">Payment</option>
                                            <option value="Refund">Refund</option>
                                        </select>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label class="form-label">Payment Method <span class="required">*</span></label>
                                        <select name="payment_method" id="edit_trans_payment_method"
                                            class="form-control" required>
                                            <option value="Cash">Cash</option>
                                            <option value="Credit">Credit Card</option>
                                            <option value="Debit">Debit Card</option>
                                            <option value="Care Credit">Care Credit</option>
                                            <option value="Alpheon">Alpheon</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="form-label">Amount ($) <span class="required">*</span></label>
                                    <input type="number" step="0.01" name="amount" id="edit_trans_amount"
                                        class="form-control" required>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="form-label">Transaction Notes (Optional)</label>
                                    <textarea name="transaction_notes" id="edit_trans_notes" class="form-control"
                                        rows="3"
                                        placeholder="Reference details, check serial numbers, tracking logs..."></textarea>
                                </div>
                            </form>
                        </div>

                        <div class="modal-footer">
                            <button class="btn btn-ghost" data-close-modal="edit-transaction-modal">Cancel</button>
                            <button class="btn btn-primary" id="btn-update-transaction">
                                <span class="btn-spinner"></span>
                                <span class="btn-text">Update Transaction</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="modal-backdrop" id="confirm-modal">
                    <div class="modal modal-sm">
                        <div class="modal-header">
                            <div class="modal-title" id="confirm-title">Confirm Action</div>
                            <button class="modal-close" data-close-modal="confirm-modal">&#x2715;</button>
                        </div>
                        <div class="modal-body">
                            <div id="confirm-body-content"></div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-ghost" data-close-modal="confirm-modal"
                                id="confirm-cancel">Cancel</button>
                            <button class="btn" id="confirm-ok">Proceed</button>
                        </div>
                    </div>
                </div>

                <div class="modal-backdrop" id="edit-payment-modal">
                    <div class="modal modal-lg">
                        <div class="modal-header">
                            <div class="modal-title">Edit Master Payment Shell Liability</div>
                            <button class="modal-close" data-close-modal="edit-payment-modal">&#x2715;</button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-4 p-3 text-sm"
                                style="background: #f8fafc; border-radius: var(--radius-md); border-left: 4px solid var(--color-primary, #64748b);">
                                <strong>Current Status:</strong> <span id="lbl_edit_pay_status"
                                    class="badge bg-secondary">Active</span>
                                <small class="text-muted d-block mt-1">Altering core metrics structuralizes
                                    modifications against running ledger balance totals.</small>
                            </div>

                            <form id="edit-payment-form" novalidate>
                                <input type="hidden" name="payment_id" id="edit_pay_id" value="">

                                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Patient File Reference <span
                                                class="required">*</span></label>
                                        <select name="patient_id" id="edit_pay_patient_id" class="form-control"
                                            style="width:100%;" required>
                                        </select>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label class="form-label">Assigned Clinic Practitioner <span
                                                class="required">*</span></label>
                                        <select name="provider_id" id="edit_pay_provider_id" class="form-control"
                                            style="width:100%;" required>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group mb-3 p-3"
                                    style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: var(--radius-md);">
                                    <label class="form-label">Contracted Medical Treatments <span
                                            class="required">*</span></label>
                                    <div style="display: flex; gap: 10px;">
                                        <select id="edit-treatment-search-select" class="form-control"
                                            style="width: 100%;">
                                            <option value="">Search and select treatment...</option>
                                        </select>
                                        <button type="button" id="btn-edit-add-treatment"
                                            class="btn btn-secondary text-nowrap">Add</button>
                                    </div>
                                    <input type="hidden" name="treatment_ids" id="edit_treatment_ids_payload" required>

                                    <div id="edit-selected-treatments-container" class="mt-3">
                                        <span class="text-muted text-sm" id="edit-no-treatment-text">No treatments added
                                            yet.</span>
                                    </div>
                                </div>

                                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Total Contracted Liability ($) <span
                                                class="required">*</span></label>
                                        <input type="number" step="0.01" name="total_amount" id="edit_pay_total_amount"
                                            class="form-control" required>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label class="form-label">Original Statement Booking Date <span
                                                class="required">*</span></label>
                                        <input type="date" name="payment_date" id="edit_pay_date" class="form-control"
                                            required>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div class="modal-footer">
                            <button class="btn btn-ghost" data-close-modal="edit-payment-modal">Cancel
                                Operation</button>
                            <button class="btn btn-primary" id="btn-update-master-payment">
                                <span class="btn-spinner"></span>
                                <span class="btn-text">Save Payment Details</span>
                            </button>
                        </div>
                    </div>
                </div>


            </div>
        </main>
    </div>

    <div id="toast-container"></div>
    <div id="global-loader">
        <div class="loader-spinner"></div>
        <div class="loader-text">Please wait…</div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/emp-payments.js"></script>
    <script>
        $(document).ready(function () {
            /* Auth checks */
            App.auth.check();
            App.auth.role(['staff', 'doctor']);

            App.ajax({
                url: '/auth/check.php', loader: false, silent: true,
                onSuccess: function (d) {
                    if (d && d.user) {
                        $('#sidebar-user-name').text(d.user.name);
                        $('#sidebar-user-role').text(d.user.role);
                        $('#user-avatar-initial').text(d.user.name.charAt(0).toUpperCase());
                    }
                }
            });
        });
    </script>
</body>

</html>