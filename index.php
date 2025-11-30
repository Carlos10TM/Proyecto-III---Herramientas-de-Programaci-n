<?php
session_start();
require_once 'config/connection.php';

// Verificar si el usuario esta logueado
if (!isset($_SESSION['jugador_id'])) {
    header('Location: login.php');
    exit();
}

// Obtener datos del jugador
$jugador_id = $_SESSION['jugador_id'];
$query = "SELECT j.*, r.oro, r.madera, r.piedra, r.comida 
          FROM jugadores j 
          LEFT JOIN recursos_jugador r ON j.id = r.jugador_id 
          WHERE j.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $jugador_id);
$stmt->execute();
$result = $stmt->get_result();
$jugador = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Kingdooms - Tu Reino</title>
    <link rel="icon" type="image/png" sizes="32x32" href="img/ui/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="img/ui/favicon-16x16.png">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS personalizado -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-crown"></i> Social Kingdooms
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($jugador['username']); ?> 
                            (Nivel Ayuntamiento <?php echo $jugador['nivel_ayuntamiento']; ?>)
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Salir
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Panel de Recursos -->
    <div class="container-fluid mt-3">
        <div class="row">
            <div class="col-12">
                <div class="card bg-dark text-white">
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3 col-6 mb-2">
                                <i class="fas fa-coins fa-2x text-warning"></i>
                                <h5 class="mt-2">Oro</h5>
                                <h4 id="oro"><?php echo number_format($jugador['oro']); ?></h4>
                            </div>
                            <div class="col-md-3 col-6 mb-2">
                                <i class="fas fa-tree fa-2x text-success"></i>
                                <h5 class="mt-2">Madera</h5>
                                <h4 id="madera"><?php echo number_format($jugador['madera']); ?></h4>
                            </div>
                            <div class="col-md-3 col-6 mb-2">
                                <i class="fas fa-mountain fa-2x text-secondary"></i>
                                <h5 class="mt-2">Piedra</h5>
                                <h4 id="piedra"><?php echo number_format($jugador['piedra']); ?></h4>
                            </div>
                            <div class="col-md-3 col-6 mb-2">
                                <i class="fas fa-bread-slice fa-2x text-danger"></i>
                                <h5 class="mt-2">Comida</h5>
                                <h4 id="comida"><?php echo number_format($jugador['comida']); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- area del Juego -->
        <div class="row mt-3">
            <!-- Panel Izquierdo - Menu -->
            <div class="col-lg-3 col-md-4 mb-3">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5><i class="fas fa-bars"></i> Menú Principal</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-secondary" onclick="mostrarSeccion('bienvenida')">
                                <i class="fas fa-home"></i> Inicio
                            </button>
                            <button class="btn btn-outline-primary" onclick="mostrarSeccion('edificios')">
                                <i class="fas fa-building"></i> Edificios
                            </button>
                            <button class="btn btn-outline-success" onclick="mostrarSeccion('unidades')">
                                <i class="fas fa-users"></i> Unidades
                            </button>
                            <button class="btn btn-outline-danger" onclick="mostrarSeccion('combate')">
                                <i class="fas fa-skull-crossbones"></i> Combate
                            </button>
                            <button class="btn btn-outline-info" onclick="mostrarSeccion('estadisticas')">
                                <i class="fas fa-chart-bar"></i> Estadísticas
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel Central - Contenido Principal -->
            <div class="col-lg-9 col-md-8">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5><i class="fas fa-gamepad"></i> Tu Reino</h5>
                    </div>
                    <div class="card-body" id="contenido-principal">
                        <!-- Seccion de Bienvenida-->
                        <div id="seccion-bienvenida" class="seccion-juego">
                            <!-- Mensaje de bienvenida -->
                            <div class="alert alert-info mb-3" style="border-left: 5px solid #667eea;">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h5 class="mb-1">
                                            <i class="fas fa-crown"></i> ¡Bienvenido a Social Kingdooms, <?php echo htmlspecialchars($jugador['username']); ?>!
                                        </h5>
                                        <p class="mb-0 small">
                                            <i class="fas fa-shield-alt"></i> Defiende tu reino de las oleadas enemigas
                                            <span class="mx-2">|</span>
                                            <i class="fas fa-users"></i> Entrena tropas poderosas
                                            <span class="mx-2">|</span>
                                            <i class="fas fa-building"></i> Construye y mejora edificios
                                        </p>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <span class="badge bg-success p-2">
                                            <i class="fas fa-flag"></i> Reino Nivel <?php echo $jugador['nivel_ayuntamiento']; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Canvas del juego -->
                            <div id="game-area-container">
                                <div id="game-area">
                                    <div id="base-grid">
                                        <!-- Las casillas se generan con JavaScript -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Seccion de Edificios -->
                        <div id="seccion-edificios" class="seccion-juego" style="display: none;">
                            <h4><i class="fas fa-building"></i> Construcción de Edificios</h4>
                            <hr>
                            
                            <!-- Mis Edificios -->
                            <div class="mb-4">
                                <h5>Mis Edificios:</h5>
                                <div id="mis-edificios" class="row">
                                    <div class="col-12">
                                        <div class="alert alert-secondary">
                                            <i class="fas fa-spinner fa-spin"></i> Cargando tus edificios...
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Edificios Disponibles -->
                            <div>
                                <h5>Edificios Disponibles para Construir:</h5>
                                <div id="edificios-disponibles" class="row">
                                    <div class="col-12">
                                        <div class="alert alert-secondary">
                                            <i class="fas fa-spinner fa-spin"></i> Cargando edificios disponibles...
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Seccion de Unidades -->
                        <div id="seccion-unidades" class="seccion-juego" style="display: none;">
                            <h4><i class="fas fa-users"></i> Entrenamiento de Unidades</h4>
                            <hr>
                            <div class="alert alert-warning">
                                <i class="fas fa-hammer"></i> Sistema en desarrollo...
                            </div>
                        </div>

                        <!-- Seccion de Combate -->
                        <div id="seccion-combate" class="seccion-juego" style="display: none;">
                            <h4><i class="fas fa-skull-crossbones"></i> Combate contra Enemigos</h4>
                            <hr>
                            <div class="alert alert-warning">
                                <i class="fas fa-hammer"></i> Sistema en desarrollo...
                            </div>
                        </div>

                        <!-- Seccion de Estadisticas -->
                        <div id="seccion-estadisticas" class="seccion-juego" style="display: none;">
                            <h4><i class="fas fa-chart-bar"></i> Estadísticas del Reino</h4>
                            <hr>
                            <div class="alert alert-warning">
                                <i class="fas fa-hammer"></i> Sistema en desarrollo...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenedor de notificaciones -->
    <div id="notificaciones-container" style="position: fixed; bottom: 20px; left: 20px; z-index: 9999; width: 320px;"></div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript del juego -->
    <script src="js/game.js"></script>

</body>
</html>