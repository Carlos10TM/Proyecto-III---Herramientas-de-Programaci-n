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

// Funciones auxiliares para calcular distancia y posiciones adyacentes
function posicionACoordenadas($posicion) {
    return [
        'fila' => floor($posicion / 9),
        'columna' => $posicion % 9
    ];
}

function calcularDistancia($pos1, $pos2) {
    $coord1 = posicionACoordenadas($pos1);
    $coord2 = posicionACoordenadas($pos2);
    return abs($coord1['fila'] - $coord2['fila']) + abs($coord1['columna'] - $coord2['columna']);
}

function sonAdyacentes($pos1, $pos2) {
    return calcularDistancia($pos1, $pos2) === 1;
}

// Iniciar transaccion
$conn->begin_transaction();

try {
    $acciones = [];
    
    // ===================================
    // FASE 1: OBTENER TODOS LAS UNIDADES
    // ===================================
    
    // Obtener enemigos vivos con posicion en el grid
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
    
    // Obtener tropas del jugador con posiciones (simuladas en PHP)
    $query = "SELECT uj.*, uc.ataque, uc.nombre as tropa_nombre, uc.vida as vida_maxima
              FROM unidades_jugador uj
              JOIN unidades_catalogo uc ON uj.unidad_catalogo_id = uc.id
              WHERE uj.jugador_id = ? 
              AND uj.cantidad > 0";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $jugador_id);
    $stmt->execute();
    $tropas_info = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $tropas = [];
    foreach ($tropas_info as $tropa_info) {
        for ($i = 0; $i < $tropa_info['cantidad']; $i++) {
            $tropas[] = [
                'id' => $tropa_info['id'],
                'tropa_individual_id' => $tropa_info['id'] . '_' . $i,
                'nombre' => $tropa_info['tropa_nombre'],
                'ataque' => $tropa_info['ataque'],
                'vida_actual' => $tropa_info['vida_actual'] ?? $tropa_info['vida_maxima'],
                'vida_maxima' => $tropa_info['vida_maxima'],
                'posicion' => rand(0, 80) // Posicion aleatoria temporal
            ];
        }
    }
    
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
    
    // ==================================
    // FASE 2: COMBATE TROPAS VS ENEMIGOS
    // ==================================
    
    $tropas_muertas = [];
    
    // Si hay tropas, los enemigos priorizan atacarlas
    if (count($tropas) > 0) {
        // Cada enemigo busca una tropa adyacente para atacar
        foreach ($enemigos as $key_enemigo => $enemigo) {
            $objetivo_encontrado = false;
            
            // Buscar tropas adyacentes
            foreach ($tropas as $key_tropa => $tropa) {
                if (sonAdyacentes($enemigo['posicion'], $tropa['posicion'])) {
                    // Enemigo ataca tropa
                    $daño = $enemigo['ataque'];
                    $nueva_vida = max(0, $tropa['vida_actual'] - $daño);
                    
                    $tropas[$key_tropa]['vida_actual'] = $nueva_vida;
                    
                    $acciones[] = [
                        'tipo' => 'enemigo_ataca_tropa',
                        'enemigo' => $enemigo['enemigo_nombre'],
                        'tropa' => $tropa['nombre'],
                        'daño' => $daño,
                        'vida_restante' => $nueva_vida
                    ];
                    
                    // Si la tropa murio
                    if ($nueva_vida <= 0) {
                        $tropas_muertas[] = $tropa['id'];
                        unset($tropas[$key_tropa]);
                        
                        $acciones[] = [
                            'tipo' => 'tropa_muerta',
                            'tropa' => $tropa['nombre']
                        ];
                    } else {
                        // Actualizar vida en BD
                        $query = "UPDATE unidades_jugador 
                                  SET vida_actual = ? 
                                  WHERE id = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("ii", $nueva_vida, $tropa['id']);
                        $stmt->execute();
                    }
                    
                    $objetivo_encontrado = true;
                    break; // Un enemigo ataca solo una tropa por turno
                }
            }
        }
        
        // Reducir cantidad de tropas muertas
        foreach (array_count_values($tropas_muertas) as $tropa_id => $cantidad_muertas) {
            $query = "UPDATE unidades_jugador 
                      SET cantidad = GREATEST(0, cantidad - ?),
                          vida_actual = (SELECT vida FROM unidades_catalogo WHERE id = unidad_catalogo_id)
                      WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $cantidad_muertas, $tropa_id);
            $stmt->execute();
        }
        
        // Tropas contraatacan a enemigos adyacentes
        foreach ($tropas as $tropa) {
            foreach ($enemigos as $key_enemigo => $enemigo) {
                if (sonAdyacentes($tropa['posicion'], $enemigo['posicion'])) {
                    // Tropa ataca enemigo
                    $daño = $tropa['ataque'];
                    $nueva_vida = max(0, $enemigo['vida_actual'] - $daño);
                    
                    // Actualizar vida del enemigo
                    $query = "UPDATE enemigos_activos 
                              SET vida_actual = ? 
                              WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ii", $nueva_vida, $enemigo['id']);
                    $stmt->execute();
                    
                    $enemigos[$key_enemigo]['vida_actual'] = $nueva_vida;
                    
                    $acciones[] = [
                        'tipo' => 'tropa_ataca_enemigo',
                        'tropa' => $tropa['nombre'],
                        'enemigo' => $enemigo['enemigo_nombre'],
                        'daño' => $daño,
                        'vida_restante' => $nueva_vida
                    ];
                    
                    // Si el enemigo murio
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
                        
                        unset($enemigos[$key_enemigo]);
                    }
                    
                    break; // Una tropa ataca solo un enemigo por turno
                }
            }
        }
    }
    
    // ===============================
    // FASE 3: TORRES ATACAN ENEMIGOS
    // ===============================

    // Obtener torres del jugador
    $query = "SELECT ej.*, ec.nombre as edificio_nombre, en.ataque_torre, en.rango_ataque
            FROM edificios_jugador ej
            JOIN edificios_catalogo ec ON ej.edificio_catalogo_id = ec.id
            JOIN edificios_niveles en ON (ec.id = en.edificio_catalogo_id AND en.nivel = ej.nivel)
            WHERE ej.jugador_id = ? 
            AND ec.tipo = 'torre'
            AND ej.esta_destruido = 0
            AND ej.posicion_x IS NOT NULL
            AND en.ataque_torre > 0";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $jugador_id);
    $stmt->execute();
    $torres = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Cada torre ataca al enemigo mas cercano dentro de su rango
    foreach ($torres as $torre) {
        $posicion_torre = $torre['posicion_x'];
        $rango = $torre['rango_ataque'];
        $ataque = $torre['ataque_torre'];
        
        $enemigo_mas_cercano = null;
        $distancia_minima = PHP_INT_MAX;
        
        // Buscar enemigo mas cercano dentro del rango
        foreach ($enemigos as $key_enemigo => $enemigo) {
            if ($enemigo['vida_actual'] > 0) {
                $distancia = calcularDistancia($posicion_torre, $enemigo['posicion']);
                
                if ($distancia <= $rango && $distancia < $distancia_minima) {
                    $distancia_minima = $distancia;
                    $enemigo_mas_cercano = $key_enemigo;
                }
            }
        }
        
        // Si encontro un enemigo lo ataca
        if ($enemigo_mas_cercano !== null) {
            $enemigo = $enemigos[$enemigo_mas_cercano];
            
            $nueva_vida = max(0, $enemigo['vida_actual'] - $ataque);
            
            // Actualizar vida del enemigo
            $query = "UPDATE enemigos_activos 
                    SET vida_actual = ? 
                    WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $nueva_vida, $enemigo['id']);
            $stmt->execute();
            
            $enemigos[$enemigo_mas_cercano]['vida_actual'] = $nueva_vida;
            
            $acciones[] = [
                'tipo' => 'torre_ataca_enemigo',
                'torre' => $torre['edificio_nombre'],
                'enemigo' => $enemigo['enemigo_nombre'],
                'daño' => $ataque,
                'vida_restante' => $nueva_vida
            ];
            
            // Si el enemigo murio
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
                
                unset($enemigos[$enemigo_mas_cercano]);
            }
        }
    }

    // ==========================================================
    // FASE 4: ENEMIGOS ATACAN EDIFICIOS (SOLO SI NO HAY TROPAS)
    // ==========================================================
    
    if (count($tropas) === 0) {
        // Crear mapa de edificios por posicion
        $edificios_por_posicion = [];
        foreach ($edificios as $edificio) {
            $edificios_por_posicion[$edificio['posicion_x']] = $edificio;
        }
        
        // Enemigos atacan edificios adyacentes
        foreach ($enemigos as $enemigo) {
            $posicion_enemigo = $enemigo['posicion'];
            $fila = floor($posicion_enemigo / 9);
            $columna = $posicion_enemigo % 9;
            
            // Posiciones adyacentes
            $posiciones_adyacentes = [];
            if ($fila > 0) $posiciones_adyacentes[] = ($fila - 1) * 9 + $columna;
            if ($fila < 8) $posiciones_adyacentes[] = ($fila + 1) * 9 + $columna;
            if ($columna > 0) $posiciones_adyacentes[] = $fila * 9 + ($columna - 1);
            if ($columna < 8) $posiciones_adyacentes[] = $fila * 9 + ($columna + 1);
            
            foreach ($posiciones_adyacentes as $pos_adyacente) {
                if (isset($edificios_por_posicion[$pos_adyacente])) {
                    $edificio_atacado = $edificios_por_posicion[$pos_adyacente];
                    
                    $daño = $enemigo['ataque'];
                    $nueva_vida = max(0, $edificio_atacado['vida_actual'] - $daño);
                    
                    $query = "UPDATE edificios_jugador 
                              SET vida_actual = ? 
                              WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ii", $nueva_vida, $edificio_atacado['id']);
                    $stmt->execute();
                    
                    $acciones[] = [
                        'tipo' => 'enemigo_ataca_edificio',
                        'enemigo' => $enemigo['enemigo_nombre'],
                        'edificio' => $edificio_atacado['edificio_nombre'],
                        'daño' => $daño,
                        'vida_restante' => $nueva_vida
                    ];
                    
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
                        
                        unset($edificios_por_posicion[$pos_adyacente]);
                    }
                    
                    break;
                }
            }
        }
    }
    
    // ================================
    // FASE 5: VERIFICAR FIN DE OLEADA
    // ================================
    
    $query = "SELECT COUNT(*) as vivos FROM enemigos_activos 
              WHERE jugador_id = ? AND esta_muerto = 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $jugador_id);
    $stmt->execute();
    $conteo = $stmt->get_result()->fetch_assoc();
    
    $oleada_completada = false;
    
    if ($conteo['vivos'] == 0) {
        $oleada_completada = true;
        
        // Calcular recompensa
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
        
        if ($oro_ganado > 0) {
            $query = "UPDATE recursos_jugador 
                      SET oro = oro + ? 
                      WHERE jugador_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $oro_ganado, $jugador_id);
            $stmt->execute();
        }
        
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
        
        // Guardar enemigos derrotados en historial
        $query = "SELECT oleada_actual FROM estado_oleadas WHERE jugador_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $jugador_id);
        $stmt->execute();
        $oleada_num = $stmt->get_result()->fetch_assoc()['oleada_actual'];

        $query = "INSERT INTO enemigos_derrotados_historial (jugador_id, oleada_numero, enemigo_catalogo_id, cantidad)
                SELECT ?, ?, enemigo_catalogo_id, COUNT(*)
                FROM enemigos_activos
                WHERE jugador_id = ? AND esta_muerto = 1
                GROUP BY enemigo_catalogo_id";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iii", $jugador_id, $oleada_num, $jugador_id);
        $stmt->execute();

        $query = "DELETE FROM enemigos_activos 
                  WHERE jugador_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $jugador_id);
        $stmt->execute();
        
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