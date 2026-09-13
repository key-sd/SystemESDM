<?php
$rol = $_SESSION["rol"];
$actual = $actual ?? "resumen";

$menus = [
    "admin" => [
        "resumen" => ["ri-layout-grid-line", "Resumen", "admin-inicio.php"],
        "sectores" => ["ri-community-line", "Sectores", "sectores.php"],
        "celulas" => ["ri-map-pin-2-line", "Células", "celulas.php"],
        "usuarios" => ["ri-team-line", "Usuarios", "usuarios.php"],
        "reportes" => ["ri-file-chart-line", "Reportes", "reportes.php"],
        "estadisticas" => [
            "ri-bar-chart-box-line",
            "Estadísticas",
            "estadisticas.php",
        ],
        "servicios" => [
            "ri-calendar-check-line",
            "Servicios y finanzas",
            "servicios.php",
        ],
        "exportar" => [
            "ri-file-excel-2-line",
            "Exportar a Excel",
            "exportar-reportes.php",
        ],
        "metas" => ["ri-focus-3-line", "Metas trimestrales", "metas.php"],
    ],
    "supervisor" => [
        "resumen" => [
            "ri-layout-grid-line",
            "Resumen",
            "sup-inicio.php?vista=resumen",
        ],
        "celulas" => [
            "ri-community-line",
            "Mis células",
            "sup-inicio.php?vista=celulas",
        ],
        "reporte" => [
            "ri-file-edit-line",
            "Reporte del sector",
            "sup-inicio.php?vista=reporte",
        ],
        "historial" => [
            "ri-history-line",
            "Reportes enviados",
            "sup-inicio.php?vista=historial",
        ],
    ],
    "lider" => [
        "resumen" => [
            "ri-layout-grid-line",
            "Resumen",
            "lider-inicio.php?vista=resumen",
        ],
        "reporte" => [
            "ri-file-edit-line",
            "Enviar reporte",
            "lider-inicio.php?vista=reporte",
        ],
        "historial" => [
            "ri-history-line",
            "Mis reportes",
            "lider-inicio.php?vista=historial",
        ],
    ],
];
?>
<aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= e(urlApp('index.php')) ?>">
        <img src="<?= e(urlApp('img/logoClaro.png')) ?>" alt="ELIM SDM"/>
    </a>

    <nav class="side-nav">
        <?php foreach ($menus[$rol] as $clave => [$icono, $texto, $ruta]): ?>
            <a class="<?= $actual === $clave ? "active" : "" ?>" href="<?= e(
    $ruta,
) ?>">
                <i class="<?= e($icono) ?>"></i>
                <span><?= e($texto) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="side-user">
        <div class="avatar"><?= e(
            strtoupper(substr($_SESSION["nombre"], 0, 1)),
        ) ?></div>
        <div class="user-copy">
            <strong><?= e($_SESSION["nombre"]) ?></strong>
            <span><?= e(ucfirst($rol)) ?></span>
        </div>
        <a class="logout" href="<?= e(urlApp('logout.php')) ?>" title="Cerrar sesión">
            <i class="ri-logout-box-r-line"></i>
        </a>
    </div>
</aside>
<button
    class="sidebar-backdrop"
    id="sidebarBackdrop"
    type="button"
    aria-label="Cerrar menú"
></button>
