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
$posiciones = $data['posiciones'] ?? [];
$jugador_id = $_SESSION['jugador_id'];

if (empty($posiciones)) {
    echo json_encode(['success' => true, 'message' => 'No hay posiciones para guardar']);
    exit();
}

$conn->begin_transaction();

try {
    // Preparar statement para insertar/actualizar
    $query = "INSERT INTO posiciones_tropas (jugador_id, unidad_jugador_id, indice_tropa, posicion) 
              VALUES (?, ?, ?, ?) 
              ON DUPLICATE KEY UPDATE posicion = VALUES(posicion)";
    $stmt = $conn->prepare($query);
    
    foreach ($posiciones as $key => $posicion) {
        // El key es: "unidad_jugador_id_indice"
        list($unidad_id, $indice) = explode('_', $key);
        
        $stmt->bind_param("iiii", $jugador_id, $unidad_id, $indice, $posicion);
        $stmt->execute();
    }
    
    $conn->commit();
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>