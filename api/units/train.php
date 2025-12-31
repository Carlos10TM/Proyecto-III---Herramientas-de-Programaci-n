<?php
session_start();
require_once '../../config/connection.php';

if (!isset($_SESSION['jugador_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$unidad_id = $data['unidad_id'] ?? null;
$cantidad = $data['cantidad'] ?? 1;
$jugador_id = $_SESSION['jugador_id'];

if (!$unidad_id || $cantidad < 1) {
    echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    exit();
}

// Obtener datos de la unidad
$query = "SELECT * FROM unidades_catalogo WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $unidad_id);
$stmt->execute();
$unidad = $stmt->get_result()->fetch_assoc();

if (!$unidad) {
    echo json_encode(['success' => false, 'error' => 'Unidad no encontrada']);
    exit();
}

// Verificar que tenga el cuartel adecuado
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

if (!$cuartel || $cuartel['nivel'] < $unidad['nivel_cuartel_requerido']) {
    echo json_encode(['success' => false, 'error' => 'Cuartel insuficiente']);
    exit();
}

// Calcular costos totales
$costo_oro_total = $unidad['costo_oro'] * $cantidad;
$costo_comida_total = $unidad['costo_comida'] * $cantidad;

// Verificar recursos
$query = "SELECT oro, comida FROM recursos_jugador WHERE jugador_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$recursos = $stmt->get_result()->fetch_assoc();

if ($recursos['oro'] < $costo_oro_total || $recursos['comida'] < $costo_comida_total) {
    echo json_encode(['success' => false, 'error' => 'Recursos insuficientes']);
    exit();
}

// Verificar limite de tropas (contando las que estan en cola)
$query = "SELECT j.limite_tropas,
          COALESCE((SELECT SUM(cantidad) FROM unidades_jugador WHERE jugador_id = ?), 0) as tropas_actuales,
          COALESCE((SELECT COUNT(*) FROM cola_entrenamiento WHERE jugador_id = ?), 0) as tropas_en_cola
          FROM jugadores j
          WHERE j.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $jugador_id, $jugador_id, $jugador_id);
$stmt->execute();
$limite_data = $stmt->get_result()->fetch_assoc();

$tropas_totales = $limite_data['tropas_actuales'] + $limite_data['tropas_en_cola'];
$limite_tropas = $limite_data['limite_tropas'];

if (($tropas_totales + $cantidad) > $limite_tropas) {
    $espacio_disponible = $limite_tropas - $tropas_totales;
    echo json_encode([
        'success' => false, 
        'error' => "Límite de tropas alcanzado. Espacio disponible: $espacio_disponible/$limite_tropas"
    ]);
    exit();
}

// Iniciar transaccion
$conn->begin_transaction();

try {
    // Descontar recursos
    $query = "UPDATE recursos_jugador 
              SET oro = oro - ?, 
                  comida = comida - ?
              WHERE jugador_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $costo_oro_total, $costo_comida_total, $jugador_id);
    $stmt->execute();
    
    // Obtener el tiempo de finalizacion de la ultima unidad en cola
    $query = "SELECT MAX(tiempo_finalizacion) as ultimo_tiempo 
              FROM cola_entrenamiento 
              WHERE jugador_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $jugador_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    // Si hay cola, agregar despues de la ultima, si no, empezar altiro
    $tiempo_inicio = $result['ultimo_tiempo'] ? strtotime($result['ultimo_tiempo']) : time();
    
    // Agregar cada unidad individualmente a la cola
    $query = "INSERT INTO cola_entrenamiento (jugador_id, unidad_catalogo_id, tiempo_finalizacion) 
              VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    
    for ($i = 0; $i < $cantidad; $i++) {
        $tiempo_finalizacion = date('Y-m-d H:i:s', $tiempo_inicio + $unidad['tiempo_entrenamiento']);
        $stmt->bind_param("iis", $jugador_id, $unidad_id, $tiempo_finalizacion);
        $stmt->execute();
        $tiempo_inicio += $unidad['tiempo_entrenamiento']; // Siguiente unidad empieza cuando termina esta
    }
    
    $conn->commit();
    
    // Obtener recursos actualizados
    $query = "SELECT oro, madera, piedra, comida FROM recursos_jugador WHERE jugador_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $jugador_id);
    $stmt->execute();
    $recursos_actualizados = $stmt->get_result()->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'message' => 'Entrenamiento iniciado',
        'recursos' => $recursos_actualizados,
        'tiempo_entrenamiento' => $unidad['tiempo_entrenamiento']
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Error al entrenar: ' . $e->getMessage()]);
}
?>