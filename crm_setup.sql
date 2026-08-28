-- Seleccionar la base de datos
USE `nikenza_store`;

-- --------------------------------------------------------
-- 1. Tabla de Clientes
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `clientes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(150) NOT NULL,
  `correo` VARCHAR(150) NOT NULL,
  `telefono` VARCHAR(30) DEFAULT NULL,
  `empresa` VARCHAR(150) DEFAULT NULL,
  `fecha_registro` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `estado` ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
  `etapa_crm` ENUM('Prospecto', 'Activo', 'Frecuente', 'Inactivo') NOT NULL DEFAULT 'Prospecto',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cliente_correo` (`correo`),
  KEY `idx_estado` (`estado`),
  KEY `idx_etapa_crm` (`etapa_crm`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. Tabla de Interacciones
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `interacciones` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` INT(11) NOT NULL,
  `usuario_id` INT(11) NOT NULL,
  `tipo` ENUM('llamada', 'correo', 'reunion') NOT NULL,
  `descripcion` TEXT NOT NULL,
  `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cliente_id` (`cliente_id`),
  KEY `idx_usuario_id` (`usuario_id`),
  CONSTRAINT `fk_interacciones_cliente` 
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_interacciones_usuario` 
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) 
    ON DELETE RESTRICT 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. Tabla de Evaluaciones CRM
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `evaluaciones_crm` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` INT(11) NOT NULL,
  `puntuacion_satisfaccion` INT(2) DEFAULT NULL,
  `comentarios` TEXT DEFAULT NULL,
  `fecha_evaluacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_evaluacion_cliente` (`cliente_id`),
  CONSTRAINT `fk_evaluaciones_cliente` 
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. Datos Iniciales de Prueba
-- --------------------------------------------------------
INSERT INTO `clientes` (`id`, `nombre`, `correo`, `telefono`, `empresa`, `estado`, `etapa_crm`) VALUES
(1, 'Juan Pérez Gómez', 'juan.perez@techsolutions.com', '4491234567', 'Tech Solutions SA de CV', 'activo', 'Prospecto'),
(2, 'María Fernández Torres', 'mfernandez@innovasoft.mx', '4499876543', 'InnovaSoft', 'activo', 'Activo'),
(3, 'Carlos Mendoza Ruiz', 'cmendoza@logisticamx.com', '4495551122', 'Logística Express', 'activo', 'Frecuente'),
(4, 'Laura Morales Silva', 'lmorales@consultoria.net', '4493334455', 'Morales Consultores', 'inactivo', 'Inactivo')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

INSERT INTO `interacciones` (`cliente_id`, `usuario_id`, `tipo`, `descripcion`, `fecha`) VALUES
(1, 1, 'llamada', 'Primer contacto con el prospecto para presentar catálogo de soluciones.', NOW() - INTERVAL 3 DAY),
(1, 1, 'correo', 'Envío de cotización formal de paquetes corporativos.', NOW() - INTERVAL 1 DAY),
(2, 1, 'reunion', 'Reunión de seguimiento para acordar fechas de entrega de uniformes.', NOW() - INTERVAL 2 DAY),
(3, 1, 'llamada', 'Llamada de postventa y validación de satisfacción del pedido.', NOW() - INTERVAL 4 HOUR);