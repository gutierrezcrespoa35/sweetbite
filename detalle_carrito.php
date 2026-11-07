<?php
session_start();
include('config/conexion.php');
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}
$nombre_usuario = $_SESSION['nombre_completo'] ?? $_SESSION['usuario'];
$id_usuario = (int)($_SESSION['id_usuario'] ?? 1);
$mensaje = "";
$tipo_mensaje = $_SESSION["tipo_mensaje_carrito"] ?? "success";
// Recuperar mensaje de una operación anterior
if (isset($_SESSION['mensaje_carrito'])) {
    $mensaje = $_SESSION['mensaje_carrito'];
    unset($_SESSION['mensaje_carrito']);
    unset($_SESSION["tipo_mensaje_carrito"]);
}
// Obtener el carrito activo del usuario
$stmt_carrito = $conexion->prepare("
    SELECT id_carrito
    FROM carritos
    WHERE id_usuario = ? AND estado = 'activo'
    ORDER BY id_carrito DESC
    LIMIT 1
");
$stmt_carrito->bind_param("i", $id_usuario);
$stmt_carrito->execute();
$res_carrito = $stmt_carrito->get_result();
if ($res_carrito->num_rows > 0) {
    $fila_carrito = $res_carrito->fetch_assoc();
    $id_carrito = (int)$fila_carrito['id_carrito'];
} else {
    $stmt_carrito->close();
    $stmt_nuevo = $conexion->prepare("
        INSERT INTO carritos (id_usuario, estado)
        VALUES (?, 'activo')
    ");
    $stmt_nuevo->bind_param("i", $id_usuario);
    $stmt_nuevo->execute();
    $id_carrito = (int)$stmt_nuevo->insert_id;
    $stmt_nuevo->close();
    $stmt_carrito = null;
}
if ($stmt_carrito !== null) {
    $stmt_carrito->close();
}
// Eliminar un producto únicamente del carrito activo de esta cuenta.
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["accion"] ?? "") === "eliminar_producto"
) {
    $id_detalle_eliminar = filter_var(
        $_POST["id_detalle_carrito"] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$id_detalle_eliminar || $id_detalle_eliminar < 1) {
        $_SESSION["mensaje_carrito"] = "No se pudo identificar el producto.";
    } else {
        $stmt_eliminar = $conexion->prepare(
            "DELETE FROM detalle_carrito
             WHERE id_detalle_carrito = ? AND id_carrito = ?"
        );
        $stmt_eliminar->bind_param(
            "ii",
            $id_detalle_eliminar,
            $id_carrito
        );
        $stmt_eliminar->execute();
        $_SESSION["mensaje_carrito"] = $stmt_eliminar->affected_rows > 0
            ? "El producto se eliminó del carrito."
            : "Ese producto ya no está en tu carrito.";
        $stmt_eliminar->close();
    }

    header("Location: detalle_carrito.php");
    exit();
}
// Consultar los productos del carrito activo
$stmt_detalle = $conexion->prepare("
    SELECT
        dc.id_detalle_carrito,
        dc.id_producto,
        dc.cantidad,
        p.nombre,
        p.precio,
        i.stock_actual,
        i.stock_minimo
    FROM detalle_carrito dc
    INNER JOIN productos p
        ON dc.id_producto = p.id_producto
    LEFT JOIN inventario i
        ON dc.id_producto = i.id_producto
    WHERE dc.id_carrito = ?
    ORDER BY dc.id_detalle_carrito ASC
");
$stmt_detalle->bind_param("i", $id_carrito);
$stmt_detalle->execute();
$resultado_detalle = $stmt_detalle->get_result();
$productos = [];
$total = 0;
$hay_stock_insuficiente = false;
while ($fila = $resultado_detalle->fetch_assoc()) {
    $fila['cantidad'] = (int)$fila['cantidad'];
    $fila['precio'] = (float)$fila['precio'];
    $fila['subtotal'] = $fila['cantidad'] * $fila['precio'];
    $stock = $fila['stock_actual'];
    if (
        $stock === null ||
        (int)$stock < $fila['cantidad'] ||
        $fila['cantidad'] <= 0
    ) {
        $hay_stock_insuficiente = true;
    }
    $total += $fila['subtotal'];
    $productos[] = $fila;
}
$stmt_detalle->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sweet Bite - Detalle del carrito</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            background-color: #fce4ec;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        header {
            background-color: white;
            padding: 15px 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            border-bottom: 2px solid #f8bbd0;
        }
        .logo {
            font-size: 22px;
            font-weight: bold;
            color: #880e4f;
        }
        .user-info {
            font-size: 14px;
            color: #ad1457;
            font-weight: 600;
        }
        .main-container {
            display: flex;
            flex: 1;
            align-items: stretch;
            width: 100%;
            min-width: 0;
        }
        aside {
            width: 240px;
            min-width: 240px;
            flex-shrink: 0;
            background: white;
            padding: 25px 15px;
            border-right: 2px solid #f8bbd0;
            min-height: 100vh;
        }
        aside p {
            font-size: 11px;
            font-weight: bold;
            color: #f48fb1;
            text-transform: uppercase;
            margin-bottom: 12px;
            padding-left: 10px;
        }
        aside ul {
            list-style: none;
        }
        aside ul li {
            margin-bottom: 6px;
        }
        aside ul li a {
            display: block;
            padding: 11px 15px;
            color: #ad1457;
            text-decoration: none;
            font-weight: 600;
            border-radius: 12px;
            font-size: 14px;
            transition: 0.2s;
        }
        aside ul li a:hover,
        aside ul li a.active {
            background-color: #f8bbd0;
            color: #880e4f;
        }
        .content {
            flex: 1;
            min-width: 0;
            padding: 35px;
        }
        .card-box {
            background: white;
            padding: 25px;
            border-radius: 20px;
            border: 2px solid #f8bbd0;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(233, 30, 99, 0.05);
        }
        .encabezado-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 8px;
        }
        h2 {
            color: #880e4f;
            font-size: 22px;
            margin-bottom: 10px;
        }
        .descripcion {
            color: #ad1457;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        .numero-carrito {
            background: #fce4ec;
            color: #880e4f;
            border: 1px solid #f8bbd0;
            padding: 8px 12px;
            border-radius: 10px;
            font-weight: bold;
            font-size: 13px;
        }
        .tabla-contenedor {
            width: 100%;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            min-width: 650px;
        }
        table th {
            background-color: #f8bbd0;
            color: #880e4f;
            text-align: left;
            padding: 13px 12px;
            font-size: 13px;
            white-space: nowrap;
        }
        table td {
            padding: 13px 12px;
            border-bottom: 1px solid #f8bbd0;
            color: #ad1457;
            font-size: 14px;
        }
        table tbody tr:hover {
            background-color: #fff5f8;
        }
        .nombre-producto {
            font-weight: 600;
            color: #880e4f;
        }
        .producto-con-eliminar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            min-width: 170px;
        }
        .btn-eliminar-producto {
            flex: 0 0 32px;
            width: 32px;
            height: 32px;
            border: 0;
            border-radius: 50%;
            background: #ffebee;
            color: #c62828;
            font-size: 22px;
            font-weight: bold;
            line-height: 1;
            cursor: pointer;
        }
        .btn-eliminar-producto:hover {
            background: #c62828;
            color: #fff;
        }
        .stock-ok {
            display: inline-block;
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 6px 9px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }
        .stock-bajo {
            display: inline-block;
            background-color: #fff3e0;
            color: #e65100;
            padding: 6px 9px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }
        .total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 22px;
            padding: 18px;
            background-color: #fce4ec;
            border: 1px solid #f8bbd0;
            border-radius: 12px;
            color: #880e4f;
            font-size: 18px;
            font-weight: bold;
        }
        .total strong {
            font-size: 23px;
        }
        .acciones {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }
        .btn {
            display: inline-block;
            text-decoration: none;
            color: white;
            border: none;
            padding: 11px 17px;
            font-weight: bold;
            border-radius: 10px;
            cursor: pointer;
            font-size: 13px;
            text-align: center;
            transition: 0.2s;
        }
        .btn-submit {
            background-color: #ec407a;
        }
        .btn-submit:hover {
            background-color: #d81b60;
        }
        .btn-secondary {
            background-color: #ad1457;
        }
        .btn-secondary:hover {
            background-color: #880e4f;
        }
        .btn-seguir {
            background-color: #f8bbd0;
            color: #880e4f;
        }
        .btn-seguir:hover {
            background-color: #f48fb1;
        }
        .alert {
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
            line-height: 1.5;
        }
        .alert.success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }
        .alert.error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }
        .sin-productos {
            text-align: center;
            padding: 35px 15px;
            color: #ad1457;
        }
        .sin-productos .emoji {
            font-size: 40px;
            margin-bottom: 12px;
        }
        .sin-productos h3 {
            color: #880e4f;
            margin-bottom: 8px;
        }
        .sin-productos p {
            font-size: 14px;
            line-height: 1.6;
        }
        .nota-stock {
            margin-top: 14px;
            color: #ad1457;
            font-size: 12px;
            line-height: 1.5;
        }
        @media (max-width: 800px) {
            .main-container {
                flex-direction: column;
            }
            aside {
                width: 100%;
                min-width: 0;
                min-height: auto;
                border-right: none;
                border-bottom: 2px solid #f8bbd0;
            }
            aside ul {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 5px;
            }
            aside ul li {
                margin-bottom: 0;
            }
            header {
                padding: 15px;
                flex-wrap: wrap;
            }
            .content {
                padding: 18px;
            }
            .card-box {
                padding: 16px;
            }
            .acciones .btn {
                flex: 1 1 150px;
            }
        }
    </style>
</head>
<body>
<header>
    <div class="logo">🍪 Sweet Bite Bakery - Panel de Ventas</div>
    <div class="user-info">
        ¡Hola, <?= htmlspecialchars($nombre_usuario, ENT_QUOTES, 'UTF-8') ?>! 🌸
    </div>
</header>
<div class="main-container">
    <aside>
        <p>MENÚ REPOSTERÍA</p>
        <ul>
            <li><a href="index.php">🏠 Inicio</a></li>
            <li><a href="roles.php">🔑 Roles</a></li>
            <li><a href="categorias.php">🏷️ Categorías</a></li>
            <li><a href="productos.php">🍪 Productos</a></li>
            <li><a href="catalogo_galletas.php">📜 Catálogo Galletas</a></li>
            <li><a href="inventarios.php">📦 Inventario</a></li>
            <li><a href="carritos.php">🛒 Carrito</a></li>
            <li><a href="detalle_carrito.php" class="active">🛒 Detalle Carrito</a></li>
            <li><a href="pedidos.php">📋 Pedidos</a></li>
            <li><a href="pagos.php">💳 Pagos</a></li>
            <li><a href="cerrar_sesion.php" style="color:#c62828;">🚪 Cerrar Sesión</a></li>
        </ul>
    </aside>
    <main class="content">
        <?php if ($mensaje !== ""): ?>
            <div class="alert <?= $tipo_mensaje === 'error' ? 'error' : 'success' ?>">
                <?= htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8") ?>
            </div>
        <?php endif; ?>
        <div class="card-box">
            <div class="encabezado-card">
                <h2>Detalle del carrito 🛒</h2>
                <span class="numero-carrito">
                    Carrito N.º <?= (int)$id_carrito ?>
                </span>
            </div>
            <p class="descripcion">
                Consulta los productos seleccionados, sus cantidades,
                precios y disponibilidad antes de finalizar tu compra.
            </p>
            <?php if (count($productos) > 0): ?>
                <div class="tabla-contenedor">
                    <table>
                        <thead>
                            <tr>
                                <th>N.º</th>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio unitario</th>
                                <th>Subtotal</th>
                                <th>Disponibilidad</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $numero = 1; ?>
                        <?php foreach ($productos as $d): ?>
                            <tr>
                                <td><?= $numero++ ?></td>
                                <td>
                                    <div class="producto-con-eliminar">
                                        <span class="nombre-producto">
                                            <?= htmlspecialchars(
                                                $d['nombre'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>
                                        <form method="POST" action="detalle_carrito.php">
                                            <input type="hidden" name="accion" value="eliminar_producto">
                                            <input type="hidden" name="id_detalle_carrito" value="<?= (int)$d['id_detalle_carrito'] ?>">
                                            <button
                                                type="submit"
                                                class="btn-eliminar-producto"
                                                aria-label="Eliminar <?= htmlspecialchars($d['nombre'], ENT_QUOTES, 'UTF-8') ?> del carrito"
                                                title="Eliminar del carrito"
                                            >×</button>
                                        </form>
                                    </div>
                                </td>
                                <td><?= (int)$d['cantidad'] ?></td>
                                <td>
                                    Bs <?= number_format($d['precio'], 2) ?>
                                </td>
                                <td>
                                    <strong>
                                        Bs <?= number_format($d['subtotal'], 2) ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php
                                    $stock = $d['stock_actual'];
                                    $cantidad = (int)$d['cantidad'];
                                    $minimo = (int)($d['stock_minimo'] ?? 0);
                                    if ($stock === null) {
                                        echo "<span class='stock-bajo'>Sin inventario</span>";
                                    } elseif ((int)$stock < $cantidad) {
                                        echo "<span class='stock-bajo'>Stock insuficiente: "
                                            . (int)$stock . "</span>";
                                    } elseif ((int)$stock <= $minimo) {
                                        echo "<span class='stock-bajo'>Stock bajo: "
                                            . (int)$stock . "</span>";
                                    } else {
                                        echo "<span class='stock-ok'>Disponible: "
                                            . (int)$stock . "</span>";
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="total">
                    <span>Total de la compra</span>
                    <strong>Bs <?= number_format($total, 2) ?></strong>
                </div>
                <p class="nota-stock">
                    * La disponibilidad puede cambiar antes de confirmar
                    la compra. El sistema debe verificar nuevamente el stock
                    al finalizar el pedido.
                </p>
            <?php else: ?>
                <div class="sin-productos">
                    <div class="emoji">🛒</div>
                    <h3>Tu carrito está vacío</h3>
                    <p>
                        Todavía no tienes galletas seleccionadas.
                        Visita nuestro catálogo y agrega tus productos favoritos.
                    </p>
                </div>
                <div class="total">
                    <span>Total de la compra</span>
                    <strong>Bs 0.00</strong>
                </div>
            <?php endif; ?>
            <div class="acciones">
                <a href="/sweetbite/catalogo_galletas.php" class="btn btn-seguir">
                    📜 Seguir comprando
                </a>
                <?php if (count($productos) > 0): ?>
                    <form method="POST" action="/sweetbite/crear_pedido.php">
                        <button type="submit" class="btn btn-secondary">
                            🛍️ Hacer pedido
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
</body>
</html>
