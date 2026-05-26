<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Lab Case Monitor</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/layout.css">
    <link rel="stylesheet" href="assets/css/lab-monitor-custom.css">
    <style>
        /* Progress Bar Styles */
        .progress-track {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
            padding: 0 20px;
            height: 80px;
            background-color: #fff !important;
        }

        .progress-track::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 50px;
            right: 50px;
            height: 3px;
            z-index: 1;
            background: #2b8a3e;
        }

        .step {
            position: relative;
            z-index: 2;
            text-align: center;
            width: 100px;
        }

        .step-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #fff;
            border: 3px solid #eee;
            margin: 0 auto 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: #ccc;
            transition: 0.3s;
        }

        .step.active .step-icon {
            border-color: var(--primary);
            color: var(--primary);
        }

        .step.completed .step-icon {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }

        .step-label {
            font-size: 12px;
            font-weight: 600;
            color: #999;
        }

        .step.active .step-label,
        .step.completed .step-label {
            color: var(--text-main);
        }

        /* Status Table Cell Helpers */
        .stage-cell {
            text-align: center;
            font-size: 0.85rem;
            padding: 12px 8px !important;
            transition: 0.2s;
        }

        .stage-pending {
            background-color: #eddc87 !important;
            color: #8a6d3b;
        }

        /* Yellow */
        .stage-success {
            background-color: #6ef787 !important;
            color: #2b8a3e;
            font-weight: 600;
        }

        /* Green */
        .stage-danger {
            background-color: #ee5050 !important;
            color: #fff !important;
        }

        /* Red */
        .step.completed .step-icon {
            background-color: #6ef787;
        }

        .step.active .step-icon {
            background-color: #6ef787;
        }

        .step.danger .step-icon {
            background-color: #ee5050;
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
                        <h1>Lab History</h1>
                        <div class="page-header-sub">Advanced tracking for clinical lab cases across all locations.
                        </div>
                    </div>

                    <a href="/adm-create-lab.php" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-plus"></i> Add New Record
                    </a>
                </div>

                <div class="card mb-6 no-print">
                    <div class="grid-4 gap-4">
                        <div>
                            <label class="form-label">Patient Name</label>
                            <input type="text" class="form-control" id="filter-patient" placeholder="Search Patient...">
                        </div>
                        <div>
                            <label class="form-label">Clinic Location</label>
                            <select class="form-control" id="filter-clinic">
                                <option value="">All Clinics</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Doctor / Provider</label>
                            <select class="form-control" id="filter-provider">
                                <option value="">All Providers</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Lab Case Type</label>
                            <select class="form-control" id="filter-lab-type">
                                <option value="">All Types</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Sent From</label>
                            <input type="date" id="filter-start-date" class="form-control">
                        </div>
                        <div>
                            <label class="form-label">Sent To</label>
                            <input type="date" id="filter-end-date" class="form-control">
                        </div>

                        <div style="grid-column: span 2">
                            <label class="form-label">Status Filter</label>
                            <div class="custom-multiselect" style="position:relative;">
                                <div class="form-control" id="status-display"
                                    style="cursor:pointer; display:flex; justify-content:space-between; align-items:center;">
                                    <span class="text-muted">Select Multiple Statuses</span>
                                    <i class="fa-solid fa-chevron-down"></i>
                                </div>
                                <div id="status-options" class="card shadow-sm"
                                    style="display:none; position:absolute; top:100%; left:0; width:100%; z-index:100; padding:10px; max-height:250px; overflow-y:auto; background: #fff;">
                                    <label class="flex flex-align gap-2 mb-2"><input type="checkbox"
                                            class="status-checkbox" value="Sent"> Sent to Lab</label>
                                    <label class="flex flex-align gap-2 mb-2"><input type="checkbox"
                                            class="status-checkbox" value="Received"> Received at Clinic</label>
                                    <label class="flex flex-align gap-2 mb-2"><input type="checkbox"
                                            class="status-checkbox" value="Scheduled"> Scheduled with Patient</label>
                                    <label class="flex flex-align gap-2 mb-0"><input type="checkbox"
                                            class="status-checkbox" value="Done"> Procedure Completed</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-justify-end gap-2 mt-4">
                        <button class="btn btn-ghost btn-sm" id="btn-clear"><i class="fa-solid fa-xmark"></i>
                            Clear</button>
                        <button class="btn btn-success btn-sm" id="btn-print">
                            <i class="fa-solid fa-print"></i> Print Report
                        </button>
                        <button class="btn btn-primary btn-sm" id="btn-search"><i
                                class="fa-solid fa-magnifying-glass"></i> Filter Results</button>
                    </div>
                </div>

                <div class="grid-2 mb-4">
                    <div class="sticky-side" id="card-pipeline-container">
                        <div class="form-section-title mb-4">
                            <i class="fa-solid fa-vial-circle-check"></i> <span>Case Completion</span>
                        </div>
                        <div id="card-pipeline-content">
                            <div class="text-center text-muted p-3">-- Loading Stats --</div>
                        </div>
                    </div>

                    <div class="sticky-side" id="card-efficiency-container">
                        <div class="form-section-title mb-4">
                            <i class="fa-solid fa-calendar-check"></i> <span>Booking Rate</span>
                        </div>
                        <div id="card-efficiency-content">
                            <div class="text-center text-muted p-3">-- Loading Stats --</div>
                        </div>
                    </div>
                </div>

                <div class="table-wrapper">
                    <table class="data-table" id="lab-monitor-table">
                        <thead>
                            <tr>
                                <th style="min-width: 180px;">Patient & Provider</th>
                                <th class="text-center">1. Sent</th>
                                <th class="text-center">2. Arrival</th>
                                <th class="text-center">3. Booked</th>
                                <th class="text-center">4. Finished</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody id="lab-monitor-tbody">
                        </tbody>
                    </table>
                </div>

                <div class="modal-backdrop" id="view-modal">
                    <div class="modal modal-lg">
                        <div class="modal-header">
                            <div class="modal-title">Pre-Auth Lifecycle</div>
                            <button class="modal-close" data-close-modal="view-modal">&#x2715;</button>
                        </div>
                        <div class="modal-body">
                            <div id="view-details-body"></div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-ghost" data-close-modal="view-modal">Close Window</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/adm-lab-monitor.js"></script>
</body>

</html>