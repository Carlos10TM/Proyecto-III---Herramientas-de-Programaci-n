-- ============================================
-- BASE DE DATOS: SOCIAL KINGDOOMS
-- ============================================

-- Tabla de jugadores
CREATE TABLE IF NOT EXISTS jugadores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nivel_ayuntamiento INT DEFAULT 1,
    limite_tropas INT DEFAULT 10,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultima_conexion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de recursos del jugador
CREATE TABLE IF NOT EXISTS recursos_jugador (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jugador_id INT NOT NULL,
    oro INT DEFAULT 1000,
    madera INT DEFAULT 500,
    piedra INT DEFAULT 500,
    comida INT DEFAULT 300,
    ultima_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (jugador_id) REFERENCES jugadores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de edificios base (Catalogo de edificios disponibles)
CREATE TABLE IF NOT EXISTS edificios_catalogo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    tipo ENUM('ayuntamiento', 'aserradero', 'cantera', 'granja', 'mina_oro', 'cuartel', 'torre') NOT NULL,
    descripcion TEXT,
    nivel_ayuntamiento_requerido INT DEFAULT 1,
    limite_construccion INT DEFAULT 3 COMMENT 'Máximo de este edificio que se puede construir',
    nivel_max INT DEFAULT 5
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de costos y estadisticas por nivel de edificio
CREATE TABLE IF NOT EXISTS edificios_niveles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    edificio_catalogo_id INT NOT NULL,
    nivel INT NOT NULL,
    costo_madera INT DEFAULT 0,
    costo_piedra INT DEFAULT 0,
    costo_comida INT DEFAULT 0,
    tiempo_construccion INT NOT NULL COMMENT 'Tiempo en segundos',
    generacion_por_minuto INT DEFAULT 0 COMMENT 'Recursos que genera por minuto (si aplica)',
    bonus_tropas INT DEFAULT 0 COMMENT 'Tropas adicionales que otorga (ayuntamiento)',
    FOREIGN KEY (edificio_catalogo_id) REFERENCES edificios_catalogo(id) ON DELETE CASCADE,
    UNIQUE KEY unique_edificio_nivel (edificio_catalogo_id, nivel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de edificios del jugador
CREATE TABLE IF NOT EXISTS edificios_jugador (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jugador_id INT NOT NULL,
    edificio_catalogo_id INT NOT NULL,
    nivel INT DEFAULT 1,
    en_construccion BOOLEAN DEFAULT FALSE,
    en_mejora BOOLEAN DEFAULT FALSE,
    tiempo_finalizacion TIMESTAMP NULL,
    posicion_x INT DEFAULT 0,
    posicion_y INT DEFAULT 0,
    fecha_construccion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (jugador_id) REFERENCES jugadores(id) ON DELETE CASCADE,
    FOREIGN KEY (edificio_catalogo_id) REFERENCES edificios_catalogo(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de unidades base (Catalogo)
CREATE TABLE IF NOT EXISTS unidades_catalogo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    tipo ENUM('soldado', 'arquero', 'caballero', 'mago') NOT NULL,
    ataque INT NOT NULL,
    defensa INT NOT NULL,
    vida INT NOT NULL,
    costo_oro INT NOT NULL,
    costo_comida INT NOT NULL,
    tiempo_entrenamiento INT NOT NULL COMMENT 'en segundos',
    descripcion TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de unidades del jugador
CREATE TABLE IF NOT EXISTS unidades_jugador (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jugador_id INT NOT NULL,
    unidad_catalogo_id INT NOT NULL,
    cantidad INT DEFAULT 0,
    en_entrenamiento INT DEFAULT 0,
    tiempo_finalizacion TIMESTAMP NULL,
    FOREIGN KEY (jugador_id) REFERENCES jugadores(id) ON DELETE CASCADE,
    FOREIGN KEY (unidad_catalogo_id) REFERENCES unidades_catalogo(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de enemigos (Catalogo)
CREATE TABLE IF NOT EXISTS enemigos_catalogo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    tipo ENUM('goblin', 'orco', 'troll', 'dragon', 'esqueleto') NOT NULL,
    nivel INT NOT NULL,
    vida INT NOT NULL,
    ataque INT NOT NULL,
    defensa INT NOT NULL,
    recompensa_oro INT NOT NULL,
    descripcion TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de oleadas/ataques
CREATE TABLE IF NOT EXISTS oleadas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jugador_id INT NOT NULL,
    oleada_numero INT NOT NULL,
    enemigos_derrotados INT DEFAULT 0,
    oro_ganado INT DEFAULT 0,
    completada BOOLEAN DEFAULT FALSE,
    fecha_inicio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_finalizacion TIMESTAMP NULL,
    FOREIGN KEY (jugador_id) REFERENCES jugadores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- DATOS INICIALES
-- ============================================

-- Insertar edificios en el catalogo
INSERT INTO edificios_catalogo (nombre, tipo, descripcion, nivel_ayuntamiento_requerido, limite_construccion, nivel_max) VALUES
('Ayuntamiento', 'ayuntamiento', 'Centro de tu reino. Desbloquea nuevas construcciones y aumenta el límite de tropas.', 1, 1, 5),
('Aserradero', 'aserradero', 'Produce madera constantemente.', 1, 3, 5),
('Cantera', 'cantera', 'Produce piedra constantemente.', 1, 3, 5),
('Granja', 'granja', 'Produce comida para tus tropas.', 1, 3, 5),
('Mina de Oro', 'mina_oro', 'Produce el valioso oro necesario para entrenar tropas.', 5, 1, 3),
('Cuartel', 'cuartel', 'Permite entrenar unidades militares.', 2, 1, 5),
('Torre de Defensa', 'torre', 'Defiende tu base de ataques enemigos.', 3, 4, 5);

-- Costos y stats del AYUNTAMIENTO
INSERT INTO edificios_niveles (edificio_catalogo_id, nivel, costo_madera, costo_piedra, tiempo_construccion, bonus_tropas) VALUES
(1, 1, 0, 0, 0, 10),
(1, 2, 1000, 1000, 120, 20),
(1, 3, 2000, 2000, 300, 35),
(1, 4, 4000, 4000, 600, 50),
(1, 5, 8000, 8000, 1200, 75);

-- Costos y stats del ASERRADERO
INSERT INTO edificios_niveles (edificio_catalogo_id, nivel, costo_madera, costo_piedra, tiempo_construccion, generacion_por_minuto) VALUES
(2, 1, 300, 200, 30, 10),
(2, 2, 600, 400, 60, 20),
(2, 3, 1200, 800, 120, 35),
(2, 4, 2400, 1600, 240, 60),
(2, 5, 4800, 3200, 480, 100);

-- Costos y stats de la CANTERA
INSERT INTO edificios_niveles (edificio_catalogo_id, nivel, costo_madera, costo_piedra, tiempo_construccion, generacion_por_minuto) VALUES
(3, 1, 200, 300, 30, 10),
(3, 2, 400, 600, 60, 20),
(3, 3, 800, 1200, 120, 35),
(3, 4, 1600, 2400, 240, 60),
(3, 5, 3200, 4800, 480, 100);

-- Costos y stats de la GRANJA
INSERT INTO edificios_niveles (edificio_catalogo_id, nivel, costo_madera, costo_piedra, tiempo_construccion, generacion_por_minuto) VALUES
(4, 1, 250, 250, 30, 8),
(4, 2, 500, 500, 60, 15),
(4, 3, 1000, 1000, 120, 28),
(4, 4, 2000, 2000, 240, 50),
(4, 5, 4000, 4000, 480, 85);

-- Costos y stats de la MINA DE ORO
INSERT INTO edificios_niveles (edificio_catalogo_id, nivel, costo_madera, costo_piedra, costo_comida, tiempo_construccion, generacion_por_minuto) VALUES
(5, 1, 5000, 5000, 2000, 300, 5),
(5, 2, 10000, 10000, 4000, 600, 10),
(5, 3, 20000, 20000, 8000, 1200, 18);

-- Insertar unidades basicas
INSERT INTO unidades_catalogo (nombre, tipo, ataque, defensa, vida, costo_oro, costo_comida, tiempo_entrenamiento, descripcion) VALUES
('Soldado', 'soldado', 10, 8, 50, 50, 20, 30, 'Unidad básica de infantería'),
('Arquero', 'arquero', 15, 5, 40, 80, 30, 45, 'Ataca a distancia con gran precisión'),
('Caballero', 'caballero', 25, 15, 100, 150, 50, 90, 'Poderosa unidad montada'),
('Mago', 'mago', 30, 10, 60, 200, 40, 120, 'Causa daño mágico devastador');

-- Insertar enemigos basicos
INSERT INTO enemigos_catalogo (nombre, tipo, nivel, vida, ataque, defensa, recompensa_oro, descripcion) VALUES
('Goblin Explorador', 'goblin', 1, 30, 5, 3, 10, 'Débil pero rápido'),
('Orco Guerrero', 'orco', 2, 80, 12, 8, 25, 'Fuerte y resistente'),
('Troll de Piedra', 'troll', 3, 150, 20, 15, 50, 'Muy difícil de derrotar'),
('Dragón Joven', 'dragon', 5, 300, 40, 25, 150, 'Boss final de oleada');