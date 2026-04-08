/**
 * assets/js/patients.js
 * CRUD logic for the Patients page.
 * This file is the template/pattern to copy when building any new module.
 * Replace 'patient' / 'patients' with your module name.
 */

$(document).ready(function () {

  /* ── State ── */
  var currentPage    = 1;
  var perPage        = 20;
  var searchQuery    = '';
  var filterGender   = '';
  var editingId      = null;   // null = adding new, number = editing

  /* ================================================================
     LOAD TABLE
  ================================================================ */
  function loadPatients(page) {
    page = page || 1;
    currentPage = page;

    App.ajax({
      url:    '/api/patients/list.php',
      method: 'GET',
      loader: false,
      data: {
        page:   page,
        limit:  perPage,
        search: searchQuery,
        gender: filterGender,
      },
      onSuccess: function (data, msg, res) {
        renderTable(data);
        renderPagination(res.meta || {});
      },
      onError: function () {
        $('#patients-tbody').html(
          '<tr><td colspan="8"><div class="table-empty"><i class="fa-solid fa-circle-exclamation"></i> Failed to load patients.</div></td></tr>'
        );
      }
    });
  }

  function renderTable(patients) {
    if (!patients || !patients.length) {
      $('#patients-tbody').html(
        '<tr><td colspan="8"><div class="table-empty"><i class="fa-solid fa-user-slash"></i> No patients found.</div></td></tr>'
      );
      return;
    }

    var rows = '';
    $.each(patients, function (i, p) {
      var genderBadge = '';
      if      (p.gender === 'male')   genderBadge = '<span class="badge badge-info">Male</span>';
      else if (p.gender === 'female') genderBadge = '<span class="badge badge-primary">Female</span>';
      else if (p.gender)              genderBadge = '<span class="badge badge-neutral">' + App.utils.escHtml(p.gender) + '</span>';
      else                            genderBadge = '<span class="text-muted">—</span>';

      rows += '<tr>' +
        '<td><strong>#' + p.id + '</strong></td>' +
        '<td>' +
          '<div class="flex flex-align gap-3">' +
            '<div class="user-avatar" style="width:32px;height:32px;font-size:0.75rem;background:var(--color-primary-light);color:var(--color-primary)">' +
              App.utils.escHtml((p.name || '?').charAt(0).toUpperCase()) +
            '</div>' +
            '<div>' +
              '<div class="fw-500">' + App.utils.escHtml(p.name || '—') + '</div>' +
              (p.patient_code ? '<div class="text-xs text-muted">' + App.utils.escHtml(p.patient_code) + '</div>' : '') +
            '</div>' +
          '</div>' +
        '</td>' +
        '<td>' + genderBadge + '</td>' +
        '<td>' + (p.date_of_birth ? App.utils.formatDate(p.date_of_birth) : '<span class="text-muted">—</span>') + '</td>' +
        '<td>' + App.utils.escHtml(p.phone  || '—') + '</td>' +
        '<td>' + App.utils.escHtml(p.email  || '—') + '</td>' +
        '<td>' + App.utils.formatDate(p.created_at) + '</td>' +
        '<td>' +
          '<div class="actions">' +
            '<button class="btn btn-ghost btn-sm btn-view" data-id="' + p.id + '" title="View"><i class="fa-solid fa-eye"></i></button>' +
            '<button class="btn btn-ghost btn-sm btn-edit" data-id="' + p.id + '" title="Edit"><i class="fa-solid fa-pen"></i></button>' +
            '<button class="btn btn-ghost btn-sm btn-delete" data-id="' + p.id + '" data-name="' + App.utils.escHtml(p.name) + '" title="Delete" style="color:var(--color-danger)"><i class="fa-solid fa-trash"></i></button>' +
          '</div>' +
        '</td>' +
      '</tr>';
    });

    $('#patients-tbody').html(rows);
  }

  function renderPagination(meta) {
    var total   = meta.total   || 0;
    var pages   = meta.pages   || 1;
    var current = meta.current || 1;
    var from    = total ? ((current - 1) * perPage + 1) : 0;
    var to      = Math.min(current * perPage, total);

    $('#patients-info').text('Showing ' + from + '–' + to + ' of ' + total + ' patients');

    var btns = '';
    btns += '<button class="page-btn" id="pg-prev" ' + (current <= 1 ? 'disabled' : '') + '><i class="fa-solid fa-chevron-left"></i></button>';

    // Show max 5 page buttons
    var start = Math.max(1, current - 2);
    var end   = Math.min(pages, start + 4);
    for (var i = start; i <= end; i++) {
      btns += '<button class="page-btn ' + (i === current ? 'active' : '') + '" data-page="' + i + '">' + i + '</button>';
    }

    btns += '<button class="page-btn" id="pg-next" ' + (current >= pages ? 'disabled' : '') + '><i class="fa-solid fa-chevron-right"></i></button>';
    $('#pagination-btns').html(btns);
  }

  /* ================================================================
     SEARCH & FILTER
  ================================================================ */
  var debouncedSearch = App.utils.debounce(function () {
    searchQuery = $('#patient-search').val().trim();
    loadPatients(1);
  }, 400);

  $('#patient-search').on('input', debouncedSearch);

  $('#filter-gender').on('change', function () {
    filterGender = $(this).val();
    loadPatients(1);
  });

  $('#btn-clear-filters').on('click', function () {
    $('#patient-search').val('');
    $('#filter-gender').val('');
    searchQuery  = '';
    filterGender = '';
    loadPatients(1);
  });

  /* Pagination */
  $(document).on('click', '.page-btn[data-page]', function () {
    loadPatients(parseInt($(this).data('page')));
  });
  $(document).on('click', '#pg-prev', function () { if (currentPage > 1) loadPatients(currentPage - 1); });
  $(document).on('click', '#pg-next', function () { loadPatients(currentPage + 1); });

  /* ================================================================
     ADD NEW PATIENT
  ================================================================ */
  $('#btn-add-patient').on('click', function () {
    editingId = null;
    resetForm();
    $('#patient-modal-title').text('Add New Patient');
    App.modal.open('patient-modal');
  });

  /* ================================================================
     SAVE PATIENT (create or update)
  ================================================================ */
  $('#btn-save-patient').on('click', function () {
    var form = document.getElementById('patient-form');

    // Front-end validation
    App.form.clearErrors(form);
    if (!App.form.validate(form)) {
      App.toast.warning('Validation', 'Please fill in all required fields.');
      return;
    }

    var data      = App.form.toObject(form);
    var isEditing = !!editingId;
    var url       = isEditing
      ? '/api/patients/update.php'
      : '/api/patients/create.php';

    if (isEditing) data.patient_id = editingId;

    App.ajax({
      url:       url,
      method:    'POST',
      data:      data,
      btn:       $('#btn-save-patient'),
      loaderMsg: isEditing ? 'Saving changes…' : 'Creating patient…',
      onSuccess: function (d, msg) {
        App.modal.close('patient-modal');
        App.toast.success('Success', msg);
        loadPatients(currentPage);
      }
    });
  });

  /* ================================================================
     VIEW PATIENT
  ================================================================ */
  $(document).on('click', '.btn-view', function () {
    var id = $(this).data('id');

    App.ajax({
      url:    '/api/patients/get.php?id=' + id,
      loader: false,
      onSuccess: function (p) {
        var html =
          '<div class="grid-2" style="gap:var(--sp-8)">' +

          '<div>' +
            '<div class="form-section-title mb-4"><i class="fa-solid fa-user"></i> Personal Info</div>' +
            infoRow('Full Name',    p.name || '—') +
            infoRow('Gender',       ucFirst(p.gender) || '—') +
            infoRow('Date of Birth',p.date_of_birth ? App.utils.formatDate(p.date_of_birth) : '—') +
            infoRow('Blood Group',  p.blood_group || '—') +
            infoRow('Referred By',  p.referred_by || '—') +
          '</div>' +

          '<div>' +
            '<div class="form-section-title mb-4"><i class="fa-solid fa-address-book"></i> Contact</div>' +
            infoRow('Phone',   p.phone || '—') +
            infoRow('Email',   p.email || '—') +
            infoRow('City',    p.city  || '—') +
            infoRow('Address', p.address || '—') +
          '</div>' +

          '</div>' +

          (p.allergies || p.notes ?
            '<div class="divider"></div>' +
            '<div class="form-section-title mb-4"><i class="fa-solid fa-notes-medical"></i> Medical Notes</div>' +
            (p.allergies ? infoRow('Allergies', p.allergies) : '') +
            (p.notes     ? '<div class="form-group"><div class="form-label">Notes</div><div style="font-size:var(--font-size-sm)">' + App.utils.escHtml(p.notes) + '</div></div>' : '')
          : '');

        $('#view-patient-body').html(html);
        $('#btn-edit-from-view').data('id', p.id);
        App.modal.open('view-patient-modal');
      }
    });
  });

  function infoRow(label, value) {
    return '<div class="flex-between mb-4" style="border-bottom:1px solid var(--color-border);padding-bottom:var(--sp-3)">' +
      '<span class="text-sm text-muted">' + label + '</span>' +
      '<span class="text-sm fw-500">' + App.utils.escHtml(String(value)) + '</span>' +
    '</div>';
  }

  function ucFirst(str) { return str ? str.charAt(0).toUpperCase() + str.slice(1) : ''; }

  $('#btn-edit-from-view').on('click', function () {
    App.modal.close('view-patient-modal');
    openEditModal($(this).data('id'));
  });

  /* ================================================================
     EDIT PATIENT
  ================================================================ */
  $(document).on('click', '.btn-edit', function () {
    openEditModal($(this).data('id'));
  });

  function openEditModal(id) {
    App.ajax({
      url:    '/api/patients/get.php?id=' + id,
      loader: false,
      onSuccess: function (p) {
        editingId = p.id;
        resetForm();
        $('#patient-modal-title').text('Edit Patient');

        // Populate form
        $('#patient-id').val(p.id);
        $('[name="first_name"]').val(p.first_name);
        $('[name="last_name"]').val(p.last_name);
        $('[name="gender"]').val(p.gender);
        $('[name="date_of_birth"]').val(p.date_of_birth);
        $('[name="blood_group"]').val(p.blood_group);
        $('[name="referred_by"]').val(p.referred_by);
        $('[name="phone"]').val(p.phone);
        $('[name="email"]').val(p.email);
        $('[name="city"]').val(p.city);
        $('[name="address"]').val(p.address);
        $('[name="allergies"]').val(p.allergies);
        $('[name="notes"]').val(p.notes);

        App.modal.open('patient-modal');
      }
    });
  }

  /* ================================================================
     DELETE PATIENT
  ================================================================ */
  $(document).on('click', '.btn-delete', function () {
    var id   = $(this).data('id');
    var name = $(this).data('name');

    App.utils.confirm(
      'Are you sure you want to delete "' + name + '"? This cannot be undone.',
      function () {
        App.ajax({
          url:       '/api/patients/delete.php',
          method:    'POST',
          data:      { patient_id: id },
          loaderMsg: 'Deleting patient…',
          onSuccess: function (d, msg) {
            App.toast.success('Deleted', msg);
            loadPatients(currentPage);
          }
        });
      }
    );
  });

  /* ================================================================
     HELPERS
  ================================================================ */
  function resetForm() {
    App.form.reset(document.getElementById('patient-form'));
    editingId = null;
  }

  /* ================================================================
     INIT — load on page ready
  ================================================================ */
  loadPatients(1);

});
