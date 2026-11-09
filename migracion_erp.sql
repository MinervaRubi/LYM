-- =====================================================
-- LYM · Migración para Módulo ERP (Órdenes, Detalles y Madurez)
-- Base de datos: lym
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- 1. Tabla de Órdenes ERP
CREATE TABLE IF NOT EXISTS `erp_ordenes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `folio` varchar(30) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `estado` enum('Pendiente','Procesando','Procesada','Cancelada') NOT NULL DEFAULT 'Procesando',
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notas` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_erp_orden_folio` (`folio`),
  KEY `fk_erp_orden_cliente` (`cliente_id`),
  KEY `fk_erp_orden_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabla de Detalles de Órdenes ERP
CREATE TABLE IF NOT EXISTS `erp_orden_detalles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `orden_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `nombre_producto` varchar(150) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `precio_unitario` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_erp_detalles_orden` (`orden_id`),
  KEY `fk_erp_detalles_producto` (`producto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Asegurar columnas y estructura de erp_estado
CREATE TABLE IF NOT EXISTS `erp_estado` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nivel` varchar(50) NOT NULL DEFAULT 'Básico',
  `porcentaje` int(11) NOT NULL DEFAULT 25,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `erp_estado` MODIFY COLUMN `nivel` varchar(50) NOT NULL DEFAULT 'Básico';
ALTER TABLE `erp_estado` ADD COLUMN IF NOT EXISTS `porcentaje` int(11) NOT NULL DEFAULT 25 AFTER `nivel`;

-- 4. Insertar registro inicial de erp_estado si no existe
INSERT INTO `erp_estado` (`id`, `nivel`, `porcentaje`) 
VALUES (1, 'Optimizado', 100) 
ON DUPLICATE KEY UPDATE `porcentaje` = IFNULL(`porcentaje`, 100);

SET FOREIGN_KEY_CHECKS=1;
