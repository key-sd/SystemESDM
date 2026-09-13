<?php

require_once __DIR__ . "/_bootstrap.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once __DIR__ . "/../vendor/autoload.php";

function rangoExportacion(string $periodo, string $inicioRecibido, string $finRecibido): array
{
    $hoy = new DateTimeImmutable("today");
    return match ($periodo) {
        "semanal" => [$hoy->modify("monday this week"), $hoy->modify("sunday this week")],
        "trimestral" => [
            $hoy->setDate((int) $hoy->format("Y"), ((int) ceil((int) $hoy->format("n") / 3) - 1) * 3 + 1, 1),
            $hoy->setDate((int) $hoy->format("Y"), ((int) ceil((int) $hoy->format("n") / 3) - 1) * 3 + 1, 1)->modify("+3 months -1 day"),
        ],
        "anual" => [$hoy->setDate((int) $hoy->format("Y"), 1, 1), $hoy->setDate((int) $hoy->format("Y"), 12, 31)],
        "personalizado" => rangoPersonalizado($inicioRecibido, $finRecibido, $hoy),
        default => [$hoy->modify("first day of this month"), $hoy->modify("last day of this month")],
    };
}

function rangoPersonalizado(string $inicio, string $fin, DateTimeImmutable $respaldo): array
{
    $desde = DateTimeImmutable::createFromFormat("!Y-m-d", $inicio) ?: $respaldo->modify("first day of this month");
    $hasta = DateTimeImmutable::createFromFormat("!Y-m-d", $fin) ?: $respaldo->modify("last day of this month");
    return $desde <= $hasta ? [$desde, $hasta] : [$hasta, $desde];
}

function aplicarEncabezado($hoja, string $titulo, array $encabezados): void
{
    $ultimaColumna = chr(64 + count($encabezados));
    $hoja->mergeCells("A1:" . $ultimaColumna . "1");
    $hoja->setCellValue("A1", $titulo);
    $hoja->getStyle("A1")->getFont()->setBold(true)->setSize(16)->getColor()->setRGB("FFFFFF");
    $hoja->getStyle("A1:" . $ultimaColumna . "1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB("010326");
    $hoja->getStyle("A1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $hoja->fromArray($encabezados, null, "A3");
    $hoja->getStyle("A3:" . $ultimaColumna . "3")->getFont()->setBold(true)->getColor()->setRGB("FFFFFF");
    $hoja->getStyle("A3:" . $ultimaColumna . "3")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB("3061F2");
    $hoja->freezePane("A4");
}

function exportarExcel(string $origen, array $filas, DateTimeImmutable $inicio, DateTimeImmutable $fin): never
{
    $esServicio = $origen === "servicios";
    $encabezados = $esServicio
        ? ["Fecha", "Servicio", "Adultos", "Niños", "Asistencia", "Ofrenda", "Diezmos", "Total ingresos", "Registrado por", "Observaciones"]
        : ["Fecha", "Sector", "Célula", "Líder", "Asistencia", "Ofrenda iglesia", "Misiones", "Bus", "Total ofrenda", "Conversiones", "Visitas", "Bautismos", "Observaciones"];
    $libro = new Spreadsheet();
    $resumen = $libro->getActiveSheet();
    $resumen->setTitle("Resumen");
    $resumen->setCellValue("A1", "Reporte " . ($esServicio ? "de servicios" : "de células"));
    $resumen->setCellValue("A2", "Periodo: " . $inicio->format("d/m/Y") . " al " . $fin->format("d/m/Y"));
    $resumen->getStyle("A1")->getFont()->setBold(true)->setSize(16);
    $resumen->getStyle("A1")->getFont()->getColor()->setRGB("010326");
    $resumen->fromArray([["Indicador", "Total"], ["Registros", count($filas)]], null, "A4");
    $resumen->getStyle("A4:B4")->getFont()->setBold(true)->getColor()->setRGB("FFFFFF");
    $resumen->getStyle("A4:B4")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB("3061F2");

    $detalle = $libro->createSheet();
    $detalle->setTitle("Detalle");
    aplicarEncabezado($detalle, "Detalle del reporte", $encabezados);
    $filaExcel = 4;
    foreach ($filas as $fila) {
        $valores = $esServicio
            ? [$fila["fecha"], ucfirst($fila["tipo_servicio"]), (int) $fila["asistencia_adultos"], (int) $fila["asistencia_ninos"], (int) $fila["asistencia_total"], (float) $fila["ofrenda"], (float) $fila["diezmos"], (float) $fila["ofrenda"] + (float) $fila["diezmos"], $fila["registrado_por"] ?? "", $fila["observaciones"] ?? ""]
            : [$fila["fecha_reporte"], $fila["sector"], $fila["celula"], $fila["lider"], (int) $fila["total_asistencia"], (float) $fila["ofrenda_iglesia"], (float) $fila["ofrenda_misiones"], (float) $fila["ofrenda_bus"], (float) $fila["ofrenda_total"], (int) $fila["conversion"], (int) $fila["visita_programada"], (int) $fila["bautismo_agua"], $fila["observaciones"] ?? ""];
        $detalle->fromArray($valores, null, "A" . $filaExcel);
        $filaExcel++;
    }
    $ultimaFila = max(4, $filaExcel - 1);
    $ultimaColumna = chr(64 + count($encabezados));
    $detalle->setAutoFilter("A3:" . $ultimaColumna . $ultimaFila);
    $resumen->setCellValue("A6", "Asistencia total");
    $resumen->setCellValue("B6", "=SUM(Detalle!E4:E" . $ultimaFila . ")");
    if ($esServicio) {
        $resumen->setCellValue("A7", "Ofrendas"); $resumen->setCellValue("B7", "=SUM(Detalle!F4:F" . $ultimaFila . ")");
        $resumen->setCellValue("A8", "Diezmos"); $resumen->setCellValue("B8", "=SUM(Detalle!G4:G" . $ultimaFila . ")");
        $resumen->setCellValue("A9", "Ingresos totales"); $resumen->setCellValue("B9", "=SUM(Detalle!H4:H" . $ultimaFila . ")");
        $columnasDinero = "F:H";
    } else {
        $resumen->setCellValue("A7", "Ofrenda total"); $resumen->setCellValue("B7", "=SUM(Detalle!I4:I" . $ultimaFila . ")");
        $resumen->setCellValue("A8", "Conversiones"); $resumen->setCellValue("B8", "=SUM(Detalle!J4:J" . $ultimaFila . ")");
        $resumen->setCellValue("A9", "Visitas programadas"); $resumen->setCellValue("B9", "=SUM(Detalle!K4:K" . $ultimaFila . ")");
        $resumen->setCellValue("A10", "Bautismos"); $resumen->setCellValue("B10", "=SUM(Detalle!L4:L" . $ultimaFila . ")");
        $columnasDinero = "F:I";
    }
    foreach (explode(":", $columnasDinero) as $columna) $detalle->getStyle($columna . "4:" . $columna . $ultimaFila)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD);
    $resumen->getStyle($esServicio ? "B7:B9" : "B7")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD);
    foreach (range("A", $ultimaColumna) as $columna) $detalle->getColumnDimension($columna)->setAutoSize(true);
    $resumen->getColumnDimension("A")->setWidth(28); $resumen->getColumnDimension("B")->setWidth(18);
    $libro->setActiveSheetIndex(0);

    $archivo = "reporte_" . $origen . "_" . $inicio->format("Ymd") . "_" . $fin->format("Ymd") . ".xlsx";
    while (ob_get_level() > 0) ob_end_clean();
    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header("Content-Disposition: attachment; filename=\"" . $archivo . "\"");
    header("Cache-Control: max-age=0");
    (new Xlsx($libro))->save("php://output");
    exit();
}

$origen = $_GET["origen"] ?? "servicios";
if (!in_array($origen, ["servicios", "celulas"], true)) $origen = "servicios";
$periodo = $_GET["periodo"] ?? "mensual";
if (!in_array($periodo, ["semanal", "mensual", "trimestral", "anual", "personalizado"], true)) $periodo = "mensual";
[$inicio, $fin] = rangoExportacion($periodo, $_GET["inicio"] ?? "", $_GET["fin"] ?? "");
$tipoServicio = $_GET["tipo_servicio"] ?? "";
if (!in_array($tipoServicio, ["", "martes", "jueves", "domingo"], true)) $tipoServicio = "";
$sectorId = filter_input(INPUT_GET, "sector_id", FILTER_VALIDATE_INT) ?: 0;
$sectores = $conexion->query("SELECT id, nombre FROM sectores WHERE activo = 1 ORDER BY nombre")->fetchAll();
$filas = [];
if ($origen === "servicios") {
    $sql = "SELECT s.*, u.nombre_completo AS registrado_por FROM servicios_iglesia s LEFT JOIN usuarios u ON u.id=s.registrado_por WHERE s.fecha BETWEEN ? AND ?";
    $parametros = [$inicio->format("Y-m-d"), $fin->format("Y-m-d")];
    if ($tipoServicio) { $sql .= " AND s.tipo_servicio = ?"; $parametros[] = $tipoServicio; }
    $sql .= " ORDER BY s.fecha";
} else {
    $sql = "SELECT r.*, s.nombre AS sector, c.nombre AS celula, u.nombre_completo AS lider FROM reportes_lider r INNER JOIN celulas c ON c.id=r.celula_id INNER JOIN sectores s ON s.id=c.sector_id INNER JOIN usuarios u ON u.id=r.lider_id WHERE r.fecha_reporte BETWEEN ? AND ?";
    $parametros = [$inicio->format("Y-m-d"), $fin->format("Y-m-d")];
    if ($sectorId) { $sql .= " AND s.id = ?"; $parametros[] = $sectorId; }
    $sql .= " ORDER BY r.fecha_reporte, s.nombre, c.nombre";
}
$consulta = $conexion->prepare($sql); $consulta->execute($parametros); $filas = $consulta->fetchAll();
if (isset($_GET["descargar"])) exportarExcel($origen, $filas, $inicio, $fin);

abrirPaginaAdmin("exportar", "Exportar reportes a Excel");
?>
<section class="card card-flush"><div class="card-head"><div><p class="eyebrow">Reportes descargables</p><h3>Excel de servicios y células</h3></div></div>
<form method="get" class="stats-filters"><div><label for="origen">Origen</label><select id="origen" name="origen"><option value="servicios" <?= $origen === "servicios" ? "selected" : "" ?>>Servicios de iglesia</option><option value="celulas" <?= $origen === "celulas" ? "selected" : "" ?>>Reportes de células</option></select></div><div><label for="periodo">Periodo</label><select id="periodo" name="periodo"><option value="semanal" <?= $periodo === "semanal" ? "selected" : "" ?>>Semana actual</option><option value="mensual" <?= $periodo === "mensual" ? "selected" : "" ?>>Mes actual</option><option value="trimestral" <?= $periodo === "trimestral" ? "selected" : "" ?>>Trimestre actual</option><option value="anual" <?= $periodo === "anual" ? "selected" : "" ?>>Año actual</option><option value="personalizado" <?= $periodo === "personalizado" ? "selected" : "" ?>>Personalizado</option></select></div><div><label for="inicio">Desde</label><input id="inicio" type="date" name="inicio" value="<?= e($inicio->format("Y-m-d")) ?>"></div><div><label for="fin">Hasta</label><input id="fin" type="date" name="fin" value="<?= e($fin->format("Y-m-d")) ?>"></div><div><label for="tipo_servicio">Servicio</label><select id="tipo_servicio" name="tipo_servicio"><option value="">Todos</option><?php foreach (["martes", "jueves", "domingo"] as $tipo): ?><option value="<?= $tipo ?>" <?= $tipoServicio === $tipo ? "selected" : "" ?>><?= e(ucfirst($tipo)) ?></option><?php endforeach; ?></select></div><div><label for="sector_id">Sector</label><select id="sector_id" name="sector_id"><option value="">Todos</option><?php foreach ($sectores as $sector): ?><option value="<?= $sector["id"] ?>" <?= $sectorId === (int)$sector["id"] ? "selected" : "" ?>><?= e($sector["nombre"]) ?></option><?php endforeach; ?></select></div><button class="btn btn-soft" type="submit">Ver registros</button><button class="btn btn-gold" type="submit" name="descargar" value="1"><i class="ri-download-2-line"></i> Descargar Excel</button></form></section>
<section class="card"><div class="card-head"><h3><?= count($filas) ?> registros encontrados</h3><span class="badge ok"><?= $inicio->format("d/m/Y") ?> – <?= $fin->format("d/m/Y") ?></span></div><div class="table-wrap"><table><thead><tr><th>Fecha</th><th><?= $origen === "servicios" ? "Servicio" : "Sector / célula" ?></th><th>Asistencia</th><th><?= $origen === "servicios" ? "Ofrenda" : "Ofrenda total" ?></th><th><?= $origen === "servicios" ? "Diezmos" : "Conversión" ?></th></tr></thead><tbody><?php foreach (array_slice($filas, 0, 50) as $fila): ?><tr><td data-label="Fecha"><?= date("d/m/Y", strtotime($fila[$origen === "servicios" ? "fecha" : "fecha_reporte"])) ?></td><td data-label="Origen"><?= $origen === "servicios" ? e(ucfirst($fila["tipo_servicio"])) : e($fila["sector"] . " / " . $fila["celula"]) ?></td><td data-label="Asistencia"><?= $fila[$origen === "servicios" ? "asistencia_total" : "total_asistencia"] ?></td><td data-label="Ofrenda">$<?= number_format($fila[$origen === "servicios" ? "ofrenda" : "ofrenda_total"], 2) ?></td><td data-label="Dato"><?= $origen === "servicios" ? "$" . number_format($fila["diezmos"], 2) : $fila["conversion"] ?></td></tr><?php endforeach; ?><?php if (!$filas): ?><tr><td colspan="5" class="empty">No hay registros con esos filtros.</td></tr><?php endif; ?></tbody></table></div><?php if (count($filas) > 50): ?><p class="form-help muted">La vista muestra los primeros 50 registros; el Excel incluye los <?= count($filas) ?> registros.</p><?php endif; ?></section>
<?php cerrarPaginaAdmin(); ?>
