<?php
session_start();
require_once '../../config/connection.php';

if (!isset($_SESSION['jugador_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$jugador_id = $_SESSION['jugador_id'];

// Obtener cola de entrenamiento
$query = "SELECT ce.*, uc.nombre, uc.tipo,
          TIMESTAMPDIFF(SECOND, NOW(), ce.tiempo_finalizacion) as segundos_restantes
          FROM cola_entrenamiento ce
          JOIN unidades_catalogo uc ON ce.unidad_catalogo_id = uc.id
          WHERE ce.jugador_id = ?
          ORDER BY ce.tiempo_finalizacion ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$result = $stmt->get_result();

$cola = [];
while ($row = $result->fetch_assoc()) {
    $cola[] = $row;
}

echo json_encode($cola);
?>