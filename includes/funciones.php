<?php

function e(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

function entero(string $campo): int
{
    return max(0, (int) ($_POST[$campo] ?? 0));
}

function dinero(string $campo): float
{
    return max(0, round((float) ($_POST[$campo] ?? 0), 2));
}

function flash(string $tipo, string $mensaje): void
{
    $_SESSION["flash"] = [$tipo, $mensaje];
}

function tomarFlash(): ?array
{
    $mensaje = $_SESSION["flash"] ?? null;
    unset($_SESSION["flash"]);

    return $mensaje;
}

function fechaBonita(?string $fecha): string
{
    if (!$fecha) {
        return "Pendiente";
    }

    return date("d/m/Y · h:i a", strtotime($fecha));
}

function semanaActual(): string
{
    // El sábado que identifica el cierre semanal de las células
    return date("Y-m-d", strtotime("saturday this week"));
}

function inicioSemanaActual(): string
{
    return date("Y-m-d", strtotime("monday this week"));
}

function finSemanaActual(): string
{
    return date("Y-m-d", strtotime("sunday this week"));
}

function validarFechaReporteCelula(?string $fecha): string
{
    $objeto = DateTimeImmutable::createFromFormat("!Y-m-d", (string) $fecha);
    $errores = DateTimeImmutable::getLastErrors();

    if (!$objeto || ($errores !== false && ($errores["warning_count"] || $errores["error_count"]))) {
        throw new InvalidArgumentException("Selecciona una fecha válida para el reporte.");
    }

    $hoy = new DateTimeImmutable("today");
    $limiteFuturo = $hoy->modify("sunday this week");
    if ($objeto > $limiteFuturo) {
        throw new InvalidArgumentException("La fecha de la reunión no puede ser una fecha futura.");
    }

    return $objeto->format("Y-m-d");
}

function anioSemanaReporte(string $fecha): int
{
    return (int) (new DateTimeImmutable($fecha))->format("oW");
}

function fechaReporteSemanal(?string $fecha): string
{
    $objeto = DateTimeImmutable::createFromFormat("!Y-m-d", (string) $fecha);
    $errores = DateTimeImmutable::getLastErrors();

    if (!$objeto || ($errores !== false && ($errores["warning_count"] || $errores["error_count"]))) {
        throw new InvalidArgumentException("Selecciona una fecha válida para el reporte.");
    }

    // El sábado que cierra la semana de la reunión.
    if ((int) $objeto->format("N") === 7) {
        return $objeto->modify("-1 day")->format("Y-m-d");
    }

    return $objeto->modify("saturday this week")->format("Y-m-d");
}

function etiquetaTipoCelula(?string $tipo): string
{
    return match ($tipo) {
        "adultos" => "Adultos",
        "jovenes" => "Juvenil",
        "ninos" => "Infantil",
        default => $tipo ? ucfirst($tipo) : "General",
    };
}
