<?php

require_once __DIR__ . "/_bootstrap.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    validarCsrf();
    $accion = $_POST["accion"] ?? "";

    try {
        if ($accion === "guardar") {
            $id = (int) ($_POST["id"] ?? 0);
            $datos = [
                trim($_POST["nombre"] ?? ""),
                trim($_POST["zona"] ?? ""),
                trim($_POST["distrito"] ?? ""),
                !empty($_POST["supervisor_id"])
                    ? (int) $_POST["supervisor_id"]
                    : null,
            ];

            if (!empty($_POST["supervisor_id"])) {
                $consultaSupervisor = $conexion->prepare(
                    "SELECT id FROM usuarios WHERE id = ? AND rol = 'supervisor' AND activo = 1",
                );
                $consultaSupervisor->execute([(int) $_POST["supervisor_id"]]);
                if (!$consultaSupervisor->fetchColumn()) {
                    throw new InvalidArgumentException("Solo se puede asignar un supervisor activo al sector.");
                }
            }

            if ($id > 0) {
                $consultaAnterior = $conexion->prepare("SELECT supervisor_id FROM sectores WHERE id = ?");
                $consultaAnterior->execute([$id]);
                $supervisorAnteriorId = (int) ($consultaAnterior->fetchColumn() ?: 0);
                $datos[] = $id;
                $consulta = $conexion->prepare(
                    'UPDATE sectores
                     SET nombre = ?, zona = ?, distrito = ?, supervisor_id = ?
                     WHERE id = ?',
                );
            } else {
                $supervisorAnteriorId = 0;
                $consulta = $conexion->prepare(
                    'INSERT INTO sectores (nombre, zona, distrito, supervisor_id)
                     VALUES (?, ?, ?, ?)',
                );
            }

            $conexion->beginTransaction();
            $consulta->execute($datos);

            $sectorGuardadoId = $id > 0 ? $id : (int) $conexion->lastInsertId();
            $supervisorId = !empty($_POST["supervisor_id"]) ? (int) $_POST["supervisor_id"] : null;
            if ($supervisorAnteriorId && $supervisorAnteriorId !== $supervisorId) {
                $consulta = $conexion->prepare("UPDATE usuarios SET sector_id = NULL WHERE id = ? AND rol = 'supervisor'");
                $consulta->execute([$supervisorAnteriorId]);
            }
            if ($supervisorId) {
                $consulta = $conexion->prepare("UPDATE sectores SET supervisor_id = NULL WHERE supervisor_id = ? AND id <> ?");
                $consulta->execute([$supervisorId, $sectorGuardadoId]);
                $consulta = $conexion->prepare("UPDATE usuarios SET sector_id = NULL WHERE sector_id = ? AND id <> ? AND rol = 'supervisor'");
                $consulta->execute([$sectorGuardadoId, $supervisorId]);
                $consulta = $conexion->prepare("UPDATE usuarios SET sector_id = ?, celula_id = NULL WHERE id = ?");
                $consulta->execute([$sectorGuardadoId, $supervisorId]);
            }
            $conexion->commit();
            flash("success", "Sector guardado correctamente.");
        }

        if ($accion === "cambiar_estado") {
            $consulta = $conexion->prepare(
                "UPDATE sectores SET activo = 1 - activo WHERE id = ?",
            );
            $consulta->execute([(int) $_POST["id"]]);
            flash("success", "Estado del sector actualizado.");
        }
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        error_log($error->getMessage());
        flash("error", "No fue posible guardar el sector. Verifica los datos.");
    }

    redirigirAdmin("sectores.php");
}

$sectorEditado = null;

if (isset($_GET["editar"])) {
    $consulta = $conexion->prepare("SELECT * FROM sectores WHERE id = ?");
    $consulta->execute([(int) $_GET["editar"]]);
    $sectorEditado = $consulta->fetch();
}

$supervisores = $conexion
    ->query(
        "SELECT id, nombre_completo
     FROM usuarios
     WHERE rol = 'supervisor' AND activo = 1
     ORDER BY nombre_completo",
    )
    ->fetchAll();

$sectores = $conexion
    ->query(
        'SELECT
        s.*,
        u.nombre_completo AS supervisor,
        (
            SELECT COUNT(*)
            FROM celulas c
            WHERE c.sector_id = s.id AND c.activo = 1
        ) AS total_celulas
     FROM sectores s
     LEFT JOIN usuarios u ON u.id = s.supervisor_id
     ORDER BY s.nombre',
    )
    ->fetchAll();

abrirPaginaAdmin(
    "sectores",
    "Sectores"
);
?>

<div class="section-split">
    <section class="card card-flush">
        <h3><?= $sectorEditado ? "Editar sector" : "Nuevo sector" ?></h3>

        <form method="post">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" value="<?= e(
                $sectorEditado["id"] ?? "",
            ) ?>">

            <label for="nombre">Nombre</label>
            <input
                id="nombre"
                name="nombre"
                required
                value="<?= e($sectorEditado["nombre"] ?? "") ?>"
            >

            <div class="form-grid form-spaced">
                <div>
                    <label for="zona">Zona</label>
                    <input id="zona" name="zona" value="<?= e(
                        $sectorEditado["zona"] ?? "",
                    ) ?>">
                </div>
                <div>
                    <label for="distrito">Distrito</label>
                    <input
                        id="distrito"
                        name="distrito"
                        value="<?= e($sectorEditado["distrito"] ?? "") ?>"
                    >
                </div>
                <div class="full">
                    <label for="supervisor_id">Supervisor</label>
                    <select id="supervisor_id" name="supervisor_id">
                        <option value="">Sin asignar</option>
                        <?php foreach ($supervisores as $supervisor): ?>
                            <option
                                value="<?= $supervisor["id"] ?>"
                                <?= ($sectorEditado["supervisor_id"] ?? "") ==
                                $supervisor["id"]
                                    ? "selected"
                                    : "" ?>
                            >
                                <?= e($supervisor["nombre_completo"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button class="btn btn-gold" type="submit">Guardar sector</button>
                <?php if ($sectorEditado): ?>
                    <a class="btn btn-soft" href="sectores.php">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="card card-flush">
        <div class="card-head">
            <h3>Sectores registrados</h3>
            <span class="badge ok"><?= count($sectores) ?> en total</span>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Sector</th>
                        <th>Supervisor</th>
                        <th>Zona / distrito</th>
                        <th>Células</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sectores as $sector): ?>
                        <tr>
                            <td><strong><?= e(
                                $sector["nombre"],
                            ) ?></strong></td>
                            <td><?= e(
                                $sector["supervisor"] ?? "Sin asignar",
                            ) ?></td>
                            <td><?= e($sector["zona"]) ?> / <?= e(
     $sector["distrito"],
 ) ?></td>
                            <td><?= $sector["total_celulas"] ?></td>
                            <td>
                                <span class="badge <?= $sector["activo"]
                                    ? "ok"
                                    : "off" ?>">
                                    <?= $sector["activo"]
                                        ? "Activo"
                                        : "Inactivo" ?>
                                </span>
                            </td>
                            <td class="actions-cell">
                                <a
                                    class="btn btn-soft btn-sm"
                                    href="sectores.php?editar=<?= $sector[
                                        "id"
                                    ] ?>"
                                >
                                    Editar
                                </a>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="accion" value="cambiar_estado">
                                    <input type="hidden" name="id" value="<?= $sector[
                                        "id"
                                    ] ?>">
                                    <button
                                        class="btn btn-sm <?= $sector["activo"]
                                            ? "btn-danger"
                                            : "btn-soft" ?>"
                                        type="submit"
                                    >
                                        <?= $sector["activo"]
                                            ? "Desactivar"
                                            : "Activar" ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php cerrarPaginaAdmin(); ?>
