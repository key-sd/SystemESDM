</main>
</div>

<script>window.APP_BASE_URL = <?= json_encode(rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/')) ?>;</script>
<script
    src="<?= e(urlApp('js/main.js')) ?>?v=<?= filemtime(__DIR__ . '/../js/main.js') ?>"
></script>

<?php foreach ($scripts ?? [] as $script): ?>
    <?php
    $esRemoto = str_starts_with($script, 'http://') || str_starts_with($script, 'https://');
    $version = $esRemoto ? '' : '?v=' . filemtime(__DIR__ . '/../' . ltrim($script, '/'));
    $ruta = $esRemoto ? $script : urlApp($script);
    ?>
    <script src="<?= e($ruta . $version) ?>"></script>
<?php endforeach; ?>
</body>
</html>
