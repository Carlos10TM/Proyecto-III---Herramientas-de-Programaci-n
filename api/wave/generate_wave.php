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

// Obtener el estado actual de oleadas
$query = "SELECT * FROM estado_oleadas WHERE jugador_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$estado = $stmt->get_result()->fetch_assoc();

if (!$estado) {
    echo json_encode(['success' => false, 'error' => 'Estado de oleadas no encontrado']);
    exit();
}

// Verificar que sea momento de generar la oleada
$query = "SELECT TIMESTAMPDIFF(SECOND, NOW(), ?) as segundos_hasta_oleada";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $estado['proxima_oleada_tiempo']);
$stmt->execute();
$tiempo = $stmt->get_result()->fetch_assoc();

if ($tiempo['segundos_hasta_oleada'] > 0) {
    echo json_encode(['success' => false, 'error' => 'Aún no es momento de la oleada']);
    exit();
}

// Verificar que no haya una oleada ya en curso
if ($estado['oleada_en_curso']) {
    echo json_encode(['success' => false, 'error' => 'Ya hay una oleada en curso']);
    exit();
}

$numero_oleada = $estado['oleada_actual'];

// Iniciar transaccion para garantizar consistencia
$conn->begin_transaction();

try {
    // Crear el registro de la oleada en la tabla oleadas
    $query = "INSERT INTO oleadas (jugador_id, oleada_numero, fecha_inicio) 
              VALUES (?, ?, NOW())";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $jugador_id, $numero_oleada);
    $stmt->execute();
    $oleada_id = $conn->insert_id;
    
    // Determinar que enemigos y cuantos segun el nivel de oleada
    $enemigos_a_generar = calcularEnemigos($numero_oleada);
    
    // Generar cada enemigo individual
    $enemigos_generados = 0;
    foreach ($enemigos_a_generar as $enemigo_data) {
        // Obtener los datos del catalogo de enemigos
        $query = "SELECT * FROM enemigos_catalogo WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $enemigo_data['enemigo_id']);
        $stmt->execute();
        $enemigo_catalogo = $stmt->get_result()->fetch_assoc();
        
        if (!$enemigo_catalogo) continue;
        
        // Generar la cantidad especificada de este tipo de enemigo
        for ($i = 0; $i < $enemigo_data['cantidad']; $i++) {
            // Posicion inicial: -1 indica que esta fuera del grid esperando entrar
            $query = "INSERT INTO enemigos_activos 
                      (jugador_id, oleada_id, enemigo_catalogo_id, vida_actual, posicion) 
                      VALUES (?, ?, ?, ?, -1)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iiii", 
                $jugador_id, 
                $oleada_id, 
                $enemigo_catalogo['id'], 
                $enemigo_catalogo['vida']
            );
            $stmt->execute();
            $enemigos_generados++;
        }
    }
    
    // Marcar la oleada como en curso
    $query = "UPDATE estado_oleadas 
              SET oleada_en_curso = 1 
              WHERE jugador_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $jugador_id);
    $stmt->execute();
    
    // Confirmar la transaccion
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'oleada_id' => $oleada_id,
        'numero_oleada' => $numero_oleada,
        'enemigos_generados' => $enemigos_generados,
        'mensaje' => "¡Oleada $numero_oleada iniciada! $enemigos_generados enemigos atacan tu reino."
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Error al generar oleada: ' . $e->getMessage()]);
}

// Funcion que determina que enemigos aparecen en cada oleada
function calcularEnemigos($numero_oleada) {
    $enemigos = [];
    
    // Oleada 1-2: Solo Goblins (Facil)
    if ($numero_oleada == 1) {
        $enemigos[] = ['enemigo_id' => 1, 'cantidad' => 3]; // 3 Goblins Exploradores
    } 
    else if ($numero_oleada == 2) {
        $enemigos[] = ['enemigo_id' => 1, 'cantidad' => 4]; // 4 Goblins Exploradores
        $enemigos[] = ['enemigo_id' => 2, 'cantidad' => 1]; // 1 Goblin Guerrero
    }
    // Oleada 3-5: Goblins y Orcos (Medio)
    else if ($numero_oleada == 3) {
        $enemigos[] = ['enemigo_id' => 2, 'cantidad' => 3]; // 3 Goblins Guerreros
        $enemigos[] = ['enemigo_id' => 3, 'cantidad' => 2]; // 2 Orcos Guerreros
    }
    else if ($numero_oleada == 4) {
        $enemigos[] = ['enemigo_id' => 2, 'cantidad' => 2]; // 2 Goblins Guerreros
        $enemigos[] = ['enemigo_id' => 3, 'cantidad' => 3]; // 3 Orcos Guerreros
        $enemigos[] = ['enemigo_id' => 4, 'cantidad' => 1]; // 1 Orco Berserker
    }
    else if ($numero_oleada == 5) {
        $enemigos[] = ['enemigo_id' => 3, 'cantidad' => 3]; // 3 Orcos Guerreros
        $enemigos[] = ['enemigo_id' => 4, 'cantidad' => 3]; // 3 Orcos Berserkers
    }
    // Oleada 6-8: Orcos y Trolls (Dificil)
    else if ($numero_oleada == 6) {
        $enemigos[] = ['enemigo_id' => 4, 'cantidad' => 3]; // 3 Orcos Berserkers
        $enemigos[] = ['enemigo_id' => 5, 'cantidad' => 2]; // 2 Trolls de Piedra
    }
    else if ($numero_oleada == 7) {
        $enemigos[] = ['enemigo_id' => 4, 'cantidad' => 2]; // 2 Orcos Berserkers
        $enemigos[] = ['enemigo_id' => 5, 'cantidad' => 3]; // 3 Trolls de Piedra
        $enemigos[] = ['enemigo_id' => 6, 'cantidad' => 1]; // 1 Troll Gigante
    }
    else if ($numero_oleada == 8) {
        $enemigos[] = ['enemigo_id' => 5, 'cantidad' => 2]; // 2 Trolls de Piedra
        $enemigos[] = ['enemigo_id' => 6, 'cantidad' => 3]; // 3 Trolls Gigantes
        $enemigos[] = ['enemigo_id' => 7, 'cantidad' => 2]; // 2 Esqueletos Guerreros
    }
    // Oleada 9-10: Esqueletos y Dragones (Muy Dificil)
    else if ($numero_oleada == 9) {
        $enemigos[] = ['enemigo_id' => 6, 'cantidad' => 2]; // 2 Trolls Gigantes
        $enemigos[] = ['enemigo_id' => 7, 'cantidad' => 4]; // 4 Esqueletos Guerreros
        $enemigos[] = ['enemigo_id' => 8, 'cantidad' => 1]; // 1 Dragon Joven
    }
    else if ($numero_oleada == 10) {
        $enemigos[] = ['enemigo_id' => 7, 'cantidad' => 3]; // 3 Esqueletos Guerreros
        $enemigos[] = ['enemigo_id' => 8, 'cantidad' => 2]; // 2 Dragones Jovenes
        $enemigos[] = ['enemigo_id' => 9, 'cantidad' => 1]; // 1 Dragon Ancestral (BOSS)
    }
    // Oleada 11+ Escala exponencialmente
    else {
        // Formula de escalado: mas enemigos a medida que suben las oleadas
        $multiplicador = 1 + ($numero_oleada - 10) * 0.3;
        $enemigos[] = ['enemigo_id' => 7, 'cantidad' => floor(4 * $multiplicador)];
        $enemigos[] = ['enemigo_id' => 8, 'cantidad' => floor(3 * $multiplicador)];
        $enemigos[] = ['enemigo_id' => 9, 'cantidad' => floor(2 * $multiplicador)];
    }
    
    return $enemigos;
}
?>