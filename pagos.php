<?php
session_start();
include('config/conexion.php');

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$nombre_usuario = $_SESSION['nombre_completo'] ?? $_SESSION['usuario'];
$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_pedido = intval($_POST['id_pedido']);
    $metodo_pago = $_POST['metodo_pago'];
    $monto = floatval($_POST['monto']);
    $estado = $_POST['estado'];

    if ($id_pedido > 0 && $monto >= 0) {
        $stmt = $conexion->prepare("INSERT INTO pagos (id_pedido, metodo_pago, monto, estado) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isds", $id_pedido, $metodo_pago, $monto, $estado);

        if ($stmt->execute()) {
            $mensaje = "<div class='alert success'>¡Pago registrado correctamente! 💳✨</div>";
        } else {
            $mensaje = "<div class='alert error'>Error al guardar: " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
}

$pedidos = $conexion->query("SELECT id_pedido FROM pedidos ORDER BY id_pedido DESC");

$sql = "SELECT pa.id_pago, pa.id_pedido, pa.metodo_pago, pa.monto, pa.fecha_pago, pa.estado
        FROM pagos pa
        INNER JOIN pedidos pe ON pa.id_pedido = pe.id_pedido
        ORDER BY pa.id_pago DESC";
$resultado = $conexion->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sweet Bite 🍪 - Pagos</title>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { background-color: #fce4ec; min-height: 100vh; display: flex; flex-direction: column; }
header { background-color: #ffffff; padding: 15px 35px; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f8bbd0; }
.logo { font-size: 22px; font-weight: bold; color: #880e4f; }
.user-info { font-size: 14px; color: #ad1457; font-weight: 600; }
.main-container { display: flex; flex: 1; }
aside { width: 240px; background: #ffffff; padding: 25px 15px; border-right: 2px solid #f8bbd0; min-height: 100vh; }
aside p { font-size: 11px; font-weight: bold; color: #f48fb1; text-transform: uppercase; margin-bottom: 12px; padding-left: 10px; }
aside ul { list-style: none; }
aside ul li { margin-bottom: 8px; }
aside ul li a { display: block; padding: 12px 15px; color: #ad1457; text-decoration: none; font-weight: 600; border-radius: 12px; transition: 0.3s; font-size: 14px; }
aside ul li a:hover, aside ul li a.active { background-color: #f8bbd0; color: #880e4f; }
.content { flex: 1; padding: 35px; }
.card-box { background: white; padding: 25px; border-radius: 20px; border: 2px solid #f8bbd0; margin-bottom: 25px; }
.card-header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px; }
h2 { color: #880e4f; font-size: 22px; }
.form-group { margin-bottom: 15px; }
label { display: block; margin-bottom: 5px; color: #ad1457; font-weight: 600; font-size: 14px; }
input, select { width: 100%; padding: 10px 15px; border: 2px solid #f8bbd0; border-radius: 10px; outline: none; font-size: 14px; background: white; }
.input-search { width: 250px; }
.btn-submit { background-color: #ec407a; color: white; border: none; padding: 10px 20px; font-weight: bold; border-radius: 10px; cursor: pointer; }
.btn-submit:hover { background-color: #d81b60; }
table { width: 100%; border-collapse: collapse; margin-top: 15px; }
table th { background-color: #f8bbd0; color: #880e4f; text-align: left; padding: 12px; font-size: 14px; }
table td { padding: 12px; border-bottom: 1px solid #f8bbd0; color: #ad1457; font-size: 14px; }
.alert { padding: 12px 15px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; }
.alert.success { background-color: #e8f5e9; color: #2e7d32; }
.alert.error { background-color: #ffebee; color: #c62828; }
.select2-container { width: 100% !important; }
.select2-container--default .select2-selection--single { height: 42px; border: 2px solid #f8bbd0; border-radius: 10px; display: flex; align-items: center; }
</style>
</head>
<body>

<header>
    <div class="logo">🍪 Sweet Bite Bakery</div>
    <div class="user-info">¡Hola, <?php echo htmlspecialchars($nombre_usuario); ?>! 🌸</div>
</header>

<div class="main-container">
    <?php include('sidebar.php'); ?>

    <main class="content">
        <?php echo $mensaje; ?>

        <div class="card-box">
            <h2>Registrar Pago 💳</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Pedido:</label>
                    <select name="id_pedido" id="id_pedido" required>
                        <option value="">-- Selecciona o busca un Pedido --</option>
                        <?php
                        while ($p = $pedidos->fetch_assoc()) {
                            echo "<option value='{$p['id_pedido']}'>Pedido #{$p['id_pedido']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Método de Pago:</label>
                    <select name="metodo_pago" required>
                        <option value="Efectivo">Efectivo</option>
                        <option value="QR">QR</option>
                        <option value="Transferencia">Transferencia</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Monto:</label>
                    <input type="number" name="monto" step="0.01" min="0" placeholder="Ej: 50.00" required>
                </div>

                <div class="form-group">
                    <label>Estado:</label>
                    <select name="estado" required>
                        <option value="Pendiente">Pendiente</option>
                        <option value="Pagado">Pagado</option>
                        <option value="Rechazado">Rechazado</option>
                    </select>
                </div>

                <button type="submit" class="btn-submit">Guardar Pago</button>
            </form>
        </div>

        <div class="card-box">
            <div class="card-header-flex">
                <h2>Lista de Pagos</h2>
                <input type="text" id="buscar" class="input-search" placeholder="🔍 Filtrar lista...">
            </div>

            <table id="tabla">
                <thead>
                    <tr>
                        <th>ID Pago</th>
                        <th>Pedido</th>
                        <th>Método de Pago</th>
                        <th>Monto</th>
                        <th>Fecha de Pago</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ($resultado && $resultado->num_rows > 0) {
                    while ($row = $resultado->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['id_pago']) . "</td>";
                        echo "<td>Pedido #" . htmlspecialchars($row['id_pedido']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['metodo_pago']) . "</td>";
                        echo "<td>Bs. " . number_format($row['monto'], 2) . "</td>";
                        echo "<td>" . htmlspecialchars($row['fecha_pago']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['estado']) . "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='6'>No existen pagos registrados.</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<script>
$(document).ready(function() {
    $('#id_pedido').select2({ placeholder: "-- Selecciona o busca un Pedido --", allowClear: true });
});

document.getElementById('buscar').addEventListener('keyup', function() {
    let filtro = this.value.toLowerCase();
    let filas = document.querySelectorAll('#tabla tbody tr');
    filas.forEach(function(fila) {
        let texto = fila.textContent.toLowerCase();
        fila.style.display = texto.includes(filtro) ? '' : 'none';
    });
});
</script>
</body>
</html>