<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Dental App</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/global.css">
  <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>

<div class="auth-page">
  <div class="auth-card">

    <!-- Brand -->
    <div class="auth-brand">
      <div class="auth-logo">
        <img src="assets/images/logo.jpg" alt="">
      </div>
      <!-- <div class="auth-company-name">DentalPro</div>
      <div class="auth-company-tagline">Clinic Management System</div> -->
    </div>

    <!-- Heading -->
    <div class="auth-title">Welcome back</div>
    <div class="auth-subtitle">Sign in to your account to continue</div>

    <!-- Alert area -->
    <div id="auth-message"></div>

    <!-- Login Form -->
    <form id="login-form" autocomplete="off" novalidate>

      <div class="form-group" id="group-email">
        <label class="form-label" for="email">Email Address <span class="required">*</span></label>
        <div class="input-group">
          <i class="fa-regular fa-envelope input-icon"></i>
          <input
            type="email"
            id="email"
            name="email"
            class="form-control"
            placeholder="you@example.com"
            autocomplete="email"
            required
          >
        </div>
        <span class="form-error">Please enter a valid email address.</span>
      </div>

      <div class="form-group" id="group-password">
        <label class="form-label" for="password">Password <span class="required">*</span></label>
        <div class="input-group icon-right">
          <input
            type="password"
            id="password"
            name="password"
            class="form-control"
            placeholder="Enter your password"
            autocomplete="current-password"
            required
          >
          <span class="input-icon-right" id="toggle-password" title="Show/hide password">
            <i class="fa-regular fa-eye" id="eye-icon"></i>
          </span>
        </div>
        <span class="form-error">Password is required.</span>
      </div>

      <div class="auth-options">
        <label class="form-check" style="cursor:pointer">
          <input type="checkbox" id="remember-me" name="remember_me">
          <span class="form-check-label">Remember me</span>
        </label>
        <a href="forgot-password.php" class="auth-forgot">Forgot password?</a>
      </div>

      <button type="submit" class="btn btn-primary auth-submit" id="login-btn">
        <span class="btn-spinner"></span>
        <span class="btn-text">Sign In</span>
      </button>

    </form>

    <div class="auth-footer">
      &copy; <?= date('Y') ?> Ouray Dental &mdash; All rights reserved
    </div>

  </div><!-- /.auth-card -->
</div><!-- /.auth-page -->

<!-- Toast container -->
<div id="toast-container"></div>
<!-- Global loader -->
<div id="global-loader">
  <div class="loader-spinner"></div>
  <div class="loader-text">Signing in…</div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="assets/js/app.js"></script>
<script>
$(document).ready(function () {

  /* ---------- Toggle password visibility ---------- */
  $('#toggle-password').on('click', function () {
    var input = $('#password');
    var icon  = $('#eye-icon');
    if (input.attr('type') === 'password') {
      input.attr('type', 'text');
      icon.removeClass('fa-eye').addClass('fa-eye-slash');
    } else {
      input.attr('type', 'password');
      icon.removeClass('fa-eye-slash').addClass('fa-eye');
    }
  });

  /* ---------- Login form submit ---------- */
  $('#login-form').on('submit', function (e) {
    e.preventDefault();

    // Clear previous errors
    App.form.clearErrors(this);
    $('#auth-message').html('');

    // Front-end validation
    var email    = $.trim($('#email').val());
    var password = $.trim($('#password').val());
    var valid    = true;

    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      $('#group-email').addClass('has-error');
      valid = false;
    }
    if (!password) {
      $('#group-password').addClass('has-error');
      valid = false;
    }
    if (!valid) return;

    /* ---------- AJAX login ---------- */
    App.ajax({
      url:       '/auth/login.php',
      method:    'POST',
      data:      { email: email, password: password, remember_me: $('#remember-me').is(':checked') ? 1 : 0 },
      loader:    true,
      loaderMsg: 'Signing in…',
      btn:       $('#login-btn'),
      onSuccess: function (data, message) {
        // Show success briefly then redirect
        console.log(data)
        $('#auth-message').html(
          '<div class="alert alert-success">' +
            '<i class="fa-solid fa-circle-check"></i>' +
            '<div class="alert-content"><div class="alert-title">Success</div>' +
            '<div class="alert-body">Welcome back, ' + App.utils.escHtml(data.name) + '!</div></div>' +
          '</div>'
        );
        setTimeout(function () {
          window.location.href = 'dashboard.php';
        }, 800);
      },
      onError: function (msg) {
        $('#auth-message').html(
          '<div class="alert alert-danger">' +
            '<i class="fa-solid fa-circle-xmark"></i>' +
            '<div class="alert-content"><div class="alert-body">' + App.utils.escHtml(msg) + '</div></div>' +
          '</div>'
        );
      }
    });
  });

  /* ---------- Clear error on input ---------- */
  $('#email, #password').on('input', function () {
    $(this).closest('.form-group').removeClass('has-error');
  });

});
</script>
</body>
</html>
