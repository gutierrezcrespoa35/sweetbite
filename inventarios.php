<?php
session_start();
include('config/conexion.php');

// --- 1. RESPUESTA AJAX PARA AUTOCOMPLETAR EL STOCK ---
if (isset($_GET['action']) && $_GET['action'] === 'obtener_stock') {
    header('Content-Type: application/json');
    $id_producto = isset($_GET['id_producto']) ? intval($_GET['id_producto']) : 0;

    if ($id_producto > 0) {
        $stmt = $conexion->prepare("SELECT stock_actual FROM inventario WHERE id_producto = ?");
        $stmt->bind_param("i", $id_producto);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'stock' => $row['stock_actual']]);
        } else {
            // Si el producto aún no ha sido registrado en inventario, inicia en 0
            echo json_encode(['success' => true, 'stock' => 0]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'stock' => '']);
    }
    exit();
}

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$nombre_usuario = $_SESSION['nombre_completo'] ?? $_SESSION['usuario'];
$mensaje = "";

// Actualizar o Guardar Inventario
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_producto'])) {
    $id_producto = intval($_POST['id_producto']);
    $stock_actual = intval($_POST['stock_actual']);
    $stock_minimo = intval($_POST['stock_minimo'] ?? 5);

    if ($id_producto > 0 && $stock_actual >= 0) {
        $check = $conexion->prepare("SELECT id_inventario FROM inventario WHERE id_producto = ?");
        $check->bind_param("i", $id_producto);
        $check->execute();
        $res_check = $check->get_result();

        if ($res_check->num_rows > 0) {
            $stmt = $conexion->prepare("UPDATE inventario SET stock_actual = ?, stock_minimo = ? WHERE id_producto = ?");
            $stmt->bind_param("iii", $stock_actual, $stock_minimo, $id_producto);
        } else {
            $stmt = $conexion->prepare("INSERT INTO inventario (id_producto, stock_actual, stock_minimo) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $id_producto, $stock_actual, $stock_minimo);
        }

        if ($stmt->execute()) {
            $mensaje = "<div class='alert success'>¡Stock actualizado correctamente! 📦✨</div>";
        } else {
            $mensaje = "<div class='alert error'>Error al guardar stock: " . $stmt->error . "</div>";
        }
        $stmt->close();
    } else {
        $mensaje = "<div class='alert error'>Por favor ingresa un stock válido y selecciona un producto.</div>";
    }
}

// Cargar lista de productos creados para el selector
$productos_combo = $conexion->query("SELECT id_producto, nombre FROM productos ORDER BY nombre ASC");

// Consulta relacional INNER JOIN entre inventario y productos
$sql_inventario = "SELECT 
                    i.id_inventario, 
                    p.nombre AS nombre_producto, 
                    p.precio, 
                    i.stock_actual, 
                    i.stock_minimo, 
                    i.fecha_actualizacion 
                   FROM inventario i 
                   INNER JOIN productos p ON i.id_producto = p.id_producto 
                   ORDER BY i.id_inventario DESC";

$resultado_inventario = $conexion->query($sql_inventario);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sweet Bite 🍪 - Inventario</title>
    <link rel="stylesheet" href="CSS/style.css">
    <link rel="stylesheet" href="css/style.css">
    
    <!-- Librería Select2 para selector con buscador -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
        input[type="number"], .input-search { width: 100%; padding: 10px 15px; border: 2px solid #f8bbd0; border-radius: 10px; outline: none; font-size: 14px; background: white; }
        .input-search { width: 250px; }
        .btn-submit { background-color: #ec407a; color: white; border: none; padding: 10px 20px; font-weight: bold; border-radius: 10px; cursor: pointer; }
        .btn-submit:hover { background-color: #d81b60; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        table th { background-color: #f8bbd0; color: #880e4f; text-align: left; padding: 12px; font-size: 14px; }
        table td { padding: 12px; border-bottom: 1px solid #f8bbd0; color: #ad1457; font-size: 14px; }
        .alert { padding: 12px 15px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; }
        .alert.success { background-color: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .alert.error { background-color: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }
        
        /* Ajustes de diseño para el buscador Select2 */
        .select2-container--default .select2-selection--single {
            height: 42px;
            border: 2px solid #f8bbd0;
            border-radius: 10px;
            display: flex;
            align-items: center;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
        }
    </style>
</head>
<body>

    <header>
        <div class="logo">🍪 Sweet Bite Bakery</div>
        <div class="user-info">¡Hola, <?php echo htmlspecialchars($nombre_usuario); ?>! 🌸</div>
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
                <li><a href="inventarios.php" class="active">📦 Inventario</a></li>
                <li><a href="carritos.php">🛒 Carrito</a></li>
                <li><a href="registrar_usuario.php">👥 Registrar Usuarias</a></li>
                <li><a href="pedidos_especiales.php">📦 Pedidos Especiales</a></li>
                <li><a href="ventas_reportes.php">📊 Ventas / Reportes</a></li>
                <li><a href="cerrar_sesion.php" style="color: #c62828;">🚪 Cerrar Sesión</a></li>
            </ul>
        </aside>

        <main class="content">
            <?php echo $mensaje; ?>

            <div class="card-box">
                <h2>Asignar Stock de Inventario 📦</h2>
                <form action="inventarios.php" method="POST">
                    <div class="form-group">
                        <label for="id_producto">Producto:</label>
                        <select name="id_producto" id="id_producto" style="width: 100%;" required>
                            <option value="">-- Selecciona o busca un Producto --</option>
                            <?php
                            if ($productos_combo && $productos_combo->num_rows > 0) {
                                while ($p = $productos_combo->fetch_assoc()) {
                                    echo "<option value='{$p['id_producto']}'>" . htmlspecialchars($p['nombre']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="stock_actual">Stock Disponible:</label>
                        <input type="number" id="stock_actual" name="stock_actual" placeholder="Ej: 30" required min="0">
                    </div>

                    <div class="form-group">
                        <label for="stock_minimo">Stock Mínimo Alerta:</label>
                        <input type="number" id="stock_minimo" name="stock_minimo" value="5" min="1">
                    </div>

                    <button type="submit" class="btn-submit">Guardar en Inventario</button>
                </form>
            </div>

            <div class="card-box">
                <div class="card-header-flex">
                    <h2>Inventario de Productos</h2>
                    <input type="text" id="buscarInventario" class="input-search" placeholder="🔍 Filtrar lista...">
                </div>
                <table id="tablaInventario">
                    <thead>
                        <tr>
                            <th>ID Inv.</th>
                            <th>Producto</th>
                            <th>Precio Unitario</th>
                            <th>Stock Disponible</th>
                            <th>Stock Mínimo</th>
                            <th>Última Actualización</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($resultado_inventario && $resultado_inventario->num_rows > 0) {
                            while ($row = $resultado_inventario->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['id_inventario']) . "</td>";
                                echo "<td><strong>" . htmlspecialchars($row['nombre_producto']) . "</strong></td>";
                                echo "<td>Bs. " . number_format($row['precio'], 2) . "</td>";
                                echo "<td>" . htmlspecialchars($row['stock_actual']) . " unidades</td>";
                                echo "<td>" . htmlspecialchars($row['stock_minimo']) . " unidades</td>";
                                echo "<td>" . htmlspecialchars($row['fecha_actualizacion']) . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6'>No hay productos con stock asignado en el inventario.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        $(document).ready(function() {
            // Activar selector interactivo con buscador interno
            $('#id_producto').select2({
                placeholder: "-- Selecciona o busca un Producto --",
                allowClear: true
            });

            // --- 2. LOGICA PARA OBTENER EL STOCK AUTOMATICAMENTE AL SELECCIONAR UN PRODUCTO ---
            $('#id_producto').on('change', function() {
                var idProducto = $(this).val();
                
                if (idProducto) {
                    $.ajax({
                        url: 'inventarios.php',
                        type: 'GET',
                        data: {
                            action: 'obtener_stock',
                            id_producto: idProducto
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                $('#stock_actual').val(response.stock);
                            } else {
                                $('#stock_actual').val(0);
                            }
                        },
                        error: function() {
                            $('#stock_actual').val(0);
                        }
                    });
                } else {
                    $('#stock_actual').val('');
                }
            });
        });

        // Buscador directo de la tabla
        document.getElementById('buscarInventario').addEventListener('keyup', function() {
            let filtro = this.value.toLowerCase();
            let filas = document.querySelectorAll('#tablaInventario tbody tr');

            filas.forEach(fila => {
                let texto = fila.textContent.toLowerCase();
                fila.style.display = texto.includes(filtro) ? '' : 'none';
            });
        });
    </script>

</body>
</html>