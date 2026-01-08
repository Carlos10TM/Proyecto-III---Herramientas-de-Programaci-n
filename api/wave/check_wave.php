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

// Obtener el estado actual de oleadas del jugador
$query = "SELECT * FROM estado_oleadas WHERE jugador_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$estado = $stmt->get_result()->fetch_assoc();

// Si el jugador no tiene estado de oleadas, crear uno inicial
if (!$estado) {
    $query = "INSERT INTO estado_oleadas (jugador_id, oleada_actual, proxima_oleada_tiempo) 
              VALUES (?, 1, DATE_ADD(NOW(), INTERVAL 5 MINUTE))";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $jugador_id);
    $stmt->execute();
    
    // Obtener el estado recien creado
    $query = "SELECT * FROM estado_oleadas WHERE jugador_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $jugador_id);
    $stmt->execute();
    $estado = $stmt->get_result()->fetch_assoc();
}

// Calcular segundos restantes hasta la proxima oleada
$segundos_restantes = 0;
$debe_generar_oleada = false;
$debe_mostrar_alerta = false;

if ($estado['proxima_oleada_tiempo']) {
    $query = "SELECT 
              TIMESTAMPDIFF(SECOND, NOW(), ?) as segundos_hasta_oleada,
              TIMESTAMPDIFF(SECOND, NOW(), DATE_SUB(?, INTERVAL 1 MINUTE)) as segundos_hasta_alerta";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $estado['proxima_oleada_tiempo'], $estado['proxima_oleada_tiempo']);
    $stmt->execute();
    $tiempos = $stmt->get_result()->fetch_assoc();
    
    $segundos_restantes = max(0, $tiempos['segundos_hasta_oleada']);
    
    // Si el tiempo llego a cero o menos, se genera la oleada
    if ($tiempos['segundos_hasta_oleada'] <= 0 && !$estado['oleada_en_curso']) {
        $debe_generar_oleada = true;
    }
    
    // Si falta menos de 60 segundos, mostrar alerta
    if ($tiempos['segundos_hasta_alerta'] <= 0 && !$estado['oleada_en_curso']) {
        $debe_mostrar_alerta = true;
    }
}

// Responder con el estado actual
echo json_encode([
    'success' => true,
    'oleada_actual' => $estado['oleada_actual'],
    'oleada_en_curso' => (bool)$estado['oleada_en_curso'],
    'segundos_restantes' => $segundos_restantes,
    'debe_generar_oleada' => $debe_generar_oleada,
    'debe_mostrar_alerta' => $debe_mostrar_alerta,
    'proxima_oleada_tiempo' => $estado['proxima_oleada_tiempo']
]);
?>