<?php

require_once __DIR__ . "/_bootstrap.php";

$reporteId = (int) ($_GET["id"] ?? 0);
$sectorId = (int) ($_GET["sector"] ?? 0);

$consulta = $conexion->prepare(
    'SELECT r.*, u.nombre_completo, c.nombre AS celula, c.tipo AS tipo_celula, s.nombre AS sector
     FROM reportes_lider r
     INNER JOIN usuarios u ON u.id = r.lider_id
     INNER JOIN celulas c ON c.id = r.celula_id
     INNER JOIN sectores s ON s.id = c.sector_id
     WHERE r.id = ?',
);
$consulta->execute([$reporteId]);
$reporte = $consulta->fetch();

if (!$reporte) {
    flash("error", "El reporte solicitado no existe.");
    redirigirAdmin("reportes.php");
}

$consulta = $conexion->prepare(
    "UPDATE reportes_lider SET visto_admin_en = COALESCE(visto_admin_en, NOW()) WHERE id = ?",
);
$consulta->execute([$reporteId]);

$consulta = $conexion->prepare(
    "SELECT visto_admin_en FROM reportes_lider WHERE id = ?",
);
$consulta->execute([$reporteId]);
$reporte["visto_admin_en"] = $consulta->fetchColumn();

$urlVolver = "reportes.php" . ($sectorId > 0 ? "?sector=" . $sectorId : "");

abrirPaginaAdmin("reportes", "Detalle del reporte");
require __DIR__ . "/../components/reporte-lider-detalle.php";
cerrarPaginaAdmin();
