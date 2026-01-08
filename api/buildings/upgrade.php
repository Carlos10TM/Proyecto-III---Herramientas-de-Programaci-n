<?php
session_start();
require_once '../../config/connection.php';

// Verificar que el usuario este logueado
if (!isset($_SESSION['jugador_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

// Obtener datos JSON
$data = json_decode(file_get_contents('php://input'), true);
$edificio_jugador_id = $data['edificio_id'] ?? null;
$jugador_id = $_SESSION['jugador_id'];

if (!$edificio_jugador_id) {
    echo json_encode(['success' => false, 'error' => 'ID de edificio no proporcionado']);
    exit();
}

// Obtener datos del edificio del jugador
$query = "SELECT ej.*, ec.nivel_max
          FROM edificios_jugador ej
          JOIN edificios_catalogo ec ON ej.edificio_catalogo_id = ec.id
          WHERE ej.id = ? AND ej.jugador_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $edificio_jugador_id, $jugador_id);
$stmt->execute();
$edificio = $stmt->get_result()->fetch_assoc();

if (!$edificio) {
    echo json_encode(['success' => false, 'error' => 'Edificio no encontrado']);
    exit();
}

// Verificar que no este en construccion o mejora
if ($edificio['en_construccion'] || $edificio['en_mejora']) {
    echo json_encode(['success' => false, 'error' => 'El edificio ya está en proceso']);
    exit();
}

// Verificar que no este en nivel máximo
if ($edificio['nivel'] >= $edificio['nivel_max']) {
    echo json_encode(['success' => false, 'error' => 'El edificio ya está en nivel máximo']);
    exit();
}

// Obtener costos del siguiente nivel
$siguiente_nivel = $edificio['nivel'] + 1;
$query = "SELECT * FROM edificios_niveles 
          WHERE edificio_catalogo_id = ? AND nivel = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $edificio['edificio_catalogo_id'], $siguiente_nivel);
$stmt->execute();
$nivel_data = $stmt->get_result()->fetch_assoc();

if (!$nivel_data) {
    echo json_encode(['success' => false, 'error' => 'Nivel no encontrado']);
    exit();
}

// Verificar recursos del jugador
$query = "SELECT * FROM recursos_jugador WHERE jugador_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$recursos = $stmt->get_result()->fetch_assoc();

// Validar que tenga recursos suficientes
if ($recursos['madera'] < $nivel_data['costo_madera'] ||
    $recursos['piedra'] < $nivel_data['costo_piedra'] ||
    $recursos['comida'] < $nivel_data['costo_comida']) {
    echo json_encode(['success' => false, 'error' => 'Recursos insuficientes']);
    exit();
}

// Iniciar transaccion
$conn->begin_transaction();

try {
    // Descontar recursos
    $query = "UPDATE recursos_jugador 
              SET madera = madera - ?, 
                  piedra = piedra - ?, 
                  comida = comida - ?
              WHERE jugador_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiii", 
        $nivel_data['costo_madera'], 
        $nivel_data['costo_piedra'], 
        $nivel_data['costo_comida'], 
        $jugador_id
    );
    $stmt->execute();
    
    // Calcular tiempo de la mejora
    $tiempo_finalizacion = date('Y-m-d H:i:s', time() + $nivel_data['tiempo_construccion']);
    
    // Marcar edificio en mejora
    $query = "UPDATE edificios_jugador 
              SET en_mejora = 1, 
                  tiempo_finalizacion = ?
              WHERE id = ? AND jugador_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sii", $tiempo_finalizacion, $edificio_jugador_id, $jugador_id);
    $stmt->execute();
    
    // Confirmar la transaccion
    $conn->commit();
    
    // Obtener recursos actualizados
    $query = "SELECT oro, madera, piedra, comida FROM recursos_jugador WHERE jugador_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $jugador_id);
    $stmt->execute();
    $recursos_actualizados = $stmt->get_result()->fetch_assoc();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Edificio mejorando',
        'recursos' => $recursos_actualizados,
        'tiempo_construccion' => $nivel_data['tiempo_construccion']
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Error al mejorar: ' . $e->getMessage()]);
}
?>