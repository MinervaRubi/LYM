CREATE DATABASE IF NOT EXISTS `LYM` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `LYM`;

CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('cliente', 'admin') NOT NULL DEFAULT 'cliente',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username_unique` (`username`),
  UNIQUE KEY `email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `clientes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `usuario_id` INT NULL,
  `nombre` VARCHAR(120) NOT NULL,
  `correo` VARCHAR(150) NOT NULL,
  `telefono` VARCHAR(30) DEFAULT NULL,
  `estado` ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
  `etapa_crm` ENUM('Prospecto', 'Contacto', 'Cotización', 'Cliente') NOT NULL DEFAULT 'Prospecto',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `correo_unique` (`correo`),
  KEY `usuario_id_idx` (`usuario_id`),
  CONSTRAINT `fk_clientes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `productos` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT,
  `price` DECIMAL(10,2) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `features` JSON DEFAULT NULL,
  `image_icon` VARCHAR(100) DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pedidos` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `cliente_id` INT NOT NULL,
  `total` DECIMAL(10,2) NOT NULL,
  `estado` ENUM('pendiente', 'pagado', 'entregado', 'cancelado') NOT NULL DEFAULT 'pendiente',
  `fecha` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `cliente_id_idx` (`cliente_id`),
  CONSTRAINT `fk_pedidos_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `detalle_pedido` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `pedido_id` INT NOT NULL,
  `producto_id` INT NOT NULL,
  `cantidad` INT NOT NULL,
  `precio_unitario` DECIMAL(10,2) NOT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `pedido_id_idx` (`pedido_id`),
  KEY `producto_id_idx` (`producto_id`),
  CONSTRAINT `fk_detalle_pedido_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_detalle_pedido_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pagos` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `pedido_id` INT NOT NULL,
  `monto` DECIMAL(10,2) NOT NULL,
  `metodo` ENUM('tarjeta', 'transferencia', 'efectivo') NOT NULL DEFAULT 'tarjeta',
  `fecha` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estado` ENUM('aprobado', 'pendiente', 'rechazado') NOT NULL DEFAULT 'aprobado',
  PRIMARY KEY (`id`),
  KEY `pedido_id_idx` (`pedido_id`),
  CONSTRAINT `fk_pagos_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `crm_interacciones` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `cliente_id` INT NOT NULL,
  `tipo` VARCHAR(50) NOT NULL,
  `comentario` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `cliente_id_idx` (`cliente_id`),
  CONSTRAINT `fk_crm_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `usuarios` (`username`, `email`, `password_hash`, `role`) VALUES
  ('admin', 'admin@lym.com', '$2y$10$rNrJ9UNlkOyOOGWLgjzHz.lmkOCGza3tjXyWOK2LFs/YbyZE5c3qi', 'admin'),
  ('cliente', 'cliente@lym.com', '$2y$10$rNrJ9UNlkOyOOGWLgjzHz.lmkOCGza3tjXyWOK2LFs/YbyZE5c3qi', 'cliente')
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`);

INSERT INTO `clientes` (`usuario_id`, `nombre`, `correo`, `telefono`, `estado`, `etapa_crm`) VALUES
  ((SELECT `id` FROM `usuarios` WHERE `username` = 'admin' LIMIT 1), 'Ana García', 'ana@correo.com', '5551112233', 'activo', 'Cliente'),
  ((SELECT `id` FROM `usuarios` WHERE `username` = 'cliente' LIMIT 1), 'Luis Torres', 'luis@correo.com', '5554445566', 'activo', 'Cotización'),
  (NULL, 'Sofía Ramírez', 'sofia@correo.com', '5557778899', 'inactivo', 'Prospecto')
ON DUPLICATE KEY UPDATE `correo` = VALUES(`correo`);

INSERT INTO `productos` (`id`, `name`, `description`, `price`, `category`, `features`, `image_icon`, `active`) VALUES
  (1, 'Taza personalizada', 'Taza con nombre y diseño personalizado', 180.00, 'hogar', JSON_ARRAY('Personalizable', 'Cerámica premium'), 'fa-mug-hot', 1),
  (2, 'Sudadera estampada', 'Sudadera con estampado de alta calidad', 420.00, 'ropa', JSON_ARRAY('Algodón', 'Talla única'), 'fa-tshirt', 1),
  (3, 'Gorra con logo', 'Gorra con bordado premium', 260.00, 'accesorios', JSON_ARRAY('Bordado', 'Ajuste cómodo'), 'fa-hat-cowboy', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `pedidos` (`id`, `cliente_id`, `total`, `estado`, `fecha`) VALUES
  (1, (SELECT `id` FROM `clientes` WHERE `correo` = 'ana@correo.com' LIMIT 1), 400.00, 'pagado', '2026-09-01 10:15:00'),
  (2, (SELECT `id` FROM `clientes` WHERE `correo` = 'luis@correo.com' LIMIT 1), 680.00, 'entregado', '2026-09-02 12:45:00')
ON DUPLICATE KEY UPDATE `total` = VALUES(`total`);

INSERT INTO `detalle_pedido` (`id`, `pedido_id`, `producto_id`, `cantidad`, `precio_unitario`, `subtotal`) VALUES
  (1, 1, 1, 2, 180.00, 360.00),
  (2, 2, 2, 1, 420.00, 420.00),
  (3, 2, 3, 1, 260.00, 260.00)
ON DUPLICATE KEY UPDATE `subtotal` = VALUES(`subtotal`);

INSERT INTO `pagos` (`id`, `pedido_id`, `monto`, `metodo`, `fecha`, `estado`) VALUES
  (1, 1, 400.00, 'tarjeta', '2026-09-01 10:20:00', 'aprobado'),
  (2, 2, 680.00, 'transferencia', '2026-09-02 12:50:00', 'aprobado')
ON DUPLICATE KEY UPDATE `monto` = VALUES(`monto`);

INSERT INTO `crm_interacciones` (`cliente_id`, `tipo`, `comentario`) VALUES
  ((SELECT `id` FROM `clientes` WHERE `correo` = 'ana@correo.com' LIMIT 1), 'Llamada', 'Cliente confirmado para entregar pedido final.'),
  ((SELECT `id` FROM `clientes` WHERE `correo` = 'luis@correo.com' LIMIT 1), 'Correo', 'Se envió cotización final con descuento del 10%');
