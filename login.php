<?php
session_start();
require_once 'config/connection.php';

// Si ya esta logueado, redirigir al juego
if (isset($_SESSION['jugador_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $query = "SELECT * FROM jugadores WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $jugador = $result->fetch_assoc();
        
        if (password_verify($password, $jugador['password'])) {
            $_SESSION['jugador_id'] = $jugador['id'];
            $_SESSION['username'] = $jugador['username'];
            header('Location: index.php');
            exit();
        } else {
            $error = 'Contraseña incorrecta';
        }
    } else {
        $error = 'Usuario no encontrado';
    }
}

// Procesar registro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = $_POST['reg_username'];
    $password = password_hash($_POST['reg_password'], PASSWORD_DEFAULT);
    
    // Verificar si el usuario ya existe
    $query = "SELECT id FROM jugadores WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $error = 'El usuario ya existe';
    } else {
        // Crear nuevo jugador
        $query = "INSERT INTO jugadores (username, password) VALUES (?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $username, $password);
        
        if ($stmt->execute()) {
            $jugador_id = $conn->insert_id;
            
            // Crear recursos iniciales
            $query = "INSERT INTO recursos_jugador (jugador_id, oro, madera, piedra, comida) 
                    VALUES (?, 500, 500, 500, 300)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $jugador_id);
            $stmt->execute();
            
            // Crear el ayuntamiento inicial (edificio_catalogo_id = 1, nivel 1)
            $query = "INSERT INTO edificios_jugador (jugador_id, edificio_catalogo_id, nivel, en_construccion, en_mejora) 
                    VALUES (?, 1, 1, 0, 0)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $jugador_id);
            $stmt->execute();
            
            $_SESSION['jugador_id'] = $jugador_id;
            $_SESSION['username'] = $username;
            header('Location: index.php');
            exit();
        } else {
            $error = 'Error al crear la cuenta';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Kingdooms - Login</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS personalizado -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="login-body">
    <div class="container">
        <div class="login-container">
            <!-- Logo/Titulo -->
            <div class="text-center mb-4">
                <i class="fas fa-crown fa-5x text-white"></i>
                <h1 class="text-white mt-3">Social Kingdooms</h1>
                <p class="text-white">Construye tu reino y conviértete en leyenda</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Tabs de Login y Registro -->
            <div class="card login-card">
                <div class="card-header login-card-header">
                    <ul class="nav nav-tabs card-header-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="login-tab" data-bs-toggle="tab" 
                                    data-bs-target="#login" type="button" role="tab">
                                <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="register-tab" data-bs-toggle="tab" 
                                    data-bs-target="#register" type="button" role="tab">
                                <i class="fas fa-user-plus"></i> Registrarse
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <!-- Tab Login -->
                        <div class="tab-pane fade show active" id="login" role="tabpanel">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-user"></i> Usuario
                                    </label>
                                    <input type="text" class="form-control" name="username" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-lock"></i> Contraseña
                                    </label>
                                    <input type="password" class="form-control" name="password" required>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" name="login" class="btn btn-primary btn-lg">
                                        <i class="fas fa-sign-in-alt"></i> Entrar al Reino
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Tab Registro -->
                        <div class="tab-pane fade" id="register" role="tabpanel">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-user"></i> Usuario
                                    </label>
                                    <input type="text" class="form-control" name="reg_username" 
                                           minlength="4" maxlength="50" required>
                                    <small class="form-text text-muted">Mínimo 4 caracteres</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-lock"></i> Contraseña
                                    </label>
                                    <input type="password" class="form-control" name="reg_password" 
                                           minlength="8" required>
                                    <small class="form-text text-muted">Mínimo 8 caracteres</small>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" name="register" class="btn btn-primary btn-lg">
                                        <i class="fas fa-user-plus"></i> Crear Reino
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center mt-3">
                <small class="text-white">
                    <i class="fa-solid fa-copyright"></i> 2025 Social Kingdooms - Todos los derechos reservados.
                    <br>
                    <i class="fas fa-info-circle"></i> Versión 1.0
                </small>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>