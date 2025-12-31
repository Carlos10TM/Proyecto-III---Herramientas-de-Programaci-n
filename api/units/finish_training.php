<?php
session_start();
require_once '../../config/connection.php';

if (!isset($_SESSION['jugador_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$jugador_id = $_SESSION['jugador_id'];

// Buscar unidades que terminaron de entrenarse
$query = "UPDATE unidades_jugador 
          SET cantidad = cantidad + en_entrenamiento,
              en_entrenamiento = 0,
              tiempo_finalizacion = NULL
          WHERE jugador_id = ? 
          AND en_entrenamiento > 0
          AND tiempo_finalizacion <= NOW()";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();

$unidades_finalizadas = $stmt->affected_rows;

echo json_encode([
    'success' => true,
    'finalizadas' => $unidades_finalizadas
]);
?>