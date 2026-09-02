<?php

require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';

requiereRol(['lider']);

$vista = $_GET['vista'] ?? 'resumen';
$vistasPermitidas = ['resumen', 'reporte', 'historial'];

if (!in_array($vista, $vistasPermitidas, true)) {
    $vista = 'resumen';
}

$actual = $vista;
$usuarioId = (int) $_SESSION['usuario_id'];

$consulta = $conexion->prepare(
    'SELECT
        u.*,
        c.nombre AS celula,
        c.tipo,
        c.direccion,
        c.lat,
        c.lng,
        s.nombre AS sector
     FROM usuarios u
     LEFT JOIN celulas c ON c.id = u.celula_id
     LEFT JOIN sectores s ON s.id = u.sector_id
     WHERE u.id = ?'
);
$consulta->execute([$usuarioId]);
$perfil = $consulta->fetch();

$reporteEditado = null;

if ($vista === 'reporte' && isset($_GET['editar'])) {
    $consulta = $conexion->prepare(
        'SELECT * FROM reportes_lider WHERE id = ? AND lider_id = ?'
    );
    $consulta->execute([(int) $_GET['editar'], $usuarioId]);
    $reporteEditado = $consulta->fetch();

    if (!$reporteEditado) {
        flash('error', 'No tienes permiso para editar ese reporte.');
        header('Location: lider-inicio.php?vista=historial');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();

    try {
        if (!$perfil['celula_id']) {
            throw new RuntimeException('El líder no tiene una célula asignada.');
        }

        $reporteId = (int) ($_POST['reporte_id'] ?? 0);

        if ($reporteId > 0) {
            $consulta = $conexion->prepare(
                'UPDATE reportes_lider SET
                    adultos_amigos = ?, adultos_hermanos = ?, jovenes_amigos = ?, jovenes_hermanos = ?, ninos = ?,
                    ofrenda_iglesia = ?, ofrenda_misiones = ?, ofrenda_bus = ?, conversion = ?,
                    visita_programada = ?, bautismo_agua = ?, observaciones = ?, actualizado_en = NOW()
                 WHERE id = ? AND lider_id = ?'
            );
            $consulta->execute([
                entero('adultos_amigos'), entero('adultos_hermanos'), entero('jovenes_amigos'),
                entero('jovenes_hermanos'), entero('ninos'), dinero('ofrenda_iglesia'),
                dinero('ofrenda_misiones'), dinero('ofrenda_bus'), entero('conversion'),
                isset($_POST['visita_programada']), isset($_POST['bautismo_agua']),
                trim($_POST['observaciones'] ?? ''), $reporteId, $usuarioId,
            ]);
            flash('success', 'Reporte corregido correctamente. Se conservó la fecha original de envío.');
            header('Location: lider-inicio.php?vista=historial');
            exit;
        }

        $consulta = $conexion->prepare(
            'INSERT INTO reportes_lider (
                celula_id,
                lider_id,
                fecha_reporte,
                adultos_amigos,
                adultos_hermanos,
                jovenes_amigos,
                jovenes_hermanos,
                ninos,
                ofrenda_iglesia,
                ofrenda_misiones,
                ofrenda_bus,
                conversion,
                visita_programada,
                bautismo_agua,
                observaciones
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $consulta->execute([
            $perfil['celula_id'],
            $usuarioId,
            fechaReporteSemanal($_POST['fecha_reporte'] ?? null),
            entero('adultos_amigos'),
            entero('adultos_hermanos'),
            entero('jovenes_amigos'),
            entero('jovenes_hermanos'),
            entero('ninos'),
            dinero('ofrenda_iglesia'),
            dinero('ofrenda_misiones'),
            dinero('ofrenda_bus'),
            entero('conversion'),
            isset($_POST['visita_programada']),
            isset($_POST['bautismo_agua']),
            trim($_POST['observaciones'] ?? ''),
        ]);

        flash(
            'success',
            'Reporte enviado al supervisor y al administrador. Tu comprobante ya está disponible.'
        );
        $vista = 'historial';
    } catch (Throwable $error) {
        $mensaje = str_contains($error->getMessage(), 'Duplicate')
            ? 'Ya existe un reporte para esta célula en esa semana. Puedes abrirlo desde el historial y corregirlo.'
            : 'No fue posible enviar el reporte. Verifica tu asignación y los datos.';

        flash('error', $mensaje);
    }

    header('Location: lider-inicio.php?vista=' . $vista);
    exit;
}

$consulta = $conexion->prepare(
    'SELECT *
     FROM reportes_lider
     WHERE lider_id = ?
     ORDER BY fecha_reporte DESC'
);
$consulta->execute([$usuarioId]);
$reportes = $consulta->fetchAll();
$ultimoReporte = $reportes[0] ?? null;

$titulos = [
    'resumen' => 'Mi resumen',
    'reporte' => 'Enviar reporte semanal',
    'historial' => 'Mis reportes',
];

$titulo = $titulos[$vista];
$subtitulo = ($perfil['celula'] ?? 'Sin célula asignada') .
    ' · ' .
    ($perfil['sector'] ?? 'Sin sector');

$tieneUbicacion = $perfil['lat'] !== null && $perfil['lng'] !== null;
$styles = $vista === 'resumen' && $tieneUbicacion
    ? ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.css']
    : [];

require __DIR__ . '/../components/head.php';
require __DIR__ . '/../components/sidebar.php';
require __DIR__ . '/../components/top.php';
?>

<?php if ($vista === 'resumen'): ?>
    <?php
    $consulta = $conexion->prepare(
        'SELECT COALESCE(SUM(conversion), 0)
         FROM reportes_lider
         WHERE lider_id = ? AND fecha_reporte >= ?'
    );
    $consulta->execute([$usuarioId, date('Y-m-01')]);
    $conversionesMes = (int) $consulta->fetchColumn();

    $reportesGrafica = array_slice($reportes, 0, 8);
    $etiquetasGrafica = array_reverse(array_map(
        fn (array $reporte): string => date('d/m', strtotime($reporte['fecha_reporte'])),
        $reportesGrafica
    ));
    $datosGrafica = array_reverse(array_map(
        fn (array $reporte): int => (int) $reporte['total_asistencia'],
        $reportesGrafica
    ));
    ?>

    <div class="grid-3">
        <div class="stat">
            <div class="label">Última asistencia</div>
            <div class="value"><?= $ultimoReporte['total_asistencia'] ?? 0 ?></div>
            <div class="hint">
                <?= $ultimoReporte
                    ? 'Reporte del sábado ' . date('d/m', strtotime($ultimoReporte['fecha_reporte']))
                    : 'Aún sin reportes' ?>
            </div>
        </div>
        <div class="stat">
            <div class="label">Conversiones este mes</div>
            <div class="value"><?= $conversionesMes ?></div>
        </div>
        <div class="stat">
            <div class="label">Reportes enviados</div>
            <div class="value"><?= count($reportes) ?></div>
        </div>
    </div>

    <div class="grid-2">
        <section class="card">
            <div class="card-head">
                <div>
                    <h3>Ubicación de mi célula</h3>
                    <p class="muted">
                        <i class="ri-map-pin-line"></i>
                        <?= e($perfil['direccion'] ?: 'Dirección pendiente de registrar') ?>
                    </p>
                </div>
                <?php if ($tieneUbicacion): ?>
                    <a class="btn btn-soft btn-sm" href="https://www.google.com/maps?q=<?= e($perfil['lat']) ?>,<?= e($perfil['lng']) ?>" target="_blank" rel="noopener">
                        Abrir mapa <i class="ri-external-link-line"></i>
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($tieneUbicacion): ?>
                <div id="mapaLider" class="map map-leader" data-lat="<?= e($perfil['lat']) ?>" data-lng="<?= e($perfil['lng']) ?>" data-name="<?= e($perfil['celula']) ?>"></div>
            <?php else: ?>
                <div class="empty map-empty">El administrador aún no ha seleccionado el punto de ubicación de esta célula.</div>
            <?php endif; ?>
        </section>

        <section class="card">
            <h3>Asistencia reciente</h3>
            <canvas
                id="chartLider"
                data-labels="<?= e(json_encode($etiquetasGrafica)) ?>"
                data-values="<?= e(json_encode($datosGrafica)) ?>"
            ></canvas>
        </section>

        <section class="card">
            <h3>Últimos comprobantes</h3>

            <?php foreach (array_slice($reportes, 0, 5) as $reporte): ?>
                <div class="activity-row">
                    <strong>
                        <?= date('d/m/Y', strtotime($reporte['fecha_reporte'])) ?>
                        · <?= $reporte['total_asistencia'] ?> personas
                    </strong>
                    <span class="badge <?= $reporte['visto_admin_en'] && $reporte['visto_supervisor_en'] ? 'ok' : 'wait' ?>">
                        <?= $reporte['visto_admin_en'] && $reporte['visto_supervisor_en']
                            ? 'Visto por ambos'
                            : 'En seguimiento' ?>
                    </span>
                </div>
            <?php endforeach; ?>

            <?php if (!$reportes): ?>
                <div class="empty">Tu primer reporte aparecerá aquí.</div>
            <?php endif; ?>
        </section>
    </div>

<?php elseif ($vista === 'reporte'): ?>
    <?php if (!$perfil['celula_id']): ?>
        <div class="alert alert-danger">
            El administrador debe asignarte una célula antes de enviar reportes.
        </div>
    <?php else: ?>
        <div class="section-split">
            <section class="card card-flush">
                <h3><?= e($perfil['celula']) ?></h3>

                <form method="post">
                    <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                    <input type="hidden" name="reporte_id" value="<?= $reporteEditado['id'] ?? 0 ?>">

                    <label for="fecha_reporte">Sábado de la semana reportada</label>
                    <input
                        id="fecha_reporte"
                        type="date"
                        name="fecha_reporte"
                        value="<?= e($reporteEditado['fecha_reporte'] ?? semanaActual()) ?>"
                        <?= $reporteEditado ? 'readonly' : '' ?>
                        required
                    >

                    <h3 class="form-section-title">Asistencia</h3>
                    <div class="report-numbers">
                        <?php
                        $camposAsistencia = [
                            'adultos_amigos' => 'Adultos amigos',
                            'adultos_hermanos' => 'Adultos hermanos',
                            'ninos' => 'Niños',
                            'jovenes_amigos' => 'Jóvenes amigos',
                            'jovenes_hermanos' => 'Jóvenes hermanos',
                        ];
                        ?>
                        <?php foreach ($camposAsistencia as $campo => $texto): ?>
                            <div>
                                <small><?= $texto ?></small>
                                <input type="number" min="0" name="<?= $campo ?>" value="<?= $reporteEditado[$campo] ?? 0 ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <h3 class="form-section-title">Ofrenda</h3>
                    <div class="report-numbers">
                        <?php foreach (['ofrenda_iglesia' => 'Iglesia', 'ofrenda_misiones' => 'Misiones', 'ofrenda_bus' => 'Bus'] as $campo => $texto): ?>
                            <div>
                                <small><?= $texto ?></small>
                                <input type="number" min="0" step="0.01" name="<?= $campo ?>" value="<?= $reporteEditado[$campo] ?? 0 ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <h3 class="form-section-title">Resultados</h3>
                    <div class="check-grid">
                        <label class="check-card check-card-number">
                            <span>Conversiones</span>
                            <input type="number" min="0" name="conversion" value="<?= $reporteEditado['conversion'] ?? 0 ?>">
                        </label>
                        <label class="check-card">
                            <input type="checkbox" name="visita_programada" <?= !empty($reporteEditado['visita_programada']) ? 'checked' : '' ?>> Visita programada
                        </label>
                        <label class="check-card">
                            <input type="checkbox" name="bautismo_agua" <?= !empty($reporteEditado['bautismo_agua']) ? 'checked' : '' ?>> Bautismo en agua
                        </label>
                    </div>

                    <label class="form-spaced" for="observaciones">Observaciones</label>
                    <textarea
                        id="observaciones"
                        name="observaciones"
                        rows="3"
                        placeholder="Notas relevantes de la reunión"
                    ><?= e($reporteEditado['observaciones'] ?? '') ?></textarea>

                    <div class="form-actions">
                        <button class="btn btn-gold btn-grow" type="submit">
                            <?= $reporteEditado ? 'Guardar correcciones' : 'Enviar reporte ahora' ?> <i class="ri-send-plane-fill"></i>
                        </button>
                    </div>
                </form>
            </section>
        </div>
    <?php endif; ?>

<?php else: ?>
    <section class="card card-flush">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Semana</th>
                        <th>Asistencia</th>
                        <th>Ofrenda</th>
                        <th>Enviado</th>
                        <th>Actualizado</th>
                        <th>Visto supervisor</th>
                        <th>Visto admin</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportes as $reporte): ?>
                        <tr>
                            <td>
                                <strong><?= date('d/m/Y', strtotime($reporte['fecha_reporte'])) ?></strong>
                            </td>
                            <td><?= $reporte['total_asistencia'] ?></td>
                            <td>$<?= number_format($reporte['ofrenda_total'], 2) ?></td>
                            <td class="receipt"><?= fechaBonita($reporte['enviado_en']) ?></td>
                            <td class="receipt"><?= fechaBonita($reporte['actualizado_en']) ?></td>
                            <td>
                                <span class="badge <?= $reporte['visto_supervisor_en'] ? 'ok' : 'wait' ?>">
                                    <?= e(fechaBonita($reporte['visto_supervisor_en'])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $reporte['visto_admin_en'] ? 'ok' : 'wait' ?>">
                                    <?= e(fechaBonita($reporte['visto_admin_en'])) ?>
                                </span>
                            </td>
                            <td>
                                <a class="btn btn-soft btn-sm" href="lider-inicio.php?vista=reporte&amp;editar=<?= $reporte['id'] ?>">Editar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (!$reportes): ?>
            <div class="empty">Todavía no has enviado reportes.</div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php
$scripts = $vista === 'resumen'
    ? array_merge(
        ['https://cdn.jsdelivr.net/npm/chart.js@4.4.7'],
        $tieneUbicacion ? ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'] : [],
        ['js/lider.js'],
    )
    : [];

require __DIR__ . '/../components/foot.php';
?>
