<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global Lab Case Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/layout.css">
</head>

<body>
    <div class="app-shell">
        <?php
        $pageTitle = "Global Lab Cases";
        $activePage = "m_labs";
        require_once "includes/page-header.php";
        ?>
        <main class="main-content">
            <div class="page-wrapper">
                <div class="page-header">
                    <div class="page-header-left">
                        <h1>Global Lab Management</h1>
                        <div class="page-header-sub">Track and schedule appointments for lab cases across all clinic locations.</div>
                    </div>
                </div>

                <div class="table-wrapper">
                    <table class="data-table" id="m-labs-table">
                        <thead>
                            <tr>
                                <th>Clinic</th>
                                <th>Patient Name</th>
                                <th>Doctor</th>
                                <th>Case Type</th>
                                <th>Sent Date</th>
                                <th>Received Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="m-labs-tbody">
                            <tr>
                                <td colspan="7">
                                    <div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading Global Records…</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="table-pagination" id="labs-pagination">
                        <span id="labs-info" class="text-muted text-sm">—</span>
                        <div class="pagination" id="pagination-btns"></div>
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

                <div class="modal-backdrop" id="view-modal">
                    <div class="modal modal-lg">
                        <div class="modal-header">
                            <div class="modal-title">Lab Case Review</div>
                            <button class="modal-close" data-close-modal="view-modal">&#x2715;</button>
                        </div>
                        <div class="modal-body" id="view-details-body">Loading…</div>
                        <div class="modal-footer">
                            <button class="btn btn-ghost" data-close-modal="view-modal">Close</button>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <div id="toast-container"></div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/m-labs.js"></script>
    <script>
        $(document).ready(function () {
            App.auth.check();
            App.auth.role('m-staff');
        });
    </script>
</body>
</html>