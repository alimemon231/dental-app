<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pre-Auth Requests Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/layout.css">
    <style>
        /* ================================================================
    TABLE CONTROL HEADER ACTIONS STYLING MATRIX
================================================================ */
        .table-controls-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            padding: var(--sp-4) var(--sp-5);
            border-radius: var(--radius-md) var(--radius-md) 0 0;
            border: 1px solid #e2e8f0;
            border-bottom: none;
            margin-top: var(--sp-4);
        }

        .table-controls-container .section-title {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
        }

        /* Custom Interactive Micro-Control Buttons Definition Layout */
        .btn-refresh-control {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #f8fafc;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 7px 14px;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-refresh-control:hover {
            background: #f1f5f9;
            color: var(--color-primary, #2563eb);
            border-color: #94a3b8;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .btn-refresh-control:active {
            transform: scale(0.97);
            background: #e2e8f0;
        }

        .btn-refresh-control i {
            font-size: 0.9rem;
            transition: transform 0.15s ease;
        }
    </style>
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

                </div>

                <div class="table-controls-container">
                    <div class="controls-right">
                        <button type="button" id="btn-refresh-table" class="btn-refresh-control"
                            title="Hot Reload Live Records Pipeline">
                            <i class="fa-solid fa-rotate"></i> <span>Refresh Data</span>
                        </button>
                    </div>
                </div>

                <div class="table-wrapper">
                    <table class="data-table" id="m-preauth-table">
                        <thead>
                            <tr>
                                <th>Case NO</th>
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


                <div class="modal-backdrop" id="confirm-modal">
                    <div class="modal modal-sm">
                        <div class="modal-header">
                            <div class="modal-title" id="confirm-title">Confirm Action</div>
                            <button class="modal-close" data-close-modal="confirm-modal">&#x2715;</button>
                        </div>
                        <div class="modal-body">
                            <div id="confirm-body-content"></div>

                            <div id="approval-expiry-container" class="mt-4 p-3 bg-light border-radius-sm"
                                style="display:none; border: 1px solid #ddd;">
                                <label class="form-label font-bold text-xs mb-2 d-block">SET APPROVAL EXPIRY
                                    DATE</label>
                                <input type="date" id="approval-expiry-date" class="form-control">
                                <small class="text-muted d-block mt-1">This date will be saved with the approval
                                    record.</small>
                            </div>

                            <div id="rejection-notes-container" class="mt-4 p-3 bg-light border-radius-sm"
                                style="display:none; border: 1px solid #ddd;">
                                <label class="form-label font-bold text-xs mb-2 d-block text-danger">REJECTION NOTES
                                    (OPTIONAL)</label>
                                <textarea id="rejection-notes" class="form-control" rows="3"
                                    placeholder="Provide details/reasoning for the staff member..."></textarea>
                                <small class="text-muted d-block mt-1">These notes will be displayed to staff explaining
                                    the denial reason.</small>
                            </div>
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