<?php
session_start();
require_once('config/conexion.php');

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$nombre_usuario = $_SESSION['nombre_completo'] ?? $_SESSION['usuario'];

if (!isset($_SESSION['csrf_detalle_pedido'])) {
    $_SESSION['csrf_detalle_pedido'] = bin2hex(random_bytes(32));
}

function e($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function redirigir() {
    header("Location: detalle_pedidos.php");
    exit();
}

function obtenerPedido($conexion, $id_pedido) {
    $stmt = $conexion->prepare(
        "SELECT id_pedido FROM pedidos WHERE id_pedido = ?"
    );
    $stmt->bind_param("i", $id_pedido);
    $stmt->execute();
    $pedido = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $pedido;
}

function obtenerProducto($conexion, $id_producto) {
    $stmt = $conexion->prepare(
        "SELECT id_producto, nombre, precio
         FROM productos WHERE id_producto = ?"
    );
    $stmt->bind_param("i", $id_producto);
    $stmt->execute();
    $producto = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $producto;
}

function actualizarTotalPedido($conexion, $id_pedido) {
    $stmt = $conexion->prepare(
        "UPDATE pedidos
         SET total = (
             SELECT COALESCE(SUM(subtotal), 0)
             FROM detalle_pedido
             WHERE id_pedido = ?
         )
         WHERE id_pedido = ?"
    );

    $stmt->bind_param("ii", $id_pedido, $id_pedido);
    $stmt->execute();
    $stmt->close();
}

$mensaje = "";
$tipo_mensaje = "success";

if (isset($_SESSION['mensaje_detalle'])) {
    $mensaje = $_SESSION['mensaje_detalle']['texto'];
    $tipo_mensaje = $_SESSION['mensaje_detalle']['tipo'];
    unset($_SESSION['mensaje_detalle']);
}

/* PROCESAR FORMULARIOS */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_detalle_pedido'], $token)
    ) {
        $_SESSION['mensaje_detalle'] = [
            'texto' => 'La sesión venció. Intenta nuevamente.',
            'tipo' => 'error'
        ];
        redirigir();
    }

    $accion = $_POST['accion'] ?? '';
    $transaccionIniciada = false;

    try {

        /* GUARDAR O ACTUALIZAR */
        if ($accion === 'guardar' || $accion === 'actualizar') {

            $id_pedido = filter_var(
                $_POST['id_pedido'] ?? null,
                FILTER_VALIDATE_INT
            );

            $id_producto = filter_var(
                $_POST['id_producto'] ?? null,
                FILTER_VALIDATE_INT
            );

            $cantidad = filter_var(
                $_POST['cantidad'] ?? null,
                FILTER_VALIDATE_INT
            );

            $id_detalle = filter_var(
                $_POST['id_detalle_pedido'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (
                !$id_pedido || $id_pedido < 1 ||
                !$id_producto || $id_producto < 1 ||
                !$cantidad || $cantidad < 1
            ) {
                throw new Exception(
                    'Selecciona un pedido, un producto y una cantidad válida.'
                );
            }

            if (
                $accion === 'actualizar' &&
                (!$id_detalle || $id_detalle < 1)
            ) {
                throw new Exception('El detalle seleccionado no es válido.');
            }

            $pedido = obtenerPedido($conexion, $id_pedido);
            $producto = obtenerProducto($conexion, $id_producto);

            if (!$pedido) {
                throw new Exception('El pedido seleccionado no existe.');
            }

            if (!$producto) {
                throw new Exception('El producto seleccionado no existe.');
            }

            $precio_unitario = (float)$producto['precio'];

            if (!is_finite($precio_unitario) || $precio_unitario < 0) {
                throw new Exception('El precio del producto no es válido.');
            }

            $subtotal = round($cantidad * $precio_unitario, 2);

            $conexion->begin_transaction();
            $transaccionIniciada = true;

            $pedidoAnterior = null;

            if ($accion === 'actualizar') {

                $stmt = $conexion->prepare(
                    "SELECT id_pedido
                     FROM detalle_pedido
                     WHERE id_detalle_pedido = ?"
                );

                $stmt->bind_param("i", $id_detalle);
                $stmt->execute();
                $pedidoAnterior = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$pedidoAnterior) {
                    throw new Exception('El detalle ya no existe.');
                }

                $id_pedido_anterior = (int)$pedidoAnterior['id_pedido'];

                $stmt = $conexion->prepare(
                    "UPDATE detalle_pedido
                     SET id_pedido = ?,
                         id_producto = ?,
                         cantidad = ?,
                         precio_unitario = ?,
                         subtotal = ?
                     WHERE id_detalle_pedido = ?"
                );

                $stmt->bind_param(
                    "iiiddi",
                    $id_pedido,
                    $id_producto,
                    $cantidad,
                    $precio_unitario,
                    $subtotal,
                    $id_detalle
                );

                $stmt->execute();
                $stmt->close();

                if ($id_pedido_anterior !== $id_pedido) {
                    actualizarTotalPedido($conexion, $id_pedido_anterior);
                }

                $mensaje_exito = 'Detalle actualizado correctamente.';

            } else {

                $stmt = $conexion->prepare(
                    "INSERT INTO detalle_pedido
                     (id_pedido, id_producto, cantidad,
                      precio_unitario, subtotal)
                     VALUES (?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "iiidd",
                    $id_pedido,
                    $id_producto,
                    $cantidad,
                    $precio_unitario,
                    $subtotal
                );

                $stmt->execute();
                $stmt->close();

                $mensaje_exito = 'Detalle registrado correctamente.';
            }

            actualizarTotalPedido($conexion, $id_pedido);

            $conexion->commit();
            $transaccionIniciada = false;

            $_SESSION['mensaje_detalle'] = [
                'texto' => $mensaje_exito .
                    ' El total del pedido se actualizó correctamente.',
                'tipo' => 'success'
            ];

            redirigir();
        }

        /* ELIMINAR */
        if ($accion === 'eliminar') {

            $id_detalle = filter_var(
                $_POST['id_detalle_pedido'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$id_detalle || $id_detalle < 1) {
                throw new Exception('El detalle seleccionado no es válido.');
            }

            $conexion->begin_transaction();
            $transaccionIniciada = true;

            $stmt = $conexion->prepare(
                "SELECT id_pedido
                 FROM detalle_pedido
                 WHERE id_detalle_pedido = ?"
            );

            $stmt->bind_param("i", $id_detalle);
            $stmt->execute();
            $detalle = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$detalle) {
                throw new Exception('El detalle no existe.');
            }

            $id_pedido_eliminado = (int)$detalle['id_pedido'];

            $stmt = $conexion->prepare(
                "DELETE FROM detalle_pedido
                 WHERE id_detalle_pedido = ?"
            );

            $stmt->bind_param("i", $id_detalle);
            $stmt->execute();
            $stmt->close();

            actualizarTotalPedido($conexion, $id_pedido_eliminado);

            $conexion->commit();
            $transaccionIniciada = false;

            $_SESSION['mensaje_detalle'] = [
                'texto' => 'Detalle eliminado y total actualizado.',
                'tipo' => 'success'
            ];

            redirigir();
        }

        throw new Exception('La acción solicitada no es válida.');

    } catch (Throwable $error) {

        if ($transaccionIniciada) {
            try {
                $conexion->rollback();
            } catch (Throwable $ignorado) {
                // La transacción ya no está activa.
            }
        }

        error_log('Error en detalle_pedidos.php: ' . $error->getMessage());

        $_SESSION['mensaje_detalle'] = [
            'texto' => 'No se pudo completar la operación. Revisa los datos e inténtalo nuevamente.',
            'tipo' => 'error'
        ];

        redirigir();
    }
}

/* CARGAR PEDIDOS Y PRODUCTOS */
$pedidos = $conexion->query(
    "SELECT id_pedido FROM pedidos ORDER BY id_pedido DESC"
);

$productos = $conexion->query(
    "SELECT id_producto, nombre, precio
     FROM productos ORDER BY nombre ASC"
);

/* DETALLE PARA EDITAR */
$detalle_editar = null;

if (isset($_GET['editar'])) {

    $id_editar = filter_var(
        $_GET['editar'],
        FILTER_VALIDATE_INT
    );

    if ($id_editar && $id_editar > 0) {

        $stmt = $conexion->prepare(
            "SELECT *
             FROM detalle_pedido
             WHERE id_detalle_pedido = ?"
        );

        $stmt->bind_param("i", $id_editar);
        $stmt->execute();
        $detalle_editar = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$detalle_editar) {
            $mensaje = 'El detalle solicitado no existe.';
            $tipo_mensaje = 'error';
        }
    }
}

/* TABLA DE DETALLES */
$resultado = $conexion->query(
    "SELECT
        dp.id_detalle_pedido,
        dp.id_pedido,
        dp.id_producto,
        p.nombre AS nombre_producto,
        dp.cantidad,
        dp.precio_unitario,
        dp.subtotal
     FROM detalle_pedido dp
     INNER JOIN pedidos pe ON dp.id_pedido = pe.id_pedido
     INNER JOIN productos p ON dp.id_producto = p.id_producto
     ORDER BY dp.id_detalle_pedido DESC"
);

/* ESTADÍSTICAS */
$estadisticas = $conexion->query(
    "SELECT
        COUNT(*) AS total_detalles,
        COALESCE(SUM(cantidad), 0) AS total_unidades,
        COALESCE(SUM(subtotal), 0) AS monto_total
     FROM detalle_pedido"
)->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sweet Bite | Detalle de pedidos</title>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}
body {
    background: #fff5f8;
    color: #4a2435;
    min-height: 100vh;
}
header {
    background: #fff;
    padding: 16px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    border-bottom: 2px solid #f8bbd0;
    box-shadow: 0 3px 12px #880e4f0b;
}
.logo {
    font-size: 21px;
    font-weight: 800;
    color: #880e4f;
}
.user-info {
    color: #ad1457;
    font-size: 14px;
    font-weight: 600;
}
.main-container {
    display: flex;
    min-height: calc(100vh - 65px);
}
aside {
    width: 240px;
    min-width: 240px;
    flex-shrink: 0;
    background: white;
    padding: 25px 15px;
    border-right: 2px solid #f8bbd0;
}
aside p {
    font-size: 11px;
    font-weight: 800;
    color: #f06292;
    margin-bottom: 14px;
    padding-left: 10px;
    letter-spacing: 1px;
}
aside ul { list-style: none; }
aside ul li { margin-bottom: 6px; }
aside ul li a {
    display: block;
    padding: 11px 13px;
    color: #ad1457;
    text-decoration: none;
    font-weight: 600;
    border-radius: 11px;
    font-size: 13px;
    transition: .2s;
}
aside ul li a:hover,
aside ul li a.active {
    background: #fce4ec;
    color: #880e4f;
}
.content {
    flex: 1;
    min-width: 0;
    padding: 30px;
}
.page-heading, .card-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}
.page-heading { margin-bottom: 24px; }
.page-heading h1 {
    color: #880e4f;
    font-size: 27px;
    margin-bottom: 5px;
}
.page-heading p {
    color: #946477;
    font-size: 14px;
}
.badge {
    display: inline-block;
    background: #fce4ec;
    color: #ad1457;
    padding: 7px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}
.stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 22px;
}
.stat-card, .card-box {
    background: white;
    border: 1px solid #f4c4d5;
    border-radius: 16px;
    box-shadow: 0 5px 18px #880e4f08;
}
.stat-card { padding: 20px; }
.stat-label {
    color: #946477;
    font-size: 13px;
    margin-bottom: 10px;
}
.stat-value {
    color: #880e4f;
    font-size: 25px;
    font-weight: 800;
}
.card-box {
    padding: 24px;
    margin-bottom: 22px;
}
.card-heading { margin-bottom: 20px; }
.card-heading h2 {
    color: #880e4f;
    font-size: 19px;
}
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}
.form-group { min-width: 0; }
label {
    display: block;
    margin-bottom: 7px;
    color: #8e3159;
    font-size: 13px;
    font-weight: 700;
}
input, select {
    width: 100%;
    padding: 12px 13px;
    border: 1px solid #e8b5c8;
    border-radius: 10px;
    outline: none;
    font-size: 14px;
    background: white;
    color: #4a2435;
}
input:focus, select:focus {
    border-color: #ec407a;
    box-shadow: 0 0 0 3px #fce4ec;
}
input[readonly] {
    background: #fff4f8;
    color: #880e4f;
    font-weight: 700;
}
.full-width { grid-column: 1 / -1; }
.total-preview {
    background: linear-gradient(135deg, #fce4ec, #fff2f7);
    border: 1px solid #f4c4d5;
    border-radius: 13px;
    padding: 17px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}
.total-preview span {
    color: #8e3159;
    font-weight: 600;
}
.total-preview strong {
    font-size: 23px;
    color: #880e4f;
}
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    border: none;
    border-radius: 10px;
    padding: 11px 16px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    transition: .2s;
}
.btn-primary { background: #c2185b; color: white; }
.btn-primary:hover { background: #880e4f; }
.btn-light { background: #fce4ec; color: #880e4f; }
.btn-light:hover { background: #f8bbd0; }
.btn-danger { background: #ffebee; color: #c62828; }
.btn-danger:hover { background: #ffcdd2; }
.actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.alert {
    padding: 14px 16px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 14px;
    font-weight: 600;
}
.alert.success {
    background: #e8f5e9;
    color: #256b35;
    border: 1px solid #c8e6c9;
}
.alert.error {
    background: #ffebee;
    color: #b71c1c;
    border: 1px solid #ffcdd2;
}
.search-box { max-width: 310px; }
.table-wrap {
    width: 100%;
    overflow-x: auto;
    border: 1px solid #f4d4df;
    border-radius: 12px;
}
table {
    width: 100%;
    border-collapse: collapse;
    min-width: 790px;
}
thead { background: #fce4ec; }
th {
    color: #880e4f;
    text-align: left;
    padding: 14px 12px;
    font-size: 12px;
    white-space: nowrap;
}
td {
    padding: 13px 12px;
    border-bottom: 1px solid #f7e1e9;
    color: #63364a;
    font-size: 13px;
}
tbody tr:hover { background: #fff8fb; }
tbody tr:last-child td { border-bottom: none; }
.product-name { font-weight: 700; color: #880e4f; }
.amount { font-weight: 700; white-space: nowrap; }
.empty-state {
    text-align: center;
    padding: 30px;
    color: #946477;
}
.select2-container { width: 100% !important; }
.select2-container--default .select2-selection--single {
    height: 44px;
    border: 1px solid #e8b5c8;
    border-radius: 10px;
    display: flex;
    align-items: center;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #4a2435;
    line-height: 42px;
    padding-left: 13px;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 42px;
    right: 7px;
}
.select2-dropdown {
    border-color: #f4c4d5;
    border-radius: 8px;
    overflow: hidden;
}
@media (max-width: 1050px) {
    .content { padding: 22px; }
    .stat-card { padding: 16px; }
}
@media (max-width: 800px) {
    .main-container { flex-direction: column; }
    aside {
        width: 100%;
        min-width: 0;
        padding: 15px;
        border-right: none;
        border-bottom: 2px solid #f8bbd0;
    }
    aside ul {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 5px;
    }
    aside ul li { margin-bottom: 0; }
    aside ul li a { padding: 10px; font-size: 12px; }
    .content { padding: 18px; }
    .stats, .form-grid { grid-template-columns: 1fr; }
    .full-width { grid-column: auto; }
    .card-box { padding: 17px; }
    header { padding: 15px; flex-wrap: wrap; }
    .logo { font-size: 18px; }
    .page-heading h1 { font-size: 23px; }
    .search-box { max-width: none; }
}
</style>
</head>
<body>

<header>
    <div class="logo">🍪 Sweet Bite Bakery</div>
    <div class="user-info">
        ¡Hola, <?= e($nombre_usuario) ?>! 🌸
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
        <li><a href="detalle_carrito.php">🛒 Detalle Carrito</a></li>
        <li><a href="pedidos.php">📦 Pedidos</a></li>
        <li><a href="detalle_pedidos.php" class="active">📋 Detalle Pedido</a></li>
        <li><a href="pagos.php">💳 Pagos</a></li>
        <li><a href="registrar_usuario.php">👥 Registrar Usuarias</a></li>
        <li><a href="ventas_reportes.php">📊 Ventas / Reportes</a></li>
        <li><a href="cerrar_sesion.php" style="color:#c62828;">🚪 Cerrar Sesión</a></li>
    </ul>
</aside>

<main class="content">

    <div class="page-heading">
        <div>
            <h1>Detalle de pedidos 📋</h1>
            <p>Administra los productos, cantidades e importes de cada pedido.</p>
        </div>
        <span class="badge">SWEET BITE · VENTAS</span>
    </div>

    <?php if ($mensaje !== ''): ?>
        <div class="alert <?= $tipo_mensaje === 'error' ? 'error' : 'success' ?>">
            <?= e($mensaje) ?>
        </div>
    <?php endif; ?>

    <section class="stats">
        <div class="stat-card">
            <div class="stat-label">Detalles registrados</div>
            <div class="stat-value">
                <?= number_format((int)$estadisticas['total_detalles']) ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Unidades en los pedidos</div>
            <div class="stat-value">
                <?= number_format((int)$estadisticas['total_unidades']) ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Importe de los detalles</div>
            <div class="stat-value">
                Bs. <?= number_format((float)$estadisticas['monto_total'], 2) ?>
            </div>
        </div>
    </section>

    <section class="card-box" id="formulario">
        <div class="card-heading">
            <h2>
                <?= $detalle_editar ? '✏️ Editar detalle' : '➕ Agregar detalle al pedido' ?>
            </h2>

            <?php if ($detalle_editar): ?>
                <a href="detalle_pedidos.php" class="btn btn-light">
                    Cancelar edición
                </a>
            <?php endif; ?>
        </div>

        <form method="POST" id="formDetalle">
            <input type="hidden" name="csrf_token"
                   value="<?= e($_SESSION['csrf_detalle_pedido']) ?>">

            <input type="hidden" name="accion"
                   value="<?= $detalle_editar ? 'actualizar' : 'guardar' ?>">

            <?php if ($detalle_editar): ?>
                <input type="hidden" name="id_detalle_pedido"
                       value="<?= (int)$detalle_editar['id_detalle_pedido'] ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="form-group">
                    <label for="id_pedido">Pedido *</label>
                    <select name="id_pedido" id="id_pedido" required>
                        <option value="">Selecciona un pedido</option>
                        <?php while ($p = $pedidos->fetch_assoc()): ?>
                            <option value="<?= (int)$p['id_pedido'] ?>"
                                <?= $detalle_editar &&
                                    (int)$detalle_editar['id_pedido'] === (int)$p['id_pedido']
                                    ? 'selected' : '' ?>>
                                Pedido #<?= (int)$p['id_pedido'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="id_producto">Producto *</label>
                    <select name="id_producto" id="id_producto" required>
                        <option value="">Busca o selecciona un producto</option>
                        <?php while ($p = $productos->fetch_assoc()): ?>
                            <option value="<?= (int)$p['id_producto'] ?>"
                                data-precio="<?= e($p['precio']) ?>"
                                <?= $detalle_editar &&
                                    (int)$detalle_editar['id_producto'] === (int)$p['id_producto']
                                    ? 'selected' : '' ?>>
                                <?= e($p['nombre']) ?> — Bs.
                                <?= number_format((float)$p['precio'], 2) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="cantidad">Cantidad *</label>
                    <input type="number" name="cantidad" id="cantidad"
                           min="1" step="1" required
                           value="<?= $detalle_editar ? (int)$detalle_editar['cantidad'] : 1 ?>">
                </div>

                <div class="form-group">
                    <label for="precio">Precio unitario</label>
                    <input type="number" id="precio" step="0.01" readonly
                           value="<?= $detalle_editar ? e($detalle_editar['precio_unitario']) : '' ?>"
                           placeholder="Se completa automáticamente">
                </div>

                <div class="total-preview full-width">
                    <span>Subtotal calculado</span>
                    <strong id="subtotalVista">Bs. 0.00</strong>
                </div>

                <div class="full-width actions">
                    <button type="submit" class="btn btn-primary">
                        <?= $detalle_editar ? '💾 Guardar cambios' : '➕ Registrar detalle' ?>
                    </button>

                    <?php if ($detalle_editar): ?>
                        <a href="detalle_pedidos.php" class="btn btn-light">Cancelar</a>
                    <?php else: ?>
                        <button type="reset" class="btn btn-light" id="limpiar">
                            Limpiar formulario
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </section>

    <section class="card-box">
        <div class="card-heading">
            <h2>📑 Detalles registrados</h2>
            <input type="search" id="buscar" class="search-box"
                   placeholder="🔎 Buscar por pedido o producto...">
        </div>

        <div class="table-wrap">
            <table id="tabla">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Pedido</th>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Precio unitario</th>
                        <th>Subtotal</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($resultado && $resultado->num_rows > 0): ?>
                    <?php while ($row = $resultado->fetch_assoc()): ?>
                        <tr>
                            <td>#<?= (int)$row['id_detalle_pedido'] ?></td>
                            <td>
                                <span class="badge">
                                    Pedido #<?= (int)$row['id_pedido'] ?>
                                </span>
                            </td>
                            <td class="product-name"><?= e($row['nombre_producto']) ?></td>
                            <td><?= number_format((int)$row['cantidad']) ?></td>
                            <td class="amount">
                                Bs. <?= number_format((float)$row['precio_unitario'], 2) ?>
                            </td>
                            <td class="amount">
                                Bs. <?= number_format((float)$row['subtotal'], 2) ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <a class="btn btn-light"
                                       href="detalle_pedidos.php?editar=<?= (int)$row['id_detalle_pedido'] ?>#formulario">
                                        ✏️ Editar
                                    </a>

                                    <form method="POST" class="formEliminar">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= e($_SESSION['csrf_detalle_pedido']) ?>">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id_detalle_pedido"
                                               value="<?= (int)$row['id_detalle_pedido'] ?>">
                                        <button type="submit" class="btn btn-danger">
                                            🗑️ Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="empty-state">
                            🍪 Todavía no hay detalles registrados. ¡Agrega el primero!
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <p id="sinResultados" class="empty-state" style="display:none;">
            No se encontraron resultados para tu búsqueda.
        </p>
    </section>
</main>
</div>

<script>
$(function () {
    $('#id_pedido, #id_producto').select2({
        width: '100%',
        allowClear: true,
        placeholder: 'Selecciona una opción'
    });

    const producto = document.getElementById('id_producto');
    const cantidad = document.getElementById('cantidad');
    const precio = document.getElementById('precio');
    const subtotalVista = document.getElementById('subtotalVista');
    const formDetalle = document.getElementById('formDetalle');

    function actualizarSubtotal() {
        const opcion = producto.options[producto.selectedIndex];
        const precioSeleccionado = opcion
            ? opcion.getAttribute('data-precio')
            : '';

        precio.value = precioSeleccionado !== null && precioSeleccionado !== ''
            ? Number(precioSeleccionado).toFixed(2)
            : '';

        const unidades = Math.max(0, parseInt(cantidad.value, 10) || 0);
        const precioUnitario = Number(precio.value) || 0;
        const subtotal = unidades * precioUnitario;

        subtotalVista.textContent = 'Bs. ' + subtotal.toLocaleString('es-BO', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    $('#id_producto').on('change', actualizarSubtotal);
    cantidad.addEventListener('input', actualizarSubtotal);

    actualizarSubtotal();

    document.querySelectorAll('.formEliminar').forEach(function (formulario) {
        formulario.addEventListener('submit', function (evento) {
            if (!confirm('¿Seguro que deseas eliminar este detalle? El total del pedido se recalculará.')) {
                evento.preventDefault();
            }
        });
    });

    const buscador = document.getElementById('buscar');
    const filas = document.querySelectorAll('#tabla tbody tr');
    const sinResultados = document.getElementById('sinResultados');

    buscador.addEventListener('input', function () {
        const filtro = this.value.trim().toLocaleLowerCase();
        let visibles = 0;

        filas.forEach(function (fila) {
            const coincide = fila.textContent.toLocaleLowerCase().includes(filtro);
            fila.style.display = coincide ? '' : 'none';

            if (coincide) {
                visibles++;
            }
        });

        sinResultados.style.display = visibles === 0 ? 'block' : 'none';
    });

    const limpiar = document.getElementById('limpiar');

    if (limpiar) {
        limpiar.addEventListener('click', function () {
            setTimeout(function () {
                $('#id_pedido, #id_producto').val(null).trigger('change');
                cantidad.value = 1;
                actualizarSubtotal();
            }, 0);
        });
    }

    formDetalle.addEventListener('submit', function (evento) {
        const idPedido = document.getElementById('id_pedido').value;
        const idProducto = producto.value;
        const unidades = Number(cantidad.value);

        if (
            !idPedido ||
            !idProducto ||
            !Number.isInteger(unidades) ||
            unidades < 1
        ) {
            evento.preventDefault();
            alert('Selecciona un pedido, un producto y una cantidad válida.');
        }
    });
});
</script>

</body>
</html>