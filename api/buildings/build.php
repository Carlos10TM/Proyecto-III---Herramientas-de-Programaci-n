<?php
session_start();
require_once '../../config/connection.php';

// Verificar sesion
if (!isset($_SESSION['jugador_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

// Obtener datos JSON
$data = json_decode(file_get_contents('php://input'), true);
$edificio_id = $data['edificio_id'] ?? null;
$jugador_id = $_SESSION['jugador_id'];

if (!$edificio_id) {
    echo json_encode(['success' => false, 'error' => 'ID de edificio no proporcionado']);
    exit();
}

// Obtener datos del edificio
$query = "SELECT ec.*, en.costo_madera, en.costo_piedra, en.costo_comida, en.tiempo_construccion
          FROM edificios_catalogo ec
          JOIN edificios_niveles en ON (ec.id = en.edificio_catalogo_id AND en.nivel = 1)
          WHERE ec.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $edificio_id);
$stmt->execute();
$edificio = $stmt->get_result()->fetch_assoc();

if (!$edificio) {
    echo json_encode(['success' => false, 'error' => 'Edificio no encontrado']);
    exit();
}

// Verificar recursos del jugador
$query = "SELECT * FROM recursos_jugador WHERE jugador_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$recursos = $stmt->get_result()->fetch_assoc();

// Validar que tenga recursos suficientes
if ($recursos['madera'] < $edificio['costo_madera'] ||
    $recursos['piedra'] < $edificio['costo_piedra'] ||
    $recursos['comida'] < $edificio['costo_comida']) {
    echo json_encode(['success' => false, 'error' => 'Recursos insuficientes']);
    exit();
}

// Verificar limite de construccion
$query = "SELECT COUNT(*) as total FROM edificios_jugador 
          WHERE jugador_id = ? AND edificio_catalogo_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $jugador_id, $edificio_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if ($result['total'] >= $edificio['limite_construccion']) {
    echo json_encode(['success' => false, 'error' => 'Límite de construcción alcanzado']);
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
        $edificio['costo_madera'], 
        $edificio['costo_piedra'], 
        $edificio['costo_comida'], 
        $jugador_id
    );
    $stmt->execute();
    
    // Calcular tiempo de finalizacion
    $tiempo_finalizacion = date('Y-m-d H:i:s', time() + $edificio['tiempo_construccion']);
    
    // Crear el edificio
    $query = "INSERT INTO edificios_jugador 
              (jugador_id, edificio_catalogo_id, nivel, en_construccion, tiempo_finalizacion) 
              VALUES (?, ?, 1, 1, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iis", $jugador_id, $edificio_id, $tiempo_finalizacion);
    $stmt->execute();
    
    // Confirmar transaccion
    $conn->commit();
    
    // Obtener recursos actualizados
    $query = "SELECT oro, madera, piedra, comida FROM recursos_jugador WHERE jugador_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $jugador_id);
    $stmt->execute();
    $recursos_actualizados = $stmt->get_result()->fetch_assoc();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Edificio en construcción',
        'recursos' => $recursos_actualizados,
        'tiempo_construccion' => $edificio['tiempo_construccion']
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Error al construir: ' . $e->getMessage()]);
}
?>