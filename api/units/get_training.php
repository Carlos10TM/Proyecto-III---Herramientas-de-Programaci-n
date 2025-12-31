<?php
session_start();
require_once '../../config/connection.php';

if (!isset($_SESSION['jugador_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$jugador_id = $_SESSION['jugador_id'];

// Obtener unidades en entrenamiento
$query = "SELECT uj.*, uc.nombre, uc.tipo, uc.tiempo_entrenamiento,
          TIMESTAMPDIFF(SECOND, NOW(), uj.tiempo_finalizacion) as segundos_restantes,
          (uc.tiempo_entrenamiento * uj.en_entrenamiento) as tiempo_total_estimado
          FROM unidades_jugador uj
          JOIN unidades_catalogo uc ON uj.unidad_catalogo_id = uc.id
          WHERE uj.jugador_id = ? 
          AND uj.en_entrenamiento > 0
          ORDER BY uj.tiempo_finalizacion ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$result = $stmt->get_result();

$unidades_entrenando = [];
while ($row = $result->fetch_assoc()) {
    $unidades_entrenando[] = $row;
}

echo json_encode($unidades_entrenando);
?>