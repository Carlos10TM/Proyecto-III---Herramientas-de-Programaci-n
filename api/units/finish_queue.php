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
$query = "SELECT * FROM cola_entrenamiento 
          WHERE jugador_id = ? 
          AND tiempo_finalizacion <= NOW()";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$result = $stmt->get_result();

$unidades_completadas = [];
while ($row = $result->fetch_assoc()) {
    $unidades_completadas[] = $row;
}

if (count($unidades_completadas) == 0) {
    echo json_encode(['success' => true, 'finalizadas' => 0]);
    exit();
}

$conn->begin_transaction();

try {
    foreach ($unidades_completadas as $unidad) {
        // Verificar si ya existe registro de esta unidad
        $query = "SELECT id, cantidad FROM unidades_jugador 
                  WHERE jugador_id = ? AND unidad_catalogo_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $jugador_id, $unidad['unidad_catalogo_id']);
        $stmt->execute();
        $existe = $stmt->get_result()->fetch_assoc();
        
        if ($existe) {
            // Sumar 1 a la cantidad
            $query = "UPDATE unidades_jugador 
                      SET cantidad = cantidad + 1
                      WHERE jugador_id = ? AND unidad_catalogo_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $jugador_id, $unidad['unidad_catalogo_id']);
            $stmt->execute();
        } else {
            // Crear nuevo registro con 1 unidad
            $query = "INSERT INTO unidades_jugador 
                      (jugador_id, unidad_catalogo_id, cantidad, en_entrenamiento) 
                      VALUES (?, ?, 1, 0)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $jugador_id, $unidad['unidad_catalogo_id']);
            $stmt->execute();
        }
        
        // Eliminar de la cola
        $query = "DELETE FROM cola_entrenamiento WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $unidad['id']);
        $stmt->execute();
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'finalizadas' => count($unidades_completadas)
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>