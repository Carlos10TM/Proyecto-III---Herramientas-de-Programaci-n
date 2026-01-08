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

// Obtener estado actual de oleadas
$query = "SELECT * FROM estado_oleadas WHERE jugador_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$estado = $stmt->get_result()->fetch_assoc();

// Calcular segundos hasta proxima oleada
$segundos_restantes = 0;
if ($estado && $estado['proxima_oleada_tiempo']) {
    $query = "SELECT TIMESTAMPDIFF(SECOND, NOW(), ?) as segundos";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $estado['proxima_oleada_tiempo']);
    $stmt->execute();
    $tiempo = $stmt->get_result()->fetch_assoc();
    $segundos_restantes = max(0, $tiempo['segundos']);
}

// Contar oleadas completadas
$query = "SELECT COUNT(*) as total FROM oleadas WHERE jugador_id = ? AND completada = 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$oleadas_completadas = $stmt->get_result()->fetch_assoc()['total'];

// Contar todos los enemigos derrotados de todas las oleadas
$query = "SELECT ec.tipo, ec.nombre, 
          (SELECT COUNT(*) 
           FROM enemigos_activos ea2
           WHERE ea2.enemigo_catalogo_id = ec.id 
           AND ea2.jugador_id = ? 
           AND ea2.esta_muerto = 1) as cantidad
          FROM enemigos_catalogo ec
          HAVING cantidad > 0
          ORDER BY cantidad DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$result = $stmt->get_result();

$enemigos_derrotados = [];
while ($row = $result->fetch_assoc()) {
    $enemigos_derrotados[] = $row;
}

// Calcular total de enemigos derrotados
$total_enemigos_derrotados = array_sum(array_column($enemigos_derrotados, 'cantidad'));

// Si hay oleada en curso obtener estadisticas actuales
$combate_actual = null;
if ($estado && $estado['oleada_en_curso']) {
    // Enemigos vivos y muertos en esta oleada
    $query = "SELECT 
              COUNT(*) as total,
              SUM(CASE WHEN esta_muerto = 1 THEN 1 ELSE 0 END) as eliminados,
              SUM(CASE WHEN esta_muerto = 0 THEN 1 ELSE 0 END) as vivos
              FROM enemigos_activos 
              WHERE jugador_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $jugador_id);
    $stmt->execute();
    $stats_enemigos = $stmt->get_result()->fetch_assoc();
    
    // Edificios destruidos
    $query = "SELECT COUNT(*) as total FROM edificios_jugador 
              WHERE jugador_id = ? AND esta_destruido = 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $jugador_id);
    $stmt->execute();
    $edificios_perdidos = $stmt->get_result()->fetch_assoc()['total'];
    
    // Obtener composicion de enemigos en esta oleada
    $query = "SELECT ec.nombre, ec.tipo, COUNT(*) as cantidad
              FROM enemigos_activos ea
              JOIN enemigos_catalogo ec ON ea.enemigo_catalogo_id = ec.id
              WHERE ea.jugador_id = ?
              GROUP BY ec.nombre, ec.tipo
              ORDER BY ec.id ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $jugador_id);
    $stmt->execute();
    $composicion = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $combate_actual = [
        'enemigos_total' => $stats_enemigos['total'],
        'enemigos_eliminados' => $stats_enemigos['eliminados'],
        'enemigos_vivos' => $stats_enemigos['vivos'],
        'edificios_perdidos' => $edificios_perdidos,
        'composicion_enemigos' => $composicion
    ];
}

// Calcular enemigos de la proxima oleada usando la misma logica de generate_wave.php
function calcularEnemigosProximaOleada($numero_oleada) {
    $enemigos = [];
    
    if ($numero_oleada == 1) {
        $enemigos[] = ['nombre' => 'Goblin Explorador', 'tipo' => 'goblin', 'cantidad' => 3];
    } 
    else if ($numero_oleada == 2) {
        $enemigos[] = ['nombre' => 'Goblin Explorador', 'tipo' => 'goblin', 'cantidad' => 4];
        $enemigos[] = ['nombre' => 'Goblin Guerrero', 'tipo' => 'goblin', 'cantidad' => 1];
    }
    else if ($numero_oleada == 3) {
        $enemigos[] = ['nombre' => 'Goblin Guerrero', 'tipo' => 'goblin', 'cantidad' => 3];
        $enemigos[] = ['nombre' => 'Orco Guerrero', 'tipo' => 'orco', 'cantidad' => 2];
    }
    else if ($numero_oleada == 4) {
        $enemigos[] = ['nombre' => 'Goblin Guerrero', 'tipo' => 'goblin', 'cantidad' => 2];
        $enemigos[] = ['nombre' => 'Orco Guerrero', 'tipo' => 'orco', 'cantidad' => 3];
        $enemigos[] = ['nombre' => 'Orco Berserker', 'tipo' => 'orco', 'cantidad' => 1];
    }
    else if ($numero_oleada == 5) {
        $enemigos[] = ['nombre' => 'Orco Guerrero', 'tipo' => 'orco', 'cantidad' => 3];
        $enemigos[] = ['nombre' => 'Orco Berserker', 'tipo' => 'orco', 'cantidad' => 3];
    }
    else if ($numero_oleada == 6) {
        $enemigos[] = ['nombre' => 'Orco Berserker', 'tipo' => 'orco', 'cantidad' => 3];
        $enemigos[] = ['nombre' => 'Troll de Piedra', 'tipo' => 'troll', 'cantidad' => 2];
    }
    else if ($numero_oleada == 7) {
        $enemigos[] = ['nombre' => 'Orco Berserker', 'tipo' => 'orco', 'cantidad' => 2];
        $enemigos[] = ['nombre' => 'Troll de Piedra', 'tipo' => 'troll', 'cantidad' => 3];
        $enemigos[] = ['nombre' => 'Troll Gigante', 'tipo' => 'troll', 'cantidad' => 1];
    }
    else if ($numero_oleada == 8) {
        $enemigos[] = ['nombre' => 'Troll de Piedra', 'tipo' => 'troll', 'cantidad' => 2];
        $enemigos[] = ['nombre' => 'Troll Gigante', 'tipo' => 'troll', 'cantidad' => 3];
        $enemigos[] = ['nombre' => 'Esqueleto Guerrero', 'tipo' => 'esqueleto', 'cantidad' => 2];
    }
    else if ($numero_oleada == 9) {
        $enemigos[] = ['nombre' => 'Troll Gigante', 'tipo' => 'troll', 'cantidad' => 2];
        $enemigos[] = ['nombre' => 'Esqueleto Guerrero', 'tipo' => 'esqueleto', 'cantidad' => 4];
        $enemigos[] = ['nombre' => 'Dragón Joven', 'tipo' => 'dragon', 'cantidad' => 1];
    }
    else if ($numero_oleada == 10) {
        $enemigos[] = ['nombre' => 'Esqueleto Guerrero', 'tipo' => 'esqueleto', 'cantidad' => 3];
        $enemigos[] = ['nombre' => 'Dragón Joven', 'tipo' => 'dragon', 'cantidad' => 2];
        $enemigos[] = ['nombre' => 'Dragón Ancestral', 'tipo' => 'dragon', 'cantidad' => 1];
    }
    else {
        $multiplicador = 1 + ($numero_oleada - 10) * 0.3;
        $enemigos[] = ['nombre' => 'Esqueleto Guerrero', 'tipo' => 'esqueleto', 'cantidad' => floor(4 * $multiplicador)];
        $enemigos[] = ['nombre' => 'Dragón Joven', 'tipo' => 'dragon', 'cantidad' => floor(3 * $multiplicador)];
        $enemigos[] = ['nombre' => 'Dragón Ancestral', 'tipo' => 'dragon', 'cantidad' => floor(2 * $multiplicador)];
    }
    
    return $enemigos;
}

$proxima_oleada_enemigos = calcularEnemigosProximaOleada($estado['oleada_actual']);

echo json_encode([
    'success' => true,
    'oleada_actual' => $estado['oleada_actual'],
    'oleada_en_curso' => (bool)$estado['oleada_en_curso'],
    'segundos_hasta_proxima' => $segundos_restantes,
    'oleadas_completadas' => $oleadas_completadas,
    'total_enemigos_derrotados' => $total_enemigos_derrotados,
    'enemigos_derrotados' => $enemigos_derrotados,
    'combate_actual' => $combate_actual,
    'proxima_oleada_enemigos' => $proxima_oleada_enemigos
]);
?>