<?php
session_start();
require_once "config/conexion.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit();
}

$rol = strtolower(trim($_SESSION["rol"] ?? ""));
if ($rol !== "cliente") {
    header("Location: index.php");
    exit();
}

$id_usuario = (int)($_SESSION["id_usuario"] ?? 0);
$nombre_usuario = $_SESSION["nombre_completo"] ?? $_SESSION["usuario"];

if ($id_usuario <= 0) {
    die("No se encontró el identificador del usuario. Cierra sesión e inicia nuevamente.");
}

$error = "";
$exito = "";

// Compatibilidad con el flujo anterior de selección de productos.
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["accion"] ?? "") === "continuar"
) {
    $seleccionados = $_POST["productos_seleccionados"] ?? [];
    $cantidades = $_POST["cantidad"] ?? [];

    if (!is_array($seleccionados) || empty($seleccionados)) {
        header("Location: detalle_carrito.php");
        exit();
    }

    $seleccion = [];

    foreach ($seleccionados as $id_detalle) {
        $id_detalle = filter_var($id_detalle, FILTER_VALIDATE_INT);

        if (!$id_detalle || $id_detalle <= 0) {
            continue;
        }

        $cantidad = filter_var(
            $cantidades[$id_detalle] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($cantidad === false || $cantidad < 1 || $cantidad > 99) {
            $error = "Revisa las cantidades de los productos seleccionados.";
            break;
        }

        $seleccion[$id_detalle] = $cantidad;
    }

    if (empty($seleccion) && $error === "") {
        $error = "Selecciona al menos una galleta.";
    }

    if ($error === "") {
        $_SESSION["compra_seleccionada"] = $seleccion;
    }
}

// Al venir desde el detalle del carrito, preparar el pedido con todos
// los productos y cantidades que ya tiene el cliente.
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $seleccion_carrito = [];
    $stmt_carrito = $conexion->prepare(
        "SELECT dc.id_detalle_carrito, dc.cantidad
         FROM carritos c
         INNER JOIN detalle_carrito dc ON dc.id_carrito = c.id_carrito
         WHERE c.id_usuario = ? AND c.estado = 'activo'
         ORDER BY dc.id_detalle_carrito ASC"
    );
    $stmt_carrito->bind_param("i", $id_usuario);
    $stmt_carrito->execute();
    $resultado_carrito = $stmt_carrito->get_result();

    while ($fila = $resultado_carrito->fetch_assoc()) {
        $seleccion_carrito[(int)$fila["id_detalle_carrito"]] =
            (int)$fila["cantidad"];
    }

    $stmt_carrito->close();
    $_SESSION["compra_seleccionada"] = $seleccion_carrito;
}

// Obtener selección guardada.
$seleccion = $_SESSION["compra_seleccionada"] ?? [];

if (!is_array($seleccion) || empty($seleccion)) {
    if ($error === "") {
        $error = "Tu carrito está vacío. Agrega algunas galletas antes de hacer el pedido.";
    }
}

function e($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

function obtenerProductos($conexion, $id_usuario, $seleccion) {
    if (empty($seleccion)) {
        return [];
    }

    $stmt = $conexion->prepare(
        "SELECT
            dc.id_detalle_carrito,
            dc.id_producto,
            dc.cantidad,
            p.nombre,
            p.descripcion,
            p.precio,
            i.stock_actual
         FROM carritos c
         INNER JOIN detalle_carrito dc
            ON dc.id_carrito = c.id_carrito
         INNER JOIN productos p
            ON p.id_producto = dc.id_producto
         LEFT JOIN inventario i
            ON i.id_producto = p.id_producto
         WHERE c.id_usuario = ?
           AND c.estado = 'activo'
           AND dc.id_detalle_carrito = ?
         LIMIT 1"
    );

    $productos = [];

    foreach ($seleccion as $id_detalle => $cantidad) {
        $id_detalle = (int)$id_detalle;
        $stmt->bind_param("ii", $id_usuario, $id_detalle);
        $stmt->execute();

        $fila = $stmt->get_result()->fetch_assoc();

        if (!$fila) {
            continue;
        }

        $fila["cantidad_compra"] = (int)$cantidad;
        $fila["precio"] = (float)$fila["precio"];
        $fila["subtotal"] = $fila["cantidad_compra"] * $fila["precio"];

        $productos[] = $fila;
    }

    $stmt->close();

    return $productos;
}

// Confirmar pedido.
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["confirmar_pedido"])
) {
    $modalidad = trim($_POST["modalidad_entrega"] ?? "");

    if (!in_array($modalidad, ["Domicilio", "Recojo"], true)) {
        $error = "Selecciona una modalidad de entrega válida.";
    } elseif (empty($seleccion)) {
        $error = "No hay productos seleccionados. Regresa al carrito.";
    } else {
        try {
            $conexion->begin_transaction();

            // Buscar el carrito activo del usuario.
            $stmt = $conexion->prepare(
                "SELECT id_carrito
                 FROM carritos
                 WHERE id_usuario = ? AND estado = 'activo'
                 ORDER BY id_carrito DESC
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->bind_param("i", $id_usuario);
            $stmt->execute();
            $carrito = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$carrito) {
                throw new Exception("No se encontró un carrito activo.");
            }

            $id_carrito = (int)$carrito["id_carrito"];
            $detalles = [];
            $total = 0;

            // Volver a consultar y validar cada producto.
            foreach ($seleccion as $id_detalle => $cantidad) {
                $id_detalle = (int)$id_detalle;
                $cantidad = (int)$cantidad;

                $stmt = $conexion->prepare(
                    "SELECT
                        dc.id_detalle_carrito,
                        dc.id_producto,
                        p.nombre,
                        p.precio,
                        i.stock_actual
                     FROM detalle_carrito dc
                     INNER JOIN productos p
                        ON p.id_producto = dc.id_producto
                     LEFT JOIN inventario i
                        ON i.id_producto = p.id_producto
                     WHERE dc.id_detalle_carrito = ?
                       AND dc.id_carrito = ?
                     LIMIT 1
                     FOR UPDATE"
                );

                $stmt->bind_param("ii", $id_detalle, $id_carrito);
                $stmt->execute();
                $producto = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$producto) {
                    throw new Exception(
                        "Uno de los productos seleccionados ya no está en tu carrito. Regresa e inténtalo nuevamente."
                    );
                }

                if ($cantidad < 1 || $cantidad > 99) {
                    throw new Exception("Una cantidad no es válida.");
                }

                if ($producto["stock_actual"] === null) {
                    throw new Exception(
                        "No se encontró inventario para: " . $producto["nombre"]
                    );
                }

                $stock = (int)$producto["stock_actual"];

                if ($stock < $cantidad) {
                    throw new Exception(
                        "No hay stock suficiente para " . $producto["nombre"] .
                        ". Stock disponible: " . $stock . "."
                    );
                }

                $precio = (float)$producto["precio"];
                $subtotal = $precio * $cantidad;
                $total += $subtotal;

                $detalles[] = [
                    "id_detalle_carrito" => $id_detalle,
                    "id_producto" => (int)$producto["id_producto"],
                    "cantidad" => $cantidad,
                    "precio_unitario" => $precio,
                    "subtotal" => $subtotal
                ];
            }

            if (empty($detalles)) {
                throw new Exception("No se encontraron productos válidos para la compra.");
            }

            // Registrar el pedido con la fecha explícita.
            $estado = "Pendiente";

            $stmt = $conexion->prepare(
                "INSERT INTO pedidos
                    (id_usuario, fecha_pedido, estado, total, modalidad_entrega)
                 VALUES (?, NOW(), ?, ?, ?)"
            );
            $stmt->bind_param(
                "isds",
                $id_usuario,
                $estado,
                $total,
                $modalidad
            );
            $stmt->execute();

            $id_pedido = (int)$conexion->insert_id;
            $stmt->close();

            if ($id_pedido <= 0) {
                throw new Exception("No se pudo obtener el número del pedido.");
            }

            // Registrar el detalle de cada producto.
            $stmtDetalle = $conexion->prepare(
                "INSERT INTO detalle_pedido
                    (id_pedido, id_producto, cantidad, precio_unitario, subtotal)
                 VALUES (?, ?, ?, ?, ?)"
            );

            $stmtStock = $conexion->prepare(
                "UPDATE inventario
                 SET stock_actual = stock_actual - ?
                 WHERE id_producto = ? AND stock_actual >= ?"
            );

            $stmtEliminar = $conexion->prepare(
                "DELETE FROM detalle_carrito
                 WHERE id_detalle_carrito = ? AND id_carrito = ?"
            );

            foreach ($detalles as $detalle) {
                $id_producto = $detalle["id_producto"];
                $cantidad = $detalle["cantidad"];
                $precio = $detalle["precio_unitario"];
                $subtotal = $detalle["subtotal"];

                $stmtDetalle->bind_param(
                    "iiidd",
                    $id_pedido,
                    $id_producto,
                    $cantidad,
                    $precio,
                    $subtotal
                );
                $stmtDetalle->execute();

                // Descontar stock solamente si sigue siendo suficiente.
                $stmtStock->bind_param(
                    "iii",
                    $cantidad,
                    $id_producto,
                    $cantidad
                );
                $stmtStock->execute();

                if ($stmtStock->affected_rows !== 1) {
                    throw new Exception(
                        "No se pudo actualizar el inventario del producto ID " .
                        $id_producto . "."
                    );
                }

                // Retirar del carrito solo lo que se compró.
                $id_detalle = $detalle["id_detalle_carrito"];

                $stmtEliminar->bind_param(
                    "ii",
                    $id_detalle,
                    $id_carrito
                );
                $stmtEliminar->execute();

                if ($stmtEliminar->affected_rows !== 1) {
                    throw new Exception(
                        "No se pudo retirar un producto del carrito."
                    );
                }
            }

            $stmtDetalle->close();
            $stmtStock->close();
            $stmtEliminar->close();

            $conexion->commit();

            unset($_SESSION["compra_seleccionada"]);

            header(
                "Location: pagos.php?pedido=" .
                $id_pedido . "&confirmado=1"
            );
            exit();

        } catch (Throwable $e) {
            try {
                $conexion->rollback();
            } catch (Throwable $ignorado) {
                // La transacción pudo no llegar a iniciarse.
            }

            // Mensaje temporal de diagnóstico para corregir el error real.
            $error = "No se pudo registrar el pedido: " . $e->getMessage();
        }
    }
}

try {
    $productos = obtenerProductos($conexion, $id_usuario, $seleccion);
} catch (Throwable $e) {
    $productos = [];
    $error = "No se pudieron cargar los productos: " . $e->getMessage();
}

$total = 0;
foreach ($productos as $producto) {
    $total += $producto["subtotal"];
}

if (empty($productos) && $error === "") {
    $error = "No se encontraron los productos seleccionados. Regresa a tu carrito e inténtalo nuevamente.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Finalizar compra | Sweet Bite</title>

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: "Segoe UI", Arial, sans-serif;
}

body {
    background: #fff0f5;
    color: #702342;
}

header {
    background: #fff;
    padding: 18px 5%;
    border-bottom: 1px solid #f8bbd0;
    box-shadow: 0 4px 12px rgba(136,14,79,.05);
}

.logo {
    color: #c2185b;
    font-size: 27px;
    font-weight: 800;
}

.subtitulo {
    margin-top: 4px;
    color: #e91e63;
    font-size: 13px;
}

nav {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 12px;
    margin-top: 20px;
    padding: 15px;
    background: #fce4ec;
    border: 1px solid #f8bbd0;
    border-radius: 16px;
}

nav a {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 75px;
    padding: 12px 8px;
    color: #880e4f;
    background: #fff;
    border: 1px solid #f3b2cb;
    border-radius: 12px;
    text-align: center;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

nav a span {
    font-size: 23px;
}

nav a:hover,
nav a.activo {
    background: #fce4ec;
    border-color: #c2185b;
}

.contenedor {
    width: 90%;
    max-width: 1000px;
    margin: 32px auto;
}

h1 {
    margin-bottom: 8px;
    color: #880e4f;
    font-size: clamp(25px, 4vw, 32px);
}

.intro {
    margin-bottom: 23px;
    color: #956477;
    font-size: 14px;
    line-height: 1.7;
}

.panel {
    margin-bottom: 22px;
    padding: 23px;
    background: #fff;
    border: 1px solid #f2bfd1;
    border-radius: 18px;
    box-shadow: 0 7px 20px rgba(136,14,79,.08);
}

h2 {
    margin-bottom: 17px;
    color: #880e4f;
    font-size: 19px;
}

.producto {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    padding: 14px 0;
    border-bottom: 1px solid #fce4ec;
}

.producto:last-child {
    border-bottom: none;
}

.producto p {
    margin-top: 5px;
    color: #956477;
    font-size: 12px;
    line-height: 1.6;
}

.precio {
    color: #c2185b;
    font-weight: 700;
    white-space: nowrap;
}

.total {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    margin-top: 15px;
    padding-top: 18px;
    border-top: 2px dashed #f3b2c8;
    font-weight: 800;
}

.total strong {
    color: #c2185b;
    font-size: 23px;
}

label {
    display: block;
    margin: 12px 0 7px;
    font-size: 13px;
    font-weight: 700;
}

select {
    width: 100%;
    padding: 12px;
    color: #702342;
    background: #fffafd;
    border: 1px solid #f3b2c8;
    border-radius: 10px;
    font-size: 14px;
}

.nota {
    margin-top: 16px;
    padding: 13px;
    color: #956477;
    background: #fff0f5;
    border-radius: 10px;
    font-size: 12px;
    line-height: 1.7;
}

.error {
    margin-bottom: 20px;
    padding: 14px;
    color: #9b1c43;
    background: #fff0f0;
    border: 1px solid #efb5c3;
    border-radius: 10px;
    font-size: 13px;
    line-height: 1.7;
    overflow-wrap: anywhere;
}

.acciones {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    padding: 13px 17px;
    border: none;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    text-align: center;
    text-decoration: none;
    cursor: pointer;
}

.btn-principal {
    color: #fff;
    background: #c2185b;
}

.btn-principal:hover {
    background: #ad1457;
}

.btn-secundario {
    color: #880e4f;
    background: #fce4ec;
    border: 1px solid #f3b2c8;
}

footer {
    margin-top: 35px;
    padding: 25px;
    color: #ad1457;
    background: #fff;
    border-top: 1px solid #f8bbd0;
    text-align: center;
    font-size: 13px;
}

@media (max-width: 600px) {
    nav {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .contenedor {
        width: 92%;
    }

    .panel {
        padding: 17px;
    }

    .acciones {
        flex-direction: column;
    }

    .acciones .btn {
        width: 100%;
    }
}
</style>
</head>

<body>
<header>
    <div class="logo">🍪 Sweet Bite</div>
    <div class="subtitulo">Galletitas hechas con amor ♡</div>

    <nav>
        <a href="tienda.php"><span>🏠</span>Inicio</a>
        <a href="catalogo_galletas.php"><span>🍪</span>Catálogo</a>
        <a href="detalle_carrito.php"><span>🛒</span>Mi carrito</a>
        <a href="mis_pedidos.php" class="activo"><span>📦</span>Mis pedidos</a>
        <a href="pagos.php"><span>💳</span>Pagos</a>
        <a href="pedidos_especiales.php"><span>🎂</span>Pedidos especiales</a>
    </nav>
</header>

<main class="contenedor">
    <h1>Finalizar compra 🛍️</h1>
    <p class="intro">
        Hola, <?= e($nombre_usuario) ?>. Revisa tu pedido y elige cómo deseas recibirlo.
    </p>

    <?php if ($error !== ""): ?>
        <div class="error">
            <strong>No se pudo completar la operación.</strong><br>
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($productos)): ?>
        <section class="panel">
            <h2>🍪 Resumen de tu pedido</h2>

            <?php foreach ($productos as $producto): ?>
                <div class="producto">
                    <div>
                        <strong><?= e($producto["nombre"]) ?></strong>
                        <p>
                            Cantidad: <?= (int)$producto["cantidad_compra"] ?>
                            <?php if ($producto["stock_actual"] !== null): ?>
                                · Stock disponible: <?= (int)$producto["stock_actual"] ?>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="precio">
                        Bs <?= number_format($producto["subtotal"], 2, ",", ".") ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="total">
                <span>Total del pedido</span>
                <strong>Bs <?= number_format($total, 2, ",", ".") ?></strong>
            </div>
        </section>

        <section class="panel">
            <h2>🚚 Modalidad de entrega</h2>

            <form method="POST" action="finalizar_compra.php">
                <label for="modalidad_entrega">¿Cómo deseas recibir tus galletas?</label>

                <select id="modalidad_entrega" name="modalidad_entrega" required>
                    <option value="">Selecciona una opción</option>
                    <option value="Domicilio">Entrega a domicilio</option>
                    <option value="Recojo">Recojo en tienda</option>
                </select>

                <div class="nota">
                    💗 El pedido se registrará con estado pendiente.
                    El pago se realizará después; esta página no cobra en línea.
                </div>

                <div class="acciones" style="margin-top:20px;">
                    <button type="submit" name="confirmar_pedido" value="1" class="btn btn-principal">
                        Confirmar pedido ♡
                    </button>

                    <a href="detalle_carrito.php" class="btn btn-secundario">
                        ← Volver al carrito
                    </a>
                </div>
            </form>
        </section>
    <?php else: ?>
        <section class="panel">
            <p>No hay productos para mostrar. Regresa al carrito y selecciona las galletas que deseas comprar.</p>
            <div class="acciones" style="margin-top:20px;">
                <a href="detalle_carrito.php" class="btn btn-principal">Volver a mi carrito</a>
            </div>
        </section>
    <?php endif; ?>
</main>

<footer>♡ Sweet Bite — Galletas que hacen la vida más dulce ♡</footer>
</body>
</html>
