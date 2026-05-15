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

                <div class="table-wrapper">
                    <table class="data-table" id="lab-table">
                        <thead>
                            <tr>
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
                                <td colspan="7" class="text-center">Loading lab cases...</td>
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
                            <form id="lab-form">
                                <div class="form-section">
                                    <div class="form-section-title"><i class="fa-solid fa-user"></i> General Information
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label class="form-label">Patient Name <span
                                                    class="required">*</span></label>
                                            <input type="text" name="patient_name" class="form-control"
                                                placeholder="Full Name" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Provider (Doctor) <span
                                                    class="required">*</span></label>
                                            <select name="doctor_id" id="doctor_id" class="form-control" required>
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
                                            <label class="form-label">Impression Type <span
                                                    class="required">*</span></label>
                                            <select name="impression_type" class="form-control" required>
                                                <option value="Scan">Digital Scan</option>
                                                <option value="VPS">VPS / Manual</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div id="section-teeth" class="hidden-field mt-4">
                                        <div class="form-section-title"><i class="fa-solid fa-tooth"></i> Select Teeth
                                        </div>
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
                                        <div class="form-section-title"><i class="fa-solid fa-grip-lines"></i> Arch
                                            Selection</div>
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
                                            <textarea name="notes" class="form-control" rows="2"
                                                placeholder="Shade, specific instructions..."></textarea>
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
                        <div class="modal-body" id="view-lab-body">
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-ghost" data-close-modal="view-lab-modal">Close</button>
                            <button class="btn btn-primary btn-edit" id="btn-edit-from-view" data-id="">
                                <i class="fa-solid fa-pen-to-square"></i> Edit Case
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Generic Confirmation Modal for Approvals/Rejections -->
                <div class="modal-backdrop" id="confirm-modal">
                    <div class="modal modal-sm">
                        <div class="modal-header">
                            <div class="modal-title" id="confirm-title">Confirm Action</div>
                            <button class="modal-close" data-close-modal="confirm-modal">&#x2715;</button>
                        </div>
                        <div class="modal-body">
                            <!-- The dynamic icon and message will be injected here -->
                            <div id="confirm-body-content"></div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-ghost" data-close-modal="confirm-modal"
                                id="confirm-cancel">Cancel</button>
                            <button class="btn" id="confirm-ok">Proceed</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/emp-lab.js"></script>
</body>

</html>