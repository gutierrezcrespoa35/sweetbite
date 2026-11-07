
<?php
session_start();
require_once('config/conexion.php');

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$nombre_usuario = $_SESSION['nombre_completo'] ?? $_SESSION['usuario'];
$mensaje = "";

// Registrar categoría
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['nombre'])) {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $estado = 1;

    if ($nombre !== '') {
        // 1. Verificar si la categoría ya existe en la base de datos
        $stmt_check = $conexion->prepare("SELECT id_categoria FROM categorias WHERE nombre = ?");
        if ($stmt_check) {
            $stmt_check->bind_param("s", $nombre);
            $stmt_check->execute();
            $stmt_check->store_result();

            if ($stmt_check->num_rows > 0) {
                $mensaje = "<div class='alert error'>⚠️ La categoría '<strong>" . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . "</strong>' ya se encuentra registrada.</div>";
                $stmt_check->close();
            } else {
                $stmt_check->close();

                // 2. Si no existe, procedemos a insertar
                $stmt = $conexion->prepare(
                    "INSERT INTO categorias (nombre, descripcion, estado)
                     VALUES (?, ?, ?)"
                );

                if ($stmt) {
                    $stmt->bind_param("ssi", $nombre, $descripcion, $estado);

                    if ($stmt->execute()) {
                        $mensaje = "<div class='alert success'>¡Categoría registrada con éxito! 🏷️✨</div>";
                    } else {
                        $mensaje = "<div class='alert error'>Error al guardar la categoría. Verifica las columnas de la tabla.</div>";
                    }

                    $stmt->close();
                } else {
                    $mensaje = "<div class='alert error'>No se pudo preparar la consulta. Verifica la tabla categorias.</div>";
                }
            }
        } else {
            $mensaje = "<div class='alert error'>Error al verificar la existencia de la categoría.</div>";
        }
    } else {
        $mensaje = "<div class='alert error'>El nombre de la categoría es obligatorio.</div>";
    }
}

// Consultar categorías
$resultado = $conexion->query(
    "SELECT id_categoria, nombre, descripcion FROM categorias"
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sweet Bite 🍪 - Categorías</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #fce4ec;
            min-height: 100vh;
        }

        header {
            background: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #f8bbd0;
            box-shadow: 0 4px 10px rgba(233, 30, 99, .05);
        }

        .logo {
            font-size: 22px;
            font-weight: bold;
            color: #880e4f;
        }

        .user-info {
            color: #ad1457;
            font-weight: 600;
            font-size: 14px;
        }

        .main-container {
            display: flex;
            min-height: calc(100vh - 60px);
        }

        aside {
            width: 240px;
            flex-shrink: 0;
            background: white;
            padding: 25px 15px;
            border-right: 2px solid #f8bbd0;
        }

        aside p {
            font-size: 11px;
            font-weight: bold;
            color: #f48fb1;
            margin-bottom: 12px;
            padding-left: 10px;
        }

        aside ul {
            list-style: none;
        }

        aside li {
            margin-bottom: 8px;
        }

        aside a {
            display: block;
            padding: 12px;
            color: #ad1457;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            border-radius: 12px;
        }

        aside a:hover,
        aside a.active {
            background: #f8bbd0;
            color: #880e4f;
        }

        .content {
            flex: 1;
            min-width: 0;
            padding: 30px;
        }

        .card-box {
            background: white;
            padding: 25px;
            border-radius: 20px;
            border: 2px solid #f8bbd0;
            box-shadow: 0 8px 20px rgba(233, 30, 99, .05);
            margin-bottom: 25px;
            overflow-x: auto;
        }

        h2 {
            color: #880e4f;
            margin-bottom: 18px;
            font-size: 22px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            color: #ad1457;
            font-size: 14px;
            font-weight: 600;
        }

        input[type="text"] {
            width: 100%;
            padding: 11px 14px;
            border: 2px solid #f8bbd0;
            border-radius: 10px;
            outline: none;
            font-size: 14px;
        }

        input[type="text"]:focus {
            border-color: #ec407a;
        }

        .btn-submit {
            background: #ec407a;
            color: white;
            border: none;
            padding: 11px 20px;
            font-weight: bold;
            border-radius: 10px;
            cursor: pointer;
        }

        .btn-submit:hover {
            background: #d81b60;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th {
            background: #f8bbd0;
            color: #880e4f;
            text-align: left;
            padding: 12px;
            font-size: 14px;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #f8bbd0;
            color: #ad1457;
            font-size: 14px;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 14px;
        }

        .success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }

        .error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }

        @media (max-width: 760px) {
            .main-container {
                flex-direction: column;
            }

            aside {
                width: 100%;
            }

            .content {
                padding: 15px;
            }

            header {
                padding: 15px;
                gap: 10px;
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>

<header>
    <div class="logo">🍪 Sweet Bite Bakery</div>
    <div class="user-info">
        ¡Hola, <?php echo htmlspecialchars($nombre_usuario, ENT_QUOTES, 'UTF-8'); ?>! 🌸
    </div>
</header>

<div class="main-container">
    <aside>
        <p>MENÚ REPOSTERÍA</p>
        <ul>
            <li><a href="index.php">🏠 Inicio</a></li>
            <li><a href="roles.php">🔑 Roles</a></li>
            <li><a href="categorias.php" class="active">🏷️ Categorías</a></li>
            <li><a href="productos.php">🍪 Productos</a></li>
            <li><a href="catalogo_galletas.php">📜 Catálogo Galletas</a></li>
            <li><a href="inventarios.php">📦 Inventario</a></li>
            <li><a href="carritos.php">🛒 Carrito</a></li>
            <li><a href="detalle_carrito.php">🛒 Detalle Carrito</a></li>
            <li><a href="pedidos.php">📋 Pedidos</a></li>
            <li><a href="detalle_pedido.php">📋 Detalle Pedido</a></li>
            <li><a href="pagos.php">💳 Pagos</a></li>
            <li><a href="registrar_usuario.php">👥 Registrar Usuarios</a></li>
            <li><a href="pedidos_especiales.php">📦 Pedidos Especiales</a></li>
            <li><a href="ventas_reportes.php">📊 Ventas / Reportes</a></li>
            <li><a href="cerrar_sesion.php" style="color:#c62828;">🚪 Cerrar Sesión</a></li>
        </ul>
    </aside>

    <main class="content">
        <?php echo $mensaje; ?>

        <div class="card-box">
            <h2>Gestión de Categorías 🏷️</h2>

            <form action="categorias.php" method="POST">
                <div class="form-group">
                    <label for="nombre">Nombre de la Categoría:</label>
                    <input
                        type="text"
                        id="nombre"
                        name="nombre"
                        placeholder="Ej: Galletas Rellenas, Especiales..."
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="descripcion">Descripción:</label>
                    <input
                        type="text"
                        id="descripcion"
                        name="descripcion"
                        placeholder="Ej: Galletas con centro de manjar o chocolate"
                    >
                </div>

                <button type="submit" class="btn-submit">
                    Guardar Categoría
                </button>
            </form>
        </div>

        <div class="card-box">
            <h2>Categorías Registradas</h2>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Categoría</th>
                        <th>Descripción</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$resultado): ?>
                    <tr>
                        <td colspan="3">Error al consultar las categorías. Verifica la tabla y sus columnas.</td>
                    </tr>
                <?php elseif ($resultado->num_rows > 0): ?>
                    <?php while ($row = $resultado->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)$row['id_categoria'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['descripcion'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3">No hay categorías registradas aún.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

</body>
</html>