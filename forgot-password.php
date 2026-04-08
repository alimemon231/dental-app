<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password — Dental App</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/global.css">
  <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>

<div class="auth-page">
  <div class="auth-card">

    <div class="auth-brand">
      <div class="auth-logo"><i class="fa-solid fa-tooth"></i></div>
      <div class="auth-company-name">DentalPro</div>
      <div class="auth-company-tagline">Password Recovery</div>
    </div>

    <!-- Step indicator -->
    <div class="auth-steps" id="step-indicator">
      <div class="auth-step active" id="step-dot-1">
        <div class="step-dot">1</div><div class="step-label">Email</div>
      </div>
      <div class="auth-step" id="step-dot-2">
        <div class="step-dot">2</div><div class="step-label">Verify Code</div>
      </div>
      <div class="auth-step" id="step-dot-3">
        <div class="step-dot">3</div><div class="step-label">New Password</div>
      </div>
    </div>

    <div id="fp-message"></div>

    <!-- STEP 1: Email -->
    <div id="step-1">
      <div class="auth-title">Forgot your password?</div>
      <div class="auth-subtitle">Enter your email and we'll send you a reset code.</div>
      <form id="fp-email-form" novalidate>
        <div class="form-group" id="group-fp-email">
          <label class="form-label" for="fp-email">Email Address <span class="required">*</span></label>
          <div class="input-group">
            <i class="fa-regular fa-envelope input-icon"></i>
            <input type="email" id="fp-email" name="email" class="form-control" placeholder="you@example.com" required>
          </div>
          <span class="form-error">Please enter a valid email address.</span>
        </div>
        <button type="submit" class="btn btn-primary auth-submit" id="send-code-btn">
          <span class="btn-spinner"></span>
          <span class="btn-text">Send Reset Code</span>
        </button>
      </form>
    </div>

    <!-- STEP 2: OTP -->
    <div id="step-2" class="hidden">
      <div class="auth-title">Enter reset code</div>
      <div class="auth-subtitle" id="otp-subtitle">A 6-digit code was sent to your email.</div>
      <form id="fp-otp-form" novalidate>
        <div class="otp-group" id="otp-inputs">
          <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric">
          <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric">
          <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric">
          <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric">
          <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric">
          <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric">
        </div>
        <div class="otp-timer">Code expires in <span id="otp-countdown">5:00</span></div>
        <div class="text-center mb-6">
          <button type="button" class="otp-resend" id="otp-resend-btn">Resend Code</button>
        </div>
        <button type="submit" class="btn btn-primary auth-submit" id="verify-code-btn">
          <span class="btn-spinner"></span>
          <span class="btn-text">Verify Code</span>
        </button>
      </form>
    </div>

    <!-- STEP 3: New password -->
    <div id="step-3" class="hidden">
      <div class="auth-title">Set new password</div>
      <div class="auth-subtitle">Choose a strong password (minimum 8 characters).</div>
      <form id="fp-password-form" novalidate>
        <div class="form-group" id="group-new-pass">
          <label class="form-label" for="new-password">New Password <span class="required">*</span></label>
          <div class="input-group icon-right">
            <input type="password" id="new-password" name="new_password" class="form-control" placeholder="Min. 8 characters" required>
            <span class="input-icon-right toggle-pass"><i class="fa-regular fa-eye"></i></span>
          </div>
          <span class="form-error">Password must be at least 8 characters.</span>
        </div>
        <div class="form-group" id="group-confirm-pass">
          <label class="form-label" for="confirm-password">Confirm Password <span class="required">*</span></label>
          <div class="input-group icon-right">
            <input type="password" id="confirm-password" name="confirm_password" class="form-control" placeholder="Re-enter new password" required>
            <span class="input-icon-right toggle-pass"><i class="fa-regular fa-eye"></i></span>
          </div>
          <span class="form-error">Passwords do not match.</span>
        </div>
        <button type="submit" class="btn btn-primary auth-submit" id="reset-pass-btn">
          <span class="btn-spinner"></span>
          <span class="btn-text">Reset Password</span>
        </button>
      </form>
    </div>

    <a href="login.php" class="auth-back"><i class="fa-solid fa-arrow-left"></i> Back to Login</a>
    <div class="auth-footer">&copy; 2025 DentalPro. All rights reserved.</div>

  </div>
</div>

<div id="toast-container"></div>
<div id="global-loader"><div class="loader-spinner"></div><div class="loader-text">Please wait…</div></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="assets/js/app.js"></script>
<script>
$(document).ready(function () {
  var userEmail = '', countdownTimer = null;

  function goStep(n) {
    $('#step-1,#step-2,#step-3').addClass('hidden');
    $('#step-' + n).removeClass('hidden');
    $('#fp-message').html('');
    for (var i = 1; i <= 3; i++) {
      var dot = $('#step-dot-' + i);
      dot.removeClass('active done');
      if (i < n)       { dot.addClass('done');   dot.find('.step-dot').html('<i class="fa-solid fa-check" style="font-size:10px"></i>'); }
      else if (i === n){ dot.addClass('active'); dot.find('.step-dot').text(i); }
      else             { dot.find('.step-dot').text(i); }
    }
  }

  function showMsg(type, body) {
    var icon = type === 'success' ? 'fa-circle-check' : 'fa-circle-xmark';
    $('#fp-message').html('<div class="alert alert-'+type+'"><i class="fa-solid '+icon+'"></i><div class="alert-content"><div class="alert-body">'+App.utils.escHtml(body)+'</div></div></div>');
  }

  /* Step 1 */
  $('#fp-email-form').on('submit', function (e) {
    e.preventDefault();
    var email = $.trim($('#fp-email').val());
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { $('#group-fp-email').addClass('has-error'); return; }
    userEmail = email;
    App.ajax({
      url: '/auth/forgot-password.php', method: 'POST',
      data: { action: 'send_code', email: email }, btn: $('#send-code-btn'), loaderMsg: 'Sending code…',
      onSuccess: function(d, msg) {
        $('#otp-subtitle').text('A 6-digit code was sent to ' + email);
        goStep(2); startCountdown(300); $('.otp-input').first().focus();
        App.toast.success('Code Sent', msg);
      }
    });
  });

  /* OTP input behaviour */
  $(document).on('input', '.otp-input', function () {
    $(this).val($(this).val().replace(/\D/g,''));
    if ($(this).val().length === 1) $(this).next('.otp-input').focus();
  });
  $(document).on('keydown', '.otp-input', function (e) {
    if (e.key === 'Backspace' && !$(this).val()) $(this).prev('.otp-input').focus();
  });
  $('#otp-inputs').on('paste', function (e) {
    var text = (e.originalEvent.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'');
    if (text.length === 6) $('.otp-input').each(function(i){ $(this).val(text[i]||''); });
    e.preventDefault();
  });

  /* Step 2 */
  $('#fp-otp-form').on('submit', function (e) {
    e.preventDefault();
    var code = ''; $('.otp-input').each(function(){ code += $(this).val(); });
    if (code.length !== 6) { showMsg('danger','Please enter the full 6-digit code.'); return; }
    App.ajax({
      url: '/auth/forgot-password.php', method: 'POST',
      data: { action: 'verify_code', email: userEmail, code: code }, btn: $('#verify-code-btn'),
      onSuccess: function(d,msg){ clearInterval(countdownTimer); goStep(3); App.toast.success('Verified', msg); }
    });
  });

  /* Step 3 */
  $('#fp-password-form').on('submit', function (e) {
    e.preventDefault();
    var np = $('#new-password').val(), cp = $('#confirm-password').val(), valid = true;
    $('#group-new-pass,#group-confirm-pass').removeClass('has-error');
    if (np.length < 8) { $('#group-new-pass').addClass('has-error'); valid = false; }
    if (np !== cp)     { $('#group-confirm-pass').addClass('has-error'); valid = false; }
    if (!valid) return;
    App.ajax({
      url: '/auth/forgot-password.php', method: 'POST',
      data: { action: 'reset_password', new_password: np, confirm_password: cp },
      btn: $('#reset-pass-btn'), loaderMsg: 'Resetting password…',
      onSuccess: function(d,msg){ App.toast.success('Done!','Redirecting to login…'); setTimeout(function(){ window.location.href='login.php'; },1500); }
    });
  });

  /* Countdown */
  function startCountdown(sec) {
    var r = sec; $('#otp-countdown').text(fmt(r)); $('#otp-resend-btn').removeClass('visible');
    countdownTimer = setInterval(function(){
      r--;
      if (r <= 0) { clearInterval(countdownTimer); $('.otp-timer').html('Code has expired.'); $('#otp-resend-btn').addClass('visible'); return; }
      $('#otp-countdown').text(fmt(r));
    }, 1000);
  }
  function fmt(s){ return Math.floor(s/60)+':'+String(s%60).padStart(2,'0'); }

  /* Resend */
  $('#otp-resend-btn').on('click', function(){
    App.ajax({ url:'api/auth/forgot-password.php', method:'POST', data:{action:'send_code',email:userEmail}, loader:false,
      onSuccess: function(d,msg){ $('.otp-input').val('').first().focus(); startCountdown(300); App.toast.info('Resent',msg); }
    });
  });

  /* Toggle password */
  $(document).on('click','.toggle-pass',function(){
    var inp=$(this).closest('.input-group').find('input');
    var ico=$(this).find('i');
    inp.attr('type', inp.attr('type')==='password'?'text':'password');
    ico.toggleClass('fa-eye fa-eye-slash');
  });
});
</script>
</body>
</html>
