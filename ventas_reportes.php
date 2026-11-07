<?php
session_start();
include('config/conexion.php');

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$nombre_usuario = $_SESSION['nombre_completo'] ?? $_SESSION['usuario'];

// --- 1. MÉTRICAS FINANCIERAS Y KPIs PARA LA EMPRESA ---
$sql_ingresos = "SELECT SUM(total) AS total_ingresos, COUNT(*) AS total_pedidos FROM pedidos WHERE estado IN ('Entregado', 'Completado') OR estado = 'Entregado'";
$res_ingresos = $conexion->query($sql_ingresos)->fetch_assoc();
$total_ingresos = $res_ingresos['total_ingresos'] ?? 0;
$total_pedidos_exitosos = $res_ingresos['total_pedidos'] ?? 0;

// Total de productos en inventario activo
$sql_stock_total = "SELECT SUM(stock_actual) AS inventario_total FROM inventario";
$res_stock = $conexion->query($sql_stock_total)->fetch_assoc();
$inventario_total = $res_stock['inventario_total'] ?? 0;

// --- 2. LISTADO DETALLADO DE VENTAS / PEDIDOS RECIENTES ---
$sql_reportes = "
    SELECT
        p.id_pedido,
        u.nombre_completo AS cliente,
        u.usuario AS username,
        p.fecha_pedido,
        p.estado,
        p.total,
        p.modalidad_entrega
    FROM pedidos p
    INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
    ORDER BY p.id_pedido DESC
    LIMIT 20
";
$resultado_reportes = $conexion->query($sql_reportes);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sweet Bite 🍪 - Ventas y Reportes</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        * {
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            margin: 0;
            padding: 0;
            background-color: #fce4ec;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: #4a2335;
        }

        header {
            background-color: white;
            padding: 15px 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        }

        aside {
            width: 240px;
            flex-shrink: 0;
            background: white;
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
            padding: 0;
        }

        aside ul li {
            margin-bottom: 6px;
        }

        aside ul li a {
            display: block;
            padding: 11px 15px;
            color: #ad1457;
            text-decoration: none;
            font-weight: 600;
            border-radius: 12px;
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

        .page-heading {
            margin-bottom: 25px;
        }

        .page-heading h1 {
            color: #880e4f;
            font-size: 27px;
            margin: 0 0 8px;
        }

        .page-heading p {
            color: #96677b;
            font-size: 14px;
            margin: 0;
        }

        .kpi-container {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .kpi-card {
            background: white;
            padding: 22px;
            border-radius: 16px;
            border: 1px solid #f8bbd0;
            border-left: 5px solid #ec407a;
            min-width: 0;
            box-shadow: 0 4px 15px rgba(233, 30, 99, 0.05);
        }

        .kpi-card small {
            display: block;
            color: #956d7d;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: bold;
        }

        .kpi-card p {
            font-size: 25px;
            color: #ad1457;
            margin: 10px 0 0;
            font-weight: bold;
            overflow-wrap: anywhere;
        }

        .card-box {
            background: white;
            padding: 25px;
            border-radius: 18px;
            border: 1px solid #f8bbd0;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(233, 30, 99, 0.05);
        }

        .report-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }

        h2 {
            color: #880e4f;
            font-size: 21px;
            margin: 0;
        }

        .report-description {
            color: #987385;
            font-size: 13px;
            margin: 8px 0 18px;
        }

        .btn-print {
            background-color: #ec407a;
            color: white;
            border: none;
            padding: 12px 19px;
            font-weight: bold;
            border-radius: 10px;
            cursor: pointer;
            transition: 0.2s;
            white-space: nowrap;
        }

        .btn-print:hover {
            background-color: #d81b60;
            transform: translateY(-1px);
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #f4d5e1;
            border-radius: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            background: white;
        }

        table th {
            background-color: #f8bbd0;
            color: #880e4f;
            text-align: left;
            padding: 14px 12px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }

        table td {
            padding: 13px 12px;
            border-bottom: 1px solid #f5e3ea;
            color: #684356;
            font-size: 13px;
            vertical-align: middle;
        }

        table tbody tr:nth-child(even) {
            background-color: #fff8fb;
        }

        table tbody tr:hover {
            background-color: #fce4ec;
        }

        table tbody tr:last-child td {
            border-bottom: none;
        }

        .badge-estado {
            display: inline-block;
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
        }

        .monto {
            color: #880e4f;
            font-weight: bold;
            white-space: nowrap;
        }

        .print-only {
            display: none;
        }

        .page-footer {
            text-align: center;
            color: #a4778c;
            font-size: 12px;
            padding: 8px 0 0;
        }

        @media (max-width: 1000px) {
            .content {
                padding: 22px;
            }

            .kpi-container {
                grid-template-columns: 1fr;
            }

            aside {
                width: 210px;
            }
        }

        @media (max-width: 650px) {
            .main-container {
                display: block;
            }

            aside {
                width: 100%;
                min-height: auto;
                border-right: none;
                border-bottom: 2px solid #f8bbd0;
            }

            .content {
                padding: 16px;
            }

            header {
                padding: 15px;
                gap: 10px;
                flex-wrap: wrap;
            }

            .report-toolbar {
                align-items: flex-start;
            }

            .btn-print {
                width: 100%;
            }
        }

        /* FORMATO PROFESIONAL PARA IMPRIMIR O GUARDAR COMO PDF */
        @page {
            size: A4 landscape;
            margin: 12mm;
        }

        @media print {
            html,
            body {
                width: 100%;
                min-height: auto;
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
                color: #333 !important;
                display: block !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            header,
            aside,
            .btn-print,
            .page-heading {
                display: none !important;
            }

            .main-container {
                display: block !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .content {
                display: block !important;
                width: 100% !important;
                min-width: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .print-only {
                display: block !important;
            }

            .print-header {
                text-align: center;
                padding: 0 0 12px;
                margin-bottom: 16px;
                border-bottom: 2px solid #ad1457;
            }

            .print-header h1 {
                color: #880e4f !important;
                font-size: 23px;
                margin: 0 0 5px;
            }

            .print-header p {
                color: #666 !important;
                font-size: 11px;
                margin: 4px 0;
            }

            .kpi-container {
                display: grid !important;
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
                gap: 12px !important;
                margin: 0 0 18px !important;
            }

            .kpi-card {
                min-width: 0 !important;
                padding: 12px 15px !important;
                border: 1px solid #e7b5ca !important;
                border-left: 4px solid #ad1457 !important;
                border-radius: 6px !important;
                box-shadow: none !important;
                break-inside: avoid;
            }

            .kpi-card small {
                color: #666 !important;
                font-size: 9px !important;
            }

            .kpi-card p {
                color: #880e4f !important;
                font-size: 17px !important;
                margin-top: 5px !important;
            }

            .card-box {
                display: block !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                border-radius: 0 !important;
                box-shadow: none !important;
            }

            .report-toolbar {
                display: block !important;
                margin-bottom: 5px !important;
            }

            h2 {
                color: #880e4f !important;
                font-size: 15px !important;
                margin: 0 !important;
            }

            .report-description {
                color: #555 !important;
                font-size: 10px !important;
                margin: 5px 0 10px !important;
            }

            .table-container {
                width: 100% !important;
                overflow: visible !important;
                border: 1px solid #dfc4d0 !important;
                border-radius: 0 !important;
            }

            table {
                width: 100% !important;
                border-collapse: collapse !important;
                table-layout: auto !important;
            }

            table thead {
                display: table-header-group;
            }

            table tr {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }

            table th {
                background: #f4d4e2 !important;
                color: #6d123d !important;
                padding: 9px 8px !important;
                border: 1px solid #dfb6c8 !important;
                font-size: 9px !important;
                white-space: normal !important;
            }

            table td {
                padding: 8px !important;
                color: #333 !important;
                border: 1px solid #ead9e1 !important;
                font-size: 9px !important;
                overflow-wrap: anywhere;
            }

            table tbody tr:nth-child(even) {
                background: #fff7fa !important;
            }

            table tbody tr:hover {
                background: inherit !important;
            }

            .badge-estado {
                background: #edf7ee !important;
                color: #25632a !important;
                border: 1px solid #c8e6c9 !important;
                padding: 3px 6px !important;
                font-size: 8px !important;
            }

            .monto {
                color: #880e4f !important;
            }

            .page-footer {
                color: #777 !important;
                font-size: 9px !important;
                margin-top: 14px !important;
                padding-top: 7px !important;
                border-top: 1px solid #e5cbd6 !important;
            }

            * {
                box-shadow: none !important;
                text-shadow: none !important;
            }
        }
    </style>
</head>

<body>
    <header>
        <div class="logo">🍪 Sweet Bite Bakery - Panel Gerencial</div>
        <div class="user-info">
            ¡Hola, <?php echo htmlspecialchars($nombre_usuario); ?>! 🌸
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
                <li><a href="pedidos.php">📋 Pedidos</a></li>
                <li><a href="pagos.php">💳 Pagos</a></li>
                <li><a href="ventas_reportes.php" class="active">📊 Ventas / Reportes</a></li>
                <li><a href="cerrar_sesion.php" style="color:#c62828;">🚪 Cerrar Sesión</a></li>
            </ul>
        </aside>

        <main class="content">

            <div class="page-heading">
                <h1>Ventas y reportes 📊</h1>
                <p>Resumen general de los movimientos de Sweet Bite Bakery.</p>
            </div>

            <!-- Encabezado exclusivo del PDF -->
            <div class="print-only print-header">
                <h1>SWEET BITE BAKERY</h1>
                <p><strong>REPORTE GENERAL DE VENTAS Y TRANSACCIONES</strong></p>
                <p>Fecha de emisión: <?php echo date('d/m/Y H:i'); ?></p>
            </div>

            <!-- Indicadores principales -->
            <div class="kpi-container">
                <div class="kpi-card">
                    <small>Ingresos Totales</small>
                    <p>Bs. <?php echo number_format($total_ingresos, 2); ?></p>
                </div>

                <div class="kpi-card">
                    <small>Pedidos Exitosos</small>
                    <p><?php echo $total_pedidos_exitosos; ?> Ventas</p>
                </div>

                <div class="kpi-card">
                    <small>Stock Global Actual</small>
                    <p><?php echo $inventario_total; ?> Unidades</p>
                </div>
            </div>

            <!-- Tabla del reporte -->
            <div class="card-box">
                <div class="report-toolbar">
                    <h2>Detalle de ventas y pedidos</h2>
                    <button onclick="window.print()" class="btn-print">
                        🖨️ Generar PDF / Imprimir
                    </button>
                </div>

                <p class="report-description">
                    Registro de los últimos 20 pedidos registrados en el sistema.
                </p>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID Pedido</th>
                                <th>Cliente / Usuario</th>
                                <th>Fecha</th>
                                <th>Modalidad</th>
                                <th>Estado</th>
                                <th>Monto Total</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            if ($resultado_reportes && $resultado_reportes->num_rows > 0) {
                                while ($row = $resultado_reportes->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td># " . htmlspecialchars($row['id_pedido']) . "</td>";
                                    echo "<td><strong>" . htmlspecialchars($row['cliente']) . "</strong></td>";
                                    echo "<td>" . htmlspecialchars($row['fecha_pedido']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['modalidad_entrega']) . "</td>";
                                    echo "<td><span class='badge-estado'>" . htmlspecialchars($row['estado']) . "</span></td>";
                                    echo "<td class='monto'>Bs. " . number_format($row['total'], 2) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6' style='text-align:center;'>No hay registros de ventas disponibles.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="page-footer">
                Sweet Bite Bakery · Sistema administrativo · Reporte generado el
                <?php echo date('d/m/Y'); ?>
            </div>

        </main>
    </div>
</body>
</html>