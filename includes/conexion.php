<?php
$host = "localhost";
$db = "db_systemelimsdm";
$user = "root";
$pass = "";

try {
    $conexion = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
    );
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $conexion->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $conexion->exec("SET time_zone = '-06:00'");
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    die("No fue posible conectar con el sistema. Inténtalo nuevamente.");
}

$conn = $conexion;
