<?php
/**
 * NestSync — Dashboard Layout Footer
 * Closes: .main-content → .wrapper → body → html
 * Also outputs flash messages and loads JS
 */
$_flash = getFlash();
?>

</div><!-- /.content-area -->
</div><!-- /.main-content -->
</div><!-- /.wrapper -->

<!-- ===== SCRIPTS ===== -->
<!-- Bootstrap 5 Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- NestSync Custom JS -->
<script src="<?= BASE_URL ?>/public/js/main.js"></script>

<!-- Flash Message (toast) -->
<?php if ($_flash): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        showToast(
            <?= json_encode($_flash['message'], JSON_HEX_TAG) ?>,
            <?= json_encode($_flash['type'],    JSON_HEX_TAG) ?>
        );
    });
</script>
<?php endif; ?>

</body>
</html>
