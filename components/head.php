<?php
$titulo = $titulo ?? "ELIM SDM"; ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?></title>
    <link rel="icon" href="/SystemESDM/img/icono.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap"
        rel="stylesheet"
    >
    <link
        href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css"
        rel="stylesheet"
    >
    <link
        rel="stylesheet"
        href="/SystemESDM/css/app.css?v=<?= filemtime(__DIR__ . '/../css/app.css') ?>"
    >
    <?php foreach ($styles ?? [] as $style): ?>
        <?php
        $esRemoto = str_starts_with($style, 'http://') || str_starts_with($style, 'https://');
        $version = $esRemoto ? '' : '?v=' . filemtime(__DIR__ . '/../' . ltrim($style, '/'));
        $ruta = $esRemoto ? $style : '/SystemESDM/' . ltrim($style, '/');
        ?>
        <link rel="stylesheet" href="<?= e($ruta . $version) ?>">
    <?php endforeach; ?>
</head>
<body>
<div class="app-shell">
