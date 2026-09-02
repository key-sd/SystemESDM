<?php
require_once __DIR__ . "/includes/auth.php";
requiereLogin();
$destinos = [
    "admin" => "/SystemESDM/admin/admin-inicio.php",
    "supervisor" => "/SystemESDM/supervisor/sup-inicio.php",
    "lider" => "/SystemESDM/lider/lider-inicio.php",
];
header("Location: " . ($destinos[$_SESSION["rol"]] ?? "/SystemESDM/login.php"));
exit();
