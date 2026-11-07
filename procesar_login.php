<?php
session_start();
require_once __DIR__ . "/config/conexion.php";
$usuario = trim($_POST["usuario"] ?? "");
$password = $_POST["password"] ?? "";
if ($usuario === "" || $password === "") {
    header("Location: login.php?error=campos");
    exit;
}
$sql = "SELECT id_usuario, usuario, password, nombre_completo, rol, estado
        FROM usuarios
        WHERE usuario = ?
        LIMIT 1";
$stmt = $conexion->prepare($sql);
if (!$stmt) {
    die("Error al preparar la consulta: " . $conexion->error);
}
$stmt->bind_param("s", $usuario);
$stmt->execute();
$resultado = $stmt->get_result();
$datos = $resultado->fetch_assoc();
$stmt->close();
if (!$datos) {
    header("Location: login.php?error=credenciales");
    exit;
}
$claveGuardada = (string)$datos["password"];
if (
    !password_verify($password, $claveGuardada) &&
    !hash_equals($claveGuardada, $password)
) {
    header("Location: login.php?error=credenciales");
    exit;
}
$estado = strtolower(trim((string)$datos["estado"]));
if (!in_array($estado, ["activo", "activa", "1"], true)) {
    header("Location: login.php?error=inactivo");
    exit;
}
session_regenerate_id(true);
$_SESSION["id_usuario"] = (int)$datos["id_usuario"];
$_SESSION["usuario"] = $datos["usuario"];
$_SESSION["nombre_completo"] = $datos["nombre_completo"];
$_SESSION["rol"] = strtolower(trim((string)$datos["rol"]));
$rol = $_SESSION["rol"];
$rol = str_replace(
    ["á", "é", "í", "ó", "ú"],
    ["a", "e", "i", "o", "u"],
    $rol
);
if (strpos($rol, "cliente") !== false) {
    header("Location: tienda.php");
    exit;
}
header("Location: /sweetbite/index.php");
exit;