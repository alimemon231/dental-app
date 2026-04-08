<?php
/**
 * includes/page-footer.php
 * Include at the bottom of every protected page.
 *
 * Usage:
 *   $extraScripts = '<script src="/assets/js/patients.js"></script>';  // optional
 *   require_once __DIR__ . '/../includes/page-footer.php';
 */
?>
    </div><!-- /.page-wrapper -->
  </main><!-- /.main-content -->

</div><!-- /.app-shell -->

<!-- Reusable confirm modal — available on every page -->
<div class="modal-backdrop" id="confirm-modal">
  <div class="modal modal-sm">
    <div class="modal-header">
      <div class="modal-title"><i class="fa-solid fa-triangle-exclamation" style="color:var(--color-warning)"></i> Confirm Action</div>
      <button class="modal-close" data-close-modal="confirm-modal">&#x2715;</button>
    </div>
    <div class="modal-body">
      <p class="confirm-message" style="font-size:var(--font-size-base);line-height:1.6">Are you sure you want to proceed?</p>
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

<!-- Core JS (jQuery + App) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="/assets/js/app.js"></script>

<!-- Auth check on  every page load -->
<script>
$(document).ready(function () {
  App.auth.check();
});
</script>

<?= $extraScripts ?? '' ?>

</body>
</html>
