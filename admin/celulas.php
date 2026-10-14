<?php

require_once __DIR__ . "/_bootstrap.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    validarCsrf();
    $accion = $_POST["accion"] ?? "";

    try {
        if ($accion === "guardar") {
            $id = (int) ($_POST["id"] ?? 0);
            $liderId = !empty($_POST["lider_id"])
                ? (int) $_POST["lider_id"]
                : null;
            $sectorId = (int) $_POST["sector_id"];
            $datos = [
                trim($_POST["nombre"] ?? ""),
                $sectorId,
                $liderId,
                $_POST["tipo"],
                trim($_POST["direccion"] ?? ""),
                $_POST["lat"] !== "" ? $_POST["lat"] : null,
                $_POST["lng"] !== "" ? $_POST["lng"] : null,
            ];

            $conexion->beginTransaction();

            $consultaSector = $conexion->prepare("SELECT id FROM sectores WHERE id = ? AND activo = 1");
            $consultaSector->execute([$sectorId]);
            if (!$consultaSector->fetchColumn()) {
                throw new InvalidArgumentException("El sector seleccionado no está activo.");
            }

            if ($liderId) {
                $consultaLider = $conexion->prepare("SELECT id FROM usuarios WHERE id = ? AND rol = 'lider' AND activo = 1");
                $consultaLider->execute([$liderId]);
                if (!$consultaLider->fetchColumn()) {
                    throw new InvalidArgumentException("Solo se puede asignar un líder activo a una célula.");
                }
            }

            if ($id > 0) {
                $datos[] = $id;
                $consulta = $conexion->prepare(
                    'UPDATE celulas
                     SET nombre = ?, sector_id = ?, lider_id = ?, tipo = ?,
                         direccion = ?, lat = ?, lng = ?
                     WHERE id = ?',
                );
                $consulta->execute($datos);
                $celulaId = $id;
            } else {
                $consulta = $conexion->prepare(
                    'INSERT INTO celulas
                        (nombre, sector_id, lider_id, tipo, direccion, lat, lng)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                );
                $consulta->execute($datos);
                $celulaId = (int) $conexion->lastInsertId();
            }

            if ($liderId) {
                // Un líder solo puede tener una célula. También se libera una
                // eventual asignación anterior para mantener ambas tablas coherentes.
                $consulta = $conexion->prepare("UPDATE celulas SET lider_id = NULL WHERE lider_id = ? AND id <> ?");
                $consulta->execute([$liderId, $celulaId]);
                $consulta = $conexion->prepare("UPDATE usuarios SET celula_id = NULL WHERE celula_id = ? AND id <> ?");
                $consulta->execute([$celulaId, $liderId]);
                $consulta = $conexion->prepare(
                    'UPDATE usuarios
                     SET sector_id = ?, celula_id = ?
                     WHERE id = ?',
                );
                $consulta->execute([$sectorId, $celulaId, $liderId]);
            } else {
                $consulta = $conexion->prepare("UPDATE usuarios SET celula_id = NULL WHERE celula_id = ?");
                $consulta->execute([$celulaId]);
            }

            $conexion->commit();
            flash("success", "Célula guardada correctamente.");
        }

        if ($accion === "cambiar_estado") {
            $consulta = $conexion->prepare(
                "UPDATE celulas SET activo = 1 - activo WHERE id = ?",
            );
            $consulta->execute([(int) $_POST["id"]]);
            flash("success", "Estado de la célula actualizado.");
        }
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        error_log($error->getMessage());
        flash("error", "No fue posible guardar la célula. Verifica los datos.");
    }

    redirigirAdmin("celulas.php");
}

$celulaEditada = null;

if (isset($_GET["editar"])) {
    $consulta = $conexion->prepare("SELECT * FROM celulas WHERE id = ?");
    $consulta->execute([(int) $_GET["editar"]]);
    $celulaEditada = $consulta->fetch();
}

$sectores = $conexion
    ->query("SELECT id, nombre FROM sectores WHERE activo = 1 ORDER BY nombre")
    ->fetchAll();

$lideres = $conexion
    ->query(
        "SELECT id, nombre_completo
     FROM usuarios
     WHERE rol = 'lider' AND activo = 1
     ORDER BY nombre_completo",
    )
    ->fetchAll();

$celulas = $conexion
    ->query(
        'SELECT
        c.*,
        s.nombre AS sector,
        u.nombre_completo AS lider
     FROM celulas c
     INNER JOIN sectores s ON s.id = c.sector_id
     LEFT JOIN usuarios u ON u.id = c.lider_id
     ORDER BY s.nombre, c.nombre',
    )
    ->fetchAll();

$puntosMapa = [];
foreach ($celulas as $celula) {
    if ($celula["lat"] && $celula["lng"]) {
        $puntosMapa[] = [
            "lat" => (float) $celula["lat"],
            "lng" => (float) $celula["lng"],
            "nombre" => $celula["nombre"],
            "sector" => $celula["sector"],
        ];
    }
}

abrirPaginaAdmin(
    "celulas",
    "Células"
);
?>

<div class="section-split">
    <section class="card card-flush">
        <h3><?= $celulaEditada ? "Editar célula" : "Nueva célula" ?></h3>

        <form method="post" id="formCelula">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" value="<?= e(
                $celulaEditada["id"] ?? "",
            ) ?>">

            <label for="nombre">Nombre</label>
            <input
                id="nombre"
                name="nombre"
                required
                value="<?= e($celulaEditada["nombre"] ?? "") ?>"
            >

            <div class="form-grid form-spaced">
                <div>
                    <label for="sector_id">Sector</label>
                    <select id="sector_id" name="sector_id" required>
                        <?php foreach ($sectores as $sector): ?>
                            <option
                                value="<?= $sector["id"] ?>"
                                <?= ($celulaEditada["sector_id"] ?? "") ==
                                $sector["id"]
                                    ? "selected"
                                    : "" ?>
                            >
                                <?= e($sector["nombre"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="lider_id">Líder</label>
                    <select id="lider_id" name="lider_id">
                        <option value="">Sin asignar</option>
                        <?php foreach ($lideres as $lider): ?>
                            <option
                                value="<?= $lider["id"] ?>"
                                <?= ($celulaEditada["lider_id"] ?? "") ==
                                $lider["id"]
                                    ? "selected"
                                    : "" ?>
                            >
                                <?= e($lider["nombre_completo"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="tipo">Tipo</label>
                    <select id="tipo" name="tipo">
                        <?php foreach (
                            [
                                "adultos" => "Adultos",
                                "jovenes" => "Jóvenes",
                                "ninos" => "Niños",
                            ]
                            as $valor => $texto
                        ): ?>
                            <option
                                value="<?= $valor ?>"
                                <?= ($celulaEditada["tipo"] ?? "adultos") ===
                                $valor
                                    ? "selected"
                                    : "" ?>
                            >
                                <?= $texto ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="direccion">Dirección</label>
                    <input
                        id="direccion"
                        name="direccion"
                        value="<?= e($celulaEditada["direccion"] ?? "") ?>"
                    >
                </div>

                <div class="full">
                    <label>Ubicación en el mapa</label>
                    <div class="location-help">
                        <i class="ri-map-pin-add-line"></i>
                        <span>
                            Presiona el punto exacto donde se reúne la célula.
                            Puedes mover el mapa y acercarlo antes de seleccionar.
                        </span>
                    </div>
                    <div
                        id="mapaSelector"
                        class="location-picker"
                        aria-label="Seleccionar ubicación de la célula"
                        data-lat="<?= e($celulaEditada["lat"] ?? "") ?>"
                        data-lng="<?= e($celulaEditada["lng"] ?? "") ?>"
                    ></div>
                    <div
                        id="estadoUbicacion"
                        class="location-status <?= !empty($celulaEditada["lat"]) ? "selected" : "" ?>"
                    >
                        <i class="ri-checkbox-circle-line"></i>
                        <span>
                            <?= !empty($celulaEditada["lat"])
                                ? "Ubicación seleccionada"
                                : "Presiona el mapa para seleccionar la ubicación" ?>
                        </span>
                    </div>
                    <input
                        id="lat"
                        name="lat"
                        type="hidden"
                        value="<?= e($celulaEditada["lat"] ?? "") ?>"
                        required
                    >
                    <input
                        id="lng"
                        name="lng"
                        type="hidden"
                        value="<?= e($celulaEditada["lng"] ?? "") ?>"
                        required
                    >
                </div>
            </div>

            <div class="form-actions">
                <button class="btn btn-gold" type="submit">Guardar célula</button>
                <?php if ($celulaEditada): ?>
                    <a class="btn btn-soft" href="celulas.php">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="card card-flush">
        <div class="card-head">
            <h3>Células registradas</h3>
            <span class="badge ok"><?= count($celulas) ?> en total</span>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Célula</th>
                        <th>Sector</th>
                        <th>Líder</th>
                        <th>Tipo</th>
                        <th>Ubicación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($celulas as $celula): ?>
                        <tr>
                            <td>
                                <strong><?= e($celula["nombre"]) ?></strong><br>
                                <span class="badge <?= $celula["activo"]
                                    ? "ok"
                                    : "off" ?>">
                                    <?= $celula["activo"]
                                        ? "Activa"
                                        : "Inactiva" ?>
                                </span>
                            </td>
                            <td><?= e($celula["sector"]) ?></td>
                            <td><?= e($celula["lider"] ?? "Sin asignar") ?></td>
                            <td><?= e(ucfirst($celula["tipo"])) ?></td>
                            <td><?= e($celula["direccion"] ?: "—") ?></td>
                            <td class="actions-cell">
                                <a
                                    class="btn btn-soft btn-sm"
                                    href="<?= urlApp('exportar-miembros.php?celula_id=' . $celula['id']) ?>"
                                    title="Descargar miembros en PDF"
                                >
                                    <i class="ri-file-pdf-2-line"></i> PDF
                                </a>
                                <a
                                    class="btn btn-soft btn-sm"
                                    href="celulas.php?editar=<?= $celula[
                                        "id"
                                    ] ?>"
                                >
                                    Editar
                                </a>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="accion" value="cambiar_estado">
                                    <input type="hidden" name="id" value="<?= $celula[
                                        "id"
                                    ] ?>">
                                    <button
                                        class="btn btn-sm <?= $celula["activo"]
                                            ? "btn-danger"
                                            : "btn-soft" ?>"
                                        type="submit"
                                    >
                                        <?= $celula["activo"]
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

<section class="card">
    <div class="card-head">
        <div>
            <h3>Mapa de células</h3>
            <p class="muted">Ubicaciones registradas para la planificación de visitas.</p>
        </div>
    </div>
    <div
        id="mapaCelulas"
        class="map"
        data-points="<?= e(json_encode($puntosMapa, JSON_UNESCAPED_UNICODE)) ?>"
    ></div>
</section>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<?php
cerrarPaginaAdmin([
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
    'js/admin.js',
]);
?>
