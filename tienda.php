<?php
session_start();

if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit();
}

if (strtolower($_SESSION["rol"] ?? "") !== "cliente") {
    header("Location: index.php");
    exit();
}

$nombre = $_SESSION["nombre_completo"] ?? $_SESSION["usuario"];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sweet Bite - Tienda</title>
<style>
*{box-sizing:border-box;font-family:'Segoe UI',sans-serif}
body{margin:0;background:#fff5f8;color:#880e4f}
header{background:white;padding:18px 6%;display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap;border-bottom:2px solid #f8bbd0}
.logo{font-size:23px;font-weight:bold}
nav a{color:#ad1457;text-decoration:none;margin:0 8px;font-weight:600;font-size:14px}
.hero{text-align:center;padding:65px 20px;background:linear-gradient(135deg,#fce4ec,#f8bbd0)}
.hero h1{font-size:35px;margin:0 0 12px}
.hero p{font-size:16px;color:#ad1457}
.btn{display:inline-block;background:#ec407a;color:white;padding:13px 22px;border-radius:12px;text-decoration:none;font-weight:bold;margin-top:15px}
.section{max-width:1000px;margin:35px auto;padding:0 20px}
.section h2{text-align:center}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:20px;margin-top:25px}
.card{background:white;border:1px solid #f8bbd0;border-radius:18px;padding:25px;text-align:center}
.emoji{font-size:45px}
.card h3{margin-bottom:8px}
.card p{font-size:14px;color:#ad1457}
footer{text-align:center;padding:25px;color:#ad1457}
</style>
</head>
<body>
<header>
    <div class="logo">🍪 Sweet Bite</div>
    <nav>
        <a href="tienda.php">Inicio</a>
        <a href="catalogo_galletas.php">Catálogo</a>
        <a href="cerrar_sesion.php">Cerrar sesión</a>
    </nav>
</header>

<section class="hero">
    <h1>¡Hola, <?= htmlspecialchars($nombre) ?>! 💗</h1>
    <p>Un pequeño antojo puede alegrarte el día.</p>
    <a class="btn" href="catalogo_galletas.php">Ver nuestras galletas 🍪</a>
</section>

<section class="section">
    <h2>Bienvenida a nuestra tienda</h2>
    <div class="grid">
        <div class="card">
            <div class="emoji">🍪</div>
            <h3>Nuestras galletas</h3>
            <p>Descubre los productos disponibles.</p>
            <a class="btn" href="catalogo_galletas.php">Ver catálogo</a>
        </div>
        <div class="card">
            <div class="emoji">🛒</div>
            <h3>Mi carrito</h3>
            <p>Pronto podrás revisar tus productos seleccionados.</p>
        </div>
        <div class="card">
            <div class="emoji">📦</div>
            <h3>Mis pedidos</h3>
            <p>Consulta tus compras desde tu cuenta.</p>
        </div>
    </div>
</section>

<footer>Sweet Bite Bakery · Hecho con cariño 💗</footer>
</body>
</html>