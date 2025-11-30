<?php
session_start();
require_once '../../config/connection.php';

if (!isset($_SESSION['jugador_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$jugador_id = $_SESSION['jugador_id'];

// Obtener edificios terminados
$query = "SELECT ej.*, ec.nombre, ec.tipo, ec.descripcion,
          en.generacion_por_minuto, en.bonus_tropas,
          en.costo_madera as costo_mejora_madera,
          en.costo_piedra as costo_mejora_piedra,
          en.costo_comida as costo_mejora_comida,
          en.tiempo_construccion as tiempo_mejora
          FROM edificios_jugador ej
          JOIN edificios_catalogo ec ON ej.edificio_catalogo_id = ec.id
          LEFT JOIN edificios_niveles en ON (ec.id = en.edificio_catalogo_id AND en.nivel = ej.nivel + 1)
          WHERE ej.jugador_id = ? AND ej.en_construccion = 0 AND ej.en_mejora = 0
          ORDER BY ec.tipo, ej.nivel DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$result = $stmt->get_result();

$edificios_terminados = [];
while ($row = $result->fetch_assoc()) {
    $edificios_terminados[] = $row;
}

// Obtener edificios en construccion o mejora
$query = "SELECT ej.*, ec.nombre, ec.tipo, ec.descripcion,
          en.tiempo_construccion,
          TIMESTAMPDIFF(SECOND, NOW(), ej.tiempo_finalizacion) as segundos_restantes
          FROM edificios_jugador ej
          JOIN edificios_catalogo ec ON ej.edificio_catalogo_id = ec.id
          JOIN edificios_niveles en ON (ec.id = en.edificio_catalogo_id AND en.nivel = ej.nivel)
          WHERE ej.jugador_id = ? AND (ej.en_construccion = 1 OR ej.en_mejora = 1)
          ORDER BY ej.tiempo_finalizacion ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$result = $stmt->get_result();

$edificios_construccion = [];
while ($row = $result->fetch_assoc()) {
    $edificios_construccion[] = $row;
}

echo json_encode([
    'terminados' => $edificios_terminados,
    'en_construccion' => $edificios_construccion
]);
?>