<?php
session_start();
if (isset($_SESSION["usuario"])) {
    if (strtolower($_SESSION["rol"] ?? "") === "cliente") {
        header("Location: tienda.php");
    } else {
        header("Location: index.php");
    }
    exit();
}
$error = $_GET["error"] ?? "";
$registro = $_GET["registro"] ?? "";
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sweet Bite 🍪 - Iniciar sesión</title>
<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: 'Segoe UI', sans-serif;
}
body {
    background: linear-gradient(135deg, #fce4ec, #f8bbd0);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.login-card {
    background: white;
    padding: 40px 35px;
    border-radius: 25px;
    box-shadow: 0 10px 25px rgba(233, 30, 99, 0.12);
    text-align: center;
    width: 100%;
    max-width: 380px;
    border: 2px solid #f48fb1;
}
.avatar {
    background: #f48fb1;
    color: white;
    width: 70px;
    height: 70px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    font-size: 30px;
}
h2 {
    color: #880e4f;
    margin-bottom: 8px;
    font-size: 26px;
}
p {
    font-size: 13px;
    color: #ad1457;
    margin-bottom: 22px;
}
input {
    width: 100%;
    padding: 12px 15px;
    margin: 8px 0;
    border: 1.5px solid #f8bbd0;
    border-radius: 12px;
    background: #fff5f8;
    font-size: 14px;
}
button {
    width: 100%;
    padding: 12px;
    background: #ec407a;
    color: white;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    font-weight: bold;
    font-size: 15px;
    margin-top: 12px;
}
button:hover {
    background: #d81b60;
}
.links {
    margin-top: 18px;
    font-size: 13px;
}
.links a {
    display: block;
    color: #ad1457;
    text-decoration: none;
    font-weight: 600;
    margin-top: 12px;
}
.mensaje {
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 14px;
    font-size: 13px;
}
.exito {
    background: #e8f5e9;
    color: #1b5e20;
}
.error {
    background: #ffebee;
    color: #b71c1c;
}
</style>
</head>
<body>
<div class="login-card">
    <div class="avatar">🍪</div>
    <h2>Sweet Bite</h2>
    <p>¡Tus galletas favoritas te esperan!</p>
    <?php if ($registro === "exitoso"): ?>
        <div class="mensaje exito">
            ¡Tu cuenta fue creada correctamente! 💗
            Ahora inicia sesión con tu usuario y contraseña.
        </div>
    <?php endif; ?>
    <?php if ($error === "credenciales"): ?>
        <div class="mensaje error">
            Usuario o contraseña incorrectos.
        </div>
    <?php elseif ($error === "inactivo"): ?>
        <div class="mensaje error">
            Tu cuenta está desactivada. Contacta con la empresa.
        </div>
    <?php endif; ?>
    <form action="procesar_login.php" method="POST">
        <input
            type="text"
            name="usuario"
            placeholder="Nombre de usuario"
            maxlength="50"
            autocomplete="username"
            required
        >
        <input
            type="password"
            name="password"
            placeholder="Contraseña"
            autocomplete="current-password"
            required
        >
        <button type="submit">Iniciar sesión 🍓</button>
    </form>
    <div class="links">
        <a href="registrar_cliente.php">
            ✨ ¿No tienes cuenta? Regístrate aquí
        </a>
    </div>
</div>
</body>
</html>