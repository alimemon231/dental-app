<?php
require_once "includes/Auth.php";
// Simple helper to check auth for this page specifically if needed
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Case Management</title>
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

        /* Form Logic Classes */
        .hidden-field {
            display: none;
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
                        <h1>Lab Case Manager</h1>
                        <div class="page-header-sub">Submit and track laboratory prescriptions.</div>
                    </div>
                    <div class="page-header-actions">
                        <button class="btn btn-primary" id="btn-add-lab">
                            <i class="fa-solid fa-flask"></i> New Lab Case
                        </button>
                    </div>
                </div>

                <div class="table-controls-container">
                    <form id="admin-filter-form" class="filter-grid-layout" onsubmit="return false;"
                        style="width: 100%; display: flex; flex-wrap: wrap; gap: var(--sp-4); align-items: flex-end;">

                        <div class="filter-group">
                            <label class="filter-label">Patient Name</label>
                            <input type="text" id="filter-patient-name" class="form-control"
                                placeholder="Search patient...">
                        </div>


                        <div class="filter-group">
                            <label class="filter-label">Pipeline Status</label>
                            <select id="filter-status" class="form-control">
                                <option value="">All Statuses</option>
                                <option value="Sent">Sent</option>
                                <option value="">Received</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Lab Case No.</label>
                            <input type="number" id="filter-case-id" class="form-control" placeholder="e.g. 280"
                                min="1">
                        </div>

                        <button type="button" id="btn-filter-table" class="btn btn-primary btn-filter"
                            title="Apply Pipeline Filters">
                            <i class="fa-solid fa-filter"></i> <span>Filter</span>
                        </button>

                    </form>
                </div>

                <div class="table-wrapper" style="margin-top:20px;">
                    <table class="data-table" id="lab-table">
                        <thead>
                            <tr>
                                <th>Lab Case Number</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Case Type</th>
                                <th>Impression</th>
                                <th>Next Visit</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="lab-tbody">
                            <tr>
                                <td colspan="8" class="text-center">Loading lab cases...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="modal-backdrop" id="lab-modal">
                    <div class="modal modal-lg">
                        <div class="modal-header">
                            <div class="modal-title">New Lab Prescription</div>
                            <button class="modal-close" data-close-modal="lab-modal">&#x2715;</button>
                        </div>
                        <div class="modal-body">
                            <form id="lab-form" novalidate>
                                <div class="form-section">
                                    <div class="form-section-title"><i class="fa-solid fa-user"></i> General Information</div>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label class="form-label">Select Patient Record <span class="required">*</span></label>
                                            <select name="patient_id" id="patient-select" class="form-control" required style="width: 100%;">
                                                <option value="">Search by patient name...</option>
                                            </select>
                                            <span class="form-error">Please designate a valid patient profile.</span>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Provider (Doctor) <span class="required">*</span></label>
                                            <select name="doctor_id" id="doctor_id" class="form-control" required style="width: 100%;">
                                                <option value="">Select Doctor</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label class="form-label">Case Type <span class="required">*</span></label>
                                            <select name="case_type_id" id="case_type_id" class="form-control" required>
                                                <option value="">Select Case Type</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Impression Type <span class="required">*</span></label>
                                            <select name="impression_type" class="form-control" required>
                                                <option value="Scan">Digital Scan</option>
                                                <option value="VPS">VPS / Manual</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div id="section-teeth" class="hidden-field mt-4">
                                        <div class="form-section-title"><i class="fa-solid fa-tooth"></i> Select Teeth</div>
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

                                    <div id="section-arch" class="hidden-field mt-4">
                                        <div class="form-section-title"><i class="fa-solid fa-grip-lines"></i> Arch Selection</div>
                                        <div class="form-group">
                                            <label class="form-label">Target Arch</label>
                                            <select id="arch_selector" class="form-control">
                                                <option value="">Choose...</option>
                                                <option value="upper">Upper Arch (Full)</option>
                                                <option value="lower">Lower Arch (Full)</option>
                                                <option value="both">Both Arches (Full)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-row mt-4">
                                        <div class="form-group">
                                            <label class="form-label">Select Lab<span class="required">*</span></label>
                                            <select name="lab_provider" id="lab_provider" class="form-control" required>
                                                <option value="">-- Select Lab --</option>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label">Next Visit <span class="required">*</span></label>
                                            <select name="next_visit" id="next_visit" class="form-control" required>
                                                <option value="">-- Select Next Step --</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-row mt-4" style="grid-template-columns: repeat(1, 1fr);">
                                        <div class="form-group">
                                            <label class="form-label">Lab Notes</label>
                                            <textarea name="notes" class="form-control" rows="2" placeholder="Shade, specific instructions..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-ghost" data-close-modal="lab-modal">Cancel</button>
                            <button class="btn btn-primary" id="btn-save-lab">Submit to Lab</button>
                        </div>
                    </div>
                </div>

                <div class="modal-backdrop" id="view-lab-modal">
                    <div class="modal modal-lg">
                        <div class="modal-header">
                            <div class="modal-title">Lab Case Details</div>
                            <button class="modal-close" data-close-modal="view-lab-modal">&#x2715;</button>
                        </div>
                        <div class="modal-body" id="view-lab-body"></div>
                        <div class="modal-footer">
                            <button class="btn btn-ghost" data-close-modal="view-lab-modal">Close</button>
                            <button class="btn btn-primary btn-edit" id="btn-edit-from-view" data-id="">
                                <i class="fa-solid fa-pen-to-square"></i> Edit Case
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
                            <button class="btn btn-ghost" data-close-modal="confirm-modal" id="confirm-cancel">Cancel</button>
                            <button class="btn" id="confirm-ok">Proceed</button>
                        </div>
                    </div>
                </div>

                  <div class="modal-backdrop" id="schedule-modal">
                    <div class="modal modal-sm">
                        <div class="modal-header">
                            <div class="modal-title">Schedule Lab Appointment</div>
                            <button class="modal-close" data-close-modal="schedule-modal">&#x2715;</button>
                        </div>
                        <div class="modal-body">
                            <form id="schedule-form" novalidate>
                                <input type="hidden" name="id" id="schedule-lab-id">
                                
                                <div class="form-group">
                                    <label class="form-label">Appointment Date <span class="required">*</span></label>
                                    <input type="date" name="appointment_date" id="appointment_date" class="form-control" required>
                                    <span class="form-error">Please select a valid date.</span>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-ghost" data-close-modal="schedule-modal">Cancel</button>
                            <button class="btn btn-primary" id="btn-confirm-schedule">
                                <i class="fa-solid fa-calendar-check"></i> Save Schedule
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/emp-lab.js"></script>
</body>

</html>