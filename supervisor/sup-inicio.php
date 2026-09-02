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
        u.id AS lider_id
     FROM celulas c
     LEFT JOIN usuarios u ON u.id = c.lider_id
     WHERE c.sector_id = ? AND c.activo = 1
     ORDER BY c.nombre'
);
$consulta->execute([$sectorId]);
$celulas = $consulta->fetchAll();

$reportesLiderSemana = [];

if ($vista === 'reporte') {
    $consulta = $conexion->prepare(
        'SELECT r.*
         FROM reportes_lider r
         INNER JOIN celulas c ON c.id = r.celula_id
         WHERE c.sector_id = ?
           AND r.fecha_reporte = ?
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
$subtitulo = 'Supervisión y seguimiento semanal';

require __DIR__ . '/../components/head.php';
require __DIR__ . '/../components/sidebar.php';
require __DIR__ . '/../components/top.php';
?>

<?php if ($vista === 'resumen'): ?>
    <?php
    $inicioSemana = semanaActual();
    $reportesSemana = array_filter(
        $reportes,
        fn (array $reporte): bool => $reporte['fecha_reporte'] >= $inicioSemana
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
                <p class="muted">
                    Abrir un reporte registra automáticamente la hora de lectura.
                </p>
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
    <div class="grid-3">
        <?php foreach ($celulas as $celula): ?>
            <article class="card card-flush">
                <span class="badge ok"><?= e(ucfirst($celula['tipo'])) ?></span>
                <h3 class="card-title-spaced"><?= e($celula['nombre']) ?></h3>
                <p class="muted">
                    <i class="ri-user-line"></i>
                    <?= e($celula['lider'] ?? 'Sin líder') ?>
                </p>
                <p class="muted">
                    <i class="ri-map-pin-line"></i>
                    <?= e($celula['direccion'] ?? 'Sin ubicación') ?>
                </p>
            </article>
        <?php endforeach; ?>
    </div>

<?php elseif ($vista === 'reporte'): ?>
    <section class="card card-flush">
        <div class="card-head">
            <div>
                <h3><?= $consolidadoEditado ? 'Editar reporte del sector' : 'Reporte del sector' ?></h3>
                <p class="muted">
                    Los reportes de líderes de esta semana se cargan automáticamente. Puedes completar o ajustar los datos antes de enviar.
                </p>
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
            <p class="muted form-help">
                Se incluyen los reportes cuya semana termina el sábado <?= date('d/m/Y', strtotime($fechaSeleccionada)) ?>.
            </p>

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
                            <th>Bautismos</th>
                            <th>Ofr. iglesia</th>
                            <th>Misiones</th>
                            <th>Bus</th>
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
