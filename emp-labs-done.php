<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalize Lab Procedures</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/layout.css">
</head>

<body>
    <div class="app-shell">
        <?php
        $pageTitle = "Finalize Lab Cases";
        $activePage = "labs_done";
        require_once "includes/page-header.php";
        ?>
        <main class="main-content">
            <div class="page-wrapper">
                <div class="page-header">
                    <div class="page-header-left">
                        <h1>Finalize Lab Procedures</h1>
                        <div class="page-header-sub">Mark scheduled lab cases as completed once the procedure is
                            finished.</div>
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
                    <table class="data-table" id="labs-done-table">
                        <thead>
                            <tr>
                                <th>Lab Case NO#</th>
                                <th>Patient Name</th>
                                <th>Scheduled Date</th>
                                <th>Doctor</th>
                                <th>Case Type</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="labs-done-tbody">
                            <tr>
                                <td colspan="6">
                                    <div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading
                                        Scheduled Cases...</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="modal-backdrop" id="view-modal">
                    <div class="modal modal-lg">
                        <div class="modal-header">
                            <div class="modal-title">Lab Case Details</div>
                            <button class="modal-close" data-close-modal="view-modal">&#x2715;</button>
                        </div>
                        <div class="modal-body" id="view-details-body">Loading…</div>
                        <div class="modal-footer">
                            <button class="btn btn-ghost" data-close-modal="view-modal">Close</button>
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
                            <button class="btn btn-primary btn-mark-done" id="btn-edit-from-view" data-id="">
                                <i class="fa-solid fa-pen-to-square"></i> Mark Complete
                            </button>
                        </div>
                    </div>
                </div>


                <div class="modal-backdrop" id="complete-modal">
                    <div class="modal modal-sm">
                        <div class="modal-header">
                            <div class="modal-title">Mark Procedure Done</div>
                            <button class="modal-close" data-close-modal="complete-modal">&#x2715;</button>
                        </div>
                        <div class="modal-body text-center">
                            <div class="mb-4">
                                <i class="fa-solid fa-circle-check text-success" style="font-size: 3rem;"></i>
                            </div>
                            <p>Are you sure the procedure for <strong id="complete-patient-name"></strong> is
                                <strong>Finished</strong>?</p>
                            <p class="text-muted text-sm">This will mark the lab case as "Done" and record today's date
                                as the completion date.</p>
                            <input type="hidden" id="complete-lab-id">
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-ghost" data-close-modal="complete-modal">Cancel</button>
                            <button class="btn btn-primary" id="btn-confirm-done">
                                <i class="fa-solid fa-check-double"></i> Yes, Procedure Done
                            </button>
                        </div>
                    </div>
                </div>

                <div class="modal-backdrop" id="reschedule-modal">
                    <div class="modal modal-sm">
                        <div class="modal-header">
                            <div class="modal-title">Reschedule Lab Appointment</div>
                            <button class="modal-close" data-close-modal="reschedule-modal">&#x2715;</button>
                        </div>
                        <div class="modal-body">
                            <div class="text-center mb-4">
                                <i class="fa-solid fa-calendar-days text-danger" style="font-size: 3rem;"></i>
                                <p class="mt-2">Select a new treatment timeline deployment date for <strong
                                        id="reschedule-patient-name"></strong>.</p>
                            </div>

                            <div class="form-group">
                                <label for="reschedule-date" class="form-label text-sm text-muted">New Scheduled Date &
                                    Time</label>
                                <input type="date" id="reschedule-date" class="form-control" required>
                            </div>

                            <input type="hidden" id="reschedule-lab-id">
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-ghost" data-close-modal="reschedule-modal">Cancel</button>
                            <button class="btn btn-danger" id="btn-confirm-reschedule">
                                <i class="fa-solid fa-clock"></i> Save New Schedule
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/emp-labs-done.js"></script>
    <script>
        $(document).ready(function () {
            App.auth.check();
            App.auth.role(['staff', 'doctor']);
        });
    </script>
</body>

</html>