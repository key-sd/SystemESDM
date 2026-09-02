<?php

require_once __DIR__ . "/../includes/conexion.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/funciones.php";

requiereRol(["admin"]);

function abrirPaginaAdmin(
    string $seccion,
    string $titulo
): void {
    // Los componentes requeridos comparten el alcance de esta función.
    // La variable local permite que el sidebar marque la página correcta.
    $actual = $seccion;

    require __DIR__ . "/../components/head.php";
    require __DIR__ . "/../components/sidebar.php";
    require __DIR__ . "/../components/top.php";
}

function cerrarPaginaAdmin(array $scripts = []): void
{
    require __DIR__ . "/../components/foot.php";
}

function redirigirAdmin(string $archivo): never
{
    header("Location: " . $archivo);
    exit();
}
