<?php

require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';

requiereRol(['lider']);

$vista = $_GET['vista'] ?? 'resumen';
$vistasPermitidas = ['resumen', 'miembros', 'reporte', 'historial'];

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

$celulaId = (int) ($perfil['celula_id'] ?? 0);
$etiquetaTipo = etiquetaTipoCelula($perfil['tipo'] ?? 'adultos');
$tipoSlug = $perfil['tipo'] ?? 'adultos';

// Obtener lista de miembros de la célula activa
$miembros = [];
if ($celulaId > 0) {
    $stmtMiembros = $conexion->prepare(
        'SELECT * FROM miembros_celula WHERE celula_id = ? ORDER BY activo DESC, nombre_completo ASC'
    );
    $stmtMiembros->execute([$celulaId]);
    $miembros = $stmtMiembros->fetchAll();
}

$miembroEditado = null;
if ($vista === 'miembros' && isset($_GET['editar_miembro'])) {
    $miembroIdEditar = (int) $_GET['editar_miembro'];
    foreach ($miembros as $m) {
        if ((int) $m['id'] === $miembroIdEditar) {
            $miembroEditado = $m;
            break;
        }
    }
}

$reporteEditado = null;
$conversionesEditadas = [];
$bautismosEditados = [];
$visitasEditadas = [];

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

    // Cargar subregistros para precargar en el formulario
    $repId = (int) $reporteEditado['id'];
    $qConv = $conexion->prepare('SELECT * FROM reportes_lider_conversiones WHERE reporte_lider_id = ? ORDER BY id ASC');
    $qConv->execute([$repId]);
    $conversionesEditadas = $qConv->fetchAll();

    $qBaut = $conexion->prepare('SELECT * FROM reportes_lider_bautismos WHERE reporte_lider_id = ? ORDER BY id ASC');
    $qBaut->execute([$repId]);
    $bautismosEditados = $qBaut->fetchAll();

    $qVis = $conexion->prepare('SELECT * FROM reportes_lider_visitas WHERE reporte_lider_id = ? ORDER BY id ASC');
    $qVis->execute([$repId]);
    $visitasEditadas = $qVis->fetchAll();
}

// PROCESAMIENTO DE PETICIONES POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();

    try {
        if (!$celulaId) {
            throw new RuntimeException('El líder no tiene una célula asignada.');
        }

        $accion = $_POST['accion'] ?? 'guardar_reporte';

        // 1. Acciones del módulo de Miembros
        if ($accion === 'guardar_miembro') {
            $miembroId = (int) ($_POST['miembro_id'] ?? 0);
            $nombre = trim($_POST['nombre_completo'] ?? '');
            $estadoCivil = in_array($_POST['estado_civil'] ?? '', ['soltero', 'casado'], true) ? $_POST['estado_civil'] : 'soltero';
            $conversion = isset($_POST['conversion']) ? 1 : 0;
            $bautismoAgua = isset($_POST['bautismo_agua']) ? 1 : 0;
            $bautismoEspiritu = isset($_POST['bautismo_espiritu']) ? 1 : 0;
            $fechaNacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
            $direccion = trim($_POST['direccion'] ?? '') ?: null;
            $telefono = trim($_POST['telefono'] ?? '') ?: null;

            if ($nombre === '') {
                throw new InvalidArgumentException('El nombre del miembro es obligatorio.');
            }

            if ($miembroId > 0) {
                $stmt = $conexion->prepare(
                    'UPDATE miembros_celula SET
                        nombre_completo = ?, estado_civil = ?, conversion = ?,
                        bautismo_agua = ?, bautismo_espiritu = ?, fecha_nacimiento = ?,
                        direccion = ?, telefono = ?, actualizado_en = NOW()
                     WHERE id = ? AND celula_id = ?'
                );
                $stmt->execute([
                    $nombre, $estadoCivil, $conversion,
                    $bautismoAgua, $bautismoEspiritu, $fechaNacimiento,
                    $direccion, $telefono, $miembroId, $celulaId
                ]);
                flash('success', 'Datos del miembro actualizados correctamente.');
            } else {
                $stmt = $conexion->prepare(
                    'INSERT INTO miembros_celula (
                        celula_id, nombre_completo, estado_civil, conversion,
                        bautismo_agua, bautismo_espiritu, fecha_nacimiento,
                        direccion, telefono
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $celulaId, $nombre, $estadoCivil, $conversion,
                    $bautismoAgua, $bautismoEspiritu, $fechaNacimiento,
                    $direccion, $telefono
                ]);
                flash('success', 'Miembro registrado exitosamente.');
            }

            header('Location: lider-inicio.php?vista=miembros');
            exit;
        }

        if ($accion === 'toggle_estado_miembro') {
            $miembroId = (int) ($_POST['miembro_id'] ?? 0);
            $stmt = $conexion->prepare(
                'UPDATE miembros_celula SET activo = IF(activo = 1, 0, 1), actualizado_en = NOW()
                 WHERE id = ? AND celula_id = ?'
            );
            $stmt->execute([$miembroId, $celulaId]);
            flash('success', 'Estado del miembro actualizado.');
            header('Location: lider-inicio.php?vista=miembros');
            exit;
        }

        if ($accion === 'eliminar_miembro') {
            $miembroId = (int) ($_POST['miembro_id'] ?? 0);
            $stmt = $conexion->prepare(
                'DELETE FROM miembros_celula WHERE id = ? AND celula_id = ?'
            );
            $stmt->execute([$miembroId, $celulaId]);
            flash('success', 'Miembro eliminado.');
            header('Location: lider-inicio.php?vista=miembros');
            exit;
        }

        // 2. Acciones del Reporte Semanal
        if ($accion === 'guardar_reporte') {
            $conexion->beginTransaction();

            $reporteId = (int) ($_POST['reporte_id'] ?? 0);
            $fechaReporte = validarFechaReporteCelula($_POST['fecha_reporte'] ?? null);
            $anioSemana = anioSemanaReporte($fechaReporte);

            // Validar que no exista otro reporte para esta célula en la misma semana
            $stmtCheck = $conexion->prepare(
                'SELECT id, fecha_reporte
                 FROM reportes_lider
                 WHERE celula_id = ? AND anio_semana = ? AND id != ?
                 LIMIT 1'
            );
            $stmtCheck->execute([$celulaId, $anioSemana, $reporteId]);
            $reporteExistente = $stmtCheck->fetch();

            if ($reporteExistente) {
                throw new InvalidArgumentException(
                    'Ya existe un reporte para esta célula en esa semana (' .
                    date('d/m/Y', strtotime($reporteExistente['fecha_reporte'])) .
                    '). Puedes abrirlo desde el historial para editarlo.'
                );
            }

            // Filtrar y validar subregistros
            $toggleConversiones = !empty($_POST['toggle_conversiones']);
            $toggleBautismos = !empty($_POST['toggle_bautismos']);
            $toggleVisitas = !empty($_POST['toggle_visitas']);

            $filasConversiones = [];
            if ($toggleConversiones && !empty($_POST['conversiones']) && is_array($_POST['conversiones'])) {
                foreach ($_POST['conversiones'] as $c) {
                    $nom = trim($c['nombre_completo'] ?? '');
                    if ($nom !== '') {
                        $filasConversiones[] = [
                            'nombre_completo' => $nom,
                            'tipo_decision' => in_array($c['tipo_decision'] ?? '', ['conversion', 'reconcilio'], true) ? $c['tipo_decision'] : 'conversion',
                            'estado_civil' => in_array($c['estado_civil'] ?? '', ['soltero', 'casado'], true) ? $c['estado_civil'] : 'soltero',
                            'bautismo_agua' => !empty($c['bautismo_agua']) ? 1 : 0,
                            'bautismo_espiritu' => !empty($c['bautismo_espiritu']) ? 1 : 0,
                            'fecha_nacimiento' => !empty($c['fecha_nacimiento']) ? $c['fecha_nacimiento'] : null,
                            'fecha' => !empty($c['fecha']) ? $c['fecha'] : $fechaReporte,
                            'direccion' => trim($c['direccion'] ?? '') ?: null,
                            'telefono' => trim($c['telefono'] ?? '') ?: null,
                        ];
                    }
                }
            }

            $filasBautismos = [];
            if ($toggleBautismos && !empty($_POST['bautismos']) && is_array($_POST['bautismos'])) {
                foreach ($_POST['bautismos'] as $b) {
                    $nom = trim($b['nombre_completo'] ?? '');
                    if ($nom !== '') {
                        $filasBautismos[] = [
                            'nombre_completo' => $nom,
                            'tipo_celula' => in_array($b['tipo_celula'] ?? '', ['adultos', 'infantil', 'juvenil'], true) ? $b['tipo_celula'] : 'adultos',
                            'estado_civil' => in_array($b['estado_civil'] ?? '', ['soltero', 'casado'], true) ? $b['estado_civil'] : 'soltero',
                            'fecha_nacimiento' => !empty($b['fecha_nacimiento']) ? $b['fecha_nacimiento'] : null,
                            'fecha_bautismo' => !empty($b['fecha_bautismo']) ? $b['fecha_bautismo'] : $fechaReporte,
                            'direccion' => trim($b['direccion'] ?? '') ?: null,
                            'telefono' => trim($b['telefono'] ?? '') ?: null,
                        ];
                    }
                }
            }

            $filasVisitas = [];
            if ($toggleVisitas && !empty($_POST['visitas']) && is_array($_POST['visitas'])) {
                foreach ($_POST['visitas'] as $v) {
                    $nom = trim($v['nombre_completo'] ?? '');
                    if ($nom !== '') {
                        $numVisita = (int) ($v['numero_visita'] ?? 1);
                        if ($numVisita < 1 || $numVisita > 4) $numVisita = 1;
                        $filasVisitas[] = [
                            'nombre_completo' => $nom,
                            'tipo_celula' => in_array($v['tipo_celula'] ?? '', ['adultos', 'infantil', 'juvenil'], true) ? $v['tipo_celula'] : 'adultos',
                            'estado_civil' => in_array($v['estado_civil'] ?? '', ['soltero', 'casado'], true) ? $v['estado_civil'] : 'soltero',
                            'bautismo_agua' => !empty($v['bautismo_agua']) ? 1 : 0,
                            'bautismo_espiritu' => !empty($v['bautismo_espiritu']) ? 1 : 0,
                            'fecha_nacimiento' => !empty($v['fecha_nacimiento']) ? $v['fecha_nacimiento'] : null,
                            'numero_visita' => $numVisita,
                            'fecha_visita' => !empty($v['fecha_visita']) ? $v['fecha_visita'] : $fechaReporte,
                            'direccion' => trim($v['direccion'] ?? '') ?: null,
                            'telefono' => trim($v['telefono'] ?? '') ?: null,
                        ];
                    }
                }
            }

            if (!isset($_POST['ofrenda_iglesia']) || trim((string)$_POST['ofrenda_iglesia']) === '') {
                throw new InvalidArgumentException('El campo de Ofrenda Iglesia es obligatorio.');
            }

            if ($tipoSlug === 'adultos') {
                if ((!isset($_POST['adultos_hermanos']) || trim((string)$_POST['adultos_hermanos']) === '') &&
                    (!isset($_POST['adultos_amigos']) || trim((string)$_POST['adultos_amigos']) === '')) {
                    throw new InvalidArgumentException('Debes ingresar la asistencia de adultos.');
                }
            } elseif ($tipoSlug === 'ninos') {
                if (!isset($_POST['ninos']) || trim((string)$_POST['ninos']) === '') {
                    throw new InvalidArgumentException('Debes ingresar la asistencia de niños.');
                }
            }

            $totalConversiones = $toggleConversiones ? count($filasConversiones) : 0;
            $tieneBautismo = ($toggleBautismos && count($filasBautismos) > 0) ? 1 : 0;
            $tieneVisita = ($toggleVisitas && count($filasVisitas) > 0) ? 1 : 0;

            if ($reporteId > 0) {
                $consulta = $conexion->prepare(
                    'UPDATE reportes_lider SET
                        fecha_reporte = ?,
                        adultos_amigos = ?, adultos_hermanos = ?, jovenes_amigos = ?, jovenes_hermanos = ?, ninos = ?,
                        ofrenda_iglesia = ?, ofrenda_misiones = ?, ofrenda_bus = ?, conversion = ?,
                        visita_programada = ?, bautismo_agua = ?, observaciones = ?, actualizado_en = NOW()
                     WHERE id = ? AND lider_id = ?'
                );
                $consulta->execute([
                    $fechaReporte,
                    entero('adultos_amigos'), entero('adultos_hermanos'), entero('jovenes_amigos'),
                    entero('jovenes_hermanos'), entero('ninos'), dinero('ofrenda_iglesia'),
                    dinero('ofrenda_misiones'), dinero('ofrenda_bus'), $totalConversiones,
                    $tieneVisita, $tieneBautismo,
                    trim($_POST['observaciones'] ?? ''), $reporteId, $usuarioId,
                ]);

                $conexion->prepare('DELETE FROM reportes_lider_conversiones WHERE reporte_lider_id = ?')->execute([$reporteId]);
                $conexion->prepare('DELETE FROM reportes_lider_bautismos WHERE reporte_lider_id = ?')->execute([$reporteId]);
                $conexion->prepare('DELETE FROM reportes_lider_visitas WHERE reporte_lider_id = ?')->execute([$reporteId]);

                $targetReporteId = $reporteId;
                flash('success', 'Reporte corregido correctamente.');
            } else {
                $consulta = $conexion->prepare(
                    'INSERT INTO reportes_lider (
                        celula_id, lider_id, fecha_reporte,
                        adultos_amigos, adultos_hermanos, jovenes_amigos, jovenes_hermanos, ninos,
                        ofrenda_iglesia, ofrenda_misiones, ofrenda_bus, conversion,
                        visita_programada, bautismo_agua, observaciones
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $consulta->execute([
                    $celulaId, $usuarioId, $fechaReporte,
                    entero('adultos_amigos'), entero('adultos_hermanos'), entero('jovenes_amigos'),
                    entero('jovenes_hermanos'), entero('ninos'), dinero('ofrenda_iglesia'),
                    dinero('ofrenda_misiones'), dinero('ofrenda_bus'), $totalConversiones,
                    $tieneVisita, $tieneBautismo,
                    trim($_POST['observaciones'] ?? ''),
                ]);
                $targetReporteId = (int) $conexion->lastInsertId();
                flash('success', 'Reporte enviado al supervisor y al administrador con éxito.');
            }

            // Insertar conversiones
            if (!empty($filasConversiones)) {
                $stmtConv = $conexion->prepare(
                    'INSERT INTO reportes_lider_conversiones (
                        reporte_lider_id, fecha, nombre_completo, tipo_decision, estado_civil,
                        bautismo_agua, bautismo_espiritu, fecha_nacimiento, direccion, telefono
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                foreach ($filasConversiones as $fc) {
                    $stmtConv->execute([
                        $targetReporteId, $fc['fecha'], $fc['nombre_completo'], $fc['tipo_decision'],
                        $fc['estado_civil'], $fc['bautismo_agua'], $fc['bautismo_espiritu'],
                        $fc['fecha_nacimiento'], $fc['direccion'], $fc['telefono']
                    ]);
                }
            }

            // Insertar bautismos
            if (!empty($filasBautismos)) {
                $stmtBaut = $conexion->prepare(
                    'INSERT INTO reportes_lider_bautismos (
                        reporte_lider_id, nombre_completo, tipo_celula, estado_civil,
                        fecha_nacimiento, fecha_bautismo, direccion, telefono
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                foreach ($filasBautismos as $fb) {
                    $stmtBaut->execute([
                        $targetReporteId, $fb['nombre_completo'], $fb['tipo_celula'], $fb['estado_civil'],
                        $fb['fecha_nacimiento'], $fb['fecha_bautismo'], $fb['direccion'], $fb['telefono']
                    ]);
                }
            }

            // Insertar visitas
            if (!empty($filasVisitas)) {
                $stmtVis = $conexion->prepare(
                    'INSERT INTO reportes_lider_visitas (
                        reporte_lider_id, nombre_completo, tipo_celula, estado_civil,
                        bautismo_agua, bautismo_espiritu, fecha_nacimiento, numero_visita,
                        fecha_visita, direccion, telefono
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                foreach ($filasVisitas as $fv) {
                    $stmtVis->execute([
                        $targetReporteId, $fv['nombre_completo'], $fv['tipo_celula'], $fv['estado_civil'],
                        $fv['bautismo_agua'], $fv['bautismo_espiritu'], $fv['fecha_nacimiento'],
                        $fv['numero_visita'], $fv['fecha_visita'], $fv['direccion'], $fv['telefono']
                    ]);
                }
            }

            $conexion->commit();
            header('Location: lider-inicio.php?vista=historial');
            exit;
        }
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        $mensaje = str_contains($error->getMessage(), 'Duplicate')
            ? 'Ya existe un reporte para esta célula en esa semana. Puedes abrirlo desde el historial y corregirlo.'
            : ($error instanceof InvalidArgumentException ? $error->getMessage() : 'No fue posible guardar los datos: ' . $error->getMessage());

        flash('error', $mensaje);
        header('Location: lider-inicio.php?vista=' . $vista);
        exit;
    }
}

// Consultar reportes para el dashboard e historial
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
    'miembros' => 'Miembros de la célula',
    'reporte' => 'Enviar reporte semanal',
    'historial' => 'Mis reportes',
];

$titulo = $titulos[$vista];
$subtitulo = ($perfil['celula'] ?? 'Sin célula asignada') .
    ' · ' .
    ($perfil['sector'] ?? 'Sin sector') .
    ' · Célula ' .
    $etiquetaTipo;

$tieneUbicacion = $perfil['lat'] !== null && $perfil['lng'] !== null;
$styles = $vista === 'resumen' && $tieneUbicacion
    ? ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.css']
    : [];

require __DIR__ . '/../components/head.php';
require __DIR__ . '/../components/sidebar.php';
require __DIR__ . '/../components/top.php';
?>

<!-- DATALIST GLOBAL PARA AUTOCOMPLETAR MIEMBROS -->
<datalist id="lista-miembros-celula">
    <?php foreach ($miembros as $m): ?>
        <?php if ($m['activo']): ?>
            <option
                value="<?= e($m['nombre_completo']) ?>"
                data-estado-civil="<?= e($m['estado_civil']) ?>"
                data-nacimiento="<?= e($m['fecha_nacimiento'] ?? '') ?>"
                data-direccion="<?= e($m['direccion'] ?? '') ?>"
                data-telefono="<?= e($m['telefono'] ?? '') ?>"
                data-bautismo-agua="<?= (int) $m['bautismo_agua'] ?>"
                data-bautismo-espiritu="<?= (int) $m['bautismo_espiritu'] ?>"
            ><?= e($m['nombre_completo']) ?> (<?= e(ucfirst($m['estado_civil'])) ?>)</option>
        <?php endif; ?>
    <?php endforeach; ?>
</datalist>

<?php if ($vista === 'resumen'): ?>
    <!-- BANNER DE IDENTIFICACIÓN DE CÉLULA CON TIPO DESTACADO (SOLO EN INICIO/RESUMEN) -->
    <div class="celula-info-banner">
        <div class="banner-details">
            <div class="banner-meta">
                <span><i class="ri-community-line"></i> <?= e($perfil['sector'] ?? 'Sin sector') ?></span>
                <span><i class="ri-map-pin-line"></i> <?= e($perfil['direccion'] ?: 'Sin dirección registrada') ?></span>
            </div>
            <h2><?= e($perfil['celula'] ?? 'Célula sin asignar') ?></h2>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge-celula badge-celula-<?= e($tipoSlug) ?>">
                <i class="ri-shield-user-line"></i> Célula <?= e($etiquetaTipo) ?>
            </span>
            <a href="<?= urlApp('exportar-miembros.php') ?>" class="btn btn-gold btn-sm" title="Descargar miembros de mi célula en PDF">
                <i class="ri-file-pdf-2-line"></i> Miembros en PDF
            </a>
        </div>
    </div>
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

    <div class="grid-4">
        <div class="stat">
            <div class="label">Última asistencia</div>
            <div class="value"><?= $ultimoReporte['total_asistencia'] ?? 0 ?></div>
            <div class="hint">
                <?= $ultimoReporte
                    ? date('d/m/Y', strtotime($ultimoReporte['fecha_reporte']))
                    : 'Aún sin reportes' ?>
            </div>
        </div>
        <div class="stat">
            <div class="label">Miembros registrados</div>
            <div class="value"><?= count(array_filter($miembros, fn($m) => $m['activo'])) ?></div>
            <div class="hint"><?= count($miembros) ?> en total</div>
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

<?php elseif ($vista === 'miembros'): ?>
    <?php
    $totalActivos = count(array_filter($miembros, fn($m) => (int)$m['activo'] === 1));
    $totalBautAgua = count(array_filter($miembros, fn($m) => (int)$m['activo'] === 1 && (int)$m['bautismo_agua'] === 1));
    $totalBautEsp = count(array_filter($miembros, fn($m) => (int)$m['activo'] === 1 && (int)$m['bautismo_espiritu'] === 1));
    $totalCasados = count(array_filter($miembros, fn($m) => (int)$m['activo'] === 1 && $m['estado_civil'] === 'casado'));
    ?>

    <div class="grid-4">
        <div class="stat">
            <div class="label">Miembros activos</div>
            <div class="value"><?= $totalActivos ?></div>
        </div>
        <div class="stat">
            <div class="label">Bautizados en agua</div>
            <div class="value"><?= $totalBautAgua ?></div>
        </div>
        <div class="stat">
            <div class="label">Bautizados en E.S.</div>
            <div class="value"><?= $totalBautEsp ?></div>
        </div>
        <div class="stat">
            <div class="label">Casados / Solteros</div>
            <div class="value"><?= $totalCasados ?> / <?= $totalActivos - $totalCasados ?></div>
        </div>
    </div>

    <!-- FORMULARIO DE AGREGAR / EDITAR MIEMBRO -->
    <section class="card">
        <div class="card-head">
            <div>
                <h3><?= $miembroEditado ? 'Editar datos de miembro' : 'Registrar nuevo miembro' ?></h3>
            </div>
            <?php if ($miembroEditado): ?>
                <a href="lider-inicio.php?vista=miembros" class="btn btn-soft btn-sm">
                    <i class="ri-close-line"></i> Cancelar edición
                </a>
            <?php endif; ?>
        </div>

        <form method="post">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <input type="hidden" name="accion" value="guardar_miembro">
            <input type="hidden" name="miembro_id" value="<?= $miembroEditado['id'] ?? 0 ?>">

            <div class="subform-grid">
                <div class="subform-col-2">
                    <label for="m_nombre">Nombres y Apellidos *</label>
                    <input
                        id="m_nombre"
                        type="text"
                        name="nombre_completo"
                        placeholder="Nombre completo"
                        value="<?= e($miembroEditado['nombre_completo'] ?? '') ?>"
                        required
                    >
                </div>

                <div>
                    <label for="m_estado_civil">Estado Civil</label>
                    <select id="m_estado_civil" name="estado_civil">
                        <option value="soltero" <?= ($miembroEditado['estado_civil'] ?? '') === 'soltero' ? 'selected' : '' ?>>Soltero(a)</option>
                        <option value="casado" <?= ($miembroEditado['estado_civil'] ?? '') === 'casado' ? 'selected' : '' ?>>Casado(a)</option>
                    </select>
                </div>

                <div>
                    <label for="m_nacimiento">Fecha de Nacimiento</label>
                    <input
                        id="m_nacimiento"
                        type="date"
                        name="fecha_nacimiento"
                        value="<?= e($miembroEditado['fecha_nacimiento'] ?? '') ?>"
                    >
                </div>

                <div>
                    <label for="m_telefono">Teléfono</label>
                    <input
                        id="m_telefono"
                        type="tel"
                        name="telefono"
                        placeholder="7000-0000"
                        value="<?= e($miembroEditado['telefono'] ?? '') ?>"
                    >
                </div>

                <div class="subform-col-2">
                    <label for="m_direccion">Dirección</label>
                    <input
                        id="m_direccion"
                        type="text"
                        name="direccion"
                        placeholder="Dirección o punto de referencia"
                        value="<?= e($miembroEditado['direccion'] ?? '') ?>"
                    >
                </div>

                <div class="subform-col-full subform-checks">
                    <label>
                        <input
                            type="checkbox"
                            name="conversion"
                            <?= !empty($miembroEditado['conversion']) ? 'checked' : '' ?>
                        >
                        Conversión
                    </label>

                    <label>
                        <input
                            type="checkbox"
                            name="bautismo_agua"
                            <?= !empty($miembroEditado['bautismo_agua']) ? 'checked' : '' ?>
                        >
                        Bautismo en Agua
                    </label>

                    <label>
                        <input
                            type="checkbox"
                            name="bautismo_espiritu"
                            <?= !empty($miembroEditado['bautismo_espiritu']) ? 'checked' : '' ?>
                        >
                        Bautismo en Espíritu Santo
                    </label>
                </div>
            </div>

            <div class="form-actions" style="margin-top: 16px;">
                <button class="btn btn-gold" type="submit">
                    <i class="ri-save-line"></i> <?= $miembroEditado ? 'Actualizar miembro' : 'Guardar miembro' ?>
                </button>
            </div>
        </form>
    </section>

    <!-- LISTADO DE MIEMBROS -->
    <section class="card card-flush">
        <div class="card-head">
            <div>
                <p class="eyebrow">Miembros de mi célula</p>
                <h3>Padrón celular</h3>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge ok"><?= count($miembros) ?> registrados</span>
                <a href="<?= urlApp('exportar-miembros.php') ?>" class="btn btn-gold btn-sm" title="Descargar padrón de miembros en PDF">
                    <i class="ri-file-pdf-2-line"></i> Descargar PDF
                </a>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width: 45px;">No.</th>
                        <th>Nombres</th>
                        <th>Estado Civil</th>
                        <th>Conversión</th>
                        <th>Bautismos</th>
                        <th>Nacimiento</th>
                        <th>Contacto</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($miembros as $idx => $m): ?>
                        <tr style="<?= !$m['activo'] ? 'opacity: 0.6;' : '' ?>">
                            <td data-label="No."><strong><?= $idx + 1 ?></strong></td>
                            <td data-label="Nombres">
                                <strong><?= e($m['nombre_completo']) ?></strong>
                            </td>
                            <td data-label="Estado Civil"><?= e(ucfirst($m['estado_civil'])) ?></td>
                            <td data-label="Conversión">
                                <span class="badge <?= $m['conversion'] ? 'ok' : 'wait' ?>">
                                    <?= $m['conversion'] ? 'Sí' : 'No' ?>
                                </span>
                            </td>
                            <td data-label="Bautismos">
                                <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                    <span class="badge <?= $m['bautismo_agua'] ? 'ok' : 'wait' ?>" title="Bautismo en Agua">
                                        Agua: <?= $m['bautismo_agua'] ? 'Sí' : 'No' ?>
                                    </span>
                                    <span class="badge <?= $m['bautismo_espiritu'] ? 'ok' : 'wait' ?>" title="Bautismo en Espíritu Santo">
                                        E.S.: <?= $m['bautismo_espiritu'] ? 'Sí' : 'No' ?>
                                    </span>
                                </div>
                            </td>
                            <td data-label="Nacimiento">
                                <?= $m['fecha_nacimiento'] ? date('d/m/Y', strtotime($m['fecha_nacimiento'])) : '—' ?>
                            </td>
                            <td data-label="Contacto">
                                <div><i class="ri-phone-line"></i> <?= e($m['telefono'] ?: '—') ?></div>
                                <small class="muted"><?= e($m['direccion'] ?: '') ?></small>
                            </td>
                            <td data-label="Estado">
                                <span class="badge <?= $m['activo'] ? 'ok' : 'off' ?>">
                                    <?= $m['activo'] ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td data-label="Acciones" class="actions-cell">
                                <div style="display: flex; gap: 5px; justify-content: flex-end;">
                                    <a class="btn btn-soft btn-sm" href="lider-inicio.php?vista=miembros&amp;editar_miembro=<?= $m['id'] ?>" title="Editar">
                                        <i class="ri-edit-line"></i>
                                    </a>

                                    <form method="post" style="display:inline;" onsubmit="return confirm('¿Deseas cambiar el estado de este miembro?');">
                                        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                                        <input type="hidden" name="accion" value="toggle_estado_miembro">
                                        <input type="hidden" name="miembro_id" value="<?= $m['id'] ?>">
                                        <button class="btn btn-soft btn-sm" type="submit" title="<?= $m['activo'] ? 'Dar de baja' : 'Reactivar' ?>">
                                            <i class="ri-user-shared-line"></i>
                                        </button>
                                    </form>

                                    <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar definitivamente este registro?');">
                                        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                                        <input type="hidden" name="accion" value="eliminar_miembro">
                                        <input type="hidden" name="miembro_id" value="<?= $m['id'] ?>">
                                        <button class="btn btn-danger btn-sm" type="submit" title="Eliminar">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (!$miembros): ?>
            <div class="empty">Aún no has registrado miembros en tu célula. Utiliza el formulario superior para comenzar.</div>
        <?php endif; ?>
    </section>

<?php elseif ($vista === 'reporte'): ?>
    <?php if (!$celulaId): ?>
        <div class="alert alert-danger">
            El administrador debe asignarte una célula antes de enviar reportes.
        </div>
    <?php else: ?>
            <section class="card card-flush">
                <div class="card-head">
                    <div>
                        <p class="eyebrow">Reporte semanal de reunión celular</p>
                        <h3><?= e($perfil['celula']) ?></h3>
                    </div>
                    <span class="badge-celula badge-celula-<?= e($tipoSlug) ?>">
                        <i class="ri-shield-user-line"></i> Célula <?= e($etiquetaTipo) ?>
                    </span>
                </div>

                <form method="post" id="formReporteLider">
                    <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                    <input type="hidden" name="accion" value="guardar_reporte">
                    <input type="hidden" name="reporte_id" value="<?= $reporteEditado['id'] ?? 0 ?>">
                    <input type="hidden" name="conversion" id="inputConversionCount" value="<?= $reporteEditado['conversion'] ?? count($conversionesEditadas) ?>">

                    <div style="max-width: 320px; margin-bottom: 28px;">
                        <label for="fecha_reporte" style="font-size: 12.5px; font-weight: 700; color: var(--night); margin-bottom: 7px; display: flex; align-items: center; gap: 6px;">
                            <i class="ri-calendar-event-line" style="color: var(--gold); font-size: 16px;"></i> Fecha de la reunión de célula
                        </label>
                        <input
                            id="fecha_reporte"
                            type="date"
                            name="fecha_reporte"
                            value="<?= e($reporteEditado['fecha_reporte'] ?? date('Y-m-d')) ?>"
                            max="<?= date('Y-m-d', strtotime('sunday this week')) ?>"
                            required
                            style="padding: 10px 12px; font-size: 14px; font-weight: 600; border-radius: 9px;"
                        >
                    </div>

                    <h3 class="form-section-title"><i class="ri-user-follow-line"></i> Asistencia a la reunión</h3>
                    <div class="attendance-table-wrap">
                        <table class="attendance-form-table" data-tipo-celula="<?= e($tipoSlug) ?>">
                            <thead>
                                <tr>
                                    <th colspan="2" class="th-section th-adultos">
                                        <i class="ri-user-line"></i> Adultos <?= $tipoSlug === 'adultos' ? '<span class="badge-req">* Obligatorio</span>' : '' ?>
                                    </th>
                                    <th colspan="2" class="th-section th-jovenes">
                                        <i class="ri-user-smile-line"></i> Jóvenes
                                    </th>
                                    <th class="th-section th-ninos">
                                        <i class="ri-bear-smile-line"></i> Niños <?= $tipoSlug === 'ninos' ? '<span class="badge-req">* Obligatorio</span>' : '' ?>
                                    </th>
                                    <th class="th-section th-total">
                                        <i class="ri-calculator-line"></i> Totales Asistencia
                                    </th>
                                </tr>
                                <tr class="th-sub">
                                    <th>Hermanos</th>
                                    <th>Amigos</th>
                                    <th>Hermanos</th>
                                    <th>Amigos</th>
                                    <th>Niños</th>
                                    <th class="th-total-col">Célula / Global</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="td-adultos-h">
                                        <span class="mobile-td-label">
                                            <i class="ri-user-line"></i> Adultos Hermanos <?= $tipoSlug === 'adultos' ? '<span class="badge-req">*</span>' : '' ?>
                                        </span>
                                        <input
                                            type="number"
                                            min="0"
                                            name="adultos_hermanos"
                                            id="adultos_hermanos"
                                            value="<?= $reporteEditado['adultos_hermanos'] ?? 0 ?>"
                                            <?= $tipoSlug === 'adultos' ? 'required' : '' ?>
                                        >
                                    </td>
                                    <td class="td-adultos-a">
                                        <span class="mobile-td-label">
                                            <i class="ri-user-line"></i> Adultos Amigos <?= $tipoSlug === 'adultos' ? '<span class="badge-req">*</span>' : '' ?>
                                        </span>
                                        <input
                                            type="number"
                                            min="0"
                                            name="adultos_amigos"
                                            id="adultos_amigos"
                                            value="<?= $reporteEditado['adultos_amigos'] ?? 0 ?>"
                                            <?= $tipoSlug === 'adultos' ? 'required' : '' ?>
                                        >
                                    </td>
                                    <td class="td-jovenes-h">
                                        <span class="mobile-td-label">
                                            <i class="ri-user-smile-line"></i> Jóvenes Hermanos
                                        </span>
                                        <input
                                            type="number"
                                            min="0"
                                            name="jovenes_hermanos"
                                            id="jovenes_hermanos"
                                            value="<?= $reporteEditado['jovenes_hermanos'] ?? 0 ?>"
                                        >
                                    </td>
                                    <td class="td-jovenes-a">
                                        <span class="mobile-td-label">
                                            <i class="ri-user-smile-line"></i> Jóvenes Amigos
                                        </span>
                                        <input
                                            type="number"
                                            min="0"
                                            name="jovenes_amigos"
                                            id="jovenes_amigos"
                                            value="<?= $reporteEditado['jovenes_amigos'] ?? 0 ?>"
                                        >
                                    </td>
                                    <td class="td-ninos">
                                        <span class="mobile-td-label">
                                            <i class="ri-bear-smile-line"></i> Niños <?= $tipoSlug === 'ninos' ? '<span class="badge-req">* Obligatorio</span>' : '' ?>
                                        </span>
                                        <input
                                            type="number"
                                            min="0"
                                            name="ninos"
                                            id="ninos"
                                            value="<?= $reporteEditado['ninos'] ?? 0 ?>"
                                            <?= $tipoSlug === 'ninos' ? 'required' : '' ?>
                                        >
                                    </td>
                                    <td class="td-total-highlight">
                                        <span class="mobile-td-label">
                                            <i class="ri-calculator-line"></i> Resumen de Asistencia
                                        </span>
                                        <div class="totals-dual-wrap">
                                            <div class="total-box total-box-target">
                                                <span class="total-badge-label" id="labelTotalGrupo">
                                                    <?= $tipoSlug === 'ninos' ? 'Solo Niños' : ($tipoSlug === 'jovenes' ? 'Solo Jóvenes' : 'Solo Adultos') ?>
                                                </span>
                                                <div class="total-big-number" id="totalGrupoDisplay">0</div>
                                            </div>
                                            <div class="total-box-divider"></div>
                                            <div class="total-box total-box-global">
                                                <span class="total-badge-label total-badge-global">
                                                    Total Global
                                                </span>
                                                <div class="total-big-number total-global-number" id="totalGlobalDisplay">0</div>
                                            </div>
                                        </div>
                                        <small class="total-rule-caption" id="totalAsistenciaCaption">
                                            Calculando asistencia...
                                        </small>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h3 class="form-section-title"><i class="ri-hand-coin-line"></i> Ofrendas</h3>
                    <div class="report-numbers">
                        <div>
                            <label for="ofrenda_iglesia">Ofrenda Iglesia <span class="badge-req">* Obligatorio</span></label>
                            <input
                                id="ofrenda_iglesia"
                                type="number"
                                min="0"
                                step="0.01"
                                name="ofrenda_iglesia"
                                value="<?= $reporteEditado['ofrenda_iglesia'] ?? 0 ?>"
                                required
                            >
                        </div>
                        <div>
                            <label for="ofrenda_misiones">Ofrenda Misiones</label>
                            <input
                                id="ofrenda_misiones"
                                type="number"
                                min="0"
                                step="0.01"
                                name="ofrenda_misiones"
                                value="<?= $reporteEditado['ofrenda_misiones'] ?? 0 ?>"
                            >
                        </div>
                        <div>
                            <label for="ofrenda_bus">Ofrenda Transporte</label>
                            <input
                                id="ofrenda_bus"
                                type="number"
                                min="0"
                                step="0.01"
                                name="ofrenda_bus"
                                value="<?= $reporteEditado['ofrenda_bus'] ?? 0 ?>"
                            >
                        </div>
                        <div class="total-ofrenda-box">
                            <span class="total-label">Total Ofrenda</span>
                            <div id="totalOfrendaDisplay" class="total-value">$0.00</div>
                        </div>
                    </div>

                    <!-- =========================================================
                         SUBFORMULARIO 1: CONVERSIONES / RECONCILIACIONES
                         ========================================================= -->
                    <h3 class="form-section-title"><i class="ri-heart-pulse-line"></i> Resultados espirituales y seguimiento</h3>

                    <?php $hayConversiones = !empty($conversionesEditadas) || (!empty($reporteEditado) && $reporteEditado['conversion'] > 0); ?>
                    <div class="subform-toggle-card <?= $hayConversiones ? 'is-active' : '' ?>" id="subform-conversiones">
                        <div class="subform-header" onclick="document.getElementById('toggle_conversiones').click()">
                            <div class="subform-header-left">
                                <i class="ri-user-add-line"></i>
                                <div>
                                    <h4>Conversiones y Reconciliaciones</h4>
                                    <p class="muted">Decisiones de entrega o reconcilio registradas en la célula</p>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;" onclick="event.stopPropagation();">
                                <span class="badge ok" id="badgeCountConversiones"><?= count($conversionesEditadas) ?></span>
                                <label class="switch-toggle">
                                    <input
                                        type="checkbox"
                                        name="toggle_conversiones"
                                        id="toggle_conversiones"
                                        data-subform-toggle="conversiones"
                                        <?= $hayConversiones ? 'checked' : '' ?>
                                    >
                                    <span class="switch-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="subform-body">
                            <div class="dynamic-rows-container" id="container-conversiones">
                                <?php foreach ($conversionesEditadas as $i => $conv): ?>
                                    <div class="dynamic-card-item">
                                        <div class="dynamic-card-head">
                                            <strong>
                                                <span class="row-number-badge">#<?= $i + 1 ?></span>
                                                <span>Datos de Conversión / Reconcilio</span>
                                            </strong>
                                            <button type="button" class="btn-remove-row"><i class="ri-delete-bin-line"></i> Eliminar</button>
                                        </div>
                                        <div class="subform-grid">
                                            <div class="subform-col-2">
                                                <label>Nombres y Apellidos *</label>
                                                <input
                                                    type="text"
                                                    name="conversiones[<?= $i ?>][nombre_completo]"
                                                    value="<?= e($conv['nombre_completo']) ?>"
                                                    placeholder="Nombre completo"
                                                    list="lista-miembros-celula"
                                                    data-member-autocomplete
                                                    required
                                                >
                                            </div>
                                            <div>
                                                <label>Tipo de Decisión</label>
                                                <select name="conversiones[<?= $i ?>][tipo_decision]">
                                                    <option value="conversion" <?= $conv['tipo_decision'] === 'conversion' ? 'selected' : '' ?>>Conversión</option>
                                                    <option value="reconcilio" <?= $conv['tipo_decision'] === 'reconcilio' ? 'selected' : '' ?>>Reconcilio</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label>Estado Civil</label>
                                                <select name="conversiones[<?= $i ?>][estado_civil]" data-field="estado_civil">
                                                    <option value="soltero" <?= $conv['estado_civil'] === 'soltero' ? 'selected' : '' ?>>Soltero(a)</option>
                                                    <option value="casado" <?= $conv['estado_civil'] === 'casado' ? 'selected' : '' ?>>Casado(a)</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label>Fecha de Nacimiento</label>
                                                <input type="date" name="conversiones[<?= $i ?>][fecha_nacimiento]" value="<?= e($conv['fecha_nacimiento'] ?? '') ?>" data-field="fecha_nacimiento">
                                            </div>
                                            <div>
                                                <label>Fecha de Decisión</label>
                                                <input type="date" name="conversiones[<?= $i ?>][fecha]" value="<?= e($conv['fecha'] ?? '') ?>">
                                            </div>
                                            <div>
                                                <label>Teléfono</label>
                                                <input type="tel" name="conversiones[<?= $i ?>][telefono]" value="<?= e($conv['telefono'] ?? '') ?>" data-field="telefono" placeholder="Teléfono">
                                            </div>
                                            <div class="subform-col-2">
                                                <label>Dirección</label>
                                                <input type="text" name="conversiones[<?= $i ?>][direccion]" value="<?= e($conv['direccion'] ?? '') ?>" data-field="direccion" placeholder="Dirección de habitación">
                                            </div>
                                            <div class="subform-col-full subform-checks">
                                                <label>
                                                    <input type="checkbox" name="conversiones[<?= $i ?>][bautismo_agua]" value="1" <?= !empty($conv['bautismo_agua']) ? 'checked' : '' ?> data-field="bautismo_agua">
                                                    Bautismo en Agua
                                                </label>
                                                <label>
                                                    <input type="checkbox" name="conversiones[<?= $i ?>][bautismo_espiritu]" value="1" <?= !empty($conv['bautismo_espiritu']) ? 'checked' : '' ?> data-field="bautismo_espiritu">
                                                    Bautismo en Espíritu Santo
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="subform-actions">
                                <button type="button" class="btn btn-soft btn-sm" data-add-row="conversiones">
                                    <i class="ri-user-add-line"></i> + Añadir persona convertida
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- =========================================================
                         SUBFORMULARIO 2: BAUTISMOS EN AGUA
                         ========================================================= -->
                    <?php $hayBautismos = !empty($bautismosEditados) || (!empty($reporteEditado) && !empty($reporteEditado['bautismo_agua'])); ?>
                    <div class="subform-toggle-card <?= $hayBautismos ? 'is-active' : '' ?>" id="subform-bautismos">
                        <div class="subform-header" onclick="document.getElementById('toggle_bautismos').click()">
                            <div class="subform-header-left">
                                <i class="ri-drop-line"></i>
                                <div>
                                    <h4>Candidatos a Bautismo en Agua</h4>
                                    <p class="muted">Miembros de la célula postulados para bautismo</p>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;" onclick="event.stopPropagation();">
                                <span class="badge ok" id="badgeCountBautismos"><?= count($bautismosEditados) ?></span>
                                <label class="switch-toggle">
                                    <input
                                        type="checkbox"
                                        name="toggle_bautismos"
                                        id="toggle_bautismos"
                                        data-subform-toggle="bautismos"
                                        <?= $hayBautismos ? 'checked' : '' ?>
                                    >
                                    <span class="switch-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="subform-body">
                            <div class="dynamic-rows-container" id="container-bautismos">
                                <?php foreach ($bautismosEditados as $i => $baut): ?>
                                    <div class="dynamic-card-item">
                                        <div class="dynamic-card-head">
                                            <strong>
                                                <span class="row-number-badge">#<?= $i + 1 ?></span>
                                                <span>Datos del Candidato a Bautismo</span>
                                            </strong>
                                            <button type="button" class="btn-remove-row"><i class="ri-delete-bin-line"></i> Eliminar</button>
                                        </div>
                                        <div class="subform-grid">
                                            <div class="subform-col-2">
                                                <label>Nombres y Apellidos *</label>
                                                <input
                                                    type="text"
                                                    name="bautismos[<?= $i ?>][nombre_completo]"
                                                    value="<?= e($baut['nombre_completo']) ?>"
                                                    placeholder="Nombre completo"
                                                    list="lista-miembros-celula"
                                                    data-member-autocomplete
                                                    required
                                                >
                                            </div>
                                            <div>
                                                <label>Célula (Tipo)</label>
                                                <select name="bautismos[<?= $i ?>][tipo_celula]">
                                                    <option value="adultos" <?= $baut['tipo_celula'] === 'adultos' ? 'selected' : '' ?>>Adultos</option>
                                                    <option value="infantil" <?= $baut['tipo_celula'] === 'infantil' ? 'selected' : '' ?>>Infantil</option>
                                                    <option value="juvenil" <?= $baut['tipo_celula'] === 'juvenil' ? 'selected' : '' ?>>Juvenil</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label>Estado Civil</label>
                                                <select name="bautismos[<?= $i ?>][estado_civil]" data-field="estado_civil">
                                                    <option value="soltero" <?= $baut['estado_civil'] === 'soltero' ? 'selected' : '' ?>>Soltero(a)</option>
                                                    <option value="casado" <?= $baut['estado_civil'] === 'casado' ? 'selected' : '' ?>>Casado(a)</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label>Fecha de Nacimiento</label>
                                                <input type="date" name="bautismos[<?= $i ?>][fecha_nacimiento]" value="<?= e($baut['fecha_nacimiento'] ?? '') ?>" data-field="fecha_nacimiento">
                                            </div>
                                            <div>
                                                <label>Fecha de Postulación</label>
                                                <input type="date" name="bautismos[<?= $i ?>][fecha_bautismo]" value="<?= e($baut['fecha_bautismo'] ?? '') ?>">
                                            </div>
                                            <div>
                                                <label>Teléfono</label>
                                                <input type="tel" name="bautismos[<?= $i ?>][telefono]" value="<?= e($baut['telefono'] ?? '') ?>" data-field="telefono" placeholder="Teléfono">
                                            </div>
                                            <div class="subform-col-2">
                                                <label>Dirección</label>
                                                <input type="text" name="bautismos[<?= $i ?>][direccion]" value="<?= e($baut['direccion'] ?? '') ?>" data-field="direccion" placeholder="Dirección">
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="subform-actions">
                                <button type="button" class="btn btn-soft btn-sm" data-add-row="bautismos">
                                    <i class="ri-drop-line"></i> + Añadir candidato a bautismo
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- =========================================================
                         SUBFORMULARIO 3: VISITAS PROGRAMADAS
                         ========================================================= -->
                    <?php $hayVisitas = !empty($visitasEditadas) || (!empty($reporteEditado) && !empty($reporteEditado['visita_programada'])); ?>
                    <div class="subform-toggle-card <?= $hayVisitas ? 'is-active' : '' ?>" id="subform-visitas">
                        <div class="subform-header" onclick="document.getElementById('toggle_visitas').click()">
                            <div class="subform-header-left">
                                <i class="ri-home-heart-line"></i>
                                <div>
                                    <h4>Visitas Programadas</h4>
                                    <p class="muted">Control y seguimiento a hogares o personas visitadas</p>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;" onclick="event.stopPropagation();">
                                <span class="badge ok" id="badgeCountVisitas"><?= count($visitasEditadas) ?></span>
                                <label class="switch-toggle">
                                    <input
                                        type="checkbox"
                                        name="toggle_visitas"
                                        id="toggle_visitas"
                                        data-subform-toggle="visitas"
                                        <?= $hayVisitas ? 'checked' : '' ?>
                                    >
                                    <span class="switch-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="subform-body">
                            <div class="dynamic-rows-container" id="container-visitas">
                                <?php foreach ($visitasEditadas as $i => $vis): ?>
                                    <div class="dynamic-card-item">
                                        <div class="dynamic-card-head">
                                            <strong>
                                                <span class="row-number-badge">#<?= $i + 1 ?></span>
                                                <span>Datos y Control de Visita Programada</span>
                                            </strong>
                                            <button type="button" class="btn-remove-row"><i class="ri-delete-bin-line"></i> Eliminar</button>
                                        </div>
                                        <div class="subform-grid">
                                            <div class="subform-col-2">
                                                <label>Nombres y Apellidos *</label>
                                                <input
                                                    type="text"
                                                    name="visitas[<?= $i ?>][nombre_completo]"
                                                    value="<?= e($vis['nombre_completo']) ?>"
                                                    placeholder="Persona visitada"
                                                    list="lista-miembros-celula"
                                                    data-member-autocomplete
                                                    required
                                                >
                                            </div>
                                            <div>
                                                <label>Célula (Tipo)</label>
                                                <select name="visitas[<?= $i ?>][tipo_celula]">
                                                    <option value="adultos" <?= $vis['tipo_celula'] === 'adultos' ? 'selected' : '' ?>>Adulto</option>
                                                    <option value="infantil" <?= $vis['tipo_celula'] === 'infantil' ? 'selected' : '' ?>>Infantil</option>
                                                    <option value="juvenil" <?= $vis['tipo_celula'] === 'juvenil' ? 'selected' : '' ?>>Juvenil</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label>Estado Civil</label>
                                                <select name="visitas[<?= $i ?>][estado_civil]" data-field="estado_civil">
                                                    <option value="soltero" <?= $vis['estado_civil'] === 'soltero' ? 'selected' : '' ?>>Soltero(a)</option>
                                                    <option value="casado" <?= $vis['estado_civil'] === 'casado' ? 'selected' : '' ?>>Casado(a)</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label>No. de Visita</label>
                                                <select name="visitas[<?= $i ?>][numero_visita]">
                                                    <option value="1" <?= (int)$vis['numero_visita'] === 1 ? 'selected' : '' ?>>Visita 1</option>
                                                    <option value="2" <?= (int)$vis['numero_visita'] === 2 ? 'selected' : '' ?>>Visita 2</option>
                                                    <option value="3" <?= (int)$vis['numero_visita'] === 3 ? 'selected' : '' ?>>Visita 3</option>
                                                    <option value="4" <?= (int)$vis['numero_visita'] === 4 ? 'selected' : '' ?>>Visita 4</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label>Fecha de la Visita</label>
                                                <input type="date" name="visitas[<?= $i ?>][fecha_visita]" value="<?= e($vis['fecha_visita'] ?? '') ?>">
                                            </div>
                                            <div>
                                                <label>Fecha de Nacimiento</label>
                                                <input type="date" name="visitas[<?= $i ?>][fecha_nacimiento]" value="<?= e($vis['fecha_nacimiento'] ?? '') ?>" data-field="fecha_nacimiento">
                                            </div>
                                            <div>
                                                <label>Teléfono</label>
                                                <input type="tel" name="visitas[<?= $i ?>][telefono]" value="<?= e($vis['telefono'] ?? '') ?>" data-field="telefono" placeholder="Teléfono">
                                            </div>
                                            <div class="subform-col-2">
                                                <label>Dirección</label>
                                                <input type="text" name="visitas[<?= $i ?>][direccion]" value="<?= e($vis['direccion'] ?? '') ?>" data-field="direccion" placeholder="Dirección">
                                            </div>
                                            <div class="subform-col-full subform-checks">
                                                <label>
                                                    <input type="checkbox" name="visitas[<?= $i ?>][bautismo_agua]" value="1" <?= !empty($vis['bautismo_agua']) ? 'checked' : '' ?> data-field="bautismo_agua">
                                                    Bautismo en Agua
                                                </label>
                                                <label>
                                                    <input type="checkbox" name="visitas[<?= $i ?>][bautismo_espiritu]" value="1" <?= !empty($vis['bautismo_espiritu']) ? 'checked' : '' ?> data-field="bautismo_espiritu">
                                                    Bautismo en Espíritu Santo (Esp.)
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="subform-actions">
                                <button type="button" class="btn btn-soft btn-sm" data-add-row="visitas">
                                    <i class="ri-home-heart-line"></i> + Añadir visita programada
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="observaciones-section" style="margin-top: 32px; margin-bottom: 24px;">
                        <label for="observaciones" style="font-size: 13.5px; font-weight: 700; color: var(--night); margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                            <i class="ri-chat-1-line" style="color: var(--gold); font-size: 18px;"></i> Observaciones adicionales
                        </label>
                        <textarea
                            id="observaciones"
                            name="observaciones"
                            rows="3"
                            placeholder="Notas o peticiones relevantes de la reunión celular..."
                            style="padding: 12px 14px; font-size: 13.5px; border-radius: 10px; width: 100%; box-sizing: border-box; min-height: 90px;"
                        ><?= e($reporteEditado['observaciones'] ?? '') ?></textarea>
                    </div>

                    <div class="form-actions" style="margin-top: 24px;">
                        <button class="btn btn-gold btn-grow" type="submit" style="min-height: 48px; font-size: 15px; font-weight: 700; padding: 12px 24px; border-radius: 10px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <?= $reporteEditado ? 'Guardar correcciones' : 'Enviar reporte ahora' ?> <i class="ri-send-plane-fill"></i>
                        </button>
                    </div>
                </form>
            </section>

        <!-- PLANTILLAS HTML PARA FILAS DINÁMICAS -->
        <template id="tpl-conversiones">
            <div class="dynamic-card-item">
                <div class="dynamic-card-head">
                    <strong>
                        <span class="row-number-badge">#</span>
                        <span>Datos de Conversión / Reconcilio</span>
                    </strong>
                    <button type="button" class="btn-remove-row"><i class="ri-delete-bin-line"></i> Eliminar</button>
                </div>
                <div class="subform-grid">
                    <div class="subform-col-2">
                        <label>Nombres y Apellidos *</label>
                        <input
                            type="text"
                            name="conversiones[__INDEX__][nombre_completo]"
                            placeholder="Nombre completo"
                            list="lista-miembros-celula"
                            data-member-autocomplete
                            required
                        >
                    </div>
                    <div>
                        <label>Tipo de Decisión</label>
                        <select name="conversiones[__INDEX__][tipo_decision]">
                            <option value="conversion">Conversión</option>
                            <option value="reconcilio">Reconcilio</option>
                        </select>
                    </div>
                    <div>
                        <label>Estado Civil</label>
                        <select name="conversiones[__INDEX__][estado_civil]" data-field="estado_civil">
                            <option value="soltero">Soltero(a)</option>
                            <option value="casado">Casado(a)</option>
                        </select>
                    </div>
                    <div>
                        <label>Fecha de Nacimiento</label>
                        <input type="date" name="conversiones[__INDEX__][fecha_nacimiento]" data-field="fecha_nacimiento">
                    </div>
                    <div>
                        <label>Fecha de Decisión</label>
                        <input type="date" name="conversiones[__INDEX__][fecha]" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div>
                        <label>Teléfono</label>
                        <input type="tel" name="conversiones[__INDEX__][telefono]" data-field="telefono" placeholder="Teléfono">
                    </div>
                    <div class="subform-col-2">
                        <label>Dirección</label>
                        <input type="text" name="conversiones[__INDEX__][direccion]" data-field="direccion" placeholder="Dirección de habitación">
                    </div>
                    <div class="subform-col-full subform-checks">
                        <label>
                            <input type="checkbox" name="conversiones[__INDEX__][bautismo_agua]" value="1" data-field="bautismo_agua">
                            Bautismo en Agua
                        </label>
                        <label>
                            <input type="checkbox" name="conversiones[__INDEX__][bautismo_espiritu]" value="1" data-field="bautismo_espiritu">
                            Bautismo en Espíritu Santo
                        </label>
                    </div>
                </div>
            </div>
        </template>

        <template id="tpl-bautismos">
            <div class="dynamic-card-item">
                <div class="dynamic-card-head">
                    <strong>
                        <span class="row-number-badge">#</span>
                        <span>Datos del Candidato a Bautismo</span>
                    </strong>
                    <button type="button" class="btn-remove-row"><i class="ri-delete-bin-line"></i> Eliminar</button>
                </div>
                <div class="subform-grid">
                    <div class="subform-col-2">
                        <label>Nombres y Apellidos *</label>
                        <input
                            type="text"
                            name="bautismos[__INDEX__][nombre_completo]"
                            placeholder="Nombre completo"
                            list="lista-miembros-celula"
                            data-member-autocomplete
                            required
                        >
                    </div>
                    <div>
                        <label>Célula (Tipo)</label>
                        <select name="bautismos[__INDEX__][tipo_celula]">
                            <option value="adultos" <?= $tipoSlug === 'adultos' ? 'selected' : '' ?>>Adultos</option>
                            <option value="infantil" <?= $tipoSlug === 'ninos' ? 'selected' : '' ?>>Infantil</option>
                            <option value="juvenil" <?= $tipoSlug === 'jovenes' ? 'selected' : '' ?>>Juvenil</option>
                        </select>
                    </div>
                    <div>
                        <label>Estado Civil</label>
                        <select name="bautismos[__INDEX__][estado_civil]" data-field="estado_civil">
                            <option value="soltero">Soltero(a)</option>
                            <option value="casado">Casado(a)</option>
                        </select>
                    </div>
                    <div>
                        <label>Fecha de Nacimiento</label>
                        <input type="date" name="bautismos[__INDEX__][fecha_nacimiento]" data-field="fecha_nacimiento">
                    </div>
                    <div>
                        <label>Fecha de Postulación</label>
                        <input type="date" name="bautismos[__INDEX__][fecha_bautismo]" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div>
                        <label>Teléfono</label>
                        <input type="tel" name="bautismos[__INDEX__][telefono]" data-field="telefono" placeholder="Teléfono">
                    </div>
                    <div class="subform-col-2">
                        <label>Dirección</label>
                        <input type="text" name="bautismos[__INDEX__][direccion]" data-field="direccion" placeholder="Dirección">
                    </div>
                </div>
            </div>
        </template>

        <template id="tpl-visitas">
            <div class="dynamic-card-item">
                <div class="dynamic-card-head">
                    <strong>
                        <span class="row-number-badge">#</span>
                        <span>Datos y Control de Visita Programada</span>
                    </strong>
                    <button type="button" class="btn-remove-row"><i class="ri-delete-bin-line"></i> Eliminar</button>
                </div>
                <div class="subform-grid">
                    <div class="subform-col-2">
                        <label>Nombres y Apellidos *</label>
                        <input
                            type="text"
                            name="visitas[__INDEX__][nombre_completo]"
                            placeholder="Persona visitada"
                            list="lista-miembros-celula"
                            data-member-autocomplete
                            required
                        >
                    </div>
                    <div>
                        <label>Célula (Tipo)</label>
                        <select name="visitas[__INDEX__][tipo_celula]">
                            <option value="adultos" <?= $tipoSlug === 'adultos' ? 'selected' : '' ?>>Adulto</option>
                            <option value="infantil" <?= $tipoSlug === 'ninos' ? 'selected' : '' ?>>Infantil</option>
                            <option value="juvenil" <?= $tipoSlug === 'jovenes' ? 'selected' : '' ?>>Juvenil</option>
                        </select>
                    </div>
                    <div>
                        <label>Estado Civil</label>
                        <select name="visitas[__INDEX__][estado_civil]" data-field="estado_civil">
                            <option value="soltero">Soltero(a)</option>
                            <option value="casado">Casado(a)</option>
                        </select>
                    </div>
                    <div>
                        <label>No. de Visita</label>
                        <select name="visitas[__INDEX__][numero_visita]">
                            <option value="1">Visita 1</option>
                            <option value="2">Visita 2</option>
                            <option value="3">Visita 3</option>
                            <option value="4">Visita 4</option>
                        </select>
                    </div>
                    <div>
                        <label>Fecha de la Visita</label>
                        <input type="date" name="visitas[__INDEX__][fecha_visita]" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div>
                        <label>Fecha de Nacimiento</label>
                        <input type="date" name="visitas[__INDEX__][fecha_nacimiento]" data-field="fecha_nacimiento">
                    </div>
                    <div>
                        <label>Teléfono</label>
                        <input type="tel" name="visitas[__INDEX__][telefono]" data-field="telefono" placeholder="Teléfono">
                    </div>
                    <div class="subform-col-2">
                        <label>Dirección</label>
                        <input type="text" name="visitas[__INDEX__][direccion]" data-field="direccion" placeholder="Dirección">
                    </div>
                    <div class="subform-col-full subform-checks">
                        <label>
                            <input type="checkbox" name="visitas[__INDEX__][bautismo_agua]" value="1" data-field="bautismo_agua">
                            Bautismo en Agua
                        </label>
                        <label>
                            <input type="checkbox" name="visitas[__INDEX__][bautismo_espiritu]" value="1" data-field="bautismo_espiritu">
                            Bautismo en Espíritu Santo (Esp.)
                        </label>
                    </div>
                </div>
            </div>
        </template>
    <?php endif; ?>

<?php else: ?>
    <!-- VISTA HISTORIAL DE REPORTES -->
    <section class="card card-flush">
        <div class="card-head">
            <div>
                <p class="eyebrow">Registro de reuniones</p>
                <h3>Historial semanal de reportes</h3>
            </div>
            <span class="badge ok"><?= count($reportes) ?> reportes</span>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Semana</th>
                        <th>Adultos</th>
                        <th>Jóvenes</th>
                        <th>Niños</th>
                        <th style="background: #eef2ff; color: #1e40af;">Total Célula</th>
                        <th style="background: #eff6ff; color: #1d4ed8;">Total Global</th>
                        <th>Iglesia</th>
                        <th>Misiones</th>
                        <th>Transporte</th>
                        <th>Total Ofrenda</th>
                        <th>Resultados</th>
                        <th>Enviado</th>
                        <th>Seguimiento</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportes as $reporte): ?>
                        <?php
                        $totAdultos = (int)$reporte['adultos_amigos'] + (int)$reporte['adultos_hermanos'];
                        $totJovenes = (int)$reporte['jovenes_amigos'] + (int)$reporte['jovenes_hermanos'];
                        $totNinos = (int)$reporte['ninos'];
                        $totalSegunTipo = ($tipoSlug === 'ninos') ? $totNinos : (($tipoSlug === 'jovenes') ? $totJovenes : $totAdultos);
                        $totalGlobal = (int)$reporte['total_asistencia'];
                        ?>
                        <tr>
                            <td data-label="Semana">
                                <strong><?= date('d/m/Y', strtotime($reporte['fecha_reporte'])) ?></strong>
                            </td>
                            <td data-label="Adultos"><?= $totAdultos ?></td>
                            <td data-label="Jóvenes"><?= $totJovenes ?></td>
                            <td data-label="Niños"><?= $totNinos ?></td>
                            <td data-label="Total Célula" style="background: #f8fafc; text-align: center;">
                                <span class="badge ok" style="font-size: 13px; font-weight: 800;">
                                    <?= $totalSegunTipo ?>
                                </span>
                                <small class="muted" style="display: block; font-size: 9.5px;">
                                    <?= $tipoSlug === 'ninos' ? 'Solo niños' : ($tipoSlug === 'jovenes' ? 'Solo jóvenes' : 'Solo adultos') ?>
                                </small>
                            </td>
                            <td data-label="Total Global" style="background: #eff6ff; text-align: center;">
                                <span class="badge" style="font-size: 13px; font-weight: 800; background: #2563eb; color: #fff;">
                                    <?= $totalGlobal ?>
                                </span>
                                <small class="muted" style="display: block; font-size: 9.5px;">
                                    Todas personas
                                </small>
                            </td>
                            <td data-label="Iglesia">$<?= number_format($reporte['ofrenda_iglesia'], 2) ?></td>
                            <td data-label="Misiones">$<?= number_format($reporte['ofrenda_misiones'], 2) ?></td>
                            <td data-label="Transporte">$<?= number_format($reporte['ofrenda_bus'], 2) ?></td>
                            <td data-label="Total Ofrenda">
                                <strong>$<?= number_format($reporte['ofrenda_total'], 2) ?></strong>
                            </td>
                            <td data-label="Resultados">
                                <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                    <?php if ($reporte['conversion'] > 0): ?>
                                        <span class="badge ok" title="Conversiones">
                                            <i class="ri-user-add-line"></i> <?= $reporte['conversion'] ?> conv.
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($reporte['bautismo_agua']): ?>
                                        <span class="badge ok" title="Bautismo en Agua">
                                            <i class="ri-drop-line"></i> Cand. Bautismo
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($reporte['visita_programada']): ?>
                                        <span class="badge ok" title="Visita programada">
                                            <i class="ri-home-heart-line"></i> Visita
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!$reporte['conversion'] && !$reporte['bautismo_agua'] && !$reporte['visita_programada']): ?>
                                        <span class="muted">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td data-label="Enviado" class="receipt"><?= fechaBonita($reporte['enviado_en']) ?></td>
                            <td data-label="Seguimiento">
                                <div style="display: flex; flex-direction: column; gap: 3px;">
                                    <span class="badge <?= $reporte['visto_supervisor_en'] ? 'ok' : 'wait' ?>" style="font-size: 9.5px;">
                                        Sup: <?= $reporte['visto_supervisor_en'] ? 'Visto' : 'Pendiente' ?>
                                    </span>
                                    <span class="badge <?= $reporte['visto_admin_en'] ? 'ok' : 'wait' ?>" style="font-size: 9.5px;">
                                        Admin: <?= $reporte['visto_admin_en'] ? 'Visto' : 'Pendiente' ?>
                                    </span>
                                </div>
                            </td>
                            <td data-label="Acción">
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
$scripts = array_merge(
    $vista === 'resumen' ? ['https://cdn.jsdelivr.net/npm/chart.js@4.4.7'] : [],
    $vista === 'resumen' && $tieneUbicacion ? ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'] : [],
    ['js/lider.js']
);

require __DIR__ . '/../components/foot.php';
?>
