<?php
session_start();
require_once "config/conexion.php";
if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit();
}
$rol = strtolower(trim($_SESSION["rol"] ?? ""));
$rol = str_replace(
    ["á", "é", "í", "ó", "ú"],
    ["a", "e", "i", "o", "u"],
    $rol
);
if (!in_array($rol, ["administradora", "administrador", "admin"], true)) {
    header("Location: catalogo_galletas.php");
    exit();
}
$nombre_usuario = $_SESSION["nombre_completo"] ?? $_SESSION["usuario"];
$busqueda = trim($_GET["buscar"] ?? "");
$estadoFiltro = trim($_GET["estado"] ?? "");
$sql = "SELECT
            c.id_carrito,
            c.id_usuario,
            c.fecha_creacion,
            c.estado,
            u.usuario,
            u.nombre_completo,
            COUNT(DISTINCT dc.id_detalle_carrito) AS cantidad_productos,
            COALESCE(SUM(dc.cantidad), 0) AS total_unidades
        FROM carritos c
        LEFT JOIN usuarios u ON u.id_usuario = c.id_usuario
        LEFT JOIN detalle_carrito dc ON dc.id_carrito = c.id_carrito
        WHERE 1 = 1";
$tipos = "";
$parametros = [];
if ($busqueda !== "") {
    $sql .= " AND (
        CAST(c.id_carrito AS CHAR) LIKE ?
        OR CAST(c.id_usuario AS CHAR) LIKE ?
        OR u.usuario LIKE ?
        OR u.nombre_completo LIKE ?
    )";
    $termino = "%" . $busqueda . "%";
    $tipos .= "ssss";
    $parametros[] = $termino;
    $parametros[] = $termino;
    $parametros[] = $termino;
    $parametros[] = $termino;
}
if ($estadoFiltro !== "") {
    $sql .= " AND c.estado = ?";
    $tipos .= "s";
    $parametros[] = $estadoFiltro;
}
$sql .= " GROUP BY
            c.id_carrito,
            c.id_usuario,
            c.fecha_creacion,
            c.estado,
            u.usuario,
            u.nombre_completo
          ORDER BY c.id_carrito DESC";
$stmt = $conexion->prepare($sql);
if (!$stmt) {
    die("Error en la consulta de carritos: " . htmlspecialchars($conexion->error));
}
if ($tipos !== "") {
    $stmt->bind_param($tipos, ...$parametros);
}
$stmt->execute();
$resultado = $stmt->get_result();
$carritos = [];
while ($fila = $resultado->fetch_assoc()) {
    $carritos[] = $fila;
}
$stmt->close();
function e($valor) {
    return htmlspecialchars((string)($valor ?? ""), ENT_QUOTES, "UTF-8");
}
$totalCarritos = count($carritos);
$totalActivos = 0;
$totalUnidades = 0;
foreach ($carritos as $carrito) {
    if (strtolower(trim((string)$carrito["estado"])) === "activo") {
        $totalActivos++;
    }
    $totalUnidades += (int)$carrito["total_unidades"];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Carritos | Sweet Bite</title>
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
    color: #880e4f;
}
/* ENCABEZADO */
header {
    background: #fff;
    padding: 18px 30px;
    border-bottom: 2px solid #f8bbd0;
    box-shadow: 0 4px 10px rgba(233, 30, 99, .05);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}
.logo {
    font-size: 23px;
    font-weight: bold;
    color: #880e4f;
}
.usuario {
    font-size: 13px;
    color: #ad1457;
    text-align: right;
}
/* DISTRIBUCIÓN GENERAL */
.contenedor {
    display: flex;
    align-items: stretch;
    min-height: calc(100vh - 70px);
}
/* MENÚ LATERAL ORIGINAL */
.contenedor > aside {
    width: 240px;
    min-width: 240px;
    flex-shrink: 0;
    min-height: calc(100vh - 70px);
    padding: 25px 15px;
    background: #ffffff;
    border-right: 2px solid #f8bbd0;
}
.contenedor > aside p {
    margin: 0 0 12px;
    padding-left: 10px;
    color: #f48fb1;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}
.contenedor > aside ul {
    margin: 0;
    padding: 0;
    list-style: none;
}
.contenedor > aside ul li {
    margin-bottom: 8px;
}
.contenedor > aside ul li a {
    display: block;
    padding: 12px 15px;
    color: #ad1457;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    border-radius: 12px;
    transition: background-color .2s ease;
}
.contenedor > aside ul li a:hover,
.contenedor > aside ul li a.active {
    background-color: #f8bbd0;
    color: #880e4f;
}
/* CONTENIDO */
.contenido {
    flex: 1;
    min-width: 0;
    padding: 30px;
}
.encabezado {
    margin-bottom: 24px;
}
.encabezado h1 {
    font-size: 28px;
    margin-bottom: 8px;
    color: #880e4f;
}
.encabezado p {
    color: #ad1457;
    font-size: 14px;
    line-height: 1.6;
}
/* TARJETAS DE RESUMEN */
.resumen {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.tarjeta {
    background: #fff;
    border: 2px solid #f8bbd0;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 5px 14px rgba(136, 14, 79, .05);
}
.tarjeta span {
    display: block;
    color: #ad1457;
    font-size: 13px;
    margin-bottom: 8px;
}
.tarjeta strong {
    font-size: 27px;
    color: #880e4f;
}
/* PANEL Y FILTROS */
.panel {
    background: #fff;
    padding: 22px;
    border: 2px solid #f8bbd0;
    border-radius: 18px;
    box-shadow: 0 5px 14px rgba(136, 14, 79, .05);
}
.panel h2 {
    font-size: 19px;
    color: #880e4f;
    margin-bottom: 18px;
}
.filtros {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 20px;
}
.filtros input,
.filtros select {
    min-width: 180px;
    flex: 1;
    padding: 12px;
    border: 1px solid #f3b2c8;
    border-radius: 10px;
    background: #fffafd;
    color: #880e4f;
    outline-color: #ec407a;
}
/* BOTONES */
.btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    padding: 11px 16px;
    border: none;
    border-radius: 10px;
    text-decoration: none;
    font-size: 13px;
    font-weight: bold;
    cursor: pointer;
    transition: .2s;
}
.btn-principal {
    background: #c2185b;
    color: #fff;
}
.btn-principal:hover {
    background: #ad1457;
}
.btn-secundario {
    background: #fce4ec;
    color: #880e4f;
    border: 1px solid #f3b2c8;
}
.btn-secundario:hover {
    background: #f8bbd0;
}
/* TABLA */
.tabla-contenedor {
    overflow-x: auto;
    width: 100%;
}
table {
    width: 100%;
    border-collapse: collapse;
    min-width: 760px;
}
thead {
    background: #fce4ec;
}
th,
td {
    padding: 14px 12px;
    text-align: left;
    border-bottom: 1px solid #f8d5e1;
    font-size: 13px;
}
th {
    color: #880e4f;
    white-space: nowrap;
}
td {
    color: #6d354c;
}
tbody tr:hover {
    background: #fff7fa;
}
.estado {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    background: #fce4ec;
    color: #ad1457;
    font-size: 11px;
    font-weight: bold;
}
.estado.activo {
    background: #e8f5e9;
    color: #2e7d32;
}
.estado.cerrado,
.estado.finalizado {
    background: #eeeeee;
    color: #616161;
}
.acciones {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
}
.vacio {
    text-align: center;
    padding: 35px 15px;
    color: #ad1457;
}
.vacio .emoji {
    font-size: 40px;
    margin-bottom: 10px;
}
/* ADAPTACIÓN A PANTALLAS PEQUEÑAS */
@media (max-width: 850px) {
    .contenedor {
        flex-direction: column;
    }
    .contenedor > aside {
        width: 100%;
        min-width: 0;
        min-height: auto;
        border-right: none;
        border-bottom: 2px solid #f8bbd0;
    }
    .contenedor > aside ul {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 5px;
    }
    .contenedor > aside ul li {
        margin-bottom: 0;
    }
    .contenido {
        padding: 20px;
    }
    .resumen {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 500px) {
    header {
        padding: 15px;
        align-items: flex-start;
        flex-direction: column;
    }
    .usuario {
        text-align: left;
    }
    .contenido {
        padding: 14px;
    }
    .panel {
        padding: 15px;
    }
    .filtros input,
    .filtros select,
    .filtros .btn {
        width: 100%;
        min-width: 0;
    }
}
</style>
</head>
<body>
<header>
    <div class="logo">🍪 Sweet Bite Bakery</div>
    <div class="usuario">
        Panel administrativo · Hola, <?= e($nombre_usuario) ?>
    </div>
</header>
<div class="contenedor">
    <?php include "sidebar.php"; ?>
    <main class="contenido">
        <div class="encabezado">
            <h1>🛒 Gestión de carritos</h1>
            <p>Consulta los carritos registrados y revisa los productos asociados a cada uno.</p>
        </div>
        <section class="resumen">
            <div class="tarjeta">
                <span>Carritos encontrados</span>
                <strong><?= $totalCarritos ?></strong>
            </div>
            <div class="tarjeta">
                <span>Carritos activos</span>
                <strong><?= $totalActivos ?></strong>
            </div>
            <div class="tarjeta">
                <span>Unidades en los carritos</span>
                <strong><?= $totalUnidades ?></strong>
            </div>
        </section>
        <section class="panel">
            <h2>📋 Registro de carritos</h2>
            <form method="GET" action="carritos.php" class="filtros">
                <input
                    type="text"
                    name="buscar"
                    placeholder="Buscar por carrito, usuario o nombre..."
                    value="<?= e($busqueda) ?>"
                >
                <select name="estado">
                    <option value="">Todos los estados</option>
                    <option value="activo" <?= $estadoFiltro === "activo" ? "selected" : "" ?>>Activo</option>
                    <option value="cerrado" <?= $estadoFiltro === "cerrado" ? "selected" : "" ?>>Cerrado</option>
                    <option value="finalizado" <?= $estadoFiltro === "finalizado" ? "selected" : "" ?>>Finalizado</option>
                </select>
                <button type="submit" class="btn btn-principal">🔎 Buscar</button>
                <a href="carritos.php" class="btn btn-secundario">Limpiar</a>
            </form>
            <div class="tabla-contenedor">
                <table>
                    <thead>
                        <tr>
                            <th>ID carrito</th>
                            <th>ID usuario</th>
                            <th>Usuario</th>
                            <th>Fecha de creación</th>
                            <th>Productos</th>
                            <th>Unidades</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($carritos)): ?>
                        <tr>
                            <td colspan="8" class="vacio">
                                <div class="emoji">🛒</div>
                                No se encontraron carritos registrados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($carritos as $c): ?>
                            <?php
                            $estado = strtolower(trim((string)$c["estado"]));
                            $claseEstado = preg_replace('/[^a-z0-9_-]/', '', $estado);
                            ?>
                            <tr>
                                <td>#<?= (int)$c["id_carrito"] ?></td>
                                <td><?= (int)$c["id_usuario"] ?></td>
                                <td>
                                    <?= e($c["nombre_completo"] ?: $c["usuario"] ?: "Usuario no disponible") ?>
                                </td>
                                <td><?= e($c["fecha_creacion"] ?: "No registrada") ?></td>
                                <td><?= (int)$c["cantidad_productos"] ?></td>
                                <td><?= (int)$c["total_unidades"] ?></td>
                                <td>
                                    <span class="estado <?= e($claseEstado) ?>">
                                        <?= e(ucfirst($estado)) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="acciones">
                                        <a
                                            class="btn btn-secundario"
                                            href="detalle_carrito.php?id_carrito=<?= (int)$c["id_carrito"] ?>"
                                        >Ver detalle</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>