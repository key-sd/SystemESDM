<?php

require_once __DIR__ . "/_bootstrap.php";

$totalUsuarios = (int) $conexion
    ->query("SELECT COUNT(*) FROM usuarios WHERE activo = 1")
    ->fetchColumn();

$totalSectores = (int) $conexion
    ->query("SELECT COUNT(*) FROM sectores WHERE activo = 1")
    ->fetchColumn();

$totalCelulas = (int) $conexion
    ->query("SELECT COUNT(*) FROM celulas WHERE activo = 1")
    ->fetchColumn();

$consultaAsistencia = $conexion->prepare(
    'SELECT COALESCE(SUM(total_asistencia), 0)
     FROM reportes_lider
     WHERE fecha_reporte >= ?',
);
$consultaAsistencia->execute([semanaActual()]);
$asistenciaSemana = (int) $consultaAsistencia->fetchColumn();

$actividadReciente = $conexion
    ->query(
        'SELECT
        r.enviado_en,
        u.nombre_completo,
        c.nombre AS celula
     FROM reportes_lider r
     INNER JOIN usuarios u ON u.id = r.lider_id
     INNER JOIN celulas c ON c.id = r.celula_id
     ORDER BY r.enviado_en DESC
     LIMIT 6',
    )
    ->fetchAll();

$datosGrafica = $conexion
    ->query(
        "SELECT
        s.nombre,
        DATE_FORMAT(r.fecha_reporte, '%d/%m') AS semana,
        SUM(r.total_asistencia) AS total
     FROM reportes_lider r
     INNER JOIN celulas c ON c.id = r.celula_id
     INNER JOIN sectores s ON s.id = c.sector_id
     WHERE r.fecha_reporte >= DATE_SUB(CURDATE(), INTERVAL 6 WEEK)
     GROUP BY s.id, s.nombre, r.fecha_reporte
     ORDER BY r.fecha_reporte",
    )
    ->fetchAll();

$etiquetas = array_values(array_unique(array_column($datosGrafica, "semana")));
$seriesPorSector = [];

foreach ($datosGrafica as $fila) {
    $seriesPorSector[$fila["nombre"]][$fila["semana"]] = (int) $fila["total"];
}

$colores = ["#010326", "#3061f2", "#2148bf", "#32378c"];
$series = [];
$indiceColor = 0;

foreach ($seriesPorSector as $nombre => $valores) {
    $series[] = [
        "label" => $nombre,
        "data" => array_map(
            fn(string $etiqueta): int => $valores[$etiqueta] ?? 0,
            $etiquetas,
        ),
        "borderColor" => $colores[$indiceColor % count($colores)],
        "tension" => 0.35,
    ];
    $indiceColor++;
}

abrirPaginaAdmin(
    "resumen",
    "Resumen general"
);
?>

<div class="grid-4">
    <div class="stat">
        <div class="label">Asistencia semanal</div>
        <div class="value"><?= $asistenciaSemana ?></div>
    </div>
    <div class="stat">
        <div class="label">Sectores activos</div>
        <div class="value"><?= $totalSectores ?></div>
    </div>
    <div class="stat">
        <div class="label">Células activas</div>
        <div class="value"><?= $totalCelulas ?></div>
    </div>
    <div class="stat">
        <div class="label">Usuarios activos</div>
        <div class="value"><?= $totalUsuarios ?></div>
    </div>
</div>

<div class="grid-2">
    <section class="card">
        <div class="card-head">
            <div>
                <h3>Comparativa por sector</h3>
                <p class="muted">Asistencia registrada durante las últimas semanas</p>
            </div>
        </div>
        <canvas
            id="chartResumen"
            data-labels="<?= e(json_encode($etiquetas)) ?>"
            data-series="<?= e(json_encode($series)) ?>"
        ></canvas>
    </section>

    <section class="card">
        <div class="card-head">
            <div>
                <h3>Actividad reciente</h3>
                <p class="muted">Últimos reportes enviados</p>
            </div>
        </div>

        <?php foreach ($actividadReciente as $actividad): ?>
            <div class="activity-row">
                <span>
                    <strong><?= e($actividad["nombre_completo"]) ?></strong>
                    · <?= e($actividad["celula"]) ?>
                </span>
                <span class="receipt"><?= fechaBonita(
                    $actividad["enviado_en"],
                ) ?></span>
            </div>
        <?php endforeach; ?>

        <?php if (!$actividadReciente): ?>
            <div class="empty">Aún no hay reportes recibidos.</div>
        <?php endif; ?>
    </section>
</div>

<?php
cerrarPaginaAdmin([
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.7',
    'js/admin.js',
]);
?>
