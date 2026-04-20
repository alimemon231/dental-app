/**
 * assets/js/offices.js
 * CRUD logic for the offices page.
 * This file is the template/pattern to copy when building any new module.
 */

$(document).ready(function () {

  /* ── State ── */
  var currentPage    = 1;
  var perPage        = 20;
  var editingId      = null;   // null = adding new, number = editing

  /* ================================================================
     LOAD TABLE
  ================================================================ */
  function loadOffices(page) {
    page = page || 1;
    currentPage = page;

    App.ajax({
      url:    '/offices/list.php',
      method: 'GET',
      loader: false,
      data: {
        page:   page,
        limit:  perPage,
      },
      onSuccess: function (data, msg, res) {
        renderTable(data);
        renderPagination(res.meta || {});
      },
      onError: function () {
        $('#patients-tbody').html(
          '<tr><td colspan="8"><div class="table-empty"><i class="fa-solid fa-circle-exclamation"></i> Failed to load Offices.</div></td></tr>'
        );
      }
    });
  }

  function renderTable(patients) {
    if (!patients || !patients.length) {
      $('#patients-tbody').html(
        '<tr><td colspan="8"><div class="table-empty"><i class="fa-solid fa-user-slash"></i> No offices found.</div></td></tr>'
      );
      return;
    }

    var rows = '';
    $.each(patients, function (i, p) {
      rows += '<tr>' +
        '<td><strong>#' + p.id + '</strong></td>' +
        '<td>' +
          '<div class="flex flex-align gap-3">' +
            '<div class="user-avatar" style="width:32px;height:32px;font-size:0.75rem;background:var(--color-primary-light);color:var(--color-primary)">' +
              App.utils.escHtml(p.office_name) +
            '</div>' +
          '</div>' +
        '</td>' +
        
        
        '<td>' + App.utils.escHtml(p.phone  || '—') + '</td>' +
        '<td>' + App.utils.escHtml(p.email  || '—') + '</td>' +
        '<td>' + App.utils.escHtml(p.address || '-') + '</td>' +
        '<td>' +
          '<div class="actions">' +
            '<button class="btn btn-ghost btn-sm btn-view" data-id="' + p.id + '" title="View"><i class="fa-solid fa-eye"></i></button>' +
            '<button class="btn btn-ghost btn-sm btn-edit" data-id="' + p.id + '" title="Edit"><i class="fa-solid fa-pen"></i></button>' +
            '<button class="btn btn-ghost btn-sm btn-delete" data-id="' + p.id + '" data-name="' + App.utils.escHtml(p.office_name) + '" title="Delete" style="color:var(--color-danger)"><i class="fa-solid fa-trash"></i></button>' +
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

    $('#patients-info').text('Showing ' + from + '–' + to + ' of ' + total + ' officess');

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
    $('#patient-modal-title').text('Add New Office');
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
      ? '/offices/update.php'
      : '/offices/create.php';

    if (isEditing) data.patient_id = editingId;

    App.ajax({
      url:       url,
      method:    'POST',
      data:      data,
      btn:       $('#btn-save-patient'),
      loaderMsg: isEditing ? 'Saving changes…' : 'Creating Office…',
      onSuccess: function (d, msg) {
        App.modal.close('patient-modal');
        App.toast.success('Success', msg);
        loadOffices(currentPage);
      }
    });
  });
  
  /* ================================================================
     VIEW PATIENT
  ================================================================ */
  $(document).on('click', '.btn-view', function () {
    var id = $(this).data('id');

    App.ajax({
      url:    '/offices/get.php?id=' + id,
      loader: false,
      onSuccess: function (p) {
        var office_detail_html =
            '<div class="form-section-title mb-4"><i class="fa-solid fa-user"></i> Office Information</div>' +
            '<input type="hidden" id="view-office-id" value="'+ p.id +'">'+
            infoRow('Office Name',    p.office_name || '—') +
            infoRow('Phone',   p.phone || '—') +
            infoRow('Email',   p.email || '—') +
            infoRow('Address', p.address || '—');

        getNotAssigenedDoctors();
        getNotAssigenedStaff();
        getAssigenedDoctors(id);
        getAssigenedStaff(id)
        $('#view-office-details').html(office_detail_html);
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
      url:    '/offices/get.php?id=' + id,
      loader: false,
      onSuccess: function (p) {
        resetForm();
        editingId = p.id;
        $('#patient-modal-title').text('Edit Office');

        // Populate form
        $('#patient-id').val(p.id);
        $('[name="name"]').val(p.office_name);
        $('[name="phone"]').val(p.phone);
        $('[name="email"]').val(p.email);
        $('[name="address"]').val(p.address);
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
          url:       '/offices/delete.php',
          method:    'POST',
          data:      { id: id },
          loaderMsg: 'Deleting Office…',
          onSuccess: function (d, msg) {
            App.toast.success('Deleted', msg);
            loadOffices(currentPage);
          }
        });
      }
    );
  });


  /* ================================================================
     Extra Functionality to support system
  ================================================================ */


  $(document).on('change', '#select-d-t-a', function () {
    var doctor_id   = $(this).val();
    var office_id = $("#view-office-id").val();
    App.utils.confirm(
      'Are you sure you want to add this doctor.',
      function () {
        App.ajax({
          url:       '/offices/assign_doctor.php',
          method:    'POST',
          data:      { doctor_id: doctor_id , office_id : office_id},
          loader: false,
          onSuccess: function (d, msg) {
            getNotAssigenedDoctors()
            getAssigenedDoctors(office_id)
            App.toast.success('Doctor Assigned', msg);
            loadOffices(currentPage);
          }
        });
      }
    );
  });

  $(document).on('change', '#select-s-t-a', function () {
    var doctor_id   = $(this).val();
    var office_id = $("#view-office-id").val();
    App.utils.confirm(
      'Are you sure you want to add this doctor.',
      function () {
        App.ajax({
          url:       '/offices/assign_doctor.php',
          method:    'POST',
          data:      { doctor_id: doctor_id , office_id : office_id},
          loader: false,
          onSuccess: function (d, msg) {
            getNotAssigenedStaff()
            getAssigenedStaff(office_id)
            App.toast.success('Staff Assigned', msg);
            loadOffices(currentPage);
          }
        });
      }
    );
  });

  function getNotAssigenedDoctors() {
    App.ajax({
      url:    '/offices/select_doctors.php',
      loader: false,
      onSuccess: function (p) {
        var rows = '<option value="null" disabled selected> Select Doctor</option>';
        $.each(p, function (i, p) {
          rows += '<option value='+ p.user_id +'>'+  p.name +'</option>' ;
        });
        
        $("#select-d-t-a").html(rows)
      }
    });
  }

  function getAssigenedDoctors(id) {
    App.ajax({
      url:    '/offices/select_assigned_doctors.php',
      method:    'POST',
      data: {id : id},
      loader: false,
      onSuccess: function (p) {
        var rows = '';
        $.each(p, function (i, p) {
          rows += '<tr>'+
           '<td>'+
           ( i + 1)
          +'</td>'+
          '<td>'+
            p.name 
          +'</td>'+
          '<td>'+
            '<button class="btn btn-ghost btn-sm btn-remove-d" data-id="' + p.user_id + '" data-name="' + App.utils.escHtml(p.name) + '" title="Remove" style="color:var(--color-danger)"><i class="fa-solid fa-trash"></i></button>' +
          '</td>'+
          '</tr>';
        });
        
        $("#assigened-doc-tbody").html(rows)
      }
    });
  }


  function getAssigenedStaff(id) {
    App.ajax({
      url:    '/offices/select_assigned_staff.php',
      method:    'POST',
      data: {id : id},
      loader: false,
      onSuccess: function (p) {
        var rows = '';
        $.each(p, function (i, p) {
          rows += '<tr>'+
           '<td>'+
           ( i + 1)
          +'</td>'+
          '<td>'+
            p.name 
          +'</td>'+
          '<td>'+
            '<button class="btn btn-ghost btn-sm btn-remove-d" data-id="' + p.user_id + '" data-name="' + App.utils.escHtml(p.name) + '" title="Remove" style="color:var(--color-danger)"><i class="fa-solid fa-trash"></i></button>' +
          '</td>'+
          '</tr>';
        });
        
        $("#assigened-staff-tbody").html(rows)
      }
    });
  }



  function getNotAssigenedStaff(){
    App.ajax({
      url:    '/offices/select_staff.php',
      loader: false,
      onSuccess: function (p) {
        var rows = '<option value="null" disabled selected> Select Staff</option>';
        $.each(p, function (i, p) {
          rows += '<option value='+ p.user_id +'>'+  p.name +'</option>' ;
        });
        
        $("#select-s-t-a").html(rows)
      }
    });
  }


  $(document).on('click', '.btn-remove-d', function () {
    var id   = $(this).data('id');
    var name = $(this).data('name');
    var office_id = $("#view-office-id").val()

    App.utils.confirm(
      'Are you sure you want to remove "' + name + '"? from this office.',
      function () {
        App.ajax({
          url:       '/offices/remove_user.php',
          method:    'POST',
          data:      { user_id: id  , office_id : office_id},
          loaderMsg: 'Removing User…',
          onSuccess: function (d, msg) {
            getAssigenedDoctors(office_id);
            getAssigenedStaff(office_id)
            getNotAssigenedDoctors()
            getNotAssigenedStaff()
            App.toast.success('Removed', msg);
            loadOffices(currentPage);
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
  loadOffices(1);

});
