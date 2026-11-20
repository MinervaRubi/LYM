-- =====================================================
-- LYM · Migración para Etapa 4: Carrito y sus Agregados
-- (Monedero, Tickets, Carrito, Impuestos y Promociones)
-- Base de datos: lym
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- 1. Ampliar tabla clientes con saldo_monedero y datos de entrega
ALTER TABLE `clientes` 
ADD COLUMN IF NOT EXISTS `saldo_monedero` DECIMAL(10,2) NOT NULL DEFAULT 1200.00 AFTER `estado`,
ADD COLUMN IF NOT EXISTS `direccion` TEXT NULL AFTER `telefono`;

-- Actualizar saldo inicial de prueba a $1200 para clientes existentes si su saldo es 0
UPDATE `clientes` SET `saldo_monedero` = 1200.00 WHERE `saldo_monedero` = 0.00 OR `saldo_monedero` IS NULL;

-- 2. Historial de movimientos del Monedero Electrónico
CREATE TABLE IF NOT EXISTS `monedero_movimientos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` INT(11) NOT NULL,
  `tipo` ENUM('recarga', 'compra', 'ajuste') NOT NULL,
  `monto` DECIMAL(10,2) NOT NULL,
  `saldo_resultante` DECIMAL(10,2) NOT NULL,
  `concepto` VARCHAR(255) NOT NULL,
  `referencia` VARCHAR(100) NULL,
  `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_monedero_cliente` (`cliente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabla de Impuestos (IVA configurable)
CREATE TABLE IF NOT EXISTS `impuestos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(50) NOT NULL,
  `porcentaje` DECIMAL(5,2) NOT NULL DEFAULT 16.00,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `impuestos` (`id`, `nombre`, `porcentaje`, `activo`) 
VALUES (1, 'IVA', 16.00, 1)
ON DUPLICATE KEY UPDATE `porcentaje` = 16.00, `activo` = 1;

-- 4. Tabla de Promociones y Descuentos Comerciales
CREATE TABLE IF NOT EXISTS `promociones` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(150) NOT NULL,
  `descripcion` TEXT NULL,
  `tipo_descuento` ENUM('porcentaje', 'monto') NOT NULL DEFAULT 'porcentaje',
  `valor` DECIMAL(10,2) NOT NULL DEFAULT 10.00,
  `codigo` VARCHAR(50) NOT NULL,
  `fecha_inicio` DATE NULL,
  `fecha_fin` DATE NULL,
  `estado` ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_promocion_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `promociones` (`id`, `nombre`, `descripcion`, `tipo_descuento`, `valor`, `codigo`, `estado`)
VALUES 
(1, 'Descuento Bienvenida', 'Descuento del 10% en tu primera compra en línea', 'porcentaje', 10.00, 'BIENVENIDO10', 'activo'),
(2, 'Promoción Artesanal', 'Descuento especial de $50 pesos en compras superiores a $400', 'monto', 50.00, 'ARTESANIA50', 'activo')
ON DUPLICATE KEY UPDATE `estado` = 'activo';

-- 5. Tabla de Carrito de Compras Persistente
CREATE TABLE IF NOT EXISTS `carrito` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` INT(11) NULL,
  `session_token` VARCHAR(100) NULL,
  `estado` ENUM('activo', 'comprado', 'abandonado') NOT NULL DEFAULT 'activo',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_carrito_cliente` (`cliente_id`),
  KEY `idx_carrito_token` (`session_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Detalles del Carrito de Compras
CREATE TABLE IF NOT EXISTS `carrito_detalle` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `carrito_id` INT(11) NOT NULL,
  `producto_id` INT(11) NOT NULL,
  `cantidad` INT(11) NOT NULL DEFAULT 1,
  `precio_unitario` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_carrito_id` (`carrito_id`),
  KEY `fk_carrito_producto` (`producto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Tabla de Atención a Clientes (Tickets de Soporte e Incidencias)
CREATE TABLE IF NOT EXISTS `tickets_atencion` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `folio` VARCHAR(20) NOT NULL,
  `cliente_id` INT(11) NOT NULL,
  `asunto` VARCHAR(255) NOT NULL,
  `mensaje` TEXT NOT NULL,
  `estado` ENUM('En proceso', 'Respondido', 'Cerrado') NOT NULL DEFAULT 'En proceso',
  `respuesta` TEXT NULL,
  `usuario_respuesta_id` INT(11) NULL,
  `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_respuesta` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ticket_folio` (`folio`),
  KEY `fk_ticket_cliente` (`cliente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar tickets iniciales de muestra basados en la imagen (T-103, T-104, T-105)
INSERT INTO `tickets_atencion` (`id`, `folio`, `cliente_id`, `asunto`, `mensaje`, `estado`, `respuesta`, `fecha`)
SELECT 1, 'T-105', c.id, 'Duda sobre mi pedido', 'Quisiera saber cuándo se enviará mi paquete artesanal.', 'En proceso', NULL, NOW() - INTERVAL 1 DAY
FROM clientes c LIMIT 1
ON DUPLICATE KEY UPDATE `folio` = VALUES(`folio`);

INSERT INTO `tickets_atencion` (`id`, `folio`, `cliente_id`, `asunto`, `mensaje`, `estado`, `respuesta`, `fecha`)
SELECT 2, 'T-104', c.id, 'Cambio de producto', 'Solicito cambio de talla en mi textil bordado.', 'Respondido', 'Estimado cliente, su cambio ha sido aprobado.', NOW() - INTERVAL 3 DAY
FROM clientes c LIMIT 1
ON DUPLICATE KEY UPDATE `folio` = VALUES(`folio`);

INSERT INTO `tickets_atencion` (`id`, `folio`, `cliente_id`, `asunto`, `mensaje`, `estado`, `respuesta`, `fecha`)
SELECT 3, 'T-103', c.id, 'Facturación', 'Requiero factura con CFDI de mi última compra.', 'Cerrado', 'Factura enviada a su correo registrado.', NOW() - INTERVAL 7 DAY
FROM clientes c LIMIT 1
ON DUPLICATE KEY UPDATE `folio` = VALUES(`folio`);

-- Insertar movimientos de muestra en el monedero basados en la imagen
INSERT INTO `monedero_movimientos` (`id`, `cliente_id`, `tipo`, `monto`, `saldo_resultante`, `concepto`, `referencia`, `fecha`)
SELECT 1, c.id, 'recarga', 200.00, 650.00, 'Recarga de saldo en tienda', 'REC-01', NOW() - INTERVAL 10 DAY
FROM clientes c LIMIT 1
ON DUPLICATE KEY UPDATE `concepto` = VALUES(`concepto`);

INSERT INTO `monedero_movimientos` (`id`, `cliente_id`, `tipo`, `monto`, `saldo_resultante`, `concepto`, `referencia`, `fecha`)
SELECT 2, c.id, 'compra', -450.00, 200.00, 'Compra (OR-00612)', 'OR-00612', NOW() - INTERVAL 6 DAY
FROM clientes c LIMIT 1
ON DUPLICATE KEY UPDATE `concepto` = VALUES(`concepto`);

INSERT INTO `monedero_movimientos` (`id`, `cliente_id`, `tipo`, `monto`, `saldo_resultante`, `concepto`, `referencia`, `fecha`)
SELECT 3, c.id, 'recarga', 1000.00, 1200.00, 'Recarga con tarjeta', 'REC-02', NOW() - INTERVAL 2 DAY
FROM clientes c LIMIT 1
ON DUPLICATE KEY UPDATE `concepto` = VALUES(`concepto`);

SET FOREIGN_KEY_CHECKS=1;