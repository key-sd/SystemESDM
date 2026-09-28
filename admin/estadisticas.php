<?php

require_once __DIR__ . "/_bootstrap.php";

$hoy = new DateTimeImmutable("today");

$modulos = [
    "todas" => "Todas las estadísticas",
    "celulas" => "Reporte de células",
    "servicios" => "Servicios de iglesia",
];
$modulo = $_GET["modulo"] ?? "todas";
if (!array_key_exists($modulo, $modulos)) {
    $modulo = "todas";
}

$periodos = [
    "4-semanas" => "Últimas 4 semanas",
    "3-meses" => "Últimos 3 meses",
    "4-meses" => "Últimos 4 meses",
    "6-meses" => "Últimos 6 meses",
    "1-anio" => "Último año",
    "personalizado" => "Personalizado",
];
$periodo = $_GET["periodo"] ?? "3-meses";
if (!array_key_exists($periodo, $periodos)) {
    $periodo = "3-meses";
}

$inicio = match ($periodo) {
    "4-semanas" => $hoy->modify("-27 days"),
    "3-meses" => $hoy->modify("first day of this month")->modify("-2 months"),
    "4-meses" => $hoy->modify("first day of this month")->modify("-3 months"),
    "6-meses" => $hoy->modify("first day of this month")->modify("-5 months"),
    "1-anio" => $hoy->modify("-1 year +1 day"),
    "personalizado" => DateTimeImmutable::createFromFormat("!Y-m-d", $_GET["inicio"] ?? "") ?: $hoy->modify("-27 days"),
    default => $hoy->modify("first day of this month")->modify("-2 months"),
};

$fin = match ($periodo) {
    "personalizado" => DateTimeImmutable::createFromFormat("!Y-m-d", $_GET["fin"] ?? "") ?: $hoy,
    default => $hoy,
};

if ($inicio > $fin) {
    [$inicio, $fin] = [$fin, $inicio];
}

$inicioSql = $inicio->format("Y-m-d");
$finSql = $fin->format("Y-m-d");
$fechaInicio = $inicio->format("Y-m-d");
$fechaFin = $fin->format("Y-m-d");

$sectorId = filter_input(INPUT_GET, "sector_id", FILTER_VALIDATE_INT) ?: 0;
$tipoServicio = $_GET["tipo_servicio"] ?? "";
if (!in_array($tipoServicio, ["", "domingo", "jueves", "martes"], true)) {
    $tipoServicio = "";
}

$sectores = $conexion->query("SELECT id, nombre FROM sectores WHERE activo = 1 ORDER BY nombre")->fetchAll();

$nombreSector = "";
if ($sectorId > 0) {
    foreach ($sectores as $sec) {
        if ((int) $sec["id"] === $sectorId) {
            $nombreSector = $sec["nombre"];
            break;
        }
    }
}

// --------------------------------------------------
// DATOS DE CÉLULAS
// --------------------------------------------------
$estadisticasCelulas = [];
$totalesCelulas = [
    "asistencia" => 0,
    "ofrenda" => 0.0,
    "conversiones" => 0,
    "bautismos" => 0,
    "reportes" => 0,
];

if ($modulo === "todas" || $modulo === "celulas") {
    if ($sectorId > 0) {
        $sqlCelulas = "SELECT
            c.id,
            c.nombre,
            c.tipo,
            u.nombre_completo AS lider,
            COALESCE(SUM(r.total_asistencia), 0) AS asistencia,
            COALESCE(SUM(r.ofrenda_total), 0) AS ofrenda,
            COALESCE(SUM(r.conversion), 0) AS conversiones,
            COALESCE(SUM(r.bautismo_agua), 0) AS bautismos,
            COUNT(r.id) AS reportes
         FROM celulas c
         LEFT JOIN usuarios u ON u.id = c.lider_id
         LEFT JOIN reportes_lider r ON r.celula_id = c.id AND r.fecha_reporte BETWEEN ? AND ?
         WHERE c.sector_id = ? AND c.activo = 1
         GROUP BY c.id, c.nombre, c.tipo, u.nombre_completo
         ORDER BY c.nombre";
        $stmtCel = $conexion->prepare($sqlCelulas);
        $stmtCel->execute([$inicioSql, $finSql, $sectorId]);
        $estadisticasCelulas = $stmtCel->fetchAll();
    } else {
        $sqlCelulas = "SELECT
            s.id,
            s.nombre,
            COALESCE(SUM(r.total_asistencia), 0) AS asistencia,
            COALESCE(SUM(r.ofrenda_total), 0) AS ofrenda,
            COALESCE(SUM(r.conversion), 0) AS conversiones,
            COALESCE(SUM(r.bautismo_agua), 0) AS bautismos,
            COUNT(r.id) AS reportes
         FROM sectores s
         LEFT JOIN celulas c ON c.sector_id = s.id AND c.activo = 1
         LEFT JOIN reportes_lider r ON r.celula_id = c.id AND r.fecha_reporte BETWEEN ? AND ?
         WHERE s.activo = 1
         GROUP BY s.id, s.nombre
         ORDER BY s.nombre";
        $stmtCel = $conexion->prepare($sqlCelulas);
        $stmtCel->execute([$inicioSql, $finSql]);
        $estadisticasCelulas = $stmtCel->fetchAll();
    }

    foreach ($estadisticasCelulas as $fila) {
        $totalesCelulas["asistencia"] += (int) $fila["asistencia"];
        $totalesCelulas["ofrenda"] += (float) $fila["ofrenda"];
        $totalesCelulas["conversiones"] += (int) $fila["conversiones"];
        $totalesCelulas["bautismos"] += (int) $fila["bautismos"];
        $totalesCelulas["reportes"] += (int) $fila["reportes"];
    }
}

$labelsCelulas = array_column($estadisticasCelulas, "nombre");
$valuesCelulas = array_map(fn(array $f): int => (int) $f["asistencia"], $estadisticasCelulas);

// --------------------------------------------------
// DATOS DE SERVICIOS
// --------------------------------------------------
$estadisticasServicios = [];
$totalesServicios = [
    "cantidad" => 0,
    "adultos" => 0,
    "ninos" => 0,
    "asistencia" => 0,
    "ofrenda" => 0.0,
    "diezmos" => 0.0,
    "ingresos" => 0.0,
];

if ($modulo === "todas" || $modulo === "servicios") {
    if ($tipoServicio !== "") {
        $sqlServicios = "SELECT
            id,
            fecha,
            tipo_servicio,
            asistencia_adultos AS adultos,
            asistencia_ninos AS ninos,
            asistencia_total AS asistencia,
            ofrenda,
            diezmos,
            (ofrenda + diezmos) AS total_ingresos,
            observaciones
         FROM servicios_iglesia
         WHERE fecha BETWEEN ? AND ? AND tipo_servicio = ?
         ORDER BY fecha ASC";
        $stmtServ = $conexion->prepare($sqlServicios);
        $stmtServ->execute([$inicioSql, $finSql, $tipoServicio]);
        $estadisticasServicios = $stmtServ->fetchAll();

        foreach ($estadisticasServicios as $fila) {
            $totalesServicios["cantidad"]++;
            $totalesServicios["adultos"] += (int) $fila["adultos"];
            $totalesServicios["ninos"] += (int) $fila["ninos"];
            $totalesServicios["asistencia"] += (int) $fila["asistencia"];
            $totalesServicios["ofrenda"] += (float) $fila["ofrenda"];
            $totalesServicios["diezmos"] += (float) $fila["diezmos"];
            $totalesServicios["ingresos"] += (float) $fila["total_ingresos"];
        }

        $labelsServicios = array_map(fn(array $f): string => date("d/m", strtotime($f["fecha"])), $estadisticasServicios);
        $valuesServicios = array_map(fn(array $f): int => (int) $f["asistencia"], $estadisticasServicios);
    } else {
        $sqlServicios = "SELECT
            tipo_servicio,
            COUNT(id) AS cantidad,
            COALESCE(SUM(asistencia_adultos), 0) AS adultos,
            COALESCE(SUM(asistencia_ninos), 0) AS ninos,
            COALESCE(SUM(asistencia_total), 0) AS asistencia,
            COALESCE(SUM(ofrenda), 0) AS ofrenda,
            COALESCE(SUM(diezmos), 0) AS diezmos,
            COALESCE(SUM(ofrenda + diezmos), 0) AS total_ingresos
         FROM servicios_iglesia
         WHERE fecha BETWEEN ? AND ?
         GROUP BY tipo_servicio
         ORDER BY FIELD(tipo_servicio, 'domingo', 'jueves', 'martes')";
        $stmtServ = $conexion->prepare($sqlServicios);
        $stmtServ->execute([$inicioSql, $finSql]);
        $estadisticasServicios = $stmtServ->fetchAll();

        foreach ($estadisticasServicios as $fila) {
            $totalesServicios["cantidad"] += (int) $fila["cantidad"];
            $totalesServicios["adultos"] += (int) $fila["adultos"];
            $totalesServicios["ninos"] += (int) $fila["ninos"];
            $totalesServicios["asistencia"] += (int) $fila["asistencia"];
            $totalesServicios["ofrenda"] += (float) $fila["ofrenda"];
            $totalesServicios["diezmos"] += (float) $fila["diezmos"];
            $totalesServicios["ingresos"] += (float) $fila["total_ingresos"];
        }

        $labelsServicios = array_map(fn(array $f): string => ucfirst($f["tipo_servicio"]), $estadisticasServicios);
        $valuesServicios = array_map(fn(array $f): int => (int) $f["asistencia"], $estadisticasServicios);
    }
}

abrirPaginaAdmin("estadisticas", "Estadísticas generales");
?>

<style>
.money-cell {
  font-family: monospace;
  font-size: 13px;
}
.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 28px 0 14px;
  font-size: 18px;
  color: var(--night);
}
.section-title i {
  color: var(--blue);
}
</style>

<!-- FILTROS DE ESTADÍSTICAS -->
<section class="card card-flush">
    <div class="card-head">
        <div>
            <p class="eyebrow">Análisis de datos</p>
            <h3>Filtros de estadísticas</h3>
        </div>
        <span class="badge ok"><?= $inicio->format("d/m/Y") ?> – <?= $fin->format("d/m/Y") ?></span>
    </div>

    <form method="get" class="stats-filters" id="formFiltrosEstadisticas">
        <div>
            <label for="modulo">Ver estadísticas de</label>
            <select id="modulo" name="modulo" data-stats-module>
                <?php foreach ($modulos as $val => $etq): ?>
                    <option value="<?= $val ?>" <?= $modulo === $val ? "selected" : "" ?>><?= e($etq) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="periodo">Periodo</label>
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

        <div data-stats-sector-box style="<?= $modulo === 'servicios' ? 'display:none;' : '' ?>">
            <label for="sector_id">Sector</label>
            <select id="sector_id" name="sector_id" data-stats-sector>
                <option value="0">Todos los sectores</option>
                <?php foreach ($sectores as $sec): ?>
                    <option value="<?= $sec["id"] ?>" <?= $sectorId === (int) $sec["id"] ? "selected" : "" ?>>
                        <?= e($sec["nombre"]) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div data-stats-service-box style="<?= $modulo === 'celulas' ? 'display:none;' : '' ?>">
            <label for="tipo_servicio">Servicio</label>
            <select id="tipo_servicio" name="tipo_servicio" data-stats-service>
                <option value="">Todos los servicios</option>
                <option value="domingo" <?= $tipoServicio === "domingo" ? "selected" : "" ?>>Domingo</option>
                <option value="jueves" <?= $tipoServicio === "jueves" ? "selected" : "" ?>>Jueves</option>
                <option value="martes" <?= $tipoServicio === "martes" ? "selected" : "" ?>>Martes</option>
            </select>
        </div>

        <button class="btn btn-gold" type="submit">
            <i class="ri-filter-3-line"></i> Filtrar
        </button>
    </form>
</section>

<!-- TARJETAS DE RESUMEN (KPIS) -->
<div class="grid-4">
    <?php if ($modulo === "todas"): ?>
        <div class="stat">
            <div class="label">Asistencia células</div>
            <div class="value"><?= number_format($totalesCelulas["asistencia"]) ?></div>
            <small class="muted">En <?= $totalesCelulas["reportes"] ?> reuniones</small>
        </div>
        <div class="stat">
            <div class="label">Ofrendas células</div>
            <div class="value">$<?= number_format($totalesCelulas["ofrenda"], 2) ?></div>
        </div>
        <div class="stat">
            <div class="label">Asistencia a cultos</div>
            <div class="value"><?= number_format($totalesServicios["asistencia"]) ?></div>
            <small class="muted">En <?= $totalesServicios["cantidad"] ?> servicios</small>
        </div>
        <div class="stat">
            <div class="label">Ingresos de cultos</div>
            <div class="value">$<?= number_format($totalesServicios["ingresos"], 2) ?></div>
            <small class="muted">Ofrendas + Diezmos</small>
        </div>
    <?php elseif ($modulo === "celulas"): ?>
        <div class="stat">
            <div class="label">Asistencia acumulada</div>
            <div class="value"><?= number_format($totalesCelulas["asistencia"]) ?></div>
            <small class="muted">En <?= $totalesCelulas["reportes"] ?> reuniones</small>
        </div>
        <div class="stat">
            <div class="label">Total ofrendas células</div>
            <div class="value">$<?= number_format($totalesCelulas["ofrenda"], 2) ?></div>
        </div>
        <div class="stat">
            <div class="label">Conversiones</div>
            <div class="value"><?= number_format($totalesCelulas["conversiones"]) ?></div>
        </div>
        <div class="stat">
            <div class="label">Bautismos</div>
            <div class="value"><?= number_format($totalesCelulas["bautismos"]) ?></div>
        </div>
    <?php else: ?>
        <div class="stat">
            <div class="label">Cultos realizados</div>
            <div class="value"><?= number_format($totalesServicios["cantidad"]) ?></div>
            <small class="muted">En el periodo</small>
        </div>
        <div class="stat">
            <div class="label">Asistencia total</div>
            <div class="value"><?= number_format($totalesServicios["asistencia"]) ?></div>
            <small class="muted">Adultos: <?= number_format($totalesServicios["adultos"]) ?> | Niños: <?= number_format($totalesServicios["ninos"]) ?></small>
        </div>
        <div class="stat">
            <div class="label">Total ofrendas</div>
            <div class="value">$<?= number_format($totalesServicios["ofrenda"], 2) ?></div>
            <small class="muted">Ofrendas de cultos</small>
        </div>
        <div class="stat">
            <div class="label">Total ingresos</div>
            <div class="value">$<?= number_format($totalesServicios["ingresos"], 2) ?></div>
            <small class="muted">Ofrendas + Diezmos ($<?= number_format($totalesServicios["diezmos"], 2) ?>)</small>
        </div>
    <?php endif; ?>
</div>

<!-- SECCIÓN: CÉLULAS -->
<?php if ($modulo === "todas" || $modulo === "celulas"): ?>
    <?php if ($modulo === "todas"): ?>
        <h3 class="section-title"><i class="ri-community-line"></i> Estadísticas de células</h3>
    <?php endif; ?>

    <div class="grid-2">
        <section class="card card-flush">
            <h3><?= $sectorId > 0 ? "Asistencia por célula - " . e($nombreSector) : "Asistencia por sector" ?></h3>
            <canvas
                data-chart-stats
                data-chart-type="bar"
                data-chart-label="Asistencia"
                data-chart-color="#3061f2"
                data-labels="<?= e(json_encode($labelsCelulas)) ?>"
                data-values="<?= e(json_encode($valuesCelulas)) ?>"
            ></canvas>
        </section>

        <section class="card card-flush">
            <h3><?= $sectorId > 0 ? "Detalle de células (" . e($nombreSector) . ")" : "Indicadores por sector" ?></h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th><?= $sectorId > 0 ? "Célula" : "Sector" ?></th>
                            <?php if ($sectorId > 0): ?><th>Líder</th><?php endif; ?>
                            <th style="text-align: right;">Asistencia</th>
                            <th style="text-align: right;">Ofrenda</th>
                            <th style="text-align: right;">Conversiones</th>
                            <th style="text-align: right;">Bautismos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($estadisticasCelulas as $fila): ?>
                            <tr>
                                <td><strong><?= e($fila["nombre"]) ?></strong></td>
                                <?php if ($sectorId > 0): ?><td><?= e($fila["lider"] ?? "") ?></td><?php endif; ?>
                                <td style="text-align: right;"><?= number_format((int) $fila["asistencia"]) ?></td>
                                <td class="money-cell" style="text-align: right;">$<?= number_format((float) $fila["ofrenda"], 2) ?></td>
                                <td style="text-align: right;"><?= $fila["conversiones"] ?></td>
                                <td style="text-align: right;"><?= $fila["bautismos"] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($estadisticasCelulas)): ?>
                            <tr>
                                <td colspan="<?= $sectorId > 0 ? 6 : 5 ?>" class="empty">No hay registros de células en este periodo.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
<?php endif; ?>

<!-- SECCIÓN: SERVICIOS -->
<?php if ($modulo === "todas" || $modulo === "servicios"): ?>
    <?php if ($modulo === "todas"): ?>
        <h3 class="section-title"><i class="ri-calendar-check-line"></i> Estadísticas de servicios de iglesia</h3>
    <?php endif; ?>

    <div class="grid-2">
        <section class="card card-flush">
            <h3>
                <?= $tipoServicio !== ""
                    ? "Evolución de cultos (" . ucfirst($tipoServicio) . ")"
                    : "Asistencia por tipo de culto" ?>
            </h3>
            <canvas
                data-chart-stats
                data-chart-type="<?= $tipoServicio !== "" ? "line" : "bar" ?>"
                data-chart-label="Asistencia"
                data-chart-color="<?= $tipoServicio !== "" ? "#3061f2" : "#2148bf" ?>"
                data-labels="<?= e(json_encode($labelsServicios)) ?>"
                data-values="<?= e(json_encode($valuesServicios)) ?>"
            ></canvas>
        </section>

        <section class="card card-flush">
            <h3>
                <?= $tipoServicio !== ""
                    ? "Historial de cultos (" . ucfirst($tipoServicio) . ")"
                    : "Resumen de servicios" ?>
            </h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th><?= $tipoServicio !== "" ? "Fecha" : "Servicio" ?></th>
                            <?php if ($tipoServicio === ""): ?><th style="text-align: right;">Cultos</th><?php endif; ?>
                            <th style="text-align: right;">Adultos</th>
                            <th style="text-align: right;">Niños</th>
                            <th style="text-align: right;">Asistencia Total</th>
                            <th style="text-align: right;">Ofrendas</th>
                            <th style="text-align: right;">Total Ingresos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($estadisticasServicios as $fila): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?= $tipoServicio !== ""
                                            ? date("d/m/Y", strtotime($fila["fecha"]))
                                            : ucfirst($fila["tipo_servicio"]) ?>
                                    </strong>
                                </td>
                                <?php if ($tipoServicio === ""): ?>
                                    <td style="text-align: right;"><?= $fila["cantidad"] ?></td>
                                <?php endif; ?>
                                <td style="text-align: right;"><?= number_format((int) $fila["adultos"]) ?></td>
                                <td style="text-align: right;"><?= number_format((int) $fila["ninos"]) ?></td>
                                <td style="text-align: right;"><strong><?= number_format((int) $fila["asistencia"]) ?></strong></td>
                                <td class="money-cell" style="text-align: right;">$<?= number_format((float) $fila["ofrenda"], 2) ?></td>
                                <td class="money-cell" style="text-align: right;"><strong>$<?= number_format((float) $fila["total_ingresos"], 2) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($estadisticasServicios)): ?>
                            <tr>
                                <td colspan="<?= $tipoServicio === "" ? 7 : 6 ?>" class="empty">No hay servicios registrados en este periodo.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
<?php endif; ?>

<?php
cerrarPaginaAdmin([
    "https://cdn.jsdelivr.net/npm/chart.js@4.4.7",
    "js/admin.js",
]);
