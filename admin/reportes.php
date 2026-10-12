<?php

require_once __DIR__ . "/_bootstrap.php";

if (isset($_GET["ver_lider"])) {
    $consulta = $conexion->prepare(
        'UPDATE reportes_lider
         SET visto_admin_en = COALESCE(visto_admin_en, NOW())
         WHERE id = ?',
    );
    $consulta->execute([(int) $_GET["ver_lider"]]);
}

if (isset($_GET["ver_supervisor"])) {
    $consulta = $conexion->prepare(
        'UPDATE reportes_supervisor
         SET visto_admin_en = COALESCE(visto_admin_en, NOW())
         WHERE id = ?',
    );
    $consulta->execute([(int) $_GET["ver_supervisor"]]);
}

$sectorId = (int) ($_GET["sector"] ?? 0);
$periodos = [
    "todos" => "Todos los tiempos",
    "4-semanas" => "Últimas 4 semanas",
    "6-meses" => "Últimos 6 meses",
    "1-anio" => "Último año",
    "personalizado" => "Periodo personalizado",
];
$periodo = $_GET["periodo"] ?? "todos";

if (!array_key_exists($periodo, $periodos)) {
    $periodo = "todos";
}

$inicio = null;
$fin = null;
$hoy = new DateTimeImmutable("today");

if ($periodo === "4-semanas") {
    $inicio = $hoy->modify("-27 days");
    $fin = $hoy;
} elseif ($periodo === "6-meses") {
    $inicio = $hoy->modify("first day of this month")->modify("-5 months");
    $fin = $hoy;
} elseif ($periodo === "1-anio") {
    $inicio = $hoy->modify("-1 year +1 day");
    $fin = $hoy;
} elseif ($periodo === "personalizado") {
    $inicioPersonalizado = DateTimeImmutable::createFromFormat("!Y-m-d", $_GET["inicio"] ?? "");
    $finPersonalizado = DateTimeImmutable::createFromFormat("!Y-m-d", $_GET["fin"] ?? "");

    if ($inicioPersonalizado && $finPersonalizado && $inicioPersonalizado <= $finPersonalizado) {
        $inicio = $inicioPersonalizado;
        $fin = $finPersonalizado;
    } else {
        $periodo = "todos";
    }
}

$fechaInicio = $inicio?->format("Y-m-d") ?? "";
$fechaFin = $fin?->format("Y-m-d") ?? "";
$condiciones = [];
$parametros = [];

if ($sectorId > 0) {
    $condiciones[] = "s.id = ?";
    $parametros[] = $sectorId;
}

if ($inicio && $fin) {
    $condiciones[] = "r.fecha_reporte BETWEEN ? AND ?";
    $parametros[] = $fechaInicio;
    $parametros[] = $fechaFin;
}

$condicionFiltros = $condiciones ? " WHERE " . implode(" AND ", $condiciones) : "";

$consulta = $conexion->prepare(
    'SELECT
        r.*,
        u.nombre_completo,
        c.nombre AS celula,
        c.tipo AS tipo_celula,
        s.nombre AS sector
     FROM reportes_lider r
     INNER JOIN usuarios u ON u.id = r.lider_id
     INNER JOIN celulas c ON c.id = r.celula_id
     INNER JOIN sectores s ON s.id = c.sector_id' .
        $condicionFiltros .
        " ORDER BY r.fecha_reporte DESC, r.enviado_en DESC",
);
$consulta->execute($parametros);
$reportesLider = $consulta->fetchAll();

$consulta = $conexion->prepare(
    'SELECT
        r.*,
        u.nombre_completo,
        s.nombre AS sector,
        COALESCE(SUM(
            d.adultos_amigos + d.adultos_hermanos +
            d.jovenes_amigos + d.jovenes_hermanos + d.ninos
        ), 0) AS asistencia,
        COALESCE(SUM(
            d.ofrenda_iglesia + d.ofrenda_misiones + d.ofrenda_bus
        ), 0) AS ofrenda
     FROM reportes_supervisor r
     INNER JOIN usuarios u ON u.id = r.supervisor_id
     INNER JOIN sectores s ON s.id = r.sector_id
     LEFT JOIN reportes_sup_detalle d ON d.reporte_supervisor_id = r.id' .
        $condicionFiltros .
        ' GROUP BY r.id, u.nombre_completo, s.nombre
      ORDER BY r.fecha_reporte DESC, r.enviado_en DESC',
);
$consulta->execute($parametros);
$reportesSupervisor = $consulta->fetchAll();

$sectores = $conexion
    ->query("SELECT id, nombre FROM sectores ORDER BY nombre")
    ->fetchAll();

abrirPaginaAdmin(
    "reportes",
    "Reportes recibidos"
);
?>

<section class="card card-flush">
    <div class="card-head">
        <div>
            <h3>Filtrar reportes</h3>
        </div>
    </div>

    <form method="get" class="stats-filters" data-period-filter-form>
        <div>
            <label for="sector">Sector</label>
            <select id="sector" name="sector">
                <option value="0">Todos los sectores</option>
                <?php foreach ($sectores as $sector): ?>
                    <option value="<?= $sector["id"] ?>" <?= $sectorId === (int) $sector["id"] ? "selected" : "" ?>>
                        <?= e($sector["nombre"]) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="periodo">Periodo</label>
            <select id="periodo" name="periodo" data-period-select>
                <?php foreach ($periodos as $valor => $etiqueta): ?>
                    <option value="<?= $valor ?>" <?= $periodo === $valor ? "selected" : "" ?>><?= e($etiqueta) ?></option>
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

        <button class="btn btn-gold" type="submit"><i class="ri-filter-3-line"></i> Aplicar</button>
    </form>
</section>

<section class="card card-flush">
    <div class="card-head">
        <div>
            <h3>Reportes de líderes</h3>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Líder / célula</th>
                    <th>Sector</th>
                    <th>Total Célula</th>
                    <th>Total Global</th>
                    <th>Ofrenda</th>
                    <th>Enviado</th>
                    <th>Actualizado</th>
                    <th>Lecturas</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportesLider as $reporte): ?>
                    <?php
                    $tipoCel = $reporte["tipo_celula"] ?? "adultos";
                    $totAd = (int) $reporte["adultos_amigos"] + (int) $reporte["adultos_hermanos"];
                    $totJov = (int) $reporte["jovenes_amigos"] + (int) $reporte["jovenes_hermanos"];
                    $totNin = (int) $reporte["ninos"];
                    $totGrupo = ($tipoCel === "ninos") ? $totNin : (($tipoCel === "jovenes") ? $totJov : $totAd);
                    $lblGrupo = ($tipoCel === "ninos") ? "niños" : (($tipoCel === "jovenes") ? "jóvenes" : "adultos");
                    ?>
                    <tr>
                        <td><?= date(
                            "d/m/Y",
                            strtotime($reporte["fecha_reporte"]),
                        ) ?></td>
                        <td>
                            <strong><?= e(
                                $reporte["nombre_completo"],
                            ) ?></strong><br>
                            <?= e($reporte["celula"]) ?>
                        </td>
                        <td><?= e($reporte["sector"]) ?></td>
                        <td><strong><?= $totGrupo ?></strong> <small class="muted">(<?= $lblGrupo ?>)</small></td>
                        <td><strong style="color: #2563eb;"><?= $reporte["total_asistencia"] ?></strong> <small class="muted">(global)</small></td>
                        <td>$<?= number_format(
                            $reporte["ofrenda_total"],
                            2,
                        ) ?></td>
                        <td class="receipt"><?= fechaBonita(
                            $reporte["enviado_en"],
                        ) ?></td>
                        <td class="receipt"><?= $reporte["actualizado_en"]
                            ? fechaBonita($reporte["actualizado_en"])
                            : "—" ?></td>
                        <td class="receipt">
                            Supervisor: <?= fechaBonita(
                                $reporte["visto_supervisor_en"],
                            ) ?><br>
                            Admin: <?= fechaBonita(
                                $reporte["visto_admin_en"],
                            ) ?>
                        </td>
                        <td>
                            <a
                                class="btn btn-soft btn-sm"
                                href="reporte-lider.php?id=<?= $reporte["id"] ?><?= $sectorId > 0 ? "&amp;sector=" . $sectorId : "" ?>"
                            >
                                Ver detalles
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (!$reportesLider): ?>
        <div class="empty">No hay reportes de líderes con este filtro.</div>
    <?php endif; ?>
</section>

<section class="card">
    <div class="card-head">
        <div>
            <h3>Reportes de supervisores</h3>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Supervisor</th>
                    <th>Sector</th>
                    <th>Asistencia</th>
                    <th>Ofrenda</th>
                    <th>Enviado</th>
                    <th>Actualizado</th>
                    <th>Visto</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportesSupervisor as $reporte): ?>
                    <tr>
                        <td><?= date(
                            "d/m/Y",
                            strtotime($reporte["fecha_reporte"]),
                        ) ?></td>
                        <td><strong><?= e(
                            $reporte["nombre_completo"],
                        ) ?></strong></td>
                        <td><?= e($reporte["sector"]) ?></td>
                        <td><?= $reporte["asistencia"] ?></td>
                        <td>$<?= number_format($reporte["ofrenda"], 2) ?></td>
                        <td class="receipt"><?= fechaBonita(
                            $reporte["enviado_en"],
                        ) ?></td>
                        <td class="receipt"><?= $reporte["actualizado_en"]
                            ? fechaBonita($reporte["actualizado_en"])
                            : "—" ?></td>
                        <td class="receipt"><?= fechaBonita(
                            $reporte["visto_admin_en"],
                        ) ?></td>
                        <td>
                            <a
                                class="btn btn-soft btn-sm"
                                href="reporte-supervisor.php?id=<?= $reporte["id"] ?><?= $sectorId > 0 ? "&amp;sector=" . $sectorId : "" ?>"
                            >
                                Ver detalles
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (!$reportesSupervisor): ?>
        <div class="empty">No hay reportes de supervisores con este filtro.</div>
    <?php endif; ?>
</section>

<?php cerrarPaginaAdmin(['js/admin.js']); ?>
