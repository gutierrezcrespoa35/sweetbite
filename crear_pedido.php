<?php
session_start();
require_once __DIR__ . "/config/conexion.php";

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
if ($id_usuario <= 0 || $_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: detalle_carrito.php");
    exit();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conexion->begin_transaction();

    $stmt_carrito = $conexion->prepare(
        "SELECT id_carrito
         FROM carritos
         WHERE id_usuario = ? AND estado = 'activo'
         ORDER BY id_carrito DESC
         LIMIT 1
         FOR UPDATE"
    );
    $stmt_carrito->bind_param("i", $id_usuario);
    $stmt_carrito->execute();
    $carrito = $stmt_carrito->get_result()->fetch_assoc();
    $stmt_carrito->close();

    if (!$carrito) {
        throw new Exception("No se encontró un carrito activo.");
    }

    $id_carrito = (int)$carrito["id_carrito"];
    $stmt_resumen = $conexion->prepare(
        "SELECT COUNT(*) AS productos,
                COALESCE(SUM(dc.cantidad * p.precio), 0) AS total
         FROM detalle_carrito dc
         INNER JOIN productos p ON p.id_producto = dc.id_producto
         WHERE dc.id_carrito = ?"
    );
    $stmt_resumen->bind_param("i", $id_carrito);
    $stmt_resumen->execute();
    $resumen = $stmt_resumen->get_result()->fetch_assoc();
    $stmt_resumen->close();

    if ((int)$resumen["productos"] < 1) {
        throw new Exception("Tu carrito está vacío.");
    }

    $estado = "Pendiente";
    $modalidad = "Pendiente";
    $total = (float)$resumen["total"];
    $stmt_pedido = $conexion->prepare(
        "INSERT INTO pedidos
            (id_usuario, fecha_pedido, estado, total, modalidad_entrega)
         VALUES (?, NOW(), ?, ?, ?)"
    );
    $stmt_pedido->bind_param("isds", $id_usuario, $estado, $total, $modalidad);
    $stmt_pedido->execute();
    $id_pedido = (int)$conexion->insert_id;
    $stmt_pedido->close();

    $stmt_detalle = $conexion->prepare(
        "INSERT INTO detalle_pedido
            (id_pedido, id_producto, cantidad, precio_unitario, subtotal)
         SELECT ?, dc.id_producto, dc.cantidad, p.precio,
                dc.cantidad * p.precio
         FROM detalle_carrito dc
         INNER JOIN productos p ON p.id_producto = dc.id_producto
         WHERE dc.id_carrito = ?"
    );
    $stmt_detalle->bind_param("ii", $id_pedido, $id_carrito);
    $stmt_detalle->execute();

    if ($stmt_detalle->affected_rows < 1) {
        throw new Exception("No se pudieron guardar los productos del pedido.");
    }
    $stmt_detalle->close();

    $stmt_vaciar = $conexion->prepare(
        "DELETE FROM detalle_carrito WHERE id_carrito = ?"
    );
    $stmt_vaciar->bind_param("i", $id_carrito);
    $stmt_vaciar->execute();
    $stmt_vaciar->close();

    $conexion->commit();
    $_SESSION["mensaje_carrito"] =
        "Pedido #" . $id_pedido .
        " creado y enviado al administrador para su gestión.";
    $_SESSION["tipo_mensaje_carrito"] = "success";
} catch (Throwable $e) {
    try {
        $conexion->rollback();
    } catch (Throwable $ignorado) {
        // La transacción pudo no llegar a iniciarse.
    }

    $_SESSION["mensaje_carrito"] = "No se pudo crear el pedido: " . $e->getMessage();
    $_SESSION["tipo_mensaje_carrito"] = "error";
}

header("Location: detalle_carrito.php");
exit();
