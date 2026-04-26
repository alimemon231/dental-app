<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Monthly Budgets</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/layout.css">
</head>

<body>
    <div class="app-shell">
        <?php require_once "includes/page-header.php" ?>
        <main class="main-content">
            <div class="page-wrapper">


                <div class="page-header">
                    <div class="page-header-left">
                        <h1>Manage Monthly Budgets</h1>
                        <div class="page-header-sub">Manage all Monthly Budgets here.</div>
                    </div>
                    <div class="page-header-actions">
                        <button class="btn btn-primary" id="btn-add-budget">
                            <i class="fa-solid fa-dollar"></i> Add Monthly Budgets
                        </button>
                    </div>
                </div>

                <div class="card mb-6">
                    <div class="flex flex-align gap-4" style="flex-wrap:wrap">
                        <select class="form-control" id="year" style="max-width:240px">
                            <option value="null" selected disabled>Select Year</option>
                            <?php

                            $currentYear = (int) date("Y");
                            for ($year = 2026; $year <= $currentYear; $year++) {
                                ?>
                                <option value="<?php echo $year; ?>"> <?php echo $year; ?> </option>
                                <?php
                            }
                            ?>
                        </select>

                        <select class="form-control" id="month" style="max-width:240px">
                            <option value="null" selected disabled>Select Month</option>
                            <?php


                            for ($m = 1; $m <= 12; $m++) {
                                ?>
                                <option value="<?php echo date("m", mktime(0, 0, 0, $m, 1)); ?>">
                                    <?php echo date("F", mktime(0, 0, 0, $m, 1)); ?>
                                </option>
                                <?php
                            }
                            ?>
                        </select>
                        <button class="btn btn-ghost btn-sm" id="btn-clear-filters">
                            <i class="fa-solid fa-xmark"></i> Clear
                        </button>
                        <button class="btn btn-ghost btn-sm" id="search-button">
                            <i class="fa-solid fa-file-export"></i> Search
                        </button>
                    </div>
                </div>

                <!-- ============================================================
     PATIENTS TABLE
============================================================ -->
                <div class="table-wrapper">
                    <table class="data-table" id="staff-table">
                        <thead>
                            <tr>
                                <th class="sortable" data-col="id">#</th>
                                <th class="sortable" data-col="name">Office</th>
                                <th>Budget Month</th>
                                <th>Expanded Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="budget-tbody">
                            <tr>
                                <td colspan="8">
                                    <div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="table-pagination" id="patients-pagination">
                        <span id="patients-info" class="text-muted text-sm">—</span>
                        <div class="pagination" id="pagination-btns"></div>
                    </div>
                </div>

                <!-- ============================================================
     ADD / EDIT Monthly Budgets MODAL
============================================================ -->
                <div class="modal-backdrop" id="budget-modal">
                    <div class="modal modal-lg">
                        <div class="modal-header">
                            <div class="modal-title" id="budget-modal-title">Add New Monthly Budgets</div>
                            <button class="modal-close" data-close-modal="budget-modal">&#x2715;</button>
                        </div>
                        <div class="modal-body">
                            <form id="budget-form" novalidate>
                                <input type="hidden" name="budget_id" id="budget-id" value="">

                                <!-- Personal Info -->
                                <div class="form-section">
                                    <div class="form-section-title"><i class="fa-solid fa-dollar"></i> Monthly Budgets
                                        Information</div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label class="form-label">Select Office <span
                                                    class="required">*</span></label>
                                            <select name="office" id="office" class="form-control" required>
                                                <option value="" selected disabled>Select Office</option>
                                            </select>
                                            <span class="form-error">Please select a office</span>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Select Year <span
                                                    class="required">*</span></label>
                                            <select name="budget_year" id="budget-year" class="form-control" required>
                                                <option value="" selected disabled>Select Year</option>
                                                <?php

                                                $currentYear = (int) date("Y");
                                                for ($year = 2026; $year <= $currentYear; $year++) {
                                                    ?>
                                                    <option value="<?php echo $year; ?>">
                                                        <?php echo $year; ?>
                                                    </option>
                                                    <?php
                                                }
                                                ?>
                                            </select>
                                            <span class="form-error">Please select a year.</span>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label class="form-label">Select Month <span
                                                    class="required">*</span></label>
                                            <select name="budget_month" id="budget-month" class="form-control" required>
                                                <option value="" selected disabled>Select Month</option>
                                                <?php
                                                for ($m = 1; $m <= 12; $m++) {
                                                    ?>
                                                    <option value="<?php echo date("m", mktime(0, 0, 0, $m, 1)); ?>">
                                                        <?php echo date("F", mktime(0, 0, 0, $m, 1)); ?>
                                                    </option>
                                                    <?php
                                                }
                                                ?>
                                            </select>
                                            <span class="form-error">Please select a month.</span>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label">Total budget <span
                                                    class="required">*</span></label>
                                            <input type="number" name="budget_amount" id="bbudget-amount" class="form-control"
                                                placeholder="e.g 2300" required>
                                            <span class="form-error">Please add amount.</span>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-ghost" data-close-modal="budget-modal">Cancel</button>
                            <button class="btn btn-primary" id="btn-save-budget">
                                <span class="btn-spinner"></span>
                                <span class="btn-text">Save Budget</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
     VIEW PATIENT MODAL
============================================================ -->
                <div class="modal-backdrop" id="view-patient-modal">
                    <div class="modal modal-lg">
                        <div class="modal-header">
                            <div class="modal-title">Monthly Budgets Details</div>
                            <button class="modal-close" data-close-modal="view-patient-modal">&#x2715;</button>
                        </div>
                        <div class="modal-body" id="view-patient-body">
                            Loading…
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-ghost" data-close-modal="view-patient-modal">Close</button>
                            <button class="btn btn-primary" id="btn-edit-from-view">
                                <i class="fa-solid fa-pen"></i> Edit
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Reusable confirm modal -->
    <div class="modal-backdrop" id="confirm-modal">
        <div class="modal modal-sm">
            <div class="modal-header">
                <div class="modal-title">Confirm Action</div>
                <button class="modal-close" data-close-modal="confirm-modal">&#x2715;</button>
            </div>
            <div class="modal-body">
                <p class="confirm-message">Are you sure?</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" id="confirm-cancel">Cancel</button>
                <button class="btn btn-danger" id="confirm-ok">Confirm</button>
            </div>
        </div>
    </div>

    <div id="toast-container"></div>
    <div id="global-loader">
        <div class="loader-spinner"></div>
        <div class="loader-text">Please wait…</div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/budget.js"></script>
    <script>
        $(document).ready(function () {

            /* 1. Check auth */
            App.auth.check();
            App.auth.role('admin')

            /* 2. User info */
            App.ajax({
                url: '/auth/check.php', loader: false, silent: true,
                onSuccess: function (d) {
                    if (d && d.user) {
                        $('#sidebar-user-name').text(d.user.name);
                        $('#sidebar-user-role').text(d.user.role || 'Staff');
                        $('#user-avatar-initial').text(d.user.name.charAt(0).toUpperCase());
                    }
                }
            });

            //* Toggle password */
            $(document).on('click', '.toggle-pass', function () {
                var inp = $(this).closest('.input-group').find('input');
                var ico = $(this).find('i');
                inp.attr('type', inp.attr('type') === 'password' ? 'text' : 'password');
                ico.toggleClass('fa-eye fa-eye-slash');
            });

        });
    </script>
</body>

</html>