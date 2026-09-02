<?php

require_once __DIR__ . "/_bootstrap.php";

$hoy = new DateTimeImmutable("today");
$periodos = [
    "4-semanas" => "Últimas 4 semanas",
    "6-meses" => "Últimos 6 meses",
    "1-anio" => "Último año",
    "personalizado" => "Periodo personalizado",
];
$periodo = $_GET["periodo"] ?? "4-semanas";

if (!array_key_exists($periodo, $periodos)) {
    $periodo = "4-semanas";
}

$inicio = $hoy->modify("-27 days");
$fin = $hoy;

if ($periodo === "6-meses") {
    $inicio = $hoy->modify("first day of this month")->modify("-5 months");
} elseif ($periodo === "1-anio") {
    $inicio = $hoy->modify("-1 year +1 day");
} elseif ($periodo === "personalizado") {
    $inicioPersonalizado = DateTimeImmutable::createFromFormat(
        "!Y-m-d",
        $_GET["inicio"] ?? "",
    );
    $finPersonalizado = DateTimeImmutable::createFromFormat(
        "!Y-m-d",
        $_GET["fin"] ?? "",
    );

    if ($inicioPersonalizado && $finPersonalizado && $inicioPersonalizado <= $finPersonalizado) {
        $inicio = $inicioPersonalizado;
        $fin = $finPersonalizado;
    } else {
        $periodo = "4-semanas";
    }
}

$inicioSql = $inicio->format("Y-m-d");
$finSql = $fin->format("Y-m-d");
$fechaInicio = $inicio->format("Y-m-d");
$fechaFin = $fin->format("Y-m-d");

$consulta = $conexion->prepare(
    'SELECT
        s.nombre,
        COALESCE(SUM(r.total_asistencia), 0) AS asistencia,
        COALESCE(SUM(r.conversion), 0) AS conversiones,
        COALESCE(SUM(r.bautismo_agua), 0) AS bautismos,
        COALESCE(SUM(r.ofrenda_total), 0) AS ofrenda
     FROM sectores s
     LEFT JOIN celulas c ON c.sector_id = s.id
     LEFT JOIN reportes_lider r
        ON r.celula_id = c.id
        AND r.fecha_reporte BETWEEN ? AND ?
     GROUP BY s.id, s.nombre
     ORDER BY s.nombre',
);
$consulta->execute([$inicioSql, $finSql]);
$estadisticas = $consulta->fetchAll();

$nombres = array_column($estadisticas, "nombre");
$asistencias = array_map(
    fn (array $fila): int => (int) $fila["asistencia"],
    $estadisticas,
);

abrirPaginaAdmin("estadisticas", "Estadísticas generales");
?>

<section class="card card-flush">
    <div class="card-head">
        <div>
            <h3>Periodo de análisis</h3>
        </div>
    </div>

    <form method="get" class="stats-filters" id="formFiltrosEstadisticas">
        <div>
            <label for="periodo">Periodo rápido</label>
            <select id="periodo" name="periodo" data-stats-period>
                <?php foreach ($periodos as $valor => $etiqueta): ?>
                    <option value="<?= $valor ?>" <?= $periodo === $valor ? "selected" : "" ?>>
                        <?= e($etiqueta) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="stats-custom-range <?= $periodo === "personalizado" ? "visible" : "" ?>" data-custom-range>
            <div>
                <label for="inicio">Desde</label>
                <input id="inicio" type="date" name="inicio" value="<?= e($fechaInicio) ?>">
            </div>
            <div>
                <label for="fin">Hasta</label>
                <input id="fin" type="date" name="fin" value="<?= e($fechaFin) ?>">
            </div>
        </div>

        <button class="btn btn-gold" type="submit">
            <i class="ri-filter-3-line"></i> Aplicar
        </button>
    </form>
</section>

<div class="grid-2">
    <section class="card card-flush">
        <h3>Asistencia por sector</h3>
        <canvas
            id="chartStats"
            data-labels="<?= e(json_encode($nombres)) ?>"
            data-values="<?= e(json_encode($asistencias)) ?>"
        ></canvas>
    </section>

    <section class="card card-flush">
        <h3>Indicadores del periodo</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Sector</th>
                        <th>Asistencia</th>
                        <th>Conversiones</th>
                        <th>Bautismos</th>
                        <th>Ofrenda</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($estadisticas as $sector): ?>
                        <tr>
                            <td><strong><?= e($sector["nombre"]) ?></strong></td>
                            <td><?= $sector["asistencia"] ?></td>
                            <td><?= $sector["conversiones"] ?></td>
                            <td><?= $sector["bautismos"] ?></td>
                            <td>$<?= number_format($sector["ofrenda"], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php
cerrarPaginaAdmin([
    "https://cdn.jsdelivr.net/npm/chart.js@4.4.7",
    "js/admin.js",
]);
