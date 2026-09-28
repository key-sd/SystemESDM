<?php

require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';

requiereRol(['supervisor']);

$vista = $_GET['vista'] ?? 'resumen';
$vistasPermitidas = ['resumen', 'celulas', 'reporte', 'historial'];

if (!in_array($vista, $vistasPermitidas, true)) {
    $vista = 'resumen';
}

$actual = $vista;
$usuarioId = (int) $_SESSION['usuario_id'];
$sectorId = (int) ($_SESSION['sector_id'] ?? 0);

$fechaSeleccionada = $_GET['fecha'] ?? semanaActual();
try {
    $fechaSeleccionada = fechaReporteSemanal($fechaSeleccionada);
    $fechaObjeto = new DateTimeImmutable($fechaSeleccionada);
} catch (InvalidArgumentException) {
    $fechaSeleccionada = semanaActual();
    $fechaObjeto = new DateTimeImmutable($fechaSeleccionada);
}

$consolidadoEditado = null;
$detallesConsolidadoEditado = [];

if ($vista === 'reporte' && isset($_GET['editar'])) {
    $consulta = $conexion->prepare(
        'SELECT * FROM reportes_supervisor
         WHERE id = ? AND sector_id = ? AND supervisor_id = ?'
    );
    $consulta->execute([(int) $_GET['editar'], $sectorId, $usuarioId]);
    $consolidadoEditado = $consulta->fetch();

    if (!$consolidadoEditado) {
        flash('error', 'No tienes permiso para editar ese consolidado.');
        header('Location: sup-inicio.php?vista=historial');
        exit;
    }

    $fechaSeleccionada = $consolidadoEditado['fecha_reporte'];
    $fechaObjeto = new DateTimeImmutable($fechaSeleccionada);

    $consulta = $conexion->prepare(
        'SELECT * FROM reportes_sup_detalle WHERE reporte_supervisor_id = ?'
    );
    $consulta->execute([$consolidadoEditado['id']]);

    foreach ($consulta->fetchAll() as $detalle) {
        if ($detalle['celula_id']) {
            $detallesConsolidadoEditado[(int) $detalle['celula_id']] = $detalle;
        }
    }
}

if (isset($_GET['ver'])) {
    $consulta = $conexion->prepare(
        'UPDATE reportes_lider r
         INNER JOIN celulas c ON c.id = r.celula_id
         SET r.visto_supervisor_en = COALESCE(r.visto_supervisor_en, NOW())
         WHERE r.id = ? AND c.sector_id = ?'
    );
    $consulta->execute([(int) $_GET['ver'], $sectorId]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();

    try {
        $conexion->beginTransaction();

        $reporteEditId = (int) ($_POST['reporte_id'] ?? 0);

        if ($reporteEditId > 0) {
            $consulta = $conexion->prepare(
                'UPDATE reportes_supervisor
                 SET observaciones = ?, actualizado_en = NOW()
                 WHERE id = ? AND sector_id = ? AND supervisor_id = ?'
            );
            $consulta->execute([
                trim($_POST['observaciones'] ?? ''),
                $reporteEditId,
                $sectorId,
                $usuarioId,
            ]);

            if ($consulta->rowCount() === 0) {
                throw new RuntimeException('No tienes permiso para editar este consolidado.');
            }

            $consulta = $conexion->prepare(
                'DELETE FROM reportes_sup_detalle WHERE reporte_supervisor_id = ?'
            );
            $consulta->execute([$reporteEditId]);
            $reporteId = $reporteEditId;
        } else {
            $consulta = $conexion->prepare(
                'INSERT INTO reportes_supervisor
                (sector_id, supervisor_id, fecha_reporte, observaciones)
             VALUES (?, ?, ?, ?)'
        );
            $consulta->execute([
                $sectorId,
                $usuarioId,
                fechaReporteSemanal($_POST['fecha_reporte'] ?? null),
                trim($_POST['observaciones'] ?? ''),
            ]);

            $reporteId = (int) $conexion->lastInsertId();
        }
        $consultaDetalle = $conexion->prepare(
            'INSERT INTO reportes_sup_detalle (
                reporte_supervisor_id,
                lider_id,
                celula_id,
                tipo_celula,
                adultos_amigos,
                adultos_hermanos,
                jovenes_amigos,
                jovenes_hermanos,
                ninos,
                conversiones,
                visitas_programadas,
                bautismos_agua,
                ofrenda_iglesia,
                ofrenda_misiones,
                ofrenda_bus
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $consultaCelulaPermitida = $conexion->prepare(
            'SELECT c.id, c.lider_id, c.tipo
             FROM celulas c
             WHERE c.id = ? AND c.sector_id = ? AND c.activo = 1'
        );

        foreach ($_POST['celula_id'] ?? [] as $indice => $celulaId) {
            $celulaId = (int) $celulaId;
            $consultaCelulaPermitida->execute([$celulaId, $sectorId]);
            $celulaPermitida = $consultaCelulaPermitida->fetch();

            if (!$celulaPermitida) {
                throw new RuntimeException('Una de las células no pertenece a tu sector.');
            }

            $consultaDetalle->execute([
                $reporteId,
                $celulaPermitida['lider_id'] ? (int) $celulaPermitida['lider_id'] : null,
                $celulaId,
                $celulaPermitida['tipo'],
                max(0, (int) $_POST['aa'][$indice]),
                max(0, (int) $_POST['ah'][$indice]),
                max(0, (int) $_POST['ja'][$indice]),
                max(0, (int) $_POST['jh'][$indice]),
                max(0, (int) $_POST['ninos'][$indice]),
                max(0, (int) $_POST['conv'][$indice]),
                max(0, (int) $_POST['visitas'][$indice]),
                max(0, (int) $_POST['baut'][$indice]),
                max(0, (float) $_POST['oi'][$indice]),
                max(0, (float) $_POST['om'][$indice]),
                max(0, (float) $_POST['ob'][$indice]),
            ]);
        }

        $conexion->commit();
        flash(
            'success',
            $reporteEditId > 0
                ? 'Consolidado corregido correctamente. Se conservó la fecha original de envío.'
                : 'Reporte enviado al administrador.'
        );
        $vista = 'historial';
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        error_log($error->getMessage());
        flash(
            'error',
            'Es posible que ya hayas enviado un reporte para esta semana.'
        );
    }

    header('Location: sup-inicio.php?vista=' . $vista);
    exit;
}

$consulta = $conexion->prepare(
    'SELECT
        c.*,
        u.nombre_completo AS lider,
        u.usuario AS lider_usuario,
        u.id AS lider_id,
        r.id AS reporte_semana_id,
        r.fecha_reporte AS reporte_semana_fecha,
        r.total_asistencia AS reporte_semana_asistencia,
        r.adultos_amigos AS reporte_semana_aa,
        r.adultos_hermanos AS reporte_semana_ah,
        r.jovenes_amigos AS reporte_semana_ja,
        r.jovenes_hermanos AS reporte_semana_jh,
        r.ninos AS reporte_semana_ninos,
        r.ofrenda_total AS reporte_semana_ofrenda,
        r.ofrenda_iglesia AS reporte_semana_oi,
        r.ofrenda_misiones AS reporte_semana_om,
        r.ofrenda_bus AS reporte_semana_ob,
        r.conversion AS reporte_semana_conversion,
        r.visita_programada AS reporte_semana_visita,
        r.bautismo_agua AS reporte_semana_bautismo,
        r.enviado_en AS reporte_semana_enviado,
        (SELECT COUNT(*) FROM miembros_celula m WHERE m.celula_id = c.id AND m.activo = 1) AS total_miembros
     FROM celulas c
     LEFT JOIN usuarios u ON u.id = c.lider_id
     LEFT JOIN reportes_lider r ON r.celula_id = c.id AND r.anio_semana = YEARWEEK(CURDATE(), 1)
     WHERE c.sector_id = ? AND c.activo = 1
     ORDER BY c.nombre'
);
$consulta->execute([$sectorId]);
$celulas = $consulta->fetchAll();

$miembrosPorCelula = [];
$historialPorCelula = [];

if ($vista === 'celulas') {
    $stmtM = $conexion->prepare(
        'SELECT m.* 
         FROM miembros_celula m
         INNER JOIN celulas c ON c.id = m.celula_id
         WHERE c.sector_id = ? AND m.activo = 1
         ORDER BY m.nombre_completo ASC'
    );
    $stmtM->execute([$sectorId]);
    foreach ($stmtM->fetchAll() as $m) {
        $miembrosPorCelula[(int) $m['celula_id']][] = $m;
    }

    $stmtH = $conexion->prepare(
        'SELECT r.id, r.celula_id, r.fecha_reporte, r.total_asistencia, r.ofrenda_total, r.conversion, r.enviado_en
         FROM reportes_lider r
         INNER JOIN celulas c ON c.id = r.celula_id
         WHERE c.sector_id = ?
         ORDER BY r.fecha_reporte DESC'
    );
    $stmtH->execute([$sectorId]);
    foreach ($stmtH->fetchAll() as $h) {
        $cId = (int) $h['celula_id'];
        if (!isset($historialPorCelula[$cId]) || count($historialPorCelula[$cId]) < 5) {
            $historialPorCelula[$cId][] = $h;
        }
    }
}

$reportesLiderSemana = [];

if ($vista === 'reporte') {
    $consulta = $conexion->prepare(
        'SELECT r.*
         FROM reportes_lider r
         INNER JOIN celulas c ON c.id = r.celula_id
         WHERE c.sector_id = ?
           AND r.anio_semana = YEARWEEK(?, 1)
         ORDER BY r.celula_id, r.fecha_reporte DESC, r.enviado_en DESC'
    );
    $consulta->execute([
        $sectorId,
        $fechaSeleccionada,
    ]);

    foreach ($consulta->fetchAll() as $reporteLider) {
        $celulaId = (int) $reporteLider['celula_id'];

        if (!isset($reportesLiderSemana[$celulaId])) {
            $reportesLiderSemana[$celulaId] = $reporteLider;
        }
    }
}

$consulta = $conexion->prepare(
    'SELECT
        r.*,
        u.nombre_completo,
        c.nombre AS celula
     FROM reportes_lider r
     INNER JOIN celulas c ON c.id = r.celula_id
     INNER JOIN usuarios u ON u.id = r.lider_id
     WHERE c.sector_id = ?
     ORDER BY r.fecha_reporte DESC, r.enviado_en DESC'
);
$consulta->execute([$sectorId]);
$reportes = $consulta->fetchAll();

$consulta = $conexion->prepare(
    'SELECT *
     FROM reportes_supervisor
     WHERE sector_id = ?
     ORDER BY fecha_reporte DESC'
);
$consulta->execute([$sectorId]);
$reportesSupervisor = $consulta->fetchAll();

$titulos = [
    'resumen' => 'Resumen del sector',
    'celulas' => 'Mis células',
    'reporte' => 'Reporte semanal',
    'historial' => 'Reportes enviados',
];

$titulo = $titulos[$vista];

$styles = ['css/sup.css'];

require __DIR__ . '/../components/head.php';
require __DIR__ . '/../components/sidebar.php';
require __DIR__ . '/../components/top.php';
?>

<?php if ($vista === 'resumen'): ?>
    <?php
    $semanaActualIso = (int) date('oW');
    $reportesSemana = array_filter(
        $reportes,
        fn (array $reporte): bool => ((int) ($reporte['anio_semana'] ?? date('oW', strtotime($reporte['fecha_reporte'])))) === $semanaActualIso
    );
    $asistenciaSemana = array_sum(array_map(
        fn (array $reporte): int => (int) $reporte['total_asistencia'],
        $reportesSemana
    ));
    ?>

    <div class="grid-3">
        <div class="stat">
            <div class="label">Células activas</div>
            <div class="value"><?= count($celulas) ?></div>
        </div>
        <div class="stat">
            <div class="label">Reportes esta semana</div>
            <div class="value">
                <?= count($reportesSemana) ?>
                <small class="value-suffix">/ <?= count($celulas) ?></small>
            </div>
        </div>
        <div class="stat">
            <div class="label">Asistencia semanal</div>
            <div class="value"><?= $asistenciaSemana ?></div>
        </div>
    </div>

    <section class="card">
        <div class="card-head">
            <div>
                <h3>Reportes recientes</h3>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Célula</th>
                        <th>Líder</th>
                        <th>Asistencia</th>
                        <th>Enviado</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($reportes, 0, 10) as $reporte): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($reporte['fecha_reporte'])) ?></td>
                            <td><strong><?= e($reporte['celula']) ?></strong></td>
                            <td><?= e($reporte['nombre_completo']) ?></td>
                            <td><?= $reporte['total_asistencia'] ?></td>
                            <td class="receipt"><?= fechaBonita($reporte['enviado_en']) ?></td>
                            <td>
                                <span class="badge <?= $reporte['visto_supervisor_en'] ? 'ok' : 'wait' ?>">
                                    <?= $reporte['visto_supervisor_en'] ? 'Visto' : 'Nuevo' ?>
                                </span>
                            </td>
                            <td>
                                <a
                                    class="btn btn-soft btn-sm"
                                    href="reporte-lider.php?id=<?= $reporte['id'] ?>"
                                >
                                    Ver detalles
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

<?php elseif ($vista === 'celulas'): ?>
    <?php if (!$celulas): ?>
        <div class="empty">No hay células registradas en este sector.</div>
    <?php else: ?>
        <div class="grid-3">
            <?php foreach ($celulas as $celula): ?>
                <?php $tieneReporteSemana = !empty($celula['reporte_semana_id']); ?>
                <article class="card card-flush cell-card" data-open-cell-modal="<?= $celula['id'] ?>">
                    <div class="cell-card-header">
                        <span class="badge-celula badge-celula-<?= e($celula['tipo']) ?>">
                            Célula <?= e(etiquetaTipoCelula($celula['tipo'])) ?>
                        </span>
                        <?php if ($tieneReporteSemana): ?>
                            <span class="badge-status-report ok">
                                <i class="ri-checkbox-circle-fill"></i> Reporte entregado
                            </span>
                        <?php else: ?>
                            <span class="badge-status-report wait">
                                <i class="ri-time-line"></i> Reporte pendiente
                            </span>
                        <?php endif; ?>
                    </div>

                    <h3 class="cell-card-title"><?= e($celula['nombre']) ?></h3>

                    <div class="cell-card-meta">
                        <p class="muted">
                            <i class="ri-user-3-line"></i>
                            <?= e($celula['lider'] ?? 'Sin líder asignado') ?>
                        </p>
                        <p class="muted">
                            <i class="ri-map-pin-line"></i>
                            <?= e($celula['direccion'] ?? 'Sin dirección') ?>
                        </p>
                    </div>

                    <div class="cell-card-footer">
                        <span class="cell-members-count">
                            <i class="ri-team-line"></i> <?= (int) $celula['total_miembros'] ?> miembro<?= (int) $celula['total_miembros'] === 1 ? '' : 's' ?>
                        </span>
                        <button type="button" class="btn btn-soft btn-sm btn-open-cell-modal" data-open-cell-modal="<?= $celula['id'] ?>">
                            <i class="ri-eye-line"></i> Ver detalles
                        </button>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <!-- Modal Backdrop y Contenedor -->
        <div id="modalCelulaDetalle" class="cell-modal-backdrop" style="display: none;" aria-hidden="true" role="dialog" aria-modal="true">
            <div class="cell-modal-container" id="modalCelulaContenido">
                <!-- Se llena dinámicamente desde el template -->
            </div>
        </div>

        <!-- Templates con toda la información detallada de cada célula -->
        <?php foreach ($celulas as $celula): ?>
            <?php
            $tieneReporteSemana = !empty($celula['reporte_semana_id']);
            $miembros = $miembrosPorCelula[(int) $celula['id']] ?? [];
            $historial = $historialPorCelula[(int) $celula['id']] ?? [];
            ?>
            <template id="tmpl-celula-<?= $celula['id'] ?>">
                <div class="cell-modal-header">
                    <div>
                        <div class="cell-modal-tags">
                            <span class="badge-celula badge-celula-<?= e($celula['tipo']) ?>">
                                Célula <?= e(etiquetaTipoCelula($celula['tipo'])) ?>
                            </span>
                            <?php if ($tieneReporteSemana): ?>
                                <span class="badge-status-report ok">
                                    <i class="ri-checkbox-circle-fill"></i> Reporte de la semana: Entregado
                                </span>
                            <?php else: ?>
                                <span class="badge-status-report wait">
                                    <i class="ri-time-line"></i> Reporte de la semana: Pendiente
                                </span>
                            <?php endif; ?>
                        </div>
                        <h2><?= e($celula['nombre']) ?></h2>
                    </div>
                    <button type="button" class="cell-modal-close" aria-label="Cerrar">&times;</button>
                </div>

                <div class="cell-modal-body">
                    <!-- 1. Ficha del líder y ubicación -->
                    <div class="cell-modal-info-grid">
                        <div class="info-block">
                            <span class="info-label"><i class="ri-user-star-line"></i> Líder encargado</span>
                            <span class="info-value"><?= e($celula['lider'] ?? 'Sin líder asignado') ?></span>
                            <?php if (!empty($celula['lider_usuario'])): ?>
                                <span class="info-sub">Usuario: @<?= e($celula['lider_usuario']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="info-block">
                            <span class="info-label"><i class="ri-map-pin-2-line"></i> Ubicación</span>
                            <span class="info-value"><?= e($celula['direccion'] ?? 'Sin dirección registrada') ?></span>
                            <?php if (!empty($celula['lat']) && !empty($celula['lng'])): ?>
                                <a href="https://maps.google.com/?q=<?= $celula['lat'] ?>,<?= $celula['lng'] ?>" target="_blank" rel="noopener" class="map-link">
                                    <i class="ri-external-link-line"></i> Ver en Google Maps
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 2. Estado del reporte de la semana actual -->
                    <div class="cell-modal-report-box <?= $tieneReporteSemana ? 'box-delivered' : 'box-pending' ?>">
                        <?php if ($tieneReporteSemana): ?>
                            <div class="report-box-head">
                                <div>
                                    <h4><i class="ri-checkbox-circle-line"></i> Reporte entregado</h4>
                                    <p class="muted">Reunión realizada el <strong><?= date('d/m/Y', strtotime($celula['reporte_semana_fecha'])) ?></strong></p>
                                </div>
                                <a href="reporte-lider.php?id=<?= $celula['reporte_semana_id'] ?>" class="btn btn-soft btn-sm">
                                    <i class="ri-file-list-3-line"></i> Ver reporte completo
                                </a>
                            </div>
                            <div class="report-box-metrics">
                                <div class="metric-item">
                                    <span class="metric-num"><?= $celula['reporte_semana_asistencia'] ?></span>
                                    <span class="metric-lbl">Asistencia total</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-num">$<?= number_format($celula['reporte_semana_ofrenda'], 2) ?></span>
                                    <span class="metric-lbl">Ofrenda total</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-num"><?= $celula['reporte_semana_conversion'] ?></span>
                                    <span class="metric-lbl">Conversiones</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-num"><?= $celula['reporte_semana_visita'] ?></span>
                                    <span class="metric-lbl">Visitas</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="report-pending-content">
                                <i class="ri-time-line report-pending-icon"></i>
                                <div>
                                    <h4>Reporte de esta semana pendiente</h4>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 3. Miembros registrados -->
                    <div class="cell-modal-section">
                        <div class="section-heading">
                            <h3>Miembros registrados en la célula</h3>
                            <span class="badge-count"><?= count($miembros) ?></span>
                        </div>
                        <?php if (!empty($miembros)): ?>
                            <div class="cell-members-table-wrap">
                                <table class="cell-members-table">
                                    <thead>
                                        <tr>
                                            <th>Nombre completo</th>
                                            <th>Estado civil</th>
                                            <th>Bautismo agua</th>
                                            <th>Bautismo espíritu</th>
                                            <th>Teléfono</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($miembros as $m): ?>
                                            <tr>
                                                <td><strong><?= e($m['nombre_completo']) ?></strong></td>
                                                <td><?= ucfirst(e($m['estado_civil'] ?? 'soltero')) ?></td>
                                                <td>
                                                    <span class="badge-pill <?= $m['bautismo_agua'] ? 'ok' : 'subtle' ?>">
                                                        <?= $m['bautismo_agua'] ? 'Sí' : 'No' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge-pill <?= $m['bautismo_espiritu'] ? 'ok' : 'subtle' ?>">
                                                        <?= $m['bautismo_espiritu'] ? 'Sí' : 'No' ?>
                                                    </span>
                                                </td>
                                                <td><?= e($m['telefono'] ?: '—') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-compact">
                                <i class="ri-team-line"></i> Esta célula aún no tiene miembros registrados por su líder.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 4. Historial reciente -->
                    <?php if (!empty($historial)): ?>
                        <div class="cell-modal-section">
                            <div class="section-heading">
                                <h3>Últimos reportes entregados</h3>
                            </div>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Fecha reunión</th>
                                            <th>Asistencia</th>
                                            <th>Ofrenda</th>
                                            <th>Conversiones</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($historial as $rep): ?>
                                            <tr>
                                                <td><strong><?= date('d/m/Y', strtotime($rep['fecha_reporte'])) ?></strong></td>
                                                <td><?= $rep['total_asistencia'] ?> personas</td>
                                                <td>$<?= number_format($rep['ofrenda_total'], 2) ?></td>
                                                <td><?= $rep['conversion'] ?></td>
                                                <td>
                                                    <a href="reporte-lider.php?id=<?= $rep['id'] ?>" class="btn btn-soft btn-sm">
                                                        <i class="ri-eye-line"></i> Ver
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </template>
        <?php endforeach; ?>
    <?php endif; ?>

<?php elseif ($vista === 'reporte'): ?>
    <section class="card card-flush">
        <div class="card-head">
            <div>
                <h3><?= $consolidadoEditado ? 'Editar reporte del sector' : 'Reporte del sector' ?></h3>
            </div>
        </div>

        <form method="post">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <input type="hidden" name="reporte_id" value="<?= $consolidadoEditado['id'] ?? 0 ?>">

            <label for="fecha_reporte">Semana del reporte</label>
            <input
                id="fecha_reporte"
                class="date-field"
                type="date"
                name="fecha_reporte"
                value="<?= e($fechaSeleccionada) ?>"
                <?= $consolidadoEditado ? 'readonly' : 'data-consolidated-date' ?>
                required
            >

            <div class="table-wrap table-spaced">
                <?php
                $camposReporte = [
                    'aa' => 'adultos_amigos',
                    'ah' => 'adultos_hermanos',
                    'ja' => 'jovenes_amigos',
                    'jh' => 'jovenes_hermanos',
                    'ninos' => 'ninos',
                    'conv' => 'conversion',
                    'visitas' => 'visita_programada',
                    'baut' => 'bautismo_agua',
                    'oi' => 'ofrenda_iglesia',
                    'om' => 'ofrenda_misiones',
                    'ob' => 'ofrenda_bus',
                ];
                $camposAsistencia = array_slice($camposReporte, 0, 5, true);
                $camposResultados = array_slice($camposReporte, 5, null, true);
                $camposDetalle = [
                    'aa' => 'adultos_amigos',
                    'ah' => 'adultos_hermanos',
                    'ja' => 'jovenes_amigos',
                    'jh' => 'jovenes_hermanos',
                    'ninos' => 'ninos',
                    'conv' => 'conversiones',
                    'visitas' => 'visitas_programadas',
                    'baut' => 'bautismos_agua',
                    'oi' => 'ofrenda_iglesia',
                    'om' => 'ofrenda_misiones',
                    'ob' => 'ofrenda_bus',
                ];
                ?>
                <table class="consolidated-table">
                    <thead>
                        <tr>
                            <th>Célula</th>
                            <th>Tipo</th>
                            <th>Adultos amigos</th>
                            <th>Adultos hermanos</th>
                            <th>Jóvenes amigos</th>
                            <th>Jóvenes hermanos</th>
                            <th>Niños</th>
                            <th>Total personas</th>
                            <th>Conversiones</th>
                            <th>Visitas</th>
                            <th title="Bautismo en agua">Cand. Bautismo</th>
                            <th>Ofr. iglesia</th>
                            <th>Misiones</th>
                            <th>Transporte</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($celulas as $celula): ?>
                            <?php
                            $reporteLider = $reportesLiderSemana[(int) $celula['id']] ?? null;
                            $detalleEditado = $detallesConsolidadoEditado[(int) $celula['id']] ?? null;
                            ?>
                            <tr data-consolidated-row>
                                <td>
                                    <strong><?= e($celula['nombre']) ?></strong>
                                    <br>
                                    <span class="badge <?= ($detalleEditado || $reporteLider) ? 'ok' : 'wait' ?>">
                                        <?= $detalleEditado ? 'Editando consolidado' : ($reporteLider ? 'Autorrellenado' : 'Sin reporte') ?>
                                    </span>
                                    <input
                                        type="hidden"
                                        name="celula_id[]"
                                        value="<?= $celula['id'] ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="lider_id[]"
                                        value="<?= $celula['lider_id'] ?>"
                                    >
                                </td>
                                <td>
                                    <input
                                        type="hidden"
                                        name="tipo[]"
                                        value="<?= $celula['tipo'] ?>"
                                    >
                                    <?= e(ucfirst($celula['tipo'])) ?>
                                </td>

                                <?php foreach (array_keys($camposAsistencia) as $campo): ?>
                                    <td>
                                        <input
                                            class="compact-number"
                                            type="number"
                                            min="0"
                                            step="<?= str_starts_with($campo, 'o') ? '0.01' : '1' ?>"
                                            name="<?= $campo ?>[]"
                                            value="<?= e($detalleEditado[$camposDetalle[$campo]] ?? $reporteLider[$camposReporte[$campo]] ?? 0) ?>"
                                            data-consolidated-input="<?= $campo ?>"
                                        >
                                    </td>
                                <?php endforeach; ?>
                                <td>
                                    <strong class="row-total" data-row-total>0</strong>
                                </td>
                                <?php foreach (array_keys($camposResultados) as $campo): ?>
                                    <td>
                                        <input
                                            class="compact-number"
                                            type="number"
                                            min="0"
                                            step="<?= str_starts_with($campo, 'o') ? '0.01' : '1' ?>"
                                            name="<?= $campo ?>[]"
                                            value="<?= e($detalleEditado[$camposDetalle[$campo]] ?? $reporteLider[$camposReporte[$campo]] ?? 0) ?>"
                                            data-consolidated-input="<?= $campo ?>"
                                        >
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="consolidated-totals">
                            <td colspan="2"><strong>TOTALES DEL SECTOR</strong></td>
                            <?php foreach (array_keys($camposAsistencia) as $campo): ?>
                                <td><strong data-column-total="<?= $campo ?>">0</strong></td>
                            <?php endforeach; ?>
                            <td><strong data-column-total="personas">0</strong></td>
                            <?php foreach (array_keys($camposResultados) as $campo): ?>
                                <td><strong data-column-total="<?= $campo ?>">0</strong></td>
                            <?php endforeach; ?>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="sector-summary" aria-live="polite">
                <div><span>Total adultos</span><strong data-sector-total="adultos">0</strong></div>
                <div><span>Total jóvenes</span><strong data-sector-total="jovenes">0</strong></div>
                <div><span>Total niños</span><strong data-sector-total="ninos">0</strong></div>
                <div><span>Total personas</span><strong data-sector-total="personas">0</strong></div>
                <div><span>Ofrenda total</span><strong>$<span data-sector-total="ofrenda">0.00</span></strong></div>
            </div>

            <label class="form-spaced" for="observaciones">Observaciones</label>
            <textarea id="observaciones" name="observaciones" rows="3"><?= e($consolidadoEditado['observaciones'] ?? '') ?></textarea>

            <div class="form-actions">
                <button class="btn btn-gold" type="submit">
                    <?= $consolidadoEditado ? 'Guardar correcciones' : 'Enviar reporte al administrador' ?>
                </button>
            </div>
        </form>
    </section>

<?php else: ?>
    <section class="card card-flush">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Semana</th>
                        <th>Enviado</th>
                        <th>Actualizado</th>
                        <th>Visto por admin</th>
                        <th>Observaciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportesSupervisor as $reporte): ?>
                        <tr>
                            <td>
                                <strong><?= date('d/m/Y', strtotime($reporte['fecha_reporte'])) ?></strong><br>
                                <a class="btn btn-soft btn-sm" href="sup-inicio.php?vista=reporte&amp;editar=<?= $reporte['id'] ?>">Editar</a>
                            </td>
                            <td class="receipt"><?= fechaBonita($reporte['enviado_en']) ?></td>
                            <td class="receipt"><?= fechaBonita($reporte['actualizado_en']) ?></td>
                            <td>
                                <span class="badge <?= $reporte['visto_admin_en'] ? 'ok' : 'wait' ?>">
                                    <?= e(fechaBonita($reporte['visto_admin_en'])) ?>
                                </span>
                            </td>
                            <td><?= e($reporte['observaciones'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (!$reportesSupervisor): ?>
            <div class="empty">Aún no has enviado reportes.</div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php
$scripts = ['js/supervisor.js'];
require __DIR__ . '/../components/foot.php';
?>
