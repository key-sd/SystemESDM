<?php
require_once __DIR__ . "/includes/auth.php";
requiereLogin();
$destinos = [
    "admin" => urlApp("admin/admin-inicio.php"),
    "supervisor" => urlApp("supervisor/sup-inicio.php"),
    "lider" => urlApp("lider/lider-inicio.php"),
];
header("Location: " . ($destinos[$_SESSION["rol"]] ?? urlApp("login.php")));
exit();
