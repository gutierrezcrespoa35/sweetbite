<?php
session_start();
require_once "config/conexion.php";

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

$pedidos = [];
$error = "";

if ($id_usuario <= 0) {
    $error = "No se pudo identificar tu usuario. Vuelve a iniciar sesión.";
} else {
    $sql = "SELECT id_pedido, fecha_pedido, estado, total, modalidad_entrega
            FROM pedidos
            WHERE id_usuario = ?
            ORDER BY fecha_pedido DESC";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();

    $resultado = $stmt->get_result();

    while ($fila = $resultado->fetch_assoc()) {
        $pedidos[] = $fila;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Mis pedidos | Sweet Bite</title>

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: "Segoe UI", Arial, sans-serif;
    background: #fff0f5;
    color: #702342;
}

/* ENCABEZADO */

header {
    background: white;
    padding: 22px 6%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    border-bottom: 2px solid #f8bbd0;
}

.logo {
    font-size: 28px;
    font-weight: bold;
    color: #c2185b;
    letter-spacing: 1px;
}

.bienvenida {
    color: #8e4562;
    font-size: 14px;
}

.salir {
    display: inline-block;
    margin-left: 10px;
    padding: 8px 13px;
    border-radius: 20px;
    background: #fce4ec;
    color: #880e4f;
    text-decoration: none;
    font-weight: 600;
}

.salir:hover {
    background: #f8bbd0;
}

/* MENÚ DE TARJETAS */

.panel-nav {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 12px;
    padding: 20px 5%;
    background: #fce4ec;
    border-bottom: 1px solid #f8bbd0;
}

.nav-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 90px;
    padding: 13px 8px;
    text-align: center;
    text-decoration: none;
    color: #880e4f;
    background: white;
    border: 1px solid #f3b2cb;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    transition: 0.2s;
}

.nav-card span {
    font-size: 25px;
}

.nav-card:hover,
.nav-card.active {
    background: #fce4ec;
    border-color: #c2185b;
    color: #ad1457;
    transform: translateY(-2px);
}

/* CONTENIDO */

.contenedor {
    width: 90%;
    max-width: 1150px;
    margin: 40px auto;
}

.presentacion {
    text-align: center;
    margin-bottom: 35px;
}

.presentacion h1 {
    color: #ad1457;
    font-size: 34px;
    margin-bottom: 12px;
}

.presentacion p {
    color: #91536b;
    font-size: 16px;
    line-height: 1.7;
}

/* TABLA DE PEDIDOS */

.tabla-contenedor {
    background: white;
    border: 1px solid #f2bfd1;
    border-radius: 18px;
    overflow-x: auto;
    box-shadow: 0 7px 20px rgba(136, 14, 79, 0.08);
}

table {
    width: 100%;
    min-width: 650px;
    border-collapse: collapse;
}

thead {
    background: #fce4ec;
}

th {
    color: #880e4f;
    padding: 16px 14px;
    text-align: left;
    font-size: 14px;
}

td {
    padding: 16px 14px;
    border-bottom: 1px solid #f8e2eb;
    color: #702342;
    font-size: 14px;
}

tbody tr:hover {
    background: #fff8fb;
}

tbody tr:last-child td {
    border-bottom: none;
}

.estado {
    display: inline-block;
    padding: 7px 12px;
    border-radius: 20px;
    background: #fce4ec;
    color: #ad1457;
    font-weight: 700;
    font-size: 12px;
}

.total {
    color: #c2185b;
    font-weight: bold;
    white-space: nowrap;
}

/* MENSAJES */

.mensaje {
    padding: 20px;
    margin-bottom: 25px;
    border-radius: 12px;
    text-align: center;
    line-height: 1.7;
}

.mensaje.error {
    background: #ffebee;
    color: #b71c1c;
    border: 1px solid #ef9a9a;
}

.vacio {
    background: white;
    border: 1px solid #f2bfd1;
    border-radius: 20px;
    padding: 40px 20px;
    text-align: center;
    box-shadow: 0 7px 20px rgba(136, 14, 79, 0.08);
}

.vacio .emoji {
    font-size: 48px;
    margin-bottom: 15px;
}

.vacio h2 {
    color: #ad1457;
    margin-bottom: 12px;
}

.vacio p {
    color: #805568;
    line-height: 1.7;
}

.boton {
    display: inline-block;
    margin-top: 20px;
    padding: 13px 22px;
    border-radius: 12px;
    background: #c2185b;
    color: white;
    text-decoration: none;
    font-weight: bold;
    transition: 0.2s;
}

.boton:hover {
    background: #880e4f;
}

/* PIE DE PÁGINA */

footer {
    margin-top: 50px;
    padding: 23px;
    text-align: center;
    background: white;
    border-top: 1px solid #f8bbd0;
    color: #91536b;
    font-size: 13px;
}

/* CELULARES */

@media (max-width: 650px) {
    header {
        justify-content: center;
        text-align: center;
    }

    .bienvenida {
        width: 100%;
    }

    .panel-nav {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        padding: 15px 4%;
    }

    .contenedor {
        width: 92%;
        margin: 28px auto;
    }

    .presentacion h1 {
        font-size: 28px;
    }
}
</style>
</head>

<body>

<header>
    <div class="logo">🍪 Sweet Bite</div>

    <div class="bienvenida">
        ¡Hola, <?= htmlspecialchars($nombre_usuario, ENT_QUOTES, "UTF-8") ?>!
        <a href="cerrar_sesion.php" class="salir">Cerrar sesión</a>
    </div>
</header>

<nav class="panel-nav">
    <a href="tienda.php" class="nav-card">
        <span>🏠</span>
        Inicio
    </a>

    <a href="catalogo_galletas.php" class="nav-card">
        <span>🍪</span>
        Catálogo
    </a>

    <a href="carritos.php" class="nav-card">
        <span>🛒</span>
        Mi carrito
    </a>

    <a href="mis_pedidos.php" class="nav-card active">
        <span>📦</span>
        Mis pedidos
    </a>

    <a href="pagos.php" class="nav-card">
        <span>💳</span>
        Pagos
    </a>

    <a href="pedidos_especiales.php" class="nav-card">
        <span>🎂</span>
        Pedidos especiales
    </a>
</nav>

<main class="contenedor">

    <section class="presentacion">
        <h1>📦 Mis pedidos</h1>
        <p>
            Aquí puedes consultar tus compras, sus estados
            y la modalidad de entrega. 💗
        </p>
    </section>

    <?php if ($error !== ""): ?>

        <div class="mensaje error">
            <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
        </div>

    <?php elseif (count($pedidos) > 0): ?>

        <div class="tabla-contenedor">
            <table>
                <thead>
                    <tr>
                        <th>N.º pedido</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Total</th>
                        <th>Entrega</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($pedidos as $pedido): ?>
                    <tr>
                        <td>
                            #<?= (int)$pedido["id_pedido"] ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $pedido["fecha_pedido"] ?? "",
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </td>

                        <td>
                            <span class="estado">
                                <?= htmlspecialchars(
                                    ucfirst((string)$pedido["estado"]),
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </span>
                        </td>

                        <td class="total">
                            Bs <?= number_format(
                                (float)$pedido["total"],
                                2
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $pedido["modalidad_entrega"] ?? "",
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>

        <section class="vacio">
            <div class="emoji">🛍️</div>
            <h2>Aún no tienes pedidos</h2>
            <p>
                Cuando realices una compra, tus pedidos aparecerán aquí.
            </p>

            <a href="catalogo_galletas.php" class="boton">
                🍪 Explorar galletas
            </a>
        </section>

    <?php endif; ?>

</main>

<footer>
    © <?= date("Y") ?> Sweet Bite · Hecho con amor. 💗
</footer>

</body>
</html>