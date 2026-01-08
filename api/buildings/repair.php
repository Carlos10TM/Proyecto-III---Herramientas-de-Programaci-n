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
$edificio_id = $data['edificio_id'] ?? null;
$jugador_id = $_SESSION['jugador_id'];

if (!$edificio_id) {
    echo json_encode(['success' => false, 'error' => 'ID de edificio no proporcionado']);
    exit();
}

// Obtener datos del edificio
$query = "SELECT ej.*, ec.nombre, ec.tipo, en.costo_madera, en.costo_piedra, en.costo_comida
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

if (!$edificio['esta_destruido']) {
    echo json_encode(['success' => false, 'error' => 'El edificio no está destruido']);
    exit();
}

// Calcular costo de reparacion (50% del costo original)
$costo_madera = floor($edificio['costo_madera'] * 0.5);
$costo_piedra = floor($edificio['costo_piedra'] * 0.5);
$costo_comida = floor($edificio['costo_comida'] * 0.5);

// Verificar recursos del jugador
$query = "SELECT * FROM recursos_jugador WHERE jugador_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$recursos = $stmt->get_result()->fetch_assoc();

if ($recursos['madera'] < $costo_madera ||
    $recursos['piedra'] < $costo_piedra ||
    $recursos['comida'] < $costo_comida) {
    echo json_encode([
        'success' => false, 
        'error' => 'Recursos insuficientes',
        'requerido' => [
            'madera' => $costo_madera,
            'piedra' => $costo_piedra,
            'comida' => $costo_comida
        ],
        'actual' => [
            'madera' => $recursos['madera'],
            'piedra' => $recursos['piedra'],
            'comida' => $recursos['comida']
        ]
    ]);
    exit();
}

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
    
    // Reparar edificio (restaurar vida completa y marcar como no destruido)
    $query = "UPDATE edificios_jugador ej
              JOIN edificios_catalogo ec ON ej.edificio_catalogo_id = ec.id
              JOIN edificios_niveles en ON (ec.id = en.edificio_catalogo_id AND en.nivel = ej.nivel)
              SET ej.esta_destruido = 0,
                  ej.vida_actual = en.vida
              WHERE ej.id = ? AND ej.jugador_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $edificio_id, $jugador_id);
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
        'message' => 'Edificio reparado exitosamente',
        'recursos' => $recursos_actualizados,
        'edificio' => $edificio['nombre']
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Error al reparar: ' . $e->getMessage()]);
}
?>