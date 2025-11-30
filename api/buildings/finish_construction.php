<?php
session_start();
require_once '../../config/connection.php';

if (!isset($_SESSION['jugador_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$jugador_id = $_SESSION['jugador_id'];

// Buscar edificios que ya terminaron de construirse
$query = "UPDATE edificios_jugador 
          SET en_construccion = 0, 
              en_mejora = 0,
              tiempo_finalizacion = NULL
          WHERE jugador_id = ? 
          AND (en_construccion = 1 OR en_mejora = 1)
          AND tiempo_finalizacion <= NOW()";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();

$edificios_finalizados = $stmt->affected_rows;

echo json_encode([
    'success' => true,
    'finalizados' => $edificios_finalizados
]);
?>