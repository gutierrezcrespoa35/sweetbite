<?php
session_start();
require_once "config/conexion.php";
$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST["nombre_completo"] ?? "");
    $usuario = trim($_POST["usuario"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmar = $_POST["confirmar"] ?? "";
    if ($nombre === "" || $usuario === "" || $password === "") {
        $error = "Completa todos los campos.";
    } elseif (strlen($nombre) > 150 || strlen($usuario) > 50) {
        $error = "El nombre o usuario es demasiado largo.";
    } elseif (strlen($password) < 8) {
        $error = "La contraseña debe tener al menos 8 caracteres.";
    } elseif ($password !== $confirmar) {
        $error = "Las contraseñas no coinciden.";
    } else {
        // Verificar si el usuario ya existe.
        $buscar = $conexion->prepare(
            "SELECT id_usuario FROM usuarios WHERE usuario = ? LIMIT 1"
        );
        $buscar->bind_param("s", $usuario);
        $buscar->execute();
        $existe = $buscar->get_result()->num_rows > 0;
        $buscar->close();
        if ($existe) {
            $error = "Ese nombre de usuario ya está registrado.";
        } else {
            // Buscar el rol Cliente.
            $nombre_rol = "Cliente";
            $buscar_rol = $conexion->prepare(
                "SELECT id_rol, estado
                 FROM roles
                 WHERE LOWER(nombre) = LOWER(?)
                 LIMIT 1"
            );
            $buscar_rol->bind_param("s", $nombre_rol);
            $buscar_rol->execute();
            $rol = $buscar_rol->get_result()->fetch_assoc();
            $buscar_rol->close();
            // Crear Cliente solamente si todavía no existe.
            if (!$rol) {
                $descripcion = "Cliente de la tienda en línea";
                $estado_rol = 1;
                $crear_rol = $conexion->prepare(
                    "INSERT INTO roles (nombre, descripcion, estado)
                     VALUES (?, ?, ?)"
                );
                $crear_rol->bind_param(
                    "ssi",
                    $nombre_rol,
                    $descripcion,
                    $estado_rol
                );
                if ($crear_rol->execute()) {
                    $rol = [
                        "id_rol" => $conexion->insert_id,
                        "estado" => 1
                    ];
                }
                $crear_rol->close();
            }
            if (!$rol) {
                $error = "No se pudo configurar el rol Cliente.";
            } elseif ((int)$rol["estado"] !== 1) {
                $error = "El registro de clientes no está habilitado.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $estado = 1;
                $id_rol = (int)$rol["id_rol"];
                // El rol Cliente se asigna desde el servidor.
                $insertar = $conexion->prepare(
                    "INSERT INTO usuarios
                    (usuario, password, nombre_completo, rol, estado, id_rol)
                    VALUES (?, ?, ?, ?, ?, ?)"
                );
                $insertar->bind_param(
                    "ssssii",
                    $usuario,
                    $hash,
                    $nombre,
                    $nombre_rol,
                    $estado,
                    $id_rol
                );
                if ($insertar->execute()) {
                    $insertar->close();
                    $conexion->close();
                    header("Location: login.php?registro=exitoso");
                    exit();
                } else {
                    $error = "No se pudo crear la cuenta. Intenta nuevamente.";
                }
                $insertar->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Crear cuenta - Sweet Bite</title>
<style>
* {
    box-sizing: border-box;
    font-family: 'Segoe UI', sans-serif;
}
body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #fce4ec, #f8bbd0);
    padding: 20px;
}
.card {
    background: white;
    width: 100%;
    max-width: 420px;
    padding: 30px;
    border-radius: 22px;
    border: 2px solid #f48fb1;
    box-shadow: 0 10px 25px #e91e631f;
}
h1 {
    text-align: center;
    color: #880e4f;
}
p {
    text-align: center;
    color: #ad1457;
    font-size: 14px;
    margin-bottom: 20px;
}
input {
    width: 100%;
    padding: 12px;
    margin: 8px 0;
    border: 1px solid #f8bbd0;
    border-radius: 10px;
}
button {
    width: 100%;
    padding: 13px;
    margin-top: 12px;
    background: #ec407a;
    color: white;
    border: 0;
    border-radius: 10px;
    font-weight: bold;
    cursor: pointer;
}
.error {
    background: #ffebee;
    color: #b71c1c;
    padding: 10px;
    border-radius: 8px;
    margin: 12px 0;
    font-size: 14px;
}
a {
    display: block;
    text-align: center;
    margin-top: 18px;
    color: #ad1457;
    text-decoration: none;
}
</style>
</head>
<body>
<div class="card">
    <h1>🍪 Únete a Sweet Bite</h1>
    <p>Crea tu cuenta para comprar tus galletas favoritas.</p>
    <?php if ($error !== ""): ?>
        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>
    <form method="POST" action="registrar_cliente.php">
        <input
            type="text"
            name="nombre_completo"
            placeholder="Nombre completo"
            maxlength="150"
            required
        >
        <input
            type="text"
            name="usuario"
            placeholder="Crea un nombre de usuario"
            maxlength="50"
            autocomplete="username"
            required
        >
        <input
            type="password"
            name="password"
            placeholder="Contraseña (mínimo 8 caracteres)"
            minlength="8"
            autocomplete="new-password"
            required
        >
        <input
            type="password"
            name="confirmar"
            placeholder="Repite tu contraseña"
            minlength="8"
            autocomplete="new-password"
            required
        >
        <button type="submit">Crear mi cuenta 💗</button>
    </form>
    <a href="login.php">Ya tengo una cuenta · Iniciar sesión</a>
</div>
</body>
</html>
<?php $conexion->close(); ?>