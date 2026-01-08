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

// Si no hay enemigos, no hacer nada
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
$GRID_SIZE = 9; // Grid de 9x9
$CENTRO = 40; // Posicion central del grid (fila 4, columna 4)

// Funcion para convertir posicion lineal a coordenadas
function posicionACoordenadas($posicion, $grid_size) {
    $fila = floor($posicion / $grid_size);
    $columna = $posicion % $grid_size;
    return ['fila' => $fila, 'columna' => $columna];
}

// Funcion para convertir coordenadas a posicion lineal
function coordenadasAPosicion($fila, $columna, $grid_size) {
    return $fila * $grid_size + $columna;
}

// Funcion para calcular la siguiente posicion hacia el centro
function calcularSiguientePosicion($posicion_actual, $centro, $grid_size, $posiciones_ocupadas) {
    // Si el enemigo esta fuera del grid (posicion negativa), moverlo al borde
    if ($posicion_actual < 0) {
        // Elegir un borde aleatorio para entrar
        $lados = ['arriba', 'abajo', 'izquierda', 'derecha'];
        $lado = $lados[array_rand($lados)];
        
        switch($lado) {
            case 'arriba':
                return rand(0, $grid_size - 1); // Primera fila
            case 'abajo':
                return rand(($grid_size - 1) * $grid_size, $grid_size * $grid_size - 1); // Ultima fila
            case 'izquierda':
                return rand(0, $grid_size - 1) * $grid_size; // Primera columna
            case 'derecha':
                return rand(0, $grid_size - 1) * $grid_size + ($grid_size - 1); // Ultima columna
        }
    }
    
    // Si ya llego al centro, quedarse ahi
    if ($posicion_actual == $centro) {
        return $centro;
    }
    
    // Obtener coordenadas actuales y del centro
    $actual = posicionACoordenadas($posicion_actual, $grid_size);
    $centro_coord = posicionACoordenadas($centro, $grid_size);
    
    // Calcular diferencias
    $diff_fila = $centro_coord['fila'] - $actual['fila'];
    $diff_columna = $centro_coord['columna'] - $actual['columna'];
    
    // Decidir direccion de movimiento (priorizar la mayor diferencia)
    $nueva_fila = $actual['fila'];
    $nueva_columna = $actual['columna'];
    
    if (abs($diff_fila) > abs($diff_columna)) {
        // Moverse verticalmente
        if ($diff_fila > 0) {
            $nueva_fila++; // Moverse hacia abajo
        } else if ($diff_fila < 0) {
            $nueva_fila--; // Moverse hacia arriba
        }
    } else if ($diff_columna != 0) {
        // Moverse horizontalmente
        if ($diff_columna > 0) {
            $nueva_columna++; // Moverse hacia la derecha
        } else {
            $nueva_columna--; // Moverse hacia la izquierda
        }
    }
    
    // Verificar que la nueva posicion este dentro del grid
    $nueva_fila = max(0, min($grid_size - 1, $nueva_fila));
    $nueva_columna = max(0, min($grid_size - 1, $nueva_columna));

    $nueva_posicion = coordenadasAPosicion($nueva_fila, $nueva_columna, $grid_size);

    // Verificar si la nueva posicion esta bloqueada por un edificio
    if (in_array($nueva_posicion, $posiciones_ocupadas)) {
        // Si esta bloqueada, el enemigo se queda rompiendo el edificio
        return $posicion_actual;
    }

    return $nueva_posicion;
}

// Mover cada enemigo
$enemigos_movidos = 0;
$conn->begin_transaction();

try {
    foreach ($enemigos as $enemigo) {
        $nueva_posicion = calcularSiguientePosicion(
            $enemigo['posicion'], 
            $CENTRO, 
            $GRID_SIZE,
            $posiciones_ocupadas
        );
        
        // Actualizar la posicion en la base de datos
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