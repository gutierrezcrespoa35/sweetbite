<?php
session_start();
include('config/conexion.php');

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$nombre_usuario = $_SESSION['nombre_completo'] ?? $_SESSION['usuario'];
$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['guardar_rol'])) {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    if ($nombre === '') {
        $mensaje = "<div class='alert error'>El nombre del rol es obligatorio.</div>";
    } else {
        try {
            $buscar = $conexion->prepare(
                "SELECT id_rol FROM roles WHERE LOWER(nombre) = LOWER(?) LIMIT 1"
            );
            $buscar->bind_param("s", $nombre);
            $buscar->execute();
            $existe = $buscar->get_result()->num_rows > 0;
            $buscar->close();

            if ($existe) {
                $mensaje = "<div class='alert error'>Ese rol ya está registrado.</div>";
            } else {
                $insertar = $conexion->prepare(
                    "INSERT INTO roles (nombre, descripcion) VALUES (?, ?)"
                );
                $insertar->bind_param("ss", $nombre, $descripcion);
                $insertar->execute();
                $insertar->close();

                $mensaje = "<div class='alert success'>¡Rol registrado correctamente! ✨</div>";
            }
        } catch (mysqli_sql_exception $e) {
            error_log("Error en roles.php: " . $e->getMessage());
            $mensaje = "<div class='alert error'>No se pudo registrar el rol. Verifica los datos e inténtalo nuevamente.</div>";
        }
    }
}

$resultado = $conexion->query(
    "SELECT id_rol, nombre, descripcion, estado
     FROM roles
     ORDER BY id_rol DESC"
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sweet Bite Bakery | Roles</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',Tahoma,sans-serif}
body{background:#fce4ec;min-height:100vh;color:#6d1740}
header{background:white;padding:18px 30px;display:flex;justify-content:space-between;align-items:center;gap:12px;border-bottom:2px solid #f8bbd0}
.logo{font-size:22px;font-weight:bold;color:#880e4f}
.user-info{font-size:14px;color:#ad1457;font-weight:600}
.main-container{display:flex;flex:1}
aside{width:240px;flex-shrink:0;background:white;padding:25px 15px;border-right:2px solid #f8bbd0}
aside p{font-size:11px;font-weight:bold;color:#f48fb1;margin-bottom:12px;padding-left:10px}
aside ul{list-style:none}
aside ul li{margin-bottom:7px}
aside ul li a{display:block;padding:11px 12px;color:#ad1457;text-decoration:none;font-weight:600;border-radius:10px;font-size:14px}
aside ul li a:hover,aside ul li a.active{background:#f8bbd0;color:#880e4f}
.content{flex:1;min-width:0;padding:30px}
.card-box{background:white;padding:24px;border-radius:18px;border:2px solid #f8bbd0;box-shadow:0 5px 15px #e91e630d;margin-bottom:24px}
h2{color:#880e4f;margin-bottom:18px;font-size:22px}
.form-group{margin-bottom:16px}
label{display:block;margin-bottom:6px;color:#ad1457;font-weight:600;font-size:14px}
input{width:100%;padding:11px 13px;border:2px solid #f8bbd0;border-radius:10px;font-size:14px;outline:none}
input:focus{border-color:#ec407a}
button{background:#ec407a;color:white;border:0;padding:11px 20px;border-radius:10px;font-weight:bold;cursor:pointer}
button:hover{background:#d81b60}
.table-container{width:100%;overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:500px}
th{background:#f8bbd0;color:#880e4f;text-align:left;padding:12px;font-size:14px}
td{padding:12px;border-bottom:1px solid #f8bbd0;font-size:14px}
.alert{padding:12px 15px;border-radius:10px;margin-bottom:18px;font-weight:600}
.success{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7}
.error{background:#ffebee;color:#c62828;border:1px solid #ef9a9a}
@media(max-width:768px){header{padding:15px;flex-wrap:wrap}.main-container{flex-direction:column}aside{width:100%;border-right:0;border-bottom:2px solid #f8bbd0}.content{padding:15px}.card-box{padding:17px}}
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
            <h2>Gestión de Roles 🔑</h2>
            <form action="roles.php" method="POST">
                <div class="form-group">
                    <label for="nombre">Nombre del rol</label>
                    <input type="text" id="nombre" name="nombre" maxlength="50"
                           placeholder="Ej.: Administradora, Vendedora" required>
                </div>

                <div class="form-group">
                    <label for="descripcion">Descripción</label>
                    <input type="text" id="descripcion" name="descripcion" maxlength="150"
                           placeholder="Funciones de este rol">
                </div>

                <button type="submit" name="guardar_rol">Guardar rol ✨</button>
            </form>
        </section>

        <section class="card-box">
            <h2>Roles registrados</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre del rol</th>
                            <th>Descripción</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($resultado && $resultado->num_rows > 0): ?>
                        <?php while ($fila = $resultado->fetch_assoc()): ?>
                            <tr>
                                <td><?= (int)$fila['id_rol'] ?></td>
                                <td><?= htmlspecialchars($fila['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($fila['descripcion'] ?? 'Sin descripción', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= (int)$fila['estado'] === 1 ? 'Activo' : 'Inactivo' ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4">Todavía no hay roles registrados.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>