<?php

require_once __DIR__ . "/_bootstrap.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once __DIR__ . "/../vendor/autoload.php";

if (!function_exists("rangoExportacion")) {
    function rangoExportacion(string $periodo, string $inicioRecibido, string $finRecibido): array
    {
        $hoy = new DateTimeImmutable("today");
        return match ($periodo) {
            "mes_actual", "mensual" => [
                $hoy->modify("first day of this month"),
                $hoy->modify("last day of this month"),
            ],
            "ultimos_3_meses", "trimestral" => [
                $hoy->modify("first day of this month")->modify("-2 months"),
                $hoy->modify("last day of this month"),
            ],
            "ultimo_anio", "anual" => [
                $hoy->setDate((int) $hoy->format("Y"), 1, 1),
                $hoy->setDate((int) $hoy->format("Y"), 12, 31),
            ],
            "semanal" => [
                $hoy->modify("monday this week"),
                $hoy->modify("sunday this week"),
            ],
            "personalizado" => rangoPersonalizado($inicioRecibido, $finRecibido, $hoy),
            default => [
                $hoy->modify("first day of this month")->modify("-2 months"),
                $hoy->modify("last day of this month"),
            ],
        };
    }
}

if (!function_exists("rangoPersonalizado")) {
    function rangoPersonalizado(string $inicio, string $fin, DateTimeImmutable $respaldo): array
    {
        $desde = DateTimeImmutable::createFromFormat("!Y-m-d", $inicio) ?: $respaldo->modify("first day of this month");
        $hasta = DateTimeImmutable::createFromFormat("!Y-m-d", $fin) ?: $respaldo->modify("last day of this month");
        return $desde <= $hasta ? [$desde, $hasta] : [$hasta, $desde];
    }
}

if (!function_exists("exportarExcel")) {
    function exportarExcel(
        string $origen,
        array $filas,
        DateTimeImmutable $inicio,
        DateTimeImmutable $fin,
        array $sectoresAgrupados,
        array $totalesGlobales,
        array $serviciosAgrupados,
        array $totalesServicios,
        string $nombreSectorSeleccionado = ""
    ): never {
        $libro = new Spreadsheet();
        $formatoMoneda = '$#,##0.00';
        $formatoEntero = '#,##0';

        if ($origen === "celulas") {
            // ==========================================
            // HOJA 1: CONSOLIDADO POR CÉLULA Y SECTOR
            // ==========================================
            $hoja1 = $libro->getActiveSheet();
            $hoja1->setTitle("Consolidado Células");

            $tituloReporte = "IGLESIA EVANGÉLICA ELIM SAN MARTÍN";
            $subtituloReporte = "REPORTE DE CÉLULAS - " . ($nombreSectorSeleccionado !== "" ? "SECTOR: " . mb_strtoupper($nombreSectorSeleccionado) : "TODOS LOS SECTORES");
            $periodoReporte = "Periodo: " . $inicio->format("d/m/Y") . " al " . $fin->format("d/m/Y");

            $hoja1->mergeCells("A1:N1");
            $hoja1->setCellValue("A1", $tituloReporte);
            $hoja1->getStyle("A1")->getFont()->setBold(true)->setSize(15)->getColor()->setRGB("FFFFFF");
            $hoja1->getStyle("A1:N1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB("010326");
            $hoja1->getStyle("A1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $hoja1->mergeCells("A2:N2");
            $hoja1->setCellValue("A2", $subtituloReporte);
            $hoja1->getStyle("A2")->getFont()->setBold(true)->setSize(12)->getColor()->setRGB("FFFFFF");
            $hoja1->getStyle("A2:N2")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB("1E3A8A");
            $hoja1->getStyle("A2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $hoja1->mergeCells("A3:N3");
            $hoja1->setCellValue("A3", $periodoReporte);
            $hoja1->getStyle("A3")->getFont()->setItalic(true)->setSize(10)->getColor()->setRGB("020540");
            $hoja1->getStyle("A3:N3")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB("EDF2FF");
            $hoja1->getStyle("A3")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $encabezadosConsolidado = [
                "Sector", "Célula", "Líder", "Tipo", "Semanas",
                "Asist. Total", "Promedio", "Ofrenda Iglesia", "Misiones", "Transporte",
                "Total Ofrenda", "Conversiones", "Visitas", "Bautismos"
            ];
            $hoja1->fromArray($encabezadosConsolidado, null, "A5");
            $hoja1->getStyle("A5:N5")->getFont()->setBold(true)->getColor()->setRGB("FFFFFF");
            $hoja1->getStyle("A5:N5")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB("3061F2");
            $hoja1->getStyle("A5:N5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $hoja1->freezePane("A6");

            $filaActual = 6;
            $filasSubtotales = [];

            if (empty($sectoresAgrupados)) {
                $hoja1->mergeCells("A6:N6");
                $hoja1->setCellValue("A6", "No hay registros en el periodo seleccionado.");
                $hoja1->getStyle("A6")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $filaActual = 7;
            } else {
                foreach ($sectoresAgrupados as $sectorData) {
                    $inicioFilaSector = $filaActual;

                    foreach ($sectorData["celulas"] as $c) {
                        $promedioAsist = $c["semanas"] > 0 ? round($c["asistencia_total"] / $c["semanas"], 1) : 0;
                        $filaC = [
                            $sectorData["nombre"],
                            $c["nombre"],
                            $c["lider"],
                            ucfirst($c["tipo_celula"]),
                            $c["semanas"],
                            $c["asistencia_total"],
                            $promedioAsist,
                            $c["ofrenda_iglesia"],
                            $c["ofrenda_misiones"],
                            $c["ofrenda_bus"],
                            $c["ofrenda_total"],
                            $c["conversiones"],
                            $c["visitas"],
                            $c["bautismos"],
                        ];
                        $hoja1->fromArray($filaC, null, "A" . $filaActual);
                        $filaActual++;
                    }

                    $finFilaSector = $filaActual - 1;

                    // Subtotal del Sector
                    $hoja1->setCellValue("A" . $filaActual, "SUBTOTAL " . mb_strtoupper($sectorData["nombre"]));
                    $hoja1->setCellValue("E" . $filaActual, "=SUM(E{$inicioFilaSector}:E{$finFilaSector})");
                    $hoja1->setCellValue("F" . $filaActual, "=SUM(F{$inicioFilaSector}:F{$finFilaSector})");
                    $hoja1->setCellValue("G" . $filaActual, "=IF(E{$filaActual}>0, F{$filaActual}/E{$filaActual}, 0)");
                    $hoja1->setCellValue("H" . $filaActual, "=SUM(H{$inicioFilaSector}:H{$finFilaSector})");
                    $hoja1->setCellValue("I" . $filaActual, "=SUM(I{$inicioFilaSector}:I{$finFilaSector})");
                    $hoja1->setCellValue("J" . $filaActual, "=SUM(J{$inicioFilaSector}:J{$finFilaSector})");
                    $hoja1->setCellValue("K" . $filaActual, "=SUM(K{$inicioFilaSector}:K{$finFilaSector})");
                    $hoja1->setCellValue("L" . $filaActual, "=SUM(L{$inicioFilaSector}:L{$finFilaSector})");
                    $hoja1->setCellValue("M" . $filaActual, "=SUM(M{$inicioFilaSector}:M{$finFilaSector})");
                    $hoja1->setCellValue("N" . $filaActual, "=SUM(N{$inicioFilaSector}:N{$finFilaSector})");

                    $hoja1->getStyle("A{$filaActual}:N{$filaActual}")->getFont()->setBold(true)->getColor()->setRGB("010326");
                    $hoja1->getStyle("A{$filaActual}:N{$filaActual}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB("DBEAFE");
                    $hoja1->getStyle("A{$filaActual}:N{$filaActual}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
                    $hoja1->getStyle("A{$filaActual}:N{$filaActual}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);

                    $filasSubtotales[] = $filaActual;
                    $filaActual++;
                }

                // Total General
                $hoja1->setCellValue("A" . $filaActual, "TOTAL GENERAL");
                if (count($filasSubtotales) === 1) {
                    $subFila = $filasSubtotales[0];
                    foreach (["E", "F", "H", "I", "J", "K", "L", "M", "N"] as $col) {
                        $hoja1->setCellValue($col . $filaActual, "={$col}{$subFila}");
                    }
                    $hoja1->setCellValue("G" . $filaActual, "=IF(E{$filaActual}>0, F{$filaActual}/E{$filaActual}, 0)");
                } else {
                    foreach (["E", "F", "H", "I", "J", "K", "L", "M", "N"] as $col) {
                        $celdas = array_map(fn($f) => "{$col}{$f}", $filasSubtotales);
                        $hoja1->setCellValue($col . $filaActual, "=" . implode("+", $celdas));
                    }
                    $hoja1->setCellValue("G" . $filaActual, "=IF(E{$filaActual}>0, F{$filaActual}/E{$filaActual}, 0)");
                }

                $hoja1->getStyle("A{$filaActual}:N{$filaActual}")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB("FFFFFF");
                $hoja1->getStyle("A{$filaActual}:N{$filaActual}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB("010326");
                $hoja1->getStyle("A{$filaActual}:N{$filaActual}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
                $hoja1->getStyle("A{$filaActual}:N{$filaActual}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE);

                $hoja1->getStyle("E6:F" . $filaActual)->getNumberFormat()->setFormatCode($formatoEntero);
                $hoja1->getStyle("G6:G" . $filaActual)->getNumberFormat()->setFormatCode('#,##0.0');
                $hoja1->getStyle("H6:K" . $filaActual)->getNumberFormat()->setFormatCode($formatoMoneda);
                $hoja1->getStyle("L6:N" . $filaActual)->getNumberFormat()->setFormatCode($formatoEntero);
            }

            foreach (range("A", "N") as $col) {
                $hoja1->getColumnDimension($col)->setAutoSize(true);
            }

            // ==========================================
            // HOJA 2: DETALLE SEMANAL
            // ==========================================
            $hoja2 = $libro->createSheet();
            $hoja2->setTitle("Detalle Semanal");

            $hoja2->mergeCells("A1:Q1");
            $hoja2->setCellValue("A1", "DETALLE SEMANAL (" . $inicio->format("d/m/Y") . " - " . $fin->format("d/m/Y") . ")");
            $hoja2->getStyle("A1")->getFont()->setBold(true)->setSize(13)->getColor()->setRGB("FFFFFF");
            $hoja2->getStyle("A1:Q1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB("010326");
            $hoja2->getStyle("A1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $encabezadosDetalle = [
                "Fecha", "Sector", "Célula", "Líder", "Tipo",
                "Adultos", "Jóvenes", "Niños", "Total Asistencia",
                "Ofrenda Iglesia", "Misiones", "Transporte", "Total Ofrenda",
                "Conversiones", "Visitas", "Bautismos", "Observaciones"
            ];
            $hoja2->fromArray($encabezadosDetalle, null, "A3");
            $hoja2->getStyle("A3:Q3")->getFont()->setBold(true)->getColor()->setRGB("FFFFFF");
            $hoja2->getStyle("A3:Q3")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB("3061F2");
            $hoja2->freezePane("A4");

            $filaD = 4;
            foreach ($filas as $r) {
                $ad = (int)($r["adultos_amigos"] + $r["adultos_hermanos"]);
                $jov = (int)($r["jovenes_amigos"] + $r["jovenes_hermanos"]);
                $nin = (int)$r["ninos"];
                $filaValores = [
                    date("d/m/Y", strtotime($r["fecha_reporte"])),
                    $r["sector"],
                    $r["celula"],
                    $r["lider"],
                    ucfirst($r["tipo_celula"] ?? "adultos"),
                    $ad,
                    $jov,
                    $nin,
                    (int)$r["total_asistencia"],
                    (float)$r["ofrenda_iglesia"],
                    (float)$r["ofrenda_misiones"],
                    (float)$r["ofrenda_bus"],
                    (float)$r["ofrenda_total"],
                    (int)$r["conversion"],
                    (int)$r["visita_programada"],
                    (int)$r["bautismo_agua"],
                    $r["observaciones"] ?? ""
                ];
                $hoja2->fromArray($filaValores, null, "A" . $filaD);
                $filaD++;
            }

            $ultimaFilaD = max(4, $filaD - 1);
            $hoja2->setAutoFilter("A3:Q" . $ultimaFilaD);
            $hoja2->getStyle("J4:M" . $ultimaFilaD)->getNumberFormat()->setFormatCode($formatoMoneda);
            foreach (range("A", "Q") as $col) {
                $hoja2->getColumnDimension($col)->setAutoSize(true);
            }

        } else {
            // ==========================================
            // HOJA 1: CONSOLIDADO DE SERVICIOS
            // ==========================================
            $hoja1 = $libro->getActiveSheet();
            $hoja1->setTitle("Consolidado Servicios");

            $hoja1->mergeCells("A1:K1");
            $hoja1->setCellValue("A1", "IGLESIA EVANGÉLICA ELIM SAN MARTÍN");
            $hoja1->getStyle("A1")->getFont()->setBold(true)->setSize(15)->getColor()->setRGB("FFFFFF");
            $hoja1->getStyle("A1:K1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB("010326");
            $hoja1->getStyle("A1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $hoja1->mergeCells("A2:K2");
            $hoja1->setCellValue("A2", "REPORTE DE SERVICIOS Y CULTOS");
            $hoja1->getStyle("A2")->getFont()->setBold(true)->setSize(12)->getColor()->setRGB("FFFFFF");
            $hoja1->getStyle("A2:K2")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB("1E3A8A");
            $hoja1->getStyle("A2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $hoja1->mergeCells("A3:K3");
            $hoja1->setCellValue("A3", "Periodo: " . $inicio->format("d/m/Y") . " al " . $fin->format("d/m/Y"));
            $hoja1->getStyle("A3")->getFont()->setItalic(true)->setSize(10)->getColor()->setRGB("020540");
            $hoja1->getStyle("A3:K3")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB("EDF2FF");
            $hoja1->getStyle("A3")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $encabezadosServicios = [
                "Servicio", "Cultos",
                "Adultos", "Prom. Adultos",
                "Niños", "Prom. Niños",
                "Asistencia Total", "Promedio",
                "Ofrendas", "Diezmos", "Total Ingresos"
            ];
            $hoja1->fromArray($encabezadosServicios, null, "A5");
            $hoja1->getStyle("A5:K5")->getFont()->setBold(true)->getColor()->setRGB("FFFFFF");
            $hoja1->getStyle("A5:K5")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB("3061F2");
            $hoja1->getStyle("A5:K5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $hoja1->freezePane("A6");

            $filaS = 6;
            foreach ($serviciosAgrupados as $clave => $item) {
                $promAd = $item["cantidad"] > 0 ? round($item["adultos"] / $item["cantidad"], 1) : 0;
                $promNin = $item["cantidad"] > 0 ? round($item["ninos"] / $item["cantidad"], 1) : 0;
                $promTot = $item["cantidad"] > 0 ? round($item["total"] / $item["cantidad"], 1) : 0;
                $filaValores = [
                    $item["tipo"],
                    $item["cantidad"],
                    $item["adultos"],
                    $promAd,
                    $item["ninos"],
                    $promNin,
                    $item["total"],
                    $promTot,
                    $item["ofrenda"],
                    $item["diezmos"],
                    $item["ingresos"]
                ];
                $hoja1->fromArray($filaValores, null, "A" . $filaS);
                $filaS++;
            }

            // Total General
            $hoja1->setCellValue("A" . $filaS, "TOTAL GENERAL");
            $hoja1->setCellValue("B" . $filaS, "=SUM(B6:B" . ($filaS - 1) . ")");
            $hoja1->setCellValue("C" . $filaS, "=SUM(C6:C" . ($filaS - 1) . ")");
            $hoja1->setCellValue("D" . $filaS, "=IF(B{$filaS}>0, C{$filaS}/B{$filaS}, 0)");
            $hoja1->setCellValue("E" . $filaS, "=SUM(E6:E" . ($filaS - 1) . ")");
            $hoja1->setCellValue("F" . $filaS, "=IF(B{$filaS}>0, E{$filaS}/B{$filaS}, 0)");
            $hoja1->setCellValue("G" . $filaS, "=SUM(G6:G" . ($filaS - 1) . ")");
            $hoja1->setCellValue("H" . $filaS, "=IF(B{$filaS}>0, G{$filaS}/B{$filaS}, 0)");
            $hoja1->setCellValue("I" . $filaS, "=SUM(I6:I" . ($filaS - 1) . ")");
            $hoja1->setCellValue("J" . $filaS, "=SUM(J6:J" . ($filaS - 1) . ")");
            $hoja1->setCellValue("K" . $filaS, "=SUM(K6:K" . ($filaS - 1) . ")");

            $hoja1->getStyle("A{$filaS}:K{$filaS}")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB("FFFFFF");
            $hoja1->getStyle("A{$filaS}:K{$filaS}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB("010326");
            $hoja1->getStyle("A{$filaS}:K{$filaS}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
            $hoja1->getStyle("A{$filaS}:K{$filaS}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE);

            $hoja1->getStyle("B6:C" . $filaS)->getNumberFormat()->setFormatCode($formatoEntero);
            $hoja1->getStyle("D6:D" . $filaS)->getNumberFormat()->setFormatCode('#,##0.0');
            $hoja1->getStyle("E6:E" . $filaS)->getNumberFormat()->setFormatCode($formatoEntero);
            $hoja1->getStyle("F6:F" . $filaS)->getNumberFormat()->setFormatCode('#,##0.0');
            $hoja1->getStyle("G6:G" . $filaS)->getNumberFormat()->setFormatCode($formatoEntero);
            $hoja1->getStyle("H6:H" . $filaS)->getNumberFormat()->setFormatCode('#,##0.0');
            $hoja1->getStyle("I6:K" . $filaS)->getNumberFormat()->setFormatCode($formatoMoneda);

            foreach (range("A", "K") as $col) {
                $hoja1->getColumnDimension($col)->setAutoSize(true);
            }

            // ==========================================
            // HOJA 2: DETALLE DE CULTOS
            // ==========================================
            $hoja2 = $libro->createSheet();
            $hoja2->setTitle("Detalle Cultos");

            $hoja2->mergeCells("A1:J1");
            $hoja2->setCellValue("A1", "HISTORIAL DE SERVICIOS (" . $inicio->format("d/m/Y") . " - " . $fin->format("d/m/Y") . ")");
            $hoja2->getStyle("A1")->getFont()->setBold(true)->setSize(13)->getColor()->setRGB("FFFFFF");
            $hoja2->getStyle("A1:J1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB("010326");
            $hoja2->getStyle("A1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $encabezadosCultos = [
                "Fecha", "Servicio", "Adultos", "Niños", "Asistencia Total",
                "Ofrenda", "Diezmos", "Total Ingresos", "Registrado por", "Observaciones"
            ];
            $hoja2->fromArray($encabezadosCultos, null, "A3");
            $hoja2->getStyle("A3:J3")->getFont()->setBold(true)->getColor()->setRGB("FFFFFF");
            $hoja2->getStyle("A3:J3")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB("3061F2");
            $hoja2->freezePane("A4");

            $filaC = 4;
            foreach ($filas as $s) {
                $ad = (int)$s["asistencia_adultos"];
                $nin = (int)$s["asistencia_ninos"];
                $tot = (int)$s["asistencia_total"];
                $ofr = (float)$s["ofrenda"];
                $die = (float)$s["diezmos"];
                $ing = $ofr + $die;
                $filaValores = [
                    date("d/m/Y", strtotime($s["fecha"])),
                    ucfirst($s["tipo_servicio"]),
                    $ad,
                    $nin,
                    $tot,
                    $ofr,
                    $die,
                    $ing,
                    $s["registrado_por"] ?? "",
                    $s["observaciones"] ?? ""
                ];
                $hoja2->fromArray($filaValores, null, "A" . $filaC);
                $filaC++;
            }

            $ultimaFilaC = max(4, $filaC - 1);
            $hoja2->setAutoFilter("A3:J" . $ultimaFilaC);
            $hoja2->getStyle("F4:H" . $ultimaFilaC)->getNumberFormat()->setFormatCode($formatoMoneda);
            foreach (range("A", "J") as $col) {
                $hoja2->getColumnDimension($col)->setAutoSize(true);
            }
        }

        $libro->setActiveSheetIndex(0);
        $archivo = "reporte_" . $origen . "_" . $inicio->format("Ymd") . "_" . $fin->format("Ymd") . ".xlsx";

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header("Content-Disposition: attachment; filename=\"" . $archivo . "\"");
        header("Cache-Control: max-age=0");
        (new Xlsx($libro))->save("php://output");
        exit();
    }
}

// --------------------------------------------------
// PARÁMETROS
// --------------------------------------------------
$origen = $_GET["origen"] ?? "celulas";
if (!in_array($origen, ["celulas", "servicios"], true)) {
    $origen = "celulas";
}

$periodo = $_GET["periodo"] ?? "ultimos_3_meses";
if (!in_array($periodo, ["mes_actual", "ultimos_3_meses", "ultimo_anio", "semanal", "mensual", "trimestral", "anual", "personalizado"], true)) {
    $periodo = "ultimos_3_meses";
}

[$inicio, $fin] = rangoExportacion($periodo, $_GET["inicio"] ?? "", $_GET["fin"] ?? "");
$tipoServicio = $_GET["tipo_servicio"] ?? "";
if (!in_array($tipoServicio, ["", "martes", "jueves", "domingo"], true)) {
    $tipoServicio = "";
}

$sectorId = filter_input(INPUT_GET, "sector_id", FILTER_VALIDATE_INT) ?: 0;
$celulaId = filter_input(INPUT_GET, "celula_id", FILTER_VALIDATE_INT) ?: 0;

$sectores = $conexion->query("SELECT id, nombre FROM sectores WHERE activo = 1 ORDER BY nombre")->fetchAll();
$celulasDisponibles = $conexion->query("SELECT id, sector_id, nombre FROM celulas WHERE activo = 1 ORDER BY sector_id, nombre")->fetchAll();

$nombreSectorSeleccionado = "";
if ($sectorId > 0) {
    foreach ($sectores as $sec) {
        if ((int)$sec["id"] === $sectorId) {
            $nombreSectorSeleccionado = $sec["nombre"];
            break;
        }
    }
}

// --------------------------------------------------
// CONSULTAS Y PROCESAMIENTO DE DATOS
// --------------------------------------------------
$filas = [];
$sectoresAgrupados = [];
$totalesGlobales = [
    "reportes" => 0,
    "asistencia_total" => 0,
    "adultos" => 0,
    "jovenes" => 0,
    "ninos" => 0,
    "ofrenda_iglesia" => 0.0,
    "ofrenda_misiones" => 0.0,
    "ofrenda_bus" => 0.0,
    "ofrenda_total" => 0.0,
    "conversiones" => 0,
    "visitas" => 0,
    "bautismos" => 0,
];

$serviciosAgrupados = [
    "domingo" => ["tipo" => "Domingo", "cantidad" => 0, "adultos" => 0, "ninos" => 0, "total" => 0, "ofrenda" => 0.0, "diezmos" => 0.0, "ingresos" => 0.0],
    "jueves"  => ["tipo" => "Jueves", "cantidad" => 0, "adultos" => 0, "ninos" => 0, "total" => 0, "ofrenda" => 0.0, "diezmos" => 0.0, "ingresos" => 0.0],
    "martes"  => ["tipo" => "Martes", "cantidad" => 0, "adultos" => 0, "ninos" => 0, "total" => 0, "ofrenda" => 0.0, "diezmos" => 0.0, "ingresos" => 0.0],
];

$totalesServicios = [
    "cantidad" => 0,
    "adultos" => 0,
    "ninos" => 0,
    "asistencia_total" => 0,
    "ofrenda" => 0.0,
    "diezmos" => 0.0,
    "total_ingresos" => 0.0,
];

if ($origen === "celulas") {
    $sql = "SELECT r.*, 
                   s.id AS sector_id, s.nombre AS sector, 
                   c.id AS celula_id, c.nombre AS celula, c.tipo AS tipo_celula,
                   u.nombre_completo AS lider
            FROM reportes_lider r
            INNER JOIN celulas c ON c.id = r.celula_id
            INNER JOIN sectores s ON s.id = c.sector_id
            INNER JOIN usuarios u ON u.id = r.lider_id
            WHERE r.fecha_reporte BETWEEN ? AND ?";
    $parametros = [$inicio->format("Y-m-d"), $fin->format("Y-m-d")];

    if ($sectorId > 0) {
        $sql .= " AND s.id = ?";
        $parametros[] = $sectorId;
    }
    if ($celulaId > 0) {
        $sql .= " AND c.id = ?";
        $parametros[] = $celulaId;
    }
    $sql .= " ORDER BY s.nombre ASC, c.nombre ASC, r.fecha_reporte ASC";

    $consulta = $conexion->prepare($sql);
    $consulta->execute($parametros);
    $filas = $consulta->fetchAll();

    foreach ($filas as $r) {
        $sId = (int)$r["sector_id"];
        $cId = (int)$r["celula_id"];

        if (!isset($sectoresAgrupados[$sId])) {
            $sectoresAgrupados[$sId] = [
                "id" => $sId,
                "nombre" => $r["sector"],
                "celulas" => [],
                "totales" => [
                    "reportes" => 0,
                    "asistencia_total" => 0,
                    "adultos" => 0,
                    "jovenes" => 0,
                    "ninos" => 0,
                    "ofrenda_iglesia" => 0.0,
                    "ofrenda_misiones" => 0.0,
                    "ofrenda_bus" => 0.0,
                    "ofrenda_total" => 0.0,
                    "conversiones" => 0,
                    "visitas" => 0,
                    "bautismos" => 0,
                ],
            ];
        }

        if (!isset($sectoresAgrupados[$sId]["celulas"][$cId])) {
            $sectoresAgrupados[$sId]["celulas"][$cId] = [
                "id" => $cId,
                "nombre" => $r["celula"],
                "lider" => $r["lider"],
                "tipo_celula" => $r["tipo_celula"] ?? "adultos",
                "semanas" => 0,
                "asistencia_total" => 0,
                "adultos" => 0,
                "jovenes" => 0,
                "ninos" => 0,
                "ofrenda_iglesia" => 0.0,
                "ofrenda_misiones" => 0.0,
                "ofrenda_bus" => 0.0,
                "ofrenda_total" => 0.0,
                "conversiones" => 0,
                "visitas" => 0,
                "bautismos" => 0,
            ];
        }

        $cRef = &$sectoresAgrupados[$sId]["celulas"][$cId];
        $cRef["semanas"]++;
        $asist = (int)$r["total_asistencia"];
        $ad = (int)($r["adultos_amigos"] + $r["adultos_hermanos"]);
        $jov = (int)($r["jovenes_amigos"] + $r["jovenes_hermanos"]);
        $nin = (int)$r["ninos"];

        $cRef["asistencia_total"] += $asist;
        $cRef["adultos"] += $ad;
        $cRef["jovenes"] += $jov;
        $cRef["ninos"] += $nin;
        $cRef["ofrenda_iglesia"] += (float)$r["ofrenda_iglesia"];
        $cRef["ofrenda_misiones"] += (float)$r["ofrenda_misiones"];
        $cRef["ofrenda_bus"] += (float)$r["ofrenda_bus"];
        $cRef["ofrenda_total"] += (float)$r["ofrenda_total"];
        $cRef["conversiones"] += (int)$r["conversion"];
        $cRef["visitas"] += (int)$r["visita_programada"];
        $cRef["bautismos"] += (int)$r["bautismo_agua"];

        $sRef = &$sectoresAgrupados[$sId]["totales"];
        $sRef["reportes"]++;
        $sRef["asistencia_total"] += $asist;
        $sRef["adultos"] += $ad;
        $sRef["jovenes"] += $jov;
        $sRef["ninos"] += $nin;
        $sRef["ofrenda_iglesia"] += (float)$r["ofrenda_iglesia"];
        $sRef["ofrenda_misiones"] += (float)$r["ofrenda_misiones"];
        $sRef["ofrenda_bus"] += (float)$r["ofrenda_bus"];
        $sRef["ofrenda_total"] += (float)$r["ofrenda_total"];
        $sRef["conversiones"] += (int)$r["conversion"];
        $sRef["visitas"] += (int)$r["visita_programada"];
        $sRef["bautismos"] += (int)$r["bautismo_agua"];

        $totalesGlobales["reportes"]++;
        $totalesGlobales["asistencia_total"] += $asist;
        $totalesGlobales["adultos"] += $ad;
        $totalesGlobales["jovenes"] += $jov;
        $totalesGlobales["ninos"] += $nin;
        $totalesGlobales["ofrenda_iglesia"] += (float)$r["ofrenda_iglesia"];
        $totalesGlobales["ofrenda_misiones"] += (float)$r["ofrenda_misiones"];
        $totalesGlobales["ofrenda_bus"] += (float)$r["ofrenda_bus"];
        $totalesGlobales["ofrenda_total"] += (float)$r["ofrenda_total"];
        $totalesGlobales["conversiones"] += (int)$r["conversion"];
        $totalesGlobales["visitas"] += (int)$r["visita_programada"];
        $totalesGlobales["bautismos"] += (int)$r["bautismo_agua"];
    }
    unset($cRef, $sRef);

} else {
    $sql = "SELECT s.*, u.nombre_completo AS registrado_por 
            FROM servicios_iglesia s 
            LEFT JOIN usuarios u ON u.id = s.registrado_por 
            WHERE s.fecha BETWEEN ? AND ?";
    $parametros = [$inicio->format("Y-m-d"), $fin->format("Y-m-d")];

    if ($tipoServicio !== "") {
        $sql .= " AND s.tipo_servicio = ?";
        $parametros[] = $tipoServicio;
    }
    $sql .= " ORDER BY s.fecha ASC";

    $consulta = $conexion->prepare($sql);
    $consulta->execute($parametros);
    $filas = $consulta->fetchAll();

    foreach ($filas as $s) {
        $tipo = $s["tipo_servicio"];
        if (!isset($serviciosAgrupados[$tipo])) {
            $serviciosAgrupados[$tipo] = ["tipo" => ucfirst($tipo), "cantidad" => 0, "adultos" => 0, "ninos" => 0, "total" => 0, "ofrenda" => 0.0, "diezmos" => 0.0, "ingresos" => 0.0];
        }
        $ad = (int)$s["asistencia_adultos"];
        $nin = (int)$s["asistencia_ninos"];
        $tot = (int)$s["asistencia_total"];
        $ofr = (float)$s["ofrenda"];
        $die = (float)$s["diezmos"];
        $ing = $ofr + $die;

        $serviciosAgrupados[$tipo]["cantidad"]++;
        $serviciosAgrupados[$tipo]["adultos"] += $ad;
        $serviciosAgrupados[$tipo]["ninos"] += $nin;
        $serviciosAgrupados[$tipo]["total"] += $tot;
        $serviciosAgrupados[$tipo]["ofrenda"] += $ofr;
        $serviciosAgrupados[$tipo]["diezmos"] += $die;
        $serviciosAgrupados[$tipo]["ingresos"] += $ing;

        $totalesServicios["cantidad"]++;
        $totalesServicios["adultos"] += $ad;
        $totalesServicios["ninos"] += $nin;
        $totalesServicios["asistencia_total"] += $tot;
        $totalesServicios["ofrenda"] += $ofr;
        $totalesServicios["diezmos"] += $die;
        $totalesServicios["total_ingresos"] += $ing;
    }
}

if (isset($_GET["descargar"])) {
    exportarExcel(
        $origen,
        $filas,
        $inicio,
        $fin,
        $sectoresAgrupados,
        $totalesGlobales,
        $serviciosAgrupados,
        $totalesServicios,
        $nombreSectorSeleccionado
    );
}

abrirPaginaAdmin("exportar", "Descarga de reportes");
?>

<style>
.is-hidden {
  display: none !important;
}
.tr-subtotal {
  background-color: #f1f5f9;
  font-weight: 700;
  color: var(--night);
}
.tr-subtotal td {
  border-top: 1px solid #cbd5e1 !important;
  border-bottom: 1px solid #cbd5e1 !important;
}
.tr-global {
  background: linear-gradient(135deg, #010326, #020540);
  color: #ffffff !important;
  font-weight: 800;
  font-size: 13.5px;
}
.tr-global td {
  color: #ffffff !important;
  border-top: 2px solid #3061f2 !important;
  border-bottom: 2px solid #3061f2 !important;
}
.money-cell {
  font-family: monospace;
  font-size: 13px;
}
</style>

<!-- FILTROS -->
<section class="card card-flush">
    <div class="card-head">
        <div>
            <p class="eyebrow">Reportes descargables</p>
            <h3>Excel de servicios y células</h3>
        </div>
    </div>

    <form method="get" class="stats-filters" id="formInformes">
        <div>
            <label for="origen">Origen</label>
            <select id="origen" name="origen" onchange="cambiarFiltrosYActualizar()">
                <option value="celulas" <?= $origen === "celulas" ? "selected" : "" ?>>Reporte de células</option>
                <option value="servicios" <?= $origen === "servicios" ? "selected" : "" ?>>Servicios de iglesia</option>
            </select>
        </div>

        <div>
            <label for="periodo">Periodo</label>
            <select id="periodo" name="periodo" onchange="cambiarPeriodoYActualizar()">
                <option value="mes_actual" <?= in_array($periodo, ["mes_actual", "mensual"]) ? "selected" : "" ?>>Mes actual</option>
                <option value="ultimos_3_meses" <?= in_array($periodo, ["ultimos_3_meses", "trimestral"]) ? "selected" : "" ?>>Últimos 3 meses</option>
                <option value="ultimo_anio" <?= in_array($periodo, ["ultimo_anio", "anual"]) ? "selected" : "" ?>>Último año</option>
                <option value="personalizado" <?= $periodo === "personalizado" ? "selected" : "" ?>>Personalizado</option>
            </select>
        </div>

        <div id="caja-rango" class="stats-custom-range <?= $periodo === 'personalizado' ? 'visible' : '' ?>">
            <div>
                <label for="inicio">Desde</label>
                <input id="inicio" type="date" name="inicio" value="<?= e($inicio->format("Y-m-d")) ?>" onchange="document.getElementById('formInformes').submit()">
            </div>
            <div>
                <label for="fin">Hasta</label>
                <input id="fin" type="date" name="fin" value="<?= e($fin->format("Y-m-d")) ?>" onchange="document.getElementById('formInformes').submit()">
            </div>
        </div>

        <div id="caja-sector" class="<?= $origen === 'celulas' ? '' : 'is-hidden' ?>">
            <label for="sector_id">Sector</label>
            <select id="sector_id" name="sector_id" onchange="cambiarSectorYActualizar()">
                <option value="0">Todos los sectores</option>
                <?php foreach ($sectores as $sec): ?>
                    <option value="<?= $sec["id"] ?>" <?= $sectorId === (int)$sec["id"] ? "selected" : "" ?>>
                        <?= e($sec["nombre"]) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="caja-celula" class="<?= ($origen === 'celulas' && $sectorId > 0) ? '' : 'is-hidden' ?>">
            <label for="celula_id">Célula</label>
            <select id="celula_id" name="celula_id" onchange="document.getElementById('formInformes').submit()">
                <option value="0">Todas las células</option>
                <?php foreach ($celulasDisponibles as $cel): ?>
                    <option value="<?= $cel["id"] ?>" data-sector="<?= $cel["sector_id"] ?>" <?= $celulaId === (int)$cel["id"] ? "selected" : "" ?>>
                        <?= e($cel["nombre"]) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="caja-servicio" class="<?= $origen === 'servicios' ? '' : 'is-hidden' ?>">
            <label for="tipo_servicio">Servicio</label>
            <select id="tipo_servicio" name="tipo_servicio" onchange="document.getElementById('formInformes').submit()">
                <option value="">Todos los servicios</option>
                <option value="domingo" <?= $tipoServicio === "domingo" ? "selected" : "" ?>>Domingo</option>
                <option value="jueves" <?= $tipoServicio === "jueves" ? "selected" : "" ?>>Jueves</option>
                <option value="martes" <?= $tipoServicio === "martes" ? "selected" : "" ?>>Martes</option>
            </select>
        </div>

        <button class="btn btn-gold" type="submit" name="descargar" value="1">
            <i class="ri-file-excel-2-line"></i> Descargar Excel
        </button>
    </form>
</section>

<!-- TARJETAS RESUMEN -->
<?php if ($origen === "celulas"): ?>
    <div class="grid-4">
        <div class="stat">
            <div class="label">Asistencia acumulada</div>
            <div class="value"><?= number_format($totalesGlobales["asistencia_total"]) ?></div>
            <small class="muted"><?= $totalesGlobales["reportes"] ?> reuniones registradas</small>
        </div>
        <div class="stat">
            <div class="label">Total ofrendas células</div>
            <div class="value">$<?= number_format($totalesGlobales["ofrenda_total"], 2) ?></div>
            <small class="muted">Iglesia: $<?= number_format($totalesGlobales["ofrenda_iglesia"], 2) ?> | Misiones: $<?= number_format($totalesGlobales["ofrenda_misiones"], 2) ?></small>
        </div>
        <div class="stat">
            <div class="label">Conversiones</div>
            <div class="value"><?= number_format($totalesGlobales["conversiones"]) ?></div>
        </div>
        <div class="stat">
            <div class="label">Bautismos</div>
            <div class="value"><?= number_format($totalesGlobales["bautismos"]) ?></div>
        </div>
    </div>
<?php else: ?>
    <div class="grid-4">
        <div class="stat">
            <div class="label">Cultos realizados</div>
            <div class="value"><?= number_format($totalesServicios["cantidad"]) ?></div>
            <small class="muted">En el periodo</small>
        </div>
        <div class="stat">
            <div class="label">Asistencia total</div>
            <div class="value"><?= number_format($totalesServicios["asistencia_total"]) ?></div>
            <small class="muted">Adultos: <?= number_format($totalesServicios["adultos"]) ?> | Niños: <?= number_format($totalesServicios["ninos"]) ?></small>
        </div>
        <div class="stat">
            <div class="label">Total ofrendas</div>
            <div class="value">$<?= number_format($totalesServicios["ofrenda"], 2) ?></div>
            <small class="muted">Cultos generales</small>
        </div>
        <div class="stat">
            <div class="label">Total ingresos</div>
            <div class="value">$<?= number_format($totalesServicios["total_ingresos"], 2) ?></div>
            <small class="muted">Ofrendas + Diezmos ($<?= number_format($totalesServicios["diezmos"], 2) ?>)</small>
        </div>
    </div>
<?php endif; ?>

<!-- TABLA CONSOLIDADA -->
<section class="card">
    <div class="card-head">
        <div>
            <span class="badge ok"><?= $inicio->format("d/m/Y") ?> – <?= $fin->format("d/m/Y") ?></span>
            <h3 style="margin-top: 6px;">
                <?= $origen === "celulas" 
                    ? ($sectorId > 0 ? "Consolidado de Células - Sector " . e($nombreSectorSeleccionado) : "Consolidado de Células")
                    : "Consolidado de Servicios" ?>
            </h3>
        </div>
        <a class="btn btn-gold" href="exportar-reportes.php?<?= e(http_build_query(array_merge($_GET, ["descargar" => 1]))) ?>">
            <i class="ri-file-excel-2-line"></i> Descargar Excel
        </a>
    </div>

    <div class="table-wrap">
        <?php if ($origen === "celulas"): ?>
            <table>
                <thead>
                    <tr>
                        <th>Sector</th>
                        <th>Célula</th>
                        <th>Líder</th>
                        <th>Tipo</th>
                        <th style="text-align: right;">Semanas</th>
                        <th style="text-align: right;">Asist. Total</th>
                        <th style="text-align: right;">Promedio</th>
                        <th style="text-align: right;">Ofrenda Iglesia</th>
                        <th style="text-align: right;">Misiones</th>
                        <th style="text-align: right;">Transporte</th>
                        <th style="text-align: right;">Total Ofrendas</th>
                        <th style="text-align: right;">Conv.</th>
                        <th style="text-align: right;">Cand. Bautismo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sectoresAgrupados)): ?>
                        <tr>
                            <td colspan="13" class="empty">No hay reportes de células registrados en el periodo seleccionado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sectoresAgrupados as $sData): ?>
                            <?php foreach ($sData["celulas"] as $c): ?>
                                <?php $prom = $c["semanas"] > 0 ? round($c["asistencia_total"] / $c["semanas"], 1) : 0; ?>
                                <tr>
                                    <td data-label="Sector"><?= e($sData["nombre"]) ?></td>
                                    <td data-label="Célula"><strong><?= e($c["nombre"]) ?></strong></td>
                                    <td data-label="Líder"><?= e($c["lider"]) ?></td>
                                    <td data-label="Tipo"><span class="badge"><?= e(ucfirst($c["tipo_celula"])) ?></span></td>
                                    <td data-label="Semanas" style="text-align: right;"><?= $c["semanas"] ?></td>
                                    <td data-label="Asist. Total" style="text-align: right;"><strong><?= number_format($c["asistencia_total"]) ?></strong></td>
                                    <td data-label="Promedio" style="text-align: right;"><?= $prom ?></td>
                                    <td data-label="Ofrenda Iglesia" class="money-cell" style="text-align: right;">$<?= number_format($c["ofrenda_iglesia"], 2) ?></td>
                                    <td data-label="Misiones" class="money-cell" style="text-align: right;">$<?= number_format($c["ofrenda_misiones"], 2) ?></td>
                                    <td data-label="Transporte" class="money-cell" style="text-align: right;">$<?= number_format($c["ofrenda_bus"], 2) ?></td>
                                    <td data-label="Total Ofrendas" class="money-cell" style="text-align: right;"><strong>$<?= number_format($c["ofrenda_total"], 2) ?></strong></td>
                                    <td data-label="Conversiones" style="text-align: right;"><?= $c["conversiones"] ?></td>
                                    <td data-label="Bautismos" style="text-align: right;"><?= $c["bautismos"] ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Subtotal del Sector -->
                            <tr class="tr-subtotal">
                                <td colspan="4">SUBTOTAL <?= mb_strtoupper(e($sData["nombre"])) ?></td>
                                <td style="text-align: right;"><?= $sData["totales"]["reportes"] ?></td>
                                <td style="text-align: right;"><?= number_format($sData["totales"]["asistencia_total"]) ?></td>
                                <td style="text-align: right;">
                                    <?= $sData["totales"]["reportes"] > 0 ? round($sData["totales"]["asistencia_total"] / $sData["totales"]["reportes"], 1) : 0 ?>
                                </td>
                                <td class="money-cell" style="text-align: right;">$<?= number_format($sData["totales"]["ofrenda_iglesia"], 2) ?></td>
                                <td class="money-cell" style="text-align: right;">$<?= number_format($sData["totales"]["ofrenda_misiones"], 2) ?></td>
                                <td class="money-cell" style="text-align: right;">$<?= number_format($sData["totales"]["ofrenda_bus"], 2) ?></td>
                                <td class="money-cell" style="text-align: right;">$<?= number_format($sData["totales"]["ofrenda_total"], 2) ?></td>
                                <td style="text-align: right;"><?= $sData["totales"]["conversiones"] ?></td>
                                <td style="text-align: right;"><?= $sData["totales"]["bautismos"] ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <!-- Totalización Global -->
                        <tr class="tr-global">
                            <td colspan="4">TOTAL GENERAL</td>
                            <td style="text-align: right;"><?= $totalesGlobales["reportes"] ?></td>
                            <td style="text-align: right;"><?= number_format($totalesGlobales["asistencia_total"]) ?></td>
                            <td style="text-align: right;">
                                <?= $totalesGlobales["reportes"] > 0 ? round($totalesGlobales["asistencia_total"] / $totalesGlobales["reportes"], 1) : 0 ?>
                            </td>
                            <td class="money-cell" style="text-align: right;">$<?= number_format($totalesGlobales["ofrenda_iglesia"], 2) ?></td>
                            <td class="money-cell" style="text-align: right;">$<?= number_format($totalesGlobales["ofrenda_misiones"], 2) ?></td>
                            <td class="money-cell" style="text-align: right;">$<?= number_format($totalesGlobales["ofrenda_bus"], 2) ?></td>
                            <td class="money-cell" style="text-align: right;">$<?= number_format($totalesGlobales["ofrenda_total"], 2) ?></td>
                            <td style="text-align: right;"><?= $totalesGlobales["conversiones"] ?></td>
                            <td style="text-align: right;"><?= $totalesGlobales["bautismos"] ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Servicio</th>
                        <th style="text-align: right;">Cultos</th>
                        <th style="text-align: right;">Adultos</th>
                        <th style="text-align: right;">Prom. Adultos</th>
                        <th style="text-align: right;">Niños</th>
                        <th style="text-align: right;">Prom. Niños</th>
                        <th style="text-align: right;">Asistencia Total</th>
                        <th style="text-align: right;">Promedio</th>
                        <th style="text-align: right;">Ofrendas</th>
                        <th style="text-align: right;">Diezmos</th>
                        <th style="text-align: right;">Total Ingresos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($filas)): ?>
                        <tr>
                            <td colspan="11" class="empty">No hay servicios registrados en el periodo seleccionado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($serviciosAgrupados as $item): ?>
                            <?php 
                                $promAd = $item["cantidad"] > 0 ? round($item["adultos"] / $item["cantidad"], 1) : 0;
                                $promNin = $item["cantidad"] > 0 ? round($item["ninos"] / $item["cantidad"], 1) : 0;
                                $promTot = $item["cantidad"] > 0 ? round($item["total"] / $item["cantidad"], 1) : 0;
                            ?>
                            <tr>
                                <td data-label="Servicio"><strong><?= e($item["tipo"]) ?></strong></td>
                                <td data-label="Cultos" style="text-align: right;"><?= $item["cantidad"] ?></td>
                                <td data-label="Adultos" style="text-align: right;"><?= number_format($item["adultos"]) ?></td>
                                <td data-label="Prom. Adultos" style="text-align: right;"><?= $promAd ?></td>
                                <td data-label="Niños" style="text-align: right;"><?= number_format($item["ninos"]) ?></td>
                                <td data-label="Prom. Niños" style="text-align: right;"><?= $promNin ?></td>
                                <td data-label="Total" style="text-align: right;"><strong><?= number_format($item["total"]) ?></strong></td>
                                <td data-label="Promedio" style="text-align: right;"><?= $promTot ?></td>
                                <td data-label="Ofrendas" class="money-cell" style="text-align: right;">$<?= number_format($item["ofrenda"], 2) ?></td>
                                <td data-label="Diezmos" class="money-cell" style="text-align: right;">$<?= number_format($item["diezmos"], 2) ?></td>
                                <td data-label="Total Ingresos" class="money-cell" style="text-align: right;"><strong>$<?= number_format($item["ingresos"], 2) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>

                        <!-- Total General -->
                        <tr class="tr-global">
                            <td>TOTAL GENERAL</td>
                            <td style="text-align: right;"><?= $totalesServicios["cantidad"] ?></td>
                            <td style="text-align: right;"><?= number_format($totalesServicios["adultos"]) ?></td>
                            <td style="text-align: right;">
                                <?= $totalesServicios["cantidad"] > 0 ? round($totalesServicios["adultos"] / $totalesServicios["cantidad"], 1) : 0 ?>
                            </td>
                            <td style="text-align: right;"><?= number_format($totalesServicios["ninos"]) ?></td>
                            <td style="text-align: right;">
                                <?= $totalesServicios["cantidad"] > 0 ? round($totalesServicios["ninos"] / $totalesServicios["cantidad"], 1) : 0 ?>
                            </td>
                            <td style="text-align: right;"><?= number_format($totalesServicios["asistencia_total"]) ?></td>
                            <td style="text-align: right;">
                                <?= $totalesServicios["cantidad"] > 0 ? round($totalesServicios["asistencia_total"] / $totalesServicios["cantidad"], 1) : 0 ?>
                            </td>
                            <td class="money-cell" style="text-align: right;">$<?= number_format($totalesServicios["ofrenda"], 2) ?></td>
                            <td class="money-cell" style="text-align: right;">$<?= number_format($totalesServicios["diezmos"], 2) ?></td>
                            <td class="money-cell" style="text-align: right;">$<?= number_format($totalesServicios["total_ingresos"], 2) ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>

<!-- DETALLE CRONOLÓGICO -->
<section class="card">
    <details>
        <summary style="cursor: pointer; padding: 14px 18px; font-weight: 700; color: var(--night); outline: none;">
            <i class="ri-list-check-2"></i> Ver detalle individual (<?= count($filas) ?> registros)
        </summary>
        <div style="padding: 16px 18px;">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th><?= $origen === "servicios" ? "Culto" : "Sector / Célula" ?></th>
                            <th><?= $origen === "servicios" ? "Adultos" : "Líder" ?></th>
                            <th><?= $origen === "servicios" ? "Niños" : "Asistencia" ?></th>
                            <th><?= $origen === "servicios" ? "Ofrendas" : "Ofrenda Total" ?></th>
                            <th><?= $origen === "servicios" ? "Diezmos" : "Resultados" ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($filas)): ?>
                            <tr><td colspan="6" class="empty">No hay registros detallados en este periodo.</td></tr>
                        <?php else: ?>
                            <?php foreach (array_slice($filas, 0, 50) as $f): ?>
                                <tr>
                                    <td data-label="Fecha"><?= date("d/m/Y", strtotime($f[$origen === "servicios" ? "fecha" : "fecha_reporte"])) ?></td>
                                    <td data-label="Origen">
                                        <?= $origen === "servicios" 
                                            ? e(ucfirst($f["tipo_servicio"])) 
                                            : "<strong>" . e($f["sector"]) . "</strong> · " . e($f["celula"]) ?>
                                    </td>
                                    <td data-label="Dato 1">
                                        <?= $origen === "servicios" 
                                            ? number_format((int)$f["asistencia_adultos"]) 
                                            : e($f["lider"]) ?>
                                    </td>
                                    <td data-label="Dato 2">
                                        <?= $origen === "servicios" 
                                            ? number_format((int)$f["asistencia_ninos"]) 
                                            : "<strong>" . number_format((int)$f["total_asistencia"]) . "</strong>" ?>
                                    </td>
                                    <td data-label="Ofrenda" class="money-cell">
                                        $<?= number_format($f[$origen === "servicios" ? "ofrenda" : "ofrenda_total"], 2) ?>
                                    </td>
                                    <td data-label="Dato 3">
                                        <?php if ($origen === "servicios"): ?>
                                            <span class="money-cell">$<?= number_format($f["diezmos"], 2) ?></span>
                                        <?php else: ?>
                                            <span style="font-size: 11.5px;">
                                                Conv: <?= (int)$f["conversion"] ?> | Vis: <?= (int)$f["visita_programada"] ?> | Cand: <?= (int)$f["bautismo_agua"] ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($filas) > 50): ?>
                <p class="form-help muted" style="margin-top: 10px;">
                    Mostrando los primeros 50 registros. El archivo Excel incluye los <?= count($filas) ?> registros completos.
                </p>
            <?php endif; ?>
        </div>
    </details>
</section>

<script>
function cambiarFiltrosYActualizar() {
    actualizarVisibilidad();
    document.getElementById('formInformes').submit();
}

function cambiarPeriodoYActualizar() {
    const periodo = document.getElementById('periodo').value;
    actualizarVisibilidad();
    if (periodo !== 'personalizado') {
        document.getElementById('formInformes').submit();
    }
}

function cambiarSectorYActualizar() {
    actualizarCelulas();
    document.getElementById('formInformes').submit();
}

function actualizarVisibilidad() {
    const origen = document.getElementById('origen').value;
    const periodo = document.getElementById('periodo').value;

    const cajaRango = document.getElementById('caja-rango');
    const cajaSector = document.getElementById('caja-sector');
    const cajaCelula = document.getElementById('caja-celula');
    const cajaServicio = document.getElementById('caja-servicio');

    if (periodo === 'personalizado') {
        cajaRango.classList.add('visible');
    } else {
        cajaRango.classList.remove('visible');
    }

    if (origen === 'servicios') {
        cajaSector.classList.add('is-hidden');
        cajaCelula.classList.add('is-hidden');
        cajaServicio.classList.remove('is-hidden');
    } else {
        cajaSector.classList.remove('is-hidden');
        cajaServicio.classList.add('is-hidden');
        actualizarCelulas();
    }
}

function actualizarCelulas() {
    const origen = document.getElementById('origen').value;
    const sectorId = parseInt(document.getElementById('sector_id').value, 10) || 0;
    const cajaCelula = document.getElementById('caja-celula');
    const selectCelula = document.getElementById('celula_id');

    if (origen !== 'celulas' || sectorId === 0) {
        cajaCelula.classList.add('is-hidden');
        selectCelula.value = '0';
        return;
    }

    cajaCelula.classList.remove('is-hidden');

    const opciones = selectCelula.querySelectorAll('option');
    opciones.forEach(opt => {
        const optSector = parseInt(opt.dataset.sector, 10) || 0;
        if (opt.value === '0' || optSector === sectorId) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    });

    const opcionActual = selectCelula.querySelector(`option[value="${selectCelula.value}"]`);
    if (opcionActual && opcionActual.style.display === 'none') {
        selectCelula.value = '0';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    actualizarVisibilidad();
});
</script>

<?php cerrarPaginaAdmin(); ?>
