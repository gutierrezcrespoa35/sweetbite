<?php
session_start();
include('config/conexion.php');

// Validar inicio de sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$nombre_usuario = $_SESSION['nombre_completo'] ?? $_SESSION['usuario'];
$mensaje = "";

// Procesar el registro del producto
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['nombre'])) {
    $id_categoria = intval($_POST['id_categoria'] ?? 0);
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio = floatval($_POST['precio'] ?? 0);
    $imagen = trim($_POST['imagen'] ?? 'default.jpg');
    $estado = 1;

    if (!empty($nombre) && $id_categoria > 0 && $precio > 0) {
        $stmt = $conexion->prepare("INSERT INTO productos (id_categoria, nombre, descripcion, precio, imagen, estado) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("issdsi", $id_categoria, $nombre, $descripcion, $precio, $imagen, $estado);
            if ($stmt->execute()) {
                $mensaje = "<div class='alert success'>¡Producto registrado con éxito! 🍪✨</div>";
            } else {
                $mensaje = "<div class='alert error'>Error al guardar: " . $stmt->error . "</div>";
            }
            $stmt->close();
        } else {
            $mensaje = "<div class='alert error'>Error en la consulta: " . $conexion->error . "</div>";
        }
    } else {
        $mensaje = "<div class='alert error'>Por favor completa todos los campos requeridos.</div>";
    }
}

// Obtener categorías activas para el formulario
$query_categorias = $conexion->query("SELECT id_categoria, nombre FROM categorias WHERE estado = 1 OR estado IS NULL");

// Obtener lista de productos junto con el nombre de su categoría (JOIN)
$sql_productos = "SELECT p.*, c.nombre AS categoria_nombre 
                  FROM productos p 
                  INNER JOIN categorias c ON p.id_categoria = c.id_categoria";
$resultado_productos = $conexion->query($sql_productos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sweet Bite 🍪 - Productos</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #fce4ec; min-height: 100vh; display: flex; flex-direction: column; }
        header { background-color: #ffffff; padding: 15px 35px; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f8bbd0; box-shadow: 0 4px 10px rgba(233, 30, 99, 0.05); }
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
        .card-box { background: white; padding: 25px; border-radius: 20px; border: 2px solid #f8bbd0; box-shadow: 0 8px 20px rgba(233, 30, 99, 0.05); margin-bottom: 25px; }
        h2 { color: #880e4f; margin-bottom: 15px; font-size: 22px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #ad1457; font-weight: 600; font-size: 14px; }
        input[type="text"], input[type="number"], select { width: 100%; padding: 10px 15px; border: 2px solid #f8bbd0; border-radius: 10px; outline: none; font-size: 14px; background: white; }
        input[type="text"]:focus, input[type="number"]:focus, select:focus { border-color: #ec407a; }
        .btn-submit { background-color: #ec407a; color: white; border: none; padding: 10px 20px; font-weight: bold; border-radius: 10px; cursor: pointer; transition: 0.3s; }
        .btn-submit:hover { background-color: #d81b60; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        table th { background-color: #f8bbd0; color: #880e4f; text-align: left; padding: 12px; font-size: 14px; border-radius: 8px 8px 0 0; }
        table td { padding: 12px; border-bottom: 1px solid #f8bbd0; color: #ad1457; font-size: 14px; }
        .alert { padding: 12px 15px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; font-size: 14px; }
        .alert.success { background-color: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .alert.error { background-color: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }
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
                <li><a href="productos.php" class="active">🍪 Productos</a></li>
                <li><a href="catalogo_galletas.php">📜 Catálogo Galletas</a></li>
                <li><a href="inventarios.php">📦 Inventario</a></li>
                <li><a href="registrar_usuario.php">👥 Registrar Usuarias</a></li>
                <li><a href="pedidos_especiales.php">📦 Pedidos Especiales</a></li>
                <li><a href="ventas_reportes.php">📊 Ventas / Reportes</a></li>
                <li><a href="cerrar_sesion.php" style="color: #c62828;">🚪 Cerrar Sesión</a></li>
            </ul>
        </aside>

        <main class="content">
            <?php echo $mensaje; ?>

            <div class="card-box">
                <h2> Registrar Producto / Galleta 🍪</h2>
                <form action="productos.php" method="POST">
                    <div class="form-group">
                        <label for="id_categoria">Categoría del Producto:</label>
                        <select name="id_categoria" id="id_categoria" required>
                            <option value="">-- Selecciona una categoría --</option>
                            <?php 
                            if ($query_categorias && $query_categorias->num_rows > 0) {
                                while ($cat = $query_categorias->fetch_assoc()) {
                                    $cat_id = $cat['id_categoria'] ?? $cat['id'];
                                    $cat_nombre = $cat['nombre'] ?? $cat['nombre_categoria'];
                                    echo "<option value='{$cat_id}'>" . htmlspecialchars($cat_nombre) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="nombre">Nombre de la Galleta:</label>
                        <input type="text" id="nombre" name="nombre" placeholder="Ej: Galleta Choco Chips Extra" required>
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción:</label>
                        <input type="text" id="descripcion" name="descripcion" placeholder="Ej: Con chispas de chocolate amargo 70%">
                    </div>

                    <div class="form-group">
                        <label for="precio">Precio (Bs.):</label>
                        <input type="number" step="0.50" id="precio" name="precio" placeholder="Ej: 12.50" required>
                    </div>

                    <button type="submit" class="btn-submit">Guardar Producto</button>
                </form>
            </div>

            <div class="card-box">
                <h2>Lista de Productos Registrados</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Categoría</th>
                            <th>Producto</th>
                            <th>Descripción</th>
                            <th>Precio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($resultado_productos && $resultado_productos->num_rows > 0) {
                            while ($row = $resultado_productos->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['id_producto']) . "</td>";
                                echo "<td><strong>" . htmlspecialchars($row['categoria_nombre']) . "</strong></td>";
                                echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['descripcion']) . "</td>";
                                echo "<td>Bs. " . number_format($row['precio'], 2) . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5'>No hay productos registrados aún.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

</body>
</html>