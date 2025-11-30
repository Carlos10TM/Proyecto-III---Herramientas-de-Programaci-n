<?php
// Configuracion de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'Carlos');
define('DB_PASS', '243162033');
define('DB_NAME', 'social_kingdooms');

// Crear conexion
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Verificar conexion
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Establecer charset UTF-8
$conn->set_charset("utf8mb4");

// Establecer la zona horaria
date_default_timezone_set('America/Santiago');
$conn->query("SET time_zone = '-03:00'");

// Mostrar mensaje de exito
//echo "Conexión exitosa a la base de datos";
?>