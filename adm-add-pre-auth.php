<?php
/**
 * Admin Panel - Create / Edit Pre-Auth Lifecycle Form Wrapper
 * adm-add-pre-auth.php
 */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Pre-Authorization Record</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/layout.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .treatment-row {
            border-bottom: 1px dashed var(--color-border, #eee);
            padding-bottom: 15px;
            margin-bottom: 15px;
        }

        .treatment-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
            margin-bottom: 0;
        }

        .btn-remove-row {
            background: #fee2e2;
            color: #ef4444;
            border: 1px solid #fca5a5;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-remove-row:hover {
            background: #fca5a5;
        }

        .error-highlight {
            border-color: #ef4444 !important;
            background-color: #fef2f2 !important;
        }

        .conditional-field-box {
            display: none;
            background: #f8fafc;
            border-left: 4px solid var(--primary, #2b8a3e);
            padding: 15px;
            border-radius: 0 4px 4px 0;
            margin-top: 15px;
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
                        <h1 id="form-action-title">Create Pre-Authorization Record</h1>
                        <div class="page-header-sub">Administrative data overrides engine override management platform.
                        </div>
                    </div>
                </div>

                <div class="card">
                    <form id="preauth-admin-form" novalidate>
                        <div class="grid-2 gap-4">
                            <div>
                                <div class="form-group mb-4">
                                    <label class="form-label font-bold">Assigned Clinic Office Location <span
                                            class="text-danger">*</span></label>
                                    <select class="form-control" id="office-id" name="office_id" required>
                                        <option value="">-- Loading Registered Locations --</option>
                                    </select>
                                </div>

                                <div class="form-group mb-4">
                                    <label class="form-label font-bold">Select Patient <span
                                            class="text-danger">*</span></label>
                                    <select class="form-control" id="patient-id" name="patient_id" required disabled
                                        style="width:100%">
                                        <option value="">-- Please select an office first --</option>
                                    </select>
                                </div>

                                <div class="form-group mb-4">
                                    <label class="form-label font-bold">Insurance Carrier <span
                                            class="text-danger">*</span></label>
                                    <select class="form-control" id="p-insurance-plan" name="p_insurance_plan" required>
                                        <option value="">-- Loading Insurance Matrix --</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <div class="form-group mb-4">
                                    <label class="form-label font-bold">Lifecycle Operational Status <span
                                            class="text-danger">*</span></label>
                                    <select class="form-control" id="status" name="status" required>
                                        <option value="Sent">Pending (Sent)</option>
                                        <option value="Approved">Approved</option>
                                        <option value="Rejected">Rejected</option>
                                        <option value="Appealed">Appealed</option>
                                        <option value="Scheduled">Scheduled</option>
                                        <option value="Completed">Completed</option>
                                    </select>
                                </div>

                                <div class="form-group mb-4">
                                    <label class="form-label font-bold">Created At (Timestamp Override) <span
                                            class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control" id="created-at" name="created_at"
                                        required>
                                </div>

                                <div id="box-appointment-date" class="conditional-field-box"
                                    style="display: none; background: #fafafa; padding: 12px; border-radius: 4px; border-left: 3px solid #2b8a3e;">
                                    <div class="form-group mb-3">
                                        <label class="form-label font-bold text-success">Scheduled Date Target <span
                                                class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="scheduled-date"
                                            name="scheduled_date">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label font-bold text-muted">Scheduled Expiry Date</label>
                                        <input type="date" class="form-control" id="scheduled-expiry-date"
                                            name="scheduled_expiry_date">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6">
                            <div class="flex flex-between flex-align mb-3">
                                <h3 class="text-md font-bold text-muted"><i class="fa-solid fa-tooth"></i> Itemized
                                    Treatment Procedures</h3>
                                <button type="button" class="btn btn-ghost btn-sm" id="btn-add-procedure">
                                    <i class="fa-solid fa-plus"></i> Append Row
                                </button>
                            </div>
                            <div id="procedures-treatment-container" class="card" style="background:#fafafa;">
                            </div>
                        </div>

                        <div class="flex flex-end gap-3 mt-6 pt-4 no-print" style="border-top:1px solid #eee;">
                            <a href="adm-pre-auth.php" class="btn btn-ghost">Cancel & Return</a>
                            <button type="submit" class="btn btn-primary" id="btn-submit-form">
                                <i class="fa-solid fa-floppy-disk"></i> Commit Record changes
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="assets/js/app.js"></script>

    <script>
        $(document).ready(function () {
            // 1. Run strict structural access authorization check
            App.auth.check();
            App.auth.role('admin');

            // Form state configurations
            let urlParams = new URLSearchParams(window.location.search);
            let editingRecordId = urlParams.get('id') || null;
            let loadedProceduresDropdownHtml = '';

            // Initialize Form Configuration default dates
            if (!editingRecordId) {
                let now = new Date();
                now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
                $('#created-at').val(now.toISOString().slice(0, 16));
            }

            // Target mapping container instantiation for Select2 setup
            $('#patient-id').select2({ placeholder: "Choose Clinic Office Location First..." });

            /**
             * Dynamic request workflow to load patients contextually isolated by selected office ID
             */
            function loadPatientsByOffice(officeId, callbackSelectedPatientId = null) {
                if (!officeId) {
                    $('#patient-id').html('<option value="">-- Please select an office first --</option>').prop('disabled', true).trigger('change');
                    return;
                }

                App.ajax({
                    url: '/adm-pre-auth/select-patients-by-office.php',
                    method: 'GET',
                    data: { office_id: officeId },
                    loader: true,
                    onSuccess: function (res) {
                        let dataPool = res.data || res;
                        let patientOptions = '<option value="">-- Choose Patient Target --</option>';

                        $.each(dataPool, function (i, item) {
                            patientOptions += `<option value="${item.id}">${App.utils.escHtml(item.name)} (DOB: ${item.dob || '—'})</option>`;
                        });

                        $('#patient-id').html(patientOptions).prop('disabled', false).select2({ placeholder: "Search Patient..." });

                        if (callbackSelectedPatientId) {
                            $('#patient-id').val(callbackSelectedPatientId).trigger('change');
                        }
                    },
                    onError: function () {
                        App.toast.error("Failed to retrieve patients lookup for the selected clinic office configuration.");
                    }
                });
            }

            // Change monitoring trigger interface workflow on Office selection
            $('#office-id').on('change', function () {
                let currentOfficeVal = $(this).val();
                loadPatientsByOffice(currentOfficeVal);
            });

            function loadFormDropdownDataPools() {
                // Aggregate lookup data endpoint mapping configuration
                App.ajax({
                    url: '/adm-pre-auth/admin-load-pre-auth-dropdown.php',
                    method: 'GET',
                    loader: true,
                    onSuccess: function (res) {
                        let datasets = res.data || res;

                        // 1. Map Office Locations
                        let officeOptions = '<option value="">-- Choose Clinic Office --</option>';
                        $.each(datasets.offices, function (i, item) {
                            officeOptions += `<option value="${item.id}">${App.utils.escHtml(item.office_name || item.name)}</option>`;
                        });
                        $('#office-id').html(officeOptions);

                        // 2. Map Carrier Plans
                        let insOptions = '<option value="">-- Choose Carrier Plan --</option>';
                        $.each(datasets.insurances, function (i, item) {
                            insOptions += `<option value="${item.id}">${App.utils.escHtml(item.name)}</option>`;
                        });
                        $('#p-insurance-plan').html(insOptions);

                        // 3. Cache Treatment Codes Memory String
                        loadedProceduresDropdownHtml = '<option value="">-- Select Code --</option>';
                        $.each(datasets.procedures, function (i, item) {
                            loadedProceduresDropdownHtml += `<option value="${item.id}">${App.utils.escHtml(item.name)}</option>`;
                        });

                        // Post-load validation router execution
                        if (!editingRecordId) {
                            appendTreatmentRowInstance();
                        } else {
                            checkAndExecuteEditModeRuntime();
                        }
                    },
                    onError: function () {
                        App.toast.error("Critical communications failure with unified collection matrices.");
                    }
                });
            }

            /**
             * Handle operational date visibility state based on select dropdown values
             */
            $('#status').on('change', function () {
                let currentStatus = $(this).val().toLowerCase();

                if (['scheduled', 'completed'].includes(currentStatus)) {
                    $('#box-appointment-date').slideDown(200);
                    $('#scheduled-date').prop('required', true);
                } else {
                    $('#box-appointment-date').slideUp(200);
                    $('#scheduled-date').prop('required', false).val('');
                    $('#scheduled-expiry-date').val('');
                }
            });

            /**
             * Append dynamic procedure data entry row nodes to container element
             */
            function appendTreatmentRowInstance(selectedProcId = '', selectedTooth = '') {
                let uniqueSeedId = Date.now() + Math.floor(Math.random() * 100);
                let rowHtml = `
                    <div class="grid-12 gap-3 flex-align treatment-row" id="row-${uniqueSeedId}">
                        <div class="col-span-7">
                            <label class="text-xs text-muted font-bold display-block mb-1">Procedure Code / Description</label>
                            <select class="form-control procedure-select-field" name="procedures[${uniqueSeedId}][id]" required>
                                ${loadedProceduresDropdownHtml}
                            </select>
                        </div>
                        <div class="col-span-3">
                            <label class="text-xs text-muted font-bold display-block mb-1">Tooth</label>
                            <select class="form-control tooth-input-field" name="procedures[${uniqueSeedId}][tooth]">
                <option value="">-- Tooth --</option>
                ${Array.from({ length: 32 }, (_, i) => i + 1).map(num => `
                    <option value="${num}" ${Number(selectedTooth) === num ? 'selected' : ''}>#${num}</option>
                `).join('')}
            </select>
                        </div>
                        <div class="col-span-2 text-center" style="margin-top:20px;">
                            <button type="button" class="btn-remove-row remove-treatment-trigger" data-target="#row-${uniqueSeedId}">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                    </div>
                `;
                $('#procedures-treatment-container').append(rowHtml);

                if (selectedProcId) {
                    $(`#row-${uniqueSeedId} .procedure-select-field`).val(selectedProcId);
                }
            }

            // Remove procedure row event binding
            $(document).on('click', '.remove-treatment-trigger', function () {
                let targetNode = $(this).data('target');
                if ($('.treatment-row').length > 1) {
                    $(targetNode).remove();
                } else {
                    App.toast.error("An authorized transaction record requires at least one procedure entry.");
                }
            });

            $('#btn-add-procedure').on('click', function (e) {
                e.preventDefault();
                appendTreatmentRowInstance();
            });

            /**
             * Runtime Hydration Engine to handle system updates/Edits
             */
            function checkAndExecuteEditModeRuntime() {
                if (!editingRecordId) return;

                $('#form-action-title').text(`Edit Pre-Auth Record (#${editingRecordId})`);

                App.ajax({
                    url: '/adm-pre-auth/get.php',
                    method: 'GET',
                    data: { id: editingRecordId },
                    loader: true,
                    onSuccess: function (res) {
                        // FIX: Access the data directly using your real API structure (res.data)
                        let record = res || null;

                        if (!record) {
                            App.toast.error("Target lifecycle data stream returned empty or inaccessible.");
                            return;
                        }

                        // Populate master field parameters 
                        $('#office-id').val(record.office_id);
                        $('#p-insurance-plan').val(record.p_insurance_plan);
                        $('#status').val(record.status).trigger('change');


                        if (record.appointment_date) {
                            $('#scheduled-date').val(record.appointment_date.replace(' ', 'T').slice(0, 16));
                        }

                        if (record.approval_expire_date) {
                            $('#scheduled-expiry-date').val(record.approval_expire_date.replace(' ', 'T').slice(0, 16));
                        }

                        if (record.notes) {
                            $('#notes').val(record.notes);
                        }

                        /**
                         * CASCADE LOOKUP TRIGGER:
                         * This calls your endpoint to fetch patients belonging to this office ID.
                         * Passing record.patient_id ensures that once the select options are built,
                         * it automatically updates the patient element to match this specific profile context.
                         */
                        loadPatientsByOffice(record.office_id, record.patient_id);

                        // Populate dates safely for standard HTML5 datetime-local/date fields
                        if (record.created_at) {
                            $('#created-at').val(record.created_at.slice(0, 16));
                        }

                        if (record.appointment_date) {
                            // If your HTML input type is 'date', use slice(0, 10). If 'datetime-local', use slice(0, 16)
                            let dateLength = $('#scheduled-date').attr('type') === 'date' ? 10 : 16;
                            $('#scheduled-date').val(record.appointment_date.slice(0, dateLength));
                        }

                        // Populate itemized nested treatments matrix layout safely
                        $('#procedures-treatment-container').empty();
                        if (record.procedures_list && record.procedures_list.length > 0) {
                            $.each(record.procedures_list, function (i, proc) {
                                appendTreatmentRowInstance(proc.procedure_id, proc.tooth_number || '');
                            });
                        } else {
                            appendTreatmentRowInstance();
                        }
                    }
                });
            }

            /**
             * Intercept submission events and execute structural data transformations
             */
            $('#preauth-admin-form').on('submit', function (e) {
                e.preventDefault();

                $('.error-highlight').removeClass('error-highlight');

                let isValid = true;
                let formsFields = document.getElementById('preauth-admin-form').querySelectorAll('[required]');

                formsFields.forEach(field => {
                    if (field.disabled) return; // Skip lookup parameters locked out during client runtime execution variations
                    if (!field.value.trim()) {
                        isValid = false;
                        $(field).addClass('error-highlight');
                        if ($(field).hasClass('select2-hidden-accessible')) {
                            $(field).next('.select2-container').find('.select2-selection').addClass('error-highlight');
                        }
                    }
                });

                if (!isValid) {
                    App.toast.error("Please fill in all required operational parameter cells.");
                    return;
                }

                let procedurePayloadItems = [];
                $('.treatment-row').each(function () {
                    let procId = $(this).find('.procedure-select-field').val();
                    let toothNum = $(this).find('.tooth-input-field').val();
                    if (procId) {
                        procedurePayloadItems.push({
                            procedure_id: procId,
                            tooth_number: toothNum
                        });
                    }
                });

                let dynamicPayload = {
                    patient_id: $('#patient-id').val(),
                    office_id: $('#office-id').val(),
                    p_insurance_plan: $('#p-insurance-plan').val(),
                    status: $('#status').val(),
                    created_at: $('#created-at').val(),
                    appointment_date: $('#scheduled-date').val() || null,
                    procedures: procedurePayloadItems
                };

                let requestUrl = '/adm-pre-auth/create.php';
                if (editingRecordId) {
                    dynamicPayload.id = editingRecordId;
                    requestUrl = '/adm-pre-auth/update.php';
                }

                App.ajax({
                    url: requestUrl,
                    method: 'POST',
                    data: dynamicPayload,
                    contentType: 'application/json',
                    onSuccess: function (response) {
                        App.toast.success(response.message || "Administrative parameters committed successfully.");
                        setTimeout(function () {
                            window.location.href = 'adm-pre-auth.php';
                        }, 1000);
                    },
                    onError: function (xhr) {
                        let errorMsg = xhr.responseJSON?.message || "An administrative update transaction exception occurred.";
                        App.toast.error(errorMsg);
                    }
                });
            });

            // Trigger initialization cascade runtime loop operations
            loadFormDropdownDataPools();
        });
    </script>
</body>

</html>