<?php

require_once __DIR__ . "/_bootstrap.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    validarCsrf();
    $accion = $_POST["accion"] ?? "";

    try {
        if ($accion === "guardar") {
            $conexion->beginTransaction();
            $id = (int) ($_POST["id"] ?? 0);
            $rol = $_POST["rol"] ?? "";
            if (!in_array($rol, ["lider", "supervisor", "admin"], true)) {
                throw new InvalidArgumentException("El rol seleccionado no es válido.");
            }

            // Al crear una cuenta no se asigna nada: esas relaciones se
            // administran desde Sectores y Células. En edición se conservan
            // como campos opcionales para corregir una asignación existente.
            $sectorId = null;
            $celulaId = null;
            if ($id > 0) {
                $sectorId = !empty($_POST["sector_id"]) ? (int) $_POST["sector_id"] : null;
                $celulaId = !empty($_POST["celula_id"]) ? (int) $_POST["celula_id"] : null;
            }

            if ($rol === "admin") {
                $sectorId = null;
                $celulaId = null;
            } elseif ($rol === "supervisor") {
                $celulaId = null;
            } elseif ($celulaId) {
                $consultaCelula = $conexion->prepare("SELECT sector_id FROM celulas WHERE id = ?");
                $consultaCelula->execute([$celulaId]);
                $sectorDeCelula = $consultaCelula->fetchColumn();
                if (!$sectorDeCelula) {
                    throw new InvalidArgumentException("La célula seleccionada no existe.");
                }
                $sectorId = (int) $sectorDeCelula;
            }

            if ($id > 0) {
                $sql = 'UPDATE usuarios
                     SET nombre_completo = ?, usuario = ?, rol = ?,
                         sector_id = ?, celula_id = ?';
                $datos = [
                    trim($_POST["nombre"] ?? ""),
                    trim($_POST["usuario"] ?? ""),
                    $rol,
                    $sectorId,
                    $celulaId,
                ];

                if (!empty($_POST["password"])) {
                    $sql .= ", password = ?";
                    $datos[] = password_hash(
                        $_POST["password"],
                        PASSWORD_DEFAULT,
                    );
                }

                $sql .= " WHERE id = ?";
                $datos[] = $id;
                $conexion->prepare($sql)->execute($datos);

                $conexion->prepare("UPDATE celulas SET lider_id = NULL WHERE lider_id = ?")->execute([$id]);
                $conexion->prepare("UPDATE sectores SET supervisor_id = NULL WHERE supervisor_id = ?")->execute([$id]);

                // Si una asignación se corrige desde la edición del usuario,
                // se refleja también en Sectores/Células para no dejar enlaces
                // distintos entre las tablas.
                if ($rol === "lider") {
                    $conexion->prepare("UPDATE celulas SET lider_id = NULL WHERE lider_id = ? AND id <> ?")
                        ->execute([$id, $celulaId ?: 0]);
                    if ($celulaId) {
                        $conexion->prepare("UPDATE usuarios SET celula_id = NULL WHERE celula_id = ? AND id <> ?")
                            ->execute([$celulaId, $id]);
                        $conexion->prepare("UPDATE celulas SET lider_id = ? WHERE id = ?")
                            ->execute([$id, $celulaId]);
                    }
                } elseif ($rol === "supervisor") {
                    $conexion->prepare("UPDATE sectores SET supervisor_id = NULL WHERE supervisor_id = ? AND id <> ?")
                        ->execute([$id, $sectorId ?: 0]);
                    if ($sectorId) {
                        $conexion->prepare("UPDATE usuarios SET sector_id = NULL WHERE sector_id = ? AND id <> ? AND rol = 'supervisor'")
                            ->execute([$sectorId, $id]);
                        $conexion->prepare("UPDATE sectores SET supervisor_id = ? WHERE id = ?")
                            ->execute([$id, $sectorId]);
                    }
                } else {
                    $conexion->prepare("UPDATE celulas SET lider_id = NULL WHERE lider_id = ?")
                        ->execute([$id]);
                    $conexion->prepare("UPDATE sectores SET supervisor_id = NULL WHERE supervisor_id = ?")
                        ->execute([$id]);
                }
            } else {
                $consulta = $conexion->prepare(
                    'INSERT INTO usuarios
                        (nombre_completo, usuario, password, rol, sector_id, celula_id)
                     VALUES (?, ?, ?, ?, ?, ?)',
                );
                $consulta->execute([
                    trim($_POST["nombre"] ?? ""),
                    trim($_POST["usuario"] ?? ""),
                    password_hash($_POST["password"], PASSWORD_DEFAULT),
                    $rol,
                    $sectorId,
                    $celulaId,
                ]);
            }

            $conexion->commit();

            flash("success", "Usuario guardado correctamente.");
        }

        if ($accion === "cambiar_estado") {
            $consulta = $conexion->prepare(
                'UPDATE usuarios
                 SET activo = 1 - activo
                 WHERE id = ? AND id <> ?',
            );
            $consulta->execute([
                (int) $_POST["id"],
                (int) $_SESSION["usuario_id"],
            ]);
            flash("success", "Estado del usuario actualizado.");
        }
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        error_log($error->getMessage());
        flash(
            "error",
            "No fue posible guardar el usuario. Verifica los datos.",
        );
    }

    redirigirAdmin("usuarios.php");
}

$usuarioEditado = null;

if (isset($_GET["editar"])) {
    $consulta = $conexion->prepare("SELECT * FROM usuarios WHERE id = ?");
    $consulta->execute([(int) $_GET["editar"]]);
    $usuarioEditado = $consulta->fetch();
}

$sectores = $conexion
    ->query("SELECT id, nombre FROM sectores WHERE activo = 1 ORDER BY nombre")
    ->fetchAll();

$celulas = $conexion
    ->query("SELECT id, nombre FROM celulas WHERE activo = 1 ORDER BY nombre")
    ->fetchAll();

$usuarios = $conexion
    ->query(
        'SELECT
        u.*,
        s.nombre AS sector,
        c.nombre AS celula
     FROM usuarios u
     LEFT JOIN sectores s ON s.id = u.sector_id
     LEFT JOIN celulas c ON c.id = u.celula_id
     ORDER BY u.nombre_completo',
    )
    ->fetchAll();

abrirPaginaAdmin(
    "usuarios",
    "Usuarios"
);
?>

<div class="section-split">
    <section class="card card-flush">
        <h3><?= $usuarioEditado ? "Editar usuario" : "Nuevo usuario" ?></h3>

        <form method="post">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" value="<?= e(
                $usuarioEditado["id"] ?? "",
            ) ?>">

            <div class="form-grid">
                <div class="full">
                    <label for="nombre">Nombre completo</label>
                    <input
                        id="nombre"
                        name="nombre"
                        required
                        value="<?= e(
                            $usuarioEditado["nombre_completo"] ?? "",
                        ) ?>"
                    >
                </div>

                <div>
                    <label for="usuario">Usuario</label>
                    <input
                        id="usuario"
                        name="usuario"
                        required
                        value="<?= e($usuarioEditado["usuario"] ?? "") ?>"
                    >
                </div>

                <div>
                    <label for="password">
                        Contraseña <?= $usuarioEditado
                            ? "(deja en blanco para no cambiarla)"
                            : "" ?>
                    </label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        <?= $usuarioEditado ? "" : "required" ?>
                    >
                </div>

                <div>
                    <label for="rol">Rol</label>
                    <select id="rol" name="rol">
                        <?php foreach (
                            [
                                "lider" => "Líder",
                                "supervisor" => "Supervisor",
                                "admin" => "Administrador",
                            ]
                            as $valor => $texto
                        ): ?>
                            <option
                                value="<?= $valor ?>"
                                <?= ($usuarioEditado["rol"] ?? "lider") ===
                                $valor
                                    ? "selected"
                                    : "" ?>
                            >
                                <?= $texto ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($usuarioEditado): ?>
                    <div>
                        <label for="sector_id">Sector</label>
                        <select id="sector_id" name="sector_id">
                            <option value="">No aplica</option>
                            <?php foreach ($sectores as $sector): ?>
                                <option value="<?= $sector["id"] ?>" <?= ($usuarioEditado["sector_id"] ?? "") == $sector["id"] ? "selected" : "" ?>>
                                    <?= e($sector["nombre"]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="full">
                        <label for="celula_id">Célula (solo líder)</label>
                        <select id="celula_id" name="celula_id">
                            <option value="">Sin asignar</option>
                            <?php foreach ($celulas as $celula): ?>
                                <option value="<?= $celula["id"] ?>" <?= ($usuarioEditado["celula_id"] ?? "") == $celula["id"] ? "selected" : "" ?>>
                                    <?= e($celula["nombre"]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button class="btn btn-gold" type="submit">Guardar usuario</button>
                <?php if ($usuarioEditado): ?>
                    <a class="btn btn-soft" href="usuarios.php">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="card card-flush">
        <div class="card-head">
            <h3>Lista de usuarios</h3>
            <span class="badge ok"><?= count($usuarios) ?> cuentas</span>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Asignación</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td><strong><?= e(
                                $usuario["nombre_completo"],
                            ) ?></strong></td>
                            <td><?= e($usuario["usuario"]) ?></td>
                            <td><?= e(ucfirst($usuario["rol"])) ?></td>
                            <td>
                                <?= e($usuario["sector"] ?? "—") ?>
                                <?= $usuario["celula"]
                                    ? " · " . e($usuario["celula"])
                                    : "" ?>
                            </td>
                            <td>
                                <span class="badge <?= $usuario["activo"]
                                    ? "ok"
                                    : "off" ?>">
                                    <?= $usuario["activo"]
                                        ? "Activo"
                                        : "Inactivo" ?>
                                </span>
                            </td>
                            <td class="actions-cell">
                                <a
                                    class="btn btn-soft btn-sm"
                                    href="usuarios.php?editar=<?= $usuario[
                                        "id"
                                    ] ?>"
                                >
                                    Editar
                                </a>

                                <?php if (
                                    $usuario["id"] != $_SESSION["usuario_id"]
                                ): ?>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                                        <input type="hidden" name="accion" value="cambiar_estado">
                                        <input type="hidden" name="id" value="<?= $usuario[
                                            "id"
                                        ] ?>">
                                        <button
                                            class="btn btn-sm <?= $usuario[
                                                "activo"
                                            ]
                                                ? "btn-danger"
                                                : "btn-soft" ?>"
                                            type="submit"
                                        >
                                            <?= $usuario["activo"]
                                                ? "Desactivar"
                                                : "Activar" ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php cerrarPaginaAdmin(); ?>
