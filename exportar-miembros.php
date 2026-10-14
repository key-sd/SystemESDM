<?php
// ==============================================================================
// EXPORTAR PADRÓN DE MIEMBROS A PDF (DESCARGA AUTOMÁTICA DIRECTA)
// ==============================================================================

require_once __DIR__ . "/includes/conexion.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/funciones.php";
require_once __DIR__ . "/includes/fpdf.php";

requiereRol(['admin', 'supervisor', 'lider']);

$rol = $_SESSION['rol'];
$usuarioId = (int) $_SESSION['usuario_id'];

// Determinar qué célula se solicita
$celulaId = (int) ($_GET['celula_id'] ?? 0);

if ($rol === 'lider') {
    // El líder solo puede consultar su propia célula asignada
    $celulaId = (int) ($_SESSION['celula_id'] ?? 0);
    if ($celulaId <= 0) {
        http_response_code(400);
        die("No tienes una célula asignada.");
    }
} elseif ($celulaId <= 0) {
    http_response_code(400);
    die("Debe especificar una célula válida.");
}

// Consultar datos de la célula, sector, líder y supervisor
$stmt = $conexion->prepare('
    SELECT 
        c.id AS celula_id,
        c.nombre AS celula_nombre,
        c.tipo AS celula_tipo,
        c.direccion AS celula_direccion,
        s.id AS sector_id,
        s.nombre AS sector_nombre,
        u_lider.nombre_completo AS lider_nombre,
        u_lider.usuario AS lider_usuario,
        u_sup.nombre_completo AS supervisor_nombre,
        u_sup.usuario AS supervisor_usuario
    FROM celulas c
    INNER JOIN sectores s ON s.id = c.sector_id
    LEFT JOIN usuarios u_lider ON u_lider.id = c.lider_id
    LEFT JOIN usuarios u_sup ON u_sup.id = s.supervisor_id
    WHERE c.id = ?
');
$stmt->execute([$celulaId]);
$celula = $stmt->fetch();

if (!$celula) {
    http_response_code(404);
    die("La célula solicitada no existe.");
}

// Validación de seguridad para supervisores: solo células de su propio sector
if ($rol === 'supervisor') {
    $sectorSupervisor = (int) ($_SESSION['sector_id'] ?? 0);
    if ((int) $celula['sector_id'] !== $sectorSupervisor) {
        http_response_code(403);
        die("No tienes permiso para consultar esta célula.");
    }
}

// Consultar lista de miembros (solo los campos necesarios)
$stmtMiembros = $conexion->prepare('
    SELECT id, nombre_completo, fecha_nacimiento, activo
    FROM miembros_celula 
    WHERE celula_id = ? 
    ORDER BY activo DESC, nombre_completo ASC
');
$stmtMiembros->execute([$celulaId]);
$miembros = $stmtMiembros->fetchAll();

// ==============================================================================
// SECCIÓN DE TEXTOS EDITABLES DEL PDF (Puedes modificar estos textos fácilmente)
// ==============================================================================
$textoInstitucion = "MISIÓN CRISTIANA ELIM SANTIAGO DE MARÍA";
$textoTituloDoc   = "PADRÓN OFICIAL DE MIEMBROS DE CÉLULA";
$textoSubtitulo   = "Control Celular y Registro de Cumpleaños";
$textoColumna1    = "N°";
$textoColumna2    = "Nombre Completo";
$textoColumna3    = "Cumpleaños";
$textoSinMiembros = "Esta célula aún no tiene miembros registrados en el sistema.";
$textoPiePagina   = "Elim Santiago de María · Misión y Trabajo Celular";
// ==============================================================================

// Función auxiliar para codificar texto a ISO-8859-1 (soporta tildes y ñ en FPDF)
function txt(string $cadena): string
{
    return mb_convert_encoding($cadena, 'ISO-8859-1', 'UTF-8');
}

// Formatear cumpleaños en español
function formatoCumpleanos(?string $fecha): string
{
    if (empty($fecha) || $fecha === '0000-00-00') {
        return 'Sin registrar';
    }
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $fecha);
    if (!$dt) {
        return (string) $fecha;
    }

    $dia = (int) $dt->format('d');
    $mes = (int) $dt->format('m');
    $hoy = new DateTimeImmutable('today');
    $edad = $hoy->diff($dt)->y;

    if ($edad > 0 && $edad < 120) {
        return sprintf('%d de %s (%d años)', $dia, $meses[$mes] ?? '', $edad);
    }
    return sprintf('%d de %s', $dia, $meses[$mes] ?? '');
}

$etiquetaTipo = match ($celula['celula_tipo']) {
    'jovenes' => 'Jóvenes',
    'ninos' => 'Infantil',
    default => 'Adultos',
};

// ==============================================================================
// CLASE DEL DOCUMENTO PDF
// ==============================================================================
class PDF_Miembros extends FPDF
{
    public string $pieTexto = '';

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(110, 110, 110);
        $texto = $this->pieTexto . '  ·  Página ' . $this->PageNo() . ' de {nb}';
        $this->Cell(0, 10, mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
    }
}

$pdf = new PDF_Miembros('P', 'mm', 'Letter');
$pdf->pieTexto = $textoPiePagina;
$pdf->AliasNbPages();
$pdf->SetMargins(14, 14, 14);
$pdf->SetAutoPageBreak(true, 18);
$pdf->AddPage();

// 1. LOGO Y ENCABEZADO
$rutaLogo = __DIR__ . '/img/logoOscuro.png';
$hayLogo = file_exists($rutaLogo);

if ($hayLogo) {
    $pdf->Image($rutaLogo, 14, 13, 34);
    $pdf->SetXY(52, 13);
} else {
    $pdf->SetXY(14, 13);
}

// Título de la Institución
$pdf->SetFont('Arial', 'B', 12.5);
$pdf->SetTextColor(1, 3, 38); // Azul profundo #010326
$pdf->Cell(0, 5.5, txt($textoInstitucion), 0, 1);

if ($hayLogo) {
    $pdf->SetX(52);
}
// Título del Reporte
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(30, 58, 138); // Azul medio #1e3a8a
$pdf->Cell(0, 5, txt($textoTituloDoc), 0, 1);

if ($hayLogo) {
    $pdf->SetX(52);
}
// Subtítulo
$pdf->SetFont('Arial', '', 8.5);
$pdf->SetTextColor(100, 116, 139); // Gris texto
$pdf->Cell(0, 4.5, txt($textoSubtitulo . ' · Emisión: ' . date('d/m/Y h:i A')), 0, 1);

// Línea divisoria decorativa
$pdf->Ln(4);
$pdf->SetDrawColor(30, 58, 138);
$pdf->SetLineWidth(0.6);
$pdf->Line(14, $pdf->GetY(), 202, $pdf->GetY());
$pdf->Ln(4);

// 2. FICHA TÉCNICA DE LA CÉLULA (Recuadro organizado)
$anchoCaja = 188; // 215.9 - 28 aprox
$pdf->SetFillColor(248, 250, 252); // Fondo tenue #f8fafc
$pdf->SetDrawColor(203, 213, 225); // Borde #cbd5e1
$pdf->SetLineWidth(0.3);

$yInicioFicha = $pdf->GetY();
$altoFicha = !empty($celula['celula_direccion']) ? 22 : 16;
$pdf->Rect(14, $yInicioFicha, $anchoCaja, $altoFicha, 'DF');

// Fila 1: Célula y Sector
$pdf->SetXY(17, $yInicioFicha + 2.5);
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(100, 116, 139);
$pdf->Write(5, txt("CÉLULA: "));
$pdf->SetFont('Arial', 'B', 9.5);
$pdf->SetTextColor(1, 3, 38);
$pdf->Write(5, txt($celula['celula_nombre'] . " (" . $etiquetaTipo . ")"));

$pdf->SetXY(110, $yInicioFicha + 2.5);
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(100, 116, 139);
$pdf->Write(5, txt("SECTOR: "));
$pdf->SetFont('Arial', '', 9.5);
$pdf->SetTextColor(1, 3, 38);
$pdf->Write(5, txt($celula['sector_nombre']));

// Fila 2: Líder y Supervisor
$pdf->SetXY(17, $yInicioFicha + 8.5);
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(100, 116, 139);
$pdf->Write(5, txt("LÍDER: "));
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(1, 3, 38);
$pdf->Write(5, txt($celula['lider_nombre'] ?? 'Sin líder asignado'));

$pdf->SetXY(110, $yInicioFicha + 8.5);
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(100, 116, 139);
$pdf->Write(5, txt("SUPERVISOR: "));
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(1, 3, 38);
$pdf->Write(5, txt($celula['supervisor_nombre'] ?? 'Sin asignar'));

// Fila 3: Dirección (si existe)
if (!empty($celula['celula_direccion'])) {
    $pdf->SetXY(17, $yInicioFicha + 14.5);
    $pdf->SetFont('Arial', 'B', 8.5);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Write(5, txt("DIRECCIÓN: "));
    $pdf->SetFont('Arial', '', 8.5);
    $pdf->SetTextColor(51, 65, 85);
    $pdf->Write(5, txt($celula['celula_direccion']));
}

$pdf->SetY($yInicioFicha + $altoFicha + 4);

// Encabezado de la lista con total
$pdf->SetFont('Arial', 'B', 9.5);
$pdf->SetTextColor(1, 3, 38);
$pdf->Cell(0, 6, txt("LISTA DE MIEMBROS (" . count($miembros) . " registrados)"), 0, 1);
$pdf->Ln(1);

// 3. TABLA DE MIEMBROS (SOLO N°, NOMBRE COMPLETO Y CUMPLEAÑOS)
$wNum = 14;
$wNombre = 108;
$wCumple = 66;

// Cabecera de la tabla
$pdf->SetFillColor(1, 3, 38);   // Azul noche #010326
$pdf->SetTextColor(255, 255, 255);
$pdf->SetDrawColor(1, 3, 38);
$pdf->SetLineWidth(0.3);
$pdf->SetFont('Arial', 'B', 9);

$pdf->Cell($wNum, 7.5, txt($textoColumna1), 1, 0, 'C', true);
$pdf->Cell($wNombre, 7.5, txt($textoColumna2), 1, 0, 'L', true);
$pdf->Cell($wCumple, 7.5, txt($textoColumna3), 1, 1, 'L', true);

// Contenido de la tabla
$pdf->SetFont('Arial', '', 8.5);
$pdf->SetTextColor(15, 23, 42);
$pdf->SetDrawColor(203, 213, 225);

if (empty($miembros)) {
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Cell($wNum + $wNombre + $wCumple, 12, txt($textoSinMiembros), 1, 1, 'C', true);
} else {
    $fill = false;
    foreach ($miembros as $idx => $m) {
        // Alternar color de fondo
        if ($fill) {
            $pdf->SetFillColor(248, 250, 252);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }

        $numStr = (string) ($idx + 1);
        $nombreStr = $m['nombre_completo'];
        $cumpleStr = formatoCumpleanos($m['fecha_nacimiento']);

        // Altura de fila
        $altoFila = 6.8;

        $pdf->Cell($wNum, $altoFila, $numStr, 1, 0, 'C', true);
        $pdf->Cell($wNombre, $altoFila, txt($nombreStr), 1, 0, 'L', true);
        $pdf->Cell($wCumple, $altoFila, txt($cumpleStr), 1, 1, 'L', true);

        $fill = !$fill;
    }
}

// ==============================================================================
// 4. DESCARGA AUTOMÁTICA DEL ARCHIVO PDF
// ==============================================================================
// Limpiar cualquier buffer previo para evitar archivos PDF corruptos
if (ob_get_length()) {
    ob_end_clean();
}

$nombreLimpio = preg_replace('/[^a-zA-Z0-9_-]/', '_', $celula['celula_nombre']);
$nombreDescarga = 'padron_miembros_' . strtolower($nombreLimpio) . '.pdf';

// Encabezados para forzar la descarga directa al dispositivo
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $nombreDescarga . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// 'D' fuerza la descarga inmediata en el navegador/celular
$pdf->Output('D', $nombreDescarga);
exit();
