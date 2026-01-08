<?php
session_start();
require_once '../../config/connection.php';

if (!isset($_SESSION['jugador_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$jugador_id = $_SESSION['jugador_id'];

// Verificar si el jugador tiene un cuartel activo (no destruido)
$query = "SELECT ej.id, ej.nivel, ej.esta_destruido
          FROM edificios_jugador ej
          JOIN edificios_catalogo ec ON ej.edificio_catalogo_id = ec.id
          WHERE ej.jugador_id = ? 
          AND ec.tipo = 'cuartel'
          AND ej.en_construccion = 0
          AND ej.en_mejora = 0";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$result = $stmt->get_result();
$cuartel = $result->fetch_assoc();

if (!$cuartel) {
    echo json_encode([
        'tiene_cuartel' => false,
        'esta_destruido' => false,
        'nivel_cuartel' => 0
    ]);
    exit();
}

echo json_encode([
    'tiene_cuartel' => true,
    'esta_destruido' => (bool)$cuartel['esta_destruido'],
    'nivel_cuartel' => $cuartel['nivel']
]);
?>