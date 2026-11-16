<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/auth/session.php';

$extraJs = $extra_js ?? [];
?>
<footer class="site-footer">
    <div class="container site-footer-inner">
        <p>ByteBack · Lost and found for Bicol University</p>
        <p>&copy; <?= e((string) date('Y')) ?></p>
    </div>
</footer>
<script src="<?= e(url('assets/js/main.js')) ?>" defer></script>
<?php foreach ($extraJs as $src): ?>
    <script src="<?= e((string) $src) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
