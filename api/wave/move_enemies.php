<?php
session_start();
require_once '../../config/connection.php';

// Verificar que el usuario este logueado
if (!isset($_SESSION['jugador_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$jugador_id = $_SESSION['jugador_id'];

// Recibir posiciones de tropas desde el cliente
$data = json_decode(file_get_contents('php://input'), true);
$posiciones_tropas = $data['posiciones_tropas'] ?? [];

// Obtener todos los enemigos vivos del jugador
$query = "SELECT * FROM enemigos_activos 
          WHERE jugador_id = ? 
          AND esta_muerto = 0
          ORDER BY id ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$result = $stmt->get_result();

$enemigos = [];
while ($row = $result->fetch_assoc()) {
    $enemigos[] = $row;
}

if (count($enemigos) === 0) {
    echo json_encode([
        'success' => true,
        'enemigos_movidos' => 0,
        'mensaje' => 'No hay enemigos activos'
    ]);
    exit();
}

// Obtener todas las posiciones ocupadas por edificios no destruidos
$query = "SELECT posicion_x FROM edificios_jugador 
          WHERE jugador_id = ? 
          AND esta_destruido = 0
          AND posicion_x IS NOT NULL";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$result = $stmt->get_result();

$posiciones_ocupadas = [];
while ($row = $result->fetch_assoc()) {
    $posiciones_ocupadas[] = $row['posicion_x'];
}

// Constantes del grid
$GRID_SIZE = 9;
$CENTRO = 40;

// Funciones auxiliares
function posicionACoordenadas($posicion, $grid_size) {
    $fila = floor($posicion / $grid_size);
    $columna = $posicion % $grid_size;
    return ['fila' => $fila, 'columna' => $columna];
}

function coordenadasAPosicion($fila, $columna, $grid_size) {
    return $fila * $grid_size + $columna;
}

function calcularDistancia($pos1, $pos2, $grid_size) {
    $coord1 = posicionACoordenadas($pos1, $grid_size);
    $coord2 = posicionACoordenadas($pos2, $grid_size);
    return abs($coord1['fila'] - $coord2['fila']) + abs($coord1['columna'] - $coord2['columna']);
}

function calcularSiguientePosicion($posicion_actual, $posicion_objetivo, $grid_size, $posiciones_ocupadas) {
    // Si el enemigo esta fuera del grid
    if ($posicion_actual < 0) {
        $lados = ['arriba', 'abajo', 'izquierda', 'derecha'];
        $lado = $lados[array_rand($lados)];
        
        switch($lado) {
            case 'arriba':
                return rand(0, $grid_size - 1);
            case 'abajo':
                return rand(($grid_size - 1) * $grid_size, $grid_size * $grid_size - 1);
            case 'izquierda':
                return rand(0, $grid_size - 1) * $grid_size;
            case 'derecha':
                return rand(0, $grid_size - 1) * $grid_size + ($grid_size - 1);
        }
    }
    
    // Si ya llego al objetivo
    if ($posicion_actual == $posicion_objetivo) {
        return $posicion_actual;
    }
    
    $actual = posicionACoordenadas($posicion_actual, $grid_size);
    $objetivo = posicionACoordenadas($posicion_objetivo, $grid_size);
    
    $diff_fila = $objetivo['fila'] - $actual['fila'];
    $diff_columna = $objetivo['columna'] - $actual['columna'];
    
    $nueva_fila = $actual['fila'];
    $nueva_columna = $actual['columna'];
    
    // Priorizar la mayor diferencia
    if (abs($diff_fila) > abs($diff_columna)) {
        if ($diff_fila > 0) {
            $nueva_fila++;
        } else if ($diff_fila < 0) {
            $nueva_fila--;
        }
    } else if ($diff_columna != 0) {
        if ($diff_columna > 0) {
            $nueva_columna++;
        } else {
            $nueva_columna--;
        }
    }
    
    $nueva_fila = max(0, min($grid_size - 1, $nueva_fila));
    $nueva_columna = max(0, min($grid_size - 1, $nueva_columna));

    $nueva_posicion = coordenadasAPosicion($nueva_fila, $nueva_columna, $grid_size);

    // Si la nueva posicion está bloqueada por un edificio no moverse
    if (in_array($nueva_posicion, $posiciones_ocupadas)) {
        return $posicion_actual;
    }

    return $nueva_posicion;
}

// Mover cada enemigo
$enemigos_movidos = 0;
$conn->begin_transaction();

try {
    foreach ($enemigos as $enemigo) {
        $posicion_actual = $enemigo['posicion'];
        
        // Determinar objetivo segun si hay tropas o no
        $objetivo = $CENTRO; // Por defecto ir al centro
        
        if (!empty($posiciones_tropas)) {
            // Si hay tropas buscar la mas cercana
            $distancia_minima = PHP_INT_MAX;
            foreach ($posiciones_tropas as $pos_tropa) {
                $distancia = calcularDistancia($posicion_actual, $pos_tropa, $GRID_SIZE);
                if ($distancia < $distancia_minima) {
                    $distancia_minima = $distancia;
                    $objetivo = $pos_tropa;
                }
            }
        }
        
        $nueva_posicion = calcularSiguientePosicion(
            $posicion_actual,
            $objetivo,
            $GRID_SIZE,
            $posiciones_ocupadas
        );
        
        $query = "UPDATE enemigos_activos 
                  SET posicion = ? 
                  WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $nueva_posicion, $enemigo['id']);
        $stmt->execute();
        
        $enemigos_movidos++;
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'enemigos_movidos' => $enemigos_movidos,
        'mensaje' => "$enemigos_movidos enemigos se movieron"
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Error al mover enemigos: ' . $e->getMessage()]);
}
?>