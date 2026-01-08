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

function sonAdyacentes($pos1, $pos2) {
    $distancia = calcularDistancia($pos1, $pos2);
    // Considerar adyacentes si estan en la misma posición O a distancia 1
    return $distancia === 0 || $distancia === 1;
}

function calcularDistancia($pos1, $pos2) {
    $coord1 = posicionACoordenadas($pos1);
    $coord2 = posicionACoordenadas($pos2);
    return abs($coord1['fila'] - $coord2['fila']) + abs($coord1['columna'] - $coord2['columna']);
}

function posicionACoordenadas($posicion) {
    return [
        'fila' => floor($posicion / 9),
        'columna' => $posicion % 9
    ];
}

// Iniciar transaccion
$conn->begin_transaction();

try {
    $acciones = [];
    
    // ===================================
    // FASE 1: OBTENER TODOS LAS UNIDADES
    // ===================================

    // Recibir posiciones de tropas desde el cliente
    $input = json_decode(file_get_contents('php://input'), true);
    $posiciones_tropas_cliente = $input['posiciones_tropas'] ?? [];

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

    // Usar las posiciones recibidas del cliente
    $tropas = [];
    foreach ($posiciones_tropas_cliente as $tropa_data) {
        $tropas[] = [
            'id' => $tropa_data['id'],
            'tropa_individual_id' => $tropa_data['individual_id'],
            'nombre' => $tropa_data['nombre'],
            'ataque' => $tropa_data['ataque'],
            'vida_actual' => $tropa_data['vida_actual'],
            'vida_maxima' => $tropa_data['vida_maxima'],
            'posicion' => $tropa_data['posicion']
        ];
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

    error_log("=== INICIO FASE 2 COMBATE ===");
    error_log("Total tropas disponibles: " . count($tropas));
    error_log("Total enemigos disponibles: " . count($enemigos));

    $tropas_muertas = [];

    // Si hay tropas, los enemigos priorizan atacarlas
    if (count($tropas) > 0) {
        error_log("HAY TROPAS - Iniciando combate");
        
        // Cada enemigo busca una tropa adyacente para atacar
        foreach ($enemigos as $key_enemigo => $enemigo) {
            if ($enemigo['vida_actual'] <= 0) {
                error_log("Enemigo {$enemigo['enemigo_nombre']} está muerto, skip");
                continue;
            }
            
            error_log("Enemigo {$enemigo['enemigo_nombre']} buscando objetivo desde posición {$enemigo['posicion']}");
            
            $objetivo_encontrado = false;
            
            // Buscar tropas adyacentes
            foreach ($tropas as $key_tropa => $tropa) {
                if ($tropa['vida_actual'] <= 0) {
                    error_log("  - Tropa {$tropa['nombre']} está muerta, skip");
                    continue;
                }
                
                $distancia = calcularDistancia($enemigo['posicion'], $tropa['posicion']);
                $es_adyacente = sonAdyacentes($enemigo['posicion'], $tropa['posicion']);
                
                error_log("  - Revisando tropa {$tropa['nombre']} en posición {$tropa['posicion']}");
                error_log("    Distancia: {$distancia}, Es adyacente: " . ($es_adyacente ? 'SI' : 'NO'));
                
                if ($es_adyacente) {
                    error_log("    ¡ADYACENTE ENCONTRADO! Enemigo atacará");
                    
                    // Enemigo ataca tropa
                    $daño = $enemigo['ataque'];
                    $nueva_vida = max(0, $tropa['vida_actual'] - $daño);
                    
                    $tropas[$key_tropa]['vida_actual'] = $nueva_vida;
                    
                    error_log("    Daño: {$daño}, Vida restante: {$nueva_vida}");
                    
                    $acciones[] = [
                        'tipo' => 'enemigo_ataca_tropa',
                        'enemigo' => $enemigo['enemigo_nombre'],
                        'tropa' => $tropa['nombre'],
                        'daño' => $daño,
                        'vida_restante' => $nueva_vida
                    ];
                    
                    // Si la tropa murio
                    if ($nueva_vida <= 0) {
                        error_log("    ¡TROPA MUERTA!");
                        $tropas_muertas[] = $tropa['id'];
                        $tropas[$key_tropa]['vida_actual'] = 0;
                        
                        $acciones[] = [
                            'tipo' => 'tropa_muerta',
                            'tropa' => $tropa['nombre']
                        ];
                    }
                    
                    $objetivo_encontrado = true;
                    break; // Un enemigo ataca solo una tropa por turno
                }
            }
            
            if (!$objetivo_encontrado) {
                error_log("  - NO encontró tropa adyacente");
            }
        }
        
        error_log("Tropas muertas en este turno: " . count($tropas_muertas));
        
        // Reducir cantidad de tropas muertas en la BD
        if (count($tropas_muertas) > 0) {
            foreach (array_count_values($tropas_muertas) as $tropa_id => $cantidad_muertas) {
                error_log("Reduciendo {$cantidad_muertas} unidades de tropa ID {$tropa_id}");
                
                $query = "UPDATE unidades_jugador 
                        SET cantidad = GREATEST(0, cantidad - ?)
                        WHERE id = ? AND jugador_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("iii", $cantidad_muertas, $tropa_id, $jugador_id);
                $stmt->execute();
                
                // Si la cantidad llega a 0, eliminar las posiciones
                $query = "DELETE FROM posiciones_tropas 
                        WHERE jugador_id = ? AND unidad_jugador_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ii", $jugador_id, $tropa_id);
                $stmt->execute();
            }
        }
        
        error_log("--- CONTRAATAQUE DE TROPAS ---");
        
        // Tropas contraatacan a enemigos adyacentes
        foreach ($tropas as $key_tropa => $tropa) {
            if ($tropa['vida_actual'] <= 0) {
                error_log("Tropa {$tropa['nombre']} está muerta, skip contraataque");
                continue;
            }
            
            error_log("Tropa {$tropa['nombre']} buscando enemigo desde posición {$tropa['posicion']}");
            
            foreach ($enemigos as $key_enemigo => $enemigo) {
                if ($enemigo['vida_actual'] <= 0) {
                    error_log("  - Enemigo {$enemigo['enemigo_nombre']} está muerto, skip");
                    continue;
                }
                
                $distancia = calcularDistancia($tropa['posicion'], $enemigo['posicion']);
                $es_adyacente = sonAdyacentes($tropa['posicion'], $enemigo['posicion']);
                
                error_log("  - Revisando enemigo {$enemigo['enemigo_nombre']} en posición {$enemigo['posicion']}");
                error_log("    Distancia: {$distancia}, Es adyacente: " . ($es_adyacente ? 'SI' : 'NO'));
                
                if ($es_adyacente) {
                    error_log("    ¡ADYACENTE ENCONTRADO! Tropa contraatacará");
                    
                    // Tropa ataca enemigo
                    $daño = $tropa['ataque'];
                    $nueva_vida = max(0, $enemigo['vida_actual'] - $daño);
                    
                    error_log("    Daño: {$daño}, Vida restante: {$nueva_vida}");
                    
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
                        error_log("    ¡ENEMIGO MUERTO!");
                        
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
                        
                        $enemigos[$key_enemigo]['vida_actual'] = 0;
                    }
                    
                    break; // Una tropa ataca solo un enemigo por turno
                }
            }
        }
        
        error_log("Total acciones generadas: " . count($acciones));
    } else {
        error_log("NO HAY TROPAS - Saltando al ataque de edificios");
    }

    error_log("=== FIN FASE 2 COMBATE ===");
    
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