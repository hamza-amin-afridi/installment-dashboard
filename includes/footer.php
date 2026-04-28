<?php
/**
 * Shared Footer
 * Closes main-wrapper and loads global scripts.
 */
?>
  </main><!-- /.page-content -->
</div><!-- /.main-wrapper -->

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<?php if (isset($extraJs)) echo $extraJs; ?>
</body>
</html>
