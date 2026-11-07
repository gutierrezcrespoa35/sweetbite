<?php
session_start();
include('config/conexion.php');

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

if (isset($conexion) && $conexion instanceof mysqli) {
    $db = $conexion;
} elseif (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
} else {
    die("Error: revisa config/conexion.php.");
}

$nombre_usuario = $_SESSION['nombre_completo'] ?? $_SESSION['usuario'];
$mensaje = '';
$tipo_mensaje = 'exito';

if (isset($_SESSION['mensaje_pedidos'])) {
    $mensaje = $_SESSION['mensaje_pedidos'];
    $tipo_mensaje = $_SESSION['tipo_mensaje_pedidos'] ?? 'exito';
    unset($_SESSION['mensaje_pedidos'], $_SESSION['tipo_mensaje_pedidos']);
}

function volverPedidos($mensaje, $tipo = 'exito') {
    $_SESSION['mensaje_pedidos'] = $mensaje;
    $_SESSION['tipo_mensaje_pedidos'] = $tipo;
    header("Location: pedidos.php");
    exit();
}

/* ACTUALIZAR ESTADO */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['accion'] ?? '') === 'actualizar_estado') {

    $id = filter_var($_POST['id_pedido'] ?? '', FILTER_VALIDATE_INT);
    $estado = $_POST['estado'] ?? '';
    $estados = ['Pendiente', 'En proceso', 'Entregado', 'Cancelado'];

    if (!$id || !in_array($estado, $estados, true)) {
        volverPedidos('Los datos enviados no son válidos.', 'error');
    }

    try {
        $db->begin_transaction();

        $stmt = $db->prepare(
            "SELECT estado FROM pedidos WHERE id_pedido = ? FOR UPDATE"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $pedido = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$pedido) {
            throw new Exception('No se encontró el pedido.');
        }

        $anterior = $pedido['estado'];

        if ($anterior === 'Entregado' && $estado !== 'Entregado') {
            throw new Exception('No se puede modificar un pedido entregado.');
        }

        if ($anterior === 'Cancelado' && $estado !== 'Cancelado') {
            throw new Exception('No se puede reactivar un pedido cancelado.');
        }

        $stmt = $db->prepare(
            "SELECT COUNT(*) AS cantidad FROM pagos WHERE id_pedido = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $pagos = (int)$stmt->get_result()->fetch_assoc()['cantidad'];
        $stmt->close();

        if ($estado === 'Cancelado' && $pagos > 0) {
            throw new Exception('No se puede cancelar un pedido con pagos registrados.');
        }

        if ($estado === 'Cancelado' && $anterior !== 'Cancelado') {
            $stmt = $db->prepare(
                "SELECT id_producto, cantidad FROM detalle_pedido WHERE id_pedido = ?"
            );
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $detalles = $stmt->get_result();

            while ($d = $detalles->fetch_assoc()) {
                $stock = $db->prepare(
                    "UPDATE inventario SET stock_actual = stock_actual + ? WHERE id_producto = ?"
                );
                $cantidad = (int)$d['cantidad'];
                $id_producto = (int)$d['id_producto'];
                $stock->bind_param("ii", $cantidad, $id_producto);
                $stock->execute();
                $stock->close();
            }
            $stmt->close();
        }

        $stmt = $db->prepare(
            "UPDATE pedidos SET estado = ? WHERE id_pedido = ?"
        );
        $stmt->bind_param("si", $estado, $id);
        $stmt->execute();
        $stmt->close();

        $db->commit();
        volverPedidos('Estado actualizado correctamente.');

    } catch (Throwable $e) {
        $db->rollback();
        volverPedidos('No se pudo actualizar: ' . $e->getMessage(), 'error');
    }
}

/* ELIMINAR PEDIDO */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['accion'] ?? '') === 'eliminar') {

    $id = filter_var($_POST['id_pedido'] ?? '', FILTER_VALIDATE_INT);

    if (!$id) {
        volverPedidos('El número de pedido no es válido.', 'error');
    }

    try {
        $db->begin_transaction();

        $stmt = $db->prepare(
            "SELECT estado FROM pedidos WHERE id_pedido = ? FOR UPDATE"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $pedido = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$pedido) {
            throw new Exception('No se encontró el pedido.');
        }

        if (!in_array($pedido['estado'], ['Pendiente', 'Cancelado'], true)) {
            throw new Exception('Solo puedes eliminar pedidos pendientes o cancelados.');
        }

        $stmt = $db->prepare(
            "SELECT COUNT(*) AS cantidad FROM pagos WHERE id_pedido = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $pagos = (int)$stmt->get_result()->fetch_assoc()['cantidad'];
        $stmt->close();

        if ($pagos > 0) {
            throw new Exception('No puedes eliminar un pedido con pagos registrados.');
        }

        if ($pedido['estado'] === 'Pendiente') {
            $stmt = $db->prepare(
                "SELECT id_producto, cantidad FROM detalle_pedido WHERE id_pedido = ?"
            );
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $detalles = $stmt->get_result();

            while ($d = $detalles->fetch_assoc()) {
                $stock = $db->prepare(
                    "UPDATE inventario SET stock_actual = stock_actual + ? WHERE id_producto = ?"
                );
                $cantidad = (int)$d['cantidad'];
                $id_producto = (int)$d['id_producto'];
                $stock->bind_param("ii", $cantidad, $id_producto);
                $stock->execute();
                $stock->close();
            }
            $stmt->close();
        }

        $stmt = $db->prepare(
            "DELETE FROM detalle_pedido WHERE id_pedido = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare(
            "DELETE FROM pedidos WHERE id_pedido = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $db->commit();
        volverPedidos('Pedido eliminado correctamente.');

    } catch (Throwable $e) {
        $db->rollback();
        volverPedidos('No se pudo eliminar: ' . $e->getMessage(), 'error');
    }
}

/* BÚSQUEDA Y FILTROS */
$buscar = trim($_GET['buscar'] ?? '');
$filtro = trim($_GET['estado'] ?? '');
$estados_validos = ['Pendiente', 'En proceso', 'Entregado', 'Cancelado'];

$sql = "
    SELECT p.*,
        (SELECT COUNT(*) FROM detalle_pedido d
         WHERE d.id_pedido = p.id_pedido) AS cantidad_productos,
        (SELECT COUNT(*) FROM pagos pg
         WHERE pg.id_pedido = p.id_pedido) AS cantidad_pagos
    FROM pedidos p
    WHERE 1=1
";

$tipos = '';
$params = [];

if ($buscar !== '') {
    $sql .= " AND (
        CAST(p.id_pedido AS CHAR) LIKE ?
        OR CAST(p.id_usuario AS CHAR) LIKE ?
        OR p.estado LIKE ?
        OR p.modalidad_entrega LIKE ?
    )";

    $termino = "%$buscar%";
    $tipos .= 'ssss';
    array_push($params, $termino, $termino, $termino, $termino);
}

if (in_array($filtro, $estados_validos, true)) {
    $sql .= " AND p.estado = ?";
    $tipos .= 's';
    $params[] = $filtro;
}

$sql .= " ORDER BY p.id_pedido DESC";

$stmt = $db->prepare($sql);

if ($tipos !== '') {
    $stmt->bind_param($tipos, ...$params);
}

$stmt->execute();
$resultado = $stmt->get_result();

/* DETALLE DEL PEDIDO */
$pedido_detalle = null;
$productos_pedido = [];
$pagos_pedido = [];

if (isset($_GET['ver'])) {
    $id_ver = filter_var($_GET['ver'], FILTER_VALIDATE_INT);

    if ($id_ver) {
        $stmt = $db->prepare(
            "SELECT * FROM pedidos WHERE id_pedido = ?"
        );
        $stmt->bind_param("i", $id_ver);
        $stmt->execute();
        $pedido_detalle = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($pedido_detalle) {
            $stmt = $db->prepare("
                SELECT d.id_producto, d.cantidad, d.precio_unitario,
                       d.subtotal, pr.nombre
                FROM detalle_pedido d
                INNER JOIN productos pr
                    ON pr.id_producto = d.id_producto
                WHERE d.id_pedido = ?
            ");
            $stmt->bind_param("i", $id_ver);
            $stmt->execute();
            $res = $stmt->get_result();

            while ($fila = $res->fetch_assoc()) {
                $productos_pedido[] = $fila;
            }
            $stmt->close();

            $stmt = $db->prepare(
                "SELECT * FROM pagos WHERE id_pedido = ? ORDER BY id_pago DESC"
            );
            $stmt->bind_param("i", $id_ver);
            $stmt->execute();
            $res = $stmt->get_result();

            while ($fila = $res->fetch_assoc()) {
                $pagos_pedido[] = $fila;
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pedidos | Sweet Bite</title>

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
    color: #4a2435;
}

/* ENCABEZADO, IGUAL AL DISEÑO DE CARRITOS */
header {
    background-color: white;
    padding: 15px 35px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
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

/* DISTRIBUCIÓN GENERAL */
.main-container {
    display: flex;
    align-items: stretch;
    width: 100%;
    min-width: 0;
    min-height: calc(100vh - 65px);
}

/* MENÚ LATERAL: MISMO CSS QUE CARRITOS.PHP */
aside {
    width: 240px;
    min-width: 240px;
    flex-shrink: 0;
    position: relative;
    background: white;
    padding: 25px 15px;
    border-right: 2px solid #f8bbd0;
    min-height: calc(100vh - 65px);
    height: auto;
    overflow-y: visible;
    z-index: 1;
}

aside p {
    font-size: 11px;
    font-weight: bold;
    color: #f48fb1;
    text-transform: uppercase;
    margin-bottom: 12px;
    padding-left: 10px;
    text-align: left;
    letter-spacing: normal;
}

aside ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

aside ul li {
    margin-bottom: 6px;
    padding: 0;
}

aside ul li a,
aside ul li a:visited {
    display: block;
    padding: 11px 15px;
    color: #ad1457 !important;
    text-decoration: none !important;
    font-weight: 600;
    border-radius: 12px;
    font-size: 14px;
    line-height: 1.4;
    transition: background-color 0.2s ease;
}

aside ul li a:hover,
aside ul li a:focus,
aside ul li a.active {
    background-color: #f8bbd0;
    color: #880e4f !important;
}

aside ul li:last-child a {
    color: #c62828 !important;
}

/* CONTENIDO */
.contenido-principal {
    flex: 1;
    min-width: 0;
    padding: 35px;
}

.encabezado {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 22px;
}

h1, h2, h3 {
    color: #880e4f;
}

h1 {
    margin: 0 0 8px;
    font-size: 26px;
}

h2 {
    margin-bottom: 15px;
    font-size: 22px;
}

h3 {
    margin: 22px 0 12px;
    font-size: 18px;
}

.subtitulo {
    color: #ad1457;
    margin: 0;
}

.tarjeta {
    background: white;
    padding: 25px;
    border-radius: 20px;
    border: 2px solid #f8bbd0;
    box-shadow: 0 4px 15px rgba(233, 30, 99, 0.05);
    margin-bottom: 25px;
    min-width: 0;
}

.mensaje {
    padding: 13px 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-weight: 600;
}

.mensaje.exito {
    background: #e8f5e9;
    color: #256029;
    border: 1px solid #a5d6a7;
}

.mensaje.error {
    background: #ffebee;
    color: #b71c1c;
    border: 1px solid #ef9a9a;
}

/* BÚSQUEDA */
.filtros, .acciones, .form-estado {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
}

input, select {
    padding: 10px 12px;
    border: 1px solid #f8bbd0;
    border-radius: 8px;
    font-size: 14px;
    background: white;
    color: #4a2435;
    max-width: 100%;
}

.filtros input {
    flex: 1;
    min-width: 180px;
}

/* BOTONES */
button, .boton {
    display: inline-block;
    border: none;
    border-radius: 10px;
    padding: 10px 14px;
    cursor: pointer;
    text-decoration: none;
    font-size: 13px;
    font-weight: bold;
    text-align: center;
}

.boton-principal {
    background: #ec407a;
    color: white;
}

.boton-principal:hover {
    background: #d81b60;
}

.boton-secundario {
    background: #f8bbd0;
    color: #880e4f;
}

.boton-secundario:hover {
    background: #f48fb1;
}

.boton-peligro {
    background: #ffebee;
    color: #b71c1c;
}

.boton-peligro:hover {
    background: #ffcdd2;
}

/* TABLAS */
.tabla-contenedor {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
    margin-top: 15px;
}

th {
    background: #f8bbd0;
    color: #880e4f;
    text-align: left;
    padding: 12px;
    font-size: 14px;
    white-space: nowrap;
}

td {
    padding: 12px;
    border-bottom: 1px solid #f8bbd0;
    color: #ad1457;
    font-size: 14px;
    vertical-align: middle;
}

tr:hover td {
    background: #fff9fb;
}

.estado {
    display: inline-block;
    padding: 6px 9px;
    border-radius: 18px;
    font-size: 12px;
    font-weight: bold;
    white-space: nowrap;
}

.pendiente {
    background: #fff3cd;
    color: #856404;
}

.en-proceso {
    background: #e3f2fd;
    color: #1565c0;
}

.entregado {
    background: #e8f5e9;
    color: #2e7d32;
}

.cancelado {
    background: #ffebee;
    color: #c62828;
}

.form-estado select {
    max-width: 130px;
    padding: 8px;
}

.acciones {
    min-width: 180px;
}

.detalle-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 12px;
    margin: 18px 0;
}

.dato {
    background: #fff5f8;
    border-radius: 10px;
    padding: 14px;
    overflow-wrap: anywhere;
}

.dato small {
    display: block;
    color: #9b6179;
    margin-bottom: 7px;
}

.dato strong {
    color: #880e4f;
}

.sin-resultados {
    text-align: center;
    padding: 25px;
    color: #ad1457;
}

/* ADAPTACIÓN A CELULARES */
@media (max-width: 800px) {
    .main-container {
        flex-direction: column;
    }

    aside {
        width: 100%;
        min-width: 0;
        min-height: auto;
        height: auto;
        border-right: none;
        border-bottom: 2px solid #f8bbd0;
        padding: 20px 15px;
    }

    aside ul {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 5px;
    }

    aside ul li {
        margin-bottom: 0;
    }

    .contenido-principal {
        padding: 18px;
    }

    header {
        padding: 15px;
    }

    .tarjeta {
        padding: 15px;
    }
}

@media (max-width: 420px) {
    aside ul {
        grid-template-columns: 1fr;
    }

    .contenido-principal {
        padding: 12px;
    }

    .filtros input {
        min-width: 100%;
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

    <!-- MENÚ CON EL MISMO DISEÑO DE CARRITOS -->
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
            <li><a href="detalle_carrito.php">🛒 Detalle Carrito</a></li>
            <li><a href="pedidos.php" class="active">📋 Pedidos</a></li>
            <li><a href="detalle_pedido.php">📋 Detalle Pedido</a></li>
            <li><a href="pagos.php">💳 Pagos</a></li>
            <li><a href="registrar_usuario.php">👥 Registrar Usuarias</a></li>
            <li><a href="pedidos_especiales.php">📦 Pedidos Especiales</a></li>
            <li><a href="ventas_reportes.php">📊 Ventas / Reportes</a></li>
            <li><a href="cerrar_sesion.php" style="color:#c62828;">🚪 Cerrar Sesión</a></li>
        </ul>
    </aside>

    <main class="contenido-principal">

        <div class="encabezado">
            <div>
                <h1>Gestión de pedidos</h1>
                <p class="subtitulo">Administra los pedidos de Sweet Bite.</p>
            </div>
        </div>

        <?php if ($mensaje !== ''): ?>
            <div class="mensaje <?= $tipo_mensaje === 'error' ? 'error' : 'exito' ?>">
                <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['ver'])): ?>

            <div class="tarjeta">
                <div class="encabezado">
                    <h2>Detalle del pedido</h2>
                    <a href="pedidos.php" class="boton boton-secundario">Volver</a>
                </div>

                <?php if (!$pedido_detalle): ?>
                    <p class="sin-resultados">No se encontró el pedido.</p>
                <?php else: ?>

                    <div class="detalle-grid">
                        <div class="dato">
                            <small>Número</small>
                            <strong>#<?= (int)$pedido_detalle['id_pedido'] ?></strong>
                        </div>
                        <div class="dato">
                            <small>Usuario</small>
                            <strong><?= (int)$pedido_detalle['id_usuario'] ?></strong>
                        </div>
                        <div class="dato">
                            <small>Fecha</small>
                            <strong><?= htmlspecialchars($pedido_detalle['fecha_pedido'] ?? '') ?></strong>
                        </div>
                        <div class="dato">
                            <small>Estado</small>
                            <strong><?= htmlspecialchars($pedido_detalle['estado']) ?></strong>
                        </div>
                        <div class="dato">
                            <small>Entrega</small>
                            <strong><?= htmlspecialchars($pedido_detalle['modalidad_entrega']) ?></strong>
                        </div>
                        <div class="dato">
                            <small>Total</small>
                            <strong>Bs <?= number_format((float)$pedido_detalle['total'], 2) ?></strong>
                        </div>
                    </div>

                    <h3>Productos del pedido</h3>
                    <div class="tabla-contenedor">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID producto</th>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Precio unitario</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($productos_pedido): ?>
                                <?php foreach ($productos_pedido as $p): ?>
                                    <tr>
                                        <td><?= (int)$p['id_producto'] ?></td>
                                        <td><?= htmlspecialchars($p['nombre']) ?></td>
                                        <td><?= (int)$p['cantidad'] ?></td>
                                        <td>Bs <?= number_format((float)$p['precio_unitario'], 2) ?></td>
                                        <td>Bs <?= number_format((float)$p['subtotal'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="sin-resultados">
                                        No hay productos registrados para este pedido.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <h3>Pagos registrados</h3>
                    <div class="tabla-contenedor">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID pago</th>
                                    <th>Método</th>
                                    <th>Monto</th>
                                    <th>Fecha</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($pagos_pedido): ?>
                                <?php foreach ($pagos_pedido as $p): ?>
                                    <tr>
                                        <td><?= (int)$p['id_pago'] ?></td>
                                        <td><?= htmlspecialchars($p['metodo_pago']) ?></td>
                                        <td>Bs <?= number_format((float)$p['monto'], 2) ?></td>
                                        <td><?= htmlspecialchars($p['fecha_pago'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($p['estado']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="sin-resultados">
                                        No hay pagos registrados.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                <?php endif; ?>
            </div>

        <?php else: ?>

            <div class="tarjeta">
                <form method="GET" action="pedidos.php" class="filtros">
                    <input
                        type="text"
                        name="buscar"
                        placeholder="Buscar pedido, usuario o estado..."
                        value="<?= htmlspecialchars($buscar) ?>"
                    >

                    <select name="estado">
                        <option value="">Todos los estados</option>
                        <?php foreach ($estados_validos as $e): ?>
                            <option
                                value="<?= htmlspecialchars($e) ?>"
                                <?= $filtro === $e ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($e) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="boton-principal">Buscar</button>
                    <a href="pedidos.php" class="boton boton-secundario">Limpiar</a>
                </form>
            </div>

            <div class="tarjeta">
                <div class="tabla-contenedor">
                    <table>
                        <thead>
                            <tr>
                                <th>N.º pedido</th>
                                <th>Usuario</th>
                                <th>Fecha</th>
                                <th>Productos</th>
                                <th>Total</th>
                                <th>Entrega</th>
                                <th>Estado</th>
                                <th>Pagos</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>

                        <?php if ($resultado->num_rows > 0): ?>
                            <?php while ($f = $resultado->fetch_assoc()): ?>

                                <?php
                                $clase = '';

                                if ($f['estado'] === 'Pendiente') {
                                    $clase = 'pendiente';
                                } elseif ($f['estado'] === 'En proceso') {
                                    $clase = 'en-proceso';
                                } elseif ($f['estado'] === 'Entregado') {
                                    $clase = 'entregado';
                                } elseif ($f['estado'] === 'Cancelado') {
                                    $clase = 'cancelado';
                                }
                                ?>

                                <tr>
                                    <td>#<?= (int)$f['id_pedido'] ?></td>
                                    <td><?= (int)$f['id_usuario'] ?></td>
                                    <td><?= htmlspecialchars($f['fecha_pedido'] ?? '') ?></td>
                                    <td><?= (int)$f['cantidad_productos'] ?></td>
                                    <td>Bs <?= number_format((float)$f['total'], 2) ?></td>
                                    <td><?= htmlspecialchars($f['modalidad_entrega']) ?></td>
                                    <td>
                                        <span class="estado <?= $clase ?>">
                                            <?= htmlspecialchars($f['estado']) ?>
                                        </span>
                                    </td>
                                    <td><?= (int)$f['cantidad_pagos'] ?></td>

                                    <td>
                                        <div class="acciones">

                                            <a
                                                href="pedidos.php?ver=<?= (int)$f['id_pedido'] ?>"
                                                class="boton boton-secundario"
                                            >
                                                Ver
                                            </a>

                                            <?php if (!in_array($f['estado'], ['Entregado', 'Cancelado'], true)): ?>
                                                <form method="POST" class="form-estado">
                                                    <input type="hidden" name="accion" value="actualizar_estado">
                                                    <input type="hidden" name="id_pedido" value="<?= (int)$f['id_pedido'] ?>">

                                                    <select name="estado" required>
                                                        <?php foreach ($estados_validos as $e): ?>
                                                            <option
                                                                value="<?= htmlspecialchars($e) ?>"
                                                                <?= $f['estado'] === $e ? 'selected' : '' ?>
                                                            >
                                                                <?= htmlspecialchars($e) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>

                                                    <button type="submit" class="boton-principal">
                                                        Guardar
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if (in_array($f['estado'], ['Pendiente', 'Cancelado'], true)): ?>
                                                <form
                                                    method="POST"
                                                    onsubmit="return confirm('¿Seguro que deseas eliminar este pedido?');"
                                                >
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="id_pedido" value="<?= (int)$f['id_pedido'] ?>">

                                                    <button type="submit" class="boton-peligro">
                                                        Eliminar
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                        </div>
                                    </td>
                                </tr>

                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="sin-resultados">
                                    No se encontraron pedidos.
                                </td>
                            </tr>
                        <?php endif; ?>

                        </tbody>
                    </table>
                </div>
            </div>

        <?php endif; ?>

    </main>
</div>

</body>
</html>