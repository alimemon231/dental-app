/**
 * ============================================================
 * DENTAL APP — CORE JS
 * Global AJAX handler, loader, toast, helpers
 * ============================================================
 */

/* ------------------------------------------------------------------ */
/*  NAMESPACE                                                           */
/* ------------------------------------------------------------------ */
window.App = window.App || {};

/* ------------------------------------------------------------------ */
/*  CONFIG (edit base URL here)                                         */
/* ------------------------------------------------------------------ */
App.config = {
  baseUrl:       '/dental-app',          // e.g. '/dental-app' if in a subdirectory
  apiPath:       '/api',      // your PHP handler folder
  loaderDelay:   200,         // ms before showing full-screen loader
  toastDuration: 4000,        // default toast auto-dismiss ms
  sessionCheckInterval: 5 * 60 * 1000  // check session every 5 min
};

/* ------------------------------------------------------------------ */
/*  LOADER                                                              */
/*  Auto-shown on AJAX start, hidden on AJAX stop.                      */
/*  Call App.loader.show() / App.loader.hide() manually too.           */
/* ------------------------------------------------------------------ */
App.loader = (function () {
  let activeRequests = 0;
  let timer          = null;

  function show(message) {
    activeRequests++;
    clearTimeout(timer);
    timer = setTimeout(function () {
      const el = document.getElementById('global-loader');
      if (el) {
        el.querySelector('.loader-text').textContent = message || 'Please wait…';
        el.classList.add('visible');
      }
    }, App.config.loaderDelay);
  }

  function hide(force) {
    if (force) activeRequests = 0;
    else activeRequests = Math.max(0, activeRequests - 1);

    if (activeRequests === 0) {
      clearTimeout(timer);
      const el = document.getElementById('global-loader');
      if (el) el.classList.remove('visible');
    }
  }

  return { show: show, hide: hide };
})();

/* ------------------------------------------------------------------ */
/*  AJAX — Universal request function                                   */
/*                                                                       */
/*  Usage:                                                              */
/*  App.ajax({                                                          */
/*    url:       'api/users/list.php',                                  */
/*    method:    'POST',          // default GET                        */
/*    data:      { key: val },    // object, FormData, or null          */
/*    loader:    true,            // show full-screen loader (default)  */
/*    loaderMsg: 'Saving…',       // custom loader message              */
/*    silent:    false,           // skip auto error toast              */
/*    onSuccess: function(res) {},                                       */
/*    onError:   function(err) {},                                       */
/*    onComplete:function() {}    // always called                      */
/*  });                                                                 */
/*                                                                      */
/*  The PHP side must return JSON:                                      */
/*  { "success": true,  "data": {}, "message": "Done" }                */
/*  { "success": false, "message": "Something went wrong" }            */
/* ------------------------------------------------------------------ */
App.ajax = function (options) {
  var settings = $.extend({
    url:        '',
    method:     'GET',
    data:       null,
    loader:     true,
    loaderMsg:  'Please wait…',
    silent:     false,
    onSuccess:  null,
    onError:    null,
    onComplete: null,
    btn:        null  // pass a jQuery button el to show inline spinner
  }, options);

  if (!settings.url) {
    console.error('[App.ajax] No URL provided.');
    return;
  }

  // Prepend base path if needed
  var fullUrl = App.config.baseUrl + App.config.apiPath+ settings.url;

  // Button loading state
  if (settings.btn) {
    settings.btn.addClass('loading').prop('disabled', true);
  }

  // Show loader
  if (settings.loader) {
    App.loader.show(settings.loaderMsg);
  }

  // Determine content type based on data type
  var isFormData = settings.data instanceof FormData;
  var ajaxOptions = {
    url:        fullUrl,
    type:       settings.method.toUpperCase(),
    dataType:   'json',
    success: function (res) {
      if (res && res.success === true) {
        if (typeof settings.onSuccess === 'function') {
          settings.onSuccess(res.data, res.message, res);
        }
      } else {
        var errMsg = (res && res.message) ? res.message : 'An unexpected error occurred.';
        if (!settings.silent) {
          App.toast.error('Error', errMsg);
        }
        if (typeof settings.onError === 'function') {
          settings.onError(errMsg, res);
        }
      }
    },
    error: function (xhr, status, error) {
      var errMsg = 'Network error. Please try again.';
      try {
        var parsed = JSON.parse(xhr.responseText);
        if (parsed && parsed.message) errMsg = parsed.message;
      } catch (e) {}

      if (status === 'timeout') errMsg = 'Request timed out. Please try again.';
      if (xhr.status === 401)   App.auth.handleUnauthorized();
      if (xhr.status === 403)   errMsg = 'You do not have permission to perform this action.';
      if (xhr.status === 404)   errMsg = 'The requested resource was not found.';
      if (xhr.status === 500)   errMsg = 'Server error. Please contact support.';

      console.error('[App.ajax] Error:', xhr.status, status, error);

      if (!settings.silent) {
        App.toast.error('Request Failed', errMsg);
      }
      if (typeof settings.onError === 'function') {
        settings.onError(errMsg, xhr);
      }
    },
    complete: function () {
      if (settings.loader) App.loader.hide();
      if (settings.btn) {
        settings.btn.removeClass('loading').prop('disabled', false);
      }
      if (typeof settings.onComplete === 'function') {
        settings.onComplete();
      }
    }
  };

  if (settings.data !== null) {
    if (isFormData) {
      ajaxOptions.data        = settings.data;
      ajaxOptions.processData = false;
      ajaxOptions.contentType = false;
    } else {
      ajaxOptions.data        = settings.data;
      ajaxOptions.contentType = 'application/x-www-form-urlencoded; charset=UTF-8';
    }
  }

  return $.ajax(ajaxOptions);
};

/* ------------------------------------------------------------------ */
/*  TOAST NOTIFICATIONS                                                 */
/*                                                                       */
/*  App.toast.success('Title', 'Message')                              */
/*  App.toast.error(...)                                                */
/*  App.toast.warning(...)                                              */
/*  App.toast.info(...)                                                 */
/* ------------------------------------------------------------------ */
App.toast = (function () {
  var icons = {
    success: 'fa-circle-check',
    error:   'fa-circle-xmark',
    warning: 'fa-triangle-exclamation',
    info:    'fa-circle-info'
  };

  function show(type, title, message, duration) {
    var container = document.getElementById('toast-container');
    if (!container) return;

    var id   = 'toast-' + Date.now();
    var icon = icons[type] || icons.info;
    var ms   = duration || App.config.toastDuration;

    var html =
      '<div id="' + id + '" class="toast toast-' + type + '">' +
        '<i class="fa-solid ' + icon + ' toast-icon ' + type + '"></i>' +
        '<div class="toast-content">' +
          (title   ? '<div class="toast-title">'   + App.utils.escHtml(title)   + '</div>' : '') +
          (message ? '<div class="toast-message">' + App.utils.escHtml(message) + '</div>' : '') +
        '</div>' +
        '<button class="toast-dismiss" onclick="App.toast.dismiss(\'' + id + '\')">&#x2715;</button>' +
      '</div>';

    $('#toast-container').append(html);

    setTimeout(function () {
      App.toast.dismiss(id);
    }, ms);
  }

  function dismiss(id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.classList.add('removing');
    setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 350);
  }

  return {
    success: function (title, msg, ms) { show('success', title, msg, ms); },
    error:   function (title, msg, ms) { show('error',   title, msg, ms); },
    warning: function (title, msg, ms) { show('warning', title, msg, ms); },
    info:    function (title, msg, ms) { show('info',    title, msg, ms); },
    dismiss: dismiss
  };
})();

/* ------------------------------------------------------------------ */
/*  AUTH                                                                */
/* ------------------------------------------------------------------ */
App.auth = {
  /**
   * Call this on every protected page's document.ready.
   * It fires an AJAX check to verify the session is alive.
   */
  check: function (redirectOnFail) {
    redirectOnFail = redirectOnFail !== false; // default true
    App.ajax({
      url:    '/auth/check.php',
      method: 'GET',
      loader: false,
      silent: true,
      onSuccess: function (data) {
        // Session valid — optionally use data.user
        App.auth.currentUser = data.user || null;
      },
      onError: function (msg, xhr) {
        if (redirectOnFail) {
         App.auth.handleUnauthorized();
        }
      }
    });
  },

  handleUnauthorized: function () {
    App.toast.warning('Session Expired', 'Please log in again.');
    setTimeout(function () {
      window.location.href = App.config.baseUrl + '/login.php';
    }, 1200);
  },

  logout: function () {
    App.ajax({
      url:       '/auth/logout.php',
      method:    'POST',
      loaderMsg: 'Logging out…',
      onSuccess: function () {
        window.location.href = App.config.baseUrl + '/login.php';
      }
    });
  },

  /**
   * Verifies if the current user has a specific role.
   * If the check fails, the user is redirected or blocked.
   * @param {string} roleName - e.g., 'admin', 'staff'
   */
  role: function (roleName) {
    App.ajax({
      url: '/auth/check-role.php',
      method: 'POST',
      data: { role: roleName },
      loader: true, // Show loader because this is a page-access check
      silent: false, 
      onSuccess: function (response) {
        
      },
      onError: function (msg) {
        // User does not have the role or session is invalid
        App.toast.error('Access Denied', msg || 'You do not have permission to view this page.');
        
        // Redirect to dashboard or index after a short delay
        setTimeout(function () {
          window.location.href = App.config.baseUrl + '/dashboard.php';
        }, 1500);
      }
    });
  },

  currentUser: null
};

/* ------------------------------------------------------------------ */
/*  MODAL HELPERS                                                        */
/* ------------------------------------------------------------------ */
App.modal = {
  open: function (id) {
    var el = document.getElementById(id);
    if (el) el.classList.add('active');
    document.body.style.overflow = 'hidden';
  },
  close: function (id) {
    var el = document.getElementById(id);
    if (el) el.classList.remove('active');
    document.body.style.overflow = '';
  },
  closeAll: function () {
    document.querySelectorAll('.modal-backdrop').forEach(function (el) {
      el.classList.remove('active');
    });
    document.body.style.overflow = '';
  }
};

// Close modal when clicking backdrop
$(document).on('click', '.modal-backdrop', function (e) {
  if (e.target === this) App.modal.close(this.id);
});
$(document).on('click', '.modal-close, [data-close-modal]', function () {
  var target = $(this).data('close-modal') || $(this).closest('.modal-backdrop').attr('id');
  App.modal.close(target);
});

/* ------------------------------------------------------------------ */
/*  FORM HELPERS                                                        */
/* ------------------------------------------------------------------ */
App.form = {
  /**
   * Serialize a form to a plain object { name: value, ... }
   */
  toObject: function (formEl) {
    var obj = {};
    $(formEl).serializeArray().forEach(function (f) { obj[f.name] = f.value; });
    return obj;
  },

  /**
   * Validate required fields. Marks .form-group as has-error.
   * Returns true if valid.
   */
  validate: function (formEl) {
    var valid = true;
    $(formEl).find('[required]').each(function () {
      var val = $(this).val();
      var group = $(this).closest('.form-group');
      if (!val || val.trim() === '') {
        group.addClass('has-error');
        valid = false;
      } else {
        group.removeClass('has-error');
      }
    });
    return valid;
  },

  /**
   * Clear all validation errors on a form
   */
  clearErrors: function (formEl) {
    $(formEl).find('.form-group').removeClass('has-error');
  },

  /**
   * Set a server error on a specific field
   */
  setError: function (fieldName, message) {
    var field = $('[name="' + fieldName + '"]');
    var group = field.closest('.form-group');
    group.addClass('has-error');
    group.find('.form-error').text(message).show();
  },

  /**
   * Reset a form and clear validation state
   */
  reset: function (formEl) {
    formEl.reset ? formEl.reset() : $(formEl)[0].reset();
    App.form.clearErrors(formEl);
  }
};

/* ------------------------------------------------------------------ */
/*  UTILITIES                                                           */
/* ------------------------------------------------------------------ */
App.utils = {
  escHtml: function (str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  },

  formatDate: function (dateStr) {
    if (!dateStr) return '—';
    var d = new Date(dateStr);
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
  },

  formatDateTime: function (dateStr) {
    if (!dateStr) return '—';
    var d = new Date(dateStr);
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) +
           ' ' + d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
  },

  formatCurrency: function (amount, symbol) {
    symbol = symbol || 'PKR ';
    return symbol + Number(amount || 0).toLocaleString('en-PK', { minimumFractionDigits: 2 });
  },

  debounce: function (fn, delay) {
    var t;
    return function () {
      clearTimeout(t);
      t = setTimeout(fn.bind(this, ...arguments), delay);
    };
  },

  confirm: function (message, onConfirm, onCancel) {
    // Use the custom confirm modal if present, else native
    if (document.getElementById('confirm-modal')) {
      $('#confirm-modal .confirm-message').text(message);
      App.modal.open('confirm-modal');
      $('#confirm-ok').off('click').on('click', function () {
        App.modal.close('confirm-modal');
        if (typeof onConfirm === 'function') onConfirm();
      });
      $('#confirm-cancel').off('click').on('click', function () {
        App.modal.close('confirm-modal');
        if (typeof onCancel === 'function') onCancel();
      });
    } else {
      if (window.confirm(message)) {
        if (typeof onConfirm === 'function') onConfirm();
      } else {
        if (typeof onCancel === 'function') onCancel();
      }
    }
  }
};

/* ------------------------------------------------------------------ */
/*  SIDEBAR TOGGLE (mobile)                                             */
/* ------------------------------------------------------------------ */
$(document).on('click', '#sidebar-toggle', function () {
  $('#sidebar').toggleClass('open');
  $('#sidebar-overlay').toggleClass('active');
});
$(document).on('click', '#sidebar-overlay', function () {
  $('#sidebar').removeClass('open');
  $('#sidebar-overlay').removeClass('active');
});

/* Nav sub-menu toggle */
$(document).on('click', '.nav-item.has-sub', function (e) {
  e.preventDefault();
  $(this).toggleClass('open');
});

/* Dropdown toggle */
$(document).on('click', '[data-toggle="dropdown"]', function (e) {
  e.stopPropagation();
  var menu = $(this).next('.dropdown-menu');
  $('.dropdown-menu').not(menu).removeClass('open');
  menu.toggleClass('open');
});
$(document).on('click', function () {
  $('.dropdown-menu').removeClass('open');
});

/* ------------------------------------------------------------------ */
/*  GLOBAL READY                                                        */
/* ------------------------------------------------------------------ */
$(document).ready(function () {
  // Inject loader and toast container if not in HTML
  if (!document.getElementById('global-loader')) {
    $('body').append(
      '<div id="global-loader">' +
        '<div class="loader-spinner"></div>' +
        '<div class="loader-text">Please wait…</div>' +
      '</div>'
    );
  }
  if (!document.getElementById('toast-container')) {
    $('body').append('<div id="toast-container"></div>');
  }

  // Hook jQuery's global AJAX events to auto-show/hide loader
  // Only for requests that set loaderEnabled = true in their settings
  $(document)
    .ajaxStart(function () { /* loader managed per-request */ })
    .ajaxStop(function ()  { /* loader managed per-request */ });
});
