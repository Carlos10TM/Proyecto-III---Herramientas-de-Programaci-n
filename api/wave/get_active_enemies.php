<?php
session_start();
require_once '../../config/connection.php';

// Verificar que el usuario este logueado
if (!isset($_SESSION['jugador_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$jugador_id = $_SESSION['jugador_id'];

// Obtener todos los enemigos activos que no esten muertos
$query = "SELECT ea.*, ec.nombre, ec.tipo, ec.vida as vida_maxima, ec.ataque, ec.descripcion,
          eo.oleada_numero
          FROM enemigos_activos ea
          JOIN enemigos_catalogo ec ON ea.enemigo_catalogo_id = ec.id
          JOIN oleadas eo ON ea.oleada_id = eo.id
          WHERE ea.jugador_id = ? 
          AND ea.esta_muerto = 0
          ORDER BY ea.id ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$result = $stmt->get_result();

$enemigos = [];
while ($row = $result->fetch_assoc()) {
    $enemigos[] = [
        'id' => $row['id'],
        'nombre' => $row['nombre'],
        'tipo' => $row['tipo'],
        'vida_actual' => $row['vida_actual'],
        'vida_maxima' => $row['vida_maxima'],
        'ataque' => $row['ataque'],
        'posicion' => $row['posicion'],
        'oleada_numero' => $row['oleada_numero'],
        'descripcion' => $row['descripcion']
    ];
}

// Obtener el estado de la oleada actual
$query = "SELECT oleada_en_curso FROM estado_oleadas WHERE jugador_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$estado = $stmt->get_result()->fetch_assoc();

echo json_encode([
    'success' => true,
    'enemigos' => $enemigos,
    'oleada_en_curso' => (bool)$estado['oleada_en_curso'],
    'total_enemigos' => count($enemigos)
]);
?>