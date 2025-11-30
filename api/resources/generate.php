<?php
session_start();
require_once '../../config/connection.php';

if (!isset($_SESSION['jugador_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$jugador_id = $_SESSION['jugador_id'];

// Obtener edificios que generan recursos y que no estan en construccion o mejora
$query = "SELECT ej.*, ec.tipo, en.generacion_por_minuto
          FROM edificios_jugador ej
          JOIN edificios_catalogo ec ON ej.edificio_catalogo_id = ec.id
          JOIN edificios_niveles en ON (ec.id = en.edificio_catalogo_id AND en.nivel = ej.nivel)
          WHERE ej.jugador_id = ? 
          AND ej.en_construccion = 0 
          AND ej.en_mejora = 0
          AND en.generacion_por_minuto > 0";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$result = $stmt->get_result();

$recursos_generados = [
    'madera' => 0,
    'piedra' => 0,
    'comida' => 0,
    'oro' => 0
];

// Calcular recursos generados por cada edificio
while ($edificio = $result->fetch_assoc()) {
    $generacion = $edificio['generacion_por_minuto'];
    
    switch($edificio['tipo']) {
        case 'aserradero':
            $recursos_generados['madera'] += $generacion;
            break;
        case 'cantera':
            $recursos_generados['piedra'] += $generacion;
            break;
        case 'granja':
            $recursos_generados['comida'] += $generacion;
            break;
        case 'mina_oro':
            $recursos_generados['oro'] += $generacion;
            break;
    }
}

// Actualizar recursos del jugador
if ($recursos_generados['madera'] > 0 || $recursos_generados['piedra'] > 0 || 
    $recursos_generados['comida'] > 0 || $recursos_generados['oro'] > 0) {
    
    $query = "UPDATE recursos_jugador 
              SET madera = madera + ?, 
                  piedra = piedra + ?, 
                  comida = comida + ?,
                  oro = oro + ?
              WHERE jugador_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiiii", 
        $recursos_generados['madera'],
        $recursos_generados['piedra'],
        $recursos_generados['comida'],
        $recursos_generados['oro'],
        $jugador_id
    );
    $stmt->execute();
}

// Obtener recursos actualizados
$query = "SELECT oro, madera, piedra, comida FROM recursos_jugador WHERE jugador_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$recursos_actuales = $stmt->get_result()->fetch_assoc();

echo json_encode([
    'success' => true,
    'generados' => $recursos_generados,
    'recursos_actuales' => $recursos_actuales
]);
?>