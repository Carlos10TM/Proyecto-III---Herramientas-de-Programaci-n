<?php
session_start();
require_once '../../config/connection.php';

// Verificar aque el usuario este logueado
if (!isset($_SESSION['jugador_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$edificio_id = $data['edificio_id'] ?? null;
$jugador_id = $_SESSION['jugador_id'];

if (!$edificio_id) {
    echo json_encode(['success' => false, 'error' => 'ID de edificio no proporcionado']);
    exit();
}

// Obtener datos del edificio
$query = "SELECT ej.*, ec.nombre, en.costo_madera, en.costo_piedra, en.costo_comida, 
          en.tiempo_construccion, en.vida_base
          FROM edificios_jugador ej
          JOIN edificios_catalogo ec ON ej.edificio_catalogo_id = ec.id
          JOIN edificios_niveles en ON (ec.id = en.edificio_catalogo_id AND en.nivel = ej.nivel)
          WHERE ej.id = ? AND ej.jugador_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $edificio_id, $jugador_id);
$stmt->execute();
$edificio = $stmt->get_result()->fetch_assoc();

if (!$edificio) {
    echo json_encode(['success' => false, 'error' => 'Edificio no encontrado']);
    exit();
}

// Verificar que el edificio este destruido
if ($edificio['esta_destruido'] == 0) {
    echo json_encode(['success' => false, 'error' => 'El edificio no está destruido']);
    exit();
}

// Calcular costos de reparación (50% del costo original)
$costo_madera = ceil($edificio['costo_madera'] * 0.5);
$costo_piedra = ceil($edificio['costo_piedra'] * 0.5);
$costo_comida = ceil($edificio['costo_comida'] * 0.5);
$tiempo_reparacion = ceil($edificio['tiempo_construccion'] * 0.25); // 25% del tiempo

// Verificar recursos del jugador
$query = "SELECT * FROM recursos_jugador WHERE jugador_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$recursos = $stmt->get_result()->fetch_assoc();

// Validar recursos suficientes
if ($recursos['madera'] < $costo_madera ||
    $recursos['piedra'] < $costo_piedra ||
    $recursos['comida'] < $costo_comida) {
    echo json_encode([
        'success' => false, 
        'error' => 'Recursos insuficientes',
        'costo' => [
            'madera' => $costo_madera,
            'piedra' => $costo_piedra,
            'comida' => $costo_comida
        ]
    ]);
    exit();
}

// Iniciar transaccion de reparacion
$conn->begin_transaction();

try {
    // Descontar recursos
    $query = "UPDATE recursos_jugador 
              SET madera = madera - ?, 
                  piedra = piedra - ?, 
                  comida = comida - ?
              WHERE jugador_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiii", $costo_madera, $costo_piedra, $costo_comida, $jugador_id);
    $stmt->execute();
    
    // Calcular tiempo de finalizacion
    $tiempo_finalizacion = date('Y-m-d H:i:s', time() + $tiempo_reparacion);
    
    // Marcar edificio en reparacion
    $query = "UPDATE edificios_jugador 
              SET en_construccion = 1,
                  tiempo_finalizacion = ?
              WHERE id = ? AND jugador_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sii", $tiempo_finalizacion, $edificio_id, $jugador_id);
    $stmt->execute();
    
    $conn->commit();
    
    // Obtener recursos actualizados
    $query = "SELECT oro, madera, piedra, comida FROM recursos_jugador WHERE jugador_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $jugador_id);
    $stmt->execute();
    $recursos_actualizados = $stmt->get_result()->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'mensaje' => 'Reparación iniciada',
        'recursos' => $recursos_actualizados,
        'tiempo_reparacion' => $tiempo_reparacion
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Error al reparar: ' . $e->getMessage()]);
}
?>