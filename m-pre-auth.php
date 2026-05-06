<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pre-Auth Requests Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/layout.css">
</head>

<body>
    <div class="app-shell">
        <?php
        $pageTitle = "Pre-Auth Requests";
        $activePage = "m_pre_auth";
        require_once "includes/page-header.php";
        ?>
        <main class="main-content">
            <div class="page-wrapper">
                <div class="page-header">
                    <div class="page-header-left">
                        <h1>Pre-Auth Requests (All Clinics)</h1>
                        <div class="page-header-sub">Review, Accept, or Reject pending authorizations from all
                            locations.</div>
                    </div>
                    <!-- Action button removed as m-staff only reviews -->
                </div>

                <div class="table-wrapper">
                    <table class="data-table" id="m-preauth-table">
                        <thead>
                            <tr>
                                <th>Clinic</th>
                                <th>Patient Name</th>
                                <th>Insurance Plan</th>
                                <th>Treatment</th>
                                <th>Created By</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="m-preauth-tbody">
                            <tr>
                                <td colspan="7">
                                    <div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading
                                        Requests…</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="table-pagination" id="preauth-pagination">
                        <span id="preauth-info" class="text-muted text-sm">—</span>
                        <div class="pagination" id="pagination-btns"></div>
                    </div>
                </div>

                <!-- VIEW MODAL (Same structure, JS fills the details) -->
                <div class="modal-backdrop" id="view-preauth-modal">
                    <div class="modal modal-lg">
                        <div class="modal-header">
                            <div class="modal-title">Request Review Details</div>
                            <button class="modal-close" data-close-modal="view-preauth-modal">&#x2715;</button>
                        </div>
                        <div class="modal-body" id="view-preauth-body">Loading…</div>
                        <div class="modal-footer">
                            <button class="btn btn-ghost" data-close-modal="view-preauth-modal">Close</button>
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

    <div id="toast-container"></div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="assets/js/app.js"></script>
    <!-- We will use a specific JS file for management staff logic -->
    <script src="assets/js/m-pre-auth.js"></script>
    <script>
        $(document).ready(function () {
            App.auth.check();
            App.auth.role('m-staff');
        });
    </script>
</body>

</html>