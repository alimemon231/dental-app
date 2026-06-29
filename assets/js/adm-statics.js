/**
 * assets/js/adm-statics.js
 * Admin module for managing dynamic dashboard statistics and custom charting matrices.
 */

$(document).ready(function () {

    /* ── State ── */
    var currentPage = 1;
    var perPage = 20;
    var editingId = null; // null = create, number = edit
    var isPopulatingForm = false; // Blocks auto-row-generation during edit loading

    // Cache for endpoints to avoid repeated API calls
    var endpointMap = {
        offices: '/offices/list.php', // Verified from your previous admin order module
        doctors: '/doctor/list.php' // Verified from your emp-pre-auth module
    };

    /* ================================================================
        1. MATRIX VISIBILITY & TARGET SCOPE RESOLUTION
    ================================================================ */
    $('#target_composite_type').on('change', function () {
        if (isPopulatingForm) return; // Prevent resetting the DOM when we are injecting Edit data

        var selection = $(this).val();
        var $chartType = $('#chart_type');
        var $specificEntity = $('#container-specific-entity');
        var $metricRowsWrapper = $('#metric-rows-wrapper');

        $chartType.empty();
        $metricRowsWrapper.empty();

        if (selection === 'all_offices' || selection === 'all_doctors') {
            // Rule A: Global selections lock rendering to Summary Cards ONLY
            $chartType.append('<option value="card">Data Summary Cards Grid</option>');
            $specificEntity.hide();
            $('#target_id').removeAttr('required');
            $('#btn-add-metric-row').hide();

            // Auto-populate the rows with structural IDs as keys
            loadGlobalLabelsMatrix(selection);

        } else {
            // Rule B: Single targets support dynamic custom pie/bar charts
            $chartType.append('<option value="pie">Interactive Circular Pie Chart</option>');
            $chartType.append('<option value="bar">Dimensional Axis Bar Chart</option>');

            if (selection === 'single_office') {
                $('#label-specific-entity').html('Select Target Office Location <span class="required">*</span>');
                loadDropdownEntities(endpointMap.offices, 'office_name');
            } else {
                $('#label-specific-entity').html('Select Target Provider Doctor <span class="required">*</span>');
                loadDropdownEntities(endpointMap.doctors, 'name');
            }

            $specificEntity.show();
            $('#target_id').attr('required', 'required');
            $('#btn-add-metric-row').show();

            // Append an initial empty editable row
            addCustomDataInputRow('', '', false, null);
        }
    });

    /* ================================================================
        2. DYNAMIC ROW INJECTION HELPERS
    ================================================================ */
    $('#btn-add-metric-row').on('click', function () {
        addCustomDataInputRow('', '', false, null);
    });

    $(document).on('click', '.btn-remove-metric', function () {
        $(this).closest('.metric-data-row').remove();
    });

    function addCustomDataInputRow(label, value, isReadOnly = false, entityId = null) {
        var readOnlyAttr = isReadOnly ? 'readonly' : '';
        var hiddenIdInput = entityId ? `<input type="hidden" class="metric-target-id" value="${entityId}">` : '';

        var rowMarkup = `
          <div class="form-row metric-data-row" style="grid-template-columns: 2fr 1fr 40px; gap: var(--sp-3); align-items: flex-end;">
            ${hiddenIdInput}
            <div class="form-group">
              <label class="form-label text-xs">Metric Field Label/Key</label>
              <input type="text" name="metric_labels[]" class="form-control metric-label-input" value="${label}" placeholder="e.g., Active Claims" required ${readOnlyAttr}>
            </div>
            <div class="form-group">
              <label class="form-label text-xs">Numeric Value Context</label>
              <input type="number" name="metric_values[]" class="form-control metric-value-input" value="${value}" placeholder="0" step="any" required>
            </div>
            <div class="form-group" style="display: flex; justify-content: center;">
              <button type="button" class="btn-remove-metric" ${isReadOnly ? 'style="visibility:hidden;"' : ''} title="Remove Data Point">
                <i class="fa-solid fa-trash-can"></i>
              </button>
            </div>
          </div>`;
        $('#metric-rows-wrapper').append(rowMarkup);
    }

    function loadGlobalLabelsMatrix(scopeType, prefillData = null) {
        var targetUrl = scopeType === 'all_offices' ? endpointMap.offices : endpointMap.doctors;
        var nameKey = scopeType === 'all_offices' ? 'office_name' : 'name';
        var idKey = scopeType === 'all_offices' ? 'id' : 'user_id';

        App.ajax({
            url: targetUrl, method: 'GET', loader: false, silent: true,
            onSuccess: function (dataset) {
                if (dataset && dataset.length > 0) {
                    dataset.forEach(function (item) {
                        var recordId = item[idKey] || item.id;
                        // Check if we are passing edit payload data back in
                        var rowValue = (prefillData && prefillData[recordId] !== undefined) ? prefillData[recordId] : 0;
                        addCustomDataInputRow(item[nameKey], rowValue, true, recordId);
                    });
                } else {
                    $('#metric-rows-wrapper').html('<div class="text-muted text-sm py-2">No active entities found in the database.</div>');
                }
            }
        });
    }

    function loadDropdownEntities(endpointUrl, nameKey, selectedValue = null) {
        var $selectElement = $('#target_id');
        $selectElement.empty().append('<option value="">Fetching database entries...</option>');

        App.ajax({
            url: endpointUrl, method: 'GET', loader: false, silent: true,
            onSuccess: function (dataset) {
                $selectElement.empty().append('<option value="">Choose designated configuration profile target...</option>');
                if (dataset && dataset.length > 0) {
                    dataset.forEach(function (item) {
                        var recordId = item.user_id || item.id;
                        $selectElement.append(`<option value="${recordId}">${item[nameKey]}</option>`);
                    });

                    // Bind the Edit record target identifier if we passed it in
                    if (selectedValue) {
                        $selectElement.val(selectedValue);
                    }
                }
            }
        });
    }

    /* ================================================================
        3. EDIT RECORD WORKFLOW
    ================================================================ */
    $(document).on('click', '.btn-edit-static', function () {
        var id = $(this).data('id');

        App.ajax({
            url: '/adm-statics/get.php?id=' + id,
            method: 'GET',
            loader: true,
            onSuccess: function (r) {
                var data = r.data || r; // Extract wrapper if present
                populateEditForm(data);
            }
        });
    });

    function populateEditForm(data) {
        resetForm();
        editingId = data.id;
        isPopulatingForm = true; // Block change events from clearing our manual setup

        $('#statics-modal-title').text('Edit Dynamic Metric Widget (#' + data.id + ')');

        // Map basic literal scalar values
        $('#static_label').val(data.static_label);
        $('#staff_visibility').val(data.staff_visiblity);
        $('#doctor_visibility').val(data.doctor_visiblity);

        // Map Date Selectors
        if (data.static_month) {
            var dateParts = data.static_month.split('-');
            $('#static_year').val(dateParts[0]);
            $('#static_month_select').val(dateParts[1]);
        }

        // Determine Composite UI Dropdown value
        var compositeType = '';
        if (data.target_type === 'office' && data.target_id === 'all') compositeType = 'all_offices';
        else if (data.target_type === 'doctor' && data.target_id === 'all') compositeType = 'all_doctors';
        else if (data.target_type === 'office') compositeType = 'single_office';
        else if (data.target_type === 'doctor') compositeType = 'single_doctor';

        $('#target_composite_type').val(compositeType);

        // Parse JSON Database Payload Matrix
        var parsedJson = {};
        try {
            parsedJson = JSON.parse(data.json_data || '{}');
        } catch (e) {
            console.error("JSON parsing failure on payload", e);
        }

        // Reconstruct Chart Logic Controls
        var $chartType = $('#chart_type');
        var $specificEntity = $('#container-specific-entity');
        var $metricRowsWrapper = $('#metric-rows-wrapper');

        $chartType.empty();
        $metricRowsWrapper.empty();

        if (compositeType === 'all_offices' || compositeType === 'all_doctors') {
            $chartType.append('<option value="card">Data Summary Cards Grid</option>');
            $chartType.val('card');
            $specificEntity.hide();
            $('#target_id').removeAttr('required');
            $('#btn-add-metric-row').hide();

            // Build fixed global array populated with the historic JSON data points
            loadGlobalLabelsMatrix(compositeType, parsedJson);

        } else {
            $chartType.append('<option value="pie">Interactive Circular Pie Chart</option>');
            $chartType.append('<option value="bar">Dimensional Axis Bar Chart</option>');
            $chartType.val(data.chart_type.toLowerCase());

            var endpoint = compositeType === 'single_office' ? endpointMap.offices : endpointMap.doctors;
            var nameKey = compositeType === 'single_office' ? 'office_name' : 'name';

            if (compositeType === 'single_office') {
                $('#label-specific-entity').html('Select Target Office Location <span class="required">*</span>');
            } else {
                $('#label-specific-entity').html('Select Target Provider Doctor <span class="required">*</span>');
            }

            loadDropdownEntities(endpoint, nameKey, data.target_id);

            $specificEntity.show();
            $('#target_id').attr('required', 'required');
            $('#btn-add-metric-row').show();

            // Inject distinct variable custom JSON fields back into the rows wrapper layout
            if (Object.keys(parsedJson).length > 0) {
                $.each(parsedJson, function (key, val) {
                    addCustomDataInputRow(key, val, false, null);
                });
            } else {
                addCustomDataInputRow('', '', false, null);
            }
        }

        isPopulatingForm = false; // Restore DOM event listener interactions
        App.modal.open('statics-modal');
    }

    /* ================================================================
        4. CREATE / SAVE DYNAMIC STATISTIC RECORD
    ================================================================ */
    $('#btn-add-statics').on('click', function () {
        editingId = null;
        isPopulatingForm = false;
        resetForm();
        $('#statics-modal-title').text('Create Dynamic Metric Grid Widget');
        $('#target_composite_type').trigger('change');
        App.modal.open('statics-modal');
    });

    $('#btn-save-statics').on('click', function (e) {
        e.preventDefault();
        var form = $('#statics-form');

        App.form.clearErrors(form);
        if (!App.form.validate(form)) {
            App.toast.warning('Validation Error', 'Please complete all required fields and ensure numeric values are valid.');
            return;
        }

        var compositeType = $('#target_composite_type').val();
        var isGlobalScope = (compositeType === 'all_offices' || compositeType === 'all_doctors');
        var finalTargetType = compositeType.includes('office') ? 'office' : 'doctor';
        var finalTargetId = isGlobalScope ? 'all' : $('#target_id').val();

        if (!isGlobalScope && !finalTargetId) {
            App.toast.warning('Target Missing', 'Please select a specific target entity from the dropdown.');
            return;
        }

        var jsonDataObject = {};
        var rowCount = 0;

        $('.metric-data-row').each(function () {
            var key;
            if (isGlobalScope) {
                key = $(this).find('.metric-target-id').val();
            } else {
                key = $(this).find('.metric-label-input').val().trim();
            }

            var val = parseFloat($(this).find('.metric-value-input').val()) || 0;

            if (key) {
                jsonDataObject[key] = val;
                rowCount++;
            }
        });

        if (rowCount === 0) {
            App.toast.warning('Empty Payload', 'Please define at least one data metric entry row.');
            return;
        }

        var year = $('#static_year').val();
        var month = $('#static_month_select').val();
        var finalDateStr = `${year}-${month}-01`;

        var payload = {
            id: editingId,
            static_month: finalDateStr,
            static_label: $('#static_label').val(),
            target_type: finalTargetType,
            target_id: finalTargetId,
            chart_type: $('#chart_type').val(),
            json_data: JSON.stringify(jsonDataObject),
            staff_visiblity: $('#staff_visibility').val(),
            doctor_visiblity: $('#doctor_visibility').val()
        };

        var url = editingId ? '/adm-statics/update.php' : '/adm-statics/create.php';

        App.ajax({
            url: url,
            method: 'POST',
            data: payload,
            btn: $('#btn-save-statics'),
            loaderMsg: 'Publishing dynamic widget data...',
            onSuccess: function (d, msg) {
                App.modal.close('statics-modal');
                App.toast.success('Configuration Saved', msg || 'Metric widget saved successfully.');
                loadStatics(currentPage);
            }
        });
    });

    /* ================================================================
        5. LOAD & RENDER TABLE DATA
    ================================================================ */
    function loadStatics(page) {
        page = page || 1;
        currentPage = page;

        var filterMonth = $('#filter-static-month').val();
        var filterTarget = $('#filter-target-type').val();

        App.ajax({
            url: '/adm-statics/list.php',
            method: 'GET',
            loader: false,
            data: {
                page: page,
                limit: perPage,
                month: filterMonth,
                target_type: filterTarget
            },
            onSuccess: function (data, msg, res) {
                renderTable(data.records);
            },
            onError: function () {
                $('#statics-tbody').html(
                    '<tr><td colspan="7"><div class="table-empty"><i class="fa-solid fa-circle-exclamation"></i> Failed to load widget configurations.</div></td></tr>'
                );
            }
        });
    }

    function renderTable(records) {
        if (!records || records.length === 0) {
            $('#statics-tbody').html(
                '<tr><td colspan="7"><div class="table-empty text-muted py-4"><i class="fa-solid fa-chart-line"></i> No dynamic widgets found.</div></td></tr>'
            );
            return;
        }

        var rows = '';
        $.each(records, function (i, r) {
            var targetScopeBadge = r.target_id === 'all'
                ? `<span class="badge bg-primary text-white px-2 py-1">Global ${r.target_type}s</span>`
                : `<span class="badge bg-light text-dark px-2 py-1">DR: ${r.target_profile_name}</span>`;

            var staffVis = parseInt(r.staff_visiblity) === 1
                ? '<i class="fa-solid fa-check text-success" title="Visible to Staff"></i>'
                : '<i class="fa-solid fa-xmark text-danger" title="Hidden from Staff"></i>';
            var docVis = parseInt(r.doctor_visiblity) === 1
                ? '<i class="fa-solid fa-check text-success" title="Visible to Doctors"></i>'
                : '<i class="fa-solid fa-xmark text-danger" title="Hidden from Doctors"></i>';

            rows += `
                <tr>
                    <td><strong>#${r.id}</strong></td>
                    <td>${App.utils.escHtml(r.formatted_month)}</td>
                    <td class="fw-bold text-primary">${App.utils.escHtml(r.static_label)}</td>
                    <td>${targetScopeBadge}</td>
                    <td><span class="status-badge status-sent" style="text-transform:uppercase;">${App.utils.escHtml(r.chart_type)}</span></td>
                    <td style="font-size: 1.1rem; letter-spacing: 10px;">
                        ${staffVis} ${docVis}
                    </td>
                    <td>
                        <div class="actions" style="display: flex; gap: 4px;">
                            <button class="btn btn-ghost btn-sm btn-edit-static" data-id="${r.id}" title="Edit Widget" style="color:var(--color-primary)">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button class="btn btn-ghost btn-sm btn-delete-static" data-id="${r.id}" title="Delete Widget" style="color:var(--color-danger)">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
        });

        $('#statics-tbody').html(rows);
    }

    /* ================================================================
    6. DELETE HANDLER
================================================================ */
    $(document).on('click', '.btn-delete-static', function () {
        var id = $(this).data('id');

        // Using the centralized confirmDelete helper 
        // (Ensure this function exists in your common JS assets)
        confirmDelete(id, '/adm-statics/delete.php', function () {
            // Callback: Reload the table after successful deletion
            loadStatics(currentPage);
        });
    });

    /**
 * Global helper to trigger the delete modal
 */
    function confirmDelete(id, endpoint, callback) {
        const $modal = $('#delete-confirm-modal');

        // Ensure we clear previous bindings to avoid multiple triggers
        $('#btn-delete-confirm-ok').off('click').on('click', function () {
            App.ajax({
                url: endpoint,
                method: 'POST',
                data: { id: id },
                loaderMsg: 'Removing widget configuration...',
                onSuccess: function (res, msg) {
                    App.modal.close('delete-confirm-modal');
                    App.toast.success('Deleted', msg || 'Widget removed successfully.');
                    if (callback) callback();
                }
            });
        });

        App.modal.open('delete-confirm-modal');
    }

    /* ================================================================
        HELPERS & INIT
    ================================================================ */
    $('#btn-filter-statics').on('click', function (e) {
        e.preventDefault();
        loadStatics(1);
    });

    function resetForm() {
        App.form.reset(document.getElementById('statics-form'));
        $('#metric-rows-wrapper').empty();
        $('#target_id').empty();
    }

    // Trigger initial table load on page ready
    loadStatics(1); // Uncomment if your list.php endpoint is ready
});