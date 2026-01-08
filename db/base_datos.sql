-- ============================================
-- BASE DE DATOS: SOCIAL KINGDOOMS
-- ============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "-03:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE DATABASE IF NOT EXISTS `social_kingdooms` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE `social_kingdooms`;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cola_entrenamiento`
--

DROP TABLE IF EXISTS `cola_entrenamiento`;
CREATE TABLE IF NOT EXISTS `cola_entrenamiento` (
  `id` int NOT NULL AUTO_INCREMENT,
  `jugador_id` int NOT NULL,
  `unidad_catalogo_id` int NOT NULL,
  `tiempo_finalizacion` timestamp NOT NULL,
  `fecha_inicio` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `unidad_catalogo_id` (`unidad_catalogo_id`),
  KEY `idx_jugador_finalizacion` (`jugador_id`,`tiempo_finalizacion`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `edificios_catalogo`
--

DROP TABLE IF EXISTS `edificios_catalogo`;
CREATE TABLE IF NOT EXISTS `edificios_catalogo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `tipo` enum('ayuntamiento','aserradero','cantera','granja','mina_oro','cuartel','torre') NOT NULL,
  `descripcion` text,
  `nivel_ayuntamiento_requerido` int DEFAULT '1',
  `limite_construccion` int DEFAULT '3' COMMENT 'Máximo de este edificio que se puede construir',
  `nivel_max` int DEFAULT '5',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `edificios_catalogo`
--

INSERT INTO `edificios_catalogo` (`id`, `nombre`, `tipo`, `descripcion`, `nivel_ayuntamiento_requerido`, `limite_construccion`, `nivel_max`) VALUES
(1, 'Ayuntamiento', 'ayuntamiento', 'Centro de tu reino. Desbloquea nuevas construcciones y aumenta el límite de tropas.', 1, 1, 5),
(2, 'Aserradero', 'aserradero', 'Produce madera constantemente.', 1, 3, 5),
(3, 'Cantera', 'cantera', 'Produce piedra constantemente.', 1, 3, 5),
(4, 'Granja', 'granja', 'Produce comida para tus tropas.', 1, 3, 5),
(5, 'Mina de Oro', 'mina_oro', 'Produce el valioso oro necesario para entrenar tropas.', 5, 1, 3),
(6, 'Cuartel', 'cuartel', 'Permite entrenar unidades para defender tu reino.', 1, 1, 5),
(7, 'Torre de Defensa', 'torre', 'Defiende tu base de ataques enemigos.', 3, 4, 5);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `edificios_jugador`
--

DROP TABLE IF EXISTS `edificios_jugador`;
CREATE TABLE IF NOT EXISTS `edificios_jugador` (
  `id` int NOT NULL AUTO_INCREMENT,
  `jugador_id` int NOT NULL,
  `edificio_catalogo_id` int NOT NULL,
  `nivel` int DEFAULT '1',
  `en_construccion` tinyint(1) DEFAULT '0',
  `en_mejora` tinyint(1) DEFAULT '0',
  `tiempo_finalizacion` timestamp NULL DEFAULT NULL,
  `posicion_x` int DEFAULT '0',
  `posicion_y` int DEFAULT '0',
  `vida_actual` int DEFAULT NULL,
  `vida_maxima` int DEFAULT NULL,
  `esta_destruido` tinyint(1) DEFAULT '0',
  `fecha_construccion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `jugador_id` (`jugador_id`),
  KEY `edificio_catalogo_id` (`edificio_catalogo_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `edificios_niveles`
--

DROP TABLE IF EXISTS `edificios_niveles`;
CREATE TABLE IF NOT EXISTS `edificios_niveles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `edificio_catalogo_id` int NOT NULL,
  `nivel` int NOT NULL,
  `costo_madera` int DEFAULT '0',
  `costo_piedra` int DEFAULT '0',
  `costo_comida` int DEFAULT '0',
  `tiempo_construccion` int NOT NULL COMMENT 'Tiempo en segundos',
  `generacion_por_minuto` int DEFAULT '0' COMMENT 'Recursos que genera por minuto (si aplica)',
  `bonus_tropas` int DEFAULT '0' COMMENT 'Tropas adicionales que otorga (ayuntamiento)',
  `vida_base` int DEFAULT '100',
  `colas_entrenamiento` int DEFAULT '0',
  `reduccion_tiempo_entrenamiento` int DEFAULT '0' COMMENT 'Porcentaje de reducción',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_edificio_nivel` (`edificio_catalogo_id`,`nivel`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `edificios_niveles`
--

INSERT INTO `edificios_niveles` (`id`, `edificio_catalogo_id`, `nivel`, `costo_madera`, `costo_piedra`, `costo_comida`, `tiempo_construccion`, `generacion_por_minuto`, `bonus_tropas`, `vida_base`, `colas_entrenamiento`, `reduccion_tiempo_entrenamiento`) VALUES
(1, 1, 1, 0, 0, 0, 0, 0, 10, 100, 0, 0),
(2, 1, 2, 1000, 1000, 0, 120, 0, 20, 150, 0, 0),
(3, 1, 3, 2000, 2000, 0, 300, 0, 35, 200, 0, 0),
(4, 1, 4, 4000, 4000, 0, 600, 0, 50, 250, 0, 0),
(5, 1, 5, 8000, 8000, 0, 1200, 0, 75, 300, 0, 0),
(6, 2, 1, 300, 200, 0, 30, 10, 0, 100, 0, 0),
(7, 2, 2, 600, 400, 0, 60, 20, 0, 150, 0, 0),
(8, 2, 3, 1200, 800, 0, 120, 35, 0, 200, 0, 0),
(9, 2, 4, 2400, 1600, 0, 240, 60, 0, 250, 0, 0),
(10, 2, 5, 4800, 3200, 0, 480, 100, 0, 300, 0, 0),
(11, 3, 1, 200, 300, 0, 30, 10, 0, 100, 0, 0),
(12, 3, 2, 400, 600, 0, 60, 20, 0, 150, 0, 0),
(13, 3, 3, 800, 1200, 0, 120, 35, 0, 200, 0, 0),
(14, 3, 4, 1600, 2400, 0, 240, 60, 0, 250, 0, 0),
(15, 3, 5, 3200, 4800, 0, 480, 100, 0, 300, 0, 0),
(16, 4, 1, 250, 250, 0, 30, 8, 0, 100, 0, 0),
(17, 4, 2, 500, 500, 0, 60, 15, 0, 150, 0, 0),
(18, 4, 3, 1000, 1000, 0, 120, 28, 0, 200, 0, 0),
(19, 4, 4, 2000, 2000, 0, 240, 50, 0, 250, 0, 0),
(20, 4, 5, 4000, 4000, 0, 480, 85, 0, 300, 0, 0),
(21, 5, 1, 5000, 5000, 2000, 300, 5, 0, 100, 0, 0),
(22, 5, 2, 10000, 10000, 4000, 600, 10, 0, 150, 0, 0),
(23, 5, 3, 20000, 20000, 8000, 1200, 18, 0, 200, 0, 0),
(24, 6, 1, 500, 500, 0, 60, 0, 0, 100, 1, 0),
(25, 6, 2, 1000, 1000, 0, 120, 0, 0, 150, 1, 10),
(26, 6, 3, 2000, 2000, 0, 240, 0, 0, 200, 2, 15),
(27, 6, 4, 4000, 4000, 0, 480, 0, 0, 250, 2, 20),
(28, 6, 5, 8000, 8000, 0, 960, 0, 0, 300, 3, 25),
(29, 7, 1, 400, 600, 0, 45, 0, 0, 100, 0, 0),
(30, 7, 2, 800, 1200, 0, 90, 0, 0, 150, 0, 0),
(31, 7, 3, 1600, 2400, 0, 180, 0, 0, 200, 0, 0),
(32, 7, 4, 3200, 4800, 0, 360, 0, 0, 250, 0, 0),
(33, 7, 5, 6400, 9600, 0, 720, 0, 0, 300, 0, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `enemigos_activos`
--

DROP TABLE IF EXISTS `enemigos_activos`;
CREATE TABLE IF NOT EXISTS `enemigos_activos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `jugador_id` int NOT NULL,
  `oleada_id` int NOT NULL,
  `enemigo_catalogo_id` int NOT NULL,
  `vida_actual` int NOT NULL,
  `posicion` int NOT NULL COMMENT 'Posición en el grid o fuera (-1 para fuera del grid)',
  `esta_muerto` tinyint(1) DEFAULT '0',
  `fecha_spawn` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `oleada_id` (`oleada_id`),
  KEY `enemigo_catalogo_id` (`enemigo_catalogo_id`),
  KEY `idx_jugador_oleada` (`jugador_id`,`oleada_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `enemigos_catalogo`
--

DROP TABLE IF EXISTS `enemigos_catalogo`;
CREATE TABLE IF NOT EXISTS `enemigos_catalogo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `tipo` enum('goblin','orco','troll','dragon','esqueleto') NOT NULL,
  `nivel` int NOT NULL,
  `vida` int NOT NULL,
  `ataque` int NOT NULL,
  `defensa` int NOT NULL,
  `recompensa_oro` int NOT NULL,
  `descripcion` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `enemigos_catalogo`
--

INSERT INTO `enemigos_catalogo` (`id`, `nombre`, `tipo`, `nivel`, `vida`, `ataque`, `defensa`, `recompensa_oro`, `descripcion`) VALUES
(1, 'Goblin Explorador', 'goblin', 1, 30, 5, 0, 10, 'Débil pero numeroso'),
(2, 'Goblin Guerrero', 'goblin', 2, 50, 8, 0, 15, 'Más fuerte que el explorador'),
(3, 'Orco Guerrero', 'orco', 3, 100, 15, 0, 30, 'Fuerte y resistente'),
(4, 'Orco Berserker', 'orco', 4, 120, 20, 0, 40, 'Ataca con furia descontrolada'),
(5, 'Troll de Piedra', 'troll', 5, 200, 25, 0, 60, 'Piel dura como roca'),
(6, 'Troll Gigante', 'troll', 6, 250, 30, 0, 80, 'Enorme y devastador'),
(7, 'Esqueleto Guerrero', 'esqueleto', 7, 150, 35, 0, 70, 'No muerto implacable'),
(8, 'Dragón Joven', 'dragon', 8, 400, 45, 0, 150, 'Escupe fuego mortal'),
(9, 'Dragón Ancestral', 'dragon', 10, 800, 60, 0, 300, 'Rey de los dragones');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estado_oleadas`
--

DROP TABLE IF EXISTS `estado_oleadas`;
CREATE TABLE IF NOT EXISTS `estado_oleadas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `jugador_id` int NOT NULL,
  `oleada_actual` int DEFAULT '1',
  `proxima_oleada_tiempo` timestamp NULL DEFAULT NULL,
  `oleada_en_curso` tinyint(1) DEFAULT '0',
  `tiempo_alerta` timestamp NULL DEFAULT NULL COMMENT 'Cuando mostrar alerta de 1 minuto',
  `ultima_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_jugador` (`jugador_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `jugadores`
--

DROP TABLE IF EXISTS `jugadores`;
CREATE TABLE IF NOT EXISTS `jugadores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nivel_ayuntamiento` int DEFAULT '1',
  `limite_tropas` int DEFAULT '10',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ultima_conexion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `oleadas`
--

DROP TABLE IF EXISTS `oleadas`;
CREATE TABLE IF NOT EXISTS `oleadas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `jugador_id` int NOT NULL,
  `oleada_numero` int NOT NULL,
  `enemigos_derrotados` int DEFAULT '0',
  `oro_ganado` int DEFAULT '0',
  `completada` tinyint(1) DEFAULT '0',
  `fecha_inicio` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_finalizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `jugador_id` (`jugador_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recursos_jugador`
--

DROP TABLE IF EXISTS `recursos_jugador`;
CREATE TABLE IF NOT EXISTS `recursos_jugador` (
  `id` int NOT NULL AUTO_INCREMENT,
  `jugador_id` int NOT NULL,
  `oro` int DEFAULT '500',
  `madera` int DEFAULT '500',
  `piedra` int DEFAULT '500',
  `comida` int DEFAULT '300',
  `ultima_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `jugador_id` (`jugador_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `unidades_catalogo`
--

DROP TABLE IF EXISTS `unidades_catalogo`;
CREATE TABLE IF NOT EXISTS `unidades_catalogo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `tipo` enum('elfo','arquero','phoenix','mago') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `ataque` int NOT NULL,
  `vida` int NOT NULL,
  `costo_oro` int NOT NULL,
  `costo_comida` int NOT NULL,
  `tiempo_entrenamiento` int NOT NULL COMMENT 'en segundos',
  `descripcion` text,
  `nivel_cuartel_requerido` int DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `unidades_catalogo`
--

INSERT INTO `unidades_catalogo` (`id`, `nombre`, `tipo`, `ataque`, `vida`, `costo_oro`, `costo_comida`, `tiempo_entrenamiento`, `descripcion`, `nivel_cuartel_requerido`) VALUES
(1, 'Elfo', 'elfo', 10, 50, 50, 20, 30, 'Guerreros ágiles y sabios del bosque.', 1),
(2, 'Arquero', 'arquero', 15, 40, 80, 30, 45, 'Ataca a distancia con gran precisión', 2),
(3, 'Mago', 'mago', 25, 60, 150, 50, 90, 'Causa daño mágico devastador', 3),
(4, 'Phoenix', 'phoenix', 30, 100, 250, 150, 120, 'Una tropa de élite nacida del fuego eterno.', 4);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `unidades_jugador`
--

DROP TABLE IF EXISTS `unidades_jugador`;
CREATE TABLE IF NOT EXISTS `unidades_jugador` (
  `id` int NOT NULL AUTO_INCREMENT,
  `jugador_id` int NOT NULL,
  `unidad_catalogo_id` int NOT NULL,
  `cantidad` int DEFAULT '0',
  `en_entrenamiento` int DEFAULT '0',
  `tiempo_finalizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `jugador_id` (`jugador_id`),
  KEY `unidad_catalogo_id` (`unidad_catalogo_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `cola_entrenamiento`
--
ALTER TABLE `cola_entrenamiento`
  ADD CONSTRAINT `cola_entrenamiento_ibfk_1` FOREIGN KEY (`jugador_id`) REFERENCES `jugadores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cola_entrenamiento_ibfk_2` FOREIGN KEY (`unidad_catalogo_id`) REFERENCES `unidades_catalogo` (`id`);

--
-- Filtros para la tabla `edificios_jugador`
--
ALTER TABLE `edificios_jugador`
  ADD CONSTRAINT `edificios_jugador_ibfk_1` FOREIGN KEY (`jugador_id`) REFERENCES `jugadores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `edificios_jugador_ibfk_2` FOREIGN KEY (`edificio_catalogo_id`) REFERENCES `edificios_catalogo` (`id`);

--
-- Filtros para la tabla `edificios_niveles`
--
ALTER TABLE `edificios_niveles`
  ADD CONSTRAINT `edificios_niveles_ibfk_1` FOREIGN KEY (`edificio_catalogo_id`) REFERENCES `edificios_catalogo` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `enemigos_activos`
--
ALTER TABLE `enemigos_activos`
  ADD CONSTRAINT `enemigos_activos_ibfk_1` FOREIGN KEY (`jugador_id`) REFERENCES `jugadores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enemigos_activos_ibfk_2` FOREIGN KEY (`oleada_id`) REFERENCES `oleadas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enemigos_activos_ibfk_3` FOREIGN KEY (`enemigo_catalogo_id`) REFERENCES `enemigos_catalogo` (`id`);

--
-- Filtros para la tabla `estado_oleadas`
--
ALTER TABLE `estado_oleadas`
  ADD CONSTRAINT `estado_oleadas_ibfk_1` FOREIGN KEY (`jugador_id`) REFERENCES `jugadores` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `oleadas`
--
ALTER TABLE `oleadas`
  ADD CONSTRAINT `oleadas_ibfk_1` FOREIGN KEY (`jugador_id`) REFERENCES `jugadores` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `recursos_jugador`
--
ALTER TABLE `recursos_jugador`
  ADD CONSTRAINT `recursos_jugador_ibfk_1` FOREIGN KEY (`jugador_id`) REFERENCES `jugadores` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `unidades_jugador`
--
ALTER TABLE `unidades_jugador`
  ADD CONSTRAINT `unidades_jugador_ibfk_1` FOREIGN KEY (`jugador_id`) REFERENCES `jugadores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `unidades_jugador_ibfk_2` FOREIGN KEY (`unidad_catalogo_id`) REFERENCES `unidades_catalogo` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
