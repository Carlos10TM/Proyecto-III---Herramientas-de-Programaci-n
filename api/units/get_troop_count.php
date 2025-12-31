<?php
session_start();
require_once '../../config/connection.php';

if (!isset($_SESSION['jugador_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$jugador_id = $_SESSION['jugador_id'];

$query = "SELECT j.limite_tropas,
          COALESCE((SELECT SUM(cantidad + en_entrenamiento) FROM unidades_jugador WHERE jugador_id = ?), 0) as tropas_actuales
          FROM jugadores j
          WHERE j.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $jugador_id, $jugador_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo json_encode($result);
?>