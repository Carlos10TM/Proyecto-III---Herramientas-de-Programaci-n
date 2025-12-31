<?php
session_start();
require_once '../../config/connection.php';

if (!isset($_SESSION['jugador_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$jugador_id = $_SESSION['jugador_id'];

// Obtener el nivel del cuartel del jugador
$query = "SELECT ej.nivel 
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
        'unidades' => []
    ]);
    exit();
}

$nivel_cuartel = $cuartel['nivel'];

// Obtener unidades disponibles segun el nivel del cuartel
$query = "SELECT uc.*,
          (SELECT cantidad FROM unidades_jugador 
           WHERE jugador_id = ? AND unidad_catalogo_id = uc.id) as cantidad_actual
          FROM unidades_catalogo uc
          WHERE uc.nivel_cuartel_requerido <= ?
          ORDER BY uc.nivel_cuartel_requerido, uc.id";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $jugador_id, $nivel_cuartel);
$stmt->execute();
$result = $stmt->get_result();

$unidades = [];
while ($row = $result->fetch_assoc()) {
    $unidades[] = [
        'id' => $row['id'],
        'nombre' => $row['nombre'],
        'tipo' => $row['tipo'],
        'ataque' => $row['ataque'],
        'defensa' => $row['defensa'],
        'vida' => $row['vida'],
        'costo_oro' => $row['costo_oro'],
        'costo_comida' => $row['costo_comida'],
        'tiempo_entrenamiento' => $row['tiempo_entrenamiento'],
        'descripcion' => $row['descripcion'],
        'cantidad_actual' => $row['cantidad_actual'] ?? 0
    ];
}

echo json_encode([
    'tiene_cuartel' => true,
    'nivel_cuartel' => $nivel_cuartel,
    'unidades' => $unidades
]);
?>