<?php
session_start();
include('config/conexion.php');

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$nombre_usuario = $_SESSION['nombre_completo'] ?? $_SESSION['usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sweet Bite 🍪 - Panel Principal</title>

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
            border-bottom: 2px solid #f8bbd0;
            box-shadow: 0 4px 10px rgba(233, 30, 99, 0.05);
        }

        .logo {
            font-size: 22px;
            font-weight: bold;
            color: #880e4f;
            display: flex;
            align-items: center;
            gap: 8px;
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
        }

        aside {
            width: 240px;
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

        .welcome-card {
            background: white;
            padding: 25px;
            border-radius: 20px;
            border: 2px solid #f8bbd0;
            box-shadow: 0 8px 20px rgba(233, 30, 99, 0.05);
            margin-bottom: 30px;
        }

        .welcome-card h1 {
            color: #880e4f;
            font-size: 26px;
        }

        .welcome-card p {
            color: #ad1457;
            font-size: 14px;
            margin-top: 5px;
        }

        .grid-menu {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 20px;
            border: 2px solid #f8bbd0;
            box-shadow: 0 6px 15px rgba(233, 30, 99, 0.05);
            text-align: center;
            text-decoration: none;
            transition: transform 0.3s, box-shadow 0.3s;
            display: block;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(233, 30, 99, 0.15);
        }

        .card .icon {
            font-size: 40px;
            margin-bottom: 10px;
            display: block;
        }

        .card h3 {
            color: #880e4f;
            font-size: 18px;
            margin-bottom: 6px;
        }

        .card p {
            color: #ad1457;
            font-size: 12px;
        }

        @media (max-width: 768px) {
            header {
                padding: 15px;
                gap: 10px;
                flex-wrap: wrap;
            }

            .main-container {
                flex-direction: column;
            }

            aside {
                width: 100%;
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

            .content {
                padding: 20px;
            }

            .grid-menu {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <header>
        <div class="logo">🍪 Sweet Bite Bakery</div>

        <div class="user-info">
            ¡Hola, <?php echo htmlspecialchars($nombre_usuario); ?>! 🌸
        </div>
    </header>

    <div class="main-container">

        <?php include('sidebar.php'); ?>

        <main class="content">

            <div class="welcome-card">
                <h1>Bienvenida al Panel de Control ✨</h1>
                <p>
                    Selecciona una opción del menú lateral o de los accesos
                    rápidos para comenzar a gestionar el sistema.
                </p>
            </div>

            <div class="grid-menu">

                <a href="roles.php" class="card">
                    <span class="icon">🔑</span>
                    <h3>Roles</h3>
                    <p>Gestionar niveles de acceso al sistema</p>
                </a>

                <a href="categorias.php" class="card">
                    <span class="icon">🏷️</span>
                    <h3>Categorías</h3>
                    <p>Clasificar el menú de productos</p>
                </a>

                <a href="productos.php" class="card">
                    <span class="icon">🍪</span>
                    <h3>Productos</h3>
                    <p>Administrar los productos de la repostería</p>
                </a>

                <a href="catalogo_galletas.php" class="card">
                    <span class="icon">📜</span>
                    <h3>Catálogo de Galletas</h3>
                    <p>Consultar productos y precios</p>
                </a>

                <a href="inventarios.php" class="card">
                    <span class="icon">📦</span>
                    <h3>Inventario</h3>
                    <p>Controlar stock y existencias</p>
                </a>

                <a href="carritos.php" class="card">
                    <span class="icon">🛒</span>
                    <h3>Carrito</h3>
                    <p>Consultar los carritos de compra</p>
                </a>

                <a href="pedidos.php" class="card">
                    <span class="icon">📋</span>
                    <h3>Pedidos</h3>
                    <p>Gestionar los pedidos</p>
                </a>

                <a href="pagos.php" class="card">
                    <span class="icon">💳</span>
                    <h3>Pagos</h3>
                    <p>Consultar los pagos registrados</p>
                </a>

                <a href="registrar_usuario.php" class="card">
                    <span class="icon">👥</span>
                    <h3>Registrar Usuarias</h3>
                    <p>Gestionar y agregar personal al equipo</p>
                </a>

                <a href="pedidos_especiales.php" class="card">
                    <span class="icon">🎂</span>
                    <h3>Pedidos Especiales</h3>
                    <p>Administrar pedidos personalizados</p>
                </a>

                <a href="ventas_reportes.php" class="card">
                    <span class="icon">📊</span>
                    <h3>Ventas y Reportes</h3>
                    <p>Revisar estadísticas del negocio</p>
                </a>

            </div>

        </main>
    </div>

</body>
</html>