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
    // Las células se reúnen en sábado; esa fecha identifica de forma única
    // el reporte semanal, aunque sea entregado el domingo.
    return date("Y-m-d", strtotime("saturday this week"));
}

function fechaReporteSemanal(?string $fecha): string
{
    $objeto = DateTimeImmutable::createFromFormat("!Y-m-d", (string) $fecha);
    $errores = DateTimeImmutable::getLastErrors();

    if (!$objeto || ($errores !== false && ($errores["warning_count"] || $errores["error_count"]))) {
        throw new InvalidArgumentException("Selecciona una fecha válida para el reporte.");
    }

    // El sábado que cierra la semana de la reunión. Así no puede existir un
    // segundo reporte de la misma semana por cambiar solamente el día.
    if ((int) $objeto->format("N") === 7) {
        return $objeto->modify("-1 day")->format("Y-m-d");
    }

    return $objeto->modify("saturday this week")->format("Y-m-d");
}
