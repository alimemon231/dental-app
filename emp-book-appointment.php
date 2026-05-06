<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Book Appointments</title>
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
            <h1>Book Appointments</h1>
            <div class="page-header-sub">Schedule patients for approved insurance pre-authorizations.</div>
          </div>
        </div>

        <div class="table-wrapper">
          <table class="data-table" id="appointment-table">
            <thead>
              <tr>
                <th>Patient Name</th>
                <th>DOB</th>
                <th>Approved By</th>
                <th>Created At</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="appointment-tbody">
              <tr>
                <td colspan="6">
                  <div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading Approved Records…</div>
                </td>
              </tr>
            </tbody>
          </table>
          <div class="table-pagination" id="appointment-pagination">
            <span id="appointment-info" class="text-muted text-sm">—</span>
            <div class="pagination" id="pagination-btns"></div>
          </div>
        </div>

        <!-- ============================================================
              BOOK APPOINTMENT MODAL
        ============================================================ -->
        <div class="modal-backdrop" id="book-modal">
          <div class="modal modal-sm">
            <div class="modal-header">
              <div class="modal-title">Schedule Appointment</div>
              <button class="modal-close" data-close-modal="book-modal">&#x2715;</button>
            </div>
            <div class="modal-body">
              <form id="book-form" novalidate>
                <input type="hidden" name="id" id="book-preauth-id">
                
                <div class="form-group">
                  <label class="form-label">Appointment Date <span class="required">*</span></label>
                  <input type="date" name="appointment_date" id="appointment_date" class="form-control" required>
                  <span class="form-error">Please select a valid date and time.</span>
                  <small class="text-muted mt-2 d-block">Select the date and time for the treatment.</small>
                </div>
              </form>
            </div>
            <div class="modal-footer">
              <button class="btn btn-ghost" data-close-modal="book-modal">Cancel</button>
              <button class="btn btn-primary" id="btn-confirm-book">
                <i class="fa-solid fa-calendar-check"></i> Confirm Booking
              </button>
            </div>
          </div>
        </div>

        <!-- VIEW DETAILS MODAL -->
        <div class="modal-backdrop" id="view-modal">
          <div class="modal modal-lg">
            <div class="modal-header">
              <div class="modal-title">Pre-Auth Details</div>
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

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="assets/js/app.js"></script>
  <!-- We will create this JS file next -->
  <script src="assets/js/emp-book-appointment.js"></script>
  <script>
    $(document).ready(function () {
      App.auth.check();
      App.auth.role('staff');
    });
  </script>
</body>
</html>