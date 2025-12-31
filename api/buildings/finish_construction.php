<?php
session_start();
require_once '../../config/connection.php';

if (!isset($_SESSION['jugador_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$jugador_id = $_SESSION['jugador_id'];

// Buscar edificios que terminaron de mejorar
$query = "UPDATE edificios_jugador 
          SET nivel = nivel + 1,
              en_mejora = 0,
              tiempo_finalizacion = NULL
          WHERE jugador_id = ? 
          AND en_mejora = 1
          AND tiempo_finalizacion <= NOW()";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$edificios_mejorados = $stmt->affected_rows;

// Buscar edificios que terminaron de construirse
$query = "UPDATE edificios_jugador 
          SET en_construccion = 0,
              tiempo_finalizacion = NULL
          WHERE jugador_id = ? 
          AND en_construccion = 1
          AND tiempo_finalizacion <= NOW()";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$edificios_construidos = $stmt->affected_rows;

// Actualizar nivel del ayuntamiento y limite de tropas en la tabla jugadores
$query = "UPDATE jugadores j
          JOIN edificios_jugador ej ON (j.id = ej.jugador_id)
          JOIN edificios_catalogo ec ON (ej.edificio_catalogo_id = ec.id)
          JOIN edificios_niveles en ON (ec.id = en.edificio_catalogo_id AND en.nivel = ej.nivel)
          SET j.nivel_ayuntamiento = ej.nivel,
              j.limite_tropas = en.bonus_tropas
          WHERE j.id = ? AND ec.tipo = 'ayuntamiento'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();

echo json_encode([
    'success' => true,
    'finalizados' => $edificios_construidos + $edificios_mejorados
]);
?>