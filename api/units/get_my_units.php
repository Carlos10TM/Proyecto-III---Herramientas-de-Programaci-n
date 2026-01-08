<?php
session_start();
require_once '../../config/connection.php';

// Verificar si el jugador esta logueado
if (!isset($_SESSION['jugador_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$jugador_id = $_SESSION['jugador_id'];

// Obtener todas las tropas del jugador que tiene actualmente
$query = "SELECT uj.*, uc.nombre, uc.tipo, uc.ataque, uc.vida as vida_maxima, uc.descripcion,
          COALESCE(uj.vida_actual, uc.vida) as vida_actual
          FROM unidades_jugador uj
          JOIN unidades_catalogo uc ON uj.unidad_catalogo_id = uc.id
          WHERE uj.jugador_id = ? 
          AND uj.cantidad > 0
          ORDER BY uj.id ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$result = $stmt->get_result();

$tropas = [];
while ($row = $result->fetch_assoc()) {
    $tropas[] = [
        'id' => $row['id'],
        'nombre' => $row['nombre'],
        'tipo' => $row['tipo'],
        'ataque' => $row['ataque'],
        'vida_actual' => $row['vida_actual'],
        'vida_maxima' => $row['vida_maxima'],
        'cantidad' => $row['cantidad'],
        'descripcion' => $row['descripcion']
    ];
}

// Obtener posiciones guardadas de tropas
$query = "SELECT * FROM posiciones_tropas WHERE jugador_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$result = $stmt->get_result();

$posiciones = [];
while ($row = $result->fetch_assoc()) {
    $key = $row['unidad_jugador_id'] . '_' . $row['indice_tropa'];
    $posiciones[$key] = $row['posicion'];
}

echo json_encode([
    'success' => true,
    'tropas' => $tropas,
    'posiciones' => $posiciones,
    'total_tropas' => array_sum(array_column($tropas, 'cantidad'))
]);
?>