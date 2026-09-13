<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/** Construye rutas válidas en XAMPP y al publicar en la raíz de un dominio. */
function urlApp(string $ruta = ''): string
{
    static $base = null;

    if ($base === null) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $base = dirname($script);

        foreach (['/admin', '/lider', '/supervisor'] as $seccion) {
            if (str_ends_with($base, $seccion)) {
                $base = substr($base, 0, -strlen($seccion));
                break;
            }
        }
        $base = rtrim($base, '/');
    }

    return ($base === '' ? '' : $base) . '/' . ltrim($ruta, '/');
}

function requiereLogin(): void
{
    if (empty($_SESSION["usuario_id"])) {
        header("Location: " . urlApp("login.php"));
        exit();
    }
}

function requiereRol(array $roles): void
{
    requiereLogin();
    if (!in_array($_SESSION["rol"] ?? "", $roles, true)) {
        http_response_code(403);
        exit("No tienes permiso para acceder a esta pantalla.");
    }
}

function csrfToken(): string
{
    if (empty($_SESSION["csrf"])) {
        $_SESSION["csrf"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf"];
}

function validarCsrf(): void
{
    if (!hash_equals($_SESSION["csrf"] ?? "", $_POST["csrf"] ?? "")) {
        http_response_code(419);
        exit(
            "La sesión del formulario venció. Recarga la página e inténtalo de nuevo."
        );
    }
}
