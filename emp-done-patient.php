<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Scheduled Appointments</title>
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
                        <h1>Manage Appointments</h1>
                        <div class="page-header-sub">Finalize treatment for scheduled patients or reschedule as needed.</div>
                    </div>
                </div>

                <div class="table-wrapper">
                    <table class="data-table" id="manage-appointments-table">
                        <thead>
                            <tr>
                                <th>Case NO </th>
                                <th>Patient Name</th>
                                <th>Appointment Date</th>
                                <th>Procedure</th>
                                <th>Insurance</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="manage-appointments-tbody">
                            <tr>
                                <td colspan="6">
                                    <div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading Scheduled Appointments...</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="table-pagination">
                        <span id="manage-info" class="text-muted text-sm">—</span>
                        <div class="pagination" id="manage-pagination-btns"></div>
                    </div>
                </div>

                <!-- ============================================================
                     VIEW DETAILS MODAL
                ============================================================ -->
                <div class="modal-backdrop" id="view-modal">
                    <div class="modal modal-lg">
                        <div class="modal-header">
                            <div class="modal-title">Appointment Lifecycle Details</div>
                            <button class="modal-close" data-close-modal="view-modal">&#x2715;</button>
                        </div>
                        <div class="modal-body">
                           <div id="view-preauth-body">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-ghost" data-close-modal="view-modal">Close Window</button>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                     CONFIRM COMPLETION MODAL
                ============================================================ -->
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
                            <p>Are you sure you want to mark this procedure as <strong>Completed</strong>?</p>
                            <p class="text-muted text-sm">This will finalize the patient journey for this pre-authorization.</p>
                            <input type="hidden" id="complete-preauth-id">
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-ghost" data-close-modal="complete-modal">No, Cancel</button>
                            <button class="btn btn-primary" id="btn-confirm-complete">
                                <i class="fa-solid fa-check-double"></i> Yes, Procedure Done
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                     CONFIRM RESCHEDULE MODAL
                ============================================================ -->
                <div class="modal-backdrop" id="reschedule-modal">
                    <div class="modal modal-sm">
                        <div class="modal-header">
                            <div class="modal-title">Reschedule Patient</div>
                            <button class="modal-close" data-close-modal="reschedule-modal">&#x2715;</button>
                        </div>
                        <div class="modal-body text-center">
                            <div class="mb-4">
                                <i class="fa-solid fa-calendar-minus text-warning" style="font-size: 3rem;"></i>
                            </div>
                            <p>Are you sure you want to <strong>Reschedule</strong> this patient?</p>
                            <p class="text-muted text-sm">This will clear the current appointment date and move the record back to the "Book Appointments" list.</p>
                            <input type="hidden" id="reschedule-preauth-id">
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-ghost" data-close-modal="reschedule-modal">Cancel</button>
                            <button class="btn btn-warning" id="btn-confirm-reschedule">
                                <i class="fa-solid fa-rotate-left"></i> Confirm Reschedule
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/emp-manage-appointments.js"></script>
    <script>
        $(document).ready(function () {
            App.auth.check();
            App.auth.role(['staff' , 'doctor']);

        });
    </script>
</body>
</html>