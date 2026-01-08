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
          -- Estadisticas del nivel actual
          en_actual.generacion_por_minuto as generacion_actual,
          en_actual.bonus_tropas as bonus_tropas_actual,
          en_actual.colas_entrenamiento as colas_entrenamiento,
          en_actual.reduccion_tiempo_entrenamiento as reduccion_tiempo_entrenamiento,
          en_actual.ataque_torre as ataque_torre,
          en_actual.rango_ataque as rango_ataque,
          -- Estadisticas y costos del nivel siguiente (para mejoras)
          en_siguiente.generacion_por_minuto as generacion_siguiente,
          en_siguiente.bonus_tropas as bonus_tropas_siguiente,
          en_siguiente.colas_entrenamiento as colas_entrenamiento_siguiente,
          en_siguiente.reduccion_tiempo_entrenamiento as reduccion_tiempo_entrenamiento_siguiente,
          en_siguiente.ataque_torre as ataque_torre_siguiente,
          en_siguiente.rango_ataque as rango_ataque_siguiente,
          en_siguiente.costo_madera as costo_mejora_madera,
          en_siguiente.costo_piedra as costo_mejora_piedra,
          en_siguiente.costo_comida as costo_mejora_comida,
          en_siguiente.tiempo_construccion as tiempo_mejora
          FROM edificios_jugador ej
          JOIN edificios_catalogo ec ON ej.edificio_catalogo_id = ec.id
          -- Join para nivel actual
          LEFT JOIN edificios_niveles en_actual ON (ec.id = en_actual.edificio_catalogo_id AND en_actual.nivel = ej.nivel)
          -- Join para nivel siguiente
          LEFT JOIN edificios_niveles en_siguiente ON (ec.id = en_siguiente.edificio_catalogo_id AND en_siguiente.nivel = ej.nivel + 1)
          WHERE ej.jugador_id = ? AND ej.en_construccion = 0 AND ej.en_mejora = 0
          ORDER BY ej.esta_destruido ASC, ec.tipo, ej.nivel DESC";

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