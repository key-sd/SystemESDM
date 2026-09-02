<?php

require_once __DIR__ . "/../includes/conexion.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/funciones.php";

requiereRol(["supervisor"]);

$reporteId = (int) ($_GET["id"] ?? 0);
$sectorId = (int) ($_SESSION["sector_id"] ?? 0);

$consulta = $conexion->prepare(
    'SELECT r.*, u.nombre_completo, c.nombre AS celula, c.tipo AS tipo_celula, s.nombre AS sector
     FROM reportes_lider r
     INNER JOIN usuarios u ON u.id = r.lider_id
     INNER JOIN celulas c ON c.id = r.celula_id
     INNER JOIN sectores s ON s.id = c.sector_id
     WHERE r.id = ? AND c.sector_id = ?',
);
$consulta->execute([$reporteId, $sectorId]);
$reporte = $consulta->fetch();

if (!$reporte) {
    flash("error", "No tienes acceso a ese reporte.");
    header("Location: sup-inicio.php?vista=resumen");
    exit;
}

$consulta = $conexion->prepare(
    "UPDATE reportes_lider SET visto_supervisor_en = COALESCE(visto_supervisor_en, NOW()) WHERE id = ?",
);
$consulta->execute([$reporteId]);

$consulta = $conexion->prepare(
    "SELECT visto_supervisor_en FROM reportes_lider WHERE id = ?",
);
$consulta->execute([$reporteId]);
$reporte["visto_supervisor_en"] = $consulta->fetchColumn();

$actual = "resumen";
$titulo = "Detalle del reporte";
$subtitulo = "Reporte semanal de una célula de tu sector";
$urlVolver = "sup-inicio.php?vista=resumen";

require __DIR__ . "/../components/head.php";
require __DIR__ . "/../components/sidebar.php";
require __DIR__ . "/../components/top.php";
require __DIR__ . "/../components/reporte-lider-detalle.php";
require __DIR__ . "/../components/foot.php";
