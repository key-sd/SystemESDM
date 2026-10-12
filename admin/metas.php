<?php

require_once __DIR__ . "/_bootstrap.php";

if (!defined("TIPOS_META")) {
    define("TIPOS_META", [
        "celulas" => "Células",
        "asistencia_adultos" => "Asistencia adulta",
        "asistencia_ninos" => "Asistencia de niños",
        "conversiones" => "Conversiones",
        "visitas" => "Visitas programadas",
        "bautismos" => "Bautismos en agua",
    ]);
}

if (!defined("TRIMESTRES_INFO")) {
    define("TRIMESTRES_INFO", [
        1 => ["nombre" => "Primer trimestre (T1 · Ene - Mar)", "corto" => "T1", "mes_inicio" => 1, "mes_fin" => 3, "meses" => "Ene - Mar"],
        2 => ["nombre" => "Segundo trimestre (T2 · Abr - Jun)", "corto" => "T2", "mes_inicio" => 4, "mes_fin" => 6, "meses" => "Abr - Jun"],
        3 => ["nombre" => "Tercer trimestre (T3 · Jul - Sep)", "corto" => "T3", "mes_inicio" => 7, "mes_fin" => 9, "meses" => "Jul - Sep"],
        4 => ["nombre" => "Cuarto trimestre (T4 · Oct - Dic)", "corto" => "T4", "mes_inicio" => 10, "mes_fin" => 12, "meses" => "Oct - Dic"],
    ]);
}

if (!function_exists("dividirMeta")) {
    function dividirMeta(int $total, int $cantidad, int $posicion): int
    {
        return intdiv($total, $cantidad) + ($posicion < $total % $cantidad ? 1 : 0);
    }
}

if (!function_exists("esCrecimiento")) {
    function esCrecimiento(string $tipo): bool
    {
        return in_array($tipo, ["celulas", "asistencia_adultos", "asistencia_ninos"], true);
    }
}

if (!function_exists("valorMeta")) {
    function valorMeta(PDO $conexion, string $tipo, int $sectorId, string $desde, ?string $hasta = null): float
    {
        if ($tipo === "celulas") {
            if ($hasta !== null) {
                $sql = "SELECT COUNT(*) FROM celulas WHERE sector_id = ? AND activo = 1 AND DATE(creado_en) <= ?";
                $q = $conexion->prepare($sql);
                $q->execute([$sectorId, $hasta]);
            } else {
                $sql = "SELECT COUNT(*) FROM celulas WHERE sector_id = ? AND activo = 1 AND DATE(creado_en) < ?";
                $q = $conexion->prepare($sql);
                $q->execute([$sectorId, $desde]);
            }
            return (float) $q->fetchColumn();
        }

        if (in_array($tipo, ["asistencia_adultos", "asistencia_ninos"], true)) {
            $campo = $tipo === "asistencia_adultos"
                ? "r.adultos_amigos + r.adultos_hermanos + r.jovenes_amigos + r.jovenes_hermanos"
                : "r.ninos";

            $fechaFiltroSub = $hasta !== null ? "r2.fecha_reporte <= ?" : "r2.fecha_reporte < ?";
            $fechaFiltroPpal = $hasta !== null ? "r.fecha_reporte <= ?" : "r.fecha_reporte < ?";
            $fechaRef = $hasta ?? $desde;

            $sql = "SELECT COALESCE(SUM($campo), 0)
                    FROM reportes_lider r
                    INNER JOIN celulas c ON c.id = r.celula_id
                    WHERE c.sector_id = ? AND $fechaFiltroPpal
                      AND r.anio_semana = (
                          SELECT MAX(r2.anio_semana)
                          FROM reportes_lider r2
                          INNER JOIN celulas c2 ON c2.id = r2.celula_id
                          WHERE c2.sector_id = ? AND $fechaFiltroSub
                      )";
            $q = $conexion->prepare($sql);
            $q->execute([$sectorId, $fechaRef, $sectorId, $fechaRef]);
            return (float) $q->fetchColumn();
        }

        $campo = [
            "conversiones" => "conversion",
            "visitas" => "visita_programada",
            "bautismos" => "bautismo_agua",
        ][$tipo] ?? null;

        if (!$campo || !$hasta) {
            return 0;
        }

        $sql = "SELECT COALESCE(SUM(r.$campo), 0)
                FROM reportes_lider r
                INNER JOIN celulas c ON c.id = r.celula_id
                WHERE c.sector_id = ? AND r.fecha_reporte BETWEEN ? AND ?";
        $q = $conexion->prepare($sql);
        $q->execute([$sectorId, $desde, $hasta]);
        return (float) $q->fetchColumn();
    }
}

// Filtros GET principales
$anio = isset($_GET["anio"]) ? (int) $_GET["anio"] : (int) date("Y");
if ($anio < 2020 || $anio > 2100) {
    $anio = (int) date("Y");
}

$periodoParam = $_GET["periodo"] ?? "actual";
$periodosValidos = ["actual", "1", "2", "3", "4", "todos"];
if (!in_array($periodoParam, $periodosValidos, true)) {
    $periodoParam = "actual";
}

$sectorFiltro = isset($_GET["sector_id"]) ? (int) $_GET["sector_id"] : 0;
if ($sectorFiltro < 0) {
    $sectorFiltro = 0;
}

// Procesar formulario POST para guardar / actualizar meta
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    validarCsrf();
    try {
        $anioGuardar = (int) ($_POST["anio"] ?? 0);
        $tipo = $_POST["tipo"] ?? "";
        $alcance = $_POST["alcance"] ?? "todos";

        if ($anioGuardar < 2020 || $anioGuardar > 2100 || !isset(TIPOS_META[$tipo]) || !in_array($alcance, ["todos", "sector"], true)) {
            throw new InvalidArgumentException("Verifica los datos de la meta.");
        }

        $objetivo = entero("objetivo");
        if ($objetivo <= 0) {
            throw new InvalidArgumentException("La meta debe ser mayor que cero.");
        }

        $sectorId = $alcance === "sector" ? (int) ($_POST["sector_id"] ?? 0) : null;
        if ($sectorId) {
            $q = $conexion->prepare("SELECT id FROM sectores WHERE id = ? AND activo = 1");
            $q->execute([$sectorId]);
            if (!$q->fetchColumn()) {
                throw new InvalidArgumentException("Selecciona un sector activo.");
            }
        }
        if ($alcance === "sector" && !$sectorId) {
            throw new InvalidArgumentException("Selecciona el sector de la meta.");
        }

        $sectoresObjetivo = $sectorId ? [["id" => $sectorId]] : $conexion->query("SELECT id FROM sectores WHERE activo = 1 ORDER BY id")->fetchAll();
        if (!$sectoresObjetivo) {
            throw new InvalidArgumentException("No hay sectores activos para asignar la meta.");
        }

        $conexion->beginTransaction();

        $sectorIdUnico = $sectorId ? (int) $sectorId : 0;
        $q = $conexion->prepare("INSERT INTO metas (anio, tipo, objetivo, sector_id, sector_id_unico, creado_por) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE objetivo = VALUES(objetivo)");
        $q->execute([$anioGuardar, $tipo, $objetivo, $sectorId, $sectorIdUnico, (int) $_SESSION["usuario_id"]]);

        $q = $conexion->prepare("SELECT id FROM metas WHERE anio = ? AND tipo = ? AND sector_id_unico = ?");
        $q->execute([$anioGuardar, $tipo, $sectorId ?? 0]);
        $metaId = (int) $q->fetchColumn();

        $bases = [];
        $q = $conexion->prepare("SELECT sector_id, base_inicial FROM metas_asignaciones_sector WHERE meta_id = ?");
        $q->execute([$metaId]);
        foreach ($q->fetchAll() as $fila) {
            $bases[(int) $fila["sector_id"]] = (float) $fila["base_inicial"];
        }

        $conexion->prepare("DELETE FROM metas_asignaciones_sector WHERE meta_id = ?")->execute([$metaId]);

        $guardar = $conexion->prepare("INSERT INTO metas_asignaciones_sector (meta_id, sector_id, objetivo, base_inicial) VALUES (?, ?, ?, ?)");
        $cantidadSectores = count($sectoresObjetivo);
        foreach ($sectoresObjetivo as $i => $sec) {
            $idSec = (int) $sec["id"];
            $base = $bases[$idSec] ?? valorMeta($conexion, $tipo, $idSec, $anioGuardar . "-01-01");
            $asignado = $sectorId ? $objetivo : dividirMeta($objetivo, $cantidadSectores, $i);
            $guardar->execute([$metaId, $idSec, $asignado, $base]);
        }

        $conexion->commit();
        flash("success", "Meta guardada correctamente.");
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        error_log($error->getMessage());
        flash("error", $error instanceof InvalidArgumentException ? $error->getMessage() : "No fue posible guardar la meta. Verifica la conexión.");
    }

    $paramPeriodo = $_POST["periodo"] ?? "actual";
    $paramSector = (int) ($_POST["sector_id_filtro"] ?? 0);
    $urlRedir = "metas.php?anio=" . ($anioGuardar ?: $anio) . "&periodo=" . urlencode($paramPeriodo) . ($paramSector > 0 ? "&sector_id=" . $paramSector : "");
    redirigirAdmin($urlRedir);
}

// Cargar sectores activos
$sectores = $conexion->query("SELECT id, nombre FROM sectores WHERE activo = 1 ORDER BY nombre")->fetchAll();

$nombreSectorFiltro = "Todos los sectores";
if ($sectorFiltro > 0) {
    $encontrado = false;
    foreach ($sectores as $s) {
        if ((int) $s["id"] === $sectorFiltro) {
            $encontrado = true;
            $nombreSectorFiltro = $s["nombre"];
            break;
        }
    }
    if (!$encontrado) {
        $sectorFiltro = 0;
        $nombreSectorFiltro = "Todos los sectores";
    }
}

// Cálculo del periodo temporal
$trimestreActual = (int) ceil(date("n") / 3);
$anioActual = (int) date("Y");
$inicioAnio = sprintf("%d-01-01", $anio);
$finAnio = sprintf("%d-12-31", $anio);

$esTodos = ($periodoParam === "todos");
$numTrimestre = null;

if ($esTodos) {
    $etiquetaPeriodo = "Todos juntos (Año {$anio})";
    $nombreCortoPeriodo = "Año {$anio}";
    $desdePeriodo = $inicioAnio;
    $hastaPeriodo = ($anio === $anioActual) ? min($finAnio, date("Y-m-d")) : $finAnio;
    $rangoFechasTexto = "01/01/{$anio} al " . date("d/m/Y", strtotime($hastaPeriodo));
} else {
    $numTrimestre = ($periodoParam === "actual") ? $trimestreActual : (int) $periodoParam;
    $infoT = TRIMESTRES_INFO[$numTrimestre];
    $desdeTrimestre = sprintf("%d-%02d-01", $anio, $infoT["mes_inicio"]);
    $ultimoDiaMesT = date("t", strtotime(sprintf("%d-%02d-01", $anio, $infoT["mes_fin"])));
    $finTrimestre = sprintf("%d-%02d-%02d", $anio, $infoT["mes_fin"], $ultimoDiaMesT);

    $desdePeriodo = $desdeTrimestre;
    if ($anio === $anioActual && $numTrimestre === $trimestreActual) {
        $hastaPeriodo = min($finTrimestre, date("Y-m-d"));
    } else {
        $hastaPeriodo = $finTrimestre;
    }

    $etiquetaPeriodo = ($periodoParam === "actual")
        ? "Trimestre actual ({$infoT['corto']} · {$infoT['meses']} {$anio})"
        : "{$infoT['nombre']} {$anio}";
    $nombreCortoPeriodo = "{$infoT['corto']} {$anio}";
    $rangoFechasTexto = date("d/m/Y", strtotime($desdeTrimestre)) . " al " . date("d/m/Y", strtotime($hastaPeriodo));
}

// Consultar metas registradas
if ($sectorFiltro > 0) {
    $q = $conexion->prepare(
        "SELECT m.*, s.nombre AS sector, COUNT(a.id) AS sectores_asignados
         FROM metas m
         LEFT JOIN sectores s ON s.id = m.sector_id
         LEFT JOIN metas_asignaciones_sector a ON a.meta_id = m.id AND a.sector_id = ?
         WHERE m.anio = ? AND (m.sector_id = ? OR m.sector_id IS NULL)
         GROUP BY m.id
         ORDER BY m.tipo, m.sector_id"
    );
    $q->execute([$sectorFiltro, $anio, $sectorFiltro]);
    $metas = $q->fetchAll();
} else {
    $q = $conexion->prepare(
        "SELECT m.*, s.nombre AS sector, COUNT(a.id) AS sectores_asignados
         FROM metas m
         LEFT JOIN sectores s ON s.id = m.sector_id
         LEFT JOIN metas_asignaciones_sector a ON a.meta_id = m.id
         WHERE m.anio = ?
         GROUP BY m.id
         ORDER BY m.tipo, m.sector_id"
    );
    $q->execute([$anio]);
    $metas = $q->fetchAll();
}

// Consultar asignaciones por sector para la tabla de progreso
if ($sectorFiltro > 0) {
    $q = $conexion->prepare(
        "SELECT a.*, m.tipo, m.sector_id AS meta_sector_id, s.nombre AS sector, m.id AS meta_id
         FROM metas_asignaciones_sector a
         INNER JOIN metas m ON m.id = a.meta_id
         INNER JOIN sectores s ON s.id = a.sector_id
         WHERE m.anio = ? AND a.sector_id = ?
         ORDER BY m.tipo, s.nombre"
    );
    $q->execute([$anio, $sectorFiltro]);
    $asignaciones = $q->fetchAll();
} else {
    $q = $conexion->prepare(
        "SELECT a.*, m.tipo, m.sector_id AS meta_sector_id, s.nombre AS sector, m.id AS meta_id
         FROM metas_asignaciones_sector a
         INNER JOIN metas m ON m.id = a.meta_id
         INNER JOIN sectores s ON s.id = a.sector_id
         WHERE m.anio = ?
         ORDER BY m.tipo, s.nombre"
    );
    $q->execute([$anio]);
    $asignaciones = $q->fetchAll();
}

// Calcular avances y métricas para cada asignación
$datosFilas = [];
$sumaCumplimiento = 0.0;
$totalCumplidas = 0;

foreach ($asignaciones as $asig) {
    $tipo = $asig["tipo"];
    $secId = (int) $asig["sector_id"];
    $metaAnual = (float) $asig["objetivo"];
    $baseInicial = (float) $asig["base_inicial"];
    $esCrec = esCrecimiento($tipo);

    if (!$esTodos) {
        // Modo Trimestre (T1, T2, T3, T4 o actual)
        if ($esCrec) {
            $actualTrimestre = valorMeta($conexion, $tipo, $secId, $desdePeriodo, $hastaPeriodo);
            $crecimientoTotal = max(0, $metaAnual - $baseInicial);
            $crecimientoEsperado = $crecimientoTotal * ($numTrimestre / 4);
            $metaEsperadaCorte = $baseInicial + $crecimientoEsperado;
            $crecimientoReal = max(0, $actualTrimestre - $baseInicial);
            $pctRitmo = $crecimientoEsperado > 0
                ? ($crecimientoReal / $crecimientoEsperado) * 100
                : ($actualTrimestre >= $metaEsperadaCorte ? 100.0 : 0.0);
            $pctAnual = $crecimientoTotal > 0
                ? ($crecimientoReal / $crecimientoTotal) * 100
                : ($actualTrimestre >= $metaAnual ? 100.0 : 0.0);

            $datosFilas[] = [
                "asignacion" => $asig,
                "base_inicial" => $baseInicial,
                "actual_periodo" => $actualTrimestre,
                "esperado_periodo" => $metaEsperadaCorte,
                "pct_ritmo" => $pctRitmo,
                "actual_anual" => $actualTrimestre,
                "meta_anual" => $metaAnual,
                "pct_anual" => $pctAnual,
            ];
            if ($pctRitmo >= 100) {
                $totalCumplidas++;
            }
            $sumaCumplimiento += $pctRitmo;
        } else {
            // Métricas de flujo (conversiones, visitas, bautismos)
            $logradoTrimestre = valorMeta($conexion, $tipo, $secId, $desdePeriodo, $hastaPeriodo);
            $cuotaTrimestre = $metaAnual / 4;
            $pctRitmo = $cuotaTrimestre > 0 ? ($logradoTrimestre / $cuotaTrimestre) * 100 : 0.0;
            $logradoAnualCorte = valorMeta($conexion, $tipo, $secId, $inicioAnio, $hastaPeriodo);
            $pctAnual = $metaAnual > 0 ? ($logradoAnualCorte / $metaAnual) * 100 : 0.0;

            $datosFilas[] = [
                "asignacion" => $asig,
                "base_inicial" => 0.0,
                "actual_periodo" => $logradoTrimestre,
                "esperado_periodo" => $cuotaTrimestre,
                "pct_ritmo" => $pctRitmo,
                "actual_anual" => $logradoAnualCorte,
                "meta_anual" => $metaAnual,
                "pct_anual" => $pctAnual,
            ];
            if ($pctRitmo >= 100) {
                $totalCumplidas++;
            }
            $sumaCumplimiento += $pctRitmo;
        }
    } else {
        // Modo Todos juntos (Acumulado anual)
        if ($esCrec) {
            $actualAnual = valorMeta($conexion, $tipo, $secId, $desdePeriodo, $hastaPeriodo);
            $crecimientoTotal = max(0, $metaAnual - $baseInicial);
            $crecimientoReal = max(0, $actualAnual - $baseInicial);
            $pctAnual = $crecimientoTotal > 0
                ? ($crecimientoReal / $crecimientoTotal) * 100
                : ($actualAnual >= $metaAnual ? 100.0 : 0.0);

            $datosFilas[] = [
                "asignacion" => $asig,
                "base_inicial" => $baseInicial,
                "actual_anual" => $actualAnual,
                "meta_anual" => $metaAnual,
                "pct_anual" => $pctAnual,
            ];
            if ($pctAnual >= 100) {
                $totalCumplidas++;
            }
            $sumaCumplimiento += $pctAnual;
        } else {
            $logradoAnual = valorMeta($conexion, $tipo, $secId, $desdePeriodo, $hastaPeriodo);
            $pctAnual = $metaAnual > 0 ? ($logradoAnual / $metaAnual) * 100 : 0.0;

            $datosFilas[] = [
                "asignacion" => $asig,
                "base_inicial" => 0.0,
                "actual_anual" => $logradoAnual,
                "meta_anual" => $metaAnual,
                "pct_anual" => $pctAnual,
            ];
            if ($pctAnual >= 100) {
                $totalCumplidas++;
            }
            $sumaCumplimiento += $pctAnual;
        }
    }
}

$promedioCumplimiento = count($datosFilas) > 0 ? ($sumaCumplimiento / count($datosFilas)) : 0.0;

// Modo edición de meta si viene en GET
$editar = null;
if (isset($_GET["editar"])) {
    $q = $conexion->prepare("SELECT * FROM metas WHERE id = ?");
    $q->execute([(int) $_GET["editar"]]);
    $editar = $q->fetch() ?: null;
}

abrirPaginaAdmin("metas", "Metas y alcances anuales");
?>

<section class="card card-flush">
    <div class="card-head" style="flex-wrap: wrap; gap: 15px; align-items: center; justify-content: space-between;">
        <div>
            <p class="eyebrow">Metas anuales y seguimiento</p>
            <h3>Progreso de metas <?= e($anio) ?></h3>
        </div>
    </div>
    <form method="get" class="stats-filters" style="margin-top: 15px;">
        <div style="min-width: 120px;">
            <label for="filtro_anio">Año</label>
            <input id="filtro_anio" name="anio" type="number" min="2020" max="2100" value="<?= $anio ?>" onchange="this.form.submit()">
        </div>
        <div style="min-width: 230px;">
            <label for="filtro_periodo">Periodo</label>
            <select id="filtro_periodo" name="periodo" onchange="this.form.submit()">
                <option value="actual" <?= $periodoParam === "actual" ? "selected" : "" ?>>Trimestre actual (T<?= $trimestreActual ?>)</option>
                <option value="1" <?= $periodoParam === "1" ? "selected" : "" ?>>Primer trimestre (T1 · Ene - Mar)</option>
                <option value="2" <?= $periodoParam === "2" ? "selected" : "" ?>>Segundo trimestre (T2 · Abr - Jun)</option>
                <option value="3" <?= $periodoParam === "3" ? "selected" : "" ?>>Tercer trimestre (T3 · Jul - Sep)</option>
                <option value="4" <?= $periodoParam === "4" ? "selected" : "" ?>>Cuarto trimestre (T4 · Oct - Dic)</option>
                <option value="todos" <?= $periodoParam === "todos" ? "selected" : "" ?>>Todos juntos (Acumulado anual)</option>
            </select>
        </div>
        <div style="min-width: 220px;">
            <label for="filtro_sector">Sector</label>
            <select id="filtro_sector" name="sector_id" onchange="this.form.submit()">
                <option value="0" <?= $sectorFiltro === 0 ? "selected" : "" ?>>Todos los sectores</option>
                <?php foreach ($sectores as $s): ?>
                    <option value="<?= $s["id"] ?>" <?= $sectorFiltro === (int) $s["id"] ? "selected" : "" ?>><?= e($s["nombre"]) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</section>

<div class="grid-4">
    <div class="stat">
        <div class="label">Periodo evaluado</div>
        <div class="value value-small"><?= e($nombreCortoPeriodo) ?></div>
        <small class="muted"><?= e($rangoFechasTexto) ?></small>
    </div>
    <div class="stat">
        <div class="label">Sector analizado</div>
        <div class="value value-small"><?= $sectorFiltro > 0 ? e($nombreSectorFiltro) : "Todos los sectores" ?></div>
        <small class="muted"><?= count($datosFilas) ?> asignación(es) activa(s)</small>
    </div>
    <div class="stat">
        <div class="label">Cumplimiento promedio</div>
        <div class="value"><?= number_format($promedioCumplimiento, 1) ?>%</div>
        <small class="muted"><?= $totalCumplidas ?> meta(s) al 100% o más</small>
    </div>
    <div class="stat">
        <div class="label">Metas anuales registradas</div>
        <div class="value"><?= count($metas) ?></div>
        <small class="muted">Configuración <?= $anio ?></small>
    </div>
</div>

<section class="card">
    <div class="card-head">
        <div>
            <p class="eyebrow"><?= e($nombreCortoPeriodo) ?></p>
            <h3>Avance y cumplimiento por sector</h3>
        </div>
        <span class="badge ok"><?= count($datosFilas) ?> asignación(es)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Indicador</th>
                    <th>Sector</th>
                    <th>Base inicial</th>
                    <?php if (!$esTodos): ?>
                        <th>Logrado en <?= e($nombreCortoPeriodo) ?></th>
                        <th>Meta esperada</th>
                        <th>Ritmo trimestre</th>
                        <th>Acumulado anual</th>
                    <?php else: ?>
                        <th>Acumulado anual</th>
                        <th>Meta anual</th>
                        <th>Cumplimiento anual</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($datosFilas as $fila): ?>
                    <?php
                    $tipoNombre = TIPOS_META[$fila["asignacion"]["tipo"]] ?? $fila["asignacion"]["tipo"];
                    $secNombre = $fila["asignacion"]["sector"];
                    ?>
                    <tr>
                        <td data-label="Indicador"><strong><?= e($tipoNombre) ?></strong></td>
                        <td data-label="Sector"><?= e($secNombre) ?></td>
                        <td data-label="Base inicial"><?= number_format($fila["base_inicial"]) ?></td>
                        <?php if (!$esTodos): ?>
                            <td data-label="Logrado en <?= e($nombreCortoPeriodo) ?>"><strong><?= number_format($fila["actual_periodo"]) ?></strong></td>
                            <td data-label="Meta esperada"><?= number_format($fila["esperado_periodo"], 1) ?></td>
                            <td data-label="Ritmo trimestre">
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                                    <strong><?= number_format($fila["pct_ritmo"], 1) ?>%</strong>
                                    <?php if ($fila["pct_ritmo"] >= 100): ?>
                                        <span class="badge ok">100%</span>
                                    <?php endif; ?>
                                </div>
                                <div class="progress" style="margin-top: 4px;">
                                    <span style="width: <?= min(100, max(0, (float) $fila["pct_ritmo"])) ?>%"></span>
                                </div>
                            </td>
                            <td data-label="Acumulado anual">
                                <span><?= number_format($fila["actual_anual"]) ?> / <?= number_format($fila["meta_anual"]) ?></span>
                                <small class="muted">(<?= number_format($fila["pct_anual"], 1) ?>%)</small>
                            </td>
                        <?php else: ?>
                            <td data-label="Acumulado anual"><strong><?= number_format($fila["actual_anual"]) ?></strong></td>
                            <td data-label="Meta anual"><?= number_format($fila["meta_anual"]) ?></td>
                            <td data-label="Cumplimiento anual">
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                                    <strong><?= number_format($fila["pct_anual"], 1) ?>%</strong>
                                    <?php if ($fila["pct_anual"] >= 100): ?>
                                        <span class="badge ok">100%</span>
                                    <?php endif; ?>
                                </div>
                                <div class="progress" style="margin-top: 4px;">
                                    <span style="width: <?= min(100, max(0, (float) $fila["pct_anual"])) ?>%"></span>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($datosFilas)): ?>
                    <tr>
                        <td colspan="<?= $esTodos ? 6 : 7 ?>" class="empty">No hay asignaciones de metas registradas para los criterios seleccionados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <div class="card-head">
        <div>
            <p class="eyebrow">Configuración <?= $anio ?></p>
            <h3>Metas registradas</h3>
            <?php if ($sectorFiltro > 0): ?>
                <p class="muted">Mostrando metas que aplican a <strong><?= e($nombreSectorFiltro) ?></strong>.</p>
            <?php endif; ?>
        </div>
        <span class="badge ok"><?= count($metas) ?> metas</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Indicador</th>
                    <th>Alcance</th>
                    <th>Objetivo anual</th>
                    <th>Asignación</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($metas as $meta): ?>
                    <tr>
                        <td data-label="Indicador"><strong><?= e(TIPOS_META[$meta["tipo"]] ?? $meta["tipo"]) ?></strong></td>
                        <td data-label="Alcance"><?= e($meta["sector"] ?? "Todos los sectores") ?></td>
                        <td data-label="Objetivo anual"><?= number_format($meta["objetivo"]) ?></td>
                        <td data-label="Asignación"><?= $meta["sectores_asignados"] ?> sector(es)</td>
                        <td data-label="Acción">
                            <a class="btn btn-soft btn-sm" href="metas.php?anio=<?= $anio ?>&periodo=<?= urlencode($periodoParam) ?>&sector_id=<?= $sectorFiltro ?>&editar=<?= $meta["id"] ?>">Editar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($metas)): ?>
                    <tr>
                        <td colspan="5" class="empty">Aún no hay metas para este año con los filtros seleccionados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <div class="card-head">
        <div>
            <h3><?= $editar ? "Editar meta anual" : "Nueva meta anual" ?></h3>
        </div>
    </div>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="anio" value="<?= $anio ?>">
        <input type="hidden" name="periodo" value="<?= e($periodoParam) ?>">
        <input type="hidden" name="sector_id_filtro" value="<?= $sectorFiltro ?>">

        <div class="form-grid form-spaced">
            <div>
                <label for="tipo">Indicador</label>
                <select id="tipo" name="tipo" required>
                    <?php foreach (TIPOS_META as $valor => $nombre): ?>
                        <option value="<?= $valor ?>" <?= ($editar["tipo"] ?? "celulas") === $valor ? "selected" : "" ?>><?= e($nombre) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="objetivo">Cantidad objetivo anual</label>
                <input id="objetivo" name="objetivo" type="number" min="1" step="1" required value="<?= e($editar ? (int) $editar["objetivo"] : "") ?>">
            </div>
            <div>
                <label for="alcance">Aplicar a</label>
                <select id="alcance" name="alcance" data-meta-scope>
                    <option value="todos" <?= empty($editar["sector_id"]) ? "selected" : "" ?>>Todos los sectores</option>
                    <option value="sector" <?= !empty($editar["sector_id"]) ? "selected" : "" ?>>Un sector específico</option>
                </select>
            </div>
            <div data-meta-sector class="<?= empty($editar["sector_id"]) ? "sr-only" : "" ?>">
                <label for="sector_id">Sector</label>
                <select id="sector_id" name="sector_id">
                    <option value="">Selecciona un sector</option>
                    <?php foreach ($sectores as $sector): ?>
                        <option value="<?= $sector["id"] ?>" <?= ($editar["sector_id"] ?? ($sectorFiltro > 0 ? $sectorFiltro : 0)) == $sector["id"] ? "selected" : "" ?>><?= e($sector["nombre"]) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-actions">
            <button class="btn btn-gold" type="submit">Guardar meta</button>
            <?php if ($editar): ?>
                <a class="btn btn-soft" href="metas.php?anio=<?= $anio ?>&periodo=<?= urlencode($periodoParam) ?>&sector_id=<?= $sectorFiltro ?>">Cancelar</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<?php cerrarPaginaAdmin(["js/admin.js"]); ?>
