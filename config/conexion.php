<?php
// config/conexion.php - Conexión robusta para Sweet Bite Bakery
$host = "localhost";
$usuario = "root";
$password = "";
$basedatos = "sweetbite";

// Habilitar reporte estricto de errores para desarrollo y seguridad
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conexion = new mysqli($host, $usuario, $password, $basedatos);
    $conexion->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    // Registro interno y mensaje limpio para la interfaz
    error_log("Error de conexión a la base de datos: " . $e->getMessage());
    die("Lo sentimos, el sistema de Sweet Bite Bakery experimenta problemas técnicos temporales.");
}
?>