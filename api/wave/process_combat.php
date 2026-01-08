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

// Verificar si hay una oleada en curso
$query = "SELECT oleada_en_curso FROM estado_oleadas WHERE jugador_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$estado = $stmt->get_result()->fetch_assoc();

if (!$estado || !$estado['oleada_en_curso']) {
    echo json_encode([
        'success' => true,
        'mensaje' => 'No hay oleada en curso',
        'acciones' => []
    ]);
    exit();
}

// Iniciar transaccion para mantener consistencia
$conn->begin_transaction();

try {
    $acciones = []; // Array para registrar todas las acciones de combate
    
    // ==================================
    // FASE 1: ENEMIGOS ATACAN EDIFICIOS
    // ==================================
    
    // Obtener enemigos vivos y sus posiciones
    $query = "SELECT ea.*, ec.ataque, ec.nombre as enemigo_nombre
              FROM enemigos_activos ea
              JOIN enemigos_catalogo ec ON ea.enemigo_catalogo_id = ec.id
              WHERE ea.jugador_id = ? 
              AND ea.esta_muerto = 0
              AND ea.posicion >= 0";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $jugador_id);
    $stmt->execute();
    $enemigos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Obtener edificios no destruidos
    $query = "SELECT ej.*, ec.nombre as edificio_nombre
              FROM edificios_jugador ej
              JOIN edificios_catalogo ec ON ej.edificio_catalogo_id = ec.id
              WHERE ej.jugador_id = ? 
              AND ej.esta_destruido = 0
              AND ej.posicion_x IS NOT NULL";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $jugador_id);
    $stmt->execute();
    $edificios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Crear un mapa de posicion -> edificio para busqueda rapida
    $edificios_por_posicion = [];
    foreach ($edificios as $edificio) {
        $edificios_por_posicion[$edificio['posicion_x']] = $edificio;
    }
    
    // Para cada enemigo verificar si esta adyacente a un edificio y atacarlo
    foreach ($enemigos as $enemigo) {
        $posicion_enemigo = $enemigo['posicion'];
        
        // Calcular posiciones adyacentes (arriba, abajo, izquierda, derecha)
        $posiciones_adyacentes = [];
        $fila = floor($posicion_enemigo / 9);
        $columna = $posicion_enemigo % 9;
        
        // Arriba
        if ($fila > 0) {
            $posiciones_adyacentes[] = ($fila - 1) * 9 + $columna;
        }
        // Abajo
        if ($fila < 8) {
            $posiciones_adyacentes[] = ($fila + 1) * 9 + $columna;
        }
        // Izquierda
        if ($columna > 0) {
            $posiciones_adyacentes[] = $fila * 9 + ($columna - 1);
        }
        // Derecha
        if ($columna < 8) {
            $posiciones_adyacentes[] = $fila * 9 + ($columna + 1);
        }
        
        // Verificar si hay un edificio en alguna posicion adyacente
        foreach ($posiciones_adyacentes as $pos_adyacente) {
            if (isset($edificios_por_posicion[$pos_adyacente])) {
                $edificio_atacado = $edificios_por_posicion[$pos_adyacente];
                
                // El enemigo ataca al edificio
                $daño = $enemigo['ataque'];
                $nueva_vida = max(0, $edificio_atacado['vida_actual'] - $daño);
                
                // Actualizar vida del edificio
                $query = "UPDATE edificios_jugador 
                          SET vida_actual = ? 
                          WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ii", $nueva_vida, $edificio_atacado['id']);
                $stmt->execute();
                
                // Registrar accion
                $acciones[] = [
                    'tipo' => 'enemigo_ataca_edificio',
                    'enemigo' => $enemigo['enemigo_nombre'],
                    'edificio' => $edificio_atacado['edificio_nombre'],
                    'daño' => $daño,
                    'vida_restante' => $nueva_vida
                ];
                
                // Si el edificio fue destruido, marcarlo
                if ($nueva_vida <= 0) {
                    $query = "UPDATE edificios_jugador 
                              SET esta_destruido = 1, vida_actual = 0 
                              WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("i", $edificio_atacado['id']);
                    $stmt->execute();
                    
                    $acciones[] = [
                        'tipo' => 'edificio_destruido',
                        'edificio' => $edificio_atacado['edificio_nombre']
                    ];
                    
                    // Remover del mapa para que no sea atacado de nuevo este turno
                    unset($edificios_por_posicion[$pos_adyacente]);
                }
                
                // Un enemigo solo ataca un edificio por turno
                break;
            }
        }
    }
    
    // =======================================================
    // FASE 2: TROPAS ATACAN ENEMIGOS Y ENEMIGOS CONTRAATACAN
    // =======================================================

    // Obtener tropas del jugador con su vida actual
    $query = "SELECT uj.*, uc.ataque, uc.nombre as tropa_nombre, uc.vida as vida_maxima
            FROM unidades_jugador uj
            JOIN unidades_catalogo uc ON uj.unidad_catalogo_id = uc.id
            WHERE uj.jugador_id = ? 
            AND uj.cantidad > 0";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $jugador_id);
    $stmt->execute();
    $tropas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Las tropas atacan a los enemigos
    foreach ($tropas as $tropa) {
        $cantidad_tropas = $tropa['cantidad'];
        $daño_por_tropa = $tropa['ataque'];
        
        // Distribuir el daño de todas las tropas de este tipo entre los enemigos vivos
        for ($i = 0; $i < $cantidad_tropas; $i++) {
            // Buscar un enemigo vivo para atacar
            foreach ($enemigos as $key => $enemigo) {
                if ($enemigo['vida_actual'] > 0) {
                    $daño = $daño_por_tropa;
                    $nueva_vida = max(0, $enemigo['vida_actual'] - $daño);
                    
                    // Actualizar vida del enemigo
                    $query = "UPDATE enemigos_activos 
                            SET vida_actual = ? 
                            WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ii", $nueva_vida, $enemigo['id']);
                    $stmt->execute();
                    
                    // Actualizar en el array local
                    $enemigos[$key]['vida_actual'] = $nueva_vida;
                    
                    $acciones[] = [
                        'tipo' => 'tropa_ataca_enemigo',
                        'tropa' => $tropa['tropa_nombre'],
                        'enemigo' => $enemigo['enemigo_nombre'],
                        'daño' => $daño,
                        'vida_restante' => $nueva_vida
                    ];
                    
                    // Si el enemigo murio, marcarlo
                    if ($nueva_vida <= 0) {
                        $query = "UPDATE enemigos_activos 
                                SET esta_muerto = 1, vida_actual = 0 
                                WHERE id = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("i", $enemigo['id']);
                        $stmt->execute();
                        
                        $acciones[] = [
                            'tipo' => 'enemigo_muerto',
                            'enemigo' => $enemigo['enemigo_nombre']
                        ];
                    } else {
                        // Enemigo contraataca si sigue vivo
                        $daño_contraataque = $enemigo['ataque'];
                        $vida_actual_tropa = $tropa['vida_actual'] ?? $tropa['vida_maxima'];
                        $nueva_vida_tropa = max(0, $vida_actual_tropa - $daño_contraataque);
                        
                        // Si la tropa muere, reducir cantidad
                        if ($nueva_vida_tropa <= 0) {
                            $query = "UPDATE unidades_jugador 
                                    SET cantidad = cantidad - 1,
                                        vida_actual = ?
                                    WHERE id = ? AND cantidad > 0";
                            $stmt = $conn->prepare($query);
                            $vida_maxima = $tropa['vida_maxima'];
                            $stmt->bind_param("ii", $vida_maxima, $tropa['id']);
                            $stmt->execute();
                            
                            $acciones[] = [
                                'tipo' => 'tropa_muerta',
                                'tropa' => $tropa['tropa_nombre']
                            ];
                        } else {
                            // Actualizar vida de la tropa
                            $query = "UPDATE unidades_jugador 
                                    SET vida_actual = ? 
                                    WHERE id = ?";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param("ii", $nueva_vida_tropa, $tropa['id']);
                            $stmt->execute();
                        }
                    }
                    
                    // Una tropa ataca un solo enemigo por iteracion
                    break;
                }
            }
        }
    }
    
    // ================================
    // FASE 3: VERIFICAR FIN DE OLEADA
    // ================================
    
    // Contar enemigos vivos restantes
    $query = "SELECT COUNT(*) as vivos FROM enemigos_activos 
              WHERE jugador_id = ? AND esta_muerto = 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $jugador_id);
    $stmt->execute();
    $conteo = $stmt->get_result()->fetch_assoc();
    
    $oleada_completada = false;
    
    if ($conteo['vivos'] == 0) {
        $oleada_completada = true;
        
        // Calcular recompensa de oro
        $query = "SELECT SUM(ec.recompensa_oro) as oro_total
                  FROM enemigos_activos ea
                  JOIN enemigos_catalogo ec ON ea.enemigo_catalogo_id = ec.id
                  WHERE ea.jugador_id = ?
                  AND ea.esta_muerto = 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $jugador_id);
        $stmt->execute();
        $recompensa = $stmt->get_result()->fetch_assoc();
        $oro_ganado = $recompensa['oro_total'] ?? 0;
        
        // Otorgar el oro al jugador
        if ($oro_ganado > 0) {
            $query = "UPDATE recursos_jugador 
                      SET oro = oro + ? 
                      WHERE jugador_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $oro_ganado, $jugador_id);
            $stmt->execute();
        }
        
        // Actualizar registro de la oleada
        $query = "UPDATE oleadas 
                  SET completada = 1, 
                      oro_ganado = ?,
                      fecha_finalizacion = NOW()
                  WHERE jugador_id = ? 
                  AND completada = 0
                  ORDER BY fecha_inicio DESC
                  LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $oro_ganado, $jugador_id);
        $stmt->execute();
        
        // Limpiar enemigos muertos
        $query = "DELETE FROM enemigos_activos 
                  WHERE jugador_id = ? AND esta_muerto = 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $jugador_id);
        $stmt->execute();
        
        // Actualizar estado a no hay oleada en curso y programar la siguiente
        $query = "UPDATE estado_oleadas 
                  SET oleada_en_curso = 0,
                      oleada_actual = oleada_actual + 1,
                      proxima_oleada_tiempo = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                  WHERE jugador_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $jugador_id);
        $stmt->execute();
        
        $acciones[] = [
            'tipo' => 'oleada_completada',
            'oro_ganado' => $oro_ganado,
            'proxima_oleada_minutos' => 15
        ];
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'acciones' => $acciones,
        'oleada_completada' => $oleada_completada,
        'enemigos_vivos' => $conteo['vivos']
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Error en combate: ' . $e->getMessage()]);
}
?>