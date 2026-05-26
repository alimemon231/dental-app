<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Patients — Dental App</title>
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
                        <h1>Manage Patients</h1>
                        <div class="page-header-sub">Manage all clinical patients here.</div>
                    </div>
                    <div class="page-header-actions">
                        <button class="btn btn-primary" id="btn-add-patient">
                            <i class="fa-solid fa-user-plus"></i> Add Patient
                        </button>
                    </div>
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

                        <div class="flex flex-justify-end gap-2 mt-4">
                            <button class="btn btn-ghost btn-sm" id="btn-clear"><i class="fa-solid fa-xmark"></i>
                                Clear</button>

                            <button class="btn btn-primary btn-sm" id="btn-search"><i
                                    class="fa-solid fa-magnifying-glass"></i> Filter Results</button>
                        </div>

                    </div>

                </div>

                <div class="table-wrapper">
                    <table class="data-table" id="patients-table">
                        <thead>
                            <tr>
                                <th class="sortable" data-col="id">#</th>
                                <th class="sortable" data-col="name">Patient Name</th>
                                <th>DOB</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Assigned Office</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="patients-tbody">
                            <tr>
                                <td colspan="7">
                                    <div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading
                                        patients…</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="table-pagination" id="patients-pagination">
                        <span id="patients-info" class="text-muted text-sm">—</span>
                        <div class="pagination" id="pagination-btns"></div>
                    </div>
                </div>

                <div class="modal-backdrop" id="patient-modal">
                    <div class="modal modal-lg">
                        <div class="modal-header">
                            <div class="modal-title" id="patient-modal-title">Add New Patient</div>
                            <button class="modal-close" data-close-modal="patient-modal">&#x2715;</button>
                        </div>
                        <div class="modal-body">
                            <form id="patient-form" autocomplete="off" novalidate>
                                <input type="hidden" name="patient_id" id="patient-id" value="">

                                <div class="form-section">
                                    <div class="form-section-title"><i class="fa-solid fa-id-card"></i> Patient Profile
                                        Information</div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label class="form-label">Patient Name <span
                                                    class="required">*</span></label>
                                            <input type="text" name="name" id="name" class="form-control"
                                                placeholder="e.g. Jane Doe" required>
                                            <span class="form-error">Patient name is required.</span>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Date of Birth <span
                                                    class="required">*</span></label>
                                            <input type="date" name="dob" id="dob" class="form-control" required>
                                            <span class="form-error">Date of birth is required.</span>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label class="form-label">Phone Number <span
                                                    class="required">*</span></label>
                                            <input type="tel" name="phone" id="phone" class="form-control"
                                                placeholder="e.g. +1 (555) 000-0000" required>
                                            <span class="form-error">Phone number is required.</span>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Email Address </label>
                                            <input type="email" name="email" id="email" class="form-control"
                                                placeholder="e.g. jane.doe@example.com">
                                            <span class="form-error">Valid email address is required.</span>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label class="form-label">Primary Office Location </label>
                                            <select name="office_id" id="office_id" class="form-control">
                                                <option value="">-- Select Assigned Office --</option>
                                            </select>
                                            <span class="form-error">Please choose an assigned office
                                                destination.</span>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Full Residential Address <span
                                                    class="required">*</span></label>
                                            <input type="text" name="address" id="address" class="form-control"
                                                placeholder="e.g. Apt 4B, 123 Main Street, New York" required>
                                            <span class="form-error">Residential address is required.</span>
                                        </div>
                                    </div>

                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-ghost" data-close-modal="patient-modal">Cancel</button>
                            <button class="btn btn-primary" id="btn-save-patient">
                                <span class="btn-spinner"></span>
                                <span class="btn-text">Save Patient</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="modal-backdrop" id="view-patient-modal">
                    <div class="modal modal-lg">
                        <div class="modal-header">
                            <div class="modal-title">Patient Details</div>
                            <button class="modal-close" data-close-modal="view-patient-modal">&#x2715;</button>
                        </div>
                        <div class="modal-body" id="view-patient-body">
                            Loading details…
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-ghost" data-close-modal="view-patient-modal">Close</button>
                            <button class="btn btn-primary" id="btn-edit-from-view">
                                <i class="fa-solid fa-pen"></i> Edit Profile
                            </button>
                        </div>
                    </div>
                </div>

                <div class="modal-backdrop" id="view-history-modal">
                    <div class="modal modal-xl" style="max-width: 1000px;">
                        <div class="modal-header">
                            <div class="modal-title"><i class="fa-solid fa-clock-rotate-left"></i> Comprehensive Patient
                                History</div>
                            <button class="modal-close" data-close-modal="view-history-modal">&#x2715;</button>
                        </div>
                        <div class="modal-body" style="background-color: #f8fafc; padding: var(--sp-6);">

                            <div class="grid-2" style="gap: var(--sp-6);">
                                <div>
                                    <div class="form-section-title mb-4"
                                        style="color: var(--color-primary); border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                                        <i class="fa-solid fa-shield-halved"></i> Pre-Authorization History
                                    </div>
                                    <div id="history-preauth-container"
                                        style="max-height: 500px; overflow-y: auto; padding-right: 5px;">
                                        <div class="text-muted text-center p-4"><i
                                                class="fa-solid fa-spinner fa-spin"></i> Loading pre-auth records...
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <div class="form-section-title mb-4"
                                        style="color: var(--color-primary); border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                                        <i class="fa-solid fa-vial"></i> Clinical Lab Cases
                                    </div>
                                    <div id="history-labs-container"
                                        style="max-height: 500px; overflow-y: auto; padding-right: 5px;">
                                        <div class="text-muted text-center p-4"><i
                                                class="fa-solid fa-spinner fa-spin"></i> Loading lab cases...</div>
                                    </div>
                                </div>
                            </div>

                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-ghost" data-close-modal="view-history-modal">Close History</button>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <div class="modal-backdrop" id="confirm-modal">
        <div class="modal modal-sm">
            <div class="modal-header">
                <div class="modal-title">Confirm Action</div>
                <button class="modal-close" data-close-modal="confirm-modal">&#x2715;</button>
            </div>
            <div class="modal-body">
                <p class="confirm-message">Are you sure?</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" id="confirm-cancel">Cancel</button>
                <button class="btn btn-danger" id="confirm-ok">Confirm</button>
            </div>
        </div>
    </div>

    <div id="toast-container"></div>
    <div id="global-loader">
        <div class="loader-spinner"></div>
        <div class="loader-text">Please wait…</div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/patients.js"></script>
    <script>
        $(document).ready(function () {

            /* 1. Establish Session Guard */
            App.auth.check();
            App.auth.role(['staff' , 'doctor']);

            /* 2. Fetch Active Sidebar Branding Metadata Contexts */
            App.ajax({
                url: '/auth/check.php',
                loader: false,
                silent: true,
                onSuccess: function (d) {
                    if (d && d.user) {
                        $('#sidebar-user-name').text(d.user.name);
                        $('#sidebar-user-role').text(d.user.role || 'Staff');
                        $('#user-avatar-initial').text(d.user.name.charAt(0).toUpperCase());
                    }
                }
            });

        });
    </script>
</body>

</html>