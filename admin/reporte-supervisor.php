<?php

require_once __DIR__ . "/_bootstrap.php";

$reporteId = (int) ($_GET["id"] ?? 0);
$sectorId = (int) ($_GET["sector"] ?? 0);

$consulta = $conexion->prepare(
    'SELECT r.*, u.nombre_completo, s.nombre AS sector
     FROM reportes_supervisor r
     INNER JOIN usuarios u ON u.id = r.supervisor_id
     INNER JOIN sectores s ON s.id = r.sector_id
     WHERE r.id = ?',
);
$consulta->execute([$reporteId]);
$reporte = $consulta->fetch();

if (!$reporte) {
    flash("error", "El reporte solicitado no existe.");
    redirigirAdmin("reportes.php");
}

$consulta = $conexion->prepare(
    "UPDATE reportes_supervisor SET visto_admin_en = COALESCE(visto_admin_en, NOW()) WHERE id = ?",
);
$consulta->execute([$reporteId]);

$consulta = $conexion->prepare(
    "SELECT visto_admin_en FROM reportes_supervisor WHERE id = ?",
);
$consulta->execute([$reporteId]);
$reporte["visto_admin_en"] = $consulta->fetchColumn();

$consulta = $conexion->prepare(
    'SELECT d.*, c.nombre AS celula, u.nombre_completo AS lider
     FROM reportes_sup_detalle d
     LEFT JOIN celulas c ON c.id = d.celula_id
     LEFT JOIN usuarios u ON u.id = d.lider_id
     WHERE d.reporte_supervisor_id = ?
     ORDER BY c.nombre',
);
$consulta->execute([$reporteId]);
$detalles = $consulta->fetchAll();

$campos = [
    "adultos_amigos", "adultos_hermanos", "jovenes_amigos", "jovenes_hermanos",
    "ninos", "conversiones", "visitas_programadas", "bautismos_agua",
    "ofrenda_iglesia", "ofrenda_misiones", "ofrenda_bus",
];
$totales = array_fill_keys($campos, 0);

foreach ($detalles as $detalle) {
    foreach ($campos as $campo) {
        $totales[$campo] += (float) $detalle[$campo];
    }
}

$totalPersonas = $totales["adultos_amigos"] + $totales["adultos_hermanos"] +
    $totales["jovenes_amigos"] + $totales["jovenes_hermanos"] + $totales["ninos"];
$totalOfrenda = $totales["ofrenda_iglesia"] + $totales["ofrenda_misiones"] + $totales["ofrenda_bus"];
$urlVolver = "reportes.php" . ($sectorId > 0 ? "?sector=" . $sectorId : "");

abrirPaginaAdmin("reportes", "Detalle del reporte del supervisor");
?>

<div class="toolbar">
    <a class="btn btn-soft" href="<?= e($urlVolver) ?>">
        <i class="ri-arrow-left-line"></i> Volver a reportes
    </a>
</div>

<section class="card">
    <div class="card-head">
        <div>
            <p class="eyebrow">Reporte del sector</p>
            <h3><?= e($reporte["sector"]) ?></h3>
            <p class="muted">
                <?= e($reporte["nombre_completo"]) ?> · Semana del <?= date("d/m/Y", strtotime($reporte["fecha_reporte"])) ?>
            </p>
        </div>
        <span class="badge ok"><?= count($detalles) ?> célula<?= count($detalles) === 1 ? "" : "s" ?></span>
    </div>

    <div class="grid-3 report-detail-stats">
        <div class="stat"><div class="label">Total personas</div><div class="value"><?= $totalPersonas ?></div></div>
        <div class="stat"><div class="label">Ofrenda total</div><div class="value">$<?= number_format($totalOfrenda, 2) ?></div></div>
        <div class="stat"><div class="label">Enviado</div><div class="hint receipt"><?= fechaBonita($reporte["enviado_en"]) ?></div></div>
    </div>
</section>

<section class="card">
    <div class="card-head">
        <div>
            <h3>Detalle por célula</h3>
            <p class="muted">Datos de reportes enviados por el supervisor.</p>
        </div>
    </div>

    <div class="table-wrap">
        <table class="consolidated-table">
            <thead>
                <tr>
                    <th>Célula / líder</th>
                    <th>Tipo</th>
                    <th>Adultos amigos</th><th>Adultos hermanos</th>
                    <th>Jóvenes amigos</th><th>Jóvenes hermanos</th><th>Niños</th>
                    <th>Total Célula</th>
                    <th>Total Personas</th><th>Conversiones</th><th>Visitas</th><th title="Bautismos en agua">Bautismos</th>
                    <th>Iglesia</th><th>Misiones</th><th>Transporte</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $detalle): ?>
                    <?php
                    $tipo = $detalle["tipo_celula"] ?? "adultos";
                    $totAd = (int)$detalle["adultos_amigos"] + (int)$detalle["adultos_hermanos"];
                    $totJov = (int)$detalle["jovenes_amigos"] + (int)$detalle["jovenes_hermanos"];
                    $totNin = (int)$detalle["ninos"];
                    $totCelula = ($tipo === "ninos") ? $totNin : (($tipo === "jovenes") ? $totJov : $totAd);
                    $personas = $totAd + $totJov + $totNin;
                    $totales["celulas_total"] = ($totales["celulas_total"] ?? 0) + $totCelula;
                    ?>
                    <tr>
                        <td><strong><?= e($detalle["celula"] ?? "Célula no disponible") ?></strong><br><?= e($detalle["lider"] ?? "Sin líder") ?></td>
                        <td><span class="badge-celula badge-celula-<?= e($tipo) ?>"><?= ucfirst(e($tipo)) ?></span></td>
                        <td><?= $detalle["adultos_amigos"] ?></td><td><?= $detalle["adultos_hermanos"] ?></td>
                        <td><?= $detalle["jovenes_amigos"] ?></td><td><?= $detalle["jovenes_hermanos"] ?></td><td><?= $detalle["ninos"] ?></td>
                        <td style="background: #f8fafc; text-align: center;"><strong><?= $totCelula ?></strong> <small class="muted" style="display: block; font-size: 9.5px;"><?= ucfirst($tipo) ?></small></td>
                        <td style="background: #eff6ff; text-align: center;"><strong style="color: #1d4ed8;"><?= $personas ?></strong> <small class="muted" style="display: block; font-size: 9.5px;">Global</small></td>
                        <td><?= $detalle["conversiones"] ?></td><td><?= $detalle["visitas_programadas"] ?></td><td><?= $detalle["bautismos_agua"] ?></td>
                        <td>$<?= number_format($detalle["ofrenda_iglesia"], 2) ?></td><td>$<?= number_format($detalle["ofrenda_misiones"], 2) ?></td><td>$<?= number_format($detalle["ofrenda_bus"], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="consolidated-totals">
                    <td colspan="2"><strong>TOTALES</strong></td>
                    <td><?= $totales["adultos_amigos"] ?></td><td><?= $totales["adultos_hermanos"] ?></td>
                    <td><?= $totales["jovenes_amigos"] ?></td><td><?= $totales["jovenes_hermanos"] ?></td><td><?= $totales["ninos"] ?></td>
                    <td style="text-align: center;"><strong><?= $totales["celulas_total"] ?? 0 ?></strong></td>
                    <td style="text-align: center;"><strong style="color: #1d4ed8;"><?= $totalPersonas ?></strong></td>
                    <td><?= $totales["conversiones"] ?></td><td><?= $totales["visitas_programadas"] ?></td><td><?= $totales["bautismos_agua"] ?></td>
                    <td>$<?= number_format($totales["ofrenda_iglesia"], 2) ?></td><td>$<?= number_format($totales["ofrenda_misiones"], 2) ?></td><td>$<?= number_format($totales["ofrenda_bus"], 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <?php if (!$detalles): ?>
        <div class="empty">Este reporte no contiene filas de células.</div>
    <?php endif; ?>
</section>

<div class="grid-2">
    <section class="card">
        <h3>Resultados globales</h3>
        <div class="detail-list">
            <div><span>Conversiones</span><strong><?= $totales["conversiones"] ?></strong></div>
            <div><span>Visitas programadas</span><strong><?= $totales["visitas_programadas"] ?></strong></div>
            <div><span>Bautismos en agua</span><strong><?= $totales["bautismos_agua"] ?></strong></div>
        </div>
    </section>
    <section class="card">
        <h3>Observaciones del supervisor</h3>
        <p class="detail-notes"><?= nl2br(e($reporte["observaciones"] ?: "No se registraron observaciones.")) ?></p>
    </section>
</div>

<section class="card">
    <h3>Comprobante de seguimiento</h3>
    <div class="detail-list">
        <div><span>Enviado por supervisor</span><strong class="receipt"><?= fechaBonita($reporte["enviado_en"]) ?></strong></div>
        <div><span>Última corrección del supervisor</span><strong class="receipt"><?= $reporte["actualizado_en"] ? fechaBonita($reporte["actualizado_en"]) : "Sin correcciones" ?></strong></div>
        <div><span>Visto por administrador</span><strong class="receipt"><?= fechaBonita($reporte["visto_admin_en"]) ?></strong></div>
    </div>
</section>

<?php cerrarPaginaAdmin(); ?>
