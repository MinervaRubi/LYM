-- =====================================================
-- LYM · Migración NO destructiva (agrega lo que falta)
-- Crea tablas faltantes (SCM, comunidad, subastas, etc.) y
-- agrega columnas faltantes SIN borrar tus datos actuales.
-- Ejecutar en phpMyAdmin con la base `lym` seleccionada.
-- Se puede correr varias veces sin problema.
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- ---------- clientes ----------
CREATE TABLE IF NOT EXISTS `clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `nombre` varchar(150) NOT NULL,
  `correo` varchar(150) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `empresa` varchar(150) DEFAULT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `etapa_crm` enum('Prospecto','Activo','Frecuente','Inactivo') NOT NULL DEFAULT 'Prospecto',
  `fecha_registro` datetime DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ultimo_descuento_pct` varchar(20) DEFAULT NULL,
  `ultimo_descuento_estado` varchar(20) DEFAULT 'sin_descuento',
  PRIMARY KEY (`id`),
  UNIQUE KEY `correo` (`correo`),
  KEY `fk_cliente_usuario` (`usuario_id`),
  KEY `idx_clientes_estado` (`estado`),
  KEY `idx_clientes_etapa` (`etapa_crm`),
  CONSTRAINT `fk_cliente_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `clientes` ADD COLUMN IF NOT EXISTS `usuario_id` int(11) NULL DEFAULT NULL;
ALTER TABLE `clientes` ADD COLUMN IF NOT EXISTS `nombre` varchar(150) NOT NULL;
ALTER TABLE `clientes` ADD COLUMN IF NOT EXISTS `correo` varchar(150) NOT NULL;
ALTER TABLE `clientes` ADD COLUMN IF NOT EXISTS `telefono` varchar(30) NULL DEFAULT NULL;
ALTER TABLE `clientes` ADD COLUMN IF NOT EXISTS `empresa` varchar(150) NULL DEFAULT NULL;
ALTER TABLE `clientes` ADD COLUMN IF NOT EXISTS `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo';
ALTER TABLE `clientes` ADD COLUMN IF NOT EXISTS `etapa_crm` enum('Prospecto','Activo','Frecuente','Inactivo') NOT NULL DEFAULT 'Prospecto';
ALTER TABLE `clientes` ADD COLUMN IF NOT EXISTS `fecha_registro` datetime NULL DEFAULT current_timestamp();
ALTER TABLE `clientes` ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT current_timestamp();
ALTER TABLE `clientes` ADD COLUMN IF NOT EXISTS `updated_at` timestamp NOT NULL DEFAULT current_timestamp() on update current_timestamp();
ALTER TABLE `clientes` ADD COLUMN IF NOT EXISTS `ultimo_descuento_pct` varchar(20) NULL DEFAULT NULL;
ALTER TABLE `clientes` ADD COLUMN IF NOT EXISTS `ultimo_descuento_estado` varchar(20) NULL DEFAULT 'sin_descuento';

-- ---------- comunidad_comentarios ----------
CREATE TABLE IF NOT EXISTS `comunidad_comentarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `titulo` varchar(150) DEFAULT NULL,
  `texto` text NOT NULL,
  `likes` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_com_user` (`usuario_id`),
  CONSTRAINT `fk_com_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `comunidad_comentarios` ADD COLUMN IF NOT EXISTS `usuario_id` int(11) NULL DEFAULT NULL;
ALTER TABLE `comunidad_comentarios` ADD COLUMN IF NOT EXISTS `nombre` varchar(100) NOT NULL;
ALTER TABLE `comunidad_comentarios` ADD COLUMN IF NOT EXISTS `titulo` varchar(150) NULL DEFAULT NULL;
ALTER TABLE `comunidad_comentarios` ADD COLUMN IF NOT EXISTS `texto` text NOT NULL;
ALTER TABLE `comunidad_comentarios` ADD COLUMN IF NOT EXISTS `likes` int(11) NOT NULL DEFAULT 0;
ALTER TABLE `comunidad_comentarios` ADD COLUMN IF NOT EXISTS `created_at` datetime NULL DEFAULT current_timestamp();

-- ---------- detalle_pedido ----------
CREATE TABLE IF NOT EXISTS `detalle_pedido` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pedido_id` int(11) NOT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `paquete_id` int(11) DEFAULT NULL,
  `nombre_producto` varchar(150) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_detalle_producto` (`producto_id`),
  KEY `fk_detalle_paquete` (`paquete_id`),
  KEY `idx_detalle_pedido` (`pedido_id`),
  CONSTRAINT `fk_detalle_paquete` FOREIGN KEY (`paquete_id`) REFERENCES `paquetes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_detalle_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_detalle_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `detalle_pedido` ADD COLUMN IF NOT EXISTS `pedido_id` int(11) NOT NULL;
ALTER TABLE `detalle_pedido` ADD COLUMN IF NOT EXISTS `producto_id` int(11) NULL DEFAULT NULL;
ALTER TABLE `detalle_pedido` ADD COLUMN IF NOT EXISTS `paquete_id` int(11) NULL DEFAULT NULL;
ALTER TABLE `detalle_pedido` ADD COLUMN IF NOT EXISTS `nombre_producto` varchar(150) NOT NULL;
ALTER TABLE `detalle_pedido` ADD COLUMN IF NOT EXISTS `cantidad` int(11) NOT NULL DEFAULT 1;
ALTER TABLE `detalle_pedido` ADD COLUMN IF NOT EXISTS `precio_unitario` decimal(10,2) NOT NULL;
ALTER TABLE `detalle_pedido` ADD COLUMN IF NOT EXISTS `subtotal` decimal(10,2) NOT NULL;
ALTER TABLE `detalle_pedido` ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT current_timestamp();

-- ---------- evaluaciones_crm ----------
CREATE TABLE IF NOT EXISTS `evaluaciones_crm` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) DEFAULT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `producto_nombre` varchar(150) DEFAULT NULL,
  `puntuacion_satisfaccion` int(11) DEFAULT NULL,
  `comentarios` text DEFAULT NULL,
  `fecha_evaluacion` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_evaluacion_cliente` (`cliente_id`),
  CONSTRAINT `fk_evaluacion_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `evaluaciones_crm` ADD COLUMN IF NOT EXISTS `cliente_id` int(11) NULL DEFAULT NULL;
ALTER TABLE `evaluaciones_crm` ADD COLUMN IF NOT EXISTS `producto_id` int(11) NULL DEFAULT NULL;
ALTER TABLE `evaluaciones_crm` ADD COLUMN IF NOT EXISTS `producto_nombre` varchar(150) NULL DEFAULT NULL;
ALTER TABLE `evaluaciones_crm` ADD COLUMN IF NOT EXISTS `puntuacion_satisfaccion` int(11) NULL DEFAULT NULL;
ALTER TABLE `evaluaciones_crm` ADD COLUMN IF NOT EXISTS `comentarios` text NULL DEFAULT NULL;
ALTER TABLE `evaluaciones_crm` ADD COLUMN IF NOT EXISTS `fecha_evaluacion` datetime NULL DEFAULT current_timestamp();

-- ---------- interacciones ----------
CREATE TABLE IF NOT EXISTS `interacciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `tipo` enum('llamada','correo','reunion','whatsapp','nota') NOT NULL,
  `descripcion` text NOT NULL,
  `estado` enum('completada','pendiente','cancelada') NOT NULL DEFAULT 'completada',
  `prioridad` enum('baja','media','alta') NOT NULL DEFAULT 'media',
  `fecha` datetime DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_interaccion_usuario` (`usuario_id`),
  KEY `idx_interaccion_cliente` (`cliente_id`),
  CONSTRAINT `fk_interaccion_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_interaccion_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `interacciones` ADD COLUMN IF NOT EXISTS `cliente_id` int(11) NOT NULL;
ALTER TABLE `interacciones` ADD COLUMN IF NOT EXISTS `usuario_id` int(11) NOT NULL;
ALTER TABLE `interacciones` ADD COLUMN IF NOT EXISTS `tipo` enum('llamada','correo','reunion','whatsapp','nota') NOT NULL;
ALTER TABLE `interacciones` ADD COLUMN IF NOT EXISTS `descripcion` text NOT NULL;
ALTER TABLE `interacciones` ADD COLUMN IF NOT EXISTS `estado` enum('completada','pendiente','cancelada') NOT NULL DEFAULT 'completada';
ALTER TABLE `interacciones` ADD COLUMN IF NOT EXISTS `prioridad` enum('baja','media','alta') NOT NULL DEFAULT 'media';
ALTER TABLE `interacciones` ADD COLUMN IF NOT EXISTS `fecha` datetime NULL DEFAULT current_timestamp();
ALTER TABLE `interacciones` ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT current_timestamp();

-- ---------- notas_internas_admin ----------
CREATE TABLE IF NOT EXISTS `notas_internas_admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `descuento_porcentaje` varchar(20) DEFAULT '10%',
  `comentario` text NOT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'pendiente',
  `fecha` datetime DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_nota_cliente` (`cliente_id`),
  KEY `idx_nota_usuario` (`usuario_id`),
  CONSTRAINT `fk_nota_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_nota_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `notas_internas_admin` ADD COLUMN IF NOT EXISTS `cliente_id` int(11) NOT NULL;
ALTER TABLE `notas_internas_admin` ADD COLUMN IF NOT EXISTS `usuario_id` int(11) NOT NULL;
ALTER TABLE `notas_internas_admin` ADD COLUMN IF NOT EXISTS `descuento_porcentaje` varchar(20) NULL DEFAULT '10%';
ALTER TABLE `notas_internas_admin` ADD COLUMN IF NOT EXISTS `comentario` text NOT NULL;
ALTER TABLE `notas_internas_admin` ADD COLUMN IF NOT EXISTS `estado` varchar(30) NOT NULL DEFAULT 'pendiente';
ALTER TABLE `notas_internas_admin` ADD COLUMN IF NOT EXISTS `fecha` datetime NULL DEFAULT current_timestamp();
ALTER TABLE `notas_internas_admin` ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT current_timestamp();

-- ---------- notificaciones ----------
CREATE TABLE IF NOT EXISTS `notificaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `titulo` varchar(255) NOT NULL,
  `mensaje` text NOT NULL,
  `tipo` varchar(50) DEFAULT 'actividad_agendada',
  `leido` tinyint(1) DEFAULT 0,
  `fecha` datetime DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE `notificaciones` ADD COLUMN IF NOT EXISTS `usuario_id` int(11) NOT NULL;
ALTER TABLE `notificaciones` ADD COLUMN IF NOT EXISTS `cliente_id` int(11) NULL DEFAULT NULL;
ALTER TABLE `notificaciones` ADD COLUMN IF NOT EXISTS `titulo` varchar(255) NOT NULL;
ALTER TABLE `notificaciones` ADD COLUMN IF NOT EXISTS `mensaje` text NOT NULL;
ALTER TABLE `notificaciones` ADD COLUMN IF NOT EXISTS `tipo` varchar(50) NULL DEFAULT 'actividad_agendada';
ALTER TABLE `notificaciones` ADD COLUMN IF NOT EXISTS `leido` tinyint(1) NULL DEFAULT 0;
ALTER TABLE `notificaciones` ADD COLUMN IF NOT EXISTS `fecha` datetime NULL DEFAULT current_timestamp();
ALTER TABLE `notificaciones` ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT current_timestamp();

-- ---------- pagos ----------
CREATE TABLE IF NOT EXISTS `pagos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pedido_id` int(11) NOT NULL,
  `metodo` enum('paypal','tarjeta','efectivo') NOT NULL,
  `referencia` varchar(255) DEFAULT NULL,
  `monto` decimal(10,2) NOT NULL,
  `estado` enum('pendiente','aprobado','rechazado','reembolsado') NOT NULL DEFAULT 'pendiente',
  `fecha_pago` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pago_pedido` (`pedido_id`),
  CONSTRAINT `fk_pago_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `pagos` ADD COLUMN IF NOT EXISTS `pedido_id` int(11) NOT NULL;
ALTER TABLE `pagos` ADD COLUMN IF NOT EXISTS `metodo` enum('paypal','tarjeta','efectivo') NOT NULL;
ALTER TABLE `pagos` ADD COLUMN IF NOT EXISTS `referencia` varchar(255) NULL DEFAULT NULL;
ALTER TABLE `pagos` ADD COLUMN IF NOT EXISTS `monto` decimal(10,2) NOT NULL;
ALTER TABLE `pagos` ADD COLUMN IF NOT EXISTS `estado` enum('pendiente','aprobado','rechazado','reembolsado') NOT NULL DEFAULT 'pendiente';
ALTER TABLE `pagos` ADD COLUMN IF NOT EXISTS `fecha_pago` datetime NULL DEFAULT NULL;
ALTER TABLE `pagos` ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT current_timestamp();

-- ---------- paquetes ----------
CREATE TABLE IF NOT EXISTS `paquetes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `items` text NOT NULL,
  `featured` tinyint(1) DEFAULT 0,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `paquetes` ADD COLUMN IF NOT EXISTS `name` varchar(100) NOT NULL;
ALTER TABLE `paquetes` ADD COLUMN IF NOT EXISTS `description` text NULL DEFAULT NULL;
ALTER TABLE `paquetes` ADD COLUMN IF NOT EXISTS `price` decimal(10,2) NOT NULL;
ALTER TABLE `paquetes` ADD COLUMN IF NOT EXISTS `items` text NOT NULL;
ALTER TABLE `paquetes` ADD COLUMN IF NOT EXISTS `featured` tinyint(1) NULL DEFAULT 0;
ALTER TABLE `paquetes` ADD COLUMN IF NOT EXISTS `active` tinyint(1) NULL DEFAULT 1;
ALTER TABLE `paquetes` ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT current_timestamp();
ALTER TABLE `paquetes` ADD COLUMN IF NOT EXISTS `updated_at` timestamp NOT NULL DEFAULT current_timestamp() on update current_timestamp();

-- ---------- pedidos ----------
CREATE TABLE IF NOT EXISTS `pedidos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `fecha_pedido` datetime DEFAULT current_timestamp(),
  `estado` enum('pendiente','confirmado','en_proceso','listo','enviado','entregado','cancelado') NOT NULL DEFAULT 'pendiente',
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `envio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `metodo_pago` enum('pendiente','paypal','tarjeta','efectivo') DEFAULT 'pendiente',
  `notas` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pedidos_cliente` (`cliente_id`),
  KEY `idx_pedidos_estado` (`estado`),
  CONSTRAINT `fk_pedido_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `pedidos` ADD COLUMN IF NOT EXISTS `cliente_id` int(11) NOT NULL;
ALTER TABLE `pedidos` ADD COLUMN IF NOT EXISTS `fecha_pedido` datetime NULL DEFAULT current_timestamp();
ALTER TABLE `pedidos` ADD COLUMN IF NOT EXISTS `estado` enum('pendiente','confirmado','en_proceso','listo','enviado','entregado','cancelado') NOT NULL DEFAULT 'pendiente';
ALTER TABLE `pedidos` ADD COLUMN IF NOT EXISTS `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `pedidos` ADD COLUMN IF NOT EXISTS `envio` decimal(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `pedidos` ADD COLUMN IF NOT EXISTS `total` decimal(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `pedidos` ADD COLUMN IF NOT EXISTS `metodo_pago` enum('pendiente','paypal','tarjeta','efectivo') NULL DEFAULT 'pendiente';
ALTER TABLE `pedidos` ADD COLUMN IF NOT EXISTS `notas` text NULL DEFAULT NULL;
ALTER TABLE `pedidos` ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT current_timestamp();
ALTER TABLE `pedidos` ADD COLUMN IF NOT EXISTS `updated_at` timestamp NOT NULL DEFAULT current_timestamp() on update current_timestamp();

-- ---------- productos ----------
CREATE TABLE IF NOT EXISTS `productos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `costo_unitario` decimal(10,2) NOT NULL DEFAULT 0.00,
  `estrategia_logistica` enum('PUSH','PULL') NOT NULL DEFAULT 'PULL',
  `stock_minimo` int(11) NOT NULL DEFAULT 5,
  `category` varchar(50) NOT NULL,
  `proveedor_id` int(11) DEFAULT NULL,
  `features` text DEFAULT NULL,
  `image_icon` varchar(100) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `productos` ADD COLUMN IF NOT EXISTS `name` varchar(100) NOT NULL;
ALTER TABLE `productos` ADD COLUMN IF NOT EXISTS `description` text NULL DEFAULT NULL;
ALTER TABLE `productos` ADD COLUMN IF NOT EXISTS `price` decimal(10,2) NOT NULL;
ALTER TABLE `productos` ADD COLUMN IF NOT EXISTS `costo_unitario` decimal(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `productos` ADD COLUMN IF NOT EXISTS `estrategia_logistica` enum('PUSH','PULL') NOT NULL DEFAULT 'PULL';
ALTER TABLE `productos` ADD COLUMN IF NOT EXISTS `stock_minimo` int(11) NOT NULL DEFAULT 5;
ALTER TABLE `productos` ADD COLUMN IF NOT EXISTS `category` varchar(50) NOT NULL;
ALTER TABLE `productos` ADD COLUMN IF NOT EXISTS `proveedor_id` int(11) NULL DEFAULT NULL;
ALTER TABLE `productos` ADD COLUMN IF NOT EXISTS `features` text NULL DEFAULT NULL;
ALTER TABLE `productos` ADD COLUMN IF NOT EXISTS `image_icon` varchar(100) NULL DEFAULT NULL;
ALTER TABLE `productos` ADD COLUMN IF NOT EXISTS `active` tinyint(1) NULL DEFAULT 1;
ALTER TABLE `productos` ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT current_timestamp();
ALTER TABLE `productos` ADD COLUMN IF NOT EXISTS `updated_at` timestamp NOT NULL DEFAULT current_timestamp() on update current_timestamp();

-- ---------- publicaciones ----------
CREATE TABLE IF NOT EXISTS `publicaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `titulo` varchar(150) NOT NULL,
  `contenido` text NOT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `imagen` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pub_user` (`usuario_id`),
  CONSTRAINT `fk_pub_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `publicaciones` ADD COLUMN IF NOT EXISTS `usuario_id` int(11) NULL DEFAULT NULL;
ALTER TABLE `publicaciones` ADD COLUMN IF NOT EXISTS `titulo` varchar(150) NOT NULL;
ALTER TABLE `publicaciones` ADD COLUMN IF NOT EXISTS `contenido` text NOT NULL;
ALTER TABLE `publicaciones` ADD COLUMN IF NOT EXISTS `precio` decimal(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `publicaciones` ADD COLUMN IF NOT EXISTS `imagen` mediumtext NULL DEFAULT NULL;
ALTER TABLE `publicaciones` ADD COLUMN IF NOT EXISTS `created_at` datetime NULL DEFAULT current_timestamp();
ALTER TABLE `publicaciones` ADD COLUMN IF NOT EXISTS `updated_at` datetime NULL DEFAULT current_timestamp() on update current_timestamp();

-- ---------- scm_inventario ----------
CREATE TABLE IF NOT EXISTS `scm_inventario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_id` int(11) DEFAULT NULL,
  `nombre` varchar(150) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `almacen` varchar(100) NOT NULL,
  `ubicacion` varchar(100) NOT NULL,
  `stock_actual` int(11) NOT NULL DEFAULT 0,
  `stock_minimo` int(11) NOT NULL DEFAULT 10,
  `stock_maximo` int(11) NOT NULL DEFAULT 100,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sku` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `scm_inventario` ADD COLUMN IF NOT EXISTS `producto_id` int(11) NULL DEFAULT NULL;
ALTER TABLE `scm_inventario` ADD COLUMN IF NOT EXISTS `nombre` varchar(150) NOT NULL;
ALTER TABLE `scm_inventario` ADD COLUMN IF NOT EXISTS `sku` varchar(50) NOT NULL;
ALTER TABLE `scm_inventario` ADD COLUMN IF NOT EXISTS `almacen` varchar(100) NOT NULL;
ALTER TABLE `scm_inventario` ADD COLUMN IF NOT EXISTS `ubicacion` varchar(100) NOT NULL;
ALTER TABLE `scm_inventario` ADD COLUMN IF NOT EXISTS `stock_actual` int(11) NOT NULL DEFAULT 0;
ALTER TABLE `scm_inventario` ADD COLUMN IF NOT EXISTS `stock_minimo` int(11) NOT NULL DEFAULT 10;
ALTER TABLE `scm_inventario` ADD COLUMN IF NOT EXISTS `stock_maximo` int(11) NOT NULL DEFAULT 100;
ALTER TABLE `scm_inventario` ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT current_timestamp();
ALTER TABLE `scm_inventario` ADD COLUMN IF NOT EXISTS `updated_at` timestamp NOT NULL DEFAULT current_timestamp() on update current_timestamp();

-- ---------- scm_logistica ----------
CREATE TABLE IF NOT EXISTS `scm_logistica` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoria` varchar(100) NOT NULL,
  `estrategia` enum('PUSH','PULL','HIBRIDO') NOT NULL DEFAULT 'PUSH',
  `punto_despacho` varchar(150) NOT NULL,
  `tiempo_transito` varchar(50) NOT NULL,
  `estado_flujo` varchar(50) NOT NULL DEFAULT 'Flujo Continuo',
  `paqueteria` varchar(100) DEFAULT 'Estafeta / DHL',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `scm_logistica` ADD COLUMN IF NOT EXISTS `categoria` varchar(100) NOT NULL;
ALTER TABLE `scm_logistica` ADD COLUMN IF NOT EXISTS `estrategia` enum('PUSH','PULL','HIBRIDO') NOT NULL DEFAULT 'PUSH';
ALTER TABLE `scm_logistica` ADD COLUMN IF NOT EXISTS `punto_despacho` varchar(150) NOT NULL;
ALTER TABLE `scm_logistica` ADD COLUMN IF NOT EXISTS `tiempo_transito` varchar(50) NOT NULL;
ALTER TABLE `scm_logistica` ADD COLUMN IF NOT EXISTS `estado_flujo` varchar(50) NOT NULL DEFAULT 'Flujo Continuo';
ALTER TABLE `scm_logistica` ADD COLUMN IF NOT EXISTS `paqueteria` varchar(100) NULL DEFAULT 'Estafeta / DHL';
ALTER TABLE `scm_logistica` ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT current_timestamp();

-- ---------- scm_movimientos ----------
CREATE TABLE IF NOT EXISTS `scm_movimientos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `folio` varchar(50) NOT NULL,
  `tipo` enum('entrada','salida','transferencia','ajuste','merma') NOT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `producto_nombre` varchar(150) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `origen` varchar(150) DEFAULT NULL,
  `destino` varchar(150) DEFAULT NULL,
  `usuario` varchar(100) DEFAULT NULL,
  `motivo` text DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `scm_movimientos` ADD COLUMN IF NOT EXISTS `folio` varchar(50) NOT NULL;
ALTER TABLE `scm_movimientos` ADD COLUMN IF NOT EXISTS `tipo` enum('entrada','salida','transferencia','ajuste','merma') NOT NULL;
ALTER TABLE `scm_movimientos` ADD COLUMN IF NOT EXISTS `producto_id` int(11) NULL DEFAULT NULL;
ALTER TABLE `scm_movimientos` ADD COLUMN IF NOT EXISTS `producto_nombre` varchar(150) NOT NULL;
ALTER TABLE `scm_movimientos` ADD COLUMN IF NOT EXISTS `cantidad` int(11) NOT NULL;
ALTER TABLE `scm_movimientos` ADD COLUMN IF NOT EXISTS `origen` varchar(150) NULL DEFAULT NULL;
ALTER TABLE `scm_movimientos` ADD COLUMN IF NOT EXISTS `destino` varchar(150) NULL DEFAULT NULL;
ALTER TABLE `scm_movimientos` ADD COLUMN IF NOT EXISTS `usuario` varchar(100) NULL DEFAULT NULL;
ALTER TABLE `scm_movimientos` ADD COLUMN IF NOT EXISTS `motivo` text NULL DEFAULT NULL;
ALTER TABLE `scm_movimientos` ADD COLUMN IF NOT EXISTS `fecha` datetime NULL DEFAULT current_timestamp();
ALTER TABLE `scm_movimientos` ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT current_timestamp();

-- ---------- scm_pedidos_proveedor ----------
CREATE TABLE IF NOT EXISTS `scm_pedidos_proveedor` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `folio` varchar(50) NOT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `producto_nombre` varchar(150) DEFAULT NULL,
  `proveedor_id` int(11) DEFAULT NULL,
  `proveedor_nombre` varchar(150) NOT NULL,
  `piezas` int(11) NOT NULL DEFAULT 1,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `tipo` varchar(50) NOT NULL DEFAULT 'Reposición',
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `estado` varchar(50) NOT NULL DEFAULT 'Pendiente',
  `fecha_pedido` date DEFAULT NULL,
  `fecha_entrega_estimada` date DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `scm_pedidos_proveedor` ADD COLUMN IF NOT EXISTS `folio` varchar(50) NOT NULL;
ALTER TABLE `scm_pedidos_proveedor` ADD COLUMN IF NOT EXISTS `producto_id` int(11) NULL DEFAULT NULL;
ALTER TABLE `scm_pedidos_proveedor` ADD COLUMN IF NOT EXISTS `producto_nombre` varchar(150) NULL DEFAULT NULL;
ALTER TABLE `scm_pedidos_proveedor` ADD COLUMN IF NOT EXISTS `proveedor_id` int(11) NULL DEFAULT NULL;
ALTER TABLE `scm_pedidos_proveedor` ADD COLUMN IF NOT EXISTS `proveedor_nombre` varchar(150) NOT NULL;
ALTER TABLE `scm_pedidos_proveedor` ADD COLUMN IF NOT EXISTS `piezas` int(11) NOT NULL DEFAULT 1;
ALTER TABLE `scm_pedidos_proveedor` ADD COLUMN IF NOT EXISTS `cantidad` int(11) NOT NULL DEFAULT 1;
ALTER TABLE `scm_pedidos_proveedor` ADD COLUMN IF NOT EXISTS `tipo` varchar(50) NOT NULL DEFAULT 'Reposición';
ALTER TABLE `scm_pedidos_proveedor` ADD COLUMN IF NOT EXISTS `total` decimal(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `scm_pedidos_proveedor` ADD COLUMN IF NOT EXISTS `estado` varchar(50) NOT NULL DEFAULT 'Pendiente';
ALTER TABLE `scm_pedidos_proveedor` ADD COLUMN IF NOT EXISTS `fecha_pedido` date NULL DEFAULT NULL;
ALTER TABLE `scm_pedidos_proveedor` ADD COLUMN IF NOT EXISTS `fecha_entrega_estimada` date NULL DEFAULT NULL;
ALTER TABLE `scm_pedidos_proveedor` ADD COLUMN IF NOT EXISTS `notas` text NULL DEFAULT NULL;
ALTER TABLE `scm_pedidos_proveedor` ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT current_timestamp();

-- ---------- scm_proveedores ----------
CREATE TABLE IF NOT EXISTS `scm_proveedores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `contacto` varchar(150) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `estado_republica` varchar(100) DEFAULT NULL,
  `especialidad` varchar(100) DEFAULT NULL,
  `calificacion` decimal(2,1) DEFAULT 5.0,
  `lead_time_dias` int(11) DEFAULT 5,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `scm_proveedores` ADD COLUMN IF NOT EXISTS `nombre` varchar(150) NOT NULL;
ALTER TABLE `scm_proveedores` ADD COLUMN IF NOT EXISTS `contacto` varchar(150) NULL DEFAULT NULL;
ALTER TABLE `scm_proveedores` ADD COLUMN IF NOT EXISTS `telefono` varchar(50) NULL DEFAULT NULL;
ALTER TABLE `scm_proveedores` ADD COLUMN IF NOT EXISTS `email` varchar(150) NULL DEFAULT NULL;
ALTER TABLE `scm_proveedores` ADD COLUMN IF NOT EXISTS `direccion` varchar(255) NULL DEFAULT NULL;
ALTER TABLE `scm_proveedores` ADD COLUMN IF NOT EXISTS `estado_republica` varchar(100) NULL DEFAULT NULL;
ALTER TABLE `scm_proveedores` ADD COLUMN IF NOT EXISTS `especialidad` varchar(100) NULL DEFAULT NULL;
ALTER TABLE `scm_proveedores` ADD COLUMN IF NOT EXISTS `calificacion` decimal(2,1) NULL DEFAULT 5.0;
ALTER TABLE `scm_proveedores` ADD COLUMN IF NOT EXISTS `lead_time_dias` int(11) NULL DEFAULT 5;
ALTER TABLE `scm_proveedores` ADD COLUMN IF NOT EXISTS `activo` tinyint(1) NULL DEFAULT 1;
ALTER TABLE `scm_proveedores` ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT current_timestamp();

-- ---------- solicitudes_descuento ----------
CREATE TABLE IF NOT EXISTS `solicitudes_descuento` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `trabajador_id` int(11) NOT NULL,
  `porcentaje` int(11) NOT NULL,
  `codigo_cupon` varchar(50) DEFAULT NULL,
  `motivo` text NOT NULL,
  `estado` enum('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
  `admin_id` int(11) DEFAULT NULL,
  `comentario_admin` text DEFAULT NULL,
  `fecha_solicitud` datetime DEFAULT current_timestamp(),
  `fecha_resolucion` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_solicitud_cliente` (`cliente_id`),
  KEY `fk_solicitud_trabajador` (`trabajador_id`),
  KEY `fk_solicitud_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `solicitudes_descuento` ADD COLUMN IF NOT EXISTS `cliente_id` int(11) NOT NULL;
ALTER TABLE `solicitudes_descuento` ADD COLUMN IF NOT EXISTS `trabajador_id` int(11) NOT NULL;
ALTER TABLE `solicitudes_descuento` ADD COLUMN IF NOT EXISTS `porcentaje` int(11) NOT NULL;
ALTER TABLE `solicitudes_descuento` ADD COLUMN IF NOT EXISTS `codigo_cupon` varchar(50) NULL DEFAULT NULL;
ALTER TABLE `solicitudes_descuento` ADD COLUMN IF NOT EXISTS `motivo` text NOT NULL;
ALTER TABLE `solicitudes_descuento` ADD COLUMN IF NOT EXISTS `estado` enum('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente';
ALTER TABLE `solicitudes_descuento` ADD COLUMN IF NOT EXISTS `admin_id` int(11) NULL DEFAULT NULL;
ALTER TABLE `solicitudes_descuento` ADD COLUMN IF NOT EXISTS `comentario_admin` text NULL DEFAULT NULL;
ALTER TABLE `solicitudes_descuento` ADD COLUMN IF NOT EXISTS `fecha_solicitud` datetime NULL DEFAULT current_timestamp();
ALTER TABLE `solicitudes_descuento` ADD COLUMN IF NOT EXISTS `fecha_resolucion` datetime NULL DEFAULT NULL;
ALTER TABLE `solicitudes_descuento` ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT current_timestamp();

-- ---------- subasta_pujas ----------
CREATE TABLE IF NOT EXISTS `subasta_pujas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subasta_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `nombre_postor` varchar(100) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_puja_sub` (`subasta_id`),
  KEY `idx_puja_user` (`usuario_id`),
  CONSTRAINT `fk_puja_subasta` FOREIGN KEY (`subasta_id`) REFERENCES `subastas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_puja_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `subasta_pujas` ADD COLUMN IF NOT EXISTS `subasta_id` int(11) NOT NULL;
ALTER TABLE `subasta_pujas` ADD COLUMN IF NOT EXISTS `usuario_id` int(11) NULL DEFAULT NULL;
ALTER TABLE `subasta_pujas` ADD COLUMN IF NOT EXISTS `nombre_postor` varchar(100) NOT NULL;
ALTER TABLE `subasta_pujas` ADD COLUMN IF NOT EXISTS `monto` decimal(10,2) NOT NULL;
ALTER TABLE `subasta_pujas` ADD COLUMN IF NOT EXISTS `created_at` datetime NULL DEFAULT current_timestamp();

-- ---------- subastas ----------
CREATE TABLE IF NOT EXISTS `subastas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_id` int(11) DEFAULT NULL,
  `titulo` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio_inicial` decimal(10,2) NOT NULL,
  `precio_actual` decimal(10,2) NOT NULL,
  `incremento_minimo` decimal(10,2) NOT NULL DEFAULT 20.00,
  `fecha_inicio` datetime DEFAULT current_timestamp(),
  `fecha_fin` datetime NOT NULL,
  `estado` enum('activa','finalizada','cancelada') NOT NULL DEFAULT 'activa',
  PRIMARY KEY (`id`),
  KEY `idx_sub_prod` (`producto_id`),
  CONSTRAINT `fk_sub_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `subastas` ADD COLUMN IF NOT EXISTS `producto_id` int(11) NULL DEFAULT NULL;
ALTER TABLE `subastas` ADD COLUMN IF NOT EXISTS `titulo` varchar(150) NOT NULL;
ALTER TABLE `subastas` ADD COLUMN IF NOT EXISTS `descripcion` text NULL DEFAULT NULL;
ALTER TABLE `subastas` ADD COLUMN IF NOT EXISTS `precio_inicial` decimal(10,2) NOT NULL;
ALTER TABLE `subastas` ADD COLUMN IF NOT EXISTS `precio_actual` decimal(10,2) NOT NULL;
ALTER TABLE `subastas` ADD COLUMN IF NOT EXISTS `incremento_minimo` decimal(10,2) NOT NULL DEFAULT 20.00;
ALTER TABLE `subastas` ADD COLUMN IF NOT EXISTS `fecha_inicio` datetime NULL DEFAULT current_timestamp();
ALTER TABLE `subastas` ADD COLUMN IF NOT EXISTS `fecha_fin` datetime NOT NULL;
ALTER TABLE `subastas` ADD COLUMN IF NOT EXISTS `estado` enum('activa','finalizada','cancelada') NOT NULL DEFAULT 'activa';

-- ---------- usuarios ----------
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('cliente','admin','trabajador') NOT NULL DEFAULT 'cliente',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `usuarios` ADD COLUMN IF NOT EXISTS `username` varchar(50) NOT NULL;
ALTER TABLE `usuarios` ADD COLUMN IF NOT EXISTS `email` varchar(150) NOT NULL;
ALTER TABLE `usuarios` ADD COLUMN IF NOT EXISTS `password_hash` varchar(255) NOT NULL;
ALTER TABLE `usuarios` ADD COLUMN IF NOT EXISTS `role` enum('cliente','admin','trabajador') NOT NULL DEFAULT 'cliente';
ALTER TABLE `usuarios` ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT current_timestamp();
ALTER TABLE `usuarios` ADD COLUMN IF NOT EXISTS `updated_at` timestamp NOT NULL DEFAULT current_timestamp() on update current_timestamp();
-- ====== Datos semilla SCM (no duplica si ya existen) ======
INSERT IGNORE INTO `scm_proveedores` VALUES (1,'Taller Artesanal Talavera Real','Don Mateo Ramírez','222-491-0391','talaverareal@correo.mx',NULL,'Puebla','Cerámica y Barro',4.9,4,1,'2026-09-23 03:52:28'),(2,'Cooperativa de Tejedoras del Valle','Elena Hernández','951-302-8812','tejedorasvalle@correo.mx',NULL,'Oaxaca','Textiles y Bordados',4.8,6,1,'2026-09-23 03:52:28'),(3,'Maderas y Lacas de Michoacán','Rodrigo Solís','443-810-9284','maderasur@correo.mx','Michoacán, México','Michoacán','Madera y Tallados',4.7,5,1,'2026-09-23 03:52:28'),(4,'Artesanos de Barro Negro','Silvia Bautista','951-678-1122','barronegro@correo.mx',NULL,'Oaxaca','Cerámica y Barro',5.0,3,1,'2026-09-23 03:52:28'),(5,'Cueros y Grabados del Bajío','Carlos Méndez','477-512-3409','bajioartesanal@correo.mx',NULL,'Guanajuato','Cuero y Marroquinería',4.6,7,0,'2026-09-23 03:52:28'),(9,'Artesanías del Sur','Mateo Gómez','961-555-0142','artesanias_sur@contacto.mx','Av. Central 45, Tuxtla Gutiérrez, Chiapas','Chiapas','Textiles y Ámbar',4.9,4,1,'2026-10-01 02:07:09'),(10,'Talleres Cooperativos MX','Beatriz Paredes','771-555-0193','cooperativas@talleresmx.org','Carretera Real del Monte Km 3, Pachuca, Hidalgo','Hidalgo','Platería y Cerámica',4.8,5,1,'2026-10-01 02:07:09'),(11,'Barro y Tradición','Pedro Solís','951-555-0188','pedro@barroytradicion.mx','Calle Reforma 102, San Bartolo Coyotepec, Oaxaca','Oaxaca','Barro Negro y Barro Rojo',5.0,3,1,'2026-10-01 02:07:09'),(12,'Alfarería García','Ignacio García','222-555-0129','ventas@alfareriagarcia.com','Calle 5 de Mayo 204, Puebla, Puebla','Puebla','Talavera Poblana',4.7,6,1,'2026-10-01 02:07:09'),(13,'Textiles Mexicanos','María Luisa Ramos','967-555-0115','mluisa@textilesmexicanos.mx','Andador Real de Guadalupe 33, San Cristóbal, Chiapas','Chiapas','Telares de Cintura y Bordados',4.9,5,1,'2026-10-01 02:07:09');

-- Crea un registro de inventario por cada producto que aún no tenga uno
INSERT IGNORE INTO `scm_inventario`
  (producto_id, nombre, sku, almacen, ubicacion, stock_actual, stock_minimo, stock_maximo)
SELECT p.id, p.name, CONCAT('SCM-', LPAD(p.id, 4, '0')), 'Almacén Central', 'Pasillo A-1',
       0, COALESCE(p.stock_minimo, 5), 100
FROM productos p
LEFT JOIN scm_inventario i ON i.producto_id = p.id
WHERE i.id IS NULL;

SET FOREIGN_KEY_CHECKS=1;
-- Fin de la migración