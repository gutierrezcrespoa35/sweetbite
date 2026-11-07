<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function exigir_roles(array $roles_permitidos): void {
    if (!isset($_SESSION["usuario"])) {
        header("Location: login.php");
        exit();
    }

    $rol = strtolower(trim($_SESSION["rol"] ?? ""));

    if (!in_array($rol, $roles_permitidos, true)) {
        http_response_code(403);
        exit("Acceso denegado. No tienes permiso para entrar a esta página.");
    }
}
?>