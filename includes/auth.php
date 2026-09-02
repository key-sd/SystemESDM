<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function requiereLogin(): void
{
    if (empty($_SESSION["usuario_id"])) {
        header("Location: /SystemESDM/login.php");
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
