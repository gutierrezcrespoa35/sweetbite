<?php
session_start();
require_once "config/conexion.php";

// Verificar que haya una sesión iniciada
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// Solo el administrador puede entrar
$rol = strtolower(trim($_SESSION['rol'] ?? ''));

if (!in_array($rol, ['administradora', 'administrador', 'admin'])) {
    header("Location: tienda.php");
    exit;
}

$nombre_usuario = $_SESSION['nombre_completo']
    ?? $_SESSION['usuario'];

$productos = [];
$inventario = [];
$error = "";

// Obtener los productos existentes
try {
    $resultado = $conexion->query("SELECT * FROM productos");

    if ($resultado) {
        while ($fila = $resultado->fetch_assoc()) {
            $productos[] = $fila;
        }
    }

    // Obtener las existencias de inventario
    $resultadoStock = $conexion->query(
        "SELECT id_producto, stock_actual FROM inventario"
    );

    if ($resultadoStock) {
        while ($filaStock = $resultadoStock->fetch_assoc()) {
            $inventario[$filaStock['id_producto']] =
                $filaStock['stock_actual'];
        }
    }
} catch (Throwable $e) {
    $error = "No se pudieron cargar los productos. Verifica las tablas de la base de datos.";
}

// Buscar un campo disponible entre los nombres posibles
function obtenerCampo($fila, $opciones, $predeterminado = '') {
    foreach ($opciones as $campo) {
        if (array_key_exists($campo, $fila)) {
            return $fila[$campo];
        }
    }

    return $predeterminado;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar catálogo | Sweet Bite</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #fff5f8;
            color: #49343d;
        }

        header {
            background: #f5c6d8;
            padding: 22px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            border-bottom: 3px solid #e9a7c1;
        }

        .logo {
            color: #813e5c;
            font-size: 27px;
            font-weight: bold;
        }

        .usuario {
            font-size: 14px;
        }

        .contenedor {
            width: 92%;
            max-width: 1200px;
            margin: 30px auto;
        }

        .volver {
            display: inline-block;
            margin-bottom: 22px;
            text-decoration: none;
            color: #914566;
            font-weight: bold;
        }

        h1 {
            color: #914566;
            margin-bottom: 10px;
        }

        .descripcion {
            color: #785c68;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .acciones {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .boton {
            display: inline-block;
            background: #b85d83;
            color: white;
            padding: 12px 18px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: bold;
            border: none;
        }

        .boton:hover {
            background: #974466;
        }

        .boton-secundario {
            background: #f0d3df;
            color: #713951;
        }

        .boton-secundario:hover {
            background: #e8bfd0;
        }

        .tabla-contenedor {
            background: white;
            border-radius: 15px;
            padding: 18px;
            box-shadow: 0 4px 15px #e9c9d6;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th, td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid #f1dce5;
        }

        th {
            background: #f9e2eb;
            color: #81435e;
        }

        tr:hover td {
            background: #fff9fb;
        }

        .estado {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            background: #f9e2eb;
            color: #81435e;
            font-size: 13px;
        }

        .mensaje {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 10px;
            background: #fff0f0;
            color: #963c3c;
        }

        .vacio {
            text-align: center;
            padding: 30px 15px;
            color: #785c68;
        }

        footer {
            text-align: center;
            padding: 25px;
            color: #926d7c;
        }
    </style>
</head>

<body>

<header>
    <div class="logo">🍪 Sweet Bite</div>
    <div class="usuario">
        Administrador: <strong>
            <?= htmlspecialchars($nombre_usuario) ?>
        </strong>
    </div>
</header>

<main class="contenedor">

    <a class="volver" href="index.php">← Volver al panel administrativo</a>

    <h1>📋 Catálogo de Galletas</h1>

    <p class="descripcion">
        Desde aquí puedes consultar los productos registrados,
        revisar sus precios y verificar las existencias disponibles.
        Esta pantalla es exclusiva del administrador.
    </p>

    <div class="acciones">
        <a class="boton" href="productos.php">
            ⚙️ Gestionar productos
        </a>

        <a class="boton boton-secundario" href="inventarios.php">
            📦 Revisar inventario
        </a>

        <a class="boton boton-secundario" href="index.php">
            🏠 Panel principal
        </a>
    </div>

    <?php if ($error !== ""): ?>
        <div class="mensaje">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <section class="tabla-contenedor">

        <?php if (count($productos) > 0): ?>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Producto</th>
                        <th>Descripción</th>
                        <th>Precio</th>
                        <th>Stock actual</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($productos as $producto): ?>

                        <?php
                        $id = obtenerCampo(
                            $producto,
                            ['id_producto', 'id'],
                            '—'
                        );

                        $nombre = obtenerCampo(
                            $producto,
                            ['nombre_producto', 'nombre', 'producto'],
                            'Producto sin nombre'
                        );

                        $descripcionProducto = obtenerCampo(
                            $producto,
                            ['descripcion', 'detalle', 'descripcion_producto'],
                            'Sin descripción'
                        );

                        $precio = obtenerCampo(
                            $producto,
                            ['precio', 'precio_venta', 'precio_unitario'],
                            null
                        );

                        $stock = $inventario[$id] ?? 'No registrado';
                        ?>

                        <tr>
                            <td><?= htmlspecialchars((string)$id) ?></td>

                            <td>
                                <strong>
                                    <?= htmlspecialchars((string)$nombre) ?>
                                </strong>
                            </td>

                            <td>
                                <?= htmlspecialchars((string)$descripcionProducto) ?>
                            </td>

                            <td>
                                <?php if ($precio !== null && is_numeric($precio)): ?>
                                    Bs <?= number_format((float)$precio, 2) ?>
                                <?php else: ?>
                                    No registrado
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="estado">
                                    <?= htmlspecialchars((string)$stock) ?>
                                </span>
                            </td>
                        </tr>

                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php else: ?>

            <div class="vacio">
                <h3>🍪 Todavía no hay productos para mostrar</h3>
                <p>Registra o revisa tus productos en la sección de gestión.</p>
                <br>
                <a class="boton" href="productos.php">
                    Ir a productos
                </a>
            </div>

        <?php endif; ?>

    </section>

</main>

<footer>
    Sweet Bite · Panel de administración
</footer>

</body>
</html>