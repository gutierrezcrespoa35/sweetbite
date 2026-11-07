<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$rol = strtolower(trim($_SESSION['rol'] ?? ''));

$esAdmin = in_array($rol, [
    'administradora',
    'administrador',
    'admin'
], true);

?>

<aside>
    <p>MENÚ SWEET BITE</p>

    <ul>
        <li><a href="index.php">🏠 Inicio</a></li>

        <?php if ($esAdmin): ?>

            <li><a href="roles.php">🔑 Roles</a></li>
            <li><a href="categorias.php">🏷️ Categorías</a></li>
            <li><a href="productos.php">🍪 Productos</a></li>
            <li><a href="catalogo_galletas.php">📜 Catálogo de galletas</a></li>
            <li><a href="inventarios.php">📦 Inventario</a></li>
            <li><a href="carritos.php">🛒 Carritos</a></li>
            <li><a href="detalle_carrito.php">🛒 Detalle del carrito</a></li>
            <li><a href="pedidos.php">📋 Pedidos</a></li>
            <li><a href="detalle_pedido.php">📋 Detalle de pedidos</a></li>
            <li><a href="pagos.php">💳 Pagos</a></li>
            <li><a href="registrar_usuario.php">👥 Usuarios</a></li>
            <li><a href="pedidos_especiales.php">🎂 Pedidos especiales</a></li>
            <li><a href="ventas_reportes.php">📊 Ventas y reportes</a></li>

        <?php else: ?>

            <li><a href="catalogo_galletas.php">🍪 Comprar galletas</a></li>
            <li><a href="carritos.php">🛒 Mi carrito</a></li>
            <li><a href="pedidos.php">📋 Mis pedidos</a></li>
            <li><a href="pagos.php">💳 Mis pagos</a></li>
            <li><a href="pedidos_especiales.php">🎂 Pedidos especiales</a></li>

        <?php endif; ?>

        <li>
            <a href="cerrar_sesion.php" style="color:#c62828;">
                🚪 Cerrar sesión
            </a>
        </li>
    </ul>
</aside>