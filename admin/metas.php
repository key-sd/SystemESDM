<?php
require_once __DIR__ . "/_bootstrap.php";

const TIPOS_META = [
    "celulas" => "Células", "asistencia_adultos" => "Asistencia adulta", "asistencia_ninos" => "Asistencia de niños",
    "conversiones" => "Conversiones", "visitas" => "Visitas programadas", "bautismos" => "Bautismos en agua",
];

function dividirMeta(int $total, int $cantidad, int $posicion): int { return intdiv($total, $cantidad) + ($posicion < $total % $cantidad ? 1 : 0); }
function esCrecimiento(string $tipo): bool { return in_array($tipo, ["celulas", "asistencia_adultos", "asistencia_ninos"], true); }

function valorMeta(PDO $conexion, string $tipo, int $sectorId, string $desde, ?string $hasta = null): float
{
    if ($tipo === "celulas") {
        $sql = "SELECT COUNT(*) FROM celulas WHERE sector_id = ? AND activo = 1" . ($hasta ? "" : " AND DATE(creado_en) < ?");
        $q = $conexion->prepare($sql); $q->execute($hasta ? [$sectorId] : [$sectorId, $desde]); return (float) $q->fetchColumn();
    }
    if (in_array($tipo, ["asistencia_adultos", "asistencia_ninos"], true)) {
        $campo = $tipo === "asistencia_adultos" ? "r.adultos_amigos+r.adultos_hermanos+r.jovenes_amigos+r.jovenes_hermanos" : "r.ninos";
        $fecha = $hasta ? "r.fecha_reporte <= ?" : "r.fecha_reporte < ?";
        $q = $conexion->prepare("SELECT COALESCE(SUM($campo),0) FROM reportes_lider r INNER JOIN celulas c ON c.id=r.celula_id WHERE c.sector_id=? AND $fecha AND r.fecha_reporte=(SELECT MAX(r2.fecha_reporte) FROM reportes_lider r2 INNER JOIN celulas c2 ON c2.id=r2.celula_id WHERE c2.sector_id=? AND " . ($hasta ? "r2.fecha_reporte <= ?" : "r2.fecha_reporte < ?") . ")");
        $q->execute([$sectorId, $hasta ?? $desde, $sectorId, $hasta ?? $desde]); return (float) $q->fetchColumn();
    }
    $campo = ["conversiones" => "conversion", "visitas" => "visita_programada", "bautismos" => "bautismo_agua"][$tipo];
    if (!$hasta) return 0;
    $q = $conexion->prepare("SELECT COALESCE(SUM(r.$campo),0) FROM reportes_lider r INNER JOIN celulas c ON c.id=r.celula_id WHERE c.sector_id=? AND r.fecha_reporte BETWEEN ? AND ?");
    $q->execute([$sectorId, $desde, $hasta]); return (float) $q->fetchColumn();
}

$anio = filter_input(INPUT_GET, "anio", FILTER_VALIDATE_INT) ?: (int) date("Y");
$trimestre = (int) ceil(date("n") / 3);
$inicioAnio = $anio . "-01-01";
$inicioTrimestre = sprintf("%d-%02d-01", $anio, (($trimestre - 1) * 3) + 1);
$finTrimestre = min((new DateTimeImmutable($inicioTrimestre))->modify("+3 months -1 day"), new DateTimeImmutable("today"))->format("Y-m-d");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    validarCsrf();
    try {
        $anioGuardar = (int) ($_POST["anio"] ?? 0); $tipo = $_POST["tipo"] ?? ""; $alcance = $_POST["alcance"] ?? "todos";
        if ($anioGuardar < 2020 || $anioGuardar > 2100 || !isset(TIPOS_META[$tipo]) || !in_array($alcance, ["todos", "sector"], true)) throw new InvalidArgumentException("Verifica los datos de la meta.");
        $objetivo = entero("objetivo"); if ($objetivo <= 0) throw new InvalidArgumentException("La meta debe ser mayor que cero.");
        $sectorId = $alcance === "sector" ? (int) ($_POST["sector_id"] ?? 0) : null;
        if ($sectorId) { $q = $conexion->prepare("SELECT id FROM sectores WHERE id=? AND activo=1"); $q->execute([$sectorId]); if (!$q->fetchColumn()) throw new InvalidArgumentException("Selecciona un sector activo."); }
        if ($alcance === "sector" && !$sectorId) throw new InvalidArgumentException("Selecciona el sector de la meta.");
        $sectores = $sectorId ? [["id" => $sectorId]] : $conexion->query("SELECT id FROM sectores WHERE activo=1 ORDER BY id")->fetchAll();
        if (!$sectores) throw new InvalidArgumentException("No hay sectores activos para asignar la meta.");
        $conexion->beginTransaction();
        $q = $conexion->prepare("INSERT INTO metas (anio,tipo,objetivo,sector_id,creado_por) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE objetivo=VALUES(objetivo)");
        $q->execute([$anioGuardar, $tipo, $objetivo, $sectorId, (int)$_SESSION["usuario_id"]]);
        $q = $conexion->prepare("SELECT id FROM metas WHERE anio=? AND tipo=? AND sector_id_unico=?"); $q->execute([$anioGuardar, $tipo, $sectorId ?? 0]); $metaId=(int)$q->fetchColumn();
        $bases=[]; $q=$conexion->prepare("SELECT sector_id,base_inicial FROM metas_asignaciones_sector WHERE meta_id=?"); $q->execute([$metaId]); foreach($q->fetchAll() as $fila) $bases[(int)$fila["sector_id"]]=(float)$fila["base_inicial"];
        $conexion->prepare("DELETE FROM metas_asignaciones_sector WHERE meta_id=?")->execute([$metaId]);
        $guardar=$conexion->prepare("INSERT INTO metas_asignaciones_sector (meta_id,sector_id,objetivo,base_inicial) VALUES (?,?,?,?)");
        foreach($sectores as $i=>$sector) { $id=(int)$sector["id"]; $base=$bases[$id] ?? valorMeta($conexion,$tipo,$id,$anioGuardar."-01-01"); $asignado=$sectorId ? $objetivo : dividirMeta($objetivo,count($sectores),$i); $guardar->execute([$metaId,$id,$asignado,$base]); }
        $conexion->commit(); flash("success", "Meta guardada correctamente.");
    } catch(Throwable $error) { if($conexion->inTransaction()) $conexion->rollBack(); error_log($error->getMessage()); flash("error", $error instanceof InvalidArgumentException ? $error->getMessage() : "No fue posible guardar la meta. Ejecuta el script de base de datos actualizado."); }
    redirigirAdmin("metas.php?anio=" . ($anioGuardar ?: $anio));
}

$sectores=$conexion->query("SELECT id,nombre FROM sectores WHERE activo=1 ORDER BY nombre")->fetchAll();
$editar=null;
if(isset($_GET["editar"])) { $q=$conexion->prepare("SELECT * FROM metas WHERE id=?"); $q->execute([(int)$_GET["editar"]]); $editar=$q->fetch() ?: null; }
$q=$conexion->prepare("SELECT m.*, s.nombre AS sector, COUNT(a.id) AS sectores_asignados FROM metas m LEFT JOIN sectores s ON s.id=m.sector_id LEFT JOIN metas_asignaciones_sector a ON a.meta_id=m.id WHERE m.anio=? GROUP BY m.id ORDER BY m.tipo,m.sector_id"); $q->execute([$anio]); $metas=$q->fetchAll();
$q=$conexion->prepare("SELECT a.*,m.tipo,m.sector_id AS meta_sector_id,s.nombre AS sector,m.id AS meta_id FROM metas_asignaciones_sector a INNER JOIN metas m ON m.id=a.meta_id INNER JOIN sectores s ON s.id=a.sector_id WHERE m.anio=? ORDER BY m.tipo,s.nombre"); $q->execute([$anio]); $asignaciones=$q->fetchAll();

abrirPaginaAdmin("metas", "Metas y alcances trimestrales");
?>
<section class="card card-flush"><div class="card-head"><div><p class="eyebrow">Metas configurables</p><h3>Crea metas generales o por sector</h3></div><form method="get"><label class="sr-only" for="anio">Año</label><input id="anio" name="anio" type="number" min="2020" max="2100" value="<?= $anio ?>" onchange="this.form.submit()"></form></div></section>
<section class="card"><h3><?= $editar ? "Editar meta" : "Nueva meta" ?></h3><form method="post"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="anio" value="<?= $anio ?>"><div class="form-grid form-spaced"><div><label for="tipo">Indicador</label><select id="tipo" name="tipo" required><?php foreach(TIPOS_META as $valor=>$nombre): ?><option value="<?= $valor ?>" <?= ($editar["tipo"] ?? "celulas") === $valor ? "selected" : "" ?>><?= e($nombre) ?></option><?php endforeach; ?></select></div><div><label for="objetivo">Cantidad objetivo anual</label><input id="objetivo" name="objetivo" type="number" min="1" step="1" required value="<?= e($editar["objetivo"] ?? "") ?>"></div><div><label for="alcance">Aplicar a</label><select id="alcance" name="alcance" data-meta-scope><option value="todos" <?= empty($editar["sector_id"]) ? "selected" : "" ?>>Todos los sectores</option><option value="sector" <?= !empty($editar["sector_id"]) ? "selected" : "" ?>>Un sector específico</option></select></div><div data-meta-sector class="<?= empty($editar["sector_id"]) ? "sr-only" : "" ?>"><label for="sector_id">Sector</label><select id="sector_id" name="sector_id"><option value="">Selecciona un sector</option><?php foreach($sectores as $sector): ?><option value="<?= $sector["id"] ?>" <?= ($editar["sector_id"] ?? 0) == $sector["id"] ? "selected" : "" ?>><?= e($sector["nombre"]) ?></option><?php endforeach; ?></select></div></div><div class="form-actions"><button class="btn btn-gold" type="submit">Guardar meta</button><?php if($editar): ?><a class="btn btn-soft" href="metas.php?anio=<?= $anio ?>">Cancelar</a><?php endif; ?></div></form></section>
<section class="card"><div class="card-head"><div><p class="eyebrow">T<?= $trimestre ?> <?= $anio ?></p><h3>Metas registradas</h3></div><span class="badge ok"><?= count($metas) ?> metas</span></div><div class="table-wrap"><table><thead><tr><th>Indicador</th><th>Alcance</th><th>Objetivo</th><th>Asignación</th><th></th></tr></thead><tbody><?php foreach($metas as $meta): ?><tr><td data-label="Indicador"><strong><?= e(TIPOS_META[$meta["tipo"]]) ?></strong></td><td data-label="Alcance"><?= e($meta["sector"] ?? "Todos los sectores") ?></td><td data-label="Objetivo"><?= number_format($meta["objetivo"]) ?></td><td data-label="Asignación"><?= $meta["sectores_asignados"] ?> sector(es)</td><td data-label="Acción"><a class="btn btn-soft btn-sm" href="metas.php?anio=<?= $anio ?>&editar=<?= $meta["id"] ?>">Editar</a></td></tr><?php endforeach; ?><?php if(!$metas): ?><tr><td colspan="5" class="empty">Aún no hay metas para este año.</td></tr><?php endif; ?></tbody></table></div></section>
<?php if($asignaciones): ?><section class="card"><div class="card-head"><div><p class="eyebrow">T<?= $trimestre ?> <?= $anio ?></p><h3>Alcance por sector</h3><p class="muted">El porcentaje compara el avance actual con el ritmo esperado del trimestre.</p></div></div><div class="table-wrap"><table><thead><tr><th>Indicador</th><th>Sector</th><th>Inicio</th><th>Actual</th><th>Meta</th><th>Ritmo</th></tr></thead><tbody><?php foreach($asignaciones as $asignacion): ?><?php $actual=valorMeta($conexion,$asignacion["tipo"],(int)$asignacion["sector_id"],$inicioTrimestre,$finTrimestre); $esperado=esCrecimiento($asignacion["tipo"]) ? max(0,($asignacion["objetivo"]-$asignacion["base_inicial"])*$trimestre/4) : $asignacion["objetivo"]*$trimestre/4; $avance=esCrecimiento($asignacion["tipo"]) ? max(0,$actual-$asignacion["base_inicial"]) : $actual; $porcentaje=$esperado > 0 ? min(100,($avance/$esperado)*100) : 0; ?><tr><td data-label="Indicador"><strong><?= e(TIPOS_META[$asignacion["tipo"]]) ?></strong></td><td data-label="Sector"><?= e($asignacion["sector"]) ?></td><td data-label="Inicio"><?= number_format($asignacion["base_inicial"]) ?></td><td data-label="Actual"><?= number_format($actual) ?></td><td data-label="Meta"><?= number_format($asignacion["objetivo"]) ?></td><td data-label="Ritmo"><strong><?= number_format($porcentaje,1) ?>%</strong><div class="progress"><span style="width:<?= $porcentaje ?>%"></span></div></td></tr><?php endforeach; ?></tbody></table></div></section><?php endif; ?>
<?php cerrarPaginaAdmin(["js/admin.js"]); ?>
