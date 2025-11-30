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

// Obtener el nivel del ayuntamiento del jugador
$query = "SELECT nivel_ayuntamiento FROM jugadores WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$result = $stmt->get_result();
$jugador = $result->fetch_assoc();
$nivel_ayuntamiento = $jugador['nivel_ayuntamiento'];

// Obtener edificios disponibles segun el nivel del ayuntamiento
$query = "SELECT ec.*, 
          (SELECT COUNT(*) FROM edificios_jugador 
           WHERE jugador_id = ? AND edificio_catalogo_id = ec.id) as construidos
          FROM edificios_catalogo ec
          WHERE ec.nivel_ayuntamiento_requerido <= ?
          ORDER BY ec.nivel_ayuntamiento_requerido, ec.nombre";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $jugador_id, $nivel_ayuntamiento);
$stmt->execute();
$result = $stmt->get_result();

$edificios = [];
while ($row = $result->fetch_assoc()) {
    // Verificar si se puede construir mas de este edificio
    $puede_construir = ($row['construidos'] < $row['limite_construccion']);
    
    // Si ya alcanzo el limite, no se muestra en disponibles
    if (!$puede_construir) {
        continue;
    }
    
    // Obtener costos del nivel 1
    $query_nivel = "SELECT * FROM edificios_niveles WHERE edificio_catalogo_id = ? AND nivel = 1";
    $stmt_nivel = $conn->prepare($query_nivel);
    $stmt_nivel->bind_param("i", $row['id']);
    $stmt_nivel->execute();
    $nivel_data = $stmt_nivel->get_result()->fetch_assoc();
    
    $edificios[] = [
        'id' => $row['id'],
        'nombre' => $row['nombre'],
        'tipo' => $row['tipo'],
        'descripcion' => $row['descripcion'],
        'construidos' => $row['construidos'],
        'limite' => $row['limite_construccion'],
        'puede_construir' => $puede_construir,
        'costos' => [
            'madera' => $nivel_data['costo_madera'],
            'piedra' => $nivel_data['costo_piedra'],
            'comida' => $nivel_data['costo_comida']
        ],
        'tiempo' => $nivel_data['tiempo_construccion'],
        'generacion' => $nivel_data['generacion_por_minuto']
    ];
}

echo json_encode($edificios);
?>