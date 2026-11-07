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
$tipo_mensaje = "";
// Procesar la acción de agregar al carrito
if ($_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST['accion'])
    && $_POST['accion'] === 'agregar') {
    $id_producto = filter_var(
        $_POST['id_producto'] ?? null,
        FILTER_VALIDATE_INT
    );
    $cantidad = filter_var(
        $_POST['cantidad'] ?? 1,
        FILTER_VALIDATE_INT
    );
    if (!$id_producto || $id_producto <= 0 || !$cantidad || $cantidad < 1) {
        $mensaje = "Selecciona un producto y una cantidad válida.";
        $tipo_mensaje = "error";
    } else {
        try {
            $conexion->begin_transaction();
            // Verificar que el producto exista
            $stmt_producto = $conexion->prepare("
                SELECT id_producto, nombre
                FROM productos
                WHERE id_producto = ?
                LIMIT 1
            ");
            $stmt_producto->bind_param("i", $id_producto);
            $stmt_producto->execute();
            $resultado_producto = $stmt_producto->get_result();
            $producto = $resultado_producto->fetch_assoc();
            $stmt_producto->close();
            if (!$producto) {
                throw new Exception("El producto seleccionado no existe.");
            }
            // Consultar y bloquear el registro de inventario
            $stmt_stock = $conexion->prepare("
                SELECT stock_actual
                FROM inventario
                WHERE id_producto = ?
                LIMIT 1
                FOR UPDATE
            ");
            $stmt_stock->bind_param("i", $id_producto);
            $stmt_stock->execute();
            $resultado_stock = $stmt_stock->get_result();
            $inventario = $resultado_stock->fetch_assoc();
            $stmt_stock->close();
            if (!$inventario || $inventario['stock_actual'] === null) {
                throw new Exception(
                    "Este producto no tiene inventario registrado."
                );
            }
            $stock = (int)$inventario['stock_actual'];
            if ($stock <= 0) {
                throw new Exception(
                    "Esta galleta está agotada por el momento."
                );
            }
            // Buscar el carrito activo del usuario
            $stmt_carrito = $conexion->prepare("
                SELECT id_carrito
                FROM carritos
                WHERE id_usuario = ?
                  AND estado = 'activo'
                ORDER BY id_carrito DESC
                LIMIT 1
                FOR UPDATE
            ");
            $stmt_carrito->bind_param("i", $id_usuario);
            $stmt_carrito->execute();
            $resultado_carrito = $stmt_carrito->get_result();
            $carrito = $resultado_carrito->fetch_assoc();
            $stmt_carrito->close();
            // Crear un carrito si todavía no existe uno activo
            if ($carrito) {
                $id_carrito = (int)$carrito['id_carrito'];
            } else {
                $stmt_nuevo = $conexion->prepare("
                    INSERT INTO carritos (id_usuario, estado)
                    VALUES (?, 'activo')
                ");
                $stmt_nuevo->bind_param("i", $id_usuario);
                $stmt_nuevo->execute();
                $id_carrito = (int)$stmt_nuevo->insert_id;
                $stmt_nuevo->close();
            }
            // Comprobar si el producto ya está en el carrito
            $stmt_existente = $conexion->prepare("
                SELECT id_detalle_carrito, cantidad
                FROM detalle_carrito
                WHERE id_carrito = ?
                  AND id_producto = ?
                LIMIT 1
                FOR UPDATE
            ");
            $stmt_existente->bind_param(
                "ii",
                $id_carrito,
                $id_producto
            );
            $stmt_existente->execute();
            $resultado_existente = $stmt_existente->get_result();
            $detalle_existente = $resultado_existente->fetch_assoc();
            $stmt_existente->close();
            $cantidad_actual = $detalle_existente
                ? (int)$detalle_existente['cantidad']
                : 0;
            $cantidad_final = $cantidad_actual + $cantidad;
            // No permitir agregar más unidades que las disponibles
            if ($cantidad_final > $stock) {
                throw new Exception(
                    "Solo hay " . $stock .
                    " unidades disponibles. Ya tienes " .
                    $cantidad_actual .
                    " en tu carrito."
                );
            }
            if ($detalle_existente) {
                // Aumentar la cantidad del producto existente
                $id_detalle = (int)$detalle_existente['id_detalle_carrito'];
                $stmt_actualizar = $conexion->prepare("
                    UPDATE detalle_carrito
                    SET cantidad = ?
                    WHERE id_detalle_carrito = ?
                      AND id_carrito = ?
                ");
                $stmt_actualizar->bind_param(
                    "iii",
                    $cantidad_final,
                    $id_detalle,
                    $id_carrito
                );
                $stmt_actualizar->execute();
                $stmt_actualizar->close();
                $mensaje = "Agregaste más unidades de " .
                    $producto['nombre'] . " a tu carrito.";
            } else {
                // Agregar un producto nuevo al carrito
                $stmt_insertar = $conexion->prepare("
                    INSERT INTO detalle_carrito
                        (id_carrito, id_producto, cantidad)
                    VALUES (?, ?, ?)
                ");
                $stmt_insertar->bind_param(
                    "iii",
                    $id_carrito,
                    $id_producto,
                    $cantidad
                );
                $stmt_insertar->execute();
                $stmt_insertar->close();
                $mensaje = $producto['nombre'] .
                    " se agregó correctamente a tu carrito.";
            }
            $conexion->commit();
            $tipo_mensaje = "success";
        } catch (Throwable $e) {
            $conexion->rollback();
            $mensaje = $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}
// Consultar productos, categorías y stock
$sql_catalogo = "
    SELECT
        p.id_producto,
        p.nombre AS producto,
        p.descripcion,
        p.precio,
        IFNULL(c.nombre, 'Sin categoría') AS categoria,
        IFNULL(i.stock_actual, 0) AS stock
    FROM productos p
    LEFT JOIN categorias c
        ON p.id_categoria = c.id_categoria
    LEFT JOIN inventario i
        ON p.id_producto = i.id_producto
    ORDER BY p.id_producto DESC
";
$resultado_catalogo = $conexion->query($sql_catalogo);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sweet Bite 🍪 - Catálogo</title>
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
            background-color: #ffffff;
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
            background: #ffffff;
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
            margin-bottom: 8px;
        }
        aside ul li a {
            display: block;
            padding: 12px 15px;
            color: #ad1457;
            text-decoration: none;
            font-weight: 600;
            border-radius: 12px;
            transition: 0.3s;
            font-size: 14px;
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
        .titulo-contenido {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 22px;
        }
        h2 {
            color: #880e4f;
            font-size: 24px;
        }
        .botones-superiores {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .btn {
            display: inline-block;
            border: none;
            border-radius: 10px;
            padding: 10px 15px;
            text-decoration: none;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
            text-align: center;
        }
        .btn-carrito {
            background-color: #ad1457;
            color: white;
        }
        .btn-carrito:hover {
            background-color: #880e4f;
        }
        .btn-detalle {
            background-color: #f8bbd0;
            color: #880e4f;
        }
        .btn-detalle:hover {
            background-color: #f48fb1;
        }
        .alert {
            padding: 13px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
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
        .grid-catalogo {
            display: grid;
            grid-template-columns: repeat(
                auto-fill,
                minmax(260px, 1fr)
            );
            gap: 20px;
        }
        .card-galleta {
            background: white;
            border-radius: 20px;
            border: 2px solid #f8bbd0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.03);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .card-galleta:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 16px rgba(233, 30, 99, 0.10);
        }
        .card-badge {
            display: inline-block;
            background-color: #f8bbd0;
            color: #880e4f;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .card-title {
            font-size: 18px;
            color: #880e4f;
            margin-bottom: 8px;
        }
        .card-desc {
            font-size: 13px;
            color: #ad1457;
            margin-bottom: 15px;
            line-height: 1.5;
            flex-grow: 1;
            overflow-wrap: anywhere;
        }
        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            border-top: 1px solid #f8bbd0;
            padding-top: 12px;
            margin-top: 12px;
        }
        .card-precio {
            font-size: 18px;
            font-weight: bold;
            color: #ec407a;
        }
        .card-stock {
            font-size: 12px;
            color: #2e7d32;
            font-weight: 600;
            background: #e8f5e9;
            padding: 5px 8px;
            border-radius: 6px;
        }
        .card-stock.sin-stock {
            color: #c62828;
            background: #ffebee;
        }
        .form-agregar {
            margin-top: 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .cantidad-label {
            color: #880e4f;
            font-size: 13px;
            font-weight: 600;
        }
        .cantidad-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #f8bbd0;
            border-radius: 9px;
            color: #880e4f;
            background-color: #fffafd;
            font-size: 14px;
            outline: none;
        }
        .cantidad-input:focus {
            border-color: #ec407a;
        }
        .btn-agregar {
            width: 100%;
            background-color: #ec407a;
            color: white;
            padding: 12px;
            font-size: 14px;
        }
        .btn-agregar:hover {
            background-color: #d81b60;
        }
        .btn-agregar:disabled {
            background-color: #e0a8bc;
            cursor: not-allowed;
        }
        .catalogo-vacio {
            background: white;
            padding: 30px;
            border: 2px solid #f8bbd0;
            border-radius: 18px;
            color: #ad1457;
            text-align: center;
            line-height: 1.7;
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
            .titulo-contenido {
                align-items: flex-start;
            }
            .grid-catalogo {
                grid-template-columns: repeat(
                    auto-fill,
                    minmax(220px, 1fr)
                );
            }
        }
        @media (max-width: 480px) {
            .grid-catalogo {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<header>
    <div class="logo">🍪 Sweet Bite Bakery</div>
    <div class="user-info">
        ¡Hola, <?= htmlspecialchars(
            $nombre_usuario,
            ENT_QUOTES,
            'UTF-8'
        ) ?>! 🌸
    </div>
</header>
<div class="main-container">
    <?php include('sidebar.php'); ?>
    <main class="content">
        <div class="titulo-contenido">
            <h2>Catálogo de Galletas 📜🍪</h2>
            <div class="botones-superiores">
                <a href="carritos.php" class="btn btn-carrito">
                    🛒 Ver carrito
                </a>
                <a href="detalle_carrito.php" class="btn btn-detalle">
                    📋 Ver detalle
                </a>
            </div>
        </div>
        <?php if ($mensaje !== ''): ?>
            <div class="alert <?= $tipo_mensaje === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars(
                    $mensaje,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
                <?php if ($tipo_mensaje === 'success'): ?>
                    <div style="margin-top:10px;">
                        <a href="carritos.php"
                           style="color:#2e7d32;font-weight:bold;">
                            Ir al carrito →
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="grid-catalogo">
            <?php if ($resultado_catalogo && $resultado_catalogo->num_rows > 0): ?>
                <?php while ($galleta = $resultado_catalogo->fetch_assoc()): ?>
                    <?php
                    $id_galleta = (int)$galleta['id_producto'];
                    $stock_num = (int)$galleta['stock'];
                    $clase_stock = $stock_num > 0
                        ? 'card-stock'
                        : 'card-stock sin-stock';
                    $texto_stock = $stock_num > 0
                        ? "Stock: {$stock_num} unid."
                        : "Agotado (0)";
                    ?>
                    <div class="card-galleta">
                        <div>
                            <span class="card-badge">
                                <?= htmlspecialchars(
                                    $galleta['categoria'] ?? 'Sin categoría',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </span>
                            <h3 class="card-title">
                                <?= htmlspecialchars(
                                    $galleta['producto'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </h3>
                            <p class="card-desc">
                                <?= htmlspecialchars(
                                    $galleta['descripcion'] ?? 'Deliciosas galletas Sweet Bite.',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </p>
                        </div>
                        <div class="card-footer">
                            <span class="card-precio">
                                Bs <?= number_format(
                                    (float)$galleta['precio'],
                                    2
                                ) ?>
                            </span>
                            <span class="<?= $clase_stock ?>">
                                <?= htmlspecialchars(
                                    $texto_stock,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </span>
                        </div>
                        <form action="catalogo_galletas.php"
                              method="POST"
                              class="form-agregar">
                            <input type="hidden"
                                   name="accion"
                                   value="agregar">
                            <input type="hidden"
                                   name="id_producto"
                                   value="<?= $id_galleta ?>">
                            <label class="cantidad-label"
                                   for="cantidad_<?= $id_galleta ?>">
                                ¿Cuántas unidades deseas?
                            </label>
                            <input
                                class="cantidad-input"
                                type="number"
                                id="cantidad_<?= $id_galleta ?>"
                                name="cantidad"
                                min="1"
                                max="<?= max(1, $stock_num) ?>"
                                value="1"
                                required
                                <?= $stock_num <= 0 ? 'disabled' : '' ?>
                            >
                            <button
                                type="submit"
                                class="btn btn-agregar"
                                <?= $stock_num <= 0 ? 'disabled' : '' ?>
                            >
                                <?= $stock_num > 0
                                    ? '🛒 Agregar al carrito'
                                    : 'Producto agotado' ?>
                            </button>
                        </form>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="catalogo-vacio">
                    <h3>🍪 Todavía no hay galletas registradas</h3>
                    <p>
                        Cuando registres productos en la sección Productos,
                        aparecerán aquí para que puedas agregarlos al carrito.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>