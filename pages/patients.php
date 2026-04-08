<?php
/**
 * pages/patients.php
 * Full CRUD listing page — demonstrates the standard inner-page pattern.
 *
 * Every new feature page follows this exact structure:
 * 1.  Set $pageTitle, $activePage, $breadcrumbs
 * 2.  require page-header.php  (handles auth + shell HTML)
 * 3.  Write page HTML
 * 4.  Set $extraScripts
 * 5.  require page-footer.php
 */

$pageTitle   = 'Patients';
$activePage  = 'patients';
$breadcrumbs = [
    ['label' => 'Clinic', 'url' => '#'],
    ['label' => 'Patients'],
];

require_once __DIR__ . '/../includes/page-header.php';
?>

<!-- ============================================================
     PAGE HEADER
============================================================ -->
<div class="page-header">
  <div class="page-header-left">
    <h1>Patients</h1>
    <div class="page-header-sub">Manage all registered patients.</div>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-primary" id="btn-add-patient">
      <i class="fa-solid fa-user-plus"></i> Add Patient
    </button>
  </div>
</div>

<!-- ============================================================
     SEARCH & FILTER BAR
============================================================ -->
<div class="card mb-6">
  <div class="flex flex-align gap-4" style="flex-wrap:wrap">
    <div class="input-group" style="max-width:320px;flex:1">
      <i class="fa-solid fa-magnifying-glass input-icon"></i>
      <input type="text" id="patient-search" class="form-control" placeholder="Search by name, phone or email…">
    </div>
    <select class="form-control" id="filter-gender" style="max-width:140px">
      <option value="">All Genders</option>
      <option value="male">Male</option>
      <option value="female">Female</option>
      <option value="other">Other</option>
    </select>
    <button class="btn btn-ghost btn-sm" id="btn-clear-filters">
      <i class="fa-solid fa-xmark"></i> Clear
    </button>
    <button class="btn btn-ghost btn-sm" id="btn-export">
      <i class="fa-solid fa-file-export"></i> Export
    </button>
  </div>
</div>

<!-- ============================================================
     PATIENTS TABLE
============================================================ -->
<div class="table-wrapper">
  <table class="data-table" id="patients-table">
    <thead>
      <tr>
        <th class="sortable" data-col="id">#</th>
        <th class="sortable" data-col="name">Patient</th>
        <th>Gender</th>
        <th>Date of Birth</th>
        <th>Phone</th>
        <th>Email</th>
        <th>Registered</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody id="patients-tbody">
      <tr>
        <td colspan="8">
          <div class="table-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>
        </td>
      </tr>
    </tbody>
  </table>
  <div class="table-pagination" id="patients-pagination">
    <span id="patients-info" class="text-muted text-sm">—</span>
    <div class="pagination" id="pagination-btns"></div>
  </div>
</div>

<!-- ============================================================
     ADD / EDIT PATIENT MODAL
============================================================ -->
<div class="modal-backdrop" id="patient-modal">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-title" id="patient-modal-title">Add New Patient</div>
      <button class="modal-close" data-close-modal="patient-modal">&#x2715;</button>
    </div>
    <div class="modal-body">
      <form id="patient-form" novalidate>
        <input type="hidden" name="patient_id" id="patient-id" value="">

        <!-- Personal Info -->
        <div class="form-section">
          <div class="form-section-title"><i class="fa-solid fa-user"></i> Personal Information</div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">First Name <span class="required">*</span></label>
              <input type="text" name="first_name" id="p-first-name" class="form-control" placeholder="e.g. Ali" required>
              <span class="form-error">First name is required.</span>
            </div>
            <div class="form-group">
              <label class="form-label">Last Name <span class="required">*</span></label>
              <input type="text" name="last_name" id="p-last-name" class="form-control" placeholder="e.g. Ahmed" required>
              <span class="form-error">Last name is required.</span>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Gender</label>
              <select name="gender" class="form-control">
                <option value="">Select…</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Date of Birth</label>
              <input type="date" name="date_of_birth" class="form-control">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Blood Group</label>
              <select name="blood_group" class="form-control">
                <option value="">Unknown</option>
                <option>A+</option><option>A-</option>
                <option>B+</option><option>B-</option>
                <option>O+</option><option>O-</option>
                <option>AB+</option><option>AB-</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Referred By</label>
              <input type="text" name="referred_by" class="form-control" placeholder="Doctor / Patient name">
            </div>
          </div>
        </div>

        <!-- Contact Info -->
        <div class="form-section">
          <div class="form-section-title"><i class="fa-solid fa-address-book"></i> Contact Details</div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Phone</label>
              <div class="input-group">
                <i class="fa-solid fa-phone input-icon"></i>
                <input type="tel" name="phone" class="form-control" placeholder="e.g. 0300-1234567">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Email</label>
              <div class="input-group">
                <i class="fa-regular fa-envelope input-icon"></i>
                <input type="email" name="email" class="form-control" placeholder="patient@email.com">
              </div>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">City</label>
              <input type="text" name="city" class="form-control" placeholder="e.g. Karachi">
            </div>
            <div class="form-group">
              <label class="form-label">Address</label>
              <input type="text" name="address" class="form-control" placeholder="Street, area…">
            </div>
          </div>
        </div>

        <!-- Medical Info -->
        <div class="form-section">
          <div class="form-section-title"><i class="fa-solid fa-notes-medical"></i> Medical Notes</div>
          <div class="form-group">
            <label class="form-label">Known Allergies</label>
            <input type="text" name="allergies" class="form-control" placeholder="e.g. Penicillin, Latex">
          </div>
          <div class="form-group mb-0">
            <label class="form-label">Additional Notes</label>
            <textarea name="notes" class="form-control" rows="3" placeholder="Any other relevant medical history…"></textarea>
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

<!-- ============================================================
     VIEW PATIENT MODAL
============================================================ -->
<div class="modal-backdrop" id="view-patient-modal">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-title">Patient Details</div>
      <button class="modal-close" data-close-modal="view-patient-modal">&#x2715;</button>
    </div>
    <div class="modal-body" id="view-patient-body">
      Loading…
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" data-close-modal="view-patient-modal">Close</button>
      <button class="btn btn-primary" id="btn-edit-from-view">
        <i class="fa-solid fa-pen"></i> Edit
      </button>
    </div>
  </div>
</div>

<?php
$extraScripts = '<script src="/assets/js/patients.js"></script>';
require_once __DIR__ . '/../includes/page-footer.php';
?>
