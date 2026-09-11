-- =========================================================
-- BASE DE DATOS COMPLETA: lym
-- SISTEMA LYM - TIENDA Y CRM
-- COMPATIBLE CON MARIADB Y MYSQL (XAMPP)
-- =========================================================

CREATE DATABASE IF NOT EXISTS `lym` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `lym`;

-- --------------------------------------------------------
-- TABLA: usuarios
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('cliente', 'admin') NOT NULL DEFAULT 'cliente',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuarios_username` (`username`),
  UNIQUE KEY `uq_usuarios_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- TABLA: clientes
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `clientes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` INT(11) DEFAULT NULL,
  `nombre` VARCHAR(150) NOT NULL,
  `correo` VARCHAR(150) NOT NULL,
  `telefono` VARCHAR(30) DEFAULT NULL,
  `empresa` VARCHAR(150) DEFAULT NULL,
  `estado` ENUM('activo', 'inactivo', 'prospecto') NOT NULL DEFAULT 'activo',
  `etapa_crm` ENUM('Prospecto', 'Contacto', 'Cotizando', 'Negociación', 'Activo', 'Frecuente', 'Inactivo') NOT NULL DEFAULT 'Prospecto',
  `fecha_registro` DATETIME DEFAULT CURRENT_TIMESTAMP(),
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `fk_cliente_usuario` (`usuario_id`),
  KEY `idx_clientes_estado` (`estado`),
  KEY `idx_clientes_etapa` (`etapa_crm`),
  CONSTRAINT `fk_clientes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- TABLA: productos
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `productos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `category` VARCHAR(100) NOT NULL,
  `features` TEXT DEFAULT NULL,
  `image_icon` VARCHAR(255) DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `idx_productos_categoria` (`category`),
  KEY `idx_productos_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- TABLA: paquetes
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `paquetes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `items` TEXT NOT NULL,
  `featured` TINYINT(1) DEFAULT 0,
  `active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- TABLA: pedidos
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pedidos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` INT(11) NOT NULL,
  `fecha_pedido` DATETIME DEFAULT CURRENT_TIMESTAMP(),
  `estado` ENUM('pendiente','confirmado','en_proceso','listo','enviado','entregado','cancelado') NOT NULL DEFAULT 'pendiente',
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `envio` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `metodo_pago` ENUM('pendiente','paypal','tarjeta','efectivo') DEFAULT 'pendiente',
  `notas` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `fk_pedidos_cliente` (`cliente_id`),
  CONSTRAINT `fk_pedidos_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- TABLA: detalle_pedido
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `detalle_pedido` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `pedido_id` INT(11) NOT NULL,
  `producto_id` INT(11) DEFAULT NULL,
  `paquete_id` INT(11) DEFAULT NULL,
  `nombre_producto` VARCHAR(150) NOT NULL,
  `cantidad` INT(11) NOT NULL DEFAULT 1,
  `precio_unitario` DECIMAL(10,2) NOT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `fk_detalle_pedido` (`pedido_id`),
  KEY `fk_detalle_producto` (`producto_id`),
  KEY `fk_detalle_paquete` (`paquete_id`),
  CONSTRAINT `fk_detalle_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_detalle_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_detalle_paquete` FOREIGN KEY (`paquete_id`) REFERENCES `paquetes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- TABLA: pagos
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pagos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `pedido_id` INT(11) NOT NULL,
  `metodo` ENUM('paypal','tarjeta','efectivo') NOT NULL,
  `referencia` VARCHAR(255) DEFAULT NULL,
  `monto` DECIMAL(10,2) NOT NULL,
  `estado` ENUM('pendiente','aprobado','rechazado','reembolsado') NOT NULL DEFAULT 'pendiente',
  `fecha_pago` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `fk_pagos_pedido` (`pedido_id`),
  CONSTRAINT `fk_pagos_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- TABLA: interacciones
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `interacciones` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` INT(11) NOT NULL,
  `usuario_id` INT(11) DEFAULT NULL,
  `tipo` ENUM('llamada','correo','reunion','whatsapp','nota') NOT NULL DEFAULT 'nota',
  `descripcion` TEXT NOT NULL,
  `fecha` DATETIME DEFAULT CURRENT_TIMESTAMP(),
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `fk_interacciones_cliente` (`cliente_id`),
  KEY `fk_interacciones_usuario` (`usuario_id`),
  CONSTRAINT `fk_interacciones_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_interacciones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- TABLA: evaluaciones_crm
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `evaluaciones_crm` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` INT(11) NOT NULL,
  `puntuacion_satisfaccion` INT(11) DEFAULT NULL,
  `comentarios` TEXT DEFAULT NULL,
  `fecha_evaluacion` DATETIME DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `fk_evaluaciones_cliente` (`cliente_id`),
  CONSTRAINT `fk_evaluaciones_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- TABLA: solicitudes_descuento
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `solicitudes_descuento` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` INT(11) NOT NULL,
  `trabajador_id` INT(11) NOT NULL,
  `porcentaje` INT(11) NOT NULL,
  `codigo_cupon` VARCHAR(50) DEFAULT NULL,
  `motivo` TEXT NOT NULL,
  `estado` ENUM('pendiente', 'aprobado', 'rechazado') NOT NULL DEFAULT 'pendiente',
  `admin_id` INT(11) DEFAULT NULL,
  `comentario_admin` TEXT DEFAULT NULL,
  `fecha_solicitud` DATETIME DEFAULT CURRENT_TIMESTAMP(),
  `fecha_resolucion` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `fk_solicitud_cliente` (`cliente_id`),
  KEY `fk_solicitud_trabajador` (`trabajador_id`),
  KEY `fk_solicitud_admin` (`admin_id`),
  CONSTRAINT `fk_solicitud_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_solicitud_trabajador` FOREIGN KEY (`trabajador_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_solicitud_admin` FOREIGN KEY (`admin_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- DATOS INICIALES (DATOS DE SEMILLA)
-- --------------------------------------------------------

-- 1. Usuarios predeterminados (admin: juanlalo / 123456)
INSERT INTO `usuarios` (`id`, `username`, `email`, `password_hash`, `role`) VALUES
(1, 'admin', 'admin@lym.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
(2, 'juanlalo', 'juanlalo@gmail.com', '$2y$10$HRMHGITYItX0832usZhCnurj9xPoc55C.535CTeLM7b5kV4/AMvO2', 'admin'),
(3, 'cliente_demo', 'cliente@gmail.com', '$2y$10$rgfL3xphzjFa5hnLjxjhrOMUCZZubIdwM.Y5D6X.FRO5iC6FuIOGW', 'cliente')
ON DUPLICATE KEY UPDATE `password_hash` = VALUES(`password_hash`), `role` = VALUES(`role`);

-- 2. Clientes
INSERT INTO `clientes` (`id`, `usuario_id`, `nombre`, `correo`, `telefono`, `empresa`, `estado`, `etapa_crm`) VALUES
(1, 2, 'Juan Lalo (Admin)', 'juanlalo@gmail.com', '4493620191', 'LYM Diseño', 'activo', 'Activo'),
(2, 3, 'Cliente Demo', 'cliente@gmail.com', '5551234567', 'Particular', 'activo', 'Prospecto')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- 3. Productos
INSERT INTO `productos` (`id`, `name`, `description`, `price`, `category`, `features`, `image_icon`, `active`) VALUES
(1, 'Taza Estándar', 'Taza estándar de cerámica personalizada', 85.00, 'tazas', 'Cerámica de alta calidad|Resistente a microondas|Diseño full color', 'fas fa-mug-hot', 1),
(2, 'Tapete Afelpado', 'Tapete blanco afelpado suave para interior', 300.00, 'tapetes', 'Reverso antideslizante|Medida 34x58 cm|Estampado lavable', 'fas fa-home', 1),
(3, 'Sudaderas Custom', 'Sudaderas personalizadas de algodón peinado', 425.00, 'sudaderas', '100% Algodón|Capucha y cangurera|Impresión DTF duradera', 'fas fa-tshirt', 1),
(4, 'Camisas Uniforme', 'Camisas de uniforme gabardina profesional', 195.00, 'uniformes', 'Gabardina peinada|Bordado personalizado|Variedad de tallas', 'fas fa-user-tie', 1)
ON DUPLICATE KEY UPDATE `price` = VALUES(`price`);

-- 4. Paquetes
INSERT INTO `paquetes` (`id`, `name`, `description`, `price`, `items`, `featured`, `active`) VALUES
(1, 'Paquete Básico', 'Paquete básico con productos esenciales', 250.00, '["1 Taza estándar", "1 Llavero de acero", "1 Rompecabezas carta"]', 0, 1),
(2, 'Paquete Viajero', 'Paquete ideal para viajes y trabajo', 320.00, '["1 Bolsa ecológica", "2 Llaveros acero", "1 Cojín 20x30"]', 1, 1),
(3, 'Paquete Deportivo', 'Paquete para deportistas', 575.00, '["1 Playera Dryfit", "1 Gorra combinada", "1 Vaso alto acero"]', 0, 1),
(4, 'Paquete Potterhead', 'Paquete temático especial', 635.00, '["1 Playera Dryfit", "1 Termo cafetero", "1 Llavero metálico"]', 0, 1)
ON DUPLICATE KEY UPDATE `price` = VALUES(`price`);

-- 5. Interacción inicial
INSERT INTO `interacciones` (`id`, `cliente_id`, `usuario_id`, `tipo`, `descripcion`, `fecha`) VALUES
(1, 1, 2, 'llamada', 'Contacto inicial y bienvenida al sistema CRM de LYM.', NOW())
ON DUPLICATE KEY UPDATE `descripcion` = VALUES(`descripcion`);
