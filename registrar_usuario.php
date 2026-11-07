<?php
session_start();
include('config/conexion.php');

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$nombre_usuario = $_SESSION['nombre_completo'] ?? $_SESSION['usuario'];
$mensaje = "";

// REGISTRAR USUARIA
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['guardar_usuario'])) {
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    $id_rol = (int)($_POST['id_rol'] ?? 0);

    if ($nombre_completo === '' || $usuario === '' || $password === '' || $id_rol <= 0) {
        $mensaje = "<div class='alert error'>Completa todos los campos.</div>";
    } else {
        // Comprobar que el rol exista y esté activo
        $stmt_rol = $conexion->prepare(
            "SELECT id_rol, nombre FROM roles WHERE id_rol = ? AND estado = 1"
        );
        $stmt_rol->bind_param("i", $id_rol);
        $stmt_rol->execute();
        $resultado_rol = $stmt_rol->get_result();
        $datos_rol = $resultado_rol->fetch_assoc();
        $stmt_rol->close();

        if (!$datos_rol) {
            $mensaje = "<div class='alert error'>Selecciona un rol válido.</div>";
        } else {
            // Comprobar que el usuario no esté repetido
            $stmt_check = $conexion->prepare(
                "SELECT id_usuario FROM usuarios WHERE usuario = ?"
            );
            $stmt_check->bind_param("s", $usuario);
            $stmt_check->execute();
            $existe = $stmt_check->get_result()->num_rows > 0;
            $stmt_check->close();

            if ($existe) {
                $mensaje = "<div class='alert error'>Ese nombre de usuario ya existe.</div>";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $rol_nombre = $datos_rol['nombre'];

                $stmt = $conexion->prepare(
                    "INSERT INTO usuarios
                    (nombre_completo, usuario, password, rol, id_rol)
                    VALUES (?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "ssssi",
                    $nombre_completo,
                    $usuario,
                    $password_hash,
                    $rol_nombre,
                    $id_rol
                );

                if ($stmt->execute()) {
                    $mensaje = "<div class='alert success'>¡Usuaria registrada correctamente! 🌸</div>";
                } else {
                    $mensaje = "<div class='alert error'>No se pudo registrar la usuaria.</div>";
                }

                $stmt->close();
            }
        }
    }
}

// ROLES ACTIVOS PARA EL SELECTOR
$roles_combo = $conexion->query(
    "SELECT id_rol, nombre FROM roles WHERE estado = 1 ORDER BY nombre ASC"
);

// LISTA DE USUARIAS
$resultado_usuarios = $conexion->query(
    "SELECT id_usuario, nombre_completo, usuario, rol, id_rol
     FROM usuarios ORDER BY id_usuario DESC"
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sweet Bite Bakery - Usuarias</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; }
body { background: #fce4ec; min-height: 100vh; }
header { background: white; padding: 18px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f8bbd0; gap: 12px; flex-wrap: wrap; }
.logo { font-size: 22px; font-weight: bold; color: #880e4f; }
.user-info { color: #ad1457; font-weight: 600; }
.main-container { display: flex; min-height: calc(100vh - 65px); }
aside { width: 240px; flex-shrink: 0; background: white; padding: 25px 15px; border-right: 2px solid #f8bbd0; }
aside p { color: #f48fb1; font-size: 11px; font-weight: bold; margin: 0 0 12px 10px; }
aside ul { list-style: none; }
aside li { margin-bottom: 8px; }
aside a { display: block; padding: 12px; color: #ad1457; text-decoration: none; font-weight: 600; border-radius: 10px; font-size: 14px; }
aside a:hover { background: #f8bbd0; color: #880e4f; }
.content { flex: 1; min-width: 0; padding: 30px; }
.card-box { background: white; padding: 25px; border-radius: 18px; border: 2px solid #f8bbd0; margin-bottom: 25px; box-shadow: 0 5px 15px #e91e630d; }
h2 { color: #880e4f; margin-bottom: 18px; font-size: 21px; }
.form-group { margin-bottom: 16px; }
label { display: block; margin-bottom: 6px; color: #ad1457; font-weight: 600; font-size: 14px; }
input, select { width: 100%; padding: 11px 13px; border: 2px solid #f8bbd0; border-radius: 10px; background: white; font-size: 14px; outline: none; }
input:focus, select:focus { border-color: #ec407a; }
.btn-submit { background: #ec407a; color: white; border: none; padding: 12px 20px; border-radius: 10px; font-weight: bold; cursor: pointer; }
.btn-submit:hover { background: #d81b60; }
.alert { padding: 12px; border-radius: 10px; margin-bottom: 18px; font-weight: 600; }
.success { background: #e8f5e9; color: #2e7d32; }
.error { background: #ffebee; color: #c62828; }
.table-container { overflow-x: auto; }
table { width: 100%; min-width: 550px; border-collapse: collapse; margin-top: 12px; }
th { background: #f8bbd0; color: #880e4f; text-align: left; padding: 12px; }
td { padding: 12px; border-bottom: 1px solid #f8bbd0; color: #ad1457; }
.badge-rol { display: inline-block; background: #fce4ec; color: #880e4f; padding: 5px 9px; border-radius: 8px; font-weight: 600; }
.input-search { max-width: 300px; margin-bottom: 10px; }
@media(max-width: 768px) {
.main-container { flex-direction: column; }
aside { width: 100%; min-height: auto; }
.content { padding: 15px; }
.card-box { padding: 18px; }
}
</style>
</head>
<body>
<header>
    <div class="logo">🍪 Sweet Bite Bakery</div>
    <div class="user-info">¡Hola, <?= htmlspecialchars($nombre_usuario, ENT_QUOTES, 'UTF-8') ?>! 🌸</div>
</header>

<div class="main-container">
    <?php include('sidebar.php'); ?>

    <main class="content">
        <?= $mensaje ?>

        <section class="card-box">
            <h2>Registrar nueva usuaria 👥</h2>

            <form action="registrar_usuario.php" method="POST">
                <div class="form-group">
                    <label for="nombre_completo">Nombre completo</label>
                    <input type="text" id="nombre_completo" name="nombre_completo" maxlength="150" required>
                </div>

                <div class="form-group">
                    <label for="usuario">Nombre de usuario</label>
                    <input type="text" id="usuario" name="usuario" maxlength="50" required>
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="form-group">
                    <label for="id_rol">Rol del sistema</label>
                    <select id="id_rol" name="id_rol" required>
                        <option value="">Selecciona un rol</option>
                        <?php if ($roles_combo): ?>
                            <?php while ($r = $roles_combo->fetch_assoc()): ?>
                                <option value="<?= (int)$r['id_rol'] ?>">
                                    <?= htmlspecialchars($r['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <button type="submit" name="guardar_usuario" class="btn-submit">
                    Guardar usuaria
                </button>
            </form>
        </section>

        <section class="card-box">
            <h2>Usuarias registradas 📋</h2>
            <input type="text" id="buscarUsuario" class="input-search" placeholder="🔍 Buscar por nombre, usuario o rol...">

            <div class="table-container">
                <table id="tablaUsuarios">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre completo</th>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>ID del rol</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($resultado_usuarios && $resultado_usuarios->num_rows > 0): ?>
                        <?php while ($u = $resultado_usuarios->fetch_assoc()): ?>
                            <tr>
                                <td><?= (int)$u['id_usuario'] ?></td>
                                <td><?= htmlspecialchars($u['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($u['usuario'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="badge-rol"><?= htmlspecialchars($u['rol'] ?? 'Sin rol', ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= $u['id_rol'] === null ? 'Sin asignar' : (int)$u['id_rol'] ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5">No hay usuarias registradas.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<script>
document.getElementById('buscarUsuario').addEventListener('input', function () {
    const filtro = this.value.toLowerCase();
    document.querySelectorAll('#tablaUsuarios tbody tr').forEach(function (fila) {
        fila.style.display = fila.textContent.toLowerCase().includes(filtro) ? '' : 'none';
    });
});
</script>
</body>
</html>