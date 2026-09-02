-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 02-09-2026 a las 19:32:52
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `lym`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `nombre` varchar(150) NOT NULL,
  `correo` varchar(150) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `empresa` varchar(150) DEFAULT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `etapa_crm` enum('Prospecto','Activo','Frecuente','Inactivo') NOT NULL DEFAULT 'Prospecto',
  `fecha_registro` datetime DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id`, `usuario_id`, `nombre`, `correo`, `telefono`, `empresa`, `estado`, `etapa_crm`, `fecha_registro`, `created_at`, `updated_at`) VALUES
(1, 2, 'juanlalo', 'juanlalo@gmail.com', NULL, NULL, 'activo', 'Prospecto', '2026-09-01 14:06:11', '2026-09-01 20:06:11', '2026-09-01 20:06:11'),
(2, 3, 'dfghjkl', '1@gmail.com', NULL, NULL, 'activo', 'Prospecto', '2026-09-02 11:24:46', '2026-09-02 17:24:46', '2026-09-02 17:24:46');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_pedido`
--

CREATE TABLE `detalle_pedido` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `paquete_id` int(11) DEFAULT NULL,
  `nombre_producto` varchar(150) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `evaluaciones_crm`
--

CREATE TABLE `evaluaciones_crm` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `puntuacion_satisfaccion` int(11) DEFAULT NULL,
  `comentarios` text DEFAULT NULL,
  `fecha_evaluacion` datetime DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `interacciones`
--

CREATE TABLE `interacciones` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `tipo` enum('llamada','correo','reunion','whatsapp','nota') NOT NULL,
  `descripcion` text NOT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos`
--

CREATE TABLE `pagos` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `metodo` enum('paypal','tarjeta','efectivo') NOT NULL,
  `referencia` varchar(255) DEFAULT NULL,
  `monto` decimal(10,2) NOT NULL,
  `estado` enum('pendiente','aprobado','rechazado','reembolsado') NOT NULL DEFAULT 'pendiente',
  `fecha_pago` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `paquetes`
--

CREATE TABLE `paquetes` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `items` text NOT NULL,
  `featured` tinyint(1) DEFAULT 0,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `paquetes`
--

INSERT INTO `paquetes` (`id`, `name`, `description`, `price`, `items`, `featured`, `active`, `created_at`, `updated_at`) VALUES
(1, 'Paquete Básico', 'Paquete básico con productos esenciales', 250.00, '[\"1 Taza estándar\",\"1 Llavero de acero\",\"1 Rompecabezas tamaño carta\",\"Diseño personalizado\"]', 0, 1, '2026-09-01 18:36:29', '2026-09-01 18:36:29'),
(2, 'Paquete Viajero', 'Paquete ideal para viajeros', 320.00, '[\"1 Bolsa ecológica chica\",\"2 Llaveros de acero\",\"1 Cojín 20 x 30 cm\",\"Diseño personalizado\"]', 1, 1, '2026-09-01 18:36:29', '2026-09-01 18:36:29'),
(3, 'Paquete Deportivo', 'Paquete para deportistas', 575.00, '[\"1 Playera deportiva Dryfit\",\"1 Gorra combinada\",\"1 Vaso alto de acero\",\"Diseño personalizado\"]', 0, 1, '2026-09-01 18:36:29', '2026-09-01 18:36:29'),
(4, 'Paquete Potterhead', 'Paquete temático', 635.00, '[\"1 Playera deportiva Dryfit\",\"1 Termo cafetero\",\"1 Llavero de acero\",\"Diseño personalizado\"]', 0, 1, '2026-09-01 18:36:29', '2026-09-01 18:36:29');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos`
--

CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `fecha_pedido` datetime DEFAULT current_timestamp(),
  `estado` enum('pendiente','confirmado','en_proceso','listo','enviado','entregado','cancelado') NOT NULL DEFAULT 'pendiente',
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `envio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `metodo_pago` enum('pendiente','paypal','tarjeta','efectivo') DEFAULT 'pendiente',
  `notas` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `category` varchar(50) NOT NULL,
  `features` text DEFAULT NULL,
  `image_icon` varchar(100) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `name`, `description`, `price`, `category`, `features`, `image_icon`, `active`, `created_at`, `updated_at`) VALUES
(1, 'Taza Estándar', 'Taza estándar de cerámica personalizada', 85.00, 'tazas', 'Taza estándar de cerámica|Taza con asa de color|Taza mágica|Par de tazas corazón|Taza recta grande', 'fas fa-mug-hot', 1, '2026-09-01 18:36:29', '2026-09-01 18:36:29'),
(2, 'Tapete Afelpado', 'Tapete blanco afelpado suave para interior', 300.00, 'tapetes', 'Tapete blanco afelpado|Revés antideslizante|34 x 58 cm|Impresión personalizada', 'fas fa-home', 1, '2026-09-01 18:36:29', '2026-09-01 18:36:29'),
(3, 'Sudaderas', 'Sudaderas personalizadas de algodón', 425.00, 'sudaderas', 'Sudadera sencilla|Sudadera con gorra|Algodón|Cuello redondo|Diseño personalizado', 'fas fa-tshirt', 1, '2026-09-01 18:36:29', '2026-09-01 18:36:29'),
(4, 'Camisas Uniforme', 'Camisas de uniforme en gabardina peinada', 195.00, 'uniformes', 'Gabardina peinada|Dama y caballero|CH|M|G|EG|2XL|3XL|4XL', 'fas fa-user-tie', 1, '2026-09-01 18:36:29', '2026-09-01 18:36:29');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('cliente','admin') NOT NULL DEFAULT 'cliente',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `username`, `email`, `password_hash`, `role`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@lym.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '2026-09-01 18:36:29', '2026-09-01 18:36:29'),
(2, 'juanlalo', 'juanlalo@gmail.com', '$2y$10$wTUEuENuP2qv2tgcFkoBju1rW4Wko1.KcOOqD/oNZat7JrczH656C', 'admin', '2026-09-01 20:06:11', '2026-09-02 16:44:24'),
(3, 'dfghjkl', '1@gmail.com', '$2y$10$rgfL3xphzjFa5hnLjxjhrOMUCZZubIdwM.Y5D6X.FRO5iC6FuIOGW', 'cliente', '2026-09-02 17:24:46', '2026-09-02 17:24:46');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `correo` (`correo`),
  ADD KEY `fk_cliente_usuario` (`usuario_id`),
  ADD KEY `idx_clientes_estado` (`estado`),
  ADD KEY `idx_clientes_etapa` (`etapa_crm`);

--
-- Indices de la tabla `detalle_pedido`
--
ALTER TABLE `detalle_pedido`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_detalle_producto` (`producto_id`),
  ADD KEY `fk_detalle_paquete` (`paquete_id`),
  ADD KEY `idx_detalle_pedido` (`pedido_id`);

--
-- Indices de la tabla `evaluaciones_crm`
--
ALTER TABLE `evaluaciones_crm`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_evaluacion_cliente` (`cliente_id`);

--
-- Indices de la tabla `interacciones`
--
ALTER TABLE `interacciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_interaccion_usuario` (`usuario_id`),
  ADD KEY `idx_interaccion_cliente` (`cliente_id`);

--
-- Indices de la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pago_pedido` (`pedido_id`);

--
-- Indices de la tabla `paquetes`
--
ALTER TABLE `paquetes`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pedidos_cliente` (`cliente_id`),
  ADD KEY `idx_pedidos_estado` (`estado`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `detalle_pedido`
--
ALTER TABLE `detalle_pedido`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `evaluaciones_crm`
--
ALTER TABLE `evaluaciones_crm`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `interacciones`
--
ALTER TABLE `interacciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pagos`
--
ALTER TABLE `pagos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `paquetes`
--
ALTER TABLE `paquetes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD CONSTRAINT `fk_cliente_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `detalle_pedido`
--
ALTER TABLE `detalle_pedido`
  ADD CONSTRAINT `fk_detalle_paquete` FOREIGN KEY (`paquete_id`) REFERENCES `paquetes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_detalle_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_detalle_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `evaluaciones_crm`
--
ALTER TABLE `evaluaciones_crm`
  ADD CONSTRAINT `fk_evaluacion_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `interacciones`
--
ALTER TABLE `interacciones`
  ADD CONSTRAINT `fk_interaccion_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_interaccion_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD CONSTRAINT `fk_pago_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD CONSTRAINT `fk_pedido_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
