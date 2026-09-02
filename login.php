<?php
require_once "includes/conexion.php";
session_start();

// Si ya hay sesion iniciada, que no vea el login de nuevo
if (
    isset($_SESSION["usuario_id"]) &&
    in_array($_SESSION["rol"] ?? "", ["admin", "supervisor", "lider"], true)
) {
    header("Location: index.php");
    exit();
}
if (isset($_SESSION["usuario_id"])) {
    $_SESSION = [];
    session_regenerate_id(true);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usuario = trim($_POST["usuario"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $conexion->prepare(
        "SELECT * FROM usuarios WHERE usuario = ? AND activo = 1",
    );
    $stmt->execute([$usuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user["password"])) {
        session_regenerate_id(true);
        $_SESSION["usuario_id"] = $user["id"];
        $_SESSION["nombre"] = $user["nombre_completo"];
        $_SESSION["rol"] = $user["rol"];
        $_SESSION["sector_id"] = $user["sector_id"];
        $_SESSION["celula_id"] = $user["celula_id"];
        header("Location: index.php");
        exit();
    } else {
        // guardamos el error en sesion y redirigimos, asi al recargar no sale el aviso de reenviar formulario
        $_SESSION["error"] = "Usuario o contraseña incorrectos";
        header("Location: login.php");
        exit();
    }
}

$error = $_SESSION["error"] ?? "";
unset($_SESSION["error"]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Elim SDM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="css/login.css">
    <link rel="icon" type="image/x-icon" href="img/icono.png" >
</head>
<body>

<div class="pagina-login d-flex flex-column align-items-center justify-content-center">
    
    <section class="tarjeta-fondo">

        <div class="marca-arriba text-center">
            <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                <img src="img/logoClaro.png" alt="ELIM SDM" class="marca-logo">
            </div>
        </div>
        
        <div class="card-stack">
            <div class="login-card">
                <h2>Iniciar sesión</h2>
                <p class="sub">Ingresa con tu usuario y contraseña</p>

                <?php if ($error): ?>
                    <div class="login-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="login.php">
                    <label for="usuario" class="form-label">Usuario</label>
                    <div class="campo-con-icono">
                        <i class="ri-user-3-line"></i>
                        <input
                            type="text"
                            id="usuario"
                            name="usuario"
                            class="form-control"
                            placeholder="Tu nombre de usuario"
                            autocomplete="username"
                            required
                            autofocus
                        >
                    </div>

                    <label for="password" class="form-label">Contraseña</label>
                    <div class="campo-con-icono campo-password">
                        <i class="ri-lock-2-line"></i>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            placeholder="Tu contraseña"
                            autocomplete="current-password"
                            required
                        >
                        <button type="button" class="btn btn-toggle-pass" id="togglePass" aria-label="Mostrar contraseña">
                            <i class="ri-eye-line" id="toggleIcon"></i>
                        </button>
                    </div>

                    <button type="submit" class="btn btn-login w-100">Entrar</button>
                </form>

                <p class="ayuda-login">
                    <i class="ri-information-line"></i>
                    Si no tienes acceso, comunícate con el administrador.
                </p>
            </div>
        </div>
    </section>
</div>

<script src="js/main.js?v=<?= filemtime(__DIR__ . '/js/main.js') ?>"></script>

</body>
</html>
