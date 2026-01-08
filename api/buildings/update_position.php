<?php
session_start();
require_once '../../config/connection.php';

// Verificar que el usuario este logueado
if (!isset($_SESSION['jugador_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$edificio_id = $data['edificio_id'] ?? null;
$posicion = $data['posicion'] ?? null;

if (!$edificio_id || $posicion === null) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit();
}

$jugador_id = $_SESSION['jugador_id'];

// Actualizar la posicion del edificio
$query = "UPDATE edificios_jugador 
          SET posicion_x = ? 
          WHERE id = ? AND jugador_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $posicion, $edificio_id, $jugador_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Posición actualizada']);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al actualizar']);
}
?>