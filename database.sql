CREATE DATABASE IF NOT EXISTS `nikenza_store` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `nikenza_store`;

CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('cliente', 'admin') NOT NULL DEFAULT 'cliente',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_usuarios_username` (`username`),
    UNIQUE KEY `uq_usuarios_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `clientes` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `usuario_id` BIGINT NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `correo` VARCHAR(150) NOT NULL,
    `telefono` VARCHAR(30) DEFAULT NULL,
    `estado` ENUM('activo', 'inactivo', 'prospecto') NOT NULL DEFAULT 'activo',
    `etapa_crm` ENUM('Prospecto', 'Contacto', 'Cotizando', 'Negociación', 'Cliente') NOT NULL DEFAULT 'Prospecto',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_clientes_usuario` (`usuario_id`),
    KEY `idx_clientes_estado` (`estado`),
    KEY `idx_clientes_etapa` (`etapa_crm`),
    CONSTRAINT `fk_clientes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `productos` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `category` VARCHAR(100) NOT NULL,
    `features` JSON DEFAULT NULL,
    `image_icon` VARCHAR(255) DEFAULT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_productos_categoria` (`category`),
    KEY `idx_productos_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `paquetes` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `items` JSON DEFAULT NULL,
    `featured` TINYINT(1) NOT NULL DEFAULT 0,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pedidos` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `cliente_id` BIGINT NOT NULL,
    `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `estado` ENUM('pendiente', 'pagado', 'cancelado') NOT NULL DEFAULT 'pendiente',
    `observaciones` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pedidos_cliente` (`cliente_id`),
    CONSTRAINT `fk_pedidos_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `detalle_pedido` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `pedido_id` BIGINT NOT NULL,
    `producto_id` BIGINT DEFAULT NULL,
    `cantidad` INT NOT NULL DEFAULT 1,
    `precio_unitario` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_detalle_pedido_pedido` (`pedido_id`),
    KEY `idx_detalle_pedido_producto` (`producto_id`),
    CONSTRAINT `fk_detalle_pedido_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_detalle_pedido_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pagos` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `pedido_id` BIGINT NOT NULL,
    `metodo` VARCHAR(50) NOT NULL,
    `monto` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `estatus` ENUM('pendiente', 'aprobado', 'rechazado') NOT NULL DEFAULT 'pendiente',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pagos_pedido` (`pedido_id`),
    CONSTRAINT `fk_pagos_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `interacciones` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `cliente_id` BIGINT NOT NULL,
    `tipo` ENUM('llamada', 'correo', 'whatsapp', 'reunion', 'seguimiento') NOT NULL,
    `descripcion` TEXT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_interacciones_cliente` (`cliente_id`),
    CONSTRAINT `fk_interacciones_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `evaluaciones_crm` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `cliente_id` BIGINT NOT NULL,
    `calificacion` TINYINT NOT NULL DEFAULT 0,
    `comentario` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_evaluaciones_cliente` (`cliente_id`),
    CONSTRAINT `fk_evaluaciones_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `productos` (`id`, `name`, `description`, `price`, `category`, `features`, `image_icon`, `active`) VALUES
(1, 'Taza Estándar', 'Taza estándar de cerámica personalizada', 85.00, 'tazas', JSON_ARRAY('Taza estándar de cerámica - $85', 'Taza con asa de color - $95', 'Taza mágica - $120', 'Par de tazas corazón - $215', 'Taza recta grande 444 ml - $130'), 'fas fa-mug-hot', 1),
(2, 'Tapete Afelpado', 'Tapete blanco afelpado suave para interior', 300.00, 'tapetes', JSON_ARRAY('Tapete blanco afelpado suave', 'Para interior, revés antideslizante', 'Medida: 34 x 58 cm', 'Impresión máxima: 30 x 43 cm', 'Diseño personalizado'), 'fas fa-home', 1),
(3, 'Sudaderas', 'Sudaderas personalizadas de algodón', 425.00, 'sudaderas', JSON_ARRAY('Sudadera sencilla - $425', 'Sudadera con gorra - $500', 'Algodón, sencilla, cerrada', 'Cuello redondo', 'Diseño personalizado'), 'fas fa-tshirt', 1),
(4, 'Camisas de Uniforme', 'Camisas de uniforme en gabardina peinada', 195.00, 'uniformes', JSON_ARRAY('Gabardina peinada, muy durable', 'Para dama y caballero', 'Tallas: CH, M, G, EG, 2XL, 3XL, 4XL', 'Frente: $195 - $535', 'Frente y vuelta: $510 - $550'), 'fas fa-user-tie', 1)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `description` = VALUES(`description`),
    `features` = VALUES(`features`);

INSERT INTO `paquetes` (`id`, `name`, `description`, `price`, `items`, `featured`, `active`) VALUES
(1, 'Paquete Básico', 'Paquete básico con productos esenciales', 250.00, JSON_ARRAY('1 Taza estándar', '1 Llavero de acero con impresión frente y vuelta', '1 Rompecabezas tamaño carta', 'Diseño personalizado a tu elección'), 0, 1),
(2, 'Paquete Viajero', 'Paquete ideal para viajeros', 320.00, JSON_ARRAY('1 Bolsa ecológica chica', '2 Llaveros de acero con impresión frente y vuelta', '1 Cojín 20 x 30 cm', 'Diseño personalizado a tu elección'), 1, 1),
(3, 'Paquete Deportivo', 'Paquete para deportistas', 575.00, JSON_ARRAY('1 Playera deportiva Dryfit impresa al frente', '1 Gorra combinada (color a elegir)', '1 Vaso alto de acero (blanco o plata)', 'Diseño personalizado a tu elección'), 0, 1),
(4, 'Paquete Potterhead', 'Paquete temático de Harry Potter', 635.00, JSON_ARRAY('1 Playera deportiva Dryfit con diseño de Quidditch', '1 Termo cafetero con asa y escudo de Hogwarts', '1 Llavero de acero con impresión frente y vuelta', 'Diseño de tu casa de Hogwarts y fecha de cumpleaños'), 0, 1)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `description` = VALUES(`description`),
    `items` = VALUES(`items`);

INSERT INTO `usuarios` (`username`, `email`, `password_hash`, `role`)
SELECT 'admin', 'admin@lym.com', '$2y$10$z2bsWP7coKfP0O3cG9c72emp5gJaW83iLJHGX461y34s6M2PnLiMW', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM `usuarios` WHERE `username` = 'admin');

INSERT INTO `clientes` (`usuario_id`, `nombre`, `correo`, `telefono`, `estado`, `etapa_crm`)
SELECT u.id, 'Administrador', u.email, '', 'activo', 'Cliente'
FROM `usuarios` u
WHERE u.username = 'admin'
AND NOT EXISTS (SELECT 1 FROM `clientes` c WHERE c.usuario_id = u.id);
