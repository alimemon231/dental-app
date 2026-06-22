<?php
/**
 * Admin Panel - Create / Edit Lab Case Record Wrapper
 * adm-create-lab.php
 */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Lab Case Prescription</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/layout.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        /* Tooth Chart Styles */
        .tooth-chart-container {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .arch-row {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-bottom: 15px;
        }

        .tooth-btn {
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #dee2e6;
            background: #fff;
            cursor: pointer;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            transition: all 0.2s;
        }

        .tooth-btn:hover {
            border-color: #2b8a3e;
            color: #2b8a3e;
        }

        .tooth-btn.selected {
            background: #2b8a3e;
            color: #fff;
            border-color: #1e5f2a;
        }

        .arch-label {
            text-align: center;
            font-weight: bold;
            font-size: 11px;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        /* Sync Select2 with custom form engine styling */
        .select2-container--default .select2-selection--single {
            height: 38px !important;
            border: 1px solid var(--color-border, #cbd5e1) !important;
            border-radius: var(--radius-md, 4px) !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 36px !important;
            padding-left: var(--sp-3, 12px) !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px !important;
        }

        /* Form Logic & Overrides */
        .hidden-field {
            display: none;
        }

        .error-highlight {
            border-color: #ef4444 !important;
            background-color: #fef2f2 !important;
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
                        <h1 id="form-action-title">Create Lab Case Record</h1>
                        <div class="page-header-sub">Administrative laboratory prescription override and lifecycle management platform.</div>
                    </div>
                </div>

                <div class="card">
                    <form id="lab-admin-form" novalidate>
                        <div class="form-section">
                            <div class="form-section-title"><i class="fa-solid fa-flask"></i> General Information & Assignment</div>

                            <div class="grid-2 gap-4">
                                <div>
                                    <div class="form-group mb-4">
                                        <label class="form-label font-bold">Assigned Clinic Office Location <span class="text-danger">*</span></label>
                                        <select class="form-control" id="office-id" name="office_id" required>
                                            <option value="">-- Loading Registered Locations --</option>
                                        </select>
                                    </div>

                                    <div class="form-group mb-4">
                                        <label class="form-label font-bold">Select Patient Profile <span class="text-danger">*</span></label>
                                        <select class="form-control" id="patient-id" name="patient_id" required disabled style="width:100%">
                                            <option value="">-- Please select an office first --</option>
                                        </select>
                                    </div>

                                    <div class="form-group mb-4">
                                        <label class="form-label font-bold">Provider (Doctor) <span class="text-danger">*</span></label>
                                        <select class="form-control" id="doctor-id" name="doctor_id" required disabled style="width:100%">
                                            <option value="">-- Please select an office first --</option>
                                        </select>
                                    </div>

                                    <div class="form-group mb-4">
                                        <label class="form-label font-bold">Case Type <span class="text-danger">*</span></label>
                                        <select class="form-control" id="case_type_id" name="case_type_id" required>
                                            <option value="">-- Loading Case Types --</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group mb-4">
                                        <label class="form-label font-bold">Lifecycle Operational Status <span class="text-danger">*</span></label>
                                        <select class="form-control" id="status" name="status" required>
                                            <option value="Sent">Sent (Pending)</option>
                                            <option value="Received">Received</option>
                                            <option value="Scheduled">Scheduled</option>
                                            <option value="Done">Done</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <div class="form-group mb-4">
                                        <label class="form-label font-bold">Impression Type <span class="text-danger">*</span></label>
                                        <select class="form-control" id="impression-type" name="impression_type" required>
                                            <option value="Scan">Digital Scan</option>
                                            <option value="VPS">VPS / Manual</option>
                                        </select>
                                    </div>

                                    <div class="form-group mb-4">
                                        <label class="form-label font-bold">Select Lab Partner <span class="text-danger">*</span></label>
                                        <select class="form-control" id="lab_provider" name="lab_provider" required>
                                            <option value="">-- Loading Laboratory Matrices --</option>
                                        </select>
                                    </div>

                                    <div class="form-group mb-4">
                                        <label class="form-label font-bold">Next Visit Step <span class="text-danger">*</span></label>
                                        <select class="form-control" id="next_visit" name="next_visit" required>
                                            <option value="">-- Loading Workflow Options --</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group mb-4">
                                        <label class="form-label font-bold">Date Sent</label>
                                        <input type="date" class="form-control" id="date_sent" name="date_sent">
                                    </div>
                                    <div class="form-group mb-4">
                                        <label class="form-label font-bold">Date Received</label>
                                        <input type="date" class="form-control" id="date_received" name="date_received">
                                    </div>
                                    <div class="form-group mb-4">
                                        <label class="form-label font-bold">Date Scheduled</label>
                                        <input type="date" class="form-control" id="date_scheduled" name="date_scheduled">
                                    </div>
                                    <div class="form-group mb-4">
                                        <label class="form-label font-bold">Date Complete (Done)</label>
                                        <input type="date" class="form-control" id="date_complete" name="date_complete">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="section-teeth" class="mt-4">
                            <div class="form-section-title"><i class="fa-solid fa-tooth"></i> Clinical Requirements: Select Teeth</div>
                            <div class="tooth-chart-container">
                                <div class="arch-label">Upper Arch</div>
                                <div class="arch-row" id="upper-arch">
                                    <?php for ($i = 1; $i <= 16; $i++)
                                        echo "<div class='tooth-btn' data-tooth='$i'>$i</div>"; ?>
                                </div>
                                <div class="arch-label">Lower Arch</div>
                                <div class="arch-row" id="lower-arch">
                                    <?php for ($i = 32; $i >= 17; $i--)
                                        echo "<div class='tooth-btn' data-tooth='$i'>$i</div>"; ?>
                                </div>
                            </div>
                            <input type="hidden" name="u_arch" id="u_arch_input">
                            <input type="hidden" name="l_arch" id="l_arch_input">
                        </div>

                        <div id="section-arch" class="mt-4 hidden-field">
                            <div class="form-section-title"><i class="fa-solid fa-grip-lines"></i> Clinical Requirements: Arch Selection</div>
                            <div class="form-group">
                                <label class="form-label font-bold">Target Arch Geometry</label>
                                <select id="arch_selector" name="arch_selector" class="form-control">
                                    <option value="">Choose Arch Configuration...</option>
                                    <option value="upper">Upper Arch (Full)</option>
                                    <option value="lower">Lower Arch (Full)</option>
                                    <option value="both">Both Arches (Full)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group mt-4">
                            <label class="form-label font-bold">Lab Notes & Special Instructions</label>
                            <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Specify shade configuration, specific processing details..."></textarea>
                        </div>

                        <div class="flex flex-end gap-3 mt-6 pt-4 no-print" style="border-top:1px solid #eee;">
                            <a href="adm-lab.php" class="btn btn-ghost">Cancel & Return</a>
                            <button type="submit" class="btn btn-primary" id="btn-submit-form">
                                <i class="fa-solid fa-floppy-disk"></i> Commit Record Changes
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
        // 1. Structural security check routing
        App.auth.check();
        App.auth.role('admin');

        // Form structural state mappings
        let urlParams = new URLSearchParams(window.location.search);
        let editingRecordId = urlParams.get('id') || null;
        
        // Tracking variable to handle the context pricing rate dynamically
        let currentCasePrice = 0.00;

        // Enforce unified timestamp configuration upon new item lifecycle instantiations
        if (!editingRecordId) {
            let now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            let today = now.toISOString().slice(0, 10); // Standard YYYY-MM-DD
            $('#date_sent').val(today);
        }

        // Target mapping container placeholders for Select2 setup
        $('#patient-id').select2({ placeholder: "Choose Clinic Office Location First..." });

        /**
         * Request loop to pull patients filtered contextually by Selected Office
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
                        patientOptions += `<option value="${item.id}">${App.utils.escHtml(item.name)} (DOB: ${item.dob})</option>`;
                    });

                    $('#patient-id').html(patientOptions).prop('disabled', false).select2({ placeholder: "Search Patient..." });

                    if (callbackSelectedPatientId) {
                        $('#patient-id').val(callbackSelectedPatientId).trigger('change');
                    }
                },
                onError: function () {
                    App.toast.error("Failed to retrieve patients lookup context for the designated clinic office.");
                }
            });
        }

        /**
         * Request loop to pull providers contextually filtered by Selected Office mapping users table (user_type = doctor)
         */
        function loadProvidersByOffice(officeId, callbackSelectedDoctorId = null) {
            if (!officeId) {
                $('#doctor-id').html('<option value="">-- Please select an office first --</option>').prop('disabled', true);
                return;
            }

            App.ajax({
                url: '/adm-labs/get-providers-by-office.php',
                method: 'GET',
                data: { office_id: officeId },
                loader: true,
                onSuccess: function (res) {
                    let dataPool = res.data || res;
                    let doctorOptions = '<option value="">-- Choose Provider (Doctor) --</option>';

                    $.each(dataPool, function (i, item) {
                        doctorOptions += `<option value="${item.user_id}">Dr. ${App.utils.escHtml(item.name || item.full_name)}</option>`;
                    });

                    $('#doctor-id').html(doctorOptions).prop('disabled', false);

                    if (callbackSelectedDoctorId) {
                        $('#doctor-id').val(callbackSelectedDoctorId);
                    }
                },
                onError: function () {
                    App.toast.error("Failed to retrieve provider professionals for the designated clinic office.");
                }
            });
        }

        // Sync structural parameters cascades on Office change
        $('#office-id').on('change', function () {
            let currentOfficeVal = $(this).val();
            loadPatientsByOffice(currentOfficeVal);
            loadProvidersByOffice(currentOfficeVal);
        });

        /**
         * Contextual interface toggle loops handling requirement mapping structures
         */
        $('#case_type_id').on('change', function () {
            let selectedOption = $(this).find('option:selected');
            let requirement = selectedOption.data('requirement');
            
            // Track and lock case price field into the context tracking state
            currentCasePrice = parseFloat(selectedOption.data('price')) || 0.00;

            if (requirement === 'tooth') {
                $('#section-teeth').slideDown(200);
                $('#section-arch').slideUp(200);
            } else if (requirement === 'arch') {
                $('#section-arch').slideDown(200);
                $('#section-teeth').slideUp(200);
            } else if (requirement === 'both') {
                $('#section-teeth').slideDown(200);
                $('#section-arch').slideDown(200);
            } else {
                // Fallback baseline layout matrix adjustment
                $('#section-teeth').slideDown(200);
                $('#section-arch').slideUp(200);
            }
        });

        /**
         * Process individual interactive tooth nodes selection matrix
         */
        $('.tooth-btn').on('click', function () {
            $(this).toggleClass('selected');

            // Evaluate Upper Arch Selection arrays
            let upperTeeth = [];
            $('#upper-arch .tooth-btn.selected').each(function () {
                upperTeeth.push($(this).data('tooth'));
            });
            $('#u_arch_input').val(upperTeeth.join(','));

            // Evaluate Lower Arch Selection arrays
            let lowerTeeth = [];
            $('#lower-arch .tooth-btn.selected').each(function () {
                lowerTeeth.push($(this).data('tooth'));
            });
            $('#l_arch_input').val(lowerTeeth.join(','));
        });

        /**
         * Combined Dropdowns Aggregator Loader Function
         */
        function loadDropdowns() {
            App.ajax({
                url: '/adm-labs/admin-load-lab-dropdown.php', // Target endpoint pointing directly to unified loader
                method: 'GET',
                loader: false,
                onSuccess: function (res) {
                    var dataset = res.data || res;

                    // 1. Populate Offices Lookup Select Option Elements
                    var officeOptions = '<option value="">-- Select Office --</option>';
                    $.each(dataset.offices || [], function (i, o) {
                        officeOptions += `<option value="${o.id}">${App.utils.escHtml(o.name)}</option>`;
                    });
                    $('#office-id').html(officeOptions);

                    // 2. Populate Case Types & attach targeted mapping metadata requirement tags
                    var caseOptions = '<option value="">-- Select Case Type --</option>';
                    $.each(dataset.case_types || [], function (i, c) {
                        let targetRequirement = c.target || 'tooth';
                        let targetPrice = c.price || 0;
                        caseOptions += `<option value="${c.id}" data-requirement="${targetRequirement}" data-price="${targetPrice}">${App.utils.escHtml(c.name)} - $${App.utils.escHtml(c.price)}</option>`;
                    });
                    $('#case_type_id').html(caseOptions);

                    // 3. Populate Next Visit Steps Options Pipeline
                    var stepOptions = '<option value="">-- Select Procedure --</option>';
                    $.each(dataset.lab_steps || [], function (i, p) {
                        stepOptions += `<option value="${p.id}">${App.utils.escHtml(p.name)}</option>`;
                    });
                    $('#next_visit').html(stepOptions);

                    // 4. Populate Lab Partners Configuration Blocks
                    var providerOptions = '<option value="">-- Select Lab --</option>';
                    $.each(dataset.lab_partners || [], function (i, p) {
                        providerOptions += `<option value="${p.id}">${App.utils.escHtml(p.name)}</option>`;
                    });
                    $('#lab_provider').html(providerOptions);

                    // Check if we are running in update mode now that base datasets are populated
                    checkAndExecuteEditModeRuntime();
                },
                onError: function () {
                    App.toast.error("Failed to fetch prerequisite configuration datasets.");
                }
            });
        }

        /**
         * Hydration runtime configuration engine to parse existing structural files
         */
        function checkAndExecuteEditModeRuntime() {
            if (!editingRecordId) return;

            $(`#form-action-title`).text(`Edit Lab Case Record (#${editingRecordId})`);

            App.ajax({
                url: '/adm-labs/get-edit.php',
                method: 'GET',
                data: { id: editingRecordId },
                loader: true,
                onSuccess: function (res) {
                    
                    let record = res;

                    if (!record) {
                        App.toast.error("Target laboratory data stream returned empty or inaccessible.");
                        return;
                    }

                    // Map fields parameters data bindings
                    $('#office-id').val(record.office_id).trigger('change');
                    
                    // 1. Set the case type and trigger the frontend UI toggle natively!
                    $('#case_type_id').val(record.case_type_id).trigger('change');
                    
                    // Hydrate currentCasePrice from backend record if explicit price field is provided; falls back to default dropdown attribute logic
                    if (record.price !== undefined && record.price !== null) {
                        currentCasePrice = parseFloat(record.price) || 0.00;
                    }

                    $('#impression-type').val(record.impression_type);
                    $('#lab_provider').val(record.lab_provider);
                    $('#next_visit').val(record.next_visit);
                    $('#status').val(record.status);
                    $('#notes').val(record.notes || '');

                    // Map All Specific Date Tracking Columns Safely
                    if (record.date_sent && record.date_sent !== '0000-00-00') {
                        $('#date_sent').val(record.date_sent);
                    }
                    if (record.date_received && record.date_received !== '0000-00-00') {
                        $('#date_received').val(record.date_received);
                    }
                    if (record.date_scheduled && record.date_scheduled !== '0000-00-00') {
                        $('#date_scheduled').val(record.date_scheduled);
                    }
                    if (record.date_complete && record.date_complete !== '0000-00-00') {
                        $('#date_complete').val(record.date_complete);
                    }

                    // 2. Reverse-Engineer the frontend Arch Selector based on DB text
                    if (record.u_arch === 'Full' && record.l_arch === 'Full') {
                        $('#arch_selector').val('both');
                    } else if (record.u_arch === 'Full') {
                        $('#arch_selector').val('upper');
                    } else if (record.l_arch === 'Full') {
                        $('#arch_selector').val('lower');
                    } else {
                        $('#arch_selector').val('');
                    }

                    // 3. Hydrate Upper Arch (Only highlight teeth if the DB doesn't say 'Full')
                    if (record.u_arch && record.u_arch !== 'Full') {
                        let teeth = record.u_arch.split(',');
                        $.each(teeth, function (i, val) {
                            $(`#upper-arch .tooth-btn[data-tooth="${val.trim()}"]`).addClass('selected');
                        });
                        $('#u_arch_input').val(record.u_arch);
                    }

                    // 4. Hydrate Lower Arch (Only highlight teeth if the DB doesn't say 'Full')
                    if (record.l_arch && record.l_arch !== 'Full') {
                        let teeth = record.l_arch.split(',');
                        $.each(teeth, function (i, val) {
                            $(`#lower-arch .tooth-btn[data-tooth="${val.trim()}"]`).addClass('selected');
                        });
                        $('#l_arch_input').val(record.l_arch);
                    }

                    // Force asynchronous cascaded lookup configurations triggers
                    loadPatientsByOffice(record.office_id, record.patient_id);
                    loadProvidersByOffice(record.office_id, record.doctor_id);
                },
                onError: function () {
                    App.toast.error("Failed to recover designated prescription values stream targeting modifications.");
                }
            });
        }

        /**
         * Catch interception submission runtime logic loop mapping configurations
         */
        $('#lab-admin-form').on('submit', function (e) {
            e.preventDefault();

            $('.error-highlight').removeClass('error-highlight');

            let isValid = true;
            let formsFields = document.getElementById('lab-admin-form').querySelectorAll('[required]');

            formsFields.forEach(field => {
                if (field.disabled) return;
                if (!field.value.trim()) {
                    isValid = false;
                    $(field).addClass('error-highlight');
                    if ($(field).hasClass('select2-hidden-accessible')) {
                        $(field).next('.select2-container').find('.select2-selection').addClass('error-highlight');
                    }
                }
            });

            if (!isValid) {
                App.toast.error("Please fill in all required laboratory operational parameter cells.");
                return;
            }

            // Collate payload structure map properties fields
            let dynamicPayload = {
                patient_id: $('#patient-id').val(),
                office_id: $('#office-id').val(),
                doctor_id: $('#doctor-id').val(),
                case_type_id: $('#case_type_id').val(),
                impression_type: $('#impression-type').val(),
                u_arch: $('#u_arch_input').val() || null,
                l_arch: $('#l_arch_input').val() || null,
                arch_selector: $('#arch_selector').val() || null,
                lab_provider: $('#lab_provider').val(),
                next_visit: $('#next_visit').val(),
                notes: $('#notes').val(),
                status: $('#status').val(),
                
                // Explicitly bundle the isolated state context price into out-bound API arrays
                price: currentCasePrice,
                
                // Specific Custom Date Bindings
                date_sent: $('#date_sent').val() || null,
                date_received: $('#date_received').val() || null,
                date_scheduled: $('#date_scheduled').val() || null,
                date_complete: $('#date_complete').val() || null
            };

            let requestUrl = '/adm-labs/create.php';
            if (editingRecordId) {
                dynamicPayload.id = editingRecordId;
                requestUrl = '/adm-labs/update.php';
            }

            App.ajax({
                url: requestUrl,
                method: 'POST',
                data: dynamicPayload,
                onSuccess: function (data) {
                    App.toast.success(data.message || "Laboratory transaction parameters committed successfully.");
                    setTimeout(function () {
                        window.location.href = '/adm-lab-monitor.php';
                    }, 1000);
                },
                onError: function (xhr) {
                    let errorMsg = xhr.responseJSON?.message || "An administrative update transaction exception occurred.";
                    App.toast.error(errorMsg);
                }
            });
        });

        // Trigger initialization cascade runtime operations
        loadDropdowns();
    });
</script>
</body>

</html>