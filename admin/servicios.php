<?php

require_once __DIR__ . "/_bootstrap.php";

/** @return array{martes: int, jueves: int, domingo: int} */
function diasServicios(): array
{
    return ["martes" => 2, "jueves" => 4, "domingo" => 7];
}

function fechaServicioValida(string $fecha, string $tipo): bool
{
    $fechaObjeto = DateTimeImmutable::createFromFormat("!Y-m-d", $fecha);
    $errores = DateTimeImmutable::getLastErrors();

    return $fechaObjeto !== false
        && ($errores === false || (!$errores["warning_count"] && !$errores["error_count"]))
        && isset(diasServicios()[$tipo])
        && (int) $fechaObjeto->format("N") === diasServicios()[$tipo];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    validarCsrf();

    try {
        $fecha = trim($_POST["fecha"] ?? "");
        $tipo = $_POST["tipo_servicio"] ?? "";
        if (!fechaServicioValida($fecha, $tipo)) {
            throw new InvalidArgumentException("La fecha debe corresponder al día del servicio seleccionado.");
        }

        $consulta = $conexion->prepare(
            "INSERT INTO servicios_iglesia
                (fecha, tipo_servicio, asistencia_adultos, asistencia_ninos, ofrenda, diezmos, observaciones, registrado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                tipo_servicio = VALUES(tipo_servicio),
                asistencia_adultos = VALUES(asistencia_adultos),
                asistencia_ninos = VALUES(asistencia_ninos),
                ofrenda = VALUES(ofrenda),
                diezmos = VALUES(diezmos),
                observaciones = VALUES(observaciones),
                registrado_por = VALUES(registrado_por)",
        );
        $consulta->execute([
            $fecha,
            $tipo,
            entero("asistencia_adultos"),
            entero("asistencia_ninos"),
            dinero("ofrenda"),
            dinero("diezmos"),
            trim($_POST["observaciones"] ?? "") ?: null,
            (int) $_SESSION["usuario_id"],
        ]);
        flash("success", "Servicio guardado correctamente.");
    } catch (Throwable $error) {
        error_log($error->getMessage());
        flash("error", $error instanceof InvalidArgumentException ? $error->getMessage() : "No fue posible guardar el servicio. Verifica que se haya ejecutado la migración.");
    }

    redirigirAdmin("servicios.php");
}

$servicioEditado = null;
if (isset($_GET["editar"])) {
    $consulta = $conexion->prepare("SELECT * FROM servicios_iglesia WHERE id = ?");
    $consulta->execute([(int) $_GET["editar"]]);
    $servicioEditado = $consulta->fetch() ?: null;
}

$tipoPredeterminado = array_search((int) date("N"), diasServicios(), true) ?: "";

$mes = $_GET["mes"] ?? date("Y-m");
if (!preg_match('/^\\d{4}-\\d{2}$/', $mes)) {
    $mes = date("Y-m");
}
$inicioMes = $mes . "-01";
$finMes = (new DateTimeImmutable($inicioMes))->modify("last day of this month")->format("Y-m-d");

$consulta = $conexion->prepare(
    "SELECT s.*, u.nombre_completo AS registrado_por_nombre
     FROM servicios_iglesia s
     LEFT JOIN usuarios u ON u.id = s.registrado_por
     WHERE s.fecha BETWEEN ? AND ?
     ORDER BY s.fecha DESC",
);
$consulta->execute([$inicioMes, $finMes]);
$servicios = $consulta->fetchAll();

$totales = ["asistencia" => 0, "ofrenda" => 0, "diezmos" => 0];
foreach ($servicios as $servicio) {
    $totales["asistencia"] += (int) $servicio["asistencia_total"];
    $totales["ofrenda"] += (float) $servicio["ofrenda"];
    $totales["diezmos"] += (float) $servicio["diezmos"];
}

abrirPaginaAdmin("servicios", "Servicios y finanzas");
?>

<div class="grid-3">
    <div class="stat"><div class="label">Asistencia del mes</div><div class="value"><?= number_format($totales["asistencia"]) ?></div></div>
    <div class="stat"><div class="label">Ofrendas del mes</div><div class="value value-small">$<?= number_format($totales["ofrenda"], 2) ?></div></div>
    <div class="stat"><div class="label">Diezmos del mes</div><div class="value value-small">$<?= number_format($totales["diezmos"], 2) ?></div></div>
</div>

<div class="section-split">
    <section class="card card-flush">
        <h3><?= $servicioEditado ? "Editar servicio" : "Registrar servicio" ?></h3>
        <p class="muted">Solo se aceptan servicios de martes, jueves y domingo. Si guardas una fecha existente, se actualizará su registro.</p>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <div class="form-grid form-spaced">
                <div><label for="fecha">Fecha</label><input id="fecha" type="date" name="fecha" required value="<?= e($servicioEditado["fecha"] ?? date("Y-m-d")) ?>"></div>
                <div><label for="tipo_servicio">Servicio</label><select id="tipo_servicio" name="tipo_servicio" required><option value="">Selecciona el servicio</option><?php foreach (array_keys(diasServicios()) as $tipo): ?><option value="<?= $tipo ?>" <?= ($servicioEditado["tipo_servicio"] ?? $tipoPredeterminado) === $tipo ? "selected" : "" ?>><?= e(ucfirst($tipo)) ?></option><?php endforeach; ?></select></div>
                <div><label for="asistencia_adultos">Asistencia adultos</label><input id="asistencia_adultos" type="number" min="0" name="asistencia_adultos" required value="<?= e($servicioEditado["asistencia_adultos"] ?? 0) ?>"></div>
                <div><label for="asistencia_ninos">Asistencia niños</label><input id="asistencia_ninos" type="number" min="0" name="asistencia_ninos" required value="<?= e($servicioEditado["asistencia_ninos"] ?? 0) ?>"></div>
                <div><label for="ofrenda">Ofrenda ($)</label><input id="ofrenda" type="number" min="0" step="0.01" name="ofrenda" required value="<?= e($servicioEditado["ofrenda"] ?? "0.00") ?>"></div>
                <div><label for="diezmos">Diezmos ($)</label><input id="diezmos" type="number" min="0" step="0.01" name="diezmos" required value="<?= e($servicioEditado["diezmos"] ?? "0.00") ?>"></div>
                <div class="full"><label for="observaciones">Observaciones</label><textarea id="observaciones" name="observaciones" rows="3"><?= e($servicioEditado["observaciones"] ?? "") ?></textarea></div>
            </div>
            <div class="form-actions"><button class="btn btn-gold" type="submit">Guardar servicio</button><?php if ($servicioEditado): ?><a class="btn btn-soft" href="servicios.php?mes=<?= e($mes) ?>">Cancelar</a><?php endif; ?></div>
        </form>
    </section>

    <section class="card card-flush">
        <div class="card-head"><div><h3>Historial de servicios</h3><p class="muted">Registros del mes seleccionado.</p></div><form method="get"><label class="sr-only" for="mes">Mes</label><input id="mes" type="month" name="mes" value="<?= e($mes) ?>" onchange="this.form.submit()"></form></div>
        <div class="table-wrap"><table><thead><tr><th>Fecha</th><th>Servicio</th><th>Asistencia</th><th>Ofrenda</th><th>Diezmos</th><th></th></tr></thead><tbody>
        <?php foreach ($servicios as $servicio): ?><tr><td data-label="Fecha"><strong><?= date("d/m/Y", strtotime($servicio["fecha"])) ?></strong></td><td data-label="Servicio"><?= e(ucfirst($servicio["tipo_servicio"])) ?></td><td data-label="Asistencia"><?= $servicio["asistencia_total"] ?> <span class="muted">(<?= $servicio["asistencia_adultos"] ?> ad. / <?= $servicio["asistencia_ninos"] ?> ni.)</span></td><td data-label="Ofrenda">$<?= number_format($servicio["ofrenda"], 2) ?></td><td data-label="Diezmos">$<?= number_format($servicio["diezmos"], 2) ?></td><td data-label="Acción"><a class="btn btn-soft btn-sm" href="servicios.php?mes=<?= e($mes) ?>&editar=<?= $servicio["id"] ?>">Editar</a></td></tr><?php endforeach; ?>
        <?php if (!$servicios): ?><tr><td colspan="6" class="empty">No hay servicios registrados en este mes.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
</div>

<?php cerrarPaginaAdmin(); ?>
