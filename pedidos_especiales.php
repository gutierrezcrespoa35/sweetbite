<?php
session_start();
require_once "config/conexion.php";

if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit();
}

$nombre_usuario = $_SESSION["nombre_completo"] ?? $_SESSION["usuario"];
$mensaje = "";
$tipo_mensaje = "";

$hoy = date("Y-m-d");

// Recuperar mensajes después de guardar el encargo.
if (isset($_SESSION["mensaje_especial"])) {
    $mensaje = $_SESSION["mensaje_especial"]["texto"];
    $tipo_mensaje = $_SESSION["mensaje_especial"]["tipo"];
    unset($_SESSION["mensaje_especial"]);
}

// Registrar encargo.
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["registrar_encargo"])) {

    $cliente = trim($_POST["cliente"] ?? "");
    $detalle = trim($_POST["detalle"] ?? "");
    $fecha_entrega = trim($_POST["fecha_entrega"] ?? "");
    $estado = "Pendiente";

    $fecha_valida = DateTime::createFromFormat("!Y-m-d", $fecha_entrega);
    $fecha_correcta = $fecha_valida &&
        $fecha_valida->format("Y-m-d") === $fecha_entrega;

    if ($cliente === "" || $detalle === "" || $fecha_entrega === "") {
        $mensaje = "Completa todos los campos para registrar tu encargo.";
        $tipo_mensaje = "error";

    } elseif (strlen($cliente) > 150 || strlen($detalle) > 2000) {
        $mensaje = "El nombre o el detalle del encargo es demasiado largo.";
        $tipo_mensaje = "error";

    } elseif (!$fecha_correcta || $fecha_entrega < $hoy) {
        $mensaje = "Selecciona una fecha de entrega válida, desde hoy en adelante.";
        $tipo_mensaje = "error";

    } else {
        $stmt = $conexion->prepare(
            "INSERT INTO pedidos_especiales
             (cliente, detalle, fecha_entrega, estado)
             VALUES (?, ?, ?, ?)"
        );

        if ($stmt) {
            $stmt->bind_param(
                "ssss",
                $cliente,
                $detalle,
                $fecha_entrega,
                $estado
            );

            if ($stmt->execute()) {
                $stmt->close();

                $_SESSION["mensaje_especial"] = [
                    "texto" => "¡Tu encargo especial se registró correctamente! 🎀",
                    "tipo" => "success"
                ];

                header("Location: pedidos_especiales.php");
                exit();

            } else {
                $mensaje = "No se pudo registrar el encargo. Verifica los datos e inténtalo nuevamente.";
                $tipo_mensaje = "error";
                $stmt->close();
            }
        } else {
            $mensaje = "No se pudo preparar el registro. Verifica la estructura de la tabla pedidos_especiales.";
            $tipo_mensaje = "error";
        }
    }
}

// Consultar encargos registrados.
$resultado_especiales = $conexion->query(
    "SELECT id_pedido_especial, cliente, detalle, fecha_entrega, estado
     FROM pedidos_especiales
     ORDER BY id_pedido_especial DESC"
);

if (!$resultado_especiales) {
    $mensaje = "No se pudo cargar la lista de encargos. Verifica la tabla pedidos_especiales.";
    $tipo_mensaje = "error";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sweet Bite 🍪 - Pedidos Especiales</title>

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
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        header {
            background: white;
            padding: 18px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            border-bottom: 1px solid #f8bbd0;
        }

        .logo {
            font-size: 23px;
            font-weight: bold;
            color: #c2185b;
        }

        .user-info {
            color: #ad1457;
            font-size: 14px;
            font-weight: 600;
        }

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
            transition: .2s;
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

        main {
            flex: 1;
            width: 100%;
            max-width: 1250px;
            margin: 0 auto;
            padding: 30px 5%;
        }

        .intro {
            margin-bottom: 22px;
        }

        .intro h1 {
            color: #880e4f;
            font-size: 28px;
            margin-bottom: 8px;
        }

        .intro p {
            color: #98516e;
            line-height: 1.6;
        }

        .layout {
            display: grid;
            grid-template-columns: minmax(260px, .8fr) minmax(0, 1.5fr);
            gap: 22px;
            align-items: start;
        }

        .card-box {
            background: white;
            padding: 23px;
            border-radius: 18px;
            border: 1px solid #f2bfd1;
            box-shadow: 0 5px 16px rgba(136, 14, 79, .06);
            min-width: 0;
        }

        h2 {
            color: #880e4f;
            font-size: 20px;
            margin-bottom: 17px;
        }

        .description {
            font-size: 13px;
            line-height: 1.6;
            color: #98516e;
            margin-top: -8px;
            margin-bottom: 16px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            color: #ad1457;
            font-weight: 600;
            font-size: 14px;
        }

        input,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #efb4cb;
            border-radius: 10px;
            background: #fffafd;
            color: #702342;
            outline: none;
            font-size: 14px;
        }

        textarea {
            resize: vertical;
            min-height: 110px;
        }

        input:focus,
        textarea:focus {
            border-color: #c2185b;
            box-shadow: 0 0 0 3px rgba(194, 24, 91, .08);
        }

        .btn {
            display: inline-block;
            padding: 12px 18px;
            border: none;
            border-radius: 10px;
            text-align: center;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
            cursor: pointer;
            transition: .2s;
        }

        .btn-principal {
            background: #c2185b;
            color: white;
            width: 100%;
        }

        .btn-principal:hover {
            background: #ad1457;
            transform: translateY(-1px);
        }

        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .alert.success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .alert.error {
            background: #ffebee;
            color: #c62828;
        }

        .table-wrap {
            overflow-x: auto;
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 560px;
        }

        th {
            background: #fce4ec;
            color: #880e4f;
            text-align: left;
            padding: 13px;
            font-size: 13px;
        }

        td {
            padding: 13px;
            border-bottom: 1px solid #f8d7e3;
            color: #702342;
            font-size: 13px;
            vertical-align: top;
            overflow-wrap: anywhere;
        }

        .estado {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            background: #fff3cd;
            color: #856404;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .empty {
            padding: 22px;
            text-align: center;
            color: #98516e;
            background: #fff8fb;
            border: 1px dashed #efb4cb;
            border-radius: 12px;
            line-height: 1.7;
        }

        footer {
            text-align: center;
            padding: 20px;
            background: white;
            border-top: 1px solid #f8bbd0;
            color: #98516e;
            font-size: 13px;
            margin-top: 25px;
        }

        @media (max-width: 850px) {
            .layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 650px) {
            .panel-nav {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                padding: 15px 4%;
            }

            main {
                padding: 22px 4%;
            }

            .card-box {
                padding: 18px;
            }

            .intro h1 {
                font-size: 24px;
            }
        }
    </style>
</head>

<body>

<header>
    <div class="logo">🍪 Sweet Bite Bakery</div>
    <div class="user-info">
        ¡Hola, <?= htmlspecialchars($nombre_usuario) ?>! 🌸
    </div>
</header>

<nav class="panel-nav">
    <a href="tienda.php" class="nav-card">
        <span>🏠</span>Inicio
    </a>

    <a href="catalogo_galletas.php" class="nav-card">
        <span>🍪</span>Catálogo
    </a>

    <a href="carritos.php" class="nav-card">
        <span>🛒</span>Mi carrito
    </a>

    <a href="mis_pedidos.php" class="nav-card">
        <span>📦</span>Mis pedidos
    </a>

    <a href="pagos.php" class="nav-card">
        <span>💳</span>Pagos
    </a>

    <a href="pedidos_especiales.php" class="nav-card active">
        <span>🎂</span>Pedidos especiales
    </a>
</nav>

<main>
    <div class="intro">
        <h1>🎂 Pedidos especiales</h1>
        <p>
            ¿Tienes una celebración o quieres galletas personalizadas?
            Cuéntanos qué necesitas y registra los detalles de tu encargo.
        </p>
    </div>

    <?php if ($mensaje !== ""): ?>
        <div class="alert <?= $tipo_mensaje === "success" ? "success" : "error" ?>">
            <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>

    <div class="layout">

        <section class="card-box">
            <h2>🎀 Crear un encargo</h2>

            <p class="description">
                Completa los datos para registrar tu pedido especial.
            </p>

            <form action="pedidos_especiales.php" method="POST">

                <div class="form-group">
                    <label for="cliente">Nombre del cliente</label>
                    <input
                        type="text"
                        id="cliente"
                        name="cliente"
                        maxlength="150"
                        value="<?= htmlspecialchars($nombre_usuario) ?>"
                        placeholder="Escribe tu nombre"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="detalle">Detalle del pedido</label>
                    <textarea
                        id="detalle"
                        name="detalle"
                        maxlength="2000"
                        placeholder="Ej.: 24 galletas decoradas para un cumpleaños, con colores rosados y nombre personalizado..."
                        required
                    ></textarea>
                </div>

                <div class="form-group">
                    <label for="fecha_entrega">Fecha de entrega</label>
                    <input
                        type="date"
                        id="fecha_entrega"
                        name="fecha_entrega"
                        min="<?= htmlspecialchars($hoy) ?>"
                        required
                    >
                </div>

                <button
                    type="submit"
                    name="registrar_encargo"
                    value="1"
                    class="btn btn-principal"
                >
                    Registrar encargo 💗
                </button>
            </form>
        </section>

        <section class="card-box">
            <h2>📋 Encargos registrados</h2>

            <p class="description">
                Consulta los encargos guardados en el sistema.
            </p>

            <?php if ($resultado_especiales && $resultado_especiales->num_rows > 0): ?>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Detalle</th>
                                <th>Entrega</th>
                                <th>Estado</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php while ($row = $resultado_especiales->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?= htmlspecialchars($row["cliente"]) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= nl2br(htmlspecialchars($row["detalle"])) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row["fecha_entrega"]) ?>
                                </td>

                                <td>
                                    <span class="estado">
                                        <?= htmlspecialchars($row["estado"]) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>
                <div class="empty">
                    🍪 Todavía no hay encargos registrados.
                    <br>
                    ¡Puedes registrar el primero con el formulario!
                </div>
            <?php endif; ?>
        </section>

    </div>
</main>

<footer>
    🍪 Sweet Bite Bakery — Hecho con amor y dulzura 💗
</footer>

</body>
</html>