-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 27-03-2025 a las 18:48:53
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
-- Base de datos: `comseproa_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `almacenes`
--

CREATE TABLE `almacenes` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `ubicacion` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `almacenes`
--

INSERT INTO `almacenes` (`id`, `nombre`, `ubicacion`) VALUES
(3, 'Grupo Seal - Motupe', 'Motupe - Lambayeque'),
(4, 'Grupo Sael - Olmos', 'Olmos - Lambayeque');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id`, `nombre`) VALUES
(2, 'Accesorios de Seguridad'),
(3, 'Kebras y Fundas Nuevas'),
(1, 'Ropa');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `entrega_uniformes`
--

CREATE TABLE `entrega_uniformes` (
  `id` int(11) NOT NULL,
  `usuario_responsable_id` int(11) NOT NULL,
  `nombre_destinatario` varchar(100) NOT NULL,
  `dni_destinatario` varchar(8) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `almacen_id` int(11) NOT NULL,
  `fecha_entrega` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos`
--

CREATE TABLE `movimientos` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `almacen_origen` int(11) DEFAULT NULL,
  `almacen_destino` int(11) DEFAULT NULL,
  `cantidad` int(11) NOT NULL,
  `tipo` enum('entrada','salida','transferencia') NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario_id` int(11) NOT NULL,
  `estado` enum('pendiente','completado','rechazado') DEFAULT 'pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  `almacen_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `talla_dimensiones` varchar(50) DEFAULT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 0,
  `unidad_medida` varchar(50) DEFAULT NULL,
  `estado` enum('Nuevo','Usado','Dañado') NOT NULL,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `categoria_id`, `almacen_id`, `nombre`, `descripcion`, `modelo`, `color`, `talla_dimensiones`, `cantidad`, `unidad_medida`, `estado`, `observaciones`) VALUES
(129, 2, 3, 'Portapistola', NULL, NULL, 'Negro', NULL, 2, 'Unidad', 'Nuevo', ''),
(130, 2, 3, 'Correajes', NULL, NULL, 'Negro', NULL, 14, 'Unidad', 'Nuevo', ''),
(131, 2, 3, 'Cascos de Seguridad', NULL, NULL, 'Blanco', NULL, 6, 'Unidad', 'Nuevo', 'INGRESO EL 17/03'),
(132, 2, 3, 'Lentes', NULL, NULL, NULL, NULL, 6, 'Unidad', 'Nuevo', 'INGRESO EL 17/03'),
(133, 2, 3, 'Guantes', NULL, NULL, NULL, NULL, 2, 'Pares', 'Nuevo', 'INGRESO EL 17/03'),
(134, 3, 3, 'Fundas', NULL, NULL, 'Azul', 'L', 16, 'Unidad', 'Nuevo', ''),
(161, 1, 3, 'Camisa Polipima', NULL, 'Manga larga', 'Blanco', 'XXL', 1, 'Unidad', 'Nuevo', NULL),
(162, 1, 3, 'Polera M/L', NULL, 'Manga larga', 'Plomo', 'M', 5, 'Unidad', 'Nuevo', 'INGRESO 21/03/2025'),
(163, 1, 3, 'Polera M/L', NULL, 'Manga larga', 'Plomo', 'L', 1, 'Unidad', 'Nuevo', 'INGRESO 21/03/2025'),
(164, 1, 3, 'Polera M/L', NULL, 'Manga larga', 'Plomo', 'S', 3, 'Unidad', 'Nuevo', 'INGRESO 21/03/2025'),
(165, 1, 3, 'Polera M/L', NULL, 'Manga larga', 'Plomo', 'XL', 3, 'Unidad', 'Nuevo', 'INGRESO 21/03/2025'),
(166, 1, 3, 'Pantalon drill', NULL, 'Varon', 'Azul', '34', 5, 'Unidad', 'Nuevo', NULL),
(167, 1, 3, 'Pantalon drill', NULL, 'Varon', 'Azul', '36', 3, 'Unidad', 'Nuevo', NULL),
(168, 1, 3, 'Pantalon drill', NULL, 'Varon', 'Azul', '38', 3, 'Unidad', 'Nuevo', NULL),
(169, 1, 3, 'Pantalon tactico cargo', NULL, 'Varon', 'Azul', '32', 1, 'Unidad', 'Nuevo', NULL),
(170, 1, 3, 'Pantalon tactico cargo', NULL, 'Varon', 'Azul', '36', 1, 'Unidad', 'Nuevo', NULL),
(171, 1, 3, 'Pantalon tactico cargo', NULL, 'Varon', 'Azul', '34', 2, 'Unidad', 'Nuevo', NULL),
(172, 1, 3, 'Pantalon tactico cargo', NULL, 'Varon', 'Azul', 'L', 126, 'Unidad', 'Nuevo', 'SIN BOTON'),
(173, 1, 3, 'Pantalon azul c/r', NULL, 'Varon', 'Azul', 'M', 20, 'Unidad', 'Nuevo', NULL),
(174, 1, 3, 'Pantalon azul c/r', NULL, 'Varon', 'Azul', 'L', 59, 'Unidad', 'Nuevo', NULL),
(175, 1, 3, 'Pantalon azul c/r', NULL, 'Varon', 'Azul', 'XL', 15, 'Unidad', 'Nuevo', NULL),
(176, 1, 3, 'Polo camisero TACTICO M/L', NULL, 'Varon', 'Azul', 'L', 7, 'Unidad', 'Nuevo', NULL),
(177, 1, 3, 'Chaleco supervisor', NULL, 'Varon', 'Plomo', 'L', 1, 'Unidad', 'Nuevo', NULL),
(178, 1, 3, 'Casaca Drill', NULL, 'Varon', 'Azul', 'L', 10, 'Unidad', 'Nuevo', NULL),
(179, 1, 3, 'Gorras', NULL, 'Varon', 'Azul', NULL, 13, 'Unidad', 'Nuevo', NULL),
(180, 1, 3, 'Corbatas', NULL, 'Varon', 'Guinda', NULL, 19, 'Unidad', 'Nuevo', NULL),
(181, 1, 3, 'Corbatas', NULL, 'Varon', 'Marrones', NULL, 21, 'Unidad', 'Nuevo', NULL),
(182, 1, 3, 'Borseguis', NULL, 'Varon', 'Negro', '41', 1, 'pares', 'Nuevo', NULL),
(183, 1, 3, 'Borseguis', NULL, 'Varon', 'Negro', '43', 2, 'par', 'Nuevo', 'Ingreso el 17/03'),
(184, 1, 3, 'Borseguis', NULL, 'Varon', 'Negro', '42', 2, 'Pares', 'Nuevo', ''),
(185, 1, 3, 'Zapatos Corfan', NULL, 'Varon', 'Negro', '42', 2, 'pares', 'Usado', ''),
(186, 1, 4, 'Pantalon tactico cargo', NULL, 'Varon', 'Azul', '32', 1, 'Unidad', 'Nuevo', NULL),
(187, 1, 4, 'Pantalon tactico cargo', NULL, 'Varon', 'Azul', 'L', 8, 'Unidad', 'Nuevo', 'SIN BOTON'),
(188, 1, 4, 'Polera M/L', NULL, 'Manga larga', 'Plomo', 'L', 2, 'Unidad', 'Nuevo', 'INGRESO 21/03/2025');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitudes_transferencia`
--

CREATE TABLE `solicitudes_transferencia` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `almacen_origen` int(11) NOT NULL,
  `almacen_destino` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `fecha_solicitud` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado` enum('pendiente','aprobada','rechazada') DEFAULT 'pendiente',
  `usuario_aprobador_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(64) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `dni` varchar(8) NOT NULL,
  `celular` varchar(15) NOT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `correo` varchar(100) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `almacen_id` int(11) DEFAULT NULL,
  `rol` enum('admin','almacenero') NOT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `apellidos`, `dni`, `celular`, `direccion`, `correo`, `contrasena`, `almacen_id`, `rol`, `estado`, `fecha_registro`) VALUES
(8, 'Jhamir Alexander', 'Silva Baldera', '71749437', '982566142', 'San Julian 664 - Motupe', 'jhamirsilva@gmail.com', '$2y$10$yG9ldNFttY94fCt/FZXx/OTuaBGPmD/rkvniTmpYFa9ZPotDIRfZ.', 3, 'admin', 'activo', '2025-03-24 13:38:41');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `almacenes`
--
ALTER TABLE `almacenes`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `entrega_uniformes`
--
ALTER TABLE `entrega_uniformes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_responsable_id` (`usuario_responsable_id`),
  ADD KEY `producto_id` (`producto_id`),
  ADD KEY `almacen_id` (`almacen_id`);

--
-- Indices de la tabla `movimientos`
--
ALTER TABLE `movimientos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `producto_id` (`producto_id`),
  ADD KEY `almacen_origen` (`almacen_origen`),
  ADD KEY `almacen_destino` (`almacen_destino`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_producto_almacen` (`nombre`,`color`,`talla_dimensiones`,`almacen_id`),
  ADD KEY `categoria_id` (`categoria_id`),
  ADD KEY `almacen_id` (`almacen_id`);

--
-- Indices de la tabla `solicitudes_transferencia`
--
ALTER TABLE `solicitudes_transferencia`
  ADD PRIMARY KEY (`id`),
  ADD KEY `producto_id` (`producto_id`),
  ADD KEY `almacen_origen` (`almacen_origen`),
  ADD KEY `almacen_destino` (`almacen_destino`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `fk_usuario_aprobador` (`usuario_aprobador_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dni` (`dni`),
  ADD UNIQUE KEY `correo` (`correo`),
  ADD KEY `almacen_id` (`almacen_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `almacenes`
--
ALTER TABLE `almacenes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `entrega_uniformes`
--
ALTER TABLE `entrega_uniformes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `movimientos`
--
ALTER TABLE `movimientos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=189;

--
-- AUTO_INCREMENT de la tabla `solicitudes_transferencia`
--
ALTER TABLE `solicitudes_transferencia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `entrega_uniformes`
--
ALTER TABLE `entrega_uniformes`
  ADD CONSTRAINT `entrega_uniformes_ibfk_1` FOREIGN KEY (`usuario_responsable_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `entrega_uniformes_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `entrega_uniformes_ibfk_3` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `movimientos`
--
ALTER TABLE `movimientos`
  ADD CONSTRAINT `movimientos_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `movimientos_ibfk_2` FOREIGN KEY (`almacen_origen`) REFERENCES `almacenes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `movimientos_ibfk_3` FOREIGN KEY (`almacen_destino`) REFERENCES `almacenes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `movimientos_ibfk_4` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `productos`
--
ALTER TABLE `productos`
  ADD CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `productos_ibfk_2` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `solicitudes_transferencia`
--
ALTER TABLE `solicitudes_transferencia`
  ADD CONSTRAINT `fk_usuario_aprobador` FOREIGN KEY (`usuario_aprobador_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `solicitudes_transferencia_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `solicitudes_transferencia_ibfk_2` FOREIGN KEY (`almacen_origen`) REFERENCES `almacenes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `solicitudes_transferencia_ibfk_3` FOREIGN KEY (`almacen_destino`) REFERENCES `almacenes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `solicitudes_transferencia_ibfk_4` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
