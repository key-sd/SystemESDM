<?php
// Detección automática: Entorno local (XAMPP) o InfinityFree
$isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'])
           || (php_sapi_name() === 'cli' && getenv('APP_ENV') !== 'production');

if ($isLocal) {
    // Configuración local (XAMPP / phpMyAdmin)
    $host = "localhost";
    $db   = "db_systemelimsdm";
    $user = "root";
    $pass = "";
} else {
    // Configuración en InfinityFree (obtenida de tu panel de control)
    $host = "sql101.infinityfree.com";
    $db   = "if0_43028489_db_systemelimsdm";
    $user = "if0_43028489";
    $pass = "Ksanchez794"; 
}

try {
    $conexion = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass
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
